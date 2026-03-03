<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/layout.php';
requireLogin();

$db     = Database::getInstance();
$page   = max(1,(int)($_GET['page']??1));
$perPage= 15;
$filter = $_GET['filter']??'';
$search = trim($_GET['search']??'');

$where  = ['p.status IN (\'aktif\',\'lunas\')'];
$params = [];

if ($search) { 
    $where[] = "(a.nama_lengkap LIKE ? OR p.no_pinjaman LIKE ?)"; 
    $params = ["%$search%","%$search%"]; 
}

if ($filter === 'telat') { 
    $where[] = "ja.jatuh_tempo < CURDATE() AND ja.status IN ('belum','sebagian')"; 
} elseif ($filter === 'hari_ini') { 
    $where[] = "ja.jatuh_tempo = CURDATE() AND ja.status = 'belum'"; 
} elseif ($filter === 'belum_bayar') { 
    $where[] = "(ja.status = 'belum' OR ja.status = 'sebagian')"; 
} elseif ($filter === 'sudah_bayar') { 
    $where[] = "ja.status = 'bayar'"; 
}

$whereStr = implode(' AND ', $where);

// Get total records
$total = (int)$db->fetch(
    "SELECT COUNT(DISTINCT ja.id) c 
     FROM jadwal_angsuran ja 
     JOIN pinjaman p ON p.id=ja.pinjaman_id 
     JOIN anggota a ON a.id=p.anggota_id 
     WHERE $whereStr", 
    $params
)['c'];

$pg = paginate($total, $perPage, $page, '?');

// Get angsuran data
$list = $db->fetchAll(
    "SELECT ja.*, p.no_pinjaman, p.jumlah_pokok, p.suku_bunga, p.jenis_bunga, p.tenor_bulan, p.total_terbayar,
            a.nama_lengkap, a.no_anggota,
            DATEDIFF(CURDATE(), ja.jatuh_tempo) AS hari_telat,
            COALESCE((SELECT SUM(jumlah) FROM pembayaran_angsuran WHERE jadwal_id=ja.id),0) AS sudah_bayar
     FROM jadwal_angsuran ja
     JOIN pinjaman p ON p.id=ja.pinjaman_id
     JOIN anggota a ON a.id=p.anggota_id
     WHERE $whereStr
     ORDER BY ja.jatuh_tempo ASC, p.no_pinjaman ASC, ja.angsuran_ke ASC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);

// Get loans for payment modal
$pinjamanList = $db->fetchAll(
    "SELECT p.id, p.no_pinjaman, a.nama_lengkap, a.no_anggota, p.jumlah_pokok, p.total_terbayar,
            COALESCE((SELECT SUM(total_bayar) FROM jadwal_angsuran WHERE pinjaman_id=p.id),0) AS total_jadwal
     FROM pinjaman p
     JOIN anggota a ON a.id=p.anggota_id
     WHERE p.status IN ('aktif','lunas')
     ORDER BY p.no_pinjaman ASC"
);

// Get all jadwal angsuran for active loans (for modal selection)
$allJadwal = $db->fetchAll(
    "SELECT ja.id, ja.pinjaman_id, ja.angsuran_ke, ja.jatuh_tempo, ja.total_bayar, ja.terbayar, ja.status, p.tenor_bulan,
            DATEDIFF(CURDATE(), ja.jatuh_tempo) AS hari_telat
     FROM jadwal_angsuran ja
     JOIN pinjaman p ON p.id=ja.pinjaman_id
     WHERE p.status IN ('aktif','lunas')
     ORDER BY ja.jatuh_tempo ASC"
);

renderHeader('Kelola Angsuran','angsuran');
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
  <h2 style="font-size:18px;font-weight:800;color:var(--primary);">Kelola Angsuran</h2>
  <?php if(hasRole(['admin','manager','kasir'])): ?>
  <button class="btn btn-primary" onclick="openModal('modalBayar')">
    <i class="fas fa-money-bill-wave"></i> Bayar Angsuran
  </button>
  <?php endif; ?>
</div>

<!-- Stats Cards -->
<div class="stats-grid" style="margin-bottom:20px;">
  <?php 
  $statsTelat = (int)$db->fetch("SELECT COUNT(*) c FROM jadwal_angsuran ja JOIN pinjaman p ON p.id=ja.pinjaman_id WHERE p.status IN ('aktif','lunas') AND ja.jatuh_tempo<CURDATE() AND ja.status IN ('belum','sebagian')")['c'];
  $statsHariIni = (int)$db->fetch("SELECT COUNT(*) c FROM jadwal_angsuran ja JOIN pinjaman p ON p.id=ja.pinjaman_id WHERE p.status IN ('aktif','lunas') AND ja.jatuh_tempo=CURDATE() AND ja.status='belum'")['c'];
  $statsBelum = (int)$db->fetch("SELECT COUNT(*) c FROM jadwal_angsuran ja JOIN pinjaman p ON p.id=ja.pinjaman_id WHERE p.status IN ('aktif','lunas') AND (ja.status='belum' OR ja.status='sebagian')")['c'];
  $statsTotal = (float)$db->fetch("SELECT COALESCE(SUM(ja.total_bayar-ja.terbayar),0) c FROM jadwal_angsuran ja JOIN pinjaman p ON p.id=ja.pinjaman_id WHERE p.status IN ('aktif','lunas') AND ja.status!='bayar'")['c'];
  ?>
  <div class="stat-card" style="border-left:4px solid var(--danger);">
    <div class="stat-icon" style="background:#fce4e4;color:var(--danger);"><i class="fas fa-exclamation-triangle"></i></div>
    <div class="stat-info">
      <small>Angsuran Terlambat</small>
      <strong><?= $statsTelat ?></strong>
    </div>
  </div>
  <div class="stat-card" style="border-left:4px solid var(--warning);">
    <div class="stat-icon" style="background:#fff3e0;color:var(--warning);"><i class="fas fa-calendar-day"></i></div>
    <div class="stat-info">
      <small>Jatuh Tempo Hari Ini</small>
      <strong><?= $statsHariIni ?></strong>
    </div>
  </div>
  <div class="stat-card" style="border-left:4px solid var(--info);">
    <div class="stat-icon" style="background:#e3f2fd;color:var(--info);"><i class="fas fa-clock"></i></div>
    <div class="stat-info">
      <small>Belum Dibayar</small>
      <strong><?= $statsBelum ?></strong>
    </div>
  </div>
  <div class="stat-card" style="border-left:4px solid var(--primary);">
    <div class="stat-icon" style="background:#e8f4fc;color:var(--primary);"><i class="fas fa-wallet"></i></div>
    <div class="stat-info">
      <small>Total Tagihan</small>
      <strong><?= rupiah($statsTotal) ?></strong>
    </div>
  </div>
</div>

<!-- Filter tabs -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
  <?php foreach([
    [''         ,'Semua','secondary'],
    ['telat'    ,'Terlambat','danger'],
    ['hari_ini' ,'Hari Ini','warning'],
    ['belum_bayar','Belum Bayar','info'],
    ['sudah_bayar','Sudah Bayar','success'],
  ] as [$val,$label,$color]): ?>
  <a href="?filter=<?=$val?>&search=<?=urlencode($search)?>" class="btn btn-sm <?=$filter===$val?'btn-primary':'btn-outline'?>"><?=$label?></a>
  <?php endforeach; ?>
</div>

<!-- Search -->
<div class="card" style="margin-bottom:16px;"><div class="card-body" style="padding:14px;">
  <form method="get" style="display:flex;gap:10px;">
    <input type="hidden" name="filter" value="<?=h($filter)?>">
    <input type="text" name="search" class="form-control" placeholder="Cari nama anggota / no. pinjaman..." value="<?=h($search)?>" style="flex:1">
    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
    <a href="?" class="btn btn-outline"><i class="fas fa-redo"></i></a>
  </form>
</div></div>

<!-- Angsuran Table -->
<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>No. Pinjaman</th>
          <th>Anggota</th>
          <th>Angsuran Ke</th>
          <th>Jatuh Tempo</th>
          <th class="text-right">Tagihan</th>
          <th class="text-right">Terbayar</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($list)): ?>
          <tr>
            <td colspan="8" class="text-center" style="padding:40px;color:var(--text-muted);">
              Tidak ada data angsuran
            </td>
          </tr>
        <?php else: foreach($list as $row):
          $isTelat = $row['hari_telat'] > 0 && $row['status'] !== 'bayar';
          $statusConfig = [
            'belum'    => ['warning','fas fa-clock','Belum Bayar'],
            'bayar'    => ['success','fas fa-check-circle','Lunas'],
            'sebagian' => ['info','fas fa-minus-circle','Sebagian'],
            'telat'    => ['danger','fas fa-exclamation-circle','Terlambat'],
          ][$row['status']] ?? ['secondary','fas fa-circle',ucfirst($row['status'])];
          
          if ($isTelat && $row['status'] === 'belum') {
            $statusConfig = ['danger','fas fa-exclamation-circle','Terlambat'];
          }
        ?>
          <tr class="<?= $isTelat ? 'table-danger' : '' ?>" style="<?= $isTelat ? 'background:#fff5f5;' : '' ?>">
            <td>
              <span class="font-mono" style="font-size:12px;background:var(--bg);padding:3px 8px;border-radius:6px;font-weight:600;">
                <?=h($row['no_pinjaman'])?>
              </span>
            </td>
            <td>
              <div style="font-weight:600;font-size:13px;"><?=h($row['nama_lengkap'])?></div>
              <div style="font-size:11px;color:var(--text-muted);"><?=h($row['no_anggota'])?></div>
            </td>
            <td>
              <span class="badge badge-secondary">Ke-<?=$row['angsuran_ke']?></span>
              <span style="font-size:11px;color:var(--text-muted);">/ <?=$row['tenor_bulan']?> bln</span>
            </td>
            <td>
              <div style="font-weight:600;"><?=tglIndo($row['jatuh_tempo'])?></div>
              <?php if($isTelat): ?>
                <div style="font-size:11px;color:var(--danger);font-weight:600;">
                  <i class="fas fa-exclamation-triangle"></i> Terlambat <?=$row['hari_telat']?> hari
                </div>
              <?php endif; ?>
            </td>
            <td class="text-right">
              <strong style="font-size:14px;"><?=rupiah($row['total_bayar'])?></strong>
              <div style="font-size:10px;color:var(--text-muted);">
                Pokok: <?=rupiah($row['pokok'])?> | Bunga: <?=rupiah($row['bunga'])?>
              </div>
            </td>
            <td class="text-right">
              <?php if($row['status'] === 'bayar'): ?>
                <strong style="font-size:14px;color:var(--success);"><?=rupiah($row['terbayar'])?></strong>
                <div style="font-size:11px;color:var(--text-muted);"><?=tglIndo($row['tanggal_bayar'])?></div>
              <?php else: ?>
                <span style="color:var(--text-muted);font-size:14px;"><?=rupiah($row['terbayar'])?></span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge badge-<?=$statusConfig[0]?>">
                <i class="<?=$statusConfig[1]?>" style="margin-right:4px;font-size:10px;"></i>
                <?=$statusConfig[2]?>
              </span>
            </td>
            <td>
              <?php if($row['status'] !== 'bayar' && hasRole(['admin','manager','kasir'])): ?>
              <button class="btn btn-xs btn-success" 
                      onclick="bayarAngsuran(<?=$row['id']?>, <?=$row['pinjaman_id']?>, <?=$row['angsuran_ke']?>, <?=$row['total_bayar']?>, <?=$row['terbayar']?>, '<?=h($row['no_pinjaman'])?>')">
                <i class="fas fa-money-bill"></i> Bayar
              </button>
              <?php else: ?>
              <button class="btn btn-xs btn-outline" onclick="lihatKwitansi(<?=$row['id']?>)" title="Lihat Kwitansi">
                <i class="fas fa-receipt"></i>
              </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  
  <!-- Pagination -->
  <?php if($pg['total_pages'] > 1): ?>
  <div style="padding:16px;border-top:1px solid var(--border);display:flex;justify-content:center;">
    <div class="pagination">
      <?php if($pg['prev']): ?>
        <a href="?page=<?=$pg['prev']?>&filter=<?=h($filter)?>&search=<?=urlencode($search)?>" class="page-link"><i class="fas fa-chevron-left"></i></a>
      <?php endif; ?>
      
      <?php for($i=1;$i<=$pg['total_pages'];$i++): ?>
        <?php if($i==1 || $i==$pg['total_pages'] || ($i>=$pg['current']-2 && $i<=$pg['current']+2)): ?>
          <a href="?page=<?=$i?>&filter=<?=h($filter)?>&search=<?=urlencode($search)?>" class="page-link <?=$i===$pg['current']?'active':''?>"><?=$i?></a>
        <?php elseif($i===$pg['current']-3 || $i===$pg['current']+3): ?>
          <span style="padding:0 8px;color:var(--text-muted);">...</span>
        <?php endif; ?>
      <?php endfor; ?>
      
      <?php if($pg['next']): ?>
        <a href="?page=<?=$pg['next']?>&filter=<?=h($filter)?>&search=<?=urlencode($search)?>" class="page-link"><i class="fas fa-chevron-right"></i></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ═══ MODAL: Bayar Angsuran (by Pinjaman) ═══════════════════════════════ -->
<div class="modal-overlay" id="modalBayar">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-money-bill-wave" style="color:var(--success)"></i> Bayar Angsuran</div>
      <button class="btn-close" onclick="closeModal('modalBayar')">&times;</button>
    </div>
    <div class="modal-body">
      <!-- Select Pinjaman -->
      <div class="form-group">
        <label class="form-label">Pinjaman <span class="req">*</span></label>
        <select id="selectPinjaman" class="form-control form-select" onchange="loadJadwalAngsuran()">
          <option value="">— Pilih Pinjaman —</option>
          <?php foreach($pinjamanList as $p): 
            $sisa = $p['total_jadwal'] - $p['total_terbayar'];
          ?>
          <option value="<?=$p['id']?>"><?=h($p['no_pinjaman'])?> - <?=h($p['nama_lengkap'])?> (Sisa: <?=rupiah($sisa)?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <!-- Jadwal List -->
      <div id="jadwalList" style="display:none;">
        <div class="form-group">
          <label class="form-label">Pilih Angsuran yang Akan Dibayar</label>
          <div id="jadwalOptions" style="max-height:250px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:10px;">
            <!-- Will be populated by JS -->
          </div>
        </div>
        
        <!-- Payment Form -->
        <div id="paymentForm" style="display:none;background:var(--bg);border-radius:10px;padding:16px;margin-top:16px;">
          <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px;">
            <div style="flex:1;min-width:150px;">
              <div style="font-size:11px;color:var(--text-muted);">Angsuran Ke</div>
              <div id="payAngsuranKe" style="font-size:16px;font-weight:700;color:var(--primary);">-</div>
            </div>
            <div style="flex:1;min-width:150px;">
              <div style="font-size:11px;color:var(--text-muted);">Jatuh Tempo</div>
              <div id="payJatuhTempo" style="font-size:16px;font-weight:700;">-</div>
            </div>
            <div style="flex:1;min-width:150px;">
              <div style="font-size:11px;color:var(--text-muted);">Total Tagihan</div>
              <div id="payTagihan" style="font-size:16px;font-weight:700;color:var(--danger);">-</div>
            </div>
            <div style="flex:1;min-width:150px;">
              <div style="font-size:11px;color:var(--text-muted);">Sudah Dibayar</div>
              <div id="payTerbayar" style="font-size:16px;font-weight:700;color:var(--success);">-</div>
            </div>
          </div>
          
          <form method="post" action="save.php">
            <input type="hidden" name="action" value="bayar">
            <input type="hidden" name="jadwal_id" id="payJadwalId">
            <input type="hidden" name="pinjaman_id" id="payPinjamanId">
            <input type="hidden" name="from" value="angsuran">
            
            <div class="form-row col-2">
              <div class="form-group">
                <label class="form-label">Jumlah Bayar <span class="req">*</span></label>
                <input type="text" name="jumlah" id="payJumlah" class="form-control" data-rupiah required placeholder="Rp">
              </div>
              <div class="form-group">
                <label class="form-label">Tanggal Bayar</label>
                <input type="date" name="tanggal_bayar" class="form-control" value="<?=date('Y-m-d')?>">
              </div>
            </div>
            
            <div class="alert alert-info" style="margin-top:12px;font-size:13px;">
              <i class="fas fa-info-circle"></i> 
              <span id="payInfo">Pilih angsuran terlebih dahulu</span>
            </div>
            
            <div style="text-align:right;margin-top:16px;">
              <button type="button" class="btn btn-outline" onclick="closeModal('modalBayar')">Batal</button>
              <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Simpan Pembayaran</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Hidden data for JS - use all jadwal for modal -->
<div id="jadwalData" style="display:none;">
  <?= json_encode($allJadwal) ?>
</div>

<script>
const jadwalList = JSON.parse(document.getElementById('jadwalData').textContent || '[]');

function loadJadwalAngsuran() {
  const pinjamId = document.getElementById('selectPinjaman').value;
  const container = document.getElementById('jadwalOptions');
  const wrapper = document.getElementById('jadwalList');
  const payForm = document.getElementById('paymentForm');
  
  if (!pinjamId) {
    wrapper.style.display = 'none';
    payForm.style.display = 'none';
    return;
  }
  
  wrapper.style.display = 'block';
  // Reset payment form when loading new pinjaman
  payForm.style.display = 'none';
  
  // Filter jadwal for selected pinjaman
  const jadwal = jadwalList.filter(j => j.pinjaman_id == pinjamId);
  
  if (jadwal.length === 0) {
    container.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted);">Tidak ada jadwal angsuran</div>';
    return;
  }
  
  let html = '';
  jadwal.forEach(j => {
    const isLunas = j.status === 'bayar';
    const isTelat = j.hari_telat > 0 && !isLunas;
    const badgeClass = isLunas ? 'success' : (isTelat ? 'danger' : 'warning');
    const badgeText = isLunas ? 'Lunas' : (isTelat ? 'Terlambat' : 'Belum Bayar');
    const btnDisabled = isLunas ? 'disabled' : '';
    const btnClass = isLunas ? 'btn-outline' : 'btn-success';
    
    html += `
      <div style="display:flex;align-items:center;justify-content:space-between;padding:10px;border-bottom:1px solid var(--border);${isTelat?'background:#fff5f5;':''}">
        <div>
          <div style="font-weight:600;">Angsuran ke-${j.angsuran_ke}</div>
          <div style="font-size:11px;color:var(--text-muted);">Jatuh Tempo: ${new Date(j.jatuh_tempo).toLocaleDateString('id-ID')}</div>
          <div style="font-size:12px;">Tagihan: <strong>${parseInt(j.total_bayar).toLocaleString('id-ID')}</strong> | Terbayar: <strong>${parseInt(j.terbayar).toLocaleString('id-ID')}</strong></div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
          <span class="badge badge-${badgeClass}">${badgeText}</span>
          ${!isLunas ? `<button type="button" class="btn btn-xs ${btnClass}" onclick="selectJadwal(${j.id}, ${j.pinjaman_id}, ${j.angsuran_ke}, ${j.total_bayar}, ${j.terbayar}, '${j.jatuh_tempo}')">Pilih</button>` : ''}
        </div>
      </div>
    `;
  });
  
  container.innerHTML = html;
}

function selectJadwal(jadwalId, pinjamId, angsuranKe, tagihan, terbayar, jatuhTempo) {
  document.getElementById('payJadwalId').value = jadwalId;
  document.getElementById('payPinjamanId').value = pinjamId;
  document.getElementById('payAngsuranKe').textContent = 'Ke-' + angsuranKe;
  document.getElementById('payJatuhTempo').textContent = new Date(jatuhTempo).toLocaleDateString('id-ID');
  document.getElementById('payTagihan').textContent = 'Rp ' + parseInt(tagihan).toLocaleString('id-ID');
  document.getElementById('payTerbayar').textContent = 'Rp ' + parseInt(terbayar).toLocaleString('id-ID');
  
  const sisa = tagihan - terbayar;
  document.getElementById('payJumlah').value = parseInt(sisa).toLocaleString('id-ID');
  
  const isTelat = new Date(jatuhTempo) < new Date();
  document.getElementById('payInfo').innerHTML = isTelat 
    ? '<strong style="color:var(--danger);">Angsuran ini terlambat!</strong> Denda akan dikenakan otomatis.'
    : 'Angsuran berjalan normal. Masukkan jumlah yang akan dibayarkan.';
  
  document.getElementById('paymentForm').style.display = 'block';
}

// Quick pay from table
function bayarAngsuran(jadwalId, pinjamId, angsuranKe, tagihan, terbayar, noPinjaman) {
  // First find the jadwal data from the loaded list to get correct jatuh tempo
  const jadwal = jadwalList.find(j => j.id === jadwalId);
  const jatuhTempo = jadwal ? jadwal.jatuh_tempo : '';
  
  // Set the select value
  document.getElementById('selectPinjaman').value = pinjamId;
  
  // Load jadwal list and then select
  loadJadwalAngsuran();
  
  // Wait for render then select with correct data
  setTimeout(() => {
    selectJadwal(jadwalId, pinjamId, angsuranKe, tagihan, terbayar, jatuhTempo);
  }, 150);
  
  openModal('modalBayar');
}

function lihatKwitansi(jadwalId) {
  alert('Fitur lihat kwitansi akan dikembangkan');
}
</script>

<?php renderFooter(); ?>

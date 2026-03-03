<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/layout.php';
requireLogin();

$db     = Database::getInstance();
$page   = max(1,(int)($_GET['page']??1));
$perPage= 20;
$search = trim($_GET['search']??'');
$awal   = $_GET['awal'] ?? date('Y-m-01');
$akhir  = $_GET['akhir'] ?? date('Y-m-t');

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(j.no_jurnal LIKE ? OR j.keterangan LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

if ($awal) {
    $where[] = "j.tanggal >= ?";
    $params[] = $awal;
}

if ($akhir) {
    $where[] = "j.tanggal <= ?";
    $params[] = $akhir;
}

$whereStr = implode(' AND ', $where);

// Get totals
$totals = $db->fetch(
    "SELECT 
        COALESCE(SUM(jd.debit),0) AS total_debit,
        COALESCE(SUM(jd.kredit),0) AS total_kredit
     FROM jurnal j
     JOIN jurnal_detail jd ON jd.jurnal_id=j.id
     WHERE $whereStr",
    $params
);

$total = (int)$db->fetch("SELECT COUNT(DISTINCT j.id) c FROM jurnal j WHERE $whereStr", $params)['c'];
$pg    = paginate($total, $perPage, $page, '?');

// Get jurnal data
$jurnal = $db->fetchAll(
    "SELECT j.id, j.no_jurnal, j.keterangan, j.tanggal, j.ref_tabel, j.ref_id,
            u.nama AS dibuat_oleh,
            SUM(jd.debit) AS total_debit,
            SUM(jd.kredit) AS total_kredit
     FROM jurnal j
     LEFT JOIN users u ON u.id=j.dibuat_oleh
     JOIN jurnal_detail jd ON jd.jurnal_id=j.id
     WHERE $whereStr
     GROUP BY j.id
     ORDER BY j.tanggal DESC, j.id DESC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);

// Get detail for each jurnal
$jurnalIds = array_column($jurnal, 'id');
$details = [];
if ($jurnalIds) {
    $detailList = $db->fetchAll(
        "SELECT * FROM jurnal_detail WHERE jurnal_id IN (" . implode(',', $jurnalIds) . ") ORDER BY jurnal_id, id"
    );
    foreach ($detailList as $d) {
        $details[$d['jurnal_id']][] = $d;
    }
}

renderHeader('Buku Jurnal','jurnal');
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
  <h2 style="font-size:18px;font-weight:800;color:var(--primary);">Buku Jurnal Umum</h2>
  <?php if(hasRole(['admin','manager'])): ?>
  <button class="btn btn-primary" onclick="window.print()">
    <i class="fas fa-print"></i> Cetak
  </button>
  <?php endif; ?>
</div>

<!-- Filter -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-body" style="padding:14px;">
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
      <div style="min-width:150px;">
        <label class="form-label" style="margin-bottom:4px;font-size:12px;">Tanggal Awal</label>
        <input type="date" name="awal" class="form-control" value="<?=h($awal)?>">
      </div>
      <div style="min-width:150px;">
        <label class="form-label" style="margin-bottom:4px;font-size:12px;">Tanggal Akhir</label>
        <input type="date" name="akhir" class="form-control" value="<?=h($akhir)?>">
      </div>
      <div style="flex:1;min-width:200px;">
        <label class="form-label" style="margin-bottom:4px;font-size:12px;">Cari</label>
        <input type="text" name="search" class="form-control" placeholder="No. Jurnal / Keterangan..." value="<?=h($search)?>">
      </div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
      <a href="?" class="btn btn-outline"><i class="fas fa-redo"></i></a>
    </form>
  </div>
</div>

<!-- Summary -->
<div class="stats-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:20px;max-width:400px;">
  <div class="stat-card" style="border-left:4px solid var(--info);">
    <div class="stat-icon" style="background:#e3f2fd;color:var(--info);"><i class="fas fa-arrow-down"></i></div>
    <div class="stat-info">
      <small>Total Debit</small>
      <strong><?=rupiah($totals['total_debit'])?></strong>
    </div>
  </div>
  <div class="stat-card" style="border-left:4px solid var(--success);">
    <div class="stat-icon" style="background:#e8f5e9;color:var(--success);"><i class="fas fa-arrow-up"></i></div>
    <div class="stat-info">
      <small>Total Kredit</small>
      <strong><?=rupiah($totals['total_kredit'])?></strong>
    </div>
  </div>
</div>

<!-- Jurnal Table -->
<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th style="width:100px;">Tanggal</th>
          <th style="width:120px;">No. Jurnal</th>
          <th>Keterangan</th>
          <th class="text-right" style="width:140px;">Debit</th>
          <th class="text-right" style="width:140px;">Kredit</th>
          <th style="width:80px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($jurnal)): ?>
          <tr>
            <td colspan="6" class="text-center" style="padding:40px;color:var(--text-muted);">
              Tidak ada data jurnal
            </td>
          </tr>
        <?php else: foreach($jurnal as $j): ?>
          <tr>
            <td><?=tglIndo($j['tanggal'])?></td>
            <td><span class="font-mono" style="font-size:11px;background:var(--bg);padding:3px 8px;border-radius:6px;font-weight:600;"><?=h($j['no_jurnal'])?></span></td>
            <td>
              <div style="font-weight:600;"><?=h($j['keterangan'])?></div>
              <div style="font-size:11px;color:var(--text-muted);"><?=h($j['dibuat_oleh'] ?: '-')?></div>
            </td>
            <td class="text-right"><strong><?=rupiah($j['total_debit'])?></strong></td>
            <td class="text-right"><strong><?=rupiah($j['total_kredit'])?></strong></td>
            <td>
              <button class="btn btn-xs btn-outline" onclick="toggleDetail(<?=$j['id']?>)">
                <i class="fas fa-eye"></i>
              </button>
            </td>
          </tr>
          <!-- Detail Row -->
          <tr id="detail-<?=$j['id']?>" style="display:none;background:#f8fafc;">
            <td colspan="6" style="padding:12px 16px;">
              <div style="font-size:12px;font-weight:600;margin-bottom:8px;">Rincian:</div>
              <table class="tbl" style="font-size:12px;">
                <thead>
                  <tr>
                    <th style="width:120px;">Kode Akun</th>
                    <th>Nama Akun</th>
                    <th class="text-right">Debit</th>
                    <th class="text-right">Kredit</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($details[$j['id']] ?? [] as $d): ?>
                  <tr>
                    <td><span class="font-mono"><?=h($d['kode_akun'])?></span></td>
                    <td><?=h($d['nama_akun'])?></td>
                    <td class="text-right"><?=$d['debit'] > 0 ? rupiah($d['debit']) : '-' ?></td>
                    <td class="text-right"><?=$d['kredit'] > 0 ? rupiah($d['kredit']) : '-' ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
      <tfoot style="background:var(--bg);font-weight:700;">
        <tr>
          <td colspan="3" class="text-right">TOTAL</td>
          <td class="text-right"><?=rupiah($totals['total_debit'])?></td>
          <td class="text-right"><?=rupiah($totals['total_kredit'])?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
  
  <!-- Pagination -->
  <?php if($pg['total_pages'] > 1): ?>
  <div style="padding:16px;border-top:1px solid var(--border);display:flex;justify-content:center;">
    <div class="pagination">
      <?php if($pg['prev']): ?>
        <a href="?page=<?=$pg['prev']?>&awal=<?=h($awal)?>&akhir=<?=h($akhir)?>&search=<?=urlencode($search)?>" class="page-link"><i class="fas fa-chevron-left"></i></a>
      <?php endif; ?>
      
      <?php for($i=1;$i<=$pg['total_pages'];$i++): ?>
        <?php if($i==1 || $i==$pg['total_pages'] || ($i>=$pg['current']-2 && $i<=$pg['current']+2)): ?>
          <a href="?page=<?=$i?>&awal=<?=h($awal)?>&akhir=<?=h($akhir)?>&search=<?=urlencode($search)?>" class="page-link <?=$i===$pg['current']?'active':''?>"><?=$i?></a>
        <?php elseif($i===$pg['current']-3 || $i===$pg['current']+3): ?>
          <span style="padding:0 8px;color:var(--text-muted);">...</span>
        <?php endif; ?>
      <?php endfor; ?>
      
      <?php if($pg['next']): ?>
        <a href="?page=<?=$pg['next']?>&awal=<?=h($awal)?>&akhir=<?=h($akhir)?>&search=<?=urlencode($search)?>" class="page-link"><i class="fas fa-chevron-right"></i></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
function toggleDetail(id) {
  const row = document.getElementById('detail-' + id);
  row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}
</script>

<?php renderFooter(); ?>

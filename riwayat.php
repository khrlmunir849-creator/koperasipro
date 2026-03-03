<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/layout.php';
requireLogin();

$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    setFlash('danger', 'ID Simpanan tidak valid');
    header('Location: list.php');
    exit;
}

// Get simpanan detail
$simpanan = $db->fetch(
    "SELECT s.*, a.nama_lengkap, a.no_anggota, a.no_telepon
     FROM simpanan s 
     JOIN anggota a ON a.id = s.anggota_id
     WHERE s.id = ?",
    [$id]
);

if (!$simpanan) {
    setFlash('danger', 'Data simpanan tidak ditemukan');
    header('Location: list.php');
    exit;
}

// Get filter parameters
$jenisFilter = $_GET['jenis'] ?? '';
$tglMulai = $_GET['tgl_mulai'] ?? '';
$tglSelesai = $_GET['tgl_selesai'] ?? '';

// Build WHERE clause
$where = ["ts.simpanan_id = ?"];
$params = [$id];

if ($jenisFilter) {
    $where[] = "ts.jenis = ?";
    $params[] = $jenisFilter;
}

if ($tglMulai) {
    $where[] = "ts.tanggal >= ?";
    $params[] = $tglMulai;
}

if ($tglSelesai) {
    $where[] = "ts.tanggal <= ?";
    $params[] = $tglSelesai;
}

$whereStr = implode(' AND ', $where);

// Get transactions
$transaksi = $db->fetchAll(
    "SELECT ts.*, u.nama as dibuat_oleh_nama
     FROM transaksi_simpanan ts
     LEFT JOIN users u ON u.id = ts.dibuat_oleh
     WHERE $whereStr
     ORDER BY ts.tanggal DESC, ts.created_at DESC",
    $params
);

// Get summary statistics
$summary = $db->fetch(
    "SELECT 
        SUM(CASE WHEN ts.jenis = 'setor' THEN ts.jumlah ELSE 0 END) as total_setor,
        SUM(CASE WHEN ts.jenis = 'tarik' THEN ts.jumlah ELSE 0 END) as total_tarik,
        COUNT(*) as total_transaksi
     FROM transaksi_simpanan ts
     WHERE ts.simpanan_id = ?",
    [$id]
);

renderHeader('Riwayat Transaksi Simpanan', 'simpanan');
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
  <div>
    <a href="list.php" class="btn btn-sm btn-outline" style="margin-bottom:8px;">
      <i class="fas fa-arrow-left"></i> Kembali
    </a>
    <h2 style="font-size:18px;font-weight:800;color:var(--primary);margin:0;">
      Riwayat Transaksi Simpanan
    </h2>
  </div>
</div>

<!-- Info Simpanan -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
      <div>
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">No. Anggota</div>
        <div style="font-weight:600;"><span class="font-mono" style="background:var(--bg);padding:3px 8px;border-radius:6px;"><?=h($simpanan['no_anggota'])?></span></div>
      </div>
      <div>
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Nama Anggota</div>
        <div style="font-weight:600;"><?=h($simpanan['nama_lengkap'])?></div>
      </div>
      <div>
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Jenis Simpanan</div>
        <div style="font-weight:600;"><span class="badge badge-<?=['pokok'=>'info','wajib'=>'success','wajib_pinjam'=>'warning','wajib_khusus'=>'purple','sukarela'=>'primary'][$simpanan['jenis_simpanan']] ?? 'secondary'?>"><?=ucfirst($simpanan['jenis_simpanan'])?></span></div>
      </div>
      <div>
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Saldo Saat Ini</div>
        <div style="font-weight:700;font-size:18px;color:var(--success);"><?=rupiah($simpanan['saldo'])?></div>
      </div>
    </div>
  </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
  <div class="stat-card">
    <div class="stat-icon" style="background:#e8f4fd;color:#2980b9;"><i class="fas fa-list"></i></div>
    <div class="stat-info"><small>Total Transaksi</small><strong><?=$summary['total_transaksi'] ?? 0?></strong></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#e8f8f0;color:#27ae60;"><i class="fas fa-arrow-up"></i></div>
    <div class="stat-info"><small>Total Setoran</small><strong style="color:var(--success);"><?=rupiah($summary['total_setor'] ?? 0)?></strong></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fff5e6;color:#e8a020;"><i class="fas fa-arrow-down"></i></div>
    <div class="stat-info"><small>Total Penarikan</small><strong style="color:var(--danger);"><?=rupiah($summary['total_tarik'] ?? 0)?></strong></div>
  </div>
</div>

<!-- Filter -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-body" style="padding:14px;">
    <form method="get" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
      <input type="hidden" name="id" value="<?=$id?>">
      <div>
        <label class="form-label" style="font-size:12px;">Jenis</label>
        <select name="jenis" class="form-control form-select" style="min-width:140px;">
          <option value="">Semua Jenis</option>
          <option value="setor" <?=$jenisFilter==='setor'?'selected':''?>>Setoran</option>
          <option value="tarik" <?=$jenisFilter==='tarik'?'selected':''?>>Penarikan</option>
        </select>
      </div>
      <div>
        <label class="form-label" style="font-size:12px;">Tanggal Mulai</label>
        <input type="date" name="tgl_mulai" class="form-control" value="<?=h($tglMulai)?>">
      </div>
      <div>
        <label class="form-label" style="font-size:12px;">Tanggal Selesai</label>
        <input type="date" name="tgl_selesai" class="form-control" value="<?=h($tglSelesai)?>">
      </div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
      <a href="riwayat.php?id=<?=$id?>" class="btn btn-outline"><i class="fas fa-redo"></i></a>
    </form>
  </div>
</div>

<!-- Transaction Table -->
<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>No. Referensi</th>
          <th>Jenis</th>
          <th class="text-right">Jumlah</th>
          <th class="text-right">Saldo Sebelum</th>
          <th class="text-right">Saldo Sesudah</th>
          <th>Keterangan</th>
          <th>Petugas</th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($transaksi)): ?>
          <tr>
            <td colspan="8" class="text-center" style="padding:40px;color:var(--text-muted);">
              <i class="fas fa-history" style="font-size:36px;display:block;margin-bottom:8px;opacity:.3;"></i>
              Tidak ada transaksi
            </td>
          </tr>
        <?php else: foreach($transaksi as $t): ?>
          <tr>
            <td style="white-space:nowrap;"><?=tglIndo($t['tanggal'])?></td>
            <td><span class="font-mono" style="font-size:11px;background:var(--bg);padding:3px 6px;border-radius:4px;"><?=h($t['no_referensi'])?></span></td>
            <td>
              <span class="badge badge-<?=$t['jenis'] === 'setor' ? 'success' : 'warning'?>">
                <?=$t['jenis'] === 'setor' ? 'Setoran' : 'Penarikan'?>
              </span>
            </td>
            <td class="text-right" style="font-weight:600;color:<?=$t['jenis'] === 'setor' ? 'var(--success)' : 'var(--danger)'?>;">
              <?=$t['jenis'] === 'setor' ? '+' : '-'?><?=rupiah($t['jumlah'])?>
            </td>
            <td class="text-right"><?=rupiah($t['saldo_sebelum'])?></td>
            <td class="text-right"><?=rupiah($t['saldo_sesudah'])?></td>
            <td><?=h($t['keterangan'] ?? '-')?></td>
            <td><?=h($t['dibuat_oleh_nama'] ?? '-')?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php renderFooter(); ?>

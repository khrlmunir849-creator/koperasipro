<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/layout.php';
requireLogin();

$db      = Database::getInstance();
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

// Neraca dari jurnal
$akun = $db->fetchAll(
    "SELECT jd.kode_akun, jd.nama_akun,
       SUM(jd.debit) AS total_debit, SUM(jd.kredit) AS total_kredit
     FROM jurnal_detail jd
     JOIN jurnal j ON j.id=jd.jurnal_id
     WHERE j.tanggal<=? AND j.is_posted=1
     GROUP BY jd.kode_akun, jd.nama_akun
     ORDER BY jd.kode_akun",
    [$tanggal]
);

// Kelompokkan per golongan
$aktiva = $pasiva = [];
foreach ($akun as $a) {
    $saldo = $a['total_debit'] - $a['total_kredit'];
    if (str_starts_with($a['kode_akun'], '1')) {
        if ($saldo != 0) $aktiva[] = [...$a, 'saldo' => $saldo];
    } else {
        if ($saldo != 0) $pasiva[] = [...$a, 'saldo' => abs($saldo)];
    }
}

// Tambah data simpanan dari tabel (lebih akurat)
$totalSimpananPokok        = (float)$db->fetch("SELECT COALESCE(SUM(saldo),0) c FROM simpanan WHERE jenis_simpanan='pokok' AND is_active=1")['c'];
$totalSimpananWajib        = (float)$db->fetch("SELECT COALESCE(SUM(saldo),0) c FROM simpanan WHERE jenis_simpanan='wajib' AND is_active=1")['c'];
$totalSimpananWajibPinjam = (float)$db->fetch("SELECT COALESCE(SUM(saldo),0) c FROM simpanan WHERE jenis_simpanan='wajib_pinjam' AND is_active=1")['c'];
$totalSimpananWajibKhusus = (float)$db->fetch("SELECT COALESCE(SUM(saldo),0) c FROM simpanan WHERE jenis_simpanan='wajib_khusus' AND is_active=1")['c'];
$totalSimpananSukarela    = (float)$db->fetch("SELECT COALESCE(SUM(saldo),0) c FROM simpanan WHERE jenis_simpanan='sukarela' AND is_active=1")['c'];
$totalPiutang             = (float)$db->fetch("SELECT COALESCE(SUM(jumlah_pokok - total_terbayar),0) c FROM pinjaman WHERE status='aktif'")['c'];
$totalKas                 = $totalSimpananPokok + $totalSimpananWajib + $totalSimpananWajibPinjam + $totalSimpananWajibKhusus + $totalSimpananSukarela - $totalPiutang;

$totalAktiva  = $totalKas + $totalPiutang;
$totalPasiva  = $totalSimpananPokok + $totalSimpananWajib + $totalSimpananWajibPinjam + $totalSimpananWajibKhusus + $totalSimpananSukarela;
$modal        = $totalAktiva - $totalPasiva;

renderHeader('Neraca Keuangan','neraca');
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
  <div>
    <h2 style="font-size:20px;font-weight:800;color:var(--primary);">Neraca Keuangan</h2>
    <p style="font-size:13px;color:var(--text-muted);">Per tanggal <?=tglIndo($tanggal)?></p>
  </div>
  <form method="get" style="display:flex;gap:8px;align-items:center;">
    <label class="form-label" style="margin:0;">Per Tanggal:</label>
    <input type="date" name="tanggal" class="form-control" value="<?=$tanggal?>" style="width:160px;">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Tampilkan</button>
    <button type="button" class="btn btn-outline btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
  </form>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
  <!-- AKTIVA -->
  <div class="card">
    <div class="card-header" style="background:linear-gradient(135deg,#1a3a5c,#234b75);border:none;">
      <div class="card-title" style="color:#fff;font-size:16px;"><i class="fas fa-plus-circle"></i> AKTIVA (Harta)</div>
    </div>
    <div class="card-body" style="padding:0;">
      <div style="padding:16px 20px;background:#f8faff;border-bottom:1px solid var(--border);">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);margin-bottom:10px;">Aktiva Lancar</div>
        <?php
        $items = [
          ['Kas & Setara Kas', $totalKas > 0 ? $totalKas : 0],
          ['Piutang Pinjaman', $totalPiutang],
        ];
        foreach ($items as [$nama,$val]): ?>
        <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #eee;font-size:13px;">
          <span style="color:var(--text-muted);">  <?=$nama?></span>
          <span style="font-weight:600;"><?=rupiah($val)?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="padding:16px 20px;">
        <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:800;color:var(--primary);border-top:2px solid var(--primary);padding-top:12px;">
          <span>TOTAL AKTIVA</span>
          <span><?=rupiah($totalAktiva)?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- PASIVA -->
  <div class="card">
    <div class="card-header" style="background:linear-gradient(135deg,#1a9e5c,#1a7a47);border:none;">
      <div class="card-title" style="color:#fff;font-size:16px;"><i class="fas fa-minus-circle"></i> PASIVA (Kewajiban + Modal)</div>
    </div>
    <div class="card-body" style="padding:0;">
      <div style="padding:16px 20px;background:#f8fff8;border-bottom:1px solid var(--border);">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);margin-bottom:10px;">Kewajiban - Simpanan Anggota</div>
        <?php foreach([
          ['Simpanan Pokok',$totalSimpananPokok],
          ['Simpanan Wajib',$totalSimpananWajib],
          ['Simpanan Wajib Pinjam',$totalSimpananWajibPinjam],
          ['Simpanan Wajib Khusus',$totalSimpananWajibKhusus],
          ['Simpanan Sukarela',$totalSimpananSukarela],
        ] as [$nama,$val]): ?>
        <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #eee;font-size:13px;">
          <span style="color:var(--text-muted);">  <?=$nama?></span>
          <span style="font-weight:600;"><?=rupiah($val)?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if($modal != 0): ?>
      <div style="padding:12px 20px;background:#fff9e6;border-bottom:1px solid var(--border);">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);margin-bottom:8px;">Modal</div>
        <div style="display:flex;justify-content:space-between;font-size:13px;">
          <span style="color:var(--text-muted);">  Modal Koperasi</span>
          <span style="font-weight:600;"><?=rupiah(abs($modal))?></span>
        </div>
      </div>
      <?php endif; ?>
      <div style="padding:16px 20px;">
        <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:800;color:var(--success);border-top:2px solid var(--success);padding-top:12px;">
          <span>TOTAL PASIVA</span>
          <span><?=rupiah($totalAktiva)?></span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Verifikasi -->
<div class="card" style="margin-top:16px;">
  <div class="card-body" style="padding:16px 24px;">
    <div style="display:flex;align-items:center;gap:12px;font-size:14px;">
      <?php if(abs($totalAktiva - $totalPasiva) < 0.01): ?>
        <i class="fas fa-check-circle" style="color:var(--success);font-size:20px;"></i>
        <span style="font-weight:600;color:var(--success);">Neraca Seimbang — Aktiva = Pasiva = <?=rupiah($totalAktiva)?></span>
      <?php else: ?>
        <i class="fas fa-exclamation-circle" style="color:var(--danger);font-size:20px;"></i>
        <span style="font-weight:600;color:var(--danger);">Selisih: <?=rupiah(abs($totalAktiva-$totalPasiva))?> — Periksa jurnal</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php renderFooter(); ?>

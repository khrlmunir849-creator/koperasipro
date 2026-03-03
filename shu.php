<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/layout.php';
requireLogin();

$db   = Database::getInstance();
$tahun= (int)($_GET['tahun'] ?? date('Y'));

// ─── Hitung SHU ────────────────────────────────────────────────
// Pendapatan bunga
$pendapatanBunga = (float)$db->fetch(
    "SELECT COALESCE(SUM(jd.kredit),0) AS total FROM jurnal_detail jd
     JOIN jurnal j ON j.id=jd.jurnal_id
     WHERE jd.kode_akun='4-1000' AND YEAR(j.tanggal)=? AND j.is_posted=1", [$tahun]
)['total'];

// Pendapatan denda
$pendapatanDenda = (float)$db->fetch(
    "SELECT COALESCE(SUM(jd.kredit),0) AS total FROM jurnal_detail jd
     JOIN jurnal j ON j.id=jd.jurnal_id
     WHERE jd.kode_akun='4-2000' AND YEAR(j.tanggal)=? AND j.is_posted=1", [$tahun]
)['total'];

// Beban operasional (bisa diisi manual)
$totalPendapatan = $pendapatanBunga + $pendapatanDenda;
$biayaOperasional = $totalPendapatan * 0.20; // estimasi 20%
$shu = $totalPendapatan - $biayaOperasional;

// ─── Distribusi SHU per Anggota ──────────────────────────────
$distribusiSHU = $db->fetchAll(
    "SELECT
       a.no_anggota, a.nama_lengkap,
       COALESCE(SUM(CASE WHEN ts.tanggal BETWEEN :dari AND :sampai THEN ts.jumlah END),0) AS total_simpanan,
       COALESCE(SUM(CASE WHEN pa.tanggal_bayar BETWEEN :dari2 AND :sampai2 THEN pa.jumlah END),0) AS total_angsuran
     FROM anggota a
     LEFT JOIN simpanan s ON s.anggota_id=a.id
     LEFT JOIN transaksi_simpanan ts ON ts.simpanan_id=s.id AND ts.jenis='setor' AND YEAR(ts.tanggal)=:thn
     LEFT JOIN pinjaman p ON p.anggota_id=a.id
     LEFT JOIN pembayaran_angsuran pa ON pa.pinjaman_id=p.id AND YEAR(pa.tanggal_bayar)=:thn2
     WHERE a.status='aktif'
     GROUP BY a.id ORDER BY total_simpanan DESC",
    [':dari'=>"$tahun-01-01",':sampai'=>"$tahun-12-31",':dari2'=>"$tahun-01-01",':sampai2'=>"$tahun-12-31",':thn'=>$tahun,':thn2'=>$tahun]
);

// Hitung porsi SHU
$grandSimpanan = array_sum(array_column($distribusiSHU,'total_simpanan'));
$grandAngsuran = array_sum(array_column($distribusiSHU,'total_angsuran'));
$shuSimpanan   = $shu * 0.40; // 40% dari simpanan
$shuAngsuran   = $shu * 0.60; // 60% dari jasa pinjaman

foreach ($distribusiSHU as &$row) {
    $pSimpanan = $grandSimpanan > 0 ? ($row['total_simpanan']/$grandSimpanan)*$shuSimpanan : 0;
    $pAngsuran = $grandAngsuran > 0 ? ($row['total_angsuran']/$grandAngsuran)*$shuAngsuran : 0;
    $row['bagian_shu'] = round($pSimpanan + $pAngsuran, 0);
}
unset($row);

renderHeader('Laporan SHU','shu');
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
  <div>
    <h2 style="font-size:20px;font-weight:800;color:var(--primary);">Laporan Sisa Hasil Usaha (SHU)</h2>
    <p style="font-size:13px;color:var(--text-muted);">Perhitungan SHU dan distribusi per anggota</p>
  </div>
  <form method="get" style="display:flex;gap:8px;align-items:center;">
    <label class="form-label" style="margin:0;white-space:nowrap;">Tahun:</label>
    <select name="tahun" class="form-control form-select" style="width:100px;" onchange="this.form.submit()">
      <?php for($y=date('Y');$y>=date('Y')-5;$y--): ?>
        <option value="<?=$y?>" <?=$tahun==$y?'selected':''?>><?=$y?></option>
      <?php endfor; ?>
    </select>
  </form>
</div>

<!-- ─── Ringkasan SHU ──────────────────────────────────────── -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:20px;">
  <?php foreach([
    ['Pendapatan Bunga','bunga',$pendapatanBunga,'var(--success)','#e8f8f0','fas fa-percentage'],
    ['Pendapatan Denda','denda',$pendapatanDenda,'var(--warning)','#fff5e6','fas fa-gavel'],
    ['Total Pendapatan','total',$totalPendapatan,'var(--primary)','#e8f4fd','fas fa-coins'],
    ['Biaya Operasional','biaya',$biayaOperasional,'var(--danger)','#fdecea','fas fa-minus-circle'],
    ['SHU Bersih','shu',$shu,'var(--accent)','#fff9e6','fas fa-trophy'],
  ] as [$label,$key,$val,$color,$bg,$icon]): ?>
  <div class="stat-card">
    <div class="stat-icon" style="background:<?=$bg?>;color:<?=$color?>;"><i class="<?=$icon?>"></i></div>
    <div class="stat-info"><small><?=$label?></small><strong style="font-size:14px;"><?=rupiah($val)?></strong></div>
  </div>
  <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;margin-bottom:20px;">
  <!-- Distribusi SHU -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-chart-pie" style="color:var(--accent)"></i> Distribusi SHU Per Anggota Tahun <?=$tahun?></div>
    </div>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr>
          <th>#</th><th>No. Anggota</th><th>Nama</th>
          <th class="text-right">Simpanan</th>
          <th class="text-right">Angsuran</th>
          <th class="text-right">Bagian SHU</th>
        </tr></thead>
        <tbody>
          <?php if(empty($distribusiSHU)): ?>
            <tr><td colspan="6" class="text-center" style="padding:32px;color:var(--text-muted);">Belum ada data</td></tr>
          <?php else: foreach($distribusiSHU as $i=>$row): ?>
            <tr>
              <td style="color:var(--text-muted);font-size:12px;"><?=$i+1?></td>
              <td><span class="font-mono" style="font-size:11px;background:var(--bg);padding:2px 7px;border-radius:5px;"><?=h($row['no_anggota'])?></span></td>
              <td style="font-weight:600;font-size:13px;"><?=h($row['nama_lengkap'])?></td>
              <td class="text-right" style="font-size:13px;"><?=rupiah($row['total_simpanan'])?></td>
              <td class="text-right" style="font-size:13px;"><?=rupiah($row['total_angsuran'])?></td>
              <td class="text-right"><strong style="color:var(--success);font-size:14px;"><?=rupiah($row['bagian_shu'])?></strong></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
          <tr style="background:var(--bg);">
            <td colspan="3" style="font-weight:700;padding:12px 16px;">TOTAL</td>
            <td class="text-right" style="font-weight:700;"><?=rupiah($grandSimpanan)?></td>
            <td class="text-right" style="font-weight:700;"><?=rupiah($grandAngsuran)?></td>
            <td class="text-right" style="font-weight:700;color:var(--success);"><?=rupiah($shu)?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- Formula SHU -->
  <div style="display:flex;flex-direction:column;gap:16px;">
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fas fa-calculator" style="color:var(--info)"></i> Formula Distribusi</div></div>
      <div class="card-body">
        <div style="background:var(--bg);border-radius:8px;padding:14px;margin-bottom:12px;">
          <div style="font-size:12px;font-weight:700;color:var(--primary);margin-bottom:6px;">40% SHU — Jasa Simpanan</div>
          <div style="font-family:var(--mono);font-size:12px;color:var(--text-muted);">= (Simpanan Anggota / Total Simpanan) × <?=rupiah($shuSimpanan)?></div>
        </div>
        <div style="background:var(--bg);border-radius:8px;padding:14px;">
          <div style="font-size:12px;font-weight:700;color:var(--primary);margin-bottom:6px;">60% SHU — Jasa Pinjaman</div>
          <div style="font-family:var(--mono);font-size:12px;color:var(--text-muted);">= (Angsuran Anggota / Total Angsuran) × <?=rupiah($shuAngsuran)?></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fas fa-file-invoice" style="color:var(--primary)"></i> Laporan SHU</div></div>
      <div class="card-body">
        <?php
        $items = [
          ['Pendapatan Usaha',''],
          ['  Bunga Pinjaman', rupiah($pendapatanBunga), false],
          ['  Denda Keterlambatan', rupiah($pendapatanDenda), false],
          ['Total Pendapatan', rupiah($totalPendapatan), true],
          ['---',''],
          ['Beban Usaha',''],
          ['  Biaya Operasional', rupiah($biayaOperasional), false],
          ['Total Beban', rupiah($biayaOperasional), true],
          ['---',''],
          ['SHU Bersih', rupiah($shu), true, 'var(--success)'],
        ];
        foreach($items as $item):
          if($item[0]==='---'){ echo '<hr class="divider">'; continue; }
          $isBold = $item[2] ?? false;
          $color  = $item[3] ?? '';
        ?>
          <div style="display:flex;justify-content:space-between;padding:5px 0;font-size:13px;<?=$isBold?'font-weight:700;border-top:1px solid var(--border);margin-top:4px;padding-top:8px;':''?>">
            <span style="color:<?=$isBold?'var(--text)':'var(--text-muted)'?>"><?=h($item[0])?></span>
            <span style="color:<?=$color?:'var(--text)'?>"><?=$item[1]?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php renderFooter(); ?>

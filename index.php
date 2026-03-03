<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$db    = Database::getInstance();
$stats = getDashboardStats();

// Angsuran jatuh tempo bulan ini
$angsuranBulanIni = $db->fetchAll(
    "SELECT ja.*, a.nama_lengkap, a.no_anggota, p.no_pinjaman
     FROM jadwal_angsuran ja
     JOIN pinjaman p ON p.id = ja.pinjaman_id
     JOIN anggota a  ON a.id = p.anggota_id
     WHERE MONTH(ja.jatuh_tempo) = MONTH(CURDATE())
       AND YEAR(ja.jatuh_tempo)  = YEAR(CURDATE())
       AND ja.status IN ('belum','sebagian')
     ORDER BY ja.jatuh_tempo ASC LIMIT 10"
);

// Transaksi terakhir
$transaksiTerbaru = $db->fetchAll(
    "SELECT ts.*, a.nama_lengkap, s.jenis_simpanan
     FROM transaksi_simpanan ts
     JOIN anggota a ON a.id = ts.anggota_id
     JOIN simpanan s ON s.id = ts.simpanan_id
     ORDER BY ts.created_at DESC LIMIT 8"
);

// Pinjaman terbaru pending
$pinjamanPending = $db->fetchAll(
    "SELECT p.*, a.nama_lengkap, a.no_anggota
     FROM pinjaman p JOIN anggota a ON a.id = p.anggota_id
     WHERE p.status = 'pending' ORDER BY p.created_at DESC LIMIT 5"
);

// Chart data simpanan per bulan (6 bulan terakhir)
$chartSimpanan = $db->fetchAll(
    "SELECT DATE_FORMAT(tanggal,'%b %Y') AS bulan, 
            DATE_FORMAT(tanggal,'%Y-%m') AS bulan_urut,
            SUM(jumlah) AS total
     FROM transaksi_simpanan 
     WHERE LOWER(jenis)='setor' 
       AND tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(tanggal,'%Y-%m') 
     ORDER BY DATE_FORMAT(tanggal,'%Y-%m')"
);

// Get max value for better scaling
$maxTotal = !empty($chartSimpanan) ? max(array_column($chartSimpanan, 'total')) : 0;
$maxY = $maxTotal > 0 ? ceil($maxTotal / 1000000) * 1000000 : 10000000;

renderHeader('Dashboard', 'dashboard');
?>

<!-- ─── STATS ──────────────────────────────────────────────── -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon" style="background:#e8f4fd;color:#2980b9;"><i class="fas fa-users"></i></div>
    <div class="stat-info">
      <small>Total Anggota Aktif</small>
      <strong><?= number_format($stats['total_anggota']) ?></strong>
      <div class="stat-sub">Anggota koperasi</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#e8f8f0;color:#27ae60;"><i class="fas fa-piggy-bank"></i></div>
    <div class="stat-info">
      <small>Total Simpanan</small>
      <strong style="font-size:16px;"><?= rupiah($stats['total_simpanan']) ?></strong>
      <div class="stat-sub">Semua jenis simpanan</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fff5e6;color:#e67e22;"><i class="fas fa-hand-holding-usd"></i></div>
    <div class="stat-info">
      <small>Total Pinjaman Aktif</small>
      <strong style="font-size:16px;"><?= rupiah($stats['total_pinjaman']) ?></strong>
      <div class="stat-sub">Sisa pokok pinjaman</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fdecea;color:#c0392b;"><i class="fas fa-exclamation-triangle"></i></div>
    <div class="stat-info">
      <small>Angsuran Telat</small>
      <strong><?= number_format($stats['angsuran_telat']) ?></strong>
      <div class="stat-sub">Perlu segera diproses</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#f3e8ff;color:#8e44ad;"><i class="fas fa-calendar-day"></i></div>
    <div class="stat-info">
      <small>Tagihan Hari Ini</small>
      <strong style="font-size:16px;"><?= rupiah($stats['angsuran_hari_ini']) ?></strong>
      <div class="stat-sub">Angsuran jatuh tempo</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#feecde;color:#e74c3c;"><i class="fas fa-times-circle"></i></div>
    <div class="stat-info">
      <small>Pinjaman Macet</small>
      <strong><?= number_format($stats['pinjaman_macet']) ?></strong>
      <div class="stat-sub">Status macet</div>
    </div>
  </div>
</div>

<!-- ─── CHART + PENDING ─────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 380px;gap:20px;margin-bottom:20px;">
  <!-- Chart -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-chart-bar" style="color:var(--accent)"></i> Setoran Simpanan 6 Bulan Terakhir</div>
    </div>
    <div class="card-body" style="position:relative;min-height:280px;">
      <?php if(empty($chartSimpanan)): ?>
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:200px;color:var(--text-muted);">
          <i class="fas fa-chart-column" style="font-size:48px;opacity:0.3;margin-bottom:12px;"></i>
          <p style="font-size:14px;">Belum ada data setoran simpanan</p>
          <p style="font-size:12px;">Data akan muncul setelah ada transaksi</p>
        </div>
      <?php else: ?>
        <canvas id="chartSimpanan" height="200"></canvas>
      <?php endif; ?>
    </div>
  </div>

  <!-- Pending loans -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-clock" style="color:var(--warning)"></i> Pinjaman Pending</div>
      <a href="/koperasi/modules/pinjaman/list.php?status=pending" class="btn btn-sm btn-outline">Lihat Semua</a>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($pinjamanPending)): ?>
        <div style="text-align:center;padding:32px;color:var(--text-muted);">
          <i class="fas fa-check-circle" style="font-size:32px;color:var(--success);"></i>
          <p style="margin-top:8px;font-size:14px;">Tidak ada pengajuan pending</p>
        </div>
      <?php else: foreach($pinjamanPending as $p): ?>
        <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;">
          <div style="width:38px;height:38px;border-radius:10px;background:#fff5e6;color:#e67e22;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">
            <?= strtoupper(substr($p['nama_lengkap'],0,1)) ?>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= h($p['nama_lengkap']) ?></div>
            <div style="font-size:12px;color:var(--text-muted);"><?= rupiah($p['jumlah_pokok']) ?> — <?= $p['tenor_bulan'] ?> bln</div>
          </div>
          <?php if(hasRole(['admin','manager'])): ?>
          <a href="/koperasi/modules/pinjaman/detail.php?id=<?= $p['id'] ?>" class="btn btn-xs btn-accent">Review</a>
          <?php endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<!-- ─── ANGSURAN + TRANSAKSI ───────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
  <!-- Angsuran bulan ini -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-calendar-alt" style="color:var(--info)"></i> Angsuran Bulan Ini</div>
      <a href="/koperasi/modules/pinjaman/angsuran.php" class="btn btn-sm btn-outline">Semua</a>
    </div>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Anggota</th><th>Tgl JT</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
          <?php if(empty($angsuranBulanIni)): ?>
            <tr><td colspan="4" class="text-center text-muted" style="padding:24px;">Tidak ada angsuran</td></tr>
          <?php else: foreach($angsuranBulanIni as $a):
            $isLate = strtotime($a['jatuh_tempo']) < time(); ?>
            <tr>
              <td>
                <div style="font-weight:600;font-size:13px;"><?= h($a['nama_lengkap']) ?></div>
                <div style="font-size:11px;color:var(--text-muted);"><?= h($a['no_pinjaman']) ?></div>
              </td>
              <td style="font-family:var(--mono);font-size:12px;<?= $isLate ? 'color:var(--danger);font-weight:600;' : '' ?>">
                <?= date('d/m/Y', strtotime($a['jatuh_tempo'])) ?>
                <?php if($isLate): ?><br><span class="badge badge-danger">Telat</span><?php endif; ?>
              </td>
              <td style="font-weight:600;font-size:13px;"><?= rupiah($a['total_bayar']) ?></td>
              <td><span class="badge badge-<?= $a['status']==='belum'?'warning':'info' ?>"><?= ucfirst($a['status']) ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Transaksi terbaru -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-history" style="color:var(--success)"></i> Transaksi Terbaru</div>
    </div>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Anggota</th><th>Jenis</th><th>Jumlah</th></tr></thead>
        <tbody>
          <?php foreach($transaksiTerbaru as $t): ?>
            <tr>
              <td>
                <div style="font-weight:600;font-size:13px;"><?= h($t['nama_lengkap']) ?></div>
                <div style="font-size:11px;color:var(--text-muted);"><?= date('d/m H:i', strtotime($t['created_at'])) ?></div>
              </td>
              <td>
                <span class="badge badge-<?= $t['jenis']==='setor'?'success':'warning' ?>">
                  <?= $t['jenis']==='setor'?'Setor':'Tarik' ?>
                </span>
                <div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><?= ucfirst($t['jenis_simpanan']) ?></div>
              </td>
              <td style="font-weight:700;color:<?= $t['jenis']==='setor'?'var(--success)':'var(--danger)' ?>;">
                <?= $t['jenis']==='setor'?'+':'-' ?><?= rupiah($t['jumlah']) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
<?php if(!empty($chartSimpanan)): ?>
const chartData = <?= json_encode($chartSimpanan) ?>;
const maxValue = <?= json_encode($maxTotal) ?>;

// Gradient colors
const gradientColors = [
  'rgba(41, 128, 185, 0.85)',
  'rgba(39, 174, 96, 0.85)',
  'rgba(142, 68, 173, 0.85)',
  'rgba(230, 126, 34, 0.85)',
  'rgba(52, 73, 94, 0.85)',
  'rgba(22, 160, 133, 0.85)'
];

new Chart(document.getElementById('chartSimpanan'), {
  type: 'bar',
  data: {
    labels: chartData.map(d => d.bulan),
    datasets: [{
      label: 'Setoran Simpanan',
      data: chartData.map(d => parseFloat(d.total)),
      backgroundColor: chartData.map((_, i) => gradientColors[i % gradientColors.length]),
      borderColor: chartData.map((_, i) => gradientColors[i % gradientColors.length].replace('0.85', '1')),
      borderWidth: 2,
      borderRadius: 10,
      borderSkipped: false,
      hoverBackgroundColor: chartData.map((_, i) => gradientColors[i % gradientColors.length].replace('0.85', '1')),
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { 
      legend: { display: false },
      tooltip: {
        backgroundColor: 'rgba(0,0,0,0.8)',
        titleFont: { size: 13, weight: 'bold' },
        bodyFont: { size: 12 },
        padding: 12,
        cornerRadius: 8,
        callbacks: {
          label: function(context) {
            return 'Setoran: Rp ' + context.parsed.y.toLocaleString('id-ID');
          }
        }
      }
    },
    scales: {
      y: { 
        beginAtZero: true,
        max: maxValue * 1.2,
        ticks: { 
          callback: v => 'Rp ' + (v/1000000).toFixed(1) + 'jt', 
          font: { size: 11 },
          color: '#666'
        }, 
        grid: { color: '#e5e5e5' },
        border: { display: false }
      },
      x: { 
        ticks: { font: { size: 11, weight: '500' }, color: '#666' }, 
        grid: { display: false },
        border: { display: false }
      }
    },
    animation: {
      duration: 1000,
      easing: 'easeOutQuart'
    }
  }
});
<?php endif; ?>
</script>

<?php renderFooter(); ?>

<?php
/**
 * Layout Header - Sistem Informasi Koperasi
 * @param string $pageTitle
 * @param string $activeMenu
 */
function renderHeader(string $pageTitle = 'Dashboard', string $activeMenu = 'dashboard'): void {
    $user = currentUser();
    $flash = getFlash();
    $roleLabel = ['admin'=>'Administrator','manager'=>'Manager','kasir'=>'Kasir','anggota'=>'Anggota'][$user['role']] ?? $user['role'];
    $roleColor = ['admin'=>'#e74c3c','manager'=>'#f39c12','kasir'=>'#27ae60','anggota'=>'#3498db'][$user['role']] ?? '#888';
    $stats = getDashboardStats();
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?> — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
  --primary: #1a3a5c;
  --primary-light: #234b75;
  --accent: #e8a020;
  --accent-light: #f5c060;
  --success: #1a9e5c;
  --danger: #c0392b;
  --warning: #e67e22;
  --info: #2980b9;
  --bg: #f0f4f8;
  --surface: #ffffff;
  --border: #dce3ec;
  --text: #1a2535;
  --text-muted: #6b7a90;
  --sidebar-w: 260px;
  --font: 'Plus Jakarta Sans', sans-serif;
  --mono: 'JetBrains Mono', monospace;
  --radius: 12px;
  --shadow: 0 2px 12px rgba(26,58,92,0.10);
  --shadow-lg: 0 8px 32px rgba(26,58,92,0.16);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--font);
  background: var(--bg);
  color: var(--text);
  display: flex;
  min-height: 100vh;
}

/* ─── Sidebar ─────────────────────────────────────── */
.sidebar {
  width: var(--sidebar-w);
  background: var(--primary);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 100;
  box-shadow: 4px 0 20px rgba(0,0,0,0.2);
  transition: transform .3s ease;
}

.sidebar-brand {
  padding: 28px 24px 20px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}

.sidebar-logo {
  display: flex; align-items: center; gap: 12px;
}

.logo-icon {
  width: 44px; height: 44px;
  background: linear-gradient(135deg, var(--accent), var(--accent-light));
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; font-weight: 800; color: var(--primary);
  box-shadow: 0 4px 12px rgba(232,160,32,0.4);
}

.logo-text h1 {
  font-size: 18px; font-weight: 800; color: #fff; line-height: 1.1;
}
.logo-text span {
  font-size: 11px; color: rgba(255,255,255,0.5); font-weight: 400; letter-spacing: .5px;
}

.sidebar-nav { flex: 1; padding: 16px 12px; overflow-y: auto; }

.nav-section-label {
  font-size: 10px; font-weight: 700; letter-spacing: 1.5px;
  color: rgba(255,255,255,0.3); text-transform: uppercase;
  padding: 16px 12px 6px; margin-top: 8px;
}

.nav-item {
  display: flex; align-items: center; gap: 12px;
  padding: 11px 14px; border-radius: 10px;
  color: rgba(255,255,255,0.65); font-size: 14px; font-weight: 500;
  text-decoration: none; cursor: pointer;
  transition: all .2s ease; margin-bottom: 2px; position: relative;
}

.nav-item:hover {
  background: rgba(255,255,255,0.08);
  color: #fff;
}

.nav-item.active {
  background: rgba(232,160,32,0.15);
  color: var(--accent-light);
  font-weight: 600;
}

.nav-item.active::before {
  content: '';
  position: absolute; left: 0; top: 20%; bottom: 20%;
  width: 3px; background: var(--accent);
  border-radius: 0 3px 3px 0;
}

.nav-item i { width: 20px; text-align: center; font-size: 15px; }

.badge-alert {
  margin-left: auto; background: var(--danger);
  color: #fff; font-size: 10px; font-weight: 700;
  padding: 2px 7px; border-radius: 20px;
}

.sidebar-user {
  padding: 16px;
  border-top: 1px solid rgba(255,255,255,0.08);
}

.user-card {
  display: flex; align-items: center; gap: 12px;
  background: rgba(255,255,255,0.06);
  border-radius: 10px; padding: 12px;
}

.user-avatar {
  width: 38px; height: 38px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; color: var(--primary); font-size: 15px;
}

.user-info { flex: 1; min-width: 0; }
.user-info strong { display: block; font-size: 13px; color: #fff; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-info small { font-size: 11px; }

.btn-logout { background: none; border: none; color: rgba(255,255,255,0.4); cursor: pointer; padding: 4px; border-radius: 6px; transition: color .2s; }
.btn-logout:hover { color: var(--danger); }

/* ─── Main Content ────────────────────────────────── */
.main-wrap {
  margin-left: var(--sidebar-w);
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.topbar {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: 0 28px;
  height: 64px;
  display: flex; align-items: center; justify-content: space-between;
  position: sticky; top: 0; z-index: 50;
  box-shadow: 0 1px 8px rgba(26,58,92,0.06);
}

.topbar-title { font-size: 18px; font-weight: 700; color: var(--primary); }
.topbar-sub   { font-size: 12px; color: var(--text-muted); font-weight: 400; }

.topbar-right { display: flex; align-items: center; gap: 16px; }

.topbar-time {
  font-family: var(--mono);
  font-size: 13px; color: var(--text-muted);
  background: var(--bg); padding: 6px 12px;
  border-radius: 8px;
}

.page-content { padding: 28px; flex: 1; }

/* ─── Cards ───────────────────────────────────────── */
.card {
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  border: 1px solid var(--border);
  overflow: hidden;
}

.card-header {
  padding: 18px 24px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  background: #fafbfd;
}

.card-title { font-size: 15px; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 8px; }
.card-body  { padding: 24px; }

/* ─── Stats Cards ─────────────────────────────────── */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px; margin-bottom: 24px; }

.stat-card {
  background: var(--surface);
  border-radius: var(--radius);
  padding: 20px 22px;
  display: flex; align-items: flex-start; gap: 16px;
  border: 1px solid var(--border);
  box-shadow: var(--shadow);
  transition: all .25s ease;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }

.stat-icon {
  width: 48px; height: 48px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; flex-shrink: 0;
}

.stat-info small { font-size: 12px; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: .5px; }
.stat-info strong { display: block; font-size: 22px; font-weight: 800; color: var(--text); line-height: 1.2; margin-top: 2px; }
.stat-info .stat-sub { font-size: 11px; color: var(--text-muted); margin-top: 3px; }

/* ─── Tables ──────────────────────────────────────── */
.table-wrap { overflow-x: auto; }
.tbl { width: 100%; border-collapse: collapse; font-size: 14px; }
.tbl th { background: var(--bg); color: var(--primary); font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: .6px; padding: 12px 16px; text-align: left; border-bottom: 2px solid var(--border); white-space: nowrap; }
.tbl td { padding: 13px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.tbl tbody tr:hover { background: #f8fafc; }
.tbl tbody tr:last-child td { border-bottom: none; }

/* ─── Badges ──────────────────────────────────────── */
.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-success { background: #d4edda; color: #155724; }
.badge-danger   { background: #f8d7da; color: #721c24; }
.badge-warning  { background: #fff3cd; color: #856404; }
.badge-info     { background: #d1ecf1; color: #0c5460; }
.badge-secondary{ background: #e2e3e5; color: #383d41; }
.badge-primary  { background: #cce5ff; color: #004085; }

/* ─── Buttons ─────────────────────────────────────── */
.btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 18px; border-radius: 9px; font-size: 13px; font-weight: 600;
  border: none; cursor: pointer; text-decoration: none; transition: all .2s ease;
  font-family: var(--font); white-space: nowrap;
}
.btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 7px; }
.btn-xs { padding: 4px 9px; font-size: 11px; border-radius: 6px; }
.btn-primary  { background: var(--primary); color: #fff; }
.btn-primary:hover { background: var(--primary-light); transform: translateY(-1px); }
.btn-accent   { background: var(--accent); color: var(--primary); }
.btn-accent:hover { background: var(--accent-light); }
.btn-success  { background: var(--success); color: #fff; }
.btn-success:hover { filter: brightness(1.1); }
.btn-danger   { background: var(--danger); color: #fff; }
.btn-danger:hover { filter: brightness(1.1); }
.btn-outline  { background: transparent; border: 1.5px solid var(--border); color: var(--text); }
.btn-outline:hover { border-color: var(--primary); color: var(--primary); background: #f0f4f8; }
.btn-ghost    { background: transparent; color: var(--text-muted); }
.btn-ghost:hover { background: var(--bg); color: var(--text); }

/* ─── Forms ───────────────────────────────────────── */
.form-group { margin-bottom: 18px; }
.form-label { display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px; }
.form-label .req { color: var(--danger); margin-left: 3px; }
.form-control {
  width: 100%; padding: 10px 14px;
  border: 1.5px solid var(--border); border-radius: 9px;
  font-size: 14px; font-family: var(--font); color: var(--text);
  background: #fff; transition: border-color .2s, box-shadow .2s;
}
.form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,58,92,0.08); }
.form-control:invalid { border-color: var(--danger); }
.form-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7a90' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 36px; }
.form-hint { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
.form-row { display: grid; gap: 16px; }
.col-2 { grid-template-columns: 1fr 1fr; }
.col-3 { grid-template-columns: 1fr 1fr 1fr; }

/* ─── Alert / Flash ───────────────────────────────── */
.alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; display: flex; align-items: flex-start; gap: 10px; border: 1px solid transparent; }
.alert-success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
.alert-danger   { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
.alert-warning  { background: #fff3cd; border-color: #ffeeba; color: #856404; }
.alert-info     { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }

/* ─── Misc ────────────────────────────────────────── */
.divider { border: none; border-top: 1px solid var(--border); margin: 20px 0; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.flex { display: flex; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }
.mt-4 { margin-top: 16px; }
.mb-4 { margin-bottom: 16px; }
.font-mono { font-family: var(--mono); }
.text-sm { font-size: 13px; }
.text-xs { font-size: 11px; }
.text-muted { color: var(--text-muted); }
.fw-bold { font-weight: 700; }
.text-danger { color: var(--danger); }
.text-success { color: var(--success); }
.text-warning { color: var(--warning); }

/* ─── Pagination ──────────────────────────────────── */
.pagination { display: flex; gap: 4px; align-items: center; }
.page-link { padding: 6px 12px; border-radius: 7px; font-size: 13px; font-weight: 500; text-decoration: none; color: var(--primary); background: var(--surface); border: 1px solid var(--border); transition: all .2s; }
.page-link:hover, .page-link.active { background: var(--primary); color: #fff; border-color: var(--primary); }

/* ─── Modal ───────────────────────────────────────── */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
.modal-overlay.open { display: flex; }
.modal { background: var(--surface); border-radius: 16px; box-shadow: var(--shadow-lg); width: 90%; max-width: 540px; max-height: 90vh; overflow-y: auto; animation: slideUp .25s ease; }
.modal-lg { max-width: 800px; }
.modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.modal-title { font-size: 16px; font-weight: 700; color: var(--primary); }
.modal-body { padding: 24px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; }
.btn-close { background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-muted); line-height: 1; }
.btn-close:hover { color: var(--danger); }

@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

/* ─── Responsive ──────────────────────────────────── */
@media(max-width:768px){
  .sidebar { transform: translateX(-100%); }
  .sidebar.open { transform: translateX(0); }
  .main-wrap { margin-left: 0; }
  .col-2, .col-3 { grid-template-columns: 1fr; }
  .stats-grid { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>

<!-- ═══ SIDEBAR ═══════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="sidebar-logo">
      <div class="logo-icon">K</div>
      <div class="logo-text">
        <h1><?= APP_NAME ?></h1>
        <span>Sistem Informasi Koperasi</span>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Utama</div>
    <a href="/koperasi/index.php" class="nav-item <?= $activeMenu==='dashboard' ? 'active' : '' ?>">
      <i class="fas fa-th-large"></i> Dashboard
    </a>

    <div class="nav-section-label">Anggota</div>
    <a href="/koperasi/modules/anggota/list.php" class="nav-item <?= $activeMenu==='anggota' ? 'active' : '' ?>">
      <i class="fas fa-users"></i> Data Anggota
    </a>

    <div class="nav-section-label">Keuangan</div>
    <a href="/koperasi/modules/simpanan/list.php" class="nav-item <?= $activeMenu==='simpanan' ? 'active' : '' ?>">
      <i class="fas fa-piggy-bank"></i> Simpanan
    </a>
    <a href="/koperasi/modules/pinjaman/list.php" class="nav-item <?= $activeMenu==='pinjaman' ? 'active' : '' ?>">
      <i class="fas fa-hand-holding-usd"></i> Pinjaman
      <?php if($stats['angsuran_telat'] > 0): ?>
        <span class="badge-alert"><?= $stats['angsuran_telat'] ?></span>
      <?php endif ?>
    </a>
    <a href="/koperasi/modules/pinjaman/angsuran.php" class="nav-item <?= $activeMenu==='angsuran' ? 'active' : '' ?>">
      <i class="fas fa-calendar-check"></i> Angsuran
    </a>

    <div class="nav-section-label">Laporan</div>
    <a href="/koperasi/modules/laporan/neraca.php" class="nav-item <?= $activeMenu==='neraca' ? 'active' : '' ?>">
      <i class="fas fa-balance-scale"></i> Neraca
    </a>
    <a href="/koperasi/modules/laporan/shu.php" class="nav-item <?= $activeMenu==='shu' ? 'active' : '' ?>">
      <i class="fas fa-chart-pie"></i> Laporan SHU
    </a>
    <a href="/koperasi/modules/laporan/jurnal.php" class="nav-item <?= $activeMenu==='jurnal' ? 'active' : '' ?>">
      <i class="fas fa-book"></i> Buku Jurnal
    </a>

    <div class="nav-section-label">Data</div>
    <a href="/koperasi/modules/import/index.php" class="nav-item <?= $activeMenu==='import' ? 'active' : '' ?>">
      <i class="fas fa-file-excel"></i> Import Excel
    </a>

    <?php if(hasRole(['admin'])): ?>
    <div class="nav-section-label">Sistem</div>
    <a href="/koperasi/modules/users/list.php" class="nav-item <?= $activeMenu==='users' ? 'active' : '' ?>">
      <i class="fas fa-user-cog"></i> Pengguna
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-user">
    <div class="user-card">
      <div class="user-avatar" style="background:<?= $roleColor ?>22;color:<?= $roleColor ?>;">
        <?= strtoupper(substr($user['nama'],0,1)) ?>
      </div>
      <div class="user-info">
        <strong><?= h($user['nama']) ?></strong>
        <small style="color:<?= $roleColor ?>;font-weight:600;"><?= $roleLabel ?></small>
      </div>
      <form method="post" action="/koperasi/logout.php">
        <button type="submit" class="btn-logout" title="Logout"><i class="fas fa-sign-out-alt"></i></button>
      </form>
    </div>
  </div>
</aside>

<!-- ═══ MAIN ══════════════════════════════════════════════════ -->
<div class="main-wrap">
  <header class="topbar">
    <div>
      <div class="topbar-title"><?= h($pageTitle) ?></div>
      <div class="topbar-sub"><i class="fas fa-map-marker-alt" style="font-size:10px"></i> <?= APP_NAME ?></div>
    </div>
    <div class="topbar-right">
      <div class="topbar-time" id="liveClock"></div>
    </div>
  </header>

  <main class="page-content">
  <?php if($flash): ?>
    <div class="alert alert-<?= h($flash['type']) ?>">
      <i class="fas fa-<?= $flash['type']==='success'?'check-circle':($flash['type']==='danger'?'exclamation-circle':'info-circle') ?>"></i>
      <?= h($flash['msg']) ?>
    </div>
  <?php endif; ?>
    <?php
}

function renderFooter(): void { ?>
  </main>
  <footer style="padding:16px 28px;border-top:1px solid var(--border);font-size:12px;color:var(--text-muted);text-align:center;">
    &copy; <?= date('Y') ?> <?= APP_NAME ?> &mdash; Sistem Informasi Koperasi v<?= APP_VERSION ?>
  </footer>
</div>

<script>
// Live clock
(function clock() {
  const el = document.getElementById('liveClock');
  if (el) {
    const update = () => {
      const now = new Date();
      const pad = n => String(n).padStart(2,'0');
      el.textContent = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'][now.getDay()===0?6:now.getDay()-1]
        + ', ' + pad(now.getDate()) + '/' + pad(now.getMonth()+1) + '/' + now.getFullYear()
        + '  ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
    };
    update(); setInterval(update, 1000);
  }
})();

// Modal helpers
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if(e.target === m) m.classList.remove('open'); });
});

// Flash auto-dismiss
setTimeout(() => { document.querySelectorAll('.alert').forEach(a => { a.style.transition='opacity .5s'; a.style.opacity='0'; setTimeout(()=>a.remove(),500); }); }, 4000);

// Confirm delete
function confirmDelete(form, nama) {
  return confirm('Hapus ' + (nama || 'data ini') + '? Aksi ini tidak dapat dibatalkan.');
}

// Format rupiah input
function formatRupiah(input) {
  input.addEventListener('input', function() {
    let val = this.value.replace(/[^\d]/g,'');
    this.value = val ? parseInt(val,10).toLocaleString('id-ID') : '';
  });
}
document.querySelectorAll('[data-rupiah]').forEach(formatRupiah);

// Sidebar toggle mobile
const sidebarToggle = document.getElementById('sidebarToggle');
if (sidebarToggle) sidebarToggle.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('open'));
</script>
</body>
</html>
<?php }

// Sanitize output
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Pagination helper
function paginate(int $total, int $perPage, int $page, string $url): array {
    $totalPages = (int)ceil($total / $perPage);
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $page,
        'total_pages' => $totalPages,
        'offset'      => ($page - 1) * $perPage,
        'prev'        => $page > 1 ? $page - 1 : null,
        'next'        => $page < $totalPages ? $page + 1 : null,
        'url'         => $url,
    ];
}

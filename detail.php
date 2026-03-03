<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/layout.php';
requireLogin();
requireRole(['admin']);

$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('danger', 'ID pengguna tidak valid.');
    header('Location: list.php');
    exit;
}

$user = $db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
if (!$user) {
    setFlash('danger', 'Pengguna tidak ditemukan.');
    header('Location: list.php');
    exit;
}

// Get related data
$anggota = $db->fetchAll(
    "SELECT a.*, 
       (SELECT COUNT(*) FROM simpanan s WHERE s.anggota_id = a.id) as total_rekening,
       (SELECT COALESCE(SUM(s.saldo),0) FROM simpanan s WHERE s.anggota_id = a.id) as total_saldo
     FROM anggota a WHERE a.user_id = ?",
    [$id]
);

$recentLogs = $db->fetchAll(
    "SELECT * FROM audit_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
    [$id]
);

$roleLabels = [
    'admin'    => 'Administrator',
    'anggota'  => 'Anggota'
];
$roleColors = [
    'admin'    => '#e74c3c',
    'anggota'  => '#3498db'
];
$roleLabel = $roleLabels[$user['role']] ?? $user['role'];
$roleColor = $roleColors[$user['role']] ?? '#888';

renderHeader('Detail Pengguna', 'users');
?>

<!-- ─── HEADER BAR ─────────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
  <div style="display:flex;align-items:center;gap:16px;">
    <a href="list.php" class="btn btn-outline" style="padding:8px 12px;">
      <i class="fas fa-arrow-left"></i>
    </a>
    <div>
      <h2 style="font-size:20px;font-weight:800;color:var(--primary);">Detail Pengguna</h2>
      <p style="font-size:13px;color:var(--text-muted);">Informasi lengkap tentang pengguna</p>
    </div>
  </div>
  <div style="display:flex;gap:8px;">
    <button class="btn btn-outline" onclick="openModal('modalEdit')">
      <i class="fas fa-edit"></i> Edit
    </button>
    <button class="btn btn-outline" onclick="openModal('modalResetPassword')">
      <i class="fas fa-key"></i> Reset Password
    </button>
  </div>
</div>

<!-- ─── USER PROFILE CARD ────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-body">
    <div style="display:flex;align-items:flex-start;gap:24px;">
      <!-- Avatar -->
      <div style="width:100px;height:100px;border-radius:20px;background:linear-gradient(135deg, <?= $roleColor ?>, <?= $roleColor ?>88);display:flex;align-items:center;justify-content:center;font-size:36px;font-weight:800;color:#fff;flex-shrink:0;box-shadow:0 4px 16px <?= $roleColor ?>44;">
        <?= strtoupper(substr($user['nama'],0,2)) ?>
      </div>
      
      <!-- Info -->
      <div style="flex:1;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
          <h3 style="font-size:24px;font-weight:800;color:var(--primary);"><?= h($user['nama']) ?></h3>
          <span class="badge badge-<?= $user['role']==='admin'?'danger':'primary' ?>">
            <?= $roleLabel ?>
          </span>
          <span class="badge badge-<?= $user['is_active'] ? 'success' : 'secondary' ?>">
            <?= $user['is_active'] ? 'Aktif' : 'Non-Aktif' ?>
          </span>
        </div>
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-top:16px;">
          <div>
            <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Username</div>
            <div style="font-size:14px;font-weight:600;font-family:var(--mono);color:var(--text);"><?= h($user['username']) ?></div>
          </div>
          <div>
            <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Email</div>
            <div style="font-size:14px;color:var(--text);"><?= h($user['email'] ?? '-') ?></div>
          </div>
          <div>
            <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Login Terakhir</div>
            <div style="font-size:14px;color:var(--text);"><?= $user['last_login'] ? tglIndo($user['last_login']) . ' ' . date('H:i', strtotime($user['last_login'])) : 'Belum pernah login' ?></div>
          </div>
          <div>
            <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Tanggal Dibuat</div>
            <div style="font-size:14px;color:var(--text);"><?= tglIndo($user['created_at']) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ─── STATS & RELATED DATA ─────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin-bottom:20px;">
  <!-- Anggota Terkait -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-users"></i> Anggota Terkait</div>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($anggota)): ?>
        <div style="padding:24px;text-align:center;color:var(--text-muted);">
          <i class="fas fa-user-plus" style="font-size:32px;display:block;margin-bottom:8px;opacity:.3;"></i>
          Tidak ada anggota terkait
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="tbl">
            <thead>
              <tr>
                <th>No. Anggota</th>
                <th>Nama</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($anggota as $a): ?>
                <tr>
                  <td class="font-mono" style="font-size:12px;"><?= h($a['no_anggota']) ?></td>
                  <td><?= h($a['nama_lengkap']) ?></td>
                  <td><span class="badge badge-<?= $a['status']==='aktif'?'success':'secondary' ?>"><?= $a['status'] ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Aktivitas Terkini -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-history"></i> Aktivitas Terkini</div>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($recentLogs)): ?>
        <div style="padding:24px;text-align:center;color:var(--text-muted);">
          <i class="fas fa-clipboard-list" style="font-size:32px;display:block;margin-bottom:8px;opacity:.3;"></i>
          Tidak ada aktivitas tercatat
        </div>
      <?php else: ?>
        <div style="max-height:300px;overflow-y:auto;">
          <?php foreach ($recentLogs as $log): ?>
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;gap:12px;">
              <div style="width:32px;height:32px;border-radius:8px;background:var(--bg);display:flex;align-items:center;justify-content:center;color:var(--text-muted);flex-shrink:0;">
                <i class="fas fa-<?= strpos($log['aksi'], 'login') !== false ? 'sign-in-alt' : (strpos($log['aksi'], 'tambah') !== false ? 'plus' : (strpos($log['aksi'], 'edit') !== false ? 'edit' : (strpos($log['aksi'], 'hapus') !== false ? 'trash' : 'cog'))) ?>"></i>
              </div>
              <div style="flex:1;">
                <div style="font-size:13px;font-weight:600;color:var(--text);"><?= h($log['aksi']) ?></div>
                <div style="font-size:11px;color:var(--text-muted);">
                  <?= tglIndo($log['created_at']) . ' ' . date('H:i', strtotime($log['created_at'])) ?>
                  <?php if($log['tabel']): ?>
                     • <?= h($log['tabel']) ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ═══ MODAL: Edit Pengguna ════════════════════════════════ -->
<div class="modal-overlay" id="modalEdit">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-edit" style="color:var(--primary)"></i> Edit Pengguna</div>
      <button class="btn-close" onclick="closeModal('modalEdit')">&times;</button>
    </div>
    <form method="post" action="save.php">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" value="<?= $user['id'] ?>">
      <div class="modal-body">
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" value="<?= h($user['username']) ?>" readonly style="background:var(--bg);">
            <div class="form-hint">Username tidak dapat diubah</div>
          </div>
          <div class="form-group">
            <label class="form-label">Nama Lengkap <span class="req">*</span></label>
            <input type="text" name="nama" class="form-control" required value="<?= h($user['nama']) ?>">
          </div>
        </div>
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= h($user['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Role <span class="req">*</span></label>
            <select name="role" class="form-control form-select" required>
              <option value="admin" <?= $user['role']==='admin'?'selected':'' ?>>Administrator</option>
            </select>
          </div>
        </div>
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Password Baru</label>
            <input type="password" name="password_baru" class="form-control" placeholder="Kosongkan jika tidak diubah" minlength="6">
            <div class="form-hint">Minimal 6 karakter</div>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="is_active" class="form-control form-select">
              <option value="1" <?= $user['is_active']?'selected':'' ?>>Aktif</option>
              <option value="0" <?= !$user['is_active']?'selected':'' ?>>Non-Aktif</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalEdit')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ MODAL: Reset Password ════════════════════════════════ -->
<div class="modal-overlay" id="modalResetPassword">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-key" style="color:var(--warning)"></i> Reset Password</div>
      <button class="btn-close" onclick="closeModal('modalResetPassword')">&times;</button>
    </div>
    <form method="post" action="save.php">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="id" value="<?= $user['id'] ?>">
      <div class="modal-body">
        <p style="margin-bottom:16px;">Reset password untuk: <strong><?= h($user['nama']) ?></strong></p>
        <div class="form-group">
          <label class="form-label">Password Baru <span class="req">*</span></label>
          <input type="password" name="password_baru" class="form-control" required minlength="6" placeholder="Minimal 6 karakter">
        </div>
        <div class="form-group">
          <label class="form-label">Konfirmasi Password <span class="req">*</span></label>
          <input type="password" name="password_confirm" class="form-control" required minlength="6" placeholder="Ulangi password">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalResetPassword')">Batal</button>
        <button type="submit" class="btn btn-warning"><i class="fas fa-key"></i> Reset Password</button>
      </div>
    </form>
  </div>
</div>

<?php renderFooter(); ?>

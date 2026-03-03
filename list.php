<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/layout.php';
requireLogin();
requireRole(['admin']);

$db = Database::getInstance();

// ─── Pagination & Filter ───────────────────────────────────────
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$search  = trim($_GET['search'] ?? '');
$role    = $_GET['role'] ?? '';
$status  = $_GET['status'] ?? '';

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = "(u.nama LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $params   = array_merge($params, ["%$search%","%$search%","%$search%"]);
}
if ($role)   { $where[] = "u.role = ?"; $params[] = $role; }
if ($status !== '') { $where[] = "u.is_active = ?"; $params[] = (int)$status; }

$whereStr = implode(' AND ', $where);
$total    = (int)$db->fetch("SELECT COUNT(*) c FROM users u WHERE $whereStr", $params)['c'];
$pg       = paginate($total, $perPage, $page, '?');
$users    = $db->fetchAll(
    "SELECT u.*,
       (SELECT COUNT(*) FROM anggota a WHERE a.user_id = u.id) AS total_anggota
     FROM users u
     WHERE $whereStr
     ORDER BY u.created_at DESC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);

renderHeader('Kelola Pengguna', 'users');
?>

<!-- ─── HEADER BAR ─────────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
  <div>
    <h2 style="font-size:20px;font-weight:800;color:var(--primary);">Kelola Pengguna</h2>
    <p style="font-size:13px;color:var(--text-muted);">Total <?= number_format($total) ?> pengguna terdaftar</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('modalTambah')">
    <i class="fas fa-user-plus"></i> Tambah Pengguna
  </button>
</div>

<!-- ─── STATS ───────────────────────────────────────────────── -->
<div class="stats-grid" style="margin-bottom:20px;">
  <div class="stat-card">
    <div class="stat-icon" style="background:#e8f4fd;color:#2980b9;"><i class="fas fa-users"></i></div>
    <div class="stat-info">
      <small>Total Pengguna</small>
      <strong><?= number_format($total) ?></strong>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#d4edda;color:#1a9e5c;"><i class="fas fa-user-check"></i></div>
    <div class="stat-info">
      <small>Aktif</small>
      <strong><?= (int)$db->fetch("SELECT COUNT(*) c FROM users WHERE is_active=1")['c'] ?></strong>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fce8f3;color:#c0392b;"><i class="fas fa-user-times"></i></div>
    <div class="stat-info">
      <small>Non-Aktif</small>
      <strong><?= (int)$db->fetch("SELECT COUNT(*) c FROM users WHERE is_active=0")['c'] ?></strong>
    </div>
  </div>
</div>

<!-- ─── FILTER BAR ────────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-body" style="padding:16px;">
    <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
      <div style="flex:1;min-width:220px;">
        <label class="form-label" style="margin-bottom:4px;">Cari Pengguna</label>
        <div style="position:relative;">
          <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#aaa;font-size:13px;"></i>
          <input type="text" name="search" class="form-control" style="padding-left:36px;" placeholder="Nama, Username, Email..." value="<?= h($search) ?>">
        </div>
      </div>
      <div style="min-width:140px;">
        <label class="form-label" style="margin-bottom:4px;">Role</label>
        <select name="role" class="form-control form-select">
          <option value="">Semua Role</option>
          <option value="admin" <?= $role==='admin'?'selected':'' ?>>Administrator</option>
        </select>
      </div>
      <div style="min-width:140px;">
        <label class="form-label" style="margin-bottom:4px;">Status</label>
        <select name="status" class="form-control form-select">
          <option value="">Semua Status</option>
          <option value="1" <?= $status==='1'?'selected':'' ?>>Aktif</option>
          <option value="0" <?= $status==='0'?'selected':'' ?>>Non-Aktif</option>
        </select>
      </div>
      <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
        <a href="?" class="btn btn-outline"><i class="fas fa-redo"></i></a>
      </div>
    </form>
  </div>
</div>

<!-- ─── TABLE ─────────────────────────────────────────────── -->
<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>Username</th>
          <th>Nama Lengkap</th>
          <th>Email</th>
          <th>Role</th>
          <th>Status</th>
          <th>Login Terakhir</th>
          <th class="text-center">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr><td colspan="7" class="text-center" style="padding:40px;color:var(--text-muted);">
            <i class="fas fa-user-cog" style="font-size:36px;display:block;margin-bottom:8px;opacity:.3;"></i>
            Tidak ada pengguna ditemukan
          </td></tr>
        <?php else: foreach ($users as $u):
          $roleColors = [
            'admin'    => '#e74c3c',
            'anggota'  => '#3498db'
          ];
          $roleLabels = [
            'admin'    => 'Administrator',
            'anggota'  => 'Anggota'
          ];
          $roleColor = $roleColors[$u['role']] ?? '#888';
          $roleLabel = $roleLabels[$u['role']] ?? $u['role'];
          $statusColor = $u['is_active'] ? 'success' : 'secondary';
        ?>
          <tr>
            <td>
              <span class="font-mono" style="font-size:12px;background:var(--bg);padding:3px 8px;border-radius:6px;font-weight:600;color:var(--primary);">
                <?= h($u['username']) ?>
              </span>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:10px;background:<?= $roleColor ?>22;color:<?= $roleColor ?>;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0;">
                  <?= strtoupper(substr($u['nama'],0,1)) ?>
                </div>
                <div style="font-weight:600;font-size:14px;"><?= h($u['nama']) ?></div>
              </div>
            </td>
            <td style="font-size:13px;color:var(--text-muted);"><?= h($u['email'] ?? '-') ?></td>
            <td>
              <span class="badge badge-<?= $u['role']==='admin'?'danger':'primary' ?>">
                <?= $roleLabel ?>
              </span>
            </td>
            <td>
              <span class="badge badge-<?= $statusColor ?>">
                <?= $u['is_active'] ? 'Aktif' : 'Non-Aktif' ?>
              </span>
            </td>
            <td style="font-size:13px;color:var(--text-muted);">
              <?= $u['last_login'] ? tglIndo($u['last_login']) . ' ' . date('H:i', strtotime($u['last_login'])) : '-' ?>
            </td>
            <td>
              <div style="display:flex;gap:4px;justify-content:center;">
                <a href="detail.php?id=<?= $u['id'] ?>" class="btn btn-xs btn-outline" title="Detail"><i class="fas fa-eye"></i></a>
                <button class="btn btn-xs btn-outline" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                <?php if($u['id'] != currentUser()['id']): ?>
                <button class="btn btn-xs btn-outline" onclick="toggleStatus(<?= $u['id'] ?>, <?= $u['is_active'] ?>)" title="<?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                  <i class="fas fa-<?= $u['is_active'] ? 'toggle-on' : 'toggle-off' ?>"></i>
                </button>
                <button class="btn btn-xs btn-outline" onclick="resetPassword(<?= $u['id'] ?>, '<?= h($u['nama']) ?>')" title="Reset Password">
                  <i class="fas fa-key"></i>
                </button>
                <button class="btn btn-xs btn-outline" onclick="deleteUser(<?= $u['id'] ?>, '<?= h($u['nama']) ?>')" title="Hapus Pengguna" style="color:var(--danger);">
                  <i class="fas fa-trash"></i>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if($pg['total_pages'] > 1): ?>
  <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:between;">
    <div style="font-size:13px;color:var(--text-muted);flex:1;">
      Menampilkan <?= ($pg['offset']+1) ?>–<?= min($pg['offset']+$pg['per_page'],$total) ?> dari <?= number_format($total) ?> pengguna
    </div>
    <div class="pagination">
      <?php if($pg['prev']): ?><a href="?page=<?=$pg['prev']?>&search=<?=urlencode($search)?>&role=<?=urlencode($role)?>&status=<?=urlencode($status)?>" class="page-link"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
      <?php for($i=max(1,$page-2);$i<=min($pg['total_pages'],$page+2);$i++): ?>
        <a href="?page=<?=$i?>&search=<?=urlencode($search)?>&role=<?=urlencode($role)?>&status=<?=urlencode($status)?>" class="page-link <?=$i==$page?'active':''?>"><?=$i?></a>
      <?php endfor; ?>
      <?php if($pg['next']): ?><a href="?page=<?=$pg['next']?>&search=<?=urlencode($search)?>&role=<?=urlencode($role)?>&status=<?=urlencode($status)?>" class="page-link"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ═══ MODAL: Tambah Pengguna ════════════════════════════════ -->
<div class="modal-overlay" id="modalTambah">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-user-plus" style="color:var(--primary)"></i> Tambah Pengguna Baru</div>
      <button class="btn-close" onclick="closeModal('modalTambah')">&times;</button>
    </div>
    <form method="post" action="save.php">
      <input type="hidden" name="action" value="tambah">
      <div class="modal-body">
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Username <span class="req">*</span></label>
            <input type="text" name="username" class="form-control" required placeholder="Username login" pattern="[a-zA-Z0-9_]+" title="Hanya huruf, angka, dan underscore">
          </div>
          <div class="form-group">
            <label class="form-label">Nama Lengkap <span class="req">*</span></label>
            <input type="text" name="nama" class="form-control" required placeholder="Nama sesuai KTP">
          </div>
        </div>
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" placeholder="email@example.com">
          </div>
          <div class="form-group">
            <label class="form-label">Role <span class="req">*</span></label>
            <select name="role" class="form-control form-select" required>
              <option value="admin" selected>Administrator</option>
            </select>
          </div>
        </div>
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Password <span class="req">*</span></label>
            <input type="password" name="password" class="form-control" required minlength="6" placeholder="Min. 6 karakter">
          </div>
          <div class="form-group">
            <label class="form-label">Konfirmasi Password <span class="req">*</span></label>
            <input type="password" name="password_confirm" class="form-control" required minlength="6" placeholder="Ulangi password">
          </div>
        </div>
        <div class="form-group">
          <label style="font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="is_active" value="1" checked>
            Akun aktif
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalTambah')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Pengguna</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ MODAL: Edit Pengguna ══════════════════════════════════ -->
<div class="modal-overlay" id="modalEdit">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-edit" style="color:var(--primary)"></i> Edit Pengguna</div>
      <button class="btn-close" onclick="closeModal('modalEdit')">&times;</button>
    </div>
    <form method="post" action="save.php">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editId">
      <div class="modal-body">
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Username <span class="req">*</span></label>
            <input type="text" name="username" id="editUsername" class="form-control" required readonly style="background:var(--bg);">
            <div class="form-hint">Username tidak dapat diubah</div>
          </div>
          <div class="form-group">
            <label class="form-label">Nama Lengkap <span class="req">*</span></label>
            <input type="text" name="nama" id="editNama" class="form-control" required>
          </div>
        </div>
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" id="editEmail" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Role <span class="req">*</span></label>
            <select name="role" id="editRole" class="form-control form-select" required>
              <option value="admin">Administrator</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Password Baru</label>
          <input type="password" name="password_baru" class="form-control" placeholder="Kosongkan jika tidak diubah" minlength="6">
          <div class="form-hint">Minimal 6 karakter, kosongkan jika tidak ingin mengubah</div>
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
      <input type="hidden" name="id" id="resetId">
      <div class="modal-body">
        <p style="margin-bottom:16px;">Reset password untuk: <strong id="resetNama"></strong></p>
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

<!-- ═══ MODAL: Hapus Pengguna ════════════════════════════════ -->
<div class="modal-overlay" id="modalHapus">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-trash" style="color:var(--danger)"></i> Hapus Pengguna</div>
      <button class="btn-close" onclick="closeModal('modalHapus')">&times;</button>
    </div>
    <form method="post" action="save.php">
      <input type="hidden" name="action" value="hapus">
      <input type="hidden" name="id" id="deleteId">
      <div class="modal-body">
        <p style="margin-bottom:8px;">Apakah Anda yakin ingin menghapus pengguna berikut?</p>
        <div style="background:var(--bg);padding:12px;border-radius:8px;margin-bottom:16px;">
          <strong id="deleteNama" style="font-size:15px;"></strong>
        </div>
        <div style="background:#fff3cd;color:#856404;padding:12px;border-radius:8px;font-size:13px;">
          <i class="fas fa-exclamation-triangle"></i> Tindakan ini tidak dapat dibatalkan.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalHapus')">Batal</button>
        <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Hapus Pengguna</button>
      </div>
    </form>
  </div>
</div>

<script>
function editUser(data) {
  document.getElementById('editId').value      = data.id;
  document.getElementById('editUsername').value = data.username;
  document.getElementById('editNama').value    = data.nama;
  document.getElementById('editEmail').value   = data.email || '';
  document.getElementById('editRole').value   = data.role;
  document.getElementById('editStatus').value = data.is_active;
  openModal('modalEdit');
}

function toggleStatus(id, currentStatus) {
  const newStatus = currentStatus ? 'nonaktifkan' : 'aktifkan';
  if (confirm('Apakah Anda yakin ingin ' + newStatus + ' pengguna ini?')) {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = 'save.php';
    form.innerHTML = `
      <input type="hidden" name="action" value="toggle_status">
      <input type="hidden" name="id" value="${id}">
      <input type="hidden" name="status" value="${currentStatus ? 0 : 1}">
    `;
    document.body.appendChild(form);
    form.submit();
  }
}

function resetPassword(id, nama) {
  document.getElementById('resetId').value = id;
  document.getElementById('resetNama').textContent = nama;
  openModal('modalResetPassword');
}

function deleteUser(id, nama) {
  document.getElementById('deleteId').value = id;
  document.getElementById('deleteNama').textContent = nama;
  openModal('modalHapus');
}
</script>

<?php renderFooter(); ?>

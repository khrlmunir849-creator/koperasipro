<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireRole(['admin']);

// Redirect back with error if not POST
function redirectWithError(string $msg): void {
    setFlash('danger', $msg);
    header('Location: list.php');
    exit;
}

function redirectWithSuccess(string $msg): void {
    setFlash('success', $msg);
    header('Location: list.php');
    exit;
}

$db = Database::getInstance();
$action = $_POST['action'] ?? '';

// ─── TAMBAH PENGGUNA ─────────────────────────────────────────
if ($action === 'tambah') {
    $username = trim($_POST['username'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'admin';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($username) || empty($nama) || empty($password)) {
        redirectWithError('Username, nama, dan password wajib diisi.');
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        redirectWithError('Username hanya boleh berisi huruf, angka, dan underscore.');
    }

    if (strlen($password) < 6) {
        redirectWithError('Password minimal 6 karakter.');
    }

    if ($password !== $password_confirm) {
        redirectWithError('Password dan konfirmasi password tidak cocok.');
    }

    if (!in_array($role, ['admin'])) {
        redirectWithError('Role tidak valid.');
    }

    // Check if username exists
    $exists = $db->fetch("SELECT id FROM users WHERE username = ?", [$username]);
    if ($exists) {
        redirectWithError('Username sudah digunakan.');
    }

    // Check if email exists (if provided)
    if ($email) {
        $emailExists = $db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
        if ($emailExists) {
            redirectWithError('Email sudah digunakan.');
        }
    }

    // Insert user
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    
    try {
        $db->query(
            "INSERT INTO users (username, password, nama, email, role, is_active) VALUES (?,?,?,?,?,?)",
            [$username, $passwordHash, $nama, $email ?: null, $role, $is_active]
        );
        
        $userId = (int)$db->lastInsertId();
        
        // Audit log
        auditLog('Tambah pengguna', 'users', $userId, [], [
            'username' => $username,
            'nama' => $nama,
            'email' => $email,
            'role' => $role
        ]);
        
        redirectWithSuccess('Pengguna baru berhasil ditambahkan.');
    } catch (Exception $e) {
        redirectWithError('Gagal menambahkan pengguna: ' . $e->getMessage());
    }
}

// ─── EDIT PENGGUNA ───────────────────────────────────────────
if ($action === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'admin';
    $password_baru = $_POST['password_baru'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if ($id <= 0) {
        redirectWithError('ID pengguna tidak valid.');
    }

    if (empty($nama)) {
        redirectWithError('Nama wajib diisi.');
    }

    if (!in_array($role, ['admin'])) {
        redirectWithError('Role tidak valid.');
    }

    // Check if user exists
    $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$user) {
        redirectWithError('Pengguna tidak ditemukan.');
    }

    // Check if email exists (exclude current user)
    if ($email) {
        $emailExists = $db->fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $id]);
        if ($emailExists) {
            redirectWithError('Email sudah digunakan oleh pengguna lain.');
        }
    }

    // Build update query
    $updates = ['nama = ?', 'email = ?', 'role = ?', 'is_active = ?'];
    $params = [$nama, $email ?: null, $role, $is_active];

    // If password is provided, update it
    if (!empty($password_baru)) {
        if (strlen($password_baru) < 6) {
            redirectWithError('Password minimal 6 karakter.');
        }
        $updates[] = 'password = ?';
        $params[] = password_hash($password_baru, PASSWORD_BCRYPT);
    }

    $params[] = $id;

    try {
        $db->query(
            "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?",
            $params
        );
        
        // Audit log
        auditLog('Edit pengguna', 'users', $id, $user, [
            'nama' => $nama,
            'email' => $email,
            'role' => $role,
            'is_active' => $is_active,
            'password_changed' => !empty($password_baru)
        ]);
        
        redirectWithSuccess('Data pengguna berhasil diperbarui.');
    } catch (Exception $e) {
        redirectWithError('Gagal memperbarui pengguna: ' . $e->getMessage());
    }
}

// ─── TOGGLE STATUS PENGGUNA ───────────────────────────────────
if ($action === 'toggle_status') {
    $id = (int)($_POST['id'] ?? 0);
    $status = (int)($_POST['status'] ?? 0);

    if ($id <= 0) {
        redirectWithError('ID pengguna tidak valid.');
    }

    // Check if user exists
    $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$user) {
        redirectWithError('Pengguna tidak ditemukan.');
    }

    // Cannot toggle own status
    if ($id === currentUser()['id']) {
        redirectWithError('Anda tidak dapat mengubah status akun Anda sendiri.');
    }

    try {
        $db->query("UPDATE users SET is_active = ? WHERE id = ?", [$status, $id]);
        
        // Audit log
        auditLog('Toggle status pengguna', 'users', $id, $user, ['is_active' => $status]);
        
        $statusText = $status ? 'diaktifkan' : 'dinonaktifkan';
        redirectWithSuccess("Pengguna berhasil {$statusText}.");
    } catch (Exception $e) {
        redirectWithError('Gagal mengubah status: ' . $e->getMessage());
    }
}

// ─── RESET PASSWORD ──────────────────────────────────────────
if ($action === 'reset_password') {
    $id = (int)($_POST['id'] ?? 0);
    $password_baru = $_POST['password_baru'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if ($id <= 0) {
        redirectWithError('ID pengguna tidak valid.');
    }

    if (empty($password_baru)) {
        redirectWithError('Password baru wajib diisi.');
    }

    if (strlen($password_baru) < 6) {
        redirectWithError('Password minimal 6 karakter.');
    }

    if ($password_baru !== $password_confirm) {
        redirectWithError('Password dan konfirmasi password tidak cocok.');
    }

    // Check if user exists
    $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$user) {
        redirectWithError('Pengguna tidak ditemukan.');
    }

    try {
        $passwordHash = password_hash($password_baru, PASSWORD_BCRYPT);
        $db->query("UPDATE users SET password = ? WHERE id = ?", [$passwordHash, $id]);
        
        // Audit log
        auditLog('Reset password', 'users', $id, $user, ['password_reset' => true]);
        
        redirectWithSuccess('Password berhasil direset.');
    } catch (Exception $e) {
        redirectWithError('Gagal mereset password: ' . $e->getMessage());
    }
}

// ─── HAPUS PENGGUNA ──────────────────────────────────────────
if ($action === 'hapus') {
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        redirectWithError('ID pengguna tidak valid.');
    }

    // Check if user exists
    $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$user) {
        redirectWithError('Pengguna tidak ditemukan.');
    }

    // Cannot delete own account
    if ($id === currentUser()['id']) {
        redirectWithError('Anda tidak dapat menghapus akun Anda sendiri.');
    }

    try {
        // Check if user has related data
        $anggotaCount = (int)$db->fetch("SELECT COUNT(*) c FROM anggota WHERE user_id = ?", [$id])['c'];
        
        if ($anggotaCount > 0) {
            // Delete related anggota records first or set user_id to null
            $db->query("UPDATE anggota SET user_id = NULL WHERE user_id = ?", [$id]);
        }

        $db->query("DELETE FROM users WHERE id = ?", [$id]);
        
        // Audit log
        auditLog('Hapus pengguna', 'users', $id, $user, []);
        
        redirectWithSuccess('Pengguna berhasil dihapus.');
    } catch (Exception $e) {
        redirectWithError('Gagal menghapus pengguna: ' . $e->getMessage());
    }
}

// If no valid action, redirect back
redirectWithError('Aksi tidak valid.');
?>

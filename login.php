<?php
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: /koperasi/index.php'); exit;
}

$error = '';
$success = '';
$show_register = ($_GET['page'] ?? '') === 'register' || ($_POST['action'] ?? '') === 'register';

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db   = Database::getInstance();
        $user = $db->fetch("SELECT * FROM users WHERE username=? AND is_active=1", [$username]);
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['user_nama']     = $user['nama'];
            $_SESSION['user_role']     = $user['role'];
            $_SESSION['user_username'] = $user['username'];
            $db->query("UPDATE users SET last_login=NOW() WHERE id=?", [$user['id']]);
            auditLog('LOGIN');
            header('Location: /koperasi/index.php'); exit;
        }
        $error = 'Username atau password salah.';
    } else {
        $error = 'Username dan password wajib diisi.';
    }
}

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Validation
    if (empty($username) || empty($password) || empty($nama)) {
        $error = 'Username, nama, dan password wajib diisi.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username hanya boleh berisi huruf, angka, dan underscore.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $password_confirm) {
        $error = 'Password dan konfirmasi password tidak cocok.';
    } else {
        $db = Database::getInstance();
        
        // Check if username exists
        $exists = $db->fetch("SELECT id FROM users WHERE username = ?", [$username]);
        if ($exists) {
            $error = 'Username sudah digunakan.';
        } else {
            // Check if email exists (if provided)
            if ($email) {
                $emailExists = $db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
                if ($emailExists) {
                    $error = 'Email sudah digunakan.';
                }
            } else {
                // Insert new user (auto active, role 'admin')
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                
                try {
                    $db->query(
                        "INSERT INTO users (username, password, nama, email, role, is_active) VALUES (?,?,?,?,?,?)",
                        [$username, $passwordHash, $nama, $email ?: null, 'admin', 1]
                    );
                    
                    auditLog('REGISTER', 'users', (int)$db->lastInsertId(), [], [
                        'username' => $username,
                        'nama' => $nama,
                        'email' => $email,
                        'role' => 'anggota'
                    ]);
                    
                    $success = 'Pendaftaran berhasil! Silakan login dengan username dan password Anda.';
                    $show_register = false;
                } catch (Exception $e) {
                    $error = 'Gagal melakukan pendaftaran: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — KoperasiPro</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root { --primary:#1a3a5c; --accent:#e8a020; --bg:#f0f4f8; --font:'Plus Jakarta Sans',sans-serif; }
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font);background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;background-image:radial-gradient(ellipse at 70% 20%, rgba(26,58,92,0.06) 0%, transparent 60%), radial-gradient(ellipse at 20% 80%, rgba(232,160,32,0.06) 0%, transparent 60%);}
.login-wrap{width:100%;max-width:420px;padding:24px;}
.login-card{background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(26,58,92,0.15);overflow:hidden;}
.login-hero{background:linear-gradient(135deg,var(--primary) 0%,#234b75 100%);padding:36px;text-align:center;position:relative;overflow:hidden;}
.login-hero::before{content:'';position:absolute;top:-60px;right:-60px;width:180px;height:180px;border-radius:50%;background:rgba(232,160,32,0.12);}
.login-hero::after{content:'';position:absolute;bottom:-40px;left:-40px;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,0.04);}
.login-logo{width:64px;height:64px;background:linear-gradient(135deg,var(--accent),#f5c060);border-radius:18px;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:800;color:var(--primary);box-shadow:0 8px 24px rgba(232,160,32,0.4);position:relative;z-index:1;}
.login-hero h1{font-size:22px;font-weight:800;color:#fff;position:relative;z-index:1;}
.login-hero p{font-size:13px;color:rgba(255,255,255,0.6);margin-top:4px;position:relative;z-index:1;}
.login-body{padding:32px;}
.form-group{margin-bottom:18px;}
.form-label{display:block;font-size:13px;font-weight:600;color:#1a2535;margin-bottom:6px;}
.input-wrap{position:relative;}
.input-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#9aabb8;font-size:14px;}
.form-control{width:100%;padding:11px 14px 11px 40px;border:1.5px solid #dce3ec;border-radius:10px;font-size:14px;font-family:var(--font);transition:all .2s;background:#fafbfc;}
.form-control:focus{outline:none;border-color:var(--primary);background:#fff;box-shadow:0 0 0 3px rgba(26,58,92,0.08);}
.alert-danger{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:14px;display:flex;align-items:center;gap:8px;}
.alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:14px;display:flex;align-items:center;gap:8px;}
.btn-login{width:100%;padding:13px;background:var(--primary);color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;font-family:var(--font);cursor:pointer;transition:all .2s;margin-top:4px;}
.btn-login:hover{background:#234b75;transform:translateY(-1px);box-shadow:0 6px 20px rgba(26,58,92,0.3);}
.btn-register{width:100%;padding:13px;background:var(--accent);color:var(--primary);border:none;border-radius:10px;font-size:15px;font-weight:700;font-family:var(--font);cursor:pointer;transition:all .2s;margin-top:4px;}
.btn-register:hover{background:#f5c060;transform:translateY(-1px);box-shadow:0 6px 20px rgba(232,160,32,0.3);}
.btn-secondary{background:#6b7a90;color:#fff;border:none;padding:10px 20px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;}
.btn-secondary:hover{background:#5a6878;}
.login-footer{text-align:center;margin-top:20px;font-size:12px;color:#9aabb8;}
.demo-accounts{background:#f8fafc;border-radius:10px;padding:14px;margin-top:20px;}
.demo-accounts h4{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#9aabb8;margin-bottom:10px;}
.demo-item{display:flex;justify-content:space-between;font-size:12px;padding:4px 0;border-bottom:1px solid #eee;}
.demo-item:last-child{border:none;}
.demo-item span:first-child{font-weight:600;color:#1a3a5c;}
.demo-item span:last-child{color:#6b7a90;font-family:monospace;}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-hero">
      <div class="login-logo">K</div>
      <h1>KoperasiPro</h1>
      <p>Sistem Informasi Koperasi</p>
    </div>
    <div class="login-body">
      <?php if($success): ?>
        <div class="alert-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      
      <?php if($error): ?>
        <div class="alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if($show_register): ?>
      <!-- Registration Form -->
      <h2 style="font-size:18px;font-weight:700;margin-bottom:20px;color:#1a2535;">Daftar Akun Baru</h2>
      <form method="post">
        <input type="hidden" name="action" value="register">
        <div class="form-group">
          <label class="form-label">Nama Lengkap</label>
          <div class="input-wrap">
            <i class="fas fa-user"></i>
            <input type="text" name="nama" class="form-control" placeholder="Masukkan nama lengkap"
              value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required autofocus>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Username</label>
          <div class="input-wrap">
            <i class="fas fa-at"></i>
            <input type="text" name="username" class="form-control" placeholder="Masukkan username"
              value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email (Opsional)</label>
          <div class="input-wrap">
            <i class="fas fa-envelope"></i>
            <input type="email" name="email" class="form-control" placeholder="Masukkan email"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-wrap">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Konfirmasi Password</label>
          <div class="input-wrap">
            <i class="fas fa-lock"></i>
            <input type="password" name="password_confirm" class="form-control" placeholder="Masukkan password lagi" required>
          </div>
        </div>
        <button type="submit" class="btn-register"><i class="fas fa-user-plus"></i> Daftar</button>
        <div style="text-align:center;margin-top:16px;">
          <a href="?page=login" class="btn-secondary" style="text-decoration:none;"><i class="fas fa-arrow-left"></i> Kembali ke Login</a>
        </div>
      </form>
      <?php else: ?>
      <!-- Login Form -->
      <h2 style="font-size:18px;font-weight:700;margin-bottom:20px;color:#1a2535;">Masuk ke Akun</h2>

      <form method="post">
        <input type="hidden" name="action" value="login">
        <div class="form-group">
          <label class="form-label">Username</label>
          <div class="input-wrap">
            <i class="fas fa-user"></i>
            <input type="text" name="username" class="form-control" placeholder="Masukkan username"
              value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-wrap">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
          </div>
        </div>
        <button type="submit" class="btn-login"><i class="fas fa-sign-in-alt"></i> Masuk</button>
      </form>
      
      <div style="text-align:center;margin-top:20px;">
        <a href="?page=register" class="btn-register" style="display:inline-block;text-decoration:none;padding:12px 24px;">
          <i class="fas fa-user-plus"></i> Daftar Akun Baru
        </a>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="login-footer">&copy; <?= date('Y') ?> KoperasiPro — v1.0</div>
</div>
</body>
</html>

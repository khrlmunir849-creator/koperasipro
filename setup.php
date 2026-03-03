<?php
/**
 * Setup Script - KoperasiPro
 * Jalankan file ini sekali untuk setup database dan user default
 */

require_once __DIR__ . '/config/database.php';

echo "<h1>Setup KoperasiPro</h1>";

$db = Database::getInstance();

// Cek apakah tabel users ada
$tables = $db->fetchAll("SHOW TABLES");
$tableNames = array_column($tables, array_key_first($tables[0] ?? []));

if (!in_array('users', $tableNames)) {
    echo "<p style='color:red'>❌ Database belum di-setup. Silakan import file <code>config/schema.sql</code> terlebih dahulu melalui phpMyAdmin.</p>";
    echo "<p>Cara import:</p>";
    echo "<ol>";
    echo "<li>Buka phpMyAdmin: <a href='http://localhost/phpmyadmin' target='_blank'>http://localhost/phpmyadmin</a></li>";
    echo "<li>Buat database baru dengan nama: <code>koperasi_db</code></li>";
    echo "<li>Pilih database tersebut, klik tab <strong>Import</strong></li>";
    echo "<li>Pilih file <code>config/schema.sql</code></li>";
    echo "<li>Klik <strong>Go</strong></li>";
    echo "</ol>";
    exit;
}

// Cek apakah ada user admin, jika tidak ada buat user default
$admin = $db->fetch("SELECT * FROM users WHERE username='admin'");

if (!$admin) {
    echo "<p style='color:orange'>⚠️ User admin belum ada. Membuat user default...</p>";
    
    // Buat user admin default
    $passwordHash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
    $db->query("INSERT INTO users (username, password, nama, email, role, is_active) VALUES (?, ?, ?, ?, ?, ?)", 
        ['admin', $passwordHash, 'Administrator', 'admin@koperasi.id', 'admin', 1]);
    
    // Buat user manager
    $db->query("INSERT INTO users (username, password, nama, email, role, is_active) VALUES (?, ?, ?, ?, ?, ?)", 
        ['manager', $passwordHash, 'Manager Koperasi', 'manager@koperasi.id', 'manager', 1]);
    
    // Buat user kasir
    $db->query("INSERT INTO users (username, password, nama, email, role, is_active) VALUES (?, ?, ?, ?, ?, ?)", 
        ['kasir', $passwordHash, 'Kasir Kopi_Opsi', 'kasir@koperasi.id', 'kasir', 1]);
    
    echo "<p style='color:green'>✅ User default berhasil dibuat!</p>";
}

echo "<p>✅ Database sudah ter-setup.</p>";

// Cek password
echo "<h3>Informasi Login:</h3>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Username</th><th>Password</th><th>Role</th><th>Status</th></tr>";

$users = $db->fetchAll("SELECT * FROM users");
foreach ($users as $u) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($u['username']) . "</td>";
    echo "<td>admin123</td>";
    echo "<td>" . htmlspecialchars($u['role']) . "</td>";
    echo "<td>" . ($u['is_active'] ? 'Aktif' : 'Nonaktif') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Debug Info:</h3>";
echo "<pre>";
echo "Jumlah user: " . count($users) . "\n";
echo "User admin: " . print_r($admin, true);
echo "</pre>";

// Test password verify
if ($admin) {
    $testPass = 'admin123';
    $verify = password_verify($testPass, $admin['password']);
    echo "<p>Test password_verify('admin123', \$admin['password']): <strong>" . ($verify ? '✅ BERHASIL' : '❌ GAGAL') . "</strong></p>";
    
    if (!$verify) {
        echo "<p style='color:red'>⚠️ Password tidak cocok! Saya akan mereset password admin...</p>";
        
        $newHash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
        $db->query("UPDATE users SET password=? WHERE username='admin'", [$newHash]);
        
        echo "<p>✅ Password admin sudah direset. Silakan login lagi.</p>";
    }
}
?>
<style>
body { font-family: sans-serif; padding: 20px; }
a { color: #1a3a5c; }
code { background: #f0f0f0; padding: 2px 6px; border-radius: 4px; }
</style>

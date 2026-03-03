# KoperasiPro — Sistem Informasi Koperasi PHP

## Persyaratan
- PHP 8.1+ dengan ekstensi: PDO, PDO_MySQL, session
- MySQL 8.0+ atau MariaDB 10.6+
- Web Server: Apache/Nginx atau PHP built-in server

## Instalasi Cepat

### 1. Clone / Ekstrak ke web root
```bash
cp -r koperasi/ /var/www/html/
# atau untuk development:
cp -r koperasi/ ~/Sites/
```

### 2. Setup Database
```bash
mysql -u root -p < koperasi/config/schema.sql
```

### 3. Konfigurasi Database
Edit `koperasi/config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'koperasi_db');
define('DB_USER', 'root');       // sesuaikan
define('DB_PASS', '');           // sesuaikan
```

### 4. Jalankan
**Dengan PHP built-in server:**
```bash
php -S localhost:8080 -t /var/www/html
# Akses: http://localhost:8080/koperasi/
```

**Dengan Apache:** Pastikan mod_rewrite aktif, akses langsung via browser.

## Akun Default
| Username | Password  | Role      |
|----------|-----------|-----------|
| admin    | admin123  | Admin     |
| manager  | admin123  | Manager   |
| kasir    | admin123  | Kasir     |

> **Penting:** Ganti password default setelah instalasi!

## Struktur Folder
```
koperasi/
├── config/
│   ├── database.php     # Koneksi database
│   └── schema.sql       # Schema + seed data
├── includes/
│   ├── functions.php    # Helper functions
│   └── layout.php       # Template header/footer
├── modules/
│   ├── anggota/         # Manajemen anggota
│   ├── simpanan/        # Modul simpanan
│   ├── pinjaman/        # Modul pinjaman
│   ├── laporan/         # Neraca, SHU, Jurnal
│   └── import/          # Import Excel cerdas
├── api/
│   └── simpanan.php     # REST API endpoint
├── index.php            # Dashboard
├── login.php            # Halaman login
└── logout.php           # Logout
```

## Fitur Utama
- ✅ Dashboard dengan statistik real-time & chart
- ✅ Manajemen Anggota (CRUD + auto nomor anggota)
- ✅ Simpanan: Pokok, Wajib, Sukarela + transaksi otomatis
- ✅ Pinjaman: Workflow approval + perhitungan bunga flat/efektif/anuitas
- ✅ Jadwal angsuran otomatis + perhitungan denda keterlambatan
- ✅ Jurnal akuntansi otomatis dari setiap transaksi
- ✅ Laporan Neraca, SHU, distribusi SHU per anggota
- ✅ Import Excel cerdas dengan AI fuzzy mapping
- ✅ RBAC 4 role: admin, manager, kasir, anggota
- ✅ Audit log setiap transaksi

## Keamanan
- Password di-hash dengan bcrypt (cost 12)
- PDO prepared statements (anti SQL injection)
- Session-based authentication
- Role-based access control per halaman
- Audit log lengkap

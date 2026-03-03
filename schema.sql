-- =====================================================
-- SISTEM INFORMASI KOPERASI - Database Schema
-- MySQL / MariaDB Compatible
-- =====================================================

CREATE DATABASE IF NOT EXISTS kopi_opsi_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kopi_opsi_db;

-- ─── USERS (Autentikasi) ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    nama        VARCHAR(100) NOT NULL,
    email       VARCHAR(100) UNIQUE,
    role        ENUM('admin','manager','kasir','anggota') NOT NULL DEFAULT 'kasir',
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    last_login  DATETIME,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── ANGGOTA ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS anggota (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    no_anggota      VARCHAR(20)  NOT NULL UNIQUE,
    user_id         INT,
    nama_lengkap    VARCHAR(100) NOT NULL,
    nik             VARCHAR(16)  NOT NULL UNIQUE,
    tempat_lahir    VARCHAR(50),
    tanggal_lahir   DATE,
    jenis_kelamin   ENUM('L','P') NOT NULL,
    alamat          TEXT NOT NULL,
    no_telepon      VARCHAR(15)  NOT NULL,
    email           VARCHAR(100),
    pekerjaan       VARCHAR(100),
    tanggal_masuk   DATE NOT NULL,
    status          ENUM('aktif','nonaktif','suspend') NOT NULL DEFAULT 'aktif',
    foto            VARCHAR(255),
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── SIMPANAN (Rekening) ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS simpanan (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    anggota_id      INT NOT NULL,
    jenis_simpanan  ENUM('pokok','wajib','wajib_pinjam','wajib_khusus','sukarela') NOT NULL,
    saldo           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    dibuka_tanggal  DATE NOT NULL,
    ditutup_tanggal DATE,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (anggota_id) REFERENCES anggota(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_anggota_jenis (anggota_id, jenis_simpanan)
) ENGINE=InnoDB;

-- ─── TRANSAKSI SIMPANAN ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS transaksi_simpanan (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    simpanan_id     INT NOT NULL,
    anggota_id      INT NOT NULL,
    jenis           ENUM('setor','tarik') NOT NULL,
    jumlah          DECIMAL(15,2) NOT NULL,
    saldo_sebelum   DECIMAL(15,2) NOT NULL,
    saldo_sesudah   DECIMAL(15,2) NOT NULL,
    keterangan      TEXT,
    no_referensi    VARCHAR(50) UNIQUE,
    tanggal         DATE NOT NULL,
    dibuat_oleh     INT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (simpanan_id)  REFERENCES simpanan(id),
    FOREIGN KEY (anggota_id)   REFERENCES anggota(id),
    FOREIGN KEY (dibuat_oleh)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── PINJAMAN ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pinjaman (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    no_pinjaman     VARCHAR(20)  NOT NULL UNIQUE,
    anggota_id      INT NOT NULL,
    jumlah_pokok    DECIMAL(15,2) NOT NULL,
    suku_bunga      DECIMAL(5,4)  NOT NULL COMMENT 'Per bulan, e.g. 0.015 = 1.5%',
    jenis_bunga     ENUM('flat','efektif','anuitas') NOT NULL DEFAULT 'flat',
    tenor_bulan     INT NOT NULL,
    tujuan          TEXT NOT NULL,
    status          ENUM('pending','disetujui','ditolak','aktif','lunas','macet') NOT NULL DEFAULT 'pending',
    disetujui_oleh  INT,
    disetujui_at    DATETIME,
    dicairkan_at    DATE,
    jatuh_tempo     DATE,
    total_terbayar  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    catatan         TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (anggota_id)    REFERENCES anggota(id),
    FOREIGN KEY (disetujui_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── JADWAL ANGSURAN ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS jadwal_angsuran (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    pinjaman_id     INT NOT NULL,
    angsuran_ke     INT NOT NULL,
    jatuh_tempo     DATE NOT NULL,
    pokok           DECIMAL(15,2) NOT NULL,
    bunga           DECIMAL(15,2) NOT NULL,
    total_bayar     DECIMAL(15,2) NOT NULL,
    terbayar        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tanggal_bayar   DATE,
    denda           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status          ENUM('belum','bayar','sebagian','telat') NOT NULL DEFAULT 'belum',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pinjaman_id) REFERENCES pinjaman(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── PEMBAYARAN ANGSURAN ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS pembayaran_angsuran (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    jadwal_id       INT NOT NULL,
    pinjaman_id     INT NOT NULL,
    jumlah          DECIMAL(15,2) NOT NULL,
    tanggal_bayar   DATE NOT NULL,
    no_kwitansi     VARCHAR(50) UNIQUE,
    keterangan      TEXT,
    dibuat_oleh     INT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jadwal_id)     REFERENCES jadwal_angsuran(id),
    FOREIGN KEY (pinjaman_id)   REFERENCES pinjaman(id),
    FOREIGN KEY (dibuat_oleh)   REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── JURNAL AKUNTANSI ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS jurnal (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    no_jurnal   VARCHAR(30) NOT NULL UNIQUE,
    keterangan  TEXT NOT NULL,
    tanggal     DATE NOT NULL,
    is_posted   TINYINT(1) NOT NULL DEFAULT 1,
    ref_tabel   VARCHAR(50),
    ref_id      INT,
    dibuat_oleh INT,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS jurnal_detail (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    jurnal_id   INT NOT NULL,
    kode_akun   VARCHAR(10) NOT NULL,
    nama_akun   VARCHAR(100) NOT NULL,
    debit       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    kredit      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (jurnal_id) REFERENCES jurnal(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── AUDIT LOG ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT,
    aksi        VARCHAR(100) NOT NULL,
    tabel       VARCHAR(50),
    record_id   INT,
    data_lama   JSON,
    data_baru   JSON,
    ip_address  VARCHAR(45),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── SEED DATA ─────────────────────────────────────────────────
-- Untuk keamanan, user default akan dibuat saat pertama kali setup
-- Silakan tambahkan manual melalui aplikasi atau import data Anda sendiri

-- Contoh query untuk membuat user admin (jalankan secara manual jika diperlukan):
-- INSERT INTO users (username, password, nama, email, role) VALUES 
-- ('admin', '$2y$12$...hash_password...', 'Administrator', 'admin@koperasi.id', 'admin');
-- Catatan: Gunakan password_hash('password_anda', PASSWORD_BCRYPT) untuk membuat password hash

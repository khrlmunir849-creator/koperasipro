<?php
/**
 * Helper Functions & Auth - Sistem Informasi Koperasi
 */

session_start();

require_once __DIR__ . '/../config/database.php';

// ─── Konstanta ─────────────────────────────────────────────────
define('APP_NAME', 'KoperasiPro');
define('APP_VERSION', '1.0');
define('DENDA_PERSEN', 0.002); // 0.2% per hari keterlambatan

// ─── Auth Functions ────────────────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /koperasi/login.php');
        exit;
    }
}

function requireRole(array $roles): void {
    requireLogin();
    if (!in_array($_SESSION['user_role'], $roles)) {
        header('Location: /koperasi/403.php');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'    => $_SESSION['user_id'] ?? null,
        'nama'  => $_SESSION['user_nama'] ?? '',
        'role'  => $_SESSION['user_role'] ?? '',
        'username' => $_SESSION['user_username'] ?? '',
    ];
}

function hasRole(array $roles): bool {
    return in_array($_SESSION['user_role'] ?? '', $roles);
}

// ─── Format Functions ──────────────────────────────────────────
function rupiah(float $amount): string {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function tglIndo(string $date): string {
    if (!$date) return '-';
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    $d = date_create($date);
    return date_format($d, 'j') . ' ' . $bulan[(int)date_format($d, 'n')] . ' ' . date_format($d, 'Y');
}

// ─── Generator ID ──────────────────────────────────────────────
function generateNoAnggota(): string {
    $db   = Database::getInstance();
    $last = $db->fetch("SELECT no_anggota FROM anggota ORDER BY id DESC LIMIT 1");
    if (!$last) return 'KOP-' . date('Y') . '-001';
    preg_match('/(\d+)$/', $last['no_anggota'], $m);
    $next = str_pad((int)($m[1] ?? 0) + 1, 3, '0', STR_PAD_LEFT);
    return 'KOP-' . date('Y') . '-' . $next;
}

function generateNoPinjaman(): string {
    $db   = Database::getInstance();
    $last = $db->fetch("SELECT no_pinjaman FROM pinjaman ORDER BY id DESC LIMIT 1");
    if (!$last) return 'PIN-' . date('Y') . '-001';
    preg_match('/(\d+)$/', $last['no_pinjaman'], $m);
    $next = str_pad((int)($m[1] ?? 0) + 1, 4, '0', STR_PAD_LEFT);
    return 'PIN-' . date('Ym') . '-' . $next;
}

function generateNoReferensi(string $prefix = 'TRX'): string {
    return $prefix . '-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -4));
}

function generateNoJurnal(): string {
    $db   = Database::getInstance();
    $last = $db->fetch("SELECT no_jurnal FROM jurnal ORDER BY id DESC LIMIT 1");
    if (!$last) return 'JRN-' . date('Y') . '-0001';
    preg_match('/(\d+)$/', $last['no_jurnal'], $m);
    $next = str_pad((int)($m[1] ?? 0) + 1, 4, '0', STR_PAD_LEFT);
    return 'JRN-' . date('Y') . '-' . $next;
}

// ─── Perhitungan Pinjaman ──────────────────────────────────────
function hitungJadwalAngsuran(float $pokok, float $rate, int $tenor, string $jenis, string $tanggalCair): array {
    $jadwal = [];
    $sisaPokok = $pokok;

    for ($i = 1; $i <= $tenor; $i++) {
        $jatuhTempo = date('Y-m-d', strtotime("+$i months", strtotime($tanggalCair)));

        if ($jenis === 'flat') {
            $angsuranPokok = $pokok / $tenor;
            $bunga         = $pokok * $rate;
        } elseif ($jenis === 'efektif') {
            $angsuranPokok = $pokok / $tenor;
            $bunga         = $sisaPokok * $rate;
        } else { // anuitas
            $pmt           = $pokok * ($rate * pow(1 + $rate, $tenor)) / (pow(1 + $rate, $tenor) - 1);
            $bunga         = $sisaPokok * $rate;
            $angsuranPokok = $pmt - $bunga;
        }

        $sisaPokok -= $angsuranPokok;
        $total      = $angsuranPokok + $bunga;

        // Pembulatan untuk angsuran terakhir (hindari selisih rounding)
        if ($i === $tenor) {
            $totalSebelum = array_sum(array_column($jadwal, 'pokok'));
            $angsuranPokok = $pokok - $totalSebelum;
            $total = $angsuranPokok + $bunga;
        }

        $jadwal[] = [
            'angsuran_ke' => $i,
            'jatuh_tempo' => $jatuhTempo,
            'pokok'       => round($angsuranPokok, 2),
            'bunga'       => round($bunga, 2),
            'total_bayar' => round($total, 2),
        ];
    }
    return $jadwal;
}

// ─── Jurnal Otomatis ───────────────────────────────────────────
function buatJurnal(string $keterangan, string $tanggal, array $detail, string $refTabel = '', int $refId = 0): int {
    $db = Database::getInstance();
    
    // Check if there's already an active transaction
    $inTransaction = $db->inTransaction();
    
    if (!$inTransaction) {
        $db->beginTransaction();
    }
    
    try {
        $noJurnal = generateNoJurnal();
        $db->query(
            "INSERT INTO jurnal (no_jurnal, keterangan, tanggal, ref_tabel, ref_id, dibuat_oleh) VALUES (?,?,?,?,?,?)",
            [$noJurnal, $keterangan, $tanggal, $refTabel, $refId, $_SESSION['user_id'] ?? null]
        );
        $jurnalId = (int)$db->lastInsertId();
        foreach ($detail as $baris) {
            $db->query(
                "INSERT INTO jurnal_detail (jurnal_id, kode_akun, nama_akun, debit, kredit) VALUES (?,?,?,?,?)",
                [$jurnalId, $baris['kode'], $baris['nama'], $baris['debit'] ?? 0, $baris['kredit'] ?? 0]
            );
        }
        
        if (!$inTransaction) {
            $db->commit();
        }
        
        return $jurnalId;
    } catch (Exception $e) {
        if (!$inTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

// ─── Flash Messages ────────────────────────────────────────────
function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// ─── Audit Log ─────────────────────────────────────────────────
function auditLog(string $aksi, string $tabel = '', int $recordId = 0, array $dataLama = [], array $dataBaru = []): void {
    try {
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO audit_log (user_id, aksi, tabel, record_id, data_lama, data_baru, ip_address) VALUES (?,?,?,?,?,?,?)",
            [
                $_SESSION['user_id'] ?? null, $aksi, $tabel, $recordId,
                $dataLama ? json_encode($dataLama) : null,
                $dataBaru ? json_encode($dataBaru) : null,
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]
        );
    } catch (Exception $e) { /* silent */ }
}

// ─── Statistik Dashboard ───────────────────────────────────────
function getDashboardStats(): array {
    $db = Database::getInstance();
    return [
        'total_anggota'    => (int)$db->fetch("SELECT COUNT(*) c FROM anggota WHERE status='aktif'")['c'],
        'total_simpanan'   => (float)$db->fetch("SELECT COALESCE(SUM(saldo),0) c FROM simpanan WHERE is_active=1")['c'],
        'total_pinjaman'   => (float)$db->fetch("SELECT COALESCE(SUM(jumlah_pokok),0) c FROM pinjaman WHERE status='aktif'")['c'],
        'pinjaman_macet'   => (int)$db->fetch("SELECT COUNT(*) c FROM pinjaman WHERE status='macet'")['c'],
        'angsuran_hari_ini'=> (float)$db->fetch("SELECT COALESCE(SUM(total_bayar-terbayar),0) c FROM jadwal_angsuran WHERE jatuh_tempo=CURDATE() AND status='belum'")['c'],
        'angsuran_telat'   => (int)$db->fetch("SELECT COUNT(*) c FROM jadwal_angsuran WHERE jatuh_tempo<CURDATE() AND status IN ('belum','sebagian')")['c'],
    ];
}

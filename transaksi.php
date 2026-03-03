<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireRole(['admin','manager','kasir']);

$db      = Database::getInstance();
$jenis   = $_POST['jenis_trx'] ?? '';
$anggotaId = (int)($_POST['anggota_id'] ?? 0);
$simpananId = (int)($_POST['simpanan_id'] ?? 0);
$jumlahRaw = preg_replace('/[^\d]/','',$_POST['jumlah'] ?? '0');
$jumlah    = (float)$jumlahRaw;
$tanggal   = $_POST['tanggal'] ?? date('Y-m-d');
$ket       = trim($_POST['keterangan'] ?? '');

try {
    if (!in_array($jenis,['setor','tarik'])) throw new Exception("Jenis transaksi tidak valid");
    if ($jumlah <= 0)                        throw new Exception("Jumlah harus lebih dari 0");
    if (!$simpananId)                        throw new Exception("Pilih rekening simpanan");

    $db->beginTransaction();

    // Lock row simpanan
    $simpanan = $db->fetch("SELECT * FROM simpanan WHERE id=? AND anggota_id=? AND is_active=1 FOR UPDATE", [$simpananId, $anggotaId]);
    if (!$simpanan) throw new Exception("Rekening simpanan tidak ditemukan atau tidak aktif");

    $anggota = $db->fetch("SELECT * FROM anggota WHERE id=?", [$anggotaId]);

    if ($jenis === 'tarik') {
        if ($simpanan['saldo'] - $jumlah < 0)           throw new Exception("Saldo tidak mencukupi. Saldo: " . rupiah($simpanan['saldo']));
    }

    $saldoSebelum = (float)$simpanan['saldo'];
    $saldoSesudah = $jenis === 'setor' ? $saldoSebelum + $jumlah : $saldoSebelum - $jumlah;

    // Update saldo
    $db->query("UPDATE simpanan SET saldo=? WHERE id=?", [$saldoSesudah, $simpananId]);

    // Catat transaksi
    $noRef = generateNoReferensi(strtoupper(substr($jenis,0,3)));
    $db->query(
        "INSERT INTO transaksi_simpanan (simpanan_id, anggota_id, jenis, jumlah, saldo_sebelum, saldo_sesudah, keterangan, no_referensi, tanggal, dibuat_oleh)
         VALUES (?,?,?,?,?,?,?,?,?,?)",
        [$simpananId, $anggotaId, $jenis, $jumlah, $saldoSebelum, $saldoSesudah, $ket ?: null, $noRef, $tanggal, $_SESSION['user_id']]
    );

    // Jurnal otomatis
    $namaJenis = ucfirst($simpanan['jenis_simpanan']);
    $kodeAkun  = [
        'pokok'=>'2-1100',
        'wajib'=>'2-1200',
        'wajib_pinjam'=>'2-1210',
        'wajib_khusus'=>'2-1220',
        'sukarela'=>'2-1300'
    ][$simpanan['jenis_simpanan']] ?? '2-1300';
    $namaAkunSimpanan = "Simpanan $namaJenis";

    if ($jenis === 'setor') {
        buatJurnal(
            "Setoran Simpanan $namaJenis - {$anggota['nama_lengkap']} ({$anggota['no_anggota']})",
            $tanggal,
            [
                ['kode'=>'1-1100','nama'=>'Kas','debit'=>$jumlah,'kredit'=>0],
                ['kode'=>$kodeAkun,'nama'=>$namaAkunSimpanan,'debit'=>0,'kredit'=>$jumlah],
            ],
            'simpanan', $simpananId
        );
    } else {
        buatJurnal(
            "Penarikan Simpanan $namaJenis - {$anggota['nama_lengkap']} ({$anggota['no_anggota']})",
            $tanggal,
            [
                ['kode'=>$kodeAkun,'nama'=>$namaAkunSimpanan,'debit'=>$jumlah,'kredit'=>0],
                ['kode'=>'1-1100','nama'=>'Kas','debit'=>0,'kredit'=>$jumlah],
            ],
            'simpanan', $simpananId
        );
    }

    $db->commit();
    auditLog("TRANSAKSI_SIMPANAN", 'simpanan', $simpananId, ['saldo'=>$saldoSebelum], ['jenis'=>$jenis,'jumlah'=>$jumlah,'saldo'=>$saldoSesudah]);
    setFlash('success', ucfirst($jenis) . " simpanan {$namaJenis} sebesar " . rupiah($jumlah) . " berhasil. Ref: $noRef");

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    setFlash('danger', "Transaksi gagal: " . $e->getMessage());
}

header('Location: list.php');
exit;

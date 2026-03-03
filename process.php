<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireRole(['admin','manager']);

header('Content-Type: application/json');

$db = Database::getInstance();

function parseDatePHP($value) {
    if (empty($value)) return date('Y-m-d');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }
    $parsed = date_create($value);
    if ($parsed && !is_bool($parsed)) {
        return date_format($parsed, 'Y-m-d');
    }
    if (is_numeric($value)) {
        $excelEpoch = new DateTime('1899-12-30');
        $days = intval($value);
        $excelEpoch->modify("+{$days} days");
        return $excelEpoch->format('Y-m-d');
    }
    return date('Y-m-d');
}

function parseNumberPHP($value) {
    if (empty($value)) return 0;
    if (is_numeric($value)) return floatval($value);
    $str = str_replace(['Rp ', 'rp ', 'Rp', 'rupiah', ' '], '', strtolower($value));
    $str = str_replace('.', '', $str);
    $str = str_replace(',', '.', $str);
    $num = floatval($str);
    return is_nan($num) ? 0 : $num;
}

try {
    $rawData = json_decode($_POST['data'] ?? '[]', true);
    $mapping = json_decode($_POST['mapping'] ?? '{}', true);
    $type    = $_POST['type'] ?? 'anggota';

    // Debug: Log first row and mapping for troubleshooting
    error_log("Import Debug - Type: " . $type);
    error_log("Import Debug - Mapping: " . json_encode($mapping));
    if (!empty($rawData)) {
        error_log("Import Debug - First row keys: " . json_encode(array_keys($rawData[0])));
    }

    if (empty($rawData)) throw new Exception("Tidak ada data yang dikirim");
    if (empty($mapping)) throw new Exception("Mapping kolom tidak valid");

    $successCount = 0;
    $errorCount   = 0;
    $logs         = [];

    $db->beginTransaction();

    foreach ($rawData as $rowNum => $row) {
        try {
            $rowNumber = isset($row['_rowNum']) ? $row['_rowNum'] : ($rowNum + 2);
            
            $hasData = false;
            foreach ($row as $key => $value) {
                if (!empty($value) && $key !== '_rowNum') {
                    $hasData = true;
                    break;
                }
            }
            if (!$hasData) {
                $logs[] = "✗ Baris $rowNumber: Dilewati (baris kosong)";
                $errorCount++;
                continue;
            }

            $val = function(string $field, $default = '') use ($row, $mapping): string {
                $col = $mapping[$field] ?? '';
                if (empty($col)) return $default;
                // Try exact match first
                if (isset($row[$col]) && $row[$col] !== '') {
                    return trim((string)$row[$col]);
                }
                // Try case-insensitive match
                $colLower = strtolower($col);
                foreach ($row as $key => $value) {
                    if (strtolower($key) === $colLower && !empty($value)) {
                        return trim((string)$value);
                    }
                }
                return $default;
            };
            
            $valNum = function(string $field, $default = 0) use ($row, $mapping): float {
                $col = $mapping[$field] ?? '';
                if (empty($col)) return $default;
                // Try exact match first
                if (isset($row[$col]) && $row[$col] !== '') {
                    return parseNumberPHP($row[$col]);
                }
                // Try case-insensitive match
                $colLower = strtolower($col);
                foreach ($row as $key => $value) {
                    if (strtolower($key) === $colLower && !empty($value)) {
                        return parseNumberPHP($value);
                    }
                }
                return $default;
            };
            
            $valDate = function(string $field) use ($row, $mapping): string {
                $col = $mapping[$field] ?? '';
                if (empty($col)) return date('Y-m-d');
                // Try exact match first
                if (isset($row[$col]) && $row[$col] !== '') {
                    return parseDatePHP($row[$col]);
                }
                // Try case-insensitive match
                $colLower = strtolower($col);
                foreach ($row as $key => $value) {
                    if (strtolower($key) === $colLower && !empty($value)) {
                        return parseDatePHP($value);
                    }
                }
                return date('Y-m-d');
            };

            if ($type === 'anggota') {
                $nama  = $val('nama_lengkap');
                $nik   = preg_replace('/\D/', '', $val('nik'));
                $telp  = preg_replace('/[^\d+]/', '', $val('no_telepon'));
                $alamat= $val('alamat') ?: 'Belum diisi';
                $tglMasuk = $valDate('tanggal_masuk');

                if (!$nama) {
                    throw new Exception("Nama tidak valid - pastikan kolom nama sudah di-map dengan benar");
                }
                
                // NIK sekarang opsional - generate random jika tidak ada
                $nikLength = strlen($nik);
                if (!$nik) {
                    // Generate random NIK 16 digit jika tidak ada
                    $nik = '0' . rand(1000000000000, 9999999999999);
                    $logs[] = "⚠ Baris $rowNumber: NIK tidak ditemukan, digenerate otomatis: $nik";
                } elseif ($nikLength !== 16) {
                    // Jika NIK tidak 16 digit, generate baru
                    $nik = '0' . rand(1000000000000, 9999999999999);
                    $logs[] = "⚠ Baris $rowNumber: NIK tidak valid ($nikLength digit), digenerate otomatis: $nik";
                }

                $cek = $db->fetch("SELECT id, no_anggota FROM anggota WHERE nik=?", [$nik]);
                if ($cek) {
                    $logs[] = "✗ Baris $rowNumber: NIK $nik sudah terdaftar dengan no anggota " . $cek['no_anggota'] . " (skip)";
                    $errorCount++;
                    continue;
                }

                $noAnggota = generateNoAnggota();
                $cekNo = $db->fetch("SELECT id FROM anggota WHERE no_anggota=?", [$noAnggota]);
                while ($cekNo) {
                    $noAnggota = generateNoAnggota();
                    $cekNo = $db->fetch("SELECT id FROM anggota WHERE no_anggota=?", [$noAnggota]);
                }

                $genderRaw = strtolower($val('jenis_kelamin', ''));
                $gender = in_array($genderRaw, ['l','laki','laki-laki','m','male','pria']) ? 'L' : 'P';

                $tglLahir = null;
                $tlRaw    = $val('tanggal_lahir', '');
                if ($tlRaw) {
                    $tglLahir = parseDatePHP($tlRaw);
                }
                $tglMasukFinal = parseDatePHP($tglMasuk);

                $db->query(
                    "INSERT INTO anggota (no_anggota, nama_lengkap, nik, jenis_kelamin, tanggal_lahir, tempat_lahir, alamat, no_telepon, email, pekerjaan, tanggal_masuk)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                    [
                        $noAnggota, $nama, $nik, $gender,
                        $tglLahir, $val('tempat_lahir', '') ?: null,
                        $alamat, $telp ?: '0',
                        $val('email', '') ?: null,
                        $val('pekerjaan', '') ?: null,
                        $tglMasukFinal,
                    ]
                );
                $anggotaId = (int)$db->lastInsertId();

                foreach (['pokok','wajib','wajib_pinjam','wajib_khusus','sukarela'] as $jenis) {
                    $db->query(
                        "INSERT INTO simpanan (anggota_id, jenis_simpanan, saldo, dibuka_tanggal) VALUES (?,?,0,?)",
                        [$anggotaId, $jenis, $tglMasukFinal]
                    );
                }
                
                $logs[] = "✓ Baris $rowNumber: [$nama] berhasil diimport (No. Anggota: $noAnggota)";
                $successCount++;

            } elseif ($type === 'simpanan') {
                $noAnggota = trim($val('no_anggota'));
                $namaCari  = trim($val('identifier', ''));
                $nikCari   = trim($val('nik', ''));
                $telpCari  = trim($val('no_telepon', ''));
                $jumlah    = $valNum('jumlah', 0);
                $jenisRaw  = strtolower($val('jenis_simpanan', 'sukarela'));
                $keterangan = $val('keterangan', 'Import Excel');
                $tanggal   = $valDate('tanggal');
                
                $jenis = 'sukarela';
                if (str_contains($jenisRaw,'pokok') || str_contains($jenisRaw,'sp')) {
                    $jenis = 'pokok';
                } elseif (str_contains($jenisRaw,'wajib pinjam') || str_contains($jenisRaw,'swp')) {
                    $jenis = 'wajib_pinjam';
                } elseif (str_contains($jenisRaw,'wajib khusus') || str_contains($jenisRaw,'swk')) {
                    $jenis = 'wajib_khusus';
                } elseif (str_contains($jenisRaw,'wajib') || str_contains($jenisRaw,'sw')) {
                    $jenis = 'wajib';
                }

                // Cari anggota berdasarkan no_anggota, nama, nik, atau telepon
                $anggota = null;
                $searchLog = [];
                
                // Try 1: Search by member number (no_anggota)
                if (!$anggota && $noAnggota) {
                    $anggota = $db->fetch("SELECT id, nama_lengkap FROM anggota WHERE no_anggota=?", [$noAnggota]);
                    if ($anggota) $searchLog[] = "ditemukan via no_anggota: $noAnggota";
                }
                
                // Try 2: Search by name (identifier)
                if (!$anggota && $namaCari) {
                    $anggota = $db->fetch("SELECT id, nama_lengkap FROM anggota WHERE nama_lengkap LIKE ?", ['%' . $namaCari . '%']);
                    if ($anggota) $searchLog[] = "ditemukan via nama: $namaCari";
                }
                
                // Try 3: Search by NIK
                if (!$anggota && $nikCari) {
                    $nikClean = preg_replace('/\D/', '', $nikCari);
                    if (strlen($nikClean) >= 6) { // At least 6 digits
                        $anggota = $db->fetch("SELECT id, nama_lengkap FROM anggota WHERE nik LIKE ?", ['%' . $nikClean . '%']);
                        if ($anggota) $searchLog[] = "ditemukan via NIK: $nikCari";
                    }
                }
                
                // Try 4: Search by phone number
                if (!$anggota && $telpCari) {
                    $telpClean = preg_replace('/[^\d+]/', '', $telpCari);
                    if (strlen($telpClean) >= 6) { // At least 6 digits
                        $anggota = $db->fetch("SELECT id, nama_lengkap FROM anggota WHERE no_telepon LIKE ?", ['%' . $telpClean . '%']);
                        if ($anggota) $searchLog[] = "ditemukan via telepon: $telpCari";
                    }
                }
                
                // Try 5: Generic search - try to find by any column that might contain member info
                if (!$anggota) {
                    // Check all row values and try to match against anggota table
                    foreach ($row as $key => $value) {
                        if (empty($value) || $key === '_rowNum' || $key === '_mapping' || $key === '_type') continue;
                        $value = trim((string)$value);
                        if (strlen($value) < 3) continue;
                        
                        // Skip if this looks like a number (probably amount/date)
                        if (is_numeric($value)) continue;
                        
                        // Try as name
                        $test = $db->fetch("SELECT id, nama_lengkap FROM anggota WHERE nama_lengkap LIKE ?", ['%' . $value . '%']);
                        if ($test) {
                            $anggota = $test;
                            $searchLog[] = "ditemukan via kolom '$key': $value";
                            break;
                        }
                    }
                }
                
                // Jika anggota tidak ditemukan, buat anggota baru secara otomatis
                if (!$anggota) {
                    $logs[] = "⚠ Baris $rowNumber: Anggota tidak ditemukan, membuat data anggota baru...";
                    
                    // Generate data untuk anggota baru
                    $namaAnggotaBaru = $namaCari ?: ($noAnggota ?: 'Anggota Import ' . date('YmdHis'));
                    $nikBaru = !empty($nikCari) ? preg_replace('/\D/', '', $nikCari) : '0' . rand(1000000000000, 9999999999999);
                    $telpBaru = !empty($telpCari) ? preg_replace('/[^\d+]/', '', $telpCari) : '0' . rand(8000000000, 9999999999);
                    
                    $noAnggotaBaru = generateNoAnggota();
                    $cekNo = $db->fetch("SELECT id FROM anggota WHERE no_anggota=?", [$noAnggotaBaru]);
                    while ($cekNo) {
                        $noAnggotaBaru = generateNoAnggota();
                        $cekNo = $db->fetch("SELECT id FROM anggota WHERE no_anggota=?", [$noAnggotaBaru]);
                    }
                    
                    $db->query(
                        "INSERT INTO anggota (no_anggota, nama_lengkap, nik, jenis_kelamin, alamat, no_telepon, tanggal_masuk, status)
                         VALUES (?,?,?,?,?,?,?,?)",
                        [
                            $noAnggotaBaru, $namaAnggotaBaru, $nikBaru, 'L',
                            'Alamat import otomatis', $telpBaru, $tanggal, 'aktif'
                        ]
                    );
                    $anggotaIdBaru = (int)$db->lastInsertId();
                    
                    // Buat rekening simpanan untuk anggota baru
                    foreach (['pokok','wajib','wajib_pinjam','wajib_khusus','sukarela'] as $jenisSimpanan) {
                        $db->query(
                            "INSERT INTO simpanan (anggota_id, jenis_simpanan, saldo, dibuka_tanggal) VALUES (?,?,0,?)",
                            [$anggotaIdBaru, $jenisSimpanan, $tanggal]
                        );
                    }
                    
                    $anggota = [
                        'id' => $anggotaIdBaru,
                        'nama_lengkap' => $namaAnggotaBaru
                    ];
                    
                    $logs[] = "✓ Baris $rowNumber: Anggota baru dibuat - [$namaAnggotaBaru] (No. Anggota: $noAnggotaBaru)";
                }
                
                // Log search info for debugging
                if (!empty($searchLog)) {
                    $logs[] = "⚠ Baris $rowNumber: " . implode(', ', $searchLog);
                }

                if ($jumlah <= 0) {
                    throw new Exception("Jumlah harus lebih dari 0");
                }
                
                $anggotaId = $anggota['id'];
                $namaAnggota = $anggota['nama_lengkap'];

                $simpanan = $db->fetch("SELECT id, saldo FROM simpanan WHERE anggota_id=? AND jenis_simpanan=?", [$anggotaId, $jenis]);
                if (!$simpanan) {
                    $db->query(
                        "INSERT INTO simpanan (anggota_id, jenis_simpanan, saldo, dibuka_tanggal) VALUES (?,?,0,?)",
                        [$anggotaId, $jenis, $tanggal]
                    );
                    $simpanan = [
                        'id' => (int)$db->lastInsertId(),
                        'saldo' => 0
                    ];
                }

                $saldoLama = floatval($simpanan['saldo']);
                $saldoBaru = $saldoLama + $jumlah;
                $db->query("UPDATE simpanan SET saldo=? WHERE id=?", [$saldoBaru, $simpanan['id']]);
                
                $db->query(
                    "INSERT INTO transaksi_simpanan (simpanan_id, anggota_id, jenis, jumlah, saldo_sebelum, saldo_sesudah, keterangan, no_referensi, tanggal, dibuat_oleh)
                     VALUES (?,?,'setor',?,?,?,?,?,?,?)",
                    [$simpanan['id'], $anggotaId, $jumlah, $saldoLama, $saldoBaru, $keterangan, generateNoReferensi('IMP'), $tanggal, $_SESSION['user_id']]
                );

                $logs[] = "✓ Baris $rowNumber: [$namaAnggota] - Simpanan $jenis Rp " . number_format($jumlah, 0, ',', '.') . " berhasil";
                $successCount++;

            } elseif ($type === 'pinjaman') {
                $noAnggota = trim($val('no_anggota'));
                $namaCari  = trim($val('identifier', ''));
                $nikCari   = trim($val('nik', ''));
                $telpCari  = trim($val('no_telepon', ''));
                $jumlah    = $valNum('jumlah_pokok', 0);
                $bunga     = $valNum('suku_bunga', 1.5);
                $tenor     = (int)$valNum('tenor_bulan', 12);
                
                $jenisBungaRaw = strtolower($val('jenis_bunga', 'flat'));
                $jenisBunga = 'flat';
                if (str_contains($jenisBungaRaw,'efektif')) $jenisBunga = 'efektif';
                elseif (str_contains($jenisBungaRaw,'anuitas')) $jenisBunga = 'anuitas';

                // Cari anggota berdasarkan no_anggota, nama, nik, atau telepon (fleksibel)
                $anggota = null;
                $searchLog = [];
                
                // Try 1: Search by member number (no_anggota)
                if (!$anggota && $noAnggota) {
                    $anggota = $db->fetch("SELECT id, nama_lengkap FROM anggota WHERE no_anggota=?", [$noAnggota]);
                    if ($anggota) $searchLog[] = "ditemukan via no_anggota: $noAnggota";
                }
                
                // Try 2: Search by name (identifier)
                if (!$anggota && $namaCari) {
                    $anggota = $db->fetch("SELECT id, nama_lengkap FROM anggota WHERE nama_lengkap LIKE ?", ['%' . $namaCari . '%']);
                    if ($anggota) $searchLog[] = "ditemukan via nama: $namaCari";
                }
                
                // Try 3: Search by NIK
                if (!$anggota && $nikCari) {
                    $nikClean = preg_replace('/\D/', '', $nikCari);
                    if (strlen($nikClean) >= 6) { // At least 6 digits
                        $anggota = $db->fetch("SELECT id, nama_lengkap FROM anggota WHERE nik LIKE ?", ['%' . $nikClean . '%']);
                        if ($anggota) $searchLog[] = "ditemukan via NIK: $nikCari";
                    }
                }
                
                // Try 4: Search by phone number
                if (!$anggota && $telpCari) {
                    $telpClean = preg_replace('/[^\d+]/', '', $telpCari);
                    if (strlen($telpClean) >= 6) { // At least 6 digits
                        $anggota = $db->fetch("SELECT id, nama_lengkap FROM anggota WHERE no_telepon LIKE ?", ['%' . $telpClean . '%']);
                        if ($anggota) $searchLog[] = "ditemukan via telepon: $telpCari";
                    }
                }
                
                // Try 5: Generic search - try to find by any column that might contain member info
                if (!$anggota) {
                    // Check all row values and try to match against anggota table
                    foreach ($row as $key => $value) {
                        if (empty($value) || $key === '_rowNum' || $key === '_mapping' || $key === '_type') continue;
                        $value = trim((string)$value);
                        if (strlen($value) < 3) continue;
                        
                        // Skip if this looks like a number (probably amount/date)
                        if (is_numeric($value)) continue;
                        
                        // Try as name
                        $test = $db->fetch("SELECT id, nama_lengkap FROM anggota WHERE nama_lengkap LIKE ?", ['%' . $value . '%']);
                        if ($test) {
                            $anggota = $test;
                            $searchLog[] = "ditemukan via kolom '$key': $value";
                            break;
                        }
                    }
                }
                
                // Jika anggota tidak ditemukan, buat anggota baru secara otomatis
                if (!$anggota) {
                    $logs[] = "⚠ Baris $rowNumber: Anggota tidak ditemukan, membuat data anggota baru...";
                    
                    // Generate data untuk anggota baru
                    $namaAnggotaBaru = $namaCari ?: ($noAnggota ?: 'Anggota Import ' . date('YmdHis'));
                    $nikBaru = !empty($nikCari) ? preg_replace('/\D/', '', $nikCari) : '0' . rand(1000000000000, 9999999999999);
                    $telpBaru = !empty($telpCari) ? preg_replace('/[^\d+]/', '', $telpCari) : '0' . rand(8000000000, 9999999999);
                    
                    $noAnggotaBaru = generateNoAnggota();
                    $cekNo = $db->fetch("SELECT id FROM anggota WHERE no_anggota=?", [$noAnggotaBaru]);
                    while ($cekNo) {
                        $noAnggotaBaru = generateNoAnggota();
                        $cekNo = $db->fetch("SELECT id FROM anggota WHERE no_anggota=?", [$noAnggotaBaru]);
                    }
                    
                    $tanggalPinjam = $valDate('tanggal');
                    
                    $db->query(
                        "INSERT INTO anggota (no_anggota, nama_lengkap, nik, jenis_kelamin, alamat, no_telepon, tanggal_masuk, status)
                         VALUES (?,?,?,?,?,?,?,?)",
                        [
                            $noAnggotaBaru, $namaAnggotaBaru, $nikBaru, 'L',
                            'Alamat import otomatis', $telpBaru, $tanggalPinjam, 'aktif'
                        ]
                    );
                    $anggotaIdBaru = (int)$db->lastInsertId();
                    
                    // Buat rekening simpanan untuk anggota baru
                    foreach (['pokok','wajib','wajib_pinjam','wajib_khusus','sukarela'] as $jenisSimpanan) {
                        $db->query(
                            "INSERT INTO simpanan (anggota_id, jenis_simpanan, saldo, dibuka_tanggal) VALUES (?,?,0,?)",
                            [$anggotaIdBaru, $jenisSimpanan, $tanggalPinjam]
                        );
                    }
                    
                    $anggota = [
                        'id' => $anggotaIdBaru,
                        'nama_lengkap' => $namaAnggotaBaru
                    ];
                    
                    $logs[] = "✓ Baris $rowNumber: Anggota baru dibuat - [$namaAnggotaBaru] (No. Anggota: $noAnggotaBaru)";
                }
                
                // Log search info for debugging
                if (!empty($searchLog)) {
                    $logs[] = "⚠ Baris $rowNumber: " . implode(', ', $searchLog);
                }

                // Debug: Log mapping info for pinjaman
                $mappingInfo = "Mapping: no_anggota=" . ($mapping['no_anggota'] ?? 'TIDAK ADA') . 
                              ", identifier=" . ($mapping['identifier'] ?? 'TIDAK ADA') .
                              ", nik=" . ($mapping['nik'] ?? 'TIDAK ADA') .
                              ", no_telepon=" . ($mapping['no_telepon'] ?? 'TIDAK ADA') .
                              ", jumlah_pokok=" . ($mapping['jumlah_pokok'] ?? 'TIDAK ADA') .
                              ", tenor_bulan=" . ($mapping['tenor_bulan'] ?? 'TIDAK ADA');
                $logs[] = "⚠ Baris $rowNumber: DEBUG - $mappingInfo";
                
                // Show raw value for debugging
                $rawJumlah = $val('jumlah_pokok', 'TIDAK ADA');
                $logs[] = "⚠ Baris $rowNumber: DEBUG - Nilai jumlah_pokok mentah: '$rawJumlah'";

                if ($jumlah <= 0) {
                    throw new Exception("Jumlah pinjaman harus lebih dari 0 (nilai saat ini: $jumlah - cek mapping kolom jumlah_pokok)");
                }
                $anggotaId = $anggota['id'];
                $namaAnggota = $anggota['nama_lengkap'];

                $noPinjaman = generateNoPinjaman();
                $tglPinjam = $valDate('tanggal');
                $tglJatuhTempo = date('Y-m-d', strtotime("+{$tenor} months", strtotime($tglPinjam)));

                $db->query(
                    "INSERT INTO pinjaman (no_pinjaman, anggota_id, jumlah_pokok, suku_bunga, jenis_bunga, tenor_bulan, tujuan, status, dicairkan_at, jatuh_tempo, created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,NOW())",
                    [
                        $noPinjaman, $anggotaId, $jumlah, $bunga/100, $jenisBunga, $tenor,
                        $val('tujuan', 'Import Excel'), 'aktif', $tglPinjam, $tglJatuhTempo
                    ]
                );
                $pinjamanId = (int)$db->lastInsertId();

                $bungaperBulan = $jumlah * ($bunga/100);
                $totalBunga = $bungaperBulan * $tenor;
                $totalPinjam = $jumlah + $totalBunga;
                $angsuranPerBulan = $totalPinjam / $tenor;

                for ($i = 1; $i <= $tenor; $i++) {
                    $tglJadwal = date('Y-m-d', strtotime("+{$i} months", strtotime($tglPinjam)));
                    $db->query(
                        "INSERT INTO jadwal_angsuran (pinjaman_id, angsuran_ke, jatuh_tempo, pokok, bunga, total_bayar, status)
                         VALUES (?,?,?,?,?,?,'belum')",
                        [
                            $pinjamanId, $i, $tglJadwal,
                            $angsuranPerBulan - $bungaperBulan, $bungaperBulan, $angsuranPerBulan
                        ]
                    );
                }

                $logs[] = "✓ Baris $rowNumber: [$namaAnggota] - Pinjaman Rp " . number_format($jumlah, 0, ',', '.') . " ($tenor bulan, $bunga%) berhasil";
                $successCount++;
            }

        } catch (Exception $e) {
            $logs[] = "✗ Baris " . (isset($row['_rowNum']) ? $row['_rowNum'] : ($rowNum + 2)) . ": Gagal - " . $e->getMessage();
            $errorCount++;
        }
    }

    $db->commit();
    auditLog("IMPORT_EXCEL", $type, 0, [], ['success'=>$successCount, 'error'=>$errorCount]);

    echo json_encode([
        'success'       => true,
        'message'       => "Import selesai dari " . count($rawData) . " baris data.",
        'success_count' => $successCount,
        'error_count'   => $errorCount,
        'logs'          => $logs,
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->getConnection()->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

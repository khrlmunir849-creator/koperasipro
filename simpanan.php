<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
header('Content-Type: application/json');

$db        = Database::getInstance();
$anggotaId = (int)($_GET['anggota_id'] ?? 0);
$jenis     = $_GET['jenis'] ?? '';

if (!$anggotaId) { echo json_encode([]); exit; }

$params = [$anggotaId];
$sql    = "SELECT id, jenis_simpanan, saldo FROM simpanan WHERE anggota_id=? AND is_active=1";
if ($jenis) { $sql .= " AND jenis_simpanan=?"; $params[] = $jenis; }
$sql .= " ORDER BY jenis_simpanan";

echo json_encode($db->fetchAll($sql, $params));

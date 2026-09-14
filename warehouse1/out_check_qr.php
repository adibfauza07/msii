<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once __DIR__ . "/../config/global.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('error');
}

$qrcodeid = isset($_POST['qrcodeid']) ? trim($_POST['qrcodeid']) : '';
if ($conn === false || $qrcodeid === '') {
    exit('true');
}

$sql = "SELECT TOP 1 QRCODE_ID FROM INV_TRAN WHERE QRCODE_ID = ? AND SCAN_TYPE = 1";
$stmt = sqlsrv_query($conn, $sql, array($qrcodeid));

if ($stmt !== false && sqlsrv_has_rows($stmt)) {
    echo 'false'; // Sudah ada (duplikat)
} else {
    echo 'true';  // Belum ada (aman)
}
if ($stmt !== false) sqlsrv_free_stmt($stmt);
?>
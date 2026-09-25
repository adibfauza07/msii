<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . "/../config/global.php"; // Pastikan koneksi ($conn) dimuat

$qrcodeid = isset($_POST['qrcodeid']) ? trim($_POST['qrcodeid']) : '';

if (empty($qrcodeid)) {
    echo 'not_found';
    exit;
}

// Gunakan parameter query T-SQL untuk keamanan (mencegah SQL Injection)
$sql = "SELECT TOP 1 QRCODE_ID FROM INV_TRAN WHERE QRCODE_ID = ?";
$params = array($qrcodeid);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    // Jika ada error pada eksekusi query
    echo 'error';
    exit;
}

// Cek apakah ada data yang dikembalikan
if (sqlsrv_has_rows($stmt)) {
    echo 'found';
} else {
    echo 'not_found';
}

sqlsrv_free_stmt($stmt);
?>
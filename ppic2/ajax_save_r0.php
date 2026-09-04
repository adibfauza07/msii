<?php
// Wajib panggil session_start paling awal untuk AJAX
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Mencegah redirect ke login.php jika koneksi/sesi putus
define('LOGIN_PAGE', true); 

error_reporting(0);
ob_start();

$configPath = __DIR__ . "/../config/global.php"; 
if (file_exists($configPath)) {
    require_once $configPath;
}

ob_clean();
header('Content-Type: application/json; charset=utf-8');

// Validasi Koneksi
if (!isset($conn) || $conn === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Koneksi database terputus.'));
    exit;
}

// 1. Ambil Input POST dari jQuery AJAX
$id_no = isset($_POST['id_no']) ? trim($_POST['id_no']) : '';
$day   = isset($_POST['day']) ? (int)$_POST['day'] : 0;
$value = isset($_POST['value']) ? trim($_POST['value']) : '';

// Validasi Parameter (Hari harus 1-31)
if ($id_no === '' || $day < 1 || $day > 31) {
    echo json_encode(array('status' => 'error', 'message' => 'Parameter input tidak valid.'));
    exit;
}

// Format nilai: kosongkan jadi NULL di database, atau konversi ke Float
$update_val = ($value === '') ? null : (float)$value;

// 2. Susun Nama Kolom Aman (Casting Integer mencegah eksploitasi SQL)
$column_name = 'D' . $day; 

// 3. Eksekusi UPDATE ke RPT_PPIC_DTL_PHP
$sql = "UPDATE RPT_PPIC_DTL_PHP 
        SET {$column_name} = ? 
        WHERE ID_NO = ? AND DESC_PROD = 'Prod Plan R0'";

$params = array($update_val, $id_no);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $errors = sqlsrv_errors();
    $err_msg = isset($errors[0]['message']) ? $errors[0]['message'] : 'Gagal menyimpan ke database.';
    echo json_encode(array('status' => 'error', 'message' => $err_msg));
    exit;
}

// 4. Kalkulasi Ulang Grand Total (G_TOTAL) langsung di level Database
$sql_calc = "UPDATE RPT_PPIC_DTL_PHP 
             SET G_TOTAL = (
                 ISNULL(D1,0) + ISNULL(D2,0) + ISNULL(D3,0) + ISNULL(D4,0) + ISNULL(D5,0) + 
                 ISNULL(D6,0) + ISNULL(D7,0) + ISNULL(D8,0) + ISNULL(D9,0) + ISNULL(D10,0) + 
                 ISNULL(D11,0) + ISNULL(D12,0) + ISNULL(D13,0) + ISNULL(D14,0) + ISNULL(D15,0) + 
                 ISNULL(D16,0) + ISNULL(D17,0) + ISNULL(D18,0) + ISNULL(D19,0) + ISNULL(D20,0) + 
                 ISNULL(D21,0) + ISNULL(D22,0) + ISNULL(D23,0) + ISNULL(D24,0) + ISNULL(D25,0) + 
                 ISNULL(D26,0) + ISNULL(D27,0) + ISNULL(D28,0) + ISNULL(D29,0) + ISNULL(D30,0) + ISNULL(D31,0)
             )
             WHERE ID_NO = ? AND DESC_PROD = 'Prod Plan R0'";

sqlsrv_query($conn, $sql_calc, array($id_no));

echo json_encode(array('status' => 'success'));
exit;
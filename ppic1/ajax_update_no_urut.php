<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Sesuaikan path ke global config jika diperlukan
$configPath = __DIR__ . "/../config/global.php"; 
if (file_exists($configPath)) { require_once $configPath; }

header('Content-Type: application/json; charset=utf-8');

if (!isset($conn) || $conn === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Koneksi database gagal.'));
    exit;
}

$id_no = isset($_POST['id_no']) ? trim($_POST['id_no']) : '';
$no_urut = isset($_POST['no_urut']) ? trim($_POST['no_urut']) : '';

if (empty($id_no)) {
    echo json_encode(array('status' => 'error', 'message' => 'ID_NO tidak valid.'));
    exit;
}

// Lakukan proses update ke Database
$sql = "UPDATE RPT_PPIC SET no_urut = ? WHERE ID_NO = ?";
$stmt = sqlsrv_query($conn, $sql, array($no_urut, $id_no));

if ($stmt === false) {
    $errors = sqlsrv_errors();
    echo json_encode(array('status' => 'error', 'message' => $errors[0]['message']));
    exit;
}

sqlsrv_free_stmt($stmt);

// Berikan respon berhasil
echo json_encode(array('status' => 'success', 'message' => 'Nomor urut berhasil diupdate.'));
exit;
?>
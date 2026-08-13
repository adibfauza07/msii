<?php
/**
 * Menyimpan data riwayat cetak label ke Database
 */
require_once __DIR__ . "/../config/global.php";
header('Content-Type: application/json; charset=UTF-8');

// Cek koneksi dari global.php
if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal.']);
    exit;
}

// Menangkap data yang dikirim oleh Javascript (AJAX)
$item_code      = isset($_POST['item_code']) ? trim($_POST['item_code']) : '';
$material_name  = isset($_POST['material_name']) ? trim($_POST['material_name']) : '';
$material_grade = isset($_POST['material_grade']) ? trim($_POST['material_grade']) : '';
$colour         = isset($_POST['colour']) ? trim($_POST['colour']) : '';

// Untuk tanggal, jika kosong kita set menjadi null agar tidak error di SQL Server
$receive_date   = !empty($_POST['receive_date']) ? $_POST['receive_date'] : null;
$issue_date     = !empty($_POST['issue_date']) ? $_POST['issue_date'] : null;
$expired_date   = !empty($_POST['expired_date']) ? $_POST['expired_date'] : null;

// Query Insert
$sql = "INSERT INTO dbo.LABEL_MATERIAL_HISTORY 
        (ITEM_CODE, MATERIAL_NAME, MATERIAL_GRADE, COLOUR, RECEIVE_DATE, ISSUE_DATE, EXPIRED_DATE) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";

$params = array($item_code, $material_name, $material_grade, $colour, $receive_date, $issue_date, $expired_date);

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan ke database.']);
    exit;
}

echo json_encode(['status' => 'success', 'message' => 'Data berhasil disimpan.']);
sqlsrv_free_stmt($stmt);
?>
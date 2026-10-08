<?php
require_once __DIR__ . "/../config/database_ordering.php";

$item_no  = isset($_POST['ITEM_NO']) ? trim($_POST['ITEM_NO']) : '';
$location = isset($_POST['LOCATION']) ? trim($_POST['LOCATION']) : '';
$polybag  = isset($_POST['POLYBAG']) ? intval($_POST['POLYBAG']) : 0;

if ($item_no !== '') {
    // Cek apakah ITEM_NO sudah ada di database untuk mencegah error duplikat
    $check_sql = "SELECT ITEM_NO FROM EPSON_STD WHERE ITEM_NO = ?";
    $check_stmt = sqlsrv_query($conn, $check_sql, array($item_no));
    
    if (sqlsrv_has_rows($check_stmt)) {
        echo json_encode(array('success' => false, 'message' => 'Item No sudah terdaftar!'));
        exit;
    }

    $sql = "INSERT INTO EPSON_STD (ITEM_NO, LOCATION, POLYBAG) VALUES (?, ?, ?)";
    $params = array($item_no, $location, $polybag);
    
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'message' => 'Gagal menyimpan ke database.'));
    }
} else {
    echo json_encode(array('success' => false, 'message' => 'Item No tidak boleh kosong.'));
}
?>
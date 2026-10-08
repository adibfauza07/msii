<?php
require_once __DIR__ . "/../config/database_ordering.php";

// Kompatibel untuk PHP 5.x
$item_no = isset($_POST['ITEM_NO']) ? $_POST['ITEM_NO'] : '';
$column  = isset($_POST['COLUMN']) ? $_POST['COLUMN'] : '';
$value   = isset($_POST['VALUE']) ? $_POST['VALUE'] : '';

// Validasi kolom agar aman dari eksekusi yang tidak diinginkan
$allowed_columns = array('LOCATION', 'POLYBAG');

if (in_array($column, $allowed_columns) && $item_no !== '') {
    $sql = "UPDATE EPSON_STD SET $column = ? WHERE ITEM_NO = ?";
    $params = array($value, $item_no);
    
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'message' => 'Gagal update database.'));
    }
} else {
    echo json_encode(array('success' => false, 'message' => 'Data tidak valid.'));
}
?>
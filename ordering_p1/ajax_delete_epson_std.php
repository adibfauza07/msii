<?php
require_once __DIR__ . "/../config/database_ordering.php";

$item_no = isset($_POST['ITEM_NO']) ? trim($_POST['ITEM_NO']) : '';

if ($item_no !== '') {
    $sql = "DELETE FROM EPSON_STD WHERE ITEM_NO = ?";
    $stmt = sqlsrv_query($conn, $sql, array($item_no));

    if ($stmt) {
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'message' => 'Gagal menghapus data.'));
    }
} else {
    echo json_encode(array('success' => false, 'message' => 'Item No tidak valid.'));
}
?>
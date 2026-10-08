<?php
require_once __DIR__ . "/../config/database_ordering.php";

$q = isset($_POST['q']) ? trim($_POST['q']) : '';
$response = array();

if ($q !== '') {
    // Mencari berdasarkan ITEM_CODE atau ITEM_NO (dibatasi 20 agar tidak memberatkan browser)
    $sql = "SELECT TOP 20 ITEM_CODE, ITEM_NO, ITEM_NAME FROM ITEMS 
            WHERE ITEM_CODE LIKE ? OR ITEM_NO LIKE ?";
    $params = array("%$q%", "%$q%");
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $response[] = array(
                'ITEM_CODE' => isset($row['ITEM_CODE']) ? trim($row['ITEM_CODE']) : '',
                'ITEM_NO'   => isset($row['ITEM_NO']) ? trim($row['ITEM_NO']) : '',
                'ITEM_NAME' => isset($row['ITEM_NAME']) ? trim($row['ITEM_NAME']) : ''
            );
        }
    }
}

echo json_encode($response);
?>
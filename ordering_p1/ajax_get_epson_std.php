<?php
require_once __DIR__ . "/../config/database_ordering.php";

$response = array('success' => false, 'data' => array());

// Melakukan JOIN dengan tabel ITEMS untuk mendapatkan ITEM_CODE
$sql = "SELECT e.ITEM_NO, e.LOCATION, e.POLYBAG, i.ITEM_CODE 
        FROM EPSON_STD e 
        LEFT JOIN ITEMS i ON e.ITEM_NO = i.ITEM_NO
        ORDER BY e.ITEM_NO ASC";

$stmt = sqlsrv_query($conn, $sql);

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Membersihkan spasi pada tipe data char
        $row['ITEM_NO'] = trim($row['ITEM_NO']); 
        
        // Kompatibel untuk PHP 5.x
        $row['ITEM_CODE'] = isset($row['ITEM_CODE']) ? trim($row['ITEM_CODE']) : '-';
        $row['LOCATION'] = isset($row['LOCATION']) ? $row['LOCATION'] : '';
        $row['POLYBAG'] = isset($row['POLYBAG']) ? $row['POLYBAG'] : 0;
        
        $response['data'][] = $row;
    }
    $response['success'] = true;
} else {
    $response['message'] = 'Gagal mengeksekusi query.';
}

echo json_encode($response);
?>
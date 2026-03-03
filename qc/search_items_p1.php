<?php
// FILE: msii/qc/search_items_p1.php

set_time_limit(5); 
ini_set('display_errors', 0); 

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['results' => [['id' => '', 'text' => "Server Crash Plant 1: " . substr($error['message'], 0, 80)]]]);
        exit;
    }
});

ob_start(); 

// KONEKSI KHUSUS PLANT 1 LANGSUNG
$db_path = '../config/database_p1.php';
header('Content-Type: application/json; charset=utf-8');

if (!file_exists($db_path)) {
    echo json_encode(['results' => [['id' => '', 'text' => "Error: File config Plant 1 tidak ditemukan"]]]);
    exit;
}

require_once $db_path;

if (!isset($conn) || $conn === false) {
    echo json_encode(['results' => [['id' => '', 'text' => "DB Error: Koneksi Plant 1 Gagal"]]]);
    exit;
}

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

// QUERY KHUSUS PLANT 1
$sql = "
SELECT TOP 30 ITEM_ID, ITEM_NO, ITEM_CODE, ITEM_NAME 
FROM ITEMS 
WHERE (ITEM_NO LIKE ? OR ITEM_NAME LIKE ? OR ITEM_CODE LIKE ?) 
AND ITEM_INACTIVE = 0 
ORDER BY ITEM_NO ASC, ITEM_CODE ASC
";

$params = ["%".$term."%", "%".$term."%", "%".$term."%"];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $e = sqlsrv_errors();
    $msg = isset($e[0]['message']) ? $e[0]['message'] : "Unknown SQL Error";
    echo json_encode(['results' => [['id' => '', 'text' => "SQL Error Plant 1: " . substr($msg, 0, 80)]]]);
    exit;
}

$results = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    
    // LOGIKA PINTAR: Jika ITEM_NO kosong, otomatis pakai ITEM_CODE
    $no = trim((string)$row['ITEM_NO']);
    if (empty($no)) {
        $no = trim((string)$row['ITEM_CODE']);
    }
    
    $item_no = mb_convert_encoding($no, 'UTF-8', 'auto');
    $item_name = mb_convert_encoding(trim((string)$row['ITEM_NAME']), 'UTF-8', 'auto');
    
    $display_text = ($item_no !== '') ? ($item_no . ' - ' . $item_name) : $item_name;
    
    $results[] = [
        'id' => $row['ITEM_ID'],      
        'text' => $display_text, 
        'item_no' => $item_no, 
        'item_name' => $item_name
    ];
}

while (ob_get_level()) ob_end_clean();

if (empty($results)) {
    echo json_encode(['results' => [], 'pagination' => ['more' => false]]);
} else {
    echo json_encode([
        'results' => $results,
        'pagination' => ['more' => false]
    ]);
}
exit;
?>
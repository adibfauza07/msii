<?php
// FILE: search_items_spc.php
set_time_limit(5); 
ini_set('display_errors', 0); 

// Gunakan config global yang sudah menangani koneksi dan deteksi plant
require_once __DIR__ . '/../config/global.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($conn) || $conn === false) {
    echo json_encode(['results' => [['id' => '', 'text' => "DB Error: Koneksi Gagal"]]]);
    exit;
}

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

// Query mencari part number (Sesuaikan tabelnya jika Anda ingin baca dari Master_Part_Spec atau ITEMS)
// Di sini saya ikuti struktur query dari ITEMS yang Anda berikan
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
    echo json_encode(['results' => [['id' => '', 'text' => "SQL Error saat mencari data"]]]);
    exit;
}

$results = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $no = trim((string)$row['ITEM_NO']);
    if (empty($no)) {
        $no = trim((string)$row['ITEM_CODE']);
    }
    
    $item_no = mb_convert_encoding($no, 'UTF-8', 'auto');
    $item_name = mb_convert_encoding(trim((string)$row['ITEM_NAME']), 'UTF-8', 'auto');
    $display_text = ($item_no !== '') ? ($item_no . ' - ' . $item_name) : $item_name;
    
    $results[] = [
        'id' => $item_no, // Simpan ITEM_NO sebagai value (agar masuk ke tabel SPC)
        'text' => $display_text
    ];
}

echo json_encode(['results' => $results]);
exit;
?>
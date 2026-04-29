<?php
// msii/pe/search_part.php
require_once '../config/database_p1.php';

header('Content-Type: application/json');

$term = isset($_GET['term']) ? $_GET['term'] : '';

if (empty($term)) {
    echo json_encode([]);
    exit;
}

// Perbaikan Query: Mengambil CUST_ID dari ITEM_CUST_VIEW_TRIAL sesuai struktur DB kamu
$sql = "SELECT TOP 15 
            I.ITEM_CODE AS part_code, 
            I.ITEM_NAME AS part_name,
            V.CUST_ID AS cust_id,
            C.CUST_COMP AS cust_comp,
            STD.MAT_CODE AS mat_code,
            MAT.ITEM_NAME AS mat_name
        FROM ITEMS I
        -- 1. Ambil relasi Customer dari View
        LEFT JOIN ITEM_CUST_VIEW_TRIAL V ON I.ITEM_CODE = V.PART_CODE
        LEFT JOIN CUST C ON V.CUST_ID = C.CUST_ID
        -- 2. Ambil relasi Material dari tabel Standard
        LEFT JOIN TRIAL_PE_STD STD ON I.ITEM_CODE = STD.ITEM_CODE
        LEFT JOIN ITEMS MAT ON STD.MAT_CODE = MAT.ITEM_CODE
        WHERE I.ITEM_CODE LIKE ? OR I.ITEM_NAME LIKE ?";

$params = array("%$term%", "%$term%");
$stmt = sqlsrv_query($conn, $sql, $params);

// Pengecekan Error
if ($stmt === false) {
    echo json_encode([
        "error" => "SQL Error",
        "pesan" => sqlsrv_errors()
    ]);
    exit;
}

$results = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $row['label'] = $row['part_code'] . ' - ' . $row['part_name'];
    $row['value'] = $row['part_code'];
    $results[] = $row;
}

echo json_encode($results);
?>
<?php
// FILE: msii/pe/search_part.php
require_once '../config/database_p1.php';

header('Content-Type: application/json');

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

if (empty($term)) {
    echo json_encode([]);
    exit;
}

// PERBAIKAN FINAL: Ambil riwayat Customer terakhir dari tabel TRIAL_PE (Jurus Paling Aman!)
$sql = "SELECT TOP 15 
            I.ITEM_CODE AS part_code, 
            I.ITEM_NAME AS part_name,
            LAST_CUST.CUST_ID AS cust_id,
            LAST_CUST.CUST_COMP AS cust_comp,
            MAT.ITEM_ID AS mat_id,
            STD.MAT_CODE AS mat_code,
            MAT.ITEM_NAME AS mat_name
        FROM ITEMS I
        LEFT JOIN TRIAL_PE_STD STD ON I.ITEM_CODE = STD.ITEM_CODE
        LEFT JOIN ITEMS MAT ON STD.MAT_CODE = MAT.ITEM_CODE
        OUTER APPLY (
            SELECT TOP 1 TP.CUST_ID, C.CUST_COMP 
            FROM TRIAL_PE TP 
            LEFT JOIN CUST C ON TP.CUST_ID = C.CUST_ID 
            WHERE TP.PART_CODE = I.ITEM_CODE 
            ORDER BY TP.TRIAL_CODE DESC
        ) AS LAST_CUST
        WHERE I.ITEM_CODE LIKE ? OR I.ITEM_NAME LIKE ?";

$params = array("%$term%", "%$term%");
$stmt = sqlsrv_query($conn, $sql, $params);

// Tangkap Error jika ada (harusnya sudah lolos!)
if ($stmt === false) {
    $errors = sqlsrv_errors();
    $pesan_error = isset($errors[0]['message']) ? $errors[0]['message'] : 'Unknown SQL Error';
    echo json_encode([["label" => "🚨 ERROR DB: " . $pesan_error, "value" => ""]]);
    exit;
}

$results = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $row['label'] = $row['part_code'] . ' - ' . $row['part_name'];
    $row['value'] = $row['part_code'];
    
    // Bawa data master ke form
    $row['cust_id']   = $row['cust_id'];
    $row['cust_comp'] = $row['cust_comp'];
    $row['mat_id']    = $row['mat_id'];
    $row['mat_code']  = $row['mat_code'];
    $row['mat_name']  = $row['mat_name'];
    
    $results[] = $row;
}

echo json_encode($results);
?>
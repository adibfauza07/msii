<?php
// ajax_autocomplete_mat.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
session_write_close();
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . "/../config/global.php"; 

if (!isset($conn)) {
    die(json_encode(array()));
}

$term  = isset($_GET['term']) ? trim($_GET['term']) : '';
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : 0;

if (strlen($term) < 2 || $tahun === 0 || $bulan === 0) {
    echo json_encode(array());
    exit;
}

try {
    // Format tanggal untuk pencocokan WO_MMYY (Format: YYYY-MM-01)
    $woDateStr = sprintf('%04d-%02d-01 00:00:00', $tahun, $bulan);
    $searchTerm = '%' . $term . '%';

    // Protective Action: Gunakan DISTINCT dan TOP 20 untuk mencegah memory leak
    // dan pastikan parameterisasi untuk keamanan SQL Server 2008.
    $sql = "SELECT DISTINCT TOP 20
                   ITEMS_1.ITEM_CODE AS MAT_CODE, 
                   ITEMS_1.ITEM_NAME AS MAT_NAME
            FROM dbo.WO 
            INNER JOIN dbo.ITEMS ON dbo.WO.ITEM_ID = dbo.ITEMS.ITEM_ID 
            INNER JOIN dbo.BOM_DEFAULT ON dbo.ITEMS.ITEM_ID = dbo.BOM_DEFAULT.PART_ID 
            INNER JOIN dbo.ITEMS AS ITEMS_1 ON dbo.BOM_DEFAULT.ITEM_ID = ITEMS_1.ITEM_ID
            WHERE dbo.WO.WO_MMYY = CONVERT(DATETIME, ?, 120)
              AND (ITEMS_1.ITEM_CODE LIKE ? OR ITEMS_1.ITEM_NAME LIKE ?)";

    $stmt = sqlsrv_query($conn, $sql, array($woDateStr, $searchTerm, $searchTerm));
    
    if ($stmt === false) {
        throw new Exception("Error DB");
    }

    $results = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = array(
            'id'    => trim($row['MAT_CODE']),
            'value' => trim($row['MAT_CODE']),
            'label' => trim($row['MAT_CODE']) . ' - ' . trim($row['MAT_NAME']),
            'name'  => trim($row['MAT_NAME'])
        );
    }
    sqlsrv_free_stmt($stmt);

    echo json_encode($results);

} catch (Exception $e) {
    echo json_encode(array());
}
?>
<?php
// Koneksi P1
require_once '../config/Database_p1.php';

header('Content-Type: application/json');

$term = isset($_GET['term']) ? $_GET['term'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20; 
$offset = ($page - 1) * $limit;
$offset_end = $page * $limit; 

$results = ['results' => [], 'pagination' => ['more' => false]];

if (strlen($term) < 2) { 
    echo json_encode($results);
    exit;
}

$searchTerm = "%" . $term . "%";

$sql = "
    WITH PagedItems AS (
        SELECT 
            ITEM_ID, ITEM_NO, ITEM_NAME,
            ROW_NUMBER() OVER (ORDER BY ITEM_NO ASC) as row_num
        FROM ITEMS 
        WHERE 
            ITEM_INACTIVE = 0 
            AND (ITEM_NO LIKE ? OR ITEM_NAME LIKE ?)
    )
    SELECT ITEM_ID, ITEM_NO, ITEM_NAME 
    FROM PagedItems
    WHERE row_num > ? AND row_num <= ?
    ORDER BY ITEM_NO;
";
$params = [$searchTerm, $searchTerm, $offset, $offset_end];

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $results['error'] = "Query Gagal: " . print_r(sqlsrv_errors(), true);
    echo json_encode($results);
    exit;
}

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $displayText = $row['ITEM_NO'] . ' - ' . htmlspecialchars($row['ITEM_NAME']);
    
    $results['results'][] = [
        'id'        => $row['ITEM_ID'],
        'text'      => $displayText, 
        'item_name' => htmlspecialchars($row['ITEM_NAME']), 
        'item_no'   => $row['ITEM_NO'] 
    ];
}

$sqlTotal = "
    SELECT COUNT(ITEM_ID) as total 
    FROM ITEMS 
    WHERE ITEM_INACTIVE = 0 
    AND (ITEM_NO LIKE ? OR ITEM_NAME LIKE ?)
";
$resTotal = sqlsrv_query($conn, $sqlTotal, [$searchTerm, $searchTerm]);
$totalRow = sqlsrv_fetch_array($resTotal, SQLSRV_FETCH_ASSOC)['total'];

if ($totalRow > $offset_end) {
    $results['pagination']['more'] = true;
}

echo json_encode($results);
?>
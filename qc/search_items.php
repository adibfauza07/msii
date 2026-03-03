<?php
// File ini akan dipanggil oleh Select2 (AJAX) untuk mencari item
require_once '../config/database.php';
require_once '../config/database_p1.php';

header('Content-Type: application/json');

$term = isset($_GET['term']) ? $_GET['term'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20; // Ambil 20 data per pencarian
$offset = ($page - 1) * $limit;
$offset_end = $page * $limit; // Batas atas untuk ROW_NUMBER()

$results = ['results' => [], 'pagination' => ['more' => false]];

if (strlen($term) < 2) { // Pastikan term ada (meski JS sudah filter)
    echo json_encode($results);
    exit;
}

$searchTerm = "%" . $term . "%";

// === INI BAGIAN YANG DIUBAH (Query kompatibel SQL Server 2008) ===
// Kita menggunakan ROW_NUMBER() dan Common Table Expression (CTE)
$sql = "
    WITH PagedItems AS (
        SELECT 
            ITEM_ID, ITEM_NO, ITEM_CODE, ITEM_NAME,
            ROW_NUMBER() OVER (ORDER BY ITEM_NO ASC) as row_num
        FROM ITEMS 
        WHERE 
            ITEM_INACTIVE = 0 
            AND (ITEM_NO LIKE ? OR ITEM_NAME LIKE ?)
    )
    SELECT ITEM_ID, ITEM_NO, ITEM_CODE, ITEM_NAME 
    FROM PagedItems
    WHERE row_num > ? AND row_num <= ?
    ORDER BY ITEM_NO;
";
// Parameter juga berubah urutannya
$params = [$searchTerm, $searchTerm, $offset, $offset_end];
// =================== AKHIR PERUBAHAN ===================

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    // Jika ada error, kirim pesan error (opsional, untuk debugging)
    $results['error'] = "Query Gagal: " . print_r(sqlsrv_errors(), true);
    echo json_encode($results);
    exit;
}

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Gabungkan ITEM_NO dan ITEM_NAME untuk ditampilkan di list
    $displayText = $row['ITEM_NO'] . ' - ' . htmlspecialchars($row['ITEM_NAME']);
    
    $results['results'][] = [
        'id'        => $row['ITEM_ID'],
        'text'      => $displayText, // Ini yang tampil di list dropdown
        'item_name' => htmlspecialchars($row['ITEM_NAME']), // Data ekstra untuk Part Name
        'item_no'   => $row['ITEM_NO'] // Data ekstra untuk ditampilkan setelah dipilih
    ];
}

// Cek apakah masih ada data lagi (untuk pagination 'load more')
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
<?php
// FILE: ajax_get_item.php
error_reporting(E_ALL);
ini_set('display_errors', 0); 
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 1. Panggil koneksi database
$active_plant = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1';
$db_path = ($active_plant == 'p2') ? __DIR__ . '/../config/database.php' : __DIR__ . '/../config/database_p1.php';

if(!file_exists($db_path)) {
    echo json_encode(array('results' => array(), 'error' => 'File DB tidak ditemukan'));
    exit;
}
require_once $db_path; 

if (!isset($conn) || $conn === false) {
    echo json_encode(array('results' => array(), 'error' => 'Koneksi database gagal'));
    exit;
}

// 2. Tangkap parameter dari Select2
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1; // Tangkap halaman saat ini
$limit = 20; // Jumlah data per halaman
$offset = ($page - 1) * $limit;
$offset_end = $page * $limit;

if ($q == "") {
    echo json_encode(array('results' => array(), 'pagination' => array('more' => false)));
    exit;
}
$like = "%" . $q . "%";

// 3. QUERY DENGAN PAGINATION (MENGGUNAKAN ROW_NUMBER)
$sql = "
    WITH PagedItems AS (
        SELECT 
            I.ITEM_ID, 
            V.PART_CODE, 
            V.PART_NO, 
            V.PART_NAME, 
            V.CUST_COMP,
            ROW_NUMBER() OVER (ORDER BY V.PART_NO ASC, V.PART_NAME ASC) as row_num
        FROM dbo.ITEM_CUSTINFO_VIEW V
        LEFT JOIN dbo.ITEMS I ON V.PART_NO = I.ITEM_NO
        WHERE V.PART_CODE LIKE ? OR V.PART_NO LIKE ?
    )
    SELECT * 
    FROM PagedItems
    WHERE row_num > ? AND row_num <= ?
";
        
$params = array($like, $like, $offset, $offset_end);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $errors = sqlsrv_errors();
    $error_msg = !empty($errors) ? $errors[0]['message'] : 'Query Gagal dieksekusi';
    echo json_encode(array('results' => array(), 'error' => $error_msg));
    exit;
}

// 4. Masukkan ke array format Select2
$data = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = array(
        'id'        => (int)trim($row['ITEM_ID']), // Pastikan tetap integer
        'text'      => trim($row['PART_NO']) . ' - ' . trim($row['PART_NAME']),
        'part_no'   => trim($row['PART_NO']),
        'part_code' => trim($row['PART_CODE']),
        'part_name' => trim($row['PART_NAME']),
        'cust_comp' => trim($row['CUST_COMP'])
    );
}

// 5. Cek Total Data untuk Pagination
$sqlTotal = "
    SELECT COUNT(V.PART_NO) as total 
    FROM dbo.ITEM_CUSTINFO_VIEW V
    WHERE V.PART_CODE LIKE ? OR V.PART_NO LIKE ?
";
$resTotal = sqlsrv_query($conn, $sqlTotal, array($like, $like));
$totalRow = 0;
if ($resTotal !== false) {
    $totalData = sqlsrv_fetch_array($resTotal, SQLSRV_FETCH_ASSOC);
    $totalRow = $totalData['total'];
}

$morePages = $totalRow > $offset_end;

echo json_encode(array(
    'results' => $data,
    'pagination' => array('more' => $morePages)
));
exit;
?>
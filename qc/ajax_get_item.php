<?php
// FILE: ajax_get_item.php
error_reporting(E_ALL);
ini_set('display_errors', 0); 
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 1. Panggil koneksi database sesuai plant aktif
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

if ($q == "") {
    echo json_encode(array('results' => array()));
    exit;
}

$like = "%" . $q . "%";

// 3. QUERY SUDAH DISESUAIKAN DENGAN STRUKTUR DATABASE ANDA
$sql = "SELECT TOP 20 ITEM_ID, PART_CODE, PART_NO, PART_NAME, CUST_COMP 
        FROM dbo.ITEM_CUSTINFO_VIEW 
        WHERE PART_CODE LIKE ? OR PART_NO LIKE ?";
        
$params = array($like, $like);
$stmt = sqlsrv_query($conn, $sql, $params);

// Tangkap pesan error SQL jika query masih gagal
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
        'id'        => trim($row['ITEM_ID']), // Diubah menjadi ITEM_ID
        'text'      => trim($row['PART_NO']) . ' | ' . trim($row['PART_CODE']),
        'part_no'   => trim($row['PART_NO']),
        'part_code' => trim($row['PART_CODE']),
        'part_name' => trim($row['PART_NAME']),
        'cust_comp' => trim($row['CUST_COMP'])
    );
}

echo json_encode(array('results' => $data));
exit;
?>
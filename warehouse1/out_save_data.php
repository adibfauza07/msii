<?php
// out_save_data.php (PHP 5.4 & SQL Server 2008)
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once __DIR__ . "/../config/global.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $conn === false) {
    exit('false');
}

$tranid    = isset($_POST['tranid']) ? (int)$_POST['tranid'] : 0;
$tranadate = isset($_POST['trandate']) ? trim($_POST['trandate']) : date('Y-m-d');
$trty      = isset($_POST['trty']) ? trim($_POST['trty']) : '';
$trandoc   = isset($_POST['trandoc']) ? trim($_POST['trandoc']) : '';
$merchant  = (isset($_POST['merchant']) && trim($_POST['merchant']) !== '') ? trim($_POST['merchant']) : null;
$tbc       = isset($_POST['tbc']) ? $_POST['tbc'] : '';

$bcty = null; 
$bcno = null; 
$bcdate = null;

if ($tbc === 'Y') {
    $bcty   = (isset($_POST['bcty']) && trim($_POST['bcty']) !== '') ? trim($_POST['bcty']) : null;
    $bcno   = (isset($_POST['bcno']) && trim($_POST['bcno']) !== '') ? trim($_POST['bcno']) : null;
    $bcdate = (isset($_POST['bcdate']) && trim($_POST['bcdate']) !== '') ? trim($_POST['bcdate']) : null;
}

$sql = "
    UPDATE TRANS 
    SET TRAN_ADATE = ?, TRTY_CODE = ?, TRAN_DOC = ?, SUP_CODE = ?, BCTY_ID = ?, BC_NO = ?, BC_DATE = ?
    WHERE TRAN_ID = ?
";
$params = array($tranadate, $trty, $trandoc, $merchant, $bcty, $bcno, $bcdate, $tranid);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt !== false) {
    sqlsrv_free_stmt($stmt);
    echo 'true';
} else {
    // Kembalikan error log spesifik ke console response jika diperlukan
    // print_r(sqlsrv_errors());
    echo 'false';
}
?>
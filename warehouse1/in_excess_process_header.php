<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once __DIR__ . "/../config/global.php"; //[cite: 3]

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('status' => 'error', 'message' => 'Metode tidak diizinkan.'));
    exit;
}

$tranadate = isset($_POST['trandate']) ? trim($_POST['trandate']) : date('Y-m-d');
$trty      = isset($_POST['trty']) ? trim($_POST['trty']) : '';
$merchant  = isset($_POST['merchant']) && $_POST['merchant'] !== '' ? $_POST['merchant'] : null;
$tbc       = isset($_POST['tbc']) ? $_POST['tbc'] : '';

$bcty = null; $bcno = null; $bcdate = null;
if ($tbc === 'Y') {
    $bcty   = isset($_POST['bcty']) ? $_POST['bcty'] : null;
    $bcno   = isset($_POST['bcno']) ? $_POST['bcno'] : null;
    $bcdate = isset($_POST['bcdate']) ? $_POST['bcdate'] : null;
}

sqlsrv_begin_transaction($conn);

try {
    // 1. Ambil nomor dokumen dari REFS[cite: 5, 3]
    $sqlRef = "SELECT TOP 1 REF_ID, NEXT_PR FROM REFS ORDER BY REF_ID";
    $stmtRef = sqlsrv_query($conn, $sqlRef);
    $rowRef = sqlsrv_fetch_array($stmtRef, SQLSRV_FETCH_ASSOC);
    $ref_id  = $rowRef['REF_ID'];
    $trandoc = $rowRef['NEXT_PR'];

    // 2. Update NEXT_PR +1[cite: 5, 3]
    $sqlUpdateRef = "UPDATE REFS SET NEXT_PR = NEXT_PR + 1 WHERE REF_ID = ?";
    sqlsrv_query($conn, $sqlUpdateRef, array($ref_id));

    // 3. Insert ke TRANS (Source = 1)[cite: 5]
    $trandate = date('Y-m-d H:i:s');
    $source = 1;
    $sqlInsertTrans = "INSERT INTO TRANS (TRAN_DATE, TRAN_ADATE, TRTY_CODE, TRAN_DOC, TRAN_SOURCE, SUP_CODE, BCTY_ID, BC_NO, BC_DATE) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    sqlsrv_query($conn, $sqlInsertTrans, array($trandate, $tranadate, $trty, $trandoc, $source, $merchant, $bcty, $bcno, $bcdate));

    // 4. Ambil TRAN_ID[cite: 5]
    $stmtGetId = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS ORDER BY TRAN_ID DESC");
    $rowId = sqlsrv_fetch_array($stmtGetId, SQLSRV_FETCH_ASSOC);
    
    sqlsrv_commit($conn);
    echo json_encode(array('status' => 'success', 'tranid' => $rowId['TRAN_ID'], 'trandoc' => $trandoc));

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
?>
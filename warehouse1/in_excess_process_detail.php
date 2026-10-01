<?php
require_once __DIR__ . "/../config/global.php"; //[cite: 2]
header('Content-Type: application/json; charset=utf-8');

$tranid   = isset($_POST['tranid']) ? (int)$_POST['tranid'] : 0;
$itemid   = isset($_POST['itemid']) ? (int)$_POST['itemid'] : 0;
$poid     = isset($_POST['poid']) && $_POST['poid'] !== '' ? (int)$_POST['poid'] : null;
$rcvdqty  = isset($_POST['rcvdqty']) ? (float)$_POST['rcvdqty'] : 0;
$qrcodeid = isset($_POST['qrcodeid']) ? trim($_POST['qrcodeid']) : '';

sqlsrv_begin_transaction($conn);

try {
    // Cek Duplikasi berdasarkan SCAN_TYPE = 0 untuk incoming[cite: 5]
    $sqlCheck = "SELECT TOP 1 QRCODE_ID FROM INV_TRAN WHERE QRCODE_ID = ? AND SCAN_TYPE = 0";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($qrcodeid));
    if (sqlsrv_has_rows($stmtCheck)) {
        throw new Exception("QR Code [$qrcodeid] sudah pernah di-scan.");
    }

    // Ambil nomor urut baris (IT_LINENO) berikutnya[cite: 2, 5]
    $sqlLine = "SELECT MAX(IT_LINENO) AS max_line FROM INV_TRAN WHERE TRAN_ID = ?";
    $stmtLine = sqlsrv_query($conn, $sqlLine, array($tranid));
    $rowLine = sqlsrv_fetch_array($stmtLine, SQLSRV_FETCH_ASSOC);
    $lineno = isset($rowLine['max_line']) ? ((int)$rowLine['max_line'] + 1) : 1;

    // Insert Detail (SCAN_TYPE = 0)[cite: 5]
    $type = 0;
    $sqlInsert = "INSERT INTO INV_TRAN (TRAN_ID, IT_LINENO, ITEM_ID, IT_QTY, IT_SID, QRCODE_ID, SCAN_TYPE) VALUES (?, ?, ?, ?, ?, ?, ?)";
    sqlsrv_query($conn, $sqlInsert, array($tranid, $lineno, $itemid, $rcvdqty, $poid, $qrcodeid, $type));

    sqlsrv_commit($conn);
    echo json_encode(array('status' => 'success', 'message' => 'Tersimpan'));

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
?>
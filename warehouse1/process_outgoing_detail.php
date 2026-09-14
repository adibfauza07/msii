<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once __DIR__ . "/../config/global.php";

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('status' => 'error', 'message' => 'Metode tidak diizinkan.'));
    exit;
}

$tranid   = isset($_POST['tranid']) ? (int)$_POST['tranid'] : 0;
$itemid   = isset($_POST['itemid']) ? (int)$_POST['itemid'] : 0;
$poid     = isset($_POST['poid']) && $_POST['poid'] !== '' ? (int)$_POST['poid'] : null;
$rcvdqty  = isset($_POST['rcvdqty']) ? (float)$_POST['rcvdqty'] : 0;
$qrcodeid = isset($_POST['qrcodeid']) ? trim($_POST['qrcodeid']) : '';

if ($tranid === 0 || $qrcodeid === '' || $itemid === 0) {
    echo json_encode(array('status' => 'error', 'message' => 'Data detail tidak valid.'));
    exit;
}

if ($conn === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Koneksi database gagal.'));
    exit;
}

sqlsrv_begin_transaction($conn);

try {
    // 1. Cek Duplikasi Scan QR berdasarkan SCAN_TYPE = 1[cite: 7]
    $sqlCheck = "SELECT TOP 1 QRCODE_ID FROM INV_TRAN WHERE QRCODE_ID = ? AND SCAN_TYPE = 1";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($qrcodeid));
    if ($stmtCheck !== false && sqlsrv_has_rows($stmtCheck)) {
        throw new Exception("QR Code [$qrcodeid] sudah pernah di-scan sebelumnya.");
    }
    if ($stmtCheck !== false) sqlsrv_free_stmt($stmtCheck);

    // 2. Ambil nomor urut baris (IT_LINENO) berikutnya[cite: 7]
    $sqlLine = "SELECT MAX(IT_LINENO) AS max_line FROM INV_TRAN WHERE TRAN_ID = ?";
    $stmtLine = sqlsrv_query($conn, $sqlLine, array($tranid));
    $rowLine = sqlsrv_fetch_array($stmtLine, SQLSRV_FETCH_ASSOC);
    $lineno = isset($rowLine['max_line']) ? ((int)$rowLine['max_line'] + 1) : 1;
    sqlsrv_free_stmt($stmtLine);

    // 3. Insert Detail ke tabel INV_TRAN[cite: 7]
    $stcode = '';
    $type   = 1;

    $sqlInsert = "
        INSERT INTO INV_TRAN (TRAN_ID, IT_LINENO, ST_CODE, ITEM_ID, IT_QTY, IT_SID, QRCODE_ID, SCAN_TYPE)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $params = array($tranid, $lineno, $stcode, $itemid, $rcvdqty, $poid, $qrcodeid, $type);
    $stmtInsert = sqlsrv_query($conn, $sqlInsert, $params);

    if ($stmtInsert === false) {
        throw new Exception("Gagal menyimpan detail item pengeluaran.");
    }
    sqlsrv_free_stmt($stmtInsert);

    sqlsrv_commit($conn);

    echo json_encode(array('status' => 'success', 'message' => 'Data detail berhasil disimpan.'));

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
?>
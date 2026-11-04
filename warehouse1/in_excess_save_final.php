<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . "/../config/global.php";

header('Content-Type: application/json');

if ($conn === false) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal.']);
    exit;
}

$tranid      = isset($_POST['tranid']) ? (int)$_POST['tranid'] : 0;
$sts         = isset($_POST['sts']) ? $_POST['sts'] : 'New';
$trandate    = isset($_POST['trandate']) ? $_POST['trandate'] : date('Y-m-d');
$trty        = isset($_POST['trty']) ? $_POST['trty'] : '';
$merchant    = isset($_POST['merchant']) ? $_POST['merchant'] : '';
$detailsJson = isset($_POST['details']) ? $_POST['details'] : '[]';
$details     = json_decode($detailsJson, true);
$stCode      = isset($_SESSION['st_code']) ? $_SESSION['st_code'] : '01';

if (empty($trty) || empty($details)) {
    echo json_encode(['status' => 'error', 'message' => 'Data transaksi atau detail item tidak lengkap.']);
    exit;
}

if ($sts === 'Edit' && $tranid > 0) {
    // Update Header Transaksi
    $sqlUpdate = "UPDATE TRANS SET TRAN_DATE = ?, SUP_CODE = ? WHERE TRAN_ID = ?";
    $paramsUp = array($trandate, empty($merchant) ? null : $merchant, $tranid);
    sqlsrv_query($conn, $sqlUpdate, $paramsUp);

    // Hapus detail lama, masukkan detail baru
    sqlsrv_query($conn, "DELETE FROM INV_TRAN WHERE TRAN_ID = ?", array($tranid));
} else {
    // Generate Nomor Dokumen 9 Digit (YYMM + 5 digit running number, contoh: 261000233)
    $tahun  = date('Y', strtotime($trandate));
    $bulan  = date('m', strtotime($trandate));
    $prefix = substr($tahun, 2, 2) . $bulan; // Contoh: '2610'

    $sqlDoc = "SELECT MAX(CAST(SUBSTRING(TRAN_DOC, 1, 9) AS INT)) as MaxNo FROM TRANS WHERE TRAN_DOC LIKE ?";
    $stmtDoc = sqlsrv_query($conn, $sqlDoc, array($prefix . '%'));
    
    $nextNo = (int)($prefix . '00001'); // Default jika belum ada transaksi di bulan tersebut
    if ($stmtDoc !== false) {
        $rowDoc = sqlsrv_fetch_array($stmtDoc, SQLSRV_FETCH_ASSOC);
        if ($rowDoc && $rowDoc['MaxNo'] !== null) {
            $nextNo = (int)$rowDoc['MaxNo'] + 1;
        }
    }
    $trandoc = (string)$nextNo; // Contoh hasil: '261000233'

    // Insert Header Baru ke TRANS
    $sqlInsertTrans = "
        INSERT INTO TRANS (TRAN_DATE, TRAN_ADATE, TRTY_CODE, TRAN_DOC, SUP_CODE, TRAN_SOURCE)
        VALUES (?, GETDATE(), ?, ?, ?, 1);
        SELECT SCOPE_IDENTITY() AS TRAN_ID;
    ";
    $paramsTrans = array($trandate, $trty, $trandoc, empty($merchant) ? null : $merchant);
    $stmtTrans = sqlsrv_query($conn, $sqlInsertTrans, $paramsTrans);

    if ($stmtTrans === false) {
        $errors = sqlsrv_errors();
        $dbErrorMsg = isset($errors[0]['message']) ? $errors[0]['message'] : 'Gagal menyimpan header transaksi.';
        echo json_encode(['status' => 'error', 'message' => $dbErrorMsg]);
        exit;
    }

    sqlsrv_next_result($stmtTrans);
    if ($row = sqlsrv_fetch_array($stmtTrans, SQLSRV_FETCH_ASSOC)) {
        $tranid = $row['TRAN_ID'];
    }
}

if ($tranid === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Gagal mendapatkan ID Transaksi.']);
    exit;
}

// Insert Detail Item ke INV_TRAN
foreach ($details as $index => $item) {
    $lineno   = $index + 1;
    $itemId   = trim($item['itemId']);
    $qty      = (float)$item['qty'];
    $qrcodeid = trim($item['qrId']);

    $sqlInsertDetail = "
        INSERT INTO INV_TRAN (TRAN_ID, IT_LINENO, ITEM_ID, IT_QTY, QRCODE_ID, ST_CODE)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $paramsDetail = array($tranid, $lineno, $itemId, $qty, $qrcodeid, $stCode);
    $stmtDetail = sqlsrv_query($conn, $sqlInsertDetail, $paramsDetail);

    if ($stmtDetail === false) {
        $errors = sqlsrv_errors();
        $dbErrorMsg = isset($errors[0]['message']) ? $errors[0]['message'] : 'Unknown SQL Error';
        
        if ($sts !== 'Edit') {
            sqlsrv_query($conn, "DELETE FROM TRANS WHERE TRAN_ID = ?", array($tranid));
        }

        echo json_encode(['status' => 'error', 'message' => 'Baris ' . $lineno . ': ' . $dbErrorMsg]);
        exit;
    }
}

echo json_encode(['status' => 'success']);
?>
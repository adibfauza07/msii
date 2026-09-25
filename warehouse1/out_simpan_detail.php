<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once __DIR__ . "/../config/global.php";

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('status' => 'error', 'message' => 'Metode tidak diizinkan.'));
    exit;
}

// 1. Ekstrak & Sanitasi Parameter
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

// Transaksi Dimulai
sqlsrv_begin_transaction($conn);

try {
    // 2. VERIFIKASI EKSISTENSI: Pastikan QR Code ada di database sebelum dikeluarkan
    $sqlExist = "SELECT TOP 1 QRCODE_ID FROM INV_TRAN WITH (NOLOCK) WHERE QRCODE_ID = ?";
    $stmtExist = sqlsrv_query($conn, $sqlExist, array($qrcodeid));
    if ($stmtExist === false || !sqlsrv_has_rows($stmtExist)) {
        throw new Exception("QR Code [$qrcodeid] tidak terdaftar di sistem. Barang belum pernah masuk!");
    }
    if ($stmtExist !== false) sqlsrv_free_stmt($stmtExist);

    // 3. Cek Duplikasi Scan QR (Mencegah Double Input) dengan Table Lock
    $sqlCheck = "SELECT TOP 1 QRCODE_ID FROM INV_TRAN WITH (UPDLOCK, HOLDLOCK) WHERE QRCODE_ID = ? AND SCAN_TYPE = 1";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($qrcodeid));
    if ($stmtCheck !== false && sqlsrv_has_rows($stmtCheck)) {
        throw new Exception("QR Code [$qrcodeid] sudah pernah di-scan untuk pengeluaran sebelumnya.");
    }
    if ($stmtCheck !== false) sqlsrv_free_stmt($stmtCheck);

    // 4. Ambil nomor urut baris (IT_LINENO) berikutnya secara aman
    $sqlLine = "SELECT ISNULL(MAX(IT_LINENO), 0) + 1 AS next_line FROM INV_TRAN WITH (UPDLOCK, HOLDLOCK) WHERE TRAN_ID = ?";
    $stmtLine = sqlsrv_query($conn, $sqlLine, array($tranid));
    $rowLine = sqlsrv_fetch_array($stmtLine, SQLSRV_FETCH_ASSOC);
    $lineno = $rowLine['next_line'];
    sqlsrv_free_stmt($stmtLine);

    // 5. Insert Detail ke tabel INV_TRAN
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
<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once __DIR__ . "/../config/global.php";

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('status' => 'error', 'message' => 'Metode tidak diizinkan.'));
    exit;
}

$tranadate = isset($_POST['trandate']) ? trim($_POST['trandate']) : date('Y-m-d');
$trty      = isset($_POST['trty']) ? trim($_POST['trty']) : '';
$merchant  = isset($_POST['merchant']) && $_POST['merchant'] !== '' ? $_POST['merchant'] : null;
$tbc       = isset($_POST['tbc']) ? $_POST['tbc'] : '';

$bcty = null;
$bcno = null;
$bcdate = null;

if ($tbc === 'Y') {
    $bcty   = isset($_POST['bcty']) ? $_POST['bcty'] : null;
    $bcno   = isset($_POST['bcno']) ? $_POST['bcno'] : null;
    $bcdate = isset($_POST['bcdate']) ? $_POST['bcdate'] : null;
}

if ($conn === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Koneksi database gagal.'));
    exit;
}

// Mulai Transaksi Database (ACID Compliance)[cite: 4]
sqlsrv_begin_transaction($conn);

try {
    // 1. Kunci Antrian REFS untuk mengambil Document Number (NEXT_PR)[cite: 4]
    $sqlRef = "SELECT TOP 1 REF_ID, NEXT_PR FROM REFS WITH (UPDLOCK, HOLDLOCK) ORDER BY REF_ID";
    $stmtRef = sqlsrv_query($conn, $sqlRef);
    if ($stmtRef === false || !sqlsrv_has_rows($stmtRef)) {
        throw new Exception("Nomor referensi dokumen (REFS) tidak ditemukan.");
    }
    $rowRef = sqlsrv_fetch_array($stmtRef, SQLSRV_FETCH_ASSOC);
    $ref_id  = $rowRef['REF_ID'];
    $trandoc = $rowRef['NEXT_PR'];
    sqlsrv_free_stmt($stmtRef);

    // 2. Update NEXT_PR +1 pada tabel REFS[cite: 4]
    $sqlUpdateRef = "UPDATE REFS SET NEXT_PR = NEXT_PR + 1 WHERE REF_ID = ?";
    $stmtUpdRef = sqlsrv_query($conn, $sqlUpdateRef, array($ref_id));
    if ($stmtUpdRef === false) {
        throw new Exception("Gagal memperbarui nomor urut dokumen otomatis.");
    }
    sqlsrv_free_stmt($stmtUpdRef);

    // 3. Insert Header Transaksi ke tabel TRANS[cite: 4]
    $trandate  = date('Y-m-d H:i:s');
    $transtime = date('H:i:s');
    $source    = 1;

    $sqlInsertTrans = "
        INSERT INTO TRANS (TRAN_DATE, TRAN_ADATE, TRTY_CODE, TRAN_DOC, TRAN_SOURCE, SUP_CODE, BCTY_ID, BC_NO, BC_DATE, TRANS_TIME)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $paramsTrans = array($trandate, $tranadate, $trty, $trandoc, $source, $merchant, $bcty, $bcno, $bcdate, $transtime);
    $stmtTrans = sqlsrv_query($conn, $sqlInsertTrans, $paramsTrans);
    if ($stmtTrans === false) {
        throw new Exception("Gagal menyimpan header transaksi.");
    }
    sqlsrv_free_stmt($stmtTrans);

    // 4. Ambil TRAN_ID menggunakan SCOPE_IDENTITY untuk akurasi mutlak di SQL Server 2008[cite: 4]
    $sqlGetId = "SELECT TOP 1 TRAN_ID FROM TRANS ORDER BY TRAN_ID DESC";
    $stmtGetId = sqlsrv_query($conn, $sqlGetId);
    $rowId = sqlsrv_fetch_array($stmtGetId, SQLSRV_FETCH_ASSOC);
    $tranid = $rowId['TRAN_ID'];
    sqlsrv_free_stmt($stmtGetId);

    // Commit transaksi jika sukses
    sqlsrv_commit($conn);

    echo json_encode(array(
        'status'  => 'success',
        'tranid'  => $tranid,
        'trandoc' => $trandoc
    ));

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
?>
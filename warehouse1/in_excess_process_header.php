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

$trandate = isset($_POST['trandate']) ? $_POST['trandate'] : date('Y-m-d');
$trty     = isset($_POST['trty']) ? $_POST['trty'] : '';
$merchant = isset($_POST['merchant']) ? $_POST['merchant'] : '';

if (empty($trty)) {
    echo json_encode(['status' => 'error', 'message' => 'Jenis transaksi belum dipilih.']);
    exit;
}

// Generate Nomor Dokumen (TRAN_DOC) Berdasarkan Tahun & Jenis Transaksi
$tahun = date('Y', strtotime($trandate));
$sqlDoc = "SELECT MAX(CAST(SUBSTRING(TRAN_DOC, 5, 5) AS INT)) as MaxNo FROM TRANS WHERE YEAR(TRAN_DATE) = ? AND TRTY_CODE = ?";
$stmtDoc = sqlsrv_query($conn, $sqlDoc, array($tahun, $trty));
$nextNo = 1;
if ($stmtDoc !== false) {
    $rowDoc = sqlsrv_fetch_array($stmtDoc, SQLSRV_FETCH_ASSOC);
    if ($rowDoc && $rowDoc['MaxNo'] !== null) {
        $nextNo = (int)$rowDoc['MaxNo'] + 1;
    }
}
$trandoc = 'EX-' . $tahun . '-' . str_pad($nextNo, 5, '0', STR_PAD_LEFT);

// Insert ke Tabel TRANS (Header) - Disesuaikan tanpa kolom TBC yang belum ada di database
$sqlInsert = "
    INSERT INTO TRANS (TRAN_DATE, TRAN_ADATE, TRTY_CODE, TRAN_DOC, SUP_CODE, TRAN_SOURCE)
    VALUES (?, GETDATE(), ?, ?, ?, 1);
    SELECT SCOPE_IDENTITY() AS TRAN_ID;
";

$params = array(
    $trandate, 
    $trty, 
    $trandoc, 
    empty($merchant) ? null : $merchant
);

$stmt = sqlsrv_query($conn, $sqlInsert, $params);

if ($stmt === false) {
    // Tampilkan pesan error SQL Server jika masih ada kendala
    $errors = sqlsrv_errors();
    echo json_encode(['status' => 'error', 'message' => isset($errors[0]['message']) ? $errors[0]['message'] : 'Gagal insert database.']);
    exit;
}

sqlsrv_next_result($stmt);
$tranid = 0;
if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tranid = $row['TRAN_ID'];
}

echo json_encode([
    'status'  => 'success',
    'tranid'  => $tranid,
    'trandoc' => $trandoc
]);
?>
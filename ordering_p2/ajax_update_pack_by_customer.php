<?php
// ajax_update_pack_by_customer.php
ob_start();
require_once __DIR__ . "/../config/database_ordering.php";
ob_clean();

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($conn) || $conn === false) {
    echo json_encode(array("success" => false, "message" => "Koneksi database gagal atau request invalid."));
    exit();
}

$custCode  = isset($_POST["cust_code"]) ? trim($_POST["cust_code"]) : "";
$newPackId = isset($_POST["pack_id"]) ? (int)$_POST["pack_id"] : 0;
$tglAwal   = isset($_POST["tgl_awal"]) ? trim($_POST["tgl_awal"]) : "";
$tglAkhir  = isset($_POST["tgl_akhir"]) ? trim($_POST["tgl_akhir"]) : "";

if ($custCode === "" || $newPackId <= 0 || empty($tglAwal) || empty($tglAkhir)) {
    echo json_encode(array("success" => false, "message" => "Parameter Customer Code, Pack, atau Tanggal tidak lengkap."));
    exit();
}

$sqlPack = "SELECT PACK_CODE FROM [dbo].[PACK] WHERE PACK_ID = ?";
$stmtPack = sqlsrv_query($conn, $sqlPack, array($newPackId));
if ($stmtPack === false || !($pack = sqlsrv_fetch_array($stmtPack, SQLSRV_FETCH_ASSOC))) {
    echo json_encode(array("success" => false, "message" => "Data Kemasan Baru tidak ditemukan."));
    exit();
}
$packCode = substr(trim($pack["PACK_CODE"]), 0, 10);

$tglAwalFull  = $tglAwal . " 00:00:00";
$tglAkhirFull = $tglAkhir . " 23:59:59.997";

sqlsrv_begin_transaction($conn);

$sqlUpdate = "
    UPDATE DI_PART
    SET 
        DI_PART.PACK_ID = ?,
        DI_PART.DIPA_PACK = ?
    FROM DI_PART
    INNER JOIN DI ON DI_PART.DI_ID = DI.DI_ID
    INNER JOIN CUST ON DI.CUST_ID = CUST.CUST_ID
    WHERE CUST.CUST_CODE = ?
      AND DI.DI_DATE >= ?
      AND DI.DI_DATE <= ?
      AND (DI_PART.PACK_ID IS NULL OR DI_PART.PACK_ID <> ?)
";

$paramsUpdate = array($newPackId, $packCode, $custCode, $tglAwalFull, $tglAkhirFull, $newPackId);
$stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);

if ($stmtUpdate === false) {
    sqlsrv_rollback($conn);
    $errors = sqlsrv_errors();
    $errMsg = isset($errors[0]['message']) ? $errors[0]['message'] : 'Unknown Error';
    echo json_encode(array("success" => false, "message" => "Gagal update: " . $errMsg));
    exit();
}

$rowsAffected = sqlsrv_rows_affected($stmtUpdate);
sqlsrv_commit($conn);

if ($rowsAffected === 0) {
    echo json_encode(array("success" => false, "message" => "Tidak ada data yang diperbarui. Semua record sudah menggunakan pack tersebut."));
} else {
    echo json_encode(array("success" => true, "message" => "Berhasil memperbarui " . $rowsAffected . " record menjadi Pack: " . $packCode . "."));
}
exit();
?>
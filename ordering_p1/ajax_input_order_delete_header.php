<?php
require_once __DIR__ . "/../config/database_ordering.php";

header("Content-Type: application/json; charset=utf-8");

if ($conn === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Koneksi database gagal."
    ));
    exit();
}

function json_error($msg) {
    echo json_encode(array(
        "success" => false,
        "message" => $msg
    ));
    exit();
}

function post_value($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : "";
}

$ordr_id_raw = post_value("ORDR_ID");

/*
    ORDR_ID bisa minus.
*/
if ($ordr_id_raw === "") {
    json_error("ORDR_ID kosong.");
}

if (!is_numeric($ordr_id_raw)) {
    json_error("ORDR_ID tidak valid: " . $ordr_id_raw);
}

$ordr_id = intval($ordr_id_raw);

/*
    Cek header ada.
*/
$sqlHeader = "
    SET NOCOUNT ON;

    SELECT TOP 1
        ORDR_ID,
        ORDR_PO
    FROM dbo.ORDERS
    WHERE ORDR_ID = ?
";

$stmtHeader = sqlsrv_query($conn, $sqlHeader, array($ordr_id));

if ($stmtHeader === false) {
    json_error("Gagal cek header: " . print_r(sqlsrv_errors(), true));
}

$header = sqlsrv_fetch_array($stmtHeader, SQLSRV_FETCH_ASSOC);

if (!$header) {
    json_error("Header order tidak ditemukan.");
}

/*
    Tidak boleh hapus header kalau masih ada detail.
*/
$sqlCount = "
    SET NOCOUNT ON;

    SELECT COUNT(*) AS CNT
    FROM dbo.ORDR_PAR
    WHERE ORDR_ID = ?
";

$stmtCount = sqlsrv_query($conn, $sqlCount, array($ordr_id));

if ($stmtCount === false) {
    json_error("Gagal cek detail: " . print_r(sqlsrv_errors(), true));
}

$c = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
$totalDetail = intval($c["CNT"]);

if ($totalDetail > 0) {
    json_error(
        "Header tidak bisa dihapus karena masih ada detail.\n\n" .
        "ORDR_ID      : " . $ordr_id . "\n" .
        "Total detail : " . $totalDetail . "\n\n" .
        "Hapus detail dulu, baru hapus header."
    );
}

/*
    Hapus header.
*/
$sqlDelete = "
    DELETE FROM dbo.ORDERS
    WHERE ORDR_ID = ?
";

$stmtDelete = sqlsrv_query($conn, $sqlDelete, array($ordr_id));

if ($stmtDelete === false) {
    json_error("Hapus header gagal: " . print_r(sqlsrv_errors(), true));
}

echo json_encode(array(
    "success" => true,
    "message" => "Header order berhasil dihapus.",
    "ORDR_ID" => $ordr_id
));
?>
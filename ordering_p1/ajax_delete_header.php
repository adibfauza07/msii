<?php
require_once __DIR__ . "/../config/database_ordering.php";

header("Content-Type: application/json");

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

$di_id = intval(post_value("DI_ID"));

if ($di_id <= 0) {
    json_error("DI_ID kosong. Cari data DI dulu sebelum delete header.");
}

/*
    Cek header ada atau tidak.
*/
$sqlHeader = "
    SELECT TOP 1
        DI_ID,
        DI_NO,
        DI_DSNO,
        DI_INVNO
    FROM DI
    WHERE DI_ID = ?
";

$stmtHeader = sqlsrv_query($conn, $sqlHeader, array($di_id));

if ($stmtHeader === false) {
    json_error("Gagal cek header DI: " . print_r(sqlsrv_errors(), true));
}

$header = sqlsrv_fetch_array($stmtHeader, SQLSRV_FETCH_ASSOC);

if (!$header) {
    json_error("Header DI tidak ditemukan.");
}

$di_no    = trim($header["DI_NO"]);
$di_dsno  = trim($header["DI_DSNO"]);
$di_invno = trim($header["DI_INVNO"]);

/*
    Tidak boleh delete header kalau masih ada detail DI_PART.
*/
$sqlDetail = "
    SELECT COUNT(*) AS CNT
    FROM DI_PART
    WHERE DI_ID = ?
";

$stmtDetail = sqlsrv_query($conn, $sqlDetail, array($di_id));

if ($stmtDetail === false) {
    json_error("Gagal cek DI_PART: " . print_r(sqlsrv_errors(), true));
}

$rowDetail = sqlsrv_fetch_array($stmtDetail, SQLSRV_FETCH_ASSOC);
$detail_count = intval($rowDetail["CNT"]);

if ($detail_count > 0) {
    json_error(
        "Header tidak bisa dihapus karena masih ada detail DI_PART.\n\n" .
        "DI_ID : " . $di_id . "\n" .
        "DI_NO : " . $di_no . "\n" .
        "Total detail : " . $detail_count . "\n\n" .
        "Hapus detail per baris atau BATAL SEMUA DETAIL dulu."
    );
}

/*
    Pengaman tambahan:
    Tidak boleh delete kalau masih ada alokasi PO di DIPA_PAR.
*/
$sqlDipaPar = "
    SELECT COUNT(*) AS CNT
    FROM DIPA_PAR
    WHERE DI_ID = ?
";

$stmtDipaPar = sqlsrv_query($conn, $sqlDipaPar, array($di_id));

if ($stmtDipaPar === false) {
    json_error("Gagal cek DIPA_PAR: " . print_r(sqlsrv_errors(), true));
}

$rowDipaPar = sqlsrv_fetch_array($stmtDipaPar, SQLSRV_FETCH_ASSOC);
$dipa_par_count = intval($rowDipaPar["CNT"]);

if ($dipa_par_count > 0) {
    json_error(
        "Header tidak bisa dihapus karena masih ada data alokasi PO di DIPA_PAR.\n\n" .
        "DI_ID : " . $di_id . "\n" .
        "DI_NO : " . $di_no . "\n" .
        "Total alokasi : " . $dipa_par_count . "\n\n" .
        "Rollback PO / hapus detail dulu."
    );
}

sqlsrv_begin_transaction($conn);

try {

    $sqlDelete = "
        DELETE FROM DI
        WHERE DI_ID = ?
    ";

    $stmtDelete = sqlsrv_query($conn, $sqlDelete, array($di_id));

    if ($stmtDelete === false) {
        throw new Exception(print_r(sqlsrv_errors(), true));
    }

    $affected = sqlsrv_rows_affected($stmtDelete);

    if ($affected === 0) {
        throw new Exception("Tidak ada header yang terhapus.");
    }

    sqlsrv_commit($conn);

    echo json_encode(array(
        "success" => true,
        "message" =>
            "Header DI berhasil dihapus.\n\n" .
            "DI_ID : " . $di_id . "\n" .
            "DI_NO : " . $di_no . "\n" .
            "DS NO : " . $di_dsno . "\n" .
            "INV NO: " . $di_invno
    ));
    exit();

} catch (Exception $e) {

    sqlsrv_rollback($conn);

    echo json_encode(array(
        "success" => false,
        "message" => "Delete header gagal: " . $e->getMessage()
    ));
    exit();
}
?>
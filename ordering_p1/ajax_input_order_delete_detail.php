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
$ordp_lino   = intval(post_value("ORDP_LINO"));

/*
    ORDR_ID di database bisa minus, jadi jangan pakai <= 0.
*/
if ($ordr_id_raw === "") {
    json_error("ORDR_ID kosong.");
}

if (!is_numeric($ordr_id_raw)) {
    json_error("ORDR_ID tidak valid: " . $ordr_id_raw);
}

$ordr_id = intval($ordr_id_raw);

if ($ordp_lino <= 0) {
    json_error("ORDP_LINO tidak valid.");
}

/*
    Cek detail dan delivery.
*/
$sqlCheck = "
    SET NOCOUNT ON;

    SELECT TOP 1
        ORDR_ID,
        ORDP_LINO,
        ISNULL(ORDP_DQTY, 0) AS ORDP_DQTY
    FROM dbo.ORDR_PAR
    WHERE ORDR_ID = ?
      AND ORDP_LINO = ?
";

$stmtCheck = sqlsrv_query($conn, $sqlCheck, array($ordr_id, $ordp_lino));

if ($stmtCheck === false) {
    json_error("Gagal cek detail: " . print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);

if (!$row) {
    json_error("Detail tidak ditemukan. Line: " . $ordp_lino);
}

$deliveryQty = intval($row["ORDP_DQTY"]);

if ($deliveryQty > 0) {
    json_error(
        "Detail tidak bisa dihapus karena sudah ada DELIVERY.\n\n" .
        "Line     : " . $ordp_lino . "\n" .
        "Delivery : " . $deliveryQty
    );
}

/*
    Hapus detail.
*/
$sqlDelete = "
    DELETE FROM dbo.ORDR_PAR
    WHERE ORDR_ID = ?
      AND ORDP_LINO = ?
      AND ISNULL(ORDP_DQTY, 0) = 0
";

$stmtDelete = sqlsrv_query($conn, $sqlDelete, array($ordr_id, $ordp_lino));

if ($stmtDelete === false) {
    json_error("Hapus detail gagal: " . print_r(sqlsrv_errors(), true));
}

echo json_encode(array(
    "success" => true,
    "message" => "Detail line " . $ordp_lino . " berhasil dihapus.",
    "ORDR_ID" => $ordr_id,
    "ORDP_LINO" => $ordp_lino
));
?>
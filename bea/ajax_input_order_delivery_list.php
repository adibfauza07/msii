<?php
require_once __DIR__ . '/config/database.php';

header("Content-Type: application/json; charset=utf-8");

if ($conn === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Koneksi database gagal.",
        "rows" => array()
    ));
    exit();
}

function json_error($msg) {
    echo json_encode(array(
        "success" => false,
        "message" => $msg,
        "rows" => array()
    ));
    exit();
}

function post_value($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : "";
}

function safe_trim($value) {
    if ($value === null) {
        return "";
    }

    return trim((string)$value);
}

function safe_int($value) {
    if ($value === null || $value === "") {
        return 0;
    }

    return intval($value);
}

function date_out($value) {
    if ($value instanceof DateTime) {
        return $value->format("d-M-Y");
    }

    if ($value == "" || $value === null) {
        return "";
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return "";
    }

    return date("d-M-Y", $ts);
}

$ordr_id_raw = post_value("ORDR_ID");
$ordp_lino   = intval(post_value("ORDP_LINO"));

if ($ordr_id_raw === "") {
    json_error("ORDR_ID kosong.");
}

if (!is_numeric($ordr_id_raw)) {
    json_error("ORDR_ID tidak valid: " . $ordr_id_raw);
}

/*
    ORDR_ID bisa minus, jadi jangan pakai <= 0.
*/
$ordr_id = intval($ordr_id_raw);

if ($ordp_lino <= 0) {
    json_error("ORDP_LINO tidak valid.");
}

$sql = "
    SET NOCOUNT ON;

    SELECT
        DI.DI_DATE,
        DI.DI_NO,
        DIPA_PAR.QTY AS DELIVERY,
        CUST.CUST_CODE,
        CUST.CUST_COMP,
        ITEMS.ITEM_CODE,
        ITEMS.ITEM_NAME
    FROM dbo.DIPA_PAR
    INNER JOIN dbo.DI
        ON DIPA_PAR.DI_ID = DI.DI_ID
    INNER JOIN dbo.CUST
        ON DI.CUST_ID = CUST.CUST_ID
    INNER JOIN dbo.PRICE
        ON DIPA_PAR.PRICE_ID = PRICE.PRICE_ID
    INNER JOIN dbo.ITEMS
        ON PRICE.PART_ID = ITEMS.ITEM_ID
    WHERE DIPA_PAR.ORDR_ID = ?
      AND DIPA_PAR.ORDP_LINO = ?
    ORDER BY
        DI.DI_DATE,
        DI.DI_NO
";

$stmt = sqlsrv_query($conn, $sql, array(
    $ordr_id,
    $ordp_lino
));

if ($stmt === false) {
    json_error("Query list delivery gagal: " . print_r(sqlsrv_errors(), true));
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = array(
        "DI_DATE"   => date_out($r["DI_DATE"]),
        "DI_NO"     => safe_trim($r["DI_NO"]),
        "DELIVERY"  => safe_int($r["DELIVERY"]),
        "CUST_CODE" => safe_trim($r["CUST_CODE"]),
        "CUST_COMP" => safe_trim($r["CUST_COMP"]),
        "ITEM_CODE" => safe_trim($r["ITEM_CODE"]),
        "ITEM_NAME" => safe_trim($r["ITEM_NAME"])
    );
}

echo json_encode(array(
    "success" => true,
    "message" => "OK",
    "rows" => $rows
));
?>
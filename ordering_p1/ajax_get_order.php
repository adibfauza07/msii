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

function date_display($value) {
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

$ordr_po = post_value("ORDR_PO");

if ($ordr_po == "") {
    json_error("PO NUMBER belum diisi.");
}

$sqlHeader = "
    SELECT TOP 1
        O.ORDR_ID,
        O.CUST_ID,
        O.ORDR_PO,
        O.ORDR_DATE,
        ISNULL(O.ORDR_REM, '') AS ORDR_REM,
        ISNULL(O.ORDR_STAT, '') AS ORDR_STAT,
        ISNULL(O.ORDR_CURR, '') AS ORDR_CURR,
        ISNULL(O.ORDR_PENDING, 0) AS ORDR_PENDING,
        ISNULL(O.ORDR_CLOSE, 0) AS ORDR_CLOSE,
        ISNULL(O.ORDR_REPLACEMENT, 0) AS ORDR_REPLACEMENT,
        ISNULL(C.CUST_CODE, '') AS CUST_CODE,
        ISNULL(C.CUST_COMP, '') AS CUST_COMP
    FROM dbo.ORDERS AS O
    INNER JOIN dbo.CUST AS C
        ON O.CUST_ID = C.CUST_ID
    WHERE O.ORDR_PO LIKE ?
    ORDER BY O.ORDR_ID DESC
";

$stmtHeader = sqlsrv_query($conn, $sqlHeader, array("%" . $ordr_po . "%"));

if ($stmtHeader === false) {
    json_error("Query header order gagal: " . print_r(sqlsrv_errors(), true));
}

$h = sqlsrv_fetch_array($stmtHeader, SQLSRV_FETCH_ASSOC);

if (!$h) {
    json_error("Data order tidak ditemukan.");
}

$ordr_id = safe_int($h["ORDR_ID"]);

$sqlDetail = "
    SELECT
        OP.ORDR_ID,
        OP.ORDP_LINO,
        OP.PRICE_ID,
        ISNULL(OP.ORDP_QTY, 0) AS ORDP_QTY,
        ISNULL(OP.ORDP_DQTY, 0) AS ORDP_DQTY,
        ISNULL(OP.ORDP_BQTY, 0) AS ORDP_BQTY,
        ISNULL(OP.ORDP_DESC, '') AS ORDP_DESC,
        ISNULL(I.ITEM_CODE, '') AS ITEM_CODE,
        ISNULL(I.ITEM_NAME, '') AS ITEM_NAME
    FROM dbo.ORDR_PAR AS OP
    INNER JOIN dbo.PRICE AS P
        ON OP.PRICE_ID = P.PRICE_ID
    INNER JOIN dbo.ITEMS AS I
        ON P.PART_ID = I.ITEM_ID
    WHERE OP.ORDR_ID = ?
    ORDER BY OP.ORDP_LINO
";

$stmtDetail = sqlsrv_query($conn, $sqlDetail, array($ordr_id));

if ($stmtDetail === false) {
    json_error("Query detail order gagal: " . print_r(sqlsrv_errors(), true));
}

$details = array();

while ($d = sqlsrv_fetch_array($stmtDetail, SQLSRV_FETCH_ASSOC)) {
    $details[] = array(
        "ORDR_ID"   => safe_int($d["ORDR_ID"]),
        "ORDP_LINO" => safe_int($d["ORDP_LINO"]),
        "PRICE_ID"  => safe_int($d["PRICE_ID"]),
        "CODE"      => safe_trim($d["ITEM_CODE"]),
        "NAME"      => safe_trim($d["ITEM_NAME"]),
        "ORDP_QTY"  => safe_int($d["ORDP_QTY"]),
        "ORDP_DQTY" => safe_int($d["ORDP_DQTY"]),
        "ORDP_BQTY" => safe_int($d["ORDP_BQTY"]),
        "ORDP_DESC" => safe_trim($d["ORDP_DESC"])
    );
}

echo json_encode(array(
    "success" => true,
    "message" => "Data order ditemukan.",
    "header" => array(
        "ORDR_ID"           => $ordr_id,
        "CUST_ID"           => safe_int($h["CUST_ID"]),
        "ORDR_PO"           => safe_trim($h["ORDR_PO"]),
        "ORDR_DATE"         => date_display($h["ORDR_DATE"]),
        "ORDR_REM"          => safe_trim($h["ORDR_REM"]),
        "ORDR_STAT"         => safe_trim($h["ORDR_STAT"]),
        "ORDR_CURR"         => safe_trim($h["ORDR_CURR"]),
        "ORDR_PENDING"      => safe_int($h["ORDR_PENDING"]),
        "ORDR_CLOSE"        => safe_int($h["ORDR_CLOSE"]),
        "ORDR_REPLACEMENT"  => safe_int($h["ORDR_REPLACEMENT"]),
        "CUST_CODE"         => safe_trim($h["CUST_CODE"]),
        "CUST_COMP"         => safe_trim($h["CUST_COMP"])
    ),
    "details" => $details
));
?>
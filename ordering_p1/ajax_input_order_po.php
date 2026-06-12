<?php
require_once __DIR__ . "/../config/database_ordering.php";

header("Content-Type: application/json; charset=utf-8");

if ($conn === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Koneksi database gagal.",
        "rows" => array()
    ));
    exit();
}

function post_value($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : "";
}

function safe_trim($value) {
    return $value === null ? "" : trim((string)$value);
}

function date_out($value) {
    if ($value instanceof DateTime) {
        return $value->format("Y-m-d");
    }

    if ($value == "" || $value === null) {
        return "";
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return "";
    }

    return date("Y-m-d", $ts);
}

$q = post_value("q");

if ($q == "") {
    echo json_encode(array(
        "success" => true,
        "message" => "OK",
        "rows" => array()
    ));
    exit();
}

$like = "%" . $q . "%";

/*
    Jangan filter CUST_ID di sini.
    Tujuannya supaya bisa cari PO customer lain tanpa keluar form.
*/
$sql = "
    SET NOCOUNT ON;

    SELECT TOP 50
        O.ORDR_ID,
        O.CUST_ID,
        ISNULL(O.ORDR_PO, '') AS ORDR_PO,
        O.ORDR_DATE,
        ISNULL(O.ORDR_CURR, '') AS ORDR_CURR,
        ISNULL(O.ORDR_CLOSE, 0) AS ORDR_CLOSE,
        ISNULL(O.ORDR_REPLACEMENT, 0) AS ORDR_REPLACEMENT,
        ISNULL(C.CUST_CODE, '') AS CUST_CODE,
        ISNULL(C.CUST_COMP, '') AS CUST_COMP
    FROM dbo.ORDERS AS O
    INNER JOIN dbo.CUST AS C
        ON O.CUST_ID = C.CUST_ID
    WHERE
        ISNULL(O.ORDR_PO, '') LIKE ?
        OR ISNULL(C.CUST_CODE, '') LIKE ?
        OR ISNULL(C.CUST_COMP, '') LIKE ?
    ORDER BY
        O.ORDR_DATE DESC,
        O.ORDR_ID DESC
";

$params = array($like, $like, $like);

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Query PO gagal: " . print_r(sqlsrv_errors(), true),
        "rows" => array()
    ));
    exit();
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $ordrPo = safe_trim($r["ORDR_PO"]);

    if ($ordrPo == "") {
        continue;
    }

    $rows[] = array(
        "ORDR_ID"           => intval($r["ORDR_ID"]),
        "CUST_ID"           => intval($r["CUST_ID"]),
        "ORDR_PO"           => $ordrPo,
        "ORDR_DATE"         => date_out($r["ORDR_DATE"]),
        "ORDR_CURR"         => safe_trim($r["ORDR_CURR"]),
        "ORDR_CLOSE"        => intval($r["ORDR_CLOSE"]),
        "ORDR_REPLACEMENT"  => intval($r["ORDR_REPLACEMENT"]),
        "CUST_CODE"         => safe_trim($r["CUST_CODE"]),
        "CUST_COMP"         => safe_trim($r["CUST_COMP"])
    );
}

echo json_encode(array(
    "success" => true,
    "message" => "OK",
    "rows" => $rows
));
?>
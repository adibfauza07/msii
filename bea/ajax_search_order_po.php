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

function json_response($arr) {
    echo json_encode($arr);
    exit();
}

function post_value($name) {
    if (isset($_POST[$name])) {
        return trim($_POST[$name]);
    }

    if (isset($_GET[$name])) {
        return trim($_GET[$name]);
    }

    return "";
}

function safe_trim($value) {
    if ($value === null) {
        return "";
    }

    return trim((string)$value);
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

$q = post_value("q");

if ($q == "") {
    json_response(array(
        "success" => true,
        "message" => "OK",
        "rows" => array()
    ));
}

/*
    Batasi input supaya pencarian tidak terlalu berat.
*/
if (strlen($q) > 50) {
    $q = substr($q, 0, 50);
}

$like = "%" . $q . "%";

/*
    Autocomplete PO.
    Bisa cari berdasarkan:
    - PO Number
    - Customer Code
    - Customer Name
*/
$sql = "
    SET NOCOUNT ON;

    SELECT TOP 20
        O.ORDR_ID,
        ISNULL(O.ORDR_PO, '') AS ORDR_PO,
        O.ORDR_DATE,
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
    json_response(array(
        "success" => false,
        "message" => "Query cari PO gagal: " . print_r(sqlsrv_errors(), true),
        "rows" => array()
    ));
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $ordrPo = safe_trim($r["ORDR_PO"]);

    if ($ordrPo == "") {
        continue;
    }

    $rows[] = array(
        "ORDR_ID"   => intval($r["ORDR_ID"]),
        "ORDR_PO"   => $ordrPo,
        "ORDR_DATE" => date_display($r["ORDR_DATE"]),
        "CUST_CODE" => safe_trim($r["CUST_CODE"]),
        "CUST_COMP" => safe_trim($r["CUST_COMP"])
    );
}

json_response(array(
    "success" => true,
    "message" => "OK",
    "rows" => $rows
));
?>
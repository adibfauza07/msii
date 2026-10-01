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

function post_value($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : "";
}

function safe_trim($value) {
    return $value === null ? "" : trim((string)$value);
}

function date_sql($value) {
    if ($value == "") {
        return "";
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return "";
    }

    return date("Y-m-d", $ts);
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

$q         = post_value("q");
$ordr_date = date_sql(post_value("ORDR_DATE"));
$cust_id   = intval(post_value("CUST_ID"));

if ($q == "") {
    echo json_encode(array(
        "success" => true,
        "message" => "OK",
        "rows" => array()
    ));
    exit();
}

if ($cust_id <= 0) {
    echo json_encode(array(
        "success" => false,
        "message" => "Customer belum dipilih.",
        "rows" => array()
    ));
    exit();
}

if ($ordr_date == "") {
    echo json_encode(array(
        "success" => false,
        "message" => "ORDER DATE belum diisi.",
        "rows" => array()
    ));
    exit();
}

$like = "%" . $q . "%";

/*
    Filter detail by customer:
    PART_VIEW punya CUST_ID, PRICE_ID, PART_CODE, PART_NAME.
    Harga dari PRICE_DETAIL sesuai tanggal order.
*/
$sql = "
    SET NOCOUNT ON;

    SELECT TOP 30
        PV.PRICE_ID,
        ISNULL(PV.PART_CODE, '') AS PART_CODE,
        ISNULL(PV.PART_NAME, '') AS PART_NAME,
        ISNULL(PV.PART_NUM, '') AS PART_NUM,
        ISNULL(PD.PRDT_PRICE, 0) AS PRDT_PRICE,
        PD.PRDT_START,
        PD.PRDT_END
    FROM dbo.PART_VIEW AS PV
    INNER JOIN dbo.PRICE_DETAIL AS PD
        ON PV.PRICE_ID = PD.PRICE_ID
    WHERE
        PV.CUST_ID = ?
        AND (
            ISNULL(PV.PART_CODE, '') LIKE ?
            OR ISNULL(PV.PART_NAME, '') LIKE ?
            OR ISNULL(PV.PART_NUM, '') LIKE ?
        )
        AND ? BETWEEN PD.PRDT_START AND PD.PRDT_END
    ORDER BY
        PV.PART_CODE,
        PD.PRDT_START DESC
";

$params = array(
    $cust_id,
    $like,
    $like,
    $like,
    $ordr_date
);

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Query item gagal: " . print_r(sqlsrv_errors(), true),
        "rows" => array()
    ));
    exit();
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $partName = safe_trim($r["PART_NAME"]);
    $partNum  = safe_trim($r["PART_NUM"]);

    if ($partNum != "" && strpos($partName, $partNum) === false) {
        $partName = trim($partName . " " . $partNum);
    }

    $rows[] = array(
        "PRICE_ID"   => intval($r["PRICE_ID"]),
        "PART_CODE"  => safe_trim($r["PART_CODE"]),
        "PART_NAME"  => $partName,
        "PART_NUM"   => $partNum,
        "PRDT_PRICE" => number_format((float)$r["PRDT_PRICE"], 4, ".", ""),
        "PRDT_START" => date_out($r["PRDT_START"]),
        "PRDT_END"   => date_out($r["PRDT_END"])
    );
}

echo json_encode(array(
    "success" => true,
    "message" => "OK",
    "rows" => $rows
));
?>
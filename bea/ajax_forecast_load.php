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

function month_out($value) {
    if ($value instanceof DateTime) {
        return $value->format("Y-m");
    }

    if ($value == "" || $value === null) {
        return "";
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return "";
    }

    return date("Y-m", $ts);
}

$price_id = intval(post_value("PRICE_ID"));

if ($price_id <= 0) {
    echo json_encode(array(
        "success" => false,
        "message" => "PRICE_ID kosong.",
        "rows" => array()
    ));
    exit();
}

$sql = "
    SET NOCOUNT ON;

    SELECT
        PRICE_ID,
        FORE_MONTH,
        ISNULL(FORE_QTY, 0) AS FORE_QTY
    FROM dbo.FORECAST
    WHERE PRICE_ID = ?
      AND FORE_MONTH >= DATEADD(year, -2, GETDATE())
    ORDER BY FORE_MONTH
";


$stmt = sqlsrv_query($conn, $sql, array($price_id));

if ($stmt === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Query forecast gagal: " . print_r(sqlsrv_errors(), true),
        "rows" => array()
    ));
    exit();
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = array(
        "PRICE_ID"    => intval($r["PRICE_ID"]),
        "FORE_MONTH" => month_out($r["FORE_MONTH"]),
        "FORE_QTY"   => intval($r["FORE_QTY"])
    );
}

echo json_encode(array(
    "success" => true,
    "message" => "OK",
    "rows" => $rows
));
?>
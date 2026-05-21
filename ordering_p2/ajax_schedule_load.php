<?php
require_once __DIR__ . "/../config/db_plant2.php";

header("Content-Type: application/json; charset=utf-8");

if ($conn === false) {
    echo json_encode(array("success" => false, "message" => "Koneksi database gagal.", "rows" => array()));
    exit();
}

function post_value($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : "";
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

$price_id = intval(post_value("PRICE_ID"));

if ($price_id <= 0) {
    echo json_encode(array("success" => false, "message" => "PRICE_ID kosong.", "rows" => array()));
    exit();
}

$sql = "
    SET NOCOUNT ON;

    SELECT
        PRICE_ID,
        DELS_DATE,
        ISNULL(DELS_QTY, 0) AS DELS_QTY,
        ISNULL(DELS_C1, 0) AS DELS_C1,
        ISNULL(DELS_C2, 0) AS DELS_C2
    FROM dbo.DELI_SCH
    WHERE PRICE_ID = ?
      AND YEAR(DELS_DATE) BETWEEN YEAR(GETDATE()) - 1 AND YEAR(GETDATE()) + 1
    ORDER BY DELS_DATE
";

$stmt = sqlsrv_query($conn, $sql, array($price_id));

if ($stmt === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Query schedule gagal: " . print_r(sqlsrv_errors(), true),
        "rows" => array()
    ));
    exit();
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = array(
        "PRICE_ID"  => intval($r["PRICE_ID"]),
        "DELS_DATE" => date_out($r["DELS_DATE"]),
        "DELS_QTY"  => intval($r["DELS_QTY"]),
        "DELS_C1"   => intval($r["DELS_C1"]),
        "DELS_C2"   => intval($r["DELS_C2"])
    );
}

echo json_encode(array("success" => true, "message" => "OK", "rows" => $rows));
?>
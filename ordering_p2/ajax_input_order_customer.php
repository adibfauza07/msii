<?php
require_once __DIR__ . "/../config/db_plant2.php";

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

$sql = "
    SET NOCOUNT ON;

    SELECT TOP 30
        CUST_ID,
        ISNULL(CUST_CODE, '') AS CUST_CODE,
        ISNULL(CUST_COMP, '') AS CUST_COMP,
        ISNULL(CURR_CODE, '') AS CURR_CODE
    FROM dbo.CUST
    WHERE
        ISNULL(CUST_CODE, '') LIKE ?
        OR ISNULL(CUST_COMP, '') LIKE ?
    ORDER BY CUST_CODE
";

$stmt = sqlsrv_query($conn, $sql, array($like, $like));

if ($stmt === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Query customer gagal: " . print_r(sqlsrv_errors(), true),
        "rows" => array()
    ));
    exit();
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = array(
        "CUST_ID"   => intval($r["CUST_ID"]),
        "CUST_CODE" => safe_trim($r["CUST_CODE"]),
        "CUST_COMP" => safe_trim($r["CUST_COMP"]),
        "CURR_CODE" => safe_trim($r["CURR_CODE"])
    );
}

echo json_encode(array(
    "success" => true,
    "message" => "OK",
    "rows" => $rows
));
?>
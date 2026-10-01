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

$cust_id = intval(post_value("CUST_ID"));

if ($cust_id <= 0) {
    echo json_encode(array(
        "success" => false,
        "message" => "CUST_ID kosong.",
        "rows" => array()
    ));
    exit();
}

$sql = "
    SET NOCOUNT ON;

    WITH LatestPrice AS
    (
        SELECT
            p.PART_ID,
            p.CUST_ID,
            MAX(d.PRDT_END) AS Latest_PRDT_END
        FROM dbo.PRICE_DETAIL AS d
        INNER JOIN dbo.PRICE AS p
            ON d.PRICE_ID = p.PRICE_ID
        WHERE p.CUST_ID = ?
        GROUP BY
            p.PART_ID,
            p.CUST_ID
    )
    SELECT
        i.ITEM_ID AS PART_ID,
        ISNULL(i.ITEM_CODE, '') AS PART_CODE,
        ISNULL(p.PRICE_CODE, '') AS PRICE_CODE,
        ISNULL(i.ITEM_NO, '') AS PART_NO,
        ISNULL(i.ITEM_NAME, '') AS PART_NAME,
        p.CUST_ID,
        p.PRICE_ID,
        d.PRDT_START,
        d.PRDT_END
    FROM dbo.PRICE AS p
    INNER JOIN dbo.ITEMS AS i
        ON p.PART_ID = i.ITEM_ID
       AND ISNULL(i.ITEM_INACTIVE, 0) = 0
    INNER JOIN dbo.PRICE_DETAIL AS d
        ON d.PRICE_ID = p.PRICE_ID
    INNER JOIN LatestPrice AS lp
        ON lp.PART_ID = p.PART_ID
       AND lp.CUST_ID = p.CUST_ID
       AND lp.Latest_PRDT_END = d.PRDT_END
    WHERE p.CUST_ID = ?
    ORDER BY
        i.ITEM_CODE,
        p.PRICE_CODE
";

$stmt = sqlsrv_query($conn, $sql, array($cust_id, $cust_id));

if ($stmt === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Query item forecast gagal: " . print_r(sqlsrv_errors(), true),
        "rows" => array()
    ));
    exit();
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $partName = safe_trim($r["PART_NAME"]);
    $partNo   = safe_trim($r["PART_NO"]);

    $rows[] = array(
        "PART_ID"    => intval($r["PART_ID"]),
        "PART_CODE"  => safe_trim($r["PART_CODE"]),
        "PRICE_CODE" => safe_trim($r["PRICE_CODE"]),
        "PART_NO"    => $partNo,
        "PART_NAME"  => $partName,
        "CUST_ID"    => intval($r["CUST_ID"]),
        "PRICE_ID"   => intval($r["PRICE_ID"]),
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
<?php
require_once __DIR__ . "/../config/database_ordering.php";

header("Content-Type: application/json; charset=utf-8");

if ($conn === false) {
    echo json_encode(array("success" => false, "message" => "Koneksi database gagal.", "rows" => array()));
    exit();
}

function post_value($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : "";
}

function safe_trim($value) {
    return $value === null ? "" : trim((string)$value);
}

function safe_int($value) {
    if ($value === null || $value === "") {
        return 0;
    }

    return intval($value);
}

$cust_id = intval(post_value("CUST_ID"));

if ($cust_id <= 0) {
    echo json_encode(array("success" => false, "message" => "CUST_ID kosong.", "rows" => array()));
    exit();
}

$sql = "
    SET NOCOUNT ON;

    ;WITH DP_SUM AS
    (
        SELECT
            ORDR_ID,
            ORDP_LINO,
            SUM(QTY) AS DELIVERY_QTY
        FROM dbo.DIPA_PAR
        GROUP BY
            ORDR_ID,
            ORDP_LINO
    ),
    OP_BASE AS
    (
        SELECT
            OP.ORDR_ID,
            OP.ORDP_LINO,
            OP.PRICE_ID,
            OP.ORDP_BQTY,
            ISNULL(DP.DELIVERY_QTY, 0) AS DELIVERY_QTY
        FROM dbo.ORDR_PAR AS OP
        LEFT JOIN DP_SUM AS DP
            ON OP.ORDR_ID = DP.ORDR_ID
           AND OP.ORDP_LINO = DP.ORDP_LINO
        WHERE
            OP.ORDP_BQTY >= 0
            AND ISNULL(OP.ORDP_CLOSE, 0) = 0
    )
    SELECT
        P.PRICE_ID,
        P.CUST_ID,
        ISNULL(I.ITEM_CODE, '') AS ITEM_CODE,
        ISNULL(P.PRICE_CODE, '') AS PRICE_CODE,
        ISNULL(I.ITEM_NAME, '') AS ITEM_NAME,
        ISNULL(I.ITEM_NO, '') AS ITEM_NO,
        SUM(ISNULL(OP.DELIVERY_QTY, 0)) AS DELIVERY_QTY,
        SUM(ISNULL(OP.ORDP_BQTY, 0)) AS BAL2
    FROM OP_BASE AS OP
    INNER JOIN dbo.PRICE AS P
        ON OP.PRICE_ID = P.PRICE_ID
    INNER JOIN dbo.ITEMS AS I
        ON P.PART_ID = I.ITEM_ID
    WHERE P.CUST_ID = ?
    GROUP BY
        P.PRICE_ID,
        P.CUST_ID,
        I.ITEM_CODE,
        I.ITEM_NAME,
        I.ITEM_NO,
        P.PRICE_CODE
    ORDER BY
        I.ITEM_CODE
";

$stmt = sqlsrv_query($conn, $sql, array($cust_id));

if ($stmt === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Query item schedule gagal: " . print_r(sqlsrv_errors(), true),
        "rows" => array()
    ));
    exit();
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = array(
        "PRICE_ID"     => safe_int($r["PRICE_ID"]),
        "CUST_ID"      => safe_int($r["CUST_ID"]),
        "ITEM_CODE"    => safe_trim($r["ITEM_CODE"]),
        "PRICE_CODE"   => safe_trim($r["PRICE_CODE"]),
        "ITEM_NAME"    => safe_trim($r["ITEM_NAME"]),
        "ITEM_NO"      => safe_trim($r["ITEM_NO"]),
        "DELIVERY_QTY" => safe_int($r["DELIVERY_QTY"]),
        "BAL2"         => safe_int($r["BAL2"])
    );
}

echo json_encode(array("success" => true, "message" => "OK", "rows" => $rows));
?>
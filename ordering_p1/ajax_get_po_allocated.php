<?php
require_once __DIR__ . "/../config/database_ordering.php";

header("Content-Type: application/json");

if ($conn === false) {
    echo json_encode(array("success" => false, "message" => "Koneksi database gagal."));
    exit();
}

function post_value($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : "";
}

function date_show($value) {
    if ($value instanceof DateTime) {
        return $value->format("Y-m-d");
    }

    if ($value == "" || $value === null) {
        return "";
    }

    $ts = strtotime($value);
    return $ts === false ? "" : date("Y-m-d", $ts);
}

$di_id     = intval(post_value("DI_ID"));
$price_id  = intval(post_value("PRICE_ID"));
$dipa_lino = intval(post_value("DIPA_LINO"));

if ($di_id <= 0 || $price_id <= 0 || $dipa_lino <= 0) {
    echo json_encode(array(
        "success" => false,
        "message" => "Parameter DI_ID / PRICE_ID / DIPA_LINO kosong."
    ));
    exit();
}

$sql = "
    SELECT
        dp.DIPA_LINO,
        dp.DI_ID,
        dp.PRICE_ID,
        o.ORDR_PO,
        op.ORDP_QTY AS ORDR,
        op.ORDP_DQTY AS DELIVERY,
        op.ORDP_BQTY AS BALANCE,
        o.ORDR_DATE,
        op.ORDR_ID,
        op.ORDP_LINO,
        ISNULL(dp2.QTY, 0) AS QTY
    FROM dbo.DI_PART AS dp
    LEFT JOIN dbo.DIPA_PAR AS dp2
        ON dp.DI_ID = dp2.DI_ID
       AND dp.PRICE_ID = dp2.PRICE_ID
       AND dp.DIPA_LINO = dp2.DIPA_LINO
    LEFT JOIN dbo.ORDR_PAR AS op
        ON op.ORDR_ID = dp2.ORDR_ID
       AND op.ORDP_LINO = dp2.ORDP_LINO
    LEFT JOIN dbo.ORDERS AS o
        ON o.ORDR_ID = op.ORDR_ID
    WHERE dp.DI_ID = ?
      AND dp.PRICE_ID = ?
      AND dp.DIPA_LINO = ?
      AND dp2.ORDR_ID IS NOT NULL
    ORDER BY o.ORDR_DATE, op.ORDR_ID, op.ORDP_LINO
";

$params = array($di_id, $price_id, $dipa_lino);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Query PO allocated gagal: " . print_r(sqlsrv_errors(), true)
    ));
    exit();
}

$rows = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = array(
        "ORDR_DATE" => date_show($row["ORDR_DATE"]),
        "ORDR_PO"   => trim($row["ORDR_PO"]),
        "QTY"       => intval($row["QTY"]),
        "ORDR_ID"   => intval($row["ORDR_ID"]),
        "ORDP_LINO" => intval($row["ORDP_LINO"])
    );
}

echo json_encode(array(
    "success" => true,
    "rows" => $rows
));
?>
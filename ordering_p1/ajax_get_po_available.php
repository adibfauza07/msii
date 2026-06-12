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

$price_id = intval(post_value("PRICE_ID"));

if ($price_id <= 0) {
    echo json_encode(array("success" => false, "message" => "PRICE_ID kosong."));
    exit();
}

$sql = "
    SELECT
        O.ORDR_DATE,
        O.ORDR_PO,
        OP.ORDP_QTY AS QTY,
        OP.ORDP_DQTY AS DQTY,
        OP.ORDP_BQTY AS BQTY,
        OP.ORDR_ID,
        OP.ORDP_LINO
    FROM dbo.ORDR_PAR AS OP
    INNER JOIN dbo.ORDERS AS O
        ON O.ORDR_ID = OP.ORDR_ID
    WHERE OP.PRICE_ID = ?
      AND ISNULL(OP.ORDP_BQTY, 0) > 0
      AND ISNULL(OP.ORDP_CLOSE, 0) = 0
    ORDER BY
        O.ORDR_DATE,
        OP.ORDR_ID,
        OP.ORDP_LINO
";

$stmt = sqlsrv_query($conn, $sql, array($price_id));

if ($stmt === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Query PO available gagal: " . print_r(sqlsrv_errors(), true)
    ));
    exit();
}

$rows = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = array(
        "ORDR_DATE" => date_show($row["ORDR_DATE"]),
        "ORDR_PO"   => trim($row["ORDR_PO"]),
        "QTY"       => intval($row["QTY"]),
        "DQTY"      => intval($row["DQTY"]),
        "BQTY"      => intval($row["BQTY"]),
        "ORDR_ID"   => intval($row["ORDR_ID"]),
        "ORDP_LINO" => intval($row["ORDP_LINO"])
    );
}

echo json_encode(array(
    "success" => true,
    "rows" => $rows
));
?>
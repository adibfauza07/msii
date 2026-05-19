<?php
require_once __DIR__ . "/../config/db_plant2.php";

header("Content-Type: application/json");

if ($conn === false) {
    echo json_encode(array(
        "success" => false,
        "valid" => false,
        "message" => "Koneksi database gagal."
    ));
    exit();
}

function json_error($msg) {
    echo json_encode(array(
        "success" => false,
        "valid" => false,
        "message" => $msg
    ));
    exit();
}

function post_value($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : "";
}

$di_id = intval(post_value("DI_ID"));

if ($di_id <= 0) {
    json_error("DI_ID kosong. Cari DI atau Save Header dulu.");
}

$sql = "
    SELECT
        DP.DIPA_LINO,
        ISNULL(I.ITEM_CODE, ISNULL(DP.PART_CODE, '')) AS CODE,
        ISNULL(I.ITEM_NAME, '') AS NAME,
        ISNULL(DP.DIPA_QTY, 0) AS SCHEDULE_QTY,
        ISNULL(SUM(DP2.QTY), 0) AS ALLOCATED_QTY,
        ISNULL(DP.DIPA_QTY, 0) - ISNULL(SUM(DP2.QTY), 0) AS SELISIH_QTY
    FROM DI_PART DP
    LEFT JOIN DIPA_PAR DP2
        ON DP.DI_ID = DP2.DI_ID
       AND DP.DIPA_LINO = DP2.DIPA_LINO
       AND DP.PRICE_ID = DP2.PRICE_ID
    LEFT JOIN PRICE P
        ON DP.PRICE_ID = P.PRICE_ID
    LEFT JOIN ITEMS I
        ON P.PART_ID = I.ITEM_ID
    WHERE DP.DI_ID = ?
    GROUP BY
        DP.DIPA_LINO,
        DP.PART_CODE,
        I.ITEM_CODE,
        I.ITEM_NAME,
        DP.DIPA_QTY
    ORDER BY
        DP.DIPA_LINO
";

$stmt = sqlsrv_query($conn, $sql, array($di_id));

if ($stmt === false) {
    json_error("Query validasi gagal: " . print_r(sqlsrv_errors(), true));
}

$rows = array();
$mismatch = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $scheduleQty  = intval($row["SCHEDULE_QTY"]);
    $allocatedQty = intval($row["ALLOCATED_QTY"]);
    $selisihQty   = intval($row["SELISIH_QTY"]);

    $item = array(
        "DIPA_LINO"     => intval($row["DIPA_LINO"]),
        "CODE"          => trim($row["CODE"]),
        "NAME"          => trim($row["NAME"]),
        "SCHEDULE_QTY"  => $scheduleQty,
        "ALLOCATED_QTY" => $allocatedQty,
        "SELISIH_QTY"   => $selisihQty
    );

    $rows[] = $item;

    if ($scheduleQty != $allocatedQty) {
        $mismatch[] = $item;
    }
}

if (count($rows) == 0) {
    echo json_encode(array(
        "success" => true,
        "valid" => false,
        "message" => "Detail DI_PART belum ada.",
        "rows" => array(),
        "mismatch" => array()
    ));
    exit();
}

if (count($mismatch) > 0) {
    echo json_encode(array(
        "success" => true,
        "valid" => false,
        "message" => "QTY DI_PART dan PO Allocated belum sama.",
        "rows" => $rows,
        "mismatch" => $mismatch
    ));
    exit();
}

echo json_encode(array(
    "success" => true,
    "valid" => true,
    "message" => "Validasi OK. Semua QTY sudah sama.",
    "rows" => $rows,
    "mismatch" => array()
));
exit();
?>
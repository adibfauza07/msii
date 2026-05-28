<?php
if (session_id() == "") {
    session_start();
}

require_once dirname(__DIR__) . "/config/db_plant2.php";

header("Content-Type: application/json; charset=utf-8");

if ($conn === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Koneksi database gagal.",
        "rows" => array()
    ));
    exit;
}

$sql = "
    SELECT
        id,
        ISNULL(part_code, '') AS part_code,
        ISNULL(part_no, '') AS part_no,
        ISNULL(part_name, '') AS part_name,
        ISNULL(qty_polibag, 0) AS qty_polibag,
        ISNULL(qty_box, 0) AS qty_box
    FROM data_barcode_showa
    WHERE
        ISNULL(part_code, '') <> ''
        OR ISNULL(part_no, '') <> ''
        OR ISNULL(part_name, '') <> ''
    ORDER BY
        part_code,
        part_no,
        part_name
";

$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Query gagal.",
        "detail" => sqlsrv_errors(),
        "rows" => array()
    ));
    exit;
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = array(
        "id"          => (int)$r["id"],
        "part_code"   => trim((string)$r["part_code"]),
        "part_no"     => trim((string)$r["part_no"]),
        "part_name"   => trim((string)$r["part_name"]),
        "qty_polibag" => (int)$r["qty_polibag"],
        "qty_box"     => (int)$r["qty_box"]
    );
}

echo json_encode(array(
    "success" => true,
    "rows" => $rows
));
exit;
?>
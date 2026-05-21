<?php
require_once __DIR__ . "/../config/db_plant2.php";

header("Content-Type: application/json; charset=utf-8");

if ($conn === false) {
    echo json_encode(array("success" => false, "message" => "Koneksi database gagal."));
    exit();
}

function json_error($msg) {
    echo json_encode(array("success" => false, "message" => $msg));
    exit();
}

function post_value($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : "";
}

function date_sql($value) {
    $value = trim($value);

    if ($value == "") {
        return "";
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return "";
    }

    return date("Y-m-d", $ts);
}

$price_id = intval(post_value("PRICE_ID"));
$date     = date_sql(post_value("DELS_DATE"));

if ($price_id <= 0) {
    json_error("PRICE_ID kosong.");
}

if ($date == "") {
    json_error("DELS_DATE tidak valid.");
}

$sql = "
    SET NOCOUNT ON;

    DELETE FROM dbo.DELI_SCH
    WHERE PRICE_ID = ?
      AND DELS_DATE >= CONVERT(datetime, ?)
      AND DELS_DATE < DATEADD(day, 1, CONVERT(datetime, ?))
";

$stmt = sqlsrv_query($conn, $sql, array($price_id, $date, $date));

if ($stmt === false) {
    json_error("Hapus schedule gagal: " . print_r(sqlsrv_errors(), true));
}

echo json_encode(array(
    "success" => true,
    "message" => "Schedule date " . $date . " berhasil dihapus."
));
?>
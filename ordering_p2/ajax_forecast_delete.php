<?php
require_once __DIR__ . "/../config/db_plant2.php";

header("Content-Type: application/json; charset=utf-8");

if ($conn === false) {
    echo json_encode(array(
        "success" => false,
        "message" => "Koneksi database gagal."
    ));
    exit();
}

function json_error($msg) {
    echo json_encode(array(
        "success" => false,
        "message" => $msg
    ));
    exit();
}

function post_value($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : "";
}

function month_start($value) {
    $value = trim($value);

    if ($value == "") {
        return "";
    }

    if (strlen($value) == 7) {
        $value .= "-01";
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return "";
    }

    return date("Y-m-01", $ts);
}

$price_id = intval(post_value("PRICE_ID"));
$month    = month_start(post_value("FORE_MONTH"));

if ($price_id <= 0) {
    json_error("PRICE_ID kosong.");
}

if ($month == "") {
    json_error("FORE_MONTH tidak valid.");
}

$sql = "
    SET NOCOUNT ON;

    DELETE FROM dbo.FORECAST
    WHERE PRICE_ID = ?
      AND FORE_MONTH >= CONVERT(datetime, ?)
      AND FORE_MONTH < DATEADD(month, 1, CONVERT(datetime, ?))
";

$stmt = sqlsrv_query($conn, $sql, array(
    $price_id,
    $month,
    $month
));

if ($stmt === false) {
    json_error("Hapus forecast gagal: " . print_r(sqlsrv_errors(), true));
}

echo json_encode(array(
    "success" => true,
    "message" => "Forecast month " . $month . " berhasil dihapus."
));
?>
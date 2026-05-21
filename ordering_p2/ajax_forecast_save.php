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

$price_id  = intval(post_value("PRICE_ID"));
$rows_json = post_value("ROWS_JSON");

if ($price_id <= 0) {
    json_error("PRICE_ID kosong.");
}

if ($rows_json == "") {
    json_error("Data forecast kosong.");
}

$rows = json_decode($rows_json, true);

if (!is_array($rows) || count($rows) == 0) {
    json_error("Format data forecast tidak valid.");
}

$monthCheck = array();

for ($i = 0; $i < count($rows); $i++) {
    $m = isset($rows[$i]["FORE_MONTH"]) ? month_start($rows[$i]["FORE_MONTH"]) : "";
    $q = isset($rows[$i]["FORE_QTY"]) ? intval($rows[$i]["FORE_QTY"]) : 0;

    if ($m == "") {
        json_error("FORE_MONTH tidak valid.");
    }

    if ($q < 0) {
        json_error("FORE_QTY tidak boleh minus. Month: " . $m);
    }

    if (isset($monthCheck[$m])) {
        json_error("FORE_MONTH duplicate: " . $m);
    }

    $monthCheck[$m] = true;
    $rows[$i]["FORE_MONTH_SQL"] = $m;
    $rows[$i]["FORE_QTY_SQL"] = $q;
}

if (!sqlsrv_begin_transaction($conn)) {
    json_error("Gagal mulai transaksi: " . print_r(sqlsrv_errors(), true));
}

try {
    $saved = 0;

    for ($i = 0; $i < count($rows); $i++) {
        $month = $rows[$i]["FORE_MONTH_SQL"];
        $qty   = $rows[$i]["FORE_QTY_SQL"];

        $sql = "
            SET NOCOUNT ON;

            IF EXISTS
            (
                SELECT 1
                FROM dbo.FORECAST
                WHERE PRICE_ID = ?
                  AND FORE_MONTH >= CONVERT(datetime, ?)
                  AND FORE_MONTH < DATEADD(month, 1, CONVERT(datetime, ?))
            )
            BEGIN
                UPDATE dbo.FORECAST
                SET FORE_QTY = ?
                WHERE PRICE_ID = ?
                  AND FORE_MONTH >= CONVERT(datetime, ?)
                  AND FORE_MONTH < DATEADD(month, 1, CONVERT(datetime, ?))
            END
            ELSE
            BEGIN
                INSERT INTO dbo.FORECAST
                (
                    PRICE_ID,
                    FORE_MONTH,
                    FORE_QTY
                )
                VALUES
                (
                    ?, CONVERT(datetime, ?), ?
                )
            END
        ";

        $params = array(
            $price_id,
            $month,
            $month,

            $qty,
            $price_id,
            $month,
            $month,

            $price_id,
            $month,
            $qty
        );

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            throw new Exception("Simpan forecast gagal: " . print_r(sqlsrv_errors(), true));
        }

        $saved++;
    }

    sqlsrv_commit($conn);

    echo json_encode(array(
        "success" => true,
        "message" => "Forecast berhasil disimpan. Total: " . $saved,
        "saved" => $saved
    ));
    exit();

} catch (Exception $e) {
    sqlsrv_rollback($conn);

    echo json_encode(array(
        "success" => false,
        "message" => $e->getMessage()
    ));
    exit();
}
?>
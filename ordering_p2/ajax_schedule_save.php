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

$price_id  = intval(post_value("PRICE_ID"));
$rows_json = post_value("ROWS_JSON");

if ($price_id <= 0) {
    json_error("PRICE_ID kosong.");
}

if ($rows_json == "") {
    json_error("Data schedule kosong.");
}

$rows = json_decode($rows_json, true);

if (!is_array($rows) || count($rows) == 0) {
    json_error("Format data schedule tidak valid.");
}

$dateCheck = array();

for ($i = 0; $i < count($rows); $i++) {
    $d  = isset($rows[$i]["DELS_DATE"]) ? date_sql($rows[$i]["DELS_DATE"]) : "";
    $q  = isset($rows[$i]["DELS_QTY"]) ? intval($rows[$i]["DELS_QTY"]) : 0;
    $c1 = isset($rows[$i]["DELS_C1"]) ? intval($rows[$i]["DELS_C1"]) : 0;
    $c2 = isset($rows[$i]["DELS_C2"]) ? intval($rows[$i]["DELS_C2"]) : 0;

    if ($d == "") {
        json_error("DELS_DATE tidak valid.");
    }

    if ($q < 0) {
        json_error("DELS_QTY tidak boleh minus. Date: " . $d);
    }

    if ($c1 < 0) {
        json_error("DELS_C1 tidak boleh minus. Date: " . $d);
    }

    if ($c2 < 0) {
        json_error("DELS_C2 tidak boleh minus. Date: " . $d);
    }

    if (isset($dateCheck[$d])) {
        json_error("DELS_DATE duplicate: " . $d);
    }

    $dateCheck[$d] = true;

    $rows[$i]["DELS_DATE_SQL"] = $d;
    $rows[$i]["DELS_QTY_SQL"]  = $q;
    $rows[$i]["DELS_C1_SQL"]   = $c1;
    $rows[$i]["DELS_C2_SQL"]   = $c2;
}

if (!sqlsrv_begin_transaction($conn)) {
    json_error("Gagal mulai transaksi: " . print_r(sqlsrv_errors(), true));
}

try {
    $saved = 0;

    for ($i = 0; $i < count($rows); $i++) {
        $date = $rows[$i]["DELS_DATE_SQL"];
        $qty  = $rows[$i]["DELS_QTY_SQL"];
        $c1   = $rows[$i]["DELS_C1_SQL"];
        $c2   = $rows[$i]["DELS_C2_SQL"];

        $sql = "
            SET NOCOUNT ON;

            IF EXISTS
            (
                SELECT 1
                FROM dbo.DELI_SCH
                WHERE PRICE_ID = ?
                  AND DELS_DATE >= CONVERT(datetime, ?)
                  AND DELS_DATE < DATEADD(day, 1, CONVERT(datetime, ?))
            )
            BEGIN
                UPDATE dbo.DELI_SCH
                SET
                    DELS_QTY = ?,
                    DELS_C1 = ?,
                    DELS_C2 = ?
                WHERE PRICE_ID = ?
                  AND DELS_DATE >= CONVERT(datetime, ?)
                  AND DELS_DATE < DATEADD(day, 1, CONVERT(datetime, ?))
            END
            ELSE
            BEGIN
                INSERT INTO dbo.DELI_SCH
                (
                    PRICE_ID,
                    DELS_DATE,
                    DELS_QTY,
                    DELS_C1,
                    DELS_C2
                )
                VALUES
                (
                    ?, CONVERT(datetime, ?), ?, ?, ?
                )
            END
        ";

        $params = array(
            $price_id,
            $date,
            $date,

            $qty,
            $c1,
            $c2,
            $price_id,
            $date,
            $date,

            $price_id,
            $date,
            $qty,
            $c1,
            $c2
        );

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            throw new Exception("Simpan schedule gagal: " . print_r(sqlsrv_errors(), true));
        }

        $saved++;
    }

    sqlsrv_commit($conn);

    echo json_encode(array(
        "success" => true,
        "message" => "Schedule berhasil disimpan. Total: " . $saved,
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
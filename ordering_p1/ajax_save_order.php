<?php
require_once __DIR__ . "/../config/database_ordering.php";

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

$ordr_id_raw = post_value("ORDR_ID");
$rows_json   = post_value("ROWS_JSON");

/*
    PENTING:
    ORDR_ID di database bisa bernilai minus.
    Jadi jangan validasi pakai <= 0.
*/
if ($ordr_id_raw === "") {
    json_error("ORDR_ID kosong.");
}

if (!is_numeric($ordr_id_raw)) {
    json_error("ORDR_ID tidak valid: " . $ordr_id_raw);
}

$ordr_id = intval($ordr_id_raw);

if ($rows_json == "") {
    json_error("Data detail kosong.");
}

$rows = json_decode($rows_json, true);

if (!is_array($rows)) {
    json_error("Format ROWS_JSON tidak valid.");
}

/*
    Cek order header valid.
*/
$sqlCheckHeader = "
    SELECT TOP 1
        ORDR_ID
    FROM dbo.ORDERS
    WHERE ORDR_ID = ?
";

$stmtCheckHeader = sqlsrv_query($conn, $sqlCheckHeader, array($ordr_id));

if ($stmtCheckHeader === false) {
    json_error("Gagal cek order header: " . print_r(sqlsrv_errors(), true));
}

if (!sqlsrv_fetch_array($stmtCheckHeader, SQLSRV_FETCH_ASSOC)) {
    json_error("Order header tidak ditemukan. ORDR_ID: " . $ordr_id);
}

/*
    Validasi semua line sebelum update.
*/
for ($i = 0; $i < count($rows); $i++) {
    $line = $rows[$i];

    $lino        = isset($line["ORDP_LINO"]) ? intval($line["ORDP_LINO"]) : 0;
    $orderQty    = isset($line["ORDP_QTY"]) ? intval($line["ORDP_QTY"]) : 0;
    $deliveryQty = isset($line["ORDP_DQTY"]) ? intval($line["ORDP_DQTY"]) : 0;
    $balanceQty  = isset($line["ORDP_BQTY"]) ? intval($line["ORDP_BQTY"]) : 0;

    if ($lino <= 0) {
        json_error("Line number tidak valid.");
    }

    if ($orderQty < 0) {
        json_error("ORDER tidak boleh minus. Line: " . $lino);
    }

    if ($deliveryQty < 0) {
        json_error("DELIVERY tidak boleh minus. Line: " . $lino);
    }

    if ($balanceQty < 0) {
        json_error("BALANCE tidak boleh minus. Line: " . $lino);
    }

    if ($orderQty < $deliveryQty) {
        json_error(
            "ORDER tidak boleh lebih kecil dari DELIVERY.\n\n" .
            "Line     : " . $lino . "\n" .
            "ORDER    : " . $orderQty . "\n" .
            "DELIVERY : " . $deliveryQty
        );
    }

    $sqlLine = "
        SELECT TOP 1
            ORDR_ID,
            ORDP_LINO
        FROM dbo.ORDR_PAR
        WHERE ORDR_ID = ?
          AND ORDP_LINO = ?
    ";

    $stmtLine = sqlsrv_query($conn, $sqlLine, array($ordr_id, $lino));

    if ($stmtLine === false) {
        json_error("Gagal cek line " . $lino . ": " . print_r(sqlsrv_errors(), true));
    }

    if (!sqlsrv_fetch_array($stmtLine, SQLSRV_FETCH_ASSOC)) {
        json_error("Line order tidak ditemukan. Line: " . $lino);
    }
}

/*
    Mulai transaksi update.
*/
if (!sqlsrv_begin_transaction($conn)) {
    json_error("Gagal mulai transaksi: " . print_r(sqlsrv_errors(), true));
}

try {
    $updated = 0;

    for ($i = 0; $i < count($rows); $i++) {
        $line = $rows[$i];

        $lino        = intval($line["ORDP_LINO"]);
        $orderQty    = intval($line["ORDP_QTY"]);
        $deliveryQty = intval($line["ORDP_DQTY"]);
        $balanceQty  = intval($line["ORDP_BQTY"]);
        $desc        = isset($line["ORDP_DESC"]) ? trim($line["ORDP_DESC"]) : "";

        $sqlUpdate = "
            UPDATE dbo.ORDR_PAR
            SET
                ORDP_QTY  = ?,
                ORDP_DQTY = ?,
                ORDP_BQTY = ?,
                ORDP_DESC = ?
            WHERE ORDR_ID = ?
              AND ORDP_LINO = ?
        ";

        $paramsUpdate = array(
            $orderQty,
            $deliveryQty,
            $balanceQty,
            $desc,
            $ordr_id,
            $lino
        );

        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);

        if ($stmtUpdate === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        $updated++;
    }

    sqlsrv_commit($conn);

    echo json_encode(array(
        "success" => true,
        "message" => "Order berhasil disimpan. Total line update: " . $updated,
        "updated" => $updated,
        "ORDR_ID" => $ordr_id
    ));
    exit();

} catch (Exception $e) {
    sqlsrv_rollback($conn);

    echo json_encode(array(
        "success" => false,
        "message" => "Simpan order gagal: " . $e->getMessage()
    ));
    exit();
}
?>
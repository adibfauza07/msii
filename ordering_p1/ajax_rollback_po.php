<?php
require_once __DIR__ . "/../config/database_ordering.php";

header("Content-Type: application/json");

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

$di_id      = intval(post_value("DI_ID"));
$dipa_lino  = intval(post_value("DIPA_LINO"));
$price_id   = intval(post_value("PRICE_ID"));

/*
    PENTING:
    ORDR_ID jangan intval.
    Nilainya bisa minus / besar, misalnya -1999754168.
*/
$ordr_id    = post_value("ORDR_ID");
$ordp_lino  = post_value("ORDP_LINO");

if ($di_id <= 0) {
    json_error("DI_ID kosong.");
}

if ($dipa_lino <= 0) {
    json_error("DIPA_LINO kosong.");
}

if ($price_id <= 0) {
    json_error("PRICE_ID kosong.");
}

if ($ordr_id == "") {
    json_error("ORDR_ID kosong.");
}

if ($ordp_lino == "") {
    json_error("ORDP_LINO kosong.");
}

/*
    Ambil total QTY allocated dari DIPA_PAR.
    Pakai SUM supaya aman kalau ternyata ada lebih dari 1 record.
*/
$sqlDipa = "
    SELECT ISNULL(SUM(QTY), 0) AS QTY
    FROM DIPA_PAR
    WHERE DI_ID = ?
      AND DIPA_LINO = ?
      AND PRICE_ID = ?
      AND ORDR_ID = ?
      AND ORDP_LINO = ?
";

$paramsDipa = array(
    $di_id,
    $dipa_lino,
    $price_id,
    $ordr_id,
    $ordp_lino
);

$stmtDipa = sqlsrv_query($conn, $sqlDipa, $paramsDipa);

if ($stmtDipa === false) {
    json_error("Gagal cek DIPA_PAR: " . print_r(sqlsrv_errors(), true));
}

$rowDipa = sqlsrv_fetch_array($stmtDipa, SQLSRV_FETCH_ASSOC);

if (!$rowDipa) {
    json_error("Data PO allocated tidak ditemukan.");
}

$rollback_qty = intval($rowDipa["QTY"]);

if ($rollback_qty <= 0) {
    json_error("QTY rollback kosong.");
}

sqlsrv_begin_transaction($conn);

try {

    /*
        Hapus alokasi dari DIPA_PAR.
    */
    $sqlDelete = "
        DELETE FROM DIPA_PAR
        WHERE DI_ID = ?
          AND DIPA_LINO = ?
          AND PRICE_ID = ?
          AND ORDR_ID = ?
          AND ORDP_LINO = ?
    ";

    $paramsDelete = array(
        $di_id,
        $dipa_lino,
        $price_id,
        $ordr_id,
        $ordp_lino
    );

    $stmtDelete = sqlsrv_query($conn, $sqlDelete, $paramsDelete);

    if ($stmtDelete === false) {
        throw new Exception(
            "Gagal delete DIPA_PAR: " .
            print_r(sqlsrv_errors(), true)
        );
    }

    /*
        Kembalikan ORDR_PAR:
        ORDP_DQTY berkurang
        ORDP_BQTY bertambah
    */
    $sqlUpdatePO = "
        UPDATE ORDR_PAR
        SET
            ORDP_DQTY =
                CASE
                    WHEN ISNULL(ORDP_DQTY, 0) - ? < 0 THEN 0
                    ELSE ISNULL(ORDP_DQTY, 0) - ?
                END,
            ORDP_BQTY = ISNULL(ORDP_BQTY, 0) + ?
        WHERE ORDR_ID = ?
          AND ORDP_LINO = ?
          AND PRICE_ID = ?
    ";

    $paramsUpdatePO = array(
        $rollback_qty,
        $rollback_qty,
        $rollback_qty,
        $ordr_id,
        $ordp_lino,
        $price_id
    );

    $stmtUpdatePO = sqlsrv_query($conn, $sqlUpdatePO, $paramsUpdatePO);

    if ($stmtUpdatePO === false) {
        throw new Exception(
            "Gagal update ORDR_PAR: " .
            print_r(sqlsrv_errors(), true)
        );
    }

    sqlsrv_commit($conn);

    echo json_encode(array(
        "success" => true,
        "message" => "ROLLBACK PO berhasil. Qty: " . $rollback_qty,
        "qty" => $rollback_qty,
        "ORDR_ID" => $ordr_id,
        "ORDP_LINO" => $ordp_lino
    ));
    exit();

} catch (Exception $e) {

    sqlsrv_rollback($conn);

    echo json_encode(array(
        "success" => false,
        "message" => "ROLLBACK PO gagal: " . $e->getMessage()
    ));
    exit();
}
?>
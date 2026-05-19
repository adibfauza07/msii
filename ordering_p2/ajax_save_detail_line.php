<?php
require_once __DIR__ . "/../config/db_plant2.php";

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

function get_item_by_price_id($conn, $price_id) {
    $sql = "
        SELECT TOP 1
            ISNULL(I.ITEM_CODE, '') AS ITEM_CODE,
            ISNULL(I.ITEM_NAME, '') AS ITEM_NAME
        FROM PRICE P
        LEFT JOIN ITEMS I
            ON I.ITEM_ID = P.PART_ID
        WHERE P.PRICE_ID = ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array($price_id));

    if ($stmt === false) {
        return array(
            "ITEM_CODE" => "",
            "ITEM_NAME" => ""
        );
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$row) {
        return array(
            "ITEM_CODE" => "",
            "ITEM_NAME" => ""
        );
    }

    return array(
        "ITEM_CODE" => trim($row["ITEM_CODE"]),
        "ITEM_NAME" => trim($row["ITEM_NAME"])
    );
}

$di_id      = intval(post_value("DI_ID"));
$dipa_lino  = intval(post_value("DIPA_LINO"));
$part_code  = post_value("CODE");
$dipa_qty   = intval(post_value("DIPA_QTY"));
$dipa_pqty  = intval(post_value("DIPA_PQTY"));
$pack_desc  = post_value("PACK_DESC");
$location   = post_value("LOCATION");
$price_id   = intval(post_value("PRICE_ID"));
$pack_id    = intval(post_value("PACK_ID"));

if ($di_id <= 0) {
    json_error("DI_ID kosong. Save Header dulu.");
}

if ($dipa_lino <= 0) {
    json_error("DIPA_LINO kosong.");
}

if ($price_id <= 0) {
    json_error("PRICE_ID kosong pada line " . $dipa_lino);
}

/*
    Kalau CODE kosong, ambil otomatis dari PRICE -> ITEMS.
*/
$itemInfo = get_item_by_price_id($conn, $price_id);

if ($part_code == "") {
    $part_code = $itemInfo["ITEM_CODE"];
}

/*
    Kalau PACK_ID kosong tapi PACK_DESC ada, cari PACK_ID dari PACK_CODE.
*/
if ($pack_id <= 0 && $pack_desc != "") {
    $sqlPack = "
        SELECT TOP 1 PACK_ID
        FROM PACK
        WHERE PACK_CODE = ?
    ";

    $stmtPack = sqlsrv_query($conn, $sqlPack, array($pack_desc));

    if ($stmtPack !== false) {
        $rowPack = sqlsrv_fetch_array($stmtPack, SQLSRV_FETCH_ASSOC);

        if ($rowPack) {
            $pack_id = intval($rowPack["PACK_ID"]);
        }
    }
}

/*
    Default PACK supaya tidak kosong.
*/
if ($pack_desc == "") {
    $pack_desc = "BB";
}

if ($pack_id <= 0) {
    $pack_id = 1;
}

if ($dipa_pqty <= 0) {
    $dipa_pqty = 1;
}

/*
    Cek DI_ID valid.
*/
$sqlCheckDI = "
    SELECT TOP 1 DI_ID
    FROM DI
    WHERE DI_ID = ?
";

$stmtCheckDI = sqlsrv_query($conn, $sqlCheckDI, array($di_id));

if ($stmtCheckDI === false) {
    json_error("Gagal cek DI: " . print_r(sqlsrv_errors(), true));
}

if (!sqlsrv_fetch_array($stmtCheckDI, SQLSRV_FETCH_ASSOC)) {
    json_error("DI_ID tidak ditemukan.");
}

/*
    Cek apakah line sudah ada.
*/
$sqlCheck = "
    SELECT COUNT(*) AS CNT
    FROM DI_PART
    WHERE DI_ID = ?
      AND DIPA_LINO = ?
";

$stmtCheck = sqlsrv_query($conn, $sqlCheck, array($di_id, $dipa_lino));

if ($stmtCheck === false) {
    json_error("Gagal cek DI_PART: " . print_r(sqlsrv_errors(), true));
}

$rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
$exists = intval($rowCheck["CNT"]) > 0;

/*
    PENGAMAN DUPLICATE ITEM.
    Dalam 1 DI_ID tidak boleh ada PRICE_ID yang sama,
    kecuali baris dirinya sendiri.
*/
$sqlDup = "
    SELECT TOP 1
        DIPA_LINO,
        PART_CODE,
        PRICE_ID
    FROM DI_PART
    WHERE DI_ID = ?
      AND PRICE_ID = ?
      AND DIPA_LINO <> ?
";

$stmtDup = sqlsrv_query($conn, $sqlDup, array(
    $di_id,
    $price_id,
    $dipa_lino
));

if ($stmtDup === false) {
    json_error("Gagal cek duplicate item: " . print_r(sqlsrv_errors(), true));
}

$rowDup = sqlsrv_fetch_array($stmtDup, SQLSRV_FETCH_ASSOC);

if ($rowDup) {
    json_error(
        "Item tidak boleh double dalam 1 DI.\n\n" .
        "DI_ID    : " . $di_id . "\n" .
        "Line ini : " . $dipa_lino . "\n" .
        "CODE     : " . $part_code . "\n" .
        "PRICE_ID : " . $price_id . "\n\n" .
        "Item sudah ada di line : " . $rowDup["DIPA_LINO"]
    );
}

sqlsrv_begin_transaction($conn);

try {

    if ($exists) {

        $sqlUpdate = "
            UPDATE DI_PART
            SET
                PART_CODE = ?,
                DIPA_QTY = ?,
                PACK_ID = ?,
                DIPA_PACK = ?,
                DIPA_PQTY = ?,
                PRICE_ID = ?,
                LOCATION = ?
            WHERE DI_ID = ?
              AND DIPA_LINO = ?
        ";

        $paramsUpdate = array(
            $part_code,
            $dipa_qty,
            $pack_id,
            $pack_desc,
            $dipa_pqty,
            $price_id,
            $location,
            $di_id,
            $dipa_lino
        );

        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);

        if ($stmtUpdate === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        $mode = "update";

    } else {

        $sqlInsert = "
            INSERT INTO DI_PART
            (
                DI_ID,
                DIPA_LINO,
                PART_CODE,
                DIPA_QTY,
                PACK_ID,
                DIPA_PACK,
                DIPA_PQTY,
                DIPA_POSTED,
                PRICE_ID,
                LOCATION,
                IS_MANUAL
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 1
            )
        ";

        $paramsInsert = array(
            $di_id,
            $dipa_lino,
            $part_code,
            $dipa_qty,
            $pack_id,
            $pack_desc,
            $dipa_pqty,
            $price_id,
            $location
        );

        $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);

        if ($stmtInsert === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        $mode = "insert";
    }

    sqlsrv_commit($conn);

    echo json_encode(array(
        "success" => true,
        "mode" => $mode,
        "message" => "Line " . $dipa_lino . " berhasil disimpan.",
        "DI_ID" => $di_id,
        "DIPA_LINO" => $dipa_lino,
        "PART_CODE" => $part_code,
        "ITEM_NAME" => $itemInfo["ITEM_NAME"],
        "DIPA_QTY" => $dipa_qty,
        "DIPA_PQTY" => $dipa_pqty,
        "PACK_ID" => $pack_id,
        "PACK_DESC" => $pack_desc
    ));
    exit();

} catch (Exception $e) {

    sqlsrv_rollback($conn);

    echo json_encode(array(
        "success" => false,
        "message" => "Save line gagal: " . $e->getMessage()
    ));
    exit();
}
?>
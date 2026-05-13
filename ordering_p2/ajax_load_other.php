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

function normalize_date_112($dateText) {
    $ts = strtotime($dateText);

    if ($ts === false) {
        return "";
    }

    return date("Ymd", $ts);
}

function get_pack_code($conn, $pack_id) {
    static $cache = array();

    $pack_id = intval($pack_id);

    if ($pack_id <= 0) {
        return "";
    }

    if (isset($cache[$pack_id])) {
        return $cache[$pack_id];
    }

    $sql = "
        SELECT TOP 1 PACK_CODE
        FROM PACK
        WHERE PACK_ID = ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array($pack_id));

    if ($stmt === false) {
        return "";
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if ($row) {
        $cache[$pack_id] = trim($row["PACK_CODE"]);
    } else {
        $cache[$pack_id] = "";
    }

    return $cache[$pack_id];
}

$di_id      = post_value("DI_ID");
$cust_code  = post_value("CUST_CODE");
$start_date = post_value("START_DATE");
$end_date   = post_value("END_DATE");

if ($di_id == "") {
    json_error("Save Header dulu sebelum LOAD DI OTHER.");
}

if ($cust_code == "") {
    json_error("Customer belum dipilih.");
}

if ($start_date == "") {
    json_error("Start date belum diisi.");
}

if ($end_date == "") {
    json_error("DI date belum diisi.");
}

$di_id_int = intval($di_id);

$start_date_sql = normalize_date_112($start_date);
$end_date_sql   = normalize_date_112($end_date);

if ($start_date_sql == "") {
    json_error("Format Start Date tidak valid.");
}

if ($end_date_sql == "") {
    json_error("Format DI Date tidak valid.");
}


// ==========================================================
// CEK DI_ID ADA
// ==========================================================
$sqlCheckDI = "
    SELECT TOP 1 DI_ID
    FROM DI
    WHERE DI_ID = ?
";

$stmtCheckDI = sqlsrv_query($conn, $sqlCheckDI, array($di_id_int));

if ($stmtCheckDI === false) {
    json_error("Gagal cek DI_ID: " . print_r(sqlsrv_errors(), true));
}

if (!sqlsrv_fetch_array($stmtCheckDI, SQLSRV_FETCH_ASSOC)) {
    json_error("DI_ID tidak ditemukan. Save Header ulang.");
}


// ==========================================================
// EXECUTE SP_DI_PART
// Delphi:
// EXECUTE SP_DI_PART :CUST_CODE, :START_DATE, :END_DATE
// ==========================================================
$sqlSource = "
    SET NOCOUNT ON;

    DECLARE @START_DATE DATETIME;
    DECLARE @END_DATE DATETIME;

    SET @START_DATE = CONVERT(DATETIME, ?, 112);
    SET @END_DATE   = CONVERT(DATETIME, ?, 112);

    EXECUTE SP_DI_PART ?, @START_DATE, @END_DATE;
";

$paramsSource = array(
    $start_date_sql,
    $end_date_sql,
    $cust_code
);

$stmtSource = sqlsrv_query($conn, $sqlSource, $paramsSource);

if ($stmtSource === false) {
    json_error(
        "Execute SP_DI_PART gagal.\n\n" .
        print_r(sqlsrv_errors(), true)
    );
}


// ==========================================================
// BACA HASIL SP_DI_PART
// ==========================================================
$rows = array();
$lineNo = 1;

do {
    while ($row = sqlsrv_fetch_array($stmtSource, SQLSRV_FETCH_ASSOC)) {

        $dailySch = isset($row["DAILY_SCH"]) ? intval($row["DAILY_SCH"]) : 0;
        $balQty   = isset($row["BAL_QTY"]) ? intval($row["BAL_QTY"]) : 0;

        if ($dailySch > 0 && $balQty > 0) {
            if ($dailySch < $balQty) {
                $qtyToLoad = $dailySch;
            } else {
                $qtyToLoad = $balQty;
            }
        } elseif ($dailySch > 0) {
            $qtyToLoad = $dailySch;
        } else {
            $qtyToLoad = 0;
        }

        $partCode = "";
        $partName = "";
        $packId   = 1;
        $dipaPQty = 1;
        $priceId  = 0;

        if (isset($row["PART_NUM"])) {
            $partCode = trim($row["PART_NUM"]);
        }

        if (isset($row["PART_NAME"])) {
            $partName = trim($row["PART_NAME"]);
        }

        if (isset($row["PACK_ID"])) {
            $packId = intval($row["PACK_ID"]);
        }

        if (isset($row["DIPA_PQTY"])) {
            $dipaPQty = intval($row["DIPA_PQTY"]);
        }

        if (isset($row["PRICE_ID"])) {
            $priceId = intval($row["PRICE_ID"]);
        }

        $packCode = get_pack_code($conn, $packId);

        $rows[] = array(
            "DIPA_LINO" => $lineNo,
            "CODE"      => $partCode,
            "NAME"      => $partName,
            "DIPA_QTY"  => $qtyToLoad,
            "PACK_ID"   => $packId,
            "DIPA_PQTY" => $dipaPQty,
            "PRICE_ID"  => $priceId,
            "PACK_DESC" => $packCode,
            "LOCATION"  => ""
        );

        $lineNo++;
    }
} while (sqlsrv_next_result($stmtSource));

if (count($rows) == 0) {
    json_error(
        "Tidak ada data dari SP_DI_PART.\n\n" .
        "Parameter:\n" .
        "CUST_CODE: " . $cust_code . "\n" .
        "START_DATE: " . $start_date . "\n" .
        "END_DATE: " . $end_date
    );
}


// ==========================================================
// INSERT KE DI_PART
//
// PACK_ID   -> dari SP_DI_PART
// DIPA_PACK -> PACK.PACK_CODE
// ==========================================================
sqlsrv_begin_transaction($conn);

try {

    $sqlDelete = "
        DELETE FROM DI_PART
        WHERE DI_ID = ?
    ";

    $stmtDelete = sqlsrv_query($conn, $sqlDelete, array($di_id_int));

    if ($stmtDelete === false) {
        throw new Exception(
            "Gagal hapus DI_PART lama: " .
            print_r(sqlsrv_errors(), true)
        );
    }

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
            ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 0
        )
    ";

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];

        if ($r["CODE"] == "") {
            throw new Exception(
                "PART_CODE kosong pada line " .
                $r["DIPA_LINO"]
            );
        }

        if ($r["PRICE_ID"] <= 0) {
            throw new Exception(
                "PRICE_ID kosong pada line " .
                $r["DIPA_LINO"] .
                " / part " .
                $r["CODE"]
            );
        }

        $paramsInsert = array(
            $di_id_int,
            $r["DIPA_LINO"],
            $r["CODE"],
            $r["DIPA_QTY"],
            $r["PACK_ID"],
            $r["PACK_DESC"],
            $r["DIPA_PQTY"],
            $r["PRICE_ID"],
            $r["LOCATION"]
        );

        $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);

        if ($stmtInsert === false) {
            throw new Exception(
                "Gagal insert DI_PART line " .
                $r["DIPA_LINO"] .
                ": " .
                print_r(sqlsrv_errors(), true)
            );
        }
    }

    sqlsrv_commit($conn);

    echo json_encode(array(
        "success" => true,
        "message" => "LOAD DI OTHER selesai. Data dimuat: " . count($rows) . " item.",
        "rows" => $rows
    ));
    exit();

} catch (Exception $e) {

    sqlsrv_rollback($conn);

    echo json_encode(array(
        "success" => false,
        "message" => "LOAD DI OTHER gagal: " . $e->getMessage()
    ));
    exit();
}
?>
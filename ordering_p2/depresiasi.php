<?php
// Pastikan tidak ada spasi atau baris kosong sebelum tag <?php ini
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

// ==========================================================
// CORE HELPERS (PHP 5.4 Compatible)
// ==========================================================
function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function safe_trim($value) {
    if ($value === null) { return ""; }
    return trim((string)$value);
}

function get_param($name, $default = "") {
    if (isset($_POST[$name])) { return trim($_POST[$name]); }
    if (isset($_GET[$name])) { return trim($_GET[$name]); }
    return $default;
}

function safe_int($value, $default = 0) {
    if ($value === null || $value === "") { return $default; }
    return intval($value);
}

function safe_float($value, $default = 0) {
    if ($value === null || $value === "") { return $default; }
    // Hapus koma ribuan sebelum konversi ke float
    $value = str_replace(",", "", (string)$value);
    return floatval($value);
}

function fmt_num($value, $decimal = 0) {
    if ($value === null || $value === "") { $value = 0; }
    return number_format((float)$value, $decimal, ".", ",");
}

function date_out($value) {
    if ($value instanceof DateTime) { return $value->format("Y-m-d"); }
    if ($value === null || $value === "") { return ""; }
    $ts = strtotime((string)$value);
    if ($ts === false) { return safe_trim($value); }
    return date("Y-m-d", $ts);
}

function date_out_display($value) {
    if ($value instanceof DateTime) { return $value->format("d-M-Y"); }
    if ($value === null || $value === "") { return ""; }
    $ts = strtotime((string)$value);
    if ($ts === false) { return safe_trim($value); }
    return date("d-M-Y", $ts);
}

function sql_error_text() {
    return print_r(sqlsrv_errors(), true);
}

function query_one($conn, $sql, $params) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) { return false; }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$row) { return null; }
    return $row;
}

function query_all($conn, $sql, $params) {
    $rows = array();
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt !== false) {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $r;
        }
    }
    return $rows;
}

function json_out($data) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data);
    exit();
}

// ==========================================================
// DATE FORMATTING HELPERS
// ==========================================================
function month_to_yyyymm($value) {
    $value = trim($value);
    if ($value == "") { return date("Ym"); }
    if (preg_match('/^\d{6}$/', $value)) { return $value; }
    if (preg_match('/^\d{4}-\d{2}$/', $value)) { return str_replace("-", "", $value); }
    $ts = strtotime($value . "-01");
    if ($ts === false) { $ts = strtotime($value); }
    if ($ts === false) { return date("Ym"); }
    return date("Ym", $ts);
}

function yyyymm_to_month_input($value) {
    $value = month_to_yyyymm($value);
    return substr($value, 0, 4) . "-" . substr($value, 4, 2);
}

function month_label($yyyymm) {
    $yyyymm = month_to_yyyymm($yyyymm);
    $ts = strtotime(substr($yyyymm, 0, 4) . "-" . substr($yyyymm, 4, 2) . "-01");
    return date("M - Y", $ts);
}

function month_start_date($yyyymm) {
    $yyyymm = month_to_yyyymm($yyyymm);
    return substr($yyyymm, 0, 4) . "-" . substr($yyyymm, 4, 2) . "-01";
}

function get_column_type($conn, $tableName, $columnName) {
    $row = query_one(
        $conn,
        "SELECT TOP 1 DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?",
        array($tableName, $columnName)
    );
    if ($row && isset($row["DATA_TYPE"])) { return strtolower(safe_trim($row["DATA_TYPE"])); }
    return "varchar";
}

function period_value_for_db($conn, $yyyymm) {
    $type = get_column_type($conn, "PRICE_DEPRESIASI", "PERIODE");
    $yyyymm = month_to_yyyymm($yyyymm);
    if ($type == "datetime" || $type == "date" || $type == "smalldatetime") { return month_start_date($yyyymm); }
    if ($type == "int" || $type == "bigint" || $type == "smallint" || $type == "tinyint" || $type == "numeric" || $type == "decimal") {
        return intval($yyyymm);
    }
    return $yyyymm;
}

function period_compare_sql($fieldName) {
    return "(CONVERT(VARCHAR(6), " . $fieldName . ", 112) = ? OR CONVERT(VARCHAR(6), " . $fieldName . ") = ? OR REPLACE(CONVERT(VARCHAR(7), " . $fieldName . ", 120), '-', '') = ?)";
}

// ==========================================================
// AJAX AUTOCOMPLETE
// ==========================================================
$ajaxMode = isset($_GET["ajax"]) ? trim($_GET["ajax"]) : "";

if ($ajaxMode != "") {
    $q = isset($_GET["q"]) ? trim($_GET["q"]) : "";
    $like = "%" . $q . "%";

    if ($ajaxMode == "item") {
        $rows = query_all(
            $conn,
            "SELECT TOP 25 ITEM_ID, ISNULL(ITEM_CODE, '') AS ITEM_CODE, ISNULL(ITEM_NO, '') AS ITEM_NO, ISNULL(ITEM_NAME, '') AS ITEM_NAME FROM dbo.ITEMS WHERE (? = '' OR ISNULL(ITEM_CODE, '') LIKE ? OR ISNULL(ITEM_NO, '') LIKE ? OR ISNULL(ITEM_NAME, '') LIKE ?) ORDER BY ITEM_CODE",
            array($q, $like, $like, $like)
        );
        $out = array();
        for ($i = 0; $i < count($rows); $i++) {
            $out[] = array("ITEM_ID" => intval($rows[$i]["ITEM_ID"]), "ITEM_CODE" => safe_trim($rows[$i]["ITEM_CODE"]), "ITEM_NO" => safe_trim($rows[$i]["ITEM_NO"]), "ITEM_NAME" => safe_trim($rows[$i]["ITEM_NAME"]));
        }
        json_out(array("success" => true, "rows" => $out));
    }

    if ($ajaxMode == "customer") {
        $rows = query_all(
            $conn,
            "SELECT TOP 25 CUST_ID, ISNULL(CUST_CODE, '') AS CUST_CODE, ISNULL(CUST_ABBR, '') AS CUST_ABBR, ISNULL(CUST_COMP, '') AS CUST_COMP FROM dbo.CUST WHERE (? = '' OR ISNULL(CUST_CODE, '') LIKE ? OR ISNULL(CUST_COMP, '') LIKE ? OR ISNULL(CUST_ABBR, '') LIKE ?) ORDER BY CUST_CODE",
            array($q, $like, $like, $like)
        );
        $out = array();
        for ($i = 0; $i < count($rows); $i++) {
            $out[] = array("CUST_ID" => intval($rows[$i]["CUST_ID"]), "CUST_CODE" => safe_trim($rows[$i]["CUST_CODE"]), "CUST_ABBR" => safe_trim($rows[$i]["CUST_ABBR"]), "CUST_COMP" => safe_trim($rows[$i]["CUST_COMP"]));
        }
        json_out(array("success" => true, "rows" => $out));
    }
    json_out(array("success" => false, "rows" => array(), "message" => "Mode ajax tidak dikenal."));
}

// ==========================================================
// PARAMETER INIT
// ==========================================================
$action = get_param("action", "");
$msg = "";
$err = "";

$selectedItemId = safe_int(get_param("item_id"), 0);
$selectedCustId = safe_int(get_param("cust_id"), 0);
$selectedPriceId = safe_int(get_param("price_id"), 0);
$selectedPeriod = month_to_yyyymm(get_param("periode", date("Y-m")));
$mode = get_param("mode", "list");
$qItem = get_param("q_item", "");

if ($mode != "list" && $mode != "1year" && $mode != "2year" && $mode != "po") {
    $mode = "list";
}

// Menangkap flag pesan sukses dari metode PRG (GET)
$msgCode = get_param("msg", "");
if ($msgCode === "saved") {
    $msg = "Data Depresiasi berhasil dihitung dan disimpan.";
} elseif ($msgCode === "deleted") {
    $msg = "Depresiasi berhasil dihapus.";
}

// ==========================================================
// ACTION HITUNG / DELETE (Backend Save Logic + PRG Pattern TUNGGAL)
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && $action == "save_dep") {
    $selectedItemId = safe_int(get_param("item_id"), 0);
    $selectedCustId = safe_int(get_param("cust_id"), 0);
    $selectedPriceId = safe_int(get_param("price_id"), 0);
    $selectedPeriod = month_to_yyyymm(get_param("periode", date("Y-m")));

    $depQty = safe_float(get_param("DEP_QTY"), 0);
    $depresiasi = safe_float(get_param("DEPRESIASI"), 0);

    if ($selectedItemId <= 0) {
        $err = "Pilih ITEM terlebih dahulu dari tabel.";
    } elseif ($selectedCustId <= 0 && $selectedPriceId <= 0) {
        $err = "Pilih Customer / Price terlebih dahulu.";
    } else {
        if ($selectedPriceId <= 0) {
            $rowPrice = query_one(
                $conn,
                "SELECT TOP 1 PRICE_ID FROM dbo.PRICE WHERE PART_ID = ? AND CUST_ID = ? ORDER BY PRICE_ID DESC",
                array($selectedItemId, $selectedCustId)
            );
            if ($rowPrice) {
                $selectedPriceId = intval($rowPrice["PRICE_ID"]);
            }
        }

        if ($selectedPriceId <= 0) {
            $err = "PRICE_ID tidak ditemukan untuk item dan customer ini. Buat dulu di Master Price.";
        } else {
            if ($depresiasi == 0) { $depresiasi = $depQty; }

            $periodeDb = period_value_for_db($conn, $selectedPeriod);
            $periodWhere = period_compare_sql("PERIODE");

            $exists = query_one(
                $conn,
                "SELECT TOP 1 price_id FROM dbo.PRICE_DEPRESIASI WHERE price_id = ? AND " . $periodWhere,
                array($selectedPriceId, $selectedPeriod, $selectedPeriod, $selectedPeriod)
            );

            if ($exists) {
                $sql = "UPDATE dbo.PRICE_DEPRESIASI SET DEPRESIASI = ?, DEP_QTY = ? WHERE price_id = ? AND " . $periodWhere;
                $params = array($depresiasi, $depQty, $selectedPriceId, $selectedPeriod, $selectedPeriod, $selectedPeriod);
            } else {
                $sql = "INSERT INTO dbo.PRICE_DEPRESIASI (price_id, PERIODE, DEPRESIASI, DEP_QTY) VALUES (?, ?, ?, ?)";
                $params = array($selectedPriceId, $periodeDb, $depresiasi, $depQty);
            }

            $stmt = sqlsrv_query($conn, $sql, $params);
            if ($stmt === false) {
                $err = "Gagal simpan PRICE_DEPRESIASI: " . sql_error_text();
            } else {
                
                // ==========================================================
                // PERBAIKAN: Update juga tabel LOCATION_CUSTOMER agar grid refresh
                // ==========================================================
                $sqlUpdateLoc = "UPDATE dbo.LOCATION_CUSTOMER SET DEPRESIASI = ? WHERE ITEM_ID = ?";
                sqlsrv_query($conn, $sqlUpdateLoc, array($depresiasi, $selectedItemId));
                // ==========================================================

                // REDIRECT PRG DENGAN CACHE BUSTER (&t=time) UNTUK FORCING REFRESH
                $redirectUrl = sprintf(
                    "depresiasi.php?item_id=%d&cust_id=%d&price_id=%d&periode=%s&mode=%s&msg=saved&t=%d",
                    $selectedItemId, $selectedCustId, $selectedPriceId, urlencode($selectedPeriod), urlencode($mode), time()
                );
                header("Location: " . $redirectUrl);
                exit();
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $action == "delete_dep") {
    $selectedPriceId = safe_int(get_param("price_id"), 0);
    $selectedPeriod = month_to_yyyymm(get_param("periode", date("Y-m")));

    if ($selectedPriceId <= 0) {
        $err = "Pilih PRICE dulu.";
    } else {
        $periodWhere = period_compare_sql("PERIODE");
        $stmt = sqlsrv_query(
            $conn,
            "DELETE FROM dbo.PRICE_DEPRESIASI WHERE price_id = ? AND " . $periodWhere,
            array($selectedPriceId, $selectedPeriod, $selectedPeriod, $selectedPeriod)
        );
        if ($stmt === false) {
            $err = "Gagal delete depresiasi: " . sql_error_text();
        } else {
            // REDIRECT PRG DENGAN CACHE BUSTER
            $redirectUrl = sprintf(
                "depresiasi.php?item_id=%d&cust_id=%d&price_id=%d&periode=%s&mode=%s&msg=deleted&t=%d",
                $selectedItemId, $selectedCustId, $selectedPriceId, urlencode($selectedPeriod), urlencode($mode), time()
            );
            header("Location: " . $redirectUrl);
            exit();
        }
    }
}

// ==========================================================
// LOAD SELECTED ITEM / CUSTOMER
// ==========================================================
$item = array("ITEM_ID" => "", "ITEM_CODE" => "", "ITEM_NO" => "", "ITEM_NAME" => "", "DEPRESIASI" => 0, "DELIVERY" => 0, "DIFF" => 0);

if ($selectedItemId <= 0 && $qItem != "") {
    $likeFind = "%" . $qItem . "%";
    $found = query_one(
        $conn,
        "SELECT TOP 1 ITEM_ID, ITEM_CODE, ITEM_NO, ITEM_NAME FROM dbo.ITEMS WHERE ITEM_CODE LIKE ? OR ITEM_NO LIKE ? OR ITEM_NAME LIKE ? ORDER BY ITEM_CODE",
        array($likeFind, $likeFind, $likeFind)
    );
    if ($found) { $selectedItemId = intval($found["ITEM_ID"]); }
}

if ($selectedItemId > 0) {
    $rowDep = query_one(
        $conn,
        "SELECT TOP 1 ITEM_ID, ISNULL(ITEM_CODE, '') AS ITEM_CODE, ISNULL(ITEM_NO, '') AS ITEM_NO, ISNULL(ITEM_NAME, '') AS ITEM_NAME, ISNULL(DEPRESIASI, 0) AS DEPRESIASI, ISNULL(DELIVERY, 0) AS DELIVERY, ISNULL(DIFF, 0) AS DIFF FROM dbo.VIEW_DEPRESIASI WHERE ITEM_ID = ?",
        array($selectedItemId)
    );

    if ($rowDep) {
        $item = array_merge($item, $rowDep);
    } else {
        $rowItem = query_one($conn, "SELECT TOP 1 ITEM_ID, ITEM_CODE, ITEM_NO, ITEM_NAME FROM dbo.ITEMS WHERE ITEM_ID = ?", array($selectedItemId));
        if ($rowItem) { $item = array_merge($item, $rowItem); }
    }
}

$customer = array("CUST_ID" => "", "CUST_CODE" => "", "CUST_COMP" => "");

if ($selectedCustId > 0) {
    $rowCust = query_one($conn, "SELECT TOP 1 CUST_ID, CUST_CODE, CUST_COMP FROM dbo.CUST WHERE CUST_ID = ?", array($selectedCustId));
    if ($rowCust) { $customer = array_merge($customer, $rowCust); }
}

// ==========================================================
// MAIN DATA DEPRESIASI (Top Grid)
// ==========================================================
$whereDep = "1 = 1";
$paramsDep = array();

if ($qItem != "") {
    $likeQ = "%" . $qItem . "%";
    $whereDep .= " AND (ITEM_CODE LIKE ? OR ITEM_NO LIKE ? OR ITEM_NAME LIKE ?)";
    $paramsDep[] = $likeQ; $paramsDep[] = $likeQ; $paramsDep[] = $likeQ;
}

if ($mode == "1year" || $mode == "2year") { $whereDep .= " AND ISNULL(DIFF, 0) <> 0"; }

$depRows = query_all(
    $conn,
    "SELECT TOP 300 ITEM_ID, ISNULL(ITEM_CODE, '') AS ITEM_CODE, ISNULL(ITEM_NO, '') AS ITEM_NO, ISNULL(ITEM_NAME, '') AS ITEM_NAME, ISNULL(DEPRESIASI, 0) AS DEPRESIASI, ISNULL(DELIVERY, 0) AS DELIVERY, ISNULL(DIFF, 0) AS DIFF FROM dbo.VIEW_DEPRESIASI WHERE " . $whereDep . " ORDER BY ITEM_CODE",
    $paramsDep
);

// ==========================================================
// PRICE ROWS FOR SELECTED ITEM / CUSTOMER
// ==========================================================
$priceRows = array();
if ($selectedItemId > 0) {
    $wherePrice = "P.PART_ID = ?";
    $paramsPrice = array($selectedItemId);

    if ($selectedCustId > 0) {
        $wherePrice .= " AND P.CUST_ID = ?";
        $paramsPrice[] = $selectedCustId;
    }

    $priceRows = query_all(
        $conn,
        "SELECT P.PRICE_ID, P.PRICE_CODE, P.PART_ID, P.CUST_ID, P.CURR_CODE, P.PACK_ID, P.PRICE_PACK_QTY, C.CUST_CODE, C.CUST_COMP FROM dbo.PRICE AS P INNER JOIN dbo.CUST AS C ON P.CUST_ID = C.CUST_ID WHERE " . $wherePrice . " ORDER BY C.CUST_CODE, P.PRICE_ID",
        $paramsPrice
    );

    if ($selectedPriceId <= 0 && count($priceRows) > 0) {
        $selectedPriceId = intval($priceRows[0]["PRICE_ID"]);
    }
}

// ==========================================================
// PRICE DEPRESIASI LIST & PRE-FILL LOGIC
// ==========================================================
$priceDepRows = array();
$depForm = array("DEPRESIASI" => "", "DEP_QTY" => "");

if ($selectedPriceId > 0) {
    $priceDepRows = query_all(
        $conn,
        "SELECT TOP 200 PD.price_id, PD.PERIODE, ISNULL(PD.DEPRESIASI, 0) AS DEPRESIASI, ISNULL(PD.DEP_QTY, 0) AS DEP_QTY, P.PRICE_CODE, C.CUST_CODE, C.CUST_COMP, I.ITEM_CODE, I.ITEM_NO, I.ITEM_NAME FROM dbo.PRICE_DEPRESIASI AS PD INNER JOIN dbo.PRICE AS P ON PD.price_id = P.PRICE_ID INNER JOIN dbo.ITEMS AS I ON P.PART_ID = I.ITEM_ID INNER JOIN dbo.CUST AS C ON P.CUST_ID = C.CUST_ID WHERE PD.price_id = ? ORDER BY PD.PERIODE DESC",
        array($selectedPriceId)
    );

    $rowCurrentDep = query_one(
        $conn,
        "SELECT TOP 1 ISNULL(DEPRESIASI, 0) AS DEPRESIASI, ISNULL(DEP_QTY, 0) AS DEP_QTY FROM dbo.PRICE_DEPRESIASI WHERE price_id = ? AND " . period_compare_sql("PERIODE"),
        array($selectedPriceId, $selectedPeriod, $selectedPeriod, $selectedPeriod)
    );

    if ($rowCurrentDep) {
        $depForm["DEPRESIASI"] = $rowCurrentDep["DEPRESIASI"];
        $depForm["DEP_QTY"] = $rowCurrentDep["DEP_QTY"];
    } else {
        $depForm["DEPRESIASI"] = isset($item["DEPRESIASI"]) ? $item["DEPRESIASI"] : 0;
        $depForm["DEP_QTY"] = isset($item["DEPRESIASI"]) ? $item["DEPRESIASI"] : 0;
    }
}

// ==========================================================
// ITEM SEARCH ROWS & PO DEPRESIASI ROWS
// ==========================================================
$itemSearchRows = array();
$whereSearch = "1 = 1";
$paramsSearch = array();

if ($qItem != "") {
    $likeQ = "%" . $qItem . "%";
    $whereSearch .= " AND (ITEM_CODE LIKE ? OR ITEM_NO LIKE ? OR ITEM_NAME LIKE ?)";
    $paramsSearch[] = $likeQ; $paramsSearch[] = $likeQ; $paramsSearch[] = $likeQ;
}

$itemSearchRows = query_all(
    $conn,
    "SELECT TOP 200 ITEM_ID, ISNULL(ITEM_CODE, '') AS ITEM_CODE, ISNULL(ITEM_NO, '') AS ITEM_NO, ISNULL(ITEM_NAME, '') AS ITEM_NAME FROM dbo.VIEW_DEPRESIASI WHERE " . $whereSearch . " ORDER BY ITEM_CODE",
    $paramsSearch
);

$poRows = array();
if ($selectedItemId > 0 && safe_trim($item["ITEM_CODE"]) != "") {
    $poRows = query_all(
        $conn,
        "SELECT TOP 200 ISNULL(ORDR_PO, '') AS ORDR_PO, ORDR_DATE, ISNULL(ITEM_CODE, '') AS ITEM_CODE, ISNULL(ITEM_NAME, '') AS ITEM_NAME, ISNULL(ORDP_QTY, 0) AS ORDP_QTY, ISNULL(ORDP_DQTY, 0) AS ORDP_DQTY, ISNULL(ORDP_BQTY, 0) AS ORDP_BQTY FROM dbo.view_po_depresiasi WHERE ITEM_CODE = ? ORDER BY ORDR_DATE DESC, ORDR_PO",
        array($item["ITEM_CODE"])
    );
}

$modeTitle = "LIST DEPRESIASI";
if ($mode == "1year") { $modeTitle = "DEPRESIASI 1 YEAR"; }
if ($mode == "2year") { $modeTitle = "MORE THAN 2 YEAR"; }
if ($mode == "po") { $modeTitle = "PO DEPRESIASI"; }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Data Depresiasi</title>
    <style>
        body { margin: 0; padding: 0; background: #d4d0c8; font-family: Tahoma, Arial, sans-serif; font-size: 12px; color: #000000; }
        .topbar { background: #000080; color: #ffffff; padding: 8px 12px; font-weight: bold; text-align: center; letter-spacing: 1px; }
        .wrap { width: 1260px; margin: 10px auto; background: #efefef; border: 1px solid #777777; padding: 8px; box-sizing: border-box; }
        .title { text-align: center; font-size: 34px; font-weight: bold; letter-spacing: 2px; margin: 0 0 4px 0; }
        .msg { padding: 7px; background: #e9ffe9; border: 1px solid #008000; color: #004000; margin-bottom: 6px; }
        .err { padding: 7px; background: #ffe9e9; border: 1px solid #800000; color: #800000; margin-bottom: 6px; white-space: pre-wrap; }
        .main-layout { display: grid; grid-template-columns: 840px 1fr; gap: 18px; align-items: start; }
        .panel { border: 1px solid #777777; background: #f5f5f5; padding: 8px; box-sizing: border-box; margin-bottom: 10px; }
        .row { margin-bottom: 7px; white-space: nowrap; }
        label { display: inline-block; width: 85px; }
        input[type="text"], input[type="number"], input[type="month"], select { height: 24px; border: 1px solid #777777; background: #ffffff; padding: 2px 4px; box-sizing: border-box; font-size: 12px; }
        input[readonly] { background: #eeeeee; }
        .w60 { width: 60px; } .w80 { width: 80px; } .w100 { width: 100px; } .w120 { width: 120px; } .w150 { width: 150px; } .w180 { width: 180px; } .w240 { width: 240px; } .w280 { width: 280px; } .w360 { width: 360px; } .w520 { width: 520px; }
        .btn { display: inline-block; border: 2px outset #ffffff; background: #d4d0c8; color: #000000; padding: 6px 14px; font-size: 12px; cursor: pointer; text-decoration: none; margin-right: 5px; min-width: 120px; text-align: center; box-sizing: border-box; }
        .btn-small { min-width: 0; padding: 5px 10px; }
        .btn:active { border: 2px inset #ffffff; }
        .grid-wrap { overflow: auto; border: 1px solid #777777; background: #ffffff; }
        .grid-main { height: 160px; } .grid-search { height: 96px; } .grid-small { height: 140px; } .grid-po { height: 140px; }
        table.grid { width: 100%; border-collapse: collapse; background: #ffffff; font-size: 12px; }
        table.grid th, table.grid td { border: 1px solid #999999; padding: 3px 5px; white-space: nowrap; }
        table.grid th { background: #d4d0c8; position: sticky; top: 0; z-index: 2; font-weight: normal; }
        table.grid tr:hover td { background: #d9ecff; cursor: pointer; }
        table.grid tr.selected td { background: #2f70c9; color: #ffffff; }
        .right { text-align: right; } .center { text-align: center; }
        .section-title { font-weight: bold; margin: 10px 0 6px 0; font-size: 12px; }
        .big-label { font-size: 32px; font-weight: bold; margin: 8px 0 4px 0; }
        .autocomplete-box { position: relative; display: inline-block; vertical-align: middle; }
        .autocomplete-list { position: absolute; left: 0; top: 24px; width: 520px; max-height: 230px; overflow-y: auto; background: #ffffff; border: 1px solid #333333; z-index: 9999; display: none; box-shadow: 2px 2px 5px rgba(0,0,0,0.25); }
        .autocomplete-item { padding: 5px 7px; border-bottom: 1px solid #dddddd; cursor: pointer; line-height: 16px; background: #ffffff; }
        .autocomplete-item:hover, .autocomplete-item.active { background: #2f70c9; color: #ffffff; }
        .autocomplete-sub { font-size: 11px; color: #555555; }
        .autocomplete-item:hover .autocomplete-sub, .autocomplete-item.active .autocomplete-sub { color: #ffffff; }
        .small-note { color: #000080; font-weight: bold; margin: 5px 0; }
    </style>
</head>
<body>

<div class="topbar">ORDERING SYSTEM - PLANT 2</div>

<div class="wrap">
    <div class="title">DATA DEPRESIASI</div>

    <?php if ($msg != "") { ?> <div class="msg"><?php echo h($msg); ?></div> <?php } ?>
    <?php if ($err != "") { ?> <div class="err"><?php echo h($err); ?></div> <?php } ?>

    <div class="main-layout">
        <!-- Kolom Kiri -->
        <div>
            <!-- TABEL UTAMA -->
            <div class="grid-wrap grid-main">
                <table class="grid">
                    <thead>
                        <tr>
                            <th>ITEM_CODE</th>
                            <th>ITEM_NO</th>
                            <th>ITEM_NAME</th>
                            <th>DEPRESIASI</th>
                            <th>DELIVERY</th>
                            <th>DIFF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < count($depRows); $i++) { $r = $depRows[$i]; ?>
                            <tr class="<?php echo intval($r["ITEM_ID"]) == intval($selectedItemId) ? "selected" : ""; ?>"
                                onclick="location.href='depresiasi.php?item_id=<?php echo intval($r["ITEM_ID"]); ?>&cust_id=<?php echo intval($selectedCustId); ?>&periode=<?php echo urlencode($selectedPeriod); ?>&mode=<?php echo urlencode($mode); ?>'">
                                <td><?php echo h($r["ITEM_CODE"]); ?></td>
                                <td><?php echo h($r["ITEM_NO"]); ?></td>
                                <td><?php echo h($r["ITEM_NAME"]); ?></td>
                                <td class="right"><?php echo h(fmt_num($r["DEPRESIASI"], 0)); ?></td>
                                <td class="right"><?php echo h(fmt_num($r["DELIVERY"], 0)); ?></td>
                                <td class="right"><?php echo h(fmt_num($r["DIFF"], 0)); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="row" style="margin-top:6px;">
                <a class="btn" href="po_depresiasi.php" target="_blank">LIST PO DEPRESIASI</a>
                <a class="btn" href="depresiasi_view_report.php" target="_blank">LIST DEPRESIASI</a>
            </div>

            <!-- SEARCH -->
            <div class="section-title">PENCARIAN ITEM CODE</div>
            <form method="get" action="depresiasi.php" autocomplete="off">
                <input type="hidden" name="cust_id" value="<?php echo intval($selectedCustId); ?>">
                <input type="hidden" name="periode" value="<?php echo h($selectedPeriod); ?>">
                <input type="hidden" name="mode" value="<?php echo h($mode); ?>">

                <div class="row">
                    <span class="autocomplete-box">
                        <input type="text" id="searchItem" name="q_item" class="w360" value="<?php echo h($qItem); ?>" placeholder="Ketik item code / item no / item name">
                        <div id="searchItemList" class="autocomplete-list"></div>
                    </span>
                    <button type="submit" class="btn btn-small">CARI</button>
                    <a class="btn btn-small" href="depresiasi.php?cust_id=<?php echo intval($selectedCustId); ?>&periode=<?php echo urlencode($selectedPeriod); ?>">REFRESH</a>
                </div>
            </form>

            <div class="grid-wrap grid-search">
                <table class="grid">
                    <thead>
                        <tr><th>ITEM_CODE</th><th>ITEM_NO</th><th>ITEM_NAME</th></tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < count($itemSearchRows); $i++) { $r = $itemSearchRows[$i]; ?>
                            <tr class="<?php echo intval($r["ITEM_ID"]) == intval($selectedItemId) ? "selected" : ""; ?>"
                                onclick="location.href='depresiasi.php?item_id=<?php echo intval($r["ITEM_ID"]); ?>&cust_id=<?php echo intval($selectedCustId); ?>&periode=<?php echo urlencode($selectedPeriod); ?>&mode=<?php echo urlencode($mode); ?>'">
                                <td><?php echo h($r["ITEM_CODE"]); ?></td>
                                <td><?php echo h($r["ITEM_NO"]); ?></td>
                                <td><?php echo h($r["ITEM_NAME"]); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- FORM INPUT DEPRESIASI -->
            <div class="big-label">QTY DEPRESIASI</div>
            <div class="panel">
                <form method="post" action="depresiasi.php" autocomplete="off">
                    <input type="hidden" name="item_id" value="<?php echo intval($selectedItemId); ?>">
                    <input type="hidden" name="cust_id" value="<?php echo intval($selectedCustId); ?>">
                    <input type="hidden" name="price_id" value="<?php echo intval($selectedPriceId); ?>">
                    <input type="hidden" name="mode" value="<?php echo h($mode); ?>">

                    <div class="row">
                        <label>ITEM</label>
                        <input type="text" class="w120" value="<?php echo h($item["ITEM_CODE"]); ?>" readonly>
                        <input type="text" class="w360" value="<?php echo h($item["ITEM_NAME"]); ?>" readonly>
                    </div>
                    <div class="row">
                        <label>PRICE_ID</label>
                        <input type="text" class="w80" value="<?php echo intval($selectedPriceId); ?>" readonly>
                        
                        <label style="width:80px;">PERIODE</label>
                        <input type="month" name="periode" class="w120" value="<?php echo h(yyyymm_to_month_input($selectedPeriod)); ?>">
                    </div>
                    <div class="row">
                        <label>DEP_QTY</label>
                        <input type="text" id="input_dep_qty" name="DEP_QTY" class="w120" 
                               value="<?php echo h(fmt_num($depForm["DEP_QTY"], 0)); ?>" onkeyup="syncDepresiasi()">
                        
                        <label style="width:100px;">DEPRESIASI</label>
                        <input type="text" id="input_depresiasi" name="DEPRESIASI" class="w120" 
                               value="<?php echo h(fmt_num($depForm["DEPRESIASI"], 0)); ?>">
                        
                        <button type="submit" name="action" value="save_dep" class="btn btn-small">HITUNG / SIMPAN</button>
                        <button type="submit" name="action" value="delete_dep" class="btn btn-small" onclick="return confirm('Delete depresiasi periode ini?');">DELETE</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Kolom Kanan -->
        <div>
            <div class="panel">
                <form method="get" action="depresiasi.php" autocomplete="off">
                    <input type="hidden" name="item_id" value="<?php echo intval($selectedItemId); ?>">
                    <input type="hidden" name="mode" value="<?php echo h($mode); ?>">
                    <a class="btn" target="_blank" href="depresiasi_report.php?TYPE=month">REPORT 12 MONTH</a>
                    <a class="btn" target="_blank" href="depresiasi_report.php?TYPE=year">REPORT 12 YEAR</a>
                </form>
            </div>

            <div class="small-note"><?php echo h($modeTitle); ?> | Month: <?php echo h(month_label($selectedPeriod)); ?></div>

            <div class="panel">
                <div class="section-title">PRICE CUSTOMER UNTUK ITEM TERPILIH</div>
                <div class="grid-wrap grid-small">
                    <table class="grid">
                        <thead>
                            <tr><th>PRICE_ID</th><th>CUST</th><th>COMPANY</th><th>CURR</th><th>PACK QTY</th></tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 0; $i < count($priceRows); $i++) { $r = $priceRows[$i]; ?>
                                <tr class="<?php echo intval($r["PRICE_ID"]) == intval($selectedPriceId) ? "selected" : ""; ?>"
                                    onclick="location.href='depresiasi.php?item_id=<?php echo intval($selectedItemId); ?>&cust_id=<?php echo intval($r["CUST_ID"]); ?>&price_id=<?php echo intval($r["PRICE_ID"]); ?>&periode=<?php echo urlencode($selectedPeriod); ?>&mode=<?php echo urlencode($mode); ?>'">
                                    <td><?php echo h($r["PRICE_ID"]); ?></td>
                                    <td><?php echo h($r["CUST_CODE"]); ?></td>
                                    <td><?php echo h($r["CUST_COMP"]); ?></td>
                                    <td><?php echo h($r["CURR_CODE"]); ?></td>
                                    <td class="right"><?php echo h($r["PRICE_PACK_QTY"]); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="section-title">PRICE_DEPRESIASI</div>
                <div class="grid-wrap grid-small">
                    <table class="grid">
                        <thead>
                            <tr><th>PERIODE</th><th>DEPRESIASI</th><th>DEP_QTY</th><th>CUST</th></tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 0; $i < count($priceDepRows); $i++) { 
                                $r = $priceDepRows[$i]; 
                                $rawPeriode = $r["PERIODE"] instanceof DateTime ? $r["PERIODE"]->format("Y-m-d") : (string)$r["PERIODE"];
                                $rowPeriod = month_to_yyyymm($rawPeriode);
                                $isSelected = ($rowPeriod == $selectedPeriod);
                            ?>
                                <tr class="<?php echo $isSelected ? "selected" : ""; ?>"
                                    onclick="location.href='depresiasi.php?item_id=<?php echo intval($selectedItemId); ?>&cust_id=<?php echo intval($selectedCustId); ?>&price_id=<?php echo intval($r["price_id"]); ?>&periode=<?php echo urlencode($rowPeriod); ?>&mode=<?php echo urlencode($mode); ?>'">
                                    <td><?php echo h(date_out($r["PERIODE"])); ?></td>
                                    <td class="right"><?php echo h(fmt_num($r["DEPRESIASI"], 0)); ?></td>
                                    <td class="right"><?php echo h(fmt_num($r["DEP_QTY"], 0)); ?></td>
                                    <td><?php echo h($r["CUST_CODE"]); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($mode == "po") { ?>
                <div class="panel">
                    <div class="section-title">PO DEPRESIASI</div>
                    <div class="grid-wrap grid-po">
                        <table class="grid">
                            <thead>
                                <tr><th>ORDR_PO</th><th>ORDR_DATE</th><th>ITEM_CODE</th><th>ITEM_NAME</th><th>ORDP_QTY</th><th>ORDP_DQTY</th><th>ORDP_BQTY</th></tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 0; $i < count($poRows); $i++) { $r = $poRows[$i]; ?>
                                    <tr>
                                        <td><?php echo h($r["ORDR_PO"]); ?></td>
                                        <td><?php echo h(date_out_display($r["ORDR_DATE"])); ?></td>
                                        <td><?php echo h($r["ITEM_CODE"]); ?></td>
                                        <td><?php echo h($r["ITEM_NAME"]); ?></td>
                                        <td class="right"><?php echo h(fmt_num($r["ORDP_QTY"], 0)); ?></td>
                                        <td class="right"><?php echo h(fmt_num($r["ORDP_DQTY"], 0)); ?></td>
                                        <td class="right"><?php echo h(fmt_num($r["ORDP_BQTY"], 0)); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<script>
// Fungsi JavaScript Murni Sesuai Standar Legacy Frontend
function acEnc(value) { return encodeURIComponent(value == null ? "" : value); }
function acHtml(value) {
    return String(value == null ? "" : value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

function syncDepresiasi() {
    var qtyInput = document.getElementById('input_dep_qty').value;
    document.getElementById('input_depresiasi').value = qtyInput;
}

function setupAutocomplete(inputId, listId, ajaxMode, renderItem, chooseItem) {
    var input = document.getElementById(inputId);
    var list = document.getElementById(listId);
    if (!input || !list) { return; }

    var rows = [];
    var activeIndex = -1;
    var timer = null;

    function hideList() { list.style.display = "none"; list.innerHTML = ""; rows = []; activeIndex = -1; }
    function setActive(index) {
        var items = list.getElementsByClassName("autocomplete-item");
        if (!items || items.length == 0) { activeIndex = -1; return; }
        if (index < 0) { index = items.length - 1; }
        if (index >= items.length) { index = 0; }
        for (var i = 0; i < items.length; i++) { items[i].className = "autocomplete-item"; }
        items[index].className = "autocomplete-item active";
        activeIndex = index;
    }
    function choose(index) {
        if (index < 0 || index >= rows.length) { return; }
        chooseItem(rows[index]);
        hideList();
    }
    function render(rowsData) {
        list.innerHTML = ""; rows = rowsData || []; activeIndex = -1;
        if (rows.length == 0) { hideList(); return; }
        for (var i = 0; i < rows.length; i++) {
            (function(row, idx) {
                var div = document.createElement("div"); div.className = "autocomplete-item"; div.innerHTML = renderItem(row);
                div.onmouseover = function() { setActive(idx); };
                div.onmousedown = function(e) { if (e && e.preventDefault) { e.preventDefault(); } choose(idx); };
                list.appendChild(div);
            })(rows[i], i);
        }
        list.style.display = "block"; setActive(0);
    }
    function search(q) {
        if (q == "") { hideList(); return; }
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "depresiasi.php?ajax=" + acEnc(ajaxMode) + "&q=" + acEnc(q), true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                var result;
                try { result = JSON.parse(xhr.responseText); } catch (e) { hideList(); return; }
                if (result && result.rows) { render(result.rows); } else { hideList(); }
            }
        };
        xhr.send(null);
    }

    input.onkeyup = function(e) {
        e = e || window.event;
        var key = e.keyCode || e.which;
        if (key == 40) { setActive(activeIndex + 1); return false; }
        if (key == 38) { setActive(activeIndex - 1); return false; }
        if (key == 13) {
            if (rows.length > 0) {
                if (activeIndex < 0) { activeIndex = 0; } choose(activeIndex); return false;
            }
            return true;
        }
        clearTimeout(timer);
        var q = input.value;
        timer = setTimeout(function() { search(q); }, 250);
    };
    input.onfocus = function() { if (input.value != "") { search(input.value); } };
    input.onblur = function() { setTimeout(function() { hideList(); }, 250); };
}

setupAutocomplete(
    "searchItem", "searchItemList", "item",
    function(r) {
        return "<b>" + acHtml(r.ITEM_CODE) + "</b> - " + acHtml(r.ITEM_NAME) + "<div class='autocomplete-sub'>ITEM NO: " + acHtml(r.ITEM_NO) + "</div>";
    },
    function(r) {
        location.href = "depresiasi.php?item_id=" + acEnc(r.ITEM_ID) + "&cust_id=<?php echo intval($selectedCustId); ?>" + "&periode=<?php echo h($selectedPeriod); ?>" + "&mode=<?php echo h($mode); ?>";
    }
);
</script>

</body>
</html>
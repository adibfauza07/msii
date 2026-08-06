<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function safe_trim($value) {
    if ($value === null) {
        return "";
    }
    return trim((string)$value);
}

function get_param($name, $default = "") {
    if (isset($_POST[$name])) {
        return trim($_POST[$name]);
    }
    if (isset($_GET[$name])) {
        return trim($_GET[$name]);
    }
    return $default;
}

function safe_int($value, $default = 0) {
    if ($value === null || $value === "") {
        return $default;
    }
    return intval($value);
}

function safe_float($value, $default = 0) {
    if ($value === null || $value === "") {
        return $default;
    }
    $value = str_replace(",", "", (string)$value);
    return floatval($value);
}

function checked_value($name) {
    return isset($_POST[$name]) ? 1 : 0;
}

function bool_checked($value) {
    return intval($value) == 1 ? " checked" : "";
}

function option_selected($a, $b) {
    return strtoupper(trim((string)$a)) == strtoupper(trim((string)$b)) ? " selected" : "";
}

function date_out($value) {
    if ($value instanceof DateTime) {
        return $value->format("Y-m-d");
    }
    if ($value === null || $value === "") {
        return "";
    }
    $ts = strtotime((string)$value);
    if ($ts === false) {
        return "";
    }
    return date("Y-m-d", $ts);
}

function fmt_num($value, $decimal = 0) {
    if ($value === null || $value === "") {
        $value = 0;
    }
    return number_format((float)$value, $decimal, ".", ",");
}

function sql_error_text() {
    return print_r(sqlsrv_errors(), true);
}

function query_one($conn, $sql, $params) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        return false;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$row) {
        return null;
    }
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

function insert_and_get_id($conn, $sql, $params, $idName) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        return false;
    }

    do {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($row && isset($row[$idName])) {
            return intval($row[$idName]);
        }
    } while (sqlsrv_next_result($stmt));

    return 0;
}

function json_out($data) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data);
    exit();
}

// ==========================================================
// AJAX AUTOCOMPLETE
// ==========================================================
$ajaxMode = isset($_GET["ajax"]) ? trim($_GET["ajax"]) : "";

if ($ajaxMode != "") {
    $qAjax = isset($_GET["q"]) ? trim($_GET["q"]) : "";
    $likeAjax = "%" . $qAjax . "%";

    if ($ajaxMode == "item") {
        $sql = "
            SELECT TOP 20
                ITEM_ID,
                ISNULL(ITEM_CODE, '') AS ITEM_CODE,
                ISNULL(ITEM_NO, '') AS ITEM_NO,
                ISNULL(ITEM_NAME, '') AS ITEM_NAME
            FROM dbo.ITEMS
            WHERE
                (? = ''
                 OR ISNULL(ITEM_CODE, '') LIKE ?
                 OR ISNULL(ITEM_NO, '') LIKE ?
                 OR ISNULL(ITEM_NAME, '') LIKE ?)
            ORDER BY ITEM_CODE
        ";
        $rows = query_all($conn, $sql, array($qAjax, $likeAjax, $likeAjax, $likeAjax));
        $out = array();
        for ($i = 0; $i < count($rows); $i++) {
            $out[] = array(
                "ITEM_ID" => intval($rows[$i]["ITEM_ID"]),
                "ITEM_CODE" => safe_trim($rows[$i]["ITEM_CODE"]),
                "ITEM_NO" => safe_trim($rows[$i]["ITEM_NO"]),
                "ITEM_NAME" => safe_trim($rows[$i]["ITEM_NAME"])
            );
        }
        json_out(array("success" => true, "rows" => $out));
    }

    if ($ajaxMode == "customer") {
        $sql = "
            SELECT TOP 20
                CUST_ID,
                ISNULL(CUST_CODE, '') AS CUST_CODE,
                ISNULL(CUST_ABBR, '') AS CUST_ABBR,
                ISNULL(CUST_COMP, '') AS CUST_COMP,
                ISNULL(CURR_CODE, '') AS CURR_CODE
            FROM dbo.CUST
            WHERE
                (? = ''
                 OR ISNULL(CUST_CODE, '') LIKE ?
                 OR ISNULL(CUST_COMP, '') LIKE ?
                 OR ISNULL(CUST_ABBR, '') LIKE ?)
            ORDER BY CUST_CODE
        ";
        $rows = query_all($conn, $sql, array($qAjax, $likeAjax, $likeAjax, $likeAjax));
        $out = array();
        for ($i = 0; $i < count($rows); $i++) {
            $out[] = array(
                "CUST_ID" => intval($rows[$i]["CUST_ID"]),
                "CUST_CODE" => safe_trim($rows[$i]["CUST_CODE"]),
                "CUST_ABBR" => safe_trim($rows[$i]["CUST_ABBR"]),
                "CUST_COMP" => safe_trim($rows[$i]["CUST_COMP"]),
                "CURR_CODE" => safe_trim($rows[$i]["CURR_CODE"])
            );
        }
        json_out(array("success" => true, "rows" => $out));
    }

    if ($ajaxMode == "currency") {
        $sql = "
            SELECT TOP 20
                ISNULL(CURR_CODE, '') AS CURR_CODE,
                ISNULL(CURR_DESC, '') AS CURR_DESC,
                ISNULL(CURR_SYMBOL, '') AS CURR_SYMBOL,
                ISNULL(CURR_DEC, 0) AS CURR_DEC
            FROM dbo.CURR
            WHERE
                (? = ''
                 OR ISNULL(CURR_CODE, '') LIKE ?
                 OR ISNULL(CURR_DESC, '') LIKE ?)
            ORDER BY CURR_CODE
        ";
        $rows = query_all($conn, $sql, array($qAjax, $likeAjax, $likeAjax));
        $out = array();
        for ($i = 0; $i < count($rows); $i++) {
            $out[] = array(
                "CURR_CODE" => safe_trim($rows[$i]["CURR_CODE"]),
                "CURR_DESC" => safe_trim($rows[$i]["CURR_DESC"]),
                "CURR_SYMBOL" => safe_trim($rows[$i]["CURR_SYMBOL"]),
                "CURR_DEC" => intval($rows[$i]["CURR_DEC"])
            );
        }
        json_out(array("success" => true, "rows" => $out));
    }

    json_out(array("success" => false, "message" => "Mode ajax tidak dikenal.", "rows" => array()));
}

function get_currency_options($conn) {
    return query_all($conn, "SELECT CURR_CODE, CURR_DESC FROM dbo.CURR ORDER BY CURR_CODE", array());
}

function get_pack_options($conn) {
    return query_all($conn, "SELECT PACK_ID, PACK_CODE, PACK_NAME, PACK_DESC FROM dbo.PACK ORDER BY PACK_CODE", array());
}

$tab = get_param("tab", "customer");
if ($tab != "customer" && $tab != "price" && $tab != "currency") {
    $tab = "customer";
}

$action = get_param("action", "");
$msg = "";
$err = "";

$forceItemId = 0;
$forcePriceId = 0;
$forcePrdtStart = "";
$forceCurrCode = "";
$forceRateStart = "";
$forceCustId = 0;

$currencyOptions = get_currency_options($conn);
$packOptions = get_pack_options($conn);

// ==========================================================
// CUSTOMER SAVE / DELETE
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && $action == "save_customer") {
    $tab = "customer";

    $custId = safe_int(get_param("CUST_ID"), 0);
    $custCode = safe_trim(get_param("CUST_CODE"));
    $custComp = safe_trim(get_param("CUST_COMP"));

    if ($custCode == "") {
        $err = "CUST_CODE belum diisi.";
    } elseif ($custComp == "") {
        $err = "CUST_COMP belum diisi.";
    } else {
        if ($custId > 0) {
            $sql = "
                UPDATE dbo.CUST
                SET
                    CUST_CODE = ?, CUST_ABBR = ?, CUST_COMP = ?, CURR_CODE = ?,
                    CUST_ADDR1 = ?, CUST_ADDR2 = ?, CUST_CITY = ?, CUST_PHONE = ?,
                    CUST_FAX = ?, CUST_EMAIL = ?, CUST_CONTA = ?, CUST_TERM = ?,
                    CUST_NPWP = ?, CUST_ALIAS = ?, KPBC_ID = ?, CUST_INACTIVE = ?,
                    CUST_HSNO = ?, CUST_TPB = ?
                WHERE CUST_ID = ?
            ";
            $params = array(
                $custCode,
                get_param("CUST_ABBR"),
                $custComp,
                get_param("CURR_CODE"),
                get_param("CUST_ADDR1"),
                get_param("CUST_ADDR2"),
                get_param("CUST_CITY"),
                get_param("CUST_PHONE"),
                get_param("CUST_FAX"),
                get_param("CUST_EMAIL"),
                get_param("CUST_CONTA"),
                get_param("CUST_TERM"),
                get_param("CUST_NPWP"),
                get_param("CUST_ALIAS"),
                safe_int(get_param("KPBC_ID"), 0),
                checked_value("CUST_INACTIVE"),
                get_param("CUST_HSNO"),
                get_param("CUST_TPB"),
                $custId
            );
            $stmt = sqlsrv_query($conn, $sql, $params);
            if ($stmt === false) {
                $err = "Gagal update customer: " . sql_error_text();
            } else {
                $msg = "Customer berhasil diupdate.";
                $forceCustId = $custId;
            }
        } else {
            $sql = "
                SET NOCOUNT ON;
                INSERT INTO dbo.CUST
                (
                    CUST_CODE, CUST_ABBR, CUST_COMP, CURR_CODE, CUST_ADDR1, CUST_ADDR2,
                    CUST_CITY, CUST_PHONE, CUST_FAX, CUST_EMAIL, CUST_CONTA, CUST_TERM,
                    CUST_NPWP, CUST_ALIAS, KPBC_ID, CUST_INACTIVE, CUST_HSNO, CUST_TPB
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
                SELECT CONVERT(INT, SCOPE_IDENTITY()) AS NEW_ID;
            ";
            $params = array(
                $custCode,
                get_param("CUST_ABBR"),
                $custComp,
                get_param("CURR_CODE"),
                get_param("CUST_ADDR1"),
                get_param("CUST_ADDR2"),
                get_param("CUST_CITY"),
                get_param("CUST_PHONE"),
                get_param("CUST_FAX"),
                get_param("CUST_EMAIL"),
                get_param("CUST_CONTA"),
                get_param("CUST_TERM"),
                get_param("CUST_NPWP"),
                get_param("CUST_ALIAS"),
                safe_int(get_param("KPBC_ID"), 0),
                checked_value("CUST_INACTIVE"),
                get_param("CUST_HSNO"),
                get_param("CUST_TPB")
            );
            $newId = insert_and_get_id($conn, $sql, $params, "NEW_ID");
            if ($newId === false || $newId <= 0) {
                $err = "Gagal insert customer: " . sql_error_text();
            } else {
                $msg = "Customer baru berhasil disimpan.";
                $forceCustId = $newId;
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $action == "delete_customer") {
    $tab = "customer";
    $custId = safe_int(get_param("CUST_ID"), 0);
    if ($custId <= 0) {
        $err = "Pilih customer dulu.";
    } else {
        $stmt = sqlsrv_query($conn, "DELETE FROM dbo.CUST WHERE CUST_ID = ?", array($custId));
        if ($stmt === false) {
            $err = "Gagal delete customer. Kemungkinan sudah dipakai transaksi. " . sql_error_text();
        } else {
            $msg = "Customer berhasil dihapus.";
        }
    }
}

// ==========================================================
// PRICE MASTER DETAIL SAVE / DELETE
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && $action == "save_price_header") {
    $tab = "price";

    $priceId = safe_int(get_param("PRICE_ID"), 0);
    $itemId = safe_int(get_param("ITEM_ID"), 0);
    $custId = safe_int(get_param("PRICE_CUST_ID"), 0);

    if ($itemId <= 0) {
        $err = "ITEM belum dipilih.";
    } elseif ($custId <= 0) {
        $err = "Customer price belum dipilih.";
    } else {
        if ($priceId > 0) {
            $sql = "
                UPDATE dbo.PRICE
                SET
                    PRICE_CODE = ?,
                    PART_ID = ?,
                    CUST_ID = ?,
                    CURR_CODE = ?,
                    PRICE_INACTIVE = ?,
                    PACK_ID = ?,
                    PRICE_PACK_QTY = ?
                WHERE PRICE_ID = ?
            ";
            $params = array(
                get_param("PRICE_CODE"),
                $itemId,
                $custId,
                get_param("PRICE_CURR_CODE"),
                checked_value("PRICE_INACTIVE"),
                safe_int(get_param("PACK_ID"), 0),
                safe_int(get_param("PRICE_PACK_QTY"), 1),
                $priceId
            );
            $stmt = sqlsrv_query($conn, $sql, $params);
            if ($stmt === false) {
                $err = "Gagal update PRICE: " . sql_error_text();
            } else {
                $msg = "PRICE berhasil diupdate.";
                $forceItemId = $itemId;
                $forcePriceId = $priceId;
            }
        } else {
            $sql = "
                SET NOCOUNT ON;
                INSERT INTO dbo.PRICE
                (
                    PRICE_CODE,
                    PART_ID,
                    CUST_ID,
                    CURR_CODE,
                    PRICE_INACTIVE,
                    PACK_ID,
                    PRICE_PACK_QTY
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?);
                SELECT CONVERT(INT, SCOPE_IDENTITY()) AS NEW_ID;
            ";
            $params = array(
                get_param("PRICE_CODE"),
                $itemId,
                $custId,
                get_param("PRICE_CURR_CODE"),
                checked_value("PRICE_INACTIVE"),
                safe_int(get_param("PACK_ID"), 0),
                safe_int(get_param("PRICE_PACK_QTY"), 1)
            );
            $newId = insert_and_get_id($conn, $sql, $params, "NEW_ID");
            if ($newId === false || $newId <= 0) {
                $err = "Gagal insert PRICE: " . sql_error_text();
            } else {
                $msg = "PRICE baru berhasil disimpan.";
                $forceItemId = $itemId;
                $forcePriceId = $newId;
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $action == "delete_price_header") {
    $tab = "price";
    $priceId = safe_int(get_param("PRICE_ID"), 0);
    $itemId = safe_int(get_param("ITEM_ID"), 0);

    if ($priceId <= 0) {
        $err = "Pilih PRICE dulu.";
    } else {
        if (!sqlsrv_begin_transaction($conn)) {
            $err = "Gagal mulai transaksi: " . sql_error_text();
        } else {
            try {
                $stmt1 = sqlsrv_query($conn, "DELETE FROM dbo.PRICE_DETAIL WHERE PRICE_ID = ?", array($priceId));
                if ($stmt1 === false) {
                    throw new Exception("Gagal hapus PRICE_DETAIL: " . sql_error_text());
                }
                $stmt2 = sqlsrv_query($conn, "DELETE FROM dbo.PRICE WHERE PRICE_ID = ?", array($priceId));
                if ($stmt2 === false) {
                    throw new Exception("Gagal hapus PRICE: " . sql_error_text());
                }
                sqlsrv_commit($conn);
                $msg = "PRICE dan detail berhasil dihapus.";
                $forceItemId = $itemId;
            } catch (Exception $e) {
                sqlsrv_rollback($conn);
                $err = $e->getMessage();
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $action == "save_price_detail") {
    $tab = "price";

    $priceId = safe_int(get_param("DETAIL_PRICE_ID"), 0);
    $itemId = safe_int(get_param("ITEM_ID"), 0);
    $oldStart = get_param("PRDT_START_OLD");
    $start = get_param("PRDT_START");
    $end = get_param("PRDT_END");

    if ($priceId <= 0) {
        $err = "Pilih PRICE dulu sebelum simpan detail.";
    } elseif ($start == "") {
        $err = "Price Start belum diisi.";
    } else {
        $exists = null;
        if ($oldStart != "") {
            $exists = query_one(
                $conn,
                "SELECT TOP 1 PRICE_ID FROM dbo.PRICE_DETAIL WHERE PRICE_ID = ? AND CONVERT(VARCHAR(10), PRDT_START, 120) = ?",
                array($priceId, $oldStart)
            );
        }

        if ($exists) {
            $sql = "
                UPDATE dbo.PRICE_DETAIL
                SET
                    PRDT_START = ?,
                    PRDT_END = ?,
                    PRDT_PRICE = ?,
                    PRDT_QNO = ?,
                    PRDT_POSTED = ?,
                    CURR_CODE = ?
                WHERE PRICE_ID = ?
                  AND CONVERT(VARCHAR(10), PRDT_START, 120) = ?
            ";
            $params = array(
                $start,
                $end,
                safe_float(get_param("PRDT_PRICE"), 0),
                get_param("PRDT_QNO"),
                checked_value("PRDT_POSTED"),
                get_param("DETAIL_CURR_CODE"),
                $priceId,
                $oldStart
            );
        } else {
            $sql = "
                INSERT INTO dbo.PRICE_DETAIL
                (
                    PRICE_ID,
                    PRDT_START,
                    PRDT_END,
                    PRDT_PRICE,
                    PRDT_QNO,
                    PRDT_POSTED,
                    CURR_CODE
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?)
            ";
            $params = array(
                $priceId,
                $start,
                $end,
                safe_float(get_param("PRDT_PRICE"), 0),
                get_param("PRDT_QNO"),
                checked_value("PRDT_POSTED"),
                get_param("DETAIL_CURR_CODE")
            );
        }

        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            $err = "Gagal simpan PRICE_DETAIL: " . sql_error_text();
        } else {
            $msg = "PRICE_DETAIL berhasil disimpan.";
            $forceItemId = $itemId;
            $forcePriceId = $priceId;
            $forcePrdtStart = $start;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $action == "delete_price_detail") {
    $tab = "price";

    $priceId = safe_int(get_param("DETAIL_PRICE_ID"), 0);
    $itemId = safe_int(get_param("ITEM_ID"), 0);
    $oldStart = get_param("PRDT_START_OLD");

    if ($priceId <= 0 || $oldStart == "") {
        $err = "Pilih PRICE_DETAIL dulu.";
    } else {
        $stmt = sqlsrv_query(
            $conn,
            "DELETE FROM dbo.PRICE_DETAIL WHERE PRICE_ID = ? AND CONVERT(VARCHAR(10), PRDT_START, 120) = ?",
            array($priceId, $oldStart)
        );
        if ($stmt === false) {
            $err = "Gagal delete PRICE_DETAIL: " . sql_error_text();
        } else {
            $msg = "PRICE_DETAIL berhasil dihapus.";
            $forceItemId = $itemId;
            $forcePriceId = $priceId;
        }
    }
}

// ==========================================================
// LOAD CUSTOMER TAB
// ==========================================================
$selectedCustId = $forceCustId > 0 ? $forceCustId : safe_int(get_param("cust_id"), 0);
$qCustomer = get_param("q_customer", "");

$customer = array(
    "CUST_ID" => "", "CUST_CODE" => "", "CUST_ABBR" => "", "CUST_COMP" => "", "CURR_CODE" => "",
    "CUST_ADDR1" => "", "CUST_ADDR2" => "", "CUST_CITY" => "", "CUST_PHONE" => "", "CUST_FAX" => "",
    "CUST_EMAIL" => "", "CUST_CONTA" => "", "CUST_TERM" => "", "CUST_NPWP" => "", "CUST_ALIAS" => "",
    "KPBC_ID" => "", "CUST_INACTIVE" => 0, "CUST_HSNO" => "", "CUST_TPB" => ""
);

if ($selectedCustId > 0) {
    $row = query_one($conn, "SELECT TOP 1 * FROM dbo.CUST WHERE CUST_ID = ?", array($selectedCustId));
    if ($row) {
        $customer = array_merge($customer, $row);
    }
}

$likeCustomer = "%" . $qCustomer . "%";
$customerList = query_all(
    $conn,
    "
    SELECT TOP 200 CUST_ID, CUST_CODE, CUST_ABBR, CUST_COMP, CURR_CODE, CUST_CITY, CUST_PHONE, CUST_INACTIVE
    FROM dbo.CUST
    WHERE (? = '' OR CUST_CODE LIKE ? OR CUST_COMP LIKE ? OR CUST_ABBR LIKE ?)
    ORDER BY CUST_CODE
    ",
    array($qCustomer, $likeCustomer, $likeCustomer, $likeCustomer)
);

// ==========================================================
// LOAD PRICE TAB MASTER DETAIL
// ==========================================================
$isNewPrice = get_param("new_price", "") == "1";
$isNewDetail = get_param("new_detail", "") == "1"; // Ditambahkan untuk New Detail logic

$selectedItemId = $forceItemId > 0 ? $forceItemId : safe_int(get_param("item_id"), 0);
$selectedPriceId = $forcePriceId > 0 ? $forcePriceId : safe_int(get_param("price_id"), 0);
$selectedPrdtStart = $forcePrdtStart != "" ? $forcePrdtStart : get_param("prdt_start", "");
$qItem = get_param("q_item", "");

if ($isNewPrice) {
    $selectedPriceId = 0;
    $selectedPrdtStart = "";
}
if ($isNewDetail) {
    $selectedPrdtStart = "";
}

$item = array("ITEM_ID" => "", "ITEM_CODE" => "", "ITEM_NO" => "", "ITEM_NAME" => "");

if ($selectedItemId <= 0 && $qItem != "") {
    $likeItemFind = "%" . $qItem . "%";
    $foundItem = query_one(
        $conn,
        "
        SELECT TOP 1 ITEM_ID, ITEM_CODE, ITEM_NO, ITEM_NAME
        FROM dbo.ITEMS
        WHERE ITEM_CODE LIKE ? OR ITEM_NO LIKE ? OR ITEM_NAME LIKE ?
        ORDER BY ITEM_CODE
        ",
        array($likeItemFind, $likeItemFind, $likeItemFind)
    );
    if ($foundItem) {
        $selectedItemId = intval($foundItem["ITEM_ID"]);
    }
}

if ($selectedItemId > 0) {
    $row = query_one($conn, "SELECT TOP 1 ITEM_ID, ITEM_CODE, ITEM_NO, ITEM_NAME FROM dbo.ITEMS WHERE ITEM_ID = ?", array($selectedItemId));
    if ($row) {
        $item = array_merge($item, $row);
    }
}

$priceRows = array();

if ($selectedItemId > 0) {
    $priceRows = query_all(
        $conn,
        "
        SELECT
            P.PRICE_ID,
            P.PRICE_CODE,
            P.PART_ID,
            P.CUST_ID,
            P.CURR_CODE,
            ISNULL(P.PRICE_INACTIVE, 0) AS PRICE_INACTIVE,
            P.PACK_ID,
            P.PRICE_PACK_QTY,
            C.CUST_CODE,
            C.CUST_COMP,
            ISNULL(PK.PACK_CODE, '') AS PACKING,
            ISNULL(PK.PACK_DESC, '') AS PACK_DESC
        FROM dbo.PRICE AS P
        INNER JOIN dbo.CUST AS C
            ON P.CUST_ID = C.CUST_ID
        LEFT JOIN dbo.PACK AS PK
            ON P.PACK_ID = PK.PACK_ID
        WHERE P.PART_ID = ?
        ORDER BY C.CUST_CODE, P.PRICE_ID
        ",
        array($selectedItemId)
    );

    if (!$isNewPrice && $selectedPriceId <= 0 && count($priceRows) > 0) {
        $selectedPriceId = intval($priceRows[0]["PRICE_ID"]);
    }
}

$priceData = array(
    "PRICE_ID" => "", "PRICE_CODE" => "", "PART_ID" => $selectedItemId, "CUST_ID" => "",
    "CUST_CODE" => "", "CUST_COMP" => "", "CURR_CODE" => "", "PRICE_INACTIVE" => 0,
    "PACK_ID" => "", "PRICE_PACK_QTY" => 1, "PACKING" => "", "PACK_DESC" => ""
);

if ($selectedPriceId > 0) {
    $row = query_one(
        $conn,
        "
        SELECT TOP 1
            P.PRICE_ID, P.PRICE_CODE, P.PART_ID, P.CUST_ID, P.CURR_CODE,
            ISNULL(P.PRICE_INACTIVE, 0) AS PRICE_INACTIVE,
            P.PACK_ID, P.PRICE_PACK_QTY,
            C.CUST_CODE, C.CUST_COMP,
            ISNULL(PK.PACK_CODE, '') AS PACKING,
            ISNULL(PK.PACK_DESC, '') AS PACK_DESC
        FROM dbo.PRICE AS P
        INNER JOIN dbo.CUST AS C
            ON P.CUST_ID = C.CUST_ID
        LEFT JOIN dbo.PACK AS PK
            ON P.PACK_ID = PK.PACK_ID
        WHERE P.PRICE_ID = ?
        ",
        array($selectedPriceId)
    );
    if ($row) {
        $priceData = array_merge($priceData, $row);
    }
}

$priceDetailRows = array();

if ($selectedPriceId > 0) {
    $priceDetailRows = query_all(
        $conn,
        "
        SELECT
            PRICE_ID,
            PRDT_START,
            PRDT_END,
            PRDT_PRICE,
            PRDT_QNO,
            ISNULL(PRDT_POSTED, 0) AS PRDT_POSTED,
            ISNULL(CURR_CODE, '') AS CURR_CODE
        FROM dbo.PRICE_DETAIL
        WHERE PRICE_ID = ?
        ORDER BY PRDT_START DESC
        ",
        array($selectedPriceId)
    );

    // Default row hanya dipasang jika bukan dalam mode NEW DETAIL
    if ($selectedPrdtStart == "" && count($priceDetailRows) > 0 && !$isNewDetail) {
        $selectedPrdtStart = date_out($priceDetailRows[0]["PRDT_START"]);
    }
}

$detailData = array(
    "PRICE_ID" => $selectedPriceId,
    "PRDT_START" => "",
    "PRDT_END" => "",
    "PRDT_PRICE" => "",
    "PRDT_QNO" => "",
    "PRDT_POSTED" => 0,
    "CURR_CODE" => safe_trim($priceData["CURR_CODE"])
);

if ($selectedPriceId > 0 && $selectedPrdtStart != "") {
    $row = query_one(
        $conn,
        "
        SELECT TOP 1
            PRICE_ID, PRDT_START, PRDT_END, PRDT_PRICE, PRDT_QNO,
            ISNULL(PRDT_POSTED, 0) AS PRDT_POSTED,
            ISNULL(CURR_CODE, '') AS CURR_CODE
        FROM dbo.PRICE_DETAIL
        WHERE PRICE_ID = ?
          AND CONVERT(VARCHAR(10), PRDT_START, 120) = ?
        ",
        array($selectedPriceId, $selectedPrdtStart)
    );
    if ($row) {
        $detailData = array_merge($detailData, $row);
    }
}

// ==========================================================
// LOAD CURRENCY TAB
// ==========================================================
$selectedCurrCode = $forceCurrCode != "" ? $forceCurrCode : get_param("curr_code", "");
$selectedRateSDate = $forceRateStart != "" ? $forceRateStart : get_param("rate_sdate", "");

$currency = array("CURR_CODE" => "", "CURR_DESC" => "", "CURR_SYMBOL" => "", "CURR_DEC" => "");
if ($selectedCurrCode != "") {
    $row = query_one($conn, "SELECT TOP 1 * FROM dbo.CURR WHERE CURR_CODE = ?", array($selectedCurrCode));
    if ($row) {
        $currency = array_merge($currency, $row);
    }
}

$currencyList = query_all($conn, "SELECT TOP 200 CURR_CODE, CURR_DESC, CURR_SYMBOL, CURR_DEC FROM dbo.CURR ORDER BY CURR_CODE", array());

$rateData = array("CURR_CODE" => $selectedCurrCode, "CURR_SDATE" => "", "CURR_EDATE" => "", "CURR_CRATE" => "", "CURR_VRATE" => "", "CURR_MM" => "", "CURR_YY" => "");
$rateList = array();

if ($selectedCurrCode != "") {
    $rateList = query_all(
        $conn,
        "SELECT CURR_CODE, CURR_SDATE, CURR_EDATE, CURR_CRATE, CURR_VRATE, CURR_MM, CURR_YY FROM dbo.CURR_RAT WHERE CURR_CODE = ? ORDER BY CURR_SDATE DESC",
        array($selectedCurrCode)
    );

    if ($selectedRateSDate == "" && count($rateList) > 0) {
        $selectedRateSDate = date_out($rateList[0]["CURR_SDATE"]);
    }

    if ($selectedRateSDate != "") {
        $row = query_one(
            $conn,
            "SELECT TOP 1 * FROM dbo.CURR_RAT WHERE CURR_CODE = ? AND CONVERT(VARCHAR(10), CURR_SDATE, 120) = ?",
            array($selectedCurrCode, $selectedRateSDate)
        );
        if ($row) {
            $rateData = array_merge($rateData, $row);
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Master - Ordering System Plant 2</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #d4d0c8;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            color: #000000;
        }
        .topbar {
            background: #000080;
            color: #ffffff;
            padding: 8px 12px;
            font-weight: bold;
            text-align: center;
            letter-spacing: 1px;
        }
        .wrap {
            width: 1180px;
            margin: 12px auto;
            border: 1px solid #666666;
            background: #a7c9e9;
            padding: 10px;
            box-sizing: border-box;
        }
        .tabs {
            border-bottom: 1px solid #666666;
            margin-bottom: 10px;
        }
        .tabs a {
            display: inline-block;
            padding: 6px 14px;
            border: 1px solid #666666;
            border-bottom: none;
            background: #d4d0c8;
            color: #000000;
            text-decoration: none;
            margin-right: 2px;
        }
        .tabs a.active {
            background: #ffffff;
            font-weight: bold;
        }
        .title {
            font-size: 22px;
            font-weight: bold;
            margin: 6px 0 10px 0;
            letter-spacing: 1px;
        }
        .msg {
            padding: 8px;
            background: #e9ffe9;
            border: 1px solid #008000;
            margin-bottom: 8px;
            color: #004000;
        }
        .err {
            padding: 8px;
            background: #ffe9e9;
            border: 1px solid #800000;
            margin-bottom: 8px;
            color: #800000;
            white-space: pre-wrap;
        }
        .panel {
            border: 1px solid #666666;
            background: #b6d3ef;
            padding: 10px;
            box-sizing: border-box;
            margin-bottom: 10px;
        }
        .row {
            margin-bottom: 6px;
            white-space: nowrap;
        }
        label {
            display: inline-block;
            width: 88px;
            vertical-align: middle;
        }
        input[type="text"], input[type="date"], input[type="number"], select {
            height: 23px;
            border: 1px solid #777777;
            background: #ffffff;
            font-size: 12px;
            box-sizing: border-box;
            padding: 2px 4px;
        }
        input[readonly] {
            background: #eeeeee;
        }
        .w50 { width: 50px; }
        .w70 { width: 70px; }
        .w80 { width: 80px; }
        .w100 { width: 100px; }
        .w120 { width: 120px; }
        .w150 { width: 150px; }
        .w180 { width: 180px; }
        .w220 { width: 220px; }
        .w300 { width: 300px; }
        .w360 { width: 360px; }
        .w500 { width: 500px; }
        .w620 { width: 620px; }
        .w760 { width: 760px; }
        .btn {
            display: inline-block;
            border: 2px outset #ffffff;
            background: #d4d0c8;
            color: #000000;
            padding: 5px 12px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            margin-right: 4px;
        }
        .btn:active {
            border: 2px inset #ffffff;
        }
        .split-price {
            display: grid;
            grid-template-columns: 640px 1fr;
            gap: 10px;
        }
        .split-two {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .grid-wrap {
            height: 250px;
            overflow: auto;
            border: 1px solid #666666;
            background: #ffffff;
        }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            font-size: 12px;
        }
        table.grid th, table.grid td {
            border: 1px solid #999999;
            padding: 4px 5px;
            white-space: nowrap;
        }
        table.grid th {
            background: #d4d0c8;
            font-weight: bold;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        table.grid tr:hover td {
            background: #cfe5ff;
        }
        table.grid tr.selected td {
            background: #2f70c9;
            color: #ffffff;
        }
        .right { text-align: right; }
        .center { text-align: center; }
        .small-note {
            color: #000080;
            font-weight: bold;
            margin: 4px 0 6px 0;
        }
        .section-title {
            font-weight: bold;
            margin: 0 0 6px 0;
            color: #000000;
        }
        .autocomplete-box {
            position: relative;
            display: inline-block;
            vertical-align: middle;
        }
        .autocomplete-list {
            position: absolute;
            left: 0;
            top: 24px;
            width: 540px;
            max-height: 240px;
            overflow-y: auto;
            background: #ffffff;
            border: 1px solid #333333;
            z-index: 9999;
            display: none;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.25);
        }
        .autocomplete-item {
            padding: 5px 7px;
            border-bottom: 1px solid #dddddd;
            cursor: pointer;
            line-height: 16px;
            background: #ffffff;
        }
        .autocomplete-item:hover,
        .autocomplete-item.active {
            background: #2f70c9;
            color: #ffffff;
        }
        .autocomplete-sub {
            font-size: 11px;
            color: #555555;
        }
        .autocomplete-item:hover .autocomplete-sub,
        .autocomplete-item.active .autocomplete-sub {
            color: #ffffff;
        }
    </style>
</head>
<body>

<div class="topbar">ORDERING SYSTEM - PLANT 2</div>

<div class="wrap">
    <div class="tabs">
        <a href="master.php?tab=customer" class="<?php echo $tab == "customer" ? "active" : ""; ?>">CUSTOMER</a>
        <a href="master.php?tab=price" class="<?php echo $tab == "price" ? "active" : ""; ?>">PRICE</a>
        <a href="master.php?tab=currency" class="<?php echo $tab == "currency" ? "active" : ""; ?>">CURRENCY</a>
    </div>

    <?php if ($msg != "") { ?>
        <div class="msg"><?php echo h($msg); ?></div>
    <?php } ?>

    <?php if ($err != "") { ?>
        <div class="err"><?php echo h($err); ?></div>
    <?php } ?>

    <?php if ($tab == "customer") { ?>
        <div class="title">MASTER CUSTOMER</div>

        <div class="panel">
            <form method="get" action="master.php" autocomplete="off">
                <input type="hidden" name="tab" value="customer">
                Search Customer:
                <span class="autocomplete-box">
                    <input type="text" id="searchCustomer" name="q_customer" class="w360" value="<?php echo h($qCustomer); ?>" placeholder="Ketik kode / nama / abbr customer">
                    <div id="searchCustomerList" class="autocomplete-list"></div>
                </span>
                <button type="submit" class="btn">CARI</button>
                <a class="btn" href="master.php?tab=customer">NEW</a>
            </form>
        </div>

        <div class="panel">
            <form method="post" action="master.php?tab=customer" autocomplete="off">
                <input type="hidden" name="CUST_ID" value="<?php echo h($customer["CUST_ID"]); ?>">

                <div class="row">
                    <label>CUST_CODE</label>
                    <input type="text" name="CUST_CODE" class="w80" value="<?php echo h($customer["CUST_CODE"]); ?>">
                    <label style="width:80px;">CUST_ABBR</label>
                    <input type="text" name="CUST_ABBR" class="w100" value="<?php echo h($customer["CUST_ABBR"]); ?>">
                    <label style="width:80px;">CUST_COMP</label>
                    <input type="text" name="CUST_COMP" class="w500" value="<?php echo h($customer["CUST_COMP"]); ?>">
                </div>

                <div class="row">
                    <label>CURR_CODE</label>
                    <select name="CURR_CODE" class="w100">
                        <option value=""></option>
                        <?php for ($i = 0; $i < count($currencyOptions); $i++) { ?>
                            <option value="<?php echo h($currencyOptions[$i]["CURR_CODE"]); ?>"<?php echo option_selected($customer["CURR_CODE"], $currencyOptions[$i]["CURR_CODE"]); ?>><?php echo h($currencyOptions[$i]["CURR_CODE"]); ?></option>
                        <?php } ?>
                    </select>
                    <label style="width:80px;">INACTIVE</label>
                    <input type="checkbox" name="CUST_INACTIVE" value="1"<?php echo bool_checked($customer["CUST_INACTIVE"]); ?>>
                </div>

                <div class="row"><label>CUST_ADDR1</label><input type="text" name="CUST_ADDR1" class="w760" value="<?php echo h($customer["CUST_ADDR1"]); ?>"></div>
                <div class="row"><label>CUST_ADDR2</label><input type="text" name="CUST_ADDR2" class="w760" value="<?php echo h($customer["CUST_ADDR2"]); ?>"></div>
                <div class="row">
                    <label>CUST_CITY</label><input type="text" name="CUST_CITY" class="w220" value="<?php echo h($customer["CUST_CITY"]); ?>">
                    <label>CUST_PHONE</label><input type="text" name="CUST_PHONE" class="w220" value="<?php echo h($customer["CUST_PHONE"]); ?>">
                </div>
                <div class="row"><label>CUST_FAX</label><input type="text" name="CUST_FAX" class="w360" value="<?php echo h($customer["CUST_FAX"]); ?>"></div>
                <div class="row"><label>CUST_EMAIL</label><input type="text" name="CUST_EMAIL" class="w760" value="<?php echo h($customer["CUST_EMAIL"]); ?>"></div>
                <div class="row">
                    <label>CUST_CONTA</label><input type="text" name="CUST_CONTA" class="w220" value="<?php echo h($customer["CUST_CONTA"]); ?>">
                    <label>CUST_TERM</label><input type="text" name="CUST_TERM" class="w120" value="<?php echo h($customer["CUST_TERM"]); ?>">
                    <label>CUST_NPWP</label><input type="text" name="CUST_NPWP" class="w220" value="<?php echo h($customer["CUST_NPWP"]); ?>">
                </div>
                <div class="row">
                    <label>CUST_ALIAS</label><input type="text" name="CUST_ALIAS" class="w180" value="<?php echo h($customer["CUST_ALIAS"]); ?>">
                    <label>KPBC_ID</label><input type="number" name="KPBC_ID" class="w80" value="<?php echo h($customer["KPBC_ID"]); ?>">
                    <label>CUST_HSNO</label><input type="text" name="CUST_HSNO" class="w120" value="<?php echo h($customer["CUST_HSNO"]); ?>">
                    <label>CUST_TPB</label><input type="text" name="CUST_TPB" class="w120" value="<?php echo h($customer["CUST_TPB"]); ?>">
                </div>
                <div class="row">
                    <button type="submit" name="action" value="save_customer" class="btn">SIMPAN</button>
                    <button type="submit" name="action" value="delete_customer" class="btn" onclick="return confirm('Delete customer ini?');">DELETE</button>
                    <a class="btn" href="master.php?tab=customer">BATAL / NEW</a>
                </div>
            </form>
        </div>

        <div class="small-note">Customer loaded: <?php echo count($customerList); ?></div>
        <div class="grid-wrap">
            <table class="grid">
                <thead><tr><th>CODE</th><th>ABBR</th><th>COMPANY</th><th>CURR</th><th>CITY</th><th>PHONE</th><th>INACTIVE</th></tr></thead>
                <tbody>
                    <?php for ($i = 0; $i < count($customerList); $i++) { $r = $customerList[$i]; ?>
                        <tr class="<?php echo intval($customer["CUST_ID"]) == intval($r["CUST_ID"]) ? "selected" : ""; ?>" onclick="location.href='master.php?tab=customer&cust_id=<?php echo intval($r["CUST_ID"]); ?>'">
                            <td><?php echo h($r["CUST_CODE"]); ?></td>
                            <td><?php echo h($r["CUST_ABBR"]); ?></td>
                            <td><?php echo h($r["CUST_COMP"]); ?></td>
                            <td><?php echo h($r["CURR_CODE"]); ?></td>
                            <td><?php echo h($r["CUST_CITY"]); ?></td>
                            <td><?php echo h($r["CUST_PHONE"]); ?></td>
                            <td><?php echo intval($r["CUST_INACTIVE"]) == 1 ? "YES" : ""; ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>

    <?php if ($tab == "price") { ?>
        <div class="title">MASTER TABLE PRICE</div>

        <div class="panel">
            <form method="get" action="master.php" autocomplete="off">
                <input type="hidden" name="tab" value="price">
                <label>ITEM CODE</label>
                <span class="autocomplete-box">
                    <input type="text" id="searchItem" name="q_item" class="w180" value="<?php echo h($item["ITEM_CODE"]); ?>" placeholder="Ketik item code / name">
                    <div id="searchItemList" class="autocomplete-list"></div>
                </span>
                <button type="submit" class="btn">CARI ITEM</button>
                <a class="btn" href="master.php?tab=price">REFRESH</a>
                <a class="btn" href="dashboard.php">CLOSE</a>
            </form>

            <div class="row" style="margin-top:8px;">
                <label>ITEM NO</label>
                <input type="text" class="w220" value="<?php echo h($item["ITEM_NO"]); ?>" readonly>
            </div>
            <div class="row">
                <label>ITEM NAME</label>
                <input type="text" class="w620" value="<?php echo h($item["ITEM_NAME"]); ?>" readonly>
            </div>
        </div>

        <div class="split-price">
            <div>
                <div class="panel">
                    <div class="section-title">PRICE MASTER PER CUSTOMER</div>
                    <form method="post" action="master.php?tab=price" autocomplete="off">
                        <input type="hidden" name="ITEM_ID" value="<?php echo intval($selectedItemId); ?>">
                        <input type="hidden" name="PRICE_ID" value="<?php echo h($priceData["PRICE_ID"]); ?>">
                        <input type="hidden" id="PRICE_CUST_ID" name="PRICE_CUST_ID" value="<?php echo h($priceData["CUST_ID"]); ?>">

                        <div class="row">
                            <label>PRICE_ID</label>
                            <input type="text" class="w80" value="<?php echo h($priceData["PRICE_ID"]); ?>" readonly>
                            <label style="width:80px;">CUSTOMER</label>
                            <span class="autocomplete-box">
                                <input type="text" id="priceCustomerText" class="w300" value="<?php echo h(trim($priceData["CUST_CODE"] . " " . $priceData["CUST_COMP"])); ?>" placeholder="Ketik customer">
                                <div id="priceCustomerList" class="autocomplete-list"></div>
                            </span>
                        </div>
                        <div class="row">
                            <label>PR_CD</label>
                            <input type="text" name="PRICE_CODE" class="w120" value="<?php echo h($priceData["PRICE_CODE"]); ?>">
                            <label style="width:80px;">CURR</label>
                            <select id="PRICE_CURR_CODE" name="PRICE_CURR_CODE" class="w100">
                                <option value=""></option>
                                <?php for ($i = 0; $i < count($currencyOptions); $i++) { ?>
                                    <option value="<?php echo h($currencyOptions[$i]["CURR_CODE"]); ?>"<?php echo option_selected($priceData["CURR_CODE"], $currencyOptions[$i]["CURR_CODE"]); ?>><?php echo h($currencyOptions[$i]["CURR_CODE"]); ?></option>
                                <?php } ?>
                            </select>
                            <label style="width:70px;">NA</label>
                            <input type="checkbox" name="PRICE_INACTIVE" value="1"<?php echo bool_checked($priceData["PRICE_INACTIVE"]); ?>>
                        </div>
                        <div class="row">
                            <label>PACKING</label>
                            <select name="PACK_ID" class="w180">
                                <option value="0"></option>
                                <?php for ($i = 0; $i < count($packOptions); $i++) { ?>
                                    <option value="<?php echo intval($packOptions[$i]["PACK_ID"]); ?>"<?php echo intval($priceData["PACK_ID"]) == intval($packOptions[$i]["PACK_ID"]) ? " selected" : ""; ?>>
                                        <?php echo h($packOptions[$i]["PACK_CODE"]); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <label style="width:80px;">PACK QTY</label>
                            <input type="number" name="PRICE_PACK_QTY" class="w80" value="<?php echo h($priceData["PRICE_PACK_QTY"]); ?>">
                        </div>
                        <div class="row">
                            <button type="submit" name="action" value="save_price_header" class="btn">SIMPAN PRICE</button>
                            <button type="submit" name="action" value="delete_price_header" class="btn" onclick="return confirm('Delete PRICE dan semua detail price ini?');">DELETE PRICE</button>
                            <a class="btn" href="master.php?tab=price&item_id=<?php echo intval($selectedItemId); ?>&new_price=1">NEW PRICE</a>
                        </div>
                    </form>
                </div>

                <div class="small-note">Price customer loaded: <?php echo count($priceRows); ?></div>
                <div class="grid-wrap" style="height:190px;">
                    <table class="grid">
                        <thead>
                            <tr>
                                <th>CUST_CODE</th>
                                <th>CUST_COMP</th>
                                <th>PR_CD</th>
                                <th>CURR</th>
                                <th>PACKING</th>
                                <th>PACK QTY</th>
                                <th>NA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 0; $i < count($priceRows); $i++) { $r = $priceRows[$i]; ?>
                                <tr class="<?php echo intval($r["PRICE_ID"]) == intval($selectedPriceId) ? "selected" : ""; ?>" onclick="location.href='master.php?tab=price&item_id=<?php echo intval($selectedItemId); ?>&price_id=<?php echo intval($r["PRICE_ID"]); ?>'">
                                    <td><?php echo h($r["CUST_CODE"]); ?></td>
                                    <td><?php echo h($r["CUST_COMP"]); ?></td>
                                    <td><?php echo h($r["PRICE_CODE"]); ?></td>
                                    <td><?php echo h($r["CURR_CODE"]); ?></td>
                                    <td><?php echo h($r["PACKING"]); ?></td>
                                    <td class="right"><?php echo h($r["PRICE_PACK_QTY"]); ?></td>
                                    <td class="center"><?php echo intval($r["PRICE_INACTIVE"]) == 1 ? "✓" : ""; ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <div class="panel">
                    <div class="section-title">PRICE DETAIL</div>
                    <form method="post" action="master.php?tab=price" autocomplete="off">
                        <input type="hidden" name="ITEM_ID" value="<?php echo intval($selectedItemId); ?>">
                        <input type="hidden" name="DETAIL_PRICE_ID" value="<?php echo intval($selectedPriceId); ?>">
                        <input type="hidden" name="PRDT_START_OLD" value="<?php echo h(date_out($detailData["PRDT_START"])); ?>">

                        <div class="row">
                            <label>PRICE_ID</label>
                            <input type="text" class="w80" value="<?php echo intval($selectedPriceId); ?>" readonly>
                            <label style="width:80px;">CURR</label>
                            <select name="DETAIL_CURR_CODE" class="w100">
                                <option value=""></option>
                                <?php for ($i = 0; $i < count($currencyOptions); $i++) { ?>
                                    <option value="<?php echo h($currencyOptions[$i]["CURR_CODE"]); ?>"<?php echo option_selected($detailData["CURR_CODE"], $currencyOptions[$i]["CURR_CODE"]); ?>><?php echo h($currencyOptions[$i]["CURR_CODE"]); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="row">
                            <label>Price Start</label>
                            <input type="date" name="PRDT_START" class="w150" value="<?php echo h(date_out($detailData["PRDT_START"])); ?>">
                            <label style="width:80px;">Price End</label>
                            <input type="date" name="PRDT_END" class="w150" value="<?php echo h(date_out($detailData["PRDT_END"])); ?>">
                        </div>
                        <div class="row">
                            <label>PRDT_PRICE</label>
                            <!-- Password protection handler ditambahkan di baris ini -->
                            <input type="text" name="PRDT_PRICE" class="w120" value="<?php echo h($detailData["PRDT_PRICE"]); ?>" readonly onclick="checkPricePassword(this)" title="Klik untuk edit" style="cursor:pointer;" placeholder="Click to Unlock">
                            <label style="width:80px;">#Quotation</label>
                            <input type="text" name="PRDT_QNO" class="w180" value="<?php echo h($detailData["PRDT_QNO"]); ?>">
                        </div>
                        <div class="row">
                            <label>POSTED</label>
                            <input type="checkbox" name="PRDT_POSTED" value="1"<?php echo bool_checked($detailData["PRDT_POSTED"]); ?>>
                        </div>
                        <div class="row">
                            <button type="submit" name="action" value="save_price_detail" class="btn">SIMPAN DETAIL</button>
                            <button type="submit" name="action" value="delete_price_detail" class="btn" onclick="return confirm('Delete price detail ini?');">DELETE DETAIL</button>
                            <!-- Mengirimkan flag new_detail=1 -->
                            <a class="btn" href="master.php?tab=price&item_id=<?php echo intval($selectedItemId); ?>&price_id=<?php echo intval($selectedPriceId); ?>&new_detail=1">NEW DETAIL</a>
                        </div>
                    </form>
                </div>

                <div class="small-note">Price detail loaded: <?php echo count($priceDetailRows); ?></div>
                <div class="grid-wrap" style="height:250px;">
                    <table class="grid">
                        <thead>
                            <tr>
                                <th>Price Start</th>
                                <th>Price End</th>
                                <th>PRDT_PRICE</th>
                                <th>Curr</th>
                                <th>#Quotation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Baris Kosong Khusus Input Data Baru -->
                            <?php if ($isNewDetail) { ?>
                                <tr style="background:#ffffcc;">
                                    <td style="padding: 2px;"><input type="date" id="in_PRDT_START" onkeydown="checkInlineEnter(event)" style="width:100px; padding:2px; font-size:11px;"></td>
                                    <td style="padding: 2px;"><input type="date" id="in_PRDT_END" onkeydown="checkInlineEnter(event)" style="width:100px; padding:2px; font-size:11px;"></td>
                                    <td style="padding: 2px;" class="right"><input type="text" id="in_PRDT_PRICE" readonly onclick="checkPricePassword(this)" onkeydown="checkInlineEnter(event)" style="width:80px; text-align:right; padding:2px; font-size:11px; cursor:pointer;" placeholder="Unlock Pwd"></td>
                                    <td style="padding: 2px;">
                                        <select id="in_CURR_CODE" onkeydown="checkInlineEnter(event)" style="width:60px; padding:2px; font-size:11px;">
                                            <option value=""></option>
                                            <?php for ($c = 0; $c < count($currencyOptions); $c++) { ?>
                                                <option value="<?php echo h($currencyOptions[$c]["CURR_CODE"]); ?>"<?php echo option_selected($detailData["CURR_CODE"], $currencyOptions[$c]["CURR_CODE"]); ?>><?php echo h($currencyOptions[$c]["CURR_CODE"]); ?></option>
                                            <?php } ?>
                                        </select>
                                    </td>
                                    <td style="padding: 2px;"><input type="text" id="in_PRDT_QNO" onkeydown="checkInlineEnter(event)" style="width:120px; padding:2px; font-size:11px;" placeholder="Press Enter to Save"></td>
                                </tr>
                            <?php } ?>
                            <!-- Loop Data Detail Eksisting -->
                            <?php for ($i = 0; $i < count($priceDetailRows); $i++) { $r = $priceDetailRows[$i]; $s = date_out($r["PRDT_START"]); ?>
                                <tr class="<?php echo $s == date_out($detailData["PRDT_START"]) && !$isNewDetail ? "selected" : ""; ?>" onclick="location.href='master.php?tab=price&item_id=<?php echo intval($selectedItemId); ?>&price_id=<?php echo intval($selectedPriceId); ?>&prdt_start=<?php echo urlencode($s); ?>'">
                                    <td><?php echo h($s); ?></td>
                                    <td><?php echo h(date_out($r["PRDT_END"])); ?></td>
                                    <td class="right"><?php echo h(fmt_num($r["PRDT_PRICE"], 4)); ?></td>
                                    <td><?php echo h($r["CURR_CODE"]); ?></td>
                                    <td><?php echo h($r["PRDT_QNO"]); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php } ?>

    <?php if ($tab == "currency") { ?>
        <div class="title">MASTER CURRENCY</div>

        <div class="split-two">
            <div>
                <div class="panel">
                    <form method="post" action="master.php?tab=currency" autocomplete="off">
                        <div class="row"><label>CURR_CODE</label><input type="text" name="CURR_CODE" class="w100" value="<?php echo h($currency["CURR_CODE"]); ?>"></div>
                        <div class="row"><label>DESC</label><input type="text" name="CURR_DESC" class="w300" value="<?php echo h($currency["CURR_DESC"]); ?>"></div>
                        <div class="row">
                            <label>SYMBOL</label><input type="text" name="CURR_SYMBOL" class="w100" value="<?php echo h($currency["CURR_SYMBOL"]); ?>">
                            <label>DEC</label><input type="number" name="CURR_DEC" class="w80" value="<?php echo h($currency["CURR_DEC"]); ?>">
                        </div>
                        <div class="row">
                            <button type="submit" name="action" value="save_currency" class="btn">SIMPAN CURRENCY</button>
                            <a class="btn" href="master.php?tab=currency">NEW</a>
                            <a class="btn" href="dashboard.php">CLOSE</a>
                        </div>
                    </form>
                </div>

                <div class="panel">
                    <div class="row">
                        <label>SEARCH</label>
                        <span class="autocomplete-box">
                            <input type="text" id="searchCurrency" class="w220" placeholder="Ketik currency code / desc">
                            <div id="searchCurrencyList" class="autocomplete-list"></div>
                        </span>
                    </div>
                    <div class="small-note">Currency loaded: <?php echo count($currencyList); ?></div>
                    <div class="grid-wrap" style="height:220px;">
                        <table class="grid">
                            <thead><tr><th>CODE</th><th>DESC</th><th>SYMBOL</th><th>DEC</th></tr></thead>
                            <tbody>
                                <?php for ($i = 0; $i < count($currencyList); $i++) { $r = $currencyList[$i]; ?>
                                    <tr class="<?php echo strtoupper($currency["CURR_CODE"]) == strtoupper($r["CURR_CODE"]) ? "selected" : ""; ?>" onclick="location.href='master.php?tab=currency&curr_code=<?php echo urlencode($r["CURR_CODE"]); ?>'">
                                        <td><?php echo h($r["CURR_CODE"]); ?></td><td><?php echo h($r["CURR_DESC"]); ?></td><td><?php echo h($r["CURR_SYMBOL"]); ?></td><td><?php echo h($r["CURR_DEC"]); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div>
                <div class="panel">
                    <form method="post" action="master.php?tab=currency&curr_code=<?php echo urlencode($currency["CURR_CODE"]); ?>" autocomplete="off">
                        <input type="hidden" name="RATE_CURR_CODE" value="<?php echo h($currency["CURR_CODE"]); ?>">
                        <input type="hidden" name="RATE_SDATE_OLD" value="<?php echo h(date_out($rateData["CURR_SDATE"])); ?>">
                        <div class="row"><label>CURR_CODE</label><input type="text" class="w100" value="<?php echo h($currency["CURR_CODE"]); ?>" readonly></div>
                        <div class="row">
                            <label>S DATE</label><input type="date" name="CURR_SDATE" class="w150" value="<?php echo h(date_out($rateData["CURR_SDATE"])); ?>">
                            <label>E DATE</label><input type="date" name="CURR_EDATE" class="w150" value="<?php echo h(date_out($rateData["CURR_EDATE"])); ?>">
                        </div>
                        <div class="row">
                            <label>C RATE</label><input type="text" name="CURR_CRATE" class="w120" value="<?php echo h($rateData["CURR_CRATE"]); ?>">
                            <label>V RATE</label><input type="text" name="CURR_VRATE" class="w120" value="<?php echo h($rateData["CURR_VRATE"]); ?>">
                        </div>
                        <div class="row">
                            <label>MM</label><input type="number" name="CURR_MM" class="w80" value="<?php echo h($rateData["CURR_MM"]); ?>">
                            <label>YY</label><input type="number" name="CURR_YY" class="w80" value="<?php echo h($rateData["CURR_YY"]); ?>">
                        </div>
                        <div class="row">
                            <button type="submit" name="action" value="save_rate" class="btn">SIMPAN RATE</button>
                            <button type="submit" name="action" value="delete_rate" class="btn" onclick="return confirm('Delete rate ini?');">DELETE RATE</button>
                        </div>
                    </form>
                </div>

                <div class="small-note">Currency rate loaded: <?php echo count($rateList); ?></div>
                <div class="grid-wrap" style="height:300px;">
                    <table class="grid">
                        <thead><tr><th>S DATE</th><th>E DATE</th><th>C RATE</th><th>V RATE</th><th>MM</th><th>YY</th></tr></thead>
                        <tbody>
                            <?php for ($i = 0; $i < count($rateList); $i++) { $r = $rateList[$i]; $s = date_out($r["CURR_SDATE"]); ?>
                                <tr class="<?php echo $s == date_out($rateData["CURR_SDATE"]) ? "selected" : ""; ?>" onclick="location.href='master.php?tab=currency&curr_code=<?php echo urlencode($r["CURR_CODE"]); ?>&rate_sdate=<?php echo urlencode($s); ?>'">
                                    <td><?php echo h($s); ?></td><td><?php echo h(date_out($r["CURR_EDATE"])); ?></td><td class="right"><?php echo h(fmt_num($r["CURR_CRATE"], 4)); ?></td><td class="right"><?php echo h(fmt_num($r["CURR_VRATE"], 4)); ?></td><td><?php echo h($r["CURR_MM"]); ?></td><td><?php echo h($r["CURR_YY"]); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php } ?>
</div>

<script>
// (Bagian script Autocomplete tidak berubah, dilewati atau dicantumkan persis sama seperti sebelumnya)
function acEnc(value) { return encodeURIComponent(value == null ? "" : value); }
function acHtml(value) {
    return String(value == null ? "" : value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

function setupAutocomplete(inputId, listId, ajaxMode, renderItem, chooseItem) {
    var input = document.getElementById(inputId);
    var list = document.getElementById(listId);
    if (!input || !list) return;

    var rows = [];
    var activeIndex = -1;
    var timer = null;

    function hideList() {
        list.style.display = "none";
        list.innerHTML = "";
        rows = [];
        activeIndex = -1;
    }

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
        if (index < 0 || index >= rows.length) return;
        chooseItem(rows[index]);
        hideList();
    }

    function render(rowsData) {
        list.innerHTML = "";
        rows = rowsData || [];
        activeIndex = -1;
        if (rows.length == 0) { hideList(); return; }
        for (var i = 0; i < rows.length; i++) {
            (function(row, idx) {
                var div = document.createElement("div");
                div.className = "autocomplete-item";
                div.innerHTML = renderItem(row);
                div.onmouseover = function() { setActive(idx); };
                div.onmousedown = function(e) { if (e && e.preventDefault) { e.preventDefault(); } choose(idx); };
                list.appendChild(div);
            })(rows[i], i);
        }
        list.style.display = "block";
        setActive(0);
    }

    function search(q) {
        if (q == "") { hideList(); return; }
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "master.php?ajax=" + acEnc(ajaxMode) + "&q=" + acEnc(q), true);
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
                if (activeIndex < 0) { activeIndex = 0; }
                choose(activeIndex);
                return false;
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

setupAutocomplete("searchCustomer", "searchCustomerList", "customer",
    function(r) { return "<b>" + acHtml(r.CUST_CODE) + "</b> - " + acHtml(r.CUST_COMP) + "<div class='autocomplete-sub'>ABBR: " + acHtml(r.CUST_ABBR) + " | CURR: " + acHtml(r.CURR_CODE) + "</div>"; },
    function(r) { location.href = "master.php?tab=customer&cust_id=" + acEnc(r.CUST_ID); }
);
setupAutocomplete("searchItem", "searchItemList", "item",
    function(r) { return "<b>" + acHtml(r.ITEM_CODE) + "</b> - " + acHtml(r.ITEM_NAME) + "<div class='autocomplete-sub'>ITEM NO: " + acHtml(r.ITEM_NO) + "</div>"; },
    function(r) { location.href = "master.php?tab=price&item_id=" + acEnc(r.ITEM_ID); }
);
setupAutocomplete("priceCustomerText", "priceCustomerList", "customer",
    function(r) { return "<b>" + acHtml(r.CUST_CODE) + "</b> - " + acHtml(r.CUST_COMP) + "<div class='autocomplete-sub'>ABBR: " + acHtml(r.CUST_ABBR) + " | CURR: " + acHtml(r.CURR_CODE) + "</div>"; },
    function(r) {
        document.getElementById("PRICE_CUST_ID").value = r.CUST_ID;
        var customerInput = document.getElementById("priceCustomerText");
        var selectedText = r.CUST_CODE + " " + r.CUST_COMP;
        customerInput.value = selectedText;
        customerInput.setAttribute("data-selected-text", selectedText);
        var currency = document.getElementById("PRICE_CURR_CODE");
        if (currency && r.CURR_CODE) { currency.value = r.CURR_CODE; }
    }
);
(function() {
    var customerInput = document.getElementById("priceCustomerText");
    var customerId = document.getElementById("PRICE_CUST_ID");
    if (!customerInput || !customerId) return;
    customerInput.setAttribute("data-selected-text", customerInput.value);
    customerInput.addEventListener("input", function() {
        if (this.value != this.getAttribute("data-selected-text")) { customerId.value = ""; }
    });
})();
setupAutocomplete("searchCurrency", "searchCurrencyList", "currency",
    function(r) { return "<b>" + acHtml(r.CURR_CODE) + "</b> - " + acHtml(r.CURR_DESC) + "<div class='autocomplete-sub'>Symbol: " + acHtml(r.CURR_SYMBOL) + " | Dec: " + acHtml(r.CURR_DEC) + "</div>"; },
    function(r) { location.href = "master.php?tab=currency&curr_code=" + acEnc(r.CURR_CODE); }
);

// ==========================================================
// CUSTOM SCRIPT UNTUK PASSWORD HARGA DAN INLINE ENTER SAVE
// ==========================================================

function checkPricePassword(el) {
    if (el.hasAttribute('readonly')) {
        var pwd = prompt("Masukkan password untuk edit harga:");
        if (pwd === 'q9tj9') {
            el.removeAttribute('readonly');
            el.focus();
            el.placeholder = ""; // Kosongkan placeholder jika sudah unlock
        } else {
            if (pwd !== null) {
                alert("Password salah!");
            }
        }
    }
}

function checkInlineEnter(e) {
    if (e.keyCode === 13 || e.key === 'Enter') {
        e.preventDefault();
        
        // Pindahkan value dari inline input (baris tabel baru) ke form yang ada di atas
        var fStart = document.querySelector('input[name="PRDT_START"]');
        var fEnd   = document.querySelector('input[name="PRDT_END"]');
        var fPrice = document.querySelector('input[name="PRDT_PRICE"]');
        var fCurr  = document.querySelector('select[name="DETAIL_CURR_CODE"]');
        var fQno   = document.querySelector('input[name="PRDT_QNO"]');
        var fOld   = document.querySelector('input[name="PRDT_START_OLD"]');
        
        if(fStart) fStart.value = document.getElementById('in_PRDT_START').value;
        if(fEnd)   fEnd.value   = document.getElementById('in_PRDT_END').value;
        if(fPrice) fPrice.value = document.getElementById('in_PRDT_PRICE').value;
        if(fCurr)  fCurr.value  = document.getElementById('in_CURR_CODE').value;
        if(fQno)   fQno.value   = document.getElementById('in_PRDT_QNO').value;
        
        // Bersihkan data start yang lama agar statusnya menjadi form Insert Baru, bukan Update.
        if(fOld)   fOld.value   = "";

        // Trigger klik pada tombol Simpan Detail (Submit trigger otomatis)
        var btn = document.querySelector('button[value="save_price_detail"]');
        if(btn) btn.click();
    }
}
</script>

</body>
</html>
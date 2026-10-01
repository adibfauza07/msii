<?php
// Memanggil koneksi database sesuai instruksi
require_once __DIR__ . '/config/database.php';

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
    // Bersihkan semua output HTML yang terlanjur dikirim oleh index.php
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data);
    exit();
}

// ==========================================================
// DYNAMIC PAGE ROUTING (Mencegah Keluar dari Menu Index)
// ==========================================================
$pageName = isset($_GET['page']) ? $_GET['page'] : 'master';
$urlBase = "?page=" . urlencode($pageName) . "&";

// ==========================================================
// AJAX AUTOCOMPLETE (Diletakkan di Awal Sebelum Query Lain)
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
                ISNULL(ITEM_CODE, '') LIKE ?
                OR ISNULL(ITEM_NO, '') LIKE ?
                OR ISNULL(ITEM_NAME, '') LIKE ?
            ORDER BY ITEM_CODE
        ";
        $rows = query_all($conn, $sql, array($likeAjax, $likeAjax, $likeAjax));
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
                ISNULL(CUST_CODE, '') LIKE ?
                OR ISNULL(CUST_COMP, '') LIKE ?
                OR ISNULL(CUST_ABBR, '') LIKE ?
            ORDER BY CUST_CODE
        ";
        $rows = query_all($conn, $sql, array($likeAjax, $likeAjax, $likeAjax));
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
                ISNULL(CURR_CODE, '') LIKE ?
                OR ISNULL(CURR_DESC, '') LIKE ?
            ORDER BY CURR_CODE
        ";
        $rows = query_all($conn, $sql, array($likeAjax, $likeAjax));
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
    
    if ($ajaxMode == "supplier") {
        $sql = "
            SELECT TOP 20
                SUP_ID,
                ISNULL(SUP_CODE, '') AS SUP_CODE,
                ISNULL(SUP_ABBR, '') AS SUP_ABBR,
                ISNULL(SUP_COMP, '') AS SUP_COMP,
                ISNULL(CURR_CODE, '') AS CURR_CODE
            FROM dbo.SUPPLIER
            WHERE
                ISNULL(SUP_CODE, '') LIKE ?
                OR ISNULL(SUP_COMP, '') LIKE ?
                OR ISNULL(SUP_ABBR, '') LIKE ?
            ORDER BY SUP_CODE
        ";
        $rows = query_all($conn, $sql, array($likeAjax, $likeAjax, $likeAjax));
        $out = array();
        for ($i = 0; $i < count($rows); $i++) {
            $out[] = array(
                "SUP_ID" => intval($rows[$i]["SUP_ID"]),
                "SUP_CODE" => safe_trim($rows[$i]["SUP_CODE"]),
                "SUP_ABBR" => safe_trim($rows[$i]["SUP_ABBR"]),
                "SUP_COMP" => safe_trim($rows[$i]["SUP_COMP"]),
                "CURR_CODE" => safe_trim($rows[$i]["CURR_CODE"])
            );
        }
        json_out(array("success" => true, "rows" => $out));
    }

    json_out(array("success" => false, "message" => "Mode ajax tidak dikenal.", "rows" => array()));
}

// ==========================================================
// EXPORT EXCEL SUPPLIER
// ==========================================================
if (get_param("action") == "export_supplier") {
    $qSup = get_param("q_supplier", "");
    $whereSup = "";
    $paramsSup = array();

    if ($qSup != "") {
        $whereSup = "WHERE SUP_CODE LIKE ? OR SUP_COMP LIKE ? OR SUP_CITY LIKE ? OR SUP_PHONE LIKE ?";
        $paramsSup = array("%" . $qSup . "%", "%" . $qSup . "%", "%" . $qSup . "%", "%" . $qSup . "%");
    }

    $sqlExp = "
        SELECT SUP_ID, SUP_CODE, SUP_COMP, CURR_CODE, SUP_ADDR1, SUP_ADDR2,
               SUP_CITY, SUP_PHONE, SUP_FAX, SUP_EMAIL, SUP_CONTA, SUP_TERM,
               SUP_NPWP, SUP_ABBR, ISNULL(SUP_PE, 0) AS SUP_PE
        FROM dbo.SUPPLIER
        $whereSup
        ORDER BY SUP_CODE
    ";
    
    $stmtExp = sqlsrv_query($conn, $sqlExp, $paramsSup);
    if ($stmtExp === false) {
        die("Export gagal: " . sql_error_text());
    }

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Master_Supplier_" . date("Ymd") . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<table border='1'>";
    echo "<tr>
            <th>CODE</th>
            <th>ABBR</th>
            <th>COMPANY</th>
            <th>CURR</th>
            <th>ADDR1</th>
            <th>ADDR2</th>
            <th>CITY</th>
            <th>PHONE</th>
            <th>FAX</th>
            <th>EMAIL</th>
            <th>CONTACT</th>
            <th>TERM</th>
            <th>NPWP</th>
            <th>INACTIVE</th>
          </tr>";

    while ($r = sqlsrv_fetch_array($stmtExp, SQLSRV_FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . h($r["SUP_CODE"]) . "</td>";
        echo "<td>" . h($r["SUP_ABBR"]) . "</td>";
        echo "<td>" . h($r["SUP_COMP"]) . "</td>";
        echo "<td>" . h($r["CURR_CODE"]) . "</td>";
        echo "<td>" . h($r["SUP_ADDR1"]) . "</td>";
        echo "<td>" . h($r["SUP_ADDR2"]) . "</td>";
        echo "<td>" . h($r["SUP_CITY"]) . "</td>";
        echo "<td>" . h($r["SUP_PHONE"]) . "</td>";
        echo "<td>" . h($r["SUP_FAX"]) . "</td>";
        echo "<td>" . h($r["SUP_EMAIL"]) . "</td>";
        echo "<td>" . h($r["SUP_CONTA"]) . "</td>";
        echo "<td>" . h($r["SUP_TERM"]) . "</td>";
        echo "<td>" . h($r["SUP_NPWP"]) . "</td>";
        echo "<td>" . (intval($r["SUP_PE"]) == 1 ? "YES" : "NO") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit();
}

function get_currency_options($conn) {
    return query_all($conn, "SELECT CURR_CODE, CURR_DESC FROM dbo.CURR ORDER BY CURR_CODE", array());
}

function get_pack_options($conn) {
    return query_all($conn, "SELECT PACK_ID, PACK_CODE, PACK_NAME, PACK_DESC FROM dbo.PACK ORDER BY PACK_CODE", array());
}

$tab = get_param("tab", "customer");
if ($tab != "customer" && $tab != "price" && $tab != "supplier" && $tab != "currency") {
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
$forceSupId = 0;

$currencyOptions = get_currency_options($conn);
$packOptions = get_pack_options($conn);

// ==========================================================
// SUPPLIER SAVE / DELETE
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && $action == "save_supplier") {
    $tab = "supplier";

    $supId    = safe_int(get_param("SUP_ID"), 0);
    $supCode  = safe_trim(get_param("SUP_CODE"));
    $supComp  = safe_trim(get_param("SUP_COMP"));

    if ($supCode == "") {
        $err = "SUP_CODE belum diisi.";
    } elseif ($supComp == "") {
        $err = "SUP_COMP belum diisi.";
    } else {
        $sqlDup = "SELECT COUNT(*) AS CNT FROM dbo.SUPPLIER WHERE SUP_CODE = ? AND SUP_ID <> ?";
        $dupRow = query_one($conn, $sqlDup, array($supCode, $supId));
        
        if ($dupRow && intval($dupRow["CNT"]) > 0) {
            $err = "Kode Supplier sudah ada.";
        } else {
            if ($supId > 0) {
                $sql = "
                    UPDATE dbo.SUPPLIER
                    SET
                        SUP_CODE = ?, SUP_ABBR = ?, SUP_COMP = ?, CURR_CODE = ?,
                        SUP_ADDR1 = ?, SUP_ADDR2 = ?, SUP_CITY = ?, SUP_PHONE = ?,
                        SUP_FAX = ?, SUP_EMAIL = ?, SUP_CONTA = ?, SUP_TERM = ?,
                        SUP_NPWP = ?, SUP_PE = ?
                    WHERE SUP_ID = ?
                ";
                $params = array(
                    $supCode,
                    get_param("SUP_ABBR"),
                    $supComp,
                    get_param("CURR_CODE"),
                    get_param("SUP_ADDR1"),
                    get_param("SUP_ADDR2"),
                    get_param("SUP_CITY"),
                    get_param("SUP_PHONE"),
                    get_param("SUP_FAX"),
                    get_param("SUP_EMAIL"),
                    get_param("SUP_CONTA"),
                    get_param("SUP_TERM"),
                    get_param("SUP_NPWP"),
                    checked_value("SUP_PE"),
                    $supId
                );
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt === false) {
                    $err = "Gagal update supplier: " . sql_error_text();
                } else {
                    $msg = "Supplier berhasil diupdate.";
                    $forceSupId = $supId;
                }
            } else {
                $sql = "
                    SET NOCOUNT ON;
                    INSERT INTO dbo.SUPPLIER
                    (
                        SUP_CODE, SUP_ABBR, SUP_COMP, CURR_CODE, SUP_ADDR1, SUP_ADDR2,
                        SUP_CITY, SUP_PHONE, SUP_FAX, SUP_EMAIL, SUP_CONTA, SUP_TERM,
                        SUP_NPWP, SUP_PE
                    )
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
                    SELECT CONVERT(INT, SCOPE_IDENTITY()) AS NEW_ID;
                ";
                $params = array(
                    $supCode,
                    get_param("SUP_ABBR"),
                    $supComp,
                    get_param("CURR_CODE"),
                    get_param("SUP_ADDR1"),
                    get_param("SUP_ADDR2"),
                    get_param("SUP_CITY"),
                    get_param("SUP_PHONE"),
                    get_param("SUP_FAX"),
                    get_param("SUP_EMAIL"),
                    get_param("SUP_CONTA"),
                    get_param("SUP_TERM"),
                    get_param("SUP_NPWP"),
                    checked_value("SUP_PE")
                );
                $newId = insert_and_get_id($conn, $sql, $params, "NEW_ID");
                if ($newId === false || $newId <= 0) {
                    $err = "Gagal insert supplier: " . sql_error_text();
                } else {
                    $msg = "Supplier baru berhasil disimpan.";
                    $forceSupId = $newId;
                }
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $action == "delete_supplier") {
    $tab = "supplier";
    $supId = safe_int(get_param("SUP_ID"), 0);
    if ($supId <= 0) {
        $err = "Pilih supplier dulu.";
    } else {
        $stmt = sqlsrv_query($conn, "DELETE FROM dbo.SUPPLIER WHERE SUP_ID = ?", array($supId));
        if ($stmt === false) {
            $err = "Gagal delete supplier. Kemungkinan sudah dipakai transaksi. " . sql_error_text();
        } else {
            $msg = "Supplier berhasil dihapus.";
        }
    }
}

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
// LOAD DATA TAB
// ==========================================================
$selectedSupId = $forceSupId > 0 ? $forceSupId : safe_int(get_param("sup_id"), 0);
$qSupplier = get_param("q_supplier", "");

$supplier = array(
    "SUP_ID" => "", "SUP_CODE" => "", "SUP_ABBR" => "", "SUP_COMP" => "", "CURR_CODE" => "IDR",
    "SUP_ADDR1" => "", "SUP_ADDR2" => "", "SUP_CITY" => "", "SUP_PHONE" => "", "SUP_FAX" => "",
    "SUP_EMAIL" => "", "SUP_CONTA" => "", "SUP_TERM" => "", "SUP_NPWP" => "", "SUP_PE" => 0
);

if ($selectedSupId > 0) {
    $row = query_one($conn, "SELECT TOP 1 * FROM dbo.SUPPLIER WHERE SUP_ID = ?", array($selectedSupId));
    if ($row) {
        $supplier = array_merge($supplier, $row);
    }
}

$whereSupList = "";
$paramsSupList = array();
if ($qSupplier != "") {
    $whereSupList = "WHERE SUP_CODE LIKE ? OR SUP_COMP LIKE ? OR SUP_CITY LIKE ? OR SUP_PHONE LIKE ?";
    $paramsSupList = array("%".$qSupplier."%", "%".$qSupplier."%", "%".$qSupplier."%", "%".$qSupplier."%");
}

$supplierList = query_all(
    $conn,
    "
    SELECT TOP 200 SUP_ID, SUP_CODE, SUP_ABBR, SUP_COMP, CURR_CODE, SUP_CITY, SUP_PHONE, ISNULL(SUP_PE, 0) AS SUP_PE
    FROM dbo.SUPPLIER
    $whereSupList
    ORDER BY SUP_CODE
    ",
    $paramsSupList
);

$termList = array();
$sqlTerm = "
    SELECT DISTINCT LTRIM(RTRIM(CAST(SUP_TERM AS VARCHAR(50)))) AS SUP_TERM
    FROM dbo.SUPPLIER
    WHERE SUP_TERM IS NOT NULL AND LTRIM(RTRIM(CAST(SUP_TERM AS VARCHAR(50)))) <> ''
    ORDER BY LTRIM(RTRIM(CAST(SUP_TERM AS VARCHAR(50))))
";
$stmtTerm = sqlsrv_query($conn, $sqlTerm);
if ($stmtTerm !== false) {
    while ($t = sqlsrv_fetch_array($stmtTerm, SQLSRV_FETCH_ASSOC)) {
        $v = trim((string)$t["SUP_TERM"]);
        if ($v != "") $termList[] = $v;
    }
}

// Load Customer Tab
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

// Load Price Tab
$isNewPrice = get_param("new_price", "") == "1";
$isNewDetail = get_param("new_detail", "") == "1"; 

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

// Load Currency Tab
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

<style>
    .autocomplete-box { position: relative; display: block; }
    .autocomplete-list {
        position: absolute; left: 0; top: 100%; width: 100%; max-height: 240px;
        overflow-y: auto; background: #ffffff; border: 1px solid #ddd;
        z-index: 9999; display: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .autocomplete-item { padding: 8px; border-bottom: 1px solid #eee; cursor: pointer; color: #333; }
    .autocomplete-item:hover, .autocomplete-item.active { background: #3c8dbc; color: #ffffff; }
    .autocomplete-sub { font-size: 11px; color: #777; }
    .autocomplete-item:hover .autocomplete-sub, .autocomplete-item.active .autocomplete-sub { color: #e0e0e0; }

    table.grid th { text-align: center; background-color: #f4f4f4; position: sticky; top: 0; z-index: 2; }
    table.grid td { vertical-align: middle !important; padding: 4px 8px !important; }
    table.grid tr:hover td { background-color: #f5f5f5; }
    table.grid tr.selected td { background-color: #3c8dbc !important; color: #ffffff !important; }
    
    .small-note { font-weight: bold; margin-bottom: 5px; color: #0056b3; font-size: 12px; }
    .table-container { overflow-y: auto; border: 1px solid #ddd; margin-bottom: 15px; background: #fff; }
    .table-container.h-200 { height: 200px; }
    .table-container.h-250 { height: 250px; }
    .table-container.h-300 { height: 300px; }
    
    table.grid input, table.grid select { 
        width: 100%; height: 28px; border: 1px solid #ddd; padding: 2px 6px; 
        background: #fff; box-sizing: border-box; font-size: 12px;
    }
    table.grid input:focus, table.grid select:focus { outline: 2px solid #f39c12; }
</style>

<div class="row">
    <div class="col-md-12">
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                <li class="<?php echo $tab == 'customer' ? 'active' : ''; ?>"><a href="<?php echo $urlBase; ?>tab=customer"><i class="fa fa-users"></i> CUSTOMER</a></li>
                <li class="<?php echo $tab == 'price' ? 'active' : ''; ?>"><a href="<?php echo $urlBase; ?>tab=price"><i class="fa fa-tags"></i> PRICE</a></li>
                <li class="<?php echo $tab == 'supplier' ? 'active' : ''; ?>"><a href="<?php echo $urlBase; ?>tab=supplier"><i class="fa fa-truck"></i> SUPPLIER</a></li>
                <li class="<?php echo $tab == 'currency' ? 'active' : ''; ?>"><a href="<?php echo $urlBase; ?>tab=currency"><i class="fa fa-money"></i> CURRENCY</a></li>
            </ul>
            <div class="tab-content" style="background-color: #ecf0f5; padding: 15px;">
                <div class="tab-pane active" style="display: block;">

                    <?php if ($msg != "") { ?>
                        <div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><i class="icon fa fa-check"></i> <?php echo h($msg); ?></div>
                    <?php } ?>

                    <?php if ($err != "") { ?>
                        <div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><i class="icon fa fa-ban"></i> <?php echo h($err); ?></div>
                    <?php } ?>

                    <!-- ======================= CUSTOMER TAB ======================= -->
                    <?php if ($tab == "customer") { ?>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="box box-solid bg-gray-light">
                                    <div class="box-body">
                                        <form method="get" action="" autocomplete="off" class="form-inline">
                                            <input type="hidden" name="page" value="<?php echo htmlspecialchars($pageName); ?>">
                                            <input type="hidden" name="tab" value="customer">
                                            
                                            <div class="form-group autocomplete-box" style="width: 400px; display: inline-block;">
                                                <div class="input-group" style="width: 100%;">
                                                    <span class="input-group-addon"><i class="fa fa-search"></i></span>
                                                    <input type="text" id="searchCustomer" name="q_customer" class="form-control" value="<?php echo h($qCustomer); ?>" placeholder="Ketik kode / nama / abbr customer">
                                                </div>
                                                <div id="searchCustomerList" class="autocomplete-list"></div>
                                            </div>
                                            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> CARI</button>
                                            <a href="<?php echo $urlBase; ?>tab=customer" class="btn btn-default"><i class="fa fa-file-o"></i> NEW</a>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="box box-primary">
                                    <div class="box-header with-border">
                                        <h3 class="box-title"><i class="fa fa-user"></i> INFORMASI CUSTOMER</h3>
                                    </div>
                                    <form method="post" action="<?php echo $urlBase; ?>tab=customer" autocomplete="off">
                                        <div class="box-body">
                                            <input type="hidden" name="CUST_ID" value="<?php echo h($customer["CUST_ID"]); ?>">

                                            <div class="row">
                                                <div class="col-md-2 form-group"><label>CUST_CODE</label><input type="text" name="CUST_CODE" class="form-control text-primary" style="font-weight:bold;" value="<?php echo h($customer["CUST_CODE"]); ?>"></div>
                                                <div class="col-md-2 form-group"><label>CUST_ABBR</label><input type="text" name="CUST_ABBR" class="form-control" value="<?php echo h($customer["CUST_ABBR"]); ?>"></div>
                                                <div class="col-md-5 form-group"><label>CUST_COMP</label><input type="text" name="CUST_COMP" class="form-control" value="<?php echo h($customer["CUST_COMP"]); ?>"></div>
                                                <div class="col-md-2 form-group">
                                                    <label>CURR_CODE</label>
                                                    <select name="CURR_CODE" class="form-control">
                                                        <option value=""></option>
                                                        <?php for ($i = 0; $i < count($currencyOptions); $i++) { ?>
                                                            <option value="<?php echo h($currencyOptions[$i]["CURR_CODE"]); ?>"<?php echo option_selected($customer["CURR_CODE"], $currencyOptions[$i]["CURR_CODE"]); ?>><?php echo h($currencyOptions[$i]["CURR_CODE"]); ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-1 form-group">
                                                    <label>&nbsp;</label>
                                                    <div class="checkbox"><label class="text-danger" style="font-weight:bold;"><input type="checkbox" name="CUST_INACTIVE" value="1"<?php echo bool_checked($customer["CUST_INACTIVE"]); ?>> INACTIVE</label></div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-12 form-group"><label>CUST_ADDR1</label><input type="text" name="CUST_ADDR1" class="form-control" value="<?php echo h($customer["CUST_ADDR1"]); ?>"></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-12 form-group"><label>CUST_ADDR2</label><input type="text" name="CUST_ADDR2" class="form-control" value="<?php echo h($customer["CUST_ADDR2"]); ?>"></div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4 form-group"><label>CUST_CITY</label><input type="text" name="CUST_CITY" class="form-control" value="<?php echo h($customer["CUST_CITY"]); ?>"></div>
                                                <div class="col-md-4 form-group"><label>CUST_PHONE</label><input type="text" name="CUST_PHONE" class="form-control" value="<?php echo h($customer["CUST_PHONE"]); ?>"></div>
                                                <div class="col-md-4 form-group"><label>CUST_FAX</label><input type="text" name="CUST_FAX" class="form-control" value="<?php echo h($customer["CUST_FAX"]); ?>"></div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-12 form-group"><label>CUST_EMAIL</label><input type="text" name="CUST_EMAIL" class="form-control" value="<?php echo h($customer["CUST_EMAIL"]); ?>"></div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4 form-group"><label>CUST_CONTA</label><input type="text" name="CUST_CONTA" class="form-control" value="<?php echo h($customer["CUST_CONTA"]); ?>"></div>
                                                <div class="col-md-2 form-group"><label>CUST_TERM</label><input type="text" name="CUST_TERM" class="form-control" value="<?php echo h($customer["CUST_TERM"]); ?>"></div>
                                                <div class="col-md-6 form-group"><label>CUST_NPWP</label><input type="text" name="CUST_NPWP" class="form-control" value="<?php echo h($customer["CUST_NPWP"]); ?>"></div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-3 form-group"><label>CUST_ALIAS</label><input type="text" name="CUST_ALIAS" class="form-control" value="<?php echo h($customer["CUST_ALIAS"]); ?>"></div>
                                                <div class="col-md-3 form-group"><label>KPBC_ID</label><input type="number" name="KPBC_ID" class="form-control" value="<?php echo h($customer["KPBC_ID"]); ?>"></div>
                                                <div class="col-md-3 form-group"><label>CUST_HSNO</label><input type="text" name="CUST_HSNO" class="form-control" value="<?php echo h($customer["CUST_HSNO"]); ?>"></div>
                                                <div class="col-md-3 form-group"><label>CUST_TPB</label><input type="text" name="CUST_TPB" class="form-control" value="<?php echo h($customer["CUST_TPB"]); ?>"></div>
                                            </div>
                                        </div>
                                        <div class="box-footer">
                                            <button type="submit" name="action" value="save_customer" class="btn btn-success"><i class="fa fa-save"></i> SIMPAN</button>
                                            <button type="submit" name="action" value="delete_customer" class="btn btn-danger" onclick="return confirm('Delete customer ini?');"><i class="fa fa-trash"></i> DELETE</button>
                                            <a class="btn btn-default" href="<?php echo $urlBase; ?>tab=customer"><i class="fa fa-undo"></i> BATAL / NEW</a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="small-note">Customer loaded: <?php echo count($customerList); ?></div>
                        <div class="table-container h-300">
                            <table class="table table-bordered table-hover grid" style="margin-bottom: 0;">
                                <thead><tr><th>CODE</th><th>ABBR</th><th>COMPANY</th><th>CURR</th><th>CITY</th><th>PHONE</th><th>INACTIVE</th></tr></thead>
                                <tbody>
                                    <?php for ($i = 0; $i < count($customerList); $i++) { $r = $customerList[$i]; ?>
                                        <tr class="<?php echo intval($customer["CUST_ID"]) == intval($r["CUST_ID"]) ? "selected" : ""; ?>" onclick="location.href='<?php echo $urlBase; ?>tab=customer&cust_id=<?php echo intval($r["CUST_ID"]); ?>'" style="cursor:pointer;">
                                            <td><?php echo h($r["CUST_CODE"]); ?></td>
                                            <td><?php echo h($r["CUST_ABBR"]); ?></td>
                                            <td><?php echo h($r["CUST_COMP"]); ?></td>
                                            <td><?php echo h($r["CURR_CODE"]); ?></td>
                                            <td><?php echo h($r["CUST_CITY"]); ?></td>
                                            <td><?php echo h($r["CUST_PHONE"]); ?></td>
                                            <td class="text-center"><?php echo intval($r["CUST_INACTIVE"]) == 1 ? "<span class='label label-danger'>YES</span>" : ""; ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>

                    <!-- ======================= PRICE TAB ======================= -->
                    <?php if ($tab == "price") { ?>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="box box-solid bg-gray-light">
                                    <div class="box-body">
                                        <form method="get" action="" autocomplete="off" class="form-inline">
                                            <input type="hidden" name="page" value="<?php echo htmlspecialchars($pageName); ?>">
                                            <input type="hidden" name="tab" value="price">
                                            
                                            <div class="form-group autocomplete-box" style="width: 300px; display: inline-block;">
                                                <div class="input-group" style="width: 100%;">
                                                    <span class="input-group-addon"><i class="fa fa-cube"></i></span>
                                                    <input type="text" id="searchItem" name="q_item" class="form-control" value="<?php echo h($item["ITEM_CODE"]); ?>" placeholder="Ketik item code / name">
                                                </div>
                                                <div id="searchItemList" class="autocomplete-list"></div>
                                            </div>
                                            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> CARI ITEM</button>
                                            <a class="btn btn-default" href="<?php echo $urlBase; ?>tab=price"><i class="fa fa-refresh"></i> REFRESH</a>
                                            
                                            <div class="form-group pull-right" style="margin-left: 15px;">
                                                <input type="text" class="form-control" value="<?php echo h($item["ITEM_NO"]); ?>" readonly placeholder="ITEM NO" style="background: #eee; width: 150px;">
                                            </div>
                                            <div class="form-group pull-right">
                                                <input type="text" class="form-control" value="<?php echo h($item["ITEM_NAME"]); ?>" readonly placeholder="ITEM NAME" style="background: #eee; width: 350px;">
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- KOLOM KIRI (PRICE MASTER) -->
                            <div class="col-md-6">
                                <div class="box box-warning">
                                    <div class="box-header with-border">
                                        <h3 class="box-title"><i class="fa fa-folder-open"></i> PRICE MASTER PER CUSTOMER</h3>
                                    </div>
                                    <form method="post" action="<?php echo $urlBase; ?>tab=price" autocomplete="off">
                                        <div class="box-body">
                                            <input type="hidden" name="ITEM_ID" value="<?php echo intval($selectedItemId); ?>">
                                            <input type="hidden" name="PRICE_ID" value="<?php echo h($priceData["PRICE_ID"]); ?>">
                                            <input type="hidden" id="PRICE_CUST_ID" name="PRICE_CUST_ID" value="<?php echo h($priceData["CUST_ID"]); ?>">

                                            <div class="row">
                                                <div class="col-md-4 form-group"><label>PRICE_ID</label><input type="text" class="form-control" value="<?php echo h($priceData["PRICE_ID"]); ?>" readonly style="background:#eee;"></div>
                                                <div class="col-md-8 form-group">
                                                    <label>CUSTOMER</label>
                                                    <div class="autocomplete-box">
                                                        <input type="text" id="priceCustomerText" class="form-control" value="<?php echo h(trim($priceData["CUST_CODE"] . " " . $priceData["CUST_COMP"])); ?>" placeholder="Ketik customer">
                                                        <div id="priceCustomerList" class="autocomplete-list"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4 form-group"><label>PR_CD</label><input type="text" name="PRICE_CODE" class="form-control" value="<?php echo h($priceData["PRICE_CODE"]); ?>"></div>
                                                <div class="col-md-4 form-group">
                                                    <label>CURR</label>
                                                    <select id="PRICE_CURR_CODE" name="PRICE_CURR_CODE" class="form-control">
                                                        <option value=""></option>
                                                        <?php for ($i = 0; $i < count($currencyOptions); $i++) { ?>
                                                            <option value="<?php echo h($currencyOptions[$i]["CURR_CODE"]); ?>"<?php echo option_selected($priceData["CURR_CODE"], $currencyOptions[$i]["CURR_CODE"]); ?>><?php echo h($currencyOptions[$i]["CURR_CODE"]); ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>&nbsp;</label>
                                                    <div class="checkbox"><label class="text-danger" style="font-weight:bold;"><input type="checkbox" name="PRICE_INACTIVE" value="1"<?php echo bool_checked($priceData["PRICE_INACTIVE"]); ?>> INACTIVE</label></div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-8 form-group">
                                                    <label>PACKING</label>
                                                    <select name="PACK_ID" class="form-control">
                                                        <option value="0"></option>
                                                        <?php for ($i = 0; $i < count($packOptions); $i++) { ?>
                                                            <option value="<?php echo intval($packOptions[$i]["PACK_ID"]); ?>"<?php echo intval($priceData["PACK_ID"]) == intval($packOptions[$i]["PACK_ID"]) ? " selected" : ""; ?>>
                                                                <?php echo h($packOptions[$i]["PACK_CODE"]); ?>
                                                            </option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-4 form-group"><label>PACK QTY</label><input type="number" name="PRICE_PACK_QTY" class="form-control" value="<?php echo h($priceData["PRICE_PACK_QTY"]); ?>"></div>
                                            </div>
                                        </div>
                                        <div class="box-footer">
                                            <button type="submit" name="action" value="save_price_header" class="btn btn-success"><i class="fa fa-save"></i> SIMPAN</button>
                                            <button type="submit" name="action" value="delete_price_header" class="btn btn-danger" onclick="return confirm('Delete PRICE dan semua detail price ini?');"><i class="fa fa-trash"></i> HAPUS</button>
                                            <a class="btn btn-default" href="<?php echo $urlBase; ?>tab=price&item_id=<?php echo intval($selectedItemId); ?>&new_price=1"><i class="fa fa-plus"></i> NEW PRICE</a>
                                        </div>
                                    </form>
                                </div>
                                
                                <div class="small-note">Price customer loaded: <?php echo count($priceRows); ?></div>
                                <div class="table-container h-250">
                                    <table class="table table-bordered table-hover grid" style="margin-bottom:0;">
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
                                                <tr class="<?php echo intval($r["PRICE_ID"]) == intval($selectedPriceId) ? "selected" : ""; ?>" onclick="location.href='<?php echo $urlBase; ?>tab=price&item_id=<?php echo intval($selectedItemId); ?>&price_id=<?php echo intval($r["PRICE_ID"]); ?>'" style="cursor:pointer;">
                                                    <td><?php echo h($r["CUST_CODE"]); ?></td>
                                                    <td><?php echo h($r["CUST_COMP"]); ?></td>
                                                    <td><?php echo h($r["PRICE_CODE"]); ?></td>
                                                    <td><?php echo h($r["CURR_CODE"]); ?></td>
                                                    <td><?php echo h($r["PACKING"]); ?></td>
                                                    <td class="text-right"><?php echo h($r["PRICE_PACK_QTY"]); ?></td>
                                                    <td class="text-center"><?php echo intval($r["PRICE_INACTIVE"]) == 1 ? "<span class='label label-danger'>✓</span>" : ""; ?></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- KOLOM KANAN (PRICE DETAIL) -->
                            <div class="col-md-6">
                                <div class="box box-success">
                                    <div class="box-header with-border">
                                        <h3 class="box-title"><i class="fa fa-list"></i> PRICE DETAIL</h3>
                                    </div>
                                    <form method="post" action="<?php echo $urlBase; ?>tab=price" autocomplete="off">
                                        <div class="box-body">
                                            <input type="hidden" name="ITEM_ID" value="<?php echo intval($selectedItemId); ?>">
                                            <input type="hidden" name="DETAIL_PRICE_ID" value="<?php echo intval($selectedPriceId); ?>">
                                            <input type="hidden" name="PRDT_START_OLD" value="<?php echo h(date_out($detailData["PRDT_START"])); ?>">

                                            <div class="row">
                                                <div class="col-md-4 form-group"><label>PRICE_ID</label><input type="text" class="form-control" value="<?php echo intval($selectedPriceId); ?>" readonly style="background:#eee;"></div>
                                                <div class="col-md-8 form-group">
                                                    <label>CURR</label>
                                                    <select name="DETAIL_CURR_CODE" class="form-control">
                                                        <option value=""></option>
                                                        <?php for ($i = 0; $i < count($currencyOptions); $i++) { ?>
                                                            <option value="<?php echo h($currencyOptions[$i]["CURR_CODE"]); ?>"<?php echo option_selected($detailData["CURR_CODE"], $currencyOptions[$i]["CURR_CODE"]); ?>><?php echo h($currencyOptions[$i]["CURR_CODE"]); ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 form-group"><label>Price Start</label><input type="date" name="PRDT_START" class="form-control" value="<?php echo h(date_out($detailData["PRDT_START"])); ?>"></div>
                                                <div class="col-md-6 form-group"><label>Price End</label><input type="date" name="PRDT_END" class="form-control" value="<?php echo h(date_out($detailData["PRDT_END"])); ?>"></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-4 form-group">
                                                    <label>PRDT_PRICE</label>
                                                    <input type="text" name="PRDT_PRICE" class="form-control" value="<?php echo h($detailData["PRDT_PRICE"]); ?>" readonly onclick="checkPricePassword(this)" title="Klik untuk edit" style="cursor:pointer;" placeholder="Click Unlock">
                                                </div>
                                                <div class="col-md-8 form-group"><label>#Quotation</label><input type="text" name="PRDT_QNO" class="form-control" value="<?php echo h($detailData["PRDT_QNO"]); ?>"></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-12 form-group">
                                                    <div class="checkbox"><label class="text-success" style="font-weight:bold;"><input type="checkbox" name="PRDT_POSTED" value="1"<?php echo bool_checked($detailData["PRDT_POSTED"]); ?>> POSTED</label></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="box-footer">
                                            <button type="submit" name="action" value="save_price_detail" class="btn btn-success"><i class="fa fa-save"></i> SIMPAN DTL</button>
                                            <button type="submit" name="action" value="delete_price_detail" class="btn btn-danger" onclick="return confirm('Delete price detail ini?');"><i class="fa fa-trash"></i> HAPUS DTL</button>
                                            <a class="btn btn-default" href="<?php echo $urlBase; ?>tab=price&item_id=<?php echo intval($selectedItemId); ?>&price_id=<?php echo intval($selectedPriceId); ?>&new_detail=1"><i class="fa fa-plus"></i> NEW DTL</a>
                                        </div>
                                    </form>
                                </div>
                                
                                <div class="small-note">Price detail loaded: <?php echo count($priceDetailRows); ?></div>
                                <div class="table-container h-250">
                                    <table class="table table-bordered table-hover grid" style="margin-bottom:0;">
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
                                            <?php if ($isNewDetail) { ?>
                                                <tr style="background:#ffffcc;">
                                                    <td><input type="date" id="in_PRDT_START" onkeydown="checkInlineEnter(event)"></td>
                                                    <td><input type="date" id="in_PRDT_END" onkeydown="checkInlineEnter(event)"></td>
                                                    <td class="text-right"><input type="text" id="in_PRDT_PRICE" readonly onclick="checkPricePassword(this)" onkeydown="checkInlineEnter(event)" style="text-align:right; cursor:pointer;" placeholder="Unlock"></td>
                                                    <td>
                                                        <select id="in_CURR_CODE" onkeydown="checkInlineEnter(event)">
                                                            <option value=""></option>
                                                            <?php for ($c = 0; $c < count($currencyOptions); $c++) { ?>
                                                                <option value="<?php echo h($currencyOptions[$c]["CURR_CODE"]); ?>"<?php echo option_selected($detailData["CURR_CODE"], $currencyOptions[$c]["CURR_CODE"]); ?>><?php echo h($currencyOptions[$c]["CURR_CODE"]); ?></option>
                                                            <?php } ?>
                                                        </select>
                                                    </td>
                                                    <td><input type="text" id="in_PRDT_QNO" onkeydown="checkInlineEnter(event)" placeholder="Press Enter"></td>
                                                </tr>
                                            <?php } ?>
                                            <?php for ($i = 0; $i < count($priceDetailRows); $i++) { $r = $priceDetailRows[$i]; $s = date_out($r["PRDT_START"]); ?>
                                                <tr class="<?php echo $s == date_out($detailData["PRDT_START"]) && !$isNewDetail ? "selected" : ""; ?>" onclick="location.href='<?php echo $urlBase; ?>tab=price&item_id=<?php echo intval($selectedItemId); ?>&price_id=<?php echo intval($selectedPriceId); ?>&prdt_start=<?php echo urlencode($s); ?>'" style="cursor:pointer;">
                                                    <td><?php echo h($s); ?></td>
                                                    <td><?php echo h(date_out($r["PRDT_END"])); ?></td>
                                                    <td class="text-right"><?php echo h((float)$r["PRDT_PRICE"]); ?></td>
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

                    <!-- ======================= SUPPLIER TAB ======================= -->
                    <?php if ($tab == "supplier") { ?>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="box box-solid bg-gray-light">
                                    <div class="box-body">
                                        <form method="get" action="" autocomplete="off" class="form-inline">
                                            <input type="hidden" name="page" value="<?php echo htmlspecialchars($pageName); ?>">
                                            <input type="hidden" name="tab" value="supplier">
                                            
                                            <div class="form-group autocomplete-box" style="width: 400px; display: inline-block;">
                                                <div class="input-group" style="width: 100%;">
                                                    <span class="input-group-addon"><i class="fa fa-search"></i></span>
                                                    <input type="text" id="searchSupplier" name="q_supplier" class="form-control" value="<?php echo h($qSupplier); ?>" placeholder="Ketik kode / nama supplier">
                                                </div>
                                                <div id="searchSupplierList" class="autocomplete-list"></div>
                                            </div>
                                            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> CARI</button>
                                            <a href="<?php echo $urlBase; ?>tab=supplier" class="btn btn-default"><i class="fa fa-file-o"></i> NEW</a>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="box box-info">
                                    <div class="box-header with-border">
                                        <h3 class="box-title"><i class="fa fa-truck"></i> INFORMASI SUPPLIER</h3>
                                    </div>
                                    <form method="post" action="<?php echo $urlBase; ?>tab=supplier" autocomplete="off">
                                        <div class="box-body">
                                            <input type="hidden" name="SUP_ID" value="<?php echo h($supplier["SUP_ID"]); ?>">

                                            <div class="row">
                                                <div class="col-md-2 form-group"><label>SUP_CODE</label><input type="text" name="SUP_CODE" class="form-control text-primary" style="font-weight:bold; text-transform:uppercase;" value="<?php echo h($supplier["SUP_CODE"]); ?>"></div>
                                                <div class="col-md-2 form-group"><label>SUP_ABBR</label><input type="text" name="SUP_ABBR" class="form-control" style="text-transform:uppercase;" value="<?php echo h($supplier["SUP_ABBR"]); ?>"></div>
                                                <div class="col-md-5 form-group"><label>SUP_COMP</label><input type="text" name="SUP_COMP" class="form-control" value="<?php echo h($supplier["SUP_COMP"]); ?>"></div>
                                                <div class="col-md-2 form-group">
                                                    <label>CURR_CODE</label>
                                                    <select name="CURR_CODE" class="form-control">
                                                        <option value=""></option>
                                                        <?php for ($i = 0; $i < count($currencyOptions); $i++) { ?>
                                                            <option value="<?php echo h($currencyOptions[$i]["CURR_CODE"]); ?>"<?php echo option_selected($supplier["CURR_CODE"], $currencyOptions[$i]["CURR_CODE"]); ?>><?php echo h($currencyOptions[$i]["CURR_CODE"]); ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-1 form-group">
                                                    <label>&nbsp;</label>
                                                    <div class="checkbox"><label class="text-danger" style="font-weight:bold;"><input type="checkbox" name="SUP_PE" value="1"<?php echo bool_checked($supplier["SUP_PE"]); ?>> INACTIVE</label></div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-12 form-group"><label>ADDR1</label><input type="text" name="SUP_ADDR1" class="form-control" value="<?php echo h($supplier["SUP_ADDR1"]); ?>"></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-12 form-group"><label>ADDR2</label><input type="text" name="SUP_ADDR2" class="form-control" value="<?php echo h($supplier["SUP_ADDR2"]); ?>"></div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4 form-group"><label>CITY</label><input type="text" name="SUP_CITY" class="form-control" value="<?php echo h($supplier["SUP_CITY"]); ?>"></div>
                                                <div class="col-md-4 form-group"><label>PHONE</label><input type="text" name="SUP_PHONE" class="form-control" value="<?php echo h($supplier["SUP_PHONE"]); ?>"></div>
                                                <div class="col-md-4 form-group"><label>FAX</label><input type="text" name="SUP_FAX" class="form-control" value="<?php echo h($supplier["SUP_FAX"]); ?>"></div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-12 form-group"><label>EMAIL</label><input type="text" name="SUP_EMAIL" class="form-control" value="<?php echo h($supplier["SUP_EMAIL"]); ?>"></div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4 form-group"><label>CONTACT</label><input type="text" name="SUP_CONTA" class="form-control" value="<?php echo h($supplier["SUP_CONTA"]); ?>"></div>
                                                <div class="col-md-4 form-group">
                                                    <label>TERM</label>
                                                    <input type="text" name="SUP_TERM" class="form-control" list="termList" value="<?php echo h($supplier["SUP_TERM"]); ?>">
                                                    <datalist id="termList">
                                                        <?php foreach ($termList as $t) { ?>
                                                            <option value="<?php echo h($t); ?>">
                                                        <?php } ?>
                                                    </datalist>
                                                </div>
                                                <div class="col-md-4 form-group"><label>NPWP</label><input type="text" name="SUP_NPWP" class="form-control" value="<?php echo h($supplier["SUP_NPWP"]); ?>"></div>
                                            </div>
                                        </div>
                                        <div class="box-footer">
                                            <button type="submit" name="action" value="save_supplier" class="btn btn-success"><i class="fa fa-save"></i> SIMPAN</button>
                                            <button type="submit" name="action" value="delete_supplier" class="btn btn-danger" onclick="return confirm('Yakin hapus supplier ini?');"><i class="fa fa-trash"></i> DELETE</button>
                                            <a class="btn btn-default" href="<?php echo $urlBase; ?>tab=supplier"><i class="fa fa-undo"></i> BATAL / NEW</a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="small-note">
                            <span class="pull-left">Supplier loaded: <?php echo count($supplierList); ?></span>
                            <form method="post" action="<?php echo $urlBase; ?>tab=supplier" class="pull-right" style="margin-bottom: 5px;">
                                <input type="hidden" name="action" value="export_supplier">
                                <input type="hidden" name="q_supplier" value="<?php echo h($qSupplier); ?>">
                                <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-file-excel-o"></i> Export to Excel</button>
                            </form>
                            <div class="clearfix"></div>
                        </div>
                        <div class="table-container h-300">
                            <table class="table table-bordered table-hover grid" style="margin-bottom: 0;">
                                <thead><tr><th>CODE</th><th>ABBR</th><th>COMPANY</th><th>CURR</th><th>CITY</th><th>PHONE</th><th>INACTIVE</th></tr></thead>
                                <tbody>
                                    <?php for ($i = 0; $i < count($supplierList); $i++) { $r = $supplierList[$i]; ?>
                                        <tr class="<?php echo intval($supplier["SUP_ID"]) == intval($r["SUP_ID"]) ? "selected" : ""; ?>" onclick="location.href='<?php echo $urlBase; ?>tab=supplier&sup_id=<?php echo intval($r["SUP_ID"]); ?>'" style="cursor:pointer;">
                                            <td><?php echo h($r["SUP_CODE"]); ?></td>
                                            <td><?php echo h($r["SUP_ABBR"]); ?></td>
                                            <td><?php echo h($r["SUP_COMP"]); ?></td>
                                            <td><?php echo h($r["CURR_CODE"]); ?></td>
                                            <td><?php echo h($r["SUP_CITY"]); ?></td>
                                            <td><?php echo h($r["SUP_PHONE"]); ?></td>
                                            <td class="text-center"><?php echo intval($r["SUP_PE"]) == 1 ? "<span class='label label-danger'>YES</span>" : ""; ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>

                    <!-- ======================= CURRENCY TAB ======================= -->
                    <?php if ($tab == "currency") { ?>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="box box-primary">
                                    <div class="box-header with-border">
                                        <h3 class="box-title"><i class="fa fa-money"></i> MASTER CURRENCY</h3>
                                    </div>
                                    <form method="post" action="<?php echo $urlBase; ?>tab=currency" autocomplete="off">
                                        <div class="box-body">
                                            <div class="row">
                                                <div class="col-md-12 form-group"><label>CURR_CODE</label><input type="text" name="CURR_CODE" class="form-control" value="<?php echo h($currency["CURR_CODE"]); ?>"></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-12 form-group"><label>DESC</label><input type="text" name="CURR_DESC" class="form-control" value="<?php echo h($currency["CURR_DESC"]); ?>"></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 form-group"><label>SYMBOL</label><input type="text" name="CURR_SYMBOL" class="form-control" value="<?php echo h($currency["CURR_SYMBOL"]); ?>"></div>
                                                <div class="col-md-6 form-group"><label>DEC</label><input type="number" name="CURR_DEC" class="form-control" value="<?php echo h($currency["CURR_DEC"]); ?>"></div>
                                            </div>
                                        </div>
                                        <div class="box-footer">
                                            <button type="submit" name="action" value="save_currency" class="btn btn-success"><i class="fa fa-save"></i> SIMPAN</button>
                                            <a class="btn btn-default" href="<?php echo $urlBase; ?>tab=currency"><i class="fa fa-undo"></i> NEW</a>
                                        </div>
                                    </form>
                                </div>
                                
                                <div class="box box-solid bg-gray-light">
                                    <div class="box-body form-inline">
                                        <div class="form-group autocomplete-box" style="width:100%;">
                                            <div class="input-group" style="width:100%;">
                                                <span class="input-group-addon"><i class="fa fa-search"></i> SEARCH</span>
                                                <input type="text" id="searchCurrency" class="form-control" placeholder="Ketik currency code / desc">
                                            </div>
                                            <div id="searchCurrencyList" class="autocomplete-list"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="small-note">Currency loaded: <?php echo count($currencyList); ?></div>
                                <div class="table-container h-200">
                                    <table class="table table-bordered table-hover grid" style="margin-bottom:0;">
                                        <thead><tr><th>CODE</th><th>DESC</th><th>SYMBOL</th><th>DEC</th></tr></thead>
                                        <tbody>
                                            <?php for ($i = 0; $i < count($currencyList); $i++) { $r = $currencyList[$i]; ?>
                                                <tr class="<?php echo strtoupper($currency["CURR_CODE"]) == strtoupper($r["CURR_CODE"]) ? "selected" : ""; ?>" onclick="location.href='<?php echo $urlBase; ?>tab=currency&curr_code=<?php echo urlencode($r["CURR_CODE"]); ?>'" style="cursor:pointer;">
                                                    <td><?php echo h($r["CURR_CODE"]); ?></td>
                                                    <td><?php echo h($r["CURR_DESC"]); ?></td>
                                                    <td><?php echo h($r["CURR_SYMBOL"]); ?></td>
                                                    <td><?php echo h($r["CURR_DEC"]); ?></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="box box-info">
                                    <div class="box-header with-border">
                                        <h3 class="box-title"><i class="fa fa-line-chart"></i> CURRENCY RATE</h3>
                                    </div>
                                    <form method="post" action="<?php echo $urlBase; ?>tab=currency&curr_code=<?php echo urlencode($currency["CURR_CODE"]); ?>" autocomplete="off">
                                        <div class="box-body">
                                            <input type="hidden" name="RATE_CURR_CODE" value="<?php echo h($currency["CURR_CODE"]); ?>">
                                            <input type="hidden" name="RATE_SDATE_OLD" value="<?php echo h(date_out($rateData["CURR_SDATE"])); ?>">
                                            <div class="row">
                                                <div class="col-md-12 form-group"><label>CURR_CODE</label><input type="text" class="form-control" value="<?php echo h($currency["CURR_CODE"]); ?>" readonly style="background:#eee;"></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 form-group"><label>S DATE</label><input type="date" name="CURR_SDATE" class="form-control" value="<?php echo h(date_out($rateData["CURR_SDATE"])); ?>"></div>
                                                <div class="col-md-6 form-group"><label>E DATE</label><input type="date" name="CURR_EDATE" class="form-control" value="<?php echo h(date_out($rateData["CURR_EDATE"])); ?>"></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 form-group"><label>C RATE</label><input type="text" name="CURR_CRATE" class="form-control" value="<?php echo h($rateData["CURR_CRATE"]); ?>"></div>
                                                <div class="col-md-6 form-group"><label>V RATE</label><input type="text" name="CURR_VRATE" class="form-control" value="<?php echo h($rateData["CURR_VRATE"]); ?>"></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 form-group"><label>MM</label><input type="number" name="CURR_MM" class="form-control" value="<?php echo h($rateData["CURR_MM"]); ?>"></div>
                                                <div class="col-md-6 form-group"><label>YY</label><input type="number" name="CURR_YY" class="form-control" value="<?php echo h($rateData["CURR_YY"]); ?>"></div>
                                            </div>
                                        </div>
                                        <div class="box-footer">
                                            <button type="submit" name="action" value="save_rate" class="btn btn-success"><i class="fa fa-save"></i> SIMPAN RATE</button>
                                            <button type="submit" name="action" value="delete_rate" class="btn btn-danger" onclick="return confirm('Delete rate ini?');"><i class="fa fa-trash"></i> HAPUS RATE</button>
                                        </div>
                                    </form>
                                </div>
                                
                                <div class="small-note">Currency rate loaded: <?php echo count($rateList); ?></div>
                                <div class="table-container h-300">
                                    <table class="table table-bordered table-hover grid" style="margin-bottom:0;">
                                        <thead><tr><th>S DATE</th><th>E DATE</th><th>C RATE</th><th>V RATE</th><th>MM</th><th>YY</th></tr></thead>
                                        <tbody>
                                            <?php for ($i = 0; $i < count($rateList); $i++) { $r = $rateList[$i]; $s = date_out($r["CURR_SDATE"]); ?>
                                                <tr class="<?php echo $s == date_out($rateData["CURR_SDATE"]) ? "selected" : ""; ?>" onclick="location.href='<?php echo $urlBase; ?>tab=currency&curr_code=<?php echo urlencode($r["CURR_CODE"]); ?>&rate_sdate=<?php echo urlencode($s); ?>'" style="cursor:pointer;">
                                                    <td><?php echo h($s); ?></td>
                                                    <td><?php echo h(date_out($r["CURR_EDATE"])); ?></td>
                                                    <td class="text-right"><?php echo h((float)$r["CURR_CRATE"]); ?></td>
                                                    <td class="text-right"><?php echo h((float)$r["CURR_VRATE"]); ?></td>
                                                    <td><?php echo h($r["CURR_MM"]); ?></td>
                                                    <td><?php echo h($r["CURR_YY"]); ?></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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
        
        var targetUrl = "";
        if (ajaxMode === "supplier") {
            targetUrl = "search_sup.php?q=" + acEnc(q);
        } else {
            targetUrl = "<?php echo $urlBase; ?>ajax=" + acEnc(ajaxMode) + "&q=" + acEnc(q);
        }

        xhr.open("GET", targetUrl, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                var raw = xhr.responseText.trim();
                
                // Sanitasi: Ekstrak JSON murni bila tercampur output HTML layout dari index.php
                var start = raw.indexOf('{');
                var end = raw.lastIndexOf('}');
                if (start !== -1 && end !== -1 && end > start) {
                    raw = raw.substring(start, end + 1);
                }

                var result;
                try { 
                    result = JSON.parse(raw); 
                } catch (e) { 
                    console.error("Gagal parse JSON autocomplete (" + ajaxMode + "):", e, xhr.responseText);
                    hideList(); 
                    return; 
                }
                
                if (result && result.rows) { 
                    render(result.rows); 
                } else { 
                    hideList(); 
                }
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

// Inisialisasi Autocomplete
setupAutocomplete("searchCustomer", "searchCustomerList", "customer",
    function(r) { return "<b>" + acHtml(r.CUST_CODE) + "</b> - " + acHtml(r.CUST_COMP) + "<div class='autocomplete-sub'>ABBR: " + acHtml(r.CUST_ABBR) + " | CURR: " + acHtml(r.CURR_CODE) + "</div>"; },
    function(r) { location.href = "<?php echo $urlBase; ?>tab=customer&cust_id=" + acEnc(r.CUST_ID); }
);

setupAutocomplete("searchItem", "searchItemList", "item",
    function(r) { return "<b>" + acHtml(r.ITEM_CODE) + "</b> - " + acHtml(r.ITEM_NAME) + "<div class='autocomplete-sub'>ITEM NO: " + acHtml(r.ITEM_NO) + "</div>"; },
    function(r) { location.href = "<?php echo $urlBase; ?>tab=price&item_id=" + acEnc(r.ITEM_ID); }
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
    function(r) { location.href = "<?php echo $urlBase; ?>tab=currency&curr_code=" + acEnc(r.CURR_CODE); }
);

setupAutocomplete("searchSupplier", "searchSupplierList", "supplier",
    function(r) { return "<b>" + acHtml(r.SUP_CODE) + "</b> - " + acHtml(r.SUP_COMP) + "<div class='autocomplete-sub'>ABBR: " + acHtml(r.SUP_ABBR) + " | CURR: " + acHtml(r.CURR_CODE) + "</div>"; },
    function(r) { location.href = "<?php echo $urlBase; ?>tab=supplier&sup_id=" + acEnc(r.SUP_ID); }
);

function checkPricePassword(el) {
    if (el.hasAttribute('readonly')) {
        var pwd = prompt("Masukkan password untuk edit harga:");
        if (pwd === 'q9tj9') {
            el.removeAttribute('readonly');
            el.focus();
            el.placeholder = ""; 
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
        
        if(fOld)   fOld.value   = "";

        var btn = document.querySelector('button[value="save_price_detail"]');
        if(btn) btn.click();
    }
}
</script>
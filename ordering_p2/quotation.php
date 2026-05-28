<?php
require_once __DIR__ . "/../config/db_plant2.php";

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

function fmt_num($value, $decimal = 2) {
    if ($value === null || $value === "") {
        return "";
    }
    $n = (float)$value;
    if ($n == 0) {
        return "";
    }
    return number_format($n, $decimal, ".", ",");
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

function date_display($value) {
    if ($value instanceof DateTime) {
        return $value->format("d-m-Y");
    }
    if ($value === null || $value === "") {
        return "";
    }
    $ts = strtotime((string)$value);
    if ($ts === false) {
        return safe_trim($value);
    }
    return date("d-m-Y", $ts);
}

function sql_error_text() {
    return print_r(sqlsrv_errors(), true);
}

function query_one($conn, $sql, $params = array()) {
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

function query_all($conn, $sql, $params = array()) {
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
// AJAX CUSTOMER AUTOCOMPLETE
// ==========================================================
$ajax = get_param("ajax", "");

if ($ajax == "customer") {
    $q = get_param("q", "");
    $like = "%" . $q . "%";

    $rows = query_all(
        $conn,
        "
        SELECT TOP 10
            CUST_ID,
            ISNULL(CUST_CODE, '') AS CUST_CODE,
            ISNULL(CUST_COMP, '') AS CUST_COMP
        FROM dbo.CUST
        WHERE
            (? = ''
             OR ISNULL(CUST_CODE, '') LIKE ?
             OR ISNULL(CUST_COMP, '') LIKE ?)
        ORDER BY CUST_CODE
        ",
        array($q, $like, $like)
    );

    $out = array();
    for ($i = 0; $i < count($rows); $i++) {
        $out[] = array(
            "CUST_ID" => intval($rows[$i]["CUST_ID"]),
            "CUST_CODE" => safe_trim($rows[$i]["CUST_CODE"]),
            "CUST_COMP" => safe_trim($rows[$i]["CUST_COMP"])
        );
    }

    json_out(array("success" => true, "rows" => $out));
}

// ==========================================================
// PARAMETER
// ==========================================================
$action = get_param("action", "");
$msg = "";
$err = "";

$q = get_param("q", "");
$selectedQuoId = safe_int(get_param("quo_id"), 0);
$forceQuoId = 0;

// ==========================================================
// SAVE / DELETE MASTER_QUOTATION
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && $action == "save") {
    $quoId = safe_int(get_param("QUO_ID"), 0);
    $custId = safe_int(get_param("CUST_ID"), 0);

    if ($custId <= 0) {
        $err = "Customer belum dipilih.";
    } else {
        if ($quoId > 0) {
            $sql = "
                UPDATE dbo.MASTER_QUOTATION
                SET
                    CUST_ID = ?,
                    MODEL = ?,
                    PART_NAME = ?,
                    PART_NO = ?,
                    REMARKS = ?,
                    REVISI_NO = ?,
                    QUO_NO = ?,
                    QUO_DATE = ?,
                    PRICE_MOLD = ?,
                    PRICE_PART = ?,
                    REMARK = ?,
                    STATUS_PO = ?
                WHERE QUO_ID = ?
            ";

            $params = array(
                $custId,
                get_param("MODEL"),
                get_param("PART_NAME"),
                get_param("PART_NO"),
                get_param("REMARKS"),
                safe_int(get_param("REVISI_NO"), 0),
                get_param("QUO_NO"),
                get_param("QUO_DATE"),
                safe_float(get_param("PRICE_MOLD"), 0),
                safe_float(get_param("PRICE_PART"), 0),
                get_param("REMARK"),
                get_param("STATUS_PO"),
                $quoId
            );

            $stmt = sqlsrv_query($conn, $sql, $params);
            if ($stmt === false) {
                $err = "Gagal update MASTER_QUOTATION: " . sql_error_text();
            } else {
                $msg = "Quotation berhasil diupdate.";
                $forceQuoId = $quoId;
            }
        } else {
            $sql = "
                SET NOCOUNT ON;
                INSERT INTO dbo.MASTER_QUOTATION
                (
                    CUST_ID,
                    MODEL,
                    PART_NAME,
                    PART_NO,
                    REMARKS,
                    REVISI_NO,
                    QUO_NO,
                    QUO_DATE,
                    PRICE_MOLD,
                    PRICE_PART,
                    REMARK,
                    STATUS_PO
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
                SELECT CONVERT(INT, SCOPE_IDENTITY()) AS NEW_ID;
            ";

            $params = array(
                $custId,
                get_param("MODEL"),
                get_param("PART_NAME"),
                get_param("PART_NO"),
                get_param("REMARKS"),
                safe_int(get_param("REVISI_NO"), 0),
                get_param("QUO_NO"),
                get_param("QUO_DATE"),
                safe_float(get_param("PRICE_MOLD"), 0),
                safe_float(get_param("PRICE_PART"), 0),
                get_param("REMARK"),
                get_param("STATUS_PO")
            );

            $newId = insert_and_get_id($conn, $sql, $params, "NEW_ID");
            if ($newId === false || $newId <= 0) {
                $err = "Gagal insert MASTER_QUOTATION: " . sql_error_text();
            } else {
                $msg = "Quotation baru berhasil disimpan.";
                $forceQuoId = $newId;
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $action == "delete") {
    $quoId = safe_int(get_param("QUO_ID"), 0);

    if ($quoId <= 0) {
        $err = "Pilih quotation dulu.";
    } else {
        $stmt = sqlsrv_query($conn, "DELETE FROM dbo.MASTER_QUOTATION WHERE QUO_ID = ?", array($quoId));
        if ($stmt === false) {
            $err = "Gagal delete MASTER_QUOTATION: " . sql_error_text();
        } else {
            $msg = "Quotation berhasil dihapus.";
            $forceQuoId = 0;
            $selectedQuoId = 0;
        }
    }
}

if ($forceQuoId > 0) {
    $selectedQuoId = $forceQuoId;
}

// ==========================================================
// LOAD DATA MASTER_QUOTATION
// TAMPIL TOP 10 SAJA
// ==========================================================
$quotation = array(
    "QUO_ID" => "",
    "CUST_ID" => "",
    "CUST_CODE" => "",
    "CUST_COMP" => "",
    "MODEL" => "",
    "PART_NAME" => "",
    "PART_NO" => "",
    "REMARKS" => "",
    "REVISI_NO" => "",
    "QUO_NO" => "",
    "QUO_DATE" => "",
    "PRICE_MOLD" => "",
    "PRICE_PART" => "",
    "REMARK" => "",
    "STATUS_PO" => ""
);

$likeQ = "%" . $q . "%";

$sqlList = "
    SELECT TOP 10
        Q.QUO_ID,
        Q.CUST_ID,
        ISNULL(C.CUST_CODE, '') AS CUST_CODE,
        ISNULL(C.CUST_COMP, '') AS CUST_COMP,
        ISNULL(Q.MODEL, '') AS MODEL,
        ISNULL(Q.PART_NAME, '') AS PART_NAME,
        ISNULL(Q.PART_NO, '') AS PART_NO,
        ISNULL(Q.REMARKS, '') AS REMARKS,
        ISNULL(Q.REVISI_NO, 0) AS REVISI_NO,
        ISNULL(Q.QUO_NO, '') AS QUO_NO,
        Q.QUO_DATE,
        ISNULL(Q.PRICE_MOLD, 0) AS PRICE_MOLD,
        ISNULL(Q.PRICE_PART, 0) AS PRICE_PART,
        ISNULL(Q.REMARK, '') AS REMARK,
        ISNULL(Q.STATUS_PO, '') AS STATUS_PO
    FROM dbo.MASTER_QUOTATION AS Q
    LEFT JOIN dbo.CUST AS C
        ON Q.CUST_ID = C.CUST_ID
    WHERE
        (? = ''
         OR ISNULL(C.CUST_CODE, '') LIKE ?
         OR ISNULL(C.CUST_COMP, '') LIKE ?
         OR ISNULL(Q.PART_NAME, '') LIKE ?
         OR ISNULL(Q.PART_NO, '') LIKE ?
         OR ISNULL(Q.QUO_NO, '') LIKE ?)
    ORDER BY
        Q.QUO_DATE DESC,
        Q.QUO_ID DESC
";

$quotationRows = query_all($conn, $sqlList, array($q, $likeQ, $likeQ, $likeQ, $likeQ, $likeQ));

if ($selectedQuoId > 0) {
    $rowSelected = query_one(
        $conn,
        "
        SELECT TOP 1
            Q.QUO_ID,
            Q.CUST_ID,
            ISNULL(C.CUST_CODE, '') AS CUST_CODE,
            ISNULL(C.CUST_COMP, '') AS CUST_COMP,
            ISNULL(Q.MODEL, '') AS MODEL,
            ISNULL(Q.PART_NAME, '') AS PART_NAME,
            ISNULL(Q.PART_NO, '') AS PART_NO,
            ISNULL(Q.REMARKS, '') AS REMARKS,
            ISNULL(Q.REVISI_NO, 0) AS REVISI_NO,
            ISNULL(Q.QUO_NO, '') AS QUO_NO,
            Q.QUO_DATE,
            ISNULL(Q.PRICE_MOLD, 0) AS PRICE_MOLD,
            ISNULL(Q.PRICE_PART, 0) AS PRICE_PART,
            ISNULL(Q.REMARK, '') AS REMARK,
            ISNULL(Q.STATUS_PO, '') AS STATUS_PO
        FROM dbo.MASTER_QUOTATION AS Q
        LEFT JOIN dbo.CUST AS C
            ON Q.CUST_ID = C.CUST_ID
        WHERE Q.QUO_ID = ?
        ",
        array($selectedQuoId)
    );

    if ($rowSelected) {
        $quotation = array_merge($quotation, $rowSelected);
    }
}

$customerText = trim($quotation["CUST_CODE"] . " " . $quotation["CUST_COMP"]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Master List Quotation</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #eeeeee;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            color: #000000;
        }

        .topbar {
            height: 36px;
            background: #eeeeee;
            border-bottom: 1px solid #cccccc;
            padding: 4px 8px;
            box-sizing: border-box;
        }

        .menu-link {
            display: inline-block;
            padding: 3px 7px;
            color: #000000;
            text-decoration: none;
        }

        .menu-link:hover {
            background: #d6e9ff;
        }

        .title {
            text-align: center;
            font-size: 34px;
            font-weight: normal;
            letter-spacing: 1px;
            margin: 8px 0 6px 0;
        }

        .wrap {
            width: 1320px;
            margin: 0 auto 20px auto;
        }

        .toolbar {
            margin-bottom: 6px;
            background: #f5f5f5;
            border: 1px solid #999999;
            padding: 6px;
        }

        input[type="text"], input[type="date"], input[type="number"] {
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

        .btn {
            display: inline-block;
            border: 2px outset #ffffff;
            background: #d4d0c8;
            color: #000000;
            padding: 4px 12px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            margin-right: 4px;
        }

        .btn:active {
            border: 2px inset #ffffff;
        }

        .msg {
            background: #e9ffe9;
            color: #004000;
            border: 1px solid #008000;
            padding: 7px;
            margin-bottom: 6px;
        }

        .err {
            background: #ffe9e9;
            color: #800000;
            border: 1px solid #800000;
            padding: 7px;
            margin-bottom: 6px;
            white-space: pre-wrap;
        }

        .grid-wrap {
            border: 1px solid #888888;
            background: #ffffff;
            overflow: auto;
            height: 230px;
        }

        table.grid {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            font-size: 12px;
        }

        table.grid th,
        table.grid td {
            border: 1px solid #b9b9b9;
            padding: 2px 4px;
            height: 20px;
            white-space: nowrap;
            overflow: hidden;
        }

        table.grid th {
            background: #e8e8e8;
            position: sticky;
            top: 0;
            z-index: 2;
            text-align: left;
            font-weight: normal;
        }

        table.grid tr:hover td {
            background: #d9ecff;
        }

        table.grid tr.selected td {
            background: #2f70c9;
            color: #ffffff;
        }

        .edit-panel {
            margin-top: 8px;
            background: #f5f5f5;
            border: 1px solid #999999;
            padding: 8px;
        }

        .row {
            margin-bottom: 6px;
            white-space: nowrap;
        }

        label {
            display: inline-block;
            width: 82px;
        }

        .w60 { width: 60px; }
        .w80 { width: 80px; }
        .w100 { width: 100px; }
        .w120 { width: 120px; }
        .w150 { width: 150px; }
        .w180 { width: 180px; }
        .w220 { width: 220px; }
        .w280 { width: 280px; }
        .w360 { width: 360px; }
        .w520 { width: 520px; }

        .right { text-align: right; }

        .autocomplete-box {
            position: relative;
            display: inline-block;
            vertical-align: middle;
        }

        .autocomplete-list {
            position: absolute;
            left: 0;
            top: 24px;
            width: 520px;
            max-height: 230px;
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
    </style>
</head>
<body>

<div class="topbar">

    <a class="menu-link" href="quotation_report.php" target="_blank">REPORT</a>
</div>

<div class="title">MASTER LIST QUOTATION</div>

<div class="wrap">
    <?php if ($msg != "") { ?>
        <div class="msg"><?php echo h($msg); ?></div>
    <?php } ?>
    <?php if ($err != "") { ?>
        <div class="err"><?php echo h($err); ?></div>
    <?php } ?>

    <div class="toolbar">
        <form method="get" action="quotation.php" autocomplete="off">
            Search:
            <input type="text" name="q" value="<?php echo h($q); ?>" class="w360" placeholder="Customer / Part / Quotation No">
            <button type="submit" class="btn">CARI</button>
            <a class="btn" href="quotation.php">REFRESH</a>
            <a class="btn" href="quotation_report.php" target="_blank">MASTER LIST QUOTATION REPORT</a>
        
            <span style="margin-left:20px;color:#000080;font-weight:bold;">Table: MASTER_QUOTATION | Tampil: TOP 10</span>
        </form>
    </div>

    <div class="grid-wrap">
        <table class="grid">
            <thead>
                <tr>
                    <th style="width:55px;">CODE</th>
                    <th style="width:210px;">CUSTOMER</th>
                    <th style="width:120px;">MODEL</th>
                    <th style="width:220px;">PART_NAME</th>
                    <th style="width:180px;">PART_NO</th>
                    <th style="width:110px;">REMARKS</th>
                    <th style="width:55px;">REV_NO</th>
                    <th style="width:110px;">QUO NO</th>
                    <th style="width:95px;">QUO DATE</th>
                    <th style="width:90px;">MOLD PRICE</th>
                    <th style="width:90px;">PART PRICE</th>
                    <th style="width:90px;">REMARK</th>
                    <th style="width:90px;">STATUS_PO</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < count($quotationRows); $i++) { $r = $quotationRows[$i]; ?>
                    <tr class="<?php echo intval($r["QUO_ID"]) == intval($selectedQuoId) ? "selected" : ""; ?>"
                        onclick="location.href='quotation.php?quo_id=<?php echo intval($r["QUO_ID"]); ?>&q=<?php echo urlencode($q); ?>'">
                        <td><?php echo h($r["CUST_CODE"]); ?></td>
                        <td><?php echo h($r["CUST_COMP"]); ?></td>
                        <td><?php echo h($r["MODEL"]); ?></td>
                        <td><?php echo h($r["PART_NAME"]); ?></td>
                        <td><?php echo h($r["PART_NO"]); ?></td>
                        <td><?php echo h($r["REMARKS"]); ?></td>
                        <td class="right"><?php echo h($r["REVISI_NO"]); ?></td>
                        <td><?php echo h($r["QUO_NO"]); ?></td>
                        <td><?php echo h(date_display($r["QUO_DATE"])); ?></td>
                        <td class="right"><?php echo h(fmt_num($r["PRICE_MOLD"], 2)); ?></td>
                        <td class="right"><?php echo h(fmt_num($r["PRICE_PART"], 2)); ?></td>
                        <td><?php echo h($r["REMARK"]); ?></td>
                        <td><?php echo h($r["STATUS_PO"]); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <div class="edit-panel">
        <form method="post" action="quotation.php" autocomplete="off">
            <input type="hidden" name="QUO_ID" value="<?php echo h($quotation["QUO_ID"]); ?>">
            <input type="hidden" id="CUST_ID" name="CUST_ID" value="<?php echo h($quotation["CUST_ID"]); ?>">

            <div class="row">
                <label>QUO_ID</label>
                <input type="text" class="w80" value="<?php echo h($quotation["QUO_ID"]); ?>" readonly>

                <label>CUSTOMER</label>
                <span class="autocomplete-box">
                    <input type="text" id="customerText" class="w360" value="<?php echo h($customerText); ?>" placeholder="Ketik customer">
                    <div id="customerSuggest" class="autocomplete-list"></div>
                </span>

                <label style="width:60px;">MODEL</label>
                <input type="text" name="MODEL" class="w180" value="<?php echo h($quotation["MODEL"]); ?>">
            </div>

            <div class="row">
                <label>PART_NAME</label>
                <input type="text" name="PART_NAME" class="w360" value="<?php echo h($quotation["PART_NAME"]); ?>">

                <label>PART_NO</label>
                <input type="text" name="PART_NO" class="w280" value="<?php echo h($quotation["PART_NO"]); ?>">

                <label>REMARKS</label>
                <input type="text" name="REMARKS" class="w180" value="<?php echo h($quotation["REMARKS"]); ?>">
            </div>

            <div class="row">
                <label>REV_NO</label>
                <input type="number" name="REVISI_NO" class="w80" value="<?php echo h($quotation["REVISI_NO"]); ?>">

                <label>QUO NO</label>
                <input type="text" name="QUO_NO" class="w150" value="<?php echo h($quotation["QUO_NO"]); ?>">

                <label>QUO DATE</label>
                <input type="date" name="QUO_DATE" class="w150" value="<?php echo h(date_out($quotation["QUO_DATE"])); ?>">

                <label>MOLD PRICE</label>
                <input type="text" name="PRICE_MOLD" class="w120" value="<?php echo h($quotation["PRICE_MOLD"]); ?>">

                <label>PART PRICE</label>
                <input type="text" name="PRICE_PART" class="w120" value="<?php echo h($quotation["PRICE_PART"]); ?>">
            </div>

            <div class="row">
                <label>REMARK</label>
                <input type="text" name="REMARK" class="w280" value="<?php echo h($quotation["REMARK"]); ?>">

                <label>STATUS_PO</label>
                <input type="text" name="STATUS_PO" class="w150" value="<?php echo h($quotation["STATUS_PO"]); ?>">

                <button type="submit" name="action" value="save" class="btn">SIMPAN</button>
                <button type="submit" name="action" value="delete" class="btn" onclick="return confirm('Delete quotation ini?');">DELETE</button>
                <a class="btn" href="quotation.php">NEW</a>
            </div>
        </form>
    </div>
</div>

<script>
function enc(value) {
    return encodeURIComponent(value == null ? "" : value);
}
function htmlEncode(value) {
    return String(value == null ? "" : value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

var custRows = [];
var custActive = -1;
var custTimer = null;

function hideCustomerSuggest() {
    var box = document.getElementById("customerSuggest");
    box.style.display = "none";
    box.innerHTML = "";
    custRows = [];
    custActive = -1;
}

function setCustomerActive(index) {
    var box = document.getElementById("customerSuggest");
    var items = box.getElementsByClassName("autocomplete-item");
    if (!items || items.length == 0) {
        custActive = -1;
        return;
    }
    if (index < 0) { index = items.length - 1; }
    if (index >= items.length) { index = 0; }
    for (var i = 0; i < items.length; i++) {
        items[i].className = "autocomplete-item";
    }
    items[index].className = "autocomplete-item active";
    custActive = index;
}

function chooseCustomer(index) {
    if (index < 0 || index >= custRows.length) {
        return;
    }
    document.getElementById("CUST_ID").value = custRows[index].CUST_ID;
    document.getElementById("customerText").value = custRows[index].CUST_CODE + " " + custRows[index].CUST_COMP;
    hideCustomerSuggest();
}

function renderCustomerSuggest(rows) {
    var box = document.getElementById("customerSuggest");
    box.innerHTML = "";
    custRows = rows || [];
    custActive = -1;

    if (custRows.length == 0) {
        hideCustomerSuggest();
        return;
    }

    for (var i = 0; i < custRows.length; i++) {
        (function(r, idx) {
            var div = document.createElement("div");
            div.className = "autocomplete-item";
            div.innerHTML = "<b>" + htmlEncode(r.CUST_CODE) + "</b> - " + htmlEncode(r.CUST_COMP);
            div.onmouseover = function() { setCustomerActive(idx); };
            div.onmousedown = function(e) {
                if (e && e.preventDefault) { e.preventDefault(); }
                chooseCustomer(idx);
            };
            box.appendChild(div);
        })(custRows[i], i);
    }
    box.style.display = "block";
    setCustomerActive(0);
}

function searchCustomer(q) {
    if (q == "") {
        hideCustomerSuggest();
        return;
    }
    var xhr = new XMLHttpRequest();
    xhr.open("GET", "quotation.php?ajax=customer&q=" + enc(q), true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            var result;
            try {
                result = JSON.parse(xhr.responseText);
            } catch (e) {
                hideCustomerSuggest();
                return;
            }
            if (result && result.rows) {
                renderCustomerSuggest(result.rows);
            } else {
                hideCustomerSuggest();
            }
        }
    };
    xhr.send(null);
}

var custInput = document.getElementById("customerText");
if (custInput) {
    custInput.onkeyup = function(e) {
        e = e || window.event;
        var key = e.keyCode || e.which;

        if (key == 40) {
            setCustomerActive(custActive + 1);
            return false;
        }
        if (key == 38) {
            setCustomerActive(custActive - 1);
            return false;
        }
        if (key == 13) {
            if (custRows.length > 0) {
                if (custActive < 0) { custActive = 0; }
                chooseCustomer(custActive);
                return false;
            }
            return true;
        }

        clearTimeout(custTimer);
        var q = this.value;
        custTimer = setTimeout(function() {
            searchCustomer(q);
        }, 250);
    };

    custInput.onblur = function() {
        setTimeout(function() { hideCustomerSuggest(); }, 250);
    };
}
</script>

</body>
</html>

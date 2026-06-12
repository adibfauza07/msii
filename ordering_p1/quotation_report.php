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
    if (isset($_GET[$name])) {
        return trim($_GET[$name]);
    }
    if (isset($_POST[$name])) {
        return trim($_POST[$name]);
    }
    return $default;
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

function print_datetime() {
    return date("d-M-Y H:i:s");
}

function sql_error_text() {
    return print_r(sqlsrv_errors(), true);
}

function json_out($data) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data);
    exit();
}

// ==========================================================
// AJAX CUSTOMER AUTOCOMPLETE UNTUK FILTER REPORT
// ==========================================================
$ajax = get_param("ajax", "");

if ($ajax == "customer") {
    $q = get_param("q", "");
    $like = "%" . $q . "%";

    $sqlCust = "
        SELECT TOP 10
            ISNULL(CUST_CODE, '') AS CUST_CODE,
            ISNULL(CUST_COMP, '') AS CUST_COMP
        FROM dbo.CUST
        WHERE
            (? = ''
             OR ISNULL(CUST_CODE, '') LIKE ?
             OR ISNULL(CUST_COMP, '') LIKE ?)
        ORDER BY CUST_CODE
    ";

    $stmtCust = sqlsrv_query($conn, $sqlCust, array($q, $like, $like));

    if ($stmtCust === false) {
        json_out(array("success" => false, "rows" => array(), "message" => sql_error_text()));
    }

    $out = array();
    while ($c = sqlsrv_fetch_array($stmtCust, SQLSRV_FETCH_ASSOC)) {
        $out[] = array(
            "CUST_CODE" => safe_trim($c["CUST_CODE"]),
            "CUST_COMP" => safe_trim($c["CUST_COMP"])
        );
    }

    json_out(array("success" => true, "rows" => $out));
}

// ==========================================================
// PARAMETER REPORT
// Default tidak load dulu, sama konsep report lain.
// Klik FILTER baru RUN=1.
// ==========================================================
$run = get_param("RUN", "") == "1";
$export = get_param("EXPORT", "") == "1";
$custCode = get_param("CUST_CODE", "%");
$searchText = get_param("SEARCH", "");

if ($custCode == "") {
    $custCode = "%";
}

$whereSql = "1 = 1";
$params = array();

if ($custCode != "%") {
    $whereSql .= " AND ISNULL(C.CUST_CODE, '') LIKE ?";
    $params[] = $custCode . "%";
}

if ($searchText != "") {
    $likeSearch = "%" . $searchText . "%";
    $whereSql .= "
        AND
        (
            ISNULL(Q.PART_NAME, '') LIKE ?
            OR ISNULL(Q.PART_NO, '') LIKE ?
            OR ISNULL(Q.QUO_NO, '') LIKE ?
            OR ISNULL(Q.REMARKS, '') LIKE ?
            OR ISNULL(Q.REMARK, '') LIKE ?
            OR ISNULL(Q.STATUS_PO, '') LIKE ?
        )
    ";
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $params[] = $likeSearch;
}

$rows = array();
$printRows = array();
$pages = array();
$totalPages = 0;
$rowsPerPage = 34;

if ($run || $export) {
    $sql = "
        SET NOCOUNT ON;

        SELECT
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
        WHERE " . $whereSql . "
        ORDER BY
            ISNULL(C.CUST_CODE, ''),
            Q.QUO_DATE DESC,
            Q.QUO_ID DESC
    ";

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die("<pre>Query MASTER_QUOTATION report gagal:\n" . h(sql_error_text()) . "</pre>");
    }

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = array(
            "QUO_ID" => intval($r["QUO_ID"]),
            "CUST_ID" => intval($r["CUST_ID"]),
            "CUST_CODE" => safe_trim($r["CUST_CODE"]),
            "CUST_COMP" => safe_trim($r["CUST_COMP"]),
            "MODEL" => safe_trim($r["MODEL"]),
            "PART_NAME" => safe_trim($r["PART_NAME"]),
            "PART_NO" => safe_trim($r["PART_NO"]),
            "REMARKS" => safe_trim($r["REMARKS"]),
            "REVISI_NO" => intval($r["REVISI_NO"]),
            "QUO_NO" => safe_trim($r["QUO_NO"]),
            "QUO_DATE" => $r["QUO_DATE"],
            "PRICE_MOLD" => (float)$r["PRICE_MOLD"],
            "PRICE_PART" => (float)$r["PRICE_PART"],
            "REMARK" => safe_trim($r["REMARK"]),
            "STATUS_PO" => safe_trim($r["STATUS_PO"])
        );
    }

    for ($i = 0; $i < count($rows); $i++) {
        $printRows[] = array_merge(array("NO" => $i + 1), $rows[$i]);
    }

    if (count($printRows) == 0) {
        $printRows[] = array("ROW_TYPE" => "EMPTY", "MESSAGE" => "Data quotation tidak ditemukan.");
    }

    $pages = array_chunk($printRows, $rowsPerPage);
    $totalPages = count($pages);

    if ($totalPages <= 0) {
        $totalPages = 1;
    }
}

// ==========================================================
// EXPORT EXCEL
// ==========================================================
if ($export) {
    $fileCust = $custCode == "%" ? "ALL" : $custCode;
    $fileName = "master_list_quotation_" . $fileCust . "_" . date("Ymd_His") . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 10pt; }
            th { background: #d9eaf7; border: 1px solid #000000; font-weight: bold; }
            td { border: 1px solid #000000; }
            .text { mso-number-format: "\@"; }
            .num { mso-number-format: "#,##0.00"; text-align: right; }
            .title { font-size: 16pt; font-weight: bold; text-align: center; }
        </style>
    </head>
    <body>
    <table>
        <tr><td colspan="15" class="title">MASTER LIST QUOTATION</td></tr>
        <tr><td colspan="15">P.T. IMC TEKNO INDONESIA</td></tr>
        <tr><td colspan="15">Customer: <?php echo h($custCode == "%" ? "ALL" : $custCode); ?></td></tr>
        <tr><td colspan="15">Search: <?php echo h($searchText == "" ? "-" : $searchText); ?></td></tr>
        <tr><td colspan="15">Export Date: <?php echo h(print_datetime()); ?></td></tr>
        <tr><td colspan="15">&nbsp;</td></tr>
        <tr>
            <th>No</th>
            <th>Code</th>
            <th>Customer</th>
            <th>Model</th>
            <th>Part Name</th>
            <th>Part No</th>
            <th>Remarks</th>
            <th>Rev No</th>
            <th>Quo No</th>
            <th>Quo Date</th>
            <th>Mold Price</th>
            <th>Part Price</th>
            <th>Remark</th>
            <th>Status PO</th>
            <th>Quo ID</th>
        </tr>
        <?php for ($i = 0; $i < count($printRows); $i++) { $r = $printRows[$i]; ?>
            <?php if (isset($r["ROW_TYPE"]) && $r["ROW_TYPE"] == "EMPTY") { ?>
                <tr><td colspan="15"><?php echo h($r["MESSAGE"]); ?></td></tr>
            <?php } else { ?>
                <tr>
                    <td class="num"><?php echo h($r["NO"]); ?></td>
                    <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
                    <td class="text"><?php echo h($r["CUST_COMP"]); ?></td>
                    <td class="text"><?php echo h($r["MODEL"]); ?></td>
                    <td class="text"><?php echo h($r["PART_NAME"]); ?></td>
                    <td class="text"><?php echo h($r["PART_NO"]); ?></td>
                    <td class="text"><?php echo h($r["REMARKS"]); ?></td>
                    <td class="num"><?php echo h($r["REVISI_NO"]); ?></td>
                    <td class="text"><?php echo h($r["QUO_NO"]); ?></td>
                    <td class="text"><?php echo h(date_display($r["QUO_DATE"])); ?></td>
                    <td class="num"><?php echo h($r["PRICE_MOLD"] == 0 ? "" : $r["PRICE_MOLD"]); ?></td>
                    <td class="num"><?php echo h($r["PRICE_PART"] == 0 ? "" : $r["PRICE_PART"]); ?></td>
                    <td class="text"><?php echo h($r["REMARK"]); ?></td>
                    <td class="text"><?php echo h($r["STATUS_PO"]); ?></td>
                    <td class="num"><?php echo h($r["QUO_ID"]); ?></td>
                </tr>
            <?php } ?>
        <?php } ?>
    </table>
    </body>
    </html>
    <?php
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Master List Quotation Report</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm;
        }

        body {
            margin: 0;
            background: #9a9a9a;
            font-family: "Courier New", monospace;
            font-size: 8px;
            color: #000000;
        }

        .filter-bar {
            width: 285mm;
            margin: 8px auto;
            background: #d4d0c8;
            border: 1px solid #666666;
            padding: 6px;
            box-sizing: border-box;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
        }

        .filter-bar input {
            height: 24px;
            border: 1px solid #777777;
            font-size: 12px;
            padding: 2px 4px;
            box-sizing: border-box;
        }

        .filter-bar button,
        .filter-bar a {
            display: inline-block;
            height: 26px;
            line-height: 24px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 4px;
            padding: 0 10px;
            border: 1px solid #777777;
            background: #eeeeee;
            color: #000000;
            text-decoration: none;
            box-sizing: border-box;
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
            width: 430px;
            max-height: 220px;
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

        .print-bar {
            width: 285mm;
            margin: 8px auto;
            text-align: right;
        }

        .print-bar button {
            padding: 6px 14px;
            font-size: 11px;
            cursor: pointer;
            font-family: Arial, sans-serif;
        }

        .page {
            width: 285mm;
            min-height: 195mm;
            margin: 10px auto;
            background: #ffffff;
            border: 2px solid #000000;
            padding: 6mm;
            box-sizing: border-box;
            page-break-after: always;
            overflow: hidden;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-family: Arial, sans-serif;
        }

        .header td {
            border: none;
            vertical-align: top;
        }

        .company {
            width: 30%;
            font-size: 11px;
            line-height: 14px;
        }

        .company-title {
            font-size: 14px;
            font-weight: normal;
        }

        .title-area {
            width: 40%;
            text-align: center;
        }

        .report-title {
            font-size: 20px;
            font-weight: normal;
            margin-top: 4px;
        }

        .period-text {
            font-size: 10px;
            margin-top: 5px;
        }

        .right-info {
            width: 30%;
            text-align: right;
            font-size: 11px;
            line-height: 17px;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .report-table th,
        .report-table td {
            padding: 1px 2px;
            height: 14px;
            line-height: 10px;
            box-sizing: border-box;
            white-space: nowrap;
            overflow: hidden;
            font-size: 7px;
        }

        .report-table thead th {
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            font-weight: normal;
            text-align: center;
        }

        .report-table tbody td {
            border-bottom: 1px dotted #cccccc;
        }

        .col-no { width: 3%; text-align: right; }
        .col-code { width: 5%; text-align: left; }
        .col-cust { width: 16%; text-align: left; }
        .col-model { width: 8%; text-align: left; }
        .col-partname { width: 16%; text-align: left; }
        .col-partno { width: 13%; text-align: left; }
        .col-remarks { width: 8%; text-align: left; }
        .col-rev { width: 4%; text-align: right; }
        .col-quono { width: 9%; text-align: left; }
        .col-date { width: 7%; text-align: left; }
        .col-price { width: 6%; text-align: right; }
        .col-status { width: 5%; text-align: left; }

        .num { text-align: right; }
        .center { text-align: center; }

        .no-data {
            font-family: Arial, sans-serif;
            font-size: 14px;
            text-align: center;
            margin-top: 70px;
            line-height: 24px;
        }

        @media print {
            html, body {
                width: 297mm;
                min-height: 210mm;
                background: #ffffff;
            }

            .filter-bar,
            .print-bar {
                display: none;
            }

            .page {
                width: 285mm;
                min-height: 195mm;
                margin: 0 auto;
                border: none;
                padding: 4mm;
                overflow: hidden;
            }
        }
    </style>
</head>
<body>

<div class="filter-bar">
    <form method="get" action="quotation_report.php" autocomplete="off">
        <input type="hidden" name="RUN" value="1">

        Customer:
        <span class="autocomplete-box">
            <input type="text" id="CUST_CODE" name="CUST_CODE" value="<?php echo h($custCode); ?>" placeholder="% / kode customer" style="width:140px;">
            <div id="custSuggest" class="autocomplete-list"></div>
        </span>

        Search:
        <input type="text" name="SEARCH" value="<?php echo h($searchText); ?>" placeholder="Part / Quo No / Remark" style="width:220px;">

        <button type="submit">FILTER</button>
        <button type="button" onclick="setAllCustomer()">ALL</button>
        <button type="button" onclick="exportExcel()">EXPORT EXCEL</button>
    </form>
</div>

<div class="print-bar">
    <button type="button" onclick="window.print()">PRINT</button>
    <button type="button" onclick="closeReport()">CLOSE</button>
</div>

<?php if (!$run) { ?>
    <div class="page">
        <table class="header">
            <tr>
                <td class="company">
                    <div class="company-title">P.T. IMC TEKNO INDONESIA</div>
                    PPIC Department
                </td>
                <td class="title-area">
                    <div class="report-title">MASTER LIST QUOTATION</div>
                    <div class="period-text">Customer: -</div>
                </td>
                <td class="right-info">
                    Page 0 of 0<br>
                    Print Date: <?php echo h(print_datetime()); ?>
                </td>
            </tr>
        </table>

        <div class="no-data">
            Data belum ditampilkan.<br><br>
            Pilih Customer / Search lalu klik <b>FILTER</b>.<br>
            Isi Customer <b>%</b> untuk semua customer.
        </div>
    </div>
<?php } ?>

<?php for ($p = 0; $p < count($pages); $p++) { ?>
    <?php $pageRows = $pages[$p]; $pageNo = $p + 1; ?>

    <div class="page">
        <table class="header">
            <tr>
                <td class="company">
                    <div class="company-title">P.T. IMC TEKNO INDONESIA</div>
                    PPIC Department
                </td>
                <td class="title-area">
                    <div class="report-title">MASTER LIST QUOTATION</div>
                    <div class="period-text">
                        Customer: <?php echo h($custCode == "%" ? "ALL" : $custCode); ?>
                        &nbsp;&nbsp; Search: <?php echo h($searchText == "" ? "-" : $searchText); ?>
                    </div>
                </td>
                <td class="right-info">
                    Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?><br>
                    Print Date: <?php echo h(print_datetime()); ?>
                </td>
            </tr>
        </table>

        <table class="report-table">
            <thead>
                <tr>
                    <th class="col-no">NO</th>
                    <th class="col-code">CODE</th>
                    <th class="col-cust">CUSTOMER</th>
                    <th class="col-model">MODEL</th>
                    <th class="col-partname">PART_NAME</th>
                    <th class="col-partno">PART_NO</th>
                    <th class="col-remarks">REMARKS</th>
                    <th class="col-rev">REV</th>
                    <th class="col-quono">QUO NO</th>
                    <th class="col-date">QUO DATE</th>
                    <th class="col-price">MOLD</th>
                    <th class="col-price">PART</th>
                    <th class="col-status">STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < count($pageRows); $i++) { $r = $pageRows[$i]; ?>
                    <?php if (isset($r["ROW_TYPE"]) && $r["ROW_TYPE"] == "EMPTY") { ?>
                        <tr>
                            <td colspan="13" class="center"><?php echo h($r["MESSAGE"]); ?></td>
                        </tr>
                    <?php } else { ?>
                        <tr>
                            <td class="col-no num"><?php echo h($r["NO"]); ?></td>
                            <td class="col-code"><?php echo h($r["CUST_CODE"]); ?></td>
                            <td class="col-cust"><?php echo h($r["CUST_COMP"]); ?></td>
                            <td class="col-model"><?php echo h($r["MODEL"]); ?></td>
                            <td class="col-partname"><?php echo h($r["PART_NAME"]); ?></td>
                            <td class="col-partno"><?php echo h($r["PART_NO"]); ?></td>
                            <td class="col-remarks"><?php echo h($r["REMARKS"]); ?></td>
                            <td class="col-rev num"><?php echo h($r["REVISI_NO"]); ?></td>
                            <td class="col-quono"><?php echo h($r["QUO_NO"]); ?></td>
                            <td class="col-date"><?php echo h(date_display($r["QUO_DATE"])); ?></td>
                            <td class="col-price num"><?php echo h(fmt_num($r["PRICE_MOLD"], 2)); ?></td>
                            <td class="col-price num"><?php echo h(fmt_num($r["PRICE_PART"], 2)); ?></td>
                            <td class="col-status"><?php echo h($r["STATUS_PO"]); ?></td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>

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

function closeReport() {
    window.open("", "_self");
    window.close();
    setTimeout(function() {
        if (!window.closed) {
            window.location.href = "quotation.php";
        }
    }, 200);
}

function setAllCustomer() {
    document.getElementById("CUST_CODE").value = "%";
}

function exportExcel() {
    var cust = document.getElementById("CUST_CODE").value;
    var search = document.querySelector("input[name='SEARCH']").value;

    if (cust == "") {
        cust = "%";
    }

    window.location = "quotation_report.php?RUN=1&EXPORT=1&CUST_CODE=" + enc(cust) + "&SEARCH=" + enc(search);
}

var rows = [];
var activeIndex = -1;
var timer = null;

function hideSuggest() {
    var box = document.getElementById("custSuggest");
    box.style.display = "none";
    box.innerHTML = "";
    rows = [];
    activeIndex = -1;
}

function setActive(index) {
    var box = document.getElementById("custSuggest");
    var items = box.getElementsByClassName("autocomplete-item");
    if (!items || items.length == 0) {
        activeIndex = -1;
        return;
    }

    if (index < 0) {
        index = items.length - 1;
    }
    if (index >= items.length) {
        index = 0;
    }

    for (var i = 0; i < items.length; i++) {
        items[i].className = "autocomplete-item";
    }

    items[index].className = "autocomplete-item active";
    activeIndex = index;
}

function choose(index) {
    if (index < 0 || index >= rows.length) {
        return;
    }

    document.getElementById("CUST_CODE").value = rows[index].CUST_CODE;
    hideSuggest();
}

function renderSuggest(dataRows) {
    var box = document.getElementById("custSuggest");
    box.innerHTML = "";
    rows = dataRows || [];
    activeIndex = -1;

    if (rows.length == 0) {
        hideSuggest();
        return;
    }

    for (var i = 0; i < rows.length; i++) {
        (function(r, idx) {
            var div = document.createElement("div");
            div.className = "autocomplete-item";
            div.innerHTML = "<b>" + htmlEncode(r.CUST_CODE) + "</b> - " + htmlEncode(r.CUST_COMP);
            div.onmouseover = function() { setActive(idx); };
            div.onmousedown = function(e) {
                if (e && e.preventDefault) {
                    e.preventDefault();
                }
                choose(idx);
            };
            box.appendChild(div);
        })(rows[i], i);
    }

    box.style.display = "block";
    setActive(0);
}

function searchCustomer(q) {
    if (q == "" || q == "%") {
        hideSuggest();
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open("GET", "quotation_report.php?ajax=customer&q=" + enc(q), true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            var result;
            try {
                result = JSON.parse(xhr.responseText);
            } catch (e) {
                hideSuggest();
                return;
            }

            if (result && result.rows) {
                renderSuggest(result.rows);
            } else {
                hideSuggest();
            }
        }
    };
    xhr.send(null);
}

var custInput = document.getElementById("CUST_CODE");

custInput.onkeyup = function(e) {
    e = e || window.event;
    var key = e.keyCode || e.which;

    if (key == 40) {
        setActive(activeIndex + 1);
        return false;
    }
    if (key == 38) {
        setActive(activeIndex - 1);
        return false;
    }
    if (key == 13) {
        if (rows.length > 0) {
            if (activeIndex < 0) {
                activeIndex = 0;
            }
            choose(activeIndex);
            return false;
        }
        return true;
    }

    clearTimeout(timer);
    var q = this.value;
    timer = setTimeout(function() {
        searchCustomer(q);
    }, 250);
};

custInput.onblur = function() {
    setTimeout(function() {
        hideSuggest();
    }, 250);
};
</script>

</body>
</html>

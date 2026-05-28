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
    if (isset($_GET[$name])) {
        return trim($_GET[$name]);
    }
    if (isset($_POST[$name])) {
        return trim($_POST[$name]);
    }
    return $default;
}

function fmt_num($value, $decimal = 0) {
    if ($value === null || $value === "") {
        $value = 0;
    }
    return number_format((float)$value, $decimal, ".", ",");
}

function fmt_zero_dash($value, $decimal = 0) {
    if ($value === null || $value === "") {
        return "-";
    }

    $n = (float)$value;
    if ($n == 0) {
        return "-";
    }

    return number_format($n, $decimal, ".", ",");
}

function normalize_start_date($value) {
    $value = trim($value);

    if ($value == "") {
        return date("Y-m-01");
    }

    if (preg_match('/^\d{4}-\d{2}$/', $value)) {
        return $value . "-01";
    }

    if (preg_match('/^\d{8}$/', $value)) {
        return substr($value, 0, 4) . "-" . substr($value, 4, 2) . "-" . substr($value, 6, 2);
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return date("Y-m-01");
    }

    return date("Y-m-d", $ts);
}

function month_input_value($dateValue) {
    $dateValue = normalize_start_date($dateValue);
    $ts = strtotime($dateValue);
    if ($ts === false) {
        return date("Y-m");
    }
    return date("Y-m", $ts);
}

function display_start($dateValue) {
    $dateValue = normalize_start_date($dateValue);
    $ts = strtotime($dateValue);
    if ($ts === false) {
        return $dateValue;
    }
    return date("d-M-Y", $ts);
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
        SELECT TOP 25
            ISNULL(CUST_CODE, '') AS CUST_CODE,
            ISNULL(CUST_ABBR, '') AS CUST_ABBR,
            ISNULL(CUST_COMP, '') AS CUST_COMP
        FROM dbo.CUST
        WHERE
            (? = ''
             OR ISNULL(CUST_CODE, '') LIKE ?
             OR ISNULL(CUST_ABBR, '') LIKE ?
             OR ISNULL(CUST_COMP, '') LIKE ?)
        ORDER BY CUST_CODE
        ",
        array($q, $like, $like, $like)
    );

    $out = array();
    for ($i = 0; $i < count($rows); $i++) {
        $out[] = array(
            "CUST_CODE" => safe_trim($rows[$i]["CUST_CODE"]),
            "CUST_ABBR" => safe_trim($rows[$i]["CUST_ABBR"]),
            "CUST_COMP" => safe_trim($rows[$i]["CUST_COMP"])
        );
    }

    json_out(array("success" => true, "rows" => $out));
}

// ==========================================================
// PARAMETER
// ==========================================================
$run = get_param("RUN", "") == "1";
$reportType = strtolower(get_param("TYPE", "month"));

if ($reportType != "month" && $reportType != "year") {
    $reportType = "month";
}

$startDate = normalize_start_date(get_param("START_DATE", date("Y-m-01")));
$custCode = get_param("CUST_CODE", "%");

if ($custCode == "") {
    $custCode = "%";
}

$procedureName = $reportType == "year" ? "dbo.depresiasi12year" : "dbo.depresiasi12month";
$reportTitle = $reportType == "year" ? "DEPRESIASI 12 YEAR" : "DEPRESIASI 12 MONTH";
$subTitle = $reportType == "year" ? "Starting Year" : "Starting Month";

$periodHeaders = array();
$tsStart = strtotime($startDate);

for ($i = 0; $i < 12; $i++) {
    if ($reportType == "year") {
        $periodHeaders[$i + 1] = date("Y", strtotime("+" . $i . " year", $tsStart));
    } else {
        $periodHeaders[$i + 1] = strtoupper(date("M-y", strtotime("+" . $i . " month", $tsStart)));
    }
}

// ==========================================================
// LOAD REPORT DATA
// ==========================================================
$detailRows = array();
$printRows = array();
$pages = array();
$rowsPerPage = 33;
$totalPages = 0;

if ($run) {
    $sql = "
        SET NOCOUNT ON;
        EXEC " . $procedureName . " ?, ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array($startDate, $custCode));

    if ($stmt === false) {
        die("<pre>Query report depresiasi gagal:\n" . h(sql_error_text()) . "</pre>");
    }

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $row = array();
        $row["CUST_CODE"] = safe_trim($r["CUST_CODE"]);
        $row["CUST_COMP"] = safe_trim($r["CUST_COMP"]);
        $row["PART_NUM"] = safe_trim($r["PART_NUM"]);
        $row["PART_NAME"] = safe_trim($r["PART_NAME"]);
        $row["PRICE_ID"] = isset($r["PRICE_ID"]) ? intval($r["PRICE_ID"]) : 0;
        $row["DEP_QTY"] = isset($r["DEP_QTY"]) ? (float)$r["DEP_QTY"] : 0;
        $row["DEL_QTY"] = isset($r["DEL_QTY"]) ? (float)$r["DEL_QTY"] : 0;
        $row["DIFF_QTY"] = isset($r["DIFF_QTY"]) ? (float)$r["DIFF_QTY"] : 0;

        $totalByMonth = 0;
        for ($m = 1; $m <= 12; $m++) {
            $qty = isset($r["DQTY" . $m]) ? (float)$r["DQTY" . $m] : 0;
            $row["DQTY" . $m] = $qty;
            $totalByMonth += $qty;
        }

        if ($row["DEL_QTY"] == 0 && $totalByMonth != 0) {
            $row["DEL_QTY"] = $totalByMonth;
            $row["DIFF_QTY"] = $row["DEP_QTY"] - $row["DEL_QTY"];
        }

        $detailRows[] = $row;
    }

    $lastCust = "";

    $custDep = 0;
    $custDel = 0;
    $custDiff = 0;
    $custMonth = array();

    $grandDep = 0;
    $grandDel = 0;
    $grandDiff = 0;
    $grandMonth = array();

    for ($m = 1; $m <= 12; $m++) {
        $custMonth[$m] = 0;
        $grandMonth[$m] = 0;
    }

    for ($i = 0; $i < count($detailRows); $i++) {
        $r = $detailRows[$i];
        $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

        if ($custKey != $lastCust) {
            if ($lastCust != "") {
                $totalRow = array(
                    "ROW_TYPE" => "CUSTOMER_TOTAL",
                    "DEP_QTY" => $custDep,
                    "DEL_QTY" => $custDel,
                    "DIFF_QTY" => $custDiff
                );
                for ($m = 1; $m <= 12; $m++) {
                    $totalRow["DQTY" . $m] = $custMonth[$m];
                }
                $printRows[] = $totalRow;
            }

            $printRows[] = array(
                "ROW_TYPE" => "CUSTOMER",
                "CUST_CODE" => $r["CUST_CODE"],
                "CUST_COMP" => $r["CUST_COMP"]
            );

            $lastCust = $custKey;
            $custDep = 0;
            $custDel = 0;
            $custDiff = 0;
            for ($m = 1; $m <= 12; $m++) {
                $custMonth[$m] = 0;
            }
        }

        $detailRow = array_merge(array("ROW_TYPE" => "DETAIL"), $r);
        $printRows[] = $detailRow;

        $custDep += $r["DEP_QTY"];
        $custDel += $r["DEL_QTY"];
        $custDiff += $r["DIFF_QTY"];

        $grandDep += $r["DEP_QTY"];
        $grandDel += $r["DEL_QTY"];
        $grandDiff += $r["DIFF_QTY"];

        for ($m = 1; $m <= 12; $m++) {
            $custMonth[$m] += $r["DQTY" . $m];
            $grandMonth[$m] += $r["DQTY" . $m];
        }
    }

    if ($lastCust != "") {
        $totalRow = array(
            "ROW_TYPE" => "CUSTOMER_TOTAL",
            "DEP_QTY" => $custDep,
            "DEL_QTY" => $custDel,
            "DIFF_QTY" => $custDiff
        );
        for ($m = 1; $m <= 12; $m++) {
            $totalRow["DQTY" . $m] = $custMonth[$m];
        }
        $printRows[] = $totalRow;
    }

    if (count($detailRows) > 0) {
        $grandRow = array(
            "ROW_TYPE" => "GRAND_TOTAL",
            "DEP_QTY" => $grandDep,
            "DEL_QTY" => $grandDel,
            "DIFF_QTY" => $grandDiff
        );
        for ($m = 1; $m <= 12; $m++) {
            $grandRow["DQTY" . $m] = $grandMonth[$m];
        }
        $printRows[] = $grandRow;
    }

    if (count($printRows) == 0) {
        $printRows[] = array("ROW_TYPE" => "EMPTY", "MESSAGE" => "Data depresiasi tidak ditemukan.");
    }

    $pages = array_chunk($printRows, $rowsPerPage);
    $totalPages = count($pages);
    if ($totalPages <= 0) {
        $totalPages = 1;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo h($reportTitle); ?></title>
    <style>
        @page {
            size: A4 landscape;
            margin: 5mm;
        }

        body {
            margin: 0;
            background: #9a9a9a;
            font-family: "Courier New", monospace;
            font-size: 8px;
            color: #000000;
        }

        .filter-bar {
            width: 287mm;
            margin: 8px auto;
            background: #d4d0c8;
            border: 1px solid #666666;
            padding: 6px;
            box-sizing: border-box;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
        }

        .filter-bar input,
        .filter-bar select {
            height: 24px;
            border: 1px solid #777777;
            font-size: 12px;
            padding: 2px 4px;
            box-sizing: border-box;
        }

        .filter-bar button {
            height: 26px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 4px;
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
            width: 460px;
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

        .autocomplete-sub {
            font-size: 11px;
            color: #555555;
        }

        .autocomplete-item:hover .autocomplete-sub,
        .autocomplete-item.active .autocomplete-sub {
            color: #ffffff;
        }

        .print-bar {
            width: 287mm;
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
            width: 287mm;
            min-height: 200mm;
            margin: 10px auto;
            background: #ffffff;
            border: 2px solid #000000;
            padding: 5mm;
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
            width: 28%;
            font-size: 11px;
            line-height: 14px;
        }

        .company-title {
            font-size: 14px;
            font-weight: normal;
        }

        .title-area {
            width: 44%;
            text-align: center;
        }

        .report-title {
            font-size: 20px;
            font-weight: normal;
            margin-top: 4px;
        }

        .period-text {
            font-size: 11px;
            margin-top: 4px;
        }

        .right-info {
            width: 28%;
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
            font-size: 7px;
        }

        .report-table tbody td {
            border-bottom: 1px dotted #cccccc;
        }

        .col-code {
            width: 9%;
            text-align: left;
        }

        .col-name {
            width: 22%;
            text-align: left;
        }

        .col-dep {
            width: 5%;
            text-align: right;
        }

        .col-month {
            width: 4.5%;
            text-align: right;
        }

        .col-total {
            width: 5%;
            text-align: right;
        }

        .customer-row td {
            background: #bfbfbf;
            font-weight: bold;
            font-size: 8px;
            border-bottom: 1px solid #999999;
        }

        .customer-total-row td {
            font-weight: bold;
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
        }

        .grand-total-row td {
            font-weight: bold;
            border-top: 2px solid #000000;
            border-bottom: 2px solid #000000;
        }

        .num {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .negative {
            color: #000000;
        }

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
                width: 287mm;
                min-height: 200mm;
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
    <form method="get" action="depresiasi_report.php" autocomplete="off">
        <input type="hidden" name="RUN" value="1">

        Type:
        <select name="TYPE">
            <option value="month"<?php echo $reportType == "month" ? " selected" : ""; ?>>12 Month</option>
            <option value="year"<?php echo $reportType == "year" ? " selected" : ""; ?>>12 Year</option>
        </select>

        Start:
        <input type="month" name="START_DATE" value="<?php echo h(month_input_value($startDate)); ?>">

        Customer:
        <span class="autocomplete-box">
            <input type="text" id="CUST_CODE" name="CUST_CODE" value="<?php echo h($custCode); ?>" placeholder="% / kode customer" style="width:140px;">
            <div id="custSuggest" class="autocomplete-list"></div>
        </span>

        <button type="submit">FILTER</button>
        <button type="button" onclick="setAllCustomer()">ALL</button>
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
                    <div class="report-title"><?php echo h($reportTitle); ?></div>
                    <div class="period-text"><?php echo h($subTitle); ?>: -</div>
                </td>
                <td class="right-info">
                    Page 0 of 0<br>
                    Print Date: <?php echo h(print_datetime()); ?>
                </td>
            </tr>
        </table>

        <div class="no-data">
            Data belum ditampilkan.<br><br>
            Pilih Type, Start, Customer lalu klik <b>FILTER</b>.<br>
            Isi Customer <b>%</b> untuk semua customer.
        </div>
    </div>
<?php } ?>

<?php for ($p = 0; $p < count($pages); $p++) { ?>
    <?php
        $pageRows = $pages[$p];
        $pageNo = $p + 1;
    ?>

    <div class="page">
        <table class="header">
            <tr>
                <td class="company">
                    <div class="company-title">P.T. IMC TEKNO INDONESIA</div>
                    PPIC Department
                </td>
                <td class="title-area">
                    <div class="report-title"><?php echo h($reportTitle); ?></div>
                    <div class="period-text">
                        <?php echo h($subTitle); ?>: <?php echo h(display_start($startDate)); ?>
                        &nbsp;&nbsp; Customer: <?php echo h($custCode == "%" ? "ALL" : $custCode); ?>
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
                    <th class="col-code">ITEM CODE</th>
                    <th class="col-name">ITEM NAME</th>
                    <th class="col-dep">DEP</th>
                    <?php for ($m = 1; $m <= 12; $m++) { ?>
                        <th class="col-month"><?php echo h($periodHeaders[$m]); ?></th>
                    <?php } ?>
                    <th class="col-total">DEL</th>
                    <th class="col-total">DIFF</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < count($pageRows); $i++) { ?>
                    <?php $r = $pageRows[$i]; ?>

                    <?php if ($r["ROW_TYPE"] == "CUSTOMER") { ?>
                        <tr class="customer-row">
                            <td colspan="17">
                                <?php echo h($r["CUST_CODE"]); ?>
                                &nbsp;&nbsp;
                                <?php echo h($r["CUST_COMP"]); ?>
                            </td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "DETAIL") { ?>
                        <tr>
                            <td class="col-code"><?php echo h($r["PART_NUM"]); ?></td>
                            <td class="col-name"><?php echo h($r["PART_NAME"]); ?></td>
                            <td class="col-dep num"><?php echo h(fmt_zero_dash($r["DEP_QTY"], 0)); ?></td>
                            <?php for ($m = 1; $m <= 12; $m++) { ?>
                                <td class="col-month num"><?php echo h(fmt_zero_dash($r["DQTY" . $m], 0)); ?></td>
                            <?php } ?>
                            <td class="col-total num"><?php echo h(fmt_zero_dash($r["DEL_QTY"], 0)); ?></td>
                            <td class="col-total num"><?php echo h(fmt_zero_dash($r["DIFF_QTY"], 0)); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "CUSTOMER_TOTAL") { ?>
                        <tr class="customer-total-row">
                            <td class="col-code" colspan="2">TOTAL CUSTOMER</td>
                            <td class="col-dep num"><?php echo h(fmt_num($r["DEP_QTY"], 0)); ?></td>
                            <?php for ($m = 1; $m <= 12; $m++) { ?>
                                <td class="col-month num"><?php echo h(fmt_num($r["DQTY" . $m], 0)); ?></td>
                            <?php } ?>
                            <td class="col-total num"><?php echo h(fmt_num($r["DEL_QTY"], 0)); ?></td>
                            <td class="col-total num"><?php echo h(fmt_num($r["DIFF_QTY"], 0)); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "GRAND_TOTAL") { ?>
                        <tr class="grand-total-row">
                            <td class="col-code" colspan="2">GRAND TOTAL</td>
                            <td class="col-dep num"><?php echo h(fmt_num($r["DEP_QTY"], 0)); ?></td>
                            <?php for ($m = 1; $m <= 12; $m++) { ?>
                                <td class="col-month num"><?php echo h(fmt_num($r["DQTY" . $m], 0)); ?></td>
                            <?php } ?>
                            <td class="col-total num"><?php echo h(fmt_num($r["DEL_QTY"], 0)); ?></td>
                            <td class="col-total num"><?php echo h(fmt_num($r["DIFF_QTY"], 0)); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
                        <tr>
                            <td colspan="17" class="center"><?php echo h($r["MESSAGE"]); ?></td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<script>
function enc(v) {
    return encodeURIComponent(v == null ? "" : v);
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
            window.location.href = "depresiasi.php";
        }
    }, 200);
}

function setAllCustomer() {
    document.getElementById("CUST_CODE").value = "%";
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
            div.innerHTML = "<b>" + htmlEncode(r.CUST_CODE) + "</b> - " + htmlEncode(r.CUST_COMP) +
                            "<div class='autocomplete-sub'>ABBR: " + htmlEncode(r.CUST_ABBR) + "</div>";
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
    xhr.open("GET", "depresiasi_report.php?ajax=customer&q=" + enc(q), true);
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

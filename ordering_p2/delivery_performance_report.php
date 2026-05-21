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

function ymd_param($value) {
    $value = trim($value);

    if ($value == "") {
        return "";
    }

    if (preg_match('/^\d{8}$/', $value)) {
        return $value;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return str_replace("-", "", $value);
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return "";
    }

    return date("Ymd", $ts);
}

function date_input_value($value, $default) {
    $value = trim($value);

    if ($value == "") {
        return $default;
    }

    if (preg_match('/^\d{8}$/', $value)) {
        return substr($value, 0, 4) . "-" . substr($value, 4, 2) . "-" . substr($value, 6, 2);
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return $default;
    }

    return date("Y-m-d", $ts);
}

function fmt_print_datetime() {
    return date("d-M-Y H:i:s");
}

function fmt_num($value, $decimal = 0) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, $decimal, ".", ",");
}

function fmt_bal($value) {
    $n = (float)$value;

    if ($n == 0) {
        return "-";
    }

    return number_format($n, 0, ".", ",");
}

function fmt_short_count_detail($value) {
    $n = (float)$value;

    if ($n == 0) {
        return "-";
    }

    return number_format($n, 0, ".", ",");
}

function fmt_percent_detail($short, $sch) {
    $short = (float)$short;
    $sch   = (float)$sch;

    if ($short == 0 || $sch == 0) {
        return "- %";
    }

    $pct = ($short / $sch) * 100;

    return number_format($pct, 2, ",", ".") . "%";
}

function fmt_percent_total($short, $sch) {
    $short = (float)$short;
    $sch   = (float)$sch;

    if ($sch == 0) {
        return "0,00%";
    }

    $pct = ($short / $sch) * 100;

    return number_format($pct, 2, ",", ".") . "%";
}

function score_value($value, $bal) {
    if ($value !== null && $value !== "") {
        return (float)$value;
    }

    if ((float)$bal < 0) {
        return 1;
    }

    return 0;
}

$is_filter = get_param("RUN", "") == "1";

$defaultStart = date("Y-m-01");
$defaultEnd   = date("Y-m-d");

$start_input = date_input_value(get_param("START_DATE", ""), $defaultStart);
$end_input   = date_input_value(get_param("END_DATE", ""), $defaultEnd);
$cust_code   = get_param("CUST_CODE", "");

if ($is_filter && $cust_code == "") {
    $cust_code = "%";
}

$start_ymd = ymd_param($start_input);
$end_ymd   = ymd_param($end_input);

$summaryMap = array();
$summaryRows = array();
$custTotals = array();
$printRows = array();
$pages = array();

$totalPages = 0;
$rowsPerPage = 42;

if ($is_filter) {
    if ($start_ymd == "" || $end_ymd == "") {
        die("Tanggal tidak valid.");
    }

    if ($cust_code == "") {
        $cust_code = "%";
    }

    $sql = "
        SET NOCOUNT ON;
        EXEC dbo.SP_DELIVERY_PERFORMANCE1 ?, ?, ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array(
        $start_ymd,
        $end_ymd,
        $cust_code
    ));

    if ($stmt === false) {
        die("<pre>Query Delivery Performance gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $custCode = safe_trim($r["CUST_CODE"]);
        $custComp = safe_trim($r["CUST_COMP"]);
        $itemCode = safe_trim($r["ITEM_CODE"]);
        $itemName = safe_trim($r["ITEM_NAME"]);

        $dsQty = isset($r["DS_QTY"]) ? (float)$r["DS_QTY"] : 0;
        $diQty = isset($r["DI_QTY"]) ? (float)$r["DI_QTY"] : 0;
        $bal   = isset($r["BAL"]) ? (float)$r["BAL"] : ($diQty - $dsQty);
        $score = score_value(isset($r["SCORE"]) ? $r["SCORE"] : null, $bal);

        $key = $custCode . "|" . $custComp . "|" . $itemCode . "|" . $itemName;

        if (!isset($summaryMap[$key])) {
            $summaryMap[$key] = array(
                "CUST_CODE" => $custCode,
                "CUST_COMP" => $custComp,
                "ITEM_CODE" => $itemCode,
                "ITEM_NAME" => $itemName,
                "DEL_SCH"   => 0,
                "DEL_ACT"   => 0,
                "BAL_QTY"   => 0,
                "CNT_SCH"   => 0,
                "CNT_ACT"   => 0,
                "SHORT_CNT" => 0
            );
        }

        $summaryMap[$key]["DEL_SCH"] += $dsQty;
        $summaryMap[$key]["DEL_ACT"] += $diQty;
        $summaryMap[$key]["BAL_QTY"] += $bal;

        if ($dsQty != 0) {
            $summaryMap[$key]["CNT_SCH"] += 1;
        }

        if ($diQty != 0) {
            $summaryMap[$key]["CNT_ACT"] += 1;
        }

        $summaryMap[$key]["SHORT_CNT"] += $score;
    }

    foreach ($summaryMap as $row) {
        $summaryRows[] = $row;
    }

    for ($i = 0; $i < count($summaryRows); $i++) {
        $r = $summaryRows[$i];
        $ck = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

        if (!isset($custTotals[$ck])) {
            $custTotals[$ck] = array(
                "DEL_SCH"   => 0,
                "DEL_ACT"   => 0,
                "BAL_QTY"   => 0,
                "CNT_SCH"   => 0,
                "CNT_ACT"   => 0,
                "SHORT_CNT" => 0
            );
        }

        $custTotals[$ck]["DEL_SCH"] += $r["DEL_SCH"];
        $custTotals[$ck]["DEL_ACT"] += $r["DEL_ACT"];
        $custTotals[$ck]["BAL_QTY"] += $r["BAL_QTY"];
        $custTotals[$ck]["CNT_SCH"] += $r["CNT_SCH"];
        $custTotals[$ck]["CNT_ACT"] += $r["CNT_ACT"];
        $custTotals[$ck]["SHORT_CNT"] += $r["SHORT_CNT"];
    }

    $lastCust = "";

    for ($i = 0; $i < count($summaryRows); $i++) {
        $r = $summaryRows[$i];
        $ck = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

        if ($ck != $lastCust) {
            $t = $custTotals[$ck];

            $printRows[] = array(
                "ROW_TYPE"  => "CUSTOMER",
                "CUST_CODE" => $r["CUST_CODE"],
                "CUST_COMP" => $r["CUST_COMP"],
                "DEL_SCH"   => $t["DEL_SCH"],
                "DEL_ACT"   => $t["DEL_ACT"],
                "BAL_QTY"   => $t["BAL_QTY"],
                "CNT_SCH"   => $t["CNT_SCH"],
                "CNT_ACT"   => $t["CNT_ACT"],
                "SHORT_CNT" => $t["SHORT_CNT"]
            );

            $lastCust = $ck;
        }

        $printRows[] = array(
            "ROW_TYPE"  => "DETAIL",
            "ITEM_CODE" => $r["ITEM_CODE"],
            "ITEM_NAME" => $r["ITEM_NAME"],
            "DEL_SCH"   => $r["DEL_SCH"],
            "DEL_ACT"   => $r["DEL_ACT"],
            "BAL_QTY"   => $r["BAL_QTY"],
            "CNT_SCH"   => $r["CNT_SCH"],
            "CNT_ACT"   => $r["CNT_ACT"],
            "SHORT_CNT" => $r["SHORT_CNT"]
        );
    }

    if (count($printRows) == 0) {
        $printRows[] = array(
            "ROW_TYPE" => "EMPTY",
            "MESSAGE"  => "Data delivery performance tidak ditemukan."
        );
    }

    $pages = array_chunk($printRows, $rowsPerPage);
    $totalPages = count($pages);

    if ($totalPages <= 0) {
        $totalPages = 1;
    }
}

$selfFile = basename($_SERVER["PHP_SELF"]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Performance</title>

    <style>
        @page {
            size: A4 landscape;
            margin: 6mm;
        }

        body {
            margin: 0;
            background: #9a9a9a;
            font-family: "Courier New", monospace;
            font-size: 10px;
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

        .filter-date {
            width: 130px;
        }

        .filter-cust {
            width: 160px;
        }

        .filter-bar button {
            height: 26px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 4px;
        }

        .autocomplete-wrap {
            position: relative;
            display: inline-block;
        }

        .autocomplete-list {
            position: absolute;
            top: 24px;
            left: 0;
            width: 430px;
            max-height: 230px;
            overflow-y: auto;
            background: #ffffff;
            border: 1px solid #444444;
            z-index: 9999;
            display: none;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.25);
        }

        .autocomplete-item {
            padding: 5px 7px;
            border-bottom: 1px solid #dddddd;
            cursor: pointer;
            line-height: 16px;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
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
            min-height: 198mm;
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
        }

        .header td {
            border: none;
            vertical-align: top;
        }

        .company {
            width: 30%;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 14px;
        }

        .company-title {
            font-size: 15px;
            font-weight: normal;
        }

        .title-area {
            width: 40%;
            text-align: center;
            font-family: Arial, sans-serif;
        }

        .report-title {
            font-size: 20px;
            font-weight: normal;
            margin-top: 6px;
        }

        .to-text {
            font-size: 10px;
            margin-top: 4px;
        }

        .right-info {
            width: 30%;
            text-align: right;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 18px;
        }

        .performance-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .performance-table th,
        .performance-table td {
            border: none;
            padding: 2px 3px;
            height: 16px;
            line-height: 12px;
            box-sizing: border-box;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            font-size: 10px;
        }

        .performance-table thead th {
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            font-weight: normal;
            text-align: center;
            font-size: 11px;
        }

        .col-item {
            width: 36%;
            text-align: left !important;
            border-right: 1px dashed #000000 !important;
        }

        .col-sum {
            width: 11%;
            text-align: right;
        }

        .col-count {
            width: 7%;
            text-align: right;
        }

        .col-percent {
            width: 10%;
            text-align: right;
        }

        .count-border {
            border-left: 1px dashed #000000 !important;
        }

        .customer-row td {
            background: #bfbfbf;
            font-weight: bold;
            font-size: 11px;
        }

        .num {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .no-data {
            font-family: Arial, sans-serif;
            font-size: 14px;
            text-align: center;
            margin-top: 70px;
            line-height: 24px;
        }

        @media print {
            html,
            body {
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
                min-height: 198mm;
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
    <form method="get" action="<?php echo h($selfFile); ?>" autocomplete="off">
        <input type="hidden" name="RUN" value="1">

        Start:
        <input type="date"
               id="START_DATE"
               name="START_DATE"
               class="filter-date"
               value="<?php echo h($start_input); ?>">

        End:
        <input type="date"
               id="END_DATE"
               name="END_DATE"
               class="filter-date"
               value="<?php echo h($end_input); ?>">

        Customer:
        <div class="autocomplete-wrap">
            <input type="text"
                   id="CUST_CODE"
                   name="CUST_CODE"
                   class="filter-cust"
                   value="<?php echo h($cust_code); ?>"
                   placeholder="Ketik customer / %">
            <div id="custSuggest" class="autocomplete-list"></div>
        </div>

        <button type="submit">FILTER</button>
        <button type="button" onclick="setAllCustomer()">ALL</button>
        <button type="button" onclick="exportExcel()">EXPORT EXCEL</button>
    </form>
</div>

<div class="print-bar">
    <button type="button" onclick="window.print()">PRINT</button>
    <button type="button" onclick="closeReport()">CLOSE</button>
</div>

<?php if (!$is_filter) { ?>
    <div class="page">
        <table class="header">
            <tr>
                <td class="company">
                    <div class="company-title">P.T. IMC TEKNO INDONESIA</div>
                    PPIC Department
                </td>

                <td class="title-area">
                    <div class="report-title">DELIVERY PERFORMANCE</div>
                    <div class="to-text">to</div>
                </td>

                <td class="right-info">
                    Print Date: <?php echo h(fmt_print_datetime()); ?>
                </td>
            </tr>
        </table>

        <div class="no-data">
            Data belum ditampilkan.<br><br>
            Isi Start, End, Customer lalu klik <b>FILTER</b>.<br>
            Klik <b>ALL</b> untuk semua customer.
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
                    <div class="report-title">DELIVERY PERFORMANCE</div>
                    <div class="to-text">
                        <?php echo h($start_input); ?> to <?php echo h($end_input); ?>
                    </div>
                </td>

                <td class="right-info">
                    Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?><br>
                    Print Date: <?php echo h(fmt_print_datetime()); ?>
                </td>
            </tr>
        </table>

        <table class="performance-table">
            <thead>
                <tr>
                    <th rowspan="2" class="col-item">COMPANY / I T E M S</th>
                    <th colspan="3">Sum</th>
                    <th colspan="4" class="count-border">Count (*)</th>
                </tr>
                <tr>
                    <th class="col-sum">Del.Sch</th>
                    <th class="col-sum">Del.Act</th>
                    <th class="col-sum">Bal.Qty</th>
                    <th class="col-count count-border">Del.Sch</th>
                    <th class="col-count">Del.Act</th>
                    <th class="col-count">Short.</th>
                    <th class="col-percent">Short%</th>
                </tr>
            </thead>

            <tbody>
                <?php for ($i = 0; $i < count($pageRows); $i++) { ?>
                    <?php $r = $pageRows[$i]; ?>

                    <?php if ($r["ROW_TYPE"] == "CUSTOMER") { ?>
                        <tr class="customer-row">
                            <td class="col-item">
                                <?php echo h($r["CUST_CODE"]); ?>
                                <?php echo h($r["CUST_COMP"]); ?>
                            </td>
                            <td class="col-sum num"><?php echo h(fmt_num($r["DEL_SCH"], 0)); ?></td>
                            <td class="col-sum num"><?php echo h(fmt_num($r["DEL_ACT"], 0)); ?></td>
                            <td class="col-sum num"><?php echo h(fmt_bal($r["BAL_QTY"])); ?></td>
                            <td class="col-count count-border num"><?php echo h(fmt_num($r["CNT_SCH"], 0)); ?></td>
                            <td class="col-count num"><?php echo h(fmt_num($r["CNT_ACT"], 0)); ?></td>
                            <td class="col-count num"><?php echo h(fmt_num($r["SHORT_CNT"], 0)); ?></td>
                            <td class="col-percent num"><?php echo h(fmt_percent_total($r["SHORT_CNT"], $r["CNT_SCH"])); ?></td>
                        </tr>

                    <?php } elseif ($r["ROW_TYPE"] == "DETAIL") { ?>
                        <tr>
                            <td class="col-item">
                                <?php echo h($r["ITEM_CODE"]); ?>
                                <?php echo h($r["ITEM_NAME"]); ?>
                            </td>
                            <td class="col-sum num"><?php echo h(fmt_num($r["DEL_SCH"], 0)); ?></td>
                            <td class="col-sum num"><?php echo h(fmt_num($r["DEL_ACT"], 0)); ?></td>
                            <td class="col-sum num"><?php echo h(fmt_bal($r["BAL_QTY"])); ?></td>
                            <td class="col-count count-border num"><?php echo h(fmt_num($r["CNT_SCH"], 0)); ?></td>
                            <td class="col-count num"><?php echo h(fmt_num($r["CNT_ACT"], 0)); ?></td>
                            <td class="col-count num"><?php echo h(fmt_short_count_detail($r["SHORT_CNT"])); ?></td>
                            <td class="col-percent num"><?php echo h(fmt_percent_detail($r["SHORT_CNT"], $r["CNT_SCH"])); ?></td>
                        </tr>

                    <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
                        <tr>
                            <td colspan="8" class="center">
                                <?php echo h($r["MESSAGE"]); ?>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<script>
var custRows = [];
var custIndex = -1;
var timer = null;

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
    try {
        if (window.parent && window.parent !== window) {
            window.location.href = "dashboard_home.php";
            return;
        }
    } catch (e) {
    }

    window.open("", "_self");
    window.close();

    setTimeout(function () {
        if (!window.closed) {
            window.location.href = "dashboard_home.php";
        }
    }, 200);
}

function exportExcel() {
    var startDate = document.getElementById("START_DATE").value;
    var endDate = document.getElementById("END_DATE").value;
    var custCode = document.getElementById("CUST_CODE").value;

    if (startDate == "") {
        alert("Start Date belum diisi.");
        document.getElementById("START_DATE").focus();
        return;
    }

    if (endDate == "") {
        alert("End Date belum diisi.");
        document.getElementById("END_DATE").focus();
        return;
    }

    if (custCode == "") {
        alert("Customer belum diisi. Isi kode customer atau % untuk semua.");
        document.getElementById("CUST_CODE").focus();
        return;
    }

    window.location =
        "delivery_performance_export_excel.php" +
        "?START_DATE=" + enc(startDate) +
        "&END_DATE=" + enc(endDate) +
        "&CUST_CODE=" + enc(custCode);
}

function setAllCustomer() {
    document.getElementById("CUST_CODE").value = "%";
    document.forms[0].submit();
}

function hideSuggest() {
    var box = document.getElementById("custSuggest");
    box.style.display = "none";
    box.innerHTML = "";
    custRows = [];
    custIndex = -1;
}

function setActiveCust(index) {
    var box = document.getElementById("custSuggest");
    var items = box.getElementsByClassName("autocomplete-item");

    if (!items || items.length == 0) {
        custIndex = -1;
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
    custIndex = index;
}

function chooseCust(index) {
    if (index < 0 || index >= custRows.length) {
        return;
    }

    var r = custRows[index];

    document.getElementById("CUST_CODE").value = r.CUST_CODE;
    hideSuggest();
}

function renderSuggest(rows) {
    var box = document.getElementById("custSuggest");
    box.innerHTML = "";

    custRows = rows || [];
    custIndex = -1;

    if (!rows || rows.length == 0) {
        box.style.display = "none";
        return;
    }

    for (var i = 0; i < rows.length; i++) {
        (function (r, idx) {
            var div = document.createElement("div");
            div.className = "autocomplete-item";
            div.innerHTML =
                "<b>" + htmlEncode(r.CUST_CODE) + "</b> - " +
                htmlEncode(r.CUST_COMP);

            div.onmouseover = function () {
                setActiveCust(idx);
            };

            div.onmousedown = function (e) {
                if (e && e.preventDefault) {
                    e.preventDefault();
                }

                chooseCust(idx);
            };

            box.appendChild(div);
        })(rows[i], i);
    }

    box.style.display = "block";
    setActiveCust(0);
}

function searchCustomer(q) {
    if (q == "" || q == "%") {
        hideSuggest();
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open("POST", "ajax_customer_autocomplete.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4 && xhr.status == 200) {
            var result;

            try {
                result = JSON.parse(xhr.responseText);
            } catch (e) {
                return;
            }

            if (result.rows) {
                renderSuggest(result.rows);
            } else {
                renderSuggest(result);
            }
        }
    };

    xhr.send("q=" + enc(q));
}

document.getElementById("CUST_CODE").onkeyup = function (e) {
    e = e || window.event;

    var key = e.keyCode || e.which;

    if (key == 40) {
        setActiveCust(custIndex + 1);
        return;
    }

    if (key == 38) {
        setActiveCust(custIndex - 1);
        return;
    }

    if (key == 13) {
        if (custRows.length > 0) {
            if (custIndex < 0) {
                custIndex = 0;
            }

            chooseCust(custIndex);
            return false;
        }

        return true;
    }

    clearTimeout(timer);

    var q = this.value;

    timer = setTimeout(function () {
        searchCustomer(q);
    }, 250);
};

document.getElementById("CUST_CODE").onblur = function () {
    setTimeout(function () {
        hideSuggest();
    }, 250);
};
</script>

</body>
</html>
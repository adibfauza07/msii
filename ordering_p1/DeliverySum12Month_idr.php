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

function month_to_yyyymmdd($value) {
    $value = trim($value);
    if ($value == "") {
        return "";
    }
    if (preg_match('/^\d{4}-\d{2}$/', $value)) {
        return str_replace("-", "", $value) . "01";
    }
    if (preg_match('/^\d{8}$/', $value)) {
        return $value;
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return "";
    }
    return date("Ymd", $ts);
}

function yyyymmdd_to_month_input($value) {
    $value = trim($value);
    if ($value == "") {
        return date("Y-m");
    }
    if (preg_match('/^\d{8}$/', $value)) {
        return substr($value, 0, 4) . "-" . substr($value, 4, 2);
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return date("Y-m");
    }
    return date("Y-m", $ts);
}

function fmt_print_datetime() {
    return date("d-M-Y H:i:s");
}

function is_idr($currCode) {
    return strtoupper(trim((string)$currCode)) == "IDR";
}

function fmt_num($value, $decimal = 0) {
    if ($value === null || $value === "") {
        $value = 0;
    }
    return number_format((float)$value, $decimal, ".", ",");
}

function fmt_price_by_curr($value, $currCode) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    if (is_idr($currCode)) {
        return number_format((float)$value, 0, ".", ",");
    }

    return number_format((float)$value, 5, ".", ",");
}

function fmt_amount_by_curr($value, $currCode) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    if (is_idr($currCode)) {
        return number_format((float)$value, 0, ".", ",");
    }

    return number_format((float)$value, 2, ".", ",");
}

function fmt_zero_dash_qty($value) {
    if ($value === null || $value === "") {
        return "-";
    }
    $n = (float)$value;
    if ($n == 0) {
        return "-";
    }
    return number_format($n, 0, ".", ",");
}

function fmt_zero_dash_amount($value, $currCode) {
    if ($value === null || $value === "") {
        return "-";
    }
    $n = (float)$value;
    if ($n == 0) {
        return "-";
    }

    if (is_idr($currCode)) {
        return number_format($n, 0, ".", ",");
    }

    return number_format($n, 2, ".", ",");
}

function total_curr_code($allIdr) {
    return $allIdr ? "IDR" : "MIX";
}

$is_filter = get_param("RUN", "") == "1";

$start_month = get_param("START_MONTH", date("Y-m"));
$cust_code   = get_param("CUST_CODE", "");

if ($is_filter && $cust_code == "") {
    $cust_code = "%";
}

$start_ymd = month_to_yyyymmdd($start_month);

$months = array();
for ($m = 1; $m <= 12; $m++) {
    $months[$m] = "";
}

$rows = array();
$printRows = array();
$pages = array();

$totalPages = 0;
$rowsPerPage = 50;

if ($is_filter) {
    if ($start_ymd == "") {
        die("Starting Month tidak valid.");
    }

    if ($cust_code == "") {
        $cust_code = "%";
    }

    $sql = "
        SET NOCOUNT ON;
        EXEC dbo.DeliverySum12Month_idr ?, ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array($start_ymd, $cust_code));

    if ($stmt === false) {
        die("<pre>Query Delivery Summary 12 Month gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if ($months[1] == "" && isset($r["Month1"])) {
            for ($m = 1; $m <= 12; $m++) {
                $months[$m] = safe_trim($r["Month" . $m]);
            }
        }

        $currCode = safe_trim($r["CURR_CODE"]);

        $row = array(
            "CUST_CODE" => safe_trim($r["CUST_CODE"]),
            "CUST_COMP" => safe_trim($r["CUST_COMP"]),
            "PART_CODE" => isset($r["PART_NUM"]) ? safe_trim($r["PART_NUM"]) : "",
            "PART_NO"   => isset($r["PART_NO"]) ? safe_trim($r["PART_NO"]) : "",
            "PART_NAME" => safe_trim($r["PART_NAME"]),
            "PRICE"     => isset($r["PART_PRICE"]) ? (float)$r["PART_PRICE"] : 0,
            "CURR_CODE" => $currCode
        );

        $totalQty = 0;
        $totalAmt = 0;

        for ($m = 1; $m <= 12; $m++) {
            $qty = isset($r["DQTY" . $m]) ? (float)$r["DQTY" . $m] : 0;
            $amt = isset($r["AMT" . $m]) ? (float)$r["AMT" . $m] : 0;

            $row["QTY" . $m] = $qty;
            $row["AMT" . $m] = $amt;

            $totalQty += $qty;
            $totalAmt += $amt;
        }

        $row["TOTAL_QTY"] = $totalQty;
        $row["TOTAL_AMT"] = $totalAmt;

        $rows[] = $row;
    }

    if ($months[1] == "") {
        $ts = strtotime(substr($start_ymd, 0, 4) . "-" . substr($start_ymd, 4, 2) . "-01");
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = date("F Y", strtotime("+" . ($m - 1) . " month", $ts));
        }
    }

    $lastCust = "";

    $custQ = array();
    $custA = array();
    $grandQ = array();
    $grandA = array();

    for ($m = 1; $m <= 12; $m++) {
        $custQ[$m] = 0;
        $custA[$m] = 0;
        $grandQ[$m] = 0;
        $grandA[$m] = 0;
    }

    $custTQty = 0;
    $custTAmt = 0;
    $grandTQty = 0;
    $grandTAmt = 0;
    $custAllIdr = true;
    $grandAllIdr = true;

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];
        $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

        if ($custKey != $lastCust) {
            if ($lastCust != "") {
                $rowTotal = array(
                    "ROW_TYPE"  => "CUSTOMER_TOTAL",
                    "TOTAL_QTY" => $custTQty,
                    "TOTAL_AMT" => $custTAmt,
                    "CURR_CODE" => total_curr_code($custAllIdr)
                );

                for ($m = 1; $m <= 12; $m++) {
                    $rowTotal["QTY" . $m] = $custQ[$m];
                    $rowTotal["AMT" . $m] = $custA[$m];
                }

                $printRows[] = $rowTotal;
            }

            $printRows[] = array(
                "ROW_TYPE"  => "CUSTOMER",
                "CUST_CODE" => $r["CUST_CODE"],
                "CUST_COMP" => $r["CUST_COMP"]
            );

            $lastCust = $custKey;

            for ($m = 1; $m <= 12; $m++) {
                $custQ[$m] = 0;
                $custA[$m] = 0;
            }

            $custTQty = 0;
            $custTAmt = 0;
            $custAllIdr = true;
        }

        $printRows[] = array_merge(array("ROW_TYPE" => "DETAIL"), $r);

        if (!is_idr($r["CURR_CODE"])) {
            $custAllIdr = false;
            $grandAllIdr = false;
        }

        for ($m = 1; $m <= 12; $m++) {
            $custQ[$m] += $r["QTY" . $m];
            $custA[$m] += $r["AMT" . $m];
            $grandQ[$m] += $r["QTY" . $m];
            $grandA[$m] += $r["AMT" . $m];
        }

        $custTQty += $r["TOTAL_QTY"];
        $custTAmt += $r["TOTAL_AMT"];
        $grandTQty += $r["TOTAL_QTY"];
        $grandTAmt += $r["TOTAL_AMT"];
    }

    if ($lastCust != "") {
        $rowTotal = array(
            "ROW_TYPE"  => "CUSTOMER_TOTAL",
            "TOTAL_QTY" => $custTQty,
            "TOTAL_AMT" => $custTAmt,
            "CURR_CODE" => total_curr_code($custAllIdr)
        );

        for ($m = 1; $m <= 12; $m++) {
            $rowTotal["QTY" . $m] = $custQ[$m];
            $rowTotal["AMT" . $m] = $custA[$m];
        }

        $printRows[] = $rowTotal;
    }

    if (count($rows) > 0) {
        $rowGrand = array(
            "ROW_TYPE"  => "GRAND_TOTAL",
            "TOTAL_QTY" => $grandTQty,
            "TOTAL_AMT" => $grandTAmt,
            "CURR_CODE" => total_curr_code($grandAllIdr)
        );

        for ($m = 1; $m <= 12; $m++) {
            $rowGrand["QTY" . $m] = $grandQ[$m];
            $rowGrand["AMT" . $m] = $grandA[$m];
        }

        $printRows[] = $rowGrand;
    }

    if (count($printRows) == 0) {
        $printRows[] = array(
            "ROW_TYPE" => "EMPTY",
            "MESSAGE"  => "Data delivery summary 12 month tidak ditemukan."
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
    <title>Delivery History Summary 12 Month</title>

    <style>
        @page { size: A4 landscape; margin: 4mm; }

        body {
            margin: 0;
            background: #9a9a9a;
            font-family: "Courier New", monospace;
            font-size: 6px;
            color: #000000;
        }

        .filter-bar {
            width: 289mm;
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

        .filter-month { width: 130px; }
        .filter-cust { width: 160px; }

        .filter-bar button {
            height: 26px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 4px;
        }

        .autocomplete-wrap { position: relative; display: inline-block; }
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
        .autocomplete-item.active { background: #2f70c9; color: #ffffff; }

        .print-bar { width: 289mm; margin: 8px auto; text-align: right; }
        .print-bar button { padding: 6px 14px; font-size: 11px; cursor: pointer; font-family: Arial, sans-serif; }

        .page {
            width: 289mm;
            min-height: 201mm;
            margin: 10px auto;
            background: #ffffff;
            border: 2px solid #000000;
            padding: 4mm;
            box-sizing: border-box;
            page-break-after: always;
            overflow: hidden;
        }
        .page:last-child { page-break-after: auto; }

        .header { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .header td { border: none; vertical-align: top; }
        .company { width: 27%; font-family: Arial, sans-serif; font-size: 10px; line-height: 13px; }
        .company-title { font-size: 14px; font-weight: normal; }
        .title-area { width: 46%; text-align: center; font-family: Arial, sans-serif; }
        .report-title { font-size: 18px; font-weight: normal; margin-top: 4px; }
        .start-month { font-size: 10px; margin-top: 4px; }
        .right-info { width: 27%; text-align: right; font-family: Arial, sans-serif; font-size: 10px; line-height: 16px; }

        .summary-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .summary-table th,
        .summary-table td {
            border: none;
            padding: 0 1px;
            height: 10px;
            line-height: 8px;
            box-sizing: border-box;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            font-size: 6px;
        }
        .summary-table thead th {
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            font-weight: normal;
            text-align: center;
            font-size: 6px;
        }

        .col-code { width: 5%; text-align: left !important; }
        .col-no { width: 7%; text-align: left !important; }
        .col-name { width: 9%; text-align: left !important; border-right: 1px dashed #000000 !important; }
        .col-price { width: 4%; text-align: right; }
        .col-curr { width: 2%; text-align: center; }
        .col-qty { width: 2.4%; text-align: right; }
        .col-amt { width: 3.4%; text-align: right; }
        .month-border { border-right: 1px dashed #000000 !important; }

        .customer-row td { background: #bfbfbf; font-weight: bold; font-size: 7px; }
        .customer-total-row td { font-weight: bold; }
        .grand-total-row td { font-weight: bold; border-top: 2px solid #000000; border-bottom: 2px solid #000000; }
        .num { text-align: right; }
        .center { text-align: center; }
        .no-data { font-family: Arial, sans-serif; font-size: 14px; text-align: center; margin-top: 70px; line-height: 24px; }

        @media print {
            html, body { width: 297mm; min-height: 210mm; background: #ffffff; }
            .filter-bar, .print-bar { display: none; }
            .page { width: 289mm; min-height: 201mm; margin: 0 auto; border: none; padding: 3mm; overflow: hidden; }
        }
    </style>
</head>
<body>

<div class="filter-bar">
    <form method="get" action="<?php echo h($selfFile); ?>" autocomplete="off">
        <input type="hidden" name="RUN" value="1">

        Starting Month:
        <input type="month" id="START_MONTH" name="START_MONTH" class="filter-month" value="<?php echo h(yyyymmdd_to_month_input($start_ymd)); ?>">

        Customer:
        <div class="autocomplete-wrap">
            <input type="text" id="CUST_CODE" name="CUST_CODE" class="filter-cust" value="<?php echo h($cust_code); ?>" placeholder="Ketik customer / %">
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
                <td class="company"><div class="company-title">P.T. IMC TEKNO INDONESIA</div>PPIC Department</td>
                <td class="title-area"><div class="report-title">DELIVERY HISTORY SUMMARY 12 MONTH</div><div class="start-month">Starting Month: -</div></td>
                <td class="right-info">Page 0 of 0<br>Print Date: <?php echo h(fmt_print_datetime()); ?></td>
            </tr>
        </table>

        <div class="no-data">
            Data belum ditampilkan.<br><br>
            Pilih Starting Month dan Customer lalu klik <b>FILTER</b>.<br>
            Klik <b>ALL</b> untuk semua customer.
        </div>
    </div>
<?php } ?>

<?php for ($p = 0; $p < count($pages); $p++) { ?>
    <?php $pageRows = $pages[$p]; $pageNo = $p + 1; ?>

    <div class="page">
        <table class="header">
            <tr>
                <td class="company"><div class="company-title">P.T. IMC TEKNO INDONESIA</div>PPIC Department</td>
                <td class="title-area"><div class="report-title">DELIVERY HISTORY SUMMARY 12 MONTH</div><div class="start-month">Starting Month: <?php echo h($start_ymd); ?></div></td>
                <td class="right-info">Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?><br>Print Date: <?php echo h(fmt_print_datetime()); ?></td>
            </tr>
        </table>

        <table class="summary-table">
            <thead>
                <tr>
                    <th rowspan="2" class="col-code">Code</th>
                    <th rowspan="2" class="col-no">No</th>
                    <th rowspan="2" class="col-name">Name</th>
                    <th rowspan="2" class="col-price">Price</th>
                    <th rowspan="2" class="col-curr">Cur</th>
                    <?php for ($m = 1; $m <= 12; $m++) { ?>
                        <th colspan="2" class="month-border"><?php echo h($months[$m]); ?></th>
                    <?php } ?>
                    <th colspan="2">TOTAL</th>
                </tr>
                <tr>
                    <?php for ($m = 1; $m <= 12; $m++) { ?>
                        <th class="col-qty">QTY</th>
                        <th class="col-amt month-border">AMT</th>
                    <?php } ?>
                    <th class="col-qty">QTY</th>
                    <th class="col-amt">AMT</th>
                </tr>
            </thead>

            <tbody>
                <?php for ($i = 0; $i < count($pageRows); $i++) { ?>
                    <?php $r = $pageRows[$i]; ?>

                    <?php if ($r["ROW_TYPE"] == "CUSTOMER") { ?>
                        <tr class="customer-row"><td colspan="31"><?php echo h($r["CUST_CODE"]); ?> <?php echo h($r["CUST_COMP"]); ?></td></tr>
                    <?php } elseif ($r["ROW_TYPE"] == "DETAIL") { ?>
                        <?php $curr = $r["CURR_CODE"]; ?>
                        <tr>
                            <td class="col-code"><?php echo h($r["PART_CODE"]); ?></td>
                            <td class="col-no"><?php echo h($r["PART_NO"]); ?></td>
                            <td class="col-name"><?php echo h($r["PART_NAME"]); ?></td>
                            <td class="col-price num"><?php echo h(fmt_price_by_curr($r["PRICE"], $curr)); ?></td>
                            <td class="col-curr center"><?php echo h($curr); ?></td>

                            <?php for ($m = 1; $m <= 12; $m++) { ?>
                                <td class="col-qty num"><?php echo h(fmt_zero_dash_qty($r["QTY" . $m])); ?></td>
                                <td class="col-amt num month-border"><?php echo h(fmt_zero_dash_amount($r["AMT" . $m], $curr)); ?></td>
                            <?php } ?>

                            <td class="col-qty num"><?php echo h(fmt_zero_dash_qty($r["TOTAL_QTY"])); ?></td>
                            <td class="col-amt num"><?php echo h(fmt_zero_dash_amount($r["TOTAL_AMT"], $curr)); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "CUSTOMER_TOTAL") { ?>
                        <?php $curr = isset($r["CURR_CODE"]) ? $r["CURR_CODE"] : "MIX"; ?>
                        <tr class="customer-total-row">
                            <td colspan="5" style="text-align:right;">TOTAL CUSTOMER</td>
                            <?php for ($m = 1; $m <= 12; $m++) { ?>
                                <td class="col-qty num"><?php echo h(fmt_num($r["QTY" . $m], 0)); ?></td>
                                <td class="col-amt num month-border"><?php echo h(fmt_amount_by_curr($r["AMT" . $m], $curr)); ?></td>
                            <?php } ?>
                            <td class="col-qty num"><?php echo h(fmt_num($r["TOTAL_QTY"], 0)); ?></td>
                            <td class="col-amt num"><?php echo h(fmt_amount_by_curr($r["TOTAL_AMT"], $curr)); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "GRAND_TOTAL") { ?>
                        <?php $curr = isset($r["CURR_CODE"]) ? $r["CURR_CODE"] : "MIX"; ?>
                        <tr class="grand-total-row">
                            <td colspan="5" style="text-align:right;">GRAND TOTAL</td>
                            <?php for ($m = 1; $m <= 12; $m++) { ?>
                                <td class="col-qty num"><?php echo h(fmt_num($r["QTY" . $m], 0)); ?></td>
                                <td class="col-amt num month-border"><?php echo h(fmt_amount_by_curr($r["AMT" . $m], $curr)); ?></td>
                            <?php } ?>
                            <td class="col-qty num"><?php echo h(fmt_num($r["TOTAL_QTY"], 0)); ?></td>
                            <td class="col-amt num"><?php echo h(fmt_amount_by_curr($r["TOTAL_AMT"], $curr)); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
                        <tr><td colspan="31" class="center"><?php echo h($r["MESSAGE"]); ?></td></tr>
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

function enc(v) { return encodeURIComponent(v == null ? "" : v); }

function htmlEncode(value) {
    return String(value == null ? "" : value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\"/g, "&quot;");
}

function closeReport() {
    try {
        if (window.parent && window.parent !== window) {
            window.location.href = "dashboard_home.php";
            return;
        }
    } catch (e) {}

    window.open("", "_self");
    window.close();

    setTimeout(function () {
        if (!window.closed) {
            window.location.href = "dashboard_home.php";
        }
    }, 200);
}

function exportExcel() {
    var startMonth = document.getElementById("START_MONTH").value;
    var custCode = document.getElementById("CUST_CODE").value;

    if (startMonth == "") {
        alert("Starting Month belum dipilih.");
        document.getElementById("START_MONTH").focus();
        return;
    }

    if (custCode == "") {
        alert("Customer belum diisi. Isi kode customer atau % untuk semua.");
        document.getElementById("CUST_CODE").focus();
        return;
    }

    window.location = "delivery_summary_12month_export_excel_idr.php?START_MONTH=" + enc(startMonth) + "&CUST_CODE=" + enc(custCode);
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
            div.innerHTML = "<b>" + htmlEncode(r.CUST_CODE) + "</b> - " + htmlEncode(r.CUST_COMP);
            div.onmouseover = function () { setActiveCust(idx); };
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

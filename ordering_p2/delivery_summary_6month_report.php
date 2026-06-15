<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function safe_trim($value) {
    return $value === null ? "" : trim((string)$value);
}

function get_param($name, $default = "") {
    if (isset($_GET[$name])) return trim($_GET[$name]);
    if (isset($_POST[$name])) return trim($_POST[$name]);
    return $default;
}

function month_to_yyyymmdd($value) {
    $value = trim($value);

    if ($value == "") return "";

    if (preg_match('/^\d{4}-\d{2}$/', $value)) {
        return str_replace("-", "", $value) . "01";
    }

    if (preg_match('/^\d{8}$/', $value)) {
        return $value;
    }

    $ts = strtotime($value);
    if ($ts === false) return "";

    return date("Ymd", $ts);
}

function yyyymmdd_to_month_input($value) {
    $value = trim($value);

    if ($value == "") return date("Y-m");

    if (preg_match('/^\d{8}$/', $value)) {
        return substr($value, 0, 4) . "-" . substr($value, 4, 2);
    }

    $ts = strtotime($value);
    if ($ts === false) return date("Y-m");

    return date("Y-m", $ts);
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

function fmt_price($value) {
    if ($value === null || $value === "") {
        return "-";
    }

    $n = (float)$value;

    if ($n == 0) {
        return "-";
    }

    return rtrim(rtrim(number_format($n, 5, ".", ","), "0"), ".");
}

function fmt_amount($value) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, 2, ".", ",");
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

$is_filter = get_param("RUN", "") == "1";

$start_month = get_param("START_MONTH", date("Y-m"));
$cust_code   = get_param("CUST_CODE", "");

if ($is_filter && $cust_code == "") {
    $cust_code = "%";
}

$start_ymd = month_to_yyyymmdd($start_month);

$months = array("", "", "", "", "", "");
$rows = array();
$printRows = array();
$pages = array();

$totalPages = 0;
$rowsPerPage = 100;

if ($is_filter) {
    if ($start_ymd == "") {
        die("Starting Month tidak valid.");
    }

    if ($cust_code == "") {
        $cust_code = "%";
    }

    $sql = "
        SET NOCOUNT ON;
        EXEC dbo.DeliverySum6Month_char ?, ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array($start_ymd, $cust_code));

    if ($stmt === false) {
        die("<pre>Query Delivery Summary 6 Month gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if ($months[0] == "" && isset($r["Month1"])) {
            for ($m = 1; $m <= 6; $m++) {
                $months[$m - 1] = safe_trim($r["Month" . $m]);
            }
        }

        $currCode = safe_trim($r["CURR_CODE"]);

        $row = array(
            "CUST_CODE" => safe_trim($r["CUST_CODE"]),
            "CUST_COMP" => safe_trim($r["CUST_COMP"]),
            "PART_NUM"  => safe_trim($r["PART_NUM"]),
            "PART_NAME" => safe_trim($r["PART_NAME"]),
            "PRICE"     => isset($r["PART_PRICE"]) ? (float)$r["PART_PRICE"] : 0,
            "CURR_CODE" => $currCode
        );

        $totalQty = 0;
        $totalAmt = 0;

        for ($m = 1; $m <= 6; $m++) {
            $qty = isset($r["DQTY" . $m]) ? (float)$r["DQTY" . $m] : 0;

            /* AMOUNT ASLI DARI SP, TIDAK KONVERSI USD */
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

    if ($months[0] == "") {
        $ts = strtotime(substr($start_ymd, 0, 4) . "-" . substr($start_ymd, 4, 2) . "-01");

        for ($m = 0; $m < 6; $m++) {
            $months[$m] = date("F Y", strtotime("+" . $m . " month", $ts));
        }
    }

    $lastCust = "";

    $custQ = array(1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0);
    $custA = array(1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0);
    $custTQty = 0;
    $custTAmt = 0;

    $grandQ = array(1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0);
    $grandA = array(1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0);
    $grandTQty = 0;
    $grandTAmt = 0;

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];
        $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

        if ($custKey != $lastCust) {
            if ($lastCust != "") {
                $rowTotal = array(
                    "ROW_TYPE"  => "CUSTOMER_TOTAL",
                    "TOTAL_QTY" => $custTQty,
                    "TOTAL_AMT" => $custTAmt
                );

                for ($m = 1; $m <= 6; $m++) {
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

            for ($m = 1; $m <= 6; $m++) {
                $custQ[$m] = 0;
                $custA[$m] = 0;
            }

            $custTQty = 0;
            $custTAmt = 0;
        }

        $printRows[] = array_merge(array("ROW_TYPE" => "DETAIL"), $r);

        for ($m = 1; $m <= 6; $m++) {
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
            "TOTAL_AMT" => $custTAmt
        );

        for ($m = 1; $m <= 6; $m++) {
            $rowTotal["QTY" . $m] = $custQ[$m];
            $rowTotal["AMT" . $m] = $custA[$m];
        }

        $printRows[] = $rowTotal;
    }

    if (count($rows) > 0) {
        $rowGrand = array(
            "ROW_TYPE"  => "GRAND_TOTAL",
            "TOTAL_QTY" => $grandTQty,
            "TOTAL_AMT" => $grandTAmt
        );

        for ($m = 1; $m <= 6; $m++) {
            $rowGrand["QTY" . $m] = $grandQ[$m];
            $rowGrand["AMT" . $m] = $grandA[$m];
        }

        $printRows[] = $rowGrand;
    }

    if (count($printRows) == 0) {
        $printRows[] = array(
            "ROW_TYPE" => "EMPTY",
            "MESSAGE"  => "Data delivery summary 6 month tidak ditemukan."
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
    <title>Delivery History Summary 6 Month</title>

    <style>
    @page{
    size:A3 landscape;
    margin:5mm;
}

body{
    margin:0;
    background:#9a9a9a;
    font-family:"Courier New",monospace;
    font-size:8px;
    color:#000;
}

/* FILTER */
.filter-bar{
    width:400mm;
    margin:5px auto;
    background:#d4d0c8;
    border:1px solid #666;
    padding:4px;
    box-sizing:border-box;
    font-family:Tahoma,Arial,sans-serif;
    font-size:11px;
}

.filter-bar input{
    height:22px;
    border:1px solid #777;
    font-size:11px;
    padding:2px 4px;
}

.filter-month{
    width:120px;
}

.filter-cust{
    width:180px;
}

.filter-bar button{
    height:24px;
    font-size:11px;
    cursor:pointer;
}

/* AUTOCOMPLETE */
.autocomplete-wrap{
    position:relative;
    display:inline-block;
}

.autocomplete-list{
    position:absolute;
    top:22px;
    left:0;
    width:420px;
    max-height:250px;
    overflow-y:auto;
    background:#fff;
    border:1px solid #444;
    z-index:9999;
    display:none;
}

.autocomplete-item{
    padding:4px 6px;
    border-bottom:1px solid #ddd;
    cursor:pointer;
    font-size:11px;
}

.autocomplete-item:hover,
.autocomplete-item.active{
    background:#2f70c9;
    color:#fff;
}

/* BUTTON */
.print-bar{
    width:400mm;
    margin:5px auto;
    text-align:right;
}

.print-bar button{
    padding:4px 10px;
    font-size:11px;
}

/* PAGE */
.page{
    width:400mm;
    min-height:285mm;
    margin:5px auto;
    background:#fff;
    border:1px solid #000;
    padding:4mm;
    box-sizing:border-box;
    page-break-after:always;
}

.page:last-child{
    page-break-after:auto;
}

/* HEADER */
.header{
    width:100%;
    border-collapse:collapse;
    margin-bottom:4px;
}

.header td{
    border:none;
    vertical-align:top;
}

.company{
    width:28%;
    font-family:Arial,sans-serif;
    font-size:10px;
    line-height:12px;
}

.company-title{
    font-size:14px;
    font-weight:bold;
}

.title-area{
    width:44%;
    text-align:center;
}

.report-title{
    font-family:Arial,sans-serif;
    font-size:18px;
    font-weight:bold;
}

.start-month{
    font-family:Arial,sans-serif;
    font-size:10px;
}

.right-info{
    width:28%;
    text-align:right;
    font-family:Arial,sans-serif;
    font-size:10px;
}

/* TABLE */
.summary-table{
    width:100%;
    border-collapse:collapse;
    table-layout:fixed;
}

.summary-table th,
.summary-table td{
    padding:1px 2px;
    height:11px;
    line-height:9px;
    white-space:nowrap;
    overflow:hidden;
    font-size:7px;
}

.summary-table thead th{
    border-top:1px solid #000;
    border-bottom:1px solid #000;
    text-align:center;
    font-weight:bold;
    font-size:7px;
}

/* COLUMN */
.col-item{
    width:23%;
    text-align:left !important;
    border-right:1px dashed #000;
    padding-left:3px;
}

.col-price{
    width:4%;
    text-align:right !important;
}

.col-curr{
    width:3%;
    text-align:center !important;
}

.col-qty{
    width:3.5%;
    text-align:right !important;
}

.col-amt{
    width:5%;
    text-align:right !important;
}

.month-border{
    border-right:1px dashed #000;
}

/* NUMBER */
.num{
    text-align:right !important;
    padding-right:2px;
    font-size:7px;
    font-variant-numeric:tabular-nums;
}

/* CUSTOMER */
.customer-row td{
    background:#c0c0c0;
    font-weight:bold;
    font-size:8px;
    padding-top:2px;
    padding-bottom:2px;
}

.customer-total-row td{
    font-weight:bold;
    font-size:7px;
    border-top:1px solid #000;
}

.customer-total-row td:first-child{
    text-align:left !important;
}

.grand-total-row td{
    font-weight:bold;
    font-size:8px;
    border-top:2px solid #000;
    border-bottom:2px solid #000;
}

.grand-total-row td:first-child{
    text-align:left !important;
}

/* ALIGN */
.center{
    text-align:center;
}

.no-data{
    text-align:center;
    margin-top:60px;
    font-family:Arial,sans-serif;
    font-size:12px;
}

/* PRINT */
@media print{

    html,
    body{
        width:420mm;
        min-height:297mm;
        background:#fff;
    }

    .filter-bar,
    .print-bar{
        display:none;
    }

    .page{
        width:400mm;
        min-height:285mm;
        border:none;
        margin:0;
        padding:3mm;
    }
}
    </style>
</head>

<body>

<div class="filter-bar">
    <form method="get" action="<?php echo h($selfFile); ?>" autocomplete="off">
        <input type="hidden" name="RUN" value="1">

        Starting Month:
        <input type="month"
               id="START_MONTH"
               name="START_MONTH"
               class="filter-month"
               value="<?php echo h(yyyymmdd_to_month_input($start_ymd)); ?>">

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
                    <div class="report-title">DELIVERY HISTORY SUMMARY 6 MONTH</div>
                    <div class="start-month">Starting Month: -</div>
                </td>

                <td class="right-info">
                    Page 0 of 0<br>
                    Print Date: <?php echo h(fmt_print_datetime()); ?>
                </td>
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
                    <div class="report-title">DELIVERY HISTORY SUMMARY 6 MONTH</div>
                    <div class="start-month">
                        Starting Month: <?php echo h($start_ymd); ?>
                    </div>
                </td>

                <td class="right-info">
                    Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?><br>
                    Print Date: <?php echo h(fmt_print_datetime()); ?>
                </td>
            </tr>
        </table>

        <table class="summary-table">
            <thead>
                <tr>
                    <th rowspan="2" class="col-item">I&nbsp;&nbsp;T&nbsp;&nbsp;E&nbsp;&nbsp;M&nbsp;&nbsp;S</th>
                    <th rowspan="2" class="col-price">Price</th>
                    <th rowspan="2" class="col-curr">Cur</th>
                    <?php for ($m = 0; $m < 6; $m++) { ?>
                        <th colspan="2" class="month-border"><?php echo h($months[$m]); ?></th>
                    <?php } ?>
                    <th colspan="2">TOTAL</th>
                </tr>
                <tr>
                    <?php for ($m = 1; $m <= 6; $m++) { ?>
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
                        <tr class="customer-row">
                            <td colspan="17">
                                <?php echo h($r["CUST_CODE"]); ?>
                                <?php echo h($r["CUST_COMP"]); ?>
                            </td>
                        </tr>

                    <?php } elseif ($r["ROW_TYPE"] == "DETAIL") { ?>
                        <tr>
                            <td class="col-item">
                                <?php echo h($r["PART_NUM"]); ?>
                                <?php echo h($r["PART_NAME"]); ?>
                            </td>

                            <td class="col-price num"><?php echo h(fmt_price($r["PRICE"])); ?></td>
                            <td class="col-curr center"><?php echo h($r["CURR_CODE"]); ?></td>

                            <?php for ($m = 1; $m <= 6; $m++) { ?>
                                <td class="col-qty num"><?php echo h(fmt_zero_dash($r["QTY" . $m], 0)); ?></td>
                                <td class="col-amt num month-border"><?php echo h(fmt_zero_dash($r["AMT" . $m], 2)); ?></td>
                            <?php } ?>

                            <td class="col-qty num"><?php echo h(fmt_zero_dash($r["TOTAL_QTY"], 0)); ?></td>
                            <td class="col-amt num"><?php echo h(fmt_zero_dash($r["TOTAL_AMT"], 2)); ?></td>
                        </tr>

                    <?php } elseif ($r["ROW_TYPE"] == "CUSTOMER_TOTAL") { ?>
                        <tr class="customer-total-row">
                            <td class="col-item">TOTAL CUSTOMER</td>
                            <td></td>
                            <td></td>

                            <?php for ($m = 1; $m <= 6; $m++) { ?>
                                <td class="col-qty num"><?php echo h(fmt_num($r["QTY" . $m], 0)); ?></td>
                                <td class="col-amt num month-border"><?php echo h(fmt_amount($r["AMT" . $m])); ?></td>
                            <?php } ?>

                            <td class="col-qty num"><?php echo h(fmt_num($r["TOTAL_QTY"], 0)); ?></td>
                            <td class="col-amt num"><?php echo h(fmt_amount($r["TOTAL_AMT"])); ?></td>
                        </tr>

                    <?php } elseif ($r["ROW_TYPE"] == "GRAND_TOTAL") { ?>
                        <tr class="grand-total-row">
                            <td class="col-item">GRAND TOTAL</td>
                            <td></td>
                            <td></td>

                            <?php for ($m = 1; $m <= 6; $m++) { ?>
                                <td class="col-qty num"><?php echo h(fmt_num($r["QTY" . $m], 0)); ?></td>
                                <td class="col-amt num month-border"><?php echo h(fmt_amount($r["AMT" . $m])); ?></td>
                            <?php } ?>

                            <td class="col-qty num"><?php echo h(fmt_num($r["TOTAL_QTY"], 0)); ?></td>
                            <td class="col-amt num"><?php echo h(fmt_amount($r["TOTAL_AMT"])); ?></td>
                        </tr>

                    <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
                        <tr>
                            <td colspan="17" class="center">
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

    window.location =
        "delivery_summary_6month_export_excel.php" +
        "?START_MONTH=" + enc(startMonth) +
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

    if (index < 0) index = items.length - 1;
    if (index >= items.length) index = 0;

    for (var i = 0; i < items.length; i++) {
        items[i].className = "autocomplete-item";
    }

    items[index].className = "autocomplete-item active";
    custIndex = index;
}

function chooseCust(index) {
    if (index < 0 || index >= custRows.length) return;

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
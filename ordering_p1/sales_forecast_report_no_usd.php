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

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return str_replace("-", "", $value);
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

function fmt_print_date() {
    return date("d-M-Y H:i:s");
}

function fmt_issue_date() {
    return date("d-M-Y");
}

function fmt_num($value, $decimal = 0) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, $decimal, ".", ",");
}

function fmt_price($value) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, 4, ".", ",");
}

function fmt_amount($value) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, 2, ".", ",");
}

/*
    RUN=1 artinya user sudah klik FILTER / ALL.
    Pertama buka report tidak load data.
*/
$is_filter = get_param("RUN", "") == "1";

$cust_code   = get_param("CUST_CODE", "");
$start_month = get_param("START_MONTH", date("Y-m"));

if ($is_filter && $cust_code == "") {
    $cust_code = "%";
}

$start_date = month_to_yyyymmdd($start_month);

$dataRows = array();
$printRows = array();
$pages = array();

$bulan1 = "";
$bulan2 = "";
$bulan3 = "";

$totalPages = 0;
$rowsPerPage = 27;

if ($is_filter) {
    if ($start_date == "") {
        die("Month tidak valid.");
    }

    if ($cust_code == "") {
        $cust_code = "%";
    }

    $sql = "
        SET NOCOUNT ON;
        EXEC dbo.SP_SALES_FORCAST_3MONTH_idr ?, ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array(
        $start_date,
        $cust_code
    ));

    if ($stmt === false) {
        die("<pre>Query Sales Forecast gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if ($bulan1 == "") {
            $bulan1 = safe_trim($r["BULAN1"]);
            $bulan2 = safe_trim($r["BULAN2"]);
            $bulan3 = safe_trim($r["BULAN3"]);
        }

        $price = isset($r["PRDT_PRICE"]) ? (float)$r["PRDT_PRICE"] : 0;
        $qty1  = isset($r["QTY1"]) ? (float)$r["QTY1"] : 0;
        $qty2  = isset($r["QTY2"]) ? (float)$r["QTY2"] : 0;
        $qty3  = isset($r["QTY3"]) ? (float)$r["QTY3"] : 0;

        $currCode = safe_trim($r["CURR_CODE"]);

        /*
            TANPA KONVERSI USD:
            - Unit price tetap original.
            - Amount = Qty x Price original.
            - Currency tetap mengikuti CURR_CODE masing-masing item.
        */
        $dataRows[] = array(
            "CUST_CODE"  => safe_trim($r["CUST_CODE"]),
            "CUST_COMP"  => safe_trim($r["CUST_COMP"]),
            "PART_NUM"   => safe_trim($r["PART_NUM"]),
            "PART_NO"    => safe_trim($r["PART_NO"]),
            "PART_NAME"  => safe_trim($r["PART_NAME"]),
            "PRDT_PRICE" => $price,
            "CURR_CODE"  => $currCode,

            "QTY1"       => $qty1,
            "AMT1"       => $qty1 * $price,

            "QTY2"       => $qty2,
            "AMT2"       => $qty2 * $price,

            "QTY3"       => $qty3,
            "AMT3"       => $qty3 * $price
        );
    }

    if ($bulan1 == "") {
        $ts = strtotime(substr($start_date, 0, 4) . "-" . substr($start_date, 4, 2) . "-01");

        $bulan1 = strtoupper(date("M-y", $ts));
        $bulan2 = strtoupper(date("M-y", strtotime("+1 month", $ts)));
        $bulan3 = strtoupper(date("M-y", strtotime("+2 month", $ts)));
    }

    $lastCust = "";
    $lastCustCode = "";
    $lastCustComp = "";

    $custAmt1 = 0;
    $custAmt2 = 0;
    $custAmt3 = 0;

    $grandAmt1 = 0;
    $grandAmt2 = 0;
    $grandAmt3 = 0;

    for ($i = 0; $i < count($dataRows); $i++) {
        $r = $dataRows[$i];

        $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

        if ($custKey != $lastCust) {
            if ($lastCust != "") {
                $printRows[] = array(
                    "ROW_TYPE"  => "CUSTOMER_TOTAL",
                    "CUST_CODE" => $lastCustCode,
                    "CUST_COMP" => $lastCustComp,
                    "AMT1"      => $custAmt1,
                    "AMT2"      => $custAmt2,
                    "AMT3"      => $custAmt3
                );
            }

            $printRows[] = array(
                "ROW_TYPE"  => "CUSTOMER",
                "CUST_CODE" => $r["CUST_CODE"],
                "CUST_COMP" => $r["CUST_COMP"]
            );

            $lastCust = $custKey;
            $lastCustCode = $r["CUST_CODE"];
            $lastCustComp = $r["CUST_COMP"];

            $custAmt1 = 0;
            $custAmt2 = 0;
            $custAmt3 = 0;
        }

        $printRows[] = array(
            "ROW_TYPE"   => "DETAIL",
            "PART_NUM"   => $r["PART_NUM"],
            "PART_NO"    => $r["PART_NO"],
            "PART_NAME"  => $r["PART_NAME"],
            "PRDT_PRICE" => $r["PRDT_PRICE"],
            "CURR_CODE"  => $r["CURR_CODE"],
            "QTY1"       => $r["QTY1"],
            "AMT1"       => $r["AMT1"],
            "QTY2"       => $r["QTY2"],
            "AMT2"       => $r["AMT2"],
            "QTY3"       => $r["QTY3"],
            "AMT3"       => $r["AMT3"]
        );

        $custAmt1 += $r["AMT1"];
        $custAmt2 += $r["AMT2"];
        $custAmt3 += $r["AMT3"];

        $grandAmt1 += $r["AMT1"];
        $grandAmt2 += $r["AMT2"];
        $grandAmt3 += $r["AMT3"];
    }

    if ($lastCust != "") {
        $printRows[] = array(
            "ROW_TYPE"  => "CUSTOMER_TOTAL",
            "CUST_CODE" => $lastCustCode,
            "CUST_COMP" => $lastCustComp,
            "AMT1"      => $custAmt1,
            "AMT2"      => $custAmt2,
            "AMT3"      => $custAmt3
        );
    }

    if (count($dataRows) > 0) {
        $printRows[] = array(
            "ROW_TYPE" => "GRAND_TOTAL",
            "AMT1"     => $grandAmt1,
            "AMT2"     => $grandAmt2,
            "AMT3"     => $grandAmt3
        );
    }

    if (count($printRows) == 0) {
        $printRows[] = array(
            "ROW_TYPE" => "EMPTY",
            "MESSAGE"  => "Data forecast tidak ditemukan."
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
    <title>Sales Forecast</title>

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

        .filter-cust {
            width: 160px;
        }

        .filter-month {
            width: 130px;
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
            padding: 7mm;
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
            vertical-align: top;
        }

        .company {
            width: 36%;
            border: none;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 14px;
        }

        .company-title {
            font-size: 16px;
            font-weight: normal;
        }

        .title-area {
            width: 34%;
            border: none;
            text-align: center;
            font-family: Arial, sans-serif;
        }

        .report-title {
            font-size: 24px;
            font-weight: normal;
            margin-top: 18px;
        }

        .right-box {
            width: 30%;
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 10px;
        }

        .right-box td {
            border: 1px solid #000000;
            text-align: center;
            height: 64px;
            width: 33.33%;
            vertical-align: top;
            padding-top: 7px;
        }

        .issued {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin-bottom: 2px;
        }

        .page-info {
            text-align: right;
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin-bottom: 3px;
        }

        .forecast-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .forecast-table th,
        .forecast-table td {
            border: none;
            padding: 1px 3px;
            height: 16px;
            line-height: 12px;
            box-sizing: border-box;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            font-size: 10px;
        }

        .forecast-table thead th {
            border-bottom: 1px solid #000000;
            font-weight: bold;
            text-align: center;
        }

        .customer-row td {
            font-weight: bold;
            font-size: 10px;
            padding-top: 4px;
        }

        .total-row td {
            font-weight: bold;
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            background: #eeeeee;
        }

        .grand-row td {
            font-weight: bold;
            border-top: 2px solid #000000;
            border-bottom: 2px solid #000000;
            background: #d9eaf7;
        }

        .num {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .col-code {
            width: 7%;
        }

        .col-no {
            width: 13%;
        }

        .col-name {
            width: 26%;
        }

        .col-price {
            width: 8%;
        }

        .col-cur {
            width: 4%;
        }

        .col-qty {
            width: 6%;
        }

        .col-amt {
            width: 8%;
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
                height: 210mm;
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
                padding: 5mm;
                overflow: hidden;
            }
        }
    </style>
</head>

<body>

<div class="filter-bar">
    <form method="get" action="<?php echo h($selfFile); ?>" autocomplete="off">
        <input type="hidden" name="RUN" value="1">

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

        Month:
        <input type="month"
               id="START_MONTH"
               name="START_MONTH"
               class="filter-month"
               value="<?php echo h(yyyymmdd_to_month_input($start_date)); ?>">

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
                    PPIC DEPT
                </td>

                <td class="title-area">
                    <div class="report-title">SALES FORECAST</div>
                </td>

                <td>
                    <table class="right-box">
                        <tr>
                            <td>Approved by</td>
                            <td>Checked by</td>
                            <td>Prepared by</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="issued">
            <b>Issued Date :</b> <?php echo h(fmt_issue_date()); ?><br>
            <?php echo h(date("H:i:s A")); ?>
        </div>

        <div class="no-data">
            Data belum ditampilkan.<br><br>
            Pilih <b>Customer</b> dan <b>Month</b>, lalu klik <b>FILTER</b>.<br>
            Klik <b>ALL</b> untuk tampil semua customer.
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
                    PPIC DEPT
                </td>

                <td class="title-area">
                    <div class="report-title">SALES FORECAST</div>
                </td>

                <td>
                    <table class="right-box">
                        <tr>
                            <td>Approved by</td>
                            <td>Checked by</td>
                            <td>Prepared by</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="page-info">
            Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?>
            &nbsp;&nbsp;&nbsp;
            Print Date : <?php echo h(fmt_print_date()); ?>
        </div>

        <div class="issued">
            <b>Issued Date :</b> <?php echo h(fmt_issue_date()); ?><br>
            <?php echo h(date("H:i:s A")); ?>
        </div>

        <table class="forecast-table">
            <thead>
                <tr>
                    <th rowspan="2" class="col-code">Code</th>
                    <th rowspan="2" class="col-no">No.</th>
                    <th rowspan="2" class="col-name">Part Name</th>
                    <th rowspan="2" class="col-price">Unit Price</th>
                    <th rowspan="2" class="col-cur">Cur.</th>

                    <th colspan="2"><?php echo h($bulan1); ?></th>
                    <th colspan="2"><?php echo h($bulan2); ?></th>
                    <th colspan="2"><?php echo h($bulan3); ?></th>
                </tr>
                <tr>
                    <th class="col-qty">Qty</th>
                    <th class="col-amt">Amount</th>
                    <th class="col-qty">Qty</th>
                    <th class="col-amt">Amount</th>
                    <th class="col-qty">Qty</th>
                    <th class="col-amt">Amount</th>
                </tr>
            </thead>

            <tbody>
                <?php for ($i = 0; $i < count($pageRows); $i++) { ?>
                    <?php $r = $pageRows[$i]; ?>

                    <?php if ($r["ROW_TYPE"] == "CUSTOMER") { ?>
                        <tr class="customer-row">
                            <td colspan="11">
                                <?php echo h($r["CUST_CODE"]); ?>
                                &nbsp;
                                <?php echo h($r["CUST_COMP"]); ?>
                            </td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "DETAIL") { ?>
                        <tr>
                            <td class="col-code"><?php echo h($r["PART_NUM"]); ?></td>
                            <td class="col-no"><?php echo h($r["PART_NO"]); ?></td>
                            <td class="col-name"><?php echo h($r["PART_NAME"]); ?></td>
                            <td class="col-price num"><?php echo h(fmt_price($r["PRDT_PRICE"])); ?></td>
                            <td class="col-cur center"><?php echo h($r["CURR_CODE"]); ?></td>

                            <td class="col-qty num"><?php echo h(fmt_num($r["QTY1"], 0)); ?></td>
                            <td class="col-amt num"><?php echo h(fmt_amount($r["AMT1"])); ?></td>

                            <td class="col-qty num"><?php echo h(fmt_num($r["QTY2"], 0)); ?></td>
                            <td class="col-amt num"><?php echo h(fmt_amount($r["AMT2"])); ?></td>

                            <td class="col-qty num"><?php echo h(fmt_num($r["QTY3"], 0)); ?></td>
                            <td class="col-amt num"><?php echo h(fmt_amount($r["AMT3"])); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "CUSTOMER_TOTAL") { ?>
                        <tr class="total-row">
                            <td colspan="6" class="num">
                                TOTAL AMOUNT
                                <?php echo h($r["CUST_CODE"]); ?>
                                -
                                <?php echo h($r["CUST_COMP"]); ?>
                            </td>

                            <td class="num"><?php echo h(fmt_amount($r["AMT1"])); ?></td>
                            <td></td>
                            <td class="num"><?php echo h(fmt_amount($r["AMT2"])); ?></td>
                            <td></td>
                            <td class="num"><?php echo h(fmt_amount($r["AMT3"])); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "GRAND_TOTAL") { ?>
                        <tr class="grand-row">
                            <td colspan="6" class="num">
                                GRAND TOTAL AMOUNT
                            </td>

                            <td class="num"><?php echo h(fmt_amount($r["AMT1"])); ?></td>
                            <td></td>
                            <td class="num"><?php echo h(fmt_amount($r["AMT2"])); ?></td>
                            <td></td>
                            <td class="num"><?php echo h(fmt_amount($r["AMT3"])); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
                        <tr>
                            <td colspan="11" class="center">
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
    var custCode = document.getElementById("CUST_CODE").value;
    var startMonth = document.getElementById("START_MONTH").value;

    if (custCode == "") {
        alert("Customer belum diisi. Isi kode customer atau % untuk semua.");
        document.getElementById("CUST_CODE").focus();
        return;
    }

    if (startMonth == "") {
        alert("Month belum dipilih.");
        document.getElementById("START_MONTH").focus();
        return;
    }

    window.location =
        "sales_forecast_export_excel_no_usd.php" +
        "?CUST_CODE=" + enc(custCode) +
        "&START_MONTH=" + enc(startMonth);
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

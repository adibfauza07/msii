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

function fmt_date($value) {
    if ($value instanceof DateTime) {
        return $value->format("j-M-y");
    }

    if ($value === null || $value === "") {
        return "";
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return "";
    }

    return date("j-M-y", $ts);
}

function fmt_print_datetime() {
    return date("d-M-Y h:i:sA");
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

function get_usd_factor($row) {
    $crate = 1;
    $basecrate = 1;

    if (isset($row["CRATE"]) && $row["CRATE"] !== null && $row["CRATE"] != 0) {
        $crate = (float)$row["CRATE"];
    }

    if (isset($row["BASECRATE"]) && $row["BASECRATE"] !== null && $row["BASECRATE"] != 0) {
        $basecrate = (float)$row["BASECRATE"];
    }

    if ($basecrate == 0) {
        $basecrate = 1;
    }

    return $crate / $basecrate;
}

$is_filter = get_param("RUN", "") == "1";

$asper_month = get_param("ASPER_MONTH", date("Y-m"));
$cust_code   = get_param("CUST_CODE", "");

if ($is_filter && $cust_code == "") {
    $cust_code = "%";
}

$asper_ymd = month_to_yyyymmdd($asper_month);

$rows = array();
$printRows = array();
$pages = array();

$monthYear = "";
$totalPages = 0;
$rowsPerPage = 28;

if ($is_filter) {
    if ($asper_ymd == "") {
        die("Month tidak valid.");
    }

    if ($cust_code == "") {
        $cust_code = "%";
    }

    $sql = "
        SET NOCOUNT ON;
        EXEC dbo.SP_DELIVERY_HISTORY_char ?, ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array(
        $asper_ymd,
        $cust_code
    ));

    if ($stmt === false) {
        die("<pre>Query Delivery History gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if ($monthYear == "" && isset($r["MonthYear"])) {
            $monthYear = safe_trim($r["MonthYear"]);
        }

        $qty = isset($r["QTY"]) ? (float)$r["QTY"] : 0;
        $price = isset($r["PART_PRICE"]) ? (float)$r["PART_PRICE"] : 0;
        $amount = isset($r["AMOUNT"]) ? (float)$r["AMOUNT"] : ($qty * $price);
        $usdFactor = get_usd_factor($r);

        $rows[] = array(
            "CUST_ID"    => isset($r["CUST_ID"]) ? intval($r["CUST_ID"]) : 0,
            "CUST_CODE"  => safe_trim($r["CUST_CODE"]),
            "CUST_COMP"  => safe_trim($r["CUST_COMP"]),
            "DI_DATE"    => $r["DI_DATE"],
            "DI_DSNO"    => safe_trim($r["DI_DSNO"]),
            "ORDR_PO"    => safe_trim($r["ORDR_PO"]),
            "PART_NUM"   => safe_trim($r["PART_NUM"]),
            "PART_CODE"  => safe_trim($r["PART_CODE"]),
            "PART_NAME"  => safe_trim($r["PART_NAME"]),
            "CURR"       => safe_trim($r["CURR"]),
            "PART_PRICE" => $price,
            "QTY"        => $qty,
            "AMOUNT"     => $amount,
            "USD_AMT"    => $amount * $usdFactor
        );
    }

    if ($monthYear == "") {
        $ts = strtotime(substr($asper_ymd, 0, 4) . "-" . substr($asper_ymd, 4, 2) . "-01");
        $monthYear = date("F Y", $ts);
    }

    $lastCust = "";
    $lastCustCode = "";
    $lastCustComp = "";

    $lastPart = "";
    $lastPartNum = "";
    $lastPartName = "";

    $partQty = 0;
    $partAmount = 0;
    $partUsdAmount = 0;

    $custQty = 0;
    $custAmount = 0;
    $custUsdAmount = 0;

    $grandQty = 0;
    $grandAmount = 0;
    $grandUsdAmount = 0;

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];

        $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];
        $partKey = $r["CUST_CODE"] . "|" . $r["PART_NUM"] . "|" . $r["PART_NAME"];

        if ($custKey != $lastCust) {
            if ($lastPart != "") {
                $printRows[] = array(
                    "ROW_TYPE" => "PART_TOTAL",
                    "QTY"      => $partQty,
                    "AMOUNT"   => $partAmount,
                    "USD_AMT"  => $partUsdAmount
                );
            }

            if ($lastCust != "") {
                $printRows[] = array(
                    "ROW_TYPE"  => "CUSTOMER_TOTAL",
                    "CUST_CODE" => $lastCustCode,
                    "CUST_COMP" => $lastCustComp,
                    "QTY"       => $custQty,
                    "AMOUNT"    => $custAmount,
                    "USD_AMT"   => $custUsdAmount
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

            $lastPart = "";
            $lastPartNum = "";
            $lastPartName = "";

            $partQty = 0;
            $partAmount = 0;
            $partUsdAmount = 0;

            $custQty = 0;
            $custAmount = 0;
            $custUsdAmount = 0;
        }

        if ($partKey != $lastPart) {
            if ($lastPart != "") {
                $printRows[] = array(
                    "ROW_TYPE" => "PART_TOTAL",
                    "QTY"      => $partQty,
                    "AMOUNT"   => $partAmount,
                    "USD_AMT"  => $partUsdAmount
                );
            }

            $lastPart = $partKey;
            $lastPartNum = $r["PART_NUM"];
            $lastPartName = $r["PART_NAME"];

            $partQty = 0;
            $partAmount = 0;
            $partUsdAmount = 0;
        }

        $printRows[] = array(
            "ROW_TYPE"   => "DETAIL",
            "PART_NUM"   => $r["PART_NUM"],
            "PART_NAME"  => $r["PART_NAME"],
            "DI_DSNO"    => $r["DI_DSNO"],
            "PART_PRICE" => $r["PART_PRICE"],
            "CURR"       => $r["CURR"],
            "DI_DATE"    => $r["DI_DATE"],
            "ORDR_PO"    => $r["ORDR_PO"],
            "QTY"        => $r["QTY"],
            "AMOUNT"     => $r["AMOUNT"],
            "USD_AMT"    => $r["USD_AMT"]
        );

        $partQty += $r["QTY"];
        $partAmount += $r["AMOUNT"];
        $partUsdAmount += $r["USD_AMT"];

        $custQty += $r["QTY"];
        $custAmount += $r["AMOUNT"];
        $custUsdAmount += $r["USD_AMT"];

        $grandQty += $r["QTY"];
        $grandAmount += $r["AMOUNT"];
        $grandUsdAmount += $r["USD_AMT"];
    }

    if ($lastPart != "") {
        $printRows[] = array(
            "ROW_TYPE" => "PART_TOTAL",
            "QTY"      => $partQty,
            "AMOUNT"   => $partAmount,
            "USD_AMT"  => $partUsdAmount
        );
    }

    if ($lastCust != "") {
        $printRows[] = array(
            "ROW_TYPE"  => "CUSTOMER_TOTAL",
            "CUST_CODE" => $lastCustCode,
            "CUST_COMP" => $lastCustComp,
            "QTY"       => $custQty,
            "AMOUNT"    => $custAmount,
            "USD_AMT"   => $custUsdAmount
        );
    }

    if (count($rows) > 0) {
        $printRows[] = array(
            "ROW_TYPE" => "GRAND_TOTAL",
            "QTY"      => $grandQty,
            "AMOUNT"   => $grandAmount,
            "USD_AMT"  => $grandUsdAmount
        );
    }

    if (count($printRows) == 0) {
        $printRows[] = array(
            "ROW_TYPE" => "EMPTY",
            "MESSAGE"  => "Data delivery history tidak ditemukan."
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
    <title>Delivery History</title>

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

        .filter-month {
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
            margin-bottom: 12px;
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
            font-size: 22px;
            font-weight: normal;
            margin-top: 8px;
        }

        .as-per {
            font-size: 11px;
            margin-top: 4px;
        }

        .right-info {
            width: 30%;
            text-align: right;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 17px;
        }

        .print-date {
            text-align: center;
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin-bottom: 8px;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .history-table th,
        .history-table td {
            border: none;
            padding: 2px 3px;
            height: 17px;
            line-height: 12px;
            box-sizing: border-box;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            font-size: 10px;
        }

        .history-table thead th {
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            font-weight: normal;
            text-align: left;
            font-size: 11px;
        }

        .customer-row td {
            font-weight: bold;
            font-size: 11px;
        }

        .part-total-row td {
            font-weight: bold;
            font-style: italic;
        }

        .customer-total-row td {
            font-weight: bold;
            border-top: 1px solid #000000;
        }

        .grand-row td {
            font-weight: bold;
            border-top: 3px double #000000;
            border-bottom: 3px double #000000;
        }

        .num {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .col-code { width: 9%; }
        .col-name { width: 27%; }
        .col-ds { width: 15%; }
        .col-price { width: 7%; }
        .col-curr { width: 5%; }
        .col-date { width: 8%; }
        .col-po { width: 11%; }
        .col-qty { width: 7%; }
        .col-amt { width: 8%; }
        .col-usd { width: 8%; }

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

        Month:
        <input type="month"
               id="ASPER_MONTH"
               name="ASPER_MONTH"
               class="filter-month"
               value="<?php echo h(yyyymmdd_to_month_input($asper_ymd)); ?>">

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
                    <div class="report-title">DELIVERY HISTORY</div>
                    <div class="as-per">As Per : -</div>
                </td>

                <td class="right-info">
                    FM.CO.00-61<br>
                    Page 0 of 0
                </td>
            </tr>
        </table>

        <div class="no-data">
            Data belum ditampilkan.<br><br>
            Pilih Month dan Customer lalu klik <b>FILTER</b>.<br>
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
                    <div class="report-title">DELIVERY HISTORY</div>
                    <div class="as-per">As Per : <?php echo h($monthYear); ?></div>
                </td>

                <td class="right-info">
                    FM.CO.00-61<br>
                    Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?>
                </td>
            </tr>
        </table>

        <div class="print-date">
            Print Date :
            &nbsp;&nbsp;&nbsp;&nbsp;
            <?php echo h(fmt_print_datetime()); ?>
        </div>

        <table class="history-table">
            <thead>
                <tr>
                    <th class="col-code">CODE</th>
                    <th class="col-name">NAME</th>
                    <th class="col-ds">DS.NO</th>
                    <th class="col-price">PRICE</th>
                    <th class="col-curr">CURR</th>
                    <th class="col-date">DATE</th>
                    <th class="col-po">PO #</th>
                    <th class="col-qty num">QTY</th>
                    <th class="col-amt num">AMOUNT</th>
                    <th class="col-usd num">USD.AMT</th>
                </tr>
            </thead>

            <tbody>
                <?php for ($i = 0; $i < count($pageRows); $i++) { ?>
                    <?php $r = $pageRows[$i]; ?>

                    <?php if ($r["ROW_TYPE"] == "CUSTOMER") { ?>
                        <tr class="customer-row">
                            <td colspan="10">
                                <?php echo h($r["CUST_CODE"]); ?>
                                &nbsp;&nbsp;&nbsp;
                                <?php echo h($r["CUST_COMP"]); ?>
                            </td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "DETAIL") { ?>
                        <tr>
                            <td class="col-code"><?php echo h($r["PART_NUM"]); ?></td>
                            <td class="col-name"><?php echo h($r["PART_NAME"]); ?></td>
                            <td class="col-ds"><?php echo h($r["DI_DSNO"]); ?></td>
                            <td class="col-price num"><?php echo h(fmt_price($r["PART_PRICE"])); ?></td>
                            <td class="col-curr center"><?php echo h($r["CURR"]); ?></td>
                            <td class="col-date"><?php echo h(fmt_date($r["DI_DATE"])); ?></td>
                            <td class="col-po"><?php echo h($r["ORDR_PO"]); ?></td>
                            <td class="col-qty num"><?php echo h(fmt_num($r["QTY"], 0)); ?></td>
                            <td class="col-amt num"><?php echo h(fmt_amount($r["AMOUNT"])); ?></td>
                            <td class="col-usd num"><?php echo h(fmt_amount($r["USD_AMT"])); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "PART_TOTAL") { ?>
                        <tr class="part-total-row">
                            <td colspan="7" class="num">
                                Subtotal P A R T
                            </td>
                            <td class="num"><?php echo h(fmt_num($r["QTY"], 0)); ?></td>
                            <td class="num"><?php echo h(fmt_amount($r["AMOUNT"])); ?></td>
                            <td class="num"><?php echo h(fmt_amount($r["USD_AMT"])); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "CUSTOMER_TOTAL") { ?>
                        <tr class="customer-total-row">
                            <td colspan="7" class="num">
                                Subtotal CUSTOMER <?php echo h($r["CUST_CODE"]); ?>
                            </td>
                            <td class="num"><?php echo h(fmt_num($r["QTY"], 0)); ?></td>
                            <td class="num"><?php echo h(fmt_amount($r["AMOUNT"])); ?></td>
                            <td class="num"><?php echo h(fmt_amount($r["USD_AMT"])); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "GRAND_TOTAL") { ?>
                        <tr class="grand-row">
                            <td colspan="7" class="num">Grand Total :</td>
                            <td class="num"><?php echo h(fmt_num($r["QTY"], 0)); ?></td>
                            <td class="num"><?php echo h(fmt_amount($r["AMOUNT"])); ?></td>
                            <td class="num"><?php echo h(fmt_amount($r["USD_AMT"])); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
                        <tr>
                            <td colspan="10" class="center">
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
    var asperMonth = document.getElementById("ASPER_MONTH").value;
    var custCode = document.getElementById("CUST_CODE").value;

    if (asperMonth == "") {
        alert("Month belum dipilih.");
        document.getElementById("ASPER_MONTH").focus();
        return;
    }

    if (custCode == "") {
        alert("Customer belum diisi. Isi kode customer atau % untuk semua.");
        document.getElementById("CUST_CODE").focus();
        return;
    }

    window.location =
        "delivery_history_export_excel.php" +
        "?ASPER_MONTH=" + enc(asperMonth) +
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
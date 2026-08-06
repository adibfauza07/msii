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

function fmt_print_date() {
    return date("d-M-Y H:i:s");
}

function fmt_date($value) {
    if ($value instanceof DateTime) {
        return $value->format("d-M-Y");
    }

    if ($value === null || $value === "") {
        return "";
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return "";
    }

    return date("d-M-Y", $ts);
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

    return number_format((float)$value, 2, ".", ",");
}

function fmt_amount($value) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, 2, ".", ",");
}

function calc_usd_factor($row) {
    $currRate = 1;
    $usdRate  = 1;

    if (isset($row["CURR_VRATE"]) && $row["CURR_VRATE"] !== null && $row["CURR_VRATE"] != 0) {
        $currRate = (float)$row["CURR_VRATE"];
    }

    if (isset($row["USDRATE"]) && $row["USDRATE"] !== null && $row["USDRATE"] != 0) {
        $usdRate = (float)$row["USDRATE"];
    }

    if ($usdRate == 0) {
        $usdRate = 1;
    }

    return $currRate / $usdRate;
}

$is_filter = get_param("RUN", "") == "1";
$cust_code = get_param("CUST_CODE", "");

if ($is_filter && $cust_code == "") {
    $cust_code = "%";
}

$rows = array();
$printRows = array();
$pages = array();
$totalPages = 0;
$rowsPerPage = 37;

if ($is_filter) {
    $sql = "
        SET NOCOUNT ON;
        EXEC dbo.SP_OUTSTANDING_ORDER ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array($cust_code));

    if ($stmt === false) {
        die("<pre>Query Outstanding Customer Order gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $factor = calc_usd_factor($r);

        $unitPriceOri = isset($r["ORDP_PRICE"]) ? (float)$r["ORDP_PRICE"] : 0;

        $orderQty = isset($r["ORDP_QTY"]) ? (float)$r["ORDP_QTY"] : 0;
        $delvQty  = isset($r["ORDP_DQTY"]) ? (float)$r["ORDP_DQTY"] : 0;
        $balQty   = isset($r["ORDP_BQTY"]) ? (float)$r["ORDP_BQTY"] : 0;

        $unitPriceUsd = $unitPriceOri * $factor;

        $rows[] = array(
            "CUST_ID"        => isset($r["CUST_ID"]) ? intval($r["CUST_ID"]) : 0,
            "CUST_CODE"      => safe_trim($r["CUST_CODE"]),
            "CUST_COMP"      => safe_trim($r["CUST_COMP"]),

            "PART_NUM"       => safe_trim($r["PART_NUM"]),
            "PART_NO"        => safe_trim($r["PART_NO"]),
            "PART_NAME"      => safe_trim($r["PART_NAME"]),

            "ORDR_PO"        => safe_trim($r["ORDR_PO"]),
            "ORDR_DATE"      => $r["ORDR_DATE"],

            "UNIT_PRICE_USD" => $unitPriceUsd,
            "CURR"           => "USD",

            "ORDP_QTY"       => $orderQty,
            "AMOUNT_USD"     => $orderQty * $unitPriceUsd,

            "ORDP_DQTY"      => $delvQty,
            "DAMOUNT_USD"    => $delvQty * $unitPriceUsd,

            "ORDP_BQTY"      => $balQty,
            "BAMOUNT_USD"    => $balQty * $unitPriceUsd
        );
    }

   $lastCust = "";
$lastCustCode = "";
$lastCustComp = "";

$lastItem = "";
$lastItemCode = "";
$lastItemName = "";

$itemOrderQty = 0;
$itemOrderAmount = 0;
$itemDelvQty = 0;
$itemDelvAmount = 0;
$itemBalQty = 0;
$itemBalAmount = 0;

$custOrderAmount = 0;
$custDelvAmount  = 0;
$custBalAmount   = 0;

$grandOrderAmount = 0;
$grandDelvAmount  = 0;
$grandBalAmount   = 0;

for ($i = 0; $i < count($rows); $i++) {
    $r = $rows[$i];

    $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];
    $itemKey = $r["PART_NUM"] . "|" . $r["PART_NO"] . "|" . $r["PART_NAME"];

    if ($custKey != $lastCust) {

        if ($lastItem != "") {
            $printRows[] = array(
                "ROW_TYPE"     => "ITEM_TOTAL",
                "PART_NUM"     => $lastItemCode,
                "PART_NAME"    => $lastItemName,
                "ORDER_QTY"    => $itemOrderQty,
                "ORDER_AMOUNT" => $itemOrderAmount,
                "DELIV_QTY"    => $itemDelvQty,
                "DELIV_AMOUNT" => $itemDelvAmount,
                "BAL_QTY"      => $itemBalQty,
                "BAL_AMOUNT"   => $itemBalAmount
            );
        }

        if ($lastCust != "") {
            $printRows[] = array(
                "ROW_TYPE"     => "CUSTOMER_TOTAL",
                "CUST_CODE"    => $lastCustCode,
                "CUST_COMP"    => $lastCustComp,
                "ORDER_AMOUNT" => $custOrderAmount,
                "DELIV_AMOUNT" => $custDelvAmount,
                "BAL_AMOUNT"   => $custBalAmount
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

        $custOrderAmount = 0;
        $custDelvAmount  = 0;
        $custBalAmount   = 0;

        $lastItem = "";
        $lastItemCode = "";
        $lastItemName = "";

        $itemOrderQty = 0;
        $itemOrderAmount = 0;
        $itemDelvQty = 0;
        $itemDelvAmount = 0;
        $itemBalQty = 0;
        $itemBalAmount = 0;
    }

    if ($itemKey != $lastItem) {
        if ($lastItem != "") {
            $printRows[] = array(
                "ROW_TYPE"     => "ITEM_TOTAL",
                "PART_NUM"     => $lastItemCode,
                "PART_NAME"    => $lastItemName,
                "ORDER_QTY"    => $itemOrderQty,
                "ORDER_AMOUNT" => $itemOrderAmount,
                "DELIV_QTY"    => $itemDelvQty,
                "DELIV_AMOUNT" => $itemDelvAmount,
                "BAL_QTY"      => $itemBalQty,
                "BAL_AMOUNT"   => $itemBalAmount
            );
        }

        $lastItem = $itemKey;
        $lastItemCode = $r["PART_NUM"];
        $lastItemName = $r["PART_NAME"];

        $itemOrderQty = 0;
        $itemOrderAmount = 0;
        $itemDelvQty = 0;
        $itemDelvAmount = 0;
        $itemBalQty = 0;
        $itemBalAmount = 0;
    }

    $printRows[] = array(
        "ROW_TYPE"       => "DETAIL",
        "PART_NUM"       => $r["PART_NUM"],
        "PART_NO"        => $r["PART_NO"],
        "PART_NAME"      => $r["PART_NAME"],
        "ORDR_PO"        => $r["ORDR_PO"],
        "ORDR_DATE"      => $r["ORDR_DATE"],
        "UNIT_PRICE_USD" => $r["UNIT_PRICE_USD"],
        "CURR"           => $r["CURR"],
        "ORDP_QTY"       => $r["ORDP_QTY"],
        "AMOUNT_USD"     => $r["AMOUNT_USD"],
        "ORDP_DQTY"      => $r["ORDP_DQTY"],
        "DAMOUNT_USD"    => $r["DAMOUNT_USD"],
        "ORDP_BQTY"      => $r["ORDP_BQTY"],
        "BAMOUNT_USD"    => $r["BAMOUNT_USD"]
    );

    $itemOrderQty += $r["ORDP_QTY"];
    $itemOrderAmount += $r["AMOUNT_USD"];
    $itemDelvQty += $r["ORDP_DQTY"];
    $itemDelvAmount += $r["DAMOUNT_USD"];
    $itemBalQty += $r["ORDP_BQTY"];
    $itemBalAmount += $r["BAMOUNT_USD"];

    $custOrderAmount += $r["AMOUNT_USD"];
    $custDelvAmount  += $r["DAMOUNT_USD"];
    $custBalAmount   += $r["BAMOUNT_USD"];

    $grandOrderAmount += $r["AMOUNT_USD"];
    $grandDelvAmount  += $r["DAMOUNT_USD"];
    $grandBalAmount   += $r["BAMOUNT_USD"];
}

if ($lastItem != "") {
    $printRows[] = array(
        "ROW_TYPE"     => "ITEM_TOTAL",
        "PART_NUM"     => $lastItemCode,
        "PART_NAME"    => $lastItemName,
        "ORDER_QTY"    => $itemOrderQty,
        "ORDER_AMOUNT" => $itemOrderAmount,
        "DELIV_QTY"    => $itemDelvQty,
        "DELIV_AMOUNT" => $itemDelvAmount,
        "BAL_QTY"      => $itemBalQty,
        "BAL_AMOUNT"   => $itemBalAmount
    );
}

if ($lastCust != "") {
    $printRows[] = array(
        "ROW_TYPE"     => "CUSTOMER_TOTAL",
        "CUST_CODE"    => $lastCustCode,
        "CUST_COMP"    => $lastCustComp,
        "ORDER_AMOUNT" => $custOrderAmount,
        "DELIV_AMOUNT" => $custDelvAmount,
        "BAL_AMOUNT"   => $custBalAmount
    );
}

if (count($rows) > 0) {
    $printRows[] = array(
        "ROW_TYPE"     => "GRAND_TOTAL",
        "ORDER_AMOUNT" => $grandOrderAmount,
        "DELIV_AMOUNT" => $grandDelvAmount,
        "BAL_AMOUNT"   => $grandBalAmount
    );
}

if (count($printRows) == 0) {
    $printRows[] = array(
        "ROW_TYPE" => "EMPTY",
        "MESSAGE"  => "Data outstanding customer order tidak ditemukan."
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
    <title>Outstanding Customer Order</title>

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
            margin-bottom: 20px;
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
            font-size: 16px;
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

        .right-info {
            width: 30%;
            text-align: right;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 17px;
        }

        .print-date {
            text-align: right;
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin-bottom: 4px;
        }

        .order-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .order-table th,
        .order-table td {
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

        .order-table thead th {
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            font-weight: bold;
            text-align: center;
        }

        .part-title {
            letter-spacing: 8px;
            text-align: left !important;
        }

        .customer-row td {
            font-weight: bold;
            font-size: 11px;
            padding-top: 4px;
        }
.item-total-row td {
    font-weight: bold;
    border-top: 1px solid #000000;
}
        .total-row td {
            font-weight: bold;
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
        }

        .grand-row td {
            font-weight: bold;
            border-top: 2px solid #000000;
            border-bottom: 2px solid #000000;
            background: #eeeeee;
        }

        .num {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .col-part {
            width: 27%;
        }

        .col-po {
            width: 13%;
        }

        .col-date {
            width: 8%;
        }

        .col-price {
            width: 8%;
        }

        .col-curr {
            width: 5%;
        }

        .col-qty {
            width: 7%;
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
                    <div class="report-title">OUTSTANDING CUSTOMER ORDER</div>
                </td>

                <td class="right-info">
                    FM.CO.00-03<br>
                    Page 0 of 0
                </td>
            </tr>
        </table>

        <div class="no-data">
            Data belum ditampilkan.<br><br>
            Isi Customer lalu klik <b>FILTER</b>.<br>
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
                    <div class="report-title">OUTSTANDING CUSTOMER ORDER</div>
                </td>

                <td class="right-info">
                    FM.CO.00-03<br>
                    Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?>
                </td>
            </tr>
        </table>

        <div class="print-date">
            Print Date: <?php echo h(fmt_print_date()); ?>
        </div>

        <table class="order-table">
            <thead>
                <tr>
                    <th class="col-part part-title">P A R T</th>
                    <th class="col-po">PO #</th>
                    <th class="col-date">PO DATE</th>
                    <th class="col-price">Unit Price</th>
                    <th class="col-curr">Curr</th>
                    <th class="col-qty">Order Qty</th>
                    <th class="col-amt">Amount</th>
                    <th class="col-qty">D.Qty</th>
                    <th class="col-amt">D Amount</th>
                    <th class="col-qty">B.Qty</th>
                    <th class="col-amt">B.Amount</th>
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
                            <td class="col-part">
                                <?php echo h($r["PART_NUM"]); ?>
                                &nbsp;
                                <?php echo h($r["PART_NAME"]); ?>
                            </td>

                            <td class="col-po"><?php echo h($r["ORDR_PO"]); ?></td>
                            <td class="col-date"><?php echo h(fmt_date($r["ORDR_DATE"])); ?></td>
                            <td class="col-price num"><?php echo h(fmt_price($r["UNIT_PRICE_USD"])); ?></td>
                            <td class="col-curr center"><?php echo h($r["CURR"]); ?></td>

                            <td class="col-qty num"><?php echo h(fmt_num($r["ORDP_QTY"], 0)); ?></td>
                            <td class="col-amt num"><?php echo h(fmt_amount($r["AMOUNT_USD"])); ?></td>

                            <td class="col-qty num"><?php echo h(fmt_num($r["ORDP_DQTY"], 0)); ?></td>
                            <td class="col-amt num"><?php echo h(fmt_amount($r["DAMOUNT_USD"])); ?></td>

                            <td class="col-qty num"><?php echo h(fmt_num($r["ORDP_BQTY"], 0)); ?></td>
                            <td class="col-amt num"><?php echo h(fmt_amount($r["BAMOUNT_USD"])); ?></td>
                        </tr>
                    
					<?php } elseif ($r["ROW_TYPE"] == "ITEM_TOTAL") { ?>
    <tr class="item-total-row">
        <td colspan="5" class="num">
            TOTAL ITEM
            <?php echo h($r["PART_NUM"]); ?>
            -
            <?php echo h($r["PART_NAME"]); ?>
        </td>

        <td class="col-qty num"><?php echo h(fmt_num($r["ORDER_QTY"], 0)); ?></td>
        <td class="col-amt num"><?php echo h(fmt_amount($r["ORDER_AMOUNT"])); ?></td>

        <td class="col-qty num"><?php echo h(fmt_num($r["DELIV_QTY"], 0)); ?></td>
        <td class="col-amt num"><?php echo h(fmt_amount($r["DELIV_AMOUNT"])); ?></td>

        <td class="col-qty num"><?php echo h(fmt_num($r["BAL_QTY"], 0)); ?></td>
        <td class="col-amt num"><?php echo h(fmt_amount($r["BAL_AMOUNT"])); ?></td>
    </tr>
					
					<?php } elseif ($r["ROW_TYPE"] == "CUSTOMER_TOTAL") { ?>
                        <tr class="total-row">
                            <td colspan="6" class="num">
                                Total <?php echo h($r["CUST_CODE"]); ?> - <?php echo h($r["CUST_COMP"]); ?>
                            </td>
                            <td class="num"><?php echo h(fmt_amount($r["ORDER_AMOUNT"])); ?></td>
                            <td></td>
                            <td class="num"><?php echo h(fmt_amount($r["DELIV_AMOUNT"])); ?></td>
                            <td></td>
                            <td class="num"><?php echo h(fmt_amount($r["BAL_AMOUNT"])); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "GRAND_TOTAL") { ?>
                        <tr class="grand-row">
                            <td colspan="6" class="num">Grand Total: USD</td>
                            <td class="num"><?php echo h(fmt_amount($r["ORDER_AMOUNT"])); ?></td>
                            <td></td>
                            <td class="num"><?php echo h(fmt_amount($r["DELIV_AMOUNT"])); ?></td>
                            <td></td>
                            <td class="num"><?php echo h(fmt_amount($r["BAL_AMOUNT"])); ?></td>
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

    if (custCode == "") {
        alert("Customer belum diisi. Isi kode customer atau % untuk semua.");
        document.getElementById("CUST_CODE").focus();
        return;
    }

    window.location =
        "outstanding_customer_order_export_excel.php" +
        "?CUST_CODE=" + enc(custCode);
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
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

function fmt_print_datetime() {
    return date("d-M-Y H:i:s");
}

function excel_num($value, $decimal = 0) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, $decimal, ".", "");
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

$asper_month = get_param("ASPER_MONTH", "");
$cust_code   = get_param("CUST_CODE", "");

if ($asper_month == "") {
    die("Month belum dipilih.");
}

if ($cust_code == "") {
    die("Customer belum diisi.");
}

$asper_ymd = month_to_yyyymmdd($asper_month);

if ($asper_ymd == "") {
    die("Month tidak valid.");
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
    die("<pre>Query Delivery History Summary gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$summaryMap = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $qty = isset($r["QTY"]) ? (float)$r["QTY"] : 0;
    $price = isset($r["PART_PRICE"]) ? (float)$r["PART_PRICE"] : 0;
    $amount = isset($r["AMOUNT"]) ? (float)$r["AMOUNT"] : ($qty * $price);
    $usdFactor = get_usd_factor($r);
    $usdAmount = $amount * $usdFactor;

    $custCode = safe_trim($r["CUST_CODE"]);
    $custComp = safe_trim($r["CUST_COMP"]);
    $partNum  = safe_trim($r["PART_NUM"]);
    $partName = safe_trim($r["PART_NAME"]);
    $curr     = safe_trim($r["CURR"]);

    $key = $custCode . "|" . $custComp . "|" . $partNum . "|" . $partName . "|" . $price . "|" . $curr;

    if (!isset($summaryMap[$key])) {
        $summaryMap[$key] = array(
            "CUST_CODE"  => $custCode,
            "CUST_COMP"  => $custComp,
            "PART_NUM"   => $partNum,
            "PART_NAME"  => $partName,
            "PART_PRICE" => $price,
            "CURR"       => $curr,
            "QTY"        => 0,
            "AMOUNT"     => 0,
            "USD_AMT"    => 0
        );
    }

    $summaryMap[$key]["QTY"] += $qty;
    $summaryMap[$key]["AMOUNT"] += $amount;
    $summaryMap[$key]["USD_AMT"] += $usdAmount;
}

$summaryRows = array();

foreach ($summaryMap as $row) {
    $summaryRows[] = $row;
}

usort($summaryRows, function ($a, $b) {
    if ($a["CUST_CODE"] == $b["CUST_CODE"]) {
        return strcmp($a["PART_NUM"], $b["PART_NUM"]);
    }

    return strcmp($a["CUST_CODE"], $b["CUST_CODE"]);
});

$fileCust = $cust_code == "%" ? "ALL" : $cust_code;
$fileName = "delivery_history_summary_" . $fileCust . "_" . $asper_month . "_" . date("Ymd_His") . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery History Summary Export</title>

    <style>
        table {
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 10pt;
        }

        th {
            background: #d9eaf7;
            font-weight: bold;
            border: 1px solid #000000;
            text-align: center;
        }

        td {
            border: 1px solid #000000;
            vertical-align: top;
        }

        .title {
            font-size: 16pt;
            font-weight: bold;
            text-align: center;
        }

        .text {
            mso-number-format: "\@";
        }

        .num {
            mso-number-format: "#,##0";
            text-align: right;
        }

        .price {
            mso-number-format: "0.0000";
            text-align: right;
        }

        .money {
            mso-number-format: "#,##0.00";
            text-align: right;
        }

        .customer-row,
        .subtotal-row {
            background: #eeeeee;
            font-weight: bold;
        }

        .grand-row {
            background: #d9eaf7;
            font-weight: bold;
        }
    </style>
</head>

<body>

<table>
    <tr>
        <td colspan="10" class="title">DELIVERY HISTORY SUMMARY</td>
    </tr>

    <tr>
        <td colspan="10">P.T. IMC TEKNO INDONESIA - PPIC Department</td>
    </tr>

    <tr>
        <td colspan="10">As Per: <?php echo h($asper_month); ?></td>
    </tr>

    <tr>
        <td colspan="10">
            Customer:
            <?php echo h($cust_code == "%" ? "ALL CUSTOMER" : $cust_code); ?>
        </td>
    </tr>

    <tr>
        <td colspan="10">
            Export Date:
            <?php echo h(fmt_print_datetime()); ?>
        </td>
    </tr>

    <tr>
        <td colspan="10">&nbsp;</td>
    </tr>

    <tr>
        <th>No</th>
        <th>Customer Code</th>
        <th>Customer Name</th>
        <th>Item Code</th>
        <th>Item Name</th>
        <th>Price</th>
        <th>Curr</th>
        <th>Qty</th>
        <th>Amount</th>
        <th>USD.Amt</th>
    </tr>

    <?php if (count($summaryRows) == 0) { ?>
        <tr>
            <td colspan="10">Data delivery history summary tidak ditemukan.</td>
        </tr>
    <?php } ?>

    <?php
    $lastCust = "";
    $lastCustCode = "";
    $lastCustComp = "";

    $rowNo = 0;

    $custQty = 0;
    $custAmount = 0;
    $custUsdAmount = 0;

    $grandQty = 0;
    $grandAmount = 0;
    $grandUsdAmount = 0;

    for ($i = 0; $i < count($summaryRows); $i++) {
        $r = $summaryRows[$i];

        $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

        if ($custKey != $lastCust) {
            if ($lastCust != "") {
                ?>
                <tr class="subtotal-row">
                    <td colspan="7" style="text-align:right;">Subtotal CUSTOMER</td>
                    <td class="num"><?php echo h(excel_num($custQty, 0)); ?></td>
                    <td class="money"><?php echo h(excel_num($custAmount, 2)); ?></td>
                    <td class="money"><?php echo h(excel_num($custUsdAmount, 2)); ?></td>
                </tr>
                <?php
            }

            ?>
            <tr class="customer-row">
                <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
                <td colspan="9"><?php echo h($r["CUST_COMP"]); ?></td>
            </tr>
            <?php

            $lastCust = $custKey;
            $lastCustCode = $r["CUST_CODE"];
            $lastCustComp = $r["CUST_COMP"];

            $rowNo = 0;
            $custQty = 0;
            $custAmount = 0;
            $custUsdAmount = 0;
        }

        $rowNo++;

        $custQty += $r["QTY"];
        $custAmount += $r["AMOUNT"];
        $custUsdAmount += $r["USD_AMT"];

        $grandQty += $r["QTY"];
        $grandAmount += $r["AMOUNT"];
        $grandUsdAmount += $r["USD_AMT"];
        ?>

        <tr>
            <td class="num"><?php echo h($rowNo); ?></td>
            <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
            <td class="text"><?php echo h($r["CUST_COMP"]); ?></td>
            <td class="text"><?php echo h($r["PART_NUM"]); ?></td>
            <td class="text"><?php echo h($r["PART_NAME"]); ?></td>
            <td class="price"><?php echo h(excel_num($r["PART_PRICE"], 4)); ?></td>
            <td class="text"><?php echo h($r["CURR"]); ?></td>
            <td class="num"><?php echo h(excel_num($r["QTY"], 0)); ?></td>
            <td class="money"><?php echo h(excel_num($r["AMOUNT"], 2)); ?></td>
            <td class="money"><?php echo h(excel_num($r["USD_AMT"], 2)); ?></td>
        </tr>

    <?php } ?>

    <?php if ($lastCust != "") { ?>
        <tr class="subtotal-row">
            <td colspan="7" style="text-align:right;">Subtotal CUSTOMER</td>
            <td class="num"><?php echo h(excel_num($custQty, 0)); ?></td>
            <td class="money"><?php echo h(excel_num($custAmount, 2)); ?></td>
            <td class="money"><?php echo h(excel_num($custUsdAmount, 2)); ?></td>
        </tr>

        <tr class="grand-row">
            <td colspan="7" style="text-align:right;">GRAND TOTAL</td>
            <td class="num"><?php echo h(excel_num($grandQty, 0)); ?></td>
            <td class="money"><?php echo h(excel_num($grandAmount, 2)); ?></td>
            <td class="money"><?php echo h(excel_num($grandUsdAmount, 2)); ?></td>
        </tr>
    <?php } ?>

</table>

</body>
</html>
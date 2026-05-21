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

function excel_num($value, $decimal = 0) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, $decimal, ".", "");
}

function fmt_print_date() {
    return date("d-M-Y H:i:s");
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

$cust_code = get_param("CUST_CODE", "");

if ($cust_code == "") {
    die("Customer belum diisi.");
}

$sql = "
    SET NOCOUNT ON;
    EXEC dbo.SP_OUTSTANDING_ORDER ?
";

$stmt = sqlsrv_query($conn, $sql, array($cust_code));

if ($stmt === false) {
    die("<pre>Query Outstanding Customer Order gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $factor = calc_usd_factor($r);

    $unitPriceOri = isset($r["ORDP_PRICE"]) ? (float)$r["ORDP_PRICE"] : 0;

    $orderQty = isset($r["ORDP_QTY"]) ? (float)$r["ORDP_QTY"] : 0;
    $delvQty  = isset($r["ORDP_DQTY"]) ? (float)$r["ORDP_DQTY"] : 0;
    $balQty   = isset($r["ORDP_BQTY"]) ? (float)$r["ORDP_BQTY"] : 0;

    $unitPriceUsd = $unitPriceOri * $factor;

    $rows[] = array(
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

$fileCust = $cust_code == "%" ? "ALL" : $cust_code;
$fileName = "outstanding_customer_order_" . $fileCust . "_" . date("Ymd_His") . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Outstanding Customer Order Export</title>

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

        .money {
            mso-number-format: "#,##0.00";
            text-align: right;
        }

        .price {
            mso-number-format: "0.00";
            text-align: right;
        }

        .customer-row {
            background: #eeeeee;
            font-weight: bold;
        }

        .item-total-row {
            background: #f7f7f7;
            font-weight: bold;
        }

        .total-row {
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
        <td colspan="15" class="title">OUTSTANDING CUSTOMER ORDER</td>
    </tr>

    <tr>
        <td colspan="15">P.T. IMC TEKNO INDONESIA - PPIC Department</td>
    </tr>

    <tr>
        <td colspan="15">
            Customer:
            <?php echo h($cust_code == "%" ? "ALL CUSTOMER" : $cust_code); ?>
        </td>
    </tr>

    <tr>
        <td colspan="15">
            Export Date:
            <?php echo h(fmt_print_date()); ?>
        </td>
    </tr>

    <tr>
        <td colspan="15">&nbsp;</td>
    </tr>

    <tr>
        <th>Customer Code</th>
        <th>Customer Name</th>
        <th>Part Code</th>
        <th>Part No</th>
        <th>Part Name</th>
        <th>PO #</th>
        <th>PO Date</th>
        <th>Unit Price USD</th>
        <th>Curr</th>
        <th>Order Qty</th>
        <th>Amount USD</th>
        <th>D.Qty</th>
        <th>D Amount USD</th>
        <th>B.Qty</th>
        <th>B Amount USD</th>
    </tr>

    <?php if (count($rows) == 0) { ?>
        <tr>
            <td colspan="15">Data outstanding customer order tidak ditemukan.</td>
        </tr>
    <?php } ?>

    <?php
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
    $custDelvAmount = 0;
    $custBalAmount = 0;

    $grandOrderAmount = 0;
    $grandDelvAmount = 0;
    $grandBalAmount = 0;

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];

        $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];
        $itemKey = $r["PART_NUM"] . "|" . $r["PART_NO"] . "|" . $r["PART_NAME"];

        if ($custKey != $lastCust) {
            if ($lastItem != "") {
                ?>
                <tr class="item-total-row">
                    <td colspan="9" style="text-align:right;">
                        TOTAL ITEM <?php echo h($lastItemCode); ?> - <?php echo h($lastItemName); ?>
                    </td>
                    <td class="num"><?php echo h(excel_num($itemOrderQty, 0)); ?></td>
                    <td class="money"><?php echo h(excel_num($itemOrderAmount, 2)); ?></td>
                    <td class="num"><?php echo h(excel_num($itemDelvQty, 0)); ?></td>
                    <td class="money"><?php echo h(excel_num($itemDelvAmount, 2)); ?></td>
                    <td class="num"><?php echo h(excel_num($itemBalQty, 0)); ?></td>
                    <td class="money"><?php echo h(excel_num($itemBalAmount, 2)); ?></td>
                </tr>
                <?php
            }

            if ($lastCust != "") {
                ?>
                <tr class="total-row">
                    <td colspan="10" style="text-align:right;">
                        TOTAL CUSTOMER <?php echo h($lastCustCode); ?> - <?php echo h($lastCustComp); ?>
                    </td>
                    <td class="money"><?php echo h(excel_num($custOrderAmount, 2)); ?></td>
                    <td></td>
                    <td class="money"><?php echo h(excel_num($custDelvAmount, 2)); ?></td>
                    <td></td>
                    <td class="money"><?php echo h(excel_num($custBalAmount, 2)); ?></td>
                </tr>
                <?php
            }

            ?>
            <tr class="customer-row">
                <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
                <td colspan="14"><?php echo h($r["CUST_COMP"]); ?></td>
            </tr>
            <?php

            $lastCust = $custKey;
            $lastCustCode = $r["CUST_CODE"];
            $lastCustComp = $r["CUST_COMP"];

            $custOrderAmount = 0;
            $custDelvAmount = 0;
            $custBalAmount = 0;

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
                ?>
                <tr class="item-total-row">
                    <td colspan="9" style="text-align:right;">
                        TOTAL ITEM <?php echo h($lastItemCode); ?> - <?php echo h($lastItemName); ?>
                    </td>
                    <td class="num"><?php echo h(excel_num($itemOrderQty, 0)); ?></td>
                    <td class="money"><?php echo h(excel_num($itemOrderAmount, 2)); ?></td>
                    <td class="num"><?php echo h(excel_num($itemDelvQty, 0)); ?></td>
                    <td class="money"><?php echo h(excel_num($itemDelvAmount, 2)); ?></td>
                    <td class="num"><?php echo h(excel_num($itemBalQty, 0)); ?></td>
                    <td class="money"><?php echo h(excel_num($itemBalAmount, 2)); ?></td>
                </tr>
                <?php
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

        $itemOrderQty += $r["ORDP_QTY"];
        $itemOrderAmount += $r["AMOUNT_USD"];
        $itemDelvQty += $r["ORDP_DQTY"];
        $itemDelvAmount += $r["DAMOUNT_USD"];
        $itemBalQty += $r["ORDP_BQTY"];
        $itemBalAmount += $r["BAMOUNT_USD"];

        $custOrderAmount += $r["AMOUNT_USD"];
        $custDelvAmount += $r["DAMOUNT_USD"];
        $custBalAmount += $r["BAMOUNT_USD"];

        $grandOrderAmount += $r["AMOUNT_USD"];
        $grandDelvAmount += $r["DAMOUNT_USD"];
        $grandBalAmount += $r["BAMOUNT_USD"];
        ?>

        <tr>
            <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
            <td class="text"><?php echo h($r["CUST_COMP"]); ?></td>
            <td class="text"><?php echo h($r["PART_NUM"]); ?></td>
            <td class="text"><?php echo h($r["PART_NO"]); ?></td>
            <td class="text"><?php echo h($r["PART_NAME"]); ?></td>
            <td class="text"><?php echo h($r["ORDR_PO"]); ?></td>
            <td class="text"><?php echo h(fmt_date($r["ORDR_DATE"])); ?></td>
            <td class="price"><?php echo h(excel_num($r["UNIT_PRICE_USD"], 2)); ?></td>
            <td class="text"><?php echo h($r["CURR"]); ?></td>
            <td class="num"><?php echo h(excel_num($r["ORDP_QTY"], 0)); ?></td>
            <td class="money"><?php echo h(excel_num($r["AMOUNT_USD"], 2)); ?></td>
            <td class="num"><?php echo h(excel_num($r["ORDP_DQTY"], 0)); ?></td>
            <td class="money"><?php echo h(excel_num($r["DAMOUNT_USD"], 2)); ?></td>
            <td class="num"><?php echo h(excel_num($r["ORDP_BQTY"], 0)); ?></td>
            <td class="money"><?php echo h(excel_num($r["BAMOUNT_USD"], 2)); ?></td>
        </tr>

    <?php } ?>

    <?php if ($lastItem != "") { ?>
        <tr class="item-total-row">
            <td colspan="9" style="text-align:right;">
                TOTAL ITEM <?php echo h($lastItemCode); ?> - <?php echo h($lastItemName); ?>
            </td>
            <td class="num"><?php echo h(excel_num($itemOrderQty, 0)); ?></td>
            <td class="money"><?php echo h(excel_num($itemOrderAmount, 2)); ?></td>
            <td class="num"><?php echo h(excel_num($itemDelvQty, 0)); ?></td>
            <td class="money"><?php echo h(excel_num($itemDelvAmount, 2)); ?></td>
            <td class="num"><?php echo h(excel_num($itemBalQty, 0)); ?></td>
            <td class="money"><?php echo h(excel_num($itemBalAmount, 2)); ?></td>
        </tr>
    <?php } ?>

    <?php if ($lastCust != "") { ?>
        <tr class="total-row">
            <td colspan="10" style="text-align:right;">
                TOTAL CUSTOMER <?php echo h($lastCustCode); ?> - <?php echo h($lastCustComp); ?>
            </td>
            <td class="money"><?php echo h(excel_num($custOrderAmount, 2)); ?></td>
            <td></td>
            <td class="money"><?php echo h(excel_num($custDelvAmount, 2)); ?></td>
            <td></td>
            <td class="money"><?php echo h(excel_num($custBalAmount, 2)); ?></td>
        </tr>

        <tr class="grand-row">
            <td colspan="10" style="text-align:right;">
                GRAND TOTAL USD
            </td>
            <td class="money"><?php echo h(excel_num($grandOrderAmount, 2)); ?></td>
            <td></td>
            <td class="money"><?php echo h(excel_num($grandDelvAmount, 2)); ?></td>
            <td></td>
            <td class="money"><?php echo h(excel_num($grandBalAmount, 2)); ?></td>
        </tr>
    <?php } ?>

</table>

</body>
</html>
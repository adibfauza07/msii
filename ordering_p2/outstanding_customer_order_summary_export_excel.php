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

function excel_num($value, $decimal = 0) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, $decimal, ".", "");
}

function fmt_print_datetime() {
    return date("d-M-Y H:i:s");
}

$cust_code = get_param("CUST_CODE", "");

if ($cust_code == "") {
    die("Customer belum diisi.");
}

$sql = "
    SET NOCOUNT ON;
    EXEC dbo.SP_OUTSTANDING_ORDER_SUM ?
";

$stmt = sqlsrv_query($conn, $sql, array($cust_code));

if ($stmt === false) {
    die("<pre>Query Outstanding Customer Order Summary gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = array(
        "CURR_CODE"  => safe_trim($r["CURR_CODE"]),
        "TQTY"       => isset($r["TQTY"]) ? (float)$r["TQTY"] : 0,
        "TDQTY"      => isset($r["TDQTY"]) ? (float)$r["TDQTY"] : 0,
        "TBQTY"      => isset($r["TBQTY"]) ? (float)$r["TBQTY"] : 0,
        "ORDP_PRICE" => isset($r["ORDP_PRICE"]) ? (float)$r["ORDP_PRICE"] : 0,
        "CUST_COMP"  => safe_trim($r["CUST_COMP"]),
        "TAMT"       => isset($r["TAMT"]) ? (float)$r["TAMT"] : 0,
        "TDAMT"      => isset($r["TDAMT"]) ? (float)$r["TDAMT"] : 0,
        "TBAMT"      => isset($r["TBAMT"]) ? (float)$r["TBAMT"] : 0,
        "CUST_CODE"  => safe_trim($r["CUST_CODE"]),
        "PART_CODE"  => safe_trim($r["PART_CODE"]),
        "PART_NUM"   => safe_trim($r["PART_NUM"]),
        "PART_NAME"  => safe_trim($r["PART_NAME"])
    );
}

$fileCust = $cust_code == "%" ? "ALL" : $cust_code;
$fileName = "outstanding_customer_order_summary_" . $fileCust . "_" . date("Ymd_His") . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Outstanding Customer Order Summary Export</title>

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
            mso-number-format: "0.00000";
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
        <td colspan="12" class="title">OUTSTANDING CUSTOMER ORDER SUMMARY</td>
    </tr>

    <tr>
        <td colspan="12">P.T. IMC TEKNO INDONESIA - PPIC Department</td>
    </tr>

    <tr>
        <td colspan="12">
            Customer:
            <?php echo h($cust_code == "%" ? "ALL CUSTOMER" : $cust_code); ?>
        </td>
    </tr>

    <tr>
        <td colspan="12">
            Export Date:
            <?php echo h(fmt_print_datetime()); ?>
        </td>
    </tr>

    <tr>
        <td colspan="12">&nbsp;</td>
    </tr>

    <tr>
        <th rowspan="2">Customer Code</th>
        <th rowspan="2">Customer Name</th>
        <th rowspan="2">Part Code</th>
        <th rowspan="2">Part Name</th>
        <th rowspan="2">Unit Price</th>
        <th rowspan="2">Curr</th>
        <th colspan="2">PO</th>
        <th colspan="2">Delivered</th>
        <th colspan="2">Balance</th>
    </tr>

    <tr>
        <th>Qty</th>
        <th>Amount</th>
        <th>Qty</th>
        <th>Amount</th>
        <th>Qty</th>
        <th>Amount</th>
    </tr>

    <?php if (count($rows) == 0) { ?>
        <tr>
            <td colspan="12">Data outstanding customer order summary tidak ditemukan.</td>
        </tr>
    <?php } ?>

    <?php
    $lastCust = "";

    $subTamt = 0;
    $subTdamt = 0;
    $subTbamt = 0;

    $grandTamt = 0;
    $grandTdamt = 0;
    $grandTbamt = 0;

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];

        $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

        if ($custKey != $lastCust) {
            if ($lastCust != "") {
                ?>
                <tr class="subtotal-row">
                    <td colspan="7" style="text-align:right;">Sub Total:</td>
                    <td class="money"><?php echo h(excel_num($subTamt, 2)); ?></td>
                    <td></td>
                    <td class="money"><?php echo h(excel_num($subTdamt, 2)); ?></td>
                    <td></td>
                    <td class="money"><?php echo h(excel_num($subTbamt, 2)); ?></td>
                </tr>
                <?php
            }

            ?>
            <tr class="customer-row">
                <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
                <td colspan="11"><?php echo h($r["CUST_COMP"]); ?></td>
            </tr>
            <?php

            $lastCust = $custKey;
            $subTamt = 0;
            $subTdamt = 0;
            $subTbamt = 0;
        }

        $subTamt += $r["TAMT"];
        $subTdamt += $r["TDAMT"];
        $subTbamt += $r["TBAMT"];

        $grandTamt += $r["TAMT"];
        $grandTdamt += $r["TDAMT"];
        $grandTbamt += $r["TBAMT"];
        ?>

        <tr>
            <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
            <td class="text"><?php echo h($r["CUST_COMP"]); ?></td>
            <td class="text"><?php echo h($r["PART_NUM"]); ?></td>
            <td class="text"><?php echo h($r["PART_NAME"]); ?></td>
            <td class="price"><?php echo h(excel_num($r["ORDP_PRICE"], 5)); ?></td>
            <td class="text"><?php echo h($r["CURR_CODE"]); ?></td>

            <td class="num"><?php echo h(excel_num($r["TQTY"], 0)); ?></td>
            <td class="money"><?php echo h(excel_num($r["TAMT"], 2)); ?></td>

            <td class="num"><?php echo h(excel_num($r["TDQTY"], 0)); ?></td>
            <td class="money"><?php echo h(excel_num($r["TDAMT"], 2)); ?></td>

            <td class="num"><?php echo h(excel_num($r["TBQTY"], 0)); ?></td>
            <td class="money"><?php echo h(excel_num($r["TBAMT"], 2)); ?></td>
        </tr>

    <?php } ?>

    <?php if ($lastCust != "") { ?>
        <tr class="subtotal-row">
            <td colspan="7" style="text-align:right;">Sub Total:</td>
            <td class="money"><?php echo h(excel_num($subTamt, 2)); ?></td>
            <td></td>
            <td class="money"><?php echo h(excel_num($subTdamt, 2)); ?></td>
            <td></td>
            <td class="money"><?php echo h(excel_num($subTbamt, 2)); ?></td>
        </tr>

        <tr class="grand-row">
            <td colspan="7" style="text-align:right;">Grand Total:</td>
            <td class="money"><?php echo h(excel_num($grandTamt, 2)); ?></td>
            <td></td>
            <td class="money"><?php echo h(excel_num($grandTdamt, 2)); ?></td>
            <td></td>
            <td class="money"><?php echo h(excel_num($grandTbamt, 2)); ?></td>
        </tr>
    <?php } ?>

</table>

</body>
</html>
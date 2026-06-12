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

function fmt_print_datetime() {
    return date("d-M-Y H:i:s");
}

function excel_num($value, $decimal = 0) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, $decimal, ".", "");
}

$asper = get_param("ASPER", "");

if ($asper == "") {
    die("Tanggal belum diisi.");
}

$asper_ymd = ymd_param($asper);

if ($asper_ymd == "") {
    die("Tanggal tidak valid.");
}

$sql = "
    SET NOCOUNT ON;
    EXEC dbo.SP_INVOICE_DAYLIST1 ?
";

$stmt = sqlsrv_query($conn, $sql, array($asper_ymd));

if ($stmt === false) {
    die("<pre>Query Daily Invoice List gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $qty = isset($r["QTY"]) ? (float)$r["QTY"] : 0;

    if (isset($r["PART_PRICE"]) && $r["PART_PRICE"] !== null) {
        $price = (float)$r["PART_PRICE"];
    } elseif (isset($r["ORDP_PRICE"]) && $r["ORDP_PRICE"] !== null) {
        $price = (float)$r["ORDP_PRICE"];
    } else {
        $price = 0;
    }

    $rows[] = array(
        "CUST_COMP"  => safe_trim($r["CUST_COMP"]),
        "PART_NAME"  => safe_trim($r["PART_NAME"]),
        "PART_NO"    => safe_trim($r["PART_NO"]),
        "PART_CODE"  => safe_trim($r["PART_CODE"]),
        "ORDR_PO"    => safe_trim($r["ORDR_PO"]),
        "DI_INVNO"   => safe_trim($r["DI_INVNO"]),
        "DI_DATE"    => $r["DI_DATE"],
        "PRICE"      => $price,
        "QTY"        => $qty,
        "AMOUNT"     => $qty * $price,
        "PART_UNIT"  => safe_trim($r["PART_UNIT"]),
        "CURR_CODE"  => safe_trim($r["CURR_CODE"]),
        "PRICE_CODE" => safe_trim($r["PRICE_CODE"])
    );
}

$fileName = "daily_invoice_list_" . $asper_ymd . "_" . date("Ymd_His") . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Invoice List Export</title>

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
            text-align: left;
        }

        .price {
            mso-number-format: "0.0000";
            text-align: left;
        }

        .money {
            mso-number-format: "#,##0.00";
            text-align: left;
        }

        .customer-row {
            background: #eeeeee;
            font-weight: bold;
        }

        .customer-total-row {
            background: #f7f7f7;
            font-weight: bold;
        }
    </style>
</head>

<body>

<table>
    <tr>
        <td colspan="12" class="title">DAILY INVOICE LIST</td>
    </tr>

    <tr>
        <td colspan="12">P.T. IMC TEKNO INDONESIA - PPIC Department</td>
    </tr>

    <tr>
        <td colspan="12">As per: <?php echo h($asper); ?></td>
    </tr>

    <tr>
        <td colspan="12">Export Date: <?php echo h(fmt_print_datetime()); ?></td>
    </tr>

    <tr>
        <td colspan="12">&nbsp;</td>
    </tr>

    <tr>
        <th>Customer</th>
        <th>INV#</th>
        <th>Date</th>
        <th>Part Code</th>
        <th>Part Name</th>
        <th>Part No</th>
        <th>Price Code</th>
        <th>Qty</th>
        <th>Price</th>
        <th>Curr</th>
        <th>Amount</th>
        <th>PO#</th>
    </tr>

    <?php if (count($rows) == 0) { ?>
        <tr>
            <td colspan="12">Data daily invoice list tidak ditemukan.</td>
        </tr>
    <?php } ?>

    <?php
    $lastCust = "";
    $custQty = 0;
    $custAmount = 0;

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];

        if ($r["CUST_COMP"] != $lastCust) {
            if ($lastCust != "") {
                ?>
                <tr class="customer-total-row">
                    <td colspan="7" style="text-align:right;">TOTAL CUSTOMER</td>
                    <td class="num"><?php echo h(excel_num($custQty, 0)); ?></td>
                    <td></td>
                    <td></td>
                    <td class="money"><?php echo h(excel_num($custAmount, 2)); ?></td>
                    <td></td>
                </tr>
                <?php
            }

            ?>
            <tr class="customer-row">
                <td colspan="12"><?php echo h($r["CUST_COMP"]); ?></td>
            </tr>
            <?php

            $lastCust = $r["CUST_COMP"];
            $custQty = 0;
            $custAmount = 0;
        }

        $custQty += $r["QTY"];
        $custAmount += $r["AMOUNT"];
        ?>

        <tr>
            <td class="text"><?php echo h($r["CUST_COMP"]); ?></td>
            <td class="text"><?php echo h($r["DI_INVNO"]); ?></td>
            <td class="text"><?php echo h(fmt_date($r["DI_DATE"])); ?></td>
            <td class="text"><?php echo h($r["PART_CODE"]); ?></td>
            <td class="text"><?php echo h($r["PART_NAME"]); ?></td>
            <td class="text"><?php echo h($r["PART_NO"]); ?></td>
            <td class="text"><?php echo h($r["PRICE_CODE"]); ?></td>
            <td class="num"><?php echo h(excel_num($r["QTY"], 0)); ?></td>
            <td class="price"><?php echo h(excel_num($r["PRICE"], 4)); ?></td>
            <td class="text"><?php echo h($r["CURR_CODE"]); ?></td>
            <td class="money"><?php echo h(excel_num($r["AMOUNT"], 2)); ?></td>
            <td class="text"><?php echo h($r["ORDR_PO"]); ?></td>
        </tr>

    <?php } ?>

    <?php if ($lastCust != "") { ?>
        <tr class="customer-total-row">
            <td colspan="7" style="text-align:right;">TOTAL CUSTOMER</td>
            <td class="num"><?php echo h(excel_num($custQty, 0)); ?></td>
            <td></td>
            <td></td>
            <td class="money"><?php echo h(excel_num($custAmount, 2)); ?></td>
            <td></td>
        </tr>
    <?php } ?>

</table>

</body>
</html>
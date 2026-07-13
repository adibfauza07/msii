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
    die("<pre>Query Delivery History gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

 $rows = array();
 $monthYear = "";

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if ($monthYear == "" && isset($r["MonthYear"])) {
        $monthYear = safe_trim($r["MonthYear"]);
    }

    $qty = isset($r["QTY"]) ? (float)$r["QTY"] : 0;
    $price = isset($r["PART_PRICE"]) ? (float)$r["PART_PRICE"] : 0;
    $amount = isset($r["AMOUNT"]) ? (float)$r["AMOUNT"] : ($qty * $price);
    $usdFactor = get_usd_factor($r);

    $rows[] = array(
        "CUST_CODE"  => safe_trim($r["CUST_CODE"]),
        "CUST_COMP"  => safe_trim($r["CUST_COMP"]),
        "DI_DATE"    => $r["DI_DATE"],
        "DI_DSNO"    => safe_trim($r["DI_DSNO"]),
        "ORDR_PO"    => safe_trim($r["ORDR_PO"]),
        "PART_NUM"   => safe_trim($r["PART_NUM"]),
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

 $fileCust = $cust_code == "%" ? "ALL" : $cust_code;
 $fileName = "delivery_history_" . $fileCust . "_" . $asper_month . "_" . date("Ymd_His") . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery History Export</title>

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
        .part-total-row {
            background: #eeeeee;
            font-weight: bold;
        }

        .customer-total-row {
            background: #f7f7f7;
            font-weight: bold;
        }

        .grand-row {
            background: #d9eaf7;
            font-weight: bold;
        }

        /* ===== ZEBRA STRIPING - WARNA FONT SAJA ===== */
        
        /* Baris ganjil - Font Merah */
        .row-red {
            color: #CC0000;
        }

        /* Baris genap - Font Biru */
        .row-blue {
            color: #0000CC;
        }
    </style>
</head>

<body>

<table>
    <tr>
        <td colspan="12" class="title">DELIVERY HISTORY</td>
    </tr>

    <tr>
        <td colspan="12">P.T. IMC TEKNO INDONESIA - PPIC Department</td>
    </tr>

    <tr>
        <td colspan="12">As Per: <?php echo h($monthYear); ?></td>
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
        <th>Customer Code</th>
        <th>Customer Name</th>
        <th>Code</th>
        <th>Name</th>
        <th>DS.NO</th>
        <th>Price</th>
        <th>Curr</th>
        <th>Date</th>
        <th>PO #</th>
        <th>Qty</th>
        <th>Amount</th>
        <th>USD.Amt</th>
    </tr>

    <?php if (count($rows) == 0) { ?>
        <tr>
            <td colspan="12">Data delivery history tidak ditemukan.</td>
        </tr>
    <?php } ?>

    <?php
    $lastCust = "";
    $lastCustCode = "";
    $lastCustComp = "";

    $lastPart = "";
    $partQty = 0;
    $partAmount = 0;
    $partUsdAmount = 0;

    $custQty = 0;
    $custAmount = 0;
    $custUsdAmount = 0;

    $grandQty = 0;
    $grandAmount = 0;
    $grandUsdAmount = 0;

    // Counter untuk zebra striping font
    $dataRowCounter = 0;

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];

        $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];
        $partKey = $r["CUST_CODE"] . "|" . $r["PART_NUM"] . "|" . $r["PART_NAME"];

        if ($custKey != $lastCust) {
            if ($lastPart != "") {
                ?>
                <tr class="part-total-row">
                    <td colspan="9" style="text-align:right;">Subtotal PART</td>
                    <td class="num"><?php echo h(excel_num($partQty, 0)); ?></td>
                    <td class="money"><?php echo h(excel_num($partAmount, 2)); ?></td>
                    <td class="money"><?php echo h(excel_num($partUsdAmount, 2)); ?></td>
                </tr>
                <?php
            }

            if ($lastCust != "") {
                ?>
                <tr class="customer-total-row">
                    <td colspan="9" style="text-align:right;">
                        Subtotal CUSTOMER <?php echo h($lastCustCode); ?>
                    </td>
                    <td class="num"><?php echo h(excel_num($custQty, 0)); ?></td>
                    <td class="money"><?php echo h(excel_num($custAmount, 2)); ?></td>
                    <td class="money"><?php echo h(excel_num($custUsdAmount, 2)); ?></td>
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
            $lastCustCode = $r["CUST_CODE"];
            $lastCustComp = $r["CUST_COMP"];

            $lastPart = "";
            $partQty = 0;
            $partAmount = 0;
            $partUsdAmount = 0;

            $custQty = 0;
            $custAmount = 0;
            $custUsdAmount = 0;
            
            // Reset counter saat ganti customer
            $dataRowCounter = 0;
        }

        if ($partKey != $lastPart) {
            if ($lastPart != "") {
                ?>
                <tr class="part-total-row">
                    <td colspan="9" style="text-align:right;">Subtotal PART</td>
                    <td class="num"><?php echo h(excel_num($partQty, 0)); ?></td>
                    <td class="money"><?php echo h(excel_num($partAmount, 2)); ?></td>
                    <td class="money"><?php echo h(excel_num($partUsdAmount, 2)); ?></td>
                </tr>
                <?php
            }

            $lastPart = $partKey;
            $partQty = 0;
            $partAmount = 0;
            $partUsdAmount = 0;
        }

        $partQty += $r["QTY"];
        $partAmount += $r["AMOUNT"];
        $partUsdAmount += $r["USD_AMT"];

        $custQty += $r["QTY"];
        $custAmount += $r["AMOUNT"];
        $custUsdAmount += $r["USD_AMT"];

        $grandQty += $r["QTY"];
        $grandAmount += $r["AMOUNT"];
        $grandUsdAmount += $r["USD_AMT"];

        // Tentukan warna font: merah atau biru
        $rowClass = ($dataRowCounter % 2 == 0) ? "row-red" : "row-blue";
        ?>

        <tr class="<?php echo $rowClass; ?>">
            <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
            <td class="text"><?php echo h($r["CUST_COMP"]); ?></td>
            <td class="text"><?php echo h($r["PART_NUM"]); ?></td>
            <td class="text"><?php echo h($r["PART_NAME"]); ?></td>
            <td class="text"><?php echo h($r["DI_DSNO"]); ?></td>
            <td class="price"><?php echo h(excel_num($r["PART_PRICE"], 4)); ?></td>
            <td class="text"><?php echo h($r["CURR"]); ?></td>
            <td class="text"><?php echo h(fmt_date($r["DI_DATE"])); ?></td>
            <td class="text"><?php echo h($r["ORDR_PO"]); ?></td>
            <td class="num"><?php echo h(excel_num($r["QTY"], 0)); ?></td>
            <td class="money"><?php echo h(excel_num($r["AMOUNT"], 2)); ?></td>
            <td class="money"><?php echo h(excel_num($r["USD_AMT"], 2)); ?></td>
        </tr>

    <?php 
        $dataRowCounter++;
    } ?>

    <?php if ($lastPart != "") { ?>
        <tr class="part-total-row">
            <td colspan="9" style="text-align:right;">Subtotal PART</td>
            <td class="num"><?php echo h(excel_num($partQty, 0)); ?></td>
            <td class="money"><?php echo h(excel_num($partAmount, 2)); ?></td>
            <td class="money"><?php echo h(excel_num($partUsdAmount, 2)); ?></td>
        </tr>
    <?php } ?>

    <?php if ($lastCust != "") { ?>
        <tr class="customer-total-row">
            <td colspan="9" style="text-align:right;">
                Subtotal CUSTOMER <?php echo h($lastCustCode); ?>
            </td>
            <td class="num"><?php echo h(excel_num($custQty, 0)); ?></td>
            <td class="money"><?php echo h(excel_num($custAmount, 2)); ?></td>
            <td class="money"><?php echo h(excel_num($custUsdAmount, 2)); ?></td>
        </tr>

        <tr class="grand-row">
            <td colspan="9" style="text-align:right;">Grand Total</td>
            <td class="num"><?php echo h(excel_num($grandQty, 0)); ?></td>
            <td class="money"><?php echo h(excel_num($grandAmount, 2)); ?></td>
            <td class="money"><?php echo h(excel_num($grandUsdAmount, 2)); ?></td>
        </tr>
    <?php } ?>

</table>

</body>
</html>
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

function usd_factor($currCode, $currRate, $usdRate) {
    $currCode = strtoupper(trim((string)$currCode));

    if ($currCode == "USD") {
        return 1;
    }

    $currRate = (float)$currRate;
    $usdRate  = (float)$usdRate;

    if ($currRate == 0) {
        $currRate = 1;
    }

    if ($usdRate == 0) {
        $usdRate = 1;
    }

    return $currRate / $usdRate;
}

$start_month = get_param("START_MONTH", "");
$cust_code   = get_param("CUST_CODE", "");

if ($start_month == "") {
    die("Starting Month belum dipilih.");
}

if ($cust_code == "") {
    die("Customer belum diisi.");
}

$start_ymd = month_to_yyyymmdd($start_month);

if ($start_ymd == "") {
    die("Starting Month tidak valid.");
}

$sql = "
    SET NOCOUNT ON;
    EXEC dbo.DeliverySum6Month_char ?, ?
";

$stmt = sqlsrv_query($conn, $sql, array($start_ymd, $cust_code));

if ($stmt === false) {
    die("<pre>Query Delivery Summary 6 Month gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();
$months = array("", "", "", "", "", "");

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if ($months[0] == "" && isset($r["Month1"])) {
        for ($m = 1; $m <= 6; $m++) {
            $months[$m - 1] = safe_trim($r["Month" . $m]);
        }
    }

    $currCode = safe_trim($r["CURR_CODE"]);
    $factor = usd_factor($currCode, $r["CURR_VRATE"], $r["USDRATE"]);

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
        $amt = isset($r["AMT" . $m]) ? (float)$r["AMT" . $m] * $factor : 0;

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

$fileCust = $cust_code == "%" ? "ALL" : $cust_code;
$fileName = "delivery_summary_6month_" . $fileCust . "_" . $start_month . "_" . date("Ymd_His") . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Summary 6 Month Export</title>

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
            mso-number-format: "0.00000";
            text-align: right;
        }

        .money {
            mso-number-format: "#,##0.00";
            text-align: right;
        }

        .customer-row,
        .customer-total-row {
            background: #eeeeee;
            font-weight: bold;
        }

        .grand-total-row {
            background: #d9eaf7;
            font-weight: bold;
        }
    </style>
</head>

<body>

<table>
    <tr>
        <td colspan="20" class="title">DELIVERY HISTORY SUMMARY 6 MONTH</td>
    </tr>

    <tr>
        <td colspan="20">P.T. IMC TEKNO INDONESIA - PPIC Department</td>
    </tr>

    <tr>
        <td colspan="20">Starting Month: <?php echo h($start_ymd); ?></td>
    </tr>

    <tr>
        <td colspan="20">
            Customer:
            <?php echo h($cust_code == "%" ? "ALL CUSTOMER" : $cust_code); ?>
        </td>
    </tr>

    <tr>
        <td colspan="20">Export Date: <?php echo h(fmt_print_datetime()); ?></td>
    </tr>

    <tr>
        <td colspan="20">&nbsp;</td>
    </tr>

    <tr>
        <th rowspan="2">Customer Code</th>
        <th rowspan="2">Customer Name</th>
        <th rowspan="2">Item Code</th>
        <th rowspan="2">Item Name</th>
        <th rowspan="2">Price Original</th>
        <th rowspan="2">Curr</th>

        <?php for ($m = 0; $m < 6; $m++) { ?>
            <th colspan="2"><?php echo h($months[$m]); ?></th>
        <?php } ?>

        <th colspan="2">Total</th>
    </tr>

    <tr>
        <?php for ($m = 1; $m <= 6; $m++) { ?>
            <th>Qty</th>
            <th>Amount USD</th>
        <?php } ?>

        <th>Qty</th>
        <th>Amount USD</th>
    </tr>

    <?php if (count($rows) == 0) { ?>
        <tr>
            <td colspan="20">Data delivery summary 6 month tidak ditemukan.</td>
        </tr>
    <?php } ?>

    <?php
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
                ?>
                <tr class="customer-total-row">
                    <td colspan="6" style="text-align:right;">TOTAL CUSTOMER</td>

                    <?php for ($m = 1; $m <= 6; $m++) { ?>
                        <td class="num"><?php echo h(excel_num($custQ[$m], 0)); ?></td>
                        <td class="money"><?php echo h(excel_num($custA[$m], 2)); ?></td>
                    <?php } ?>

                    <td class="num"><?php echo h(excel_num($custTQty, 0)); ?></td>
                    <td class="money"><?php echo h(excel_num($custTAmt, 2)); ?></td>
                </tr>
                <?php
            }

            ?>
            <tr class="customer-row">
                <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
                <td colspan="19"><?php echo h($r["CUST_COMP"]); ?></td>
            </tr>
            <?php

            $lastCust = $custKey;

            for ($m = 1; $m <= 6; $m++) {
                $custQ[$m] = 0;
                $custA[$m] = 0;
            }

            $custTQty = 0;
            $custTAmt = 0;
        }

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
        ?>

        <tr>
            <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
            <td class="text"><?php echo h($r["CUST_COMP"]); ?></td>
            <td class="text"><?php echo h($r["PART_NUM"]); ?></td>
            <td class="text"><?php echo h($r["PART_NAME"]); ?></td>
            <td class="price"><?php echo h(excel_num($r["PRICE"], 5)); ?></td>
            <td class="text"><?php echo h($r["CURR_CODE"]); ?></td>

            <?php for ($m = 1; $m <= 6; $m++) { ?>
                <td class="num"><?php echo h(excel_num($r["QTY" . $m], 0)); ?></td>
                <td class="money"><?php echo h(excel_num($r["AMT" . $m], 2)); ?></td>
            <?php } ?>

            <td class="num"><?php echo h(excel_num($r["TOTAL_QTY"], 0)); ?></td>
            <td class="money"><?php echo h(excel_num($r["TOTAL_AMT"], 2)); ?></td>
        </tr>

    <?php } ?>

    <?php if ($lastCust != "") { ?>
        <tr class="customer-total-row">
            <td colspan="6" style="text-align:right;">TOTAL CUSTOMER</td>

            <?php for ($m = 1; $m <= 6; $m++) { ?>
                <td class="num"><?php echo h(excel_num($custQ[$m], 0)); ?></td>
                <td class="money"><?php echo h(excel_num($custA[$m], 2)); ?></td>
            <?php } ?>

            <td class="num"><?php echo h(excel_num($custTQty, 0)); ?></td>
            <td class="money"><?php echo h(excel_num($custTAmt, 2)); ?></td>
        </tr>

        <tr class="grand-total-row">
            <td colspan="6" style="text-align:right;">GRAND TOTAL</td>

            <?php for ($m = 1; $m <= 6; $m++) { ?>
                <td class="num"><?php echo h(excel_num($grandQ[$m], 0)); ?></td>
                <td class="money"><?php echo h(excel_num($grandA[$m], 2)); ?></td>
            <?php } ?>

            <td class="num"><?php echo h(excel_num($grandTQty, 0)); ?></td>
            <td class="money"><?php echo h(excel_num($grandTAmt, 2)); ?></td>
        </tr>
    <?php } ?>

</table>

</body>
</html>
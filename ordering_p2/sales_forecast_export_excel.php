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

function fmt_print_date() {
    return date("d-M-Y H:i:s");
}

function excel_num($value, $decimal = 0) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, $decimal, ".", "");
}

function load_rate_map($conn) {
    $rateMap = array();

    $sql = "
        SELECT
            ISNULL(CURR_CODE, '') AS CURR_CODE,
            ISNULL(CURR_VRATE, 1) AS CURR_VRATE
        FROM dbo.TODAY_RATE_VIEW
    ";

    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt === false) {
        return $rateMap;
    }

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $code = strtoupper(trim((string)$r["CURR_CODE"]));

        if ($code != "") {
            $rateMap[$code] = (float)$r["CURR_VRATE"];
        }
    }

    return $rateMap;
}

function get_usd_factor_by_curr($currCode, $rateMap) {
    $currCode = strtoupper(trim((string)$currCode));

    if ($currCode == "") {
        return 1;
    }

    if ($currCode == "USD") {
        return 1;
    }

    $rateOri = isset($rateMap[$currCode]) ? (float)$rateMap[$currCode] : 1;
    $rateUsd = isset($rateMap["USD"]) ? (float)$rateMap["USD"] : 1;

    if ($rateUsd == 0) {
        $rateUsd = 1;
    }

    return $rateOri / $rateUsd;
}

$cust_code   = get_param("CUST_CODE", "");
$start_month = get_param("START_MONTH", "");

if ($cust_code == "") {
    die("Customer belum diisi.");
}

if ($start_month == "") {
    die("Month belum dipilih.");
}

$start_date = month_to_yyyymmdd($start_month);

if ($start_date == "") {
    die("Month tidak valid.");
}

$rateMap = load_rate_map($conn);

$sql = "
    SET NOCOUNT ON;
    EXEC dbo.SP_SALES_FORCAST_3MONTH1 ?, ?
";

$stmt = sqlsrv_query($conn, $sql, array(
    $start_date,
    $cust_code
));

if ($stmt === false) {
    die("<pre>Query Sales Forecast gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();

$bulan1 = "";
$bulan2 = "";
$bulan3 = "";

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

    $currCode = isset($r["CURR_CODE"]) ? safe_trim($r["CURR_CODE"]) : "";
    $usdRateFactor = get_usd_factor_by_curr($currCode, $rateMap);

    /*
        PRICE tetap original.
        AMOUNT dikonversi ke USD.
    */
    $rows[] = array(
        "CUST_CODE"       => safe_trim($r["CUST_CODE"]),
        "CUST_COMP"       => safe_trim($r["CUST_COMP"]),
        "PART_NUM"        => safe_trim($r["PART_NUM"]),
        "PART_NO"         => safe_trim($r["PART_NO"]),
        "PART_NAME"       => safe_trim($r["PART_NAME"]),
        "PRDT_PRICE"      => $price,
        "CURR_CODE"       => $currCode,
        "USD_RATE_FACTOR" => $usdRateFactor,

        "QTY1"            => $qty1,
        "AMT1"            => $qty1 * $price * $usdRateFactor,

        "QTY2"            => $qty2,
        "AMT2"            => $qty2 * $price * $usdRateFactor,

        "QTY3"            => $qty3,
        "AMT3"            => $qty3 * $price * $usdRateFactor
    );
}

if ($bulan1 == "") {
    $ts = strtotime(substr($start_date, 0, 4) . "-" . substr($start_date, 4, 2) . "-01");

    $bulan1 = strtoupper(date("M-y", $ts));
    $bulan2 = strtoupper(date("M-y", strtotime("+1 month", $ts)));
    $bulan3 = strtoupper(date("M-y", strtotime("+2 month", $ts)));
}

$fileCust = $cust_code == "%" ? "ALL" : $cust_code;
$fileName = "sales_forecast_" . $fileCust . "_" . $start_month . "_" . date("Ymd_His") . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Forecast Export</title>

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

        .sub-title {
            font-size: 10pt;
            font-weight: bold;
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
            mso-number-format: "0.0000";
            text-align: right;
        }

        .customer-row {
            background: #eeeeee;
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
        <td colspan="13" class="title">SALES FORECAST</td>
    </tr>

    <tr>
        <td colspan="13" class="sub-title">P.T. IMC TEKNO INDONESIA - PPIC DEPT</td>
    </tr>

    <tr>
        <td colspan="13">
            Customer:
            <?php echo h($cust_code == "%" ? "ALL CUSTOMER" : $cust_code); ?>
        </td>
    </tr>

    <tr>
        <td colspan="13">
            Month:
            <?php echo h($start_month); ?>
        </td>
    </tr>

    <tr>
        <td colspan="13">
            Export Date:
            <?php echo h(fmt_print_date()); ?>
        </td>
    </tr>

    <tr>
        <td colspan="13">&nbsp;</td>
    </tr>

    <tr>
        <th rowspan="2">Customer Code</th>
        <th rowspan="2">Customer Name</th>
        <th rowspan="2">Code</th>
        <th rowspan="2">No.</th>
        <th rowspan="2">Part Name</th>
        <th rowspan="2">Unit Price Original</th>
        <th rowspan="2">Cur.</th>
        <th colspan="2"><?php echo h($bulan1); ?></th>
        <th colspan="2"><?php echo h($bulan2); ?></th>
        <th colspan="2"><?php echo h($bulan3); ?></th>
    </tr>

    <tr>
        <th>Qty</th>
        <th>Amount USD</th>
        <th>Qty</th>
        <th>Amount USD</th>
        <th>Qty</th>
        <th>Amount USD</th>
    </tr>

    <?php if (count($rows) == 0) { ?>
        <tr>
            <td colspan="13">Data forecast tidak ditemukan.</td>
        </tr>
    <?php } ?>

    <?php
    $lastCust = "";
    $lastCustCode = "";
    $lastCustComp = "";

    $custAmt1 = 0;
    $custAmt2 = 0;
    $custAmt3 = 0;

    $grandAmt1 = 0;
    $grandAmt2 = 0;
    $grandAmt3 = 0;

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];

        $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

        if ($custKey != $lastCust) {
            if ($lastCust != "") {
                ?>
                <tr class="total-row">
                    <td colspan="8" style="text-align:right;">
                        TOTAL AMOUNT USD <?php echo h($lastCustCode); ?> - <?php echo h($lastCustComp); ?>
                    </td>
                    <td class="money"><?php echo h(excel_num($custAmt1, 2)); ?></td>
                    <td></td>
                    <td class="money"><?php echo h(excel_num($custAmt2, 2)); ?></td>
                    <td></td>
                    <td class="money"><?php echo h(excel_num($custAmt3, 2)); ?></td>
                </tr>
                <?php
            }

            ?>
            <tr class="customer-row">
                <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
                <td colspan="12"><?php echo h($r["CUST_COMP"]); ?></td>
            </tr>
            <?php

            $lastCust = $custKey;
            $lastCustCode = $r["CUST_CODE"];
            $lastCustComp = $r["CUST_COMP"];

            $custAmt1 = 0;
            $custAmt2 = 0;
            $custAmt3 = 0;
        }

        $custAmt1 += $r["AMT1"];
        $custAmt2 += $r["AMT2"];
        $custAmt3 += $r["AMT3"];

        $grandAmt1 += $r["AMT1"];
        $grandAmt2 += $r["AMT2"];
        $grandAmt3 += $r["AMT3"];
        ?>

        <tr>
            <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
            <td class="text"><?php echo h($r["CUST_COMP"]); ?></td>
            <td class="text"><?php echo h($r["PART_NUM"]); ?></td>
            <td class="text"><?php echo h($r["PART_NO"]); ?></td>
            <td class="text"><?php echo h($r["PART_NAME"]); ?></td>
            <td class="price"><?php echo h(excel_num($r["PRDT_PRICE"], 4)); ?></td>
            <td class="text"><?php echo h($r["CURR_CODE"]); ?></td>

            <td class="num"><?php echo h(excel_num($r["QTY1"], 0)); ?></td>
            <td class="money"><?php echo h(excel_num($r["AMT1"], 2)); ?></td>

            <td class="num"><?php echo h(excel_num($r["QTY2"], 0)); ?></td>
            <td class="money"><?php echo h(excel_num($r["AMT2"], 2)); ?></td>

            <td class="num"><?php echo h(excel_num($r["QTY3"], 0)); ?></td>
            <td class="money"><?php echo h(excel_num($r["AMT3"], 2)); ?></td>
        </tr>

    <?php } ?>

    <?php if ($lastCust != "") { ?>
        <tr class="total-row">
            <td colspan="8" style="text-align:right;">
                TOTAL AMOUNT USD <?php echo h($lastCustCode); ?> - <?php echo h($lastCustComp); ?>
            </td>
            <td class="money"><?php echo h(excel_num($custAmt1, 2)); ?></td>
            <td></td>
            <td class="money"><?php echo h(excel_num($custAmt2, 2)); ?></td>
            <td></td>
            <td class="money"><?php echo h(excel_num($custAmt3, 2)); ?></td>
        </tr>

        <tr class="grand-row">
            <td colspan="8" style="text-align:right;">
                GRAND TOTAL AMOUNT USD
            </td>
            <td class="money"><?php echo h(excel_num($grandAmt1, 2)); ?></td>
            <td></td>
            <td class="money"><?php echo h(excel_num($grandAmt2, 2)); ?></td>
            <td></td>
            <td class="money"><?php echo h(excel_num($grandAmt3, 2)); ?></td>
        </tr>
    <?php } ?>

</table>

</body>
</html>
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

function month_label_from_yyyymmdd($value) {
    $value = trim($value);

    if (preg_match('/^\d{8}$/', $value)) {
        $ts = strtotime(substr($value, 0, 4) . "-" . substr($value, 4, 2) . "-" . substr($value, 6, 2));
    } else {
        $ts = strtotime($value);
    }

    if ($ts === false) {
        return "";
    }

    return date("F Y", $ts);
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

function excel_text($value) {
    return "=" . '"' . str_replace('"', '""', (string)$value) . '"';
}

$cust_code   = get_param("CUST_CODE", "%");
$start_month = get_param("START_MONTH", date("Y-m"));

if ($cust_code == "") {
    $cust_code = "%";
}

$start_date = month_to_yyyymmdd($start_month);

if ($start_date == "") {
    die("Month tidak valid.");
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

$dataRows = array();
$printRows = array();

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

    /*
        TANPA KONVERSI USD:
        Amount langsung dihitung dari qty x price original.
        Currency tetap sesuai CURR_CODE dari data.
    */
    $dataRows[] = array(
        "CUST_CODE"  => safe_trim($r["CUST_CODE"]),
        "CUST_COMP"  => safe_trim($r["CUST_COMP"]),
        "PART_NUM"   => safe_trim($r["PART_NUM"]),
        "PART_NO"    => safe_trim($r["PART_NO"]),
        "PART_NAME"  => safe_trim($r["PART_NAME"]),
        "PRDT_PRICE" => $price,
        "CURR_CODE"  => safe_trim($r["CURR_CODE"]),

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
$custQty1 = 0;
$custQty2 = 0;
$custQty3 = 0;

$grandAmt1 = 0;
$grandAmt2 = 0;
$grandAmt3 = 0;
$grandQty1 = 0;
$grandQty2 = 0;
$grandQty3 = 0;

for ($i = 0; $i < count($dataRows); $i++) {
    $r = $dataRows[$i];
    $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

    if ($custKey != $lastCust) {
        if ($lastCust != "") {
            $printRows[] = array(
                "ROW_TYPE"  => "CUSTOMER_TOTAL",
                "CUST_CODE" => $lastCustCode,
                "CUST_COMP" => $lastCustComp,
                "QTY1"      => $custQty1,
                "AMT1"      => $custAmt1,
                "QTY2"      => $custQty2,
                "AMT2"      => $custAmt2,
                "QTY3"      => $custQty3,
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
        $custQty1 = 0;
        $custQty2 = 0;
        $custQty3 = 0;
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

    $custQty1 += $r["QTY1"];
    $custQty2 += $r["QTY2"];
    $custQty3 += $r["QTY3"];

    $custAmt1 += $r["AMT1"];
    $custAmt2 += $r["AMT2"];
    $custAmt3 += $r["AMT3"];

    $grandQty1 += $r["QTY1"];
    $grandQty2 += $r["QTY2"];
    $grandQty3 += $r["QTY3"];

    $grandAmt1 += $r["AMT1"];
    $grandAmt2 += $r["AMT2"];
    $grandAmt3 += $r["AMT3"];
}

if ($lastCust != "") {
    $printRows[] = array(
        "ROW_TYPE"  => "CUSTOMER_TOTAL",
        "CUST_CODE" => $lastCustCode,
        "CUST_COMP" => $lastCustComp,
        "QTY1"      => $custQty1,
        "AMT1"      => $custAmt1,
        "QTY2"      => $custQty2,
        "AMT2"      => $custAmt2,
        "QTY3"      => $custQty3,
        "AMT3"      => $custAmt3
    );
}

if (count($dataRows) > 0) {
    $printRows[] = array(
        "ROW_TYPE" => "GRAND_TOTAL",
        "QTY1"     => $grandQty1,
        "AMT1"     => $grandAmt1,
        "QTY2"     => $grandQty2,
        "AMT2"     => $grandAmt2,
        "QTY3"     => $grandQty3,
        "AMT3"     => $grandAmt3
    );
}

if (count($printRows) == 0) {
    $printRows[] = array(
        "ROW_TYPE" => "EMPTY",
        "MESSAGE"  => "Data forecast tidak ditemukan."
    );
}

$fileCust = $cust_code == "%" ? "ALL" : $cust_code;
$fileName = "sales_forecast_no_usd_" . $fileCust . "_" . str_replace("-", "", $start_month) . "_" . date("Ymd_His") . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table {
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 10pt;
        }

        th {
            background: #d9eaf7;
            border: 1px solid #000000;
            font-weight: bold;
            text-align: center;
        }

        td {
            border: 1px solid #000000;
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
            mso-number-format: "#,##0.0000";
            text-align: right;
        }

        .amount {
            mso-number-format: "#,##0.00";
            text-align: right;
        }

        .customer-row {
            background: #eeeeee;
            font-weight: bold;
        }

        .total-row {
            background: #f3f3f3;
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
        <td colspan="11" class="title">SALES FORECAST</td>
    </tr>
    <tr>
        <td colspan="11">P.T. IMC TEKNO INDONESIA - PPIC DEPT</td>
    </tr>
    <tr>
        <td colspan="11">Customer: <?php echo h($cust_code == "%" ? "ALL" : $cust_code); ?></td>
    </tr>
    <tr>
        <td colspan="11">Start Month: <?php echo h(month_label_from_yyyymmdd($start_date)); ?></td>
    </tr>
    <tr>
        <td colspan="11">Export Date: <?php echo h(date("d-M-Y H:i:s")); ?></td>
    </tr>
    <tr>
        <td colspan="11">Amount tanpa konversi USD. Amount = Qty x Unit Price sesuai currency original.</td>
    </tr>
    <tr>
        <td colspan="11">&nbsp;</td>
    </tr>

    <tr>
        <th rowspan="2">Code</th>
        <th rowspan="2">No.</th>
        <th rowspan="2">Part Name</th>
        <th rowspan="2">Unit Price</th>
        <th rowspan="2">Cur.</th>
        <th colspan="2"><?php echo h($bulan1); ?></th>
        <th colspan="2"><?php echo h($bulan2); ?></th>
        <th colspan="2"><?php echo h($bulan3); ?></th>
    </tr>
    <tr>
        <th>Qty</th>
        <th>Amount</th>
        <th>Qty</th>
        <th>Amount</th>
        <th>Qty</th>
        <th>Amount</th>
    </tr>

    <?php for ($i = 0; $i < count($printRows); $i++) { ?>
        <?php $r = $printRows[$i]; ?>

        <?php if ($r["ROW_TYPE"] == "CUSTOMER") { ?>
            <tr class="customer-row">
                <td colspan="11" class="text">
                    <?php echo h($r["CUST_CODE"]); ?>
                    &nbsp;
                    <?php echo h($r["CUST_COMP"]); ?>
                </td>
            </tr>
        <?php } elseif ($r["ROW_TYPE"] == "DETAIL") { ?>
            <tr>
                <td class="text"><?php echo h($r["PART_NUM"]); ?></td>
                <td class="text"><?php echo h($r["PART_NO"]); ?></td>
                <td class="text"><?php echo h($r["PART_NAME"]); ?></td>
                <td class="price"><?php echo h(fmt_price($r["PRDT_PRICE"])); ?></td>
                <td class="text"><?php echo h($r["CURR_CODE"]); ?></td>

                <td class="num"><?php echo h(fmt_num($r["QTY1"], 0)); ?></td>
                <td class="amount"><?php echo h(fmt_amount($r["AMT1"])); ?></td>

                <td class="num"><?php echo h(fmt_num($r["QTY2"], 0)); ?></td>
                <td class="amount"><?php echo h(fmt_amount($r["AMT2"])); ?></td>

                <td class="num"><?php echo h(fmt_num($r["QTY3"], 0)); ?></td>
                <td class="amount"><?php echo h(fmt_amount($r["AMT3"])); ?></td>
            </tr>
        <?php } elseif ($r["ROW_TYPE"] == "CUSTOMER_TOTAL") { ?>
            <tr class="total-row">
                <td colspan="5" style="text-align:right;">
                    TOTAL
                    <?php echo h($r["CUST_CODE"]); ?>
                    -
                    <?php echo h($r["CUST_COMP"]); ?>
                </td>
                <td class="num"><?php echo h(fmt_num($r["QTY1"], 0)); ?></td>
                <td class="amount"><?php echo h(fmt_amount($r["AMT1"])); ?></td>
                <td class="num"><?php echo h(fmt_num($r["QTY2"], 0)); ?></td>
                <td class="amount"><?php echo h(fmt_amount($r["AMT2"])); ?></td>
                <td class="num"><?php echo h(fmt_num($r["QTY3"], 0)); ?></td>
                <td class="amount"><?php echo h(fmt_amount($r["AMT3"])); ?></td>
            </tr>
        <?php } elseif ($r["ROW_TYPE"] == "GRAND_TOTAL") { ?>
            <tr class="grand-row">
                <td colspan="5" style="text-align:right;">GRAND TOTAL</td>
                <td class="num"><?php echo h(fmt_num($r["QTY1"], 0)); ?></td>
                <td class="amount"><?php echo h(fmt_amount($r["AMT1"])); ?></td>
                <td class="num"><?php echo h(fmt_num($r["QTY2"], 0)); ?></td>
                <td class="amount"><?php echo h(fmt_amount($r["AMT2"])); ?></td>
                <td class="num"><?php echo h(fmt_num($r["QTY3"], 0)); ?></td>
                <td class="amount"><?php echo h(fmt_amount($r["AMT3"])); ?></td>
            </tr>
        <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
            <tr>
                <td colspan="11"><?php echo h($r["MESSAGE"]); ?></td>
            </tr>
        <?php } ?>
    <?php } ?>
</table>
</body>
</html>

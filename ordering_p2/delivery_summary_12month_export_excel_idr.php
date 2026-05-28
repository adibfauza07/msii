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

function is_idr($currCode) {
    return strtoupper(trim((string)$currCode)) == "IDR";
}

function fmt_excel_number($value, $decimal) {
    if ($value === null || $value === "") {
        $value = 0;
    }
    return number_format((float)$value, $decimal, ".", "");
}

function fmt_qty_excel($value) {
    return fmt_excel_number($value, 0);
}

function fmt_price_excel($value, $currCode) {
    if (is_idr($currCode)) {
        return fmt_excel_number($value, 0);
    }
    return fmt_excel_number($value, 5);
}

function fmt_amount_excel($value, $currCode) {
    if (is_idr($currCode)) {
        return fmt_excel_number($value, 0);
    }
    return fmt_excel_number($value, 2);
}

function cell_class_price($currCode) {
    return is_idr($currCode) ? "price-idr" : "price";
}

function cell_class_amount($currCode) {
    return is_idr($currCode) ? "amount-idr" : "amount";
}

function total_curr_code($allIdr) {
    return $allIdr ? "IDR" : "MIX";
}

$start_month = get_param("START_MONTH", date("Y-m"));
$cust_code   = get_param("CUST_CODE", "%");

if ($cust_code == "") {
    $cust_code = "%";
}

$start_ymd = month_to_yyyymmdd($start_month);

if ($start_ymd == "") {
    die("Starting Month tidak valid.");
}

$sql = "
    SET NOCOUNT ON;
    EXEC dbo.DeliverySum12Month_idr ?, ?
";

$stmt = sqlsrv_query($conn, $sql, array($start_ymd, $cust_code));

if ($stmt === false) {
    die("<pre>Query Delivery Summary 12 Month gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$months = array();
for ($m = 1; $m <= 12; $m++) {
    $months[$m] = "";
}

$rows = array();
$printRows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if ($months[1] == "" && isset($r["Month1"])) {
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = safe_trim($r["Month" . $m]);
        }
    }

    $currCode = safe_trim($r["CURR_CODE"]);

    $row = array(
        "CUST_CODE" => safe_trim($r["CUST_CODE"]),
        "CUST_COMP" => safe_trim($r["CUST_COMP"]),
        "PART_CODE" => isset($r["PART_NUM"]) ? safe_trim($r["PART_NUM"]) : "",
        "PART_NO"   => isset($r["PART_NO"]) ? safe_trim($r["PART_NO"]) : "",
        "PART_NAME" => safe_trim($r["PART_NAME"]),
        "PRICE"     => isset($r["PART_PRICE"]) ? (float)$r["PART_PRICE"] : 0,
        "CURR_CODE" => $currCode
    );

    $totalQty = 0;
    $totalAmt = 0;

    for ($m = 1; $m <= 12; $m++) {
        $qty = isset($r["DQTY" . $m]) ? (float)$r["DQTY" . $m] : 0;
        $amt = isset($r["AMT" . $m]) ? (float)$r["AMT" . $m] : 0;

        $row["QTY" . $m] = $qty;
        $row["AMT" . $m] = $amt;

        $totalQty += $qty;
        $totalAmt += $amt;
    }

    $row["TOTAL_QTY"] = $totalQty;
    $row["TOTAL_AMT"] = $totalAmt;

    $rows[] = $row;
}

if ($months[1] == "") {
    $ts = strtotime(substr($start_ymd, 0, 4) . "-" . substr($start_ymd, 4, 2) . "-01");
    for ($m = 1; $m <= 12; $m++) {
        $months[$m] = date("F Y", strtotime("+" . ($m - 1) . " month", $ts));
    }
}

$lastCust = "";

$custQ = array();
$custA = array();
$grandQ = array();
$grandA = array();

for ($m = 1; $m <= 12; $m++) {
    $custQ[$m] = 0;
    $custA[$m] = 0;
    $grandQ[$m] = 0;
    $grandA[$m] = 0;
}

$custTQty = 0;
$custTAmt = 0;
$grandTQty = 0;
$grandTAmt = 0;
$custAllIdr = true;
$grandAllIdr = true;

for ($i = 0; $i < count($rows); $i++) {
    $r = $rows[$i];
    $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

    if ($custKey != $lastCust) {
        if ($lastCust != "") {
            $rowTotal = array(
                "ROW_TYPE"  => "CUSTOMER_TOTAL",
                "TOTAL_QTY" => $custTQty,
                "TOTAL_AMT" => $custTAmt,
                "CURR_CODE" => total_curr_code($custAllIdr)
            );

            for ($m = 1; $m <= 12; $m++) {
                $rowTotal["QTY" . $m] = $custQ[$m];
                $rowTotal["AMT" . $m] = $custA[$m];
            }

            $printRows[] = $rowTotal;
        }

        $printRows[] = array(
            "ROW_TYPE"  => "CUSTOMER",
            "CUST_CODE" => $r["CUST_CODE"],
            "CUST_COMP" => $r["CUST_COMP"]
        );

        $lastCust = $custKey;

        for ($m = 1; $m <= 12; $m++) {
            $custQ[$m] = 0;
            $custA[$m] = 0;
        }

        $custTQty = 0;
        $custTAmt = 0;
        $custAllIdr = true;
    }

    $printRows[] = array_merge(array("ROW_TYPE" => "DETAIL"), $r);

    if (!is_idr($r["CURR_CODE"])) {
        $custAllIdr = false;
        $grandAllIdr = false;
    }

    for ($m = 1; $m <= 12; $m++) {
        $custQ[$m] += $r["QTY" . $m];
        $custA[$m] += $r["AMT" . $m];
        $grandQ[$m] += $r["QTY" . $m];
        $grandA[$m] += $r["AMT" . $m];
    }

    $custTQty += $r["TOTAL_QTY"];
    $custTAmt += $r["TOTAL_AMT"];
    $grandTQty += $r["TOTAL_QTY"];
    $grandTAmt += $r["TOTAL_AMT"];
}

if ($lastCust != "") {
    $rowTotal = array(
        "ROW_TYPE"  => "CUSTOMER_TOTAL",
        "TOTAL_QTY" => $custTQty,
        "TOTAL_AMT" => $custTAmt,
        "CURR_CODE" => total_curr_code($custAllIdr)
    );

    for ($m = 1; $m <= 12; $m++) {
        $rowTotal["QTY" . $m] = $custQ[$m];
        $rowTotal["AMT" . $m] = $custA[$m];
    }

    $printRows[] = $rowTotal;
}

if (count($rows) > 0) {
    $rowGrand = array(
        "ROW_TYPE"  => "GRAND_TOTAL",
        "TOTAL_QTY" => $grandTQty,
        "TOTAL_AMT" => $grandTAmt,
        "CURR_CODE" => total_curr_code($grandAllIdr)
    );

    for ($m = 1; $m <= 12; $m++) {
        $rowGrand["QTY" . $m] = $grandQ[$m];
        $rowGrand["AMT" . $m] = $grandA[$m];
    }

    $printRows[] = $rowGrand;
}

if (count($printRows) == 0) {
    $printRows[] = array("ROW_TYPE" => "EMPTY", "MESSAGE" => "Data delivery summary 12 month tidak ditemukan.");
}

$fileCust = $cust_code == "%" ? "ALL" : $cust_code;
$fileName = "delivery_summary_12month_idr_" . $fileCust . "_" . str_replace("-", "", $start_month) . "_" . date("Ymd_His") . ".xls";

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
            font-size: 9pt;
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
            mso-number-format: "#,##0.00000";
            text-align: right;
        }

        .price-idr {
            mso-number-format: "#,##0";
            text-align: right;
        }

        .amount {
            mso-number-format: "#,##0.00";
            text-align: right;
        }

        .amount-idr {
            mso-number-format: "#,##0";
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
        <td colspan="31" class="title">DELIVERY HISTORY SUMMARY 12 MONTH</td>
    </tr>
    <tr>
        <td colspan="31">P.T. IMC TEKNO INDONESIA - PPIC Department</td>
    </tr>
    <tr>
        <td colspan="31">Customer: <?php echo h($cust_code == "%" ? "ALL" : $cust_code); ?></td>
    </tr>
    <tr>
        <td colspan="31">Start Month: <?php echo h(month_label_from_yyyymmdd($start_ymd)); ?></td>
    </tr>
    <tr>
        <td colspan="31">Export Date: <?php echo h(date("d-M-Y H:i:s")); ?></td>
    </tr>
    <tr>
        <td colspan="31"></td>
    </tr>
    <tr>
        <td colspan="31">&nbsp;</td>
    </tr>

    <tr>
        <th rowspan="2">Code</th>
        <th rowspan="2">No</th>
        <th rowspan="2">Name</th>
        <th rowspan="2">Price</th>
        <th rowspan="2">Cur</th>
        <?php for ($m = 1; $m <= 12; $m++) { ?>
            <th colspan="2"><?php echo h($months[$m]); ?></th>
        <?php } ?>
        <th colspan="2">TOTAL</th>
    </tr>
    <tr>
        <?php for ($m = 1; $m <= 12; $m++) { ?>
            <th>QTY</th>
            <th>AMT</th>
        <?php } ?>
        <th>QTY</th>
        <th>AMT</th>
    </tr>

    <?php for ($i = 0; $i < count($printRows); $i++) { ?>
        <?php $r = $printRows[$i]; ?>

        <?php if ($r["ROW_TYPE"] == "CUSTOMER") { ?>
            <tr class="customer-row">
                <td colspan="31" class="text"><?php echo h($r["CUST_CODE"]); ?> <?php echo h($r["CUST_COMP"]); ?></td>
            </tr>
        <?php } elseif ($r["ROW_TYPE"] == "DETAIL") { ?>
            <?php $curr = $r["CURR_CODE"]; ?>
            <tr>
                <td class="text"><?php echo h($r["PART_CODE"]); ?></td>
                <td class="text"><?php echo h($r["PART_NO"]); ?></td>
                <td class="text"><?php echo h($r["PART_NAME"]); ?></td>
                <td class="<?php echo h(cell_class_price($curr)); ?>"><?php echo h(fmt_price_excel($r["PRICE"], $curr)); ?></td>
                <td class="text"><?php echo h($curr); ?></td>

                <?php for ($m = 1; $m <= 12; $m++) { ?>
                    <td class="num"><?php echo h(fmt_qty_excel($r["QTY" . $m])); ?></td>
                    <td class="<?php echo h(cell_class_amount($curr)); ?>"><?php echo h(fmt_amount_excel($r["AMT" . $m], $curr)); ?></td>
                <?php } ?>

                <td class="num"><?php echo h(fmt_qty_excel($r["TOTAL_QTY"])); ?></td>
                <td class="<?php echo h(cell_class_amount($curr)); ?>"><?php echo h(fmt_amount_excel($r["TOTAL_AMT"], $curr)); ?></td>
            </tr>
        <?php } elseif ($r["ROW_TYPE"] == "CUSTOMER_TOTAL") { ?>
            <?php $curr = isset($r["CURR_CODE"]) ? $r["CURR_CODE"] : "MIX"; ?>
            <tr class="total-row">
                <td colspan="5" style="text-align:right;">TOTAL CUSTOMER</td>
                <?php for ($m = 1; $m <= 12; $m++) { ?>
                    <td class="num"><?php echo h(fmt_qty_excel($r["QTY" . $m])); ?></td>
                    <td class="<?php echo h(cell_class_amount($curr)); ?>"><?php echo h(fmt_amount_excel($r["AMT" . $m], $curr)); ?></td>
                <?php } ?>
                <td class="num"><?php echo h(fmt_qty_excel($r["TOTAL_QTY"])); ?></td>
                <td class="<?php echo h(cell_class_amount($curr)); ?>"><?php echo h(fmt_amount_excel($r["TOTAL_AMT"], $curr)); ?></td>
            </tr>
        <?php } elseif ($r["ROW_TYPE"] == "GRAND_TOTAL") { ?>
            <?php $curr = isset($r["CURR_CODE"]) ? $r["CURR_CODE"] : "MIX"; ?>
            <tr class="grand-row">
                <td colspan="5" style="text-align:right;">GRAND TOTAL</td>
                <?php for ($m = 1; $m <= 12; $m++) { ?>
                    <td class="num"><?php echo h(fmt_qty_excel($r["QTY" . $m])); ?></td>
                    <td class="<?php echo h(cell_class_amount($curr)); ?>"><?php echo h(fmt_amount_excel($r["AMT" . $m], $curr)); ?></td>
                <?php } ?>
                <td class="num"><?php echo h(fmt_qty_excel($r["TOTAL_QTY"])); ?></td>
                <td class="<?php echo h(cell_class_amount($curr)); ?>"><?php echo h(fmt_amount_excel($r["TOTAL_AMT"], $curr)); ?></td>
            </tr>
        <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
            <tr>
                <td colspan="31"><?php echo h($r["MESSAGE"]); ?></td>
            </tr>
        <?php } ?>
    <?php } ?>
</table>
</body>
</html>

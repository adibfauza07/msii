<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) die("Koneksi database gagal.");

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
function safe_trim($v){ return $v === null ? "" : trim((string)$v); }

function get_param($name, $default = "") {
    if (isset($_GET[$name])) return trim($_GET[$name]);
    if (isset($_POST[$name])) return trim($_POST[$name]);
    return $default;
}

function month_to_yyyymmdd($value) {
    $value = trim($value);
    if ($value == "") return "";
    if (preg_match('/^\d{4}-\d{2}$/', $value)) return str_replace("-", "", $value) . "01";
    if (preg_match('/^\d{8}$/', $value)) return $value;

    $ts = strtotime($value);
    if ($ts === false) return "";
    return date("Ymd", $ts);
}

function fmt_print_datetime() {
    return date("d-M-Y H:i:s");
}

function excel_num($value, $decimal = 0) {
    if ($value === null || $value === "") return "";

    $n = (float)$value;
    if (abs($n) < 0.000001) return "";

    if ($decimal === "price") {
        return rtrim(rtrim(number_format($n, 5, ".", ""), "0"), ".");
    }

    return number_format($n, $decimal, ".", "");
}

$start_month = get_param("START_MONTH", "");
$cust_code   = get_param("CUST_CODE", "");

if ($start_month == "") die("Starting Month belum dipilih.");
if ($cust_code == "") die("Customer belum diisi.");

$start_ymd = month_to_yyyymmdd($start_month);
if ($start_ymd == "") die("Starting Month tidak valid.");

$period = 6;

$sql = "
    SET NOCOUNT ON;
    EXEC dbo.DeliverySum6Month_char ?, ?
";

$stmt = sqlsrv_query($conn, $sql, array($start_ymd, $cust_code));

if ($stmt === false) {
    die("<pre>Query Delivery Summary 6 Month gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();
$months = array();

for ($i = 1; $i <= $period; $i++) {
    $months[$i] = "";
}

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    for ($i = 1; $i <= $period; $i++) {
        if ($months[$i] == "" && isset($r["Month" . $i])) {
            $months[$i] = safe_trim($r["Month" . $i]);
        }
    }

    $currCode = safe_trim($r["CURR_CODE"]);

    $row = array(
        "CUST_CODE" => safe_trim($r["CUST_CODE"]),
        "CUST_COMP" => safe_trim($r["CUST_COMP"]),
        "PART_NUM"  => safe_trim($r["PART_NUM"]),
        "PART_NAME" => safe_trim($r["PART_NAME"]),
        "PRICE"     => isset($r["PART_PRICE"]) ? (float)$r["PART_PRICE"] : 0,
        "CURR_CODE" => $currCode,
        "TOTAL_QTY" => 0,
        "TOTAL_AMT" => 0
    );

    for ($i = 1; $i <= $period; $i++) {
        $qty = isset($r["DQTY" . $i]) ? (float)$r["DQTY" . $i] : 0;

        // AMOUNT ASLI DARI SP, TIDAK KONVERSI USD
        $amt = isset($r["AMT" . $i]) ? (float)$r["AMT" . $i] : 0;

        $row["QTY" . $i] = $qty;
        $row["AMT" . $i] = $amt;
        $row["TOTAL_QTY"] += $qty;
        $row["TOTAL_AMT"] += $amt;
    }

    $rows[] = $row;
}

$tsStart = strtotime(substr($start_ymd, 0, 4) . "-" . substr($start_ymd, 4, 2) . "-01");

for ($i = 1; $i <= $period; $i++) {
    if ($months[$i] == "") {
        $months[$i] = date("F Y", strtotime("+" . ($i - 1) . " month", $tsStart));
    }
}

$fileCust = $cust_code == "%" ? "ALL" : $cust_code;
$fileName = "delivery_summary_6month_" . $fileCust . "_" . $start_month . "_" . date("Ymd_His") . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Delivery Summary 6 Month Export</title>
<style>
table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 10pt; }
th { background: #d9eaf7; font-weight: bold; border: 1px solid #000; text-align: center; }
td { border: 1px solid #000; vertical-align: top; padding: 3px; }
.title { font-size: 16pt; font-weight: bold; text-align: center; }
.text { mso-number-format: "\@"; }
.num { mso-number-format: "#,##0"; text-align: right; }
.price { mso-number-format: "General"; text-align: right; }
.money { mso-number-format: "#,##0.00"; text-align: right; }
.customer-row, .customer-total-row { background: #eeeeee; font-weight: bold; }
.grand-total-row { background: #d9eaf7; font-weight: bold; }
</style>
</head>
<body>

<table>
<tr><td colspan="<?php echo 6 + ($period * 2) + 2; ?>" class="title">DELIVERY HISTORY SUMMARY 6 MONTH</td></tr>
<tr><td colspan="<?php echo 6 + ($period * 2) + 2; ?>">P.T. IMC TEKNO INDONESIA - PPIC Department</td></tr>
<tr><td colspan="<?php echo 6 + ($period * 2) + 2; ?>">Starting Month: <?php echo h($start_ymd); ?></td></tr>
<tr><td colspan="<?php echo 6 + ($period * 2) + 2; ?>">Customer: <?php echo h($cust_code == "%" ? "ALL CUSTOMER" : $cust_code); ?></td></tr>
<tr><td colspan="<?php echo 6 + ($period * 2) + 2; ?>">Export Date: <?php echo h(fmt_print_datetime()); ?></td></tr>
<tr><td colspan="<?php echo 6 + ($period * 2) + 2; ?>">&nbsp;</td></tr>

<tr>
    <th rowspan="2">Customer Code</th>
    <th rowspan="2">Customer Name</th>
    <th rowspan="2">Item Code</th>
    <th rowspan="2">Item Name</th>
    <th rowspan="2">Price Original</th>
    <th rowspan="2">Curr</th>
    <?php for ($i = 1; $i <= $period; $i++) { ?>
        <th colspan="2"><?php echo h($months[$i]); ?></th>
    <?php } ?>
    <th colspan="2">Total</th>
</tr>
<tr>
    <?php for ($i = 1; $i <= $period; $i++) { ?>
        <th>Qty</th>
        <th>Amount</th>
    <?php } ?>
    <th>Qty</th>
    <th>Amount</th>
</tr>

<?php if (count($rows) == 0) { ?>
<tr><td colspan="<?php echo 6 + ($period * 2) + 2; ?>">Data delivery summary 6 month tidak ditemukan.</td></tr>
<?php } ?>

<?php
$lastCust = "";
$custQ = array();
$custA = array();
$grandQ = array();
$grandA = array();

for ($i = 1; $i <= $period; $i++) {
    $custQ[$i] = 0; $custA[$i] = 0;
    $grandQ[$i] = 0; $grandA[$i] = 0;
}

$custTQty = 0;
$custTAmt = 0;
$grandTQty = 0;
$grandTAmt = 0;

for ($x = 0; $x < count($rows); $x++) {
    $r = $rows[$x];
    $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

    if ($custKey != $lastCust) {
        if ($lastCust != "") {
?>
<tr class="customer-total-row">
    <td colspan="6" style="text-align:right;">TOTAL CUSTOMER</td>
    <?php for ($i = 1; $i <= $period; $i++) { ?>
        <td class="num"><?php echo h(excel_num($custQ[$i], 0)); ?></td>
        <td class="money"><?php echo h(excel_num($custA[$i], 2)); ?></td>
    <?php } ?>
    <td class="num"><?php echo h(excel_num($custTQty, 0)); ?></td>
    <td class="money"><?php echo h(excel_num($custTAmt, 2)); ?></td>
</tr>
<?php
        }

        echo '<tr class="customer-row">';
        echo '<td class="text">' . h($r["CUST_CODE"]) . '</td>';
        echo '<td colspan="' . (5 + ($period * 2) + 2) . '">' . h($r["CUST_COMP"]) . '</td>';
        echo '</tr>';

        $lastCust = $custKey;

        for ($i = 1; $i <= $period; $i++) {
            $custQ[$i] = 0;
            $custA[$i] = 0;
        }

        $custTQty = 0;
        $custTAmt = 0;
    }

    for ($i = 1; $i <= $period; $i++) {
        $custQ[$i] += $r["QTY" . $i];
        $custA[$i] += $r["AMT" . $i];
        $grandQ[$i] += $r["QTY" . $i];
        $grandA[$i] += $r["AMT" . $i];
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
    <td class="price"><?php echo h(excel_num($r["PRICE"], "price")); ?></td>
    <td class="text"><?php echo h($r["CURR_CODE"]); ?></td>

    <?php for ($i = 1; $i <= $period; $i++) { ?>
        <td class="num"><?php echo h(excel_num($r["QTY" . $i], 0)); ?></td>
        <td class="money"><?php echo h(excel_num($r["AMT" . $i], 2)); ?></td>
    <?php } ?>

    <td class="num"><?php echo h(excel_num($r["TOTAL_QTY"], 0)); ?></td>
    <td class="money"><?php echo h(excel_num($r["TOTAL_AMT"], 2)); ?></td>
</tr>
<?php } ?>

<?php if ($lastCust != "") { ?>
<tr class="customer-total-row">
    <td colspan="6" style="text-align:right;">TOTAL CUSTOMER</td>
    <?php for ($i = 1; $i <= $period; $i++) { ?>
        <td class="num"><?php echo h(excel_num($custQ[$i], 0)); ?></td>
        <td class="money"><?php echo h(excel_num($custA[$i], 2)); ?></td>
    <?php } ?>
    <td class="num"><?php echo h(excel_num($custTQty, 0)); ?></td>
    <td class="money"><?php echo h(excel_num($custTAmt, 2)); ?></td>
</tr>

<tr class="grand-total-row">
    <td colspan="6" style="text-align:right;">GRAND TOTAL</td>
    <?php for ($i = 1; $i <= $period; $i++) { ?>
        <td class="num"><?php echo h(excel_num($grandQ[$i], 0)); ?></td>
        <td class="money"><?php echo h(excel_num($grandA[$i], 2)); ?></td>
    <?php } ?>
    <td class="num"><?php echo h(excel_num($grandTQty, 0)); ?></td>
    <td class="money"><?php echo h(excel_num($grandTAmt, 2)); ?></td>
</tr>
<?php } ?>

</table>
</body>
</html>
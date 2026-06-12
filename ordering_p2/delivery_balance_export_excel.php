<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function get_param($name, $default = "") {
    if (isset($_GET[$name])) return trim($_GET[$name]);
    if (isset($_POST[$name])) return trim($_POST[$name]);
    return $default;
}

function ymd_param($value) {
    $value = trim($value);

    if ($value == "") return "";

    if (preg_match('/^\d{8}$/', $value)) return $value;

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return str_replace("-", "", $value);
    }

    $ts = strtotime($value);
    if ($ts === false) return "";

    return date("Ymd", $ts);
}

function safe_trim($value) {
    if ($value === null) return "";
    return trim((string)$value);
}

function excel_num($value) {
    if ($value === null || $value === "") return "";
    return number_format((float)$value, 0, ".", "");
}

$start_date = get_param("START_DATE", "");
$end_date   = get_param("END_DATE", "");
$cust_code  = get_param("CUST_CODE", "%");

$start_ymd = ymd_param($start_date);
$end_ymd   = ymd_param($end_date);

if ($start_ymd == "" || $end_ymd == "") {
    die("Tanggal tidak valid.");
}

if ($cust_code == "") {
    $cust_code = "%";
}

$sql = "
    SET NOCOUNT ON;
    EXEC dbo.SP_DELIVERY_INSTRUCTION1 ?, ?, ?
";

$stmt = sqlsrv_query($conn, $sql, array(
    $start_ymd,
    $end_ymd,
    $cust_code
));

if ($stmt === false) {
    die("<pre>Query Delivery Balance gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$fileCust = ($cust_code == "%") ? "ALL" : $cust_code;
$fileName = "delivery_balance_" . $fileCust . "_" . date("Ymd_His") . ".xls";

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
        padding: 3px;
    }

    .title {
        font-size: 16pt;
        font-weight: bold;
        text-align: center;
    }

    .info {
        font-weight: bold;
    }

    .customer {
        background: #eeeeee;
        font-weight: bold;
    }

    .num {
        mso-number-format: "#,##0";
        text-align: right;
    }

    .minus {
        color: red;
        font-weight: bold;
    }

    .text {
        mso-number-format: "\@";
    }
</style>
</head>
<body>

<table>
    <tr>
        <td colspan="7" class="title">DELIVERY BALANCE</td>
    </tr>
    <tr>
        <td colspan="7" class="info">P.T. IMC TEKNO INDONESIA - PPIC Department</td>
    </tr>
    <tr>
        <td colspan="7">Period: <?php echo h($start_date); ?> s/d <?php echo h($end_date); ?></td>
    </tr>
    <tr>
        <td colspan="7">Customer: <?php echo h($cust_code == "%" ? "ALL CUSTOMER" : $cust_code); ?></td>
    </tr>
    <tr>
        <td colspan="7">Export Date: <?php echo h(date("d-M-Y H:i:s")); ?></td>
    </tr>
    <tr>
        <td colspan="7">&nbsp;</td>
    </tr>

    <tr>
        <th>Part Code</th>
        <th>Part No</th>
        <th>Part Name</th>
        <th>PO Bal.</th>
        <th>Schedule</th>
        <th>Delivered</th>
        <th>Balance</th>
    </tr>

<?php
$lastCust = "";
$rowCount = 0;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $custKey = safe_trim($r["CUST_CODE"]) . "|" . safe_trim($r["CUST_COMP"]);

    if ($custKey != $lastCust) {
        ?>
        <tr class="customer">
            <td colspan="7">
                <?php echo h(safe_trim($r["CUST_CODE"])); ?>
                -
                <?php echo h(safe_trim($r["CUST_COMP"])); ?>
            </td>
        </tr>
        <?php
        $lastCust = $custKey;
    }

    $poBal     = isset($r["SPOQTY"]) ? (float)$r["SPOQTY"] : 0;
    $schedule  = isset($r["SSQTY"]) ? (float)$r["SSQTY"] : 0;
    $delivered = isset($r["SDELQTY"]) ? (float)$r["SDELQTY"] : 0;
    $balance   = $delivered - $schedule;

    $balClass = "num";
    if ($balance < 0) {
        $balClass .= " minus";
    }

    $rowCount++;
    ?>
    <tr>
        <td class="text"><?php echo h(safe_trim($r["PART_NUM"])); ?></td>
        <td class="text"><?php echo h(safe_trim($r["PART_NO"])); ?></td>
        <td class="text"><?php echo h(safe_trim($r["PART_NAME"])); ?></td>
        <td class="num"><?php echo h(excel_num($poBal)); ?></td>
        <td class="num"><?php echo h(excel_num($schedule)); ?></td>
        <td class="num"><?php echo h(excel_num($delivered)); ?></td>
        <td class="<?php echo h($balClass); ?>"><?php echo h(excel_num($balance)); ?></td>
    </tr>
    <?php
}

if ($rowCount == 0) {
    ?>
    <tr>
        <td colspan="7" style="text-align:center;">Data delivery balance tidak ditemukan.</td>
    </tr>
    <?php
}
?>

</table>

</body>
</html>
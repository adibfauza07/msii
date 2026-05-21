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

function fmt_print_datetime() {
    return date("d-M-Y H:i:s");
}

function excel_num_cell($value) {
    if ($value === null || $value === "") {
        return "";
    }

    $n = (float)$value;

    if ($n == 0) {
        return "";
    }

    return number_format($n, 0, ".", "");
}

$start_date = get_param("START_DATE", "");
$end_date   = get_param("END_DATE", "");
$cust_code  = get_param("CUST_CODE", "");

if ($start_date == "" || $end_date == "") {
    die("Tanggal belum diisi.");
}

if ($cust_code == "") {
    die("Customer belum diisi.");
}

$start_ymd = ymd_param($start_date);
$end_ymd   = ymd_param($end_date);

if ($start_ymd == "" || $end_ymd == "") {
    die("Tanggal tidak valid.");
}

$sql = "
    SET NOCOUNT ON;
    EXEC dbo.sp_PivotDeliverySchedule_ByCustomer ?, ?, ?
";

$stmt = sqlsrv_query($conn, $sql, array(
    $start_ymd,
    $end_ymd,
    $cust_code
));

if ($stmt === false) {
    die("<pre>Query Delivery Schedule gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $r;
}

$fileCust = $cust_code == "%" ? "ALL" : $cust_code;
$fileName = "delivery_schedule_" . $fileCust . "_" . date("Ymd_His") . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Schedule Export</title>

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

        .item-row {
            background: #eeeeee;
            font-weight: bold;
        }

        .label {
            font-weight: bold;
            background: #f7f7f7;
        }
    </style>
</head>

<body>

<table>
    <tr>
        <td colspan="35" class="title">DELIVERY SCHEDULE</td>
    </tr>

    <tr>
        <td colspan="35">P.T. IMC TEKNO INDONESIA - PPIC Department</td>
    </tr>

    <tr>
        <td colspan="35">
            Period:
            <?php echo h($start_date); ?>
            s/d
            <?php echo h($end_date); ?>
        </td>
    </tr>

    <tr>
        <td colspan="35">
            Customer:
            <?php echo h($cust_code == "%" ? "ALL CUSTOMER" : $cust_code); ?>
        </td>
    </tr>

    <tr>
        <td colspan="35">
            Export Date:
            <?php echo h(fmt_print_datetime()); ?>
        </td>
    </tr>

    <tr>
        <td colspan="35">&nbsp;</td>
    </tr>

    <?php if (count($rows) == 0) { ?>
        <tr>
            <td colspan="35">Data delivery schedule tidak ditemukan.</td>
        </tr>
    <?php } ?>

    <?php for ($ridx = 0; $ridx < count($rows); $ridx++) { ?>
        <?php $hrow = $rows[$ridx]; ?>

        <tr class="item-row">
            <td colspan="35">
                <?php echo h(safe_trim($hrow["CUST_CODE"])); ?>
                -
                <?php echo h(safe_trim($hrow["CUST_COMP"])); ?>
                |
                <?php echo h(safe_trim($hrow["ITEM_CODE"])); ?>
                -
                <?php echo h(safe_trim($hrow["ITEM_NAME"])); ?>
            </td>
        </tr>

        <tr>
            <th></th>
            <?php for ($i = 1; $i <= 31; $i++) { ?>
                <th><?php echo h(str_pad($i, 2, "0", STR_PAD_LEFT)); ?></th>
            <?php } ?>
        </tr>

        <tr>
            <td class="label">Pla</td>
            <?php for ($i = 1; $i <= 31; $i++) { ?>
                <?php
                    $col = $i . "_SCH";
                    $val = isset($hrow[$col]) ? $hrow[$col] : 0;
                ?>
                <td class="num"><?php echo h(excel_num_cell($val)); ?></td>
            <?php } ?>
        </tr>

        <tr>
            <td class="label">Act</td>
            <?php for ($i = 1; $i <= 31; $i++) { ?>
                <?php
                    $col = $i . "_DEL";
                    $val = isset($hrow[$col]) ? $hrow[$col] : 0;
                ?>
                <td class="num"><?php echo h(excel_num_cell($val)); ?></td>
            <?php } ?>
        </tr>

        <tr>
            <td class="label">Bal</td>
            <?php for ($i = 1; $i <= 31; $i++) { ?>
                <?php
                    $col = $i . "_BAL";
                    $val = isset($hrow[$col]) ? $hrow[$col] : 0;
                ?>
                <td class="num"><?php echo h(excel_num_cell($val)); ?></td>
            <?php } ?>
        </tr>

        <tr>
            <td colspan="35">&nbsp;</td>
        </tr>
    <?php } ?>

</table>

</body>
</html>
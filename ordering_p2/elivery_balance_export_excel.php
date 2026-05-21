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

function excel_num($value, $decimal = 0) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, $decimal, ".", "");
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

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $poBal     = isset($r["SPOQTY"]) ? (float)$r["SPOQTY"] : 0;
    $schedule  = isset($r["SSQTY"]) ? (float)$r["SSQTY"] : 0;
    $delivered = isset($r["SDELQTY"]) ? (float)$r["SDELQTY"] : 0;
    $balance   = $delivered - $schedule;

    $rows[] = array(
        "CUST_CODE" => safe_trim($r["CUST_CODE"]),
        "CUST_COMP" => safe_trim($r["CUST_COMP"]),
        "PART_NUM"  => safe_trim($r["PART_NUM"]),
        "PART_NO"   => safe_trim($r["PART_NO"]),
        "PART_NAME" => safe_trim($r["PART_NAME"]),
        "PO_BAL"    => $poBal,
        "SCHEDULE"  => $schedule,
        "DELIVERED" => $delivered,
        "BALANCE"   => $balance
    );
}

$fileCust = $cust_code == "%" ? "ALL" : $cust_code;
$fileName = "delivery_balance_" . $fileCust . "_" . date("Ymd_His") . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Balance Export</title>

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

        .customer-row {
            background: #eeeeee;
            font-weight: bold;
        }
    </style>
</head>

<body>

<table>
    <tr>
        <td colspan="9" class="title">DELIVERY BALANCE</td>
    </tr>

    <tr>
        <td colspan="9">P.T. IMC TEKNO INDONESIA - PPIC Departement</td>
    </tr>

    <tr>
        <td colspan="9">
            Period:
            <?php echo h($start_date); ?>
            ~
            <?php echo h($end_date); ?>
        </td>
    </tr>

    <tr>
        <td colspan="9">
            Customer:
            <?php echo h($cust_code == "%" ? "ALL CUSTOMER" : $cust_code); ?>
        </td>
    </tr>

    <tr>
        <td colspan="9">
            Export Date:
            <?php echo h(fmt_print_datetime()); ?>
        </td>
    </tr>

    <tr>
        <td colspan="9">&nbsp;</td>
    </tr>

    <tr>
        <th>Customer Code</th>
        <th>Customer Name</th>
        <th>Part Code</th>
        <th>Part No</th>
        <th>Part Name</th>
        <th>PO Bal.</th>
        <th>Schedule</th>
        <th>Delivered</th>
        <th>Balance</th>
    </tr>

    <?php if (count($rows) == 0) { ?>
        <tr>
            <td colspan="9">Data delivery balance tidak ditemukan.</td>
        </tr>
    <?php } ?>

    <?php
    $lastCust = "";

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];

        $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

        if ($custKey != $lastCust) {
            ?>
            <tr class="customer-row">
                <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
                <td colspan="8"><?php echo h($r["CUST_COMP"]); ?></td>
            </tr>
            <?php

            $lastCust = $custKey;
        }
        ?>

        <tr>
            <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
            <td class="text"><?php echo h($r["CUST_COMP"]); ?></td>
            <td class="text"><?php echo h($r["PART_NUM"]); ?></td>
            <td class="text"><?php echo h($r["PART_NO"]); ?></td>
            <td class="text"><?php echo h($r["PART_NAME"]); ?></td>
            <td class="num"><?php echo h(excel_num($r["PO_BAL"], 0)); ?></td>
            <td class="num"><?php echo h(excel_num($r["SCHEDULE"], 0)); ?></td>
            <td class="num"><?php echo h(excel_num($r["DELIVERED"], 0)); ?></td>
            <td class="num"><?php echo h(excel_num($r["BALANCE"], 0)); ?></td>
        </tr>

    <?php } ?>

</table>

</body>
</html>
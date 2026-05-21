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

function excel_percent($short, $sch) {
    $short = (float)$short;
    $sch = (float)$sch;

    if ($sch == 0) {
        return "0.00";
    }

    return number_format(($short / $sch) * 100, 2, ".", "");
}

function score_value($value, $bal) {
    if ($value !== null && $value !== "") {
        return (float)$value;
    }

    if ((float)$bal < 0) {
        return 1;
    }

    return 0;
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
    EXEC dbo.SP_DELIVERY_PERFORMANCE1 ?, ?, ?
";

$stmt = sqlsrv_query($conn, $sql, array(
    $start_ymd,
    $end_ymd,
    $cust_code
));

if ($stmt === false) {
    die("<pre>Query Delivery Performance gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$summaryMap = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $custCode = safe_trim($r["CUST_CODE"]);
    $custComp = safe_trim($r["CUST_COMP"]);
    $itemCode = safe_trim($r["ITEM_CODE"]);
    $itemName = safe_trim($r["ITEM_NAME"]);

    $dsQty = isset($r["DS_QTY"]) ? (float)$r["DS_QTY"] : 0;
    $diQty = isset($r["DI_QTY"]) ? (float)$r["DI_QTY"] : 0;
    $bal   = isset($r["BAL"]) ? (float)$r["BAL"] : ($diQty - $dsQty);
    $score = score_value(isset($r["SCORE"]) ? $r["SCORE"] : null, $bal);

    $key = $custCode . "|" . $custComp . "|" . $itemCode . "|" . $itemName;

    if (!isset($summaryMap[$key])) {
        $summaryMap[$key] = array(
            "CUST_CODE" => $custCode,
            "CUST_COMP" => $custComp,
            "ITEM_CODE" => $itemCode,
            "ITEM_NAME" => $itemName,
            "DEL_SCH"   => 0,
            "DEL_ACT"   => 0,
            "BAL_QTY"   => 0,
            "CNT_SCH"   => 0,
            "CNT_ACT"   => 0,
            "SHORT_CNT" => 0
        );
    }

    $summaryMap[$key]["DEL_SCH"] += $dsQty;
    $summaryMap[$key]["DEL_ACT"] += $diQty;
    $summaryMap[$key]["BAL_QTY"] += $bal;

    if ($dsQty != 0) {
        $summaryMap[$key]["CNT_SCH"] += 1;
    }

    if ($diQty != 0) {
        $summaryMap[$key]["CNT_ACT"] += 1;
    }

    $summaryMap[$key]["SHORT_CNT"] += $score;
}

$summaryRows = array();

foreach ($summaryMap as $row) {
    $summaryRows[] = $row;
}

$fileCust = $cust_code == "%" ? "ALL" : $cust_code;
$fileName = "delivery_performance_" . $fileCust . "_" . date("Ymd_His") . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Performance Export</title>

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

        .percent {
            mso-number-format: "0.00";
            text-align: right;
        }

        .customer-row {
            background: #c0c0c0;
            font-weight: bold;
        }
    </style>
</head>

<body>

<table>
    <tr>
        <td colspan="12" class="title">DELIVERY PERFORMANCE</td>
    </tr>

    <tr>
        <td colspan="12">P.T. IMC TEKNO INDONESIA - PPIC Department</td>
    </tr>

    <tr>
        <td colspan="12">
            Period:
            <?php echo h($start_date); ?>
            to
            <?php echo h($end_date); ?>
        </td>
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
        <th>Item Code</th>
        <th>Item Name</th>
        <th>Del.Sch Sum</th>
        <th>Del.Act Sum</th>
        <th>Bal.Qty Sum</th>
        <th>Del.Sch Count</th>
        <th>Del.Act Count</th>
        <th>Short Count</th>
        <th>Short %</th>
    </tr>

    <?php if (count($summaryRows) == 0) { ?>
        <tr>
            <td colspan="11">Data delivery performance tidak ditemukan.</td>
        </tr>
    <?php } ?>

    <?php
    $lastCust = "";

    $custDelSch = 0;
    $custDelAct = 0;
    $custBalQty = 0;
    $custCntSch = 0;
    $custCntAct = 0;
    $custShort = 0;

    for ($i = 0; $i < count($summaryRows); $i++) {
        $r = $summaryRows[$i];

        $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

        if ($custKey != $lastCust) {
            if ($lastCust != "") {
                ?>
                <tr class="customer-row">
                    <td colspan="4" style="text-align:right;">TOTAL CUSTOMER</td>
                    <td class="num"><?php echo h(excel_num($custDelSch, 0)); ?></td>
                    <td class="num"><?php echo h(excel_num($custDelAct, 0)); ?></td>
                    <td class="num"><?php echo h(excel_num($custBalQty, 0)); ?></td>
                    <td class="num"><?php echo h(excel_num($custCntSch, 0)); ?></td>
                    <td class="num"><?php echo h(excel_num($custCntAct, 0)); ?></td>
                    <td class="num"><?php echo h(excel_num($custShort, 0)); ?></td>
                    <td class="percent"><?php echo h(excel_percent($custShort, $custCntSch)); ?></td>
                </tr>
                <?php
            }

            ?>
            <tr class="customer-row">
                <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
                <td colspan="10"><?php echo h($r["CUST_COMP"]); ?></td>
            </tr>
            <?php

            $lastCust = $custKey;

            $custDelSch = 0;
            $custDelAct = 0;
            $custBalQty = 0;
            $custCntSch = 0;
            $custCntAct = 0;
            $custShort = 0;
        }

        $custDelSch += $r["DEL_SCH"];
        $custDelAct += $r["DEL_ACT"];
        $custBalQty += $r["BAL_QTY"];
        $custCntSch += $r["CNT_SCH"];
        $custCntAct += $r["CNT_ACT"];
        $custShort += $r["SHORT_CNT"];
        ?>

        <tr>
            <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
            <td class="text"><?php echo h($r["CUST_COMP"]); ?></td>
            <td class="text"><?php echo h($r["ITEM_CODE"]); ?></td>
            <td class="text"><?php echo h($r["ITEM_NAME"]); ?></td>
            <td class="num"><?php echo h(excel_num($r["DEL_SCH"], 0)); ?></td>
            <td class="num"><?php echo h(excel_num($r["DEL_ACT"], 0)); ?></td>
            <td class="num"><?php echo h(excel_num($r["BAL_QTY"], 0)); ?></td>
            <td class="num"><?php echo h(excel_num($r["CNT_SCH"], 0)); ?></td>
            <td class="num"><?php echo h(excel_num($r["CNT_ACT"], 0)); ?></td>
            <td class="num"><?php echo h(excel_num($r["SHORT_CNT"], 0)); ?></td>
            <td class="percent"><?php echo h(excel_percent($r["SHORT_CNT"], $r["CNT_SCH"])); ?></td>
        </tr>

    <?php } ?>

    <?php if ($lastCust != "") { ?>
        <tr class="customer-row">
            <td colspan="4" style="text-align:right;">TOTAL CUSTOMER</td>
            <td class="num"><?php echo h(excel_num($custDelSch, 0)); ?></td>
            <td class="num"><?php echo h(excel_num($custDelAct, 0)); ?></td>
            <td class="num"><?php echo h(excel_num($custBalQty, 0)); ?></td>
            <td class="num"><?php echo h(excel_num($custCntSch, 0)); ?></td>
            <td class="num"><?php echo h(excel_num($custCntAct, 0)); ?></td>
            <td class="num"><?php echo h(excel_num($custShort, 0)); ?></td>
            <td class="percent"><?php echo h(excel_percent($custShort, $custCntSch)); ?></td>
        </tr>
    <?php } ?>

</table>

</body>
</html>
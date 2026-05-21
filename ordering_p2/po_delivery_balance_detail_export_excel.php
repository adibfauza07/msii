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

function fmt_excel_num($value, $decimal = 0) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, $decimal, ".", "");
}

function fmt_print_datetime() {
    return date("d-M-Y H:i:s");
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
    EXEC dbo.CUST_ORD_DEL_NEW ?, ?, ?
";

$stmt = sqlsrv_query($conn, $sql, array(
    $start_ymd,
    $end_ymd,
    $cust_code
));

if ($stmt === false) {
    die("<pre>Query PO Delivery Balance Detail gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = array(
        "PART_NUM"  => safe_trim($r["PART_NUM"]),
        "PART_NO"   => safe_trim($r["PART_NO"]),
        "PART_NAME" => safe_trim($r["PART_NAME"]),
        "ORDR_PO"   => safe_trim($r["ORDR_PO"]),
        "ORDP_QTY"  => isset($r["ORDP_QTY"]) ? (float)$r["ORDP_QTY"] : 0,
        "DI_DSNO"   => safe_trim($r["DI_DSNO"]),
        "DI_DATE"   => $r["DI_DATE"],
        "QTY"       => isset($r["QTY"]) ? (float)$r["QTY"] : 0,
        "CUST_CODE" => safe_trim($r["CUST_CODE"]),
        "CUST_COMP" => safe_trim($r["CUST_COMP"]),
        "ORDR_DATE" => $r["ORDR_DATE"]
    );
}

$fileCust = $cust_code == "%" ? "ALL" : $cust_code;
$fileName = "po_delivery_balance_detail_" . $fileCust . "_" . date("Ymd_His") . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PO Delivery Balance Detail Export</title>

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

        .item-row {
            background: #f7f7f7;
            font-weight: bold;
            font-style: italic;
        }
    </style>
</head>

<body>

<table>
    <tr>
        <td colspan="11" class="title">PO DELIVERY BALANCE DETAIL</td>
    </tr>

    <tr>
        <td colspan="11">P.T. IMC TEKNO INDONESIA - PPIC Departement</td>
    </tr>

    <tr>
        <td colspan="11">
            Period:
            <?php echo h($start_date); ?>
            s/d
            <?php echo h($end_date); ?>
        </td>
    </tr>

    <tr>
        <td colspan="11">
            Customer:
            <?php echo h($cust_code == "%" ? "ALL CUSTOMER" : $cust_code); ?>
        </td>
    </tr>

    <tr>
        <td colspan="11">
            Export Date:
            <?php echo h(fmt_print_datetime()); ?>
        </td>
    </tr>

    <tr>
        <td colspan="11">&nbsp;</td>
    </tr>

    <tr>
        <th>Customer Code</th>
        <th>Customer Name</th>
        <th>Part Code</th>
        <th>Part No</th>
        <th>Part Name</th>
        <th>PO #</th>
        <th>PO Date</th>
        <th>DS Date</th>
        <th>DS #</th>
        <th>PO Qty</th>
        <th>Del Qty</th>
        <th>PO Bal</th>
    </tr>

    <?php if (count($rows) == 0) { ?>
        <tr>
            <td colspan="12">Data PO Delivery Balance Detail tidak ditemukan.</td>
        </tr>
    <?php } ?>

    <?php
    $lastCust = "";
    $lastItem = "";
    $lastPoKey = "";
    $runningDelQty = 0;

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];

        $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];
        $itemKey = $r["CUST_CODE"] . "|" . $r["PART_NUM"] . "|" . $r["PART_NO"] . "|" . $r["PART_NAME"];

        $poDateKey = "";
        if ($r["ORDR_DATE"] instanceof DateTime) {
            $poDateKey = $r["ORDR_DATE"]->format("Ymd");
        } else {
            $poDateKey = safe_trim($r["ORDR_DATE"]);
        }

        $poKey = $itemKey . "|" . $r["ORDR_PO"] . "|" . $poDateKey . "|" . $r["ORDP_QTY"];

        if ($custKey != $lastCust) {
            ?>
            <tr class="customer-row">
                <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
                <td colspan="11"><?php echo h($r["CUST_COMP"]); ?></td>
            </tr>
            <?php

            $lastCust = $custKey;
            $lastItem = "";
            $lastPoKey = "";
            $runningDelQty = 0;
        }

        if ($itemKey != $lastItem) {
            ?>
            <tr class="item-row">
                <td colspan="12">
                    <?php echo h($r["PART_NUM"]); ?>
                    -
                    <?php echo h($r["PART_NAME"]); ?>
                    <?php if ($r["PART_NO"] != "") { ?>
                        -
                        <?php echo h($r["PART_NO"]); ?>
                    <?php } ?>
                </td>
            </tr>
            <?php

            $lastItem = $itemKey;
            $lastPoKey = "";
            $runningDelQty = 0;
        }

        $isFirstPoLine = false;

        if ($poKey != $lastPoKey) {
            $lastPoKey = $poKey;
            $runningDelQty = 0;
            $isFirstPoLine = true;
        }

        $runningDelQty += $r["QTY"];
        $poBal = $r["ORDP_QTY"] - $runningDelQty;

        if ($poBal < 0) {
            $poBal = 0;
        }
        ?>

        <tr>
            <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
            <td class="text"><?php echo h($r["CUST_COMP"]); ?></td>
            <td class="text"><?php echo h($r["PART_NUM"]); ?></td>
            <td class="text"><?php echo h($r["PART_NO"]); ?></td>
            <td class="text"><?php echo h($r["PART_NAME"]); ?></td>
            <td class="text"><?php echo $isFirstPoLine ? h($r["ORDR_PO"]) : ""; ?></td>
            <td class="text"><?php echo $isFirstPoLine ? h(fmt_date($r["ORDR_DATE"])) : ""; ?></td>
            <td class="text"><?php echo h(fmt_date($r["DI_DATE"])); ?></td>
            <td class="text"><?php echo h($r["DI_DSNO"]); ?></td>
            <td class="num"><?php echo $isFirstPoLine ? h(fmt_excel_num($r["ORDP_QTY"], 0)) : ""; ?></td>
            <td class="num"><?php echo h(fmt_excel_num($r["QTY"], 0)); ?></td>
            <td class="num"><?php echo h(fmt_excel_num($poBal, 0)); ?></td>
        </tr>

    <?php } ?>

</table>

</body>
</html>
<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function fmt_date($value) {
    if ($value instanceof DateTime) {
        return $value->format("d-M-y");
    }

    if ($value == "" || $value === null) {
        return "";
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return "";
    }

    return date("d-M-y", $ts);
}

function fmt_qty($value) {
    return number_format((float)$value, 0, ",", ".");
}

function clean_text($value) {
    return trim((string)$value);
}

function clean_filename($text) {
    $text = preg_replace('/[^A-Za-z0-9_\-]/', '_', $text);
    return trim($text, "_");
}

$di_id = isset($_GET["DI_ID"]) ? intval($_GET["DI_ID"]) : 0;

if ($di_id <= 0) {
    die("DI_ID tidak valid.");
}

$sql = "
    SET NOCOUNT ON;
    EXEC dbo.SP_INVOICE_TOYODENSO ?
";

$stmt = sqlsrv_query($conn, $sql, array($di_id));

if ($stmt === false) {
    die("<pre>Query SP_INVOICE_TOYODENSO gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $row;
}

if (count($rows) == 0) {
    die("Data DS Toyodenso tidak ditemukan untuk DI_ID: " . h($di_id));
}

$head = $rows[0];

$custComp  = isset($head["CUST_COMP"]) ? clean_text($head["CUST_COMP"]) : "";
$custAddr1 = isset($head["CUST_ADDR1"]) ? clean_text($head["CUST_ADDR1"]) : "";
$custAddr2 = isset($head["CUST_ADDR2"]) ? clean_text($head["CUST_ADDR2"]) : "";
$custCity  = isset($head["CUST_CITY"]) ? clean_text($head["CUST_CITY"]) : "";

$diDate = isset($head["DI_DATE"]) ? $head["DI_DATE"] : "";
$dsNo   = isset($head["DI_DSNO"]) ? clean_text($head["DI_DSNO"]) : "";
$driver = isset($head["DRIVER"]) ? clean_text($head["DRIVER"]) : "";
$truckNo = isset($head["NO_POLISI"]) ? clean_text($head["NO_POLISI"]) : "";

$poList = array();

for ($i = 0; $i < count($rows); $i++) {
    $po = isset($rows[$i]["ORDR_PO"]) ? clean_text($rows[$i]["ORDR_PO"]) : "";

    if ($po != "" && !in_array($po, $poList)) {
        $poList[] = $po;
    }
}

$poHeader = implode(", ", $poList);

$filename = "DS_TOYODENSO_" . clean_filename($dsNo) . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF";
?>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table {
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 10pt;
        }

        td, th {
            border: 1px solid #000000;
            padding: 4px;
            vertical-align: top;
        }

        .no-border td {
            border: none;
        }

        .title {
            font-size: 18pt;
            font-weight: bold;
            text-align: center;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .header {
            background: #D9EAF7;
            font-weight: bold;
            text-align: center;
        }
    </style>
</head>

<body>

<table class="no-border" style="width:100%;">
    <tr>
        <td colspan="2">
            <b>P.T. IMC TEKNO INDONESIA</b><br>
            Kawasan Industri Kota Bukit Indah<br>
            Blok A-III No.15E Dangdeur Bungursari, Jawa Barat<br>
            Phone : (0264)351440
        </td>
        <td colspan="3" class="title">SELLING CARD</td>
        <td colspan="2">
            <b>TO :</b><br>
            <?php echo h($custComp); ?><br>
            <?php echo h($custAddr1); ?><br>
            <?php echo h($custAddr2); ?><br>
            <?php echo h($custCity); ?>
        </td>
    </tr>

    <tr>
        <td colspan="7">&nbsp;</td>
    </tr>

    <tr>
        <td class="center bold">VENDOR NO</td>
        <td class="center">1900.5R</td>
        <td class="center bold">VENDOR NAME</td>
        <td class="center">IMC</td>
        <td class="center bold">DATE</td>
        <td colspan="2" class="center"><?php echo h(fmt_date($diDate)); ?></td>
    </tr>

    <tr>
        <td class="center bold">PO NUMBER</td>
        <td colspan="2"><?php echo h($poHeader); ?></td>
        <td class="center bold">DO-NO</td>
        <td><?php echo h($dsNo); ?></td>
        <td class="center bold">TRUCK NO</td>
        <td><?php echo h($truckNo); ?></td>
    </tr>

    <tr>
        <td class="center bold">DRIVER</td>
        <td colspan="6"><?php echo h($driver); ?></td>
    </tr>
</table>

<br>

<table style="width:100%;">
    <tr class="header">
        <th style="width:40px;">NO</th>
        <th style="width:160px;">KEY NUMBER</th>
        <th style="width:320px;">PART NAME</th>
        <th style="width:80px;">P.O QTY</th>
        <th style="width:120px;">DELIVERY QTY</th>
        <th style="width:120px;">REMARK</th>
    </tr>

    <?php for ($i = 0; $i < count($rows); $i++) { ?>
        <?php
        $r = $rows[$i];

        $qty = isset($r["QTY"]) ? (float)$r["QTY"] : 0;
        $poQty = isset($r["POQTY"]) ? (float)$r["POQTY"] : 0;

        $partNo = isset($r["PART_NO"]) ? clean_text($r["PART_NO"]) : "";
        $partName = isset($r["PART_NAME"]) ? clean_text($r["PART_NAME"]) : "";
        $partNum = isset($r["PART_NUM"]) ? clean_text($r["PART_NUM"]) : "";
        $packName = isset($r["PACK_NAME"]) ? clean_text($r["PACK_NAME"]) : "";
        $ordrPo = isset($r["ORDR_PO"]) ? clean_text($r["ORDR_PO"]) : "";
        $ordpRem = isset($r["ORDP_REM"]) ? clean_text($r["ORDP_REM"]) : "";

        $partNameDisplay = $partName;

        if ($partNum != "" && strpos($partNameDisplay, $partNum) === false) {
            $partNameDisplay = trim($partNameDisplay . " " . $partNum);
        }

        $deliveryText = fmt_qty($qty);

        if ($packName != "") {
            $deliveryText .= " " . $packName;
        }

        $remark = $ordpRem != "" ? $ordpRem : $ordrPo;
        ?>

        <tr>
            <td class="center"><?php echo h($i + 1); ?></td>
            <td><?php echo h($partNo); ?></td>
            <td><?php echo h($partNameDisplay); ?></td>
            <td class="right"><?php echo h(fmt_qty($poQty)); ?></td>
            <td class="center"><?php echo h($deliveryText); ?></td>
            <td><?php echo h($remark); ?></td>
        </tr>
    <?php } ?>
</table>

<br>

<table style="width:100%;">
    <tr>
        <td colspan="2">From :</td>
        <td colspan="2" class="center bold">PT. TOYO DENSO INDONESIA</td>
    </tr>
    <tr>
        <td class="center">Manager</td>
        <td class="center">Staff</td>
        <td colspan="2" class="center">Receiver</td>
    </tr>
    <tr>
        <td style="height:50px;"></td>
        <td></td>
        <td colspan="2"></td>
    </tr>
</table>

<br>

Note :<br>
1. Original White : TTEC Accounting Dept.<br>
2. Copy Red : Supplier<br>
3. Copy Red : Supplier<br>
4. Copy Green : TTEC Receiving

</body>
</html>
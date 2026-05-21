<?php
require_once __DIR__ . "/../config/db_plant2.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function fmt_date_id($value) {
    if ($value instanceof DateTime) {
        return $value->format("d-F-Y");
    }

    if ($value == "" || $value === null) {
        return "";
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return "";
    }

    return date("d-F-Y", $ts);
}

function fmt_qty($value) {
    return number_format((float)$value, 0, ",", ".");
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
    EXEC dbo.SP_RPT_DELIVERYSHEETwPO_cab ?
";

$stmt = sqlsrv_query($conn, $sql, array($di_id));

if ($stmt === false) {
    die("<pre>Query SP_RPT_DELIVERYSHEETwPO_cab gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $row;
}

if (count($rows) == 0) {
    die("Data Delivery Sheet Cabinindo tidak ditemukan untuk DI_ID: " . h($di_id));
}

$head = $rows[0];

$custComp  = isset($head["CUST_COMP"]) ? $head["CUST_COMP"] : "";
$custAddr1 = isset($head["CUST_ADDR1"]) ? $head["CUST_ADDR1"] : "";
$custAddr2 = isset($head["CUST_ADDR2"]) ? $head["CUST_ADDR2"] : "";
$custCity  = isset($head["CUST_CITY"]) ? $head["CUST_CITY"] : "";

$diDate = isset($head["DI_DATE"]) ? $head["DI_DATE"] : "";
$dsNo   = isset($head["DI_DSNO"]) ? $head["DI_DSNO"] : "";
$invNo  = isset($head["DI_INVNO"]) ? $head["DI_INVNO"] : "";

$filename = "DELIVERY_SHEET_CABININDO_" . clean_filename($dsNo) . ".xls";

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

        .company {
            font-weight: bold;
            font-size: 12pt;
        }

        .header {
            background: #D9EAF7;
            font-weight: bold;
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }
    </style>
</head>

<body>

<table class="no-border" style="width:100%;">
    <tr>
        <td colspan="3">
            <div class="company">P.T. IMC TEKNO INDONESIA</div>
            Kawasan Industri Kota Bukit Indah<br>
            Blok A-III No.15E Dangdeur Bungursari<br>
            Kab. Purwakarta, Jawa Barat 41181<br>
            Phone : (0264)351440
        </td>
        <td colspan="3" class="title">DELIVERY SHEET</td>
        <td colspan="3">
            Date : <?php echo h(fmt_date_id($diDate)); ?><br>
            DS.No : <?php echo h($dsNo); ?><br>
            INV.No : <?php echo h($invNo); ?>
        </td>
    </tr>

    <tr>
        <td colspan="9">&nbsp;</td>
    </tr>

    <tr>
        <td colspan="9">
            [MESSRS]<br>
            <b><?php echo h($custComp); ?></b><br>
            <?php echo h($custAddr1); ?><br>
            <?php echo h($custAddr2); ?><br>
            <?php echo h($custCity); ?>
        </td>
    </tr>
</table>

<br>

<table style="width:100%;">
    <tr class="header">
        <th style="width:35px;">No</th>
        <th style="width:100px;">Part Code</th>
        <th style="width:140px;">Part No</th>
        <th style="width:300px;">Part Name</th>
        <th style="width:70px;">Qty</th>
        <th style="width:70px;">Unit</th>
        <th style="width:120px;">P.O.#</th>
        <th style="width:90px;">Packing</th>
        <th style="width:70px;">P.Qty</th>
    </tr>

    <?php for ($i = 0; $i < count($rows); $i++) { ?>
        <?php
        $r = $rows[$i];

        $qty = isset($r["QTY"]) ? (float)$r["QTY"] : 0;

        $partCode = isset($r["PART_CODE"]) ? trim($r["PART_CODE"]) : "";
        $partNo   = isset($r["PART_NO"]) ? trim($r["PART_NO"]) : "";
        $partName = isset($r["PART_NAME"]) ? trim($r["PART_NAME"]) : "";
        $partNum  = isset($r["PART_NUM"]) ? trim($r["PART_NUM"]) : "";
        $unit     = isset($r["PART_UNIT"]) ? trim($r["PART_UNIT"]) : "";
        $poNo     = isset($r["ORDR_PO"]) ? trim($r["ORDR_PO"]) : "";

        $packing = "";
        if (isset($r["DIPA_PACK"]) && trim($r["DIPA_PACK"]) != "" && trim($r["DIPA_PACK"]) != "0") {
            $packing = trim($r["DIPA_PACK"]);
        }

        $pqty = "";
        if (isset($r["DIPA_PQTY"]) && (float)$r["DIPA_PQTY"] != 0) {
            $pqty = fmt_qty($r["DIPA_PQTY"]);
        }

        $locationCust = isset($r["LOCATION_CUST"]) ? trim($r["LOCATION_CUST"]) : "";

        $partNameDisplay = $partName;

        if ($partNum != "" && strpos($partNameDisplay, $partNum) === false) {
            $partNameDisplay = trim($partNameDisplay . " " . $partNum);
        }

        if ($locationCust != "" && strpos($partNameDisplay, $locationCust) === false) {
            $partNameDisplay = trim($partNameDisplay . " " . $locationCust);
        }
        ?>

        <tr>
            <td class="center"><?php echo h($i + 1); ?></td>
            <td><?php echo h($partCode); ?></td>
            <td><?php echo h($partNo); ?></td>
            <td><?php echo h($partNameDisplay); ?></td>
            <td class="right"><?php echo h(fmt_qty($qty)); ?></td>
            <td class="center"><?php echo h($unit); ?></td>
            <td><?php echo h($poNo); ?></td>
            <td class="center"><?php echo h($packing); ?></td>
            <td class="right"><?php echo h($pqty); ?></td>
        </tr>
    <?php } ?>
</table>

<br><br>

<table class="no-border" style="width:100%;">
    <tr>
        <td class="center">Delivered by</td>
        <td class="center">Knowledge by</td>
        <td class="center">Received by</td>
    </tr>
    <tr>
        <td style="height:60px;"></td>
        <td></td>
        <td></td>
    </tr>
    <tr>
        <td class="center">__________________</td>
        <td class="center">__________________</td>
        <td class="center">__________________</td>
    </tr>
</table>

</body>
</html>
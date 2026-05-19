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

function fmt_price($value) {
    return number_format((float)$value, 5, ",", ".");
}

function fmt_amount($value) {
    return number_format((float)$value, 2, ",", ".");
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
    EXEC dbo.SP_INVOICE ?
";

$stmt = sqlsrv_query($conn, $sql, array($di_id));

if ($stmt === false) {
    die("<pre>Query SP_INVOICE gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $row;
}

if (count($rows) == 0) {
    die("Data invoice tidak ditemukan untuk DI_ID: " . h($di_id));
}

$head = $rows[0];

$custComp  = isset($head["CUST_COMP"]) ? $head["CUST_COMP"] : "";
$custAddr1 = isset($head["CUST_ADDR1"]) ? $head["CUST_ADDR1"] : "";
$custAddr2 = isset($head["CUST_ADDR2"]) ? $head["CUST_ADDR2"] : "";
$custCity  = isset($head["CUST_CITY"]) ? $head["CUST_CITY"] : "";

$diDate = isset($head["DI_DATE"]) ? $head["DI_DATE"] : "";
$invNo  = isset($head["DI_INVNO"]) ? $head["DI_INVNO"] : "";
$dsNo   = isset($head["DI_DSNO"]) ? $head["DI_DSNO"] : "";

$currency = "";

if (isset($head["ORDR_CURR"]) && trim($head["ORDR_CURR"]) != "") {
    $currency = trim($head["ORDR_CURR"]);
} elseif (isset($head["CURR_CODE"]) && trim($head["CURR_CODE"]) != "") {
    $currency = trim($head["CURR_CODE"]);
}

if ($currency == "") {
    $currency = "IDR";
}

$totalAmount = 0;

$filename = "INVOICE_PO_" . clean_filename($invNo) . ".xls";

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

        .bold {
            font-weight: bold;
        }

        .italic {
            font-style: italic;
        }
    </style>
</head>

<body>

<table class="no-border" style="width:100%;">
    <tr>
        <td colspan="4">
            <div class="company">P.T. IMC TEKNO INDONESIA</div>
            Kawasan Industri Kota Bukit Indah<br>
            Blok A-III No.15E Dangdeur Bungursari<br>
            Kab. Purwakarta, Jawa Barat 41181<br>
            Phone : (0264)351440
        </td>
        <td colspan="3" class="title">INVOICE</td>
        <td colspan="2">
            Date : <?php echo h(fmt_date_id($diDate)); ?><br>
            Invoice No : <?php echo h($invNo); ?><br>
            DS.No. : <?php echo h($dsNo); ?>
        </td>
    </tr>

    <tr>
        <td colspan="9">&nbsp;</td>
    </tr>

    <tr>
        <td colspan="5">
            [MESSRS]<br>
            <b><?php echo h($custComp); ?></b><br>
            <?php echo h($custAddr1); ?><br>
            <?php echo h($custAddr2); ?><br>
            <?php echo h($custCity); ?>
        </td>
        <td colspan="4">
            Payment Term : 1 month<br><br>
            Currency : <?php echo h($currency); ?>
        </td>
    </tr>
</table>

<br>

<table style="width:100%;">
    <tr class="header">
        <th style="width:35px;">No.</th>
        <th style="width:130px;">Part No.Number</th>
        <th style="width:250px;">Part Name</th>
        <th style="width:70px;">Part Qty</th>
        <th style="width:70px;">Part Unit</th>
        <th style="width:90px;">Unit Price</th>
        <th style="width:110px;">Amount</th>
        <th style="width:100px;">P.O.#</th>
        <th style="width:90px;">Remark</th>
    </tr>

    <?php for ($i = 0; $i < count($rows); $i++) { ?>
        <?php
        $r = $rows[$i];

        $qty = isset($r["QTY"]) ? (float)$r["QTY"] : 0;

        $price = 0;

        if (isset($r["PART_PRICE"]) && (float)$r["PART_PRICE"] != 0) {
            $price = (float)$r["PART_PRICE"];
        } elseif (isset($r["ORDP_PRICE"])) {
            $price = (float)$r["ORDP_PRICE"];
        }

        $amount = $qty * $price;
        $totalAmount += $amount;

        $partCode = isset($r["PART_CODE"]) ? trim($r["PART_CODE"]) : "";
        $partNo   = isset($r["PART_NO"]) ? trim($r["PART_NO"]) : "";
        $partName = isset($r["PART_NAME"]) ? trim($r["PART_NAME"]) : "";
        $partNum  = isset($r["PART_NUM"]) ? trim($r["PART_NUM"]) : "";
        $unit     = isset($r["PART_UNIT"]) ? trim($r["PART_UNIT"]) : "";

        $poNo = "";

        if (isset($r["PRICE_CODE"]) && trim($r["PRICE_CODE"]) != "") {
            $poNo = trim($r["PRICE_CODE"]);
        } elseif (isset($r["ORDR_PO"])) {
            $poNo = trim($r["ORDR_PO"]);
        }

        $remark = isset($r["ORDP_REM"]) ? trim($r["ORDP_REM"]) : "";

        $partNoDisplay = $partNo;

        if ($partNoDisplay == "") {
            $partNoDisplay = $partCode;
        }

        $partNameDisplay = $partName;

        if ($partNum != "" && strpos($partNameDisplay, $partNum) === false) {
            $partNameDisplay = trim($partNameDisplay . " " . $partNum);
        }
        ?>

        <tr>
            <td class="center"><?php echo h($i + 1); ?></td>
            <td><?php echo h($partNoDisplay); ?></td>
            <td><?php echo h($partNameDisplay); ?></td>
            <td class="right"><?php echo h(fmt_qty($qty)); ?></td>
            <td class="center"><?php echo h($unit); ?></td>
            <td class="right"><?php echo h(fmt_price($price)); ?></td>
            <td class="right"><?php echo h(fmt_amount($amount)); ?></td>
            <td><?php echo h($poNo); ?></td>
            <td><?php echo h($remark); ?></td>
        </tr>
    <?php } ?>

    <tr>
        <td colspan="6" class="center bold italic">TOTAL AMOUNT</td>
        <td class="right bold italic"><?php echo h(fmt_amount($totalAmount)); ?></td>
        <td colspan="2"></td>
    </tr>
</table>

<br>

<table class="no-border" style="width:100%;">
    <tr>
        <td colspan="6" class="bold italic">
            Box / Tray Harap di kembalikan ke PT.IMC TEKNO INDONESIA
        </td>
        <td colspan="3" class="right italic">
            Authorized Signature
        </td>
    </tr>

    <tr>
        <td colspan="9" style="height:60px;"></td>
    </tr>

    <tr>
        <td colspan="9" class="italic">
            Received By :
        </td>
    </tr>
</table>

</body>
</html>
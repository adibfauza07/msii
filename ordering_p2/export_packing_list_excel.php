<?php
require_once __DIR__ . "/../config/db_plant2.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function clean_text($value) {
    return trim((string)$value);
}

function get_value($row, $keys, $default = 0) {
    for ($i = 0; $i < count($keys); $i++) {
        $key = $keys[$i];

        if (isset($row[$key]) && $row[$key] !== null && $row[$key] !== "") {
            return $row[$key];
        }
    }

    return $default;
}

function fmt_date_id($value) {
    if ($value instanceof DateTime) {
        return $value->format("d-M-Y");
    }

    if ($value == "" || $value === null) {
        return "";
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return "";
    }

    return date("d-M-Y", $ts);
}

function fmt_qty($value) {
    return number_format((float)$value, 0, ",", ".");
}

function fmt_kg($value) {
    return number_format((float)$value, 3, ",", ".");
}

function fmt_gr($value) {
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
    EXEC dbo.SP_PACKINGLIST ?
";

$stmt = sqlsrv_query($conn, $sql, array($di_id));

if ($stmt === false) {
    die("<pre>Query SP_PACKINGLIST gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $row;
}

if (count($rows) == 0) {
    die("Data Packing List tidak ditemukan untuk DI_ID: " . h($di_id));
}

$head = $rows[0];

$custComp  = isset($head["CUST_COMP"]) ? clean_text($head["CUST_COMP"]) : "";
$custAddr1 = isset($head["CUST_ADDR1"]) ? clean_text($head["CUST_ADDR1"]) : "";
$custAddr2 = isset($head["CUST_ADDR2"]) ? clean_text($head["CUST_ADDR2"]) : "";
$custCity  = isset($head["CUST_CITY"]) ? clean_text($head["CUST_CITY"]) : "";

$diDate = isset($head["DI_DATE"]) ? $head["DI_DATE"] : "";
$invNo  = isset($head["DI_INVNO"]) ? clean_text($head["DI_INVNO"]) : "";

$totalPartQty      = 0;
$totalPartWeightKg = 0;
$totalPackQty      = 0;
$totalWeightKg     = 0;

$detailRows = array();

for ($i = 0; $i < count($rows); $i++) {
    $r = $rows[$i];

    $partCode = isset($r["PART_CODE"]) ? clean_text($r["PART_CODE"]) : "";
    $partNo   = isset($r["PART_NO"]) ? clean_text($r["PART_NO"]) : "";
    $partName = isset($r["PART_NAME"]) ? clean_text($r["PART_NAME"]) : "";
    $partNum  = isset($r["PART_NUM"]) ? clean_text($r["PART_NUM"]) : "";

    $qty     = isset($r["DIPA_QTY"]) ? (float)$r["DIPA_QTY"] : 0;
    $packQty = isset($r["DIPA_PQTY"]) ? (float)$r["DIPA_PQTY"] : 0;

    /*
        PART_WEIGHT biasanya berat per pcs dalam gram.
        PART_WEIGHT_KG jika sudah ada dari SP berarti total berat part dalam kg.
    */
    $partWeightGram = (float)get_value($r, array("PART_WEIGHT"), 0);

    if (isset($r["PART_WEIGHT_KG"])) {
        $partWeightKg = (float)$r["PART_WEIGHT_KG"];
    } else {
        $partWeightKg = ($qty * $partWeightGram) / 1000;
    }

    /*
        PACK_WEIGHT_GR / PACK_WEIGHT = berat packing per box dalam gram.
        Jangan pakai PWEIGHT untuk kolom Weight(gr),
        karena PWEIGHT adalah total packing weight dalam kg.
    */
    $packWeightGram = (float)get_value($r, array("PACK_WEIGHT_GR", "PACK_WEIGHT"), 0);

    /*
        Total weight kg:
        - Pakai TOTAL_WEIGHT_KG jika SP baru sudah menyediakan.
        - Kalau belum, pakai TWEIGHT.
        - Kalau tidak ada, hitung manual.
    */
    if (isset($r["TOTAL_WEIGHT_KG"])) {
        $totalLineWeightKg = (float)$r["TOTAL_WEIGHT_KG"];
    } elseif (isset($r["TWEIGHT"])) {
        $totalLineWeightKg = (float)$r["TWEIGHT"];
    } else {
        $packWeightKgTotal = ($packWeightGram * $packQty) / 1000;
        $totalLineWeightKg = $partWeightKg + $packWeightKgTotal;
    }

    $partNoDisplay = $partNo;

    if ($partNoDisplay == "") {
        $partNoDisplay = $partCode;
    }

    $partNameDisplay = $partName;

    if ($partNum != "" && strpos($partNameDisplay, $partNum) === false) {
        $partNameDisplay = trim($partNameDisplay . " " . $partNum);
    }

    $detailRows[] = array(
        "PART_NO"         => $partNoDisplay,
        "PART_NAME"       => $partNameDisplay,
        "DIPA_QTY"        => $qty,
        "PART_WEIGHT_KG"  => $partWeightKg,
        "DIPA_PQTY"       => $packQty,
        "PACK_WEIGHT_GR"  => $packWeightGram,
        "TOTAL_WEIGHT_KG" => $totalLineWeightKg
    );

    $totalPartQty      += $qty;
    $totalPartWeightKg += $partWeightKg;
    $totalPackQty      += $packQty;
    $totalWeightKg     += $totalLineWeightKg;
}

$filename = "PACKING_LIST_" . clean_filename($invNo) . ".xls";

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

        .text {
            mso-number-format: "\@";
        }

        .num {
            mso-number-format: "#,##0.000";
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

        <td colspan="2" class="title">PACKING LIST</td>

        <td colspan="3">
            Date : <?php echo h(fmt_date_id($diDate)); ?><br>
            Invoice # : <?php echo h($invNo); ?>
        </td>
    </tr>

    <tr>
        <td colspan="8">&nbsp;</td>
    </tr>

    <tr>
        <td colspan="8">
            [MESSRS]<br>
            <b><?php echo h($custComp); ?></b><br>
            <?php if ($custAddr1 != "") { echo h($custAddr1) . "<br>"; } ?>
            <?php if ($custAddr2 != "") { echo h($custAddr2) . "<br>"; } ?>
            <?php if ($custCity != "") { echo h($custCity); } ?>
        </td>
    </tr>
</table>

<br>

<table style="width:100%;">
    <tr class="header">
        <th rowspan="2" style="width:40px;">#</th>
        <th rowspan="2" style="width:150px;">PART NUMBER</th>
        <th rowspan="2" style="width:320px;">PART NAME</th>
        <th colspan="2">PART</th>
        <th colspan="2">PACKING</th>
        <th rowspan="2" style="width:120px;">TOTAL<br>Weight(kg)</th>
    </tr>
    <tr class="header">
        <th style="width:90px;">Qty(pcs)</th>
        <th style="width:110px;">Weight(kg)</th>
        <th style="width:90px;">Qty(box)</th>
        <th style="width:110px;">Weight(gr)</th>
    </tr>

    <?php for ($i = 0; $i < count($detailRows); $i++) { ?>
        <?php $r = $detailRows[$i]; ?>

        <tr>
            <td class="center"><?php echo h($i + 1); ?></td>
            <td class="text"><?php echo h($r["PART_NO"]); ?></td>
            <td><?php echo h($r["PART_NAME"]); ?></td>
            <td class="right"><?php echo h(fmt_qty($r["DIPA_QTY"])); ?></td>
            <td class="right"><?php echo h(fmt_kg($r["PART_WEIGHT_KG"])); ?></td>
            <td class="right"><?php echo h(fmt_qty($r["DIPA_PQTY"])); ?></td>
            <td class="right"><?php echo h(fmt_gr($r["PACK_WEIGHT_GR"])); ?></td>
            <td class="right"><?php echo h(fmt_kg($r["TOTAL_WEIGHT_KG"])); ?></td>
        </tr>
    <?php } ?>

    <tr>
        <td colspan="3" class="center bold">TOTAL</td>
        <td class="right bold"><?php echo h(fmt_qty($totalPartQty)); ?></td>
        <td class="right bold"><?php echo h(fmt_kg($totalPartWeightKg)); ?></td>
        <td class="right bold"><?php echo h(fmt_qty($totalPackQty)); ?></td>
        <td class="right bold"></td>
        <td class="right bold"><?php echo h(fmt_kg($totalWeightKg)); ?></td>
    </tr>
</table>

<br>

<table class="no-border" style="width:100%;">
    <tr>
        <td colspan="8" class="right">
            <i><u>Authorized Signature</u></i>
        </td>
    </tr>
</table>

</body>
</html>
<?php
require_once __DIR__ . '/config/database.php';

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

function fmt_datetime_header() {
    return date("d/m/y H:i:s");
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
        PART_WEIGHT_KG kalau SP baru sudah menyediakan total berat part dalam kg.
    */
    $partWeightGram = (float)get_value($r, array("PART_WEIGHT"), 0);

    if (isset($r["PART_WEIGHT_KG"])) {
        $partWeightKg = (float)$r["PART_WEIGHT_KG"];
    } else {
        $partWeightKg = ($qty * $partWeightGram) / 1000;
    }

    /*
        PACK_WEIGHT_GR / PACK_WEIGHT = berat packing per box dalam gram.
        Jangan pakai PWEIGHT untuk kolom Packing Weight(gr),
        karena PWEIGHT adalah total berat packing dalam kg.
    */
    $packWeightGram = (float)get_value($r, array("PACK_WEIGHT_GR", "PACK_WEIGHT"), 0);

    /*
        Total weight:
        Jika SP sudah punya TOTAL_WEIGHT_KG / TWEIGHT, pakai itu.
        Kalau belum, hitung manual:
        part total kg + packing total kg.
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
        "PART_NO"          => $partNoDisplay,
        "PART_NAME"        => $partNameDisplay,
        "DIPA_QTY"         => $qty,
        "PART_WEIGHT_KG"   => $partWeightKg,
        "DIPA_PQTY"        => $packQty,
        "PACK_WEIGHT_GR"   => $packWeightGram,
        "TOTAL_WEIGHT_KG"  => $totalLineWeightKg
    );

    $totalPartQty      += $qty;
    $totalPartWeightKg += $partWeightKg;
    $totalPackQty      += $packQty;
    $totalWeightKg     += $totalLineWeightKg;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Packing List - <?php echo h($invNo); ?></title>

    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        body {
            margin: 0;
            background: #9a9a9a;
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000000;
        }

        .print-bar {
            width: 210mm;
            margin: 10px auto;
            text-align: right;
        }

        .print-bar button {
            padding: 6px 14px;
            font-size: 11px;
            cursor: pointer;
        }

        .paper {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: #ffffff;
            border: 2px solid #000000;
            padding: 7mm;
            box-sizing: border-box;
            overflow: hidden;
        }

        .top {
            display: grid;
            grid-template-columns: 38% 24% 38%;
            align-items: start;
            width: 100%;
        }

        .company {
            font-size: 12px;
            line-height: 15px;
        }

        .company-name {
            font-weight: bold;
            font-size: 15px;
            letter-spacing: 1px;
        }

        .title {
            text-align: center;
            font-size: 25px;
            font-weight: bold;
            letter-spacing: 2px;
            padding-top: 28px;
        }

        .right-head {
            font-size: 11px;
            line-height: 15px;
        }

        .right-topline {
            display: flex;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        .info-line {
            display: grid;
            grid-template-columns: 90px 1fr;
        }

        .info-line .label {
            text-align: right;
            padding-right: 5px;
        }

        .info-line .value {
            text-align: right;
        }

        .separator-dash {
            border-top: 1px dashed #000000;
            margin: 10px 0 7px 0;
        }

        .customer-area {
            min-height: 70px;
            font-size: 12px;
            line-height: 16px;
        }

        .messrs {
            margin-bottom: 4px;
        }

        .cust-name {
            font-weight: bold;
            font-size: 13px;
            letter-spacing: 1px;
        }

        .detail {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 6px;
            font-size: 12px;
        }

        .detail th,
        .detail td {
            border: 1px solid #000000;
            padding: 4px 3px;
            height: 28px;
            line-height: 14px;
            font-size: 11px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: clip;
            box-sizing: border-box;
        }

        .detail th {
            text-align: center;
            font-weight: bold;
        }

        .no {
            width: 4%;
            text-align: center;
        }

        .partno {
            width: 20%;
            text-align: left;
        }

        .partname {
            width: 36%;
            text-align: left;
        }

        .qtypcs {
            width: 8%;
            text-align: right;
        }

        .partweight {
            width: 10%;
            text-align: right;
        }

        .packqty {
            width: 8%;
            text-align: right;
        }

        .packweight {
            width: 9%;
            text-align: right;
        }

        .totalweight {
            width: 10%;
            text-align: right;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        .center {
            text-align: center;
        }

        .total-row td {
            font-weight: bold;
            border-top: 1px dashed #000000;
            border-bottom: 1px dashed #000000;
        }

        .separator-bottom {
            border-top: 1px dashed #000000;
            margin-top: 7px;
        }

        .signature {
            margin-top: 12px;
            text-align: right;
            padding-right: 70px;
            font-size: 12px;
            font-style: italic;
            text-decoration: underline;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .print-bar {
                display: none;
            }

            .paper {
                width: 196mm;
                min-height: 281mm;
                margin: 0;
                border: 2px solid #000000;
                padding: 5mm;
            }

            .detail th,
            .detail td {
                font-size: 11px;
                line-height: 14px;
                height: 27px;
                padding: 3px 3px;
            }

            .company,
            .right-head,
            .customer-area {
                font-size: 11px;
            }

            .company-name {
                font-size: 13px;
            }

            .cust-name {
                font-size: 12px;
            }

            .title {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>

<div class="print-bar">
    <button onclick="window.print()">PRINT</button>
    <button onclick="window.location.href='export_packing_list_excel.php?DI_ID=<?php echo h($di_id); ?>'">EXPORT EXCEL</button>
    <button onclick="window.close()">CLOSE</button>
</div>

<div class="paper">

    <div class="top">
        <div class="company">
            <div class="company-name">P.T. IMC TEKNO INDONESIA</div>
            Kawasan Industri Kota Bukit Indah<br>
            Blok A-III No.15E Dangdeur Bungursari<br>
            Kab. Purwakarta, Jawa Barat 41181<br>
            Phone : (0264)351440
        </div>

        <div class="title">PACKING LIST</div>

        <div class="right-head">
            <div class="right-topline">
                <div><?php echo h(fmt_datetime_header()); ?></div>
                <div>
                    FM.CO.00-59<br>
                    Page 1 of 1
                </div>
            </div>

            <div class="info-line">
                <div class="label">Date :</div>
                <div class="value"><?php echo h(fmt_date_id($diDate)); ?></div>
            </div>

            <div class="info-line">
                <div class="label">Invoice # :</div>
                <div class="value"><?php echo h($invNo); ?></div>
            </div>
        </div>
    </div>

    <div class="separator-dash"></div>

    <div class="customer-area">
        <div class="messrs">[MESSRS]</div>
        <div class="cust-name"><?php echo h($custComp); ?></div>

        <?php if ($custAddr1 != "") { ?>
            <?php echo h($custAddr1); ?><br>
        <?php } ?>

        <?php if ($custAddr2 != "") { ?>
            <?php echo h($custAddr2); ?><br>
        <?php } ?>

        <?php if ($custCity != "") { ?>
            <?php echo h($custCity); ?>
        <?php } ?>
    </div>

    <table class="detail">
        <thead>
            <tr>
                <th class="no" rowspan="2">#</th>
                <th class="partno" rowspan="2">PART NUMBER</th>
                <th class="partname" rowspan="2">PART NAME</th>
                <th colspan="2">PART</th>
                <th colspan="2">PACKING</th>
                <th class="totalweight" rowspan="2">TOTAL<br>Weight (kg)</th>
            </tr>
            <tr>
                <th class="qtypcs">Qty (pcs)</th>
                <th class="partweight">Weight (kg)</th>
                <th class="packqty">Qty (box)</th>
                <th class="packweight">Weight (gr)</th>
            </tr>
        </thead>

        <tbody>
            <?php for ($i = 0; $i < count($detailRows); $i++) { ?>
                <?php $r = $detailRows[$i]; ?>

                <tr>
                    <td class="no"><?php echo h($i + 1); ?></td>
                    <td class="partno"><?php echo h($r["PART_NO"]); ?></td>
                    <td class="partname"><?php echo h($r["PART_NAME"]); ?></td>
                    <td class="qtypcs num"><?php echo h(fmt_qty($r["DIPA_QTY"])); ?></td>
                    <td class="partweight num"><?php echo h(fmt_kg($r["PART_WEIGHT_KG"])); ?></td>
                    <td class="packqty num"><?php echo h(fmt_qty($r["DIPA_PQTY"])); ?></td>
                    <td class="packweight num"><?php echo h(fmt_gr($r["PACK_WEIGHT_GR"])); ?></td>
                    <td class="totalweight num"><?php echo h(fmt_kg($r["TOTAL_WEIGHT_KG"])); ?></td>
                </tr>
            <?php } ?>

            <tr class="total-row">
                <td colspan="3" class="center">TOTAL</td>
                <td class="qtypcs num"><?php echo h(fmt_qty($totalPartQty)); ?></td>
                <td class="partweight num"><?php echo h(fmt_kg($totalPartWeightKg)); ?></td>
                <td class="packqty num"><?php echo h(fmt_qty($totalPackQty)); ?></td>
                <td class="packweight num"></td>
                <td class="totalweight num"><?php echo h(fmt_kg($totalWeightKg)); ?></td>
            </tr>
        </tbody>
    </table>

    <div class="separator-bottom"></div>

    <div class="signature">Authorized Signature</div>

</div>

</body>
</html>
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

function fmt_date($value) {
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

$di_id = isset($_GET["DI_ID"]) ? intval($_GET["DI_ID"]) : 0;

if ($di_id <= 0) {
    die("DI_ID tidak valid.");
}

$sql = "
    SET NOCOUNT ON;
    EXEC dbo.SP_RPT_DELIVERYSHEETwPO1 ?
";

$stmt = sqlsrv_query($conn, $sql, array($di_id));

if ($stmt === false) {
    die("<pre>Query SP_RPT_DELIVERYSHEETwPO1 gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $row;
}

if (count($rows) == 0) {
    die("Data Surat Jalan tidak ditemukan untuk DI_ID: " . h($di_id));
}

$head = $rows[0];

$custComp  = isset($head["CUST_COMP"]) ? clean_text($head["CUST_COMP"]) : "";
$custAddr1 = isset($head["CUST_ADDR1"]) ? clean_text($head["CUST_ADDR1"]) : "";
$custAddr2 = isset($head["CUST_ADDR2"]) ? clean_text($head["CUST_ADDR2"]) : "";
$custCity  = isset($head["CUST_CITY"]) ? clean_text($head["CUST_CITY"]) : "";

$diDate = isset($head["DI_DATE"]) ? $head["DI_DATE"] : "";
$dsNo   = isset($head["DI_DSNO"]) ? clean_text($head["DI_DSNO"]) : "";
$invNo  = isset($head["DI_INVNO"]) ? clean_text($head["DI_INVNO"]) : "";
$diNo   = isset($head["DI_NO"]) ? clean_text($head["DI_NO"]) : "";

$poList = array();

$totalQty = 0;

for ($i = 0; $i < count($rows); $i++) {
    $po = isset($rows[$i]["ORDR_PO"]) ? clean_text($rows[$i]["ORDR_PO"]) : "";

    if ($po != "" && !in_array($po, $poList)) {
        $poList[] = $po;
    }

    $totalQty += isset($rows[$i]["QTY"]) ? (float)$rows[$i]["QTY"] : 0;
}

$poHeader = implode(", ", $poList);

/*
    Area detail dibuat full.
    Data sedikit = sisanya ruang kosong tetap bergaris.
*/
$minDetailRows = 18;
$totalDetailRows = count($rows);

if ($totalDetailRows < $minDetailRows) {
    $totalDetailRows = $minDetailRows;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Surat Jalan - <?php echo h($dsNo); ?></title>

    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        body {
            margin: 0;
            background: #9a9a9a;
            font-family: "Courier New", monospace;
            font-size: 11px;
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
            padding: 8mm;
            box-sizing: border-box;
            overflow: hidden;
        }

        .top {
            display: grid;
            grid-template-columns: 55% 45%;
            align-items: start;
        }

        .company {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 13px;
        }

        .company-name {
            font-weight: bold;
            font-size: 12px;
        }

        .customer-to {
            font-size: 10px;
            line-height: 13px;
            padding-left: 25px;
        }

        .cust-title {
            font-weight: bold;
        }

        .cust-name {
            font-weight: bold;
            font-size: 12px;
            font-family: Arial, sans-serif;
        }

        .title {
            text-align: center;
            font-family: Arial, sans-serif;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 1px;
            margin: 10px 0 8px 0;
        }

        .header-info {
            width: 55%;
            margin-top: 6px;
            font-size: 11px;
            line-height: 16px;
        }

        .header-row {
            display: grid;
            grid-template-columns: 70px 12px 1fr;
        }

        .detail {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 6px;
            font-size: 10px;
        }

       .detail th {
    border: 1px solid #000000;
    padding: 3px 3px;
    height: 28px;
    line-height: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: clip;
    box-sizing: border-box;
    text-align: center;
    font-weight: bold;
}

.detail td {
    border-left: 1px solid #000000;
    border-right: 1px solid #000000;
    border-top: none;
    border-bottom: none;
    padding: 3px 3px;
    height: 28px;
    line-height: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: clip;
    box-sizing: border-box;
}

        .detail th {
            text-align: center;
            font-weight: bold;
        }

        .detail .no {
            width: 4%;
            text-align: center;
        }

        .detail .date {
            width: 15%;
            text-align: center;
        }

        .detail .partno {
            width: 15%;
            text-align: left;
        }

        .detail .partname {
            width: 28%;
            text-align: left;
        }

        .detail .qty {
            width: 12%;
            text-align: right;
        }

        .detail .unit {
            width: 8%;
            text-align: center;
        }

        .detail .ket {
            width: 18%;
            text-align: center;
        }

        .blank-row td {
            height: 20px;
            line-height: 20px;
        }

        .blank-big td {
            height: 500px;
            line-height: 500px;
        }

        .total-row td {
    font-weight: bold;
    height: 22px;
    border-top: 1px solid #000000;
    border-bottom: 1px solid #000000;
}

        .footer-code {
            display: grid;
            grid-template-columns: 1fr 1fr;
            font-size: 9px;
            margin-top: 3px;
        }

        .footer-code .right {
            text-align: right;
        }

        .sign-area {
            display: grid;
            grid-template-columns: 1fr 1fr;
            margin-top: 45px;
            font-size: 11px;
        }

        .sign-box {
            text-align: center;
            height: 75px;
            position: relative;
        }

        .sign-line {
            position: absolute;
            left: 28%;
            right: 28%;
            bottom: 0;
            border-top: 1px solid #000000;
            height: 1px;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .print-bar {
                display: none;
            }

            .paper {
                width: 194mm;
                min-height: 281mm;
                margin: 0;
                border: 2px solid #000000;
                padding: 6mm;
            }

            .detail th,
            .detail td {
                font-size: 9.5px;
                line-height: 11px;
            }

            .blank-big td {
                height: 185px;
                line-height: 185px;
            }

            .title {
                font-size: 23px;
            }
        }
    </style>
</head>

<body>

<div class="print-bar">
    <button onclick="window.print()">PRINT</button>
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

        <div class="customer-to">
            <div class="cust-title">Kepada Yth.</div>
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
    </div>

    <div class="title">SURAT JALAN</div>

    <div class="header-info">
        <div class="header-row">
            <div>Po. No.</div>
            <div>:</div>
            <div><?php echo h($poHeader); ?></div>
        </div>
        <div class="header-row">
            <div>Tanggal</div>
            <div>:</div>
            <div><?php echo h(fmt_date($diDate)); ?></div>
        </div>
        <div class="header-row">
            <div>SJ No.</div>
            <div>:</div>
            <div><?php echo h($dsNo); ?></div>
        </div>
    </div>

    <table class="detail">
        <thead>
            <tr>
                <th class="no">No.</th>
                <th class="date">Due Date</th>
                <th class="partno">Part No.</th>
                <th class="partname">Part Name</th>
                <th class="qty">Quantity</th>
                <th class="unit">(PCS)</th>
                <th class="ket">Keterangan</th>
            </tr>
        </thead>

        <tbody>
            <?php for ($i = 0; $i < count($rows); $i++) { ?>
                <?php
                $r = $rows[$i];

                $qty = isset($r["QTY"]) ? (float)$r["QTY"] : 0;
                $partNo = isset($r["PART_NO"]) ? clean_text($r["PART_NO"]) : "";
                $partName = isset($r["PART_NAME"]) ? clean_text($r["PART_NAME"]) : "";
                $partNum = isset($r["PART_NUM"]) ? clean_text($r["PART_NUM"]) : "";
                $unit = isset($r["PART_UNIT"]) ? clean_text($r["PART_UNIT"]) : "Pcs";
                $poNo = isset($r["ORDR_PO"]) ? clean_text($r["ORDR_PO"]) : "";

                $partNameDisplay = $partName;

                if ($partNum != "" && strpos($partNameDisplay, $partNum) === false) {
                    $partNameDisplay = trim($partNameDisplay . " " . $partNum);
                }
                ?>
                <tr>
                    <td class="no"><?php echo h($i + 1); ?></td>
                    <td class="date"><?php echo h(fmt_date($diDate)); ?></td>
                    <td class="partno"><?php echo h($partNo); ?></td>
                    <td class="partname"><?php echo h($partNameDisplay); ?></td>
                    <td class="qty"><?php echo h(fmt_qty($qty)); ?></td>
                    <td class="unit"><?php echo h($unit); ?></td>
                    <td class="ket"><?php echo h($poNo); ?></td>
                </tr>
            <?php } ?>

            <tr class="blank-big">
                <td class="no">&nbsp;</td>
                <td class="date">&nbsp;</td>
                <td class="partno">&nbsp;</td>
                <td class="partname">&nbsp;</td>
                <td class="qty">&nbsp;</td>
                <td class="unit">&nbsp;</td>
                <td class="ket">&nbsp;</td>
            </tr>

            <tr class="total-row">
                <td colspan="4" style="text-align:center;">JUMLAH</td>
                <td class="qty"><?php echo h(fmt_qty($totalQty)); ?></td>
                <td class="unit">pcs</td>
                <td class="ket">&nbsp;</td>
            </tr>
        </tbody>
    </table>

    <div class="footer-code">
        <div>FM.CO.00-05</div>
        <div class="right"><?php echo h(fmt_date($diDate)); ?></div>
    </div>

    <div class="sign-area">
        <div class="sign-box">
            Penerima
            <div class="sign-line"></div>
        </div>

        <div class="sign-box">
            Hormat Kami
            <div class="sign-line"></div>
        </div>
    </div>

</div>

</body>
</html>
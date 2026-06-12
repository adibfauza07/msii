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

function fmt_datetime_header() {
    return date("d/m/y H:i:s");
}

function fmt_qty($value) {
    return number_format((float)$value, 0, ",", ".");
}

function clean_text($value) {
    return trim((string)$value);
}

/*
    PACK_NAME dipendekkan.
    Contoh:
    - Impraboard Box -> Box
    - Plastic Box    -> Box
    - Bucket         -> Bucket
*/
function short_pack_name($value) {
    $value = trim((string)$value);

    if ($value == "") {
        return "";
    }

    $upper = strtoupper($value);

    if (strpos($upper, "BOX") !== false) {
        return "Box";
    }

    if (strpos($upper, "BUCKET") !== false) {
        return "Bucket";
    }

    if (strpos($upper, "BAG") !== false) {
        return "Bag";
    }

    if (strpos($upper, "PALLET") !== false) {
        return "Pallet";
    }

    if (strpos($upper, "PCS") !== false) {
        return "Pcs";
    }

    return $value;
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

$diDate  = isset($head["DI_DATE"]) ? $head["DI_DATE"] : "";
$dsNo    = isset($head["DI_DSNO"]) ? clean_text($head["DI_DSNO"]) : "";
$invNo   = isset($head["DI_INVNO"]) ? clean_text($head["DI_INVNO"]) : "";
$driver  = isset($head["DRIVER"]) ? clean_text($head["DRIVER"]) : "";
$truckNo = isset($head["NO_POLISI"]) ? clean_text($head["NO_POLISI"]) : "";

$poList = array();

for ($i = 0; $i < count($rows); $i++) {
    $po = isset($rows[$i]["ORDR_PO"]) ? clean_text($rows[$i]["ORDR_PO"]) : "";

    if ($po != "" && !in_array($po, $poList)) {
        $poList[] = $po;
    }
}

$poHeader = implode(", ", $poList);

/*
    Jumlah baris detail dibuat tetap supaya garis tabel selalu full.
    Kalau data cuma sedikit, sisanya otomatis baris kosong.
    Ubah ke 28 kalau mau garis lebih panjang.
*/
$minDetailRows = 25;
$totalDetailRows = count($rows);

if ($totalDetailRows < $minDetailRows) {
    $totalDetailRows = $minDetailRows;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Selling Card - <?php echo h($dsNo); ?></title>

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

        .title {
            text-align: center;
            font-family: Arial, sans-serif;
            font-size: 23px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-bottom: 6px;
        }

        .top-area {
            display: grid;
            grid-template-columns: 31% 22% 47%;
            gap: 6px;
            align-items: start;
        }

        .company {
            font-family: Arial, sans-serif;
            font-size: 9px;
            line-height: 12px;
        }

        .company-name {
            font-weight: bold;
            font-size: 11px;
        }

        .vendor-box,
        .to-box {
            border: 1px solid #000000;
            font-size: 11px;
            line-height: 14px;
        }

        .vendor-row {
            display: grid;
            grid-template-columns: 1fr;
            border-bottom: 1px solid #000000;
            padding: 2px 4px;
            text-align: center;
        }

        .vendor-row:last-child {
            border-bottom: none;
        }

        .vendor-label {
            font-weight: bold;
            text-decoration: underline;
            font-size: 10px;
        }

        .vendor-value {
            font-weight: bold;
        }

        .to-box {
            display: grid;
            grid-template-columns: 45px 1fr;
            min-height: 68px;
        }

        .to-label {
            padding: 4px;
            border-right: 1px solid #000000;
            font-weight: bold;
        }

        .to-content {
            padding: 4px;
            line-height: 14px;
        }

        .doc-grid {
            margin-top: 8px;
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 11px;
        }

        .doc-grid th,
        .doc-grid td {
            border: 1px solid #000000;
            padding: 3px 4px;
            height: 18px;
            white-space: nowrap;
            overflow: hidden;
        }

        .doc-grid th {
            text-align: center;
            font-weight: bold;
        }

        .detail {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 8px;
            font-size: 10px;
        }

        .detail th,
        .detail td {
            border: 1px solid #000000;
            padding: 2px 3px;
            height: 16px;
            line-height: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: clip;
        }

        .detail th {
            text-align: center;
            font-weight: bold;
            background: #ffffff;
        }

        .detail .blank-row td {
            height: 17px;
            line-height: 17px;
        }

        .no {
            width: 5%;
            text-align: center;
        }

        .key {
            width: 18%;
            text-align: left;
        }

        .partname {
            width: 34%;
            text-align: left;
        }

        .poqty {
            width: 8%;
            text-align: right;
        }

        .delqty {
            width: 18%;
            text-align: center;
        }

        .remark {
            width: 17%;
            text-align: center;
        }

        .detail .delqty {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: clip;
        }

        .sign-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 14px;
            font-size: 10px;
        }

        .sign-table td {
            border: 1px solid #000000;
            height: 38px;
            text-align: center;
            vertical-align: top;
            padding: 3px;
        }

        .sign-table .small {
            height: 18px;
            font-size: 9px;
        }

        .note {
            margin-top: 10px;
            font-size: 10px;
            line-height: 13px;
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

            .title {
                font-size: 22px;
            }

            .detail th,
            .detail td {
                font-size: 9.5px;
                line-height: 11px;
            }

            .detail .blank-row td {
                height: 17px;
                line-height: 17px;
            }

            .doc-grid,
            .vendor-box,
            .to-box {
                font-size: 10px;
            }
        }
    </style>
</head>

<body>

<div class="print-bar">
    <button onclick="window.print()">PRINT</button>
    <button onclick="window.location.href='export_ds_toyodenso_excel.php?DI_ID=<?php echo h($di_id); ?>'">EXPORT EXCEL</button>
    <button onclick="window.close()">CLOSE</button>
</div>

<div class="paper">

    <div class="title">SELLING CARD</div>

    <div class="top-area">
        <div class="company">
            <div class="company-name">P.T. IMC TEKNO INDONESIA</div>
            Kawasan Industri Kota Bukit Indah<br>
            Blok A-III No.15E Dangdeur Bungursari, Jawa Barat<br>
            Phone : (0264)351440
        </div>

        <div class="vendor-box">
            <div class="vendor-row">
                <div class="vendor-label">VENDOR NO</div>
                <div class="vendor-value">1900.5R</div>
            </div>
            <div class="vendor-row">
                <div class="vendor-label">VENDOR NAME</div>
                <div class="vendor-value">IMC</div>
            </div>
        </div>

        <div class="to-box">
            <div class="to-label">TO :</div>
            <div class="to-content">
                <b><?php echo h($custComp); ?></b><br>
                <?php echo h($custAddr1); ?><br>
                <?php echo h($custAddr2); ?><br>
                <?php echo h($custCity); ?>
            </div>
        </div>
    </div>

    <table class="doc-grid">
        <tr>
            <th style="width:22%;">PO NUMBER</th>
            <th style="width:20%;">DO-NO</th>
            <th style="width:18%;">DATE</th>
            <th style="width:20%;">DRIVER</th>
            <th style="width:20%;">TRUCK NO</th>
        </tr>
        <tr>
            <td><?php echo h($poHeader); ?></td>
            <td style="text-align:center;"><?php echo h($dsNo); ?></td>
            <td style="text-align:center;"><?php echo h(fmt_date($diDate)); ?></td>
            <td style="text-align:center;"><?php echo h($driver); ?></td>
            <td style="text-align:center;"><?php echo h($truckNo); ?></td>
        </tr>
    </table>

    <table class="detail">
        <thead>
            <tr>
                <th class="no">NO</th>
                <th class="key">KEY NUMBER</th>
                <th class="partname">PART NAME</th>
                <th class="poqty">P.O QTY</th>
                <th class="delqty">DELIVERY<br>QTY</th>
                <th class="remark">REMARK</th>
            </tr>
        </thead>

        <tbody>
            <?php for ($i = 0; $i < $totalDetailRows; $i++) { ?>

                <?php if ($i < count($rows)) { ?>

                    <?php
                    $r = $rows[$i];

                    $qty = isset($r["QTY"]) ? (float)$r["QTY"] : 0;
                    $poQty = isset($r["POQTY"]) ? (float)$r["POQTY"] : 0;

                    $partNo   = isset($r["PART_NO"]) ? clean_text($r["PART_NO"]) : "";
                    $partName = isset($r["PART_NAME"]) ? clean_text($r["PART_NAME"]) : "";
                    $partNum  = isset($r["PART_NUM"]) ? clean_text($r["PART_NUM"]) : "";
                    $packName = isset($r["PACK_NAME"]) ? short_pack_name($r["PACK_NAME"]) : "";
                    $ordrPo   = isset($r["ORDR_PO"]) ? clean_text($r["ORDR_PO"]) : "";
                    $ordpRem  = isset($r["ORDP_REM"]) ? clean_text($r["ORDP_REM"]) : "";

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
                        <td class="no"><?php echo h($i + 1); ?></td>
                        <td class="key"><?php echo h($partNo); ?></td>
                        <td class="partname"><?php echo h($partNameDisplay); ?></td>
                        <td class="poqty"><?php echo h(fmt_qty($poQty)); ?></td>
                        <td class="delqty"><?php echo h($deliveryText); ?></td>
                        <td class="remark"><?php echo h($remark); ?></td>
                    </tr>

                <?php } else { ?>

                    <tr class="blank-row">
                        <td class="no">&nbsp;</td>
                        <td class="key">&nbsp;</td>
                        <td class="partname">&nbsp;</td>
                        <td class="poqty">&nbsp;</td>
                        <td class="delqty">&nbsp;</td>
                        <td class="remark">&nbsp;</td>
                    </tr>

                <?php } ?>

            <?php } ?>
        </tbody>
    </table>

    <table class="sign-table">
        <tr>
            <td colspan="2" style="text-align:left;">From :</td>
            <td colspan="2"><b>PT. TOYO DENSO INDONESIA</b></td>
        </tr>
        <tr class="small">
            <td>Manager</td>
            <td>Staff</td>
            <td colspan="2">Receiver</td>
        </tr>
        <tr>
            <td></td>
            <td></td>
            <td colspan="2"></td>
        </tr>
    </table>

    <div class="note">
        Note :<br>
        1. Original White : TTEC Accounting Dept.<br>
        2. Copy Red&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: Supplier<br>
        3. Copy Red&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: Supplier<br>
        4. Copy Green&nbsp;&nbsp;&nbsp;&nbsp;: TTEC Receiving
    </div>

</div>

</body>
</html>
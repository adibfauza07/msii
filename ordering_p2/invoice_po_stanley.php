<?php
require_once __DIR__ . "/../config/database_ordering.php";

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

function fmt_datetime_header() {
    return date("d/m/y H:i:s");
}

function fmt_qty($value) {
    return number_format((float)$value, 0, ",", ".");
}

function fmt_price($value) {
    return number_format((float)$value, 0, ",", ".");
}

function fmt_amount($value) {
    return number_format((float)$value, 0, ",", ".");
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
$seen = array(); // Array untuk melacak data yang sudah dimasukkan

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Membuat kunci unik (hash) dari Part No, Part Code, dan Qty 
    // agar baris duplikat bisa disaring dan dibuang
    $partNo = isset($row["PART_NO"]) ? trim((string)$row["PART_NO"]) : "";
    $partCode = isset($row["PART_CODE"]) ? trim((string)$row["PART_CODE"]) : "";
    $qty = isset($row["QTY"]) ? (string)$row["QTY"] : "";
    
    $hash = md5($partNo . "-" . $partCode . "-" . $qty);

    if (!isset($seen[$hash])) {
        $rows[] = $row;
        $seen[$hash] = true;
    }
}

if (count($rows) == 0) {
    die("Data invoice tidak ditemukan untuk DI_ID: " . h($di_id));
}

$head = $rows[0];

// =========================================================================
// PERBAIKAN: OVERRIDE / HARDCODE ALAMAT KHUSUS UNTUK STANLEY ELECTRIC
// =========================================================================
$custComp  = "P.T. INDONESIA STANLEY ELECTRIC";
$custAddr1 = "Jalan Millennium Raya 4A Blok G,";
$custAddr2 = "Miellennium Industrial Estate, Peusar, Panongan,";
$custCity  = "Kab. Tangerang, Banten, 15710";
// =========================================================================

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

for ($i = 0; $i < count($rows); $i++) {
    $qty = isset($rows[$i]["QTY"]) ? (float)$rows[$i]["QTY"] : 0;

    $price = 0;

    if (isset($rows[$i]["PART_PRICE"]) && $rows[$i]["PART_PRICE"] !== null) {
        $price = (float)$rows[$i]["PART_PRICE"];
    } elseif (isset($rows[$i]["ORDP_PRICE"]) && $rows[$i]["ORDP_PRICE"] !== null) {
        $price = (float)$rows[$i]["ORDP_PRICE"];
    }

    $totalAmount += ($qty * $price);
}

// Menghitung VAT 11% dan Grand Total
$vatAmount = $totalAmount * 0.11;
$grandTotal = $totalAmount + $vatAmount;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice - <?php echo h($invNo); ?></title>

    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        body {
            margin: 0;
            background: #9a9a9a;
            font-family: "Courier New", monospace;
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
            border: 1px solid #000000;
            padding: 7mm;
            box-sizing: border-box;
            overflow: hidden;
        }

        .top {
            display: grid;
            grid-template-columns: 38% 24% 38%;
            align-items: start;
            width: 100%;
            box-sizing: border-box;
        }

        .company {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 14px;
        }

        .company-name {
            font-weight: bold;
            font-size: 15px;
            letter-spacing: 1px;
            margin-bottom: 3px;
        }

        .title {
            text-align: center;
            font-family: "Times New Roman", serif;
            font-size: 30px;
            font-weight: normal;
            padding-top: 34px;
        }

        .right-head {
            font-size: 11px;
            line-height: 16px;
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

        .separator {
            border-top: 1px solid #000000;
            margin: 15px 0 7px 0;
        }

        .customer-area {
            display: grid;
            grid-template-columns: 63% 37%;
            min-height: 95px;
            width: 100%;
            box-sizing: border-box;
        }

        .messrs {
            font-size: 12px;
            line-height: 16px;
        }

        .messrs-title {
            margin-bottom: 5px;
        }

        .cust-name {
            font-weight: bold;
            font-size: 15px;
            font-family: Arial, sans-serif;
        }

        .payment {
            text-align: left;
            padding-left: 18px;
            font-size: 12px;
        }

        .currency {
            text-align: center;
            font-style: italic;
            margin-top: 48px;
        }

        .detail {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            margin-top: 6px;
            table-layout: fixed;
            box-sizing: border-box;
        }

        .detail th {
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            padding: 3px 1px;
            border-bottom: 1px solid #000000;
            vertical-align: bottom;
            line-height: 13px;
            box-sizing: border-box;
        }

        .detail td {
            font-size: 11px;
            padding: 2px 1px;
            vertical-align: top;
            line-height: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: clip;
            box-sizing: border-box;
        }

        .detail .no {
            width: 4%;
            text-align: left;
        }

        .detail .partno {
            width: 14%;
            text-align: left;
        }

        .detail .partname {
            width: 34%;
            text-align: left;
        }

        .detail .qty {
            width: 6%;
            text-align: right;
        }

        .detail .unit {
            width: 5%;
            text-align: center;
        }

        .detail .price {
            width: 10%;
            text-align: right;
        }

        .detail .amount {
            width: 12%;
            text-align: right;
        }

        .detail .po {
            width: 10%;
            text-align: center;
        }

        .detail .remark {
            width: 5%;
            text-align: left;
        }

        .totals-wrapper {
            border-bottom: 1px solid #000000;
            padding: 10px 0 8px 0;
            width: 100%;
            box-sizing: border-box;
        }

        .total-line {
            display: grid;
            grid-template-columns: 1fr 180px;
            padding: 4px 0;
            font-weight: bold;
            font-style: italic;
            font-size: 15px;
            width: 100%;
            box-sizing: border-box;
        }

        .total-label {
            text-align: center;
            letter-spacing: 7px;
        }

        .total-value {
            text-align: right;
            padding-right: 8px;
        }

        .note-row {
            display: grid;
            grid-template-columns: 62% 38%;
            margin-top: 8px;
            font-size: 11px;
            width: 100%;
            box-sizing: border-box;
        }

        .note {
            font-weight: bold;
            font-style: italic;
        }

        .signature {
            text-align: right;
            padding-right: 8px;
            font-style: italic;
            text-decoration: underline;
        }

        .received {
            margin-top: 58px;
            font-size: 11px;
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
                border: none;
                padding: 0;
                overflow: hidden;
            }

            .detail th,
            .detail td {
                font-size: 10.5px;
                line-height: 12px;
            }

            .company,
            .messrs,
            .payment,
            .right-head,
            .note-row,
            .received {
                font-size: 10.5px;
            }

            .company-name {
                font-size: 13px;
            }

            .cust-name {
                font-size: 13px;
            }

            .title {
                font-size: 28px;
            }
        }
    </style>
</head>

<body>

<div class="print-bar">
    <button onclick="window.print()">PRINT</button>
    <button onclick="window.location.href='export_invoice_po_excel.php?DI_ID=<?php echo h($di_id); ?>'">EXPORT EXCEL</button>
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

        <div class="title">INVOICE</div>

        <div class="right-head">
            <div class="right-topline">
                <div><?php echo h(fmt_datetime_header()); ?></div>
                <div>
                    FM.CO.00-60<br>
                    Page 1 of 1
                </div>
            </div>

            <div class="info-line">
                <div class="label">Date :</div>
                <div class="value"><?php echo h(fmt_date_id($diDate)); ?></div>
            </div>

            <div class="info-line">
                <div class="label">Invoice No :</div>
                <div class="value"><?php echo h($invNo); ?></div>
            </div>

            <div class="info-line">
                <div class="label">DS.No. :</div>
                <div class="value"><?php echo h($dsNo); ?></div>
            </div>
        </div>
    </div>

    <div class="separator"></div>

    <div class="customer-area">
        <div class="messrs">
            <div class="messrs-title">[MESSRS]</div>
            <div class="cust-name"><?php echo h($custComp); ?></div>

            <?php if (trim($custAddr1) != "") { ?>
                <?php echo h($custAddr1); ?><br>
            <?php } ?>

            <?php if (trim($custAddr2) != "") { ?>
                <?php echo h($custAddr2); ?><br>
            <?php } ?>

            <?php if (trim($custCity) != "") { ?>
                <?php echo h($custCity); ?>
            <?php } ?>
        </div>

        <div class="payment">
            Payment Term : 1 month
            <div class="currency">Currency : <?php echo h($currency); ?></div>
        </div>
    </div>

    <table class="detail">
        <thead>
            <tr>
                <th class="no"></th>
                <th class="partno">Part<br>No.Number</th>
                <th class="partname">Part<br>Name</th>
                <th class="qty">Part<br>Qty</th>
                <th class="unit">Part<br>Unit</th>
                <th class="price">Unit<br>Price</th>
                <th class="amount">Amount</th>
                <th class="po">P.O.#</th>
                <th class="remark">Remark</th>
            </tr>
        </thead>

        <tbody>
            <?php for ($i = 0; $i < count($rows); $i++) { ?>
                <?php
                $r = $rows[$i];

                $qty = isset($r["QTY"]) ? (float)$r["QTY"] : 0;

                $price = 0;

                if (isset($r["PART_PRICE"]) && $r["PART_PRICE"] !== null) {
                    $price = (float)$r["PART_PRICE"];
                } elseif (isset($r["ORDP_PRICE"]) && $r["ORDP_PRICE"] !== null) {
                    $price = (float)$r["ORDP_PRICE"];
                }

                $amount = $qty * $price;

                $partCode = isset($r["PART_CODE"]) ? trim($r["PART_CODE"]) : "";
                $partNo   = isset($r["PART_NO"]) ? trim($r["PART_NO"]) : "";
                $partName = isset($r["PART_NAME"]) ? trim($r["PART_NAME"]) : "";
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
                ?>
                <tr>
                    <td class="no"><?php echo h($i + 1); ?>.</td>
                    <td class="partno"><?php echo h($partNoDisplay); ?></td>
                    <td class="partname"><?php echo h($partNameDisplay); ?></td>
                    <td class="qty"><?php echo h(fmt_qty($qty)); ?></td>
                    <td class="unit"><?php echo h($unit); ?></td>
                    <td class="price"><?php echo h(fmt_price($price)); ?></td>
                    <td class="amount"><?php echo h(fmt_amount($amount)); ?></td>
                    <td class="po"><?php echo h($poNo); ?></td>
                    <td class="remark"><?php echo h($remark); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <div class="totals-wrapper">
        <div class="total-line">
            <div class="total-label">TOTAL AMOUNT</div>
            <div class="total-value"><?php echo h(fmt_amount($totalAmount)); ?></div>
        </div>
        <div class="total-line">
            <div class="total-label">VAT 11%</div>
            <div class="total-value"><?php echo h(fmt_amount($vatAmount)); ?></div>
        </div>
        <div class="total-line">
            <div class="total-label">GRAND TOTAL</div>
            <div class="total-value"><?php echo h(fmt_amount($grandTotal)); ?></div>
        </div>
    </div>

    <div class="note-row">
        <div class="note">Box / Tray Harap di kembalikan ke PT.IMC TEKNO INDONESIA</div>
        <div class="signature">Authorized Signature</div>
    </div>

    <div class="received">Received By :</div>

</div>

</body>
</html>
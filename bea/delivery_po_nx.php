<?php
require_once __DIR__ . '/config/database.php';

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function fmt_date_id($value) {
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
    die("Data delivery sheet PO tidak ditemukan untuk DI_ID: " . h($di_id));
}

$head = $rows[0];
$diDate = isset($head["DI_DATE"]) ? $head["DI_DATE"] : "";
$dsNo   = isset($head["DI_DSNO"]) ? $head["DI_DSNO"] : "";
$invNo  = isset($head["DI_INVNO"]) ? $head["DI_INVNO"] : "";

$jenisBc = isset($head["JENIS_BC"]) ? trim($head["JENIS_BC"]) : "";
$nomorBc = isset($head["NOMOR_BC"]) ? trim($head["NOMOR_BC"]) : "";

$jenisBcDisplay = str_replace(" ", ".", $jenisBc); 
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Sheet PO - <?php echo h($dsNo); ?></title>
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        body { margin: 0; background: #9a9a9a; font-family: "Courier New", monospace; font-size: 12px; color: #000; }
        .print-bar { width: 210mm; margin: 10px auto; text-align: right; }
        .print-bar button { padding: 6px 14px; font-size: 11px; cursor: pointer; }
        .paper { width: 210mm; min-height: 297mm; margin: 20px auto; background: #fff; border: 1px solid #000; padding: 7mm; box-sizing: border-box; overflow: hidden; }
        .top { display: grid; grid-template-columns: 38% 24% 38%; align-items: start; width: 100%; }
        .company { font-family: Arial, sans-serif; font-size: 11px; line-height: 14px; }
        .company-name { font-weight: bold; font-size: 14px; letter-spacing: 1px; margin-bottom: 3px; }
        .title { text-align: center; font-family: Arial, sans-serif; font-size: 22px; font-weight: normal; letter-spacing: 1px; padding-top: 34px; }
        .right-head { font-size: 11px; line-height: 16px; }
        .right-topline { display: flex; justify-content: space-between; margin-bottom: 28px; }
        
        .info-line { display: grid; grid-template-columns: 90px 1fr; align-items: baseline; margin-bottom: 2px; }
        .info-line .label { text-align: right; padding-right: 5px; }
        .info-line .value { text-align: right; }

        .doc-number { font-size: 15px; font-weight: bold; }

        .separator { border-top: 1px solid #000; margin: 15px 0 7px 0; }
        .customer-area { min-height: 80px; width: 100%; }
        .messrs { font-size: 12px; line-height: 16px; }
        .messrs-title { margin-bottom: 5px; }
        .cust-name { font-weight: bold; font-size: 14px; font-family: Arial, sans-serif; }
        
        .detail { width: 100%; border-collapse: collapse; border-top: 1px solid #000; border-bottom: 1px solid #000; margin-top: 6px; table-layout: fixed; }
        .detail th { font-size: 12px; font-weight: bold; text-align: center; padding: 3px 1px; border-bottom: 1px solid #000; vertical-align: bottom; line-height: 13px; box-sizing: border-box; }
        .detail td { font-size: 12px; padding: 2px 1px; vertical-align: top; line-height: 14px; white-space: nowrap; overflow: hidden; text-overflow: clip; box-sizing: border-box; }
        
        .detail .no { width: 4%; text-align: left; }
        .detail .partno { width: 14%; text-align: left; } 
        
        /* --- PERBAIKAN: Menambahkan jarak aman (padding-right) dan efek elipsis (...) jika terpotong --- */
        .detail .partname { width: 35%; text-align: left; padding-right: 15px; text-overflow: ellipsis; } 
        /* --------------------------------------------------------------------------------------------- */
        
        .detail .qty { width: 7%; text-align: right; padding-right: 6px; } 
        .detail .unit { width: 5%; text-align: center; }
        .detail .po { width: 16%; text-align: center; } 
        .detail .packing { width: 10%; text-align: center; }
        .detail .pqty { width: 9%; text-align: right; padding-right: 12px; } 

        .signature-line { border-top: 1px solid #000; margin-top: 138mm; }
        .sign-area { display: grid; grid-template-columns: 1fr 1fr 1fr; margin-top: 12px; font-size: 10px; text-align: center; }
        .sign-box { height: 58px; position: relative; }
        .sign-name { position: absolute; left: 20%; right: 20%; bottom: 0; border-top: 1px solid #000; height: 1px; }
        
        @media print {
            body { background: #fff; }
            .print-bar { display: none; }
            .paper { width: 196mm; min-height: 281mm; margin: 0; border: none; padding: 0; overflow: hidden; }
            .detail th, .detail td { font-size: 11px; line-height: 13px; }
            .company, .messrs, .right-head { font-size: 11px; }
            .doc-number { font-size: 14px; } 
            .signature-line { margin-top: 132mm; }
        }
    </style>
</head>
<body>
<div class="print-bar">
    <button onclick="window.print()">PRINT</button>
    <button onclick="window.location.href='export_delivery_sheet_po_excel.php?DI_ID=<?php echo h($di_id); ?>'">EXPORT EXCEL</button>
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
        <div class="title">DELIVERY SHEET</div>
        <div class="right-head">
            <div class="right-topline">
                <div><?php echo h(fmt_datetime_header()); ?></div>
                <div>FM.CO.00-05<br>Page 1 of 1</div>
            </div>
            
            <div class="info-line"><div class="label">Date :</div><div class="value doc-number"><?php echo h(fmt_date_id($diDate)); ?></div></div>
            <div class="info-line"><div class="label">DS.No :</div><div class="value doc-number"><?php echo h($dsNo); ?></div></div>
            <div class="info-line"><div class="label">INV.No :</div><div class="value doc-number"><?php echo h($invNo); ?></div></div>
            
            <?php if ($jenisBc != "" || $nomorBc != "") { ?>
                <div class="info-line"><div class="label">jenis BC :</div><div class="value doc-number"><?php echo h($jenisBcDisplay); ?></div></div>
                <div class="info-line"><div class="label">Nomor BC :</div><div class="value doc-number"><?php echo h($nomorBc); ?></div></div>
            <?php } ?>
        </div>
    </div>
    <div class="separator"></div>
    <div class="customer-area">
        <div class="messrs">
            <div class="messrs-title">DELIVERED TO :</div>
            <div class="cust-name">PT.INDONESIA EPSON INDUSTRY</div>
            PDPLB PT.INDOENSIA EPSON INDUSTRY<br>
            LOGOS CIKARANG, LOGISTIC PARK, GUDANG IB<br>
            JL.GREENLAND BATAVIA BLOCK BB/1-BC/1,<br>
            CIKARANG PUSAT KOTA DELTAMAS, BEKASI JAWA BARAT
        </div>
    </div>
    <table class="detail">
        <thead>
            <tr>
                <th class="no">No</th>
                <th class="partno">Part No</th>
                <th class="partname">Part Name</th>
                <th class="qty">Qty</th>
                <th class="unit">Unit</th>
                <th class="po">PO #</th>
                <th class="packing">Packing</th>
                <th class="pqty">P.Qty</th>
            </tr>
        </thead>
        <tbody>
            <?php for ($i = 0; $i < count($rows); $i++) { ?>
                <?php
                $r = $rows[$i];
                $qty = isset($r["QTY"]) ? (float)$r["QTY"] : 0;
                $packing = "";
                if (isset($r["DIPA_PACK"]) && trim($r["DIPA_PACK"]) != "" && trim($r["DIPA_PACK"]) != "0") {
                    $packing = trim($r["DIPA_PACK"]);
                } elseif (isset($r["PACK_CODE"]) && trim($r["PACK_CODE"]) != "") {
                    $packing = trim($r["PACK_CODE"]);
                }
                $pqty = "";
                if (isset($r["DIPA_PQTY"]) && (float)$r["DIPA_PQTY"] != 0) {
                    $pqty = fmt_qty($r["DIPA_PQTY"]);
                }
                $partCode = isset($r["PART_CODE"]) ? trim($r["PART_CODE"]) : "";
                $partNo = isset($r["PART_NO"]) ? trim($r["PART_NO"]) : "";
                $partName = isset($r["PART_NAME"]) ? trim($r["PART_NAME"]) : "";
                $partNum = isset($r["PART_NUM"]) ? trim($r["PART_NUM"]) : "";
                $unit = isset($r["PART_UNIT"]) ? trim($r["PART_UNIT"]) : "";
                $poNo = isset($r["ORDR_PO"]) ? trim($r["ORDR_PO"]) : "";
                
                $partNoDisplay = $partNo != "" ? $partNo : $partCode;
                $partNameDisplay = $partName;
                ?>
                <tr>
                    <td class="no"><?php echo h($i + 1); ?></td>
                    <td class="partno"><?php echo h($partNoDisplay); ?></td>
                    <td class="partname"><?php echo h($partNameDisplay); ?></td>
                    <td class="qty"><?php echo h(fmt_qty($qty)); ?></td>
                    <td class="unit"><?php echo h($unit); ?></td>
                    <td class="po"><?php echo h($poNo); ?></td>
                    <td class="packing"><?php echo h($packing); ?></td>
                    <td class="pqty"><?php echo h($pqty); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    <div class="signature-line"></div>
    <div class="sign-area">
        <div class="sign-box">Delivered by<div class="sign-name"></div></div>
        <div class="sign-box">Knowledge by<div class="sign-name"></div></div>
        <div class="sign-box">Received by<div class="sign-name"></div></div>
    </div>
</div>
</body>
</html>
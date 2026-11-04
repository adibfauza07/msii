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

$di_id = isset($_GET["DI_ID"]) ? intval($_GET["DI_ID"]) : 0;
if ($di_id <= 0) {
    die("DI_ID tidak valid.");
}

$sql = "
    SET NOCOUNT ON;
    EXEC dbo.SP_RPT_DELIVERYSHEET_mapi ?
";

$stmt = sqlsrv_query($conn, $sql, array($di_id));
if ($stmt === false) {
    die("<pre>Query SP_RPT_DELIVERYSHEET_mapi gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $row;
}

if (count($rows) == 0) {
    die("Data delivery sheet tidak ditemukan untuk DI_ID: " . h($di_id));
}

$head = $rows[0];
$custComp  = isset($head["CUST_COMP"]) ? $head["CUST_COMP"] : "";
$custAddr1 = isset($head["CUST_ADDR1"]) ? $head["CUST_ADDR1"] : "";
$custAddr2 = isset($head["CUST_ADDR2"]) ? $head["CUST_ADDR2"] : "";
$custCity  = isset($head["CUST_CITY"]) ? $head["CUST_CITY"] : "";
$diDate = isset($head["DI_DATE"]) ? $head["DI_DATE"] : "";
$dsNo   = isset($head["DI_DSNO"]) ? $head["DI_DSNO"] : "";
$invNo  = isset($head["DI_INVNO"]) ? $head["DI_INVNO"] : "";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Sheet - <?php echo h($dsNo); ?></title>
    <style>
        /* Pengaturan ukuran A4 Portrait */
        @page { 
            size: A4 portrait; 
            margin: 8mm; 
        }
        
        body { 
            margin: 0; 
            background: #9a9a9a; 
            font-family: "Courier New", monospace; 
            font-size: 12px; 
            color: #000; 
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
            background: #fff; 
            border: 1px solid #000; 
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
            font-family: Arial, sans-serif; 
            font-size: 11px; 
            line-height: 14px; 
        }
        
        .company-name { 
            font-weight: bold; 
            font-size: 14px; 
            letter-spacing: 1px; 
            margin-bottom: 3px; 
        }
        
        .title { 
            text-align: center; 
            font-family: Arial, sans-serif; 
            font-size: 22px; 
            font-weight: normal; 
            letter-spacing: 1px; 
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
            grid-template-columns: 100px 1fr; 
            font-size: 13px; 
            line-height: 18px; 
        }
        
        .info-line .label { 
            text-align: right; 
            padding-right: 5px; 
        }
        
        .info-line .value { 
            text-align: right; 
        }
        
        .separator { 
            border-top: 1px solid #000; 
            margin: 15px 0 7px 0; 
        }
        
        .customer-area { 
            min-height: 80px; 
            width: 100%; 
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
            font-size: 14px; 
            font-family: Arial, sans-serif; 
        }
        
        .detail { 
            width: 100%; 
            border-collapse: collapse; 
            border-top: 1px solid #000; 
            border-bottom: 1px solid #000; 
            margin-top: 6px; 
            table-layout: fixed; 
        }
        
        .detail th { 
            font-size: 11px; 
            font-weight: bold; 
            text-align: center; 
            padding: 3px 1px; 
            border-bottom: 1px solid #000; 
            vertical-align: bottom; 
            line-height: 13px; 
            box-sizing: border-box; 
        }
        
        .detail td { 
            font-size: 11px; 
            padding: 2px 1px; 
            vertical-align: top; 
            line-height: 14px; 
            white-space: normal; /* Diubah ke normal agar teks panjang seperti PO bisa turun ke bawah jika tidak muat */
            overflow: hidden; 
            box-sizing: border-box; 
        }
        
        /* Penyesuaian Lebar Kolom untuk memberi ruang pada PO No */
        .detail .no { width: 4%; text-align: left; }
        .detail .partcode { width: 12%; text-align: left; }
        .detail .partno { width: 16%; text-align: left; }
        .detail .partname { width: 22%; text-align: left; }
        .detail .qty { width: 7%; text-align: right; }
        .detail .unit { width: 5%; text-align: center; }
        .detail .lotno { width: 13%; text-align: left; }
        .detail .pono { width: 15%; text-align: left; } /* Menggantikan .packing, lebar diperbesar */
        .detail .pqty { width: 6%; text-align: right; }
        
        .signature-line { 
            border-top: 1px solid #000; 
            margin-top: 80mm; 
        }
        
        .sign-area { 
            display: grid; 
            grid-template-columns: 1fr 1fr 1fr; 
            margin-top: 12px; 
            font-size: 10px; 
            text-align: center; 
        }
        
        .sign-box { 
            height: 58px; 
            position: relative; 
        }
        
        .sign-name { 
            position: absolute; 
            left: 20%; 
            right: 20%; 
            bottom: 0; 
            border-top: 1px solid #000; 
            height: 1px; 
        }
        
        @media print {
            body { 
                background: #fff; 
            }
            .print-bar { 
                display: none; 
            }
            .paper { 
                width: 210mm; 
                min-height: 297mm; 
                margin: 0; 
                border: none; 
                padding: 0; 
                overflow: hidden; 
                page-break-after: avoid; 
            }
            .detail th, .detail td { 
                font-size: 10px; 
                line-height: 12px; 
            }
            .company, .messrs { 
                font-size: 11px; 
            }
            .right-head .right-topline {
                font-size: 11px;
            }
            .info-line { 
                font-size: 12px; 
            }
            .signature-line { 
                margin-top: 70mm; 
            }
        }
    </style>
</head>
<body>
<div class="print-bar">
    <button onclick="window.print()">PRINT</button>
    <button onclick="window.location.href='export_delivery_sheet_excel.php?DI_ID=<?php echo h($di_id); ?>'">EXPORT EXCEL</button>
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
            <div class="info-line"><div class="label">Date :</div><div class="value"><?php echo h(fmt_date_id($diDate)); ?></div></div>
            <div class="info-line"><div class="label">DS.No :</div><div class="value"><?php echo h($dsNo); ?></div></div>
            <div class="info-line"><div class="label">INV.No :</div><div class="value"><?php echo h($invNo); ?></div></div>
        </div>
    </div>
    <div class="separator"></div>
    <div class="customer-area">
        <div class="messrs">
            <div class="messrs-title">[MESSRS]</div>
            <div class="cust-name"><?php echo h($custComp); ?></div>
            <?php if (trim($custAddr1) != "") { echo h($custAddr1) . "<br>"; } ?>
            <?php if (trim($custAddr2) != "") { echo h($custAddr2) . "<br>"; } ?>
            <?php if (trim($custCity) != "") { echo h($custCity); } ?>
        </div>
    </div>
    <table class="detail">
        <thead>
            <tr>
                <th class="no">No</th>
                <th class="partcode">Part Code</th>
                <th class="partno">Part No</th>
                <th class="partname">Part Name</th>
                <th class="qty">Qty</th>
                <th class="unit">Unit</th>
                <th class="lotno">Lot Number</th> 
                <!-- Kolom PO No menggantikan Packing -->
                <th class="pono">PO No</th>
                <th class="pqty">P.Qty</th>
            </tr>
        </thead>
        <tbody>
            <?php for ($i = 0; $i < count($rows); $i++) { ?>
                <?php
                $r = $rows[$i];
                $qty = isset($r["DIPA_QTY"]) ? (float)$r["DIPA_QTY"] : 0;
                
                // --- PERUBAHAN: Menarik data PO Number ---
                $pono = isset($r["ORDR_PO"]) ? trim($r["ORDR_PO"]) : "";
                
                $pqty = isset($r["DIPA_PQTY"]) ? (float)$r["DIPA_PQTY"] : 0;
                $partCode = isset($r["PART_CODE"]) ? trim($r["PART_CODE"]) : "";
                $partNo = isset($r["PART_NO"]) ? trim($r["PART_NO"]) : "";
                $partName = isset($r["PART_NAME"]) ? trim($r["PART_NAME"]) : "";
                $partNum = isset($r["PART_NUM"]) ? trim($r["PART_NUM"]) : "";
                $unit = isset($r["PART_UNIT"]) ? trim($r["PART_UNIT"]) : "";
                
                $location = "";
                if (isset($r["LOCATION_CUST"]) && trim($r["LOCATION_CUST"]) !== "") {
                    $location = trim($r["LOCATION_CUST"]);
                } elseif (isset($r["LOCATION"]) && trim($r["LOCATION"]) !== "") {
                    $location = trim($r["LOCATION"]);
                }
                
                $partNameDisplay = trim($partName);
                $partNameDisplay = preg_replace('/\s*[0-9]{5}-.*/', '', $partNameDisplay);
                $partNameDisplay = rtrim($partNameDisplay, " ,.-");
                ?>
                <tr>
                    <td class="no"><?php echo h($i + 1); ?></td>
                    <td class="partcode"><?php echo h($partNum); ?></td>
                    <td class="partno"><?php echo h($partNo); ?></td>
                    <td class="partname"><?php echo h($partNameDisplay); ?></td>
                    <td class="qty"><?php echo h(fmt_qty($qty)); ?></td>
                    <td class="unit"><?php echo h($unit); ?></td>
                    <td class="lotno"><?php echo h($location); ?></td>
                    <!-- Menampilkan Nomor PO -->
                    <td class="pono"><?php echo h($pono); ?></td>
                    <td class="pqty"><?php echo h(fmt_qty($pqty)); ?></td>
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
<?php
// File: wo.php (Cetak Work Order Khusus Proses Injection)
error_reporting(0);
ob_start();

require_once __DIR__ . '/config/database.php';

$db = null;
if (isset($conn) && $conn !== false) {
    $db = $conn;
} elseif (isset($connection) && $connection !== false) {
    $db = $connection;
} elseif (isset($dbconn) && $dbconn !== false) {
    $db = $dbconn;
}

if (!$db) {
    die("Koneksi database tidak tersedia.");
}

$wo_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['wo_id']) ? intval($_GET['wo_id']) : 0);

// Ambil Data Header WO Lengkap
$sqlHdr = "SELECT TOP 1
            W.WO_ID, W.WO_NUMBER, W.WO_MMYY, W.WO_REF, W.WO_NO, W.MODIFY_DATE, W.PRCD_CODE,
            P.PROC_NAME, C.CUST_COMP, M.MAC_CODE, W.WO_CAP, W.WO_START, W.WO_END, W.WO_QTY, W.WO_REM,
            IP.ITEM_CAVT, IP.ITEM_CYTM, IP.CAPD, I.ITEM_CODE, I.ITEM_NO, I.ITEM_NAME,
            ISNULL(SP.WARNA_LABEL, 'WHITE') AS WARNA_LABEL,
            ISNULL(SP.STD_PACK, 0) AS STD_PACK,
            ISNULL(SP.STD_PACK_BOX, 0) AS STD_PACK_BOX,
            W.BOM_ID
          FROM dbo.WO W
          INNER JOIN dbo.ITEMS I ON W.ITEM_ID = I.ITEM_ID
          LEFT JOIN dbo.PROCESS P ON W.PROC_ID = P.PROC_ID
          LEFT JOIN dbo.ITEM_CUSTINFO_VIEW C ON W.ITEM_ID = C.ITEM_ID
          LEFT JOIN dbo.ITEM_CAPD_VIEW IP ON W.ITEM_ID = IP.ITEM_ID
          LEFT JOIN dbo.MAC M ON W.MAC_ID = M.MAC_ID
          LEFT JOIN (
              SELECT ITEM_CODE, MAX(WARNA_LABEL) AS WARNA_LABEL, MAX(STD_PACK) AS STD_PACK, MAX(STD_PACK_BOX) AS STD_PACK_BOX
              FROM dbo.STD_PACK GROUP BY ITEM_CODE
          ) SP ON SP.ITEM_CODE = I.ITEM_CODE
          WHERE W.WO_ID = ?";

$stmtHdr = sqlsrv_query($db, $sqlHdr, array($wo_id));
$header = $stmtHdr ? sqlsrv_fetch_object($stmtHdr) : null;

if (!$header) {
    die("Data Work Order ID $wo_id tidak ditemukan.");
}

// Ambil Detail Material via Stored Procedure RPT_WO_new, jika tidak ada gunakan query BOM fallback
$materials = array();
$sqlProc = "EXEC RPT_WO_new ?";
$stmtMat = sqlsrv_query($db, $sqlProc, array($wo_id));
if ($stmtMat && sqlsrv_has_rows($stmtMat)) {
    while ($rm = sqlsrv_fetch_object($stmtMat)) {
        $materials[] = $rm;
    }
} else {
    $sqlMat = "SELECT I.ITEM_CODE AS CITEM_CODE, I.ITEM_NAME AS CITEM_NAME, 
                      ISNULL(B.QTY, 1) AS QTY, ISNULL(I.ITEM_UNIT, 'Pcs') AS UNIT, 0 AS FINAL_PCT_QTY
               FROM dbo.BOM B
               LEFT JOIN dbo.ITEMS I ON B.ITEM_ID = I.ITEM_ID
               WHERE B.BOM_ID = ?
               ORDER BY I.ITEM_CODE ASC";
    $stmtMat2 = sqlsrv_query($db, $sqlMat, array($header->BOM_ID));
    if ($stmtMat2) {
        while ($rm = sqlsrv_fetch_object($stmtMat2)) {
            $materials[] = $rm;
        }
    }
}
$sql = count($materials);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cetak Work Order - <?= $header->WO_NUMBER ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
    table[border="1"] {
        border-collapse: collapse !important;
        border-spacing: 0 !important;
        border: 1.5px solid #000 !important;
    }
    table[border="1"] > tbody > tr > th,
    table[border="1"] > tbody > tr > td,
    table[border="1"] > tr > th,
    table[border="1"] > tr > td {
        border: 1.5px solid #000 !important;
        border-color: #000 !important;
    }
    @media screen {
        .invoice { margin-left: 5px; margin-right: 5px; }
    }
    @media print {
        .no-print, .no-print * { display: none !important; }
        .pagebreak { page-break-after: always; }
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color: #000 !important;
        }
        table[border="1"] { border-collapse: collapse !important; border: 1.5px solid #000 !important; }
        table[border="1"] th, table[border="1"] td { border: 1.5px solid #000 !important; }
    }
    .table > thead tr th { padding: 2px; font-size: 11px; }
    .table > tbody tr td { padding: 2px; font-size: 10px; }
    .kecil { font-size: 12px; padding-right: 2px; }
    .besar { font-size: 14px; font-weight: bold; color: #D15B47; padding-right: 2px; }
    </style>
</head>
<body style="background:#fff;">

<section class="invoice" style="margin-top:2px; padding:10px; text-align:center;">
    <div class="col-lg-12">
        <div class="row" style="min-height:200px;">
        <center>
            <table width="750px" border="0">
                <tr>
                    <th class="kecil">PT. IMC TEKNO INDONESIA</th>
                    <th rowspan="4" style="text-align:right; width:70px; padding:0px;">
                        <div style="border:1.5px solid #000; width:65px; height:65px; line-height:65px; text-align:center; font-size:9px; font-weight:bold;">QR CODE</div>
                    </th>
                </tr>
                <tr><th class="kecil">PPIC DEPARTEMENT</th></tr>
                <tr><th class="kecil">PRODUCTION PLANNING CONTROL</th></tr>
                <tr><th class="kecil">&nbsp;</th></tr>
            </table>

            <table width="750px" border="1">
                <tr>
                    <th class="kecil">WO NO</th>
                    <td class="kecil" style="font-weight:bold;"><?= $header->WO_NUMBER ?></td>
                    <th class="kecil" colspan="2" rowspan="3" style="text-align:center; vertical-align:middle; font-size:24px; font-weight:bold;">WORK ORDER</th>
                    <th class="kecil">Part Code</th>
                    <td class="kecil" style="font-weight:bold;"><?= $header->ITEM_CODE ?></td>
                    <th class="kecil">Cav</th>
                    <td class="kecil"><?= $header->ITEM_CAVT ?></td>
                </tr>
                <tr>
                    <th class="kecil">Issue Date</th>
                    <td class="kecil"><?= date('d-M-Y', strtotime($header->MODIFY_DATE)) ?></td>
                    <th class="kecil">Part No</th>
                    <td class="kecil"><?= $header->ITEM_NO ?></td>
                    <th class="kecil">C.Time</th>
                    <td class="kecil"><?= number_format($header->ITEM_CYTM, 2) ?></td>
                </tr>
                <tr>
                    <th class="kecil">Section</th>
                    <td class="kecil"><?= $header->PROC_NAME ?></td>
                    <th class="kecil">Part Name</th>
                    <td class="kecil" colspan="3"><?= $header->ITEM_NAME ?></td>
                </tr>
                <tr>
                    <th class="kecil">Machine No</th>
                    <td class="kecil"><?= $header->MAC_CODE ?></td>
                    <th class="kecil">Plan Start Date</th>
                    <td class="kecil"><?= date('d-M-Y', strtotime($header->WO_START)) ?></td>
                    <th class="kecil">Customer</th>
                    <td class="kecil" colspan="3"><?= $header->CUST_COMP ?></td>
                </tr>
                <tr>
                    <th class="kecil">WO Qty</th>
                    <td class="kecil" style="font-weight:bold;"><?= number_format($header->WO_QTY, 0) ?> Pcs</td>
                    <th class="kecil">Plan Finish Date</th>
                    <td class="kecil"><?= date('d-M-Y', strtotime($header->WO_END)) ?></td>
                    <th class="kecil">Cap/Day</th>
                    <td class="kecil"><?= number_format($header->CAPD, 0) ?> Pcs</td>
                    <th class="kecil">Mc Day Std</th>
                    <td class="kecil">
                        <?php 
                            $mcd_std = (isset($header->CAPD) && $header->CAPD > 0) ? ($header->WO_QTY / $header->CAPD) : 0;
                            echo number_format($mcd_std, 2); 
                        ?>
                    </td>
                </tr>
                <tr>
                    <th class="kecil" style="vertical-align:middle;">Item Code</th>
                    <th class="kecil" colspan="3" style="text-align:center; height:25px; vertical-align:middle;">Material Name</th>
                    <th class="kecil" style="text-align:center; vertical-align:middle;">BOM</th>
                    <th class="kecil" style="text-align:center; vertical-align:middle;">Used Plan</th>
                    <th class="kecil" style="text-align:center; vertical-align:middle;">Unit</th>
                    <th class="kecil" style="text-align:center; vertical-align:middle;">%</th>
                </tr>
                <?php
                    if ($sql > 0) {
                        $tinggi = ($sql == 1) ? '60px' : (($sql == 2) ? '30px' : '20px');
                        foreach ($materials as $key) {
                            $persen = (isset($key->FINAL_PCT_QTY) && number_format($key->FINAL_PCT_QTY, 0) != 0) ? number_format($key->FINAL_PCT_QTY, 0) : '-';
                            echo '<tr>
                                    <td class="kecil" style="height:'.$tinggi.'">'.$key->CITEM_CODE.'</td>
                                    <td colspan="3" class="kecil">'.$key->CITEM_NAME.'</td>
                                    <td class="kecil" style="text-align:center;">'.number_format($key->QTY, 2).'</td>
                                    <td class="kecil" style="text-align:center;">'.number_format($key->QTY * $header->WO_QTY, 2).'</td>
                                    <td class="kecil" style="text-align:center;">'.$key->UNIT.'</td>
                                    <td class="kecil" style="text-align:center;">'.$persen.'</td>
                                 </tr>';
                        }
                    }
                ?>
                <tr>
                    <td colspan="8">
                        <table cellpadding="0" style="width: 100%; border: none;">
                            <tr>
                                <td style="width: 150px;">
                                    <?php 
                                        $tipe_prod = isset($header->PRCD_CODE) ? strtoupper(trim($header->PRCD_CODE)) : '';
                                        $cek_normal = ($tipe_prod == 'MP') ? '&#10004;' : '&nbsp;';
                                        $cek_trial  = ($tipe_prod == 'PP') ? '&#10004;' : '&nbsp;';
                                    ?>
                                    <table cellpadding="0" style="border: none;">
                                        <tr><td style="border:1.5px solid #000; width:40px; height:20px; text-align:center;">&nbsp;</td><td class="kecil" style="padding-left:5px;">URGENT</td></tr>
                                        <tr><td style="border:1.5px solid #000; width:40px; height:20px; text-align:center; font-weight:bold; font-size:14px;"><?= $cek_normal; ?></td><td class="kecil" style="padding-left:5px;">NORMAL</td></tr>
                                        <tr><td style="border:1.5px solid #000; width:40px; height:20px; text-align:center; font-weight:bold; font-size:14px;"><?= $cek_trial; ?></td><td class="kecil" style="padding-left:5px;">TRIAL</td></tr>
                                        <tr><td style="border:1.5px solid #000; width:40px; height:20px; text-align:center;">&nbsp;</td><td class="kecil" style="padding-left:5px;">ETC</td></tr>
                                    </table>
                                </td>

                                <td class="kecil" style="vertical-align:top; padding:5px;" colspan="3">
                                    <table cellpadding="0" cellspacing="0" style="border:none; table-layout:fixed; width:100%;">
                                        <tr>
                                            <td style="border:none; width:140px; white-space:nowrap; font-size:10px;">REMARKS (By PPC)</td>
                                            <td style="border:none; width:10px; text-align:center; font-size:10px;">:</td>
                                            <td style="border:none; font-size:12px;"><?= isset($header->WO_REM) ? $header->WO_REM : ''; ?></td>
                                        </tr>
                                        <tr>
                                            <td style="border:none; width:140px; white-space:nowrap; font-size:10px;"><strong>Label Colour</strong></td>
                                            <td style="border:none; width:10px; text-align:center; font-size:10px;"><strong>:</strong></td>
                                            <td style="border:none; font-size:12px;"><strong><?= isset($header->WARNA_LABEL) ? $header->WARNA_LABEL : 'WHITE'; ?></strong></td>
                                        </tr>
                                        <?php 
                                            $wo_qty       = isset($header->WO_QTY) ? $header->WO_QTY : 0;
                                            $std_pack     = (isset($header->STD_PACK) && $header->STD_PACK > 1) ? $header->STD_PACK : 0;
                                            $std_pack_box = (isset($header->STD_PACK_BOX) && $header->STD_PACK_BOX > 1) ? $header->STD_PACK_BOX : 0;
                                            $jml_polybag  = ($std_pack > 1) ? ceil($wo_qty / $std_pack) : 0;
                                            $jml_box      = ($std_pack_box > 1) ? ceil($wo_qty / $std_pack_box) : 0;
                                        ?>
                                        <tr>
                                            <td style="border:none; width:140px; white-space:nowrap; font-size:10px;"><strong>Polybag label QTY</strong></td>
                                            <td style="border:none; width:10px; text-align:center; font-size:10px;"><strong>:</strong></td>
                                            <td style="border:none; font-size:12px;"><strong><?= number_format($jml_polybag, 0) ?> Label</strong></td>
                                        </tr>
                                        <tr>
                                            <td style="border:none; width:140px; white-space:nowrap; font-size:10px;"><strong>Box Label QTY</strong></td>
                                            <td style="border:none; width:10px; text-align:center; font-size:10px;"><strong>:</strong></td>
                                            <td style="border:none; font-size:12px;"><strong><?= number_format($jml_box, 0) ?> Label</strong></td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr><th colspan="8" class="kecil" style="text-align:center;">PRODUCTION ACHIEVMENT</th></tr>
                <tr>
                    <th class="kecil" colspan="2" style="text-align:center;">Actual</th>
                    <th class="kecil">QTY OK</th>
                    <td class="kecil" style="text-align:right">Pcs</td>
                    <th class="kecil" style="min-height: 5px" colspan="4" rowspan="3">Remarks (By Production)</th>
                </tr>
                <tr>
                    <th class="kecil">Start Date</th>
                    <th></th>
                    <th class="kecil">QTY HOLD</th>
                    <td class="kecil" style="text-align:right">Pcs</td>
                </tr>
                <tr>
                    <th class="kecil">Finish Date</th>
                    <th></th>
                    <th class="kecil">QTY NG</th>
                    <td class="kecil" style="text-align:right">Pcs</td>
                </tr>

                <tr style="border:0px;">
                    <th class="kecil" colspan="4" style="vertical-align:bottom;">FM.CO.00.10(REV.8:28 Nov 23)</th>
                    <td colspan="4" style="padding:0px;">
                        <table width="100%" border="1" style="border-collapse:collapse;">
                            <tr>
                                <th colspan="2" class="kecil" style="text-align:center; width:50%;">Production</th>
                                <th colspan="2" class="kecil" style="text-align:center; width:50%;">PPC</th>
                            </tr>
                            <tr>
                                <td class="kecil" style="text-align:center; width:25%; vertical-align:top; height:60px;">Checked By</td>
                                <td class="kecil" style="text-align:center; width:25%; vertical-align:top; height:60px;">Prepared By</td>
                                <td class="kecil" style="text-align:center; width:25%; vertical-align:top; height:60px;">Checked By</td>
                                <td class="kecil" style="text-align:center; width:25%; vertical-align:top; height:60px;">Prepared By</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </center>
        </div>

        <div class="row no-print" style="margin-top:15px; max-width:750px; margin-left:auto; margin-right:auto;">
            <div class="col-sm-12 text-right">
                <button type="button" class="btn btn-default btn-sm cetak"><i class="fa fa-print"></i> Print</button> 
                <button type="button" class="btn btn-default btn-sm tutup"><i class="fa fa-times"></i> Close</button>
            </div>
        </div>
    </div>
</section>
<div class="pagebreak"></div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script>
$(document).ready(function(){
    $('body').on('click','.cetak',function(e){ window.print(); window.close(); });
    $('body').on('click','.tutup',function(e){ window.close(); });
});
</script>
</body>
</html>
<?php 
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (session_status() == PHP_SESSION_NONE) { 
    session_start(); 
}
require_once __DIR__ . "/../config/global.php";

$tranid = isset($_GET['tranid']) ? (int)$_GET['tranid'] : 0;

if ($conn === false || $tranid === 0) {
    die("Koneksi database gagal atau TRAN_ID tidak valid.");
}

$ses_plant = isset($_SESSION['plant']) ? strtolower(trim($_SESSION['plant'])) : 'plan1';

if ($ses_plant === 'plan2' || $ses_plant === 'plant2') {
    $nama_pabrik = "PT. IMC TEKNO INDONESIA PLANT 2";
    $dept = "PPIC DEPARTEMENT PLANT 2"; 
} else {
    $nama_pabrik = "PT. IMC TEKNO INDONESIA PLANT 2";
    $dept = "PPIC DEPARTEMENT";
}

$sqlHeader = "
    SELECT TOP 1 
        TRANS.*, 
        TRTY.TRTY_DESC
    FROM TRANS
    LEFT JOIN TRTY ON TRTY.TRTY_CODE = TRANS.TRTY_CODE
    WHERE TRANS.TRAN_ID = ?
";
$stmtH = sqlsrv_query($conn, $sqlHeader, array($tranid));
if ($stmtH === false || !sqlsrv_has_rows($stmtH)) {
    die("Data transaksi tidak ditemukan.");
}
$header = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmtH);

$tran_time = $header['TRANS_TIME'] instanceof DateTime ? $header['TRANS_TIME']->format('H:i:s') : (string)$header['TRANS_TIME'];
$tran_adate = $header['TRAN_ADATE'] instanceof DateTime ? $header['TRAN_ADATE']->format('d-M-Y') : (string)$header['TRAN_ADATE'];

$sqlDetail = "
    SELECT 
        ITEMS.ITEM_CODE, 
        ITEMS.ITEM_NAME, 
        ITEMS.ITEM_UNIT, 
        SUM(INV_TRAN.IT_QTY) AS IT_QTY
    FROM INV_TRAN
    INNER JOIN ITEMS ON ITEMS.ITEM_ID = INV_TRAN.ITEM_ID
    WHERE INV_TRAN.TRAN_ID = ?
    GROUP BY 
        INV_TRAN.ITEM_ID, 
        ITEMS.ITEM_CODE, 
        ITEMS.ITEM_NAME, 
        ITEMS.ITEM_UNIT
";
$stmtD = sqlsrv_query($conn, $sqlDetail, array($tranid));
$details = array();
$total_qty = 0;
if ($stmtD !== false) {
    while ($row = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) {
        $details[] = $row;
        $total_qty += (float)$row['IT_QTY'];
    }
    sqlsrv_free_stmt($stmtD);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Material/Part Slip - <?php echo htmlspecialchars($header['TRAN_DOC']); ?></title>
  <style>
    /* Pengaturan ukuran kertas 28cm x 24cm Landscape */
    @page { 
        size: 28cm 24cm landscape; 
        margin: 5mm; 
    }
    
    @media print {    
        .no-print, .no-print * { display: none !important; }
        body { 
            margin: 0; 
            padding: 0; 
            background: #fff; 
            -webkit-print-color-adjust: exact; 
            print-color-adjust: exact; 
        }
        .invoice { 
            padding: 0 !important; 
            margin: 0 !important; 
            width: 100% !important;
            page-break-inside: avoid; 
        }
    }

    body { background: #fff; color: #000; font-family: 'Calibri', sans-serif; font-size: 20px; line-height: 1.2; }
    table { width: 100% !important; border-collapse: collapse; }
    .table-slip { margin-top: 5px; margin-bottom: 5px; }
    .table-slip th { padding: 4px; font-size: 12px; border: 1px solid #000; text-align: center; }
    .table-slip td { padding: 3px 4px; font-size: 15px; border: 1px solid #000; height: 22px; vertical-align: middle; }
    .rapat { line-height: 1.2; margin: 0; padding: 0; font-size: 12px; }
    .kecil { font-size: 15px; padding-right: 2px; color: #000; }
    .tengah { text-align: center; }
    .kanan { text-align: right; }
</style>
</head>
<body>

<section class="invoice" style="padding: 0px;">
    <div style="width: 100%; margin: 0 auto;">
        <center>
            <table width="100%" border="0">
                <tr>
                    <td style="width:60%; text-align: left; vertical-align: top;">
                        <b style="font-size:16px;"><?php echo $nama_pabrik; ?></b>
                        <div class="rapat" style="margin-top: 2px;">
                            <?php echo $dept; ?>
                        </div>
                    </td>
                    <td class="kecil" style="width:40%; text-align:right; vertical-align: top;">
                        &nbsp;TIME: <?php echo $tran_time; ?>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="padding:5px 0px 5px 0px; text-align:center;">
                        <b style="font-size:16px; text-decoration: underline;">MATERIAL/PART SLIP</b>
                    </td>
                </tr>
            </table>
            
            <table width="100%" border="0" style="text-align: left; margin-bottom: 3px;">
                <tr>
                    <th class="kecil" style="width:12%;">NOMOR</th>
                    <th class="kecil" style="width:2%;">:</th>
                    <th class="kecil" style="width:36%;"><b style="font-size:15px;"><?php echo htmlspecialchars($header['TRAN_DOC']); ?></b></th>
                    <th class="kecil" style="width:15%;">&nbsp;</th>
                    <th class="kecil" style="width:2%;">&nbsp;</th>
                    <th class="kecil" style="width:33%;">&nbsp;</th>
                </tr>
                <tr>
                    <th class="kecil">DATE</th>
                    <th class="kecil">:</th>
                    <th class="kecil"><b style="font-size:12px;"><?php echo $tran_adate; ?></b></th>
                    <th class="kecil">FROM</th>
                    <th class="kecil">:</th>
                    <th class="kecil"><?php echo htmlspecialchars((string)$header['TRTY_DESC']); ?></th>
                </tr>
            </table>
            
            <table class="table-slip" width="100%">
                <thead>
                <tr>
                    <th style="width:5%">NO</th>
                    <th style="width:15%">WO NO</th>
                    <th style="width:15%">ITEM CODE</th>
                    <th style="width:30%">MATERIAL NAME</th>
                    <th style="width:5%">UNIT</th>
                    <th style="width:12%">QUANTITY</th>
                    <th style="width:18%">REMARK</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $max_rows = 8;
                $total_items = count($details);
                $rows_to_print = ($total_items > $max_rows) ? $total_items : $max_rows;

                for ($i = 0; $i < $rows_to_print; $i++) {
                    if ($i < $total_items) {
                        $key = $details[$i];
                        echo '<tr>
                                <td class="tengah">'.($i+1).'</td>
                                <td></td>
                                <td>'.htmlspecialchars($key['ITEM_CODE']).'</td>
                                <td>'.htmlspecialchars($key['ITEM_NAME']).'</td>
                                <td class="tengah">'.htmlspecialchars($key['ITEM_UNIT']).'</td>
                                <td class="kanan">'.number_format($key['IT_QTY'], 2).'</td>
                                <td></td>
                              </tr>';
                    } else {
                        echo '<tr><td class="tengah">'.($i+1).'</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
                    }
                }
                
                echo '<tr>
                        <td colspan="5" class="kanan" style="font-size:12px; padding-right:5px; height: 22px;"><b>TOTAL</b></td>
                        <td class="kanan" style="font-size:12px;"><b>'.number_format($total_qty, 2).'</b></td>
                        <td></td>
                      </tr>';
                ?>
                </tbody>
            </table>
            
            <table width="100%" border="0" style="margin-top: 4px;">
                <tr>
                    <td colspan="3" class="kecil" style="text-align: left; padding-bottom: 5px; font-size:10px;">FM.CO.01-36(Revisi2:Tgl.10.Des.2019)</td>
                </tr>
                <tr>
                    <td style="width:35%;" class="kecil tengah">DELIVERED</td>
                    <td style="width:30%;" class="kecil tengah">&nbsp;</td>
                    <td style="width:35%;" class="kecil tengah">RECEIVED</td>
                </tr>
                <tr><td colspan="3" style="height:40px;"></td></tr>
                <tr>
                    <td class="kecil tengah">(..............................)</td>
                    <td class="kecil tengah"></td>
                    <td class="kecil tengah">(..............................)</td>
                </tr>
            </table>
        </center>
        
        <div class="no-print" style="margin-top: 15px; text-align: right; border-top: 1px solid #ccc; padding-top: 10px;">
            <button style="padding: 5px 15px; cursor: pointer; font-size: 14px;" onclick="window.print();">Print</button> 
            <button style="padding: 5px 15px; cursor: pointer; font-size: 14px;" onclick="window.close();">Close</button>
        </div>
    </div>
</section>

</body>
</html>
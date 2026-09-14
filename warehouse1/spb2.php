<?php 
error_reporting(E_ALL);
ini_set('display_errors', 0);
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . "/../config/global.php";

$tranid = isset($_GET['tranid']) ? (int)$_GET['tranid'] : 0;

if ($conn === false || $tranid === 0) {
    die("Koneksi database gagal atau TRAN_ID tidak valid.");
}

$ses_plant = isset($_SESSION['plant']) ? strtolower(trim($_SESSION['plant'])) : 'plan1';

if ($ses_plant === 'plan2' || $ses_plant === 'plant2') {
    $nama_pabrik = "PT. IMC TEKNO INDONESIA PLANT 2";
    $alamat_pabrik = "Kawasan Industry Kota Bukit Indah ST-1 <br>
                      Blok A-III Lot No.15E-15F<br>
                      Purwakarta, Jawa Barat 41181, Indonesia.";
} else {
    $nama_pabrik = "PT. IMC TEKNO INDONESIA PLANT 2";
    $alamat_pabrik = "Kawasan Industry Kota Bukit Indah ST-1 <br>
                      Blok A-III Lot No.15E-15F<br>
                      Purwakarta, Jawa Barat 41181, Indonesia.";
}

$sqlHeader = "
    SELECT TOP 1 
        TRANS.*, 
        SUPPLIER.SUP_COMP, 
        BCTY.BCTY_NAME, 
        TRTY.TRTY_DESC
    FROM TRANS
    LEFT JOIN SUPPLIER ON SUPPLIER.SUP_CODE = TRANS.SUP_CODE
    LEFT JOIN BCTY ON BCTY.BCTY_ID = TRANS.BCTY_ID
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
$bc_info = trim($header['BCTY_NAME']) != '' ? htmlspecialchars($header['BCTY_NAME'] . '/' . $header['BC_NO']) : '-';

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
if ($stmtD !== false) {
    while ($row = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) {
        $details[] = $row;
    }
    sqlsrv_free_stmt($stmtD);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Pengantar Barang - <?php echo htmlspecialchars($header['TRAN_DOC']); ?></title>
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

    body { background: #fff; color: #000; font-family: 'Segoe UI', Tahoma, sans-serif; line-height: 1.1; }
    table { width: 100% !important; border-collapse: collapse; }
    .table-slip { margin-top: 3px; margin-bottom: 3px; }
    .table-slip th { padding: 2px 4px; font-size: 11px; border: 1px solid #000; text-align: center; }
    .table-slip td { padding: 1px 4px; font-size: 11px; border: 1px solid #000; height: 20px; vertical-align: middle; }
    .rapat { line-height: 1.1; margin: 0; padding: 0; font-size: 10px; }
    .kecil { font-size: 11px; padding-right: 2px; color: #000; }
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
                    <td style="width:65%; text-align: left; vertical-align: top;">
                        <b style="font-size:14px;"><?php echo $nama_pabrik; ?></b>
                        <div class="rapat">
                            <?php echo $alamat_pabrik; ?>
                        </div>
                    </td>
                    <td class="kecil" style="width:35%; text-align:right; vertical-align: top;">
                        &nbsp;TIME: <?php echo $tran_time; ?>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="padding:3px 0px; text-align:center;">
                        <b style="font-size:14px; text-decoration: underline;">SURAT PENGANTAR BARANG</b>
                    </td>
                </tr>
            </table>
            
            <table width="100%" border="0" style="text-align: left;">
                <tr>
                    <th class="kecil gkiri gatas gbawah" style="width:12%; padding-left:3px;">Nomor</th>
                    <th class="kecil gatas" style="width:2%;">:</th>
                    <th class="kecil gatas gkanan" style="width:36%;"><b style="font-size:12px;"><?php echo htmlspecialchars($header['TRAN_DOC']); ?></b></th>
                    <th rowspan="4" class="kecil gatas gkanan gbawah" style="width:50%; vertical-align:top; padding: 3px;">
                        Kepada Yth.<br>
                        <b style="font-size:12px;"><?php echo htmlspecialchars((string)$header['SUP_COMP']); ?></b>
                    </th>
                </tr>
                <tr>
                    <th class="kecil gkiri gatas" style="padding-left:3px;">Tanggal</th>
                    <th class="kecil gatas">:</th>
                    <th class="kecil gatas gkanan"><b style="font-size:11px;"><?php echo $tran_adate; ?></b></th>
                </tr>
                <tr>
                    <th class="kecil gkiri gatas" style="padding-left:3px;">No. Kend</th>
                    <th class="kecil gatas">:</th>
                    <th class="kecil gatas gkanan"></th>
                </tr>
                <tr>
                    <th class="kecil gkiri gatas gbawah" style="padding-left:3px;">Jenis/NO.BC</th>
                    <th class="kecil gatas gbawah">:</th>
                    <th class="kecil gatas gbawah gkanan"><?php echo $bc_info; ?></th>
                </tr>
            </table>
            
            <table class="table-slip" width="100%">
                <thead>
                <tr>
                    <th style="width:5%">NO</th>
                    <th style="width:15%">KODE</th>
                    <th style="width:35%">NAMA</th>
                    <th style="width:8%">SATUAN</th>
                    <th style="width:12%">JUMLAH</th>
                    <th style="width:25%">KETERANGAN</th>
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
                                <td>'.htmlspecialchars($key['ITEM_CODE']).'</td>
                                <td>'.htmlspecialchars($key['ITEM_NAME']).'</td>
                                <td class="tengah">'.htmlspecialchars($key['ITEM_UNIT']).'</td>
                                <td class="kanan">'.number_format($key['IT_QTY'], 2).'</td>
                                <td></td>
                              </tr>';
                    } else {
                        echo '<tr><td class="tengah">'.($i+1).'</td><td></td><td></td><td></td><td></td><td></td></tr>';
                    }
                }
                ?>
                </tbody>
            </table>
            
            <table width="100%" border="0" style="margin-top: 2px;">
                <tr>
                    <td style="width:20%;" class="kecil tengah">Penerima</td>
                    <td style="width:20%;" class="kecil tengah">&nbsp;</td>
                    <td style="width:20%;" class="kecil tengah">Mengetahui</td>
                    <td style="width:20%;" class="kecil tengah">&nbsp;</td>
                    <td style="width:20%;" class="kecil tengah">Hormat Kami</td>
                </tr>
                <tr><td colspan="5" style="height:35px;"></td></tr>
                <tr>
                    <td class="kecil tengah">(..............................)</td>
                    <td class="kecil tengah"></td>
                    <td class="kecil tengah">(..............................)</td>
                    <td class="kecil tengah"></td>
                    <td class="kecil tengah">(..............................)</td>
                </tr>
                <tr><td colspan="5" style="height:3px;"></td></tr>
                <tr>
                    <td class="kecil">1. White:Customer</td>
                    <td class="kecil">2. Pink:Accounting</td>
                    <td class="kecil">3. Yellow:Store</td>
                    <td class="kecil">4. Blue:Customer</td>
                    <td class="kecil">5. Green:Security</td>
                </tr>
                <tr>
                    <td colspan="5" class="kecil" style="padding-top:2px; font-size:9px;">FM.CO.01-20(Revisi4:Tgl.1.Mei.12)</td>
                </tr>
            </table>
        </center>
        
        <div class="no-print" style="margin-top: 15px; text-align: right; border-top: 1px solid #ccc; padding-top: 10px;">
            <button style="padding: 5px 15px; cursor: pointer;" onclick="window.print();">Print</button> 
            <button style="padding: 5px 15px; cursor: pointer;" onclick="window.close();">Close</button>
        </div>
    </div>
</section>
</body>
</html>
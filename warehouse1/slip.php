<?php 
// spb.php (Kompatibel PHP 5.4 & SQL Server 2008 - Layout Terkunci)
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (session_status() == PHP_SESSION_NONE) { 
    session_start(); 
}
require_once __DIR__ . "/../config/global.php";

// 1. Sanitasi Input
$tranid = isset($_GET['tranid']) ? (int)$_GET['tranid'] : 0;

if ($conn === false || $tranid === 0) {
    die("Koneksi database gagal atau TRAN_ID tidak valid.");
}

// 2. Logika Deteksi Plant (Dari Session Login)
$ses_plant = isset($_SESSION['plant']) ? strtolower(trim($_SESSION['plant'])) : 'plan1';

if ($ses_plant === 'plan2' || $ses_plant === 'plant2') {
    $nama_pabrik = "PT. IMC TEKNO INDONESIA PLANT 2";
    $dept = "PPIC DEPARTEMENT PLANT 2"; 
} else {
    $nama_pabrik = "PT. IMC TEKNO INDONESIA PLANT 1";
    $dept = "PPIC DEPARTEMENT";
}

// 3. Eksekusi Kueri Header (Parameterized Query)
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

// 4. Eksekusi Kueri Detail Grouping
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
            <!-- Header Organisasi -->
            <table width="100%" border="0">
                <tr>
                    <td style="width:60%; text-align: left; vertical-align: top;">
                        <b style="font-size:14px;"><?php echo $nama_pabrik; ?></b>
                        <div class="rapat" style="margin-top: 2px;">
                            <?php echo $dept; ?>
                        </div>
                    </td>
                    <td class="kecil" style="width:40%; text-align:right; vertical-align: top;">
                        &nbsp;TIME: <?php echo $tran_time; ?>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="padding:5px 0px 3px 0px; text-align:center;">
                        <b style="font-size:14px; text-decoration: underline;">MATERIAL/PART SLIP</b>
                    </td>
                </tr>
            </table>
            
            <!-- Header Dokumen -->
            <table width="100%" border="0" style="text-align: left;">
                <tr>
                    <th class="kecil" style="width:12%;">NOMOR</th>
                    <th class="kecil" style="width:2%;">:</th>
                    <th class="kecil" style="width:36%;"><b style="font-size:12px;"><?php echo htmlspecialchars($header['TRAN_DOC']); ?></b></th>
                    <th class="kecil" style="width:15%;">&nbsp;</th>
                    <th class="kecil" style="width:2%;">&nbsp;</th>
                    <th class="kecil" style="width:33%;">&nbsp;</th>
                </tr>
                <tr>
                    <th class="kecil">DATE</th>
                    <th class="kecil">:</th>
                    <th class="kecil"><b style="font-size:11px;"><?php echo $tran_adate; ?></b></th>
                    <th class="kecil">FROM</th>
                    <th class="kecil">:</th>
                    <th class="kecil"><?php echo htmlspecialchars((string)$header['TRTY_DESC']); ?></th>
                </tr>
            </table>
            
            <!-- Tabel Item (Fixed Layout) -->
            <table class="table-slip" width="100%">
                <thead>
                <tr>
                    <!-- Lebar kolom dikunci absolut dengan persentase -->
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
                // Wajib mencetak 8 baris, tidak kurang, tidak lebih.
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
                        // Baris kosong pengisi slot 8 baris
                        echo '<tr><td class="tengah">'.($i+1).'</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
                    }
                }
                
                // Baris Total
                echo '<tr>
                        <td colspan="5" class="kanan" style="font-size:11px; padding-right:5px; height: 20px;"><b>TOTAL</b></td>
                        <td class="kanan" style="font-size:11px;"><b>'.number_format($total_qty, 2).'</b></td>
                        <td></td>
                      </tr>';
                ?>
                </tbody>
            </table>
            
            <!-- Footer Tanda Tangan -->
            <table width="100%" border="0" style="margin-top: 2px;">
                <tr>
                    <td colspan="3" class="kecil" style="text-align: left; padding-bottom: 5px; font-size:9px;">FM.CO.01-36(Revisi2:Tgl.10.Des.2019)</td>
                </tr>
                <tr>
                    <td style="width:35%;" class="kecil tengah">DELIVERED</td>
                    <td style="width:30%;" class="kecil tengah">&nbsp;</td>
                    <td style="width:35%;" class="kecil tengah">RECEIVED</td>
                </tr>
                <!-- Kunci jarak tanda tangan agar tidak mendorong tabel ke bawah -->
                <tr><td colspan="3" style="height:35px;"></td></tr>
                <tr>
                    <td class="kecil tengah">(..............................)</td>
                    <td class="kecil tengah"></td>
                    <td class="kecil tengah">(..............................)</td>
                </tr>
            </table>
        </center>
        
        <!-- Action Buttons -->
        <div class="no-print" style="margin-top: 15px; text-align: right; border-top: 1px solid #ccc; padding-top: 10px;">
            <button style="padding: 5px 15px; cursor: pointer;" onclick="window.print();">Print</button> 
            <button style="padding: 5px 15px; cursor: pointer;" onclick="window.close();">Close</button>
        </div>
    </div>
</section>

</body>
</html>
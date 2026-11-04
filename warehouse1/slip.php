<?php 
// spb.php (Kompatibel PHP 5.4 & SQL Server 2008 - Multi Page Fit to 14cm)
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

if ($ses_plant === 'plan2' || $ses_plant === 'plan2') {
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

// ==== LOGIKA PEMECAHAN HALAMAN (PAGINATION) ====
$max_rows_per_page = 8; // Dibatasi 8 baris agar Total & TTD tidak tumpah
$chunks = array();
if (count($details) == 0) {
    $chunks[] = array();
} else {
    $chunks = array_chunk($details, $max_rows_per_page);
}
$total_pages = count($chunks);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Material/Part Slip - <?php echo htmlspecialchars($header['TRAN_DOC']); ?></title>
    <style>
        /* Pengaturan ukuran kertas 21.5cm x 14cm Landscape */
        @page { 
            size: 21.5cm 14cm; 
            margin: 0; 
        }
        
        * { box-sizing: border-box; }

        body { 
            background: #fff; 
            color: #000; 
            font-family: 'Calibri', Arial, sans-serif; /* Disamakan dengan layout pertama */
            font-size: 12px; 
            line-height: 1.1; 
            margin: 0; 
            padding: 0; 
        }

        table { width: 100% !important; border-collapse: collapse; }
        
        /* Margin tabel dan spasi dirapatkan */
        .table-slip { margin-top: 5px; margin-bottom: 5px; font-size: 11px; }
        .table-slip th { padding: 3px; font-weight: bold; border: 1px solid #000; text-align: center; background-color: #f2f2f2; }
        .table-slip td { padding: 3px 5px; border: 1px solid #000; height: 18px; vertical-align: top; }
        
        .rapat { line-height: 1.2; margin: 0; padding: 0; font-size: 10px; }
        .kecil { font-size: 11px; padding-right: 2px; color: #000; }
        .tengah { text-align: center; }
        .kanan { text-align: right; }

        .toolbar { 
            position: fixed; 
            top: 15px; 
            right: 15px; 
            z-index: 1000; 
            padding: 10px; 
            background: #ffffff; 
            border: 1px solid #dddddd; 
            border-radius: 6px; 
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15); 
        }
        .btn { 
            display: inline-block; 
            margin-left: 5px; 
            padding: 6px 15px; 
            border: 1px solid #999; 
            border-radius: 4px; 
            background: #f5f5f5; 
            color: #000; 
            font-family: Arial, sans-serif; 
            font-size: 12px; 
            font-weight: bold; 
            text-decoration: none; 
            cursor: pointer; 
        }
        .btn:hover { background: #e5e5e5; }

        @media screen { 
            body { background: #eeeeee; padding: 20px; } 
            .invoice { 
                width: 21.5cm; 
                height: 14cm; 
                margin: 0 auto 20px auto; 
                background: #ffffff; 
                padding: 8mm 15mm; /* Padding disesuaikan agar pas */
                box-shadow: 0 0 10px rgba(0,0,0,0.15); 
                overflow: hidden;
                position: relative;
            } 
        }

        @media print {    
            .no-print, .no-print * { display: none !important; }
            body, html { 
                background: #fff !important; 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact; 
            }
            .invoice { 
                padding: 8mm 15mm !important; /* Padding disesuaikan agar pas tanpa tumpah */
                margin: 0 !important; 
                width: 21.5cm !important;
                height: 14cm !important;
                box-sizing: border-box !important;
                box-shadow: none !important;
                page-break-after: always; 
                break-after: page;
            }
            .invoice:last-of-type {
                page-break-after: auto;
                break-after: auto;
            }
        }
    </style>
</head>
<body>

<div class="no-print toolbar">
    <button class="btn" type="button" onclick="window.print();">Print</button>
    <button class="btn" type="button" onclick="window.close();">Close</button>
</div>

<?php 
// LOOPING HALAMAN
foreach ($chunks as $page_index => $chunk) { 
    $current_page = $page_index + 1;
?>
<section class="invoice">
    <div style="width: 100%; margin: 0 auto;">
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
                    &nbsp;<b>TIME:</b> <?php echo $tran_time; ?><br>
                    <?php if($total_pages > 1) { echo "<i>Hal. " . $current_page . " dari " . $total_pages . "</i>"; } ?>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding:6px 0px 4px 0px; text-align:center;">
                    <b style="font-size:15px; text-decoration: underline;">MATERIAL/PART SLIP</b>
                </td>
            </tr>
        </table>
        
        <!-- Header Dokumen -->
        <table width="100%" border="0" style="text-align: left; margin-bottom: 5px;">
            <tr>
                <td class="kecil" style="width:12%; padding:2px 0;">NOMOR</td>
                <td class="kecil" style="width:2%; text-align:center;">:</td>
                <td class="kecil" style="width:36%; padding:2px 0;"><b style="font-size:12px;"><?php echo htmlspecialchars($header['TRAN_DOC']); ?></b></td>
                <td class="kecil" style="width:15%; padding:2px 0;"></td>
                <td class="kecil" style="width:2%; text-align:center;"></td>
                <td class="kecil" style="width:33%; padding:2px 0;"></td>
            </tr>
            <tr>
                <td class="kecil" style="padding:2px 0;">DATE</td>
                <td class="kecil" style="text-align:center;">:</td>
                <td class="kecil" style="padding:2px 0;"><b><?php echo $tran_adate; ?></b></td>
                <td class="kecil" style="padding:2px 0;">FROM</td>
                <td class="kecil" style="text-align:center;">:</td>
                <td class="kecil" style="padding:2px 0;"><?php echo htmlspecialchars((string)$header['TRTY_DESC']); ?></td>
            </tr>
        </table>
        
        <!-- Tabel Item -->
        <table class="table-slip" width="100%">
            <thead>
            <tr>
                <th style="width:5%">NO</th>
                <th style="width:15%">WO NO</th>
                <th style="width:18%">ITEM CODE</th>
                <th style="width:32%">MATERIAL NAME</th>
                <th style="width:6%">UNIT</th>
                <th style="width:12%">QUANTITY</th>
                <th style="width:12%">REMARK</th>
            </tr>
            </thead>
            <tbody>
            <?php
            // Cetak 8 Baris Maksimal
            for ($i = 0; $i < $max_rows_per_page; $i++) {
                $nomor_urut = ($page_index * $max_rows_per_page) + ($i + 1);

                if (isset($chunk[$i])) {
                    $key = $chunk[$i];
                    echo '<tr>
                            <td class="tengah">'.$nomor_urut.'</td>
                            <td></td>
                            <td>'.htmlspecialchars($key['ITEM_CODE']).'</td>
                            <td>'.htmlspecialchars($key['ITEM_NAME']).'</td>
                            <td class="tengah">'.htmlspecialchars($key['ITEM_UNIT']).'</td>
                            <td class="kanan">'.number_format($key['IT_QTY'], 2).'</td>
                            <td></td>
                          </tr>';
                } else {
                    echo '<tr>
                            <td class="tengah">'.$nomor_urut.'</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                          </tr>';
                }
            }
            
            // Baris Total (Tetap muncul di setiap halaman untuk konsistensi tinggi layout)
            echo '<tr>
                    <td colspan="5" class="kanan" style="padding-right:5px; height: 18px;"><b>TOTAL</b></td>
                    <td class="kanan"><b>'.number_format($total_qty, 2).'</b></td>
                    <td></td>
                  </tr>';
            ?>
            </tbody>
        </table>
        
        <!-- Footer Tanda Tangan -->
        <table width="100%" border="0" style="margin-top: 5px;">
            <tr>
                <td colspan="3" class="kecil" style="text-align: left; padding-bottom: 5px; font-size:8px;">FM.CO.01-36(Revisi2:Tgl.10.Des.2019)</td>
            </tr>
            <tr>
                <td style="width:35%;" class="kecil tengah"><b>DELIVERED</b></td>
                <td style="width:30%;" class="kecil tengah">&nbsp;</td>
                <td style="width:35%;" class="kecil tengah"><b>RECEIVED</b></td>
            </tr>
            <!-- Jarak tanda tangan disesuaikan agar pas di 14cm -->
            <tr><td colspan="3" style="height:25px;"></td></tr>
            <tr>
                <td class="kecil tengah">(........................................)</td>
                <td class="kecil tengah"></td>
                <td class="kecil tengah">(........................................)</td>
            </tr>
        </table>
    </div>
</section>
<?php 
} // END OF FOREACH CHUNKS 
?>

</body>
</html>
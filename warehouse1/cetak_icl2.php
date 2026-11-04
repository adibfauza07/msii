<?php
// cetak_icl.php (Kompatibel PHP 5.4 & SQL Server 2008 - Multi Page Fit to 14cm)
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/global.php";

$rcv_id = isset($_GET['rcv_id']) ? trim($_GET['rcv_id']) : '';

if ($rcv_id === '') { 
    die("ID Receive / ICL tidak valid."); 
}
if ($conn === false) { 
    die("Koneksi database gagal."); 
}

$ses_plant = isset($_SESSION['plant']) ? strtolower(trim($_SESSION['plant'])) : 'plan1';

if ($ses_plant === 'plan2' || $ses_plant === 'plan2') {
    $nama_pabrik    = "PT. IMC TEKNO INDONESIA PLANT 2";
    $alamat_pabrik = "Kawasan Industry Kota Bukit Indah ST-1 <br>
                      Blok A-III Lot No.15E-15F<br>
                      Purwakarta, Jawa Barat 41181, Indonesia.";
} else {
    $nama_pabrik    = "PT. IMC TEKNO INDONESIA PLANT 2";
    $alamat_pabrik = "Kawasan Industry Kota Bukit Indah ST-1 <br>
                      Blok A-III Lot No.15E-15F<br>
                      Purwakarta, Jawa Barat 41181, Indonesia.";
}

$sql = "{CALL sp_cetak_icl (?)}";
$params = array($rcv_id);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) { 
    die("Gagal mengeksekusi stored procedure laporan."); 
}

$header = null;
$details = array();
$total_qty = 0;

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if ($header === null) {
        $rcv_date = $row['RCV_DATE'] instanceof DateTime ? $row['RCV_DATE']->format('d-M-Y') : $row['RCV_DATE'];
        $header = array(
            'RCV_ID'   => $row['RCV_ID'],
            'RCV_DATE' => $rcv_date,
            'RCV_DONO' => $row['RCV_DONO'],
            'RCV_NO'   => $row['RCV_NO'],
            'SUP_COMP' => $row['SUP_COMP']
        );
    }
    $details[] = $row;
    $total_qty += (float)$row['RCVD_QTY'];
}
sqlsrv_free_stmt($stmt);

// ==== LOGIKA PEMECAHAN HALAMAN (PAGINATION) ====
$max_rows_per_page = 8; // Dibatasi 8 baris agar pas dengan header/footer
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
    <title>Cetak ICL - <?php echo isset($header['RCV_NO']) ? htmlspecialchars($header['RCV_NO']) : ''; ?></title>
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
            font-family: 'Calibri', Arial, sans-serif; 
            font-size: 12px; 
            line-height: 1.1; 
            margin: 0; 
            padding: 0; 
        }

        table { width: 100% !important; border-collapse: collapse; }
        
        .table-slip { margin-top: 5px; margin-bottom: 5px; font-size: 11px; }
        .table-slip th { padding: 3px; font-weight: bold; border: 1px solid #000; text-align: center; background-color: #f2f2f2; }
        .table-slip td { padding: 3px 5px; border: 1px solid #000; height: 18px; vertical-align: middle; }
        
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
            font-weight: bold; 
            cursor: pointer; 
            text-decoration: none; 
        }
        .btn:hover { background: #e5e5e5; }

        @media screen { 
            body { background: #eeeeee; padding: 20px; } 
            .invoice { 
                width: 21.5cm; 
                height: 14cm; 
                margin: 0 auto 20px auto; 
                background: #ffffff; 
                padding: 8mm 15mm; 
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
                padding: 8mm 15mm !important; 
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
    <button class="btn" type="button" onclick="window.print();">Print Dokumen</button>
    <button class="btn" type="button" onclick="window.close();">Tutup</button>
</div>

<?php 
// LOOPING HALAMAN
foreach ($chunks as $page_index => $chunk) { 
    $current_page = $page_index + 1;
?>
<section class="invoice">
    <div style="width: 100%; margin: 0 auto;">
        
        <!-- Header Perusahaan & Kotak Checker -->
        <table width="100%" border="0" style="margin-bottom: 2px;">
            <tr>
                <td style="width:70%; text-align:left; vertical-align:top;">
                    <b style="font-size:14px;"><?php echo htmlspecialchars($nama_pabrik); ?></b><br>
                    <b style="font-size:12px;">INCOMING CHECK LIST</b>
                    <div class="rapat" style="margin-top:2px;">
                        <?php echo $alamat_pabrik; ?>
                    </div>
                </td>
                <td style="width:30%; text-align:right; vertical-align:top;">
                    <?php if($total_pages > 1) { echo "<i class='kecil'>Hal. " . $current_page . " dari " . $total_pages . "</i><br>"; } ?>
                    
                    <table width="100%" border="1" style="border-collapse: collapse; margin-top:4px;">
                        <tr>
                            <td class="tengah" style="background:#f9f9f9; padding: 2px; font-size:10px; font-weight:bold;">CHECKER</td>
                            <td class="tengah" style="background:#f9f9f9; padding: 2px; font-size:10px; font-weight:bold;">RECEIVER</td>
                        </tr>
                        <tr>
                            <!-- Jarak area TTD Header dibuat konsisten -->
                            <td class="tengah" style="height:25px;">&nbsp;</td>
                            <td class="tengah"></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Tabel Informasi Dokumen -->
        <table width="100%" border="0" style="text-align: left; margin-top: 5px; margin-bottom: 5px;">
            <tr>
                <td class="kecil" style="width:12%; padding:2px 0;">Date</td>
                <td class="kecil" style="width:2%; text-align:center;">:</td>
                <td class="kecil" style="width:36%; padding:2px 0;"><b><?php echo isset($header['RCV_DATE']) ? $header['RCV_DATE'] : '-'; ?></b></td>
                <td class="kecil" style="width:15%; padding:2px 0;">ICL Number</td>
                <td class="kecil" style="width:2%; text-align:center;">:</td>
                <td class="kecil" style="width:33%; padding:2px 0;"><b><?php echo isset($header['RCV_NO']) ? htmlspecialchars($header['RCV_NO']) : '-'; ?></b></td>
            </tr>
            <tr>
                <td class="kecil" style="padding:2px 0;">Supplier</td>
                <td class="kecil" style="text-align:center;">:</td>
                <td class="kecil" style="padding:2px 0;"><b><?php echo isset($header['SUP_COMP']) ? htmlspecialchars($header['SUP_COMP']) : '-'; ?></b></td>
                <td class="kecil" style="padding:2px 0;">DO Number</td>
                <td class="kecil" style="text-align:center;">:</td>
                <td class="kecil" style="padding:2px 0;"><?php echo isset($header['RCV_DONO']) ? htmlspecialchars($header['RCV_DONO']) : '-'; ?></td>
            </tr>
            <tr>
                <td class="kecil" style="padding:2px 0;">Dept</td>
                <td class="kecil" style="text-align:center;">:</td>
                <td class="kecil" style="padding:2px 0;">PPIC</td>
                <td colspan="3"></td>
            </tr>
        </table>

        <!-- Tabel Detail Barang (Ditetapkan 8 Baris + 1 Baris Total) -->
        <table class="table-slip" width="100%">
            <thead>
                <tr>
                    <th rowspan="2" style="width:15%">CODE</th>
                    <th rowspan="2" style="width:37%">NAME</th>
                    <th rowspan="2" style="width:8%">UNIT</th>
                    <th rowspan="2" style="width:12%">QTY</th>
                    <th colspan="2" style="width:14%">JUDGEMENT</th>
                    <th rowspan="2" style="width:14%">PROBLEM</th>
                </tr>
                <tr>
                    <th>QTY</th>
                    <th>HOLD</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                for ($i = 0; $i < $max_rows_per_page; $i++) {
                    if (isset($chunk[$i])) {
                        $key = $chunk[$i];
                        echo '<tr>
                                <td>'.htmlspecialchars($key['ITEM_CODE']).'</td>
                                <td>'.htmlspecialchars($key['ITEM_NAME']).'</td>
                                <td class="tengah">'.htmlspecialchars($key['ITEM_UNIT']).'</td>
                                <td class="kanan">'.number_format((float)$key['RCVD_QTY'], 0).'</td>
                                <td></td>
                                <td></td>
                                <td></td>
                              </tr>';
                    } else {
                        echo '<tr>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                              </tr>';
                    }
                }
                
                // Baris Total
                echo '<tr>
                        <td colspan="3" class="kanan" style="padding-right:5px; height: 18px;"><b>TOTAL</b></td>
                        <td class="kanan"><b>'.number_format($total_qty, 0).'</b></td>
                        <td colspan="3"></td>
                      </tr>';
                ?>
            </tbody>
        </table>

        <!-- Catatan Kaki (Distribusi Dokumen) -->
        <table width="100%" border="0" style="text-align: left; margin-top: 5px;">
            <tr>
                <td class="rapat" width="9%">White</td><td class="rapat" width="1%">:</td><td class="rapat" width="23%">Acc+Finance</td>
                <td class="rapat" width="9%">Green</td><td class="rapat" width="1%">:</td><td class="rapat" width="23%">Checker</td>
                <td class="rapat" width="4%">R</td><td class="rapat" width="1%">:</td><td class="rapat" width="29%">Raw Material</td>
            </tr>
            <tr>
                <td class="rapat">Yellow</td><td class="rapat">:</td><td class="rapat">Purchasing</td>
                <td class="rapat">Pink</td><td class="rapat">:</td><td class="rapat">Receiver</td>
                <td class="rapat">V</td><td class="rapat">:</td><td class="rapat">Vendor</td>
            </tr>
            <tr>
                <td colspan="6"></td>
                <td class="rapat">P</td><td class="rapat">:</td><td class="rapat">Packing</td>
            </tr>
            <tr>
                <td colspan="9" class="rapat" style="padding-top: 5px; font-size: 8px;">FM.CO.01-40(Revisi5:Tgl.1.Mar.23)</td>
            </tr>
        </table>
        
    </div>
</section>
<?php 
} // END OF FOREACH CHUNKS 
?>

</body>
</html>
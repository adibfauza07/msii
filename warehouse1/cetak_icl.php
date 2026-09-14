<?php
// Kompatibel PHP 5.4 & SQL Server 2008
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Pastikan session berjalan untuk mengambil data cabang login
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

// =========================================================================
// LOGIKA DETEKSI PLANT DARI SESSION (Sesuai Aturan: plan1 vs plan2)
// =========================================================================
// Asumsi variabel session Anda bernama 'plant'. Ubah jika namanya berbeda.
$ses_plant = isset($_SESSION['plant']) ? strtolower(trim($_SESSION['plant'])) : 'plan1';

if ($ses_plant === 'plan2' || $ses_plant === 'plant2') {
    $nama_pabrik   = "PT. IMC TEKNO INDONESIA PLANT 2";
    $alamat_pabrik = "Kawasan Industry Kota Bukit Indah ST-1 <br>
                      Blok A-III Lot No.15E-15F<br>
                      Purwakarta, Jawa Barat 41181, Indonesia.";
} else {
    // Default Plan 1
    $nama_pabrik   = "PT. IMC TEKNO INDONESIA PLANT 1";
    $alamat_pabrik = "Kawasan Berikat Kota Bukit Indah <br>
                      Blok A-II LotNo.29 ST-4D Dangdeur Bungursari<br>
                      Kab. Purwakarta, Jawa Barat 41181<br>
                      Phone (0264) 351441";
}
// =========================================================================

// Eksekusi Stored Procedure sp_cetak_icl (SQL Server 2008 Safe)
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak ICL - <?php echo isset($header['RCV_NO']) ? htmlspecialchars($header['RCV_NO']) : ''; ?></title>
    <style>
        /* Pengaturan Cetak Spesifik A5 Landscape */
        @page { size: A5 landscape; margin: 5mm; }
        
        @media print { 
            .no-print, .no-print * { display: none !important; } 
            body { margin: 0; padding: 0; background: #fff; color: #000; -webkit-print-color-adjust: exact; print-color-adjust: exact;}
            .invoice { page-break-after: avoid; width: 100%; margin: 0 !important; padding: 0 !important; }
        }
        
        body { font-family: 'Segoe UI', Tahoma, sans-serif; color: #000; background: #fff; line-height: 1.1; }
        .invoice { padding: 5px; text-align: center; }
        table { width: 100% !important; border-collapse: collapse; }
        .rapat { line-height: 1.1; margin: 0; padding: 0; font-size: 10px; }
        .kecilsekali { font-size: 9px; padding-right: 2px; }
        .kecil { font-size: 11px; padding-right: 2px; color:#000; }
        .tengah { text-align: center; }
        .kanan { text-align: right; }
        .table-icl { margin-bottom: 5px; }
        .table-icl th { border: 1px solid #000; padding: 2px; font-size: 10px; text-align: center; }
        .table-icl td { border: 1px solid #000; padding: 1px 4px; font-size: 10px; height: 18px; vertical-align: middle; }
    </style>
</head>
<body>

<section class="invoice">
    <div style="width: 100%; margin: 0 auto;">
        <center>
            <!-- Header Perusahaan & Kotak Tanda Tangan Sesuai Gambar -->
            <table width="100%" border="0" style="margin-bottom: 5px;">
                <tr>
                    <td style="width:75%; text-align:left; vertical-align:top;">
                        <b style="font-size:15px;"><?php echo $nama_pabrik; ?></b><br>
                        <b style="font-size:13px;">INCOMING CHECK LIST</b>
                        <div class="rapat" style="margin-top:2px;">
                            <?php echo $alamat_pabrik; ?>
                        </div>
                    </td>
                    <td style="width:25%; text-align:center; vertical-align:top;">
                        <table width="100%" border="1" style="border-collapse: collapse; margin-top:0px;">
                            <tr>
                                <td class="kecilsekali tengah" style="background:#f9f9f9; padding: 1px;">CHECKER</td>
                                <td class="kecilsekali tengah" style="background:#f9f9f9; padding: 1px;">RECEIVER</td>
                            </tr>
                            <tr>
                                <td class="kecil tengah" style="height:25px;">&nbsp;</td>
                                <td class="kecil tengah"></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- Informasi Dokumen -->
            <table width="100%" border="0" style="text-align: left; margin-bottom: 5px;">
                <tr>
                    <th class="kecil" style="width:12%;">Date</th>
                    <th class="kecil" style="width:1%;">:</th>
                    <th class="kecil" style="width:37%;"><b style="font-size:11px;"><?php echo isset($header['RCV_DATE']) ? $header['RCV_DATE'] : '-'; ?></b></th>
                    <th class="kecil" style="width:15%;">ICL Number</th>
                    <th class="kecil" style="width:1%;">:</th>
                    <th class="kecil" style="width:34%;"><b style="font-size:12px;"><?php echo isset($header['RCV_NO']) ? htmlspecialchars($header['RCV_NO']) : '-'; ?></b></th>
                </tr>
                <tr>
                    <th class="kecil">Supplier</th>
                    <th class="kecil">:</th>
                    <th class="kecil"><b style="font-size:11px;"><?php echo isset($header['SUP_COMP']) ? htmlspecialchars($header['SUP_COMP']) : '-'; ?></b></th>
                    <th class="kecil">DO Number</th>
                    <th class="kecil">:</th>
                    <th class="kecil"><?php echo isset($header['RCV_DONO']) ? htmlspecialchars($header['RCV_DONO']) : '-'; ?></th>
                </tr>
                <tr>
                    <th class="kecil">Dept</th>
                    <th class="kecil">:</th>
                    <th class="kecil">PPIC</th>
                    <th class="kecil"></th><th class="kecil"></th><th class="kecil"></th>
                </tr>
            </table>

            <!-- Tabel Detail Material (Fixed 8 Baris) -->
            <table width="100%" border="1" class="table-icl">
                <thead>
                    <tr>
                        <th rowspan="2" style="width:15%">CODE</th>
                        <th rowspan="2" style="width:35%">NAME</th>
                        <th rowspan="2" style="width:8%">UNIT</th>
                        <th rowspan="2" style="width:12%">QTY</th>
                        <th colspan="2" style="width:15%">JUDGEMENT</th>
                        <th rowspan="2" style="width:15%">PROBLEM</th>
                    </tr>
                    <tr>
                        <th>QTY</th>
                        <th>HOLD</th>
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
                                    <td>'.htmlspecialchars($key['ITEM_CODE']).'</td>
                                    <td>'.htmlspecialchars($key['ITEM_NAME']).'</td>
                                    <td class="tengah">'.htmlspecialchars($key['ITEM_UNIT']).'</td>
                                    <td class="kanan">'.number_format((float)$key['RCVD_QTY'], 0).'</td>
                                    <td></td><td></td><td></td>
                                  </tr>';
                        } else {
                            // Baris kosong pengisi slot 8 baris
                            echo '<tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
                        }
                    }
                    ?>
                    <tr>
                        <td colspan="3" class="kanan fw-bold">TOTAL</td>
                        <td class="kanan fw-bold"><?php echo number_format($total_qty, 0); ?></td>
                        <td colspan="3"></td>
                    </tr>
                </tbody>
            </table>

            <!-- Catatan Keterangan Warna & Footer -->
            <table width="100%" border="0" style="text-align: left; margin-top: 2px;">
                <tr>
                    <td class="rapat" width="10%">White</td><td class="rapat" width="1%">:</td><td class="rapat" width="22%">Acc+Finance</td>
                    <td class="rapat" width="10%">Green</td><td class="rapat" width="1%">:</td><td class="rapat" width="22%">Checker</td>
                    <td class="rapat" width="5%">R</td><td class="rapat" width="1%">:</td><td class="rapat" width="28%">Raw Material</td>
                </tr>
                <tr>
                    <td class="rapat">Yellow</td><td class="rapat">:</td><td class="rapat">Purchasing</td>
                    <td class="rapat">Pink</td><td class="rapat">:</td><td class="rapat">Receiver</td>
                    <td class="rapat">V</td><td class="rapat">:</td><td class="rapat">Vendor</td>
                </tr>
                <tr>
                    <td class="rapat"></td><td class="rapat"></td><td class="rapat"></td>
                    <td class="rapat"></td><td class="rapat"></td><td class="rapat"></td>
                    <td class="rapat">P</td><td class="rapat">:</td><td class="rapat">Packing</td>
                </tr>
                <tr>
                    <td colspan="5" class="rapat" style="padding-top: 5px;">FM.CO.01-40(Revisi5:Tgl.1.Mar.23)</td>
                </tr>
            </table>
        </center>

        <!-- Tombol Aksi (Hidden saat Print) -->
        <div class="no-print" style="margin-top: 10px; border-top: 1px solid #ccc; padding-top: 10px; text-align: right;">
            <button style="padding: 5px 15px; cursor: pointer;" onclick="window.print();">Print</button> 
            <button style="padding: 5px 15px; cursor: pointer;" onclick="window.close();">Close</button>
        </div>
    </div>
</section>

<!-- Pastikan jQuery diload jika Anda berencana menggunakannya (meski tidak wajib untuk form print statis) -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
</body>
</html>
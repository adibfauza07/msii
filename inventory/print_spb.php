<?php
// print_spb.php (Kompatibel PHP 5.4 & SQL Server 2008)
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (session_status() == PHP_SESSION_NONE) { 
    session_start(); 
}
require_once __DIR__ . '/../config/database_p1.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'SPB'; 

if ($conn === false || $id === 0) {
    die('Koneksi database gagal atau ID transaksi tidak valid. <button onclick="window.history.back()">Kembali</button>');
}

$sqlHead = "SELECT T.*, S.SUP_COMP, S.SUP_ADDR1 
            FROM TRANS T 
            LEFT JOIN SUPPLIER S ON T.SUP_CODE = S.SUP_CODE 
            WHERE T.TRAN_ID = ?";
$qHead = sqlsrv_query($conn, $sqlHead, array($id));

if ($qHead === false || !($rHead = sqlsrv_fetch_array($qHead, SQLSRV_FETCH_ASSOC))) {
    die('Error: Data transaksi tidak ditemukan. <button onclick="window.history.back()">Kembali</button>');
}
sqlsrv_free_stmt($qHead);

$tranDate = ($rHead['TRAN_DATE'] instanceof DateTime) ? $rHead['TRAN_DATE']->format('d-M-Y') : $rHead['TRAN_DATE'];
$tran_time = ($rHead['TRANS_TIME'] instanceof DateTime) ? $rHead['TRANS_TIME']->format('H:i:s') : (isset($rHead['TRANS_TIME']) ? (string)$rHead['TRANS_TIME'] : '-');
$noKendaraan = ""; 
$kepada = isset($rHead['SUP_COMP']) ? $rHead['SUP_COMP'] : '';
$no_doc = isset($rHead['TRAN_DOC']) ? $rHead['TRAN_DOC'] : '';

$ses_plant = isset($_SESSION['active_plant']) ? strtolower(trim($_SESSION['active_plant'])) : 'plant1';
$companyName = "PT. IMC TEKNO INDONESIA PLANT 1";
$companyAddress = "Kawasan Industri Bukit Indah<br>ST-1 Blok A-III Lot No. 15E - 15F Dangdeur Bungursari<br>Kab. Purwakarta Jawa Barat 41181";

if ($ses_plant == 'p2' || $ses_plant == 'plant2') {
    $companyName = "PT. IMC TEKNO INDONESIA PLANT 2";
    $companyAddress = "Kawasan Industri Kota Bukit Indah<br>ST-1 Blok A-III Lot No. 15E - 15F Dangdeur Bungursari<br>Kab. Purwakarta Jawa Barat 41181";
}

$sqlDetail = "SELECT T.IT_LINENO, T.IT_QTY, T.ITEM_CODE as TRAN_CODE, T.TRAN_REMARK,
                     I.ITEM_CODE as MASTER_CODE, I.ITEM_NAME as MASTER_NAME, I.ITEM_UNIT as MASTER_UNIT
              FROM INV_TRAN T 
              LEFT JOIN ITEMS I ON (T.ITEM_ID = I.ITEM_ID OR (T.ITEM_CODE IS NOT NULL AND LTRIM(RTRIM(T.ITEM_CODE)) = LTRIM(RTRIM(I.ITEM_CODE))))
              WHERE T.TRAN_ID = ? 
              ORDER BY T.IT_LINENO ASC";
$qDet = sqlsrv_query($conn, $sqlDetail, array($id));

if ($qDet === false) {
    die("<div style='background:#ffcccc; padding:20px; border:2px solid red;'><b>Terjadi Kesalahan SQL Detail:</b><br>" . print_r(sqlsrv_errors(), true) . "</div>");
}

$summary_details = array();
$total_qty = 0;

while ($row = sqlsrv_fetch_array($qDet, SQLSRV_FETCH_ASSOC)) {
    // Normalisasi Nama, Kode, Unit
    $item_code = !empty($row['MASTER_CODE']) ? $row['MASTER_CODE'] : (!empty($row['TRAN_CODE']) ? $row['TRAN_CODE'] : '???');
    $item_name = !empty($row['MASTER_NAME']) ? $row['MASTER_NAME'] : (!empty($row['TRAN_REMARK']) ? $row['TRAN_REMARK'] : '');
    $item_unit = !empty($row['MASTER_UNIT']) ? $row['MASTER_UNIT'] : '';
    
    $row['ITEM_CODE'] = $item_code;
    $row['ITEM_NAME'] = $item_name;
    $row['ITEM_UNIT'] = $item_unit;

    // LOGIKA GROUPING (SUM) MENGGUNAKAN PHP ARRAY
    if (!isset($summary_details[$item_code])) {
        // Jika item belum ada di array, masukkan sebagai data baru
        $summary_details[$item_code] = $row;
        $summary_details[$item_code]['IT_QTY'] = (float)$row['IT_QTY'];
    } else {
        // Jika item sudah ada, jumlahkan QTY-nya
        $summary_details[$item_code]['IT_QTY'] += (float)$row['IT_QTY'];
        
        // Logika gabung remark jika ada remark yang berbeda pada item yang sama
        $existing_remark = trim((string)$summary_details[$item_code]['TRAN_REMARK']);
        $new_remark = trim((string)$row['TRAN_REMARK']);
        
        if ($new_remark !== '' && $existing_remark !== $new_remark) {
            // Cegah remark ganda di dalam string
            if (strpos($existing_remark, $new_remark) === false) {
                $summary_details[$item_code]['TRAN_REMARK'] = $existing_remark ? $existing_remark . ', ' . $new_remark : $new_remark;
            }
        }
    }
    
    // Hitung Grand Total Qty
    $total_qty += (float)$row['IT_QTY'];
}
sqlsrv_free_stmt($qDet);

// Reset index array dari associative menjadi numeric berurutan agar fungsi array_chunk bisa bekerja
$details = array_values($summary_details);

// Maksimal 12 Baris
$max_rows_per_page = 12; 
$chunks = (count($details) == 0) ? array(array()) : array_chunk($details, $max_rows_per_page);
$total_pages = count($chunks);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Pengantar Barang - <?= htmlspecialchars($no_doc) ?></title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; color: #000; margin: 0; padding: 15px; background-color: #f9f9f9; }
        .page-container { width: 100%; max-width: 850px; margin: 0 auto; background: #fff; padding: 20px 30px; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-bottom: 20px; box-sizing: border-box; }
        .header-section { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .company-info h3 { margin: 0 0 3px 0; font-size: 15px; font-weight: bold; }
        .company-info p { margin: 0; line-height: 1.3; font-size: 12px; }
        .time-info { font-size: 12px; margin-top: 5px; text-align: right; }
        .doc-title { text-align: center; font-size: 16px; font-weight: bold; margin-bottom: 8px; text-decoration: underline; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 12px; }
        .info-table td { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }
        .info-table .col-label { font-weight: bold; background: #fcfcfc; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 12px; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 4px 6px; }
        .items-table th { background-color: #f5f5f5; text-align: center; font-weight: bold; }
        .tengah { text-align: center; } .kanan { text-align: right; } .kiri { text-align: left; }
        .signature-section { display: flex; justify-content: space-between; text-align: center; margin-bottom: 5px; font-size: 12px; }
        .signature-box { width: 33%; font-weight: bold; }
        .signature-space { margin-top: 50px; font-weight: normal; } 
        .footer-note { font-size: 10px; display: flex; justify-content: space-between; margin-top: 5px; }
        .doc-control { margin-top: 2px; font-size: 10px; text-align: left; }
        .no-print { margin-bottom: 20px; text-align: right; }
        .btn { padding: 6px 15px; cursor: pointer; text-decoration: none; border: 1px solid #ccc; background: #fff; color: #333; display: inline-block; border-radius: 4px; }
        .btn-print { margin-left: 10px; font-weight: bold; border: 1px solid #333; background: #f0f0f0; }

        @media print {
            @page { size: 21.5cm 14cm; margin: 8mm 5mm 5mm 5mm; }
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; background-color: #fff; font-size: 11px; line-height: 1.1; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .page-container { box-shadow: none; padding: 0; width: 100%; max-width: 100%; page-break-inside: avoid; page-break-after: always; margin-bottom: 0; }
            .page-container:last-of-type { page-break-after: auto; }
            .company-info h3 { font-size: 14px; margin-bottom: 2px; }
            .header-section { margin-bottom: 3px; }
            .doc-title { margin-bottom: 3px; font-size: 15px; }
            .info-table, .items-table { margin-bottom: 3px; font-size: 11px; } 
            .info-table td, .items-table th, .items-table td { padding: 2px 4px; } /* Padding dikecilkan agar muat 12 baris */
            .signature-section { margin-bottom: 2px; font-size: 11px; }
            .signature-space { margin-top: 25px; } /* Jarak ttd dikecilkan */
            .footer-note { font-size: 9px; }
            .doc-control { margin-top: 0px; font-size: 9px; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.close()" class="btn">&laquo; Tutup</button>
        <button onclick="window.print()" class="btn btn-print">Cetak Dokumen</button>
    </div>
<?php 
    foreach ($chunks as $page_index => $chunk) { 
        $current_page = $page_index + 1;
?>
    <div class="page-container">
        <div class="header-section">
            <div class="company-info">
                <h3><?= $companyName ?></h3>
                <p><?= $companyAddress ?></p>
            </div>
            <div class="time-info">
                <b>TIME:</b> <?= $tran_time ?><br>
                <?php if ($total_pages > 1): ?>
                    <i>Hal. <?= $current_page ?> dari <?= $total_pages ?></i>
                <?php endif; ?>
            </div>
        </div>
        <div class="doc-title">SURAT PENGANTAR BARANG</div>
        <table class="info-table">
            <tr>
                <td class="col-label" style="width: 12%;">Nomor</td>
                <td class="col-value" style="width: 38%;">: <strong><?= htmlspecialchars($no_doc) ?></strong></td>
                <td rowspan="3" style="width: 50%;">
                    <b>Kepada Yth.</b><br><br>
                    <strong><?= htmlspecialchars($kepada) ?></strong>
                </td>
            </tr>
            <tr>
                <td class="col-label">Tanggal</td>
                <td class="col-value">: <strong><?= $tranDate ?></strong></td>
            </tr>
            <tr>
                <td class="col-label">No Kend</td>
                <td class="col-value">: <?= htmlspecialchars($noKendaraan) ?></td>
            </tr>
        </table>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:5%">NO</th><th style="width:20%">KODE BARANG</th><th style="width:35%">NAMA BARANG</th><th style="width:10%">SATUAN</th><th style="width:10%">JUMLAH</th><th style="width:20%">KETERANGAN</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                for ($i = 0; $i < $max_rows_per_page; $i++) { 
                    $nomor_urut = ($page_index * $max_rows_per_page) + ($i + 1);
                    if (isset($chunk[$i])) {
                        $key = $chunk[$i];
                        $item_code = htmlspecialchars($key['ITEM_CODE']);
                        $item_name = htmlspecialchars($key['ITEM_NAME']);
                        $item_unit = htmlspecialchars($key['ITEM_UNIT']);
                        $qty = (floor($key['IT_QTY']) == $key['IT_QTY']) ? number_format($key['IT_QTY'], 0) : number_format($key['IT_QTY'], 2);
                        $remark    = htmlspecialchars(isset($key['TRAN_REMARK']) ? $key['TRAN_REMARK'] : '');
                    } else {
                        $item_code = '&nbsp;'; $item_name = '&nbsp;'; $item_unit = '&nbsp;'; $qty = '&nbsp;'; $remark = '&nbsp;';
                    }
                ?>
                    <tr>
                        <td class="tengah"><?= $nomor_urut ?></td><td class="tengah"><strong><?= $item_code ?></strong></td><td><?= $item_name ?></td><td class="tengah"><?= $item_unit ?></td><td class="kanan"><?= $qty ?></td><td><?= $remark ?></td>
                    </tr>
                <?php } ?>
                <tr style="font-weight:bold; background-color:#f9f9f9;">
                    <td colspan="4" class="kanan">TOTAL KESELURUHAN</td><td class="kanan"><?= number_format($total_qty, 2) ?></td><td></td>
                </tr>
            </tbody>
        </table>
        <div class="signature-section">
            <div class="signature-box">Penerima<div class="signature-space">(........................................)</div></div>
            <div class="signature-box">Mengetahui<div class="signature-space">(........................................)</div></div>
            <div class="signature-box">Hormat Kami<div class="signature-space">(........................................)</div></div>
        </div>
        <div class="footer-note">
            <div>
                <span>1. White : Customer</span> &nbsp;|&nbsp; <span>2. Pink : Accounting</span> &nbsp;|&nbsp; <span>3. Yellow : Store</span> &nbsp;|&nbsp; <span>4. Blue : Customer</span> &nbsp;|&nbsp; <span>5. Green : Security</span>
            </div>
        </div>
        <div class="doc-control">FM.CO.01-20 (Revisi 4 : Tgl.1 Mei 12)</div>
    </div>
<?php } ?>
</body>
</html>
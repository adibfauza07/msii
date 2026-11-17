<?php
// print_part_slip.php (Aman untuk semua PHP 5.x & SQL Server 2008)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Pengecekan session yang lebih aman untuk PHP versi lama
if (!session_id()) { 
    session_start(); 
}

require_once __DIR__ . '/../config/database_p1.php';

// 1. Sanitasi Input
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!isset($conn) || $conn === false || $id === 0) {
    die('Koneksi database gagal atau ID transaksi tidak valid. <button onclick="window.history.back()">Kembali</button>');
}

// 2. Logika Deteksi Plant (Dari Session Login)
$ses_plant = isset($_SESSION['plant']) ? strtolower(trim($_SESSION['plant'])) : 'plant1';

if ($ses_plant === 'plant2' || $ses_plant === 'p2') {
    $nama_pabrik = "PT. IMC TEKNO INDONESIA PLANT 2";
    $dept = "PPIC DEPARTEMENT PLANT 2"; 
} else {
    $nama_pabrik = "PT. IMC TEKNO INDONESIA PLANT 1";
    $dept = "PPIC DEPARTEMENT";
}

// 3. Eksekusi Kueri Header
$sqlHead = "SELECT TOP 1 T.*, TY.TRTY_DESC 
            FROM TRANS T 
            LEFT JOIN TRTY TY ON T.TRTY_CODE = TY.TRTY_CODE
            WHERE T.TRAN_ID = ?";
$qHead = sqlsrv_query($conn, $sqlHead, array($id));
if ($qHead === false || !sqlsrv_has_rows($qHead)) {
    die('Data Transaksi Tidak Ditemukan. <button onclick="window.history.back()">Kembali</button>');
}
$rHead = sqlsrv_fetch_array($qHead, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($qHead);

// --- LOGIKA FROM & TO ---
$tipe = isset($rHead['TRTY_CODE']) ? trim($rHead['TRTY_CODE']) : '';
$txtFrom = "-";
$txtTo   = "-";

if ($tipe == '08') {
    $txtFrom = "Production";
    $txtTo   = "Warehouse";
} elseif ($tipe == '09') {
    $txtFrom = "Warehouse";
    $txtTo   = "Production";
} else {
    $txtFrom = isset($rHead['TRTY_DESC']) ? $rHead['TRTY_DESC'] : '-';
    $txtTo   = "Warehouse";
}

$tran_time = isset($rHead['TRANS_TIME']) && is_object($rHead['TRANS_TIME']) ? $rHead['TRANS_TIME']->format('H:i:s') : '-';
$tgl = isset($rHead['TRAN_DATE']) && is_object($rHead['TRAN_DATE']) ? $rHead['TRAN_DATE']->format('d-M-Y') : '-';

// 4. Eksekusi Kueri Detail Barang
$sqlDet = "SELECT 
                COALESCE(NULLIF(LTRIM(RTRIM(I.ITEM_CODE)), ''), LTRIM(RTRIM(T.ITEM_CODE)), '-') as ITEM_CODE_DISPLAY,
                COALESCE(I.ITEM_NAME, T.TRAN_REMARK, '-') as ITEM_NAME_DISPLAY,
                COALESCE(I.ITEM_UNIT, '-') as ITEM_UNIT_DISPLAY,
                T.IT_QTY, 
                T.TRAN_REMARK
           FROM INV_TRAN T
           LEFT JOIN ITEMS I ON (T.ITEM_ID = I.ITEM_ID OR LTRIM(RTRIM(T.ITEM_CODE)) = LTRIM(RTRIM(I.ITEM_CODE)))
           WHERE T.TRAN_ID = ? 
           ORDER BY T.IT_LINENO ASC";

$qDet = sqlsrv_query($conn, $sqlDet, array($id));
$summary_details = array();
$total_qty = 0;

if ($qDet !== false) {
    while ($row = sqlsrv_fetch_array($qDet, SQLSRV_FETCH_ASSOC)) {
        $item_code = $row['ITEM_CODE_DISPLAY'];
        
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
}

// Reset index array dari associative menjadi numeric berurutan agar fungsi array_chunk bisa bekerja
$details = array_values($summary_details);

// ==== LOGIKA PEMECAHAN HALAMAN (12 Baris per Halaman) ====
$max_rows_per_page = 12; 
if (count($details) == 0) {
    $chunks = array(array());
} else {
    $chunks = array_chunk($details, $max_rows_per_page);
}
$total_pages = count($chunks);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Part Slip - <?php echo htmlspecialchars(isset($rHead['TRAN_DOC']) ? $rHead['TRAN_DOC'] : ''); ?></title>
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
        
        .tengah { text-align: center; } 
        .kanan { text-align: right; } 
        
        .signature-section { display: flex; justify-content: space-between; text-align: center; margin-bottom: 5px; font-size: 12px; }
        .signature-box { width: 33%; font-weight: bold; }
        .signature-space { margin-top: 40px; font-weight: normal; } 
        
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
            
            /* Padding tabel diperkecil agar 12 baris muat di kertas 14cm */
            .info-table td, .items-table th, .items-table td { padding: 2px 4px; } 
            
            .signature-section { margin-bottom: 2px; font-size: 11px; }
            .signature-space { margin-top: 25px; } 
            .doc-control { margin-top: 0px; font-size: 9px; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.history.back()" class="btn">&laquo; Kembali</button>
        <button onclick="window.print()" class="btn btn-print">Cetak Dokumen</button>
    </div>
<?php 
    foreach ($chunks as $page_index => $chunk) { 
        $current_page = $page_index + 1;
?>
    <div class="page-container">
        
        <!-- HEADER -->
        <div class="header-section">
            <div class="company-info">
                <h3><?php echo $nama_pabrik; ?></h3>
                <p><?php echo $dept; ?></p>
            </div>
            <div class="time-info">
                <b>TIME:</b> <?php echo $tran_time; ?><br>
                <?php if ($total_pages > 1) { echo "<i>Hal. " . $current_page . " dari " . $total_pages . "</i>"; } ?>
            </div>
        </div>
        
        <div class="doc-title">PART/MATERIAL SLIP</div>
        
        <!-- INFO TRANSAKSI -->
        <table class="info-table">
            <tr>
                <td class="col-label" style="width: 12%;">NOMOR</td>
                <td class="col-value" style="width: 38%;">: <strong><?php echo htmlspecialchars($rHead['TRAN_DOC']) . ' / ' . htmlspecialchars($tipe); ?></strong></td>
                <td class="col-label" style="width: 12%;">FROM</td>
                <td class="col-value" style="width: 38%;">: <?php echo htmlspecialchars($txtFrom); ?></td>
            </tr>
            <tr>
                <td class="col-label">DATE</td>
                <td class="col-value">: <strong><?php echo $tgl; ?></strong></td>
                <td class="col-label">TO</td>
                <td class="col-value">: <strong><?php echo htmlspecialchars($txtTo); ?></strong></td>
            </tr>
            <tr>
                <td class="col-label">REMARK</td>
                <td colspan="3" class="col-value">: <?php echo htmlspecialchars(isset($rHead['TRAN_REM']) ? $rHead['TRAN_REM'] : ''); ?></td>
            </tr>
        </table>
        
        <!-- TABEL BARANG -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:5%">NO</th>
                    <th style="width:18%">ITEM CODE</th>
                    <th style="width:38%">ITEM NAME</th>
                    <th style="width:8%">UNIT</th>
                    <th style="width:12%">QTY</th>
                    <th style="width:19%">REMARK</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                for ($i = 0; $i < $max_rows_per_page; $i++) { 
                    $nomor_urut = ($page_index * $max_rows_per_page) + ($i + 1);
                    if (isset($chunk[$i])) {
                        $key = $chunk[$i];
                        $item_code = htmlspecialchars($key['ITEM_CODE_DISPLAY']);
                        $item_name = htmlspecialchars($key['ITEM_NAME_DISPLAY']);
                        $item_unit = htmlspecialchars($key['ITEM_UNIT_DISPLAY']);
                        $item_qty  = number_format((float)$key['IT_QTY'], 2);
                        $remark    = htmlspecialchars(isset($key['TRAN_REMARK']) ? $key['TRAN_REMARK'] : '');
                    } else {
                        $item_code = '&nbsp;'; $item_name = '&nbsp;'; $item_unit = '&nbsp;'; $item_qty  = '&nbsp;'; $remark = '&nbsp;';
                    }
                ?>
                    <tr>
                        <td class="tengah"><?php echo $nomor_urut; ?></td>
                        <td class="tengah"><strong><?php echo $item_code; ?></strong></td>
                        <td><?php echo $item_name; ?></td>
                        <td class="tengah"><?php echo $item_unit; ?></td>
                        <td class="kanan"><?php echo $item_qty; ?></td>
                        <td><?php echo $remark; ?></td>
                    </tr>
                <?php } ?>
                <tr style="font-weight:bold; background-color:#f9f9f9;">
                    <td colspan="4" class="kanan">TOTAL</td>
                    <td class="kanan"><?php echo number_format($total_qty, 2); ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        
        <div class="doc-control">FM.CO.01-36(Revisi2:Tgl.10.Des.2019)</div>
        
        <!-- TANDA TANGAN -->
        <div class="signature-section">
            <div class="signature-box">DELIVERED<div class="signature-space">(........................................)</div></div>
            <div class="signature-box"></div>
            <div class="signature-box">RECEIVED<div class="signature-space">(........................................)</div></div>
        </div>
        
    </div>
<?php } ?>
</body>
</html>
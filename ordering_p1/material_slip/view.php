<?php
session_start();

require_once __DIR__ . "/../../config/database_ordering.php";

if (!isset($conn) || $conn === false) {
    header("Location: ../login.php?error=session_expired");
    exit();
}

function get_header($doc_number) {
    global $conn;
    if (!$conn) return null;
    $sql = "SELECT * FROM TR_MATERIAL_SLIP_HEADER WHERE TRAN_DOC = ?";
    $params = array($doc_number);
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) return null;
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

function get_details($header_id) {
    global $conn;
    if (!$conn) return array();
    $sql = "SELECT * FROM TR_MATERIAL_SLIP_DETAIL WHERE HEADER_ID = ? ORDER BY ID";
    $params = array($header_id);
    $stmt = sqlsrv_query($conn, $sql, $params);
    $results = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
    return $results;
}

function get_instansi() {
    return array('nama_instansi' => 'PT. IMC TEKNO INDONESIA');
}

$doc_number = isset($_GET['doc']) ? $_GET['doc'] : null;
if (!$doc_number) die('Document number required');

$header = get_header($doc_number);
if (!$header) die('Data not found');

$details = get_details($header['ID']);
$sql_count = count($details);
?>
<!DOCTYPE html>
<html>
<head>
    <title>View - Material/Part Slip</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f9f9f9; padding-top: 20px; padding-bottom: 20px; }
        .view-container { 
            max-width: 850px; 
            margin: 0 auto; 
            background: #fff; 
            padding: 30px; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
            border-radius: 4px;
        }
        
        /* Header & Info Styles */
        .company-header { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .company-header h3 { margin: 0 0 5px 0; font-size: 18px; font-weight: bold; }
        .company-header p { margin: 0; font-size: 12px; }
        .doc-title { text-align: center; font-size: 18px; font-weight: bold; margin-bottom: 15px; text-transform: uppercase; }
        
        /* Info Table Styles */
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 13px; }
        .info-table td { border: 1px solid #ddd; padding: 6px 10px; vertical-align: top; }
        .info-table .col-label { font-weight: bold; background: #fcfcfc; }
        .info-table .col-recipient { vertical-align: top; }

        /* Table Item Styles */
        .items-table { font-size: 12px; margin-bottom: 15px; }
        .items-table > thead > tr > th { background-color: #f5f5f5; text-align: center; vertical-align: middle; }
        .tengah { text-align: center; }
        .kanan { text-align: right; }
        
        /* Footer Notes Styles */
        .footer-notes { margin-top: 10px; font-size: 11px; font-weight: normal; }
        .footer-copies { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .footer-code { text-align: left; }
        
        /* SETTING PRINT AGAR TIDAK LONCAT */
        @media print { 
            @page {
                margin: 5mm; /* Margin diperkecil agar area cetak lebih luas */
            }
            .no-print, .no-print * { display: none !important; } 
            
            body { 
                background-color: #fff; 
                padding: 0; 
                margin: 0;
                font-size: 10px; /* Huruf sedikit dikecilkan agar muat */
                -webkit-print-color-adjust: exact;
            }
            .view-container { 
                box-shadow: none; 
                padding: 0; 
                width: 100%; 
                max-width: 100%; 
                margin: 0;
            }
            
            .invoice {
                page-break-inside: avoid; /* Memaksa seluruh form tetap di 1 halaman jika memungkinkan */
            }
            
            /* Penyesuaian agar teks proporsional & jarak ekstra rapat */
            .company-header h3 { font-size: 14px; margin-bottom: 2px; }
            .company-header p { font-size: 9px; line-height: 1.2; }
            .doc-title { font-size: 14px; margin-bottom: 5px; margin-top: 2px; }
            
            .info-table { margin-bottom: 5px; font-size: 10px; }
            .info-table td { padding: 2px 4px; } /* Padding tabel atas dirapatkan */
            
            .items-table { font-size: 10px; margin-bottom: 5px; }
            .items-table > thead > tr > th, 
            .items-table > tbody > tr > td { padding: 2px 4px; } /* Padding tabel item dirapatkan */
            
            /* Area tanda tangan didekatkan ke tabel */
            .signature-area { 
                margin-top: 10px !important; 
                font-size: 10px; 
                page-break-inside: avoid; 
                break-inside: avoid; 
            }
            .footer-notes { font-size: 9px; margin-top: 5px; } 
        }
    </style>
</head>
<body>
<div class="container view-container">
    <!-- Tombol Aksi (Print & Close) -->
    <div class="row no-print" style="margin-bottom:20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
        <div class="col-sm-12 text-right">
            <!-- Ubah href menuju print.php dan bawa parameter doc -->
            <a href="print.php?doc=<?= urlencode($doc_number) ?>" class="btn btn-primary" target="_blank">
                <span class="glyphicon glyphicon-print"></span> Print Layout
            </a>
            <a href="index.php" class="btn btn-default">
                <span class="glyphicon glyphicon-remove"></span> Back to List
            </a>
        </div>
    </div>

    <section class="invoice">
        <!-- Kop Surat -->
        <div class="company-header">
            <div>
                <?php $instansi = get_instansi(); ?>
                 <p>Kawasan Berikat Kota Bukit Indah ST-4D<br>
                Block A-II No.29, Purwakarta, Jawa Barat 41181, Indonesia.<br>			
                </p>
            </div>
            <div style="text-align: right; font-size: 11px;">
                TIME: 
                <?php 
                    if (isset($header['TRANS_TIME']) && is_object($header['TRANS_TIME'])) {
                        echo $header['TRANS_TIME']->format('H:i:s');
                    } else {
                        echo date('H:i:s');
                    }
                ?>
            </div>
        </div>
        
        <div class="doc-title">
            SURAT PENGANTAR BARANG
        </div>
        
        <!-- Info Dokumen & Penerima -->
        <table class="info-table">
            <tr>
                <td class="col-label" style="width: 15%;">Nomor</td>
                <td class="col-value" style="width: 35%;">: <strong><?= htmlspecialchars($header['TRAN_DOC']) ?></strong></td>
                <td class="col-recipient" colspan="2" style="width: 50%;" rowspan="2">
                    Kepada Yth.<br><br>
                    <strong><?= htmlspecialchars((isset($header['RECIPIENT']) && $header['RECIPIENT'] !== '') ? $header['RECIPIENT'] : 'PT. IMC TEKNO INDONESIA (Plan 2)') ?></strong>
                </td>
            </tr>
            <tr>
                <td class="col-label">Tanggal</td>
                <td class="col-value">: 
                    <?php 
                        if (isset($header['TRAN_ADATE']) && is_object($header['TRAN_ADATE'])) {
                            echo $header['TRAN_ADATE']->format('d-M-Y');
                        } else {
                            echo htmlspecialchars(isset($header['TRAN_ADATE']) ? $header['TRAN_ADATE'] : '');
                        }
                    ?>
                </td>
            </tr>
            <tr>
                <td class="col-label">No. Kend</td>
                <td class="col-value">: <?= htmlspecialchars(isset($header['VEHICLE_NO']) ? $header['VEHICLE_NO'] : '') ?></td>
                
                <!-- Jenis BC Sejajar di Kolom Kanan -->
                <td class="col-label" style="width: 15%;">Jenis/NO.BC</td>
                <td class="col-value" style="width: 35%;">: <?= htmlspecialchars((isset($header['BC_NO']) && $header['BC_NO'] !== '') ? $header['BC_NO'] : '/') ?></td>
            </tr>
        </table>
        
        <!-- Detail Barang -->
        <table class="table table-bordered items-table">
            <thead>
                <tr>
                    <th width="5%">NO</th>
                    <th width="15%">KODE BARANG</th>
                    <th width="35%">NAMA BARANG</th>
                    <th width="10%">SATUAN</th>
                    <th width="15%">JUMLAH</th>
                    <th width="20%">KETERANGAN</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $max_rows = 7; // Default limit 7 baris
                $row_count = max($sql_count, $max_rows);
                $sub = 0;
                
                for ($i = 0; $i < $row_count; $i++): 
                    // Jika ada data barang, tampilkan datanya
                    if ($i < $sql_count) {
                        $key = $details[$i];
                        $item_code = htmlspecialchars($key['ITEM_CODE']);
                        $item_name = htmlspecialchars($key['ITEM_NAME']);
                        $item_unit = htmlspecialchars($key['ITEM_UNIT']);
                        $item_qty  = number_format($key['IT_QTY'], 2);
                        $remark    = htmlspecialchars($key['REMARK']);
                        $sub += $key['IT_QTY'];
                    } 
                    // Jika data kurang dari 7, isi sisa baris dengan kosong
                    else {
                        $item_code = '&nbsp;';
                        $item_name = '&nbsp;';
                        $item_unit = '&nbsp;';
                        $item_qty  = '&nbsp;';
                        $remark    = '&nbsp;';
                    }
                ?>
                    <tr>
                        <td class="tengah"><?= ($i + 1) ?></td>
                        <td><?= $item_code ?></td>
                        <td><?= $item_name ?></td>
                        <td class="tengah"><?= $item_unit ?></td>
                        <td class="kanan"><?= $item_qty ?></td>
                        <td><?= $remark ?></td>
                    </tr>
                <?php endfor; ?>
                
                <!-- Baris Total -->
                <tr style="font-weight:bold; background-color:#f9f9f9;">
                    <td colspan="4" class="kanan">TOTAL KESELURUHAN</td>
                    <td class="kanan"><?= number_format($sub, 2) ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        
        <!-- Tanda Tangan -->
        <div class="row signature-area" style="margin-top: 20px; text-align: center;">
            <div class="col-xs-4">
                Penerima<br><br><br><br>
                (.......................................)
            </div>
            <div class="col-xs-4">
                Mengetahui<br><br><br><br>
                (.......................................)
            </div>
            <div class="col-xs-4">
                Hormat Kami<br><br><br><br>
                (.......................................)
            </div>
        </div>

        <!-- Footer Keterangan Copy & Kode Form -->
        <div class="footer-notes">
            <div class="footer-copies">
                <span>1. White:Customer</span>
                <span>2. Pink:Accounting</span>
                <span>3. Yellow:Store</span>
                <span>4. Blue:Customer</span>
                <span>5. Green:Security</span>
            </div>
            <div class="footer-code">
                FM.CO.01-20(Revisi4:Tgl.1.Mei.12)
            </div>
        </div>

    </section>
</div>
</body>
</html>
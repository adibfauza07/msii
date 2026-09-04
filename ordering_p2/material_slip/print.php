<?php
session_start();

require_once __DIR__ . '/../../config/database_ordering.php';

// 1. SET TIMEZONE KE WIB (JAKARTA)
date_default_timezone_set('Asia/Jakarta');

if (!isset($conn) || $conn === false) {
    die('Koneksi database terputus. <button onclick="window.history.back()">Kembali</button>');
}

// --- FUNGSI PEMBANTU ---
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
// -----------------------

$doc_number = isset($_GET['doc']) ? $_GET['doc'] : null;

if (!$doc_number) {
    die('Document number required. <button onclick="window.history.back()">Kembali</button>');
}

$header = get_header($doc_number);
if (!$header) {
    die('Data not found. <button onclick="window.history.back()">Kembali</button>');
}

$details = get_details($header['ID']);
$sql_count = count($details);

// 2. LOGIC PENENTUAN JUMLAH BARIS DAN HALAMAN
// Jika jumlah item 1 atau 2, maksimal 5 baris. Jika lebih, maksimal 7 baris per halaman.
$max_rows_per_page = ($sql_count <= 2) ? 5 : 7;
$total_pages = ($sql_count > 0) ? ceil($sql_count / $max_rows_per_page) : 1;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Pengantar Barang - <?= htmlspecialchars($header['TRAN_DOC']) ?></title>
    <style>
        /* Pengaturan Dasar Font dan Margin */
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #000;
            margin: 0;
            padding: 15px;
            background-color: #f9f9f9;
        }

        /* Container Pembungkus Kertas */
        .page-container {
            width: 100%;
            max-width: 850px; 
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        /* Header Perusahaan */
        .header-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        .company-info h3 {
            margin: 0 0 3px 0;
            font-size: 16px;
            font-weight: bold;
        }
        .company-info p {
            margin: 0;
            line-height: 1.3;
            font-size: 11px;
        }
        .time-info {
            font-size: 11px;
            margin-top: 5px;
        }

        /* Judul Dokumen */
        .doc-title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        /* Tabel Informasi */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 12px;
        }
        .info-table td {
            border: 1px solid #000;
            padding: 5px 8px;
            vertical-align: top;
        }
        .info-table .col-label {
            font-weight: bold;
            background: #fcfcfc;
        }

        /* Tabel Daftar Barang */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }
        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 5px 8px;
        }
        .items-table th {
            background-color: #f5f5f5;
            text-align: center;
            font-weight: bold;
        }
        .tengah { text-align: center; }
        .kanan { text-align: right; }

        /* Bagian Tanda Tangan */
        .signature-section {
            display: flex;
            justify-content: space-between;
            text-align: center;
            margin-bottom: 15px;
            font-size: 11px;
        }
        .signature-box {
            width: 30%;
        }
        .signature-space {
            margin-top: 50px; 
        }

        /* Catatan Kaki */
        .footer-notes {
            font-size: 10px;
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
        .doc-control {
            margin-top: 5px;
            font-size: 10px;
            text-align: left;
        }

       /* --- SETTING KHUSUS SAAT DIPRINT --- */
        @media print {
            @page {
                /* PERBAIKAN: Margin atas disesuaikan jadi 10mm agar jaraknya pas, tidak mepet ke atas kertas */
                /* Format: Atas Kanan Bawah Kiri */
                margin: 10mm 5mm 5mm 5mm; 
            }
            .no-print { display: none !important; }
            
            body { 
                padding: 0; 
                margin: 0;
                background-color: #fff;
                font-size: 10px; 
                -webkit-print-color-adjust: exact;
            }
            .page-container { 
                box-shadow: none; 
                padding: 0; 
                width: 100%; 
                max-width: 100%; 
                page-break-inside: avoid; /* Mencegah terpotong 2 halaman */
                page-break-after: always; /* Pemisah per halaman */
                margin-bottom: 0;
            }
            .page-container:last-of-type {
                page-break-after: auto; /* Hilangkan page-break di halaman terakhir */
            }
            
            /* Merapatkan tabel agar muat 1 lembar */
            .info-table td, .items-table th, .items-table td {
                padding: 3px 5px;
            }
            .signature-space { margin-top: 30px; } /* Jarak tanda tangan dikurangi saat print */
        }
    </style>
</head>
<body onload="window.print()">

  <!-- Tombol Navigasi -->
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <a href="index.php" class="btn" style="padding: 6px 15px; cursor: pointer; text-decoration: none; border: 1px solid #ccc; background: #fff; color: #333; display: inline-block; border-radius: 4px;">&laquo; Kembali ke List</a>
        <button onclick="window.print()" class="btn" style="padding: 6px 15px; cursor: pointer; margin-left: 10px; font-weight: bold; border: 1px solid #333; background: #f0f0f0; border-radius: 4px;">Cetak Dokumen</button>
    </div>

    <!-- LOOPING UNTUK MULTIPLE PAGES -->
    <?php for ($page = 0; $page < $total_pages; $page++): 
        $start_index = $page * $max_rows_per_page;
        // Inisialisasi sub total khusus halaman terakhir
        $sub = 0;
        $sub_box = 0;
    ?>
    <div class="page-container">
        
        <!-- Bagian Kop Perusahaan -->
        <div class="header-section">
            <div class="company-info">
                <?php $instansi = get_instansi(); ?>
                 <h3><?= $instansi['nama_instansi'] ?> PLANT 2</h3>
                <p>Kawasan Industry Kota Bukit Indah ST-1<br>
                Blok A-III Lot No.15E-15F,Purwakarta, Jawa Barat 41181, Indonesia.<br>			
                </p>
            </div>
            <div class="time-info">
                <!-- MENGGUNAKAN WAKTU CETAK REAL-TIME -->
                TIME: <?= date('H:i:s') ?>
            </div>
        </div>

        <!-- Judul -->
        <div class="doc-title">
            SURAT PENGANTAR BARANG
            <?php if ($total_pages > 1): ?>
                <div style="font-size: 10px; margin-top: 3px;">(Halaman <?= $page + 1 ?> dari <?= $total_pages ?>)</div>
            <?php endif; ?>
        </div>

        <!-- Tabel Informasi Dokumen -->
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

        <!-- Tabel Detail Barang (Kolom Baru) -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%">NO</th>
                    <th width="15%">KODE BARANG</th>
                    <th width="30%">NAMA BARANG</th>
                    <th width="10%">SATUAN</th>
                    <th width="10%">JUMLAH</th>
                    <th width="10%">JML BOX</th>
                    <th width="20%">KETERANGAN</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                for ($i = 0; $i < $max_rows_per_page; $i++): 
                    $data_index = $start_index + $i;
                    
                    if ($data_index < $sql_count) {
                        $key = $details[$data_index];
                        $item_code = htmlspecialchars(isset($key['ITEM_CODE']) ? $key['ITEM_CODE'] : '');
                        $item_name = htmlspecialchars(isset($key['ITEM_NAME']) ? $key['ITEM_NAME'] : '');
                        $item_unit = htmlspecialchars(isset($key['ITEM_UNIT']) ? $key['ITEM_UNIT'] : '');
                        $item_qty  = number_format(isset($key['IT_QTY']) ? $key['IT_QTY'] : 0, 0);
                        
                        $jumlah_box_raw = isset($key['JUMLAH_BOX']) ? $key['JUMLAH_BOX'] : null;
                        $jumlah_box = ($jumlah_box_raw !== null && $jumlah_box_raw !== '') ? number_format($jumlah_box_raw, 0) : '';
                        
                        $remark    = htmlspecialchars(isset($key['REMARK']) ? $key['REMARK'] : '');
                    } else {
                        $item_code = '&nbsp;';
                        $item_name = '&nbsp;';
                        $item_unit = '&nbsp;';
                        $item_qty  = '&nbsp;';
                        $jumlah_box = '&nbsp;';
                        $remark    = '&nbsp;';
                    }
                ?>
                    <tr>
                        <td class="tengah"><?= ($data_index + 1) ?></td>
                        <td><?= $item_code ?></td>
                        <td><?= $item_name ?></td>
                        <td class="tengah"><?= $item_unit ?></td>
                        <td class="kanan"><?= $item_qty ?></td>
                        <td class="kanan"><?= $jumlah_box ?></td>
                        <td><?= $remark ?></td>
                    </tr>
                <?php endfor; ?>
                
                <?php 
                // Tampilkan baris Total Keseluruhan hanya pada Halaman Terakhir
                if ($page == $total_pages - 1): 
                    // Kalkulasi ulang subtotal untuk seluruh data
                    $total_qty = 0;
                    $total_box = 0;
                    foreach($details as $d) {
                        $total_qty += isset($d['IT_QTY']) ? $d['IT_QTY'] : 0;
                        $total_box += (int)(isset($d['JUMLAH_BOX']) ? $d['JUMLAH_BOX'] : 0);
                    }
                ?>
                <!-- Baris Total -->
                <tr style="font-weight:bold; background-color:#f9f9f9;">
                    <td colspan="4" class="kanan">TOTAL KESELURUHAN</td>
                    <td class="kanan"><?= number_format($total_qty, 0) ?></td>
                    <td class="kanan"><?= $total_box > 0 ? number_format($total_box, 0) : '' ?></td>
                    <td></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Bagian Tanda Tangan -->
        <div class="signature-section">
            <div class="signature-box">
                Penerima
                <div class="signature-space">
                    (........................................)
                </div>
            </div>
            <div class="signature-box">
                Mengetahui
                <div class="signature-space">
                    (........................................)
                </div>
            </div>
            <div class="signature-box">
                Hormat Kami
                <div class="signature-space">
                    (........................................)
                </div>
            </div>
        </div>

        <!-- Catatan Kaki (Distribusi Surat) -->
        <div class="footer-notes">
            <span>1. White:Customer</span>
            <span>2. Pink:Accounting</span>
            <span>3. Yellow:Store</span>
            <span>4. Blue:Customer</span>
            <span>5. Green:Security</span>
        </div>
        <div class="doc-control">
            FM.CO.01-20(Revisi4:Tgl.1.Mei.12)
        </div>

    </div>
    <?php endfor; ?>
</body>
</html>
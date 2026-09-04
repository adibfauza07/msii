<?php
session_start();

require_once __DIR__ . '/../../config/database_ordering.php';

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
            padding: 20px;
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
                /* Format: Atas Kanan Bawah Kiri */
                margin: 10mm 5mm 5mm 5mm; /* Margin atas diperbesar menjadi 15mm */
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

    <div class="page-container">
        
        <!-- Bagian Kop Perusahaan -->
        <div class="header-section">
            <div class="company-info">
                <?php $instansi = get_instansi(); ?>
                 <?php $instansi = get_instansi(); ?>
                 <h3><?= $instansi['nama_instansi'] ?> PLANT 2</h3>
                <p>Kawasan Industry Kota Bukit Indah ST-1<br>
                Blok A-III Lot No.15E-15F,Purwakarta, Jawa Barat 41181, Indonesia.<br>			
                </p>
            </div>
            <div class="time-info">
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

        <!-- Judul -->
        <div class="doc-title">
            SURAT PENGANTAR BARANG
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
                
                <!-- Attendance sejajar di kolom kanan -->
                <td class="col-label" style="width: 15%;">Attendance</td>
                <td class="col-value" style="width: 35%;">: <?= htmlspecialchars((isset($header['BC_NO']) && trim($header['BC_NO']) !== '' && trim($header['BC_NO']) !== '/') ? $header['BC_NO'] : '') ?></td>
            </tr>
        </table>

        <!-- Tabel Detail Barang (Default 7 Baris) -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%">NO</th>
                    <th width="35%">NAMA BARANG</th>
                    <th width="10%">SATUAN</th>
                    <th width="15%">JUMLAH</th>
                    <th width="35%">KETERANGAN</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $max_rows = 7; 
                $row_count = max($sql_count, $max_rows);
                $sub = 0;
                
                for ($i = 0; $i < $row_count; $i++): 
                    if ($i < $sql_count) {
                        $key = $details[$i];
                        $item_name = htmlspecialchars(isset($key['ITEM_NAME']) ? $key['ITEM_NAME'] : '');
                        $item_unit = htmlspecialchars(isset($key['ITEM_UNIT']) ? $key['ITEM_UNIT'] : '');
                        $item_qty  = number_format(isset($key['IT_QTY']) ? $key['IT_QTY'] : 0, 0);
                        $remark    = htmlspecialchars(isset($key['REMARK']) ? $key['REMARK'] : '');
                        $sub += isset($key['IT_QTY']) ? $key['IT_QTY'] : 0;
                    } else {
                        $item_name = '&nbsp;';
                        $item_unit = '&nbsp;';
                        $item_qty  = '&nbsp;';
                        $remark    = '&nbsp;';
                    }
                ?>
                    <tr>
                        <td class="tengah"><?= ($i + 1) ?></td>
                        <td><?= $item_name ?></td>
                        <td class="tengah"><?= $item_unit ?></td>
                        <td class="tengah"><?= $item_qty ?></td>
                        <td><?= $remark ?></td>
                    </tr>
                <?php endfor; ?>
                
               
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
</body>
</html>
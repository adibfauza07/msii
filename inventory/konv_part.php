<?php
if (session_id() == "") {
    session_start();
}

require_once __DIR__ . '/../config/global.php';

// Set timezone ke WIB (Jakarta)
date_default_timezone_set('Asia/Jakarta');

if (!isset($conn) || $conn === false) {
    die("Koneksi database gagal. <button onclick='window.close()'>Tutup</button>");
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8");
}

$tran_doc = isset($_GET['doc']) ? trim($_GET['doc']) : '';

if ($tran_doc === '') {
    die("Document number (doc) tidak valid. <button onclick='window.close()'>Tutup</button>");
}

// Menjalankan Stored Procedure yang baru saja dioptimasi
$sql = "EXEC [dbo].[RPT_KONV_PART] @TRAN_DOC = ?";
$params = array($tran_doc);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("<pre>Query Error:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $r;
}

// Konfigurasi pagination
$linesPerPage = 25; // Sesuaikan jika ingin lebih rapat atau renggang
$pages = array_chunk($rows, $linesPerPage);
$totalPages = count($pages) > 0 ? count($pages) : 1;
$printDate = date('d-M-Y H:i:s'); // Format Print Date seperti screenshot
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>KONVERSI STOCK OPNAME - <?php echo h($tran_doc); ?></title>
    <style>
        * {
            font-family: Arial, Helvetica, sans-serif;
            box-sizing: border-box;
        }
        
        body {
            font-size: 11px;
            color: #000;
            padding: 20px;
            background: #f0f0f0;
            margin: 0;
        }

        /* Container untuk mode web */
        .page-container {
            background: #fff;
            width: 297mm; /* Lebar A4 Landscape */
            min-height: 210mm; /* Tinggi A4 Landscape */
            margin: 0 auto 20px auto;
            padding: 20px 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        /* Layout Header */
        .header-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: top;
        }
        .company-info {
            font-size: 12px;
            line-height: 1.2;
        }
        .report-title {
            text-align: center;
            font-size: 18px;
            font-weight: normal; /* Sesuai gambar, tidak bold */
        }
        .page-info {
            text-align: right;
            font-size: 11px;
        }

        .print-date {
            text-align: right;
            font-size: 10px;
            margin-bottom: 5px;
        }

        /* Tabel Data Laporan */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .data-table th {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 6px 4px;
            text-align: left;
            font-weight: normal; /* Header tidak bold di screenshot */
        }
        .data-table td {
            padding: 4px;
            vertical-align: top;
        }
        
        /* Helper class */
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }

        /* Tombol print */
        .no-print {
            text-align: right;
            margin-bottom: 20px;
            width: 297mm;
            margin: 0 auto 20px auto;
        }
        .btn {
            padding: 6px 15px;
            cursor: pointer;
            border: 1px solid #333;
            background: #fff;
            border-radius: 4px;
        }

        /* Konfigurasi Print A4 Landscape */
        @media print {
            @page {
                size: A4 landscape;
                margin: 10mm;
            }
            body {
                background: #fff;
                padding: 0;
                margin: 0;
                -webkit-print-color-adjust: exact;
            }
            .no-print { display: none !important; }
            
            .page-container {
                width: 100%;
                min-height: auto;
                margin: 0;
                padding: 0;
                border: none;
                box-shadow: none;
                page-break-inside: avoid;
                page-break-after: always;
            }
            .page-container:last-of-type {
                page-break-after: auto;
            }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn" onclick="window.close()">&laquo; Tutup</button>
        <button class="btn" onclick="window.print()" style="background: #e0e0e0; font-weight:bold; margin-left: 5px;">Print Report</button>
    </div>

    <?php 
    if (empty($pages)) {
        // Jika data kosong, buat 1 halaman kosong
        $pages = array(array());
    }

    foreach ($pages as $pageIndex => $pageRows): 
        $pageNumber = $pageIndex + 1;
    ?>
    <div class="page-container">
        
        <table class="header-table">
            <tr>
                <td style="width: 30%;" class="company-info">
                    P.T. IMC TEKNO INDONESIA<br>
                    PPIC PLANT 1
                </td>
                <td style="width: 40%;" class="report-title">
                    KONVERSI STOCK OPNAME
                </td>
                <td style="width: 30%;" class="page-info">
                    Page <?php echo $pageNumber; ?> of <?php echo $totalPages; ?>
                </td>
            </tr>
        </table>

        <div class="print-date">
            Print Date: <?php echo $printDate; ?>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 25%;">Materials</th>
                    <th style="width: 25%;">Part</th>
                    <th style="width: 10%;" class="text-right">Tag Qty</th>
                    <th style="width: 10%;" class="text-right">konversi</th>
                    <th style="width: 5%;" class="text-center">unit</th>
                    <th style="width: 10%;" class="text-right">Price</th>
                    <th style="width: 15%;" class="text-right">US$ Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if (count($pageRows) > 0) {
                    foreach ($pageRows as $r) {
                        // Perhitungan amount (IT_QTY x PRICE)
                        $tagQty = floatval($r['QTY']);
                        $konversi = floatval($r['IT_QTY']);
                        $price = floatval($r['PRICE']);
                        $amount = $konversi * $price;
                ?>
                <tr>
                    <td>
                        <?php echo h($r['MAT_CODE']); ?><br>
                        <?php echo h($r['MAT_NAME']); ?>
                    </td>
                    <td>
                        <?php echo h($r['ITEM_CODE']); ?><br>
                        <?php echo h($r['ITEM_NAME']); ?>
                    </td>
                    <td class="text-right"><?php echo number_format($tagQty, 2, '.', ','); ?></td>
                    <td class="text-right"><?php echo number_format($konversi, 2, '.', ','); ?></td>
                    <td class="text-center"><?php echo h($r['UNIT']); ?></td>
                    <td class="text-right"><?php echo number_format($price, 4, '.', ','); ?></td>
                    <td class="text-right"><?php echo number_format($amount, 2, '.', ','); ?></td>
                </tr>
                <?php 
                    } 
                } else {
                    echo "<tr><td colspan='7' class='text-center'>Tidak ada data ditemukan</td></tr>";
                }
                ?>
            </tbody>
        </table>

    </div>
    <?php endforeach; ?>

</body>
</html>
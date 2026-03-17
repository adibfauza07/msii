<?php
require_once __DIR__ . '/../config/database_p1.php';

$id = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($id)) {
    die("Error: ID Transaksi tidak ditemukan.");
}

// 1. AMBIL DATA HEADER TRANSAKSI
$sqlHead = "SELECT T.*, S.SUP_COMP, S.SUP_ADDR1 
            FROM TRANS T 
            LEFT JOIN SUPPLIER S ON T.SUP_CODE = S.SUP_CODE 
            WHERE T.TRAN_ID = ?";
$qHead = sqlsrv_query($conn, $sqlHead, array($id));

if ($qHead === false || !($rHead = sqlsrv_fetch_array($qHead, SQLSRV_FETCH_ASSOC))) {
    die("Error: Data transaksi tidak ditemukan.");
}

// Format Tanggal (Sesuai gambar: 11-March-2026)
$tranDate = ($rHead['TRAN_DATE'] instanceof DateTime) ? $rHead['TRAN_DATE']->format('d-F-Y') : $rHead['TRAN_DATE'];
$supplier = isset($rHead['SUP_COMP']) ? $rHead['SUP_COMP'] : '';

// =========================================================================
// 2. AMBIL DATA DETAIL BARANG (DENGAN PHP FALLBACK & ERROR TRAPPING)
// =========================================================================
$sqlDetail = "SELECT T.IT_LINENO, T.IT_QTY, T.ITEM_CODE as TRAN_CODE, T.TRAN_REMARK,
                     I.ITEM_CODE as MASTER_CODE, I.ITEM_NAME as MASTER_NAME, I.ITEM_UNIT as MASTER_UNIT
              FROM INV_TRAN T 
              LEFT JOIN ITEMS I ON (T.ITEM_ID = I.ITEM_ID OR (T.ITEM_CODE IS NOT NULL AND T.ITEM_CODE = I.ITEM_CODE))
              WHERE T.TRAN_ID = ? 
              ORDER BY T.IT_LINENO ASC";

$qDet = sqlsrv_query($conn, $sqlDetail, array($id));

// JIKA QUERY ERROR, MUNCULKAN PESANNYA DI LAYAR
if ($qDet === false) {
    die("<div style='background:#ffcccc; padding:20px; border:2px solid red; font-family:sans-serif;'>
            <b>Terjadi Kesalahan SQL Detail:</b><br>" . print_r(sqlsrv_errors(), true) . 
        "</div>");
}

$dataDetail = [];
$totalQty = 0; // Variabel untuk menghitung Grand Total
while ($row = sqlsrv_fetch_array($qDet, SQLSRV_FETCH_ASSOC)) {
    $row['ITEM_CODE'] = !empty($row['MASTER_CODE']) ? $row['MASTER_CODE'] : (!empty($row['TRAN_CODE']) ? $row['TRAN_CODE'] : '???');
    $row['ITEM_NAME'] = !empty($row['MASTER_NAME']) ? $row['MASTER_NAME'] : (!empty($row['TRAN_REMARK']) ? $row['TRAN_REMARK'] : '');
    $row['ITEM_UNIT'] = !empty($row['MASTER_UNIT']) ? $row['MASTER_UNIT'] : '';
    
    $totalQty += (float)$row['IT_QTY']; // Hitung Total Qty
    $dataDetail[] = $row;
}
// =========================================================================

// Nama & Alamat Perusahaan disesuaikan persis dengan gambar referensi
$companyName = "PT. IMC TEKNO INDONESIA PLANT 1";
$companyAddress = "Kawasan Industri Mitra Karawang<br>Blok A-III No. 15E Dangdeur Bungursari<br>Kab. Purwakarta, Jawa Barat 41181<br>Phone : (0264)351440";

if (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2') {
    $companyName = "PT. IMC TEKNO INDONESIA PLANT 2";
    $companyAddress = "Kawasan Industri Kota Bukit Indah<br>Blok A-III No. 15E Dangdeur Bungursari<br>Kab. Purwakarta, Jawa Barat 41181<br>Phone : (0264)351440";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>ICL Otomatis - <?php echo $rHead['TRAN_DOC']; ?></title>
    <style>
        /* RESET & DASAR: Menggunakan Arial agar persis seperti gambar target */
        body { 
            font-family: Arial, Helvetica, sans-serif; 
            font-size: 11px; 
            color: #000; 
            padding: 20px; 
            background: #f0f0f0; 
            margin: 0;
        }
        
        .page-container {
            background: #fff;
            width: 210mm; /* Lebar A4 Portrait */
            min-height: 140mm; 
            margin: 0 auto;
            padding: 15px 20px;
            box-sizing: border-box;
        }

        /* HEADER SECTION */
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .company-name { 
            font-size: 13px; 
            font-weight: bold; 
            text-decoration: underline; 
            margin-bottom: 2px;
        }
        .doc-title { 
            font-size: 13px; 
            font-weight: bold; 
            margin-bottom: 2px;
        }
        .company-addr { 
            font-size: 10px; 
            line-height: 1.1; 
            color: #333;
        }

        /* KOTAK TTD KANAN ATAS */
        .sign-box {
            border-collapse: collapse;
            font-size: 9px;
            text-align: center;
        }
        .sign-box th, .sign-box td {
            border: 1px solid #000;
            padding: 2px;
            width: 70px;
        }
        .sign-box td { height: 35px; }

        /* META INFO (Tanggal, Supplier, Dokumen) */
        .meta-table {
            width: 100%;
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .meta-table td { padding: 1px 0; vertical-align: top;}

        /* TABEL UTAMA ICL */
        .icl-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 5px;
        }
        .icl-table th, .icl-table td {
            border: 1px solid #000;
            padding: 2px 4px; /* Padding tipis agar compact */
        }
        .icl-table th {
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            font-size: 10px; /* Font header sedikit lebih kecil */
        }
        
        /* LEGEND BAWAH (Menggunakan Table agar rata rapi) */
        .legend-table {
            width: 100%;
            font-size: 10px;
            border-collapse: collapse;
            line-height: 1.2;
            margin-top: 5px;
        }
        .legend-table td { padding: 1px 0; vertical-align: top; }
        
        .footer-no {
            margin-top: 15px;
            font-size: 11px;
        }

        /* PRINT SETTINGS */
        .no-print { text-align: center; margin-bottom: 20px; }
        .btn { padding: 8px 15px; cursor: pointer; border: 1px solid #ccc; background: #fff; font-weight: bold; margin: 0 5px; }
        .btn:hover { background: #e0e0e0; }
        
        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none; }
            .page-container { width: 100%; padding: 0; }
            @page { size: auto; margin: 10mm; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn" onclick="window.close()">&laquo; Tutup</button>
        <button class="btn" onclick="window.print()">Print ICL</button>
    </div>

    <div class="page-container">
        
        <div class="header-container">
            <div>
                <div class="company-name"><?php echo $companyName; ?></div>
                <div class="doc-title">INCOMING CHECK LIST</div>
                <div class="company-addr"><?php echo $companyAddress; ?></div>
            </div>
            <div>
                <table class="sign-box">
                    <tr>
                        <th>CHECKER</th>
                        <th>RECEIVER</th>
                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                    </tr>
                </table>
            </div>
        </div>

        <table class="meta-table">
            <tr>
                <td style="width: 8%;">Date</td>
                <td style="width: 2%;">:</td>
                <td style="width: 45%; font-weight: normal;"><?php echo $tranDate; ?></td>
                <td style="width: 12%;"></td>
                <td style="width: 2%;"></td>
                <td style="width: 31%;"></td>
            </tr>
            <tr>
                <td>Supplier</td>
                <td>:</td>
                <td style="font-weight: normal;"><?php echo $supplier; ?></td>
                <td>ICL Number</td>
                <td>:</td>
                <td style="font-weight: normal;"><?php echo $rHead['TRAN_DOC']; ?></td>
            </tr>
            <tr>
                <td>Dept</td>
                <td>:</td>
                <td style="font-weight: normal;">PPIC</td>
                <td>DO Number</td>
                <td>:</td>
                <td style="font-weight: normal;"><?php echo isset($rHead['TRAN_REM']) ? $rHead['TRAN_REM'] : ''; ?></td>
            </tr>
        </table>

        <table class="icl-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 12%;">CODE</th>
                    <th rowspan="2" style="width: 35%;">NAME</th>
                    <th rowspan="2" style="width: 5%;">UNIT</th>
                    <th rowspan="2" style="width: 10%;">Incoming<br>QTY</th>
                    <th colspan="2" style="width: 10%;">JUDGEMENT</th>
                    <th rowspan="2" style="width: 14%;">PROBLEM</th>
                    <th rowspan="2" style="width: 14%;">RECOMENDATION</th>
                </tr>
                <tr>
                    <th style="width: 5%;">OK</th>
                    <th style="width: 5%;">HOLD</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if (empty($dataDetail)) {
                    echo "<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
                } else {
                    foreach ($dataDetail as $row) {
                        // Format Qty dengan koma ribuan dan 2 desimal (Contoh: 4,800.00)
                        $qty = number_format($row['IT_QTY'], 2, '.', ',');
                        
                        echo "<tr>
                                <td>{$row['ITEM_CODE']}</td>
                                <td>{$row['ITEM_NAME']}</td>
                                <td style='text-align: center; text-transform: capitalize;'>{$row['ITEM_UNIT']}</td>
                                <td style='text-align: right;'>{$qty}</td>
                                <td></td> <td></td> <td></td> <td></td> </tr>";
                    }
                }
                ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align: right; font-weight: bold; border: 1px solid #000; padding: 2px 4px;">TOTAL :</td>
                    <td style="text-align: right; border: 1px solid #000; padding: 2px 4px;"><?php echo number_format($totalQty, 2, '.', ','); ?></td>
                    <td colspan="4" style="border: 1px solid #000;"></td>
                </tr>
            </tfoot>
        </table>

        <table class="legend-table">
            <tr>
                <td style="width: 6%;">White</td>
                <td style="width: 16%;">: Acc + Finnace</td>
                <td style="width: 6%;">Green</td>
                <td style="width: 16%;">: Checker</td>
                <td style="width: 16%;">R : Raw Material</td>
                <td style="width: 16%;">M : Machine</td>
            </tr>
            <tr>
                <td>Yellow</td>
                <td>: Purchasing</td>
                <td>Pink</td>
                <td>: Receiver</td>
                <td>V : Vendor</td>
                <td>A : ATK</td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td>P : Packing</td>
                <td>O : Other</td>
            </tr>
        </table>

        <div class="footer-no">
            FM.CO.01-41 (Revisi 5 : Tgl.17 Mar 26)
        </div>

    </div>

</body>
</html>
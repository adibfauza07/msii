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

// Format Tanggal (Sesuai gambar: 14-February-2023)
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
while ($row = sqlsrv_fetch_array($qDet, SQLSRV_FETCH_ASSOC)) {
    // Logika Pengganti COALESCE di SQL (Dijalankan dengan aman di PHP)
    $row['ITEM_CODE'] = !empty($row['MASTER_CODE']) ? $row['MASTER_CODE'] : (!empty($row['TRAN_CODE']) ? $row['TRAN_CODE'] : '???');
    $row['ITEM_NAME'] = !empty($row['MASTER_NAME']) ? $row['MASTER_NAME'] : (!empty($row['TRAN_REMARK']) ? $row['TRAN_REMARK'] : '');
    $row['ITEM_UNIT'] = !empty($row['MASTER_UNIT']) ? $row['MASTER_UNIT'] : '';
    
    $dataDetail[] = $row;
}
// =========================================================================

// Nama & Alamat Perusahaan berdasarkan Session (Plant 1 atau Plant 2)
$companyName = "PT. IMC TEKNO INDONESIA PLANT 1";
$companyAddress = "Kawasan Industri Mitra Karawang<br>ST-1 Blok A-III Lot No. 15E - 15F Dangdeur Bungursari<br>Kab. Purwakarta Jawa Barat 41181<br>Phone (0264) 351441";

if (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2') {
    $companyName = "PT. IMC TEKNO INDONESIA PLANT 2";
    $companyAddress = "Kawasan Industri Kota Bukit Indah<br>ST-1 Blok A-III Lot No. 15E- 15F Dangdeur Bungursari<br>Kab. Purwakarta Jawa Barat 41181<br>Phone (0264) 351441";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>ICL Otomatis - <?php echo $rHead['TRAN_DOC']; ?></title>
    <style>
        /* RESET & DASAR */
        body { 
            font-family: 'Times New Roman', Times, serif; 
            font-size: 13px; 
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
            padding: 20px;
            box-sizing: border-box;
        }

        /* HEADER SECTION */
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .kop-surat { line-height: 1.2; }
        .company-name { font-size: 14px; font-weight: bold; letter-spacing: 0.5px;}
        .doc-title { font-size: 15px; font-weight: bold; margin-top: 2px; margin-bottom: 2px;}
        .company-addr { font-size: 12px; }

        /* KOTAK TTD KANAN ATAS */
        .sign-box {
            border-collapse: collapse;
            font-size: 11px;
            text-align: center;
        }
        .sign-box th, .sign-box td {
            border: 1px solid #000;
            padding: 3px;
            width: 80px;
        }
        .sign-box th { font-weight: bold; }
        .sign-box td { height: 40px; } /* Ruang ttd kosong */

        /* META INFO (Tanggal, Supplier, Dokumen) */
        .meta-table {
            width: 100%;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .meta-table td { padding: 2px 0; vertical-align: top;}
        .col-left-label { width: 10%; }
        .col-left-colon { width: 2%; }
        .col-left-val { width: 45%; }
        
        .col-right-label { width: 12%; }
        .col-right-colon { width: 2%; }
        .col-right-val { width: 29%; }

        /* TABEL UTAMA ICL */
        .icl-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 10px;
        }
        .icl-table th, .icl-table td {
            border: 1px solid #000;
            padding: 4px;
        }
        .icl-table th {
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            text-transform: uppercase;
        }
        
        /* Ukuran Kolom Tabel */
        .c-code { width: 15%; text-align: left; }
        .c-name { width: 33%; text-align: left; }
        .c-unit { width: 5%; text-align: center; }
        .c-qty { width: 8%; text-align: center; }
        .c-judg { width: 12%; text-align: center; }
        .c-ok-hold { width: 6%; text-align: center; }
        .c-prob { width: 10%; text-align: left; }
        .c-rec { width: 17%; text-align: left; }

        /* LEGEND BAWAH */
        .legend-container {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin-top: 10px;
            line-height: 1.4;
        }
        .legend-col { width: 30%; }
        
        .footer-no {
            margin-top: 30px;
            font-size: 12px;
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
            <div class="kop-surat">
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
                <td class="col-left-label">Date</td>
                <td class="col-left-colon">:</td>
                <td class="col-left-val"><?php echo $tranDate; ?></td>
                
                <td class="col-right-label"></td>
                <td class="col-right-colon"></td>
                <td class="col-right-val"></td>
            </tr>
            <tr>
                <td class="col-left-label">Supplier</td>
                <td class="col-left-colon">:</td>
                <td class="col-left-val"><?php echo $supplier; ?></td>
                
                <td class="col-right-label">ICL Number</td>
                <td class="col-right-colon">:</td>
                <td class="col-right-val"><?php echo $rHead['TRAN_DOC']; ?></td>
            </tr>
            <tr>
                <td class="col-left-label">Dept</td>
                <td class="col-left-colon">:</td>
                <td class="col-left-val">PPIC</td>
                
                <td class="col-right-label">DO Number</td>
                <td class="col-right-colon">:</td>
                <td class="col-right-val"></td>
            </tr>
        </table>

        <table class="icl-table">
            <thead>
                <tr>
                    <th rowspan="2" class="c-code">CODE</th>
                    <th rowspan="2" class="c-name">NAME</th>
                    <th rowspan="2" class="c-unit">UNIT</th>
                    <th rowspan="2" class="c-qty">Incoming<br>QTY</th>
                    <th colspan="2" class="c-judg">JUDGEMENT</th>
                    <th rowspan="2" class="c-prob">PROBLEM</th>
                    <th rowspan="2" class="c-rec">RECOMENDATION</th>
                </tr>
                <tr>
                    <th class="c-ok-hold">OK</th>
                    <th class="c-ok-hold">HOLD</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if (empty($dataDetail)) {
                    echo "<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
                } else {
                    foreach ($dataDetail as $row) {
                        $qty = (floor($row['IT_QTY']) == $row['IT_QTY']) ? number_format($row['IT_QTY'], 0) : number_format($row['IT_QTY'], 2);
                        echo "<tr>
                                <td>{$row['ITEM_CODE']}</td>
                                <td>{$row['ITEM_NAME']}</td>
                                <td style='text-align: center;'>{$row['ITEM_UNIT']}</td>
                                <td style='text-align: center;'>{$qty}</td>
                                <td></td> <td></td> <td></td> <td></td> </tr>";
                    }
                }
                
                // Beri 1 baris kosong sebagai penyeimbang
                echo "<tr>
                        <td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                      </tr>";
                ?>
            </tbody>
        </table>

        <div class="legend-container">
            <div class="legend-col" style="width: 25%;">
                <table style="width:100%; font-size:12px;">
                    <tr><td width="30%">White</td><td width="5%">:</td><td>Acc + Finnace</td></tr>
                    <tr><td>Yellow</td><td>:</td><td>Purchasing</td></tr>
                </table>
            </div>
            
            <div class="legend-col" style="width: 25%;">
                <table style="width:100%; font-size:12px;">
                    <tr><td width="30%">Green</td><td width="5%">:</td><td>Checker</td></tr>
                    <tr><td>Blue</td><td>:</td><td>Receiver</td></tr>
                </table>
            </div>
            
            <div class="legend-col" style="width: 25%;">
                <table style="width:100%; font-size:12px;">
                    <tr><td width="15%">R</td><td width="5%">:</td><td>Raw Material</td></tr>
                    <tr><td>V</td><td>:</td><td>Vendor</td></tr>
                    <tr><td>P</td><td>:</td><td>Packing</td></tr>
                </table>
            </div>
            
            <div class="legend-col" style="width: 25%;">
                <table style="width:100%; font-size:12px;">
                    <tr><td width="15%">M</td><td width="5%">:</td><td>Machine</td></tr>
                    <tr><td>A</td><td>:</td><td>ATK</td></tr>
                    <tr><td>O</td><td>:</td><td>Other</td></tr>
                </table>
            </div>
        </div>

        <div class="footer-no">
            FM.CO.01-41 (Revisi 5 : Tgl.1 Mar 23)
        </div>

    </div>

</body>
</html>
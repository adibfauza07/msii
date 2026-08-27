<?php
require_once __DIR__ . '/../config/database_p1.php';

$id = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($id)) {
    die("Error: ID Transaksi tidak ditemukan.");
}

// =========================================================================
// 1. AMBIL DATA HEADER TRANSAKSI
// =========================================================================
$sqlHead = "SELECT T.*, S.SUP_COMP, S.SUP_ADDR1 
            FROM TRANS T 
            LEFT JOIN SUPPLIER S ON T.SUP_CODE = S.SUP_CODE 
            WHERE T.TRAN_ID = ?";
$qHead = sqlsrv_query($conn, $sqlHead, array($id));

if ($qHead === false || !($rHead = sqlsrv_fetch_array($qHead, SQLSRV_FETCH_ASSOC))) {
    die("Error: Data transaksi tidak ditemukan.");
}

$tranDate = ($rHead['TRAN_DATE'] instanceof DateTime) ? $rHead['TRAN_DATE']->format('d M Y') : $rHead['TRAN_DATE'];

$trty = $rHead['TRTY_CODE'];
$txtFrom = "";
$txtTo   = "";

if ($trty == '08') {
    $txtFrom = "Produksi";
    $txtTo   = "WH";
} elseif ($trty == '09') {
    $txtFrom = "WH";
    $txtTo   = "Produksi";
}

// =========================================================================
// 2. AMBIL DATA DETAIL BARANG
// =========================================================================
$sqlDetail = "SELECT T.IT_LINENO, T.IT_QTY, T.ITEM_CODE as TRAN_CODE, T.TRAN_REMARK,
                     I.ITEM_CODE as MASTER_CODE, I.ITEM_NAME as MASTER_NAME, I.ITEM_UNIT as MASTER_UNIT
              FROM INV_TRAN T 
              LEFT JOIN ITEMS I ON (T.ITEM_ID = I.ITEM_ID OR (T.ITEM_CODE IS NOT NULL AND T.ITEM_CODE = I.ITEM_CODE))
              WHERE T.TRAN_ID = ? 
              ORDER BY T.IT_LINENO ASC";

$qDet = sqlsrv_query($conn, $sqlDetail, array($id));

if ($qDet === false) {
    die("<div style='background:#ffcccc; padding:20px; border:2px solid red;'><b>Kesalahan SQL Detail:</b><br>" . print_r(sqlsrv_errors(), true) . "</div>");
}

$dataDetail = [];
while ($row = sqlsrv_fetch_array($qDet, SQLSRV_FETCH_ASSOC)) {
    $row['ITEM_CODE'] = !empty($row['MASTER_CODE']) ? $row['MASTER_CODE'] : (!empty($row['TRAN_CODE']) ? $row['TRAN_CODE'] : '???');
    $row['ITEM_NAME'] = !empty($row['MASTER_NAME']) ? $row['MASTER_NAME'] : (!empty($row['TRAN_REMARK']) ? $row['TRAN_REMARK'] : '');
    $row['ITEM_UNIT'] = !empty($row['MASTER_UNIT']) ? $row['MASTER_UNIT'] : '';
    $dataDetail[] = $row;
}

// =========================================================================
// LOGIKA PAGINATION - MAKSIMAL 13 ITEM PER HALAMAN (SETENGAH A4)
// =========================================================================
$maxRowsPerPage = 13;
$chunks = array_chunk($dataDetail, $maxRowsPerPage);
if (empty($chunks)) {
    $chunks = [[]]; 
}
$totalPages = count($chunks);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Part Slip - <?php echo $rHead['TRAN_DOC']; ?></title>
    <style>
        /* RESET & DASAR */
        body { 
            font-family: Arial, Helvetica, sans-serif; 
            font-size: 10px; /* Ukuran font diperkecil sedikit agar muat di setengah A4 */
            color: #000; 
            background: #f0f0f0; 
            margin: 0;
            padding: 20px;
        }
        
        /* Container diset ke tinggi Setengah A4 (140mm) */
        .page-container {
            background: #fff;
            width: 210mm; 
            min-height: 140mm; 
            margin: 0 auto 15px auto;
            padding: 10px 15px;
            box-sizing: border-box;
            position: relative;
        }

        /* HEADER SECTION */
        .header-company { font-weight: bold; font-size: 12px; }
        .header-dept { font-weight: bold; font-size: 11px; margin-bottom: 2px; }
        .header-title { text-align: center; font-weight: bold; font-size: 15px; margin-top: -15px; margin-bottom: 10px; letter-spacing: 1px;}

        /* TABEL META */
        .meta-container { width: 100%; margin-bottom: 5px; border-collapse: separate; border-spacing: 5px 0; }
        .meta-box { border-collapse: collapse; width: 100%; font-size: 10px; }
        .meta-box td { border: 1px solid #000; padding: 2px 4px; height: 14px; }
        .meta-label { width: 30%; font-weight: normal; }
        .meta-value { font-weight: bold; }

        /* TABEL UTAMA BARANG - SANGAT RAPAT */
        .main-table { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 5px; }
        .main-table th, .main-table td { border: 1px solid #000; padding: 1px 4px; }
        .main-table th { text-align: center; font-weight: bold; text-transform: uppercase; padding: 2px; }
        .main-table td { height: 15px; } /* Dipaksa 15px agar 13 baris muat */

        /* FOOTER TANDA TANGAN */
        .footer-container { width: 100%; border-collapse: collapse; margin-top: 2px; }
        .footer-container td { vertical-align: top; }
        .ttd-box { text-align: center; font-size: 10px; }
        .ttd-space { height: 35px; } /* Ruang tanda tangan ditipiskan */

        /* TABEL ITEM CHECK */
        .check-table { border-collapse: collapse; width: 100%; font-size: 9px; }
        .check-table th, .check-table td { border: 1px solid #000; padding: 1px 2px; text-align: center; }
        .check-table th { font-weight: bold; }
        .check-table .left-align { text-align: left; font-weight: normal;}

        .footer-notes { font-size: 9px; margin-top: 3px; }

        /* PRINT SETTINGS */
        .no-print { text-align: center; margin-bottom: 20px; }
        .btn { padding: 8px 15px; cursor: pointer; border: 1px solid #ccc; background: #fff; font-weight: bold; margin: 0 5px; }
        
        @media print {
            /* 1. Tambahkan margin: 0 pada html dan body */
            html, body { background: #fff; padding: 0; margin: 0; }
            
            .no-print { display: none; }
            
            .page-container { 
                width: 100%; 
                /* 2. Reset min-height agar tinggi menyesuaikan otomatis dan tidak meluap */
                min-height: auto; 
                padding: 0; 
                margin: 0; 
                box-shadow: none; 
                border: none; 
                /* 3. Mencegah elemen terpotong di tengah halaman */
                page-break-inside: avoid; 
            }
            
            .page-break { page-break-after: always; }
            
            /* 4. Kurangi sedikit margin bawaan agar konten 13 baris muat dengan aman */
            @page { size: 210mm 140mm; margin: 3mm 5mm; } 
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print">
        <button class="btn" onclick="window.close()">&laquo; Tutup</button>
        <button class="btn" onclick="window.print()">Print Part Slip</button>
    </div>

    <?php 
    $globalNo = 1;
    foreach ($chunks as $pageIndex => $chunk): 
        $isLastPage = ($pageIndex == $totalPages - 1);
    ?>

    <div class="page-container <?php echo !$isLastPage ? 'page-break' : ''; ?>">
        
        <div class="header-company">PT. IMC TEKNO INDONESIA</div>
        <div class="header-dept">PPIC DEPARTEMENT</div>
        
        <div class="header-title">PART SLIP</div>

        <!-- BOX META INFO -->
        <table class="meta-container" style="margin-left: -5px;">
            <tr>
                <td style="width: 35%;">
                    <table class="meta-box">
                        <tr>
                            <td class="meta-label">PS NO.</td>
                            <td class="meta-value"><?php echo $rHead['TRAN_DOC']; ?></td>
                        </tr>
                        <tr>
                            <td class="meta-label">DATE</td>
                            <td class="meta-value" style="font-weight: normal;"><?php echo $tranDate; ?></td>
                        </tr>
                    </table>
                </td>
                <td style="width: 30%; vertical-align: top;">
                    <table class="meta-box">
                        <tr>
                            <td class="meta-label" style="width: 25%;">FROM</td>
                            <td class="meta-value"><?php echo $txtFrom; ?></td>
                        </tr>
                    </table>
                </td>
                <td style="width: 35%; vertical-align: top;">
                    <table class="meta-box">
                        <tr>
                            <td class="meta-label" style="width: 20%;">TO</td>
                            <td class="meta-value"><?php echo $txtTo; ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- TABEL BARANG (Pasti 13 Baris per Halaman) -->
        <table class="main-table">
            <thead>
                <tr>
                    <th style="width: 4%;">NO</th>
                    <th style="width: 16%;">ITEM CODE</th>
                    <th style="width: 14%;">PART NO</th>
                    <th style="width: 36%;">PART NAME</th>
                    <th style="width: 6%;">UNIT</th>
                    <th style="width: 10%;">QUANTITY</th>
                    <th style="width: 14%;">REMARK</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Cetak Data Barang
                foreach ($chunk as $row) {
                    $qty = (floor($row['IT_QTY']) == $row['IT_QTY']) ? number_format($row['IT_QTY'], 0) : number_format($row['IT_QTY'], 2);
                    
                    echo "<tr>
                            <td style='text-align: center;'>{$globalNo}</td>
                            <td style='text-align: center;'>{$row['ITEM_CODE']}</td>
                            <td></td>
                            <td>{$row['ITEM_NAME']}</td>
                            <td style='text-align: center; text-transform: uppercase;'>{$row['ITEM_UNIT']}</td>
                            <td style='text-align: right;'>{$qty}</td>
                            <td>{$row['TRAN_REMARK']}</td>
                          </tr>";
                    $globalNo++;
                }

                // Cetak Kotak Kosong agar Layout Tinggi Stabil
                $emptyRowsNeeded = $maxRowsPerPage - count($chunk);
                for ($i = 0; $i < $emptyRowsNeeded; $i++) {
                    echo "<tr>
                            <td>&nbsp;</td> <td></td> <td></td> <td></td> <td></td> <td></td> <td></td>
                          </tr>";
                }
                ?>
            </tbody>
        </table>

        <!-- FOOTER TANDA TANGAN & ITEM CHECK -->
        <table class="footer-container">
            <tr>
                <td style="width: 30%;">
                    <div class="ttd-box">
                        <div>DELIVERED</div>
                        <div class="ttd-space"></div>
                        <div>( ......................................... )</div>
                    </div>
                </td>
                <td style="width: 35%;">
                    <div class="ttd-box">
                        <div>RECEIVED</div>
                        <div class="ttd-space"></div>
                        <div>( ......................................... )</div>
                    </div>
                </td>
                <td style="width: 35%; padding-left: 10px;">
                    <table class="check-table">
                        <tr>
                            <th rowspan="2">Item Check</th>
                            <th colspan="2">Prod</th>
                            <th colspan="2">WH</th>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">Result</td><td style="font-weight: bold;">Sign</td>
                            <td style="font-weight: bold;">Result</td><td style="font-weight: bold;">Sign</td>
                        </tr>
                        <tr><td class="left-align">1. Qty</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td class="left-align">2. Label</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td class="left-align">3. Actual Part</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td colspan="5" class="left-align" style="vertical-align: top; height: 15px;">Remarks :</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- CATATAN BAWAH -->
        <div class="footer-notes">
            <span style="display:inline-block; width: 200px;">FM.CO.01-35 (Revisi 4 Tgl 10 Des 19)</span>
            <span style="display:inline-block; width: 160px;">White/Pink : WH/Produksi</span>
            <span>Yellow/Green : Receiver</span>
            <?php if ($totalPages > 1): ?>
                <span style="float: right; font-style: italic;">Page <?php echo ($pageIndex + 1) . " of " . $totalPages; ?></span>
            <?php endif; ?>
        </div>

    </div>

    <?php endforeach; ?>

</body>
</html>
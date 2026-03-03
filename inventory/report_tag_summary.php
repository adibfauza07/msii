<?php
require_once __DIR__ . '/../config/database_p1.php';

$sopID = isset($_GET['sop']) ? $_GET['sop'] : '';

if (empty($sopID)) {
    die("Error: Parameter SOP ID tidak ditemukan.");
}

// ============================================================================
// 1. AMBIL HEADER INFO DARI TABEL SOP
// ============================================================================
$sopRef = "";
$sopDate = "";
$qHead = sqlsrv_query($conn, "SELECT SOP_REF, SOP_SDATE FROM SOP WHERE SOP_ID = ?", array($sopID));
if ($qHead && $rHead = sqlsrv_fetch_array($qHead, SQLSRV_FETCH_ASSOC)) {
    $sopRef = $rHead['SOP_REF'];
    $sopDate = ($rHead['SOP_SDATE'] instanceof DateTime) ? $rHead['SOP_SDATE']->format('d-M-Y') : $rHead['SOP_SDATE'];
}

// ============================================================================
// 2. JALANKAN STORED PROCEDURE BAWAAN CRYSTAL REPORT
// Menggunakan SP asli 'RPT_TAGSUM_BY_ITEM' agar kalkulasi Kurs USD dan Grouping sama persis.
// ============================================================================
$sqlSP = "EXEC RPT_TAGSUM_BY_ITEM @SOP_ID = ?";
$stmt = sqlsrv_query($conn, $sqlSP, array($sopID));

$dataReport = [];
if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $dataReport[] = $row;
    }
} else {
    // JIKA SP GAGAL ATAU TIDAK ADA DI PLANT 2, GUNAKAN MANUAL QUERY (FALLBACK)
    $sqlManual = "SELECT 
                    'INVENTORY DATA' as SOURCE_TYPE,
                    'ALL ITEMS' as ITTY_DESC,
                    T.ITEM_CODE,
                    MAX(I.ITEM_NAME) as ITEM_NAME,
                    MAX(I.ITEM_CUR) as ITEM_CUR,
                    1 as CURR_VRATE,
                    MAX(I.ITEM_COST) as ITEM_COST,
                    SUM(T.TAG_QTY) as TQTY,
                    (SUM(T.TAG_QTY) * MAX(I.ITEM_COST)) as USDAMOUNT
                FROM TAGS T
                LEFT JOIN ITEMS I ON (T.ITEM_CODE = I.ITEM_CODE OR LTRIM(RTRIM(T.ITEM_CODE)) = LTRIM(RTRIM(I.ITEM_CODE)))
                WHERE T.SOP_ID = ?
                GROUP BY T.ITEM_CODE
                ORDER BY T.ITEM_CODE ASC";
                
    $stmtMan = sqlsrv_query($conn, $sqlManual, array($sopID));
    if ($stmtMan) {
        while ($row = sqlsrv_fetch_array($stmtMan, SQLSRV_FETCH_ASSOC)) {
            $dataReport[] = $row;
        }
    } else {
        die("<div style='color:red; font-family:sans-serif;'><b>Error Query Database:</b><br>" . print_r(sqlsrv_errors(), true) . "</div>");
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tag Summary By Item - <?php echo $sopID; ?></title>
    <style>
        /* RESET & DASAR */
        body { 
            font-family: Arial, Helvetica, sans-serif; 
            font-size: 11px; 
            color: #000; 
            padding: 20px; 
            background: #fff; 
            margin: 0;
        }
        
        .page-container {
            width: 100%; 
            max-width: 210mm; /* Lebar standar kertas */
            margin: 0 auto;
        }

        /* HEADER DOKUMEN */
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .kop-kiri { line-height: 1.3; }
        .kop-kiri .company { font-size: 14px; font-weight: normal; }
        .kop-kiri .dept { font-size: 11px; }
        
        .kop-tengah { text-align: center; flex-grow: 1; padding-top: 5px;}
        .kop-tengah .title { font-size: 16px; font-weight: normal; letter-spacing: 0.5px; }
        
        .kop-kanan { text-align: right; font-size: 11px; line-height: 1.4; }

        /* TABEL REPORT */
        .report-table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 11px; 
        }
        .report-table th { 
            border-top: 1.5px solid #000; 
            border-bottom: 1.5px solid #000; 
            font-weight: normal; 
            padding: 5px 2px; 
        }
        .report-table td { 
            padding: 3px 2px; 
            vertical-align: top; 
        }
        
        /* Lebar Kolom Meniru Gambar */
        .c-item { width: 45%; text-align: left; }
        .c-rate { width: 10%; text-align: right; }
        .c-curr { width: 7%; text-align: center; }
        .c-qty  { width: 10%; text-align: right; }
        .c-cost { width: 14%; text-align: right; }
        .c-usd  { width: 14%; text-align: right; }

        .group-header-1 { padding-top: 15px !important; font-weight: bold; font-size: 12px; text-transform: uppercase; }
        .group-header-2 { font-weight: bold; font-size: 12px; padding-bottom: 5px !important; }

        /* PRINT SETTINGS */
        .no-print { text-align: center; margin-bottom: 20px; }
        .btn { padding: 8px 15px; cursor: pointer; border: 1px solid #ccc; background: #fff; font-weight: bold; margin: 0 5px; }
        .btn:hover { background: #e0e0e0; }
        
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            .page-container { max-width: 100%; }
            @page { size: portrait; margin: 10mm; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print">
        <button class="btn" onclick="window.close()">&laquo; Tutup</button>
        <button class="btn" onclick="window.print()">Print Report</button>
    </div>

    <div class="page-container">
        
        <div class="header-container">
            <div class="kop-kiri">
                <div class="company">P.T. IMC TEKNO INDONESIA</div>
                <div class="dept">PPIC</div>
            </div>
            
            <div class="kop-tengah">
                <div class="title">TAG SUMMARY BY ITEM</div>
            </div>
            
            <div class="kop-kanan">
                Page 1 of 1<br>
                SOP Date : <?php echo $sopDate; ?><br>
                Ref# : <?php echo $sopRef; ?><br>
                Print Date: <?php echo date('d-M-Y H:i'); ?>
            </div>
        </div>

        <table class="report-table">
            <thead>
                <tr>
                    <th class="c-item">I&nbsp;&nbsp;&nbsp;T&nbsp;&nbsp;&nbsp;E&nbsp;&nbsp;&nbsp;M&nbsp;&nbsp;&nbsp;S</th>
                    <th class="c-rate">Rate</th>
                    <th class="c-curr">Curr</th>
                    <th class="c-qty">Qty</th>
                    <th class="c-cost">Cost</th>
                    <th class="c-usd">USD AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if (empty($dataReport)) {
                    echo "<tr><td colspan='6' style='text-align:center; padding: 20px;'>Tidak ada data.</td></tr>";
                } else {
                    $currentSource = null;
                    $currentItty = null;

                    foreach ($dataReport as $row) {
                        
                        // Ekstrak Grouping 1 (SOURCE_TYPE) & Grouping 2 (ITTY_DESC)
                        $source = isset($row['SOURCE_TYPE']) ? trim($row['SOURCE_TYPE']) : '';
                        $itty   = isset($row['ITTY_DESC']) ? trim($row['ITTY_DESC']) : '';
                        
                        // Cetak Header Group 1 (Contoh: PE PRODUCTION ENGINEERING)
                        if ($source !== $currentSource && $source !== '') {
                            echo "<tr><td colspan='6' class='group-header-1'>{$source}</td></tr>";
                            $currentSource = $source;
                            $currentItty = null; // Reset sub-grup agar tercetak lagi
                        }
                        
                        // Cetak Header Group 2 (Contoh: MASTER BATCH)
                        if ($itty !== $currentItty && $itty !== '') {
                            echo "<tr><td colspan='6' class='group-header-2'>{$itty}</td></tr>";
                            $currentItty = $itty;
                        }

                        // Siapkan Data Baris
                        $code = $row['ITEM_CODE'];
                        $name = isset($row['ITEM_NAME']) ? $row['ITEM_NAME'] : '';
                        
                        // Rate
                        $rate = (isset($row['CURR_VRATE']) && $row['CURR_VRATE'] > 0) ? number_format($row['CURR_VRATE'], 0) : '';
                        if ($rate == '1') $rate = '1';
                        
                        // Currency
                        $curr = isset($row['ITEM_CUR']) ? $row['ITEM_CUR'] : '';
                        
                        // Qty (Menghilangkan desimal jika bilangannya bulat)
                        $qtyRaw = isset($row['TQTY']) ? (float)$row['TQTY'] : 0;
                        $qty = (floor($qtyRaw) == $qtyRaw) ? number_format($qtyRaw, 0) : number_format($qtyRaw, 2);
                        
                        // Cost (4 angka di belakang koma)
                        $costRaw = isset($row['ITEM_COST']) ? (float)$row['ITEM_COST'] : 0;
                        $cost = number_format($costRaw, 4);
                        
                        // USD Amount (2 angka di belakang koma)
                        $usdRaw = isset($row['USDAMOUNT']) ? (float)$row['USDAMOUNT'] : 0;
                        $usd = number_format($usdRaw, 2);

                        // Cetak Baris Barang
                        echo "<tr>
                                <td>
                                    <span style='display:inline-block; width:70px;'>{$code}</span>
                                    <span>{$name}</span>
                                </td>
                                <td class='c-rate'>{$rate}</td>
                                <td class='c-curr'>{$curr}</td>
                                <td class='c-qty'>{$qty}</td>
                                <td class='c-cost'>{$cost}</td>
                                <td class='c-usd'>{$usd}</td>
                              </tr>";
                    }
                }
                ?>
                <tr><td colspan="6" style="border-top: 1.5px solid #000; padding:0;"></td></tr>
            </tbody>
        </table>

    </div>

</body>
</html>
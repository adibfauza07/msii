<?php
require_once __DIR__ . '/../config/database_p1.php';

// 1. Tangkap Parameter
require_once __DIR__ . '/../config/database_p1.php';

// 1. Tangkap Parameter 
$passedItem = isset($_GET['item_id']) ? trim($_GET['item_id']) : '0';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$period    = isset($_GET['period']) ? (int)$_GET['period'] : 1;

$itemId = 0;
$itemCodeDisplay = "ALL ITEMS";

// TRANSLATOR: Ubah ITEM_CODE menjadi ITEM_ID dengan sangat aman
if ($passedItem !== '0' && $passedItem !== '') {
    // Cari berdasarkan ITEM_CODE terlebih dahulu
    $qFind = sqlsrv_query($conn, "SELECT TOP 1 ITEM_ID, ITEM_CODE FROM ITEMS WHERE ITEM_CODE = ?", array($passedItem));
    
    if ($qFind && $rFind = sqlsrv_fetch_array($qFind, SQLSRV_FETCH_ASSOC)) {
        $itemId = $rFind['ITEM_ID'];
        $itemCodeDisplay = $rFind['ITEM_CODE'];
    } else {
        // Jika tidak ketemu, cek apakah parameter murni angka (berarti itu memang ITEM_ID)
        if (is_numeric($passedItem)) {
            $itemId = (int)$passedItem;
            
            // Ambil kodenya buat ditampilkan di header laporan
            $qName = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITEM_ID = ?", array($itemId));
            if ($qName && $rName = sqlsrv_fetch_array($qName, SQLSRV_FETCH_ASSOC)) {
                $itemCodeDisplay = $rName['ITEM_CODE'];
            } else {
                $itemCodeDisplay = "ID: " . $itemId;
            }
        } else {
            $itemId = -999; // Dibuat minus agar tidak nyasar ke barang lain
            $itemCodeDisplay = "ITEM NOT FOUND";
        }
    }
}

// Format StartDate menjadi 'YYYYMMDD' untuk Stored Procedure
$startDateFormatted = date('Ymd', strtotime($startDate));

// 2. Eksekusi Stored Procedure
$sql = "EXEC sp_StockAnalysis4 @STARTDATE = ?, @PERIOD = ?, @ITEM_ID = ?";
$params = array($startDateFormatted, $period, $itemId);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("<div style='background:#ffcccc; padding:20px; border:2px solid red;'>
            <b>Error Eksekusi Stored Procedure:</b><br>" . print_r(sqlsrv_errors(), true) . "
         </div>");
}

// 3. Proses Hasil Data & Grouping (Berdasarkan Item, Lalu Lokasi)
$dataList = [];
$groupedData = [];

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $itemKey = $row['ITEM_CODE'] . " - " . $row['ITEM_NAME'];
    $locName = $row['LOC_NAME'];

    if (!isset($groupedData[$itemKey])) {
        $groupedData[$itemKey] = [];
    }
    if (!isset($groupedData[$itemKey][$locName])) {
        $groupedData[$itemKey][$locName] = [];
    }
    
    $groupedData[$itemKey][$locName][] = $row;
}

$pageTitle = ($itemId == 0) ? "ALL ITEMS" : "SINGLE ITEM";
$companyName = isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2' ? "PT. IMC TEKNO INDONESIA PLANT 2" : "PT. IMC TEKNO INDONESIA PLANT 1";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Stock Analysis - <?php echo $pageTitle; ?></title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; margin: 20px; background: #f0f0f0; }
        .page-container { background: #fff; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 20px; box-sizing: border-box; }
        
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0 0 5px 0; font-size: 16px; text-decoration: underline; }
        .header h3 { margin: 0 0 5px 0; font-size: 14px; }

        .info-table { width: 100%; font-size: 12px; font-weight: bold; margin-bottom: 10px; }
        .info-table td { padding: 2px 0; }

        .data-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 20px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 4px; }
        .data-table th { background: #e0e0e0; text-align: center; font-weight: bold; }
        
        .item-header { background: #ffeeba; font-weight: bold; font-size: 12px; border-bottom: 2px solid #000; }
        .loc-header { background: #d9edf7; font-weight: bold; font-size: 11px; font-style: italic; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .no-print { text-align: center; margin-bottom: 20px; }
        .btn { padding: 8px 15px; cursor: pointer; border: 1px solid #ccc; background: #fff; font-weight: bold; margin: 0 5px; }
        
        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .no-print { display: none; }
            .page-container { width: 100%; padding: 0; box-shadow: none; }
            @page { size: portrait; margin: 10mm; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn" onclick="window.close()">&laquo; Tutup</button>
        <button class="btn" onclick="window.print()">Print Report</button>
        <a href="export_stock_analysis.php?item_id=<?php echo $itemId; ?>&start_date=<?php echo $startDate; ?>&period=<?php echo $period; ?>" class="btn">Export to Excel</a>
    </div>

    <div class="page-container">
        <div class="header">
            <h3><?php echo $companyName; ?></h3>
            <h2>STOCK ANALYSIS REPORT</h2>
        </div>

        <table class="info-table">
            <tr>
                <td style="width: 15%;">ITEM CODE</td>
                <td style="width: 2%;">:</td>
                <td style="width: 50%; color: blue;"><?php echo ($itemId == 0) ? "- ALL ITEMS -" : "SPECIFIC ITEM"; ?></td>
                <td style="width: 15%;">START DATE</td>
                <td style="width: 2%;">:</td>
                <td><?php echo date('d-M-Y', strtotime($startDate)); ?></td>
            </tr>
            <tr>
                <td>PERIOD</td>
                <td>:</td>
                <td><?php echo $period; ?> Month(s)</td>
                <td></td><td></td><td></td>
            </tr>
        </table>

        <?php if (empty($groupedData)): ?>
            <div style="text-align:center; padding: 30px; border: 1px solid #000; font-weight:bold;">
                Tidak ada riwayat transaksi (Stock Analysis) pada periode yang dipilih.
            </div>
        <?php else: ?>

            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">DATE</th>
                        <th style="width: 20%;">DOC. NO</th>
                        <th style="width: 25%;">DESCRIPTION</th>
                        <th style="width: 15%;">IN (TIN)</th>
                        <th style="width: 15%;">OUT (TOUT)</th>
                        <th style="width: 15%;">SYS. BALANCE</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groupedData as $itemKey => $locations): ?>
                        
                        <tr class="item-header">
                            <td colspan="6">ITEM : <?php echo $itemKey; ?></td>
                        </tr>

                        <?php foreach ($locations as $locName => $rows): ?>
                            
                            <tr class="loc-header">
                                <td colspan="6">&nbsp;&nbsp;&nbsp;&raquo; LOCATION : <?php echo strtoupper($locName); ?></td>
                            </tr>

                            <?php 
                            $totalIn = 0;
                            $totalOut = 0;

                            foreach ($rows as $r): 
                                $date = ($r['TRAN_DATE'] instanceof DateTime) ? $r['TRAN_DATE']->format('d-M-Y') : $r['TRAN_DATE'];
                                
                                $desc = $r['TRTY_DESC'];
                                if (!empty($r['SUP_ABBR'])) {
                                    $desc .= " (" . $r['SUP_ABBR'] . ")";
                                }

                                $tin  = (floor($r['TIN']) == $r['TIN']) ? number_format($r['TIN'], 0) : number_format($r['TIN'], 2);
                                $tout = (floor($r['TOUT']) == $r['TOUT']) ? number_format($r['TOUT'], 0) : number_format($r['TOUT'], 2);
                                $bal  = (floor($r['BAL_QTY']) == $r['BAL_QTY']) ? number_format($r['BAL_QTY'], 0) : number_format($r['BAL_QTY'], 2);
                                
                                $totalIn += $r['TIN'];
                                $totalOut += $r['TOUT'];
                            ?>
                                <tr>
                                    <td class="text-center"><?php echo $date; ?></td>
                                    <td><?php echo $r['TRAN_DOC']; ?></td>
                                    <td><?php echo $desc; ?></td>
                                    <td class="text-right"><?php echo $tin; ?></td>
                                    <td class="text-right"><?php echo $tout; ?></td>
                                    <td class="text-right" style="color: blue;"><?php echo $bal; ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <tr style="background-color: #fafafa; font-weight: bold;">
                                <td colspan="3" class="text-right">SUBTOTAL <?php echo strtoupper($locName); ?> :</td>
                                <td class="text-right"><?php echo (floor($totalIn) == $totalIn) ? number_format($totalIn, 0) : number_format($totalIn, 2); ?></td>
                                <td class="text-right"><?php echo (floor($totalOut) == $totalOut) ? number_format($totalOut, 0) : number_format($totalOut, 2); ?></td>
                                <td class="text-right"></td>
                            </tr>
                            
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>

    </div>
</body>
</html>
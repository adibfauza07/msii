<?php
require_once __DIR__ . '/../config/database_p1.php';

// 1. Tangkap Parameter (Berupa ITEM_CODE atau ITEM_ID dari form)
$passedItem = isset($_GET['item_id']) ? trim($_GET['item_id']) : '0';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$period    = isset($_GET['period']) ? (int)$_GET['period'] : 1;

$itemId = 0;
$itemCodeDisplay = "ALL ITEMS";

// TRANSLATOR: Ubah ITEM_CODE menjadi ITEM_ID dengan aman
if ($passedItem !== '0' && $passedItem !== '') {
    $qFind = sqlsrv_query($conn, "SELECT TOP 1 ITEM_ID, ITEM_CODE FROM ITEMS WHERE ITEM_CODE = ?", array($passedItem));
    if ($qFind && $rFind = sqlsrv_fetch_array($qFind, SQLSRV_FETCH_ASSOC)) {
        $itemId = $rFind['ITEM_ID'];
        $itemCodeDisplay = $rFind['ITEM_CODE'];
    } else {
        if (is_numeric($passedItem)) {
            $itemId = (int)$passedItem;
            $qName = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITEM_ID = ?", array($itemId));
            if ($qName && $rName = sqlsrv_fetch_array($qName, SQLSRV_FETCH_ASSOC)) {
                $itemCodeDisplay = $rName['ITEM_CODE'];
            }
        } else {
            $itemId = -999; 
            $itemCodeDisplay = "ITEM NOT FOUND";
        }
    }
}

// Format StartDate untuk SP
$startDateFormatted = date('Ymd', strtotime($startDate));

// 2. Eksekusi Stored Procedure
sqlsrv_query($conn, "SET NOCOUNT ON");

$sql = "EXEC sp_StockAnalysis2 @STARTDATE = ?, @PERIOD = ?, @ITEM_ID = ?";
$params = array($startDateFormatted, $period, $itemId);

set_time_limit(0); 
$options = array("QueryTimeout" => 300);
$stmt = sqlsrv_query($conn, $sql, $params, $options);

if ($stmt === false) {
    die("<div style='text-align:center; padding:50px; color:red; font-family:Arial;'>
            <h2>🚨 Gagal Mengeksekusi Stored Procedure 🚨</h2>
            <pre style='background:#fde8e8; padding:20px; text-align:left; border:1px solid red; display:inline-block;'>". print_r(sqlsrv_errors(), true) ."</pre>
         </div>");
}

// 3. Proses Data & Grouping
$groupedData = [];
$companyName = (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2') ? "P.T. IMC TEKNO INDONESIA" : "P.T. IMC TEKNO INDONESIA";

do {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if (isset($row['ITEM_CODE'])) {
            $itemKey = $row['ITEM_CODE'] . "||" . (isset($row['ITEM_NAME']) ? $row['ITEM_NAME'] : '');
            
            // PERBAIKAN: Tarik LOC_GROUP lalu gabungkan dengan LOC_NAME
            $locGroup = isset($row['LOC_GROUP']) ? trim($row['LOC_GROUP']) : "";
            $locName = isset($row['LOC_NAME']) ? trim($row['LOC_NAME']) : "UNASSIGNED";
            $locKey = trim($locGroup . " " . $locName); 
            
            if (!isset($groupedData[$itemKey])) {
                $groupedData[$itemKey] = [];
            }
            if (!isset($groupedData[$itemKey][$locKey])) {
                $groupedData[$itemKey][$locKey] = [];
            }
            
            $groupedData[$itemKey][$locKey][] = $row;
        }
    }
} while (sqlsrv_next_result($stmt));


// Bulan String untuk Header
$startMonthStr = date('F - Y', strtotime($startDate));
$bulanEn = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
$bulanId = array('Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
$startMonthStr = str_replace($bulanEn, $bulanId, $startMonthStr);

function formatCR($val) {
    if (round($val, 2) == 0) return '-';
    return number_format($val, 2, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Stock Analysis Report</title>
    <style>
        body { font-family: "Arial", Helvetica, sans-serif; font-size: 12px; margin: 0; background: #e0e0e0; }
        .page-container { background: #fff; width: 210mm; min-height: 297mm; margin: 20px auto; padding: 25px 40px; box-sizing: border-box; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        
        .header-table { width: 100%; margin-bottom: 20px; }
        .header-table td { vertical-align: top; }
        .company-name { font-size: 15px; font-weight: normal; }
        .title-center { text-align: center; }
        .title-center h2 { margin: 0 0 2px 0; font-size: 24px; font-weight: normal; letter-spacing: 1px; }
        .info-desc { font-size: 12px; margin-bottom: 1px; }
        .page-info { text-align: right; font-size: 11px; }
        
        .data-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .data-table td, .data-table th { padding: 2px 2px; vertical-align: top; border: none; word-wrap: break-word; }

        .item-header th, .item-header td { 
            border-top: 1px solid #000; 
            border-bottom: 1px solid #000; 
            font-weight: bold; 
            font-size: 13px; 
            padding: 5px 2px;
        }
        .item-header .code { font-size: 14px; }
        
        .loc-header td { font-weight: bold; font-style: italic; font-size: 15px; padding-top: 15px; padding-bottom: 5px; text-transform: uppercase; }
        
        /* PERBAIKAN: Posisi Kolom Dibuat Rata Kiri */
        .beg-balance td { padding-top: 3px; padding-bottom: 3px; font-size: 12px; }
        .beg-balance .lbl-col { text-align: left; padding-left: 10px; white-space: nowrap; }
        .beg-balance .lbl-text { color: blue; font-style: italic; margin-right: 20px; }
        .beg-balance .lbl-date { color: blue; font-style: italic; margin-right: 25px; }
        .beg-balance .lbl-bal { color: black; font-style: normal; }
        
        .row-data td { font-size: 12px; padding: 2px 2px; }
        .row-data .desc { text-align: left; padding-left: 10px; white-space: nowrap; }
        
        .num-cell { text-align: right; }
        .in-text { color: green; }
        .out-text { color: red; }
        .bal-text { color: black; }
        .bal-total { color: blue; }
        
        .grand-total td { font-weight: bold; padding-top: 8px; padding-bottom: 25px; font-size: 13px; }
        .grand-total .lbl { text-align: right; padding-right: 15px; white-space: nowrap; }
        
        .no-print { text-align: center; margin-bottom: 20px; padding: 15px; background: #fff; border-bottom: 1px solid #ccc; }
        .btn { padding: 8px 15px; cursor: pointer; border: 1px solid #ccc; background: #f8f9fa; font-weight: bold; margin: 0 5px; }
        
        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .no-print { display: none; }
            .page-container { width: 100%; margin: 0; padding: 0; box-shadow: none; }
            @page { size: portrait; margin: 15mm 10mm; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn" onclick="window.close()">Tutup</button>
        <button class="btn" onclick="window.print()">Print Report</button>
        <a href="export_stock_analysis.php?item_id=<?php echo htmlspecialchars($passedItem); ?>&start_date=<?php echo htmlspecialchars($startDate); ?>&period=<?php echo $period; ?>" class="btn" style="text-decoration:none; color:black;">Export to Excel</a>
    </div>

    <div class="page-container">
        <!-- HEADER -->
        <table class="header-table">
            <tr>
                <td style="width: 33%;" class="company-name">
                    <?php echo htmlspecialchars($companyName); ?>
                </td>
                <td style="width: 34%;" class="title-center">
                    <h2>STOCK ANALYSIS</h2>
                    <div class="info-desc">Starting Month : <?php echo $startMonthStr; ?></div>
                    <div class="info-desc">Period : <?php echo $period; ?> Month(s)</div>
                </td>
                <td style="width: 33%;" class="page-info">
                    <div>Page 1 of 1</div>
                    <div>Print Date : <?php echo date('m/d/Y'); ?></div>
                    <div><?php echo date('g:i:sA'); ?></div>
                </td>
            </tr>
        </table>

        <!-- KONTEN TABEL -->
        <?php if (empty($groupedData)): ?>
            <div style="text-align: center; padding: 40px; font-weight: bold; font-size: 13px;">
                Tidak ada riwayat transaksi pada periode yang dipilih.
            </div>
        <?php else: ?>
            <table class="data-table">
                <!-- COLGROUP: Penyesuaian Lebar Kolom -->
                <colgroup>
                    <col style="width: 25%;"> <!-- Doc No -->
                    <col style="width: 42%;"> <!-- Deskripsi -->
                    <col style="width: 11%;">  <!-- IN -->
                    <col style="width: 11%;">  <!-- OUT -->
                    <col style="width: 11%;">  <!-- BAL -->
                </colgroup>
                
                <?php foreach ($groupedData as $itemKey => $locations): 
                    list($itemCode, $itemName) = explode("||", $itemKey);
                ?>
                    <tr>
                        <td colspan="5" style="font-weight: bold; font-size: 12px; padding-bottom: 2px;">[]</td>
                    </tr>
                    
                    <tr class="item-header">
                        <td colspan="2" class="code"><?php echo htmlspecialchars($itemCode); ?> &nbsp;&nbsp; <?php echo htmlspecialchars($itemName); ?></td>
                        <th class="num-cell in-text">IN</th>
                        <th class="num-cell out-text">OUT</th>
                        <th class="num-cell">BAL</th>
                    </tr>
                    
                    <?php foreach ($locations as $locKey => $rows): 
                        $runningBalance = isset($rows[0]['BAL_QTY']) ? (float)$rows[0]['BAL_QTY'] : 0;
                        $sumIn = 0;
                        $sumOut = 0;
                        $currentDate = '';
                        
                        echo "<tr class='loc-header'><td colspan='5'>".htmlspecialchars($locKey)."</td></tr>";
                        
                        foreach ($rows as $index => $r):
                            $tDateObj = $r['TRAN_DATE'];
                            $tDateStr = ($tDateObj instanceof DateTime) ? $tDateObj->format('Y-m-d') : $tDateObj;
                            
                            // PRINT BEGINNING BALANCE
                            if ($tDateStr != $currentDate) {
                                if ($index == 0) {
                                    $lblDate = date('j-M-y', strtotime($startDate));
                                } else {
                                    $lblDate = date('j-M-y', strtotime($tDateStr . ' -1 day'));
                                }
                                
                                // FORMAT HTML BEGINNING BALANCE
                                echo "<tr class='beg-balance'>
                                        <td></td>
                                        <td class='lbl-col'>
                                            <span class='lbl-text'>BEGINING BALANCE :</span>
                                            <span class='lbl-date'>{$lblDate}</span>
                                            <span class='lbl-bal'>".formatCR($runningBalance)."</span>
                                        </td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                      </tr>";
                                      
                                $currentDate = $tDateStr;
                            }
                            
                            $inQty  = isset($r['TIN']) ? (float)$r['TIN'] : 0;
                            $outQty = isset($r['TOUT']) ? (float)$r['TOUT'] : 0;
                            
                            $runningBalance += ($inQty - $outQty); 
                            $sumIn += $inQty;
                            $sumOut += $outQty;
                            
                            $docNo    = isset($r['TRAN_DOC']) ? $r['TRAN_DOC'] : '';
                            $trtyCode = isset($r['TRTY_CODE']) ? $r['TRTY_CODE'] : '';
                            $trtyDesc = isset($r['TRTY_DESC']) ? $r['TRTY_DESC'] : '';
                            
                            $displayDate = date('d-M-y', strtotime($tDateStr));
                            $displayDesc = "{$displayDate} [{$trtyCode}] {$trtyDesc}";
                        ?>
                            <tr class="row-data">
                                <td><?php echo htmlspecialchars($docNo); ?></td>
                                <td class="desc"><?php echo htmlspecialchars($displayDesc); ?></td>
                                <td class="num-cell in-text"><?php echo formatCR($inQty); ?></td>
                                <td class="num-cell out-text"><?php echo formatCR($outQty); ?></td>
                                <td class="num-cell bal-text"><?php echo formatCR($runningBalance); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <tr class="grand-total">
                            <td colspan="2" class="lbl">GRAND TOTAL :</td>
                            <td class="num-cell in-text"><?php echo formatCR($sumIn); ?></td>
                            <td class="num-cell out-text"><?php echo formatCR($sumOut); ?></td>
                            <td class="num-cell bal-total"><?php echo formatCR($runningBalance); ?></td>
                        </tr>
                        
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
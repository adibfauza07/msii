<?php
require_once __DIR__ . '/../config/database_p1.php';

// 1. Tangkap Parameter 
$passedItem = isset($_GET['item_id']) ? trim($_GET['item_id']) : '0';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$period    = isset($_GET['period']) ? (int)$_GET['period'] : 1;

// 2. Terjemahkan Parameter ke ITEM_ID (Wajib agar SP membaca dengan benar)
$itemId = 0;
if ($passedItem !== '0' && $passedItem !== '') {
    $qFind = sqlsrv_query($conn, "SELECT TOP 1 ITEM_ID FROM ITEMS WHERE ITEM_CODE = ?", array($passedItem));
    if ($qFind && $rFind = sqlsrv_fetch_array($qFind, SQLSRV_FETCH_ASSOC)) {
        $itemId = $rFind['ITEM_ID'];
    } else {
        if (is_numeric($passedItem)) {
            $itemId = (int)$passedItem;
        } else {
            $itemId = -999;
        }
    }
}

// 3. Eksekusi Stored Procedure
$startDateFormatted = date('Ymd', strtotime($startDate));
$sql = "EXEC sp_StockAnalysis2 @STARTDATE = ?, @PERIOD = ?, @ITEM_ID = ?";
$params = array($startDateFormatted, $period, $itemId);

set_time_limit(0); 
ini_set('memory_limit', '1024M');
$options = array("QueryTimeout" => 300);
$stmt = sqlsrv_query($conn, $sql, $params, $options);

// 4. Proses Grouping Data
$groupedData = [];
if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $itemKey = (isset($row['ITEM_CODE']) ? $row['ITEM_CODE'] : '') . "||" . (isset($row['ITEM_NAME']) ? $row['ITEM_NAME'] : '');
        $locName = isset($row['LOC_NAME']) ? trim($row['LOC_NAME']) : "UNASSIGNED";
        $groupedData[$itemKey][$locName][] = $row;
    }
}

// 5. Header Export ke format Excel .xls
$filename = "Stock_Analysis_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: max-age=0");
?>
<!-- Format Tabel Datar (Flat Table) untuk kenyamanan Filter Excel -->
<table border="1">
    <thead>
        <tr>
            <th style="background-color: #d9d9d9; font-weight:bold;">DATE</th>
            <th style="background-color: #d9d9d9; font-weight:bold;">LOC GROUP</th>
            <th style="background-color: #d9d9d9; font-weight:bold;">LOCATION</th>
            <th style="background-color: #d9d9d9; font-weight:bold;">ITEM CODE</th>
            <th style="background-color: #d9d9d9; font-weight:bold;">ITEM NAME</th>
            <th style="background-color: #d9d9d9; font-weight:bold;">DOC NO</th>
            <th style="background-color: #d9d9d9; font-weight:bold;">TYPE</th>
            <th style="background-color: #d9d9d9; font-weight:bold;">IN (TIN)</th>
            <th style="background-color: #d9d9d9; font-weight:bold;">OUT (TOUT)</th>
            <th style="background-color: #d9d9d9; font-weight:bold;">BALANCE</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        if (!empty($groupedData)) {
            foreach ($groupedData as $itemKey => $locations) {
                list($itemCode, $itemName) = explode("||", $itemKey);
                
                foreach ($locations as $locName => $rows) {
                    $runningBalance = isset($rows[0]['BAL_QTY']) ? (float)$rows[0]['BAL_QTY'] : 0;
                    $locGroup = isset($rows[0]['LOC_GROUP']) ? htmlspecialchars($rows[0]['LOC_GROUP']) : '';
                    $currentDate = '';
                    
                    foreach ($rows as $index => $r) {
                        $tDateObj = $r['TRAN_DATE'];
                        $tDateStr = ($tDateObj instanceof DateTime) ? $tDateObj->format('Y-m-d') : $tDateObj;
                        
                        // Cetak Baris Beginning Balance setiap ganti hari
                        if ($tDateStr != $currentDate) {
                            $lblDate = ($index == 0) ? date('d-M-y', strtotime($startDate)) : date('d-M-y', strtotime($tDateStr . ' -1 day'));
                            echo "<tr>
                                    <td>{$lblDate}</td>
                                    <td>{$locGroup}</td>
                                    <td>" . htmlspecialchars($locName) . "</td>
                                    <td>" . htmlspecialchars($itemCode) . "</td>
                                    <td>" . htmlspecialchars($itemName) . "</td>
                                    <td></td>
                                    <td style='color:blue; font-style:italic;'>BEGINING BALANCE</td>
                                    <td>0</td>
                                    <td>0</td>
                                    <td style='font-weight:bold;'>{$runningBalance}</td>
                                  </tr>";
                            $currentDate = $tDateStr;
                        }
                        
                        // Kalkulasi Mutasi Stok
                        $inQty  = isset($r['TIN']) ? (float)$r['TIN'] : 0;
                        $outQty = isset($r['TOUT']) ? (float)$r['TOUT'] : 0;
                        $runningBalance += ($inQty - $outQty);
                        
                        $docNo    = isset($r['TRAN_DOC']) ? htmlspecialchars($r['TRAN_DOC']) : '';
                        $trtyCode = isset($r['TRTY_CODE']) ? $r['TRTY_CODE'] : '';
                        $trtyDesc = isset($r['TRTY_DESC']) ? htmlspecialchars($r['TRTY_DESC']) : '';
                        
                        $displayDate = date('d-M-y', strtotime($tDateStr));
                        $displayDesc = "[{$trtyCode}] {$trtyDesc}";
                        
                        // Cetak Baris Transaksi
                        echo "<tr>
                                <td>{$displayDate}</td>
                                <td>{$locGroup}</td>
                                <td>" . htmlspecialchars($locName) . "</td>
                                <td>" . htmlspecialchars($itemCode) . "</td>
                                <td>" . htmlspecialchars($itemName) . "</td>
                                <td>{$docNo}</td>
                                <td>{$displayDesc}</td>
                                <td style='color:green;'>{$inQty}</td>
                                <td style='color:red;'>{$outQty}</td>
                                <td>{$runningBalance}</td>
                              </tr>";
                    }
                }
            }
        } else {
            echo "<tr><td colspan='10' style='text-align:center;'>No Data Available</td></tr>";
        }
        ?>
    </tbody>
</table>
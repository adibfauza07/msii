<?php
require_once __DIR__ . '/config/database.php';

// PENGATURAN ANTI TIMEOUT & MEMORY
set_time_limit(0); 
ini_set('memory_limit', '1024M'); 

// 1. Tangkap Parameter Tanggal
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Format SQL Server YYYYMMDD
$startFormatted = date('Ymd', strtotime($startDate));
$endFormatted   = date('Ymd', strtotime($endDate));
$periodeStr     = date('01 - M - Y', strtotime($startDate)) . " ~ " . date('d - M - Y', strtotime($endDate)); 

// 2. Eksekusi Stored Procedure
$sql = "EXEC SP_SOPANALISYS2 @START_DATE = ?, @END_DATE = ?";
$options = array("QueryTimeout" => 300);
$stmt = sqlsrv_query($conn, $sql, array($startFormatted, $endFormatted), $options);

if ($stmt === false) {
    die("<pre>Error SP_SOPANALISYS2: \n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

// 3. Proses Grouping Data berdasarkan LOC_GROUP
$groupedData = [];
$usRateMaster = 0;

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Kalkulasi Stok untuk keperluan filter
    $begBal = isset($row['TAG1_QTY']) ? (float)$row['TAG1_QTY'] : 0;
    $inQty  = isset($row['TIN']) ? (float)$row['TIN'] : 0;
    $outQty = isset($row['TOUT']) ? (float)$row['TOUT'] : 0;
    $endBal = $begBal + $inQty - $outQty;
    $stkOpn = isset($row['TAG2_QTY']) ? (float)$row['TAG2_QTY'] : 0;
    
    // --- FILTER SUPPRESS BLANK ROW ---
    // Jika semua aktivitas 0, lewati baris ini (jangan dicetak)
    if (round($begBal, 2) == 0 && round($inQty, 2) == 0 && round($outQty, 2) == 0 && round($endBal, 2) == 0 && round($stkOpn, 2) == 0) {
        continue; 
    }

    // Ambil US Rate dari baris pertama untuk Header
    if ($usRateMaster == 0 && isset($row['USRATE'])) {
        $usRateMaster = (float)$row['USRATE'];
    }

    $locGroup = isset($row['LOC_GROUP']) ? trim($row['LOC_GROUP']) : '';
    $groupName = isset($row['GROUP_NAME']) ? trim($row['GROUP_NAME']) : '';
    $groupKey = ($locGroup != '') ? ($locGroup . "-" . $groupName) : "UNASSIGNED";

    if (!isset($groupedData[$groupKey])) {
        $groupedData[$groupKey] = [];
    }
    $groupedData[$groupKey][] = $row;
}

$companyName = (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2') ? "P.T. ISHIKAWA INDONESIA" : "P.T. ISHIKAWA INDONESIA";

// Helper Penampil Angka (Kosongkan jika 0, beri tanda "-" khusus mata uang USD)
function formatVal($val, $desimal = 2, $isCurrency = false) {
    if (round($val, $desimal) == 0) {
        return $isCurrency ? "-" : ""; 
    }
    return number_format($val, $desimal);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Stock Opname Analisys</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; margin: 20px; background: #f0f0f0; }
        .page-container { background: #fff; width: 297mm; min-height: 210mm; margin: 0 auto; padding: 20px; box-sizing: border-box; }
        
        /* Header Box */
        .top-header { width: 100%; margin-bottom: 5px; }
        .top-header td { vertical-align: top; }
        .title-box { text-align: center; }
        .title-box h2 { margin: 0; font-size: 18px; font-weight: normal; letter-spacing: 1px; }
        
        .ttd-table { border-collapse: collapse; width: 100%; text-align: center; }
        .ttd-table th, .ttd-table td { border: 1px solid #000; font-weight: normal; }
        .ttd-table th { padding: 2px; }
        .ttd-table td { height: 40px; }

        .info-rate { font-style: italic; font-weight: bold; font-size: 11px; margin-top: 5px; }
        .info-date { font-size: 10px; }

        /* Main Table */
        .data-table { width: 100%; border-collapse: collapse; font-size: 10px; table-layout: fixed; margin-top: 10px; }
        .data-table th, .data-table td { padding: 3px 2px; word-wrap: break-word; overflow: hidden; vertical-align: top; }
        
        /* Lebar Kolom Sesuai Screenshot */
        .data-table th:nth-child(1) { width: 7%; }  /* Code */
        .data-table th:nth-child(2) { width: 10%; } /* No */
        .data-table th:nth-child(3) { width: 22%; } /* Name */
        .data-table th:nth-child(4) { width: 8%; }  /* Cost */
        .data-table th:nth-child(5) { width: 7%; }  /* Beg Bal */
        .data-table th:nth-child(6) { width: 7%; }  /* Tot In */
        .data-table th:nth-child(7) { width: 7%; }  /* Tot Out */
        .data-table th:nth-child(8) { width: 7%; }  /* End Bal */
        .data-table th:nth-child(9) { width: 7%; }  /* Stock Opname */
        .data-table th:nth-child(10) { width: 8%; } /* Var Qty */
        .data-table th:nth-child(11) { width: 10%; }/* US$ */

        /* Border Header Putus-putus Atas Bawah */
        .data-table thead th {
            border-top: 1px solid #000;
            border-bottom: 1px dashed #000;
            font-weight: normal;
            text-align: right;
            vertical-align: bottom;
            padding-bottom: 5px;
        }
        .data-table thead th.txt-left { text-align: left; }

        .group-header { font-weight: bold; font-size: 11px; padding-top: 15px !important; }
        
        .subtotal-row td { font-weight: bold; padding-top: 15px !important; padding-bottom: 15px !important; }
        .grand-total-row td { font-weight: bold; font-size: 12px; padding-top: 5px !important; }

        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        .text-left { text-align: left !important; }
        
        .no-print { text-align: center; margin-bottom: 20px; }
        .btn { padding: 8px 15px; cursor: pointer; border: 1px solid #ccc; background: #fff; font-weight: bold; margin: 0 5px; }
        
        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .no-print { display: none; }
            .page-container { width: 100%; padding: 0; box-shadow: none; border: none; }
            @page { size: landscape; margin: 10mm; }
            thead { display: table-header-group; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn" onclick="window.close()">Tutup</button>
        <button class="btn" onclick="window.print()">Print Report (Landscape)</button>
    </div>

    <div class="page-container">
        <table class="top-header">
            <tr>
                <td style="width: 30%;">
                    <div style="font-weight: bold; font-size: 14px;"><?php echo htmlspecialchars($companyName); ?></div>
                    <div>Commercial Business</div>
                    
                    <div class="info-rate">1 US$ = <?php echo number_format($usRateMaster, 2); ?></div>
                    <div class="info-date">Print Date : <?php echo date('d-M-Y H:i:s'); ?></div>
                </td>
                <td style="width: 40%;" class="title-box">
                    <h2>STOCK OPNAME ANALISYS</h2>
                    <div>Periode: <?php echo $periodeStr; ?></div>
                </td>
                <td style="width: 30%;">
                    <table class="ttd-table">
                        <tr>
                            <th style="width: 33%;">Approved</th>
                            <th style="width: 33%;">Checked</th>
                            <th style="width: 33%;">Prepared</th>
                        </tr>
                        <tr><td></td><td></td><td></td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <?php if (empty($groupedData)): ?>
            <div style="text-align: center; padding: 50px; font-weight: bold; border-top: 1px solid #000;">
                Tidak ada data Stock Analysis Opname pada periode tersebut.
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="txt-left">Code</th>
                        <th class="txt-left">No</th>
                        <th class="txt-left">Name</th>
                        <th>Item<br>Cost</th>
                        <th>Beg.<br>Balance</th>
                        <th>Total<br>In</th>
                        <th>Total<br>Out</th>
                        <th>End.<br>Balance</th>
                        <th>Stock.<br>Opname</th>
                        <th>Variance<br>Qty</th>
                        <th>US$</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grandTotalUsd = 0;

                    foreach ($groupedData as $groupName => $items): 
                        $subTotalUsd = 0;
                    ?>
                        <tr>
                            <td colspan="11" class="group-header"><?php echo htmlspecialchars($groupName); ?></td>
                        </tr>

                        <?php foreach ($items as $r): 
                            $begBal = isset($r['TAG1_QTY']) ? (float)$r['TAG1_QTY'] : 0;
                            $inQty  = isset($r['TIN']) ? (float)$r['TIN'] : 0;
                            $outQty = isset($r['TOUT']) ? (float)$r['TOUT'] : 0;
                            $endBal = $begBal + $inQty - $outQty;
                            
                            $stkOpn = isset($r['TAG2_QTY']) ? (float)$r['TAG2_QTY'] : 0;
                            $varQty = $stkOpn - $endBal;

                            $cost    = isset($r['ITEM_COST']) ? (float)$r['ITEM_COST'] : 0;
                            $cur     = isset($r['ITEM_CUR']) ? trim($r['ITEM_CUR']) : '';
                            $rateUnit= isset($r['RATEUNIT']) ? (float)$r['RATEUNIT'] : 1;

                            $usAmount = 0;
                            if ($varQty != 0 && $cost > 0) {
                                $pengali = ($rateUnit > 0) ? $rateUnit : 1;
                                $usAmount = $varQty * $cost * $pengali;
                            }

                            $subTotalUsd += $usAmount;
                            $grandTotalUsd += $usAmount;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['ITEM_CODE']); ?></td>
                                <td><?php echo htmlspecialchars(isset($r['ITEM_NO']) ? $r['ITEM_NO'] : ''); ?></td>
                                <td><?php echo htmlspecialchars($r['ITEM_NAME']); ?></td>
                                <td class="text-right">
                                    <?php 
                                        if ($cost > 0) echo number_format($cost, 2) . " " . htmlspecialchars($cur); 
                                        else echo "";
                                    ?>
                                </td>
                                <td class="text-right"><?php echo formatVal($begBal); ?></td>
                                <td class="text-right"><?php echo formatVal($inQty); ?></td>
                                <td class="text-right"><?php echo formatVal($outQty); ?></td>
                                <td class="text-right"><?php echo formatVal($endBal); ?></td>
                                <td class="text-right"><?php echo formatVal($stkOpn); ?></td>
                                <td class="text-right"><?php echo formatVal($varQty); ?></td>
                                <td class="text-right"><?php echo formatVal($usAmount, 2, true); ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <tr class="subtotal-row">
                            <td colspan="3" class="text-center">SUBTOTAL <?php echo htmlspecialchars($groupName); ?></td>
                            <td colspan="7"></td>
                            <td class="text-right"><?php echo number_format($subTotalUsd, 2); ?></td>
                        </tr>

                    <?php endforeach; ?>

                    <tr class="grand-total-row">
                        <td colspan="10"></td>
                        <td class="text-right"><?php echo number_format($grandTotalUsd, 2); ?></td>
                    </tr>

                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
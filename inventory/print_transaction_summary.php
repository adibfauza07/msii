<?php
require_once __DIR__ . '/../config/database_p1.php';

// 1. Tangkap Parameter Tanggal
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Format ke YYYYMMDD sesuai permintaan Stored Procedure
$startFormatted = date('Ymd', strtotime($startDate));
$endFormatted   = date('Ymd', strtotime($endDate));

// String untuk Header Laporan
$dateHeaderStr = $startFormatted . " ~ " . $endFormatted;

// 2. Eksekusi Stored Procedure
$sql = "EXEC SP_TRANSUMMARY2 @STARTDATE = ?, @ENDDATE = ?";
$stmt = sqlsrv_query($conn, $sql, array($startFormatted, $endFormatted));

if ($stmt === false) {
    die("<pre>Error mengeksekusi SP_TRANSUMMARY2:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

// 3. Proses Grouping Data
// Hierarki: TRTY_CODE -> ITTY_CODE -> Items
$groupedData = [];

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $trtyKey = $row['TRTY_CODE'] . " " . $row['TRTY_DESC'];
    $ittyKey = $row['ITTY_CODE'] . " " . $row['ITTY_DESC'];

    if (!isset($groupedData[$trtyKey])) {
        $groupedData[$trtyKey] = [];
    }
    if (!isset($groupedData[$trtyKey][$ittyKey])) {
        $groupedData[$trtyKey][$ittyKey] = [];
    }
    
    $groupedData[$trtyKey][$ittyKey][] = $row;
}

$companyName = (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2') ? "PT. ISHIKAWA INDONESIA" : "P.T ISHIKAWA INDONESIA";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Transaction Summary</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; margin: 20px; background: #f0f0f0; }
        .page-container { background: #fff; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 20px; box-sizing: border-box; }
        
        /* Header & TTD Styling */
        .top-header { width: 100%; margin-bottom: 20px; }
        .title-box { text-align: center; vertical-align: top; }
        .title-box h2 { margin: 0; font-size: 18px; font-weight: normal; }
        .title-box p { margin: 2px 0 0 0; font-size: 11px; }
        
        .ttd-table { border-collapse: collapse; width: 100%; text-align: center; }
        .ttd-table th, .ttd-table td { border: 1px solid #000; font-weight: normal; }
        .ttd-table th { padding: 3px; }
        .ttd-table td { height: 50px; }

        /* Main Data Table Styling */
        .data-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .data-table thead th { 
            border-top: 2px solid #000; 
            border-bottom: 1px solid #000; 
            padding: 8px 4px; 
            text-align: left; 
            font-weight: normal; 
        }
        .data-table tbody td { padding: 3px 4px; vertical-align: top; }
        
        /* Grouping Headers */
        .trty-header { font-weight: bold; font-size: 12px; padding-top: 15px !important; }
        .itty-header { font-style: italic; color: #333; padding-left: 15px !important; }
        .item-cell { padding-left: 20px !important; display: flex; gap: 10px; }
        .item-code { width: 80px; }
        
        /* Subtotals */
        .subtotal-row td { font-weight: bold; padding-top: 8px; padding-bottom: 15px; }
        
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
        <button class="btn" onclick="window.close()">Tutup</button>
        <button class="btn" onclick="window.print()">Print Report</button>
    </div>

    <div class="page-container">
        <table class="top-header">
            <tr>
                <td style="width: 30%; vertical-align: top;">
                    <div style="font-weight: bold; font-size: 14px;"><?php echo $companyName; ?></div>
                    <div>Commercial Business</div>
                </td>
                <td style="width: 40%;" class="title-box">
                    <h2>TRANSACTION SUMMARY</h2>
                    <p>Date : <?php echo $dateHeaderStr; ?></p>
                </td>
                <td style="width: 30%; vertical-align: top;">
                    <table class="ttd-table">
                        <tr>
                            <th style="width:33%;">Approved</th>
                            <th style="width:33%;">Checked</th>
                            <th style="width:33%;">Prepared</th>
                        </tr>
                        <tr>
                            <td></td><td></td><td></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <?php if (empty($groupedData)): ?>
            <div style="text-align: center; padding: 40px; border-top: 2px solid #000; border-bottom: 1px solid #000; font-weight: bold;">
                Tidak ada data transaksi pada rentang tanggal tersebut.
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 60%; font-size: 13px; letter-spacing: 2px;">I T E M S</th>
                        <th style="width: 25%; text-align: right;">Qty</th>
                        <th style="width: 15%; text-align: center;">Unit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($groupedData as $trtyName => $ittys): 
                        $trtySubtotal = 0;
                    ?>
                        <tr>
                            <td colspan="3" class="trty-header"><?php echo htmlspecialchars($trtyName); ?></td>
                        </tr>

                        <?php foreach ($ittys as $ittyName => $items): ?>
                            <tr>
                                <td colspan="3" class="itty-header"><?php echo htmlspecialchars($ittyName); ?></td>
                            </tr>

                            <?php foreach ($items as $r): 
                                $qty = isset($r['TQTY']) ? (float)$r['TQTY'] : 0;
                                $trtySubtotal += $qty;
                            ?>
                                <tr>
                                    <td class="item-cell">
                                        <div class="item-code"><?php echo htmlspecialchars($r['ITEM_CODE']); ?></div>
                                        <div><?php echo htmlspecialchars($r['ITEM_NAME']); ?></div>
                                    </td>
                                    <td style="text-align: right;"><?php echo number_format($qty, 2); ?></td>
                                    <td style="text-align: center;"><?php echo htmlspecialchars($r['ITEM_UNIT']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        
                        <tr class="subtotal-row">
                            <td></td>
                            <td style="text-align: right; font-size: 12px;"><?php echo number_format($trtySubtotal, 2); ?></td>
                            <td></td>
                        </tr>

                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
require_once __DIR__ . '/../config/database_p1.php';

// PENGATURAN ANTI TIMEOUT (Karena data Daily Stock sangat banyak)
set_time_limit(0); 
ini_set('memory_limit', '512M'); 

// 1. Tangkap Parameter Tanggal (As Per)
$asPerDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$asPerFormatted = date('Ymd', strtotime($asPerDate));
$asPerDisplay   = date('d - M - Y', strtotime($asPerDate)); // Format: 01 - Juni - 2026

// 2. Eksekusi Stored Procedure
$sql = "EXEC RPT_DAILYSTOCK2 @ASPER = ?";
$options = array("QueryTimeout" => 300); // Tunggu sampai 5 menit jika data besar
$stmt = sqlsrv_query($conn, $sql, array($asPerFormatted), $options);

if ($stmt === false) {
    die("<pre>Error SP RPT_DAILYSTOCK2: \n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

// 3. Grouping Data berdasarkan Lokasi (LOC_NAME)
$groupedData = [];

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $locName = isset($row['LOC_NAME']) ? trim($row['LOC_NAME']) : "UNASSIGNED";
    if ($locName == '') $locName = "UNASSIGNED";

    if (!isset($groupedData[$locName])) {
        $groupedData[$locName] = [];
    }
    
    $groupedData[$locName][] = $row;
}

// Nama Perusahaan
$companyName = (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2') ? "PT. IMC TEKNO INDONESIA" : "PT. IMC TEKNO INDONESIA";

// Fungsi Bantuan: Jika 0 maka tampilkan Strip (-)
function formatAngkaAtauStrip($angka, $desimal = 2) {
    if (round($angka, $desimal) == 0) {
        return "-";
    }
    return number_format($angka, $desimal);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daily Stock Report - <?php echo $asPerDisplay; ?></title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; margin: 20px; background: #f0f0f0; }
        .page-container { background: #fff; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 20px; box-sizing: border-box; }
        
        .header-table { width: 100%; margin-bottom: 5px; }
        .header-table h2 { text-align: center; margin: 0 0 5px 0; font-size: 18px; font-weight: normal; }
        
        /* Table Utama */
        .data-table { width: 100%; border-collapse: collapse; font-size: 10px; table-layout: fixed; }
        .data-table th, .data-table td { padding: 3px 2px; word-wrap: break-word; overflow: hidden; vertical-align: top; }
        
        /* Lebar Kolom */
        .data-table th:nth-child(1) { width: 45%; } /* ITEMS */
        .data-table th:nth-child(2) { width: 10%; } /* Beg.Stock */
        .data-table th:nth-child(3) { width: 8%; }  /* In */
        .data-table th:nth-child(4) { width: 8%; }  /* Out */
        .data-table th:nth-child(5) { width: 10%; } /* End.Stock */
        .data-table th:nth-child(6) { width: 10%; } /* Cost */
        .data-table th:nth-child(7) { width: 9%; }  /* USD Amount */

        /* Border Header 2 Lapis ala Crystal Report */
        .data-table thead th { 
            border-top: 2px solid #000; 
            border-bottom: 2px solid #000; 
            text-align: right; 
            font-weight: normal; 
            padding: 8px 4px;
        }
        .data-table thead th:first-child { text-align: left; letter-spacing: 2px; }

        /* Grouping Lokasi */
        .loc-header { font-weight: bold; font-size: 11px; padding-top: 15px !important; padding-bottom: 5px !important; text-transform: uppercase; }
        
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        .text-left { text-align: left !important; }
        
        .no-print { text-align: center; margin-bottom: 20px; }
        .btn { padding: 8px 15px; cursor: pointer; border: 1px solid #ccc; background: #fff; font-weight: bold; margin: 0 5px; }
        
        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .no-print { display: none; }
            .page-container { width: 100%; padding: 0; box-shadow: none; }
            @page { size: portrait; margin: 10mm; }
            thead { display: table-header-group; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn" onclick="window.close()">Tutup</button>
        <button class="btn" onclick="window.print()">Print Report</button>
    </div>

    <div class="page-container">
        <table class="header-table">
            <tr>
                <td style="width: 30%; font-size: 13px; vertical-align: top; font-weight: normal;">
                    <?php echo htmlspecialchars($companyName); ?>
                </td>
                <td style="width: 40%; text-align: center; vertical-align: top;">
                    <h2>Daily Stock Report</h2>
                    <div style="font-size: 12px;">as per: <?php echo $asPerDisplay; ?></div>
                </td>
                <td style="width: 30%; text-align: right; vertical-align: bottom; font-size: 10px;">
                    Print Date: <?php echo date('d-M-Y H:i:s'); ?>
                </td>
            </tr>
        </table>

        <?php if (empty($groupedData)): ?>
            <div style="text-align: center; padding: 40px; border-top: 2px solid #000; border-bottom: 2px solid #000; font-weight: bold;">
                Tidak ada data stok pada tanggal yang dipilih.
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>I T E M S</th>
                        <th>Beg.Stock</th>
                        <th>In</th>
                        <th>Out</th>
                        <th>End.Stock</th>
                        <th>Cost</th>
                        <th>USD Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groupedData as $locName => $items): ?>
                        
                        <tr>
                            <td colspan="7" class="loc-header"><?php echo htmlspecialchars($locName); ?></td>
                        </tr>

                        <?php foreach ($items as $r): 
                            // Hitung Stok
                            $begStock = isset($r['BAL_QTY']) ? (float)$r['BAL_QTY'] : 0;
                            $inQty    = isset($r['TIN']) ? (float)$r['TIN'] : 0;
                            $outQty   = isset($r['TOUT']) ? abs((float)$r['TOUT']) : 0; // Di-abs() karena di SP nilainya minus
                            $endStock = $begStock + $inQty - $outQty; // Rumus End Stock

                            // Hitung Cost dan Valuasi USD
                            $cost   = isset($r['ITEM_COST']) ? (float)$r['ITEM_COST'] : 0;
                            $cur    = isset($r['ITEM_CUR']) ? trim($r['ITEM_CUR']) : '';
                            $crate  = isset($r['CURR_CRATE']) ? (float)$r['CURR_CRATE'] : 0;
                            $usrate = isset($r['USDRATE']) ? (float)$r['USDRATE'] : 0;
                            
                            $usdAmount = 0;
                            if ($endStock != 0 && $cost > 0) {
                                if (strtoupper($cur) == 'USD') {
                                    $usdAmount = $endStock * $cost;
                                } else {
                                    if ($usrate > 0) {
                                        $usdAmount = ($endStock * $cost * $crate) / $usrate;
                                    }
                                }
                            }
                        ?>
                            <tr>
                                <td class="text-left">
                                    <?php echo htmlspecialchars($r['ITEM_CODE']) . " &nbsp;&nbsp; " . htmlspecialchars($r['ITEM_NAME']); ?>
                                </td>
                                <td class="text-right"><?php echo formatAngkaAtauStrip($begStock); ?></td>
                                <td class="text-right"><?php echo formatAngkaAtauStrip($inQty); ?></td>
                                <td class="text-right"><?php echo formatAngkaAtauStrip($outQty); ?></td>
                                <td class="text-right"><?php echo formatAngkaAtauStrip($endStock); ?></td>
                                <td class="text-right">
                                    <?php 
                                    if ($cost > 0) {
                                        echo number_format($cost, 2) . " " . htmlspecialchars($cur);
                                    } else {
                                        echo "-";
                                    }
                                    ?>
                                </td>
                                <td class="text-right"><?php echo formatAngkaAtauStrip($usdAmount); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
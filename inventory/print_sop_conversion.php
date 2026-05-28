<?php
require_once __DIR__ . '/../config/database_p1.php';

$sopId = isset($_GET['sop_id']) ? (int)$_GET['sop_id'] : 0;
if ($sopId == 0) die("Error: ID SOP tidak ditemukan.");

// 1. Ambil Header SOP (Referensi & Tanggal)
$sqlHeader = "SELECT SOP_REF, SOP_SDATE FROM dbo.SOP WHERE SOP_ID = ?";
$stmtH = sqlsrv_query($conn, $sqlHeader, array($sopId));
$header = ($stmtH) ? sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC) : null;

$sopRef  = ($header && isset($header['SOP_REF'])) ? $header['SOP_REF'] : "SOP ID: " . $sopId;
$sopDate = ($header && isset($header['SOP_SDATE']) && $header['SOP_SDATE'] instanceof DateTime) ? $header['SOP_SDATE']->format('d-M-Y') : "-";

// 2. Eksekusi Stored Procedure
$sql = "EXEC RPT_TAG_MATERIAL @SOP_ID = ?";
$stmt = sqlsrv_query($conn, $sql, array($sopId));
if ($stmt === false) die(print_r(sqlsrv_errors(), true));

// 3. Grouping Data: Location -> Parent Item -> Child Items
$groupedData = [];
$companyName = (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2') ? "PT. IMC TEKNO INDONESIA PLANT 2" : "PT. IMC TEKNO INDONESIA PLANT 1";

if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $locName = $row['LOC_NAME'] ? $row['LOC_NAME'] : "UNKNOWN LOCATION";
        $parentKey = ($row['PARENT_CODE'] ? $row['PARENT_CODE'] : '') . " " . ($row['PARENT_NAME'] ? $row['PARENT_NAME'] : '');
        
        if (!isset($groupedData[$locName])) {
            $groupedData[$locName] = [];
        }
        if (!isset($groupedData[$locName][$parentKey])) {
            $groupedData[$locName][$parentKey] = [
                'PARENT_QTY' => $row['PARENT_QTY'] ? $row['PARENT_QTY'] : 0,
                'PARENT_UNIT' => $row['PARENT_UNIT'] ? $row['PARENT_UNIT'] : '',
                'CHILDREN' => []
            ];
        }
        
        $groupedData[$locName][$parentKey]['CHILDREN'][] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SOP Conversion - <?php echo htmlspecialchars($sopRef); ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; background: #f0f0f0; }
        .page-container { background: #fff; width: 297mm; min-height: 210mm; margin: 0 auto; padding: 20px; box-sizing: border-box; }
        .header { text-align: center; margin-bottom: 20px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th, .data-table td { padding: 4px 5px; vertical-align: top; }
        .data-table th { background: #fff; border-top: 2px solid #000; border-bottom: 2px solid #000; text-align: left; }
        
        .loc-header { font-weight: bold; font-size: 12px; padding-top: 15px !important; }
        .parent-row { font-weight: bold; border-top: 1px dashed #ccc; }
        .child-row td { padding-top: 2px; padding-bottom: 2px; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .no-print { text-align: center; margin-bottom: 20px; }
        .btn { padding: 8px 15px; cursor: pointer; border: 1px solid #ccc; background: #fff; font-weight: bold; margin: 0 5px; }
        
        @media print { .no-print { display: none; } .page-container { width: 100%; padding: 0; box-shadow: none; } @page { size: landscape; margin: 10mm; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn" onclick="window.close()">Tutup</button>
        <button class="btn" onclick="window.print()">Print (Landscape)</button>
        <a href="export_sop_conversion.php?sop_id=<?php echo $sopId; ?>" class="btn" style="text-decoration: none; color: black;">Export to Excel</a>
    </div>

    <div class="page-container">
        <table style="width: 100%; margin-bottom: 10px;">
            <tr>
                <td style="width: 33%; font-weight: bold; font-size: 12px;"><?php echo htmlspecialchars($companyName); ?><br><span style="font-weight: normal; font-size: 10px;">PPIC</span></td>
                <td style="width: 34%; text-align: center;">
                    <h2 style="margin: 0; font-size: 16px;">KONVERSI STOCK OPNAME</h2>
                    <div style="font-weight: bold; font-size: 12px;">S.O.P : <?php echo htmlspecialchars($sopDate); ?></div>
                </td>
                <td style="width: 33%; text-align: right; font-size: 10px; vertical-align: bottom;">
                    Print Date: <?php echo date('d-M-Y H:i:s'); ?>
                </td>
            </tr>
        </table>

        <?php if (empty($groupedData)): ?>
            <p style="text-align:center; padding: 20px; border: 1px solid #000; font-weight: bold;">Tidak ada data Konversi Material untuk dokumen SOP ini.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Materials</th>
                        <th style="width: 30%;">Part</th>
                        <th style="width: 10%; text-align: right;">Tag Qty</th>
                        <th style="width: 15%; text-align: right;">Net-Weight</th>
                        <th style="width: 10%; text-align: right;">Child Qty.(kg)</th>
                        <th style="width: 10%; text-align: right;">Price</th>
                        <th style="width: 10%; text-align: right;">US$ Amount LV</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groupedData as $loc => $parents): ?>
                        <tr>
                            <td colspan="7" class="loc-header"><?php echo strtoupper($loc); ?></td>
                        </tr>

                        <?php foreach ($parents as $parentName => $parentData): ?>
                            <tr class="parent-row">
                                <td></td>
                                <td><?php echo htmlspecialchars($parentName); ?></td>
                                <td class="text-right">
                                    <?php echo number_format($parentData['PARENT_QTY'], 2); ?> 
                                    <span style="font-weight: normal; font-size: 9px;"><?php echo htmlspecialchars($parentData['PARENT_UNIT']); ?></span>
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>

                            <?php 
                            $totalUsd = 0;
                            $totalChildQty = 0;
                            foreach ($parentData['CHILDREN'] as $child): 
                                $usd = ($child['CHILD_QTY'] * $child['PRICE']) * ($child['CURR_VRATE'] / max($child['USRATE'], 1));
                                $totalUsd += $usd;
                                $totalChildQty += $child['CHILD_QTY'];
                            ?>
                                <tr class="child-row">
                                    <td style="padding-left: 10px; color: #444;"><?php echo htmlspecialchars($child['CHILD_CODE'] ? $child['CHILD_CODE'] : ''); ?><br><span style="font-size: 9px;"><?php echo htmlspecialchars($child['CHILD_NAME'] ? $child['CHILD_NAME'] : ''); ?></span></td>
                                    <td></td> <td></td>
                                    <td class="text-right">
                                        <?php echo number_format($child['BOM_QTY'] ? $child['BOM_QTY'] : 0, 5); ?> 
                                        <span style="font-size: 9px; text-transform: uppercase;"><?php echo htmlspecialchars($child['BOM_UNIT'] ? $child['BOM_UNIT'] : ''); ?></span>
                                    </td>
                                    <td class="text-right"><?php echo number_format($child['CHILD_QTY'] ? $child['CHILD_QTY'] : 0, 2); ?></td>
                                    <td class="text-right"><?php echo number_format($child['PRICE'] ? $child['PRICE'] : 0, 5); ?> /</td>
                                    <td class="text-right"><?php echo number_format($usd, 2); ?> |</td>
                                </tr>
                            <?php endforeach; ?>

                            <tr>
                                <td></td><td></td><td></td><td></td>
                                <td class="text-right" style="font-weight: bold; border-top: 1px solid #ccc; padding-top: 2px;"><?php echo number_format($totalChildQty, 2); ?></td>
                                <td></td>
                                <td class="text-right" style="font-weight: bold; border-top: 1px solid #ccc; padding-top: 2px;"><?php echo number_format($totalUsd, 2); ?></td>
                            </tr>

                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
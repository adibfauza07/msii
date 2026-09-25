<?php
require_once __DIR__ . '/../config/database_p1.php';

// Ambil ID SOP dari parameter URL
$sopId = isset($_GET['sop_id']) ? (int)$_GET['sop_id'] : 0;
if ($sopId == 0) die("Error: ID Dokumen SOP tidak ditemukan.");

// Eksekusi Stored Procedure
$sql = "EXEC TAG_BY_ITTY_USAMT @SOP_ID = ?";
$stmt = sqlsrv_query($conn, $sql, array($sopId));

if ($stmt === false) {
    die("<div style='color:red; padding:20px; font-family:sans-serif;'><b>Error Eksekusi SP:</b><br>" . print_r(sqlsrv_errors(), true) . "</div>");
}

// Persiapan Variabel Grouping
$groupedData = [];
$sopRef = "-";
$sopDate = "-";
$grandTotalQty = 0;
$grandTotalUSD = 0;

$companyName = (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2') ? "PT. IMC TEKNO INDONESIA PLANT 2" : "PT. IMC TEKNO INDONESIA PLANT 1";

// Memproses dan mengelompokkan data berdasarkan ITTY_DESC (Item Type)
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Ambil Header Dokumen dari baris pertama
    if ($sopRef === "-") {
        $sopRef = $row['SOP_REF'] ? $row['SOP_REF'] : "-";
        $sopDate = ($row['SOP_SDATE'] instanceof DateTime) ? $row['SOP_SDATE']->format('d-M-Y') : $row['SOP_SDATE'];
    }
    
    // Kelompokkan berdasarkan Tipe (ITTY_DESC)
    $ittyDesc = $row['ITTY_DESC'] ? trim($row['ITTY_DESC']) : "TANPA TIPE";
    $groupedData[$ittyDesc][] = $row;
    
    // Hitung Grand Total
    $grandTotalQty += (float)$row['STQTY'];
    $grandTotalUSD += (float)$row['USAMOUNT'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tag By Type - <?php echo htmlspecialchars($sopRef); ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; background: #f0f0f0; }
        .page-container { background: #fff; width: 297mm; min-height: 210mm; margin: 0 auto; padding: 20px; box-sizing: border-box; }
        .header { text-align: center; margin-bottom: 20px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 5px; }
        .data-table th { background: #eee; text-align: center; }
        .type-header { background: #d9edf7; font-weight: bold; font-size: 12px; }
        .subtotal-row { background: #fff3cd; font-weight: bold; }
        .grandtotal-row { background: #d4edda; font-weight: bold; font-size: 12px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .no-print { text-align: center; margin-bottom: 20px; }
        .btn { padding: 8px 15px; cursor: pointer; border: 1px solid #ccc; background: #fff; font-weight: bold; }
        @media print { .no-print { display: none; } .page-container { width: 100%; padding: 0; } @page { size: landscape; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn" onclick="window.close()">Tutup</button> 
        <button class="btn" onclick="window.print()">Print Laporan (Landscape)</button>
        <!-- Tombol Export Excel Baru -->
        <a href="export_tag_by_type.php?sop_id=<?php echo $sopId; ?>" class="btn" style="text-decoration: none; color: black;">Export Excel</a>
    </div>
    
    <div class="page-container">
        <div class="header">
            <h3><?php echo htmlspecialchars($companyName); ?></h3>
            <h2>TAG BY TYPE REPORT</h2>
        </div>
        <p><b>SOP REF:</b> <?php echo htmlspecialchars($sopRef); ?> &nbsp;&nbsp;&nbsp; <b>SOP DATE:</b> <?php echo htmlspecialchars($sopDate); ?></p>

        <?php if (empty($groupedData)): ?>
            <p style="text-align:center; padding: 20px;">Tidak ada data ditemukan untuk dokumen SOP ini.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ITEM CODE</th>
                        <th>ITEM NAME</th>
                        <th>QTY OK1</th>
                        <th>QTY OK2</th>
                        <th>QTY HOLD</th>
                        <th>TOTAL QTY</th>
                        <th>UNIT</th>
                        <th>CURR</th>
                        <th>COST</th>
                        <th>USD RATE</th>
                        <th>USD AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groupedData as $type => $rows): ?>
                        <tr class="type-header"><td colspan="11">TYPE: <?php echo strtoupper(htmlspecialchars($type)); ?></td></tr>
                        
                        <?php 
                        $subQty = 0; $subUsd = 0;
                        foreach ($rows as $r): 
                            $subQty += (float)$r['STQTY'];
                            $subUsd += (float)$r['USAMOUNT'];
                        ?>
                            <tr>
                                <td><b style="color:blue;"><?php echo htmlspecialchars($r['ITEM_CODE']); ?></b></td>
                                <td><?php echo htmlspecialchars($r['ITEM_NAME']); ?></td>
                                <td class="text-right"><?php echo number_format($r['TOK1'], 0); ?></td>
                                <td class="text-right"><?php echo number_format($r['TOK2'], 0); ?></td>
                                <td class="text-right"><?php echo number_format($r['THOLD'], 0); ?></td>
                                <td class="text-right"><b><?php echo number_format($r['STQTY'], 2); ?></b></td>
                                <td class="text-center"><?php echo htmlspecialchars($r['ITEM_UNIT']); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($r['ITEM_CUR']); ?></td>
                                <td class="text-right"><?php echo number_format($r['ITEM_COST'], 4); ?></td>
                                <td class="text-right"><?php echo number_format($r['USRATE'], 0); ?></td>
                                <td class="text-right"><b><?php echo number_format($r['USAMOUNT'], 2); ?></b></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <tr class="subtotal-row">
                            <td colspan="5" class="text-right">SUBTOTAL <?php echo strtoupper(htmlspecialchars($type)); ?> :</td>
                            <td class="text-right"><?php echo number_format($subQty, 2); ?></td>
                            <td colspan="4"></td>
                            <td class="text-right"><?php echo number_format($subUsd, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="grandtotal-row">
                        <td colspan="5" class="text-right">GRAND TOTAL :</td>
                        <td class="text-right"><?php echo number_format($grandTotalQty, 2); ?></td>
                        <td colspan="4"></td>
                        <td class="text-right"><?php echo number_format($grandTotalUSD, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
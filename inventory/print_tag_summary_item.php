<?php
require_once __DIR__ . '/../config/database_p1.php';

$sopId = isset($_GET['sop_id']) ? (int)$_GET['sop_id'] : 0;
if ($sopId == 0) die("Error: ID SOP tidak ditemukan.");

// 1. AMBIL HEADER SOP LANGSUNG DARI TABEL
$sqlHeader = "SELECT SOP_REF, SOP_SDATE FROM dbo.SOP WHERE SOP_ID = ?";
$stmtH = sqlsrv_query($conn, $sqlHeader, array($sopId));
$header = ($stmtH) ? sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC) : null;

// Jika header tidak ketemu di tabel SOP, kita ambil referensi ID saja
$sopRef  = ($header && isset($header['SOP_REF'])) ? $header['SOP_REF'] : "SOP ID: " . $sopId;
$sopDate = ($header && isset($header['SOP_SDATE']) && $header['SOP_SDATE'] instanceof DateTime) ? $header['SOP_SDATE']->format('d-M-Y') : "-";

// 2. Eksekusi Stored Procedure untuk DATA DETAIL
$sql = "EXEC RPT_TAGSUM_BY_ITEM @SOP_ID = ?";
$stmt = sqlsrv_query($conn, $sql, array($sopId));
if ($stmt === false) die(print_r(sqlsrv_errors(), true));

// 3. Proses Grouping Data
$groupedData = [];
$companyName = (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2') ? "PT. IMC TEKNO INDONESIA PLANT 2" : "PT. IMC TEKNO INDONESIA PLANT 1";

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $locName = $row['LOC_NAME'] ? $row['LOC_NAME'] : "Unknown Location";
    $ittyDesc = $row['ITTY_DESC'] ? $row['ITTY_DESC'] : "Unknown Type";

    if (!isset($groupedData[$locName][$ittyDesc])) {
        $groupedData[$locName][$ittyDesc] = [];
    }
    $groupedData[$locName][$ittyDesc][] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tag Summary - <?php echo htmlspecialchars($sopRef); ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; background: #f0f0f0; }
        .page-container { background: #fff; width: 297mm; min-height: 210mm; margin: 0 auto; padding: 20px; box-sizing: border-box; }
        .header { text-align: center; margin-bottom: 20px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 5px; }
        .data-table th { background: #eee; text-align: center; }
        .loc-header { background: #d9edf7; font-weight: bold; }
        .type-header { background: #fff3cd; font-style: italic; }
        .text-right { text-align: right; }
        .no-print { text-align: center; margin-bottom: 20px; }
        .btn { padding: 8px 15px; cursor: pointer; border: 1px solid #ccc; background: #fff; font-weight: bold; }
        @media print { .no-print { display: none; } .page-container { width: 100%; padding: 0; } @page { size: landscape; } }
    </style>
</head>
<body>
    <div class="no-print"><button class="btn" onclick="window.close()">Tutup</button> <button class="btn" onclick="window.print()">Print (Landscape)</button>
<a href="export_tag_summary.php?sop_id=<?php echo $sopId; ?>" class="btn">Export to Excel</a></div>
    <div class="page-container">
        <div class="header">
            <h3><?php echo htmlspecialchars($companyName); ?></h3>
            <h2>TAG SUMMARY BY ITEM REPORT</h2>
        </div>
        <p><b>SOP REF:</b> <?php echo htmlspecialchars($sopRef); ?> &nbsp;&nbsp;&nbsp; <b>SOP DATE:</b> <?php echo htmlspecialchars($sopDate); ?></p>

        <?php if (empty($groupedData)): ?>
            <p style="text-align:center; padding: 20px;">Tidak ada data ditemukan untuk dokumen SOP ini.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr><th>ITEM CODE</th><th>ITEM NAME</th><th>QTY OK1</th><th>QTY OK2</th><th>QTY HOLD</th><th>TOTAL</th><th>CURR</th><th>PRICE</th><th>USD RATE</th><th>USD AMOUNT</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($groupedData as $loc => $types): ?>
                        <tr class="loc-header"><td colspan="10">LOCATION: <?php echo strtoupper($loc); ?></td></tr>
                        <?php foreach ($types as $type => $rows): ?>
                            <tr class="type-header"><td colspan="10">&nbsp;&nbsp;&nbsp;TYPE: <?php echo strtoupper($type); ?></td></tr>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r['ITEM_CODE'] ? $r['ITEM_CODE'] : ''); ?></td>
                                    <td><?php echo htmlspecialchars($r['ITEM_NAME'] ? $r['ITEM_NAME'] : ''); ?></td>
                                    <td class="text-right"><?php echo number_format($r['TOK1'] ? $r['TOK1'] : 0, 0); ?></td>
                                    <td class="text-right"><?php echo number_format($r['TOK2'] ? $r['TOK2'] : 0, 0); ?></td>
                                    <td class="text-right"><?php echo number_format($r['THOLD'] ? $r['THOLD'] : 0, 0); ?></td>
                                    <td class="text-right"><b><?php echo number_format($r['TQTY'] ? $r['TQTY'] : 0, 0); ?></b></td>
                                    <td class="text-center"><?php echo htmlspecialchars($r['ITEM_CUR'] ? $r['ITEM_CUR'] : ''); ?></td>
                                    <td class="text-right"><?php echo number_format($r['PRICE'] ? $r['PRICE'] : 0, 4); ?></td>
                                    <td class="text-right"><?php echo number_format($r['CURR_VRATE'] ? $r['CURR_VRATE'] : 0, 0); ?></td>
                                    <td class="text-right"><b><?php echo number_format($r['USDAMOUNT'] ? $r['USDAMOUNT'] : 0, 2); ?></b></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
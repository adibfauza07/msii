<?php
// Pastikan error reporting disesuaikan untuk production
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . "/../config/global.php";

// Inisialisasi variabel
$reportData = array();
$sop_date = '-';
$sop_rem = 'Tidak ada SOP aktif';
$error_msg = '';

if ($conn === false) {
    $error_msg = "Koneksi database gagal.";
} else {
    // 1. Dapatkan SOP Aktif
    $sqlSop = "SELECT TOP 1 SOP_ID, SOP_SDATE, SOP_REM FROM SOP WHERE SOP_FINISHED = 'F' ORDER BY SOP_SDATE DESC";
    $stmtSop = sqlsrv_query($conn, $sqlSop);

    $sop_id = null;
    if ($stmtSop !== false && sqlsrv_has_rows($stmtSop)) {
        $rowSop = sqlsrv_fetch_array($stmtSop, SQLSRV_FETCH_ASSOC);
        $sop_id = $rowSop['SOP_ID'];
        
        if ($rowSop['SOP_SDATE'] instanceof DateTime) {
            $sop_date = $rowSop['SOP_SDATE']->format('d-M-Y');
        } else {
            $sop_date = htmlspecialchars((string)$rowSop['SOP_SDATE'], ENT_QUOTES, 'UTF-8');
        }
        $sop_rem = htmlspecialchars((string)$rowSop['SOP_REM'], ENT_QUOTES, 'UTF-8');
    }
    
    if ($stmtSop !== false) sqlsrv_free_stmt($stmtSop);

    // 2. Query Detail dengan filter ITTY_CODE = '02'
    if ($sop_id !== null) {
        $sqlReport = "
            SELECT 
                LOC.LOC_CODE, 
                LOC.LOC_NAME, 
                ITEMS.ITEM_CODE, 
                ITEMS.ITEM_NAME, 
                ITEMS.ITEM_UNIT,
                ITEMS.ITTY_CODE,
                TAGS.TAG_NO,
                TAGS.TAG_QTY
            FROM SOP 
            INNER JOIN TAGS ON SOP.SOP_ID = TAGS.SOP_ID 
            INNER JOIN LOC ON TAGS.LOC_ID = LOC.LOC_ID 
            INNER JOIN ITEMS ON TAGS.ITEM_ID = ITEMS.ITEM_ID
            WHERE SOP.SOP_ID = ? AND ITEMS.ITTY_CODE = '02'
            ORDER BY 
                LOC.LOC_CODE ASC, 
                ITEMS.ITEM_CODE ASC,
                TAGS.TAG_NO ASC
        ";
        
        $params = array($sop_id);
        $stmtReport = sqlsrv_query($conn, $sqlReport, $params);

        if ($stmtReport !== false) {
            // 3. Susun data Hierarki: Lokasi -> Item -> Detail Tag
            while ($row = sqlsrv_fetch_array($stmtReport, SQLSRV_FETCH_ASSOC)) {
                $locCode = $row['LOC_CODE'];
                $itemCode = $row['ITEM_CODE'];
                
                if (!isset($reportData[$locCode])) {
                    $reportData[$locCode] = array(
                        'LOC_NAME' => $row['LOC_NAME'],
                        'ITEMS' => array()
                    );
                }
                
                if (!isset($reportData[$locCode]['ITEMS'][$itemCode])) {
                    $reportData[$locCode]['ITEMS'][$itemCode] = array(
                        'ITEM_NAME' => $row['ITEM_NAME'],
                        'ITEM_UNIT' => $row['ITEM_UNIT'],
                        'TAGS' => array(),
                        'SUBTOTAL' => 0
                    );
                }
                
                $qty = (float)$row['TAG_QTY'];
                $reportData[$locCode]['ITEMS'][$itemCode]['TAGS'][] = array(
                    'TAG_NO' => $row['TAG_NO'],
                    'TAG_QTY' => $qty
                );
                
                // Akumulasikan ke Subtotal
                $reportData[$locCode]['ITEMS'][$itemCode]['SUBTOTAL'] += $qty;
            }
            sqlsrv_free_stmt($stmtReport);
        } else {
            $error_msg = "Gagal memuat data laporan detail.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Detail Tag - ITTY 02</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        body { 
            background-color: #f4f7f6; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            font-size: 10px;
            line-height: 1.2;
        }
        .main-container { max-width: 1100px; margin: 15px auto; }
        .card { border: none; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 10px; }
        
        .table-compact th, .table-compact td {
            padding: 4px 6px !important;
            font-size: 10px;
            vertical-align: middle;
        }
        .group-header td { 
            background-color: #2c3e50; 
            color: #fff; 
            font-weight: bold; 
            padding: 4px 6px !important;
        }
        .subtotal-row td {
            background-color: #eef2f5;
            font-weight: bold;
            border-top: 1px solid #c2c9d1;
        }
        .table-responsive { max-height: 600px; overflow-y: auto; }
        thead th { position: sticky; top: 0; z-index: 1; background-color: #e9ecef; }
        
        h3.title-header { font-size: 14px; margin-bottom: 2px !important; }
        .info-box { font-size: 10px; }
        .btn-sm-compact { font-size: 10px; padding: 2px 8px; }
    </style>
</head>
<body>

<div class="container main-container">
    <div class="card p-2">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h3 class="fw-bold title-header"><i class="bi bi-list-columns-reverse me-1"></i>Detail Tag List (SOP) - ITTY 02</h3>
                <p class="text-muted mb-0">Laporan Detail Tag & Subtotal Khusus Item Category 02</p>
            </div>
            
            <div class="bg-light border border-secondary border-opacity-25 rounded px-2 py-1 text-end shadow-sm info-box">
                <div class="text-primary fw-bold">
                    <i class="bi bi-calendar-event me-1"></i> SOP Date: <?php echo $sop_date; ?>
                </div>
                <div class="text-secondary" style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo $sop_rem; ?>">
                    <i class="bi bi-journal-text me-1"></i> <?php echo $sop_rem; ?>
                </div>
            </div>
        </div>
        
        <div class="mt-2">
            <a href="dashboard_wh.php" class="btn btn-outline-secondary btn-sm-compact"><i class="bi bi-arrow-left"></i> Kembali</a>
            <button onclick="window.print()" class="btn btn-primary btn-sm-compact ms-2"><i class="bi bi-printer"></i> Cetak</button>
        </div>
    </div>

    <div class="card p-0 overflow-hidden">
        <?php if ($error_msg !== ''): ?>
            <div class="p-2 text-danger fw-bold"><i class="bi bi-exclamation-triangle"></i> <?php echo $error_msg; ?></div>
        <?php elseif (empty($reportData)): ?>
            <div class="p-3 text-muted text-center"><i class="bi bi-inbox fs-4 d-block mb-1"></i>Tidak ada data scan untuk Item Category 02 (ITTY 02) pada SOP ini.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 table-compact">
                    <thead class="table-light text-center">
                        <tr>
                            <th width="15%">Item Code</th>
                            <th width="40%">Item Name</th>
                            <th width="20%">Tag No</th>
                            <th width="15%">Qty</th>
                            <th width="10%">Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $locCode => $locData): ?>
                            
                            <tr class="group-header">
                                <td colspan="5">
                                    <i class="bi bi-geo-alt-fill me-1"></i> LOKASI: 
                                    <?php echo htmlspecialchars($locCode, ENT_QUOTES, 'UTF-8'); ?> - 
                                    <?php echo htmlspecialchars($locData['LOC_NAME'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                            </tr>
                            
                            <?php foreach ($locData['ITEMS'] as $itemCode => $itemData): ?>
                                <?php $tagCount = count($itemData['TAGS']); ?>
                                
                                <?php foreach ($itemData['TAGS'] as $index => $tag): ?>
                                    <tr>
                                        <?php if ($index === 0): ?>
                                            <td class="text-center fw-bold text-primary" rowspan="<?php echo $tagCount; ?>">
                                                <?php echo htmlspecialchars($itemCode, ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td rowspan="<?php echo $tagCount; ?>">
                                                <?php echo htmlspecialchars($itemData['ITEM_NAME'], ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                        <?php endif; ?>
                                        
                                        <td class="text-center font-monospace">
                                            <?php echo htmlspecialchars($tag['TAG_NO'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="text-center">
                                            <?php echo $tag['TAG_QTY']; ?>
                                        </td>
                                        
                                        <?php if ($index === 0): ?>
                                            <td class="text-center" rowspan="<?php echo $tagCount; ?>">
                                                <?php echo htmlspecialchars($itemData['ITEM_UNIT'], ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php if ($tagCount > 1): ?>
                                    <tr class="subtotal-row">
                                        <td colspan="3" class="text-end pe-3 text-secondary">
                                            Subtotal <?php echo htmlspecialchars($itemCode, ENT_QUOTES, 'UTF-8'); ?> :
                                        </td>
                                        <td class="text-center text-success">
                                            <?php echo $itemData['SUBTOTAL']; ?>
                                        </td>
                                        <td></td>
                                    </tr>
                                <?php endif; ?>
                                
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
<?php
// Cek apakah tombol Export Excel ditekan
$isExportExcel = (isset($_GET['export']) && $_GET['export'] == 'excel');

// Menangkap parameter bulan dari filter (Default: bulan saat ini)
$reqMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m'); 

// Set tanggal selalu ke tanggal 1 di bulan yang dipilih
$rawDate = $reqMonth . '-01'; 
$reqDate = str_replace('-', '', $rawDate); // Format YYYYMMDD untuk SP

if ($isExportExcel) {
    $filename = "Purchase_Report_" . date('Ymd') . ".xls";
    header("Content-type: application/vnd-ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
}

// Memuat konfigurasi global
require_once dirname(__DIR__) . "/config/global.php";

/**
 * ASUMSI: $conn adalah resource dari sqlsrv_connect() yang ada di global.php
 */

// Menangkap parameter supplier dari filter
$reqSupplier = isset($_GET['supplier']) ? trim($_GET['supplier']) : '';

// Eksekusi Stored Procedure menggunakan sqlsrv_query (Tanggal selalu tgl 1 sesuai input Cut-Off)
$sql = "EXEC [dbo].[RPT_PURCHASE_CR8] @DATE = ?";
$params = array(
    array($reqDate, SQLSRV_PARAM_IN)
);

// Jalankan query
$stmt = sqlsrv_query($conn, $sql, $params);

// Cek jika terjadi error pada query
if ($stmt === false) {
    die("<pre>Error Query:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

// ====================================================================================
// GROUPING DATA
// ====================================================================================
$reportData = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // 1. Filter berdasarkan Supplier (jika dipilih di dropdown)
    if (!empty($reqSupplier) && trim($row['SUP_CODE']) !== $reqSupplier) {
        continue;
    }

    // Format kunci grouping
    $catKey  = trim($row['ITTY_CODE']) . " " . trim($row['ITTY_DESC']);
    $supKey  = $row['SUP_CODE'] . " ~ " . $row['SUP_COMP'];
    $itemKey = $row['ITEM_CODE'] . " " . $row['ITEM_NAME'];
    $poNum   = $row['PO_NUM'];

    // Inisialisasi array jika belum ada
    if (!isset($reportData[$catKey])) {
        $reportData[$catKey] = array();
    }
    if (!isset($reportData[$catKey][$supKey])) {
        $reportData[$catKey][$supKey] = array();
    }
    if (!isset($reportData[$catKey][$supKey][$itemKey])) {
        $reportData[$catKey][$supKey][$itemKey] = array();
    }
    if (!isset($reportData[$catKey][$supKey][$itemKey][$poNum])) {
        $reportData[$catKey][$supKey][$itemKey][$poNum] = array(
            'PO_DATE'   => $row['PO_DATE'],
            'POD_PRICE' => $row['POD_PRICE'],
            'PO_QTY'    => $row['PO_QTY'],
            'OS_QTY'    => $row['OS_QTY'], // Outstanding QTY dari SP
            'RECEIPTS'  => array()
        );
    }

    // 2. Masukkan data ICL (Receive) hanya jika memang ada
    if (!empty($row['RCV_NO'])) {
        $reportData[$catKey][$supKey][$itemKey][$poNum]['RECEIPTS'][] = array(
            'RCV_NO'   => $row['RCV_NO'],
            'RCV_DATE' => $row['RCV_DATE'],
            'RCV_QTY'  => $row['RCV_QTY']
        );
    }
}

// Bebaskan resource memori SQL
sqlsrv_free_stmt($stmt);


// ====================================================================================
// SORTING DATA (A-Z DAN URUTAN TANGGAL PO TERLAMA KE TERBARU)
// ====================================================================================
ksort($reportData); // Sort Kategori A-Z
foreach ($reportData as $catKey => &$suppliers) {
    ksort($suppliers); // Sort Supplier A-Z
    foreach ($suppliers as $supKey => &$items) {
        ksort($items); // Sort Item A-Z
        foreach ($items as $itemKey => &$pos) {
            // Sort PO berdasarkan Tanggal (Ascending / Lama ke Baru)
            uasort($pos, function($a, $b) {
                $d1 = is_object($a['PO_DATE']) ? $a['PO_DATE']->format('Y-m-d') : date('Y-m-d', strtotime($a['PO_DATE']));
                $d2 = is_object($b['PO_DATE']) ? $b['PO_DATE']->format('Y-m-d') : date('Y-m-d', strtotime($b['PO_DATE']));
                
                if ($d1 == $d2) return 0;
                return ($d1 < $d2) ? -1 : 1;
            });
        }
    }
}
unset($suppliers, $items, $pos); // Clean up references
// ====================================================================================

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Purchase Report</title>
    
    <?php if (!$isExportExcel): ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <?php endif; ?>

    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        /* MENGUNCI LAYAR AGAR TIDAK BISA SCROLL DI LUAR */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden; 
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px; 
            color: #000;
        }

        .page-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        .filter-section {
            background-color: #f1f4f9;
            padding: 8px 10px;
            border-bottom: 1px solid #ddd;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0; 
        }
        .filter-section label { font-weight: bold; font-size: 13px; }
        .filter-section input[type="month"], .filter-section select { padding: 4px; border: 1px solid #ccc; }
        
        .btn { padding: 4px 10px; font-size: 12px; color: #fff; border: none; cursor: pointer; border-radius: 2px; }
        .btn-filter { background: #0056b3; }
        .btn-print { background: #28a745; }
        .btn-excel { background: #17a2b8; }

        .layout-wrapper {
            display: flex;
            flex-grow: 1;
            overflow: hidden; 
        }
        
        /* AREA SCROLL KHUSUS SIDEBAR KIRI */
        .sidebar-nav {
            width: 250px; 
            flex-shrink: 0;
            border-right: 2px solid #888;
            background-color: #f5f5f5;
            padding: 10px;
            overflow-y: auto; 
            box-sizing: border-box;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
        }
        
        /* AREA SCROLL KHUSUS REPORT (Kanan) */
        .report-content {
            flex-grow: 1;
            padding: 15px 20px;
            background-color: #fff;
            overflow-y: auto; 
            box-sizing: border-box;
        }

        .tree-list { list-style: none; padding: 0; margin: 0; }
        .tree-sublist { list-style: none; padding-left: 15px; margin-top: 5px; }
        .tree-node { margin-bottom: 4px; display: flex; align-items: center; }
        .tree-node a { text-decoration: none; color: #000; }
        .tree-node a:hover { text-decoration: underline; color: #0056b3; }
        .btn-tree { 
            border: 1px solid #999; 
            padding: 0 3px; 
            font-size: 10px; 
            margin-right: 6px; 
            font-family: monospace; 
            background: #fff; 
            display: inline-block; 
            color: #000; 
            text-decoration: none;
            line-height: 12px;
            cursor: pointer;
            width: 8px;
            text-align: center;
        }

        .header-table { width: 100%; margin-bottom: 10px; }
        .header-title { font-size: 18px; font-weight: normal; text-align: center; }
        
        .report-table { width: 100%; border-collapse: collapse; font-size: 12px; table-layout: auto; }
        .report-table th, .report-table td { padding: 2px 4px; vertical-align: top; }
        
        /* KONFIGURASI STICKY HEADER TABEL */
        .report-table thead th { 
            font-weight: normal; 
            border: none; 
            position: sticky; 
            top: 0; 
            background-color: #ffffff; 
            z-index: 10; 
            box-shadow: inset 0 1px 0 #000, inset 0 -1px 0 #000; 
        }
        
        /* SCROLL MARGIN AGAR ANCHOR LINK TIDAK TERTUTUP HEADER */
        tr[id^="cat-"], tr[id^="sup-"] { scroll-margin-top: 35px; }

        .row-category { font-style: italic; padding-top: 6px; }
        .row-supplier { background-color: #e6e6e6; text-align: center; padding: 4px; }
        .row-item { font-weight: bold; padding-top: 5px; }
        
        .row-total td { border-top: 1px solid #000; font-weight: bold; padding-top: 4px; }
        .row-total-sub td { font-weight: bold; padding-bottom: 15px; }

        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        
        .row-po { color: #000; }
        .row-icl { color: #0000FF; } 

        /* OVERRIDE SCROLL KHUSUS UNTUK PRINT / CETAK */
        @media print {
            html, body { height: auto; overflow: visible; }
            .page-container { display: block; height: auto; }
            .layout-wrapper { display: block; overflow: visible; }
            .report-content { overflow: visible; padding: 0; }
            .report-table thead th { 
                position: static; 
                box-shadow: none; 
                border-top: 1px solid #000; 
                border-bottom: 1px solid #000; 
            }
            .no-print { display: none !important; }
            .sidebar-nav { display: none !important; }
        }
    </style>
</head>
<body>

<div class="page-container">
    <?php if (!$isExportExcel): ?>
    <div class="filter-section no-print">
        <form method="GET" action="" style="display: flex; align-items: center; gap: 10px; margin: 0;">
            
            <label for="month">Bulan (SP Cut-off):</label>
            <input type="month" name="month" id="month" value="<?php echo htmlspecialchars($reqMonth); ?>">

            <label for="supplier">Supplier:</label>
            <select name="supplier" id="supplier" style="width: 250px;">
                <?php if (!empty($reqSupplier)): ?>
                    <option value="<?php echo htmlspecialchars($reqSupplier); ?>" selected="selected"><?php echo htmlspecialchars($reqSupplier); ?></option>
                <?php endif; ?>
            </select>

            <button type="submit" class="btn btn-filter">Filter</button>
            <button type="button" class="btn btn-print" onclick="window.print()">Cetak (Print)</button>
            <button type="submit" name="export" value="excel" class="btn btn-excel">Export Excel</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="layout-wrapper">
        
        <?php if (!$isExportExcel): ?>
        <!-- SIDEBAR GROUP TREE (Hierarki Kategori & Supplier) -->
        <div class="sidebar-nav no-print">
            <ul class="tree-list">
                <li>
                    <div class="tree-node">
                        <span class="btn-tree toggle-btn">-</span> <strong>PURCHASE</strong>
                    </div>
                    <ul class="tree-sublist">
                        <?php 
                        if (!empty($reportData)) {
                            foreach ($reportData as $catKey => $suppliers) { 
                                $catId = md5($catKey); 
                        ?>
                            <li>
                                <div class="tree-node">
                                    <span class="btn-tree toggle-btn">-</span> 
                                    <a href="#cat-<?php echo $catId; ?>"><?php echo htmlspecialchars($catKey); ?></a>
                                </div>
                                <ul class="tree-sublist">
                                    <?php 
                                    foreach ($suppliers as $supKey => $items) { 
                                        $supId = md5($supKey); 
                                    ?>
                                        <li>
                                            <div class="tree-node" style="padding-left: 10px;">
                                                <span class="btn-tree toggle-btn" style="visibility:hidden;">+</span>
                                                <a href="#sup-<?php echo $supId; ?>" style="color:#555;"><?php echo htmlspecialchars($supKey); ?></a>
                                            </div>
                                        </li>
                                    <?php } ?>
                                </ul>
                            </li>
                        <?php 
                            }
                        } 
                        ?>
                    </ul>
                </li>
            </ul>
        </div>
        <?php endif; ?>

        <!-- REPORT UTAMA -->
        <div class="report-content">
            <table class="header-table">
                <tr>
                    <td width="33%" class="text-left" style="vertical-align: top;">
                        <strong>P.T. IMCTEKNO INDONESIA</strong><br>
                        PPIC
                    </td>
                    <td width="34%" class="header-title" style="vertical-align: top; padding-top: 5px;">
                        PURCHASE
                    </td>
                    <td width="33%" class="text-right" style="vertical-align: top;">
                        Page 1 of 1<br><br>
                        Print Date: <?php echo date('d-M-Y H:i:s'); ?>
                    </td>
                </tr>
            </table>

            <table class="report-table">
                <thead>
                    <tr>
                        <th class="text-left" style="width: 20%;">PO #<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ICL #</th>
                        <th class="text-center" style="width: 12%;">PO DATE<br>ICL DATE</th>
                        <th class="text-right" style="width: 12%;">PO PRICE</th>
                        <th class="text-right" style="width: 14%;">PO Qty<br>ICL Qty</th>
                        <th class="text-right" style="width: 14%;">PO.Bal</th>
                        <th class="text-right" style="width: 14%;">PO Amount<br>ICL Amount</th>
                        <th class="text-right" style="width: 14%;">PO.Bal. Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (empty($reportData)) {
                        echo '<tr><td colspan="7" class="text-center" style="padding: 20px;">Data tidak ditemukan.</td></tr>';
                    }
                    
                    foreach ($reportData as $catKey => $suppliers): 
                        $catId = md5($catKey); 
                    ?>
                        <tr id="cat-<?php echo $catId; ?>">
                            <td colspan="7" class="row-category">[ <?php echo htmlspecialchars($catKey); ?> ]</td>
                        </tr>

                        <?php 
                        foreach ($suppliers as $supKey => $items): 
                            $supId = md5($supKey);
                            
                            // Variabel Akumulasi Total per Supplier
                            $sumPoAmount = 0;
                            $sumIclAmount = 0;
                            $sumPoBalAmount = 0;
                        ?>
                            <!-- Tambah Anchor ID di row supplier -->
                            <tr id="sup-<?php echo $supId; ?>">
                                <td colspan="7" class="row-supplier"><?php echo htmlspecialchars($supKey); ?></td>
                            </tr>

                            <?php foreach ($items as $itemKey => $pos): ?>
                                <tr>
                                    <td colspan="7" class="row-item"><?php echo htmlspecialchars($itemKey); ?></td>
                                </tr>

                                <?php foreach ($pos as $poNum => $poData): 
                                    $poQty = (float)$poData['PO_QTY'];
                                    $poPrice = (float)$poData['POD_PRICE'];
                                    $poAmount = $poQty * $poPrice;
                                    
                                    // Tambah ke akumulasi Total PO Supplier
                                    $sumPoAmount += $poAmount;

                                    $poDateRaw = $poData['PO_DATE'];
                                    if (is_object($poDateRaw)) {
                                        $poDate = $poDateRaw->format('d-M-Y');
                                    } else {
                                        $poDate = $poDateRaw ? date('d-M-Y', strtotime($poDateRaw)) : '';
                                    }
                                    
                                    $runningBalQty = $poQty;
                                    $runningBalAmount = $poAmount;
                                ?>
                                    <tr class="row-po">
                                        <td class="text-left">&nbsp;&nbsp;&nbsp;&nbsp;<?php echo htmlspecialchars($poNum); ?></td>
                                        <td class="text-center"><?php echo $poDate; ?></td>
                                        <td class="text-right"><?php echo number_format($poPrice, 2); ?></td>
                                        <td class="text-right"><?php echo number_format($poQty, 2); ?></td>
                                        <td class="text-right"><?php echo number_format($runningBalQty, 2); ?></td>
                                        <td class="text-right"><?php echo number_format($poAmount, 2); ?></td>
                                        <td class="text-right"><?php echo number_format($runningBalAmount, 2); ?></td>
                                    </tr>

                                    <?php foreach ($poData['RECEIPTS'] as $rcv): 
                                        $rcvQty = (float)$rcv['RCV_QTY'];
                                        $rcvAmount = $rcvQty * $poPrice;
                                        
                                        // Tambah ke akumulasi Total ICL Supplier
                                        $sumIclAmount += $rcvAmount;

                                        $runningBalQty -= $rcvQty;
                                        $runningBalAmount -= $rcvAmount;

                                        $rcvDateRaw = $rcv['RCV_DATE'];
                                        if (is_object($rcvDateRaw)) {
                                            $rcvDate = $rcvDateRaw->format('d-M-Y');
                                        } else {
                                            $rcvDate = $rcvDateRaw ? date('d-M-Y', strtotime($rcvDateRaw)) : '';
                                        }
                                    ?>
                                    <tr class="row-icl">
                                        <td class="text-left">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php echo htmlspecialchars($rcv['RCV_NO']); ?></td>
                                        <td class="text-center" style="color:#000;"><?php echo $rcvDate; ?></td>
                                        <td class="text-right"></td>
                                        <td class="text-right" style="color:#000;"><?php echo number_format($rcvQty, 2); ?></td>
                                        <td class="text-right" style="color:#000;"><?php echo number_format($runningBalQty, 2); ?></td>
                                        <td class="text-right" style="color:#000;"><?php echo number_format($rcvAmount, 2); ?></td>
                                        <td class="text-right" style="color:#000;"><?php echo number_format($runningBalAmount, 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php 
                                        // Tambah ke akumulasi Total Balance Supplier (Nilai akhir balance dari setiap PO)
                                        $sumPoBalAmount += $runningBalAmount;
                                    ?>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            
                            <!-- SUMMARY TOTAL PER SUPPLIER -->
                            <tr class="row-total">
                                <td colspan="5" class="text-right" style="padding-right: 20px;">Total PO / Balance</td>
                                <td class="text-right"><?php echo number_format($sumPoAmount, 2); ?></td>
                                <td class="text-right"><?php echo number_format($sumPoBalAmount, 2); ?></td>
                            </tr>
                            <tr class="row-total-sub">
                                <td colspan="5" class="text-right" style="padding-right: 20px;">Total ICL</td>
                                <td class="text-right"><?php echo number_format($sumIclAmount, 2); ?></td>
                                <td class="text-right"></td>
                            </tr>

                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div> <!-- END OF REPORT CONTENT -->
    </div> <!-- END OF LAYOUT WRAPPER -->
</div> <!-- END OF PAGE CONTAINER -->

<?php if (!$isExportExcel): ?>
<script>
$(document).ready(function() {
    // Select2 untuk filter
    $('#supplier').select2({
        placeholder: 'Pilih / Ketik Nama Supplier...',
        allowClear: true,
        ajax: {
            url: 'search_sup.php',
            type: 'POST',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term };
            },
            processResults: function (data) {
                return {
                    results: $.map(data, function (item) {
                        return {
                            text: item.SUP_CODE + ' ~ ' + item.SUP_COMP,
                            id: item.SUP_CODE
                        }
                    })
                };
            },
            cache: true
        }
    });

    // Interaktivitas Sidebar Tree (Expand / Collapse)
    $('.toggle-btn').click(function(e) {
        e.preventDefault();
        var $sublist = $(this).closest('.tree-node').next('.tree-sublist');
        if($sublist.length > 0) {
            $sublist.toggle();
            // Ganti text + atau -
            $(this).text($(this).text() === '-' ? '+' : '-');
        }
    });
});
</script>
<?php endif; ?>

</body>
</html>
<?php
// Cek apakah tombol Export Excel ditekan
$isExportExcel = (isset($_GET['export']) && $_GET['export'] == 'excel');

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

// Menangkap parameter tanggal (default hari ini jika tidak ada)
$rawDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'); 
$reqDate = str_replace('-', '', $rawDate); 

// Menangkap parameter supplier dari filter
$reqSupplier = isset($_GET['supplier']) ? trim($_GET['supplier']) : '';

// Eksekusi Stored Procedure menggunakan sqlsrv_query
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
    // Jika supplier dipilih pada filter, lewati baris yang tidak sesuai
    if (!empty($reqSupplier) && trim($row['SUP_CODE']) !== $reqSupplier) {
        continue;
    }

    // Format kunci grouping
    $catKey  = "[ " . $row['ITTY_CODE'] . " " . $row['ITTY_DESC'] . " ]";
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
            'OS_QTY'    => $row['OS_QTY'],
            'RECEIPTS'  => array()
        );
    }

    // Jika ada data penerimaan (Receive/ICL), masukkan ke array RECEIPTS
    if (!empty($row['RCV_NO'])) {
        $reportData[$catKey][$supKey][$itemKey][$poNum]['RECEIPTS'][] = array(
            'RCV_NO'   => $row['RCV_NO'],
            'RCV_DATE' => $row['RCV_DATE'],
            'RCV_QTY'  => $row['RCV_QTY']
        );
    }
}

// Bebaskan resource memori
sqlsrv_free_stmt($stmt);
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

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px; 
            color: #000;
        }

        .filter-section {
            background-color: #f1f4f9;
            padding: 8px 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .filter-section label {
            font-weight: bold;
            font-size: 13px;
        }
        .filter-section input[type="date"], .filter-section select {
            padding: 4px;
            border: 1px solid #ccc;
        }
        .btn {
            padding: 4px 10px;
            font-size: 12px;
            color: #fff;
            border: none;
            cursor: pointer;
            border-radius: 2px;
        }
        .btn-filter { background: #0056b3; }
        .btn-print { background: #28a745; }
        .btn-excel { background: #17a2b8; }

        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
        }

        .header-table {
            width: 100%;
            margin-bottom: 5px;
        }
        .header-title {
            font-size: 17px;
            font-weight: bold;
            text-align: center;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px; 
            table-layout: auto; /* Membantu mengatur lebar sel */
        }
        
        .report-table th, .report-table td {
            padding: 1px 3px; 
            vertical-align: top;
        }
        
        .report-table thead th {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            font-weight: bold;
        }
        
        .row-category { 
            font-weight: bold; 
            font-style: italic; 
            padding-top: 6px; 
        }
        .row-supplier { 
            background-color: #e6e6e6; 
            text-align: center; 
            font-weight: bold;
            padding: 3px; 
        }
        .row-item { 
            font-weight: bold; 
        }
        
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-blue { color: #0000FF; font-weight: bold; }
    </style>
</head>
<body>

<?php if (!$isExportExcel): ?>
<div class="filter-section no-print">
    <form method="GET" action="" style="display: flex; align-items: center; gap: 10px;">
        <label for="date">Tanggal:</label>
        <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($rawDate); ?>">

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

<table class="header-table">
    <tr>
        <td width="30%" class="text-left" style="vertical-align: top;">
            <strong>P.T. IMCTEKNO INDONESIA</strong><br>
            Commercial Business
        </td>
        <td width="40%" class="header-title" style="vertical-align: top;">PURCHASE</td>
        <td width="30%" class="text-right" style="vertical-align: top;">
            Print Date: <?php echo date('d-M-Y H:i:s'); ?>
        </td>
    </tr>
</table>

<table class="report-table">
    <thead>
        <!-- PENYESUAIAN LEBAR KOLOM (WIDTH) UNTUK MENGHILANGKAN CELAH -->
        <tr>
            <th class="text-left" style="width: auto;">PO #<br>ICL #</th>
            <th class="text-left" style="width: 10%; white-space: nowrap;">PO DATE<br>ICL DATE</th>
            <th class="text-right" style="width: 10%; white-space: nowrap;">PO PRICE</th>
            <th class="text-right" style="width: 10%; white-space: nowrap;">PO Qty<br>ICL Qty</th>
            <th class="text-right" style="width: 10%; white-space: nowrap;">PO.Bal</th>
            <th class="text-right" style="width: 13%; white-space: nowrap;">PO Amount<br>ICL Amount</th>
            <th class="text-right" style="width: 13%; white-space: nowrap;">PO.Bal. Amount</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        if (empty($reportData)) {
            echo '<tr><td colspan="7" class="text-center" style="padding: 20px;">Data tidak ditemukan.</td></tr>';
        }
        foreach ($reportData as $catKey => $suppliers): 
        ?>
            <!-- GROUP KATEGORI -->
            <tr>
                <td colspan="7" class="row-category"><?php echo htmlspecialchars($catKey); ?></td>
            </tr>

            <?php foreach ($suppliers as $supKey => $items): ?>
                <!-- GROUP SUPPLIER -->
                <tr>
                    <td colspan="7" class="row-supplier"><?php echo htmlspecialchars($supKey); ?></td>
                </tr>

                <?php foreach ($items as $itemKey => $pos): ?>
                    <!-- GROUP ITEM -->
                    <tr>
                        <td colspan="7" class="row-item"><?php echo htmlspecialchars($itemKey); ?></td>
                    </tr>

                    <?php foreach ($pos as $poNum => $poData): 
                        $poQty = (float)$poData['PO_QTY'];
                        $poPrice = (float)$poData['POD_PRICE'];
                        $poAmount = $poQty * $poPrice;
                        
                        $poDateRaw = $poData['PO_DATE'];
                        if (is_object($poDateRaw)) {
                            $poDate = $poDateRaw->format('d-M-Y');
                        } else {
                            $poDate = $poDateRaw ? date('d-M-Y', strtotime($poDateRaw)) : '';
                        }
                        
                        $runningBalQty = $poQty;
                        $runningBalAmount = $poAmount;
                    ?>
                        <!-- BARIS PO -->
                        <tr>
                            <td class="text-left"><?php echo htmlspecialchars($poNum); ?></td>
                            <td class="text-left"><?php echo $poDate; ?></td>
                            <td class="text-right"><?php echo number_format($poPrice, 0); ?></td>
                            <td class="text-right"><?php echo number_format($poQty, 0); ?></td>
                            <td class="text-right"><?php echo number_format($runningBalQty, 0); ?></td>
                            <td class="text-right"><?php echo number_format($poAmount, 0); ?></td>
                            <td class="text-right"><?php echo number_format($runningBalAmount, 0); ?></td>
                        </tr>

                        <!-- BARIS RECEIPTS (ICL) -->
                        <?php foreach ($poData['RECEIPTS'] as $rcv): 
                            $rcvQty = (float)$rcv['RCV_QTY'];
                            $rcvAmount = $rcvQty * $poPrice;
                            
                            $runningBalQty -= $rcvQty;
                            $runningBalAmount -= $rcvAmount;

                            $rcvDateRaw = $rcv['RCV_DATE'];
                            if (is_object($rcvDateRaw)) {
                                $rcvDate = $rcvDateRaw->format('d-M-Y');
                            } else {
                                $rcvDate = $rcvDateRaw ? date('d-M-Y', strtotime($rcvDateRaw)) : '';
                            }
                        ?>
                        <tr>
                            <td class="text-left text-blue">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php echo htmlspecialchars($rcv['RCV_NO']); ?></td>
                            <td class="text-left"><?php echo $rcvDate; ?></td>
                            <td class="text-right"></td>
                            <td class="text-right"><?php echo number_format($rcvQty, 2); ?></td>
                            <td class="text-right"><?php echo number_format($runningBalQty, 2); ?></td>
                            <td class="text-right"><?php echo number_format($rcvAmount, 2); ?></td>
                            <td class="text-right"><?php echo number_format($runningBalAmount, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if (!$isExportExcel): ?>
<script>
$(document).ready(function() {
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
});
</script>
<?php endif; ?>

</body>
</html>
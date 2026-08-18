<?php
if (session_id() === '') {
    session_start();
}

require_once dirname(__DIR__) . "/config/db_plant2.php";

if (!isset($conn) || $conn === false) {
    die('Koneksi database gagal. Silakan login terlebih dahulu.');
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function display_date($value) {
    if ($value === null || $value === '') return '';
    if ($value instanceof DateTime) return $value->format('d-M-Y');
    $timestamp = strtotime((string)$value);
    return $timestamp !== false ? date('d-M-Y', $timestamp) : (string)$value;
}

function format_price_cr($value, $curr) {
    $curr = strtoupper(trim((string)$curr));
    $decimals = ($curr === 'IDR') ? 2 : 5; 
    return number_format((float)$value, $decimals, '.', ',');
}

$itemName = isset($_GET['item_name']) ? trim($_GET['item_name']) : '';
$nameParam = $itemName === '' ? '%' : '%' . $itemName . '%';

$reportSql = "EXEC dbo.RPT_MATPRICELIST_CTH @NAME = ?";
$reportParams = array($nameParam);
$reportStmt = @sqlsrv_query($conn, $reportSql, $reportParams);

if ($reportStmt === false) {
    die("Report gagal dijalankan.");
}

$groupedData = array();
while ($row = sqlsrv_fetch_array($reportStmt, SQLSRV_FETCH_ASSOC)) {
    // Grouping: Supplier -> ITTY Category
    $supKey = trim((string)$row['SUP_CODE']) . ' ' . trim((string)$row['SUP_COMP']);
    $ittyKey = trim((string)$row['ITTY_CODE']) . ' ' . trim((string)$row['ITTY_DESC']);
    
    if (!isset($groupedData[$supKey])) {
        $groupedData[$supKey] = array();
    }
    if (!isset($groupedData[$supKey][$ittyKey])) {
        $groupedData[$supKey][$ittyKey] = array();
    }
    $groupedData[$supKey][$ittyKey][] = $row;
}
sqlsrv_free_stmt($reportStmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Print Price List</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            font-size: 11px; 
            color: #000; 
            margin: 0; 
            padding: 20px; 
            background: #fff;
        }
        .cr-header { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 12px; }
        .cr-title { text-align: center; font-size: 18px; margin-top: 10px; margin-bottom: 20px; }
        .cr-print-date { text-align: right; margin-bottom: 5px; font-size: 11px; }
        
        table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 10px; }
        th { 
            border-top: 1px solid #000; 
            border-bottom: 1px solid #000; 
            padding: 4px 0; 
            font-weight: normal; 
            text-align: left;
        }
        td { padding: 2px 0; vertical-align: top; }
        
        .sup-row td { 
            font-size: 12px; 
            font-weight: bold; 
            font-style: italic; 
            padding-top: 12px; 
            padding-bottom: 4px;
        }
        .itty-row td { 
            font-size: 11px; 
            font-weight: bold; 
            padding-bottom: 4px; 
        }
        .num { text-align: right; }
        .center { text-align: center; }

        .toolbar { padding: 10px; background: #f0f0f0; border: 1px solid #ccc; margin-bottom: 20px; text-align: center; }
        .btn { padding: 5px 15px; font-size: 14px; cursor: pointer; }

        @media print {
            .toolbar { display: none !important; }
            body { padding: 0; }
            @page { size: A4 portrait; margin: 10mm; }
        }
    </style>
</head>
<body onload="window.print()">

<div class="toolbar">
    <button class="btn" onclick="window.print()">Print Dokumen</button>
    <button class="btn" onclick="window.close()">Tutup</button>
</div>

<div class="cr-header">
    <div>P.T. IMCTEKNO INDONESIA</div>
</div>

<div class="cr-title">GENERAL PRICELIST (All Items)</div>

<div class="cr-print-date">Print Date: <?php echo date('m/d/Y h:i:s A'); ?></div>

<table>
    <thead>
        <tr>
            <th style="letter-spacing: 5px; width: 45%;">I T E M S</th>
            <th class="num" style="width: 10%;">Price</th>
            <th class="center" style="width: 5%;">Unit</th>
            <th style="width: 15%;">Quot.NO</th>
            <th class="center" style="width: 12%;">Quot. Date</th>
            <th class="center" style="width: 13%;">Effect. Date</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($groupedData as $supKey => $ittys) { ?>
            <!-- Group 1: Supplier -->
            <tr class="sup-row">
                <td colspan="6"><?php echo h($supKey); ?></td>
            </tr>
            
            <?php foreach ($ittys as $ittyKey => $items) { ?>
                <!-- Group 2: ITTY Category -->
                <tr class="itty-row">
                    <td colspan="6"><?php echo h($ittyKey); ?></td>
                </tr>
                
                <!-- Data Items -->
                <?php foreach ($items as $item) { ?>
                    <tr>
                        <td><?php echo h($item['ITEM_CODE'] . ' ' . $item['ITEM_NAME']); ?></td>
                        <td class="num">
                            <?php echo h(format_price_cr($item['QUOD_PRICE'], $item['CURR_CODE'])) . ' ' . h($item['CURR_CODE']); ?>
                        </td>
                        <td class="center"><?php echo h($item['QUOD_UNIT']); ?></td>
                        <td><?php echo h($item['QUO_NO']); ?></td>
                        <td class="center"><?php echo display_date($item['QUO_DATE']); ?></td>
                        <td class="center"><?php echo display_date($item['QUO_EFFDATE']); ?></td>
                    </tr>
                <?php } ?>
            <?php } ?>
        <?php } ?>
    </tbody>
</table>

</body>
</html>
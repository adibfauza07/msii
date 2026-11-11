<?php
// ============================================================
// WEEKLY RECEIVE MATERIAL SUPPLIER - GABUNGAN P1 & P2
// PHP 5.4 + SQL Server 2008+
// Stored procedure pada setiap plant: dbo.sp_rec_mat_sup
// ============================================================

set_time_limit(180);

if (session_id() === '') {
    session_start();
}

require_once __DIR__ . "/../config/global.php";

$dbName = 'msData';

$serversConfig = array(
    'p1' => array(
        'ip' => '192.168.0.4',
        'label' => 'Plant 1',
        'short' => 'P1'
    ),
    'p2' => array(
        'ip' => '192.168.0.9',
        'label' => 'Plant 2',
        'short' => 'P2'
    )
);

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sqlErrorsText()
{
    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);

    if (!is_array($errors) || count($errors) === 0) {
        return 'Kesalahan SQL Server tidak diketahui.';
    }

    $messages = array();

    foreach ($errors as $error) {
        $state = isset($error['SQLSTATE']) ? $error['SQLSTATE'] : '';
        $code = isset($error['code']) ? $error['code'] : '';
        $message = isset($error['message']) ? $error['message'] : '';

        $messages[] = trim('[' . $state . '] ' . $code . ' ' . $message);
    }

    return implode(' | ', $messages);
}

function normalizePlant($plant)
{
    $plant = strtolower(trim((string)$plant));

    if (!in_array($plant, array('all', 'p1', 'p2'), true)) {
        return 'all';
    }

    return $plant;
}

function plantKeys($plant)
{
    return $plant === 'all'
        ? array('p1', 'p2')
        : array($plant);
}

function plantDisplay($plant)
{
    if ($plant === 'p1') {
        return 'Plant 1';
    }

    if ($plant === 'p2') {
        return 'Plant 2';
    }

    return 'Gabungan P1 & P2';
}

function sortableValue($value)
{
    if ($value === null) {
        return '';
    }

    if ($value instanceof DateTime) {
        return $value->format('Y-m-d H:i:s.u');
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_scalar($value)) {
        return (string)$value;
    }

    return '';
}

function openPlantConnection($serverKey, $serversConfig, $dbName)
{
    if (
        !isset($_SESSION['db_user']) ||
        trim((string)$_SESSION['db_user']) === ''
    ) {
        return false;
    }

    if (!isset($serversConfig[$serverKey])) {
        return false;
    }

    $uid = $_SESSION['db_user'];
    $pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : '';

    $connectionOptions = array(
        'Database' => $dbName,
        'Uid' => $uid,
        'PWD' => $pwd,
        'CharacterSet' => 'UTF-8',
        'LoginTimeout' => 5,
        'ReturnDatesAsStrings' => false
    );

    return @sqlsrv_connect(
        $serversConfig[$serverKey]['ip'],
        $connectionOptions
    );
}

function formatDate($date)
{
    if (!$date) {
        return '';
    }

    if ($date instanceof DateTime) {
        return $date->format('m/d/Y');
    }

    $timestamp = strtotime((string)$date);

    if ($timestamp === false) {
        return '';
    }

    return date('m/d/Y', $timestamp);
}

function formatDatePO($date)
{
    if (!$date) {
        return '';
    }

    if ($date instanceof DateTime) {
        return $date->format('d-M-y');
    }

    $timestamp = strtotime((string)$date);

    if ($timestamp === false) {
        return '';
    }

    return date('d-M-y', $timestamp);
}

function formatQty($number)
{
    if ((float)$number == 0) {
        return '0';
    }

    return number_format((float)$number, 0, '.', ',');
}

function formatPOQty($number)
{
    if ((float)$number == 0) {
        return '0.00';
    }

    return number_format((float)$number, 2, '.', ',');
}

function formatMoney($number)
{
    if ((float)$number == 0) {
        return '0.00';
    }

    return number_format((float)$number, 2, '.', ',');
}


$hasParams = isset($_GET['start_date']) && isset($_GET['end_date']);
$isExport = isset($_GET['export']) && $_GET['export'] === 'excel';

$dataRows = array();
$grouped = array();
$errors = array();
$serverStatus = array('p1' => false, 'p2' => false);

$totalRows = 0;
$startDate = '';
$endDate = '';
$supCode = '';
$itemName = '';
$plant = isset($_GET['plant']) ? normalizePlant($_GET['plant']) : 'all';
$currentYear = date('Y');

if ($hasParams) {
    $startDate = trim($_GET['start_date']);
    $endDate = trim($_GET['end_date']);
    $supCode = isset($_GET['sup_code']) ? trim($_GET['sup_code']) : '';
    $itemName = isset($_GET['item_name']) ? trim($_GET['item_name']) : '';

    $supParam = $supCode !== '' ? $supCode : '%';

    foreach (plantKeys($plant) as $serverKey) {
        $serverLabel = $serversConfig[$serverKey]['label'];
        $serverShort = $serversConfig[$serverKey]['short'];

        $conn = openPlantConnection($serverKey, $serversConfig, $dbName);

        if ($conn === false) {
            $errors[] = $serverLabel . ': koneksi gagal. ' . sqlErrorsText();
            continue;
        }

        $serverStatus[$serverKey] = true;

        $sql = '{CALL dbo.sp_rec_mat_sup(?, ?, ?, ?)}';

        $params = array(
            array($startDate, SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DATETIME),
            array($endDate, SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DATETIME),
            array($supParam, SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_VARCHAR(20)),
            array($itemName, SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_VARCHAR(100))
        );

        $stmt = @sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $errors[] = $serverLabel . ': stored procedure gagal. ' . sqlErrorsText();
            sqlsrv_close($conn);
            continue;
        }

        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $row['_PLANT_KEY'] = $serverKey;
            $row['_PLANT_SHORT'] = $serverShort;
            $row['_PLANT_LABEL'] = $serverLabel;
            $dataRows[] = $row;
        }

        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);
    }

    // Sort awal
    usort($dataRows, function ($a, $b) {
        $fields = array(
            '_PLANT_SHORT',
            'ITTY_CODE',
            'SUP_CODE',
            'PO_NUM',
            'ITEM_CODE',
            'RCV_DATE'
        );

        foreach ($fields as $field) {
            $left = isset($a[$field]) ? sortableValue($a[$field]) : '';
            $right = isset($b[$field]) ? sortableValue($b[$field]) : '';

            $compare = strcmp($left, $right);

            if ($compare !== 0) {
                return $compare;
            }
        }

        return 0;
    });

    // GROUPING: Plant -> ITTY -> Supplier -> PO -> Item -> Receive Date
    foreach ($dataRows as $row) {
        $plantKey = $row['_PLANT_KEY'];
        
        $ittyCode = isset($row['ITTY_CODE']) ? trim((string)$row['ITTY_CODE']) : '';
        $ittyDesc = isset($row['ITTY_DESC']) ? trim((string)$row['ITTY_DESC']) : '';
        $ittyKey = ($ittyCode === '' && $ittyDesc === '') ? 'Uncategorized' : trim($ittyCode . ' ' . $ittyDesc);
        
        $supKey = trim((string)$row['SUP_CODE']);
        $poKey = trim((string)$row['PO_NUM']);
        $itemKey = trim((string)$row['ITEM_CODE']);

        if (!isset($grouped[$plantKey])) {
            $grouped[$plantKey] = array(
                'PLANT_SHORT' => $row['_PLANT_SHORT'],
                'PLANT_LABEL' => $row['_PLANT_LABEL'],
                'ittys' => array()
            );
        }

        if (!isset($grouped[$plantKey]['ittys'][$ittyKey])) {
            $grouped[$plantKey]['ittys'][$ittyKey] = array();
        }

        if (!isset($grouped[$plantKey]['ittys'][$ittyKey][$supKey])) {
            $grouped[$plantKey]['ittys'][$ittyKey][$supKey] = array(
                'SUP_CODE' => $supKey,
                'SUP_COMP' => trim((string)$row['SUP_COMP']),
                'pos' => array()
            );
        }

        if (!isset($grouped[$plantKey]['ittys'][$ittyKey][$supKey]['pos'][$poKey])) {
            $grouped[$plantKey]['ittys'][$ittyKey][$supKey]['pos'][$poKey] = array(
                'PO_NUM' => $poKey,
                'items' => array()
            );
        }

        if (!isset($grouped[$plantKey]['ittys'][$ittyKey][$supKey]['pos'][$poKey]['items'][$itemKey])) {
            $grouped[$plantKey]['ittys'][$ittyKey][$supKey]['pos'][$poKey]['items'][$itemKey] = array(
                'ITEM_CODE' => $itemKey,
                'ITEM_NAME' => trim((string)$row['ITEM_NAME']),
                'POD_QTY' => isset($row['POD_QTY']) ? (float)$row['POD_QTY'] : 0,
                'UNIT' => isset($row['UNIT']) ? trim((string)$row['UNIT']) : '',
                'PRICE' => isset($row['PRICE']) ? (float)$row['PRICE'] : 0,
                'CURR_CODE' => isset($row['CURR_CODE']) ? trim((string)$row['CURR_CODE']) : '',
                'TOTAL_RECEIVE' => 0,
                'dates' => array()
            );
        }

        if (isset($row['RCV_DATE']) && $row['RCV_DATE'] instanceof DateTime) {
            $rcvDateKey = $row['RCV_DATE']->format('Y-m-d');
        } else {
            $rcvDateKey = isset($row['RCV_DATE']) ? (string)$row['RCV_DATE'] : '';
        }

        $receiveQty = isset($row['RQty']) ? (float)$row['RQty'] : 0;
        $price = isset($row['PRICE']) ? (float)$row['PRICE'] : 0;

        if (!isset($grouped[$plantKey]['ittys'][$ittyKey][$supKey]['pos'][$poKey]['items'][$itemKey]['dates'][$rcvDateKey])) {
            $grouped[$plantKey]['ittys'][$ittyKey][$supKey]['pos'][$poKey]['items'][$itemKey]['dates'][$rcvDateKey] = array(
                'RCV_DATE' => isset($row['RCV_DATE']) ? $row['RCV_DATE'] : '',
                'RQty' => 0,
                'AMOUNT' => 0
            );
        }

        $grouped[$plantKey]['ittys'][$ittyKey][$supKey]['pos'][$poKey]['items'][$itemKey]['dates'][$rcvDateKey]['RQty'] += $receiveQty;
        $grouped[$plantKey]['ittys'][$ittyKey][$supKey]['pos'][$poKey]['items'][$itemKey]['dates'][$rcvDateKey]['AMOUNT'] += $receiveQty * $price;
        $grouped[$plantKey]['ittys'][$ittyKey][$supKey]['pos'][$poKey]['items'][$itemKey]['TOTAL_RECEIVE'] += $receiveQty;
    }

    // Sort Kategori ITTY A-Z pada tiap plant
    foreach ($grouped as &$pg) {
        ksort($pg['ittys']);
    }
    unset($pg);

    $totalRows = count($dataRows);
}

// ---------------------------------------------------------
// EXPORT HEADER DILAKUKAN SEBELUM HTML OUTPUT
// ---------------------------------------------------------
if ($isExport) {
    $filename = 'Weekly_Receive_' . strtoupper($plant) . '_' . date('Ymd') . '.xls';

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
}
?>


<?php
// =========================================================
// HTML WRAPPER (HANYA DITAMPILKAN JIKA BUKAN EXPORT EXCEL)
// =========================================================
if (!$isExport) { 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Receive Gabungan</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        body { background:#f0f2f5; font-family:"Times New Roman",serif; font-size:13px; margin:0; padding:0; <?php echo $hasParams ? 'height: 100vh; overflow: hidden;' : ''; ?> }
        
        .page-container { display: flex; flex-direction: column; height: 100vh; padding-top: 55px; }
        .layout-wrapper { display: flex; flex-grow: 1; overflow: hidden; background: #fff; border-top: 1px solid #d7dee7; }
        
        /* Area Scroll Sidebar Kiri */
        .sidebar-nav { width: 300px; flex-shrink: 0; border-right: 2px solid #ccc; background-color: #f8f9fa; padding: 15px; overflow-y: auto; box-sizing: border-box; font-family: Arial, sans-serif; font-size: 12px; }
        .tree-list { list-style: none; padding: 0; margin: 0; }
        .tree-sublist { list-style: none; padding-left: 15px; margin-top: 5px; }
        .tree-node { margin-bottom: 5px; display: flex; align-items: flex-start; gap: 5px; }
        .tree-node a { text-decoration: none; color: #333; word-break: break-word; display: block; padding: 2px 0; }
        .tree-node a:hover, .tree-node a.active { color: #0d6efd; font-weight: bold; }
        .btn-tree { border: 1px solid #999; padding: 0; font-size: 11px; font-family: monospace; background: #fff; display: inline-block; color: #000; text-decoration: none; line-height: 12px; cursor: pointer; width: 12px; height: 12px; text-align: center; flex-shrink: 0; margin-top: 4px; user-select: none; }
        
        /* Area Scroll Report Kanan */
        .report-content { flex-grow: 1; padding: 25px; overflow-y: auto; box-sizing: border-box; background-color: #fff; position: relative; }

        .param-overlay{position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:9999;display:flex;align-items:center;justify-content:center}
        .param-dialog{background:#fff;border-radius:14px;width:600px;max-width:95vw;box-shadow:0 25px 60px rgba(0,0,0,.25);overflow:visible}
        .param-dialog-header{padding:18px 25px;border-bottom:1px solid #e2e8f0;font-family:Arial,sans-serif}
        .param-dialog-body{padding:22px 25px;font-family:Arial,sans-serif}
        .param-dialog-footer{padding:15px 25px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end}
        .param-field{margin-bottom:15px}
        .param-field label{font-size:11px;font-weight:700;color:#475569;margin-bottom:5px}
        .date-row{display:flex;align-items:center;gap:8px}
        .ac-wrap{position:relative}
        .ac-list{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-radius:7px;max-height:230px;overflow:auto;z-index:10010;display:none;box-shadow:0 10px 25px rgba(0,0,0,.15)}
        .ac-list.show{display:block}
        .ac-item{padding:8px 10px;border-bottom:1px solid #f1f5f9;cursor:pointer;display:grid;grid-template-columns:55px 105px 1fr;gap:8px;font-size:11px}
        .ac-item:hover{background:#dbeafe}
        .plant-badge{font-weight:700;color:#7c3aed}
        .code{font-family:monospace;font-weight:700;color:#1d4ed8}
        
        .company-name{font:bold 14px Arial,sans-serif}
        .report-header{text-align:center;margin-bottom:20px}
        .report-title{font:bold 20px Arial,sans-serif;text-decoration:underline}
        .report-params{font:bold 13px Arial,sans-serif;margin-top:7px}
        
        .report-table{width:100%;border-collapse:collapse;font-size:13px}
        .report-table thead th { border-top:1px solid #000; border-bottom:1px solid #000; padding:6px 2px; position: sticky; top: 0; background: #fff; z-index: 10; box-shadow: inset 0 1px 0 #000, inset 0 -1px 0 #000; }
        .report-table td{padding:3px 2px}
        
        tr[id^="sup-"] { scroll-margin-top: 40px; } 
        
        .plant-row td{background:#dbeafe;font:bold 13px Arial,sans-serif;border-top:2px solid #2563eb;padding:7px}
        .itty-row td{background:#f1f5f9;font:bold 12px Arial,sans-serif;padding:6px; font-style: italic;}
        .supplier-row td{font:bold 12px Arial,sans-serif;padding-top:9px}
        .po-row td{font:bold 12px Arial,sans-serif;padding-top:5px; color: #333;}
        
        @media print{
            body{background:#fff; height: auto; overflow: visible;}
            .page-container { display: block; height: auto; padding-top: 0; }
            .layout-wrapper { display: block; overflow: visible; border: none; }
            .report-content { overflow: visible; padding: 0; }
            .report-table thead th { position: static; box-shadow: none; border-top: 1px solid #000; border-bottom: 1px solid #000; }
            .no-print{display:none!important}
            .itty-data-group { display: table-row-group !important; } /* Munculkan semua saat print */
            #empty-msg { display: none !important; }
        }
    </style>
</head>
<body>

    <?php if (!$hasParams) { ?>
    <div class="param-overlay">
        <div class="param-dialog">
            <div class="param-dialog-header">
                <strong>Weekly Receive Material — P1 & P2</strong>
                <div class="text-secondary small">Pilih plant dan kriteria laporan</div>
            </div>

            <div class="param-dialog-body">
                <form id="paramForm" method="get" action="">
                    <div class="param-field">
                        <label>Plant</label>
                        <select class="form-select" name="plant" id="plantFilter">
                            <option value="all">Gabungan P1 & P2</option>
                            <option value="p1">Plant 1</option>
                            <option value="p2">Plant 2</option>
                        </select>
                    </div>

                    <div class="param-field">
                        <label>Periode RCV</label>
                        <div class="date-row">
                            <input type="date" name="start_date" value="<?php echo h($currentYear . '-01-01'); ?>" class="form-control" required>
                            <span>s/d</span>
                            <input type="date" name="end_date" value="<?php echo h(date('Y-m-d')); ?>" class="form-control" required>
                        </div>
                    </div>

                    <div class="param-field">
                        <label>Supplier</label>
                        <div class="ac-wrap" id="acWrapSup">
                            <input type="text" id="modalSupInput" class="form-control" placeholder="Kosongkan untuk semua" autocomplete="off">
                            <input type="hidden" name="sup_code" id="modalSupHidden">
                            <div class="ac-list" id="modalSupDropdown"></div>
                        </div>
                    </div>

                    <div class="param-field">
                        <label>Item Name</label>
                        <input type="text" name="item_name" class="form-control" placeholder="Keyword nama item">
                    </div>
                </form>
            </div>

            <div class="param-dialog-footer">
                <button type="submit" form="paramForm" class="btn btn-primary btn-sm">
                    <i class="bi bi-search"></i> View Report
                </button>
            </div>
        </div>
    </div>
    <?php } ?>

    <?php if ($hasParams) { ?>
    <nav class="navbar bg-white border-bottom fixed-top no-print">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1 fs-6 fw-bold">
                WEEKLY RECEIVE MATERIAL
            </span>
            <div>
                <a href="?" class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> Parameter</a>
                <a class="btn btn-success btn-sm" href="?plant=<?php echo urlencode($plant); ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&sup_code=<?php echo urlencode($supCode); ?>&item_name=<?php echo urlencode($itemName); ?>&export=excel"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.print();"><i class="bi bi-printer"></i> Print</button>
            </div>
        </div>
    </nav>

    <div class="page-container">
        
        <?php if (count($errors) > 0) { ?>
        <div class="container-fluid no-print" style="margin-top: 10px;">
            <div class="alert alert-warning">
                <?php foreach ($errors as $error) { ?>
                    <div><?php echo h($error); ?></div>
                <?php } ?>
            </div>
        </div>
        <?php } ?>
        
        <div class="layout-wrapper">
            
            <!-- SIDEBAR TREE VIEW -->
            <div class="sidebar-nav no-print">
                <ul class="tree-list">
                    <?php foreach ($grouped as $plantKey => $plantGroup) { ?>
                    <li>
                        <div class="tree-node">
                            <span class="btn-tree toggle-btn">-</span> <strong><?php echo h($plantGroup['PLANT_SHORT']); ?></strong>
                        </div>
                        <ul class="tree-sublist">
                            <?php foreach ($plantGroup['ittys'] as $ittyKey => $suppliers) { 
                                $ittyHash = $plantKey . '-' . md5($ittyKey);
                            ?>
                                <li>
                                    <div class="tree-node">
                                        <span class="btn-tree toggle-btn">-</span> 
                                        <!-- KLIK ITTY: Hanya tampilkan data ITTY ini -->
                                        <a href="javascript:void(0);" onclick="showIttyData('<?php echo $ittyHash; ?>', this)" style="font-weight: bold;"><?php echo h($ittyKey); ?></a>
                                    </div>
                                    <ul class="tree-sublist">
                                        <?php foreach ($suppliers as $supKey => $supData) { 
                                            $supAnchorId = $plantKey . '-' . md5($supKey);
                                        ?>
                                            <li>
                                                <div class="tree-node">
                                                    <span class="btn-tree" style="visibility:hidden;">-</span>
                                                    <!-- KLIK SUPPLIER: Tampilkan ITTY ini dan scroll ke Supplier -->
                                                    <a href="javascript:void(0);" onclick="showSupData('<?php echo $ittyHash; ?>', 'sup-<?php echo $supAnchorId; ?>', this)"><?php echo h($supKey . ' ~ ' . $supData['SUP_COMP']); ?></a>
                                                </div>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                </li>
                            <?php } ?>
                        </ul>
                    </li>
                    <?php } ?>
                </ul>
            </div>

            <!-- REPORT UTAMA BUKA -->
            <div class="report-content">
    <?php } // PENUTUP if ($hasParams) ?>
<?php } // PENUTUP if (!$isExport) ?>



<?php 
// =========================================================
// DATA TABEL (DITAMPILKAN BAIK DI HTML MAUPUN EXCEL)
// =========================================================
if ($hasParams) { 
?>
                <div class="company-name" <?php echo !$isExport ? 'id="report-header-top"' : ''; ?>>PT.IMC TEKNO INDONESIA</div>
                <div class="report-header">
                    <div class="report-title">WEEKLY RECEIVE MATERIAL SUPPLIER</div>
                    <div class="report-params">
                        <?php echo h(plantDisplay($plant)); ?> |
                        From: <?php echo h(formatDatePO($startDate)); ?> |
                        To: <?php echo h(formatDatePO($endDate)); ?>
                    </div>
                </div>

                <table class="report-table" <?php echo $isExport ? 'border="1"' : ''; ?>>
                    <thead>
                    <tr>
                        <th style="text-align:left;width:12%">Item</th>
                        <th style="text-align:left;width:28%">Name</th>
                        <th style="text-align:center;width:10%">RCV Date</th>
                        <th style="text-align:right;width:8%">PO Qty</th>
                        <th style="text-align:right;width:8%">Receive</th>
                        <th style="text-align:right;width:8%">O/S PO</th>
                        <th style="text-align:center;width:6%">Unit</th>
                        <th style="text-align:right;width:10%">Price</th>
                        <th style="text-align:center;width:5%">Curr</th>
                        <th style="text-align:right;width:12%">Amount</th>
                    </tr>
                    </thead>

                    <?php if (!$isExport) { ?>
                    <!-- PESAN AWAL KOSONG DI TAMPILAN WEB -->
                    <tbody id="empty-msg">
                        <tr>
                            <td colspan="10" style="text-align:center; padding:80px 20px; color:#64748b; font-size:15px;">
                                <i class="bi bi-arrow-left-square" style="font-size: 30px; display:block; margin-bottom: 10px; color:#0d6efd;"></i>
                                Silakan klik nama <b>Kategori (Part / Resin)</b> di menu sebelah kiri<br>untuk menampilkan data laporannya.
                            </td>
                        </tr>
                    </tbody>
                    <?php } ?>

                    <?php if (count($grouped) > 0) { ?>
                        <?php foreach ($grouped as $plantKey => $plantGroup) { ?>
                            
                            <?php foreach ($plantGroup['ittys'] as $ittyKey => $suppliers) { 
                                $ittyHash = $plantKey . '-' . md5($ittyKey);
                            ?>
                                <!-- BUNGKUS SETIAP KATEGORI ITTY DALAM TBODY TERPISAH -->
                                <tbody id="content-<?php echo $ittyHash; ?>" class="itty-data-group" <?php echo !$isExport ? 'style="display:none;"' : ''; ?>>
                                
                                <tr class="plant-row">
                                    <td colspan="10">
                                        <?php echo h($plantGroup['PLANT_SHORT'] . ' - ' . $plantGroup['PLANT_LABEL']); ?>
                                    </td>
                                </tr>
                                <tr class="itty-row">
                                    <td colspan="10">[ <?php echo h($ittyKey); ?> ]</td>
                                </tr>
                                
                                <?php foreach ($suppliers as $supKey => $supplier) { 
                                    $supAnchorId = $plantKey . '-' . md5($supKey);
                                ?>
                                    <tr class="supplier-row" id="sup-<?php echo $supAnchorId; ?>">
                                        <td colspan="10">
                                            <?php echo h($supplier['SUP_CODE'] . ' ' . $supplier['SUP_COMP']); ?>
                                        </td>
                                    </tr>

                                    <?php foreach ($supplier['pos'] as $po) { ?>
                                        <tr class="po-row">
                                            <td colspan="10">&nbsp;&nbsp;<?php echo h($po['PO_NUM']); ?></td>
                                        </tr>

                                        <?php foreach ($po['items'] as $item) { ?>
                                            <?php
                                            $outstanding = $item['POD_QTY'] - $item['TOTAL_RECEIVE'];
                                            $outstandingDisplay = $outstanding <= 0 ? '-' : formatQty($outstanding);
                                            ?>
                                            <tr>
                                                <td style="padding-left:15px"><?php echo h($item['ITEM_CODE']); ?></td>
                                                <td><?php echo h($item['ITEM_NAME']); ?></td>
                                                <td></td>
                                                <td style="text-align:right;color:blue;font-weight:bold">
                                                    <?php echo h(formatQty($item['POD_QTY'])); ?>
                                                </td>
                                                <td style="text-align:right;color:green;font-weight:bold">
                                                    <?php echo h(formatQty($item['TOTAL_RECEIVE'])); ?>
                                                </td>
                                                <td style="text-align:right;color:red;font-weight:bold">
                                                    <?php echo h($outstandingDisplay); ?>
                                                </td>
                                                <td></td><td></td><td></td><td></td>
                                            </tr>

                                            <?php foreach ($item['dates'] as $dateRow) { ?>
                                            <tr>
                                                <td></td>
                                                <td></td>
                                                <td style="text-align:center">
                                                    <?php echo h(formatDatePO($dateRow['RCV_DATE'])); ?>
                                                </td>
                                                <td></td>
                                                <td style="text-align:right">
                                                    <?php echo h(formatQty($dateRow['RQty'])); ?>
                                                </td>
                                                <td></td>
                                                <td style="text-align:center"><?php echo h($item['UNIT']); ?></td>
                                                <td style="text-align:right"><?php echo h(formatMoney($item['PRICE'])); ?></td>
                                                <td style="text-align:center"><?php echo h($item['CURR_CODE']); ?></td>
                                                <td style="text-align:right"><?php echo h(formatMoney($dateRow['AMOUNT'])); ?></td>
                                            </tr>
                                            <?php } ?>
                                        <?php } ?>
                                    <?php } ?>
                                <?php } ?>
                                </tbody> <!-- End of this ITTY block -->
                            <?php } ?>
                        <?php } ?>
                    <?php } else { ?>
                        <tbody>
                            <tr>
                                <td colspan="10" style="text-align:center;padding:35px;color:#64748b">
                                    Tidak ada data untuk kriteria ini.
                                </td>
                            </tr>
                        </tbody>
                    <?php } ?>
                </table>
<?php } // PENUTUP if ($hasParams) untuk bagian tabel ?>



<?php 
// =========================================================
// HTML WRAPPER PENUTUP DAN JS (HANYA DITAMPILKAN DI HTML)
// =========================================================
if (!$isExport) { 
    if ($hasParams) {
?>
            </div> <!-- End report-content -->
        </div> <!-- End layout-wrapper -->
    </div> <!-- End page-container -->
    <?php } // PENUTUP if ($hasParams) ?>

<script>
// Fungsi Menampilkan Data Berdasarkan Klik Kategori (ITTY)
function showIttyData(ittyHash, el) {
    // Sembunyikan semua data dan pesan kosong
    $('.itty-data-group').hide();
    $('#empty-msg').hide();
    
    // Tampilkan hanya tbody ITTY yang diklik
    $('#content-' + ittyHash).show();
    
    // Ganti warna tulisan yg aktif di sidebar
    $('.tree-list a').removeClass('active');
    $(el).addClass('active');

    // Scroll ke atas halaman report
    $('.report-content').scrollTop(0);
}

// Fungsi Menampilkan Data Berdasarkan Klik Supplier
function showSupData(ittyHash, supId, el) {
    // Sembunyikan semua dan tampilkan ITTY induknya
    $('.itty-data-group').hide();
    $('#empty-msg').hide();
    $('#content-' + ittyHash).show();
    
    // Ganti warna tulisan yg aktif di sidebar
    $('.tree-list a').removeClass('active');
    $(el).addClass('active');

    // Beri jeda sedikit agar DOM sempat render, lalu scroll otomatis ke baris Supplier
    setTimeout(function() {
        var target = document.getElementById(supId);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, 50);
}

function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = value || '';
    return div.innerHTML;
}

function createAutocomplete(config) {
    var input = document.getElementById(config.inputId);
    var hidden = document.getElementById(config.hiddenId);
    var dropdown = document.getElementById(config.dropdownId);
    var wrap = document.getElementById(config.wrapId);
    var plant = document.getElementById('plantFilter');
    var timer = null;

    if (!input || !hidden || !dropdown || !wrap) {
        return;
    }

    function hide() {
        dropdown.classList.remove('show');
        dropdown.innerHTML = '';
    }

    function search(query) {
        dropdown.innerHTML = '<div style="padding:10px;text-align:center;color:#94a3b8">Mencari...</div>';
        dropdown.classList.add('show');

        var formData = new FormData();
        formData.append('q', query);
        formData.append('plant', plant ? plant.value : 'all');

        fetch(config.url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (!data || !data.length) {
                dropdown.innerHTML = '<div style="padding:10px;text-align:center;color:#94a3b8">Tidak ditemukan</div>';
                return;
            }

            var html = '';

            for (var i = 0; i < data.length; i++) {
                var row = data[i];
                var code = escapeHtml(row.SUP_CODE || '');
                var name = escapeHtml(row.SUP_COMP || '');
                var plantText = escapeHtml(row.PLANT_SHORT || '');

                html +=
                    '<div class="ac-item" data-code="' + code + '" data-name="' + name + '">' +
                    '<span class="plant-badge">' + plantText + '</span>' +
                    '<span class="code">' + code + '</span>' +
                    '<span>' + name + '</span>' +
                    '</div>';
            }

            dropdown.innerHTML = html;

            var items = dropdown.querySelectorAll('.ac-item');

            for (var j = 0; j < items.length; j++) {
                items[j].onclick = function () {
                    var code = this.getAttribute('data-code') || '';
                    var name = this.getAttribute('data-name') || '';

                    input.value = code + (name ? ' - ' + name : '');
                    hidden.value = code;
                    hide();
                };
            }
        })
        .catch(function () {
            dropdown.innerHTML = '<div style="padding:10px;text-align:center;color:#b91c1c">Gagal memuat data</div>';
        });
    }

    input.addEventListener('input', function () {
        var query = this.value.replace(/^\s+|\s+$/g, '');
        hidden.value = '';
        clearTimeout(timer);

        if (query.length < 1) {
            hide();
            return;
        }

        timer = setTimeout(function () {
            search(query);
        }, 300);
    });

    document.addEventListener('click', function (event) {
        if (!wrap.contains(event.target)) {
            hide();
        }
    });

    if (plant) {
        plant.addEventListener('change', function () {
            input.value = '';
            hidden.value = '';
            hide();
        });
    }
}

createAutocomplete({
    inputId: 'modalSupInput',
    hiddenId: 'modalSupHidden',
    dropdownId: 'modalSupDropdown',
    wrapId: 'acWrapSup',
    url: 'search_sup_multi.php'
});

// Interaktivitas Sidebar Tree (Expand / Collapse)
$(document).ready(function() {
    $('.toggle-btn').click(function(e) {
        e.preventDefault();
        var $sublist = $(this).closest('.tree-node').next('.tree-sublist');
        if($sublist.length > 0) {
            $sublist.toggle();
            $(this).text($(this).text() === '-' ? '+' : '-');
        }
    });
});
</script>
</body>
</html>
<?php } // PENUTUP TOTAL UNTUK if (!$isExport) ?>
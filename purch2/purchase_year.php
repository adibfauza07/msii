<?php
// ============================================================
// PURCHASE YEAR REPORT - GABUNGAN P1 & P2
// PHP 5.4 + SQL Server 2008+
// Stored procedure pada setiap plant: dbo.RPT_PURCHASE_YEAR
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

/*
 * SQLSRV mengembalikan kolom datetime sebagai objek DateTime.
 * Fungsi ini menormalkan nilai sebelum dipakai oleh strcmp/usort.
 */
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

$grandPoQty = 0;
$grandPoAmount = 0;
$grandRcvQty = 0;
$grandRcvAmount = 0;

$totalRows = 0;
$startDate = '';
$endDate = '';
$itemCode = '';
$supCode = '';
$plant = isset($_GET['plant']) ? normalizePlant($_GET['plant']) : 'all';
$currentYear = date('Y');

if ($hasParams) {
    $startDate = trim($_GET['start_date']);
    $endDate = trim($_GET['end_date']);
    $itemCode = isset($_GET['item_code']) ? trim($_GET['item_code']) : '';
    $supCode = isset($_GET['sup_code']) ? trim($_GET['sup_code']) : '';

    $codeParam = $itemCode !== '' ? '%' . $itemCode . '%' : '%';
    $supParam = $supCode !== '' ? '%' . $supCode . '%' : '%';

    foreach (plantKeys($plant) as $serverKey) {
        $serverLabel = $serversConfig[$serverKey]['label'];
        $serverShort = $serversConfig[$serverKey]['short'];

        $conn = openPlantConnection($serverKey, $serversConfig, $dbName);

        if ($conn === false) {
            $errors[] = $serverLabel . ': koneksi gagal. ' . sqlErrorsText();
            continue;
        }

        $serverStatus[$serverKey] = true;

        $sql = '{CALL dbo.RPT_PURCHASE_YEAR(?, ?, ?, ?)}';

        $params = array(
            array($startDate, SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DATETIME),
            array($endDate, SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DATETIME),
            array($codeParam, SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_VARCHAR(20)),
            array($supParam, SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_VARCHAR(20))
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

    usort($dataRows, function ($a, $b) {
    $fields = array(
        '_PLANT_SHORT',
        'SUP_CODE',
        'ITEM_CODE',
        'PO_DATE',
        'PO_NUM',
        'RCV_DATE',
        'RCV_NOMOR'
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

    // GROUPING: Plant -> Supplier -> Item -> PO -> Receive
    foreach ($dataRows as $row) {
        $plantKey = $row['_PLANT_KEY'];
        $supKey = trim((string)$row['SUP_CODE']);
        $itemKey = trim((string)$row['ITEM_CODE']);
        $poKey = trim((string)$row['PO_NUM']);

        if (!isset($grouped[$plantKey])) {
            $grouped[$plantKey] = array(
                'PLANT_SHORT' => $row['_PLANT_SHORT'],
                'PLANT_LABEL' => $row['_PLANT_LABEL'],
                'suppliers' => array()
            );
        }

        if (!isset($grouped[$plantKey]['suppliers'][$supKey])) {
            $grouped[$plantKey]['suppliers'][$supKey] = array(
                'SUP_CODE' => $supKey,
                'SUP_COMP' => trim((string)$row['SUP_COMP']),
                'items' => array()
            );
        }

        if (!isset($grouped[$plantKey]['suppliers'][$supKey]['items'][$itemKey])) {
            $grouped[$plantKey]['suppliers'][$supKey]['items'][$itemKey] = array(
                'ITEM_CODE' => $itemKey,
                'ITEM_NAME' => trim((string)$row['ITEM_NAME']),
                'pos' => array()
            );
        }

        if (
            !isset(
                $grouped[$plantKey]['suppliers'][$supKey]['items'][$itemKey]['pos'][$poKey]
            )
        ) {
            $poQty = isset($row['QTY']) ? (float)$row['QTY'] : 0;
            $poPrice = isset($row['POD_PRICE']) ? (float)$row['POD_PRICE'] : 0;

            $grouped[$plantKey]['suppliers'][$supKey]['items'][$itemKey]['pos'][$poKey] = array(
                'PO_NUM' => $poKey,
                'PO_DATE' => isset($row['PO_DATE']) ? $row['PO_DATE'] : '',
                'POD_PRICE' => $poPrice,
                'PO_CUR' => isset($row['PO_CUR']) ? trim((string)$row['PO_CUR']) : '',
                'QTY' => $poQty,
                'POD_UNIT' => isset($row['POD_UNIT']) ? trim((string)$row['POD_UNIT']) : '',
                'AMOUNT' => $poQty * $poPrice,
                'TOTAL_REC_QTY' => 0,
                'rcvs' => array()
            );

            $grandPoQty += $poQty;
            $grandPoAmount += $poQty * $poPrice;
        }

        $rcvNo = isset($row['RCV_NOMOR'])
            ? trim((string)$row['RCV_NOMOR'])
            : '';

        if ($rcvNo !== '') {
            $rcvQty = isset($row['RCVD_QTY']) ? (float)$row['RCVD_QTY'] : 0;
            $rcvPrice = isset($row['RCV_PRICE']) ? (float)$row['RCV_PRICE'] : 0;

            if (
                !isset(
                    $grouped[$plantKey]['suppliers'][$supKey]['items'][$itemKey]['pos'][$poKey]['rcvs'][$rcvNo]
                )
            ) {
                $grouped[$plantKey]['suppliers'][$supKey]['items'][$itemKey]['pos'][$poKey]['rcvs'][$rcvNo] = array(
                    'RCV_NOMOR' => $rcvNo,
                    'RCV_DATE' => isset($row['RCV_DATE']) ? $row['RCV_DATE'] : '',
                    'RCVD_QTY' => 0,
                    'RCV_PRICE' => $rcvPrice
                );
            }

            $grouped[$plantKey]['suppliers'][$supKey]['items'][$itemKey]['pos'][$poKey]['rcvs'][$rcvNo]['RCVD_QTY'] += $rcvQty;
            $grouped[$plantKey]['suppliers'][$supKey]['items'][$itemKey]['pos'][$poKey]['TOTAL_REC_QTY'] += $rcvQty;

            $grandRcvQty += $rcvQty;
            $grandRcvAmount += $rcvQty * $rcvPrice;
        }
    }

    $totalRows = count($dataRows);
}

if ($isExport) {
    $filename = 'Purchase_Year_' . strtoupper($plant) . '_' . date('Ymd') . '.xls';

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
}
?>
<?php if (!$isExport) { ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Year Gabungan</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <style>
        body{background:#f0f2f5;font-family:"Times New Roman",serif;font-size:13px}
        .param-overlay{position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:9999;display:flex;align-items:center;justify-content:center}
        .param-dialog{background:#fff;border-radius:14px;width:600px;max-width:95vw;box-shadow:0 25px 60px rgba(0,0,0,.25);overflow:visible}
        .param-dialog-header{padding:18px 25px;border-bottom:1px solid #e2e8f0;font-family:Arial,sans-serif}
        .param-dialog-body{padding:22px 25px;font-family:Arial,sans-serif}
        .param-dialog-footer{padding:15px 25px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px}
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
        .report-container{background:#fff;border:1px solid #d7dee7;padding:35px;margin:20px auto;max-width:1450px}
        .report-header{border-bottom:2px solid #000;padding-bottom:9px;margin-bottom:10px}
        .report-title{font:bold 20px Arial,sans-serif}
        .report-params{display:flex;justify-content:space-between;font:bold 13px Arial,sans-serif;margin-top:10px}
        .report-table{width:100%;border-collapse:collapse;font-size:12px}
        .report-table th{border-top:1px solid #000;border-bottom:1px solid #000;padding:7px 4px}
        .report-table td{padding:4px}
        .plant-row td{background:#dbeafe;font:bold 13px Arial,sans-serif;border-top:2px solid #2563eb;padding:7px}
        .supplier-row td{font-weight:bold;padding-top:8px}
        .item-row td{font-weight:bold;padding-left:12px}
        .summary-box{font-family:Arial,sans-serif;font-size:12px;background:#f8fafc;border:1px solid #e2e8f0;padding:8px;margin-top:12px}
        @media print{body{background:#fff}.no-print{display:none!important}.report-container{border:0;padding:0;margin:0;max-width:none}}
    </style>
</head>
<body>
<?php if (!$hasParams) { ?>
<div class="param-overlay">
    <div class="param-dialog">
        <div class="param-dialog-header">
            <strong>Purchase Year Report — P1 & P2</strong>
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
                    <label>Periode PO</label>
                    <div class="date-row">
                        <input type="date"
                               name="start_date"
                               value="<?php echo h($currentYear . '-01-01'); ?>"
                               class="form-control"
                               required>
                        <span>s/d</span>
                        <input type="date"
                               name="end_date"
                               value="<?php echo h(date('Y-m-d')); ?>"
                               class="form-control"
                               required>
                    </div>
                </div>

                <div class="param-field">
                    <label>Item</label>
                    <div class="ac-wrap" id="acWrapItem">
                        <input type="text"
                               id="modalItemInput"
                               class="form-control"
                               placeholder="Kosongkan untuk semua"
                               autocomplete="off">
                        <input type="hidden"
                               name="item_code"
                               id="modalItemHidden">
                        <div class="ac-list" id="modalItemDropdown"></div>
                    </div>
                </div>

                <div class="param-field">
                    <label>Supplier</label>
                    <div class="ac-wrap" id="acWrapSup">
                        <input type="text"
                               id="modalSupInput"
                               class="form-control"
                               placeholder="Kosongkan untuk semua"
                               autocomplete="off">
                        <input type="hidden"
                               name="sup_code"
                               id="modalSupHidden">
                        <div class="ac-list" id="modalSupDropdown"></div>
                    </div>
                </div>
            </form>
        </div>

        <div class="param-dialog-footer">
            <button type="submit"
                    form="paramForm"
                    class="btn btn-primary btn-sm">
                <i class="bi bi-search"></i> View Report
            </button>
        </div>
    </div>
</div>
<?php } ?>

<?php if ($hasParams) { ?>
<nav class="navbar bg-white border-bottom fixed-top no-print">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1 fs-6 fw-bold">PURCHASE YEAR</span>
        <div>
            <a href="?" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-funnel"></i> Parameter
            </a>

            <a class="btn btn-success btn-sm"
               href="?plant=<?php echo urlencode($plant); ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&item_code=<?php echo urlencode($itemCode); ?>&sup_code=<?php echo urlencode($supCode); ?>&export=excel">
                <i class="bi bi-file-earmark-excel"></i> Excel
            </a>

            <button type="button"
                    class="btn btn-primary btn-sm"
                    onclick="window.print();">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>
    </div>
</nav>

<div style="height:65px"></div>

<?php if (count($errors) > 0) { ?>
<div class="container-fluid no-print">
    <div class="alert alert-warning">
        <?php foreach ($errors as $error) { ?>
            <div><?php echo h($error); ?></div>
        <?php } ?>
    </div>
</div>
<?php } ?>
<?php } ?>
<?php } ?>

<?php if ($hasParams) { ?>
<?php if (!$isExport) { ?><main class="container-fluid"><div class="report-container"><?php } ?>

<div class="report-header">
    <div class="report-title">PURCHASE YEAR</div>

    <div class="report-params">
        <div>Plant: <?php echo h(plantDisplay($plant)); ?></div>
        <div>From: <?php echo h(formatDatePO($startDate)); ?></div>
        <div>To: <?php echo h(formatDatePO($endDate)); ?></div>
    </div>
</div>

<?php if ($isExport) { ?>
<table border="0">
    <tr>
        <td colspan="10" style="font-size:18px;font-weight:bold;text-align:center">
            PURCHASE YEAR
        </td>
    </tr>
    <tr>
        <td colspan="10" style="text-align:center">
            <?php echo h(plantDisplay($plant)); ?> |
            <?php echo h(formatDatePO($startDate)); ?> s/d
            <?php echo h(formatDatePO($endDate)); ?>
        </td>
    </tr>
</table>
<?php } ?>

<?php if ($totalRows > 0) { ?>
<table class="report-table" <?php echo $isExport ? 'border="1"' : ''; ?>>
    <thead>
    <tr>
        <th style="text-align:left;width:25%">Dokumen / Item / Supplier</th>
        <th style="text-align:center">Date</th>
        <th style="text-align:right">Price</th>
        <th style="text-align:left">Curr</th>
        <th style="text-align:right">PO Qty</th>
        <th style="text-align:right">Rec Qty</th>
        <th style="text-align:right">Rec Price</th>
        <th style="text-align:right">Outstanding</th>
        <th style="text-align:center">Unit</th>
        <th style="text-align:right">Amount</th>
    </tr>
    </thead>

    <tbody>
    <?php foreach ($grouped as $plantGroup) { ?>
        <tr class="plant-row">
            <td colspan="10">
                <?php echo h($plantGroup['PLANT_SHORT'] . ' - ' . $plantGroup['PLANT_LABEL']); ?>
            </td>
        </tr>

        <?php foreach ($plantGroup['suppliers'] as $supplier) { ?>
            <tr class="supplier-row">
                <td colspan="10">
                    <?php echo h($supplier['SUP_CODE'] . ' ' . $supplier['SUP_COMP']); ?>
                </td>
            </tr>

            <?php foreach ($supplier['items'] as $item) { ?>
                <tr class="item-row">
                    <td colspan="10">
                        <?php echo h($item['ITEM_CODE'] . ' ' . $item['ITEM_NAME']); ?>
                    </td>
                </tr>

                <?php foreach ($item['pos'] as $po) { ?>
                    <?php $outstanding = $po['QTY'] - $po['TOTAL_REC_QTY']; ?>
                    <tr>
                        <td style="font-weight:bold"><?php echo h($po['PO_NUM']); ?></td>
                        <td style="text-align:center;font-weight:bold"><?php echo h(formatDatePO($po['PO_DATE'])); ?></td>
                        <td style="text-align:right"><?php echo h(formatMoney($po['POD_PRICE'])); ?></td>
                        <td style="padding-left:10px"><?php echo h($po['PO_CUR']); ?></td>
                        <td style="color:blue;font-weight:bold;text-align:right"><?php echo h(formatPOQty($po['QTY'])); ?></td>
                        <td style="color:red;font-weight:bold;text-align:right"><?php echo h(formatQty($po['TOTAL_REC_QTY'])); ?></td>
                        <td></td>
                        <td style="color:green;font-weight:bold;text-align:right"><?php echo h(formatQty($outstanding)); ?></td>
                        <td style="text-align:center"><?php echo h($po['POD_UNIT']); ?></td>
                        <td style="text-align:right"><?php echo h(formatMoney($po['AMOUNT'])); ?></td>
                    </tr>

                    <?php foreach ($po['rcvs'] as $receive) { ?>
                    <tr>
                        <td style="color:blue;font-weight:bold;padding-left:10px">
                            <?php echo h($receive['RCV_NOMOR']); ?>
                        </td>
                        <td style="text-align:center">
                            <?php echo h(formatDate($receive['RCV_DATE'])); ?>
                        </td>
                        <td></td><td></td><td></td>
                        <td style="text-align:right;font-weight:bold">
                            <?php echo h(formatQty($receive['RCVD_QTY'])); ?>
                        </td>
                        <td style="text-align:right">
                            <?php echo h(formatMoney($receive['RCV_PRICE'])); ?>
                        </td>
                        <td></td><td></td><td></td>
                    </tr>
                    <?php } ?>
                <?php } ?>
            <?php } ?>
        <?php } ?>
    <?php } ?>
    </tbody>
</table>

<div class="summary-box">
    Total baris: <strong><?php echo $totalRows; ?></strong>
    &nbsp; | &nbsp; PO Qty:
    <strong><?php echo h(formatPOQty($grandPoQty)); ?></strong>
    &nbsp; | &nbsp; Receive Qty:
    <strong><?php echo h(formatQty($grandRcvQty)); ?></strong>
    &nbsp; | &nbsp; PO Amount:
    <strong><?php echo h(formatMoney($grandPoAmount)); ?></strong>
    &nbsp; | &nbsp; Receive Amount:
    <strong><?php echo h(formatMoney($grandRcvAmount)); ?></strong>
</div>
<?php } else { ?>
<div style="text-align:center;padding:35px;color:#64748b">
    Tidak ada data untuk kriteria ini.
</div>
<?php } ?>

<?php if (!$isExport) { ?></div></main><?php } ?>
<?php } ?>

<?php if (!$isExport) { ?>
<script>
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
        dropdown.innerHTML =
            '<div style="padding:10px;text-align:center;color:#94a3b8">Mencari...</div>';
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
                dropdown.innerHTML =
                    '<div style="padding:10px;text-align:center;color:#94a3b8">Tidak ditemukan</div>';
                return;
            }

            var html = '';

            for (var i = 0; i < data.length; i++) {
                var row = data[i];
                var code = escapeHtml(row[config.codeField] || '');
                var name = escapeHtml(row[config.nameField] || '');
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
            dropdown.innerHTML =
                '<div style="padding:10px;text-align:center;color:#b91c1c">Gagal memuat data</div>';
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
    inputId: 'modalItemInput',
    hiddenId: 'modalItemHidden',
    dropdownId: 'modalItemDropdown',
    wrapId: 'acWrapItem',
    url: 'search_item_multi.php',
    codeField: 'ITEM_CODE',
    nameField: 'ITEM_NAME'
});

createAutocomplete({
    inputId: 'modalSupInput',
    hiddenId: 'modalSupHidden',
    dropdownId: 'modalSupDropdown',
    wrapId: 'acWrapSup',
    url: 'search_sup_multi.php',
    codeField: 'SUP_CODE',
    nameField: 'SUP_COMP'
});
</script>
</body>
</html>
<?php } ?>
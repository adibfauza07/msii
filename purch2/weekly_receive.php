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

    usort($dataRows, function ($a, $b) {
        $fields = array(
            '_PLANT_SHORT',
            'SUP_CODE',
            'PO_NUM',
            'ITEM_CODE',
            'RCV_DATE'
        );

        foreach ($fields as $field) {
            $left = isset($a[$field])
                ? sortableValue($a[$field])
                : '';

            $right = isset($b[$field])
                ? sortableValue($b[$field])
                : '';

            $compare = strcmp($left, $right);

            if ($compare !== 0) {
                return $compare;
            }
        }

        return 0;
    });

    // GROUPING: Plant -> Supplier -> PO -> Item -> Receive Date
    foreach ($dataRows as $row) {
        $plantKey = $row['_PLANT_KEY'];
        $supKey = trim((string)$row['SUP_CODE']);
        $poKey = trim((string)$row['PO_NUM']);
        $itemKey = trim((string)$row['ITEM_CODE']);

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
                'pos' => array()
            );
        }

        if (!isset($grouped[$plantKey]['suppliers'][$supKey]['pos'][$poKey])) {
            $grouped[$plantKey]['suppliers'][$supKey]['pos'][$poKey] = array(
                'PO_NUM' => $poKey,
                'items' => array()
            );
        }

        if (
            !isset(
                $grouped[$plantKey]['suppliers'][$supKey]['pos'][$poKey]['items'][$itemKey]
            )
        ) {
            $grouped[$plantKey]['suppliers'][$supKey]['pos'][$poKey]['items'][$itemKey] = array(
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
            $rcvDateKey = isset($row['RCV_DATE'])
                ? (string)$row['RCV_DATE']
                : '';
        }

        $receiveQty = isset($row['RQty']) ? (float)$row['RQty'] : 0;
        $price = isset($row['PRICE']) ? (float)$row['PRICE'] : 0;

        if (
            !isset(
                $grouped[$plantKey]['suppliers'][$supKey]['pos'][$poKey]['items'][$itemKey]['dates'][$rcvDateKey]
            )
        ) {
            $grouped[$plantKey]['suppliers'][$supKey]['pos'][$poKey]['items'][$itemKey]['dates'][$rcvDateKey] = array(
                'RCV_DATE' => isset($row['RCV_DATE']) ? $row['RCV_DATE'] : '',
                'RQty' => 0,
                'AMOUNT' => 0
            );
        }

        $grouped[$plantKey]['suppliers'][$supKey]['pos'][$poKey]['items'][$itemKey]['dates'][$rcvDateKey]['RQty'] += $receiveQty;
        $grouped[$plantKey]['suppliers'][$supKey]['pos'][$poKey]['items'][$itemKey]['dates'][$rcvDateKey]['AMOUNT'] += $receiveQty * $price;
        $grouped[$plantKey]['suppliers'][$supKey]['pos'][$poKey]['items'][$itemKey]['TOTAL_RECEIVE'] += $receiveQty;
    }

    $totalRows = count($dataRows);
}

if ($isExport) {
    $filename = 'Weekly_Receive_' . strtoupper($plant) . '_' . date('Ymd') . '.xls';

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
    <title>Weekly Receive Gabungan</title>

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
        .report-container{background:#fff;border:1px solid #d7dee7;padding:35px;margin:20px auto;max-width:1450px}
        .company-name{font:bold 14px Arial,sans-serif}
        .report-header{text-align:center;margin-bottom:20px}
        .report-title{font:bold 20px Arial,sans-serif;text-decoration:underline}
        .report-params{font:bold 13px Arial,sans-serif;margin-top:7px}
        .report-table{width:100%;border-collapse:collapse;font-size:13px}
        .report-table th{border-top:1px solid #000;border-bottom:1px solid #000;padding:6px 2px}
        .report-table td{padding:3px 2px}
        .plant-row td{background:#dbeafe;font:bold 13px Arial,sans-serif;border-top:2px solid #2563eb;padding:7px}
        .supplier-row td{font:bold 12px Arial,sans-serif;padding-top:9px}
        .po-row td{font:bold 12px Arial,sans-serif;padding-top:5px}
        @media print{body{background:#fff}.no-print{display:none!important}.report-container{border:0;padding:0;margin:0;max-width:none}}
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

                <div class="param-field">
                    <label>Item Name</label>
                    <input type="text"
                           name="item_name"
                           class="form-control"
                           placeholder="Keyword nama item">
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
        <span class="navbar-brand mb-0 h1 fs-6 fw-bold">
            WEEKLY RECEIVE MATERIAL
        </span>

        <div>
            <a href="?" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-funnel"></i> Parameter
            </a>

            <a class="btn btn-success btn-sm"
               href="?plant=<?php echo urlencode($plant); ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&sup_code=<?php echo urlencode($supCode); ?>&item_name=<?php echo urlencode($itemName); ?>&export=excel">
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

<div class="company-name">PT.IMC TEKNO INDONESIA</div>

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

    <tbody>
    <?php if (count($grouped) > 0) { ?>
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

                <?php foreach ($supplier['pos'] as $po) { ?>
                    <tr class="po-row">
                        <td colspan="10"><?php echo h($po['PO_NUM']); ?></td>
                    </tr>

                    <?php foreach ($po['items'] as $item) { ?>
                        <?php
                        $outstanding = $item['POD_QTY'] - $item['TOTAL_RECEIVE'];
                        $outstandingDisplay = $outstanding <= 0
                            ? '-'
                            : formatQty($outstanding);
                        ?>
                        <tr>
                            <td style="padding-left:5px"><?php echo h($item['ITEM_CODE']); ?></td>
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
        <?php } ?>
    <?php } else { ?>
        <tr>
            <td colspan="10" style="text-align:center;padding:35px;color:#64748b">
                Tidak ada data untuk kriteria ini.
            </td>
        </tr>
    <?php } ?>
    </tbody>
</table>

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
    inputId: 'modalSupInput',
    hiddenId: 'modalSupHidden',
    dropdownId: 'modalSupDropdown',
    wrapId: 'acWrapSup',
    url: 'search_sup_multi.php'
});
</script>
</body>
</html>
<?php } ?>
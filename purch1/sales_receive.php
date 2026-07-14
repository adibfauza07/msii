<?php
// ============================================================
// MATERIAL SALES VS RECEIVE - TAMPILAN REPORT BULANAN
// Kompatibel PHP 5.4 + SQL Server 2008
// Stored procedure: dbo.sp_mat_sales_receive
// ============================================================
set_time_limit(120);
session_start();

require_once __DIR__ . "/../config/database_ordering.php";

if (!isset($_SESSION['db_user']) || $_SESSION['db_user'] == '') {
    die('<div style="padding:24px;color:#b91c1c;background:#fff;font-family:Arial,sans-serif;">Silakan login terlebih dahulu.</div>');
}

$uid = $_SESSION['db_user'];
$pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : '';
$dbName = 'msData';

$servers_config = array(
    'p1' => array('ip' => '192.168.0.4', 'label' => 'Plant 1', 'short' => 'P1'),
    'p2' => array('ip' => '192.168.0.9', 'label' => 'Plant 2', 'short' => 'P2')
);

function json_output($data)
{
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

function valid_month_ym($value)
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}$/', $value)) {
        return false;
    }

    $parts = explode('-', $value);
    if (count($parts) != 2) {
        return false;
    }

    $year = (int)$parts[0];
    $month = (int)$parts[1];

    return ($year >= 1900 && $year <= 2100 && $month >= 1 && $month <= 12);
}

function month_label_id($value)
{
    $monthNames = array(
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    );

    $parts = explode('-', $value);
    $year = isset($parts[0]) ? (int)$parts[0] : 0;
    $month = isset($parts[1]) ? (int)$parts[1] : 0;

    if (!isset($monthNames[$month])) {
        return $value;
    }

    return $monthNames[$month] . ' ' . $year;
}

function sqlsrv_error_text()
{
    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    if (!is_array($errors) || count($errors) == 0) {
        return 'Kesalahan SQL Server tidak diketahui.';
    }

    $messages = array();
    foreach ($errors as $error) {
        $code = isset($error['code']) ? $error['code'] : '';
        $message = isset($error['message']) ? $error['message'] : '';
        $messages[] = trim($code . ' ' . $message);
    }

    return implode(' | ', $messages);
}

function normalize_currency($currency)
{
    return strtoupper(trim((string)$currency));
}

if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
    $plant = isset($_GET['plant']) ? strtolower(trim($_GET['plant'])) : 'all';
    if (!in_array($plant, array('p1', 'p2', 'all'), true)) {
        $plant = 'all';
    }

    $periodMonth = isset($_GET['month']) ? trim($_GET['month']) : date('Y-m');

    if (!valid_month_ym($periodMonth)) {
        json_output(array(
            'status' => 'error',
            'message' => 'Format bulan harus YYYY-MM.'
        ));
    }

    $fromDate = $periodMonth . '-01';
    $toDate = date('Y-m-t', strtotime($periodMonth . '-01'));
    $fromDateSql = $fromDate . ' 00:00:00';
    $toDateSql = $toDate . ' 23:59:59';

    $connOptions = array(
        'Database' => $dbName,
        'Uid' => $uid,
        'PWD' => $pwd,
        'CharacterSet' => 'UTF-8',
        'LoginTimeout' => 5
    );

    $serversToTry = ($plant === 'all') ? array('p1', 'p2') : array($plant);
    $rows = array();
    $errors = array();
    $serverStatus = array('p1' => false, 'p2' => false);

    foreach ($serversToTry as $serverKey) {
        $serverLabel = isset($servers_config[$serverKey]['label'])
            ? $servers_config[$serverKey]['label']
            : strtoupper($serverKey);

        $serverShort = isset($servers_config[$serverKey]['short'])
            ? $servers_config[$serverKey]['short']
            : strtoupper($serverKey);

        $conn = @sqlsrv_connect($servers_config[$serverKey]['ip'], $connOptions);
        if (!$conn) {
            $errors[] = $serverLabel . ': koneksi gagal. ' . sqlsrv_error_text();
            continue;
        }

        $serverStatus[$serverKey] = true;

        $sql = 'EXEC dbo.sp_mat_sales_receive @from_date = ?, @to_date = ?';
        $params = array($fromDateSql, $toDateSql);
        $stmt = @sqlsrv_query($conn, $sql, $params);

        if (!$stmt) {
            $errors[] = $serverLabel . ': stored procedure gagal. ' . sqlsrv_error_text();
            sqlsrv_close($conn);
            continue;
        }

        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $sales = isset($row['SALES_MAT']) ? (float)$row['SALES_MAT'] : 0;
            $tags = isset($row['TAGS']) ? (float)$row['TAGS'] : 0;
            $mrp = isset($row['MRP1']) ? (float)$row['MRP1'] : 0;
            $pdQty = isset($row['PD_QTY']) ? (float)$row['PD_QTY'] : 0;
            $prodVendor = isset($row['PROD_VENDOR']) ? (float)$row['PROD_VENDOR'] : 0;
            $receive = isset($row['REC_QTY']) ? (float)$row['REC_QTY'] : 0;
            $price = isset($row['PRICE']) ? (float)$row['PRICE'] : 0;
            $currency = isset($row['CURR']) ? normalize_currency($row['CURR']) : '';
            $rate = isset($row['CURR_VRATE']) ? (float)$row['CURR_VRATE'] : 0;

            if ($currency == '' || $currency == 'IDR' || $currency == 'RP') {
                $effectiveRate = 1;
            } else {
                $effectiveRate = $rate;
            }

            $tagsSales = $tags - $sales;
            $prodActual = $pdQty + $prodVendor;
            $balance = $tagsSales + $receive;
            $balanceAmount = $balance * $price * $effectiveRate;

            $record = array(
                'plant' => $serverKey,
                'plant_short' => $serverShort,
                'plant_label' => $serverLabel,
                'mat_id' => isset($row['MAT_ID']) ? $row['MAT_ID'] : null,
                'mat_code' => isset($row['MAT_CODE']) ? trim((string)$row['MAT_CODE']) : '',
                'mat_name' => isset($row['MAT_NAME']) ? trim((string)$row['MAT_NAME']) : '',
                'supp_code' => isset($row['SUPP_CODE']) ? trim((string)$row['SUPP_CODE']) : '',
                'supplier' => isset($row['SUPPLIER']) ? trim((string)$row['SUPPLIER']) : '',
                'price' => $price,
                'curr' => $currency,
                'curr_vrate' => $rate,
                'sales' => $sales,
                'tags' => $tags,
                'tags_sales' => $tagsSales,
                'mrp' => $mrp,
                'prod_actual' => $prodActual,
                'pd_qty' => $pdQty,
                'prod_vendor' => $prodVendor,
                'receive' => $receive,
                'balance' => $balance,
                'balance_amount' => $balanceAmount,
                'rate_missing' => (($currency != '' && $currency != 'IDR' && $currency != 'RP' && $rate <= 0) ? 1 : 0)
            );

            $rows[] = $record;
        }

        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);
    }

    usort($rows, function ($a, $b) {
        $plantCompare = strcmp($a['plant_short'], $b['plant_short']);
        if ($plantCompare !== 0) {
            return $plantCompare;
        }

        $supplierCompare = strcmp($a['supp_code'], $b['supp_code']);
        if ($supplierCompare !== 0) {
            return $supplierCompare;
        }

        return strcmp($a['mat_code'], $b['mat_code']);
    });

    $totals = array(
        'sales' => 0,
        'tags' => 0,
        'tags_sales' => 0,
        'mrp' => 0,
        'prod_actual' => 0,
        'receive' => 0,
        'balance' => 0,
        'balance_amount' => 0
    );

    $missingRateCount = 0;
    foreach ($rows as $record) {
        foreach ($totals as $key => $value) {
            $totals[$key] += isset($record[$key]) ? (float)$record[$key] : 0;
        }
        if (!empty($record['rate_missing'])) {
            $missingRateCount++;
        }
    }

    $percentages = array(
        'receive_sales' => ($totals['sales'] != 0 ? ($totals['receive'] / $totals['sales']) * 100 : null),
        'receive_mrp' => ($totals['mrp'] != 0 ? ($totals['receive'] / $totals['mrp']) * 100 : null),
        'production_sales' => ($totals['sales'] != 0 ? ($totals['prod_actual'] / $totals['sales']) * 100 : null)
    );

    if ($missingRateCount > 0) {
        $errors[] = $missingRateCount . ' baris mata uang asing belum memiliki kurs; Balance Amount baris tersebut menjadi 0.';
    }

    json_output(array(
        'status' => 'success',
        'rows' => $rows,
        'totals' => $totals,
        'percentages' => $percentages,
        'errors' => $errors,
        'servers' => $serverStatus,
        'month' => $periodMonth,
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'period_label' => month_label_id($periodMonth),
        'plant' => $plant
    ));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Material vs Receive Material</title>
    <style>
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;background:#dbe7f4;color:#000;font-family:Arial,Helvetica,sans-serif;font-size:12px}
        button,input,select{font-family:Arial,Helvetica,sans-serif}

        .toolbar{background:linear-gradient(#f8fbff,#d8e7f8);border-bottom:1px solid #8094aa;padding:8px 10px;display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap;position:sticky;top:0;z-index:50;box-shadow:0 2px 5px rgba(0,0,0,.12)}
        .field{display:flex;flex-direction:column;gap:3px}
        .field label{font-size:10px;font-weight:bold;color:#34495e;text-transform:uppercase}
        .field select,.field input{height:30px;border:1px solid #8fa7c0;background:#fff;padding:0 8px;min-width:145px}
        .btn{height:30px;border:1px solid #7d94ad;border-radius:3px;background:linear-gradient(#fff,#dce8f4);padding:0 14px;font-weight:bold;cursor:pointer;color:#1f3e5a}
        .btn:hover{background:linear-gradient(#fff,#cbdff2)}
        .btn-primary{background:linear-gradient(#ffefb6,#f2c34d);border-color:#b98a21;color:#594100}
        .btn-danger{background:linear-gradient(#fff,#f8cccc);border-color:#d38484;color:#7f1d1d}
        .toolbar-spacer{flex:1}
        .toolbar-info{height:30px;display:flex;align-items:center;padding:0 10px;border:1px solid #a4b5c8;background:#fff;font-weight:bold;color:#24415e}

        .status{display:none;margin:8px;border:1px solid #d49a34;background:#fff8df;color:#7a4d00;padding:8px 12px;line-height:1.5}
        .status.show{display:block}

        .viewer{display:grid;grid-template-columns:245px minmax(900px,1fr);height:calc(100vh - 47px);min-height:500px}
        .sidebar{background:#eef4fb;border-right:1px solid #8fa7c0;overflow:auto}
        .side-head{padding:9px 10px;background:linear-gradient(#fff,#cbdcf0);border-bottom:1px solid #8fa7c0;font-weight:bold;color:#24415e;position:sticky;top:0;z-index:2}
        .supplier-list{padding:5px 0}
        .supplier-item{display:block;width:100%;border:0;background:transparent;text-align:left;padding:5px 9px;font-size:11px;cursor:pointer;color:#0d3f78;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .supplier-item:hover,.supplier-item.active{background:#c7daf0;color:#001f45;font-weight:bold}
        .supplier-item:before{content:'└';display:inline-block;width:14px;color:#777}
        .supplier-item.all:before{content:'▾'}
        .supplier-count{float:right;color:#667;font-size:10px}

        .report-pane{overflow:auto;padding:8px;background:#d5d5d5}
        .loading{display:none;position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);background:#fff;border:2px solid #365f8b;box-shadow:0 5px 20px rgba(0,0,0,.25);padding:18px 28px;font-weight:bold;color:#24415e;z-index:100}
        .loading.show{display:block}

        .report-sheet{background:#fff;border:3px solid #111;min-width:1280px;max-width:1500px;margin:0 auto;padding:28px 28px 34px;box-shadow:0 2px 8px rgba(0,0,0,.25)}
        .report-header{display:grid;grid-template-columns:1fr 410px;gap:30px;align-items:start}
        .company{font-family:"Arial Black",Arial,sans-serif;font-size:19px;font-weight:900;letter-spacing:.2px;line-height:1.15}
        .report-title{font-family:"Arial Black",Arial,sans-serif;font-size:18px;font-weight:900;letter-spacing:.3px;line-height:1.15}
        .report-sub{margin-top:9px;font-size:11px}
        .period-grid{display:grid;grid-template-columns:60px 1fr;gap:4px;margin-left:15px}
        .period-grid b{font-weight:bold}
        .metric-table{width:100%;border-collapse:collapse;font-weight:bold;font-size:12px;margin-top:6px}
        .metric-table td{padding:2px 4px}
        .metric-table td:nth-child(2){text-align:right;width:90px}
        .metric-table td:nth-child(3){width:25px}

        .report-month-center{text-align:center;font-weight:bold;margin-top:-15px;margin-bottom:2px}
        .report-table{width:100%;border-collapse:collapse;font-family:"Times New Roman",serif;font-size:10.5px;table-layout:fixed}
        .report-table col.code{width:70px}.report-table col.name{width:210px}.report-table col.price{width:70px}.report-table col.curr{width:42px}
        .report-table col.qty{width:75px}.report-table col.prod{width:105px}.report-table col.receive{width:65px}.report-table col.balance{width:64px}.report-table col.amount{width:100px}
        .report-table thead th{font-family:"Times New Roman",serif;font-size:10px;padding:3px 4px;border-bottom:2px solid #111;text-align:center;white-space:nowrap;vertical-align:bottom}
        .report-table thead th.left{text-align:left}
        .report-table tbody td{padding:2px 4px;vertical-align:top;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .report-table tbody td.num{text-align:right;font-variant-numeric:tabular-nums}
        .report-table tbody td.center{text-align:center}
        .supplier-row td{font-family:"Arial Narrow",Arial,sans-serif!important;font-weight:bold;font-size:11px!important;padding-top:7px!important;border-top:1px solid #aaa;overflow:visible!important}
        .supplier-row:first-child td{border-top:0}
        .receive-cell{color:#ff0000;font-weight:bold}
        .negative{color:#0000cc;font-weight:bold}
        .rate-warning{background:#fff1b8}
        .grand-total td{border-top:2px solid #111;border-bottom:2px solid #111;font-weight:bold;padding-top:4px!important;padding-bottom:4px!important}
        .no-data{text-align:center;padding:35px!important;font-family:Arial,sans-serif!important;color:#666}
        .plant-chip{font-family:Arial,sans-serif;font-size:8px;border:1px solid #7894b2;background:#edf5ff;border-radius:2px;padding:0 3px;margin-right:3px;color:#264a70}
        .report-footer{display:flex;justify-content:space-between;margin-top:9px;font-size:9px;color:#555}

        @media(max-width:900px){
            .viewer{grid-template-columns:1fr;height:auto}.sidebar{max-height:180px;border-right:0;border-bottom:1px solid #8fa7c0}.report-pane{min-height:600px}
        }

        @media print{
            @page{size:A3 landscape;margin:8mm}
            html,body{background:#fff}
            .toolbar,.sidebar,.status,.loading{display:none!important}
            .viewer{display:block;height:auto}
            .report-pane{overflow:visible;padding:0;background:#fff}
            .report-sheet{border:0;box-shadow:none;min-width:0;max-width:none;width:100%;padding:0}
            .report-table{font-size:8px}
            .report-table thead th{font-size:8px}
            .report-table tbody td{padding:1px 3px}
            .supplier-row td{font-size:9px!important}
            .report-footer{display:none}
        }
    </style>
</head>
<body>
<div class="toolbar">
    <div class="field">
        <label>Plant</label>
        <select id="plant">
            <option value="all">Gabungan P1 &amp; P2</option>
            <option value="p1">Plant 1</option>
            <option value="p2">Plant 2</option>
        </select>
    </div>
    <div class="field">
        <label>Bulan</label>
        <input type="month" id="periodMonth" value="<?php echo htmlspecialchars(date('Y-m'), ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <button type="button" class="btn btn-primary" onclick="loadData()">Preview</button>
    <button type="button" class="btn" onclick="window.print()">Print</button>
    <button type="button" class="btn" onclick="exportCsv()">Export CSV</button>
    <button type="button" class="btn" onclick="showAllSuppliers()">Semua Supplier</button>
    <div class="toolbar-spacer"></div>
    <div class="toolbar-info" id="pageInfo">Belum ada data</div>
</div>

<div class="status" id="statusBox"></div>
<div class="loading" id="loading">Menarik data laporan...</div>

<div class="viewer">
    <aside class="sidebar">
        <div class="side-head">Daftar Supplier</div>
        <div class="supplier-list" id="supplierList">
            <button type="button" class="supplier-item all active">Semua Supplier</button>
        </div>
    </aside>

    <main class="report-pane">
        <section class="report-sheet" id="reportSheet">
            <div class="report-header">
                <div>
                    <div class="company">PT.IMC TEKNO INDONESIA</div>
                    <div class="report-title">SALES MATERIAL VS RECEIVE MATERIAL</div>
                    <div class="report-sub">
                        <div class="period-grid">
                            <b>MONTH:</b><span id="monthLeft">-</span>
                            <b>PLANT:</b><span id="plantText">-</span>
                        </div>
                    </div>
                </div>
                <div>
                    <table class="metric-table">
                        <tr><td>% RECEIVE / SALES</td><td id="pctSales">-</td><td>%</td></tr>
                        <tr><td>% RECEIVE / MRP</td><td id="pctMrp">-</td><td>%</td></tr>
                        <tr><td>% PROD ACTUAL / SALES</td><td id="pctProduction">-</td><td>%</td></tr>
                    </table>
                </div>
            </div>

            <div class="report-month-center" id="monthCenter">-</div>

            <table class="report-table">
                <colgroup>
                    <col class="code"><col class="name"><col class="price"><col class="curr">
                    <col class="qty"><col class="qty"><col class="qty"><col class="qty">
                    <col class="prod"><col class="receive"><col class="balance"><col class="amount">
                </colgroup>
                <thead>
                    <tr>
                        <th class="left">CODE</th>
                        <th class="left">ITEM_NAME</th>
                        <th>Price</th>
                        <th>Curr</th>
                        <th>SALES MATERIAL</th>
                        <th>TAGS</th>
                        <th>TAGS-SALES</th>
                        <th>MRP</th>
                        <th>KONVERSI PROD ACTUAL</th>
                        <th>RECEIVE</th>
                        <th>BAL</th>
                        <th>BALAMOUNT</th>
                    </tr>
                </thead>
                <tbody id="tbody">
                    <tr><td colspan="12" class="no-data">Pilih bulan kemudian klik Preview.</td></tr>
                </tbody>
            </table>

            <div class="report-footer">
                <span id="generatedText">-</span>
                <span>Material Sales vs Receive</span>
            </div>
        </section>
    </main>
</div>

<script type="text/javascript">
var ALL_ROWS = [];
var VISIBLE_ROWS = [];
var LAST_PERIOD = '';
var LAST_PERIOD_LABEL = '';
var LAST_PLANT = 'all';
var ACTIVE_SUPPLIER_KEY = '';

function esc(value) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(value === null || typeof value === 'undefined' ? '' : String(value)));
    return div.innerHTML;
}

function isNearZero(value) {
    return Math.abs(Number(value || 0)) < 0.0000001;
}

function formatQty(value) {
    var n = Number(value || 0);
    if (isNearZero(n)) {
        return '-';
    }
    return n.toLocaleString('en-US', {minimumFractionDigits:0, maximumFractionDigits:3});
}

function formatPrice(value) {
    var n = Number(value || 0);
    if (isNearZero(n)) {
        return '-';
    }
    return n.toLocaleString('en-US', {minimumFractionDigits:0, maximumFractionDigits:2});
}

function formatAmount(value) {
    var n = Number(value || 0);
    if (isNearZero(n)) {
        return '-';
    }
    return Math.round(n).toLocaleString('en-US');
}

function formatPercent(value) {
    if (value === null || typeof value === 'undefined' || isNaN(Number(value))) {
        return '-';
    }
    return Number(value).toFixed(2);
}

function valueClass(value, extraClass) {
    var classes = extraClass ? extraClass : '';
    if (Number(value || 0) < 0) {
        classes += (classes ? ' ' : '') + 'negative';
    }
    return classes;
}

function showStatus(messages) {
    var box = document.getElementById('statusBox');
    if (!messages || messages.length === 0) {
        box.className = 'status';
        box.innerHTML = '';
        return;
    }
    box.className = 'status show';
    var html = '';
    var i;
    for (i = 0; i < messages.length; i++) {
        html += '<div>' + esc(messages[i]) + '</div>';
    }
    box.innerHTML = html;
}

function setLoading(show) {
    document.getElementById('loading').className = show ? 'loading show' : 'loading';
}

function loadData() {
    var plant = document.getElementById('plant').value;
    var periodMonth = document.getElementById('periodMonth').value;

    if (!periodMonth) {
        showStatus(['Bulan wajib dipilih.']);
        return;
    }

    setLoading(true);
    showStatus([]);

    var url = '?action=fetch&plant=' + encodeURIComponent(plant) + '&month=' + encodeURIComponent(periodMonth);
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) {
            return;
        }

        setLoading(false);

        if (xhr.status < 200 || xhr.status >= 300) {
            showStatus(['HTTP ' + xhr.status + ': gagal mengambil data.']);
            return;
        }

        var data;
        try {
            data = JSON.parse(xhr.responseText);
        } catch (error) {
            showStatus(['Respons JSON tidak valid: ' + error.message]);
            return;
        }

        if (!data || data.status !== 'success') {
            showStatus([data && data.message ? data.message : 'Respons server tidak valid.']);
            return;
        }

        ALL_ROWS = data.rows || [];
        LAST_PERIOD = data.month || periodMonth;
        LAST_PERIOD_LABEL = data.period_label || periodMonth;
        LAST_PLANT = plant;
        ACTIVE_SUPPLIER_KEY = '';

        document.getElementById('monthLeft').innerHTML = esc(LAST_PERIOD_LABEL);
        document.getElementById('monthCenter').innerHTML = esc(LAST_PERIOD_LABEL);
        document.getElementById('plantText').innerHTML = plant === 'all' ? 'P1 & P2' : plant.toUpperCase();
        document.getElementById('generatedText').innerHTML = 'Dicetak: ' + esc(new Date().toLocaleString('id-ID'));

        renderPercentages(data.percentages || {});
        buildSupplierList(ALL_ROWS);
        renderReport(ALL_ROWS);
        showStatus(data.errors || []);
    };
    xhr.send(null);
}

function renderPercentages(percentages) {
    document.getElementById('pctSales').innerHTML = formatPercent(percentages.receive_sales);
    document.getElementById('pctMrp').innerHTML = formatPercent(percentages.receive_mrp);
    document.getElementById('pctProduction').innerHTML = formatPercent(percentages.production_sales);
}

function supplierKey(row) {
    return String(row.plant || '') + '|' + String(row.supp_code || '') + '|' + String(row.supplier || '');
}

function buildSupplierList(rows) {
    var groups = {};
    var order = [];
    var i;

    for (i = 0; i < rows.length; i++) {
        var row = rows[i];
        var key = supplierKey(row);
        if (!groups[key]) {
            groups[key] = {
                key: key,
                plant_short: row.plant_short || '',
                supp_code: row.supp_code || '-',
                supplier: row.supplier || '(Tanpa nama supplier)',
                count: 0
            };
            order.push(key);
        }
        groups[key].count++;
    }

    var html = '<button type="button" class="supplier-item all active" data-key="" onclick="filterSupplier(\'\')">Semua Supplier<span class="supplier-count">' + rows.length + '</span></button>';
    for (i = 0; i < order.length; i++) {
        var g = groups[order[i]];
        var label = (LAST_PLANT === 'all' ? '[' + g.plant_short + '] ' : '') + g.supp_code + '  ' + g.supplier;
        html += '<button type="button" class="supplier-item" data-key="' + esc(g.key) + '" onclick="filterSupplier(this.getAttribute(\'data-key\'))" title="' + esc(label) + '">'
            + esc(label) + '<span class="supplier-count">' + g.count + '</span></button>';
    }
    document.getElementById('supplierList').innerHTML = html;
}

function filterSupplier(key) {
    ACTIVE_SUPPLIER_KEY = key;
    var rows = [];
    var i;

    if (key === '') {
        rows = ALL_ROWS.slice(0);
    } else {
        for (i = 0; i < ALL_ROWS.length; i++) {
            if (supplierKey(ALL_ROWS[i]) === key) {
                rows.push(ALL_ROWS[i]);
            }
        }
    }

    var buttons = document.getElementById('supplierList').getElementsByTagName('button');
    for (i = 0; i < buttons.length; i++) {
        buttons[i].className = buttons[i].getAttribute('data-key') === key
            ? (buttons[i].getAttribute('data-key') === '' ? 'supplier-item all active' : 'supplier-item active')
            : (buttons[i].getAttribute('data-key') === '' ? 'supplier-item all' : 'supplier-item');
    }

    renderReport(rows);
    renderPercentages(calculatePercentages(rows));
}

function showAllSuppliers() {
    filterSupplier('');
}

function calculatePercentages(rows) {
    var mrp = 0;
    var sales = 0;
    var receive = 0;
    var prodActual = 0;
    var i;
    for (i = 0; i < rows.length; i++) {
        mrp += Number(rows[i].mrp || 0);
        sales += Number(rows[i].sales || 0);
        receive += Number(rows[i].receive || 0);
        prodActual += Number(rows[i].prod_actual || 0);
    }
    return {
        receive_sales: sales !== 0 ? receive / sales * 100 : null,
        receive_mrp: mrp !== 0 ? receive / mrp * 100 : null,
        production_sales: sales !== 0 ? prodActual / sales * 100 : null
    };
}

function renderReport(rows) {
    VISIBLE_ROWS = rows.slice(0);
    var html = '';
    var currentGroup = '';
    var total = {
        sales:0, tags:0, tags_sales:0, mrp:0,
        prod_actual:0, receive:0, balance:0, balance_amount:0
    };
    var i;

    for (i = 0; i < rows.length; i++) {
        var r = rows[i];
        var groupKey = supplierKey(r);

        if (groupKey !== currentGroup) {
            currentGroup = groupKey;
            var groupLabel = (LAST_PLANT === 'all' ? '<span class="plant-chip">' + esc(r.plant_short) + '</span>' : '')
                + esc(r.supp_code || '-') + '&nbsp;&nbsp;&nbsp;' + esc(r.supplier || '(Tanpa nama supplier)');
            html += '<tr class="supplier-row"><td colspan="12">' + groupLabel + '</td></tr>';
        }

        total.tags += Number(r.tags || 0);
        total.tags_sales += Number(r.tags_sales || 0);
        total.mrp += Number(r.mrp || 0);
        total.prod_actual += Number(r.prod_actual || 0);
        total.sales += Number(r.sales || 0);
        total.receive += Number(r.receive || 0);
        total.balance += Number(r.balance || 0);
        total.balance_amount += Number(r.balance_amount || 0);

        html += '<tr>'
            + '<td title="' + esc(r.mat_code) + '">' + esc(r.mat_code) + '</td>'
            + '<td title="' + esc(r.mat_name) + '">' + esc(r.mat_name) + '</td>'
            + '<td class="num">' + formatPrice(r.price) + '</td>'
            + '<td class="center' + (Number(r.rate_missing || 0) ? ' rate-warning' : '') + '">' + esc(r.curr || '-') + '</td>'
            + '<td class="num">' + formatQty(r.sales) + '</td>'
            + '<td class="num">' + formatQty(r.tags) + '</td>'
            + '<td class="num ' + valueClass(r.tags_sales, '') + '">' + formatQty(r.tags_sales) + '</td>'
            + '<td class="num">' + formatQty(r.mrp) + '</td>'
            + '<td class="num">' + formatQty(r.prod_actual) + '</td>'
            + '<td class="num receive-cell">' + formatQty(r.receive) + '</td>'
            + '<td class="num ' + valueClass(r.balance, '') + '">' + formatQty(r.balance) + '</td>'
            + '<td class="num ' + valueClass(r.balance_amount, '') + '">' + formatAmount(r.balance_amount) + '</td>'
            + '</tr>';
    }

    if (rows.length === 0) {
        html = '<tr><td colspan="12" class="no-data">Data tidak ditemukan.</td></tr>';
    } else {
        html += '<tr class="grand-total">'
            + '<td colspan="4">TOTAL</td>'
            + '<td class="num">' + formatQty(total.sales) + '</td>'
            + '<td class="num">' + formatQty(total.tags) + '</td>'
            + '<td class="num ' + valueClass(total.tags_sales, '') + '">' + formatQty(total.tags_sales) + '</td>'
            + '<td class="num">' + formatQty(total.mrp) + '</td>'
            + '<td class="num">' + formatQty(total.prod_actual) + '</td>'
            + '<td class="num receive-cell">' + formatQty(total.receive) + '</td>'
            + '<td class="num ' + valueClass(total.balance, '') + '">' + formatQty(total.balance) + '</td>'
            + '<td class="num ' + valueClass(total.balance_amount, '') + '">' + formatAmount(total.balance_amount) + '</td>'
            + '</tr>';
    }

    document.getElementById('tbody').innerHTML = html;
    document.getElementById('pageInfo').innerHTML = rows.length + ' material';
}

function csvCell(value) {
    var text = value === null || typeof value === 'undefined' ? '' : String(value);
    return '"' + text.replace(/"/g, '""') + '"';
}

function exportCsv() {
    if (!VISIBLE_ROWS || VISIBLE_ROWS.length === 0) {
        alert('Tidak ada data untuk diekspor.');
        return;
    }

    var headers = [
        'Plant','Supplier Code','Supplier','Code','Item Name','Price','Curr','Rate',
        'Sales Material','Tags','Tags-Sales','MRP','Konversi Prod Actual',
        'Receive','Balance','Balance Amount'
    ];
    var lines = [headers.map(csvCell).join(',')];
    var i;

    for (i = 0; i < VISIBLE_ROWS.length; i++) {
        var r = VISIBLE_ROWS[i];
        lines.push([
            r.plant_short,r.supp_code,r.supplier,r.mat_code,r.mat_name,r.price,r.curr,r.curr_vrate,
            r.sales,r.tags,r.tags_sales,r.mrp,r.prod_actual,r.receive,r.balance,r.balance_amount
        ].map(csvCell).join(','));
    }

    var content = '\ufeff' + lines.join('\r\n');
    var filename = 'Sales_Material_vs_Receive_' + LAST_PERIOD.replace(/[^0-9A-Za-z]+/g, '_') + '.csv';

    if (window.Blob && window.URL && window.URL.createObjectURL) {
        var blob = new Blob([content], {type:'text/csv;charset=utf-8;'});
        var link = document.createElement('a');
        var url = URL.createObjectURL(blob);
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    } else {
        window.open('data:text/csv;charset=utf-8,' + encodeURIComponent(content));
    }
}

window.onload = function () {
    loadData();
};
</script>
</body>
</html>

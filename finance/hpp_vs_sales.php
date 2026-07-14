<?php
/*
    Perbandingan HPP IDR/USD vs Sales IDR/USD per Item per Customer
    PHP 5.4 + SQL Server 2008

    Catatan:
    - Stored procedure harus mengembalikan CUST_ID, CUST_CODE, dan CUST_COMP.
    - Tabel dan Excel dikelompokkan per customer.
    - Pencarian dan pemilihan grafik tetap berdasarkan item.
*/
set_time_limit(180);
if (session_id() === '') {
    session_start();
}

require_once __DIR__ . "/../config/database_ordering.php";

if (!isset($_SESSION['db_user']) || $_SESSION['db_user'] === '') {
    die('<div style="padding:24px;font-family:Arial;color:#b91c1c">Silakan login terlebih dahulu.</div>');
}

$uid = $_SESSION['db_user'];
$pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : '';
$dbName = 'msData';

$servers_config = array(
    'p1' => array('ip' => '192.168.0.4', 'label' => 'Plant 1', 'short' => 'P1'),
    'p2' => array('ip' => '192.168.0.9', 'label' => 'Plant 2', 'short' => 'P2')
);

function clean_plant($value)
{
    $value = strtolower(trim((string)$value));
    if (!in_array($value, array('p1', 'p2', 'all'), true)) {
        return 'all';
    }
    return $value;
}

function safe_error_text($errors)
{
    if (!is_array($errors)) {
        return '';
    }
    $parts = array();
    foreach ($errors as $error) {
        if (isset($error['message'])) {
            $parts[] = trim($error['message']);
        }
    }
    return implode(' | ', $parts);
}

if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
    header('Content-Type: application/json; charset=UTF-8');

    $plant = clean_plant(isset($_GET['plant']) ? $_GET['plant'] : 'all');
    $mode = isset($_GET['mode']) ? strtoupper(trim($_GET['mode'])) : 'YEARLY';
    if (!in_array($mode, array('YEARLY', 'MONTHLY'), true)) {
        $mode = 'YEARLY';
    }

    $currentYear = (int)date('Y');
    if ($mode === 'YEARLY') {
        $startYear = isset($_GET['startYear']) ? (int)$_GET['startYear'] : $currentYear;
        $endYear = isset($_GET['endYear']) ? (int)$_GET['endYear'] : $currentYear;
        if ($startYear < 2000) $startYear = 2000;
        if ($endYear > $currentYear + 1) $endYear = $currentYear + 1;
        if ($startYear > $endYear) {
            $tmp = $startYear;
            $startYear = $endYear;
            $endYear = $tmp;
        }
        $fromDate = sprintf('%04d-01-01 00:00:00', $startYear);
        $toDate = sprintf('%04d-12-31 23:59:59', $endYear);
        $periodText = ($startYear === $endYear) ? (string)$startYear : ($startYear . ' - ' . $endYear);
    } else {
        $targetYear = isset($_GET['targetYear']) ? (int)$_GET['targetYear'] : $currentYear;
        if ($targetYear < 2000) $targetYear = 2000;
        if ($targetYear > $currentYear + 1) $targetYear = $currentYear + 1;
        $fromDate = sprintf('%04d-01-01 00:00:00', $targetYear);
        $toDate = sprintf('%04d-12-31 23:59:59', $targetYear);
        $periodText = (string)$targetYear;
    }

    $serversToTry = ($plant === 'all') ? array('p1', 'p2') : array($plant);
    $connOptions = array(
        'Database' => $dbName,
        'Uid' => $uid,
        'PWD' => $pwd,
        'CharacterSet' => 'UTF-8',
        'LoginTimeout' => 5
    );

    $merged = array();
    $serverStatus = array('p1' => false, 'p2' => false);
    $errors = array();
    $missingCustomerColumns = false;
    $customerMappedRows = 0;
    $customerUnmappedRows = 0;

    foreach ($serversToTry as $serverKey) {
        $serverLabel = isset($servers_config[$serverKey]['label']) ? $servers_config[$serverKey]['label'] : strtoupper($serverKey);
        $serverShort = isset($servers_config[$serverKey]['short']) ? $servers_config[$serverKey]['short'] : strtoupper($serverKey);
        $serverIp = $servers_config[$serverKey]['ip'];

        $conn = @sqlsrv_connect($serverIp, $connOptions);
        if ($conn === false) {
            $errors[] = $serverLabel . ' offline: ' . safe_error_text(sqlsrv_errors());
            continue;
        }
        $serverStatus[$serverKey] = true;

        $sql = '{CALL dbo.sp_hpp_sales_item_idr(?, ?, ?)}';
        $params = array($fromDate, $toDate, $mode);
        $stmt = @sqlsrv_query($conn, $sql, $params, array('QueryTimeout' => 180));

        if ($stmt === false) {
            $errors[] = $serverLabel . ': procedure gagal: ' . safe_error_text(sqlsrv_errors());
            sqlsrv_close($conn);
            continue;
        }

        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if (!array_key_exists('CUST_CODE', $row) || !array_key_exists('CUST_COMP', $row)) {
                $missingCustomerColumns = true;
            }

            $periodKey = isset($row['PERIOD_KEY']) ? (int)$row['PERIOD_KEY'] : 0;
            $itemCode = isset($row['ITEM_CODE']) ? trim((string)$row['ITEM_CODE']) : '';
            $custId = isset($row['CUST_ID']) ? (int)$row['CUST_ID'] : 0;
            $custCode = isset($row['CUST_CODE']) ? trim((string)$row['CUST_CODE']) : 'TANPA-CUSTOMER';
            $custComp = isset($row['CUST_COMP']) ? trim((string)$row['CUST_COMP']) : 'TANPA CUSTOMER';

            if ($custCode === '') {
                $custCode = 'TANPA-CUSTOMER';
            }
            if ($custComp === '') {
                $custComp = 'TANPA CUSTOMER';
            }

            if ($custCode === 'TANPA-CUSTOMER') {
                $customerUnmappedRows++;
            } else {
                $customerMappedRows++;
            }

            if ($periodKey === 0 || $itemCode === '') {
                continue;
            }

            /*
                Customer dimasukkan ke merge key supaya item yang sama pada
                customer berbeda tidak tercampur. Untuk gabungan P1 & P2,
                customer dengan kode yang sama tetap digabung.
            */
            $mergeKey = $periodKey . '|' . strtoupper($custCode) . '|' . strtoupper($itemCode);
            if (!isset($merged[$mergeKey])) {
                $salesYear = isset($row['SALES_YEAR']) ? (int)$row['SALES_YEAR'] : 0;
                $salesMonth = isset($row['SALES_MONTH']) ? (int)$row['SALES_MONTH'] : 0;
                $merged[$mergeKey] = array(
                    'period_key' => $periodKey,
                    'sales_year' => $salesYear,
                    'sales_month' => $salesMonth,
                    'cust_id' => $custId,
                    'cust_code' => $custCode,
                    'cust_comp' => $custComp,
                    'item_id' => isset($row['ITEM_ID']) ? (int)$row['ITEM_ID'] : 0,
                    'item_code' => $itemCode,
                    'item_name' => isset($row['ITEM_NAME']) ? trim((string)$row['ITEM_NAME']) : '',
                    'sales_qty' => 0.0,
                    'total_hpp_idr' => 0.0,
                    'total_sales_idr' => 0.0,
                    'total_hpp_usd' => 0.0,
                    'total_sales_usd' => 0.0,
                    'hpp_error_lines' => 0,
                    'sales_error_lines' => 0,
                    'sources' => array(),
                    'plants' => array()
                );
            }

            $merged[$mergeKey]['sales_qty'] += isset($row['SALES_QTY']) ? (float)$row['SALES_QTY'] : 0;
            $merged[$mergeKey]['total_hpp_idr'] += isset($row['TOTAL_HPP_IDR']) ? (float)$row['TOTAL_HPP_IDR'] : 0;
            $merged[$mergeKey]['total_sales_idr'] += isset($row['TOTAL_SALES_IDR']) ? (float)$row['TOTAL_SALES_IDR'] : 0;
            $merged[$mergeKey]['total_hpp_usd'] += isset($row['TOTAL_HPP_USD']) ? (float)$row['TOTAL_HPP_USD'] : 0;
            $merged[$mergeKey]['total_sales_usd'] += isset($row['TOTAL_SALES_USD']) ? (float)$row['TOTAL_SALES_USD'] : 0;
            $merged[$mergeKey]['hpp_error_lines'] += isset($row['HPP_ERROR_LINES']) ? (int)$row['HPP_ERROR_LINES'] : 0;
            $merged[$mergeKey]['sales_error_lines'] += isset($row['SALES_RATE_ERROR_LINES']) ? (int)$row['SALES_RATE_ERROR_LINES'] : 0;

            $source = isset($row['HPP_SOURCE']) ? trim((string)$row['HPP_SOURCE']) : '';
            if ($source !== '' && !in_array($source, $merged[$mergeKey]['sources'], true)) {
                $merged[$mergeKey]['sources'][] = $source;
            }
            if (!in_array($serverShort, $merged[$mergeKey]['plants'], true)) {
                $merged[$mergeKey]['plants'][] = $serverShort;
            }
        }

        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);
    }

    if ($missingCustomerColumns) {
        $errors[] = 'Stored procedure yang aktif belum mengembalikan CUST_CODE dan CUST_COMP. Jalankan ulang SQL sp_hpp_sales_item_idr versi customer pada plant terkait.';
    } elseif ($customerMappedRows === 0 && $customerUnmappedRows > 0) {
        $errors[] = 'Seluruh transaksi masuk TANPA-CUSTOMER. Periksa relasi DIPA_PAR.PRICE_ID ke PART_VIEW.PRICE_ID dan CUST.CUST_ID.';
    }

    $result = array();
    foreach ($merged as $data) {
        $qty = (float)$data['sales_qty'];
        $totalHpp = (float)$data['total_hpp_idr'];
        $totalSales = (float)$data['total_sales_idr'];
        $totalHppUsd = (float)$data['total_hpp_usd'];
        $totalSalesUsd = (float)$data['total_sales_usd'];
        $hppUnit = ($qty != 0) ? ($totalHpp / $qty) : 0;
        $salesUnit = ($qty != 0) ? ($totalSales / $qty) : 0;
        $hppUnitUsd = ($qty != 0) ? ($totalHppUsd / $qty) : 0;
        $salesUnitUsd = ($qty != 0) ? ($totalSalesUsd / $qty) : 0;
        $margin = $totalSales - $totalHpp;
        $marginPct = ($totalSales != 0) ? ($margin / $totalSales * 100) : null;
        $marginUsd = $totalSalesUsd - $totalHppUsd;
        $marginPctUsd = ($totalSalesUsd != 0) ? ($marginUsd / $totalSalesUsd * 100) : null;

        $result[] = array(
            'period_key' => $data['period_key'],
            'sales_year' => $data['sales_year'],
            'sales_month' => $data['sales_month'],
            'cust_id' => $data['cust_id'],
            'cust_code' => $data['cust_code'],
            'cust_comp' => $data['cust_comp'],
            'item_id' => $data['item_id'],
            'item_code' => $data['item_code'],
            'item_name' => $data['item_name'],
            'sales_qty' => $qty,
            'hpp_unit_idr' => $hppUnit,
            'sales_unit_idr' => $salesUnit,
            'unit_difference_idr' => $salesUnit - $hppUnit,
            'hpp_unit_usd' => $hppUnitUsd,
            'sales_unit_usd' => $salesUnitUsd,
            'unit_difference_usd' => $salesUnitUsd - $hppUnitUsd,
            'total_hpp_idr' => $totalHpp,
            'total_sales_idr' => $totalSales,
            'margin_idr' => $margin,
            'margin_percent' => $marginPct,
            'total_hpp_usd' => $totalHppUsd,
            'total_sales_usd' => $totalSalesUsd,
            'margin_usd' => $marginUsd,
            'margin_percent_usd' => $marginPctUsd,
            'hpp_source' => implode(', ', $data['sources']),
            'plants' => implode(' & ', $data['plants']),
            'hpp_error_lines' => $data['hpp_error_lines'],
            'sales_error_lines' => $data['sales_error_lines'],
            'error_lines' => $data['hpp_error_lines'] + $data['sales_error_lines']
        );
    }

    usort($result, function ($a, $b) {
        $custCompare = strcmp($a['cust_code'], $b['cust_code']);
        if ($custCompare !== 0) {
            return $custCompare;
        }

        if ($a['period_key'] == $b['period_key']) {
            return strcmp($a['item_code'], $b['item_code']);
        }

        return ($a['period_key'] < $b['period_key']) ? -1 : 1;
    });

    echo json_encode(array(
        'status' => 'success',
        'mode' => $mode,
        'period_text' => $periodText,
        'plant' => $plant,
        'servers' => $serverStatus,
        'errors' => $errors,
        'data' => $result
    ));
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HPP IDR/USD vs Sales IDR/USD per Item per Customer</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>
<style>
:root{--bg:#f3f6fa;--card:#fff;--line:#dbe3ec;--text:#172033;--muted:#667085;--hpp:#dc2626;--sales:#047857;--accent:#1d4ed8;--warn:#b45309;--radius:12px}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:Arial,Helvetica,sans-serif;font-size:13px}.page{max-width:1500px;margin:0 auto;padding:18px}.header{display:flex;justify-content:space-between;gap:15px;align-items:flex-start;margin-bottom:14px}.header h1{font-size:23px;margin:0 0 5px}.sub{color:var(--muted)}.badge{display:inline-block;padding:6px 10px;border-radius:20px;background:#e8efff;color:#1d4ed8;font-weight:bold}.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);box-shadow:0 4px 14px rgba(15,23,42,.05);margin-bottom:14px}.filters{padding:12px;display:flex;align-items:end;gap:10px;flex-wrap:wrap}.field{display:flex;flex-direction:column;gap:5px}.field label{font-size:11px;font-weight:bold;color:var(--muted);text-transform:uppercase}.field select,.field input{height:36px;padding:0 10px;border:1px solid #cfd8e3;border-radius:7px;background:#fff;min-width:125px}.btn{height:36px;border:0;border-radius:7px;padding:0 15px;font-weight:bold;cursor:pointer}.btn-primary{background:var(--accent);color:#fff}.btn-excel{background:#15803d;color:#fff}.btn-light{background:#e9eef5;color:#263247}.error{display:none;padding:12px;border:1px solid #fecaca;background:#fff1f2;color:#991b1b;border-radius:8px;margin-bottom:14px}.summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:14px}.summary .card{margin:0;padding:15px}.summary-label{color:var(--muted);font-size:11px;font-weight:bold;text-transform:uppercase}.summary-value{font-size:19px;font-weight:bold;margin-top:7px}.summary-small{margin-top:5px;color:var(--muted)}.content-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:14px}.chart-card{padding:15px}.chart-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px}.chart-title{font-weight:bold;font-size:15px}.chart-wrap{height:330px}.chart-block+.chart-block{margin-top:18px;padding-top:18px;border-top:1px solid var(--line)}.chart-subtitle{font-size:12px;font-weight:bold;color:var(--muted);margin:0 0 8px}.autocomplete-input{min-width:420px!important;width:100%}.table-card{padding:14px}.table-toolbar{display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:10px}.search-box{width:300px;max-width:100%}.table-wrap{overflow:auto;max-height:650px;border:1px solid var(--line);border-radius:8px}table{border-collapse:collapse;width:100%;min-width:2100px}th{position:sticky;top:0;z-index:2;background:#edf2f7;padding:9px 8px;border-bottom:2px solid #cad5e1;font-size:11px;white-space:nowrap;text-align:center}td{padding:7px 8px;border-bottom:1px solid #e8edf3;white-space:nowrap}tbody tr:hover{background:#f8fbff}.num{text-align:right;font-variant-numeric:tabular-nums}.center{text-align:center}.hpp{color:var(--hpp);font-weight:bold}.sales{color:var(--sales);font-weight:bold}.negative{color:#dc2626;font-weight:bold}.positive{color:#047857;font-weight:bold}.warning-row{background:#fffaf0}
.customer-group-row td{background:#dbeafe!important;color:#1e3a8a;font-weight:bold;border-top:2px solid #93c5fd;border-bottom:1px solid #93c5fd;padding:10px 12px}
.customer-group-meta{float:right;color:#475569;font-weight:normal}
.customer-subtotal-row td{background:#f1f5f9;font-weight:bold;border-top:2px solid #cbd5e1;border-bottom:2px solid #cbd5e1}
.customer-subtotal-row .label{text-align:left;color:#334155}
.grand-total-row td{background:#e2e8f0;font-weight:bold;border-top:3px double #64748b}.pager{display:flex;justify-content:space-between;align-items:center;padding-top:10px}.pager-buttons{display:flex;gap:6px}.search-actions{display:flex;gap:6px;align-items:end;flex-wrap:wrap}.filter-status{font-size:11px;color:var(--muted);padding:6px 0 0}.loading{position:fixed;inset:0;background:rgba(255,255,255,.82);display:none;align-items:center;justify-content:center;z-index:1000}.loading.show{display:flex}.loader-box{background:#fff;border:1px solid var(--line);padding:22px 30px;border-radius:12px;font-weight:bold;box-shadow:0 8px 30px rgba(0,0,0,.12)}.dot{display:inline-block;width:17px;height:17px;border:3px solid #d5ddec;border-top-color:#1d4ed8;border-radius:50%;animation:spin .8s linear infinite;vertical-align:middle;margin-right:8px}@keyframes spin{to{transform:rotate(360deg)}}

.autocomplete-wrap{position:relative;width:100%}
.autocomplete-list{display:none;position:absolute;left:0;right:0;top:39px;z-index:50;background:#fff;border:1px solid #cfd8e3;border-radius:8px;box-shadow:0 10px 26px rgba(15,23,42,.18);max-height:260px;overflow:auto}
.autocomplete-list.show{display:block}
.autocomplete-item{padding:8px 10px;border-bottom:1px solid #edf2f7;cursor:pointer;line-height:1.35;background:#fff}
.autocomplete-item:hover,.autocomplete-item.active{background:#eff6ff}
.autocomplete-code{font-weight:bold;color:#172033}.autocomplete-name{color:#475569}.autocomplete-empty{padding:10px;color:#64748b}.autocomplete-hint{font-size:11px;color:#64748b;padding:6px 10px;background:#f8fafc;border-bottom:1px solid #e2e8f0}
.show-all-note{font-size:11px;color:#64748b;margin-left:6px}

@media(max-width:850px){.summary{grid-template-columns:1fr 1fr}.header{flex-direction:column}.page{padding:10px}}
@media print{body{background:#fff}.filters,.table-toolbar,.pager,.header-actions{display:none!important}.page{max-width:none;padding:0}.card{box-shadow:none}.table-wrap{max-height:none;overflow:visible}th{position:static}.chart-wrap{height:280px}}
</style>
</head>
<body>
<div class="loading" id="loading"><div class="loader-box"><span class="dot"></span>Menarik data HPP dan sales IDR/USD...</div></div>
<div class="page">
    <div class="header">
        <div>
            <h1>Perbandingan HPP IDR/USD vs Sales IDR/USD per Item per Customer</h1>
            <div class="sub">Customer ditampilkan sebagai header grup dan kolom detail; pencarian dan grafik tetap berdasarkan item</div>
        </div>
        <div class="header-actions"><span class="badge" id="plantBadge">PLANT</span></div>
    </div>

    <div class="card filters">
        <div class="field"><label>Plant</label><select id="plant"><option value="all">Gabungan P1 & P2</option><option value="p1">Plant 1</option><option value="p2">Plant 2</option></select></div>
        <div class="field"><label>Mode</label><select id="mode"><option value="YEARLY">Tahunan</option><option value="MONTHLY">Bulanan</option></select></div>
        <div id="yearlyFields" style="display:flex;gap:10px">
            <div class="field"><label>Dari Tahun</label><select id="startYear"></select></div>
            <div class="field"><label>Sampai Tahun</label><select id="endYear"></select></div>
        </div>
        <div id="monthlyFields" style="display:none"><div class="field"><label>Tahun Bulanan</label><select id="targetYear"></select></div></div>
        <button class="btn btn-primary" type="button" id="applyBtn">Tampilkan</button>
        <button class="btn btn-excel" type="button" id="excelBtn">Export Excel</button>
        <button class="btn btn-light" type="button" onclick="window.print()">Print</button>
    </div>

    <div class="error" id="errorBox"></div>

    <div class="summary">
        <div class="card"><div class="summary-label">Qty Sales</div><div class="summary-value" id="sumQty">0</div><div class="summary-small">Total quantity terfilter</div></div>
        <div class="card"><div class="summary-label">Total HPP IDR</div><div class="summary-value hpp" id="sumHpp">Rp 0</div><div class="summary-small" id="avgHpp">Rata-rata unit Rp 0</div></div>
        <div class="card"><div class="summary-label">Total Sales IDR</div><div class="summary-value sales" id="sumSales">Rp 0</div><div class="summary-small" id="avgSales">Rata-rata unit Rp 0</div></div>
        <div class="card"><div class="summary-label">Margin IDR</div><div class="summary-value" id="sumMargin">Rp 0</div><div class="summary-small" id="sumMarginPct">0%</div></div>
        <div class="card"><div class="summary-label">Total HPP USD</div><div class="summary-value hpp" id="sumHppUsd">$ 0.00</div><div class="summary-small" id="avgHppUsd">Rata-rata unit $ 0.00</div></div>
        <div class="card"><div class="summary-label">Total Sales USD</div><div class="summary-value sales" id="sumSalesUsd">$ 0.00</div><div class="summary-small" id="avgSalesUsd">Rata-rata unit $ 0.00</div></div>
        <div class="card"><div class="summary-label">Margin USD</div><div class="summary-value" id="sumMarginUsd">$ 0.00</div><div class="summary-small" id="sumMarginPctUsd">0%</div></div>
        <div class="card"><div class="summary-label">Jumlah Customer</div><div class="summary-value" id="sumCustomer">0</div><div class="summary-small">Customer pada data terfilter</div></div>
    </div>

    <div class="card chart-card">
        <div class="chart-head">
            <div class="chart-title">Grafik harga unit HPP vs Sales + line tren harga Sales</div>
            <div class="field" style="min-width:430px"><label>Cari Item untuk Grafik</label><div class="autocomplete-wrap"><input class="autocomplete-input" id="chartItemSearch" type="text" placeholder="Ketik kode atau nama item" autocomplete="off"><div class="autocomplete-list" id="chartItemSuggest"></div></div></div>
        </div>
        <div class="chart-block">
            <div class="chart-subtitle">Perbandingan harga unit dalam IDR</div>
            <div class="chart-wrap"><canvas id="priceChartIdr"></canvas></div>
        </div>
        <div class="chart-block">
            <div class="chart-subtitle">Perbandingan harga unit dalam USD</div>
            <div class="chart-wrap"><canvas id="priceChartUsd"></canvas></div>
        </div>
    </div>

    <div class="card table-card">
        <div class="table-toolbar">
            <div class="search-actions">
                <div class="field"><label>Cari Item (kode / nama)</label><div class="autocomplete-wrap"><input class="search-box" id="searchText" type="text" placeholder="Kosongkan untuk tampil semua item" autocomplete="off"><div class="autocomplete-list" id="searchItemSuggest"></div></div><div class="filter-status" id="filterStatus">Menampilkan semua item</div></div>
                <button class="btn btn-light" type="button" id="showAllBtn">Tampilkan Semua Item</button>
            </div>
            <div class="field"><label>Baris per Halaman</label><select id="pageSize"><option value="ALL" selected>Semua</option><option value="25">25</option><option value="50">50</option><option value="100">100</option><option value="250">250</option><option value="500">500</option><option value="1000">1000</option></select><span class="show-all-note">Default: tampil semua item</span></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>Periode</th><th>Plant</th><th>Customer Code</th><th>Customer Name</th><th>Item Code</th><th>Item Name</th><th>Qty Sales</th>
                    <th>HPP / Unit IDR</th><th>Sales / Unit IDR</th><th>Selisih / Unit IDR</th>
                    <th>HPP / Unit USD</th><th>Sales / Unit USD</th><th>Selisih / Unit USD</th>
                    <th>Total HPP IDR</th><th>Total Sales IDR</th><th>Margin IDR</th><th>Margin % IDR</th>
                    <th>Total HPP USD</th><th>Total Sales USD</th><th>Margin USD</th><th>Margin % USD</th>
                    <th>Sumber HPP</th><th>Error</th>
                </tr></thead>
                <tbody id="dataBody"></tbody>
            </table>
        </div>
        <div class="pager"><div id="pageInfo">0 data</div><div class="pager-buttons"><button class="btn btn-light" id="prevBtn">Sebelumnya</button><button class="btn btn-light" id="nextBtn">Berikutnya</button></div></div>
    </div>
</div>
<script>
var ALL_DATA = [];
var FILTERED_DATA = [];
var CURRENT_PAGE = 1;
var PRICE_CHART_IDR = null;
var PRICE_CHART_USD = null;
var SELECTED_ITEM_CODE = '';
var ITEM_LABEL_TO_CODE = {};
var ITEM_CODE_TO_LABEL = {};
var ITEM_OPTIONS = [];
var MONTH_NAMES = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
var CURRENT_YEAR = new Date().getFullYear();

function byId(id){return document.getElementById(id);}
function escapeHtml(value){return String(value === null || value === undefined ? '' : value).replace(/[&<>'"]/g,function(ch){return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch];});}
function formatIDR(value){return 'Rp ' + Math.round(Number(value || 0)).toLocaleString('id-ID');}
function formatUSD(value){return '$ ' + Number(value || 0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:4});}
function formatNum(value, decimals){return Number(value || 0).toLocaleString('id-ID',{minimumFractionDigits:decimals,maximumFractionDigits:decimals});}
function formatPct(value){if(value === null || value === undefined || isNaN(value))return '-';return Number(value).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2})+'%';}
function periodLabel(row){if(byId('mode').value === 'MONTHLY'){return MONTH_NAMES[Math.max(0,Number(row.sales_month)-1)]+' '+row.sales_year;}return String(row.sales_year);}
function showLoading(show){byId('loading').className = show ? 'loading show' : 'loading';}

function initYears(){
    var ids=['startYear','endYear','targetYear'];
    var i,y,sel;
    for(i=0;i<ids.length;i++){
        sel=byId(ids[i]);
        for(y=2015;y<=CURRENT_YEAR+1;y++)sel.options.add(new Option(y,y));
    }
    byId('startYear').value=CURRENT_YEAR-2;
    byId('endYear').value=CURRENT_YEAR;
    byId('targetYear').value=CURRENT_YEAR;
}

function updateModeFields(){
    var monthly=byId('mode').value==='MONTHLY';
    byId('yearlyFields').style.display=monthly?'none':'flex';
    byId('monthlyFields').style.display=monthly?'block':'none';
}

function loadData(){
    showLoading(true);
    var mode=byId('mode').value;
    var url='?action=fetch&plant='+encodeURIComponent(byId('plant').value)+'&mode='+encodeURIComponent(mode);
    if(mode==='YEARLY')url+='&startYear='+encodeURIComponent(byId('startYear').value)+'&endYear='+encodeURIComponent(byId('endYear').value);
    else url+='&targetYear='+encodeURIComponent(byId('targetYear').value);

    fetch(url).then(function(res){return res.json();}).then(function(json){
        ALL_DATA=json.data||[];
        CURRENT_PAGE=1;
        byId('searchText').value='';
        byId('pageSize').value='ALL';
        updatePlantBadge(json);
        showErrors(json.errors||[]);
        populateItemAutocomplete();
        applySearch();
        showLoading(false);
    }).catch(function(err){
        showErrors(['Gagal menarik data: '+err.message]);
        ALL_DATA=[];FILTERED_DATA=[];renderTable();renderSummary();renderChart();showLoading(false);
    });
}

function updatePlantBadge(json){
    var plant=json.plant||'all',text='';
    if(plant==='p1')text='PLANT 1';else if(plant==='p2')text='PLANT 2';else text='P1 & P2';
    var live=[];if(json.servers&&json.servers.p1)live.push('P1');if(json.servers&&json.servers.p2)live.push('P2');
    if(live.length===0)text+=' OFFLINE';
    byId('plantBadge').innerHTML=escapeHtml(text);
}
function showErrors(errors){var box=byId('errorBox');if(!errors.length){box.style.display='none';box.innerHTML='';return;}box.style.display='block';box.innerHTML='<b>Peringatan:</b><br>'+errors.map(escapeHtml).join('<br>');}

function populateItemAutocomplete(){
    var map={},i,row,keys=[],oldCode=SELECTED_ITEM_CODE;
    ITEM_LABEL_TO_CODE={};
    ITEM_CODE_TO_LABEL={};
    ITEM_OPTIONS=[];

    for(i=0;i<ALL_DATA.length;i++){
        row=ALL_DATA[i];
        if(row.item_code && !map[row.item_code]){
            map[row.item_code]=row.item_name || '';
        }
    }

    for(var key in map){
        if(map.hasOwnProperty(key))keys.push(key);
    }
    keys.sort();

    for(i=0;i<keys.length;i++){
        var label=keys[i]+' - '+map[keys[i]];
        ITEM_LABEL_TO_CODE[label.toLowerCase()]=keys[i];
        ITEM_CODE_TO_LABEL[keys[i]]=label;
        ITEM_OPTIONS.push({code:keys[i],name:map[keys[i]],label:label});
    }

    hideSuggest('chartItemSuggest');
    hideSuggest('searchItemSuggest');

    if(oldCode&&ITEM_CODE_TO_LABEL[oldCode])setSelectedItem(oldCode,false);
    else if(keys.length)setSelectedItem(keys[0],false);
    else{SELECTED_ITEM_CODE='';byId('chartItemSearch').value='';}
}

function resolveItemCode(value){
    var raw=String(value||'').replace(/^\s+|\s+$/g,'');
    if(raw==='' || raw==='Kosongkan untuk tampil semua item')return '';
    var low=raw.toLowerCase();
    if(ITEM_LABEL_TO_CODE[low])return ITEM_LABEL_TO_CODE[low];
    if(ITEM_CODE_TO_LABEL[raw])return raw;
    for(var code in ITEM_CODE_TO_LABEL){
        if(ITEM_CODE_TO_LABEL.hasOwnProperty(code)&&ITEM_CODE_TO_LABEL[code].toLowerCase().indexOf(low)>=0)return code;
    }
    return '';
}


function hideSuggest(id){
    var box=byId(id);
    if(box){box.className='autocomplete-list';box.innerHTML='';}
}

function buildSuggestHtml(list, includeShowAll){
    var html='',i,opt;
    if(includeShowAll){
        html+='<div class="autocomplete-hint" data-show-all="1"><b>Tampilkan semua item</b> — kosongkan filter pencarian</div>';
    }
    if(!list.length){
        html+='<div class="autocomplete-empty">Item tidak ditemukan</div>';
        return html;
    }
    for(i=0;i<list.length;i++){
        opt=list[i];
        html+='<div class="autocomplete-item" data-code="'+escapeHtml(opt.code)+'">'+
            '<span class="autocomplete-code">'+escapeHtml(opt.code)+'</span> - '+
            '<span class="autocomplete-name">'+escapeHtml(opt.name)+'</span></div>';
    }
    return html;
}

function findItemOptions(keyword, limit){
    var q=String(keyword||'').replace(/^\s+|\s+$/g,'').toLowerCase();
    var exact=[],starts=[],contains=[],i,opt,hay;
    for(i=0;i<ITEM_OPTIONS.length;i++){
        opt=ITEM_OPTIONS[i];
        hay=(opt.code+' '+opt.name).toLowerCase();
        if(q==='')contains.push(opt);
        else if(String(opt.code).toLowerCase()===q)exact.push(opt);
        else if(String(opt.code).toLowerCase().indexOf(q)===0 || String(opt.name).toLowerCase().indexOf(q)===0)starts.push(opt);
        else if(hay.indexOf(q)>=0)contains.push(opt);
    }
    var res=exact.concat(starts).concat(contains);
    return res.slice(0,limit||80);
}

function showSuggest(inputId, suggestId, includeShowAll){
    var input=byId(inputId),box=byId(suggestId);
    if(!input||!box)return;
    var list=findItemOptions(input.value,80);
    box.innerHTML=buildSuggestHtml(list,includeShowAll);
    box.className='autocomplete-list show';
}

function setupAutocomplete(inputId, suggestId, mode){
    var input=byId(inputId),box=byId(suggestId);
    if(!input||!box)return;

    input.onfocus=function(){showSuggest(inputId,suggestId,mode==='search');};
    input.oninput=function(){showSuggest(inputId,suggestId,mode==='search');};
    input.onkeyup=function(e){
        e=e||window.event;
        if(e.keyCode===13){
            var code=resolveItemCode(input.value);
            if(mode==='chart'){
                if(code)setSelectedItem(code,true);
            }else{
                applySearch();
            }
            hideSuggest(suggestId);
        }else if(e.keyCode!==38&&e.keyCode!==40&&e.keyCode!==27){
            showSuggest(inputId,suggestId,mode==='search');
        }
        if(e.keyCode===27)hideSuggest(suggestId);
    };

    box.onclick=function(e){
        e=e||window.event;
        var target=e.target||e.srcElement;
        while(target && target!==box && !target.getAttribute('data-code') && !target.getAttribute('data-show-all')){
            target=target.parentNode;
        }
        if(!target||target===box)return;
        if(target.getAttribute('data-show-all')){
            showAllItems();
            hideSuggest(suggestId);
            return;
        }
        var code=target.getAttribute('data-code');
        if(!code)return;
        input.value=ITEM_CODE_TO_LABEL[code]||code;
        if(mode==='chart'){
            setSelectedItem(code,true);
        }else{
            applySearch();
        }
        hideSuggest(suggestId);
    };
}

document.addEventListener('click',function(e){
    e=e||window.event;
    var t=e.target||e.srcElement;
    if(t&&t.id==='chartItemSearch')return;
    if(t&&t.id==='searchText')return;
    var p=t;
    while(p){
        if(p.id==='chartItemSuggest'||p.id==='searchItemSuggest')return;
        p=p.parentNode;
    }
    hideSuggest('chartItemSuggest');
    hideSuggest('searchItemSuggest');
});

function setSelectedItem(code,redraw){
    if(!code||!ITEM_CODE_TO_LABEL[code])return;
    SELECTED_ITEM_CODE=code;
    byId('chartItemSearch').value=ITEM_CODE_TO_LABEL[code];
    if(redraw!==false)renderChart();
}

function applySearch(){
    var rawSearch=byId('searchText').value.replace(/^\s+|\s+$/g,'');
    if(rawSearch==='Kosongkan untuk tampil semua item')rawSearch='';
    var resolvedCode = rawSearch==='' ? '' : resolveItemCode(rawSearch);
    var q = rawSearch==='' ? '' : (resolvedCode!==''?resolvedCode:rawSearch).toLowerCase();
    FILTERED_DATA=[];

    /*
        Pencarian sengaja hanya memakai kode dan nama item.
        Customer tidak dimasukkan ke pencarian.
    */
    for(var i=0;i<ALL_DATA.length;i++){
        var r=ALL_DATA[i],hay=(r.item_code+' '+r.item_name).toLowerCase();
        if(q===''||hay.indexOf(q)>=0)FILTERED_DATA.push(r);
    }

    FILTERED_DATA.sort(function(a,b){
        var ca=String(a.cust_code||'').toUpperCase();
        var cb=String(b.cust_code||'').toUpperCase();
        if(ca<cb)return -1;
        if(ca>cb)return 1;
        if(Number(a.period_key)!==Number(b.period_key))return Number(a.period_key)-Number(b.period_key);
        return String(a.item_code||'').localeCompare(String(b.item_code||''));
    });

    byId('filterStatus').innerHTML = q === '' ? ('Menampilkan semua item: '+ALL_DATA.length+' detail') : ('Filter item aktif: ' + escapeHtml(rawSearch));
    CURRENT_PAGE=1;renderTable();renderSummary();renderChart();
}

function showAllItems(){
    byId('searchText').value='';
    byId('pageSize').value='ALL';
    applySearch();
}

function customerKey(row){
    return String(row.cust_code||'TANPA-CUSTOMER')+'|'+String(row.cust_comp||'TANPA CUSTOMER');
}

function calculateCustomerTotals(rows){
    var totals={},i,r,key,t;
    for(i=0;i<rows.length;i++){
        r=rows[i];
        key=customerKey(r);
        if(!totals[key]){
            totals[key]={
                cust_code:r.cust_code||'TANPA-CUSTOMER',
                cust_comp:r.cust_comp||'TANPA CUSTOMER',
                sales_qty:0,total_hpp_idr:0,total_sales_idr:0,
                total_hpp_usd:0,total_sales_usd:0,error_lines:0
            };
        }
        t=totals[key];
        t.sales_qty+=Number(r.sales_qty||0);
        t.total_hpp_idr+=Number(r.total_hpp_idr||0);
        t.total_sales_idr+=Number(r.total_sales_idr||0);
        t.total_hpp_usd+=Number(r.total_hpp_usd||0);
        t.total_sales_usd+=Number(r.total_sales_usd||0);
        t.error_lines+=Number(r.error_lines||0);
    }
    return totals;
}

function customerSubtotalRow(t){
    var qty=Number(t.sales_qty||0);
    var hpp=Number(t.total_hpp_idr||0),sales=Number(t.total_sales_idr||0);
    var hppUsd=Number(t.total_hpp_usd||0),salesUsd=Number(t.total_sales_usd||0);
    var hppUnit=qty!==0?hpp/qty:0,salesUnit=qty!==0?sales/qty:0;
    var hppUnitUsd=qty!==0?hppUsd/qty:0,salesUnitUsd=qty!==0?salesUsd/qty:0;
    var margin=sales-hpp,marginUsd=salesUsd-hppUsd;
    var pct=sales!==0?margin/sales*100:null,pctUsd=salesUsd!==0?marginUsd/salesUsd*100:null;
    return '<tr class="customer-subtotal-row">'+
        '<td colspan="6" class="label">SUBTOTAL CUSTOMER</td>'+
        '<td class="num">'+formatNum(qty,2)+'</td>'+
        '<td class="num hpp">'+formatIDR(hppUnit)+'</td>'+
        '<td class="num sales">'+formatIDR(salesUnit)+'</td>'+
        '<td class="num '+((salesUnit-hppUnit)<0?'negative':'positive')+'">'+formatIDR(salesUnit-hppUnit)+'</td>'+
        '<td class="num hpp">'+formatUSD(hppUnitUsd)+'</td>'+
        '<td class="num sales">'+formatUSD(salesUnitUsd)+'</td>'+
        '<td class="num '+((salesUnitUsd-hppUnitUsd)<0?'negative':'positive')+'">'+formatUSD(salesUnitUsd-hppUnitUsd)+'</td>'+
        '<td class="num">'+formatIDR(hpp)+'</td>'+
        '<td class="num">'+formatIDR(sales)+'</td>'+
        '<td class="num '+(margin<0?'negative':'positive')+'">'+formatIDR(margin)+'</td>'+
        '<td class="num '+(margin<0?'negative':'positive')+'">'+formatPct(pct)+'</td>'+
        '<td class="num">'+formatUSD(hppUsd)+'</td>'+
        '<td class="num">'+formatUSD(salesUsd)+'</td>'+
        '<td class="num '+(marginUsd<0?'negative':'positive')+'">'+formatUSD(marginUsd)+'</td>'+
        '<td class="num '+(marginUsd<0?'negative':'positive')+'">'+formatPct(pctUsd)+'</td>'+
        '<td class="center">-</td><td class="center">'+formatNum(t.error_lines,0)+'</td></tr>';
}

function renderTable(){
    var sizeValue=byId('pageSize').value;
    var size=(sizeValue==='ALL')?FILTERED_DATA.length:(parseInt(sizeValue,10)||50);
    if(size<=0)size=1;
    var pages=Math.max(1,Math.ceil(FILTERED_DATA.length/size));
    if(CURRENT_PAGE>pages)CURRENT_PAGE=pages;

    var start=(CURRENT_PAGE-1)*size;
    var end=Math.min(start+size,FILTERED_DATA.length);
    var html='',currentCustomer='',customerTotals=calculateCustomerTotals(FILTERED_DATA);

    for(var i=start;i<end;i++){
        var r=FILTERED_DATA[i];
        var key=customerKey(r);

        if(key!==currentCustomer){
            currentCustomer=key;
            html+='<tr class="customer-group-row"><td colspan="23">'+
                'CUSTOMER: '+escapeHtml(r.cust_code||'TANPA-CUSTOMER')+' - '+escapeHtml(r.cust_comp||'TANPA CUSTOMER')+
                '<span class="customer-group-meta">Klik item untuk menampilkan grafik</span></td></tr>';
        }

        var marginClass=Number(r.margin_idr)<0?'negative':'positive';
        var marginUsdClass=Number(r.margin_usd)<0?'negative':'positive';
        var warning=Number(r.error_lines)>0?' class="warning-row"':'';

        html+='<tr'+warning+' data-item="'+escapeHtml(r.item_code)+'">'+
            '<td class="center">'+escapeHtml(periodLabel(r))+'</td><td class="center">'+escapeHtml(r.plants)+'</td>'+
            '<td><b>'+escapeHtml(r.cust_code||'TANPA-CUSTOMER')+'</b></td><td>'+escapeHtml(r.cust_comp||'TANPA CUSTOMER')+'</td>'+
            '<td><b>'+escapeHtml(r.item_code)+'</b></td><td>'+escapeHtml(r.item_name)+'</td>'+
            '<td class="num">'+formatNum(r.sales_qty,2)+'</td>'+
            '<td class="num hpp">'+formatIDR(r.hpp_unit_idr)+'</td><td class="num sales">'+formatIDR(r.sales_unit_idr)+'</td>'+
            '<td class="num '+(Number(r.unit_difference_idr)<0?'negative':'positive')+'">'+formatIDR(r.unit_difference_idr)+'</td>'+
            '<td class="num hpp">'+formatUSD(r.hpp_unit_usd)+'</td><td class="num sales">'+formatUSD(r.sales_unit_usd)+'</td>'+
            '<td class="num '+(Number(r.unit_difference_usd)<0?'negative':'positive')+'">'+formatUSD(r.unit_difference_usd)+'</td>'+
            '<td class="num">'+formatIDR(r.total_hpp_idr)+'</td><td class="num">'+formatIDR(r.total_sales_idr)+'</td>'+
            '<td class="num '+marginClass+'">'+formatIDR(r.margin_idr)+'</td><td class="num '+marginClass+'">'+formatPct(r.margin_percent)+'</td>'+
            '<td class="num">'+formatUSD(r.total_hpp_usd)+'</td><td class="num">'+formatUSD(r.total_sales_usd)+'</td>'+
            '<td class="num '+marginUsdClass+'">'+formatUSD(r.margin_usd)+'</td><td class="num '+marginUsdClass+'">'+formatPct(r.margin_percent_usd)+'</td>'+
            '<td class="center">'+escapeHtml(r.hpp_source)+'</td><td class="center">'+escapeHtml(r.error_lines)+'</td></tr>';

        /*
            Subtotal hanya ditampilkan ketika baris terakhir customer berada
            pada halaman aktif. Nilainya tetap memakai seluruh data customer.
        */
        var nextIndex=i+1;
        if(nextIndex>=FILTERED_DATA.length || customerKey(FILTERED_DATA[nextIndex])!==key){
            html+=customerSubtotalRow(customerTotals[key]);
        }
    }

    if(!html)html='<tr><td colspan="23" class="center" style="padding:30px">Tidak ada data</td></tr>';
    byId('dataBody').innerHTML=html;
    byId('pageInfo').innerHTML=FILTERED_DATA.length+' detail item | halaman '+CURRENT_PAGE+' dari '+pages;
    byId('prevBtn').disabled=CURRENT_PAGE<=1;
    byId('nextBtn').disabled=CURRENT_PAGE>=pages;

    var rows=byId('dataBody').getElementsByTagName('tr');
    for(var x=0;x<rows.length;x++){
        rows[x].onclick=function(){
            var code=this.getAttribute('data-item');
            if(code){
                setSelectedItem(code,true);
                window.scrollTo(0,0);
            }
        };
    }
}

function renderSummary(){
    var qty=0,hpp=0,sales=0,hppUsd=0,salesUsd=0;
    for(var i=0;i<FILTERED_DATA.length;i++){
        qty+=Number(FILTERED_DATA[i].sales_qty||0);
        hpp+=Number(FILTERED_DATA[i].total_hpp_idr||0);
        sales+=Number(FILTERED_DATA[i].total_sales_idr||0);
        hppUsd+=Number(FILTERED_DATA[i].total_hpp_usd||0);
        salesUsd+=Number(FILTERED_DATA[i].total_sales_usd||0);
    }
    var margin=sales-hpp,pct=sales!==0?margin/sales*100:null;
    var marginUsd=salesUsd-hppUsd,pctUsd=salesUsd!==0?marginUsd/salesUsd*100:null;
    var custMap={};
    for(var ci=0;ci<FILTERED_DATA.length;ci++){custMap[customerKey(FILTERED_DATA[ci])]=1;}
    byId('sumQty').innerHTML=formatNum(qty,2);
    byId('sumHpp').innerHTML=formatIDR(hpp);byId('sumSales').innerHTML=formatIDR(sales);
    byId('avgHpp').innerHTML='Rata-rata unit '+formatIDR(qty!==0?hpp/qty:0);byId('avgSales').innerHTML='Rata-rata unit '+formatIDR(qty!==0?sales/qty:0);
    byId('sumMargin').innerHTML=formatIDR(margin);byId('sumMargin').className='summary-value '+(margin<0?'negative':'positive');byId('sumMarginPct').innerHTML=formatPct(pct);
    byId('sumHppUsd').innerHTML=formatUSD(hppUsd);byId('sumSalesUsd').innerHTML=formatUSD(salesUsd);
    byId('avgHppUsd').innerHTML='Rata-rata unit '+formatUSD(qty!==0?hppUsd/qty:0);byId('avgSalesUsd').innerHTML='Rata-rata unit '+formatUSD(qty!==0?salesUsd/qty:0);
    byId('sumMarginUsd').innerHTML=formatUSD(marginUsd);byId('sumMarginUsd').className='summary-value '+(marginUsd<0?'negative':'positive');byId('sumMarginPctUsd').innerHTML=formatPct(pctUsd);
    var custCount=0;for(var ck in custMap){if(custMap.hasOwnProperty(ck))custCount++;}
    byId('sumCustomer').innerHTML=formatNum(custCount,0);
}

function aggregateSelectedItemByPeriod(code){
    var map={},i,r,key,a,result=[];
    for(i=0;i<ALL_DATA.length;i++){
        r=ALL_DATA[i];
        if(r.item_code!==code)continue;
        key=String(r.period_key);
        if(!map[key]){
            map[key]={
                period_key:Number(r.period_key),
                sales_year:Number(r.sales_year),
                sales_month:Number(r.sales_month),
                sales_qty:0,total_hpp_idr:0,total_sales_idr:0,
                total_hpp_usd:0,total_sales_usd:0
            };
        }
        a=map[key];
        a.sales_qty+=Number(r.sales_qty||0);
        a.total_hpp_idr+=Number(r.total_hpp_idr||0);
        a.total_sales_idr+=Number(r.total_sales_idr||0);
        a.total_hpp_usd+=Number(r.total_hpp_usd||0);
        a.total_sales_usd+=Number(r.total_sales_usd||0);
    }

    for(key in map){
        if(map.hasOwnProperty(key)){
            a=map[key];
            a.hpp_unit_idr=a.sales_qty!==0?a.total_hpp_idr/a.sales_qty:0;
            a.sales_unit_idr=a.sales_qty!==0?a.total_sales_idr/a.sales_qty:0;
            a.hpp_unit_usd=a.sales_qty!==0?a.total_hpp_usd/a.sales_qty:0;
            a.sales_unit_usd=a.sales_qty!==0?a.total_sales_usd/a.sales_qty:0;
            result.push(a);
        }
    }

    result.sort(function(x,y){return Number(x.period_key)-Number(y.period_key);});
    return result;
}

function renderChart(){
    var code=SELECTED_ITEM_CODE||resolveItemCode(byId('chartItemSearch').value);
    if(code&&code!==SELECTED_ITEM_CODE)SELECTED_ITEM_CODE=code;

    /*
        Karena tabel dipisahkan per customer, grafik item dijumlahkan kembali
        per periode agar label tahun/bulan tidak berulang. Harga unit adalah
        weighted average berdasarkan sales quantity seluruh customer.
    */
    var rows=aggregateSelectedItemByPeriod(code);
    var labels=[],hppIdr=[],salesIdr=[],hppUsd=[],salesUsd=[],i;

    for(i=0;i<rows.length;i++){
        labels.push(periodLabel(rows[i]));
        hppIdr.push(Number(rows[i].hpp_unit_idr||0));
        salesIdr.push(Number(rows[i].sales_unit_idr||0));
        hppUsd.push(Number(rows[i].hpp_unit_usd||0));
        salesUsd.push(Number(rows[i].sales_unit_usd||0));
    }

    var ctxIdr=byId('priceChartIdr').getContext('2d');
    if(PRICE_CHART_IDR)PRICE_CHART_IDR.destroy();
    PRICE_CHART_IDR=new Chart(ctxIdr,{
        type:'bar',
        data:{
            labels:labels,
            datasets:[
                {
                    label:'HPP / Unit IDR',
                    data:hppIdr,
                    backgroundColor:'rgba(220,38,38,.65)',
                    borderColor:'#dc2626',
                    borderWidth:1,
                    borderRadius:4,
                    order:3
                },
                {
                    label:'Sales / Unit IDR',
                    data:salesIdr,
                    backgroundColor:'rgba(4,120,87,.58)',
                    borderColor:'#047857',
                    borderWidth:1,
                    borderRadius:4,
                    order:2
                },
                {
                    type:'line',
                    label:'Trend Harga Sales IDR',
                    data:salesIdr,
                    borderColor:'#0f172a',
                    backgroundColor:'rgba(15,23,42,0)',
                    borderWidth:3,
                    pointRadius:4,
                    pointHoverRadius:6,
                    pointBackgroundColor:'#0f172a',
                    tension:.25,
                    fill:false,
                    order:1
                }
            ]
        },
        options:{
            responsive:true,
            maintainAspectRatio:false,
            interaction:{mode:'index',intersect:false},
            plugins:{
                tooltip:{callbacks:{label:function(c){return c.dataset.label+': '+formatIDR(c.raw);}}}
            },
            scales:{
                y:{beginAtZero:true,ticks:{callback:function(v){return 'Rp '+Number(v).toLocaleString('id-ID');}}}
            }
        }
    });

    var ctxUsd=byId('priceChartUsd').getContext('2d');
    if(PRICE_CHART_USD)PRICE_CHART_USD.destroy();
    PRICE_CHART_USD=new Chart(ctxUsd,{
        type:'bar',
        data:{
            labels:labels,
            datasets:[
                {
                    label:'HPP / Unit USD',
                    data:hppUsd,
                    backgroundColor:'rgba(220,38,38,.65)',
                    borderColor:'#dc2626',
                    borderWidth:1,
                    borderRadius:4,
                    order:3
                },
                {
                    label:'Sales / Unit USD',
                    data:salesUsd,
                    backgroundColor:'rgba(4,120,87,.58)',
                    borderColor:'#047857',
                    borderWidth:1,
                    borderRadius:4,
                    order:2
                },
                {
                    type:'line',
                    label:'Trend Harga Sales USD',
                    data:salesUsd,
                    borderColor:'#0f172a',
                    backgroundColor:'rgba(15,23,42,0)',
                    borderWidth:3,
                    pointRadius:4,
                    pointHoverRadius:6,
                    pointBackgroundColor:'#0f172a',
                    tension:.25,
                    fill:false,
                    order:1
                }
            ]
        },
        options:{
            responsive:true,
            maintainAspectRatio:false,
            interaction:{mode:'index',intersect:false},
            plugins:{
                tooltip:{callbacks:{label:function(c){return c.dataset.label+': '+formatUSD(c.raw);}}}
            },
            scales:{
                y:{beginAtZero:true,ticks:{callback:function(v){return '$ '+Number(v).toLocaleString('en-US',{maximumFractionDigits:4});}}}
            }
        }
    });
}

function excelDetailHeader(){
    return ['Periode','Plant','Customer Code','Customer Name','Item Code','Item Name','Qty Sales',
        'HPP / Unit IDR','Sales / Unit IDR','Selisih / Unit IDR',
        'HPP / Unit USD','Sales / Unit USD','Selisih / Unit USD',
        'Total HPP IDR','Total Sales IDR','Margin IDR','Margin % IDR',
        'Total HPP USD','Total Sales USD','Margin USD','Margin % USD',
        'Sumber HPP','Error'];
}

function exportExcel(){
    if(!FILTERED_DATA.length)return;

    var rows=[
        ['PT. IMC TEKNO INDONESIA'],
        ['PERBANDINGAN HPP VS SALES PER ITEM PER CUSTOMER - IDR & USD'],
        ['Pencarian aktif: '+(byId('searchText').value||'Semua Item')],
        []
    ];
    var rowTypes=['title','title','note','blank'];
    var groups={},order=[],i,r,key,g;

    for(i=0;i<FILTERED_DATA.length;i++){
        r=FILTERED_DATA[i];
        key=customerKey(r);
        if(!groups[key]){
            groups[key]={
                cust_code:r.cust_code||'TANPA-CUSTOMER',
                cust_comp:r.cust_comp||'TANPA CUSTOMER',
                details:[],
                sales_qty:0,total_hpp_idr:0,total_sales_idr:0,
                total_hpp_usd:0,total_sales_usd:0,error_lines:0
            };
            order.push(key);
        }
        g=groups[key];
        g.details.push(r);
        g.sales_qty+=Number(r.sales_qty||0);
        g.total_hpp_idr+=Number(r.total_hpp_idr||0);
        g.total_sales_idr+=Number(r.total_sales_idr||0);
        g.total_hpp_usd+=Number(r.total_hpp_usd||0);
        g.total_sales_usd+=Number(r.total_sales_usd||0);
        g.error_lines+=Number(r.error_lines||0);
    }

    var grand={sales_qty:0,total_hpp_idr:0,total_sales_idr:0,total_hpp_usd:0,total_sales_usd:0,error_lines:0};

    for(var oi=0;oi<order.length;oi++){
        key=order[oi];
        g=groups[key];

        rows.push(['CUSTOMER: '+g.cust_code+' - '+g.cust_comp]);
        rowTypes.push('customer');

        rows.push(excelDetailHeader());
        rowTypes.push('header');

        for(i=0;i<g.details.length;i++){
            r=g.details[i];
            rows.push([
                periodLabel(r),r.plants,r.cust_code,r.cust_comp,r.item_code,r.item_name,Number(r.sales_qty),
                Number(r.hpp_unit_idr),Number(r.sales_unit_idr),Number(r.unit_difference_idr),
                Number(r.hpp_unit_usd),Number(r.sales_unit_usd),Number(r.unit_difference_usd),
                Number(r.total_hpp_idr),Number(r.total_sales_idr),Number(r.margin_idr),
                r.margin_percent===null?'':Number(r.margin_percent)/100,
                Number(r.total_hpp_usd),Number(r.total_sales_usd),Number(r.margin_usd),
                r.margin_percent_usd===null?'':Number(r.margin_percent_usd)/100,
                r.hpp_source,Number(r.error_lines)
            ]);
            rowTypes.push('detail');
        }

        var qty=g.sales_qty;
        var hppUnit=qty!==0?g.total_hpp_idr/qty:0;
        var salesUnit=qty!==0?g.total_sales_idr/qty:0;
        var hppUnitUsd=qty!==0?g.total_hpp_usd/qty:0;
        var salesUnitUsd=qty!==0?g.total_sales_usd/qty:0;
        var margin=g.total_sales_idr-g.total_hpp_idr;
        var marginUsd=g.total_sales_usd-g.total_hpp_usd;

        rows.push([
            'SUBTOTAL CUSTOMER','','','','','',qty,
            hppUnit,salesUnit,salesUnit-hppUnit,
            hppUnitUsd,salesUnitUsd,salesUnitUsd-hppUnitUsd,
            g.total_hpp_idr,g.total_sales_idr,margin,
            g.total_sales_idr!==0?margin/g.total_sales_idr:'',
            g.total_hpp_usd,g.total_sales_usd,marginUsd,
            g.total_sales_usd!==0?marginUsd/g.total_sales_usd:'',
            '',g.error_lines
        ]);
        rowTypes.push('subtotal');
        rows.push([]);
        rowTypes.push('blank');

        grand.sales_qty+=g.sales_qty;
        grand.total_hpp_idr+=g.total_hpp_idr;
        grand.total_sales_idr+=g.total_sales_idr;
        grand.total_hpp_usd+=g.total_hpp_usd;
        grand.total_sales_usd+=g.total_sales_usd;
        grand.error_lines+=g.error_lines;
    }

    var gQty=grand.sales_qty;
    var gHppUnit=gQty!==0?grand.total_hpp_idr/gQty:0;
    var gSalesUnit=gQty!==0?grand.total_sales_idr/gQty:0;
    var gHppUnitUsd=gQty!==0?grand.total_hpp_usd/gQty:0;
    var gSalesUnitUsd=gQty!==0?grand.total_sales_usd/gQty:0;
    var gMargin=grand.total_sales_idr-grand.total_hpp_idr;
    var gMarginUsd=grand.total_sales_usd-grand.total_hpp_usd;

    rows.push([
        'GRAND TOTAL','','','','','',gQty,
        gHppUnit,gSalesUnit,gSalesUnit-gHppUnit,
        gHppUnitUsd,gSalesUnitUsd,gSalesUnitUsd-gHppUnitUsd,
        grand.total_hpp_idr,grand.total_sales_idr,gMargin,
        grand.total_sales_idr!==0?gMargin/grand.total_sales_idr:'',
        grand.total_hpp_usd,grand.total_sales_usd,gMarginUsd,
        grand.total_sales_usd!==0?gMarginUsd/grand.total_sales_usd:'',
        '',grand.error_lines
    ]);
    rowTypes.push('grand');

    var ws=XLSX.utils.aoa_to_sheet(rows);
    ws['!cols']=[
        {wch:18},{wch:10},{wch:16},{wch:32},{wch:16},{wch:40},{wch:14},
        {wch:19},{wch:19},{wch:19},
        {wch:17},{wch:17},{wch:17},
        {wch:20},{wch:20},{wch:20},{wch:13},
        {wch:17},{wch:17},{wch:17},{wch:13},
        {wch:20},{wch:8}
    ];
    ws['!merges']=[
        {s:{r:0,c:0},e:{r:0,c:22}},
        {s:{r:1,c:0},e:{r:1,c:22}},
        {s:{r:2,c:0},e:{r:2,c:22}}
    ];

    for(var rr=0;rr<rows.length;rr++){
        var type=rowTypes[rr]||'detail';

        if(type==='customer'){
            ws['!merges'].push({s:{r:rr,c:0},e:{r:rr,c:22}});
        }

        if(type==='title'){
            var titleCell=XLSX.utils.encode_cell({r:rr,c:0});
            if(ws[titleCell])ws[titleCell].s={font:{bold:true,sz:rr===0?14:12},alignment:{horizontal:'center'}};
        }else if(type==='note'){
            var noteCell=XLSX.utils.encode_cell({r:rr,c:0});
            if(ws[noteCell])ws[noteCell].s={alignment:{horizontal:'center'}};
        }else if(type==='customer'){
            var custCell=XLSX.utils.encode_cell({r:rr,c:0});
            if(ws[custCell])ws[custCell].s={font:{bold:true,color:{rgb:'1E3A8A'}},fill:{fgColor:{rgb:'DBEAFE'}}};
        }else if(type==='header'){
            for(var hc=0;hc<23;hc++){
                var hCell=XLSX.utils.encode_cell({r:rr,c:hc});
                if(ws[hCell])ws[hCell].s={font:{bold:true},fill:{fgColor:{rgb:'DCE6F1'}},alignment:{horizontal:'center'}};
            }
        }else if(type==='subtotal'||type==='grand'){
            for(var tc=0;tc<23;tc++){
                var tCell=XLSX.utils.encode_cell({r:rr,c:tc});
                if(ws[tCell])ws[tCell].s={
                    font:{bold:true},
                    fill:{fgColor:{rgb:type==='grand'?'CBD5E1':'F1F5F9'}}
                };
            }
        }

        if(type==='detail'||type==='subtotal'||type==='grand'){
            for(var cc=7;cc<=9;cc++){
                var idrUnit=XLSX.utils.encode_cell({r:rr,c:cc});
                if(ws[idrUnit])ws[idrUnit].z='#,##0.00';
            }
            for(var cIdr=13;cIdr<=15;cIdr++){
                var idrCell=XLSX.utils.encode_cell({r:rr,c:cIdr});
                if(ws[idrCell])ws[idrCell].z='#,##0';
            }
            for(var cUsd=10;cUsd<=12;cUsd++){
                var usdUnit=XLSX.utils.encode_cell({r:rr,c:cUsd});
                if(ws[usdUnit])ws[usdUnit].z='$#,##0.0000';
            }
            for(var cUsdTot=17;cUsdTot<=19;cUsdTot++){
                var usdCell=XLSX.utils.encode_cell({r:rr,c:cUsdTot});
                if(ws[usdCell])ws[usdCell].z='$#,##0.00';
            }
            var pIdr=XLSX.utils.encode_cell({r:rr,c:16});
            if(ws[pIdr])ws[pIdr].z='0.00%';
            var pUsd=XLSX.utils.encode_cell({r:rr,c:20});
            if(ws[pUsd])ws[pUsd].z='0.00%';
        }
    }

    var wb=XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb,ws,'HPP vs Sales Customer');
    XLSX.writeFile(wb,'HPP_vs_Sales_per_Customer_'+byId('mode').value+'.xlsx');
}

byId('mode').onchange=function(){updateModeFields();};
byId('applyBtn').onclick=loadData;
byId('excelBtn').onclick=exportExcel;
byId('searchText').onchange=applySearch;
byId('showAllBtn').onclick=showAllItems;
byId('pageSize').onchange=function(){CURRENT_PAGE=1;renderTable();};
byId('chartItemSearch').onchange=function(){var code=resolveItemCode(this.value);if(code)setSelectedItem(code,true);};
byId('prevBtn').onclick=function(){if(CURRENT_PAGE>1){CURRENT_PAGE--;renderTable();}};
byId('nextBtn').onclick=function(){var sv=byId('pageSize').value;var size=(sv==='ALL')?FILTERED_DATA.length:(parseInt(sv,10)||50);if(size<=0)size=1;if(CURRENT_PAGE<Math.ceil(FILTERED_DATA.length/size)){CURRENT_PAGE++;renderTable();}};

document.addEventListener('DOMContentLoaded',function(){initYears();updateModeFields();setupAutocomplete('chartItemSearch','chartItemSuggest','chart');setupAutocomplete('searchText','searchItemSuggest','search');loadData();});
</script>
</body>
</html>

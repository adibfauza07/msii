<?php
/**
 * DAILY PRODUCTION VENDOR REPORT - SUMMARY PER ITEM
 * Compatible target: PHP 5.4 + Microsoft SQL Server 2008 + sqlsrv
 */

require_once __DIR__ . "/../config/global.php";

/* =========================================================
 * 1. CONNECTION
 * ========================================================= */
$db = null;
if (isset($conn)) {
    $db = $conn;
} elseif (isset($connection)) {
    $db = $connection;
} elseif (isset($dbconn)) {
    $db = $dbconn;
}

if (!$db) {
    die('Koneksi database tidak ditemukan. Pastikan global.php membuat variabel $conn.');
}

if (!function_exists('sqlsrv_query')) {
    die('Extension sqlsrv belum aktif pada PHP.');
}

/* =========================================================
 * 2. HELPERS — PHP 5.4 SAFE
 * ========================================================= */
function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function num($value)
{
    if ($value === null || $value === '') {
        return 0;
    }
    return (float)$value;
}

function fmt0($value)
{
    return number_format(num($value), 0, '.', ',');
}

function fmtPct($value, $decimals)
{
    return number_format(num($value), (int)$decimals, '.', ',') . ' %';
}

function validDateYmd($value, $fallback)
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $fallback;
    }
    $ts = strtotime($value . ' 00:00:00');
    if ($ts === false || date('Y-m-d', $ts) !== $value) {
        return $fallback;
    }
    return $value;
}

function dateDisplay($ymd)
{
    $ts = strtotime($ymd);
    return $ts ? date('d-m-Y', $ts) : $ymd;
}

function sqlsrvErrorText()
{
    $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
    if (!$errors) {
        return 'Unknown SQL Server error.';
    }
    $out = array();
    foreach ($errors as $err) {
        $out[] = '[' . $err['SQLSTATE'] . '] ' . $err['code'] . ' - ' . $err['message'];
    }
    return implode("\n", $out);
}

function xlsSafe($value)
{
    $v = (string)$value;
    $v = str_replace("\r\n", "\n", $v);
    $v = str_replace("\r", "\n", $v);
    return $v;
}

/* =========================================================
 * 3. FILTER
 * ========================================================= */
$defaultStart = date('Y-m-01');
$defaultEnd   = date('Y-m-d');

$startDate = validDateYmd(isset($_GET['start_date']) ? $_GET['start_date'] : $defaultStart, $defaultStart);
$endDate   = validDateYmd(isset($_GET['end_date']) ? $_GET['end_date'] : $defaultEnd, $defaultEnd);

if (strtotime($startDate) > strtotime($endDate)) {
    $tmp = $startDate;
    $startDate = $endDate;
    $endDate = $tmp;
}

$code      = isset($_GET['code']) && $_GET['code'] !== '' ? trim($_GET['code']) : '%';
$vendCode  = isset($_GET['vend_code']) && $_GET['vend_code'] !== '' ? trim($_GET['vend_code']) : '%';

$startSql = $startDate . ' 00:00:00.000';
$endSql   = $endDate . ' 23:59:59.997';

/* =========================================================
 * 4. EXECUTE STORED PROCEDURE
 * ========================================================= */
$sql    = "{CALL dbo.sp_daily_prod_vendor(?, ?, ?)}";
$params = array($startSql, $endSql, $code);
$stmt   = sqlsrv_query($db, $sql, $params);

if ($stmt === false) {
    die('<pre style="white-space:pre-wrap;color:#a00">' . h(sqlsrvErrorText()) . '</pre>');
}

$rows = array();
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if ($vendCode !== '%' && isset($r['VEND_CODE']) && trim($r['VEND_CODE']) !== $vendCode) {
        continue;
    }
    $rows[] = $r;
}
sqlsrv_free_stmt($stmt);

/* =========================================================
 * 5. GROUP BY VENDOR -> THEN BY ITEM (SUMMARY)
 * ========================================================= */
$groups = array();
foreach ($rows as $r) {
    $vend = isset($r['VEND_CODE']) ? trim((string)$r['VEND_CODE']) : '';
    $comp = isset($r['VEND_COMP']) ? trim((string)$r['VEND_COMP']) : '';
    $itemCode = isset($r['ITEM_CODE']) ? trim((string)$r['ITEM_CODE']) : '';
    $itemName = isset($r['ITEM_NAME']) ? trim((string)$r['ITEM_NAME']) : '';
    
    $groupKey = $vend . '|' . $comp;
    $itemKey  = $itemCode . '|' . $itemName;

    // Inisialisasi Group Vendor jika belum ada
    if (!isset($groups[$groupKey])) {
        $groups[$groupKey] = array(
            'vend_code' => $vend,
            'vend_comp' => $comp,
            'ok'        => 0,
            'hold'      => 0,
            'ng'        => 0,
            'items'     => array()
        );
    }

    // Inisialisasi Item di dalam Vendor jika belum ada
    if (!isset($groups[$groupKey]['items'][$itemKey])) {
        $groups[$groupKey]['items'][$itemKey] = array(
            'item_code' => $itemCode,
            'item_name' => $itemName,
            'ok'        => 0,
            'hold'      => 0,
            'ng'        => 0
        );
    }

    // Akumulasi data level item
    $ok   = num(isset($r['PD_OK']) ? $r['PD_OK'] : 0);
    $hold = num(isset($r['PD_HOLD']) ? $r['PD_HOLD'] : 0);
    $ng   = num(isset($r['PD_NG']) ? $r['PD_NG'] : 0);

    $groups[$groupKey]['items'][$itemKey]['ok']   += $ok;
    $groups[$groupKey]['items'][$itemKey]['hold'] += $hold;
    $groups[$groupKey]['items'][$itemKey]['ng']   += $ng;

    // Akumulasi data level vendor (total)
    $groups[$groupKey]['ok']   += $ok;
    $groups[$groupKey]['hold'] += $hold;
    $groups[$groupKey]['ng']   += $ng;
}

// Sorting Vendor by Vend Code
ksort($groups);
foreach ($groups as $gk => $g) {
    // Sorting Item by Item Code di dalam masing-masing vendor
    ksort($groups[$gk]['items']);
}

/* =========================================================
 * 6. GRAND TOTAL CALCULATION
 * ========================================================= */
$grandOk   = 0;
$grandHold = 0;
$grandNg   = 0;

foreach ($groups as $g) {
    $grandOk   += $g['ok'];
    $grandHold += $g['hold'];
    $grandNg   += $g['ng'];
}

$grandTotalOutput = $grandOk + $grandHold + $grandNg;
$grandNgPct       = $grandTotalOutput != 0 ? ($grandNg / $grandTotalOutput) * 100 : 0;

/* =========================================================
 * 7. EXPORT XLS
 * ========================================================= */
if (isset($_GET['export']) && $_GET['export'] === 'xls') {

    $filename = 'Summary_Prod_Vendor_' . $startDate . '_to_' . $endDate . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: no-cache');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"><style>td,th{mso-number-format:\@;vertical-align:middle;font-size:11px;font-family:Arial,sans-serif;}th{background-color:#d9e1f2;font-weight:bold;text-align:center;}.n{mso-number-format:\#\,\#\#0;text-align:right;}.n2{mso-number-format:\#\,\#\#0\.00;text-align:right;}.cust{background-color:#e8f0dc;font-weight:bold;font-size:12px;}.sum{background-color:#f2f2f2;font-weight:bold;}</style></head><body>';

    echo '<table border="1" cellspacing="0" cellpadding="2">';
    echo '<tr><td colspan="6" style="font-size:14px;font-weight:bold;text-align:center;">SUMMARY PRODUCTION VENDOR PER ITEM</td></tr>';
    echo '<tr><td style="font-weight:bold;">DARI TANGGAL :</td><td>' . xlsSafe(dateDisplay($startDate)) . '</td><td style="font-weight:bold;">KE TANGGAL :</td><td>' . xlsSafe(dateDisplay($endDate)) . '</td><td colspan="2"></td></tr>';

    echo '<tr>';
    echo '<th>ITEM CODE</th>';
    echo '<th>ITEM NAME</th>';
    echo '<th>TOTAL OK</th>';
    echo '<th>TOTAL HOLD</th>';
    echo '<th>TOTAL NG</th>';
    echo '<th>NG %</th>';
    echo '</tr>';

    if (count($groups) === 0) {
        echo '<tr><td colspan="6" style="text-align:center;">Data tidak ditemukan.</td></tr>';
    } else {
        foreach ($groups as $g) {
            echo '<tr class="cust"><td colspan="6">' . xlsSafe($g['vend_code']) . ' - ' . xlsSafe($g['vend_comp']) . '</td></tr>';
            
            foreach ($g['items'] as $item) {
                $itemTotal = $item['ok'] + $item['hold'] + $item['ng'];
                $itemNgPct = $itemTotal != 0 ? ($item['ng'] / $itemTotal) * 100 : 0;

                echo '<tr>';
                echo '<td>' . xlsSafe($item['item_code']) . '</td>';
                echo '<td>' . xlsSafe($item['item_name']) . '</td>';
                echo '<td class="n">' . num($item['ok']) . '</td>';
                echo '<td class="n">' . num($item['hold']) . '</td>';
                echo '<td class="n">' . num($item['ng']) . '</td>';
                echo '<td class="n2">' . round($itemNgPct, 2) . ' %</td>';
                echo '</tr>';
            }
            
            // Subtotal per vendor
            $gTotalOutput = $g['ok'] + $g['hold'] + $g['ng'];
            $gNgPct = $gTotalOutput != 0 ? ($g['ng'] / $gTotalOutput) * 100 : 0;
            
            echo '<tr class="sum">';
            echo '<td colspan="2" style="text-align:right;">SUBTOTAL VENDOR :</td>';
            echo '<td class="n">' . num($g['ok']) . '</td>';
            echo '<td class="n">' . num($g['hold']) . '</td>';
            echo '<td class="n">' . num($g['ng']) . '</td>';
            echo '<td class="n2">' . round($gNgPct, 2) . ' %</td>';
            echo '</tr>';
        }
    }

    echo '</table></body></html>';
    exit;
}

/* =========================================================
 * 8. NORMAL HTML OUTPUT — Bootstrap 3
 * ========================================================= */ ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Summary Production Vendor Report</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">

    <style>
        body { background: #f5f5f5; font-size: 12px; padding-top: 10px; }
        .panel-filter .panel-heading { padding: 6px 12px; background: #337ab7; border-color: #2e6da4; }
        .panel-filter .panel-heading h4 { margin: 0; color: #fff; font-size: 14px; font-weight: 600; }
        .panel-filter .panel-body { padding: 10px 12px; }
        .filter-group label { font-weight: 600; font-size: 11px; margin-bottom: 2px; color: #333; }
        .filter-group .form-control { font-size: 12px; height: 30px; padding: 3px 8px; }
        .btn-filter { height: 30px; padding: 0 14px; font-size: 12px; }

        .period-bar { background: #fff; border: 1px solid #ddd; border-radius: 3px; padding: 8px 12px; margin-bottom: 12px; }
        .period-bar .plabel { font-weight: 600; color: #555; font-size: 11px; }
        .period-bar .pvalue { font-weight: 700; color: #333; font-size: 12px; }

        .table-report { background: #fff; border: 1px solid #ddd; font-size: 11px; width: 100%; margin-bottom: 0; }
        .table-report > thead > tr > th { text-align: center; font-size: 11px; background: #eef2f9 !important; border: 1px solid #999 !important; padding: 6px; white-space: nowrap; vertical-align: middle; }
        .table-report > tbody > tr > td { border: 1px solid #999 !important; padding: 6px; vertical-align: middle; }
        .table-report .tr-cust td { font-weight: 700; font-size: 12px; background: #e8f0dc !important; }
        .table-report .tr-even td { background-color: #ffffff !important; }
        .table-report .tr-odd td { background-color: #f4f6f9 !important; }
        .table-report .tr-subtotal td { background-color: #f2f2f2 !important; font-weight: 700; }

        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; white-space: nowrap; }
        .left { text-align: left; }
        .empty-msg { padding: 24px !important; text-align: center; font-size: 12px !important; color: #999; }
    </style>
</head>
<body>

<div class="container" style="min-width:960px;">

    <div class="panel panel-primary panel-filter">
        <div class="panel-heading">
            <h4 class="panel-title">
                <span class="glyphicon glyphicon-filter"></span> Filter Summary Laporan Vendor
            </h4>
        </div>
        <div class="panel-body">
            <form class="form-inline" method="get" action="" id="filterForm">
                <div class="form-group filter-group" style="margin-right:10px;">
                    <label for="start_date">Dari Tanggal</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo h($startDate); ?>">
                </div>

                <div class="form-group filter-group" style="margin-right:10px;">
                    <label for="end_date">Ke Tanggal</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo h($endDate); ?>">
                </div>

                <div class="form-group filter-group" style="margin-right:10px;">
                    <label for="code">Item Code</label>
                    <input type="text" class="form-control" id="code" name="code" value="<?php echo h($code === '%' ? '' : $code); ?>" placeholder="Cari item...">
                </div>

                <div class="form-group filter-group" style="margin-right:10px;">
                    <label for="vend_code">Vendor Code</label>
                    <input type="text" class="form-control" id="vend_code" name="vend_code" value="<?php echo h($vendCode === '%' ? '' : $vendCode); ?>" placeholder="Cari vendor...">
                </div>

                <div class="form-group" style="margin-right:6px; vertical-align:bottom;">
                    <label>&nbsp;</label><br>
                    <button type="submit" class="btn btn-primary btn-filter">
                        <span class="glyphicon glyphicon-search"></span> Tampilkan
                    </button>
                </div>
                <div class="form-group" style="margin-right:6px; vertical-align:bottom;">
                    <label>&nbsp;</label><br>
                    <button type="button" class="btn btn-default btn-filter" onclick="window.print();">
                        <span class="glyphicon glyphicon-print"></span> Print
                    </button>
                </div>
                <div class="form-group" style="vertical-align:bottom;">
                    <label>&nbsp;</label><br>
                    <a href="<?php
                        $expParams = array(
                            'start_date' => $startDate,
                            'end_date'   => $endDate,
                            'code'       => $code,
                            'vend_code'  => $vendCode,
                            'export'     => 'xls'
                        );
                        echo '?' . http_build_query($expParams, '', '&amp;');
                    ?>" class="btn btn-success btn-filter">
                        <span class="glyphicon glyphicon-download"></span> Export XLS
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- GRAND TOTAL & PERIOD BAR -->
    <div class="period-bar">
        <span class="plabel">DARI TANGGAL :</span>
        <span class="pvalue"><?php echo h(dateDisplay($startDate)); ?></span>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <span class="plabel">KE TANGGAL :</span>
        <span class="pvalue"><?php echo h(dateDisplay($endDate)); ?></span>
        
        <span style="float: right; border-left: 2px solid #ccc; padding-left: 15px;">
            <span class="plabel">GRAND TOTAL OK :</span>
            <span class="pvalue" style="color: #28a745;"><?php echo fmt0($grandOk); ?></span>
            &nbsp;&nbsp;&nbsp;
            <span class="plabel">GRAND TOTAL HOLD :</span>
            <span class="pvalue" style="color: #ffc107;"><?php echo fmt0($grandHold); ?></span>
            &nbsp;&nbsp;&nbsp;
            <span class="plabel">GRAND TOTAL NG :</span>
            <span class="pvalue" style="color: #dc3545;"><?php echo fmt0($grandNg); ?></span>
            &nbsp;&nbsp;&nbsp;
            <span class="plabel">GRAND TOTAL NG % :</span>
            <span class="pvalue" style="color: #dc3545;"><?php echo fmtPct($grandNgPct, 2); ?></span>
        </span>
        <div style="clear: both;"></div>
    </div>

    <div class="table-responsive">
        <table class="table table-condensed table-report">
            <thead>
                <tr>
                    <th>ITEM CODE</th>
                    <th>ITEM NAME</th>
                    <th width="12%">TOTAL OK</th>
                    <th width="12%">TOTAL HOLD</th>
                    <th width="12%">TOTAL NG</th>
                    <th width="10%">NG %</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($groups) === 0): ?>
                <tr><td colspan="6" class="empty-msg">Data tidak ditemukan untuk filter yang dipilih.</td></tr>
            <?php else: ?>
                <?php foreach ($groups as $g): ?>
                    <tr class="tr-cust">
                        <td colspan="6">
                            <span class="glyphicon glyphicon-briefcase" style="margin-right:4px;"></span>
                            <?php echo h($g['vend_code']); ?> &nbsp;&mdash;&nbsp; <?php echo h($g['vend_comp']); ?>
                        </td>
                    </tr>

                    <?php $index = 0; foreach ($g['items'] as $item): ?>
                        <?php
                        $itemTotal = $item['ok'] + $item['hold'] + $item['ng'];
                        $itemNgPct = $itemTotal != 0 ? ($item['ng'] / $itemTotal) * 100 : 0;
                        $rowClass = ($index % 2 === 0) ? 'tr-even' : 'tr-odd';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td class="left"><?php echo h($item['item_code']); ?></td>
                            <td class="left"><?php echo h($item['item_name']); ?></td>
                            <td class="num"><?php echo fmt0($item['ok']); ?></td>
                            <td class="num"><?php echo fmt0($item['hold']); ?></td>
                            <td class="num"><?php echo fmt0($item['ng']); ?></td>
                            <td class="num"><?php echo round($itemNgPct, 2); ?> %</td>
                        </tr>
                    <?php $index++; endforeach; ?>
                    
                    <!-- SUBTOTAL VENDOR -->
                    <?php 
                        $gTotalOutput = $g['ok'] + $g['hold'] + $g['ng'];
                        $gNgPct = $gTotalOutput != 0 ? ($g['ng'] / $gTotalOutput) * 100 : 0;
                    ?>
                    <tr class="tr-subtotal">
                        <td colspan="2" class="num">SUBTOTAL <?php echo h($g['vend_code']); ?> :</td>
                        <td class="num" style="color: #28a745;"><?php echo fmt0($g['ok']); ?></td>
                        <td class="num" style="color: #d39e00;"><?php echo fmt0($g['hold']); ?></td>
                        <td class="num" style="color: #dc3545;"><?php echo fmt0($g['ng']); ?></td>
                        <td class="num"><?php echo round($gNgPct, 2); ?> %</td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
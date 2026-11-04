<?php
/**
 * DAILY PRODUCTION VENDOR REPORT
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

function fmt2($value)
{
    return number_format(num($value), 2, '.', ',');
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
    // Filter tambahan untuk vendor jika diperlukan di sisi PHP
    if ($vendCode !== '%' && isset($r['VEND_CODE']) && trim($r['VEND_CODE']) !== $vendCode) {
        continue;
    }
    $rows[] = $r;
}
sqlsrv_free_stmt($stmt);

/* =========================================================
 * 5. SORTING
 * ========================================================= */
usort($rows, function ($a, $b) {
    $keys = array('VEND_CODE', 'MAC_CODE', 'ITEM_CODE', 'PD_LOT');
    foreach ($keys as $key) {
        $av = isset($a[$key]) ? (string)$a[$key] : '';
        $bv = isset($b[$key]) ? (string)$b[$key] : '';
        $cmp = strnatcasecmp($av, $bv);
        if ($cmp !== 0) {
            return $cmp;
        }
    }
    return 0;
});

/* =========================================================
 * 6. GROUP BY VENDOR + CALCULATE ACCUMULATIONS
 * ========================================================= */
$groups = array();
foreach ($rows as $r) {
    $vend = isset($r['VEND_CODE']) ? trim((string)$r['VEND_CODE']) : '';
    $comp = isset($r['VEND_COMP']) ? trim((string)$r['VEND_COMP']) : '';
    $groupKey = $vend . '|' . $comp;

    if (!isset($groups[$groupKey])) {
        $groups[$groupKey] = array(
            'vend_code' => $vend,
            'vend_comp' => $comp,
            'ok'        => 0,
            'hold'      => 0,
            'ng'        => 0,
            'rows'      => array()
        );
    }

    $groups[$groupKey]['ok']   += num(isset($r['PD_OK']) ? $r['PD_OK'] : 0);
    $groups[$groupKey]['hold'] += num(isset($r['PD_HOLD']) ? $r['PD_HOLD'] : 0);
    $groups[$groupKey]['ng']   += num(isset($r['PD_NG']) ? $r['PD_NG'] : 0);
    
    $groups[$groupKey]['rows'][] = $r;
}

/* =========================================================
 * 7. FORMULA NG
 * ========================================================= */
function ngPercentValue($r)
{
    $ok   = num(isset($r['PD_OK']) ? $r['PD_OK'] : 0);
    $hold = num(isset($r['PD_HOLD']) ? $r['PD_HOLD'] : 0);
    $ng   = num(isset($r['PD_NG']) ? $r['PD_NG'] : 0);
    $total = $ok + $hold + $ng;
    return $total != 0 ? ($ng / $total) * 100 : 0;
}

/* =========================================================
 * GRAND TOTAL CALCULATION
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
 * 8. EXPORT XLS
 * ========================================================= */
if (isset($_GET['export']) && $_GET['export'] === 'xls') {

    $filename = 'Daily_Production_Vendor_' . $startDate . '_to_' . $endDate . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: no-cache');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"><style>td,th{mso-number-format:\@;vertical-align:middle;font-size:10px;font-family:Arial,sans-serif;}th{background-color:#d9e1f2;font-weight:bold;text-align:center;}.n{mso-number-format:\#\,\#\#0;text-align:right;}.n2{mso-number-format:\#\,\#\#0\.00;text-align:right;}.cust{background-color:#e8f0dc;font-weight:bold;font-size:11px;}.sum{background-color:#f2f2f2;font-weight:bold;}</style></head><body>';

    echo '<table border="1" cellspacing="0" cellpadding="2">';
    echo '<tr><td colspan="15" style="font-size:14px;font-weight:bold;text-align:center;">DAILY PRODUCTION VENDOR</td></tr>';
    echo '<tr><td style="font-weight:bold;">DARI TANGGAL :</td><td>' . xlsSafe(dateDisplay($startDate)) . '</td><td style="font-weight:bold;">KE TANGGAL :</td><td>' . xlsSafe(dateDisplay($endDate)) . '</td><td colspan="11"></td></tr>';

    echo '<tr>';
    echo '<th>MC CODE</th>';
    echo '<th>STATION</th>';
    echo '<th>ITEM CODE</th>';
    echo '<th>ITEM NAME</th>';
    echo '<th>PD DATE</th>';
    echo '<th>LOT</th>';
    echo '<th>OK</th>';
    echo '<th>HOLD</th>';
    echo '<th>NG</th>';
    echo '<th>CAV</th>';
    echo '<th>CT</th>';
    echo '<th>CYTM</th>';
    echo '<th>CAVT</th>';
    echo '<th>NG %</th>';
    echo '</tr>';

    if (count($groups) === 0) {
        echo '<tr><td colspan="14" style="text-align:center;">Data tidak ditemukan.</td></tr>';
    } else {
        foreach ($groups as $g) {
            $gTotalOutput = $g['ok'] + $g['hold'] + $g['ng'];
            $gNgPct = $gTotalOutput != 0 ? ($g['ng'] / $gTotalOutput) * 100 : 0;

            echo '<tr class="cust"><td colspan="14">' . xlsSafe($g['vend_code']) . ' - ' . xlsSafe($g['vend_comp']) . '</td></tr>';
            
            foreach ($g['rows'] as $r) {
                $pdDate = isset($r['PD_DATE']) && $r['PD_DATE'] instanceof DateTime ? $r['PD_DATE']->format('Y-m-d') : (isset($r['PD_DATE']) ? substr((string)$r['PD_DATE'], 0, 10) : '');
                $ngPct = ngPercentValue($r);

                echo '<tr>';
                echo '<td>' . xlsSafe(isset($r['MAC_CODE']) ? $r['MAC_CODE'] : '') . '</td>';
                echo '<td>' . xlsSafe(isset($r['MAG_STATION']) ? $r['MAG_STATION'] : '') . '</td>';
                echo '<td>' . xlsSafe(isset($r['ITEM_CODE']) ? $r['ITEM_CODE'] : '') . '</td>';
                echo '<td>' . xlsSafe(isset($r['ITEM_NAME']) ? $r['ITEM_NAME'] : '') . '</td>';
                echo '<td>' . xlsSafe($pdDate) . '</td>';
                echo '<td>' . xlsSafe(isset($r['PD_LOT']) ? $r['PD_LOT'] : '') . '</td>';
                echo '<td class="n">' . num(isset($r['PD_OK']) ? $r['PD_OK'] : 0) . '</td>';
                echo '<td class="n">' . num(isset($r['PD_HOLD']) ? $r['PD_HOLD'] : 0) . '</td>';
                echo '<td class="n">' . num(isset($r['PD_NG']) ? $r['PD_NG'] : 0) . '</td>';
                echo '<td class="n">' . num(isset($r['CAV']) ? $r['CAV'] : 0) . '</td>';
                echo '<td class="n2">' . num(isset($r['CT']) ? $r['CT'] : 0) . '</td>';
                echo '<td class="n2">' . num(isset($r['ITEM_CYTM']) ? $r['ITEM_CYTM'] : 0) . '</td>';
                echo '<td class="n2">' . num(isset($r['ITEM_CAVT']) ? $r['ITEM_CAVT'] : 0) . '</td>';
                echo '<td class="n2">' . round($ngPct, 3) . '</td>';
                echo '</tr>';
            }
        }
    }

    echo '</table></body></html>';
    exit;
}

/* =========================================================
 * 9. NORMAL HTML OUTPUT — Bootstrap 3
 * ========================================================= */ ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daily Production Vendor Report</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">

    <style>
        body { background: #f5f5f5; font-size: 11px; padding-top: 10px; }
        .panel-filter .panel-heading { padding: 6px 12px; background: #337ab7; border-color: #2e6da4; }
        .panel-filter .panel-heading h4 { margin: 0; color: #fff; font-size: 13px; font-weight: 600; }
        .panel-filter .panel-body { padding: 10px 12px; }
        .filter-group label { font-weight: 600; font-size: 10px; margin-bottom: 2px; color: #333; }
        .filter-group .form-control { font-size: 11px; height: 30px; padding: 3px 8px; }
        .btn-filter { height: 30px; padding: 0 14px; font-size: 11px; }

        .period-bar { background: #fff; border: 1px solid #ddd; border-radius: 3px; padding: 5px 12px; margin-bottom: 8px; }
        .period-bar .plabel { font-weight: 600; color: #555; font-size: 10px; }
        .period-bar .pvalue { font-weight: 700; color: #333; font-size: 11px; }

        .table-report { background: #fff; border: 1px solid #ddd; font-size: 9px; width: 100%; margin-bottom: 0; }
        .table-report > thead > tr > th { text-align: center; font-size: 9px; background: #eef2f9 !important; border: 1px solid #999 !important; padding: 5px; white-space: nowrap; vertical-align: middle; }
        .table-report > tbody > tr > td { border: 1px solid #999 !important; padding: 5px; vertical-align: middle; }
        .table-report .tr-cust td { font-weight: 700; font-size: 10px; background: #e8f0dc !important; }
        .table-report .tr-even td { background-color: #ffffff !important; }
        .table-report .tr-odd td { background-color: #f4f6f9 !important; }

        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; white-space: nowrap; }
        .left { text-align: left; }
        .empty-msg { padding: 24px !important; text-align: center; font-size: 12px !important; color: #999; }
    </style>
</head>
<body>

<div class="container-fluid" style="min-width:1200px;">

    <div class="panel panel-primary panel-filter">
        <div class="panel-heading">
            <h4 class="panel-title">
                <span class="glyphicon glyphicon-filter"></span> Filter Laporan Vendor
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
            <span class="plabel">TOTAL OK :</span>
            <span class="pvalue" style="color: #28a745;"><?php echo fmt0($grandOk); ?></span>
            &nbsp;&nbsp;&nbsp;
            <span class="plabel">TOTAL HOLD :</span>
            <span class="pvalue" style="color: #ffc107;"><?php echo fmt0($grandHold); ?></span>
            &nbsp;&nbsp;&nbsp;
            <span class="plabel">TOTAL NG :</span>
            <span class="pvalue" style="color: #dc3545;"><?php echo fmt0($grandNg); ?></span>
            &nbsp;&nbsp;&nbsp;
            <span class="plabel">TOTAL NG % :</span>
            <span class="pvalue" style="color: #dc3545;"><?php echo fmtPct($grandNgPct, 2); ?></span>
        </span>
        <div style="clear: both;"></div>
    </div>

    <div class="table-responsive">
        <table class="table table-condensed table-report">
            <thead>
                <tr>
                    <th>MC CODE</th>
                    <th>STATION</th>
                    <th>ITEM CODE</th>
                    <th>ITEM NAME</th>
                    <th>PD DATE</th>
                    <th>LOT</th>
                    <th>OK</th>
                    <th>HOLD</th>
                    <th>NG</th>
                    <th>CAV</th>
                    <th>CT</th>
                    <th>CYTM</th>
                    <th>CAVT</th>
                    <th>NG %</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($groups) === 0): ?>
                <tr><td colspan="14" class="empty-msg">Data tidak ditemukan untuk filter yang dipilih.</td></tr>
            <?php else: ?>
                <?php foreach ($groups as $g): ?>
                    <?php 
                        $gTotalOutput = $g['ok'] + $g['hold'] + $g['ng'];
                        $gNgPct = $gTotalOutput != 0 ? ($g['ng'] / $gTotalOutput) * 100 : 0;
                    ?>
                    <tr class="tr-cust">
                        <td colspan="14">
                            <span class="glyphicon glyphicon-briefcase" style="margin-right:4px;"></span>
                            <?php echo h($g['vend_code']); ?> &nbsp;&mdash;&nbsp; <?php echo h($g['vend_comp']); ?>
                        </td>
                    </tr>

                    <?php foreach ($g['rows'] as $index => $r): ?>
                        <?php
                        $pdDate = isset($r['PD_DATE']) && $r['PD_DATE'] instanceof DateTime ? $r['PD_DATE']->format('Y-m-d') : (isset($r['PD_DATE']) ? substr((string)$r['PD_DATE'], 0, 10) : '');
                        $ngPct = ngPercentValue($r);
                        $rowClass = ($index % 2 === 0) ? 'tr-even' : 'tr-odd';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td class="center"><?php echo h(isset($r['MAC_CODE']) ? $r['MAC_CODE'] : ''); ?></td>
                            <td class="center"><?php echo h(isset($r['MAG_STATION']) ? $r['MAG_STATION'] : ''); ?></td>
                            <td class="left"><?php echo h(isset($r['ITEM_CODE']) ? $r['ITEM_CODE'] : ''); ?></td>
                            <td class="left"><?php echo h(isset($r['ITEM_NAME']) ? $r['ITEM_NAME'] : ''); ?></td>
                            <td class="center"><?php echo h($pdDate); ?></td>
                            <td class="center"><?php echo h(isset($r['PD_LOT']) ? $r['PD_LOT'] : ''); ?></td>
                            <td class="num"><?php echo fmt0(isset($r['PD_OK']) ? $r['PD_OK'] : 0); ?></td>
                            <td class="num"><?php echo fmt0(isset($r['PD_HOLD']) ? $r['PD_HOLD'] : 0); ?></td>
                            <td class="num"><?php echo fmt0(isset($r['PD_NG']) ? $r['PD_NG'] : 0); ?></td>
                            <td class="num"><?php echo fmt0(isset($r['CAV']) ? $r['CAV'] : 0); ?></td>
                            <td class="num"><?php echo fmt2(isset($r['CT']) ? $r['CT'] : 0); ?></td>
                            <td class="num"><?php echo fmt2(isset($r['ITEM_CYTM']) ? $r['ITEM_CYTM'] : 0); ?></td>
                            <td class="num"><?php echo fmt2(isset($r['ITEM_CAVT']) ? $r['ITEM_CAVT'] : 0); ?></td>
                            <td class="num"><?php echo round($ngPct, 3); ?> %</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
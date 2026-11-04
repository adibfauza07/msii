<?php
/**
 * DAILY PRODUCTION REPORT
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

$code     = isset($_GET['code']) && $_GET['code'] !== '' ? trim($_GET['code']) : '%';
$custCode = isset($_GET['cust_code']) && $_GET['cust_code'] !== '' ? trim($_GET['cust_code']) : '%';

$lotGroup = isset($_GET['lot_group']) ? strtoupper(trim($_GET['lot_group'])) : '';
if (!in_array($lotGroup, array('A', 'B', 'C'))) {
    $lotGroup = '';
}

$startSql = $startDate . ' 00:00:00.000';
$endSql   = $endDate . ' 23:59:59.997';

/* =========================================================
 * 4. EXECUTE STORED PROCEDURE
 * ========================================================= */
$sql    = "{CALL dbo.sp_daily_prod(?, ?, ?, ?)}";
$params = array($startSql, $endSql, $code, $custCode);
$stmt   = sqlsrv_query($db, $sql, $params);

if ($stmt === false) {
    die('<pre style="white-space:pre-wrap;color:#a00">' . h(sqlsrvErrorText()) . '</pre>');
}

$rows = array();
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $r;
}
sqlsrv_free_stmt($stmt);

/* =========================================================
 * 5. SORTING
 * ========================================================= */
usort($rows, function ($a, $b) {
    $keys = array('CUST_CODE', 'MAC_CODE', 'ITEM_CODE', 'PD_LOT', 'WO_NUMBER');
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
 * 6. GROUP BY CUSTOMER + CALCULATE ACCUMULATIONS
 * ========================================================= */
$groups = array();
foreach ($rows as $r) {
    $pdLot = isset($r['PD_LOT']) ? trim((string)$r['PD_LOT']) : '';

    if ($lotGroup !== '') {
        $groupChar = strlen($pdLot) >= 8 ? strtoupper(substr($pdLot, 7, 1)) : '';
        if ($groupChar !== $lotGroup) {
            if (strpos(strtoupper($pdLot), $lotGroup) === false) {
                continue;
            }
        }
    }

    $cust    = isset($r['CUST_CODE']) ? trim((string)$r['CUST_CODE']) : '';
    $comp    = isset($r['CUST_COMP']) ? trim((string)$r['CUST_COMP']) : '';
    $groupKey = $cust . '|' . $comp;

    if (!isset($groups[$groupKey])) {
        $groups[$groupKey] = array(
            'cust_code' => $cust,
            'cust_comp' => $comp,
            'plan'      => 0,
            'act'       => 0,
            'cap'       => 0,
            'ok'        => 0,
            'hold'      => 0,
            'ng'        => 0,
            'wo_seen'   => array(),
            'rows'      => array()
        );
    }

    $woNumber = isset($r['WO_NUMBER']) ? trim((string)$r['WO_NUMBER']) : '';
    $woKey    = $woNumber !== '' ? $woNumber : ('__row_' . count($groups[$groupKey]['rows']));

    if (!isset($groups[$groupKey]['wo_seen'][$woKey])) {
        $groups[$groupKey]['plan'] += num(isset($r['WO_QTY']) ? $r['WO_QTY'] : 0);
        $groups[$groupKey]['wo_seen'][$woKey] = true;
    }

    $groups[$groupKey]['act']  += num(isset($r['PD_QTY']) ? $r['PD_QTY'] : 0);
    $groups[$groupKey]['cap']  += num(isset($r['WO_CAP']) ? $r['WO_CAP'] : 0);
    
    // Akumulasi Output untuk hitung NG%
    $groups[$groupKey]['ok']   += num(isset($r['PD_OK']) ? $r['PD_OK'] : 0);
    $groups[$groupKey]['hold'] += num(isset($r['PD_HO']) ? $r['PD_HO'] : 0);
    $groups[$groupKey]['ng']   += num(isset($r['NG']) ? $r['NG'] : 0);
    
    $groups[$groupKey]['rows'][] = $r;
}

/* =========================================================
 * 7. FORMULA NG
 * ========================================================= */
function ngPercentValue($r)
{
    $ok   = num(isset($r['PD_OK']) ? $r['PD_OK'] : 0);
    $hold = num(isset($r['PD_HO']) ? $r['PD_HO'] : 0);
    $ng   = num(isset($r['NG']) ? $r['NG'] : 0);
    $total = $ok + $hold + $ng;
    return $total != 0 ? ($ng / $total) * 100 : 0;
}

/* =========================================================
 * GRAND TOTAL CALCULATION
 * ========================================================= */
$grandPlan = 0;
$grandAct  = 0;
$grandCap  = 0;
$grandOk   = 0;
$grandHold = 0;
$grandNg   = 0;

foreach ($groups as $g) {
    $grandPlan += $g['plan'];
    $grandAct  += $g['act'];
    $grandCap  += $g['cap'];
    $grandOk   += $g['ok'];
    $grandHold += $g['hold'];
    $grandNg   += $g['ng'];
}
$grandProdPct = $grandPlan != 0 ? ($grandAct / $grandPlan) * 100 : 0;
$grandEffPct  = $grandCap != 0 ? ($grandAct / $grandCap) * 100 : 0;

$grandTotalOutput = $grandOk + $grandHold + $grandNg;
$grandNgPct       = $grandTotalOutput != 0 ? ($grandNg / $grandTotalOutput) * 100 : 0;

/* =========================================================
 * 8. EXPORT XLS
 * ========================================================= */
if (isset($_GET['export']) && $_GET['export'] === 'xls') {

    $filename = 'Daily_Production_' . $startDate . '_to_' . $endDate . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: no-cache');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"><style>td,th{mso-number-format:\@;vertical-align:middle;font-size:10px;font-family:Arial,sans-serif;}th{background-color:#d9e1f2;font-weight:bold;text-align:center;}.n{mso-number-format:\#\,\#\#0;text-align:right;}.n2{mso-number-format:\#\,\#\#0\.00;text-align:right;}.cust{background-color:#e8f0dc;font-weight:bold;font-size:11px;}.sum{background-color:#f2f2f2;font-weight:bold;}</style></head><body>';

    echo '<table border="1" cellspacing="0" cellpadding="2">';
    echo '<tr><td colspan="19" style="font-size:14px;font-weight:bold;text-align:center;">DAILY PRODUCTION</td></tr>';
    echo '<tr><td style="font-weight:bold;">DARI TANGGAL :</td><td>' . xlsSafe(dateDisplay($startDate)) . '</td><td style="font-weight:bold;">KE TANGGAL :</td><td>' . xlsSafe(dateDisplay($endDate)) . '</td><td colspan="15"></td></tr>';

    echo '<tr>';
    echo '<th rowspan="2">MC NO</th>';
    echo '<th>ITEM CODE</th><th>ITEM NAME</th>';
    echo '<th rowspan="2">LOT</th>';
    echo '<th rowspan="2">CAP/D</th>';
    echo '<th>CAV STD</th><th>CAV ACT</th>';
    echo '<th>CT STD</th><th>CT ACT</th>';
    echo '<th>WGT STD</th><th>WGT ACT</th>';
    echo '<th>RUN STD</th><th>RUN ACT</th>';
    echo '<th rowspan="2">TM</th>';
    echo '<th>OK</th><th>HOLD</th><th>NG</th>';
    echo '<th rowspan="2">PURG</th>';
    echo '<th rowspan="2">PROBLEM / ITEM NG</th>';
    echo '<th rowspan="2">NG %</th>';
    echo '</tr>';
    echo '<tr><th>ITEMS</th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th></tr>';

    if (count($groups) === 0) {
        echo '<tr><td colspan="19" style="text-align:center;">Data tidak ditemukan.</td></tr>';
    } else {
        foreach ($groups as $g) {
            $gProdPct = $g['plan'] != 0 ? ($g['act'] / $g['plan']) * 100 : 0;
            $gEffPct  = $g['cap'] != 0 ? ($g['act'] / $g['cap']) * 100 : 0;
            
            $gTotalOutput = $g['ok'] + $g['hold'] + $g['ng'];
            $gNgPct = $gTotalOutput != 0 ? ($g['ng'] / $gTotalOutput) * 100 : 0;

            echo '<tr class="cust"><td colspan="19">' . xlsSafe($g['cust_code']) . '  ' . xlsSafe($g['cust_comp']) . '</td></tr>';
            echo '<tr class="sum"><td colspan="2">PLAN : ' . fmt0($g['plan']) . '</td><td colspan="17">PERSENTASE PRODUKSI : ' . fmtPct($gProdPct, 2) . '</td></tr>';
            echo '<tr class="sum"><td colspan="2">ACT  : ' . fmt0($g['act']) . '</td><td colspan="17">EFFISIENSI : ' . fmtPct($gEffPct, 2) . ' &nbsp;&nbsp;|&nbsp;&nbsp; NG : ' . fmtPct($gNgPct, 2) . '</td></tr>';

            foreach ($g['rows'] as $r) {
                $mc    = trim((isset($r['MAG_STATION']) ? (string)$r['MAG_STATION'] : '') . ' ' . (isset($r['MAC_CODE']) ? (string)$r['MAC_CODE'] : ''));
                $ngPct = ngPercentValue($r);

                echo '<tr>';
                echo '<td>' . xlsSafe($mc) . '</td>';
                echo '<td>' . xlsSafe(isset($r['ITEM_CODE']) ? $r['ITEM_CODE'] : '') . '</td>';
                echo '<td>' . xlsSafe(isset($r['ITEM_NAME']) ? $r['ITEM_NAME'] : '') . '</td>';
                echo '<td>' . xlsSafe(isset($r['PD_LOT']) ? $r['PD_LOT'] : '') . '</td>';
                echo '<td class="n">' . num(isset($r['WO_CAP']) ? $r['WO_CAP'] : 0) . '</td>';
                echo '<td class="n">' . num(isset($r['CAV_STD']) ? $r['CAV_STD'] : 0) . '</td>';
                echo '<td class="n">' . num(isset($r['CAV_ACT']) ? $r['CAV_ACT'] : 0) . '</td>';
                echo '<td class="n2">' . num(isset($r['CT_STD']) ? $r['CT_STD'] : 0) . '</td>';
                echo '<td class="n2">' . num(isset($r['CT_ACT']) ? $r['CT_ACT'] : 0) . '</td>';
                echo '<td class="n2">' . num(isset($r['ITEM_WEIGHT']) ? $r['ITEM_WEIGHT'] : 0) . '</td>';
                echo '<td class="n2">' . num(isset($r['PD_WEIGHT_S']) ? $r['PD_WEIGHT_S'] : 0) . '</td>';
                echo '<td class="n2">' . num(isset($r['ITEM_RWEIGHT']) ? $r['ITEM_RWEIGHT'] : 0) . '</td>';
                echo '<td class="n2">' . num(isset($r['PD_RUN_S']) ? $r['PD_RUN_S'] : 0) . '</td>';
                echo '<td class="n2">' . num(isset($r['PD_TAKE_TM']) ? $r['PD_TAKE_TM'] : 0) . '</td>';
                echo '<td class="n">' . num(isset($r['PD_OK']) ? $r['PD_OK'] : 0) . '</td>';
                echo '<td class="n">' . num(isset($r['PD_HO']) ? $r['PD_HO'] : 0) . '</td>';
                echo '<td class="n">' . num(isset($r['NG']) ? $r['NG'] : 0) . '</td>';
                echo '<td class="n2">' . num(isset($r['PUR']) ? $r['PUR'] : 0) . '</td>';
                echo '<td>' . xlsSafe(isset($r['PD_REM']) ? $r['PD_REM'] : '') . '</td>';
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
    <title>Daily Production Report</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">

    <style>
        body {
            background: #f5f5f5;
            font-size: 11px;
            padding-top: 10px;
        }

        /* ---------- Panel & Form ---------- */
        .panel-filter .panel-heading {
            padding: 6px 12px;
            background: #337ab7;
            border-color: #2e6da4;
        }
        .panel-filter .panel-heading h4 {
            margin: 0;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
        }
        .panel-filter .panel-body {
            padding: 10px 12px;
        }
        .filter-group label {
            font-weight: 600;
            font-size: 10px;
            margin-bottom: 2px;
            color: #333;
        }
        .filter-group .form-control {
            font-size: 11px;
            height: 30px;
            padding: 3px 8px;
        }
        .btn-filter {
            height: 30px;
            padding: 0 14px;
            font-size: 11px;
        }

        /* ---------- Autocomplete ---------- */
        .ac-wrap { position: relative; }
        .ac-dropdown {
            position: absolute;
            z-index: 99999;
            background: #fff;
            border: 1px solid #ccc;
            border-top: none;
            max-height: 240px;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
            display: none;
            min-width: 320px;
        }
        .ac-item {
            padding: 5px 10px;
            font-size: 11px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            border-bottom: 1px solid #f0f0f0;
        }
        .ac-item:last-child { border-bottom: none; }
        .ac-item:hover,
        .ac-item.ac-active {
            background: #d9edf7;
        }
        .ac-item strong {
            background: #fcf8c8;
            border-radius: 2px;
            padding: 0 1px;
        }
        .ac-empty {
            padding: 8px 10px;
            font-size: 10px;
            color: #999;
            font-style: italic;
        }
        .ac-loading {
            padding: 8px 10px;
            font-size: 10px;
            color: #666;
        }

        /* ---------- Period Info ---------- */
        .period-bar {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 3px;
            padding: 5px 12px;
            margin-bottom: 8px;
        }
        .period-bar .plabel {
            font-weight: 600;
            color: #555;
            font-size: 10px;
        }
        .period-bar .pvalue {
            font-weight: 700;
            color: #333;
            font-size: 11px;
        }

        /* ---------- Report Table ---------- */
        .table-report {
            background: #fff;
            border: 1px solid #ddd;
            font-size: 8px;
            width: 100%;
            margin-bottom: 0;
        }
        .table-report > thead > tr > th {
            text-align: center;
            font-size: 8px;
            line-height: 1.15;
            background: #eef2f9 !important;
            border: 1px solid #999 !important;
            padding: 4px 5px; 
            white-space: nowrap;
            vertical-align: middle;
        }
        .table-report > tbody > td {
            border: 1px solid #999 !important;
            padding: 4px 5px;
            line-height: 1.2;
            vertical-align: middle;
        }
        .table-report .tr-cust td {
            font-weight: 700;
            font-size: 9px;
            background: #e8f0dc !important;
            padding-top: 4px;
            padding-bottom: 1px;
            border-bottom: none !important;
        }
        .table-report .tr-sum td {
            font-weight: 700;
            background: #f5f5f5 !important;
            border-top: none !important;
            border-bottom: none !important;
            padding-top: 1px;
            padding-bottom: 1px;
        }
        .table-report .tr-sum-last td {
            border-bottom: 1px solid #999 !important;
            padding-bottom: 4px;
        }

        /* ZEBRA STRIPING CSS */
        .table-report .tr-data td {
            border: 1px solid #d2d6de !important; 
        }
        .table-report .tr-even td {
            background-color: #ffffff !important;
        }
        .table-report .tr-odd td {
            background-color: #f4f6f9 !important; 
        }
        .table-report .tr-data:hover td {
            background-color: #e2eff7 !important;
        }

        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; white-space: nowrap; }
        .left { text-align: left; }
        .muted-text { color: #888; font-size: 9px; }
        .empty-msg {
            padding: 24px !important;
            text-align: center;
            font-size: 12px !important;
            color: #999;
        }

        /* Column widths */
        .c-mc      { min-width: 60px; }
        .c-item    { min-width: 180px; }
        .c-lot     { min-width: 90px; }
        .c-problem { min-width: 120px; }

        /* ---------- Print ---------- */
        @page { size: A3 landscape; margin: 8mm; }
        @media print {
            body { background: #fff; font-size: 8px; padding: 0; }
            .panel-filter { display: none !important; }
            .table-report > thead { display: table-header-group; }
            .table-report tr { page-break-inside: avoid; }
            .table-report > thead > tr > th {
                background: #eee !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .table-report .tr-cust td {
                background: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .table-report .tr-odd td {
                background-color: #f4f6f9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .table-report .tr-even td {
                background-color: #ffffff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid" style="min-width:1200px;">

    <div class="panel panel-primary panel-filter">
        <div class="panel-heading">
            <h4 class="panel-title">
                <span class="glyphicon glyphicon-filter"></span> Filter Laporan
            </h4>
        </div>
        <div class="panel-body">
            <form class="form-inline" method="get" action="" id="filterForm">
                <div class="form-group filter-group" style="margin-right:10px;">
                    <label for="start_date" class="control-label">Dari Tanggal</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo h($startDate); ?>">
                </div>

                <div class="form-group filter-group" style="margin-right:10px;">
                    <label for="end_date" class="control-label">Ke Tanggal</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo h($endDate); ?>">
                </div>

                <div class="form-group filter-group ac-wrap" style="margin-right:10px; min-width:260px;">
                    <label for="code" class="control-label">Item Code</label>
                    <input type="text" class="form-control" id="code" name="code"
                           value="<?php echo h($code === '%' ? '' : $code); ?>"
                           placeholder="Ketik untuk cari..." autocomplete="off">
                    <div class="ac-dropdown" id="ac_dropdown_item"></div>
                </div>

                <div class="form-group filter-group" style="margin-right:10px; width: 65px;">
                    <label for="lot_group" class="control-label">Group</label>
                    <select class="form-control" id="lot_group" name="lot_group" style="width:100%; padding: 3px 6px;">
                        <option value="" <?php echo $lotGroup === '' ? 'selected' : ''; ?>>All</option>
                        <option value="A" <?php echo $lotGroup === 'A' ? 'selected' : ''; ?>>A</option>
                        <option value="B" <?php echo $lotGroup === 'B' ? 'selected' : ''; ?>>B</option>
                        <option value="C" <?php echo $lotGroup === 'C' ? 'selected' : ''; ?>>C</option>
                    </select>
                </div>

                <div class="form-group filter-group ac-wrap" style="margin-right:10px; min-width:260px;">
                    <label for="cust_code" class="control-label">Customer</label>
                    <input type="text" class="form-control" id="cust_code" name="cust_code"
                           value="<?php echo h($custCode === '%' ? '' : $custCode); ?>"
                           placeholder="Ketik untuk cari..." autocomplete="off">
                    <div class="ac-dropdown" id="ac_dropdown_customer"></div>
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
                            'lot_group'  => $lotGroup,
                            'cust_code'  => $custCode,
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
            <span class="plabel">GRAND PLAN :</span>
            <span class="pvalue"><?php echo fmt0($grandPlan); ?></span>
            &nbsp;&nbsp;&nbsp;
            <span class="plabel">GRAND ACT :</span>
            <span class="pvalue"><?php echo fmt0($grandAct); ?></span>
            &nbsp;&nbsp;&nbsp;
            <span class="plabel">TOTAL PERSENTASE :</span>
            <span class="pvalue" style="color: #0056b3;"><?php echo fmtPct($grandProdPct, 2); ?></span>
            &nbsp;&nbsp;&nbsp;
            <span class="plabel">TOTAL EFF :</span>
            <span class="pvalue" style="color: #28a745;"><?php echo fmtPct($grandEffPct, 2); ?></span>
            &nbsp;&nbsp;&nbsp;
            <!-- TAMBAHAN: GRAND TOTAL NG % -->
            <span class="plabel">TOTAL NG :</span>
            <span class="pvalue" style="color: #dc3545;"><?php echo fmtPct($grandNgPct, 2); ?></span>
        </span>
        <div style="clear: both;"></div>
    </div>

    <div class="table-responsive">
        <table class="table table-condensed table-report">
            <thead>
                <tr>
                    <th class="c-mc" rowspan="2">MC NO</th>
                    <th class="c-item" colspan="2">ITEMS</th>
                    <th class="c-lot" rowspan="2">LOT</th>
                    <th class="c-cap" rowspan="2">CAP/D</th>
                    <th colspan="2">CAV</th>
                    <th colspan="2">CT</th>
                    <th colspan="2">WEIGHT</th>
                    <th colspan="2">RUNNER</th>
                    <th rowspan="2">TM</th>
                    <th colspan="3">OUTPUT</th>
                    <th rowspan="2">PURG</th>
                    <th class="c-problem" rowspan="2">PROBLEM / ITEM NG</th>
                    <th rowspan="2">NG %</th>
                </tr>
                <tr>
                    <th style="min-width:80px;">CODE</th>
                    <th style="min-width:92px;">NAME</th>
                    <th>STD</th><th>ACT</th>
                    <th>STD</th><th>ACT</th>
                    <th>STD</th><th>ACT</th>
                    <th>STD</th><th>ACT</th>
                    <th>OK</th><th>HOLD</th><th>NG</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($groups) === 0): ?>
                <tr><td colspan="19" class="empty-msg">Data tidak ditemukan untuk filter yang dipilih.</td></tr>
            <?php else: ?>
                <?php foreach ($groups as $g): ?>
                    <?php 
                        // Perhitungan Grup: Prod, Eff, dan NG
                        $gProdPct = $g['plan'] != 0 ? ($g['act'] / $g['plan']) * 100 : 0;
                        $gEffPct  = $g['cap'] != 0 ? ($g['act'] / $g['cap']) * 100 : 0;
                        
                        $gTotalOutput = $g['ok'] + $g['hold'] + $g['ng'];
                        $gNgPct = $gTotalOutput != 0 ? ($g['ng'] / $gTotalOutput) * 100 : 0;
                    ?>
                    <tr class="tr-cust">
                        <td colspan="19">
                            <span class="glyphicon glyphicon-user" style="margin-right:4px;"></span>
                            <?php echo h($g['cust_code']); ?> &nbsp;&mdash;&nbsp; <?php echo h($g['cust_comp']); ?>
                        </td>
                    </tr>
                    <tr class="tr-sum">
                        <td colspan="2">PLAN : <span class="num"><?php echo fmt0($g['plan']); ?></span></td>
                        <td colspan="17">
                            <span style="margin-left: 15px;">PERSENTASE PRODUKSI : <b style="color:#0056b3;"><?php echo fmtPct($gProdPct, 2); ?></b></span>
                        </td>
                    </tr>
                    <tr class="tr-sum tr-sum-last">
                        <td colspan="2">ACT&nbsp;&nbsp;: <span class="num"><?php echo fmt0($g['act']); ?></span></td>
                        <td colspan="17">
                            <span style="margin-left: 15px;">EFFISIENSI : <b style="color:#28a745;"><?php echo fmtPct($gEffPct, 2); ?></b></span>
                            <!-- TAMBAHAN: GRUP TOTAL NG % -->
                            <span style="margin-left: 15px;">NG : <b style="color:#dc3545;"><?php echo fmtPct($gNgPct, 2); ?></b></span>
                        </td>
                    </tr>

                    <?php foreach ($g['rows'] as $index => $r): ?>
                        <?php
                        $mc    = trim((isset($r['MAG_STATION']) ? (string)$r['MAG_STATION'] : '') . ' ' . (isset($r['MAC_CODE']) ? (string)$r['MAC_CODE'] : ''));
                        $ngPct = ngPercentValue($r);
                        
                        // ZEBRA LOGIC
                        $rowClass = ($index % 2 === 0) ? 'tr-even' : 'tr-odd';
                        ?>
                        <tr class="tr-data <?php echo $rowClass; ?>">
                            <td class="center" title="<?php echo h($mc); ?>"><?php echo h($mc); ?></td>
                            <td class="left" title="<?php echo h(isset($r['ITEM_CODE']) ? $r['ITEM_CODE'] : ''); ?>">
                                <?php echo h(isset($r['ITEM_CODE']) ? $r['ITEM_CODE'] : ''); ?>
                            </td>
                            <td class="left" title="<?php echo h(isset($r['ITEM_NAME']) ? $r['ITEM_NAME'] : ''); ?>">
                                <?php echo h(isset($r['ITEM_NAME']) ? $r['ITEM_NAME'] : ''); ?>
                            </td>
                            <td class="center"><?php echo h(isset($r['PD_LOT']) ? $r['PD_LOT'] : ''); ?></td>
                            <td class="num"><?php echo fmt0(isset($r['WO_CAP']) ? $r['WO_CAP'] : 0); ?></td>

                            <td class="num"><?php echo fmt0(isset($r['CAV_STD']) ? $r['CAV_STD'] : 0); ?></td>
                            <td class="num"><?php echo fmt0(isset($r['CAV_ACT']) ? $r['CAV_ACT'] : 0); ?></td>

                            <td class="num"><?php echo fmt2(isset($r['CT_STD']) ? $r['CT_STD'] : 0); ?></td>
                            <td class="num"><?php echo fmt2(isset($r['CT_ACT']) ? $r['CT_ACT'] : 0); ?></td>

                            <td class="num"><?php echo fmt2(isset($r['ITEM_WEIGHT']) ? $r['ITEM_WEIGHT'] : 0); ?></td>
                            <td class="num"><?php echo fmt2(isset($r['PD_WEIGHT_S']) ? $r['PD_WEIGHT_S'] : 0); ?></td>

                            <td class="num"><?php echo fmt2(isset($r['ITEM_RWEIGHT']) ? $r['ITEM_RWEIGHT'] : 0); ?></td>
                            <td class="num"><?php echo fmt2(isset($r['PD_RUN_S']) ? $r['PD_RUN_S'] : 0); ?></td>

                            <td class="num"><?php echo fmt2(isset($r['PD_TAKE_TM']) ? $r['PD_TAKE_TM'] : 0); ?></td>

                            <td class="num"><?php echo fmt0(isset($r['PD_OK']) ? $r['PD_OK'] : 0); ?></td>
                            <td class="num"><?php echo fmt0(isset($r['PD_HO']) ? $r['PD_HO'] : 0); ?></td>
                            <td class="num"><?php echo fmt0(isset($r['NG']) ? $r['NG'] : 0); ?></td>

                            <td class="num"><?php echo fmt2(isset($r['PUR']) ? $r['PUR'] : 0); ?></td>
                            <td class="left" title="<?php echo h(isset($r['PD_REM']) ? $r['PD_REM'] : ''); ?>">
                                <?php echo h(isset($r['PD_REM']) ? $r['PD_REM'] : ''); ?>
                            </td>
                            <td class="num"><?php echo fmtPct($ngPct, 3); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div><script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>

<script>
(function ($) {
    "use strict";

    /* =========================================================
     * AUTOCOMPLETE ENGINE
     * ========================================================= */

    function initAutocomplete(inputId, dropdownId, url) {
        var $input    = $("#" + inputId);
        var $dropdown = $("#" + dropdownId);
        if (!$input.length || !$dropdown.length) return;

        var items    = [];
        var selIdx   = -1;
        var xhr      = null;
        var debounce = null;
        var DEBOUNCE = 400;

        $input.on("input focus", function () {
            clearTimeout(debounce);
            var q = $(this).val().replace(/%/g, "").trim();
            
            if (q.length < 2) { 
                hide(); 
                return; 
            }
            
            debounce = setTimeout(function () { fetch(q); }, DEBOUNCE);
        });

        $input.on("keydown", function (e) {
            if ($dropdown.is(":hidden")) return;

            if (e.keyCode === 40) { // Arrow Down
                e.preventDefault();
                selIdx = Math.min(selIdx + 1, items.length - 1);
                render();
                scrollIntoView();
            } else if (e.keyCode === 38) { // Arrow Up
                e.preventDefault();
                selIdx = Math.max(selIdx - 1, 0);
                render();
                scrollIntoView();
            } else if (e.keyCode === 13) { // Enter
                e.preventDefault();
                if (selIdx >= 0 && selIdx < items.length) {
                    pick(items[selIdx]);
                }
                hide();
            } else if (e.keyCode === 27) { // Escape
                hide();
            }
        });

        $(document).on("mousedown", function (e) {
            if (!$(e.target).closest($input).length && !$(e.target).closest($dropdown).length) {
                hide();
            }
        });

        function fetch(q) {
            if (xhr && xhr.readyState < 4) xhr.abort();
            showLoading();

            xhr = $.ajax({
                url: url,
                data: { q: q },
                dataType: "json",
                cache: false,
                success: function (data) {
                    items = [];
                    if (Array.isArray(data)) {
                        for (var i = 0; i < data.length; i++) {
                            var d = data[i];
                            if (d.ITEM_CODE) {
                                items.push({ value: d.ITEM_CODE, label: d.ITEM_CODE + " - " + (d.ITEM_NAME || "") });
                            } else if (d.CUST_CODE) {
                                items.push({ value: d.CUST_CODE, label: d.CUST_CODE + " - " + (d.CUST_COMP || "") });
                            }
                        }
                    }

                    selIdx = -1;
                    if (items.length > 0) {
                        render();
                        show();
                    } else {
                        showEmpty();
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    if (textStatus === 'abort') return;
                    console.error("AJAX Error [" + url + "]:", textStatus, errorThrown);
                    $dropdown.html('<div class="ac-empty" style="color:#d9534f; font-weight:bold;">Error mengambil data. Cek Console F12.</div>');
                    show();
                }
            });
        }

        function render() {
            var q    = $input.val().replace(/%/g, "").trim().toLowerCase();
            var html = "";

            for (var i = 0; i < items.length; i++) {
                var cls   = (i === selIdx) ? " ac-active" : "";
                var label = highlight(items[i].label, q);
                html += '<div class="ac-item' + cls + '" data-i="' + i + '">' + label + '</div>';
            }
            $dropdown.html(html);

            $dropdown.find(".ac-item").on("mousedown", function (e) {
                e.preventDefault();
                var idx = parseInt($(this).attr("data-i"), 10);
                if (idx >= 0 && idx < items.length) pick(items[idx]);
                hide();
            });
        }

        function highlight(text, q) {
            if (!q) return escHtml(text);
            var lower = text.toLowerCase();
            var idx   = lower.indexOf(q);
            if (idx === -1) return escHtml(text);
            return escHtml(text.substring(0, idx))
                 + "<strong>" + escHtml(text.substring(idx, idx + q.length)) + "</strong>"
                 + escHtml(text.substring(idx + q.length));
        }

        function escHtml(s) {
            return $("<span>").text(s).html();
        }

        function pick(item) {
            $input.val(item.value).trigger("change");
        }

        function show() {
            var w = Math.max($input.outerWidth(), 340);
            $dropdown.css({ width: w, display: "block" });
        }

        function hide() {
            $dropdown.hide().empty();
            selIdx = -1;
        }

        function showLoading() {
            $dropdown.html('<div class="ac-loading"><span class="glyphicon glyphicon-refresh glyphicon-spin"></span> Mencari...</div>');
            show();
        }

        function showEmpty() {
            $dropdown.html('<div class="ac-empty">Tidak ditemukan</div>');
            show();
        }

        function scrollIntoView() {
            var $active = $dropdown.find(".ac-active");
            if ($active.length) {
                var top = $active.position().top;
                var height = $dropdown.height();
                if (top < 0 || top + $active.outerHeight() > height) {
                    $dropdown.scrollTop($dropdown.scrollTop() + top - 4);
                }
            }
        }
    }

    initAutocomplete("code",      "ac_dropdown_item",     "ac_item.php");
    initAutocomplete("cust_code", "ac_dropdown_customer", "ac_customer.php");

    $("#filterForm").on("submit", function () {
        var $code = $("#code");
        var $cust = $("#cust_code");

        if ($code.val().replace(/%/g, "").trim() === "") {
            $code.val("%");
        }
        if ($cust.val().replace(/%/g, "").trim() === "") {
            $cust.val("%");
        }
    });

    var style = document.createElement("style");
    style.textContent = "@-webkit-keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}.glyphicon-spin{animation:spin 1s linear infinite}";
    document.head.appendChild(style);

})(jQuery);
</script>
</body>
</html>
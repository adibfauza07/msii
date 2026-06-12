<?php
/*
    invoice_epro_report_excel_php54.php
    PHP 5.4 + SQL Server 2008

    Report: MONTHLY INVOICE EPRO VS APPROVAL
    Source : EXEC dbo.SP_INVOICE_EPRO @ASPER

    Fitur:
    - Tampilkan report di browser
    - Export ke Excel format .xls HTML
    - Default As Per: bulan berjalan
*/

/* =========================
   ERROR SETTING
   ========================= */
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   LOAD CONFIG DATABASE
   ========================= */
$configs = array(
    __DIR__ . '/../config/db_plant1.php',
    __DIR__ . '/config/db_plant2.php',
    __DIR__ . '/../config/database_p2.php',
    __DIR__ . '/config/database_p2.php'
);

$configLoaded = false;

foreach ($configs as $cfg) {
    if (file_exists($cfg)) {
        require_once $cfg;
        $configLoaded = true;
        break;
    }
}

if (!$configLoaded) {
    die('Config database tidak ditemukan. Cek path db_plant2.php.');
}

if (!isset($conn) || $conn === false) {
    die('Koneksi SQL Server gagal.');
}

/* =========================
   HELPER
   ========================= */
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function nval($v) {
    if ($v === null || $v === '') {
        return 0;
    }

    return (float)$v;
}

function fmtDateInput($v) {
    if ($v instanceof DateTime) {
        return $v->format('Y-m-d');
    }

    if ($v == '') {
        return date('Y-m-d');
    }

    $t = strtotime((string)$v);

    if ($t !== false) {
        return date('Y-m-d', $t);
    }

    return date('Y-m-d');
}

function fmtDateView($v) {
    if ($v instanceof DateTime) {
        return $v->format('j-M-Y');
    }

    if ($v == '') {
        return '';
    }

    $t = strtotime((string)$v);

    if ($t !== false) {
        return date('j-M-Y', $t);
    }

    return (string)$v;
}

function fmtMonthTitle($v) {
    if ($v instanceof DateTime) {
        return $v->format('M Y');
    }

    $t = strtotime((string)$v);

    if ($t !== false) {
        return date('M Y', $t);
    }

    return date('M Y');
}

function fmtNum($v, $dec) {
    if ($v === null || $v === '') {
        return '';
    }

    return number_format((float)$v, $dec, '.', ',');
}

function sqlErrorText() {
    $errs = sqlsrv_errors();

    if (!$errs) {
        return 'Unknown SQL Error';
    }

    $msg = array();

    foreach ($errs as $e) {
        $msg[] = '[' . $e['SQLSTATE'] . '] ' . $e['code'] . ' - ' . $e['message'];
    }

    return implode("\n", $msg);
}

function q($sql, $params) {
    global $conn;

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die('<pre>SQL ERROR: ' . h(sqlErrorText()) . "\n\nSQL:\n" . h($sql) . '</pre>');
    }

    return $stmt;
}

function loadReport($asPer) {
    $stmt = q("EXEC dbo.SP_INVOICE_EPRO ?", array($asPer));

    $rows = array();

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }

    return $rows;
}

function safeVal($row, $key, $default) {
    return isset($row[$key]) ? $row[$key] : $default;
}

function rowCalc($r) {
    $qty = nval(safeVal($r, 'QTY', 0));
    $priceEpro = nval(safeVal($r, 'PRICE_EPRO', 0));
    $approval = safeVal($r, 'PRICE_APPROVAL', null);

    $diff = '';
    if ($approval !== null && $approval !== '') {
        $diff = $priceEpro - nval($approval);
    }

    $amount = $qty * $priceEpro;

    return array(
        'qty' => $qty,
        'price_epro' => $priceEpro,
        'approval' => $approval,
        'diff' => $diff,
        'amount' => $amount
    );
}

function renderReportTable($rows, $asPer, $isExcel) {
    $printDate = date('d-M-Y H:i:s');

    if ($isExcel) {
        echo '<table border="0" style="font-family:Arial;font-size:10pt;">';
        echo '<tr>';
        echo '<td colspan="4" style="font-size:14pt;font-weight:bold;">P.T. IMC TEKNO INDONESIA</td>';
        echo '<td colspan="6" style="font-size:16pt;font-weight:bold;text-align:center;">MONTHLY INVOICE EPRO VS APPROVAL</td>';
        echo '<td colspan="3" style="text-align:right;">Print date: ' . h($printDate) . '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<td colspan="4">PPIC Departement</td>';
        echo '<td colspan="6" style="text-align:center;font-weight:bold;">As per: ' . h(fmtMonthTitle($asPer)) . '</td>';
        echo '<td colspan="3"></td>';
        echo '</tr>';
        echo '<tr><td colspan="13">&nbsp;</td></tr>';
    } else {
        echo '<div class="report-head">';
        echo '<div class="company">P.T. IMC TEKNO INDONESIA<br><span>PPIC Departement</span></div>';
        echo '<div class="title">MONTHLY INVOICE EPRO VS APPROVAL<br><span>As per: ' . h(fmtMonthTitle($asPer)) . '</span></div>';
        echo '<div class="print">Print date: ' . h($printDate) . '</div>';
        echo '</div>';
    }

    echo '<table class="report-table" border="1">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>INV#</th>';
    echo '<th>DATE</th>';
    echo '<th>P</th>';
    echo '<th>A</th>';
    echo '<th>R</th>';
    echo '<th>PO</th>';
    echo '<th>Qty.</th>';
    echo '<th>Price epro</th>';
    echo '<th>Price Approval</th>';
    echo '<th>Remarks</th>';
    echo '<th>Diff</th>';
    echo '<th>Amount</th>';
    echo '<th>Location</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    $lastLoc = '';
    $totalAmount = 0;

    foreach ($rows as $r) {
        $loc = safeVal($r, 'LOC', '');

        if ($loc != $lastLoc) {
            echo '<tr>';
            echo '<td colspan="13" style="font-weight:bold;background:#f5f5f5;">' . h($loc) . '</td>';
            echo '</tr>';
            $lastLoc = $loc;
        }

        $c = rowCalc($r);
        $totalAmount += $c['amount'];

        echo '<tr>';
        echo '<td>' . h(safeVal($r, 'INV_NO', '')) . '</td>';
        echo '<td>' . h(fmtDateView(safeVal($r, 'INV_DATE', ''))) . '</td>';
        echo '<td>' . h(safeVal($r, 'ITEM_NO', '')) . '</td>';
        echo '<td></td>';
        echo '<td>' . h(safeVal($r, 'ITEM_NAME', '')) . '</td>';
        echo '<td>' . h(safeVal($r, 'PO', '')) . '</td>';
        echo '<td class="num">' . h(fmtNum($c['qty'], 0)) . '</td>';
        echo '<td class="num">' . h(fmtNum($c['price_epro'], 4)) . '</td>';
        echo '<td class="num">' . h($c['approval'] === null || $c['approval'] === '' ? '' : fmtNum($c['approval'], 4)) . '</td>';
        echo '<td>' . h(safeVal($r, 'REMARK', '')) . '</td>';
        echo '<td class="num">' . h($c['diff'] === '' ? '' : fmtNum($c['diff'], 4)) . '</td>';
        echo '<td class="num">' . h(fmtNum($c['amount'], 2)) . '</td>';
        echo '<td>' . h($loc) . '</td>';
        echo '</tr>';
    }

    echo '<tr>';
    echo '<td colspan="11" class="num"><b>TOTAL</b></td>';
    echo '<td class="num"><b>' . h(fmtNum($totalAmount, 2)) . '</b></td>';
    echo '<td></td>';
    echo '</tr>';

    echo '</tbody>';
    echo '</table>';

    if ($isExcel) {
        echo '</table>';
    }
}

/* =========================
   REQUEST
   ========================= */
$defaultAsPer = date('Y-m-01');
$asPer = isset($_GET['as_per']) ? $_GET['as_per'] : $defaultAsPer;
$export = isset($_GET['export']) ? $_GET['export'] : '';

$asPer = fmtDateInput($asPer);
$rows = loadReport($asPer);

/* =========================
   EXPORT EXCEL
   ========================= */
if ($export == 'excel') {
    $fileName = 'Monthly_Invoice_EPRO_Approval_' . date('Ym', strtotime($asPer)) . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<html>';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<style>';
    echo 'table.report-table{border-collapse:collapse;font-family:Arial;font-size:9pt;}';
    echo 'table.report-table th{background:#d9eaf7;font-weight:bold;text-align:center;border:1px solid #000;}';
    echo 'table.report-table td{border:1px solid #999;}';
    echo '.num{text-align:right;}';
    echo '</style>';
    echo '</head>';
    echo '<body>';

    renderReportTable($rows, $asPer, true);

    echo '</body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Invoice EPRO VS Approval</title>

    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            margin: 0;
            background: #f4f6f8;
        }

        .topbar {
            background: #060078;
            color: #fff;
            padding: 10px 15px;
            font-size: 15px;
            font-weight: bold;
            text-align: center;
        }

        .wrap {
            margin: 15px;
            background: #fff;
            border: 1px solid #ccc;
            padding: 12px;
        }

        .filter {
            padding: 8px;
            background: #eee;
            border: 1px solid #ddd;
            margin-bottom: 10px;
        }

        input[type=date] {
            height: 24px;
            border: 1px solid #bfc7d1;
            padding: 2px 4px;
            font-size: 12px;
        }

        .btn {
            border: 0;
            padding: 6px 10px;
            cursor: pointer;
            border-radius: 3px;
            font-size: 12px;
            color: #fff;
            text-decoration: none;
            display: inline-block;
        }

        .btn-blue {
            background: #337ab7;
        }

        .btn-green {
            background: #058b57;
        }

        .report-head {
            display: table;
            width: 100%;
            margin-top: 10px;
            margin-bottom: 12px;
        }

        .company,
        .title,
        .print {
            display: table-cell;
            vertical-align: top;
        }

        .company {
            width: 30%;
            font-size: 16px;
            font-weight: bold;
        }

        .company span {
            font-size: 12px;
            font-weight: normal;
        }

        .title {
            width: 40%;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
        }

        .title span {
            font-size: 14px;
        }

        .print {
            width: 30%;
            text-align: right;
        }

        .scroll {
            max-height: 650px;
            overflow: auto;
            border: 1px solid #ccc;
        }

        table.report-table {
            border-collapse: collapse;
            width: 100%;
            font-size: 11px;
            background: #fff;
        }

        table.report-table th {
            background: #111;
            color: #fff;
            padding: 5px;
            border: 1px solid #444;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        table.report-table td {
            border: 1px solid #ddd;
            padding: 4px;
        }

        table.report-table tr:nth-child(even) td {
            background: #f9f9f9;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        .info {
            margin-left: 10px;
            color: #666;
        }
    </style>
</head>

<body>

<div class="topbar">MONTHLY INVOICE EPRO VS APPROVAL</div>

<div class="wrap">
    <form method="get" action="<?php echo h(basename(__FILE__)); ?>" class="filter">
        As Per:
        <input type="date" name="as_per" value="<?php echo h($asPer); ?>">

        <button type="submit" class="btn btn-blue">Load</button>

        <a class="btn btn-green"
           href="<?php echo h(basename(__FILE__)); ?>?as_per=<?php echo urlencode($asPer); ?>&export=excel">
            Export Excel
        </a>

        <span class="info">Total data: <?php echo count($rows); ?></span>
    </form>

    <div class="scroll">
        <?php renderReportTable($rows, $asPer, false); ?>
    </div>
</div>

</body>
</html>

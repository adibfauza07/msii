<?php
/*
 * purchase_finance_gabungan.php
 * PHP 5.4 + SQL Server sqlsrv + Bootstrap 3 + jQuery UI Autocomplete
 *
 * Report Purchase gabungan Plant 1 / Plant 2 / All.
 * Stored procedure:
 *   dbo.RPT_PURCHASE_YEAR2(@CODE, @SUP_CODE, @RCV_NOMOR)
 *
 * Autocomplete sudah built-in dalam file ini:
 *   purchase_finance_gabungan.php?ajax=item_code&plant=all&term=100
 *   purchase_finance_gabungan.php?ajax=receive_no&plant=p1&term=260
 *   purchase_finance_gabungan.php?ajax=sup_code&plant=all&term=whs
 */

set_time_limit(180);

if (session_id() === '') {
    session_start();
}

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

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function json_output($data)
{
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

function sqlsrv_error_text()
{
    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    if (!$errors) {
        return 'Terjadi kesalahan database.';
    }

    $messages = array();
    foreach ($errors as $error) {
        $messages[] = '[' . $error['SQLSTATE'] . '] ' . $error['message'];
    }

    return implode(' | ', $messages);
}

function normalize_plant($plant)
{
    $plant = strtolower(trim((string) $plant));
    if (!in_array($plant, array('p1', 'p2', 'all'), true)) {
        return 'all';
    }
    return $plant;
}

function selected_servers($plant)
{
    $plant = normalize_plant($plant);
    return ($plant === 'all') ? array('p1', 'p2') : array($plant);
}

function server_label_text($plant)
{
    if ($plant === 'p1') {
        return 'Plant 1';
    }
    if ($plant === 'p2') {
        return 'Plant 2';
    }
    return 'Gabungan P1 & P2';
}

function connect_server($serverKey, &$connectError)
{
    global $servers_config, $dbName, $uid, $pwd;

    $connectError = '';
    if (!isset($servers_config[$serverKey])) {
        $connectError = strtoupper($serverKey) . ': konfigurasi server tidak ditemukan.';
        return false;
    }

    $connOptions = array(
        'Database' => $dbName,
        'Uid' => $uid,
        'PWD' => $pwd,
        'CharacterSet' => 'UTF-8',
        'LoginTimeout' => 5
    );

    $conn = @sqlsrv_connect($servers_config[$serverKey]['ip'], $connOptions);
    if (!$conn) {
        $connectError = $servers_config[$serverKey]['label'] . ': koneksi gagal. ' . sqlsrv_error_text();
        return false;
    }

    return $conn;
}

function date_display($value)
{
    if ($value === null || $value === '') {
        return '';
    }

    if ($value instanceof DateTime) {
        return $value->format('d-m-Y');
    }

    $timestamp = strtotime((string) $value);
    return $timestamp ? date('d-m-Y', $timestamp) : (string) $value;
}

function number_display($value, $decimal)
{
    if ($value === null || $value === '') {
        return '';
    }

    return number_format((float) $value, $decimal, ',', '.');
}

function clean_lookup_value($value)
{
    $value = trim((string) $value);

    // Jika user paste label autocomplete seperti "100184-0 - Nama Item",
    // ambil kodenya saja.
    $pos = strpos($value, ' - ');
    if ($pos !== false) {
        $value = trim(substr($value, 0, $pos));
    }

    return $value;
}

function right_like_param($value, $maxLength)
{
    $value = clean_lookup_value($value);

    if ($value === '') {
        return '%';
    }

    // Jika user isi wildcard sendiri, pakai apa adanya sepanjang tidak melebihi parameter SP.
    if (strpos($value, '%') !== false || strpos($value, '_') !== false) {
        return substr($value, 0, $maxLength);
    }

    // Penting: @CODE di SP varchar(8).
    // Jangan kirim %100184-0% karena akan kepotong menjadi %100184-.
    if (strlen($value) >= $maxLength) {
        return substr($value, 0, $maxLength);
    }

    return $value . '%';
}

function contains_like_param($value, $maxLength)
{
    $value = clean_lookup_value($value);

    if ($value === '') {
        return '%';
    }

    if (strpos($value, '%') !== false || strpos($value, '_') !== false) {
        return substr($value, 0, $maxLength);
    }

    $param = '%' . $value . '%';
    if (strlen($param) <= $maxLength) {
        return $param;
    }

    $param = $value . '%';
    if (strlen($param) <= $maxLength) {
        return $param;
    }

    return substr($value, 0, $maxLength);
}

function has_main_filter($itemCode, $receiveNo)
{
    return (clean_lookup_value($itemCode) !== '' || clean_lookup_value($receiveNo) !== '');
}

function sort_date_value($value)
{
    if ($value === null || $value === '') {
        return 0;
    }

    if ($value instanceof DateTime) {
        return $value->getTimestamp();
    }

    $timestamp = strtotime((string) $value);
    return $timestamp ? $timestamp : 0;
}

function add_unique_option(&$result, &$seen, $value, $label)
{
    $value = trim((string) $value);
    $label = trim((string) $label);

    if ($value === '') {
        return;
    }

    $key = strtoupper($value);
    if (isset($seen[$key])) {
        return;
    }

    $seen[$key] = true;
    $result[] = array(
        'label' => ($label !== '' ? $label : $value),
        'value' => $value
    );
}

/*
|--------------------------------------------------------------------------
| AJAX autocomplete item / receive / supplier
|--------------------------------------------------------------------------
*/
if (isset($_GET['ajax'])) {
    $ajax = trim((string) $_GET['ajax']);
    $plantAjax = isset($_GET['plant']) ? normalize_plant($_GET['plant']) : 'all';
    $term = isset($_GET['term']) ? trim((string) $_GET['term']) : '';

    if ($term === '') {
        json_output(array());
    }

    $like = '%' . $term . '%';
    $startLike = $term . '%';
    $serversToTry = selected_servers($plantAjax);
    $result = array();
    $seen = array();

    foreach ($serversToTry as $serverKey) {
        $connectError = '';
        $connAjax = connect_server($serverKey, $connectError);
        if (!$connAjax) {
            continue;
        }

        if ($ajax === 'item_code') {
            $sql = "
                SELECT TOP 20
                    ITEM_CODE,
                    ITEM_NAME
                FROM dbo.ITEMS
                WHERE ITEM_CODE LIKE ?
                   OR ITEM_NAME LIKE ?
                ORDER BY
                    CASE WHEN ITEM_CODE LIKE ? THEN 0 ELSE 1 END,
                    ITEM_CODE
            ";
            $params = array($like, $like, $startLike);
            $stmt = @sqlsrv_query($connAjax, $sql, $params);

            if ($stmt !== false) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $itemCode = isset($row['ITEM_CODE']) ? trim((string) $row['ITEM_CODE']) : '';
                    $itemName = isset($row['ITEM_NAME']) ? trim((string) $row['ITEM_NAME']) : '';
                    $label = $itemCode . ($itemName !== '' ? ' - ' . $itemName : '');
                    if ($plantAjax === 'all' && isset($GLOBALS['servers_config'][$serverKey]['short'])) {
                        $label = '[' . $GLOBALS['servers_config'][$serverKey]['short'] . '] ' . $label;
                    }
                    add_unique_option($result, $seen, $itemCode, $label);
                    if (count($result) >= 30) {
                        break;
                    }
                }
                sqlsrv_free_stmt($stmt);
            }
        } elseif ($ajax === 'receive_no') {
            $sql = "
                SELECT TOP 20
                    RCV_NO,
                    RCV_DATE
                FROM dbo.RECEIVE
                WHERE RCV_NO IS NOT NULL
                  AND LTRIM(RTRIM(RCV_NO)) <> ''
                  AND RCV_NO LIKE ?
                ORDER BY
                    CASE WHEN RCV_NO LIKE ? THEN 0 ELSE 1 END,
                    RCV_DATE DESC,
                    RCV_NO DESC
            ";
            $params = array($like, $startLike);
            $stmt = @sqlsrv_query($connAjax, $sql, $params);

            if ($stmt !== false) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $receiveNo = isset($row['RCV_NO']) ? trim((string) $row['RCV_NO']) : '';
                    $label = $receiveNo;
                    if ($plantAjax === 'all' && isset($GLOBALS['servers_config'][$serverKey]['short'])) {
                        $label = '[' . $GLOBALS['servers_config'][$serverKey]['short'] . '] ' . $label;
                    }
                    add_unique_option($result, $seen, $receiveNo, $label);
                    if (count($result) >= 30) {
                        break;
                    }
                }
                sqlsrv_free_stmt($stmt);
            }
        } elseif ($ajax === 'sup_code') {
            $sql = "
                SELECT TOP 20
                    SUP_CODE,
                    SUP_COMP
                FROM dbo.SUPPLIER
                WHERE SUP_CODE LIKE ?
                   OR SUP_COMP LIKE ?
                ORDER BY
                    CASE WHEN SUP_CODE LIKE ? THEN 0 ELSE 1 END,
                    SUP_CODE
            ";
            $params = array($like, $like, $startLike);
            $stmt = @sqlsrv_query($connAjax, $sql, $params);

            if ($stmt !== false) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $supplierCode = isset($row['SUP_CODE']) ? trim((string) $row['SUP_CODE']) : '';
                    $supplierName = isset($row['SUP_COMP']) ? trim((string) $row['SUP_COMP']) : '';
                    $label = $supplierCode . ($supplierName !== '' ? ' - ' . $supplierName : '');
                    if ($plantAjax === 'all' && isset($GLOBALS['servers_config'][$serverKey]['short'])) {
                        $label = '[' . $GLOBALS['servers_config'][$serverKey]['short'] . '] ' . $label;
                    }
                    add_unique_option($result, $seen, $supplierCode, $label);
                    if (count($result) >= 30) {
                        break;
                    }
                }
                sqlsrv_free_stmt($stmt);
            }
        }

        sqlsrv_close($connAjax);

        if (count($result) >= 30) {
            break;
        }
    }

    json_output($result);
}

/*
|--------------------------------------------------------------------------
| Nilai awal form
|--------------------------------------------------------------------------
*/
$plant = isset($_GET['plant']) ? normalize_plant($_GET['plant']) : 'all';
$itemCode = isset($_GET['item_code']) ? trim((string) $_GET['item_code']) : '';
$supCode = isset($_GET['sup_code']) ? trim((string) $_GET['sup_code']) : '';
$receiveNo = isset($_GET['receive_no']) ? trim((string) $_GET['receive_no']) : '';

$submitted = isset($_GET['show_report']);
$hasMainFilter = has_main_filter($itemCode, $receiveNo);
$errorMessages = array();
$warningMessages = array();
$reportRows = array();
$pageSelf = basename(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : 'purchase_finance_gabungan.php');

/*
|--------------------------------------------------------------------------
| Jalankan report
|--------------------------------------------------------------------------
*/
if ($submitted && !$hasMainFilter) {
    $errorMessages[] = 'Isi Item Code atau Receive No terlebih dahulu, supaya report tidak menarik semua data.';
}

if ($submitted && $hasMainFilter) {
    $itemCodeParam = right_like_param($itemCode, 8);
    $supCodeParam = contains_like_param($supCode, 8);
    $receiveNoParam = contains_like_param($receiveNo, 20);

    $reportSql = "
        EXEC dbo.RPT_PURCHASE_YEAR2
            @CODE = ?,
            @SUP_CODE = ?,
            @RCV_NOMOR = ?
    ";

    $reportParams = array(
        $itemCodeParam,
        $supCodeParam,
        $receiveNoParam
    );

    $serversForReport = selected_servers($plant);
    foreach ($serversForReport as $serverKey) {
        $connectError = '';
        $connReport = connect_server($serverKey, $connectError);
        if (!$connReport) {
            $errorMessages[] = $connectError;
            continue;
        }

        $reportStmt = @sqlsrv_query($connReport, $reportSql, $reportParams);
        if ($reportStmt === false) {
            $errorMessages[] = $servers_config[$serverKey]['label'] . ': stored procedure gagal. ' . sqlsrv_error_text();
            sqlsrv_close($connReport);
            continue;
        }

        while ($reportRow = sqlsrv_fetch_array($reportStmt, SQLSRV_FETCH_ASSOC)) {
            $reportRow['PLANT_KEY'] = $serverKey;
            $reportRow['PLANT_SHORT'] = isset($servers_config[$serverKey]['short']) ? $servers_config[$serverKey]['short'] : strtoupper($serverKey);
            $reportRow['PLANT_LABEL'] = isset($servers_config[$serverKey]['label']) ? $servers_config[$serverKey]['label'] : strtoupper($serverKey);
            $reportRows[] = $reportRow;
        }

        sqlsrv_free_stmt($reportStmt);
        sqlsrv_close($connReport);
    }

    // Sort terbaru di atas berdasarkan Receive Date.
    // Jika tanggal sama, Receive No terbesar di atas, lalu Plant, lalu PO Date terbaru.
    usort($reportRows, function ($a, $b) {
        $dateA = isset($a['RCV_DATE']) ? sort_date_value($a['RCV_DATE']) : 0;
        $dateB = isset($b['RCV_DATE']) ? sort_date_value($b['RCV_DATE']) : 0;

        if ($dateA != $dateB) {
            return ($dateA < $dateB) ? 1 : -1;
        }

        $receiveA = isset($a['RCV_NOMOR']) ? (string) $a['RCV_NOMOR'] : '';
        $receiveB = isset($b['RCV_NOMOR']) ? (string) $b['RCV_NOMOR'] : '';
        $receiveCompare = strcasecmp($receiveB, $receiveA);
        if ($receiveCompare != 0) {
            return $receiveCompare;
        }

        $plantA = isset($a['PLANT_SHORT']) ? (string) $a['PLANT_SHORT'] : '';
        $plantB = isset($b['PLANT_SHORT']) ? (string) $b['PLANT_SHORT'] : '';
        $plantCompare = strcmp($plantA, $plantB);
        if ($plantCompare != 0) {
            return $plantCompare;
        }

        $poDateA = isset($a['PO_DATE']) ? sort_date_value($a['PO_DATE']) : 0;
        $poDateB = isset($b['PO_DATE']) ? sort_date_value($b['PO_DATE']) : 0;
        if ($poDateA == $poDateB) {
            return 0;
        }

        return ($poDateA < $poDateB) ? 1 : -1;
    });
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Purchase Report Gabungan</title>

    <link rel="stylesheet"
          href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">

    <style type="text/css">
        body {
            padding-top: 20px;
            padding-bottom: 30px;
            background: #f5f5f5;
        }

        .report-container {
            width: 98%;
            margin: 0 auto;
        }

        .panel-heading h3 {
            margin: 0;
        }

        .ui-autocomplete {
            z-index: 9999;
            max-height: 250px;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .table-report {
            background: #fff;
            font-size: 12px;
            white-space: nowrap;
        }

        .table-report th {
            vertical-align: middle !important;
            text-align: center;
        }

        .text-number {
            text-align: right;
        }

        .filter-summary {
            margin-bottom: 10px;
            font-size: 12px;
        }

        .filter-note {
            margin-top: 5px;
            color: #777;
            font-size: 11px;
        }

        .empty-result {
            padding: 35px;
            text-align: center;
            color: #777;
            background: #fff;
        }

        .plant-badge {
            display: inline-block;
            min-width: 28px;
            padding: 2px 5px;
            border-radius: 3px;
            background: #eef5ff;
            border: 1px solid #aac4e8;
            color: #24527a;
            font-weight: bold;
            text-align: center;
        }

        @media print {
            body {
                padding: 0;
                background: #fff;
            }

            .no-print {
                display: none !important;
            }

            .report-container {
                width: 100%;
            }

            .panel {
                border: 0;
                box-shadow: none;
            }

            .panel-heading {
                border: 0;
            }

            .table-responsive {
                overflow: visible;
            }

            .table-report {
                font-size: 8px;
            }
        }
    </style>
</head>
<body>
<div class="report-container">

    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title">
                <span class="glyphicon glyphicon-shopping-cart"></span>
                Purchase Report Gabungan
            </h3>
        </div>

        <div class="panel-body no-print">
            <?php if (count($errorMessages) > 0) { ?>
                <div class="alert alert-danger">
                    <?php foreach ($errorMessages as $msg) { ?>
                        <div><?php echo h($msg); ?></div>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if (count($warningMessages) > 0) { ?>
                <div class="alert alert-warning">
                    <?php foreach ($warningMessages as $msg) { ?>
                        <div><?php echo h($msg); ?></div>
                    <?php } ?>
                </div>
            <?php } ?>

            <form method="get" action="<?php echo h($pageSelf); ?>" autocomplete="off" id="reportForm">
                <div class="row">
                    <div class="col-sm-2">
                        <div class="form-group">
                            <label for="plant">Server / Plant</label>
                            <select class="form-control" id="plant" name="plant">
                                <option value="all" <?php echo ($plant === 'all') ? 'selected' : ''; ?>>Gabungan P1 &amp; P2</option>
                                <option value="p1" <?php echo ($plant === 'p1') ? 'selected' : ''; ?>>Plant 1</option>
                                <option value="p2" <?php echo ($plant === 'p2') ? 'selected' : ''; ?>>Plant 2</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-sm-3">
                        <div class="form-group">
                            <label for="item_code">Item Code</label>
                            <input type="text"
                                   class="form-control"
                                   id="item_code"
                                   name="item_code"
                                   value="<?php echo h($itemCode); ?>"
                                   placeholder="Semua item">
                        </div>
                    </div>

                    <div class="col-sm-4">
                        <div class="form-group">
                            <label for="sup_code">Supplier</label>
                            <input type="text"
                                   class="form-control"
                                   id="sup_code"
                                   name="sup_code"
                                   value="<?php echo h($supCode); ?>"
                                   placeholder="Semua supplier">
                        </div>
                    </div>

                    <div class="col-sm-3">
                        <div class="form-group">
                            <label for="receive_no">Receive No</label>
                            <input type="text"
                                   class="form-control"
                                   id="receive_no"
                                   name="receive_no"
                                   value="<?php echo h($receiveNo); ?>"
                                   placeholder="Semua receive">
                        </div>
                    </div>
                </div>

                <div class="filter-note">
                    Isi <strong>Item Code</strong> atau <strong>Receive No</strong> terlebih dahulu, baru klik Tampilkan.
                    Ini mencegah report menarik semua data dari server gabungan.
                </div>

                <button type="submit"
                        class="btn btn-primary"
                        id="btnTampilkan"
                        name="show_report"
                        value="1"
                        <?php echo $hasMainFilter ? '' : 'disabled="disabled"'; ?>>
                    <span class="glyphicon glyphicon-search"></span>
                    Tampilkan
                </button>

                <a href="<?php echo h($pageSelf); ?>" class="btn btn-default">
                    <span class="glyphicon glyphicon-refresh"></span>
                    Reset
                </a>

                <?php if ($submitted && $hasMainFilter) { ?>
                    <button type="button"
                            class="btn btn-success"
                            onclick="window.print();">
                        <span class="glyphicon glyphicon-print"></span>
                        Cetak
                    </button>
                <?php } ?>
            </form>
        </div>

        <?php if ($submitted && $hasMainFilter) { ?>
            <div class="panel-body">
                <div class="filter-summary">
                    <strong>Server:</strong>
                    <?php echo h(server_label_text($plant)); ?>

                    &nbsp; | &nbsp;
                    <strong>Item:</strong>
                    <?php echo $itemCode === '' ? 'Semua' : h($itemCode); ?>

                    &nbsp; | &nbsp;
                    <strong>Supplier:</strong>
                    <?php echo $supCode === '' ? 'Semua' : h($supCode); ?>

                    &nbsp; | &nbsp;
                    <strong>Receive:</strong>
                    <?php echo $receiveNo === '' ? 'Semua' : h($receiveNo); ?>

                    &nbsp; | &nbsp;
                    <strong>Jumlah data:</strong>
                    <?php echo count($reportRows); ?>
                </div>

                <?php if (count($reportRows) > 0) { ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-condensed table-report">
                            <thead>
                            <tr>
                                <?php if ($plant === 'all') { ?>
                                    <th>Plant</th>
                                <?php } ?>
                                <th>No.</th>
                                <th>PO Date</th>
                                <th>PO No.</th>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th>Supplier</th>
                                <th>Qty PO</th>
                                <th>Qty Receive</th>
                                <th>Unit</th>
                                <th>PO Price</th>
                                <th>Receive Price</th>
                                <th>Currency</th>
                                <th>Due Date</th>
                                <th>Receive No.</th>
                                <th>Receive Date</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $no = 1;
                            $totalQtyPo = 0;
                            $totalQtyReceive = 0;

                            foreach ($reportRows as $row) {
                                $qtyPo = isset($row['QTY']) ? (float) $row['QTY'] : 0;
                                $qtyReceive = isset($row['RCVD_QTY']) ? (float) $row['RCVD_QTY'] : 0;

                                $totalQtyPo += $qtyPo;
                                $totalQtyReceive += $qtyReceive;
                                ?>
                                <tr>
                                    <?php if ($plant === 'all') { ?>
                                        <td class="text-center">
                                            <span class="plant-badge"><?php echo h(isset($row['PLANT_SHORT']) ? $row['PLANT_SHORT'] : ''); ?></span>
                                        </td>
                                    <?php } ?>
                                    <td class="text-center"><?php echo $no; ?></td>
                                    <td><?php echo h(date_display(isset($row['PO_DATE']) ? $row['PO_DATE'] : '')); ?></td>
                                    <td><?php echo h(isset($row['PO_NUM']) ? $row['PO_NUM'] : ''); ?></td>
                                    <td><?php echo h(isset($row['ITEM_CODE']) ? $row['ITEM_CODE'] : ''); ?></td>
                                    <td><?php echo h(isset($row['ITEM_NAME']) ? $row['ITEM_NAME'] : ''); ?></td>
                                    <td>
                                        <?php
                                        echo h(
                                            trim(isset($row['SUP_CODE']) ? $row['SUP_CODE'] : '') .
                                            ' - ' .
                                            trim(isset($row['SUP_COMP']) ? $row['SUP_COMP'] : '')
                                        );
                                        ?>
                                    </td>
                                    <td class="text-number"><?php echo h(number_display($qtyPo, 2)); ?></td>
                                    <td class="text-number"><?php echo h(number_display($qtyReceive, 2)); ?></td>
                                    <td><?php echo h(isset($row['POD_UNIT']) ? $row['POD_UNIT'] : ''); ?></td>
                                    <td class="text-number">
                                        <?php echo h(number_display(isset($row['POD_PRICE']) ? $row['POD_PRICE'] : null, 2)); ?>
                                    </td>
                                    <td class="text-number">
                                        <?php echo h(number_display(isset($row['RCV_PRICE']) ? $row['RCV_PRICE'] : null, 2)); ?>
                                    </td>
                                    <td class="text-center"><?php echo h(isset($row['PO_CUR']) ? $row['PO_CUR'] : ''); ?></td>
                                    <td><?php echo h(date_display(isset($row['POD_DUE']) ? $row['POD_DUE'] : '')); ?></td>
                                    <td><?php echo h(isset($row['RCV_NOMOR']) ? $row['RCV_NOMOR'] : ''); ?></td>
                                    <td><?php echo h(date_display(isset($row['RCV_DATE']) ? $row['RCV_DATE'] : '')); ?></td>
                                </tr>
                                <?php
                                $no++;
                            }
                            ?>
                            </tbody>
                            <tfoot>
                            <tr>
                                <th colspan="<?php echo ($plant === 'all') ? 7 : 6; ?>" class="text-right">TOTAL</th>
                                <th class="text-number"><?php echo h(number_display($totalQtyPo, 2)); ?></th>
                                <th class="text-number"><?php echo h(number_display($totalQtyReceive, 2)); ?></th>
                                <th colspan="7"></th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php } else { ?>
                    <div class="empty-result">
                        <span class="glyphicon glyphicon-info-sign"></span>
                        Data tidak ditemukan untuk filter yang dipilih.
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>

<script type="text/javascript">
    $(function () {
        function hasMainFilterClient() {
            return $.trim($("#item_code").val()) !== "" || $.trim($("#receive_no").val()) !== "";
        }

        function updateSubmitButton() {
            $("#btnTampilkan").prop("disabled", !hasMainFilterClient());
        }

        function bindAutocomplete(selector, ajaxName, minLengthValue) {
            var pendingRequest = null;

            $(selector).autocomplete({
                minLength: minLengthValue,
                delay: 800,
                autoFocus: false,
                source: function (request, response) {
                    if (pendingRequest && pendingRequest.readyState !== 4) {
                        pendingRequest.abort();
                    }

                    pendingRequest = $.ajax({
                        url: "<?php echo h($pageSelf); ?>",
                        dataType: "json",
                        cache: false,
                        data: {
                            ajax: ajaxName,
                            plant: $("#plant").val(),
                            term: request.term
                        },
                        success: function (data) {
                            response(data);
                        },
                        error: function () {
                            response([]);
                        }
                    });
                },
                focus: function (event, ui) {
                    // Jangan otomatis menimpa text saat list autocomplete baru muncul.
                    event.preventDefault();
                    return false;
                },
                select: function (event, ui) {
                    event.preventDefault();
                    $(this).val(ui.item.value);
                    updateSubmitButton();
                    return false;
                }
            });
        }

        bindAutocomplete("#item_code", "item_code", 2);
        bindAutocomplete("#receive_no", "receive_no", 3);
        bindAutocomplete("#sup_code", "sup_code", 2);

        $("#item_code, #receive_no").on("keyup change paste input", function () {
            window.setTimeout(updateSubmitButton, 0);
        });

        updateSubmitButton();

        // Refresh URL ketika drop down plant diganti dihilangkan
        // karena supplier sekarang sudah via autocomplete yang me-listen ke $("#plant").val() secara dinamis.
        // $("#plant").on("change", function () {
        //     ...
        // });
    });
</script>
</body>
</html>
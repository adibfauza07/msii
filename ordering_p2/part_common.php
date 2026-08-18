<?php
ob_start();

/*
 * Report Epson Group by Forecast
 * Customer values are grouped by PART_ID + CUST_ID.
 * Compatible with PHP 5.4 + Microsoft SQLSRV driver + Bootstrap 3.
 *
 * Database config is expected to provide one of these connection variables:
 * $conn, $connection, $con, $koneksi, or $db.
 */

require_once __DIR__ . "/../config/database_ordering.php";

date_default_timezone_set('Asia/Jakarta');

/* Resolve the SQLSRV connection variable from the config file. */
$sqlsrvConnection = null;

if (isset($conn)) {
    $sqlsrvConnection = $conn;
} elseif (isset($connection)) {
    $sqlsrvConnection = $connection;
} elseif (isset($con)) {
    $sqlsrvConnection = $con;
} elseif (isset($koneksi)) {
    $sqlsrvConnection = $koneksi;
} elseif (isset($db)) {
    $sqlsrvConnection = $db;
}

function epson_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function epson_number($value)
{
    if ($value === null || $value === '') {
        return 0;
    }

    return (float) $value;
}

function epson_format_number($value)
{
    return number_format((float) $value, 0, '.', ',');
}

function epson_valid_date($value)
{
    $date = DateTime::createFromFormat('Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value;
}

function epson_sqlsrv_error_text()
{
    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);

    if (!is_array($errors)) {
        return 'Unknown SQL Server error.';
    }

    $messages = array();

    foreach ($errors as $error) {
        $messages[] = '[' . $error['code'] . '] ' . $error['message'];
    }

    return implode(' | ', $messages);
}


function epson_export_excel($items, $fromDate, $toDate)
{
    $fileName = 'epson_group_detail_' .
        date('Ymd', strtotime($fromDate)) . '_' .
        date('Ymd', strtotime($toDate)) . '.xls';

    $rows = array();

    foreach ($items as $item) {
        $totalForecast = 0;
        $totalCustomerDelivery = 0;

        foreach ($item['customers'] as $customer) {
            $totalForecast += $customer['forecast'];
            $totalCustomerDelivery += $customer['delivery'];
        }

        $itemDelivery = $item['item_delivery'];

        if ($itemDelivery == 0 && $totalCustomerDelivery != 0) {
            $itemDelivery = $totalCustomerDelivery;
        }

        $itemStockActual = $item['stock_actual'];

        if ($itemStockActual == 0 &&
            ($item['stock'] != 0 || $item['prod'] != 0 || $itemDelivery != 0)) {
            $itemStockActual =
                $item['stock'] + $item['prod'] - $itemDelivery;
        }

        foreach ($item['customers'] as $customer) {
            $quotaPercent = 0;
            $quotaQty = 0;
            $customerPlan =
                $customer['schedule'] - $customer['delivery'];

            if ($totalForecast != 0) {
                $quotaPercent =
                    $customer['forecast'] / $totalForecast;

                $quotaQty =
                    $itemStockActual * $quotaPercent;
            }

            $rows[] = array(
                'item_code'       => $item['part_code'],
                'customer'        => $customer['customer'],
                'item_name'       => $item['part_name'],
                'item_no'         => $item['part_no'],
                'stock'           => $item['stock'],
                'prod'            => $item['prod'],
                'delivery_actual' => $customer['delivery'],
                'stock_actual'    => $itemStockActual,
                'forecast'        => $customer['forecast'],
                'schedule'        => $customer['schedule'],
                'plan'            => $customerPlan,
                'quota_percent'   => $quotaPercent,
                'quota_qty'       => $quotaQty
            );
        }
    }

    usort($rows, function ($a, $b) {
        $itemCompare = strcmp($a['item_code'], $b['item_code']);

        if ($itemCompare !== 0) {
            return $itemCompare;
        }

        return strcmp($a['customer'], $b['customer']);
    });

    if (ob_get_length()) {
        ob_clean();
    }

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    echo "\xEF\xBB\xBF";
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
        }

        table {
            border-collapse: collapse;
        }

        td,
        th {
            border: 1px solid #000000;
            padding: 4px 6px;
        }

        .title {
            border: 0;
            font-size: 15pt;
            font-weight: bold;
        }

        .subtitle {
            border: 0;
            font-size: 13pt;
            font-weight: bold;
        }

        .period-label,
        .period-value,
        .blank {
            border: 0;
        }

        .header {
            background: #d9eaf7;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
        }

        .text {
            mso-number-format: "\@";
        }

        .integer {
            mso-number-format: "#,##0;[Red]-#,##0";
            text-align: right;
        }

        .percent {
            mso-number-format: "0.00%";
            text-align: right;
        }
    </style>
</head>
<body>
<table>
    <tr>
        <td colspan="13" class="title">PT.IMC TEKNO INDONESIA</td>
    </tr>
    <tr>
        <td colspan="13" class="subtitle">EPSON GROUP BY FORECAST</td>
    </tr>
    <tr>
        <td class="period-label"><strong>FROM DATE :</strong></td>
        <td colspan="2" class="period-value text"><?php echo epson_escape(date('d-M-Y', strtotime($fromDate))); ?></td>
        <td class="period-label"><strong>TO DATE :</strong></td>
        <td colspan="9" class="period-value text"><?php echo epson_escape(date('d-M-Y', strtotime($toDate))); ?></td>
    </tr>
    <tr>
        <td colspan="13" class="blank">&nbsp;</td>
    </tr>
    <tr>
        <th class="header">ITEM CODE</th>
        <th class="header">CUSTOMER</th>
        <th class="header">ITEM NAME</th>
        <th class="header">ITEM NO</th>
        <th class="header">STOCK</th>
        <th class="header">PROD</th>
        <th class="header">DELIVERY ACTUAL</th>
        <th class="header">STOCK ACTUAL</th>
        <th class="header">FORECAST</th>
        <th class="header">SCHEDULE</th>
        <th class="header">PLAN SCH-ACT</th>
        <th class="header">QUOTA %</th>
        <th class="header">QUOTA QTY</th>
    </tr>

    <?php if (count($rows) === 0): ?>
        <tr>
            <td colspan="13">Tidak ada data pada periode yang dipilih.</td>
        </tr>
    <?php else: ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td class="text"><?php echo epson_escape($row['item_code']); ?></td>
                <td class="text"><?php echo epson_escape($row['customer']); ?></td>
                <td class="text"><?php echo epson_escape($row['item_name']); ?></td>
                <td class="text"><?php echo epson_escape($row['item_no']); ?></td>
                <td class="integer"><?php echo (float) $row['stock']; ?></td>
                <td class="integer"><?php echo (float) $row['prod']; ?></td>
                <td class="integer"><?php echo (float) $row['delivery_actual']; ?></td>
                <td class="integer"><?php echo (float) $row['stock_actual']; ?></td>
                <td class="integer"><?php echo (float) $row['forecast']; ?></td>
                <td class="integer"><?php echo (float) $row['schedule']; ?></td>
                <td class="integer"><?php echo (float) $row['plan']; ?></td>
                <td class="percent"><?php echo (float) $row['quota_percent']; ?></td>
                <td class="integer"><?php echo (float) $row['quota_qty']; ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>
</body>
</html>
    <?php

    exit;
}

$fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : date('Y-m-01');
$toDate   = isset($_GET['to_date']) ? trim($_GET['to_date']) : date('Y-m-d');

$pageError = '';
$items = array();

if (!epson_valid_date($fromDate) || !epson_valid_date($toDate)) {
    $pageError = 'Format tanggal harus YYYY-MM-DD.';
} elseif ($fromDate > $toDate) {
    $pageError = 'FROM DATE tidak boleh lebih besar dari TO DATE.';
} elseif ($sqlsrvConnection === null) {
    $pageError = 'Koneksi database tidak ditemukan. Pastikan database_ordering.php membuat variabel $conn.';
} else {
    // Memanggil Stored Procedure yang baru disesuaikan
    $sql = '{CALL dbo.sp_stok_actual_common(?, ?)}';

    $params = array(
        array($fromDate . ' 00:00:00', SQLSRV_PARAM_IN),
        array($toDate . ' 00:00:00', SQLSRV_PARAM_IN)
    );

    $stmt = sqlsrv_query($sqlsrvConnection, $sql, $params);

    if ($stmt === false) {
        $pageError = epson_sqlsrv_error_text();
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $partId = isset($row['PART_ID']) ? (string) $row['PART_ID'] : '';
            $custId = isset($row['CUST_ID']) ? (string) $row['CUST_ID'] : '';

            if ($partId === '') {
                continue;
            }

            if (!isset($items[$partId])) {
                $items[$partId] = array(
                    'part_id'       => $partId,
                    'part_code'     => isset($row['PART_CODE']) ? $row['PART_CODE'] : '',
                    'part_name'     => isset($row['PART_NAME']) ? $row['PART_NAME'] : '',
                    'part_no'       => isset($row['PART_NO']) ? $row['PART_NO'] : '',
                    'stock'         => epson_number(isset($row['STOCK']) ? $row['STOCK'] : 0),
                    'prod'          => epson_number(isset($row['PROD']) ? $row['PROD'] : 0),
                    'item_delivery' => epson_number(isset($row['ITEM_DELIVERY']) ? $row['ITEM_DELIVERY'] : 0),
                    'stock_actual'  => epson_number(
                        isset($row['STOCK_ACTUAL'])
                            ? $row['STOCK_ACTUAL']
                            : (isset($row['STOCK ACTUAL']) ? $row['STOCK ACTUAL'] : 0)
                    ),
                    'customers'     => array()
                );
            }

            /*
             * Customer detail is grouped by CUST_ID.
             * Delivery, forecast, schedule and plan returned by the SP
             * are already aggregated by PART_ID + CUST_ID.
             */
            if ($custId === '') {
                $custId = 'NO-CUST-' . count($items[$partId]['customers']);
            }

            if (!isset($items[$partId]['customers'][$custId])) {
                $items[$partId]['customers'][$custId] = array(
                    'cust_id'         => $custId,
                    'customer'        => isset($row['CUST_COMP']) ? $row['CUST_COMP'] : '',
                    'delivery'        => 0,
                    'forecast'        => 0,
                    'schedule'        => 0,
                    'plan'            => 0,
                    // Penambahan kolom baru dari SP (jaga-jaga jika diperlukan)
                    'schedule_accmin' => 0,
                    'schedule_c1'     => 0,
                    'schedule_c2'     => 0
                );
            }

            $items[$partId]['customers'][$custId]['delivery'] =
                epson_number(isset($row['DELIVERY']) ? $row['DELIVERY'] : 0);

            $items[$partId]['customers'][$custId]['forecast'] =
                epson_number(isset($row['FORECAST']) ? $row['FORECAST'] : 0);

            $items[$partId]['customers'][$custId]['schedule'] =
                epson_number(isset($row['SCHEDULE']) ? $row['SCHEDULE'] : 0);

            $items[$partId]['customers'][$custId]['plan'] =
                epson_number(isset($row['PLAN']) ? $row['PLAN'] : 0);

            // Fetch tambahan data detail schedule dari SP baru
            $items[$partId]['customers'][$custId]['schedule_accmin'] = 
                epson_number(isset($row['SCHEDULE_ACCMIN']) ? $row['SCHEDULE_ACCMIN'] : 0);
            
            $items[$partId]['customers'][$custId]['schedule_c1'] = 
                epson_number(isset($row['SCHEDULE_C1']) ? $row['SCHEDULE_C1'] : 0);
                
            $items[$partId]['customers'][$custId]['schedule_c2'] = 
                epson_number(isset($row['SCHEDULE_C2']) ? $row['SCHEDULE_C2'] : 0);
        }

        sqlsrv_free_stmt($stmt);

        uasort($items, function ($a, $b) {
            return strcmp($a['part_code'], $b['part_code']);
        });

        foreach ($items as $partId => $itemData) {
            uasort($items[$partId]['customers'], function ($a, $b) {
                return strcmp($a['customer'], $b['customer']);
            });
        }
    }
}

$periodFrom = epson_valid_date($fromDate) ? date('d-M-Y', strtotime($fromDate)) : $fromDate;
$periodTo   = epson_valid_date($toDate) ? date('d-M-Y', strtotime($toDate)) : $toDate;

$exportType = isset($_GET['export']) ? strtolower(trim($_GET['export'])) : '';

if ($exportType === 'excel' && $pageError === '') {
    epson_export_excel($items, $fromDate, $toDate);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Epson Group by Forecast</title>

    <link rel="stylesheet"
          href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">

    <style>
        body {
            background: #eeeeee;
            color: #111111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
        }

        .report-wrapper {
            width: 98%;
            max-width: 1500px;
            margin: 20px auto;
        }

        .filter-panel {
            margin-bottom: 15px;
        }

        .report-paper {
            background: #ffffff;
            padding: 18px 24px 28px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .16);
        }

        .company-title {
            margin: 0;
            font-size: 20px;
            font-weight: bold;
            line-height: 1.25;
        }

        .report-title {
            margin: 2px 0 7px;
            font-size: 18px;
            font-weight: bold;
            line-height: 1.25;
        }

        .period-table {
            width: auto;
            margin-bottom: 8px;
        }

        .period-table td {
            border: 0;
            padding: 0 18px 0 0;
            white-space: nowrap;
        }

        .report-table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: collapse;
        }

        .report-table thead th {
            border-top: 0;
            border-bottom: 1px solid #111111 !important;
            padding: 5px 4px;
            vertical-align: bottom;
            font-size: 11px;
            white-space: nowrap;
        }

        .report-table tbody td {
            border-top: 0;
            padding: 3px 4px;
            vertical-align: top;
        }

        .item-row td {
            border-top: 1px solid #111111 !important;
            padding-top: 6px !important;
            font-weight: bold;
        }

        .customer-row td:first-child {
            padding-left: 55px;
        }

        .total-row td {
            border-bottom: 1px solid #111111 !important;
            font-weight: bold;
            padding-bottom: 6px !important;
        }

        .number {
            text-align: right;
            white-space: nowrap;
        }

        .negative {
            color: #b30000;
            font-weight: bold;
        }

        .muted-value {
            color: #777777;
        }

        .no-data {
            padding: 35px 10px !important;
            text-align: center;
            color: #777777;
        }

        .part-description {
            display: inline-block;
            margin-left: 8px;
            font-weight: normal;
        }

        .report-meta {
            margin-top: 10px;
            color: #777777;
            font-size: 10px;
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 8mm;
            }

            body {
                background: #ffffff;
                font-size: 10px;
            }

            .no-print {
                display: none !important;
            }

            .report-wrapper {
                width: 100%;
                max-width: none;
                margin: 0;
            }

            .report-paper {
                padding: 0;
                box-shadow: none;
            }

            .report-table thead {
                display: table-header-group;
            }

            .item-row {
                page-break-after: avoid;
            }

            .report-meta {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="report-wrapper">

    <div class="panel panel-default filter-panel no-print">
        <div class="panel-body">
            <form method="get" class="form-inline">
                <div class="form-group">
                    <label for="from_date">From date</label>
                    <input type="date"
                           class="form-control input-sm"
                           id="from_date"
                           name="from_date"
                           value="<?php echo epson_escape($fromDate); ?>">
                </div>

                <div class="form-group">
                    <label for="to_date">To date</label>
                    <input type="date"
                           class="form-control input-sm"
                           id="to_date"
                           name="to_date"
                           value="<?php echo epson_escape($toDate); ?>">
                </div>

                <button type="submit" class="btn btn-primary btn-sm">
                    Tampilkan
                </button>

                <button type="button"
                        class="btn btn-default btn-sm"
                        onclick="window.print();">
                    Print
                </button>

                <a class="btn btn-success btn-sm"
                   href="?from_date=<?php echo rawurlencode($fromDate); ?>&amp;to_date=<?php echo rawurlencode($toDate); ?>&amp;export=excel">
                    Export Excel
                </a>
            </form>
        </div>
    </div>

    <div class="report-paper">
        <h1 class="company-title">PT.IMC TEKNO INDONESIA</h1>
        <h2 class="report-title">COMMON PART GROUP BY FORECAST</h2>

        <table class="period-table">
            <tr>
                <td><strong>FROM DATE :</strong></td>
                <td><?php echo epson_escape($periodFrom); ?></td>
                <td style="padding-left: 45px;"><strong>TO DATE :</strong></td>
                <td><?php echo epson_escape($periodTo); ?></td>
            </tr>
        </table>

        <?php if ($pageError !== ''): ?>
            <div class="alert alert-danger">
                <?php echo epson_escape($pageError); ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table report-table">
                    <thead>
                    <tr>
                        <th style="min-width: 330px;">ITEM CODE / CUSTOMER</th>
                        <th class="number">STOCK</th>
                        <th class="number">PROD</th>
                        <th class="number">DELIVERY<br>ACTUAL</th>
                        <th class="number">STOCK<br>ACTUAL</th>
                        <th class="number">FORECAST</th>
                        <th class="number">SCHEDULE</th>
                        <th class="number">PLAN<br>SCH-ACT</th>
                        <th class="number">QUOTA %</th>
                        <th class="number">QUOTA QTY</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (count($items) === 0): ?>
                        <tr>
                            <td colspan="10" class="no-data">
                                Tidak ada data pada periode yang dipilih.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $totalForecast = 0;
                            $totalSchedule = 0;
                            $totalCustomerDelivery = 0;
                            foreach ($item['customers'] as $customer) {
                                $totalForecast += $customer['forecast'];
                                $totalSchedule += $customer['schedule'];
                                $totalCustomerDelivery += $customer['delivery'];
                            }

                            $totalPlan = $totalSchedule - $totalCustomerDelivery;

                            /*
                             * Item-level stock, production, delivery and stock actual come
                             * from the SP. Customer actual/forecast/schedule are keyed by
                             * CUST_ID, so values are not copied to a different customer.
                             */
                            $itemDelivery = $item['item_delivery'];

                            if ($itemDelivery == 0 && $totalCustomerDelivery != 0) {
                                $itemDelivery = $totalCustomerDelivery;
                            }

                            $itemStockActual = $item['stock_actual'];

                            if ($itemStockActual == 0 &&
                                ($item['stock'] != 0 || $item['prod'] != 0 || $itemDelivery != 0)) {
                                $itemStockActual = $item['stock'] + $item['prod'] - $itemDelivery;
                            }
                            ?>
                            <tr class="item-row">
                                <td>
                                    <?php echo epson_escape($item['part_code']); ?>
                                    <span class="part-description">
                                        <?php echo epson_escape($item['part_name']); ?>
                                        <?php if ($item['part_no'] !== ''): ?>
                                            &nbsp;<?php echo epson_escape($item['part_no']); ?>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="number"><?php echo epson_format_number($item['stock']); ?></td>
                                <td class="number"><?php echo epson_format_number($item['prod']); ?></td>
                                <td class="number"><?php echo epson_format_number($itemDelivery); ?></td>
                                <td class="number <?php echo $itemStockActual < 0 ? 'negative' : ''; ?>">
                                    <?php echo epson_format_number($itemStockActual); ?>
                                </td>
                                <td class="number"><?php echo epson_format_number($totalForecast); ?></td>
                                <td class="number"><?php echo epson_format_number($totalSchedule); ?></td>
                                <td class="number <?php echo $totalPlan < 0 ? 'negative' : ''; ?>">
                                    <?php echo epson_format_number($totalPlan); ?>
                                </td>
                                <td class="number">100.00%</td>
                                <td class="number"><?php echo epson_format_number($itemStockActual); ?></td>
                            </tr>

                            <?php foreach ($item['customers'] as $customer): ?>
                                <?php
                                $quotaPercent = 0;
                                $quotaQty = 0;
                                $customerPlan = $customer['schedule'] - $customer['delivery'];

                                if ($totalForecast != 0) {
                                    $quotaPercent = ($customer['forecast'] / $totalForecast) * 100;
                                    $quotaQty = ($itemStockActual * $customer['forecast']) / $totalForecast;
                                }
                                ?>
                                <tr class="customer-row">
                                    <td><?php echo epson_escape($customer['customer']); ?></td>
                                    <td class="number muted-value">-</td>
                                    <td class="number muted-value">-</td>
                                    <td class="number">
                                        <?php echo epson_format_number($customer['delivery']); ?>
                                    </td>
                                    <td class="number muted-value">-</td>
                                    <td class="number">
                                        <?php echo epson_format_number($customer['forecast']); ?>
                                    </td>
                                    <td class="number">
                                        <?php echo epson_format_number($customer['schedule']); ?>
                                    </td>
                                    <td class="number <?php echo $customerPlan < 0 ? 'negative' : ''; ?>">
                                        <?php echo epson_format_number($customerPlan); ?>
                                    </td>
                                    <td class="number">
                                        <?php echo number_format($quotaPercent, 2, '.', ','); ?>%
                                    </td>
                                    <td class="number <?php echo $quotaQty < 0 ? 'negative' : ''; ?>">
                                        <?php echo epson_format_number($quotaQty); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <tr class="total-row">
                                <td class="number">TOTAL</td>
                                <td></td>
                                <td></td>
                                <td class="number"><?php echo epson_format_number($totalCustomerDelivery); ?></td>
                                <td></td>
                                <td class="number"><?php echo epson_format_number($totalForecast); ?></td>
                                <td class="number"><?php echo epson_format_number($totalSchedule); ?></td>
                                <td class="number <?php echo $totalPlan < 0 ? 'negative' : ''; ?>">
                                    <?php echo epson_format_number($totalPlan); ?>
                                </td>
                                <td class="number">100.00%</td>
                                <td class="number"><?php echo epson_format_number($itemStockActual); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="report-meta">
            Generated: <?php echo date('d-M-Y H:i:s'); ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>
</html>
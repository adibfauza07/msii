<?php
// ============================================================
// REPORT MATERIAL PRICE LIST
// PHP 5.4 + SQL Server sqlsrv + Bootstrap 3
// Stored procedure: dbo.RPT_MATPRICELIST_CTH
// ============================================================
set_time_limit(180);

if (session_id() === '') {
    session_start();
}

require_once dirname(__DIR__) . "/config/db_plant2.php";

if (!isset($conn) || $conn === false) {
    die('<div style="padding:24px;color:#b91c1c;background:#fff;font-family:Arial,sans-serif;">Koneksi database gagal. Silakan login terlebih dahulu.</div>');
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function display_date($value) {
    if ($value === null || $value === '') return '';
    if ($value instanceof DateTime) return $value->format('d-M-Y');
    $timestamp = strtotime((string)$value);
    return $timestamp !== false ? date('d-M-Y', $timestamp) : (string)$value;
}

function display_number($value, $decimals = 2) {
    if ($value === null || $value === '') return '';
    return number_format((float)$value, (int)$decimals, ',', '.');
}

function sql_error_text() {
    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    if (!is_array($errors) || count($errors) === 0) return 'Kesalahan SQL Server tidak diketahui.';
    $messages = array();
    foreach ($errors as $error) {
        $messages[] = trim('[' . $error['SQLSTATE'] . '] ' . $error['message']);
    }
    return implode(' | ', $messages);
}

$itemName = isset($_GET['item_name']) ? trim($_GET['item_name']) : '';
$isExport = isset($_GET['export']) && $_GET['export'] === 'excel';
$submitted = isset($_GET['show_report']) || $isExport;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50; // Jumlah data per halaman

$errorMessage = '';
$reportRows = array();

/*
|--------------------------------------------------------------------------
| Jalankan Report (Stored Procedure)
|--------------------------------------------------------------------------
*/
if ($submitted) {
    $nameParam = $itemName === '' ? '%' : '%' . $itemName . '%';

    $reportSql = "EXEC dbo.RPT_MATPRICELIST_CTH @NAME = ?";
    $reportParams = array($nameParam);
    $reportStmt = @sqlsrv_query($conn, $reportSql, $reportParams);

    if ($reportStmt === false) {
        $errorMessage = 'Report gagal dijalankan: ' . sql_error_text();
    } else {
        while ($row = sqlsrv_fetch_array($reportStmt, SQLSRV_FETCH_ASSOC)) {
            $reportRows[] = $row;
        }
        sqlsrv_free_stmt($reportStmt);
    }
}

$totalData = count($reportRows);
$totalPages = ceil($totalData / $limit);
$offset = ($page - 1) * $limit;
$webRows = array_slice($reportRows, $offset, $limit);

/*
|--------------------------------------------------------------------------
| Export to Excel (Export SELURUH DATA)
|--------------------------------------------------------------------------
*/
if ($isExport && $errorMessage === '') {
    $filename = 'Material_PriceList_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    ?>
    <table border="1">
        <thead>
            <tr><th colspan="13" style="font-size:16px; font-weight:bold; text-align:center;">MATERIAL PRICE LIST</th></tr>
            <tr>
                <th>No</th><th>Supplier Code</th><th>Supplier Name</th><th>Item Code</th><th>Item Name</th>
                <th>Maker</th><th>Customer</th><th>Curr</th><th>Price</th><th>Unit</th><th>Min Qty</th>
                <th>Quotation No</th><th>Eff Date</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($reportRows as $r) { ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td><?php echo h($r['SUP_CODE']); ?></td><td><?php echo h($r['SUP_COMP']); ?></td>
                <td><?php echo h($r['ITEM_CODE']); ?></td><td><?php echo h($r['ITEM_NAME']); ?></td>
                <td><?php echo h($r['MAKER']); ?></td><td><?php echo h($r['CUST_COMP']); ?></td>
                <td><?php echo h($r['CURR_CODE']); ?></td><td><?php echo h((float)$r['QUOD_PRICE']); ?></td>
                <td><?php echo h($r['QUOD_UNIT']); ?></td><td><?php echo h((float)$r['QUOD_MINQTY']); ?></td>
                <td><?php echo h($r['QUO_NO']); ?></td><td><?php echo h(display_date($r['QUO_EFFDATE'])); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Report Material Price List</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <style>
        body { padding: 18px 0 30px; background: #eef3f8; }
        .report-container { width: 98%; margin: 0 auto; }
        .panel { border-color: #337ab7; }
        .panel-heading h3 { margin: 0; font-weight: bold; }
        .table-report { background: #fff; font-size: 11px; white-space: nowrap; }
        .table-report th { background: #eaf1f8; text-align: center; vertical-align: middle !important; border-bottom: 2px solid #ccc !important; }
        .text-number { text-align: right; }
        .filter-summary { margin-bottom: 10px; padding: 8px 10px; border: 1px solid #c4d2df; background: #f8fbfe; font-size: 12px; }
        .empty-result { padding: 35px; text-align: center; color: #777; background: #fff; border: 1px solid #ddd; }
        .pagination { margin: 0; }
    </style>
</head>
<body>

<div class="report-container">
    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title"><span class="glyphicon glyphicon-list-alt"></span> Report Material Price List</h3>
        </div>

        <div class="panel-body">
            <?php if ($errorMessage !== '') { ?>
                <div class="alert alert-danger"><?php echo h($errorMessage); ?></div>
            <?php } ?>

            <form method="get" action="" autocomplete="off" class="form-inline">
                <div class="form-group" style="margin-right: 15px;">
                    <label for="item_name">Item Name : </label>
                    <input type="text" class="form-control input-sm" id="item_name" name="item_name" value="<?php echo h($itemName); ?>" placeholder="Kosongkan untuk semua">
                </div>

                <button type="submit" class="btn btn-primary btn-sm" name="show_report" value="1">
                    <span class="glyphicon glyphicon-search"></span> Tampilkan
                </button>
                <a href="report_price_list.php" class="btn btn-default btn-sm"><span class="glyphicon glyphicon-refresh"></span> Reset</a>

                <?php if ($submitted && $errorMessage === '' && $totalData > 0) { ?>
                    <!-- Ubah Tombol Cetak untuk membuka Tab Baru -->
                    <button type="button" class="btn btn-default btn-sm" onclick="window.open('print_price_list.php?item_name=<?php echo urlencode($itemName); ?>', '_blank');">
                        <span class="glyphicon glyphicon-print"></span> Cetak
                    </button>
                    <button type="submit" name="export" value="excel" class="btn btn-success btn-sm">
                        <span class="glyphicon glyphicon-export"></span> Excel
                    </button>
                <?php } ?>
            </form>
        </div>

        <?php if ($submitted && $errorMessage === '') { ?>
            <div class="panel-body">
                <div class="filter-summary">
                    <strong>Pencarian Item:</strong> <?php echo $itemName === '' ? 'Semua Item' : h($itemName); ?>
                    &nbsp; | &nbsp;
                    <strong>Total Baris:</strong> <?php echo $totalData; ?>
                    <span style="float:right; color:#a94442;"><i>* Menampilkan Halaman <?php echo $page; ?> dari <?php echo $totalPages; ?></i></span>
                </div>

                <?php if ($totalData > 0) { ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-condensed table-report">
                            <thead>
                            <tr>
                                <th>No.</th><th>Sup. Code</th><th>Supplier Name</th><th>Item Code</th><th>Item Name</th>
                                <th>Maker</th><th>Customer</th><th>Cur</th><th>Price</th><th>Unit</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $no = $offset + 1;
                            foreach ($webRows as $row) {
                                ?>
                                <tr>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td class="text-center"><?php echo h(isset($row['SUP_CODE']) ? $row['SUP_CODE'] : ''); ?></td>
                                    <td><?php echo h(isset($row['SUP_COMP']) ? $row['SUP_COMP'] : ''); ?></td>
                                    <td><?php echo h(isset($row['ITEM_CODE']) ? $row['ITEM_CODE'] : ''); ?></td>
                                    <td><?php echo h(isset($row['ITEM_NAME']) ? $row['ITEM_NAME'] : ''); ?></td>
                                    <td><?php echo h(isset($row['MAKER']) ? $row['MAKER'] : ''); ?></td>
                                    <td><?php echo h(isset($row['CUST_COMP']) ? $row['CUST_COMP'] : ''); ?></td>
                                    <td class="text-center"><?php echo h(isset($row['CURR_CODE']) ? $row['CURR_CODE'] : ''); ?></td>
                                    <td class="text-number" style="font-weight:bold;">
                                        <?php echo h(display_number(isset($row['QUOD_PRICE']) ? $row['QUOD_PRICE'] : 0, 4)); ?>
                                    </td>
                                    <td class="text-center"><?php echo h(isset($row['QUOD_UNIT']) ? $row['QUOD_UNIT'] : ''); ?></td>
                                </tr>
                                <?php
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINATION -->
                    <div class="text-center">
                        <ul class="pagination pagination-sm">
                            <?php if ($page > 1) { ?>
                                <li><a href="?show_report=1&item_name=<?php echo urlencode($itemName); ?>&page=1">&laquo; First</a></li>
                                <li><a href="?show_report=1&item_name=<?php echo urlencode($itemName); ?>&page=<?php echo $page - 1; ?>">Prev</a></li>
                            <?php } ?>

                            <?php 
                            $startPage = max(1, $page - 3);
                            $endPage = min($totalPages, $page + 3);
                            for ($i = $startPage; $i <= $endPage; $i++) { 
                                $active = ($i == $page) ? 'class="active"' : '';
                            ?>
                                <li <?php echo $active; ?>><a href="?show_report=1&item_name=<?php echo urlencode($itemName); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a></li>
                            <?php } ?>

                            <?php if ($page < $totalPages) { ?>
                                <li><a href="?show_report=1&item_name=<?php echo urlencode($itemName); ?>&page=<?php echo $page + 1; ?>">Next</a></li>
                                <li><a href="?show_report=1&item_name=<?php echo urlencode($itemName); ?>&page=<?php echo $totalPages; ?>">Last &raquo;</a></li>
                            <?php } ?>
                        </ul>
                    </div>

                <?php } else { ?>
                    <div class="empty-result">
                        <span class="glyphicon glyphicon-info-sign"></span>
                        Data tidak ditemukan.
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>

</body>
</html>
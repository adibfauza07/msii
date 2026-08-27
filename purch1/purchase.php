<?php
// ============================================================
// PURCHASE REPORT
// PHP 5.4 + SQL Server sqlsrv + Bootstrap 3 + jQuery UI
//
// global.php              : konfigurasi aplikasi/session
// database_ordering.php   : wajib menghasilkan variabel $conn
// Autocomplete:
//   - search_item.php
//   - search_receive.php
// Stored procedure:
//   - dbo.RPT_PURCHASE_YEAR2
// ============================================================
set_time_limit(120);

if (session_id() === '') {
    session_start();
}

require_once __DIR__ . "/../config/global.php";


function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sql_error_text()
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

function display_date($value)
{
    if ($value === null || $value === '') {
        return '';
    }

    if ($value instanceof DateTime) {
        return $value->format('d-m-Y');
    }

    $timestamp = strtotime((string)$value);
    return $timestamp !== false ? date('d-m-Y', $timestamp) : (string)$value;
}

function display_number($value, $decimals)
{
    if ($value === null || $value === '') {
        return '';
    }

    return number_format((float)$value, (int)$decimals, ',', '.');
}

function valid_date_ymd($value)
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    $parts = explode('-', $value);

    if (count($parts) !== 3) {
        return false;
    }

    return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
}

if (!isset($conn) || $conn === false) {
    die(
        '<div style="margin:20px;padding:15px;border:1px solid #b91c1c;' .
        'background:#fff5f5;color:#b91c1c;font-family:Arial,sans-serif;">' .
        '<strong>Koneksi database belum tersedia.</strong><br>' .
        'Pastikan <code>../config/database_ordering.php</code> membuat variabel <code>$conn</code>.' .
        '</div>'
    );
}

$defaultStartDate = date('Y-m-01');
$defaultEndDate = date('Y-m-d');

$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : $defaultStartDate;
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : $defaultEndDate;
$itemCode = isset($_GET['item_code']) ? trim($_GET['item_code']) : '';
$supCode = isset($_GET['sup_code']) ? trim($_GET['sup_code']) : '';
$receiveNo = isset($_GET['receive_no']) ? trim($_GET['receive_no']) : '';

$submitted = isset($_GET['show_report']);
$errorMessage = '';
$warningMessage = '';
$reportRows = array();
$suppliers = array();

/*
|--------------------------------------------------------------------------
| Dropdown supplier
|--------------------------------------------------------------------------
*/
$supplierSql = "
    SELECT
        SUP_CODE,
        SUP_COMP
    FROM dbo.SUPPLIER
    WHERE SUP_CODE IS NOT NULL
    ORDER BY SUP_CODE
";

$supplierStmt = @sqlsrv_query($conn, $supplierSql);

if ($supplierStmt === false) {
    $errorMessage = 'Daftar supplier gagal dibaca: ' . sql_error_text();
} else {
    while ($supplierRow = sqlsrv_fetch_array($supplierStmt, SQLSRV_FETCH_ASSOC)) {
        $suppliers[] = $supplierRow;
    }

    sqlsrv_free_stmt($supplierStmt);
}

/*
|--------------------------------------------------------------------------
| Jalankan report
|--------------------------------------------------------------------------
*/
if ($submitted && $errorMessage === '') {
    if (!valid_date_ymd($startDate) || !valid_date_ymd($endDate)) {
        $errorMessage = 'Tanggal awal atau tanggal akhir tidak valid.';
    } elseif (strtotime($startDate) > strtotime($endDate)) {
        $errorMessage = 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.';
    } else {
        $startDateSql = $startDate . ' 00:00:00';
        $endDateSql = $endDate . ' 23:59:59';

        /*
         * Stored procedure memakai LIKE.
         * Nilai kosong dikirim sebagai wildcard.
         *
         * Catatan:
         * Agar receive kosong menampilkan PO yang RCV_NOMOR-nya NULL,
         * gunakan file RPT_PURCHASE_YEAR2_fix.sql yang disertakan.
         */
        $itemCodeParam = $itemCode === '' ? '%' : $itemCode;
        $supCodeParam = $supCode === '' ? '%' : $supCode;
        $receiveNoParam = $receiveNo === '' ? '%' : $receiveNo;

        $reportSql = "
            EXEC dbo.RPT_PURCHASE_YEAR2
                @START_DATE = ?,
                @END_DATE = ?,
                @CODE = ?,
                @SUP_CODE = ?,
                @RCV_NOMOR = ?
        ";

        $reportParams = array(
            $startDateSql,
            $endDateSql,
            $itemCodeParam,
            $supCodeParam,
            $receiveNoParam
        );

        $reportStmt = @sqlsrv_query($conn, $reportSql, $reportParams);

        if ($reportStmt === false) {
            $errorMessage = 'Report gagal dijalankan: ' . sql_error_text();
        } else {
            /*
             * Beberapa stored procedure menghasilkan result set tambahan.
             * Cari result set yang benar-benar mempunyai kolom.
             */
            $hasColumns = sqlsrv_num_fields($reportStmt) !== false;

            while (!$hasColumns && sqlsrv_next_result($reportStmt)) {
                $hasColumns = sqlsrv_num_fields($reportStmt) !== false;
            }

            if ($hasColumns) {
                while ($reportRow = sqlsrv_fetch_array($reportStmt, SQLSRV_FETCH_ASSOC)) {
                    $reportRows[] = $reportRow;
                }
            }

            sqlsrv_free_stmt($reportStmt);

            if (count($reportRows) === 0) {
                $warningMessage =
                    'Query berhasil tetapi tidak menghasilkan data. ' .
                    'Periksa periode yang dipilih dan jalankan perbaikan stored procedure ' .
                    'agar filter RCV_NOMOR kosong juga menerima nilai NULL.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Purchase Report</title>

    <link rel="stylesheet"
          href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">

    <style type="text/css">
        body {
            padding: 18px 0 30px;
            background: #eef3f8;
        }

        .report-container {
            width: 98%;
            margin: 0 auto;
        }

        .panel {
            border-color: #7d9fc1;
        }

        .panel-heading h3 {
            margin: 0;
        }

        .ui-autocomplete {
            z-index: 99999;
            max-height: 260px;
            overflow-y: auto;
            overflow-x: hidden;
            font-size: 12px;
        }

        .autocomplete-loading {
            background-image: url("https://code.jquery.com/ui/1.12.1/themes/base/images/ui-anim_basic_16x16.gif");
            background-position: right center;
            background-repeat: no-repeat;
        }

        .table-report {
            background: #fff;
            font-size: 12px;
            white-space: nowrap;
        }

        .table-report th {
            background: #eaf1f8;
            text-align: center;
            vertical-align: middle !important;
        }

        .text-number {
            text-align: right;
        }

        .filter-summary {
            margin-bottom: 10px;
            padding: 8px 10px;
            border: 1px solid #c4d2df;
            background: #f8fbfe;
            font-size: 12px;
        }

        .empty-result {
            padding: 35px;
            text-align: center;
            color: #777;
            background: #fff;
            border: 1px solid #ddd;
        }

        .help-block {
            margin-bottom: 0;
            font-size: 11px;
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
                font-size: 9px;
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
                Purchase Report
            </h3>
        </div>

        <div class="panel-body no-print">
            <?php if ($errorMessage !== '') { ?>
                <div class="alert alert-danger">
                    <?php echo h($errorMessage); ?>
                </div>
            <?php } ?>

            <?php if ($warningMessage !== '') { ?>
                <div class="alert alert-warning">
                    <?php echo h($warningMessage); ?>
                </div>
            <?php } ?>

            <form method="get" action="" autocomplete="off">
                <div class="row">
                    <div class="col-sm-2">
                        <div class="form-group">
                            <label for="start_date">Tanggal Awal</label>
                            <input type="date"
                                   class="form-control"
                                   id="start_date"
                                   name="start_date"
                                   value="<?php echo h($startDate); ?>"
                                   required>
                        </div>
                    </div>

                    <div class="col-sm-2">
                        <div class="form-group">
                            <label for="end_date">Tanggal Akhir</label>
                            <input type="date"
                                   class="form-control"
                                   id="end_date"
                                   name="end_date"
                                   value="<?php echo h($endDate); ?>"
                                   required>
                        </div>
                    </div>

                    <div class="col-sm-2">
                        <div class="form-group">
                            <label for="item_code">Item Code</label>
                            <input type="text"
                                   class="form-control"
                                   id="item_code"
                                   name="item_code"
                                   value="<?php echo h($itemCode); ?>"
                                   placeholder="Ketik kode/nama item">
                            <span class="help-block">Kosong = semua item</span>
                        </div>
                    </div>

                    <div class="col-sm-3">
                        <div class="form-group">
                            <label for="sup_code">Supplier</label>
                            <select class="form-control" id="sup_code" name="sup_code">
                                <option value="">-- Semua Supplier --</option>

                                <?php foreach ($suppliers as $supplier) { ?>
                                    <?php
                                    $supplierCode = isset($supplier['SUP_CODE'])
                                        ? trim((string)$supplier['SUP_CODE'])
                                        : '';

                                    $supplierName = isset($supplier['SUP_COMP'])
                                        ? trim((string)$supplier['SUP_COMP'])
                                        : '';
                                    ?>
                                    <option value="<?php echo h($supplierCode); ?>"
                                        <?php echo $supCode === $supplierCode ? 'selected="selected"' : ''; ?>>
                                        <?php
                                        echo h(
                                            $supplierCode .
                                            ($supplierName !== '' ? ' - ' . $supplierName : '')
                                        );
                                        ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <span class="help-block">Tidak dipilih = semua supplier</span>
                        </div>
                    </div>

                    <div class="col-sm-3">
                        <div class="form-group">
                            <label for="receive_no">Receive No.</label>
                            <input type="text"
                                   class="form-control"
                                   id="receive_no"
                                   name="receive_no"
                                   value="<?php echo h($receiveNo); ?>"
                                   placeholder="Ketik RCV_NOMOR">
                            <span class="help-block">Kosong = semua receive</span>
                        </div>
                    </div>
                </div>

                <button type="submit"
                        class="btn btn-primary"
                        name="show_report"
                        value="1">
                    <span class="glyphicon glyphicon-search"></span>
                    Tampilkan
                </button>

                <a href="<?php echo h(basename($_SERVER['PHP_SELF'])); ?>"
                   class="btn btn-default">
                    <span class="glyphicon glyphicon-refresh"></span>
                    Reset
                </a>

                <?php if ($submitted && $errorMessage === '') { ?>
                    <button type="button"
                            class="btn btn-success"
                            onclick="window.print();">
                        <span class="glyphicon glyphicon-print"></span>
                        Cetak
                    </button>
                <?php } ?>
            </form>
        </div>

        <?php if ($submitted && $errorMessage === '') { ?>
            <div class="panel-body">
                <div class="filter-summary">
                    <strong>Periode:</strong>
                    <?php echo h(display_date($startDate)); ?>
                    s/d
                    <?php echo h(display_date($endDate)); ?>

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
                                $qtyPo = isset($row['QTY']) ? (float)$row['QTY'] : 0;
                                $qtyReceive = isset($row['RCVD_QTY']) ? (float)$row['RCVD_QTY'] : 0;

                                $totalQtyPo += $qtyPo;
                                $totalQtyReceive += $qtyReceive;
                                ?>
                                <tr>
                                    <td class="text-center"><?php echo $no; ?></td>
                                    <td><?php echo h(display_date(isset($row['PO_DATE']) ? $row['PO_DATE'] : '')); ?></td>
                                    <td><?php echo h(isset($row['PO_NUM']) ? $row['PO_NUM'] : ''); ?></td>
                                    <td><?php echo h(isset($row['ITEM_CODE']) ? $row['ITEM_CODE'] : ''); ?></td>
                                    <td><?php echo h(isset($row['ITEM_NAME']) ? $row['ITEM_NAME'] : ''); ?></td>
                                    <td>
                                        <?php
                                        $rowSupCode = isset($row['SUP_CODE'])
                                            ? trim((string)$row['SUP_CODE'])
                                            : '';

                                        $rowSupName = isset($row['SUP_COMP'])
                                            ? trim((string)$row['SUP_COMP'])
                                            : '';

                                        echo h(
                                            $rowSupCode .
                                            ($rowSupName !== '' ? ' - ' . $rowSupName : '')
                                        );
                                        ?>
                                    </td>
                                    <td class="text-number"><?php echo h(display_number($qtyPo, 2)); ?></td>
                                    <td class="text-number"><?php echo h(display_number($qtyReceive, 2)); ?></td>
                                    <td><?php echo h(isset($row['POD_UNIT']) ? $row['POD_UNIT'] : ''); ?></td>
                                    <td class="text-number">
                                        <?php echo h(display_number(isset($row['POD_PRICE']) ? $row['POD_PRICE'] : '', 2)); ?>
                                    </td>
                                    <td class="text-number">
                                        <?php echo h(display_number(isset($row['RCV_PRICE']) ? $row['RCV_PRICE'] : '', 2)); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo h(isset($row['PO_CUR']) ? $row['PO_CUR'] : ''); ?>
                                    </td>
                                    <td>
                                        <?php echo h(display_date(isset($row['POD_DUE']) ? $row['POD_DUE'] : '')); ?>
                                    </td>
                                    <td>
                                        <?php echo h(isset($row['RCV_NOMOR']) ? $row['RCV_NOMOR'] : ''); ?>
                                    </td>
                                    <td>
                                        <?php echo h(display_date(isset($row['RCV_DATE']) ? $row['RCV_DATE'] : '')); ?>
                                    </td>
                                </tr>
                                <?php
                                $no++;
                            }
                            ?>
                            </tbody>
                            <tfoot>
                            <tr>
                                <th colspan="6" class="text-right">TOTAL</th>
                                <th class="text-number">
                                    <?php echo h(display_number($totalQtyPo, 2)); ?>
                                </th>
                                <th class="text-number">
                                    <?php echo h(display_number($totalQtyReceive, 2)); ?>
                                </th>
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
    $("#item_code").autocomplete({
        minLength: 1,
        delay: 250,
        source: function (request, response) {
            $("#item_code").addClass("autocomplete-loading");

            $.ajax({
                url: "search_item.php",
                type: "POST",
                dataType: "json",
                data: {
                    q: request.term
                },
                success: function (data) {
                    response($.map(data, function (item) {
                        var code = item.ITEM_CODE || "";
                        var name = item.ITEM_NAME || "";

                        return {
                            label: code + (name !== "" ? " - " + name : ""),
                            value: code
                        };
                    }));
                },
                error: function () {
                    response([]);
                },
                complete: function () {
                    $("#item_code").removeClass("autocomplete-loading");
                }
            });
        },
        select: function (event, ui) {
            $(this).val(ui.item.value);
            return false;
        }
    });

    $("#receive_no").autocomplete({
        minLength: 1,
        delay: 250,
        source: function (request, response) {
            $("#receive_no").addClass("autocomplete-loading");

            $.ajax({
                url: "search_receive.php",
                type: "POST",
                dataType: "json",
                data: {
                    q: request.term
                },
                success: function (data) {
                    response($.map(data, function (item) {
                        var receiveNo = item.RCV_NOMOR || "";

                        return {
                            label: receiveNo,
                            value: receiveNo
                        };
                    }));
                },
                error: function () {
                    response([]);
                },
                complete: function () {
                    $("#receive_no").removeClass("autocomplete-loading");
                }
            });
        },
        select: function (event, ui) {
            $(this).val(ui.item.value);
            return false;
        }
    });
});
</script>
</body>
</html>
<?php
sqlsrv_close($conn);
?>

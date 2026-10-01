<?php
// ============================================================
// PURCHASE REPORT
// PHP 5.4 + SQL Server sqlsrv + Bootstrap 3 + jQuery UI
// ============================================================
set_time_limit(180);

if (session_id() === '') {
    session_start();
}

require_once __DIR__ . "/../config/global.php";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sql_error_text() {
    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    if (!is_array($errors) || count($errors) === 0) return 'Kesalahan SQL Server tidak diketahui.';
    $messages = array();
    foreach ($errors as $error) {
        $messages[] = trim('[' . (isset($error['SQLSTATE']) ? $error['SQLSTATE'] : '') . '] ' . (isset($error['message']) ? $error['message'] : ''));
    }
    return implode(' | ', $messages);
}

function display_date($value) {
    if ($value === null || $value === '') return '';
    if ($value instanceof DateTime) return $value->format('d-M-Y');
    $timestamp = strtotime((string)$value);
    return $timestamp !== false ? date('d-M-Y', $timestamp) : (string)$value;
}

function display_number($value, $decimals) {
    if ($value === null || $value === '') return '';
    return number_format((float)$value, (int)$decimals, '.', ',');
}

function valid_date_ymd($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    $parts = explode('-', $value);
    return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
}

if (!isset($conn) || $conn === false) {
    die('<div class="alert alert-danger">Koneksi database belum tersedia.</div>');
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
$reportRows = array();
$suppliers = array();

/* =========================================================
   Ambil data supplier untuk Autocomplete
========================================================= */
$supplierStmt = @sqlsrv_query($conn, "SELECT SUP_CODE, SUP_COMP FROM dbo.SUPPLIER WHERE SUP_CODE IS NOT NULL ORDER BY SUP_CODE");
if ($supplierStmt !== false) {
    while ($supplierRow = sqlsrv_fetch_array($supplierStmt, SQLSRV_FETCH_ASSOC)) {
        $suppliers[] = $supplierRow;
    }
    sqlsrv_free_stmt($supplierStmt);
}

/* =========================================================
   Jalankan Report & Filtering
========================================================= */
if ($submitted && $errorMessage === '') {
    if (!valid_date_ymd($startDate) || !valid_date_ymd($endDate)) {
        $errorMessage = 'Tanggal awal atau tanggal akhir tidak valid.';
    } elseif (strtotime($startDate) > strtotime($endDate)) {
        $errorMessage = 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.';
    } else {
        
        $startDateYmd = date('Y-m-d', strtotime($startDate));
        $endDateYmd   = date('Y-m-d', strtotime($endDate));

        $itemParam = $itemCode !== '' ? $itemCode . '%' : '';
        $supParam  = $supCode !== '' ? $supCode . '%' : '';
        $rcvParam  = $receiveNo !== '' ? $receiveNo . '%' : '';

        $reportSql = "EXEC dbo.RPT_PURCHASE_YEAR2 @CODE = ?, @SUP_CODE = ?, @RCV_NOMOR = ?";
        $reportParams = array($itemParam, $supParam, $rcvParam);
        $reportStmt = @sqlsrv_query($conn, $reportSql, $reportParams);

        if ($reportStmt === false) {
            $errorMessage = 'Report gagal dijalankan: ' . sql_error_text();
        } else {
            $hasColumns = sqlsrv_num_fields($reportStmt) !== false;
            while (!$hasColumns && sqlsrv_next_result($reportStmt)) {
                $hasColumns = sqlsrv_num_fields($reportStmt) !== false;
            }

            if ($hasColumns) {
                while ($reportRow = sqlsrv_fetch_array($reportStmt, SQLSRV_FETCH_ASSOC)) {
                    
                    // =====================================================
                    // FILTERING PRESISI DI PHP
                    // =====================================================
                    
                    // 1. Filter Tanggal (PO Date)
                    $poDate = isset($reportRow['PO_DATE']) ? $reportRow['PO_DATE'] : null;
                    if ($poDate !== null) {
                        $poDateYmd = ($poDate instanceof DateTime) ? $poDate->format('Y-m-d') : date('Y-m-d', strtotime((string)$poDate));
                        if ($poDateYmd < $startDateYmd || $poDateYmd > $endDateYmd) {
                            continue; 
                        }
                    }

                    // 2. Filter Item Code / Name
                    if ($itemCode !== '') {
                        $rItemCode = isset($reportRow['ITEM_CODE']) ? trim((string)$reportRow['ITEM_CODE']) : '';
                        $rItemName = isset($reportRow['ITEM_NAME']) ? trim((string)$reportRow['ITEM_NAME']) : '';
                        if (stripos($rItemCode, $itemCode) === false && stripos($rItemName, $itemCode) === false) {
                            continue;
                        }
                    }

                    // 3. Filter Supplier
                    if ($supCode !== '') {
                        $rSupCode = isset($reportRow['SUP_CODE']) ? trim((string)$reportRow['SUP_CODE']) : '';
                        if (stripos($rSupCode, $supCode) === false) {
                            continue;
                        }
                    }

                    // 4. Filter Receive No
                    if ($receiveNo !== '') {
                        $rRcvNo = isset($reportRow['RCV_NOMOR']) ? trim((string)$reportRow['RCV_NOMOR']) : '';
                        if (stripos($rRcvNo, $receiveNo) === false) {
                            continue;
                        }
                    }
                    
                    $reportRows[] = $reportRow;
                }
            }
            sqlsrv_free_stmt($reportStmt);
        }
    }
}

// Data Perusahaan
$companyName = "PT. IMC TEKNO INDONESIA PLANT 1";
$companyAddress = "Kawasan Berikat, NSS Indonesia<br>Kota Bukit Indah Dangdeur Bungursari<br>Kab. Purwakarta, Jawa Barat 41181<br>Phone : (0264)351440";
if (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2') {
    $companyName = "PT. IMC TEKNO INDONESIA PLANT 2";
    $companyAddress = "Kawasan Industri Kota Bukit Indah<br>Blok A-III No. 15E Dangdeur Bungursari<br>Kab. Purwakarta, Jawa Barat 41181<br>Phone : (0264)351440";
}

$linesPerPage = 12; 
$chunks = array_chunk($reportRows, $linesPerPage);
if (empty($chunks)) $chunks = [[]];
$totalPages = count($chunks);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Purchase Report</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <style type="text/css">
        /* ==== CSS GLOBAL & NO-PRINT (Form Pencarian) ==== */
        body { background: #eef3f8; font-family: Arial, sans-serif; }
        .no-print { padding: 20px; }
        .ui-autocomplete { z-index: 99999; max-height: 260px; overflow-y: auto; font-size: 12px; }

        /* ==== CSS KHUSUS PRINT & PAGE CONTAINER ==== */
        .page-container {
            background: #fff; width: 210mm; min-height: 140mm; 
            margin: 0 auto 20px auto; padding: 15px 20px; box-sizing: border-box;
            position: relative; overflow: hidden; border: 1px solid #ccc; box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        .header-container { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 5px; }
        .company-name { font-size: 14px; font-weight: bold; text-decoration: underline; margin-bottom: 2px; line-height: 1.1; color:#000;}
        .doc-title { font-size: 14px; font-weight: bold; margin-bottom: 2px; line-height: 1.1; color:#000;}
        .company-addr { font-size: 10px; line-height: 1; color: #000;}
        .page-info { font-size: 10px; font-weight: bold; text-align: right; margin-bottom: 2px; line-height: 1; color:#000;}

        .meta-table { width: 100%; font-size: 11px; font-weight: bold; margin-bottom: 5px; border-collapse: collapse; color:#000;}
        .meta-table td { padding: 0; line-height: 1.1; vertical-align: top;}

        /* Data Tabel 15 Kolom */
        .data-table { width: 100%; border-collapse: collapse; font-size: 8px; margin-bottom: 5px; color:#000; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 2px 3px; }
        .data-table th { font-weight: bold; text-align: center; vertical-align: middle; background-color: #f8f9fa; }
        .data-table td { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .wrap-text { white-space: normal !important; }
        
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }

        @media print {
            @page { size: 210mm 140mm; margin: 5mm 8mm; }
            body { background: #fff; padding: 0; margin: 0; }
            .no-print { display: none !important; }
            .page-container { 
                width: 100%; height: 130mm; min-height: 130mm; 
                padding: 0; margin: 0; border: none; box-shadow: none; 
            }
            .page-break { page-break-after: always; }
        }
    </style>
</head>
<body>

<!-- ================= FORM PENCARIAN (NO-PRINT) ================= -->
<div class="no-print">
    <div class="panel panel-primary" style="margin: 0 auto; max-width: 1200px;">
        <div class="panel-heading">
            <h3 class="panel-title"><span class="glyphicon glyphicon-shopping-cart"></span> Purchase Report</h3>
        </div>
        <div class="panel-body">
            <?php if ($errorMessage !== '') { echo '<div class="alert alert-danger">'.h($errorMessage).'</div>'; } ?>
            <form method="get" action="" autocomplete="off">
                <div class="row">
                    <div class="col-sm-2">
                        <div class="form-group">
                            <label>Tanggal Awal (PO Date)</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo h($startDate); ?>" required>
                        </div>
                    </div>
                    <div class="col-sm-2">
                        <div class="form-group">
                            <label>Tanggal Akhir (PO Date)</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo h($endDate); ?>" required>
                        </div>
                    </div>
                    <div class="col-sm-2">
                        <div class="form-group">
                            <label>Item Code / Name</label>
                            <input type="text" class="form-control" id="item_code" name="item_code" value="<?php echo h($itemCode); ?>" placeholder="Kosong = Semua">
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="form-group">
                            <label>Supplier Code / Name</label>
                            <input type="text" class="form-control" id="sup_code" name="sup_code" value="<?php echo h($supCode); ?>" placeholder="Kosong = Semua">
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="form-group">
                            <label>Receive No.</label>
                            <input type="text" class="form-control" id="receive_no" name="receive_no" value="<?php echo h($receiveNo); ?>" placeholder="Kosong = Semua">
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" name="show_report" value="1"><span class="glyphicon glyphicon-search"></span> Tampilkan</button>
                <a href="<?php echo h(basename($_SERVER['PHP_SELF'])); ?>" class="btn btn-default"><span class="glyphicon glyphicon-refresh"></span> Reset</a>
                <?php if ($submitted && $errorMessage === '' && count($reportRows) > 0) { ?>
                    <button type="button" class="btn btn-success" onclick="window.print();"><span class="glyphicon glyphicon-print"></span> Cetak Report</button>
                <?php } ?>
            </form>
        </div>
    </div>
</div>

<!-- ================= CETAKAN REPORT (PAGE CONTAINER) ================= -->
<?php if ($submitted && $errorMessage === '' && count($reportRows) > 0) { 
    $globalNo = 1;
    $grandTotalPo = 0;
    $grandTotalRcv = 0;

    foreach ($chunks as $pageIndex => $chunk) {
        $pageNumber = $pageIndex + 1;
        $isLastPage = ($pageNumber == $totalPages);
?>
<div class="page-container <?php echo !$isLastPage ? 'page-break' : ''; ?>">
    
    <div class="header-container">
        <div class="header-left">
            <div class="company-name"><?php echo $companyName; ?></div>
            <div class="doc-title">PURCHASE REPORT</div>
            <div class="company-addr"><?php echo $companyAddress; ?></div>
        </div>
        <div class="header-right">
            <div class="page-info">Page <?php echo $pageNumber; ?> of <?php echo $totalPages; ?></div>
        </div>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 8%;">Periode</td>
            <td style="width: 2%;">:</td>
            <td style="width: 45%; font-weight: normal;"><?php echo h(display_date($startDate)) . " s/d " . h(display_date($endDate)); ?></td>
            <td style="width: 12%;">Item Code</td>
            <td style="width: 2%;">:</td>
            <td style="width: 31%; font-weight: normal;"><?php echo $itemCode === '' ? 'ALL' : h($itemCode); ?></td>
        </tr>
        <tr>
            <td>Supplier</td>
            <td>:</td>
            <td style="font-weight: normal;"><?php echo $supCode === '' ? 'ALL' : h($supCode); ?></td>
            <td>Receive No</td>
            <td>:</td>
            <td style="font-weight: normal;"><?php echo $receiveNo === '' ? 'ALL' : h($receiveNo); ?></td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 2%;">No</th>
                <th style="width: 6%;">PO Date</th>
                <th style="width: 8%;">PO No.</th>
                <th style="width: 8%;">Item Code</th>
                <th style="width: 16%;">Item Name</th>
                <th style="width: 14%;">Supplier</th>
                <th style="width: 6%;">Qty PO</th>
                <th style="width: 6%;">Qty Rcv</th>
                <th style="width: 4%;">Unit</th>
                <th style="width: 6%;">PO Price</th>
                <th style="width: 6%;">Rcv Price</th>
                <th style="width: 3%;">Cur</th>
                <th style="width: 6%;">Due Date</th>
                <th style="width: 9%;">Receive No.</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $pageTotalPo = 0;
            $pageTotalRcv = 0;
            
            foreach ($chunk as $row) {
                $qtyPo = isset($row['QTY']) ? (float)$row['QTY'] : 0;
                $qtyRcv = isset($row['RCVD_QTY']) ? (float)$row['RCVD_QTY'] : 0;
                $pageTotalPo += $qtyPo;
                $pageTotalRcv += $qtyRcv;
                $grandTotalPo += $qtyPo;
                $grandTotalRcv += $qtyRcv;
                
                $supName = isset($row['SUP_COMP']) ? trim((string)$row['SUP_COMP']) : '';
            ?>
                <tr>
                    <td class="center"><?php echo $globalNo++; ?></td>
                    <td class="center"><?php echo h(display_date(isset($row['PO_DATE']) ? $row['PO_DATE'] : '')); ?></td>
                    <td><?php echo h(isset($row['PO_NUM']) ? $row['PO_NUM'] : ''); ?></td>
                    <td><?php echo h(isset($row['ITEM_CODE']) ? $row['ITEM_CODE'] : ''); ?></td>
                    <td class="wrap-text" style="max-width: 100px;"><?php echo h(isset($row['ITEM_NAME']) ? $row['ITEM_NAME'] : ''); ?></td>
                    <td class="wrap-text" style="max-width: 90px;"><?php echo h($supName); ?></td>
                    <td class="right"><?php echo h(display_number($qtyPo, 2)); ?></td>
                    <td class="right"><?php echo h(display_number($qtyRcv, 2)); ?></td>
                    <td class="center"><?php echo h(isset($row['POD_UNIT']) ? $row['POD_UNIT'] : ''); ?></td>
                    <td class="right"><?php echo h(display_number(isset($row['POD_PRICE']) ? $row['POD_PRICE'] : '', 2)); ?></td>
                    <td class="right"><?php echo h(display_number(isset($row['RCV_PRICE']) ? $row['RCV_PRICE'] : '', 2)); ?></td>
                    <td class="center"><?php echo h(isset($row['PO_CUR']) ? $row['PO_CUR'] : ''); ?></td>
                    <td class="center"><?php echo h(display_date(isset($row['POD_DUE']) ? $row['POD_DUE'] : '')); ?></td>
                    <td><?php echo h(isset($row['RCV_NOMOR']) ? $row['RCV_NOMOR'] : ''); ?></td>
                </tr>
            <?php } ?>
        </tbody>
        <tfoot>
            <?php if ($isLastPage) { ?>
                <tr>
                    <th colspan="6" class="right bold">GRAND TOTAL :</th>
                    <th class="right bold"><?php echo h(display_number($grandTotalPo, 2)); ?></th>
                    <th class="right bold"><?php echo h(display_number($grandTotalRcv, 2)); ?></th>
                    <th colspan="6"></th>
                </tr>
            <?php } ?>
        </tfoot>
    </table>

</div>
<?php } 
} elseif ($submitted && $errorMessage === '') { ?>
    <div class="no-print">
        <div style="max-width:1200px; margin:0 auto;" class="alert alert-warning text-center">
            <strong>Data kosong!</strong> Tidak ada transaksi yang sesuai dengan filter pencarian Anda.
        </div>
    </div>
<?php } ?>

<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>

<script type="text/javascript">
$(function () {
    
    // Autocomplete Item Code (Ajax)
    $("#item_code").autocomplete({
        minLength: 1, delay: 250,
        source: function (request, response) {
            $("#item_code").addClass("autocomplete-loading");
            $.ajax({
                url: "search_item.php", type: "POST", dataType: "json", data: { q: request.term },
                success: function (data) {
                    response($.map(data, function (item) {
                        return { label: (item.ITEM_CODE||"") + (item.ITEM_NAME ? " - "+item.ITEM_NAME : ""), value: item.ITEM_CODE||"" };
                    }));
                },
                complete: function () { $("#item_code").removeClass("autocomplete-loading"); }
            });
        }
    });

    // Autocomplete Receive No (Ajax)
    $("#receive_no").autocomplete({
        minLength: 1, delay: 250,
        source: function (request, response) {
            $("#receive_no").addClass("autocomplete-loading");
            $.ajax({
                url: "search_receive.php", type: "POST", dataType: "json", data: { q: request.term },
                success: function (data) {
                    response($.map(data, function (item) {
                        return { label: item.RCV_NOMOR||"", value: item.RCV_NOMOR||"" };
                    }));
                },
                complete: function () { $("#receive_no").removeClass("autocomplete-loading"); }
            });
        }
    });

    // Data Supplier dari PHP diproses menjadi JavaScript Array
    var availableSuppliers = <?php
        $supJsArr = array();
        foreach ($suppliers as $s) {
            $sCode = trim((string)$s['SUP_CODE']);
            $sName = trim((string)$s['SUP_COMP']);
            $label = $sCode . ($sName !== '' ? ' - ' . $sName : '');
            $supJsArr[] = array('label' => $label, 'value' => $sCode);
        }
        echo json_encode($supJsArr);
    ?>;

    // Autocomplete Supplier (Lokal Data)
    $("#sup_code").autocomplete({
        minLength: 1, delay: 100,
        source: function(request, response) {
            var term = $.ui.autocomplete.escapeRegex(request.term);
            var matcher = new RegExp(term, "i");
            var results = $.grep(availableSuppliers, function(item) {
                return matcher.test(item.label) || matcher.test(item.value);
            });
            response(results);
        }
    });

});
</script>
</body>
</html>
<?php sqlsrv_close($conn); ?>
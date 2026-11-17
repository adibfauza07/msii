<?php
// ============================================================
// PURCHASE REPORT BY ICL (RECEIVE NO)
// ============================================================
set_time_limit(120);

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
        $messages[] = trim('[' . (isset($error['SQLSTATE']) ? $error['SQLSTATE'] : '') . '] ' . (isset($error['code']) ? $error['code'] : '') . ' ' . (isset($error['message']) ? $error['message'] : ''));
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
    return number_format((float)$value, (int)$decimals, ',', '.');
}

if (!isset($conn) || $conn === false) {
    die('<div style="margin:20px;padding:15px;border:1px solid #b91c1c;background:#fff5f5;color:#b91c1c;font-family:Arial,sans-serif;"><strong>Koneksi database belum tersedia.</strong></div>');
}

// Menangkap parameter ICL (Receive No)
$receiveNo = isset($_GET['receive_no']) ? trim($_GET['receive_no']) : '';
$submitted = isset($_GET['show_report']) || isset($_GET['export']);
$isExportExcel = (isset($_GET['export']) && $_GET['export'] == 'excel');
$errorMessage = '';
$reportRows = array();

// =========================================================================
// Jika EXCEL ditekan, set header untuk download xls
// =========================================================================
if ($isExportExcel && $receiveNo !== '') {
    $filename = "Purchase_by_ICL_" . preg_replace('/[^A-Za-z0-9\-]/', '_', $receiveNo) . "_" . date('Ymd_Hi') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
}

/*
|--------------------------------------------------------------------------
| Jalankan query manual by ICL
|--------------------------------------------------------------------------
*/
if ($submitted && $receiveNo !== '') {
    $reportSql = "
        SELECT 
            S.SUP_CODE, S.SUP_COMP,
            IT.ITEM_CODE, IT.ITEM_NAME, IT.ITEM_UNIT AS POD_UNIT,
            P.PO_NUM, P.PO_DATE, P.PO_CUR, 
            PD.POD_PRICE, PD.POD_QTY, PD.POD_DUE,
            R.RCV_NO AS RCV_NOMOR, R.RCV_DATE, RD.RCVD_QTY
        FROM dbo.RECEIVE R
        INNER JOIN dbo.RECEIVE_DETAIL RD ON R.RCV_ID = RD.RCV_ID
        INNER JOIN dbo.PO P ON RD.PO_ID = P.PO_ID
        INNER JOIN dbo.PO_DETAIL PD ON P.PO_ID = PD.PO_ID AND RD.ITEM_ID = PD.ITEM_ID
        INNER JOIN dbo.ITEMS IT ON RD.ITEM_ID = IT.ITEM_ID
        LEFT JOIN dbo.SUPPLIER S ON P.SUP_ID = S.SUP_ID
        WHERE R.RCV_NO LIKE ?
        ORDER BY R.RCV_DATE DESC, R.RCV_NO DESC, P.PO_DATE ASC, P.PO_NUM ASC, IT.ITEM_CODE ASC
    ";

    $receiveNoParam = '%' . $receiveNo . '%';
    $reportStmt = @sqlsrv_query($conn, $reportSql, array($receiveNoParam));

    if ($reportStmt === false) {
        $errorMessage = 'Query gagal dijalankan: ' . sql_error_text();
    } else {
        while ($reportRow = sqlsrv_fetch_array($reportStmt, SQLSRV_FETCH_ASSOC)) {
            $reportRows[] = $reportRow;
        }
        sqlsrv_free_stmt($reportStmt);
        
        if (count($reportRows) === 0) {
            $errorMessage = "Data ICL tidak ditemukan atau tidak terkait dengan PO manapun.";
        }
    }
} elseif ($submitted && $receiveNo === '') {
    $errorMessage = 'Harap ketikkan Nomor ICL / Receive No untuk mencari data.';
}

// GROUPING DATA UNTUK TAMPILAN: ICL -> PO -> ITEMS
$groupedData = array();
foreach ($reportRows as $row) {
    $iclNo = trim((string)$row['RCV_NOMOR']);
    $poNum = trim((string)$row['PO_NUM']);

    if (!isset($groupedData[$iclNo])) {
        $groupedData[$iclNo] = array(
            'RCV_DATE' => $row['RCV_DATE'],
            'SUP_COMP' => trim((string)$row['SUP_COMP']),
            'POS'      => array()
        );
    }
    
    if (!isset($groupedData[$iclNo]['POS'][$poNum])) {
        $groupedData[$iclNo]['POS'][$poNum] = array(
            'PO_DATE' => $row['PO_DATE'],
            'PO_CUR'  => trim((string)$row['PO_CUR']),
            'ITEMS'   => array()
        );
    }
    
    $groupedData[$iclNo]['POS'][$poNum]['ITEMS'][] = array(
        'ITEM_CODE' => trim((string)$row['ITEM_CODE']),
        'ITEM_NAME' => trim((string)$row['ITEM_NAME']),
        'POD_PRICE' => (float)$row['POD_PRICE'],
        'PO_QTY'    => (float)$row['POD_QTY'],
        'RCVD_QTY'  => (float)$row['RCVD_QTY']
    );
}

// =========================================================================
// RENDER OUTPUT EXCEL (HTML TABLE murni tanpa class bawaan Bootstrap)
// =========================================================================
if ($isExportExcel): ?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px; }
        th, td { border: 1px solid #000000; padding: 4px 6px; vertical-align: middle; }
        th { background-color: #f2f2f2; font-weight: bold; text-align: center; }
        .num { text-align: right; }
        .bold { font-weight: bold; }
        .title { font-size: 14px; font-weight: bold; text-align: center; border: none; }
        .bg-icl { background-color: #d9edf7; font-weight: bold; }
        .bg-po { background-color: #f9f9f9; font-weight: bold; }
        .text-blue { color: #0000FF; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td colspan="8" class="title">PURCHASE REPORT BY ICL</td>
        </tr>
        <tr>
            <td colspan="8" style="text-align:center; border:none; font-size:11px;">Filtered by: <?php echo h($receiveNo); ?> | Print Date: <?php echo date('d-M-Y H:i'); ?></td>
        </tr>
        <tr><td colspan="8" style="border:none;"></td></tr>
        
        <tr>
            <th>ITEM CODE & NAME</th>
            <th>PRICE</th>
            <th>PO QTY</th>
            <th>ICL QTY</th>
            <th>BAL QTY</th>
            <th>PO AMOUNT</th>
            <th>ICL AMOUNT</th>
            <th>BAL AMOUNT</th>
        </tr>
        
        <?php foreach ($groupedData as $iclNo => $iclData): ?>
            <tr>
                <td colspan="8" class="bg-icl text-blue">
                    ICL #: <?php echo h($iclNo); ?> | Date: <?php echo display_date($iclData['RCV_DATE']); ?> | Supplier: <?php echo h($iclData['SUP_COMP']); ?>
                </td>
            </tr>

            <?php 
            $sumIclAmountAll = 0;
            foreach ($iclData['POS'] as $poNum => $poData): 
            ?>
                <tr>
                    <td colspan="8" class="bg-po">
                        PO #: <?php echo h($poNum); ?> | Date: <?php echo display_date($poData['PO_DATE']); ?> | Curr: <?php echo h($poData['PO_CUR']); ?>
                    </td>
                </tr>
                
                <?php 
                $sumPoAmount = 0; $sumIclAmountPo = 0; $sumBalAmount = 0;
                foreach ($poData['ITEMS'] as $item): 
                    $poQty = $item['PO_QTY'];
                    $iclQty = $item['RCVD_QTY'];
                    $balQty = $poQty - $iclQty;
                    $price = $item['POD_PRICE'];
                    
                    $poAmt = $poQty * $price;
                    $iclAmt = $iclQty * $price;
                    $balAmt = $balQty * $price;
                    
                    $sumPoAmount += $poAmt;
                    $sumIclAmountPo += $iclAmt;
                    $sumBalAmount += $balAmt;
                ?>
                    <tr>
                        <td style="padding-left: 15px;"><?php echo h($item['ITEM_CODE'] . " - " . $item['ITEM_NAME']); ?></td>
                        <td class="num"><?php echo $price; ?></td>
                        <td class="num"><?php echo $poQty; ?></td>
                        <td class="num text-blue"><?php echo $iclQty; ?></td>
                        <td class="num"><?php echo $balQty; ?></td>
                        <td class="num"><?php echo $poAmt; ?></td>
                        <td class="num text-blue"><?php echo $iclAmt; ?></td>
                        <td class="num"><?php echo $balAmt; ?></td>
                    </tr>
                <?php endforeach; ?>
                
                <?php $sumIclAmountAll += $sumIclAmountPo; ?>
                <tr>
                    <td colspan="5" class="num bold">Subtotal PO: <?php echo h($poNum); ?></td>
                    <td class="num bold"><?php echo $sumPoAmount; ?></td>
                    <td class="num bold text-blue"><?php echo $sumIclAmountPo; ?></td>
                    <td class="num bold"><?php echo $sumBalAmount; ?></td>
                </tr>
            <?php endforeach; ?>
            
            <tr>
                <td colspan="6" class="num bold text-blue" style="font-size: 12px;">TOTAL AMOUNT ICL (<?php echo h($iclNo); ?>)</td>
                <td class="num bold text-blue" style="font-size: 12px;"><?php echo $sumIclAmountAll; ?></td>
                <td></td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
<?php exit; endif; ?>

<!-- =========================================================================
// RENDER OUTPUT WEB / PRINT PDF
// ========================================================================= -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Purchase Report by ICL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    
    <style>
        body { background: #f8f9fa; font-family: Arial, sans-serif; font-size: 12px; }
        .filter-bar { background: #fff; padding: 15px; border-bottom: 2px solid #ddd; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .report-wrapper { padding: 20px; background: #fff; margin: 20px auto; width: 98%; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        
        .header-table { width: 100%; margin-bottom: 20px; }
        .header-title { font-size: 18px; font-weight: bold; text-align: center; line-height:1.2; }
        
        .report-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .report-table th, .report-table td { padding: 5px 6px; vertical-align: middle; }
        .report-table thead th { border-top: 2px solid #000; border-bottom: 2px solid #000; background: #fff; }
        
        .row-icl-head td { background-color: #d9edf7; font-size: 12px; border-top: 2px solid #000; padding-top: 8px !important; padding-bottom: 8px !important; }
        .row-po-head td { background-color: #f9f9f9; padding-left: 20px !important; border-bottom: 1px solid #ddd; padding-top: 6px !important; padding-bottom: 6px !important; }
        .row-item td { border-bottom: 1px dashed #eee; }
        
        .row-total td { border-top: 1px solid #aaa; font-weight: bold; padding-top: 6px !important; padding-bottom: 6px !important; }
        .row-total-sub td { font-weight: bold; padding-bottom: 25px !important; padding-top: 8px !important; }

        .ui-autocomplete {
            z-index: 1050;
            max-height: 300px;
            overflow-y: auto;
            overflow-x: hidden;
            font-size: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .ui-menu-item-wrapper { padding: 5px 10px; cursor: pointer; }

        /* ==============================================================
           PRINT CSS CONFIGURATION - MENCEGAH BERANTAKAN SAAT DICETAK 
        ============================================================== */
        @media print {
            @page { size: landscape; margin: 10mm; }
            body { background: #fff; padding: 0; margin: 0; font-size: 10px !important; color: #000; }
            .filter-bar, .no-print { display: none !important; }
            .report-wrapper { box-shadow: none; margin: 0; padding: 0; width: 100%; }
            
            /* Memaksa background color cetak */
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            
            /* Konfigurasi Header Berulang & Hindari Pemotongan Baris */
            .report-table { width: 100%; font-size: 10px !important; }
            .report-table thead { display: table-header-group !important; }
            .report-table tbody tr { page-break-inside: avoid !important; }
            
            .report-table th, .report-table td { padding: 4px !important; }
            .report-table thead th { border-top: 2px solid #000 !important; border-bottom: 2px solid #000 !important; box-shadow: none !important; }
            
            .row-icl-head td { border-top: 2px solid #000 !important; background-color: #d9edf7 !important; }
            .row-po-head td { background-color: #f9f9f9 !important; border-bottom: 1px solid #ccc !important; }
            .row-item td { border-bottom: 1px dashed #ccc !important; }
            .row-total td { border-top: 1px solid #000 !important; }
            
            .text-primary { color: #0000FF !important; }
            .text-success { color: #008000 !important; }
        }
    </style>
</head>
<body>

<div class="filter-bar no-print">
    <form method="GET" action="" class="d-flex align-items-center gap-3 m-0">
        <label class="fw-bold m-0"><i class="fas fa-search text-primary"></i> Cari By ICL:</label>
        
        <input type="text" name="receive_no" id="receive_no" class="form-control form-control-sm w-25" placeholder="Ketik RCV / ICL Number..." value="<?php echo h($receiveNo); ?>" autocomplete="off" required>
        
        <button type="submit" name="show_report" value="1" class="btn btn-primary btn-sm px-4 fw-bold">Tampilkan</button>
        <button type="button" class="btn btn-success btn-sm px-4 fw-bold" onclick="window.print()">Cetak</button>
        <button type="submit" name="export" value="excel" class="btn btn-info btn-sm px-4 fw-bold text-white">Export Excel</button>
    </form>
    <?php if ($errorMessage): ?>
        <div class="alert alert-danger mt-3 mb-0 py-2"><i class="fas fa-exclamation-circle"></i> <?php echo $errorMessage; ?></div>
    <?php endif; ?>
</div>

<?php if ($submitted && $errorMessage === '' && count($reportRows) > 0): ?>
<div class="report-wrapper">
    <table class="header-table">
        <tr>
            <td width="33%" class="text-start align-top">
                <strong>P.T. IMCTEKNO INDONESIA</strong><br>PPIC
            </td>
            <td width="34%" class="header-title align-top pt-2">
                PURCHASE REPORT BY ICL<br>
                <span style="font-size:11px; font-weight:normal; color:#555;">Filtered by: <?php echo h($receiveNo); ?></span>
            </td>
            <td width="33%" class="text-end align-top" style="font-size: 11px;">
                Print Date: <?php echo date('d-M-Y H:i:s'); ?>
            </td>
        </tr>
    </table>

    <div class="table-responsive">
        <table class="report-table">
            <thead>
                <tr>
                    <th class="text-start" style="width: 25%;">ITEM CODE & NAME</th>
                    <th class="text-end" style="width: 10%;">PRICE</th>
                    <th class="text-end" style="width: 10%;">PO QTY</th>
                    <th class="text-end" style="width: 10%;">ICL QTY</th>
                    <th class="text-end" style="width: 10%;">BAL QTY</th>
                    <th class="text-end" style="width: 12%;">PO AMOUNT</th>
                    <th class="text-end" style="width: 11%;">ICL AMOUNT</th>
                    <th class="text-end" style="width: 12%;">BAL AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groupedData as $iclNo => $iclData): ?>
                    
                    <tr class="row-icl-head">
                        <td colspan="8" class="text-primary fw-bold">
                            <i class="fas fa-box-open"></i> ICL #: <?php echo h($iclNo); ?> &nbsp;&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;&nbsp; Date: <?php echo display_date($iclData['RCV_DATE']); ?> &nbsp;&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;&nbsp; Supplier: <?php echo h($iclData['SUP_COMP']); ?>
                        </td>
                    </tr>

                    <?php 
                    $sumIclAmountAll = 0;
                    foreach ($iclData['POS'] as $poNum => $poData): 
                    ?>
                        <tr class="row-po-head">
                            <td colspan="8" class="fw-bold">
                                <i class="fas fa-shopping-cart text-success"></i> PO #: <?php echo h($poNum); ?> &nbsp;&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;&nbsp; Date: <?php echo display_date($poData['PO_DATE']); ?> &nbsp;&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;&nbsp; Curr: <?php echo h($poData['PO_CUR']); ?>
                            </td>
                        </tr>

                        <?php 
                        $sumPoAmount = 0; $sumIclAmountPo = 0; $sumBalAmount = 0;
                        foreach ($poData['ITEMS'] as $item): 
                            $poQty = $item['PO_QTY'];
                            $iclQty = $item['RCVD_QTY'];
                            $balQty = $poQty - $iclQty;
                            $price = $item['POD_PRICE'];
                            
                            $poAmt = $poQty * $price;
                            $iclAmt = $iclQty * $price;
                            $balAmt = $balQty * $price;
                            
                            $sumPoAmount += $poAmt;
                            $sumIclAmountPo += $iclAmt;
                            $sumBalAmount += $balAmt;
                        ?>
                            <tr class="row-item">
                                <td class="text-start" style="padding-left: 30px !important;"><?php echo h($item['ITEM_CODE'] . " - " . $item['ITEM_NAME']); ?></td>
                                <td class="text-end"><?php echo display_number($price, 2); ?></td>
                                <td class="text-end"><?php echo display_number($poQty, 2); ?></td>
                                <td class="text-end text-primary fw-bold"><?php echo display_number($iclQty, 2); ?></td>
                                <td class="text-end"><?php echo display_number($balQty, 2); ?></td>
                                <td class="text-end"><?php echo display_number($poAmt, 2); ?></td>
                                <td class="text-end text-primary fw-bold"><?php echo display_number($iclAmt, 2); ?></td>
                                <td class="text-end"><?php echo display_number($balAmt, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php $sumIclAmountAll += $sumIclAmountPo; ?>
                        <tr class="row-total" style="background:#fcfcfc;">
                            <td colspan="5" class="text-end pe-4 fst-italic">Subtotal PO: <?php echo h($poNum); ?></td>
                            <td class="text-end"><?php echo display_number($sumPoAmount, 2); ?></td>
                            <td class="text-end text-primary"><?php echo display_number($sumIclAmountPo, 2); ?></td>
                            <td class="text-end"><?php echo display_number($sumBalAmount, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <tr class="row-total-sub">
                        <td colspan="6" class="text-end pe-4 text-primary fs-6">TOTAL AMOUNT ICL (<?php echo h($iclNo); ?>)</td>
                        <td class="text-end text-primary fs-6 border-top border-primary"><?php echo display_number($sumIclAmountAll, 2); ?></td>
                        <td></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Javascript Dependencies -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

<script>
$(document).ready(function() {
    $("#receive_no").autocomplete({
        minLength: 2,
        delay: 250,
        source: function (request, response) {
            $.ajax({
                url: "search_receive.php",
                type: "GET",
                dataType: "json",
                data: { term: request.term },
                success: function (data) { response(data); },
                error: function() { response([]); }
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
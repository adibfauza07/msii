<?php
require_once __DIR__ . '/../config/database_p1.php';

// 1. Tangkap Parameter Filter
$passedItem = isset($_GET['item_id']) ? trim($_GET['item_id']) : '';
$startDate  = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$period     = isset($_GET['period']) ? (int)$_GET['period'] : 1;

$itemId = 0;
$itemCodeDisplay = "ALL ITEMS";
$groupedData = [];

// CEK APAKAH ADA AJAX REQUEST UNTUK PENCARIAN ITEM (SUPAYA BISA DI-SEARCH DI HALAMAN INI)
if (isset($_POST['ajax_search_item'])) {
    while (ob_get_level()) ob_end_clean(); 
    header('Content-Type: application/json');
    
    $q = trim($_POST['q']);
    $res = [];
    
    $sqlAc = "SELECT TOP 40 ITEM_ID, ITEM_CODE, ITEM_NAME, ITEM_UNIT FROM ITEMS WHERE ITEM_CODE LIKE ? OR ITEM_NAME LIKE ? ORDER BY ITEM_CODE ASC";
    $stmtAc = sqlsrv_query($conn, $sqlAc, array("%$q%", "%$q%"));
    
    if ($stmtAc !== false) {
        while ($rowAc = sqlsrv_fetch_array($stmtAc, SQLSRV_FETCH_ASSOC)) {
            $res[] = [
                'ITEM_ID' => $rowAc['ITEM_ID'],
                'ITEM_CODE' => trim((string)$rowAc['ITEM_CODE']),
                'ITEM_NAME' => trim((string)$rowAc['ITEM_NAME']),
                'ITEM_UNIT' => trim((string)$rowAc['ITEM_UNIT'])
            ];
        }
    }
    echo json_encode($res);
    exit;
}

// 2. JIKA ITEM TELAH DIPILIH DAN DISUBMIT, JALANKAN STORED PROCEDURE
if ($passedItem !== '' && $passedItem !== '0') {
    if ($passedItem !== '0') {
        $qFind = sqlsrv_query($conn, "SELECT TOP 1 ITEM_ID, ITEM_CODE FROM ITEMS WHERE ITEM_CODE = ?", array($passedItem));
        if ($qFind && $rFind = sqlsrv_fetch_array($qFind, SQLSRV_FETCH_ASSOC)) {
            $itemId = $rFind['ITEM_ID'];
            $itemCodeDisplay = $rFind['ITEM_CODE'];
        } else {
            if (is_numeric($passedItem)) {
                $itemId = (int)$passedItem;
                $qName = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITEM_ID = ?", array($itemId));
                if ($qName && $rName = sqlsrv_fetch_array($qName, SQLSRV_FETCH_ASSOC)) {
                    $itemCodeDisplay = $rName['ITEM_CODE'];
                }
            } else {
                $itemId = -999; 
                $itemCodeDisplay = "ITEM NOT FOUND";
            }
        }
    }

    $startDateFormatted = date('Ymd', strtotime($startDate));
    sqlsrv_query($conn, "SET NOCOUNT ON");

    $sql = "EXEC sp_StockAnalysis2 @STARTDATE = ?, @PERIOD = ?, @ITEM_ID = ?";
    $params = array($startDateFormatted, $period, $itemId);

    set_time_limit(0); 
    $options = array("QueryTimeout" => 300);
    $stmt = sqlsrv_query($conn, $sql, $params, $options);

    if ($stmt !== false) {
        do {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                if (isset($row['ITEM_CODE'])) {
                    $itemKey = $row['ITEM_CODE'] . "||" . (isset($row['ITEM_NAME']) ? $row['ITEM_NAME'] : '');
                    
                    $locGroup = isset($row['LOC_GROUP']) ? trim($row['LOC_GROUP']) : "";
                    $locName = isset($row['LOC_NAME']) ? trim($row['LOC_NAME']) : "UNASSIGNED";
                    $locKey = trim($locGroup . " " . $locName); 
                    
                    if (!isset($groupedData[$itemKey])) {
                        $groupedData[$itemKey] = [];
                    }
                    if (!isset($groupedData[$itemKey][$locKey])) {
                        $groupedData[$itemKey][$locKey] = [];
                    }
                    
                    $groupedData[$itemKey][$locKey][] = $row;
                }
            }
        } while (sqlsrv_next_result($stmt));
    }
}

$startMonthStr = date('F - Y', strtotime($startDate));
$bulanEn = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
$bulanId = array('Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
$startMonthStr = str_replace($bulanEn, $bulanId, $startMonthStr);

function formatCR($val) {
    if (round($val, 2) == 0) return '-';
    return number_format($val, 2, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Stock Analysis Report</title>
    <!-- CSS & Select2 Assets untuk Filter Bar -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <style>
        body { font-family: "Arial", Helvetica, sans-serif; font-size: 12px; margin: 0; background: #e0e0e0; }
        .page-container { background: #fff; width: 210mm; min-height: 297mm; margin: 20px auto; padding: 25px 40px; box-sizing: border-box; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        
        .header-table { width: 100%; margin-bottom: 20px; }
        .header-table td { vertical-align: top; }
        .company-name { font-size: 15px; font-weight: normal; }
        .title-center { text-align: center; }
        .title-center h2 { margin: 0 0 2px 0; font-size: 24px; font-weight: normal; letter-spacing: 1px; }
        .info-desc { font-size: 12px; margin-bottom: 1px; }
        .page-info { text-align: right; font-size: 11px; }
        
        .data-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .data-table td, .data-table th { padding: 2px 2px; vertical-align: top; border: none; word-wrap: break-word; }

        .item-header th, .item-header td { 
            border-top: 1px solid #000; 
            border-bottom: 1px solid #000; 
            font-weight: bold; 
            font-size: 13px; 
            padding: 5px 2px;
        }
        .item-header .code { font-size: 14px; }
        
        .loc-header td { font-weight: bold; font-style: italic; font-size: 15px; padding-top: 15px; padding-bottom: 5px; text-transform: uppercase; }
        
        .beg-balance td { padding-top: 3px; padding-bottom: 3px; font-size: 12px; }
        .beg-balance .lbl-col { text-align: left; padding-left: 10px; white-space: nowrap; }
        .beg-balance .lbl-text { color: blue; font-style: italic; margin-right: 20px; }
        .beg-balance .lbl-date { color: blue; font-style: italic; margin-right: 25px; }
        .beg-balance .lbl-bal { color: black; font-style: normal; }
        
        .row-data td { font-size: 12px; padding: 2px 2px; }
        .row-data .desc { text-align: left; padding-left: 10px; white-space: nowrap; }
        
        .num-cell { text-align: right; }
        .in-text { color: green; }
        .out-text { color: red; }
        .bal-text { color: black; }
        .bal-total { color: blue; }
        
        .grand-total td { font-weight: bold; padding-top: 8px; padding-bottom: 25px; font-size: 13px; }
        .grand-total .lbl { text-align: right; padding-right: 15px; white-space: nowrap; }
        
        .no-print { text-align: center; margin-bottom: 20px; padding: 15px; background: #fff; border-bottom: 1px solid #ccc; }
        .btn { padding: 8px 15px; cursor: pointer; border: 1px solid #ccc; background: #f8f9fa; font-weight: bold; margin: 0 5px; }
        
        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .no-print { display: none; }
            .page-container { width: 100%; margin: 0; padding: 0; box-shadow: none; }
            @page { size: portrait; margin: 15mm 10mm; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <!-- FORM FILTER INTERAKTIF DI DALAM HALAMAN PRINT -->
        <form method="GET" action="" class="row g-2 align-items-center justify-content-center mb-3">
            <div class="col-auto">
                <label class="fw-bold">Pilih Item:</label>
            </div>
            <div class="col-auto" style="width: 300px;">
                <select id="filter_item_id" name="item_id" class="form-select form-select-sm" required>
                    <?php if (!empty($passedItem)): ?>
                        <option value="<?php echo htmlspecialchars($passedItem); ?>" selected><?php echo htmlspecialchars($itemCodeDisplay); ?></option>
                    <?php else: ?>
                        <option value="" selected disabled>-- Pilih / Cari Item --</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="fw-bold">Start Date:</label>
            </div>
            <div class="col-auto">
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($startDate); ?>">
            </div>
            <div class="col-auto">
                <label class="fw-bold">Periode:</label>
            </div>
            <div class="col-auto" style="width: 80px;">
                <input type="number" name="period" class="form-control form-control-sm" value="<?php echo $period; ?>" min="1">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">Tampilkan Analisis</button>
            </div>
        </form>

        <div>
            <button class="btn" onclick="window.close()">Tutup</button>
            <button class="btn" onclick="window.print()">Print Report</button>
            <a href="export_stock_analysis.php?item_id=<?php echo htmlspecialchars($passedItem); ?>&start_date=<?php echo htmlspecialchars($startDate); ?>&period=<?php echo $period; ?>" class="btn" style="text-decoration:none; color:black;">Export to Excel</a>
        </div>
    </div>

    <div class="page-container">
        <!-- HEADER -->
        <table class="header-table">
            <tr>
                <td style="width: 33%;" class="company-name">
                    P.T. IMC TEKNO INDONESIA
                </td>
                <td style="width: 34%;" class="title-center">
                    <h2>STOCK ANALYSIS</h2>
                    <div class="info-desc">Starting Month : <?php echo $startMonthStr; ?></div>
                    <div class="info-desc">Period : <?php echo $period; ?> Month(s)</div>
                </td>
                <td style="width: 33%;" class="page-info">
                    <div>Page 1 of 1</div>
                    <div>Print Date : <?php echo date('m/d/Y'); ?></div>
                    <div><?php echo date('g:i:sA'); ?></div>
                </td>
            </tr>
        </table>

        <!-- KONTEN TABEL -->
        <?php if ($passedItem === '' || $passedItem === '0'): ?>
            <div style="text-align: center; padding: 60px; font-weight: bold; font-size: 14px; color: #666;">
                Silakan pilih item terlebih dahulu melalui menu filter di atas, lalu klik <b>Tampilkan Analisis</b>.
            </div>
        <?php elseif (empty($groupedData)): ?>
            <div style="text-align: center; padding: 40px; font-weight: bold; font-size: 13px; color: red;">
                Tidak ada riwayat transaksi ditemukan untuk item tersebut pada periode yang dipilih.
            </div>
        <?php else: ?>
            <table class="data-table">
                <colgroup>
                    <col style="width: 25%;"> 
                    <col style="width: 42%;"> 
                    <col style="width: 11%;">  
                    <col style="width: 11%;">  
                    <col style="width: 11%;">  
                </colgroup>
                
                <?php foreach ($groupedData as $itemKey => $locations): 
                    list($itemCode, $itemName) = explode("||", $itemKey);
                ?>
                    <tr>
                        <td colspan="5" style="font-weight: bold; font-size: 12px; padding-bottom: 2px;">[]</td>
                    </tr>
                    
                    <tr class="item-header">
                        <td colspan="2" class="code"><?php echo htmlspecialchars($itemCode); ?> &nbsp;&nbsp; <?php echo htmlspecialchars($itemName); ?></td>
                        <th class="num-cell in-text">IN</th>
                        <th class="num-cell out-text">OUT</th>
                        <th class="num-cell">BAL</th>
                    </tr>
                    
                    <?php foreach ($locations as $locKey => $rows): 
                        $runningBalance = isset($rows[0]['BAL_QTY']) ? (float)$rows[0]['BAL_QTY'] : 0;
                        $sumIn = 0;
                        $sumOut = 0;
                        
                        echo "<tr class='loc-header'><td colspan='5'>".htmlspecialchars($locKey)."</td></tr>";
                        
                        foreach ($rows as $index => $r):
                            $tDateObj = $r['TRAN_DATE'];
                            $tDateStr = ($tDateObj instanceof DateTime) ? $tDateObj->format('Y-m-d') : $tDateObj;
                            
                            if ($index == 0) {
                                $lblDate = date('j-M-y', strtotime($startDate));
                                echo "<tr class='beg-balance'>
                                        <td></td>
                                        <td class='lbl-col'>
                                            <span class='lbl-text'>BEGINING BALANCE :</span>
                                            <span class='lbl-date'>{$lblDate}</span>
                                            <span class='lbl-bal'>".formatCR($runningBalance)."</span>
                                        </td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                      </tr>";
                            }
                            
                            $inQty  = isset($r['TIN']) ? (float)$r['TIN'] : 0;
                            $outQty = isset($r['TOUT']) ? (float)$r['TOUT'] : 0;
                            
                            $runningBalance += ($inQty - $outQty); 
                            $sumIn += $inQty;
                            $sumOut += $outQty;
                            
                            $docNo    = isset($r['TRAN_DOC']) ? $r['TRAN_DOC'] : '';
                            $trtyCode = isset($r['TRTY_CODE']) ? $r['TRTY_CODE'] : '';
                            $trtyDesc = isset($r['TRTY_DESC']) ? $r['TRTY_DESC'] : '';
                            
                            $displayDate = date('d-M-y', strtotime($tDateStr));
                            $displayDesc = "{$displayDate} [{$trtyCode}] {$trtyDesc}";
                        ?>
                            <tr class="row-data">
                                <td><?php echo htmlspecialchars($docNo); ?></td>
                                <td class="desc"><?php echo htmlspecialchars($displayDesc); ?></td>
                                <td class="num-cell in-text"><?php echo formatCR($inQty); ?></td>
                                <td class="num-cell out-text"><?php echo formatCR($outQty); ?></td>
                                <td class="num-cell bal-text"><?php echo formatCR($runningBalance); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <tr class="grand-total">
                            <td colspan="2" class="lbl">GRAND TOTAL :</td>
                            <td class="num-cell in-text"><?php echo formatCR($sumIn); ?></td>
                            <td class="num-cell out-text"><?php echo formatCR($sumOut); ?></td>
                            <td class="num-cell bal-total"><?php echo formatCR($runningBalance); ?></td>
                        </tr>
                        
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <!-- SCRIPT SELECT2 PENCARIAN ITEM DI HALAMAN PRINT -->
    <script>
    $(document).ready(function() {
        $('#filter_item_id').select2({
            theme: 'bootstrap-5',
            placeholder: 'Ketik Kode / Nama Item...',
            allowClear: true,
            minimumInputLength: 1,
            ajax: {
                url: window.location.href,
                type: 'POST',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        ajax_search_item: 1,
                        q: params.term
                    };
                },
                processResults: function (data) {
                    var mapped = $.map(data, function(obj) {
                        return {
                            id: obj.ITEM_CODE,
                            text: obj.ITEM_CODE + ' - ' + obj.ITEM_NAME
                        };
                    });
                    return { results: mapped };
                },
                cache: true
            }
        });
    });
    </script>
</body>
</html>
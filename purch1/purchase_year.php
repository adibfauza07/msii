<?php
// ============================================
// KONFIGURASI DATABASE
// ============================================
require_once __DIR__ . "/../config/global.php";

// ============================================
// CEK APAKAH PARAMETER SUDAH DIISI
// ============================================
 $hasParams = isset($_GET['start_date']) && isset($_GET['end_date']);
 $isExport  = isset($_GET['export']) && $_GET['export'] === 'excel';

 $dataRows      = array();
 $grouped       = array();
 $grandPoQty    = 0;
 $grandPoAmount = 0;
 $grandRcvQty   = 0;
 $grandRcvAmount= 0;
 $totalRows     = 0;
 $totalGroups   = 0;
 $startDate     = '';
 $endDate       = '';
 $itemCode      = '';
 $supCode       = '';
 $currentYear   = date('Y');

if ($hasParams) {
    $startDate = $_GET['start_date'];
    $endDate   = $_GET['end_date'];
    $itemCode  = isset($_GET['item_code']) ? trim($_GET['item_code']) : '';
    $supCode   = isset($_GET['sup_code'])  ? trim($_GET['sup_code'])  : '';

    $codeParam = ($itemCode !== '') ? "%$itemCode%" : "%";
    $supParam  = ($supCode  !== '') ? "%$supCode%"  : "%";

    $sql = "{CALL RPT_PURCHASE_YEAR(?, ?, ?, ?)}";
    $params = array(
        array($startDate, SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DATETIME),
        array($endDate,   SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DATETIME),
        array($codeParam, SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_VARCHAR(20)),
        array($supParam,  SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_VARCHAR(20))
    );

    $stmt = sqlsrv_query($conn, $sql, $params);

    if (!$stmt) {
        $errorMsg = "Query gagal: " . print_r(sqlsrv_errors(), true);
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $dataRows[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    sqlsrv_close($conn);

    // ============================================
    // GROUPING: Supplier -> Item -> PO -> RCV
    // ============================================
    foreach ($dataRows as $row) {
        $supKey  = trim($row['SUP_CODE']);
        $itemKey = trim($row['ITEM_CODE']);
        $poKey   = trim($row['PO_NUM']);

        // 1. Inisialisasi Supplier
        if (!isset($grouped[$supKey])) {
            $grouped[$supKey] = array(
                'SUP_CODE' => trim($row['SUP_CODE']),
                'SUP_COMP' => trim($row['SUP_COMP']),
                'items'    => array()
            );
        }

        // 2. Inisialisasi Item
        if (!isset($grouped[$supKey]['items'][$itemKey])) {
            $grouped[$supKey]['items'][$itemKey] = array(
                'ITEM_CODE' => trim($row['ITEM_CODE']),
                'ITEM_NAME' => trim($row['ITEM_NAME']),
                'pos'       => array()
            );
        }

        // 3. Inisialisasi PO
        if (!isset($grouped[$supKey]['items'][$itemKey]['pos'][$poKey])) {
            $poQty   = floatval($row['QTY']);
            $poPrice = floatval($row['POD_PRICE']);
            
            $grouped[$supKey]['items'][$itemKey]['pos'][$poKey] = array(
                'PO_NUM'        => trim($row['PO_NUM']),
                'PO_DATE'       => $row['PO_DATE'],
                'POD_PRICE'     => $poPrice,
                'PO_CUR'        => trim($row['PO_CUR']),
                'QTY'           => $poQty,
                'POD_UNIT'      => trim($row['POD_UNIT']),
                'AMOUNT'        => $poQty * $poPrice,
                'TOTAL_REC_QTY' => 0,
                'rcvs'          => array()
            );

            $grandPoQty    += $poQty;
            $grandPoAmount += ($poQty * $poPrice);
        }

        // 4. Masukkan baris RCV dan gabungkan jika RCV_NOMOR sama (GROUP BY RCV_NOMOR)
        $rcvNo = trim($row['RCV_NOMOR']);
        if ($rcvNo !== '') {
            $rcvQty   = floatval($row['RCVD_QTY']);
            $rcvPrice = floatval($row['RCV_PRICE']);

            if (!isset($grouped[$supKey]['items'][$itemKey]['pos'][$poKey]['rcvs'][$rcvNo])) {
                $grouped[$supKey]['items'][$itemKey]['pos'][$poKey]['rcvs'][$rcvNo] = array(
                    'RCV_NOMOR' => $rcvNo,
                    'RCV_DATE'  => $row['RCV_DATE'],
                    'RCVD_QTY'  => 0,
                    'RCV_PRICE' => $rcvPrice
                );
            }

            $grouped[$supKey]['items'][$itemKey]['pos'][$poKey]['rcvs'][$rcvNo]['RCVD_QTY'] += $rcvQty;
            $grouped[$supKey]['items'][$itemKey]['pos'][$poKey]['TOTAL_REC_QTY'] += $rcvQty;
            $grandRcvQty    += $rcvQty;
            $grandRcvAmount += ($rcvQty * $rcvPrice);
        }
    }

    $totalRows   = count($dataRows);
    $totalGroups = count($grouped);
}

// FUNGSI FORMATTING
function formatDate($date) {
    if (!$date) return '';
    if ($date instanceof DateTime) return $date->format('m/d/Y');
    $ts = strtotime($date);
    if ($ts === false) return '';
    return date('m/d/Y', $ts);
}
function formatDatePO($date) {
    if (!$date) return '';
    if ($date instanceof DateTime) return $date->format('d-M-y');
    $ts = strtotime($date);
    if ($ts === false) return '';
    return date('d-M-y', $ts);
}
function formatQty($num) {
    if ($num == 0) return '0';
    return number_format($num, 0, '.', ','); 
}
function formatPOQty($num) {
    if ($num == 0) return '0.00';
    return number_format($num, 2, '.', ','); 
}
function formatMoney($num) {
    if ($num == 0) return '0.00';
    return number_format($num, 2, '.', ',');
}

// PROSES EXPORT EXCEL
if ($isExport) {
    $filename = "Purchase_Year_Report_" . date('Ymd') . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
}
?>

<?php if (!$isExport): ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Year - PT.IMC TEKNO INDONESIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bs-body-font-family: 'Plus Jakarta Sans', sans-serif;
            --accent: #2563eb;
            --accent-light: #dbeafe;
            --accent-dark: #1d4ed8;
            --bg-page: #f0f2f5;
            --bg-card: #ffffff;
            --border-color: #e2e8f0;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
        }
        body {
            background-color: var(--bg-page);
            color: var(--text-primary);
            font-family: 'Times New Roman', Times, serif;
            font-size: 13px;
        }
        .param-overlay {
            position: fixed; inset: 0; background: rgba(15,23,42,0.5); backdrop-filter: blur(4px); z-index: 9999;
            display: flex; align-items: center; justify-content: center;
        }
        .param-dialog { background: var(--bg-card); border-radius: 16px; width: 560px; max-width: 95vw; box-shadow: 0 25px 60px -12px rgba(0,0,0,0.25); overflow: hidden; }
        .param-dialog-header { padding: 20px 28px 16px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 12px; }
        .param-dialog-header .icon-wrap { width: 36px; height: 36px; border-radius: 10px; background: var(--accent-light); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 16px; }
        .param-dialog-header .title { font-size: 15px; font-weight: 700; font-family: Arial, sans-serif; }
        .param-dialog-body { padding: 24px 28px; font-family: Arial, sans-serif; }
        .param-tabs { display: flex; border-bottom: 1px solid var(--border-color); margin-bottom: 20px; }
        .param-tab { padding: 8px 16px; font-size: 12px; font-weight: 600; color: var(--accent); border-bottom: 2px solid var(--accent); background: none; border-top: none; border-left: none; border-right: none; }
        .radio-group { display: flex; gap: 20px; margin-bottom: 20px; padding: 12px 16px; background: #f8fafc; border-radius: 10px; border: 1px solid var(--border-color); }
        .filter-section-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.12em; color: var(--text-muted); margin-bottom: 14px; padding-bottom: 8px; border-bottom: 1px dashed var(--border-color); }
        .param-field { margin-bottom: 16px; }
        .param-field label { display: block; font-size: 11px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; }
        .param-field .form-control { font-size: 13px; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); }
        .date-range-row { display: flex; align-items: center; gap: 10px; }
        .param-field .ac-wrap { position: relative; }
        .param-field .ac-list { position: absolute; top: 100%; left: 0; right: 0; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; margin-top: 4px; max-height: 200px; overflow-y: auto; z-index: 10001; display: none; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15); }
        .param-field .ac-list.show { display: block; }
        .param-field .ac-item { padding: 8px 12px; cursor: pointer; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f1f5f9; }
        .param-field .ac-item:hover { background: var(--accent-light); }
        .param-field .ac-item .code { font-family: monospace; font-size: 11px; font-weight: 600; color: var(--accent-dark); min-width: 90px; }
        .param-field .ac-item .name { font-size: 11px; color: var(--text-primary); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .param-dialog-footer { padding: 16px 28px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; }
        
        .report-container { background: var(--bg-card); border: 1px solid var(--border-color); padding: 40px; margin: 20px auto; max-width: 1400px; }
        .report-header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 10px; }
        .report-title { font-size: 20px; font-weight: bold; font-family: Arial, sans-serif; }
        .report-params { font-size: 14px; display: flex; justify-content: space-between; font-weight: bold; font-family: Arial, sans-serif;}
        
        .report-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .report-table thead th { border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 8px 4px; font-weight: bold; text-transform: uppercase; }
        .report-table tbody td { padding: 4px; vertical-align: top; }
        
        @media print {
            body { background: #fff !important; }
            .no-print { display: none !important; }
            .report-container { border: none; padding: 0; margin: 0; }
        }
    </style>
</head>
<body>

    <!-- MODAL PARAMETER UTAMA -->
    <?php if (!$hasParams): ?>
    <div class="param-overlay" id="paramOverlay">
        <div class="param-dialog">
            <div class="param-dialog-header">
                <div class="icon-wrap"><i class="bi bi-printer"></i></div>
                <div>
                    <div class="title">Purchase Year Report</div>
                    <div class="subtitle">Tentang atau isi kriteria pencetakan dokumen dibawah ini</div>
                </div>
            </div>
            <div class="param-dialog-body">
                <div class="param-tabs">
                    <button type="button" class="param-tab">Layer</button>
                </div>
                <div class="radio-group">
                    <label><input type="radio" name="destination" value="screen" checked> Tampil di Layar</label>
                    <label><input type="radio" name="destination" value="printer"> Printer</label>
                </div>
                <div class="filter-section-label">Default Filter</div>
                <form id="paramForm" method="GET" action="">
                    <div class="param-field">
                        <label>Dari Tanggal</label>
                        <div class="date-range-row">
                            <input type="date" name="start_date" value="<?= $currentYear ?>-06-01" class="form-control" style="flex:1;" required>
                            <span class="mx-2">s/d</span>
                            <input type="date" name="end_date" value="<?= date('Y-m-d') ?>" class="form-control" style="flex:1;" required>
                        </div>
                    </div>
                    <div class="param-field">
                        <label>CODE (Item)</label>
                        <div class="ac-wrap" id="acWrapItem">
                            <input type="text" id="modalItemInput" placeholder="Kosongkan untuk semua..." autocomplete="off" class="form-control">
                            <input type="hidden" name="item_code" id="modalItemHidden" value="">
                            <div class="ac-list" id="modalItemDropdown"></div>
                        </div>
                    </div>
                    <div class="param-field">
                        <label>SUPPLIER</label>
                        <div class="ac-wrap" id="acWrapSup">
                            <input type="text" id="modalSupInput" placeholder="Kosongkan untuk semua..." autocomplete="off" class="form-control">
                            <input type="hidden" name="sup_code" id="modalSupHidden" value="">
                            <div class="ac-list" id="modalSupDropdown"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="param-dialog-footer">
                <button type="button" class="btn btn-light btn-sm" onclick="window.location.reload();">Close</button>
                <button type="submit" form="paramForm" class="btn btn-primary btn-sm"><i class="bi bi-printer me-1"></i> Print</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- NAVBAR ATAS -->
    <?php if ($hasParams): ?>
    <nav class="navbar navbar-light bg-white border-bottom fixed-top no-print">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1" style="font-family: Arial, sans-serif; font-size: 14px; font-weight: bold;">PURCHASE YEAR</span>
            <div>
                <button onclick="window.location.href='?'" class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> Parameter</button>
                <a href="?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&item_code=<?= urlencode($itemCode) ?>&sup_code=<?= urlencode($supCode) ?>&export=excel" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="bi bi-printer"></i> Print</button>
            </div>
        </div>
    </nav>
    <div style="margin-top: 70px;"></div>
    <?php endif; ?>

<?php endif; // END OF: if (!$isExport) ?>

<?php if ($hasParams): ?>
    <!-- AREA DOKUMEN CETAK / EXCEL -->
    <?php if (!$isExport): ?><main class="container-fluid"><div class="report-container"><?php endif; ?>
        
        <?php if ($isExport): ?>
            <table border="0">
                <tr><td colspan="10" style="font-size: 18px; font-weight: bold; text-align: center; font-family:Arial;">PURCHASE YEAR</td></tr>
                <tr><td colspan="10" style="text-align: center; font-family:Arial;">From : <?= formatDatePO($startDate) ?> &nbsp;&nbsp;&nbsp; To : <?= formatDatePO($endDate) ?></td></tr>
                <tr><td colspan="10"></td></tr>
            </table>
        <?php else: ?>
            <div class="report-header">
                <div class="report-title">PURCHASE YEAR</div>
                <div class="report-params mt-3">
                    <div>From : &nbsp;&nbsp;&nbsp;<?= formatDatePO($startDate) ?></div>
                    <div>To : &nbsp;&nbsp;&nbsp;<?= formatDatePO($endDate) ?></div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="report-table-wrap">
            <?php if ($totalRows > 0): ?>
            <table class="report-table" <?= $isExport ? 'border="1"' : '' ?>>
                <thead>
                    <tr>
                        <th style="text-align:left; width: 25%; <?= $isExport ? 'background-color:#e2e8f0;' : '' ?>">Dokumen / Item / Supplier</th>
                        <th style="text-align:center; <?= $isExport ? 'background-color:#e2e8f0;' : '' ?>">DATE</th>
                        <th style="text-align:right; <?= $isExport ? 'background-color:#e2e8f0;' : '' ?>">PRICE</th>
                        <th style="text-align:left; padding-left: 10px; <?= $isExport ? 'background-color:#e2e8f0;' : '' ?>">CURR</th>
                        <th style="text-align:right; <?= $isExport ? 'background-color:#e2e8f0;' : '' ?>">PO QTY</th>
                        <th style="text-align:right; <?= $isExport ? 'background-color:#e2e8f0;' : '' ?>">REC QTY</th>
                        <th style="text-align:right; <?= $isExport ? 'background-color:#e2e8f0;' : '' ?>">REC PRICE</th>
                        <th style="text-align:right; <?= $isExport ? 'background-color:#e2e8f0;' : '' ?>">OUTSTANDING</th>
                        <th style="text-align:center; <?= $isExport ? 'background-color:#e2e8f0;' : '' ?>">UNIT</th>
                        <th style="text-align:right; <?= $isExport ? 'background-color:#e2e8f0;' : '' ?>">AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grouped as $supKey => $sup): ?>
                        <!-- SUPPLIER LEVEL -->
                        <tr>
                            <td colspan="10" style="font-weight: bold;"><?= htmlspecialchars($sup['SUP_CODE'] . ' ' . $sup['SUP_COMP']) ?></td>
                        </tr>
                        <?php foreach ($sup['items'] as $itemKey => $item): ?>
                            <!-- ITEM LEVEL -->
                            <tr>
                                <td colspan="10" style="font-weight: bold; padding-left: 10px;"><?= htmlspecialchars($item['ITEM_CODE'] . ' ' . $item['ITEM_NAME']) ?></td>
                            </tr>
                            <?php foreach ($item['pos'] as $poKey => $po): 
                                $outstanding = $po['QTY'] - $po['TOTAL_REC_QTY'];
                            ?>
                                <!-- PO LEVEL -->
                                <tr>
                                    <td style="font-weight: bold;"><?= htmlspecialchars($po['PO_NUM']) ?></td>
                                    <td style="text-align:center; font-weight: bold;"><?= formatDatePO($po['PO_DATE']) ?></td>
                                    <td style="text-align:right;"><?= formatMoney($po['POD_PRICE']) ?></td>
                                    <td style="padding-left: 10px;"><?= htmlspecialchars($po['PO_CUR']) ?></td>
                                    <td style="color: blue; font-weight: bold; text-align:right;"><?= formatPOQty($po['QTY']) ?></td>
                                    <td style="color: red; font-weight: bold; text-align:right;"><?= formatQty($po['TOTAL_REC_QTY']) ?></td>
                                    <td></td>
                                    <td style="color: green; font-weight: bold; text-align:right;"><?= formatQty($outstanding) ?></td>
                                    <td style="text-align:center;"><?= htmlspecialchars($po['POD_UNIT']) ?></td>
                                    <td style="text-align:right;"><?= formatMoney($po['AMOUNT']) ?></td>
                                </tr>
                                <!-- RECEIVE DETAILS LEVEL (GROUPED BY RCV_NOMOR) -->
                                <?php foreach ($po['rcvs'] as $rcv): ?>
                                    <tr>
                                        <td style="color: blue; font-weight: bold; padding-left: 10px;"><?= htmlspecialchars($rcv['RCV_NOMOR']) ?></td>
                                        <td style="text-align:center; padding-left: 20px;"><?= formatDate($rcv['RCV_DATE']) ?></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td style="text-align:right; font-weight: bold;"><?= formatQty($rcv['RCVD_QTY']) ?></td>
                                        <td style="text-align:right;"><?= formatMoney($rcv['RCV_PRICE']) ?></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <?php if (!$isExport): ?><div class="text-center my-5 text-muted">Tidak ada data untuk periode ini.</div><?php endif; ?>
            <?php endif; ?>
        </div>
    <?php if (!$isExport): ?></div></main><?php endif; ?>
<?php endif; ?>

<?php if (!$isExport): ?>
    <!-- SCRIPT AUTOCOMPLETE BAWAN ANDA -->
    <script>
        function createAutocomplete(config) {
            var input    = document.getElementById(config.inputId);
            var hidden   = document.getElementById(config.hiddenId);
            var dropdown = document.getElementById(config.dropdownId);
            var wrapId   = config.wrapId;
            if (!input || !hidden || !dropdown) return;

            var acTimeout = null;
            var activeIdx = -1;

            input.addEventListener('input', function() {
                var q = this.value.trim();
                hidden.value = '';
                activeIdx = -1;
                if (q.length < 1) { acHide(); return; }
                clearTimeout(acTimeout);
                acTimeout = setTimeout(function() { acSearch(q); }, 300);
            });

            input.addEventListener('focus', function() {
                var q = this.value.trim();
                if (q.length >= 1) acSearch(q);
            });

            input.addEventListener('keydown', function(e) {
                var items = dropdown.querySelectorAll('.ac-item');
                if (!items.length) return;
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeIdx = Math.min(activeIdx + 1, items.length - 1);
                    acHighlight(items);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeIdx = Math.max(activeIdx - 1, 0);
                    acHighlight(items);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (activeIdx >= 0 && items[activeIdx]) acSelect(items[activeIdx]);
                } else if (e.key === 'Escape') {
                    acHide();
                    input.blur();
                }
            });

            document.addEventListener('click', function(e) {
                var wrap = document.getElementById(wrapId);
                if (wrap && !wrap.contains(e.target)) { acHide(); }
            });

            function acSearch(q) {
                dropdown.innerHTML = '<div class="ac-loading" style="padding:10px; font-size:11px; text-align:center; color:#94a3b8;">Mencari...</div>';
                dropdown.classList.add('show');
                var fd = new FormData();
                fd.append('q', q);

                window.fetch(config.url, { method: 'POST', body: fd })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (!data || !data.length) {
                            dropdown.innerHTML = '<div class="ac-empty" style="padding:10px; font-size:11px; text-align:center; color:#94a3b8;">Tidak ditemukan</div>';
                            return;
                        }
                        var html = '';
                        for (var i = 0; i < data.length; i++) {
                            var item = data[i];
                            var code = acEsc(item[config.codeField]);
                            var name = acEsc(item[config.nameField]);
                            html += '<div class="ac-item" data-code="' + code + '" data-name="' + name + '" style="padding:8px 12px; cursor:pointer; display:flex; gap:10px; font-size:11px;">'
                                  + '<span class="code" style="font-family:monospace; font-weight:600; color:#1d4ed8; min-width:90px;">' + code + '</span>'
                                  + '<span class="name" style="color:#0f172a; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' + name + '</span>'
                                  + '</div>';
                        }
                        dropdown.innerHTML = html;

                        var allItems = dropdown.querySelectorAll('.ac-item');
                        for (var j = 0; j < allItems.length; j++) {
                            allItems[j].addEventListener('click', function() { acSelect(this); });
                        }
                    })
                    .catch(function() {
                        dropdown.innerHTML = '<div class="ac-empty" style="padding:10px; font-size:11px; text-align:center; color:#94a3b8;">Gagal memuat data</div>';
                    });
            }

            function acSelect(el) {
                var code = el.getAttribute('data-code');
                var name = el.getAttribute('data-name');
                input.value  = code + ' - ' + name;
                hidden.value = code;
                acHide();
            }

            function acHighlight(items) {
                for (var i = 0; i < items.length; i++) {
                    if (i === activeIdx) { items[i].style.background = '#dbeafe'; } 
                    else { items[i].style.background = 'transparent'; }
                }
            }

            function acHide() { dropdown.classList.remove('show'); dropdown.innerHTML = ''; activeIdx = -1; }
        }

        function acEsc(s) {
            if (!s) return '';
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        // INIT AUTOCOMPLETES
        createAutocomplete({
            inputId:   'modalItemInput',
            hiddenId:  'modalItemHidden',
            dropdownId:'modalItemDropdown',
            wrapId:    'acWrapItem',
            url:       'search_item.php',
            codeField: 'ITEM_CODE',
            nameField: 'ITEM_NAME'
        });

        createAutocomplete({
            inputId:   'modalSupInput',
            hiddenId:  'modalSupHidden',
            dropdownId:'modalSupDropdown',
            wrapId:    'acWrapSup',
            url:       'search_sup.php',
            codeField: 'SUP_CODE',
            nameField: 'SUP_COMP'
        });
    </script>
</body>
</html>
<?php endif; ?>
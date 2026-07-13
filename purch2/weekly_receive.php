<?php
// ============================================
// KONFIGURASI DATABASE
// ============================================
require_once __DIR__ . "/../config/database_ordering.php";

// ============================================
// CEK APAKAH PARAMETER SUDAH DIISI
// ============================================
 $hasParams = isset($_GET['start_date']) && isset($_GET['end_date']);
 $isExport  = isset($_GET['export']) && $_GET['export'] === 'excel';

 $dataRows      = array();
 $grouped       = array();
 $totalRows     = 0;
 $startDate     = '';
 $endDate       = '';
 $supCode       = '';
 $itemName      = '';
 $currentYear   = date('Y');

if ($hasParams) {
    $startDate = $_GET['start_date'];
    $endDate   = $_GET['end_date'];
    $supCode   = isset($_GET['sup_code']) ? trim($_GET['sup_code']) : '';
    $itemName  = isset($_GET['item_name']) ? trim($_GET['item_name']) : '';

    // SP menggunakan LIKE langsung, pastikan format parameter sesuai kebutuhan query SP
    $supParam  = ($supCode !== '') ? $supCode : '%';

    $sql = "{CALL sp_rec_mat_sup(?, ?, ?, ?)}";
    $params = array(
        array($startDate, SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DATETIME),
        array($endDate,   SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DATETIME),
        array($supParam,  SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_VARCHAR(20)),
        array($itemName,  SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_VARCHAR(100))
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
    // GROUPING: Supplier -> PO -> Item
    // ============================================
    foreach ($dataRows as $row) {
        $supKey  = trim($row['SUP_CODE']);
        $poKey   = trim($row['PO_NUM']);
        $itemKey = trim($row['ITEM_CODE']);

        // 1. Level Supplier
        if (!isset($grouped[$supKey])) {
            $grouped[$supKey] = array(
                'SUP_CODE' => trim($row['SUP_CODE']),
                'SUP_COMP' => trim($row['SUP_COMP']),
                'pos'      => array()
            );
        }

        // 2. Level PO
        if (!isset($grouped[$supKey]['pos'][$poKey])) {
            $grouped[$supKey]['pos'][$poKey] = array(
                'PO_NUM' => $poKey,
                'items'  => array()
            );
        }

        // 3. Level Item
        if (!isset($grouped[$supKey]['pos'][$poKey]['items'][$itemKey])) {
            $grouped[$supKey]['pos'][$poKey]['items'][$itemKey] = array(
                'ITEM_CODE'     => $itemKey,
                'ITEM_NAME'     => trim($row['ITEM_NAME']),
                'POD_QTY'       => floatval($row['POD_QTY']),
                'UNIT'          => trim($row['UNIT']),
                'PRICE'         => floatval($row['PRICE']),
                'CURR_CODE'     => trim($row['CURR_CODE']),
                'TOTAL_RECEIVE' => 0,
                'dates'         => array()
            );
        }

        // 4. Baris Pecahan RCV Date
        $rcvDateKey = $row['RCV_DATE'] instanceof DateTime ? $row['RCV_DATE']->format('Y-m-d') : $row['RCV_DATE'];
        $rQty       = floatval($row['RQty']);
        $price      = floatval($row['PRICE']);

        if (!isset($grouped[$supKey]['pos'][$poKey]['items'][$itemKey]['dates'][$rcvDateKey])) {
            $grouped[$supKey]['pos'][$poKey]['items'][$itemKey]['dates'][$rcvDateKey] = array(
                'RCV_DATE' => $row['RCV_DATE'],
                'RQty'     => 0,
                'AMOUNT'   => 0
            );
        }

        $grouped[$supKey]['pos'][$poKey]['items'][$itemKey]['dates'][$rcvDateKey]['RQty']   += $rQty;
        $grouped[$supKey]['pos'][$poKey]['items'][$itemKey]['dates'][$rcvDateKey]['AMOUNT'] += ($rQty * $price);
        
        // Akumulasi Total Receive untuk Item induknya
        $grouped[$supKey]['pos'][$poKey]['items'][$itemKey]['TOTAL_RECEIVE'] += $rQty;
    }

    $totalRows = count($dataRows);
}

// FUNGSI FORMATTING
function formatDatePO($date) {
    if (!$date) return '';
    if ($date instanceof DateTime) return $date->format('d-M-y');
    $ts = strtotime($date);
    if ($ts === false) return '';
    return date('d-M-y', $ts);
}
function formatQty($num) {
    if ($num == 0) return '-';
    return number_format($num, 0, '.', ',');
}
function formatMoney($num) {
    if ($num == 0) return '0';
    if (floor($num) == $num) return number_format($num, 0, '.', ',');
    return number_format($num, 2, '.', ',');
}

// PROSES EXPORT EXCEL
if ($isExport) {
    $filename = "Weekly_Receive_Material_Supplier_" . date('Ymd') . ".xls";
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
    <title>Weekly Receive Material Supplier - PT.IMC TEKNO INDONESIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-page: #f0f2f5;
            --bg-card: #ffffff;
            --border-color: #e2e8f0;
            --accent: #2563eb;
            --accent-light: #dbeafe;
        }
        body {
            background-color: var(--bg-page);
            font-family: 'Times New Roman', Times, serif;
            font-size: 13px;
        }
        .param-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.5); backdrop-filter: blur(4px); z-index: 9999; display: flex; align-items: center; justify-content: center; }
        .param-dialog { background: var(--bg-card); border-radius: 16px; width: 560px; max-width: 95vw; box-shadow: 0 25px 60px -12px rgba(0,0,0,0.25); overflow: hidden; }
        .param-dialog-header { padding: 20px 28px 16px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 12px; }
        .param-dialog-header .icon-wrap { width: 36px; height: 36px; border-radius: 10px; background: var(--accent-light); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 16px; }
        .param-dialog-body { padding: 24px 28px; font-family: Arial, sans-serif; }
        .param-field { margin-bottom: 16px; }
        .param-field label { display: block; font-size: 11px; font-weight: 600; margin-bottom: 6px; color: #475569;}
        .param-field .form-control { font-size: 13px; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); }
        .date-range-row { display: flex; align-items: center; gap: 10px; }
        .param-field .ac-wrap { position: relative; }
        .param-field .ac-list { position: absolute; top: 100%; left: 0; right: 0; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; margin-top: 4px; max-height: 200px; overflow-y: auto; z-index: 10001; display: none; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15); }
        .param-field .ac-list.show { display: block; }
        .param-field .ac-item { padding: 8px 12px; cursor: pointer; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f1f5f9; }
        .param-field .ac-item:hover { background: var(--accent-light); }
        
        .report-container { background: var(--bg-card); border: 1px solid var(--border-color); padding: 40px; margin: 20px auto; max-width: 1400px; }
        .report-header { text-align: center; margin-bottom: 20px; }
        .company-name { font-size: 14px; font-weight: bold; text-align: left; font-family: Arial, sans-serif; }
        .report-title { font-size: 20px; font-weight: bold; text-decoration: underline; font-family: Arial, sans-serif; margin-top: 5px;}
        .report-params { font-size: 13px; font-weight: bold; font-family: Arial, sans-serif; margin-top: 5px; }
        
        .report-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .report-table thead th { border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 6px 2px; font-weight: bold; }
        .report-table tbody td { padding: 3px 2px; vertical-align: top; }
        
        @media print {
            body { background: #fff !important; }
            .no-print { display: none !important; }
            .report-container { border: none; padding: 0; margin: 0; }
        }
    </style>
</head>
<body>

    <!-- DIALOG FILTER PARAMETER -->
    <?php if (!$hasParams): ?>
    <div class="param-overlay" id="paramOverlay">
        <div class="param-dialog">
            <div class="param-dialog-header">
                <div class="icon-wrap"><i class="bi bi-funnel"></i></div>
                <div>
                    <div class="title" style="font-family: Arial, sans-serif; font-weight: bold;">Weekly Receive Report Filter</div>
                    <div class="subtitle" style="font-family: Arial, sans-serif; font-size: 11px; color:#94a3b8;">Kriteria Pencarian Data Penerimaan Barang</div>
                </div>
            </div>
            <div class="param-dialog-body">
                <form id="paramForm" method="GET" action="">
                    <div class="param-field">
                        <label>Periode Tanggal RCV</label>
                        <div class="date-range-row">
                            <input type="date" name="start_date" value="<?= $currentYear ?>-07-01" class="form-control" style="flex:1;" required>
                            <span>s/d</span>
                            <input type="date" name="end_date" value="<?= date('Y-m-d') ?>" class="form-control" style="flex:1;" required>
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
                    <div class="param-field">
                        <label>Item Name (LIKE Keyword)</label>
                        <input type="text" name="item_name" placeholder="Ketik nama item..." class="form-control">
                    </div>
                </form>
            </div>
            <div class="param-dialog-footer">
                <button type="button" class="btn btn-light btn-sm" onclick="window.location.reload();">Close</button>
                <button type="submit" form="paramForm" class="btn btn-primary btn-sm"><i class="bi bi-printer me-1"></i> View Report</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- NAVBAR ATAS -->
    <?php if ($hasParams): ?>
    <nav class="navbar navbar-light bg-white border-bottom fixed-top no-print">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1" style="font-family: Arial, sans-serif; font-size: 14px; font-weight: bold;">WEEKLY RECEIVE MATERIAL</span>
            <div>
                <button onclick="window.location.href='?'" class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> Parameter</button>
                <a href="?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&sup_code=<?= urlencode($supCode) ?>&item_name=<?= urlencode($itemName) ?>&export=excel" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="bi bi-printer"></i> Print</button>
            </div>
        </div>
    </nav>
    <div style="margin-top: 70px;"></div>
    <?php endif; ?>

<?php endif; // END OF: if (!$isExport) ?>

<?php if ($hasParams): ?>
    <?php if (!$isExport): ?><main class="container-fluid"><div class="report-container"><?php endif; ?>
        
        <!-- HEADER DOKUMEN -->
        <div class="company-name">PT.IMC TEKNO INDONESIA</div>
        <div class="report-header">
            <div class="report-title">WEEKLY RECEIVE MATERIAL SUPLIER</div>
            <div class="report-params">
                From Date : <?= formatDatePO($startDate) ?> &nbsp;&nbsp;&nbsp;&nbsp; To Date : <?= formatDatePO($endDate) ?>
            </div>
        </div>
        
        <!-- TABEL LAPORAN -->
        <table class="report-table" <?= $isExport ? 'border="1"' : '' ?>>
            <thead>
                <tr>
                    <th style="text-align:left; width: 12%;">Item</th>
                    <th style="text-align:left; width: 28%;">Name</th>
                    <th style="text-align:center; width: 10%;">RCV DATE</th>
                    <th style="text-align:right; width: 8%;">PO QTY</th>
                    <th style="text-align:right; width: 8%;">RECEIVE</th>
                    <th style="text-align:right; width: 8%;">O/S PO</th>
                    <th style="text-align:center; width: 6%;">Unit</th>
                    <th style="text-align:right; width: 10%;">Price</th>
                    <th style="text-align:center; width: 5%;">Curr</th>
                    <th style="text-align:right; width: 12%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($grouped) > 0): ?>
                    <?php foreach ($grouped as $sup): ?>
                        
                        <!-- SUPPLIER HEADER ROW -->
                        <tr>
                            <td colspan="10" style="font-weight: bold; padding-top: 10px; font-family: Arial, sans-serif;">
                                <?= htmlspecialchars($sup['SUP_CODE'] . ' ' . $sup['SUP_COMP']) ?>
                            </td>
                        </tr>
                        
                        <?php foreach ($sup['pos'] as $po): ?>
                            <!-- PO NUMBER LAYER HEADER -->
                            <tr>
                                <td colspan="10" style="font-weight: bold; padding-top: 5px; font-family: Arial, sans-serif;">
                                    <?= htmlspecialchars($po['PO_NUM']) ?>
                                </td>
                            </tr>
                            
                            <?php foreach ($po['items'] as $item): 
                                $os_po = $item['POD_QTY'] - $item['TOTAL_RECEIVE'];
                                $os_display = ($os_po <= 0) ? '-' : formatQty($os_po);
                            ?>
                                <!-- ITEM INDUK SUMMARY ROW -->
                                <tr>
                                    <td style="padding-left: 5px;"><?= htmlspecialchars($item['ITEM_CODE']) ?></td>
                                    <td><?= htmlspecialchars($item['ITEM_NAME']) ?></td>
                                    <td></td>
                                    <!-- PO QTY (Warna Biru) -->
                                    <td style="text-align:right; color: blue; font-weight: bold;"><?= number_format($item['POD_QTY'], 0, '.', ',') ?></td>
                                    <!-- TOTAL RECEIVE (Warna Hijau) -->
                                    <td style="text-align:right; color: green; font-weight: bold;"><?= number_format($item['TOTAL_RECEIVE'], 0, '.', ',') ?></td>
                                    <!-- OUTSTANDING PO (Warna Merah) -->
                                    <td style="text-align:right; color: red; font-weight: bold;"><?= $os_display ?></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                
                                <!-- DETAIL BREAKDOWN PER RCV DATE -->
                                <?php foreach ($item['dates'] as $dateRow): ?>
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td style="text-align:center;"><?= formatDatePO($dateRow['RCV_DATE']) ?></td>
                                        <td></td>
                                        <td style="text-align:right;"><?= number_format($dateRow['RQty'], 0, '.', ',') ?></td>
                                        <td></td>
                                        <td style="text-align:center;"><?= htmlspecialchars($item['UNIT']) ?></td>
                                        <td style="text-align:right;"><?= formatMoney($item['PRICE']) ?></td>
                                        <td style="text-align:center;"><?= htmlspecialchars($item['CURR_CODE']) ?></td>
                                        <td style="text-align:right;"><?= formatMoney($dateRow['AMOUNT']) ?></td>
                                    </tr>
                                <?php endforeach; ?>

                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align:center; padding: 30px; color: #94a3b8;">Tidak ada data pada kriteria periode ini.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
    <?php if (!$isExport): ?></div></main><?php endif; ?>
<?php endif; ?>

<?php if (!$isExport): ?>
    <!-- JAVASCRIPT AUTOCOMPLETE FOR SUPPLIER -->
    <script>
        function createAutocomplete(config) {
            var input    = document.getElementById(config.inputId);
            var hidden   = document.getElementById(config.hiddenId);
            var dropdown = document.getElementById(config.dropdownId);
            var wrapId   = config.wrapId;
            if (!input || !hidden || !dropdown) return;

            var acTimeout = null;

            input.addEventListener('input', function() {
                var q = this.value.trim();
                hidden.value = '';
                if (q.length < 1) { acHide(); return; }
                clearTimeout(acTimeout);
                acTimeout = setTimeout(function() { acSearch(q); }, 300);
            });

            input.addEventListener('focus', function() {
                var q = this.value.trim();
                if (q.length >= 1) acSearch(q);
            });

            document.addEventListener('click', function(e) {
                var wrap = document.getElementById(wrapId);
                if (wrap && !wrap.contains(e.target)) { acHide(); }
            });

            function acSearch(q) {
                dropdown.innerHTML = '<div style="padding:10px; font-size:11px; text-align:center; color:#94a3b8;">Mencari...</div>';
                dropdown.classList.add('show');
                var fd = new FormData();
                fd.append('q', q);

                window.fetch(config.url, { method: 'POST', body: fd })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (!data || !data.length) {
                            dropdown.innerHTML = '<div style="padding:10px; font-size:11px; text-align:center; color:#94a3b8;">Tidak ditemukan</div>';
                            return;
                        }
                        var html = '';
                        for (var i = 0; i < data.length; i++) {
                            var item = data[i];
                            var code = acEsc(item[config.codeField]);
                            var name = acEsc(item[config.nameField]);
                            html += '<div class="ac-item" data-code="' + code + '" data-name="' + name + '" style="padding:8px 12px; cursor:pointer; display:flex; gap:10px; font-size:11px;">'
                                  + '<span style="font-family:monospace; font-weight:600; color:#1d4ed8; min-width:90px;">' + code + '</span>'
                                  + '<span style="color:#0f172a; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' + name + '</span>'
                                  + '</div>';
                        }
                        dropdown.innerHTML = html;

                        var allItems = dropdown.querySelectorAll('.ac-item');
                        for (var j = 0; j < allItems.length; j++) {
                            allItems[j].addEventListener('click', function() { acSelect(this); });
                        }
                    })
                    .catch(function() {
                        dropdown.innerHTML = '<div style="padding:10px; font-size:11px; text-align:center; color:#94a3b8;">Gagal memuat data</div>';
                    });
            }

            function acSelect(el) {
                var code = el.getAttribute('data-code');
                var name = el.getAttribute('data-name');
                input.value  = code + ' - ' + name;
                hidden.value = code;
                acHide();
            }

            function acHide() { dropdown.classList.remove('show'); dropdown.innerHTML = ''; }
        }

        function acEsc(s) {
            if (!s) return '';
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

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
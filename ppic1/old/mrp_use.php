<?php
// mrp_use.php
$configPath = __DIR__ . "/../config/global.php"; 
if (file_exists($configPath)) { require_once $configPath; }
if (session_status() == PHP_SESSION_NONE) { session_start(); }

$currentYear = (int)date('Y');
$currentMonth = (int)date('n');
$currentDateTime = date('d-M-Y H:i');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>MRP System - ERP PPIC Module</title>
    <!-- Kompatibel dengan Browser Legacy/Modern -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 12px; background-color: #f0f2f5; margin: 0; padding: 0; color: #333; }
        .top-navbar { background-color: #000080; color: #fff; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .top-navbar .brand { font-size: 16px; font-weight: 700; letter-spacing: 1px; }
        .top-navbar .datetime { font-size: 12px; font-weight: 600; }
        .content-wrapper { padding: 15px; }
        .panel { background-color: #fff; border: 1px solid #dcdcdc; border-radius: 4px; padding: 15px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .panel-title { font-weight: 700; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 1px solid #eee; font-size: 13px; display: flex; align-items: center; gap: 8px; }
        .title-gray { color: #555; }
        .title-blue { color: #0056b3; border-bottom: none; margin-bottom: 5px; padding-bottom: 0; }
        .search-bar-row { display: flex; flex-wrap: wrap; gap: 20px; align-items: center; width: 100%; }
        .form-group { display: flex; align-items: center; gap: 8px; }
        label { font-weight: 600; color: #444; }
        select, input[type="text"] { padding: 5px 8px; border: 1px solid #ccc; border-radius: 3px; font-size: 12px; height: 28px; box-sizing: border-box; outline: none; transition: border-color 0.2s; }
        select:focus, input[type="text"]:focus { border-color: #80bdff; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); }
        input[readonly] { background-color: #f5f5f5; color: #666; cursor: not-allowed; }
        #txtItemCode { border: 1px solid #0056b3; font-weight: bold; color: #0056b3; }
        .input-yellow { background-color: #fff9c4 !important; color: #f57f17 !important; font-weight: 600; }
        
        button { padding: 5px 15px; font-weight: 600; cursor: pointer; border: 1px solid transparent; border-radius: 3px; height: 28px; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; font-size: 12px; }
        .btn-primary { background-color: #0056b3; color: #fff; }
        .btn-primary:hover { background-color: #004494; }
        .btn-success { background-color: #28a745; color: #fff; }
        .btn-success:hover { background-color: #218838; }
        .btn-secondary { background-color: #6c757d; color: #fff; }
        .btn-secondary:hover { background-color: #5a6268; }
        
        .divider-v { width: 1px; height: 30px; background-color: #ddd; margin: 0 5px; }
        .table-container { width: 100%; overflow-x: auto; border: 1px solid #dcdcdc; border-radius: 4px; background-color: #fff; }
        table.grid-table { border-collapse: separate; border-spacing: 0; white-space: nowrap; table-layout: fixed; width: 100%; }
        table.grid-table th, table.grid-table td { border-right: 1px solid #eee; border-bottom: 1px solid #eee; padding: 6px 8px; font-size: 11px; box-sizing: border-box; }
        table.grid-table th { background-color: #f8f9fa; font-weight: 700; color: #333; position: sticky; top: 0; z-index: 10; text-align: center; border-bottom: 2px solid #ddd; border-top: none; }
        
        /* Tampilan WEB */
        .day-col { width: 65px; min-width: 65px; text-align: right; }
        .desc-col { text-align: left; font-weight: 700; background-color: #f8f9fa; color: #444; width: 130px; min-width: 130px; position: sticky; left: 0; z-index: 11; }
        .gtotal-col { background-color: #f1f3f5; font-weight: 700; text-align: right; width: 85px; min-width: 85px; position: sticky; left: 130px; z-index: 11; }
        
        table.grid-table th.desc-col, table.grid-table th.gtotal-col { z-index: 12; background-color: #e9ecef; }
        table.grid-table tbody tr:hover td { background-color: #f8f9fa; }
        table.grid-table tbody tr:hover td.desc-col { background-color: #e9ecef; }
        
        /* Styling Input "In Plan" */
        .input-in-plan { width: 100%; text-align: right; border: 1px solid #0056b3; background-color: #e6f7ff; box-sizing: border-box; padding: 3px; font-weight: 600; border-radius: 2px; }
        .input-in-plan:focus { outline: none; background-color: #fff; border: 1px solid #ff9900; box-shadow: 0 0 3px rgba(255,153,0,0.5); }
        .val-minus { color: #cc0000 !important; background-color: #ffe6e6 !important; }
        
        /* CSS Khusus Autocomplete */
        .ui-autocomplete { font-size: 11px; max-height: 250px; overflow-y: auto; overflow-x: hidden; z-index: 9999; border: 1px solid #0056b3; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .ui-menu-item-wrapper { padding: 5px 8px !important; border-bottom: 1px solid #eee; }
        .ui-state-active { background-color: #e6f7ff !important; border-color: #99c2ff !important; color: #333 !important; }

        .workspace-container { display: flex; flex-direction: column; gap: 20px; }
        .workspace { display: flex; width: 100%; gap: 15px; position: relative; padding-bottom: 10px; }
        .workspace::after { content: ''; display: block; position: absolute; bottom: -10px; left: 0; right: 0; height: 1px; border-bottom: 2px dashed #cfd8dc; }
        .workspace:last-child::after { display: none; }
        .left-pane { flex: 0 0 250px; width: 250px; }
        .right-pane { flex: 1; min-width: 0; overflow: hidden; } 
        .pref-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .pref-table td { padding: 5px 0; }
        .pref-table td.label-cell { font-weight: 600; width: 80px; text-align: right; padding-right: 10px; color: #555; }
        .pref-table input[type="text"] { width: 100%; }
        ::-webkit-scrollbar { height: 8px; width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb { background: #bbb; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #888; }
        .action-bar { padding: 10px; background: #f8f9fa; border: 1px solid #dcdcdc; border-top: none; text-align: left; border-radius: 0 0 4px 4px; }

        /* Alert Minus Banner */
        .alert-minus { background-color: #ffebee; color: #c62828; border: 1px solid #ef9a9a; padding: 12px 15px; border-radius: 4px; margin-bottom: 15px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 10px; transition: background-color 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .alert-minus:hover { background-color: #ffcdd2; }

        /* KELAS UNTUK WEEKEND (SABTU & MINGGU) */
        .bg-weekend { background-color: #ffe4e1 !important; }
        input.input-in-plan.bg-weekend { background-color: #ffe4e1 !important; border-color: #d87093; }

        /* ========================================= */
        /* CSS Print Mode - A4 Landscape (Identik Layar) */
        /* ========================================= */
        @media print {
            @page { 
                size: A4 landscape; 
                margin: 5mm; 
            }
            
            /* Memaksa background color untuk tetap dicetak (bergantung pd setelan browser) */
            body { background-color: #f0f2f5; padding: 0; margin: 0; -webkit-print-color-adjust: exact !important; color-adjust: exact !important; zoom: 90%; }
            
            /* Sembunyikan navbar atas, action bar (tombol save/print), dan alert info */
            .top-navbar, .action-bar, #minusAlert { display: none !important; }
            .content-wrapper { padding: 5px; }
            
            /* Panel Parameters & Filters tetap dimunculkan */
            .panel { border: 1px solid #ccc !important; box-shadow: none !important; padding: 10px !important; margin-bottom: 10px !important; }
            .search-bar-row { flex-wrap: nowrap !important; gap: 15px !important; }
            #btnLoad { display: none !important; } /* Sembunyikan khusus tombol Load MRP saja */

            /* --- PERTAHANKAN FLEXBOX KIRI & KANAN --- */
            .workspace-container { display: block; }
            .workspace { display: flex !important; flex-direction: row !important; gap: 10px !important; page-break-inside: avoid; border: none; margin: 0 0 15px 0 !important; padding: 0; }
            .workspace::after { display: none; }
            
            /* Panel Kiri (MRP Target) */
            .left-pane { flex: 0 0 180px !important; width: 180px !important; margin: 0 !important; }
            .panel-title { font-size: 11px !important; margin-bottom: 8px !important; padding-bottom: 4px !important; color: #0056b3 !important; }
            
            .pref-table { width: 100%; border-collapse: separate; border-spacing: 0 4px; font-size: 9px !important; }
            .pref-table td { padding: 2px !important; border: none; }
            .pref-table td.label-cell { width: 60px !important; text-align: right; font-weight: 600; color: #555; }
            .pref-table input[type="text"] { font-size: 9px !important; padding: 2px 4px !important; border: 1px solid #ccc !important; width: 100%; box-sizing: border-box; font-weight: bold; }
            .input-yellow { background-color: #fff9c4 !important; color: #f57f17 !important; }

            /* Panel Kanan (Tabel Matrix) */
            .right-pane { flex: 1 !important; min-width: 0; overflow: hidden !important; }
            .table-container { overflow: hidden !important; border: 1px solid #ccc !important; width: 100%; border-radius: 4px; }
            table.grid-table { border-collapse: collapse; width: 100%; table-layout: fixed; }

            /* Skala Kolom Tabel Khusus A4 */
            .day-col { min-width: 0 !important; width: auto !important; }
            .desc-col { min-width: 0 !important; width: 75px !important; white-space: normal !important; background-color: #f8f9fa !important; }
            .gtotal-col { min-width: 0 !important; width: 50px !important; background-color: #f1f3f5 !important; }

            table.grid-table th, table.grid-table td { 
                font-size: 7.5px !important; 
                padding: 3px 1px !important; 
                border: 1px solid #ddd !important; 
                word-wrap: break-word;
                color: #000;
                text-align: center;
            }
            table.grid-table th { background-color: #f0f0f0 !important; font-weight: bold; }
            table.grid-table td.desc-col { text-align: left; font-weight: bold; }
            table.grid-table td.gtotal-col { text-align: right; font-weight: bold; }
            
            /* Input In Plan menyesuaikan teks murni */
            .input-in-plan { border: none !important; background: transparent !important; padding: 0; font-size: 7.5px !important; box-shadow: none !important; text-align: right; color: #000; font-weight: bold; }
            .val-minus { color: #cc0000 !important; background-color: #ffe6e6 !important; font-weight: bold; }

            /* Sembunyikan workspace yang tidak sedang di-print */
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="top-navbar">
        <div class="brand">PPIC SYSTEM - MRP MANAGER</div>
        <div class="datetime"><?php echo htmlspecialchars($currentDateTime, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>

    <div class="content-wrapper">
        <div class="panel">
            <div class="panel-title title-gray"><i class="fa fa-search"></i> Parameters & Filters</div>
            <div class="search-bar-row">
                <div class="form-group">
                    <label>Tahun:</label>
                    <select id="cbTahun">
                        <?php for ($y = $currentYear - 2; $y <= $currentYear + 2; $y++) {
                            $sel = ($y === $currentYear) ? 'selected' : '';
                            echo "<option value=\"$y\" $sel>$y</option>";
                        } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Bulan:</label>
                    <select id="cbBulan">
                        <?php for ($m = 1; $m <= 12; $m++) {
                            $sel = ($m === $currentMonth) ? 'selected' : '';
                            echo "<option value=\"$m\" $sel>$m</option>";
                        } ?>
                    </select>
                </div>
                <div class="divider-v"></div>
                <div class="form-group">
                    <label>Item Code:</label>
                    <input type="text" id="txtItemCode" size="18" placeholder="Ketik Item Code..." autocomplete="off">
                </div>
                <div class="form-group">
                    <button id="btnLoad" class="btn-primary"><i class="fa fa-refresh"></i> Load MRP</button>
                </div>
            </div>
        </div>

        <div id="mainContainer" style="display:none;">
            <!-- BANNER INFO MINUS -->
            <div id="minusAlert" class="alert-minus" style="display:none;"></div>
            
            <div id="mrpDetailContainer" class="workspace-container"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    
    <script>
        $(document).ready(function() {
            
            function escapeHtml(str) {
                if (str === null || str === undefined) return '';
                return String(str).replace(/[&<>"']/g, function(m) {
                    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[m];
                });
            }

            // Fungsi untuk mendeteksi apakah hari tsb Sabtu atau Minggu
            function isWeekend(year, month, day) {
                // Bulan di JavaScript dimulai dari 0 (Jan = 0, Des = 11)
                var dt = new Date(year, month - 1, day);
                var dayOfWeek = dt.getDay();
                return (dayOfWeek === 6 || dayOfWeek === 0); // 6 = Sabtu, 0 = Minggu
            }

            // =====================================================================
            // AUTOCOMPLETE
            // =====================================================================
            $('#txtItemCode').autocomplete({
                minLength: 2,
                source: function(request, response) {
                    $.ajax({
                        url: 'ajax_autocomplete_mat.php',
                        type: 'GET',
                        dataType: 'json',
                        data: { term: request.term, tahun: $('#cbTahun').val(), bulan: $('#cbBulan').val() },
                        success: function(data) { response(data); },
                        error: function() { response([]); }
                    });
                },
                select: function(event, ui) {
                    $('#txtItemCode').val(ui.item.value);
                    return false;
                }
            }).autocomplete("instance")._renderItem = function(ul, item) {
                return $("<li>")
                    .append("<div><b>" + escapeHtml(item.value) + "</b> - <span style='color:#555;'>" + escapeHtml(item.name) + "</span></div>")
                    .appendTo(ul);
            };

            // =====================================================================
            // FUNGSI UTAMA: Update Informasi Banner Minus
            // =====================================================================
            window.updateMinusSummary = function() {
                var minusCount = 0;
                var targets = [];

                $('.workspace').each(function() {
                    // Cari sel Stock Plan dalam workspace ini yang memiliki kelas val-minus
                    if ($(this).find('.cell-stock-plan.val-minus').length > 0) {
                        minusCount++;
                        targets.push($(this));
                    }
                });

                if (minusCount > 0) {
                    $('#minusAlert').html('<i class="fa fa-exclamation-circle" style="font-size:16px;"></i> Peringatan: Terdapat ' + minusCount + ' Item dengan Stock Plan Minus! (Klik di sini untuk melihat)').fadeIn(200);
                    $('#minusAlert').data('targets', targets); // Simpan referensi DOM
                } else {
                    $('#minusAlert').fadeOut(200);
                }
            };

            // Event Klik Pada Banner Minus (Auto-Scroll)
            $('#mainContainer').on('click', '#minusAlert', function() {
                var targets = $(this).data('targets');
                if (targets && targets.length > 0) {
                    var target = targets[0]; // Auto scroll ke item pertama yang minus
                    $('html, body').animate({ scrollTop: target.offset().top - 20 }, 500);
                    
                    // Efek Highlight Sesaat
                    target.css({'box-shadow': '0 0 12px 3px #ef5350', 'transition': 'box-shadow 0.5s ease'});
                    setTimeout(function() { target.css('box-shadow', 'none'); }, 3000);
                }
            });

            // =====================================================================
            // FUNGSI UTAMA: Kalkulasi Real-time MRP Matrix
            // =====================================================================
            window.recalcMRPMatrix = function(workspaceId) {
                var table = $('#matrix_' + workspaceId);
                var begStock = parseFloat(table.data('begstock')) || 0;
                
                var runStockPlan = begStock;
                var runStockAct  = begStock;

                var totalUsedPlan = 0, totalUsedAct = 0;
                var totalInPlan   = 0, totalInAct   = 0;
                var totalOutAct   = 0;

                for (var d = 1; d <= 31; d++) {
                    var usedPlan = parseFloat(table.find('.cell-used-plan.day-' + d).text()) || 0;
                    var usedAct  = parseFloat(table.find('.cell-used-act.day-' + d).text())  || 0;
                    var inPlan   = parseFloat(table.find('.input-in-plan.day-' + d).val())   || 0; 
                    var inAct    = parseFloat(table.find('.cell-in-act.day-' + d).text())    || 0;
                    var outAct   = parseFloat(table.find('.cell-out-act.day-' + d).text())   || 0;

                    runStockPlan = runStockPlan - usedPlan + inPlan;
                    runStockAct  = runStockAct + inAct - outAct;

                    if(Math.abs(runStockPlan) < 0.001) runStockPlan = 0;
                    if(Math.abs(runStockAct) < 0.001) runStockAct = 0;

                    table.find('.cell-stock-plan.day-' + d).text(runStockPlan !== 0 ? runStockPlan.toFixed(2) : '0').toggleClass('val-minus', runStockPlan < 0);
                    table.find('.cell-stock-act.day-' + d).text(runStockAct !== 0 ? runStockAct.toFixed(2) : '0').toggleClass('val-minus', runStockAct < 0);
                    
                    totalUsedPlan += usedPlan;
                    totalUsedAct  += usedAct;
                    totalInPlan   += inPlan;
                    totalInAct    += inAct;
                    totalOutAct   += outAct;
                }

                table.find('tr').each(function() {
                    var tr = $(this);
                    var rName = $.trim(tr.find('.desc-col').text());
                    var gTotalCell = tr.find('.gtotal-col');
                    
                    if (rName === 'Used Plan') { gTotalCell.text(totalUsedPlan !== 0 ? totalUsedPlan.toFixed(2) : '0'); } 
                    else if (rName === 'Used Act') { gTotalCell.text(totalUsedAct !== 0 ? totalUsedAct.toFixed(2) : '0'); } 
                    else if (rName === 'In Plan') { gTotalCell.text(totalInPlan !== 0 ? totalInPlan.toFixed(2) : '0'); } 
                    else if (rName === 'In Act') { gTotalCell.text(totalInAct !== 0 ? totalInAct.toFixed(2) : '0'); } 
                    else if (rName === 'Out Act') { gTotalCell.text(totalOutAct !== 0 ? totalOutAct.toFixed(2) : '0'); } 
                    else if (rName === 'Stock Plan' || rName === 'Stock Act') { gTotalCell.text('').removeClass('val-minus'); }
                });

                // Setiap kali selesai menghitung matrix, update banner summary
                updateMinusSummary();
            };

            
            function buildWorkspaceHtml(idx, data) {
                var begStock = parseFloat(data.BEG_STOCK) || 0;
                var safeCode = escapeHtml(data.ITEM_CODE);
                var begStockDisplay = begStock !== 0 ? begStock.toFixed(2) : '0';
                
                // Ambil parameter tahun & bulan untuk dilempar ke stok.php
                var y = $('#cbTahun').val();
                var m = parseInt($('#cbBulan').val());
                var mStr = (m < 10) ? '0' + m : m;
                var lastDay = new Date(y, m, 0).getDate(); // Cari tanggal terakhir di bulan tersebut
                var startDate = y + '-' + mStr + '-01';
                var endDate = y + '-' + mStr + '-' + lastDay;
                
                // Bentuk URL hyperlink
                var stokUrl = 'stok.php?report_type=bahanbaku&item_id=' + encodeURIComponent(data.ITEM_CODE) + '&start_date=' + startDate + '&end_date=' + endDate;
                
                var html = '<div class="workspace" id="ws_' + idx + '">';
                html += '<div class="left-pane"><div class="panel" style="margin-bottom:0; height:100%; box-sizing:border-box;">';
                html += '  <div class="panel-title title-blue"><i class="fa fa-cube"></i> MRP Target</div>';
                html += '  <table class="pref-table">';
                
                // Modifikasi input text menjadi seperti link (cursor:pointer, underline, dan onclick)
                html += '    <tr><td class="label-cell">Mat. Code</td><td><input type="text" value="' + safeCode + '" readonly style="color:#0056b3; font-weight:600; cursor:pointer; text-decoration:underline;" onclick="window.open(\'' + stokUrl + '\', \'_blank\')" title="Klik untuk buka Stock Analysis"></td></tr>';
            
            
                html += '    <tr><td class="label-cell">Mat. Name</td><td><input type="text" value="' + escapeHtml(data.ITEM_NAME) + '" readonly></td></tr>';
                html += '    <tr><td class="label-cell">Material</td><td><input type="text" value="' + escapeHtml(data.MATERIAL_NO) + '" readonly></td></tr>';
                html += '    <tr><td class="label-cell">Supplier</td><td><input type="text" value="' + escapeHtml(data.SUPPLIER) + '" readonly></td></tr>';
                html += '    <tr><td colspan="2"><div style="height:10px;"></div></td></tr>';
                html += '    <tr><td class="label-cell">Beg. Stock</td><td><input type="text" class="input-yellow" value="' + begStockDisplay + '" readonly></td></tr>';
                html += '    <tr><td class="label-cell">Mix</td><td><input type="text" value="' + escapeHtml(data.MIX) + '" readonly></td></tr>';
                html += '  </table>';
                html += '</div></div>';
                
                html += '<div class="right-pane">';
                html += '<div class="table-container"><table class="grid-table" id="matrix_' + idx + '" data-begstock="' + begStock + '">';
                html += '<thead><tr><th class="desc-col">Description</th><th class="gtotal-col">Grand Total</th>';
                
                // HEADER TABEL (Menambahkan Kelas Weekend)
                for(var i=1; i<=31; i++) { 
                    var weekendClass = isWeekend(y, m, i) ? ' bg-weekend' : '';
                    html += '<th class="day-col' + weekendClass + '">' + i + '</th>'; 
                }
                html += '</tr></thead><tbody>';

                var rows = [
                    { name: 'Used Plan', class: 'cell-used-plan' },
                    { name: 'Used Act', class: 'cell-used-act' },
                    { name: 'In Plan', isInput: true }, 
                    { name: 'In Act', class: 'cell-in-act' },
                    { name: 'Out Act', class: 'cell-out-act' },
                    { name: 'Stock Plan', class: 'cell-stock-plan' },
                    { name: 'Stock Act', class: 'cell-stock-act' }
                ];

               $.each(rows, function(rIndex, rDef) {
                    html += '<tr>';
                    html += '<td class="desc-col">' + rDef.name + '</td><td class="gtotal-col">0</td>';
                    var rowData = data.rows[rDef.name] || {};
                    for(var d=1; d<=31; d++) {
                        var val = parseFloat(rowData['D'+d]) || 0;
                        var displayVal = val !== 0 ? val.toFixed(2) : '0'; 
                        
                        // Cek Weekend untuk Kolom Cell
                        var weekendClass = isWeekend(y, m, d) ? ' bg-weekend' : '';
                        
                        // Buat format tanggal (YYYY-MM-DD) untuk URL
                        var dStr = (d < 10) ? '0' + d : d;
                        var fullDate = y + '-' + mStr + '-' + dStr;

                        if (rDef.isInput) {
                            html += '<td class="day-col' + weekendClass + '"><input type="text" class="input-in-plan day-' + d + weekendClass + '" value="' + (val !== 0 ? val.toFixed(2) : '') + '" /></td>';
                        } else if (rDef.name === 'In Act' && val !== 0) {
                            // Render Hyperlink khusus untuk baris "In Act" jika ada nilainya
                            var bcUrl = 'bc.php?item=' + encodeURIComponent(data.ITEM_CODE) + '&date=' + fullDate;
                            html += '<td class="day-col ' + rDef.class + ' day-' + d + weekendClass + '">';
                            html += '<a href="' + bcUrl + '" target="_blank" style="color: #0056b3; font-weight: bold; text-decoration: underline;" title="Klik untuk lihat detail BC/PO">' + displayVal + '</a>';
                            html += '</td>';
                        } else {
                            html += '<td class="day-col ' + rDef.class + ' day-' + d + weekendClass + '">' + displayVal + '</td>';
                        }
                    }
                    html += '</tr>';
                });
                
                html += '</tbody></table></div>';
                
                // ACTION BAR WITH PRINT BUTTON
                html += '<div class="action-bar">';
                html += '  <button class="btn-success btn-save-mrp" data-itemcode="' + safeCode + '" data-idx="' + idx + '"><i class="fa fa-save"></i> Save In Plan</button>';
                html += '  <button class="btn-secondary btn-print-mrp" style="margin-left:5px;"><i class="fa fa-print"></i> Print</button>';
                html += '</div>';
                html += '</div></div>';
                
                return html;
            }

            // =====================================================================
            // EVENT LISTENERS
            // =====================================================================

            // Event Print Per Workspace
            $('#mrpDetailContainer').on('click', '.btn-print-mrp', function() {
                var currentWorkspace = $(this).closest('.workspace');
                
                // Tambahkan kelas no-print ke semua workspace lain agar hanya 1 data yang diprint
                $('.workspace').addClass('no-print');
                currentWorkspace.removeClass('no-print');
                
                window.print();
                
                // Kembalikan ke kondisi semula setelah dialog print selesai/ditutup
                $('.workspace').removeClass('no-print');
            });

            // Keyboard Navigation (Panah Kiri, Kanan & Enter untuk Save)
            $('#mrpDetailContainer').on('keydown', '.input-in-plan', function(e) {
                var keyCode = e.keyCode || e.which;
                if (keyCode === 37) {
                    e.preventDefault(); 
                    var prevInput = $(this).closest('td').prev('td').find('.input-in-plan');
                    if (prevInput.length) { prevInput.focus().select(); }
                } else if (keyCode === 39) {
                    e.preventDefault();
                    var nextInput = $(this).closest('td').next('td').find('.input-in-plan');
                    if (nextInput.length) { nextInput.focus().select(); }
                } else if (keyCode === 13) { // Deteksi tombol Enter
                    e.preventDefault();
                    $(this).blur(); // Hilangkan fokus agar sistem menghitung ulang Grand Total terlebih dahulu
                    // Memicu klik pada tombol Save hijau di workspace yang sedang aktif
                    $(this).closest('.workspace').find('.btn-save-mrp').click();
                }
            });

            // AJAX Save
            $('#mrpDetailContainer').on('click', '.btn-save-mrp', function() {
                var btn = $(this);
                var workspace = btn.closest('.workspace');
                var itemCode = btn.data('itemcode');
                var tahun = $('#cbTahun').val();
                var bulan = $('#cbBulan').val();

                if (!itemCode) {
                    alert('Tindakan Protektif: Material Code hilang, silakan muat ulang data (Load MRP).');
                    return;
                }

                btn.html('<i class="fa fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
                var daysData = {};
                for (var i = 1; i <= 31; i++) {
                    var val = parseFloat(workspace.find('.input-in-plan.day-' + i).val()) || 0;
                    daysData['d' + i] = val;
                }

                $.ajax({
                    url: 'ajax_save_in_plan.php',
                    type: 'POST',
                    data: { tahun: tahun, bulan: bulan, item_code: itemCode, days: daysData },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            btn.html('<i class="fa fa-check"></i> Saved').removeClass('btn-success').addClass('btn-primary');
                            setTimeout(function() {
                                btn.html('<i class="fa fa-save"></i> Save In Plan').removeClass('btn-primary').addClass('btn-success');
                                btn.prop('disabled', false);
                            }, 2000);
                        } else {
                            alert('Gagal Menyimpan: ' + res.message);
                            btn.html('<i class="fa fa-save"></i> Save In Plan').prop('disabled', false);
                        }
                    },
                    error: function(xhr) {
                        alert('Koneksi ke server terputus.');
                        btn.html('<i class="fa fa-save"></i> Save In Plan').prop('disabled', false);
                    }
                });
            });

            // AJAX Load Data
            $('#btnLoad').click(function() {
                var btn = $(this);
                var tahun = $('#cbTahun').val();
                var bulan = $('#cbBulan').val();
                var itemCode = $.trim($('#txtItemCode').val());

                if (!tahun || !bulan) {
                    alert("Peringatan: Tahun dan Bulan wajib dipilih.");
                    return; 
                }

                btn.html('<i class="fa fa-spinner fa-spin"></i> Loading...').prop('disabled', true);
                $('#minusAlert').hide(); // Hide banner info saat loading
                $('#mainContainer').hide();
                $('#mrpDetailContainer').empty();

                $.ajax({
                    url: 'ajax_load_mrp.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { tahun: tahun, bulan: bulan, itemCode: itemCode },
                    success: function(response) {
                        if (response.error) {
                            alert("Error: " + response.error);
                            btn.html('<i class="fa fa-refresh"></i> Load MRP').prop('disabled', false);
                            return;
                        }
                        if (response.length === 0) {
                            $('#mrpDetailContainer').html('<div class="panel" style="color:#cc0000; font-weight:600;"><i class="fa fa-exclamation-triangle"></i> Tidak ada data MRP ditemukan.</div>');
                            $('#mainContainer').fadeIn();
                            btn.html('<i class="fa fa-refresh"></i> Load MRP').prop('disabled', false);
                            return;
                        }

                        var detailHtml = '';
                        $.each(response, function(idx, item) {
                            detailHtml += buildWorkspaceHtml(idx, item);
                        });
                        
                        $('#mrpDetailContainer').html(detailHtml);
                        
                        // Hitung Matrix & Update Minus Summary
                        $.each(response, function(idx) {
                            recalcMRPMatrix(idx);
                        });

                        $('#mainContainer').fadeIn();
                    },
                    error: function(xhr, status, error) {
                        alert("Terjadi kesalahan jaringan/server: " + error);
                    },
                    complete: function() {
                        btn.html('<i class="fa fa-refresh"></i> Load MRP').prop('disabled', false);
                    }
                });
            });
            
            // Realtime Calc on input change
            $('#mrpDetailContainer').on('input change', '.input-in-plan', function() {
                var workspaceId = $(this).closest('.workspace').attr('id').replace('ws_', '');
                recalcMRPMatrix(workspaceId);
            });
        });
    </script>
</body>
</html>
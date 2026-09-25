<?php
// mat_use.php
// Tindakan Protektif: Mencegah session collision dan error 'headers already sent'
$configPath = __DIR__ . "/../config/global.php"; 
if (file_exists($configPath)) { require_once $configPath; }

if (session_status() == PHP_SESSION_NONE) { session_start(); }

$currentYear = (int)date('Y');
$currentMonth = (int)date('n');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Material Usage - ERP PPIC Module</title>
    <!-- Library Eksternal (Kompabilitas Standar Era PHP 5.4 / IE9+) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css">
    
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; background-color: #f4f6f9; margin: 0; padding: 15px; }
        .panel { background-color: #fff; border: 1px solid #ccc; padding: 12px; margin-bottom: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .panel-title { font-weight: bold; color: #333; margin-bottom: 12px; border-bottom: 2px solid #0056b3; padding-bottom: 4px; font-size: 13px; }
        
        /* Form & Inputs */
        .form-group { display: inline-block; margin-right: 15px; vertical-align: middle; }
        label { font-weight: bold; margin-right: 5px; color: #444; }
        select, input[type="text"] { padding: 4px; border: 1px solid #ccc; font-size: 12px; height: 26px; box-sizing: border-box; }
        input[readonly] { background-color: #e9ecef; color: #555; border-color: #bbb; cursor: not-allowed; }
        
        /* Buttons */
        button { padding: 4px 15px; font-weight: bold; cursor: pointer; border: 1px solid transparent; height: 28px; transition: background-color 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 5px; }
        .btn-primary { background-color: #0056b3; color: #fff; border-color: #004494; }
        .btn-primary:hover { background-color: #004494; }
        .btn-success { background-color: #28a745; color: #fff; border-color: #1e7e34; }
        .btn-success:hover { background-color: #218838; }
        .btn-secondary { background-color: #6c757d; color: #fff; border-color: #5a6268; }
        .btn-secondary:hover { background-color: #5a6268; }
        button:disabled { opacity: 0.6; cursor: not-allowed; }
        
        /* Tindakan Protektif UI: Autocomplete Custom Styling (Mencegah overflow layar) */
        .ui-autocomplete { font-size: 11px; max-height: 250px; overflow-y: auto; overflow-x: hidden; z-index: 9999; border: 1px solid #0056b3; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .ui-menu-item-wrapper { padding: 5px 8px !important; border-bottom: 1px solid #eee; }
        .ui-state-active { background-color: #e6f7ff !important; border-color: #99c2ff !important; color: #333 !important; }

        /* Grid Table (Modern Sticky Header) */
        .table-container { width: 100%; overflow-x: auto; max-height: 400px; border: 1px solid #bbb; background-color: #fff; }
        table.grid-table { border-collapse: separate; border-spacing: 0; white-space: nowrap; table-layout: fixed; }
        table.grid-table th, table.grid-table td { border-right: 1px solid #bbb; border-bottom: 1px solid #bbb; padding: 5px 6px; font-size: 11px; box-sizing: border-box; }
        table.grid-table th { border-top: 1px solid #bbb; background-color: #eaeaea; font-weight: bold; position: sticky; top: 0; z-index: 10; text-align: center; }
        table.grid-table td:first-child, table.grid-table th:first-child { border-left: 1px solid #bbb; }
        
        .day-col { width: 60px; min-width: 60px; text-align: right; }
        .desc-col { text-align: left; font-weight: bold; background-color: #f9f9f9; width: 150px; min-width: 150px; position: sticky; left: 0; z-index: 11; }
        .gtotal-col { background-color: #e2e2e2; font-weight: bold; text-align: right; width: 85px; min-width: 85px; position: sticky; left: 150px; z-index: 11; }
        table.grid-table th.desc-col, table.grid-table th.gtotal-col { z-index: 12; background-color: #dcdcdc; }

        /* Workspaces (Layout Flexbox untuk Detail FG) */
        .workspace { display: flex; width: 100%; gap: 15px; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 3px dashed #b0bec5; flex-direction: column; }
        .workspace-top { display: flex; width: 100%; gap: 15px; }
        .workspace:last-child { border-bottom: none; }
        .left-pane { flex: 0 0 290px; width: 290px; }
        .right-pane { flex: 1; min-width: 0; overflow: hidden; } 
        
        /* Pref-Table (Panel Kiri) */
        .pref-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .pref-table td { padding: 4px; border: 1px solid #ddd; }
        .pref-table td.label-cell { background-color: #f2f2f2; font-weight: bold; width: 90px; text-align: right; }
        .pref-table input[type="text"] { width: 100%; border: 1px solid #bbb; background: #fafafa; text-align: left; padding: 4px; box-sizing: border-box; color: #0056b3; font-weight: bold;}

        /* Warna Pink untuk Sabtu & Minggu (Mendukung Print) */
        .weekend-pink { background-color: #ffc0cb !important; color: #000 !important; }

        /* ========================================= */
        /* CSS Print Mode - A4 Landscape (Identik Layar) */
        /* ========================================= */
        @media print {
            @page { 
                size: A4 landscape; 
                margin: 5mm; 
            }
            
            /* Paksa background cetak sesuai pengaturan browser */
            body { background-color: #f4f6f9; padding: 0; margin: 0; -webkit-print-color-adjust: exact !important; color-adjust: exact !important; zoom: 90%; }
            
            /* Sembunyikan elemen tombol agar tidak ikut di-print */
            button { display: none !important; }
            .ui-autocomplete { display: none !important; }
            
            /* Panel Parameters & Filters tetap dimunculkan */
            .panel { border: 1px solid #ccc !important; box-shadow: none !important; padding: 10px !important; margin-bottom: 10px !important; }
            .panel-title { font-size: 11px !important; margin-bottom: 8px !important; padding-bottom: 4px !important; }

            /* --- PERTAHANKAN FLEXBOX KIRI & KANAN --- */
            #mainContainer { display: block; width: 100%; }
            .workspace { display: flex !important; flex-direction: column !important; gap: 0 !important; margin-bottom: 20px !important; page-break-inside: avoid; border: none; }
            .workspace-top { display: flex !important; flex-direction: row !important; gap: 10px !important; width: 100%; }
            
            /* Skala Panel Kiri (FG Target) */
            .left-pane { flex: 0 0 180px !important; width: 180px !important; margin: 0 !important; }
            .pref-table { font-size: 9px !important; border-spacing: 0; }
            .pref-table td { padding: 2px !important; }
            .pref-table td.label-cell { width: 60px !important; }
            .pref-table input[type="text"] { font-size: 9px !important; padding: 2px !important; border: 1px solid #ccc !important; }

            /* Skala Panel Kanan (Matrix 31 Hari) */
            .right-pane { flex: 1 !important; min-width: 0; overflow: hidden !important; }
            .table-container { overflow: hidden !important; border: 1px solid #ccc !important; max-height: none !important; }
            table.grid-table { border-collapse: collapse; width: 100%; table-layout: fixed; }
            
            /* Paksa kolom hari tidak memiliki batas bawah (agar fit A4) */
            .day-col { min-width: 0 !important; width: auto !important; }
            .desc-col { min-width: 0 !important; width: 85px !important; white-space: normal !important; background-color: #f9f9f9 !important; }
            .gtotal-col { min-width: 0 !important; width: 55px !important; background-color: #e2e2e2 !important; }

            table.grid-table th, table.grid-table td { 
                font-size: 7px !important; /* Dikecilkan ekstra agar data 31 hari muat sempurna */
                padding: 3px 1px !important; 
                border: 1px solid #ddd !important; 
                word-wrap: break-word; text-align: center; color: #000;
            }
            table.grid-table th { background-color: #eaeaea !important; }
            table.grid-table td.desc-col { text-align: left; }
            table.grid-table td.gtotal-col { text-align: right; }

            /* Sistem isolasi workspace (Menyembunyikan tabel yang tidak ditekan tombol print-nya) */
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <!-- Panel Kontrol Utama -->
    <div class="panel">
        <div class="panel-title"><i class="fa fa-search"></i> Search & Parameters</div>
        <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: center;">
            
            <!-- Blok Pemilihan Periode -->
            <div>
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
            </div>
            
            <!-- Blok Penentuan Material & Perintah Dasar -->
            <div style="border-left: 2px solid #eee; padding-left: 20px;">
                <div class="form-group">
                    <label>Material Code:</label>
                    <input type="text" id="txtMatCode" size="18" placeholder="Ketik code..." style="border: 1px solid #0056b3; font-weight: bold;" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Name:</label>
                    <input type="text" id="txtMatName" size="30" readonly placeholder="Auto-fill material name...">
                </div>
                <div class="form-group">
                    <button id="btnLoad" class="btn-primary"><i class="fa fa-refresh"></i> Load Usage</button>
                    <button id="btnGenerate" class="btn-success" style="margin-left: 5px;"><i class="fa fa-cogs"></i> Generate by WO</button>
                </div>
            </div>

            <!-- Blok Pencarian Global FG Target (Independen) -->
            <div style="border-left: 2px solid #ccc; padding-left: 20px;">
                <div class="form-group">
                    <label style="color:#d32f2f;"><i class="fa fa-search"></i> Find FG Target:</label>
                    <input type="text" id="txtSearchFg" size="22" placeholder="Ketik Item Code FG..." style="border: 1px solid #d32f2f; font-weight: bold; background-color:#fff8e1;" autocomplete="off">
                </div>
            </div>

        </div>
    </div>

    <!-- Kontainer Data Dinamis -->
    <div id="mainContainer" style="display:none;">
        <div class="panel">
            <div class="panel-title" style="color:#d32f2f;"><i class="fa fa-database"></i> Total Material Usage Summary</div>
            <div id="gridTotal"></div>
        </div>
        
        <div id="gridDetailContainer" style="margin-top: 20px;"></div>
    </div>

    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    
    <script>
        $(document).ready(function() {
            
            $('#cbTahun, #cbBulan').change(function() {
                $('#txtMatCode').val('');
                $('#txtMatName').val('');
                $('#txtSearchFg').val('');
                $('#mainContainer').fadeOut();
            });

            function escapeHtml(str) {
                if (str === null || str === undefined) return '';
                return String(str).replace(/[&<>"']/g, function(m) {
                    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[m];
                });
            }

            // AUTOCOMPLETE MAT & FG
            $('#txtMatCode').autocomplete({
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
                    $('#txtMatCode').val(ui.item.value);
                    $('#txtMatName').val(ui.item.name);
                    $('#txtSearchFg').val(''); 
                    return false;
                }
            }).autocomplete("instance")._renderItem = function(ul, item) {
                return $("<li>")
                    .append("<div><b>" + escapeHtml(item.value) + "</b><br><span style='color:#666; font-size:10px;'>" + escapeHtml(item.name) + "</span></div>")
                    .appendTo(ul);
            };

            $('#txtSearchFg').autocomplete({
                minLength: 2,
                source: function(request, response) {
                    $.ajax({
                        url: 'ajax_autocomplete_fg.php',
                        type: 'GET',
                        dataType: 'json',
                        data: { term: request.term, tahun: $('#cbTahun').val(), bulan: $('#cbBulan').val() },
                        success: function(data) { response(data); },
                        error: function() { response([]); }
                    });
                },
                select: function(event, ui) {
                    $('#txtSearchFg').val(ui.item.value);
                    var selectedFgCode = ui.item.value;
                    var requiredMaterials = ui.item.req_mat; 
                    
                    var targetWorkspace = $('.workspace').filter(function() {
                        return $(this).find('input[value="' + escapeHtml(selectedFgCode) + '"]').length > 0;
                    });

                    if (targetWorkspace.length > 0) {
                        $('html, body').animate({ scrollTop: targetWorkspace.offset().top - 15 }, 500);
                        targetWorkspace.css({'box-shadow': '0 0 12px 3px #ff9800', 'transition': 'box-shadow 0.5s ease'});
                        setTimeout(function() { targetWorkspace.css('box-shadow', 'none'); }, 3000);
                    } else {
                        if(requiredMaterials) {
                            $('#txtMatCode').val(requiredMaterials);
                            window.autoScrollFg = selectedFgCode;
                            $('#btnLoad').trigger('click');
                        } else {
                            alert("Material Code tidak ditemukan untuk FG " + selectedFgCode);
                        }
                    }
                    return false;
                }
            }).autocomplete("instance")._renderItem = function(ul, item) {
                return $("<li>")
                    .append("<div><b style='color:#d32f2f;'>" + escapeHtml(item.value) + "</b><br><span style='color:#666; font-size:10px;'>" + escapeHtml(item.name) + "</span></div>")
                    .appendTo(ul);
            };

            // FUNGSI BUILD GRID HTML (SUDAH DIPERBARUI DENGAN WARNA PINK SABTU & MINGGU)
            function buildGridHtml(dataRows) {
                // Ambil tahun dan bulan dari dropdown saat ini (dikurangi 1 karena index bulan di JS dimulai dari 0)
                var selectedTahun = parseInt($('#cbTahun').val());
                var selectedBulan = parseInt($('#cbBulan').val()) - 1; 

                var html = '<div class="table-container"><table class="grid-table"><thead><tr>';
                html += '<th class="desc-col">Description</th><th class="gtotal-col">Grand Total</th>';
                
                // Header Tabel (1-31)
                for(var i=1; i<=31; i++) { 
                    var dObj = new Date(selectedTahun, selectedBulan, i);
                    // Cek jika bulannya cocok (mencegah overflow hari) DAN harinya 0 (Minggu) atau 6 (Sabtu)
                    var isWeekend = (dObj.getMonth() === selectedBulan && (dObj.getDay() === 0 || dObj.getDay() === 6));
                    var weekendClass = isWeekend ? ' weekend-pink' : '';
                    
                    html += '<th class="day-col' + weekendClass + '">' + i + '</th>'; 
                }
                html += '</tr></thead><tbody>';

                // Isi Tabel (Data Rows)
                $.each(dataRows, function(idx, row) {
                    html += '<tr>';
                    html += '<td class="desc-col">' + escapeHtml(row.DESC_PROD) + '</td>';
                    
                    html += '<td class="gtotal-col">' + parseFloat(row.G_TOTAL || 0).toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:2}) + '</td>';
                    
                    for(var d=1; d<=31; d++) {
                        // Cek ulang weekend untuk cell data
                        var dObj = new Date(selectedTahun, selectedBulan, d);
                        var isWeekend = (dObj.getMonth() === selectedBulan && (dObj.getDay() === 0 || dObj.getDay() === 6));
                        var weekendClass = isWeekend ? ' weekend-pink' : '';

                        var val = parseFloat(row['D'+d] || 0);
                        html += '<td class="day-col' + weekendClass + '">' + (val !== 0 ? val.toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:2}) : '0') + '</td>';
                    }
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
                return html;
            }

            // =========================================================================
            // MANAJEMEN SUMMARY AKUMULASI GLOBAL
            // =========================================================================
            var globalTotal = [];
            function resetGlobalTotal() {
                globalTotal = [
                    { DESC_PROD: 'Used Plan', G_TOTAL: 0 },
                    { DESC_PROD: 'Used Act', G_TOTAL: 0 },
                    { DESC_PROD: 'Supply Act', G_TOTAL: 0 }
                ];
                for(var i=0; i<3; i++) {
                    for(var d=1; d<=31; d++) globalTotal[i]['D'+d] = 0;
                }
            }

            function accumulateGlobalTotal(newTotalData) {
                $.each(newTotalData, function(idx, newRow) {
                    var targetRow = globalTotal.find(function(r) { return r.DESC_PROD === newRow.DESC_PROD; });
                    if (targetRow) {
                        targetRow.G_TOTAL += parseFloat(newRow.G_TOTAL) || 0;
                        for(var d=1; d<=31; d++) {
                            targetRow['D'+d] += parseFloat(newRow['D'+d]) || 0;
                        }
                    }
                });
            }

            // =========================================================================
            // ACTION: Generate by WO
            // =========================================================================
            $('#btnGenerate').click(function() {
                var tahun = $('#cbTahun').val();
                var bulan = $('#cbBulan').val();
                var matCode = $.trim($('#txtMatCode').val());
                var msg = matCode ? "Material Code: " + matCode : "SEMUA MATERIAL";

                if (!confirm("Konfirmasi Eksekusi:\nTindakan ini akan meng-generate data untuk " + msg + " berdasarkan Work Order (WO) periode " + bulan + "-" + tahun + ".\n\nLanjutkan?")) {
                    return;
                }

                var btn = $(this);
                btn.html('<i class="fa fa-spinner fa-spin"></i> Generating...').prop('disabled', true);
                
                $.ajax({
                    url: 'ajax_generate_use_wo.php',
                    type: 'POST',
                    data: { tahun: tahun, bulan: bulan, mat_code: matCode },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            alert(res.message);
                            $('#btnLoad').trigger('click'); 
                        } else {
                            alert('Gagal: ' + res.message);
                        }
                    },
                    error: function(xhr) { alert("Koneksi server terputus/Timeout."); },
                    complete: function() { btn.html('<i class="fa fa-cogs"></i> Generate by WO').prop('disabled', false); }
                });
            });

            // =========================================================================
            // ACTION: SEQUENTIAL LOAD (BERJENJANG)
            // =========================================================================
            function loadSequentialItem(index, itemList, tahun, bulan, btn) {
                if (index >= itemList.length) {
                    btn.html('<i class="fa fa-refresh"></i> Load Usage').prop('disabled', false);
                    $('#txtMatName').val('SELESAI DIMUAT (' + itemList.length + ' Material)');
                    return;
                }

                var currentMatCode = itemList[index];
                btn.html('<i class="fa fa-spinner fa-spin"></i> Loading ' + (index + 1) + ' dari ' + itemList.length + '...');
                $('#txtMatName').val('Memuat... (' + currentMatCode + ')');

                $.ajax({
                    url: 'ajax_load_mat_use.php',
                    type: 'GET',
                    dataType: 'json',
                    data: { tahun: tahun, bulan: bulan, mat_code: currentMatCode },
                    success: function(res) {
                        if (res.status === 'success') {
                            
                            // 1. Akumulasi Total Header
                            accumulateGlobalTotal(res.data_total);
                            $('#gridTotal').html(buildGridHtml(globalTotal)); 
                            
                            // 2. Susun HTML Workspace Detail
                            var detailHtml = '';
                            $.each(res.data_detail, function(idx, fg) {
                                detailHtml += '<div class="workspace">';
                                detailHtml += '  <div class="workspace-top">';
                                
                                detailHtml += '    <div class="left-pane"><div class="panel" style="margin-bottom:0; padding:10px;">';
                                detailHtml += '      <div class="panel-title" style="color:#0056b3;"><i class="fa fa-cube"></i> FG Target</div>';
                                detailHtml += '      <table class="pref-table">';
                                detailHtml += '        <tr><td class="label-cell">Mat. Code</td><td><input type="text" value="' + escapeHtml(fg.MAT_CODE) + '" readonly style="background:#e3f2fd; color:#0d47a1; font-weight:bold;"></td></tr>';
                                detailHtml += '        <tr><td class="label-cell">Item No</td><td><input type="text" value="' + escapeHtml(fg.FG_ITEM_NO) + '" readonly style="color:#333;"></td></tr>';
                                detailHtml += '        <tr><td class="label-cell">Item Code</td><td><input type="text" value="' + escapeHtml(fg.FG_CODE) + '" readonly></td></tr>';
                                detailHtml += '        <tr><td class="label-cell">Item Name</td><td><input type="text" value="' + escapeHtml(fg.FG_NAME) + '" readonly style="font-size:10px;"></td></tr>';
                                detailHtml += '        <tr><td class="label-cell">WO No</td><td><input type="text" value="' + escapeHtml(fg.WO_NUMBER) + '" readonly style="background:#e8f5e9; color:#1b5e20;"></td></tr>';
                                detailHtml += '        <tr><td class="label-cell">WO Qty</td><td><input type="text" value="' + parseFloat(fg.WO_QTY || 0).toLocaleString() + '" readonly style="background:#e8f5e9; color:#1b5e20;"></td></tr>';
                                detailHtml += '        <tr><td class="label-cell">Net Weight</td><td><input type="text" value="' + escapeHtml(fg.NET_WEIGHT) + '" readonly style="background:#fff3cd; color:#856404;"></td></tr>';
                                detailHtml += '      </table>';
                                detailHtml += '      <button class="btn-primary" style="margin-top:10px; width:100%;"><i class="fa fa-refresh"></i> Re-Calc FG</button>';
                                detailHtml += '      <button class="btn-secondary btn-print-mat" style="margin-top:5px; width:100%;"><i class="fa fa-print"></i> Print Data Ini</button>';
                                detailHtml += '    </div></div>';
                                
                                detailHtml += '    <div class="right-pane"><div class="panel" style="margin-bottom:0; padding:0; border:none; box-shadow:none;">';
                                detailHtml += buildGridHtml(fg.rows);
                                detailHtml += '    </div></div>';
                                
                                detailHtml += '  </div></div>';
                            });
                            
                            $('#gridDetailContainer').append(detailHtml);
                        }
                        
                        // Lanjutkan Tarik Material Berikutnya
                        loadSequentialItem(index + 1, itemList, tahun, bulan, btn);
                    },
                    error: function(xhr) { 
                        // Jika gagal timeout di satu item, abaikan dan lanjut ke item berikutnya
                        console.log('Gagal menarik material: ' + currentMatCode);
                        loadSequentialItem(index + 1, itemList, tahun, bulan, btn); 
                    }
                });
            }

            $('#btnLoad').click(function() {
                var btn = $(this);
                var tahun = $('#cbTahun').val();
                var bulan = $('#cbBulan').val();
                var matCode = $.trim($('#txtMatCode').val());

                btn.prop('disabled', true);
                $('#mainContainer').hide();
                $('#gridDetailContainer').empty();
                $('#txtSearchFg').val(''); 

                // 1. JIKA INPUT KOSONG (TARIK SEMUA BERJENJANG)
                if (matCode === '') {
                    if (!confirm("Konfirmasi: Material Code kosong.\nSistem akan menarik SEMUA Data Usage secara bertahap.\n\nLanjutkan?")) {
                        btn.prop('disabled', false);
                        return;
                    }

                    btn.html('<i class="fa fa-spinner fa-spin"></i> Menyiapkan Antrean...');
                    
                    $.ajax({
                        url: 'ajax_load_mat_use.php',
                        type: 'GET',
                        dataType: 'json',
                        data: { action: 'get_item_list', tahun: tahun, bulan: bulan },
                        success: function(res) {
                            if (res.status === 'success') {
                                var itemList = res.data;
                                if (itemList.length === 0) {
                                    $('#gridDetailContainer').html('<div class="panel" style="color:#cc0000; font-weight:600;"><i class="fa fa-exclamation-triangle"></i> Tidak ada data Material Usage ditemukan.</div>');
                                    $('#mainContainer').fadeIn();
                                    btn.html('<i class="fa fa-refresh"></i> Load Usage').prop('disabled', false);
                                    return;
                                }

                                resetGlobalTotal(); // Reset summary banner atas ke angka 0
                                $('#mainContainer').show();
                                
                                // Mulai antrean pemanggilan dari Index ke-0
                                loadSequentialItem(0, itemList, tahun, bulan, btn);
                            } else {
                                alert("Gagal menyusun antrean material.");
                                btn.html('<i class="fa fa-refresh"></i> Load Usage').prop('disabled', false);
                            }
                        },
                        error: function() {
                            alert("Terjadi kesalahan jaringan.");
                            btn.html('<i class="fa fa-refresh"></i> Load Usage').prop('disabled', false);
                        }
                    });

                // 2. JIKA INPUT DIISI (TARIK 1 MATERIAL SEPERTI BIASA)
                } else {
                    btn.html('<i class="fa fa-spinner fa-spin"></i> Loading...');
                    resetGlobalTotal();

                    $.ajax({
                        url: 'ajax_load_mat_use.php',
                        type: 'GET',
                        data: { tahun: tahun, bulan: bulan, mat_code: matCode },
                        dataType: 'json',
                        success: function(res) {
                            if (res.status === 'success') {
                                $('#txtMatName').val(res.mat_name);
                                
                                accumulateGlobalTotal(res.data_total);
                                $('#gridTotal').html(buildGridHtml(globalTotal)); 
                                
                                var detailHtml = '';
                                $.each(res.data_detail, function(idx, fg) {
                                    detailHtml += '<div class="workspace">';
                                    detailHtml += '  <div class="workspace-top">';
                                    detailHtml += '    <div class="left-pane"><div class="panel" style="margin-bottom:0; padding:10px;">';
                                    detailHtml += '      <div class="panel-title" style="color:#0056b3;"><i class="fa fa-cube"></i> FG Target</div>';
                                    detailHtml += '      <table class="pref-table">';
                                    detailHtml += '        <tr><td class="label-cell">Mat. Code</td><td><input type="text" value="' + escapeHtml(fg.MAT_CODE) + '" readonly style="background:#e3f2fd; color:#0d47a1; font-weight:bold;"></td></tr>';
                                    detailHtml += '        <tr><td class="label-cell">Item No</td><td><input type="text" value="' + escapeHtml(fg.FG_ITEM_NO) + '" readonly style="color:#333;"></td></tr>';
                                    detailHtml += '        <tr><td class="label-cell">Item Code</td><td><input type="text" value="' + escapeHtml(fg.FG_CODE) + '" readonly></td></tr>';
                                    detailHtml += '        <tr><td class="label-cell">Item Name</td><td><input type="text" value="' + escapeHtml(fg.FG_NAME) + '" readonly style="font-size:10px;"></td></tr>';
                                    detailHtml += '        <tr><td class="label-cell">WO No</td><td><input type="text" value="' + escapeHtml(fg.WO_NUMBER) + '" readonly style="background:#e8f5e9; color:#1b5e20;"></td></tr>';
                                    detailHtml += '        <tr><td class="label-cell">WO Qty</td><td><input type="text" value="' + parseFloat(fg.WO_QTY || 0).toLocaleString() + '" readonly style="background:#e8f5e9; color:#1b5e20;"></td></tr>';
                                    detailHtml += '        <tr><td class="label-cell">Net Weight</td><td><input type="text" value="' + escapeHtml(fg.NET_WEIGHT) + '" readonly style="background:#fff3cd; color:#856404;"></td></tr>';
                                    detailHtml += '      </table>';
                                    detailHtml += '      <button class="btn-primary" style="margin-top:10px; width:100%;"><i class="fa fa-refresh"></i> Re-Calc FG</button>';
                                    detailHtml += '      <button class="btn-secondary btn-print-mat" style="margin-top:5px; width:100%;"><i class="fa fa-print"></i> Print Data Ini</button>';
                                    detailHtml += '    </div></div>';
                                    detailHtml += '    <div class="right-pane"><div class="panel" style="margin-bottom:0; padding:0; border:none; box-shadow:none;">';
                                    detailHtml += buildGridHtml(fg.rows);
                                    detailHtml += '    </div></div>';
                                    detailHtml += '  </div></div>';
                                });
                                
                                $('#gridDetailContainer').html(detailHtml);
                                $('#mainContainer').fadeIn();

                                if (typeof window.autoScrollFg !== 'undefined' && window.autoScrollFg !== null) {
                                    $('#txtSearchFg').val(window.autoScrollFg);
                                    setTimeout(function() {
                                        var newTarget = $('.workspace').filter(function() {
                                            return $(this).find('input[value="' + escapeHtml(window.autoScrollFg) + '"]').length > 0;
                                        });

                                        if (newTarget.length > 0) {
                                            $('html, body').animate({ scrollTop: newTarget.offset().top - 15 }, 500);
                                            newTarget.css({'box-shadow': '0 0 12px 3px #ff9800', 'transition': 'box-shadow 0.5s ease'});
                                            setTimeout(function() { newTarget.css('box-shadow', 'none'); }, 3000);
                                        }
                                        window.autoScrollFg = null; 
                                    }, 150); 
                                }
                            } else {
                                alert('Sistem Gagal Memuat Data: ' + res.message);
                            }
                        },
                        error: function(xhr) { alert("Koneksi server terputus."); },
                        complete: function() { btn.html('<i class="fa fa-refresh"></i> Load Usage').prop('disabled', false); }
                    });
                }
            });

            $('#gridDetailContainer').on('click', '.btn-print-mat', function() {
                var currentWorkspace = $(this).closest('.workspace');
                $('.workspace').addClass('no-print');
                currentWorkspace.removeClass('no-print');
                window.print();
                $('.workspace').removeClass('no-print');
            });

        });
    </script>
</body>
</html>
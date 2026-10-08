<?php
// view_matrix_ovh.php
// Kompatibilitas: PHP 5.4 & SQL Server 2008
if (session_status() == PHP_SESSION_NONE) { 
    session_start(); 
}

// --- LOAD CONFIG & KONEKSI ---
$configPath = __DIR__ . "/../config/global.php";
if (file_exists($configPath)) { 
    require_once $configPath; 
}

$currentYear = (int)date('Y');
$currentMonth = (int)date('n');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Matrix Over Hour - Molding (Std 25K Shots)</title>
    <!-- Library CSS FontAwesome & jQuery UI (Legacy Support) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; background-color: #f4f6f9; margin: 0; padding: 15px; }
        .panel { background-color: #fff; border: 1px solid #d1d5db; border-radius: 4px; padding: 15px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .panel-title { font-weight: bold; color: #0056b3; margin-bottom: 15px; border-bottom: 2px solid #0056b3; padding-bottom: 6px; font-size: 14px; }
        
        /* Form & Input */
        .form-group { display: inline-block; margin-right: 15px; vertical-align: middle; }
        label { font-weight: bold; margin-right: 5px; color: #333; }
        select, input[type="text"] { padding: 4px 8px; border: 1px solid #ccc; border-radius: 3px; font-size: 12px; height: 28px; outline: none; box-sizing: border-box; }
        button { padding: 4px 15px; font-weight: bold; cursor: pointer; border: none; border-radius: 3px; height: 28px; color: #fff; background-color: #0056b3; transition: background 0.2s;}
        button:hover { background-color: #004494; }
        button:disabled { background-color: #8bb3df; cursor: not-allowed; }
        .btn-clear { background-color: #dc3545; padding: 4px 10px; margin-left: -5px; }
        .btn-report { background-color: #28a745; }
        .btn-report:hover { background-color: #218838; }
        
        /* Grid Table Structure & Sticky Columns */
        .table-container { width: 100%; overflow: auto; max-height: 60vh; border: 1px solid #bbb; background-color: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        table.grid-table { border-collapse: separate; border-spacing: 0; white-space: nowrap; table-layout: fixed; min-width: 100%; }
        table.grid-table th, table.grid-table td { border-right: 1px solid #ccc; border-bottom: 1px solid #ccc; padding: 6px 8px; box-sizing: border-box; }
        table.grid-table th { border-top: 1px solid #ccc; background-color: #e2e8f0; font-weight: bold; position: sticky; top: 0; z-index: 10; text-align: center; }
        table.grid-table td:first-child, table.grid-table th:first-child { border-left: 1px solid #ccc; }
        
        .fix-mc { position: sticky; left: 0; width: 75px; min-width: 75px; background-color: #f8fafc; z-index: 5; font-weight: bold; text-align: center; }
        .fix-item { position: sticky; left: 75px; width: 220px; min-width: 220px; background-color: #f8fafc; z-index: 5; font-weight: bold; border-right: 1px solid #ccc;}
        .fix-lastshoot { position: sticky; left: 295px; width: 90px; min-width: 90px; background-color: #f8fafc; z-index: 5; font-weight: bold; text-align: right; border-right: 2px solid #ccc; }
        .fix-desc { position: sticky; left: 385px; width: 120px; min-width: 120px; background-color: #f1f5f9; z-index: 5; font-weight: bold; }
        .fix-total { position: sticky; left: 505px; width: 75px; min-width: 75px; background-color: #e2e8f0; z-index: 5; font-weight: bold; text-align: right; border-right: 2px solid #64748b !important; }
        table.grid-table th.fix-mc, table.grid-table th.fix-item, table.grid-table th.fix-lastshoot, table.grid-table th.fix-desc, table.grid-table th.fix-total { z-index: 15; background-color: #cbd5e1; }
        
        .day-col { width: 55px; min-width: 55px; text-align: right; }
        .group-header { background-color: #1e293b !important; color: #fff !important; font-weight: bold; text-align: left !important; font-size: 12px; position: sticky; left: 0; z-index: 6; }
        
        /* Row Colors & Target Alert */
        .ovh-alert { background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold; box-shadow: inset 0 0 4px #f87171; }
        .row-plan td:not(.fix-mc):not(.fix-item):not(.fix-lastshoot):not(.fix-desc):not(.fix-total):not(.ovh-alert):not(.reach-target) { color: #0284c7; }
        .row-act td:not(.fix-mc):not(.fix-item):not(.fix-lastshoot):not(.fix-desc):not(.fix-total):not(.ovh-alert) { color: #166534; background-color: #f0fdf4; }
        
        /* Highlight 25K Shots Target */
        .reach-target { background-color: #fef08a !important; color: #b45309 !important; font-weight: bold; border: 2px solid #eab308 !important; box-shadow: inset 0 0 5px rgba(234, 179, 8, 0.5); }
        .reach-target::after { content: " 🔧"; font-size: 10px; }

        /* Hyperlink Modal */
        .ovh-link { color: #0056b3; text-decoration: none; font-weight: bold; border-bottom: 1px dashed #0056b3; cursor: pointer; display: block; width: 100%; height: 100%; }
        .ovh-link:hover { color: #d9534f; border-bottom: 1px solid #d9534f; }
        .reach-target .ovh-link { color: #b45309; border-bottom-color: #b45309; }

        /* Inline Input Last Shoot */
        .edit-last-shoot { width: 100%; box-sizing: border-box; text-align: right; border: 1px solid #ccc; padding: 4px; background: #fff; border-radius: 3px; font-size: 11px; font-weight: bold; color: #856404; }
        .edit-success { background-color: #d4edda !important; border-color: #28a745 !important; color: #155724 !important; }
        .edit-error { background-color: #f8d7da !important; border-color: #dc3545 !important; }
        
        /* UI Modal */
        .detail-grid { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
        .detail-grid th, .detail-grid td { border: 1px solid #ccc; padding: 6px; text-align: center; }
        .detail-grid th { background-color: #e2e8f0; }
        .ui-autocomplete { z-index: 9999 !important; font-size: 11px; max-height: 200px; overflow-y: auto; overflow-x: hidden; }
    </style>
</head>
<body>

<!-- ================= PANEL FILTER ================= -->
<div class="panel">
    <div class="panel-title"><i class="fa fa-cogs"></i> Matrik Jadwal & Aktual Over Hour (Limit 25.000 Shots)</div>
    
    <div class="form-group">
        <label for="inputCustomer">Customer:</label>
        <input type="text" id="inputCustomer" placeholder="Semua Customer (Ketik...)" style="width: 250px;">
        <input type="hidden" id="cust_code" value="">
        <button type="button" id="btnClearCust" class="btn-clear" title="Clear Customer"><i class="fa fa-times"></i></button>
    </div>

    <div class="form-group">
        <label for="cbTahun">Tahun:</label>
        <select id="cbTahun">
            <?php for ($y = $currentYear - 2; $y <= $currentYear + 1; $y++) {
                $sel = ($y === $currentYear) ? 'selected' : '';
                echo "<option value=\"$y\" $sel>$y</option>";
            } ?>
        </select>
    </div>
    
    <div class="form-group">
        <label for="cbBulan">Bulan:</label>
        <select id="cbBulan">
            <?php 
            $namaBulan = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
            for ($m = 1; $m <= 12; $m++) {
                $sel = ($m === $currentMonth) ? 'selected' : '';
                echo "<option value=\"$m\" $sel>" . $namaBulan[$m-1] . " ($m)</option>";
            } ?>
        </select>
    </div>
    
    <button id="btnLoadMatrix"><i class="fa fa-search"></i> Load Matrix Data</button>
    
    <!-- Tombol Pintasan ke Halaman Report Plan & Actual OVH -->
    <a href="report_ovh_per_machine.php" target="_blank" style="text-decoration: none; margin-left: 5px;">
        <button type="button" class="btn-report"><i class="fa fa-file-text-o"></i> Report Plan & Actual OVH</button>
    </a>
    
    <!-- Legend Info -->
    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #ccc;">
        <span style="color: #666; font-style: italic; margin-right: 20px;">* Ketik angka di kolom Last Shoot lalu tekan <b>Enter</b> untuk menyimpan.</span>
        <span style="display:inline-block; width:15px; height:15px; background-color:#fef08a; border: 1px solid #eab308; vertical-align:middle; margin-right:5px;"></span>
        <b>Jadwal OVH/Maintenance (Tercapai kelipatan 25.000)</b>
        <span style="margin-left: 20px; color: #0056b3; font-weight:bold;">* Centang kotak kecil bertuliskan 'Actual' di setiap kolom tanggal pada baris Actual untuk menandai tanggal pelaksanaan OVH.</span>
    </div>
</div>

<!-- ================= SUMMARY ALERT HEADER ================= -->
<div id="summaryAlertContainer" style="display:none; margin-bottom: 15px;">
    <div class="alert" style="background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 12px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <span style="font-weight: bold; font-size: 13px;" id="summaryAlertText">
            <!-- Teks di-generate via jQuery -->
        </span>
        <div>
            <button type="button" id="btnFilterOvh" style="background-color: #d35400; padding: 6px 12px; color: #fff; border:none; border-radius: 3px; cursor:pointer; font-weight:bold;"><i class="fa fa-filter"></i> Lihat Data OVH</button>
            <button type="button" id="btnResetFilter" style="background-color: #fff; color: #d35400; padding: 6px 12px; border: 1px solid #d35400; border-radius: 3px; cursor:pointer; font-weight:bold; display:none;"><i class="fa fa-refresh"></i> Reset Filter</button>
        </div>
    </div>
</div>

<!-- ================= AREA MATRIKS GRID ================= -->
<div class="table-container">
    <table class="grid-table" id="tblMatrix">
        <thead>
            <tr id="tableHeaderRow">
                <th class="fix-mc">Mesin</th>
                <th class="fix-item">Item Detail</th>
                <th class="fix-lastshoot">Last Shoot</th>
                <th class="fix-desc">Keterangan</th>
                <th class="fix-total">Total Shots</th>
                <!-- Kolom Tanggal akan digenerate via jQuery -->
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="36" style="text-align:center; padding: 40px; color:#666;">
                    Silakan pilih periode Tahun & Bulan, lalu klik <b>Load Matrix Data</b>.
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- ================= MODAL JQUERY UI DETAIL DOKUMEN ================= -->
<div id="modalDetailOvh" title="Rincian Jadwal / Produksi" style="display:none;">
    <div style="margin-bottom: 10px; background: #f8fafc; padding: 10px; border: 1px solid #cbd5e1; border-radius: 3px;">
        <table style="width:100%; font-size:12px;">
            <tr>
                <td width="60%"><b>Mesin:</b> <span id="lblDetailMc" style="color:#0056b3;"></span></td>
                <td width="40%" align="right"><b>Tanggal:</b> <span id="lblDetailDate" style="color:#0056b3;"></span></td>
            </tr>
            <tr>
                <td colspan="2"><b>Item:</b> <span id="lblDetailItem"></span></td>
            </tr>
        </table>
    </div>
    
    <div id="targetOvhAlert" style="display:none; padding:8px; background:#fef08a; border:1px solid #eab308; color:#b45309; font-weight:bold; margin-bottom: 10px; text-align:center;">
        <i class="fa fa-wrench"></i> TARGET JADWAL OVH / MAINTENANCE TERCAPAI PADA HARI INI!
    </div>

    <table class="detail-grid">
        <thead>
            <tr>
                <th>No. Dokumen (WO/PS)</th>
                <th>Qty Pcs</th>
                <th>Cavity</th>
                <th>Total Shots</th>
            </tr>
        </thead>
        <tbody id="tbodyDetailOvh">
            <!-- Data Detail Di-load via AJAX -->
        </tbody>
    </table>
</div>

<!-- JQuery 1.12.4 (Legacy yang aman) & UI -->
<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
$(document).ready(function() {
    
    // --- 1. INISIALISASI KOMPONEN UI ---
    $("#modalDetailOvh").dialog({
        autoOpen: false, modal: true, width: 600,
        buttons: { "Tutup": function() { $(this).dialog("close"); } }
    });

    $("#inputCustomer").autocomplete({
        source: function(request, response) {
            $.ajax({
                url: "ajax_customer.php", type: "GET", dataType: "json",
                data: { term: request.term }, success: function(data) { response(data); }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            $("#inputCustomer").val(ui.item.label);
            $("#cust_code").val(ui.item.value);
            return false;
        }
    }).on("input", function() { if ($(this).val() === "") { $("#cust_code").val(""); } });

    $('#btnClearCust').click(function(){ $('#inputCustomer, #cust_code').val(''); $('#inputCustomer').focus(); });

    // --- 2. SECURITY & HELPER FUNCTIONS ---
    function escapeHtml(text) {
        if (text == null) return '';
        return text.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
    function formatMatrixNumber(num) {
        if(num === 0 || num === "0" || num === null || isNaN(num)) return "-";
        return parseFloat(num).toLocaleString('id-ID');
    }
    function getDaysInMonth(month, year) { return new Date(year, month, 0).getDate(); }

    // --- 3. LOAD DATA & LOGIKA KALKULASI FRONT-END ---
    $('#btnLoadMatrix').click(function() {
        var btn = $(this);
        var thn = parseInt($('#cbTahun').val());
        var bln = parseInt($('#cbBulan').val());
        var ccode = $('#cust_code').val(); 
        var totalDays = getDaysInMonth(bln, thn);
        var stdLimit = 25000; 
        
        var theadTr = $('#tableHeaderRow');
        var tbody = $('#tblMatrix tbody');
        
        // Render Header
        var headHtml = '<th class="fix-mc">Mesin</th><th class="fix-item">Item Detail</th>' +
                       '<th class="fix-lastshoot" title="Klik lalu Enter untuk Update">Last Shoot <i class="fa fa-pencil"></i></th>' +
                       '<th class="fix-desc">Keterangan</th><th class="fix-total">Total Shots</th>';
        for(var i = 1; i <= totalDays; i++) { headHtml += '<th class="day-col">' + i + '</th>'; }
        theadTr.html(headHtml);

        // Reset UI Status
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading...');
        tbody.html('<tr><td colspan="' + (totalDays + 5) + '" style="text-align:center; padding:40px;"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Sedang memproses kalkulasi matrix...</td></tr>');
        $('#summaryAlertContainer').slideUp();
        $('#btnResetFilter').hide(); $('#btnFilterOvh').show(); 
        
        $.ajax({
            url: 'ajax_get_matrix_ovh.php', type: 'POST', dataType: 'json',
            data: { tahun: thn, bulan: bln, cust_code: ccode },
            success: function(res) {
                if(res.status === 'success') {
                    var html = '';
                    var hasData = false;
                    var countItemOvh = 0; 
                    
                    $.each(res.data, function(custName, items) {
                        hasData = true;
                        
                        html += '<tr class="group-header-row">';
                        html += '<td colspan="' + (totalDays + 5) + '" class="group-header"><i class="fa fa-building"></i> CUSTOMER: ' + escapeHtml(custName) + '</td>';
                        html += '</tr>';
                        
                        $.each(items, function(itemKey, row) {
                            var sumPlanOvh = 0, sumActOvh = 0;
                            var tdPlan = '', tdAct = '';
                            
                            var initialLastShoot = parseFloat(row.last_shoot) || 0;
                            
                            var cumulativePlan = initialLastShoot;
                            var nextTargetPlan = (Math.floor(initialLastShoot / stdLimit) + 1) * stdLimit;
                            
                            var cumulativeAct = initialLastShoot;
                            var nextTargetAct = (Math.floor(initialLastShoot / stdLimit) + 1) * stdLimit;
                            
                            var hasReachTargetThisItem = false;
                            
                            for (var d = 1; d <= totalDays; d++) {
                                var dPlan = (row.days && row.days[d]) ? parseFloat(row.days[d].plan_ovh) || 0 : 0;
                                var dAct  = (row.days && row.days[d]) ? parseFloat(row.days[d].act_ovh) || 0 : 0;
                                
                                sumPlanOvh += dPlan;
                                sumActOvh += dAct;
                                
                                var isReachTargetPlan = false;
                                if (dPlan > 0) {
                                    cumulativePlan += dPlan; 
                                    if (cumulativePlan >= nextTargetPlan) {
                                        isReachTargetPlan = true;
                                        hasReachTargetThisItem = true;
                                        nextTargetPlan = (Math.floor(cumulativePlan / stdLimit) + 1) * stdLimit;
                                    }
                                }
                                
                                var isReachTargetAct = false;
                                if (dAct > 0) {
                                    cumulativeAct += dAct; 
                                    if (cumulativeAct >= nextTargetAct) {
                                        isReachTargetAct = true;
                                        hasReachTargetThisItem = true;
                                        nextTargetAct = (Math.floor(cumulativeAct / stdLimit) + 1) * stdLimit;
                                    }
                                }
                                
                                var clsPlan = isReachTargetPlan ? 'reach-target' : (dPlan > stdLimit ? 'ovh-alert' : '');
                                var clsAct  = isReachTargetAct  ? 'reach-target' : (dAct  > stdLimit ? 'ovh-alert' : '');
                                
                                var titlePlan = isReachTargetPlan ? 'Target 25K Tercapai! (Plan Kumulatif: ' + formatMatrixNumber(cumulativePlan) + ')' : 'Plan Tgl ' + d;
                                var titleAct  = isReachTargetAct  ? 'Target 25K Tercapai! (Actual Kumulatif: ' + formatMatrixNumber(cumulativeAct) + ')' : 'Actual Tgl ' + d;
                                
                                var linkPlan = (dPlan > 0) ? '<a class="ovh-link" data-type="plan" data-mc="'+escapeHtml(row.mc_no)+'" data-item="'+escapeHtml(row.item_code)+'" data-day="'+d+'" data-reach="'+(isReachTargetPlan ? '1':'0')+'">' + formatMatrixNumber(dPlan) + '</a>' : '-';
                                
                                // Checkbox penanda tanggal aktual OVH di SEMUA kolom tanggal baris Actual (baik ada data maupun kosong)
                                var isCheckedDate = (row.checked_dates && row.checked_dates[d] == '1') ? 'checked' : '';
                                var valDisplay = (dAct > 0) ? formatMatrixNumber(dAct) : '-';
                                var dateCheckbox = '<br><label style="font-size:9px; color:#166534; cursor:pointer;" title="Centang tanggal aktual pelaksanaan OVH"><input type="checkbox" class="chk-ovh-date" data-mc="'+escapeHtml(row.mc_no)+'" data-item="'+escapeHtml(row.item_code)+'" data-day="'+d+'" ' + isCheckedDate + '> Actual</label>';
                                
                                var linkAct = '<a class="ovh-link" data-type="act" data-mc="'+escapeHtml(row.mc_no)+'" data-item="'+escapeHtml(row.item_code)+'" data-day="'+d+'" data-reach="'+(isReachTargetAct ? '1':'0')+'">' + valDisplay + '</a>' + dateCheckbox;

                                tdPlan += '<td class="day-col ' + clsPlan + '" title="' + titlePlan + '">' + linkPlan + '</td>';
                                tdAct  += '<td class="day-col ' + clsAct  + '" title="' + titleAct + '">' + linkAct + '</td>';
                            }
                            
                            if (hasReachTargetThisItem) countItemOvh++;
                            
                            var grandTotalPlan = initialLastShoot + sumPlanOvh;
                            var grandTotalAct  = initialLastShoot + sumActOvh;
                            
                            var strItemDetail = '<b>' + escapeHtml(row.item_code) + '</b><br>' +
                                                '<span style="color:#0056b3;">' + escapeHtml(row.item_no) + '</span><br>' +
                                                '<small style="color:#555;">' + escapeHtml(row.item_name) + '</small><br>' +
                                                '<small style="color:#e67e22; font-weight:bold;">Cavity: ' + escapeHtml(row.plan_cavity) + '</small>';

                            var inputLastShoot = '<input type="text" class="edit-last-shoot" data-mc="' + escapeHtml(row.mc_no) + '" data-item="' + escapeHtml(row.item_code) + '" value="' + initialLastShoot + '">';

                            var trPlanClass = hasReachTargetThisItem ? 'row-plan row-has-ovh' : 'row-plan';
                            var trActClass  = hasReachTargetThisItem ? 'row-act act-has-ovh' : 'row-act';

                            html += '<tr class="'+trPlanClass+'">';
                            html += '<td class="fix-mc" rowspan="2">' + escapeHtml(row.mc_no) + '</td>';
                            html += '<td class="fix-item" rowspan="2" style="white-space:normal; line-height: 1.3;">' + strItemDetail + '</td>';
                            html += '<td class="fix-lastshoot" rowspan="2" style="vertical-align:middle; background-color:#fff3cd; padding:4px;">' + inputLastShoot + '</td>';
                            html += '<td class="fix-desc"><i class="fa fa-file-text-o"></i> Plan Shots</td>';
                            html += '<td class="fix-total" style="color:#0284c7; font-weight:bold;">' + formatMatrixNumber(grandTotalPlan) + '</td>' + tdPlan + '</tr>';
                            
                            html += '<tr class="'+trActClass+'">';
                            html += '<td class="fix-desc"><i class="fa fa-industry"></i> Actual Shots</td>';
                            html += '<td class="fix-total" style="color:#166534; font-weight:bold;">' + formatMatrixNumber(grandTotalAct) + '</td>' + tdAct + '</tr>';
                        });
                    });
                    
                    if(!hasData) {
                        html = '<tr><td colspan="' + (totalDays + 5) + '" style="text-align:center; padding: 30px; color:#d9534f;"><b>Tidak ada jadwal produksi untuk periode ini.</b></td></tr>';
                    }
                    tbody.html(html);

                    if(countItemOvh > 0) {
                        $('#summaryAlertText').html('⚠️ PERHATIAN: Terdapat <b>' + countItemOvh + ' Item/Mesin</b> yang mencapai jadwal Maintenance/OVH (Kelipatan 25.000 Shots) bulan ini!');
                        $('#summaryAlertContainer').slideDown();
                    }
                } else {
                    tbody.html('<tr><td colspan="' + (totalDays + 5) + '" style="text-align:center; padding: 20px; color:#d9534f;"><b>Gagal:</b> ' + escapeHtml(res.message) + '</td></tr>');
                }
            },
            error: function(xhr) { 
                console.log(xhr.responseText);
                tbody.html('<tr><td colspan="' + (totalDays + 5) + '" style="text-align:center; color:#d9534f; padding:20px;"><b>Error server. Cek console (F12).</b></td></tr>'); 
            },
            complete: function() { btn.prop('disabled', false).html('<i class="fa fa-search"></i> Load Matrix Data'); }
        });
    });

    // --- 4. LOGIKA FILTER DOM ---
    $('#btnFilterOvh').click(function() {
        $('#tblMatrix tbody tr').not('.group-header-row').hide();
        $('.row-has-ovh').show();
        $('.act-has-ovh').show();
        
        $('.group-header-row').each(function() {
            if ($(this).nextUntil('.group-header-row', '.row-has-ovh').length > 0) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });

        $(this).hide();
        $('#btnResetFilter').show();
    });

    $('#btnResetFilter').click(function() {
        $('#tblMatrix tbody tr').show();
        $(this).hide();
        $('#btnFilterOvh').show();
    });

    // --- 5. ENTER KEY: UPDATE LAST SHOOT ---
    $('#tblMatrix').on('keypress', '.edit-last-shoot', function(e) {
        if (e.which == 13) {
            e.preventDefault();
            var inputField = $(this);
            var macCode = inputField.data('mc'), itemCode = inputField.data('item'), valShoot = inputField.val();
            var thn = $('#cbTahun').val(), bln = $('#cbBulan').val();
            
            inputField.prop('disabled', true);
            
            $.ajax({
                url: 'ajax_save_last_shoot.php', type: 'POST', dataType: 'json',
                data: { tahun: thn, bulan: bln, mac_code: macCode, item_code: itemCode, last_shoot: valShoot },
                success: function(res) {
                    inputField.prop('disabled', false);
                    if(res.status === 'success') {
                        // Beri tanda warna hijau bahwa data berhasil disimpan
                        inputField.addClass('edit-success');
                        inputField.blur(); // Hilangkan kursor dari input setelah save
                        
                        setTimeout(function(){ 
                            inputField.removeClass('edit-success'); 
                        }, 2000);
                        
                        // $('#btnLoadMatrix').click(); <-- BARIS INI DIHAPUS AGAR TIDAK RELOAD
                    } else {
                        alert("Gagal: " + res.message);
                        inputField.addClass('edit-error');
                        setTimeout(function(){ inputField.removeClass('edit-error'); }, 2000);
                    }
                },
                error: function() {
                    inputField.prop('disabled', false).addClass('edit-error');
                    alert("Terjadi kesalahan koneksi saat menyimpan Last Shoot.");
                    setTimeout(function(){ inputField.removeClass('edit-error'); }, 2000);
                }
            });
        }
    });
	

    // --- 6. EVENT KLIK MODAL: RINCIAN DOKUMEN ---
    $('#tblMatrix').on('click', '.ovh-link', function(e) {
        e.preventDefault();
        var mcCode = $(this).data('mc');
        var itemCode = $(this).data('item');
        var day = $(this).data('day');
        var dataType = $(this).data('type'); 
        var isReach = $(this).data('reach');
        
        var thn = $('#cbTahun').val();
        var bln = $('#cbBulan').val();
        var txtDate = ("0" + day).slice(-2) + '/' + ("0" + bln).slice(-2) + '/' + thn;
        
        $('#lblDetailMc').text(mcCode);
        $('#lblDetailItem').text(itemCode);
        $('#lblDetailDate').text(txtDate + ' (' + (dataType === 'plan' ? 'Jadwal Plan' : 'Aktual Produksi') + ')');
        
        if(isReach == '1') { $('#targetOvhAlert').show(); } else { $('#targetOvhAlert').hide(); }

        $('#tbodyDetailOvh').html('<tr><td colspan="4"><i class="fa fa-spinner fa-spin"></i> Mengambil rincian data...</td></tr>');
        $("#modalDetailOvh").dialog("open");

        $.ajax({
            url: 'ajax_get_detail_ovh.php', type: 'POST', dataType: 'json',
            data: { tahun: thn, bulan: bln, hari: day, mc_code: mcCode, item_code: itemCode, tipe: dataType },
            success: function(res) {
                if(res.status === 'success' && res.data.length > 0) {
                    var htmlTr = '';
                    $.each(res.data, function(idx, row) {
                        htmlTr += '<tr>';
                        htmlTr += '<td>' + escapeHtml(row.DOC_NO) + '</td>';
                        htmlTr += '<td>' + formatMatrixNumber(row.QTY) + '</td>';
                        htmlTr += '<td>' + escapeHtml(row.CAVITY) + '</td>';
                        htmlTr += '<td><b>' + formatMatrixNumber(row.TOTAL_SHOTS) + '</b></td>';
                        htmlTr += '</tr>';
                    });
                    $('#tbodyDetailOvh').html(htmlTr);
                } else {
                    $('#tbodyDetailOvh').html('<tr><td colspan="4" style="color:#666;">Data detail dokumen kosong/tidak ditemukan.</td></tr>');
                }
            },
            error: function() {
                $('#tbodyDetailOvh').html('<tr><td colspan="4" style="color:red;"><b>Error:</b> Gagal menghubungi server.</td></tr>');
            }
        });
    });

   // --- 7. EVENT CHECKBOX PER TANGGAL: SIMPAN STATUS AKTUAL OVH ---
    $('#tblMatrix').on('change', '.chk-ovh-date', function() {
        var chk = $(this);
        var mcCode = chk.data('mc');
        var itemCode = chk.data('item');
        var day = chk.data('day');
        var thn = $('#cbTahun').val();
        var bln = $('#cbBulan').val();
        var statusVal = chk.is(':checked') ? 1 : 0;
        
        var labelEl = chk.closest('label');
        var origText = labelEl.text();

        // Berikan indikator visual sedang menyimpan
        chk.prop('disabled', true);
        labelEl.css('color', '#d9534f').text(' Saving...');

        $.ajax({
            url: 'ajax_save_ovh_date_status.php',
            type: 'POST',
            dataType: 'json',
            data: { tahun: thn, bulan: bln, hari: day, mac_code: mcCode, item_code: itemCode, status: statusVal },
            success: function(res) {
                chk.prop('disabled', false);
                if(res.status === 'success') {
                    // Berikan indikator sukses tersimpan
                    labelEl.css('color', '#28a745').text(' Saved!');
                    setTimeout(function() {
                        labelEl.css('color', '#166534');
                        labelEl.html('').append(chk).append(' Actual');
                        // Kembalikan status centangnya
                        chk.prop('checked', statusVal === 1);
                    }, 1200);
                } else {
                    alert("Gagal menyimpan tanggal aktual OVH: " + res.message);
                    chk.prop('checked', !statusVal); 
                    labelEl.css('color', '#166534');
                    labelEl.html('').append(chk).append(' Actual');
                }
            },
            error: function() {
                chk.prop('disabled', false);
                alert("Terjadi kesalahan koneksi server.");
                chk.prop('checked', !statusVal);
                labelEl.css('color', '#166534');
                labelEl.html('').append(chk).append(' Actual');
            }
        });
    });

});
</script>
</body>
</html>
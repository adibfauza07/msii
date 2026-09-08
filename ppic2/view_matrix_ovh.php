<?php
// view_matrix_ovh.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// --- LOAD CONFIG & KONEKSI ---
$configPath = __DIR__ . "/../config/global.php";
if (file_exists($configPath)) { require_once $configPath; }

$currentYear = (int)date('Y');
$currentMonth = (int)date('n');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Matrix Over Hour - Molding (Std 25K Shots)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; background-color: #f4f6f9; margin: 0; padding: 15px; }
        .panel { background-color: #fff; border: 1px solid #d1d5db; border-radius: 4px; padding: 15px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .panel-title { font-weight: bold; color: #0056b3; margin-bottom: 15px; border-bottom: 2px solid #0056b3; padding-bottom: 6px; font-size: 14px; }
        
        .form-group { display: inline-block; margin-right: 15px; vertical-align: middle; }
        label { font-weight: bold; margin-right: 5px; color: #333; }
        select, input[type="text"] { padding: 4px 8px; border: 1px solid #ccc; border-radius: 3px; font-size: 12px; height: 28px; outline: none; box-sizing: border-box; }
        button { padding: 4px 15px; font-weight: bold; cursor: pointer; border: none; border-radius: 3px; height: 28px; color: #fff; background-color: #0056b3; transition: background 0.2s;}
        button:hover { background-color: #004494; }
        button:disabled { background-color: #8bb3df; cursor: not-allowed; }
        .btn-clear { background-color: #dc3545; padding: 4px 10px; margin-left: -5px; }
        .btn-clear:hover { background-color: #c82333; }
        
        /* Table Structure */
        .table-container { width: 100%; overflow: auto; max-height: 65vh; border: 1px solid #bbb; background-color: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        table.grid-table { border-collapse: separate; border-spacing: 0; white-space: nowrap; table-layout: fixed; min-width: 100%; }
        table.grid-table th, table.grid-table td { border-right: 1px solid #ccc; border-bottom: 1px solid #ccc; padding: 6px 8px; box-sizing: border-box; }
        
        table.grid-table th { border-top: 1px solid #ccc; background-color: #e2e8f0; font-weight: bold; position: sticky; top: 0; z-index: 10; text-align: center; }
        table.grid-table td:first-child, table.grid-table th:first-child { border-left: 1px solid #ccc; }
        
        /* Sticky Columns */
        .fix-mc { position: sticky; left: 0; width: 75px; min-width: 75px; background-color: #f8fafc; z-index: 5; font-weight: bold; text-align: center; }
        .fix-item { position: sticky; left: 75px; width: 220px; min-width: 220px; background-color: #f8fafc; z-index: 5; font-weight: bold; border-right: 1px solid #ccc;}
        .fix-lastshoot { position: sticky; left: 295px; width: 90px; min-width: 90px; background-color: #f8fafc; z-index: 5; font-weight: bold; text-align: right; border-right: 2px solid #ccc; }
        .fix-desc { position: sticky; left: 385px; width: 120px; min-width: 120px; background-color: #f1f5f9; z-index: 5; font-weight: bold; }
        .fix-total { position: sticky; left: 505px; width: 75px; min-width: 75px; background-color: #e2e8f0; z-index: 5; font-weight: bold; text-align: right; border-right: 2px solid #64748b !important; }
        
        /* Corner overlaps (z-index 15) */
        table.grid-table th.fix-mc, table.grid-table th.fix-item, table.grid-table th.fix-lastshoot, table.grid-table th.fix-desc, table.grid-table th.fix-total { z-index: 15; background-color: #cbd5e1; }
        
        .day-col { width: 55px; min-width: 55px; text-align: right; }
        .group-header { background-color: #1e293b !important; color: #fff !important; font-weight: bold; text-align: left !important; font-size: 12px; position: sticky; left: 0; z-index: 6; }
        
        /* Styling Row & Alert */
        .ovh-alert { background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold; box-shadow: inset 0 0 4px #f87171; }
        .row-plan td:not(.fix-mc):not(.fix-item):not(.fix-lastshoot):not(.fix-desc):not(.fix-total):not(.ovh-alert) { color: #0284c7; }
        .row-act td:not(.fix-mc):not(.fix-item):not(.fix-lastshoot):not(.fix-desc):not(.fix-total):not(.ovh-alert) { color: #166534; background-color: #f0fdf4; }
        
        /* UI Autocomplete Z-Index */
        .ui-autocomplete { z-index: 9999 !important; font-size: 11px; max-height: 200px; overflow-y: auto; overflow-x: hidden;}

        /* Styling untuk Input Inline Last Shoot */
        .edit-last-shoot { width: 100%; box-sizing: border-box; text-align: right; border: 1px solid #ccc; padding: 4px; background: #fff; border-radius: 3px; font-size: 11px; transition: all 0.3s; font-weight: bold; color: #856404; }
        .edit-last-shoot:focus { border-color: #0056b3; outline: none; box-shadow: 0 0 4px rgba(0,86,179,0.5); }
        .edit-success { background-color: #d4edda !important; border-color: #28a745 !important; color: #155724 !important; }
        .edit-error { background-color: #f8d7da !important; border-color: #dc3545 !important; }
    </style>
</head>
<body>

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
    <span style="margin-left: 10px; color: #666; font-style: italic;">* Ketik angka pada kolom Last Shoot dan tekan <b>Enter</b> untuk menyimpan.</span>
</div>

<div class="table-container">
    <table class="grid-table" id="tblMatrix">
        <thead>
            <tr id="tableHeaderRow">
                <th class="fix-mc">Mesin</th>
                <th class="fix-item">Item Detail</th>
                <th class="fix-lastshoot">Last Shoot</th>
                <th class="fix-desc">Keterangan</th>
                <th class="fix-total">Total Shots</th>
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

<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
$(document).ready(function() {
    
    // --- 1. SETUP AUTOCOMPLETE CUSTOMER ---
    $("#inputCustomer").autocomplete({
        source: function(request, response) {
            $.ajax({
                url: "ajax_customer.php",
                type: "GET",
                dataType: "json",
                data: { term: request.term },
                success: function(data) { response(data); }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            $("#inputCustomer").val(ui.item.label);
            $("#cust_code").val(ui.item.value);
            return false;
        }
    }).on("input", function() {
        if ($(this).val() === "") {
            $("#cust_code").val(""); 
        }
    });

    $('#btnClearCust').click(function(){
        $('#inputCustomer').val('');
        $('#cust_code').val('');
        $('#inputCustomer').focus();
    });

    // --- 2. FUNGSI HELPER (XSS Prevention & Formatting) ---
    function escapeHtml(text) {
        if (text == null) return '';
        return text.toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function formatMatrixNumber(num) {
        if(num === 0 || num === "0" || num === null || isNaN(num)) return "-";
        return parseFloat(num).toLocaleString('id-ID');
    }

    function getDaysInMonth(month, year) {
        return new Date(year, month, 0).getDate();
    }

    // --- 3. PROSES LOAD DATA MATRIX ---
    $('#btnLoadMatrix').click(function() {
        var btn = $(this);
        var thn = parseInt($('#cbTahun').val());
        var bln = parseInt($('#cbBulan').val());
        var ccode = $('#cust_code').val(); 

        var totalDays = getDaysInMonth(bln, thn);
        var stdLimit = 25000; 
        
        var theadTr = $('#tableHeaderRow');
        var tbody = $('#tblMatrix tbody');
        
        // Build Thead
        var headHtml = '<th class="fix-mc">Mesin</th>' +
                       '<th class="fix-item">Item Detail</th>' +
                       '<th class="fix-lastshoot" title="Klik lalu Enter untuk Update">Last Shoot <i class="fa fa-pencil"></i></th>' +
                       '<th class="fix-desc">Keterangan</th>' +
                       '<th class="fix-total">Total Shots</th>';
        for(var i = 1; i <= totalDays; i++) {
            headHtml += '<th class="day-col">' + i + '</th>';
        }
        theadTr.html(headHtml);

        // Loading State
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading...');
        tbody.html('<tr><td colspan="' + (totalDays + 5) + '" style="text-align:center; padding:40px;"><i class="fa fa-spinner fa-spin fa-2x"></i><br><br>Sedang memproses kalkulasi matrix...</td></tr>');
        
        // Fetch via AJAX
        $.ajax({
            url: 'ajax_get_matrix_ovh.php',
            type: 'POST',
            data: { tahun: thn, bulan: bln, cust_code: ccode },
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    var html = '';
                    var hasData = false;
                    
                    $.each(res.data, function(custName, items) {
                        hasData = true;
                        
                        html += '<tr>';
                        html += '<td colspan="' + (totalDays + 5) + '" class="group-header"><i class="fa fa-building"></i> CUSTOMER: ' + escapeHtml(custName) + '</td>';
                        html += '</tr>';
                        
                        $.each(items, function(itemKey, row) {
                            var sumPlanOvh = 0;
                            var sumActOvh = 0;
                            var tdPlan = '';
                            var tdAct = '';
                            
                            for (var d = 1; d <= totalDays; d++) {
                                var dPlan = (row.days && row.days[d]) ? parseFloat(row.days[d].plan_ovh) || 0 : 0;
                                var dAct  = (row.days && row.days[d]) ? parseFloat(row.days[d].act_ovh) || 0 : 0;
                                
                                sumPlanOvh += dPlan;
                                sumActOvh += dAct;
                                
                                var clsPlan = dPlan > stdLimit ? 'ovh-alert' : '';
                                var clsAct  = dAct > stdLimit ? 'ovh-alert' : '';
                                
                                tdPlan += '<td class="day-col ' + clsPlan + '" title="Plan Tgl ' + d + '">' + formatMatrixNumber(dPlan) + '</td>';
                                tdAct  += '<td class="day-col ' + clsAct  + '" title="Actual Tgl ' + d + '">' + formatMatrixNumber(dAct) + '</td>';
                            }
                            
                            var strItemDetail = '<b>' + escapeHtml(row.item_code) + '</b><br>' +
                                                '<span style="color:#0056b3;">' + escapeHtml(row.item_no) + '</span><br>' +
                                                '<small style="color:#555;">' + escapeHtml(row.item_name) + '</small><br>' +
                                                '<small style="color:#e67e22; font-weight:bold;">Cavity: ' + escapeHtml(row.plan_cavity) + '</small>';

                            // INPUT INLINE UNTUK LAST SHOOT
                            var inputLastShoot = '<input type="text" class="edit-last-shoot" ' +
                                                 'data-mc="' + escapeHtml(row.mc_no) + '" ' +
                                                 'data-item="' + escapeHtml(row.item_code) + '" ' +
                                                 'value="' + (row.last_shoot || 0) + '">';

                            // Baris PLAN
                            html += '<tr class="row-plan">';
                            html += '<td class="fix-mc" rowspan="2">' + escapeHtml(row.mc_no) + '</td>';
                            html += '<td class="fix-item" rowspan="2" style="white-space:normal; line-height: 1.3;">' + strItemDetail + '</td>';
                            html += '<td class="fix-lastshoot" rowspan="2" style="vertical-align:middle; background-color:#fff3cd; padding:4px;">' + inputLastShoot + '</td>';
                            html += '<td class="fix-desc"><i class="fa fa-file-text-o"></i> Plan Shots</td>';
                            html += '<td class="fix-total">' + formatMatrixNumber(sumPlanOvh) + '</td>';
                            html += tdPlan;
                            html += '</tr>';
                            
                            // Baris ACTUAL
                            html += '<tr class="row-act">';
                            html += '<td class="fix-desc"><i class="fa fa-industry"></i> Actual Shots</td>';
                            html += '<td class="fix-total">' + formatMatrixNumber(sumActOvh) + '</td>';
                            html += tdAct;
                            html += '</tr>';
                        });
                    });
                    
                    if(!hasData) {
                        html = '<tr><td colspan="' + (totalDays + 5) + '" style="text-align:center; padding: 30px; color:#d9534f;"><b>Tidak ada jadwal produksi untuk periode ini (Atau filter Customer tidak memiliki data).</b></td></tr>';
                    }
                    
                    tbody.html(html);
                } else {
                    tbody.html('<tr><td colspan="' + (totalDays + 5) + '" style="text-align:center; padding: 20px; color:#d9534f;"><b>Gagal:</b> ' + escapeHtml(res.message) + '</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.log(xhr.responseText);
                tbody.html('<tr><td colspan="' + (totalDays + 5) + '" style="text-align:center; padding: 20px; color:#d9534f;"><b>Error:</b> Terjadi kesalahan pada server. Cek Console (F12).</td></tr>');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fa fa-search"></i> Load Matrix Data');
            }
        });
    });

    // --- 4. EVENT LISTENER UNTUK MENYIMPAN LAST SHOOT (TOMBOL ENTER) ---
    $('#tblMatrix').on('keypress', '.edit-last-shoot', function(e) {
        if (e.which == 13) { // 13 adalah keycode untuk Enter
            e.preventDefault();
            
            var inputField = $(this);
            var macCode    = inputField.data('mc');
            var itemCode   = inputField.data('item');
            var valShoot   = inputField.val();
            
            var thn = $('#cbTahun').val();
            var bln = $('#cbBulan').val();
            
            inputField.prop('disabled', true); // Kunci form saat AJAX loading
            
            $.ajax({
                url: 'ajax_save_last_shoot.php', // Pastikan file backend ini sudah dibuat
                type: 'POST',
                data: {
                    tahun: thn,
                    bulan: bln,
                    mac_code: macCode,
                    item_code: itemCode,
                    last_shoot: valShoot
                },
                dataType: 'json',
                success: function(res) {
                    inputField.prop('disabled', false);
                    
                    if(res.status === 'success') {
                        // Animasi Sukses (Hijau)
                        inputField.addClass('edit-success');
                        setTimeout(function(){ 
                            inputField.removeClass('edit-success'); 
                        }, 2000);
                    } else {
                        alert("Gagal menyimpan data: " + res.message);
                        inputField.addClass('edit-error');
                        setTimeout(function(){ inputField.removeClass('edit-error'); }, 2000);
                    }
                },
                error: function(xhr, status, error) {
                    inputField.prop('disabled', false);
                    console.log(xhr.responseText);
                    alert("Terjadi kesalahan pada server saat menyimpan Last Shoot.");
                    
                    // Animasi Error (Merah)
                    inputField.addClass('edit-error');
                    setTimeout(function(){ inputField.removeClass('edit-error'); }, 2000);
                }
            });
        }
    });

});
</script>
</body>
</html>
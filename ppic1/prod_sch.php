<?php
// prod_sch.php
// Gunakan Config Baru
$configPath = __DIR__ . "/../config/global.php"; 
if (file_exists($configPath)) { require_once $configPath; }

if (session_status() == PHP_SESSION_NONE) { session_start(); }

$currentYear = (int)date('Y');
$currentMonth = (int)date('n');

// =====================================================================
// AMBIL DATA MASTER GROUP DAN MACHINE UNTUK COMBO BOX
// =====================================================================
$arrMachGroup = array();
$arrMachine = array();

$databaseName = "msData";
$serverName = isset($_SESSION['active_server']) ? $_SESSION['active_server'] : "192.168.0.9";
$uid = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : "";
$pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "";
$connectionOptions = array("Database" => $databaseName, "CharacterSet" => "UTF-8");
if ($uid !== "") { 
    $connectionOptions["Uid"] = $uid; 
    $connectionOptions["PWD"] = $pwd; 
}

$connCombo = @sqlsrv_connect($serverName, $connectionOptions);
if ($connCombo) {
    $stmtMag = @sqlsrv_query($connCombo, "SELECT DISTINCT MAG_STATION FROM dbo.MAG WHERE MAG_STATION IS NOT NULL ORDER BY MAG_STATION");
    if ($stmtMag) {
        while ($row = sqlsrv_fetch_array($stmtMag, SQLSRV_FETCH_ASSOC)) {
            $arrMachGroup[] = trim($row['MAG_STATION']);
        }
    }

    $sqlMac = "SELECT MAG.MAG_STATION, MAC.MAC_CODE 
               FROM dbo.MAC 
               INNER JOIN dbo.MAG ON MAC.MAG_ID = MAG.MAG_ID
               WHERE MAC.MAC_CODE IS NOT NULL
               ORDER BY MAC.MAC_CODE ASC";
               
    $stmtMac = @sqlsrv_query($connCombo, $sqlMac);
    
    if ($stmtMac) {
        while ($row = sqlsrv_fetch_array($stmtMac, SQLSRV_FETCH_ASSOC)) {
            $arrMachine[] = array(
                'mac' => trim($row['MAC_CODE']),
                'mag' => trim($row['MAG_STATION'])
            );
        }
    }
    @sqlsrv_close($connCombo);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Production Schedule - ERP PPIC Module</title>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; background-color: #f4f6f9; margin: 0; padding: 15px; }
        .panel { background-color: #fff; border: 1px solid #ccc; padding: 12px; margin-bottom: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .panel-title { font-weight: bold; color: #333; margin-bottom: 12px; border-bottom: 2px solid #0056b3; padding-bottom: 4px; font-size: 13px; }
        
        .form-group { display: inline-block; margin-right: 15px; vertical-align: middle; }
        label { font-weight: bold; margin-right: 4px; color: #444; }
        select, input[type="text"] { padding: 4px; border: 1px solid #ccc; font-size: 12px; height: 26px; box-sizing: border-box; }
        
        button { padding: 4px 15px; font-weight: bold; cursor: pointer; border: 1px solid transparent; height: 28px; display: inline-flex; align-items: center; justify-content: center; gap: 5px; }
        .btn-primary { background-color: #0056b3; color: #fff; border-color: #004494; }
        .btn-primary:hover { background-color: #004494; }
        .btn-success { background-color: #28a745; color: #fff; border-color: #1e7e34; }
        .btn-success:hover { background-color: #218838; }
        .btn-danger { background-color: #dc3545; color: #fff; border-color: #bd2130; }
        .btn-danger:hover { background-color: #c82333; }
        .btn-warning { background-color: #f0ad4e; color: #333; border-color: #d39e00; }
        .btn-secondary { background-color: #6c757d; color: #fff; border-color: #5a6268; }
        .btn-secondary:hover { background-color: #5a6268; }
        
        /* UI Autocomplete Custom Styling */
        .ui-autocomplete { font-size: 11px; max-height: 250px; overflow-y: auto; overflow-x: hidden; z-index: 9999; border: 1px solid #0056b3; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .ui-menu-item-wrapper { padding: 5px 8px !important; border-bottom: 1px solid #eee; }
        .ui-state-active { background-color: #e6f7ff !important; border-color: #99c2ff !important; color: #333 !important; }

        .workspace { display: flex; width: 100%; gap: 15px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 3px dashed #b0bec5; flex-direction: column; }
        .workspace-top { display: flex; width: 100%; gap: 15px; }
        .workspace:last-child { border-bottom: none; }
        .left-pane { flex: 0 0 300px; width: 300px; }
        .right-pane { flex: 1; min-width: 0; overflow: hidden; } 
        
        .pref-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .pref-table td { padding: 4px; border: 1px solid #ddd; }
        .pref-table td.label-cell { background-color: #f2f2f2; font-weight: bold; width: 110px; text-align: right; }
        .pref-table input[type="text"] { width: 100%; border: 1px solid #bbb; background: #fafafa; text-align: left; padding: 3px; box-sizing: border-box; color: #0056b3; font-weight: bold;}

        .table-container { width: 100%; overflow-x: auto; max-height: 500px; border: 1px solid #bbb; background-color: #fff; border-bottom: none; }
        table.grid-table { border-collapse: separate; border-spacing: 0; white-space: nowrap; table-layout: fixed; }
        table.grid-table th, table.grid-table td { border-right: 1px solid #bbb; border-bottom: 1px solid #bbb; padding: 4px 6px; font-size: 11px; box-sizing: border-box; }
        table.grid-table th { border-top: 1px solid #bbb; background-color: #eaeaea; font-weight: bold; position: sticky; top: 0; z-index: 10; text-align: center; }
        table.grid-table td:first-child, table.grid-table th:first-child { border-left: 1px solid #bbb; }
        
        .day-col { width: 60px; min-width: 60px; text-align: right; }
        .desc-col { text-align: left; font-weight: bold; background-color: #f9f9f9; width: 130px; min-width: 130px; position: sticky; left: 0; z-index: 11; }
        .gtotal-col { background-color: #e2e2e2; font-weight: bold; text-align: right; width: 75px; min-width: 75px; position: sticky; left: 130px; z-index: 11; }
        table.grid-table th.desc-col, table.grid-table th.gtotal-col { z-index: 12; background-color: #dcdcdc; }

        .input-r0 { width: 100%; text-align: right; border: 1px solid #0056b3; background-color: #e6f7ff; box-sizing: border-box; padding: 2px;}
        .input-r0:focus { outline: none; background-color: #fff; border: 1px solid #ff9900; }
        .val-minus { color: #cc0000 !important; background-color: #ffe6e6 !important; }
        
        .action-bar { padding: 8px 12px; background: #e9ecef; border: 1px solid #bbb; border-top: none; text-align: left; }
        .action-bar button { height: 28px; padding: 4px 15px; font-size: 11px; margin-right: 8px; }
        .save-status { font-size: 11px; margin-left: 15px; padding: 3px 8px; border-radius: 3px; display: inline-block; vertical-align: middle; }
        .save-status.ok { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .save-status.warn { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }

        /* Banner Informasi */
        .alert-minus { background-color: #ffebee; color: #c62828; border: 1px solid #ef9a9a; padding: 12px 15px; border-radius: 4px; margin-bottom: 10px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .alert-info { background-color: #e3f2fd; color: #0d47a1; border: 1px solid #bbdefb; padding: 12px 15px; border-radius: 4px; margin-bottom: 15px; font-weight: 700; display: flex; align-items: center; gap: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }

        /* ========================================= */
        /* CSS Print Mode - A4 Landscape Optimal     */
        /* ========================================= */
        @media print {
            @page { size: A4 landscape; margin: 5mm; }
            body { background-color: #f4f6f9; padding: 0; margin: 0; -webkit-print-color-adjust: exact !important; color-adjust: exact !important; zoom: 85%; }
            hr, .action-bar, #minusAlert, #emptyDatesAlert, .btn-load-assembly-del, #btnAddItem, span.save-status, .ui-autocomplete { display: none !important; }
            div[style*="background-color: #e9ecef"] { display: none !important; } 
            .panel { border: 1px solid #ccc !important; box-shadow: none !important; padding: 10px !important; margin-bottom: 10px !important; }
            .panel-title { font-size: 11px !important; margin-bottom: 8px !important; padding-bottom: 4px !important; color: #0056b3 !important; }
            .workspace { display: flex !important; flex-direction: row !important; gap: 10px !important; page-break-inside: avoid; border: none !important; margin: 0 0 15px 0 !important; padding: 0; }
            .workspace-top { display: flex !important; flex-direction: row !important; width: 100%; gap: 10px !important; }
            .left-pane { flex: 0 0 200px !important; width: 200px !important; margin: 0 !important; }
            .pref-table { font-size: 9px !important; border-spacing: 0; }
            .pref-table td { padding: 2px !important; border: none; }
            .pref-table td.label-cell { width: 75px !important; text-align: right; font-weight: 600; color: #555; background-color: transparent !important; }
            .pref-table input[type="text"] { font-size: 9px !important; padding: 2px !important; border: 1px solid #ccc !important; width: 100%; box-sizing: border-box; font-weight: bold; background: transparent !important; }
            .right-pane { flex: 1 !important; min-width: 0; overflow: hidden !important; }
            .table-container { overflow: hidden !important; border: 1px solid #ccc !important; max-height: none !important; }
            table.grid-table { border-collapse: collapse; width: 100%; table-layout: fixed; }
            .day-col { min-width: 0 !important; width: auto !important; }
            .desc-col { min-width: 0 !important; width: 85px !important; white-space: normal !important; background-color: #f9f9f9 !important; }
            .gtotal-col { min-width: 0 !important; width: 45px !important; background-color: #e2e2e2 !important; }
            table.grid-table th, table.grid-table td { font-size: 7px !important; padding: 3px 1px !important; border: 1px solid #ddd !important; word-wrap: break-word; text-align: center; color: #000; }
            table.grid-table th { background-color: #eaeaea !important; font-weight: bold; }
            table.grid-table td.desc-col { text-align: left; font-weight: bold; }
            table.grid-table td.gtotal-col { text-align: right; font-weight: bold; }
            .input-r0 { border: none !important; background: transparent !important; padding: 0; font-size: 7px !important; box-shadow: none !important; text-align: right; color: #000; font-weight: bold; }
            .val-minus { color: #cc0000 !important; background-color: #ffe6e6 !important; font-weight: bold; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <!-- HEADER PANEL -->
    <div class="panel">
        <div class="panel-title"><i class="fa fa-search"></i> Search & Parameters</div>
        <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: center;">
            
            <!-- BLOK 1: PERIODE -->
            <div>
                <div class="form-group">
                    <label for="cbTahun">Tahun:</label>
                    <select id="cbTahun">
                        <?php for ($y = $currentYear - 2; $y <= $currentYear + 2; $y++) {
                            $sel = ($y === $currentYear) ? 'selected' : '';
                            echo "<option value=\"$y\" $sel>$y</option>";
                        } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cbBulan">Bulan:</label>
                    <select id="cbBulan">
                        <?php for ($m = 1; $m <= 12; $m++) {
                            $sel = ($m === $currentMonth) ? 'selected' : '';
                            echo "<option value=\"$m\" $sel>$m</option>";
                        } ?>
                    </select>
                </div>
            </div>

            <!-- BLOK 2: MACHINE GROUP & CODE -->
            <div style="border-left: 2px solid #eee; padding-left: 20px;">
                <div class="form-group">
                    <label for="cbMachGroup">Mach Group:</label>
                    <select id="cbMachGroup" style="width: 130px; font-weight: bold;">
                        <option value="">-- Semua Group --</option>
                        <?php foreach($arrMachGroup as $mag): ?>
                            <option value="<?= htmlspecialchars($mag, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($mag, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cbMachCode">Machine Code:</label>
                    <select id="cbMachCode" style="width: 130px; font-weight: bold; border: 1px solid #0056b3;">
                        <option value="">-- Pilih Mesin --</option>
                    </select>
                </div>
                <div class="form-group">
                    <button id="btnLoad" class="btn-primary"><i class="fa fa-search"></i> Load Schedule</button>
                </div>
            </div>

            <!-- BLOK 3: FIND ITEM ROUTING -->
            <div style="border-left: 2px solid #ccc; padding-left: 20px;">
                <div class="form-group">
                    <label style="color:#d32f2f;"><i class="fa fa-map-marker"></i> Find Item Machine:</label>
                    <input type="text" id="txtSearchItemSch" size="25" placeholder="Ketik Item Code (Lacak Mesin)..." style="border: 1px solid #d32f2f; font-weight: bold; background-color:#fff8e1;" autocomplete="off">
                </div>
            </div>

        </div>

        <hr style="border:0; border-top:1px dashed #ccc; margin:15px 0;">
        
        <!-- BLOK ADD ITEM -->
        <div style="background-color: #e9ecef; padding: 8px; border-radius: 4px; display: inline-block;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="txtNewItemCode" style="color: #28a745;"><i class="fa fa-plus-circle"></i> Add Item Schedule:</label>
                <input type="text" id="txtNewItemCode" value="" placeholder="Ketik Item Code..." size="25" autocomplete="off">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <button id="btnAddItem" class="btn-success"><i class="fa fa-plus"></i> Add Item</button>
            </div>
            <span style="font-size:11px; color:#666; margin-left: 10px;">(Pilih Mesin di atas, ketik Item Code, lalu Add)</span>
        </div>
        
    </div>

    <!-- NOTIFIKASI INFO -->
    <div id="minusAlert" class="alert-minus" style="display:none;"></div>
    <div id="emptyDatesAlert" class="alert-info" style="display:none;"></div>

    <!-- WORKSPACE CONTAINER -->
    <div id="workspaceContainer">
        <div class="panel" style="text-align:center; padding:50px; color:#666;">
            Silakan pilih Machine Code dan klik "Load Schedule" atau cari Item melalui "Find Item Machine".
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    
    <script>
    var allMachines = <?php echo json_encode($arrMachine); ?>;

    function populateMachine(selectedGroup) {
        var cbMac = $('#cbMachCode');
        var currentMac = cbMac.val(); 
        cbMac.empty().append('<option value="">-- Pilih Mesin --</option>');
        $.each(allMachines, function(i, item) {
            if (selectedGroup === '' || item.mag === selectedGroup || item.mag === '') {
                cbMac.append($('<option></option>').val(item.mac).text(item.mac));
            }
        });
        if (currentMac) cbMac.val(currentMac); 
    }

    function escapeHtml(unsafe) {
        if (unsafe === null || unsafe === undefined) return '';
        return unsafe.toString().replace(/[&<>"']/g, function(m) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[m];
        });
    }

    // =====================================================================
    // FUNGSI: Deteksi Tanggal Kosong pada Mesin
    // =====================================================================
    window.updateEmptyDatesSummary = function() {
        if ($('.workspace').length === 0) {
            $('#emptyDatesAlert').hide();
            return;
        }

        var emptyDays = [];
        for (var d = 1; d <= 31; d++) {
            var isDayEmpty = true;
            $('.workspace').each(function() {
                var planVal = parseFloat($(this).find('.input-r0.day-' + d).val()) || 0;
                var actVal = parseFloat($(this).find('.cell-po.day-' + d).text()) || 0;
                if (planVal > 0 || actVal > 0) { isDayEmpty = false; }
            });
            if (isDayEmpty) { emptyDays.push(d); }
        }

        function formatDaysRange(days) {
            if (days.length === 0) return "";
            var ranges = [];
            var start = days[0], end = days[0];
            for (var i = 1; i < days.length; i++) {
                if (days[i] === end + 1) {
                    end = days[i];
                } else {
                    ranges.push(start === end ? start : start + '-' + end);
                    start = days[i]; end = days[i];
                }
            }
            ranges.push(start === end ? start : start + '-' + end);
            return ranges.join(', ');
        }

        if (emptyDays.length > 0) {
            var emptyStr = formatDaysRange(emptyDays);
            $('#emptyDatesAlert').html('<i class="fa fa-calendar-check-o" style="font-size:16px;"></i> <div><b>Kapasitas Mesin Tersedia:</b> Tidak ada aktivitas/jadwal produksi pada tanggal: <b>' + emptyStr + '</b>.</div>')
                .css({'background-color': '#e3f2fd', 'color': '#0d47a1', 'border-color': '#bbdefb'}).fadeIn(200);
        } else {
            $('#emptyDatesAlert').html('<i class="fa fa-calendar-times-o" style="font-size:16px;"></i> <div><b>Jadwal Penuh:</b> Seluruh tanggal pada bulan ini sudah memiliki aktivitas/jadwal produksi.</div>')
                .css({'background-color': '#fff3cd', 'color': '#856404', 'border-color': '#ffeeba'}).fadeIn(200);
        }
    };

    // =====================================================================
    // FUNGSI: Deteksi Minus Stock Plan
    // =====================================================================
    window.updateMinusSummary = function() {
        var minusCount = 0;
        var targets = [];

        $('.workspace').each(function() {
            if ($(this).find('.cell-estplan.val-minus').length > 0) {
                minusCount++;
                targets.push($(this));
            }
        });

        if (minusCount > 0) {
            $('#minusAlert').html('<i class="fa fa-exclamation-circle" style="font-size:16px;"></i> Peringatan: Terdapat ' + minusCount + ' Item dengan Est Stock Plan Minus! (Klik di sini untuk melihat)').fadeIn(200);
            $('#minusAlert').data('targets', targets); 
        } else {
            $('#minusAlert').fadeOut(200);
        }
    };

    $('body').on('click', '#minusAlert', function() {
        var targets = $(this).data('targets');
        if (targets && targets.length > 0) {
            var target = targets[0];
            $('html, body').animate({ scrollTop: target.offset().top - 20 }, 500);
            target.css({'box-shadow': '0 0 12px 3px #ef5350', 'transition': 'box-shadow 0.5s ease'});
            setTimeout(function() { target.css('box-shadow', 'none'); }, 3000);
        }
    });

    // =====================================================================
    // RECALC MATRIX (Real-Time UI) - SUDAH DIPERBAIKI (DESKTOP SYNC)
    // =====================================================================
    function recalcMatrix(idx) {
        var table = $('#matrix_' + idx);
        var begStock = parseFloat(table.data('begstock')) || 0;
        
        var runPlan = begStock;
        var runAct  = begStock;
        var runDelBal  = 0;
        var runProdBal = 0;

        for (var d = 1; d <= 31; d++) {
            var dp = parseFloat(table.find('.cell-dp.day-' + d).text()) || 0;
            var da = parseFloat(table.find('.cell-da.day-' + d).text()) || 0;
            var pp = parseFloat(table.find('.input-r0.day-' + d).val()) || 0;
            var po = parseFloat(table.find('.cell-po.day-' + d).text()) || 0;
            var ph = parseFloat(table.find('.cell-ph.day-' + d).text()) || 0;

            // PERBAIKAN: Rumus dibalik menjadi ACTUAL - PLAN (da - dp) agar minus
            runDelBal  = runDelBal + (da - dp); 
            runProdBal = runProdBal + (po - pp); 
            
            runPlan = runPlan + pp - dp;
            runAct  = runAct + po + ph - da;

            table.find('.cell-delbal.day-' + d).text(runDelBal).attr('class', 'day-col cell-delbal day-' + d + (runDelBal < 0 ? ' val-minus' : ''));
            table.find('.cell-prodbal.day-' + d).text(runProdBal).attr('class', 'day-col cell-prodbal day-' + d + (runProdBal < 0 ? ' val-minus' : ''));
            table.find('.cell-estplan.day-' + d).text(runPlan).attr('class', 'day-col cell-estplan day-' + d + (runPlan < 0 ? ' val-minus' : ''));
            table.find('.cell-estact.day-' + d).text(runAct).attr('class', 'day-col cell-estact day-' + d + (runAct < 0 ? ' val-minus' : ''));
        }

        table.find('tbody tr').each(function() {
            var tr = $(this);
            var rName = $.trim(tr.find('.desc-col').text());
            
            if (rName === 'Est Stock Plan') { tr.find('.gtotal-col').text(runPlan).toggleClass('val-minus', runPlan < 0); return; }
            if (rName === 'Est Stock Actual') { tr.find('.gtotal-col').text(runAct).toggleClass('val-minus', runAct < 0); return; }
            
            // PERBAIKAN: Paksa Grand Total Balance menjadi 0 persis seperti Desktop
            if (rName === 'Del Balance') { tr.find('.gtotal-col').text(0).removeClass('val-minus'); return; }
            if (rName === 'Prod Balance') { tr.find('.gtotal-col').text(0).removeClass('val-minus'); return; }

            var sum = 0;
            for(var d = 1; d <= 31; d++) {
                var el = tr.find('.day-' + d);
                var val = el.is('input') ? (parseFloat(el.val())||0) : (el.find('input').length>0 ? (parseFloat(el.find('input').val())||0) : parseFloat(el.text())||0);
                sum += val;
            }
            tr.find('.gtotal-col').text(sum).toggleClass('val-minus', sum < 0);
        });

        updateMinusSummary(); 
        updateEmptyDatesSummary();
    }

    // =====================================================================
    // BUILD WORKSPACE HTML
    // =====================================================================
    function buildWorkspaceHtml(index, item) {
        var h = item.header;
        var begStock   = parseFloat(h.BEG_BALANCE) || 0; 
        var rawSafety  = parseFloat(h.SAFETY_STK) || (begStock * 0.25); 
        var safetyStk  = Math.round(rawSafety); 
        var safeItemCode = escapeHtml(h.ITEM_CODE);
        var woCap = parseFloat(h.CAP_DAY_STD) || 0;
        var workDayCalc = parseFloat(h.WORK_DAY) || 0;

        // INJEKSI NO URUT
        var noUrut = h.NO_URUT || h.no_urut || ''; 
        var badgeUrut = noUrut ? '<span style="background-color:#dc3545; color:#fff; padding:3px 6px; border-radius:3px; margin-left:10px; font-size:11px;">No. Urut: ' + escapeHtml(noUrut) + '</span>' : '';
        
        var html = '<div class="workspace" id="workspace_' + index + '"><div class="workspace-top">';
        
        // Panel Kiri
        html += '<div class="left-pane"><div class="panel" style="margin-bottom:0; padding:10px;">';
        
        html += '<div class="panel-title" style="color:#0056b3; font-size:13px; padding-bottom:6px;"><i class="fa fa-cube"></i> Item Code: ' + safeItemCode + badgeUrut + '</div>';
        
        html += '<table class="pref-table">';
        html += '<tr><td class="label-cell">Machine No</td><td><input type="text" data-field="MC_NO" value="' + escapeHtml(h.MC_NO) + '" readonly></td></tr>';
        html += '<tr><td class="label-cell">Part Name</td><td><input type="text" data-field="PART_NAME" value="' + escapeHtml(h.PART_NAME) + '" readonly></td></tr>';
        html += '<tr><td class="label-cell">Part No</td><td><input type="text" data-field="PART_NO" value="' + escapeHtml(h.PART_NO) + '" readonly></td></tr>';
        html += '<tr><td class="label-cell">Item Code</td><td><input type="text" data-field="ITEM_CODE" value="' + safeItemCode + '" readonly></td></tr>';
        html += '<tr><td class="label-cell">Customer</td><td><input type="text" data-field="CUST" value="' + escapeHtml(h.CUST) + '" readonly></td></tr>';
        html += '<tr><td class="label-cell">Estimation Order</td><td><input type="text" data-field="CUR_PO_BO" value="' + escapeHtml(h.CUR_PO_BO) + '" readonly></td></tr>';
        html += '<tr><td class="label-cell">Safety Stock</td><td><input type="text" data-field="SAFETY_STK" value="' + safetyStk + '" readonly style="background-color:#fff3cd;"></td></tr>';
        html += '<tr><td class="label-cell">Beginning Stock</td><td><input type="text" data-field="BEG_BALANCE" value="' + begStock + '" readonly style="background-color:#ffeeba; color:#d32f2f;"></td></tr>';
        html += '<tr><td class="label-cell">Cycle Time Std</td><td><input type="text" data-field="CYCLE_TIME_STD" value="' + escapeHtml(h.CYCLE_TIME_STD) + '" readonly></td></tr>';
        html += '<tr><td class="label-cell">Cavity Std</td><td><input type="text" data-field="CAVITY_STD" value="' + escapeHtml(h.CAVITY_STD) + '" readonly></td></tr>';
        html += '<tr><td class="label-cell">Cap / Days Std</td><td><input type="text" data-field="CAP_DAY_STD" value="' + woCap + '" readonly></td></tr>';
        html += '<tr><td class="label-cell">Forecast+1</td><td><input type="text" data-field="PROD_PLAN" value="' + escapeHtml(h.PROD_PLAN) + '" readonly></td></tr>';
        html += '<tr><td class="label-cell">Working Days</td><td><input type="text" data-field="WORK_DAY" value="' + workDayCalc + '" readonly></td></tr>';
        html += '</table></div></div>';

        // Panel Kanan
        html += '<div class="right-pane"><div class="panel" style="margin-bottom:0; padding:0;">';
        
        html += '<div style="background-color: #fff3cd; padding: 6px 10px; border: 1px solid #bbb; border-bottom: none; display: flex; align-items: center;">';
        html += '  <label style="color:#856404; margin-right: 8px;"><i class="fa fa-sitemap"></i> Assembly Part:</label>';
        html += '  <select class="cb-assembly" data-item="' + safeItemCode + '" style="width: 180px; margin-right: 8px;"><option value="">-- Memuat BOM... --</option></select>';
        html += '  <button type="button" class="btn-warning btn-load-assembly-del" data-idx="' + index + '" style="height: 24px; padding: 2px 10px; font-size: 11px;"><i class="fa fa-download"></i> Pull Delivery</button>';
        html += '</div>';

        html += '<div class="table-container"><table class="grid-table" id="matrix_' + index + '" data-begstock="' + begStock + '">';
        html += '<thead><tr><th class="desc-col">Description</th><th class="gtotal-col">Grand Total</th>';
        for (var i = 1; i <= 31; i++) { html += '<th class="day-col">' + i + '</th>'; }
        html += '</tr></thead><tbody>';
        
        var idNoRecord = 0; 
        
        $.each(item.data, function(idx, row) {
            var isR0  = (row.DESC_PROD === 'Prod Plan R0');
            var rName = $.trim(row.DESC_PROD);
            if (row.ID_NO) idNoRecord = row.ID_NO;
            
            var rowClass = '';
            if (rName === 'Del Plan') rowClass = 'cell-dp';
            else if (rName === 'Del Actual') rowClass = 'cell-da';
            else if (rName === 'Prod OK') rowClass = 'cell-po';
            else if (rName === 'Prod HOLD') rowClass = 'cell-ph';
            else if (rName === 'Del Balance') rowClass = 'cell-delbal';
            else if (rName === 'Prod Balance') rowClass = 'cell-prodbal';
            else if (rName === 'Est Stock Plan') rowClass = 'cell-estplan';
            else if (rName === 'Est Stock Actual') rowClass = 'cell-estact';
            
            html += '<tr><td class="desc-col">' + escapeHtml(row.DESC_PROD) + '</td><td class="gtotal-col">' + (row.G_TOTAL || 0) + '</td>';
            
            for (var d = 1; d <= 31; d++) {
                var cellVal = row['D' + d] || 0;
                if (isR0) {
                    html += '<td class="day-col"><input type="text" class="input-r0 day-' + d + '" value="' + cellVal + '" /></td>';
                } else {
                    var tdClass = (cellVal < 0) ? ' val-minus' : '';
                    html += '<td class="day-col ' + rowClass + ' day-' + d + tdClass + '">' + cellVal + '</td>';
                }
            }
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        
        // --- ACTION BAR ---
        html += '<div class="action-bar">';
        html += '<button type="button" class="btn-primary btn-action-load" data-idx="' + index + '"><i class="fa fa-refresh"></i> Load / Re-Calc</button>';
        html += '<button type="button" class="btn-success btn-action-save" data-idx="' + index + '" data-idno="' + idNoRecord + '"><i class="fa fa-save"></i> Save</button>';
        html += '<button type="button" class="btn-danger btn-action-delete" data-idx="' + index + '" data-idno="' + idNoRecord + '"><i class="fa fa-trash"></i> Delete</button>';
        html += '<button type="button" class="btn-secondary btn-print-sch" data-idx="' + index + '"><i class="fa fa-print"></i> Print</button>';
        html += '<span class="save-status" id="saveStatus_' + index + '"></span>';
        html += '</div></div></div></div></div>'; 
        
        return html;
    }

    // =====================================================================
    // DOCUMENT READY
    // =====================================================================
    $(document).ready(function() {
        var urlLoadGrid        = 'ajax_load_grid.php';
        var urlAddScheduleItem = 'ajax_add_schedule_item.php'; 
        
        populateMachine(''); 
        
        $('#cbMachGroup').change(function() { populateMachine($(this).val()); });

        $('#cbMachCode').change(function() {
            var selectedMac = $(this).val();
            if (selectedMac !== '') {
                var found = null;
                for (var i = 0; i < allMachines.length; i++) {
                    if (allMachines[i].mac === selectedMac) { found = allMachines[i]; break; }
                }
                if (found && found.mag !== '') {
                    $('#cbMachGroup').val(found.mag);
                    populateMachine(found.mag);
                    $(this).val(selectedMac); 
                }
            }
        });

        // =====================================================================
        // IMPLEMENTASI FUNGSI TOMBOL AKSI
        // =====================================================================

        // 1. Tombol Print Item
        $('#workspaceContainer').on('click', '.btn-print-sch', function() {
            var currentWorkspace = $(this).closest('.workspace');
            $('.workspace').addClass('no-print');
            currentWorkspace.removeClass('no-print');
            window.print();
            $('.workspace').removeClass('no-print');
        });

        // 2. Tombol Re-Calc / Load Manual per Item
        $('#workspaceContainer').on('click', '.btn-action-load', function() {
            var idx = $(this).data('idx');
            recalcMatrix(idx);
            $('#saveStatus_' + idx).removeClass('warn').addClass('ok').text('Kalkulasi ulang berhasil.').show().delay(2000).fadeOut();
        });

        // 3. Tombol Pull Delivery (Assembly BOM)
        $('#workspaceContainer').on('click', '.btn-load-assembly-del', function() {
            var idx = $(this).data('idx');
            var cbAssembly = $('#workspace_' + idx).find('.cb-assembly').val();
            
            if(!cbAssembly) { 
                alert('Pilih Part BOM pada menu dropdown Assembly Part terlebih dahulu.'); 
                return; 
            }
            
            alert('Fitur Pull Delivery siap dieksekusi untuk part: ' + cbAssembly + '\n(Harap hubungkan fungsi ini dengan backend ajax pull_delivery Anda).');
        });

        // 4. Tombol Delete Schedule
        $('#workspaceContainer').on('click', '.btn-action-delete', function() {
            var btn = $(this);
            var idno = btn.data('idno');
            var idx = btn.data('idx');

            if (!idno) {
                alert("Data ID tidak valid untuk dihapus."); return;
            }

            if (!confirm('PERINGATAN: Apakah Anda yakin ingin menghapus jadwal dan riwayat matrix item ini dari tabel produksi?')) {
                return;
            }

            $.ajax({
                url: 'ajax_delete_schedule_item.php', 
                type: 'POST',
                data: { id_no: idno },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        $('#workspace_' + idx).slideUp(400, function() {
                            $(this).remove();
                            updateEmptyDatesSummary();
                            updateMinusSummary();
                        });
                    } else {
                        alert('Gagal menghapus jadwal: ' + res.message);
                    }
                },
                error: function() { 
                    alert('Error koneksi saat menghapus jadwal.'); 
                }
            });
        });

        // 5. Tombol Save Schedule (Prod Plan R0)
        $('#workspaceContainer').on('click', '.btn-action-save', function() {
            var btn = $(this);
            var idx = btn.data('idx');
            var idno = btn.data('idno');
            var workspace = $('#workspace_' + idx);
            var statusSpan = $('#saveStatus_' + idx);

            if (!idno || idno == 0) {
                alert("Simpan gagal: ID NO tidak valid."); return;
            }

            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Memproses...');
            statusSpan.removeClass('ok warn').text('Menyimpan data...');

            var r0Data = {};
            for (var i = 1; i <= 31; i++) {
                r0Data['d'+i] = parseFloat(workspace.find('.input-r0.day-'+i).val()) || 0;
            }

            $.ajax({
                url: 'ajax_save_all_r0.php', 
                type: 'POST',
                data: { id_no: idno, r0: r0Data },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        statusSpan.removeClass('warn').addClass('ok').text('Jadwal R0 Tersimpan!');
                        setTimeout(function(){ statusSpan.fadeOut(function(){ $(this).text('').show(); }); }, 3000);
                    } else {
                        statusSpan.removeClass('ok').addClass('warn').text('Gagal: ' + res.message);
                    }
                },
                error: function() { 
                    statusSpan.removeClass('ok').addClass('warn').text('Error jaringan.'); 
                },
                complete: function() { 
                    btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save'); 
                }
            });
        });

        // =====================================================================
        // AUTOCOMPLETE UX & EVENT LISTENERS LAINNYA
        // =====================================================================
        $('#txtSearchItemSch').autocomplete({
            minLength: 2,
            source: function(request, response) {
                $.ajax({
                    url: 'ajax_autocomplete_item_sch.php',
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        term: request.term,
                        tahun: $('#cbTahun').val(),
                        bulan: $('#cbBulan').val()
                    },
                    success: function(data) { response(data); },
                    error: function() { response([]); }
                });
            },
            select: function(event, ui) {
                $('#txtSearchItemSch').val(ui.item.value);
                var macCode = ui.item.mac_code;
                var magStation = ui.item.mag_station;

                if (confirm("INFORMASI RUTING MESIN:\nItem " + ui.item.value + " (" + ui.item.name + ") dijadwalkan pada:\n\nGroup: " + magStation + "\nMesin: " + macCode + "\n\nApakah Anda ingin memuat jadwal mesin ini sekarang?")) {
                    
                    $('#cbMachGroup').val(magStation).trigger('change');
                    
                    setTimeout(function() {
                        $('#cbMachCode').val(macCode);
                        $('#btnLoad').trigger('click'); 
                        $('#txtSearchItemSch').val('');
                    }, 200);

                } else {
                    $('#txtSearchItemSch').val('');
                }
                return false;
            }
        }).autocomplete("instance")._renderItem = function(ul, item) {
            return $("<li>")
                .append("<div><b style='color:#d32f2f;'>" + escapeHtml(item.value) + "</b><br><span style='color:#666; font-size:10px;'>" + escapeHtml(item.name) + " | Mesin: " + escapeHtml(item.mac_code) + "</span></div>")
                .appendTo(ul);
        };

        $('#txtNewItemCode').autocomplete({ 
            minLength: 2, 
            source: 'ajax_autocomplete.php?type=item',
            select: function(event, ui) {
                $('#txtNewItemCode').val(ui.item.value);
                return false; 
            }
        }).autocomplete("instance")._renderItem = function(ul, item) {
            return $("<li>")
                .append("<div><b>" + escapeHtml(item.value) + "</b><br><span style='font-size:10px; color:#666;'>" + escapeHtml(item.desc || '') + "</span></div>")
                .appendTo(ul);
        };

        $('#btnAddItem').click(function() {
            var tahun = $('#cbTahun').val();
            var bulan = $('#cbBulan').val();
            var machGroup = $('#cbMachGroup').val();
            var mcNo  = $('#cbMachCode').val(); 
            var itemCode = $('#txtNewItemCode').val();
            
            if ($.trim(mcNo) === '') { alert('Pilih Machine Code terlebih dahulu.'); $('#cbMachCode').focus(); return; }
            if ($.trim(itemCode) === '') { alert('Ketik Item Code yang ingin ditambahkan.'); $('#txtNewItemCode').focus(); return; }
            
            var btn = $(this);
            btn.html('<i class="fa fa-spinner fa-spin"></i> Adding...').prop('disabled', true);
            
            $.ajax({
                url: urlAddScheduleItem, type: 'POST', 
                data: { tahun: tahun, bulan: bulan, mach_group: machGroup, mc_no: mcNo, item_code: itemCode }, 
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') { 
                        $('#txtNewItemCode').val(''); 
                        $('#btnLoad').trigger('click'); 
                    } else { alert('Gagal menambah item: ' + res.message); }
                },
                error: function() { alert("ERROR SISTEM: Gagal terhubung ke server."); },
                complete: function() { btn.html('<i class="fa fa-plus"></i> Add Item').prop('disabled', false); }
            });
        });

        // =====================================================================
        // LOAD SCHEDULE & BIND BOM (AJAX GET ASSEMBLY)
        // =====================================================================
        $('#btnLoad').click(function() {
            var tahun = $('#cbTahun').val();
            var bulan = $('#cbBulan').val();
            var machGroup = $('#cbMachGroup').val();
            var mcNo  = $('#cbMachCode').val(); 
            
            if ($.trim(mcNo) === '') { alert('Peringatan: Silakan pilih Machine Code terlebih dahulu.'); $('#cbMachCode').focus(); return; }
            
            $('#minusAlert').hide();
            $('#emptyDatesAlert').hide(); 
            $('#workspaceContainer').html('<div class="panel" style="text-align:center; padding:50px;"><i class="fa fa-spinner fa-spin fa-2x"></i><br><br>Memuat jadwal...</div>');

            $.ajax({
                url: urlLoadGrid, type: 'GET',
                data: { tahun: tahun, bulan: bulan, mach_group: machGroup, mc_no: mcNo },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        var allHtml = '';
                        $.each(res.items, function(index, item) {
                            allHtml += buildWorkspaceHtml(index, item);
                        });
                        $('#workspaceContainer').html(allHtml);
                        
                        // Hitung matriks dan Muat BOM untuk setiap item
                        $.each(res.items, function(index, item) { 
                            recalcMatrix(index); 
                            
                            // Eksekusi AJAX otomatis untuk memuat opsi Dropdown BOM menggunakan ajax_get_assembly.php
                            var cbAssembly = $('#workspace_' + index).find('.cb-assembly');
                            var itemCode = item.header.ITEM_CODE;
                            
                            $.ajax({
                                url: 'ajax_get_assembly.php', // URL sudah diperbarui
                                type: 'GET',
                                data: { item_code: itemCode },
                                dataType: 'json',
                                success: function(bomRes) {
                                    cbAssembly.empty();
                                    // Pengecekan disesuaikan dengan response: { status: 'success', data: [...] }
                                    if(bomRes && bomRes.status === 'success' && bomRes.data && bomRes.data.length > 0) {
                                        $.each(bomRes.data, function(i, partCode) {
                                            cbAssembly.append('<option value="'+escapeHtml(partCode)+'">'+escapeHtml(partCode)+'</option>');
                                        });
                                    } else {
                                        cbAssembly.append('<option value="">-- Tidak ada BOM --</option>');
                                    }
                                },
                                error: function() { 
                                    cbAssembly.html('<option value="">-- Gagal memuat BOM --</option>'); 
                                }
                            });
                        });
                        
                        updateEmptyDatesSummary();

                    } else {
                        $('#workspaceContainer').html('<div class="panel" style="text-align:center; color:#d9534f; padding:20px;"><b>Pemberitahuan:</b> ' + escapeHtml(res.message) + '</div>');
                    }
                },
                error: function(xhr) {
                    $('#workspaceContainer').html('<div class="panel" style="background-color:#ffebee; padding:20px; text-align:center;"><strong style="color:#c62828;">GAGAL MEMUAT DATA</strong><br>Pastikan koneksi jaringan stabil.</div>');
                }
            });
        });

        $('#workspaceContainer').on('input change', '.input-r0', function() {
            var workspaceIdx = $(this).closest('.workspace').attr('id').split('_')[1];
            $(this).data('modified', true).addClass('modified');
            recalcMatrix(workspaceIdx);
        });

    });
    </script>
</body>
</html>
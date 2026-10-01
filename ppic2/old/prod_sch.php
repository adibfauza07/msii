<?php
// prod_sch.php
$configPath = __DIR__ . "/../config/global.php"; 
if (file_exists($configPath)) { require_once $configPath; }

if (session_status() == PHP_SESSION_NONE) { session_start(); }

$currentYear = (int)date('Y');
$currentMonth = (int)date('n');

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
        
        .ui-autocomplete { font-size: 11px; max-height: 250px; overflow-y: auto; overflow-x: hidden; z-index: 9999; border: 1px solid #0056b3; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .ui-menu-item-wrapper { padding: 5px 8px !important; border-bottom: 1px solid #eee; }
        .ui-state-active { background-color: #e6f7ff !important; border-color: #99c2ff !important; color: #333 !important; }

        .workspace { display: flex; width: 100%; gap: 15px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 3px dashed #b0bec5; flex-direction: column; transition: all 0.3s ease; }
        .workspace-top { display: flex; width: 100%; gap: 15px; }
        .workspace:last-child { border-bottom: none; }
        .left-pane { flex: 0 0 240px; width: 240px; }
        .right-pane { flex: 1; min-width: 0; overflow: hidden; } 
        
        .pref-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .pref-table td { padding: 4px; border: 1px solid #ddd; }
        .pref-table td.label-cell { background-color: #f2f2f2; font-weight: bold; width: 115px; text-align: right; }
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
        .val-minus { color: #cc0000 !important; background-color: #ffe6e6 !important; font-weight: bold; }
        
        .action-bar { padding: 8px 12px; background: #e9ecef; border: 1px solid #bbb; border-top: none; text-align: left; }
        .action-bar button { height: 28px; padding: 4px 15px; font-size: 11px; margin-right: 8px; }
        .save-status { font-size: 11px; margin-left: 15px; padding: 3px 8px; border-radius: 3px; display: inline-block; vertical-align: middle; }
        .save-status.ok { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .save-status.warn { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }

        .alert-minus { background-color: #ffebee; color: #c62828; border: 1px solid #ef9a9a; padding: 12px 15px; border-radius: 4px; margin-bottom: 10px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .alert-info { background-color: #e3f2fd; color: #0d47a1; border: 1px solid #bbdefb; padding: 12px 15px; border-radius: 4px; margin-bottom: 15px; font-weight: 700; display: flex; align-items: center; gap: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }

        /* Highlight Weekend (Sabtu & Minggu) */
        .weekend-bg { background-color: #ffccd5 !important; }
        .weekend-bg input.input-r0 { background-color: #ffccd5 !important; }
        .weekend-bg input.input-r0:focus { background-color: #fff !important; }

        @media print {
            @page { size: A4 landscape; margin: 5mm; }
            body { background-color: #f4f6f9; padding: 0; margin: 0; -webkit-print-color-adjust: exact !important; color-adjust: exact !important; zoom: 85%; }
            hr, .action-bar, #minusAlert, #emptyDatesAlert, .btn-load-assembly-del, #btnAddItem, span.save-status, .ui-autocomplete { display: none !important; }
            div[style*="background-color: #e9ecef"] { display: none !important; } 
            .panel { border: 1px solid #ccc !important; box-shadow: none !important; padding: 10px !important; margin-bottom: 10px !important; }
            .panel-title { font-size: 11px !important; margin-bottom: 8px !important; padding-bottom: 4px !important; color: #0056b3 !important; }
            .workspace { display: flex !important; flex-direction: row !important; gap: 10px !important; page-break-inside: avoid; border: none !important; margin: 0 0 15px 0 !important; padding: 0; }
            .workspace-top { display: flex !important; flex-direction: row !important; width: 100%; gap: 10px !important; }
            .left-pane { flex: 0 0 240px !important; width: 240px !important; margin: 0 !important; }
            .pref-table { font-size: 9px !important; border-spacing: 0; }
            .pref-table td { padding: 2px !important; border: none; }
            .pref-table td.label-cell { width: 95px !important; text-align: right; font-weight: 600; color: #555; background-color: transparent !important; }
            .pref-table input[type="text"] { font-size: 9px !important; padding: 2px !important; border: 1px solid #ccc !important; width: 100%; box-sizing: border-box; font-weight: bold; background: transparent !important; }
            .pref-table a { font-size: 9px !important; text-decoration: none !important; color: #000 !important; }
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

    <div class="panel">
        <div class="panel-title"><i class="fa fa-search"></i> Search & Parameters</div>
        <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: center;">
            
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
                    <button type="button" id="btnLoadAll" class="btn-secondary" style="background-color: #17a2b8; border-color: #117a8b; margin-left: 5px;"><i class="fa fa-globe"></i> Load All Mesin</button>
                </div>
                
            </div>

            <div style="border-left: 2px solid #ccc; padding-left: 20px;">
                <div class="form-group">
                    <label style="color:#d32f2f;"><i class="fa fa-map-marker"></i> Find Item Machine:</label>
                    <input type="text" id="txtSearchItemSch" size="25" placeholder="Ketik Item Code (Load Semua Mesin)..." style="border: 1px solid #d32f2f; font-weight: bold; background-color:#fff8e1;" autocomplete="off">
                </div>
            </div>

        </div>

        <hr style="border:0; border-top:1px dashed #ccc; margin:15px 0;">
        
        <div style="background-color: #e9ecef; padding: 8px; border-radius: 4px; display: inline-block;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="txtNewItemCode" style="color: #28a745;"><i class="fa fa-plus-circle"></i> Add Item Schedule:</label>
                <input type="text" id="txtNewItemCode" value="" placeholder="Ketik Item Code..." size="25" autocomplete="off">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <input type="text" id="txtNoUrut" placeholder="No. Urut" style="width: 70px; text-align: center; border: 1px solid #ccc; padding: 4px; height: 26px; box-sizing: border-box;" autocomplete="off">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <button id="btnAddItem" class="btn-success"><i class="fa fa-plus"></i> Add Item</button>
            </div>
            <span style="font-size:11px; color:#666; margin-left: 10px;">(Pilih Mesin, ketik Item Code & No Urut, lalu Add)</span>
            
            <div class="form-group" style="margin-bottom: 0; border-left: 2px solid #ccc; padding-left: 15px;">
                <button type="button" id="btnLogicalStok" class="btn-warning" style="background-color: #17a2b8; border-color: #117a8b; color: white;">
                    <i class="fa fa-cubes"></i> Logical Stok
                </button>
            </div>
        </div>
        
    </div>

    <div id="minusAlert" class="alert-minus" style="display:none;"></div>
    <div id="emptyDatesAlert" class="alert-info" style="display:none;"></div>

    <div id="workspaceContainer">
        <div class="panel" style="text-align:center; padding:50px; color:#666;">
            Silakan pilih Machine Code dan klik "Load Schedule" atau cari Item melalui "Find Item Machine".
        </div>
    </div>

    <!-- Gunakan jquery versi stabil -->
    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    
    <script>
    var allMachines = <?php echo json_encode($arrMachine); ?>;

    function escapeHtml(unsafe) {
        if (unsafe === null || unsafe === undefined) return '';
        return unsafe.toString().replace(/[&<>"']/g, function(m) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[m];
        });
    }
    
    function saveWorkspaceData(workspace) {
        var idno = workspace.find('.btn-action-save').data('idno');
        var statusSpan = workspace.find('.save-status');

        if (!idno || idno == 0) {
            alert("Simpan gagal: ID NO tidak valid.");
            return;
        }

        var r0Data = {};
        for (var i = 1; i <= 31; i++) {
            r0Data['d'+i] = parseFloat(workspace.find('.input-r0.day-'+i).val()) || 0;
        }

        statusSpan.removeClass('ok warn').text('Menyimpan...').show();

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
            }
        });
    }
    
    $('#btnLogicalStok').click(function() {
        var tahun = $('#cbTahun').val();
        var bulan = $('#cbBulan').val();
        var mcNo  = $('#cbMachCode').val();
        var url = 'logical_stok.php?tahun=' + tahun + '&bulan=' + bulan + '&mc_no=' + mcNo;
        window.open(url, '_blank');
    });

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

    function sortWorkspacesLocally() {
        var $container = $('#workspaceContainer');
        var $workspaces = $container.children('.workspace');

        $workspaces.sort(function(a, b) {
            var valA = parseInt($(a).attr('data-urut')) || 99999;
            var valB = parseInt($(b).attr('data-urut')) || 99999;
            return valA - valB;
        });

        $workspaces.detach().appendTo($container);
    }

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
                if (days[i] === end + 1) { end = days[i]; } 
                else {
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

    // Menambahkan proteksi isBulkLoad agar performa browser tidak lambat
    function recalcMatrix(idx, isBulkLoad) {
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

            var ng   = parseFloat(table.find('.cell-ng.day-' + d).text()) || 0;   
            var lo   = parseFloat(table.find('.cell-lo.day-' + d).text()) || 0;   
            var rtc  = parseFloat(table.find('.cell-rtc.day-' + d).text()) || 0;  
            var rfc  = parseFloat(table.find('.cell-rfc.day-' + d).text()) || 0;  
            var rtc2 = parseFloat(table.find('.cell-rtc2.day-' + d).text()) || 0; 
            var rfc2 = parseFloat(table.find('.cell-rfc2.day-' + d).text()) || 0;

            runDelBal  = runDelBal + (da - dp); 
            runProdBal = runProdBal + (po - pp); 
            
            var stockAdjustment = rfc + rfc2 - lo - rtc - rtc2 - ng;

            runPlan = runPlan + pp - dp + stockAdjustment;
            runAct  = runAct + po + ph - da + stockAdjustment;

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

        if (!isBulkLoad) {
            updateMinusSummary(); 
            updateEmptyDatesSummary();
        }
    }

    function buildWorkspaceHtml(index, item, tahun, bulan) {
        var h = item.header;
        var begStock   = parseFloat(h.BEG_BALANCE) || 0; 
        var prevEstAct = parseFloat(h.PREV_EST_ACTUAL) || 0; 
        var rawSafety  = parseFloat(h.SAFETY_STK) || (begStock * 0.25); 
        var safetyStk  = Math.round(rawSafety); 
        var safeItemCode = escapeHtml(h.ITEM_CODE);
        var woCap = parseFloat(h.CAP_DAY_STD) || 0;
        var workDayCalc = parseFloat(h.WORK_DAY) || 0;
        var noUrut = h.NO_URUT || h.no_urut || ''; 
        var idNo = h.ID_NO || 0; 
        var sortVal = parseInt(noUrut);
        if (isNaN(sortVal)) sortVal = 99999;
        
        var padBulan = bulan.toString().length === 1 ? '0' + bulan : bulan;
        var startDateStr = tahun + '-' + padBulan + '-01';
        
        var today = new Date();
        var tTahun = today.getFullYear();
        var tBulan = (today.getMonth() + 1).toString();
        var padTBulan = tBulan.length === 1 ? '0' + tBulan : tBulan;
        var tHari = today.getDate().toString();
        var padTHari = tHari.length === 1 ? '0' + tHari : tHari;
        var endDateStr = tTahun + '-' + padTBulan + '-' + padTHari;

        var stokUrl = 'stok.php?report_type=barang&item_id=' + encodeURIComponent(h.ITEM_CODE) + '&start_date=' + startDateStr + '&end_date=' + endDateStr;
        
        var badgeUrut = '<span style="background-color:#dc3545; color:#fff; padding:3px 6px; border-radius:3px; margin-left:10px; font-size:11px; display:inline-flex; align-items:center;">' + 
                        'No. Urut: <input type="text" class="input-no-urut" data-idno="' + idNo + '" value="' + escapeHtml(noUrut) + '" ' + 
                        'style="width:30px; height:18px; padding:0 2px; margin-left:5px; text-align:center; border:none; border-radius:2px; color:#000; font-weight:bold;">' + 
                        '</span>';
        
        var html = '<div class="workspace" id="workspace_' + index + '" data-urut="' + sortVal + '"><div class="workspace-top">';
        
        html += '<div class="left-pane"><div class="panel" style="margin-bottom:0; padding:10px;">';
        html += '<div class="panel-title" style="color:#0056b3; font-size:13px; padding-bottom:6px;"><i class="fa fa-cube"></i> Item Code: ' + safeItemCode + badgeUrut + '</div>';
        
        html += '<table class="pref-table">';
        html += '<tr><td class="label-cell">Machine No</td><td><input type="text" data-field="MC_NO" value="' + escapeHtml(h.MC_NO) + '" readonly style="background-color:#e8f4f8;"></td></tr>';
        html += '<tr><td class="label-cell">Part Name</td><td><input type="text" data-field="PART_NAME" value="' + escapeHtml(h.PART_NAME) + '" readonly></td></tr>';
        html += '<tr><td class="label-cell">Part No</td><td><input type="text" data-field="PART_NO" value="' + escapeHtml(h.PART_NO) + '" readonly></td></tr>';
        html += '<tr><td class="label-cell">Item Code</td><td style="background:#fafafa;"><a href="' + stokUrl + '" target="_blank" style="color:#d32f2f; font-weight:bold; text-decoration:underline; display:block; padding:3px;" title="Lihat Analisis Stok (s/d Hari Ini)">' + safeItemCode + ' <i class="fa fa-external-link"></i></a></td></tr>';
        html += '<tr><td class="label-cell">Customer</td><td><input type="text" data-field="CUST" value="' + escapeHtml(h.CUST) + '" readonly></td></tr>';
        html += '<tr><td class="label-cell">Estimation Order</td><td><input type="text" data-field="CUR_PO_BO" value="' + escapeHtml(h.CUR_PO_BO) + '" readonly></td></tr>';
        html += '<tr><td class="label-cell">Safety Stock</td><td><input type="text" data-field="SAFETY_STK" value="' + safetyStk + '" readonly style="background-color:#fff3cd;"></td></tr>';
        html += '<tr><td class="label-cell">Beginning Stock</td><td><input type="text" data-field="BEG_BALANCE" value="' + begStock + '" readonly style="background-color:#ffeeba; color:#d32f2f;"></td></tr>';
        html += '<tr><td class="label-cell">Last Mth Actual</td><td><input type="text" data-field="PREV_EST_ACTUAL" value="' + prevEstAct + '" readonly style="background-color:#e6f7ff; color:#0056b3;"></td></tr>';
        html += '<tr><td class="label-cell">Cycle Time Std</td><td><input type="text" data-field="CYCLE_TIME_STD" value="' + escapeHtml(h.CYCLE_TIME_STD) + '" readonly></td></tr>';
        html += '<tr><td class="label-cell">Cavity Std</td><td><input type="text" data-field="CAVITY_STD" value="' + escapeHtml(h.CAVITY_STD) + '" readonly></td></tr>';
        html += '<tr><td class="label-cell">Cap / Days Std</td><td><input type="text" data-field="CAP_DAY_STD" value="' + woCap + '" readonly></td></tr>';
        html += '<tr><td class="label-cell">Forecast+1</td><td><input type="text" data-field="PROD_PLAN" value="' + escapeHtml(h.PROD_PLAN) + '" readonly></td></tr>';
        html += '<tr><td class="label-cell">Working Days</td><td><input type="text" data-field="WORK_DAY" value="' + workDayCalc + '" readonly></td></tr>';
        html += '</table></div></div>';

        html += '<div class="right-pane"><div class="panel" style="margin-bottom:0; padding:0;">';
        var historyUrl = 'prod_history.php?item_code=' + encodeURIComponent(h.ITEM_CODE);
        html += '<div style="background-color: #fff3cd; padding: 6px 10px; border: 1px solid #bbb; border-bottom: none; display: flex; align-items: center;">';
        html += '  <label style="color:#856404; margin-right: 8px;"><i class="fa fa-sitemap"></i> Assembly Part:</label>';
        html += '  <select class="cb-assembly" data-item="' + safeItemCode + '" style="width: 180px; margin-right: 8px;"><option value="">-- Memuat BOM... --</option></select>';
        html += '  <button type="button" class="btn-warning btn-load-assembly-del" data-idx="' + index + '" style="height: 24px; padding: 2px 10px; font-size: 11px;"><i class="fa fa-download"></i> Pull Delivery</button>';
		html += '  <a href="' + historyUrl + '" target="_blank" class="btn-secondary" style="height: 24px; padding: 2px 10px; font-size: 11px; text-decoration:none; display:inline-flex; align-items:center; gap:4px; margin-left: 8px; background-color: #17a2b8; border-color: #117a8b; color: white;"><i class="fa fa-history"></i> Prod History</a>';
        html += '</div>';

        html += '<div class="table-container"><table class="grid-table" id="matrix_' + index + '" data-begstock="' + begStock + '">';
        html += '<thead><tr><th class="desc-col">Description</th><th class="gtotal-col">Grand Total</th>';
        
        for (var i = 1; i <= 31; i++) { 
            var dt = new Date(tahun, bulan - 1, i);
            var isWeekend = (dt.getMonth() == (bulan - 1) && (dt.getDay() === 0 || dt.getDay() === 6));
            var wClass = isWeekend ? ' weekend-bg' : '';
            html += '<th class="day-col' + wClass + '">' + i + '</th>'; 
        }
        html += '</tr></thead><tbody>';
        
        var idNoRecord = 0; 
        
        $.each(item.data, function(idx, row) {
            var isR0  = (row.DESC_PROD === 'Prod Plan R0');
            var rName = $.trim(row.DESC_PROD);
            if (row.ID_NO) idNoRecord = row.ID_NO;
            if (rName === 'Prod Plan R1') return true; 
            
            var rowClass = '';
            if (rName === 'Del Plan') rowClass = 'cell-dp';
            else if (rName === 'Del Actual') rowClass = 'cell-da';
            else if (rName === 'Prod OK') rowClass = 'cell-po';
            else if (rName === 'Prod NG') rowClass = 'cell-png'; 
            else if (rName === 'Prod HOLD') rowClass = 'cell-ph';
            else if (rName === 'Del Balance') rowClass = 'cell-delbal';
            else if (rName === 'Prod Balance') rowClass = 'cell-prodbal';
            else if (rName === 'NG Rework') rowClass = 'cell-ng';
            else if (rName === 'Limbah Out') rowClass = 'cell-lo';
            else if (rName === 'Repl To Customer') rowClass = 'cell-rtc';
            else if (rName === 'Retur From Cust') rowClass = 'cell-rfc';
            else if (rName === 'Retur To Cust2') rowClass = 'cell-rtc2';
            else if (rName === 'Replace From Cust2') rowClass = 'cell-rfc2';
            else if (rName === 'Est Stock Plan') rowClass = 'cell-estplan';
            else if (rName === 'Est Stock Actual') rowClass = 'cell-estact';
            
            var displayDesc = escapeHtml(row.DESC_PROD);
            var descStyle = '';
            
            if (isR0) {
                displayDesc = 'PROD PLAN';
                descStyle = 'background-color: #d4edda; color: #155724;'; 
            }
            
            html += '<tr><td class="desc-col" style="' + descStyle + '">' + displayDesc + '</td><td class="gtotal-col">' + (row.G_TOTAL || 0) + '</td>';
            
            for (var d = 1; d <= 31; d++) {
                var dt2 = new Date(tahun, bulan - 1, d);
                var isWeekend = (dt2.getMonth() == (bulan - 1) && (dt2.getDay() === 0 || dt2.getDay() === 6));
                var wClass = isWeekend ? ' weekend-bg' : '';
                var cellVal = row['D' + d] || 0;
                
                if (isR0) {
                    html += '<td class="day-col' + wClass + '"><input type="text" class="input-r0 day-' + d + '" value="' + cellVal + '" /></td>';
                } else {
                    var tdClass = (cellVal < 0) ? ' val-minus' : '';
                    html += '<td class="day-col ' + rowClass + ' day-' + d + tdClass + wClass + '">' + cellVal + '</td>';
                }
            }
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        
        html += '<div class="action-bar">';
        html += '<button type="button" class="btn-primary btn-action-load" data-idx="' + index + '"><i class="fa fa-refresh"></i> Load / Re-Calc</button>';
        html += '<button type="button" class="btn-success btn-action-save" data-idx="' + index + '" data-idno="' + idNoRecord + '"><i class="fa fa-save"></i> Save</button>';
        html += '<button type="button" class="btn-danger btn-action-delete" data-idx="' + index + '" data-idno="' + idNoRecord + '"><i class="fa fa-trash"></i> Delete</button>';
        html += '<button type="button" class="btn-secondary btn-print-sch" data-idx="' + index + '"><i class="fa fa-print"></i> Print</button>';
        html += '<span class="save-status" id="saveStatus_' + index + '"></span>';
        html += '</div></div></div></div></div>'; 
        
        return html;
    }

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

        $('#workspaceContainer').on('click', '.btn-print-sch', function() {
            var currentWorkspace = $(this).closest('.workspace');
            $('.workspace').addClass('no-print');
            currentWorkspace.removeClass('no-print');
            window.print();
            $('.workspace').removeClass('no-print');
        });

        $('#workspaceContainer').on('click', '.btn-action-load', function() {
            var idx = $(this).data('idx');
            recalcMatrix(idx, false);
            $('#saveStatus_' + idx).removeClass('warn').addClass('ok').text('Kalkulasi ulang berhasil.').show().delay(2000).fadeOut();
        });

       $('#workspaceContainer').on('click', '.btn-load-assembly-del', function() {
            var btn = $(this);
            var idx = btn.data('idx');
            var workspace = $('#workspace_' + idx);
            var cbAssembly = workspace.find('.cb-assembly').val();
            var idNoRecord = workspace.find('.btn-action-save').data('idno'); 
            var tahun = $('#cbTahun').val();
            var bulan = $('#cbBulan').val();

            if(!cbAssembly) { alert('Pilih Part BOM (Assembly Part) terlebih dahulu.'); return; }
            if(!idNoRecord || idNoRecord == 0) { alert('ID_NO tidak ditemukan. Silakan load ulang jadwal.'); return; }

            var originalHtml = btn.html();
            btn.html('<i class="fa fa-spinner fa-spin"></i> Pulling...').prop('disabled', true);

            $.ajax({
                url: 'ajax_pull_delivery.php', 
                type: 'POST',
                data: { assembly_code: cbAssembly, tahun: tahun, bulan: bulan, id_no: idNoRecord },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        for (var d = 1; d <= 31; d++) {
                            var planVal = res.data.plan['D' + d] || 0;
                            var actVal  = res.data.actual['D' + d] || 0;
                            workspace.find('.cell-dp.day-' + d).text(planVal);
                            workspace.find('.cell-da.day-' + d).text(actVal);
                        }
                        recalcMatrix(idx, false);
                        $('#saveStatus_' + idx).removeClass('warn').addClass('ok')
                            .text('Delivery ditarik & tersimpan!').show().delay(3000).fadeOut();
                    } else { alert('Gagal Pull Delivery: ' + res.message); }
                },
                error: function() { alert('Error koneksi saat mengeksekusi Pull Delivery.'); },
                complete: function() { btn.html(originalHtml).prop('disabled', false); }
            });
        });

        $('#workspaceContainer').on('click', '.btn-action-delete', function() {
            var btn = $(this);
            var idno = btn.data('idno');
            var idx = btn.data('idx');
            if (!idno) { alert("Data ID tidak valid untuk dihapus."); return; }
            if (!confirm('PERINGATAN: Apakah Anda yakin ingin menghapus jadwal dan riwayat matrix item ini?')) return;

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
                    } else { alert('Gagal menghapus jadwal: ' + res.message); }
                },
                error: function() { alert('Error koneksi saat menghapus jadwal.'); }
            });
        });

        $('#workspaceContainer').on('click', '.btn-action-save', function() {
            var idx = $(this).data('idx');
            var workspace = $('#workspace_' + idx);
            saveWorkspaceData(workspace);
        });

        $('#txtSearchItemSch').autocomplete({
            minLength: 2,
            source: function(request, response) {
                $.ajax({
                    url: 'ajax_autocomplete_item_sch.php',
                    type: 'GET',
                    dataType: 'json',
                    data: { term: request.term, tahun: $('#cbTahun').val(), bulan: $('#cbBulan').val() },
                    success: function(data) { response(data); },
                    error: function() { response([]); }
                });
            },
            select: function(event, ui) {
                var searchItem = ui.item.value;
                $('#txtSearchItemSch').val(searchItem);
                
                if (confirm("Tampilkan jadwal Item " + searchItem + " di SEMUA mesin sekaligus ke layar?")) {
                    triggerSequentialLoad($('#cbTahun').val(), $('#cbBulan').val(), 'ALL', 'ALL', searchItem);
                } else {
                    var macCode = ui.item.mac_code;
                    var magStation = ui.item.mag_station;
                    $('#cbMachGroup').val(magStation).trigger('change');
                    setTimeout(function() {
                        $('#cbMachCode').val(macCode);
                        triggerSequentialLoad($('#cbTahun').val(), $('#cbBulan').val(), magStation, macCode, '');
                    }, 200);
                }
                setTimeout(function(){ $('#txtSearchItemSch').val(''); }, 500);
                return false;
            }
        }).autocomplete("instance")._renderItem = function(ul, item) {
            return $("<li>")
                .append("<div><b style='color:#d32f2f;'>" + escapeHtml(item.value) + "</b><br><span style='color:#666; font-size:10px;'>" + escapeHtml(item.name) + " | Mesin Tersedia: " + escapeHtml(item.mac_code) + "</span></div>")
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
            var noUrut = $('#txtNoUrut').val(); 
            
            if ($.trim(mcNo) === '') { alert('Pilih Machine Code terlebih dahulu.'); $('#cbMachCode').focus(); return; }
            if ($.trim(itemCode) === '') { alert('Ketik Item Code yang ingin ditambahkan.'); $('#txtNewItemCode').focus(); return; }
            
            var btn = $(this);
            btn.html('<i class="fa fa-spinner fa-spin"></i> Adding...').prop('disabled', true);
            
            $.ajax({
                url: urlAddScheduleItem, type: 'POST', 
                data: { tahun: tahun, bulan: bulan, mach_group: machGroup, mc_no: mcNo, item_code: itemCode, no_urut: noUrut }, 
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') { 
                        $('#txtNewItemCode').val(''); 
                        $('#txtNoUrut').val(''); 
                        $('#btnLoad').trigger('click'); 
                    } else { alert('Gagal menambah item: ' + res.message); }
                },
                error: function() { alert("ERROR SISTEM: Gagal terhubung ke server."); },
                complete: function() { btn.html('<i class="fa fa-plus"></i> Add Item').prop('disabled', false); }
            });
        });

        // =========================================================================
        // SEQUENTIAL LOAD SYSTEM (SISTEM ANTREAN MUAT MESIN)
        // =========================================================================
        var isProcessingAll = false;

        function triggerSequentialLoad(tahun, bulan, machGroup, mcNo, searchItem) {
            searchItem = searchItem || '';

            if (isProcessingAll) {
                alert("Harap tunggu, sistem sedang memproses antrean jadwal...");
                return;
            }
            
            isProcessingAll = true;
            $('#minusAlert').hide();
            $('#emptyDatesAlert').hide(); 
            $('#workspaceContainer').html('<div class="panel" style="text-align:center; padding:50px;"><i class="fa fa-spinner fa-spin fa-2x"></i><br><br><span id="loadingText">Menyusun antrean jadwal dari server...</span></div>');

            // 1. Meminta Daftar Antrean ke Server
            $.ajax({
                url: urlLoadGrid,
                type: 'GET',
                cache: false,
                data: { action: 'get_queue', tahun: tahun, bulan: bulan, mach_group: machGroup, mc_no: mcNo, search_item: searchItem },
                dataType: 'json',
                success: function(resQueue) {
                    if (resQueue.status === 'success') {
                        var queue = resQueue.data;
                        if (queue.length === 0) {
                            $('#workspaceContainer').html('<div class="panel" style="text-align:center; color:#d9534f; padding:20px;"><b>Data jadwal tidak ditemukan untuk periode/mesin ini.</b></div>');
                            isProcessingAll = false;
                            return;
                        }

                        $('#workspaceContainer').empty(); 
                        $('#workspaceContainer').before('<div id="progressOverlay" class="panel" style="background-color:#d1ecf1; border-color:#bee5eb; color:#0c5460; text-align:center; font-weight:bold; position:sticky; top:0; z-index:9999; box-shadow: 0 4px 6px rgba(0,0,0,0.1);"><i class="fa fa-spinner fa-spin"></i> Memuat & Sinkronisasi: <span id="saveProgress">0</span> dari ' + queue.length + ' Item diselesaikan...</div>');

                        // 2. Memulai Proses Muat Berjenjang (Index ke-0)
                        processSequentialSchedule(0, queue, tahun, bulan, machGroup, mcNo);
                    } else {
                        $('#workspaceContainer').html('<div class="panel" style="text-align:center; color:#d9534f; padding:20px;"><b>Gagal menyusun antrean.</b></div>');
                        isProcessingAll = false;
                    }
                },
                error: function() {
                    $('#workspaceContainer').html('<div class="panel" style="background-color:#ffebee; padding:20px; text-align:center;"><strong style="color:#c62828;">GAGAL MEMINTA ANTREAN JADWAL</strong></div>');
                    isProcessingAll = false;
                }
            });
        }

        // Fungsi Rekursif (Memanggil Dirinya Sendiri Hingga Antrean Habis)
        function processSequentialSchedule(index, queue, tahun, bulan, machGroup, mcNo) {
            // Selesai jika index mencapai batas
            if (index >= queue.length) {
                $('#progressOverlay').removeClass('fa-spinner fa-spin').css({'background-color':'#d4edda', 'color':'#155724', 'border-color':'#c3e6cb'})
                    .html('<i class="fa fa-check-circle"></i> Selesai! Semua data telah termuat dan tersimpan ke Database. <button onclick="$(\'#progressOverlay\').slideUp(function(){ $(this).remove(); })" style="margin-left:15px; padding:3px 10px; font-size:11px; cursor:pointer;" class="btn-success">Tutup</button>');
                isProcessingAll = false;
                updateEmptyDatesSummary(); 
                updateMinusSummary();
                return;
            }

            var currentItemCode = queue[index];

            $.ajax({
                url: urlLoadGrid,
                type: 'GET',
                cache: false,
                data: { tahun: tahun, bulan: bulan, mach_group: machGroup, mc_no: mcNo, search_item: currentItemCode },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success' && res.items && res.items.length > 0) {
                        var htmlBatch = '';
                        var workspacesToSave = [];

                        $.each(res.items, function(i, item) {
                            var globalIdx = index + "_" + i; 
                            htmlBatch += buildWorkspaceHtml(globalIdx, item, tahun, bulan);
                            
                            var idno = item.header.ID_NO;
                            if (idno && idno != 0) {
                                workspacesToSave.push({ idx: globalIdx, idno: idno });
                            }
                        });

                        $('#workspaceContainer').append(htmlBatch);

                        $.each(res.items, function(i, item) {
                            var globalIdx = index + "_" + i; 
                            recalcMatrix(globalIdx, true); // True = jangan kalkulasi alert minus dulu agar tidak lambat

                            var cbAssembly = $('#workspace_' + globalIdx).find('.cb-assembly');
                            cbAssembly.empty();
                            if(item.bom && item.bom.length > 0) {
                                $.each(item.bom, function(j, partCode) {
                                    cbAssembly.append('<option value="'+escapeHtml(partCode)+'">'+escapeHtml(partCode)+'</option>');
                                });
                            } else {
                                cbAssembly.append('<option value="">-- Tidak ada BOM --</option>');
                            }
                        });

                        // Jalankan Auto-Save untuk baris ini
                        if (workspacesToSave.length > 0) {
                            processAutoSaveSubSequence(workspacesToSave, 0, function() {
                                $('#saveProgress').text(index + 1);
                                processSequentialSchedule(index + 1, queue, tahun, bulan, machGroup, mcNo);
                            });
                        } else {
                            $('#saveProgress').text(index + 1);
                            processSequentialSchedule(index + 1, queue, tahun, bulan, machGroup, mcNo);
                        }

                    } else {
                        // Jika gagal memuat 1 item, lewatkan dan lanjut ke item berikutnya di antrean
                        $('#saveProgress').text(index + 1);
                        processSequentialSchedule(index + 1, queue, tahun, bulan, machGroup, mcNo);
                    }
                },
                error: function() {
                    console.log("Error loading item: " + currentItemCode);
                    $('#saveProgress').text(index + 1);
                    processSequentialSchedule(index + 1, queue, tahun, bulan, machGroup, mcNo);
                }
            });
        }

        function processAutoSaveSubSequence(items, currentIndex, onComplete) {
            if (currentIndex >= items.length) {
                if (typeof onComplete === 'function') onComplete();
                return;
            }

            var currentItem = items[currentIndex];
            var workspace = $('#workspace_' + currentItem.idx);
            var r0Data = {};
            
            for (var i = 1; i <= 31; i++) {
                r0Data['d'+i] = parseFloat(workspace.find('.input-r0.day-'+i).val()) || 0;
            }

            var statusSpan = $('#saveStatus_' + currentItem.idx);
            statusSpan.removeClass('ok warn').text('Auto-Saving...').show();

            $.ajax({
                url: 'ajax_save_all_r0.php', 
                type: 'POST',
                data: { id_no: currentItem.idno, r0: r0Data },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        statusSpan.removeClass('warn').addClass('ok').text('Tersimpan!');
                    } else {
                        statusSpan.removeClass('ok').addClass('warn').text('Gagal');
                    }
                },
                error: function() { statusSpan.removeClass('ok').addClass('warn').text('Error'); },
                complete: function() {
                    setTimeout(function(){ statusSpan.fadeOut(function(){ $(this).text('').show(); }); }, 2000);
                    processAutoSaveSubSequence(items, currentIndex + 1, onComplete);
                }
            });
        }

        // -------------------------------------------------------------------------
        // TRIGGER EVENT UNTUK LOAD DATA
        // -------------------------------------------------------------------------
        $('#btnLoad').click(function() {
            var tahun = $('#cbTahun').val();
            var bulan = $('#cbBulan').val();
            var machGroup = $('#cbMachGroup').val();
            var mcNo  = $('#cbMachCode').val(); 
            
            if ($.trim(mcNo) === '') { 
                alert('Peringatan: Silakan pilih Machine Code terlebih dahulu.'); 
                $('#cbMachCode').focus(); 
                return; 
            }
            triggerSequentialLoad(tahun, bulan, machGroup, mcNo, '');
        });

        $('#btnLoadAll').click(function() {
            var tahun = $('#cbTahun').val();
            var bulan = $('#cbBulan').val();
            
            if (!confirm('Peringatan: Memuat seluruh mesin sekaligus akan memakan waktu proses karena sistem akan menyinkronisasi seluruh jadwal ke database. Lanjutkan?')) {
                return;
            }
            triggerSequentialLoad(tahun, bulan, 'ALL', 'ALL', '');
        });
        
        $('#workspaceContainer').on('input change', '.input-r0', function() {
            var workspaceIdx = $(this).closest('.workspace').attr('id').split('_')[1];
            $(this).data('modified', true).addClass('modified');
            recalcMatrix(workspaceIdx, false);
        });

        $('#workspaceContainer').on('keydown', '.input-r0', function(e) {
            var currentTd = $(this).closest('td');
            var workspace = $(this).closest('.workspace');
            
            if (e.which === 37) { 
                var prevInput = currentTd.prev('td.day-col').find('.input-r0');
                if (prevInput.length > 0) { prevInput.focus(); prevInput.select(); e.preventDefault(); }
            } 
            else if (e.which === 39) { 
                var nextInput = currentTd.next('td.day-col').find('.input-r0');
                if (nextInput.length > 0) { nextInput.focus(); nextInput.select(); e.preventDefault(); }
            }
            else if (e.which === 13) { 
                e.preventDefault();
                saveWorkspaceData(workspace);
                var nextInput = currentTd.next('td.day-col').find('.input-r0');
                if (nextInput.length > 0) { nextInput.focus(); nextInput.select(); }
            }
        });

        $('#workspaceContainer').on('keypress', '.input-no-urut', function(e) {
            if (e.which == 13) {
                e.preventDefault();
                var inputEl = $(this);
                var idno = inputEl.data('idno');
                var newUrut = inputEl.val();
                var workspaceEl = inputEl.closest('.workspace'); 
                
                if (!idno || idno == 0) { alert("Gagal: Data tidak memiliki ID yang valid."); return; }

                var originalBg = inputEl.css('background-color');
                inputEl.css('background-color', '#fff3cd'); 

                $.ajax({
                    url: 'ajax_update_no_urut.php',
                    type: 'POST',
                    data: { id_no: idno, no_urut: newUrut },
                    dataType: 'json',
                    success: function(res) {
                        if(res.status === 'success') {
                            inputEl.css('background-color', '#d4edda'); 
                            var parsedUrut = parseInt(newUrut);
                            if (isNaN(parsedUrut)) parsedUrut = 99999;
                            workspaceEl.attr('data-urut', parsedUrut);

                            setTimeout(function(){ 
                                inputEl.css('background-color', originalBg); 
                                sortWorkspacesLocally(); 
                            }, 500); 
                        } else {
                            alert('Gagal update No Urut: ' + res.message);
                            inputEl.css('background-color', '#f8d7da'); 
                        }
                    },
                    error: function() {
                        alert('Error koneksi saat mengupdate No Urut.');
                        inputEl.css('background-color', '#f8d7da');
                    }
                });
            }
        });
    });
    </script>
</body>
</html>
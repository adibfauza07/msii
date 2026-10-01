<?php
// File: production.php
error_reporting(0);
require_once __DIR__ . '/config/database.php';

$db = isset($conn) ? $conn : (isset($connection) ? $connection : (isset($dbconn) ? $dbconn : null));

$ng_types = array();
if ($db) {
    $sql_ng = "SELECT NGT_ID, NGT_DESC FROM dbo.NG_TYPE ORDER BY NGT_DESC ASC";
    $stmt_ng = sqlsrv_query($db, $sql_ng);
    if ($stmt_ng) {
        while ($row = sqlsrv_fetch_array($stmt_ng, SQLSRV_FETCH_ASSOC)) {
            $ng_types[] = array(
                'id' => $row['NGT_ID'],
                'desc' => trim($row['NGT_DESC'])
            );
        }
    }
}
?>

<style>
    /* Styling Padat / Compact Header */
    .compact-box { margin-bottom: 8px !important; }
    .compact-box .box-header { padding: 5px 10px; }
    .compact-box .box-body { padding: 6px 10px; }
    .compact-form .form-group { margin-bottom: 4px; }
    .compact-form label { margin-bottom: 1px; font-size: 11px; font-weight: 600; color: #333; }
    .compact-form .form-control { height: 24px; padding: 2px 6px; font-size: 11px; }
    .compact-radio label { margin-right: 6px; font-size: 11px; cursor: pointer; }
    
    /* Tabel WO Kanan Padat */
    .table-wo-container { height: 160px; overflow-y: auto; border: 1px solid #d2d6de; background: #fff; }
    .table-wo { font-size: 11px; margin-bottom: 0; }
    .table-wo th { background: #eaeaea; position: sticky; top: 0; z-index: 2; padding: 4px 6px !important; }
    .table-wo td { padding: 3px 6px !important; }
    .table-wo tbody tr:hover { background-color: #d9edf7 !important; cursor: pointer; }
    .table-wo tbody tr.selected { background-color: #337ab7 !important; color: #fff; font-weight: bold; }
    .table-wo tbody tr.selected td { color: #fff; }

    /* Grid Input Bawah */
    .grid-input, .grid-input-act { 
        width: 100%; height: 24px; box-sizing: border-box; border: 1px solid transparent; 
        background: transparent; font-family: inherit; font-size: 11px; padding: 2px 4px; outline: none;
    }
    .grid-input:focus, .grid-input-act:focus { background: #fff; border: 1px solid #3c8dbc; box-shadow: inset 0 0 3px rgba(60,141,188,0.5); }
    .box-icon { display: inline-block; border: 1px solid #777; width: 14px; height: 14px; line-height: 12px; text-align: center; font-size: 10px; font-weight: bold; background: #fff; cursor: pointer; user-select: none; border-radius: 2px; }
    .box-icon:hover { background: #3c8dbc; color: #fff; border-color: #367fa9; }
    
    .actual-container { display: flex; gap: 8px; background: #f4f4f4; padding: 6px 10px; border-left: 3px solid #f39c12; }
    .actual-table { background: #fff; width: 200px; font-size: 10px; margin-bottom: 0; }
    .actual-table td { padding: 1px 4px !important; vertical-align: middle; }
    
    .ng-panel-wrapper { padding: 6px 10px 6px 120px; background-color: #f9f9f9; border-left: 3px solid #dd4b39; }
    .ng-panel { width: 420px; background: #fff; border: 1px solid #ccc; padding: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 3px; }
    .ng-grid input { width: 100%; border: none; padding: 2px 4px; font-size: 11px; outline: none; }
    .ng-grid input:focus { background-color: #e8f0fe; }
</style>

<!-- ========================================== -->
<!-- BAGIAN ATAS (COMPACT & FIT TINGGI LAYAR) -->
<!-- ========================================== -->
<div class="row">
    <!-- PANEL KIRI: ITEM DATA & SETUP -->
    <div class="col-md-5">
        <div class="box box-solid box-primary compact-box">
            <div class="box-header with-border">
                <h3 class="box-title" style="font-size: 12px; font-weight: bold;"><i class="fa fa-sliders"></i> Item Data & Setup</h3>
            </div>
            <div class="box-body compact-form">
                <!-- Baris 1: Tgl, Group & Shift -->
                <div class="row">
                    <div class="col-xs-4 form-group">
                        <label>Date Prod:</label>
                        <input type="date" id="input_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-xs-4 form-group compact-radio">
                        <label>Group:</label><br>
                        <label><input type="radio" name="group" value="A" checked> A</label>
                        <label><input type="radio" name="group" value="B"> B</label>
                        <label><input type="radio" name="group" value="C"> C</label>
                    </div>
                    <div class="col-xs-4 form-group compact-radio">
                        <label>Shift:</label><br>
                        <label><input type="radio" name="shift" value="1" checked> 1</label>
                        <label><input type="radio" name="shift" value="2"> 2</label>
                        <label><input type="radio" name="shift" value="3"> 3</label>
                    </div>
                </div>

                <!-- Baris 2: Cari & Part Code -->
                <div class="row">
                    <div class="col-xs-6 form-group">
                        <label class="text-primary"><i class="fa fa-search"></i> Cari Part / WO:</label>
                        <input type="text" id="search_item" class="form-control font-weight-bold" placeholder="Ketik kode / WO..." autocomplete="off">
                    </div>
                    <div class="col-xs-6 form-group">
                        <label>Part / Item Code:</label>
                        <input type="text" id="item_code" class="form-control font-weight-bold" readonly style="background:#eee; color:#0056b3;">
                    </div>
                </div>

                <!-- Baris 3: Part Name -->
                <div class="row">
                    <div class="col-xs-12 form-group">
                        <label>Part Name:</label>
                        <input type="text" id="item_name" class="form-control" readonly style="background:#eee;">
                    </div>
                </div>

                <!-- Baris 4: Process & M/C -->
                <div class="row">
                    <div class="col-xs-7 form-group">
                        <label>Process:</label>
                        <input type="text" id="proc_name" class="form-control" readonly style="background:#eee;">
                        <input type="hidden" id="proc_id">
                    </div>
                    <div class="col-xs-5 form-group">
                        <label>Machine (M/C):</label>
                        <input type="text" id="mac_code" class="form-control font-weight-bold text-center" readonly style="background:#eee;">
                        <input type="hidden" id="mac_code_hidden">
                    </div>
                </div>

                <input type="hidden" id="wo_id">
                <input type="hidden" id="wo_number">
            </div>
        </div>
    </div>

    <!-- PANEL KANAN: DAFTAR WO -->
    <div class="col-md-7">
        <div class="box box-solid box-default compact-box">
            <div class="box-header with-border" style="background:#f4f4f4;">
                <h3 class="box-title" style="font-size: 12px; font-weight: bold;"><i class="fa fa-list-alt"></i> Daftar Work Order (Tersedia)</h3>
            </div>
            <div class="box-body no-padding">
                <div class="table-wo-container">
                    <table class="table table-bordered table-striped table-wo">
                        <thead>
                            <tr>
                                <th>WO NUMBER</th>
                                <th>ITEM CODE</th>
                                <th class="text-right">QTY</th>
                                <th class="text-center">M/C</th>
                                <th class="text-right">CAP/DAY</th>
                                <th>START</th>
                                <th>END</th>
                            </tr>
                        </thead>
                        <tbody id="wo_table_body">
                            <tr><td colspan="7" class="text-center text-muted" style="padding: 20px;">Ketik kode / nama barang di sebelah kiri untuk menampilkan daftar WO...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- BAGIAN BAWAH: PRODUCTION RESULT (GRID) -->
<!-- ========================================== -->
<div class="row">
    <div class="col-md-12">
        <div class="box box-primary" style="margin-bottom: 5px;">
            <div class="box-header with-border" style="padding: 6px 10px;">
                <h3 class="box-title" style="font-size: 13px; font-weight: bold;"><i class="fa fa-cubes"></i> Production Result (Grid Mode)</h3>
                <div class="box-tools pull-right" style="display: flex; gap: 6px; align-items: center;">
                    <input type="text" id="pd_lot_new" class="form-control input-sm font-weight-bold text-center" readonly placeholder="Auto Lot..." style="background:#fff; width: 130px; height: 26px; padding: 2px;">
                    <button type="button" id="btn_insert_lot" class="btn btn-xs btn-success font-weight-bold" style="padding: 4px 8px;">
                        <i class="fa fa-plus-circle"></i> Tambah Baris Lot
                    </button>
                </div>
            </div>

            <div class="box-body no-padding table-responsive" style="max-height: calc(100vh - 350px); min-height: 220px; overflow-y: auto;">
                <table class="table table-bordered table-condensed" style="font-size: 11px; margin-bottom: 0;">
                    <thead>
                        <tr style="background:#eaeaea;">
                            <th rowspan="2" style="width: 20px; vertical-align: middle; text-align: center;">#</th>
                            <th rowspan="2" style="vertical-align: middle; width: 120px;">LOT #</th>
                            <th rowspan="2" style="width: 30px; text-align: center; vertical-align: middle;">Act</th>
                            <th colspan="6" style="text-align: center; background:#d9edf7;">Result</th>
                            <th rowspan="2" style="vertical-align: middle; width: 80px;">Purging (kg)</th>
                            <th rowspan="2" style="vertical-align: middle;">Remark</th>
                            <th rowspan="2" style="vertical-align: middle; width: 85px;">Operator</th>
                            <th rowspan="2" style="vertical-align: middle; width: 110px;">PIC Line / Leader</th>
                            <th rowspan="2" style="vertical-align: middle; width: 85px;">Input Date</th>
                            <th rowspan="2" style="width: 65px; text-align: center; vertical-align: middle;">Aksi</th>
                        </tr>
                        <tr style="background:#f4f4f4;">
                            <th style="width: 60px; text-align: right;">OK</th>
                            <th style="width: 60px; text-align: right;">Hold</th>
                            <th style="width: 20px; text-align: center;">+</th>
                            <th style="width: 60px; text-align: right;">NG</th>
                            <th style="width: 20px; text-align: center;">+</th>
                            <th style="width: 60px; text-align: right;">DT</th>
                        </tr>
                    </thead>
                    <tbody id="history_table_body">
                        <tr><td colspan="15" class="text-center text-muted" style="padding: 20px;">Pilih Work Order di atas untuk memuat data produksi.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
var ngMasterData = <?php echo json_encode($ng_types); ?>;

$(document).ready(function() {
    var delayTimer;

    // Datalist Autocomplete Defect NG
    var datalistHTML = '<datalist id="ng-datalist">';
    for(var i=0; i<ngMasterData.length; i++) {
        datalistHTML += '<option value="' + ngMasterData[i].desc + '">';
    }
    datalistHTML += '</datalist>';
    $('body').append(datalistHTML);

    function cekNol(val) { return (val == 0 || val == null) ? '' : val; }

    function updateLotNumber() {
        var procId = $('#proc_id').val();
        var dateVal = $('#input_date').val(); 
        var groupVal = $('input[name="group"]:checked').val();
        var shiftVal = $('input[name="shift"]:checked').val();
        var macCodeOrig = $('#mac_code_hidden').val();

        if (!procId || !dateVal || !groupVal || !shiftVal || !macCodeOrig) {
            $('#pd_lot_new').val(''); return;
        }

        var prefix = (procId == '1') ? 'I' : ((procId == '3') ? 'A' : 'X');
        var dateObj = new Date(dateVal);
        var yy = String(dateObj.getFullYear()).slice(-2);
        var mm = String(dateObj.getMonth() + 1);
        if (mm.length < 2) mm = '0' + mm;
        var dd = String(dateObj.getDate());
        if (dd.length < 2) dd = '0' + dd;
        var dateStr = yy + mm + dd;

        var macNum = macCodeOrig.replace(/\D/g, '').slice(-2);
        while(macNum.length < 2) macNum = '0' + macNum;
        $('#pd_lot_new').val(prefix + dateStr + groupVal + macNum + shiftVal);
    }

    $('#input_date, input[name="group"], input[name="shift"]').on('change', function() { updateLotNumber(); });

    // Autocomplete pencarian part / WO via ac_item.php
    $('#search_item').on('keyup', function() {
        clearTimeout(delayTimer);
        var query = $(this).val();

        if (query.length >= 2) {
            delayTimer = setTimeout(function() {
                $.ajax({
                    url: 'ac_item.php',
                    type: 'GET',
                    data: { action: 'get_wo_list', item_code: query },
                    dataType: 'json',
                    success: function(response) {
                        var html = '';
                        if(response && response.length > 0) {
                            $.each(response, function(index, wo) {
                                html += '<tr data-woid="' + wo.WO_ID + '" ' +
                                             'data-wonumber="' + wo.WO_NUMBER + '" ' +
                                             'data-procid="' + wo.PROC_ID + '" ' +
                                             'data-procname="' + wo.PROC_NAME + '" ' +
                                             'data-itemcode="' + wo.ITEM_CODE + '" ' +
                                             'data-itemname="' + wo.ITEM_NAME + '" ' +
                                             'data-maccode="' + wo.MAC_CODE + '">' +
                                            '<td><b>' + wo.WO_NUMBER + '</b></td>' +
                                            '<td><b>' + wo.ITEM_CODE + '</b></td>' +
                                            '<td class="text-right">' + wo.WO_QTY + '</td>' +
                                            '<td class="text-center">' + wo.MAC_CODE + '</td>' +
                                            '<td class="text-right">' + wo.WO_CAP + '</td>' +
                                            '<td>' + wo.WO_START + '</td>' +
                                            '<td>' + wo.WO_END + '</td>' +
                                         '</tr>';
                            });
                            $('#wo_table_body').html(html);
                        } else {
                            $('#wo_table_body').html('<tr><td colspan="7" class="text-center text-muted">Tidak ada data WO ditemukan.</td></tr>');
                        }
                    }
                });
            }, 250);
        }
    });

    // Ketika baris WO diklik
    $(document).on('click', '.table-wo tbody tr', function() {
        var woid = $(this).attr('data-woid') || $(this).data('woid') || 0;
        var wonumber = $(this).attr('data-wonumber') || $(this).data('wonumber') || '';
        if(!woid && !wonumber) return; 

        $('.table-wo tbody tr').removeClass('selected');
        $(this).addClass('selected');

        $('#wo_id').val(woid);
        $('#wo_number').val(wonumber);
        $('#proc_id').val($(this).attr('data-procid') || $(this).data('procid'));
        $('#proc_name').val($(this).attr('data-procname') || $(this).data('procname'));
        $('#item_code').val($(this).attr('data-itemcode') || $(this).data('itemcode'));
        $('#item_name').val($(this).attr('data-itemname') || $(this).data('itemname'));
        $('#mac_code').val($(this).attr('data-maccode') || $(this).data('maccode'));
        $('#mac_code_hidden').val($(this).attr('data-maccode') || $(this).data('maccode'));

        updateLotNumber();
        loadHistoryGrid(woid, wonumber);
    });

    // Load histori produksi via ac_item.php
    function loadHistoryGrid(woid, wonumber) {
        $('#history_table_body').html('<tr><td colspan="15" class="text-center text-primary" style="padding:15px;"><i class="fa fa-spinner fa-spin"></i> Memuat data produksi untuk WO ' + wonumber + '...</td></tr>');
        
        $.ajax({
            url: 'ac_item.php',
            type: 'GET',
            data: { action: 'get_prod_history', wo_id: woid, wo_number: wonumber },
            dataType: 'json',
            success: function(historyData) {
                if (historyData && historyData.status === 'error') {
                    alert("Error Database: " + historyData.message);
                    $('#history_table_body').html('<tr><td colspan="15" class="text-center text-danger" style="padding:15px;">Gagal: ' + historyData.message + '</td></tr>');
                    return;
                }

                var histHtml = '';
                if(Array.isArray(historyData) && historyData.length > 0) {
                    $.each(historyData, function(i, h) {
                        var rowIcon = (i === 0) ? '▶' : '';
                        histHtml += '<tr class="main-row" data-pdid="' + h.PD_ID + '">' +
                                        '<td style="text-align:center;" class="row-indicator text-primary font-weight-bold">' + rowIcon + '</td>' +
                                        '<td><b>' + h.PD_LOT + '</b></td>' +
                                        '<td style="text-align:center;"><span class="box-icon btn-expand-act" title="Detail Actual Data">+</span></td>' +
                                        '<td class="text-right"><input type="number" class="grid-input in-ok text-right" value="' + cekNol(h.PD_OK) + '"></td>' +
                                        '<td class="text-right"><input type="number" class="grid-input in-ho text-right" value="' + cekNol(h.PD_HO) + '"></td>' +
                                        '<td style="text-align:center;"><span class="box-icon">+</span></td>' +
                                        '<td class="text-right"><input type="number" class="grid-input in-ng text-right" value="' + cekNol(h.PD_NG) + '"></td>' +
                                        '<td style="text-align:center;"><span class="box-icon btn-expand-ng" title="Detail Defect NG">+</span></td>' +
                                        '<td class="text-right"><input type="number" step="any" class="grid-input in-dt text-right" value="' + cekNol(h.PD_LOSTHOUR) + '"></td>' +
                                        '<td class="text-right"><input type="number" step="any" class="grid-input in-purging text-right" value="' + cekNol(h.PD_SC) + '"></td>' +
                                        '<td><input type="text" class="grid-input in-rem" value="' + h.PD_REM + '"></td>' +
                                        '<td><input type="text" class="grid-input in-opr" value="' + h.PD_OPR + '"></td>' +
                                        '<td><input type="text" class="grid-input in-pic" value="' + h.PD_PIC_LINE + '"></td>' +
                                        '<td>' + h.PD_INPUT + '</td>' +
                                        '<td style="text-align:center; white-space:nowrap;">' +
                                            '<button type="button" class="btn btn-xs btn-success btn-save-row" data-pdid="' + h.PD_ID + '" title="Simpan Baris Ini"><i class="fa fa-save"></i></button> ' +
                                            '<button type="button" class="btn btn-xs btn-danger btn-delete" data-pdid="' + h.PD_ID + '" title="Hapus Lot"><i class="fa fa-trash"></i></button>' +
                                        '</td>' +
                                     '</tr>';
                        
                        // SUB-ROW 1: ACTUAL DATA
                        histHtml += '<tr class="sub-row sub-row-' + h.PD_ID + '" style="display:none; background:#f4f4f4;">' +
                                        '<td colspan="15" style="padding:0;">' +
                                            '<div class="actual-container">' +
                                                '<table class="table table-bordered actual-table">' +
                                                    '<tr><td style="background:#eee;">Work.Hr</td><td><input type="number" step="any" class="grid-input-act act-wkh" value="' + cekNol(h.PD_WKH) + '"></td></tr>' +
                                                    '<tr><td style="background:#eee;">Lost.Hr</td><td><input type="number" step="any" class="grid-input-act act-losthr" value="' + cekNol(h.PD_LOSTHOUR) + '"></td></tr>' +
                                                    '<tr><td style="background:#eee;">Set.Hr</td><td><input type="number" step="any" class="grid-input-act act-sethr" value="' + cekNol(h.PD_SETUPHOUR) + '"></td></tr>' +
                                                    '<tr><td style="background:#eee;">Cav.</td><td><input type="number" class="grid-input-act act-cav" value="' + cekNol(h.PD_CAV) + '"></td></tr>' +
                                                    '<tr><td style="background:#eee;">Weight.S</td><td><input type="number" step="any" class="grid-input-act act-weights" value="' + cekNol(h.PD_WEIGHT_S) + '"></td></tr>' +
                                                    '<tr><td style="background:#eee;">Runner.S</td><td><input type="number" step="any" class="grid-input-act act-runs" value="' + cekNol(h.PD_RUN_S) + '"></td></tr>' +
                                                    '<tr><td style="background:#eee;">Cyl.Tm.</td><td><input type="number" step="any" class="grid-input-act act-cytm" value="' + cekNol(h.PD_CYTM) + '"></td></tr>' +
                                                '</table>' +
                                                '<div style="flex:1;">' +
                                                    '<label style="font-size:10px;">Lost Reason / Keterangan:</label>' +
                                                    '<input type="text" class="form-control input-sm act-reason" value="' + h.PD_LOST_REASON + '">' +
                                                '</div>' +
                                                '<div style="padding-top: 15px;">' +
                                                    '<button type="button" class="btn btn-xs btn-warning btn-save-act font-weight-bold" data-pdid="' + h.PD_ID + '"><i class="fa fa-save"></i> Save Actual</button>' +
                                                '</div>' +
                                            '</div>' +
                                        '</td>' +
                                     '</tr>';

                        // SUB-ROW 2: DEFECT NG
                        histHtml += '<tr class="sub-row-ng sub-row-ng-' + h.PD_ID + '" style="display:none;">' +
                                        '<td colspan="15" style="padding:0;">' +
                                            '<div class="ng-panel-wrapper">' +
                                                '<div class="ng-panel">' +
                                                    '<table class="table table-bordered table-condensed ng-grid" style="margin-bottom:4px;">' +
                                                        '<thead><tr style="background:#f4f4f4;"><th style="width:25px;">#</th><th>NG Type</th><th style="width:75px;" class="text-right">Qty</th><th style="width:25px;"></th></tr></thead>' +
                                                        '<tbody id="ng-tbody-' + h.PD_ID + '"></tbody>' +
                                                    '</table>' +
                                                    '<button type="button" class="btn btn-xs btn-primary btn-add-ng-row" data-pdid="' + h.PD_ID + '"><i class="fa fa-plus"></i> Tambah Defect</button>' +
                                                '</div>' +
                                            '</div>' +
                                        '</td>' +
                                     '</tr>';
                    });
                } else {
                    histHtml = '<tr><td colspan="15" class="text-center text-muted" style="padding:15px;">Belum ada hasil produksi untuk WO ini. Klik "+ Tambah Baris Lot".</td></tr>';
                }
                $('#history_table_body').html(histHtml);
            },
            error: function(xhr, status, error) {
                $('#history_table_body').html('<tr><td colspan="15" class="text-center text-danger" style="padding:15px;">Koneksi ke server gagal: ' + error + '</td></tr>');
            }
        });
    }

    // Insert Baris Lot Baru
    $('#btn_insert_lot').on('click', function() {
        var woid = $('#wo_id').val();
        var wonumber = $('#wo_number').val();
        var lotnew = $('#pd_lot_new').val();
        var inputdate = $('#input_date').val();

        if(!woid || !lotnew) { alert("Pilih WO dan pastikan nomor lot siap digenerate!"); return; }

        $.post('ac_item.php', { action: 'insert_new', wo_id: woid, pd_lot: lotnew, input_date: inputdate }, function(res) {
            if(res.status === 'success') {
                loadHistoryGrid(woid, wonumber); 
            } else {
                alert("Gagal Insert Baris Lot!");
            }
        }, 'json');
    });

    // Simpan Baris Utama
    function saveMainRow($tr) {
        var pdId = $tr.data('pdid');
        var dtValue = $tr.find('.in-dt').val();
        var postData = {
            action: 'update_detail', pd_id: pdId,
            pd_ok: $tr.find('.in-ok').val(), pd_ho: $tr.find('.in-ho').val(), pd_ng: $tr.find('.in-ng').val(),
            pd_dt: dtValue, pd_purging: $tr.find('.in-purging').val(), pd_rem: $tr.find('.in-rem').val(),
            pd_opr: $tr.find('.in-opr').val(), pd_pic: $tr.find('.in-pic').val()
        };
        $.post('ac_item.php', postData, function(res) {
            if(res.status === 'success') {
                $tr.css('background-color', '#dff0d8');
                setTimeout(function() { $tr.css('background-color', ''); }, 400);
                $('.sub-row-' + pdId).find('.act-losthr').val(dtValue);
            }
        }, 'json');
    }

    $(document).on('click', '.btn-save-row', function() {
        saveMainRow($(this).closest('.main-row'));
    });

    // Delete Baris Lot
    $(document).on('click', '.btn-delete', function() {
        if ($(this).hasClass('btn-del-ng')) return;
        var pdId = $(this).data('pdid');
        var $trMain = $(this).closest('.main-row');
        if (confirm("Apakah Anda yakin ingin menghapus data Lot ini?")) {
            $.post('ac_item.php', { action: 'delete_detail', pd_id: pdId }, function(res) {
                if(res.status === 'success') {
                    $trMain.fadeOut(300, function() { $(this).remove(); });
                    $('.sub-row-' + pdId + ', .sub-row-ng-' + pdId).remove();
                }
            }, 'json');
        }
    });

    // Actual Data Expand & Save
    $(document).on('click', '.btn-expand-act', function() {
        var pdId = $(this).closest('tr').data('pdid');
        var $subRow = $('.sub-row-' + pdId);
        $('.sub-row-ng-' + pdId).hide();
        $(this).closest('tr').find('.btn-expand-ng').text('+');
        if ($subRow.is(':visible')) {
            $subRow.hide(); $(this).text('+');
        } else {
            $subRow.show(); $(this).text('-');
        }
    });

    $(document).on('click', '.btn-save-act', function() {
        var pdId = $(this).data('pdid');
        var $sub = $('.sub-row-' + pdId);
        var postData = {
            action: 'update_actual', pd_id: pdId,
            pd_wkh: $sub.find('.act-wkh').val(), pd_losthr: $sub.find('.act-losthr').val(),
            pd_sethr: $sub.find('.act-sethr').val(), pd_cav: $sub.find('.act-cav').val(),
            pd_weights: $sub.find('.act-weights').val(), pd_runs: $sub.find('.act-runs').val(),
            pd_cytm: $sub.find('.act-cytm').val(), pd_reason: $sub.find('.act-reason').val()
        };
        $.post('ac_item.php', postData, function(res) {
            if(res.status === 'success') {
                $sub.prev('.main-row').find('.in-dt').val(postData.pd_losthr);
                alert("Actual Data berhasil disimpan!");
            }
        }, 'json');
    });

    // NG Defect Expand & CRUD
    $(document).on('click', '.btn-expand-ng', function() {
        var pdId = $(this).closest('tr').data('pdid');
        var $subNg = $('.sub-row-ng-' + pdId);
        $('.sub-row-' + pdId).hide();
        $(this).closest('tr').find('.btn-expand-act').text('+');
        if ($subNg.is(':visible')) {
            $subNg.hide(); $(this).text('+');
        } else {
            $subNg.show(); $(this).text('-');
            loadNgGrid(pdId);
        }
    });

    function loadNgGrid(pdId) {
        var $tbody = $('#ng-tbody-' + pdId);
        $tbody.html('<tr><td colspan="4" class="text-center text-muted">Loading...</td></tr>');
        $.post('ac_item.php', { action: 'load_ng', pd_id: pdId }, function(res) {
            if (res.status === 'success') {
                $tbody.empty();
                if (res.data.length > 0) {
                    res.data.forEach(function(row) {
                        appendNgRow(pdId, row.NGT_ID, row.NGT_DESC, row.NGP_QTY);
                    });
                } else {
                    $tbody.html('<tr class="ng-empty"><td colspan="4" class="text-center text-muted">Kosong. Klik Tambah Defect.</td></tr>');
                }
            }
        }, 'json');
    }

    $(document).on('click', '.btn-add-ng-row', function() {
        var pdId = $(this).data('pdid');
        $('#ng-tbody-' + pdId).find('.ng-empty').remove();
        appendNgRow(pdId, 0, '', '');
    });

    function appendNgRow(pdId, oldNgtId, ngDescVal, qtyVal) {
        var rowHtml = '<tr class="ng-row" data-oldngtid="' + oldNgtId + '" data-pdid="' + pdId + '">' +
                        '<td class="text-center text-primary ng-row-indicator"></td>' +
                        '<td><input type="text" class="ng-type-input" list="ng-datalist" value="' + ngDescVal + '" placeholder="Pilih tipe NG..."></td>' +
                        '<td><input type="number" class="ng-qty-input text-right" value="' + qtyVal + '" step="any"></td>' +
                        '<td class="text-center"><button type="button" class="btn btn-xs btn-danger btn-del-ng"><i class="fa fa-times"></i></button></td>' +
                      '</tr>';
        $('#ng-tbody-' + pdId).append(rowHtml);
        if (oldNgtId === 0) $('#ng-tbody-' + pdId).find('.ng-row').last().find('.ng-type-input').focus();
    }

    $(document).on('focus', '.ng-type-input, .ng-qty-input', function() {
        $(this).closest('tbody').find('.ng-row-indicator').text('');
        $(this).closest('tr').find('.ng-row-indicator').text('▶');
    });

    function saveNgRow($tr) {
        var oldNgtId = $tr.data('oldngtid');
        var pdId = $tr.data('pdid');
        var descInput = $tr.find('.ng-type-input').val().trim();
        var qty = $tr.find('.ng-qty-input').val();

        if (!descInput) { alert("Pilih tipe NG!"); return; }
        var ngObj = null;
        for(var i=0; i<ngMasterData.length; i++) {
            if(ngMasterData[i].desc.toLowerCase() === descInput.toLowerCase()) { ngObj = ngMasterData[i]; break; }
        }
        if (!ngObj) { alert("Tipe NG tidak ditemukan!"); return; }
        if (!qty) { alert("Isi qty defect!"); return; }

        $.post('ac_item.php', { action: 'save_ng', pd_id: pdId, old_ngt_id: oldNgtId, ngt_id: ngObj.id, qty: qty }, function(res) {
            if(res.status === 'success') {
                $tr.css('background', '#dff0d8');
                setTimeout(function() { $tr.css('background', ''); }, 500);
                if (oldNgtId == 0) loadNgGrid(pdId); else $tr.data('oldngtid', ngObj.id);
            }
        }, 'json');
    }

    $(document).on('keydown', '.ng-type-input', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); $(this).closest('tr').find('.ng-qty-input').focus(); }
    });
    $(document).on('keydown', '.ng-qty-input', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); saveNgRow($(this).closest('tr')); }
    });
    $(document).on('click', '.btn-del-ng', function() {
        var $tr = $(this).closest('tr');
        var oldNgtId = $tr.data('oldngtid');
        var pdId = $tr.data('pdid');
        if (oldNgtId == 0) { $tr.remove(); } else {
            if (confirm("Hapus defect ini?")) {
                $.post('ac_item.php', { action: 'delete_ng', pd_id: pdId, ngt_id: oldNgtId }, function(res) {
                    if (res.status === 'success') $tr.remove();
                }, 'json');
            }
        }
    });

    // Keyboard Arrow & Enter Navigation
    $(document).on('focus', '.grid-input', function() {
        $('.row-indicator').text('');
        $(this).closest('tr').find('.row-indicator').text('▶');
    });

    $(document).on('keydown', '.grid-input', function(e) {
        var $tr = $(this).closest('tr.main-row'); 
        var colIndex = $tr.children('td').index($(this).closest('td'));

        if (e.key === 'ArrowUp') {
            e.preventDefault(); $tr.prevAll('.main-row').first().children('td').eq(colIndex).find('.grid-input').focus();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault(); $tr.nextAll('.main-row').first().children('td').eq(colIndex).find('.grid-input').focus();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            saveMainRow($tr);
            $tr.nextAll('.main-row').first().children('td').eq(colIndex).find('.grid-input').focus();
        }
    });
});
</script>
<?php
// File: input_prod.php
require_once __DIR__ . "/../config/global.php";

$db = isset($conn) ? $conn : (isset($connection) ? $connection : (isset($dbconn) ? $dbconn : null));

// =================================================================
// FETCH MASTER DATA NG_TYPE SAAT LOAD AWAL
// =================================================================
$ng_types = [];
if ($db) {
    $sql_ng = "SELECT NGT_ID, NGT_CODE, NGT_DESC FROM dbo.NG_TYPE ORDER BY NGT_DESC ASC";
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

// =================================================================
// 1. HANDLER AJAX: INSERT LOT BARU KE GRID
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'insert_new') {
    header('Content-Type: application/json');
    if (!$db) { echo json_encode(['status' => 'error', 'message' => 'DB disconnect']); exit; }

    $wo_id = (int)$_POST['wo_id'];
    $pd_lot = trim($_POST['pd_lot']);
    $input_date = $_POST['input_date'];

    $sql_prod = "INSERT INTO dbo.PRODUCTION (WO_ID, PD_LOT, PD_OK, PD_NG, PD_HO, PD_REM, PD_INPUT) 
                 VALUES (?, ?, 0, 0, 0, '', ?)";
    $stmt = sqlsrv_query($db, $sql_prod, array($wo_id, $pd_lot, $input_date));
    
    if ($stmt) { echo json_encode(['status' => 'success']); } 
    else { echo json_encode(['status' => 'error', 'errors' => sqlsrv_errors()]); }
    exit;
}

// =================================================================
// 2. HANDLER AJAX: UPDATE DETAIL GRID UTAMA
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'update_detail') {
    header('Content-Type: application/json');
    if (!$db) { echo json_encode(['status' => 'error', 'message' => 'DB disconnect']); exit; }
    
    $pd_id = (int)$_POST['pd_id'];
    $pd_ok = (int)$_POST['pd_ok'];
    $pd_ho = (int)$_POST['pd_ho'];
    $pd_ng = (int)$_POST['pd_ng'];
    $pd_dt = (float)$_POST['pd_dt'];
    $pd_purging = (float)$_POST['pd_purging'];
    $pd_rem = trim($_POST['pd_rem']);
    $pd_opr = trim($_POST['pd_opr']);
    $pd_pic = trim($_POST['pd_pic']);
    
    $sql_update = "UPDATE dbo.PRODUCTION SET PD_OK=?, PD_HO=?, PD_NG=?, PD_LOSTHOUR=?, PD_SC=?, PD_REM=?, PD_OPR=?, PD_PIC_LINE=? WHERE PD_ID=?";
    $stmt_upd = sqlsrv_query($db, $sql_update, array($pd_ok, $pd_ho, $pd_ng, $pd_dt, $pd_purging, $pd_rem, $pd_opr, $pd_pic, $pd_id));
    
    if ($stmt_upd) { echo json_encode(['status' => 'success']); } 
    else { echo json_encode(['status' => 'error', 'errors' => sqlsrv_errors()]); }
    exit;
}

// =================================================================
// 3. HANDLER AJAX: UPDATE ACTUAL DATA (EXPAND PLUS PERTAMA)
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'update_actual') {
    header('Content-Type: application/json');
    if (!$db) { echo json_encode(['status' => 'error', 'message' => 'DB disconnect']); exit; }
    
    $pd_id = (int)$_POST['pd_id'];
    $pd_wkh = (float)$_POST['pd_wkh'];
    $pd_losthr = (float)$_POST['pd_losthr'];
    $pd_sethr = (float)$_POST['pd_sethr'];
    $pd_cav = (int)$_POST['pd_cav'];
    $pd_weights = (float)$_POST['pd_weights'];
    $pd_runs = (float)$_POST['pd_runs'];
    $pd_cytm = (float)$_POST['pd_cytm'];
    $pd_reason = trim($_POST['pd_reason']);
    
    $sql_act = "UPDATE dbo.PRODUCTION SET PD_WKH=?, PD_LOSTHOUR=?, PD_SETUPHOUR=?, PD_CAV=?, PD_WEIGHT_S=?, PD_RUN_S=?, PD_CYTM=?, PD_LOST_REASON=? WHERE PD_ID=?";
    $stmt_act = sqlsrv_query($db, $sql_act, array($pd_wkh, $pd_losthr, $pd_sethr, $pd_cav, $pd_weights, $pd_runs, $pd_cytm, $pd_reason, $pd_id));
    
    if ($stmt_act) { echo json_encode(['status' => 'success']); } 
    else { echo json_encode(['status' => 'error', 'errors' => sqlsrv_errors()]); }
    exit;
}

// =================================================================
// 4. HANDLER AJAX: HAPUS BARIS DATA
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'delete_detail') {
    header('Content-Type: application/json');
    if (!$db) { echo json_encode(['status' => 'error', 'message' => 'DB disconnect']); exit; }
    
    $pd_id = (int)$_POST['pd_id'];
    
    $sql_del = "DELETE FROM dbo.PRODUCTION WHERE PD_ID=?";
    $stmt_del = sqlsrv_query($db, $sql_del, array($pd_id));
    
    if ($stmt_del) { echo json_encode(['status' => 'success']); } 
    else { echo json_encode(['status' => 'error', 'errors' => sqlsrv_errors()]); }
    exit;
}

// =================================================================
// 5. HANDLER AJAX: NG DETAIL ACTIONS (EXPAND PLUS KEDUA)
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'load_ng') {
    header('Content-Type: application/json');
    $pd_id = (int)$_POST['pd_id'];
    
    $sql = "SELECT NP.NGT_ID, NP.NGP_QTY, NT.NGT_DESC 
            FROM dbo.NG_PROD NP 
            INNER JOIN dbo.NG_TYPE NT ON NP.NGT_ID = NT.NGT_ID 
            WHERE NP.PD_ID = ?";
            
    $stmt = sqlsrv_query($db, $sql, array($pd_id));
    
    $data = [];
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $row['NGT_DESC'] = trim($row['NGT_DESC']); 
            $data[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
    } else {
        echo json_encode(['status' => 'error', 'errors' => sqlsrv_errors()]);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'save_ng') {
    header('Content-Type: application/json');
    $pd_id = (int)$_POST['pd_id'];
    $ngt_id = (int)$_POST['ngt_id'];
    $old_ngt_id = isset($_POST['old_ngt_id']) ? (int)$_POST['old_ngt_id'] : 0;
    $qty = (float)$_POST['qty'];

    $check_id = ($old_ngt_id > 0) ? $old_ngt_id : $ngt_id;
    $sql_check = "SELECT COUNT(*) as cnt FROM dbo.NG_PROD WHERE PD_ID = ? AND NGT_ID = ?";
    $stmt_check = sqlsrv_query($db, $sql_check, array($pd_id, $check_id));
    $row_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);

    if ($row_check['cnt'] > 0) {
        $sql = "UPDATE dbo.NG_PROD SET NGT_ID = ?, NGP_QTY = ? WHERE PD_ID = ? AND NGT_ID = ?";
        $stmt = sqlsrv_query($db, $sql, array($ngt_id, $qty, $pd_id, $check_id));
    } else {
        $sql = "INSERT INTO dbo.NG_PROD (PD_ID, NGT_ID, NGP_QTY) VALUES (?, ?, ?)";
        $stmt = sqlsrv_query($db, $sql, array($pd_id, $ngt_id, $qty));
    }
    
    if ($stmt) { echo json_encode(['status' => 'success']); } 
    else { echo json_encode(['status' => 'error', 'errors' => sqlsrv_errors()]); }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'delete_ng') {
    header('Content-Type: application/json');
    $pd_id = (int)$_POST['pd_id'];
    $ngt_id = (int)$_POST['ngt_id'];
    
    $sql = "DELETE FROM dbo.NG_PROD WHERE PD_ID = ? AND NGT_ID = ?";
    $stmt = sqlsrv_query($db, $sql, array($pd_id, $ngt_id));
    
    if ($stmt) { echo json_encode(['status' => 'success']); } 
    else { echo json_encode(['status' => 'error', 'errors' => sqlsrv_errors()]); }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Production Entry System</title>
    <!-- AdminLTE & Bootstrap CSS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    
    <style>
        /* Custom Styles specifically for maintaining the fast data-entry grid feel */
        body { background-color: #f4f6f9; font-size: 12px; }
        .table-responsive { max-height: 400px; }
        .wo-table th, .history-table th { background: #f4f6f9; position: sticky; top: 0; z-index: 10; box-shadow: inset 0 -1px 0 #dee2e6;}
        .history-table th.sub-head { top: 32px; }
        .wo-table tr:hover { cursor: pointer; }
        .wo-table tr.selected { background-color: #007bff !important; color: white; }
        
        .p-0 { padding: 0 !important; }
        .grid-input, .grid-input-act { 
            width: 100%; height: 100%; min-height: 28px; box-sizing: border-box; 
            border: none; background: transparent; padding: 4px 8px; outline: none;
        }
        .grid-input:focus, .grid-input-act:focus { background: #fff; box-shadow: inset 0 0 0 2px #007bff; }
        input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        
        .box-icon { display: inline-block; border: 1px solid #6c757d; width: 16px; height: 16px; line-height: 14px; text-align: center; font-size: 12px; background: #fff; cursor: pointer; user-select: none; border-radius: 2px;}
        .history-table tbody tr.main-row:nth-child(4n+1) { background-color: #ffffff; } 
        .history-table tbody tr.main-row:nth-child(4n+3) { background-color: #f2f7ec; } 
        
        .ng-panel { background: #f8f9fa; border: 1px solid #ced4da; padding: 10px; border-radius: 4px;}
        .ng-grid th { background: #e9ecef; }
        
        /* Modifikasi Jarak & Ukuran agar sama persis dengan referensi (Horizontal Layout) */
        .content-wrapper { padding-top: 8px !important; } 
        .text-xs { font-size: 11px !important; font-weight: bold; color: #333; margin-bottom: 2px; }
        .form-control-xs { height: 26px !important; padding: 2px 6px !important; font-size: 12px !important; border-radius: 3px !important; }
        .compact-form .row { margin-bottom: 6px; }
        .radio-inline-label { font-size: 11px; margin-right: 10px; cursor: pointer; font-weight: normal !important; }
        .radio-inline-label input { vertical-align: middle; margin-right: 3px; }
        
        .wo-table-container { 
            max-height: 205px; /* Tinggi dibatasi drastis menyesuaikan form kiri yang horizontal */
            overflow-y: auto; 
        }
    </style>
</head>
<body class="hold-transition layout-top-nav">
<div class="wrapper">
    <div class="content-wrapper">
        <section class="content pt-0">
            <div class="container-fluid">
                
                <div class="row">
                    <!-- Left Panel: Form Setup (HORIZONTAL LAYOUT) -->
                    <div class="col-md-5">
                        <div class="card card-primary card-outline h-100 mb-2">
                            <div class="card-header py-1 bg-light">
                                <h3 class="card-title text-sm"><i class="fas fa-list mr-1"></i> Item Data & Setup</h3>
                            </div>
                            <div class="card-body p-2 compact-form">
                                
                                <!-- Baris 1: Date Prod, Group, Shift -->
                                <div class="row">
                                    <div class="col-4">
                                        <label class="text-xs">Date Prod:</label>
                                        <input type="date" id="input_date" class="form-control form-control-xs" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-4">
                                        <label class="text-xs">Group:</label>
                                        <div>
                                            <label class="radio-inline-label"><input type="radio" name="group" value="A" checked> A</label>
                                            <label class="radio-inline-label"><input type="radio" name="group" value="B"> B</label>
                                            <label class="radio-inline-label"><input type="radio" name="group" value="C"> C</label>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <label class="text-xs">Shift:</label>
                                        <div>
                                            <label class="radio-inline-label"><input type="radio" name="shift" value="1" checked> 1</label>
                                            <label class="radio-inline-label"><input type="radio" name="shift" value="2"> 2</label>
                                            <label class="radio-inline-label"><input type="radio" name="shift" value="3"> 3</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Baris 2: Search Part, Item Code -->
                                <div class="row">
                                    <div class="col-6">
                                        <label class="text-xs"><i class="fas fa-search text-muted"></i> Cari Part / WO:</label>
                                        <input type="text" id="search_item" class="form-control form-control-xs" placeholder="Ketik kode / WO...">
                                    </div>
                                    <div class="col-6">
                                        <label class="text-xs">Part / Item Code:</label>
                                        <input type="text" id="item_code" class="form-control form-control-xs bg-light" readonly>
                                    </div>
                                </div>

                                <!-- Baris 3: Part Name -->
                                <div class="row">
                                    <div class="col-12">
                                        <label class="text-xs">Part Name:</label>
                                        <input type="text" id="item_name" class="form-control form-control-xs bg-light" readonly>
                                    </div>
                                </div>

                                <!-- Baris 4: Process, Machine -->
                                <div class="row mb-0">
                                    <div class="col-6">
                                        <label class="text-xs">Process:</label>
                                        <input type="text" id="proc_name" class="form-control form-control-xs bg-light" readonly>
                                        <input type="hidden" id="proc_id">
                                    </div>
                                    <div class="col-6">
                                        <label class="text-xs">Machine (M/C):</label>
                                        <input type="text" id="mac_code" class="form-control form-control-xs bg-light" readonly>
                                        <input type="hidden" id="mac_code_hidden">
                                    </div>
                                </div>
                                
                                <input type="hidden" id="wo_id">
                                <input type="hidden" id="wo_number">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Panel: WO Grid -->
                    <div class="col-md-7">
                        <div class="card card-outline card-secondary h-100 mb-2">
                            <div class="card-header py-1 bg-light">
                                <h3 class="card-title text-sm"><i class="fas fa-list-alt mr-1"></i> Daftar Work Order (Tersedia)</h3>
                            </div>
                            <div class="card-body p-0 wo-table-container">
                                <table class="table table-sm table-hover table-bordered wo-table text-nowrap m-0">
                                    <thead>
                                        <tr>
                                            <th>WO NUMBER</th>
                                            <th>ITEM CODE</th>
                                            <th>QTY</th>
                                            <th>M/C</th>
                                            <th>CAP/DAY</th>
                                            <th>START</th>
                                            <th>END</th>
                                        </tr>
                                    </thead>
                                    <tbody id="wo_table_body">
                                        <tr><td colspan="7" class="text-center text-muted py-4 font-italic text-sm">Ketik kode / nama barang di sebelah kiri untuk menampilkan daftar WO...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Panel: Production History Grid -->
                <div class="row mt-1">
                    <div class="col-md-12">
                        <div class="card card-success card-outline mb-0">
                            <div class="card-header py-1 d-flex justify-content-between align-items-center bg-light">
                                <h3 class="card-title text-sm font-weight-bold"><i class="fas fa-boxes text-muted mr-1"></i> Production Result (Grid Mode)</h3>
                                <div class="card-tools d-flex align-items-center">
                                    <input type="text" id="pd_lot_new" class="form-control form-control-xs mr-2 text-center text-bold" readonly placeholder="Auto Lot..." style="width: 150px;">
                                    <button type="button" id="btn_insert_lot" class="btn btn-xs btn-success"><i class="fas fa-plus"></i> Tambah Baris Lot</button>
                                </div>
                            </div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-sm table-bordered history-table text-nowrap m-0" style="min-width: 1200px;">
                                    <thead>
                                        <tr>
                                            <th rowspan="2" style="width: 30px;" class="text-center">#</th>
                                            <th rowspan="2" class="align-middle">LOT #</th>
                                            <th rowspan="2" class="align-middle text-center">Act</th>
                                            <th colspan="6" class="text-center align-middle bg-lightblue">Result</th>
                                            <th rowspan="2" class="align-middle text-right">Purging (kg)</th>
                                            <th rowspan="2" class="align-middle">Remark</th>
                                            <th rowspan="2" class="align-middle">Operator</th>
                                            <th rowspan="2" class="align-middle">PIC Line / Leader</th>
                                            <th rowspan="2" class="align-middle">Input Date</th>
                                            <th rowspan="2" style="width: 40px;" class="text-center align-middle">Aksi</th>
                                        </tr>
                                        <tr>
                                            <th class="sub-head text-right bg-lightblue">OK</th>
                                            <th class="sub-head text-right bg-lightblue">Hold</th>
                                            <th class="sub-head text-center bg-lightblue" style="width: 25px;">+</th>
                                            <th class="sub-head text-right bg-lightblue">NG</th>
                                            <th class="sub-head text-center bg-lightblue" style="width: 25px;">+</th>
                                            <th class="sub-head text-right bg-lightblue">DT</th>
                                        </tr>
                                    </thead>
                                    <tbody id="history_table_body">
                                        <tr><td colspan="15" class="text-center text-muted py-4 font-italic text-sm">Pilih Work Order di atas untuk memuat data produksi.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
// Load Master Data NG Type dari PHP ke Javascript Array
const ngMasterData = <?php echo json_encode($ng_types); ?>;

$(document).ready(function() {
    let delayTimer;

    // GENERATE DATALIST HTML5 UNTUK AUTOCOMPLETE NG_DESC
    let datalistHTML = '<datalist id="ng-datalist">';
    ngMasterData.forEach(function(item) {
        datalistHTML += `<option value="${item.desc}">`;
    });
    datalistHTML += '</datalist>';
    $('body').append(datalistHTML);

    function cekNol(val) { return (val == 0 || val == null) ? '' : val; }

    function updateLotNumber() {
        let procId = $('#proc_id').val();
        let dateVal = $('#input_date').val(); 
        let groupVal = $('input[name="group"]:checked').val();
        let shiftVal = $('input[name="shift"]:checked').val();
        let macCodeOrig = $('#mac_code_hidden').val();

        if (!procId || !dateVal || !groupVal || !shiftVal || !macCodeOrig) {
            $('#pd_lot_new').val(''); return;
        }

        let prefix = (procId == '1') ? 'I' : ((procId == '3') ? 'A' : 'X');
        let dateObj = new Date(dateVal);
        let yy = String(dateObj.getFullYear()).slice(-2);
        let mm = String(dateObj.getMonth() + 1).padStart(2, '0');
        let dd = String(dateObj.getDate()).padStart(2, '0');
        let dateStr = yy + mm + dd;

        let macNum = macCodeOrig.replace(/\D/g, '').slice(-2).padStart(2, '0'); 
        $('#pd_lot_new').val(prefix + dateStr + groupVal + macNum + shiftVal);
    }

    $('#input_date, input[name="group"], input[name="shift"]').on('change', function() { updateLotNumber(); });

    $('#search_item').on('keyup', function() {
        clearTimeout(delayTimer);
        let query = $(this).val();

        if (query.length >= 2) {
            delayTimer = setTimeout(function() {
                $.ajax({
                    url: 'get_wo_list.php', 
                    type: 'GET',
                    data: { item_code: query },
                    dataType: 'json',
                    success: function(response) {
                        let html = '';
                        if(response.length > 0) {
                            $.each(response, function(index, wo) {
                                html += `<tr data-woid="${wo.WO_ID}" 
                                             data-wonumber="${wo.WO_NUMBER}" 
                                             data-procid="${wo.PROC_ID}" 
                                             data-procname="${wo.PROC_NAME}" 
                                             data-itemcode="${wo.ITEM_CODE}" 
                                             data-itemname="${wo.ITEM_NAME}" 
                                             data-maccode="${wo.MAC_CODE}">
                                            <td>${wo.WO_NUMBER}</td>
                                            <td>${wo.ITEM_CODE ? wo.ITEM_CODE : '-'}</td>
                                            <td class="text-right">${wo.WO_QTY ? wo.WO_QTY : '-'}</td> 
                                            <td>${wo.MAC_CODE}</td>
                                            <td class="text-right">${wo.WO_CAP ? wo.WO_CAP : '-'}</td>
                                            <td>${wo.WO_START}</td>
                                            <td>${wo.WO_END}</td>
                                         </tr>`;
                            });
                            $('#wo_table_body').html(html);
                        } else {
                            $('#wo_table_body').html('<tr><td colspan="7" class="text-center font-italic">Tidak ada data ditemukan.</td></tr>');
                        }
                    }
                });
            }, 300);
        }
    });

    $(document).on('click', '.wo-table tbody tr', function() {
        let woid = $(this).data('woid');
        if(!woid) return; 

        $('.wo-table tbody tr').removeClass('selected text-white bg-primary');
        $(this).addClass('selected text-white bg-primary');

        $('#wo_id').val(woid);
        $('#wo_number').val($(this).data('wonumber'));
        $('#proc_id').val($(this).data('procid'));
        $('#proc_name').val($(this).data('procname'));
        $('#item_code').val($(this).data('itemcode'));
        $('#item_name').val($(this).data('itemname'));
        $('#mac_code').val($(this).data('maccode'));
        $('#mac_code_hidden').val($(this).data('maccode'));

        updateLotNumber();
        loadHistoryGrid(woid);
    });

    function loadHistoryGrid(woid) {
        $.ajax({
            url: 'get_prod_history.php', 
            type: 'GET',
            data: { wo_id: woid },
            dataType: 'json',
            success: function(historyData) {
                let histHtml = '';
                if(historyData.length > 0) {
                    $.each(historyData, function(i, h) {
                        let rowIcon = (i === 0) ? '<i class="fas fa-caret-right text-primary"></i>' : '';
                        
                        let valOk = cekNol(h.PD_OK); let valHo = cekNol(h.PD_HO);
                        let valNg = cekNol(h.PD_NG); let valDt = cekNol(h.PD_LOSTHOUR);
                        let valSc = cekNol(h.PD_SC); let valWkh = cekNol(h.PD_WKH);
                        let valLostHr = cekNol(h.PD_LOSTHOUR); let valSetHr = cekNol(h.PD_SETUPHOUR);
                        let valCav = cekNol(h.PD_CAV); let valWeight = cekNol(h.PD_WEIGHT_S);
                        let valRun = cekNol(h.PD_RUN_S); let valCytm = cekNol(h.PD_CYTM);
                        
                        histHtml += `<tr class="main-row" data-pdid="${h.PD_ID}">
                                        <td class="text-center align-middle row-indicator">${rowIcon}</td>
                                        <td class="align-middle">${h.PD_LOT}</td>
                                        <td class="text-center align-middle"><span class="box-icon btn-expand-act"><i class="fas fa-plus fa-xs"></i></span></td>
                                        
                                        <td class="text-right p-0"><input type="number" class="grid-input in-ok text-right" value="${valOk}"></td>
                                        <td class="text-right p-0"><input type="number" class="grid-input in-ho text-right" value="${valHo}"></td>
                                        <td class="text-center align-middle"><span class="box-icon"><i class="fas fa-plus fa-xs"></i></span></td>
                                        <td class="text-right p-0"><input type="number" class="grid-input in-ng text-right" value="${valNg}"></td>
                                        
                                        <td class="text-center align-middle"><span class="box-icon btn-expand-ng"><i class="fas fa-plus fa-xs"></i></span></td>
                                        
                                        <td class="text-right p-0"><input type="number" step="any" class="grid-input in-dt text-right" value="${valDt}"></td>
                                        <td class="text-right p-0"><input type="number" step="any" class="grid-input in-purging text-right" value="${valSc}"></td>
                                        
                                        <td class="p-0"><input type="text" class="grid-input in-rem" value="${h.PD_REM}"></td>
                                        <td class="p-0"><input type="text" class="grid-input in-opr" value="${h.PD_OPR}"></td>
                                        <td class="p-0"><input type="text" class="grid-input in-pic" value="${h.PD_PIC_LINE}"></td>
                                        <td class="align-middle">${h.PD_INPUT}</td>
                                        <td class="text-center align-middle"><button type="button" class="btn btn-xs btn-danger btn-delete" data-pdid="${h.PD_ID}"><i class="fas fa-times"></i></button></td>
                                     </tr>`;
                        
                        // ROW UNTUK ACTUAL DATA
                        histHtml += `<tr class="sub-row sub-row-${h.PD_ID}" style="display:none; background-color:#f8f9fa;">
                                        <td colspan="15" class="p-2">
                                            <div class="row align-items-center">
                                                <div class="col-md-2 offset-md-1">
                                                    <table class="table table-sm table-bordered m-0 bg-white">
                                                        <thead class="bg-light"><tr><th colspan="2" class="text-center py-1">Actual Data</th></tr></thead>
                                                        <tbody>
                                                            <tr><td>Work.Hr</td><td class="p-0"><input type="number" step="any" class="grid-input-act act-wkh" value="${valWkh}"></td></tr>
                                                            <tr><td>Lost.Hr</td><td class="p-0"><input type="number" step="any" class="grid-input-act act-losthr" value="${valLostHr}"></td></tr>
                                                            <tr><td>Set.Hr</td><td class="p-0"><input type="number" step="any" class="grid-input-act act-sethr" value="${valSetHr}"></td></tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="col-md-2">
                                                    <table class="table table-sm table-bordered m-0 bg-white">
                                                        <thead class="bg-light"><tr><th colspan="2" class="text-center py-1">Machine Params</th></tr></thead>
                                                        <tbody>
                                                            <tr><td>Cav.</td><td class="p-0"><input type="number" class="grid-input-act act-cav" value="${valCav}"></td></tr>
                                                            <tr><td>Weight.S</td><td class="p-0"><input type="number" step="any" class="grid-input-act act-weights" value="${valWeight}"></td></tr>
                                                            <tr><td>Run.S</td><td class="p-0"><input type="number" step="any" class="grid-input-act act-runs" value="${valRun}"></td></tr>
                                                            <tr><td>Cyl.Tm</td><td class="p-0"><input type="number" step="any" class="grid-input-act act-cytm" value="${valCytm}"></td></tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="col-md-5">
                                                    <label class="text-xs">Reasons</label>
                                                    <textarea class="form-control act-reason mb-2" rows="3">${h.PD_LOST_REASON}</textarea>
                                                </div>
                                                <div class="col-md-2 text-center">
                                                    <button type="button" class="btn btn-sm btn-primary btn-save-act w-100" data-pdid="${h.PD_ID}"><i class="fas fa-save mr-1"></i> Save Data</button>
                                                </div>
                                            </div>
                                        </td>
                                     </tr>`;

                        // ROW UNTUK NG DATA
                        histHtml += `<tr class="sub-row-ng sub-row-ng-${h.PD_ID}" style="display:none; background-color:#f8f9fa;">
                                        <td colspan="15" class="p-2">
                                            <div class="row">
                                                <div class="col-md-5 offset-md-4">
                                                    <div class="ng-panel shadow-sm">
                                                        <table class="table table-sm table-bordered ng-grid mb-2 bg-white">
                                                            <thead>
                                                                <tr>
                                                                    <th>NG Type</th>
                                                                    <th style="width: 100px;">NG Qty</th>
                                                                    <th style="width: 50px; text-align:center;"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="ng-tbody-${h.PD_ID}"></tbody>
                                                        </table>
                                                        <button class="btn btn-xs btn-outline-secondary btn-add-ng-row w-100" data-pdid="${h.PD_ID}"><i class="fas fa-plus"></i> Tambah Defect</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                     </tr>`;
                    });
                } else {
                    histHtml = '<tr><td colspan="15" class="text-center py-4 text-muted font-italic text-sm">Data kosong. Klik "Tambah Baris Lot" untuk memulai.</td></tr>';
                }
                $('#history_table_body').html(histHtml);
                $('#history_table_body .in-ok').first().focus();
            }
        });
    }

    $('#btn_insert_lot').on('click', function() {
        let woid = $('#wo_id').val();
        let lotnew = $('#pd_lot_new').val();
        let inputdate = $('#input_date').val();

        if(!woid || !lotnew) { alert("Pilih WO dan pastikan Lot siap digenerate!"); return; }

        $.post(window.location.href, { action: 'insert_new', wo_id: woid, pd_lot: lotnew, input_date: inputdate }, function(res) {
            if(res.status === 'success') {
                loadHistoryGrid(woid); 
            } else {
                let msg = (res.errors && res.errors[0]) ? res.errors[0].message : "Periksa Koneksi atau Struktur Tabel.";
                alert("Gagal Insert Baris Lot Baru!\n\nError SQL: " + msg);
            }
        });
    });

    $(document).on('click', '.btn-delete', function() {
        if ($(this).hasClass('btn-del-ng')) return;
        
        let pdId = $(this).data('pdid');
        let $trMain =$(this).closest('.main-row');
        let $trSubAct =$('.sub-row-' + pdId);
        let $trSubNg =$('.sub-row-ng-' + pdId);

        if (confirm("Apakah Anda yakin ingin menghapus data Lot ini?")) {
            $.post(window.location.href, { action: 'delete_detail', pd_id: pdId }, function(res) {
                if(res.status === 'success') {
                    $trMain.fadeOut(300, function() {$(this).remove(); });
                    $trSubAct.remove();$trSubNg.remove();
                } else {
                    let msg = (res.errors && res.errors[0]) ? res.errors[0].message : "";
                    alert("Gagal menghapus data!\n" + msg);
                }
            });
        }
    });

    $(document).on('click', '.btn-expand-act', function() {
        let $tr =$(this).closest('tr');
        let pdId = $tr.data('pdid');
        let $subRow =$('.sub-row-' + pdId);
        
        $('.sub-row-ng-' + pdId).hide();$tr.find('.btn-expand-ng').html('<i class="fas fa-plus fa-xs"></i>');

        if ($subRow.is(':visible')) {
            $subRow.hide();$(this).html('<i class="fas fa-plus fa-xs"></i>');
        } else {
            $subRow.show();$(this).html('<i class="fas fa-minus fa-xs"></i>');
            $subRow.find('.act-wkh').focus();
        }
    });

    $(document).on('click', '.btn-save-act', function() {
        let pdId = $(this).data('pdid');
        let $subRow =$('.sub-row-' + pdId);
        
        let postData = {
            action: 'update_actual', pd_id: pdId,
            pd_wkh: $subRow.find('.act-wkh').val(), pd_losthr:$subRow.find('.act-losthr').val(),
            pd_sethr: $subRow.find('.act-sethr').val(), pd_cav:$subRow.find('.act-cav').val(),
            pd_weights: $subRow.find('.act-weights').val(), pd_runs:$subRow.find('.act-runs').val(),
            pd_cytm: $subRow.find('.act-cytm').val(), pd_reason:$subRow.find('.act-reason').val()
        };
        
        $.post(window.location.href, postData, function(res) {
            if(res.status === 'success') {
                $subRow.find('.btn-save-act').removeClass('btn-primary').addClass('btn-success').html('<i class="fas fa-check"></i> Saved');
                setTimeout(() => $subRow.find('.btn-save-act').removeClass('btn-success').addClass('btn-primary').html('<i class="fas fa-save mr-1"></i> Save Data'), 1500);
                $subRow.prev('.main-row').find('.in-dt').val(postData.pd_losthr);
            } else { 
                let msg = (res.errors && res.errors[0]) ? res.errors[0].message : "";
                alert("Gagal mengupdate Actual Data!\n" + msg); 
            }
        });
    });

    $(document).on('click', '.btn-expand-ng', function() {
        let $tr =$(this).closest('tr');
        let pdId = $tr.data('pdid');
        let $subRowNg =$('.sub-row-ng-' + pdId);
        
        $('.sub-row-' + pdId).hide();$tr.find('.btn-expand-act').html('<i class="fas fa-plus fa-xs"></i>');

        if ($subRowNg.is(':visible')) {
            $subRowNg.hide();$(this).html('<i class="fas fa-plus fa-xs"></i>');
        } else {
            $subRowNg.show();$(this).html('<i class="fas fa-minus fa-xs"></i>');
            loadNgGrid(pdId);
        }
    });

    function loadNgGrid(pdId) {
        let $tbody =$('#ng-tbody-' + pdId);
        $tbody.html('<tr><td colspan="4" class="text-center py-2 text-muted">Loading...</td></tr>');
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: { action: 'load_ng', pd_id: pdId },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $tbody.empty();
                    if (res.data.length > 0) {
                        res.data.forEach(function(row) {
                            appendNgRow(pdId, row.NGT_ID, row.NGT_DESC, row.NGP_QTY);
                        });
                    } else {
                        $tbody.html('<tr class="ng-empty"><td colspan="4" class="text-center text-muted font-italic py-2">Kosong. Klik Tambah Defect di bawah.</td></tr>');
                    }
                } else {
                    let errMsg = (res.errors && res.errors[0]) ? res.errors[0].message : "Terjadi kesalahan SQL.";
                    $tbody.html(`<tr><td colspan="4" class="text-danger py-2">${errMsg}</td></tr>`);
                }
            },
            error: function() {
                $tbody.html('<tr><td colspan="4" class="text-danger py-2">Gagal terhubung ke server.</td></tr>');
            }
        });
    }

    $(document).on('click', '.btn-add-ng-row', function() {
        let pdId = $(this).data('pdid');
        let $tbody =$('#ng-tbody-' + pdId);
        $tbody.find('.ng-empty').remove();
        
        appendNgRow(pdId, 0, '', '');
    });

    function appendNgRow(pdId, oldNgtId, ngDescVal, qtyVal) {
        let rowHtml = `
            <tr class="ng-row" data-oldngtid="${oldNgtId}" data-pdid="${pdId}">
                <td class="p-0">
                    <input type="text" class="grid-input ng-type-input" list="ng-datalist" value="${ngDescVal}" placeholder="...">
                </td>
                <td class="p-0">
                    <input type="number" class="grid-input text-right ng-qty-input" value="${qtyVal}" step="any">
                </td>
                <td class="text-center align-middle p-0">
                    <button type="button" class="btn btn-xs btn-danger btn-del-ng" title="Hapus"><i class="fas fa-times"></i></button>
                </td>
            </tr>
        `;
        $('#ng-tbody-' + pdId).append(rowHtml);
        
        if (oldNgtId === 0) {
            $('#ng-tbody-' + pdId).find('.ng-row').last().find('.ng-type-input').focus();
        }
    }

    function saveNgRow($tr) {
        let oldNgtId = $tr.data('oldngtid');
        let pdId = $tr.data('pdid');
        let descInput = $tr.find('.ng-type-input').val().trim();
        let qty = $tr.find('.ng-qty-input').val();

        if (!descInput) { alert("Pilih tipe NG (NG Type) terlebih dahulu!"); return; }

        let ngObj = ngMasterData.find(x => x.desc.toLowerCase() === descInput.toLowerCase());
        if (!ngObj) {
            alert("Tipe NG '" + descInput + "' tidak ditemukan dalam Master Data!");
            return;
        }
        
        let ngtId = ngObj.id;
        if (!qty) { alert("Isi quantity (NG Qty) terlebih dahulu!"); return; }

        $.post(window.location.href, { 
            action: 'save_ng', 
            pd_id: pdId, 
            old_ngt_id: oldNgtId, 
            ngt_id: ngtId, 
            qty: qty 
        }, function(res) {
            if(res.status === 'success') {
                $tr.addClass('bg-success');
                setTimeout(() => { $tr.removeClass('bg-success'); }, 500);
                
                if (oldNgtId == 0) {
                    loadNgGrid(pdId); 
                } else {
                    $tr.data('oldngtid', ngtId); 
                }
            } else {
                let msg = (res.errors && res.errors[0]) ? res.errors[0].message : "";
                alert("Gagal simpan data NG!\n" + msg);
            }
        });
    }

    $(document).on('keydown', '.ng-type-input', function(e) {         if (e.key === 'Enter') {             e.preventDefault();$(this).closest('tr').find('.ng-qty-input').focus();
        }
    });

    $(document).on('keydown', '.ng-qty-input', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveNgRow($(this).closest('tr'));
        }
    });

    $(document).on('click', '.btn-del-ng', function() {
        let $tr =$(this).closest('tr');
        let oldNgtId = $tr.data('oldngtid');
        let pdId = $tr.data('pdid');
        
        if (oldNgtId == 0) {
            $tr.remove(); 
        } else {
            if (confirm("Yakin ingin menghapus defect ini?")) {
                $.post(window.location.href, { action: 'delete_ng', pd_id: pdId, ngt_id: oldNgtId }, function(res) {
                    if (res.status === 'success') {
                        $tr.remove();
                    } else {
                        let msg = (res.errors && res.errors[0]) ? res.errors[0].message : "";
                        alert("Gagal menghapus data NG!\n" + msg);
                    }
                });
            }
        }
    });

    $(document).on('focus', '.grid-input', function() {
        $('.row-indicator').html('');$(this).closest('tr').find('.row-indicator').html('<i class="fas fa-caret-right text-primary"></i>');
    });

    $(document).on('keydown', '.grid-input', function(e) {
        let $this =$(this);
        let $td =$this.closest('td');
        let $tr =$this.closest('tr.main-row'); 
        let colIndex = $tr.children('td').index($td);

        if (e.key === 'ArrowUp') {
            e.preventDefault(); $tr.prevAll('.main-row').first().children('td').eq(colIndex).find('.grid-input').focus();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault(); $tr.nextAll('.main-row').first().children('td').eq(colIndex).find('.grid-input').focus();
        } else if (e.key === 'ArrowLeft') {
            if ($this[0].selectionStart === 0 || $this.attr('type') === 'number') { e.preventDefault();$td.prevAll().find('.grid-input').first().focus(); }
        } else if (e.key === 'ArrowRight') {
            if ($this[0].selectionStart ===$this.val().length || $this.attr('type') === 'number') { e.preventDefault();$td.nextAll().find('.grid-input').first().focus(); }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            let pdId = $tr.data('pdid');
            let dtValue = $tr.find('.in-dt').val();
            
            let postData = {
                action: 'update_detail', pd_id: pdId,
                pd_ok: $tr.find('.in-ok').val(), pd_ho: $tr.find('.in-ho').val(), pd_ng:$tr.find('.in-ng').val(),
                pd_dt: dtValue, pd_purging: $tr.find('.in-purging').val(), pd_rem:$tr.find('.in-rem').val(),
                pd_opr: $tr.find('.in-opr').val(), pd_pic:$tr.find('.in-pic').val()
            };
            
            $.post(window.location.href, postData, function(res) {
                if(res.status === 'success') {
                    $tr.css('background-color', '#d4edda');
                    setTimeout(() => $tr.css('background-color', ''), 500);$('.sub-row-' + pdId).find('.act-losthr').val(dtValue);
                } else { 
                    let msg = (res.errors && res.errors[0]) ? res.errors[0].message : "";
                    alert("Gagal mengupdate data!\n" + msg); 
                }
            });
            
            $tr.nextAll('.main-row').first().children('td').eq(colIndex).find('.grid-input').focus();
        }
    });

    $(document).on('keydown', '.grid-input-act', function(e) {
        let $this =$(this);
        let $tr =$this.closest('tr'); 

        if (e.key === 'ArrowUp') {
            e.preventDefault(); $tr.prev().find('.grid-input-act').focus();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault(); $tr.next().find('.grid-input-act').focus();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            $this.closest('.row').find('.btn-save-act').click();
            let $nextInput =$tr.next().find('.grid-input-act');
            if($nextInput.length) {$nextInput.focus(); } 
        }
    });
});
</script>
</body>
</html>
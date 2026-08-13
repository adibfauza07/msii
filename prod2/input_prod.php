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
    
    // Menggunakan JOIN sesuai struktur tabel yang Anda berikan
    $sql = "SELECT NP.NGT_ID, NP.NGP_QTY, NT.NGT_DESC 
            FROM dbo.NG_PROD NP 
            INNER JOIN dbo.NG_TYPE NT ON NP.NGT_ID = NT.NGT_ID 
            WHERE NP.PD_ID = ?";
            
    $stmt = sqlsrv_query($db, $sql, array($pd_id));
    
    $data = [];
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $row['NGT_DESC'] = trim($row['NGT_DESC']); // Bersihkan spasi dari tipe data CHAR
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

    // Cek apakah defect ini sudah pernah diinput di Lot/PD_ID ini
    $check_id = ($old_ngt_id > 0) ? $old_ngt_id : $ngt_id;
    $sql_check = "SELECT COUNT(*) as cnt FROM dbo.NG_PROD WHERE PD_ID = ? AND NGT_ID = ?";
    $stmt_check = sqlsrv_query($db, $sql_check, array($pd_id, $check_id));
    $row_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);

    if ($row_check['cnt'] > 0) {
        // UPDATE: Jika diubah, perbarui NGT_ID dan QTY nya
        $sql = "UPDATE dbo.NG_PROD SET NGT_ID = ?, NGP_QTY = ? WHERE PD_ID = ? AND NGT_ID = ?";
        $stmt = sqlsrv_query($db, $sql, array($ngt_id, $qty, $pd_id, $check_id));
    } else {
        // INSERT: Tambah defect baru untuk Lot/PD_ID ini
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
    
    // DELETE berdasarkan PD_ID dan NGT_ID
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
    <title>Production Entry System</title>
    <style>
        body { font-family: Tahoma, sans-serif; background-color: #e0e0e0; margin: 10px; font-size: 11px; }
        .container { width: 100%; max-width: 1400px; margin: auto; background: #f0f0f0; padding: 10px; border: 1px solid #999; box-shadow: 2px 2px 5px rgba(0,0,0,0.2); box-sizing: border-box;}
        
        .top-panel { display: flex; gap: 10px; margin-bottom: 10px; }
        .top-left { flex: 0 0 350px; background: #e8e8e8; padding: 10px; border: 1px solid #ccc; }
        .top-right { flex: 1; background: #fff; border: 1px solid #999; height: 260px; overflow-y: auto; }
        
        .bottom-panel { background: #fff; padding: 10px; border: 1px solid #999; margin-top: 5px; }
        
        .form-group { margin-bottom: 6px; }
        label { display: block; margin-bottom: 2px; color: #333; font-weight: normal;}
        
        input[type="text"], input[type="number"], input[type="date"], select { padding: 3px 5px; border: 1px solid #a0a0a0; font-family: Tahoma, sans-serif; font-size: 11px; }
        input[readonly] { background-color: #d8e4f8; cursor: default; border: 1px solid #8ba0bc; }
        
        .w-full { width: 100%; box-sizing: border-box; }
        .w-120 { width: 120px; }
        .w-150 { width: 150px; }
        
        .radio-group { padding-top: 2px; }
        .radio-group label { display: inline-block; margin-right: 10px; cursor: pointer; }
        .radio-group input { margin-right: 3px; vertical-align: middle; }

        .data-table { width: 100%; border-collapse: collapse; font-size: 11px; white-space: nowrap; }
        .data-table th, .data-table td { padding: 4px 6px; border: 1px solid #999; text-align: left; }
        
        .wo-table th { background: #d0d0d0; color: #000; position: sticky; top: 0; z-index: 10; border-bottom: 2px solid #999;}
        .wo-table tr:hover { background-color: #e2f0ff; cursor: pointer; }
        .wo-table tr.selected { background-color: #0078d7; color: white; }
        
        .history-panel { max-height: 400px; overflow-y: auto; border: 1px solid #999; background-color: #fff; }
        .history-table th { background: #e0e0e0; color: #000; position: sticky; top: 0; z-index: 10; font-weight: normal;}
        .history-table th.sub-head { top: 23px; }
        
        .history-table tbody tr.main-row:nth-child(4n+1) { background-color: #ffffff; } 
        .history-table tbody tr.main-row:nth-child(4n+3) { background-color: #cde4c4; } 
        .history-table td { border: 1px solid #c0c0c0; }
        .history-table td.text-right { text-align: right; }
        
        .p-0 { padding: 0 !important; }
        .grid-input, .grid-input-act { 
            width: 100%; height: 100%; min-height: 20px; box-sizing: border-box; 
            border: none; background: transparent; font-family: inherit; font-size: inherit; 
            text-align: inherit; padding: 4px 6px; outline: none;
        }
        .grid-input:focus, .grid-input-act:focus { background: #fff; box-shadow: inset 0 0 0 2px #0078d7; }
        input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        
        .box-icon { display: inline-block; border: 1px solid #666; width: 11px; height: 11px; line-height: 9px; text-align: center; font-size: 10px; background: #fff; color: #000; cursor: pointer; user-select: none;}
        
        /* Tombol Tambahan */
        button { padding: 5px 15px; background-color: #0078d7; color: white; border: 1px solid #005a9e; cursor: pointer; font-size: 11px; font-weight: bold; }
        button:hover { background-color: #005a9e; }
        .btn-delete { padding: 3px 6px; background-color: #dc3545; color: white; border: 1px solid #c82333; border-radius: 3px; cursor: pointer; font-size: 9px; font-weight: bold; width:100%;}
        .btn-delete:hover { background-color: #c82333; }
        
        .header-title { font-size: 12px; font-weight: bold; margin: 0 0 10px 0; border-bottom: 1px solid #ccc; padding-bottom: 5px;}
        
        /* SUB-TABLE (ACTUAL DATA) */
        .actual-container { display: flex; gap: 0px; background: #e8e8e8; padding: 5px; border-top: none; }
        .actual-table { border-collapse: collapse; background: #fff; width: 150px; font-size: 11px; margin-right: 10px;}
        .actual-table td { border: 1px solid #ccc; padding: 0; }
        .actual-table td:first-child { background: #f0f0f0; padding: 2px 4px;}
        .actual-header { background: #d0d0d0; padding: 2px 5px; font-weight: normal; border: 1px solid #ccc; border-bottom: none; width: 140px;}
        
        /* SUB-TABLE (NG DATA) - DESKTOP STYLE */
        .ng-panel-wrapper {
            padding: 6px 10px 6px 365px; /* Mengatur margin kiri agar sejajar di bawah kolom NG */
            background-color: #e8e8e8;
        }
        .ng-panel {
            width: 350px;
            background: #f0f0f0;
            border: 1px solid #a0a0a0;
            border-right: 2px solid #808080;
            border-bottom: 2px solid #808080;
            padding: 4px;
        }
        .ng-grid {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border: 1px solid #999;
        }
        .ng-grid th {
            background: #e4e4e4;
            color: #000;
            padding: 3px 5px;
            font-weight: normal;
            border: 1px solid #999;
            text-align: left;
        }
        .ng-grid td {
            border: 1px solid #999;
            padding: 0;
        }
        .ng-grid input {
            width: 100%;
            box-sizing: border-box;
            border: none;
            padding: 3px 5px;
            font-family: inherit;
            font-size: inherit;
            outline: none;
            background: transparent;
        }
        .ng-grid input:focus {
            background-color: #e2f0ff;
        }
        .ng-toolbar-bottom {
            display: flex;
            gap: 10px;
            padding: 6px 4px 2px 4px;
            align-items: center;
            background: #f0f0f0;
            color: #888;
        }
        .icon-btn {
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            color: #000;
            user-select: none;
        }
        .icon-btn:hover {
            color: #0078d7;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="top-panel">
        <div class="top-left">
            <div class="header-title">Item Data & Setup</div>
            <div class="form-group">
                <label>Date production:</label>
                <input type="date" id="input_date" class="w-150" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group" style="display:flex; gap:10px;">
                <div>
                    <label>Group:</label>
                    <div class="radio-group">
                        <label><input type="radio" name="group" value="A" checked> A</label>
                        <label><input type="radio" name="group" value="B"> B</label>
                        <label><input type="radio" name="group" value="C"> C</label>
                    </div>
                </div>
                <div>
                    <label>Shift:</label>
                    <div class="radio-group">
                        <label><input type="radio" name="shift" value="1" checked> 1</label>
                        <label><input type="radio" name="shift" value="2"> 2</label>
                        <label><input type="radio" name="shift" value="3"> 3</label>
                    </div>
                </div>
            </div>
            <div class="form-group" style="margin-top: 10px; border-top: 1px solid #ccc; padding-top: 5px;">
                <label><strong>Cari Part Code / Part Name:</strong></label>
                <input type="text" id="search_item" class="w-full" placeholder="Ketik minimal 2 karakter...">
            </div>
            <div class="form-group" style="margin-top: 5px;">
                <label>Part Code / Item Code:</label>
                <input type="text" id="item_code" class="w-full" readonly>
            </div>
            <div class="form-group">
                <label>Part Name / Item Name:</label>
                <input type="text" id="item_name" class="w-full" readonly>
            </div>
            <div class="form-group" style="display:flex; gap:10px;">
                <div style="flex:1;">
                    <label>Process:</label>
                    <input type="text" id="proc_name" class="w-full" readonly>
                    <input type="hidden" id="proc_id">
                </div>
                <div style="flex:1;">
                    <label>Mac Code:</label>
                    <input type="text" id="mac_code" class="w-full" readonly>
                    <input type="hidden" id="mac_code_hidden">
                </div>
            </div>
            <input type="hidden" id="wo_id">
            <input type="hidden" id="wo_number">
        </div>

        <div class="top-right">
            <table class="data-table wo-table">
                <thead>
                    <tr>
                        <th>WO</th>
                        <th>Qty</th>
                        <th>MC</th>
                        <th>Cap/Day</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>#</th>
                    </tr>
                </thead>
                <tbody id="wo_table_body">
                    <tr><td colspan="7" style="text-align:center; padding: 20px; color:#666;">Cari item di sebelah kiri...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bottom-panel">
        <div class="header-title" style="display:flex; justify-content:space-between; align-items:center;">
            <span>Production Result (Grid Mode)</span>
            <div style="display:flex; gap:5px; align-items:center;">
                <input type="text" id="pd_lot_new" class="w-120" readonly placeholder="Auto Lot..." style="background:#fff; text-align:center; font-weight:bold;">
                <button type="button" id="btn_insert_lot" style="background-color:#28a745; border-color:#218838;">➕ Tambah Baris Lot</button>
            </div>
        </div>

        <div class="history-panel">
            <table class="data-table history-table">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 20px;"></th>
                        <th rowspan="2">LOT #</th>
                        <th rowspan="2">Act.</th>
                        <th colspan="6" style="text-align:center;">Result</th>
                        <th rowspan="2">Purging (kg)</th>
                        <th rowspan="2">Remark</th>
                        <th rowspan="2">Operator</th>
                        <th rowspan="2">PIC LINE / LEADER</th>
                        <th rowspan="2">Input Date</th>
                        <th rowspan="2" style="width: 30px;">Act</th>
                    </tr>
                    <tr>
                        <th class="sub-head">OK</th>
                        <th class="sub-head">Hold</th>
                        <th class="sub-head" style="width: 15px;">+</th>
                        <th class="sub-head">NG</th>
                        <th class="sub-head" style="width: 15px;">+</th>
                        <th class="sub-head">DT</th>
                    </tr>
                </thead>
                <tbody id="history_table_body">
                    <tr><td colspan="15" style="text-align:center; color:#666;">Pilih WO untuk memuat data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                    url: 'get_wo_list.php', // Pastikan file ini sesuai di server Anda
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
                                            <td class="text-right">${wo.WO_QTY ? wo.WO_QTY : '-'}</td> 
                                            <td>${wo.MAC_CODE}</td>
                                            <td class="text-right">${wo.WO_CAP ? wo.WO_CAP : '-'}</td>
                                            <td>${wo.WO_START}</td>
                                            <td>${wo.WO_END}</td>
                                            <td style="text-align:center;"><input type="checkbox" disabled></td>
                                         </tr>`;
                            });
                            $('#wo_table_body').html(html);
                        } else {
                            $('#wo_table_body').html('<tr><td colspan="7" style="text-align:center;">Tidak ada data ditemukan.</td></tr>');
                        }
                    }
                });
            }, 300);
        }
    });

    $(document).on('click', '.wo-table tbody tr', function() {
        let woid = $(this).data('woid');
        if(!woid) return; 

        $('.wo-table tbody tr').removeClass('selected');
        $(this).addClass('selected');

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
            url: 'get_prod_history.php', // Pastikan file ini sesuai di server Anda
            type: 'GET',
            data: { wo_id: woid },
            dataType: 'json',
            success: function(historyData) {
                let histHtml = '';
                if(historyData.length > 0) {
                    $.each(historyData, function(i, h) {
                        let rowIcon = (i === 0) ? '▶' : '';
                        
                        let valOk = cekNol(h.PD_OK);
                        let valHo = cekNol(h.PD_HO);
                        let valNg = cekNol(h.PD_NG);
                        let valDt = cekNol(h.PD_LOSTHOUR);
                        let valSc = cekNol(h.PD_SC);
                        
                        let valWkh = cekNol(h.PD_WKH);
                        let valLostHr = cekNol(h.PD_LOSTHOUR);
                        let valSetHr = cekNol(h.PD_SETUPHOUR);
                        let valCav = cekNol(h.PD_CAV);
                        let valWeight = cekNol(h.PD_WEIGHT_S);
                        let valRun = cekNol(h.PD_RUN_S);
                        let valCytm = cekNol(h.PD_CYTM);
                        
                        histHtml += `<tr class="main-row" data-pdid="${h.PD_ID}">
                                        <td style="text-align:center;" class="row-indicator">${rowIcon}</td>
                                        <td>${h.PD_LOT}</td>
                                        <td style="text-align:center;"><span class="box-icon btn-expand-act">+</span></td>
                                        
                                        <td class="text-right p-0"><input type="number" class="grid-input in-ok" value="${valOk}"></td>
                                        <td class="text-right p-0"><input type="number" class="grid-input in-ho" value="${valHo}"></td>
                                        <td style="text-align:center;"><span class="box-icon">+</span></td>
                                        <td class="text-right p-0"><input type="number" class="grid-input in-ng" value="${valNg}"></td>
                                        
                                        <td style="text-align:center;"><span class="box-icon btn-expand-ng">+</span></td>
                                        
                                        <td class="text-right p-0"><input type="number" step="any" class="grid-input in-dt" value="${valDt}"></td>
                                        <td class="text-right p-0"><input type="number" step="any" class="grid-input in-purging" value="${valSc}"></td>
                                        
                                        <td class="p-0"><input type="text" class="grid-input in-rem" value="${h.PD_REM}"></td>
                                        <td class="p-0"><input type="text" class="grid-input in-opr" value="${h.PD_OPR}"></td>
                                        <td class="p-0"><input type="text" class="grid-input in-pic" value="${h.PD_PIC_LINE}"></td>
                                        <td>${h.PD_INPUT}</td>
                                        <td style="text-align:center; padding: 2px;"><button type="button" class="btn-delete" data-pdid="${h.PD_ID}">X</button></td>
                                     </tr>`;
                        
                        // ROW UNTUK ACTUAL DATA (EXPAND 1)
                        histHtml += `<tr class="sub-row sub-row-${h.PD_ID}" style="display:none; background-color:#e8e8e8;">
                                        <td colspan="15" class="p-0">
                                            <div class="actual-container">
                                                <div style="width: 150px;"></div>
                                                <div>
                                                    <div class="actual-header">Actual Data</div>
                                                    <table class="actual-table">
                                                        <tr><td>Work.Hr</td><td><input type="number" step="any" class="grid-input-act act-wkh" value="${valWkh}"></td></tr>
                                                        <tr><td>Lost.Hr</td><td><input type="number" step="any" class="grid-input-act act-losthr" value="${valLostHr}"></td></tr>
                                                        <tr><td>Set.Hr</td><td><input type="number" step="any" class="grid-input-act act-sethr" value="${valSetHr}"></td></tr>
                                                        <tr><td>Cav.</td><td><input type="number" class="grid-input-act act-cav" value="${valCav}"></td></tr>
                                                        <tr><td>Weight.S</td><td><input type="number" step="any" class="grid-input-act act-weights" value="${valWeight}"></td></tr>
                                                        <tr><td>Runner.S</td><td><input type="number" step="any" class="grid-input-act act-runs" value="${valRun}"></td></tr>
                                                        <tr><td>Cyl.Tm.</td><td><input type="number" step="any" class="grid-input-act act-cytm" value="${valCytm}"></td></tr>
                                                    </table>
                                                </div>
                                                <div style="flex:1;">
                                                    <div class="actual-header" style="width: 98%;">Reasons</div>
                                                    <input type="text" class="grid-input-act act-reason" style="width: 98%; border: 1px solid #ccc; border-top: none; padding: 5px; outline: none; background: #fff;" value="${h.PD_LOST_REASON}">
                                                </div>
                                                <div style="padding-top: 15px; margin-right: 15px;">
                                                    <button type="button" class="btn-save-act" data-pdid="${h.PD_ID}">Save<br>Actual Data</button>
                                                </div>
                                            </div>
                                        </td>
                                     </tr>`;

                        // ROW UNTUK NG DATA DENGAN DESAIN MIRIP DESKTOP (EXPAND 2)
                        histHtml += `<tr class="sub-row-ng sub-row-ng-${h.PD_ID}" style="display:none;">
                                        <td colspan="15" class="p-0">
                                            <div class="ng-panel-wrapper">
                                                <div class="ng-panel">
                                                    <table class="ng-grid">
                                                        <thead>
                                                            <tr>
                                                                <th style="width: 220px;">NG Type</th>
                                                                <th style="width: 90px;">NG Qty</th>
                                                                <th style="width: 40px; text-align:center;"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="ng-tbody-${h.PD_ID}">
                                                            <!-- Diisi via AJAX -->
                                                        </tbody>
                                                    </table>
                                                    <div class="ng-toolbar-bottom">
                                                        <!-- Ikon visual seperti di aplikasi desktop -->
                                                        <span style="font-size:10px; letter-spacing:2px;">|◄ ◄ ► ►|</span>
                                                        <span class="icon-btn btn-add-ng-row" data-pdid="${h.PD_ID}" title="Tambah Defect">➕</span>
                                                        <span style="font-size:10px; letter-spacing:2px;">➖ ▲ ▼ ❌ ↻</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                     </tr>`;
                    });
                } else {
                    histHtml = '<tr><td colspan="15" style="text-align:center; color:#666;">Data kosong. Klik "Tambah Baris Lot" untuk memulai.</td></tr>';
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
        if ($(this).hasClass('btn-del-ng')) return; // Lewati jika tombol ini adalah delete untuk detail NG
        
        let pdId = $(this).data('pdid');
        let $trMain = $(this).closest('.main-row');
        let $trSubAct = $('.sub-row-' + pdId);
        let $trSubNg = $('.sub-row-ng-' + pdId);

        if (confirm("Apakah Anda yakin ingin menghapus data Lot ini?")) {
            $.post(window.location.href, { action: 'delete_detail', pd_id: pdId }, function(res) {
                if(res.status === 'success') {
                    $trMain.fadeOut(300, function() { $(this).remove(); });
                    $trSubAct.remove();
                    $trSubNg.remove();
                } else {
                    let msg = (res.errors && res.errors[0]) ? res.errors[0].message : "";
                    alert("Gagal menghapus data!\n" + msg);
                }
            });
        }
    });

    $(document).on('click', '.btn-expand-act', function() {
        let $tr = $(this).closest('tr');
        let pdId = $tr.data('pdid');
        let $subRow = $('.sub-row-' + pdId);
        
        $('.sub-row-ng-' + pdId).hide();
        $tr.find('.btn-expand-ng').text('+');

        if ($subRow.is(':visible')) {
            $subRow.hide();
            $(this).text('+');
        } else {
            $subRow.show();
            $(this).text('-');
            $subRow.find('.act-wkh').focus();
        }
    });

    $(document).on('click', '.btn-save-act', function() {
        let pdId = $(this).data('pdid');
        let $subRow = $('.sub-row-' + pdId);
        
        let postData = {
            action: 'update_actual', pd_id: pdId,
            pd_wkh: $subRow.find('.act-wkh').val(), pd_losthr: $subRow.find('.act-losthr').val(),
            pd_sethr: $subRow.find('.act-sethr').val(), pd_cav: $subRow.find('.act-cav').val(),
            pd_weights: $subRow.find('.act-weights').val(), pd_runs: $subRow.find('.act-runs').val(),
            pd_cytm: $subRow.find('.act-cytm').val(), pd_reason: $subRow.find('.act-reason').val()
        };
        
        $.post(window.location.href, postData, function(res) {
            if(res.status === 'success') {
                $subRow.find('.actual-container').css('background', '#90ee90');
                setTimeout(() => $subRow.find('.actual-container').css('background', '#e8e8e8'), 600);
                $subRow.prev('.main-row').find('.in-dt').val(postData.pd_losthr);
            } else { 
                let msg = (res.errors && res.errors[0]) ? res.errors[0].message : "";
                alert("Gagal mengupdate Actual Data!\n" + msg); 
            }
        });
    });

    
// ==========================================
    // NG DATA EXPAND, AUTOCOMPLETE, ENTER SAVE
    // ==========================================
    $(document).on('click', '.btn-expand-ng', function() {
        let $tr = $(this).closest('tr');
        let pdId = $tr.data('pdid');
        let $subRowNg = $('.sub-row-ng-' + pdId);
        
        $('.sub-row-' + pdId).hide();
        $tr.find('.btn-expand-act').text('+');

        if ($subRowNg.is(':visible')) {
            $subRowNg.hide();
            $(this).text('+');
        } else {
            $subRowNg.show();
            $(this).text('-');
            loadNgGrid(pdId);
        }
    });

    function loadNgGrid(pdId) {
        let $tbody = $('#ng-tbody-' + pdId);
        $tbody.html('<tr><td colspan="4" style="text-align:center; padding:10px; color:#666;">Loading...</td></tr>');
        
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
                            // Lempar NGT_ID sebagai primary tracking ID (oldNgtId)
                            appendNgRow(pdId, row.NGT_ID, row.NGT_DESC, row.NGP_QTY);
                        });
                    } else {
                        $tbody.html('<tr class="ng-empty"><td colspan="4" style="text-align:center; color:#999; font-style:italic; padding:5px;">Kosong. Klik ➕ di bawah.</td></tr>');
                    }
                } else {
                    let errMsg = (res.errors && res.errors[0]) ? res.errors[0].message : "Terjadi kesalahan SQL.";
                    $tbody.html(`<tr><td colspan="4" style="color:red; padding:10px;">${errMsg}</td></tr>`);
                }
            },
            error: function() {
                $tbody.html('<tr><td colspan="4" style="color:red; padding:10px;">Gagal terhubung ke server.</td></tr>');
            }
        });
    }

    $(document).on('click', '.btn-add-ng-row', function() {
        let pdId = $(this).data('pdid');
        let $tbody = $('#ng-tbody-' + pdId);
        $tbody.find('.ng-empty').remove();
        
        appendNgRow(pdId, 0, '', '');
    });

    function appendNgRow(pdId, oldNgtId, ngDescVal, qtyVal) {
        let rowHtml = `
            <tr class="ng-row" data-oldngtid="${oldNgtId}" data-pdid="${pdId}">
                <td style="text-align:center; font-size:10px;" class="ng-row-indicator"></td>
                <td>
                    <input type="text" class="ng-type-input" list="ng-datalist" value="${ngDescVal}" placeholder="...">
                </td>
                <td>
                    <input type="number" class="ng-qty-input" style="text-align:right;" value="${qtyVal}" step="any">
                </td>
                <td style="text-align:center; background:#f5f5f5;">
                    <button type="button" class="btn-delete btn-del-ng" style="width: auto; padding: 2px 6px; font-size: 10px; border-radius:2px;" title="Hapus">X</button>
                </td>
            </tr>
        `;
        $('#ng-tbody-' + pdId).append(rowHtml);
        
        if (oldNgtId === 0) {
            $('#ng-tbody-' + pdId).find('.ng-row').last().find('.ng-type-input').focus();
        }
    }

    // Indikator panah dinamis saat grid di klik (Mimic Desktop)
    $(document).on('focus', '.ng-type-input, .ng-qty-input', function() {
        $(this).closest('tbody').find('.ng-row-indicator').text('');
        $(this).closest('tr').find('.ng-row-indicator').text('▶');
    });

    // FUNGSI SIMPAN NG DENGAN ENTER
    function saveNgRow($tr) {
        let oldNgtId = $tr.data('oldngtid');
        let pdId = $tr.data('pdid');
        let descInput = $tr.find('.ng-type-input').val().trim();
        let qty = $tr.find('.ng-qty-input').val();

        if (!descInput) { alert("Pilih tipe NG (NG Type) terlebih dahulu!"); return; }

        // Mencari ID berdasarkan ketikan (desc) dari array ngMasterData
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
                $tr.css('background-color', '#90ee90');
                setTimeout(() => { $tr.css('background-color', ''); }, 500);
                
                if (oldNgtId == 0) {
                    loadNgGrid(pdId); // Refresh baris jika insert data baru
                } else {
                    $tr.data('oldngtid', ngtId); // Update attribute dengan ID terbaru jika diubah
                }
            } else {
                let msg = (res.errors && res.errors[0]) ? res.errors[0].message : "";
                alert("Gagal simpan data NG!\n" + msg);
            }
        });
    }

    // Navigasi Keyboard Enter (NG Type -> Qty)
    $(document).on('keydown', '.ng-type-input', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $(this).closest('tr').find('.ng-qty-input').focus();
        }
    });

    // Navigasi Keyboard Enter (Qty -> Save ke Database)
    $(document).on('keydown', '.ng-qty-input', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveNgRow($(this).closest('tr'));
        }
    });

    // Hapus Data NG Detail
    $(document).on('click', '.btn-del-ng', function() {
        let $tr = $(this).closest('tr');
        let oldNgtId = $tr.data('oldngtid');
        let pdId = $tr.data('pdid');
        
        if (oldNgtId == 0) {
            $tr.remove(); // Hapus baris HTML jika belum pernah di-save ke DB
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


    // ==========================================
    // KEYBOARD NAVIGATION GRID UTAMA (Sama Seperti Aslinya)
    // ==========================================
    $(document).on('focus', '.grid-input', function() {
        $('.row-indicator').text('');
        $(this).closest('tr').find('.row-indicator').text('▶');
    });

    $(document).on('keydown', '.grid-input', function(e) {
        let $this = $(this);
        let $td = $this.closest('td');
        let $tr = $this.closest('tr.main-row'); 
        let colIndex = $tr.children('td').index($td);

        if (e.key === 'ArrowUp') {
            e.preventDefault(); $tr.prevAll('.main-row').first().children('td').eq(colIndex).find('.grid-input').focus();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault(); $tr.nextAll('.main-row').first().children('td').eq(colIndex).find('.grid-input').focus();
        } else if (e.key === 'ArrowLeft') {
            if ($this[0].selectionStart === 0 || $this.attr('type') === 'number') { e.preventDefault(); $td.prevAll().find('.grid-input').first().focus(); }
        } else if (e.key === 'ArrowRight') {
            if ($this[0].selectionStart === $this.val().length || $this.attr('type') === 'number') { e.preventDefault(); $td.nextAll().find('.grid-input').first().focus(); }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            let pdId = $tr.data('pdid');
            let dtValue = $tr.find('.in-dt').val();
            
            let postData = {
                action: 'update_detail', pd_id: pdId,
                pd_ok: $tr.find('.in-ok').val(), pd_ho: $tr.find('.in-ho').val(), pd_ng: $tr.find('.in-ng').val(),
                pd_dt: dtValue, pd_purging: $tr.find('.in-purging').val(), pd_rem: $tr.find('.in-rem').val(),
                pd_opr: $tr.find('.in-opr').val(), pd_pic: $tr.find('.in-pic').val()
            };
            
            $.post(window.location.href, postData, function(res) {
                if(res.status === 'success') {
                    $tr.css('background-color', '#90ee90');
                    setTimeout(() => $tr.css('background-color', ''), 500);
                    $('.sub-row-' + pdId).find('.act-losthr').val(dtValue);
                } else { 
                    let msg = (res.errors && res.errors[0]) ? res.errors[0].message : "";
                    alert("Gagal mengupdate data!\n" + msg); 
                }
            });
            
            $tr.nextAll('.main-row').first().children('td').eq(colIndex).find('.grid-input').focus();
        }
    });

    $(document).on('keydown', '.grid-input-act', function(e) {
        let $this = $(this);
        let $tr = $this.closest('tr'); 

        if (e.key === 'ArrowUp') {
            e.preventDefault(); $tr.prev().find('.grid-input-act').focus();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault(); $tr.next().find('.grid-input-act').focus();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            $this.closest('.actual-container').find('.btn-save-act').click();
            let $nextInput = $tr.next().find('.grid-input-act');
            if($nextInput.length) { $nextInput.focus(); } 
        }
    });
});
</script>

</body>
</html>
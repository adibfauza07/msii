<?php
// File: input_sms.php
error_reporting(0); // Mencegah PHP Warning merusak respons JSON
ob_start();         // Memulai output buffering

require_once __DIR__ . "/../config/global.php";

$db = isset($conn) ? $conn : (isset($connection) ? $connection : (isset($dbconn) ? $dbconn : null));

// Fungsi pembantu agar output selalu murni JSON
function sendJSON($data) {
    ob_clean(); // Hapus semua teks/error yang sempat tercetak sebelumnya
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// =================================================================
// 1. HANDLER AJAX: AMBIL LIST SEMUA WO (UNTUK AUTOCOMPLETE)
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'get_all_wo') {
    $sql = "SELECT WO_ID, WO_NUMBER FROM dbo.WO ORDER BY WO_NUMBER DESC";
    $stmt = sqlsrv_query($db, $sql);
    $wos = array();
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $wos[] = array('id' => $row['WO_ID'], 'number' => trim($row['WO_NUMBER']));
        }
    }
    sendJSON(array('status' => 'success', 'data' => $wos));
}

// =================================================================
// 1.5. HANDLER AJAX: AMBIL 10 SMS TERAKHIR (UNTUK AUTOCOMPLETE)
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'get_recent_sms') {
    $sql = "SELECT TOP 10 SMS_NO FROM dbo.SMS ORDER BY SMS_ID DESC";
    $stmt = sqlsrv_query($db, $sql);
    $sms_list = array();
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $sms_list[] = trim($row['SMS_NO']);
        }
    }
    sendJSON(array('status' => 'success', 'data' => $sms_list));
}

// =================================================================
// 2. HANDLER AJAX: AMBIL DATA INFO WO & ITEM DETAIL
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'get_wo_info') {
    $wo_number = trim($_POST['wo_number']);
    $sql = "SELECT W.WO_ID, W.WO_NUMBER, M.MAC_CODE, I.ITEM_CODE, I.ITEM_NAME 
            FROM dbo.WO W 
            LEFT JOIN dbo.MAC M ON W.MAC_ID = M.MAC_ID 
            LEFT JOIN dbo.ITEMS I ON W.ITEM_ID = I.ITEM_ID 
            WHERE W.WO_NUMBER = ?";
    $stmt = sqlsrv_query($db, $sql, array($wo_number));
    
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $row['MAC_CODE'] = trim(isset($row['MAC_CODE']) ? $row['MAC_CODE'] : '');
        $row['ITEM_CODE'] = trim(isset($row['ITEM_CODE']) ? $row['ITEM_CODE'] : '');
        $row['ITEM_NAME'] = trim(isset($row['ITEM_NAME']) ? $row['ITEM_NAME'] : '');
        sendJSON(array('status' => 'success', 'data' => $row));
    } else {
        sendJSON(array('status' => 'error', 'message' => 'WO tidak ditemukan'));
    }
}

// =================================================================
// 3. HANDLER AJAX: AMBIL LIST LOT BERDASARKAN WO
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'get_lots') {
    $wo_id = isset($_POST['wo_id']) ? (int)$_POST['wo_id'] : 0;
    $sql = "SELECT PD_ID, PD_LOT FROM dbo.PRODUCTION WHERE WO_ID = ? ORDER BY PD_LOT ASC";
    $stmt = sqlsrv_query($db, $sql, array($wo_id));
    $lots = array();
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $lots[] = $row;
        }
    }
    sendJSON(array('status' => 'success', 'data' => $lots));
}

// =================================================================
// 4. HANDLER AJAX: AMBIL MASTER MATERIAL / ITEMS
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'get_materials') {
    $sql = "SELECT ITEM_ID, ITEM_CODE, ITEM_NAME FROM dbo.ITEMS ORDER BY ITEM_CODE ASC";
    $stmt = sqlsrv_query($db, $sql);
    $materials = array();
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $materials[] = array(
                'id' => $row['ITEM_ID'],
                'code' => trim($row['ITEM_CODE']),
                'name' => trim($row['ITEM_NAME'])
            );
        }
    }
    sendJSON(array('status' => 'success', 'data' => $materials));
}

// =================================================================
// 5. HANDLER AJAX: CARI & NAVIGASI DATA LAMA
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'load_sms_data') {
    $nav = isset($_POST['nav']) ? $_POST['nav'] : '';
    $sms_no = isset($_POST['sms_no']) ? trim($_POST['sms_no']) : '';
    $current_id = isset($_POST['current_id']) ? (int)$_POST['current_id'] : 0;

    $sql = "";
    $params = array();

    if ($nav === 'search') {
        $sql = "SELECT TOP 1 S.SMS_ID, S.SMS_NO, S.WO_ID, S.SMS_DATE, S.SMS_INPUT, W.WO_NUMBER, M.MAC_CODE, I.ITEM_CODE, I.ITEM_NAME FROM dbo.SMS S LEFT JOIN dbo.WO W ON S.WO_ID = W.WO_ID LEFT JOIN dbo.MAC M ON W.MAC_ID = M.MAC_ID LEFT JOIN dbo.ITEMS I ON W.ITEM_ID = I.ITEM_ID WHERE S.SMS_NO = ?";
        $params = array($sms_no);
    } else if ($nav === 'first') {
        $sql = "SELECT TOP 1 S.SMS_ID, S.SMS_NO, S.WO_ID, S.SMS_DATE, S.SMS_INPUT, W.WO_NUMBER, M.MAC_CODE, I.ITEM_CODE, I.ITEM_NAME FROM dbo.SMS S LEFT JOIN dbo.WO W ON S.WO_ID = W.WO_ID LEFT JOIN dbo.MAC M ON W.MAC_ID = M.MAC_ID LEFT JOIN dbo.ITEMS I ON W.ITEM_ID = I.ITEM_ID ORDER BY S.SMS_ID ASC";
    } else if ($nav === 'last') {
        $sql = "SELECT TOP 1 S.SMS_ID, S.SMS_NO, S.WO_ID, S.SMS_DATE, S.SMS_INPUT, W.WO_NUMBER, M.MAC_CODE, I.ITEM_CODE, I.ITEM_NAME FROM dbo.SMS S LEFT JOIN dbo.WO W ON S.WO_ID = W.WO_ID LEFT JOIN dbo.MAC M ON W.MAC_ID = M.MAC_ID LEFT JOIN dbo.ITEMS I ON W.ITEM_ID = I.ITEM_ID ORDER BY S.SMS_ID DESC";
    } else if ($nav === 'prev') {
        $sql = "SELECT TOP 1 S.SMS_ID, S.SMS_NO, S.WO_ID, S.SMS_DATE, S.SMS_INPUT, W.WO_NUMBER, M.MAC_CODE, I.ITEM_CODE, I.ITEM_NAME FROM dbo.SMS S LEFT JOIN dbo.WO W ON S.WO_ID = W.WO_ID LEFT JOIN dbo.MAC M ON W.MAC_ID = M.MAC_ID LEFT JOIN dbo.ITEMS I ON W.ITEM_ID = I.ITEM_ID WHERE S.SMS_ID < ? ORDER BY S.SMS_ID DESC";
        $params = array($current_id);
    } else if ($nav === 'next') {
        $sql = "SELECT TOP 1 S.SMS_ID, S.SMS_NO, S.WO_ID, S.SMS_DATE, S.SMS_INPUT, W.WO_NUMBER, M.MAC_CODE, I.ITEM_CODE, I.ITEM_NAME FROM dbo.SMS S LEFT JOIN dbo.WO W ON S.WO_ID = W.WO_ID LEFT JOIN dbo.MAC M ON W.MAC_ID = M.MAC_ID LEFT JOIN dbo.ITEMS I ON W.ITEM_ID = I.ITEM_ID WHERE S.SMS_ID > ? ORDER BY S.SMS_ID ASC";
        $params = array($current_id);
    }

    $stmt = sqlsrv_query($db, $sql, $params);
    if ($stmt === false) { sendJSON(array('status' => 'error', 'message' => 'Query Header Gagal', 'errors' => sqlsrv_errors())); }

    if ($hdr = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $hdr['SMS_NO'] = trim(isset($hdr['SMS_NO']) ? $hdr['SMS_NO'] : '');
        $hdr['WO_NUMBER'] = trim(isset($hdr['WO_NUMBER']) ? $hdr['WO_NUMBER'] : '');
        $hdr['MAC_CODE'] = trim(isset($hdr['MAC_CODE']) ? $hdr['MAC_CODE'] : '');
        $hdr['ITEM_CODE'] = trim(isset($hdr['ITEM_CODE']) ? $hdr['ITEM_CODE'] : '');
        $hdr['ITEM_NAME'] = trim(isset($hdr['ITEM_NAME']) ? $hdr['ITEM_NAME'] : '');
        
        $sDate = $hdr['SMS_DATE'];
        if (is_object($sDate) && method_exists($sDate, 'format')) { $hdr['SMS_DATE'] = $sDate->format('Y-m-d'); }
        $iDate = $hdr['SMS_INPUT'];
        if (is_object($iDate) && method_exists($iDate, 'format')) { $hdr['SMS_INPUT'] = $iDate->format('Y-m-d'); }

        $sql_dtl = "SELECT D.PD_ID, P.PD_LOT, D.ITEM_ID, I.ITEM_CODE, I.ITEM_NAME, D.SMSD_QTY, D.SMSD_MATLOT, D.SMSD_SUPPLIED, D.SMSD_TIME
                    FROM dbo.SMS_DETAIL D
                    LEFT JOIN dbo.PRODUCTION P ON D.PD_ID = P.PD_ID
                    LEFT JOIN dbo.ITEMS I ON D.ITEM_ID = I.ITEM_ID
                    WHERE D.SMS_ID = ?";
        $stmt_dtl = sqlsrv_query($db, $sql_dtl, array($hdr['SMS_ID']));
        if ($stmt_dtl === false) { sendJSON(array('status' => 'error', 'message' => 'Query Detail Gagal', 'errors' => sqlsrv_errors())); }
        
        $details = array();
        while ($dtl = sqlsrv_fetch_array($stmt_dtl, SQLSRV_FETCH_ASSOC)) {
            $details[] = array(
                'pd_id' => $dtl['PD_ID'],
                'item_id' => $dtl['ITEM_ID'],
                'item_code' => trim(isset($dtl['ITEM_CODE']) ? $dtl['ITEM_CODE'] : ''),
                'item_name' => trim(isset($dtl['ITEM_NAME']) ? $dtl['ITEM_NAME'] : ''),
                'qty' => (float)$dtl['SMSD_QTY'],
                'matlot' => trim(isset($dtl['SMSD_MATLOT']) ? $dtl['SMSD_MATLOT'] : ''),
                'supplied' => trim(isset($dtl['SMSD_SUPPLIED']) ? $dtl['SMSD_SUPPLIED'] : ''),
                'time' => (float)$dtl['SMSD_TIME']
            );
        }
        sendJSON(array('status' => 'success', 'header' => $hdr, 'details' => $details));
    } else {
        sendJSON(array('status' => 'not_found'));
    }
}

// =================================================================
// 6. HANDLER AJAX: SIMPAN (HEADER & DETAIL)
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'save_sms') {
    $sms_id    = isset($_POST['sms_id']) ? (int)$_POST['sms_id'] : 0;
    $sms_no    = isset($_POST['sms_no']) ? trim($_POST['sms_no']) : '';
    $wo_id     = isset($_POST['wo_id']) ? (int)$_POST['wo_id'] : 0;
    $sms_date  = isset($_POST['sms_date']) ? $_POST['sms_date'] : '';
    $sms_input = isset($_POST['sms_input']) ? $_POST['sms_input'] : '';
    $details   = isset($_POST['details']) && is_array($_POST['details']) ? $_POST['details'] : array();
    
    sqlsrv_begin_transaction($db);

    try {
        if ($sms_id == 0) {
            // INSERT HEADER
            $sql_hdr = "INSERT INTO dbo.SMS (SMS_NO, WO_ID, SMS_DATE, SMS_INPUT) VALUES (?, ?, ?, ?)";
            $stmt_hdr = sqlsrv_query($db, $sql_hdr, array($sms_no, $wo_id, $sms_date, $sms_input));
            if (!$stmt_hdr) { throw new Exception("Gagal Insert Header SMS."); }
            
            // DAPATKAN ID BARU (Kompatibel Semua Versi SQL Server)
            $stmt_id = sqlsrv_query($db, "SELECT @@IDENTITY AS NEW_ID");
            if ($stmt_id && sqlsrv_fetch($stmt_id)) {
                $sms_id = sqlsrv_get_field($stmt_id, 0);
            } else {
                throw new Exception("Gagal mendapatkan ID SMS Baru.");
            }
        } else {
            // UPDATE HEADER & REPLACE DETAIL
            $sql_hdr = "UPDATE dbo.SMS SET SMS_NO=?, WO_ID=?, SMS_DATE=?, SMS_INPUT=? WHERE SMS_ID=?";
            $stmt_hdr = sqlsrv_query($db, $sql_hdr, array($sms_no, $wo_id, $sms_date, $sms_input, $sms_id));
            if (!$stmt_hdr) { throw new Exception("Gagal Update Header SMS."); }
            
            $sql_del = "DELETE FROM dbo.SMS_DETAIL WHERE SMS_ID=?";
            sqlsrv_query($db, $sql_del, array($sms_id));
        }

        // INSERT DETAIL
        foreach ($details as $d) {
            $pd_id = isset($d['pd_id']) ? (int)$d['pd_id'] : 0;
            $item_id = isset($d['item_id']) ? (int)$d['item_id'] : 0;
            $qty = isset($d['qty']) ? (float)$d['qty'] : 0;
            $matlot = isset($d['matlot']) ? trim($d['matlot']) : '';
            $supplied = isset($d['supplied']) ? trim($d['supplied']) : '';
            $time = isset($d['time']) ? (float)$d['time'] : 0;
            $time_rem = 0; // Default
            
            if ($pd_id > 0 && $item_id > 0) {
                $sql_dtl = "INSERT INTO dbo.SMS_DETAIL (SMS_ID, PD_ID, ITEM_ID, SMSD_MATLOT, SMSD_TIME, SMSD_SUPPLIED, SMSD_TIME_REM, SMSD_QTY) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_dtl = sqlsrv_query($db, $sql_dtl, array($sms_id, $pd_id, $item_id, $matlot, $time, $supplied, $time_rem, $qty));
                if (!$stmt_dtl) { throw new Exception("Gagal Insert Baris Detail SMS."); }
            }
        }
        
        sqlsrv_commit($db);
        sendJSON(array('status' => 'success', 'sms_id' => $sms_id));
        
    } catch (Exception $e) {
        sqlsrv_rollback($db);
        sendJSON(array('status' => 'error', 'message' => $e->getMessage(), 'errors' => sqlsrv_errors()));
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Supply Material Slip</title>
    <style>
        body { font-family: Tahoma, sans-serif; background-color: #d0d0d0; margin: 10px; font-size: 11px; }
        .window-container { width: 100%; max-width: 900px; margin: auto; background: #f0f0f0; border: 1px solid #666; box-shadow: 2px 2px 10px rgba(0,0,0,0.3); display: flex; flex-direction: column; }
        .header-panel { background-color: #9ac2e5; padding: 10px; display: flex; gap: 20px; border-bottom: 1px solid #666; }
        .form-col { flex: 1; display: flex; flex-direction: column; gap: 4px; }
        .form-row { display: flex; align-items: center; }
        .form-row label { width: 80px; text-align: right; padding-right: 10px; font-weight: normal; color:#000; }
        .header-input { padding: 3px; border: 1px solid #888; font-family: Tahoma, sans-serif; font-size: 11px; background: #fff; width: 160px; box-sizing: border-box; }
        .header-input:focus { border: 2px solid #000; padding: 2px; }
        .header-input[readonly] { background-color: #e4e4e4; color: #333; }
        .w-long { width: 300px; }
        .w-short { width: 60px; }

        .toolbar { background-color: #e8e8e8; padding: 5px 10px; display: flex; gap: 8px; border-bottom: 1px solid #999; align-items: center; }
        .tool-btn { background: transparent; border: none; font-size: 12px; font-weight: bold; cursor: pointer; color: #000; padding: 2px 5px; }
        .tool-btn:hover { background: #d0d0d0; border-radius: 3px; }
        .tool-sep { border-left: 1px solid #aaa; height: 16px; margin: 0 5px; }
        
        .grid-container { background: #fff; height: 300px; overflow-y: auto; padding: 0; border-bottom: 1px solid #999; }
        .data-grid { width: 100%; border-collapse: collapse; font-size: 11px; }
        .data-grid th { background: #e4e4e4; color: #000; padding: 4px; text-align: left; border: 1px solid #a0a0a0; font-weight: normal; position: sticky; top: 0; }
        .data-grid td { border: 1px solid #c0c0c0; padding: 0; }
        
        .grid-input { width: 100%; height: 20px; box-sizing: border-box; border: none; padding: 2px 4px; outline: none; font-family: inherit; font-size: inherit; background: transparent; }
        .grid-input:focus { background-color: #e2f0ff; }
        .text-right { text-align: right; }
        .row-indicator { width: 15px; text-align: center; background: #f0f0f0; border-right: 1px solid #ccc;}
        
        .status-bar { padding: 4px 10px; background: #e0e0e0; font-size: 10px; color: #333; }
    </style>
</head>
<body>

<div class="window-container">
    <div class="header-panel">
        <div class="form-col" style="max-width: 250px;">
            <div class="form-row">
                <label>SMS NO</label>
                <input type="text" id="sms_no" class="header-input" list="sms-datalist" placeholder="Ketik/Pilih lalu Enter..." autocomplete="off">
                <input type="hidden" id="sms_id" value="0">
            </div>
            <div class="form-row">
                <label>WO NO</label>
                <input type="text" id="wo_number" class="header-input" list="wo-datalist" placeholder="Ketik/Pilih WO..." autocomplete="off">
                <input type="hidden" id="wo_id" value="0">
            </div>
            <div class="form-row">
                <label>SMS DATE</label>
                <input type="date" id="sms_date" class="header-input" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-row">
                <label>INPUT DATE</label>
                <input type="date" id="sms_input" class="header-input" value="<?php echo date('Y-m-d'); ?>">
            </div>
        </div>
        
        <div class="form-col">
            <div class="form-row">
                <label>ITEM CODE</label>
                <input type="text" id="item_code" class="header-input" readonly>
            </div>
            <div class="form-row">
                <label>ITEM NAME</label>
                <input type="text" id="item_name" class="header-input w-long" readonly>
            </div>
            <div class="form-row">
                <label>M/C</label>
                <input type="text" id="mac_code" class="header-input w-short" readonly>
            </div>
        </div>
    </div>

    <div class="toolbar">
        <button class="tool-btn" id="btn_first" title="First Record">|◄</button>
        <button class="tool-btn" id="btn_fastrev" title="Fast Reverse">◄◄</button>
        <button class="tool-btn" id="btn_prev" title="Previous Record">◄</button>
        <button class="tool-btn" id="btn_next" title="Next Record">►</button>
        <button class="tool-btn" id="btn_fastfwd" title="Fast Forward">►►</button>
        <button class="tool-btn" id="btn_last" title="Last Record">►|</button>
        <div class="tool-sep"></div>
        <button class="tool-btn" id="btn_add" title="Tambah Transaksi Baru">➕ Baru</button>
        <button class="tool-btn" id="btn_add_row" title="Tambah Baris Material">⬇️ Baris</button>
        <div class="tool-sep"></div>
        <button class="tool-btn" id="btn_save" title="Simpan Data">💾 Simpan</button>
        <button class="tool-btn" id="btn_delete_row" title="Hapus Baris Terpilih">❌ Hapus</button>
    </div>

    <div class="grid-container">
        <table class="data-grid" id="tbl_detail">
            <thead>
                <tr>
                    <th style="width: 20px;"></th>
                    <th style="width: 120px;">LOT.NO</th>
                    <th style="width: 100px;">MAT CODE</th>
                    <th style="width: 200px;">MAT NAME</th>
                    <th style="width: 60px;">Qty</th>
                    <th style="width: 80px;">MAT.LOT</th>
                    <th style="width: 100px;">SUPPLIED</th>
                    <th style="width: 60px;">Time(s)</th>
                </tr>
            </thead>
            <tbody id="detail_body">
            </tbody>
        </table>
    </div>
    
    <div class="status-bar">
        Status: <span id="status_text">Ready</span>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    var materialsData = [];
    var lotsData = []; 
    var materialDatalist = '<datalist id="mat-datalist">';
    
    $.post(window.location.href, { action: 'get_recent_sms' }, function(res) {
        if(res.status === 'success') {
            var smsDatalist = '<datalist id="sms-datalist">';
            for(var i=0; i<res.data.length; i++) { smsDatalist += '<option value="' + res.data[i] + '">'; }
            smsDatalist += '</datalist>';
            $('body').append(smsDatalist);
        }
    });

    $.post(window.location.href, { action: 'get_all_wo' }, function(res) {
        if(res.status === 'success') {
            var woDatalist = '<datalist id="wo-datalist">';
            for(var i=0; i<res.data.length; i++) { woDatalist += '<option value="' + res.data[i].number + '">'; }
            woDatalist += '</datalist>';
            $('body').append(woDatalist);
        }
    });

    $.post(window.location.href, { action: 'get_materials' }, function(res) {
        if(res.status === 'success') {
            materialsData = res.data;
            for(var i=0; i<materialsData.length; i++) { materialDatalist += '<option value="' + materialsData[i].code + '">'; }
            materialDatalist += '</datalist>';
            $('body').append(materialDatalist);
        }
    });

    $('#wo_number').on('change', function() {
        var woNum = $(this).val().trim();
        if(!woNum) { clearHeader(); return; }
        
        setStatus("Mengambil data WO...");
        $.post(window.location.href, { action: 'get_wo_info', wo_number: woNum }, function(res) {
            if(res.status === 'success') {
                $('#wo_id').val(res.data.WO_ID);
                $('#item_code').val(res.data.ITEM_CODE);
                $('#item_name').val(res.data.ITEM_NAME);
                $('#mac_code').val(res.data.MAC_CODE);
                loadLotsForWO(res.data.WO_ID);
            } else {
                alert("Nomor WO tidak ditemukan!");
                clearHeader();
            }
        });
    });

    function clearHeader() {
        $('#wo_id').val(0);
        $('#item_code, #item_name, #mac_code').val('');
        lotsData = []; 
        updateAllLotDropdowns();
        setStatus("Ready");
    }

    function loadLotsForWO(woid, callback) {
        $.post(window.location.href, { action: 'get_lots', wo_id: woid }, function(res) {
            if(res.status === 'success') {
                lotsData = res.data; 
                updateAllLotDropdowns(); 
                setStatus("Data WO dan Lot siap.");
            }
            if(callback) callback();
        }, 'json').fail(function() {
            if(callback) callback();
        });
    }

    // MAIN AJAX NAVIGATOR
    function fetchRecord(actionType, paramValue) {
        setStatus("Mencari data...");
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                action: 'load_sms_data',
                nav: actionType,
                sms_no: (actionType === 'search') ? paramValue : '',
                current_id: (actionType !== 'search') ? paramValue : 0
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#sms_id').val(res.header.SMS_ID);
                    $('#sms_no').val(res.header.SMS_NO);
                    $('#wo_id').val(res.header.WO_ID);
                    $('#wo_number').val(res.header.WO_NUMBER);
                    $('#sms_date').val(res.header.SMS_DATE);
                    $('#sms_input').val(res.header.SMS_INPUT);
                    $('#item_code').val(res.header.ITEM_CODE);
                    $('#item_name').val(res.header.ITEM_NAME);
                    $('#mac_code').val(res.header.MAC_CODE);

                    loadLotsForWO(res.header.WO_ID, function() {
                        $('#detail_body').empty();
                        if(res.details && res.details.length > 0) {
                            for(var i=0; i<res.details.length; i++) { appendDetailRowObj(res.details[i]); }
                        }
                        setStatus("Data berhasil dimuat.");
                    });
                } else if (res.status === 'not_found') {
                    if (actionType === 'search') alert("Nomor SMS tidak ditemukan di Database.");
                    setStatus("Ready");
                } else {
                    alert("Error Database: \n" + res.message + "\n\n" + JSON.stringify(res.errors));
                    setStatus("Error data.");
                }
            },
            error: function(xhr, status, error) {
                alert("Error Request ke Server. Mohon periksa Koneksi/PHP:\n\n" + xhr.responseText.substring(0, 500));
                setStatus("Error server.");
            }
        });
    }

    // TRIGGER SEARCH
    $('#sms_no').on('keypress', function(e) {
        if(e.which == 13) {
            e.preventDefault();
            var val = $(this).val().trim();
            if(val) fetchRecord('search', val);
        }
    });
    
    $('#sms_no').on('input', function() {
        var val = $(this).val();
        var listId = $(this).attr('list');
        if (!val || !listId) return;
        var isDatalistOption = false;
        $('#' + listId + ' option').each(function() {
            if($(this).val() === val) { isDatalistOption = true; return false; }
        });
        if(isDatalistOption) { fetchRecord('search', val); $(this).blur(); }
    });

    // ACTION TOMBOL NAVBAR BAWAH
    $('#btn_first').click(function() { fetchRecord('first', 0); });
    $('#btn_last').click(function() { fetchRecord('last', 0); });
    $('#btn_prev, #btn_fastrev').click(function() {
        var cid = $('#sms_id').val();
        if(cid > 0) fetchRecord('prev', cid);
    });
    $('#btn_next, #btn_fastfwd').click(function() {
        var cid = $('#sms_id').val();
        if(cid > 0) fetchRecord('next', cid);
    });

    // FUNGSI RENDER DETAIL
    function generateLotOptions(selectedId) {
        if (selectedId === undefined || selectedId === null) { selectedId = ''; }
        var opt = '<option value="">- Pilih Lot -</option>';
        for(var i=0; i<lotsData.length; i++) {
            var l = lotsData[i];
            var sel = (l.PD_ID == selectedId) ? 'selected' : '';
            opt += '<option value="' + l.PD_ID + '" ' + sel + '>' + l.PD_LOT + '</option>';
        }
        return opt;
    }

    function updateAllLotDropdowns() {
        $('.in-lot').each(function() {
            var currentVal = $(this).val();
            $(this).html(generateLotOptions(currentVal));
        });
    }

    function appendDetailRowObj(d) {
        var tr = '<tr class="dtl-row">' +
                    '<td class="row-indicator"></td>' +
                    '<td><select class="grid-input in-lot">' + generateLotOptions(d.pd_id) + '</select></td>' +
                    '<td>' +
                        '<input type="text" class="grid-input in-matcode" list="mat-datalist" value="' + d.item_code + '" placeholder="Ketik Kode...">' +
                        '<input type="hidden" class="in-itemid" value="' + d.item_id + '">' +
                    '</td>' +
                    '<td><input type="text" class="grid-input in-matname" value="' + d.item_name + '" readonly style="background:#f5f5f5; color:#666;"></td>' +
                    '<td><input type="number" step="any" class="grid-input text-right in-qty" value="' + d.qty + '"></td>' +
                    '<td><input type="text" class="grid-input in-matlot" value="' + d.matlot + '"></td>' +
                    '<td><input type="text" class="grid-input in-supplied" value="' + d.supplied + '"></td>' +
                    '<td><input type="number" step="any" class="grid-input text-right in-time" value="' + d.time + '"></td>' +
                '</tr>';
        $('#detail_body').append(tr);
    }

    $('#btn_add_row').on('click', function() {
        var woid = $('#wo_id').val();
        if(woid == 0 || woid == '') { alert('Ketik/Pilih WO NO yang valid terlebih dahulu!'); return; }
        appendDetailRowObj({pd_id:'', item_id:0, item_code:'', item_name:'', qty:0, matlot:'', supplied:'', time:0});
        setStatus("Baris baru ditambahkan.");
    });

    $(document).on('change', '.in-matcode', function() {
        var code = $(this).val().trim();
        var $tr = $(this).closest('tr');
        var mat = null;
        for (var i = 0; i < materialsData.length; i++) {
            if (materialsData[i].code.toLowerCase() === code.toLowerCase()) { mat = materialsData[i]; break; }
        }
        if(mat) {
            $tr.find('.in-itemid').val(mat.id);
            $tr.find('.in-matname').val(mat.name);
        } else {
            $tr.find('.in-itemid').val(0);
            $tr.find('.in-matname').val('');
        }
    });

    $(document).on('focus', '.grid-input', function() {
        $('.row-indicator').text('');
        $(this).closest('tr').find('.row-indicator').text('▶');
        $('.dtl-row').css('background-color', '');
        $(this).closest('tr').css('background-color', '#e2f0ff');
    });

    $('#btn_delete_row').on('click', function() {
        var $activeRow = $('.row-indicator:contains("▶")').closest('tr');
        if($activeRow.length > 0) {
            $activeRow.remove();
            setStatus("Baris dihapus.");
        } else {
            alert("Klik/Pilih pada baris yang ingin dihapus terlebih dahulu.");
        }
    });

    $('#btn_add').on('click', function() {
        $('#sms_id').val(0);
        $('#sms_no').val('');
        $('#wo_number').val('');
        clearHeader();
        $('#detail_body').empty();
        $('#sms_no').focus();
        setStatus("Siap input data baru.");
    });

    $('#btn_save').on('click', function() {
        var sms_id = $('#sms_id').val();
        var sms_no = $('#sms_no').val().trim();
        var wo_id = $('#wo_id').val();
        var sms_date = $('#sms_date').val();
        var sms_input = $('#sms_input').val();

        if(!sms_no || wo_id == 0) { alert("SMS NO dan WO NO yang valid harus diisi!"); return; }

        var details = [];
        var valid = true;

        $('.dtl-row').each(function() {
            var pd_id = $(this).find('.in-lot').val();
            var item_id = $(this).find('.in-itemid').val();
            if(!pd_id || item_id == 0) {
                valid = false;
                $(this).css('background-color', '#ffcccc');
            } else {
                details.push({
                    pd_id: pd_id, item_id: item_id,
                    qty: $(this).find('.in-qty').val() || 0,
                    matlot: $(this).find('.in-matlot').val() || '',
                    supplied: $(this).find('.in-supplied').val() || '',
                    time: $(this).find('.in-time').val() || 0
                });
            }
        });

        if(!valid) { alert("Ada baris grid yang belum lengkap."); return; }
        if(details.length === 0) { alert("Detail material kosong!"); return; }

        setStatus("Menyimpan...");
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                action: 'save_sms', sms_id: sms_id, sms_no: sms_no, wo_id: wo_id, 
                sms_date: sms_date, sms_input: sms_input, details: details
            },
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    $('#sms_id').val(res.sms_id); 
                    alert("Data berhasil disimpan!");
                    setStatus("Saved.");
                    $('.dtl-row').css('background-color', '');
                } else {
                    alert("Gagal menyimpan: \n" + res.message + "\n\n" + JSON.stringify(res.errors));
                    setStatus("Error saat menyimpan.");
                }
            },
            error: function(xhr) {
                alert("Error Saat Menyimpan (Parsing/PHP Error):\n\n" + xhr.responseText.substring(0, 500));
                setStatus("Error server.");
            }
        });
    });
    
    function setStatus(msg) { $('#status_text').text(msg); }
});
</script>

</body>
</html>
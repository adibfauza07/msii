<?php
// File: input_sms.php
error_reporting(0);
ob_start();

// Sesuaikan path ini dengan struktur folder aplikasi Anda
require_once __DIR__ . "/../config/global.php";

$db = isset($conn) ? $conn : (isset($connection) ? $connection : (isset($dbconn) ? $dbconn : null));

function sendJSON($data) {
    ob_clean(); 
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function get_sql_errors() {
    $errors = sqlsrv_errors();
    if ($errors == null) return "Unknown SQL Error";
    $msg = "";
    foreach ($errors as $error) {
        $msg .= $error['message'] . " ";
    }
    return $msg;
}

// =================================================================
// 1. HANDLER AJAX: CARI WO ASINKRON
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'search_wo') {
    $term = isset($_POST['term']) ? trim($_POST['term']) : '';
    $sql = "SELECT TOP 50 WO_ID, WO_NUMBER FROM dbo.WO WHERE WO_NUMBER LIKE ? ORDER BY WO_NUMBER DESC";
    $stmt = sqlsrv_query($db, $sql, array("%" . $term . "%"));
    $wos = array();
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $wos[] = trim($row['WO_NUMBER']);
        }
    }
    sendJSON($wos);
}

// =================================================================
// 1.5. HANDLER AJAX: CARI SMS ASINKRON
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'search_sms') {
    $term = isset($_POST['term']) ? trim($_POST['term']) : '';
    $sql = "SELECT TOP 50 SMS_NO FROM dbo.SMS WHERE SMS_NO LIKE ? ORDER BY SMS_NO DESC";
    $stmt = sqlsrv_query($db, $sql, array("%" . $term . "%"));
    $sms_list = array();
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $sms_list[] = trim($row['SMS_NO']);
        }
    }
    sendJSON($sms_list);
}

// =================================================================
// 2. HANDLER AJAX: AMBIL DATA INFO WO
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
// 3. HANDLER AJAX: AMBIL LIST LOT & PENCARIAN MATERIAL AJAX (TOP 10)
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'get_lots') {
    $wo_id = isset($_POST['wo_id']) ? (int)$_POST['wo_id'] : 0;
    
    $sql = "SELECT DISTINCT PD_LOT 
            FROM dbo.PRODUCTION 
            WHERE WO_ID = ? 
            ORDER BY PD_LOT ASC";
            
    $stmt = sqlsrv_query($db, $sql, array($wo_id));
    $lots = array();
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $lots[] = array(
                'lot' => trim($row['PD_LOT'])
            );
        }
    }
    sendJSON(array('status' => 'success', 'data' => $lots));
}

// OPTIMASI: Pencarian Material Server-Side (Limit 10 data teratas yang mirip)
if (isset($_POST['action']) && $_POST['action'] === 'search_material') {
    $term = isset($_POST['term']) ? trim($_POST['term']) : '';
    
    $sql = "SELECT TOP 10 ITEM_CODE, ITEM_NAME, ITEM_ID, ITTY_CODE 
            FROM dbo.ITEMS 
            WHERE (ITTY_CODE IN ('01', '02', '03', '05', '13')) 
            AND ITEM_CODE LIKE ? 
            ORDER BY ITEM_CODE ASC";
            
    $stmt = sqlsrv_query($db, $sql, array("%" . $term . "%"));
    $materials = array();
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $materials[] = array(
                'label' => trim($row['ITEM_CODE']),
                'value' => trim($row['ITEM_CODE']),
                'name'  => trim($row['ITEM_NAME'])
            );
        }
    }
    sendJSON($materials);
}

// =================================================================
// 4. HANDLER AJAX: LOAD DATA SMS LAMA
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'load_sms_data') {
    $nav = isset($_POST['nav']) ? $_POST['nav'] : '';
    $sms_no = isset($_POST['sms_no']) ? trim($_POST['sms_no']) : '';
    $current_id = isset($_POST['current_id']) ? (int)$_POST['current_id'] : 0;

    $sql = ""; $params = array();

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
    if ($stmt === false) { sendJSON(array('status' => 'error', 'message' => 'Query Header Gagal: ' . get_sql_errors())); }

    if ($hdr = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $hdr['SMS_NO'] = trim(isset($hdr['SMS_NO']) ? $hdr['SMS_NO'] : '');
        $hdr['WO_NUMBER'] = trim(isset($hdr['WO_NUMBER']) ? $hdr['WO_NUMBER'] : '');
        $hdr['MAC_CODE'] = trim(isset($hdr['MAC_CODE']) ? $hdr['MAC_CODE'] : '');
        $hdr['ITEM_CODE'] = trim(isset($hdr['ITEM_CODE']) ? $hdr['ITEM_CODE'] : '');
        $hdr['ITEM_NAME'] = trim(isset($hdr['ITEM_NAME']) ? $hdr['ITEM_NAME'] : '');
        
        $sDate = $hdr['SMS_DATE'];
        if (is_object($sDate)) { $hdr['SMS_DATE'] = $sDate->format('d/m/Y'); }
        $iDate = $hdr['SMS_INPUT'];
        if (is_object($iDate)) { $hdr['SMS_INPUT'] = $iDate->format('d/m/Y'); }

        $sql_dtl = "SELECT D.PD_ID, P.PD_LOT, D.ITEM_ID, I.ITEM_CODE, I.ITEM_NAME, D.SMSD_QTY, D.SMSD_MATLOT, D.SMSD_SUPPLIED, D.SMSD_TIME
                    FROM dbo.SMS_DETAIL D
                    LEFT JOIN dbo.PRODUCTION P ON D.PD_ID = P.PD_ID
                    LEFT JOIN dbo.ITEMS I ON D.ITEM_ID = I.ITEM_ID
                    WHERE D.SMS_ID = ?";
        $stmt_dtl = sqlsrv_query($db, $sql_dtl, array($hdr['SMS_ID']));
        
        $details = array();
        while ($dtl = sqlsrv_fetch_array($stmt_dtl, SQLSRV_FETCH_ASSOC)) {
            $details[] = array(
                'pd_lot' => trim(isset($dtl['PD_LOT']) ? $dtl['PD_LOT'] : ''),
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
// 5. HANDLER AJAX: SIMPAN KE DATABASE 
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'save_sms') {
    $header = isset($_POST['header']) ? $_POST['header'] : array();
    $details = isset($_POST['details']) ? $_POST['details'] : array();
    
    $sms_id = isset($header['sms_id']) ? (int)$header['sms_id'] : 0;
    $sms_no = isset($header['sms_no']) ? trim($header['sms_no']) : '';
    $wo_id  = isset($header['wo_id']) ? (int)$header['wo_id'] : 0;
    
    $sms_date_raw = isset($header['sms_date']) ? $header['sms_date'] : date('d/m/Y');
    $dateObj = DateTime::createFromFormat('d/m/Y', $sms_date_raw);
    $sms_date = $dateObj ? $dateObj->format('Y-m-d') : date('Y-m-d');

    if (empty($sms_no) || $wo_id == 0) {
        sendJSON(array('status' => 'error', 'message' => 'SMS NO atau WO NO kosong.'));
    }

    sqlsrv_begin_transaction($db);
    $ok = true;
    $error_msg = "";

    if ($sms_id == 0) {
        $stmtCek = sqlsrv_query($db, "SELECT SMS_ID FROM dbo.SMS WHERE SMS_NO = ?", array($sms_no));
        if ($stmtCek && $rCek = sqlsrv_fetch_array($stmtCek, SQLSRV_FETCH_ASSOC)) {
            $sms_id = $rCek['SMS_ID'];
        }
    }

    if ($sms_id == 0) {
        $sqlH = "
            SET NOCOUNT ON;
            DECLARE @OutputTbl TABLE (NEW_ID INT);
            INSERT INTO dbo.SMS (SMS_NO, WO_ID, SMS_DATE, SMS_INPUT) 
            OUTPUT INSERTED.SMS_ID INTO @OutputTbl
            VALUES (?, ?, ?, GETDATE());
            SELECT NEW_ID FROM @OutputTbl;
        ";
        $stmtH = sqlsrv_query($db, $sqlH, array($sms_no, $wo_id, $sms_date));
        if ($stmtH === false) { 
            $ok = false; $error_msg = "Insert Header Gagal: " . get_sql_errors(); 
        } else {
            $row = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
            if ($row && isset($row['NEW_ID'])) {
                $sms_id = $row['NEW_ID'];
            } else {
                if (sqlsrv_next_result($stmtH)) {
                    $row = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
                    if ($row && isset($row['NEW_ID'])) $sms_id = $row['NEW_ID'];
                }
            }
        }
        if ($ok && $sms_id == 0) { $ok = false; $error_msg = "ID SMS baru gagal ditangkap dari Insert."; }
    } else {
        $sqlH = "UPDATE dbo.SMS SET SMS_NO = ?, WO_ID = ?, SMS_DATE = ? WHERE SMS_ID = ?";
        $stmtH = sqlsrv_query($db, $sqlH, array($sms_no, $wo_id, $sms_date, $sms_id));
        if ($stmtH === false) { $ok = false; $error_msg = "Update Header Gagal: " . get_sql_errors(); }
    }

    if ($ok && $sms_id > 0) {
        $stmtDel = sqlsrv_query($db, "DELETE FROM dbo.SMS_DETAIL WHERE SMS_ID = ?", array($sms_id));
        if ($stmtDel === false) { $ok = false; $error_msg = "Gagal Reset Detail: " . get_sql_errors(); }

        if ($ok) {
            foreach ($details as $dtl) {
                $pd_lot = trim($dtl['pd_lot']);
                $item_code = trim($dtl['item_code']);
                $qty = (float)$dtl['qty'];
                $matlot = trim($dtl['matlot']);
                $supplied = trim($dtl['supplied']);
                $time = (float)$dtl['time'];

                if ($pd_lot === '' || $item_code === '') continue;

                $pd_id = 0;
                $stmtPd = sqlsrv_query($db, "SELECT TOP 1 PD_ID FROM dbo.PRODUCTION WHERE PD_LOT = ? AND WO_ID = ?", array($pd_lot, $wo_id));
                if ($stmtPd && $rPd = sqlsrv_fetch_array($stmtPd, SQLSRV_FETCH_ASSOC)) { $pd_id = $rPd['PD_ID']; }

                $item_id = 0;
                $stmtIt = sqlsrv_query($db, "SELECT TOP 1 ITEM_ID FROM dbo.ITEMS WHERE ITEM_CODE = ?", array($item_code));
                if ($stmtIt && $rIt = sqlsrv_fetch_array($stmtIt, SQLSRV_FETCH_ASSOC)) { $item_id = $rIt['ITEM_ID']; }

                if ($pd_id > 0 && $item_id > 0) {
                    $sqlIns = "INSERT INTO dbo.SMS_DETAIL (SMS_ID, PD_ID, ITEM_ID, SMSD_QTY, SMSD_MATLOT, SMSD_SUPPLIED, SMSD_TIME) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmtIns = sqlsrv_query($db, $sqlIns, array($sms_id, $pd_id, $item_id, $qty, $matlot, $supplied, $time));
                    if ($stmtIns === false) { $ok = false; $error_msg = "Insert Detail Gagal (Lot: $pd_lot): " . get_sql_errors(); break; }
                }
            }
        }
    }

    if ($ok) {
        sqlsrv_commit($db);
        sendJSON(array('status' => 'success', 'sms_id' => $sms_id));
    } else {
        sqlsrv_rollback($db);
        sendJSON(array('status' => 'error', 'message' => $error_msg));
    }
}

// =================================================================
// 6. HANDLER AJAX: HAPUS DOKUMEN SMS
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'delete_sms') {
    $sms_id = isset($_POST['sms_id']) ? (int)$_POST['sms_id'] : 0;
    
    if ($sms_id <= 0) {
        sendJSON(array('status' => 'error', 'message' => 'ID SMS tidak valid.'));
    }

    sqlsrv_begin_transaction($db);
    
    $stmt1 = sqlsrv_query($db, "DELETE FROM dbo.SMS_DETAIL WHERE SMS_ID = ?", array($sms_id));
    $stmt2 = sqlsrv_query($db, "DELETE FROM dbo.SMS WHERE SMS_ID = ?", array($sms_id));
    
    if ($stmt1 !== false && $stmt2 !== false) {
        sqlsrv_commit($db);
        sendJSON(array('status' => 'success'));
    } else {
        sqlsrv_rollback($db);
        sendJSON(array('status' => 'error', 'message' => get_sql_errors()));
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supply Material Slip</title>
    
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    
    <style>
        .my-classic-ui {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            background-color: #cde6cc; 
            width: 100%; min-height: 80vh;
            display: flex; flex-direction: column;
            padding: 15px; box-sizing: border-box;
        }

        .top-search-bar { display: flex; justify-content: flex-end; margin-bottom: 15px; }
        .top-search-input { width: 250px; padding: 4px 8px; border: 1px solid #007bff; border-radius: 2px; font-size: 12px; }

        .header-panel { display: flex; justify-content: flex-start; gap: 30px; margin-bottom: 10px; }
        .form-group-cls { margin-bottom: 8px; }
        .form-group-cls label { display: block; font-weight: bold; margin-bottom: 2px; color: #111; font-size: 11px; }
        .classic-input {
            border: 1px solid #a9a9a9; padding: 3px 5px; font-size: 12px; outline: none;
            background-color: white; width: 220px; box-sizing: border-box;
        }
        .classic-input:read-only { background-color: #e4e4e4; }

        .control-section { margin-top: 15px; margin-bottom: 10px; }
        .navigator-box { display: flex; align-items: center; gap: 5px; margin-bottom: 8px; }
        .btn-nav { background: #f0f0f0; border: 1px solid #888; padding: 2px 8px; font-weight: bold; cursor: pointer; }
        
        .fieldset-control { border: 1px solid #a9a9a9; padding: 8px 10px; display: inline-block; }
        .fieldset-control legend { font-size: 11px; color: #333; padding: 0 4px; }
        .btn-classic {
            background: #f0f0f0; border: 1px solid #888; border-bottom-color: #444; border-right-color: #444;
            padding: 3px 12px; font-size: 12px; color: #000; cursor: pointer; margin-right: 2px; min-width: 70px;
        }
        .btn-classic:active { border: 1px solid #444; border-bottom-color: #888; border-right-color: #888; background: #e4e4e4; }

        .grid-container { flex-grow: 1; overflow-y: auto; background: white; border: 1px solid #a9a9a9; max-height: 400px; }
        table.datagrid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.datagrid th {
            background-color: #ece9d8; border-right: 1px solid #c0c0c0; border-bottom: 1px solid #888;
            font-weight: normal; font-size: 11px; padding: 4px; text-align: left; position: sticky; top: 0; z-index: 2;
        }
        table.datagrid td { border-right: 1px solid #e0e0e0; border-bottom: 1px solid #e0e0e0; padding: 0; font-size: 11px; height: 22px; }
        table.datagrid input { width: 100%; height: 100%; border: none; padding: 0 4px; font-size: 11px; background: transparent; box-sizing: border-box; outline: none; }
        table.datagrid input:focus { background-color: #e7f1ff; } 
        
        .row-indicator { width: 25px; text-align: center; background-color: #ece9d8; border-right: 1px solid #c0c0c0; }
        .row-indicator span { display: none; font-size: 10px; }
        
        tr.active-row td { background-color: #fff4cc !important; }
        tr.active-row td.row-indicator { background-color: #ece9d8 !important; }
        tr.active-row td.row-indicator span { display: block; color: black; }

        .ui-autocomplete { font-size: 11px; max-height: 200px; overflow-y: auto; overflow-x: hidden; border-radius: 0; border: 1px solid #888; z-index: 9999 !important; }
        .ui-menu-item-wrapper { padding: 3px 5px !important; }
        .ui-state-active { background: #0058e6 !important; color: white !important; border: none !important; margin:0 !important; }
    </style>
</head>
<body class="sidebar-mini">

<div class="wrapper">
    <div class="content-wrapper" style="background: transparent; min-height: auto;">
        <div class="my-classic-ui">
            
            <div class="top-search-bar">
                <input type="text" id="search_global" class="top-search-input" placeholder="Search Document/BC/Type..." autocomplete="off">
            </div>

            <div class="header-panel">
                <div>
                    <div class="form-group-cls">
                        <label>Internal Document (SMS NO)</label>
                        <input type="text" id="sms_no" class="classic-input" autocomplete="off" placeholder="Ketik lalu Enter/Pilih">
                        <input type="hidden" id="sms_id" value="0">
                    </div>
                    <div class="form-group-cls">
                        <label>WO NO</label>
                        <input type="text" id="wo_number" class="classic-input" autocomplete="off">
                        <input type="hidden" id="wo_id" value="0">
                    </div>
                    <div class="form-group-cls" style="display:flex; gap: 10px;">
                        <div>
                            <label>Input Date</label>
                            <input type="text" id="input_date" class="classic-input bg-light" value="<?php echo date('d/m/Y'); ?>" readonly style="width:105px;">
                        </div>
                        <div>
                            <label>SMS Date</label>
                            <input type="text" id="sms_date" class="classic-input" value="<?php echo date('d/m/Y'); ?>" style="width:105px;">
                        </div>
                    </div>
                </div>
                
                <div>
                    <div class="form-group-cls">
                        <label>Item Code</label>
                        <input type="text" id="item_code" class="classic-input" readonly>
                    </div>
                    <div class="form-group-cls">
                        <label>Item Name</label>
                        <input type="text" id="item_name" class="classic-input" readonly style="width: 350px;">
                    </div>
                    <div class="form-group-cls">
                        <label>M/C</label>
                        <input type="text" id="mac_code" class="classic-input" readonly>
                    </div>
                </div>
            </div>

            <div class="control-section">
                <div class="navigator-box">
                    <input type="text" class="classic-input" style="width: 150px; background: white; font-weight: bold; color: blue;" id="status_text" value="Ready." readonly>
                    <button class="btn-nav" id="btn_first">|&lt;</button>
                    <button class="btn-nav" id="btn_prev">&lt;</button>
                    <button class="btn-nav" id="btn_next">&gt;</button>
                    <button class="btn-nav" id="btn_last">&gt;|</button>
                </div>
                
                <fieldset class="fieldset-control">
                    <legend>Control</legend>
                    <button type="button" class="btn-classic" id="btn_add">Tambah</button>
                    <button type="button" class="btn-classic">Rubah</button>
                    <button type="button" class="btn-classic" id="btn_delete">Hapus</button>
                    <button type="button" class="btn-classic" id="btn_save">Simpan</button>
                    <button type="button" class="btn-classic" onclick="resetForm()">Batal</button>
                    <button type="button" class="btn-classic" onclick="location.reload()">Refresh</button>
                </fieldset>
            </div>

            <div class="grid-container">
                <table class="datagrid" id="tbl_detail">
                    <thead>
                        <tr>
                            <th style="width: 30px;" class="text-center">###</th>
                            <th style="width: 120px;">LOT.NO</th>
                            <th style="width: 100px;">MAT CODE</th>
                            <th style="width: 250px;">MAT NAME</th>
                            <th style="width: 70px; text-align: right;">QTY</th>
                            <th style="width: 100px;">MAT.LOT</th>
                            <th style="width: 100px;">SUPPLIED</th>
                            <th style="width: 60px;">Time(s)</th>
                            <th style="width: 30px;" class="text-center">ACT</th>
                        </tr>
                    </thead>
                    <tbody id="detail_body">
                        <!-- Baris dirender JS -->
                    </tbody>
                </table>
            </div>

        </div> 
    </div> 
</div> 

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
$(document).ready(function() {
    let listLots = []; 

    // ==========================================
    // 1. AUTOCOMPLETE AJAX
    // ==========================================
    $('#search_global').autocomplete({
        source: function(request, response) {
            $.ajax({
                url: window.location.href, type: "POST", dataType: "json",
                data: { action: 'search_sms', term: request.term },
                success: function(data) { response(data); }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            $(this).val(ui.item.value);
            loadSmsData('search', ui.item.value);
            return false;
        }
    });

    $('#wo_number').autocomplete({
        source: function(request, response) {
            $.ajax({
                url: window.location.href, type: "POST", dataType: "json",
                data: { action: 'search_wo', term: request.term },
                success: function(data) { response(data); }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            $('#wo_number').val(ui.item.value).trigger('change');
            return false;
        }
    });

    $('#sms_no').autocomplete({
        source: function(request, response) {
            $.ajax({
                url: window.location.href, type: "POST", dataType: "json",
                data: { action: 'search_sms', term: request.term },
                success: function(data) { response(data); }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            $('#sms_no').val(ui.item.value);
            loadSmsData('search', ui.item.value);
            return false;
        }
    });

    // ==========================================
    // 2. TRIGGER EVENT HEADER
    // ==========================================
    $('#search_global, #sms_no').on('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            let val = $(this).val().trim();
            if(val !== '') loadSmsData('search', val);
        }
    });

    $('#wo_number').on('change', function() {
        let wo = $(this).val().trim();
        if(!wo) return;
        
        $.post(window.location.href, { action: 'get_wo_info', wo_number: wo }, function(res) {
            if (res.status === 'success') {
                $('#wo_id').val(res.data.WO_ID);
                $('#item_code').val(res.data.ITEM_CODE);
                $('#item_name').val(res.data.ITEM_NAME);
                $('#mac_code').val(res.data.MAC_CODE);
                
                $.post(window.location.href, { action: 'get_lots', wo_id: res.data.WO_ID }, function(lres) {
                    if (lres.status === 'success') listLots = lres.data;
                });
            }
        });
    });

    $('#btn_first').click(() => loadSmsData('first'));
    $('#btn_last').click(() => loadSmsData('last'));
    $('#btn_prev').click(() => loadSmsData('prev'));
    $('#btn_next').click(() => loadSmsData('next'));

    // ==========================================
    // 3. FUNGSI LOAD DATA & HAPUS
    // ==========================================
    function loadSmsData(nav, smsNo = '') {
        let currentId = $('#sms_id').val();
        
        $.post(window.location.href, { action: 'load_sms_data', nav: nav, sms_no: smsNo, current_id: currentId }, function(res) {
            if (res.status === 'success') {
                let h = res.header;
                $('#sms_id').val(h.SMS_ID);
                $('#sms_no').val(h.SMS_NO);
                $('#wo_id').val(h.WO_ID);
                $('#wo_number').val(h.WO_NUMBER);
                if (h.SMS_DATE) $('#sms_date').val(h.SMS_DATE);
                if (h.SMS_INPUT) $('#input_date').val(h.SMS_INPUT);
                $('#item_code').val(h.ITEM_CODE);
                $('#item_name').val(h.ITEM_NAME);
                $('#mac_code').val(h.MAC_CODE);

                $.post(window.location.href, { action: 'get_lots', wo_id: h.WO_ID }, function(lres) {
                    if (lres.status === 'success') listLots = lres.data;
                });

                $('#detail_body').empty();
                if (res.details.length > 0) {
                    res.details.forEach(d => addRowToGrid(d));
                } else {
                    addRowToGrid();
                }
            } else {
                alert('Data tidak ditemukan.');
                if (nav === 'search') resetForm();
            }
        });
    }

    $('#btn_delete').click(function() {
        let sms_id = $('#sms_id').val();
        let sms_no = $('#sms_no').val();
        
        if (sms_id === '0' || sms_id === '') {
            alert('Pilih / cari dokumen SMS yang ingin dihapus terlebih dahulu!');
            return;
        }
        
        if (confirm('APAKAH ANDA YAKIN INGIN MENGHAPUS DOKUMEN SMS ' + sms_no + ' INI BESERTA SELURUH DETAILNYA?')) {
            $('#status_text').val('Menghapus data...');
            $.post(window.location.href, { action: 'delete_sms', sms_id: sms_id }, function(res) {
                if (res && res.status === 'success') {
                    alert('Data SMS berhasil dihapus!');
                    $('#status_text').val('Data terhapus.');
                    resetForm();
                } else {
                    alert("Gagal menghapus data: \n" + (res.message ? res.message : "Kesalahan server"));
                    $('#status_text').val('Gagal hapus.');
                }
            }).fail(function() {
                alert("Koneksi ke server terputus.");
                $('#status_text').val('Error 500.');
            });
        }
    });

    function resetForm() {
        $('#sms_id').val('0');
        $('#wo_id').val('0');
        $('#wo_number, #item_code, #item_name, #mac_code').val('');
        $('#detail_body').empty();
        addRowToGrid();
    }

    window.resetForm = resetForm; 

    // ==========================================
    // 4. MANAJEMEN GRID & LOOKUP (AJAX MATERIAL SEARCH)
    // ==========================================
    // ==========================================
    // 4. MANAJEMEN GRID & LOOKUP (AJAX MATERIAL SEARCH)
    // ==========================================
    function addRowToGrid(data = {}) {
        let trCount = $('#detail_body tr').length + 1;
        let tr = `
            <tr class="dtl-row">
                <td class="row-indicator text-center" style="font-size:10px;"><span>▶</span>${trCount}</td>
                <td><input type="text" class="grid-input in-lot" value="${data.pd_lot || ''}"></td>
                <td><input type="text" class="grid-input in-matcode" value="${data.item_code || ''}"></td>
                <td><input type="text" class="grid-input in-matname" value="${data.item_name || ''}" readonly tabindex="-1"></td>
                <td><input type="number" class="grid-input in-qty" style="text-align: right;" step="any" value="${data.qty || ''}"></td>
                <td><input type="text" class="grid-input in-matlot" value="${data.matlot || ''}"></td>
                <td><input type="text" class="grid-input in-supplied" value="${data.supplied || ''}"></td>
                <td><input type="number" class="grid-input in-time" step="any" value="${data.time || ''}"></td>
                <td class="text-center"><button type="button" class="btn-delete-row" style="cursor:pointer; background:none; border:1px solid #aaa;" tabindex="-1">X</button></td>
            </tr>
        `;
        let $row = $(tr).appendTo('#detail_body');
        
        let $lotInput = $row.find('.in-lot');
        $lotInput.autocomplete({ 
            source: function(request, response) {
                let term = $.ui.autocomplete.escapeRegex(request.term);
                let matcher = new RegExp(term, "i");
                let matches = $.grep(listLots, function(item) { 
                    return matcher.test(item.lot); 
                });
                
                response($.map(matches, function(item) {
                    return { label: item.lot, value: item.lot };
                }));
            }, 
            minLength: 0,
            select: function(event, ui) {
                // Terapkan flag bahwa event ini dipicu dari autocomplete select
                $(this).data('is_selecting', true); 
                $(this).val(ui.item.value);
                
                let $currentRow = $(this).closest('tr');
                
                // Langsung simpan dan pindah cursor ke kolom Mat Code
                $('#status_text').val('Menyimpan Lot...');
                saveSmsAjax(function(success) {
                    $currentRow.find('.in-matcode').focus();
                });
                
                return false;
            }
        }).on('focus click', function() {
            if (listLots.length > 0) {
                $(this).autocomplete("search", "");
            } else {
                let woNo = $('#wo_number').val();
                if (!woNo) alert("Pilih WO NO di header terlebih dahulu!");
            }
        }).on('blur', function() {
            let val = $(this).val().trim();
            if (val !== "") {
                let isFound = listLots.find(l => l.lot === val);
                if (!isFound) {
                    alert("LOT NO '" + val + "' tidak terdaftar pada WO ini!");
                    $(this).val('');
                    $(this).closest('tr').find('.in-matcode, .in-matname').val('');
                }
            }
        });

        // OPTIMASI: Autocomplete MAT CODE dengan AJAX (Limit 10 data)
        $row.find('.in-matcode').autocomplete({ 
            source: function(request, response) {
                $.ajax({
                    url: window.location.href, 
                    type: "POST", 
                    dataType: "json",
                    data: { action: 'search_material', term: request.term },
                    success: function(data) { response(data); }
                });
            }, 
            minLength: 2, 
            select: function(event, ui) {
                // Terapkan flag agar tidak melompat baris jika ditekan Enter
                $(this).data('is_selecting', true);
                
                $(this).val(ui.item.value);
                $(this).closest('tr').find('.in-matname').val(ui.item.name);
                $(this).closest('tr').find('.in-qty').focus();
                return false;
            }
        });
    }

    $('#btn_add').click(function() { resetForm(); $('#sms_no').val('').focus(); });
    
    $(document).on('click', '.btn-delete-row', function() { 
        $(this).closest('tr').remove(); 
        saveSmsAjax(function(success) {}); 
    });

    // Handler jika diketik manual lalu blur/change
    $(document).on('change', '.in-matcode', function() { 
        let $input = $(this);
        let code = $input.val().trim();
        if (code !== '') {
            $.post(window.location.href, { action: 'search_material', term: code }, function(res) {
                if (res && res.length > 0) {
                    $input.closest('tr').find('.in-matname').val(res[0].name);
                } else {
                    $input.closest('tr').find('.in-matname').val('');
                }
            });
        } else {
            $input.closest('tr').find('.in-matname').val('');
        }
    });
    
    $(document).on('focus', '.grid-input', function() {
        $('#detail_body tr').removeClass('active-row');
        $(this).closest('tr').addClass('active-row');
    });

    // ==========================================
    // 5. AUTO-SAVE & AUTO-ADD ROW (ENTER KEY)
    // ==========================================
    $(document).on('keydown', '.grid-input', function(e) {
        if (e.key === 'Enter') {
            let $currentInput = $(this);
            
            // CEK FLAG: Jika input sedang dipilih lewat Autocomplete, Batal tambah baris baru
            if ($currentInput.data('is_selecting')) {
                // Hapus flag agar input berikutnya berjalan normal
                $currentInput.data('is_selecting', false);
                return; // Berhenti di sini, fungsi autocomplete select yang ambil alih pindah kolom
            }

            e.preventDefault();
            
            $currentInput.css({'background-color': '#fff9c4', 'color': 'black'}); 
            $('#status_text').val('Menyimpan...');

            saveSmsAjax(function(success) {
                if (success) {
                    $currentInput.css({'background-color': '#c8e6c9', 'color': 'black'});
                    setTimeout(() => $currentInput.css({'background-color': '', 'color': ''}), 800);
                    focusNextRow($currentInput);
                } else {
                    $currentInput.css({'background-color': '#ffcdd2', 'color': 'black'});
                }
            });
        }
    });
    
    function focusNextRow($inputEl) {
        let $tr = $inputEl.closest('tr.dtl-row');
        let $allRows = $('#detail_body tr.dtl-row');
        let currentIndex = $allRows.index($tr);

        if (currentIndex === $allRows.length - 1) {
            addRowToGrid();
            $('#detail_body tr.dtl-row').last().find('.in-lot').focus();
        } else {
            let inputClass = $inputEl.attr('class').split(' ').find(c => c.startsWith('in-'));
            let $nextRow = $allRows.eq(currentIndex + 1);
            if (inputClass) $nextRow.find('.' + inputClass).focus().select();
        }
    }

    function saveSmsAjax(callback) {
        let headerData = {
            sms_id: $('#sms_id').val(), sms_no: $('#sms_no').val(),
            wo_id: $('#wo_id').val(), sms_date: $('#sms_date').val()
        };

        if (headerData.sms_no.trim() === '') {
            if (callback) callback(false);
            return;
        }
        if (headerData.wo_id === '0' || headerData.wo_id === '') {
            $('#status_text').val('WO Belum Valid.');
            if (callback) callback(false);
            return;
        }

        let detailsData = [];
        $('#detail_body tr.dtl-row').each(function() {
            let matCode = $(this).find('.in-matcode').val();
            let lotVal = $(this).find('.in-lot').val();
            if (matCode && matCode.trim() !== '' && lotVal && lotVal.trim() !== '') {
                detailsData.push({
                    pd_lot: lotVal,
                    item_code: matCode,
                    qty: $(this).find('.in-qty').val(),
                    matlot: $(this).find('.in-matlot').val(),
                    supplied: $(this).find('.in-supplied').val(),
                    time: $(this).find('.in-time').val()
                });
            }
        });

        $.post(window.location.href, { action: 'save_sms', header: headerData, details: detailsData }, function(res) {
            if (res && res.status === 'success') {
                if (res.sms_id) $('#sms_id').val(res.sms_id);
                $('#status_text').val('Tersimpan di Server.');
                if (callback) callback(true);
            } else {
                alert("Kesalahan Server Database:\n" + (res.message ? res.message : "Data Response tidak valid"));
                $('#status_text').val('Gagal Simpan SQL.');
                if (callback) callback(false);
            }
        }).fail(function(xhr) { 
            alert("Koneksi ke Server terputus. Coba refresh halaman.");
            $('#status_text').val('Server Error.');
            if (callback) callback(false); 
        });
    }

    $('#btn_save').click(function() {
        $('#status_text').val('Menyimpan...');
        saveSmsAjax(function(success) {
            if(success) alert('Data berhasil disimpan ke Database!');
        });
    });

    resetForm();
});
</script>
</body>
</html>
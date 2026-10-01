<?php
// File: sms.php
error_reporting(0);
require_once __DIR__ . '/config/database.php';

$db = isset($conn) ? $conn : (isset($connection) ? $connection : (isset($dbconn) ? $dbconn : null));

function sendJSON($data) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

// =================================================================
// HANDLER AJAX ENDPOINTS
// =================================================================
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. AUTOCOMPLETE PENCARIAN DATA SMS LAMA (REAL-TIME BY KEYWORD)
    if ($action === 'search_sms_autocomplete') {
        $term = isset($_POST['term']) ? trim($_POST['term']) : '';
        if ($term === '') {
            sendJSON(array('status' => 'success', 'data' => array()));
        }

        $like = '%' . $term . '%';
        $sql = "SELECT TOP 25 
                    S.SMS_ID,
                    LTRIM(RTRIM(S.SMS_NO)) AS SMS_NO,
                    CONVERT(varchar(10), S.SMS_DATE, 120) AS SMS_DATE,
                    ISNULL(LTRIM(RTRIM(W.WO_NUMBER)), '') AS WO_NUMBER,
                    ISNULL(LTRIM(RTRIM(I.ITEM_CODE)), '') AS ITEM_CODE,
                    ISNULL(LTRIM(RTRIM(I.ITEM_NAME)), '') AS ITEM_NAME
                FROM dbo.SMS S
                LEFT JOIN dbo.WO W ON S.WO_ID = W.WO_ID
                LEFT JOIN dbo.ITEMS I ON W.ITEM_ID = I.ITEM_ID
                WHERE S.SMS_NO LIKE ? OR W.WO_NUMBER LIKE ? OR I.ITEM_CODE LIKE ? OR I.ITEM_NAME LIKE ?
                ORDER BY S.SMS_ID DESC";

        $stmt = sqlsrv_query($db, $sql, array($like, $like, $like, $like));
        $list = array();
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $list[] = array(
                    'sms_id'    => intval($row['SMS_ID']),
                    'sms_no'    => trim($row['SMS_NO']),
                    'sms_date'  => $row['SMS_DATE'],
                    'wo_number' => trim($row['WO_NUMBER']),
                    'item_code' => trim($row['ITEM_CODE']),
                    'item_name' => trim($row['ITEM_NAME'])
                );
            }
            sqlsrv_free_stmt($stmt);
        }
        sendJSON(array('status' => 'success', 'data' => $list));
    }

    // 2. AMBIL LIST RECENT SMS (DEFAULT LIST)
    if ($action === 'get_recent_sms') {
        $sql = "SELECT TOP 15 LTRIM(RTRIM(SMS_NO)) AS SMS_NO FROM dbo.SMS ORDER BY SMS_ID DESC";
        $stmt = sqlsrv_query($db, $sql);
        $sms_list = array();
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $sms_list[] = trim($row['SMS_NO']);
            }
            sqlsrv_free_stmt($stmt);
        }
        sendJSON(array('status' => 'success', 'data' => $sms_list));
    }

    // 3. AMBIL LIST WO (AUTOCOMPLETE)
    if ($action === 'get_all_wo') {
        $sql = "SELECT TOP 50 WO_ID, LTRIM(RTRIM(WO_NUMBER)) AS WO_NUMBER FROM dbo.WO ORDER BY WO_ID DESC";
        $stmt = sqlsrv_query($db, $sql);
        $wos = array();
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $wos[] = array('id' => $row['WO_ID'], 'number' => trim($row['WO_NUMBER']));
            }
            sqlsrv_free_stmt($stmt);
        }
        sendJSON(array('status' => 'success', 'data' => $wos));
    }

    // 4. AMBIL INFO DETAIL DARI NOMOR WO
    if ($action === 'get_wo_info') {
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

    // 5. AMBIL LIST LOT BERDASARKAN WO_ID ATAU NOMOR WO
    if ($action === 'get_lots') {
        $wo_id = isset($_POST['wo_id']) ? intval($_POST['wo_id']) : 0;
        $sql = "SELECT P.PD_ID, LTRIM(RTRIM(P.PD_LOT)) AS PD_LOT 
                FROM dbo.PRODUCTION P 
                WHERE P.WO_ID = ? OR P.WO_ID IN (SELECT WO_ID FROM dbo.WO WHERE WO_ID = ?)
                ORDER BY P.PD_ID ASC";
        $stmt = sqlsrv_query($db, $sql, array($wo_id, $wo_id));
        $lots = array();
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $lots[] = $row;
            }
            sqlsrv_free_stmt($stmt);
        }
        sendJSON(array('status' => 'success', 'data' => $lots));
    }

    // 6. AMBIL MASTER MATERIAL
    if ($action === 'get_materials') {
        $sql = "SELECT ITEM_ID, LTRIM(RTRIM(ITEM_CODE)) AS ITEM_CODE, LTRIM(RTRIM(ITEM_NAME)) AS ITEM_NAME FROM dbo.ITEMS ORDER BY ITEM_CODE ASC";
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
            sqlsrv_free_stmt($stmt);
        }
        sendJSON(array('status' => 'success', 'data' => $materials));
    }

    // 7. CARI & NAVIGASI DATA LAMA (BY SEARCH / NAVBAR)
    if ($action === 'load_sms_data') {
        $nav = isset($_POST['nav']) ? $_POST['nav'] : '';
        $sms_no = isset($_POST['sms_no']) ? trim($_POST['sms_no']) : '';
        $current_id = isset($_POST['current_id']) ? intval($_POST['current_id']) : 0;
        $params = array();

        if ($nav === 'search') {
            $sql = "SELECT TOP 1 S.SMS_ID, S.SMS_NO, S.WO_ID, S.SMS_DATE, S.SMS_INPUT, W.WO_NUMBER, M.MAC_CODE, I.ITEM_CODE, I.ITEM_NAME 
                    FROM dbo.SMS S 
                    LEFT JOIN dbo.WO W ON S.WO_ID = W.WO_ID 
                    LEFT JOIN dbo.MAC M ON W.MAC_ID = M.MAC_ID 
                    LEFT JOIN dbo.ITEMS I ON W.ITEM_ID = I.ITEM_ID 
                    WHERE LTRIM(RTRIM(S.SMS_NO)) = ?";
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
        if ($stmt && $hdr = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $hdr['SMS_ID'] = intval($hdr['SMS_ID']);
            $hdr['SMS_NO'] = trim(isset($hdr['SMS_NO']) ? $hdr['SMS_NO'] : '');
            $hdr['WO_NUMBER'] = trim(isset($hdr['WO_NUMBER']) ? $hdr['WO_NUMBER'] : '');
            $hdr['MAC_CODE'] = trim(isset($hdr['MAC_CODE']) ? $hdr['MAC_CODE'] : '');
            $hdr['ITEM_CODE'] = trim(isset($hdr['ITEM_CODE']) ? $hdr['ITEM_CODE'] : '');
            $hdr['ITEM_NAME'] = trim(isset($hdr['ITEM_NAME']) ? $hdr['ITEM_NAME'] : '');
            
            if ($hdr['SMS_DATE'] instanceof DateTime) { $hdr['SMS_DATE'] = $hdr['SMS_DATE']->format('Y-m-d'); }
            if ($hdr['SMS_INPUT'] instanceof DateTime) { $hdr['SMS_INPUT'] = $hdr['SMS_INPUT']->format('Y-m-d'); }

            // Query detail aman tanpa ORDER BY D.ID
            $sql_dtl = "SELECT 
                            D.PD_ID, 
                            ISNULL(LTRIM(RTRIM(P.PD_LOT)), '') AS PD_LOT, 
                            D.ITEM_ID, 
                            ISNULL(LTRIM(RTRIM(I.ITEM_CODE)), '') AS ITEM_CODE, 
                            ISNULL(LTRIM(RTRIM(I.ITEM_NAME)), '') AS ITEM_NAME, 
                            ISNULL(D.SMSD_QTY, 0) AS SMSD_QTY, 
                            ISNULL(LTRIM(RTRIM(D.SMSD_MATLOT)), '') AS SMSD_MATLOT, 
                            ISNULL(LTRIM(RTRIM(D.SMSD_SUPPLIED)), '') AS SMSD_SUPPLIED, 
                            ISNULL(D.SMSD_TIME, 0) AS SMSD_TIME
                        FROM dbo.SMS_DETAIL D
                        LEFT JOIN dbo.PRODUCTION P ON D.PD_ID = P.PD_ID
                        LEFT JOIN dbo.ITEMS I ON D.ITEM_ID = I.ITEM_ID
                        WHERE D.SMS_ID = ?";
            
            $stmt_dtl = sqlsrv_query($db, $sql_dtl, array($hdr['SMS_ID']));
            $details = array();
            if ($stmt_dtl) {
                while ($dtl = sqlsrv_fetch_array($stmt_dtl, SQLSRV_FETCH_ASSOC)) {
                    $details[] = array(
                        'pd_id'     => intval($dtl['PD_ID']),
                        'pd_lot'    => trim($dtl['PD_LOT']),
                        'item_id'   => intval($dtl['ITEM_ID']),
                        'item_code' => trim($dtl['ITEM_CODE']),
                        'item_name' => trim($dtl['ITEM_NAME']),
                        'qty'       => floatval($dtl['SMSD_QTY']),
                        'matlot'    => trim($dtl['SMSD_MATLOT']),
                        'supplied'  => trim($dtl['SMSD_SUPPLIED']),
                        'time'      => floatval($dtl['SMSD_TIME'])
                    );
                }
                sqlsrv_free_stmt($stmt_dtl);
            }
            sendJSON(array('status' => 'success', 'header' => $hdr, 'details' => $details));
        } else {
            sendJSON(array('status' => 'not_found'));
        }
    }

    // 8. SIMPAN DATA (INSERT / UPDATE)
    if ($action === 'save_sms') {
        $sms_id    = isset($_POST['sms_id']) ? intval($_POST['sms_id']) : 0;
        $sms_no    = isset($_POST['sms_no']) ? trim($_POST['sms_no']) : '';
        $wo_id     = isset($_POST['wo_id']) ? intval($_POST['wo_id']) : 0;
        $sms_date  = isset($_POST['sms_date']) ? $_POST['sms_date'] : date('Y-m-d');
        $sms_input = isset($_POST['sms_input']) ? $_POST['sms_input'] : date('Y-m-d');
        $details   = isset($_POST['details']) && is_array($_POST['details']) ? $_POST['details'] : array();
        
        sqlsrv_begin_transaction($db);
        try {
            if ($sms_id == 0) {
                $sql_hdr = "INSERT INTO dbo.SMS (SMS_NO, WO_ID, SMS_DATE, SMS_INPUT) VALUES (?, ?, ?, ?)";
                $stmt_hdr = sqlsrv_query($db, $sql_hdr, array($sms_no, $wo_id, $sms_date, $sms_input));
                if (!$stmt_hdr) { throw new Exception("Gagal Insert Header SMS."); }
                
                $stmt_id = sqlsrv_query($db, "SELECT @@IDENTITY AS NEW_ID");
                if ($stmt_id && sqlsrv_fetch($stmt_id)) {
                    $sms_id = sqlsrv_get_field($stmt_id, 0);
                } else {
                    throw new Exception("Gagal memperoleh ID SMS Baru.");
                }
            } else {
                $sql_hdr = "UPDATE dbo.SMS SET SMS_NO=?, WO_ID=?, SMS_DATE=?, SMS_INPUT=? WHERE SMS_ID=?";
                $stmt_hdr = sqlsrv_query($db, $sql_hdr, array($sms_no, $wo_id, $sms_date, $sms_input, $sms_id));
                if (!$stmt_hdr) { throw new Exception("Gagal Update Header SMS."); }
                sqlsrv_query($db, "DELETE FROM dbo.SMS_DETAIL WHERE SMS_ID=?", array($sms_id));
            }

            foreach ($details as $d) {
                $pd_id = isset($d['pd_id']) ? intval($d['pd_id']) : 0;
                $item_id = isset($d['item_id']) ? intval($d['item_id']) : 0;
                $qty = isset($d['qty']) ? floatval($d['qty']) : 0;
                $matlot = isset($d['matlot']) ? trim($d['matlot']) : '';
                $supplied = isset($d['supplied']) ? trim($d['supplied']) : '';
                $time = isset($d['time']) ? floatval($d['time']) : 0;
                
                if ($pd_id > 0 && $item_id > 0) {
                    $sql_dtl = "INSERT INTO dbo.SMS_DETAIL (SMS_ID, PD_ID, ITEM_ID, SMSD_MATLOT, SMSD_TIME, SMSD_SUPPLIED, SMSD_TIME_REM, SMSD_QTY) 
                                VALUES (?, ?, ?, ?, ?, ?, 0, ?)";
                    $stmt_dtl = sqlsrv_query($db, $sql_dtl, array($sms_id, $pd_id, $item_id, $matlot, $time, $supplied, $qty));
                    if (!$stmt_dtl) { throw new Exception("Gagal Insert Detail SMS."); }
                }
            }
            sqlsrv_commit($db);
            sendJSON(array('status' => 'success', 'sms_id' => $sms_id));
        } catch (Exception $e) {
            sqlsrv_rollback($db);
            sendJSON(array('status' => 'error', 'message' => $e->getMessage(), 'errors' => sqlsrv_errors()));
        }
    }
}
?>

<div class="box box-primary">
    <!-- HEADER PANEL: TITLE DILENGKAPI KOTAK PENCARIAN AUTOCOMPLETE SMS LAMA -->
    <div class="box-header with-border" style="padding: 8px 12px;">
        <h3 class="box-title" style="vertical-align: middle; font-weight: bold; margin-right: 15px;">
            <i class="fa fa-paperclip text-primary"></i> Supply Material Slip (SMS)
        </h3>

        <!-- PENCARIAN DATA SMS LAMA -->
        <div style="display: inline-block; vertical-align: middle; width: 340px;">
            <div class="input-group input-group-sm">
                <input type="text" id="search_old_sms" class="form-control" list="search_sms_datalist" placeholder="🔍 Cari No. SMS Lama / WO / Part..." autocomplete="off">
                <span class="input-group-btn">
                    <button class="btn btn-primary btn-flat font-weight-bold" type="button" id="btn_search_sms">
                        <i class="fa fa-search"></i> Cari
                    </button>
                </span>
            </div>
            <datalist id="search_sms_datalist"></datalist>
        </div>
    </div>

    <div class="box-body" style="padding-top: 10px;">
        <!-- INFORMASI HEADER FORM -->
        <div class="well well-sm" style="background:#eaf2f8; border-color:#bce8f1; margin-bottom: 12px; padding: 10px;">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group" style="margin-bottom: 6px;">
                        <label style="margin-bottom: 2px;">SMS NO:</label>
                        <input type="text" id="sms_no" class="form-control input-sm font-weight-bold" list="sms-datalist" placeholder="Ketik/Pilih SMS..." autocomplete="off">
                        <input type="hidden" id="sms_id" value="0">
                        <datalist id="sms-datalist"></datalist>
                    </div>
                    <div class="form-group" style="margin-bottom: 2px;">
                        <label style="margin-bottom: 2px;">WO NO:</label>
                        <input type="text" id="wo_number" class="form-control input-sm font-weight-bold" list="wo-datalist" placeholder="Ketik/Pilih WO..." autocomplete="off">
                        <input type="hidden" id="wo_id" value="0">
                        <datalist id="wo-datalist"></datalist>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group" style="margin-bottom: 6px;">
                        <label style="margin-bottom: 2px;">SMS DATE:</label>
                        <input type="date" id="sms_date" class="form-control input-sm" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 2px;">
                        <label style="margin-bottom: 2px;">INPUT DATE:</label>
                        <input type="date" id="sms_input" class="form-control input-sm" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group" style="margin-bottom: 6px;">
                        <label style="margin-bottom: 2px;">ITEM CODE:</label>
                        <input type="text" id="item_code" class="form-control input-sm font-weight-bold" readonly style="background:#fff; color:#0056b3;">
                    </div>
                    <div class="form-group" style="margin-bottom: 2px;">
                        <label style="margin-bottom: 2px;">ITEM NAME:</label>
                        <input type="text" id="item_name" class="form-control input-sm" readonly style="background:#fff;">
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group" style="margin-bottom: 2px;">
                        <label style="margin-bottom: 2px;">M/C:</label>
                        <input type="text" id="mac_code" class="form-control input-sm font-weight-bold text-center" readonly style="background:#fff; font-size:14px;">
                    </div>
                </div>
            </div>
        </div>

        <!-- TOOLBAR NAVIGASI & AKSI -->
        <div class="row" style="margin-bottom: 10px;">
            <div class="col-md-6">
                <div class="btn-group">
                    <button class="btn btn-default btn-sm" id="btn_first" title="First Record"><i class="fa fa-fast-backward"></i></button>
                    <button class="btn btn-default btn-sm" id="btn_fastrev" title="Fast Reverse"><i class="fa fa-backward"></i></button>
                    <button class="btn btn-default btn-sm" id="btn_prev" title="Previous"><i class="fa fa-caret-left"></i></button>
                    <button class="btn btn-default btn-sm" id="btn_next" title="Next"><i class="fa fa-caret-right"></i></button>
                    <button class="btn btn-default btn-sm" id="btn_fastfwd" title="Fast Forward"><i class="fa fa-forward"></i></button>
                    <button class="btn btn-default btn-sm" id="btn_last" title="Last Record"><i class="fa fa-fast-forward"></i></button>
                </div>
            </div>
            <div class="col-md-6 text-right">
                <div class="btn-group">
                    <button class="btn btn-primary btn-sm font-weight-bold" id="btn_add"><i class="fa fa-plus"></i> Slip Baru</button>
                    <button class="btn btn-info btn-sm font-weight-bold" id="btn_add_row"><i class="fa fa-arrow-down"></i> Tambah Material</button>
                    <button class="btn btn-success btn-sm font-weight-bold" id="btn_save"><i class="fa fa-save"></i> SIMPAN</button>
                    <!-- TOMBOL BATAL BARU DITAMBAHKAN -->
                    <button class="btn btn-default btn-sm font-weight-bold" id="btn_cancel" title="Batal Input/Reset Form"><i class="fa fa-times-circle text-danger"></i> BATAL</button>
                    <button class="btn btn-danger btn-sm font-weight-bold" id="btn_delete_row"><i class="fa fa-trash"></i> Hapus Baris</button>
                </div>
            </div>
        </div>

        <!-- GRID DETAIL MATERIAL -->
        <div class="table-responsive" style="height: 380px; overflow-y: auto; border: 1px solid #ddd;">
            <table class="table table-bordered table-condensed" id="tbl_detail" style="font-size: 12px; margin-bottom: 0;">
                <thead>
                    <tr style="background:#eaeaea; position: sticky; top:0; z-index:2;">
                        <th style="width: 25px; text-align: center;">#</th>
                        <th style="width: 150px;">LOT. NO</th>
                        <th style="width: 140px;">MAT CODE</th>
                        <th>MAT NAME</th>
                        <th style="width: 85px;" class="text-right">Qty</th>
                        <th style="width: 110px;">MAT. LOT</th>
                        <th style="width: 120px;">SUPPLIED</th>
                        <th style="width: 85px;" class="text-right">Time(s)</th>
                    </tr>
                </thead>
                <tbody id="detail_body">
                    <tr><td colspan="8" class="text-center text-muted" style="padding:20px;">Pilih atau cari Slip SMS untuk memuat detail material...</td></tr>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 8px; font-size: 11px; color:#555;">
            <b>Status:</b> <span id="status_text" class="text-primary font-weight-bold">Ready</span>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var materialsData = [];
    var lotsData = []; 
    var delayTimer;

    // Load master material
    $.post('sms.php', { action: 'get_materials' }, function(res) {
        if(res.status === 'success') {
            materialsData = res.data;
            var materialDatalist = '<datalist id="mat-datalist">';
            for(var i=0; i<materialsData.length; i++) { 
                materialDatalist += '<option value="' + materialsData[i].code + '">'; 
            }
            materialDatalist += '</datalist>';
            $('body').append(materialDatalist);
        }
    }, 'json');

    // Load recent SMS
    $.post('sms.php', { action: 'get_recent_sms' }, function(res) {
        if(res.status === 'success') {
            var options = '';
            for(var i=0; i<res.data.length; i++) { 
                options += '<option value="' + res.data[i] + '">'; 
            }
            $('#sms-datalist').html(options);
        }
    }, 'json');

    // Load list WO
    $.post('sms.php', { action: 'get_all_wo' }, function(res) {
        if(res.status === 'success') {
            var options = '';
            for(var i=0; i<res.data.length; i++) { 
                options += '<option value="' + res.data[i].number + '">'; 
            }
            $('#wo-datalist').html(options);
        }
    }, 'json');

    // ==============================================================
    // LOGIKA AUTOCOMPLETE PENCARIAN SMS LAMA
    // ==============================================================
    $('#search_old_sms').on('input keyup', function(e) {
        var term = $(this).val().trim();
        
        if (e.which === 13) {
            e.preventDefault();
            triggerSearchSms();
            return;
        }

        clearTimeout(delayTimer);
        if (term.length >= 1) {
            delayTimer = setTimeout(function() {
                $.post('sms.php', { action: 'search_sms_autocomplete', term: term }, function(res) {
                    if (res && res.status === 'success') {
                        var dlist = '';
                        for (var i = 0; i < res.data.length; i++) {
                            var row = res.data[i];
                            var labelInfo = 'Tgl: ' + row.sms_date + ' | WO: ' + row.wo_number + ' (' + row.item_code + ')';
                            dlist += '<option value="' + row.sms_no + '">' + labelInfo + '</option>';
                        }
                        $('#search_sms_datalist').html(dlist);
                    }
                }, 'json');
            }, 250);
        }
    });

    $('#search_old_sms').on('change', function() {
        triggerSearchSms();
    });

    $('#btn_search_sms').on('click', function() {
        triggerSearchSms();
    });

    function triggerSearchSms() {
        var val = $('#search_old_sms').val().trim();
        if (val) {
            fetchRecord('search', val);
        }
    }

    $('#wo_number').on('change', function() {
        var woNum = $(this).val().trim();
        if(!woNum) { clearHeader(); return; }
        
        setStatus("Mengambil data WO...");
        $.post('sms.php', { action: 'get_wo_info', wo_number: woNum }, function(res) {
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
        }, 'json');
    });

    function clearHeader() {
        $('#wo_id').val(0);
        $('#item_code, #item_name, #mac_code').val('');
        lotsData = []; 
        updateAllLotDropdowns();
        setStatus("Ready");
    }

    function loadLotsForWO(woid, callback) {
        $.post('sms.php', { action: 'get_lots', wo_id: woid }, function(res) {
            if(res && res.status === 'success') {
                lotsData = res.data; 
                updateAllLotDropdowns(); 
            }
            if(callback) callback();
        }, 'json').fail(function() {
            if(callback) callback();
        });
    }

    // ==============================================================
    // MEMUAT DATA LAMA & MENAMPILKAN DETAIL LANGSUNG
    // ==============================================================
    function fetchRecord(actionType, paramValue) {
        setStatus("Memuat data...");
        $.ajax({
            url: 'sms.php',
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
                    // 1. Tampilkan Header Data
                    $('#sms_id').val(res.header.SMS_ID);
                    $('#sms_no').val(res.header.SMS_NO);
                    $('#search_old_sms').val(res.header.SMS_NO);
                    $('#wo_id').val(res.header.WO_ID);
                    $('#wo_number').val(res.header.WO_NUMBER);
                    $('#sms_date').val(res.header.SMS_DATE);
                    $('#sms_input').val(res.header.SMS_INPUT);
                    $('#item_code').val(res.header.ITEM_CODE);
                    $('#item_name').val(res.header.ITEM_NAME);
                    $('#mac_code').val(res.header.MAC_CODE);

                    // 2. Tampilkan Detail Material LANGSUNG TANPA TERTUNDA
                    $('#detail_body').empty();
                    if (res.details && res.details.length > 0) {
                        for(var i=0; i<res.details.length; i++) { 
                            appendDetailRowObj(res.details[i]); 
                        }
                    } else {
                        $('#detail_body').html('<tr><td colspan="8" class="text-center text-muted" style="padding:15px;">Belum ada baris material pada slip ini.</td></tr>');
                    }
                    setStatus("Data SMS " + res.header.SMS_NO + " berhasil dimuat.");

                    // 3. Ambil lot secara paralel di background untuk dropdown
                    loadLotsForWO(res.header.WO_ID);

                } else if (res.status === 'not_found') {
                    if (actionType === 'search') alert("Nomor SMS '" + paramValue + "' tidak ditemukan.");
                    setStatus("Ready");
                }
            },
            error: function(xhr, status, error) {
                alert("Gagal memuat data dari server: " + error);
                setStatus("Error server.");
            }
        });
    }

    // Enter pada input SMS NO
    $('#sms_no').on('keypress', function(e) {
        if(e.which == 13) {
            e.preventDefault();
            var val = $(this).val().trim();
            if(val) fetchRecord('search', val);
        }
    });

    // Tombol Navigasi Bawah
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

    // ==============================================================
    // LOGIKA TOMBOL BATAL (RESET / CANCEL EDIT)
    // ==============================================================
    $('#btn_cancel').on('click', function() {
        var cid = $('#sms_id').val();
        var curSmsNo = $('#sms_no').val().trim();

        // Jika sedang menampilkan data lama yang sudah tersimpan, muat ulang dari database
        if (cid > 0 && curSmsNo !== '') {
            fetchRecord('search', curSmsNo);
            setStatus("Perubahan dibatalkan, data dimuat ulang.");
        } else {
            // Jika sedang input baru (belum disimpan), bersihkan semua form
            $('#sms_id').val(0);
            $('#sms_no').val('');
            $('#search_old_sms').val('');
            $('#wo_number').val('');
            clearHeader();
            $('#detail_body').html('<tr><td colspan="8" class="text-center text-muted" style="padding:20px;">Pilih atau cari Slip SMS untuk memuat detail material...</td></tr>');
            setStatus("Input dibatalkan.");
        }
    });

    // ==============================================================
    // RENDER DETAIL BARIS MATERIAL
    // ==============================================================
    function generateLotOptions(selectedId, selectedLotName) {
        if (selectedId === undefined || selectedId === null) { selectedId = ''; }
        var opt = '<option value="">- Pilih Lot -</option>';
        var found = false;

        if (lotsData && lotsData.length > 0) {
            for(var i=0; i<lotsData.length; i++) {
                var l = lotsData[i];
                var sel = (l.PD_ID == selectedId) ? 'selected' : '';
                if (l.PD_ID == selectedId) found = true;
                opt += '<option value="' + l.PD_ID + '" ' + sel + '>' + l.PD_LOT + '</option>';
            }
        }

        // Tetap pasang lot lama agar langsung terpilih
        if (selectedId && !found) {
            var label = selectedLotName ? selectedLotName : ('Lot #' + selectedId);
            opt += '<option value="' + selectedId + '" selected>' + label + '</option>';
        }
        return opt;
    }

    function updateAllLotDropdowns() {
        $('.in-lot').each(function() {
            var currentVal = $(this).val();
            var currentText = $(this).find('option:selected').text();
            $(this).html(generateLotOptions(currentVal, currentText));
        });
    }

    function appendDetailRowObj(d) {
        var tr = '<tr class="dtl-row">' +
                    '<td class="text-center text-primary font-weight-bold row-indicator"></td>' +
                    '<td><select class="form-control input-sm in-lot" style="height:26px; padding:2px;">' + generateLotOptions(d.pd_id, d.pd_lot) + '</select></td>' +
                    '<td>' +
                        '<input type="text" class="form-control input-sm in-matcode font-weight-bold" list="mat-datalist" value="' + (d.item_code || '') + '" placeholder="Ketik Kode...">' +
                        '<input type="hidden" class="in-itemid" value="' + (d.item_id || 0) + '">' +
                    '</td>' +
                    '<td><input type="text" class="form-control input-sm in-matname" value="' + (d.item_name || '') + '" readonly style="background:#eee;"></td>' +
                    '<td><input type="number" step="any" class="form-control input-sm text-right in-qty" value="' + (d.qty || 0) + '"></td>' +
                    '<td><input type="text" class="form-control input-sm in-matlot" value="' + (d.matlot || '') + '"></td>' +
                    '<td><input type="text" class="form-control input-sm in-supplied" value="' + (d.supplied || '') + '"></td>' +
                    '<td><input type="number" step="any" class="form-control input-sm text-right in-time" value="' + (d.time || 0) + '"></td>' +
                '</tr>';
        $('#detail_body').append(tr);
    }

    $('#btn_add_row').on('click', function() {
        var woid = $('#wo_id').val();
        if(!woid || woid == 0) { alert('Pilih WO NO yang valid terlebih dahulu!'); return; }
        
        if ($('#detail_body tr td').length === 1) {
            $('#detail_body').empty();
        }

        appendDetailRowObj({pd_id:'', pd_lot:'', item_id:0, item_code:'', item_name:'', qty:0, matlot:'', supplied:'', time:0});
        setStatus("Baris material ditambahkan.");
    });

    $(document).on('change input', '.in-matcode', function() {
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

    $(document).on('focus', '.in-lot, .in-matcode, .in-qty, .in-matlot, .in-supplied, .in-time', function() {
        $('.row-indicator').text('');
        $(this).closest('tr').find('.row-indicator').text('▶');
        $('.dtl-row').css('background-color', '');
        $(this).closest('tr').css('background-color', '#eaf2f8');
    });

    $('#btn_delete_row').on('click', function() {
        var $activeRow = $('.row-indicator:contains("▶")').closest('tr');
        if($activeRow.length > 0) {
            $activeRow.remove();
            setStatus("Baris dihapus.");
        } else {
            alert("Klik baris grid yang ingin dihapus terlebih dahulu.");
        }
    });

    $('#btn_add').on('click', function() {
        $('#sms_id').val(0);
        $('#sms_no').val('');
        $('#search_old_sms').val('');
        $('#wo_number').val('');
        clearHeader();
        $('#detail_body').html('<tr><td colspan="8" class="text-center text-muted" style="padding:15px;">Klik tombol "+ Tambah Material" untuk menambahkan item.</td></tr>');
        $('#sms_no').focus();
        setStatus("Input SMS baru.");
    });

    $('#btn_save').on('click', function() {
        var sms_id = $('#sms_id').val();
        var sms_no = $('#sms_no').val().trim();
        var wo_id = $('#wo_id').val();
        var sms_date = $('#sms_date').val();
        var sms_input = $('#sms_input').val();

        if(!sms_no || wo_id == 0) { alert("SMS NO dan WO NO wajib diisi!"); return; }

        var details = [];
        var valid = true;

        $('.dtl-row').each(function() {
            var pd_id = $(this).find('.in-lot').val();
            var item_id = $(this).find('.in-itemid').val();
            if(!pd_id || item_id == 0) {
                valid = false;
                $(this).css('background-color', '#f2dede');
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

        if(!valid) { alert("Pastikan LOT dan Material di setiap baris telah dipilih!"); return; }
        if(details.length === 0) { alert("Baris material masih kosong!"); return; }

        setStatus("Menyimpan...");
        $.ajax({
            url: 'sms.php',
            type: 'POST',
            data: {
                action: 'save_sms', sms_id: sms_id, sms_no: sms_no, wo_id: wo_id, 
                sms_date: sms_date, sms_input: sms_input, details: details
            },
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    $('#sms_id').val(res.sms_id); 
                    alert("Slip SMS berhasil disimpan!");
                    setStatus("Saved.");
                    $('.dtl-row').css('background-color', '');
                } else {
                    alert("Gagal menyimpan: " + res.message);
                    setStatus("Error saat menyimpan.");
                }
            }
        });
    });
    
    function setStatus(msg) { $('#status_text').text(msg); }
});
</script>
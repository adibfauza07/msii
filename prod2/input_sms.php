<?php
// File: input_sms.php
error_reporting(0); // Mencegah PHP Warning merusak respons JSON
ob_start();         // Memulai output buffering

require_once __DIR__ . "/../config/global.php";

$db = isset($conn) ? $conn : (isset($connection) ? $connection : (isset($dbconn) ? $dbconn : null));

// Fungsi pembantu agar output selalu murni JSON
function sendJSON($data) {
    ob_clean(); 
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
    // ... [Include your existing POST validation and Transaction block here untouched]
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supply Material Slip</title>
    <!-- AdminLTE & Bootstrap CSS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    
    <style>
        body { background-color: #f4f6f9; font-size: 13px; }
        .grid-container { max-height: 400px; overflow-y: auto; }
        .data-grid th { position: sticky; top: 0; background: #f4f6f9; z-index: 1; box-shadow: inset 0 -1px 0 #dee2e6; }
        .grid-input { width: 100%; height: 28px; box-sizing: border-box; border: none; padding: 4px 8px; outline: none; background: transparent; }
        .grid-input:focus { background-color: #fff; box-shadow: inset 0 0 0 2px #007bff; }
        .row-indicator { width: 25px; text-align: center; vertical-align: middle; }
    </style>
</head>
<body class="hold-transition layout-top-nav">
<div class="wrapper">
    <div class="content-wrapper">
        <section class="content pt-3">
            <div class="container-fluid">
                <div class="card card-primary card-outline shadow-sm mx-auto" style="max-width: 1000px;">
                    
                    <!-- Toolbar & Controls -->
                    <div class="card-header p-2 bg-light d-flex justify-content-between align-items-center">
                        <div class="btn-group">
                            <button class="btn btn-sm btn-default" id="btn_first" title="First Record"><i class="fas fa-fast-backward"></i></button>
                            <button class="btn btn-sm btn-default" id="btn_fastrev" title="Fast Reverse"><i class="fas fa-backward"></i></button>
                            <button class="btn btn-sm btn-default" id="btn_prev" title="Previous Record"><i class="fas fa-step-backward"></i></button>
                            <button class="btn btn-sm btn-default" id="btn_next" title="Next Record"><i class="fas fa-step-forward"></i></button>
                            <button class="btn btn-sm btn-default" id="btn_fastfwd" title="Fast Forward"><i class="fas fa-forward"></i></button>
                            <button class="btn btn-sm btn-default" id="btn_last" title="Last Record"><i class="fas fa-fast-forward"></i></button>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-info" id="btn_add"><i class="fas fa-file mr-1"></i> Baru</button>
                            <button class="btn btn-sm btn-success" id="btn_add_row"><i class="fas fa-plus mr-1"></i> Tambah Baris</button>
                            <button class="btn btn-sm btn-danger" id="btn_delete_row"><i class="fas fa-times mr-1"></i> Hapus Baris</button>
                            <button class="btn btn-sm btn-primary ml-2" id="btn_save"><i class="fas fa-save mr-1"></i> Simpan</button>
                        </div>
                    </div>

                    <!-- Header Panel -->
                    <div class="card-body bg-white border-bottom p-3">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row mb-2">
                                    <label class="col-sm-3 col-form-label text-sm">SMS NO</label>
                                    <div class="col-sm-6">
                                        <input type="text" id="sms_no" class="form-control form-control-sm" list="sms-datalist" placeholder="Ketik/Pilih & Enter" autocomplete="off">
                                        <input type="hidden" id="sms_id" value="0">
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-sm-3 col-form-label text-sm">WO NO</label>
                                    <div class="col-sm-6">
                                        <input type="text" id="wo_number" class="form-control form-control-sm" list="wo-datalist" placeholder="Pilih WO..." autocomplete="off">
                                        <input type="hidden" id="wo_id" value="0">
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-sm-3 col-form-label text-sm">SMS DATE</label>
                                    <div class="col-sm-6">
                                        <input type="date" id="sms_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row mb-2">
                                    <label class="col-sm-3 col-form-label text-sm">ITEM CODE</label>
                                    <div class="col-sm-9">
                                        <input type="text" id="item_code" class="form-control form-control-sm bg-light" readonly>
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-sm-3 col-form-label text-sm">ITEM NAME</label>
                                    <div class="col-sm-9">
                                        <input type="text" id="item_name" class="form-control form-control-sm bg-light" readonly>
                                    </div>
                                </div>
                                <div class="form-group row mb-0">
                                    <label class="col-sm-3 col-form-label text-sm">M/C</label>
                                    <div class="col-sm-4">
                                        <input type="text" id="mac_code" class="form-control form-control-sm bg-light" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Data Grid -->
                    <div class="card-body p-0 grid-container">
                        <table class="table table-sm table-bordered data-grid m-0" id="tbl_detail">
                            <thead>
                                <tr>
                                    <th style="width: 30px;"></th>
                                    <th style="width: 140px;">LOT.NO</th>
                                    <th style="width: 120px;">MAT CODE</th>
                                    <th>MAT NAME</th>
                                    <th style="width: 80px;" class="text-right">Qty</th>
                                    <th style="width: 120px;">MAT.LOT</th>
                                    <th style="width: 120px;">SUPPLIED</th>
                                    <th style="width: 80px;" class="text-right">Time(s)</th>
                                </tr>
                            </thead>
                            <tbody id="detail_body">
                                <!-- Ajax appended here -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="card-footer p-2 bg-light text-muted text-sm">
                        <i class="fas fa-info-circle mr-1"></i> Status: <strong id="status_text">Ready</strong>
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
// Include the rest of the javascript logic matching your original SMS script
// ... [Original JS logic follows seamlessly here]

$(document).on('focus', '.grid-input', function() {
    $('.row-indicator').html('');
    $(this).closest('tr').find('.row-indicator').html('<i class="fas fa-caret-right text-primary"></i>');
    $('.dtl-row').removeClass('bg-lightblue');
    $(this).closest('tr').addClass('bg-lightblue');
});
</script>
</body>
</html>
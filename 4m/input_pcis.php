<?php
// FILE: msii/4m/input_pcis.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['erp_user'])) { $_SESSION['erp_user'] = 'Guest'; }

require_once __DIR__ . '/../config/database.php';
require_once 'pcis_functions.php';

// =========================================================================
// AJAX HANDLER UNTUK AUTOCOMPLETE CUST, PART, DAN MATERIAL
// =========================================================================
if (isset($_GET['ajax_search'])) {
    while (ob_get_level()) { ob_end_clean(); } 
    header('Content-Type: application/json');
    $type = $_GET['ajax_search'];
    $term = isset($_GET['term']) ? trim($_GET['term']) : '';
    $res = [];
    
    if ($type == 'cust') {
        $sql = "SELECT TOP 50 CUST_ID, CUST_COMP FROM CUST WHERE CUST_COMP LIKE ?";
        $stmt = sqlsrv_query($conn, $sql, ["%$term%"]);
        if($stmt) while($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $res[] = ['label' => $r['CUST_COMP'], 'value' => $r['CUST_ID']];
    } 
    elseif ($type == 'part') {
        $cust_id = isset($_GET['cust_id']) ? $_GET['cust_id'] : '';
        if (!empty($cust_id)) {
            $sql = "SELECT * FROM (
                        SELECT ITEM_ID, CUST_ID, PART_CODE, PART_NAME 
                        FROM ITEM_CUSTINFO_VIEW
                    ) AS CustItems 
                    WHERE CUST_ID = ? AND (PART_NAME LIKE ? OR PART_CODE LIKE ?)";
            $stmt = sqlsrv_query($conn, $sql, [$cust_id, "%$term%", "%$term%"]);
            if ($stmt === false) {
                $err = sqlsrv_errors();
                $res[] = ['label' => "Error SQL: " . $err[0]['message'], 'value' => ''];
            } else {
                while($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $res[] = ['label' => trim($r['PART_CODE']) . " - " . trim($r['PART_NAME']), 'value' => $r['ITEM_ID'], 'model' => ''];
                }
            }
        }
    }
    elseif ($type == 'mat') {
        $sql = "SELECT TOP 50 ITEM_ID, ITEM_CODE, ITEM_NAME FROM ITEMS WHERE ITEM_NAME LIKE ? OR ITEM_CODE LIKE ?";
        $stmt = sqlsrv_query($conn, $sql, ["%$term%", "%$term%"]);
        if($stmt) while($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $res[] = ['label' => $r['ITEM_CODE'] . " - " . $r['ITEM_NAME'], 'value' => $r['ITEM_ID']];
    }
    echo json_encode($res);
    exit;
}

// =========================================================================
// PROSES SIMPAN / UPDATE DATABASE (SINKRONISASI TOTAL PARAMETER)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btnSimpan'])) {
    function val($k, $d = "") { return isset($_POST[$k]) ? $_POST[$k] : $d; }

    $control_id   = intval(val('control_id', 0));
    $control_no   = trim(val('control_no'));
    $control_date = trim(val('control_date'));
    $dep_code     = trim(val('dep_code'));
    $pic_name     = trim(val('pic_name'));
    $item_id      = intval(val('item_id', 0));
    $model        = trim(val('model'));
    $material_id  = intval(val('material_id', 0)); 
    
    $to_pcis      = trim(val('to_pcis'));
    $cc_pcis      = trim(val('cc_pcis'));
    
    $reason       = trim(val('reason'));
    $bef_change   = trim(val('bef_change'));
    $aft_change   = trim(val('aft_change'));
    $status       = trim(val('status'));
    
    $prepared     = trim(val('prepared'));
    $imc_checked  = trim(val('imc_checked'));
    $imc_aprove   = trim(val('imc_aprove'));
    
    $pe_remark   = trim(val('pe_remark'));
    $qc_remark   = trim(val('qc_remark'));
    $mold_remark = trim(val('mold_remark'));
    $ppic_remark = trim(val('ppic_remark'));
    $prod_remark = trim(val('prod_remark'));
    $mkt_remark  = trim(val('mkt_remark'));

    $man         = isset($_POST['man']) ? 1 : 0;
    $machine     = isset($_POST['machine']) ? 1 : 0;
    $method      = isset($_POST['method']) ? 1 : 0;
    $material_4m = isset($_POST['material_4m']) ? 1 : 0; 
    $other       = isset($_POST['other']) ? 1 : 0;
    
    $internal    = isset($_POST['internal']) ? 1 : 0;
    $customer    = isset($_POST['customer']) ? 1 : 0;
    $supplier    = isset($_POST['supplier']) ? 1 : 0;
    $perm        = isset($_POST['perm']) ? intval($_POST['perm']) : 1;

    // Capture Data Baru Hasil Sinkronisasi Fitur Kotak Merah Laporan
    $need_cust   = isset($_POST['need_customer']) ? intval($_POST['need_customer']) : 1;
    $f_email     = isset($_POST['attach_email']) ? 1 : 0;
    $f_drawing   = isset($_POST['attach_drawing']) ? 1 : 0;
    $f_sample    = isset($_POST['attach_sample']) ? 1 : 0;
    $f_data      = isset($_POST['attach_data']) ? 1 : 0;

    $sch_change   = !empty($_POST['sch_change']) ? $_POST['sch_change'] : null;
    $start_change = !empty($_POST['start_change']) ? $_POST['start_change'] : null;
    $close_change = !empty($_POST['close_change']) ? $_POST['close_change'] : null;

    if ($control_id > 0) {
        // MODE UPDATE (Total 39 Parameter Field SET)
        $sql = "UPDATE PROSES_CHANGE SET 
                    CONTROL_NO = ?, CONTROL_DATE1 = ?, DEP_CODE = ?, PIC_NAME = ?, 
                    ITEM_ID = ?, MODEL = ?, MATERIAL_ID = ?, TO_PCIS = ?, CC = ?, 
                    MAN = ?, MACHINE = ?, METHOD = ?, MATERIAL = ?, OTHER = ?, INTERNAL = ?, CUSTOMER = ?, SUPPLIER = ?, 
                    PERMANENT_CHANGE = ?, REASON = ?, BEF_CHANGE = ?, AFT_CHANGE = ?, STATUS = ?, 
                    IMC_PREPARED = ?, IMC_CHECKED = ?, IMC_APROVE = ?,
                    PE_REMARK = ?, QC_REMARK = ?, MOLDSHOP_REMARK = ?, PPIC_REMARK = ?, PRODUCTION_REMARK = ?, MARKETING_REMARK = ?,
                    NEED_CUSTOMER = ?, EMAIL = ?, DRAWING = ?, SAMPLE = ?, DATA = ?,
                    SCH_CHANGE = ?, START_CHANGE = ?, CLOSE_CHANGE = ?
                WHERE CONTROL_ID = ?";
        
        $params = array(
            $control_no, $control_date, $dep_code, $pic_name, $item_id, $model, $material_id, $to_pcis, $cc_pcis,
            $man, $machine, $method, $material_4m, $other, $internal, $customer, $supplier, $perm, 
            $reason, $bef_change, $aft_change, $status, 
            $prepared, $imc_checked, $imc_aprove,
            $pe_remark, $qc_remark, $mold_remark, $ppic_remark, $prod_remark, $mkt_remark,
            $need_cust, $f_email, $f_drawing, $f_sample, $f_data,
            $sch_change, $start_change, $close_change,
            $control_id
        );
        $msg = "Data PCIS #{$control_no} berhasil diperbarui!";
    } else {
        // MODE INSERT (KALIBRASI TOTAL: 39 nama kolom dipasangkan akurat dengan 39 buah tanda tanya)
        $sql = "INSERT INTO PROSES_CHANGE (
                    CONTROL_NO, CONTROL_DATE1, DEP_CODE, PIC_NAME, ITEM_ID, MODEL, MATERIAL_ID, TO_PCIS, CC,
                    MAN, MACHINE, METHOD, MATERIAL, OTHER, INTERNAL, CUSTOMER, SUPPLIER, PERMANENT_CHANGE, 
                    REASON, BEF_CHANGE, AFT_CHANGE, STATUS, 
                    IMC_PREPARED, IMC_CHECKED, IMC_APROVE, 
                    PE_REMARK, QC_REMARK, MOLDSHOP_REMARK, PPIC_REMARK, PRODUCTION_REMARK, MARKETING_REMARK,
                    NEED_CUSTOMER, EMAIL, DRAWING, SAMPLE, DATA, SCH_CHANGE, START_CHANGE, CLOSE_CHANGE
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = array(
            $control_no, $control_date, $dep_code, $pic_name, $item_id, $model, $material_id, $to_pcis, $cc_pcis,
            $man, $machine, $method, $material_4m, $other, $internal, $customer, $supplier, $perm, 
            $reason, $bef_change, $aft_change, $status, 
            $prepared, $imc_checked, $imc_aprove,
            $pe_remark, $qc_remark, $mold_remark, $ppic_remark, $prod_remark, $mkt_remark,
            $need_cust, $f_email, $f_drawing, $f_sample, $f_data, $sch_change, $start_change, $close_change
        );
        $msg = "Data PCIS baru berhasil disimpan!";
    }
    
// Matikan peringatan error karakter kepanjangan
    sqlsrv_query($conn, "SET ANSI_WARNINGS OFF"); 
    
    $stmt = q($sql, $params);
    
    // Hidupkan kembali
    sqlsrv_query($conn, "SET ANSI_WARNINGS ON"); 
    
    if ($stmt === false) { die(print_r(sqlsrv_errors(), true)); }
    
    $qId = q("SELECT CONTROL_ID FROM PROSES_CHANGE WHERE CONTROL_NO = ?", array($control_no));
    $rId = sqlsrv_fetch_array($qId, SQLSRV_FETCH_ASSOC);
    $redir_id = $rId ? $rId['CONTROL_ID'] : '';

    echo "<script>alert('$msg'); window.location.href = 'input_pcis.php?id={$redir_id}';</script>";
    exit();
}

// === LOAD SELECTION DROPDOWN MASTER ===
$qDept = q("SELECT DEP_CODE, DEP_NAME FROM DEPT ORDER BY DEP_NAME");
$qStatus = q("SELECT STATUS FROM PROSES_STATUS");

$qUsers = q("
    SELECT DISTINCT nama FROM (
        SELECT IMC_PREPARED AS nama FROM PROSES_CHANGE
        UNION 
        SELECT IMC_CHECKED AS nama FROM PROSES_CHANGE
        UNION 
        SELECT IMC_APROVE AS nama FROM PROSES_CHANGE
    ) AS t WHERE nama IS NOT NULL AND RTRIM(nama) <> '' ORDER BY nama ASC
");
$list_users = [];
if($qUsers) {
    while($u = sqlsrv_fetch_array($qUsers, SQLSRV_FETCH_ASSOC)) {
        $list_users[] = trim($u['nama']);
    }
}

$autoControlNo = getNewControlNumber();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input PCIS (4M Change) - PE System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <style>
        body { background-color: #f4f7f6; font-family: "Segoe UI", Roboto, Arial, sans-serif; overflow-x: hidden; }
        #sidebar { width: 260px; height: 100vh; background: #1f2a36; color: white; position: fixed; display: flex; flex-direction: column; z-index: 1050; box-shadow: 3px 0 10px rgba(0,0,0,0.2); }
        #sidebar .brand { padding: 22px 20px; font-size: 16px; font-weight: 700; background: #8b5cf6; text-align: center; text-transform: uppercase; }
        .nav-link { color: #aab0b6; padding: 12px 20px; font-size: 13.5px; border-left: 4px solid transparent; display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background: #2c3e50; color: #fff !important; border-left-color: #a78bfa; }
        .menu-label { padding: 15px 20px 5px 20px; font-size: 11px; text-transform: uppercase; color: #5b6e80; font-weight: 800; }
        #content { padding-left: 260px; transition: all 0.3s; }
        .top-header { background: white; padding: 15px 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card-custom { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); background: white; margin-bottom: 25px; overflow: hidden; }
        .card-header-custom { background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #eef0f3; font-weight: 700; color: #1f2a36; display: flex; align-items: center; }
        .card-header-custom i { color: #8b5cf6; margin-right: 10px; font-size: 1.2rem; }
        label { font-weight: 600; color: #495057; font-size: 0.82rem; margin-bottom: 4px; text-transform: uppercase;}
        .form-control, .form-select { border-radius: 8px; border: 1px solid #ced4da; padding: 8px 12px; font-size: 0.9rem;}
        .floating-action { background: white; padding: 15px; border-radius: 12px; box-shadow: 0 -4px 15px rgba(0,0,0,0.05); position: sticky; bottom: 20px; z-index: 100;}
        .ui-autocomplete { position: absolute; z-index: 9999 !important; background: #fff; border: 1px solid #ced4da; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-height: 200px; overflow-y: auto; padding: 5px 0; }
        .ui-menu-item .ui-menu-item-wrapper { padding: 8px 15px; font-size: 0.88rem; cursor: pointer; }
        .ui-menu-item .ui-menu-item-wrapper:hover, .ui-menu-item .ui-menu-item-wrapper.ui-state-active { background-color: #8b5cf6 !important; color: #fff !important; border: none; }
    </style>
</head>
<body>

<div class="d-flex w-100">
    <div id="sidebar">
        <div class="brand"><i class="bi bi-arrow-repeat me-2"></i>4M Change System</div>
        <div class="py-2 overflow-auto h-100">
            <ul class="nav flex-column">
                <li class="nav-item"><a href="dashboard_4m.php?page=home" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard Overview</a></li>
                <div class="menu-label fw-bold">PROSES PERUBAHAN</div>
                <li class="nav-item"><a href="input_pcis.php" class="nav-link active"><i class="bi bi-plus-circle"></i> Input 4M Change</a></li>
                <li class="nav-item"><a href="dashboard_4m.php?page=history" class="nav-link"><i class="bi bi-clock-history"></i> Riwayat Perubahan</a></li>
                <li class="nav-item"><a href="dashboard_4m.php?page=rekap" class="nav-link"><i class="bi bi-journal-text"></i> Rekap Summary (SP)</a></li>
            </ul>
        </div>
        <div class="sidebar-footer p-3 bg-dark mt-auto">
            <small class="text-white-50 d-block mb-2"><i class="bi bi-person-circle me-1"></i> <?php echo $_SESSION['erp_user']; ?></small>
            <a href="logout.php" class="btn btn-danger w-100 btn-sm"><i class="bi bi-box-arrow-right"></i> LOGOUT</a><hr class="border-secondary my-2">
            <a href="../index.php" class="btn btn-sm btn-outline-light w-100"><i class="bi bi-box-arrow-left"></i> Kembali ke ERP</a>
        </div>
    </div>

    <div id="content" class="w-100 pb-5">
        <div class="top-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-file-earmark-plus text-primary me-2"></i> Pengajuan Formulir PCIS Baru</h4>
            <a href="dashboard_4m.php?page=history" class="btn btn-outline-secondary btn-sm fw-bold"><i class="bi bi-arrow-left"></i> Riwayat</a>
        </div>

        <div class="container-fluid px-4">
            <form method="POST" id="form4M">
                <input type="hidden" name="control_id" id="CONTROL_ID" value="0">

                <div class="card-custom mb-4" style="border-top: 4px solid #8b5cf6 !important;">
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label>CONTROL NO</label>
                                <input type="text" name="control_no" class="form-control fw-bold text-danger" readonly value="<?php echo $autoControlNo; ?>">
                            </div>
                            <div class="col-md-3">
                                <label>CONTROL DATE</label>
                                <input type="date" name="control_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label>TO</label>
                                <input type="text" name="to_pcis" class="form-control" value="ALL DEPARTEMENT">
                            </div>
                            <div class="col-md-3">
                                <label>CC</label>
                                <input type="text" name="cc_pcis" class="form-control" value="ALL HEAD DEPT">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card-custom">
                            <div class="card-header-custom"><i class="bi bi-tools"></i> Technical Change Details</div>
                            <div class="card-body p-4">
                                <div class="row g-3 p-3 mb-4 rounded border bg-light">
                                    <div class="col-md-4">
                                        <label class="d-block mb-2">Request By</label>
                                        <div class="form-check small mb-1"><input class="form-check-input" type="checkbox" name="internal" value="1" checked> <label>Internal</label></div>
                                        <div class="form-check small mb-1"><input class="form-check-input" type="checkbox" name="customer" value="1"> <label>Customer</label></div>
                                        <div class="form-check small"><input class="form-check-input" type="checkbox" name="supplier" value="1"> <label>Supplier</label></div>
                                    </div>
                                    <div class="col-md-8">
                                        <label>Person in Charge (PIC)</label>
                                        <input type="text" name="pic_name" class="form-control mb-2" placeholder="Ketik Nama PIC..." required>
                                        <select name="dep_code" class="form-select" required>
                                            <option value="">-- Pilih Departemen PIC --</option>
                                            <?php while($d = sqlsrv_fetch_array($qDept, SQLSRV_FETCH_ASSOC)) echo "<option value='{$d['DEP_CODE']}'>{$d['DEP_NAME']}</option>"; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label>Customer Name</label>
                                        <input type="text" id="CUST_COMP" class="form-control border-primary" placeholder="Klik/Cari customer..." autocomplete="off" required>
                                        <input type="hidden" name="cust_id" id="CUST_ID">
                                    </div>
                                    <div class="col-md-6">
                                        <label>Part / Item Name</label>
                                        <input type="text" id="PART_NAME" class="form-control border-primary" placeholder="Pilih customer, lalu klik ini..." autocomplete="off" required>
                                        <input type="hidden" name="item_id" id="ITEM_ID">
                                    </div>
                                    <div class="col-md-6">
                                        <label>Model</label>
                                        <input type="text" name="model" id="MODEL" class="form-control" placeholder="Model item...">
                                    </div>
                                    <div class="col-md-6">
                                        <label>Material Name (Autocomplete)</label>
                                        <input type="text" id="MATERIAL_TEXT" class="form-control border-primary" placeholder="Klik/Cari nama material...">
                                        <input type="hidden" name="material_id" id="MATERIAL_ID">
                                    </div>
                                </div>

                                <div class="row g-3 p-3 mb-4 rounded border bg-light">
                                    <div class="col-md-6">
                                        <label class="border-bottom d-block pb-1 mb-2">Item Change (4M Kategori)</label>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <div class="form-check small"><input class="form-check-input" type="checkbox" name="man" value="1"> <label>Man</label></div>
                                            <div class="form-check small"><input class="form-check-input" type="checkbox" name="machine" value="1"> <label>Machine</label></div>
                                            <div class="form-check small"><input class="form-check-input" type="checkbox" name="method" value="1"> <label>Method</label></div>
                                            <div class="form-check small"><input class="form-check-input" type="checkbox" name="material_4m" value="1"> <label>Material</label></div>
                                            <div class="form-check small"><input class="form-check-input" type="checkbox" name="other" value="1"> <label>Other</label></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 border-start ps-4">
                                        <label class="border-bottom d-block pb-1 mb-2">Changing Type</label>
                                        <div class="form-check form-check-inline small"><input class="form-check-input" type="radio" name="perm" value="1" checked> <label>Permanent</label></div>
                                        <div class="form-check form-check-inline small"><input class="form-check-input" type="radio" name="perm" value="0"> <label>Temporary</label></div>
                                    </div>
                                </div>

                                <div class="row g-3 p-3 mb-4 rounded border bg-light">
                                    <div class="col-md-12">
                                        <label class="border-bottom d-block pb-1 mb-2">Checklist Attachments (Lampiran)</label>
                                        <div class="d-flex gap-4 flex-wrap">
                                            <div class="form-check small"><input class="form-check-input" type="checkbox" name="attach_email" value="1"> <label>Email / Information</label></div>
                                            <div class="form-check small"><input class="form-check-input" type="checkbox" name="attach_drawing" value="1"> <label>Drawing</label></div>
                                            <div class="form-check small"><input class="form-check-input" type="checkbox" name="attach_sample" value="1"> <label>Sample</label></div>
                                            <div class="form-check small"><input class="form-check-input" type="checkbox" name="attach_data" value="1"> <label>Data</label></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label>Reason / Purpose</label>
                                    <textarea name="reason" class="form-control" rows="2" placeholder="Tuliskan alasan modifikasi..."></textarea>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="text-danger fw-bold">BEFORE CHANGE</label>
                                        <textarea name="bef_change" class="form-control border-danger-subtle" rows="3" placeholder="Kondisi awal sebelum perubahan..."></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="text-success fw-bold">AFTER CHANGE</label>
                                        <textarea name="aft_change" class="form-control border-success-subtle" rows="3" placeholder="Kondisi target sesudah perubahan..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card-custom mb-4">
                            <div class="card-header-custom"><i class="bi bi-calendar3"></i> Schedule Target</div>
                            <div class="card-body p-3">
                                <div class="mb-2">
                                    <label class="small text-muted">Schedule Proses Change</label>
                                    <input type="date" name="sch_change" id="SCH_CHANGE" class="form-control form-control-sm">
                                </div>
                                <div class="mb-2">
                                    <label class="small text-muted">Start Changing Date</label>
                                    <input type="date" name="start_change" id="START_CHANGE" class="form-control form-control-sm">
                                </div>
                                <div class="mb-2">
                                    <label class="small text-muted">Close Changing Date</label>
                                    <input type="date" name="close_change" id="CLOSE_CHANGE" class="form-control form-control-sm">
                                </div>
                            </div>
                        </div>

                        <div class="card-custom mb-4">
                            <div class="card-header-custom"><i class="bi bi-question-circle"></i> Customer Approval Needs</div>
                            <div class="card-body p-3">
                                <label class="small text-muted d-block mb-2">Do we need Customer Approved?</label>
                                <select name="need_customer" id="NEED_CUSTOMER" class="form-select form-select-sm fw-bold">
                                    <option value="1">YES (Memerlukan Persetujuan)</option>
                                    <option value="0">NO (Tidak Perlu)</option>
                                </select>
                            </div>
                        </div>

                        <div class="card-custom mb-4">
                            <div class="card-header-custom"><i class="bi bi-chat-square-text"></i> Departmental Review</div>
                            <div class="card-body p-3 overflow-auto" style="max-height: 220px;">
                                <?php foreach(['PE'=>'pe_remark','QC'=>'qc_remark','MOLD'=>'mold_remark','PPIC'=>'ppic_remark','PROD'=>'prod_remark','MKT'=>'mkt_remark'] as $lbl => $n): ?>
                                <div class="mb-2 pb-2 border-bottom">
                                    <label class="text-muted small" style="font-size:10px;"><?= $lbl ?> REMARK</label>
                                    <textarea name="<?= $n ?>" class="form-control form-control-sm" rows="1"></textarea>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <datalist id="list_nama_pegawai">
                            <?php foreach($list_users as $nama): ?>
                                <option value="<?php echo htmlspecialchars($nama); ?>">
                            <?php endforeach; ?>
                        </datalist>

                        <div class="card-custom bg-dark text-white p-3">
                            <label class="text-warning small mb-3"><i class="bi bi-shield-check me-1"></i> Post Validation & Status</label>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="text-white-50" style="font-size:10px;">STATUS</label>
                                    <select name="status" class="form-select form-select-sm bg-warning text-dark border-0 fw-bold">
                                        <?php while($s = sqlsrv_fetch_array($qStatus, SQLSRV_FETCH_ASSOC)) echo "<option value='{$s['STATUS']}'>{$s['STATUS']}</option>"; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="text-white-50" style="font-size:10px;">PREPARED BY</label>
                                    <input type="text" name="prepared" list="list_nama_pegawai" class="form-control form-control-sm bg-secondary text-white border-0" value="<?php echo $_SESSION['erp_user']; ?>" placeholder="Pilih / Ketik...">
                                </div>
                                <div class="col-6">
                                    <label class="text-white-50" style="font-size:10px;">CHECKED BY</label>
                                    <input type="text" name="imc_checked" list="list_nama_pegawai" class="form-control form-control-sm bg-secondary text-white border-0" placeholder="Pilih / Ketik...">
                                </div>
                                <div class="col-6">
                                    <label class="text-white-50" style="font-size:10px;">APPROVED BY</label>
                                    <input type="text" name="imc_aprove" list="list_nama_pegawai" class="form-control form-control-sm bg-secondary text-white border-0" placeholder="Pilih / Ketik...">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="floating-action d-flex justify-content-between align-items-center mt-4">
                    <div>
                        <button type="button" class="btn btn-outline-dark btn-nav shadow-sm" id="firstBtn">⏮ First</button>
                        <button type="button" class="btn btn-outline-dark btn-nav shadow-sm" id="prevBtn">← Prev</button>
                        <button type="button" class="btn btn-outline-dark btn-nav shadow-sm" id="nextBtn">Next →</button>
                        <button type="button" class="btn btn-outline-dark btn-nav shadow-sm" id="lastBtn">Last ⏭</button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-success btn-nav me-2 shadow-sm" id="newBtn"><i class="bi bi-plus-lg"></i> Form Baru</button>
                        <button type="submit" name="btnSimpan" class="btn btn-primary btn-nav shadow"><i class="bi bi-save"></i> Simpan Laporan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let MIN_ID = "", MAX_ID = "";

function loadMinMax() {
    $.get("load_pcis.php", { mode: "minmax" }, function(r) {
        if (r.status === "ok") { MIN_ID = r.min_id; MAX_ID = r.max_id; }
    }, "json");
}

function updateNav(id) {
    $("#firstBtn, #prevBtn").prop("disabled", id == MIN_ID);
    $("#nextBtn, #lastBtn").prop("disabled", id == MAX_ID);
}

function fillForm(rec) {
    $("#CONTROL_ID").val(rec.CONTROL_ID);
    $("input[name='control_no']").val(rec.CONTROL_NO.trim());
    $("input[name='control_date']").val(rec.CONTROL_DATE1);
    $("input[name='to_pcis']").val(rec.TO_PCIS ? rec.TO_PCIS.trim() : '');
    $("input[name='cc_pcis']").val(rec.CC ? rec.CC.trim() : '');
    $("input[name='pic_name']").val(rec.PIC_NAME ? rec.PIC_NAME.trim() : '');
    $("select[name='dep_code']").val(rec.DEP_CODE ? rec.DEP_CODE.trim() : '');
    
    $("#CUST_COMP").val(rec.CUST_COMP ? rec.CUST_COMP.trim() : '');
    $("#CUST_ID").val(rec.CUST_ID || '');
    $("#PART_NAME").val(rec.PART_NAME ? rec.PART_NAME.trim() : '');
    $("#ITEM_ID").val(rec.ITEM_ID || '');
    $("#MODEL").val(rec.MODEL ? rec.MODEL.trim() : '');
    
    $("#MATERIAL_TEXT").val(rec.MATERIAL_NAME ? rec.MATERIAL_NAME.trim() : '');
    $("#MATERIAL_ID").val(rec.MATERIAL_ID || '');

    $("input[name='internal']").prop('checked', rec.INTERNAL == 1);
    $("input[name='customer']").prop('checked', rec.CUSTOMER == 1);
    $("input[name='supplier']").prop('checked', rec.SUPPLIER == 1);
    $("input[name='man']").prop('checked', rec.MAN == 1);
    $("input[name='machine']").prop('checked', rec.MACHINE == 1);
    $("input[name='method']").prop('checked', rec.METHOD == 1);
    $("input[name='material_4m']").prop('checked', rec.MATERIAL == 1 || rec.MATERIAL === true);
    $("input[name='other']").prop('checked', rec.OTHER == 1);

    // Dynamic Binding Saat Navigasi (First/Prev/Next/Last)
    $("#NEED_CUSTOMER").val(rec.NEED_CUSTOMER !== undefined ? rec.NEED_CUSTOMER : 1);
    $("input[name='attach_email']").prop('checked', rec.EMAIL == 1);
    $("input[name='attach_drawing']").prop('checked', rec.DRAWING == 1);
    $("input[name='attach_sample']").prop('checked', rec.SAMPLE == 1);
    $("input[name='attach_data']").prop('checked', rec.DATA == 1);

    if(rec.PERMANENT_CHANGE == 0) window.jQuery("input[name='perm'][value='0']").prop('checked', true);
    else window.jQuery("input[name='perm'][value='1']").prop('checked', true);

    $("textarea[name='reason']").val(rec.REASON ? rec.REASON.trim() : '');
    $("textarea[name='bef_change']").val(rec.BEF_CHANGE ? rec.BEF_CHANGE.trim() : '');
    $("textarea[name='aft_change']").val(rec.AFT_CHANGE ? rec.AFT_CHANGE.trim() : '');

    $("#SCH_CHANGE").val(rec.SCH_CHANGE || '');
    $("#START_CHANGE").val(rec.START_CHANGE || '');
    $("#CLOSE_CHANGE").val(rec.CLOSE_CHANGE || '');

    $("textarea[name='pe_remark']").val(rec.PE_REMARK ? rec.PE_REMARK.trim() : '');
    $("textarea[name='qc_remark']").val(rec.QC_REMARK ? rec.QC_REMARK.trim() : '');
    $("textarea[name='mold_remark']").val(rec.MOLDSHOP_REMARK ? rec.MOLDSHOP_REMARK.trim() : '');
    $("textarea[name='ppic_remark']").val(rec.PPIC_REMARK ? rec.PPIC_REMARK.trim() : '');
    $("textarea[name='prod_remark']").val(rec.PRODUCTION_REMARK ? rec.PRODUCTION_REMARK.trim() : '');
    $("textarea[name='mkt_remark']").val(rec.MARKETING_REMARK ? rec.MARKETING_REMARK.trim() : '');

    $("input[name='prepared']").val(rec.IMC_PREPARED ? rec.IMC_PREPARED.trim() : '');
    $("input[name='imc_checked']").val(rec.IMC_CHECKED ? rec.IMC_CHECKED.trim() : '');
    $("input[name='imc_aprove']").val(rec.IMC_APROVE ? rec.IMC_APROVE.trim() : '');
    $("select[name='status']").val(rec.STATUS ? rec.STATUS.trim() : 'OPEN');

    updateNav(rec.CONTROL_ID);
}

$(document).ready(function() {
    loadMinMax();

    $("#firstBtn").click(function(){ $.get("load_pcis.php", {mode:"first"}, function(r){ if(r.status==="ok") fillForm(r.record);},"json"); });
    $("#lastBtn").click(function(){ $.get("load_pcis.php", {mode:"last"}, function(r){ if(r.status==="ok") fillForm(r.record);},"json"); });
    $("#nextBtn").click(function(){ let id=$("#CONTROL_ID").val()||0; $.get("load_pcis.php", {mode:"next", id:id}, function(r){ if(r.status==="ok") fillForm(r.record);},"json"); });
    $("#prevBtn").click(function(){ let id=$("#CONTROL_ID").val()||0; $.get("load_pcis.php", {mode:"prev", id:id}, function(r){ if(r.status==="ok") fillForm(r.record);},"json"); });

    $("#CUST_COMP").autocomplete({
        minLength: 0, source: "?ajax_search=cust",
        select: function(event, ui) {
            $("#CUST_COMP").val(ui.item.label);
            $("#CUST_ID").val(ui.item.value);
            $("#PART_NAME, #ITEM_ID, #MODEL").val("");
            return false;
        }
    }).on("focus click", function() { $(this).autocomplete("search", $(this).val()); });

    $("#PART_NAME").autocomplete({
        minLength: 0,
        source: function(req, res) {
            let activeCust = $("#CUST_ID").val();
            if(!activeCust) { res([{ label: "Silakan pilih Customer terlebih dahulu!", value: "" }]); return; }
            $.ajax({
                url: "?ajax_search=part", type: "GET", dataType: "json", data: { term: req.term, cust_id: activeCust },
                success: function(data) {
                    if (!data || data.length === 0) res([{ label: "- Part tidak ditemukan -", value: "" }]); else res(data);
                }
            });
        },
        select: function(event, ui) {
            if(ui.item.value === "") return false;
            $("#PART_NAME").val(ui.item.label);
            $("#ITEM_ID").val(ui.item.value);
            $("#MODEL").val(ui.item.model || '');
            return false;
        }
    }).on("focus click", function() { $(this).autocomplete("search", $(this).val()); });

    $("#MATERIAL_TEXT").autocomplete({
        minLength: 0, source: "?ajax_search=mat",
        select: function(event, ui) {
            $("#MATERIAL_TEXT").val(ui.item.label);
            $("#MATERIAL_ID").val(ui.item.value); 
            return false;
        }
    }).on("focus click", function() { $(this).autocomplete("search", $(this).val()); });

    $("#newBtn").click(function(){
        $.get("load_pcis.php", {mode:"last"}, function(r){ location.reload(); });
    });

    const urlParams = new URLSearchParams(window.location.search);
    if(urlParams.has('id')) {
        let currentID = urlParams.get('id');
        $.get("load_pcis.php", { mode: "load", id: currentID }, function(r){
            if (r.status === "ok") fillForm(r.record);
        },"json");
    }
});
</script>
</body>
</html>
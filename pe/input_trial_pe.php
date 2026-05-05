<?php
// FILE: msii/pe/input_trial_pe.php
require_once 'MiddleWare/Auth.php'; 
require_once __DIR__ . '/../config/database_p1.php';

// === AJAX HANDLER UNTUK AUTOCOMPLETE CUSTOMER & MATERIAL ===
// Fitur baru agar form tidak perlu pindah halaman untuk mencari data
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $type = $_GET['ajax_search'];
    $term = isset($_GET['term']) ? trim($_GET['term']) : '';
    $res = [];
    
    if ($type == 'cust' && !empty($term)) {
        $stmt = sqlsrv_query($conn, "SELECT TOP 15 CUST_ID, CUST_COMP FROM CUST WHERE CUST_COMP LIKE ?", ["%$term%"]);
        if($stmt) while($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            $res[] = ['label' => $r['CUST_COMP'], 'value' => $r['CUST_ID']];
        }
    } 
    elseif ($type == 'mat' && !empty($term)) {
        $stmt = sqlsrv_query($conn, "SELECT TOP 15 ITEM_ID, ITEM_NAME FROM ITEMS WHERE ITEM_NAME LIKE ? OR ITEM_CODE LIKE ?", ["%$term%", "%$term%"]);
        if($stmt) while($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            $res[] = ['label' => $r['ITEM_NAME'], 'value' => $r['ITEM_ID']];
        }
    }
    echo json_encode($res);
    exit;
}

// === Ambil data jenis trial ===
$jenis_list = [];
$resJenis = sqlsrv_query($conn, "SELECT ID, JENIS_TRIAL FROM TRIAL_PE_JENIS ORDER BY JENIS_TRIAL");
if($resJenis) while ($row = sqlsrv_fetch_array($resJenis, SQLSRV_FETCH_ASSOC)) { $jenis_list[] = $row; }

// === Ambil data Judge Trial ===
$judge_list = [];
$resJudge = sqlsrv_query($conn, "SELECT ID, JUDGE_TRIAL FROM JUDGE_TRIAL ORDER BY JUDGE_TRIAL");
if($resJudge) while ($row = sqlsrv_fetch_array($resJudge, SQLSRV_FETCH_ASSOC)) { $judge_list[] = $row; }

// === Auto-generate TRIAL_CODE (integer) ===
$resLast = sqlsrv_query($conn, "SELECT TOP 1 TRIAL_CODE FROM TRIAL_PE ORDER BY TRIAL_CODE DESC");
$nextNum = 1;
if ($resLast && ($last = sqlsrv_fetch_array($resLast, SQLSRV_FETCH_ASSOC))) {
    $nextNum = intval($last['TRIAL_CODE']) + 1;
}
$autoTrialCode = $nextNum;

// === Fungsi Upload Foto ===
function uploadFoto($inputName) {
    if (!empty($_FILES[$inputName]['name'])) {
        $dir = "../assets/foto_trial/";
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $name = time() . "_" . preg_replace('/[^A-Za-z0-9_\.-]/', '_', basename($_FILES[$inputName]['name']));
        move_uploaded_file($_FILES[$inputName]['tmp_name'], $dir . $name);
        return $name;
    }
    return null;
}

// === Simpan data TRIAL_PE ===
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    function val($k, $d = "") { return isset($_POST[$k]) ? $_POST[$k] : $d; }

    $current_code = val('CURRENT_CODE'); 
    $part_code    = val('PART_CODE');

    $foto_name   = uploadFoto('foto'); 
    $foto_mat    = uploadFoto('foto_material');
    $foto_core   = uploadFoto('foto_mold_core');
    $foto_cavity = uploadFoto('foto_mold_cavity');
    $foto_mach   = uploadFoto('foto_machine');

    // Ambil fallback data dari Master
    $sqlMaster = "SELECT TOP 1 LAST_CUST.CUST_ID, std.MAT_CODE, mat.ITEM_ID AS MAT_ID
                  FROM ITEMS I
                  LEFT JOIN TRIAL_PE_STD std ON I.ITEM_CODE = std.ITEM_CODE
                  LEFT JOIN ITEMS mat ON mat.ITEM_CODE = std.MAT_CODE
                  OUTER APPLY (
                      SELECT TOP 1 CUST_ID FROM TRIAL_PE WHERE PART_CODE = I.ITEM_CODE ORDER BY TRIAL_CODE DESC
                  ) AS LAST_CUST
                  WHERE I.ITEM_CODE = ?";
    $stmtM = sqlsrv_query($conn, $sqlMaster, array($part_code));
    $master = $stmtM ? sqlsrv_fetch_array($stmtM, SQLSRV_FETCH_ASSOC) : null;

    // PRIORITASKAN INPUT USER: Jika diketik manual, ambil ID manualnya. Jika tidak, baru pakai Master.
    $cust_id = !empty(val('CUST_ID')) ? intval(val('CUST_ID')) : ($master && $master['CUST_ID'] ? intval($master['CUST_ID']) : 0);
    $mat_id  = !empty(val('MAT_USING')) ? intval(val('MAT_USING')) : ($master && $master['MAT_ID'] ? intval($master['MAT_ID']) : 0);

    if (!empty($current_code)) {
        // UPDATE
        $sql = "UPDATE TRIAL_PE SET 
            DATE=?, PART_CODE=?, CUST_ID=?, QUANTITY_TRIAL=?, TRIAL_REASON=?, TRIAL_TIMES=?,
            MAT_USING=?, MAT_DRYING_TIME=?, MOLD_SET_UP=?, MOLD_SET_DOWN=?, TRIAL_DURATION=?,
            QE_COMMENT=?, PE_COMMENT=?, JUDGE_ID=?, PIC=?, WEIGHT_RUNNER=?, PREPARED=?,
            CHECKED=?, APPROVED=?, QTY_OK=?, QTY_NG=?, CYCLE_TIME_ACT=?, MAC_NO=?, JENIS_ID=?,
            TONAGE=?, CORRECTIVE_ACTION=?, ANALYSYS=?, 
            OPERATION=?, REGRIND_PCT=?, CHK_BURRY=?, CHK_VOID=?, CHK_SHORTMOLD=?, CHK_WELDLINE=?, 
            CHK_BURNING=?, CHK_SINKMARK=?, CHK_DENTED=?, CHK_SILVER=?, CHK_SCRATCH=? ";
        
        $params = [
            val('DATE'), $part_code, $cust_id, floatval(val('QUANTITY_TRIAL')), val('TRIAL_REASON'), val('TRIAL_TIMES'),
            $mat_id, intval(val('MAT_DRYING_TIME')), intval(val('MOLD_SET_UP')), intval(val('MOLD_SET_DOWN')), val('TRIAL_DURATION'),
            val('QE_COMMENT'), val('PE_COMMENT'), intval(val('JUDGE_ID')), val('PIC'), floatval(val('WEIGHT_RUNNER')), val('PREPARED'),
            val('CHECKED'), val('APPROVED'), intval(val('QTY_OK')), intval(val('QTY_NG')), floatval(val('CYCLE_TIME_ACT')), intval(val('MAC_NO')), intval(val('JENIS_ID')),
            intval(val('TONAGE')), val('CORRECTIVE_ACTION'), val('ANALYSYS'),
            val('OPERATION'), floatval(val('REGRIND_PCT')), val('CHK_BURRY','V'), val('CHK_VOID','V'), val('CHK_SHORTMOLD','V'), val('CHK_WELDLINE','V'),
            val('CHK_BURNING','V'), val('CHK_SINKMARK','V'), val('CHK_DENTED','V'), val('CHK_SILVER','V'), val('CHK_SCRATCH','V')
        ];

        if ($foto_name)   { $sql .= ", foto=? "; $params[] = $foto_name; }
        if ($foto_mat)    { $sql .= ", foto_material=? "; $params[] = $foto_mat; }
        if ($foto_core)   { $sql .= ", foto_mold_core=? "; $params[] = $foto_core; }
        if ($foto_cavity) { $sql .= ", foto_mold_cavity=? "; $params[] = $foto_cavity; }
        if ($foto_mach)   { $sql .= ", foto_machine=? "; $params[] = $foto_mach; }

        $sql .= " WHERE TRIAL_CODE=?";
        $params[] = $current_code;

        $stmt = sqlsrv_query($conn, $sql, $params);
        $msg = "Data Trial #{$current_code} berhasil di-UPDATE!";
        $targetCode = $current_code;

    } else {
        // INSERT
        $sql = "INSERT INTO TRIAL_PE (
            DATE, PART_CODE, CUST_ID, QUANTITY_TRIAL, TRIAL_REASON, TRIAL_TIMES,
            MAT_USING, MAT_DRYING_TIME, MOLD_SET_UP, MOLD_SET_DOWN, TRIAL_DURATION,
            QE_COMMENT, PE_COMMENT, JUDGE_ID, PIC, WEIGHT_RUNNER, PREPARED,
            CHECKED, APPROVED, QTY_OK, QTY_NG, CYCLE_TIME_ACT, MAC_NO, JENIS_ID,
            TONAGE, CORRECTIVE_ACTION, ANALYSYS, foto, 
            OPERATION, REGRIND_PCT, CHK_BURRY, CHK_VOID, CHK_SHORTMOLD, CHK_WELDLINE, 
            CHK_BURNING, CHK_SINKMARK, CHK_DENTED, CHK_SILVER, CHK_SCRATCH, 
            foto_material, foto_mold_core, foto_mold_cavity, foto_machine
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $params = [
            val('DATE'), $part_code, $cust_id, floatval(val('QUANTITY_TRIAL')), val('TRIAL_REASON'), val('TRIAL_TIMES'),
            $mat_id, intval(val('MAT_DRYING_TIME')), intval(val('MOLD_SET_UP')), intval(val('MOLD_SET_DOWN')), val('TRIAL_DURATION'),
            val('QE_COMMENT'), val('PE_COMMENT'), intval(val('JUDGE_ID')), val('PIC'), floatval(val('WEIGHT_RUNNER')), val('PREPARED'),
            val('CHECKED'), val('APPROVED'), intval(val('QTY_OK')), intval(val('QTY_NG')), floatval(val('CYCLE_TIME_ACT')), intval(val('MAC_NO')), intval(val('JENIS_ID')),
            intval(val('TONAGE')), val('CORRECTIVE_ACTION'), val('ANALYSYS'), $foto_name,
            val('OPERATION'), floatval(val('REGRIND_PCT')), val('CHK_BURRY','V'), val('CHK_VOID','V'), val('CHK_SHORTMOLD','V'), val('CHK_WELDLINE','V'),
            val('CHK_BURNING','V'), val('CHK_SINKMARK','V'), val('CHK_DENTED','V'), val('CHK_SILVER','V'), val('CHK_SCRATCH','V'),
            $foto_mat, $foto_core, $foto_cavity, $foto_mach
        ];

        $stmt = sqlsrv_query($conn, $sql, $params);
        $msg = "Data Trial BARU berhasil disimpan!";
        $targetCode = val('TRIAL_CODE');
    }

    if ($stmt) {
        echo "<script>alert('$msg'); window.location='input_trial_pe.php?mode=load&code={$targetCode}';</script>";
        exit;
    } else {
        echo "<pre>Gagal memproses data:\n" . print_r(sqlsrv_errors(), true) . "</pre>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Trial Produk - PE System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        body { background-color: #f4f7f6; font-family: "Segoe UI", Roboto, Arial, sans-serif; overflow-x: hidden; }
        
        #sidebar {
            width: 250px; height: 100vh; background: #1f2a36; color: white;
            position: fixed; top: 0; left: 0; z-index: 1050;
            display: flex; flex-direction: column; box-shadow: 3px 0 10px rgba(0,0,0,0.2);
        }
        #sidebar .brand { padding: 22px 20px; font-size: 18px; font-weight: 700; background: #1a232d; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-link { color: #aab0b6; padding: 12px 20px; font-size: 14.5px; border-left: 4px solid transparent; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background: #2c3e50; color: #fff !important; border-left-color: #3498db; }
        .nav-link i { margin-right: 10px; font-size: 1.1rem; }
        .menu-label { padding: 20px 20px 8px 20px; font-size: 11px; text-transform: uppercase; color: #5b6e80; font-weight: 800; letter-spacing: 1px; }
        .sidebar-footer { margin-top: auto; padding: 15px 20px; background: #161e27; border-top: 1px solid rgba(255,255,255,0.05); }
        
        #content { padding-left: 250px; transition: all 0.3s; }
        .top-header { background: white; padding: 15px 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }

        .card-custom { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); background: white; margin-bottom: 25px; overflow: hidden; }
        .card-header-custom { background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #eef0f3; font-weight: 700; color: #1f2a36; display: flex; align-items: center; }
        .card-header-custom i { color: #3498db; margin-right: 10px; font-size: 1.2rem; }
        
        label { font-weight: 600; color: #495057; font-size: 0.85rem; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;}
        .form-control, .form-select { border-radius: 8px; border: 1px solid #ced4da; padding: 10px 15px; font-size: 0.95rem;}
        .form-control:focus, .form-select:focus { box-shadow: 0 0 0 0.25rem rgba(52,152,219,0.25); border-color: #3498db; }
        .form-control[readonly] { background-color: #f8f9fa; border-color: #e9ecef; }
        
        .table-act th { background: #1f2a36; color: white; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; border:none;}
        #ACT_TABLE td.editable { cursor:pointer; background: #fffbe6; font-weight: bold;}
        #ACT_TABLE td.editable:hover { background: #fff3cd; }
        #ACT_TABLE td.editable input { width:100%; border:1px solid #3498db; border-radius:4px; padding:4px 8px; font-size:0.9rem; }
        
        .btn-nav { border-radius: 8px; padding: 10px 20px; font-weight: 600; letter-spacing: 0.5px; }
        .floating-action { background: white; padding: 15px; border-radius: 12px; box-shadow: 0 -4px 15px rgba(0,0,0,0.05); position: sticky; bottom: 20px; z-index: 100;}
    
        .ui-autocomplete {
            position: absolute;
            z-index: 9999 !important; 
            background-color: #ffffff;
            border: 1px solid #ced4da;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            max-height: 250px;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 5px 0;
        }
        .ui-menu-item .ui-menu-item-wrapper {
            padding: 8px 15px;
            font-size: 0.9rem;
            cursor: pointer;
        }
        .ui-menu-item .ui-menu-item-wrapper:hover,
        .ui-menu-item .ui-menu-item-wrapper.ui-state-active {
            background-color: #3498db !important;
            color: #ffffff !important;
            border: none;
            margin: 0;
        }
    </style>
</head>
<body>

<div class="d-flex w-100">
    <?php include 'includes/sidebar.php'; ?>

    <div id="content" class="w-100 pb-5">
        
        <div class="top-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-file-earmark-plus text-primary me-2"></i> Input Data Trial Baru</h4>
            <a href="dashboard_pe.php" class="btn btn-outline-secondary btn-sm fw-bold"><i class="bi bi-arrow-left"></i> Kembali</a>
        </div>

        <div class="container-fluid px-4">
            <form method="POST" enctype="multipart/form-data" id="trialForm">
                <input type="hidden" name="CURRENT_CODE" id="CURRENT_CODE">

                <div class="row">
                    <div class="col-lg-8">

                        <div class="card-custom">
                            <div class="card-header-custom"><i class="bi bi-info-square"></i> Informasi Part & Material</div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label>Kode Trial (Auto)</label>
                                        <input type="text" name="TRIAL_CODE" id="TRIAL_CODE" class="form-control fw-bold text-primary" readonly value="<?= $autoTrialCode ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label>Tanggal</label>
                                        <input type="date" name="DATE" class="form-control" required value="<?= date('Y-m-d') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label>Trial Ke</label>
                                        <input type="number" name="TRIAL_TIMES" class="form-control" placeholder="1">
                                    </div>

                                    <div class="col-md-4">
                                        <label>Kode Part</label>
                                        <input type="text" name="PART_CODE" id="PART_CODE" class="form-control border-primary" autocomplete="off" placeholder="Ketik Part Code..." required>
                                    </div>
                                    <div class="col-md-4">
                                        <label>Nama Part</label>
                                        <input type="text" id="PART_NAME" class="form-control" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label>Customer</label>
                                        <!-- KUNCI DIBUKA & DIBERI AUTOCOMPLETE -->
                                        <input type="text" id="CUST_COMP" class="form-control border-primary" placeholder="Cari Customer..." autocomplete="off">
                                        <input type="hidden" name="CUST_ID" id="CUST_ID">
                                    </div>

                                    <div class="col-md-8">
                                        <label>Material Name</label>
                                        <!-- KUNCI DIBUKA & DIBERI AUTOCOMPLETE -->
                                        <input type="hidden" name="MAT_USING" id="MAT_USING">
                                        <input type="text" id="MAT_NAME" class="form-control border-primary" placeholder="Cari Material..." autocomplete="off">
                                    </div>
                                    <div class="col-md-4">
                                        <label>Regrind Material (%)</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" name="REGRIND_PCT" class="form-control" value="0">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-custom">
                            <div class="card-header-custom"><i class="bi bi-sliders"></i> Parameter Kondisi Mesin</div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label>Operation Method</label>
                                        <select name="OPERATION" class="form-select">
                                            <option value="AUTO ROBOT">AUTO ROBOT</option>
                                            <option value="MANUAL">MANUAL</option>
                                            <option value="SEMI AUTO">SEMI AUTO</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label>No Mesin / Tonnage</label>
                                        <div class="input-group">
                                            <input type="text" name="MAC_NO" class="form-control" placeholder="No Mac">
                                            <input type="text" name="TONAGE" class="form-control" placeholder="Ton">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label>Cycle Time Actual (Sec)</label>
                                        <input type="text" name="CYCLE_TIME_ACT" class="form-control">
                                    </div>

                                    <div class="col-md-3">
                                        <label>Material Drying Time</label>
                                        <input type="number" name="MAT_DRYING_TIME" class="form-control">
                                    </div>
                                    <div class="col-md-3">
                                        <label>Mold Set Up (Min)</label>
                                        <input type="number" name="MOLD_SET_UP" class="form-control">
                                    </div>
                                    <div class="col-md-3">
                                        <label>Mold Set Down (Min)</label>
                                        <input type="number" name="MOLD_SET_DOWN" class="form-control">
                                    </div>
                                    <div class="col-md-3">
                                        <label>Durasi Trial (Min)</label>
                                        <input type="text" name="TRIAL_DURATION" class="form-control">
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label>Qty Trial (Shots)</label>
                                        <input type="number" name="QUANTITY_TRIAL" class="form-control">
                                    </div>
                                    <div class="col-md-8">
                                        <label>Trial Reason</label>
                                        <input type="text" name="TRIAL_REASON" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-custom">
                            <div class="card-header-custom bg-info text-white border-0"><i class="bi bi-shield-check text-white"></i> Quality Appearance & Judgment</div>
                            <div class="card-body p-4">
                                <h6 class="fw-bold mb-3">APPEARANCE CHECK (V = OK, X = NG)</h6>
                                <div class="row g-2 text-center align-items-end mb-4">
                                    <?php 
                                    $checks = [
                                        'CHK_BURRY'=>'Burry', 'CHK_VOID'=>'Void', 'CHK_SHORTMOLD'=>'Shortmold',
                                        'CHK_WELDLINE'=>'Weld Line', 'CHK_BURNING'=>'Burning', 'CHK_SINKMARK'=>'Sink Mark',
                                        'CHK_DENTED'=>'Dented', 'CHK_SILVER'=>'Silver Mark', 'CHK_SCRATCH'=>'Scratch'
                                    ];
                                    foreach($checks as $key => $label): ?>
                                    <div class="col">
                                        <label style="font-size:10px;"><?= $label ?></label>
                                        <select name="<?= $key ?>" id="<?= $key ?>" class="form-select form-select-sm fw-bold">
                                            <option value="V" class="text-success">V</option>
                                            <option value="X" class="text-danger">X</option>
                                        </select>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <hr>
                                <div class="row g-3 mt-1">
                                   
                                    <div class="col-md-12">
                                        <label>Problem</label>
                                        <textarea name="PE_COMMENT" class="form-control" rows="2"></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Corrective Action</label>
                                        <textarea name="CORRECTIVE_ACTION" class="form-control" rows="2"></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Analisis</label>
                                        <textarea name="ANALYSYS" class="form-control" rows="2"></textarea>
                                    </div>

                                    <div class="col-md-4">
                                        <label>Qty OK</label>
                                        <input type="number" name="QTY_OK" class="form-control border-success">
                                    </div>
                                    <div class="col-md-4">
                                        <label>Qty NG</label>
                                        <input type="number" name="QTY_NG" class="form-control border-danger">
                                    </div>
                                    <div class="col-md-4">
                                        <label>Final Judge</label>
                                        <select name="JUDGE_ID" class="form-select border-primary fw-bold">
                                            <option value="">-- Pilih Hasil --</option>
                                            <?php foreach($judge_list as $j) echo "<option value='{$j['ID']}'>{$j['JUDGE_TRIAL']}</option>"; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="col-lg-4">
                        
                        <div class="card-custom">
                            <div class="card-header-custom"><i class="bi bi-speedometer"></i> Standard vs Actual</div>
                            <div class="card-body p-3">
                                <div class="bg-light p-2 rounded mb-3 small border">
                                    <div class="d-flex justify-content-between"><span>Part STD:</span><strong id="STD_WEIGHT_PART">-</strong></div>
                                    <div class="d-flex justify-content-between"><span>Runner STD:</span><strong id="STD_WEIGHT_RUNNER">-</strong></div>
                                    <div class="d-flex justify-content-between"><span>Cavity STD:</span><strong id="STD_CAVITY">-</strong></div>
                                </div>

                                <label class="mb-2">Input Berat Aktual Per Cavity</label>
                                <button type="button" class="btn btn-primary btn-sm w-100 mb-2 fw-bold" id="addACTbtn">+ Tambah Cavity Aktual</button>
                                
                                <div class="table-responsive rounded border">
                                    <table class="table table-sm table-act mb-0" id="ACT_TABLE">
                                        <thead><tr><th width="10%">No</th><th>Weight (g)</th><th width="20%">Cav</th><th width="20%">#</th></tr></thead>
                                        <tbody id="ACT_BODY">
                                            <tr><td colspan='4' class='text-center text-muted small py-3'>Simpan Trial dulu untuk input berat aktual.</td></tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-3">
                                    <label>Weight Runner Aktual (g)</label>
                                    <input type="text" name="WEIGHT_RUNNER" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="card-custom">
                            <div class="card-header-custom"><i class="bi bi-pen"></i> Tanda Tangan & Lampiran</div>
                            <div class="card-body p-3">
                                <div class="row g-2 mb-3">
                                    <div class="col-6"><label>PIC</label><input type="text" name="PIC" class="form-control form-control-sm"></div>
                                    <div class="col-6"><label>Prepared</label><input type="text" name="PREPARED" class="form-control form-control-sm"></div>
                                    <div class="col-6"><label>Checked</label><input type="text" name="CHECKED" class="form-control form-control-sm"></div>
                                    <div class="col-6"><label>Approved</label><input type="text" name="APPROVED" class="form-control form-control-sm"></div>
                                </div>
                                <hr>
                                <div class="mb-2">
                                    <label>Foto Produk/Part</label>
                                    <input type="file" name="foto" class="form-control form-control-sm" accept="image/*">
                                </div>
                                <div class="mb-2">
                                    <label>Foto Material Bag</label>
                                    <input type="file" name="foto_material" class="form-control form-control-sm" accept="image/*">
                                </div>
                                <div class="mb-2">
                                    <label>Foto Mold Core</label>
                                    <input type="file" name="foto_mold_core" class="form-control form-control-sm" accept="image/*">
                                </div>
                                <div class="mb-2">
                                    <label>Foto Mold Cavity</label>
                                    <input type="file" name="foto_mold_cavity" class="form-control form-control-sm" accept="image/*">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold">PICTURE OF MACHINE STATISTIC</label>
                                    <input type="file" name="foto_machine" id="foto_machine" class="form-control" accept="image/*">
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="floating-action d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <button type="button" class="btn btn-outline-dark btn-nav" id="firstBtn">⏮ First</button>
                        <button type="button" class="btn btn-outline-dark btn-nav" id="prevBtn">← Prev</button>
                        <button type="button" class="btn btn-outline-dark btn-nav" id="nextBtn">Next →</button>
                        <button type="button" class="btn btn-outline-dark btn-nav" id="lastBtn">Last ⏭</button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-success btn-nav me-2" id="newBtn"><i class="bi bi-plus-lg"></i> Form Baru</button>
                        <button type="submit" class="btn btn-primary btn-nav shadow"><i class="bi bi-save"></i> Simpan Laporan</button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
// --- LOGIC JAVASCRIPT ---

let MIN_CODE = "", MAX_CODE = "";
function loadMinMax() {
    $.get("load_record.php", {mode: "minmax"}, function(r){
        if (r.status === "ok") { MIN_CODE = r.min_code; MAX_CODE = r.max_code; }
    }, "json");
}
loadMinMax();

function updateNav(code) {
    $("#firstBtn, #prevBtn").prop("disabled", code == MIN_CODE);
    $("#nextBtn, #lastBtn").prop("disabled", code == MAX_CODE);
}

// FUNGSI FILL FORM SAAT KLIK NEXT/PREV
function fillForm(rec) {
    $("#CURRENT_CODE").val(rec.TRIAL_CODE);
    $("#TRIAL_CODE").val(rec.TRIAL_CODE);

    $("input[name='DATE']").val(rec.DATE);
    $("input[name='PART_CODE']").val(rec.PART_CODE);
    $("#PART_NAME").val(rec.PART_NAME || "");
    $("#CUST_COMP").val(rec.CUST_COMP || "");
    $("#CUST_ID").val(rec.CUST_ID || "");
    $("#MAT_USING").val(rec.MAT_USING || "");
    $("#MAT_NAME").val(rec.MAT_NAME || "");

    $("input[name='QUANTITY_TRIAL']").val(rec.QUANTITY_TRIAL);
    $("input[name='TRIAL_REASON']").val(rec.TRIAL_REASON);
    $("input[name='TRIAL_TIMES']").val(rec.TRIAL_TIMES);
    $("input[name='MAT_DRYING_TIME']").val(rec.MAT_DRYING_TIME);
    $("input[name='MOLD_SET_UP']").val(rec.MOLD_SET_UP);
    $("input[name='MOLD_SET_DOWN']").val(rec.MOLD_SET_DOWN);
    $("input[name='TRIAL_DURATION']").val(rec.TRIAL_DURATION);
    $("input[name='CYCLE_TIME_ACT']").val(rec.CYCLE_TIME_ACT);
    $("input[name='MAC_NO']").val(rec.MAC_NO);
    $("input[name='TONAGE']").val(rec.TONAGE);
    $("select[name='OPERATION']").val(rec.OPERATION || "AUTO ROBOT");
    $("input[name='REGRIND_PCT']").val(rec.REGRIND_PCT || 0);

    let checks = ['BURRY','VOID','SHORTMOLD','WELDLINE','BURNING','SINKMARK','DENTED','SILVER','SCRATCH'];
    checks.forEach(c => {
        let field = 'CHK_' + c;
        if(rec[field]) $("#" + field).val(rec[field]);
    });

    $("textarea[name='QE_COMMENT']").val(rec.QE_COMMENT);
    $("textarea[name='PE_COMMENT']").val(rec.PE_COMMENT);
    $("textarea[name='CORRECTIVE_ACTION']").val(rec.CORRECTIVE_ACTION);
    $("textarea[name='ANALYSYS']").val(rec.ANALYSYS);

    $("input[name='PIC']").val(rec.PIC);
    $("input[name='WEIGHT_RUNNER']").val(rec.WEIGHT_RUNNER);
    $("input[name='PREPARED']").val(rec.PREPARED);
    $("input[name='CHECKED']").val(rec.CHECKED);
    $("input[name='APPROVED']").val(rec.APPROVED);
    $("input[name='QTY_OK']").val(rec.QTY_OK);
    $("input[name='QTY_NG']").val(rec.QTY_NG);
    $("select[name='JUDGE_ID']").val(rec.JUDGE_ID);

    updateNav(rec.TRIAL_CODE);
    loadSTD(rec.PART_CODE);
    loadACTgrid(rec.TRIAL_CODE);
}

// Navigasi Tombol
$("#firstBtn").click(function(){ $.get("load_record.php", {mode:"first"}, function(r){ if(r.status==="ok") fillForm(r.record);},"json"); });
$("#lastBtn").click(function(){ $.get("load_record.php", {mode:"last"}, function(r){ if(r.status==="ok") fillForm(r.record);},"json"); });
$("#nextBtn").click(function(){ let code=$("#CURRENT_CODE").val()||0; $.get("load_record.php", {mode:"next", code:code}, function(r){ if(r.status==="ok") fillForm(r.record);},"json"); });
$("#prevBtn").click(function(){ let code=$("#CURRENT_CODE").val()||0; $.get("load_record.php", {mode:"prev", code:code}, function(r){ if(r.status==="ok") fillForm(r.record);},"json"); });

// Load Data STD
function loadSTD(part_code){
    if (!part_code) return;
    $.getJSON("get_std.php", {code: part_code}, function(std){
        $("#STD_WEIGHT_PART").text((std.WEIGHT_PART_STD || "-") + " g");
        $("#STD_WEIGHT_RUNNER").text((std.WEIGHT_RUNNER_STD || "-") + " g");
        $("#STD_CAVITY").text(std.CAVITY_STD || "-");
    });
}

// === FUNGSI BARU: AUTOCOMPLETE CUSTOMER ===
$("#CUST_COMP").autocomplete({
    source: "?ajax_search=cust",
    minLength: 2,
    select: function(event, ui) {
        $("#CUST_COMP").val(ui.item.label);
        $("#CUST_ID").val(ui.item.value);
        return false;
    }
});

// === FUNGSI BARU: AUTOCOMPLETE MATERIAL ===
$("#MAT_NAME").autocomplete({
    source: "?ajax_search=mat",
    minLength: 2,
    select: function(event, ui) {
        $("#MAT_NAME").val(ui.item.label);
        $("#MAT_USING").val(ui.item.value);
        return false;
    }
});

// Autocomplete Part
$("#PART_CODE").autocomplete({
    source: function(req, res){ $.getJSON("search_part.php", {term: req.term}, function(data){ res(data); }); },
    minLength: 2,
    select: function(event, ui){
        $("#PART_CODE").val(ui.item.part_code);
        $("#PART_NAME").val(ui.item.part_name);
        $("#CUST_COMP").val(ui.item.cust_comp || "");
        $("#CUST_ID").val(ui.item.cust_id || "");
        $("#MAT_USING").val(ui.item.mat_id || ""); // Pastikan ini menggunakan mat_id
        $("#MAT_NAME").val(ui.item.mat_name || "");
        loadSTD(ui.item.part_code);
        return false;
    }
});

// Tombol Form Baru
$("#newBtn").click(function(){
    $.getJSON("load_record.php", {mode:"last"}, function(r){
        let newCode = 1;
        if (r.status === "ok") newCode = parseInt(r.record.TRIAL_CODE) + 1;
        $("input[type='text'], input[type='number'], input[type='date']").val("");
        $("textarea").val(""); $("select").val("");
        $("#TRIAL_CODE").val(newCode); $("#CURRENT_CODE").val("");
        $("#ACT_BODY").html("<tr><td colspan='4' class='text-center text-muted py-3'>Simpan Trial dulu untuk input berat aktual.</td></tr>");
        $("input[name='DATE']").val(new Date().toISOString().slice(0, 10)); 
        alert("Form siap untuk diisi.");
    });
});

// ===================== ACT GRID INLINE =====================
function loadACTgrid(trial_code){
    $.getJSON("get_act_list.php", {trial: trial_code}, function(list){
        let TB = $("#ACT_BODY"); TB.empty();
        if (!list || list.length === 0) {
            TB.append("<tr><td colspan='4' class='text-center text-muted py-3 small'>Belum ada data Actual</td></tr>");
            return;
        }
        list.forEach((r, i) => {
            TB.append(`<tr data-id="${r.ID}">
                <td class="text-center">${i+1}</td>
                <td class="editable" data-field="Weight_Part_Actual">${r.Weight_Part_Actual}</td>
                <td class="editable text-center" data-field="CAVITY">${r.CAVITY}</td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-danger py-0 px-2" onclick="deleteACT(${r.ID})"><i class="bi bi-x"></i></button></td>
            </tr>`);
        });
    });
}

$(document).on("click", "#ACT_TABLE td.editable", function () {
    let td = $(this);
    if (td.find("input").length > 0) return;
    let oldVal = td.text().trim(), field = td.data("field"), id = td.closest("tr").data("id");
    let input = $("<input>").val(oldVal).addClass("form-control form-control-sm text-center");
    td.empty().append(input); input.focus();

    input.on("blur", function () { saveCell(id, field, input.val(), oldVal, td); });
    input.on("keydown", function(e){
        if (e.key === "Enter") { e.preventDefault(); input.blur(); }
        if (e.key === "Escape") td.text(oldVal);
    });
});

function saveCell(id, field, value, oldVal, td){
    $.post("save_act_inline.php", {id: id, field: field, value: value}, function(r){
        if (r.status === "ok") {
            td.text(value); td.css("background","#d4ffd4"); setTimeout(()=> td.css("background",""), 400);
        } else {
            td.text(oldVal); td.css("background","#ffd4d4"); setTimeout(()=> td.css("background",""), 600);
            alert("Gagal menyimpan data!");
        }
    },"json");
}

$("#addACTbtn").click(function(){
    let trial = $("#CURRENT_CODE").val();
    if (!trial) { alert("Simpan Data Master Trial terlebih dahulu!"); return; }
    $.post("add_act_inline.php", {trial: trial}, function(r){
        if (r.status === "ok") loadACTgrid(trial);
        else alert("Gagal menambah baris!");
    },"json");
});

function deleteACT(id){
    if (!confirm("Hapus baris ini?")) return;
    $.post("delete_act.php", {id: id}, function(r){
        if (r.status === "ok") loadACTgrid($("#CURRENT_CODE").val());
    },"json");
}

$(document).ready(function(){
    const urlParams = new URLSearchParams(window.location.search);
    if(urlParams.has('code')) {
        let code = urlParams.get('code');
        $.get("load_record.php", {mode: "load", code: code}, function(r){
            if (r.status === "ok") fillForm(r.record);
        },"json");
    }
});
</script>
</body>
</html>
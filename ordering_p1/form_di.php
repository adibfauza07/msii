<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

// =========================================================================
// 1. BLOK PROSES AJAX UPDATE
// =========================================================================
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action    = $_POST['ajax_action'];
    $item_code = trim($_POST['item_code']);
    $value     = trim($_POST['value']);

    if ($item_code === '') {
        echo json_encode(array('status' => 'error', 'msg' => 'Item Code kosong'));
        exit;
    }

    // A. Update STD_PACK_BOX
    if ($action === 'update_std_box') {
        $box_val = floatval($value);
        $sql = "UPDATE dbo.STD_PACK SET STD_PACK_BOX = ? WHERE ITEM_CODE = ?";
        $stmt = sqlsrv_query($conn, $sql, array($box_val, $item_code));
        
        if ($stmt) {
            $rows_affected = sqlsrv_rows_affected($stmt);
            if ($rows_affected === 0) {
                // Jika 0 baris terupdate (Item belum ada di STD_PACK), maka INSERT baru
                $sql_ins = "INSERT INTO dbo.STD_PACK (ITEM_CODE, STD_PACK_BOX) VALUES (?, ?)";
                $stmt_ins = sqlsrv_query($conn, $sql_ins, array($item_code, $box_val));
                
                if ($stmt_ins) {
                    echo json_encode(array('status' => 'success', 'msg' => 'Inserted'));
                } else {
                    echo json_encode(array('status' => 'error', 'msg' => 'Gagal Insert Baru: ' . print_r(sqlsrv_errors(), true)));
                }
            } else {
                echo json_encode(array('status' => 'success', 'msg' => 'Updated'));
            }
        } else {
            echo json_encode(array('status' => 'error', 'msg' => print_r(sqlsrv_errors(), true)));
        }
        exit;
    }

    // B. Update PACK_ID berdasarkan PACK_CODE
    if ($action === 'update_pack_code') {
        $sql_find = "SELECT PACK_ID FROM dbo.PACK WHERE PACK_CODE = ?";
        $stmt_find = sqlsrv_query($conn, $sql_find, array($value));
        $pack_id = null;
        
        if ($stmt_find && $row = sqlsrv_fetch_array($stmt_find, SQLSRV_FETCH_ASSOC)) {
            $pack_id = $row['PACK_ID'];
        }

        if ($pack_id !== null) {
            $sql_upd = "UPDATE dbo.STD_PACK SET PACK_ID = ? WHERE ITEM_CODE = ?";
            $stmt_upd = sqlsrv_query($conn, $sql_upd, array($pack_id, $item_code));
            
            if ($stmt_upd) {
                $rows_affected = sqlsrv_rows_affected($stmt_upd);
                if ($rows_affected === 0) {
                    // Jika 0 baris terupdate (Item belum ada di STD_PACK), maka INSERT baru
                    $sql_ins = "INSERT INTO dbo.STD_PACK (ITEM_CODE, PACK_ID) VALUES (?, ?)";
                    $stmt_ins = sqlsrv_query($conn, $sql_ins, array($item_code, $pack_id));
                    
                    if ($stmt_ins) {
                        echo json_encode(array('status' => 'success', 'msg' => 'Inserted'));
                    } else {
                        echo json_encode(array('status' => 'error', 'msg' => 'Gagal Insert Baru: ' . print_r(sqlsrv_errors(), true)));
                    }
                } else {
                    echo json_encode(array('status' => 'success', 'msg' => 'Updated'));
                }
            } else {
                echo json_encode(array('status' => 'error', 'msg' => print_r(sqlsrv_errors(), true)));
            }
        } else {
            echo json_encode(array('status' => 'error', 'msg' => "Kode Packing '{$value}' tidak ditemukan di tabel Master PACK!"));
        }
        exit;
    }
    exit;
}

// =========================================================================
// 2. FUNGSI BANTUAN
// =========================================================================
function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
function get_param($name, $default = "") {
    if (isset($_GET[$name])) return trim($_GET[$name]);
    if (isset($_POST[$name])) return trim($_POST[$name]);
    return $default;
}
function to_yyyymmdd($value) {
    $value = trim($value);
    if ($value == "") return "";
    if (preg_match('/^\d{8}$/', $value)) return $value;
    $ts = strtotime($value);
    if ($ts === false) return "";
    return date("Ymd", $ts);
}
function fmt_date_id($yyyymmdd) {
    if ($yyyymmdd == "") return "";
    $ts = strtotime($yyyymmdd);
    if ($ts === false) return $yyyymmdd;
    return strtoupper(date("d M Y", $ts));
}
function fmt_print_date() {
    return date("d-M-Y H:i:s");
}
function fmt_num($value, $decimal = 0) {
    if ($value === null || $value === "") $value = 0;
    return number_format((float)$value, $decimal, ".", ",");
}

// =========================================================================
// 3. MASTER DATA UNTUK AUTOCOMPLETE
// =========================================================================
$master_customers = array();
$stmt_cust = sqlsrv_query($conn, "SELECT CUST_CODE, CUST_COMP FROM dbo.CUST ORDER BY CUST_COMP ASC");
if ($stmt_cust !== false) {
    while ($row = sqlsrv_fetch_array($stmt_cust, SQLSRV_FETCH_ASSOC)) {
        $master_customers[] = $row;
    }
}

$master_packs = array();
$stmt_pack = sqlsrv_query($conn, "SELECT PACK_ID, PACK_CODE FROM dbo.PACK ORDER BY PACK_CODE ASC");
if ($stmt_pack !== false) {
    while ($row = sqlsrv_fetch_array($stmt_pack, SQLSRV_FETCH_ASSOC)) {
        $master_packs[] = $row;
    }
}

// =========================================================================
// 4. PARAMETER & EKSEKUSI SP
// =========================================================================
$cust_code  = get_param("CUST_CODE", "");
$start_raw  = get_param("START_DATE", "");
$end_raw    = get_param("END_DATE", "");
$start_date = to_yyyymmdd($start_raw);
$end_date   = to_yyyymmdd($end_raw);

$rows = array();
$pages = array();
$displayStart = "";
$displayEnd = "";
$totalPages = 0;
$rowsPerPage = 20;

if ($cust_code !== "" && $start_date !== "" && $end_date !== "") {
    $sql = "SET NOCOUNT ON; EXEC dbo.SP_DELIVERY_INSTRUCTION_PO2 ?, ?, ?";
    $stmt = sqlsrv_query($conn, $sql, array($cust_code, $start_date, $end_date));
    if ($stmt === false) die("<pre>Query SP_DELIVERY_INSTRUCTION_PO2 gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { $rows[] = $r; }
    
    if (count($rows) > 0) {
        $displayStart = isset($rows[0]["START_DATE_DISPLAY"]) ? trim($rows[0]["START_DATE_DISPLAY"]) : fmt_date_id($start_date);
        $displayEnd   = isset($rows[0]["END_DATE_DISPLAY"]) ? trim($rows[0]["END_DATE_DISPLAY"]) : fmt_date_id($end_date);
    } else {
        $displayStart = fmt_date_id($start_date);
        $displayEnd   = fmt_date_id($end_date);
    }
    
    $customerPages = array();
    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];
        $cCode = isset($r["CUST_CODE"]) ? trim((string)$r["CUST_CODE"]) : "";
        $cComp = isset($r["CUST_COMP"]) ? trim((string)$r["CUST_COMP"]) : "";
        $key = $cCode . "|" . $cComp;
    
        if (!isset($customerPages[$key])) {
            $customerPages[$key] = array("CUST_CODE" => $cCode, "CUST_COMP" => $cComp, "ROWS" => array());
        }
        $customerPages[$key]["ROWS"][] = array(
            "ITEM_CODE"    => isset($r["PART_NUM"]) ? trim((string)$r["PART_NUM"]) : "",
            "ITEM_NO"      => isset($r["PART_NO"]) ? trim((string)$r["PART_NO"]) : "",
            "ITEM_NAME"    => isset($r["PART_NAME"]) ? trim((string)$r["PART_NAME"]) : "",
            "PBQTY"        => isset($r["PBQTY"]) ? $r["PBQTY"] : 0,
            "PLAN_QTY"     => isset($r["PLAN_QTY"]) ? $r["PLAN_QTY"] : 0,
            "PO"           => isset($r["PO"]) ? trim((string)$r["PO"]) : "",
            "STD_PACK_BOX" => isset($r["STD_PACK_BOX"]) ? $r["STD_PACK_BOX"] : 0,
            "STD_BOX"      => isset($r["STD_BOX"]) ? $r["STD_BOX"] : 0,
            "PACK_CODE"    => isset($r["PACK_CODE"]) ? trim((string)$r["PACK_CODE"]) : ""
        );
    }
    
    foreach ($customerPages as $page) { $pages[] = $page; }
    if (count($pages) == 0) { $pages[] = array("CUST_CODE" => $cust_code, "CUST_COMP" => "", "ROWS" => array()); }
    $totalPages = count($pages);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delivery Instruction | PPIC</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- AdminLTE Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

    <style>
        /* ============================================================
           CSS KHUSUS UNTUK AREA PRINT & TABEL REPORT
           ============================================================ */
        @page { size: A4 landscape; margin: 6mm; }
        
        .report-container {
            background: #9a9a9a;
            padding: 20px;
            font-family: "Courier New", monospace;
            font-size: 10px;
            color: #000000;
            overflow-x: auto;
        }

        .page { width: 285mm; min-height: 198mm; margin: 10px auto; background: #ffffff; border: 2px solid #000000; padding: 5mm; box-sizing: border-box; page-break-after: always; overflow: hidden; }
        .page:last-child { page-break-after: auto; }
        
        .header { width: 100%; border-collapse: collapse; margin-bottom: 3px; }
        .header td { border: none; vertical-align: top; }
        .company { width: 32%; font-family: Arial, sans-serif; font-size: 11px; line-height: 13px; }
        .company-title { font-size: 14px; font-weight: normal; }
        .title-area { width: 40%; text-align: center; font-family: Arial, sans-serif; }
        .report-title { font-size: 21px; font-weight: normal; margin-top: 16px; margin-bottom: 5px; }
        .period { font-size: 12px; }
        .right-info { width: 28%; text-align: right; font-family: Arial, sans-serif; font-size: 11px; line-height: 16px; }
        .form-no { font-weight: normal; }
        .page-no { margin-top: 4px; }
        .print-date { text-align: right; font-size: 11px; font-family: Arial, sans-serif; margin-top: 0; margin-bottom: 3px; }
        .customer-title { width: 100%; font-family: "Courier New", monospace; font-size: 12px; font-weight: bold; margin-top: 2px; margin-bottom: 4px; padding-left: 2px; box-sizing: border-box; }
        
        .di-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .di-table th, .di-table td { border: 1px solid #000000; padding: 2px 3px; height: 20px; line-height: 12px; box-sizing: border-box; vertical-align: middle; white-space: nowrap; overflow: hidden; text-overflow: clip; }
        .di-table th { font-weight: normal; text-align: center; }
        .empty-line td { height: 20px; }
        
        .num { text-align: right; }
        .center { text-align: center; }
        .left { text-align: left; padding-left: 5px !important; }

        .input-cell { padding: 0 !important; }
        .input-edit { width: 100%; height: 100%; box-sizing: border-box; border: none; background: #fff9c4; text-align: center; font-family: inherit; font-size: inherit; outline: none; transition: background 0.3s;}
        .input-edit:focus { background: #ffffff; border: 1px dashed #333;}
        
        .signature { width: 100%; margin-top: 8px; font-size: 11px; }
        .signature td { border: none; }
        .sig-right { text-align: right; padding-right: 55px; font-style: italic; }

        .col-item-code { width: 9%; } .col-item-no { width: 15%; } .col-item-name { width: 20%; }
        .col-lot-no { width: 7%; } .col-po-bal { width: 7%; } .col-plan { width: 7%; }
        .col-pcs { width: 6%; } .col-pack { width: 6%; } .col-initial { width: 6%; }
        .col-total { width: 7%; } .col-remark { width: 7%; } .col-check { width: 3%; }

        /* Mencegah elemen UI AdminLTE tercetak saat CTRL+P */
        @media print {
            body, html { width: 297mm; height: 210mm; background: #ffffff; margin: 0; padding: 0; }
            .no-print, .main-header, .main-footer, .card-header, .content-header { display: none !important; }
            .content-wrapper, .report-container { background: transparent !important; padding: 0 !important; }
            .page { width: 285mm; min-height: 198mm; margin: 0 auto; border: none; padding: 0; overflow: hidden; }
            .input-edit { background: transparent !important; }
            .di-table th, .di-table td { height: 20px; line-height: 12px; font-size: 10px; padding: 2px 3px; }
            .report-title { font-size: 21px; } .company-title { font-size: 14px; }
        }
    </style>
</head>
<body class="hold-transition layout-top-nav">
<div class="wrapper">

    <!-- DATALIST UNTUK AUTOCOMPLETE -->
    <datalist id="pack-list">
        <?php foreach($master_packs as $p): ?>
            <option value="<?php echo h($p['PACK_CODE']); ?>">
        <?php endforeach; ?>
    </datalist>

    <datalist id="cust-list">
        <?php foreach($master_customers as $c): ?>
            <option value="<?php echo h($c['CUST_CODE']); ?>"><?php echo h($c['CUST_COMP']); ?></option>
        <?php endforeach; ?>
    </datalist>

    <!-- Navbar AdminLTE -->
    <nav class="main-header navbar navbar-expand-md navbar-dark bg-primary no-print">
        <div class="container-fluid">
            <a href="#" class="navbar-brand">
                <span class="brand-text font-weight-light">P.T. IMC TEKNO INDONESIA</span>
            </a>
        </div>
    </nav>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <div class="content-header no-print">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Delivery Instruction (PPIC)</h1>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="content">
            <div class="container-fluid">
                
                <!-- KOTAK FILTER PENCARIAN -->
                <div class="card card-default no-print">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-search"></i> Filter Data</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Customer</label>
                                        <input type="text" name="CUST_CODE" list="cust-list" class="form-control" value="<?php echo h($cust_code); ?>" placeholder="Ketik kode/nama..." required autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Start Date</label>
                                        <input type="date" name="START_DATE" class="form-control" value="<?php echo h($start_raw); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>End Date</label>
                                        <input type="date" name="END_DATE" class="form-control" value="<?php echo h($end_raw); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="form-group w-100">
                                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-sync"></i> LOAD</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- HASIL LAPORAN (REPORT) -->
                <?php if (count($pages) > 0): ?>
                    <div class="card card-default">
                        <div class="card-header no-print">
                            <h3 class="card-title"><i class="fas fa-file-alt"></i> Area Cetak Laporan</h3>
                            <div class="card-tools">
                                <span class="text-muted mr-3"><i class="fas fa-lightbulb text-warning"></i> Tips: Edit sel lalu pindah baris/tekan <strong>Enter</strong> untuk Auto-Save.</span>
                                <button type="button" class="btn btn-success btn-sm" onclick="window.print()"><i class="fas fa-print"></i> PRINT</button>
                            </div>
                        </div>
                        
                        <div class="card-body p-0">
                            <!-- Container spesifik untuk render kertas/A4 -->
                            <div class="report-container">
                                
                                <?php for ($p = 0; $p < count($pages); $p++): ?>
                                    <?php
                                        $pageNo     = $p + 1;
                                        $pageData   = $pages[$p];
                                        $cCode      = $pageData["CUST_CODE"];
                                        $cComp      = $pageData["CUST_COMP"];
                                        $detailRows = $pageData["ROWS"];
                                    ?>
                                    <div class="page">
                                        <table class="header">
                                            <tr>
                                                <td class="company">
                                                    <div class="company-title">P.T. IMC TEKNO INDONESIA</div>PPIC Department
                                                </td>
                                                <td class="title-area">
                                                    <div class="report-title">DELIVERY INSTRUCTION</div>
                                                    <div class="period"><?php echo h($displayStart); ?> &nbsp;&nbsp; - &nbsp;&nbsp; <?php echo h($displayEnd); ?></div>
                                                </td>
                                                <td class="right-info">
                                                    <div class="form-no">FM.CO.00-06</div><div class="page-no">Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?></div>
                                                </td>
                                            </tr>
                                        </table>

                                        <div class="print-date">Print Date : &nbsp; <?php echo h(fmt_print_date()); ?></div>
                                        <div class="customer-title"><?php echo h($cCode); ?> &nbsp;&nbsp; <?php echo h($cComp); ?></div>

                                        <table class="di-table">
                                            <thead>
                                                <tr>
                                                    <th rowspan="3" class="col-item-code">Item Code</th><th rowspan="3" class="col-item-no">Item No</th><th rowspan="3" class="col-item-name">Item Name</th>
                                                    <th rowspan="3" class="col-lot-no">Lot No</th><th rowspan="3" class="col-po-bal">PO.Bal</th><th rowspan="3" class="col-plan">Del.Plan</th>
                                                    <th colspan="4">Actual Qty</th><th rowspan="3" class="col-remark">REMARK</th><th rowspan="3" class="col-check">loading<br>check</th>
                                                </tr>
                                                <tr><th rowspan="2" class="col-pcs">actual</th><th rowspan="2" class="col-pack">std_box</th><th class="col-initial">Initial</th><th rowspan="2" class="col-total">Total</th></tr>
                                                <tr><th class="col-initial">packing</th></tr>
                                            </thead>
                                            <tbody>
                                                <?php if (count($detailRows) == 0): ?>
                                                    <tr><td colspan="12" class="center">Data tidak ditemukan.</td></tr>
                                                <?php endif; ?>

                                                <?php 
                                                    $total_po_bal = 0; $total_plan = 0; $total_box = 0;
                                                    for ($i = 0; $i < count($detailRows); $i++): 
                                                        $r = $detailRows[$i]; 
                                                        $jml_box = (!empty($r["STD_PACK_BOX"]) && $r["STD_PACK_BOX"] > 0) ? ceil($r["PLAN_QTY"] / $r["STD_PACK_BOX"]) : 0;
                                                        $total_po_bal += $r["PBQTY"]; $total_plan += $r["PLAN_QTY"]; $total_box += $jml_box;
                                                ?>
                                                    <tr>
                                                        <td class="col-item-code"><?php echo h($r["ITEM_CODE"]); ?></td>
                                                        <td class="col-item-no"><?php echo h($r["ITEM_NO"]); ?></td>
                                                        <td class="col-item-name"><?php echo h($r["ITEM_NAME"]); ?></td>
                                                        <td class="col-lot-no">&nbsp;</td>
                                                        <td class="col-po-bal center"><?php echo h(fmt_num($r["PBQTY"], 0)); ?></td>
                                                        <td class="col-plan center"><?php echo h(fmt_num($r["PLAN_QTY"], 0)); ?></td>
                                                        <td class="col-pcs"></td>
                                                        
                                                        <td class="col-pack center input-cell">
                                                            <input type="number" step="any" class="input-edit std-box-trigger" 
                                                                   data-item="<?php echo h($r["ITEM_CODE"]); ?>" 
                                                                   data-plan="<?php echo h($r["PLAN_QTY"]); ?>" 
                                                                   value="<?php echo h($r["STD_PACK_BOX"]); ?>">
                                                        </td>
                                                        
                                                        <td class="col-initial center input-cell">
                                                            <input type="text" list="pack-list" class="input-edit pack-code-trigger" 
                                                                   data-item="<?php echo h($r["ITEM_CODE"]); ?>" 
                                                                   value="<?php echo h($r["PACK_CODE"]); ?>">
                                                        </td>
                                                        
                                                        <td class="col-total center calc-total-box"><?php echo h(fmt_num($jml_box, 0)); ?></td>
                                                        <td class="col-remark">&nbsp;</td><td class="col-check"></td>
                                                    </tr>
                                                <?php endfor; ?>

                                                <?php
                                                    $usedRows = (count($detailRows) == 0) ? 1 : count($detailRows);
                                                    $fillCount = max(0, $rowsPerPage - $usedRows - 1);
                                                    for ($e = 0; $e < $fillCount; $e++): 
                                                ?>
                                                    <tr class="empty-line">
                                                        <td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                                                    </tr>
                                                <?php endfor; ?>
                                                
                                                <tr>
                                                    <td colspan="4" class="center"><strong>TOTAL</strong></td>
                                                    <td class="center"><strong><?php echo h(fmt_num($total_po_bal, 0)); ?></strong></td>
                                                    <td class="center"><strong><?php echo h(fmt_num($total_plan, 0)); ?></strong></td>
                                                    <td></td><td></td><td></td>
                                                    <td class="center"><strong><?php echo h(fmt_num($total_box, 0)); ?></strong></td>
                                                    <td></td><td></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <table class="signature">
                                            <tr><td></td><td class="sig-right">[Checked by]</td><td class="sig-right">[Prepared by]</td></tr>
                                        </table>
                                    </div>
                                <?php endfor; ?>

                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<!-- jQuery (Bawaan Wajib AdminLTE) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<!-- AJAX SCRIPT UNTUK UPDATE DATA DENGAN ENTER ATAU PINDAH BARIS -->
<script>
$(document).ready(function() {
    
    // 1. Mendeteksi event 'change' (aktif saat nilai diubah & kursor pindah)
    $(document).on('change', '.std-box-trigger, .pack-code-trigger', function() {
        var inputElem = $(this);
        var actionName = inputElem.hasClass('std-box-trigger') ? 'update_std_box' : 'update_pack_code';
        
        doAjaxUpdate(inputElem, actionName);
    });

    // 2. Mendeteksi Enter
    $(document).on('keydown', '.std-box-trigger, .pack-code-trigger', function(e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            $(this).blur(); // Memicu event 'change' di atas
        }
    });

    // Fungsi utama AJAX
    function doAjaxUpdate(inputElem, actionName) {
        var itemCode = inputElem.data('item');
        var newValue = inputElem.val();
        
        // Indikator loading (Abu-abu)
        inputElem.css('background-color', '#e0e0e0');

        $.ajax({
            url: window.location.href, // Target URL mengikuti url browser saat ini (menghindari masalah iframe)
            type: 'POST',
            dataType: 'json',
            data: {
                ajax_action: actionName,
                item_code: itemCode,
                value: newValue
            },
            success: function(response) {
                console.log("Respon Status:", response.status, "| Pesan:", response.msg);
                if (response.status === 'success') {
                    // Berhasil -> hijau sebentar, lalu kuning lagi
                    inputElem.css('background-color', '#c8e6c9');
                    setTimeout(function() { inputElem.css('background-color', '#fff9c4'); }, 1500);

                    // Update UI Total Box secara Realtime
                    if (actionName === 'update_std_box') {
                        var planQty = parseFloat(inputElem.data('plan')) || 0;
                        var stdBox  = parseFloat(newValue) || 0;
                        var newTotal = 0;
                        
                        if (stdBox > 0) {
                            newTotal = Math.ceil(planQty / stdBox);
                        }
                        
                        inputElem.closest('tr').find('.calc-total-box').text(newTotal.toLocaleString('en-US'));
                    }
                } else {
                    inputElem.css('background-color', '#ffcdd2');
                    alert('GAGAL UPDATE: \n' + response.msg);
                }
            },
            error: function(xhr, status, error) {
                inputElem.css('background-color', '#ffcdd2');
                alert('Terjadi kesalahan jaringan atau response bukan JSON valid. Cek console tab.');
                console.log(xhr.responseText);
            }
        });
    }
});
</script>

</body>
</html>
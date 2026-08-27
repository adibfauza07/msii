<?php
// Pastikan config.php sudah mendefinisikan $conn (budget) dan $conn_msdata (msdata)
require_once 'config.php';

// Fungsi untuk mencegah XSS (Kompatibel PHP 5.4)
function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

$message = "";

// ==========================================================
// 1. PROSES ACTION (Approve/Cancel, Close Manual, Sync Massal)
// ==========================================================
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    // --- Action Jalur BUDGETING (Approve/Cancel) ---
    if (($action === 'cancel' || $action === 'approve_finance') && isset($_GET['pr_no'])) {
        $target_pr_no = trim($_GET['pr_no']);
        $sql_check = "SELECT status FROM PR_Header WHERE pr_no = ?";
        $stmt_check = sqlsrv_query($conn, $sql_check, array($target_pr_no));
        
        if ($stmt_check && sqlsrv_has_rows($stmt_check)) {
            $row_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
            if ($row_check['status'] === 'PENDING') {
                $new_status = ($action === 'cancel') ? 'CANCELED' : 'APPROVED';
                $sql_upd = "UPDATE PR_Header SET status = ? WHERE pr_no = ?";
                if (sqlsrv_query($conn, $sql_upd, array($new_status, $target_pr_no))) {
                    $message = "<div class='alert alert-success alert-dismissible'><button type='button' class='close' data-dismiss='alert'>&times;</button><i class='fas fa-check-circle'></i> Purchase Request <strong>".h($target_pr_no)."</strong> berhasil diubah menjadi ".h($new_status).".</div>";
                }
            } else {
                $message = "<div class='alert alert-warning'><i class='fas fa-lock'></i> PR <strong>".h($target_pr_no)."</strong> tidak dapat diproses (Status: ".h($row_check['status']).").</div>";
            }
        }
    }
    
    // --- Action Jalur MSDATA (Force Close Per Baris) ---
    if ($action === 'close_pr_msdata' && isset($_GET['req_id'])) {
        $req_id = (int)$_GET['req_id'];
        if ($req_id > 0) {
            $sql_close = "UPDATE REQUISITION SET REQ_CLOSE = 1 WHERE REQ_ID = ?";
            if (sqlsrv_query($conn_msdata, $sql_close, array($req_id))) {
                $message = "<div class='alert alert-success alert-dismissible'><button type='button' class='close' data-dismiss='alert'>&times;</button><i class='fas fa-check-circle'></i> Dokumen PR (Requisition) berhasil ditutup secara manual (CLOSED).</div>";
            } else {
                $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Gagal menutup dokumen PR.</div>";
            }
        }
    }

    // --- Action Jalur MSDATA (Sinkronisasi Massal via SP) ---
    if ($action === 'sync_pr_msdata') {
        $sql_sync = "{CALL SP_Sync_AutoClose_PR}";
        $stmt_sync = sqlsrv_query($conn_msdata, $sql_sync);
        
        if ($stmt_sync) {
            $message = "<div class='alert alert-success alert-dismissible shadow-sm'><button type='button' class='close' data-dismiss='alert'>&times;</button><i class='fas fa-sync fa-spin'></i> <strong>Sinkronisasi Massal Berhasil!</strong> Seluruh PR yang Qty-nya telah terpenuhi oleh PO kini berstatus CLOSED.</div>";
        } else {
            $err_msg = "";
            $errors = sqlsrv_errors();
            if ($errors != null) { foreach ($errors as $error) { $err_msg .= $error['message'] . "<br>"; } }
            $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Gagal Sinkronisasi: " . $err_msg . "</div>";
        }
    }
}

// ==========================================================
// 2. LOAD DATA COMBO BOX (DEPARTEMEN DINAMIS)
// ==========================================================
$source        = isset($_GET['source']) ? trim($_GET['source']) : 'budget';
$filter_plant  = isset($_GET['plant_id']) ? trim($_GET['plant_id']) : '';
$filter_dept   = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';
$filter_search = isset($_GET['search']) ? trim($_GET['search']) : ''; 
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : 'ALL'; 

$opt_dept = '<option value="">-- Pilih Departemen --</option>';

if ($source === 'budget') {
    $sql_m_dept = "SELECT department_id, department_name FROM Master_Department ORDER BY department_name ASC";
    $stmt_m_dept = sqlsrv_query($conn, $sql_m_dept);
    if ($stmt_m_dept) {
        while ($d = sqlsrv_fetch_array($stmt_m_dept, SQLSRV_FETCH_ASSOC)) {
            $selected = ($filter_dept === (string)$d['department_id']) ? 'selected' : '';
            $opt_dept .= '<option value="'.h($d['department_id']).'" '.$selected.'>'.h($d['department_name']).'</option>';
        }
    }
} else {
    $sql_m_dept = "SELECT DEP_ID, DEP_CODE, DEP_NAME FROM DEPT ORDER BY DEP_NAME ASC";
    $stmt_m_dept = sqlsrv_query($conn_msdata, $sql_m_dept);
    if ($stmt_m_dept) {
        while ($d = sqlsrv_fetch_array($stmt_m_dept, SQLSRV_FETCH_ASSOC)) {
            $selected = ($filter_dept === (string)$d['DEP_ID']) ? 'selected' : '';
            $opt_dept .= '<option value="'.(int)$d['DEP_ID'].'" '.$selected.'>'.h($d['DEP_CODE'].' - '.$d['DEP_NAME']).'</option>';
        }
    }
}

// ==========================================================
// 3. FILTER & PENCARIAN (MANDATORY FILTER)
// ==========================================================
$params = array();
$is_filtered = ($filter_dept !== '' || $filter_search !== '');
$stmt_data = false;

if ($is_filtered) {
    if ($source === 'budget') {
        $sql_data = "
            SELECT 
                PR_Header.pr_no, PR_Header.pr_date, PR_Header.plant_id, PR_Header.department_id, 
                PR_Header.total_amount, PR_Header.status, PR_Header.created_by,
                PR_Detail.item_code, PR_Detail.qty_request, PR_Detail.unit_price, PR_Detail.subtotal
            FROM PR_Header
            INNER JOIN PR_Detail ON PR_Header.pr_no = PR_Detail.pr_no
            WHERE 1=1
        ";
        if ($filter_plant !== '') { $sql_data .= " AND PR_Header.plant_id = ?"; $params[] = $filter_plant; }
        if ($filter_dept !== '') { $sql_data .= " AND PR_Header.department_id = ?"; $params[] = $filter_dept; }
        if ($filter_search !== '') { 
            $sql_data .= " AND (PR_Header.pr_no LIKE ? OR PR_Detail.item_code LIKE ?)"; 
            $params[] = "%" . $filter_search . "%"; $params[] = "%" . $filter_search . "%"; 
        }
        
        if ($filter_status === 'OPEN') { $sql_data .= " AND PR_Header.status IN ('PENDING', 'APPROVED')"; } 
        elseif ($filter_status === 'CLOSED') { $sql_data .= " AND PR_Header.status = 'CANCELED'"; }

        $sql_data .= " ORDER BY PR_Header.pr_date DESC, PR_Header.pr_no DESC";
        $stmt_data = sqlsrv_query($conn, $sql_data, $params);
    } 
    else {
        $sql_data = "
            SELECT 
                r.REQ_ID, r.REQ_NO, r.REQ_DATE, r.REQ_CLOSE,
                d.DEP_NAME, d.DEP_CODE,
                rd.REQD_QTY, rd.REQD_REM,
                i.ITEM_ID, i.ITEM_CODE, i.ITEM_NAME, i.ITEM_UNIT
            FROM REQUISITION r
            LEFT JOIN DEPT d ON r.DEP_ID = d.DEP_ID
            INNER JOIN REQ_DETAIL rd ON r.REQ_ID = rd.REQ_ID
            INNER JOIN ITEMS i ON rd.ITEM_ID = i.ITEM_ID
            WHERE 1=1
        ";
        if ($filter_dept !== '') { $sql_data .= " AND r.DEP_ID = ?"; $params[] = (int)$filter_dept; }
        if ($filter_search !== '') { 
            $sql_data .= " AND (r.REQ_NO LIKE ? OR i.ITEM_CODE LIKE ? OR i.ITEM_NAME LIKE ?)"; 
            $params[] = "%" . $filter_search . "%"; $params[] = "%" . $filter_search . "%"; $params[] = "%" . $filter_search . "%"; 
        }

        if ($filter_status === 'OPEN') { $sql_data .= " AND r.REQ_CLOSE = 0"; } 
        elseif ($filter_status === 'CLOSED') { $sql_data .= " AND r.REQ_CLOSE = 1"; }

        $sql_data .= " ORDER BY r.REQ_DATE DESC, r.REQ_NO DESC";
        $stmt_data = sqlsrv_query($conn_msdata, $sql_data, $params);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Purchase Request (Hybrid) - ERP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { background-color: #f4f6f9; font-family: Tahoma, sans-serif; font-size: 13px; padding: 20px; }
        .table th, .table td { vertical-align: middle; white-space: nowrap; }
        .empty-state { padding: 40px 20px; text-align: center; color: #6c757d; }
        .empty-state i { font-size: 48px; color: #ced4da; margin-bottom: 15px; }
        .select2-container .select2-selection--single { height: 38px !important; border: 1px solid #ced4da !important; border-radius: 4px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; }
        @media print {
            .no-print { display: none !important; }
            body { background-color: #ffffff; padding: 0; }
        }
    </style>
</head>
<body>

    <div class="container-fluid">
        <?php echo $message; ?>

        <!-- FILTER HYBRID -->
        <div class="card shadow-sm mb-4 no-print border-primary">
            <div class="card-header bg-dark text-white font-weight-bold">
                <i class="fas fa-filter"></i> Laporan & Pelacakan Purchase Request (PR)
            </div>
            <div class="card-body bg-light">
                <form method="GET" action="">
                    
                    <div class="form-row mb-3">
                        <div class="col-md-3">
                            <label class="font-weight-bold text-primary">Pilih Sumber Data:</label>
                            <select name="source" class="form-control font-weight-bold" onchange="this.form.submit()">
                                <option value="budget" <?php echo ($source === 'budget') ? 'selected' : ''; ?>>Jalur Budgeting (Non-PO)</option>
                                <option value="msdata" <?php echo ($source === 'msdata') ? 'selected' : ''; ?>>Jalur Utama (PO - MSDATA)</option>
                            </select>
                        </div>
                        
                        <?php if ($source === 'budget'): ?>
                        <div class="col-md-2">
                            <label class="font-weight-bold">Plant:</label>
                            <select name="plant_id" class="form-control" onchange="this.form.submit()">
                                <option value="">Semua Plant</option>
                                <option value="P1" <?php echo ($filter_plant === 'P1') ? 'selected' : ''; ?>>P1</option>
                                <option value="P2" <?php echo ($filter_plant === 'P2') ? 'selected' : ''; ?>>P2</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-4">
                            <label class="font-weight-bold">Department <span class="text-danger">*</span>:</label>
                            <select name="department_id" class="form-control select2-dept" onchange="this.form.submit()">
                                <?php echo $opt_dept; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="font-weight-bold">Status Dokumen:</label>
                            <select name="status" class="form-control border-info" onchange="this.form.submit()">
                                <option value="ALL" <?php echo ($filter_status === 'ALL') ? 'selected' : ''; ?>>Semua Status</option>
                                <option value="OPEN" <?php echo ($filter_status === 'OPEN') ? 'selected' : ''; ?>>OPEN</option>
                                <option value="CLOSED" <?php echo ($filter_status === 'CLOSED') ? 'selected' : ''; ?>>CLOSED</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-inline">
                        <input type="text" name="search" class="form-control border-primary mr-2" style="width: 350px;" placeholder="Cari Nomor PR atau Kode/Nama Barang..." value="<?php echo h($filter_search); ?>">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Tampilkan Data</button>
                        <a href="pr_report.php?source=<?php echo h($source); ?>" class="btn btn-secondary ml-2"><i class="fas fa-sync"></i> Reset</a>
                        
                        <!-- TOMBOL SINKRONISASI MASSAL (Hanya muncul jika sumber data adalah msdata) -->
                        <?php if ($source === 'msdata'): ?>
                            <a href="?source=msdata&action=sync_pr_msdata&department_id=<?php echo urlencode($filter_dept); ?>&status=<?php echo urlencode($filter_status); ?>" 
                               class="btn btn-warning ml-auto font-weight-bold shadow-sm" 
                               onclick="return confirm('Proses ini akan mengecek seluruh PR yang OPEN dan menutupnya secara otomatis jika Qty PO sudah terpenuhi. Lanjutkan?');">
                               <i class="fas fa-magic"></i> Sinkronisasi Massal Status PR
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- TABEL HASIL (DINAMIS BERDASARKAN SUMBER) -->
        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white font-weight-bold">
                <i class="fas fa-list"></i> Data Laporan - <?php echo ($source === 'budget') ? 'BUDGETING' : 'MSDATA'; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover mb-0">
                        
                        <?php if ($source === 'budget'): ?>
                        <!-- ============================================== -->
                        <!-- FORMAT TABEL: BUDGETING (NON-PO)               -->
                        <!-- ============================================== -->
                        <thead class="thead-light">
                            <tr>
                                <th>PR No</th>
                                <th>Tanggal</th>
                                <th class="text-center">Plant / Dept</th>
                                <th>Item Code</th>
                                <th class="text-center">Qty Req</th>
                                <th class="text-right">Unit Price</th>
                                <th class="text-right">Subtotal</th>
                                <th class="text-center">Status</th>
                                <th class="text-center" width="12%">Aksi / Approval</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (!$is_filtered) {
                                echo "<tr><td colspan='9'><div class='empty-state'><i class='fas fa-search'></i><br>Silakan pilih <strong>Department</strong> pada Combo Box di atas.</div></td></tr>";
                            } else {
                                if ($stmt_data !== false) {
                                    $has_data = false;
                                    while ($row = sqlsrv_fetch_array($stmt_data, SQLSRV_FETCH_ASSOC)) {
                                        $has_data = true;
                                        $pr_date = $row['pr_date'] instanceof DateTime ? $row['pr_date']->format('d-m-Y') : $row['pr_date'];
                            ?>
                                    <tr>
                                        <td><strong><?php echo h($row['pr_no']); ?></strong></td>
                                        <td><?php echo $pr_date; ?></td>
                                        <td class="text-center"><?php echo h($row['plant_id'] . ' / ' . $row['department_id']); ?></td>
                                        <td><?php echo h($row['item_code']); ?></td>
                                        <td class="text-center font-weight-bold"><?php echo number_format($row['qty_request'], 2, ',', '.'); ?></td>
                                        <td class="text-right"><?php echo number_format($row['unit_price'], 2, ',', '.'); ?></td>
                                        <td class="text-right font-weight-bold text-success"><?php echo number_format($row['subtotal'], 2, ',', '.'); ?></td>
                                        <td class="text-center">
                                            <?php 
                                                if ($row['status'] === 'APPROVED') echo '<span class="badge badge-success">APPROVED</span>';
                                                elseif ($row['status'] === 'CANCELED') echo '<span class="badge badge-danger">CANCELED</span>';
                                                else echo '<span class="badge badge-warning">'.h($row['status']).'</span>';
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="report_pr.php?pr_no=<?php echo urlencode($row['pr_no']); ?>" target="_blank" class="btn btn-sm btn-info" title="Cetak PR"><i class="fas fa-print"></i></a>
                                                <?php if ($row['status'] === 'PENDING'): ?>
                                                    <a href="?source=budget&action=approve_finance&pr_no=<?php echo urlencode($row['pr_no']); ?>&department_id=<?php echo urlencode($filter_dept); ?>&status=<?php echo urlencode($filter_status); ?>" class="btn btn-sm btn-success" onclick="return confirm('Setujui PR ini?');" title="Approve Finance"><i class="fas fa-check-double"></i></a>
                                                    <a href="?source=budget&action=cancel&pr_no=<?php echo urlencode($row['pr_no']); ?>&department_id=<?php echo urlencode($filter_dept); ?>&status=<?php echo urlencode($filter_status); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Batalkan PR ini?');" title="Batalkan PR"><i class="fas fa-times"></i></a>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-secondary" disabled><i class="fas fa-check-double"></i></button>
                                                    <button class="btn btn-sm btn-secondary" disabled><i class="fas fa-times"></i></button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                            <?php 
                                    }
                                    if (!$has_data) echo "<tr><td colspan='9' class='text-center text-danger font-weight-bold py-4'>Data tidak ditemukan.</td></tr>";
                                }
                            }
                            ?>
                        </tbody>

                        <?php else: ?>
                        <!-- ============================================== -->
                        <!-- FORMAT TABEL: MSDATA (PO TRACKING)             -->
                        <!-- ============================================== -->
                        <thead class="thead-light">
                            <tr>
                                <th>PR / Req No</th>
                                <th>Tanggal</th>
                                <th>Departemen</th>
                                <th>Kode Barang</th>
                                <th>Nama Barang</th>
                                <th class="text-center">Qty Req</th>
                                <th class="text-center">Status PR</th>
                                <th class="text-center" width="18%">Aksi & Pelacakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (!$is_filtered) {
                                echo "<tr><td colspan='8'><div class='empty-state'><i class='fas fa-search'></i><br>Silakan pilih <strong>Department</strong> pada Combo Box di atas.</div></td></tr>";
                            } else {
                                if ($stmt_data !== false) {
                                    $has_data = false;
                                    while ($row = sqlsrv_fetch_array($stmt_data, SQLSRV_FETCH_ASSOC)) {
                                        $has_data = true;
                                        $req_date = $row['REQ_DATE'] instanceof DateTime ? $row['REQ_DATE']->format('d-m-Y') : date('d-m-Y', strtotime($row['REQ_DATE']));
                            ?>
                                    <tr>
                                        <td><strong class="text-primary"><?php echo h($row['REQ_NO']); ?></strong></td>
                                        <td><?php echo $req_date; ?></td>
                                        <td><?php echo h($row['DEP_NAME'] ? $row['DEP_NAME'] : $row['DEP_CODE']); ?></td>
                                        <td><strong><?php echo h($row['ITEM_CODE']); ?></strong></td>
                                        <td><?php echo h($row['ITEM_NAME']); ?></td>
                                        <td class="text-center font-weight-bold text-success"><?php echo number_format($row['REQD_QTY'], 2, ',', '.'); ?> <?php echo h($row['ITEM_UNIT']); ?></td>
                                        <td class="text-center">
                                            <?php echo $row['REQ_CLOSE'] ? '<span class="badge badge-secondary">CLOSED</span>' : '<span class="badge badge-success">OPEN</span>'; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <!-- TOMBOL CETAK PR MSDATA -->
        <a href="report_pr_msdata.php?req_no=<?php echo urlencode($row['REQ_NO']); ?>" target="_blank" class="btn btn-sm btn-info font-weight-bold" title="Cetak Dokumen PR">
            <i class="fas fa-print"></i> Cetak
        </a>
												<button type="button" class="btn btn-sm btn-outline-info font-weight-bold btn-track-po" 
                                                        data-reqid="<?php echo (int)$row['REQ_ID']; ?>" 
                                                        data-itemid="<?php echo (int)$row['ITEM_ID']; ?>"
                                                        data-itemname="<?php echo h($row['ITEM_NAME']); ?>">
                                                    <i class="fas fa-search"></i> Cek PO
                                                </button>
                                                
                                                <!-- TOMBOL FORCE CLOSE -->
                                                <?php if ($row['REQ_CLOSE'] == 0): ?>
                                                    <a href="?source=msdata&action=close_pr_msdata&req_id=<?php echo (int)$row['REQ_ID']; ?>&department_id=<?php echo urlencode($filter_dept); ?>&status=<?php echo urlencode($filter_status); ?>" 
                                                       class="btn btn-sm btn-warning font-weight-bold" 
                                                       onclick="return confirm('Tutup PR ini secara manual? Transaksi PO tidak bisa dilanjutkan untuk PR ini.');" 
                                                       title="Force Close PR">
                                                       <i class="fas fa-lock"></i> Tutup
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-secondary font-weight-bold" disabled><i class="fas fa-lock"></i> Tutup</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                            <?php 
                                    }
                                    if (!$has_data) echo "<tr><td colspan='8' class='text-center text-danger font-weight-bold py-4'>Data tidak ditemukan.</td></tr>";
                                }
                            }
                            ?>
                        </tbody>
                        <?php endif; ?>

                    </table>
                </div>
            </div>
        </div>
    </div>

<!-- MODAL PELACAKAN PO -->
<div class="modal fade" id="modalPO" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-info text-white">
                <h6 class="modal-title font-weight-bold"><i class="fas fa-truck-loading"></i> Histori Pelacakan Purchase Order (PO)</h6>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body bg-light">
                <div class="alert alert-secondary py-2 mb-3">
                    <strong>Item Diminta:</strong> <span id="modalItemName" class="text-info font-weight-bold"></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm bg-white" id="tableModalPO">
                        <thead class="thead-light text-center">
                            <tr>
                                <th>Tanggal PO</th>
                                <th>Nomor PO</th>
                                <th>Qty di PO</th>
                                <th>Kode Sup.</th>
                                <th class="text-left">Nama Supplier</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup Jendela</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    
    // Inisialisasi Select2 untuk Dropdown Department
    $('.select2-dept').select2({ placeholder: "-- Pilih Departemen --", allowClear: true });
    
    // AJAX Pelacakan PO (Pastikan get_po_tracking.php ada di satu folder yang sama)
    $('.btn-track-po').click(function() {
        var reqId = $(this).data('reqid');
        var itemId = $(this).data('itemid');
        var itemName = $(this).data('itemname');
        var tbody = $('#tableModalPO tbody');

        $('#modalItemName').text(itemName);
        tbody.html('<tr><td colspan="5" class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i><br>Mencari relasi PO...</td></tr>');
        $('#modalPO').modal('show');

        $.ajax({
            url: 'get_po_tracking.php',
            type: 'GET',
            data: { req_id: reqId, item_id: itemId },
            dataType: 'json',
            success: function(data) {
                if (data.length > 0) {
                    var rows = '';
                    $.each(data, function(index, po) {
                        rows += `
                            <tr>
                                <td class="text-center">${po.po_date}</td>
                                <td class="text-center font-weight-bold text-primary">${po.po_num}</td>
                                <td class="text-center font-weight-bold text-success">${po.qty}</td>
                                <td class="text-center">${po.sup_code}</td>
                                <td>${po.supplier}</td>
                            </tr>
                        `;
                    });
                    tbody.html(rows);
                } else {
                    tbody.html('<tr><td colspan="5" class="text-center text-danger font-weight-bold py-4"><i class="fas fa-exclamation-triangle"></i> Item ini belum diproses ke dalam Purchase Order (PO).</td></tr>');
                }
            },
            error: function() {
                tbody.html('<tr><td colspan="5" class="text-center text-danger py-4">Gagal memuat histori PO dari server.</td></tr>');
            }
        });
    });

});
</script>
</body>
</html>
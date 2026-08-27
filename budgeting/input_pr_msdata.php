<?php
require_once 'config.php'; //[cite: 1]

function h($string) { return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8'); }

function get_sql_errors() {
    $errors = sqlsrv_errors();
    $err_msg = "";
    if ($errors != null) { foreach ($errors as $error) { $err_msg .= $error['message'] . "<br>"; } } 
    else { $err_msg = "Unknown Error SQL."; }
    return $err_msg;
}

$message = "";

// ==========================================================
// PROSES SIMPAN REQUISITION (PR) KE MSDATA
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_requisition') {
    $dep_id   = isset($_POST['dep_id']) ? (int)$_POST['dep_id'] : 0;
    $req_due  = isset($_POST['req_due']) ? trim($_POST['req_due']) : date('Y-m-d');
    $req_rem  = isset($_POST['req_rem']) ? substr(trim($_POST['req_rem']), 0, 20) : ''; 
    
    $item_ids = isset($_POST['item_id']) ? $_POST['item_id'] : array();
    $qtys     = isset($_POST['reqd_qty']) ? $_POST['reqd_qty'] : array();
    $remarks  = isset($_POST['reqd_rem']) ? $_POST['reqd_rem'] : array();

    if ($dep_id > 0 && count($item_ids) > 0) {
        sqlsrv_begin_transaction($conn_msdata);
        $is_success = true;
        $error_detail = "";

        $prefix = 'RQ' . date('ym');
        $sql_last = "SELECT TOP 1 REQ_NO FROM REQUISITION WHERE REQ_NO LIKE ? ORDER BY REQ_NO DESC";
        $stmt_last = sqlsrv_query($conn_msdata, $sql_last, array($prefix . '%'));
        
        $seq = 1;
        if ($stmt_last && sqlsrv_has_rows($stmt_last)) {
            $r_last = sqlsrv_fetch_array($stmt_last, SQLSRV_FETCH_ASSOC);
            $seq = (int)substr(trim($r_last['REQ_NO']), -4) + 1;
        }
        $req_no = $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
        
        if (strtotime($req_due) < strtotime(date('Y-m-d'))) { $req_due = date('Y-m-d'); }

        $sql_head = "INSERT INTO REQUISITION (REQ_NO, REQ_DATE, REQ_DUE, REQ_REM, DEP_ID, REQ_ISAMR, REQ_CLOSE) 
                     VALUES (?, GETDATE(), ?, ?, ?, 0, 0);
                     SELECT SCOPE_IDENTITY() AS new_req_id;";
                     
        $params_head = array($req_no, $req_due, $req_rem, $dep_id);
        $stmt_head = sqlsrv_query($conn_msdata, $sql_head, $params_head);
        
        if ($stmt_head) {
            sqlsrv_next_result($stmt_head); 
            $row_id = sqlsrv_fetch_array($stmt_head, SQLSRV_FETCH_ASSOC);
            $new_req_id = $row_id['new_req_id'];

            $sql_det = "INSERT INTO REQ_DETAIL (ITEM_ID, REQ_ID, REQD_QTY, REQD_REM) VALUES (?, ?, ?, ?)";
            $inserted_items = array(); 

            for ($i = 0; $i < count($item_ids); $i++) {
                $p_item_id = (int)$item_ids[$i];
                $p_qty     = (float)$qtys[$i];
                $p_remark  = isset($remarks[$i]) ? substr(trim($remarks[$i]), 0, 50) : '';

                if ($p_qty > 0 && $p_item_id > 0 && !in_array($p_item_id, $inserted_items)) {
                    $stmt_det = sqlsrv_query($conn_msdata, $sql_det, array($p_item_id, $new_req_id, $p_qty, $p_remark));
                    
                    if (!$stmt_det) { 
                        $is_success = false; $error_detail .= "Gagal insert Item ID {$p_item_id}: " . get_sql_errors(); break; 
                    }
                    $inserted_items[] = $p_item_id;
                }
            }
        } else {
            $is_success = false; $error_detail .= "Gagal Insert Header: " . get_sql_errors();
        }

        if ($is_success) {
            sqlsrv_commit($conn_msdata);
            $message = "<div class='alert alert-success shadow-sm'><i class='fas fa-check-circle'></i> Requisition (PR) Berhasil Disimpan!<br>Nomor Dokumen: <strong>".h($req_no)."</strong></div>";
        } else {
            sqlsrv_rollback($conn_msdata);
            $message = "<div class='alert alert-danger'><strong>Transaksi Gagal!</strong><br><small>{$error_detail}</small></div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Mohon lengkapi Departemen dan minimal 1 barang.</div>";
    }
}

// Load Departemen Saja (Load Items dihapus untuk performa)
$sql_dept = "SELECT DEP_ID, DEP_CODE, DEP_NAME FROM DEPT ORDER BY DEP_NAME ASC";
$stmt_dept = sqlsrv_query($conn_msdata, $sql_dept);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Requisition (PR msdata) - ERP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { background-color: #f4f6f9; font-family: Tahoma, sans-serif; font-size: 13px; padding: 20px; }
        .select2-container .select2-selection--single { height: 38px !important; border: 1px solid #ced4da !important; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white font-weight-bold">
            <i class="fas fa-file-invoice"></i> Form Requisition / Purchase Request (MSDATA)
        </div>
        <div class="card-body">
            <?php echo $message; ?>

            <form method="POST" action="" id="formRequisition">
                <input type="hidden" name="action" value="save_requisition">
                
                <h5 class="text-primary border-bottom pb-2 mb-3">1. Data Requisition (Header)</h5>
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Nomor Requisition</label>
                        <input type="text" class="form-control text-center font-weight-bold bg-light" value="[ AUTO GENERATE ]" readonly>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Departemen <span class="text-danger">*</span></label>
                        <select name="dep_id" class="form-control select2-search" required>
                            <option value="">-- Pilih Departemen --</option>
                            <?php 
                            if ($stmt_dept !== false) {
                                while ($d = sqlsrv_fetch_array($stmt_dept, SQLSRV_FETCH_ASSOC)) {
                                    $dept_name = isset($d['DEP_NAME']) ? h($d['DEP_NAME']) : "Dept ID: " . $d['DEP_ID'];
                                    echo '<option value="'.(int)$d['DEP_ID'].'">'.$dept_name.'</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Due Date <span class="text-danger">*</span></label>
                        <input type="date" name="req_due" class="form-control" min="<?php echo date('Y-m-d'); ?>" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Remarks</label>
                        <input type="text" name="req_rem" class="form-control" maxlength="20" placeholder="Max 20 Karakter">
                    </div>
                </div>

                <h5 class="text-primary border-bottom pb-2 mt-4 mb-3">2. Detail Permintaan Barang</h5>
                <div class="table-responsive">
                    <table class="table table-bordered" id="tableDetail">
                        <thead class="thead-light">
                            <tr>
                                <th width="45%">Cari Kode / Nama Barang <span class="text-danger">*</span></th>
                                <th width="15%">Qty Diminta <span class="text-danger">*</span></th>
                                <th width="30%">Catatan Item (Max 50 Char)</th>
                                <th width="10%" class="text-center"><i class="fas fa-cog"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <!-- Select tidak lagi memiliki opsi default, akan diload AJAX -->
                                    <select name="item_id[]" class="form-control select2-item-ajax" required>
                                        <option value="" selected="selected">-- Ketik min 2 huruf --</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="reqd_qty[]" class="form-control" min="0.01" step="any" required>
                                </td>
                                <td>
                                    <input type="text" name="reqd_rem[]" class="form-control" maxlength="50" placeholder="Catatan spesifik...">
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-danger btn-remove" disabled><i class="fas fa-times"></i></button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-danger font-italic text-right">
                                    <small>* Harap jangan memilih barang yang sama (duplikat) dalam satu dokumen.</small>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-primary" id="btnAddRow"><i class="fas fa-plus"></i></button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <button type="submit" class="btn btn-primary btn-lg mt-3">
                    <i class="fas fa-save"></i> Simpan Requisition
                </button>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('.select2-search').select2();

    // Fungsi Inisialisasi AJAX Select2
    function initSelect2Ajax(element) {
        element.select2({
            placeholder: "-- Ketik min 2 huruf --",
            minimumInputLength: 2,
            ajax: {
                url: "get_items_autocomplete.php",
                dataType: 'json',
                delay: 250, // Jeda waktu sebelum melakukan pencarian ke DB (Meringankan server)
                data: function (params) {
                    return { q: params.term };
                },
                processResults: function (data) {
                    return { results: data.results };
                },
                cache: true
            }
        });
    }

    // Terapkan ke baris pertama saat halaman dimuat
    initSelect2Ajax($('.select2-item-ajax'));

    // Tambah Baris
    $('#btnAddRow').click(function() {
        var newRow = `
            <tr>
                <td><select name="item_id[]" class="form-control select2-item-ajax" required><option value="" selected="selected">-- Ketik min 2 huruf --</option></select></td>
                <td><input type="number" name="reqd_qty[]" class="form-control" min="0.01" step="any" required></td>
                <td><input type="text" name="reqd_rem[]" class="form-control" maxlength="50" placeholder="Catatan spesifik..."></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-danger btn-remove"><i class="fas fa-times"></i></button></td>
            </tr>
        `;
        $('#tableDetail tbody').append(newRow);
        
        // Terapkan Select2 AJAX ke baris yang baru saja dibuat
        initSelect2Ajax($('#tableDetail tbody tr:last').find('.select2-item-ajax'));
        checkRemoveButtons();
    });

    // Hapus Baris
    $(document).on('click', '.btn-remove', function() {
        $(this).closest('tr').find('select').select2('destroy');
        $(this).closest('tr').remove();
        checkRemoveButtons();
    });

    function checkRemoveButtons() {
        if ($('#tableDetail tbody tr').length === 1) {
            $('.btn-remove').prop('disabled', true);
        } else {
            $('.btn-remove').prop('disabled', false);
        }
    }
});
</script>
</body>
</html>
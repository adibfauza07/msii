<?php
require_once 'config.php';

// Fungsi sanitasi standar PHP 5.4 (Mencegah XSS)
function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

// Fungsi khusus menangkap error SQL Server agar terbaca jelas (Tidak Blank)
function get_sql_errors() {
    $errors = sqlsrv_errors();
    $err_msg = "";
    if ($errors != null) {
        foreach ($errors as $error) {
            $err_msg .= $error['message'] . "<br>";
        }
    } else {
        $err_msg = "Unknown Error / Parameter tidak sesuai tipe data SQL.";
    }
    return $err_msg;
}

$message = "";

// ==========================================================
// PROSES SIMPAN GOODS RECEIPT (GR)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_receive') {
    $department_id = isset($_POST['department_id']) ? trim($_POST['department_id']) : '';
    $plant_id      = isset($_POST['plant_id']) ? trim($_POST['plant_id']) : '';
    $received_by   = isset($_POST['received_by']) ? trim($_POST['received_by']) : '';
    
    // Generate Auto Number Goods Receipt (Format: RCV-YYMMXXXX)
    $prefix = 'RCV-' . date('ym');
    $sql_last = "SELECT TOP 1 receive_no FROM Receive_Header WHERE receive_no LIKE ? ORDER BY receive_no DESC";
    $stmt_last = sqlsrv_query($conn, $sql_last, array($prefix . '%'));
    
    $seq = 1;
    if ($stmt_last && sqlsrv_has_rows($stmt_last)) {
        $r_last = sqlsrv_fetch_array($stmt_last, SQLSRV_FETCH_ASSOC);
        $seq = (int)substr($r_last['receive_no'], -4) + 1;
    }
    $receive_no = $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    $receive_date = date('Y-m-d H:i:s');
    
    // Arrays Detail
    $pr_detail_ids = isset($_POST['pr_detail_id']) ? $_POST['pr_detail_id'] : array();
    $item_codes    = isset($_POST['item_code']) ? $_POST['item_code'] : array();
    $qty_ins       = isset($_POST['qty_in']) ? $_POST['qty_in'] : array();

    if (!empty($department_id) && !empty($plant_id) && count($pr_detail_ids) > 0) {
        
        // 1. Mulai Transaksi SQL
        if (sqlsrv_begin_transaction($conn) === false) {
            die(get_sql_errors());
        }

        $is_success = true;
        $error_detail = ""; 

        // 2. Insert Receive_Header
        $sql_header = "INSERT INTO Receive_Header (receive_no, receive_date, department_id, plant_id, received_by, created_at) 
                       VALUES (?, ?, ?, ?, ?, GETDATE())";
        $params_header = array($receive_no, $receive_date, $department_id, $plant_id, $received_by);
        $stmt_header = sqlsrv_query($conn, $sql_header, $params_header);

        if (!$stmt_header) {
            $is_success = false;
            $error_detail .= "Gagal Insert Header: " . get_sql_errors();
        }

        // 3. Insert Receive_Det (Looping) - Menggunakan nama tabel baru
        if ($is_success) {
            $sql_detail = "INSERT INTO Receive_Det (receive_no, pr_detail_id, item_code, qty_in) 
                           VALUES (?, ?, ?, ?)";
            
            for ($i = 0; $i < count($pr_detail_ids); $i++) {
                $p_detail_id = isset($pr_detail_ids[$i]) ? (int)$pr_detail_ids[$i] : 0;
                $p_item_code = isset($item_codes[$i]) ? trim($item_codes[$i]) : '';
                $p_qty_in    = isset($qty_ins[$i]) ? (float)$qty_ins[$i] : 0;

                if ($p_qty_in > 0 && $p_detail_id > 0 && $p_item_code !== '') {
                    $params_detail = array($receive_no, $p_detail_id, $p_item_code, $p_qty_in);
                    $stmt_detail = sqlsrv_query($conn, $sql_detail, $params_detail);

                    if (!$stmt_detail) {
                        $is_success = false;
                        $error_detail .= "Gagal Insert Detail [{$p_item_code}]: " . get_sql_errors();
                        break; 
                    }
                }
            }
        }

        // 4. Commit / Rollback (Memastikan syntax kurung kurawal tertutup sempurna)
        if ($is_success) {
            sqlsrv_commit($conn);
            $message = "
            <div class='alert alert-success shadow-sm'>
                <h4 class='alert-heading'><i class='fas fa-check-circle'></i> Penerimaan Berhasil!</h4>
                <p>Barang telah berhasil diterima dan masuk ke sistem inventory.</p>
                <hr>
                <p class='mb-0'>
                    No. Dokumen (GR): <strong class='badge badge-success' style='font-size:16px;'>" . h($receive_no) . "</strong> 
                    <a href='report_penerimaan.php?id=" . urlencode($receive_no) . "' target='_blank' class='btn btn-warning btn-sm ml-3'>
                        <i class='fas fa-print'></i> Cetak ICL (Incoming Check List)
                    </a>
                </p>
            </div>";
        } else {
            sqlsrv_rollback($conn);
            $message = "<div class='alert alert-danger'><strong>Transaksi Gagal!</strong> Terjadi kesalahan pada database.<br><small>{$error_detail}</small></div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Form tidak lengkap! Departemen, Plant, dan minimal 1 Item barang diterima harus diisi.</div>";
    }
}

// Ambil Master Department
$sql_dept = "SELECT department_id, department_name, plant_id FROM Master_Department WHERE is_active = 1 ORDER BY department_name ASC";
$stmt_dept = sqlsrv_query($conn, $sql_dept);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Receive Barang (GR) - ERP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { background-color: #f4f6f9; font-family: Tahoma, sans-serif; font-size: 13px; padding: 20px; }
        .select2-container .select2-selection--single, .select2-container .select2-selection--multiple { min-height: 38px !important; border: 1px solid #ced4da !important; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white font-weight-bold">
            <i class="fas fa-boxes"></i> Form Penerimaan Barang (Goods Receipt - Multi PR, Single Dept)
        </div>
        <div class="card-body">
            <?php echo $message; ?>

            <form method="POST" action="" id="formReceive">
                <input type="hidden" name="action" value="save_receive">
                
                <h5 class="text-success border-bottom pb-2 mb-3">1. Data Header Penerimaan</h5>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Departemen <span class="text-danger">*</span></label>
                        <select name="department_id" id="deptSelect" class="form-control select2-search" required>
                            <option value="">-- Pilih Departemen --</option>
                            <?php 
                            if ($stmt_dept !== false) {
                                while ($d = sqlsrv_fetch_array($stmt_dept, SQLSRV_FETCH_ASSOC)) {
                                    $plant = h($d['plant_id']);
                                    echo '<option value="'.h($d['department_id']).'" data-plant="'.$plant.'">'.h($d['department_name']).' ('.$plant.')</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 form-group">
                        <label>Plant / Lokasi <span class="text-danger">*</span></label>
                        <input type="text" id="plantDisplay" class="form-control bg-light" readonly placeholder="Otomatis...">
                        <input type="hidden" name="plant_id" id="plantHidden" required>
                    </div>

                    <div class="col-md-3 form-group">
                        <label>Diterima Oleh (Nama) <span class="text-danger">*</span></label>
                        <input type="text" name="received_by" class="form-control" required placeholder="Nama Penerima">
                    </div>
                </div>

                <div class="form-group mt-3">
                    <label class="font-weight-bold text-primary"><i class="fas fa-search"></i> Pilih Nomor Purchase Request (PR)</label>
                    <select id="prSelect" class="form-control select2-multiple" multiple="multiple" disabled>
                    </select>
                    <small class="text-muted">Pilih satu atau beberapa nomor PR dari departemen di atas untuk memuat daftar barang.</small>
                </div>

                <h5 class="text-success border-bottom pb-2 mt-4 mb-3">2. Detail Barang Diterima</h5>
                <div class="table-responsive">
                    <table class="table table-bordered" id="tableDetail">
                        <thead class="thead-light">
                            <tr>
                                <th width="15%">No. PR</th>
                                <th width="12%">ID Detail PR</th>
                                <th width="20%">Kode & Nama Barang</th>
                                <th width="15%">Qty Diminta</th>
                                <th width="20%">Qty Diterima (Qty In) <span class="text-danger">*</span></th>
                                <th width="8%" class="text-center"><i class="fas fa-cog"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr id="emptyRow">
                                <td colspan="6" class="text-center text-muted py-3">Belum ada PR yang dipilih. Silakan pilih Departemen dan Nomor PR di atas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="btn btn-success btn-lg mt-3" id="btnSubmit" disabled>
                    <i class="fas fa-save"></i> Simpan Penerimaan Barang
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
    $('.select2-multiple').select2({
        placeholder: "-- Pilih Nomor PR --",
        allowClear: true
    });

    $('#deptSelect').on('change', function() {
        var selected = $(this).find('option:selected');
        var plant = selected.data('plant');
        var deptId = $(this).val();

        if (plant) {
            $('#plantDisplay').val(plant + ' - Plant ' + plant.replace('P',''));
            $('#plantHidden').val(plant);
        } else {
            $('#plantDisplay').val('');
            $('#plantHidden').val('');
        }

        $('#prSelect').val(null).trigger('change').prop('disabled', true);
        $('#tableDetail tbody').html('<tr id="emptyRow"><td colspan="6" class="text-center text-muted py-3">Belum ada PR yang dipilih.</td></tr>');
        $('#btnSubmit').prop('disabled', true);

        if (deptId !== "") {
            $.ajax({
                url: 'get_pr_by_dept.php',
                type: 'GET',
                data: { dept_id: deptId },
                dataType: 'json',
                success: function(data) {
                    var options = '';
                    $.each(data, function(i, item) {
                        options += '<option value="' + item.pr_no + '">' + item.text + '</option>';
                    });
                    $('#prSelect').html(options).prop('disabled', false).trigger('change');
                }
            });
        }
    });

    $('#prSelect').on('change', function() {
        var selectedPRs = $(this).val(); 

        if (!selectedPRs || selectedPRs.length === 0) {
            $('#tableDetail tbody').html('<tr id="emptyRow"><td colspan="6" class="text-center text-muted py-3">Belum ada PR yang dipilih.</td></tr>');
            $('#btnSubmit').prop('disabled', true);
            return;
        }

        $.ajax({
            url: 'get_pr_details.php',
            type: 'GET',
            data: { pr_nos: selectedPRs.join(',') },
            dataType: 'json',
            success: function(data) {
                var rows = '';
                if (data.length > 0) {
                    $.each(data, function(i, row) {
                        rows += `
                            <tr>
                                <td><strong>${row.pr_no}</strong></td>
                                <td>
                                    ${row.pr_detail_id}
                                    <input type="hidden" name="pr_detail_id[]" value="${row.pr_detail_id}">
                                </td>
                                <td>
                                    ${row.item_code} - ${row.item_name} (${row.uom})
                                    <input type="hidden" name="item_code[]" value="${row.item_code}">
                                </td>
                                <td class="text-center font-weight-bold">${row.qty_request}</td>
                                <td>
                                    <input type="number" name="qty_in[]" class="form-control qty-input" min="0.01" max="${row.qty_request}" step="any" value="${row.qty_request}" required>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-danger btn-remove" title="Hapus Baris"><i class="fas fa-times"></i></button>
                                </td>
                            </tr>
                        `;
                    });
                    $('#tableDetail tbody').html(rows);
                    $('#btnSubmit').prop('disabled', false);
                } else {
                    $('#tableDetail tbody').html('<tr id="emptyRow"><td colspan="6" class="text-center text-danger py-3">Tidak ada detail barang ditemukan.</td></tr>');
                    $('#btnSubmit').prop('disabled', true);
                }
            }
        });
    });

    $('#formReceive').on('submit', function(e) {
        if ($('#tableDetail tbody tr#emptyRow').length > 0 || $('#tableDetail tbody tr').length === 0) {
            e.preventDefault();
            alert("Harap pilih minimal 1 PR dan pastikan ada barang di tabel!");
            return false;
        }
    });

    $(document).on('click', '.btn-remove', function() {
        $(this).closest('tr').remove();
        if ($('#tableDetail tbody tr').length === 0) {
            $('#tableDetail tbody').html('<tr id="emptyRow"><td colspan="6" class="text-center text-muted py-3">Belum ada PR yang dipilih.</td></tr>');
            $('#btnSubmit').prop('disabled', true);
        }
    });
});
</script>

</body>
</html>
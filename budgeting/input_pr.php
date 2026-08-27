<?php
require_once 'config.php';

// Fungsi sanitasi standar PHP 5.4
function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

// Fungsi khusus menangkap error SQL Server agar terbaca jelas (bukan blank)
function get_sql_errors() {
    $errors = sqlsrv_errors();
    $err_msg = "";
    if ($errors != null) {
        foreach ($errors as $error) {
            $err_msg .= $error['message'] . "<br>";
        }
    } else {
        $err_msg = "Unknown Error / Parameter tidak sesuai tipe data.";
    }
    return $err_msg;
}

$message = "";

// ==========================================================
// 1. PROSES SIMPAN PURCHASE REQUEST (PR)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_pr') {
    
    $department_id = isset($_POST['department_id']) ? trim($_POST['department_id']) : '';
    $plant_id      = isset($_POST['plant_id']) ? trim($_POST['plant_id']) : ''; 
    $created_by    = isset($_POST['created_by']) ? trim($_POST['created_by']) : '';
    $pr_date       = date('Y-m-d H:i:s');
    
    // Array Data Detail
    $item_codes   = isset($_POST['item_code']) ? $_POST['item_code'] : array();
    $quote_ids    = isset($_POST['quote_id']) ? $_POST['quote_id'] : array(); 
    $qty_requests = isset($_POST['qty_request']) ? $_POST['qty_request'] : array();
    $unit_prices  = isset($_POST['unit_price']) ? $_POST['unit_price'] : array();
    $remarks      = isset($_POST['remark']) ? $_POST['remark'] : array(); 

    // Validasi Pra-Transaksi: Pastikan minimal ada 1 barang valid
    $valid_items_count = 0;
    $total_amount = 0;
    
    for ($i = 0; $i < count($item_codes); $i++) {
        $icode = trim($item_codes[$i]);
        $qty   = (float)$qty_requests[$i];
        $qid   = isset($quote_ids[$i]) ? (int)$quote_ids[$i] : 0;
        $price = (float)$unit_prices[$i];
        
        if ($qty > 0 && $icode !== '' && $qid > 0) {
            $valid_items_count++;
            $total_amount += ($qty * $price);
        }
    }

    // Jika form Header lengkap DAN ada minimal 1 barang valid
    if (!empty($department_id) && !empty($plant_id) && $valid_items_count > 0) {
        
        // Mulai Transaksi SQL
        if (sqlsrv_begin_transaction($conn) === false) {
            die("Transaction Error: " . get_sql_errors());
        }

        $is_success = true;
        $error_detail = ""; 
        
        // =======================================================
        // GENERATE NOMOR PR OTOMATIS (Format: PRYYMMXXXX)
        // =======================================================
        $prefix = 'PR' . date('ym'); 
        $sql_last_pr = "SELECT TOP 1 pr_no FROM PR_Header WHERE pr_no LIKE ? ORDER BY pr_no DESC";
        $stmt_last = sqlsrv_query($conn, $sql_last_pr, array($prefix . '%'));
        
        $new_sequence = 1;
        if ($stmt_last && sqlsrv_has_rows($stmt_last)) {
            $row_last = sqlsrv_fetch_array($stmt_last, SQLSRV_FETCH_ASSOC);
            $last_pr = $row_last['pr_no'];
            $last_seq = (int)substr($last_pr, -4);
            $new_sequence = $last_seq + 1;
        }
        $pr_no = $prefix . str_pad($new_sequence, 4, '0', STR_PAD_LEFT);

        // 1. Insert Header
        $sql_header = "INSERT INTO PR_Header (pr_no, pr_date, plant_id, department_id, total_amount, status, created_by, created_at) 
                       VALUES (?, ?, ?, ?, ?, 'PENDING', ?, GETDATE())";
        $params_header = array($pr_no, $pr_date, $plant_id, $department_id, $total_amount, $created_by);
        
        $stmt_header = sqlsrv_query($conn, $sql_header, $params_header);
        
        if (!$stmt_header) { 
            $is_success = false; 
            $error_detail .= "Gagal Header: " . get_sql_errors();
        }

        // 2. Insert Detail
        if ($is_success) {
            $sql_detail = "INSERT INTO PR_Detail (pr_no, item_code, quote_id, qty_request, qty_received, unit_price, subtotal, remark) 
                           VALUES (?, ?, ?, ?, 0, ?, ?, ?)";
            
            for ($i = 0; $i < count($item_codes); $i++) {
                $icode = trim($item_codes[$i]);
                $qid   = isset($quote_ids[$i]) ? (int)$quote_ids[$i] : 0;
                $qty   = (float)$qty_requests[$i];
                $price = (float)$unit_prices[$i];
                $sub   = $qty * $price;
                $rem   = isset($remarks[$i]) ? trim($remarks[$i]) : '';
                
                // Hanya insert baris yang diisi valid
                if ($qty > 0 && $icode !== '' && $qid > 0) {
                    $params_detail = array($pr_no, $icode, $qid, $qty, $price, $sub, $rem);
                    $stmt_detail = sqlsrv_query($conn, $sql_detail, $params_detail);
                    
                    if (!$stmt_detail) {
                        $is_success = false;
                        $error_detail .= "Gagal Detail [{$icode}]: " . get_sql_errors();
                        break;
                    }
                }
            }
        }

        // 3. Commit atau Rollback
        if ($is_success) {
            sqlsrv_commit($conn);
            $message = "
            <div class='alert alert-success shadow-sm'>
                <h4 class='alert-heading'><i class='fas fa-check-circle'></i> Transaksi Berhasil Disimpan!</h4>
                <p>Data Header dan Detail Purchase Request (PR) telah berhasil dimasukkan ke dalam sistem.</p>
                <hr>
                <p class='mb-0'>
                    Nomor PR Anda adalah: <strong class='badge badge-success' style='font-size:16px;'>" . h($pr_no) . "</strong> 
                    <a href='report_pr.php?pr_no=" . urlencode($pr_no) . "' target='_blank' class='btn btn-warning btn-sm ml-3'>
                        <i class='fas fa-print'></i> Cetak Dokumen PR
                    </a>
                </p>
            </div>";
        } else {
            sqlsrv_rollback($conn);
            $message = "<div class='alert alert-danger'>Gagal menyimpan PR. <br><small>{$error_detail}</small></div>";
        }
        
    } else {
        $message = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Form tidak valid! Pastikan Departemen terpilih dan <strong>minimal ada 1 Barang</strong> yang memiliki Qty dan Quotation/Vendor.</div>";
    }
}

// ==========================================================
// 2. AMBIL DATA MASTER UNTUK DROPDOWN DEPARTEMEN
// ==========================================================
$sql_dept = "SELECT department_id, department_name, plant_id FROM Master_Department WHERE is_active = 1 ORDER BY department_name ASC";
$stmt_dept = sqlsrv_query($conn, $sql_dept);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Purchase Request (PR) - ERP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <style>
        body { background-color: #f4f6f9; font-family: Tahoma, sans-serif; font-size: 13px; padding: 20px; }
        .card-header { font-weight: bold; font-size: 14px; }
        .table th, .table td { vertical-align: middle; }
        .subtotal-col { background-color: #e9ecef; font-weight: bold; }
        .select2-container .select2-selection--single { height: 38px !important; border: 1px solid #ced4da !important; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-shopping-cart"></i> Form Pengajuan Purchase Request (PR)
        </div>
        <div class="card-body">
            
            <?php echo $message; ?>

            <form method="POST" action="">
                <input type="hidden" name="action" value="save_pr">

                <h5 class="text-primary border-bottom pb-2 mb-3">1. Data Header (Pemohon)</h5>
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Nomor PR (Auto)</label>
                        <input type="text" class="form-control font-weight-bold text-center text-primary bg-light" value="[ AUTO GENERATED ]" readonly disabled>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Departemen <span class="text-danger">*</span></label>
                        <select name="department_id" id="deptSelect" class="form-control select2-search" required>
                            <option value="">-- Pilih Departemen --</option>
                            <?php 
                            if ($stmt_dept !== false) {
                                while ($d = sqlsrv_fetch_array($stmt_dept, SQLSRV_FETCH_ASSOC)) {
                                    $plant = h($d['plant_id']);
                                    $dept_name = h($d['department_name']) . " (" . $plant . ")";
                                    echo '<option value="'.h($d['department_id']).'" data-plant="'.$plant.'">'.$dept_name.'</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Plant / Lokasi <span class="text-danger">*</span></label>
                        <input type="text" id="plantDisplay" class="form-control bg-light" readonly placeholder="Otomatis terisi...">
                        <input type="hidden" name="plant_id" id="plantHidden" required>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Dibuat Oleh (Nama) <span class="text-danger">*</span></label>
                        <input type="text" name="created_by" class="form-control" required placeholder="Nama Pemohon">
                    </div>
                </div>

                <h5 class="text-primary border-bottom pb-2 mt-4 mb-3">2. Daftar Barang yang Diminta (Item Detail)</h5>
                <div class="table-responsive">
                    <table class="table table-bordered" id="prTable">
                        <thead class="thead-light">
                            <tr>
                                <th width="20%">Pilih Barang (Quotation)</th>
                                <th width="15%">Vendor</th>
                                <th width="10%">Qty Diminta</th>
                                <th width="15%">Harga Satuan</th>
                                <th width="15%">Subtotal (Rp)</th>
                                <th width="20%">Remark / Keterangan</th>
                                <th width="5%" class="text-center"><i class="fas fa-cog"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Baris pertama (Default) -->
                            <tr>
                                <td>
                                    <select name="item_code[]" class="form-control select2-item" required>
                                        <option value="">-- Pilih Departemen Dahulu --</option>
                                    </select>
                                    <input type="hidden" name="quote_id[]" class="quote-input" required>
                                </td>
                                <td>
                                    <input type="text" class="form-control vendor-display bg-light" readonly placeholder="Nama Vendor...">
                                </td>
                                <td>
                                    <input type="number" name="qty_request[]" class="form-control qty-input" min="0.01" step="any" placeholder="0" required>
                                </td>
                                <td>
                                    <input type="number" name="unit_price[]" class="form-control price-input bg-light" min="0" step="any" placeholder="0" required readonly>
                                </td>
                                <td>
                                    <input type="text" class="form-control subtotal-col subtotal-display" value="0" readonly>
                                </td>
                                <td>
                                    <textarea name="remark[]" class="form-control" rows="1" maxlength="300" placeholder="Keterangan opsional..."></textarea>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-danger btn-remove" disabled><i class="fas fa-times"></i></button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-right font-weight-bold align-middle">GRAND TOTAL:</td>
                                <td>
                                    <input type="text" id="grandTotal" class="form-control text-primary font-weight-bold" value="0" readonly style="background-color: #fff;">
                                </td>
                                <td></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-primary" id="btnAddRow" disabled><i class="fas fa-plus"></i></button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <hr>
                <div class="text-right">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-save"></i> Simpan Header & Detail PR
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    
    $('.select2-search').select2();
    $('.select2-item').select2();

    var itemOptions = '<option value="">-- Pilih Barang --</option>';

    // =======================================================
    // 1. AJAX LOAD ITEMS BERDASARKAN DEPARTEMEN
    // =======================================================
    $('#deptSelect').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var plantValue = selectedOption.data('plant');
        var deptId = $(this).val();

        // Auto Fill Plant
        if (plantValue) {
            $('#plantDisplay').val(plantValue + ' - Plant ' + plantValue.replace('P', ''));
            $('#plantHidden').val(plantValue);
        } else {
            $('#plantDisplay').val('');
            $('#plantHidden').val('');
        }

        // Reset Tabel (kembalikan jadi 1 baris kosong)
        $('#prTable tbody tr:not(:first)').remove();
        $('.qty-input, .price-input, .vendor-display, .quote-input, textarea[name="remark[]"]').val('');
        $('.subtotal-display, #grandTotal').val(0);
        checkRemoveButtons();

        var firstSelect = $('#prTable tbody tr:first').find('select.select2-item');
        firstSelect.empty().append('<option value="">Sedang memuat data...</option>').trigger('change');
        $('#btnAddRow').prop('disabled', true);
        
        // Fetch Items (Hanya yang punya Quotation aktif)
        if (deptId !== "") {
            $.ajax({
                url: 'get_items_by_dept.php', 
                type: 'GET',
                data: { dept_id: deptId }, 
                dataType: 'json',
                success: function(data) {
                    var options = '<option value="">-- Pilih Barang --</option>';
                    
                    $.each(data, function(index, item) {
                        var textDisplay = item.item_code + ' - ' + item.item_name + ' (' + item.uom + ')';
                        options += '<option value="' + item.item_code + '" ' +
                                   'data-quote="' + item.quote_id + '" ' +
                                   'data-price="' + item.unit_price + '" ' +
                                   'data-vendor="' + item.vendor_name + '">' + 
                                   textDisplay + '</option>';
                    });

                    itemOptions = options; 
                    firstSelect.empty().append(options).trigger('change');
                    $('#btnAddRow').prop('disabled', false); 
                },
                error: function(xhr, status, error) {
                    var errMsg = xhr.responseText ? xhr.responseText.substring(0, 150) : error;
                    alert("Gagal menarik data barang. Detail Server: \n" + errMsg);
                }
            });
        } else {
            itemOptions = '<option value="">-- Pilih Departemen Dahulu --</option>';
            firstSelect.empty().append(itemOptions).trigger('change');
            $('#btnAddRow').prop('disabled', true);
        }
    });

    // =======================================================
    // 2. EVENT AUTO-FILL VENDOR & HARGA SAAT BARANG DIPILIH
    // =======================================================
    $(document).on('change', 'select[name="item_code[]"]', function() {
        var selected = $(this).find('option:selected');
        var row = $(this).closest('tr');
        
        if (selected.val() !== "") {
            var quoteId = selected.data('quote');
            var price   = selected.data('price');
            var vendor  = selected.data('vendor');
            
            row.find('.quote-input').val(quoteId);
            row.find('.vendor-display').val(vendor);
            row.find('.price-input').val(price); 
            
            // Trigger input agar kalkulasi subtotal langsung jalan
            row.find('.qty-input').trigger('input');
        } else {
            row.find('.quote-input').val('');
            row.find('.vendor-display').val('');
            row.find('.price-input').val(0);
            row.find('.subtotal-display').val(0);
        }
    });

    // =======================================================
    // 3. LOGIKA TAMBAH/HAPUS BARIS
    // =======================================================
    $('#btnAddRow').click(function() {
        var newRow = `
            <tr>
                <td>
                    <select name="item_code[]" class="form-control select2-item-dynamic" required>
                        ${itemOptions}
                    </select>
                    <input type="hidden" name="quote_id[]" class="quote-input" required>
                </td>
                <td>
                    <input type="text" class="form-control vendor-display bg-light" readonly placeholder="Nama Vendor...">
                </td>
                <td>
                    <input type="number" name="qty_request[]" class="form-control qty-input" min="0.01" step="any" placeholder="0" required>
                </td>
                <td>
                    <input type="number" name="unit_price[]" class="form-control price-input bg-light" min="0" step="any" placeholder="0" required readonly>
                </td>
                <td>
                    <input type="text" class="form-control subtotal-col subtotal-display" value="0" readonly>
                </td>
                <td>
                    <textarea name="remark[]" class="form-control" rows="1" maxlength="300" placeholder="Keterangan opsional..."></textarea>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger btn-remove"><i class="fas fa-times"></i></button>
                </td>
            </tr>
        `;
        $('#prTable tbody').append(newRow);
        $('#prTable tbody tr:last').find('.select2-item-dynamic').select2();
        checkRemoveButtons();
    });

    $(document).on('click', '.btn-remove', function() {
        $(this).closest('tr').find('select').select2('destroy');
        $(this).closest('tr').remove();
        calculateGrandTotal();
        checkRemoveButtons();
    });

    // Kalkulasi subtotal (real-time)
    $(document).on('input', '.qty-input, .price-input', function() {
        var row = $(this).closest('tr');
        var qty = parseFloat(row.find('.qty-input').val()) || 0;
        var price = parseFloat(row.find('.price-input').val()) || 0;
        var subtotal = qty * price;
        
        row.find('.subtotal-display').val(subtotal.toLocaleString('id-ID'));
        calculateGrandTotal();
    });

    function calculateGrandTotal() {
        var grandTotal = 0;
        $('#prTable tbody tr').each(function() {
            var qty = parseFloat($(this).find('.qty-input').val()) || 0;
            var price = parseFloat($(this).find('.price-input').val()) || 0;
            grandTotal += (qty * price);
        });
        $('#grandTotal').val(grandTotal.toLocaleString('id-ID', { style: 'currency', currency: 'IDR' }));
    }

    function checkRemoveButtons() {
        var rowCount = $('#prTable tbody tr').length;
        if (rowCount === 1) {
            $('.btn-remove').prop('disabled', true);
        } else {
            $('.btn-remove').prop('disabled', false);
        }
    }
});
</script>

</body>
</html>
<?php
require_once 'config.php';

// Fungsi sanitasi standar PHP 5.4
function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

// Fungsi khusus menangkap error SQL Server agar terbaca jelas
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
$pr_no = isset($_GET['pr_no']) ? trim($_GET['pr_no']) : '';

if (empty($pr_no)) {
    die("Nomor PR tidak diberikan.");
}

// ==========================================================
// 1. PROSES UPDATE DATA PR
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_pr') {
    $department_id = isset($_POST['department_id']) ? trim($_POST['department_id']) : '';
    $plant_id      = isset($_POST['plant_id']) ? trim($_POST['plant_id']) : ''; 
    $created_by    = isset($_POST['created_by']) ? trim($_POST['created_by']) : '';
    
    $item_codes   = isset($_POST['item_code']) ? $_POST['item_code'] : array();
    $quote_ids    = isset($_POST['quote_id']) ? $_POST['quote_id'] : array(); 
    $qty_requests = isset($_POST['qty_request']) ? $_POST['qty_request'] : array();
    $unit_prices  = isset($_POST['unit_price']) ? $_POST['unit_price'] : array();
    $remarks      = isset($_POST['remark']) ? $_POST['remark'] : array(); 

    // Validasi Pra-Transaksi
    $valid_items_count = 0;
    $total_amount = 0;
    
    for ($i = 0; $i < count($item_codes); $i++) {
        $qty = (float)$qty_requests[$i];
        $qid = isset($quote_ids[$i]) ? (int)$quote_ids[$i] : 0;
        if ($qty > 0 && trim($item_codes[$i]) !== '' && $qid > 0) {
            $valid_items_count++;
            $total_amount += ($qty * (float)$unit_prices[$i]);
        }
    }

    if (!empty($department_id) && !empty($plant_id) && $valid_items_count > 0) {
        if (sqlsrv_begin_transaction($conn) === false) {
            die("Transaction Error: " . get_sql_errors());
        }

        $is_success = true;
        $error_detail = "";

        // Update Header
        $sql_upd_head = "UPDATE PR_Header SET department_id = ?, plant_id = ?, total_amount = ?, created_by = ? WHERE pr_no = ?";
        $stmt_upd = sqlsrv_query($conn, $sql_upd_head, array($department_id, $plant_id, $total_amount, $created_by, $pr_no));
        if (!$stmt_upd) {
            $is_success = false;
            $error_detail .= "Gagal Update Header: " . get_sql_errors();
        }

        // Hapus Detail Lama (Metode aman untuk update detail bersarang di ERP)
        if ($is_success) {
            $sql_del = "DELETE FROM PR_Detail WHERE pr_no = ?";
            $stmt_del = sqlsrv_query($conn, $sql_del, array($pr_no));
            if (!$stmt_del) {
                $is_success = false;
                $error_detail .= "Gagal Hapus Detail Lama: " . get_sql_errors();
            }
        }

        // Insert Detail Baru
        if ($is_success) {
            $sql_det = "INSERT INTO PR_Detail (pr_no, item_code, quote_id, qty_request, qty_received, unit_price, subtotal, remark) 
                        VALUES (?, ?, ?, ?, 0, ?, ?, ?)";
            
            for ($i = 0; $i < count($item_codes); $i++) {
                $icode = trim($item_codes[$i]);
                $qid   = isset($quote_ids[$i]) ? (int)$quote_ids[$i] : 0;
                $qty   = (float)$qty_requests[$i];
                $price = (float)$unit_prices[$i];
                $sub   = $qty * $price;
                $rem   = isset($remarks[$i]) ? trim($remarks[$i]) : '';
                
                if ($qty > 0 && $icode !== '' && $qid > 0) {
                    $stmt_ins = sqlsrv_query($conn, $sql_det, array($pr_no, $icode, $qid, $qty, $price, $sub, $rem));
                    if (!$stmt_ins) {
                        $is_success = false;
                        $error_detail .= "Gagal Insert Detail [{$icode}]: " . get_sql_errors();
                        break;
                    }
                }
            }
        }

        if ($is_success) {
            sqlsrv_commit($conn);
            $message = "<div class='alert alert-success shadow-sm'><strong>Berhasil!</strong> Purchase Request No. <strong>" . h($pr_no) . "</strong> telah diperbarui. <a href='pr_report.php' class='alert-link ml-2'>Kembali ke Laporan</a></div>";
        } else {
            sqlsrv_rollback($conn);
            $message = "<div class='alert alert-danger'>Gagal Update PR: <br><small>{$error_detail}</small></div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Form tidak valid! Pastikan Departemen, Plant, dan minimal 1 Item terisi dengan benar.</div>";
    }
}

// ==========================================================
// 2. LOAD DATA HEADER PR SAAT INI
// ==========================================================
$sql_head = "SELECT * FROM PR_Header WHERE pr_no = ?";
$stmt_head = sqlsrv_query($conn, $sql_head, array($pr_no));
if ($stmt_head === false || !sqlsrv_has_rows($stmt_head)) {
    die("Data Purchase Request tidak ditemukan.");
}
$header = sqlsrv_fetch_array($stmt_head, SQLSRV_FETCH_ASSOC);

// Validasi Keamanan: Hanya status PENDING yang boleh diedit
if ($header['status'] !== 'PENDING') {
    die("<div class='container mt-5'><div class='alert alert-danger'><h4>Akses Ditolak!</h4>Purchase Request dengan nomor <strong>".h($pr_no)."</strong> memiliki status <strong>".h($header['status'])."</strong> dan tidak dapat diedit. <br><br><a href='pr_report.php' class='btn btn-secondary'>Kembali ke Laporan</a></div></div>");
}

// ==========================================================
// 3. LOAD DATA DETAIL PR SAAT INI
// ==========================================================
$sql_det = "SELECT pd.*, mi.item_name, mi.uom, v.vendor_name 
            FROM PR_Detail pd
            INNER JOIN Master_Item mi ON pd.item_code = mi.item_code
            LEFT JOIN Quotation q ON pd.quote_id = q.quote_id
            LEFT JOIN Master_Vendor v ON q.vendor_id = v.vendor_id
            WHERE pd.pr_no = ?";
$stmt_det = sqlsrv_query($conn, $sql_det, array($pr_no));
$details = array();
if ($stmt_det !== false) {
    while ($r = sqlsrv_fetch_array($stmt_det, SQLSRV_FETCH_ASSOC)) {
        $details[] = $r;
    }
}

// Master Department
$sql_dept = "SELECT department_id, department_name, plant_id FROM Master_Department WHERE is_active = 1 ORDER BY department_name ASC";
$stmt_dept = sqlsrv_query($conn, $sql_dept);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Purchase Request - <?php echo h($pr_no); ?></title>
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
            <i class="fas fa-edit"></i> Edit Purchase Request (PR) - <?php echo h($pr_no); ?>
        </div>
        <div class="card-body">
            
            <?php echo $message; ?>

            <form method="POST" action="">
                <input type="hidden" name="action" value="update_pr">

                <h5 class="text-primary border-bottom pb-2 mb-3">1. Data Header (Pemohon)</h5>
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Nomor PR</label>
                        <input type="text" class="form-control font-weight-bold text-center text-primary bg-light" value="<?php echo h($pr_no); ?>" readonly>
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
                                    $selected = ($d['department_id'] === $header['department_id']) ? 'selected' : '';
                                    echo '<option value="'.h($d['department_id']).'" data-plant="'.$plant.'" '.$selected.'>'.$dept_name.'</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Plant / Lokasi <span class="text-danger">*</span></label>
                        <input type="text" id="plantDisplay" class="form-control bg-light" value="<?php echo h($header['plant_id'] . ' - Plant ' . str_replace('P','', $header['plant_id'])); ?>" readonly>
                        <input type="hidden" name="plant_id" id="plantHidden" value="<?php echo h($header['plant_id']); ?>" required>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Dibuat Oleh (Nama) <span class="text-danger">*</span></label>
                        <input type="text" name="created_by" class="form-control" value="<?php echo h($header['created_by']); ?>" required placeholder="Nama Pemohon">
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
                            <?php 
                            $grand_total = 0;
                            foreach ($details as $d): 
                                $subtotal = (float)$d['qty_request'] * (float)$d['unit_price'];
                                $grand_total += $subtotal;
                            ?>
                            <tr>
                                <td>
                                    <!-- Class current-item agar di-load opsi dari AJAX sesuai departemennya -->
                                    <select name="item_code[]" class="form-control select2-item-dynamic current-item" required>
                                        <option value="<?php echo h($d['item_code']); ?>" selected>
                                            <?php echo h($d['item_code'] . ' - ' . $d['item_name'] . ' (' . $d['uom'] . ')'); ?>
                                        </option>
                                    </select>
                                    <input type="hidden" name="quote_id[]" class="quote-input" value="<?php echo (int)$d['quote_id']; ?>" required>
                                </td>
                                <td>
                                    <input type="text" class="form-control vendor-display bg-light" value="<?php echo h($d['vendor_name']); ?>" readonly>
                                </td>
                                <td>
                                    <input type="number" name="qty_request[]" class="form-control qty-input" value="<?php echo (float)$d['qty_request']; ?>" min="0.01" step="any" required>
                                </td>
                                <td>
                                    <input type="number" name="unit_price[]" class="form-control price-input bg-light" value="<?php echo (float)$d['unit_price']; ?>" min="0" step="any" required readonly>
                                </td>
                                <td>
                                    <input type="text" class="form-control subtotal-col subtotal-display" value="<?php echo number_format($subtotal, 0, ',', '.'); ?>" readonly>
                                </td>
                                <td>
                                    <textarea name="remark[]" class="form-control" rows="1" maxlength="300" placeholder="Keterangan opsional..."><?php echo h($d['remark']); ?></textarea>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-danger btn-remove"><i class="fas fa-times"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-right font-weight-bold align-middle">GRAND TOTAL:</td>
                                <td>
                                    <input type="text" id="grandTotal" class="form-control text-primary font-weight-bold" value="<?php echo number_format($grand_total, 0, ',', '.'); ?>" readonly style="background-color: #fff;">
                                </td>
                                <td></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-primary" id="btnAddRow"><i class="fas fa-plus"></i></button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <hr>
                <div class="text-right">
                    <a href="pr_report.php" class="btn btn-secondary btn-lg mr-2"><i class="fas fa-arrow-left"></i> Kembali</a>
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-save"></i> Perbarui Purchase Request
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
    $('.select2-item-dynamic').select2();

    var itemOptions = '<option value="">-- Pilih Barang --</option>';
    var initialLoad = true;

    // Fungsi untuk menarik master barang berdasarkan departemen via AJAX
    function fetchItemsForDept(deptId, callback) {
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
                    if (typeof callback === 'function') { callback(options); }
                }
            });
        }
    }

    // Inisialisasi awal saat halaman dibuka: Load opsi barang untuk departemen yang sedang aktif
    var initialDept = $('#deptSelect').val();
    fetchItemsForDept(initialDept, function(options) {
        $('.current-item').each(function() {
            var currentVal = $(this).val();
            $(this).empty().append(options).val(currentVal).trigger('change.select2');
        });
        initialLoad = false;
    });

    // Event saat departemen diubah
    $('#deptSelect').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var plantValue = selectedOption.data('plant');
        var deptId = $(this).val();

        if (plantValue) {
            $('#plantDisplay').val(plantValue + ' - Plant ' + plantValue.replace('P', ''));
            $('#plantHidden').val(plantValue);
        } else {
            $('#plantDisplay').val('');
            $('#plantHidden').val('');
        }

        // Jika departemen diubah setelah halaman dimuat, reset baris tabel
        if (!initialLoad) {
            $('#prTable tbody').html(`
                <tr>
                    <td>
                        <select name="item_code[]" class="form-control select2-item-dynamic" required>
                            ${itemOptions}
                        </select>
                        <input type="hidden" name="quote_id[]" class="quote-input" required>
                    </td>
                    <td><input type="text" class="form-control vendor-display bg-light" readonly placeholder="Nama Vendor..."></td>
                    <td><input type="number" name="qty_request[]" class="form-control qty-input" min="0.01" step="any" placeholder="0" required></td>
                    <td><input type="number" name="unit_price[]" class="form-control price-input bg-light" min="0" step="any" placeholder="0" required readonly></td>
                    <td><input type="text" class="form-control subtotal-col subtotal-display" value="0" readonly></td>
                    <td><textarea name="remark[]" class="form-control" rows="1" maxlength="300" placeholder="Keterangan opsional..."></textarea></td>
                    <td class="text-center"><button type="button" class="btn btn-sm btn-danger btn-remove" disabled><i class="fas fa-times"></i></button></td>
                </tr>
            `);
            $('#prTable tbody tr:last').find('.select2-item-dynamic').select2();
            calculateGrandTotal();
            checkRemoveButtons();
        }

        fetchItemsForDept(deptId, function(options) {
            itemOptions = options;
        });
    });

    // Event Auto-fill Vendor & Harga saat item dipilih
    $(document).on('change', 'select[name="item_code[]"]', function(e) {
        if (e.namespace !== 'select2') {
            var selected = $(this).find('option:selected');
            var row = $(this).closest('tr');
            
            if (selected.val() !== "" && typeof selected.data('quote') !== 'undefined') {
                row.find('.quote-input').val(selected.data('quote'));
                row.find('.vendor-display').val(selected.data('vendor'));
                row.find('.price-input').val(selected.data('price'));
                row.find('.qty-input').trigger('input');
            } else {
                row.find('.quote-input').val('');
                row.find('.vendor-display').val('');
                row.find('.price-input').val(0);
                row.find('.subtotal-display').val(0);
            }
        }
    });

    // Tambah Baris Detail
    $('#btnAddRow').click(function() {
        var newRow = `
            <tr>
                <td>
                    <select name="item_code[]" class="form-control select2-item-dynamic" required>
                        ${itemOptions}
                    </select>
                    <input type="hidden" name="quote_id[]" class="quote-input" required>
                </td>
                <td><input type="text" class="form-control vendor-display bg-light" readonly placeholder="Nama Vendor..."></td>
                <td><input type="number" name="qty_request[]" class="form-control qty-input" min="0.01" step="any" placeholder="0" required></td>
                <td><input type="number" name="unit_price[]" class="form-control price-input bg-light" min="0" step="any" placeholder="0" required readonly></td>
                <td><input type="text" class="form-control subtotal-col subtotal-display" value="0" readonly></td>
                <td><textarea name="remark[]" class="form-control" rows="1" maxlength="300" placeholder="Keterangan opsional..."></textarea></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-danger btn-remove"><i class="fas fa-times"></i></button></td>
            </tr>
        `;
        $('#prTable tbody').append(newRow);
        $('#prTable tbody tr:last').find('.select2-item-dynamic').select2();
        checkRemoveButtons();
    });

    // Hapus Baris Detail
    $(document).on('click', '.btn-remove', function() {
        $(this).closest('tr').find('select').select2('destroy');
        $(this).closest('tr').remove();
        calculateGrandTotal();
        checkRemoveButtons();
    });

    // Kalkulasi subtotal real-time
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
        $('#grandTotal').val(grandTotal.toLocaleString('id-ID'));
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
<?php
session_start();

// Memanggil koneksi database
require_once __DIR__ . "/../../config/database_ordering.php";

if (!isset($conn) || $conn === false) {
    header("Location: ../login.php?error=session_expired");
    exit();
}

// Helper functions
function generate_doc_number() {
    global $conn;
    
    // Jika koneksi gagal, kembalikan format IMC/YYMM/XXXX secara acak
    if (!$conn) return 'IMC/' . date('ym') . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    sqlsrv_begin_transaction($conn);
    $sql_check = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'GEN_DOC_NUMBER'";
    $stmt = sqlsrv_query($conn, $sql_check);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    if ($row['cnt'] == 0) {
        sqlsrv_query($conn, "CREATE TABLE GEN_DOC_NUMBER (DOC_TYPE VARCHAR(20) PRIMARY KEY, LAST_NUMBER INT DEFAULT 0)");
        sqlsrv_query($conn, "INSERT INTO GEN_DOC_NUMBER (DOC_TYPE, LAST_NUMBER) VALUES ('MATSLIP', 0)");
        $last_number = 1;
    } else {
        $sql = "SELECT LAST_NUMBER FROM GEN_DOC_NUMBER WHERE DOC_TYPE = 'MATSLIP'";
        $stmt = sqlsrv_query($conn, $sql);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($row) {
            $last_number = $row['LAST_NUMBER'] + 1;
        } else {
            $last_number = 1;
            sqlsrv_query($conn, "INSERT INTO GEN_DOC_NUMBER (DOC_TYPE, LAST_NUMBER) VALUES ('MATSLIP', 1)");
        }
    }
    
    sqlsrv_query($conn, "UPDATE GEN_DOC_NUMBER SET LAST_NUMBER = $last_number WHERE DOC_TYPE = 'MATSLIP'");
    sqlsrv_commit($conn);
    
    // RETURN FORMAT BARU: IMC/YYMM/0001
    // date('ym') = 2 digit tahun & 2 digit bulan (Contoh: 2607 untuk Juli 2026)
    // str_pad(..., 4, '0', ...) = membuat angka berurutan menjadi 4 digit (Contoh: 0001)
    return 'IMC/' . date('ym') . '/' . str_pad($last_number, 4, '0', STR_PAD_LEFT);
}

function get_header($doc_number) {
    global $conn;
    if (!$conn) return null;
    $sql = "SELECT * FROM TR_MATERIAL_SLIP_HEADER WHERE TRAN_DOC = ?";
    $params = array($doc_number);
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) return null;
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

function get_details($header_id) {
    global $conn;
    if (!$conn) return array();
    $sql = "SELECT * FROM TR_MATERIAL_SLIP_DETAIL WHERE HEADER_ID = ? ORDER BY ID";
    $params = array($header_id);
    $stmt = sqlsrv_query($conn, $sql, $params);
    $results = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
    return $results;
}

$doc_number = isset($_GET['doc']) ? $_GET['doc'] : null;
$is_edit = false;
$header = null;
$details = array();
$header_id = null;

if ($doc_number) {
    $header = get_header($doc_number);
    if ($header) {
        $is_edit = true;
        $header_id = $header['ID'];
        $details = get_details($header['ID']);
    } else {
        die('Data not found');
    }
}

if (!$is_edit) {
    $new_doc_number = generate_doc_number();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>SURAT PENGANTAR BARANG</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <!-- Tambahan CSS jQuery UI untuk Autocomplete -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <style>
        .form-container { padding: 20px; background-color: #fff; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.05); margin-top: 20px; }
        body { background-color: #f5f5f5; }
        .table-detail { margin-top: 20px; }
        .remove-row { cursor: pointer; color: red; font-weight: bold; }
        .remove-row:hover { color: #cc0000; }
        .required { color: red; }
        .table-detail thead th { background: #f9f9f9; text-align: center; }
        /* Custom UI Autocomplete z-index to stay above other elements */
        .ui-autocomplete { z-index: 9999 !important; font-size: 13px; max-height: 200px; overflow-y: auto; overflow-x: hidden; }
    </style>
</head>
<body>
<div class="container form-container">
    <h2>SURAT PENGANTAR BARANG</h2>
    <hr>
    
    <form id="materialSlipForm" action="save.php" method="POST">
        <?php if ($is_edit): ?>
            <input type="hidden" name="ID" value="<?= $header_id ?>">
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Document Number</label>
                    <input type="text" name="TRAN_DOC" class="form-control" 
                           value="<?= $is_edit ? htmlspecialchars($header['TRAN_DOC']) : $new_doc_number ?>" readonly>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Date <span class="required">*</span></label>
                    <input type="date" name="TRAN_ADATE" class="form-control" 
                           value="<?= $is_edit ? (is_object($header['TRAN_ADATE']) ? $header['TRAN_ADATE']->format('Y-m-d') : date('Y-m-d', strtotime($header['TRAN_ADATE']))) : date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>From (Departemen) <span class="required">*</span></label>
                    <input type="text" name="TRTY_DESC" class="form-control" 
                           value="<?= $is_edit ? htmlspecialchars($header['TRTY_DESC']) : '' ?>" 
                           placeholder="Contoh: Produksi, PPIC" required>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Kepada Yth. (Recipient)</label>
                    <!-- ID recipient_autocomplete DITAMBAHKAN DI SINI -->
                    <input type="text" id="recipient_autocomplete" name="RECIPIENT" class="form-control" 
                           value="<?= $is_edit ? htmlspecialchars(isset($header['RECIPIENT']) ? $header['RECIPIENT'] : '') : '' ?>" 
                           placeholder="Cari Customer / Tujuan...">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>No. Kendaraan</label>
                    <input type="text" name="VEHICLE_NO" class="form-control" 
                           value="<?= $is_edit ? htmlspecialchars(isset($header['VEHICLE_NO']) ? $header['VEHICLE_NO'] : '') : '' ?>" 
                           placeholder="Contoh: B 1234 CD">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Attendance</label>
                    <input type="text" name="BC_NO" class="form-control" 
                           value="<?= ($is_edit && isset($header['BC_NO']) && trim($header['BC_NO']) !== '/') ? htmlspecialchars($header['BC_NO']) : '' ?>" 
                           placeholder="Attendance">
                </div>
            </div>
        </div>
        
        <hr>
        <h4>Detail Items <span class="required">*</span></h4>
        <div class="table-responsive">
            <table class="table table-bordered table-detail" id="detailTable">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th>Material Name</th>
                        <th width="100">Unit</th>
                        <th width="120">Quantity</th>
                        <th>Remark</th>
                        <th width="60">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($is_edit && !empty($details)): ?>
                        <?php $no = 1; foreach ($details as $detail): ?>
                        <tr>
                            <td class="text-center" style="vertical-align: middle;"><?= $no++ ?></td>
                            <td><input type="text" name="ITEM_NAME[]" class="form-control input-sm item-name" value="<?= htmlspecialchars($detail['ITEM_NAME']) ?>"></td>
                            <td><input type="text" name="ITEM_UNIT[]" class="form-control input-sm item-unit" value="<?= htmlspecialchars($detail['ITEM_UNIT']) ?>"></td>
                            <td><input type="text" name="IT_QTY[]" class="form-control input-sm qty text-right" value="<?= number_format($detail['IT_QTY'], 2) ?>"></td>
                            <td><input type="text" name="REMARK[]" class="form-control input-sm" value="<?= htmlspecialchars($detail['REMARK']) ?>"></td>
                            <td class="text-center" style="vertical-align: middle;"><span class="remove-row">✕</span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td class="text-center" style="vertical-align: middle;">1</td>
                            <td><input type="text" name="ITEM_NAME[]" class="form-control input-sm item-name" placeholder="Ketik nama barang..."></td>
                            <td><input type="text" name="ITEM_UNIT[]" class="form-control input-sm item-unit" placeholder="Contoh: Pcs"></td>
                            <td><input type="text" name="IT_QTY[]" class="form-control input-sm qty text-right"></td>
                            <td><input type="text" name="REMARK[]" class="form-control input-sm"></td>
                            <td class="text-center" style="vertical-align: middle;"><span class="remove-row">✕</span></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6">
                            <button type="button" class="btn btn-success btn-sm add-row">
                                <span class="glyphicon glyphicon-plus"></span> Add Row
                            </button>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class="row" style="margin-top:20px;">
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary">
                    <span class="glyphicon glyphicon-save"></span> Save Data
                </button>
                <button type="reset" class="btn btn-warning">
                    <span class="glyphicon glyphicon-refresh"></span> Reset Form
                </button>
                <a href="index.php" class="btn btn-default pull-right">
                    <span class="glyphicon glyphicon-arrow-left"></span> Back to List
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Tambahan script jQuery dan jQuery UI -->
<script src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script>
$(document).ready(function() {
    
    // --- 1. AUTOCOMPLETE UNTUK RECIPIENT (CUSTOMER) ---
    $('#recipient_autocomplete').autocomplete({
        source: function(request, response) {
            $.ajax({
                url: "ajax_customer_autocomplete.php", // Memanggil file customer ajax
                type: "POST",
                dataType: "json",
                data: { q: request.term }, // request.term adalah kata yang diketik
                success: function(data) {
                    response($.map(data, function(item) {
                        return {
                            label: item.CUST_CODE + " - " + item.CUST_COMP, // Tampilan dropdown
                            value: item.CUST_COMP // Nilai yang dimasukkan ke input
                        };
                    }));
                }
            });
        },
        minLength: 2 // Mulai mencari setelah ketik 2 huruf
    });

    // --- TAMBAH BARIS BARU ---
    $('.add-row').on('click', function() {
        var rowCount = $('#detailTable tbody tr').length + 1;
        var newRow = `
            <tr>
                <td class="text-center" style="vertical-align: middle;">${rowCount}</td>
                <td><input type="text" name="ITEM_NAME[]" class="form-control input-sm item-name" placeholder="Ketik nama barang..."></td>
                <td><input type="text" name="ITEM_UNIT[]" class="form-control input-sm item-unit" placeholder="Contoh: Pcs"></td>
                <td><input type="text" name="IT_QTY[]" class="form-control input-sm qty text-right"></td>
                <td><input type="text" name="REMARK[]" class="form-control input-sm"></td>
                <td class="text-center" style="vertical-align: middle;"><span class="remove-row">✕</span></td>
            </tr>
        `;
        $('#detailTable tbody').append(newRow);
        
        updateRowNumbers();
    });
    
    // Hapus Baris
    $('body').on('click', '.remove-row', function() {
        if ($('#detailTable tbody tr').length > 1) {
            $(this).closest('tr').remove();
            updateRowNumbers();
        } else {
            alert('Form minimal harus memiliki 1 baris item/material.');
        }
    });
    
    function updateRowNumbers() {
        $('#detailTable tbody tr').each(function(index) {
            $(this).find('td:first').text(index + 1);
        });
    }
    
    $('#materialSlipForm').on('submit', function(e) {
        var hasData = false;
        $('#detailTable tbody tr').each(function() {
            var itemName = $(this).find('input[name="ITEM_NAME[]"]').val().trim();
            if (itemName) hasData = true;
        });
        if (!hasData) {
            alert('Silakan isi minimal satu detail barang!');
            e.preventDefault();
            return false;
        }
    });
    
    $('body').on('blur', '.qty', function() {
        var val = $(this).val().replace(/,/g, '');
        if (!isNaN(val) && val !== '' && val !== '0') {
            $(this).val(Number(val).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        } else if (val === '') {
            $(this).val('');
        }
    });
});
</script>
</body>
</html>
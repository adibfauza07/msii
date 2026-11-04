<?php
// FILE: input_problem.php

if (!isset($conn) || $conn === false) {
    die("<div class='alert alert-danger'>Koneksi database tidak tersedia.</div>");
}

$pesan = "";

$retained_item_id = "";
$retained_part_code = "";
$retained_text = "";
$retained_part_name = "";
$retained_cust_comp = "";

// ==========================================
// AMBIL DAFTAR PROBLEM DARI DB UNTUK DATALIST
// ==========================================
$problem_options = "";
$sql_ng = "SELECT DISTINCT NGT_DESC FROM dbo.NG_TYPE WHERE NGT_DESC IS NOT NULL AND NGT_DESC <> ''";
$stmt_ng = sqlsrv_query($conn, $sql_ng);
if ($stmt_ng) {
    while ($r = sqlsrv_fetch_array($stmt_ng, SQLSRV_FETCH_ASSOC)) {
        $desc = htmlspecialchars(trim($r['NGT_DESC']));
        $problem_options .= "<option value=\"$desc\">";
    }
}
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    .table-grid input, .table-grid select, .table-grid textarea {
        font-size: 11px; padding: 4px; border: 1px solid transparent; width: 100%; box-sizing: border-box; background: transparent;
    }
    .table-grid input:focus, .table-grid select:focus, .table-grid textarea:focus { border: 1px solid #80bdff; background: #fff; outline: none; }
    .table-grid textarea { resize: vertical; min-height: 35px; }
    .table-grid th { font-size: 11px; text-transform: uppercase; background-color: #f4f6f9 !important; padding: 6px; }
    .table-grid td { padding: 2px; vertical-align: middle; border: 1px solid #dee2e6; }
    .btn-grid { padding: 1px 5px; font-size: 12px; }
    .row-history { background-color: #fdfdfe; }
    .select2-container .select2-selection--single { font-size: 11px; height: 32px; }
</style>

<!-- Memori Datalist (Data NG_TYPE) -->
<datalist id="listProblems">
    <?php echo $problem_options; ?>
</datalist>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-bold"><i class="bi bi-grid-3x3-gap-fill text-primary"></i> Form Input QC Problem (Save Per Baris)</h5>
    </div>
    
    <div id="alert-container"></div>
    
    <!-- Bagian Informasi Part -->
    <div class="card shadow-sm mb-3 border-top border-danger border-3">
        <div class="card-body py-2 px-3">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold mb-1" style="font-size:12px; color:#dc3545;">Part Number / Part Code</label>
                    <select id="select_part" class="form-select form-select-sm" required>
                        <?php if($retained_item_id != ""): ?>
                            <option value="<?php echo $retained_item_id; ?>" selected="selected"><?php echo $retained_text; ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold mb-1" style="font-size:12px;">Part Name</label>
                    <input type="text" id="part_name" class="form-control form-control-sm bg-light" readonly value="<?php echo htmlspecialchars($retained_part_name); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold mb-1" style="font-size:12px;">Customer</label>
                    <input type="text" id="cust_comp" class="form-control form-control-sm bg-light" readonly value="<?php echo htmlspecialchars($retained_cust_comp); ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Grid Data Problem -->
    <div class="card shadow-sm border-top border-primary border-3">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-primary">Data Problem Grid (Simpan per Baris)</h6>
            <div>
                <button type="button" class="btn btn-sm btn-outline-primary fw-bold" id="btnAddRow"><i class="bi bi-plus-lg"></i> Tambah Baris Input</button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0 table-grid" id="tableGridProblem">
                    <thead>
                        <tr>
                            <th width="10%">Tanggal</th>
                            <th width="18%">Problem</th>
                            <th width="8%">Shift</th>
                            <th width="15%">Penangan</th>
                            <th width="15%">Perbaikan</th>
                            <th width="12%">PIC (Koma)</th>
                            <th width="9%">Status</th>
                            <th width="10%">Tmpt Foto</th>
                            <th width="5%" class="text-center"><i class="bi bi-gear"></i> Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Default Baris Input Baru (Belum Tersimpan) -->
                        <tr class="row-input bg-light">
                            <input type="hidden" class="row-id-problem" value="">
                            <td><input type="date" class="row-tanggal" value="<?php echo date('Y-m-d'); ?>" required></td>
                            <td>
                                <input type="text" class="form-control row-problem" list="listProblems" placeholder="Ketik/Pilih Problem..." required autocomplete="off">
                            </td>
                            <td>
                                <select class="row-shift" required>
                                    <option value="">- Pilih -</option>
                                    <option value="A1">A1</option><option value="A2">A2</option><option value="A3">A3</option>
                                    <option value="B1">B1</option><option value="B2">B2</option><option value="B3">B3</option>
                                    <option value="C1">C1</option><option value="C2">C2</option><option value="C3">C3</option>
                                    <option value="Non-Shift">Non-Shift</option>
                                </select>
                            </td>
                            <td><textarea class="row-penangan"></textarea></td>
                            <td><textarea class="row-perbaikan"></textarea></td>
                            <td><input type="text" class="row-pic" required></td>
                            <td>
                                <select class="row-status" required>
                                    <option value="1">OPEN</option>
                                    <option value="2">CLOSED</option>
                                </select>
                            </td>
                            <td><input type="text" class="row-tempat-foto"></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-success btn-grid btn-save-row" title="Simpan Baris Ini"><i class="bi bi-save"></i></button>
                                <button type="button" class="btn btn-danger btn-grid btn-remove-row" title="Hapus Baris"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                        <!-- Baris History akan dimuat via AJAX di bawah sini -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
var shiftList = ['A1','A2','A3','B1','B2','B3','C1','C2','C3','Non-Shift'];

function escapeHtml(text) {
    if (!text) return '';
    var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function showAlert(message, type) {
    if (!type) type = 'success';
    var alertHtml = "<div class='alert alert-" + type + " alert-dismissible fade show'>" + message + " <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    $('#alert-container').html(alertHtml);
    setTimeout(function() { $('.alert').alert('close'); }, 4000);
}

function loadHistory(part_code) {
    $('#tableGridProblem tbody tr.row-history').remove();

    $.ajax({
        url: 'ajax_get_history.php',
        type: 'GET',
        data: { part_code: part_code },
        dataType: 'json',
        success: function(response) {
            if (response.error) {
                showAlert("Gagal load history: " + response.error, 'danger');
                return;
            }

            if(response.length > 0) {
                var historyHtml = '';
                $.each(response, function(index, row) {
                    var shiftOpts = '<option value="">- Pilih -</option>';
                    $.each(shiftList, function(i, val) {
                        var sel = (row.SHIFT == val) ? 'selected' : '';
                        shiftOpts += '<option value="' + val + '" ' + sel + '>' + val + '</option>';
                    });
                    
                    var st_open = (row.STATUS == 'OPEN') ? 'selected' : '';
                    var st_closed = (row.STATUS == 'CLOSED') ? 'selected' : '';
                    var safe_problem = escapeHtml(row.PROBLEM);
                    var safe_tanggal = row.TANGGAL ? row.TANGGAL.substring(0, 10) : new Date().toISOString().split('T')[0];

                    historyHtml += '<tr class="row-history" id="tr_hist_' + row.ID_PROBLEM + '">' +
                        '<input type="hidden" class="row-id-problem" value="' + row.ID_PROBLEM + '">' +
                        '<td><input type="date" class="row-tanggal" value="' + safe_tanggal + '" required></td>' +
                        '<td><input type="text" class="form-control row-problem" list="listProblems" value="' + safe_problem + '" required autocomplete="off"></td>' +
                        '<td><select class="row-shift" required>' + shiftOpts + '</select></td>' +
                        '<td><textarea class="row-penangan">' + (row.Penangan || '') + '</textarea></td>' +
                        '<td><textarea class="row-perbaikan">' + (row.Perbaikan || '') + '</textarea></td>' +
                        '<td><input type="text" class="row-pic" value="' + (row.PIC || '') + '" required></td>' +
                        '<td><select class="row-status" required><option value="1" ' + st_open + '>OPEN</option><option value="2" ' + st_closed + '>CLOSED</option></select></td>' +
                        '<td><input type="text" class="row-tempat-foto" value="' + (row.tempat_foto || '') + '"></td>' +
                        '<td class="text-center text-nowrap">' +
                            '<button type="button" class="btn btn-success btn-grid btn-save-row" title="Update Baris Ini"><i class="bi bi-save"></i></button> ' +
                            '<button type="button" class="btn btn-outline-danger btn-grid" onclick="hapusProblem(' + row.ID_PROBLEM + ', this)" title="Hapus Permanen"><i class="bi bi-trash-fill"></i></button>' +
                        '</td>' +
                    '</tr>';
                });
                
                $("#tableGridProblem tbody").append(historyHtml);
            }
        },
        error: function(xhr) {
            console.error("AJAX Error:", xhr.responseText);
        }
    });
}

$(document).ready(function() {
    // Inisialisasi Select2 Part
    $('#select_part').select2({
        theme: 'bootstrap-5',
        placeholder: 'Cari Part...',
        minimumInputLength: 2,
        ajax: {
            url: 'ajax_get_item.php', 
            dataType: 'json',
            delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: data.results }; },
            cache: true
        }
    });

    $('#select_part').on('select2:select', function (e) {
        var data = e.params.data;
        $('#part_name').val(data.part_name);
        $('#cust_comp').val(data.cust_comp);
        loadHistory(data.part_code);
    });

    <?php if($retained_part_code != ""): ?>
        loadHistory('<?php echo $retained_part_code; ?>');
    <?php endif; ?>

    // Tambah Baris Input Baru di Grid
    $("#btnAddRow").click(function() {
        var today = new Date().toISOString().split('T')[0];
        var shiftOpts = '<option value="">- Pilih -</option>';
        $.each(shiftList, function(i, val) { shiftOpts += '<option value="' + val + '">' + val + '</option>'; });

        var newRow = '<tr class="row-input bg-light">' +
            '<input type="hidden" class="row-id-problem" value="">' +
            '<td><input type="date" class="row-tanggal" value="' + today + '" required></td>' +
            '<td><input type="text" class="form-control row-problem" list="listProblems" placeholder="Ketik/Pilih Problem..." required autocomplete="off"></td>' +
            '<td><select class="row-shift" required>' + shiftOpts + '</select></td>' +
            '<td><textarea class="row-penangan"></textarea></td>' +
            '<td><textarea class="row-perbaikan"></textarea></td>' +
            '<td><input type="text" class="row-pic" required></td>' +
            '<td><select class="row-status" required><option value="1">OPEN</option><option value="2">CLOSED</option></select></td>' +
            '<td><input type="text" class="row-tempat-foto"></td>' +
            '<td class="text-center text-nowrap">' +
                '<button type="button" class="btn btn-success btn-grid btn-save-row" title="Simpan Baris Ini"><i class="bi bi-save"></i></button> ' +
                '<button type="button" class="btn btn-danger btn-grid btn-remove-row" title="Hapus Baris"><i class="bi bi-trash"></i></button>' +
            '</td>' +
        '</tr>';
        
        $("#tableGridProblem tbody").prepend(newRow);
    });

    $(document).on('click', '.btn-remove-row', function() {
        $(this).closest('tr').remove();
    });

    // FUNGSI SIMPAN FOKUS PER BARIS (AJAX)
    $(document).on('click', '.btn-save-row', function() {
        var tr = $(this).closest('tr');
        var item_id = $('#select_part').val();

        if (!item_id) {
            alert("Silakan pilih Part Number / Part Code terlebih dahulu pada bagian atas!");
            $('#select_part').select2('open');
            return;
        }

        var dataRow = {
            item_id: item_id,
            id_problem: tr.find('.row-id-problem').val(),
            tanggal: tr.find('.row-tanggal').val(),
            problem: tr.find('.row-problem').val(),
            shift: tr.find('.row-shift').val(),
            penangan: tr.find('.row-penangan').val(),
            perbaikan: tr.find('.row-perbaikan').val(),
            pic: tr.find('.row-pic').val(),
            status_id: tr.find('.row-status').val(),
            tempat_foto: tr.find('.row-tempat-foto').val()
        };

        if (!dataRow.problem.trim()) {
            alert("Kolom Problem tidak boleh kosong!");
            tr.find('.row-problem').focus();
            return;
        }
        if (!dataRow.shift) {
            alert("Pilih Shift terlebih dahulu!");
            tr.find('.row-shift').focus();
            return;
        }
        if (!dataRow.pic.trim()) {
            alert("PIC tidak boleh kosong!");
            tr.find('.row-pic').focus();
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true);

        $.ajax({
            url: 'ajax_save_problem.php',
            type: 'POST',
            data: dataRow,
            dataType: 'json',
            success: function(res) {
                btn.prop('disabled', false);
                if (res.status === 'success') {
                    showAlert(res.message, 'success');
                    var selectedData = $('#select_part').select2('data');
                    if (selectedData && selectedData.length > 0 && selectedData[0].part_code) {
                        loadHistory(selectedData[0].part_code);
                    } else if ('<?php echo $retained_part_code; ?>' !== '') {
                        loadHistory('<?php echo $retained_part_code; ?>');
                    }
                } else {
                    alert("Gagal menyimpan: " + res.message);
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false);
                console.error("AJAX Error:", xhr.responseText);
                alert("Terjadi kesalahan sistem saat menyimpan baris ini.");
            }
        });
    });
});

function hapusProblem(id, btn) {
    if (confirm("Yakin ingin menghapus data problem ini secara permanen dari database?")) {
        $.ajax({
            url: 'ajax_delete_problem.php',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if (res.status == 'success') {
                    showAlert("Data berhasil dihapus.", 'success');
                    $(btn).closest('tr').fadeOut(300, function() { $(this).remove(); });
                } else { 
                    alert("Gagal menghapus: " + res.message); 
                }
            },
            error: function() { 
                alert("Terjadi kesalahan sistem saat menghapus data."); 
            }
        });
    }
}
</script>
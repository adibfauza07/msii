<?php
require_once "../middleware/Auth.php";
require_once "../middleware/RoleCheck.php";
only(['p2', 'admin']);

require "../config/database.php";

$page       = isset($_GET['page'])    ? intval($_GET['page'])    : 1;
$mac_id_get = isset($_GET['mac_id'])  ? intval($_GET['mac_id'])  : 0;

$sqlTotal = "SELECT COUNT(*) AS JML FROM MAC_MTN WHERE MAC_ACTIVE = 1";
$rTotal   = sqlsrv_query($conn,$sqlTotal);
$rowTotal = sqlsrv_fetch_array($rTotal, SQLSRV_FETCH_ASSOC);
$total    = (int)$rowTotal['JML'];

if ($total < 1) die("Tidak ada machine aktif!");

$perPage = 1;
$maxPage = ceil($total / $perPage);

$cte = "
WITH X AS (
    SELECT MAC_ID, MAC, PLANT, TONAGE,
           ROW_NUMBER() OVER (ORDER BY PLANT ASC, MAC ASC) AS RN
    FROM MAC_MTN WHERE MAC_ACTIVE = 1
)
";

if ($mac_id_get > 0) {
    $sql =$cte . " SELECT * FROM X WHERE MAC_ID = ?";
    $m = sqlsrv_query($conn, $sql, [$mac_id_get]);
    $master = sqlsrv_fetch_array($m, SQLSRV_FETCH_ASSOC);
    if (!$master) die("Machine dengan MAC_ID tsb tidak ditemukan!");
    $page = (int)$master['RN'];
} else {
    if ($page < 1)$page = 1;
    if ($page >$maxPage) $page =$maxPage;
    $sql =$cte . " SELECT * FROM X WHERE RN = ?";
    $m = sqlsrv_query($conn, $sql, [$page]);
    $master = sqlsrv_fetch_array($m, SQLSRV_FETCH_ASSOC);
    if (!$master) die("Data master tidak ditemukan!");
    $mac_id_get =$master["MAC_ID"];
}

$mac_id = (int)$master['MAC_ID'];
$mac    =$master['MAC'];
$plant  =$master['PLANT'];
$tonage =$master['TONAGE'];

$sqlDept = "SELECT DEP_CODE, DEP_NAME FROM DEPT ORDER BY DEP_NAME";
$deptRes = sqlsrv_query($conn, $sqlDept);$DEPTS = [];
while($d = sqlsrv_fetch_array($deptRes, SQLSRV_FETCH_ASSOC)){
    $DEPTS[] =$d;
}

$sqlDet = "SELECT CARNO, ISSUE_DATE, PROBLEM, DEPT_CODE, EFFECTIVE_DATE, PIC, CLOSED, TANGGAL_PENGEMBALIAN, TANDA_PENGEMBALIAN
           FROM MTN_HISTORY_CARNO WHERE ID_MAC = ? ORDER BY ISSUE_DATE DESC, CARNO ASC";
$det = sqlsrv_query($conn, $sqlDet, [$mac_id]);

function tglDMY($dt){
    if (!$dt) return "";
    if ($dt instanceof DateTime) return$dt->format("d-M-Y");
    return date("d-M-Y", strtotime($dt));
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>CAR Maintenance</title>
    
    <!-- Memanfaatkan FontAwesome & SweetAlert2 agar lebih keren Bro! -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <script src="../assets/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { background: #f4f6f9; padding: 20px; font-family: 'Segoe UI', Arial, sans-serif; }
        .page-title { color: #333; font-weight: 600; text-align: center; margin-bottom: 25px; }
        
        /* Card Layout untuk Master Info */
        .master-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 20px; margin-bottom: 20px; border-left: 5px solid #007bff; }
        .master-label { font-size: 12px; font-weight: bold; color: #6c757d; text-transform: uppercase; margin-bottom: 5px; display: block; }
        .master-value { font-size: 16px; font-weight: bold; color: #212529; padding: 6px 12px; background: #e9ecef; border-radius: 4px; display: block; }
        
        /* Grid Table Styling */
        .grid-wrapper { height: 50vh; overflow-y: auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #dee2e6; }
        table.grid { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        table.grid th, table.grid td { border: 1px solid #e9ecef; font-size: 13px; padding: 8px; vertical-align: middle; }
        
        /* Sticky Header */
        table.grid thead th { background: #343a40; color: #fff; text-align: center; position: sticky; top: 0; z-index: 10; font-weight: 500; }
        table.grid tbody tr:hover { background: #f8f9fa; }
        
        textarea, input[type=text], select { width: 100%; border: 1px solid #ced4da; border-radius: 4px; padding: 5px; outline: none; transition: 0.2s; }
        textarea:focus, input[type=text]:focus, select:focus { border-color: #80bdff; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); }
        textarea { height: 50px; resize: none; }
        .dtpicker { background: #fff url('https://cdn-icons-png.flaticon.com/512/5968/5968523.png') no-repeat right 8px center; background-size: 14px; cursor: pointer; padding-right: 25px; }
        .chk { text-align: center; }
        
        /* Pagination */
        .pagination-box { display: flex; align-items: center; gap: 5px; }
    </style>
</head>
<body>

<!-- MODAL LOOKUP -->
<div class="modal fade" id="lookupMachine" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-search"></i> Cari Mesin</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="text" id="lookupSearch" class="form-control mb-3" placeholder="Ketik MAC / Plant / Tonage...">
                <div style="max-height:400px; overflow:auto;">
                    <table class="table table-bordered table-hover text-center">
                        <thead class="bg-light">
                            <tr><th>MAC</th><th>PLANT</th><th>TONAGE</th><th width="80">Aksi</th></tr>
                        </thead>
                        <tbody id="lookupBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="dashboard_mtn.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
    <div class="pagination-box">
        <a class="btn btn-sm btn-secondary" href="?page=1"><i class="fas fa-angle-double-left"></i></a>
        <a class="btn btn-sm btn-secondary <?= ($page <= 1) ? 'disabled' : '' ?>" href="?page=<?=($page-1)?>"><i class="fas fa-angle-left"></i></a>
        <span class="badge bg-primary px-3 py-2 text-white" style="font-size:14px;">Baris <?= $page ?> / <?= $maxPage ?></span>
        <a class="btn btn-sm btn-secondary <?= ($page >= $maxPage) ? 'disabled' : '' ?>" href="?page=<?=($page+1)?>"><i class="fas fa-angle-right"></i></a>
        <a class="btn btn-sm btn-secondary" href="?page=<?=$maxPage?>"><i class="fas fa-angle-double-right"></i></a>
    </div>
</div>

<h2 class="page-title"><i class="fas fa-tools"></i> CAR MAINTENANCE</h2>

<form id="formCar">
    <input type="hidden" name="mac_id" value="<?=$mac_id?>">
    <input type="hidden" name="mac" value="<?=$mac?>">
    <input type="hidden" name="plant" value="<?=$plant?>">

    <!-- MASTER CARD INFO -->
    <div class="master-card">
        <div class="row">
            <div class="col-md-4">
                <span class="master-label">Machine (MAC)</span>
                <div class="input-group">
                    <input class="form-control font-weight-bold" readonly value="<?=htmlspecialchars($mac)?>">
                    <div class="input-group-append">
                        <button type="button" id="btnLookup" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <span class="master-label">Plant</span>
                <span class="master-value"><?=htmlspecialchars($plant)?></span>
            </div>
            <div class="col-md-4">
                <span class="master-label">Tonage</span>
                <span class="master-value"><?=htmlspecialchars($tonage)?></span>
            </div>
        </div>
    </div>

    <div class="d-flex mb-2 gap-2">
        <button type="button" id="btnAdd" class="btn btn-success btn-sm mr-2"><i class="fas fa-plus"></i> Tambah Baris</button>
        <button type="submit" id="btnSave" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Simpan Data</button>
    </div>

    <div class="grid-wrapper">
        <table class="grid">
            <thead>
                <tr>
                    <th width="120">CAR.NO</th>
                    <th width="120">ISSUE DATE</th>
                    <th width="200">PROBLEM</th>
                    <th width="140">DEPT</th>
                    <th width="120">EFFECTIVE</th>
                    <th width="120">PIC</th>
                    <th width="60">CLOSE</th>
                    <th width="60">KEMBALI</th>
                    <th width="120">TGL KEMBALI</th>
                    <th width="50">HAPUS</th>
                </tr>
            </thead>
            <tbody id="detailBody">
            <?php $i=0; while($r = sqlsrv_fetch_array($det, SQLSRV_FETCH_ASSOC)): ?>
                <tr>
                    <td>
                        <input type="hidden" name="rows[<?=$i?>][carno_old]" value="<?=htmlspecialchars($r['CARNO'])?>">
                        <input type="text" name="rows[<?=$i?>][carno]" value="<?=htmlspecialchars($r['CARNO'])?>">
                    </td>
                    <td><input type="text" class="dtpicker" name="rows[<?=$i?>][issue_date]" value="<?=tglDMY($r['ISSUE_DATE'])?>"></td>
                    <td><textarea name="rows[<?=$i?>][problem]"><?=htmlspecialchars($r['PROBLEM'])?></textarea></td>
                    <td>
                        <select name="rows[<?=$i?>][dept_code]">
                            <option value="">- Pilih -</option>
                            <?php foreach($DEPTS as$d): ?>
                                <option value="<?=$d['DEP_CODE']?>" <?=$d['DEP_CODE']==$r['DEPT_CODE']?'selected':''?>><?=$d['DEP_CODE']?> - <?=$d['DEP_NAME']?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="text" class="dtpicker" name="rows[<?=$i?>][effective_date]" value="<?=tglDMY($r['EFFECTIVE_DATE'])?>"></td>
                    <td><input type="text" name="rows[<?=$i?>][pic]" value="<?=htmlspecialchars($r['PIC'])?>"></td>
                    <td class="chk"><input type="checkbox" name="rows[<?=$i?>][closed]" value="1" <?=$r['CLOSED']?'checked':''?>></td>
                    <td class="chk"><input type="checkbox" name="rows[<?=$i?>][tanda_pengembalian]" value="1" <?=$r['TANDA_PENGEMBALIAN']?'checked':''?>></td>
                    <td><input type="text" class="dtpicker" name="rows[<?=$i?>][tgl_pengembalian]" value="<?=tglDMY($r['TANGGAL_PENGEMBALIAN'])?>"></td>
                    <td class="chk"><button type="button" class="btn btn-outline-danger btn-sm delRow" data-carno="<?=htmlspecialchars($r['CARNO'])?>"><i class="fas fa-trash"></i></button></td>
                </tr>
            <?php $i++; endwhile; ?>
            </tbody>
        </table>
    </div>
</form>

<script>
function initDP(){
    $(".dtpicker").datepicker({ dateFormat:"dd-M-yy", changeMonth:true, changeYear:true });
}
initDP();

let rowIndex = <?=$i?>;

// ============= ADD BARIS BARU ===================
$('#btnAdd').click(function(){
    let i = rowIndex++;
    let row = `
    <tr>
        <td><input type="hidden" name="rows[${i}][carno_old]" value=""><input type="text" name="rows[${i}][carno]"></td>
        <td><input type="text" class="dtpicker" name="rows[${i}][issue_date]"></td>
        <td><textarea name="rows[${i}][problem]"></textarea></td>
        <td>
            <select name="rows[${i}][dept_code]">
                <option value="">- Pilih -</option>
                <?php foreach($DEPTS as $d): ?><option value="<?=$d['DEP_CODE']?>"><?=$d['DEP_CODE']?> - <?=$d['DEP_NAME']?></option><?php endforeach; ?>
            </select>
        </td>
        <td><input type="text" class="dtpicker" name="rows[${i}][effective_date]"></td>
        <td><input type="text" name="rows[${i}][pic]"></td>
        <td class="chk"><input type="checkbox" name="rows[${i}][closed]" value="1"></td>
        <td class="chk"><input type="checkbox" name="rows[${i}][tanda_pengembalian]" value="1"></td>
        <td><input type="text" class="dtpicker" name="rows[${i}][tgl_pengembalian]"></td>
        <td class="chk"><button type="button" class="btn btn-outline-danger btn-sm delRowNew"><i class="fas fa-times"></i></button></td>
    </tr>`;
    $('#detailBody').prepend(row);
    initDP();
});

// ============= SAVE VIA AJAX (SWEETALERT2) ===================
$('#formCar').submit(function(e){
    e.preventDefault();
    $('#btnSave').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');

    $.post("car_save_ajax.php", $(this).serialize(), function(res){$('#btnSave').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan Data');
        if (res.status === "ok"){
            Swal.fire('Berhasil!', 'Data telah disimpan.', 'success').then(() => { location.reload(); });
        } else {
            Swal.fire('Gagal!', res.message || 'Terjadi kesalahan sistem.', 'error');
        }
    }, 'json').fail(function(){
        $('#btnSave').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan Data');
        Swal.fire('Error!', 'AJAX Error / Koneksi bermasalah', 'error');
    });
});

// ============= DELETE BARIS LAMA (SWEETALERT2) ===================
$(document).on('click', '.delRow', function(){
    let carno = $(this).data('carno');
    let tr = $(this).closest("tr");

    Swal.fire({
        title: 'Yakin hapus data?',
        text: "CARNO: " + carno + " akan dihapus permanen!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post("car_delete.php", {carno:carno}, function(res){
                if (res.status === "ok"){
                    tr.fadeOut(400, function(){ $(this).remove(); });
                    Swal.fire('Terhapus!', 'Data berhasil dihapus.', 'success');
                } else {
                    Swal.fire('Gagal!', res.message, 'error');
                }
            }, 'json');
        }
    });
});

// ============= DELETE BARIS BARU ===================
$(document).on('click', '.delRowNew', function(){
    $(this).closest('tr').fadeOut(300, function(){$(this).remove(); });
});

// ============= LOOKUP MACHINE ===================
$('#btnLookup').click(function(){
    $('#lookupMachine').modal('show');
    $('#lookupSearch').val('');
    loadLookup('');
});

function loadLookup(k){
    $.get("lookup_machine_ajax.php", {q:k}, function(res){
        $('#lookupBody').html('');
        res.forEach(r => {
            $('#lookupBody').append(`
                <tr>
                    <td>${r.MAC}</td>
                    <td>${r.PLANT}</td>
                    <td>${r.TONAGE}</td>
                    <td><button type="button" class="btn btn-sm btn-success pickMachine" data-id="${r.MAC_ID}"><i class="fas fa-check"></i></button></td>
                </tr>
            `);
        });
    }, 'json');
}

$('#lookupSearch').keyup(function(){ loadLookup($(this).val()); });$(document).on("click", ".pickMachine", function(){
    window.location.href = "Car_Maintenance.php?mac_id=" + $(this).data("id");
});
</script>
</body>
</html>
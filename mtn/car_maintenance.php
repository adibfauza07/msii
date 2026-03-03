<?php
// =======================================================
// SECURITY
// =======================================================
require_once "../middleware/Auth.php";
require_once "../middleware/RoleCheck.php";
only(['p2', 'admin']);   // sesuaikan role

require "../config/database.php";


// =======================================================
// PARAMETER
// =======================================================
$page       = isset($_GET['page'])    ? intval($_GET['page'])    : 1;
$mac_id_get = isset($_GET['mac_id'])  ? intval($_GET['mac_id'])  : 0;


// =======================================================
// HITUNG TOTAL MESIN AKTIF
// =======================================================
$sqlTotal = "SELECT COUNT(*) AS JML FROM MAC_MTN WHERE MAC_ACTIVE = 1";
$rTotal   = sqlsrv_query($conn, $sqlTotal);
$rowTotal = sqlsrv_fetch_array($rTotal, SQLSRV_FETCH_ASSOC);
$total    = (int)$rowTotal['JML'];

if ($total < 1) die("Tidak ada machine aktif!");

$perPage = 1;
$maxPage = ceil($total / $perPage);


// =======================================================
// CTE MASTER (sama seperti daily maintenance)
// =======================================================
$cte = "
WITH X AS (
    SELECT
        MAC_ID,
        MAC,
        PLANT,
        TONAGE,
        ROW_NUMBER() OVER (ORDER BY PLANT ASC, MAC ASC) AS RN
    FROM MAC_MTN
    WHERE MAC_ACTIVE = 1
)
";


// =======================================================
// MODE LOOKUP: jika dipanggil pakai ?mac_id=xxx
// =======================================================
if ($mac_id_get > 0) {

    $sql = $cte . " SELECT * FROM X WHERE MAC_ID = ?";
    $m = sqlsrv_query($conn, $sql, array($mac_id_get));
    $master = sqlsrv_fetch_array($m, SQLSRV_FETCH_ASSOC);

    if (!$master) die("Machine dengan MAC_ID tsb tidak ditemukan!");

    // set page sesuai RN mesin
    $page = (int)$master['RN'];

} 
// =======================================================
// MODE PAGING BIASA
// =======================================================
else {

    if ($page < 1) $page = 1;
    if ($page > $maxPage) $page = $maxPage;

    $sql = $cte . " SELECT * FROM X WHERE RN = ?";
    $m = sqlsrv_query($conn, $sql, array($page));
    $master = sqlsrv_fetch_array($m, SQLSRV_FETCH_ASSOC);

    if (!$master) die("Data master tidak ditemukan!");

    $mac_id_get = $master["MAC_ID"];
}


// =======================================================
// AMBIL DATA MASTER
// =======================================================
$mac_id = (int)$master['MAC_ID'];
$mac    = $master['MAC'];
$plant  = $master['PLANT'];
$tonage = $master['TONAGE'];


// =======================================================
// LOOKUP DEPT
// =======================================================
$sqlDept = "SELECT DEP_CODE, DEP_NAME FROM DEPT ORDER BY DEP_NAME";
$deptRes = sqlsrv_query($conn, $sqlDept);

$DEPTS = array();
while($d = sqlsrv_fetch_array($deptRes, SQLSRV_FETCH_ASSOC)){
    $DEPTS[] = $d;
}


// =======================================================
// DETAIL CAR (MTN_HISTORY_CARNO)
// =======================================================
$sqlDet = "
    SELECT
        CARNO,
        ISSUE_DATE,
        PROBLEM,
        DEPT_CODE,
        EFFECTIVE_DATE,
        PIC,
        CLOSED,
        TANGGAL_PENGEMBALIAN,
        TANDA_PENGEMBALIAN
    FROM MTN_HISTORY_CARNO
    WHERE ID_MAC = ?
    ORDER BY ISSUE_DATE DESC, CARNO ASC
";

$det = sqlsrv_query($conn, $sqlDet, array($mac_id));


// =======================================================
// HELPER TANGGAL
// =======================================================
function tglDMY($dt){
    if (!$dt) return "";
    if ($dt instanceof DateTime) return $dt->format("d-M-Y");
    return date("d-M-Y", strtotime($dt));
}

?>
<!DOCTYPE html>
<html>
<head>
<title>CAR Maintenance</title>

<link rel="stylesheet" href="../assets/bootstrap.min.css">
<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="../assets/bootstrap.min.js"></script>

<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

<style>
body{ background:#eee; padding:15px; font-family:Arial; }
h2{ text-align:center; margin-bottom:20px; }

.form-control-ro{
    width:200px; padding:5px;
    border:1px solid #777; background:white; font-weight:bold;
}

.grid-wrapper{
    height:520px; background:white;
    border:1px solid #666; overflow:auto;
}

table.grid{ width:100%; border-collapse:collapse; }
table.grid th, table.grid td{
    border:1px solid #999; font-size:12px; padding:4px;
}
table.grid th{ background:#ddd; text-align:center; }

textarea{ width:100%; height:60px; border:none; resize:none; }
input[type=text], select{ width:100%; border:none; height:26px; }
.dtpicker{ width:100%; border:none; height:26px; cursor:pointer; background:white; }

.chk { text-align:center; }

</style>
</head>
<body>
<div class="modal fade" id="lookupMachine" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Search Machine</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                
                <input type="text" id="lookupSearch" class="form-control mb-2" 
                       placeholder="Cari MAC / Plant / Tonage...">

                <div style="max-height:400px; overflow:auto;">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>MAC</th>
                                <th>PLANT</th>
                                <th>TONAGE</th>
                                <th width="50">Pilih</th>
                            </tr>
                        </thead>
                        <tbody id="lookupBody"></tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<a href="dashboard_mtn.php" class="btn btn-secondary mb-2">← Kembali ke Menu</a>

<h2>CAR MAINTENANCE</h2>


<!-- NAVIGASI -->
<div class="mb-3">
    <a class="btn btn-sm btn-secondary" href="?page=1">|&lt;</a>

    <?php if ($page > 1): ?>
        <a class="btn btn-sm btn-secondary" href="?page=<?=($page-1)?>">&lt;</a>
    <?php else: ?>
        <button class="btn btn-sm btn-secondary" disabled>&lt;</button>
    <?php endif; ?>

    <?php if ($page < $maxPage): ?>
        <a class="btn btn-sm btn-secondary" href="?page=<?=($page+1)?>">&gt;</a>
    <?php else: ?>
        <button class="btn btn-sm btn-secondary" disabled>&gt;</button>
    <?php endif; ?>

    <a class="btn btn-sm btn-secondary" href="?page=<?=$maxPage?>">&gt;|</a>

    <span style="margin-left:10px;">Record <?= $page ?> / <?= $maxPage ?></span>
</div>


<form id="formCar">

<input type="hidden" name="mac_id" value="<?=$mac_id?>">
<input type="hidden" name="mac" value="<?=$mac?>">
<input type="hidden" name="plant" value="<?=$plant?>">

<table>
<tr>
    <td width="80"><b>MACHINE</b></td>
    <td>
        <div style="display:flex; gap:5px;">
            <input class="form-control-ro" readonly value="<?=htmlspecialchars($mac)?>">
            <button type="button" id="btnLookup" class="btn btn-info btn-sm">🔍</button>
        </div>
    </td>
</tr>
<tr>
    <td><b>PLANT</b></td>
    <td><input class="form-control-ro" readonly value="<?=$plant?>"></td>
</tr>
<tr>
    <td><b>TONAGE</b></td>
    <td><input class="form-control-ro" readonly value="<?=$tonage?>"></td>
</tr>
</table>

<br>

<button type="button" id="btnAdd" class="btn btn-success btn-sm">+ Add</button>
<button type="submit" id="btnSave" class="btn btn-primary btn-sm">Save</button>
<span id="msg"></span>

<div class="grid-wrapper mt-2">
<table class="grid">
<thead>
<tr>
    <th width="110">CAR.NO</th>
    <th width="110">ISSUE DATE</th>
    <th width="200">PROBLEM</th>
    <th width="130">DEPT</th>
    <th width="130">EFFECTIVE DATE</th>
    <th width="120">PIC</th>
    <th width="50">CLOSE</th>
    <th width="130">PENGEMBALIAN</th>
    <th width="130">TGL PENGEMBALIAN</th>
    <th width="50">DEL</th>
</tr>
</thead>
<tbody id="detailBody">

<?php $i=0; while($r = sqlsrv_fetch_array($det, SQLSRV_FETCH_ASSOC)): ?>
<tr>

<td>
    <input type="hidden" name="rows[<?=$i?>][carno_old]" value="<?=$r['CARNO']?>">
    <input type="text" name="rows[<?=$i?>][carno]" value="<?=$r['CARNO']?>">
</td>

<td>
    <input type="text" class="dtpicker" name="rows[<?=$i?>][issue_date]"
           value="<?=tglDMY($r['ISSUE_DATE'])?>">
</td>

<td>
    <textarea name="rows[<?=$i?>][problem]"><?=$r['PROBLEM']?></textarea>
</td>

<td>
    <select name="rows[<?=$i?>][dept_code]">
        <option value="">-- pilih --</option>
        <?php foreach($DEPTS as $d): ?>
            <option value="<?=$d['DEP_CODE']?>"
                <?=$d['DEP_CODE']==$r['DEPT_CODE']?'selected':''?>>
                <?=$d['DEP_CODE']?> - <?=$d['DEP_NAME']?>
            </option>
        <?php endforeach; ?>
    </select>
</td>

<td>
    <input type="text" class="dtpicker"
           name="rows[<?=$i?>][effective_date]"
           value="<?=tglDMY($r['EFFECTIVE_DATE'])?>">
</td>

<td><input type="text" name="rows[<?=$i?>][pic]" value="<?=$r['PIC']?>"></td>

<td class="chk">
    <input type="checkbox" name="rows[<?=$i?>][closed]" value="1"
        <?=$r['CLOSED']?'checked':''?>>
</td>

<td class="chk">
    <input type="checkbox" name="rows[<?=$i?>][tanda_pengembalian]" value="1"
        <?=$r['TANDA_PENGEMBALIAN']?'checked':''?>>
</td>

<td>
    <input type="text" class="dtpicker"
           name="rows[<?=$i?>][tgl_pengembalian]"
           value="<?=tglDMY($r['TANGGAL_PENGEMBALIAN'])?>">
</td>

<td class="chk">
    <button type="button" class="btn btn-danger btn-sm delRow"
            data-carno="<?=$r['CARNO']?>">X</button>
</td>

</tr>
<?php $i++; endwhile; ?>

</tbody>
</table>
</div>

</form>


<!-- =======================================================
     JAVASCRIPT
======================================================= -->
<script>
function initDP(){
    $(".dtpicker").datepicker({
        dateFormat:"dd-M-yy",
        changeMonth:true,
        changeYear:true
    });
}
initDP();

let rowIndex = <?=$i?>;

// ============= ADD BARIS BARU ===================
$('#btnAdd').click(function(){
    let i = rowIndex++;

    let row = `
<tr>
<td>
    <input type="hidden" name="rows[${i}][carno_old]" value="">
    <input type="text" name="rows[${i}][carno]">
</td>
<td><input type="text" class="dtpicker" name="rows[${i}][issue_date]"></td>
<td><textarea name="rows[${i}][problem]"></textarea></td>
<td>
    <select name="rows[${i}][dept_code]">
        <option value="">-- pilih --</option>
        <?php foreach($DEPTS as $d): ?>
        <option value="<?=$d['DEP_CODE']?>"><?=$d['DEP_CODE']?> - <?=$d['DEP_NAME']?></option>
        <?php endforeach; ?>
    </select>
</td>
<td><input type="text" class="dtpicker" name="rows[${i}][effective_date]"></td>
<td><input type="text" name="rows[${i}][pic]"></td>
<td class="chk"><input type="checkbox" name="rows[${i}][closed]" value="1"></td>
<td class="chk"><input type="checkbox" name="rows[${i}][tanda_pengembalian]" value="1"></td>
<td><input type="text" class="dtpicker" name="rows[${i}][tgl_pengembalian]"></td>
<td class="chk"><button type="button" class="btn btn-danger btn-sm delRowNew">X</button></td>
</tr>
`;
    $('#detailBody').prepend(row);
    initDP();
});



// ============= SAVE VIA AJAX ===================
$('#formCar').submit(function(e){
    e.preventDefault();

    $('#btnSave').prop('disabled', true);
    $('#msg').text("Saving...");

    $.post("car_save_ajax.php", $(this).serialize(), function(res){

        $('#btnSave').prop('disabled', false);

        if (res.status === "ok"){
            $('#msg').html('<span style="color:green;">Saved</span>');
            setTimeout(()=> location.reload(), 600);
        } else {
            $('#msg').html('<span style="color:red;">ERROR</span>');
            console.log(res);
            alert(res.message || "Gagal menyimpan");
        }

    }, 'json').fail(function(xhr){
        $('#btnSave').prop('disabled', false);
        $('#msg').html('<span style="color:red;">AJAX Error</span>');
        console.log(xhr.responseText);
    });

});


// ============= DELETE BARIS LAMA ===================
$(document).on('click', '.delRow', function(){
    let carno = $(this).data('carno');
    let tr    = $(this).closest("tr");

    if (!confirm("Hapus CARNO: "+carno+" ?")) return;
    if (!confirm("Tekan OK sekali lagi untuk hapus permanen.")) return;

    $.post("car_delete.php", {carno:carno}, function(res){

        if (res.status === "ok"){
            tr.remove();
        } else {
            alert(res.message || "Gagal hapus");
        }

    }, 'json');
});


// ============= DELETE BARIS BARU ===================
$(document).on('click', '.delRowNew', function(){
    if (!confirm("Hapus baris baru ini?")) return;
    $(this).closest('tr').remove();
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
                    <td>
                        <button type="button" class="btn btn-sm btn-success pickMachine"
                                data-id="${r.MAC_ID}">
                            Pilih
                        </button>
                    </td>
                </tr>
            `);
        });

    }, 'json');
}

$('#lookupSearch').keyup(function(){
    loadLookup($(this).val());
});

$(document).on("click", ".pickMachine", function(){
    let id = $(this).data("id");
window.location.href = "Car_Maintenance.php?mac_id="+id;
});
</script>

</body>
</html>

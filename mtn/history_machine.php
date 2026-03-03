<?php
require_once "../middleware/Auth.php";
require_once "../middleware/RoleCheck.php";
only(['p2']);   // hanya divisi maintenance (ubah sesuai kebutuhan)
require "../config/database.php";


/*
-------------------------------------------------------
 MODE HALAMAN
-------------------------------------------------------
Jika user klik lookup:
    history_machine.php?idmac=123
maka ambil data mesin berdasarkan ID_MAC.

Jika user pakai navigasi:
    history_machine.php?page=3
maka ambil berdasarkan urutan MAC + PLANT.
-------------------------------------------------------
*/

// parameter dari URL
$idmac = isset($_GET['idmac']) ? intval($_GET['idmac']) : 0;
$page  = isset($_GET['page'])  ? intval($_GET['page'])  : 1;
$per   = 1;

// ---------- HITUNG TOTAL MESIN SEKALI SAJA ----------
$sqlTotal = "SELECT COUNT(*) AS JML FROM MAC_MTN WHERE MAC_ACTIVE = 1";
$rTotal   = sqlsrv_query($conn, $sqlTotal);

if ($rTotal === false) {
    die("SQL Error TOTAL: " . print_r(sqlsrv_errors(), true));
}

$t = sqlsrv_fetch_array($rTotal, SQLSRV_FETCH_ASSOC);
$total = (int)$t['JML'];

if ($total < 1) {
    die("Tidak ada data mesin!");
}

$maxPage = ceil($total / $per);
if ($page < 1) $page = 1;
if ($page > $maxPage) $page = $maxPage;

/* -----------------------------------------------------
   MODE 1: Lookup by ID_MAC
----------------------------------------------------- */
if ($idmac > 0) {

    $sql = "SELECT MAC_ID, MAC, PLANT, TONAGE 
            FROM MAC_MTN 
            WHERE MAC_ID = ? AND MAC_ACTIVE = 1";
    $m = q($sql, [$idmac]);
    $master = sqlsrv_fetch_array($m, SQLSRV_FETCH_ASSOC);

    if (!$master) {
        die("Machine ID not found!");
    }

    // hitung posisi mesin ini dalam paging
    $sqlPos = "WITH X AS (
                    SELECT MAC_ID,
                           ROW_NUMBER() OVER(ORDER BY MAC ASC, PLANT ASC) AS RN
                    FROM MAC_MTN WHERE MAC_ACTIVE = 1
               )
               SELECT RN FROM X WHERE MAC_ID = ?";
    $p = q($sqlPos, [$idmac]);
    $rowP = sqlsrv_fetch_array($p, SQLSRV_FETCH_ASSOC);

    if ($rowP && isset($rowP['RN'])) {
        $page = (int)$rowP['RN'];   // supaya info "Record X / Y" tetap benar
    }

} else {

/* -----------------------------------------------------
   MODE 2: Paging normal
----------------------------------------------------- */

    $sqlMaster = "
        WITH X AS (
            SELECT 
                MAC_ID, MAC, PLANT, TONAGE,
                ROW_NUMBER() OVER (ORDER BY MAC ASC, PLANT ASC) AS RN
            FROM MAC_MTN
            WHERE MAC_ACTIVE = 1
        )
        SELECT MAC_ID, MAC, PLANT, TONAGE
        FROM X
        WHERE RN = $page
    ";

    $m = sqlsrv_query($conn, $sqlMaster);

if ($m === false) {
    die("SQL Error MASTER: " . print_r(sqlsrv_errors(), true));
}

$master = sqlsrv_fetch_array($m, SQLSRV_FETCH_ASSOC);

if (!$master) {
    die("Data master tidak ditemukan!");
}

$idmac = $master['MAC_ID'];

}

// ---------- LOOKUP KERUSAKAN ----------
$sqlKer = "SELECT ID, KERUSAKAN FROM MTN_KERUSAKAN ORDER BY KERUSAKAN";
$ker = sqlsrv_query($conn, $sqlKer);

if ($ker === false) {
    die("SQL Error KERUSAKAN: " . print_r(sqlsrv_errors(), true));
}

$KER = [];
while ($x = sqlsrv_fetch_array($ker, SQLSRV_FETCH_ASSOC)) {
    $KER[] = $x;
}

// ---------- LOOKUP KATEGORI ----------
$sqlKat = "SELECT ID, KELOMPOK FROM MTN_KATEGORI ORDER BY KELOMPOK";
$kat = sqlsrv_query($conn, $sqlKat);

if ($kat === false) {
    die("SQL Error KATEGORI: " . print_r(sqlsrv_errors(), true));
}

$KAT = [];
while ($x = sqlsrv_fetch_array($kat, SQLSRV_FETCH_ASSOC)) {
    $KAT[] = $x;
}

// ---------- DETAIL ----------
$sqlDetail = "
    SELECT ID, DATE, DESCRIPTION, SERVICE, ID_KERUSAKAN, TIME, KATEGORI
    FROM MTN_HISTORY_MAC
    WHERE ID_MAC = ?
    ORDER BY DATE DESC
";
$det = sqlsrv_query($conn, $sqlDetail, [$idmac]);

if ($det === false) {
    die("SQL Error DETAIL: " . print_r(sqlsrv_errors(), true));
}

// fungsi tampilkan dd-MMM-yyyy
function tglDMY($dt) {
    return $dt ? date("d-M-Y", strtotime($dt->format("Y-m-d"))) : "";
}
?>
<!DOCTYPE html>
<html>
<head>
<title>History Machine</title>

<link rel="stylesheet" href="../assets/bootstrap.min.css">

<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="../assets/bootstrap.min.js"></script>

<!-- jQuery UI -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

<style>
body{ background:#eee; padding:15px; font-family:Arial; }
h2{text-align:center; margin-bottom:20px;}

.form-control-ro{
    background:white;
    border:1px solid #999;
    padding:4px;
    width:200px;
    font-weight:bold;
}

.grid-wrapper{
    height:520px;
    background:white;
    border:1px solid #666;
    overflow:auto;
}

table.grid{ width:100%; border-collapse:collapse; }
table.grid th, table.grid td{
    border:1px solid #888;
    padding:4px;
    font-size:12px;
}
table.grid th{ background:#ddd; }

textarea{width:100%; height:60px; border:none; resize:none;}
input[type=text], select{ width:100%; height:26px; border:none; }

.dtpicker{
    width:100%; 
    border:none; 
    height:26px; 
    background:white;
    cursor:pointer;
}
</style>

</head>
<body>
<!-- BACK BUTTON -->
<a href="dashboard_mtn.php" class="btn btn-secondary" style="margin-bottom:15px;">
    ← Kembali ke Menu 
</a>
<!-- ========== MODAL LOOKUP MACHINE ========== -->
<div class="modal fade" id="lookupMachine" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Search Machine</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="text" id="lookupSearch" class="form-control mb-2"
                       placeholder="Cari MAC / Plant / Tonage...">

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

<h2>HISTORY OF MACHINE</h2>

<!-- ========================= -->
<!--   NAVIGASI MASTER        -->
<!-- ========================= -->

<div class="nav-box mb-2">
    <a class="btn btn-sm btn-secondary" href="?page=1">|&lt;</a>

    <?php if ($page > 1): ?>
        <a class="btn btn-sm btn-secondary" href="?page=<?= $page-1 ?>">&lt;</a>
    <?php else: ?>
        <button class="btn btn-sm btn-secondary" disabled>&lt;</button>
    <?php endif; ?>

    <?php if ($page < $maxPage): ?>
        <a class="btn btn-sm btn-secondary" href="?page=<?= $page+1 ?>">&gt;</a>
    <?php else: ?>
        <button class="btn btn-sm btn-secondary" disabled>&gt;</button>
    <?php endif; ?>

    <a class="btn btn-sm btn-secondary" href="?page=<?= $maxPage ?>">&gt;|</a>

    <span style="margin-left:12px;">
        Record <?= $page ?> / <?= $maxPage ?>
    </span>
</div>

<form id="formHistory">

<input type="hidden" name="idmac" value="<?= $idmac ?>">
<input type="hidden" name="page"  value="<?= $page ?>">

<table>
<tr>
    <td width="90"><b>MACHINE</b></td>
    <td>
        <div style="display:flex; gap:5px;">
            <input class="form-control-ro" readonly id="txtMachine"
                   value="<?= $master['MAC'] ?>">
            <button type="button" class="btn btn-info btn-sm" id="btnLookup">🔍</button>
        </div>
    </td>
</tr>

<tr>
    <td><b>PLANT</b></td>
    <td><input class="form-control-ro" readonly value="<?= $master['PLANT'] ?>"></td>
</tr>

<tr>
    <td><b>TONAGE</b></td>
    <td><input class="form-control-ro" readonly value="<?= $master['TONAGE'] ?>"></td>
</tr>
</table>

<br>

<button type="button" id="btnAdd" class="btn btn-success btn-sm">+ Add</button>
<button type="submit" id="btnSave" class="btn btn-primary btn-sm">Save</button>
<span id="msg"></span>

<!-- ============================================== -->
<!--                 DETAIL GRID                    -->
<!-- ============================================== -->
<div class="grid-wrapper mt-2">
<table class="grid">
<thead>
<tr>
    <th width="120">DATE</th>
    <th>DESCRIPTION</th>
    <th>SERVICE</th>
    <th width="140">KERUSAKAN</th>
    <th width="140">KATEGORI</th>
    <th width="70">TIME</th>
    <th width="60">DEL</th>
</tr>
</thead>

<tbody id="detailBody">
<?php $i=0; while($r = sqlsrv_fetch_array($det, SQLSRV_FETCH_ASSOC)): ?>
<tr>

<td>
    <input type="hidden" name="rows[<?= $i ?>][id]" value="<?= $r['ID'] ?>">
    <input type="text" class="dtpicker"
           name="rows[<?= $i ?>][date]"
           value="<?= tglDMY($r['DATE']) ?>">
</td>

<td>
    <textarea name="rows[<?= $i ?>][description]"><?= $r['DESCRIPTION'] ?></textarea>
</td>

<td>
    <textarea name="rows[<?= $i ?>][service]"><?= $r['SERVICE'] ?></textarea>
</td>

<td>
    <select name="rows[<?= $i ?>][kerusakan]">
        <option value="">-- pilih --</option>
        <?php foreach($KER as $k): ?>
        <option value="<?= $k['ID'] ?>" 
            <?= $k['ID']==$r['ID_KERUSAKAN'] ? 'selected':'' ?>>
            <?= $k['KERUSAKAN'] ?>
        </option>
        <?php endforeach; ?>
    </select>
</td>

<td>
    <select name="rows[<?= $i ?>][kategori]">
        <option value="">-- pilih --</option>
        <?php foreach($KAT as $k): ?>
        <option value="<?= $k['ID'] ?>" 
            <?= $k['ID']==$r['KATEGORI'] ? 'selected':'' ?>>
            <?= $k['KELOMPOK'] ?>
        </option>
        <?php endforeach; ?>
    </select>
</td>

<td>
    <input type="text" name="rows[<?= $i ?>][time]"
           value="<?= $r['TIME'] ?>">
</td>

<td>
    <button type="button" class="btn btn-danger btn-sm delRow"
            data-id="<?= $r['ID'] ?>">X</button>
</td>

</tr>
<?php $i++; endwhile; ?>
</tbody>

</table>
</div>
</form>

<script>
let rowIndex = <?= $i ?>;

// =======================================
//   INIT DATEPICKER
// =======================================
function initDatePicker(){
    $(".dtpicker").datepicker({
        dateFormat: "dd-M-yy",
        changeMonth: true,
        changeYear: true
    });
}
initDatePicker();


// =======================================
//   ADD NEW ROW
// =======================================
$('#btnAdd').click(function(){
    let i = rowIndex++;

    let row = `
<tr>

<td>
    <input type="hidden" name="rows[${i}][id]" value="">
    <input type="text" class="dtpicker" name="rows[${i}][date]">
</td>

<td><textarea name="rows[${i}][description]"></textarea></td>

<td><textarea name="rows[${i}][service]"></textarea></td>

<td>
    <select name="rows[${i}][kerusakan]">
        <option value="">-- pilih --</option>
        <?php foreach($KER as $k): ?>
        <option value="<?= $k['ID'] ?>"><?= $k['KERUSAKAN'] ?></option>
        <?php endforeach; ?>
    </select>
</td>

<td>
    <select name="rows[${i}][kategori]">
        <option value="">-- pilih --</option>
        <?php foreach($KAT as $k): ?>
        <option value="<?= $k['ID'] ?>"><?= $k['KELOMPOK'] ?></option>
        <?php endforeach; ?>
    </select>
</td>

<td><input type="text" name="rows[${i}][time]"></td>

<td>
    <button type="button" class="btn btn-danger btn-sm delRowNew">X</button>
</td>

</tr>
    `;

    $('#detailBody').prepend(row);
    initDatePicker();
});
</script>


<!-- ========================================================= -->
<!--                SAVE DATA (AJAX)                           -->
<!-- ========================================================= -->
<script>
$('#formHistory').submit(function(e){
    e.preventDefault();

    $('#btnSave').prop('disabled', true);
    $('#msg').text("Saving...");

    $.post('history_save_ajax.php', $(this).serialize(), function(res){

        $('#btnSave').prop('disabled', false);

        if(res.status === "ok"){
            $('#msg').html("<span style='color:green;'>Saved</span>");

            // reload agar ID baru muncul
            setTimeout(() => {
                window.location.reload();
            }, 600);

        } else {
            $('#msg').html("<span style='color:red;'>ERROR</span>");
            alert(res.message || "Gagal menyimpan.");
        }

    }, 'json');

});
</script>


<!-- ========================================================= -->
<!--        DELETE BARIS LAMA (DB)                             -->
<!-- ========================================================= -->
<script>
$(document).on("click", ".delRow", function(){

    let id = $(this).data("id");
    let tr = $(this).closest("tr");

    if(!confirm("Hapus data ini?\nData akan terhapus permanen.")) return;
    if(!confirm("Tekan OK sekali lagi untuk konfirmasi hapus.")) return;

    $.post("history_delete.php", { id:id }, function(res){

        if(res.status === "ok"){
            tr.remove();
        } else {
            alert("Gagal menghapus: " + (res.message || ""));
        }

    }, "json");
});
</script>


<!-- ========================================================= -->
<!--        DELETE BARIS BARU (belum DB)                       -->
<!-- ========================================================= -->
<script>
$(document).on("click", ".delRowNew", function(){

    if(!confirm("Hapus baris baru ini?")) return;
    if(!confirm("Tekan OK untuk konfirmasi.")) return;

    $(this).closest("tr").remove();
});
</script>


<!-- ========================================================= -->
<!--                LOOKUP MACHINE POPUP                       -->
<!-- ========================================================= -->
<script>

// buka popup
$('#btnLookup').click(function () {
    $('#lookupMachine').modal('show');
    $('#lookupSearch').val("");
    loadLookup("");
    setTimeout(()=>$('#lookupSearch').focus(), 300);
});

// ambil data lookup
function loadLookup(keyword){
    $.get("lookup_machine_ajax.php", { q:keyword }, function(res){

        $('#lookupBody').html("");

        res.forEach(row => {

            $('#lookupBody').append(`
                <tr>
                    <td>${row.MAC}</td>
                    <td>${row.PLANT}</td>
                    <td>${row.TONAGE}</td>
                    <td>
                        <button class="btn btn-success btn-sm pickMachine"
                                data-id="${row.MAC_ID}">
                            Pilih
                        </button>
                    </td>
                </tr>
            `);

        });

    }, "json");
}

// search on typing
$('#lookupSearch').keyup(function(){
    loadLookup($(this).val());
});

// pilih machine → reload halaman dgn idmac
$(document).on("click", ".pickMachine", function(){
    let idmac = $(this).data("id");
    window.location.href = "history_machine.php?idmac=" + idmac;
});

</script>

</body>
</html>

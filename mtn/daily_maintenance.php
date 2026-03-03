<?php
// =======================================================
// SECURITY
// =======================================================
require_once "../middleware/Auth.php";
require_once "../middleware/RoleCheck.php";
// hanya MTN & Admin yang boleh akses (silakan sesuaikan)
only(['p2', 'admin']);

require "../config/database.php";

/*
 * DAILY MAINTENANCE
 * Master  : MAC_MTN (MAC_ID, MAC, PLANT, TONAGE, MAC_ACTIVE)
 * Detail  : MTN_DAILY (link by MAC_ID)
 */

// ------------ PARAMETER ------------
// jika dari lookup: daily_maintenance.php?mac_id=123
// jika dari paging : daily_maintenance.php?page=3
$page        = isset($_GET['page'])    ? intval($_GET['page'])    : 1;
$mac_id_get  = isset($_GET['mac_id'])  ? intval($_GET['mac_id'])  : 0;

// ------------ HITUNG TOTAL MASTER ------------
// (supaya tahu jumlah halaman / record)
$sqlTotal = "SELECT COUNT(*) AS JML FROM MAC_MTN WHERE MAC_ACTIVE = 1";
$rTotal   = sqlsrv_query($conn, $sqlTotal);

if ($rTotal === false) {
    die("SQL Error TOTAL:<br>" . print_r(sqlsrv_errors(), true));
}

$totRow = sqlsrv_fetch_array($rTotal, SQLSRV_FETCH_ASSOC);
$total  = (int)$totRow['JML'];

if ($total < 1) {
    die("Tidak ada machine aktif di MAC_MTN");
}

$perPage = 1;
$maxPage = ceil($total / $perPage);

// ------------ CTE MASTER UNTUK ROW_NUMBER ------------
// dipakai baik untuk lookup by MAC_ID maupun paging by RN
$cteMaster = "
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

// ------------ AMBIL MASTER ------------

if ($mac_id_get > 0) {
    // MODE LOOKUP: dipanggil dari popup -> pakai MAC_ID
    $sqlMaster = $cteMaster . " SELECT * FROM X WHERE MAC_ID = ?";
    $m = sqlsrv_query($conn, $sqlMaster, [$mac_id_get]);

    if ($m === false) {
        die("SQL Error MASTER (by MAC_ID):<br>" . print_r(sqlsrv_errors(), true));
    }

    $master = sqlsrv_fetch_array($m, SQLSRV_FETCH_ASSOC);
    if (!$master) {
        die("Machine dengan MAC_ID $mac_id_get tidak ditemukan.");
    }

    // sesuaikan page supaya label navigasi benar
    $page = (int)$master['RN'];

} else {
    // MODE PAGING BIASA: pakai nomor halaman (RN)
    if ($page < 1)        $page = 1;
    if ($page > $maxPage) $page = $maxPage;

    $sqlMaster = $cteMaster . " SELECT * FROM X WHERE RN = ?";
    $m = sqlsrv_query($conn, $sqlMaster, [$page]);

    if ($m === false) {
        die("SQL Error MASTER (by RN):<br>" . print_r(sqlsrv_errors(), true));
    }

    $master = sqlsrv_fetch_array($m, SQLSRV_FETCH_ASSOC);
    if (!$master) {
        die("Data master tidak ditemukan.");
    }
}

$mac_id = (int)$master['MAC_ID'];
$mac    = trim($master['MAC']);
$plant  = $master['PLANT'];
$tonage = $master['TONAGE'];

// ------------ LOOKUP STATUS ------------
// tabel STATUS (STATUS varchar)
$sqlStatus = "SELECT STATUS FROM STATUS ORDER BY STATUS";
$st = sqlsrv_query($conn, $sqlStatus);

if ($st === false) {
    die("SQL Error STATUS:<br>" . print_r(sqlsrv_errors(), true));
}

$STATUS_LIST = [];
while($s = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $STATUS_LIST[] = $s;
}

// ------------ DETAIL DAILY ------------
// semua record daily untuk MAC_ID tertentu
$sqlDet = "
    SELECT
        ID,
        DATE,
        PROBLEM,
        CAUSE,
        PIC,
        FROM_HOURS,
        TO_HOURS,
        FINISH,
        STATUS,
        DESCRIPTION
    FROM MTN_DAILY
    WHERE MAC_ID = ?
    ORDER BY DATE DESC, ID DESC
";
$det = sqlsrv_query($conn, $sqlDet, [$mac_id]);

if ($det === false) {
    die("SQL Error DETAIL DAILY:<br>" . print_r(sqlsrv_errors(), true));
}

// helper format tanggal
function tglDMY($dt) {
    if (!$dt) return "";
    if ($dt instanceof DateTime) return $dt->format("d-M-Y");
    return date("d-M-Y", strtotime($dt));
}

// helper format jam
function timeHM($t) {
    if (!$t) return "";
    if ($t instanceof DateTime) return $t->format("H:i");
    return substr($t, 0, 5);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Daily Maintenance</title>

    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <script src="../assets/bootstrap.min.js"></script>

    <!-- jQuery UI untuk datepicker -->
    <link rel="stylesheet"
          href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

    <style>
        body{ background:#eee; padding:15px; font-family:Arial; }
        h2{ text-align:center; margin-bottom:20px; }

        .form-control-ro{
            width:200px;
            border:1px solid #777;
            padding:5px;
            background:white;
            font-weight:bold;
        }

        .grid-wrapper{
            height:520px;
            overflow:auto;
            border:1px solid #666;
            background:white;
            margin-top:10px;
        }

        table.grid{ width:100%; border-collapse:collapse; }
        table.grid th, table.grid td{
            border:1px solid #999;
            font-size:12px;
            padding:4px;
            vertical-align:top;
        }
        table.grid th{
            background:#ddd;
            text-align:center;
        }
        textarea{
            width:100%;
            height:60px;
            border:none;
            resize:none;
        }
        input[type=text],
        input[type=time],
        select{
            width:100%;
            border:none;
            height:26px;
        }
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
<h2>DAILY MAINTENANCE</h2>

<!-- NAVIGASI MASTER -->
<div class="mb-3">
    <a class="btn btn-sm btn-secondary" href="?page=1">|&lt;</a>

    <?php if ($page > 1): ?>
        <a class="btn btn-sm btn-secondary" href="?page=<?= $page-1 ?>">&lt;</a>
    <?php else: ?>
        <button type="button" class="btn btn-sm btn-secondary" disabled>&lt;</button>
    <?php endif; ?>

    <?php if ($page < $maxPage): ?>
        <a class="btn btn-sm btn-secondary" href="?page=<?= $page+1 ?>">&gt;</a>
    <?php else: ?>
        <button type="button" class="btn btn-sm btn-secondary" disabled>&gt;</button>
    <?php endif; ?>

    <a class="btn btn-sm btn-secondary" href="?page=<?= $maxPage ?>">&gt;|</a>

    <span style="margin-left:10px;">
        Record <?= $page ?> / <?= $maxPage ?>
    </span>
</div>

<form id="formDaily">
    <!-- HIDDEN KEY -->
    <input type="hidden" name="mac_id" value="<?= $mac_id ?>">
    <!-- penting untuk INSERT -->
    <input type="hidden" name="mac"   value="<?= htmlspecialchars($mac) ?>">
    <input type="hidden" name="plant" value="<?= htmlspecialchars($plant) ?>">

    <table>
        <tr>
            <td width="80"><b>MACHINE</b></td>
            <td>
                <div style="display:flex; gap:5px;">
                    <input id="txtMachine" class="form-control-ro" readonly
                           value="<?= htmlspecialchars($mac) ?>">
                    <button type="button" id="btnLookup"
                            class="btn btn-info btn-sm">🔍</button>
                </div>
            </td>
        </tr>
        <tr>
            <td><b>PLANT</b></td>
            <td><input class="form-control-ro" readonly
                       value="<?= htmlspecialchars($plant) ?>"></td>
        </tr>
        <tr>
            <td><b>TONAGE</b></td>
            <td><input class="form-control-ro" readonly
                       value="<?= htmlspecialchars($tonage) ?>"></td>
        </tr>
    </table>

    <br>

    <button type="button" id="btnAdd"  class="btn btn-success btn-sm">+ Add</button>
    <button type="submit" id="btnSave" class="btn btn-primary btn-sm">Save</button>
    <span id="msg"></span>

    <div class="grid-wrapper">
        <table class="grid">
            <thead>
            <tr>
                <th width="110">DATE</th>
                <th width="200">PROBLEM</th>
                <th width="230">CAUSE</th>
                <th width="120">PIC</th>
                <th width="70">FROM</th>
                <th width="70">TO</th>
                <th width="70">FINISH</th>
                <th width="90">STATUS</th>
                <th>REMARK</th>
                <th width="40">DEL</th>
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
                    <td><textarea name="rows[<?= $i ?>][problem]"><?= $r['PROBLEM'] ?></textarea></td>
                    <td><textarea name="rows[<?= $i ?>][cause]"><?= $r['CAUSE'] ?></textarea></td>
                    <td><input type="text" name="rows[<?= $i ?>][pic]" value="<?= $r['PIC'] ?>"></td>
                    <td><input type="time" name="rows[<?= $i ?>][fromh]"  value="<?= timeHM($r['FROM_HOURS']) ?>"></td>
                    <td><input type="time" name="rows[<?= $i ?>][toh]"    value="<?= timeHM($r['TO_HOURS']) ?>"></td>
                    <td><input type="time" name="rows[<?= $i ?>][finish]" value="<?= timeHM($r['FINISH']) ?>"></td>
                    <td>
                        <select name="rows[<?= $i ?>][status]">
                            <option value="">-- pilih --</option>
                            <?php foreach($STATUS_LIST as $s): ?>
                                <option value="<?= $s['STATUS'] ?>"
                                    <?= $s['STATUS']==$r['STATUS'] ? 'selected' : '' ?>>
                                    <?= $s['STATUS'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><textarea name="rows[<?= $i ?>][remark]"><?= $r['DESCRIPTION'] ?></textarea></td>
                    <td style="text-align:center;">
                        <button type="button"
                                class="btn btn-danger btn-sm delRow"
                                data-id="<?= $r['ID'] ?>">X</button>
                    </td>
                </tr>
            <?php $i++; endwhile; ?>
            </tbody>
        </table>
    </div>
</form>

<!-- ===================== MODAL LOOKUP MACHINE ===================== -->
<div class="modal fade" id="lookupMachine" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Search Machine</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="text" id="lookupSearch" class="form-control mb-2"
                       placeholder="Cari MAC / PLANT / TONAGE...">
                <table class="table table-bordered table-hover mb-0">
                    <thead>
                    <tr>
                        <th width="80">MAC</th>
                        <th width="80">PLANT</th>
                        <th width="80">TONAGE</th>
                        <th width="60">Pilih</th>
                    </tr>
                    </thead>
                    <tbody id="lookupBody"></tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<script>
// --------- INIT DATEPICKER ---------
function initDP(){
    $(".dtpicker").datepicker({
        dateFormat: "dd-M-yy",   // cocok dengan tglDMY dan toSQLdate
        changeMonth: true,
        changeYear: true
    });
}
initDP();

let rowIndex = <?= $i ?>;

// --------- TAMBAH BARIS BARU ---------
$('#btnAdd').click(function(){
    let i = rowIndex++;

    let row = `
<tr>
    <td>
        <input type="hidden" name="rows[${i}][id]" value="">
        <input type="text" class="dtpicker" name="rows[${i}][date]">
    </td>
    <td><textarea name="rows[${i}][problem]"></textarea></td>
    <td><textarea name="rows[${i}][cause]"></textarea></td>
    <td><input type="text" name="rows[${i}][pic]"></td>
    <td><input type="time" name="rows[${i}][fromh]"></td>
    <td><input type="time" name="rows[${i}][toh]"></td>
    <td><input type="time" name="rows[${i}][finish]"></td>
    <td>
        <select name="rows[${i}][status]">
            <option value="">-- pilih --</option>
            <?php foreach($STATUS_LIST as $s): ?>
            <option value="<?= $s['STATUS'] ?>"><?= $s['STATUS'] ?></option>
            <?php endforeach; ?>
        </select>
    </td>
    <td><textarea name="rows[${i}][remark]"></textarea></td>
    <td style="text-align:center;">
        <button type="button" class="btn btn-danger btn-sm delRowNew">X</button>
    </td>
</tr>`;
    $('#detailBody').prepend(row);
    initDP();
});

// --------- SAVE AJAX ---------
$('#formDaily').submit(function(e){
    e.preventDefault();

    $('#btnSave').prop('disabled', true);
    $('#msg').text('Saving...');

    $.post('daily_save_ajax.php', $(this).serialize(), function(res){
        $('#btnSave').prop('disabled', false);

        if (res.status === 'ok') {
            $('#msg').html('<span style="color:green;">Saved</span>');
            // reload agar ID baru ter-refresh
            setTimeout(function(){ window.location.reload(); }, 600);
        } else {
            $('#msg').html('<span style="color:red;">ERROR</span>');
            alert(res.message || 'Gagal menyimpan');
            console.log(res);
        }
    }, 'json').fail(function(xhr){
        $('#btnSave').prop('disabled', false);
        $('#msg').html('<span style="color:red;">AJAX error</span>');
        console.error(xhr.responseText);
    });
});

// --------- HAPUS BARIS LAMA (DB) ---------
$(document).on('click', '.delRow', function(){
    let id = $(this).data('id');
    let tr = $(this).closest('tr');

    if (!confirm('Hapus data ini? Data akan terhapus permanen.')) return;
    if (!confirm('Tekan OK sekali lagi untuk hapus.')) return;

    $.post('daily_delete.php', {id:id}, function(res){
        if (res.status === 'ok') {
            tr.remove();
        } else {
            alert(res.message || 'Gagal hapus');
        }
    }, 'json').fail(function(xhr){
        alert('AJAX error saat delete');
        console.error(xhr.responseText);
    });
});

// --------- HAPUS BARIS BARU (BELUM SAVE) ---------
$(document).on('click', '.delRowNew', function(){
    if (!confirm('Hapus baris baru ini?')) return;
    $(this).closest('tr').remove();
});

// =============== LOOKUP MACHINE POPUP ===============
$('#btnLookup').click(function () {
    $('#lookupMachine').modal('show');
    $('#lookupSearch').val("");
    loadLookup("");
    setTimeout(()=>$('#lookupSearch').focus(), 300);
});

function loadLookup(keyword){
    $.get("lookup_machine_ajax.php", { q:keyword }, function(res){

        $('#lookupBody').html("");

        res.forEach(row => {
            $('#lookupBody').append(`
                <tr>
                    <td>${row.MAC}</td>
                    <td>${row.PLANT}</td>
                    <td>${row.TONAGE ?? ''}</td>
                    <td>
                        <button type="button" class="btn btn-success btn-sm pickMachine"
                                data-id="${row.MAC_ID}">
                            Pilih
                        </button>
                    </td>
                </tr>
            `);
        });

    }, "json");
}

// ketik search
$('#lookupSearch').keyup(function(){
    loadLookup($(this).val());
});

// klik pilih -> pindah ke daily_maintenance.php?mac_id=...
$(document).on("click", ".pickMachine", function(){
    let idmac = $(this).data("id");
    window.location.href = "daily_maintenance.php?mac_id=" + idmac;
});
</script>

</body>
</html>

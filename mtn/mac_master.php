<?php
// ==============================
// SECURITY
// ==============================
require_once "../middleware/Auth.php";
require_once "../middleware/RoleCheck.php";
only(['p2']);   // hanya divisi maintenance (ubah sesuai kebutuhan)
require "../config/database.php";

// ==============================
// PARAM & SETTING
// ==============================
$idmac = isset($_GET['idmac']) ? intval($_GET['idmac']) : 0;
$page  = isset($_GET['page'])  ? intval($_GET['page'])  : 1;
$per   = 1;

// ==============================
// TOTAL RECORD
// ==============================
$sqlTotal = "SELECT COUNT(*) AS JML FROM MAC_MTN";
$rTotal   = sqlsrv_query($conn, $sqlTotal);
$t        = sqlsrv_fetch_array($rTotal, SQLSRV_FETCH_ASSOC);
$total    = (int)$t['JML'];

$maxPage = $total > 0 ? ceil($total / $per) : 1;

if ($page < 1)        $page = 1;
if ($page > $maxPage) $page = $maxPage;

// ==============================
// MODE 1 — buka berdasarkan ?idmac
// ==============================
if ($idmac > 0 && $total > 0) {

    $sql = "SELECT MAC_ID, MAC, MAC_SERIAL, MAC_TYPE, MAC_ACTIVE, PLANT, TONAGE
            FROM MAC_MTN
            WHERE MAC_ID = ?";
    $m = sqlsrv_query($conn, $sql, [$idmac]);
    $master = sqlsrv_fetch_array($m, SQLSRV_FETCH_ASSOC);

    if (!$master) {
        die("Machine ID tidak ditemukan di MAC_MTN");
    }

    // cari posisi RN
    $sqlPos = "
        WITH X AS (
            SELECT MAC_ID,
                   ROW_NUMBER() OVER(ORDER BY PLANT ASC, MAC ASC) AS RN
            FROM MAC_MTN
        )
        SELECT RN FROM X WHERE MAC_ID = ?
    ";
    $p = sqlsrv_query($conn, $sqlPos, [$idmac]);
    $rowP = sqlsrv_fetch_array($p, SQLSRV_FETCH_ASSOC);

    if ($rowP && isset($rowP['RN'])) {
        $page = (int)$rowP['RN'];
    }

}
// ==============================
// MODE 2 — navigasi halaman
// ==============================
else if ($total > 0) {

    $sqlMaster = "
        WITH X AS (
            SELECT
                MAC_ID, MAC, MAC_SERIAL, MAC_TYPE, MAC_ACTIVE, PLANT, TONAGE,
                ROW_NUMBER() OVER(ORDER BY PLANT ASC, MAC ASC) AS RN
            FROM MAC_MTN
        )
        SELECT MAC_ID, MAC, MAC_SERIAL, MAC_TYPE, MAC_ACTIVE, PLANT, TONAGE
        FROM X
        WHERE RN = ?
    ";
    $m = sqlsrv_query($conn, $sqlMaster, [$page]);
    $master = sqlsrv_fetch_array($m, SQLSRV_FETCH_ASSOC);

    if (!$master) {
        $idmac  = 0;
        $master = [
            'MAC_ID'     => 0,
            'MAC'        => '',
            'MAC_SERIAL' => '',
            'MAC_TYPE'   => '',
            'MAC_ACTIVE' => 1,
            'PLANT'      => '',
            'TONAGE'     => ''
        ];
    } else {
        $idmac = (int)$master['MAC_ID'];
    }

}
// ==============================
// MODE 3 — tidak ada data
// ==============================
else {

    $idmac  = 0;
    $master = [
        'MAC_ID'     => 0,
        'MAC'        => '',
        'MAC_SERIAL' => '',
        'MAC_TYPE'   => '',
        'MAC_ACTIVE' => 1,
        'PLANT'      => '',
        'TONAGE'     => ''
    ];

    $page    = 1;
    $maxPage = 1;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Master Mesin - MAC_MTN</title>

    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <script src="../assets/bootstrap.min.js"></script>

    <style>
        body{ background:#eee; padding:15px; font-family:Arial; }
        h2{ text-align:center; margin-bottom:20px; }
        .form-control-sm{ height:26px; padding:3px 6px; }
        .label-col{ width:100px; font-weight:bold; }
        .nav-box a{ margin-right:5px; }
        .info-msg{ margin-left:10px; }
    </style>
</head>
<body>

<h2>MASTER MACHINE (MAC_MTN)</h2>

<!-- NAVIGASI -->
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

    <span class="info-msg">
        Record <?= $total > 0 ? $page : 0 ?> / <?= $maxPage ?> (Total: <?= $total ?>)
    </span>
</div>

<form id="formMac">

    <input type="hidden" name="mac_id" value="<?= (int)$idmac ?>">
    <input type="hidden" name="page"   value="<?= (int)$page ?>">

    <table>
        <tr>
            <td class="label-col">MAC</td>
            <td>
                <input type="text" name="mac" class="form-control form-control-sm"
                       style="width:200px;"
                       value="<?= htmlspecialchars($master['MAC']) ?>">
            </td>
        </tr>

        <tr>
            <td class="label-col">MAC SERIAL</td>
            <td>
                <input type="text" name="mac_serial" class="form-control form-control-sm"
                       style="width:250px;"
                       value="<?= htmlspecialchars($master['MAC_SERIAL']) ?>">
            </td>
        </tr>

        <tr>
            <td class="label-col">MAC TYPE</td>
            <td>
                <input type="text" name="mac_type" class="form-control form-control-sm"
                       style="width:250px;"
                       value="<?= htmlspecialchars($master['MAC_TYPE']) ?>">
            </td>
        </tr>

        <tr>
            <td class="label-col">PLANT</td>
            <td>
                <input type="text" name="plant" class="form-control form-control-sm"
                       style="width:100px;"
                       value="<?= htmlspecialchars($master['PLANT']) ?>">
            </td>
        </tr>

        <tr>
            <td class="label-col">TONAGE</td>
            <td>
                <input type="text" name="tonage" class="form-control form-control-sm"
                       style="width:100px;"
                       value="<?= htmlspecialchars($master['TONAGE']) ?>">
            </td>
        </tr>

        <tr>
            <td class="label-col">ACTIVE</td>
            <td>
                <label>
                    <input type="checkbox" name="mac_active" value="1"
                           <?= ($master['MAC_ACTIVE'] ? 'checked' : '') ?>>
                    &nbsp;Active
                </label>
            </td>
        </tr>
    </table>

    <br>

    <button type="button" id="btnNew"  class="btn btn-success btn-sm">New</button>
    <button type="submit" id="btnSave" class="btn btn-primary btn-sm">Save</button>
    <button type="button" id="btnDel"  class="btn btn-danger btn-sm">Delete</button>
    <span id="msg"></span>

</form>

<script>
// NEW
$('#btnNew').click(function(){
    $('input[name=mac_id]').val('0');
    $('input[name=mac]').val('');
    $('input[name=mac_serial]').val('');
    $('input[name=mac_type]').val('');
    $('input[name=plant]').val('');
    $('input[name=tonage]').val('');
    $('input[name=mac_active]').prop('checked', true);
    $('#msg').text('Mode input baru (NEW).');
});

// SAVE
$('#formMac').submit(function(e){
    e.preventDefault();

    $('#btnSave').prop('disabled', true);
    $('#msg').text('Saving...');

    $.post('mac_save_ajax.php', $(this).serialize(), function(res){
        $('#btnSave').prop('disabled', false);

        if (res.status === 'ok') {

            $('#msg').html('<span style="color:green;">Saved</span>');

            if (res.new_id) {
                setTimeout(function(){
                    window.location.href = 'mac_master.php?idmac=' + res.new_id;
                }, 600);
            } else {
                setTimeout(function(){
                    window.location.reload();
                }, 600);
            }

        } else {
            $('#msg').html('<span style="color:red;">ERROR</span>');
            alert(res.message || 'Gagal menyimpan.');
        }

    }, 'json').fail(function(){
        $('#btnSave').prop('disabled', false);
        alert('AJAX error / jaringan bermasalah.');
    });
});

// DELETE
$('#btnDel').click(function(){
    let id = $('input[name=mac_id]').val();
    if (!id || id == '0') {
        alert('Data baru belum tersimpan. Tidak bisa delete.');
        return;
    }

    if (!confirm('Hapus mesin ini (MAC_ID='+id+') ?')) return;
    if (!confirm('Tekan OK sekali lagi untuk konfirmasi hapus permanen.')) return;

    $.post('mac_delete.php', { mac_id:id }, function(res){
        if (res.status === 'ok') {
            alert('Data berhasil dihapus.');
            window.location.href = 'mac_master.php';
        } else {
            alert(res.message || 'Gagal menghapus.');
        }
    }, 'json').fail(function(){
        alert('AJAX error / jaringan bermasalah.');
    });
});
</script>

<a href="dashboard_mtn.php" class="btn btn-secondary mt-3">
    ← Kembali ke Menu 
</a>

</body>
</html>

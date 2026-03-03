<?php
// ============================================================
// SECURITY: hanya MTN (atau admin jika diizinkan)
// ============================================================
require_once "../middleware/Auth.php";
require_once "../middleware/RoleCheck.php";
only(['mtn', 'admin']);  // ubah sesuai kebutuhan

require "../config/database.php";

$idmac = isset($_GET['idmac']) ? intval($_GET['idmac']) : 0;

if ($idmac <= 0) {
    die("ID MAC tidak valid.");
}

// ============================================================
// LOOKUP KERUSAKAN
// ============================================================

$sqlKer = "SELECT ID, KERUSAKAN FROM MTN_KERUSAKAN ORDER BY KERUSAKAN ASC";
$ker = sqlsrv_query($conn, $sqlKer);

if ($ker === false) {
    die("SQL Error Lookup Kerusakan:<br>" . print_r(sqlsrv_errors(), true));
}

$KERUSAKAN_LIST = [];
while ($x = sqlsrv_fetch_array($ker, SQLSRV_FETCH_ASSOC)) {
    $KERUSAKAN_LIST[] = $x;
}

// ============================================================
// LOOKUP KATEGORI
// ============================================================

$sqlKat = "SELECT ID, KELOMPOK FROM MTN_KATEGORI ORDER BY KELOMPOK ASC";
$kat = sqlsrv_query($conn, $sqlKat);

if ($kat === false) {
    die("SQL Error Lookup Kategori:<br>" . print_r(sqlsrv_errors(), true));
}

$KATEGORI_LIST = [];
while ($x = sqlsrv_fetch_array($kat, SQLSRV_FETCH_ASSOC)) {
    $KATEGORI_LIST[] = $x;
}

// ============================================================
// HEADER MESIN
// ============================================================

$sqlHead = "SELECT MAC, PLANT, TONAGE FROM MAC_MTN WHERE MAC_ID = ?";
$h = sqlsrv_query($conn, $sqlHead, [$idmac]);

if ($h === false) {
    die("SQL Error Header Mesin:<br>" . print_r(sqlsrv_errors(), true));
}

$head = sqlsrv_fetch_array($h, SQLSRV_FETCH_ASSOC);

if (!$head) {
    die("Data mesin tidak ditemukan!");
}

// ============================================================
// DETAIL HISTORY
// ============================================================

$sqlDet = "SELECT ID, DATE, DESCRIPTION, SERVICE, ID_KERUSAKAN, TIME, KATEGORI
           FROM MTN_HISTORY_MAC
           WHERE ID_MAC = ?
           ORDER BY DATE DESC";

$det = sqlsrv_query($conn, $sqlDet, [$idmac]);

if ($det === false) {
    die("SQL Error Detail History:<br>" . print_r(sqlsrv_errors(), true));
}
?>
<!DOCTYPE html>
<html>
<head>
<title>History Machine - Detail</title>

<link rel="stylesheet" href="../assets/bootstrap.min.css">
<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="../assets/bootstrap.min.js"></script>

<style>
body{ font-family:Arial; background:#e8e8e8; padding:15px; }
h2{ text-align:center; margin-bottom:20px; }
.label{ font-weight:bold; width:80px; }

/* header machine */
.form-control-ro{
    background:#f8f8f8;
    border:1px solid #ccc;
    padding:4px;
    color:#000;
}

/* wrapper tabel agar fit layar */
.grid-wrapper{
    background:white;
    border:1px solid #333;
    height:520px;
    overflow:auto;
}

/* tabel fit layar */
table.grid{
    width:100%;
    border-collapse:collapse;
}

/* kolom fleksibel tapi tidak terlalu sempit */
table.grid th, table.grid td{
    border:1px solid #999;
    padding:4px;
    vertical-align:top;
    font-size:12px;
    white-space:normal;
}

table.grid th{ background:#ddd; }

th:nth-child(1), td:nth-child(1){ min-width:110px; }
th:nth-child(2), td:nth-child(2){ min-width:280px; }
th:nth-child(3), td:nth-child(3){ min-width:280px; }
th:nth-child(4), td:nth-child(4){ min-width:90px; }
th:nth-child(5), td:nth-child(5){ min-width:90px; }
th:nth-child(6), td:nth-child(6){ min-width:60px; }
th:nth-child(7), td:nth-child(7){ min-width:40px; text-align:center; }

textarea{
    width:100%;
    height:60px;
    border:none;
    resize:none;
}

input[type=text], input[type=date], select{
    width:100%;
    border:none;
    height:26px;
}
</style>

</head>
<body>

<h2>HISTORY OF MACHINE</h2>

<a href="history_master.php" class="btn btn-secondary btn-sm mb-2">&laquo; Back</a>

<form method="post" action="history_save.php">
<input type="hidden" name="idmac" value="<?= $idmac ?>">

<table>
<tr>
    <td class="label">MACHINE</td>
    <td><input type="text" class="form-control-ro" readonly value="<?= $head['MAC']; ?>"></td>
</tr>
<tr>
    <td class="label">PLANT</td>
    <td><input type="text" class="form-control-ro" readonly value="<?= $head['PLANT']; ?>"></td>
</tr>
<tr>
    <td class="label">TONAGE</td>
    <td><input type="text" class="form-control-ro" readonly value="<?= $head['TONAGE']; ?>"></td>
</tr>
</table>

<br>

<button type="button" class="btn btn-success btn-sm" id="btnAdd">+</button>
<button type="submit" name="save" value="1" class="btn btn-primary btn-sm">Save</button>

<div class="grid-wrapper mt-2">
<table class="grid">
<thead>
<tr>
    <th>DATE</th>
    <th>DESCRIPTION</th>
    <th>SERVICE</th>
    <th>KERUSAKAN</th>
    <th>KATEGORI</th>
    <th>TIME</th>
    <th>DEL</th>
</tr>
</thead>

<tbody id="detailBody">
<?php 
$i=0; 
while($r = sqlsrv_fetch_array($det, SQLSRV_FETCH_ASSOC)): 
?>
<tr>
    <td>
        <input type="hidden" name="rows[<?= $i ?>][id]" value="<?= $r['ID']; ?>">
        <input type="date" name="rows[<?= $i ?>][date]" 
               value="<?= ($r['DATE']) ? $r['DATE']->format('Y-m-d') : '' ?>">
    </td>

    <td><textarea name="rows[<?= $i ?>][description]"><?= $r['DESCRIPTION']; ?></textarea></td>
    <td><textarea name="rows[<?= $i ?>][service]"><?= $r['SERVICE']; ?></textarea></td>

    <td>
        <select name="rows[<?= $i ?>][kerusakan]">
            <option value="">-- pilih --</option>
            <?php foreach($KERUSAKAN_LIST as $k): ?>
            <option value="<?= $k['ID'] ?>" 
                <?= ($k['ID'] == $r['ID_KERUSAKAN']) ? 'selected' : '' ?>>
                <?= $k['KERUSAKAN'] ?>
            </option>
            <?php endforeach; ?>
        </select>
    </td>

    <td>
        <select name="rows[<?= $i ?>][kategori]">
            <option value="">-- pilih --</option>
            <?php foreach($KATEGORI_LIST as $k): ?>
            <option value="<?= $k['ID'] ?>"
                <?= ($r['KATEGORI'] == $k['ID']) ? 'selected' : '' ?>>
                <?= $k['KELOMPOK'] ?>
            </option>
            <?php endforeach; ?>
        </select>
    </td>

    <td><input type="text" name="rows[<?= $i ?>][time]" value="<?= $r['TIME']; ?>"></td>

    <td><input type="checkbox" name="rows[<?= $i ?>][del]" value="1"></td>
</tr>
<?php 
$i++; 
endwhile; 
?>
</tbody>
</table>
</div>

</form>

<script>
let rowIndex = <?= $i ?>;

$('#btnAdd').click(function() {
    let i = rowIndex++;

    let html = `
<tr>
    <td>
        <input type="hidden" name="rows[${i}][id]" value="">
        <input type="date" name="rows[${i}][date]" value="">
    </td>

    <td><textarea name="rows[${i}][description]"></textarea></td>
    <td><textarea name="rows[${i}][service]"></textarea></td>

    <td>
        <select name="rows[${i}][kerusakan]">
            <option value="">-- pilih --</option>
            <?php foreach($KERUSAKAN_LIST as $k): ?>
            <option value="<?= $k['ID'] ?>"><?= $k['KERUSAKAN'] ?></option>
            <?php endforeach; ?>
        </select>
    </td>

    <td>
        <select name="rows[${i}][kategori]">
            <option value="">-- pilih --</option>
            <?php foreach($KATEGORI_LIST as $k): ?>
            <option value="<?= $k['ID'] ?>"><?= $k['KELOMPOK'] ?></option>
            <?php endforeach; ?>
        </select>
    </td>

    <td><input type="text" name="rows[${i}][time]"></td>

    <td><input type="checkbox" name="rows[${i}][del]" value="1"></td>
</tr>`;

    $('#detailBody').prepend(html);
});
</script>

</body>
</html>

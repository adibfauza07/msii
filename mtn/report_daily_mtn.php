<?php
require "../config/database.php";

// Ambil parameter GET
$from  = isset($_GET['from']) ? $_GET['from'] : "";
$to    = isset($_GET['to']) ? $_GET['to'] : "";
$mac   = isset($_GET['mac']) ? intval($_GET['mac']) : 0;
$plant = isset($_GET['plant']) ? intval($_GET['plant']) : 0;

// Helper format tanggal SQL Server
function sqlDate($dt){
    if(!$dt) return "";
    if($dt instanceof DateTime) return $dt->format("d-M-Y");
    return date("d-M-Y", strtotime($dt));
}

// Helper format waktu SQL Server
function sqlTime($dt){
    if(!$dt) return "";
    if($dt instanceof DateTime) return $dt->format("H:i");
    return substr($dt, 0, 5);
}

// Jika tombol Tampilkan ditekan
$data = [];
// Menggunakan >= 0 agar input nilai 0 tetap memicu eksekusi query
if ($from && $to && $mac >= 0 && $plant >= 0) {

    $params = [
        $from, 
        $to,
        $mac,
        $plant
    ];

    $sql = "EXEC sp_daily_mtn_web @from_date=?, @end_date=?, @mac=?, @plan=?";
    $q = q($sql, $params);

    while($r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)){
        $data[] = $r;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>DAILY MTN REPORT</title>

<link rel="stylesheet" href="../assets/bootstrap.min.css">

<style>
body { background:#f4f4f4; padding:20px; font-family:Arial; }
h2 { text-align:center; margin-bottom:20px; }
.table th {
    background:#ddd;
    text-align:center;
}
</style>

</head>

<body>
<!-- BACK BUTTON -->
<a href="dashboard_mtn.php" class="btn btn-secondary" style="margin-bottom:15px;">
    ← Kembali ke Menu 
</a>
<h2>DAILY MTN REPORT</h2>

<form method="get" class="mb-3">

<div class="row mb-2">

    <div class="col-md-3">
        <label>Dari Tanggal</label>
        <input type="date" name="from" class="form-control" value="<?= $from ?>">
    </div>

    <div class="col-md-3">
        <label>Sampai Tanggal</label>
        <input type="date" name="to" class="form-control" value="<?= $to ?>">
    </div>

    <div class="col-md-2">
        <label>MAC</label>
        <input type="number" name="mac" class="form-control" value="<?= $mac ?>">
    </div>

    <div class="col-md-2">
        <label>Plant</label>
        <input type="number" name="plant" class="form-control" value="<?= $plant ?>">
    </div>

    <div class="col-md-2" style="padding-top:30px;">
        <button class="btn btn-primary w-100">Tampilkan</button>
    </div>

</div>

</form>


<!-- TABEL DATA -->
<div class="table-responsive">
<table class="table table-bordered table-striped">
<thead>
<tr>
    <th>Tanggal</th>
    <th>MAC</th>
    <th>Problem</th>
    <th>Cause</th>
    <th>PIC</th>
    <th>From</th>
    <th>To</th>
    <th>Status</th>
    <th>Remark</th>
</tr>
</thead>
<a href="report_daily_mtn_print.php?from=<?= $from ?>&to=<?= $to ?>&mac=<?= $mac ?>&plant=<?= $plant ?>" 
   target="_blank" class="btn btn-info">
   Cetak versi PHP
</a>

<tbody>

<?php if(empty($data)): ?>
<tr><td colspan="9" class="text-center text-muted">Tidak ada data</td></tr>
<?php endif; ?>

<?php foreach($data as $d): ?>
<tr>
    <td><?= sqlDate($d['DATE']) ?></td>
    <td><?= htmlspecialchars($d['MAC']) ?></td>
    <td><?= htmlspecialchars($d['PROBLEM']) ?></td>
    <td><?= htmlspecialchars($d['CAUSE']) ?></td>
    <td><?= htmlspecialchars($d['PIC']) ?></td>

    <td><?= sqlTime($d['FROM_HOURS']) ?></td>
    <td><?= sqlTime($d['TO_HOURS']) ?></td>

    <td><?= htmlspecialchars($d['STATUS']) ?></td>
    <td><?= htmlspecialchars($d['DESCRIPTION']) ?></td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>

</body>
</html>

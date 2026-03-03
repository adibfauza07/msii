<?php
require "../config/database.php";

// ambil semua mesin aktif
$sql = "SELECT MAC_ID, MAC, PLANT, TONAGE 
        FROM MAC_MTN
        WHERE MAC_ACTIVE = 1
        ORDER BY PLANT ASC";

$res = q($sql);
?>
<!DOCTYPE html>
<html>
<head>
<title>History Machine - Master</title>
<link rel="stylesheet" href="../assets/bootstrap.min.css">
<script src="../assets/jquery.min.js"></script>
<script src="../assets/bootstrap.min.js"></script>

<style>
body { font-family:Arial; padding:15px; background:#e8e8e8; }
h2 { text-align:center; margin-bottom:20px; }
.table-master { background:white; }
</style>
</head>
<body>

<h2>HISTORY OF MACHINE</h2>

<table class="table table-bordered table-master">
<thead class="thead-light">
<tr>
    <th>#</th>
    <th>MACHINE</th>
    <th>PLANT</th>
    <th>TONAGE</th>
    <th>DETAIL</th>
</tr>
</thead>
<tbody>
<?php $no=1; while($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)): ?>
<tr>
    <td><?= $no++; ?></td>
    <td><?= $row['MAC']; ?></td>
    <td><?= $row['PLANT']; ?></td>
    <td><?= $row['TONAGE']; ?></td>
    <td>
        <a class="btn btn-sm btn-primary" 
           href="history_detail.php?idmac=<?= $row['MAC_ID']; ?>">
           Open
        </a>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

</body>
</html>

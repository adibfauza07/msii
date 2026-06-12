<?php
require "../config/database_ppic.php";

$sql = "SELECT * FROM barcode_showa ORDER BY id DESC";
$data = q($sql);
?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../assets/bootstrap.min.css">
</head>
<body class="p-4">

<h3>Daftar Barcode Showa</h3>

<table border="1" cellpadding="5" cellspacing="0" class="table table-bordered">
    <tr>
        <th>ID</th>
        <th>Part Code</th>
        <th>Part No</th>
        <th>Part Name</th>
        <th>Polibag</th>
        <th>Box</th>
        <th>QR</th>
    </tr>

<?php while($r = sqlsrv_fetch_array($data, SQLSRV_FETCH_ASSOC)){ ?>
<tr>
    <td><?= $r['id'] ?></td>
    <td><?= $r['part_code'] ?></td>
    <td><?= $r['part_no'] ?></td>
    <td><?= $r['part_name'] ?></td>
    <td><?= $r['qty_polibag'] ?></td>
    <td><?= $r['qty_box'] ?></td>
    <td><img src="<?= $r['qr_path'] ?>" width="100"></td>
</tr>
<?php } ?>
</table>

</body>
</html>

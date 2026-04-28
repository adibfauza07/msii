<?php
require_once __DIR__ . '/../config/database_p1.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("ID tidak valid.");
}

$sql = "SELECT ID, TRIAL_CODE, Weight_Part_Actual, CAVITY 
        FROM TRIAL_PE_WPart_ACT 
        WHERE ID = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));
$row  = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$row) {
    die("Data tidak ditemukan.");
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Edit Actual Trial</title>
<link href="../assets/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">

<h3>Edit Actual Trial</h3>

<form method="post" action="update_act.php">
    <input type="hidden" name="ID" value="<?= $row['ID'] ?>">

    <div class="mb-3">
        <label>Trial Code</label>
        <input type="text" class="form-control" value="<?= $row['TRIAL_CODE'] ?>" readonly>
    </div>

    <div class="mb-3">
        <label>Weight Part Actual</label>
        <input type="number" step="0.001" name="Weight_Part_Actual" class="form-control"
               value="<?= $row['Weight_Part_Actual'] ?>" required>
    </div>

    <div class="mb-3">
        <label>Cavity</label>
        <input type="number" name="CAVITY" class="form-control"
               value="<?= $row['CAVITY'] ?>" required>
    </div>

    <button type="submit" class="btn btn-primary">Update</button>
    <a href="input_trial_pe.php?code=<?= $row['TRIAL_CODE'] ?>" class="btn btn-secondary">Kembali</a>
</form>

</body>
</html>

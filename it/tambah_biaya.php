<?php
session_start();
if (!isset($_SESSION['db_user'])) {
    header("Location: login.php");
    exit();
}

include "../config/database_p1.php";

$project_id = $_GET['id'];

if (isset($_POST['simpan'])) {

    $tanggal = $_POST['tanggal'];
    $kategori = $_POST['kategori'];
    $keterangan = $_POST['keterangan'];
    $nominal = $_POST['nominal'];

    $sql = "INSERT INTO it_project_costs
            (project_id, tanggal, kategori, keterangan, nominal)
            VALUES (?, ?, ?, ?, ?)";

    $params = array(
        $project_id,
        $tanggal,
        $kategori,
        $keterangan,
        $nominal
    );

    $query = sqlsrv_query($conn, $sql, $params);

    if ($query) {
        header("Location: detail_project.php?id=".$project_id);
        exit();
    } else {
        die(print_r(sqlsrv_errors(), true));
    }
}

$sql_project = "SELECT * FROM it_projects WHERE id=?";
$q_project = sqlsrv_query($conn, $sql_project, array($project_id));
$project = sqlsrv_fetch_array($q_project, SQLSRV_FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Tambah Biaya</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-4">

    <h3>Tambah Biaya Project</h3>
    <p><?php echo $project['nama_software']; ?></p>

    <form method="POST" class="card p-4 bg-white">

        <div class="mb-3">
            <label>Tanggal</label>
            <input type="date" name="tanggal" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Kategori Biaya</label>
            <select name="kategori" class="form-select">
                <option>Developer</option>
                <option>Server</option>
                <option>Domain</option>
                <option>Tools</option>
                <option>Testing</option>
                <option>Maintenance</option>
                <option>Lain-lain</option>
            </select>
        </div>

        <div class="mb-3">
            <label>Keterangan</label>
            <textarea name="keterangan" class="form-control"></textarea>
        </div>

        <div class="mb-3">
            <label>Nominal</label>
            <input type="number" name="nominal" class="form-control" required>
        </div>

        <button type="submit" name="simpan" class="btn btn-primary">Simpan Biaya</button>
        <a href="project.php" class="btn btn-secondary">Kembali</a>

    </form>

</div>

</body>
</html>
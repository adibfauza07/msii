<?php
session_start();
if (!isset($_SESSION['db_user'])) {
    header("Location: login.php");
    exit();
}

include "../config/database_p1.php";

$sql = "SELECT * FROM it_projects ORDER BY id DESC";
$query = sqlsrv_query($conn, $sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Data Project IT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between mb-3">
        <h3>Data Project IT</h3>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="tambah_project.php" class="btn btn-primary">Tambah Project</a>
        </div>
    </div>

    <table class="table table-bordered table-striped bg-white">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama project</th>
                <th>Department</th>
                <th>PIC Dept</th>
                <th>PIC IT</th>
                <th>Deadline</th>
                <th>Budget</th>
                <th>Progress</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>

        <tbody>
        <?php
        $no = 1;
        while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
        ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td><?php echo $row['nama_software']; ?></td>
                <td><?php echo $row['department']; ?></td>
                <td><?php echo $row['pic_department']; ?></td>
                <td><?php echo $row['pic_it']; ?></td>
                <td>
                    <?php
                    if ($row['deadline']) {
                        echo $row['deadline']->format('Y-m-d');
                    }
                    ?>
                <td>
    <a href="edit_project.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">
        Edit
    </a>

    <a href="tambah_progress.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success">
        Progress
    </a>

    <a href="tambah_biaya.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">
        Biaya
    </a>

    <a href="detail_project.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
        Detail
    </a>

    <a href="hapus_project.php?id=<?php echo $row['id']; ?>"
       class="btn btn-sm btn-danger"
       onclick="return confirm('Yakin hapus project ini? Semua progress dan biaya juga akan terhapus.')">
        Hapus
    </a>
</td>
            </tr>
        <?php } ?>
        </tbody>
    </table>

</div>

</body>
</html>
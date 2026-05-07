<?php

session_start();

if (!isset($_SESSION['db_user'])) {
    header("Location: login.php");
    exit();
}

include "../config/database_p1.php";

$sql = "SELECT 
            p.*, 
            pr.nama_software,
            pr.department
        FROM it_project_progress p
        LEFT JOIN it_projects pr ON p.project_id = pr.id
        ORDER BY p.id DESC";

$query = sqlsrv_query($conn, $sql);

if ($query === false) {
    die(print_r(sqlsrv_errors(), true));
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Progress Pengerjaan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between mb-3">
        <h3>Progress Pengerjaan IT</h3>

        <div>
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="project.php" class="btn btn-primary">Data Project</a>
        </div>
    </div>

    <table class="table table-bordered table-striped bg-white">
        <thead>
            <tr>
                <th>No</th>
                <th>Project</th>
                <th>Department</th>
                <th>Tanggal</th>
                <th>Jam</th>
                <th>Total Jam</th>
                <th>Catatan</th>
                <th>Progress</th>
                <th>Status</th>
                <th>Foto</th>
                <th width="150">Aksi</th>
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

                <td>
                    <?php
                    if ($row['tanggal']) {
                        echo $row['tanggal']->format('Y-m-d');
                    }
                    ?>
                </td>

                <td>
                    <?php
                    if ($row['jam_mulai']) {
                        echo $row['jam_mulai']->format('H:i');
                    }

                    echo " - ";

                    if ($row['jam_selesai']) {
                        echo $row['jam_selesai']->format('H:i');
                    }
                    ?>
                </td>

                <td><?php echo $row['total_jam']; ?></td>

                <td><?php echo $row['catatan']; ?></td>

                <td><?php echo $row['progress']; ?>%</td>

                <td><?php echo $row['status']; ?></td>

                <td>
                    <?php if ($row['foto'] != "") { ?>
                        <a href="uploads/<?php echo $row['foto']; ?>" target="_blank">
                            Lihat
                        </a>
                    <?php } else { ?>
                        -
                    <?php } ?>
                </td>

                <td>
                    <a href="edit_progress.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">
                        Edit
                    </a>

                    <a href="hapus_progress.php?id=<?php echo $row['id']; ?>&project_id=<?php echo $row['project_id']; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Yakin hapus progress ini?')">
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
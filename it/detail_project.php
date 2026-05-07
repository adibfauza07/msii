<?php
session_start();
if (!isset($_SESSION['db_user'])) {
    header("Location: login.php");
    exit();
}

include "../config/database_p1.php";

$id = $_GET['id'];

$sql = "SELECT * FROM it_projects WHERE id=?";
$q = sqlsrv_query($conn, $sql, array($id));
$project = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);

$sql_progress = "SELECT * FROM it_project_progress WHERE project_id=? ORDER BY id DESC";
$q_progress = sqlsrv_query($conn, $sql_progress, array($id));

$sql_biaya = "SELECT * FROM it_project_costs WHERE project_id=? ORDER BY id DESC";
$q_biaya = sqlsrv_query($conn, $sql_biaya, array($id));

$sql_total_biaya = "SELECT ISNULL(SUM(nominal),0) AS total FROM it_project_costs WHERE project_id=?";
$q_total_biaya = sqlsrv_query($conn, $sql_total_biaya, array($id));
$total_biaya = sqlsrv_fetch_array($q_total_biaya, SQLSRV_FETCH_ASSOC);

$sisa_budget = $project['budget'] - $total_biaya['total'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Detail Project</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between mb-3">
        <h3>Detail Project</h3>
        <a href="project.php" class="btn btn-secondary">Kembali</a>
    </div>

    <div class="card p-4 mb-4">
        <h4><?php echo $project['nama_software']; ?></h4>
        <p><?php echo $project['deskripsi']; ?></p>

        <table class="table table-bordered">
            <tr>
                <th>Department</th>
                <td><?php echo $project['department']; ?></td>
            </tr>
            <tr>
                <th>PIC Department</th>
                <td><?php echo $project['pic_department']; ?></td>
            </tr>
            <tr>
                <th>PIC IT</th>
                <td><?php echo $project['pic_it']; ?></td>
            </tr>
            <tr>
                <th>Deadline</th>
                <td>
                    <?php
                    if ($project['deadline']) {
                        echo $project['deadline']->format('Y-m-d');
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th>Budget</th>
                <td>Rp <?php echo number_format($project['budget'],0,',','.'); ?></td>
            </tr>
            <tr>
                <th>Total Biaya</th>
                <td>Rp <?php echo number_format($total_biaya['total'],0,',','.'); ?></td>
            </tr>
            <tr>
                <th>Sisa Budget</th>
                <td>Rp <?php echo number_format($sisa_budget,0,',','.'); ?></td>
            </tr>
            <tr>
                <th>Progress</th>
                <td><?php echo $project['progress']; ?>%</td>
            </tr>
            <tr>
                <th>Status</th>
                <td><?php echo $project['status']; ?></td>
            </tr>
        </table>

        <div>
            <a href="tambah_progress.php?id=<?php echo $id; ?>" class="btn btn-success">Tambah Progress</a>
            <a href="tambah_biaya.php?id=<?php echo $id; ?>" class="btn btn-warning">Tambah Biaya</a>
        </div>
    </div>

    <div class="card p-4 mb-4">
        <h4>Riwayat Progress</h4>

        <table class="table table-bordered table-striped">
            <tr>
                <th>Tanggal</th>
                <th>Jam</th>
                <th>Total Jam</th>
                <th>Catatan</th>
                <th>Progress</th>
                <th>Status</th>
                <th>Foto</th>
            </tr>

            <?php while ($p = sqlsrv_fetch_array($q_progress, SQLSRV_FETCH_ASSOC)) { ?>
            <tr>
                <td><?php echo $p['tanggal']->format('Y-m-d'); ?></td>
                <td>
                    <?php
                    if ($p['jam_mulai']) echo $p['jam_mulai']->format('H:i');
                    echo " - ";
                    if ($p['jam_selesai']) echo $p['jam_selesai']->format('H:i');
                    ?>
                </td>
                <td><?php echo $p['total_jam']; ?></td>
                <td><?php echo $p['catatan']; ?></td>
                <td><?php echo $p['progress']; ?>%</td>
                <td><?php echo $p['status']; ?></td>
                <td>
                    <?php if ($p['foto'] != "") { ?>
                        <a href="uploads/<?php echo $p['foto']; ?>" target="_blank">Lihat</a>
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
        </table>
    </div>

    <div class="card p-4">
        <h4>Riwayat Biaya</h4>

        <table class="table table-bordered table-striped">
            <tr>
                <th>Tanggal</th>
                <th>Kategori</th>
                <th>Keterangan</th>
                <th>Nominal</th>
            </tr>

            <?php while ($b = sqlsrv_fetch_array($q_biaya, SQLSRV_FETCH_ASSOC)) { ?>
            <tr>
                <td><?php echo $b['tanggal']->format('Y-m-d'); ?></td>
                <td><?php echo $b['kategori']; ?></td>
                <td><?php echo $b['keterangan']; ?></td>
                <td>Rp <?php echo number_format($b['nominal'],0,',','.'); ?></td>
            </tr>
            <?php } ?>
        </table>
    </div>

</div>

</body>
</html>
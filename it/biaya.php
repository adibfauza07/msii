<?php
session_start();
if (!isset($_SESSION['db_user'])) {
    header("Location: login.php");
    exit();
}

include "../config/database_p1.php";

$sql = "SELECT 
            c.*, 
            p.nama_software,
            p.department
        FROM it_project_costs c
        LEFT JOIN it_projects p ON c.project_id = p.id
        ORDER BY c.id DESC";

$query = sqlsrv_query($conn, $sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Budget & Biaya</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between mb-3">
        <h3>Budget & Biaya Project</h3>
        <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
    </div>

    <table class="table table-bordered table-striped bg-white">
        <tr>
            <th>No</th>
            <th>Project</th>
            <th>Department</th>
            <th>Tanggal</th>
            <th>Kategori</th>
            <th>Keterangan</th>
            <th>Nominal</th>
        </tr>

        <?php
        $no = 1;
        while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
        ?>
        <tr>
            <td><?php echo $no++; ?></td>
            <td><?php echo $row['nama_software']; ?></td>
            <td><?php echo $row['department']; ?></td>
            <td><?php echo $row['tanggal']->format('Y-m-d'); ?></td>
            <td><?php echo $row['kategori']; ?></td>
            <td><?php echo $row['keterangan']; ?></td>
            <td>Rp <?php echo number_format($row['nominal'],0,',','.'); ?></td>
        </tr>
        <?php } ?>
    </table>

</div>

</body>
</html>
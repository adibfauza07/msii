<?php

session_start();

if (!isset($_SESSION['db_user'])) {
    header("Location: login.php");
    exit();
}

$serverName = "192.168.0.4";

if (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == "p2") {
    $serverName = "192.168.0.9";
}

$connectionOptions = array(
    "Database" => "msData",
    "Uid" => $_SESSION['db_user'],
    "PWD" => $_SESSION['db_pass'],
    "CharacterSet" => "UTF-8"
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {

    die(print_r(sqlsrv_errors(), true));

}

$sql_total = "SELECT COUNT(*) AS total FROM it_projects";
$q_total = sqlsrv_query($conn, $sql_total);
$total = sqlsrv_fetch_array($q_total, SQLSRV_FETCH_ASSOC);

$sql_dev = "SELECT COUNT(*) AS total FROM it_projects WHERE status='Development'";
$q_dev = sqlsrv_query($conn, $sql_dev);
$dev = sqlsrv_fetch_array($q_dev, SQLSRV_FETCH_ASSOC);

$sql_done = "SELECT COUNT(*) AS total FROM it_projects WHERE status='Selesai'";
$q_done = sqlsrv_query($conn, $sql_done);
$done = sqlsrv_fetch_array($q_done, SQLSRV_FETCH_ASSOC);

$sql_late = "SELECT COUNT(*) AS total 
             FROM it_projects 
             WHERE deadline < GETDATE() 
             AND status <> 'Selesai'";

$q_late = sqlsrv_query($conn, $sql_late);
$late = sqlsrv_fetch_array($q_late, SQLSRV_FETCH_ASSOC);

$sql_budget = "SELECT ISNULL(SUM(budget),0) AS total FROM it_projects";
$q_budget = sqlsrv_query($conn, $sql_budget);
$budget = sqlsrv_fetch_array($q_budget, SQLSRV_FETCH_ASSOC);

$sql_biaya = "SELECT ISNULL(SUM(nominal),0) AS total FROM it_project_costs";
$q_biaya = sqlsrv_query($conn, $sql_biaya);
$biaya = sqlsrv_fetch_array($q_biaya, SQLSRV_FETCH_ASSOC);

$sql_project = "SELECT TOP 10 * FROM it_projects ORDER BY id DESC";
$q_project = sqlsrv_query($conn, $sql_project);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <title>Dashboard IT Project</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>

        body {
            background: #eef2f7;
            font-family: Arial, sans-serif;
        }

        .sidebar {
            width: 240px;
            height: 100vh;
            background: #1e293b;
            position: fixed;
            left: 0;
            top: 0;
            color: white;
            padding: 25px 15px;
        }

        .sidebar h3 {
            font-size: 20px;
            margin-bottom: 30px;
        }

        .sidebar a {
            display: block;
            color: #cbd5e1;
            text-decoration: none;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #2563eb;
            color: white;
        }

        .content {
            margin-left: 260px;
            padding: 25px;
        }

        .card-box {
            background: white;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .card-box h4 {
            font-size: 15px;
            color: #64748b;
            margin-bottom: 10px;
        }

        .card-box h2 {
            font-size: 28px;
            font-weight: bold;
            color: #1e293b;
        }

        .table-box {
            background: white;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .badge-status {
            padding: 6px 10px;
            border-radius: 8px;
            color: white;
            font-size: 12px;
        }

        .Request { background: #64748b; }
        .Planning { background: #8b5cf6; }
        .Development { background: #2563eb; }
        .Testing { background: #f59e0b; }
        .Revision { background: #ef4444; }
        .Deployment { background: #0ea5e9; }
        .Selesai { background: #16a34a; }
        .Pending { background: #475569; }

    </style>

</head>

<body>

<div class="sidebar">

    <h3>MENU PROJECT</h3>

    <a href="dashboard.php" class="active">Dashboard</a>
    <a href="department.php">Department</a>
	<a href="project.php">Data Project</a>
    <a href="tambah_project.php">Tambah Project</a>
    <a href="progress.php">Progress Pengerjaan</a>
    <a href="biaya.php">Budget & Biaya</a>
    <a href="laporan.php">Laporan</a>
    <a href="logout.php">Logout</a>
    <a href="../index.php">Kembali Portal</a>

</div>

<div class="content">

    <h2>Dashboard IT Project</h2>

    <p>Monitoring pembuatan software internal perusahaan.</p>

    <div class="row">

        <div class="col-md-3">
            <div class="card-box">
                <h4>Total Project</h4>
                <h2><?php echo $total['total']; ?></h2>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-box">
                <h4>Development</h4>
                <h2><?php echo $dev['total']; ?></h2>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-box">
                <h4>Selesai</h4>
                <h2><?php echo $done['total']; ?></h2>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-box">
                <h4>Lewat Deadline</h4>
                <h2><?php echo $late['total']; ?></h2>
            </div>
        </div>

    </div>

    <div class="row">

        <div class="col-md-6">
            <div class="card-box">
                <h4>Total Budget</h4>
                <h2>
                    Rp <?php echo number_format($budget['total'],0,',','.'); ?>
                </h2>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card-box">
                <h4>Total Biaya Terpakai</h4>
                <h2>
                    Rp <?php echo number_format($biaya['total'],0,',','.'); ?>
                </h2>
            </div>
        </div>

    </div>

    <div class="table-box">

        <h4>Project Terbaru</h4>

        <table class="table table-bordered table-striped">

            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Software</th>
                    <th>Department</th>
                    <th>PIC IT</th>
                    <th>Deadline</th>
                    <th>Progress</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            <?php
            $no = 1;

            while ($row = sqlsrv_fetch_array($q_project, SQLSRV_FETCH_ASSOC)) {
            ?>

                <tr>

                    <td><?php echo $no++; ?></td>

                    <td><?php echo $row['nama_software']; ?></td>

                    <td><?php echo $row['department']; ?></td>

                    <td><?php echo $row['pic_it']; ?></td>

                    <td>
                        <?php
                        if ($row['deadline']) {
                            echo $row['deadline']->format('Y-m-d');
                        }
                        ?>
                    </td>

                    <td><?php echo $row['progress']; ?>%</td>

                    <td>
                        <span class="badge-status <?php echo $row['status']; ?>">
                            <?php echo $row['status']; ?>
                        </span>
                    </td>

                </tr>

            <?php } ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "auth.php";
require_once "../config/database_p2.php";

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

function runQuery($conn, $sql, $params = array())
{
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        echo "<pre>";
        echo "SQL ERROR:\n";
        print_r(sqlsrv_errors());
        echo "\nQUERY:\n" . $sql;
        echo "</pre>";
        exit();
    }

    return $stmt;
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
$user_department = isset($_SESSION['department']) ? $_SESSION['department'] : '';
$nama_lengkap = isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : '';

$where = "";
$params = array();

if ($role != 'admin') {
    $where = " WHERE department = ?";
    $params[] = $user_department;
}

$sql_total = "SELECT COUNT(*) AS total
              FROM dbo.it_projects
              $where";
$q_total = runQuery($conn, $sql_total, $params);
$total = sqlsrv_fetch_array($q_total, SQLSRV_FETCH_ASSOC);

if ($where == "") {
    $sql_progress = "SELECT COUNT(*) AS total
                     FROM dbo.it_projects
                     WHERE status NOT IN ('Finish','Done','Completed')";
    $params_progress = array();
} else {
    $sql_progress = "SELECT COUNT(*) AS total
                     FROM dbo.it_projects
                     WHERE department = ?
                     AND status NOT IN ('Finish','Done','Completed')";
    $params_progress = array($user_department);
}
$q_progress = runQuery($conn, $sql_progress, $params_progress);
$progress = sqlsrv_fetch_array($q_progress, SQLSRV_FETCH_ASSOC);

if ($where == "") {
    $sql_done = "SELECT COUNT(*) AS total
                 FROM dbo.it_projects
                 WHERE status IN ('Finish','Done','Completed')";
    $params_done = array();
} else {
    $sql_done = "SELECT COUNT(*) AS total
                 FROM dbo.it_projects
                 WHERE department = ?
                 AND status IN ('Finish','Done','Completed')";
    $params_done = array($user_department);
}
$q_done = runQuery($conn, $sql_done, $params_done);
$done = sqlsrv_fetch_array($q_done, SQLSRV_FETCH_ASSOC);

if ($where == "") {
    $sql_late = "SELECT COUNT(*) AS total
                 FROM dbo.it_projects
                 WHERE deadline < GETDATE()
                 AND status NOT IN ('Finish','Done','Completed')";
    $params_late = array();
} else {
    $sql_late = "SELECT COUNT(*) AS total
                 FROM dbo.it_projects
                 WHERE department = ?
                 AND deadline < GETDATE()
                 AND status NOT IN ('Finish','Done','Completed')";
    $params_late = array($user_department);
}
$q_late = runQuery($conn, $sql_late, $params_late);
$late = sqlsrv_fetch_array($q_late, SQLSRV_FETCH_ASSOC);

$sql_budget = "SELECT ISNULL(SUM(budget),0) AS total
               FROM dbo.it_projects
               $where";
$q_budget = runQuery($conn, $sql_budget, $params);
$budget = sqlsrv_fetch_array($q_budget, SQLSRV_FETCH_ASSOC);

if ($role == 'admin') {
    $sql_biaya = "SELECT ISNULL(SUM(c.nominal),0) AS total
                  FROM dbo.it_project_costs c
                  LEFT JOIN dbo.it_projects p
                  ON c.project_id = p.id";
    $params_biaya = array();
} else {
    $sql_biaya = "SELECT ISNULL(SUM(c.nominal),0) AS total
                  FROM dbo.it_project_costs c
                  LEFT JOIN dbo.it_projects p
                  ON c.project_id = p.id
                  WHERE p.department = ?";
    $params_biaya = array($user_department);
}
$q_biaya = runQuery($conn, $sql_biaya, $params_biaya);
$biaya = sqlsrv_fetch_array($q_biaya, SQLSRV_FETCH_ASSOC);

if ($role == 'admin') {
    $sql_project = "SELECT TOP 10 *
                    FROM dbo.it_projects
                    ORDER BY id DESC";
    $params_project = array();
} else {
    $sql_project = "SELECT TOP 10 *
                    FROM dbo.it_projects
                    WHERE department = ?
                    ORDER BY id DESC";
    $params_project = array($user_department);
}
$q_project = runQuery($conn, $sql_project, $params_project);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard IT Project</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          rel="stylesheet">

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
    </style>
</head>

<body>

<div class="sidebar">
    <h3>MENU PROJECT</h3>

    <a href="dashboard.php" class="active">Dashboard</a>
    <a href="department.php">Department</a>
    <a href="project.php">Data Project</a>
    <a href="tambah_project.php">Tambah Project</a>
    <a href="laporan.php">Laporan</a>
    <a href="laporan_budget.php">Laporan Budget</a>

    <?php if ($role == 'admin') { ?>
        <a href="user.php">Management User</a>
    <?php } ?>

    <a href="logout.php">Logout</a>
</div>

<div class="content">

    <h2>Dashboard IT Project</h2>

    <p>
        Welcome,
        <strong><?php echo htmlspecialchars($nama_lengkap); ?></strong>
        |
        Department:
        <strong><?php echo htmlspecialchars($user_department); ?></strong>
        |
        Role:
        <strong><?php echo htmlspecialchars($role); ?></strong>
    </p>

    <div class="row">

        <div class="col-md-3">
            <div class="card-box">
                <h4>Total Project</h4>
                <h2><?php echo $total['total']; ?></h2>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-box">
                <h4>On Progress</h4>
                <h2><?php echo $progress['total']; ?></h2>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-box">
                <h4>Finish</h4>
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
                    Rp <?php echo number_format($budget['total'], 0, ',', '.'); ?>
                </h2>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card-box">
                <h4>Total Biaya Actual</h4>
                <h2>
                    Rp <?php echo number_format($biaya['total'], 0, ',', '.'); ?>
                </h2>
            </div>
        </div>

    </div>

    <div class="table-box mt-3">
        <h4>Project Terbaru</h4>

        <table class="table table-bordered table-striped mt-3">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Project</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Deadline</th>
                    <th>Budget</th>
                </tr>
            </thead>

            <tbody>
            <?php
            $no = 1;
            while ($row = sqlsrv_fetch_array($q_project, SQLSRV_FETCH_ASSOC)) {
            ?>
                <tr>
                    <td><?php echo $no++; ?></td>

                    <td>
                        <?php
                        echo isset($row['nama_software'])
                            ? htmlspecialchars($row['nama_software'])
                            : '-';
                        ?>
                    </td>

                    <td>
                        <?php
                        echo isset($row['department'])
                            ? htmlspecialchars($row['department'])
                            : '-';
                        ?>
                    </td>

                    <td>
                        <?php
                        echo isset($row['status'])
                            ? htmlspecialchars($row['status'])
                            : '-';
                        ?>
                    </td>

                    <td>
                        <?php
                        if (isset($row['deadline']) && $row['deadline'] instanceof DateTime) {
                            echo $row['deadline']->format('Y-m-d');
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>

                    <td>
                        Rp <?php
                        echo isset($row['budget'])
                            ? number_format($row['budget'], 0, ',', '.')
                            : '0';
                        ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
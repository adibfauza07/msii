<?php
session_start();

if (!isset($_SESSION['db_user'])) {
    header("Location: login.php");
    exit();
}

include "../config/database_p1.php";

if ($conn === false) {
    die("Koneksi database gagal. Coba refresh atau cek koneksi ke server SQL.");
}

$where = " WHERE 1=1 ";
$params = array();

if (isset($_GET['department']) && $_GET['department'] != "") {
    $where .= " AND department = ? ";
    $params[] = $_GET['department'];
}

if (isset($_GET['status']) && $_GET['status'] != "") {
    $where .= " AND status = ? ";
    $params[] = $_GET['status'];
}

$sql = "SELECT 
            id,
            nama_software,
            department,
            pic_it,
            deadline,
            budget,
            progress,
            status
        FROM it_projects
        $where
        ORDER BY id DESC";

$query = sqlsrv_query($conn, $sql, $params);

if ($query === false) {
    die(print_r(sqlsrv_errors(), true));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Project IT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between mb-3 no-print">
        <h3>Laporan Project IT</h3>

        <div>
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <button onclick="window.print()" class="btn btn-primary">Print</button>
        </div>
    </div>

    <form method="GET" class="card p-3 mb-3 no-print">
        <div class="row">

            <div class="col-md-5">
                <label>Department</label>
                <select name="department" class="form-select">
                    <option value="">Semua Department</option>

                    <?php
                    $sql_dept = "SELECT DEP_NAME FROM DEPT ORDER BY DEP_NAME ASC";
                    $q_dept = sqlsrv_query($conn, $sql_dept);

                    if ($q_dept !== false) {
                        while ($dept = sqlsrv_fetch_array($q_dept, SQLSRV_FETCH_ASSOC)) {
                            $dep_name = rtrim($dept['DEP_NAME']);
                            $selected = "";

                            if (isset($_GET['department']) && $_GET['department'] == $dep_name) {
                                $selected = "selected";
                            }

                            echo "<option value='".$dep_name."' ".$selected.">".$dep_name."</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-5">
                <label>Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>

                    <?php
                    $statuses = array(
                        "Request",
                        "Planning",
                        "Development",
                        "Testing",
                        "Revision",
                        "Deployment",
                        "Selesai",
                        "Pending"
                    );

                    foreach ($statuses as $st) {
                        $selected = "";

                        if (isset($_GET['status']) && $_GET['status'] == $st) {
                            $selected = "selected";
                        }

                        echo "<option value='".$st."' ".$selected.">".$st."</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-2">
                <label>&nbsp;</label>
                <button class="btn btn-success w-100">Filter</button>
            </div>

        </div>
    </form>

    <div class="card p-4 bg-white">

        <h4 class="text-center">LAPORAN PROJECT SOFTWARE IT</h4>
        <p class="text-center">PT IMC TEKNO INDONESIA</p>

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Software</th>
                    <th>Department</th>
                    <th>PIC IT</th>
                    <th>Deadline</th>
                    <th>Budget</th>
                    <th>Progress</th>
                    <th>Status</th>
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
                        <td><?php echo $row['pic_it']; ?></td>

                        <td>
                            <?php
                            if ($row['deadline']) {
                                echo $row['deadline']->format('Y-m-d');
                            }
                            ?>
                        </td>

                        <td>
                            Rp <?php echo number_format($row['budget'], 0, ',', '.'); ?>
                        </td>

                        <td><?php echo $row['progress']; ?>%</td>
                        <td><?php echo $row['status']; ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

    </div>

</div>

</body>
</html>
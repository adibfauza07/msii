<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "auth.php";
require_once "../config/database_p2.php";

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

$login_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
$login_department = isset($_SESSION['department']) ? trim($_SESSION['department']) : '';

$department = isset($_GET['department']) ? trim($_GET['department']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

$where = " WHERE 1=1 ";
$params = array();

if ($login_role != 'admin') {
    $where .= " AND department = ? ";
    $params[] = $login_department;
} else {
    if ($department != "") {
        $where .= " AND department = ? ";
        $params[] = $department;
    }
}

if ($status != "") {
    $where .= " AND status = ? ";
    $params[] = $status;
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
        FROM dbo.it_projects
        $where
        ORDER BY id DESC";

$query = sqlsrv_query($conn, $sql, $params);

if ($query === false) {
    die(print_r(sqlsrv_errors(), true));
}

/* =========================
   LOOKUP DEPARTMENT
========================= */
if ($login_role == 'admin') {
    $sql_dept = "SELECT DEP_NAME FROM DEPT ORDER BY DEP_NAME ASC";
    $q_dept = sqlsrv_query($conn, $sql_dept);
} else {
    $sql_dept = "SELECT DEP_NAME FROM DEPT WHERE DEP_NAME = ? ORDER BY DEP_NAME ASC";
    $q_dept = sqlsrv_query($conn, $sql_dept, array($login_department));
}

if ($q_dept === false) {
    die(print_r(sqlsrv_errors(), true));
}

$statuses = array(
    "Request",
    "Planning",
    "Development",
    "Testing",
    "Revision",
    "Deployment",
    "Selesai",
    "Pending",
    "On Progress",
    "Finish",
    "Done",
    "Completed"
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Project</title>
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
        <h3>Laporan Project</h3>

        <div>
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <button onclick="window.print()" class="btn btn-primary">Print</button>
        </div>
    </div>

    <form method="GET" class="card p-3 mb-3 no-print">
        <div class="row">

            <div class="col-md-5">
                <label>Department</label>

                <?php if ($login_role == 'admin') { ?>

                    <select name="department" class="form-select">
                        <option value="">Semua Department</option>

                        <?php
                        while ($dept = sqlsrv_fetch_array($q_dept, SQLSRV_FETCH_ASSOC)) {
                            $dep_name = rtrim($dept['DEP_NAME']);
                            $selected = ($department == $dep_name) ? "selected" : "";
                        ?>
                            <option value="<?php echo htmlspecialchars($dep_name); ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($dep_name); ?>
                            </option>
                        <?php } ?>
                    </select>

                <?php } else { ?>

                    <input type="text"
                           class="form-control"
                           value="<?php echo htmlspecialchars($login_department); ?>"
                           readonly>

                <?php } ?>
            </div>

            <div class="col-md-5">
                <label>Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>

                    <?php foreach ($statuses as $st) { ?>
                        <option value="<?php echo htmlspecialchars($st); ?>"
                            <?php echo ($status == $st) ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($st); ?>
                        </option>
                    <?php } ?>
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

        <?php if ($login_role != 'admin') { ?>
            <p class="text-center">
                Department: <strong><?php echo htmlspecialchars($login_department); ?></strong>
            </p>
        <?php } ?>

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Software</th>
                    <th>Department</th>
                    <th>PIC</th>
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
                        <td><?php echo htmlspecialchars($row['nama_software']); ?></td>
                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                        <td><?php echo htmlspecialchars($row['pic_it']); ?></td>

                        <td>
                            <?php
                            if ($row['deadline']) {
                                echo $row['deadline']->format('Y-m-d');
                            } else {
                                echo "-";
                            }
                            ?>
                        </td>

                        <td>
                            Rp <?php echo number_format($row['budget'], 0, ',', '.'); ?>
                        </td>

                        <td><?php echo htmlspecialchars($row['progress']); ?>%</td>
                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                    </tr>
                <?php } ?>

                <?php if ($no == 1) { ?>
                    <tr>
                        <td colspan="8" class="text-center">Data tidak ditemukan</td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

    </div>

</div>

</body>
</html>
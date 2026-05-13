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

/* =========================
   LIST DEPARTMENT
========================= */
if ($login_role == 'admin') {
    $sqlDept = "SELECT DISTINCT department
                FROM dbo.it_projects
                WHERE department IS NOT NULL
                AND department <> ''
                ORDER BY department ASC";

    $qDept = sqlsrv_query($conn, $sqlDept);
} else {
    $sqlDept = "SELECT DISTINCT department
                FROM dbo.it_projects
                WHERE department = ?
                ORDER BY department ASC";

    $qDept = sqlsrv_query($conn, $sqlDept, array($login_department));
}

if ($qDept === false) {
    die(print_r(sqlsrv_errors(), true));
}

/* =========================
   QUERY LAPORAN BUDGET
========================= */
$params = array();

$sql = "SELECT
            p.id,
            p.nama_software,
            p.department,
            p.budget,
            p.progress,
            p.status,
            ISNULL(SUM(c.nominal),0) AS total_biaya
        FROM dbo.it_projects p
        LEFT JOIN dbo.it_project_costs c
            ON p.id = c.project_id
        WHERE 1=1";

if ($login_role != 'admin') {
    $sql .= " AND p.department = ?";
    $params[] = $login_department;
} else {
    if ($department != '') {
        $sql .= " AND p.department = ?";
        $params[] = $department;
    }
}

$sql .= " GROUP BY
            p.id,
            p.nama_software,
            p.department,
            p.budget,
            p.progress,
            p.status
          ORDER BY p.department ASC,
                   p.nama_software ASC";

$query = sqlsrv_query($conn, $sql, $params);

if ($query === false) {
    die(print_r(sqlsrv_errors(), true));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <title>Laporan Budget Project</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          rel="stylesheet">

<style>
@page {
    size: A4 landscape;
    margin: 10mm;
}

body {
    background: #f5f5f5;
    font-size: 13px;
}

.card {
    border-radius: 8px;
}

@media print {
    .no-print {
        display: none !important;
    }

    body {
        background: white !important;
        font-size: 11px;
    }

    .container-fluid {
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .table {
        font-size: 10px;
    }

    .table th,
    .table td {
        padding: 4px !important;
    }

    .card {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>
</head>

<body>

<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between mb-3 no-print">

        <h3>Laporan Penggunaan Budget Project</h3>

        <div>
            <a href="dashboard.php" class="btn btn-secondary">
                Dashboard
            </a>

            <button onclick="window.print()" class="btn btn-primary">
                Cetak
            </button>
        </div>

    </div>

    <div class="card p-3 mb-4 no-print">

        <form method="GET">

            <div class="row">

                <div class="col-md-4">

                    <label>Filter Department</label>

                    <?php if ($login_role == 'admin') { ?>

                        <select name="department" class="form-control">

                            <option value="">
                                Semua Department
                            </option>

                            <?php
                            while ($d = sqlsrv_fetch_array($qDept, SQLSRV_FETCH_ASSOC)) {
                                $selected = '';

                                if ($department == $d['department']) {
                                    $selected = 'selected';
                                }
                            ?>

                                <option value="<?php echo htmlspecialchars($d['department']); ?>"
                                        <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($d['department']); ?>
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

                <div class="col-md-2">

                    <label>&nbsp;</label><br>

                    <button type="submit" class="btn btn-success">
                        Filter
                    </button>

                </div>

            </div>

        </form>

    </div>

    <div class="card p-4">

        <h4 class="mb-3">
            Data Budget Project
        </h4>

        <?php if ($login_role != 'admin') { ?>
            <p>
                Department:
                <strong><?php echo htmlspecialchars($login_department); ?></strong>
            </p>
        <?php } ?>

        <table class="table table-bordered table-striped">

            <thead>
                <tr class="table-dark">
                    <th>No</th>
                    <th>Department</th>
                    <th>Project</th>
                    <th>Budget</th>
                    <th>Total Biaya Actual</th>
                    <th>Sisa Budget</th>
                    <th>Progress</th>
                    <th>Status</th>
                    <th>% Budget</th>
                </tr>
            </thead>

            <tbody>

            <?php
            $no = 1;

            $grand_budget = 0;
            $grand_biaya = 0;
            $grand_sisa = 0;

            while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {

                $budget = isset($row['budget']) ? $row['budget'] : 0;
                $biaya = isset($row['total_biaya']) ? $row['total_biaya'] : 0;
                $sisa = $budget - $biaya;

                $persen = 0;

                if ($budget > 0) {
                    $persen = ($biaya / $budget) * 100;
                }

                $grand_budget += $budget;
                $grand_biaya += $biaya;
                $grand_sisa += $sisa;
            ?>

                <tr>
                    <td><?php echo $no++; ?></td>

                    <td><?php echo htmlspecialchars($row['department']); ?></td>

                    <td><?php echo htmlspecialchars($row['nama_software']); ?></td>

                    <td>
                        Rp <?php echo number_format($budget, 0, ',', '.'); ?>
                    </td>

                    <td>
                        Rp <?php echo number_format($biaya, 0, ',', '.'); ?>
                    </td>

                    <td>
                        <strong>
                            Rp <?php echo number_format($sisa, 0, ',', '.'); ?>
                        </strong>

                        <?php if ($sisa < 0) { ?>
                            <span class="badge bg-danger">
                                Minus
                            </span>
                        <?php } ?>
                    </td>

                    <td><?php echo htmlspecialchars($row['progress']); ?>%</td>

                    <td><?php echo htmlspecialchars($row['status']); ?></td>

                    <td>
                        <div class="progress">
                            <div class="progress-bar bg-danger"
                                 style="width: <?php echo min($persen, 100); ?>%">
                                <?php echo round($persen, 1); ?>%
                            </div>
                        </div>
                    </td>
                </tr>

            <?php } ?>

            <?php if ($no == 1) { ?>
                <tr>
                    <td colspan="9" class="text-center">
                        Data tidak ditemukan
                    </td>
                </tr>
            <?php } ?>

            </tbody>

            <tfoot>
                <tr class="table-secondary">
                    <th colspan="3">
                        TOTAL
                    </th>

                    <th>
                        Rp <?php echo number_format($grand_budget, 0, ',', '.'); ?>
                    </th>

                    <th>
                        Rp <?php echo number_format($grand_biaya, 0, ',', '.'); ?>
                    </th>

                    <th>
                        Rp <?php echo number_format($grand_sisa, 0, ',', '.'); ?>
                    </th>

                    <th colspan="3"></th>
                </tr>
            </tfoot>

        </table>

    </div>

</div>

</body>
</html>
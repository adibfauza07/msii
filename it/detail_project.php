<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "auth.php";
require_once "../config/database_p2.php";

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("Project tidak valid.");
}

if (function_exists('validateProjectAccess')) {
    validateProjectAccess($conn, $id);
}

/* =========================
   DATA PROJECT
========================= */
$sql = "SELECT *
        FROM dbo.it_projects
        WHERE id = ?";

$q = sqlsrv_query($conn, $sql, array($id));

if ($q === false) {
    die(print_r(sqlsrv_errors(), true));
}

$project = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);

if (!$project) {
    die("Project tidak ditemukan.");
}

/* =========================
   RIWAYAT PROGRESS
========================= */
$sql_progress = "SELECT *
                 FROM dbo.it_project_progress
                 WHERE project_id = ?
                 ORDER BY id DESC";

$q_progress = sqlsrv_query($conn, $sql_progress, array($id));

if ($q_progress === false) {
    die(print_r(sqlsrv_errors(), true));
}

/* =========================
   AVG PROGRESS
========================= */
$sql_avg_progress = "SELECT ISNULL(AVG(CAST(progress AS FLOAT)),0) AS avg_progress
                     FROM dbo.it_project_progress
                     WHERE project_id = ?";

$q_avg_progress = sqlsrv_query($conn, $sql_avg_progress, array($id));

if ($q_avg_progress === false) {
    die(print_r(sqlsrv_errors(), true));
}

$row_avg_progress = sqlsrv_fetch_array($q_avg_progress, SQLSRV_FETCH_ASSOC);

$avg_progress = isset($row_avg_progress['avg_progress'])
    ? round($row_avg_progress['avg_progress'], 0)
    : 0;

if ($avg_progress < 0) {
    $avg_progress = 0;
}

if ($avg_progress > 100) {
    $avg_progress = 100;
}

$progress_color = "bg-danger";

if ($avg_progress >= 80) {
    $progress_color = "bg-success";
} elseif ($avg_progress >= 40) {
    $progress_color = "bg-warning";
}

/* =========================
   TOTAL BIAYA PROGRESS / BUDGET PROJECT
========================= */
$sql_total_biaya_progress = "SELECT ISNULL(SUM(biaya),0) AS total
                             FROM dbo.it_project_progress
                             WHERE project_id = ?";

$q_total_biaya_progress = sqlsrv_query($conn, $sql_total_biaya_progress, array($id));

if ($q_total_biaya_progress === false) {
    die(print_r(sqlsrv_errors(), true));
}

$total_biaya_progress = sqlsrv_fetch_array($q_total_biaya_progress, SQLSRV_FETCH_ASSOC);

$budget_project = isset($total_biaya_progress['total'])
    ? $total_biaya_progress['total']
    : 0;

/* =========================
   RIWAYAT BIAYA ACTUAL
========================= */
$sql_biaya = "SELECT *
              FROM dbo.it_project_costs
              WHERE project_id = ?
              ORDER BY id DESC";

$q_biaya = sqlsrv_query($conn, $sql_biaya, array($id));

if ($q_biaya === false) {
    die(print_r(sqlsrv_errors(), true));
}

/* =========================
   TOTAL BIAYA ACTUAL
========================= */
$sql_total_biaya = "SELECT ISNULL(SUM(nominal),0) AS total
                    FROM dbo.it_project_costs
                    WHERE project_id = ?";

$q_total_biaya = sqlsrv_query($conn, $sql_total_biaya, array($id));

if ($q_total_biaya === false) {
    die(print_r(sqlsrv_errors(), true));
}

$total_biaya = sqlsrv_fetch_array($q_total_biaya, SQLSRV_FETCH_ASSOC);

$total_biaya_actual = isset($total_biaya['total'])
    ? $total_biaya['total']
    : 0;

$sisa_budget = $budget_project - $total_biaya_actual;

$persen_biaya = 0;

if ($budget_project > 0) {
    $persen_biaya = ($total_biaya_actual / $budget_project) * 100;
}

if ($persen_biaya < 0) {
    $persen_biaya = 0;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">

<title>
    <?php echo htmlspecialchars($project['nama_software']); ?> - Detail Project
</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
      rel="stylesheet">

<style>
body {
    background: #f5f5f5;
    font-size: 13px;
}

.card {
    border-radius: 10px;
}

.table th {
    width: 180px;
}

@media print {
    .btn {
        display: none !important;
    }
}
</style>
</head>

<body>

<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between mb-3">

        <h3>Detail Project</h3>

        <div>
            <a href="project.php" class="btn btn-secondary">Kembali</a>

            <button onclick="window.print()" class="btn btn-primary">Cetak</button>

            <a href="detail_project_pdf.php?id=<?php echo $id; ?>"
               target="_blank"
               class="btn btn-danger">
                PDF
            </a>
        </div>

    </div>

    <div class="card p-4 mb-4">

        <h3><?php echo htmlspecialchars($project['nama_software']); ?></h3>

        <p><?php echo htmlspecialchars($project['deskripsi']); ?></p>

        <table class="table table-bordered">

            <tr>
                <th>Department</th>
                <td><?php echo htmlspecialchars($project['department']); ?></td>
            </tr>

            <tr>
                <th>PIC Department</th>
                <td><?php echo htmlspecialchars($project['pic_department']); ?></td>
            </tr>

            <tr>
                <th>PIC IT</th>
                <td><?php echo htmlspecialchars($project['pic_it']); ?></td>
            </tr>

            <tr>
                <th>Deadline</th>
                <td>
                    <?php
                    if ($project['deadline']) {
                        echo $project['deadline']->format('Y-m-d');
                    } else {
                        echo "-";
                    }
                    ?>
                </td>
            </tr>

            <tr>
                <th>Budget Project</th>
                <td>
                    Rp <?php echo number_format($budget_project, 0, ',', '.'); ?>
                </td>
            </tr>

            <tr>
                <th>Total Biaya Actual</th>
                <td>
                    Rp <?php echo number_format($total_biaya_actual, 0, ',', '.'); ?>
                </td>
            </tr>

            <tr>
                <th>Sisa Budget</th>
                <td>
                    <strong>
                        Rp <?php echo number_format($sisa_budget, 0, ',', '.'); ?>
                    </strong>

                    <?php if ($sisa_budget < 0) { ?>
                        <span class="badge bg-danger">Minus</span>
                    <?php } ?>
                </td>
            </tr>

            <tr>
                <th>Progress AVG</th>
                <td>
                    <div class="progress" style="height: 24px;">
                        <div class="progress-bar <?php echo $progress_color; ?>"
                             role="progressbar"
                             style="width: <?php echo $avg_progress; ?>%;"
                             aria-valuenow="<?php echo $avg_progress; ?>"
                             aria-valuemin="0"
                             aria-valuemax="100">
                            <?php echo $avg_progress; ?>%
                        </div>
                    </div>
                </td>
            </tr>

            <tr>
                <th>Status</th>
                <td><?php echo htmlspecialchars($project['status']); ?></td>
            </tr>

        </table>

        <div class="mt-3">
            <strong>Penggunaan Budget Actual</strong>

            <div class="progress mt-2">
                <div class="progress-bar bg-danger"
                     style="width: <?php echo min($persen_biaya, 100); ?>%">
                    <?php echo round($persen_biaya, 1); ?>%
                </div>
            </div>
        </div>

        <div class="mt-4">
            <a href="progress.php?project_id=<?php echo $id; ?>"
               class="btn btn-success">
                Kelola Progress
            </a>

            <a href="biaya.php?project_id=<?php echo $id; ?>"
               class="btn btn-warning">
                Kelola Biaya Actual
            </a>
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
                <th>Qty</th>
                <th>Amount</th>
                <th>Biaya Progress</th>
                <th>Status</th>
                <th>Foto</th>
            </tr>

            <?php
            while ($p = sqlsrv_fetch_array($q_progress, SQLSRV_FETCH_ASSOC)) {
            ?>

            <tr>
                <td>
                    <?php
                    if ($p['tanggal']) {
                        echo $p['tanggal']->format('Y-m-d');
                    } else {
                        echo "-";
                    }
                    ?>
                </td>

                <td>
                    <?php
                    if ($p['jam_mulai']) {
                        echo $p['jam_mulai']->format('H:i');
                    }

                    echo " - ";

                    if ($p['jam_selesai']) {
                        echo $p['jam_selesai']->format('H:i');
                    }
                    ?>
                </td>

                <td><?php echo htmlspecialchars($p['total_jam']); ?></td>

                <td><?php echo htmlspecialchars($p['catatan']); ?></td>

                <td><?php echo htmlspecialchars($p['progress']); ?>%</td>

                <td>
                    <?php echo isset($p['qty']) ? number_format($p['qty'], 2) : '0.00'; ?>
                </td>

                <td>
                    Rp <?php
                    echo isset($p['amount'])
                        ? number_format($p['amount'], 0, ',', '.')
                        : '0';
                    ?>
                </td>

                <td>
                    Rp <?php
                    echo isset($p['biaya'])
                        ? number_format($p['biaya'], 0, ',', '.')
                        : '0';
                    ?>
                </td>

                <td><?php echo htmlspecialchars($p['status']); ?></td>

                <td>
                    <?php if (!empty($p['foto'])) { ?>
                        <a href="uploads/<?php echo htmlspecialchars($p['foto']); ?>"
                           target="_blank">
                            Lihat
                        </a>
                    <?php } else { ?>
                        -
                    <?php } ?>
                </td>
            </tr>

            <?php } ?>

        </table>

        <div class="text-end mt-3">
            <h5>
                Total Biaya Progress :
                <strong>
                    Rp <?php echo number_format($budget_project, 0, ',', '.'); ?>
                </strong>
            </h5>
        </div>

    </div>

    <div class="card p-4 mb-4">

        <h4>Riwayat Biaya Actual</h4>

        <table class="table table-bordered table-striped">

            <tr>
                <th>Tanggal</th>
                <th>Kategori</th>
                <th>Keterangan</th>
                <th>Nominal</th>
            </tr>

            <?php
            while ($b = sqlsrv_fetch_array($q_biaya, SQLSRV_FETCH_ASSOC)) {
            ?>

            <tr>
                <td>
                    <?php
                    if ($b['tanggal']) {
                        echo $b['tanggal']->format('Y-m-d');
                    } else {
                        echo "-";
                    }
                    ?>
                </td>

                <td><?php echo htmlspecialchars($b['kategori']); ?></td>

                <td><?php echo htmlspecialchars($b['keterangan']); ?></td>

                <td>
                    Rp <?php echo number_format($b['nominal'], 0, ',', '.'); ?>
                </td>
            </tr>

            <?php } ?>

        </table>

        <div class="text-end mt-3">
            <h5>
                Total Biaya Actual :
                <strong>
                    Rp <?php echo number_format($total_biaya_actual, 0, ',', '.'); ?>
                </strong>
            </h5>
        </div>

    </div>

</div>

</body>
</html>
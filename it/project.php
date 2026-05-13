<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "auth.php";
require_once "../config/database_p2.php";

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

/* =========================
   SESSION LOGIN
========================= */
$login_role = isset($_SESSION['role'])
    ? $_SESSION['role']
    : 'user';

$login_department = isset($_SESSION['department'])
    ? trim($_SESSION['department'])
    : '';

/* =========================
   FILTER GET
========================= */
$department = isset($_GET['department'])
    ? trim($_GET['department'])
    : '';

$keyword = isset($_GET['keyword'])
    ? trim($_GET['keyword'])
    : '';

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
   QUERY PROJECT
   Budget = SUM biaya progress
========================= */
$sql = "SELECT 
            p.*,
            ISNULL(b.total_biaya_progress,0) AS budget_progress
        FROM dbo.it_projects p
        LEFT JOIN (
            SELECT 
                project_id,
                SUM(biaya) AS total_biaya_progress
            FROM dbo.it_project_progress
            GROUP BY project_id
        ) b ON p.id = b.project_id
        WHERE 1=1";

$params = array();

/* USER BIASA HANYA DEPARTMENT SENDIRI */
if ($login_role != 'admin') {
    $sql .= " AND p.department = ?";
    $params[] = $login_department;
}

/* ADMIN BOLEH FILTER DEPARTMENT */
if ($login_role == 'admin' && $department != '') {
    $sql .= " AND p.department = ?";
    $params[] = $department;
}

/* SEARCH KEYWORD */
if ($keyword != '') {
    $sql .= " AND p.nama_software LIKE ?";
    $params[] = '%' . $keyword . '%';
}

$sql .= " ORDER BY p.id DESC";

$query = sqlsrv_query(
    $conn,
    $sql,
    $params,
    array("Scrollable" => SQLSRV_CURSOR_KEYSET)
);

if ($query === false) {
    die(print_r(sqlsrv_errors(), true));
}

$total_data = sqlsrv_num_rows($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data Project IT</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between mb-3">

        <h3>
            Data Project

            <?php if ($login_role != 'admin') { ?>
                -
                <span style="color:#2563eb;">
                    <?php echo htmlspecialchars($login_department); ?>
                </span>
            <?php } ?>
        </h3>

        <div>
            <a href="dashboard.php" class="btn btn-secondary">
                Dashboard
            </a>

            <a href="tambah_project.php" class="btn btn-primary">
                Tambah Project
            </a>
        </div>

    </div>

    <div class="card p-3 mb-3">
        <form method="GET">

            <div class="row">

                <div class="col-md-4">
                    <label>Nama Project</label>

                    <input type="text"
                           name="keyword"
                           class="form-control"
                           placeholder="Cari nama project..."
                           value="<?php echo htmlspecialchars($keyword); ?>">
                </div>

                <div class="col-md-4">
                    <label>Department</label>

                    <?php if ($login_role == 'admin') { ?>

                        <select name="department" class="form-control">
                            <option value="">Semua Department</option>

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

                <div class="col-md-4">
                    <label>&nbsp;</label><br>

                    <button type="submit" class="btn btn-primary">
                        Filter
                    </button>

                    <a href="project.php" class="btn btn-secondary">
                        Reset
                    </a>
                </div>

            </div>

        </form>
    </div>

    <div class="mb-2">
        <strong>Total Data:</strong>
        <?php echo $total_data; ?>
    </div>

    <table class="table table-bordered table-striped bg-white">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Project</th>
                <th>Department</th>
                <th>PIC Dept</th>
                <th>PIC Khusus</th>
                <th>Deadline</th>
                <th>Budget</th>
                <th>Progress</th>
                <th>Status</th>
                <th width="320">Aksi</th>
            </tr>
        </thead>

        <tbody>
        <?php
        $no = 1;

        while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
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
                    echo isset($row['pic_department'])
                        ? htmlspecialchars($row['pic_department'])
                        : '-';
                    ?>
                </td>

                <td>
                    <?php
                    echo isset($row['pic_it'])
                        ? htmlspecialchars($row['pic_it'])
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
                    echo isset($row['budget_progress'])
                        ? number_format($row['budget_progress'], 0, ',', '.')
                        : '0';
                    ?>
                </td>

                <td>
                    <?php
                    echo isset($row['progress'])
                        ? $row['progress'] . '%'
                        : '0%';
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
                    <a href="detail_project.php?id=<?php echo $row['id']; ?>"
                       class="btn btn-sm btn-info">
                        Detail
                    </a>

                    <a href="progress.php?project_id=<?php echo $row['id']; ?>"
                       class="btn btn-sm btn-primary">
                        Progress
                    </a>

                    <a href="biaya.php?project_id=<?php echo $row['id']; ?>"
                       class="btn btn-sm btn-success">
                        Biaya
                    </a>

                    <a href="edit_project.php?id=<?php echo $row['id']; ?>"
                       class="btn btn-sm btn-warning">
                        Edit
                    </a>

                    <?php if ($login_role == 'admin') { ?>
                        <a href="hapus_project.php?id=<?php echo $row['id']; ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Yakin hapus project ini?')">
                            Hapus
                        </a>
                    <?php } ?>
                </td>
            </tr>

        <?php } ?>

        <?php if ($total_data == 0) { ?>
            <tr>
                <td colspan="10" class="text-center">
                    Data tidak ditemukan
                </td>
            </tr>
        <?php } ?>

        </tbody>
    </table>

</div>

</body>
</html>
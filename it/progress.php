<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "auth.php";
require_once "../config/database_p2.php";

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($project_id <= 0) {
    die("Project belum dipilih.");
}

if (function_exists('validateProjectAccess')) {
    validateProjectAccess($conn, $project_id);
}

$statuses = array(
    "Planning",
    "On Progress",
    "Pending",
    "Waiting",
    "Review",
    "Approval",
    "Hold",
    "Open",
    "Development",
    "Testing",
    "Revision",
    "Deployment",
    "Monitoring",
    "Maintenance",
    "Done",
    "Completed",
    "Finish",
    "Closed",
    "Cancelled"
);

/* =========================
   DATA PROJECT
========================= */
$sql_project = "SELECT *
                FROM dbo.it_projects
                WHERE id = ?";

$q_project = sqlsrv_query($conn, $sql_project, array($project_id));

if ($q_project === false) {
    die(print_r(sqlsrv_errors(), true));
}

$project = sqlsrv_fetch_array($q_project, SQLSRV_FETCH_ASSOC);

if (!$project) {
    die("Project tidak ditemukan.");
}

/* =========================
   UPDATE STATUS PROJECT
========================= */
function updateProjectStatus($conn, $project_id)
{
    $sql = "SELECT TOP 1 progress, status
            FROM dbo.it_project_progress
            WHERE project_id = ?
            ORDER BY tanggal DESC, id DESC";

    $q = sqlsrv_query($conn, $sql, array($project_id));

    if ($q === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $last = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);

    if ($last) {
        $progress = $last['progress'];
        $status   = $last['status'];
    } else {
        $progress = 0;
        $status   = "Planning";
    }

    $sql_update = "UPDATE dbo.it_projects
                   SET progress = ?, status = ?
                   WHERE id = ?";

    $q_update = sqlsrv_query($conn, $sql_update, array(
        $progress,
        $status,
        $project_id
    ));

    if ($q_update === false) {
        die(print_r(sqlsrv_errors(), true));
    }
}

/* =========================
   SIMPAN / UPDATE
========================= */
if (isset($_POST['simpan'])) {

    $id = isset($_POST['id']) ? $_POST['id'] : '';

    $tanggal     = $_POST['tanggal'];
    $jam_mulai   = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    $total_jam   = $_POST['total_jam'];
    $catatan     = $_POST['catatan'];
    $progress    = $_POST['progress'];
    $status      = $_POST['status'];

    $qty    = isset($_POST['qty']) ? $_POST['qty'] : 0;
    $amount = isset($_POST['amount']) ? $_POST['amount'] : 0;

    if ($jam_mulai == "") {
        $jam_mulai = null;
    }

    if ($jam_selesai == "") {
        $jam_selesai = null;
    }

    if ($total_jam == "" || !is_numeric($total_jam)) {
        $total_jam = 0;
    }

    if ($progress == "" || !is_numeric($progress)) {
        $progress = 0;
    }

    if ($qty == "" || !is_numeric($qty)) {
        $qty = 0;
    }

    if ($amount == "" || !is_numeric($amount)) {
        $amount = 0;
    }

    $total_jam = floatval($total_jam);
    $progress  = intval($progress);
    $qty       = floatval($qty);
    $amount    = floatval($amount);
    $biaya     = $qty * $amount;

    if ($progress < 0) {
        $progress = 0;
    }

    if ($progress > 100) {
        $progress = 100;
    }

    $foto_name = isset($_POST['foto_lama']) ? $_POST['foto_lama'] : "";

    if (!empty($_FILES['foto']['name'])) {

        if (!is_dir("uploads")) {
            mkdir("uploads", 0777, true);
        }

        $foto_name = time() . "_" . basename($_FILES['foto']['name']);

        move_uploaded_file(
            $_FILES['foto']['tmp_name'],
            "uploads/" . $foto_name
        );
    }

    if ($id == "") {

        $sql = "INSERT INTO dbo.it_project_progress
                (
                    project_id,
                    tanggal,
                    jam_mulai,
                    jam_selesai,
                    total_jam,
                    catatan,
                    progress,
                    status,
                    foto,
                    qty,
                    amount,
                    biaya
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )";

        $params = array(
            $project_id,
            $tanggal,
            $jam_mulai,
            $jam_selesai,
            $total_jam,
            $catatan,
            $progress,
            $status,
            $foto_name,
            $qty,
            $amount,
            $biaya
        );

    } else {

        $sql = "UPDATE dbo.it_project_progress SET
                    tanggal = ?,
                    jam_mulai = ?,
                    jam_selesai = ?,
                    total_jam = ?,
                    catatan = ?,
                    progress = ?,
                    status = ?,
                    foto = ?,
                    qty = ?,
                    amount = ?,
                    biaya = ?
                WHERE id = ?
                AND project_id = ?";

        $params = array(
            $tanggal,
            $jam_mulai,
            $jam_selesai,
            $total_jam,
            $catatan,
            $progress,
            $status,
            $foto_name,
            $qty,
            $amount,
            $biaya,
            $id,
            $project_id
        );
    }

    $q = sqlsrv_query($conn, $sql, $params);

    if ($q === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    updateProjectStatus($conn, $project_id);

    header("Location: progress.php?project_id=" . $project_id);
    exit();
}

/* =========================
   HAPUS
========================= */
if (isset($_GET['hapus'])) {

    $id = intval($_GET['hapus']);

    $sql = "DELETE FROM dbo.it_project_progress
            WHERE id = ?
            AND project_id = ?";

    $q = sqlsrv_query($conn, $sql, array($id, $project_id));

    if ($q === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    updateProjectStatus($conn, $project_id);

    header("Location: progress.php?project_id=" . $project_id);
    exit();
}

/* =========================
   EDIT
========================= */
$edit = array();

if (isset($_GET['edit'])) {

    $id = intval($_GET['edit']);

    $sql = "SELECT *
            FROM dbo.it_project_progress
            WHERE id = ?
            AND project_id = ?";

    $q_edit = sqlsrv_query($conn, $sql, array($id, $project_id));

    if ($q_edit === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $edit = sqlsrv_fetch_array($q_edit, SQLSRV_FETCH_ASSOC);
}

/* =========================
   LIST PROGRESS
========================= */
$sql = "SELECT *
        FROM dbo.it_project_progress
        WHERE project_id = ?
        ORDER BY tanggal DESC, id DESC";

$query = sqlsrv_query($conn, $sql, array($project_id));

if ($query === false) {
    die(print_r(sqlsrv_errors(), true));
}

/* =========================
   TOTAL BIAYA PROGRESS
========================= */
$sql_total_biaya = "SELECT ISNULL(SUM(biaya), 0) AS total_biaya
                    FROM dbo.it_project_progress
                    WHERE project_id = ?";

$q_total_biaya = sqlsrv_query($conn, $sql_total_biaya, array($project_id));

if ($q_total_biaya === false) {
    die(print_r(sqlsrv_errors(), true));
}

$row_total_biaya = sqlsrv_fetch_array($q_total_biaya, SQLSRV_FETCH_ASSOC);

$total_biaya_progress = isset($row_total_biaya['total_biaya'])
    ? $row_total_biaya['total_biaya']
    : 0;

$current_status = isset($edit['status'])
    ? $edit['status']
    : $project['status'];

$edit_qty = isset($edit['qty']) ? $edit['qty'] : 0;
$edit_amount = isset($edit['amount']) ? $edit['amount'] : 0;
$edit_biaya = isset($edit['biaya']) ? $edit['biaya'] : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Progress Project</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between mb-3">

        <div>
            <h3>Progress Pengerjaan</h3>

            <strong>Project:</strong>
            <?php echo htmlspecialchars($project['nama_software']); ?>

            <br>

            <strong>Department:</strong>
            <?php echo htmlspecialchars($project['department']); ?>

            <br>

            <strong>Total Biaya Progress:</strong>
            Rp <?php echo number_format($total_biaya_progress, 0, ',', '.'); ?>
        </div>

        <div>
            <a href="project.php" class="btn btn-secondary">
                Kembali
            </a>
        </div>

    </div>

    <div class="card mb-4">

        <div class="card-header bg-primary text-white">
            <?php echo !empty($edit) ? "Edit Progress" : "Tambah Progress"; ?>
        </div>

        <div class="card-body">

            <form method="POST" enctype="multipart/form-data">

                <input type="hidden"
                       name="id"
                       value="<?php echo isset($edit['id']) ? $edit['id'] : ''; ?>">

                <input type="hidden"
                       name="foto_lama"
                       value="<?php echo isset($edit['foto']) ? $edit['foto'] : ''; ?>">

                <div class="row mb-3">

                    <div class="col-md-3">
                        <label>Tanggal</label>

                        <input type="date"
                               name="tanggal"
                               class="form-control"
                               required
                               value="<?php
                               if (isset($edit['tanggal']) && $edit['tanggal']) {
                                   echo $edit['tanggal']->format('Y-m-d');
                               } else {
                                   echo date('Y-m-d');
                               }
                               ?>">
                    </div>

                    <div class="col-md-3">
                        <label>Jam Mulai</label>

                        <input type="time"
                               name="jam_mulai"
                               class="form-control"
                               value="<?php
                               if (isset($edit['jam_mulai']) && $edit['jam_mulai']) {
                                   echo $edit['jam_mulai']->format('H:i');
                               }
                               ?>">
                    </div>

                    <div class="col-md-3">
                        <label>Jam Selesai</label>

                        <input type="time"
                               name="jam_selesai"
                               class="form-control"
                               value="<?php
                               if (isset($edit['jam_selesai']) && $edit['jam_selesai']) {
                                   echo $edit['jam_selesai']->format('H:i');
                               }
                               ?>">
                    </div>

                    <div class="col-md-3">
                        <label>Total Jam</label>

                        <input type="number"
                               step="0.5"
                               name="total_jam"
                               class="form-control"
                               value="<?php echo isset($edit['total_jam']) ? $edit['total_jam'] : '0'; ?>">
                    </div>

                </div>

                <div class="mb-3">
                    <label>Catatan</label>

                    <textarea name="catatan"
                              class="form-control"
                              rows="3"><?php echo isset($edit['catatan']) ? htmlspecialchars($edit['catatan']) : ''; ?></textarea>
                </div>

                <div class="row mb-3">

                    <div class="col-md-3">
                        <label>Progress %</label>

                        <input type="number"
                               name="progress"
                               class="form-control"
                               min="0"
                               max="100"
                               value="<?php
                               if (isset($edit['progress'])) {
                                   echo $edit['progress'];
                               } else {
                                   echo $project['progress'];
                               }
                               ?>">
                    </div>

                    <div class="col-md-3">
                        <label>Status</label>

                        <select name="status" class="form-control" required>
                            <?php foreach ($statuses as $st) { ?>
                                <option value="<?php echo htmlspecialchars($st); ?>"
                                    <?php echo ($current_status == $st) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($st); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>Jumlah</label>

                        <input type="number"
                               name="qty"
                               id="qty"
                               class="form-control"
                               min="0"
                               step="0.01"
                               value="<?php echo $edit_qty; ?>">
                    </div>

                    <div class="col-md-3">
                        <label>Harga</label>

                        <input type="number"
                               name="amount"
                               id="amount"
                               class="form-control"
                               min="0"
                               step="1000"
                               value="<?php echo $edit_amount; ?>">
                    </div>

                </div>

                <div class="row mb-3">

                    <div class="col-md-3">
                        <label>Total Harga</label>

                        <input type="number"
                               id="biaya_display"
                               class="form-control"
                               readonly
                               value="<?php echo $edit_biaya; ?>">
                    </div>

                    <div class="col-md-3">
                        <label>Foto / Screenshot</label>

                        <input type="file"
                               name="foto"
                               class="form-control">
                    </div>

                </div>

                <div class="mb-3">

                    <button type="submit"
                            name="simpan"
                            class="btn btn-primary">
                        <?php echo !empty($edit) ? "Update Progress" : "Tambah Progress"; ?>
                    </button>

                    <?php if (!empty($edit)) { ?>
                        <a href="progress.php?project_id=<?php echo $project_id; ?>"
                           class="btn btn-secondary">
                            Batal
                        </a>
                    <?php } ?>

                </div>

            </form>

        </div>
    </div>

    <div class="mb-3 text-end">

        <a href="progress.php?project_id=<?php echo $project_id; ?>"
           class="btn btn-success">
            + Tambah Progress
        </a>

    </div>

    <table class="table table-bordered table-striped bg-white">

        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Jam</th>
                <th>Total Jam</th>
                <th>Catatan</th>
                <th>Progress</th>
                <th>Status</th>
                <th>Jumlah</th>
                <th>Harga</th>
                <th>Total Harga</th>
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

                <td><?php echo htmlspecialchars($row['catatan']); ?></td>

                <td><?php echo $row['progress']; ?>%</td>

                <td><?php echo htmlspecialchars($row['status']); ?></td>

                <td>
                    <?php echo isset($row['qty']) ? $row['qty'] : 0; ?>
                </td>

                <td>
                    Rp <?php
                    echo isset($row['amount'])
                        ? number_format($row['amount'], 0, ',', '.')
                        : '0';
                    ?>
                </td>

                <td>
                    Rp <?php
                    echo isset($row['biaya'])
                        ? number_format($row['biaya'], 0, ',', '.')
                        : '0';
                    ?>
                </td>

                <td>
                    <?php if ($row['foto'] != "") { ?>
                        <a href="uploads/<?php echo htmlspecialchars($row['foto']); ?>"
                           target="_blank">
                            Lihat
                        </a>
                    <?php } else { ?>
                        -
                    <?php } ?>
                </td>

                <td>
                    <a href="progress.php?project_id=<?php echo $project_id; ?>&edit=<?php echo $row['id']; ?>"
                       class="btn btn-sm btn-warning">
                        Edit
                    </a>

                    <a href="progress.php?project_id=<?php echo $project_id; ?>&hapus=<?php echo $row['id']; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Hapus progress ini?')">
                        Hapus
                    </a>
                </td>
            </tr>

        <?php } ?>

        </tbody>

    </table>

</div>

<script>
function hitungBiaya() {
    var qty = parseFloat(document.getElementById('qty').value) || 0;
    var amount = parseFloat(document.getElementById('amount').value) || 0;
    var total = qty * amount;

    document.getElementById('biaya_display').value = total;
}

document.getElementById('qty').addEventListener('input', hitungBiaya);
document.getElementById('amount').addEventListener('input', hitungBiaya);

hitungBiaya();
</script>

</body>
</html>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "auth.php";
require_once "../config/database_p2.php";

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

/* =========================
   AMBIL PROJECT ID
   support project_id dan id
========================= */
$project_id = 0;

if (isset($_GET['project_id'])) {
    $project_id = intval($_GET['project_id']);
} elseif (isset($_GET['id'])) {
    $project_id = intval($_GET['id']);
}

if ($project_id <= 0) {
    die("ID Project tidak valid.");
}

/* =========================
   VALIDASI AKSES PROJECT
========================= */
if (function_exists('validateProjectAccess')) {
    validateProjectAccess($conn, $project_id);
}

/* =========================
   DATA PROJECT
========================= */
$sql_project = "SELECT *
                FROM dbo.it_projects
                WHERE id = ?";

$q_project = sqlsrv_query(
    $conn,
    $sql_project,
    array($project_id)
);

if ($q_project === false) {
    die(print_r(sqlsrv_errors(), true));
}

$project = sqlsrv_fetch_array($q_project, SQLSRV_FETCH_ASSOC);

if (!$project) {
    die("Project tidak ditemukan.");
}

/* =========================
   SIMPAN PROGRESS
========================= */
if (isset($_POST['simpan'])) {

    $tanggal     = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d');
    $jam_mulai   = isset($_POST['jam_mulai']) ? $_POST['jam_mulai'] : null;
    $jam_selesai = isset($_POST['jam_selesai']) ? $_POST['jam_selesai'] : null;
    $total_jam   = isset($_POST['total_jam']) ? $_POST['total_jam'] : 0;
    $catatan     = isset($_POST['catatan']) ? $_POST['catatan'] : '';
    $progress    = isset($_POST['progress']) ? $_POST['progress'] : 0;
    $status      = isset($_POST['status']) ? $_POST['status'] : 'Planning';
    $biaya       = isset($_POST['biaya']) ? $_POST['biaya'] : 0;

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

    if ($biaya == "" || !is_numeric($biaya)) {
        $biaya = 0;
    }

    $total_jam = floatval($total_jam);
    $progress  = intval($progress);
    $biaya     = floatval($biaya);

    if ($progress < 0) {
        $progress = 0;
    }

    if ($progress > 100) {
        $progress = 100;
    }

    $foto_name = "";

    if (!empty($_FILES['foto']['name'])) {

        if (!is_dir("uploads")) {
            mkdir("uploads", 0777, true);
        }

        $foto_name = time() . "_" . basename($_FILES['foto']['name']);

        $upload_path = "uploads/" . $foto_name;

        move_uploaded_file(
            $_FILES['foto']['tmp_name'],
            $upload_path
        );
    }

    /* =========================
       INSERT PROGRESS
    ========================= */
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
                biaya
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
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
        $biaya
    );

    $query = sqlsrv_query($conn, $sql, $params);

    if ($query === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    /* =========================
       UPDATE PROJECT
    ========================= */
    $sql_update = "UPDATE dbo.it_projects
                   SET progress = ?,
                       status = ?
                   WHERE id = ?";

    $params_update = array(
        $progress,
        $status,
        $project_id
    );

    $q_update = sqlsrv_query($conn, $sql_update, $params_update);

    if ($q_update === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    header("Location: progress.php?project_id=" . $project_id);
    exit();
}

/* =========================
   STATUS LIST
========================= */
$statuses = array(
    "Planning",
    "On Progress",
    "Development",
    "Testing",
    "Revision",
    "Deployment",
    "Review",
    "Pending",
    "Hold",
    "Done",
    "Completed",
    "Finish",
    "Cancelled"
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <title>Tambah Progress</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between mb-3">
        <div>
            <h3>Tambah Progress</h3>

            <p class="mb-0">
                <strong>Project:</strong>
                <?php echo htmlspecialchars($project['nama_software']); ?>
            </p>

            <p class="mb-0">
                <strong>Department:</strong>
                <?php echo htmlspecialchars($project['department']); ?>
            </p>
        </div>

        <a href="progress.php?project_id=<?php echo $project_id; ?>"
           class="btn btn-secondary">
            Kembali
        </a>
    </div>

    <form method="POST"
          enctype="multipart/form-data"
          class="card p-4 bg-white">

        <div class="mb-3">
            <label>Tanggal</label>

            <input type="date"
                   name="tanggal"
                   class="form-control"
                   required
                   value="<?php echo date('Y-m-d'); ?>">
        </div>

        <div class="row">

            <div class="col-md-4 mb-3">
                <label>Jam Mulai</label>

                <input type="time"
                       name="jam_mulai"
                       class="form-control">
            </div>

            <div class="col-md-4 mb-3">
                <label>Jam Selesai</label>

                <input type="time"
                       name="jam_selesai"
                       class="form-control">
            </div>

            <div class="col-md-4 mb-3">
                <label>Total Jam</label>

                <input type="number"
                       step="0.5"
                       name="total_jam"
                       class="form-control"
                       value="0">
            </div>

        </div>

        <div class="mb-3">
            <label>Catatan Pengerjaan</label>

            <textarea name="catatan"
                      class="form-control"
                      rows="4"></textarea>
        </div>

        <div class="row">

            <div class="col-md-4 mb-3">
                <label>Progress %</label>

                <input type="number"
                       name="progress"
                       class="form-control"
                       min="0"
                       max="100"
                       value="<?php
                       echo isset($project['progress'])
                            ? intval($project['progress'])
                            : 0;
                       ?>">
            </div>

            <div class="col-md-4 mb-3">
                <label>Status</label>

                <select name="status" class="form-select">

                    <?php foreach ($statuses as $st) { ?>

                        <option value="<?php echo htmlspecialchars($st); ?>"
                            <?php
                            echo (
                                isset($project['status']) &&
                                $project['status'] == $st
                            )
                            ? 'selected'
                            : '';
                            ?>>

                            <?php echo htmlspecialchars($st); ?>

                        </option>

                    <?php } ?>

                </select>
            </div>

            <div class="col-md-4 mb-3">
                <label>Biaya Progress</label>

                <input type="number"
                       name="biaya"
                       class="form-control"
                       min="0"
                       step="1000"
                       value="0">
            </div>

        </div>

        <div class="mb-3">
            <label>Upload Foto / Screenshot</label>

            <input type="file"
                   name="foto"
                   class="form-control">
        </div>

        <div>
            <button type="submit"
                    name="simpan"
                    class="btn btn-primary">
                Simpan Progress
            </button>

            <a href="progress.php?project_id=<?php echo $project_id; ?>"
               class="btn btn-secondary">
                Batal
            </a>
        </div>

    </form>

</div>

</body>
</html>
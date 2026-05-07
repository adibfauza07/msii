<?php

session_start();

if (!isset($_SESSION['db_user'])) {
    header("Location: login.php");
    exit();
}

include "../config/database_p1.php";

$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($project_id <= 0) {
    die("ID Project tidak valid.");
}

if (isset($_POST['simpan'])) {

    $tanggal = $_POST['tanggal'];

    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];

    if ($jam_mulai == "") {
        $jam_mulai = null;
    }

    if ($jam_selesai == "") {
        $jam_selesai = null;
    }

    $total_jam = $_POST['total_jam'];

    if ($total_jam == "" || !is_numeric($total_jam)) {
        $total_jam = 0;
    }

    $total_jam = floatval($total_jam);

    $catatan = $_POST['catatan'];

    $progress = $_POST['progress'];

    if ($progress == "" || !is_numeric($progress)) {
        $progress = 0;
    }

    $progress = intval($progress);

    $status = $_POST['status'];

    $foto_name = "";

    if (!empty($_FILES['foto']['name'])) {

        if (!is_dir("uploads")) {
            mkdir("uploads");
        }

        $foto_name = time() . "_" . basename($_FILES['foto']['name']);

        move_uploaded_file(
            $_FILES['foto']['tmp_name'],
            "uploads/" . $foto_name
        );
    }

    $sql = "INSERT INTO it_project_progress
            (project_id, tanggal, jam_mulai, jam_selesai, total_jam, catatan, progress, status, foto)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $params = array(
        array($project_id, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_INT, SQLSRV_SQLTYPE_INT),
        array($tanggal, SQLSRV_PARAM_IN),
        array($jam_mulai, SQLSRV_PARAM_IN),
        array($jam_selesai, SQLSRV_PARAM_IN),
        array($total_jam, SQLSRV_PARAM_IN),
        array($catatan, SQLSRV_PARAM_IN),
        array($progress, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_INT, SQLSRV_SQLTYPE_INT),
        array($status, SQLSRV_PARAM_IN),
        array($foto_name, SQLSRV_PARAM_IN)
    );

    $query = sqlsrv_query($conn, $sql, $params);

    if ($query) {

        $sql_update = "UPDATE it_projects 
                       SET progress = ?, status = ? 
                       WHERE id = ?";

        $params_update = array(
            array($progress, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_INT, SQLSRV_SQLTYPE_INT),
            array($status, SQLSRV_PARAM_IN),
            array($project_id, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_INT, SQLSRV_SQLTYPE_INT)
        );

        $q_update = sqlsrv_query($conn, $sql_update, $params_update);

        if ($q_update === false) {
            die(print_r(sqlsrv_errors(), true));
        }

        header("Location: detail_project.php?id=" . $project_id);
        exit();

    } else {

        die(print_r(sqlsrv_errors(), true));

    }
}

$sql_project = "SELECT * FROM it_projects WHERE id = ?";
$q_project = sqlsrv_query(
    $conn,
    $sql_project,
    array(
        array($project_id, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_INT, SQLSRV_SQLTYPE_INT)
    )
);

if ($q_project === false) {
    die(print_r(sqlsrv_errors(), true));
}

$project = sqlsrv_fetch_array($q_project, SQLSRV_FETCH_ASSOC);

if (!$project) {
    die("Project tidak ditemukan.");
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <title>Tambah Progress</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between mb-3">
        <div>
            <h3>Tambah Progress</h3>
            <p class="mb-0">
                <?php echo $project['nama_software']; ?>
            </p>
        </div>

        <a href="project.php" class="btn btn-secondary">
            Kembali
        </a>
    </div>

    <form method="POST" enctype="multipart/form-data" class="card p-4 bg-white">

        <div class="mb-3">
            <label>Tanggal</label>
            <input type="date" name="tanggal" class="form-control" required>
        </div>

        <div class="row">

            <div class="col-md-4 mb-3">
                <label>Jam Mulai</label>
                <input type="time" name="jam_mulai" class="form-control">
            </div>

            <div class="col-md-4 mb-3">
                <label>Jam Selesai</label>
                <input type="time" name="jam_selesai" class="form-control">
            </div>

            <div class="col-md-4 mb-3">
                <label>Total Jam</label>
                <input type="number" step="0.5" name="total_jam" class="form-control" value="0">
            </div>

        </div>

        <div class="mb-3">
            <label>Catatan Pengerjaan</label>
            <textarea name="catatan" class="form-control" rows="4"></textarea>
        </div>

        <div class="mb-3">
            <label>Progress %</label>
            <input type="number"
                   name="progress"
                   class="form-control"
                   min="0"
                   max="100"
                   value="<?php echo intval($project['progress']); ?>">
        </div>

        <div class="mb-3">
            <label>Status</label>
            <select name="status" class="form-select">

                <?php
                $statuses = array(
                    "Planning",
                    "Development",
                    "Testing",
                    "Revision",
                    "Deployment",
                    "Selesai",
                    "Pending"
                );

                foreach ($statuses as $st) {
                    $selected = ($project['status'] == $st) ? "selected" : "";
                    echo "<option value='" . $st . "' " . $selected . ">" . $st . "</option>";
                }
                ?>

            </select>
        </div>

        <div class="mb-3">
            <label>Upload Foto / Screenshot</label>
            <input type="file" name="foto" class="form-control">
        </div>

        <button type="submit" name="simpan" class="btn btn-primary">
            Simpan Progress
        </button>

    </form>

</div>

</body>
</html>
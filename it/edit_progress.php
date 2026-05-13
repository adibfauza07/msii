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
    die("ID Progress tidak valid.");
}

$sql = "SELECT * FROM it_project_progress WHERE id = ?";
$q = sqlsrv_query($conn, $sql, array($id));

if ($q === false) {
    die(print_r(sqlsrv_errors(), true));
}

$data = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);

if (!$data) {
    die("Data progress tidak ditemukan.");
}

$project_id = intval($data['project_id']);

if (isset($_POST['update'])) {

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

    $foto_name = $data['foto'];

    if (!empty($_FILES['foto']['name'])) {

        if (!is_dir("uploads")) {
            mkdir("uploads");
        }

        if ($foto_name != "" && file_exists("uploads/" . $foto_name)) {
            unlink("uploads/" . $foto_name);
        }

        $foto_name = time() . "_" . basename($_FILES['foto']['name']);

        move_uploaded_file(
            $_FILES['foto']['tmp_name'],
            "uploads/" . $foto_name
        );
    }

    $sql_update = "UPDATE it_project_progress SET
                    tanggal = ?,
                    jam_mulai = ?,
                    jam_selesai = ?,
                    total_jam = ?,
                    catatan = ?,
                    progress = ?,
                    status = ?,
                    foto = ?
                   WHERE id = ?";

    $params = array(
        $tanggal,
        $jam_mulai,
        $jam_selesai,
        $total_jam,
        $catatan,
        $progress,
        $status,
        $foto_name,
        $id
    );

    $query = sqlsrv_query($conn, $sql_update, $params);

    if ($query) {

        $sql_project_update = "UPDATE it_projects SET progress = ?, status = ? WHERE id = ?";
        sqlsrv_query($conn, $sql_project_update, array($progress, $status, $project_id));

        header("Location: detail_project.php?id=" . $project_id);
        exit();

    } else {
        die(print_r(sqlsrv_errors(), true));
    }
}

$tanggal = "";
$jam_mulai = "";
$jam_selesai = "";

if ($data['tanggal']) {
    $tanggal = $data['tanggal']->format('Y-m-d');
}

if ($data['jam_mulai']) {
    $jam_mulai = $data['jam_mulai']->format('H:i');
}

if ($data['jam_selesai']) {
    $jam_selesai = $data['jam_selesai']->format('H:i');
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Progress</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between mb-3">
        <h3>Edit Progress</h3>
        <a href="detail_project.php?id=<?php echo $project_id; ?>" class="btn btn-secondary">
            Kembali
        </a>
    </div>

    <form method="POST" enctype="multipart/form-data" class="card p-4 bg-white">

        <div class="mb-3">
            <label>Tanggal</label>
            <input type="date" name="tanggal" class="form-control" value="<?php echo $tanggal; ?>" required>
        </div>

        <div class="row">

            <div class="col-md-4 mb-3">
                <label>Jam Mulai</label>
                <input type="time" name="jam_mulai" class="form-control" value="<?php echo $jam_mulai; ?>">
            </div>

            <div class="col-md-4 mb-3">
                <label>Jam Selesai</label>
                <input type="time" name="jam_selesai" class="form-control" value="<?php echo $jam_selesai; ?>">
            </div>

            <div class="col-md-4 mb-3">
                <label>Total Jam</label>
                <input type="number" step="0.5" name="total_jam" class="form-control" value="<?php echo $data['total_jam']; ?>">
            </div>

        </div>

        <div class="mb-3">
            <label>Catatan Pengerjaan</label>
            <textarea name="catatan" class="form-control" rows="4"><?php echo $data['catatan']; ?></textarea>
        </div>

        <div class="mb-3">
            <label>Progress %</label>
            <input type="number" name="progress" class="form-control" min="0" max="100" value="<?php echo intval($data['progress']); ?>">
        </div>

        <div class="mb-3">
            <label>Status</label>
            <select name="status" class="form-select">
                <?php
                $statuses = array("Planning","Development","Testing","Revision","Deployment","Selesai","Pending");

                foreach ($statuses as $st) {
                    $selected = ($data['status'] == $st) ? "selected" : "";
                    echo "<option value='$st' $selected>$st</option>";
                }
                ?>
            </select>
        </div>

        <div class="mb-3">
            <label>Foto / Screenshot Saat Ini</label><br>

            <?php if ($data['foto'] != "") { ?>
                <a href="uploads/<?php echo $data['foto']; ?>" target="_blank">
                    Lihat Foto
                </a>
            <?php } else { ?>
                <span class="text-muted">Tidak ada foto</span>
            <?php } ?>
        </div>

        <div class="mb-3">
            <label>Ganti Foto / Screenshot</label>
            <input type="file" name="foto" class="form-control">
        </div>

        <button type="submit" name="update" class="btn btn-primary">
            Update Progress
        </button>

    </form>

</div>

</body>
</html>
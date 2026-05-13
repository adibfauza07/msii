<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "auth.php";
require_once "../config/database_p2.php";

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}
$serverName = "192.168.0.4";

if (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == "p2") {
    $serverName = "192.168.0.9";
}

$connectionOptions = array(
    "Database" => "msData",
    "Uid" => $_SESSION['db_user'],
    "PWD" => $_SESSION['db_pass'],
    "CharacterSet" => "UTF-8"
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

$id = intval($_GET['id']);

$sql_project = "SELECT * FROM it_projects WHERE id = ?";
$q_project = sqlsrv_query($conn, $sql_project, array($id));

if ($q_project === false) {
    die(print_r(sqlsrv_errors(), true));
}

$project = sqlsrv_fetch_array($q_project, SQLSRV_FETCH_ASSOC);

if (!$project) {
    echo "Project tidak ditemukan.";
    exit();
}

if (isset($_POST['update'])) {

    $nama_software = $_POST['nama_software'];
    $department = $_POST['department'];
    $pic_department = $_POST['pic_department'];
    $pic_it = $_POST['pic_it'];
    $deskripsi = $_POST['deskripsi'];
    $tanggal_mulai = $_POST['tanggal_mulai'];
    $deadline = $_POST['deadline'];
    $budget = $_POST['budget'];
    $progress = $_POST['progress'];
    $status = $_POST['status'];

    $sql_update = "UPDATE it_projects SET
                    nama_software = ?,
                    department = ?,
                    pic_department = ?,
                    pic_it = ?,
                    deskripsi = ?,
                    tanggal_mulai = ?,
                    deadline = ?,
                    budget = ?,
                    progress = ?,
                    status = ?
                   WHERE id = ?";

    $params = array(
        $nama_software,
        $department,
        $pic_department,
        $pic_it,
        $deskripsi,
        $tanggal_mulai,
        $deadline,
        $budget,
        $progress,
        $status,
        $id
    );

    $query = sqlsrv_query($conn, $sql_update, $params);

    if ($query) {
        header("Location: project.php");
        exit();
    } else {
        die(print_r(sqlsrv_errors(), true));
    }
}

$tanggal_mulai = "";
$deadline = "";

if ($project['tanggal_mulai']) {
    $tanggal_mulai = $project['tanggal_mulai']->format('Y-m-d');
}

if ($project['deadline']) {
    $deadline = $project['deadline']->format('Y-m-d');
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Project IT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between mb-3">
        <h3>Edit Project IT</h3>
        <a href="project.php" class="btn btn-secondary">Kembali</a>
    </div>

    <form method="POST" class="card p-4 bg-white">

        <div class="mb-3">
            <label>Nama Software</label>
            <input type="text" name="nama_software" class="form-control"
                   value="<?php echo $project['nama_software']; ?>" required>
        </div>

        <div class="mb-3">
            <label>Department</label>
           <select name="department" class="form-select" required>
    <option value="">-- Pilih Department --</option>

    <?php
    $sql_dept = "SELECT DEP_NAME 
                 FROM DEPT
                 ORDER BY DEP_NAME ASC";

    $q_dept = sqlsrv_query($conn, $sql_dept);

    while ($dept = sqlsrv_fetch_array($q_dept, SQLSRV_FETCH_ASSOC)) {
        $dep_name = rtrim($dept['DEP_NAME']);
        $selected = (trim($project['department']) == $dep_name) ? "selected" : "";

        echo "<option value='".$dep_name."' ".$selected.">".$dep_name."</option>";
    }
    ?>
</select>
        </div>

        <div class="mb-3">
            <label>PIC Department</label>
            <input type="text" name="pic_department" class="form-control"
                   value="<?php echo $project['pic_department']; ?>">
        </div>

        <div class="mb-3">
            <label>PIC IT</label>
            <input type="text" name="pic_it" class="form-control"
                   value="<?php echo $project['pic_it']; ?>">
        </div>

        <div class="mb-3">
            <label>Deskripsi Kebutuhan</label>
            <textarea name="deskripsi" class="form-control" rows="4"><?php echo $project['deskripsi']; ?></textarea>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label>Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" class="form-control"
                       value="<?php echo $tanggal_mulai; ?>">
            </div>

            <div class="col-md-6 mb-3">
                <label>Deadline</label>
                <input type="date" name="deadline" class="form-control"
                       value="<?php echo $deadline; ?>">
            </div>
        </div>

        <div class="mb-3">
            <label>Budget</label>
            <input type="number" name="budget" class="form-control"
                   value="<?php echo $project['budget']; ?>">
        </div>

        <div class="mb-3">
            <label>Progress %</label>
            <input type="number" name="progress" class="form-control" min="0" max="100"
                   value="<?php echo $project['progress']; ?>">
        </div>

        <div class="mb-3">
            <label>Status</label>
            <select name="status" class="form-select">
                <?php
                $statuses = array(
                    "Request", "Planning", "Development", "Testing",
                    "Revision", "Deployment", "Selesai", "Pending"
                );

                foreach ($statuses as $st) {
                    $selected = ($project['status'] == $st) ? "selected" : "";
                    echo "<option value='$st' $selected>$st</option>";
                }
                ?>
            </select>
        </div>

        <button type="submit" name="update" class="btn btn-primary">Update Project</button>
        <a href="project.php" class="btn btn-secondary">Batal</a>

    </form>

</div>

</body>
</html>
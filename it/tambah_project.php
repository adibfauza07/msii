<?php
session_start();
if (!isset($_SESSION['db_user'])) {
    header("Location: login.php");
    exit();
}

include "../config/database_p1.php";

if (isset($_POST['simpan'])) {

    $nama_software = $_POST['nama_software'];
    $department = $_POST['department'];
    $pic_department = $_POST['pic_department'];
    $pic_it = $_POST['pic_it'];
    $deskripsi = $_POST['deskripsi'];
    $tanggal_mulai = $_POST['tanggal_mulai'];
    $deadline = $_POST['deadline'];
    $budget = $_POST['budget'];
    $status = $_POST['status'];

    $sql = "INSERT INTO it_projects
            (nama_software, department, pic_department, pic_it, deskripsi, tanggal_mulai, deadline, budget, progress, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $params = array(
        $nama_software,
        $department,
        $pic_department,
        $pic_it,
        $deskripsi,
        $tanggal_mulai,
        $deadline,
        $budget,
        0,
        $status
    );

    $query = sqlsrv_query($conn, $sql, $params);

    if ($query) {
        header("Location: project.php");
        exit();
    } else {
        die(print_r(sqlsrv_errors(), true));
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Tambah Project IT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-4">
    <h3>Tambah Project IT</h3>

    <form method="POST" class="card p-4 bg-white">

        <div class="mb-3">
            <label>Nama Project</label>
            <input type="text" name="nama_software" class="form-control" required>
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
        echo "<option value='".$dep_name."'>".$dep_name."</option>";
    }
    ?>
</select>
        </div>

        <div class="mb-3">
            <label>PIC Department</label>
            <input type="text" name="pic_department" class="form-control">
        </div>

        <div class="mb-3">
            <label>PIC IT</label>
            <input type="text" name="pic_it" class="form-control">
        </div>

        <div class="mb-3">
            <label>Deskripsi Kebutuhan</label>
            <textarea name="deskripsi" class="form-control" rows="4"></textarea>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label>Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" class="form-control">
            </div>

            <div class="col-md-6 mb-3">
                <label>Deadline</label>
                <input type="date" name="deadline" class="form-control">
            </div>
        </div>

        <div class="mb-3">
            <label>Budget</label>
            <input type="number" name="budget" class="form-control" value="0">
        </div>

        <div class="mb-3">
            <label>Status</label>
            <select name="status" class="form-select">
                <option>Request</option>
                <option>Planning</option>
                <option>Development</option>
                <option>Testing</option>
                <option>Revision</option>
                <option>Deployment</option>
                <option>Selesai</option>
                <option>Pending</option>
            </select>
        </div>

        <button type="submit" name="simpan" class="btn btn-primary">Simpan</button>
        <a href="project.php" class="btn btn-secondary">Kembali</a>

    </form>
</div>

</body>
</html>
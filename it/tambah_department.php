<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "auth.php";
require_once "../config/database_p2.php";

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

if (isset($_POST['simpan'])) {

    $dep_code = strtoupper(trim($_POST['dep_code']));
    $dep_name = strtoupper(trim($_POST['dep_name']));
    $dep_level = intval($_POST['dep_level']);

    $sql = "INSERT INTO DEPT
            (DEP_CODE, DEP_NAME, DEP_LEVEL)
            VALUES (?, ?, ?)";

    $params = array(
        $dep_code,
        $dep_name,
        $dep_level
    );

    $query = sqlsrv_query($conn, $sql, $params);

    if ($query) {

        header("Location: department.php");
        exit();

    } else {

        die(print_r(sqlsrv_errors(), true));

    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <title>Tambah Department</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between mb-3">

        <h3>Tambah Department</h3>

        <a href="department.php" class="btn btn-secondary">
            Kembali
        </a>

    </div>

    <form method="POST" class="card p-4 bg-white">

        <div class="mb-3">

            <label>Kode Department</label>

            <input type="text"
                   name="dep_code"
                   maxlength="10"
                   class="form-control"
                   required>

        </div>

        <div class="mb-3">

            <label>Nama Department</label>

            <input type="text"
                   name="dep_name"
                   maxlength="25"
                   class="form-control"
                   required>

        </div>

        <div class="mb-3">

            <label>Level</label>

            <input type="number"
                   name="dep_level"
                   class="form-control"
                   value="1">

        </div>

        <button type="submit"
                name="simpan"
                class="btn btn-primary">

            Simpan

        </button>

    </form>

</div>

</body>
</html>
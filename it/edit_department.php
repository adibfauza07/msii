<?php
session_start();

if (!isset($_SESSION['db_user'])) {
    header("Location: login.php");
    exit();
}

include "../config/database_p1.php";

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("ID Department tidak valid.");
}

$sql = "SELECT * FROM DEPT WHERE DEP_ID = ?";
$query = sqlsrv_query($conn, $sql, array($id));

if ($query === false) {
    die(print_r(sqlsrv_errors(), true));
}

$data = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);

if (!$data) {
    die("Department tidak ditemukan.");
}

if (isset($_POST['update'])) {

    $dep_code = strtoupper(trim($_POST['dep_code']));
    $dep_name = strtoupper(trim($_POST['dep_name']));
    $dep_parent = trim($_POST['dep_parent']);
    $dep_level = trim($_POST['dep_level']);

    if ($dep_parent == "") {
        $dep_parent = null;
    } else {
        $dep_parent = intval($dep_parent);
    }

    if ($dep_level == "") {
        $dep_level = null;
    } else {
        $dep_level = intval($dep_level);
    }

    $sql_update = "UPDATE DEPT SET
                    DEP_CODE = ?,
                    DEP_NAME = ?,
                    DEP_PARENT = ?,
                    DEP_LEVEL = ?
                   WHERE DEP_ID = ?";

    $params = array(
        $dep_code,
        $dep_name,
        $dep_parent,
        $dep_level,
        $id
    );

    $update = sqlsrv_query($conn, $sql_update, $params);

    if ($update) {
        header("Location: department.php");
        exit();
    } else {
        die(print_r(sqlsrv_errors(), true));
    }
}

$dep_code = rtrim($data['DEP_CODE']);
$dep_name = rtrim($data['DEP_NAME']);
$dep_parent = $data['DEP_PARENT'];
$dep_level = $data['DEP_LEVEL'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Department</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between mb-3">
        <h3>Edit Department</h3>
        <a href="department.php" class="btn btn-secondary">Kembali</a>
    </div>

    <form method="POST" class="card p-4 bg-white">

        <div class="mb-3">
            <label>Kode Department</label>
            <input type="text"
                   name="dep_code"
                   maxlength="10"
                   class="form-control"
                   value="<?php echo $dep_code; ?>"
                   required>
        </div>

        <div class="mb-3">
            <label>Nama Department</label>
            <input type="text"
                   name="dep_name"
                   maxlength="25"
                   class="form-control"
                   value="<?php echo $dep_name; ?>"
                   required>
        </div>

        <div class="mb-3">
            <label>Parent Department ID</label>
            <input type="number"
                   name="dep_parent"
                   class="form-control"
                   value="<?php echo $dep_parent; ?>">
        </div>

        <div class="mb-3">
            <label>Level</label>
            <input type="number"
                   name="dep_level"
                   class="form-control"
                   value="<?php echo $dep_level; ?>">
        </div>

        <button type="submit" name="update" class="btn btn-primary">
            Update
        </button>

    </form>

</div>

</body>
</html>
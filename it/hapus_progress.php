<?php

session_start();

if (!isset($_SESSION['db_user'])) {
    header("Location: login.php");
    exit();
}

include "../config/database_p1.php";

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($id <= 0 || $project_id <= 0) {
    die("Parameter tidak valid.");
}

$sql_get = "SELECT foto FROM it_project_progress WHERE id = ?";
$q_get = sqlsrv_query($conn, $sql_get, array($id));

if ($q_get === false) {
    die(print_r(sqlsrv_errors(), true));
}

$data = sqlsrv_fetch_array($q_get, SQLSRV_FETCH_ASSOC);

if ($data && $data['foto'] != "") {
    if (file_exists("uploads/" . $data['foto'])) {
        unlink("uploads/" . $data['foto']);
    }
}

$sql_delete = "DELETE FROM it_project_progress WHERE id = ?";
$query = sqlsrv_query($conn, $sql_delete, array($id));

if ($query) {

    $sql_last = "SELECT TOP 1 progress, status 
                 FROM it_project_progress 
                 WHERE project_id = ? 
                 ORDER BY id DESC";

    $q_last = sqlsrv_query($conn, $sql_last, array($project_id));
    $last = sqlsrv_fetch_array($q_last, SQLSRV_FETCH_ASSOC);

    if ($last) {
        $sql_update = "UPDATE it_projects SET progress = ?, status = ? WHERE id = ?";
        sqlsrv_query($conn, $sql_update, array($last['progress'], $last['status'], $project_id));
    } else {
        $sql_update = "UPDATE it_projects SET progress = 0, status = 'Planning' WHERE id = ?";
        sqlsrv_query($conn, $sql_update, array($project_id));
    }

    header("Location: detail_project.php?id=" . $project_id);
    exit();

} else {
    die(print_r(sqlsrv_errors(), true));
}

?>
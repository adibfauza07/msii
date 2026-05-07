<?php

session_start();

if (!isset($_SESSION['db_user'])) {
    header("Location: login.php");
    exit();
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

$sql_progress = "DELETE FROM it_project_progress WHERE project_id=?";
sqlsrv_query($conn, $sql_progress, array($id));

$sql_biaya = "DELETE FROM it_project_costs WHERE project_id=?";
sqlsrv_query($conn, $sql_biaya, array($id));

$sql_project = "DELETE FROM it_projects WHERE id=?";
$query = sqlsrv_query($conn, $sql_project, array($id));

if ($query) {

    header("Location: project.php");
    exit();

} else {

    die(print_r(sqlsrv_errors(), true));

}
?>
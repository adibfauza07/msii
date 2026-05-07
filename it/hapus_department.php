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

$sql_check_child = "SELECT COUNT(*) AS total FROM DEPT WHERE DEP_PARENT = ?";
$q_child = sqlsrv_query($conn, $sql_check_child, array($id));
$child = sqlsrv_fetch_array($q_child, SQLSRV_FETCH_ASSOC);

if ($child['total'] > 0) {
    die("Department tidak bisa dihapus karena masih punya child department. Hapus child department dulu.");
}

$sql_delete = "DELETE FROM DEPT WHERE DEP_ID = ?";
$query = sqlsrv_query($conn, $sql_delete, array($id));

if ($query) {
    header("Location: department.php");
    exit();
} else {
    die(print_r(sqlsrv_errors(), true));
}
?>
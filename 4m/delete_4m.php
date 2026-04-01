<?php
require_once __DIR__ . '/../config/database.php';

$id = isset($_GET['id']) ? $_GET['id'] : 0;

if ($id > 0) {
    // Gunakan query delete berdasarkan CONTROL_ID
    $sql = "DELETE FROM PROSES_CHANGE WHERE CONTROL_ID = ?";
    $params = array($id);
    $stmt = q($sql, $params);

    if ($stmt) {
        header("Location: dashboard_4m.php?page=history&status=deleted");
    } else {
        die(print_r(sqlsrv_errors(), true));
    }
}
?>
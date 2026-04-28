<?php
require_once __DIR__ . '/../config/database_p1.php';
header("Content-Type: text/html; charset=utf-8");

$id     = intval($_POST['ID']);
$w      = floatval($_POST['Weight_Part_Actual']);
$cavity = intval($_POST['CAVITY']);

if ($id <= 0) {
    die("ID tidak valid.");
}

$sql = "UPDATE TRIAL_PE_WPart_ACT
        SET Weight_Part_Actual = ?, CAVITY = ?
        WHERE ID = ?";

$stmt = sqlsrv_query($conn, $sql, array($w, $cavity, $id));

if ($stmt) {
    echo "<script>alert('Berhasil update data Actual!'); window.history.back();</script>";
} else {
    echo "<pre>";
    print_r(sqlsrv_errors());
    echo "</pre>";
}

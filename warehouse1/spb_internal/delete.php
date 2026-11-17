<?php
session_start();

require_once __DIR__ . "/../../config/database_ordering.php";

if (!isset($conn) || $conn === false) {
    header("Location: ../login.php?error=session_expired");
    exit();
}

function base_url($path = '') {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    return $protocol . $host . $dir . '/' . $path;
}

function redirect($url) {
    header('Location: ' . base_url($url));
    exit;
}

function set_flash($key, $message) {
    $_SESSION[$key] = $message;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    set_flash('error', 'Invalid ID');
    redirect('index.php');
}

sqlsrv_begin_transaction($conn);
try {
    $sql_detail = "DELETE FROM TR_MATERIAL_SLIP_DETAIL WHERE HEADER_ID = ?";
    $params = array($id);
    $stmt = sqlsrv_query($conn, $sql_detail, $params);
    if (!$stmt) throw new Exception("Failed to delete details");
    
    $sql_header = "DELETE FROM TR_MATERIAL_SLIP_HEADER WHERE ID = ?";
    $params = array($id);
    $stmt = sqlsrv_query($conn, $sql_header, $params);
    if (!$stmt) throw new Exception("Failed to delete header");
    
    sqlsrv_commit($conn);
    set_flash('success', 'Data deleted successfully');
} catch (Exception $e) {
    sqlsrv_rollback($conn);
    set_flash('error', 'Failed to delete data: ' . $e->getMessage());
}

redirect('index.php');
?>
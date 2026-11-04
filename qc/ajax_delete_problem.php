<?php
// FILE: ajax_delete_problem.php
error_reporting(E_ALL);
ini_set('display_errors', 0); 
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) { session_start(); }

$active_plant = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1';
$db_path = ($active_plant == 'p2') ? __DIR__ . '/../config/database.php' : __DIR__ . '/../config/database_p1.php';
if(file_exists($db_path)) { require_once $db_path; }

if (!isset($conn) || $conn === false) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal']);
    exit;
}

$id = isset($_POST['id']) ? $_POST['id'] : '';

if ($id != "") {
    sqlsrv_begin_transaction($conn);
    
    // Hapus detail (PIC) terlebih dahulu karena ada relasi Foreign Key
    $sql_del_pic = "DELETE FROM dbo.QC_DIC_PIC WHERE ID_PROBLEM = ?";
    $stmt_pic = sqlsrv_query($conn, $sql_del_pic, array($id));
    
    // Hapus master (QC_PROBLEM)
    $sql_del_master = "DELETE FROM dbo.QC_PROBLEM WHERE ID_PROBLEM = ?";
    $stmt_master = sqlsrv_query($conn, $sql_del_master, array($id));
    
    if ($stmt_pic && $stmt_master) {
        sqlsrv_commit($conn);
        echo json_encode(['status' => 'success']);
    } else {
        sqlsrv_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data dari database']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
}
exit;
?>
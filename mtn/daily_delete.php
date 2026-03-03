<?php
require_once "../middleware/Auth.php";
require_once "../middleware/RoleCheck.php";
only(['p2','admin']);   // sesuaikan role yang boleh menghapus

require "../config/database.php";
header("Content-Type: application/json");

// ===============================================
// VALIDASI METHOD
// ===============================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('status'=>'error','message'=>'Invalid request method'));
    exit;
}

// ===============================================
// VALIDASI ID
// ===============================================
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
    echo json_encode(array(
        'status'=>'error',
        'message'=>'ID tidak valid'
    ));
    exit;
}

// ===============================================
// DELETE
// ===============================================
$sql = "DELETE FROM MTN_DAILY WHERE ID = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));

if ($stmt === false) {
    echo json_encode(array(
        'status'=>'error',
        'message'=>'SQL Delete Error',
        'sqlsrv'=>sqlsrv_errors()
    ));
    exit;
}

// ===============================================
// SUCCESS
// ===============================================
echo json_encode(array('status'=>'ok'));
exit;
?>

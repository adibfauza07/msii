<?php
require_once "../mtn/middleware/Auth.php";
require_once "../mtn/middleware/RoleCheck.php";
only(['p2','admin']);      // sesuaikan role
require_once "../config/database.php";

header("Content-Type: application/json");

// =====================================================
// VALIDASI METHOD
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array(
        'status'  => 'error',
        'message' => 'Invalid request'
    ));
    exit;
}

// =====================================================
// VALIDASI PARAMETER
// =====================================================
$carno = isset($_POST['carno']) ? trim($_POST['carno']) : '';

if ($carno === '') {
    echo json_encode(array(
        'status'  => 'error',
        'message' => 'CARNO kosong'
    ));
    exit;
}

// =====================================================
// PROSES DELETE
// =====================================================
$sql = "DELETE FROM MTN_HISTORY_CARNO WHERE CARNO = ?";
$stmt = sqlsrv_query($conn, $sql, array($carno));

if ($stmt === false) {
    echo json_encode(array(
        'status'  => 'error',
        'message' => 'SQL Delete Error',
        'sqlsrv'  => sqlsrv_errors()
    ));
    exit;
}

// =====================================================
// SUCCESS
// =====================================================
echo json_encode(array('status' => 'ok'));
exit;
?>

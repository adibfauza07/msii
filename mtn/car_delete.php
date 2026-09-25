<?php
require_once "../mtn/middleware/Auth.php";
require_once "../mtn/middleware/RoleCheck.php";
only(['p2','admin']);
require_once "../config/database.php";

header("Content-Type: application/json");

// =====================================================
// VALIDASI METHOD & PARAMETER
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$carno = isset($_POST['carno']) ? trim($_POST['carno']) : '';

if (empty($carno)) {
    echo json_encode(['status' => 'error', 'message' => 'Nomor CARNO tidak valid atau kosong']);
    exit;
}

// =====================================================
// PROSES DELETE
// =====================================================
$sql = "DELETE FROM MTN_HISTORY_CARNO WHERE CARNO = ?";
$stmt = sqlsrv_query($conn, $sql, [$carno]);

if ($stmt === false) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gagal menghapus data di database',
        'sqlsrv'  => sqlsrv_errors()
    ]);
    exit;
}

// SUCCESS
echo json_encode(['status' => 'ok', 'message' => 'Data berhasil dihapus']);
exit;
?>
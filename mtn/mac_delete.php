<?php
// ======================================================================
// SECURITY — hanya MTN boleh hapus (ubah jika perlu)
// ======================================================================
require_once "../middleware/Auth.php";
require_once "../middleware/RoleCheck.php";
only(['p2']);   // jika admin juga boleh: only(['mtn','admin']);
require_once "../config/database.php";

header('Content-Type: application/json');

// ======================================================================
// VALIDASI REQUEST
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error','message'=>'Invalid request method']);
    exit;
}

$mac_id = isset($_POST['mac_id']) ? intval($_POST['mac_id']) : 0;

if ($mac_id <= 0) {
    echo json_encode(['status'=>'error','message'=>'MAC_ID tidak valid']);
    exit;
}

// ======================================================================
// DELETE DATA
// ======================================================================
$sql = "DELETE FROM MAC_MTN WHERE MAC_ID = ?";
$stmt = sqlsrv_query($conn, $sql, [$mac_id]);

if ($stmt === false) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gagal menghapus data',
        'sqlsrv'  => sqlsrv_errors()
    ]);
    exit;
}

// ======================================================================
// SUCCESS
// ======================================================================
echo json_encode(['status'=>'ok']);
exit;
?>

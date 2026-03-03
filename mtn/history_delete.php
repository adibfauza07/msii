<?php
require_once "../middleware/Auth.php";
require_once "../middleware/RoleCheck.php";
only(['p2']);     // ubah jika role lain boleh hapus
require "../config/database.php";

header('Content-Type: application/json');

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
    echo json_encode(['status'=>'error','message'=>'ID tidak valid']);
    exit;
}

$sql = "DELETE FROM MTN_HISTORY_MAC WHERE ID = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);

if ($stmt === false) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gagal eksekusi SQL',
        'sqlsrv'  => sqlsrv_errors()
    ]);
    exit;
}

echo json_encode(['status'=>'ok']);
exit;

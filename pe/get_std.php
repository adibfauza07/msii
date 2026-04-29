<?php
// msii/pe/get_std.php
require_once '../config/database_p1.php';

header('Content-Type: application/json');

$code = isset($_GET['code']) ? $_GET['code'] : '';

if (empty($code)) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT TOP 1 
            WEIGHT_PART_STD, 
            WEIGHT_RUNNER_STD, 
            CYCLE_TIME_STD, 
            TONAGE_STD, 
            CAVITY_STD 
        FROM TRIAL_PE_STD 
        WHERE ITEM_CODE = ?";

$stmt = sqlsrv_query($conn, $sql, array($code));

if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    echo json_encode($row);
} else {
    // Jika data STD belum dibuat di database untuk part ini
    echo json_encode([
        'WEIGHT_PART_STD' => '',
        'WEIGHT_RUNNER_STD' => '',
        'CYCLE_TIME_STD' => '',
        'TONAGE_STD' => '',
        'CAVITY_STD' => ''
    ]);
}
?>
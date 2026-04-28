<?php
require_once __DIR__ . '/../config/database_p1.php';
header('Content-Type: application/json');

$code = isset($_GET['code']) ? $_GET['code'] : '';

$sql = "SELECT ITEM_CODE, WEIGHT_PART_STD, WEIGHT_RUNNER_STD, CYCLE_TIME_STD, TONAGE_STD, CAVITY_STD
        FROM TRIAL_PE_STD
        WHERE ITEM_CODE = ?";

$stmt = sqlsrv_query($conn, $sql, [$code]);
$row  = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;

if (!$row) {
    echo json_encode(new stdClass());
} else {
    echo json_encode($row);
}
?>
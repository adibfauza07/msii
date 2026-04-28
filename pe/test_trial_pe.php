<?php
require_once __DIR__ . '/../config/database_p1.php';

$sql = "SELECT TOP 1 * FROM TRIAL_PE ORDER BY TRIAL_CODE DESC";
$stmt = sqlsrv_query($conn, $sql);

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($row);

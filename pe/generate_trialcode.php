<?php
require_once '../config/database.php';

$prefix = "TR-PE-" . date('Y') . "-";

$sql = "SELECT TOP 1 TRIAL_CODE FROM TRIAL_PE ORDER BY TRIAL_CODE DESC";
$res = sqlsrv_query($conn, $sql);

$next = 1;
if ($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) {
    $next = intval($row['TRIAL_CODE']) + 1;
}

echo json_encode(["code" => $next]);
?>

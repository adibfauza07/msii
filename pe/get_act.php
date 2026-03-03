<?php
require_once '../config/database.php';

$code = isset($_GET['code']) ? $_GET['code'] : '';

$sql = "SELECT 
            Trial_CODE,
            Weight_Part_Actual,
            CAVITY
        FROM Trial_PE_WPart_ACT
        WHERE Trial_CODE = ?";

$stmt = sqlsrv_query($conn, $sql, [$code]);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($row ?: []);
?>

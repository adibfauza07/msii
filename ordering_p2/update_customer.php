<?php
require_once __DIR__ . "/../config/database_ordering.php";

$cust_id = isset($_POST['cust_id']) ? (int)$_POST['cust_id'] : 0;
$curr_code = isset($_POST['curr_code']) ? trim($_POST['curr_code']) : '';

if ($cust_id > 0) {
    $sql = "UPDATE ORDR_IMPORT_XLS SET CUST_ID = ?, ORDR_CURR = ?";
    $params = array($cust_id, $curr_code);
    sqlsrv_query($conn, $sql, $params);
    echo "Sukses";
}
?>
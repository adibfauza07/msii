<?php
// config/log_helper.php

function write_user_log($conn, $action, $module, $refNo = '', $details = '') {
    if (!$conn) return false;
    
    $username = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : 'SYSTEM';
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    
    $sql = "INSERT INTO dbo.APP_USER_LOG (LOG_DATE, USERNAME, [ACTION], MODULE, REFERENCE_NO, DETAILS, IP_ADDRESS) 
            VALUES (GETDATE(), ?, ?, ?, ?, ?, ?)";
    $params = array($username, strtoupper($action), $module, $refNo, $details, $ip);
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt !== false) {
        sqlsrv_free_stmt($stmt);
        return true;
    }
    return false;
}
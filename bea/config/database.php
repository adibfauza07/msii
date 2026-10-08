<?php
// ==========================================================
// config/database.php
// Koneksi SQL Server (PHP 5.4 + SQL Server 2008)
// Server   : 192.168.0.4
// Database : msdata
// ==========================================================

if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$serverName   = "192.168.0.4";
$databaseName = "msdata";
$loginUrl     = "/msii/bea/login.php";

// Akun kredensial SQL Server yang sah di server
$db_user_sql = "plan1";
$db_pass_sql = "plan1"; 

// Opsi koneksi
$connectionOptions = array(
    "Database"             => $databaseName,
    "Uid"                  => $db_user_sql,
    "PWD"                  => $db_pass_sql,
    "CharacterSet"         => "UTF-8",
    "ReturnDatesAsStrings" => false
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    $dbConnected = false;
    if (!defined('LOGIN_PAGE')) {
        $_SESSION = array();
        if (session_id() !== '') {
            session_destroy();
        }
        if (!headers_sent()) {
            header("Location: " . $loginUrl . "?error=session_expired");
        } else {
            echo "<script>window.location.href='" . $loginUrl . "?error=session_expired';</script>";
        }
        exit();
    }
} else {
    $dbConnected = true;
}
?>
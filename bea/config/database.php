<?php
// ==========================================================
// config/database.php
// Koneksi SQL Server (PHP 5.4 + SQL Server 2008)
// Server   : 192.168.0.4
// Database : msdata
// ==========================================================

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$serverName   = "192.168.0.4";
$databaseName = "msdata";
$loginUrl     = "/msii/bea/login.php";

$uid = "";
$pwd = "";
$should_connect = false;
$conn = false;

// KASUS A: SEDANG PROSES LOGIN (Dipanggil dari login.php)
if (isset($is_login_process) && $is_login_process == true) {
    if (isset($temp_username) && isset($temp_password)) {
        $uid = $temp_username;
        $pwd = $temp_password;
        $should_connect = true;
    }
}
// KASUS B: USER SUDAH LOGIN (Ambil dari session)
elseif (isset($_SESSION['db_user']) && !empty($_SESSION['db_user'])) {
    $uid = $_SESSION['db_user'];
    $pwd = $_SESSION['db_pass'];
    $should_connect = true;
}
// KASUS C: BELUM LOGIN
else {
    if (!defined('LOGIN_PAGE')) {
        header("Location: " . $loginUrl);
        exit();
    }
    $conn = false;
    return;
}

// EKSEKUSI KONEKSI
if ($should_connect) {
    $connectionOptions = array(
        "Database"     => $databaseName,
        "Uid"          => $uid,
        "PWD"          => $pwd,
        "CharacterSet" => "UTF-8"
    );

    $conn = sqlsrv_connect($serverName, $connectionOptions);

    // Jika koneksi gagal dan bukan di halaman login, buang session dan redirect
    if ($conn === false && !defined('LOGIN_PAGE')) {
        session_destroy();
        header("Location: " . $loginUrl . "?error=session_expired_or_db_error");
        exit();
    }
}
?>
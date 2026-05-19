<?php
// ==========================================================
// db_plant2.php
// Koneksi SQL Server Plant 2
// Server   : 192.168.0.9
// Database : msData
// PHP 5.4 + SQL Server 2008
// ==========================================================


// ==========================================================
// SESSION
// ==========================================================
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// ==========================================================
// SETTING DATABASE PLANT 2
// ==========================================================
$serverName   = "192.168.0.9";
$databaseName = "data1";


// ==========================================================
// SETTING LOGIN URL
// Karena file ini ada di /msii/config/
// dan manual_order.php ada di /msii/ordering_p2/
// maka redirect login harus pakai path absolute.
// ==========================================================
$loginUrl = "/msii/ordering_p2/login.php";


// ==========================================================
// VARIABLE DEFAULT
// ==========================================================
$uid = "";
$pwd = "";
$should_connect = false;
$conn = false;


// ==========================================================
// KASUS A: SEDANG PROSES LOGIN
// Dipakai oleh login.php
// login.php harus menyiapkan:
// define('LOGIN_PAGE', true);
// $is_login_process = true;
// $temp_username = $_POST['username'];
// $temp_password = $_POST['password'];
// ==========================================================
if (isset($is_login_process) && $is_login_process == true) {

    if (isset($temp_username) && isset($temp_password)) {
        $uid = $temp_username;
        $pwd = $temp_password;
        $should_connect = true;
    }

}


// ==========================================================
// KASUS B: USER SUDAH LOGIN
// Ambil user dan password database dari session
// ==========================================================
elseif (isset($_SESSION['db_user']) && !empty($_SESSION['db_user'])) {

    $uid = $_SESSION['db_user'];
    $pwd = $_SESSION['db_pass'];
    $should_connect = true;

}


// ==========================================================
// KASUS C: BELUM LOGIN
// Jika bukan halaman login, arahkan ke /msii/login.php
// ==========================================================
else {

    if (!defined('LOGIN_PAGE')) {
        header("Location: " . $loginUrl);
        exit();
    }

    // Jika dipanggil oleh login.php, jangan connect dulu.
    $conn = false;
    return;
}


// ==========================================================
// EKSEKUSI KONEKSI DATABASE
// ==========================================================
if ($should_connect) {

    $connectionOptions = array(
        "Database"     => $databaseName,
        "Uid"          => $uid,
        "PWD"          => $pwd,
        "CharacterSet" => "UTF-8"
    );

    $conn = sqlsrv_connect($serverName, $connectionOptions);

    // Jika koneksi gagal saat user sudah login,
    // hapus session lalu arahkan ulang ke login.
    if ($conn === false && !defined('LOGIN_PAGE')) {
        session_destroy();
        header("Location: " . $loginUrl . "?error=session_expired");
        exit();
    }

} else {

    $conn = false;

}
?>
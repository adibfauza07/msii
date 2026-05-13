<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| SERVER DATABASE BERDASARKAN PLANT
|--------------------------------------------------------------------------
*/

$serverName = "192.168.0.4"; // Plant 1 default

if (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2') {
    $serverName = "192.168.0.9"; // Plant 2
}

if (isset($serverCheck) && !empty($serverCheck)) {
    $serverName = $serverCheck;
}

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

$databaseName = "msdata";

/*
|--------------------------------------------------------------------------
| USER SQL SERVER TETAP
|--------------------------------------------------------------------------
| GANTI sesuai user SQL Server kamu.
| Jangan pakai user login aplikasi seperti admin/admin123.
|--------------------------------------------------------------------------
*/

$sqlUser = "sa";
$sqlPass = "Ishikawa25";

/*
|--------------------------------------------------------------------------
| CEK HALAMAN LOGIN / NON LOGIN
|--------------------------------------------------------------------------
*/

if (!defined('LOGIN_PAGE')) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

/*
|--------------------------------------------------------------------------
| KONEKSI SQL SERVER
|--------------------------------------------------------------------------
*/

$connectionOptions = array(
    "Database" => $databaseName,
    "Uid" => $sqlUser,
    "PWD" => $sqlPass,
    "CharacterSet" => "UTF-8",
    "TrustServerCertificate" => true
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {

    if (defined('LOGIN_PAGE')) {
        // Saat di halaman login, biarkan login.php yang tampilkan error
        return;
    } else {
        session_destroy();
        header("Location: login.php?error=session_expired");
        exit();
    }
}
?>
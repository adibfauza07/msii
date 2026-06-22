<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Menentukan server database berdasarkan sesi active_plant
switch (isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1') {
    case 'p2':
        $serverName = "192.168.0.9";
        break;
    case 'p1':
    default:
        $serverName = "192.168.0.4";
        break;
}

$databaseName = "msData";

// Proteksi halaman jika belum memiliki sesi login aktif
if (!isset($_SESSION['db_user']) || empty($_SESSION['db_user'])) {
    header("Location: ../login.php");
    exit();
}

$uid = $_SESSION['db_user'];
$pwd = $_SESSION['db_pass'];

$connectionOptions = array(
    "Database" => $databaseName,
    "Uid" => $uid,
    "PWD" => $pwd,
    "CharacterSet" => "UTF-8"
);

// Inisialisasi koneksi menggunakan driver sqlsrv
$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Fungsi helper penangkal XSS injection
// Fungsi helper penangkal XSS yang aman untuk objek DateTime SQL Server
function h($value) {
    if ($value instanceof DateTime) {
        return $value->format('Y-m-d H:i:s'); // atau sesuaikan formatnya jika diperlukan
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
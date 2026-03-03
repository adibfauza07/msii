<?php
// /msii/config/database_aging.php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['db_user'], $_SESSION['active_plant'])) {
    header("Location: login.php");
    exit();
}

$active_plant = $_SESSION['active_plant'];
$serverName = ($active_plant == 'p1') ? "192.168.0.4" : "192.168.0.9";
$uid = $_SESSION['db_user'];
$pwd = $_SESSION['db_pass'];

$connectionOptions = array(
    "Database" => "msdata",
    "Uid" => $uid,
    "PWD" => $pwd,
    "CharacterSet" => "UTF-8"
);

// Simpan koneksi ke variabel global agar bisa dibaca fungsi q()
$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

if (!function_exists('q')) {
    function q($sql, $params = []) {
        global $conn; // Mengambil resource koneksi dari global scope
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            die("Query Error: " . print_r(sqlsrv_errors(), true));
        }
        return $stmt;
    }
}
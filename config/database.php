<?php
// /msii/config/database.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['server_sql'], $_SESSION['erp_user'], $_SESSION['erp_pass'])) {
    die("Session database tidak lengkap. Silakan login ulang.");
}

$serverName = $_SESSION['server_sql'];
$uid        = $_SESSION['erp_user'];
$pwd        = $_SESSION['erp_pass'];

$connectionOptions = array(
    "Database"     => "msdata",
    "Uid"          => $uid,
    "PWD"          => $pwd,
    "CharacterSet" => "UTF-8"
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    echo "<h3 style='color:red;'>Koneksi database gagal!</h3>";
    die(print_r(sqlsrv_errors(), true));
}
// =============================================================
// TAMBAHAN: FUNGSI SINGKAT QUERY (q)
// Ini adalah fungsi yang dicari oleh history_machine.php
// =============================================================
if (!function_exists('q')) {
    function q($sql, $params = []) {
        global $conn; // Mengambil variabel $conn dari luar fungsi
        
        // Eksekusi query standar sql server
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        // Cek error
        if ($stmt === false) {
            // Jika error, tampilkan pesan error biar gampang debugging
            die("Query Error di fungsi q(): " . print_r(sqlsrv_errors(), true));
        }
        
        return $stmt;
    }
}
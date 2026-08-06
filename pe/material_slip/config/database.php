<?php
// material_slip/config/database.php
// Menggunakan koneksi dari database_ordering.php

// Ambil koneksi dari parent directory
$basePath = dirname(__DIR__);
$configPath = $basePath . '/../config/database_ordering.php';

if (file_exists($configPath)) {
    require_once $configPath;
    
    // Gunakan koneksi yang sudah ada
    if (isset($conn) && $conn !== false) {
        // Koneksi sudah ada dari database_ordering.php
    } else {
        // Jika koneksi belum ada, buat koneksi sendiri
        $connectionOptions = array(
            "Database" => "msData",
            "Uid" => isset($_SESSION['db_user']) ? $_SESSION['db_user'] : "",
            "PWD" => isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "",
            "CharacterSet" => "UTF-8"
        );
        
        $serverName = isset($_SESSION['active_server']) ? $_SESSION['active_server'] : "192.168.0.4";
        $conn = sqlsrv_connect($serverName, $connectionOptions);
    }
} else {
    // Fallback jika database_ordering.php tidak ditemukan
    $connectionOptions = array(
        "Database" => "msData",
        "Uid" => isset($_SESSION['db_user']) ? $_SESSION['db_user'] : "",
        "PWD" => isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "",
        "CharacterSet" => "UTF-8"
    );
    
    $serverName = isset($_SESSION['active_server']) ? $_SESSION['active_server'] : "192.168.0.4";
    $conn = sqlsrv_connect($serverName, $connectionOptions);
}

// Fungsi untuk mendapatkan koneksi
function getDB() {
    global $conn;
    return $conn;
}
?>
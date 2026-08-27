<?php
// File: budgeting/config.php

// Cara pengecekan session yang paling aman untuk PHP 5.4 ke bawah
if (session_id() == "") {
    session_start();
}

// Pengaturan Database SQL Server 2008
$serverName   = "192.168.0.9";
$dbBudget     = "budget"; // Database untuk modul Budgeting / Non-PO
$dbMsData     = "msdata"; // Database utama untuk PO / Master Barang
$loginUrl     = "login.php"; // Relatif di dalam folder budgeting

// Flag untuk mendeteksi apakah kita sedang berada di halaman login
$is_login_page = defined('LOGIN_PAGE') && LOGIN_PAGE === true;

$conn = false;        // Resource koneksi untuk db 'budget'
$conn_msdata = false; // Resource koneksi untuk db 'msdata'

// Jika user sudah memiliki session login
if (isset($_SESSION['db_user']) && isset($_SESSION['db_pass'])) {
    
    // 1. Buka Koneksi ke Database BUDGET
    $connOptionsBudget = array(
        "Database"     => $dbBudget,
        "Uid"          => $_SESSION['db_user'],
        "PWD"          => $_SESSION['db_pass'],
        "CharacterSet" => "UTF-8"
    );
    $conn = sqlsrv_connect($serverName, $connOptionsBudget);

    // 2. Buka Koneksi ke Database MSDATA
    $connOptionsMsData = array(
        "Database"     => $dbMsData,
        "Uid"          => $_SESSION['db_user'],
        "PWD"          => $_SESSION['db_pass'],
        "CharacterSet" => "UTF-8"
    );
    $conn_msdata = sqlsrv_connect($serverName, $connOptionsMsData);

    // Jika salah satu database gagal terkoneksi dan bukan di halaman login
    if (($conn === false || $conn_msdata === false) && !$is_login_page) {
        // Hancurkan session dan kembalikan ke login
        session_destroy();
        header("Location: " . $loginUrl . "?error=expired");
        exit();
    }

} else {
    // Jika belum login dan bukan di halaman login, paksa ke login.php
    if (!$is_login_page) {
        header("Location: " . $loginUrl);
        exit();
    }
}
?>
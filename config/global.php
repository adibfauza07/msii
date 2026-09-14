<?php
// Kompatibel dengan PHP 5.4
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$databaseName = "msData";
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : "";

$serverName = "192.168.0.4"; // Default Server
$detectedPlant = "p1";       // Default Plant
$baseUrl = "/msii/ordering_p1"; // Default Base URL untuk Redirect

/*
    AUTO DETECT BERDASARKAN FOLDER
    /msii/ordering_p1/ = Plant 1
    /msii/ordering_p2/ = Plant 2
*/
if (strpos($scriptName, "/ordering_p1/") !== false) {
    $serverName = "192.168.0.4";
    $detectedPlant = "p1";
    $baseUrl = "/msii/ordering_p1";
} elseif (strpos($scriptName, "/ordering_p2/") !== false) {
    $serverName = "192.168.0.9";
    $detectedPlant = "p2";
    $baseUrl = "/msii/ordering_p2";
} else {
    // Fallback ke Session jika tidak terdeteksi dari URL
    if (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == "p2") {
        $serverName = "192.168.0.9";
        $detectedPlant = "p2";
        $baseUrl = "/msii/ordering_p2";
    } else {
        $serverName = "192.168.0.4";
        $detectedPlant = "p1";
        $baseUrl = "/msii/ordering_p1";
    }
}

$_SESSION['active_plant'] = $detectedPlant;
$_SESSION['active_server'] = $serverName;

$uid = "";
$pwd = "";
$should_connect = false;

/*
    KASUS A: Dipanggil dari login.php saat proses login
*/
if (isset($is_login_process) && $is_login_process == true) {
    if (isset($temp_username) && isset($temp_password)) {
        $uid = $temp_username;
        $pwd = $temp_password;
        $should_connect = true;

        if (isset($serverCheck) && $serverCheck != "") {
            $serverName = $serverCheck;
            if ($serverCheck == "192.168.0.9") {
                $_SESSION['active_plant'] = "p2";
            } else {
                $_SESSION['active_plant'] = "p1";
            }
            $_SESSION['active_server'] = $serverName;
        }
    }
}
/*
    KASUS B: User sudah login 
    (WARNING: Menyimpan $_SESSION['db_pass'] tidak disarankan untuk jangka panjang)
*/
elseif (isset($_SESSION['db_user']) && $_SESSION['db_user'] != "") {
    $uid = $_SESSION['db_user'];
    $pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "";
    $should_connect = true;
}
/*
    KASUS C: Belum login & bukan halaman login
*/
else {
    if (!defined('LOGIN_PAGE')) {
        // Menggunakan $baseUrl dinamis dan typo diperbaiki
        header("Location: " . $baseUrl . "/login.php");
        exit();
    }
    $conn = false;
    return;
}

/*
    KONEKSI SQL SERVER 2008
*/
if ($should_connect) {
    $connectionOptions = array(
        "Database" => $databaseName,
        "Uid" => $uid,
        "PWD" => $pwd,
        "CharacterSet" => "UTF-8"
    );

    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if ($conn === false) {
        // Catat error ke log server secara rahasia (membantu proses debugging Anda)
        error_log("Gagal koneksi SQL Server: " . print_r(sqlsrv_errors(), true));
        
        if (!defined('LOGIN_PAGE')) {
            session_destroy();
            header("Location: " . $baseUrl . "/login.php?error=session_expired_or_db_down");
            exit();
        }
    }
} else {
    $conn = false;
}
?>
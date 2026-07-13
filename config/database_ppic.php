<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$databaseName = "msData";

$scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : "";

$serverName = "192.168.0.4";
$detectedPlant = "p1";

/*
    AUTO DETECT BERDASARKAN FOLDER
    /msii/ppic/  = Plant 2
    /msii/ppic1/ = Plant 1
*/
if (strpos($scriptName, "/ppic1/") !== false) {
    $serverName = "192.168.0.4";
    $detectedPlant = "p1";
} elseif (strpos($scriptName, "/ppic2/") !== false) {
    $serverName = "192.168.0.9";
    $detectedPlant = "p2";
} else {
    if (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == "p2") {
        $serverName = "192.168.0.9";
        $detectedPlant = "p2";
    } else {
        $serverName = "192.168.0.4";
        $detectedPlant = "p1";
    }
}

$_SESSION['active_plant'] = $detectedPlant;
$_SESSION['active_server'] = $serverName;

$uid = "";
$pwd = "";
$should_connect = false;

/*
    KASUS A:
    Dipanggil dari login.php saat proses login
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
    KASUS B:
    User sudah login
*/
elseif (isset($_SESSION['db_user']) && $_SESSION['db_user'] != "") {
    $uid = $_SESSION['db_user'];
    $pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "";
    $should_connect = true;
}

/*
    KASUS C:
    Belum login
*/
else {
    if (!defined('LOGIN_PAGE')) {
        header("Location: /msii/ppic2/login.php");
        exit();
    }

    $conn = false;
    return;
}

/*
    KONEKSI SQL SERVER
*/
if ($should_connect) {
    $connectionOptions = array(
        "Database" => $databaseName,
        "Uid" => $uid,
        "PWD" => $pwd,
        "CharacterSet" => "UTF-8"
    );

    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if ($conn === false && !defined('LOGIN_PAGE')) {
        session_destroy();
        header("Location: /msii/ppic2/login.php?error=session_expired");
        exit();
    }
} else {
    $conn = false;
}
?>
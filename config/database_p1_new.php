<?php

/* =========================================
   SESSION SETTING
========================================= */

ini_set('session.gc_maxlifetime', 28800);
session_set_cookie_params(28800);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/* =========================================
   DATABASE
========================================= */

$databaseName = "msData";

/* =========================================
   SERVER PLANT
========================================= */

$serverName = "192.168.0.4";

if (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == "p2") {
    $serverName = "192.168.0.9";
}

/* =========================================
   DEFAULT VARIABLE
========================================= */

$uid = "";
$pwd = "";

$conn = false;

$should_connect = false;

$conn_error = false;

/* =========================================
   LOGIN PROCESS
========================================= */

if (isset($is_login_process) && $is_login_process == true) {

    if (isset($temp_username) && isset($temp_password)) {

        $uid = trim($temp_username);
        $pwd = $temp_password;

        $should_connect = true;

        if (isset($serverCheck) && $serverCheck != "") {
            $serverName = $serverCheck;
        }
    }
}

/* =========================================
   SESSION LOGIN
========================================= */

elseif (isset($_SESSION['db_user']) && $_SESSION['db_user'] != "") {

    $uid = $_SESSION['db_user'];
    $pwd = $_SESSION['db_pass'];

    $should_connect = true;
}

/* =========================================
   BELUM LOGIN
========================================= */

else {

    if (!defined('LOGIN_PAGE')) {

        header("Location: login.php");
        exit();
    }

    $conn = false;
    return;
}

/* =========================================
   CONNECT SQL SERVER
========================================= */

if ($should_connect) {

    $connectionOptions = array(

        "Database" => $databaseName,

        "Uid" => $uid,

        "PWD" => $pwd,

        "CharacterSet" => "UTF-8",

        // PERFORMANCE
        "ConnectionPooling" => true,

        "MultipleActiveResultSets" => false,

        // TIMEOUT
        "LoginTimeout" => 5,

        "QueryTimeout" => 30,

        // NETWORK
        "Encrypt" => false,

        "TrustServerCertificate" => true
    );

    $conn = sqlsrv_connect($serverName, $connectionOptions);

    /* =========================================
       JANGAN AUTO LOGOUT
    ========================================= */

    if ($conn === false) {

        $conn_error = true;

        // jangan destroy session
        // jangan redirect login

    } else {

        $conn_error = false;
    }
}
?>
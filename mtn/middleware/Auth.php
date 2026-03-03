<?php
// /msii/middleware/Auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==============================
// AUTO LOGOUT 10 MENIT
// ==============================
$timeout = 10 * 60; // 10 menit

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: /msii/mtn/login.php?timeout=1");
    exit;
}
$_SESSION['last_activity'] = time();

// ==============================
// HARUS LOGIN
// ==============================
if (!isset($_SESSION['erp_user'])) {
    header("Location: /msii/mtn/login.php");
    exit;
}

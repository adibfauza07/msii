<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$timeout = 15 * 60; // Set 15 menit timeout

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: login.php?msg=timeout"); // Path ke root ordering_p2
    exit;
}
$_SESSION['last_activity'] = time();

// Proteksi akses
if (!isset($_SESSION['erp_user']) || $_SESSION['active_plant'] !== 'p2') {
    header("Location: login.php");
    exit;
}
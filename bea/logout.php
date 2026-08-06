<?php
/**
 * Logout IT Inventory
 * Lokasi:
 *   /msii/it-inventory-bc/logout.php
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/*
 * Hapus seluruh data session login agar login.php
 * tidak langsung mengarahkan kembali ke index.php.
 */
$_SESSION = array();

/*
 * Hapus cookie session jika PHP memakai session cookie.
 */
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

/*
 * Hancurkan session.
 */
session_destroy();

/*
 * Kembali ke menu login IT Inventory.
 */
header("Location: /msii/bea/login.php");
exit();
?>

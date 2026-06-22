<?php
// 1. Pastikan session dimulai agar bisa dihancurkan
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Hapus semua variabel session yang tersimpan (db_user, db_pass, active_plant, dll)
$_SESSION = array();

// 3. Hapus cookie session jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Hancurkan session seutuhnya dari memori server
session_destroy();

// 5. Redireksi kembali ke halaman login di dalam folder mold
header("Location: mold/login.php");
exit();
?>
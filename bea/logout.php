<?php
/**
 * Logout IT Inventory
 * Lokasi: /msii/bea/logout.php
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Catat log keluar (LOGOUT) sebelum sesi dihapus
if (isset($_SESSION['db_user']) && trim($_SESSION['db_user']) !== "") {
    require_once __DIR__ . '/config/database.php';
    if (isset($conn) && $conn !== false) {
        $username  = $_SESSION['db_user'];
        $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
        
        $sqlLog = "INSERT INTO dbo.APP_USER_LOG (LOG_DATE, USERNAME, [ACTION], MODULE, REFERENCE_NO, DETAILS, IP_ADDRESS) 
                   VALUES (GETDATE(), ?, 'LOGOUT', 'Autentikasi', '-', 'Pengguna keluar dari sistem', ?)";
        $stmtLog = sqlsrv_query($conn, $sqlLog, array($username, $ipAddress));
        if ($stmtLog) {
            sqlsrv_free_stmt($stmtLog);
        }
        sqlsrv_close($conn);
    }
}

// 2. Kosongkan seluruh variabel sesi
$_SESSION = array();

// 3. Bersihkan cookie sesi pada browser jika aktif
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

// 4. Hancurkan sesi server
session_destroy();

// 5. Arahkan pengguna kembali ke halaman login
header("Location: /msii/bea/login.php");
exit();
?>
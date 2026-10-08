<?php
define('LOGIN_PAGE', true);

$errorMsg = "";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$loginUrl = "/msii/bea/login.php";

/*
|--------------------------------------------------------------------------
| LOGOUT HANDLER
|--------------------------------------------------------------------------
*/
if (isset($_GET['logout'])) {
    if (isset($_SESSION['db_user']) && trim($_SESSION['db_user']) !== "") {
        require __DIR__ . "/config/database.php";
        if (isset($conn) && $conn !== false) {
            $username = $_SESSION['db_user'];
            $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
            $sqlLog = "INSERT INTO dbo.APP_USER_LOG (LOG_DATE, USERNAME, [ACTION], MODULE, REFERENCE_NO, DETAILS, IP_ADDRESS) 
                       VALUES (GETDATE(), ?, 'LOGOUT', 'Autentikasi', '-', 'Pengguna keluar dari sistem', ?)";
            $stmtLog = sqlsrv_query($conn, $sqlLog, array($username, $ipAddress));
            if ($stmtLog) { sqlsrv_free_stmt($stmtLog); }
            sqlsrv_close($conn);
        }
    }

    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
    header("Location: " . $loginUrl);
    exit();
}

/*
|--------------------------------------------------------------------------
| PROSES AUTENTIKASI LOGIN
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $username = isset($_POST['username']) ? trim($_POST['username']) : "";
    $password = isset($_POST['password']) ? trim($_POST['password']) : "";

    if ($username == "" || $password == "") {
        $errorMsg = "Username dan password wajib diisi.";
    } else {
        // Gunakan akun default database (plan1) sebagai bridge untuk memeriksa tabel APP_USERS
        $bridge_user = "plan1";
        $bridge_pass = "plan1"; // Sesuaikan password plan1 jika ada password khusus

        $serverName = "192.168.0.4";
        $dbConnOptions = array(
            "Database"     => "msdata",
            "Uid"          => $bridge_user,
            "PWD"          => $bridge_pass,
            "CharacterSet" => "UTF-8"
        );

        $connCheck = sqlsrv_connect($serverName, $dbConnOptions);

        // Jika bridge gagal, coba connect langsung memakai username & password yang diinput
        if ($connCheck === false) {
            $dbConnOptions['Uid'] = $username;
            $dbConnOptions['PWD'] = $password;
            $connCheck = sqlsrv_connect($serverName, $dbConnOptions);
        }

        if ($connCheck !== false) {
            $authSuccess = false;
            $allowedMenus = 'master,ordering,purchasing,inventory,proses,accounting,laporan';

            // 1. Cek kecocokan di tabel dbo.APP_USERS
            $sqlAppUser = "SELECT USERNAME, [PASSWORD], ALLOWED_MENUS FROM dbo.APP_USERS WHERE USERNAME = ?";
            $stmtAppUser = sqlsrv_query($connCheck, $sqlAppUser, array($username));

            if ($stmtAppUser && $rowAppUser = sqlsrv_fetch_array($stmtAppUser, SQLSRV_FETCH_ASSOC)) {
                // Verifikasi password (plaintext atau MD5)
                $dbPassword = trim($rowAppUser['PASSWORD']);
                if ($password === $dbPassword || md5($password) === $dbPassword) {
                    $authSuccess = true;
                    if (!empty($rowAppUser['ALLOWED_MENUS'])) {
                        $allowedMenus = $rowAppUser['ALLOWED_MENUS'];
                    }
                }
                sqlsrv_free_stmt($stmtAppUser);
            } else {
                // Fallback: Jika belum ada di tabel APP_USERS, tetapi login SQL Server-nya valid (misal plan1)
                $authSuccess = true;
            }

            if ($authSuccess) {
                session_regenerate_id(true);

                // Set session user login
                $_SESSION['db_user'] = $username;
                // Simpan kredensial database bridge agar halaman selanjutnya selalu terhubung
                $_SESSION['db_pass'] = ($bridge_user == $username) ? $password : $bridge_pass;
                $_SESSION['active_plant'] = "p1";
                $_SESSION['active_module'] = "it_inventory";
                $_SESSION['allowed_menus'] = $allowedMenus;
                $_SESSION['login_time'] = date('Y-m-d H:i:s');

                // 2. Catat event LOGIN ke dbo.APP_USER_LOG
                $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
                $sqlLog = "INSERT INTO dbo.APP_USER_LOG (LOG_DATE, USERNAME, [ACTION], MODULE, REFERENCE_NO, DETAILS, IP_ADDRESS) 
                           VALUES (GETDATE(), ?, 'LOGIN', 'Autentikasi', '-', 'Pengguna berhasil masuk ke sistem', ?)";
                $stmtLog = sqlsrv_query($connCheck, $sqlLog, array($username, $ipAddress));
                if ($stmtLog) { sqlsrv_free_stmt($stmtLog); }

                sqlsrv_close($connCheck);

                header("Location: /msii/bea/index.php");
                exit();
            } else {
                sqlsrv_close($connCheck);
                $errorMsg = "Login gagal. Password untuk user <b>" . htmlspecialchars($username) . "</b> salah.";
            }

        } else {
            $errorMsg = "Koneksi database gagal. Pastikan database server aktif.";
        }
    }

} else {
    if (
        isset($_SESSION['db_user']) &&
        trim($_SESSION['db_user']) != "" &&
        isset($_SESSION['active_module']) &&
        $_SESSION['active_module'] == "it_inventory"
    ) {
        header("Location: /msii/bea/index.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login IT Inventory Plant 1</title>
    <style>
        html, body { height: 100%; margin: 0; padding: 0; background: #d4d0c8; font-family: Tahoma, Arial, sans-serif; font-size: 12px; }
        .login-wrapper { width: 360px; margin: 100px auto; border: 2px solid #808080; background: #d4d0c8; box-shadow: 3px 3px 8px rgba(0, 0, 0, 0.25); }
        .title-bar { background: #005b8f; color: #ffffff; padding: 7px 9px; font-weight: bold; }
        .login-body { padding: 16px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px; }
        input { width: 100%; height: 25px; border: 1px solid #808080; font-family: Tahoma, Arial, sans-serif; font-size: 12px; box-sizing: border-box; padding: 3px 5px; }
        button { font-family: Tahoma, Arial, sans-serif; font-size: 12px; background: #d4d0c8; border: 2px outset #ffffff; padding: 5px 18px; cursor: pointer; }
        button:active { border: 2px inset #ffffff; }
        .button-row { text-align: right; margin-top: 12px; }
        .error { background: #ffd6d6; border: 1px solid #cc0000; color: #800000; padding: 8px; margin-bottom: 10px; }
        .info { background: #eeeeee; border: 1px solid #808080; padding: 8px; margin-bottom: 10px; line-height: 18px; }
        .footer { font-size: 11px; margin-top: 12px; color: #333333; }
        .back-link { display: inline-block; margin-top: 12px; color: #000080; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        @media screen and (max-width: 420px) { .login-wrapper { width: auto; margin: 40px 15px; } }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="title-bar">LOGIN IT INVENTORY BEA CUKAI - PLANT 1</div>
    <div class="login-body">

        <?php if ($errorMsg != "") { ?>
            <div class="error"><?php echo $errorMsg; ?></div>
        <?php } ?>

        <?php if (isset($_GET['error']) && $_GET['error'] == 'session_expired') { ?>
            <div class="error">Sesi berakhir atau koneksi database terputus. Silakan login kembali.</div>
        <?php } ?>

        <div class="info">
            Server: <b>192.168.0.4</b><br>
            Database: <b>msdata</b><br>
            Modul: <b>IT Inventory Kawasan Berikat</b>
        </div>

        <form method="post" action="/msii/bea/login.php" autocomplete="off">
            <table>
                <tr>
                    <td style="width: 90px;">Username</td>
                    <td><input type="text" name="username" id="username" maxlength="100" autofocus required></td>
                </tr>
                <tr>
                    <td>Password</td>
                    <td><input type="password" name="password" id="password" maxlength="200" required></td>
                </tr>
            </table>

            <div class="button-row">
                <button type="submit">LOGIN</button>
                <button type="reset">RESET</button>
            </div>
        </form>

        <div class="footer">Masukkan username & password yang terdaftar pada sistem IT Inventory.</div>
        <a href="/msii/index.php" class="back-link">&laquo; Kembali ke ERP Portal</a>
    </div>
</div>

</body>
</html>
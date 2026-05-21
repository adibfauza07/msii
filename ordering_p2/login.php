<?php
define('LOGIN_PAGE', true);

$errorMsg = "";
$successLogin = false;

// Jika user klik logout
if (isset($_GET['logout'])) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    session_destroy();
    header("Location: login.php");
    exit();
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $username = isset($_POST['username']) ? trim($_POST['username']) : "";
    $password = isset($_POST['password']) ? $_POST['password'] : "";

    if ($username == "" || $password == "") {
        $errorMsg = "Username dan password wajib diisi.";
    } else {

        $is_login_process = true;
        $temp_username = $username;
        $temp_password = $password;

        require_once __DIR__ . "/../config/db_plant2.php";

        if ($conn !== false) {
            $_SESSION['db_user'] = $username;
            $_SESSION['db_pass'] = $password;
            $_SESSION['active_plant'] = "p2";
            $_SESSION['login_time'] = date('Y-m-d H:i:s');

            session_regenerate_id(true);

            header("Location: dashboard.php");
            exit();
        } else {
            $errorMsg = "Login gagal. Username atau password database salah.";
        }
    }

} else {

    require_once __DIR__ . "/../config/db_plant2.php";

    // Kalau sudah login, langsung ke dashboard
    if (isset($_SESSION['db_user']) && !empty($_SESSION['db_user']) && $conn !== false) {
        header("Location: dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Login Ordering Plant 2</title>

    <style>
        body {
            margin: 0;
            padding: 0;
            background: #d4d0c8;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
        }

        .login-wrapper {
            width: 360px;
            margin: 100px auto;
            border: 2px solid #808080;
            background: #d4d0c8;
            padding: 0;
        }

        .title-bar {
            background: #000080;
            color: #ffffff;
            padding: 6px 8px;
            font-weight: bold;
        }

        .login-body {
            padding: 16px;
        }

        table {
            width: 100%;
        }

        td {
            padding: 4px;
        }

        input {
            width: 100%;
            height: 24px;
            border: 1px solid #808080;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            box-sizing: border-box;
            padding: 2px 4px;
        }

        button {
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            background: #d4d0c8;
            border: 2px outset #ffffff;
            padding: 5px 18px;
            cursor: pointer;
        }

        button:active {
            border: 2px inset #ffffff;
        }

        .button-row {
            text-align: right;
            margin-top: 12px;
        }

        .error {
            background: #ffd6d6;
            border: 1px solid #cc0000;
            color: #800000;
            padding: 8px;
            margin-bottom: 10px;
        }

        .info {
            background: #eeeeee;
            border: 1px solid #808080;
            padding: 8px;
            margin-bottom: 10px;
        }

        .footer {
            font-size: 11px;
            margin-top: 10px;
            color: #333333;
        }
    </style>
</head>

<body>

<div class="login-wrapper">
    <div class="title-bar">LOGIN ORDERING SYSTEM - PLANT 2</div>

    <div class="login-body">

        <?php if ($errorMsg != "") { ?>
            <div class="error"><?php echo htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php } ?>

        <?php if (isset($_GET['error']) && $_GET['error'] == 'session_expired') { ?>
            <div class="error">Session habis atau koneksi database gagal. Silakan login ulang.</div>
        <?php } ?>

        <div class="info">
            Server: <b>192.168.0.9</b><br>
            Database: <b>msdata</b>
        </div>

        <form method="post" action="login.php" autocomplete="off">
            <table>
                <tr>
                    <td style="width:90px;">Username</td>
                    <td>
                        <input type="text" name="username" id="username" autofocus>
                    </td>
                </tr>

                <tr>
                    <td>Password</td>
                    <td>
                        <input type="password" name="password" id="password">
                    </td>
                </tr>
            </table>

            <div class="button-row">
                <button type="submit">LOGIN</button>
                <button type="reset">RESET</button>
            </div>
        </form>

        <div class="footer">
            Gunakan user SQL Server yang memiliki akses ke database msData.
        </div>

    </div>
</div>

</body>
</html>
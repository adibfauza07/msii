<?php
define('LOGIN_PAGE', true);

$errorMsg = "";

/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/
if (isset($_GET['logout'])) {

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

    header("Location: /msii/bea/login.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| URL LOGIN MODUL IT INVENTORY
|--------------------------------------------------------------------------
| Nilai ini dibaca oleh config/db_plant1.php.
*/
$loginUrl = "/msii/bea/login.php";

/*
|--------------------------------------------------------------------------
| PROSES LOGIN
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $username = isset($_POST['username'])
        ? trim($_POST['username'])
        : "";

    $password = isset($_POST['password'])
        ? $_POST['password']
        : "";

    if ($username == "" || $password == "") {

        $errorMsg = "Username dan password wajib diisi.";

    } else {

        /*
        | Variabel ini digunakan oleh db_plant1.php
        | untuk mencoba koneksi dengan user dari form login.
        */
        $is_login_process = true;
        $temp_username = $username;
        $temp_password = $password;

        require __DIR__ . "/../config/db_plant1.php";

        if ($conn !== false) {

            /*
            | Regenerasi ID session setelah autentikasi berhasil.
            */
            session_regenerate_id(true);

            $_SESSION['db_user'] = $username;
            $_SESSION['db_pass'] = $password;
            $_SESSION['active_plant'] = "p1";
            $_SESSION['active_module'] = "it_inventory";
            $_SESSION['login_time'] = date('Y-m-d H:i:s');

            /*
            | Tutup koneksi login.
            | Halaman index akan membuka koneksi kembali.
            */
            sqlsrv_close($conn);

            header("Location: /msii/bea/index.php");
            exit();

        } else {

            $errorMsg = "Login gagal. Username atau password SQL Server salah.";
        }
    }

} else {

    /*
    |--------------------------------------------------------------------------
    | CEK SESSION YANG SUDAH ADA
    |--------------------------------------------------------------------------
    */
    require __DIR__ . "/../config/db_plant1.php";

    if (
        isset($_SESSION['db_user']) &&
        $_SESSION['db_user'] != "" &&
        isset($_SESSION['active_module']) &&
        $_SESSION['active_module'] == "it_inventory" &&
        $conn !== false
    ) {
        sqlsrv_close($conn);

        header("Location: /msii/bea/index.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Login IT Inventory Plant 1</title>

    <style>
        html,
        body {
            height: 100%;
        }

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
            box-shadow: 3px 3px 8px rgba(0, 0, 0, 0.25);
        }

        .title-bar {
            background: #005b8f;
            color: #ffffff;
            padding: 7px 9px;
            font-weight: bold;
        }

        .login-body {
            padding: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 4px;
        }

        input {
            width: 100%;
            height: 25px;
            border: 1px solid #808080;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            box-sizing: border-box;
            padding: 3px 5px;
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
            line-height: 18px;
        }

        .footer {
            font-size: 11px;
            margin-top: 12px;
            color: #333333;
        }

        .back-link {
            display: inline-block;
            margin-top: 12px;
            color: #000080;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        @media screen and (max-width: 420px) {
            .login-wrapper {
                width: auto;
                margin: 40px 15px;
            }
        }
    </style>
</head>

<body>

<div class="login-wrapper">

    <div class="title-bar">
        LOGIN IT INVENTORY BEA CUKAI - PLANT 1
    </div>

    <div class="login-body">

        <?php if ($errorMsg != "") { ?>
            <div class="error">
                <?php
                echo htmlspecialchars(
                    $errorMsg,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </div>
        <?php } ?>

        <?php
        if (
            isset($_GET['error']) &&
            $_GET['error'] == 'session_expired'
        ) {
        ?>
            <div class="error">
                Session habis atau koneksi database gagal.
                Silakan login kembali.
            </div>
        <?php } ?>

        <div class="info">
            Server:
            <b>192.168.0.4</b>
            <br>

            Database:
            <b>msdata</b>
            <br>

            Modul:
            <b>IT Inventory Kawasan Berikat</b>
        </div>

        <form
            method="post"
            action="/msii/bea/login.php"
            autocomplete="off"
        >
            <table>
                <tr>
                    <td style="width: 90px;">
                        Username
                    </td>

                    <td>
                        <input
                            type="text"
                            name="username"
                            id="username"
                            maxlength="100"
                            autofocus
                        >
                    </td>
                </tr>

                <tr>
                    <td>
                        Password
                    </td>

                    <td>
                        <input
                            type="password"
                            name="password"
                            id="password"
                            maxlength="200"
                        >
                    </td>
                </tr>
            </table>

            <div class="button-row">
                <button type="submit">
                    LOGIN
                </button>

                <button type="reset">
                    RESET
                </button>
            </div>
        </form>

        <div class="footer">
            Gunakan akun SQL Server yang mempunyai akses ke
            database msdata.
        </div>

        <a
            href="/msii/index.php"
            class="back-link"
        >
            &laquo; Kembali ke ERP Portal
        </a>

    </div>
</div>

</body>
</html>
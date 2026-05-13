<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

define('LOGIN_PAGE', true);

$error = "";

if (isset($_POST['btnMasuk'])) {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $plant    = isset($_POST['plant']) ? $_POST['plant'] : 'p1';

    if ($username == "" || $password == "") {
        $error = "Username dan password wajib diisi";
    } else {

        $is_login_process = true;
        $temp_username = $username;
        $temp_password = $password;

        if ($plant == 'p2') {
            $serverCheck = "192.168.0.9";
            $_SESSION['active_plant'] = "p2";
        } else {
            $serverCheck = "192.168.0.4";
            $_SESSION['active_plant'] = "p1";
        }

        include "../config/database_p2.php";

        if ($conn === false) {
            $error = "Koneksi database gagal";
        } else {

            $sql = "SELECT TOP 1 *
                    FROM dbo.it_users
                    WHERE username = ?
                    AND password = ?
                    AND status_user = 'active'";

            $params = array($username, $password);

            $query = sqlsrv_query($conn, $sql, $params);

            if ($query === false) {
                die(print_r(sqlsrv_errors(), true));
            }

            $user = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);

            if ($user) {

                session_regenerate_id(true);

                $_SESSION['user_id']      = $user['id'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['department']   = $user['department'];
                $_SESSION['role']         = $user['role'];
                $_SESSION['status_user']  = $user['status_user'];
                $_SESSION['module']       = "IT";

               

                header("Location: dashboard.php");
                exit();

            } else {
                $error = "Username / Password salah atau user tidak aktif";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Budgeting</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .login-box {
            width: 360px;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .login-box h2 {
            text-align: center;
            margin-bottom: 22px;
            color: #0f172a;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 14px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: #334155;
            font-size: 14px;
        }

        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #2563eb;
        }

        button {
            width: 100%;
            padding: 11px;
            background: #2563eb;
            border: none;
            color: white;
            font-size: 15px;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 6px;
        }

        button:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>

<div class="login-box">
    <h2>LOGIN BUDGETING</h2>

    <?php if (!empty($error)) { ?>
        <div class="error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php } ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>Plant</label>
            <select name="plant">
                <option value="p1">Plant 1</option>
                <option value="p2">Plant 2</option>
            </select>
        </div>

        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" required autofocus>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>

        <button type="submit" name="btnMasuk">Masuk</button>
    </form>
</div>

</body>
</html>
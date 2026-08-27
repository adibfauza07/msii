<?php
// Deklarasikan konstanta agar config.php tahu kita sedang di halaman login
define('LOGIN_PAGE', true);
require_once 'config.php';

$error_msg = "";

// Tangkap pesan error dari URL (misal: session_expired)
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'expired') {
        $error_msg = "Sesi Anda telah berakhir atau koneksi terputus. Silakan login kembali.";
    }
}

// Proses autentikasi saat form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (!empty($username) && !empty($password)) {
        // Lakukan uji coba koneksi menggunakan variabel $dbBudget dari config.php
        $testConnectionOptions = array(
            "Database"     => $dbBudget,
            "Uid"          => $username,
            "PWD"          => $password,
            "CharacterSet" => "UTF-8"
        );

        $test_conn = sqlsrv_connect($serverName, $testConnectionOptions);

        if ($test_conn !== false) {
            // Login sukses, simpan kredensial ke dalam Session
            $_SESSION['db_user'] = $username;
            $_SESSION['db_pass'] = $password;
            $_SESSION['login_time'] = date("d-M-Y H:i");

            // Bebaskan resource koneksi uji coba
            sqlsrv_close($test_conn);

            // Arahkan ke halaman utama
            header("Location: index.php");
            exit();
        } else {
            $error_msg = "Login Gagal. Username atau Password salah, atau akses ke database ditolak.";
        }
    } else {
        $error_msg = "Username dan Password harus diisi.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - ERP Budgeting</title>
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
            background-color: #e9ecef;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-box {
            background: #ffffff;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 350px;
        }
        .login-title {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            color: #666;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn-login {
            width: 100%;
            padding: 10px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn-login:hover {
            background-color: #218838;
        }
        .alert {
            padding: 10px;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 13px;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="login-box">
        <div class="login-title">Login Budgeting</div>
        
        <?php if ($error_msg !== ""): ?>
            <div class="alert"><?php echo htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>DB Username</label>
                <input type="text" name="username" class="form-control" required autocomplete="off" autofocus>
            </div>
            <div class="form-group">
                <label>DB Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn-login">Login</button>
        </form>
    </div>

</body>
</html>
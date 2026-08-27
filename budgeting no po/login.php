<?php
// File: budgeting/login.php

// Definisikan konstanta agar config.php tahu ini halaman login
define('LOGIN_PAGE', true);
require_once 'config.php';

// Jika user ternyata sudah login (koneksi valid), langsung arahkan ke index
if ($conn !== false) {
    header("Location: index.php");
    exit();
}

$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil input dan sanitasi dasar
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (!empty($username) && !empty($password)) {
        
        // Coba koneksi menggunakan kredensial dari form
        $testOptions = array(
            "Database"     => $databaseName, // Variabel dari config.php
            "Uid"          => $username,
            "PWD"          => $password,
            "CharacterSet" => "UTF-8"
        );

        $test_conn = sqlsrv_connect($serverName, $testOptions);

        if ($test_conn !== false) {
            // Login berhasil, simpan ke session
            $_SESSION['db_user'] = $username;
            $_SESSION['db_pass'] = $password;
            
            sqlsrv_close($test_conn);

            // Arahkan ke dashboard budgeting
            header("Location: index.php");
            exit();
        } else {
            $error_message = "Login Gagal. Username atau Password salah.";
        }
    } else {
        $error_message = "Harap isi Username dan Password.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Modul Budgeting</title>
    <style>
        body { font-family: Arial, sans-serif; background: #e9ecef; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: #fff; padding: 30px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 300px; }
        .login-box h2 { margin-top: 0; text-align: center; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #666; }
        .form-group input { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        .btn { width: 100%; padding: 10px; background: #28a745; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn:hover { background: #218838; }
        .alert { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; font-size: 14px; }
    </style>
</head>
<body>

<div class="login-box">
    <h2>Login Budgeting</h2>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] == 'expired'): ?>
        <div class="alert">Sesi Anda telah berakhir, silakan login kembali.</div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="username">DB Username</label>
            <input type="text" name="username" id="username" required autocomplete="off">
        </div>
        <div class="form-group">
            <label for="password">DB Password</label>
            <input type="password" name="password" id="password" required>
        </div>
        <button type="submit" class="btn">Login</button>
    </form>
</div>

</body>
</html>
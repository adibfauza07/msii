<?php
// FILE: /msii/4m/login.php
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Jika sudah login, langsung ke dashboard
if (isset($_SESSION['erp_user'])) {
    header("Location: dashboard_4m.php");
    exit();
}

$error = "";
if (isset($_POST['btnLog'])) {
    $u = trim($_POST['username']);
    $p = $_POST['password'];
    $s = $_POST['server']; // Pilihan Plant/Server

    // Opsi koneksi untuk tes login
    $connectionOptions = array(
        "Database" => "msdata",
        "Uid" => $u,
        "PWD" => $p,
        "CharacterSet" => "UTF-8"
    );

    $conn = sqlsrv_connect($s, $connectionOptions);

    if ($conn) {
        // Simpan ke Session untuk digunakan di Auth.php dan database.php
        $_SESSION['erp_user'] = $u;
        $_SESSION['erp_pass'] = $p;
        $_SESSION['server_sql'] = $s;
        
        // Simpan active plant untuk keperluan filter data
        $_SESSION['active_plant'] = ($s == '192.168.0.9') ? 'p2' : 'p1';

        header("Location: dashboard_4m.php");
        exit();
    } else {
        $error = "Login Gagal. Periksa User/Pass atau koneksi Server.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>4M System - Login</title>
    <!-- Load CDN Bootstrap 5 dan Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { 
            background: #f4f7f6;
            height: 100vh; 
            display: flex; align-items: center; justify-content: center; 
            margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-box {
            width: 100%; max-width: 420px;
            background: #fff; padding: 40px;
            border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .brand-logo {
            font-size: 24px; font-weight: 800; color: #2c3e50;
            text-align: center; margin-bottom: 30px;
        }
        .brand-logo span { color: #8b5cf6; /* Warna Ungu 4M */ } 
        
        .btn-primary { background: #8b5cf6; border: none; padding: 12px; font-weight: 600; transition: background 0.3s; color: #fff; }
        .btn-primary:hover { background: #7c3aed; color: #fff; }
        
        /* Menggunakan background-color agar icon panah SVG Bootstrap tidak tertimpa */
        .form-control { padding: 12px; background-color: #f8f9fa; border: 1px solid #eee; }
        .form-select { background-color: #f8f9fa; border: 1px solid #eee; padding-top: 12px; padding-bottom: 12px; }
        
        .form-control:focus, .form-select:focus { box-shadow: none; border-color: #8b5cf6; background-color: #fff; }
    </style>
</head>
<body>

<div class="login-box">
    <div class="brand-logo">
        4M CHANGE <span>SYSTEM</span>
        <div style="font-size: 12px; font-weight: 400; color: #158806;">PT IMC TEKNO INDONESIA</div>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger d-flex align-items-center small" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-2"></i>
            <div><?php echo $error; ?></div>
        </div>
    <?php endif; ?>

    <?php if(isset($_GET['timeout']) && $_GET['timeout'] == 1): ?>
        <div class="alert alert-warning d-flex align-items-center small" role="alert">
            <i class="bi bi-clock-history me-2"></i>
            <div>Sesi habis. Silakan login kembali.</div>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label class="form-label small fw-bold">Pilih Plant / Server</label>
            <select name="server" class="form-select shadow-sm" required>
                <option value="192.168.0.4">PLANT 1</option>
                <option value="192.168.0.9">PLANT 2</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-bold">Username</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-person"></i></span>
                <input type="text" name="username" class="form-control border-start-0 shadow-sm" placeholder="Username SQL Server..." required autofocus>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label small fw-bold">Password</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" class="form-control border-start-0 shadow-sm" placeholder="••••••••" required>
            </div>
        </div>

        <div class="d-grid gap-2">
            <button type="submit" name="btnLog" class="btn btn-primary fw-bold py-2">
                LOGIN KE SISTEM <i class="bi bi-box-arrow-in-right ms-2"></i>
            </button>
            
            <a href="http://192.168.0.9:81/msii/" class="btn btn-outline-dark fw-bold py-2">
                <i class="bi bi-house-door-fill me-2"></i> KEMBALI KE MENU UTAMA
            </a>
        </div>
    </form>

    <div class="text-center mt-4 small text-muted">
        &copy; <?php echo date('Y'); ?> 4M Change Management System
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
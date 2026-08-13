<?php
// FILE: /msii/pe/login.php
// Matikan error display agar tampilan rapi, tapi log error tetap jalan
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Jika user sudah login sebelumnya, langsung arahkan ke Dashboard
if (isset($_SESSION['db_user']) && isset($_SESSION['active_plant'])) {
    header("Location: dashboard_pe.php");
    exit();
}

$error = "";

// --- PROSES LOGIN ---
if (isset($_POST['btnMasuk'])) {
    $temp_username = trim($_POST['username']);
    $temp_password = $_POST['password'];
    $selected_plant = $_POST['plant']; // Mengambil pilihan Plant (p1 atau p2)

    // Tentukan IP Server berdasarkan pilihan dropdown
    if ($selected_plant == 'p2') {
        $serverCheck = "192.168.0.9"; // Plant 2
    } else {
        $serverCheck = "192.168.0.4"; // Plant 1 (Default)
    }

    $databaseName = "msData"; // Pastikan nama DB sama di kedua server

    // Coba koneksi menggunakan driver sqlsrv (agar konsisten dengan halaman lain)
    $connectionOptions = array(
        "Database" => $databaseName,
        "Uid" => $temp_username,
        "PWD" => $temp_password,
        "CharacterSet" => "UTF-8"
    );

    // Tes Koneksi
    $conn = sqlsrv_connect($serverCheck, $connectionOptions);

    if ($conn) {
        // --- LOGIN SUKSES ---
        $_SESSION['db_user'] = $temp_username;
        $_SESSION['db_pass'] = $temp_password;
        $_SESSION['active_plant'] = $selected_plant; // Simpan pilihan Plant (p1/p2)

        header("Location: dashboard_pe.php");
        exit();
    } else {
        // --- LOGIN GAGAL ---
        // Ambil pesan error detail (opsional untuk debugging)
        $errors = sqlsrv_errors();
        $msg = "Koneksi Gagal / User Salah.";
        if($errors != null) {
            $msg .= " (Server: $serverCheck)";
        }
        $error = "Login Gagal! Pastikan Username/Password benar dan terdaftar di Plant yang dipilih.<br><small>$msg</small>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Product Engineering System - Login</title>
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
        .brand-logo span { color: #e74c3c; }
        .btn-primary { background: #2c3e50; border: none; padding: 12px; font-weight: 600; }
        .btn-primary:hover { background: #1a252f; }
        .form-control { padding: 12px; background: #f8f9fa; border: 1px solid #eee; }
    </style>
</head>
<body>

<div class="login-box">
    <div class="brand-logo">
        PRODUCT ENGINEERING <span>SYSTEM</span>
        <div style="font-size: 12px; font-weight: 400; color: #158806;">PT IMC TEKNO INDONESIA</div>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger d-flex align-items-center small" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-2"></i>
            <div><?php echo $error; ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label class="form-label small fw-bold">Pilih Plant</label>
            <select name="plant" class="form-select shadow-sm" required>
                <option value="p1">PLANT 1</option>
                <option value="p2">PLANT 2</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-bold">Username</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-person"></i></span>
                <input type="text" name="username" class="form-control border-start-0 shadow-sm" placeholder="Username..." required autofocus>
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
            <button type="submit" name="btnMasuk" class="btn btn-primary fw-bold py-2">
                LOGIN KE SISTEM <i class="bi bi-box-arrow-in-right ms-2"></i>
            </button>
            
            <a href="http://192.168.0.9:81/msii/" class="btn btn-outline-dark fw-bold py-2">
                <i class="bi bi-house-door-fill me-2"></i> KEMBALI KE MENU UTAMA
            </a>
        </div>
    </form>

    <div class="text-center mt-4 small text-muted">
        &copy; <?php echo date('Y'); ?> Product Engineering Department
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
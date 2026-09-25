<?php
// FILE: /msii/mtn/login.php
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Jika user sudah login sebelumnya, langsung arahkan ke Dashboard MTN
if (isset($_SESSION['erp_user']) && isset($_SESSION['erp_role'])) {
    header("Location: dashboard_mtn.php");
    exit();
}

$error = "";

// --- PROSES LOGIN SAAT FORM DI-SUBMIT ---
if (isset($_POST['btnMasuk'])) {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];
    $u    = strtolower($user);
    $selected_plant = $_POST['plant']; // Mengambil pilihan Plant (p1 atau p2)

    // Menentukan IP Server & Role berdasarkan pilihan dropdown
    if ($selected_plant == 'p2') {
        $serverName = "192.168.0.9"; // Plant 2
        $role       = 'p2';
    } else {
        $serverName = "192.168.0.4"; // Plant 1 (Default)
        $role       = 'p1';
    }

    // Override server/role untuk user khusus (Admin)
    if ($u == 'admin') {
        $serverName = '192.168.0.9';
        $role       = 'admin';
    }

    // Coba koneksi menggunakan driver sqlsrv
    $connectionInfo = array(
        "Database"     => "msdata",
        "Uid"          => $user,
        "PWD"          => $pass,
        "CharacterSet" => "UTF-8"
    );

    // Tes Koneksi
    $testConn = sqlsrv_connect($serverName, $connectionInfo);

    if ($testConn) {
        // --- LOGIN SUKSES ---
        sqlsrv_close($testConn); // Tutup koneksi tes

        // Simpan sesi
        $_SESSION['erp_user']   = $user;
        $_SESSION['erp_pass']   = $pass;
        $_SESSION['server_sql'] = $serverName;
        $_SESSION['erp_role']   = $role;
        $_SESSION['last_activity'] = time();

        // REDIRECT SESUAI ROLE / DIVISI
        // Khusus masuk ke Dashboard MTN
        if ($role === 'p1' || $role === 'p2' || $u == 'mtn') {
            header("Location: dashboard_mtn.php");
            exit;
        }
        
        // Routing untuk divisi lainnya
        if ($u == "admin") header("Location: /msii/admin/dashboard_admin.php");
        elseif ($u == "marketing") header("Location: /msii/marketing/dashboard_marketing.php");
        elseif ($u == "sales") header("Location: /msii/sales/dashboard_sales.php");
        elseif ($u == "inventory") header("Location: /msii/inventory/dashboard_inv.php");
        elseif ($u == "purchasing") header("Location: /msii/purchasing/dashboard_purchasing.php");
        elseif ($u == "production") header("Location: /msii/production/dashboard_production.php");
        elseif ($u == "qc") header("Location: /msii/qc/dashboard_qc.php");
        elseif ($u == "pe") header("Location: /msii/pe/dashboard_pe.php");
        else {
            $error = "User tidak memiliki akses ke dashboard ini!";
        }
        exit();
        
    } else {
        // --- LOGIN GAGAL ---
        $errors = sqlsrv_errors();
        $msg = "Koneksi Gagal / User Salah.";
        if($errors != null) {
            $msg .= " (Server: $serverName)";
        }
        $error = "Login Gagal! Pastikan Username/Password benar di Plant yang dipilih.<br><small>$msg</small>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MTN System - Login</title>
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
        .brand-logo span { color: #e74c3c; }
        .btn-primary { background: #2c3e50; border: none; padding: 12px; font-weight: 600; transition: background 0.3s; }
        .btn-primary:hover { background: #1a252f; }
        
        /* PERBAIKAN: Gunakan background-color, bukan background, agar SVG panah Bootstrap tidak hilang */
        .form-control { padding: 12px; background-color: #f8f9fa; border: 1px solid #eee; }
        .form-select { background-color: #f8f9fa; border: 1px solid #eee; padding-top: 12px; padding-bottom: 12px; }
        
        .form-control:focus, .form-select:focus { box-shadow: none; border-color: #2c3e50; background-color: #fff; }
    </style>
</head>
<body>

<div class="login-box">
    <div class="brand-logo">
        MAINTENANCE <span>SYSTEM</span>
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
        &copy; <?php echo date('Y'); ?> Maintenance System
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
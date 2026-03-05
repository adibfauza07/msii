<?php
// FILE: login.php
// Matikan error display agar tampilan rapi, tapi log error tetap jalan
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Jika user sudah login sebelumnya, langsung arahkan ke Dashboard
if (isset($_SESSION['db_user']) && isset($_SESSION['active_plant'])) {
    header("Location: dashboard_wh.php");
    exit();
}

$error = "";

// --- PROSES LOGIN ---masuk1996
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

        header("Location: dashboard_wh.php");
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
    <title>Login Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f0f2f5; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card-login { width: 100%; max-width: 400px; border: none; shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .card-header { background: #0d6efd; color: white; text-align: center; padding: 20px; }
    </style>
</head>
<body>

<div class="card card-login shadow">
    <div class="card-header">
        <h4 class="mb-1 fw-bold">WAREHOUSE SYSTEM</h4>
        <small class="text-white-50">Silakan Login & Pilih Plant</small>
    </div>
    <div class="card-body p-4 bg-white">
        
        <?php if($error): ?>
            <div class="alert alert-danger py-2 mb-3 small" role="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">PILIH LOKASI (DATABASE)</label>
                <select name="plant" class="form-select shadow-sm border-primary" required>
                    <option value="p1">PLANT 1</option>
                    <option value="p2">PLANT 2</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold small text-muted">USERNAME</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="User Database" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold small text-muted">PASSWORD</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="•••••••" required>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" name="btnMasuk" class="btn btn-primary fw-bold shadow-sm">
                    MASUK SISTEM <i class="bi bi-box-arrow-in-right"></i>
                </button>
            </div>
        </form>
    </div>
    <div class="card-footer bg-light text-center small text-muted py-3">
        &copy; <?php echo date('Y'); ?> Warehouse Management
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
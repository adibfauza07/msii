<?php
// FILE: exim/login.php
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Jika sudah login, langsung ke dashboard exim
if (isset($_SESSION['db_user']) && isset($_SESSION['active_plant'])) {
    header("Location: dashboard_exim.php");
    exit();
}

$error = "";

// --- PROSES LOGIN ---
if (isset($_POST['btnMasuk'])) {
    $temp_username = trim($_POST['username']);
    $temp_password = $_POST['password'];
    $selected_plant = $_POST['plant']; 

    // Konfigurasi Server (Menyamakan dengan logic inventory kamu)
    if ($selected_plant == 'p2') {
        $serverCheck = "192.168.0.9"; // Plant 2
    } else {
        $serverCheck = "192.168.0.4"; // Plant 1
    }

    $databaseName = "msData";
    
    // Mencoba koneksi ke SQL Server
    $connectionOptions = array(
        "Database" => $databaseName,
        "Uid" => $temp_username,
        "PWD" => $temp_password,
        "CharacterSet" => "UTF-8"
    );

    $conn = sqlsrv_connect($serverCheck, $connectionOptions);

    if ($conn) {
        // Simpan ke Session
        $_SESSION['db_user'] = $temp_username;
        $_SESSION['db_pass'] = $temp_password;
        $_SESSION['active_plant'] = $selected_plant;
        $_SESSION['server_sql'] = $serverCheck;

        header("Location: dashboard_exim.php");
        exit();
    } else {
        $error = "Login Gagal! Username/Password salah atau server tidak terjangkau.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EXIM System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #1a1a2e; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { width: 100%; max-width: 400px; border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .card-header { background: #16213e; color: white; border-radius: 15px 15px 0 0 !important; padding: 25px; text-align: center; }
        .btn-primary { background: #e94560; border: none; }
        .btn-primary:hover { background: #c62a48; }
    </style>
</head>
<body>

<div class="card login-card">
    <div class="card-header">
        <h4 class="mb-0 fw-bold"><i class="bi bi-ship"></i> EXIM MODULE</h4>
        <small class="text-white-50">Sistem Integrasi CEISA 4.0</small>
    </div>
    <div class="card-body p-4 bg-light">
        <?php if($error): ?>
            <div class="alert alert-danger small"><i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-bold small text-muted">LOKASI PLANT</label>
                <select name="plant" class="form-select border-0 shadow-sm">
                    <option value="p1">PLANT 1 (192.168.0.4)</option>
                    <option value="p2">PLANT 2 (192.168.0.9)</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold small text-muted">USERNAME</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-person text-muted"></i></span>
                    <input type="text" name="username" class="form-control border-start-0 shadow-sm" placeholder="User Database" required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold small text-muted">PASSWORD</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control border-start-0 shadow-sm" placeholder="•••••••" required>
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" name="btnMasuk" class="btn btn-primary fw-bold p-2 shadow">
                    MASUK EXIM <i class="bi bi-box-arrow-in-right"></i>
                </button>
            </div>
        </form>
    </div>
    <div class="card-footer bg-white text-center small text-muted py-3 rounded-bottom">
        &copy; <?php echo date('Y'); ?> ERP System - Exim Dept.
    </div>
</div>

</body>
</html>
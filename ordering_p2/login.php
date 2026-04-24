<?php
// Aktifkan error reporting sementara untuk mendeteksi penyebab layar blank
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Jika sudah login, arahkan ke dashboard internal ordering
if (isset($_SESSION['db_user']) && $_SESSION['active_plant'] == 'p2') {
    header("Location: dashboard.php");
    exit();
}

$error = "";
if (isset($_POST['btnMasuk'])) {
    $u = trim($_POST['username']);
    $p = $_POST['password'];
    
    // Koneksi ke Database Plant 2
    $serverName = "192.168.0.9"; 
    $connectionOptions = array(
        "Database" => "msData",
        "Uid" => $u,
        "PWD" => $p,
        "CharacterSet" => "UTF-8"
    );

    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if ($conn) {
        $_SESSION['db_user'] = $u;
        $_SESSION['db_pass'] = $p;
        $_SESSION['erp_user'] = $u; // Untuk keperluan Auth.php
        $_SESSION['active_plant'] = 'p2';
        $_SESSION['erp_role'] = 'staff_p2'; // Role default
        $_SESSION['last_activity'] = time();
        
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Gagal konek ke Server Plant 2! Cek User & Password SQL Server.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Ordering Plant 2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f8f9fa; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card-login { width: 100%; max-width: 400px; border-radius: 15px; border: none; }
    </style>
</head>
<body>
<div class="card card-login shadow">
    <div class="card-header bg-success text-white text-center py-3">
        <h5 class="mb-0 fw-bold">ORDERING SYSTEM P2</h5>
        <small>PT. IMCTekno Indonesia</small>
    </div>
    <div class="card-body p-4">
        <?php if($error): ?>
            <div class="alert alert-danger py-2 small"><?= $error ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold">USERNAME</label>
                <input type="text" name="username" class="form-control" required placeholder="User DB Plant 2">
            </div>
            <div class="mb-4">
                <label class="form-label small fw-bold">PASSWORD</label>
                <input type="password" name="password" class="form-control" required placeholder="••••••">
            </div>
            <button type="submit" name="btnMasuk" class="btn btn-success w-100 fw-bold">LOG IN <i class="bi bi-box-arrow-in-right"></i></button>
        </form>
    </div>
</div>
</body>
</html>
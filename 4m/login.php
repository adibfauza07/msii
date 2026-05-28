<?php
session_start();
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
    <title>Login 4M Change System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { width: 100%; max-width: 400px; border-radius: 15px; overflow: hidden; }
        .card-header { background: #8b5cf6; color: white; text-align: center; padding: 20px; border: none; }
    </style>
</head>
<body>
    <div class="card login-card shadow">
        <div class="card-header">
            <h4 class="mb-0">4M CHANGE SYSTEM</h4>
            <small>Integrated Management System</small>
        </div>
        <div class="card-body p-4">
            <?php if($error): ?>
                <div class="alert alert-danger small"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="small fw-bold">SERVER / PLANT</label>
                    <select name="server" class="form-select">
                        <option value="192.168.0.4">PLANT 1</option>
                        <option value="192.168.0.9">PLANT 2</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold">USERNAME</label>
                    <input type="text" name="username" class="form-control" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="small fw-bold">PASSWORD</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" name="btnLog" class="btn btn-primary w-100 fw-bold" style="background: #8b5cf6; border: none;">
                    LOGIN SYSTEM
                </button>
            </form>
        </div>
    </div>
</body>
</html>
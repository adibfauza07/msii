<?php
session_start();

// Jika sudah login, arahkan ke index
if (isset($_SESSION['db_user'])) {
    header("Location: index.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $temp_username = $_POST['username'];
    $temp_password = $_POST['password'];
    $serverCheck = $_POST['plant']; // Pilihan Plant 1 atau Plant 2
    
    $is_login_process = true; // Penanda untuk database_p1.php
    define('LOGIN_PAGE', true);
    require_once 'config/database_p1.php'; // Sesuaikan path

    if ($conn) {
        // Login Sukses
        $_SESSION['db_user'] = $temp_username;
        $_SESSION['db_pass'] = $temp_password;
        $_SESSION['active_plant'] = ($serverCheck == '192.168.0.4') ? 'p1' : 'p2';
        
        header("Location: index.php");
        exit();
    } else {
        $error = "Username, Password, atau Plant salah!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h4 class="text-center mb-4">Login System</h4>
                        <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label>Username (Database User)</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Pilih Plant</label>
                                <select name="plant" class="form-select">
                                    <option value="192.168.0.4">Plant 1</option>
                                    <option value="192.168.0.9">Plant 2</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
session_start();

$error = "";

// =====================================
// PROSES LOGIN SAAT FORM DI-SUBMIT
// =====================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);
    $u    = strtolower($user);

    // ===========================================
    // PILIH SERVER & ROLE BERDASARKAN USER LOGIN
    // ===========================================
    switch ($u) {
        case 'plan1':
            $serverName = '192.168.0.4';
            $role       = 'p1';   // user plant 1
            break;

        case 'plan2':
            $serverName = '192.168.0.9';
            $role       = 'p2';   // user plant 2
            break;

        case 'admin':
            $serverName = '192.168.0.9';
            $role       = 'admin';
            break;

        default:
            $serverName = '192.168.0.9';
            $role       = 'other';
            break;
    }

    // TEST LOGIN KE SQL SERVER
    $connectionInfo = [
        "Database"     => "msdata",
        "Uid"          => $user,
        "PWD"          => $pass,
        "CharacterSet" => "UTF-8"
    ];

    $testConn = @sqlsrv_connect($serverName, $connectionInfo);

    if ($testConn === false) {
        $error = "Login gagal! Username atau password salah.";
    } else {

        // Jika login berhasil → tutup koneksi test
        sqlsrv_close($testConn);

        // SIMPAN USER & INFO LAIN KE SESSION
        $_SESSION['erp_user']   = $user;
        $_SESSION['erp_pass']   = $pass;
        $_SESSION['server_sql'] = $serverName;
        $_SESSION['erp_role']   = $role;
        $_SESSION['last_activity'] = time();

        // REDIRECT SESUAI ROLE / DIVISI
        if ($role === 'p1' || $role === 'p2') {
            header("Location: /msii/mtn/dashboard_mtn.php");
            exit;
        }

        if ($u == "admin") {
            header("Location: /msii/admin/dashboard_admin.php");
            exit;
        }
        if ($u == "marketing") {
            header("Location: /msii/marketing/dashboard_marketing.php");
            exit;
        }
        if ($u == "sales") {
            header("Location: /msii/sales/dashboard_sales.php");
            exit;
        }
        if ($u == "inventory") {
            header("Location: /msii/inventory/dashboard_inv.php");
            exit;
        }
        if ($u == "purchasing") {
            header("Location: /msii/purchasing/dashboard_purchasing.php");
            exit;
        }
        if ($u == "production") {
            header("Location: /msii/production/dashboard_production.php");
            exit;
        }
        if ($u == "qc") {
            header("Location: /msii/qc/dashboard_qc.php");
            exit;
        }
        if ($u == "pe") {
            header("Location: /msii/pe/dashboard_pe.php");
            exit;
        }
        if ($u == "mtn") {
            header("Location: /msii/mtn/dashboard_mtn.php");
            exit;
        }

        // Jika username tidak ada routingnya
        $error = "User tidak memiliki dashboard!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Login ERP | IMC Tekno</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="../assets/bootstrap.min.css" rel="stylesheet">

<style>
body {
  height: 100vh;
  margin: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #0d6efd 0%, #1a73e8 50%, #0049b7 100%);
  font-family: "Segoe UI", sans-serif;
}
.login-card {
  width: 360px;
  padding: 30px;
  border-radius: 15px;
  background: rgba(255,255,255,0.15);
  box-shadow: 0 8px 25px rgba(0,0,0,0.25);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255,255,255,0.25);
  color: white;
}
.login-logo {
  width: 90px;
  border-radius: 10px;
  margin-bottom: 15px;
}
.form-control {
  background: rgba(255,255,255,0.25);
  border: none;
  color: #fff;
}
.form-control:focus {
  background: rgba(255,255,255,0.35);
  box-shadow: none;
  outline: none;
  color: #fff;
}
::placeholder { color: #e5e5e5 !important; }
.btn-login {
  background: #ffc107;
  border: none;
  color: #000;
  font-weight: bold;
  padding: 10px;
  border-radius: 8px;
}
.alert-custom {
  background: rgba(255,0,0,0.6);
  color: white;
  border-radius: 8px;
  padding: 10px;
}
</style>
</head>


<body>

<div class="login-card text-center">
  <img src="../mtn/logo_imc.jpg" class="login-logo" alt="Logo">
  <h4 class="fw-bold mb-3">Login ERP IMC</h4>

  <?php if($error != "") { ?>
    <div class="alert-custom mb-3"><?= $error ?></div>
  <?php } ?>

  <?php if(isset($_GET['timeout']) && $_GET['timeout'] == 1) { ?>
    <div class="alert-custom mb-3">Sesi habis. Silakan login kembali.</div>
  <?php } ?>

  <form method="post">
      <input type="text" name="username" class="form-control mb-3" placeholder="Username SQL Server" required>
      <input type="password" name="password" class="form-control mb-4" placeholder="Password" required>
      <button class="btn-login w-100">Login</button>
  </form>

</div>

</body>
</html>

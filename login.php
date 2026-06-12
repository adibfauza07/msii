<?php
session_start();

$error = "";

/*
    Jika sudah login, redirect sesuai role/plant
*/
if (isset($_SESSION['erp_user']) && isset($_SESSION['erp_role'])) {

    if ($_SESSION['erp_role'] == 'p1') {
        header("Location: /msii/ppic1/dashboard_ppic.php");
        exit;
    }

    if ($_SESSION['erp_role'] == 'p2') {
        header("Location: /msii/ppic/dashboard_ppic.php");
        exit;
    }

    if ($_SESSION['erp_role'] == 'admin') {
        header("Location: /msii/admin/dashboard_admin.php");
        exit;
    }
}

/*
    PROSES LOGIN
*/
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);
    $u    = strtolower($user);

    /*
        Mapping:
        plan1 = Plant 1 = 192.168.0.4 = /msii/ppic1/
        plan2 = Plant 2 = 192.168.0.9 = /msii/ppic/
    */
    switch ($u) {

        case 'plan1':
            $serverName = '192.168.0.4';
            $role       = 'p1';
            $activePlant = 'p1';
            $redirectUrl = '/msii/ppic1/dashboard_ppic.php';
            break;

        case 'plan2':
            $serverName = '192.168.0.9';
            $role       = 'p2';
            $activePlant = 'p2';
            $redirectUrl = '/msii/ppic/dashboard_ppic.php';
            break;

        case 'admin':
            $serverName = '192.168.0.9';
            $role       = 'admin';
            $activePlant = 'p2';
            $redirectUrl = '/msii/admin/dashboard_admin.php';
            break;

        case 'marketing':
            $serverName = '192.168.0.9';
            $role       = 'marketing';
            $activePlant = 'p2';
            $redirectUrl = '/msii/marketing/dashboard_marketing.php';
            break;

        case 'sales':
            $serverName = '192.168.0.9';
            $role       = 'sales';
            $activePlant = 'p2';
            $redirectUrl = '/msii/sales/dashboard_sales.php';
            break;

        case 'inventory':
            $serverName = '192.168.0.9';
            $role       = 'inventory';
            $activePlant = 'p2';
            $redirectUrl = '/msii/inventory/dashboard_inv.php';
            break;

        case 'purchasing':
            $serverName = '192.168.0.9';
            $role       = 'purchasing';
            $activePlant = 'p2';
            $redirectUrl = '/msii/purchasing/dashboard_purchasing.php';
            break;

        case 'production':
            $serverName = '192.168.0.9';
            $role       = 'production';
            $activePlant = 'p2';
            $redirectUrl = '/msii/production/dashboard_production.php';
            break;

        case 'qc':
            $serverName = '192.168.0.9';
            $role       = 'qc';
            $activePlant = 'p2';
            $redirectUrl = '/msii/qc/dashboard_qc.php';
            break;

        case 'pe':
            $serverName = '192.168.0.9';
            $role       = 'pe';
            $activePlant = 'p2';
            $redirectUrl = '/msii/pe/dashboard_pe.php';
            break;

        case 'mtn':
            $serverName = '192.168.0.9';
            $role       = 'mtn';
            $activePlant = 'p2';
            $redirectUrl = '/msii/mtn/dashboard_mtn.php';
            break;

        case 'mkt01':
            $serverName = '192.168.0.9';
            $role       = 'mkt01';
            $activePlant = 'p2';
            $redirectUrl = '/msii/mtn/dashboard_inv.php';
            break;

        default:
            $serverName = '192.168.0.9';
            $role       = 'other';
            $activePlant = 'p2';
            $redirectUrl = '';
            break;
    }

    /*
        TEST LOGIN KE SQL SERVER
    */
    $connectionInfo = array(
        "Database"     => "msData",
        "Uid"          => $user,
        "PWD"          => $pass,
        "CharacterSet" => "UTF-8"
    );

    $testConn = @sqlsrv_connect($serverName, $connectionInfo);

    if ($testConn === false) {

        $error = "Login gagal! Username atau password salah.";

    } else {

        sqlsrv_close($testConn);

        /*
            SESSION LAMA
        */
        $_SESSION['erp_user']      = $user;
        $_SESSION['erp_pass']      = $pass;
        $_SESSION['server_sql']    = $serverName;
        $_SESSION['erp_role']      = $role;
        $_SESSION['last_activity'] = time();

        /*
            SESSION BARU UNTUK database_ppic.php
        */
        $_SESSION['db_user']       = $user;
        $_SESSION['db_pass']       = $pass;
        $_SESSION['active_plant']  = $activePlant;
        $_SESSION['active_server'] = $serverName;

        /*
            REDIRECT
        */
        if ($redirectUrl != '') {
            header("Location: " . $redirectUrl);
            exit;
        }

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
<link href="assets/bootstrap.min.css" rel="stylesheet">

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

::placeholder {
  color: #e5e5e5 !important;
}

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

  <img src="logo_imc.jpg" class="login-logo" alt="Logo">

  <h4 class="fw-bold mb-3">Login ERP IMC</h4>

  <?php if ($error != "") { ?>
    <div class="alert-custom mb-3">
        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  <?php } ?>

  <?php if (isset($_GET['timeout']) && $_GET['timeout'] == 1) { ?>
    <div class="alert-custom mb-3">
        Sesi habis. Silakan login kembali.
    </div>
  <?php } ?>

  <form method="post">
      <input
        type="text"
        name="username"
        class="form-control mb-3"
        placeholder="Username SQL Server"
        required>

      <input
        type="password"
        name="password"
        class="form-control mb-4"
        placeholder="Password"
        required>

      <button class="btn-login w-100" type="submit">
        Login
      </button>
  </form>

</div>

</body>
</html>
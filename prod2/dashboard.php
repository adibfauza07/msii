<?php
if (session_id() == "") {
    session_start();
}

require_once __DIR__ . "/../config/database_prod.php";

if ($conn === false) {
    header("Location: login.php?error=session_expired");
    exit();
}

$dbUser = isset($_SESSION["db_user"]) ? $_SESSION["db_user"] : "";
$loginTime = isset($_SESSION["login_time"]) ? $_SESSION["login_time"] : "";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PRODUCTION System - Plant 2</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Theme style (AdminLTE) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

    <style>
        /* Hilangkan scroll pada body utama karena menggunakan iframe */
        html, body {
            height: 100%;
            overflow: hidden; 
        }
        
        /* Agar iframe mengisi seluruh sisa layar di bawah navbar */
        .content-wrapper {
            height: calc(100vh - 57px); /* 57px adalah perkiraan tinggi navbar AdminLTE */
            display: flex;
            flex-direction: column;
            padding: 0;
            margin: 0;
            background: #f4f6f9;
        }

        #mainFrame {
            flex: 1;
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>
<body class="hold-transition layout-top-nav">
<div class="wrapper">

  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand-md navbar-dark bg-primary">
    <div class="container-fluid">
      <a href="dashboard_home.php" target="mainFrame" class="navbar-brand">
        <i class="fas fa-industry brand-image mt-1 shadow-sm" style="opacity: .8"></i>
        <span class="brand-text font-weight-light"><b>PRODUCTION</b> Plant 2</span>
      </a>

      <button class="navbar-toggler order-1" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <!-- Panel Menu Pindah Ke Atas -->
      <div class="collapse navbar-collapse order-3" id="navbarCollapse">
        <ul class="navbar-nav">
          <li class="nav-item">
            <a href="dashboard_home.php" target="mainFrame" class="nav-link menu-link active">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
          </li>
          
          <li class="nav-item">
            <a href="report_kapasitas_mesin.PHP" target="mainFrame" class="nav-link menu-link">
                <i class="fas fa-calendar-alt"></i> Schedule
            </a>
          </li>

          <li class="nav-item dropdown">
            <a id="dropdownSubMenu1" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" class="nav-link dropdown-toggle">
                <i class="fas fa-edit"></i> Production Entry
            </a>
            <ul class="dropdown-menu border-0 shadow" aria-labelledby="dropdownSubMenu1">
              <li><a href="label_material_input.php" target="mainFrame" class="dropdown-item menu-link">Cetak Label Material</a></li>
              <li><a href="input_prod.php" target="mainFrame" class="dropdown-item menu-link">Input Production</a></li>
              <li><a href="input_sms.php" target="mainFrame" class="dropdown-item menu-link">SMS Production</a></li>
            </ul>
          </li>

          <li class="nav-item dropdown">
            <a id="dropdownSubMenu2" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" class="nav-link dropdown-toggle">
                <i class="fas fa-database"></i> Master
            </a>
            <ul class="dropdown-menu border-0 shadow" aria-labelledby="dropdownSubMenu2">
              <li><a href="master_supplier.php" target="mainFrame" class="dropdown-item menu-link">Master Supplier</a></li>
            </ul>
          </li>
        </ul>
      </div>

      <!-- Right navbar links (User Info & Logout) -->
      <ul class="order-1 order-md-3 navbar-nav navbar-no-expand ml-auto">
        <li class="nav-item d-none d-md-inline-block">
            <span class="nav-link">
                <i class="far fa-clock"></i> <?php echo h(date("d-M-Y H:i")); ?>
            </span>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link" data-toggle="dropdown" href="#">
            <i class="far fa-user-circle"></i> <?php echo h($dbUser); ?>
          </a>
          <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
            <span class="dropdown-header">Login Time: <?php echo h($loginTime); ?></span>
            <div class="dropdown-divider"></div>
            <a href="../prod2/logout.php" class="dropdown-item text-danger">
              <i class="fas fa-sign-out-alt mr-2"></i> Logout
            </a>
          </div>
        </li>
      </ul>
    </div>
  </nav>
  <!-- /.navbar -->

  <!-- Content Wrapper (Contains iframe) -->
  <div class="content-wrapper">
    <iframe id="mainFrame" name="mainFrame" src="dashboard_home.php"></iframe>
  </div>
  <!-- /.content-wrapper -->

</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
// Script untuk mengatur status 'active' pada menu
$(document).ready(function() {
    $('.menu-link').on('click', function() {
        // Hapus class active dari semua menu-link
        $('.menu-link').removeClass('active');
        
        // Tambahkan class active ke menu yang diklik
        $(this).addClass('active');

        // Jika yang diklik ada di dalam dropdown, buat dropdown toggle-nya juga menjadi active
        if($(this).hasClass('dropdown-item')) {
            $(this).closest('.dropdown').find('.dropdown-toggle').addClass('active');
        }
    });
});
</script>
</body>
</html>
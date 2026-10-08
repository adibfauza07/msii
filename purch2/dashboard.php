<?php
if (session_id() == "") {
    session_start();
}

require_once __DIR__ . "/../config/db_plant2.php";

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
    <title>PURCHASING System - Plant 2</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Theme style (AdminLTE 3) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

    <style>
        html, body {
            height: 100%;
            margin: 0;
            overflow: hidden; /* Mencegah scroll ganda dengan iframe */
            background-color: #f4f6f9;
        }

        .wrapper {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .main-header {
            flex-shrink: 0; /* Mencegah navbar menyusut */
        }

        .content-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            padding: 0;
            margin: 0;
            height: calc(100vh - 57px); /* Sisa tinggi layar setelah navbar */
        }

        #mainFrame {
            flex: 1;
            width: 100%;
            height: 100%;
            border: none;
            background: #d4d0c8; /* Warna background bawaan sebelumnya */
        }

        /* Penyesuaian agar teks menu dropdown tidak terlalu besar */
        .dropdown-menu {
            font-size: 14px;
        }
    </style>
</head>
<body class="hold-transition layout-top-nav">
<div class="wrapper">

    <!-- Navbar Atas -->
    <nav class="main-header navbar navbar-expand-md navbar-dark navbar-primary">
        <div class="container-fluid">
            <a href="dashboard_home.php" target="mainFrame" class="navbar-brand">
                <span class="brand-text font-weight-light"><i class="fas fa-industry mr-2"></i><b>Purchasing</b> Plant 2</span>
            </a>

            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarCollapse">
                <!-- Left navbar links -->
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a href="dashboard_home.php" target="mainFrame" class="nav-link menu-link active">Dashboard / Report</a>
                    </li>
                    
                    <!-- Dropdown Menu Entry -->
                    <li class="nav-item dropdown">
                        <a id="dropdownEntry" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" class="nav-link dropdown-toggle">Purchasing Entry</a>
                        <ul aria-labelledby="dropdownEntry" class="dropdown-menu border-0 shadow">
                            <li><a href="requisition.php" target="mainFrame" class="dropdown-item menu-link">Purchase Requisition</a></li>
                            <li><a href="quotation.php" target="mainFrame" class="dropdown-item menu-link">Quotation</a></li>
                            <li><a href="po.php" target="mainFrame" class="dropdown-item menu-link">Purchase Order</a></li>
                            <li><a href="receive.php" target="mainFrame" class="dropdown-item menu-link">Receive</a></li>
                        </ul>
                    </li>

                    <!-- Dropdown Menu Master -->
                    <li class="nav-item dropdown">
                        <a id="dropdownMaster" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" class="nav-link dropdown-toggle">Master Data</a>
                        <ul aria-labelledby="dropdownMaster" class="dropdown-menu border-0 shadow">
                            <li><a href="master_supplier.php" target="mainFrame" class="dropdown-item menu-link">Master Supplier</a></li>
                        </ul>
                    </li>
                </ul>

                <!-- Right navbar links -->
                <ul class="navbar-nav ml-auto">
                    <!-- Menampilkan info login User -->
                    <li class="nav-item d-none d-lg-flex align-items-center mr-3">
                        <span class="text-white-50 text-sm">
                            <i class="fas fa-user-circle mr-1"></i> <?php echo h($dbUser); ?> &nbsp;|&nbsp; <i class="fas fa-clock mr-1"></i> <?php echo h($loginTime); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link text-white" style="background-color: rgba(255,0,0,0.2); border-radius: 4px;" title="Logout">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <!-- /.navbar -->

    <!-- Iframe Content Area -->
    <div class="content-wrapper">
        <iframe id="mainFrame" name="mainFrame" src="dashboard_home.php"></iframe>
    </div>
    <!-- /.content-wrapper -->

</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
    $(document).ready(function() {
        // Script untuk mengatur menu yang aktif (highlight)
        $('.menu-link').on('click', function() {
            // Hapus kelas aktif dari semua link
            $('.menu-link').removeClass('active');
            $('.nav-item.dropdown .nav-link').removeClass('active');
            
            // Tambahkan kelas aktif ke link yang diklik
            $(this).addClass('active');

            // Jika link tersebut berada di dalam dropdown, highlight juga tab dropdown induknya
            if($(this).hasClass('dropdown-item')) {
                $(this).closest('.dropdown').find('.nav-link.dropdown-toggle').addClass('active');
            }
        });
    });
</script>

</body>
</html>
<?php
if (session_id() == "") {
    session_start();
}

require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    header("Location: login.php?error=session_expired");
    exit();
}

$dbUser    = isset($_SESSION["db_user"]) ? $_SESSION["db_user"] : "Guest";
$loginTime = isset($_SESSION["login_time"]) ? $_SESSION["login_time"] : date("Y-m-d H:i");

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ordering System - Plant 1</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Theme style (AdminLTE v3) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

    <style>
        /* Navbar Biru Muda & Border Penegas */
        .navbar-custom {
            background-color: #dbeafe !important;
            border-bottom: 2px solid #93c5fd;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 0;
            z-index: 1040;
        }

        /* Brand Title */
        .navbar-custom .navbar-brand {
            color: #1e3a8a !important;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        /* Link Menu - Font Tebal & Kontras Jelas */
        .navbar-custom .navbar-nav .nav-link {
            color: #1e293b !important;
            font-weight: 600;
            font-size: 13.5px;
            padding: 8px 12px;
            border-radius: 4px;
            transition: all 0.2s ease-in-out;
        }

        /* Hover Menu */
        .navbar-custom .navbar-nav .nav-link:hover {
            color: #1d4ed8 !important;
            background-color: rgba(255, 255, 255, 0.7);
        }

        /* Menu Aktif */
        .navbar-custom .navbar-nav .nav-link.active {
            color: #ffffff !important;
            background-color: #2563eb !important;
        }

        .navbar-custom .navbar-nav .nav-link.active i {
            color: #ffffff !important;
        }

        /* Container Teks Petunjuk di Tengah */
        .nav-center-hint {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            margin: 0 15px;
        }

        /* Style Badge Teks Petunjuk */
        .hint-badge {
            font-size: 13px;
            font-weight: 700;
            color: #0369a1;
            background-color: #e0f2fe;
            border: 1px solid #7dd3fc;
            padding: 5px 14px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .hint-badge i {
            color: #0284c7;
            margin-right: 6px;
        }

        /* Info User */
        .navbar-custom .user-info {
            color: #1e293b !important;
            font-size: 13px;
            font-weight: 600;
        }

        /* Dropdown Area */
        .dropdown-menu {
            border: 1px solid #bfdbfe;
            border-radius: 6px;
        }

        .dropdown-item {
            font-size: 13px;
            font-weight: 500;
            color: #334155;
            padding: 8px 16px;
        }

        .dropdown-item:hover {
            background-color: #eff6ff;
            color: #1d4ed8;
        }

        .dropdown-item i {
            width: 20px;
        }

        /* Area Iframe */
        .content-wrapper {
            background-color: #f1f5f9;
        }

        #mainFrame {
            width: 100%;
            height: calc(100vh - 120px);
            min-height: 600px;
            border: none;
            border-radius: 4px;
            background: #ffffff;
            display: block;
        }
    </style>
</head>
<body class="hold-transition layout-top-nav">
<div class="wrapper">

    <!-- Top Navbar -->
    <nav class="main-header navbar navbar-expand-xl navbar-light navbar-custom">
        <div class="container-fluid">
            <!-- Brand -->
            <a href="dashboard_home.php" class="navbar-brand iframe-link">
                <i class="fas fa-cubes mr-1 text-primary"></i> <b>Ordering</b> Plant 1
            </a>

            <!-- Mobile Toggler -->
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Nav Container -->
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <!-- Daftar Menu Kiri -->
                <ul class="navbar-nav">

                    <li class="nav-item">
                        <a href="dashboard_home.php" class="nav-link iframe-link active">
                            <i class="fas fa-tachometer-alt text-primary"></i> Report
                        </a>
                    </li>

                    <!-- Dropdown: Konfirmasi BC (Khusus Plant 1) -->
                    <li class="nav-item dropdown">
                        <a id="dropBC" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" class="nav-link dropdown-toggle">
                            <i class="fas fa-file-check text-success"></i> Dokumen BC
                        </a>
                        <ul aria-labelledby="dropBC" class="dropdown-menu border-0 shadow">
                            <li><a href="dokumen_bc.php" class="dropdown-item iframe-link"><i class="fas fa-file-alt text-success mr-2"></i> Dokumen Confirmation</a></li>
                            <li><a href="inv_bc.php" class="dropdown-item iframe-link"><i class="fas fa-file-invoice text-info mr-2"></i> Dokumen Confirmation INV</a></li>
                        </ul>
                    </li>

                    <li class="nav-item">
                        <a href="generate.php" class="nav-link iframe-link">
                            <i class="fas fa-file-invoice-dollar text-success"></i> Generate DI_NO
                        </a>
                    </li>

                    <!-- Dropdown: Master -->
                    <li class="nav-item dropdown">
                        <a id="dropMaster" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" class="nav-link dropdown-toggle">
                            <i class="fas fa-database text-warning"></i> Master
                        </a>
                        <ul aria-labelledby="dropMaster" class="dropdown-menu border-0 shadow">
                            <li><a href="master.php" class="dropdown-item iframe-link"><i class="fas fa-table text-warning mr-2"></i> Master Cust, Price, Curr</a></li>
                            <li><a href="sync_part_price_customer.php" class="dropdown-item iframe-link"><i class="fas fa-sync-alt text-info mr-2"></i> Price Synchronization</a></li>
                            <li><a href="form_di.php" class="dropdown-item iframe-link"><i class="fas fa-box text-secondary mr-2"></i> Update Packing STD Box</a></li>
                        </ul>
                    </li>

                    <!-- Dropdown: Order & Schedule -->
                    <li class="nav-item dropdown">
                        <a id="dropOrder" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" class="nav-link dropdown-toggle">
                            <i class="fas fa-tasks text-info"></i> Order &amp; Schedule
                        </a>
                        <ul aria-labelledby="dropOrder" class="dropdown-menu border-0 shadow">
                            <li><a href="input_order.php" class="dropdown-item iframe-link"><i class="fas fa-cart-plus text-success mr-2"></i> Input Order</a></li>
                            <li><a href="order_edit.php" class="dropdown-item iframe-link"><i class="fas fa-edit text-warning mr-2"></i> Edit Order</a></li>
                            <li><a href="manual_order.php" class="dropdown-item iframe-link"><i class="fas fa-truck-loading text-primary mr-2"></i> Delivery Instruction</a></li>
                            <li class="dropdown-divider"></li>
                            <li><a href="forecast.php" class="dropdown-item iframe-link"><i class="fas fa-chart-line text-info mr-2"></i> Forecast</a></li>
                            <li><a href="schedule.php" class="dropdown-item iframe-link"><i class="fas fa-calendar-alt text-danger mr-2"></i> Schedule</a></li>
                            <li><a href="schedule1.php" class="dropdown-item iframe-link"><i class="fas fa-th text-secondary mr-2"></i> Schedule Matrix</a></li>
                        </ul>
                    </li>

                    <!-- Dropdown: Keuangan & Dokumen -->
                    <li class="nav-item dropdown">
                        <a id="dropKeuangan" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" class="nav-link dropdown-toggle">
                            <i class="fas fa-wallet text-secondary"></i> Keuangan
                        </a>
                        <ul aria-labelledby="dropKeuangan" class="dropdown-menu border-0 shadow">
                            <li><a href="depresiasi.php" class="dropdown-item iframe-link"><i class="fas fa-balance-scale text-secondary mr-2"></i> Depresiasi</a></li>
                            <li><a href="quotation.php" class="dropdown-item iframe-link"><i class="fas fa-file-contract text-primary mr-2"></i> List Quotation</a></li>
                        </ul>
                    </li>

                    <!-- Dropdown: SPB -->
                    <li class="nav-item dropdown">
                        <a id="dropSPB" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" class="nav-link dropdown-toggle">
                            <i class="fas fa-clipboard-list text-primary"></i> SPB
                        </a>
                        <ul aria-labelledby="dropSPB" class="dropdown-menu border-0 shadow">
                            <li><a href="material_slip/index.php" class="dropdown-item iframe-link"><i class="fas fa-clipboard text-info mr-2"></i> SPB</a></li>
                            <li><a href="SPB/index.php" class="dropdown-item iframe-link"><i class="fas fa-clipboard-check text-success mr-2"></i> SPB General</a></li>
                        </ul>
                    </li>

                </ul>

                <!-- Teks Petunjuk di Area Tengah -->
                <div class="nav-center-hint d-none d-lg-flex">
                    <span class="hint-badge">
                        <i class="fas fa-mouse-pointer"></i> Klik menu di panel untuk memulai !
                    </span>
                </div>

                <!-- Sisi Kanan: User Info & Logout -->
                <ul class="navbar-nav ml-auto align-items-center">
                    <li class="nav-item d-none d-xl-block mr-3 user-info">
                        <i class="fas fa-user-circle text-primary mr-1"></i> <b><?php echo h($dbUser); ?></b>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="btn btn-danger btn-sm font-weight-bold" onclick="return confirm('Logout dari sistem?');">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="content-wrapper">
        <div class="content p-2">
            <div class="container-fluid p-0">
                <iframe id="mainFrame" name="mainFrame" src="dashboard_home.php"></iframe>
            </div>
        </div>
    </div>

</div>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.1/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
$(document).ready(function() {
    $('.iframe-link').on('click', function(e) {
        e.preventDefault();
        var urlTarget = $(this).attr('href');

        if (urlTarget && urlTarget !== '#') {
            $('#mainFrame').attr('src', urlTarget);

            // Bersihkan status aktif sebelumnya
            $('.navbar-nav .nav-link, .dropdown-item').removeClass('active');
            
            // Tandai link yang diklik
            $(this).addClass('active');

            // Jika item dropdown diklik, aktifkan tombol induknya
            var parentDropdown = $(this).closest('.dropdown');
            if (parentDropdown.length) {
                parentDropdown.find('.dropdown-toggle').addClass('active');
            }

            // Tutup dropdown pada tampilan mobile
            $('.navbar-collapse').collapse('hide');
        }
    });
});
</script>
</body>
</html>
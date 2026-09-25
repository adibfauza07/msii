<?php
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// =======================================================
// SECURITY (WAJIB LOGIN)
// =======================================================
require_once "../middleware/Auth.php";
require_once "../middleware/RoleCheck.php";
only(['p2', 'admin']); // Sesuaikan akses divisi

// =======================================================
// KONEKSI DATABASE
// =======================================================
require_once "../config/database.php";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard MTN | PT IMC Tekno</title>

    <!-- Bootstrap 5 & FontAwesome CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f4f7f6;
            overflow-x: hidden;
            margin: 0;
        }

        /* --- WRAPPER & SIDEBAR --- */
        #wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
        }

        #sidebar {
            min-width: 260px;
            max-width: 260px;
            background: #1e2833;
            color: #fff;
            transition: all 0.3s ease;
            height: 100vh;
            position: sticky;
            top: 0;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        #sidebar.toggled {
            margin-left: -260px;
        }

        /* Custom Scrollbar Sidebar */
        #sidebar::-webkit-scrollbar { width: 5px; }
        #sidebar::-webkit-scrollbar-track { background: #1e2833; }
        #sidebar::-webkit-scrollbar-thumb { background: #3a4b5c; border-radius: 4px; }
        #sidebar::-webkit-scrollbar-thumb:hover { background: #51687d; }

        .sidebar-header {
            padding: 22px 20px;
            background: #171f28;
            border-bottom: 1px solid #2a3847;
            text-align: center;
        }
        .sidebar-header h4 { margin: 0; font-weight: 800; font-size: 22px; letter-spacing: 1px; color: #fff;}
        .sidebar-header span { color: #3498db; }

        .sidebar-menu {
            padding: 10px 0;
            list-style: none;
            margin: 0;
        }

        .sidebar-menu .menu-title {
            padding: 15px 20px 5px;
            font-size: 11px;
            color: #7b8b9a;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .sidebar-menu a {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            color: #aeb9c5;
            text-decoration: none;
            transition: 0.2s;
            font-size: 14.5px;
            font-weight: 600;
        }

        .sidebar-menu a i.icon-main {
            width: 25px;
            font-size: 16px;
            text-align: center;
            margin-right: 12px;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: #273442;
            color: #fff;
            border-left: 4px solid #3498db;
        }

        /* Styling Submenu (Accordion) */
        .sidebar-menu .collapse-inner {
            background: #171f28;
            padding: 5px 0;
        }

        .sidebar-menu .collapse-inner a {
            padding: 10px 20px 10px 55px;
            font-size: 13.5px;
            border-left: none;
            color: #8c9ca9;
        }
        
        .sidebar-menu .collapse-inner a:hover, .sidebar-menu .collapse-inner a.active {
            background: transparent;
            color: #3498db;
            font-weight: 700;
        }

        .icon-arrow {
            font-size: 12px;
            transition: transform 0.3s;
        }
        .sidebar-menu a:not(.collapsed) .icon-arrow {
            transform: rotate(180deg);
        }

        /* --- CONTENT AREA --- */
        #content-wrapper {
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* TOP NAVBAR */
        .topbar {
            background: #fff;
            height: 65px;
            padding: 0 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            z-index: 999;
            position: sticky;
            top: 0;
        }

        .btn-toggle {
            background: #f4f7f6;
            border: none;
            font-size: 18px;
            color: #2c3e50;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-toggle:hover { background: #e2e8f0; }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            color: #495057;
            font-size: 14px;
        }
        .user-profile img {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }

        /* MAIN CONTENT PADDING */
        .main-content {
            padding: 30px;
            flex: 1;
        }

        /* DASHBOARD WIDGET CARDS */
        .dash-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
            border: 1px solid #f1f1f1;
            border-left: 5px solid #3498db;
            margin-bottom: 25px;
            transition: transform 0.2s;
        }
        .dash-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.05); }
        .dash-card.card-green { border-left-color: #2ecc71; }
        .dash-card.card-orange { border-left-color: #f39c12; }
        
        .dash-icon {
            width: 65px;
            height: 65px;
            border-radius: 12px;
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin-right: 20px;
        }
        .card-green .dash-icon { background: rgba(46, 204, 113, 0.1); color: #2ecc71; }
        .card-orange .dash-icon { background: rgba(243, 156, 18, 0.1); color: #f39c12; }

        .dash-info h3 { margin: 0; font-size: 26px; font-weight: 800; color: #2c3e50; }
        .dash-info span { font-size: 13px; color: #95a5a6; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            #sidebar {
                margin-left: -260px;
                position: fixed;
            }
            #sidebar.toggled { margin-left: 0; }
            .main-content { padding: 20px; }
        }
    </style>
</head>
<body>

<div id="wrapper">

    <!-- SIDEBAR -->
    <nav id="sidebar">
        <div class="sidebar-header">
            <h4>MTN <span>SYSTEM</span></h4>
        </div>

        <ul class="sidebar-menu">
            <li class="menu-title">Home</li>
            <li>
                <a href="?page=home" class="<?= ($page=='home')?'active':'' ?>">
                    <i class="fas fa-home icon-main"></i> Dashboard
                </a>
            </li>

            <li class="menu-title">Daily Maintenance</li>
            
            <!-- MENU APLIKASI (Dropdown Accordion) -->
            <li>
                <a href="#menuAplikasi" data-bs-toggle="collapse" class="<?= in_array($page, ['mac_master','history','daily','car']) ? '' : 'collapsed' ?>">
                    <i class="fas fa-desktop icon-main"></i> Aplikasi MTN
                    <i class="fas fa-chevron-down ms-auto icon-arrow"></i>
                </a>
                <div class="collapse <?= in_array($page, ['mac_master','history','daily','car']) ? 'show' : '' ?>" id="menuAplikasi">
                    <div class="collapse-inner">
                        <a href="mac_master.php" class="<?= ($page=='mac_master')?'active':'' ?>"><i class="fas fa-circle ms-1 me-2" style="font-size:6px;"></i> Master Machine</a>
                        <a href="history_machine.php" class="<?= ($page=='history')?'active':'' ?>"><i class="fas fa-circle ms-1 me-2" style="font-size:6px;"></i> History Machine</a>
                        <a href="daily_maintenance.php" class="<?= ($page=='daily')?'active':'' ?>"><i class="fas fa-circle ms-1 me-2" style="font-size:6px;"></i> Daily Maintenance</a>
                        <a href="car_maintenance.php" class="<?= ($page=='car')?'active':'' ?>"><i class="fas fa-circle ms-1 me-2" style="font-size:6px;"></i> CAR Maintenance</a>
                    </div>
                </div>
            </li>

            <!-- MENU REPORT (Dropdown Accordion) -->
            <li>
                <a href="#menuReport" data-bs-toggle="collapse" class="<?= in_array($page, ['report_daily','report_kerusakan','report_perbaikan','report_aging']) ? '' : 'collapsed' ?>">
                    <i class="fas fa-chart-pie icon-main"></i> Reports
                    <i class="fas fa-chevron-down ms-auto icon-arrow"></i>
                </a>
                <div class="collapse <?= in_array($page, ['report_daily','report_kerusakan','report_perbaikan','report_aging']) ? 'show' : '' ?>" id="menuReport">
                    <div class="collapse-inner">
                        <a href="report_daily_mtn.php" class="<?= ($page=='report_daily')?'active':'' ?>"><i class="fas fa-circle ms-1 me-2" style="font-size:6px;"></i> Daily MTN Report</a>
                        <a href="report_kerusakan_periode.php" class="<?= ($page=='report_kerusakan')?'active':'' ?>"><i class="fas fa-circle ms-1 me-2" style="font-size:6px;"></i> Kerusakan Mesin</a>
                        <a href="report_perbaikan_mtn.php" class="<?= ($page=='report_perbaikan')?'active':'' ?>"><i class="fas fa-circle ms-1 me-2" style="font-size:6px;"></i> Report Perbaikan</a>
                        <a href="report_aging_yearly.php" class="<?= ($page=='report_aging')?'active':'' ?>"><i class="fas fa-circle ms-1 me-2" style="font-size:6px;"></i> Grafik Aging Time</a>
                    </div>
                </div>
            </li>

            <li class="menu-title">Session</li>
            <li>
                <a href="logout.php" style="color:#ff6b6b;">
                    <i class="fas fa-sign-out-alt icon-main"></i> Logout Sistem
                </a>
            </li>
        </ul>
    </nav>

    <!-- CONTENT WRAPPER -->
    <div id="content-wrapper">
        
        <!-- TOP NAVBAR -->
        <div class="topbar">
            <button class="btn-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="user-profile">
                <span>Halo, <?php echo isset($_SESSION['erp_user']) ? strtoupper($_SESSION['erp_user']) : 'Tim Maintenance'; ?></span>
                <!-- Avatar generator otomatis berdasarkan inisial -->
                <img src="https://ui-avatars.com/api/?name=MTN&background=3498db&color=fff&bold=true" alt="User Avatar">
            </div>
        </div>

        <!-- MAIN CONTENT AREA -->
        <div class="main-content">
            <?php if ($page == 'home'): ?>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="fw-bold mb-1" style="color:#2c3e50;">Dashboard Overview</h3>
                        <p class="text-muted mb-0">Selamat datang di Sistem Informasi Maintenance (MTN)</p>
                    </div>
                    <div class="d-none d-sm-block">
                        <span class="badge bg-white text-dark shadow-sm px-3 py-2 border"><i class="far fa-calendar-alt text-primary me-2"></i> <?= date('d F Y') ?></span>
                    </div>
                </div>

                <!-- DASHBOARD WIDGETS (Mockup Indikator) -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="dash-card">
                            <div class="dash-icon"><i class="fas fa-cogs"></i></div>
                            <div class="dash-info">
                                <span>Total Mesin</span>
                                <h3>-</h3> <!-- Angka dinamis bisa di-query dari MAC_MTN -->
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="dash-card card-orange">
                            <div class="dash-icon"><i class="fas fa-tools"></i></div>
                            <div class="dash-info">
                                <span>Perbaikan Hari Ini</span>
                                <h3>-</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="dash-card card-green">
                            <div class="dash-icon"><i class="fas fa-clipboard-list"></i></div>
                            <div class="dash-info">
                                <span>Total CAR Aktif</span>
                                <h3>-</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- WELCOME BANNER -->
                <div class="card border-0 shadow-sm mt-3" style="border-radius:15px;">
                    <div class="card-body p-5 text-center">
                        <i class="fas fa-shield-alt fa-4x text-primary mb-3" style="opacity: 0.2;"></i>
                        <h4 class="fw-bold text-dark">Maintenance & Repair Management</h4>
                        <p class="text-muted mx-auto" style="max-width: 600px;">
                            Gunakan navigasi di sebelah kiri untuk mengelola master data mesin, mencatat <i>daily maintenance</i>, mengelola formulir CAR, hingga merekap laporan kerusakan secara periodik.
                        </p>
                    </div>
                </div>

            <?php endif; ?>
        </div>

    </div>

</div>

<!-- Javascript -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Fitur Toggle Buka-Tutup Sidebar
    $('#sidebarToggle').click(function() {
        $('#sidebar').toggleClass('toggled');
    });
</script>
</body>
</html>
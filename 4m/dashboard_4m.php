<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Cek sesi login agar terhindar dari bug 'Session tidak lengkap'
if (!isset($_SESSION['erp_user'])) {
    $_SESSION['erp_user'] = 'Guest'; // Fallback aman
}
if (!isset($_SESSION['active_plant'])) {
    $_SESSION['active_plant'] = 'p1'; // Default Fallback
}

require_once __DIR__ . '/../config/database.php'; // Hubungkan database

$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>4M Change System - PT. IMC Tekno</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Bootstrap 5 & FontAwesome CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Dependencies 4M (Select2, DataTables) -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
    
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f4f7f6;
            overflow-x: hidden;
            margin: 0;
        }

        /* --- WRAPPER & SIDEBAR --- */
        #wrapper { display: flex; width: 100%; align-items: stretch; }
        
        #sidebar {
            min-width: 260px; max-width: 260px;
            background: #1e2833; color: #fff;
            transition: all 0.3s ease; height: 100vh;
            position: sticky; top: 0; overflow-y: auto;
            z-index: 1000; box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        #sidebar.toggled { margin-left: -260px; }

        #sidebar::-webkit-scrollbar { width: 5px; }
        #sidebar::-webkit-scrollbar-track { background: #1e2833; }
        #sidebar::-webkit-scrollbar-thumb { background: #3a4b5c; border-radius: 4px; }
        #sidebar::-webkit-scrollbar-thumb:hover { background: #51687d; }

        .sidebar-header {
            padding: 22px 20px; background: #171f28;
            border-bottom: 1px solid #2a3847; text-align: center;
        }
        .sidebar-header h4 { margin: 0; font-weight: 800; font-size: 20px; letter-spacing: 1px; color: #fff;}
        .sidebar-header span { color: #8b5cf6; /* Aksen Ungu Khas 4M */ } 

        .sidebar-menu { padding: 10px 0; list-style: none; margin: 0; }
        .sidebar-menu .menu-title {
            padding: 15px 20px 5px; font-size: 11px; color: #7b8b9a;
            text-transform: uppercase; font-weight: 800; letter-spacing: 1px;
        }
        .sidebar-menu a {
            padding: 12px 20px; display: flex; align-items: center;
            color: #aeb9c5; text-decoration: none; transition: 0.2s;
            font-size: 14.5px; font-weight: 600;
        }
        .sidebar-menu a i.icon-main {
            width: 25px; font-size: 16px; text-align: center; margin-right: 12px;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: #273442; color: #fff;
            border-left: 4px solid #8b5cf6; /* Aksen Ungu */
        }

        /* --- CONTENT AREA --- */
        #content-wrapper { width: 100%; min-height: 100vh; display: flex; flex-direction: column; overflow: hidden; }

        /* TOP NAVBAR */
        .topbar {
            background: #fff; height: 65px; padding: 0 25px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03); z-index: 999; position: sticky; top: 0;
        }
        .btn-toggle {
            background: #f4f7f6; border: none; font-size: 18px; color: #2c3e50;
            width: 40px; height: 40px; border-radius: 8px; cursor: pointer; transition: 0.2s;
        }
        .btn-toggle:hover { background: #e2e8f0; }

        .user-profile { display: flex; align-items: center; gap: 12px; font-weight: 700; color: #495057; font-size: 14px; }
        .user-profile img {
            width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0;
        }

        .main-content { padding: 30px; flex: 1; }

        /* DASHBOARD WIDGET CARDS */
        .dash-card {
            background: #fff; border-radius: 12px; padding: 20px;
            display: flex; align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,0.02);
            border: 1px solid #f1f1f1; border-left: 5px solid #8b5cf6;
            margin-bottom: 25px; transition: transform 0.2s;
        }
        .dash-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.05); }
        .dash-icon {
            width: 65px; height: 65px; border-radius: 12px;
            background: rgba(139, 92, 246, 0.1); color: #8b5cf6;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px; margin-right: 20px;
        }
        .dash-info h3 { margin: 0; font-size: 28px; font-weight: 800; color: #2c3e50; }
        .dash-info span { font-size: 12px; color: #95a5a6; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; }

        @media (max-width: 768px) {
            #sidebar { margin-left: -260px; position: fixed; }
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
            <h4>4M <span>SYSTEM</span></h4>
        </div>

        <ul class="sidebar-menu">
            <li class="menu-title">Home</li>
            <li>
                <a href="?page=home" class="<?= ($page=='home')?'active':'' ?>">
                    <i class="fas fa-home icon-main"></i> Dashboard
                </a>
            </li>

            <li class="menu-title">Proses Perubahan</li>
            <li>
                <a href="input_pcis.php" class="<?= ($page=='input_pcis')?'active':'' ?>">
                    <i class="fas fa-file-signature icon-main"></i> Input 4M Change
                </a>
            </li>
            <li>
                <a href="?page=history" class="<?= ($page=='history')?'active':'' ?>">
                    <i class="fas fa-history icon-main"></i> Riwayat Perubahan
                </a>
            </li>
            <li>
                <a href="?page=rekap" class="<?= ($page=='rekap')?'active':'' ?>">
                    <i class="fas fa-file-invoice icon-main"></i> Rekap Summary (SP)
                </a>
            </li>

            <li class="menu-title">Session</li>
            <li>
                <a href="logout.php" style="color:#ff6b6b;">
                    <i class="fas fa-sign-out-alt icon-main"></i> Logout Sistem
                </a>
            </li>
            <li>
                <a href="../index.php" style="color:#aeb9c5;">
                    <i class="fas fa-arrow-left icon-main"></i> Kembali ke ERP
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
                <span>Halo, <?php echo isset($_SESSION['erp_user']) ? strtoupper($_SESSION['erp_user']) : 'Guest'; ?></span>
                <img src="https://ui-avatars.com/api/?name=4M&background=8b5cf6&color=fff&bold=true" alt="Avatar">
            </div>
        </div>

        <!-- MAIN CONTENT AREA -->
        <div class="main-content">
            <?php
            // Jalankan intercept form post action_4m secara internal
            if (isset($_POST['btnSimpan'])) {
                include "action_4m.php";
            }

            switch ($page) {
                case 'input_pcis':
                    include file_exists("page_input_4m.php") ? "page_input_4m.php" : "rekap_4m.php";
                    break;
                case 'history':
                    include "page_history.php";
                    break;
                case 'rekap':
                    include "rekap_4m.php";
                    break;
                case 'home':
                default:
                    ?>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h3 class="fw-bold mb-1" style="color:#2c3e50;">4M Change Management Overview</h3>
                            <p class="text-muted mb-0">Selamat datang di Sistem Manajemen Perubahan (4M)</p>
                        </div>
                        <div class="d-none d-sm-block">
                            <span class="badge bg-white text-dark shadow-sm px-3 py-2 border"><i class="far fa-calendar-alt text-primary me-2"></i> <?= date('d F Y') ?></span>
                        </div>
                    </div>

                    <!-- DASHBOARD WIDGETS -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="dash-card">
                                <div class="dash-icon"><i class="fas fa-file-contract"></i></div>
                                <div class="dash-info">
                                    <span>Total Pengajuan PCIS</span>
                                    <h3>
                                        <?php 
                                        $res = q("SELECT COUNT(*) as total FROM PROSES_CHANGE");
                                        $data = sqlsrv_fetch_array($res);
                                        echo $data['total'] ? number_format($data['total']) : '0';
                                        ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- WELCOME BANNER -->
                    <div class="card border-0 shadow-sm mt-2" style="border-radius:15px;">
                        <div class="card-body p-5 text-center">
                            <i class="fas fa-project-diagram fa-4x mb-3" style="color: #8b5cf6; opacity: 0.2;"></i>
                            <h4 class="fw-bold text-dark">Sistem Pengendalian PCIS</h4>
                            <p class="text-muted mx-auto" style="max-width: 600px;">
                                Gunakan menu navigasi di sebelah kiri untuk membuat dokumen PCIS baru, memantau riwayat pengajuan perubahan (Man, Machine, Material, Method), atau mencetak rekapitulasi summary.
                            </p>
                        </div>
                    </div>

                    <?php
                    break;
            }
            ?>
        </div>
    </div>
</div>

<!-- Javascript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Library Datatables & Select2 untuk halaman child (History/Rekap) -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    // Fitur Toggle Buka-Tutup Sidebar
    $('#sidebarToggle').click(function() {
        $('#sidebar').toggleClass('toggled');
    });
</script>
</body>
</html>
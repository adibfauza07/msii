<?php
// 1. CEK SESI & KONEKSI
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['db_user'])) { header("Location: login.php"); exit(); }

require_once __DIR__ . '/../config/database_p1.php';
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Inventory System - Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSS Frameworks -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        /* --- GLOBAL & FONT --- */
        body {
            background: #f4f7f6;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            overflow-x: hidden;
            color: #334155;
        }

        /* --- SIDEBAR PREMIUM --- */
        #sidebar {
            width: 260px;
            height: 100vh;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            color: #cbd5e1;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1050;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }

        #sidebar .brand {
            padding: 22px 20px;
            font-size: 18px;
            font-weight: 800;
            background: rgba(0, 0, 0, 0.2);
            color: #f8fafc;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        #sidebar .brand i { font-size: 22px; color: #38bdf8; }

        .nav-link {
            color: #94a3b8;
            padding: 12px 20px;
            font-size: 14.5px;
            border-left: 4px solid transparent;
            transition: all 0.2s ease-in-out;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.05);
            color: #f8fafc;
            border-left-color: #475569;
        }

        .nav-link.active {
            background: rgba(56, 189, 248, 0.1);
            color: #38bdf8 !important;
            border-left-color: #38bdf8;
            font-weight: 600;
        }

        .nav-link i { font-size: 1.2rem; }

        .menu-label {
            padding: 20px 20px 8px 20px;
            font-size: 11px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 700;
            letter-spacing: 1px;
        }

        /* Sidebar Footer */
        .sidebar-footer {
            margin-top: auto;
            padding: 20px;
            background: rgba(0,0,0,0.25);
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        /* --- CONTENT AREA & TOP NAVBAR --- */
        #content-wrapper {
            margin-left: 260px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: all 0.3s;
        }

        .top-navbar {
            height: 65px;
            background: #ffffff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 25px;
            z-index: 1000;
            position: sticky;
            top: 0;
        }

        .content-main {
            padding: 30px;
            flex-grow: 1;
            animation: fadeIn 0.5s ease-in-out;
        }

        /* --- DASHBOARD CARDS UI --- */
        .dash-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
            background: #fff;
            height: 100%;
        }
        .dash-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }
        .dash-icon {
            width: 50px; height: 50px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 12px; font-size: 24px;
        }

        /* --- MOBILE RESPONSIVE --- */
        .btn-toggle-mobile { display: none; background: transparent; border: none; color: #334155; }
        .overlay {
            display: none; position: fixed; width: 100vw; height: 100vh; 
            background: rgba(15, 23, 42, 0.6); z-index: 1040; top: 0; left: 0;
            backdrop-filter: blur(2px);
        }

        @media (max-width: 768px) {
            #sidebar { margin-left: -260px; }
            #sidebar.active { margin-left: 0; }
            #content-wrapper { margin-left: 0; }
            .btn-toggle-mobile { display: block; }
            .content-main { padding: 20px 15px; }
            .overlay.active { display: block; }
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>

<body>

    <div class="overlay"></div>

    <!-- SIDEBAR -->
    <div id="sidebar">
        <div class="brand">
            <i class="bi bi-box-seam-fill"></i> INVENTORY
        </div>

        <div class="py-2 overflow-auto h-100">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="?page=home" class="nav-link <?php echo ($page=='home')?'active':''; ?>">
                        <i class="bi bi-grid-1x2"></i> Dashboard Overview
                    </a>
                </li>

                <div class="menu-label">Master Data</div>
                <li class="nav-item">
                    <a href="?page=master" class="nav-link <?php echo ($page=='master')?'active':''; ?>">
                        <i class="bi bi-database"></i> Master Items
                    </a>
                </li>
                
                <div class="menu-label">Transaksi & Audit</div>
                <li class="nav-item">
                    <a href="?page=sop" class="nav-link <?php echo ($page=='sop')?'active':''; ?>">
                        <i class="bi bi-clipboard-check"></i> Stock Opname
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=transaksi" class="nav-link <?php echo ($page=='transaksi')?'active':''; ?>">
                        <i class="bi bi-arrow-left-right"></i> Transaksi General
                    </a>
                </li>

                <div class="menu-label">Report & System</div>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page=='kumpulan_report')?'active':''; ?>" href="?page=kumpulan_report">
                        <i class="bi bi-folder2-open"></i> Kumpulan Report
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page=='reposting')?'active':''; ?>" href="?page=reposting">
                        <i class="bi bi-arrow-clockwise"></i> Reposting
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page=='posting')?'active':''; ?>" href="?page=posting">
                        <i class="bi bi-cloud-arrow-up"></i> Posting
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-footer">
            <div class="d-flex align-items-center mb-3">
                <div class="bg-success rounded-circle shadow" style="width: 12px; height: 12px; margin-right: 12px;"></div>
                <div>
                    <div style="font-size: 10px; color: #94a3b8; text-transform:uppercase; letter-spacing:1px;">Logged in as</div>
                    <div class="text-white fw-bold" style="font-size: 14px;"><?php echo htmlspecialchars($_SESSION['db_user']); ?></div>
                </div>
            </div>
            <a href="../index.php" class="btn w-100 btn-sm mb-2 text-white" style="background: rgba(255,255,255,0.1); border:none;">
                <i class="bi bi-house-door me-2"></i> ERP Portal
            </a>
            <a href="logout.php" class="btn w-100 btn-sm text-white" style="background: #ef4444; border:none;">
                <i class="bi bi-power me-2"></i> LOGOUT
            </a>
        </div>
    </div>

    <!-- MAIN CONTENT WRAPPER -->
    <div id="content-wrapper">
        
        <!-- TOP NAVBAR -->
        <div class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="btn-toggle-mobile p-1 me-3" id="mobileToggle">
                    <i class="bi bi-list fs-3"></i>
                </button>
                <h5 class="mb-0 fw-bold text-secondary text-uppercase fs-6 d-none d-md-block">
                    <i class="bi bi-calendar3 me-2"></i> <?php echo date('d F Y'); ?>
                </h5>
            </div>
            <div>
                <span class="badge bg-primary px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-building"></i> Plant 1 / Plant 2</span>
            </div>
        </div>

        <!-- PAGE CONTENT -->
        <div class="content-main">
            <?php
            switch ($page) {
                case 'master': include "page_master.php"; break;
                case 'sop': include "page_sop.php"; break;
                case 'transaksi': include "page_transaksi.php"; break;
                case 'kumpulan_report': include 'page_kumpulan_report.php'; break;
                case 'reposting': include 'reposting.php'; break;
                case 'posting': include 'posting.php'; break;
                
                case 'home': default:
                    // MENGAMBIL DATA UNTUK STATISTIK KOTAK (Bisa disesuaikan jika query berbeda)
                    $qItems = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM ITEMS WHERE ITEM_INACTIVE=0");
                    $totItems = ($qItems && $r = sqlsrv_fetch_array($qItems)) ? $r['t'] : 0;

                    $qTrans = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM TRANS WHERE CONVERT(date, TRAN_DATE) = CONVERT(date, GETDATE())");
                    $totTrans = ($qTrans && $r = sqlsrv_fetch_array($qTrans)) ? $r['t'] : 0;

                    $qSop = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM SOP WHERE SOP_FINISHED='F'");
                    $totSop = ($qSop && $r = sqlsrv_fetch_array($qSop)) ? $r['t'] : 0;
                    ?>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h3 class="fw-bold mb-1" style="color: #1e293b;">Selamat Datang, <?php echo htmlspecialchars($_SESSION['db_user']); ?>!</h3>
                            <p class="text-muted mb-0">Berikut adalah ringkasan sistem Inventory hari ini.</p>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <!-- Card 1: Total Items -->
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="dash-card card p-4">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="text-muted small fw-bold text-uppercase mb-2">Total Master Items</div>
                                        <h2 class="fw-bold text-dark mb-0"><?php echo number_format($totItems); ?></h2>
                                    </div>
                                    <div class="dash-icon bg-primary bg-opacity-10 text-primary">
                                        <i class="bi bi-box-seam"></i>
                                    </div>
                                </div>
                                <div class="mt-3 small text-muted"><i class="bi bi-check-circle-fill text-success me-1"></i> Item Aktif di Sistem</div>
                            </div>
                        </div>

                        <!-- Card 2: Transaksi Hari Ini -->
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="dash-card card p-4">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="text-muted small fw-bold text-uppercase mb-2">Transaksi Hari Ini</div>
                                        <h2 class="fw-bold text-dark mb-0"><?php echo number_format($totTrans); ?></h2>
                                    </div>
                                    <div class="dash-icon bg-success bg-opacity-10 text-success">
                                        <i class="bi bi-arrow-left-right"></i>
                                    </div>
                                </div>
                                <div class="mt-3 small text-muted"><i class="bi bi-clock-fill text-warning me-1"></i> Input per tgl <?php echo date('d/m/Y'); ?></div>
                            </div>
                        </div>

                        <!-- Card 3: SOP Pending -->
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="dash-card card p-4">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="text-muted small fw-bold text-uppercase mb-2">SOP Belum Finished</div>
                                        <h2 class="fw-bold text-dark mb-0"><?php echo number_format($totSop); ?></h2>
                                    </div>
                                    <div class="dash-icon bg-warning bg-opacity-10 text-warning">
                                        <i class="bi bi-clipboard-data"></i>
                                    </div>
                                </div>
                                <div class="mt-3 small text-muted"><i class="bi bi-exclamation-circle-fill text-danger me-1"></i> Perlu direview & dikunci</div>
                            </div>
                        </div>

                        <!-- Card 4: Server Status -->
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="dash-card card p-4">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="text-muted small fw-bold text-uppercase mb-2">Status Database</div>
                                        <h4 class="fw-bold text-dark mb-0 mt-2">CONNECTED</h4>
                                    </div>
                                    <div class="dash-icon bg-info bg-opacity-10 text-info">
                                        <i class="bi bi-server"></i>
                                    </div>
                                </div>
                                <div class="mt-3 small text-muted"><i class="bi bi-hdd-network-fill text-primary me-1"></i> MS SQL Server Aktif</div>
                            </div>
                        </div>
                    </div>
                    <?php 
                    break;
            }
            ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
    $(document).ready(function() {
        // Interaksi Mobile Sidebar
        $('#mobileToggle, .overlay').click(function() {
            $('#sidebar').toggleClass('active');
            $('.overlay').toggleClass('active');
        });

        // Setup Select2 secara Global untuk input pencarian
        if($('.select2-ajax').length) {
            $('.select2-ajax').select2({
                theme: 'bootstrap-5',
                placeholder: 'Ketik Kode / Nama...',
                allowClear: true,
                minimumInputLength: 1,
                ajax: {
                    url: 'api_cari_barang.php',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) { return { q: params.term }; },
                    processResults: function (data) { return { results: data }; },
                    cache: true
                }
            });
        }
    });
    </script>

</body>
</html>
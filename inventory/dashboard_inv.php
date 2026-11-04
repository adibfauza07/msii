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

        /* --- TOP NAVBAR PREMIUM --- */
        .navbar-custom {
            background: linear-gradient(90deg, #0f172a 0%, #1e293b 100%);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-size: 18px;
            font-weight: 800;
            color: #f8fafc !important;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        
        .navbar-brand i { color: #38bdf8; }

        .navbar-custom .nav-link {
            color: #94a3b8;
            font-size: 14.5px;
            font-weight: 500;
            padding: 10px 15px;
            transition: all 0.2s ease-in-out;
        }

        .navbar-custom .nav-link:hover {
            color: #f8fafc;
        }

        .navbar-custom .nav-link.active {
            color: #38bdf8 !important;
            font-weight: 600;
        }

        .navbar-custom .dropdown-menu {
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-radius: 8px;
        }

        .dropdown-item.active {
            background-color: rgba(56, 189, 248, 0.1);
            color: #0f172a;
            font-weight: 600;
        }

        /* --- CONTENT AREA --- */
        .content-main {
            padding: 30px;
            animation: fadeIn 0.5s ease-in-out;
            min-height: calc(100vh - 70px);
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

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>

<body>

    <!-- TOP NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container-fluid px-4">
            <!-- Brand -->
            <a class="navbar-brand d-flex align-items-center gap-2" href="?page=home">
                <i class="bi bi-box-seam-fill"></i> INVENTORY
            </a>
            
            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler text-white border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu">
                <i class="bi bi-list fs-1 text-white"></i>
            </button>

            <!-- Nav Links -->
            <div class="collapse navbar-collapse" id="navbarMenu">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page=='home')?'active':''; ?>" href="?page=home">
                            <i class="bi bi-grid-1x2 me-1"></i> Dashboard
                        </a>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo ($page=='master')?'active':''; ?>" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-database me-1"></i> Master Data
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item <?php echo ($page=='master')?'active':''; ?>" href="?page=master">Master Items</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo in_array($page, ['sop', 'transaksi']) ? 'active' : ''; ?>" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-arrow-left-right me-1"></i> Transaksi & Audit
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item <?php echo ($page=='sop')?'active':''; ?>" href="?page=sop"><i class="bi bi-clipboard-check me-2"></i> Stock Opname</a></li>
                            <li><a class="dropdown-item <?php echo ($page=='transaksi')?'active':''; ?>" href="?page=transaksi"><i class="bi bi-arrow-left-right me-2"></i> Transaksi General</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo in_array($page, ['kumpulan_report', 'reposting', 'posting']) ? 'active' : ''; ?>" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-folder2-open me-1"></i> Report & System
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item <?php echo ($page=='kumpulan_report')?'active':''; ?>" href="?page=kumpulan_report"><i class="bi bi-file-earmark-bar-graph me-2"></i> Kumpulan Report</a></li>
                            <li><a class="dropdown-item <?php echo ($page=='reposting')?'active':''; ?>" href="?page=reposting"><i class="bi bi-arrow-clockwise me-2"></i> Reposting</a></li>
                            <li><a class="dropdown-item <?php echo ($page=='posting')?'active':''; ?>" href="?page=posting"><i class="bi bi-cloud-arrow-up me-2"></i> Posting</a></li>
                        </ul>
                    </li>
                </ul>

                <!-- Right Side (User Info & Badges) -->
                <div class="d-flex align-items-center gap-3 mt-3 mt-lg-0">
                    <span class="badge bg-primary px-3 py-2 rounded-pill shadow-sm d-none d-md-inline-block">
                        <i class="bi bi-building"></i> Plant 1 / Plant 2
                    </span>
                    
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
                            <div class="bg-success rounded-circle shadow" style="width: 10px; height: 10px;"></div>
                            <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['db_user']); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end mt-2">
                            <li>
                                <a class="dropdown-item py-2" href="../index.php">
                                    <i class="bi bi-house-door me-2 text-primary"></i> ERP Portal
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item py-2 text-danger fw-bold" href="logout.php">
                                    <i class="bi bi-power me-2"></i> LOGOUT
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- PAGE CONTENT -->
    <div class="content-main container-fluid">
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
                
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
                    <div>
                        <h3 class="fw-bold mb-1" style="color: #1e293b;">Selamat Datang, <?php echo htmlspecialchars($_SESSION['db_user']); ?>!</h3>
                        <p class="text-muted mb-0">Berikut adalah ringkasan sistem Inventory hari ini.</p>
                    </div>
                    <div class="mt-3 mt-md-0">
                        <h5 class="mb-0 fw-bold text-secondary text-uppercase fs-6">
                            <i class="bi bi-calendar3 me-2"></i> <?php echo date('d F Y'); ?>
                        </h5>
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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
    $(document).ready(function() {
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
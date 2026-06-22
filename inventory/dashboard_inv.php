<?php
// 1. CEK SESI & KONEKSI
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['db_user'])) { header("Location: login.php"); exit(); }
// MENJADI:
require_once __DIR__ . '/../config/database_p1.php';
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Inventory System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        /* --- GAYA ELEGAN (SEPERTI AWAL) --- */
        body {
            background: #f4f6f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            overflow-x: hidden;
        }

        /* Sidebar Asli yang Elegan */
        #sidebar {
            width: 240px; /* Lebar asli */
            height: 100vh;
            background: #1f2a36; /* Warna Gelap Premium */
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1050;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); /* Animasi halus */
            box-shadow: 2px 0 10px rgba(0,0,0,0.3);
            display: flex;
            flex-direction: column;
        }

        #sidebar .brand {
            padding: 20px;
            font-size: 17px;
            font-weight: 600;
            background: #273442; /* Header Sidebar */
            border-bottom: 1px solid rgba(255,255,255,0.05);
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
        }

        .nav-link {
            color: #aab0b6; /* Warna teks abu-abu elegan */
            padding: 12px 20px;
            font-size: 14px;
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }

        .nav-link:hover {
            background: #2c3e50;
            color: #fff;
            border-left-color: #5c7cfa; /* Sedikit biru saat hover */
        }

        .nav-link.active {
            background: #2c3e50;
            color: #fff !important;
            border-left-color: #3498db; /* Biru terang aktif */
            font-weight: 500;
        }

        .nav-link i { margin-right: 12px; font-size: 1.1rem; }

        /* Kategori Menu Kecil */
        .menu-label {
            padding: 15px 20px 5px 20px;
            font-size: 11px;
            text-transform: uppercase;
            color: #5b6e80;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        /* Footer Sidebar */
        .sidebar-footer {
            margin-top: auto;
            padding: 15px 20px;
            background: #19222c;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        /* --- LOGIKA RESPONSIVE (Media Queries) --- */
        #content {
            width: 100%;
            min-height: 100vh;
            transition: all 0.3s;
            padding: 25px;
            padding-left: 265px; /* 240px sidebar + 25px gap */
        }

        /* Tombol Burger (Hanya muncul di HP) */
        .btn-toggle-mobile {
            position: fixed; top: 15px; left: 15px; z-index: 2000;
            display: none; 
            background: #1f2a36; color: white; border: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }

        /* Overlay Gelap (Hanya muncul di HP) */
        .overlay {
            display: none; position: fixed;
            width: 100vw; height: 100vh; background: rgba(0,0,0,0.5);
            z-index: 1040; top: 0; left: 0;
        }

        /* TAMPILAN HP (< 768px) */
        @media (max-width: 768px) {
            #sidebar { margin-left: -240px; } /* Sembunyikan Sidebar */
            #sidebar.active { margin-left: 0; } /* Munculkan Sidebar */
            #content { padding-left: 20px; padding-top: 60px; } /* Konten Full Width */
            .btn-toggle-mobile { display: block; } /* Munculkan Tombol Burger */
            .overlay.active { display: block; } /* Munculkan Overlay */
        }
    </style>
</head>

<body>

    <button class="btn btn-toggle-mobile rounded-circle p-2" id="mobileToggle">
        <i class="bi bi-list fs-4"></i>
    </button>
    <div class="overlay"></div>

    <div id="sidebar">
        <div class="brand">
            <i class="bi bi-boxes"></i> INVENTORY
        </div>

        <div class="py-2 overflow-auto h-100">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="?page=home" class="nav-link <?php echo ($page=='home')?'active':''; ?>">
                        <i class="bi bi-speedometer2"></i> Dashboard
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
                <li class="nav-item">
    <a class="nav-link" href="?page=kumpulan_report">
        <i class="bi bi-folder2-open"></i> Kumpulan Report
    </a>
</li>
            </ul>
        </div>

        <div class="sidebar-footer">
            <div class="d-flex align-items-center mb-3">
                <div class="bg-success rounded-circle" style="width: 10px; height: 10px; margin-right: 8px;"></div>
                <small class="text-white-50">User: <strong class="text-white"><?php echo $_SESSION['db_user']; ?></strong></small>
            </div>
            
            <a href="../index.php" class="btn btn-outline-secondary w-100 btn-sm mb-2 text-white-50" style="border-color: #4a5568;">
                <i class="bi bi-arrow-left"></i> Kembali ke ERP
            </a>
            <a href="logout.php" class="btn btn-danger w-100 btn-sm">
                <i class="bi bi-box-arrow-right"></i> LOGOUT
            </a>
        </div>
    </div>

    <div id="content">
        <?php
        switch ($page) {
            case 'master': include "page_master.php"; break;
            case 'sop': include "page_sop.php"; break;
            case 'transaksi': include "page_transaksi.php"; break;
            case 'kumpulan_report': include 'page_kumpulan_report.php'; break;
            case 'home': default:
                ?>
                <h3 class="mb-4" style="font-weight: 600; color: #333;"><i class="bi bi-house"></i> Dashboard Overview</h3>
                <div class="row g-4">
                    <div class="col-12 col-md-4">
                        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #0d6efd;">
                            <div class="card-body">
                                <div class="text-muted small text-uppercase fw-bold">Total Items</div>
                                <div class="fs-3 fw-bold text-dark mt-1">
                                    <?php 
                                    $q = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM ITEMS WHERE ITEM_INACTIVE=0");
                                    $r = sqlsrv_fetch_array($q); echo number_format($r['t']); 
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                <?php break;
        }
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
    $(document).ready(function() {
        // Logic Toggle Sidebar Mobile
        $('#mobileToggle, .overlay').click(function() {
            $('#sidebar').toggleClass('active');
            $('.overlay').toggleClass('active');
        });

        // Global Select2
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
    });
    </script>

</body>
</html>
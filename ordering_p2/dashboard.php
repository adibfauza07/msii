<?php
require_once 'Middleware/Auth.php';
require_once 'Middleware/RoleCheck.php';

// Koneksi Database Plant 2
$serverName = "192.168.0.9";
$connectionOptions = array("Database" => "msData", "Uid" => $_SESSION['db_user'], "PWD" => $_SESSION['db_pass']);
$conn = sqlsrv_connect($serverName, $connectionOptions);

$page = isset($_GET['page']) ? $_GET['page'] : 'Home';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Ordering P2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
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

    <div id="sidebar">
        <div class="p-3 text-center fw-bold border-bottom border-secondary">ORDERING P2</div>
        <ul class="nav flex-column mt-3">
            <li class="nav-item">
                <a href="?page=home" class="nav-link <?= ($page=='home')?'active':'' ?>"><i class="bi bi-house"></i> Home</a>
            </li>
            <div class="menu-label">Transaksi</div>
            <li class="nav-item">
                <a href="?page=forecast" class="nav-link <?= ($page=='forecast')?'active':'' ?>"><i class="bi bi-graph-up"></i> Forecast</a>
            </li>
            <li class="nav-item">
                <a href="?page=di_auto" class="nav-link <?= ($page=='di_auto')?'active':'' ?>"><i class="bi bi-truck"></i> DI Auto Manual</a>
            </li>
            <div class="menu-label">System</div>
            <li class="nav-item">
                <a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-left"></i> Logout</a>
            </li>
        </ul>
    </div>

    <div id="content">
        <?php 
            switch($page) {
                case 'forecast': include "page_forecast.php"; break; // Referensi Ucast.pas
                case 'di_auto': include "page_di_auto.php"; break;   // Referensi UAuto_po.pas
                default: echo "<h3>Selamat Datang, ".$_SESSION['db_user']."</h3><p>Sistem Ordering Plant 2 Aktif.</p>"; break;
            }
        ?>
    </div>

</body>
</html>
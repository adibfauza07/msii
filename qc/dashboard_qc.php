<?php
// FILE: msii/qc/dashboard_qc.php

// 1. START SESSION
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 2. CEK LOGIN
if (!isset($_SESSION['db_user'])) {
    header("Location: login.php");
    exit();
}

// 3. LOGIC SWITCH PLANT
if (isset($_GET['plant'])) {
    $_SESSION['active_plant'] = $_GET['plant'];
}

// Default ke P1 jika belum ada
if (!isset($_SESSION['active_plant'])) {
    $_SESSION['active_plant'] = 'p1';
}

$active_plant = $_SESSION['active_plant'];
$plant_name = ($active_plant == 'p2') ? "PLANT 2" : "PLANT 1";
$theme_color = ($active_plant == 'p2') ? "warning" : "primary"; 

// --- KONFIGURASI DATABASE PENTING ---
// Variabel $conn wajib diciptakan di file ini
if ($active_plant == 'p2') {
    $_SESSION['erp_user'] = $_SESSION['db_user'];
    $_SESSION['erp_pass'] = $_SESSION['db_pass'];
    $_SESSION['server_sql'] = "192.168.0.9"; 
    $db_path = __DIR__ . '/../config/database.php'; 
} else {
    $db_path = __DIR__ . '/../config/database_p1.php';
}

// Eksekusi koneksi
if(file_exists($db_path)) {
    require_once $db_path; 
} else {
    $db_error = "File database tidak ditemukan: $db_path";
}

$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>QC Dashboard - <?php echo $plant_name; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        body { background: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; }
        #sidebar { width: 250px; height: 100vh; background: #1f2a36; color: white; position: fixed; top: 0; left: 0; z-index: 1050; display: flex; flex-direction: column; transition: 0.3s; }
        .nav-link { color: #aab0b6; padding: 12px 20px; text-decoration: none; display: block; border-left: 3px solid transparent; }
        .nav-link:hover, .nav-link.active { background: #2c3e50; color: #fff; border-left: 3px solid var(--bs-<?php echo $theme_color; ?>); } 
        .nav-link i { width: 25px; display: inline-block; }
        #content { margin-left: 250px; padding: 25px; transition: 0.3s; }
        .menu-label { padding: 15px 20px 5px; font-size: 11px; text-transform: uppercase; color: #6c757d; font-weight: bold; letter-spacing: 1px; }
        
        .plant-badge {
            background: <?php echo ($active_plant == 'p2') ? '#ffc107' : '#0d6efd'; ?>;
            color: <?php echo ($active_plant == 'p2') ? '#000' : '#fff'; ?>;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
            display: inline-block;
            margin-top: 5px;
        }

        @media (max-width: 768px) { 
            #sidebar { margin-left: -250px; } 
            #content { margin-left: 0; } 
        }
    </style>
</head>
<body>

<div id="sidebar">
    <div class="p-3 text-center fw-bold fs-5 border-bottom border-secondary bg-dark text-white">
        <i class="bi bi-shield-check text-<?php echo $theme_color; ?>"></i> QC SYSTEM
        <div class="plant-badge"><?php echo $plant_name; ?></div>
    </div>
    <div class="flex-grow-1 overflow-auto">
        <div class="menu-label">Menu Utama</div>
        <a href="?page=home" class="nav-link <?php echo $page=='home'?'active':''; ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
        
        <div class="menu-label">MENU OPERASIONAL</div>
        
        <?php if ($active_plant == 'p1'): ?>
            <a href="?page=kakotora&plant=p1" class="nav-link <?php echo ($page=='kakotora')?'active':''; ?>">
                <i class="bi bi-building text-primary"></i> Data Claim (Plant 1)
            </a>
            <a href="?page=usulan_perubahan_p1&plant=p1" class="nav-link <?php echo (($page=='usulan_perubahan_p1' || $page=='input_usulan' || $page=='edit_usulan'))?'active':''; ?>">
                <i class="bi bi-file-earmark-text text-primary"></i> Usulan Perubahan Plant 1
            </a>
        <?php else: ?>
            <a href="?page=kakotora&plant=p2" class="nav-link <?php echo ($page=='kakotora')?'active':''; ?>">
                <i class="bi bi-building-fill text-warning"></i> Data Claim (Plant 2)
            </a>
            <a href="?page=usulan_perubahan&plant=p2" class="nav-link <?php echo (($page=='usulan_perubahan' || $page=='input_usulan' || $page=='edit_usulan'))?'active':''; ?>">
                <i class="bi bi-file-earmark-text-fill text-warning"></i> Usulan Perubahan Plant 2
            </a>
        <?php endif; ?>

        
        <div class="menu-label">Laporan</div>
        <a href="#" class="nav-link"><i class="bi bi-file-earmark-bar-graph"></i> Summary Report</a>
        <!-- Tambahkan menu NG Production di sini -->
        <a href="?page=ng_prod" class="nav-link <?php echo ($page=='ng_prod')?'active':''; ?>">
            <i class="bi bi-display text-info"></i> NG Production
        </a>
    </div>
    <div class="p-3 border-top border-secondary bg-dark">
        <div class="d-flex align-items-center">
            <div class="bg-secondary rounded-circle text-white d-flex align-items-center justify-content-center" style="width: 35px; height: 35px; margin-right: 10px;">
                <i class="bi bi-person"></i>
            </div>
            <div style="font-size: 12px; line-height: 1.2;">
                <span class="d-block text-white fw-bold"><?php echo $_SESSION['db_user']; ?></span>
                <span class="text-muted">User Login</span>
            </div>
        </div>
        <a href="logout.php" class="btn btn-outline-danger btn-sm w-100 mt-3"><i class="bi bi-box-arrow-right"></i> LOGOUT</a>
    </div>
</div>

<div id="content" class="<?php echo ($page == 'home') ? 'night-mode' : ''; ?>" style="<?php echo ($page == 'home') ? 'min-height: 100vh;' : ''; ?>">
    <?php
    if (isset($db_error)) {
        echo "<div class='alert alert-danger'>$db_error</div>";
    }

    // 1. DASHBOARD HOME
// 1. DASHBOARD HOME


    if ($page == 'home') {
        echo "<h3>Dashboard Overview ($plant_name)</h3><hr>";
        echo "<div class='row'>";
        echo "<div class='col-md-4'><div class='card bg-$theme_color text-white p-3 mb-3'><h5>Total Claim</h5><h3>Check Data</h3></div></div>";
        echo "</div>";
    }
    // 2. KAKOTORA CLAIM
    elseif ($page == 'kakotora') {
        if (file_exists('page_kakotora.php')) {
            echo "<script>var currentPlantName = '$plant_name';</script>";
            include "page_kakotora.php";
        } else {
            echo "<div class='alert alert-danger'>File <b>page_kakotora.php</b> tidak ditemukan!</div>";
        }
    }
    // 3. LIST USULAN PERUBAHAN (GEMBOK SUDAH DIBUKA)
    elseif ($page == 'usulan_perubahan') {
        if (file_exists('page_usulan_perubahan.php')) { 
            include "page_usulan_perubahan.php"; 
        } else {
            echo "<div class='alert alert-danger'>File <b>page_usulan_perubahan.php</b> tidak ditemukan!</div>";
        }
    }
    // --- TAMBAHAN UNTUK LIST USULAN PLANT 1 ---
    elseif ($page == 'usulan_perubahan_p1') {
        if (file_exists('page_usulan_perubahan_p1.php')) { 
            include "page_usulan_perubahan_p1.php"; 
        } else {
            echo "<div class='alert alert-danger'>File <b>page_usulan_perubahan_p1.php</b> tidak ditemukan!</div>";
        }
    }
    // 4. FORM INPUT USULAN BARU (GEMBOK SUDAH DIBUKA)
    elseif ($page == 'input_usulan') {
        if (file_exists('input_usulan.php')) { 
            include "input_usulan.php"; 
        } else {
            echo "<div class='alert alert-danger'>File <b>input_usulan.php</b> tidak ditemukan!</div>";
        }
    }
// ... (kode sebelumnya) ...

    // 5. FORM EDIT USULAN (GEMBOK SUDAH DIBUKA)
    elseif ($page == 'edit_usulan') {
        if (file_exists('edit_usulan.php')) { 
            include "edit_usulan.php"; 
        } else {
            echo "<div class='alert alert-danger'>File <b>edit_usulan.php</b> tidak ditemukan!</div>";
        }
    }
    
    // --- TAMBAHAN BARU: HALAMAN NG PRODUCTION ---
    elseif ($page == 'ng_prod') {
        if (file_exists('ng_prod.php')) {
            // Kita tambahkan class night-mode secara dinamis agar tampilannya tetap gelap/keren
            echo "<script>document.getElementById('content').classList.add('night-mode');</script>";
            include "ng_prod.php";
        } else {
            echo "<div class='alert alert-danger'>File <b>ng_prod.php</b> tidak ditemukan!</div>";
        }
    }

    // 6. JIKA HALAMAN TIDAK ADA
    else {
        echo "<div class='alert alert-info'>Halaman tidak ditemukan.</div>";
    }
    
    ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Update Judul Halaman Kakotora secara dinamis
    if(typeof currentPlantName !== 'undefined' && document.getElementById('pageTitle')) {
        document.getElementById('pageTitle').innerHTML = '<i class="bi bi-list-task"></i> DATA KAKOTORA - ' + currentPlantName;
    }
</script>

</body>
</html>
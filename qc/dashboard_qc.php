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

$page = isset($_GET['page']) ? trim($_GET['page']) : 'home';

// --- BLOK KODE UNTUK EXPORT EXCEL REPORT QC ---
if ($page == 'report_qc' && isset($_GET['action']) && $_GET['action'] == 'excel') {
    while (ob_get_level()) ob_end_clean(); 
    
    if (file_exists('page_report.php')) {
        include "page_report.php";
        exit;
    }
}
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
        body { 
            background: #e9ecef; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        }
        
        .navbar-custom {
            background-color: #e3f2fd;
            border-bottom: 3px solid #dc3545;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .navbar-custom .navbar-brand {
            font-weight: 800;
            color: #0d6efd;
            font-size: 1.2rem;
        }
        .navbar-custom .nav-link {
            color: #0d6efd;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 10px 15px;
        }
        .navbar-custom .nav-link:hover, .navbar-custom .nav-link.active {
            background-color: #cce5ff;
            border-radius: 4px;
        }
        .navbar-custom .dropdown-menu {
            border: 1px solid #b6d4fe;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .navbar-custom .dropdown-item {
            font-size: 0.85rem;
            font-weight: 500;
        }
        .navbar-custom .dropdown-item i {
            width: 20px;
        }
        
        #content {
            padding: 20px;
            background: #f8f9fa;
            min-height: calc(100vh - 60px);
        }

        @media print {
            .navbar-custom {
                display: none !important;
            }
            #content {
                padding: 0 !important;
                background: #fff !important;
            }
        }
    </style>
</head>
<body>

<!-- TOP NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-custom sticky-top">
    <div class="container-fluid">
        <!-- Brand & Plant -->
        <a class="navbar-brand" href="?page=home">
            <i class="bi bi-shield-check"></i> QC System <span class="badge bg-<?php echo $theme_color; ?> text-dark ms-1"><?php echo $plant_name; ?></span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topMenu" aria-controls="topMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="topMenu">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 align-items-center">
                
                <!-- Menu Dashboard -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $page=='home'?'active':''; ?>" href="?page=home">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                
                <!-- Menu Input Data -->
                <li class="nav-item">
                    <a class="nav-link text-success <?php echo $page=='input_problem'?'active':''; ?>" href="?page=input_problem&plant=<?php echo $active_plant; ?>">
                        <i class="bi bi-ui-checks-grid"></i> Input QC Problem
                    </a>
                </li>

                <!-- Menu Operasional (Dropdown) -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo in_array($page, ['kakotora','usulan_perubahan','usulan_perubahan_p1','input_usulan','edit_usulan','kalibrasi'])?'active':''; ?>" href="#" id="menuOperasional" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-list-check"></i> Menu Operasional
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="menuOperasional">
                        <?php if ($active_plant == 'p1'): ?>
                            <li><a class="dropdown-item" href="?page=kakotora&plant=p1"><i class="bi bi-building text-primary"></i> Data Claim (Plant 1)</a></li>
                            <li><a class="dropdown-item" href="?page=usulan_perubahan_p1&plant=p1"><i class="bi bi-file-earmark-text text-primary"></i> Usulan Perubahan Plant 1</a></li>
                            <li><a class="dropdown-item" href="?page=kalibrasi"><i class="bi bi-tools text-primary"></i> Kalibrasi Plant 1</a></li>
                        <?php else: ?>
                            <li><a class="dropdown-item" href="?page=kakotora&plant=p2"><i class="bi bi-building-fill text-warning"></i> Data Claim (Plant 2)</a></li>
                            <li><a class="dropdown-item" href="?page=usulan_perubahan&plant=p2"><i class="bi bi-file-earmark-text-fill text-warning"></i> Usulan Perubahan Plant 2</a></li>
                            <li><a class="dropdown-item" href="?page=kalibrasi"><i class="bi bi-tools text-warning"></i> Kalibrasi Plant 2</a></li>
                        <?php endif; ?>
                    </ul>
                </li>

                <!-- Menu Laporan (Dropdown) -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo in_array($page, ['ng_prod', 'report_qc', 'delivery_item_list', 'delivery_item_list_12month'])?'active':''; ?>" href="#" id="menuLaporan" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-file-earmark-bar-graph"></i> Laporan
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="menuLaporan">
                        <li><a class="dropdown-item" href="?page=report_qc&plant=<?php echo $active_plant; ?>"><i class="bi bi-file-earmark-bar-graph text-success"></i> DIC REPORT</a></li>
                        <li><a class="dropdown-item" href="?page=ng_prod"><i class="bi bi-display text-info"></i> NG Production</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="?page=delivery_item_list_12month&plant=<?php echo $active_plant; ?>"><i class="bi bi-box-seam text-primary"></i> Item List (12M)</a></li>
                    </ul>
                </li>
            </ul>

            <!-- Info User & Logout -->
            <div class="d-flex align-items-center">
                <span class="text-primary fw-bold me-3">
                    <i class="bi bi-person-circle"></i> <?php echo $_SESSION['db_user']; ?>
                </span>
                <a href="logout.php" class="btn btn-danger btn-sm fw-bold shadow-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- MAIN CONTENT AREA -->
<div id="content" class="<?php echo ($page == 'home') ? 'night-mode' : ''; ?>">
    <?php
    if (isset($db_error)) {
        echo "<div class='alert alert-danger'>$db_error</div>";
    }

    // 1. DASHBOARD HOME
    if ($page == 'home') {
        echo "<div class='card shadow-sm'><div class='card-body text-center py-5'>";
        echo "<h3 class='text-uppercase fw-bold mb-3'>Dashboard Overview ($plant_name)</h3><hr>";
        echo "<div class='row justify-content-center mt-4'>";
        echo "<div class='col-md-4'><div class='card bg-$theme_color text-white p-4 shadow'><h5>Total Claim</h5><h2 class='fw-bold'>Check Data</h2></div></div>";
        echo "</div>";
        echo "</div></div>";
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
    // 3. LIST USULAN PERUBAHAN
    elseif ($page == 'usulan_perubahan') {
        if (file_exists('page_usulan_perubahan.php')) { include "page_usulan_perubahan.php"; } 
        else { echo "<div class='alert alert-danger'>File <b>page_usulan_perubahan.php</b> tidak ditemukan!</div>"; }
    }
    elseif ($page == 'usulan_perubahan_p1') {
        if (file_exists('page_usulan_perubahan_p1.php')) { include "page_usulan_perubahan_p1.php"; } 
        else { echo "<div class='alert alert-danger'>File <b>page_usulan_perubahan_p1.php</b> tidak ditemukan!</div>"; }
    }
    elseif ($page == 'input_usulan') {
        if (file_exists('input_usulan.php')) { include "input_usulan.php"; } 
        else { echo "<div class='alert alert-danger'>File <b>input_usulan.php</b> tidak ditemukan!</div>"; }
    }
    elseif ($page == 'edit_usulan') {
        if (file_exists('edit_usulan.php')) { include "edit_usulan.php"; } 
        else { echo "<div class='alert alert-danger'>File <b>edit_usulan.php</b> tidak ditemukan!</div>"; }
    }
    // 4. HALAMAN LAPORAN & NG PRODUCTION
    elseif ($page == 'report_qc') {
        if (file_exists('page_report.php')) {
            include "page_report.php";
        } else {
            echo "<div class='alert alert-danger'>File <b>page_report.php</b> tidak ditemukan!</div>";
        }
    }
    elseif ($page == 'ng_prod') {
        if (file_exists('ng_prod.php')) {
            echo "<script>document.getElementById('content').classList.add('night-mode');</script>";
            include "ng_prod.php";
        } else {
            echo "<div class='alert alert-danger'>File <b>ng_prod.php</b> tidak ditemukan!</div>";
        }
    }
    // 5. HALAMAN DELIVERY ITEM LIST (MENDUKUNG KEDUA PARAMETER URL)
    elseif ($page == 'delivery_item_list' || $page == 'delivery_item_list_12month') {
        if (file_exists(__DIR__ . '/delivery_item_list_12month.php')) {
            include __DIR__ . '/delivery_item_list_12month.php';
        } elseif (file_exists(__DIR__ . '/../ordering/delivery_item_list_12month.php')) {
            include __DIR__ . '/../ordering/delivery_item_list_12month.php';
        } else {
            echo "<div class='alert alert-danger'>File <b>delivery_item_list_12month.php</b> tidak ditemukan di folder <code>msii/qc/</code>! Pastikan file tersebut sudah disimpan di folder yang sama dengan <code>dashboard_qc.php</code>.</div>";
        }
    }
    // 6. HALAMAN KALIBRASI
    elseif ($page == 'kalibrasi') {
        if (file_exists('kalibrasi.php')) { include "kalibrasi.php"; } 
        else { echo "<div class='alert alert-danger'>File <b>kalibrasi.php</b> tidak ditemukan!</div>"; }
    }
    // 7. FORM INPUT MASTER DETAIL QC PROBLEM
    elseif ($page == 'input_problem') {
        if (file_exists('input_problem.php')) {
            include "input_problem.php";
        } else {
            echo "<div class='alert alert-danger'>File <b>input_problem.php</b> tidak ditemukan!</div>";
        }
    }
    // 8. JIKA HALAMAN TIDAK ADA
    else {
        echo "<div class='alert alert-info'>Halaman tidak ditemukan.</div>";
    }
    ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    if(typeof currentPlantName !== 'undefined' && document.getElementById('pageTitle')) {
        document.getElementById('pageTitle').innerHTML = '<i class="bi bi-list-task"></i> DATA KAKOTORA - ' + currentPlantName;
    }
</script>

</body>
</html>
<?php
// 1. CEK SESI & KONEKSI
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['db_user'])) { header("Location: ../login.php"); exit(); }

require_once __DIR__ . '/../config/database_p1.php';
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>ERP System - Exim Department</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        /* Mengadopsi Style dari dashboard_inv.php */
        body { background: #f4f6f9; font-family: -apple-system, sans-serif; overflow-x: hidden; }
        #sidebar { 
            width: 240px; height: 100vh; background: #1a1a2e; /* Warna lebih gelap untuk Exim */
            color: white; position: fixed; z-index: 1050; transition: all 0.3s;
            display: flex; flex-direction: column; box-shadow: 2px 0 10px rgba(0,0,0,0.3);
        }
        #sidebar .brand { 
            padding: 20px; font-size: 17px; font-weight: 600; background: #16213e; 
            text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .nav-link { color: #aab0b6; padding: 12px 20px; border-left: 3px solid transparent; }
        .nav-link:hover, .nav-link.active { 
            background: #0f3460; color: #fff !important; border-left-color: #e94560; 
        }
        #content { width: 100%; padding: 25px; padding-left: 265px; transition: all 0.3s; }
        .menu-label { padding: 15px 20px 5px; font-size: 11px; color: #5b6e80; font-weight: 700; text-transform: uppercase; }
        
        @media (max-width: 768px) {
            #sidebar { margin-left: -240px; }
            #sidebar.active { margin-left: 0; }
            #content { padding-left: 20px; padding-top: 60px; }
        }
    </style>
</head>
<body>

    <button class="btn btn-dark position-fixed d-md-none" style="top:15px; left:15px; z-index:2000" id="mobileToggle">
        <i class="bi bi-list"></i>
    </button>

    <div id="sidebar">
        <div class="brand"><i class="bi bi-ship"></i> EXIM MODULE</div>
        <div class="py-2 overflow-auto h-100">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="?page=home" class="nav-link <?php echo ($page=='home')?'active':''; ?>">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <div class="menu-label">Dokumen Pabean</div>
                <li class="nav-item">
                    <a href="?page=bc27" class="nav-link <?php echo ($page=='bc27')?'active':''; ?>">
                        <i class="bi bi-file-earmark-text"></i> BC 2.7 (TPB)
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=status" class="nav-link <?php echo ($page=='status')?'active':''; ?>">
                        <i class="bi bi-cloud-arrow-up"></i> Ceisa Status
                    </a>
                </li>
            </ul>
        </div>
        <div class="sidebar-footer p-3 bg-dark">
            <small class="text-white-50 d-block mb-2">User: <?php echo $_SESSION['db_user']; ?></small>
            <a href="../index.php" class="btn btn-outline-light btn-sm w-100 mb-2">Kembali ke ERP</a>
            <a href="logout.php" class="btn btn-danger btn-sm w-100">Logout</a>
        </div>
    </div>

    <div id="content">
        <?php
        switch ($page) {
            case 'status': include "page_status.php"; break;
            case 'bc27': include "page_bc27.php"; break;
            case 'home': default:
                ?>
                <h3 class="mb-4"><i class="bi bi-house"></i> Exim Overview</h3>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm p-3" style="border-left: 4px solid #e94560;">
                            <div class="text-muted small fw-bold">DRAFT BC 2.7</div>
                            <div class="fs-3 fw-bold">12</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm p-3" style="border-left: 4px solid #0d6efd;">
                            <div class="text-muted small fw-bold">TERKIRIM KE CEISA</div>
                            <div class="fs-3 fw-bold">45</div>
                        </div>
                    </div>
                </div>
                <?php break;
        }
        ?>
    </div>

    <script>
        $('#mobileToggle').click(function() { $('#sidebar').toggleClass('active'); });
    </script>
</body>
</html>
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
    <title>4M Change System - PT. IMC Tekno Indonesia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <style>
        body { background: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; }
        #sidebar { width: 260px; height: 100vh; background: #1f2a36; color: white; position: fixed; display: flex; flex-direction: column; z-index: 1000; transition: all 0.3s; }
        #sidebar .brand { padding: 20px; font-size: 16px; font-weight: 700; background: #8b5cf6; text-align: center; text-transform: uppercase; letter-spacing: 1px; }
        .menu-label { font-size: 11px; letter-spacing: 1px; color: #64748b; padding-left: 20px; margin-top: 15px; margin-bottom: 5px; }
        .nav-link { color: #aab0b6; padding: 10px 20px; font-size: 13.5px; border-left: 4px solid transparent; display: flex; align-items: center; gap: 10px; transition: all 0.2s; }
        .nav-link:hover { background: #243342; color: #fff; }
        .nav-link.active { background: #2c3e50; color: #fff !important; border-left-color: #a78bfa; font-weight: 600; }
        #content { padding: 30px; padding-left: 290px; transition: all 0.3s; }
        .card { border-radius: 10px; border: none; }
        @media (max-width: 768px) { #sidebar { margin-left: -260px; } #content { padding-left: 20px; } }
    </style>
</head>
<body>

    <div id="sidebar">
        <div class="brand"><i class="bi bi-arrow-repeat me-2"></i>4M Change System</div>
        <div class="py-2 overflow-auto h-100">
            <ul class="nav flex-column">
                <li class="nav-item"><a href="?page=home" class="nav-link <?php echo ($page=='home')?'active':''; ?>"><i class="bi bi-speedometer2"></i> Dashboard Overview</a></li>
                <div class="menu-label fw-bold">PROSES PERUBAHAN</div>
                <li class="nav-item"><a href="input_pcis.php" class="nav-link <?php echo ($page=='input_pcis')?'active':''; ?>"><i class="bi bi-plus-circle"></i> Input 4M Change</a></li>
                <li class="nav-item"><a href="?page=history" class="nav-link <?php echo ($page=='history')?'active':''; ?>"><i class="bi bi-clock-history"></i> Riwayat Perubahan</a></li>
                <li class="nav-item"><a href="?page=rekap" class="nav-link <?php echo ($page=='rekap')?'active':''; ?>"><i class="bi bi-journal-text"></i> Rekap Summary (SP)</a></li>
            </ul>
        </div>
        <div class="sidebar-footer p-3 bg-dark mt-auto">
            <small class="text-white-50 d-block mb-2"><i class="bi bi-person-circle me-1"></i> <?php echo $_SESSION['erp_user']; ?></small>
            <a href="logout.php" class="btn btn-danger w-100 btn-sm">
                <i class="bi bi-box-arrow-right"></i> LOGOUT
            </a><hr>
            <a href="../index.php" class="btn btn-sm btn-outline-light w-100"><i class="bi bi-box-arrow-left"></i> Kembali ke ERP</a>
            
        </div>
    </div>

    <div id="content">
        <?php
        // Jalankan intercept form post action_4m secara internal sebelum merender halaman (Menghindari 404!)
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
                <h4 class="fw-bold mb-4 text-dark">4M Change Management System Overview</h4>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0" style="border-left: 4px solid #8b5cf6 !important;">
                            <div class="card-body p-4">
                                <div class="text-muted small fw-bold text-uppercase">Total Pengajuan PCIS</div>
                                <div class="fs-2 fw-bold text-dark mt-1">
                                    <?php 
                                    $res = q("SELECT COUNT(*) as total FROM PROSES_CHANGE");
                                    $data = sqlsrv_fetch_array($res);
                                    echo $data['total'] ? number_format($data['total']) : '0';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                break;
        }
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
</body>
</html>
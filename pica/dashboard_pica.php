<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['db_user'])) { header("Location: ../login.php"); exit(); }

require_once __DIR__ . '/../config/database_p1.php';
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>PICA System</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f4f6f9; font-family: -apple-system, sans-serif; overflow-x: hidden; }
        #sidebar { width: 240px; height: 100vh; background: #1f2a36; color: white; position: fixed; top: 0; left: 0; z-index: 1050; display: flex; flex-direction: column; }
        #sidebar .brand { padding: 20px; font-size: 17px; font-weight: 600; background: #273442; color: #fff; text-align: center; }
        .nav-link { color: #aab0b6; padding: 12px 20px; font-size: 14px; border-left: 3px solid transparent; }
        .nav-link:hover, .nav-link.active { background: #2c3e50; color: #fff !important; border-left-color: #3498db; }
        .menu-label { padding: 15px 20px 5px 20px; font-size: 11px; text-transform: uppercase; color: #5b6e80; font-weight: 700; }
        #content { width: 100%; min-height: 100vh; padding: 25px 25px 25px 265px; }
        .sidebar-footer { margin-top: auto; padding: 15px 20px; background: #19222c; }
    </style>
</head>
<body>
    <div id="sidebar">
        <div class="brand"><i class="bi bi-file-earmark-ruled"></i> PICA REPORT</div>
        <div class="py-2 overflow-auto h-100">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="?page=home" class="nav-link <?php echo ($page=='home')?'active':''; ?>">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <div class="menu-label">Menu Utama</div>
                <li class="nav-item">
                    <a href="?page=input_pica" class="nav-link <?php echo ($page=='input_pica')?'active':''; ?>">
                        <i class="bi bi-pencil-square"></i> Input PICA Baru
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=list_pica" class="nav-link <?php echo ($page=='list_pica')?'active':''; ?>">
                        <i class="bi bi-card-list"></i> Data PICA
                    </a>
                </li>
            </ul>
        </div>
        <div class="sidebar-footer">
            <div class="d-flex align-items-center mb-3">
                <small class="text-white-50">User: <strong class="text-white"><?php echo $_SESSION['db_user']; ?></strong></small>
            </div>
            <a href="../index.php" class="btn btn-outline-secondary w-100 btn-sm mb-2"><i class="bi bi-arrow-left"></i> Menu ERP</a>
            <a href="../pica/logout.php" class="btn btn-danger w-100 btn-sm"><i class="bi bi-box-arrow-right"></i> LOGOUT</a>
        </div>
    </div>

    <div id="content">
        <?php
        switch ($page) {
            case 'input_pica': 
                include "page_input_pica.php"; 
                break;
            case 'list_pica': 
                include "page_list_pica.php"; 
                break;
            case 'cetak_pica': 
                include "page_cetak_pica.php"; 
                break;
            // PASTIKAN BARIS INI ADA:
            case 'edit_pica': 
                include "page_edit_pica.php"; 
                break;
            case 'home': default:
                ?>
                <h3 class="mb-4"><i class="bi bi-house"></i> Dashboard PICA</h3>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm" style="border-left: 4px solid #dc3545;">
                            <div class="card-body">
                                <div class="text-muted small text-uppercase fw-bold">Total Report PICA</div>
                                <div class="fs-3 fw-bold text-dark mt-1">
                                    <?php 
                                    // Contoh mengambil jumlah report dari database
                                    $q = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM PICA_HEADER");
                                    if($q) {
                                        $r = sqlsrv_fetch_array($q); 
                                        echo number_format($r['total']); 
                                    } else {
                                        echo "0";
                                    }
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

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    // Mengaktifkan fitur searchable dropdown
    $('.select2-search').select2({
        theme: 'bootstrap-5',
        placeholder: 'Ketik untuk mencari...',
        allowClear: true
    });
});
</script>
</body>
</html>
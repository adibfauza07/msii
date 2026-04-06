<?php
// 1. CEK SESI & MIDDLEWARE

require_once __DIR__ . '/../config/database.php'; // Koneksi DB & Fungsi q()

$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>4M Change System - PT. IMC Tekno</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSS dari Boilerplate Inventory kamu[cite: 8] -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        /* Mengambil gaya sidebar elegan dari source[cite: 8] */
        body { background: #f4f6f9; font-family: sans-serif; overflow-x: hidden; }
        #sidebar {
            width: 240px; height: 100vh; background: #1f2a36; 
            color: white; position: fixed; display: flex; flex-direction: column;
        }
        #sidebar .brand {
            padding: 20px; font-size: 17px; font-weight: 600; 
            background: #8b5cf6; /* Warna Ungu (seperti warna PPIC di portal)[cite: 14] */
            text-align: center; text-transform: uppercase;
        }
        .nav-link { color: #aab0b6; padding: 12px 20px; font-size: 14px; border-left: 3px solid transparent; }
        .nav-link.active { background: #2c3e50; color: #fff !important; border-left-color: #a78bfa; }
        #content { padding: 25px; padding-left: 265px; }
        
        /* Tombol responsive[cite: 8] */
        @media (max-width: 768px) {
            #sidebar { margin-left: -240px; }
            #content { padding-left: 20px; }
        }
    </style>
</head>
<body>

    <div id="sidebar">
        <div class="brand">
            <i class="bi bi-arrow-repeat"></i> FOR M CHANGE
        </div>

        <div class="py-2 overflow-auto h-100">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="?page=home" class="nav-link <?php echo ($page=='home')?'active':''; ?>">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>

                <div class="menu-label text-muted px-4 small fw-bold mt-3">PROSES PERUBAHAN</div>
                
                <li class="nav-item">
                    <a href="?page=input_change" class="nav-link <?php echo ($page=='input_change')?'active':''; ?>">
                        <i class="bi bi-plus-circle"></i> Input 4M Change
                    </a>
                </li>

                <li class="nav-item">
                    <a href="?page=history" class="nav-link <?php echo ($page=='history')?'active':''; ?>">
                        <i class="bi bi-clock-history"></i> Riwayat Perubahan
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-footer p-3 bg-dark mt-auto">
            <small class="text-white-50 d-block mb-2">User: <?php echo $_SESSION['erp_user']; ?></small>
            <a href="../index.php" class="btn btn-sm btn-outline-light w-100 mb-1">Kembali ke ERP</a>
        </div>
    </div>

    <div id="content">
        <?php
        switch ($page) {
            case 'input_change':
                // PERBAIKAN: Memanggil file form yang sudah kita buat
                if (file_exists("page_input_4m.php")) {
                    include "page_input_4m.php";
                } else {
                    echo "<div class='alert alert-danger'>File page_input_4m.php tidak ditemukan!</div>";
                }
                break;

            case 'history':
                // PERBAIKAN: Memanggil file tabel riwayat
                if (file_exists("page_history.php")) {
                    include "page_history.php";
                } else {
                    echo "<div class='alert alert-danger'>File page_history.php tidak ditemukan!</div>";
                }
                break;

            case 'home':
            default:
                ?>
                <h3 class="mb-4">4M Change Overview</h3>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0" style="border-left: 4px solid #8b5cf6;">
                            <div class="card-body">
                                <div class="text-muted small fw-bold text-uppercase">Total Pengajuan PCIS</div>
                                <div class="fs-2 fw-bold">
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

</body>
</html>
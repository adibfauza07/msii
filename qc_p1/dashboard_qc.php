<?php
// Tentukan halaman yang aktif. Defaultnya adalah 'home'.
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard QC</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Menggunakan path aset dari file QC asli -->
    <link href="../assets/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Mengambil CSS styling dari dashboard_ppic.php -->
    <style>
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f4f6f9;
        }

        /* SIDEBAR */
        #sidebar {
            width: 240px;
            height: 100vh;
            background: #1f2a36; /* Warna gelap untuk sidebar */
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            box-shadow: 2px 0 5px rgba(0,0,0,0.2);
            z-index: 1000;
        }

        #sidebar h4 {
            padding: 18px 20px;
            margin: 0;
            font-size: 17px;
            background: #273442; /* Warna header sidebar */
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .nav-link {
            color: #e5e5e5; /* Warna teks link */
            font-size: 14px;
            padding: 12px 20px;
            display: block;
            transition: 0.15s;
            cursor: pointer;
            text-decoration: none; /* Hapus-Garis bawah */
        }

        .nav-link:hover {
            background: #324257; /* Warna saat hover */
            color: #fff;
        }

        /* SUBMENU (jika diperlukan) */
        .submenu {
            margin-left: 10px;
        }

        .submenu .nav-link {
            padding-left: 35px;
            font-size: 13px;
            color: #cccccc;
        }

        .submenu .nav-link:hover {
            background: #2c3a4b;
        }

        /* Tombol 'Kembali ke ERP' */
        .erp-link-wrapper {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 15px 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            background: #1f2a36;
        }
        .erp-link {
            background-color: #4a5a6a;
            color: #fff;
            text-align: center;
            border-radius: 5px;
        }
        .erp-link:hover {
            background-color: #5a6c7d;
            color: #fff;
        }


        /* ACTIVE */
        .active {
            background: #405066 !important; /* Warna link aktif */
            font-weight: bold;
            color: white !important;
        }

        /* CONTENT */
        #content {
            margin-left: 240px; /* Lebar yang sama dengan sidebar */
            padding: 30px;
        }

        .header-line {
            border-bottom: 1px solid #dcdcdc;
            margin: 12px 0 20px 0;
        }

        h2 {
            margin: 0;
            font-size: 22px;
            font-weight: bold;
            color: #333;
        }
    </style>
</head>

<body>

<!-- SIDEBAR -->
<div id="sidebar">

    <h4><i class="bi bi-patch-check"></i> Dashboard QC</h4>

    <ul class="nav flex-column">

        <!-- DASHBOARD -->
        <li class="nav-item">
            <a href="?page=home" class="nav-link <?php echo ($page=='home')?'active':''; ?>">
                <i class="bi bi-house-door"></i> Dashboard
            </a>
        </li>

        <!-- USULAN PERUBAHAN -->
        <li class="nav-item">
            <!-- Judul Grup Menu -->
            <a class="nav-link" style="color: #6c757d; font-size: 12px; text-transform: uppercase; font-weight: bold; padding-bottom: 5px;">
                Usulan Perubahan
            </a>

            <!-- Submenu untuk Plant -->
            <div class="submenu">
                <a href="?page=p1" 
                   class="nav-link <?php echo ($page=='p1')?'active':''; ?>">
                   <i class="bi bi-building"></i> Plant 1 (Serplan1)
                </a>

                <a href="?page=p2" 
                   class="nav-link <?php echo ($page=='p2')?'active':''; ?>">
                   <i class="bi bi-building"></i> Plant 2 (Serplan3)
                </a>
            </div>
        </li>

    </ul>

    <!-- Tombol Kembali ke Menu Utama ERP -->
    <div class="erp-link-wrapper">
      <a href="../index.php" class="nav-link erp-link">
        <i class="bi bi-arrow-left-circle"></i> Kembali ke ERP
      </a>
    </div>

</div>

<!-- CONTENT -->
<div id="content">
<?php
// ROUTING PAGE (Mengarahkan konten berdasarkan 'page')
if ($page == 'home') {
    echo "<h2><i class_=\"bi bi-house-door\"></i> Dashboard QC</h2>";
    echo "<div class='header-line'></div>";
    echo "<p>Selamat datang di Dashboard Quality Control. Silakan pilih menu di sidebar untuk melihat data.</p>";
    
    // Anda bisa tambahkan ringkasan atau widget di sini
    echo "<div class='alert alert-info'>Halaman ini adalah halaman utama Dashboard QC. Konten untuk Plant 1 dan Plant 2 akan dimuat di sini.</div>";

}
// Jika 'page' adalah 'p1', includekan file usulan perubahan Plant 1
elseif ($page == 'p1') { 
    // Path ini diambil dari file QC asli
    include "../qc_p1/usulan_perubahan.php"; 
}
// Jika 'page' adalah 'p2', includekan file usulan perubahan Plant 2
elseif ($page == 'p2') { 
    // Path ini diambil dari file QC asli
    include "usulan_perubahan.php"; 
}
// Tambahan jika ada halaman lain
// elseif ($page == 'nama_halaman_lain') { 
//     include 'file_halaman_lain.php'; 
// }
?>
</div>

<!-- Menggunakan path aset dari file QC asli -->
<script src="../assets/bootstrap.bundle.min.js"></script>
<!-- Jika butuh jQuery, tambahkan di sini -->
<!-- <script src="../assets/jquery.min.js"></script> -->

</body>
</html>
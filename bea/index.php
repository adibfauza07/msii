<?php
$dbConnected = false;
$dbError = '';
$conn = false;

// Memanggil file konfigurasi database
require_once __DIR__ . '/config/database.php';

if (isset($conn) && $conn !== false) {
    $dbConnected = true;
} else {
    $dbError = 'Koneksi SQL Server tidak tersedia.';
}

// ---------------------------------------------------------
// TANGKAP PERMINTAAN HALAMAN DARI MENU DROPDOWN
// ---------------------------------------------------------
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>IT Inventory P.T. IMC TEKNO INDONESIA</title>
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    
    <!-- Link CSS Bootstrap & AdminLTE -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/2.4.18/css/AdminLTE.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/2.4.18/css/skins/_all-skins.min.css">
    
    <!-- JQUERY UI CSS (Untuk Tampilan Dropdown Autocomplete) -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">

    <!-- jQuery (Wajib paling atas) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    
    <!-- JQUERY UI JS (Wajib di bawah jQuery agar fungsi autocomplete aktif) -->
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
</head>
<body class="hold-transition skin-blue layout-top-nav">
<div class="wrapper">

  <header class="main-header">
    <nav class="navbar navbar-static-top">
      <div class="container-fluid"> <!-- Diubah ke container-fluid agar tabel/form lebih luas -->
        <div class="navbar-header">
          <a href="index.php" class="navbar-brand"><b>IT Inventory</b> P.T. IMC</a>
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-collapse">
            <i class="fa fa-bars"></i>
          </button>
        </div>

        <!-- Bagian Menu Dropdown di Atas -->
        <div class="collapse navbar-collapse pull-left" id="navbar-collapse">
          <ul class="nav navbar-nav">
            
            <!-- 0. Menu Master -->
            <li class="dropdown <?php echo (strpos($page, 'master_') !== false) ? 'active' : ''; ?>">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Master <span class="caret"></span></a>
              <ul class="dropdown-menu" role="menu">
                <li><a href="?page=master_barang">Master Barang</a></li>
                <li><a href="?page=master_bom">BOM (Bill of Materials)</a></li>
                <li><a href="?page=master_price">Price, Customer, Currency, Supplier</a></li>
                <li><a href="?page=master_unit">Unit</a></li>
              </ul>
            </li>

            <!-- 1. Menu Planning dan Ordering -->
            <li class="dropdown <?php echo (strpos($page, 'plan_') !== false) ? 'active' : ''; ?>">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Ordering <span class="caret"></span></a>
              <ul class="dropdown-menu" role="menu">
                <li><a href="?page=plan_forecast">Forecast, Order, Schedule</a></li>
                <li><a href="?page=plan_gen">Generate DI</a></li>
                 <li><a href="?page=plan_schedule">Delivery Instruction</a></li>
              </ul>
            </li>
            
            <!-- 2. Menu pemesanan barang -->
            <li class="dropdown <?php echo (strpos($page, 'order_') !== false) ? 'active' : ''; ?>">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Purchasing <span class="caret"></span></a>
              <ul class="dropdown-menu" role="menu">
                <li><a href="?page=order_pr">PR, Quotation, Purchase Order</a></li>
                <li><a href="?page=order_rec">Receive Suppier</a></li>
              </ul>
            </li>
            
            <!-- 3. Menu transaksi in dan out -->
            <li class="dropdown <?php echo (strpos($page, 'proses_') !== false) ? 'active' : ''; ?>">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Inventory Transaction <span class="caret"></span></a>
              <ul class="dropdown-menu" role="menu">
                <li><a href="?page=sop_trans">SOP & TRANS</a></li>
              </ul>
            </li>
            
            <!-- 4. Menu Proses -->
            <li class="dropdown <?php echo (strpos($page, 'proses_') !== false) ? 'active' : ''; ?>">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown"> PROSES <span class="caret"></span></a>
              <ul class="dropdown-menu" role="menu">
                <li><a href="?page=wo">WORK ORDER</a></li>
                <li><a href="?page=sms">SUPPLY MATERIAL</a></li>
                <li><a href="?page=prod">PRODUCTION</a></li>
              </ul>
            </li>
            
            <!-- 5. Menu Accounting -->
            <li class="dropdown <?php echo (strpos($page, 'acc_') !== false) ? 'active' : ''; ?>">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Accounting <span class="caret"></span></a>
              <ul class="dropdown-menu" role="menu">
                <li><a href="?page=acc_jurnal">Jurnal</a></li>
                <li><a href="?page=acc_laporan">Laporan Keuangan</a></li>
              </ul>
            </li>
            
            <!-- 6. Menu User -->
            <li class="dropdown <?php echo (strpos($page, 'acc_') !== false) ? 'active' : ''; ?>">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Daftar User <span class="caret"></span></a>
              <ul class="dropdown-menu" role="menu">
                <li><a href="?page=acc_jurnal">User Log</a></li>
                <li><a href="?page=acc_laporan">Change password</a></li>
              </ul>
            </li>

            <!-- 7. Menu Laporan -->
            <li class="dropdown <?php echo (strpos($page, 'lap_') !== false) ? 'active' : ''; ?>">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Laporan <span class="caret"></span></a>
              <ul class="dropdown-menu" role="menu">
                <li><a href="?page=lap_1">1. Laporan Pemasukan Barang</a></li>
                <li><a href="?page=lap_2">2. Laporan Pengeluaran Barang</a></li>
                <li><a href="?page=lap_3">3. Laporan Posisi Barang per Dokumen</a></li>
                
                <li class="dropdown-header" style="font-size: 14px; color: #333;">4. Laporan Mutasi Barang</li>
                <li><a href="?page=lap_4_bb">&nbsp;&nbsp;&nbsp;a. Mutasi Bahan Baku</a></li>
                <li><a href="?page=lap_4_bj">&nbsp;&nbsp;&nbsp;b. Mutasi Barang Jadi</a></li>
                <li><a href="?page=lap_4_mesin">&nbsp;&nbsp;&nbsp;c. Mutasi Mesin dan Peralatan</a></li>
                <li><a href="?page=lap_4_scrap">&nbsp;&nbsp;&nbsp;d. Mutasi Scrap</a></li>
                
                <li><a href="?page=lap_5">5. Laporan Barang dalam Proses</a></li>
                <li><a href="?page=lap_6">6. Laporan Barang Sisa/Scrap</a></li>
                <li><a href="?page=lap_7">7. Laporan Mesin dan Peralatan</a></li>
              </ul>
            </li>

          </ul>
        </div>
        <!-- /.navbar-collapse -->

        <!-- Menu Kanan (User) -->
        <div class="navbar-custom-menu">
          <ul class="nav navbar-nav">
            <li><a href="#"><i class="fa fa-user"></i> plan1</a></li>
            <li><a href="logout.php"><i class="fa fa-sign-out"></i> Keluar</a></li>
          </ul>
        </div>
      </div>
    </nav>
  </header>

  <!-- KONTEN UTAMA -->
  <div class="content-wrapper">
    <div class="container-fluid">
      <section class="content-header">
        <h1>
            <?php 
                if ($page == 'dashboard') {
                    echo "Dashboard <small>Ringkasan IT Inventory Kawasan Berikat</small>";
                } else {
                    // Mengubah text judul halaman sesuai URL (contoh: master_barang -> Master Barang)
                    echo ucwords(str_replace('_', ' ', $page)); 
                }
            ?>
        </h1>
        
        <!-- Pesan Status Koneksi Database -->
        <?php if($dbConnected && $page == 'dashboard'): ?>
            <div class="alert alert-success alert-dismissible" style="margin-top:10px;">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <i class="icon fa fa-check"></i> Database Terhubung!
            </div>
        <?php elseif($dbError != ''): ?>
            <div class="alert alert-danger alert-dismissible" style="margin-top:10px;">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <i class="icon fa fa-ban"></i> Gagal Terhubung: <?php echo $dbError; ?>
            </div>
        <?php endif; ?>
      </section>

      <section class="content">
          
        <?php 
        // =========================================================================
        // LOGIKA ROUTING: MENAMPILKAN HALAMAN SESUAI MENU YANG DIKLIK
        // =========================================================================
        
        if ($page == 'dashboard' || $page == '') { 
            // -------------------------------------------------------------
            // TAMPILAN JIKA BERADA DI DASHBOARD AWAL
            // -------------------------------------------------------------
            
            $totalPemasukan = 0;
            $totalPengeluaran = 0;

            // LOGIKA MENGHITUNG DOKUMEN BULAN BERJALAN
            if ($dbConnected) {
                $tglAwalBulan = date('Y-m-01');
                $tglAkhirBulan = date('Y-m-t');

                // 1. Menghitung Dokumen Pemasukan (Berdasarkan SP Laporan)
                $stmtIn = sqlsrv_query($conn, "{CALL dbo.sp_pemasukan_dok_receive(?, ?, ?)}", array($tglAwalBulan, $tglAkhirBulan, "%"));
                if ($stmtIn !== false) {
                    $uniqueDocsIn = array();
                    while ($row = sqlsrv_fetch_array($stmtIn, SQLSRV_FETCH_ASSOC)) {
                        $no_bc = isset($row["NOMOR_BC"]) ? trim($row["NOMOR_BC"]) : "";
                        if ($no_bc !== "") {
                            $uniqueDocsIn[$no_bc] = true; // Menyaring agar 1 nomor BC hanya dihitung 1 kali
                        }
                    }
                    $totalPemasukan = count($uniqueDocsIn);
                    sqlsrv_free_stmt($stmtIn);
                }

                // 2. Menghitung Dokumen Pengeluaran (Berdasarkan SP Laporan)
                $startParamOut = $tglAwalBulan . " 00:00:00";
                $endParamOut   = $tglAkhirBulan . " 23:59:59";
                $stmtOut = sqlsrv_query($conn, "{CALL dbo.sp_pengeluaran_dok(?, ?, ?)}", array($startParamOut, $endParamOut, "%"));
                if ($stmtOut !== false) {
                    $uniqueDocsOut = array();
                    while ($row = sqlsrv_fetch_array($stmtOut, SQLSRV_FETCH_ASSOC)) {
                        $no_bc = isset($row["NOMOR_BC"]) ? trim($row["NOMOR_BC"]) : "";
                        if ($no_bc !== "") {
                            $uniqueDocsOut[$no_bc] = true; // Menyaring agar 1 nomor BC hanya dihitung 1 kali
                        }
                    }
                    $totalPengeluaran = count($uniqueDocsOut);
                    sqlsrv_free_stmt($stmtOut);
                }
            }
        ?>
            <!-- 4 KOLOM PANEL MENYAMPING -->
            <div class="row">
              <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-aqua">
                  <div class="inner">
                    <h3><?php echo number_format($totalPemasukan, 0, ',', '.'); ?></h3>
                    <p>DOKUMEN PEMASUKAN</p>
                  </div>
                  <a href="?page=lap_1" class="small-box-footer">Lihat laporan <i class="fa fa-arrow-circle-right"></i></a>
                </div>
              </div>
              <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-green">
                  <div class="inner">
                    <h3><?php echo number_format($totalPengeluaran, 0, ',', '.'); ?></h3>
                    <p>DOKUMEN PENGELUARAN</p>
                  </div>
                  <a href="?page=lap_2" class="small-box-footer">Lihat laporan <i class="fa fa-arrow-circle-right"></i></a>
                </div>
              </div>
              <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-yellow">
                  <div class="inner">
                    <h3>0</h3>
                    <p>STOK BAHAN BAKU</p>
                  </div>
                  <a href="?page=lap_4_bb" class="small-box-footer">Lihat mutasi <i class="fa fa-arrow-circle-right"></i></a>
                </div>
              </div>
              <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-red">
                  <div class="inner">
                    <h3>0</h3>
                    <p>DOKUMEN MENUNGGU</p>
                  </div>
                  <a href="?page=proses_bc" class="small-box-footer">Periksa dokumen <i class="fa fa-arrow-circle-right"></i></a>
                </div>
              </div>
            </div>
            
            <!-- PESAN INFO DASHBOARD -->
            <div class="box box-primary">
                <div class="box-body" style="min-height: 300px; display:flex; align-items:center; justify-content:center;">
                    <h4 class="text-center text-muted"><i class="fa fa-info-circle"></i> Silakan pilih menu navigasi di atas untuk memulai.</h4>
                </div>
            </div>
            
        <?php 
        } 
        // -------------------------------------------------------------
        // PEMANGGILAN HALAMAN MASTER BARANG
        // -------------------------------------------------------------
        elseif ($page == 'master_barang') { 
            if (file_exists('master_barang.php')) {
                include 'master_barang.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>master_barang.php</b> tidak ditemukan pada server.</div>";
            }
        } 
        
        elseif ($page == 'master_bom') { 
            if (file_exists('bom.php')) {
                include 'bom.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>bom.php</b> tidak ditemukan pada server.</div>";
            }
        } 
        
        elseif ($page == 'master_price') { 
            if (file_exists('master.php')) {
                include 'master.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>master.php</b> tidak ditemukan pada server.</div>";
            }
        } 
        
        elseif ($page == 'plan_forecast') { 
            if (file_exists('forecast.php')) {
                include 'forecast.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>forecast.php</b> tidak ditemukan pada server.</div>";
            }
        } 
        
        elseif ($page == 'plan_gen') { 
            if (file_exists('generate.php')) {
                include 'generate.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>generate.php</b> tidak ditemukan pada server.</div>";
            }
        } 
        
        elseif ($page == 'plan_schedule') { 
            if (file_exists('manual_order.php')) {
                include 'manual_order.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>manual_order.php</b> tidak ditemukan pada server.</div>";
            }
        } 
        
        elseif ($page == 'order_pr') { 
            if (file_exists('pr.php')) {
                include 'pr.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>pr.php</b> tidak ditemukan pada server.</div>";
            }
        } 
        
        elseif ($page == 'order_rec') { 
            if (file_exists('receive.php')) {
                include 'receive.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>receive.php</b> tidak ditemukan pada server.</div>";
            }
        } 
        
        elseif ($page == 'sop_trans') { 
            if (file_exists('trans.php')) {
                include 'trans.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>trans.php</b> tidak ditemukan pada server.</div>";
            }
        } 
        
        elseif ($page == 'wo') { 
            if (file_exists('work_order.php')) {
                include 'work_order.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>work_order.php</b> tidak ditemukan pada server.</div>";
            }
        } 
        
        elseif ($page == 'sms') { 
            if (file_exists('sms.php')) {
                include 'sms.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>sms.php</b> tidak ditemukan pada server.</div>";
            }
        } 
        
        elseif ($page == 'prod') { 
            if (file_exists('production.php')) {
                include 'production.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>production.php</b> tidak ditemukan pada server.</div>";
            }
        } 
         
        // LAPORAN BEA CUKAI
        
        elseif ($page == 'lap_1') { 
            if (file_exists('pemasukan.php')) {
                include 'pemasukan.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>pemasukan.php</b> tidak ditemukan pada server.</div>";
            }
        } 
        
        elseif ($page == 'lap_2') { 
            if (file_exists('pengeluaran.php')) {
                include 'pengeluaran.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>pengeluaran.php</b> tidak ditemukan pada server.</div>";
            }
        } 
        
        elseif ($page == 'lap_3') { 
            if (file_exists('posisi_dokumen.php')) {
                include 'posisi_dokumen.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>posisi_dokumen.php</b> tidak ditemukan pada server.</div>";
            }
        } 

        // -------------------------------------------------------------
        // PEMANGGILAN HALAMAN MUTASI BARANG
        // -------------------------------------------------------------
        elseif ($page == 'lap_4_bb') { 
            if (file_exists('mutasi_bahan_baku.php')) {
                include 'mutasi_bahan_baku.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>mutasi_bahan_baku.php</b> tidak ditemukan pada server.</div>";
            }
        } 
        elseif ($page == 'lap_4_bj') { 
            if (file_exists('mutasi_barang_jadi.php')) {
                include 'mutasi_barang_jadi.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>mutasi_barang_jadi.php</b> tidak ditemukan pada server.</div>";
            }
        } 
        elseif ($page == 'lap_4_mesin') { 
            if (file_exists('mutasi_mesin.php')) {
                include 'mutasi_mesin.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>mutasi_mesin.php</b> tidak ditemukan pada server.</div>";
            }
        } 
		
        elseif ($page == 'lap_4_scrap') { 
            if (file_exists('mutasi_scrap.php')) {
                include 'mutasi_scrap.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>mutasi_scrap.php</b> tidak ditemukan pada server.</div>";
            }
        }
		
		 elseif ($page == 'lap_5') { 
            if (file_exists('mutasi_wip.php')) {
                include 'mutasi_wip.php';
            } else {
                echo "<div class='alert alert-danger'><h4><i class='icon fa fa-ban'></i> Error 404</h4>File <b>mutasi_scrap.php</b> tidak ditemukan pada server.</div>";
            }
        }
		
		
         
        // -------------------------------------------------------------
        // FALLBACK UNTUK HALAMAN LAIN YANG BELUM DIBUAT
        // -------------------------------------------------------------
        else {
            echo "
            <div class='box box-warning'>
                <div class='box-header with-border'>
                    <h3 class='box-title'><i class='fa fa-code'></i> Dalam Pengembangan</h3>
                </div>
                <div class='box-body'>
                    <p>Halaman untuk modul <b>" . htmlspecialchars($page) . "</b> saat ini belum tersedia atau sedang dalam tahap pengembangan.</p>
                    <a href='index.php' class='btn btn-primary'><i class='fa fa-arrow-left'></i> Kembali ke Dashboard</a>
                </div>
            </div>";
        }
        ?>

      </section>
    </div>
  </div>
</div>

<!-- Link JavaScript menggunakan CDN -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/2.4.18/js/adminlte.min.js"></script>

</body>
</html>
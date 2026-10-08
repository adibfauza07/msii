<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$dbConnected = false;
$dbError = '';
$conn = false;

// Memanggil file konfigurasi database dan helper log
require_once __DIR__ . '/config/database.php';
if (file_exists(__DIR__ . '/config/log_helper.php')) {
    require_once __DIR__ . '/config/log_helper.php';
}

if (isset($conn) && $conn !== false) {
    $dbConnected = true;
} else {
    $dbError = 'Koneksi SQL Server tidak tersedia.';
}

// Perlindungan login session
if (!isset($_SESSION['db_user']) || trim($_SESSION['db_user']) == "") {
    header("Location: /msii/bea/login.php");
    exit();
}

// ---------------------------------------------------------
// PENGATURAN HAK AKSES USER
// ---------------------------------------------------------
$currentUser = strtolower(trim($_SESSION['db_user']));
$allowedMenusArray = array('master', 'ordering', 'purchasing', 'inventory', 'proses', 'accounting', 'laporan');

if ($dbConnected) {
    $checkTable = sqlsrv_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'APP_USERS'");
    if ($checkTable && sqlsrv_has_rows($checkTable)) {
        $stmtUsr = sqlsrv_query($conn, "SELECT ALLOWED_MENUS FROM dbo.APP_USERS WHERE LOWER(USERNAME) = ?", array($currentUser));
        if ($stmtUsr && $rowUsr = sqlsrv_fetch_array($stmtUsr, SQLSRV_FETCH_ASSOC)) {
            if (!empty($rowUsr['ALLOWED_MENUS'])) {
                $allowedMenusArray = explode(',', $rowUsr['ALLOWED_MENUS']);
            }
        }
    }
}

function hasAccess($moduleKey, $arrAllowed) {
    return in_array($moduleKey, $arrAllowed);
}

// Tangkap permintaan halaman
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
    
    <!-- JQUERY UI CSS -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">

    <!-- jQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
</head>
<body class="hold-transition skin-blue layout-top-nav">
<div class="wrapper">

  <header class="main-header">
    <nav class="navbar navbar-static-top">
      <div class="container-fluid">
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
            <?php if (hasAccess('master', $allowedMenusArray)) { ?>
            <li class="dropdown <?php echo (strpos($page, 'master_') !== false) ? 'active' : ''; ?>">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Master <span class="caret"></span></a>
              <ul class="dropdown-menu" role="menu">
                <li><a href="?page=master_barang">Master Barang</a></li>
                <li><a href="?page=master_bom">BOM (Bill of Materials)</a></li>
                <li><a href="?page=master_price">Price, Customer, Currency, Supplier</a></li>
                <li><a href="?page=master_unit">Unit</a></li>
              </ul>
            </li>
            <?php } ?>

            <!-- 1. Menu Planning dan Ordering -->
            <?php if (hasAccess('ordering', $allowedMenusArray)) { ?>
            <li class="dropdown <?php echo (strpos($page, 'plan_') !== false) ? 'active' : ''; ?>">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Ordering <span class="caret"></span></a>
              <ul class="dropdown-menu" role="menu">
                <li><a href="?page=plan_forecast">Forecast, Order, Schedule</a></li>
                <li><a href="?page=plan_gen">Generate DI</a></li>
                <li><a href="?page=plan_schedule">Delivery Instruction</a></li>
              </ul>
            </li>
            <?php } ?>
            
            <!-- 2. Menu Purchasing -->
            <?php if (hasAccess('purchasing', $allowedMenusArray)) { ?>
            <li class="dropdown <?php echo (strpos($page, 'order_') !== false) ? 'active' : ''; ?>">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Purchasing <span class="caret"></span></a>
              <ul class="dropdown-menu" role="menu">
                <li><a href="?page=order_pr">PR, Quotation, Purchase Order</a></li>
                <li><a href="?page=order_rec">Receive Supplier</a></li>
              </ul>
            </li>
            <?php } ?>
            
            <!-- 3. Menu Inventory Transaction -->
            <?php if (hasAccess('inventory', $allowedMenusArray)) { ?>
            <li class="dropdown <?php echo ($page == 'sop_trans') ? 'active' : ''; ?>">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Inventory Transaction <span class="caret"></span></a>
              <ul class="dropdown-menu" role="menu">
                <li><a href="?page=sop_trans">SOP & TRANS</a></li>
              </ul>
            </li>
            <?php } ?>
            
            <!-- 4. Menu Proses -->
            <?php if (hasAccess('proses', $allowedMenusArray)) { ?>
            <li class="dropdown <?php echo (in_array($page, array('wo','sms','prod'))) ? 'active' : ''; ?>">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown"> PROSES <span class="caret"></span></a>
              <ul class="dropdown-menu" role="menu">
                <li><a href="?page=wo">WORK ORDER</a></li>
                <li><a href="?page=sms">SUPPLY MATERIAL</a></li>
                <li><a href="?page=prod">PRODUCTION</a></li>
              </ul>
            </li>
            <?php } ?>
            
            <!-- 5. Menu Accounting -->
            <?php if (hasAccess('accounting', $allowedMenusArray)) { ?>
            <li class="dropdown <?php echo (strpos($page, 'acc_') !== false) ? 'active' : ''; ?>">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Accounting <span class="caret"></span></a>
              <ul class="dropdown-menu" role="menu">
                <li><a href="?page=acc_jurnal">Jurnal</a></li>
                <li><a href="?page=acc_laporan">Laporan Keuangan</a></li>
              </ul>
            </li>
            <?php } ?>
            
            <!-- 6. Menu User Log & Management (KHUSUS ADMIN) -->
            <?php if ($currentUser === 'admin') { ?>
            <li class="dropdown <?php echo (in_array($page, array('user_log', 'user_management'))) ? 'active' : ''; ?>">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Daftar User <span class="caret"></span></a>
              <ul class="dropdown-menu" role="menu">
                <li><a href="?page=user_log"><i class="fa fa-history"></i> User Log (Riwayat)</a></li>
                <li><a href="?page=user_management"><i class="fa fa-users"></i> User Management & Hak Akses</a></li>
              </ul>
            </li>
            <?php } ?>

            <!-- 7. Menu Laporan Bea Cukai -->
            <?php if (hasAccess('laporan', $allowedMenusArray)) { ?>
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
                
                <li><a href="?page=lap_5">5. Laporan Barang dalam Proses (WIP)</a></li>
              </ul>
            </li>
            <?php } ?>

          </ul>
        </div>

        <!-- Menu Kanan (User Login) -->
        <div class="navbar-custom-menu">
          <ul class="nav navbar-nav">
            <li><a href="#"><i class="fa fa-user"></i> <?php echo htmlspecialchars($_SESSION["db_user"]); ?></a></li>
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
                if ($page == 'dashboard' || $page == '') {
                    echo "Dashboard <small>Ringkasan IT Inventory Kawasan Berikat</small>";
                } else {
                    echo ucwords(str_replace('_', ' ', $page)); 
                }
            ?>
        </h1>
        
        <?php if($dbConnected && ($page == 'dashboard' || $page == '')): ?>
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
        $accessGranted = true;

        // Pengecekan Hak Akses Menu Modul
        if (strpos($page, 'master_') !== false && !hasAccess('master', $allowedMenusArray)) { $accessGranted = false; }
        elseif (strpos($page, 'plan_') !== false && !hasAccess('ordering', $allowedMenusArray)) { $accessGranted = false; }
        elseif (strpos($page, 'order_') !== false && !hasAccess('purchasing', $allowedMenusArray)) { $accessGranted = false; }
        elseif ($page == 'sop_trans' && !hasAccess('inventory', $allowedMenusArray)) { $accessGranted = false; }
        elseif (in_array($page, array('wo','sms','prod')) && !hasAccess('proses', $allowedMenusArray)) { $accessGranted = false; }
        elseif (strpos($page, 'acc_') !== false && !hasAccess('accounting', $allowedMenusArray)) { $accessGranted = false; }
        elseif (strpos($page, 'lap_') !== false && !hasAccess('laporan', $allowedMenusArray)) { $accessGranted = false; }
        // Proteksi Ketat Halaman User (Hanya Boleh 'admin')
        elseif (in_array($page, array('user_log', 'user_management')) && $currentUser !== 'admin') { $accessGranted = false; }

        if (!$accessGranted) {
            echo '<div class="alert alert-danger"><h4><i class="icon fa fa-ban"></i> Akses Ditolak!</h4>Anda tidak memiliki izin membuka halaman ini. Hubungi Administrator.</div>';
        } else {
            // -------------------------------------------------------------
            // TAMPILAN DASHBOARD
            // -------------------------------------------------------------
            if ($page == 'dashboard' || $page == '') { 
                $totalPemasukan = 0;
                $totalPengeluaran = 0;

                if ($dbConnected) {
                    $tglAwalBulan = date('Y-m-01');
                    $tglAkhirBulan = date('Y-m-t');

                    $stmtIn = sqlsrv_query($conn, "{CALL dbo.sp_pemasukan_dok_receive(?, ?, ?)}", array($tglAwalBulan, $tglAkhirBulan, "%"));
                    if ($stmtIn !== false) {
                        $uniqueDocsIn = array();
                        while ($row = sqlsrv_fetch_array($stmtIn, SQLSRV_FETCH_ASSOC)) {
                            $no_bc = isset($row["NOMOR_BC"]) ? trim($row["NOMOR_BC"]) : "";
                            if ($no_bc !== "") { $uniqueDocsIn[$no_bc] = true; }
                        }
                        $totalPemasukan = count($uniqueDocsIn);
                        sqlsrv_free_stmt($stmtIn);
                    }

                    $startParamOut = $tglAwalBulan . " 00:00:00";
                    $endParamOut   = $tglAkhirBulan . " 23:59:59";
                    $stmtOut = sqlsrv_query($conn, "{CALL dbo.sp_pengeluaran_dok(?, ?, ?)}", array($startParamOut, $endParamOut, "%"));
                    if ($stmtOut !== false) {
                        $uniqueDocsOut = array();
                        while ($row = sqlsrv_fetch_array($stmtOut, SQLSRV_FETCH_ASSOC)) {
                            $no_bc = isset($row["NOMOR_BC"]) ? trim($row["NOMOR_BC"]) : "";
                            if ($no_bc !== "") { $uniqueDocsOut[$no_bc] = true; }
                        }
                        $totalPengeluaran = count($uniqueDocsOut);
                        sqlsrv_free_stmt($stmtOut);
                    }
                }
            ?>
                <!-- 4 KOLOM PANEL MENYAMPING DI DASHBOARD -->
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
                
                <div class="box box-primary">
                    <div class="box-body" style="min-height: 250px; display:flex; align-items:center; justify-content:center;">
                        <h4 class="text-center text-muted"><i class="fa fa-info-circle"></i> Silakan pilih menu navigasi di atas untuk memulai.</h4>
                    </div>
                </div>
            <?php 
            } 
            // -------------------------------------------------------------
            // PEMANGGILAN HALAMAN MODUL
            // -------------------------------------------------------------
            elseif ($page == 'user_log') { 
                if (file_exists('user_log.php')) include 'user_log.php'; 
                else echo "<div class='alert alert-danger'>File user_log.php belum dibuat.</div>";
            }
            elseif ($page == 'user_management') { 
                if (file_exists('user_management.php')) include 'user_management.php'; 
                else echo "<div class='alert alert-danger'>File user_management.php belum dibuat.</div>";
            }
            elseif ($page == 'master_barang') { if (file_exists('master_barang.php')) include 'master_barang.php'; }
            elseif ($page == 'master_bom') { if (file_exists('bom.php')) include 'bom.php'; }
            elseif ($page == 'master_price') { if (file_exists('master.php')) include 'master.php'; }
            elseif ($page == 'master_unit') { if (file_exists('unit.php')) include 'unit.php'; }
            elseif ($page == 'plan_forecast') { if (file_exists('forecast.php')) include 'forecast.php'; }
            elseif ($page == 'plan_gen') { if (file_exists('generate.php')) include 'generate.php'; }
            elseif ($page == 'plan_schedule') { if (file_exists('manual_order.php')) include 'manual_order.php'; }
            elseif ($page == 'order_pr') { if (file_exists('pr.php')) include 'pr.php'; }
            elseif ($page == 'order_rec') { if (file_exists('receive.php')) include 'receive.php'; }
            elseif ($page == 'sop_trans') { if (file_exists('trans.php')) include 'trans.php'; }
            elseif ($page == 'wo') { if (file_exists('work_order.php')) include 'work_order.php'; }
            elseif ($page == 'sms') { if (file_exists('sms.php')) include 'sms.php'; }
            elseif ($page == 'prod') { if (file_exists('production.php')) include 'production.php'; }
            elseif ($page == 'lap_1') { if (file_exists('pemasukan.php')) include 'pemasukan.php'; }
            elseif ($page == 'lap_2') { if (file_exists('pengeluaran.php')) include 'pengeluaran.php'; }
            elseif ($page == 'lap_3') { if (file_exists('posisi_dokumen.php')) include 'posisi_dokumen.php'; }
            elseif ($page == 'lap_4_bb') { if (file_exists('mutasi_bahan_baku.php')) include 'mutasi_bahan_baku.php'; }
            elseif ($page == 'lap_4_bj') { if (file_exists('mutasi_barang_jadi.php')) include 'mutasi_barang_jadi.php'; }
            elseif ($page == 'lap_4_mesin') { if (file_exists('mutasi_mesin.php')) include 'mutasi_mesin.php'; }
            elseif ($page == 'lap_4_scrap') { if (file_exists('mutasi_scrap.php')) include 'mutasi_scrap.php'; }
            elseif ($page == 'lap_5') { if (file_exists('mutasi_wip.php')) include 'mutasi_wip.php'; }
            else {
                echo "<div class='box box-warning'><div class='box-body'><p>Modul <b>" . htmlspecialchars($page) . "</b> saat ini sedang dalam pengembangan.</p></div></div>";
            }
        }
        ?>
      </section>
    </div>
  </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/2.4.18/js/adminlte.min.js"></script>
</body>
</html>
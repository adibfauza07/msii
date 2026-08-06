<?php
/**
 * Dashboard IT Inventory Bea Cukai - Plant 1
 * PHP 5.4 + Bootstrap 3 + SQLSRV
 *
 * Lokasi file:
 *   /msii/it-inventory-bc/index.php
 */

/* =========================================================
 * SESSION DAN LOGIN PROTECTION
 * ========================================================= */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$loginUrl = "/msii/bea/login.php";

/*
 * Periksa session lebih dahulu agar pengguna yang belum login
 * langsung diarahkan ke login IT Inventory.
 */
if (
    !isset($_SESSION['db_user']) ||
    trim($_SESSION['db_user']) == "" ||
    !isset($_SESSION['active_module']) ||
    $_SESSION['active_module'] != "it_inventory"
) {
    header("Location: " . $loginUrl);
    exit();
}

/*
 * config/database.php akan mencari:
 *   /msii/config/db_plant1.php
 * atau:
 *   /msii/config/db_plan1.php
 *
 * Variabel $loginUrl di atas diteruskan ke db_plant1.php,
 * sehingga kegagalan koneksi kembali ke login IT Inventory.
 */
require_once __DIR__ . "/config/database.php";

if (!isset($dbConnected) || !$dbConnected || !isset($conn) || $conn === false) {
    header("Location: " . $loginUrl . "?error=session_expired");
    exit();
}

require_once __DIR__ . "/inc/functions.php";
require_once __DIR__ . "/inc/data.php";
require_once __DIR__ . "/inc/repository.php";

/* =========================================================
 * IDENTITAS HALAMAN
 * ========================================================= */
$pageTitle  = "Dashboard - IT Inventory Kawasan Berikat";
$currentPage = "dashboard";
$currentUser = $_SESSION['db_user'];

/* =========================================================
 * DEFAULT DATA
 * Tidak memakai angka demo agar dashboard produksi tidak
 * menampilkan angka yang bukan berasal dari database.
 * ========================================================= */
$summary = array(
    "pemasukan_bulan"  => 0,
    "pengeluaran_bulan"=> 0,
    "stok_bahan_baku"  => 0,
    "stok_barang_jadi" => 0,
    "dokumen_pending"  => 0,
    "aset_it"          => 0
);

$recentDocuments = array();
$assetActive     = 0;
$assetRepair     = 0;

/* =========================================================
 * AMBIL RINGKASAN DATABASE
 * Tabel contoh:
 *   dbo.dokumen_bc
 *   dbo.aset_it
 * Sesuaikan inc/repository.php dengan tabel produksi.
 * ========================================================= */
$summary = inventory_get_summary($conn, $summary);

$dbRows = inventory_get_recent_documents($conn, 5);
if (is_array($dbRows)) {
    $recentDocuments = $dbRows;
}

if (inventory_table_exists($conn, "aset_it")) {
    $assetActive = (int) db_query_value(
        $conn,
        "SELECT COUNT(*) FROM dbo.aset_it WHERE status = ?",
        array("Aktif"),
        0
    );

    $assetRepair = (int) db_query_value(
        $conn,
        "SELECT COUNT(*) FROM dbo.aset_it
         WHERE status IN (?, ?, ?)",
        array("Perbaikan", "Rusak", "Maintenance"),
        0
    );
}

/* =========================================================
 * ARUS DOKUMEN ENAM BULAN
 * ========================================================= */
$monthNames = array(
    1  => "Jan",
    2  => "Feb",
    3  => "Mar",
    4  => "Apr",
    5  => "Mei",
    6  => "Jun",
    7  => "Jul",
    8  => "Agu",
    9  => "Sep",
    10 => "Okt",
    11 => "Nov",
    12 => "Des"
);

$monthlyFlow = array();
$monthKeys   = array();

/*
 * Siapkan enam bulan, termasuk bulan berjalan.
 */
for ($i = 5; $i >= 0; $i--) {
    $time = strtotime("-" . $i . " month");
    $year = (int) date("Y", $time);
    $month = (int) date("n", $time);
    $key = $year . "-" . str_pad($month, 2, "0", STR_PAD_LEFT);

    $monthKeys[$key] = count($monthlyFlow);

    $monthlyFlow[] = array(
        "bulan"  => $monthNames[$month],
        "tahun"  => $year,
        "masuk"  => 0,
        "keluar" => 0
    );
}

if (inventory_table_exists($conn, "dokumen_bc")) {
    $flowSql = "
        SELECT
            YEAR(tanggal) AS tahun,
            MONTH(tanggal) AS bulan,
            SUM(CASE WHEN arah = 'Pemasukan' THEN 1 ELSE 0 END) AS masuk,
            SUM(CASE WHEN arah = 'Pengeluaran' THEN 1 ELSE 0 END) AS keluar
        FROM dbo.dokumen_bc
        WHERE tanggal >= DATEADD(
            month,
            DATEDIFF(month, 0, GETDATE()) - 5,
            0
        )
        GROUP BY YEAR(tanggal), MONTH(tanggal)
        ORDER BY YEAR(tanggal), MONTH(tanggal)
    ";

    $flowRows = db_query_rows($conn, $flowSql, array());

    foreach ($flowRows as $flowRow) {
        $rowYear  = isset($flowRow["tahun"]) ? (int) $flowRow["tahun"] : 0;
        $rowMonth = isset($flowRow["bulan"]) ? (int) $flowRow["bulan"] : 0;
        $rowKey   = $rowYear . "-" . str_pad($rowMonth, 2, "0", STR_PAD_LEFT);

        if (isset($monthKeys[$rowKey])) {
            $rowIndex = $monthKeys[$rowKey];

            $monthlyFlow[$rowIndex]["masuk"] = isset($flowRow["masuk"])
                ? (int) $flowRow["masuk"]
                : 0;

            $monthlyFlow[$rowIndex]["keluar"] = isset($flowRow["keluar"])
                ? (int) $flowRow["keluar"]
                : 0;
        }
    }
}

/*
 * Cari nilai terbesar untuk menentukan lebar diagram.
 */
$maxFlow = 1;
foreach ($monthlyFlow as $flowItem) {
    if ($flowItem["masuk"] > $maxFlow) {
        $maxFlow = $flowItem["masuk"];
    }

    if ($flowItem["keluar"] > $maxFlow) {
        $maxFlow = $flowItem["keluar"];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title><?php echo e($pageTitle); ?></title>

    <link
        rel="stylesheet"
        href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

    <style>
        .user-menu-name {
            max-width: 210px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .dashboard-user-box {
            float: right;
            margin-top: -35px;
            font-size: 12px;
            color: #617789;
        }

        .empty-table {
            padding: 28px !important;
            color: #8999a6;
            text-align: center;
        }

        @media (max-width: 767px) {
            .dashboard-user-box {
                float: none;
                margin-top: 8px;
            }
        }
    </style>
</head>

<body>

<!-- =======================================================
     TOP NAVIGATION
     ======================================================= -->
<nav class="navbar navbar-fixed-top topbar">
    <div class="container-fluid">

        <div class="navbar-header">

            <button
                type="button"
                class="navbar-toggle collapsed"
                data-toggle="collapse"
                data-target="#main-menu"
            >
                <span class="sr-only">Buka navigasi</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>

            <a class="navbar-brand" href="index.php">
                <span class="brand-mark">
                    <i class="fa fa-bar-chart"></i>
                </span>

                IT Inventory P.T. IMC TEKNO INDONESIA
            </a>
        </div>

        <ul class="nav navbar-nav navbar-right hidden-xs">

            <li>
                <a href="#" class="user-menu-name">
                    <i class="fa fa-user-circle"></i>

                    <?php echo e($currentUser); ?>
                </a>
            </li>

            <li>
                <a href="logout.php">
                    <i class="fa fa-sign-out"></i>
                    Keluar
                </a>
            </li>
        </ul>
    </div>
</nav>

<div class="layout">

    <!-- ===================================================
         SIDEBAR
         =================================================== -->
    <aside class="sidebar">

        <div class="sidebar-profile">

            <div class="profile-icon">
                <i class="fa fa-cubes"></i>
            </div>

            <div>
                <strong>IT Inventory</strong>
                <small>Kawasan Berikat</small>
            </div>
        </div>

        <div class="collapse navbar-collapse" id="main-menu">

            <ul class="nav sidebar-nav">

                <li class="active">
                    <a href="index.php">
                        <i class="fa fa-dashboard"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li>
                    <a href="laporan.php">
                        <i class="fa fa-briefcase"></i>
                        <span>Laporan</span>
                        <i class="fa fa-angle-right menu-arrow"></i>
                    </a>
                </li>

                <li>
                    <a href="#">
                        <i class="fa fa-exchange"></i>
                        <span>Transaksi Barang</span>
                        <i class="fa fa-angle-right menu-arrow"></i>
                    </a>
                </li>

                <li>
                    <a href="#">
                        <i class="fa fa-database"></i>
                        <span>Master Data</span>
                        <i class="fa fa-angle-right menu-arrow"></i>
                    </a>
                </li>

                <li>
                    <a href="#">
                        <i class="fa fa-wrench"></i>
                        <span>Utility</span>
                        <i class="fa fa-angle-right menu-arrow"></i>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-footer">
            BC Inventory v1.0
        </div>
    </aside>

    <!-- ===================================================
         MAIN CONTENT
         =================================================== -->
    <main class="main-content">

        <div class="page-header-box">

            <h1>Dashboard</h1>

            <ol class="breadcrumb breadcrumb-clean">
                <li>
                    <i class="fa fa-home"></i>
                </li>

                <li class="active">
                    Ringkasan IT Inventory Kawasan Berikat
                </li>
            </ol>

            <div class="dashboard-user-box">
                Login:
                <strong><?php echo e($currentUser); ?></strong>

                &nbsp;|&nbsp;

                <a href="logout.php">
                    Logout
                </a>
            </div>
        </div>

        <!-- =================================================
             SUMMARY CARDS
             ================================================= -->
        <div class="row">

            <div class="col-sm-6 col-lg-3">
                <div class="summary-card">

                    <div class="value">
                        <?php
                        echo format_number_id(
                            $summary["pemasukan_bulan"]
                        );
                        ?>
                    </div>

                    <div class="label-text">
                        Dokumen pemasukan bulan ini
                    </div>

                    <a
                        class="mini-link"
                        href="laporan.php?report=1"
                    >
                        Lihat laporan
                        <i class="fa fa-angle-right"></i>
                    </a>

                    <i class="fa fa-sign-in icon"></i>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="summary-card">

                    <div class="value">
                        <?php
                        echo format_number_id(
                            $summary["pengeluaran_bulan"]
                        );
                        ?>
                    </div>

                    <div class="label-text">
                        Dokumen pengeluaran bulan ini
                    </div>

                    <a
                        class="mini-link"
                        href="laporan.php?report=2"
                    >
                        Lihat laporan
                        <i class="fa fa-angle-right"></i>
                    </a>

                    <i class="fa fa-sign-out icon"></i>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="summary-card">

                    <div class="value">
                        <?php
                        echo format_number_id(
                            $summary["stok_bahan_baku"]
                        );
                        ?>
                    </div>

                    <div class="label-text">
                        Stok bahan baku dan penolong
                    </div>

                    <a
                        class="mini-link"
                        href="laporan.php?report=3"
                    >
                        Lihat mutasi
                        <i class="fa fa-angle-right"></i>
                    </a>

                    <i class="fa fa-cubes icon"></i>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="summary-card">

                    <div class="value">
                        <?php
                        echo format_number_id(
                            $summary["dokumen_pending"]
                        );
                        ?>
                    </div>

                    <div class="label-text">
                        Dokumen menunggu proses
                    </div>

                    <a
                        class="mini-link"
                        href="#recent-documents"
                    >
                        Periksa dokumen
                        <i class="fa fa-angle-right"></i>
                    </a>

                    <i class="fa fa-clock-o icon"></i>
                </div>
            </div>
        </div>

        <!-- =================================================
             DOKUMEN TERBARU DAN ARUS DOKUMEN
             ================================================= -->
        <div class="row">

            <div class="col-md-8">

                <div
                    class="panel panel-clean"
                    id="recent-documents"
                >
                    <div class="panel-heading">
                        <i class="fa fa-file-text-o"></i>
                        Dokumen Terbaru
                    </div>

                    <div class="table-responsive">

                        <table class="table table-hover table-condensed">

                            <thead>
                                <tr>
                                    <th>No. Dokumen</th>
                                    <th>Tanggal</th>
                                    <th>Jenis</th>
                                    <th>Arah</th>
                                    <th>Partner/Gudang</th>
                                    <th class="text-right">Jumlah</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php if (count($recentDocuments) > 0) { ?>

                                <?php foreach ($recentDocuments as $doc) { ?>

                                    <?php
                                    $statusValue = isset($doc["status"])
                                        ? strtolower($doc["status"])
                                        : "";

                                    $allowedStatus = array(
                                        "selesai",
                                        "proses",
                                        "pending"
                                    );

                                    if (!in_array($statusValue, $allowedStatus)) {
                                        $statusValue = "proses";
                                    }
                                    ?>

                                    <tr>
                                        <td>
                                            <?php
                                            echo e(
                                                isset($doc["no_dokumen"])
                                                    ? $doc["no_dokumen"]
                                                    : ""
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            <?php
                                            echo e(
                                                isset($doc["tanggal"])
                                                    ? $doc["tanggal"]
                                                    : ""
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            <?php
                                            echo e(
                                                isset($doc["jenis"])
                                                    ? $doc["jenis"]
                                                    : ""
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            <?php
                                            echo e(
                                                isset($doc["arah"])
                                                    ? $doc["arah"]
                                                    : ""
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            <?php
                                            echo e(
                                                isset($doc["supplier"])
                                                    ? $doc["supplier"]
                                                    : ""
                                            );
                                            ?>
                                        </td>

                                        <td class="text-right">
                                            <?php
                                            echo format_number_id(
                                                isset($doc["jumlah"])
                                                    ? $doc["jumlah"]
                                                    : 0
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            <span
                                                class="status-badge status-<?php echo e($statusValue); ?>"
                                            >
                                                <?php
                                                echo e(
                                                    isset($doc["status"])
                                                        ? $doc["status"]
                                                        : ""
                                                );
                                                ?>
                                            </span>
                                        </td>
                                    </tr>

                                <?php } ?>

                            <?php } else { ?>

                                <tr>
                                    <td
                                        colspan="7"
                                        class="empty-table"
                                    >
                                        <i class="fa fa-info-circle"></i>
                                        Belum ada data dokumen pada tabel
                                        <strong>dbo.dokumen_bc</strong>.
                                    </td>
                                </tr>

                            <?php } ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-4">

                <div class="panel panel-clean">

                    <div class="panel-heading">
                        <i class="fa fa-line-chart"></i>
                        Arus Dokumen 6 Bulan
                    </div>

                    <div class="panel-body">

                        <div class="legend">

                            <span>
                                <i class="fa fa-square legend-in"></i>
                                Pemasukan
                            </span>

                            <span>
                                <i class="fa fa-square legend-out"></i>
                                Pengeluaran
                            </span>
                        </div>

                        <div class="flow-bars">

                            <?php foreach ($monthlyFlow as $flow) { ?>

                                <?php
                                $inWidth = round(
                                    ($flow["masuk"] / $maxFlow) * 100
                                );

                                $outWidth = round(
                                    ($flow["keluar"] / $maxFlow) * 100
                                );
                                ?>

                                <div class="flow-row">

                                    <div class="flow-label">

                                        <strong>
                                            <?php
                                            echo e(
                                                $flow["bulan"]
                                                . " "
                                                . $flow["tahun"]
                                            );
                                            ?>
                                        </strong>

                                        <span class="pull-right">
                                            <?php
                                            echo (int) $flow["masuk"];
                                            ?>
                                            /
                                            <?php
                                            echo (int) $flow["keluar"];
                                            ?>
                                        </span>
                                    </div>

                                    <div
                                        class="bar-track"
                                        title="Masuk <?php echo (int) $flow["masuk"]; ?>, Keluar <?php echo (int) $flow["keluar"]; ?>"
                                    >
                                        <div
                                            class="bar-in"
                                            style="width: <?php echo (int) $inWidth; ?>%;"
                                        ></div>

                                        <div
                                            class="bar-out"
                                            style="width: <?php echo (int) $outWidth; ?>%;"
                                        ></div>
                                    </div>
                                </div>

                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- =================================================
             ASET IT DAN STATUS SISTEM
             ================================================= -->
        <div class="row">

            <div class="col-sm-6">

                <div class="panel panel-clean">

                    <div class="panel-heading">
                        <i class="fa fa-desktop"></i>
                        Ringkasan Aset IT
                    </div>

                    <div class="panel-body">

                        <div class="row text-center">

                            <div class="col-xs-4">
                                <h3>
                                    <?php
                                    echo format_number_id(
                                        $summary["aset_it"]
                                    );
                                    ?>
                                </h3>
                                <small>Total aset</small>
                            </div>

                            <div class="col-xs-4">
                                <h3>
                                    <?php
                                    echo format_number_id(
                                        $assetActive
                                    );
                                    ?>
                                </h3>
                                <small>Aktif</small>
                            </div>

                            <div class="col-xs-4">
                                <h3>
                                    <?php
                                    echo format_number_id(
                                        $assetRepair
                                    );
                                    ?>
                                </h3>
                                <small>Perbaikan</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6">

                <div class="panel panel-clean">

                    <div class="panel-heading">
                        <i class="fa fa-check-square-o"></i>
                        Status Sistem
                    </div>

                    <div class="panel-body">

                        <p>
                            Koneksi SQL Server:
                            <strong class="text-success">
                                <i class="fa fa-check-circle"></i>
                                Terhubung
                            </strong>
                        </p>

                        <p>
                            Server:
                            <strong>192.168.0.4</strong>
                        </p>

                        <p>
                            Database:
                            <strong>msdata</strong>
                        </p>

                        <p>
                            Waktu akses:
                            <strong>
                                <?php echo date("d-m-Y H:i:s"); ?>
                            </strong>
                        </p>

                        <small>
                            Pastikan pemetaan tabel dan kolom pada
                            <code>inc/repository.php</code>
                            sesuai dengan database produksi.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-note">
            Bootstrap 3, PHP 5.4, SQLSRV, dan SQL Server 2008.
        </div>
    </main>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>

<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>

<script>
(function () {
    "use strict";

    if (typeof jQuery !== "undefined") {
        jQuery('[data-toggle="tooltip"]').tooltip();
    }
})();
</script>

</body>
</html>
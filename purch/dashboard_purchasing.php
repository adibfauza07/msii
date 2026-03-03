<?php
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// =======================================================
// SECURITY (WAJIB LOGIN & HANYA P1/P2)
// =======================================================
require_once "../middleware/Auth.php";
require_once "../middleware/RoleCheck.php";
only(['p2']); // hanya pengguna role p1 & p2

// =======================================================
// KONEKSI DATABASE
// =======================================================
require_once "../config/database.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard PURCHASING</title>

    <link rel="stylesheet" href="assets/bootstrap.min.css">

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
        }

        /* SIDEBAR */
        #sidebar {
            width: 240px;
            height: 100vh;
            background: #1f2a36;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            box-shadow: 2px 0 5px rgba(0,0,0,0.2);
        }

        #sidebar h4 {
            padding: 18px 20px;
            margin: 0;
            font-size: 17px;
            background: #273442;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .nav-link {
            color: #e5e5e5;
            font-size: 14px;
            padding: 12px 20px;
            display: block;
            transition: 0.15s;
            cursor: pointer;
            text-decoration: none;
        }

        .nav-link:hover {
            background: #324257;
            color: #fff;
        }

        /* SUBMENU */
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

        /* ACTIVE */
        .active {
            background: #405066 !important;
            font-weight: bold;
            color: white !important;
        }

        /* CONTENT */
        #content {
            margin-left: 240px;
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

    <h4>Dashboard PURCHASING</h4>

    <ul class="nav flex-column">

        <!-- DASHBOARD -->
        <li class="nav-item">
            <a href="?page=home" class="nav-link <?= ($page=='home')?'active':'' ?>">
                🏠 Dashboard
            </a>
        </li>

        <!-- MENU MTN -->
        <li class="nav-item">
            <a class="nav-link">DAILY MAINTENANCE REPORT</a>

            <div class="submenu">

                <!-- APLIKASI -->
                <a class="nav-link">➤ APLIKASI</a>

                <div class="submenu">
                    <a href="mac_master.php" 
                       class="nav-link <?= ($page=='mac_master')?'active':'' ?>">
                       - MASTER MACHINE
                    </a>
                </div>

                <div class="submenu">
                    <a href="history_machine.php" 
                       class="nav-link <?= ($page=='history')?'active':'' ?>">
                       - HISTORY OF MACHINE
                    </a>
                </div>

                <div class="submenu">
                    <a href="daily_maintenance.php" 
                       class="nav-link <?= ($page=='daily')?'active':'' ?>">
                       - DAILY MAINTENANCE
                    </a>
                </div>

                <div class="submenu">
                    <a href="car_maintenance.php" 
                       class="nav-link <?= ($page=='car')?'active':'' ?>">
                       - CAR MAINTENANCE
                    </a>
                </div>

                <!-- REPORT -->
                <a class="nav-link">➤ REPORT</a>

                <div class="submenu">
                    <a href="report_daily_mtn.php" 
                       class="nav-link <?= ($page=='report_daily')?'active':'' ?>">
                       - DAILY MTN
                    </a>
                </div>

                <div class="submenu">
                    <a href="report_kerusakan_periode.php" 
                       class="nav-link <?= ($page=='report_kerusakan')?'active':'' ?>">
                       - KERUSAKAN MESIN
                    </a>
                </div>

                <div class="submenu">
                    <a href="report_perbaikan_mtn.php" 
                       class="nav-link <?= ($page=='report_perbaikan')?'active':'' ?>">
                       - REPORT PERBAIKAN
                    </a>
                </div>

                <div class="submenu">
                    <a href="report_aging_yearly.php" 
                       class="nav-link <?= ($page=='report_aging')?'active':'' ?>">
                       - GRAFIK AGING TIME
                    </a>
                </div>

            </div>
        </li>

        <!-- LOGOUT -->
        <li class="nav-item">
            <a href="logout.php" class="nav-link" style="color:#ff6b6b;font-weight:bold;">
                🚪 Logout
            </a>
        </li>

    </ul>

</div>

<!-- CONTENT -->
<div id="content">
<?php
// ROUTING PAGE
if ($page == 'home') {
    echo "<h2>Dashboard MAINTENANCE</h2><div class='header-line'></div>";
    echo "Selamat datang di dashboard MAINTENANCE.";
}
?>
</div>

<script src="assets/jquery.min.js"></script>
<script src="assets/bootstrap.min.js"></script>

</body>
</html>

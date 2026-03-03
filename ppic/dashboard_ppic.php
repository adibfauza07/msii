<?php
// /msii/ppic/dashboard_ppic.php

require_once __DIR__ . "/../middleware/Auth.php";
require_once __DIR__ . "/../middleware/RoleCheck.php";

$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard PPIC</title>

    <link rel="stylesheet" href="../assets/bootstrap.min.css">

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
            overflow-y: auto;
            box-shadow: 2px 0 5px rgba(0,0,0,0.2);
        }

        #sidebar h4 {
            padding: 18px 20px;
            margin: 0;
            font-size: 17px;
            background: #273442;
            border-bottom: 1px solid rgba(255,255,255,0.1);

            display:flex;
            justify-content:space-between;
            align-items:center;
        }

        #sidebar h4 a {
            color:#ff6b6b;
            font-size:12px;
            text-decoration:none;
        }

        .nav-link {
            color: #e5e5e5;
            font-size: 14px;
            padding: 12px 20px;
            display: block;
            transition: 0.15s;
            cursor: pointer;
        }

        .nav-link:hover {
            background: #324257;
            color: #fff;
        }

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

        .active {
            background: #405066 !important;
            font-weight: bold;
            color: white !important;
        }

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

    <h4>
        Dashboard PPIC 
        <a href="../logout.php">Logout ⎋</a>
    </h4>

    <ul class="nav flex-column">

        <!-- DASHBOARD -->
        <li class="nav-item">
            <a href="?page=home" class="nav-link <?= ($page=='home')?'active':'' ?>">
                🏠 Dashboard
            </a>
        </li>

        <!-- DELIVERY INSTRUCTION -->
        <li class="nav-item">
            <a class="nav-link">🚚 Delivery Instruction</a>

            <!-- MENU UNTUK PLANT 1 -->
            <?php if (isset($_SESSION['erp_role']) && $_SESSION['erp_role'] === 'p1'): ?>
            <div class="submenu">
                <a href="?page=p1" class="nav-link <?= ($page=='p1')?'active':'' ?>">➤ Plant 1</a>
                <a href="?page=schedule_p1" class="nav-link <?= ($page=='schedule_p1')?'active':'' ?>">➤ Schedule Plant 1</a>
            </div>
            <?php endif; ?>

            <!-- MENU UNTUK PLANT 2 -->
            <?php if (isset($_SESSION['erp_role']) && $_SESSION['erp_role'] === 'p2'): ?>
            <div class="submenu">
                <a href="?page=p2" class="nav-link <?= ($page=='p2')?'active':'' ?>">➤ Plant 2</a>
                <a href="?page=schedule_p2" class="nav-link <?= ($page=='schedule_p2')?'active':'' ?>">➤ Schedule Plant 2</a>
            </div>
            <?php endif; ?>
        </li>

        <!-- SHOWA BARCODE (contoh: boleh untuk semua role p1/p2) -->
        <li class="nav-item">
            <a class="nav-link">🏷 Barcode Showa</a>

            <div class="submenu">
                <a href="grid_input.php" class="nav-link">- List Barcode</a>
                <a href="input.php" class="nav-link">- Print Barcode</a>
            </div>
        </li>
    </ul>

</div>

<!-- CONTENT -->
<div id="content">
<?php
// ROUTING
if ($page == 'home') {
    echo "<h2>Dashboard PPIC</h2><div class='header-line'></div>
          Selamat datang, <b>{$_SESSION['erp_user']}</b>!";
}

// PLANT 1 HANYA UNTUK ROLE p1
elseif ($page == 'p1') { 
    only(['p1']);
    include "instruction.php"; 
}
elseif ($page == 'schedule_p1') { 
    only(['p1']);
    include "schedule_p1.php"; 
}

// PLANT 2 HANYA UNTUK ROLE p2
elseif ($page == 'p2') { 
    only(['p2']);
    include "instruction_p2.php"; 
}
elseif ($page == 'schedule_p2') { 
    only(['p2']);
    include "schedule_p2.php"; 
}
?>
</div>

<script src="../assets/jquery.min.js"></script>
<script src="../assets/bootstrap.min.js"></script>

</body>
</html>

<?php
if (session_id() == "") {
    session_start();
}

require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    header("Location: login.php?error=session_expired");
    exit();
}

$dbUser = isset($_SESSION["db_user"]) ? $_SESSION["db_user"] : "";
$loginTime = isset($_SESSION["login_time"]) ? $_SESSION["login_time"] : "";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ordering System - Plant 2</title>

    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background: #d4d0c8;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            color: #000000;
            overflow: hidden;
        }

        .layout {
            display: flex;
            width: 100%;
            height: 100vh;
        }

        .sidebar {
            width: 210px;
            min-width: 210px;
            height: 100vh;
            background: #1d2a3d;
            color: #ffffff;
            box-sizing: border-box;
            padding: 18px 14px;
            overflow-y: auto;
        }

        .sidebar-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 24px;
            text-align: center;
            line-height: 23px;
        }

        .menu-link {
            display: block;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 10px;
            margin-bottom: 7px;
            border-radius: 4px;
            font-size: 12px;
        }

        .menu-link:hover {
            background: #2f65d9;
        }

        .menu-link.active {
            background: #2f65d9;
            font-weight: bold;
        }

        /* Style khusus untuk menu Logout */
        .menu-link.logout {
            margin-top: 20px;
            border-top: 1px solid #3a4a5e;
            padding-top: 15px;
            color: #ff6b6b;
        }

        .menu-link.logout:hover {
            background: #c0392b;
            color: #ffffff;
        }

        .main {
            flex: 1;
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: #d4d0c8;
            overflow: hidden;
        }

        .topbar {
            height: 34px;
            line-height: 34px;
            background: #000080;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
            font-size: 16px;
            letter-spacing: 1px;
            flex-shrink: 0;
        }

        .frame-area {
            flex: 1;
            overflow: hidden;
            background: #d4d0c8;
        }

        #mainFrame {
            width: 100%;
            height: 100%;
            border: none;
            background: #d4d0c8;
        }

        /* Tambahan style untuk menu material slip */
        .menu-link.material-slip {
            background: #2c3e50;
            border-left: 3px solid #3498db;
        }

        .menu-link.material-slip:hover {
            background: #34495e;
            border-left-color: #5dade2;
        }

        .menu-link.material-slip.active {
            background: #2f65d9;
            border-left-color: #ffffff;
        }

        /* Separator antara menu normal dan material slip */
        .menu-separator {
            border-top: 1px solid #3a4a5e;
            margin: 15px 0;
            opacity: 0.5;
        }
    </style>
</head>

<body>

<div class="layout">

    <div class="sidebar">
        <div class="sidebar-title">
            MENU<br>
            ORDERING SYSTEM
        </div>

        <a class="menu-link active" href="dashboard_home.php" target="mainFrame">Report</a>
		<a class="menu-link" href="generate.php" target="mainFrame">GENERATE DI,DS &INV</a>
        <a class="menu-link" href="master.php" target="mainFrame">MASTER CUST, PRICE, CURR</a>
		<a class="menu-link" href="sync_part_price_customer.php" target="mainFrame">PRICE SYNCRONISATION</a>
        <a class="menu-link" href="manual_order.php" target="mainFrame">Manual Delivery Instruction</a>
        <a class="menu-link" href="input_order.php" target="mainFrame">Input Order</a>
        <a class="menu-link" href="forecast.php" target="mainFrame">Forecast</a>
        <a class="menu-link" href="schedule.php" target="mainFrame">Schedule</a>
		<a class="menu-link" href="schedule1.php" target="mainFrame">Schedule matrix</a>
        <a class="menu-link" href="order_edit.php" target="mainFrame">Edit Order</a>
        <a class="menu-link" href="depresiasi.php" target="mainFrame">DEPRESIASI</a>
        <a class="menu-link" href="quotation.php" target="mainFrame">LIST QUOTATION</a>
        
        <!-- Separator -->
        <div class="menu-separator"></div>
        
        <!-- Menu Material Slip - Tambahan di sini -->
        <a class="menu-link material-slip" href="material_slip/index.php" target="mainFrame">
            📋 SPB TO CUSTOMER / VENDOR
        </a>
		
		 <!-- Menu Material Slip - Tambahan di sini -->
        <a class="menu-link material-slip" href="SPB/index.php" target="mainFrame">
            📋 SPB GENERAL
        </a>

		 <!-- Menu Material Slip - Tambahan di sini -->
        <a class="menu-link material-slip" href="spb_internal/index.php" target="mainFrame">
            📋 SPB TO PLANT 1
        </a>
        
        <!-- Logout di paling bawah -->
        <a class="menu-link logout" href="logout.php" target="_top">Logout</a>
    </div>

    <div class="main">
        <div class="topbar">
            ORDERING SYSTEM - PLANT 2
        </div>

        <div class="frame-area">
            <iframe id="mainFrame" name="mainFrame" src="dashboard_home.php"></iframe>
        </div>
    </div>

</div>

<script>
var menuLinks = document.getElementsByClassName("menu-link");

for (var i = 0; i < menuLinks.length; i++) {
    menuLinks[i].onclick = function () {
        for (var j = 0; j < menuLinks.length; j++) {
            menuLinks[j].className = "menu-link";
        }

        if (this.getAttribute("target") != "_top") {
            this.className = "menu-link active";
        }
    };
}
</script>

</body>
</html>
<?php
if (session_id() == "") {
    session_start();
}

require_once __DIR__ . "/../config/database_ppic.php";

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
    <title>PPIC System - Plant 2</title>

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
            width: 230px;
            min-width: 230px;
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
            margin-bottom: 18px;
            text-align: center;
            line-height: 23px;
            letter-spacing: 1px;
        }

        .user-box {
            background: #263850;
            border: 1px solid #425a78;
            border-radius: 4px;
            padding: 8px;
            margin-bottom: 16px;
            font-size: 11px;
            line-height: 17px;
        }

        .menu-section {
            font-size: 11px;
            color: #b8c7dd;
            margin: 14px 0 6px 2px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .menu-link {
            display: block;
            color: #ffffff;
            text-decoration: none;
            padding: 11px 10px;
            margin-bottom: 6px;
            border-radius: 4px;
            font-size: 12px;
            background: transparent;
        }

        .menu-link:hover {
            background: #2f65d9;
        }

        .menu-link.active {
            background: #2f65d9;
            font-weight: bold;
        }

        .menu-link.logout {
            background: #7a1f1f;
            margin-top: 14px;
        }

        .menu-link.logout:hover {
            background: #b32626;
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
            height: 38px;
            line-height: 38px;
            background: #000080;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
            font-size: 16px;
            letter-spacing: 1px;
            flex-shrink: 0;
            position: relative;
        }

        .topbar-right {
            position: absolute;
            right: 12px;
            top: 0;
            font-size: 11px;
            font-weight: normal;
            letter-spacing: 0;
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
    </style>
</head>

<body>

<div class="layout">

    <div class="sidebar">
        <div class="sidebar-title">
            MENU<br>
            PPIC SYSTEM
        </div>

        <div class="user-box">
            User: <?php echo h($dbUser); ?><br>
            Login: <?php echo h($loginTime); ?>
        </div>

        <div class="menu-section">Main</div>
        <a class="menu-link active" href="dashboard_home.php" target="mainFrame">Dashboard / Report</a>
		
		<div class="menu-section">SCHEDULE OVH MOULDING </div>
		<a class="menu-link" href="view_matrix_ovh.php" target="mainFrame">TOTAL SHOOT </a> 

        <div class="menu-section">MACHINE CAPACITY BY SCHEDULE </div>
		<a class="menu-link" href="report_kapasitas_mesin.php" target="mainFrame">MACHINE CAPACITY BY SCHEDULE</a> 	 
		<div class="menu-section">PROD SCH </div>
		<a class="menu-link" href="prod_sch.php" target="mainFrame">PRODUCTION SCHEDULE</a> 
		<div class="menu-section">MAT USE </div>
		<a class="menu-link" href="mat_use.php" target="mainFrame">MATERIAL USE</a> 
		<div class="menu-section">MRP </div>
		<a class="menu-link" href="mrp_use.php" target="mainFrame">MATERIAL REQUEST PLANNING</a> 


		
		<div class="menu-section">PPIC Entry</div>
		<a class="menu-link" href="hitung_label.php" target="mainFrame">REPORT KEBUTUHAN LABEL PLANT2</a>
		<a class="menu-link" href="packaging.php" target="mainFrame">REPORT KEBUTUHAN PACK DAN BOX PLANT2</a>
		<a class="menu-link" href="hitung_label_vendor.php" target="mainFrame">REPORT KEBUTUHAN LABEL VENDOR</a>
		<a class="menu-link" href="packaging_vendor.php" target="mainFrame">REPORT KEBUTUHAN PACK DAN BOX VENDOR</a>
		<a class="menu-link" href="no_std.php" target="mainFrame">NO STD</a>
        <a class="menu-link" href="std_cust.php" target="mainFrame">NO STD CUSTOMER</a>
        <a class="menu-link" href="grid_input.php" target="mainFrame">LIST MASTER HITACHI</a>
        <a class="menu-link" href="input.php" target="mainFrame">BUAT QR HITACHI LABEL</a>
        <a class="menu-link" href="label_plant2.php" target="mainFrame">LABEL MANUAL PLANT 2</a>
		<a class="menu-link" href="prod_sch.php" target="_blank">PRODUCTION SCHEDULE</a>
       

        <div class="menu-section">Master</div>
        <a class="menu-link" href="master_item_prod.php" target="mainFrame">BOM MASTER</a>
        <a class="menu-link" href="master_machine.php" target="mainFrame">MASTER MACHINE</a>
        <a class="menu-link" href="master_process.php" target="mainFrame">MASTER PROSES</a>
		<a class="menu-link" href="mcs.php" target="_blank">MCS</a>
		<a class="menu-link" href="mcs_production.php" target="_blank">MCS MP</a>
       
		

       

       <a class="menu-link logout" href="logout.php" target="_top">Logout</a>
    </div>

    <div class="main">
        <div class="topbar">
            PPIC SYSTEM - PLANT 2
            <div class="topbar-right">
                <?php echo h(date("d-M-Y H:i")); ?>
            </div>
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
        if (this.getAttribute("target") == "_top") {
            return true;
        }

        for (var j = 0; j < menuLinks.length; j++) {
            menuLinks[j].className = menuLinks[j].className.replace(" active", "");
        }

        if (this.className.indexOf("active") < 0) {
            this.className = this.className + " active";
        }

        return true;
    };
}
</script>

</body>
</html>
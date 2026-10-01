<?php
if (session_id() == "") {
    session_start();
}

require_once __DIR__ . "/../config/db_plant1.php";

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
    <title>PURCHASING System - Plant 2</title>

<style>
        html, body {
            margin: 0; padding: 0; width: 100%; height: 100%;
            background: #f1f5f9; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            font-size: 13px; color: #334155; overflow: hidden;
        }
        .layout { display: flex; width: 100%; height: 100vh; }
        
        /* Sidebar Modern */
        .sidebar {
            width: 250px; min-width: 250px; height: 100vh;
            background: #0f172a; color: #f8fafc;
            box-sizing: border-box; padding: 25px 15px;
            overflow-y: auto; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 10;
        }
        .sidebar-title {
            font-size: 16px; font-weight: 800; margin-bottom: 25px;
            text-align: center; line-height: 1.5; letter-spacing: 1px; color: #38bdf8;
        }
        .user-box {
            background: #1e293b; border: 1px solid #334155; border-radius: 8px;
            padding: 12px; margin-bottom: 25px; font-size: 12px; line-height: 1.6;
            color: #cbd5e1; text-align: center;
        }
        .menu-section {
            font-size: 11px; color: #64748b; margin: 20px 0 10px 5px;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .menu-link {
            display: block; color: #e2e8f0; text-decoration: none;
            padding: 10px 15px; margin-bottom: 5px; border-radius: 6px;
            font-size: 13px; font-weight: 500; transition: all 0.2s ease;
        }
        .menu-link:hover { background: #334155; color: #ffffff; transform: translateX(3px); }
        .menu-link.active {
            background: #38bdf8; color: #0f172a; font-weight: 700;
            box-shadow: 0 4px 6px -1px rgba(56,189,248,0.3);
        }
        .menu-link.logout {
            background: #ef4444; color: #ffffff; margin-top: 30px; text-align: center;
        }
        .menu-link.logout:hover { background: #dc2626; transform: none; }
        
        /* Area Konten Utama */
        .main {
            flex: 1; height: 100vh; display: flex; flex-direction: column;
            background: #f8fafc; overflow: hidden;
        }
        .topbar {
            height: 55px; line-height: 55px; background: #ffffff; color: #0f172a;
            font-weight: 700; text-align: center; font-size: 16px; letter-spacing: 1px;
            flex-shrink: 0; position: relative; box-shadow: 0 1px 3px rgba(0,0,0,0.05); z-index: 5;
        }
        .topbar-right {
            position: absolute; right: 20px; top: 0; font-size: 12px;
            font-weight: 600; color: #64748b; letter-spacing: 0;
        }
        .frame-area { flex: 1; overflow: hidden; background: #f1f5f9; padding: 15px; }
        #mainFrame {
            width: 100%; height: 100%; border: none; background: #ffffff;
            border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
    </style>
</head>

<body>

<div class="layout">

    <div class="sidebar">
        <div class="sidebar-title">
            MENU<br>
            PURCHASING SYSTEM
        </div>

        <div class="user-box">
            User: <?php echo h($dbUser); ?><br>
            Login: <?php echo h($loginTime); ?>
        </div>

        <div class="menu-section">Main</div>
        <a class="menu-link active" href="dashboard_home.php" target="mainFrame">Dashboard / Report</a>

        <div class="menu-section">PURCHASING Entry</div>
        <a class="menu-link" href="requisition.php" target="mainFrame">PURCHASE REQUESTION</a>
        <a class="menu-link" href="quotation.php" target="mainFrame">QUOTATION</a>
        <a class="menu-link" href="po.php" target="mainFrame">PURCHASE ORDER</a>
		 <!-- <a class="menu-link" href="label_plant2.php" target="mainFrame">RECEIVE</a> -->
         <a class="menu-link" href="receive.php" target="mainFrame">RECEIVE</a>
        
        <div class="menu-section">Master</div>
        <a class="menu-link" href="master_supplier.php" target="mainFrame">MASTER SUPPLIER </a>
        

       

        <a class="menu-link logout" href="logout.php" target="_top">Logout</a>
    </div>

    <div class="main">
        <div class="topbar">
            PURCHASING SYSTEM - PLANT 1
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
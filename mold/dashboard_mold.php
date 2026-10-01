<?php
require_once __DIR__ . "/../config/database_mold.php";
$loginTime = isset($_SESSION["login_time"]) ? $_SESSION["login_time"] : date("Y-m-d H:i");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Mold Controling System</title>
    <style>
        html, body { margin: 0; padding: 0; width: 100%; height: 100%; background: #d4d0c8; font-family: Tahoma, Arial, sans-serif; font-size: 12px; overflow: hidden; }
        .layout { display: flex; width: 100%; height: 100vh; }
        .sidebar { width: 230px; min-width: 230px; height: 100vh; background: #1a252f; color: #ffffff; box-sizing: border-box; padding: 18px 14px; overflow-y: auto; }
        .sidebar-title { font-size: 14px; font-weight: bold; margin-bottom: 18px; text-align: center; line-height: 20px; color: #e74c3c; }
        .user-box { background: #2c3e50; border: 1px solid #34495e; border-radius: 4px; padding: 8px; margin-bottom: 16px; font-size: 11px; line-height: 16px; }
        .menu-section { font-size: 11px; color: #bdc3c7; margin: 14px 0 6px 2px; font-weight: bold; text-transform: uppercase; }
        .menu-link { display: block; color: #ffffff; text-decoration: none; padding: 10px; margin-bottom: 4px; border-radius: 4px; background: transparent; }
        .menu-link:hover, .menu-link.active { background: #2980b9; font-weight: bold; }
        .menu-link.logout { background: #c0392b; margin-top: 20px; }
        .menu-link.logout:hover { background: #e74c3c; }
        .main { flex: 1; height: 100vh; display: flex; flex-direction: column; background: #d4d0c8; }
        .topbar { height: 38px; line-height: 38px; background: #2c3e50; color: #ffffff; font-weight: bold; text-align: center; font-size: 14px; position: relative; }
        .topbar-right { position: absolute; right: 12px; top: 0; font-size: 11px; font-weight: normal; }
        .frame-area { flex: 1; overflow: hidden; }
        #mainFrame { width: 100%; height: 100%; border: none; }
    </style>
</head>
<body>
<div class="layout">
    <div class="sidebar">
        <div class="sidebar-title">MOLD CONTROL SYSTEM<br>PLANT <?= ($_SESSION['active_plant'] == 'p2') ? '2' : '1'; ?></div>
        <div class="user-box">
            User: <?= h($uid); ?><br>
            Login: <?= h($loginTime); ?>
        </div>
        
        <div class="menu-section">Main Menu</div>
        <a class="menu-link active" href="home_dashboard.php" target="mainFrame">Dashboard</a>
        
        <div class="menu-section">Menu Entry/Input</div>
        <a class="menu-link" href="report_center.php" target="mainFrame">Mold History Entry</a>
        <a class="menu-link" href="mold_trans.php" target="mainFrame">Mold Transaction Entry</a>
        <a class="menu-link" href="mold_tags.php" target="mainFrame">Generate Mold Tags</a>
        
        <div class="menu-section">Master Data</div>
        <a class="menu-link" href="mold_master.php" target="mainFrame">Mold Master</a>
        <a class="menu-link" href="master_classification.php" target="mainFrame">Classification Master</a>
        
        <a class="menu-link logout" href="logout.php" target="_top">Logout</a>
    </div>
    
    <div class="main">
        <div class="topbar">
            MOLD CONTROL ERP INTERFACE
            <div class="topbar-right"><?= date("d-M-Y H:i"); ?></div>
        </div>
        <div class="frame-area">
            <iframe id="mainFrame" name="mainFrame" src="home_dashboard.php"></iframe>
        </div>
    </div>
</div>

<script>
    let links = document.querySelectorAll(".menu-link");
    links.forEach(link => {
        link.addEventListener("click", function() {
            if(this.getAttribute("target") === "_top") return true;
            links.forEach(l => l.classList.remove("active"));
            this.classList.add("active");
        });
    });
</script>
</body>
</html>
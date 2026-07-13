<?php
if (session_id() == "") {
    session_start();
}

require_once __DIR__ . "/../config/global.php";

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
    <title>PRODUCTION System - Plant 2</title>

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
            PRODUCTION SYSTEM
        </div>

        <div class="user-box">
            User: <?php echo h($dbUser); ?><br>
            Login: <?php echo h($loginTime); ?>
        </div>

        <div class="menu-section">Main</div>
        <a class="menu-link active" href="dashboard_home.php" target="mainFrame">Dashboard / Report</a>

        <div class="menu-section">PRODUCTION Entry</div>
        <a class="menu-link" href="requisition.php" target="mainFrame">PRODUCTION REQUESTION</a>
        <a class="menu-link" href="quotation.php" target="mainFrame">QUOTATION</a>
        <a class="menu-link" href="po.php" target="mainFrame">PRODUCTION ORDER</a>
		 <a class="menu-link" href="label_plant2.php" target="mainFrame">RECEIVE</a>
        
        <div class="menu-section">Master</div>
        <a class="menu-link" href="master_supplier.php" target="mainFrame">MASTER SUPPLIER </a>
        

       

        <a class="menu-link logout" href="../prod2/logout.php">Logout</a>
    </div>

    <div class="main">
        <div class="topbar">
            PRODUCTION SYSTEM - PLANT 2
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
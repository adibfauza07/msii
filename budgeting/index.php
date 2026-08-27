<?php
if (session_id() == "") {
    session_start();
}

// Memanggil konfigurasi mandiri modul budgeting
require_once "config.php";

// Jika config.php mengembalikan koneksi false (meskipun sudah ditangani di config, ini double check)
if ($conn === false) {
    header("Location: login.php?error=session_expired");
    exit();
}

$dbUser = isset($_SESSION["db_user"]) ? $_SESSION["db_user"] : "User";
// Set waktu login jika belum ada di session (bisa ditambahkan saat proses login sukses di login.php)
$loginTime = isset($_SESSION["login_time"]) ? $_SESSION["login_time"] : date("d-M-Y H:i");

// Fungsi sanitasi output standar untuk mencegah XSS
function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Budgeting System - ERP</title>

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

        /* Sidebar Styling */
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

        /* Main Area */
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
            background: #0056b3; /* Warna disesuaikan untuk Budgeting */
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
            background: #f4f6f9; /* Agar Bootstrap di iframe terlihat lebih bersih */
        }

        #mainFrame {
            width: 100%;
            height: 100%;
            border: none;
            background: transparent;
        }
    </style>
</head>

<body>

<div class="layout">

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-title">
            MENU<br>
            BUDGETING SYSTEM
        </div>

        <div class="user-box">
            User: <?php echo h($dbUser); ?><br>
            Login: <?php echo h($loginTime); ?>
        </div>
        <div class="menu-section">APPROVAL FINANCE</div>
		<a class="menu-link" href="finance_approval.php" target="mainFrame">APPROVAL FINANCE</a>
		
		
        <div class="menu-section">Summary & Laporan</div>
        <a class="menu-link active" href="dashboard_home.php" target="mainFrame">Dashboard</a>
        <a class="menu-link" href="pr_report.php" target="mainFrame">Laporan PR Dept</a>
		 <a class="menu-link" href="receive_report.php" target="mainFrame">Laporan RECEIVE Dept</a>
        <a class="menu-link" href="outstanding_pr.php" target="mainFrame">Outstanding PR vs ICL</a>

        <div class="menu-section">Transaksi</div>
        <a class="menu-link" href="form_budget.php" target="mainFrame">Input Planning Budget</a>
        <a class="menu-link" href="input_pr.php" target="mainFrame">PURCHASE REQUEST (PR) NO PO</a>
		<a class="menu-link" href="input_pr_msdata.php" target="mainFrame">PURCHASE REQUEST (PR) PO</a>
        <!-- Tambahan Menu Penerimaan Barang -->
        <a class="menu-link" href="receive.php" target="mainFrame">Input Receive (ICL)</a>
        
        <div class="menu-section">Master Data</div>
		 <a class="menu-link" href="master_vendor.php" target="mainFrame">Master Supplier</a>
        <a class="menu-link" href="master_department.php" target="mainFrame">Master Department</a>
        <a class="menu-link" href="master_item.php" target="mainFrame">Master Barang</a>
        <a class="menu-link" href="master_quotation.php" target="mainFrame">Master Quotation</a>


        <a class="menu-link logout" href="logout.php" target="_top">Logout</a>
    </div>

    <!-- MAIN AREA -->
    <div class="main">
        <div class="topbar">
            ERP BUDGETING MODULE
            <div class="topbar-right">
                <?php echo h(date("d-M-Y H:i")); ?> WIB
            </div>
        </div>

        <div class="frame-area">
            <!-- Iframe akan memuat dashboard Bootstrap yang sebelumnya ada di index.php -->
            <iframe id="mainFrame" name="mainFrame" src="dashboard_home.php"></iframe>
        </div>
    </div>

</div>

<!-- JAVASCRIPT UNTUK MENU ACTIVE -->
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
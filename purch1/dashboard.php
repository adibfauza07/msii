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
        /* ==== CSS NAVBAR MODERN & RESPONSIVE ==== */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        html, body {
            margin: 0; padding: 0; width: 100%; height: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f0f2f5; overflow: hidden; display: flex; flex-direction: column;
        }
        
        /* Navbar Utama */
        .navbar {
            display: flex; align-items: center; justify-content: space-between;
            background-color: #1e293b; color: #f8fafc; height: 50px; padding: 0 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex-shrink: 0; z-index: 1000;
        }
        
        .nav-brand { 
            font-size: 15px; font-weight: 800; color: #38bdf8; letter-spacing: 0.5px; 
            display: flex; align-items: center; gap: 8px;
        }
        .nav-brand span { 
            color: #94a3b8; font-weight: 500; font-size: 12px; 
            border-left: 1px solid #475569; padding-left: 8px;
        }
        
        /* List Menu */
        .nav-menu { 
            display: flex; list-style: none; margin: 0; padding: 0; height: 100%; align-items: center; 
        }
        .nav-menu li { 
            height: 100%; position: relative; display: flex; align-items: center; 
        }
        .nav-menu a {
            color: #cbd5e1; text-decoration: none; font-size: 12.5px; font-weight: 500;
            padding: 0 16px; height: 100%; display: flex; align-items: center; transition: all 0.2s;
            cursor: pointer;
        }
        .nav-menu a:hover, .nav-menu a.active { 
            color: #ffffff; background-color: #334155; 
        }
        
        /* Dropdown Menu */
        .dropdown-content {
            display: none; position: absolute; top: 50px; left: 0; background-color: #ffffff;
            min-width: 220px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); 
            border-radius: 0 0 6px 6px; overflow: hidden; border: 1px solid #e2e8f0; 
            border-top: none; flex-direction: column; z-index: 9999;
        }
        .dropdown:hover .dropdown-content { display: flex; }
        .dropdown-content a {
            color: #334155; padding: 12px 16px; height: auto; text-decoration: none;
            display: block; border-bottom: 1px solid #f1f5f9; font-size: 12px; font-weight: 500;
        }
        .dropdown-content a:last-child { border-bottom: none; }
        .dropdown-content a:hover { background-color: #f8fafc; color: #0284c7; padding-left: 20px; }
        
        /* Bagian Kanan (User Info & Logout) */
        .nav-right { display: flex; align-items: center; gap: 15px; }
        .user-info { font-size: 11px; color: #94a3b8; text-align: right; line-height: 1.3; }
        .user-info strong { color: #f8fafc; font-weight: 600; font-size: 12px; }
        
        .logout-btn {
            background-color: #ef4444; color: #ffffff; padding: 6px 12px; border-radius: 4px;
            text-decoration: none; font-size: 11px; font-weight: 600; transition: background 0.2s;
            text-transform: uppercase; letter-spacing: 0.5px; display: inline-block;
        }
        .logout-btn:hover { background-color: #dc2626; color: #fff; }
        
        /* Area Iframe */
        .frame-area { 
            flex: 1; background: #f0f2f5; position: relative; overflow: hidden; 
            padding: 15px; box-sizing: border-box; 
        }
        #mainFrame { 
            width: 100%; height: 100%; border: none; background: #ffffff; 
            border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); 
        }
    </style>
</head>

<body>

    <!-- TOP NAVBAR -->
    <nav class="navbar">
        <div class="nav-brand">
            PURCHASING <span>PLANT 1</span>
        </div>
        
        <ul class="nav-menu">
            <li>
                <a href="dashboard_home.php" target="mainFrame" class="menu-link active">Dashboard</a>
            </li>
            
            <li class="dropdown">
                <a class="dropbtn">Purchasing Entry ▼</a>
                <div class="dropdown-content">
                    <a href="requisition.php" target="mainFrame" class="menu-link">Purchase Requisition</a>
                    <a href="quotation.php" target="mainFrame" class="menu-link">Quotation</a>
                    <a href="po.php" target="mainFrame" class="menu-link">Purchase Order</a>
                    <a href="receive.php" target="mainFrame" class="menu-link">Receive</a>
                </div>
            </li>
            
            <li class="dropdown">
                <a class="dropbtn">Master Data ▼</a>
                <div class="dropdown-content">
                    <a href="master_supplier.php" target="mainFrame" class="menu-link">Master Supplier</a>
                </div>
            </li>
        </ul>
        
        <div class="nav-right">
            <div class="user-info">
                User: <strong><?php echo h($dbUser); ?></strong><br>
                <?php echo h($loginTime); ?>
            </div>
            <a href="logout.php" target="_top" class="logout-btn">Logout</a>
        </div>
    </nav>

    <!-- KONTEN UTAMA (IFRAME) -->
    <div class="frame-area">
        <iframe id="mainFrame" name="mainFrame" src="dashboard_home.php"></iframe>
    </div>

<script>
    // Script untuk mengatur efek 'Active' (terpilih) pada menu navbar
    var menuLinks = document.querySelectorAll(".menu-link");
    var dropBtns = document.querySelectorAll(".dropbtn");

    menuLinks.forEach(function(link) {
        link.addEventListener("click", function() {
            // Bersihkan semua class active
            menuLinks.forEach(function(el) { el.classList.remove("active"); });
            dropBtns.forEach(function(el) { el.classList.remove("active"); });

            // Tambahkan active ke link yang diklik
            this.classList.add("active");

            // Jika link berada di dalam dropdown, aktifkan juga tombol dropdown utamanya
            var parentDropdown = this.closest('.dropdown');
            if (parentDropdown) {
                var btn = parentDropdown.querySelector('.dropbtn');
                if(btn) btn.classList.add("active");
            }
        });
    });
</script>

</body>
</html>
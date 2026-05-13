<?php
require_once __DIR__ . "/../config/db_plant2.php";

if ($conn === false) {
    header("Location: login.php?error=session_expired");
    exit();
}

$dbUser = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : "";
$loginTime = isset($_SESSION['login_time']) ? $_SESSION['login_time'] : "";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dashboard Ordering Plant 2</title>

    <style>
        body {
            margin: 0;
            padding: 0;
            background: #d4d0c8;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            color: #000000;
        }

        .topbar {
            background: #000080;
            color: #ffffff;
            padding: 8px 12px;
            font-weight: bold;
        }

        .container {
            width: 1000px;
            margin: 20px auto;
            border: 2px solid #808080;
            background: #d4d0c8;
            padding: 12px;
            box-sizing: border-box;
        }

        .welcome {
            border: 1px solid #808080;
            background: #eeeeee;
            padding: 10px;
            margin-bottom: 12px;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .menu-card {
            border: 1px solid #808080;
            background: #f0f0f0;
            padding: 14px;
            min-height: 100px;
            box-sizing: border-box;
        }

        .menu-card h3 {
            margin: 0 0 8px 0;
            font-size: 14px;
        }

        .menu-card p {
            margin: 0 0 12px 0;
            line-height: 1.4;
        }

        a.button {
            display: inline-block;
            text-decoration: none;
            color: #000000;
            background: #d4d0c8;
            border: 2px outset #ffffff;
            padding: 5px 14px;
        }

        a.button:active {
            border: 2px inset #ffffff;
        }

        .bottom-row {
            margin-top: 16px;
            text-align: right;
        }

        .status {
            margin-top: 12px;
            border: 1px solid #808080;
            background: #ffffff;
            padding: 8px;
        }
    </style>
</head>

<body>

<div class="topbar">
    ORDERING SYSTEM - PLANT 2
</div>

<div class="container">

    <div class="welcome">
        Login sebagai: <b><?php echo htmlspecialchars($dbUser, ENT_QUOTES, 'UTF-8'); ?></b><br>
        Server: <b>192.168.0.9</b><br>
        Database: <b>msData</b><br>
        Login time: <b><?php echo htmlspecialchars($loginTime, ENT_QUOTES, 'UTF-8'); ?></b>
    </div>

    <div class="menu-grid">

        <div class="menu-card">
            <h3>Manual Delivery Instruction</h3>
            <p>Input dan proses manual DI seperti form Delphi.</p>
            <a class="button" href="manual_order.php">OPEN</a>
        </div>

        <div class="menu-card">
            <h3>Import PO</h3>
            <p>Menu import PO. Nanti bisa diarahkan ke modul import PO.</p>
            <a class="button" href="#" onclick="alert('Modul Import PO dibuat step berikutnya.'); return false;">OPEN</a>
        </div>

        <div class="menu-card">
            <h3>Import Schedule</h3>
            <p>Menu import schedule. Nanti bisa diarahkan ke modul import schedule.</p>
            <a class="button" href="#" onclick="alert('Modul Import Schedule dibuat step berikutnya.'); return false;">OPEN</a>
        </div>

        <div class="menu-card">
            <h3>Edit Order</h3>
            <p>Menu edit order seperti form Delphi ORDER edit.</p>
            <a class="button" href="#" onclick="alert('Modul Edit Order dibuat step berikutnya.'); return false;">OPEN</a>
        </div>

        <div class="menu-card">
            <h3>Report</h3>
            <p>Menu laporan invoice, delivery sheet, packing list, dan selling card.</p>
            <a class="button" href="#" onclick="alert('Modul Report dibuat step berikutnya.'); return false;">OPEN</a>
        </div>

        <div class="menu-card">
            <h3>Logout</h3>
            <p>Keluar dari sistem dan hapus session login.</p>
            <a class="button" href="login.php?logout=1">LOGOUT</a>
        </div>

    </div>

    <div class="status">
        Status koneksi: <b>Connected</b>
    </div>

    <div class="bottom-row">
        <a class="button" href="login.php?logout=1">LOGOUT</a>
    </div>

</div>

</body>
</html>
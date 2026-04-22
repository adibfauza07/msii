<?php
// Gunakan pengecekan sesi yang sama dengan inventory kamu
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['db_user'])) { header("Location: ../login.php"); exit(); }

require_once __DIR__ . '/../config/database_p1.php'; // Pastikan path benar
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Ordering System Plant 2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Pakai style sidebar yang sudah kamu punya di dashboard_inv.php */
        /* ... (Copy paste style CSS dari dashboard_inv.php) ... */
    </style>
</head>
<body>
    <div id="sidebar">
        <div class="brand"><i class="bi bi-cart-check"></i> ORDERING P2</div>
        <div class="py-2 overflow-auto h-100">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="?page=home" class="nav-link <?= ($page=='home')?'active':'' ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
                </li>
                <div class="menu-label">Plan & Schedule</div>
                <li class="nav-item">
                    <a href="?page=forecast" class="nav-link <?= ($page=='forecast')?'active':'' ?>"><i class="bi bi-graph-up"></i> Forecast</a>
                </li>
                <div class="menu-label">Order & Delivery</div>
                <li class="nav-item">
                    <a href="?page=order_po" class="nav-link <?= ($page=='order_po')?'active':'' ?>"><i class="bi bi-file-earmark-text"></i> Order PO</a>
                </li>
                <li class="nav-item">
                    <a href="?page=di_auto" class="nav-link <?= ($page=='di_auto')?'active':'' ?>"><i class="bi bi-truck"></i> DI Auto Manual</a>
                </li>
                <div class="menu-label">Reports</div>
                <li class="nav-item">
                    <a href="?page=rpt_outstanding" class="nav-link <?= ($page=='rpt_outstanding')?'active':'' ?>"><i class="bi bi-clipboard-data"></i> Outstanding Order</a>
                </li>
            </ul>
        </div>
    </div>

    <div id="content">
        <?php 
        switch($page) {
            case 'forecast': include "page_forecast.php"; break; // Dari Ucast.pas
            case 'di_auto': include "page_di_auto.php"; break;   // Dari UAuto_po.pas
            case 'rpt_outstanding': include "page_rpt_outstanding.php"; break; // Dari UCustOrder.pas
            default: echo "<h3>Welcome to Ordering Plant 2 System</h3>"; break;
        }
        ?>
    </div>
</body>
</html>


<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['db_user'])) {
    header("Location: login.php");
    exit;
}

$login_user = isset($_SESSION['db_user']) ? strtolower(trim($_SESSION['db_user'])) : '';
$active_plant = isset($_SESSION['active_plant']) ? strtolower(trim($_SESSION['active_plant'])) : '';

$isP1 = ($login_user == 'plant1' || $active_plant == 'p1');
$isP2 = ($login_user == 'plant2' || $active_plant == 'p2');

/*
   Fallback:
   Kalau active_plant kosong dan user bukan plant1/plant2,
   jangan tampilkan menu khusus plant.
*/
$plantLabel = isset($_SESSION['active_plant']) ? strtoupper($_SESSION['active_plant']) : '-';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Finance System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        body {
            background-color: #f8f9fa;
        }

        .sidebar {
            height: 100vh;
            width: 250px;
            position: fixed;
            left: 0;
            top: 0;
            background: #2c3e50;
            color: white;
            overflow-y: auto;
            transition: 0.3s;
        }

        .sidebar a {
            color: #bdc3c7;
            text-decoration: none;
            padding: 13px 20px;
            display: block;
            transition: 0.3s;
            font-size: 13px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #34495e;
            color: white;
            border-left: 4px solid #e74c3c;
        }

        .sidebar hr {
            border-color: rgba(255,255,255,0.2);
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
            background-color: #f4f7f6;
            min-height: 100vh;
        }

        .card-stats {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .card {
            border: none;
            border-radius: 12px;
        }

        .table thead th {
            background-color: #2c3e50 !important;
            color: white;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }
    </style>
</head>

<body>

<div class="sidebar">
    <div class="p-4 text-center">
        <h5 class="fw-bold"><i class="bi bi-bank2 me-2"></i>FINANCE</h5>
        <hr>
    </div>

    <a href="dashboard.php">
        <i class="bi bi-speedometer2 me-2"></i> Dashboard
    </a>

    <a href="aging_sales.php">
        <i class="bi bi-graph-up-arrow me-2"></i> Aging Sales
    </a>

    <a href="aging_ap.php">
        <i class="bi bi-graph-down-arrow me-2"></i> Aging AP
    </a>
	
	<a href="laporan_laba_rugi.php">
        <i class="bi bi-graph-down-arrow me-2"></i> LAPORAN LABA RUGI
    </a>
	<a href="jurnal_material.php">
        <i class="bi bi-graph-down-arrow me-2"></i> JURNAL MATERIAL
    </a>
	

    <?php if ($isP1) { ?>
	

	     <a href="import_bom_tally1.php">
        <i class="bi bi-graph-down-arrow me-2"></i> IMPORT BOM
    </a>
	
	<a href="import_prod_tally1.php">
        <i class="bi bi-graph-down-arrow me-2"></i> IMPORT PRODUCTION TO TALLY 
    </a>
	
	<a href="consumtion_tally1.php">
        <i class="bi bi-graph-down-arrow me-2"></i> IMPORT CONSUMTION TO TALLY 
    </a>
	
        <a href="export_receive_p1.php">
            <i class="bi bi-box-arrow-in-down me-2"></i> IMPORT RECEIVE NOTE TO TALLY P1
        </a>

        <a href="export_sales_p1.php">
            <i class="bi bi-box-arrow-in-down me-2"></i> IMPORT SALES TO TALLY P1
        </a>

        <a href="import_sop1.php">
            <i class="bi bi-box-arrow-in-down me-2"></i> IMPORT SOP TO TALLY P1
        </a>

        <a href="tally_import.php">
            <i class="bi bi-arrow-left-right me-2"></i> Import SQL To Tally Plant1
        </a>
    <?php } ?>

    <?php if ($isP2) { ?>
	     <a href="import_bom_tally.php">
        <i class="bi bi-graph-down-arrow me-2"></i> IMPORT BOM
    </a>
	
	<a href="import_prod_tally.php">
        <i class="bi bi-graph-down-arrow me-2"></i> IMPORT PRODUCTION TO TALLY 
    </a>
	
	<a href="consumtion_tally.php">
        <i class="bi bi-graph-down-arrow me-2"></i> IMPORT CONSUMTION TO TALLY 
    </a>
	
        <a href="export_receive.php">
            <i class="bi bi-box-arrow-in-down me-2"></i> IMPORT RECEIVE NOTE TO TALLY P2
        </a>

        <a href="export_sales_p2.php">
            <i class="bi bi-box-arrow-in-down me-2"></i> IMPORT SALES TO TALLY P2
        </a>

        <a href="import_sop2.php">
            <i class="bi bi-box-arrow-in-down me-2"></i> IMPORT SOP TO TALLY P2
        </a>

        <a href="tally_import_p2.php">
            <i class="bi bi-arrow-left-right me-2"></i> Import SQL To Tally P2
        </a>
    <?php } ?>

       
     <a href="inv_coretax_epson.php">
        <i class="bi bi-file-earmark-excel me-2"></i> Invoice Coretax Epson
    </a>  

    <a href="inv_coretax.php">
        <i class="bi bi-file-earmark-excel me-2"></i> Invoice Coretax
    </a>

    <a href="inv_coretax_monthly.php">
        <i class="bi bi-calendar-range me-2"></i> Coretax By Date
    </a>

    <hr>

    <a href="logout.php" class="text-danger">
        <i class="bi bi-box-arrow-left me-2"></i> Logout
    </a>
</div>

<div class="main-content">
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1 fs-6 text-secondary">
                <i class="bi bi-buildings me-1"></i> PLANT: <?php echo strtoupper($_SESSION['active_plant']); ?>
            </span>

            <div class="d-flex align-items-center">
                <span class="me-3 small text-muted">
                    Welcome, <strong><?php echo strtoupper($_SESSION['active_plant']); ?></strong>
                </span>
                <i class="bi bi-person-circle fs-4"></i>
            </div>
        </div>
    </nav>
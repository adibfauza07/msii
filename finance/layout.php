<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['db_user'])) header("Location: login.php");
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
        body { background-color: #f8f9fa; }
        .sidebar { height: 100vh; width: 250px; position: fixed; background: #2c3e50; color: white; transition: 0.3s; }
        .sidebar a { color: #bdc3c7; text-decoration: none; padding: 15px 20px; display: block; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { background: #34495e; color: white; border-left: 4px solid #e74c3c; }
        .main-content { margin-left: 250px; padding: 20px; }
        .card-stats { border: none; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        /* Tambahkan atau pastikan ini ada di layout.php */
.main-content {
    margin-left: 250px; /* Lebar sidebar */
    padding: 30px;
    background-color: #f4f7f6;
    min-height: 100vh;
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
    <a href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    <a href="aging_sales.php"><i class="bi bi-graph-up-arrow me-2"></i> Aging Sales</a>
    <a href="aging_ap.php"><i class="bi bi-graph-down-arrow me-2"></i> Aging AP</a>
	<a href="export_receive.php"><i class="bi bi-graph-down-arrow me-2"></i> EXPOR RECEIVE NOTE TO TALLY</a>
	<a href="export_receive_p1.php"><i class="bi bi-graph-down-arrow me-2"></i> EXPOR RECEIVE NOTE TO TALLY P1</a>
	<a href="import_sop2.php"><i class="bi bi-graph-down-arrow me-2"></i> EXPOR SOP TO TALLY</a>
    <a href="inv_coretax.php" class="nav-link">
    <i class="bi bi-file-earmark-excel"></i> Invoice Coretax
	<a href="inv_coretax_monthly.php" class="nav-link">
    <i class="bi bi-calendar-range"></i> Coretax By Date
</a>
	<?php
$login_user = isset($_SESSION['db_user']) ? strtolower(trim($_SESSION['db_user'])) : '';
$active_plant_menu = isset($_SESSION['active_plant']) ? strtolower(trim($_SESSION['active_plant'])) : '';

$allow_tally_menu = false;

if ($login_user == 'plant1' || $active_plant_menu == 'p1') {
    $allow_tally_menu = true;
}
?>

<?php if ($allow_tally_menu) { ?>
    <a href="tally_import.php" class="nav-link">
        <i class="bi bi-arrow-left-right"></i> Import SQL To Tally plant1
    </a>
<?php } ?>
<?php
$login_user = isset($_SESSION['db_user']) ? strtolower(trim($_SESSION['db_user'])) : '';
$active_plant_menu = isset($_SESSION['active_plant']) ? strtolower(trim($_SESSION['active_plant'])) : '';

if ($login_user == 'plant2' || $active_plant_menu == 'p2') {
?>
    <a href="tally_import_p2.php" class="nav-link">
        <i class="bi bi-arrow-left-right"></i> Import SQL To Tally P2
    </a>
<?php } ?>
</a>

    <hr>
    <a href="logout.php" class="text-danger"><i class="bi bi-box-arrow-left me-2"></i> Logout</a>
</div>

<div class="main-content">
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4 border-radius-10">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1 fs-6 text-secondary">
                <i class="bi bi-buildings me-1"></i> PLANT: <?php echo strtoupper($_SESSION['active_plant']); ?>
            </span>
            <div class="d-flex align-items-center">
                <span class="me-3 small text-muted">Welcome, <strong><?php echo $_SESSION['db_user']; ?></strong></span>
                <i class="bi bi-person-circle fs-4"></i>
            </div>
        </div>
    </nav>
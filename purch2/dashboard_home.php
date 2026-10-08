<?php
if (session_id() == "") {
    session_start();
}

require_once __DIR__ . "/../config/db_plant2.php";

if ($conn === false) {
    echo "Koneksi database gagal.";
    exit();
}

$dbUser = isset($_SESSION["db_user"]) ? $_SESSION["db_user"] : "Guest";
$loginTime = isset($_SESSION["login_time"]) ? $_SESSION["login_time"] : date("Y-m-d H:i:s");

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report Menu | Purchasing Plant 2</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Theme style (AdminLTE) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
</head>
<body class="hold-transition layout-top-nav">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand-md navbar-light navbar-white">
        <div class="container">
            <a href="#" class="navbar-brand">
                <i class="fas fa-industry text-primary mr-2"></i>
                <span class="brand-text font-weight-light"><b>Purchasing</b> Plant 2</span>
            </a>
            
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <span class="nav-link text-success"><i class="fas fa-circle"></i> Connected to msData</span>
                </li>
                <li class="nav-item dropdown">
                    <a id="dropdownSubMenu1" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" class="nav-link dropdown-toggle">
                        <i class="fas fa-user-circle"></i> <?php echo h($dbUser); ?>
                    </a>
                    <ul aria-labelledby="dropdownSubMenu1" class="dropdown-menu border-0 shadow">
                        <li><a href="#" class="dropdown-item">Login time: <?php echo h($loginTime); ?></a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
    <!-- /.navbar -->

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"> Report Menu <small>Dashboard</small></h1>
                    </div>
                </div><!-- /.row -->
            </div><!-- /.container-fluid -->
        </div>
        <!-- /.content-header -->

        <!-- Main content -->
        <div class="content">
            <div class="container">

                <!-- Server Info Card -->
                <div class="card card-outline card-primary mb-4">
                    <div class="card-body py-2">
                        <div class="row text-center text-md-left">
                            <div class="col-md-3 col-6 border-right">
                                <span class="text-muted"><i class="fas fa-user"></i> Login:</span> <b><?php echo h($dbUser); ?></b>
                            </div>
                            <div class="col-md-3 col-6 border-right">
                                <span class="text-muted"><i class="fas fa-server"></i> Server:</span> <b>192.168.0.9</b>
                            </div>
                            <div class="col-md-3 col-6 border-right">
                                <span class="text-muted"><i class="fas fa-database"></i> Database:</span> <b>msData</b>
                            </div>
                            <div class="col-md-3 col-6">
                                <span class="text-muted"><i class="fas fa-clock"></i> Time:</span> <b><?php echo h($loginTime); ?></b>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reports Grid (AdminLTE Small Boxes) -->
                <div class="row">
                    <!-- Report Item 1 -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h6>PURCHASE</h6>
                                <p>Report</p>
                            </div>
                            <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                            <a href="purchase1.php" target="_blank" class="small-box-footer">Open Report <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>

                    <!-- Report Item 2 -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h6>FINANCE P1 & P2</h6>
                                <p>Purchase Year</p>
                            </div>
                            <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
                            <a href="purchase_finance.php" target="_blank" class="small-box-footer">Open Report <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>

                    <!-- Report Item 3 -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h6>PURCHASE YEAR</h6>
                                <p>Annual Report</p>
                            </div>
                            <div class="icon"><i class="fas fa-calendar-alt"></i></div>
                            <a href="purchase_year.php" target="_blank" class="small-box-footer text-dark">Open Report <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>

                    <!-- Report Item 4 -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h6>WEEKLY RECEIVE</h6>
                                <p>Supplier</p>
                            </div>
                            <div class="icon"><i class="fas fa-truck-loading"></i></div>
                            <a href="weekly_receive.php" target="_blank" class="small-box-footer">Open Report <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>

                    <!-- Report Item 5 -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-primary">
                            <div class="inner">
                                <h6>OUTSTANDING PO</h6>
                                <p>Supplier</p>
                            </div>
                            <div class="icon"><i class="fas fa-clipboard-list"></i></div>
                            <a href="report_po_outstanding.php" target="_blank" class="small-box-footer">Open Report <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>

                    <!-- Report Item 6 -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-secondary">
                            <div class="inner">
                                <h6>FORECAST VS RCV</h6>
                                <p>Material</p>
                            </div>
                            <div class="icon"><i class="fas fa-chart-line"></i></div>
                            <a href="forecast_receive.php" target="_blank" class="small-box-footer">Open Report <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>

                    <!-- Report Item 7 -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h6>SALES VS RCV</h6>
                                <p>Material</p>
                            </div>
                            <div class="icon"><i class="fas fa-balance-scale"></i></div>
                            <a href="sales_receive.php" target="_blank" class="small-box-footer">Open Report <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>

                    <!-- Report Item 8 -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h6>PRICE LIST</h6>
                                <p>Material</p>
                            </div>
                            <div class="icon"><i class="fas fa-tags"></i></div>
                            <a href="report_price_list.php" target="_blank" class="small-box-footer">Open Report <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>

                </div>
                <!-- /.row -->
            </div><!-- /.container -->
        </div>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->

    <!-- Main Footer -->
    <footer class="main-footer">
        <div class="float-right d-none d-sm-inline">
            Status: <b>Connected</b>
        </div>
        <strong>&copy; <?php echo date("Y"); ?> P.T. IMC TEKNO INDONESIA</strong> - Ordering System Plant 2.
    </footer>
</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.1/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
function notReady(reportName) {
    alert("Report belum dibuat: " + reportName);
}
</script>
</body>
</html>
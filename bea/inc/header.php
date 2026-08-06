<?php
if (!isset($pageTitle)) {
    $pageTitle = 'IT Inventory Kawasan Berikat';
}
if (!isset($currentPage)) {
    $currentPage = 'dashboard';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($pageTitle); ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-fixed-top topbar">
    <div class="container-fluid">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#main-menu">
                <span class="sr-only">Buka navigasi</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="index.php">
                <span class="brand-mark"><i class="fa fa-bar-chart"></i></span>
                IT Inventory P.T. IMC TEKNO INDONESIA
            </a>
        </div>
        <ul class="nav navbar-nav navbar-right hidden-xs">
            <li><a href="#"><i class="fa fa-user-circle"></i> Administrator</a></li>
            <li><a href="#"><i class="fa fa-sign-out"></i> Keluar</a></li>
        </ul>
    </div>
</nav>
<div class="layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="main-content">

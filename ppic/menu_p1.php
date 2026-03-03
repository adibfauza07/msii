<?php
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Laporan P1 - PPIC</title>

    <!-- Bootstrap 3 -->
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <script src="../assets/jquery.min.js"></script>
    <script src="../assets/bootstrap.min.js"></script>

    <style>
        body {
            margin: 0;
            background: #f3f3f3;
            font-family: Arial;
        }

        /* SIDEBAR */
        #sidebar {
            width: 230px;
            height: 100vh;
            background: #1f2a36;
            color: #fff;
            position: fixed;
            left: 0;
            top: 0;
            padding-top: 20px;
        }

        #sidebar a {
            color: #fff;
            padding: 12px 20px;
            display: block;
            text-decoration: none;
            font-size: 14px;
        }

        #sidebar a:hover {
            background: #2c3a4b;
        }

        /* CONTENT */
        #content {
            margin-left: 250px;
            padding: 25px;
        }

        .page-title {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .card-box {
            background: white;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .report-list p {
            font-size: 15px;
            margin-bottom: 10px;
        }

        .report-list a {
            color: #2a4d9b;
            font-weight: bold;
            text-decoration: none;
        }

        .report-list a:hover {
            text-decoration: underline;
        }
    </style>

</head>
<body>

<!-- SIDEBAR -->
<div id="sidebar">
    <a href="dashboard_ppic.php">🏠 Dashboard</a>
    <a href="menu_p1.php" style="background:#2c3a4b;">📑 Menu P1</a>
</div>

<!-- CONTENT -->
<div id="content">

    <div class="page-title">📄 Daftar Laporan P1</div>
    <hr>

    <div class="card-box">

        <div class="report-list">
            <p>1. <a href="instruction.php">Delivery Instruction VS Balance</a></p>

            <p>2. <a href="schedule_p1.php">Delivery Schedule PLANT 1</a></p>

            <p>3. <a href="#">test</a></p>

            <p>4. <a href="#">test</a></p>

            <p>5. <a href="#">test</a></p>

            <p>6. <a href="#">test</a></p>

            <p>7. <a href="#">test</a></p>
        </div>

    </div>

</div>

</body>
</html>

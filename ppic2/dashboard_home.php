<?php
if (session_id() == "") {
    session_start();
}

require_once __DIR__ . "/../config/database_ppic.php";

if ($conn === false) {
    echo "Koneksi database gagal.";
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
    <title>Report Menu</title>

    <style>
        body {
            margin: 0;
            padding: 18px;
            background: #d4d0c8;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            color: #000000;
        }

        .content {
            width: 980px;
            margin: 0 auto;
            box-sizing: border-box;
        }

        .header-box {
            border: 1px solid #808080;
            background: #eeeeee;
            padding: 15px;
            margin-bottom: 14px;
            text-align: center;
        }

        .header-title {
            font-size: 26px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .header-subtitle {
            font-size: 13px;
        }

        .info-box {
            border: 1px solid #808080;
            background: #ffffff;
            padding: 10px;
            margin-bottom: 14px;
            line-height: 22px;
        }

        .report-box {
            border: 1px solid #808080;
            background: #f0f0f0;
            padding: 14px;
            box-sizing: border-box;
        }

        .report-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 12px;
            border-bottom: 1px solid #808080;
            padding-bottom: 8px;
        }

        .report-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .report-item {
            border: 1px solid #808080;
            background: #ffffff;
            padding: 10px;
            min-height: 70px;
            box-sizing: border-box;
        }

        .report-name {
            font-weight: bold;
            margin-bottom: 8px;
            min-height: 28px;
        }

        button,
        a.button {
            display: inline-block;
            text-decoration: none;
            color: #000000;
            background: #d4d0c8;
            border: 2px outset #ffffff;
            padding: 5px 14px;
            font-size: 12px;
            cursor: pointer;
            font-family: Tahoma, Arial, sans-serif;
        }

        button:active,
        a.button:active {
            border: 2px inset #ffffff;
        }

        .status {
            border: 1px solid #808080;
            background: #ffffff;
            padding: 8px;
            margin-top: 12px;
        }

        .footer {
            margin-top: 18px;
            text-align: center;
            color: #333333;
            font-size: 11px;
        }
    </style>
</head>

<body>

<div class="content">

    <div class="header-box">
        <div class="header-title">REPORT MENU</div>
        <div class="header-subtitle">PPIC System Plant 2</div>
    </div>

    <div class="info-box">
        Login sebagai:
        <b><?php echo h($dbUser); ?></b>
        <br>

        Server:
        <b>192.168.0.9</b>
        &nbsp; | &nbsp;

        Database:
        <b>msData</b>
        &nbsp; | &nbsp;

        Status:
        <b>Connected</b>
        <br>

        Login time:
        <b><?php echo h($loginTime); ?></b>
    </div>

    <div class="report-box">
        <div class="report-title">Report</div>

        <div class="report-grid">

            <div class="report-item">
                <div class="report-name">BOM(BILL OF MATERIAL)</div>
                <a class="button" href="bom.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">MPS</div>
                 <a class="button" href="mps.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">LOADING CAPACITY PER TONASE </div>
                <a class="button" href="loading_capacity_tonase.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">MACHINE CAPICITY</div>
				 <a class="button" href="mc_capacity.php" target="_blank">OPEN</a>
                         </div>
			
			<div class="report-item">
                <div class="report-name">MACHINE CAPICITY PLAN 3 MONTH </div>
				 <a class="button" href="capacity_plan.php" target="_blank">OPEN</a>
                         </div>
			
			
			<div class="report-item">
                <div class="report-name">MRP</div>
				<a class="button" href="mrp.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">MOR</div>
               <a class="button" href="mor.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">Montly Injection Report</div>
               <a class="button" href="monthly_injection.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">LOADING CAPACITY YEARLY</div>
                <a class="button" href="loading_capacity_yearly.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">SALES & MATERIAL USE PLAN USD </div>
                <a class="button" href="sales_material_cost_usd.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">SALES & MATERIAL USE ACTUAL USD</div>
                <a class="button" href="actual_material_cost_usd.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">SALES & MATERIAL USE PLAN IDR</div>
                <a class="button" href="sales_material_cost_idr.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">SALES & MATERIAL USE ACTUAL IDR</div>
                <a class="button" href="actual_material_cost_idr.php" target="_blank">OPEN</a>
            </div>
			
			<div class="report-item">
                <div class="report-name">LOGICAL STOCK</div>
                <a class="button" href="logical.php" target="_blank">OPEN</a>
            </div>
			
			<div class="report-item">
                <div class="report-name">STOK PLAN VS ACTUAL</div>
                <a class="button" href="report_stok_actual.php" target="_blank">OPEN</a>
            </div>
			
			<div class="report-item">
                <div class="report-name">DEAD STOCK</div>
                <a class="button" href="dead_stok.php" target="_blank">OPEN</a>
            </div>
			
			<div class="report-item">
                <div class="report-name">STOK PLAN VS ACTUAL PART COMMON </div>
                <a class="button" href="part_common.php" target="_blank">OPEN</a>
            </div>
			<div class="report-item">
                <div class="report-name">MC RUN </div>
                <a class="button" href="report_mc_run.php" target="_blank">OPEN</a>
            </div>


            

        </div>
    </div>

    <div class="status">
        Status koneksi: <b>Connected</b>
    </div>

    <div class="footer">
        P.T. IMC TEKNO INDONESIA - Ordering System Plant 2
    </div>

</div>

<script>
function notReady(reportName) {
    alert("Report belum dibuat: " + reportName);
}
</script>

</body>
</html>
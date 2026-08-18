<?php
if (session_id() == "") {
    session_start();
}

require_once __DIR__ . "/../config/database_ordering.php";

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
        <div class="header-subtitle">Ordering System Plant 2</div>
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
                <div class="report-name">GRAFIK SALES GABUNGAN P1 DAN P2 </div>
                <a class="button" href="sales_grafik_gab.php" target="_blank">OPEN</a>
            </div>
			
			<div class="report-item">
                <div class="report-name">GRAFIK MATERIAL CONSUME VS SALES P1 DAN P2 </div>
                <a class="button" href="hpp_sales.php" target="_blank">OPEN</a>
            </div>
			
			 <div class="report-item">
                <div class="report-name">COMMON PART</div>
                <a class="button" href="part_common.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">Customer List</div>
                <a class="button" href="customer_list_report.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">Sales Price List</div>
                 <a class="button" href="sales_price_list.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">Sales Price List History</div>
                <a class="button" href="sales_price_history_report.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">Sales Forecast USD</div>
                <a class="button" href="sales_forecast_report.php">OPEN</a>
            </div>
			
			<div class="report-item">
                <div class="report-name">Sales Forecast IDR</div>
                <a class="button" href="sales_forecast_report_no_usd.php">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">Outstanding Customer Order</div>
               <a class="button" href="outstanding_customer_order_report.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">Outstanding Customer Order Summary</div>
               <a class="button" href="outstanding_customer_order_summary_report.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">Outstanding Customer Order Detail</div>
                <a class="button" href="po_delivery_balance_detail_report.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">Delivery Schedule</div>
                <a class="button" href="delivery_schedule_report.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">Delivery History</div>
                <a class="button" href="delivery_history_report.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">Delivery History Summary</div>
                <a class="button" href="delivery_history_summary_report.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">GRAFIK SHORTAGE</div>
                <a class="button" href="delivery_shortage_report.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">Delivery Balance Amount USD</div>
               <a class="button" href="delivery_balance_amount_report.php" target="_blank">OPEN</a>
            </div>
			
			<div class="report-item">
                <div class="report-name">Delivery Balance Amount IDR P1 DAN P2</div>
               <a class="button" href="delivery_balance_amount_report_idr.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">Delivery Information</div>
                <a class="button" href="manual_order.php">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">Delivery Analysis</div>
                <button type="button" onclick="notReady('Delivery Analysis')">OPEN</button>
            </div>

            <div class="report-item">
                <div class="report-name">Daily Invoice List</div>
              <a class="button" href="daily_invoice_list_report.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">Monthly Invoice List</div>
                <a class="button" href="monthly_invoice_list_report.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">Monthly Invoice List (price by po)</div>
                <button type="button" onclick="notReady('Monthly Invoice List price by po')">OPEN</button>
            </div>

            <div class="report-item">
                <div class="report-name">Delivery Performance</div>
                <a class="button" href="delivery_performance_report.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">Delivery Summary 3 Month </div>
                <a class="button" href="delivery_summary_3month_report.php" target="_blank">OPEN</a>
            </div>

            <div class="report-item">
                <div class="report-name">Delivery Summary 6 month</div>
                <a class="button" href="delivery_summary_6month_report.php" target="_blank">OPEN</a>
            </div>
			
			
			<div class="report-item">
                <div class="report-name">Delivery Summary 1 YEAR USD</div>
                <a class="button" href="delivery_summary_12month.php" target="_blank">OPEN</a>
            </div>
			<div class="report-item">
                <div class="report-name">Delivery Summary 1 YEAR IDR</div>
                <a class="button" href="DeliverySum12Month_idr.php" target="_blank">OPEN</a>
            </div>
			
			<div class="report-item">
                <div class="report-name">LOGICAL STOCK</div>
                <a class="button" href="delivery_schedule_prod_report.php" target="_blank">OPEN</a>
            </div>
			
			<div class="report-item">
                <div class="report-name">TOTAL SHOOT DELIVERY</div>
                <a class="button" href="shoot.php" target="_blank">OPEN</a>
            </div>
			
			<div class="report-item">
                <div class="report-name">TOTAL SHOOT PRODUCTION</div>
                <a class="button" href="shoot_prod.php" target="_blank">OPEN</a>
            </div>
			
			<div class="report-item">
                <div class="report-name">SALES INTERNAL VS VENDOR</div>
                <a class="button" href="sales.php" target="_blank">OPEN</a>
            </div>
			
			<div class="report-item">
                <div class="report-name">GRAFIK SALES INTERNAL VS VENDOR</div>
                <a class="button" href="sales_grafik.php" target="_blank">OPEN</a>
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
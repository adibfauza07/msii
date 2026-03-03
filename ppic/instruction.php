<?php

require_once "../config/database.php"; // koneksi utama
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>DI PART Monitoring PLANT 1</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <script src="../assets/jquery.min.js"></script>
    <script src="../assets/bootstrap.min.js"></script>

    <!-- Select2 -->
    <link rel="stylesheet" href="../assets/select2.min.css">
    <script src="../assets/select2.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { margin:20px; font-size:13px; }
        .danger-row { background:#f8d7da !important; }
        .summary-box {
            margin-top:20px;
            padding:15px;
            background:#e9ecef;
            border-radius:5px;
        }
        .pagination-container { margin-top:10px; }
        .page-link-custom {
            cursor:pointer;
            padding:4px 10px;
            border:1px solid #ccc;
            margin-right:3px;
        }
        .page-link-active {
            background:#007bff;
            color:#fff;
        }
    </style>

</head>
<body>
<body>

<!-- TOMBOL BACK -->
<a href="dashboard_ppic.php" class="btn btn-secondary" style="margin-bottom:15px;">
    ← Kembali ke Menu P1
</a>

<h3><b>Delivery Instruction - Part Monitoring PLANT 1</b></h3>
<hr>


<!-- ================== FORM UTAMA ====================== -->
<?php
// Default tanggal: 1 bulan ini & besok
$default_start = date("Y-m-01");
$default_end   = date("Y-m-d", strtotime("+1 day"));
?>

<form method="POST">

<div class="row">

    <!-- CUSTOMER LOOKUP -->
    <div class="col-md-3">
        <label><b>Customer</b></label>
        <select name="cust_code" class="form-control select2" required>
            <option value="">-- pilih customer --</option>
            <option value="ALL" <?php if(isset($_POST['cust_code']) && $_POST['cust_code']=="ALL") echo "selected"; ?>>ALL CUSTOMER</option>

            <?php
            $sql_cust = "SELECT CUST_CODE, CUST_COMP FROM CUST ORDER BY CUST_CODE";
            $cust_stmt = sqlsrv_query($conn, $sql_cust);
            while ($c = sqlsrv_fetch_array($cust_stmt, SQLSRV_FETCH_ASSOC)) {
                $sel = (isset($_POST['cust_code']) && $_POST['cust_code'] == $c['CUST_CODE']) ? "selected" : "";
                echo "<option value='".$c['CUST_CODE']."' $sel>".$c['CUST_CODE']." - ".$c['CUST_COMP']."</option>";
            }
            ?>
        </select>
    </div>

  <!-- START DATE -->
<div class="col-md-3">
    <label><b>Start Date</b></label>
    <input type="date" name="start_date" class="form-control" required
           value="<?php echo isset($_POST['start_date']) ? $_POST['start_date'] : $default_start; ?>">
</div>

<!-- END DATE -->
<div class="col-md-3">
    <label><b>End Date</b></label>
    <input type="date" name="end_date" class="form-control" required
           value="<?php echo isset($_POST['end_date']) ? $_POST['end_date'] : $default_end; ?>">
</div>


    <div class="col-md-2">
        <br>
        <button class="btn btn-primary btn-block" style="margin-top:6px;">Tampilkan</button>
    </div>

</div>

</form>

<script>
$(document).ready(function(){
    $(".select2").select2();
});
</script>

<hr>

<!-- ================== START PROCESS ====================== -->

<?php
$chart_labels = [];
$chart_daily = [];
$chart_balance = [];

if ($_SERVER['REQUEST_METHOD']=="POST") {

    $cust  = $_POST['cust_code'];
    $start = $_POST['start_date'];
    $end   = $_POST['end_date'];

    echo "<h5>Hasil: <b>$cust</b> | Periode <b>$start</b> s/d <b>$end</b></h5><br>";

    $sql = "{CALL SP_DI_PART_new(?, ?, ?)}";
    $params = array($cust, $start, $end);

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        echo "<pre>";
        print_r(sqlsrv_errors());
        echo "</pre>";
        exit;
    }

    // summary
    $rows = [];
    $total_bal_qty = 0;
    $total_daily = 0;
    $total_balance = 0;

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;

        $total_bal_qty += (float)$r['BAL_QTY'];
        $total_daily   += (float)$r['DAILY_SCH'];
        $total_balance += (float)$r['BALANCE'];
    }

    // chart (top 10)
    $maxChartItems = 10;
    $counter = 0;
    foreach ($rows as $r) {
        $chart_labels[]  = $r['PART_NUM'];
        $chart_daily[]   = (float)$r['DAILY_SCH'];
        $chart_balance[] = (float)$r['BALANCE'];
        $counter++;
        if ($counter >= $maxChartItems) break;
    }
?>

<!-- ================== FILTERS ====================== -->

<div class="row">
    <div class="col-md-3">
        <label>Filter teks</label>
        <input type="text" id="filterText" class="form-control" placeholder="ketik untuk filter...">
    </div>
    <div class="col-md-2">
        <label>Min Balance</label>
        <input type="number" id="minBalance" class="form-control">
    </div>
    <div class="col-md-2">
        <label>Max Balance</label>
        <input type="number" id="maxBalance" class="form-control">
    </div>
    <div class="col-md-2">
        <label>&nbsp;</label><br>
        <input type="checkbox" id="onlyCritical"> Hanya BALANCE < DAILY
    </div>
    <div class="col-md-3">
        <label>&nbsp;</label><br>
        <a href="export_excel_xlsx.php?cust=<?php echo urlencode($cust); ?>&start=<?php echo urlencode($start); ?>&end=<?php echo urlencode($end); ?>" class="btn btn-success">Export Excel</a>
        <a href="export_pdf.php?cust=<?php echo urlencode($cust); ?>&start=<?php echo urlencode($start); ?>&end=<?php echo urlencode($end); ?>" class="btn btn-danger">Export PDF</a>
    </div>
</div>

<br>

<!-- ================== TABLE ====================== -->

<div class="table-responsive">
<table class="table table-bordered table-striped" id="dataTable">
    <thead class="thead-dark">
        <tr>
            <th>No</th>
            <th>PART_NUM</th>
            <th>PART_NO</th>
            <th>PART_NAME</th>
            <th>BAL_PO</th>
            <th>DAILY_SCH</th>
            <th>STOK ACTUAL</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $no = 1;
        foreach ($rows as $r) {
            $danger = ($r['BALANCE'] < $r['DAILY_SCH']) ? "danger-row" : "";
            echo "<tr class='$danger'>
                    <td>".$no."</td>
                    <td>".$r['PART_NUM']."</td>
                    <td>".$r['PART_NO']."</td>
                    <td>".$r['PART_NAME']."</td>
                    <td>".number_format($r['BAL_QTY'])."</td>
                    <td>".number_format($r['DAILY_SCH'])."</td>
                    <td>".number_format($r['BALANCE'])."</td>
                  </tr>";
            $no++;
        }
        ?>
    </tbody>
</table>
</div>

<!-- ================== PAGINATION ====================== -->

<div class="pagination-container">
    <span>Rows per page: </span>
    <select id="rowsPerPage">
        <option value="10">10</option>
        <option value="25" selected>25</option>
        <option value="50">50</option>
        <option value="100">100</option>
    </select>
    <div id="pagination" style="display:inline-block; margin-left:10px;"></div>
</div>

<!-- ================== SUMMARY ====================== -->

<div class="summary-box">
    <h5><b>SUMMARY TOTAL</b></h5>
    <table class="table table-bordered">
        <tr>
            <th>Total BAL_QTY</th>
            <th>Total DAILY_SCH</th>
            <th>Total BALANCE</th>
        </tr>
        <tr>
            <td><?php echo number_format($total_bal_qty); ?></td>
            <td><?php echo number_format($total_daily); ?></td>
            <td><?php echo number_format($total_balance); ?></td>
        </tr>
    </table>
</div>

<!-- ================== CHART ====================== -->

<br>
<h5><b>Chart BALANCE vs DAILY_SCH (Top <?php echo $maxChartItems ?>)</b></h5>
<canvas id="diChart" height="100"></canvas>

<script>
// ------- CHART -------
var ctx = document.getElementById('diChart').getContext('2d');
var diChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [
            {
                label: 'DAILY_SCH',
                data: <?php echo json_encode($chart_daily); ?>
            },
            {
                label: 'BALANCE',
                data: <?php echo json_encode($chart_balance); ?>
            }
        ]
    },
    options: {
        responsive:true,
        scales:{
            y:{ beginAtZero:true }
        }
    }
});

// ------- FILTER -------
function applyFilter() {
    var text = $("#filterText").val().toLowerCase();
    var minBal = $("#minBalance").val();
    var maxBal = $("#maxBalance").val();
    var onlyCritical = $("#onlyCritical").is(":checked");

    $("#dataTable tbody tr").each(function(){
        var row = $(this);
        var rowText = row.text().toLowerCase();

        var daily = parseFloat(row.find("td:eq(5)").text().replace(/,/g,'')) || 0;
        var bal   = parseFloat(row.find("td:eq(6)").text().replace(/,/g,'')) || 0;

        var show = true;

        if (text && rowText.indexOf(text) === -1) show = false;
        if (minBal && bal < minBal) show = false;
        if (maxBal && bal > maxBal) show = false;
        if (onlyCritical && !(bal < daily)) show = false;

        row.toggle(show);
    });

    setupPagination();
}

$("#filterText, #minBalance, #maxBalance").on("keyup change", function(){ applyFilter(); });
$("#onlyCritical").on("change", function(){ applyFilter(); });

// ------- PAGINATION -------
function setupPagination() {
    var rowsPerPage = parseInt($("#rowsPerPage").val());
    var rows = $("#dataTable tbody tr:visible");
    var totalRows = rows.length;
    var totalPages = Math.ceil(totalRows / rowsPerPage);

    $("#pagination").empty();

    if (totalPages <= 1) {
        rows.show();
        return;
    }

    for (let i = 1; i <= totalPages; i++) {
        let btn = $("<span class='page-link-custom'>" + i + "</span>");
        btn.on("click", function(){
            $(".page-link-custom").removeClass("page-link-active");
            $(this).addClass("page-link-active");

            var start = (i - 1) * rowsPerPage;
            var end   = start + rowsPerPage;

            rows.hide();
            rows.slice(start, end).show();
        });
        $("#pagination").append(btn);
    }

    $("#pagination .page-link-custom:first").click();
}

$("#rowsPerPage").on("change", setupPagination);

$(document).ready(function(){
    applyFilter();
});
</script>

<?php } ?>

</body>
</html>

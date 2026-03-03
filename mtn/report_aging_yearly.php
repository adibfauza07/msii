<?php
require "../config/database.php";

// =============================
// GET PARAMETER (boleh kosong)
// =============================
$from  = isset($_GET['from'])  ? $_GET['from']  : "";
$to    = isset($_GET['to'])    ? $_GET['to']    : "";
$plant = isset($_GET['plant']) ? $_GET['plant'] : "";

// cek apakah user sudah klik Tampilkan
$hasFilter = ($from !== "" && $to !== "" && $plant !== "");

// siapkan variabel untuk tabel / chart
$months      = [];
$tonase      = [];
$matrix      = [];
$monthTotal  = [];

// =============================
// LOAD DATA HANYA JIKA PARAMETER LENGKAP
// =============================
if ($hasFilter) {
    $sql    = "{CALL sp_MTN_GRAPH_AGING_TIME_new(?, ?, ?)}";
    $params = [$from, $to, $plant];
    $stmt   = sqlsrv_query($conn, $sql, $params, ["Scrollable" => "buffered"]);

    if (!$stmt) {
        die(print_r(sqlsrv_errors(), true));
    }

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

        $bln = $r["bulan"];
        $t   = $r["TONAGE"];
        $val = (float)$r["Total Time"];

        $months[$bln] = $bln;
        $tonase[$t]   = $t;

        $matrix[$t][$bln] = $val;

        if (!isset($monthTotal[$bln])) $monthTotal[$bln] = 0;
        $monthTotal[$bln] += $val;
    }

    ksort($months);
    ksort($tonase);
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Aging Time Report - Yearly</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<link rel="stylesheet" href="../assets/bootstrap.min.css">

<style>
body{
    font-family: Arial, Helvetica, sans-serif;
    padding:20px;
    background:white;
}

h2{
    text-align:center;
    margin-bottom:20px;
}

/* area parameter */
.param-row{
    margin-bottom:15px;
}

/* PRINT MODE LANDSCAPE & 1 HALAMAN */
@media print {
    @page {
        size: landscape;
        margin: 10mm;
    }
    #btnPrint, #filterForm { display:none; }

    #report-header, .table-container, .chart-container, table {
        page-break-inside: avoid !important;
    }
}

/* TABLE STYLE */
table{
    border-collapse: collapse;
    width:100%;
    margin-top:20px;
}

th, td{
    border:1px solid #000;
    padding:6px;
    font-size:12px;
    text-align:center;
}

th{
    background:#e6e6e6;
}

.row-title{
    text-align:left;
    font-weight:bold;
}

.print-info td{
    border:none;
    padding:4px 0;
    font-size:13px;
}

.chart-container{
    width:100%;
    height:350px;
    margin-top:35px;
}
</style>
</head>
<body>
<!-- BACK BUTTON -->
<a href="dashboard_mtn.php" class="btn btn-secondary" style="margin-bottom:15px;">
    ← Kembali ke Menu 
</a>
<button id="btnPrint" class="btn btn-success btn-sm mb-3" onclick="window.print()">🖨 Print</button>

<h2>AGING TIME REPORT (YEARLY)</h2>

<!-- ====================== FORM PARAMETER ====================== -->
<form id="filterForm" class="mb-3" method="get" action="">

  <div class="row param-row">
    <div class="col-md-3">
        <label><b>Dari Tanggal</b></label>
        <input type="date" name="from" class="form-control"
               value="<?= htmlspecialchars($from) ?>">
    </div>
    <div class="col-md-3">
        <label><b>Sampai Tanggal</b></label>
        <input type="date" name="to" class="form-control"
               value="<?= htmlspecialchars($to) ?>">
    </div>
    <div class="col-md-2">
        <label><b>Plant</b></label>
        <input type="text" name="plant" class="form-control"
               placeholder="wajib isi"
               value="<?= htmlspecialchars($plant) ?>">
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary btn-block">Tampilkan</button>
    </div>
  </div>

</form>

<!-- ====================== HEADER INFO ====================== -->
<div id="report-header">
    <table class="print-info">
        <tr>
            <td width="120"><b>Periode</b></td>
            <td>:
                <?php
                if ($hasFilter) {
                    echo htmlspecialchars($from) . " s/d " . htmlspecialchars($to);
                } else {
                    echo "- s/d -";
                }
                ?>
            </td>

            <td width="120"><b>Plant</b></td>
            <td>: <?= $plant !== "" ? htmlspecialchars($plant) : "%" ?></td>
        </tr>
        <tr>
            <td><b>Total Bulan</b></td>
            <td>: <?= $hasFilter ? count($months) : 0 ?> Bulan</td>
            <td><b>Total Tonase</b></td>
            <td>: <?= $hasFilter ? count($tonase) : 0 ?> Tonase</td>
        </tr>
    </table>
</div>

<?php if (!$hasFilter): ?>
    <p><i>Silakan pilih periode dan plant, kemudian klik <b>Tampilkan</b>.</i></p>
<?php else: ?>

<!-- ====================== TABEL PIVOT ====================== -->
<div class="table-container">
<table>
<thead>
<tr>
    <th width="80">TONASE</th>
    <?php foreach($months as $m): ?>
        <th><?= $m ?></th>
    <?php endforeach; ?>
    <th width="80">TOTAL</th>
</tr>
</thead>
<tbody>

<?php foreach($tonase as $t): ?>
<tr>
    <td class="row-title"><?= $t ?></td>
    <?php
    $rowTotal = 0;
    foreach($months as $m):
        $v = isset($matrix[$t][$m]) ? $matrix[$t][$m] : 0;
        $rowTotal += $v;
        echo "<td>$v</td>";
    endforeach;
    ?>
    <td><b><?= $rowTotal ?></b></td>
</tr>
<?php endforeach; ?>

<tr>
    <th>TOTAL</th>
    <?php foreach($months as $m): ?>
        <th><?= $monthTotal[$m] ?></th>
    <?php endforeach; ?>
    <th><?= array_sum($monthTotal) ?></th>
</tr>

</tbody>
</table>
</div>

<!-- ====================== GRAFIK BAR ====================== -->
<div class="chart-container">
    <canvas id="agingChart"></canvas>
</div>

<script>
const labels   = <?= json_encode(array_values($months)) ?>;
const dataTot  = <?= json_encode(array_values($monthTotal)) ?>;

const ctx = document.getElementById('agingChart');

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Total Aging Time Per Bulan',
            data: dataTot,
            backgroundColor: 'rgba(54, 162, 235, 0.7)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio:false,
        scales: {
            y: {
                beginAtZero: true,
                title:{
                    display:true,
                    text:"Total Time"
                }
            },
            x:{
                title:{
                    display:true,
                    text:"Bulan"
                }
            }
        }
    }
});
</script>

<?php endif; ?>

</body>
</html>

<?php
require "../config/database.php";

// ----------------------
// Ambil parameter filter
// ----------------------
$from  = isset($_GET['from'])  ? $_GET['from']  : "";
$to    = isset($_GET['to'])    ? $_GET['to']    : "";
$mac   = isset($_GET['mac'])   ? trim($_GET['mac'])   : "";
$plant = isset($_GET['plant']) ? trim($_GET['plant']) : "";

// untuk tampilan header
$from_disp  = $from;
$to_disp    = $to;
$mac_disp   = ($mac === "" ? "%" : $mac);
$plant_disp = ($plant === "" ? "-" : $plant);

// konversi tanggal ke format SQL (Y-m-d) bila diisi
$from_sql = $from ? date("Y-m-d", strtotime($from)) : null;
$to_sql   = $to   ? date("Y-m-d", strtotime($to))   : null;

// MAC untuk parameter SP (varchar(6) + LIKE)
$mac_param = ($mac === "" ? "%" : $mac);

// PLANT untuk parameter SP (INT, wajib)
$plant_param = ($plant === "" ? 0 : (int)$plant);

// ----------------------
// Ambil data dari SP
// ----------------------
$data = [];
if ($from_sql && $to_sql) {
    $sql    = "{CALL SP_MTN_HISTORY_PERBAIKAN_new(?, ?, ?, ?)}";
    $params = array($from_sql, $to_sql, $mac_param, $plant_param);

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        // untuk debugging (boleh di-comment kalau sudah beres)
        echo "<pre>";
        print_r(sqlsrv_errors());
        echo "</pre>";
    } else {
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Laporan Perbaikan Mesin</title>
    <link rel="stylesheet" href="../assets/bootstrap.min.css">

    <style>
        body{
            font-family: Arial, Helvetica, sans-serif;
            padding:20px;
            background:#f7f7f7;
        }
        h2{
            text-align:center;
            margin-bottom:20px;
        }
        .filter-row{
            margin-bottom:15px;
        }
        table{
            width:100%;
            border-collapse:collapse;
            margin-top:15px;
        }
        th, td{
            border:1px solid #000;
            padding:5px;
            font-size:12px;
        }
        thead th{
            background:#eee;
            text-align:center;
        }
        .info-table td{
            border:none;
            padding:2px 4px;
            font-size:13px;
        }
        @media print{
            @page{
                size: landscape;
                margin:10mm;
            }
            #filterForm, #btnPrint{
                display:none;
            }
            body{ padding:0; background:#fff; }
        }
        #btnPrint{
            margin-bottom:10px;
        }
    </style>
</head>
<body>
<!-- BACK BUTTON -->
<a href="dashboard_mtn.php" class="btn btn-secondary" style="margin-bottom:15px;">
    ← Kembali ke Menu 
</a>

<?php 
    // Ambil parameter yang ada di URL (from, to, mac, plant)
    $params = $_SERVER['QUERY_STRING']; 
?>

<div class="float-end" style="margin-bottom:10px;">
    <a href="report_perbaikan_pdf.php?<?= $params ?>" target="_blank" class="btn btn-danger btn-sm">
        📄 Export PDF
    </a>
    
    <a href="report_perbaikan_excel.php?<?= $params ?>" target="_blank" class="btn btn-success btn-sm">
        📊 Export Excel
    </a>

    <button class="btn btn-secondary btn-sm" onclick="window.print()">🖨 Print</button>
</div>

<div style="clear:both"></div>

<h2>LAPORAN PERBAIKAN MESIN</h2>
<!-- FORM FILTER -->
<form id="filterForm" method="get" action="" class="mb-3">
    <div class="row filter-row">
        <div class="col-md-3">
            <label>Dari Tanggal</label>
            <input type="date" name="from" class="form-control"
                   value="<?= htmlspecialchars($from_disp) ?>">
        </div>
        <div class="col-md-3">
            <label>Sampai Tanggal</label>
            <input type="date" name="to" class="form-control"
                   value="<?= htmlspecialchars($to_disp) ?>">
        </div>
        <div class="col-md-2">
            <label>MAC</label>
            <input type="text" name="mac" class="form-control"
                   value="<?= htmlspecialchars($mac === "" ? "" : $mac) ?>"
                   placeholder="% = semua">
        </div>
        <div class="col-md-2">
            <label>Plant</label>
            <input type="number" name="plant" class="form-control"
                   value="<?= htmlspecialchars($plant === "" ? "" : $plant) ?>"
                   placeholder="wajib isi">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
        </div>
    </div>
</form>

<!-- INFO PERIODE -->
<table class="info-table">
    <tr>
        <td width="120"><b>Periode</b></td>
        <td>: 
            <?= $from_disp ? date("d-M-Y", strtotime($from_disp)) : "-" ?>
            s/d 
            <?= $to_disp ? date("d-M-Y", strtotime($to_disp)) : "-" ?>
        </td>
    </tr>
    <tr>
        <td><b>MAC</b></td>
        <td>: <?= htmlspecialchars($mac_disp) ?></td>
    </tr>
    <tr>
        <td><b>Plant</b></td>
        <td>: <?= htmlspecialchars($plant_disp) ?></td>
    </tr>
</table>

<hr>

<table>
    <thead>
    <tr>
        <th width="60">MAC</th>
        <th width="50">Plant</th>
        <th width="60">Tonage</th>
        <th width="90">Tanggal</th>
        <th width="220">Description</th>
        <th width="220">Service</th>
        <th>Kerusakan</th>
    </tr>
    </thead>
    <tbody>
    
    <?php if ($from_sql && $to_sql && empty($data)): ?>
        <tr>
            <td colspan="7" style="text-align:center;"><i>Tidak ada data.</i></td>
        </tr>
    <?php endif; ?>

    <?php foreach($data as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['MAC']) ?></td>
            <td style="text-align:center;"><?= htmlspecialchars($r['PLANT']) ?></td>
            <td style="text-align:center;"><?= htmlspecialchars($r['TONAGE']) ?></td>
            <td>
                <?= $r['DATE'] instanceof DateTime 
                        ? $r['DATE']->format("d-M-Y") 
                        : "" ?>
            </td>
            <td><?= htmlspecialchars($r['DESCRIPTION']) ?></td>
            <td><?= htmlspecialchars($r['SERVICE']) ?></td>
            <td><?= htmlspecialchars($r['KERUSAKAN']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>

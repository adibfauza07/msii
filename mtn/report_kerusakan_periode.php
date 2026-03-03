<?php
require "../config/database.php";

// ----------------------
// Ambil parameter filter
// ----------------------
$from  = isset($_GET['from'])  ? $_GET['from']  : "";
$to    = isset($_GET['to'])    ? $_GET['to']    : "";
$mac   = isset($_GET['mac'])   ? trim($_GET['mac'])   : "";
$plant = isset($_GET['plant']) ? trim($_GET['plant']) : "";

// nilai untuk ditampilkan di header (asli, format input)
$from_disp  = $from;
$to_disp    = $to;
$mac_disp   = ($mac === "" ? "%" : $mac);
$plant_disp = ($plant === "" ? "%" : $plant);

// Konversi tanggal ke format SQL (Y-m-d) bila tidak kosong
$from_sql = $from ? date("Y-m-d", strtotime($from)) : null;
$to_sql   = $to   ? date("Y-m-d", strtotime($to))   : null;

// MAC untuk parameter SP → LIKE @mac
// jika kosong → '%' (semua mac)
$mac_param = ($mac === "" ? "%" : $mac);

// PLANT untuk parameter SP (int, wajib)
// kalau kosong → 0 (kalau mau wajib isi plant, bisa paksa required di form)
$plant_param = ($plant === "" ? 0 : (int)$plant);

// Ambil data hanya kalau tanggal diisi
$rows = [];
if ($from_sql && $to_sql) {
    $sql = "{CALL SP_MTN_HISTORY_KERUSAKAN_KATEGORI_new(?, ?, ?, ?)}";
    $params = array($from_sql, $to_sql, $mac_param, $plant_param);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $err = sqlsrv_errors();
        // Untuk debugging, sementara tampilkan error apa adanya
        // (kalau sudah OK bisa diganti pesan lebih halus)
        echo "<pre>";
        print_r($err);
        echo "</pre>";
    } else {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $r;
        }
    }
}

// ----------------------
// Grouping per KATEGORI
// ----------------------
$grouped = [];
if (!empty($rows)) {
    foreach ($rows as $r) {
        $kat = $r['KATEGORI'];      // nama kelompok (ex: HOPPER DRYER / MESIN INJECTION)
        if (!isset($grouped[$kat])) {
            $grouped[$kat] = [
                'items' => [],
                'total' => 0,
            ];
        }
        $grouped[$kat]['items'][] = $r;
        $grouped[$kat]['total']  += (int)$r['KERUSAKAN'];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Laporan Kerusakan Per Periode</title>
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
        .table-header th{
            background:#eee;
            text-align:center;
            border:1px solid #000;
            padding:5px;
            font-size:12px;
        }
        table.report-table{
            width:100%;
            border-collapse: collapse;
            margin-bottom:25px;
        }
        table.report-table td{
            border:1px solid #000;
            padding:4px;
            font-size:12px;
        }
        .kategori-title{
            font-weight:bold;
            margin-top:15px;
            margin-bottom:5px;
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
<button id="btnPrint" class="btn btn-success btn-sm" onclick="window.print()">🖨 Cetak</button>

<h2>LAPORAN KERUSAKAN PER PERIODE</h2>

<!-- FORM FILTER -->
<form id="filterForm" class="mb-3" method="get" action="">
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
            <button type="submit" class="btn btn-primary btn-block">Tampilkan</button>
        </div>
    </div>
</form>

<!-- INFO PERIODE -->
<table class="info-table">
    <tr>
        <td width="120"><b>Periode</b></td>
        <td>: <?= $from_disp ? date("d-M-Y", strtotime($from_disp)) : "-" ?>
           s/d <?= $to_disp ? date("d-M-Y", strtotime($to_disp)) : "-" ?></td>
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

<?php if ($from_sql && $to_sql && empty($rows)): ?>
    <p><i>Tidak ada data untuk periode / filter ini.</i></p>
<?php endif; ?>

<?php
// Tampilkan per KATEGORI
foreach ($grouped as $kat => $data):
?>
    <div class="kategori-title"><?= htmlspecialchars($kat) ?></div>

    <table class="report-table">
        <thead>
        <tr class="table-header">
            <th>Detail Kerusakan</th>
            <th width="80">Total</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($data['items'] as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['DETAIL']) ?></td>
                <td style="text-align:center;"><?= (int)$item['KERUSAKAN'] ?></td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <td style="text-align:right; font-weight:bold;">Total</td>
            <td style="text-align:center; font-weight:bold;"><?= (int)$data['total'] ?></td>
        </tr>
        </tbody>
    </table>
<?php endforeach; ?>

</body>
</html>

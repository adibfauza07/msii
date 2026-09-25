<?php
require "../config/database.php";

$from  = isset($_GET['from'])  ? $_GET['from']  : "";
$to    = isset($_GET['to'])    ? $_GET['to']    : "";
$mac   = isset($_GET['mac'])   ? trim($_GET['mac'])   : "";
$plant = isset($_GET['plant']) ? trim($_GET['plant']) : "";

$from_disp  = $from;
$to_disp    = $to;
$mac_disp   = ($mac === "" ? "%" : $mac);
$plant_disp = ($plant === "" ? "-" : $plant);

$from_sql = $from ? date("Y-m-d", strtotime($from)) : null;
$to_sql   = $to   ? date("Y-m-d", strtotime($to))   : null;
$mac_param = ($mac === "" ? "%" : $mac);
$plant_param = ($plant === "" ? 0 : (int)$plant);

$data = [];
$error_msg = "";
if ($from_sql && $to_sql) {
    $sql    = "{CALL SP_MTN_HISTORY_PERBAIKAN_new(?, ?, ?, ?)}";
    $params = [$from_sql, $to_sql, $mac_param, $plant_param];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $error_msg = "Terjadi kesalahan saat mengambil data.";
    } else {
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Laporan Perbaikan Mesin</title>
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
        body { background: #f4f6f9; padding: 20px; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .page-title { color: #333; font-weight: 600; text-align: center; margin-bottom: 25px; }
        .card-custom { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: none; margin-bottom: 20px; }
        .card-header-custom { background: #343a40; color: #fff; border-radius: 8px 8px 0 0; padding: 12px 20px; font-weight: bold; }
        .table-custom th { background: #f8f9fa; text-align: center; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6; }
        .table-custom td { vertical-align: middle; }
        
        /* Pengaturan Cetak CSS */
        @media print {
            @page { size: landscape; margin: 15mm; }
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .card-custom { box-shadow: none; border: none; }
            .doc-code { display: block !important; position: fixed; bottom: 0; left: 0; font-size: 11px; font-weight: bold; }
        }
        .doc-code { display: none; }
    </style>
</head>
<body>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <a href="dashboard_mtn.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
    
    <?php $params_url = $_SERVER['QUERY_STRING']; ?>
    <div class="btn-group">
        <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        <?php if ($from_sql && $to_sql && empty($error_msg)): ?>
            <a href="report_perbaikan_pdf.php?<?= $params_url ?>" target="_blank" class="btn btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
            <a href="report_perbaikan_excel.php?<?= $params_url ?>" target="_blank" class="btn btn-success"><i class="fas fa-file-excel"></i> Excel</a>
        <?php endif; ?>
    </div>
</div>

<h2 class="page-title"><i class="fas fa-tools text-primary"></i> LAPORAN PERBAIKAN MESIN</h2>

<!-- FORM FILTER -->
<div class="card card-custom no-print">
    <div class="card-body">
        <form method="get" action="">
            <div class="row align-items-end">
                <div class="col-md-3 mb-2">
                    <label class="font-weight-bold text-muted small">Dari Tanggal</label>
                    <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from_disp) ?>" required>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="font-weight-bold text-muted small">Sampai Tanggal</label>
                    <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to_disp) ?>" required>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="font-weight-bold text-muted small">MAC</label>
                    <input type="text" name="mac" class="form-control" value="<?= htmlspecialchars($mac === "" ? "" : $mac) ?>" placeholder="Kosong = Semua">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="font-weight-bold text-muted small">Plant</label>
                    <input type="number" name="plant" class="form-control" value="<?= htmlspecialchars($plant === "" ? "" : $plant) ?>" placeholder="Misal: 2">
                </div>
                <div class="col-md-2 mb-2">
                    <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search"></i> Tampilkan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($error_msg): ?>
    <div class="alert alert-danger no-print"><i class="fas fa-exclamation-triangle"></i> <?= $error_msg ?></div>
<?php endif; ?>

<!-- HASIL LAPORAN -->
<?php if ($from_sql && $to_sql): ?>
<div class="card card-custom">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <span><i class="fas fa-table"></i> Data Perbaikan</span>
        <span class="badge badge-light text-dark">Periode: <?= date("d-M-Y", strtotime($from_disp)) ?> s/d <?= date("d-M-Y", strtotime($to_disp)) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-custom table-bordered w-100" style="margin-bottom:0;">
                <thead>
                    <tr>
                        <th width="80">MAC</th>
                        <th width="60">Plant</th>
                        <th width="80">Tonage</th>
                        <th width="100">Tanggal</th>
                        <th>Description</th>
                        <th>Service</th>
                        <th width="200">Kerusakan</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($data)): ?>
                    <tr><td colspan="7" class="text-center p-4 text-muted"><i class="fas fa-folder-open fa-2x mb-2"></i><br>Tidak ada data perbaikan</td></tr>
                <?php else: ?>
                    <?php foreach($data as $r): ?>
                    <tr>
                        <td class="text-center font-weight-bold"><?= htmlspecialchars($r['MAC']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($r['PLANT']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($r['TONAGE']) ?></td>
                        <td class="text-center"><?= $r['DATE'] instanceof DateTime ? $r['DATE']->format("d-M-Y") : "" ?></td>
                        <td><?= htmlspecialchars($r['DESCRIPTION']) ?></td>
                        <td><?= htmlspecialchars($r['SERVICE']) ?></td>
                        <td><?= htmlspecialchars($r['KERUSAKAN']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <!-- TFOOT sebagai pengganjal ruang kosong saat diprint -->
                <tfoot><tr><td colspan="7" style="border:none; height:35px;"></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Elemen kode form akan muncul saat di-print -->
<div class="doc-code">FM.MTN.S01-43</div>

</body>
</html>
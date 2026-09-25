<?php
require "../config/database.php";

// ----------------------
// Ambil parameter filter
// ----------------------
$from  = isset($_GET['from'])  ? $_GET['from']  : "";
$to    = isset($_GET['to'])    ? $_GET['to']    : "";
$mac   = isset($_GET['mac'])   ? trim($_GET['mac'])   : "";
$plant = isset($_GET['plant']) ? trim($_GET['plant']) : "";

$from_disp  = $from;
$to_disp    = $to;
$mac_disp   = ($mac === "" ? "%" : $mac);
$plant_disp = ($plant === "" ? "%" : $plant);

$from_sql = $from ? date("Y-m-d", strtotime($from)) : null;
$to_sql   = $to   ? date("Y-m-d", strtotime($to))   : null;

$mac_param = ($mac === "" ? "%" : $mac);
$plant_param = ($plant === "" ? 0 : (int)$plant);

// Ambil data
$rows = [];
$error_msg = "";
if ($from_sql && $to_sql) {
    $sql = "{CALL SP_MTN_HISTORY_KERUSAKAN_KATEGORI_new(?, ?, ?, ?)}";
    $params = [$from_sql, $to_sql, $mac_param, $plant_param];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $error_msg = "Terjadi kesalahan saat mengambil data dari database.";
    } else {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $r;
        }
    }
}

// Grouping per KATEGORI
$grouped = [];
if (!empty($rows)) {
    foreach ($rows as $r) {
        $kat = $r['KATEGORI'];
        if (!isset($grouped[$kat])) {
            $grouped[$kat] = ['items' => [], 'total' => 0];
        }
        $grouped[$kat]['items'][] = $r;
        $grouped[$kat]['total']  += (int)$r['KERUSAKAN'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Laporan Kerusakan Per Periode</title>
    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
        body { background: #f4f6f9; padding: 20px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .page-title { color: #333; font-weight: 600; text-align: center; margin-bottom: 25px; }
        .card-custom { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: none; margin-bottom: 20px; }
        .card-header-custom { background: #343a40; color: #fff; border-radius: 8px 8px 0 0; padding: 12px 20px; font-weight: bold; }
        .kategori-title { font-weight: bold; background: #e9ecef; padding: 10px 15px; border-left: 4px solid #007bff; margin-top: 20px; margin-bottom: 0; font-size: 15px; }
        .table-custom { margin-bottom: 0; background: #fff; }
        .table-custom th { background: #f8f9fa; text-align: center; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6; }
        .table-custom td { vertical-align: middle; }
        .total-row { background: #fdfdfe; font-weight: bold; }
        .badge-total { font-size: 14px; padding: 6px 12px; border-radius: 20px; }
    </style>
</head>
<body>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="dashboard_mtn.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Kembali ke Menu</a>
    
    <?php if ($from_sql && $to_sql && empty($error_msg)): ?>
    <!-- Tombol Cetak Baru (Buka Tab Baru) -->
    <a href="report_kerusakan_periode_print.php?from=<?=urlencode($from)?>&to=<?=urlencode($to)?>&mac=<?=urlencode($mac)?>&plant=<?=urlencode($plant)?>" 
       target="_blank" class="btn btn-success">
       <i class="fas fa-print"></i> Cetak Laporan
    </a>
    <?php endif; ?>
</div>

<h2 class="page-title"><i class="fas fa-tools text-primary"></i> LAPORAN KERUSAKAN PER PERIODE</h2>

<!-- FORM FILTER -->
<div class="card card-custom">
    <div class="card-body">
        <form id="filterForm" method="get" action="">
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
                    <label class="font-weight-bold text-muted small">MAC (Mesin)</label>
                    <input type="text" name="mac" class="form-control" value="<?= htmlspecialchars($mac === "" ? "" : $mac) ?>" placeholder="Kosongkan = Semua">
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
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $error_msg ?></div>
<?php endif; ?>

<!-- HASIL LAPORAN -->
<?php if ($from_sql && $to_sql): ?>
<div class="card card-custom">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <span><i class="fas fa-file-alt"></i> Hasil Laporan Kerusakan</span>
        <span class="badge badge-light text-dark">
            Periode: <?= date("d-M-Y", strtotime($from_disp)) ?> s/d <?= date("d-M-Y", strtotime($to_disp)) ?>
        </span>
    </div>
    <div class="card-body p-0">
        
        <?php if (empty($rows)): ?>
            <div class="p-5 text-center text-muted">
                <i class="fas fa-folder-open fa-3x mb-3 text-light"></i>
                <h5>Tidak ada data kerusakan</h5>
                <p>Silakan sesuaikan parameter pencarian Anda.</p>
            </div>
        <?php else: ?>
            
            <div class="p-3 bg-light border-bottom">
                <strong>Filter Aktif:</strong> MAC (<?= htmlspecialchars($mac_disp) ?>) | Plant (<?= htmlspecialchars($plant_disp) ?>)
            </div>

            <?php foreach ($grouped as $kat => $data): ?>
                <div class="kategori-title"><i class="fas fa-cogs"></i> <?= htmlspecialchars($kat) ?></div>
                <div class="table-responsive">
                    <table class="table table-hover table-custom table-bordered">
                        <thead>
                            <tr>
                                <th>Detail Kerusakan</th>
                                <th width="150">Jumlah Kerusakan</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($data['items'] as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['DETAIL']) ?></td>
                                <td class="text-center font-weight-bold"><?= (int)$item['KERUSAKAN'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="total-row text-primary">
                                <td class="text-right text-uppercase">Total Kerusakan <?= htmlspecialchars($kat) ?></td>
                                <td class="text-center"><span class="badge badge-primary badge-total"><?= (int)$data['total'] ?></span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
            
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

</body>
</html>
<?php
// FILE: report_spc.php
require_once __DIR__ . '/../config/global.php';

if ($conn === false) {
    die("<div class='alert alert-danger p-3 m-3 font-weight-bold'>Koneksi database tidak tersedia.</div>");
}

$active_plant_session = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1';
$nama_plant = ($active_plant_session == 'p2') ? 'PLANT 2' : 'PLANT 1';
$badge_color = ($active_plant_session == 'p2') ? 'bg-success' : 'bg-primary';

$search_code = isset($_GET['search_code']) ? trim($_GET['search_code']) : '';
$data_found = false;
$pesan = "";

// Variabel Penampung Data
$header = [];
$details = [];
$chart_labels = [];
$chart_data_xbar = [];

// Variabel SPC Math
$k_subgroups = 0;
$sum_xbar = 0;
$sum_range = 0;

if (!empty($search_code)) {
    // 1. Ambil Data Header & Spesifikasi Master Part
    $sqlHead = "
        SELECT h.*, m.PartName, m.Target_Dimensi, m.USL, m.LSL 
        FROM SPC_Inspection_Header h
        LEFT JOIN Master_Part_Spec m ON h.PartNo = m.PartNo
        WHERE h.SpcCode = ?
    ";
    $stmtHead = sqlsrv_query($conn, $sqlHead, array($search_code));
    
    if ($stmtHead && $rowH = sqlsrv_fetch_array($stmtHead, SQLSRV_FETCH_ASSOC)) {
        $data_found = true;
        $header = $rowH;
        $spcId = $header['SpcID'];
        
        // 2. Ambil Data Detail Pengukuran
        $sqlDet = "SELECT * FROM SPC_Inspection_Detail WHERE SpcID = ? ORDER BY Sampel_Ke ASC";
        $stmtDet = sqlsrv_query($conn, $sqlDet, array($spcId));
        
        if ($stmtDet) {
            while ($rowD = sqlsrv_fetch_array($stmtDet, SQLSRV_FETCH_ASSOC)) {
                // Ambil nilai yang valid saja
                $vals = [];
                for ($i = 1; $i <= 5; $i++) {
                    if ($rowD["Val$i"] !== null && trim($rowD["Val$i"]) !== '') {
                        $vals[] = (float)$rowD["Val$i"];
                    }
                }
                
                if (count($vals) > 0) {
                    $xbar = array_sum($vals) / count($vals);
                    $range = max($vals) - min($vals);
                    
                    $rowD['X_Bar'] = $xbar;
                    $rowD['Range'] = $range;
                    $details[] = $rowD;
                    
                    // Akumulasi untuk Grand Total
                    $sum_xbar += $xbar;
                    $sum_range += $range;
                    $k_subgroups++;
                    
                    // Data untuk Grafik
                    $chart_labels[] = "S-" . $rowD['Sampel_Ke'];
                    $chart_data_xbar[] = round($xbar, 4);
                }
            }
        }
    } else {
        $pesan = "<div class='alert alert-warning'><i class='bi bi-exclamation-circle'></i> Dokumen SPC <b>$search_code</b> tidak ditemukan.</div>";
    }
}

// ==========================================
// KALKULASI CP & CPK
// ==========================================
$x_double_bar = 0;
$r_bar = 0;
$cp = 0;
$cpk = 0;
$sigma = 0;
$status_cpk = "N/A";
$color_cpk = "text-muted";

$target = isset($header['Target_Dimensi']) ? (float)$header['Target_Dimensi'] : 0;
$usl = isset($header['USL']) ? (float)$header['USL'] : 0;
$lsl = isset($header['LSL']) ? (float)$header['LSL'] : 0;

$is_spec_valid = ($usl > 0 || $lsl > 0); // Pastikan master spec sudah diisi

if ($data_found && $k_subgroups > 0) {
    $x_double_bar = $sum_xbar / $k_subgroups;
    $r_bar = $sum_range / $k_subgroups;
    
    // Konstanta d2 untuk n=5 sampel adalah 2.326
    $d2 = 2.326; 
    $sigma = ($r_bar / $d2);
    
    if ($is_spec_valid && $sigma > 0) {
        // Rumus Cp
        $cp = ($usl - $lsl) / (6 * $sigma);
        
        // Rumus Cpk (Ambil nilai terkecil antara Upper dan Lower)
        $cpk_upper = ($usl - $x_double_bar) / (3 * $sigma);
        $cpk_lower = ($x_double_bar - $lsl) / (3 * $sigma);
        $cpk = min($cpk_upper, $cpk_lower);
        
        // Penentuan Status
        if ($cpk >= 1.33) {
            $status_cpk = "EXCELLENT (Aman)";
            $color_cpk = "text-success";
        } elseif ($cpk >= 1.00) {
            $status_cpk = "GOOD (Cukup)";
            $color_cpk = "text-primary";
        } else {
            $status_cpk = "POOR (Bahaya / NG)";
            $color_cpk = "text-danger";
        }
    }
}

// Data Array Konstan untuk Garis Batas (Target, USL, LSL) di Grafik
$chart_data_usl = array_fill(0, count($chart_labels), $usl);
$chart_data_lsl = array_fill(0, count($chart_labels), $lsl);
$chart_data_target = array_fill(0, count($chart_labels), $target);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Analisa SPC</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .stat-box { background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); text-align: center; border-bottom: 4px solid #dee2e6; }
        .stat-box.primary { border-bottom-color: #0d6efd; }
        .stat-box.success { border-bottom-color: #198754; }
        .stat-box.warning { border-bottom-color: #ffc107; }
        .stat-box.danger { border-bottom-color: #dc3545; }
        .stat-value { font-size: 24px; font-weight: bold; margin-top: 5px; }
        .table-custom th { background-color: #f1f3f5; font-size: 12px; text-transform: uppercase; text-align: center; }
        .table-custom td { text-align: center; vertical-align: middle; font-size: 13px; }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <!-- HEADER & PENCARIAN -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h4 class="mb-0 fw-bold"><i class="bi bi-graph-up-arrow text-primary me-2"></i>Report Analisa SPC</h4>
        
        <div class="d-flex flex-grow-1 mx-md-4 align-items-center gap-2" style="max-width: 600px;">
            <form action="" method="GET" class="input-group input-group-sm shadow-sm" id="formSearchCode">
                <input type="hidden" name="page" value="report_spc"> <!-- Hidden input untuk dashboard -->
                <span class="input-group-text bg-white text-primary"><i class="bi bi-search"></i></span>
                <select id="search_code" name="search_code" class="form-control" required>
                    <?php if(!empty($search_code)): ?>
                        <option value="<?php echo htmlspecialchars($search_code); ?>" selected="selected">
                            <?php echo htmlspecialchars($search_code); ?>
                        </option>
                    <?php endif; ?>
                </select>
                <button type="submit" class="btn btn-primary fw-bold d-none">Lihat Report</button>
            </form>
            <a href="input_spc.php" class="btn btn-sm btn-success fw-bold shadow-sm text-nowrap">
                <i class="bi bi-plus-circle"></i> Input SPC Baru
            </a>
        </div>
        
        <span class="badge <?php echo $badge_color; ?> fs-6 px-3 py-2 shadow-sm rounded-pill text-nowrap">
            <i class="bi bi-building me-1"></i> <?php echo $nama_plant; ?>
        </span>
    </div>

    <?php echo $pesan; ?>

    <?php if($data_found): ?>
    
    <!-- PERINGATAN JIKA MASTER SPEC BELUM DIATUR -->
    <?php if(!$is_spec_valid): ?>
        <div class="alert alert-danger shadow-sm border-0 border-start border-danger border-4 py-2">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> 
            <strong>Perhatian:</strong> Nilai USL dan LSL untuk Part <b><?php echo htmlspecialchars($header['PartNo']); ?></b> belum diatur di database Master. Nilai Cp dan Cpk tidak dapat dihitung. Silakan Edit di tabel Master_Part_Spec.
        </div>
    <?php endif; ?>

    <!-- INFO HEADER DOKUMEN -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-2 px-3 bg-white rounded">
            <div class="row text-center text-md-start">
                <div class="col-md-2 border-end">
                    <small class="text-muted fw-bold d-block">KODE DOKUMEN</small>
                    <strong class="text-primary fs-6"><?php echo htmlspecialchars($header['SpcCode']); ?></strong>
                </div>
                <div class="col-md-3 border-end">
                    <small class="text-muted fw-bold d-block">PART NUMBER / NAME</small>
                    <strong class="text-dark fs-6"><?php echo htmlspecialchars($header['PartNo'] . ' - ' . $header['PartName']); ?></strong>
                </div>
                <div class="col-md-2 border-end">
                    <small class="text-muted fw-bold d-block">TANGGAL & SHIFT</small>
                    <strong class="text-dark fs-6">
                        <?php echo ($header['Tgl_Inspeksi'] instanceof DateTime) ? $header['Tgl_Inspeksi']->format('d-m-Y') : $header['Tgl_Inspeksi']; ?> 
                        (Shift <?php echo $header['Shift']; ?>)
                    </strong>
                </div>
                <div class="col-md-2 border-end">
                    <small class="text-muted fw-bold d-block">MESIN & INSPECTOR</small>
                    <strong class="text-dark fs-6"><?php echo htmlspecialchars($header['Mesin'] . ' | ' . $header['Inspector']); ?></strong>
                </div>
                <div class="col-md-3">
                    <small class="text-muted fw-bold d-block">STANDAR SPEC (STD)</small>
                    <span class="badge bg-secondary">Target: <?php echo $target; ?></span>
                    <span class="badge bg-danger">LSL: <?php echo $lsl; ?></span>
                    <span class="badge bg-danger">USL: <?php echo $usl; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- STATISTIK BOX -->
    <div class="row mb-3">
        <div class="col-md-2">
            <div class="stat-box primary">
                <div class="text-muted small fw-bold">X-Double Bar (Rata-rata)</div>
                <div class="stat-value text-primary"><?php echo number_format($x_double_bar, 4); ?></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-box primary">
                <div class="text-muted small fw-bold">R-Bar (Range Rata-rata)</div>
                <div class="stat-value text-primary"><?php echo number_format($r_bar, 4); ?></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-box warning">
                <div class="text-muted small fw-bold">Sigma (Estimasi &sigma;)</div>
                <div class="stat-value text-warning"><?php echo number_format($sigma, 5); ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box <?php echo $cp >= 1.33 ? 'success' : 'danger'; ?>">
                <div class="text-muted small fw-bold">Nilai Cp (Capability)</div>
                <div class="stat-value <?php echo $cp >= 1.33 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo $is_spec_valid ? number_format($cp, 3) : 'N/A'; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box <?php echo $cpk >= 1.33 ? 'success' : ($cpk >= 1.0 ? 'primary' : 'danger'); ?>">
                <div class="text-muted small fw-bold">Nilai Cpk (Index)</div>
                <div class="stat-value <?php echo $color_cpk; ?>">
                    <?php echo $is_spec_valid ? number_format($cpk, 3) : 'N/A'; ?>
                </div>
                <span class="badge <?php echo str_replace('text-', 'bg-', $color_cpk); ?> mt-1"><?php echo $status_cpk; ?></span>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- GRAFIK X-BAR -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0 mb-3 h-100">
                <div class="card-header bg-white py-2">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-graph-up text-primary"></i> Grafik X-Bar (Trend Ukuran)</h6>
                </div>
                <div class="card-body">
                    <canvas id="spcChart" style="min-height: 350px;"></canvas>
                </div>
            </div>
        </div>
        
        <!-- TABEL DETAIL -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-3 h-100">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">Data Kalkulasi Sampel</h6>
                    <a href="input_spc.php?search_code=<?php echo urlencode($search_code); ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i> Edit Data</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-bordered table-striped table-hover table-custom mb-0">
                            <thead class="sticky-top">
                                <tr>
                                    <th>Sampel</th>
                                    <th>Rata-rata (X-Bar)</th>
                                    <th>Range (R)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($details as $row): ?>
                                <tr>
                                    <td class="fw-bold">Ke-<?php echo $row['Sampel_Ke']; ?></td>
                                    <td><?php echo number_format($row['X_Bar'], 3); ?></td>
                                    <td><?php echo number_format($row['Range'], 3); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; // End of $data_found ?>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- CHART.JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(document).ready(function() {
    // Autocomplete Pencarian Report
    $('#search_code').select2({
        theme: 'bootstrap-5',
        placeholder: 'Cari Kode SPC / Part Number...',
        minimumInputLength: 1, 
        ajax: {
            url: 'ajax_search_spc_code.php', 
            dataType: 'json',
            delay: 300,
            data: function (params) { return { term: params.term }; },
            processResults: function (data) { return { results: data.results }; },
            cache: true
        }
    });

    $('#search_code').on('select2:select', function (e) {
        $('#formSearchCode').submit();
    });
});

<?php if($data_found && $is_spec_valid): ?>
    // ==========================================
    // RENDER GRAFIK CHART.JS
    // ==========================================
    const ctx = document.getElementById('spcChart').getContext('2d');
    
    const dataLabels = <?php echo json_encode($chart_labels); ?>;
    const dataXBar = <?php echo json_encode($chart_data_xbar); ?>;
    
    const dataUSL = <?php echo json_encode($chart_data_usl); ?>;
    const dataLSL = <?php echo json_encode($chart_data_lsl); ?>;
    const dataTarget = <?php echo json_encode($chart_data_target); ?>;
    
    const spcChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: dataLabels,
            datasets: [
                {
                    label: 'X-Bar (Rata-rata)',
                    data: dataXBar,
                    borderColor: '#0d6efd', // Biru
                    backgroundColor: 'rgba(13, 110, 253, 0.2)',
                    borderWidth: 3,
                    pointRadius: 5,
                    pointBackgroundColor: '#fff',
                    pointBorderWidth: 2,
                    tension: 0.1,
                    fill: false,
                    zIndex: 10
                },
                {
                    label: 'Target Std',
                    data: dataTarget,
                    borderColor: '#198754', // Hijau
                    borderWidth: 2,
                    borderDash: [5, 5], // Garis putus-putus
                    pointRadius: 0,
                    fill: false
                },
                {
                    label: 'USL (Batas Atas)',
                    data: dataUSL,
                    borderColor: '#dc3545', // Merah
                    borderWidth: 2,
                    borderDash: [10, 5],
                    pointRadius: 0,
                    fill: false
                },
                {
                    label: 'LSL (Batas Bawah)',
                    data: dataLSL,
                    borderColor: '#dc3545', // Merah
                    borderWidth: 2,
                    borderDash: [10, 5],
                    pointRadius: 0,
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y;
                        }
                    }
                },
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    // Agar grafik tidak menempel ketat di USL/LSL, kita berikan sedikit ruang padding
                    suggestedMax: <?php echo $usl + ($usl * 0.05); ?>,
                    suggestedMin: <?php echo $lsl - ($lsl * 0.05); ?>
                }
            }
        }
    });
<?php endif; ?>
</script>
</body>
</html>
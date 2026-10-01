<?php
ini_set('memory_limit', '512M');
require_once __DIR__ . "/../config/database_mold.php";

if (!function_exists('h')) {
    function h($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

// -------------------------------------------------------------
// 1. STATISTIK KARTU UTAMA
// -------------------------------------------------------------

// Total Master Mold
$qTotalMold = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM MOLD_MASTER");
$rTotalMold = $qTotalMold ? sqlsrv_fetch_array($qTotalMold, SQLSRV_FETCH_ASSOC) : ['total' => 0];
$totalMasterMold = $rTotalMold['total'];

// Total Kasus Open & Close (MOLD_HISTORY_DETAIL)
$qStatusHist = sqlsrv_query($conn, "SELECT 
    SUM(CASE WHEN [STATUS] = 0 OR [STATUS] = 'OPEN' THEN 1 ELSE 0 END) AS total_open,
    SUM(CASE WHEN [STATUS] = 1 OR [STATUS] = 'CLOSE' OR [STATUS] = 'CLOSED' THEN 1 ELSE 0 END) AS total_close,
    COUNT(*) AS total_cases
    FROM MOLD_HISTORY_DETAIL");
$rStatusHist = $qStatusHist ? sqlsrv_fetch_array($qStatusHist, SQLSRV_FETCH_ASSOC) : ['total_open' => 0, 'total_close' => 0, 'total_cases' => 0];
$totalOpenCases  = intval($rStatusHist['total_open']);
$totalCloseCases = intval($rStatusHist['total_close']);
$totalCases      = intval($rStatusHist['total_cases']);

// Mutasi Bulan Ini (MOLD_TRANS_DETAIL)
$currentMonthStart = date('Y-m-01 00:00:00');
$currentMonthEnd   = date('Y-m-t 23:59:59');
$qTransMonth = sqlsrv_query($conn, "SELECT 
    SUM(CASE WHEN JENIS_TRANSAKSI = 1 OR MOLD_IN = 1 THEN 1 ELSE 0 END) AS total_in,
    SUM(CASE WHEN JENIS_TRANSAKSI = 2 OR MOLD_OUT = 1 THEN 1 ELSE 0 END) AS total_out,
    COUNT(*) AS total_trans
    FROM MOLD_TRANS_DETAIL
    WHERE [DATE] BETWEEN ? AND ?", [$currentMonthStart, $currentMonthEnd]);
$rTransMonth = $qTransMonth ? sqlsrv_fetch_array($qTransMonth, SQLSRV_FETCH_ASSOC) : ['total_in' => 0, 'total_out' => 0, 'total_trans' => 0];
$totalInMonth    = intval($rTransMonth['total_in']);
$totalOutMonth   = intval($rTransMonth['total_out']);
$totalTransMonth = intval($rTransMonth['total_trans']);

// Total Rak Penyimpanan
$qTotalRack = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM MOLD_RACK");
$rTotalRack = $qTotalRack ? sqlsrv_fetch_array($qTotalRack, SQLSRV_FETCH_ASSOC) : ['total' => 0];
$totalRack  = $rTotalRack['total'];

// -------------------------------------------------------------
// 2. TABEL RECENT OPEN ISSUES (BUTUH TINDAKAN)
// -------------------------------------------------------------
$recentOpenSql = "SELECT TOP 5 
                    MM.PART_NO, 
                    MM.PART_NAME, 
                    MHD.[DATE], 
                    MHD.PROBLEM, 
                    MHD.WORKING_PROSES, 
                    MHD.PIC,
                    MC.CLASSIFICATION
                  FROM MOLD_HISTORY_DETAIL MHD
                  LEFT JOIN MOLD_MASTER MM ON MHD.MOLD_ID = MM.MOLD_ID
                  LEFT JOIN MOLD_CLASSIFICATION MC ON MHD.CLASSIFICATON = MC.ID
                  WHERE (MHD.[STATUS] = 0 OR MHD.[STATUS] = 'OPEN')
                  ORDER BY MHD.[DATE] DESC, MHD.MOLD_ID DESC";
$resRecentOpen = sqlsrv_query($conn, $recentOpenSql);
$recentOpenList = [];
if ($resRecentOpen !== false) {
    while ($row = sqlsrv_fetch_array($resRecentOpen, SQLSRV_FETCH_ASSOC)) {
        $recentOpenList[] = $row;
    }
}

// -------------------------------------------------------------
// 3. TABEL RECENT TRANSAKSI MUTASI (5 TERAKHIR)
// -------------------------------------------------------------
$recentTransSql = "SELECT TOP 5 
                    MM.PART_NO, 
                    MM.PART_NAME, 
                    MM.CUST_ALIAS, 
                    MTD.[DATE], 
                    MTD.JENIS_TRANSAKSI, 
                    MTD.MOLD_IN, 
                    MTD.MOLD_OUT,
                    MTD.REASON, 
                    MTD.VENDOR, 
                    MTD.SUPPLIER
                   FROM MOLD_TRANS_DETAIL MTD
                   LEFT JOIN MOLD_MASTER MM ON MTD.MOLD_ID = MM.MOLD_ID
                   ORDER BY MTD.[DATE] DESC";
$resRecentTrans = sqlsrv_query($conn, $recentTransSql);
$recentTransList = [];
if ($resRecentTrans !== false) {
    while ($row = sqlsrv_fetch_array($resRecentTrans, SQLSRV_FETCH_ASSOC)) {
        $recentTransList[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Dashboard Ringkasan Mold</title>
    <style>
        body { margin: 0; padding: 15px; background: #d4d0c8; font-family: Tahoma, Arial, sans-serif; font-size: 11px; color: #222; }
        
        /* Bar Atas Judul */
        .page-header { background: #2c3e50; color: #ffffff; padding: 10px 14px; border-radius: 3px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 14px; font-weight: bold; letter-spacing: 0.5px; }
        .page-date { font-size: 11px; color: #bdc3c7; }

        /* Grid Kartu Statistik */
        .card-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 15px; }
        .kpi-card { background: #ffffff; border: 1px solid #7f9db9; border-radius: 3px; padding: 12px 14px; box-shadow: 1px 1px 3px rgba(0,0,0,0.1); }
        .kpi-title { font-size: 11px; font-weight: bold; color: #555; text-transform: uppercase; margin-bottom: 6px; }
        .kpi-value { font-size: 24px; font-weight: bold; color: #2c3e50; line-height: 28px; }
        .kpi-desc { font-size: 10px; color: #7f8c8d; margin-top: 4px; }
        
        .card-red { border-left: 5px solid #c0392b; }
        .card-green { border-left: 5px solid #27ae60; }
        .card-blue { border-left: 5px solid #2980b9; }
        .card-orange { border-left: 5px solid #d35400; }

        /* Layout 2 Kolom Konten */
        .dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .panel-box { background: #ffffff; border: 1px solid #808080; border-radius: 2px; }
        .panel-header { background: #eeeeee; border-bottom: 1px solid #808080; padding: 8px 12px; font-weight: bold; color: #2c3e50; font-size: 11px; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 8px; }
        
        .view-all-link { color: #2980b9; text-decoration: none; font-size: 10px; font-weight: bold; }
        .view-all-link:hover { text-decoration: underline; }

        /* Tabel Mini */
        .dash-table { width: 100%; border-collapse: collapse; font-size: 10px; }
        .dash-table th { background: #2e4053; color: white; padding: 6px; border: 1px solid #7f8c8d; text-align: left; }
        .dash-table td { padding: 5px; border: 1px solid #bdc3c7; }
        .dash-table tr:nth-child(even) { background: #f8f9fa; }
        .text-center { text-align: center; }

        .badge-open { background: #fceae9; color: #c0392b; font-weight: bold; padding: 2px 6px; border-radius: 2px; border: 1px solid #f5c6cb; }
        .badge-in { background: #e8f8f5; color: #16a085; font-weight: bold; padding: 2px 6px; border-radius: 2px; }
        .badge-out { background: #fef5e7; color: #d35400; font-weight: bold; padding: 2px 6px; border-radius: 2px; }

        /* Progress Bar Sederhana */
        .status-bar-container { background: #ecf0f1; border-radius: 3px; height: 12px; overflow: hidden; margin-top: 8px; display: flex; }
        .bar-green { background: #27ae60; height: 100%; }
        .bar-red { background: #e74c3c; height: 100%; }
    </style>
</head>
<body>

<div class="page-header">
    <div class="page-title">MOLD CONTROL OVERVIEW & MONITORING DASHBOARD</div>
    <div class="page-date">Periode: <?= date('F Y'); ?> | Server Time: <?= date('d-M-Y H:i'); ?></div>
</div>

<!-- ======================================================== -->
<!-- 1. KARTU STATISTIK METRIK UTAMA (KPI)                   -->
<!-- ======================================================== -->
<div class="card-grid">
    <div class="kpi-card card-blue">
        <div class="kpi-title">TOTAL MASTER MOLD</div>
        <div class="kpi-value"><?= number_format($totalMasterMold); ?></div>
        <div class="kpi-desc">Cetakan aktif terdaftar pada master</div>
    </div>
    
    <div class="kpi-card card-red">
        <div class="kpi-title">MOLD CASES (OPEN)</div>
        <div class="kpi-value" style="color: #c0392b;"><?= number_format($totalOpenCases); ?></div>
        <div class="kpi-desc">Kasus perbaikan mold membutuhkan aksi</div>
    </div>

    <div class="kpi-card card-orange">
        <div class="kpi-title">MUTASI BULAN INI</div>
        <div class="kpi-value"><?= number_format($totalTransMonth); ?></div>
        <div class="kpi-desc">In: <?= $totalInMonth; ?> Mold | Out: <?= $totalOutMonth; ?> Mold</div>
    </div>

    <div class="kpi-card card-green">
        <div class="kpi-title">STATUS PERBAIKAN SELESAI</div>
        <div class="kpi-value" style="color: #27ae60;">
            <?= ($totalCases > 0) ? round(($totalCloseCases / $totalCases) * 100) : 0; ?>%
        </div>
        <div class="kpi-desc"><?= $totalCloseCases; ?> dari <?= $totalCases; ?> Kasus ditutup (CLOSED)</div>
    </div>
</div>

<!-- Progress Bar Rasio Kasus Perbaikan -->
<div style="background: #fff; padding: 10px 14px; border: 1px solid #7f9db9; border-radius: 3px; margin-bottom: 15px;">
    <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:10px;">
        <span>RASIO PENYELESAIAN KASUS (CLOSED: <?= $totalCloseCases; ?> vs OPEN: <?= $totalOpenCases; ?>)</span>
        <span><?= ($totalCases > 0) ? round(($totalCloseCases / $totalCases) * 100) : 0; ?>% Tuntas</span>
    </div>
    <div class="status-bar-container">
        <?php 
            $pctClose = ($totalCases > 0) ? ($totalCloseCases / $totalCases) * 100 : 100;
            $pctOpen  = 100 - $pctClose;
        ?>
        <div class="bar-green" style="width: <?= $pctClose; ?>%;" title="Closed"></div>
        <div class="bar-red" style="width: <?= $pctOpen; ?>%;" title="Open"></div>
    </div>
</div>

<!-- ======================================================== -->
<!-- 2. TABEL MONITORING (2 KOLOM)                           -->
<!-- ======================================================== -->
<div class="dashboard-grid">
    <!-- Kolom Kiri: Kasus Open Butuh Perhatian -->
    <div class="panel-box">
        <div class="panel-header">
            <span>DAFTAR KASUS MOLD PERLU TINDAKAN (OPEN)</span>
            <a href="report_center.php" class="view-all-link">LIHAT SEMUA &rarr;</a>
        </div>
        <div class="panel-body">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">PART NO</th>
                        <th style="width: 25%;">KLASIFIKASI</th>
                        <th style="width: 35%;">PROBLEM / PENYEBAB</th>
                        <th style="width: 15%;" class="text-center">PIC</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recentOpenList)): ?>
                        <?php foreach ($recentOpenList as $row): ?>
                            <tr>
                                <td><strong><?= h(trim($row['PART_NO'])); ?></strong></td>
                                <td><?= h($row['CLASSIFICATION'] ? $row['CLASSIFICATION'] : '-'); ?></td>
                                <td><?= h($row['PROBLEM']); ?></td>
                                <td class="text-center"><?= h($row['PIC']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center" style="padding: 15px; color: #27ae60; font-weight: bold;">
                                &check; Tidak ada kendala mold yang pending / open.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Kolom Kanan: 5 Mutasi Mold Terkini -->
    <div class="panel-box">
        <div class="panel-header">
            <span>MUTASI TRANSAKSI TERAKHIR</span>
            <a href="mold_trans.php" class="view-all-link">LIHAT TRANSAKSI &rarr;</a>
        </div>
        <div class="panel-body">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th style="width: 20%;">WAKTU</th>
                        <th style="width: 30%;">PART NO</th>
                        <th style="width: 15%;" class="text-center">TIPE</th>
                        <th style="width: 35%;">KETERANGAN / VENDOR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recentTransList)): ?>
                        <?php foreach ($recentTransList as $row): ?>
                            <?php 
                                $isOut = ($row['JENIS_TRANSAKSI'] == 2 || $row['MOLD_OUT'] == 1);
                                $tgl = ($row['DATE'] instanceof DateTime) ? $row['DATE']->format('d-M H:i') : '-';
                            ?>
                            <tr>
                                <td><?= $tgl; ?></td>
                                <td><strong><?= h(trim($row['PART_NO'])); ?></strong></td>
                                <td class="text-center">
                                    <span class="<?= $isOut ? 'badge-out' : 'badge-in'; ?>">
                                        <?= $isOut ? 'MOLD OUT' : 'MOLD IN'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?= h($row['REASON'] ? $row['REASON'] : ($row['VENDOR'] ? 'Vendor: ' . $row['VENDOR'] : '-')); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center" style="padding: 15px; color: #888;">
                                Belum ada riwayat transaksi mutasi.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
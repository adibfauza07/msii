<?php
/* ============================================================
   ng_prod.php — Dashboard NG Production (Include Version)
   Koneksi via: require_once '../config/Database_p1.php';
   Dipanggil dari sidebar menu: ?page=ng_prod
   ============================================================ */

// ── Koneksi Database dari config ─────────────────────────────
require_once '../config/Database_p1.php';
// Asumsi: Database_p1.php menyediakan variabel $conn (sqlsrv)
// Jika variabel berbeda, sesuaikan (misal $db, $connection, dll)

// ── Parameter Tanggal ────────────────────────────────────────
 $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
 $end_date   = isset($_POST['end_date'])   ? $_POST['end_date']   : date('Y-m-d');
 $cust_id    = 275;

// ── Query Utama: NGP_QTY per PART_NO ─────────────────────────
 $sqlMain = "
    SELECT 
        SUM(dbo.PRODUCTION.PD_QTY) AS PD_QTY, 
        SUM(dbo.PRODUCTION.PD_NG) AS NG, 
        dbo.ITEM_CUSTINFO_VIEW.CUST_ID, 
        dbo.ITEM_CUSTINFO_VIEW.CUST_CODE, 
        dbo.ITEM_CUSTINFO_VIEW.CUST_COMP, 
        dbo.NG_PROD.NGP_QTY, 
        dbo.NG_TYPE.NGT_CODE, 
        dbo.NG_TYPE.NGT_DESC, 
        dbo.ITEM_CUSTINFO_VIEW.PART_NO, 
        dbo.ITEM_CUSTINFO_VIEW.PART_NAME
    FROM dbo.PRODUCTION 
        INNER JOIN dbo.WO ON dbo.PRODUCTION.WO_ID = dbo.WO.WO_ID 
        INNER JOIN dbo.ITEM_CUSTINFO_VIEW ON dbo.WO.ITEM_ID = dbo.ITEM_CUSTINFO_VIEW.ITEM_ID 
        INNER JOIN dbo.NG_PROD ON dbo.PRODUCTION.PD_ID = dbo.NG_PROD.PD_ID 
        INNER JOIN dbo.NG_TYPE ON dbo.NG_PROD.NGT_ID = dbo.NG_TYPE.NGT_ID
    WHERE dbo.PRODUCTION.PD_DATE BETWEEN ? AND ?
        AND dbo.ITEM_CUSTINFO_VIEW.CUST_ID = ?
    GROUP BY dbo.ITEM_CUSTINFO_VIEW.CUST_ID, dbo.ITEM_CUSTINFO_VIEW.CUST_CODE, 
             dbo.ITEM_CUSTINFO_VIEW.CUST_COMP, dbo.NG_PROD.NGP_QTY, dbo.NG_TYPE.NGT_CODE, 
             dbo.NG_TYPE.NGT_DESC, dbo.ITEM_CUSTINFO_VIEW.PART_NO, dbo.ITEM_CUSTINFO_VIEW.PART_NAME
    ORDER BY dbo.ITEM_CUSTINFO_VIEW.PART_NO
";
 $paramsMain = array($start_date, $end_date, $cust_id);
 $stmtMain  = sqlsrv_query($conn, $sqlMain, $paramsMain);

// ── Agregasi data per PART_NO di PHP ─────────────────────────
 $partData   = array();
 $allRows    = array();
 $totalPD    = 0;
 $totalNG    = 0;
 $totalNGP   = 0;
 $custInfo   = array("code" => "", "comp" => "");

if ($stmtMain) {
    while ($row = sqlsrv_fetch_array($stmtMain, SQLSRV_FETCH_ASSOC)) {
        $allRows[] = $row;
        $pn = $row['PART_NO'];
        $totalPD  += (float)$row['PD_QTY'];
        $totalNG  += (float)$row['NG'];
        $totalNGP += (float)$row['NGP_QTY'];
        $custInfo["code"] = $row['CUST_CODE'];
        $custInfo["comp"] = $row['CUST_COMP'];

        if (!isset($partData[$pn])) {
            $partData[$pn] = array(
                "part_name" => $row['PART_NAME'],
                "total_ngp" => 0,
                "total_pd"  => 0,
                "total_ng"  => 0,
                "ng_types"  => array()
            );
        }
        $partData[$pn]["total_ngp"] += (float)$row['NGP_QTY'];
        $partData[$pn]["total_pd"]  += (float)$row['PD_QTY'];
        $partData[$pn]["total_ng"]  += (float)$row['NG'];

        $ngtKey = $row['NGT_CODE'] . ' - ' . $row['NGT_DESC'];
        if (!isset($partData[$pn]["ng_types"][$ngtKey])) {
            $partData[$pn]["ng_types"][$ngtKey] = 0;
        }
        $partData[$pn]["ng_types"][$ngtKey] += (float)$row['NGP_QTY'];
    }
}

// Urutkan berdasarkan total_ngp terbesar
uasort($partData, function($a, $b) {
    return $b['total_ngp'] - $a['total_ngp'];
});

// Ambil PART_NO teratas untuk trend
 $topPartNo   = !empty($partData) ? key($partData) : '';
 $topPartName = isset($partData[$topPartNo]['part_name']) ? $partData[$topPartNo]['part_name'] : '-';

// ── Query Trend: NGP_QTY harian untuk PART_NO teratas ───────
 $trendLabels = array();
 $trendValues = array();
if ($topPartNo) {
    $sqlTrend = "
        SELECT 
            CONVERT(VARCHAR(10), dbo.PRODUCTION.PD_DATE, 23) AS TANGGAL,
            SUM(dbo.NG_PROD.NGP_QTY) AS NGP_QTY
        FROM dbo.PRODUCTION 
            INNER JOIN dbo.WO ON dbo.PRODUCTION.WO_ID = dbo.WO.WO_ID 
            INNER JOIN dbo.ITEM_CUSTINFO_VIEW ON dbo.WO.ITEM_ID = dbo.ITEM_CUSTINFO_VIEW.ITEM_ID 
            INNER JOIN dbo.NG_PROD ON dbo.PRODUCTION.PD_ID = dbo.NG_PROD.PD_ID 
        WHERE dbo.PRODUCTION.PD_DATE BETWEEN ? AND ?
            AND dbo.ITEM_CUSTINFO_VIEW.CUST_ID = ?
            AND dbo.ITEM_CUSTINFO_VIEW.PART_NO = ?
        GROUP BY CONVERT(VARCHAR(10), dbo.PRODUCTION.PD_DATE, 23)
        ORDER BY CONVERT(VARCHAR(10), dbo.PRODUCTION.PD_DATE, 23)
    ";
    $paramsTrend = array($start_date, $end_date, $cust_id, $topPartNo);
    $stmtTrend   = sqlsrv_query($conn, $sqlTrend, $paramsTrend);
    if ($stmtTrend) {
        while ($trow = sqlsrv_fetch_array($stmtTrend, SQLSRV_FETCH_ASSOC)) {
            $trendLabels[] = $trow['TANGGAL'];
            $trendValues[] = (float)$trow['NGP_QTY'];
        }
    }
}

// ── Siapkan data untuk Chart.js (JSON) ───────────────────────
 $pieLabels   = array();
 $pieValues   = array();
 $barLabels   = array();
 $barValues   = array();
 $barColors   = array();

 $palette = array(
    '#f59e0b', '#ef4444', '#10b981', '#3b82f6', '#8b5cf6',
    '#ec4899', '#14b8a6', '#f97316', '#06b6d4', '#84cc16',
    '#e11d48', '#6366f1', '#22d3ee', '#a3e635', '#fb923c',
    '#c084fc', '#fbbf24', '#34d399', '#60a5fa', '#f472b6'
);

 $i = 0;
foreach ($partData as $pn => $info) {
    $shortLabel = strlen($pn) > 18 ? substr($pn, 0, 18) . '…' : $pn;
    $pieLabels[] = $shortLabel;
    $pieValues[] = $info['total_ngp'];
    $barLabels[] = $pn;
    $barValues[] = $info['total_ngp'];
    $barColors[] = $palette[$i % count($palette)];
    $i++;
}

 $ngRate    = $totalPD > 0 ? round(($totalNG / $totalPD) * 100, 2) : 0;
 $partCount = count($partData);

// NG Type distribution
 $ngTypeData = array();
foreach ($partData as $pn => $info) {
    foreach ($info['ng_types'] as $ngtName => $ngtQty) {
        if (!isset($ngTypeData[$ngtName])) {
            $ngTypeData[$ngtName] = 0;
        }
        $ngTypeData[$ngtName] += $ngtQty;
    }
}
arsort($ngTypeData);
 $ngTypeLabels = array_keys($ngTypeData);
 $ngTypeValues = array_values($ngTypeData);
?>

<!-- NG Production Dashboard — Stylesheet -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

<style>
    /* Reset khusus area konten NG Prod */
    #ngp-dashboard {
        font-family: 'Space Grotesk', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: #e5e7eb;
        max-width: 1400px;
    }

    /* Override background konten utama jadi gelap */
    #content.night-mode {
        background: #0b0d11 !important;
    }

    /* Background grid halus */
    .ngp-bg-grid {
        background-image:
            linear-gradient(rgba(245,158,11,0.03) 1px, transparent 1px),
            linear-gradient(90deg, rgba(245,158,11,0.03) 1px, transparent 1px);
        background-size: 40px 40px;
        position: relative;
    }
    .ngp-bg-grid::before {
        content: '';
        position: absolute;
        top: -200px; right: -200px;
        width: 600px; height: 600px;
        background: radial-gradient(circle, rgba(245,158,11,0.06) 0%, transparent 70%);
        pointer-events: none;
    }
    .ngp-bg-grid::after {
        content: '';
        position: absolute;
        bottom: -300px; left: -200px;
        width: 700px; height: 700px;
        background: radial-gradient(circle, rgba(239,68,68,0.04) 0%, transparent 70%);
        pointer-events: none;
    }

    /* Card */
    .ngp-card {
        background: #161a24;
        border: 1px solid #252b3b;
        border-radius: 12px;
        transition: all 0.3s ease;
    }
    .ngp-card:hover {
        border-color: rgba(245,158,11,0.3);
        box-shadow: 0 0 30px rgba(245,158,11,0.05);
    }

    /* Stat card */
    .ngp-stat { position: relative; overflow: hidden; }
    .ngp-stat::before {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 100%; height: 3px;
        background: linear-gradient(90deg, transparent, #f59e0b, transparent);
        opacity: 0;
        transition: opacity 0.3s;
    }
    .ngp-stat:hover::before { opacity: 1; }
    .ngp-stat.ngp-danger::before {
        background: linear-gradient(90deg, transparent, #ef4444, transparent);
    }
    .ngp-stat.ngp-success::before {
        background: linear-gradient(90deg, transparent, #10b981, transparent);
    }

    /* Angka */
    .ngp-num {
        font-family: 'JetBrains Mono', monospace;
        font-weight: 600;
        font-size: 1.7rem;
        line-height: 1;
    }

    /* Input date */
    .ngp-date-input {
        background: #1c2130;
        border: 1px solid #252b3b;
        color: #e5e7eb;
        padding: 8px 14px;
        border-radius: 8px;
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.85rem;
        outline: none;
        transition: border-color 0.2s;
    }
    .ngp-date-input:focus {
        border-color: #f59e0b;
        box-shadow: 0 0 0 3px rgba(245,158,11,0.15);
    }
    .ngp-date-input::-webkit-calendar-picker-indicator {
        filter: invert(0.7);
        cursor: pointer;
    }

    /* Tombol */
    .ngp-btn {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: #0b0d11;
        font-weight: 600;
        padding: 8px 22px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        font-family: 'Space Grotesk', sans-serif;
        font-size: 0.9rem;
    }
    .ngp-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 20px rgba(245,158,11,0.3);
    }
    .ngp-btn:active { transform: translateY(0); }

    /* Badge */
    .ngp-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.03em;
    }
    .ngp-badge-danger { background: rgba(239,68,68,0.15); color: #f87171; }
    .ngp-badge-warn   { background: rgba(245,158,11,0.15); color: #fbbf24; }
    .ngp-badge-ok     { background: rgba(16,185,129,0.15); color: #34d399; }

    /* Tabel */
    .ngp-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .ngp-table thead th {
        background: #1c2130;
        padding: 12px 14px;
        text-align: left;
        font-weight: 600;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #6b7280;
        border-bottom: 1px solid #252b3b;
        position: sticky;
        top: 0;
        z-index: 2;
    }
    .ngp-table thead th:first-child { border-radius: 8px 0 0 0; }
    .ngp-table thead th:last-child  { border-radius: 0 8px 0 0; }
    .ngp-table tbody tr { transition: background 0.2s; }
    .ngp-table tbody tr:hover { background: rgba(245,158,11,0.04); }
    .ngp-table tbody td {
        padding: 10px 14px;
        border-bottom: 1px solid rgba(37,43,59,0.5);
        font-size: 0.82rem;
    }
    .ngp-table tbody tr:last-child td { border-bottom: none; }

    /* Pulse dot */
    .ngp-pulse {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #f59e0b;
        position: relative;
        display: inline-block;
    }
    .ngp-pulse::after {
        content: '';
        position: absolute;
        inset: -4px;
        border-radius: 50%;
        border: 2px solid #f59e0b;
        animation: ngp-pulse-ring 2s ease infinite;
    }
    @keyframes ngp-pulse-ring {
        0%   { transform: scale(0.8); opacity: 0.6; }
        100% { transform: scale(1.8); opacity: 0; }
    }

    /* Scrollbar */
    .ngp-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
    .ngp-scroll::-webkit-scrollbar-track { background: #0b0d11; }
    .ngp-scroll::-webkit-scrollbar-thumb { background: #252b3b; border-radius: 3px; }
    .ngp-scroll::-webkit-scrollbar-thumb:hover { background: #3a4155; }

    /* Animasi masuk */
    @keyframes ngpFadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .ngp-anim {
        animation: ngpFadeUp 0.5s ease forwards;
        opacity: 0;
    }
    .ngp-d1 { animation-delay: 0.05s; }
    .ngp-d2 { animation-delay: 0.1s; }
    .ngp-d3 { animation-delay: 0.15s; }
    .ngp-d4 { animation-delay: 0.2s; }
    .ngp-d5 { animation-delay: 0.3s; }
    .ngp-d6 { animation-delay: 0.4s; }
    .ngp-d7 { animation-delay: 0.5s; }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            animation-duration: 0.01ms !important;
            transition-duration: 0.01ms !important;
        }
    }
</style>

<!-- Konten Dashboard NG Production -->
<div id="ngp-dashboard" class="ngp-bg-grid" style="position:relative; z-index:1;">

    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6 ngp-anim ngp-d1">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <span class="ngp-pulse"></span>
                <span style="color:#f59e0b; font-size:0.7rem; font-family:'JetBrains Mono',monospace; letter-spacing:0.15em; text-transform:uppercase;">Live Monitoring</span>
            </div>
            <h2 style="margin:0; font-size:1.5rem; font-weight:700; color:#f0f0f0;">
                NG Production <span style="color:#f59e0b;">Dashboard</span>
                <span style="font-size:0.75rem; color:#6b7280; font-weight:400; margin-left:8px;">Plant 1</span>
            </h2>
            <p style="font-size:0.82rem; color:#6b7280; margin-top:4px;">
                Customer: <span style="color:#d1d5db; font-weight:500;"><?= htmlspecialchars($custInfo['comp'] ?: '-') ?></span>
                <span style="margin:0 8px; color:#374151;">|</span>
                Code: <span style="font-family:'JetBrains Mono',monospace; color:#d1d5db;"><?= htmlspecialchars($custInfo['code'] ?: '-') ?></span>
            </p>
        </div>

        <!-- Filter Tanggal -->
        <form method="POST" action="?page=ng_prod" style="display:flex; flex-wrap:wrap; align-items:flex-end; gap:10px;">
            <div style="display:flex; flex-direction:column; gap:4px;">
                <label style="font-size:0.72rem; color:#6b7280; font-weight:500;">Start Date</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="ngp-date-input">
            </div>
            <div style="display:flex; flex-direction:column; gap:4px;">
                <label style="font-size:0.72rem; color:#6b7280; font-weight:500;">End Date</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="ngp-date-input">
            </div>
            <button type="submit" class="ngp-btn">
                <i class="bi bi-funnel"></i> Terapkan
            </button>
        </form>
    </div>

    <!-- Summary Cards -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:20px;">
        <div class="ngp-card ngp-stat ngp-anim ngp-d1" style="padding:20px;">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                <div style="width:32px; height:32px; border-radius:8px; background:rgba(245,158,11,0.1); display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-box-seam" style="color:#f59e0b; font-size:0.85rem;"></i>
                </div>
                <span style="font-size:0.7rem; color:#6b7280; font-weight:500; text-transform:uppercase; letter-spacing:0.06em;">Total Production</span>
            </div>
            <div class="ngp-num" style="color:#e5e7eb;" id="numPd"><?= number_format($totalPD, 0) ?></div>
            <p style="font-size:0.72rem; color:#4b5563; margin-top:8px;">Unit diproduksi</p>
        </div>

        <div class="ngp-card ngp-stat ngp-danger ngp-anim ngp-d2" style="padding:20px;">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                <div style="width:32px; height:32px; border-radius:8px; background:rgba(239,68,68,0.1); display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-exclamation-triangle" style="color:#f87171; font-size:0.85rem;"></i>
                </div>
                <span style="font-size:0.7rem; color:#6b7280; font-weight:500; text-transform:uppercase; letter-spacing:0.06em;">Total NG</span>
            </div>
            <div class="ngp-num" style="color:#f87171;" id="numNg"><?= number_format($totalNG, 0) ?></div>
            <p style="font-size:0.72rem; color:#4b5563; margin-top:8px;">Unit tidak lolos QC</p>
        </div>

        <div class="ngp-card ngp-stat ngp-anim ngp-d3" style="padding:20px;">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                <div style="width:32px; height:32px; border-radius:8px; background:rgba(245,158,11,0.1); display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-percent" style="color:#f59e0b; font-size:0.85rem;"></i>
                </div>
                <span style="font-size:0.7rem; color:#6b7280; font-weight:500; text-transform:uppercase; letter-spacing:0.06em;">NG Rate</span>
            </div>
            <div class="ngp-num" id="numRate" style="color:<?= $ngRate > 5 ? '#f87171' : ($ngRate > 2 ? '#fbbf24' : '#34d399') ?>;"><?= number_format($ngRate, 2) ?>%</div>
            <p style="font-size:0.72rem; color:#4b5563; margin-top:8px;">
                <?php if ($ngRate > 5): ?>
                    <span class="ngp-badge ngp-badge-danger">KRITIS</span>
                <?php elseif ($ngRate > 2): ?>
                    <span class="ngp-badge ngp-badge-warn">WASPADA</span>
                <?php else: ?>
                    <span class="ngp-badge ngp-badge-ok">NORMAL</span>
                <?php endif; ?>
            </p>
        </div>

        <div class="ngp-card ngp-stat ngp-danger ngp-anim ngp-d4" style="padding:20px;">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                <div style="width:32px; height:32px; border-radius:8px; background:rgba(239,68,68,0.1); display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-x-circle" style="color:#f87171; font-size:0.85rem;"></i>
                </div>
                <span style="font-size:0.7rem; color:#6b7280; font-weight:500; text-transform:uppercase; letter-spacing:0.06em;">Total NGP Qty</span>
            </div>
            <div class="ngp-num" style="color:#f87171;" id="numNgp"><?= number_format($totalNGP, 0) ?></div>
            <p style="font-size:0.72rem; color:#4b5563; margin-top:8px;">dari <?= number_format($partCount, 0) ?> part number</p>
        </div>
    </div>

    <!-- Charts Row 1: Pie + Bar -->
    <div style="display:grid; grid-template-columns: 1fr 1.6fr; gap:16px; margin-bottom:20px;">
        <!-- Pie Chart -->
        <div class="ngp-card ngp-anim ngp-d5" style="padding:20px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
                <div>
                    <h3 style="margin:0; font-size:0.875rem; font-weight:600; color:#e5e7eb;">Distribusi NGP Qty</h3>
                    <p style="margin:2px 0 0; font-size:0.75rem; color:#6b7280;">Per Part Number</p>
                </div>
                <div style="width:32px; height:32px; border-radius:8px; background:rgba(245,158,11,0.1); display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-pie-chart" style="color:#f59e0b; font-size:0.85rem;"></i>
                </div>
            </div>
            <div style="position:relative; height:320px;">
                <canvas id="ngpPieChart"></canvas>
            </div>
        </div>

        <!-- Bar Chart -->
        <div class="ngp-card ngp-anim ngp-d6" style="padding:20px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
                <div>
                    <h3 style="margin:0; font-size:0.875rem; font-weight:600; color:#e5e7eb;">NGP Qty per Part Number</h3>
                    <p style="margin:2px 0 0; font-size:0.75rem; color:#6b7280;">Diurutkan dari terbesar</p>
                </div>
                <div style="width:32px; height:32px; border-radius:8px; background:rgba(245,158,11,0.1); display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-bar-chart" style="color:#f59e0b; font-size:0.85rem;"></i>
                </div>
            </div>
            <div style="position:relative; height:320px;">
                <canvas id="ngpBarChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Trend Chart -->
    <div class="ngp-card ngp-anim ngp-d6" style="padding:20px; margin-bottom:20px;">
        <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:8px; margin-bottom:16px;">
            <div>
                <h3 style="margin:0; font-size:0.875rem; font-weight:600; color:#e5e7eb;">Trend NGP Qty Harian</h3>
                <p style="margin:2px 0 0; font-size:0.75rem; color:#6b7280;">
                    Part terbesar: <span style="font-family:'JetBrains Mono',monospace; color:#f59e0b;"><?= htmlspecialchars($topPartNo) ?></span>
                    <span style="color:#374151; margin:0 6px;">—</span>
                    <?= htmlspecialchars($topPartName) ?>
                </p>
            </div>
            <div style="display:flex; align-items:center; gap:16px;">
                <div style="display:flex; align-items:center; gap:6px;">
                    <span style="width:14px; height:2px; background:#f59e0b; border-radius:1px; display:block;"></span>
                    <span style="font-size:0.72rem; color:#6b7280;">NGP Qty</span>
                </div>
                <div style="display:flex; align-items:center; gap:6px;">
                    <span style="width:14px; height:14px; background:rgba(245,158,11,0.15); border:1px solid rgba(245,158,11,0.35); border-radius:3px; display:block;"></span>
                    <span style="font-size:0.72rem; color:#6b7280;">Area</span>
                </div>
            </div>
        </div>
        <div style="position:relative; height:260px;">
            <canvas id="ngpTrendChart"></canvas>
        </div>
    </div>

    <!-- NG Type Distribution -->
    <?php if (count($ngTypeLabels) > 0): ?>
    <div class="ngp-card ngp-anim ngp-d7" style="padding:20px; margin-bottom:20px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
            <div>
                <h3 style="margin:0; font-size:0.875rem; font-weight:600; color:#e5e7eb;">Distribusi Tipe NG</h3>
                <p style="margin:2px 0 0; font-size:0.75rem; color:#6b7280;">Berdasarkan NGT Code &amp; Deskripsi</p>
            </div>
            <div style="width:32px; height:32px; border-radius:8px; background:rgba(239,68,68,0.1); display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-bug" style="color:#f87171; font-size:0.85rem;"></i>
            </div>
        </div>
        <div style="position:relative; height:240px;">
            <canvas id="ngpTypeChart"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabel Detail -->
    <div class="ngp-card ngp-anim ngp-d7" style="overflow:hidden;">
        <div style="padding:20px 20px 12px; display:flex; align-items:center; justify-content:space-between;">
            <div>
                <h3 style="margin:0; font-size:0.875rem; font-weight:600; color:#e5e7eb;">Detail Data per Part Number</h3>
                <p style="margin:2px 0 0; font-size:0.75rem; color:#6b7280;"><?= number_format(count($allRows), 0) ?> baris data mentah</p>
            </div>
            <div style="width:32px; height:32px; border-radius:8px; background:rgba(245,158,11,0.1); display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-table" style="color:#f59e0b; font-size:0.85rem;"></i>
            </div>
        </div>
        <div class="ngp-scroll" style="overflow-x:auto; max-height:400px; overflow-y:auto;">
            <table class="ngp-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Part Number</th>
                        <th>Part Name</th>
                        <th style="text-align:right;">PD Qty</th>
                        <th style="text-align:right;">NG</th>
                        <th style="text-align:right;">NGP Qty</th>
                        <th>NG Type</th>
                        <th>NG Deskripsi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allRows)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center; color:#6b7280; padding:48px 16px;">
                            <i class="bi bi-inbox" style="font-size:2rem; color:#374151; display:block; margin-bottom:10px;"></i>
                            Tidak ada data untuk periode ini
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($allRows as $r): ?>
                        <tr>
                            <td style="color:#6b7280; font-family:'JetBrains Mono',monospace; font-size:0.75rem;"><?= $no++ ?></td>
                            <td style="font-family:'JetBrains Mono',monospace; color:rgba(245,158,11,0.8); font-size:0.78rem;"><?= htmlspecialchars($r['PART_NO']) ?></td>
                            <td style="color:#d1d5db; font-size:0.78rem; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($r['PART_NAME']) ?>"><?= htmlspecialchars($r['PART_NAME']) ?></td>
                            <td style="text-align:right; font-family:'JetBrains Mono',monospace; color:#d1d5db;"><?= number_format((float)$r['PD_QTY'], 0) ?></td>
                            <td style="text-align:right; font-family:'JetBrains Mono',monospace; color:#f87171;"><?= number_format((float)$r['NG'], 0) ?></td>
                            <td style="text-align:right; font-family:'JetBrains Mono',monospace; color:#f87171; font-weight:600;"><?= number_format((float)$r['NGP_QTY'], 0) ?></td>
                            <td><span class="ngp-badge ngp-badge-warn"><?= htmlspecialchars($r['NGT_CODE']) ?></span></td>
                            <td style="color:#9ca3af; font-size:0.78rem; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($r['NGT_DESC']) ?>"><?= htmlspecialchars($r['NGT_DESC']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Footer -->
    <div style="margin-top:24px; padding-bottom:16px; text-align:center; font-size:0.72rem; color:#374151;">
        NG Production Dashboard — Data: <?= htmlspecialchars($start_date) ?> s/d <?= htmlspecialchars($end_date) ?>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

<script>
(function() {
    'use strict';

    /* ── Konfigurasi global Chart.js ── */
    Chart.defaults.color = '#6b7280';
    Chart.defaults.font.family = "'Space Grotesk', sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.plugins.legend.labels.padding = 14;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.pointStyleWidth = 10;
    Chart.defaults.scale.grid = { color: 'rgba(37,43,59,0.6)', lineWidth: 1 };

    /* ── Data dari PHP ── */
    var pieLabels   = <?= json_encode($pieLabels) ?>;
    var pieValues   = <?= json_encode($pieValues) ?>;
    var barLabels   = <?= json_encode($barLabels) ?>;
    var barValues   = <?= json_encode($barValues) ?>;
    var barColors   = <?= json_encode($barColors) ?>;
    var trendLabels = <?= json_encode($trendLabels) ?>;
    var trendValues = <?= json_encode($trendValues) ?>;
    var ngTypeLabels = <?= json_encode($ngTypeLabels) ?>;
    var ngTypeValues = <?= json_encode($ngTypeValues) ?>;

    var palette = [
        '#f59e0b','#ef4444','#10b981','#3b82f6','#8b5cf6',
        '#ec4899','#14b8a6','#f97316','#06b6d4','#84cc16',
        '#e11d48','#6366f1','#22d3ee','#a3e635','#fb923c',
        '#c084fc','#fbbf24','#34d399','#60a5fa','#f472b6'
    ];

    /* Tooltip style bersama */
    var tooltipStyle = {
        backgroundColor: '#1c2130',
        borderColor: '#252b3b',
        borderWidth: 1,
        titleFont: { weight: '600' },
        padding: 12,
        cornerRadius: 8
    };

    /* ── PIE / DOUGHNUT CHART ── */
    new Chart(document.getElementById('ngpPieChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: pieLabels,
            datasets: [{
                data: pieValues,
                backgroundColor: barColors,
                borderColor: '#161a24',
                borderWidth: 2,
                hoverBorderColor: '#f59e0b',
                hoverBorderWidth: 2,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '55%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 8, boxHeight: 8, padding: 10, font: { size: 10 } }
                },
                tooltip: Object.assign({}, tooltipStyle, {
                    callbacks: {
                        label: function(ctx) {
                            var total = ctx.dataset.data.reduce(function(a,b){ return a+b; }, 0);
                            var pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                            return ' ' + ctx.label + ': ' + ctx.parsed.toLocaleString() + ' (' + pct + '%)';
                        }
                    }
                })
            },
            animation: { animateRotate: true, duration: 1200, easing: 'easeOutQuart' }
        }
    });

    /* ── BAR CHART (Horizontal) ── */
    new Chart(document.getElementById('ngpBarChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: barLabels,
            datasets: [{
                label: 'NGP Qty',
                data: barValues,
                backgroundColor: barColors.map(function(c){ return c + '99'; }),
                borderColor: barColors,
                borderWidth: 1,
                borderRadius: 4,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: Object.assign({}, tooltipStyle, {
                    callbacks: {
                        title: function(items) { return items[0].label; },
                        label: function(ctx) { return ' NGP Qty: ' + ctx.parsed.x.toLocaleString(); }
                    }
                })
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { font: { family: "'JetBrains Mono', monospace", size: 10 } },
                    title: { display: true, text: 'NGP Qty', font: { size: 11, weight: '500' }, color: '#6b7280' }
                },
                y: {
                    ticks: {
                        font: { family: "'JetBrains Mono', monospace", size: 9 },
                        callback: function(val) {
                            var lbl = this.getLabelForValue(val);
                            return lbl.length > 22 ? lbl.substring(0, 22) + '...' : lbl;
                        }
                    },
                    grid: { display: false }
                }
            },
            animation: { duration: 1000, easing: 'easeOutCubic' }
        }
    });

    /* ── TREND LINE CHART ── */
    if (trendLabels.length > 0) {
        var tCtx = document.getElementById('ngpTrendChart').getContext('2d');
        var grad = tCtx.createLinearGradient(0, 0, 0, 250);
        grad.addColorStop(0, 'rgba(245,158,11,0.25)');
        grad.addColorStop(0.5, 'rgba(245,158,11,0.08)');
        grad.addColorStop(1, 'rgba(245,158,11,0.0)');

        new Chart(tCtx, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'NGP Qty',
                    data: trendValues,
                    borderColor: '#f59e0b',
                    backgroundColor: grad,
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#f59e0b',
                    pointBorderColor: '#161a24',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: '#fbbf24',
                    pointHoverBorderColor: '#f59e0b',
                    pointHoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: Object.assign({}, tooltipStyle, {
                        displayColors: false,
                        callbacks: {
                            title: function(items) { return 'Tanggal: ' + items[0].label; },
                            label: function(ctx) { return 'NGP Qty: ' + ctx.parsed.y.toLocaleString(); }
                        }
                    })
                },
                scales: {
                    x: {
                        ticks: { font: { family: "'JetBrains Mono', monospace", size: 9 }, maxRotation: 45, autoSkip: true, maxTicksLimit: 15 },
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            font: { family: "'JetBrains Mono', monospace", size: 10 },
                            stepSize: Math.max(1, Math.ceil(Math.max.apply(null, trendValues) / 5))
                        }
                    }
                },
                animation: { duration: 1500, easing: 'easeOutQuart' }
            }
        });
    } else {
        document.getElementById('ngpTrendChart').parentElement.innerHTML =
            '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6b7280;">' +
            '<div style="text-align:center;"><i class="bi bi-graph-up" style="font-size:2rem;color:#374151;display:block;margin-bottom:10px;"></i>' +
            '<p style="font-size:0.85rem;">Tidak ada data trend untuk periode ini</p></div></div>';
    }

    /* ── NG TYPE BAR CHART ── */
    if (ngTypeLabels.length > 0) {
        var ngCols = ngTypeLabels.map(function(_, i) { return palette[i % palette.length]; });
        new Chart(document.getElementById('ngpTypeChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: ngTypeLabels,
                datasets: [{
                    label: 'NGP Qty',
                    data: ngTypeValues,
                    backgroundColor: ngCols.map(function(c){ return c + '80'; }),
                    borderColor: ngCols,
                    borderWidth: 1,
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: Object.assign({}, tooltipStyle, {
                        callbacks: {
                            label: function(ctx) { return ' NGP Qty: ' + ctx.parsed.y.toLocaleString(); }
                        }
                    })
                },
                scales: {
                    x: {
                        ticks: { font: { size: 9 }, maxRotation: 45, autoSkip: false },
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { font: { family: "'JetBrains Mono', monospace", size: 10 } }
                    }
                },
                animation: { duration: 1200, easing: 'easeOutCubic' }
            }
        });
    }

    /* ── Animasi counter angka ── */
    function animateNum(el, target, suffix) {
        suffix = suffix || '';
        var dur = 1200, start = null;
        function step(ts) {
            if (!start) start = ts;
            var p = Math.min((ts - start) / dur, 1);
            var e = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.floor(e * target).toLocaleString() + suffix;
            if (p < 1) requestAnimationFrame(step);
            else el.textContent = target.toLocaleString() + suffix;
        }
        requestAnimationFrame(step);
    }

    animateNum(document.getElementById('numPd'), <?= (int)$totalPD ?>);
    animateNum(document.getElementById('numNg'), <?= (int)$totalNG ?>);
    animateNum(document.getElementById('numNgp'), <?= (int)$totalNGP ?>);

    /* Animasi NG Rate */
    (function() {
        var target = <?= $ngRate ?>, dur = 1200, start = null, el = document.getElementById('numRate');
        function step(ts) {
            if (!start) start = ts;
            var p = Math.min((ts - start) / dur, 1);
            var e = 1 - Math.pow(1 - p, 3);
            el.textContent = (e * target).toFixed(2) + '%';
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    })();

})();
</script>
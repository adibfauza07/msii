<?php
/* ============================================================
   ng_prod.php — Dashboard NG Production (Plant 1)
   Simpan di: qc_p1/ng_prod.php
   ============================================================ */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ── Koneksi Database ─────────────────────────────────────────
require_once '../config/Database_p1.php';

// Deteksi nama variabel koneksi secara otomatis
 $dbConn = null;
foreach (array('conn','db','koneksi','connection','dbConn','link') as $v) {
    if (isset($$v) && (is_resource($$v) || is_object($$v))) {
        $dbConn = $$v;
        break;
    }
}

if (!$dbConn) {
    echo '<div style="background:#1a1a2e;color:#f87171;padding:20px;font-family:monospace;font-size:14px;border:2px solid #ef4444;border-radius:8px;margin:10px;">';
    echo '<p style="margin:0 0 6px;"><b style="color:#fbbf24;">ERROR:</b> Variabel koneksi tidak ditemukan di Database_p1.php</p>';
    echo '<p style="margin:0;color:#9ca3af;">Variabel yang tersedia:</p><pre style="margin:6px 0 0;color:#34d399;background:#0b0d11;padding:12px;border-radius:6px;overflow-x:auto;font-size:12px;">';
    foreach (get_defined_vars() as $k => $val) {
        if ($k === 'dbConn') continue;
        echo $k . ' = ' . gettype($val) . "\n";
    }
    echo '</pre></div>';
    return;
}

// ── Parameter Tanggal ────────────────────────────────────────
 $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
 $end_date   = isset($_POST['end_date'])   ? $_POST['end_date']   : date('Y-m-d');
 $cust_id    = 275;

// ── Query Utama ──────────────────────────────────────────────
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
 $stmtMain = sqlsrv_query($dbConn, $sqlMain, array($start_date, $end_date, $cust_id));

if ($stmtMain === false) {
    $err = sqlsrv_errors();
    echo '<div style="background:#1a1a2e;color:#f87171;padding:20px;font-family:monospace;font-size:13px;border:2px solid #ef4444;border-radius:8px;margin:10px;">';
    echo '<b style="color:#fbbf24;">QUERY ERROR:</b><br>';
    echo 'SQLSTATE: ' . htmlspecialchars($err[0]['SQLSTATE']) . '<br>';
    echo 'Kode: ' . htmlspecialchars($err[0]['code']) . '<br>';
    echo 'Pesan: ' . htmlspecialchars($err[0]['message']);
    echo '</div>';
    return;
}

// ── Agregasi data ────────────────────────────────────────────
 $partData = array();
 $allRows  = array();
 $totalPD = $totalNG = $totalNGP = 0;
 $custInfo = array("code" => "", "comp" => "");

while ($row = sqlsrv_fetch_array($stmtMain, SQLSRV_FETCH_ASSOC)) {
    $allRows[] = $row;
    $pn = $row['PART_NO'];
    $totalPD  += (float)$row['PD_QTY'];
    $totalNG  += (float)$row['NG'];
    $totalNGP += (float)$row['NGP_QTY'];
    $custInfo["code"] = $row['CUST_CODE'];
    $custInfo["comp"] = $row['CUST_COMP'];

    if (!isset($partData[$pn])) {
        $partData[$pn] = array("part_name"=>$row['PART_NAME'],"total_ngp"=>0,"total_pd"=>0,"total_ng"=>0,"ng_types"=>array());
    }
    $partData[$pn]["total_ngp"] += (float)$row['NGP_QTY'];
    $partData[$pn]["total_pd"]  += (float)$row['PD_QTY'];
    $partData[$pn]["total_ng"]  += (float)$row['NG'];

    $ngtKey = $row['NGT_CODE'] . ' - ' . $row['NGT_DESC'];
    if (!isset($partData[$pn]["ng_types"][$ngtKey])) $partData[$pn]["ng_types"][$ngtKey] = 0;
    $partData[$pn]["ng_types"][$ngtKey] += (float)$row['NGP_QTY'];
}

uasort($partData, function($a,$b){ return $b['total_ngp'] - $a['total_ngp']; });
 $topPartNo   = !empty($partData) ? key($partData) : '';
 $topPartName = isset($partData[$topPartNo]) ? $partData[$topPartNo]['part_name'] : '-';

// ── Query Trend ──────────────────────────────────────────────
 $trendLabels = $trendValues = array();
if ($topPartNo) {
    $sqlTrend = "
        SELECT CONVERT(VARCHAR(10), dbo.PRODUCTION.PD_DATE, 23) AS TANGGAL,
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
    $stmtT = sqlsrv_query($dbConn, $sqlTrend, array($start_date, $end_date, $cust_id, $topPartNo));
    if ($stmtT) {
        while ($tr = sqlsrv_fetch_array($stmtT, SQLSRV_FETCH_ASSOC)) {
            $trendLabels[] = $tr['TANGGAL'];
            $trendValues[] = (float)$tr['NGP_QTY'];
        }
    }
}

// ── Siapkan data Chart ───────────────────────────────────────
 $pieLabels = $barLabels = $pieValues = $barValues = $barColors = array();
 $pal = array('#f59e0b','#ef4444','#10b981','#3b82f6','#8b5cf6','#ec4899','#14b8a6','#f97316','#06b6d4','#84cc16','#e11d48','#6366f1','#22d3ee','#a3e635','#fb923c','#c084fc','#fbbf24','#34d399','#60a5fa','#f472b6');
 $j = 0;
foreach ($partData as $pn => $info) {
    $pieLabels[] = strlen($pn) > 18 ? substr($pn,0,18).'…' : $pn;
    $pieValues[] = $info['total_ngp'];
    $barLabels[] = $pn;
    $barValues[] = $info['total_ngp'];
    $barColors[] = $pal[$j++ % count($pal)];
}
 $ngRate    = $totalPD > 0 ? round(($totalNG/$totalPD)*100, 2) : 0;
 $partCount = count($partData);

 $ngTypeData = array();
foreach ($partData as $pn => $info) {
    foreach ($info['ng_types'] as $name => $qty) {
        if (!isset($ngTypeData[$name])) $ngTypeData[$name] = 0;
        $ngTypeData[$name] += $qty;
    }
}
arsort($ngTypeData);
 $ngTypeLabels = array_keys($ngTypeData);
 $ngTypeValues = array_values($ngTypeData);
?>

<!-- ========== NG PROD DASHBOARD START ========== -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ── Scope semua style dengan #ngp-dash agar tidak kena sidebar ── */
#ngp-dash{font-family:'Space Grotesk',sans-serif;color:#e5e7eb;max-width:1440px;position:relative;z-index:1}
#ngp-dash *{box-sizing:border-box}

/* Background */
.ngp-wrap{background:#0b0d11;background-image:linear-gradient(rgba(245,158,11,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(245,158,11,.03) 1px,transparent 1px);background-size:40px 40px;padding:4px;min-height:100vh}
.ngp-glow{position:fixed;pointer-events:none;z-index:0}
.ngp-glow-1{top:-200px;right:-200px;width:600px;height:600px;background:radial-gradient(circle,rgba(245,158,11,.06) 0%,transparent 70%)}
.ngp-glow-2{bottom:-300px;left:-200px;width:700px;height:700px;background:radial-gradient(circle,rgba(239,68,68,.04) 0%,transparent 70%)}

/* Card */
.nc{background:#161a24;border:1px solid #252b3b;border-radius:12px;transition:all .3s ease}
.nc:hover{border-color:rgba(245,158,11,.3);box-shadow:0 0 30px rgba(245,158,11,.05)}

/* Stat */
.ns{position:relative;overflow:hidden}
.ns::before{content:'';position:absolute;top:0;left:0;width:100%;height:3px;opacity:0;transition:opacity .3s}
.ns:hover::before{opacity:1}
.ns.ns-a::before{background:linear-gradient(90deg,transparent,#f59e0b,transparent)}
.ns.ns-d::before{background:linear-gradient(90deg,transparent,#ef4444,transparent)}
.ns.ns-s::before{background:linear-gradient(90deg,transparent,#10b981,transparent)}

/* Number */
.nn{font-family:'JetBrains Mono',monospace;font-weight:600;font-size:1.7rem;line-height:1}

/* Input */
.ndi{background:#1c2130;border:1px solid #252b3b;color:#e5e7eb;padding:8px 14px;border-radius:8px;font-family:'JetBrains Mono',monospace;font-size:.85rem;outline:none;transition:border-color .2s}
.ndi:focus{border-color:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.15)}
.ndi::-webkit-calendar-picker-indicator{filter:invert(.7);cursor:pointer}

/* Button */
.nbtn{background:linear-gradient(135deg,#f59e0b,#d97706);color:#0b0d11;font-weight:600;padding:8px 22px;border-radius:8px;border:none;cursor:pointer;transition:all .2s;font-family:'Space Grotesk',sans-serif;font-size:.9rem}
.nbtn:hover{transform:translateY(-1px);box-shadow:0 4px 20px rgba(245,158,11,.3)}
.nbtn:active{transform:translateY(0)}

/* Badge */
.nbg{display:inline-block;padding:2px 10px;border-radius:9999px;font-size:.7rem;font-weight:600;letter-spacing:.03em}
.nbg-d{background:rgba(239,68,68,.15);color:#f87171}
.nbg-w{background:rgba(245,158,11,.15);color:#fbbf24}
.nbg-o{background:rgba(16,185,129,.15);color:#34d399}

/* Table */
.ntb{width:100%;border-collapse:separate;border-spacing:0}
.ntb thead th{background:#1c2130;padding:12px 14px;text-align:left;font-weight:600;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;border-bottom:1px solid #252b3b;position:sticky;top:0;z-index:2}
.ntb thead th:first-child{border-radius:8px 0 0 0}
.ntb thead th:last-child{border-radius:0 8px 0 0}
.ntb tbody tr{transition:background .2s}
.ntb tbody tr:hover{background:rgba(245,158,11,.04)}
.ntb tbody td{padding:10px 14px;border-bottom:1px solid rgba(37,43,59,.5);font-size:.82rem}
.ntb tbody tr:last-child td{border-bottom:none}

/* Pulse */
.npls{width:8px;height:8px;border-radius:50%;background:#f59e0b;display:inline-block;position:relative}
.npls::after{content:'';position:absolute;inset:-4px;border-radius:50%;border:2px solid #f59e0b;animation:npls-r 2s ease infinite}
@keyframes npls-r{0%{transform:scale(.8);opacity:.6}100%{transform:scale(1.8);opacity:0}}

/* Scrollbar */
.nsr::-webkit-scrollbar{width:6px;height:6px}
.nsr::-webkit-scrollbar-track{background:#0b0d11}
.nsr::-webkit-scrollbar-thumb{background:#252b3b;border-radius:3px}
.nsr::-webkit-scrollbar-thumb:hover{background:#3a4155}

/* Anim */
@keyframes nfu{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.na{animation:nfu .5s ease forwards;opacity:0}
.nd1{animation-delay:.05s}.nd2{animation-delay:.1s}.nd3{animation-delay:.15s}.nd4{animation-delay:.2s}.nd5{animation-delay:.3s}.nd6{animation-delay:.4s}.nd7{animation-delay:.5s}

/* Grid helpers */
.g4{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:20px}
.g-pie-bar{display:grid;grid-template-columns:2fr 3fr;gap:16px;margin-bottom:20px}

/* Ikon box */
.ibox{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ibox-a{background:rgba(245,158,11,.1)}
.ibox-a i{color:#f59e0b}
.ibox-d{background:rgba(239,68,68,.1)}
.ibox-d i{color:#f87171}

/* Section title */
.stitle{font-size:.875rem;font-weight:600;color:#e5e7eb;margin:0}
.ssub{font-size:.75rem;color:#6b7280;margin:2px 0 0}

/* Mono */
.mono{font-family:'JetBrains Mono',monospace}

@media(max-width:900px){
    .g-pie-bar{grid-template-columns:1fr}
}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important}}
</style>

<div class="ngp-wrap">
<div class="ngp-glow ngp-glow-1"></div>
<div class="ngp-glow ngp-glow-2"></div>

<div id="ngp-dash">

    <!-- ── HEADER ── -->
    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;" class="na nd1">
        <div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
                <span class="npls"></span>
                <span class="mono" style="color:#f59e0b;font-size:.7rem;letter-spacing:.15em;text-transform:uppercase;">Live Monitoring</span>
            </div>
            <h2 style="margin:0;font-size:1.4rem;font-weight:700;color:#f0f0f0;">
                NG Production <span style="color:#f59e0b;">Dashboard</span>
                <span style="font-size:.72rem;color:#6b7280;font-weight:400;margin-left:8px;">Plant 1</span>
            </h2>
            <p style="font-size:.82rem;color:#6b7280;margin-top:4px;">
                Customer: <span style="color:#d1d5db;font-weight:500;"><?= htmlspecialchars($custInfo['comp'] ?: '-') ?></span>
                <span style="margin:0 8px;color:#374151;">|</span>
                Code: <span class="mono" style="color:#d1d5db;"><?= htmlspecialchars($custInfo['code'] ?: '-') ?></span>
            </p>
        </div>
        <form method="POST" action="?page=ng_prod" style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:10px;">
            <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:.72rem;color:#6b7280;font-weight:500;">Start Date</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="ndi">
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:.72rem;color:#6b7280;font-weight:500;">End Date</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="ndi">
            </div>
            <button type="submit" class="nbtn"><i class="bi bi-funnel"></i> Terapkan</button>
        </form>
    </div>

    <!-- ── SUMMARY CARDS ── -->
    <div class="g4">
        <div class="nc ns ns-a na nd1" style="padding:20px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                <div class="ibox ibox-a"><i class="bi bi-box-seam" style="font-size:.85rem;"></i></div>
                <span style="font-size:.7rem;color:#6b7280;font-weight:500;text-transform:uppercase;letter-spacing:.06em;">Total Production</span>
            </div>
            <div class="nn" style="color:#e5e7eb;" id="numPd"><?= number_format($totalPD,0) ?></div>
            <p style="font-size:.72rem;color:#4b5563;margin-top:8px;">Unit diproduksi</p>
        </div>

        <div class="nc ns ns-d na nd2" style="padding:20px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                <div class="ibox ibox-d"><i class="bi bi-exclamation-triangle" style="font-size:.85rem;"></i></div>
                <span style="font-size:.7rem;color:#6b7280;font-weight:500;text-transform:uppercase;letter-spacing:.06em;">Total NG</span>
            </div>
            <div class="nn" style="color:#f87171;" id="numNg"><?= number_format($totalNG,0) ?></div>
            <p style="font-size:.72rem;color:#4b5563;margin-top:8px;">Unit tidak lolos QC</p>
        </div>

        <div class="nc ns ns-a na nd3" style="padding:20px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                <div class="ibox ibox-a"><i class="bi bi-percent" style="font-size:.85rem;"></i></div>
                <span style="font-size:.7rem;color:#6b7280;font-weight:500;text-transform:uppercase;letter-spacing:.06em;">NG Rate</span>
            </div>
            <div class="nn" id="numRate" style="color:<?= $ngRate>5?'#f87171':($ngRate>2?'#fbbf24':'#34d399') ?>;"><?= number_format($ngRate,2) ?>%</div>
            <p style="font-size:.72rem;color:#4b5563;margin-top:8px;">
                <?php if($ngRate>5):?><span class="nbg nbg-d">KRITIS</span><?php elseif($ngRate>2):?><span class="nbg nbg-w">WASPADA</span><?php else:?><span class="nbg nbg-o">NORMAL</span><?php endif;?>
            </p>
        </div>

        <div class="nc ns ns-d na nd4" style="padding:20px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                <div class="ibox ibox-d"><i class="bi bi-x-circle" style="font-size:.85rem;"></i></div>
                <span style="font-size:.7rem;color:#6b7280;font-weight:500;text-transform:uppercase;letter-spacing:.06em;">Total NGP Qty</span>
            </div>
            <div class="nn" style="color:#f87171;" id="numNgp"><?= number_format($totalNGP,0) ?></div>
            <p style="font-size:.72rem;color:#4b5563;margin-top:8px;">dari <?= number_format($partCount,0) ?> part number</p>
        </div>
    </div>

    <!-- ── PIE + BAR ── -->
    <div class="g-pie-bar">
        <div class="nc na nd5" style="padding:20px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <div><p class="stitle">Distribusi NGP Qty</p><p class="ssub">Per Part Number</p></div>
                <div class="ibox ibox-a"><i class="bi bi-pie-chart" style="font-size:.85rem;"></i></div>
            </div>
            <div style="position:relative;height:320px;"><canvas id="ngpPie"></canvas></div>
        </div>
        <div class="nc na nd6" style="padding:20px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <div><p class="stitle">NGP Qty per Part Number</p><p class="ssub">Diurutkan dari terbesar</p></div>
                <div class="ibox ibox-a"><i class="bi bi-bar-chart" style="font-size:.85rem;"></i></div>
            </div>
            <div style="position:relative;height:320px;"><canvas id="ngpBar"></canvas></div>
        </div>
    </div>

    <!-- ── TREND ── -->
    <div class="nc na nd6" style="padding:20px;margin-bottom:20px;">
        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;margin-bottom:16px;">
            <div>
                <p class="stitle">Trend NGP Qty Harian</p>
                <p class="ssub">Part terbesar: <span class="mono" style="color:#f59e0b;"><?= htmlspecialchars($topPartNo) ?></span> <span style="color:#374151;margin:0 6px;">—</span> <?= htmlspecialchars($topPartName) ?></p>
            </div>
            <div style="display:flex;align-items:center;gap:16px;">
                <div style="display:flex;align-items:center;gap:6px;"><span style="width:14px;height:2px;background:#f59e0b;border-radius:1px;display:block;"></span><span style="font-size:.72rem;color:#6b7280;">NGP Qty</span></div>
                <div style="display:flex;align-items:center;gap:6px;"><span style="width:14px;height:14px;background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.35);border-radius:3px;display:block;"></span><span style="font-size:.72rem;color:#6b7280;">Area</span></div>
            </div>
        </div>
        <div id="ngpTrendWrap" style="position:relative;height:260px;"><canvas id="ngpTrend"></canvas></div>
    </div>

    <!-- ── NG TYPE ── -->
    <?php if(count($ngTypeLabels)>0): ?>
    <div class="nc na nd7" style="padding:20px;margin-bottom:20px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <div><p class="stitle">Distribusi Tipe NG</p><p class="ssub">Berdasarkan NGT Code &amp; Deskripsi</p></div>
            <div class="ibox ibox-d"><i class="bi bi-bug" style="font-size:.85rem;"></i></div>
        </div>
        <div style="position:relative;height:240px;"><canvas id="ngpType"></canvas></div>
    </div>
    <?php endif; ?>

    <!-- ── TABEL ── -->
    <div class="nc na nd7" style="overflow:hidden;">
        <div style="padding:20px 20px 12px;display:flex;align-items:center;justify-content:space-between;">
            <div><p class="stitle">Detail Data per Part Number</p><p class="ssub"><?= number_format(count($allRows),0) ?> baris data mentah</p></div>
            <div class="ibox ibox-a"><i class="bi bi-table" style="font-size:.85rem;"></i></div>
        </div>
        <div class="nsr" style="overflow-x:auto;max-height:400px;overflow-y:auto;">
            <table class="ntb">
                <thead><tr>
                    <th>No</th><th>Part Number</th><th>Part Name</th>
                    <th style="text-align:right;">PD Qty</th><th style="text-align:right;">NG</th>
                    <th style="text-align:right;">NGP Qty</th><th>NG Type</th><th>NG Deskripsi</th>
                </tr></thead>
                <tbody>
                <?php if(empty($allRows)): ?>
                <tr><td colspan="8" style="text-align:center;color:#6b7280;padding:48px 16px;">
                    <i class="bi bi-inbox" style="font-size:2rem;color:#374151;display:block;margin-bottom:10px;"></i>
                    Tidak ada data untuk periode ini
                </td></tr>
                <?php else: $no=1; foreach($allRows as $r): ?>
                <tr>
                    <td class="mono" style="color:#6b7280;font-size:.75rem;"><?= $no++ ?></td>
                    <td class="mono" style="color:rgba(245,158,11,.8);font-size:.78rem;"><?= htmlspecialchars($r['PART_NO']) ?></td>
                    <td style="color:#d1d5db;font-size:.78rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($r['PART_NAME']) ?>"><?= htmlspecialchars($r['PART_NAME']) ?></td>
                    <td class="mono" style="text-align:right;color:#d1d5db;"><?= number_format((float)$r['PD_QTY'],0) ?></td>
                    <td class="mono" style="text-align:right;color:#f87171;"><?= number_format((float)$r['NG'],0) ?></td>
                    <td class="mono" style="text-align:right;color:#f87171;font-weight:600;"><?= number_format((float)$r['NGP_QTY'],0) ?></td>
                    <td><span class="nbg nbg-w"><?= htmlspecialchars($r['NGT_CODE']) ?></span></td>
                    <td style="color:#9ca3af;font-size:.78rem;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($r['NGT_DESC']) ?>"><?= htmlspecialchars($r['NGT_DESC']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="margin-top:24px;padding-bottom:16px;text-align:center;font-size:.72rem;color:#374151;">
        NG Production Dashboard — Data: <?= htmlspecialchars($start_date) ?> s/d <?= htmlspecialchars($end_date) ?>
    </div>
</div>
</div>
<!-- ========== NG PROD DASHBOARD END ========== -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function(){
    'use strict';
    Chart.defaults.color='#6b7280';
    Chart.defaults.font.family="'Space Grotesk',sans-serif";
    Chart.defaults.font.size=11;
    Chart.defaults.plugins.legend.labels.padding=14;
    Chart.defaults.plugins.legend.labels.usePointStyle=true;
    Chart.defaults.plugins.legend.labels.pointStyleWidth=10;
    Chart.defaults.scale.grid={color:'rgba(37,43,59,0.6)',lineWidth:1};

    var pL=<?=json_encode($pieLabels)?>,pV=<?=json_encode($pieValues)?>;
    var bL=<?=json_encode($barLabels)?>,bV=<?=json_encode($barValues)?>,bC=<?=json_encode($barColors)?>;
    var tL=<?=json_encode($trendLabels)?>,tV=<?=json_encode($trendValues)?>;
    var nL=<?=json_encode($ngTypeLabels)?>,nV=<?=json_encode($ngTypeValues)?>;
    var pal=['#f59e0b','#ef4444','#10b981','#3b82f6','#8b5cf6','#ec4899','#14b8a6','#f97316','#06b6d4','#84cc16','#e11d48','#6366f1','#22d3ee','#a3e635','#fb923c'];
    var tt={backgroundColor:'#1c2130',borderColor:'#252b3b',borderWidth:1,titleFont:{weight:'600'},padding:12,cornerRadius:8};

    // PIE
    new Chart(document.getElementById('ngpPie').getContext('2d'),{
        type:'doughnut',
        data:{labels:pL,datasets:[{data:pV,backgroundColor:bC,borderColor:'#161a24',borderWidth:2,hoverBorderColor:'#f59e0b',hoverBorderWidth:2,hoverOffset:8}]},
        options:{responsive:true,maintainAspectRatio:false,cutout:'55%',
            plugins:{legend:{position:'bottom',labels:{boxWidth:8,boxHeight:8,padding:10,font:{size:10}}},
                tooltip:Object.assign({},tt,{callbacks:{label:function(c){var t=c.dataset.data.reduce(function(a,b){return a+b},0);var p=t>0?((c.parsed/t)*100).toFixed(1):0;return ' '+c.label+': '+c.parsed.toLocaleString()+' ('+p+'%)'}}})},
            animation:{animateRotate:true,duration:1200,easing:'easeOutQuart'}}
    });

    // BAR
    new Chart(document.getElementById('ngpBar').getContext('2d'),{
        type:'bar',
        data:{labels:bL,datasets:[{label:'NGP Qty',data:bV,backgroundColor:bC.map(function(c){return c+'99'}),borderColor:bC,borderWidth:1,borderRadius:4,borderSkipped:false}]},
        options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',
            plugins:{legend:{display:false},tooltip:Object.assign({},tt,{callbacks:{title:function(i){return i[0].label},label:function(c){return ' NGP Qty: '+c.parsed.x.toLocaleString()}}})},
            scales:{x:{beginAtZero:true,ticks:{font:{family:"'JetBrains Mono',monospace",size:10}},title:{display:true,text:'NGP Qty',font:{size:11,weight:'500'},color:'#6b7280'}},
                y:{ticks:{font:{family:"'JetBrains Mono',monospace",size:9},callback:function(v){var l=this.getLabelForValue(v);return l.length>22?l.substring(0,22)+'...':l}},grid:{display:false}}},
            animation:{duration:1000,easing:'easeOutCubic'}}
    });

    // TREND
    if(tL.length>0){
        var tc=document.getElementById('ngpTrend').getContext('2d');
        var gr=tc.createLinearGradient(0,0,0,250);
        gr.addColorStop(0,'rgba(245,158,11,0.25)');gr.addColorStop(0.5,'rgba(245,158,11,0.08)');gr.addColorStop(1,'rgba(245,158,11,0.0)');
        new Chart(tc,{type:'line',data:{labels:tL,datasets:[{label:'NGP Qty',data:tV,borderColor:'#f59e0b',backgroundColor:gr,borderWidth:2.5,fill:true,tension:0.35,pointBackgroundColor:'#f59e0b',pointBorderColor:'#161a24',pointBorderWidth:2,pointRadius:4,pointHoverRadius:7,pointHoverBackgroundColor:'#fbbf24',pointHoverBorderColor:'#f59e0b',pointHoverBorderWidth:2}]},
            options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},
                plugins:{legend:{display:false},tooltip:Object.assign({},tt,{displayColors:false,callbacks:{title:function(i){return 'Tanggal: '+i[0].label},label:function(c){return 'NGP Qty: '+c.parsed.y.toLocaleString()}}})},
                scales:{x:{ticks:{font:{family:"'JetBrains Mono',monospace",size:9},maxRotation:45,autoSkip:true,maxTicksLimit:15},grid:{display:false}},
                    y:{beginAtZero:true,ticks:{font:{family:"'JetBrains Mono',monospace",size:10},stepSize:Math.max(1,Math.ceil(Math.max.apply(null,tV)/5))}}},
                animation:{duration:1500,easing:'easeOutQuart'}}});
    } else {
        document.getElementById('ngpTrendWrap').innerHTML='<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6b7280;"><div style="text-align:center;"><i class="bi bi-graph-up" style="font-size:2rem;color:#374151;display:block;margin-bottom:10px;"></i><p style="font-size:.85rem;">Tidak ada data trend untuk periode ini</p></div></div>';
    }

    // NG TYPE
    if(nL.length>0){
        var nc=nL.map(function(_,i){return pal[i%pal.length]});
        new Chart(document.getElementById('ngpType').getContext('2d'),{type:'bar',data:{labels:nL,datasets:[{label:'NGP Qty',data:nV,backgroundColor:nc.map(function(c){return c+'80'}),borderColor:nc,borderWidth:1,borderRadius:6,borderSkipped:false}]},
            options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:Object.assign({},tt,{callbacks:{label:function(c){return ' NGP Qty: '+c.parsed.y.toLocaleString()}}})},
                scales:{x:{ticks:{font:{size:9},maxRotation:45,autoSkip:false},grid:{display:false}},y:{beginAtZero:true,ticks:{font:{family:"'JetBrains Mono',monospace",size:10}}}},
                animation:{duration:1200,easing:'easeOutCubic'}}});
    }

    // Animasi angka
    function aN(el,target,suf){suf=suf||'';var dur=1200,st=null;function step(ts){if(!st)st=ts;var p=Math.min((ts-st)/dur,1);var e=1-Math.pow(1-p,3);el.textContent=Math.floor(e*target).toLocaleString()+suf;if(p<1)requestAnimationFrame(step);else el.textContent=target.toLocaleString()+suf;}requestAnimationFrame(step);}
    aN(document.getElementById('numPd'),<?= (int)$totalPD ?>);
    aN(document.getElementById('numNg'),<?= (int)$totalNG ?>);
    aN(document.getElementById('numNgp'),<?= (int)$totalNGP ?>);
    (function(){var t=<?= $ngRate ?>,dur=1200,st=null,el=document.getElementById('numRate');function step(ts){if(!st)st=ts;var p=Math.min((ts-st)/dur,1);var e=1-Math.pow(1-p,3);el.textContent=(e*t).toFixed(2)+'%';if(p<1)requestAnimationFrame(step);}requestAnimationFrame(step);})();
})();
</script>
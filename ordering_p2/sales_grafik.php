<?php
session_start();
require_once __DIR__ . "/../config/database_ordering.php";

if (!$conn) {
    die('<div style="padding:24px;color:#F85149;background:#0D1117;font-family:monospace;">Koneksi database tidak tersedia. Silakan login terlebih dahulu.</div>');
}

// 1. Eksekusi Query untuk mendapatkan Nilai Total Per Tahun dan Per Bulan (Real Data)
$sql = "
WITH VendorItems AS (
    SELECT DISTINCT INV_TRAN.ITEM_ID
    FROM TRANS
    INNER JOIN INV_TRAN ON TRANS.TRAN_ID = INV_TRAN.TRAN_ID
    WHERE TRANS.TRTY_CODE = '12'
),
SalesData AS (
    SELECT
        ITEMS.ITEM_ID,
        YEAR(DI.DI_DATE) AS SALES_YEAR,
        MONTH(DI.DI_DATE) AS SALES_MONTH,
        DIPA_PAR.QTY AS QTY,
        DIPA_PAR.PART_PRICE AS PRICE,
        ISNULL(APV.CURR_CODE, 'IDR') AS CURR_CODE,
        ISNULL(RV.CURR_VRATE, 1) AS CURR_RATE
    FROM DI
    INNER JOIN DIPA_PAR ON DI.DI_ID = DIPA_PAR.DI_ID
    INNER JOIN PRICE ON DIPA_PAR.PRICE_ID = PRICE.PRICE_ID
    INNER JOIN ITEMS ON PRICE.PART_ID = ITEMS.ITEM_ID
    LEFT JOIN dbo.ACTIVE_PRICE_VIEW AS APV ON PRICE.PRICE_ID = APV.PRICE_ID
    LEFT JOIN dbo.TODAY_RATE_VIEW AS RV ON APV.CURR_CODE = RV.CURR_CODE
    WHERE DI.DI_DATE IS NOT NULL
)
SELECT
    s.SALES_YEAR,
    s.SALES_MONTH,
    SUM(s.QTY * s.PRICE * CASE WHEN s.CURR_CODE IN ('IDR', 'RP') THEN 1 ELSE s.CURR_RATE END) AS TOTAL_SALES,
    CASE WHEN v.ITEM_ID IS NOT NULL THEN 1 ELSE 0 END AS IS_VENDOR
FROM SalesData s
LEFT JOIN VendorItems v ON s.ITEM_ID = v.ITEM_ID
GROUP BY s.SALES_YEAR, s.SALES_MONTH, CASE WHEN v.ITEM_ID IS NOT NULL THEN 1 ELSE 0 END
ORDER BY s.SALES_YEAR ASC, s.SALES_MONTH ASC
";

$stmt = sqlsrv_query($conn, $sql);

$min_year = 9999;
$max_year = 0;
$raw_yearly = [];
$raw_monthly = [];

// Memproses Hasil Query
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $year = (int)$row['SALES_YEAR'];
        $month = (int)$row['SALES_MONTH'];
        $is_vendor = (int)$row['IS_VENDOR'];
        $sales = (float)$row['TOTAL_SALES'];

        if ($year < $min_year) $min_year = $year;
        if ($year > $max_year) $max_year = $year;

        if (!isset($raw_yearly[$year])) {
            $raw_yearly[$year] = ['year' => $year, 'vendor' => 0, 'internal' => 0];
            $raw_monthly[$year] = ['vendor' => array_fill(0, 12, 0), 'internal' => array_fill(0, 12, 0)];
        }

        if ($is_vendor === 1) {
            $raw_yearly[$year]['vendor'] += $sales;
            $raw_monthly[$year]['vendor'][$month - 1] += $sales;
        } else {
            $raw_yearly[$year]['internal'] += $sales;
            $raw_monthly[$year]['internal'][$month - 1] += $sales;
        }
    }
    sqlsrv_free_stmt($stmt);
}
sqlsrv_close($conn);

$yearly_data = [];
$monthly_data = [];

// 2. Isi rentang tahun kosong dengan nilai 0
if ($min_year <= $max_year) {
    for ($y = $min_year; $y <= $max_year; $y++) {
        if (isset($raw_yearly[$y])) {
            $yearly_data[] = $raw_yearly[$y];
            $monthly_data[$y] = $raw_monthly[$y];
        } else {
            $yearly_data[] = ['year' => $y, 'vendor' => 0, 'internal' => 0];
            $monthly_data[$y] = ['vendor' => array_fill(0, 12, 0), 'internal' => array_fill(0, 12, 0)];
        }
    }
}

$all_data_json = json_encode($yearly_data);
$monthly_data_json = json_encode($monthly_data);

$plant_label = isset($_SESSION['active_plant']) ? strtoupper($_SESSION['active_plant']) : 'P1';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grafik Tahunan + Bulanan — Internal vs Vendor</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --bg:#0D1117;--card:#161B22;--border:#30363D;--text:#E6EDF3;--muted:#8B949E;
            --vendor:#FF6B35;--vendor-dim:rgba(255,107,53,0.12);--vendor-glow:rgba(255,107,53,0.25);
            --internal:#00D4AA;--internal-dim:rgba(0,212,170,0.12);--internal-glow:rgba(0,212,170,0.25);
            --accent:#FFD93D;--accent-dim:rgba(255,217,61,0.10);--danger:#F85149;
            --r:12px;--rs:6px;
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;padding:24px 20px 60px;position:relative;overflow-x:hidden;}
        body::before{content:'';position:fixed;top:-250px;right:-150px;width:600px;height:600px;background:radial-gradient(circle,rgba(255,107,53,0.05) 0%,transparent 65%);pointer-events:none;animation:floatB 12s ease-in-out infinite alternate;}
        body::after{content:'';position:fixed;bottom:-250px;left:-150px;width:600px;height:600px;background:radial-gradient(circle,rgba(0,212,170,0.05) 0%,transparent 65%);pointer-events:none;animation:floatB 14s ease-in-out infinite alternate-reverse;}
        @keyframes floatB{0%{transform:translate(0,0) scale(1);}100%{transform:translate(30px,-20px) scale(1.08);}}

        .container{max-width:1100px;margin:0 auto;position:relative;z-index:1;opacity:0;transform:translateY(20px);animation:fadeUp .6s ease forwards;}
        @keyframes fadeUp{to{opacity:1;transform:translateY(0);}}

        .header{margin-bottom:16px;}
        .header-top{display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap;}
        .header h1{font-family:'Space Grotesk',sans-serif;font-size:22px;font-weight:700;letter-spacing:-.3px;}
        .header h1 i{color:var(--accent);margin-right:6px;font-size:18px;}
        .plant-badge{background:var(--accent-dim);color:var(--accent);font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;letter-spacing:.5px;}
        .header-sub{color:var(--muted);font-size:13px;}
        .header-sub b{color:var(--text);font-weight:600;}

        .filter-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:12px 18px;margin-bottom:12px;}
        .filter-bar label{font-size:12px;color:var(--muted);font-weight:600;white-space:nowrap;}
        .filter-bar select{background:var(--bg);border:1px solid var(--border);color:var(--text);padding:8px 32px 8px 12px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;outline:none;cursor:pointer;transition:border .2s;appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238B949E' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;}
        .filter-bar select:focus{border-color:var(--accent);}
        .sep{width:1px;height:24px;background:var(--border);margin:0 2px;}
        .range-hint{font-size:11px;color:var(--muted);display:flex;align-items:center;gap:5px;margin-left:auto;}
        .range-hint i{font-size:10px;}
        .range-hint .count-badge{background:var(--accent-dim);color:var(--accent);font-weight:700;padding:2px 8px;border-radius:10px;font-size:11px;font-family:'Space Grotesk',sans-serif;}
        .range-hint.error{color:var(--danger);}

        .preset-row{display:flex;align-items:center;gap:6px;margin-bottom:16px;flex-wrap:wrap;}
        .preset-row span{font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-right:4px;}
        .preset-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border-radius:20px;font-family:'DM Sans',sans-serif;font-size:11px;font-weight:600;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;transition:all .2s;white-space:nowrap;}
        .preset-btn:hover{border-color:var(--muted);color:var(--text);transform:translateY(-1px);}
        .preset-btn.active{border-color:var(--accent);color:var(--accent);background:var(--accent-dim);}

        .chart-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:28px 32px 24px;position:relative;overflow:hidden;}
        .chart-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--vendor) 0%,var(--accent) 50%,var(--internal) 100%);}
        .chart-title{font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:24px;display:flex;align-items:center;gap:8px;}
        .chart-title i{color:var(--accent);font-size:14px;}
        .chart-title .click-hint{font-size:11px;font-weight:400;color:var(--muted);text-transform:none;letter-spacing:0;margin-left:auto;display:flex;align-items:center;gap:5px;opacity:.7;}
        .chart-title .click-hint i{color:var(--accent);font-size:11px;opacity:.6;}
        .chart-area{position:relative;width:100%;height:420px;}
        .chart-area canvas{width:100%!important;height:100%!important;}

        .custom-legend{display:flex;align-items:center;justify-content:center;gap:32px;margin-top:20px;padding-top:18px;border-top:1px solid var(--border);flex-wrap:wrap;}
        .legend-item{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);font-weight:500;cursor:default;transition:color .2s;}
        .legend-item:hover{color:var(--text);}
        .legend-dot{width:12px;height:12px;border-radius:3px;}
        .legend-dot.vendor{background:linear-gradient(180deg,#FF8C5A,#FF6B35);box-shadow:0 0 8px rgba(255,107,53,.3);}
        .legend-dot.internal{background:linear-gradient(180deg,#33E0BE,#00D4AA);box-shadow:0 0 8px rgba(0,212,170,.3);}

        .monthly-panel{ max-height:0; overflow:hidden; opacity:0; transform:translateY(-8px); transition: max-height .5s cubic-bezier(.4,0,.2,1), opacity .4s ease .05s, transform .4s ease .05s, margin-top .4s ease; margin-top:0; }
        .monthly-panel.open{ max-height:700px; opacity:1; transform:translateY(0); margin-top:16px; }
        .monthly-card{ background:var(--card); border:1px solid var(--border); border-radius:14px; padding:24px 28px 20px; position:relative; overflow:hidden; }
        .monthly-card::before{ content:'';position:absolute;top:0;left:0;right:0;height:2px; background:linear-gradient(90deg,var(--accent) 0%,var(--internal) 50%,var(--accent) 100%); }
        .monthly-header{ display:flex;align-items:center;justify-content:space-between; margin-bottom:18px;flex-wrap:wrap;gap:10px; }
        .monthly-header-left{display:flex;align-items:center;gap:10px;}
        .monthly-header-left .year-badge{ background:var(--accent-dim);color:var(--accent); font-family:'Space Grotesk',sans-serif;font-size:15px;font-weight:700; padding:5px 14px;border-radius:8px; }
        .monthly-header-left h3{ font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:600; color:var(--muted);text-transform:uppercase;letter-spacing:1px; }
        .monthly-header-left h3 i{color:var(--accent);font-size:13px;margin-right:6px;}
        .close-btn{ width:32px;height:32px;border-radius:8px;border:1px solid var(--border); background:transparent;color:var(--muted);font-size:14px;cursor:pointer; display:flex;align-items:center;justify-content:center; transition:all .2s; }
        .close-btn:hover{background:rgba(248,81,73,.1);border-color:var(--danger);color:var(--danger);transform:scale(1.05);}
        .monthly-chart-area{position:relative;width:100%;height:280px;}
        .monthly-chart-area canvas{width:100%!important;height:100%!important;}
        .monthly-summary{ display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:8px;margin-top:16px;padding-top:14px;border-top:1px solid var(--border); }
        .ms-item{ display:flex;flex-direction:column;gap:2px; }
        .ms-item .ms-label{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;font-weight:600;}
        .ms-item .ms-val{font-family:'Space Grotesk',sans-serif;font-size:15px;font-weight:700;}
        .ms-item .ms-val.mv{color:var(--vendor);}
        .ms-item .ms-val.mi{color:var(--internal);}
        .ms-item .ms-val.ma{color:var(--accent);}

        .summary-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:16px;}
        .summary-card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:16px 18px;position:relative;overflow:hidden;opacity:0;transform:translateY(12px);animation:fadeUp .4s ease forwards;transition:transform .2s,border-color .2s;}
        .summary-card:nth-child(1){animation-delay:.15s}.summary-card:nth-child(2){animation-delay:.2s}.summary-card:nth-child(3){animation-delay:.25s}.summary-card:nth-child(4){animation-delay:.3s}.summary-card:nth-child(5){animation-delay:.35s}.summary-card:nth-child(6){animation-delay:.4s}
        .summary-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;}
        .summary-card.sv::before{background:var(--vendor);}.summary-card.si::before{background:var(--internal);}.summary-card.sa::before{background:var(--accent);}
        .summary-card:hover{transform:translateY(-2px);border-color:var(--muted);}
        .sc-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;margin-bottom:8px;}
        .sv .sc-icon{background:var(--vendor-dim);color:var(--vendor);}.si .sc-icon{background:var(--internal-dim);color:var(--internal);}.sa .sc-icon{background:var(--accent-dim);color:var(--accent);}
        .sc-label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;font-weight:600;margin-bottom:4px;}
        .sc-num{font-family:'Space Grotesk',sans-serif;font-size:20px;font-weight:700;line-height:1;}
        .sc-sub{font-size:11px;color:var(--muted);margin-top:5px;}
        .sc-sub .pct{font-weight:700;font-size:12px;}
        .pv{color:var(--vendor);}.pi{color:var(--internal);}

        .toast{position:fixed;bottom:24px;right:24px;background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:12px 20px;font-size:13px;color:var(--text);z-index:999;opacity:0;transform:translateY(12px);transition:all .3s;pointer-events:none;display:flex;align-items:center;gap:8px;box-shadow:0 8px 32px rgba(0,0,0,.4);}
        .toast.show{opacity:1;transform:translateY(0);pointer-events:auto;}
        .toast i{color:var(--internal);}
        .particle{position:fixed;width:2px;height:2px;border-radius:50%;pointer-events:none;opacity:0;z-index:0;}
        @keyframes particleDrift{0%{opacity:0;transform:translateY(0) scale(.5);}20%{opacity:.6;}80%{opacity:.3;}100%{opacity:0;transform:translateY(-120px) scale(1.2);}}

        @media(max-width:768px){
            .chart-card{padding:20px 16px 18px;}.chart-area{height:320px;}
            .header h1{font-size:18px;}.summary-row{grid-template-columns:1fr 1fr;}
            .custom-legend{gap:20px;}.range-hint{margin-left:0;width:100%;}
            .monthly-chart-area{height:220px;}.monthly-card{padding:18px 14px 16px;}
            .chart-title .click-hint{display:none;}
        }
    </style>
</head>
<body>

<div id="particles"></div>

<div class="container">

    <div class="header">
        <div class="header-top">
            <h1><i class="fas fa-chart-column"></i> Perbandingan Tahunan — Internal vs Vendor</h1>
            <span class="plant-badge"><?= htmlspecialchars($plant_label) ?></span>
        </div>
        <div class="header-sub">Nilai Sales (IDR) per tahun &nbsp;|&nbsp; Periode: <b id="periodLabel">Pilih Rentang Waktu</b></div>
    </div>

    <div class="filter-bar">
        <label for="startYear">Dari Tahun</label>
        <select id="startYear" aria-label="Tahun awal"></select>
        <div class="sep"></div>
        <label for="endYear">Sampai Tahun</label>
        <select id="endYear" aria-label="Tahun akhir"></select>
        <div class="range-hint" id="rangeHint">
            <i class="fas fa-calendar-days"></i>
            <span id="rangeText">... tahun dipilih</span>
            <span class="count-badge" id="rangeCount">...</span>
        </div>
    </div>

    <div class="preset-row">
        <span><i class="fas fa-bolt" style="color:var(--accent);margin-right:2px;"></i> Cepat:</span>
        <button class="preset-btn" onclick="applyPreset(3)">3 Tahun</button>
        <button class="preset-btn" onclick="applyPreset(5)">5 Tahun</button>
        <button class="preset-btn active" onclick="applyPreset('last5')">5 Tahun Terakhir</button>
        <button class="preset-btn" onclick="applyPreset(7)">7 Tahun</button>
        <button class="preset-btn" onclick="applyPreset(10)">10 Tahun</button>
    </div>

    <!-- CHART TAHUNAN -->
    <div class="chart-card">
        <div class="chart-title">
            <i class="fas fa-chart-bar"></i>
            Tren Nilai Sales Tahunan
            <span class="click-hint"><i class="fas fa-hand-pointer"></i> Klik bar untuk lihat detail bulanan</span>
        </div>
        <div class="chart-area">
            <canvas id="annualBarChart"></canvas>
        </div>
        
        <!-- Tambahan Legend Line Chart Gabungan -->
        <div class="custom-legend">
            <div class="legend-item"><div class="legend-dot vendor"></div>Vendor</div>
            <div class="legend-item"><div class="legend-dot internal"></div>Internal</div>
            <div class="legend-item">
                <div style="width:18px;height:2px;background:var(--accent);position:relative;display:inline-block;margin-right:4px;">
                    <div style="position:absolute;width:8px;height:8px;border-radius:50%;background:var(--card);border:2px solid var(--accent);top:-3px;left:5px;"></div>
                </div>
                Total Gabungan (Pertumbuhan YoY)
            </div>
        </div>
    </div>

    <!-- PANEL BULANAN (tersembunyi, muncul saat klik) -->
    <div class="monthly-panel" id="monthlyPanel">
        <div class="monthly-card">
            <div class="monthly-header">
                <div class="monthly-header-left">
                    <span class="year-badge" id="monthlyYearBadge">2024</span>
                    <h3><i class="fas fa-calendar-week"></i> Detail Bulanan</h3>
                </div>
                <button class="close-btn" id="closeMonthly" aria-label="Tutup detail bulanan">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
            <div class="monthly-chart-area">
                <canvas id="monthlyBarChart"></canvas>
            </div>
            <div class="monthly-summary" id="monthlySummary"></div>
        </div>
    </div>

    <!-- RINGKASAN -->
    <div class="summary-row" id="summaryRow"></div>

</div>

<div class="toast" id="toast"><i class="fas fa-check-circle"></i><span id="toastMsg"></span></div>

<script>
// =============================================
// DATA TAHUNAN & BULANAN DARI DATABASE
// =============================================
var ALL_DATA = <?= $all_data_json ?: '[]' ?>;
var MONTHLY_DATA = <?= $monthly_data_json ?: '{}' ?>;

if (ALL_DATA.length === 0) {
    var currentYear = new Date().getFullYear();
    ALL_DATA = [{year: currentYear, vendor: 0, internal: 0}];
    MONTHLY_DATA[currentYear] = { vendor: Array(12).fill(0), internal: Array(12).fill(0) };
}

var MONTH_LABELS = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

var MIN_YEAR = ALL_DATA[0].year;
var MAX_YEAR = ALL_DATA[ALL_DATA.length - 1].year;
var MAX_SPAN = 10;

var chartInstance = null;
var monthlyChartInstance = null;
var currentEnd = MAX_YEAR;
var currentStart = Math.max(MIN_YEAR, MAX_YEAR - 4);
var selectedYear = null;

// =============================================
// FORMAT
// =============================================
function fmtShort(n) {
    if (n >= 1e12) return (n / 1e12).toFixed(1) + 'T';
    if (n >= 1e9) return (n / 1e9).toFixed(1) + 'M';
    if (n >= 1e6) return (n / 1e6).toFixed(1) + 'Jt';
    if (n >= 1e3) return (n / 1e3).toFixed(0) + 'Rb';
    return n.toString();
}
function fmtFull(n) { return 'Rp ' + n.toLocaleString('id-ID', {maximumFractionDigits: 0}); }

// =============================================
// INISIALISASI DROPDOWN
// =============================================
function initSelects() {
    var selS = document.getElementById('startYear');
    var selE = document.getElementById('endYear');
    for (var y = MIN_YEAR; y <= MAX_YEAR; y++) {
        var oS = document.createElement('option'); oS.value = y; oS.textContent = y; selS.appendChild(oS);
        var oE = document.createElement('option'); oE.value = y; oE.textContent = y; selE.appendChild(oE);
    }
    selS.value = currentStart;
    selE.value = currentEnd;
    selS.addEventListener('change', onRangeChange);
    selE.addEventListener('change', onRangeChange);
}

function onRangeChange() {
    var s = parseInt(document.getElementById('startYear').value);
    var e = parseInt(document.getElementById('endYear').value);
    if (e < s) { e = s; document.getElementById('endYear').value = e; }
    if (e - s + 1 > MAX_SPAN) { e = s + MAX_SPAN - 1; document.getElementById('endYear').value = e; showToast('Maksimal ' + MAX_SPAN + ' tahun dipilih'); }
    currentStart = s; currentEnd = e;
    clearActivePreset();
    closeMonthlyPanel();
    updateAll();
}

function applyPreset(type) {
    clearActivePreset();
    var s, e;
    if (type === 'last5') { s = MAX_YEAR - 4; e = MAX_YEAR; }
    else { var span = parseInt(type); e = MAX_YEAR; s = e - span + 1; }
    if (s < MIN_YEAR) s = MIN_YEAR;
    currentStart = s; currentEnd = e;
    document.getElementById('startYear').value = s;
    document.getElementById('endYear').value = e;
    var btns = document.querySelectorAll('.preset-btn');
    btns.forEach(function(b) { if (b.getAttribute('onclick').includes("'" + type + "'") || b.getAttribute('onclick').includes("(" + type + ")")) b.classList.add('active'); });
    closeMonthlyPanel();
    updateAll();
    showToast('Menampilkan ' + (e - s + 1) + ' tahun (' + s + '–' + e + ')');
}

function clearActivePreset() { document.querySelectorAll('.preset-btn').forEach(function(b) { b.classList.remove('active'); }); }

function updateAll() {
    var filtered = ALL_DATA.filter(function(d) { return d.year >= currentStart && d.year <= currentEnd; });
    var span = filtered.length;
    var hint = document.getElementById('rangeHint');
    var rt = document.getElementById('rangeText');
    var rc = document.getElementById('rangeCount');
    rt.textContent = span + ' tahun dipilih';
    rc.textContent = span;
    hint.classList.toggle('error', span > MAX_SPAN);
    document.getElementById('periodLabel').textContent = currentStart + ' s/d ' + currentEnd;
    updateChart(filtered);
    updateSummary(filtered);
}

// =============================================
// CHART TAHUNAN (DENGAN LINE PERTUMBUHAN YOY)
// =============================================
function updateChart(data) {
    var ctx = document.getElementById('annualBarChart').getContext('2d');
    var vGrad = ctx.createLinearGradient(0, 0, 0, 400);
    vGrad.addColorStop(0, '#FF8C5A'); vGrad.addColorStop(1, '#FF6B35');
    var iGrad = ctx.createLinearGradient(0, 0, 0, 400);
    iGrad.addColorStop(0, '#33E0BE'); iGrad.addColorStop(1, '#00D4AA');

    var labels = data.map(function(d) { return d.year.toString(); });
    var vVals = data.map(function(d) { return d.vendor; });
    var iVals = data.map(function(d) { return d.internal; });
    var totalVals = data.map(function(d) { return d.vendor + d.internal; }); // Untuk dataset Line
    
    var barPct = data.length <= 5 ? 0.65 : data.length <= 7 ? 0.55 : 0.45;

    var vBg = data.map(function(d) { return d.year === selectedYear ? '#FFAA80' : vGrad; });
    var iBg = data.map(function(d) { return d.year === selectedYear ? '#80F0D8' : iGrad; });
    var vBd = data.map(function(d) { return d.year === selectedYear ? '#FFD0B0' : '#FF8C5A'; });
    var iBd = data.map(function(d) { return d.year === selectedYear ? '#B0FFE8' : '#33E0BE'; });

    if (chartInstance) {
        chartInstance.data.labels = labels;
        chartInstance.data.datasets[0].data = vVals;
        chartInstance.data.datasets[0].backgroundColor = vBg;
        chartInstance.data.datasets[0].borderColor = vBd;
        chartInstance.data.datasets[0].barPercentage = barPct;
        chartInstance.data.datasets[1].data = iVals;
        chartInstance.data.datasets[1].backgroundColor = iBg;
        chartInstance.data.datasets[1].borderColor = iBd;
        chartInstance.data.datasets[1].barPercentage = barPct;
        chartInstance.data.datasets[2].data = totalVals; // Update Line data
        chartInstance.update('active');
    } else {
        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {label:'Vendor',type:'bar',data:vVals,backgroundColor:vBg,borderColor:vBd,borderWidth:1,borderRadius:{topLeft:6,topRight:6},borderSkipped:false,barPercentage:barPct,categoryPercentage:0.6},
                    {label:'Internal',type:'bar',data:iVals,backgroundColor:iBg,borderColor:iBd,borderWidth:1,borderRadius:{topLeft:6,topRight:6},borderSkipped:false,barPercentage:barPct,categoryPercentage:0.6},
                    {
                        label:'Total Gabungan',
                        type:'line',
                        data:totalVals,
                        borderColor:'#FFD93D',
                        borderWidth:2,
                        backgroundColor:'rgba(255,217,61,0.1)',
                        pointBackgroundColor:'#161B22',
                        pointBorderColor:'#FFD93D',
                        pointBorderWidth:2,
                        pointRadius:4,
                        pointHoverRadius:6,
                        tension:0.3,
                        fill:false
                    }
                ]
            },
            options: {
                responsive:true, maintainAspectRatio:false,
                layout:{ padding:{ top: 35 } }, // Space untuk teks persentase
                interaction:{mode:'index',intersect:false},
                onClick: function(evt, elements) {
                    if (elements.length > 0) {
                        var idx = elements[0].index;
                        var year = parseInt(chartInstance.data.labels[idx]);
                        toggleMonthly(year);
                    }
                },
                onHover: function(evt, elements) {
                    evt.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
                },
                plugins:{
                    legend:{display:false},
                    tooltip:{
                        backgroundColor:'#1C2333',titleColor:'#E6EDF3',bodyColor:'#8B949E',borderColor:'#30363D',borderWidth:1,cornerRadius:8,padding:14,
                        titleFont:{family:'Space Grotesk',size:14,weight:'700'},bodyFont:{family:'DM Sans',size:12},boxPadding:6,usePointStyle:true,
                        callbacks:{
                            title:function(items){return 'Tahun ' + items[0].label + (parseInt(items[0].label) === selectedYear ? '  (dipilih)' : '');},
                            label:function(c){
                                if (c.dataset.type === 'line') {
                                    var idx = c.dataIndex;
                                    var val = c.chart.data.datasets[2].data[idx];
                                    var prev = idx > 0 ? c.chart.data.datasets[2].data[idx-1] : 0;
                                    var growthText = '-';
                                    if (idx > 0 && prev > 0) {
                                        var g = ((val - prev)/prev)*100;
                                        growthText = (g>0?'+':'') + g.toFixed(1) + '%';
                                    }
                                    return ' Total: '+fmtFull(val)+' (Pertumbuhan: '+growthText+')';
                                } else {
                                    var v=c.parsed.y;
                                    var t=c.chart.data.datasets[0].data[c.dataIndex] + c.chart.data.datasets[1].data[c.dataIndex];
                                    var p=t>0?((v/t)*100).toFixed(1):0;
                                    return ' '+c.dataset.label+': '+fmtFull(v)+'  ('+p+'%)';
                                }
                            }
                        }
                    }
                },
                scales:{
                    x:{grid:{display:false},ticks:{color:'#E6EDF3',font:{family:'Space Grotesk',size:13,weight:'600'},padding:8},border:{color:'#30363D'}},
                    y:{beginAtZero:true,grid:{color:'rgba(48,54,61,0.4)',lineWidth:1},ticks:{color:'#8B949E',font:{family:'Space Grotesk',size:11},padding:12,callback:function(v){return fmtShort(v);},maxTicksLimit:7},border:{display:false}}
                },
                animation:{duration:800,easing:'easeOutQuart'}
            },
            plugins:[{
                id:'chartLabelsPlugin',
                afterDatasetsDraw:function(chart){
                    var c=chart.ctx;

                    // 1. Gambar nilai bar per item
                    chart.data.datasets.forEach(function(ds,di){
                        if(ds.type === 'line') return; // Lewati line chart
                        var meta=chart.getDatasetMeta(di);
                        meta.data.forEach(function(bar,idx){
                            var val=ds.data[idx];
                            c.save();c.textAlign='center';c.textBaseline='bottom';
                            var fs=chart.data.labels.length<=5?10:chart.data.labels.length<=7?9:8;
                            c.font='600 '+fs+'px "Space Grotesk",sans-serif';
                            c.fillStyle=di===0?'#FF8C5A':'#33E0BE';
                            if (parseInt(chart.data.labels[idx]) === selectedYear) {
                                c.font = '700 ' + (fs+1) + 'px "Space Grotesk",sans-serif';
                            }
                            c.fillText(fmtShort(val), bar.x, bar.y - 5);
                            c.restore();
                        });
                    });

                    // 2. Gambar label Pertumbuhan Total YoY di atas Line Chart
                    var lineIdx = 2;
                    if(chart.data.datasets[lineIdx]) {
                        var dsTotal = chart.data.datasets[lineIdx].data;
                        var metaTotal = chart.getDatasetMeta(lineIdx);

                        metaTotal.data.forEach(function(point, idx){
                            var val = dsTotal[idx];
                            var prevVal = idx > 0 ? dsTotal[idx-1] : null;

                            c.save();
                            c.textAlign = 'center';
                            c.textBaseline = 'bottom';

                            if (idx > 0 && prevVal !== null && prevVal > 0) {
                                var g = ((val - prevVal) / prevVal) * 100;
                                var text = (g > 0 ? '▲ +' : (g < 0 ? '▼ ' : '')) + g.toFixed(1) + '%';
                                
                                // Efek shadow pekat agar tulisan kontras menonjol di atas bar grafik
                                c.shadowColor = '#0D1117';
                                c.shadowBlur = 4;
                                c.lineWidth = 3;
                                c.strokeStyle = '#0D1117';
                                c.font = '700 13px "Space Grotesk",sans-serif';
                                
                                c.strokeText(text, point.x, point.y - 12); // Outline hitam
                                
                                c.shadowBlur = 0;
                                c.fillStyle = g > 0 ? '#00D4AA' : (g < 0 ? '#F85149' : '#FFD93D');
                                c.fillText(text, point.x, point.y - 12); // Teks warna
                            }
                            c.restore();
                        });
                    }
                }
            }]
        });
    }
}

// =============================================
// LOGIKA BULANAN
// =============================================
function toggleMonthly(year) {
    if (selectedYear === year) closeMonthlyPanel(); else openMonthlyPanel(year);
}

function openMonthlyPanel(year) {
    selectedYear = year;
    document.getElementById('monthlyYearBadge').textContent = year;
    document.getElementById('monthlyPanel').classList.add('open');

    var filtered = ALL_DATA.filter(function(d) { return d.year >= currentStart && d.year <= currentEnd; });
    updateChart(filtered);
    updateMonthlyChart(year);
    updateMonthlySummary(year);

    showToast('Detail bulanan tahun ' + year);
    setTimeout(function() { document.getElementById('monthlyPanel').scrollIntoView({behavior:'smooth', block:'nearest'}); }, 200);
}

function closeMonthlyPanel() {
    selectedYear = null;
    document.getElementById('monthlyPanel').classList.remove('open');
    var filtered = ALL_DATA.filter(function(d) { return d.year >= currentStart && d.year <= currentEnd; });
    updateChart(filtered);
}

document.getElementById('closeMonthly').addEventListener('click', closeMonthlyPanel);

function updateMonthlyChart(year) {
    var md = MONTHLY_DATA[year];
    if (!md) return;

    var ctx = document.getElementById('monthlyBarChart').getContext('2d');
    var vGrad = ctx.createLinearGradient(0, 0, 0, 260);
    vGrad.addColorStop(0, '#FF8C5A'); vGrad.addColorStop(1, '#FF6B35');
    var iGrad = ctx.createLinearGradient(0, 0, 0, 260);
    iGrad.addColorStop(0, '#33E0BE'); iGrad.addColorStop(1, '#00D4AA');

    if (monthlyChartInstance) {
        monthlyChartInstance.data.datasets[0].data = md.vendor;
        monthlyChartInstance.data.datasets[0].backgroundColor = vGrad;
        monthlyChartInstance.data.datasets[1].data = md.internal;
        monthlyChartInstance.data.datasets[1].backgroundColor = iGrad;
        monthlyChartInstance.update('active');
    } else {
        monthlyChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: MONTH_LABELS,
                datasets: [
                    {label:'Vendor',data:md.vendor,backgroundColor:vGrad,hoverBackgroundColor:'#FFA07A',borderColor:'#FF8C5A',borderWidth:1,borderRadius:{topLeft:4,topRight:4},borderSkipped:false,barPercentage:0.7,categoryPercentage:0.65},
                    {label:'Internal',data:md.internal,backgroundColor:iGrad,hoverBackgroundColor:'#66EDD6',borderColor:'#33E0BE',borderWidth:1,borderRadius:{topLeft:4,topRight:4},borderSkipped:false,barPercentage:0.7,categoryPercentage:0.65}
                ]
            },
            options: {
                responsive:true, maintainAspectRatio:false,
                interaction:{mode:'index',intersect:false},
                plugins:{
                    legend:{display:false},
                    tooltip:{
                        backgroundColor:'#1C2333',titleColor:'#E6EDF3',bodyColor:'#8B949E',borderColor:'#30363D',borderWidth:1,cornerRadius:8,padding:12,
                        titleFont:{family:'Space Grotesk',size:13,weight:'700'},bodyFont:{family:'DM Sans',size:11},boxPadding:5,usePointStyle:true,
                        callbacks:{
                            title:function(items){return MONTH_LABELS[items[0].dataIndex] + ' ' + year;},
                            label:function(c){var v=c.parsed.y;var t=c.chart.data.datasets.reduce(function(s,ds){return s+ds.data[c.dataIndex];},0);var p=t>0?((v/t)*100).toFixed(1):0;return ' '+c.dataset.label+': '+fmtFull(v)+' ('+p+'%)';}
                        }
                    }
                },
                scales:{
                    x:{grid:{display:false},ticks:{color:'#E6EDF3',font:{family:'Space Grotesk',size:11,weight:'600'},padding:6},border:{color:'#30363D'}},
                    y:{beginAtZero:true,grid:{color:'rgba(48,54,61,0.35)',lineWidth:1},ticks:{color:'#8B949E',font:{family:'Space Grotesk',size:10},padding:10,callback:function(v){return fmtShort(v);},maxTicksLimit:6},border:{display:false}}
                },
                animation:{duration:600,easing:'easeOutQuart'}
            },
            plugins:[{
                id:'monthlyValLabels',
                afterDatasetsDraw:function(chart){
                    var c=chart.ctx;
                    chart.data.datasets.forEach(function(ds,di){
                        var meta=chart.getDatasetMeta(di);
                        meta.data.forEach(function(bar,idx){
                            var val=ds.data[idx];
                            c.save();c.textAlign='center';c.textBaseline='bottom';
                            c.font='600 8px "Space Grotesk",sans-serif';
                            c.fillStyle=di===0?'#FF8C5A':'#33E0BE';
                            c.fillText(fmtShort(val), bar.x, bar.y - 4);
                            c.restore();
                        });
                    });
                }
            }]
        });
    }
}

function updateMonthlySummary(year) {
    var md = MONTHLY_DATA[year];
    if (!md) return;
    var tV = 0, tI = 0, maxVM = 0, maxIM = 0, maxVMName = '', maxIMName = '';
    for (var m = 0; m < 12; m++) {
        tV += md.vendor[m]; tI += md.internal[m];
        if (md.vendor[m] > maxVM) { maxVM = md.vendor[m]; maxVMName = MONTH_LABELS[m]; }
        if (md.internal[m] > maxIM) { maxIM = md.internal[m]; maxIMName = MONTH_LABELS[m]; }
    }
    var total = tV + tI;
    var pV = total > 0 ? ((tV / total) * 100).toFixed(1) : 0;
    var pI = total > 0 ? ((tI / total) * 100).toFixed(1) : 0;

    document.getElementById('monthlySummary').innerHTML =
        '<div class="ms-item"><span class="ms-label">Total Vendor</span><span class="ms-val mv">' + fmtShort(tV) + '</span></div>' +
        '<div class="ms-item"><span class="ms-label">Total Internal</span><span class="ms-val mi">' + fmtShort(tI) + '</span></div>' +
        '<div class="ms-item"><span class="ms-label">Vendor Puncak</span><span class="ms-val mv">' + maxVMName + ' (' + fmtShort(maxVM) + ')</span></div>' +
        '<div class="ms-item"><span class="ms-label">Internal Puncak</span><span class="ms-val mi">' + maxIMName + ' (' + fmtShort(maxIM) + ')</span></div>' +
        '<div class="ms-item"><span class="ms-label">Proporsi Vendor</span><span class="ms-val ma">' + pV + '%</span></div>' +
        '<div class="ms-item"><span class="ms-label">Proporsi Internal</span><span class="ms-val ma">' + pI + '%</span></div>';
}

// =============================================
// RINGKASAN TAHUNAN
// =============================================
function updateSummary(data) {
    var tV=0,tI=0;
    data.forEach(function(d){tV+=d.vendor;tI+=d.internal;});
    var total=tV+tI;
    var pV=total>0?((tV/total)*100).toFixed(1):0;
    var pI=total>0?((tI/total)*100).toFixed(1):0;
    var li=data.length-1;
    var gV=0,gI=0;
    if(li>0){
        gV = data[li-1].vendor > 0 ? ((data[li].vendor-data[li-1].vendor)/data[li-1].vendor*100).toFixed(1) : (data[li].vendor > 0 ? 100 : 0);
        gI = data[li-1].internal > 0 ? ((data[li].internal-data[li-1].internal)/data[li-1].internal*100).toFixed(1) : (data[li].internal > 0 ? 100 : 0);
    }
    var avgV=data.length>0?tV/data.length:0;
    var avgI=data.length>0?tI/data.length:0;

    document.getElementById('summaryRow').innerHTML =
        '<div class="summary-card sv"><div class="sc-icon"><i class="fas fa-truck-field"></i></div><div class="sc-label">Total Vendor</div><div class="sc-num" style="color:var(--vendor);">'+fmtShort(tV)+'</div><div class="sc-sub">Proporsi: <span class="pct pv">'+pV+'%</span></div></div>'+
        '<div class="summary-card si"><div class="sc-icon"><i class="fas fa-industry"></i></div><div class="sc-label">Total Internal</div><div class="sc-num" style="color:var(--internal);">'+fmtShort(tI)+'</div><div class="sc-sub">Proporsi: <span class="pct pi">'+pI+'%</span></div></div>'+
        '<div class="summary-card sa"><div class="sc-icon"><i class="fas fa-chart-line"></i></div><div class="sc-label">Pertumbuhan Vendor '+(data.length ? data[li].year : '-')+'</div><div class="sc-num" style="color:'+(gV>=0?'#00D4AA':'#F85149')+';">'+(gV>=0?'+':'')+gV+'%</div><div class="sc-sub">vs tahun '+(li>0?data[li-1].year:'-')+'</div></div>'+
        '<div class="summary-card sa"><div class="sc-icon"><i class="fas fa-chart-line"></i></div><div class="sc-label">Pertumbuhan Internal '+(data.length ? data[li].year : '-')+'</div><div class="sc-num" style="color:'+(gI>=0?'#00D4AA':'#F85149')+';">'+(gI>=0?'+':'')+gI+'%</div><div class="sc-sub">vs tahun '+(li>0?data[li-1].year:'-')+'</div></div>'+
        '<div class="summary-card sv"><div class="sc-icon"><i class="fas fa-calculator"></i></div><div class="sc-label">Rata-rata Vendor/Tahun</div><div class="sc-num" style="color:var(--vendor);font-size:17px;">'+fmtShort(avgV)+'</div><div class="sc-sub">dari '+data.length+' tahun</div></div>'+
        '<div class="summary-card si"><div class="sc-icon"><i class="fas fa-calculator"></i></div><div class="sc-label">Rata-rata Internal/Tahun</div><div class="sc-num" style="color:var(--internal);font-size:17px;">'+fmtShort(avgI)+'</div><div class="sc-sub">dari '+data.length+' tahun</div></div>';

    document.querySelectorAll('.summary-card').forEach(function(card,i){
        card.style.animation='none';card.offsetHeight;card.style.animation='fadeUp .4s ease forwards';card.style.animationDelay=(0.1+i*0.05)+'s';
    });
}

function showToast(msg){
    var t=document.getElementById('toast');
    document.getElementById('toastMsg').textContent=msg;
    t.classList.add('show');clearTimeout(t._timer);
    t._timer=setTimeout(function(){t.classList.remove('show');},2200);
}

(function(){
    var c=document.getElementById('particles');
    var colors=['#FF6B35','#00D4AA','#FFD93D'];
    for(var i=0;i<16;i++){
        var p=document.createElement('div');p.className='particle';
        p.style.left=Math.random()*100+'%';p.style.top=(40+Math.random()*60)+'%';
        var s=(1.5+Math.random()*2)+'px';p.style.width=s;p.style.height=s;
        p.style.background=colors[Math.floor(Math.random()*colors.length)];
        p.style.animation='particleDrift '+(6+Math.random()*8)+'s ease-in-out '+(Math.random()*6)+'s infinite';
        c.appendChild(p);
    }
})();

initSelects();
updateAll();
</script>

</body>
</html>
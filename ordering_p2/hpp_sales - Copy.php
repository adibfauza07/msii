<?php
// ============================================
// SAFETY: Batasi maksimal eksekusi 20 detik
// ============================================
set_time_limit(20);
session_start();

require_once __DIR__ . "/../config/database_ordering.php";

if (!isset($_SESSION['db_user']) || $_SESSION['db_user'] == "") {
    die('<div style="padding:24px;color:#F85149;background:#0D1117;font-family:monospace;">Silakan login terlebih dahulu.</div>');
}

 $uid = $_SESSION['db_user'];
 $pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "";
 $dbName = "msData";

 $servers_config = [
    'p1' => ['ip' => '192.168.0.4', 'label' => 'Plant 1', 'short' => 'P1'],
    'p2' => ['ip' => '192.168.0.9', 'label' => 'Plant 2', 'short' => 'P2'],
];

 $selected_plant = isset($_GET['plant']) ? strtolower($_GET['plant']) : 'all';
if (!in_array($selected_plant, ['p1', 'p2', 'all'])) $selected_plant = 'all';

 $server_available = [];
 $conn_errors = [];
 $raw_yearly = [];
 $raw_monthly = [];
 $min_year = 9999;
 $max_year = 0;

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
";

 $conn_opts_base = [
    "Database" => $dbName, "Uid" => $uid, "PWD" => $pwd,
    "CharacterSet" => "UTF-8", "LoginTimeout" => 3, "Encrypt" => false,
];

 $servers_to_try = ($selected_plant === 'all') ? ['p1', 'p2'] : [$selected_plant];

foreach ($servers_to_try as $key) {
    $ip = $servers_config[$key]['ip'];
    $name = $servers_config[$key]['label'];
    $conn = @sqlsrv_connect($ip, $conn_opts_base);

    if (!$conn) {
        $server_available[$key] = false;
        $errs = @sqlsrv_errors();
        $detail = '';
        if ($errs) {
            $nativeMsg = isset($errs[0]['message']) ? $errs[0]['message'] : '';
            if (stripos($nativeMsg, 'timeout') !== false || stripos($nativeMsg, 'timed out') !== false) {
                $detail = 'Timeout — server tidak merespon dalam 3 detik';
            } elseif (stripos($nativeMsg, 'login failed') !== false) {
                $detail = 'Login gagal';
            } else {
                $detail = htmlspecialchars(mb_substr($nativeMsg, 0, 120));
            }
        } else { $detail = 'Tidak ada respons dari server'; }
        $conn_errors[] = "<strong>{$name} ({$ip})</strong> — {$detail}";
        continue;
    }

    $server_available[$key] = true;
    $stmt = @sqlsrv_query($conn, $sql);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $year = (int)$row['SALES_YEAR'];
            $month = (int)$row['SALES_MONTH'];
            $is_vendor = (int)$row['IS_VENDOR'];
            $sales = (float)$row['TOTAL_SALES'];
            if ($year < $min_year) $min_year = $year;
            if ($year > $max_year) $max_year = $year;
            if (!isset($raw_yearly[$year])) {
                // Tambahkan key 'total' untuk memudahkan JS
                $raw_yearly[$year] = ['year' => $year, 'vendor' => 0, 'internal' => 0, 'total' => 0];
                $raw_monthly[$year] = ['vendor' => array_fill(0, 12, 0), 'internal' => array_fill(0, 12, 0), 'total' => array_fill(0, 12, 0)];
            }
            if ($is_vendor === 1) {
                $raw_yearly[$year]['vendor'] += $sales;
                $raw_monthly[$year]['vendor'][$month - 1] += $sales;
            } else {
                $raw_yearly[$year]['internal'] += $sales;
                $raw_monthly[$year]['internal'][$month - 1] += $sales;
            }
            $raw_yearly[$year]['total'] += $sales;
            $raw_monthly[$year]['total'][$month - 1] += $sales;
        }
        sqlsrv_free_stmt($stmt);
    } else {
        $errs = @sqlsrv_errors();
        $detail = $errs ? htmlspecialchars(mb_substr($errs[0]['message'], 0, 120)) : 'Query gagal';
        $conn_errors[] = "<strong>{$name}</strong> — Koneksi OK tapi query gagal: {$detail}";
    }
    sqlsrv_close($conn);
}

 $p1_ok = !empty($server_available['p1']);
 $p2_ok = !empty($server_available['p2']);
 $any_ok = $p1_ok || $p2_ok;

 $yearly_data = [];
 $monthly_data = [];
 $has_real_data = false;
if ($min_year <= $max_year) {
    $has_real_data = true;
    for ($y = $min_year; $y <= $max_year; $y++) {
        if (isset($raw_yearly[$y])) {
            $yearly_data[] = $raw_yearly[$y];
            $monthly_data[$y] = $raw_monthly[$y];
        } else {
            $yearly_data[] = ['year' => $y, 'vendor' => 0, 'internal' => 0, 'total' => 0];
            $monthly_data[$y] = ['vendor' => array_fill(0, 12, 0), 'internal' => array_fill(0, 12, 0), 'total' => array_fill(0, 12, 0)];
        }
    }
}

 $plant_badge_text = '';
if ($selected_plant === 'p1') { $plant_badge_text = 'PLANT 1' . ($p1_ok ? '' : ' <span style="color:#F85149">(OFFLINE)</span>'); }
elseif ($selected_plant === 'p2') { $plant_badge_text = 'PLANT 2' . ($p2_ok ? '' : ' <span style="color:#F85149">(OFFLINE)</span>'); }
else {
    $up = []; $down = [];
    if ($p1_ok) $up[] = 'P1'; else $down[] = 'P1';
    if ($p2_ok) $up[] = 'P2'; else $down[] = 'P2';
    $plant_badge_text = empty($up) ? '<span style="color:#F85149">SEMUA OFFLINE</span>' : implode(' & ', $up);
    if (!empty($down)) $plant_badge_text .= ' <span style="color:#F85149;font-size:10px;opacity:.8">(' . implode(', ', $down) . ' dilewati)</span>';
}

 $all_data_json = json_encode($yearly_data);
 $monthly_data_json = json_encode($monthly_data);
 $server_avail_json = json_encode($server_available);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grafik Sales — Total Amount</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root{--bg:#0D1117;--card:#161B22;--border:#30363D;--text:#E6EDF3;--muted:#8B949E;--sales:#4C8BF5;--sales-dim:rgba(76,139,245,0.12);--vendor:#FF6B35;--internal:#00D4AA;--accent:#FFD93D;--accent-dim:rgba(255,217,61,0.10);--danger:#F85149;--r:12px;--rs:6px;}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;padding:24px 20px 60px;position:relative;overflow-x:hidden;}
        .container{max-width:1100px;margin:0 auto;position:relative;z-index:1;opacity:0;transform:translateY(20px);animation:fadeUp .6s ease forwards;}
        @keyframes fadeUp{to{opacity:1;transform:translateY(0);}}
        .header{margin-bottom:16px;}.header-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:15px;}.header-title-group{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}.header h1{font-family:'Space Grotesk',sans-serif;font-size:22px;font-weight:700;}.header h1 i{color:var(--accent);margin-right:6px;font-size:18px;}.plant-badge{background:var(--accent-dim);color:var(--accent);font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;letter-spacing:.5px;}.header-sub{color:var(--muted);font-size:13px;}.header-sub b{color:var(--text);font-weight:600;}
        .btn-pdf{background:var(--accent);color:#0D1117;border:none;padding:8px 18px;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 4px 12px rgba(255,217,61,0.2);transition:all 0.2s;}.btn-pdf:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(255,217,61,0.3);}.btn-pdf:disabled{opacity:.6;cursor:wait;transform:none;}
        .err-box{background:rgba(248,81,73,0.08);border:1px solid rgba(248,81,73,0.25);border-radius:10px;padding:14px 18px;margin-bottom:12px;}.err-box .err-title{color:var(--danger);font-size:12px;font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:6px;text-transform:uppercase;letter-spacing:.5px;}.err-box .err-item{color:rgba(248,81,73,0.85);font-size:12px;line-height:1.7;padding-left:18px;position:relative;}.err-box .err-item:not(:last-child){margin-bottom:2px;}.err-box .err-item::before{content:'';position:absolute;left:4px;top:9px;width:5px;height:5px;border-radius:50%;background:rgba(248,81,73,0.5);}
        .filter-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:12px 18px;margin-bottom:12px;}.filter-bar label{font-size:12px;color:var(--muted);font-weight:600;white-space:nowrap;}.filter-bar select{background:var(--bg);border:1px solid var(--border);color:var(--text);padding:8px 32px 8px 12px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;outline:none;cursor:pointer;appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238B949E' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;}.filter-bar select:focus{border-color:var(--accent);}.sep{width:1px;height:24px;background:var(--border);margin:0 2px;}.range-hint{font-size:11px;color:var(--muted);display:flex;align-items:center;gap:5px;margin-left:auto;}.range-hint .count-badge{background:var(--accent-dim);color:var(--accent);font-weight:700;padding:2px 8px;border-radius:10px;font-size:11px;font-family:'Space Grotesk',sans-serif;}
        .preset-row{display:flex;align-items:center;gap:6px;margin-bottom:16px;flex-wrap:wrap;}.preset-row span{font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-right:4px;}.preset-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border-radius:20px;font-family:'DM Sans',sans-serif;font-size:11px;font-weight:600;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;transition:all .2s;white-space:nowrap;}.preset-btn:hover{border-color:var(--muted);color:var(--text);transform:translateY(-1px);}.preset-btn.active{border-color:var(--accent);color:var(--accent);background:var(--accent-dim);}
        .chart-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:28px 32px 24px;position:relative;overflow:hidden;margin-bottom:16px;}.chart-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--sales),var(--accent));}.chart-title{font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:24px;display:flex;align-items:center;gap:8px;}.chart-title i{color:var(--accent);font-size:14px;}.chart-title .click-hint{font-size:11px;font-weight:400;color:var(--muted);text-transform:none;letter-spacing:0;margin-left:auto;display:flex;align-items:center;gap:5px;opacity:.7;}.chart-area{position:relative;width:100%;height:380px;}
        .empty-state{display:none;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;text-align:center;}.empty-state.visible{display:flex;}.empty-state .empty-icon{width:80px;height:80px;border-radius:50%;background:rgba(248,81,73,0.08);display:flex;align-items:center;justify-content:center;font-size:32px;color:var(--danger);margin-bottom:20px;}.empty-state h3{font-family:'Space Grotesk',sans-serif;font-size:18px;font-weight:700;color:var(--text);margin-bottom:8px;}.empty-state p{font-size:13px;color:var(--muted);max-width:440px;line-height:1.7;}
        .custom-legend{display:flex;align-items:center;justify-content:center;gap:32px;margin-top:20px;padding-top:18px;border-top:1px solid var(--border);flex-wrap:wrap;}.legend-item{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);font-weight:500;cursor:default;}.legend-dot{width:12px;height:12px;border-radius:3px;}.legend-dot.sales{background:linear-gradient(180deg,#6BA3F7,#4C8BF5);}
        .monthly-panel{max-height:0;overflow:hidden;opacity:0;transform:translateY(-8px);transition:max-height .5s cubic-bezier(.4,0,.2,1),opacity .4s ease .05s,transform .4s ease .05s,margin-top .4s ease;margin-top:0;}.monthly-panel.open{max-height:700px;opacity:1;transform:translateY(0);margin-bottom:16px;}.monthly-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:24px 28px 20px;position:relative;overflow:hidden;}.monthly-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--sales),var(--accent));}.monthly-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;}.monthly-header-left{display:flex;align-items:center;gap:10px;}.monthly-header-left .year-badge{background:var(--accent-dim);color:var(--accent);font-family:'Space Grotesk',sans-serif;font-size:15px;font-weight:700;padding:5px 14px;border-radius:8px;}.monthly-header-left h3{font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:1px;}.close-btn{width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--muted);font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;}.close-btn:hover{background:rgba(248,81,73,.1);border-color:var(--danger);color:var(--danger);}.monthly-chart-area{position:relative;width:100%;height:250px;}
        .monthly-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;margin-top:16px;padding-top:14px;border-top:1px solid var(--border);}.ms-item{display:flex;flex-direction:column;gap:2px;}.ms-item .ms-label{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;font-weight:600;}.ms-item .ms-val{font-family:'Space Grotesk',sans-serif;font-size:15px;font-weight:700;}.ms-item .ms-val.ms{color:var(--sales);}.ms-item .ms-val.ma{color:var(--accent);}
        .summary-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:16px;}.summary-card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:16px 18px;position:relative;overflow:hidden;opacity:0;transform:translateY(12px);animation:fadeUp .4s ease forwards;}.summary-card:nth-child(1){animation-delay:.1s}.summary-card:nth-child(2){animation-delay:.15s}.summary-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;}.summary-card.ss::before{background:var(--sales);}.summary-card.sa::before{background:var(--accent);}.sc-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;margin-bottom:8px;}.ss .sc-icon{background:var(--sales-dim);color:var(--sales);}.sa .sc-icon{background:var(--accent-dim);color:var(--accent);}.sc-label{font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:600;margin-bottom:4px;}.sc-num{font-family:'Space Grotesk',sans-serif;font-size:20px;font-weight:700;line-height:1;}.sc-sub{font-size:11px;color:var(--muted);margin-top:5px;}.sc-sub .pct{font-weight:700;font-size:12px;}.ps{color:var(--sales);}.pv{color:var(--vendor);}.pi{color:var(--internal);}
        .table-card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:24px 20px;position:relative;overflow:visible;page-break-inside:avoid;}.table-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--sales),var(--accent));}.table-wrapper{overflow-x:auto;margin-top:12px;}.data-table{width:100%;border-collapse:collapse;font-size:12px;text-align:left;}.data-table th{font-family:'Space Grotesk',sans-serif;background:rgba(48,54,61,0.5);color:var(--text);padding:8px 10px;font-weight:600;border-bottom:2px solid var(--border);white-space:nowrap;font-size:11px;}.data-table td{padding:7px 10px;border-bottom:1px solid rgba(48,54,61,0.6);color:var(--text);font-family:'Space Grotesk',sans-serif;font-size:11px;white-space:nowrap;}.data-table tbody tr:hover{background:rgba(255,255,255,0.02);}.data-table tr.total-row{background:rgba(255,217,61,0.05)!important;font-weight:bold;}.data-table tr.total-row td{border-top:2px solid var(--border);border-bottom:2px solid var(--border);color:var(--accent);}.t-vendor{color:var(--vendor)!important;}.t-internal{color:var(--internal)!important;}.t-total{color:var(--sales)!important;font-weight:700;}
        .table-tabs{display:flex;gap:8px;border-bottom:1px solid var(--border);padding-bottom:10px;}.tab-btn{background:transparent;border:1px solid var(--border);color:var(--muted);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;font-family:'DM Sans',sans-serif;transition:all 0.2s;}.tab-btn:hover{border-color:var(--muted);color:var(--text);}.tab-btn.active{background:var(--accent-dim);color:var(--accent);border-color:var(--accent);}.table-title-container{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:10px;}
        .toast{position:fixed;bottom:24px;right:24px;background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:12px 20px;font-size:13px;color:var(--text);z-index:999;opacity:0;transform:translateY(12px);transition:all .3s;display:flex;align-items:center;gap:8px;}.toast.show{opacity:1;transform:translateY(0);}
        .pdf-export-mode,.pdf-export-mode::before,.pdf-export-mode::after{background:#FFF!important;animation:none!important;}.pdf-export-mode .container{background:#FFF!important;padding:8px 12px!important;max-width:1540px!important;width:1540px!important;transform:none!important;opacity:1!important;animation:none!important;margin:0!important;}.pdf-export-mode .header h1{color:#111827!important;font-size:16px!important;}.pdf-export-mode .header h1 i{color:#D97706!important;}.pdf-export-mode .header-sub{color:#6B7280!important;font-size:10px!important;}.pdf-export-mode .plant-badge{background:#FEF3C7!important;color:#92400E!important;}.pdf-export-mode .chart-card,.pdf-export-mode .monthly-card,.pdf-export-mode .table-card{background:#FFF!important;border-color:#D1D5DB!important;box-shadow:0 1px 3px rgba(0,0,0,0.06)!important;padding:10px 12px!important;margin-bottom:6px!important;overflow:visible!important;width:100%!important;}.pdf-export-mode .chart-card::before,.pdf-export-mode .monthly-card::before,.pdf-export-mode .table-card::before{background:linear-gradient(90deg,#2563EB,#D97706)!important;}.pdf-export-mode .chart-area{height:260px!important;}.pdf-export-mode .monthly-chart-area{height:180px!important;}.pdf-export-mode .summary-row{gap:5px!important;margin-bottom:6px!important;}.pdf-export-mode .summary-card{background:#F9FAFB!important;border-color:#E5E7EB!important;padding:7px 9px!important;opacity:1!important;transform:none!important;animation:none!important;}.pdf-export-mode .sc-num{color:#111827!important;font-size:13px!important;}.pdf-export-mode .data-table{width:100%!important;table-layout:fixed!important;font-size:9px!important;}.pdf-export-mode .data-table th{background:#F3F4F6!important;color:#111827!important;padding:4px 5px!important;font-size:8px!important;}.pdf-export-mode .data-table td{color:#374151!important;padding:3px 5px!important;font-size:9px!important;}.pdf-export-mode .t-vendor{color:#EA580C!important;}.pdf-export-mode .t-internal{color:#059669!important;}.pdf-export-mode .t-total{color:#2563EB!important;}.pdf-export-mode .filter-bar,.pdf-export-mode .preset-row,.pdf-export-mode .click-hint,.pdf-export-mode #pdfBtn,.pdf-export-mode .close-btn,.pdf-export-mode .table-tabs,.pdf-export-mode .toast{display:none!important;}
    </style>
</head>
<body>
<div class="container" id="mainContainer">
    <div class="header">
        <div class="header-top">
            <div class="header-title-group">
                <h1><i class="fas fa-chart-column"></i> Total Sales Amount</h1>
                <span class="plant-badge <?= (!$any_ok ? 'offline-badge' : '') ?>"><?= $plant_badge_text ?></span>
            </div>
            <?php if ($has_real_data): ?>
                <button id="pdfBtn" class="btn-pdf" onclick="generatePDF()"><i class="fas fa-file-pdf"></i> Download PDF A3</button>
            <?php endif; ?>
        </div>
        <div class="header-sub">Nilai Sales (IDR) &nbsp;|&nbsp; Periode: <b id="periodLabel">Pilih Rentang Waktu</b></div>
    </div>

    <?php if (!empty($conn_errors)): ?>
        <div class="err-box"><div class="err-title"><i class="fas fa-triangle-exclamation"></i> Peringatan</div>
            <?php foreach ($conn_errors as $err): ?><div class="err-item"><?= $err ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <div class="filter-bar">
        <label for="plantFilter"><i class="fas fa-building" style="color:var(--accent);"></i> Plant</label>
        <select id="plantFilter">
            <option value="all" <?= $selected_plant == 'all' ? 'selected' : '' ?>>Gabungan (P1 & P2)</option>
            <option value="p1" <?= $selected_plant == 'p1' ? 'selected' : '' ?>>Plant 1</option>
            <option value="p2" <?= $selected_plant == 'p2' ? 'selected' : '' ?>>Plant 2</option>
        </select>
        <div class="sep"></div><label for="startYear">Dari</label><select id="startYear"></select>
        <div class="sep"></div><label for="endYear">Sampai</label><select id="endYear"></select>
        <div class="range-hint"><i class="fas fa-calendar-days"></i><span id="rangeText">...</span><span class="count-badge" id="rangeCount">...</span></div>
    </div>
    <div class="preset-row">
        <span><i class="fas fa-bolt" style="color:var(--accent);margin-right:2px;"></i> Cepat:</span>
        <button class="preset-btn" onclick="applyPreset(3)">3 Tahun</button>
        <button class="preset-btn active" onclick="applyPreset(5)">5 Tahun</button>
        <button class="preset-btn" onclick="applyPreset(7)">7 Tahun</button>
    </div>

    <div class="chart-card">
        <div class="chart-title"><i class="fas fa-chart-bar"></i> Tren Total Sales Tahunan<span class="click-hint"><i class="fas fa-hand-pointer"></i> Klik bar → detail bulanan</span></div>
        <div class="empty-state <?= $has_real_data ? '' : 'visible' ?>" id="emptyState"><div class="empty-icon"><i class="fas fa-server"></i></div><h3>Tidak Ada Data</h3><p id="emptyMsg">Memuat...</p></div>
        <div class="chart-area" id="chartArea" style="<?= $has_real_data ? '' : 'display:none;' ?>"><canvas id="annualBarChart"></canvas></div>
        <div class="custom-legend" id="legendBar" style="<?= $has_real_data ? '' : 'display:none;' ?>">
            <div class="legend-item"><div class="legend-dot sales"></div>Total Sales (Internal + Vendor)</div>
        </div>
    </div>

    <div class="monthly-panel" id="monthlyPanel">
        <div class="monthly-card">
            <div class="monthly-header"><div class="monthly-header-left"><span class="year-badge" id="monthlyYearBadge">2024</span><h3><i class="fas fa-calendar-week"></i> Detail Bulanan</h3></div><button class="close-btn" id="closeMonthly"><i class="fas fa-xmark"></i></button></div>
            <div class="monthly-chart-area"><canvas id="monthlyBarChart"></canvas></div>
            <div class="monthly-summary" id="monthlySummary"></div>
        </div>
    </div>

    <div class="summary-row" id="summaryRow"></div>

    <div class="table-card" id="tableDataContainer" style="<?= $has_real_data ? '' : 'display:none;' ?>">
        <div class="table-title-container">
            <div class="chart-title" style="margin-bottom:0;"><i class="fas fa-table"></i> Tabulasi Sales</div>
            <div class="table-tabs">
                <button class="tab-btn active" id="tabTahunanBtn" onclick="switchTableTab('tahunan')">Tahunan</button>
                <button class="tab-btn" id="tabBulananBtn" onclick="switchTableTab('bulanan')">Bulanan (<span id="tabBulananYear">Pilih Tahun</span>)</button>
            </div>
        </div>
        <div class="table-wrapper" id="wrapperTahunan">
            <table class="data-table" id="tableTahunan"><thead><tr>
                <th>Tahun</th><th style="text-align:right;">Vendor (IDR)</th><th style="text-align:right;">Internal (IDR)</th><th style="text-align:right;">Total Sales (IDR)</th>
            </tr></thead><tbody id="tbodyTahunan"></tbody></table>
        </div>
        <div class="table-wrapper" id="wrapperBulanan" style="display:none;">
            <table class="data-table" id="tableBulanan"><thead><tr>
                <th>Bulan (<span id="lblTableBulananYear">2024</span>)</th><th style="text-align:right;">Vendor (IDR)</th><th style="text-align:right;">Internal (IDR)</th><th style="text-align:right;">Total Sales (IDR)</th>
            </tr></thead><tbody id="tbodyBulanan"></tbody></table>
        </div>
    </div>
</div>
<div class="toast" id="toast"><i class="fas fa-check-circle" style="color:var(--sales);"></i><span id="toastMsg"></span></div>

<script>
var ALL_DATA=<?= $all_data_json ?: '[]' ?>;
var MONTHLY_DATA=<?= $monthly_data_json ?: '{}' ?>;
var SERVER_AVAIL=<?= $server_avail_json ?: '{}' ?>;
var HAS_REAL_DATA=<?= $has_real_data ? 'true' : 'false' ?>;

(function(){var sel=document.getElementById('plantFilter');sel.querySelectorAll('option').forEach(function(o){var v=o.value;if(v!=='all'&&SERVER_AVAIL[v]===false){o.disabled=true;o.textContent+=' — offline';}if(v==='all'&&!SERVER_AVAIL.p1&&!SERVER_AVAIL.p2){o.disabled=true;o.textContent+=' (semua offline)';}});if(sel.options[sel.selectedIndex].disabled){for(var i=0;i<sel.options.length;i++){if(!sel.options[i].disabled){sel.selectedIndex=i;break;}}}sel.addEventListener('change',function(){if(this.value!=='all'&&SERVER_AVAIL[this.value]===false){showToast('Plant ini sedang offline');return;}window.location.href='?plant='+this.value;});})();

if(!HAS_REAL_DATA){var msg='';var p1=SERVER_AVAIL.p1,p2=SERVER_AVAIL.p2;if(p1===false&&p2===false)msg='Kedua server tidak merespon.';else if(p1===false)msg='Plant 1 tidak merespon.';else if(p2===false)msg='Plant 2 tidak merespon.';else msg='Tidak ada data ditemukan.';document.getElementById('emptyMsg').textContent=msg;}

if(HAS_REAL_DATA&&ALL_DATA.length>0){
var ML=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
var MIN_Y=ALL_DATA[0].year,MAX_Y=ALL_DATA[ALL_DATA.length-1].year,MAX_SP=10;
var chInst=null,chMInst=null,curEnd=MAX_Y,curStart=Math.max(MIN_Y,MAX_Y-4),selYear=null;

function fS(n){if(n>=1e12)return(n/1e12).toFixed(1)+'T';if(n>=1e9)return(n/1e9).toFixed(1)+'M';if(n>=1e6)return(n/1e6).toFixed(1)+'Jt';if(n>=1e3)return(n/1e3).toFixed(0)+'Rb';return n.toString();}
function fF(n){return'Rp '+n.toLocaleString('id-ID',{maximumFractionDigits:0});}

function initSel(){var sS=document.getElementById('startYear'),sE=document.getElementById('endYear');for(var y=MIN_Y;y<=MAX_Y;y++){var oS=document.createElement('option');oS.value=y;oS.textContent=y;sS.appendChild(oS);var oE=document.createElement('option');oE.value=y;oE.textContent=y;sE.appendChild(oE);}sS.value=curStart;sE.value=curEnd;sS.addEventListener('change',onRC);sE.addEventListener('change',onRC);}
function onRC(){var s=parseInt(document.getElementById('startYear').value),e=parseInt(document.getElementById('endYear').value);if(e<s){e=s;document.getElementById('endYear').value=e;}if(e-s+1>MAX_SP){e=s+MAX_SP-1;document.getElementById('endYear').value=e;showToast('Maksimal '+MAX_SP+' tahun');}curStart=s;curEnd=e;clrAP();closeMP();updAll();}
window.applyPreset=function(t){clrAP();var s,e;var sp=parseInt(t);e=MAX_Y;s=e-sp+1;if(s<MIN_Y)s=MIN_Y;curStart=s;curEnd=e;document.getElementById('startYear').value=s;document.getElementById('endYear').value=e;document.querySelectorAll('.preset-btn').forEach(function(b){if(b.getAttribute('onclick').indexOf(t)!==-1)b.classList.add('active');});closeMP();updAll();showToast('Menampilkan '+(e-s+1)+' tahun ('+s+'–'+e+')');};
function clrAP(){document.querySelectorAll('.preset-btn').forEach(function(b){b.classList.remove('active');});}

function updAll(){var f=ALL_DATA.filter(function(d){return d.year>=curStart&&d.year<=curEnd;});document.getElementById('rangeText').textContent=f.length+' tahun';document.getElementById('rangeCount').textContent=f.length;document.getElementById('periodLabel').textContent=curStart+' s/d '+curEnd;updChart(f);updSum(f);genTTable(f);}

function updChart(data){
var isL=window._pdfLM===true;var ctx=document.getElementById('annualBarChart').getContext('2d');
var sG=ctx.createLinearGradient(0,0,0,400);sG.addColorStop(0,isL?'#60A5FA':'#6BA3F7');sG.addColorStop(1,isL?'#2563EB':'#4C8BF5');
var lb=data.map(function(d){return d.year.toString();}),tV=data.map(function(d){return d.total;}); // HANYA TOTAL
var bP=data.length<=5?0.45:data.length<=7?0.35:0.25;
var C=isL?{sB:'#2563EB',ttBg:'#FFFFFF',ttT:'#111827',ttBd:'#4B5563',ttBr:'#E5E7EB',grd:'rgba(0,0,0,0.06)',xT:'#374151',yT:'#6B7280',xBd:'#E5E7EB'}:{sB:'#4C8BF5',ttBg:'#1C2333',ttT:'#E6EDF3',ttBd:'#8B949E',ttBr:'#30363D',grd:'rgba(48,54,61,0.4)',xT:'#E6EDF3',yT:'#8B949E',xBd:'#30363D'};

if(chInst){chInst.data.labels=lb;chInst.data.datasets[0].data=tV;chInst.update('active');
}else{chInst=new Chart(ctx,{type:'bar',data:{labels:lb,datasets:[{label:'Total Sales',type:'bar',data:tV,backgroundColor:sG,borderColor:C.sB,borderWidth:1,borderRadius:{topLeft:6,topRight:6},barPercentage:bP,categoryPercentage:0.7}]},options:{responsive:true,maintainAspectRatio:false,layout:{padding:{top:20}},interaction:{mode:'index',intersect:false},onClick:function(evt,el){if(el.length>0)togMP(parseInt(chInst.data.labels[el[0].index]));},plugins:{legend:{display:false},tooltip:{backgroundColor:C.ttBg,titleColor:C.ttT,bodyColor:C.ttBd,borderColor:C.ttBr,borderWidth:1,cornerRadius:8,padding:14,callbacks:{title:function(i){return'Tahun '+i[0].label;},label:function(c){
var d=data[c.dataIndex];var pV=d.total>0?((d.vendor/d.total)*100).toFixed(1):0;var pI=d.total>0?((d.internal/d.total)*100).toFixed(1):0;
return[' Total Sales: '+fF(d.total),' └ Vendor: '+fF(d.vendor)+' ('+pV+'%)',' └ Internal: '+fF(d.internal)+' ('+pI+'%)'];
}}}},scales:{x:{grid:{display:false},ticks:{color:C.xT,font:{family:'Space Grotesk',size:13,weight:'600'}},border:{color:C.xBd}},y:{beginAtZero:true,grid:{color:C.grd},ticks:{color:C.yT,font:{family:'Space Grotesk',size:11},callback:function(v){return fS(v);}},border:{display:false}}},animation:{duration:isL?0:800,easing:'easeOutQuart'}}});}
}

function togMP(y){if(selYear===y)closeMP();else openMP(y);}
function openMP(y){selYear=y;document.getElementById('monthlyYearBadge').textContent=y;document.getElementById('tabBulananYear').textContent=y;document.getElementById('lblTableBulananYear').textContent=y;document.getElementById('monthlyPanel').classList.add('open');updMChart(y);updMSum(y);genBTable(y);swTab('bulanan');showToast('Detail bulanan tahun '+y);setTimeout(function(){document.getElementById('monthlyPanel').scrollIntoView({behavior:'smooth',block:'nearest'});},200);}
function closeMP(){selYear=null;document.getElementById('tabBulananYear').textContent='Pilih Tahun';document.getElementById('monthlyPanel').classList.remove('open');swTab('tahunan');}
document.getElementById('closeMonthly').addEventListener('click',closeMP);

function updMChart(year){
var md=MONTHLY_DATA[year];if(!md)return;var isL=window._pdfLM===true;var ctx=document.getElementById('monthlyBarChart').getContext('2d');
var sG=ctx.createLinearGradient(0,0,0,280);sG.addColorStop(0,isL?'#60A5FA':'#6BA3F7');sG.addColorStop(1,isL?'#2563EB':'#4C8BF5');
var C=isL?{sB:'#2563EB',ttBg:'#FFFFFF',ttT:'#111827',ttBd:'#4B5563',ttBr:'#E5E7EB',grd:'rgba(0,0,0,0.06)',xT:'#374151',yT:'#6B7280',xBd:'#E5E7EB'}:{sB:'#4C8BF5',ttBg:'#1C2333',ttT:'#E6EDF3',ttBd:'#8B949E',ttBr:'#30363D',grd:'rgba(48,54,61,0.4)',xT:'#E6EDF3',yT:'#8B949E',xBd:'#30363D'};
var tV=md.total; // HANYA TOTAL
if(chMInst){chMInst.data.datasets[0].data=tV;chMInst.update('active');}
else{chMInst=new Chart(ctx,{type:'bar',data:{labels:ML,datasets:[{label:'Total Sales',type:'bar',data:tV,backgroundColor:sG,borderColor:C.sB,borderWidth:1,barPercentage:0.5,categoryPercentage:0.7}]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:false},tooltip:{backgroundColor:C.ttBg,titleColor:C.ttT,bodyColor:C.ttBd,borderColor:C.ttBr,borderWidth:1,cornerRadius:6,padding:10,callbacks:{label:function(c){
var i=c.dataIndex;var d_v=md.vendor[i],d_i=md.internal[i],d_t=md.total[i];var pV=d_t>0?((d_v/d_t)*100).toFixed(1):0;var pI=d_t>0?((d_i/d_t)*100).toFixed(1):0;
return[' Total Sales: '+fF(d_t),' └ Vendor: '+fF(d_v)+' ('+pV+'%)',' └ Internal: '+fF(d_i)+' ('+pI+'%)'];
}}}},scales:{x:{grid:{display:false},ticks:{color:C.xT,font:{size:10}},border:{color:C.xBd}},y:{beginAtZero:true,position:'left',grid:{color:C.grd},ticks:{color:C.yT,font:{size:10},callback:function(v){return fS(v);}},border:{display:false}}}}});}
}

function updMSum(year){
var md=MONTHLY_DATA[year];if(!md)return;
var tv=0,ti=0,tt=0;for(var i=0;i<12;i++){tv+=md.vendor[i];ti+=md.internal[i];tt+=md.total[i];}
document.getElementById('monthlySummary').innerHTML=
'<div class="ms-item"><div class="ms-label">Total Sales</div><div class="ms-val ms">'+fF(tt)+'</div></div>'+
'<div class="ms-item"><div class="ms-label">Vendor ('+( tt>0?((tv/tt)*100).toFixed(1):0 )+'%)</div><div class="ms-val" style="color:var(--vendor)">'+fF(tv)+'</div></div>'+
'<div class="ms-item"><div class="ms-label">Internal ('+( tt>0?((ti/tt)*100).toFixed(1):0 )+'%)</div><div class="ms-val" style="color:var(--internal)">'+fF(ti)+'</div></div>';
}

function updSum(data){
var tv=0,ti=0,tt=0;data.forEach(function(d){tv+=d.vendor;ti+=d.internal;tt+=d.total;});
document.getElementById('summaryRow').innerHTML=
'<div class="summary-card ss"><div class="sc-icon"><i class="fas fa-money-bill-trend-up"></i></div><div class="sc-label">Total Sales</div><div class="sc-num">'+fF(tt)+'</div><div class="sc-sub">Vendor: <span class="pct pv">'+fF(tv)+'</span> ('+( tt>0?((tv/tt)*100).toFixed(1):0 )+'%)</div></div>'+
'<div class="summary-card sa"><div class="sc-icon"><i class="fas fa-industry"></i></div><div class="sc-label">Sales Internal</div><div class="sc-num">'+fF(ti)+'</div><div class="sc-sub">Kontribusi: <span class="pct ps">'+( tt>0?((ti/tt)*100).toFixed(1):0 )+'%</span></div></div>';
}

function genTTable(data){var tb=document.getElementById('tbodyTahunan');var html='';var tv=0,ti=0,tt=0;data.forEach(function(d){tv+=d.vendor;ti+=d.internal;tt+=d.total;html+='<tr><td>'+d.year+'</td><td style="text-align:right;" class="t-vendor">'+fF(d.vendor)+'</td><td style="text-align:right;" class="t-internal">'+fF(d.internal)+'</td><td style="text-align:right;" class="t-total">'+fF(d.total)+'</td></tr>';});html+='<tr class="total-row"><td>TOTAL</td><td style="text-align:right;">'+fF(tv)+'</td><td style="text-align:right;">'+fF(ti)+'</td><td style="text-align:right;">'+fF(tt)+'</td></tr>';tb.innerHTML=html;}

function genBTable(year){var md=MONTHLY_DATA[year];if(!md)return;var tb=document.getElementById('tbodyBulanan');var html='';var tv=0,ti=0,tt=0;for(var i=0;i<12;i++){var v=md.vendor[i],ii=md.internal[i],t=md.total[i];tv+=v;ti+=ii;tt+=t;html+='<tr><td>'+ML[i]+'</td><td style="text-align:right;" class="t-vendor">'+fF(v)+'</td><td style="text-align:right;" class="t-internal">'+fF(ii)+'</td><td style="text-align:right;" class="t-total">'+fF(t)+'</td></tr>';}html+='<tr class="total-row"><td>TOTAL</td><td style="text-align:right;">'+fF(tv)+'</td><td style="text-align:right;">'+fF(ti)+'</td><td style="text-align:right;">'+fF(tt)+'</td></tr>';tb.innerHTML=html;}

window.switchTableTab=swTab;
function swTab(t){document.getElementById('tabTahunanBtn').classList.toggle('active',t==='tahunan');document.getElementById('tabBulananBtn').classList.toggle('active',t==='bulanan');document.getElementById('wrapperTahunan').style.display=t==='tahunan'?'':'none';document.getElementById('wrapperBulanan').style.display=t==='bulanan'?'':'none';}

function showToast(m){var t=document.getElementById('toast');document.getElementById('toastMsg').textContent=m;t.classList.add('show');setTimeout(function(){t.classList.remove('show');},2500);}

window.generatePDF=function(){
var btn=document.getElementById('pdfBtn');btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Memformat...';
window._pdfLM=true;if(chInst)updChart(ALL_DATA.filter(function(d){return d.year>=curStart&&d.year<=curEnd;}));if(chMInst&&selYear)updMChart(selYear);
document.body.classList.add('pdf-export-mode');
setTimeout(function(){
var element=document.getElementById('mainContainer');var opt={margin:[6,6,6,6],filename:'Laporan_Total_Sales.pdf',image:{type:'jpeg',quality:0.98},html2canvas:{scale:2,useCORS:true,letterRendering:true},jsPDF:{unit:'mm',format:'a3',orientation:'landscape'}};
html2pdf().set(opt).from(element).save().then(function(){
document.body.classList.remove('pdf-export-mode');window._pdfLM=false;
if(chInst)updChart(ALL_DATA.filter(function(d){return d.year>=curStart&&d.year<=curEnd;}));if(chMInst&&selYear)updMChart(selYear);
btn.disabled=false;btn.innerHTML='<i class="fas fa-file-pdf"></i> Download PDF A3';showToast('PDF berhasil diunduh');
}).catch(function(){document.body.classList.remove('pdf-export-mode');window._pdfLM=false;btn.disabled=false;btn.innerHTML='<i class="fas fa-file-pdf"></i> Download PDF A3';});
},600);
};

initSel();updAll();
}
</script>
</body>
</html>
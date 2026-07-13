<?php
// ============================================
// SAFETY: Batasi maksimal eksekusi 20 detik
// ============================================
set_time_limit(20);
session_start();

require_once __DIR__ . "/../config/database_ordering.php";

if (!isset($_SESSION['db_user']) || $_SESSION['db_user'] == "") {
    die('<div style="padding:24px;color:#F85149;background:#FFFFFF;font-family:monospace;">Silakan login terlebih dahulu.</div>');
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
                $detail = 'Login gagal — username/password tidak valid';
            } elseif (stripos($nativeMsg, 'network') !== false) {
                $detail = 'Tidak dapat menjangkau server (jaringan/firewall)';
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

if ($selected_plant === 'p1' && !$p1_ok && $p2_ok) {
    $selected_plant = 'p2';
    $conn_errors[] = "<i class=\"fas fa-arrow-right-arrow-left\"></i> <strong>Auto-fallback:</strong> Plant 1 offline, dialihkan ke Plant 2.";
    if (!isset($server_available['p2'])) {
        $conn = @sqlsrv_connect($servers_config['p2']['ip'], $conn_opts_base);
        if ($conn) {
            $server_available['p2'] = true; $p2_ok = true; $any_ok = true;
            $stmt = @sqlsrv_query($conn, $sql);
            if ($stmt) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $year=(int)$row['SALES_YEAR'];$month=(int)$row['SALES_MONTH'];$is_vendor=(int)$row['IS_VENDOR'];$sales=(float)$row['TOTAL_SALES'];
                    if($year<$min_year)$min_year=$year;if($year>$max_year)$max_year=$year;
                    if(!isset($raw_yearly[$year])){$raw_yearly[$year]=['year'=>$year,'vendor'=>0,'internal'=>0];$raw_monthly[$year]=['vendor'=>array_fill(0,12,0),'internal'=>array_fill(0,12,0)];}
                    if($is_vendor===1){$raw_yearly[$year]['vendor']+=$sales;$raw_monthly[$year]['vendor'][$month-1]+=$sales;}else{$raw_yearly[$year]['internal']+=$sales;$raw_monthly[$year]['internal'][$month-1]+=$sales;}
                }
                sqlsrv_free_stmt($stmt);
            }
            sqlsrv_close($conn);
        } else { $server_available['p2'] = false; }
    }
} elseif ($selected_plant === 'p2' && !$p2_ok && $p1_ok) {
    $selected_plant = 'p1';
    $conn_errors[] = "<i class=\"fas fa-arrow-right-arrow-left\"></i> <strong>Auto-fallback:</strong> Plant 2 offline, dialihkan ke Plant 1.";
    if (!isset($server_available['p1'])) {
        $conn = @sqlsrv_connect($servers_config['p1']['ip'], $conn_opts_base);
        if ($conn) {
            $server_available['p1'] = true; $p1_ok = true; $any_ok = true;
            $stmt = @sqlsrv_query($conn, $sql);
            if ($stmt) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $year=(int)$row['SALES_YEAR'];$month=(int)$row['SALES_MONTH'];$is_vendor=(int)$row['IS_VENDOR'];$sales=(float)$row['TOTAL_SALES'];
                    if($year<$min_year)$min_year=$year;if($year>$max_year)$max_year=$year;
                    if(!isset($raw_yearly[$year])){$raw_yearly[$year]=['year'=>$year,'vendor'=>0,'internal'=>0];$raw_monthly[$year]=['vendor'=>array_fill(0,12,0),'internal'=>array_fill(0,12,0)];}
                    if($is_vendor===1){$raw_yearly[$year]['vendor']+=$sales;$raw_monthly[$year]['vendor'][$month-1]+=$sales;}else{$raw_yearly[$year]['internal']+=$sales;$raw_monthly[$year]['internal'][$month-1]+=$sales;}
                }
                sqlsrv_free_stmt($stmt);
            }
            sqlsrv_close($conn);
        } else { $server_available['p1'] = false; }
    }
}

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
            $yearly_data[] = ['year' => $y, 'vendor' => 0, 'internal' => 0];
            $monthly_data[$y] = ['vendor' => array_fill(0, 12, 0), 'internal' => array_fill(0, 12, 0)];
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
    <title>Grafik & Tabel Tahunan + Bulanan — Internal vs Vendor</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>
    <style>
        :root{--bg:#FFFFFF;--card:#FFFFFF;--border:#E5E7EB;--text:#000000;--muted:#000000;--vendor:#EA580C;--vendor-dim:rgba(234,88,12,0.10);--internal:#059669;--internal-dim:rgba(5,150,105,0.10);--accent:#D97706;--accent-dim:rgba(217,119,6,0.10);--danger:#DC2626;--r:12px;--rs:6px;}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;padding:24px 20px 60px;position:relative;overflow-x:hidden;}
        body::before{content:'';position:fixed;top:-250px;right:-150px;width:600px;height:600px;background:radial-gradient(circle,rgba(234,88,12,0.035) 0%,transparent 65%);pointer-events:none;animation:floatB 12s ease-in-out infinite alternate;}
        body::after{content:'';position:fixed;bottom:-250px;left:-150px;width:600px;height:600px;background:radial-gradient(circle,rgba(5,150,105,0.035) 0%,transparent 65%);pointer-events:none;animation:floatB 14s ease-in-out infinite alternate-reverse;}
        @keyframes floatB{0%{transform:translate(0,0) scale(1);}100%{transform:translate(30px,-20px) scale(1.08);}}
        .container{max-width:1100px;margin:0 auto;position:relative;z-index:1;opacity:0;transform:translateY(20px);animation:fadeUp .6s ease forwards;background:var(--bg);}
        @keyframes fadeUp{to{opacity:1;transform:translateY(0);}}

        .header{margin-bottom:16px;}
        .header-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:15px;}
        .header-title-group{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
        .header h1{font-family:'Space Grotesk',sans-serif;font-size:22px;font-weight:700;letter-spacing:-.3px;}
        .header h1 i{color:var(--accent);margin-right:6px;font-size:18px;}
        .plant-badge{background:var(--accent-dim);color:var(--accent);font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;letter-spacing:.5px;}
        .plant-badge.offline-badge{background:rgba(248,81,73,0.12);color:var(--danger);}
        .header-sub{color:var(--muted);font-size:13px;}
        .header-sub b{color:var(--text);font-weight:600;}

        .header-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
        .btn-pdf,.btn-print,.btn-excel{border:none;padding:8px 16px;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;transition:all 0.2s;white-space:nowrap;}
        .btn-pdf{background:var(--accent);color:#FFFFFF;box-shadow:0 4px 12px rgba(217,119,6,0.20);}
        .btn-pdf:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(217,119,6,0.28);}
        .btn-print{background:#2563EB;color:#FFFFFF;box-shadow:0 4px 12px rgba(37,99,235,0.18);}
        .btn-print:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(37,99,235,0.28);}
        .btn-excel{background:#15803D;color:#FFFFFF;box-shadow:0 4px 12px rgba(21,128,61,0.20);}
        .btn-excel:hover{transform:translateY(-2px);background:#166534;box-shadow:0 6px 16px rgba(21,128,61,0.28);}
        .btn-pdf:disabled,.btn-print:disabled,.btn-excel:disabled{opacity:.6;cursor:wait;transform:none;}

        .err-box{background:rgba(248,81,73,0.08);border:1px solid rgba(248,81,73,0.25);border-radius:10px;padding:14px 18px;margin-bottom:12px;}
        .err-box .err-title{color:var(--danger);font-size:12px;font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:6px;text-transform:uppercase;letter-spacing:.5px;}
        .err-box .err-item{color:rgba(248,81,73,0.85);font-size:12px;line-height:1.7;padding-left:18px;position:relative;}
        .err-box .err-item:not(:last-child){margin-bottom:2px;}
        .err-box .err-item::before{content:'';position:absolute;left:4px;top:9px;width:5px;height:5px;border-radius:50%;background:rgba(248,81,73,0.5);}
        .err-box .err-item i{margin-right:4px;}

        .bypass-box{background:rgba(0,212,170,0.06);border:1px solid rgba(0,212,170,0.2);border-radius:10px;padding:12px 18px;margin-bottom:16px;font-size:12px;color:rgba(0,212,170,0.9);display:flex;align-items:flex-start;gap:8px;line-height:1.5;}
        .bypass-box i{margin-top:2px;font-size:14px;flex-shrink:0;}

        .filter-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:12px 18px;margin-bottom:12px;}
        .filter-bar,.chart-card,.monthly-card,.summary-card,.table-card{box-shadow:0 6px 20px rgba(15,23,42,0.045);}
        .filter-bar label{font-size:12px;color:var(--muted);font-weight:600;white-space:nowrap;}
        .filter-bar select{background:var(--bg);border:1px solid var(--border);color:var(--text);padding:8px 32px 8px 12px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;outline:none;cursor:pointer;transition:border .2s;appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238B949E' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;}
        .filter-bar select:focus{border-color:var(--accent);}
        .filter-bar select option:disabled{color:#9CA3AF;background:#F3F4F6;}
        .sep{width:1px;height:24px;background:var(--border);margin:0 2px;}
        .range-hint{font-size:11px;color:var(--muted);display:flex;align-items:center;gap:5px;margin-left:auto;}
        .range-hint i{font-size:10px;}
        .range-hint .count-badge{background:var(--accent-dim);color:var(--accent);font-weight:700;padding:2px 8px;border-radius:10px;font-size:11px;font-family:'Space Grotesk',sans-serif;}

        .preset-row{display:flex;align-items:center;gap:6px;margin-bottom:16px;flex-wrap:wrap;}
        .preset-row span{font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-right:4px;}
        .preset-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border-radius:20px;font-family:'DM Sans',sans-serif;font-size:11px;font-weight:600;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;transition:all .2s;white-space:nowrap;}
        .preset-btn:hover{border-color:var(--muted);color:var(--text);transform:translateY(-1px);}
        .preset-btn.active{border-color:var(--accent);color:var(--accent);background:var(--accent-dim);}

        .chart-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:28px 32px 24px;position:relative;overflow:hidden;margin-bottom:16px;}
        .chart-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--vendor) 0%,var(--accent) 50%,var(--internal) 100%);}
        .chart-title{font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:24px;display:flex;align-items:center;gap:8px;}
        .chart-title i{color:var(--accent);font-size:14px;}
        .chart-title .click-hint{font-size:11px;font-weight:400;color:var(--muted);text-transform:none;letter-spacing:0;margin-left:auto;display:flex;align-items:center;gap:5px;opacity:.7;}
        .chart-area{position:relative;width:100%;height:380px;}
        .chart-area canvas{width:100%!important;height:100%!important;}

        .empty-state{display:none;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;text-align:center;}
        .empty-state.visible{display:flex;}
        .empty-state .empty-icon{width:80px;height:80px;border-radius:50%;background:rgba(248,81,73,0.08);display:flex;align-items:center;justify-content:center;font-size:32px;color:var(--danger);margin-bottom:20px;animation:pulse 2s ease-in-out infinite;}
        @keyframes pulse{0%,100%{transform:scale(1);opacity:1;}50%{transform:scale(1.05);opacity:.8;}}
        .empty-state h3{font-family:'Space Grotesk',sans-serif;font-size:18px;font-weight:700;color:var(--text);margin-bottom:8px;}
        .empty-state p{font-size:13px;color:var(--muted);max-width:440px;line-height:1.7;}

        .custom-legend{display:flex;align-items:center;justify-content:center;gap:32px;margin-top:20px;padding-top:18px;border-top:1px solid var(--border);flex-wrap:wrap;}
        .legend-item{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);font-weight:500;cursor:default;}
        .legend-dot{width:12px;height:12px;border-radius:3px;}
        .legend-dot.vendor{background:linear-gradient(180deg,#FF8C5A,#FF6B35);}
        .legend-dot.internal{background:linear-gradient(180deg,#33E0BE,#00D4AA);}

        .monthly-panel{max-height:0;overflow:hidden;opacity:0;transform:translateY(-8px);transition:max-height .5s cubic-bezier(.4,0,.2,1),opacity .4s ease .05s,transform .4s ease .05s,margin-top .4s ease;margin-top:0;}
        .monthly-panel.open{max-height:700px;opacity:1;transform:translateY(0);margin-bottom:16px;}
        .monthly-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:24px 28px 20px;position:relative;overflow:hidden;}
        .monthly-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--accent) 0%,var(--internal) 50%,var(--accent) 100%);}
        .monthly-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;}
        .monthly-header-left{display:flex;align-items:center;gap:10px;}
        .monthly-header-left .year-badge{background:var(--accent-dim);color:var(--accent);font-family:'Space Grotesk',sans-serif;font-size:15px;font-weight:700;padding:5px 14px;border-radius:8px;}
        .monthly-header-left h3{font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:1px;}
        .close-btn{width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--muted);font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;}
        .close-btn:hover{background:rgba(248,81,73,.1);border-color:var(--danger);color:var(--danger);}
        .monthly-chart-area{position:relative;width:100%;height:250px;}

        .monthly-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;margin-top:16px;padding-top:14px;border-top:1px solid var(--border);}
        .ms-item{display:flex;flex-direction:column;gap:2px;}
        .ms-item .ms-label{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;font-weight:600;}
        .ms-item .ms-val{font-family:'Space Grotesk',sans-serif;font-size:15px;font-weight:700;}
        .ms-item .ms-val.mv{color:var(--vendor);}.ms-item .ms-val.mi{color:var(--internal);}.ms-item .ms-val.ma{color:var(--accent);}

        .summary-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:16px;}
        .summary-card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:16px 18px;position:relative;overflow:hidden;opacity:0;transform:translateY(12px);animation:fadeUp .4s ease forwards;}
        .summary-card:nth-child(1){animation-delay:.15s}.summary-card:nth-child(2){animation-delay:.2s}.summary-card:nth-child(3){animation-delay:.25s}.summary-card:nth-child(4){animation-delay:.3s}.summary-card:nth-child(5){animation-delay:.35s}.summary-card:nth-child(6){animation-delay:.4s}
        .summary-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;}
        .summary-card.sv::before{background:var(--vendor);}.summary-card.si::before{background:var(--internal);}.summary-card.sa::before{background:var(--accent);}
        .sc-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;margin-bottom:8px;}
        .sv .sc-icon{background:var(--vendor-dim);color:var(--vendor);}.si .sc-icon{background:var(--internal-dim);color:var(--internal);}.sa .sc-icon{background:var(--accent-dim);color:var(--accent);}
        .sc-label{font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:600;margin-bottom:4px;}
        .sc-num{font-family:'Space Grotesk',sans-serif;font-size:20px;font-weight:700;line-height:1;}
        .sc-sub{font-size:11px;color:var(--muted);margin-top:5px;}
        .sc-sub .pct{font-weight:700;font-size:12px;}
        .pv{color:var(--vendor);}.pi{color:var(--internal);}

        /* ===== TABEL (LAYAR) ===== */
        .table-card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:24px 20px;position:relative;overflow:visible;page-break-inside:avoid;}
        .table-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--internal) 0%,var(--accent) 50%,var(--vendor) 100%);}
        .table-wrapper{overflow-x:auto;margin-top:12px;}
        .data-table{width:100%;border-collapse:collapse;font-size:12px;text-align:left;}
        .data-table th{font-family:'Space Grotesk',sans-serif;background:#F3F4F6;color:var(--text);padding:8px 10px;font-weight:600;border-bottom:2px solid var(--border);white-space:nowrap;font-size:11px;}
        .data-table td{padding:7px 10px;border-bottom:1px solid #EEF2F7;color:var(--text);font-family:'Space Grotesk',sans-serif;font-size:11px;white-space:nowrap;}
        .data-table tbody tr:hover{background:#F9FAFB;}
        .data-table tr.total-row{background:rgba(255,217,61,0.05)!important;font-weight:bold;}
        .data-table tr.total-row td{border-top:2px solid var(--border);border-bottom:2px solid var(--border);color:var(--accent);}
        .t-vendor{color:var(--vendor)!important;}
        .t-internal{color:var(--internal)!important;}
        .t-pct-v{color:var(--vendor)!important;font-weight:600!important;}
        .t-pct-i{color:var(--internal)!important;font-weight:600!important;}
        .data-table .col-year{min-width:50px;text-align:center;}
        .data-table .col-vendor{min-width:175px;text-align:right;}
        .data-table .col-internal{min-width:175px;text-align:right;}
        .data-table .col-pct-v{min-width:52px;text-align:center;}
        .data-table .col-pct-i{min-width:52px;text-align:center;}
        .data-table .col-total{min-width:175px;text-align:right;font-weight:600;}
        .data-table .col-bar{min-width:65px;}

        .pct-bar-cell{display:flex;align-items:center;justify-content:center;}
        .pct-bar-track{width:38px;height:6px;border-radius:3px;background:rgba(17,24,39,0.08);overflow:hidden;position:relative;}
        .pct-bar-fill-v{position:absolute;left:0;top:0;height:100%;border-radius:3px;background:var(--vendor);transition:width .4s ease;}
        .pct-bar-fill-i{position:absolute;right:0;top:0;height:100%;border-radius:3px;background:var(--internal);transition:width .4s ease;}

        .table-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-left:auto;}
        .table-tabs{display:flex;gap:8px;border-bottom:1px solid var(--border);padding-bottom:10px;}
        .tab-btn{background:transparent;border:1px solid var(--border);color:var(--muted);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;font-family:'DM Sans',sans-serif;transition:all 0.2s;}
        .tab-btn:hover{border-color:var(--muted);color:var(--text);}
        .tab-btn.active{background:var(--accent-dim);color:var(--accent);border-color:var(--accent);}
        .table-title-container{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:10px;}

        .toast{position:fixed;bottom:24px;right:24px;background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:12px 20px;font-size:13px;color:var(--text);z-index:999;opacity:0;transform:translateY(12px);transition:all .3s;display:flex;align-items:center;gap:8px;}
        .toast.show{opacity:1;transform:translateY(0);}
        .particle{position:fixed;width:2px;height:2px;border-radius:50%;pointer-events:none;opacity:0;}


        /* ===== SEMUA TULISAN HITAM ===== */
        body,
        .header h1,
        .header-sub,
        .header-sub b,
        .filter-bar label,
        .filter-bar select,
        .range-hint,
        .preset-row span,
        .preset-btn,
        .chart-title,
        .chart-title .click-hint,
        .legend-item,
        .monthly-header-left h3,
        .close-btn,
        .ms-item .ms-label,
        .ms-item .ms-val,
        .sc-label,
        .sc-num,
        .sc-sub,
        .sc-sub .pct,
        .data-table th,
        .data-table td,
        .tab-btn,
        .tab-btn.active,
        .plant-badge,
        .year-badge,
        .empty-state h3,
        .empty-state p,
        .toast,
        .t-vendor,
        .t-internal,
        .t-pct-v,
        .t-pct-i,
        .ms-val.mv,
        .ms-val.mi,
        .ms-val.ma,
        .pv,
        .pi {
            color:#000000 !important;
        }
        .filter-bar select option{color:#000000 !important;background:#FFFFFF !important;}

        /* ================================================
           PDF MODE — FIX TOTAL: lebar tetap + kolom persen
           ================================================ */
        .pdf-export-mode,
        .pdf-export-mode::before,
        .pdf-export-mode::after {
            background: #FFFFFF !important;
            animation: none !important;
        }
        /* Container DIPAKSA 1540px — pas A3 Landscape margin 6mm */
        .pdf-export-mode .container {
            background: #FFFFFF !important;
            padding: 8px 12px !important;
            max-width: 1540px !important;
            width: 1540px !important;
            transform: none !important;
            opacity: 1 !important;
            animation: none !important;
            margin: 0 !important;
        }

        .pdf-export-mode,
        .pdf-export-mode .header h1,
        .pdf-export-mode .header-sub,
        .pdf-export-mode .header-sub b,
        .pdf-export-mode .chart-title,
        .pdf-export-mode .chart-title .click-hint,
        .pdf-export-mode .legend-item,
        .pdf-export-mode .monthly-header-left h3,
        .pdf-export-mode .ms-label,
        .pdf-export-mode .ms-val,
        .pdf-export-mode .sc-label,
        .pdf-export-mode .sc-num,
        .pdf-export-mode .sc-sub,
        .pdf-export-mode .sc-sub .pct,
        .pdf-export-mode .data-table th,
        .pdf-export-mode .data-table td,
        .pdf-export-mode .t-vendor,
        .pdf-export-mode .t-internal,
        .pdf-export-mode .t-pct-v,
        .pdf-export-mode .t-pct-i,
        .pdf-export-mode .plant-badge,
        .pdf-export-mode .year-badge,
        .pdf-export-mode .ms-val.mv,
        .pdf-export-mode .ms-val.mi,
        .pdf-export-mode .ms-val.ma,
        .pdf-export-mode .pv,
        .pdf-export-mode .pi {
            color:#000000 !important;
        }
        .pdf-export-mode .header h1 { color: #000000 !important; font-size: 16px !important; }
        .pdf-export-mode .header h1 i { color: #D97706 !important; }
        .pdf-export-mode .header-sub { color: #000000 !important; font-size: 10px !important; }
        .pdf-export-mode .header-sub b { color: #000000 !important; }
        .pdf-export-mode .plant-badge { background: #FEF3C7 !important; color: #000000 !important; }
        .pdf-export-mode .chart-card,
        .pdf-export-mode .monthly-card,
        .pdf-export-mode .table-card {
            background: #FFFFFF !important;
            border-color: #D1D5DB !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06) !important;
            padding: 10px 12px !important;
            margin-bottom: 6px !important;
            overflow: visible !important;
            width: 100% !important;
        }
        .pdf-export-mode .chart-card::before,
        .pdf-export-mode .monthly-card::before,
        .pdf-export-mode .table-card::before {
            background: linear-gradient(90deg, #F97316, #EAB308, #10B981) !important;
        }
        .pdf-export-mode .chart-title { color: #000000 !important; font-size: 10px !important; margin-bottom: 10px !important; }
        .pdf-export-mode .chart-title i { color: #D97706 !important; }
        .pdf-export-mode .chart-area { height: 260px !important; }
        .pdf-export-mode .monthly-chart-area { height: 180px !important; }
        .pdf-export-mode .summary-row { gap: 5px !important; margin-bottom: 6px !important; }
        .pdf-export-mode .summary-card {
            background: #F9FAFB !important;
            border-color: #E5E7EB !important;
            box-shadow: none !important;
            padding: 7px 9px !important;
            opacity: 1 !important; transform: none !important; animation: none !important;
        }
        .pdf-export-mode .sc-icon { width: 22px !important; height: 22px !important; font-size: 9px !important; margin-bottom: 3px !important; }
        .pdf-export-mode .sv .sc-icon { background: #FFF7ED !important; color: #EA580C !important; }
        .pdf-export-mode .si .sc-icon { background: #ECFDF5 !important; color: #059669 !important; }
        .pdf-export-mode .sa .sc-icon { background: #FEF9C3 !important; color: #CA8A04 !important; }
        .pdf-export-mode .sc-label { color: #000000 !important; font-size: 8px !important; }
        .pdf-export-mode .sc-num { color: #000000 !important; font-size: 13px !important; }
        .pdf-export-mode .sc-sub { color: #000000 !important; font-size: 8px !important; }
        .pdf-export-mode .sc-sub .pct.pv { color: #000000 !important; }
        .pdf-export-mode .sc-sub .pct.pi { color: #000000 !important; }

        /* ===== TABEL PDF — KOLOM PERSEN, TIDAK TERPOTONG ===== */
        .pdf-export-mode .table-wrapper { overflow: visible !important; margin-top: 6px !important; width: 100% !important; }
        .pdf-export-mode .data-table {
            width: 100% !important;
            table-layout: fixed !important;
            font-size: 9px !important;
        }
        /* 7 kolom total = 100%. Kolom angka besar dapat porsi lebih. */
        .pdf-export-mode .data-table .col-year      { width: 4.5% !important; min-width: 0 !important; text-align: center !important; }
        .pdf-export-mode .data-table .col-vendor     { width: 23% !important; min-width: 0 !important; text-align: right !important; }
        .pdf-export-mode .data-table .col-internal   { width: 23% !important; min-width: 0 !important; text-align: right !important; }
        .pdf-export-mode .data-table .col-pct-v      { width: 6% !important; min-width: 0 !important; text-align: center !important; }
        .pdf-export-mode .data-table .col-pct-i      { width: 6% !important; min-width: 0 !important; text-align: center !important; }
        .pdf-export-mode .data-table .col-bar        { width: 5.5% !important; min-width: 0 !important; text-align: center !important; }
        .pdf-export-mode .data-table .col-total      { width: 32% !important; min-width: 0 !important; text-align: right !important; }

        .pdf-export-mode .data-table th {
            background: #F3F4F6 !important;
            color: #111827 !important;
            border-bottom-color: #D1D5DB !important;
            padding: 4px 5px !important;
            font-size: 8px !important;
            word-break: normal !important;
        }
        .pdf-export-mode .data-table td {
            color: #000000 !important;
            border-bottom-color: #F3F4F6 !important;
            padding: 3px 5px !important;
            font-size: 9px !important;
            white-space: nowrap !important;
            overflow: visible !important;
        }
        .pdf-export-mode .data-table tr.total-row { background: #FFFBEB !important; }
        .pdf-export-mode .data-table tr.total-row td { color: #000000 !important; border-color: #FDE68A !important; }
        .pdf-export-mode .t-vendor { color: #000000 !important; }
        .pdf-export-mode .t-internal { color: #000000 !important; }
        .pdf-export-mode .t-pct-v { color: #000000 !important; }
        .pdf-export-mode .t-pct-i { color: #000000 !important; }
        .pdf-export-mode .pct-bar-track { background: rgba(0,0,0,0.08) !important; width: 100% !important; }
        .pdf-export-mode .pct-bar-fill-v { background: #EA580C !important; }
        .pdf-export-mode .pct-bar-fill-i { background: #059669 !important; }

        .pdf-export-mode .custom-legend { border-top-color: #E5E7EB !important; margin-top: 8px !important; padding-top: 6px !important; }
        .pdf-export-mode .legend-item { color: #000000 !important; font-size: 9px !important; }
        .pdf-export-mode .legend-dot.vendor { background: linear-gradient(180deg,#FB923C,#EA580C) !important; }
        .pdf-export-mode .legend-dot.internal { background: linear-gradient(180deg,#34D399,#059669) !important; }
        .pdf-export-mode .monthly-panel.open { max-height: none !important; margin-bottom: 6px !important; }
        .pdf-export-mode .monthly-card { padding: 10px 12px !important; }
        .pdf-export-mode .year-badge { background: #FEF3C7 !important; color: #000000 !important; font-size: 11px !important; }
        .pdf-export-mode .monthly-header-left h3 { color: #000000 !important; font-size: 10px !important; }
        .pdf-export-mode .monthly-summary { border-top-color: #E5E7EB !important; margin-top: 6px !important; padding-top: 6px !important; }
        .pdf-export-mode .ms-label { color: #000000 !important; }
        .pdf-export-mode .ms-val.mv { color: #000000 !important; }
        .pdf-export-mode .ms-val.mi { color: #000000 !important; }
        .pdf-export-mode .ms-val.ma { color: #000000 !important; }
        .pdf-export-mode .err-box { background: #FEF2F2 !important; border-color: #FECACA !important; }
        .pdf-export-mode .err-box .err-title { color: #DC2626 !important; }
        .pdf-export-mode .err-box .err-item { color: #991B1B !important; }
        .pdf-export-mode .err-box .err-item::before { background: #F87171 !important; }
        .pdf-export-mode .bypass-box { background: #ECFDF5 !important; border-color: #A7F3D0 !important; color: #065F46 !important; }
        .pdf-export-mode .filter-bar,
        .pdf-export-mode .preset-row,
        .pdf-export-mode .click-hint,
        .pdf-export-mode #pdfBtn,
        .pdf-export-mode #printBtn,
        .pdf-export-mode #excelBtn,
        .pdf-export-mode .close-btn,
        .pdf-export-mode .table-tabs,
        .pdf-export-mode #particles,
        .pdf-export-mode .toast { display: none !important; }


        /* ================================================
           PRINT PREVIEW — A3 LANDSCAPE, FIT LEBAR HALAMAN
           ================================================ */
        @page { size: A3 landscape; margin: 6mm; }
        @media print {
            html, body { background:#FFFFFF !important; }
            body.print-export-mode {
                margin:0 !important;
                padding:0 !important;
                width:auto !important;
                min-height:0 !important;
                overflow:visible !important;
                -webkit-print-color-adjust:exact !important;
                print-color-adjust:exact !important;
            }
            body.print-export-mode::before,
            body.print-export-mode::after { display:none !important; }
            body.print-export-mode .container {
                width:408mm !important;       /* 420mm A3 landscape - margin kiri/kanan 12mm */
                max-width:408mm !important;
                margin:0 auto !important;
                padding:0 !important;
            }
            body.print-export-mode .chart-card,
            body.print-export-mode .monthly-card,
            body.print-export-mode .summary-card,
            body.print-export-mode .table-card {
                break-inside:avoid !important;
                page-break-inside:avoid !important;
            }

            body.print-export-mode,
            body.print-export-mode .header h1,
            body.print-export-mode .header-sub,
            body.print-export-mode .header-sub b,
            body.print-export-mode .chart-title,
            body.print-export-mode .chart-title .click-hint,
            body.print-export-mode .legend-item,
            body.print-export-mode .monthly-header-left h3,
            body.print-export-mode .ms-label,
            body.print-export-mode .ms-val,
            body.print-export-mode .sc-label,
            body.print-export-mode .sc-num,
            body.print-export-mode .sc-sub,
            body.print-export-mode .sc-sub .pct,
            body.print-export-mode .data-table th,
            body.print-export-mode .data-table td,
            body.print-export-mode .t-vendor,
            body.print-export-mode .t-internal,
            body.print-export-mode .t-pct-v,
            body.print-export-mode .t-pct-i,
            body.print-export-mode .plant-badge,
            body.print-export-mode .year-badge,
            body.print-export-mode .ms-val.mv,
            body.print-export-mode .ms-val.mi,
            body.print-export-mode .ms-val.ma,
            body.print-export-mode .pv,
            body.print-export-mode .pi {
                color:#000000 !important;
            }
            body.print-export-mode #pdfBtn,
            body.print-export-mode #printBtn,
            body.print-export-mode #excelBtn,
            body.print-export-mode #particles,
            body.print-export-mode .toast { display:none !important; }
        }
    </style>
</head>
<body>
<div id="particles"></div>
<div class="container" id="mainContainer">
    <div class="header">
        <div class="header-top">
            <div class="header-title-group">
                <h1><i class="fas fa-chart-column"></i> Perbandingan Tahunan — Internal vs Vendor</h1>
                <span class="plant-badge <?= (!$any_ok ? 'offline-badge' : '') ?>"><?= $plant_badge_text ?></span>
            </div>
            <?php if ($has_real_data): ?>
                <div class="header-actions">
                    <button id="printBtn" class="btn-print" onclick="printPreviewA3()"><i class="fas fa-print"></i> Print Preview A3</button>
                    <button id="pdfBtn" class="btn-pdf" onclick="generatePDF()"><i class="fas fa-file-pdf"></i> Download PDF A3</button>
                </div>
            <?php endif; ?>
        </div>
        <div class="header-sub">Nilai Sales (IDR) per tahun &nbsp;|&nbsp; Periode: <b id="periodLabel">Pilih Rentang Waktu</b></div>
    </div>
    <?php if (!empty($conn_errors)): ?>
        <div class="err-box"><div class="err-title"><i class="fas fa-triangle-exclamation"></i> Peringatan Koneksi</div>
            <?php foreach ($conn_errors as $err): ?><div class="err-item"><?= $err ?></div><?php endforeach; ?></div>
    <?php endif; ?>
    <?php if ($any_ok && $selected_plant === 'all' && count(array_filter($server_available)) < count($servers_config)): ?>
        <div class="bypass-box"><i class="fas fa-shield-halved"></i><div><strong>Bypass aktif:</strong> Data ditampilkan hanya dari server yang merespon.</div></div>
    <?php endif; ?>
    <div class="filter-bar">
        <label for="plantFilter"><i class="fas fa-building" style="color:var(--accent);"></i> Lokasi Plant</label>
        <select id="plantFilter">
            <option value="all" <?= $selected_plant == 'all' ? 'selected' : '' ?>>Gabungan (P1 & P2)</option>
            <option value="p1" <?= $selected_plant == 'p1' ? 'selected' : '' ?>>Plant 1 Saja</option>
            <option value="p2" <?= $selected_plant == 'p2' ? 'selected' : '' ?>>Plant 2 Saja</option>
        </select>
        <div class="sep"></div><label for="startYear">Dari</label><select id="startYear" aria-label="Tahun awal"></select>
        <div class="sep"></div><label for="endYear">Sampai</label><select id="endYear" aria-label="Tahun akhir"></select>
        <div class="range-hint"><i class="fas fa-calendar-days"></i><span id="rangeText">... tahun</span><span class="count-badge" id="rangeCount">...</span></div>
    </div>
    <div class="preset-row">
        <span><i class="fas fa-bolt" style="color:var(--accent);margin-right:2px;"></i> Cepat:</span>
        <button class="preset-btn" onclick="applyPreset(3)">3 Tahun</button>
        <button class="preset-btn" onclick="applyPreset(5)">5 Tahun</button>
        <button class="preset-btn active" onclick="applyPreset('last5')">5 Tahun Terakhir</button>
        <button class="preset-btn" onclick="applyPreset(7)">7 Tahun</button>
        <button class="preset-btn" onclick="applyPreset(10)">10 Tahun</button>
    </div>
    <div class="chart-card">
        <div class="chart-title"><i class="fas fa-chart-bar"></i> Tren Nilai Sales Tahunan<span class="click-hint"><i class="fas fa-hand-pointer"></i> Klik bar untuk lihat detail bulanan</span></div>
        <div class="empty-state <?= $has_real_data ? '' : 'visible' ?>" id="emptyState"><div class="empty-icon"><i class="fas fa-server"></i></div><h3>Tidak Ada Data Tersedia</h3><p id="emptyMsg">Memuat informasi server...</p></div>
        <div class="chart-area" id="chartArea" style="<?= $has_real_data ? '' : 'display:none;' ?>"><canvas id="annualBarChart"></canvas></div>
        <div class="custom-legend" id="legendBar" style="<?= $has_real_data ? '' : 'display:none;' ?>">
            <div class="legend-item"><div class="legend-dot vendor"></div>Vendor</div>
            <div class="legend-item"><div class="legend-dot internal"></div>Internal</div>
            <div class="legend-item"><div style="width:18px;height:2px;background:var(--accent);position:relative;display:inline-block;margin-right:4px;"><div style="position:absolute;width:8px;height:8px;border-radius:50%;background:var(--card);border:2px solid var(--accent);top:-3px;left:5px;"></div></div>Total Gabungan</div>
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
            <div class="chart-title" style="margin-bottom:0;"><i class="fas fa-table"></i> Tabulasi Data Rincian Nilai Sales</div>
            <div class="table-actions">
                <button type="button" id="excelBtn" class="btn-excel" onclick="exportTableExcel()" title="Export tabulasi yang sedang aktif ke Excel">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
                <div class="table-tabs">
                    <button type="button" class="tab-btn active" id="tabTahunanBtn" onclick="switchTableTab('tahunan')">Rincian Tahunan</button>
                    <button type="button" class="tab-btn" id="tabBulananBtn" onclick="switchTableTab('bulanan')">Rincian Bulanan (<span id="tabBulananYear">Pilih Tahun</span>)</button>
                </div>
            </div>
        </div>
        <div class="table-wrapper" id="wrapperTahunan">
            <table class="data-table" id="tableTahunan"><thead><tr>
                <th class="col-year">Tahun</th><th class="col-vendor" style="text-align:right;">Vendor (IDR)</th><th class="col-internal" style="text-align:right;">Internal (IDR)</th><th class="col-pct-v">% Ven</th><th class="col-pct-i">% Int</th><th class="col-bar">Proporsi</th><th class="col-total" style="text-align:right;">Total (IDR)</th>
            </tr></thead><tbody id="tbodyTahunan"></tbody></table>
        </div>
        <div class="table-wrapper" id="wrapperBulanan" style="display:none;">
            <table class="data-table" id="tableBulanan"><thead><tr>
                <th class="col-year">Bulan (<span id="lblTableBulananYear">2024</span>)</th><th class="col-vendor" style="text-align:right;">Vendor (IDR)</th><th class="col-internal" style="text-align:right;">Internal (IDR)</th><th class="col-pct-v">% Ven</th><th class="col-pct-i">% Int</th><th class="col-bar">Proporsi</th><th class="col-total" style="text-align:right;">Total (IDR)</th>
            </tr></thead><tbody id="tbodyBulanan"></tbody></table>
        </div>
    </div>
</div>
<div class="toast" id="toast"><i class="fas fa-check-circle" style="color:var(--internal);"></i><span id="toastMsg"></span></div>
<script>
var ALL_DATA=<?= $all_data_json ?: '[]' ?>;
var MONTHLY_DATA=<?= $monthly_data_json ?: '{}' ?>;
var SERVER_AVAIL=<?= $server_avail_json ?: '{}' ?>;
var SELECTED_PLANT=<?= json_encode($selected_plant) ?>;
var HAS_REAL_DATA=<?= $has_real_data ? 'true' : 'false' ?>;

(function(){var sel=document.getElementById('plantFilter');sel.querySelectorAll('option').forEach(function(o){var v=o.value;if(v!=='all'&&SERVER_AVAIL[v]===false){o.disabled=true;o.textContent+=' — offline';}if(v==='all'&&!SERVER_AVAIL.p1&&!SERVER_AVAIL.p2){o.disabled=true;o.textContent+=' (semua offline)';}});if(sel.options[sel.selectedIndex].disabled){for(var i=0;i<sel.options.length;i++){if(!sel.options[i].disabled){sel.selectedIndex=i;break;}}}sel.addEventListener('change',function(){if(this.value!=='all'&&SERVER_AVAIL[this.value]===false){showToast('Plant ini sedang offline');return;}window.location.href='?plant='+this.value;});})();

if(!HAS_REAL_DATA){var msg='';var p1=SERVER_AVAIL.p1,p2=SERVER_AVAIL.p2;if(p1===false&&p2===false)msg='Kedua server tidak merespon.';else if(p1===false)msg='Plant 1 tidak merespon.';else if(p2===false)msg='Plant 2 tidak merespon.';else msg='Tidak ada data ditemukan.';document.getElementById('emptyMsg').textContent=msg;}

if(HAS_REAL_DATA&&ALL_DATA.length>0){
var ML=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
var MIN_Y=ALL_DATA[0].year,MAX_Y=ALL_DATA[ALL_DATA.length-1].year,MAX_SP=10;
var chInst=null,chMInst=null,curEnd=MAX_Y,curStart=Math.max(MIN_Y,MAX_Y-4),selYear=null;
var activeTableTab='tahunan';

function fS(n){if(n>=1e12)return(n/1e12).toFixed(1)+'T';if(n>=1e9)return(n/1e9).toFixed(1)+'M';if(n>=1e6)return(n/1e6).toFixed(1)+'Jt';if(n>=1e3)return(n/1e3).toFixed(0)+'Rb';return n.toString();}
function fF(n){return'Rp '+n.toLocaleString('id-ID',{maximumFractionDigits:0});}
function pBarH(pV){var pI=(100-pV).toFixed(1);return'<div class="pct-bar-cell"><div class="pct-bar-track"><div class="pct-bar-fill-v" style="width:'+pV.toFixed(1)+'%;"></div><div class="pct-bar-fill-i" style="width:'+pI+'%;"></div></div></div>';}

function initSel(){var sS=document.getElementById('startYear'),sE=document.getElementById('endYear');for(var y=MIN_Y;y<=MAX_Y;y++){var oS=document.createElement('option');oS.value=y;oS.textContent=y;sS.appendChild(oS);var oE=document.createElement('option');oE.value=y;oE.textContent=y;sE.appendChild(oE);}sS.value=curStart;sE.value=curEnd;sS.addEventListener('change',onRC);sE.addEventListener('change',onRC);}
function onRC(){var s=parseInt(document.getElementById('startYear').value),e=parseInt(document.getElementById('endYear').value);if(e<s){e=s;document.getElementById('endYear').value=e;}if(e-s+1>MAX_SP){e=s+MAX_SP-1;document.getElementById('endYear').value=e;showToast('Maksimal '+MAX_SP+' tahun');}curStart=s;curEnd=e;clrAP();closeMP();updAll();}
window.applyPreset=function(t){clrAP();var s,e;if(t==='last5'){s=MAX_Y-4;e=MAX_Y;}else{var sp=parseInt(t);e=MAX_Y;s=e-sp+1;}if(s<MIN_Y)s=MIN_Y;curStart=s;curEnd=e;document.getElementById('startYear').value=s;document.getElementById('endYear').value=e;document.querySelectorAll('.preset-btn').forEach(function(b){if(b.getAttribute('onclick').indexOf(t)!==-1)b.classList.add('active');});closeMP();updAll();showToast('Menampilkan '+(e-s+1)+' tahun ('+s+'–'+e+')');};
function clrAP(){document.querySelectorAll('.preset-btn').forEach(function(b){b.classList.remove('active');});}

function updAll(){var f=ALL_DATA.filter(function(d){return d.year>=curStart&&d.year<=curEnd;});document.getElementById('rangeText').textContent=f.length+' tahun';document.getElementById('rangeCount').textContent=f.length;document.getElementById('periodLabel').textContent=curStart+' s/d '+curEnd;updChart(f);updSum(f);genTTable(f);}

function updChart(data){
var isL=window._pdfLM===true;var ctx=document.getElementById('annualBarChart').getContext('2d');
var vG=ctx.createLinearGradient(0,0,0,400);vG.addColorStop(0,isL?'#FB923C':'#FF8C5A');vG.addColorStop(1,isL?'#EA580C':'#FF6B35');
var iG=ctx.createLinearGradient(0,0,0,400);iG.addColorStop(0,isL?'#34D399':'#33E0BE');iG.addColorStop(1,isL?'#059669':'#00D4AA');
var lb=data.map(function(d){return d.year.toString();}),vV=data.map(function(d){return d.vendor;}),iV=data.map(function(d){return d.internal;}),tV=data.map(function(d){return d.vendor+d.internal;});
var bP=data.length<=5?0.65:data.length<=7?0.55:0.45;
var C=isL?{vB:'#EA580C',iB:'#059669',vL:'#000000',iL:'#000000',lnC:'#D97706',lnBg:'#FFFFFF',lnBd:'#D97706',ttBg:'#FFFFFF',ttT:'#000000',ttBd:'#000000',ttBr:'#E5E7EB',grd:'rgba(0,0,0,0.06)',xT:'#000000',yT:'#000000',xBd:'#000000',lnF:'rgba(217,119,6,0.06)'}:{vB:'#EA580C',iB:'#059669',vL:'#000000',iL:'#000000',lnC:'#D97706',lnBg:'#FFFFFF',lnBd:'#D97706',ttBg:'#FFFFFF',ttT:'#000000',ttBd:'#000000',ttBr:'#E5E7EB',grd:'rgba(17,24,39,0.07)',xT:'#000000',yT:'#000000',xBd:'#000000',lnF:'rgba(217,119,6,0.08)'};
if(chInst){chInst.data.labels=lb;chInst.data.datasets[0].data=vV;chInst.data.datasets[1].data=iV;chInst.data.datasets[2].data=tV;chInst.update('active');
}else{chInst=new Chart(ctx,{type:'bar',data:{labels:lb,datasets:[{label:'Vendor',type:'bar',data:vV,backgroundColor:vG,borderColor:C.vB,borderWidth:1,borderRadius:{topLeft:6,topRight:6},barPercentage:bP,categoryPercentage:0.6},{label:'Internal',type:'bar',data:iV,backgroundColor:iG,borderColor:C.iB,borderWidth:1,borderRadius:{topLeft:6,topRight:6},barPercentage:bP,categoryPercentage:0.6},{label:'Total Gabungan',type:'line',data:tV,borderColor:C.lnC,borderWidth:2.5,backgroundColor:C.lnF,pointBackgroundColor:C.lnBg,pointBorderColor:C.lnBd,pointBorderWidth:2,pointRadius:4,pointHoverRadius:6,tension:0.3,fill:false}]},options:{responsive:true,maintainAspectRatio:false,layout:{padding:{top:35}},interaction:{mode:'index',intersect:false},onClick:function(evt,el){if(el.length>0)togMP(parseInt(chInst.data.labels[el[0].index]));},plugins:{legend:{display:false},tooltip:{backgroundColor:C.ttBg,titleColor:C.ttT,bodyColor:C.ttBd,borderColor:C.ttBr,borderWidth:1,cornerRadius:8,padding:14,titleFont:{family:'Space Grotesk',size:14,weight:'700'},bodyFont:{family:'DM Sans',size:12},boxPadding:6,usePointStyle:true,callbacks:{title:function(i){return'Tahun '+i[0].label;},label:function(c){if(c.dataset.type==='line')return' Total: '+fF(c.parsed.y);var t=c.chart.data.datasets[0].data[c.dataIndex]+c.chart.data.datasets[1].data[c.dataIndex],p=t>0?((c.parsed.y/t)*100).toFixed(1):0;return' '+c.dataset.label+': '+fF(c.parsed.y)+' ('+p+'%)';}}}},scales:{x:{grid:{display:false},ticks:{color:C.xT,font:{family:'Space Grotesk',size:13,weight:'600'},padding:8},border:{color:C.xBd}},y:{beginAtZero:true,grid:{color:C.grd,lineWidth:1},ticks:{color:C.yT,font:{family:'Space Grotesk',size:11},padding:12,callback:function(v){return fS(v);},maxTicksLimit:7},border:{display:false}}},animation:{duration:isL?0:800,easing:'easeOutQuart'}},plugins:[{id:'cLP',afterDatasetsDraw:function(ch){var cx=ch.ctx;ch.data.datasets.forEach(function(ds,di){if(ds.type==='line')return;var m=ch.getDatasetMeta(di);m.data.forEach(function(bar,idx){var val=ds.data[idx];cx.save();cx.textAlign='center';cx.textBaseline='bottom';var fs=ch.data.labels.length<=5?10:8;cx.font='600 '+fs+'px "Space Grotesk",sans-serif';cx.fillStyle=di===0?C.vL:C.iL;cx.fillText(fS(val),bar.x,bar.y-5);cx.restore();});});}}]});}
}

function togMP(y){if(selYear===y)closeMP();else openMP(y);}
function openMP(y){selYear=y;document.getElementById('monthlyYearBadge').textContent=y;document.getElementById('tabBulananYear').textContent=y;document.getElementById('lblTableBulananYear').textContent=y;document.getElementById('monthlyPanel').classList.add('open');var f=ALL_DATA.filter(function(d){return d.year>=curStart&&d.year<=curEnd;});updChart(f);updMChart(y);updMSum(y);genBTable(y);swTab('bulanan');showToast('Detail bulanan tahun '+y);setTimeout(function(){document.getElementById('monthlyPanel').scrollIntoView({behavior:'smooth',block:'nearest'});},200);}
function closeMP(){selYear=null;document.getElementById('tabBulananYear').textContent='Pilih Tahun';document.getElementById('monthlyPanel').classList.remove('open');var f=ALL_DATA.filter(function(d){return d.year>=curStart&&d.year<=curEnd;});updChart(f);swTab('tahunan');}
document.getElementById('closeMonthly').addEventListener('click',closeMP);

function updMChart(year){
var md=MONTHLY_DATA[year];if(!md)return;var isL=window._pdfLM===true;var ctx=document.getElementById('monthlyBarChart').getContext('2d');
var vG=ctx.createLinearGradient(0,0,0,260);vG.addColorStop(0,isL?'#FB923C':'#FF8C5A');vG.addColorStop(1,isL?'#EA580C':'#FF6B35');
var iG=ctx.createLinearGradient(0,0,0,260);iG.addColorStop(0,isL?'#34D399':'#33E0BE');iG.addColorStop(1,isL?'#059669':'#00D4AA');
var C=isL?{vB:'#EA580C',iB:'#059669',vL:'#000000',iL:'#000000',ttBg:'#FFFFFF',ttT:'#000000',ttBd:'#000000',ttBr:'#E5E7EB',grd:'rgba(0,0,0,0.06)',xT:'#000000',yT:'#000000',xBd:'#000000'}:{vB:'#EA580C',iB:'#059669',vL:'#000000',iL:'#000000',ttBg:'#FFFFFF',ttT:'#000000',ttBd:'#000000',ttBr:'#E5E7EB',grd:'rgba(17,24,39,0.07)',xT:'#000000',yT:'#000000',xBd:'#000000'};
if(chMInst){chMInst.data.datasets[0].data=md.vendor;chMInst.data.datasets[1].data=md.internal;chMInst.update('active');
}else{chMInst=new Chart(ctx,{type:'bar',data:{labels:ML,datasets:[{label:'Vendor',data:md.vendor,backgroundColor:vG,hoverBackgroundColor:isL?'#FDBA74':'#FFA07A',borderColor:C.vB,borderWidth:1,borderRadius:{topLeft:4,topRight:4},barPercentage:0.7,categoryPercentage:0.65},{label:'Internal',data:md.internal,backgroundColor:iG,hoverBackgroundColor:isL?'#6EE7B7':'#66EDD6',borderColor:C.iB,borderWidth:1,borderRadius:{topLeft:4,topRight:4},barPercentage:0.7,categoryPercentage:0.65}]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:false},tooltip:{backgroundColor:C.ttBg,titleColor:C.ttT,bodyColor:C.ttBd,borderColor:C.ttBr,borderWidth:1,cornerRadius:8,padding:12,titleFont:{family:'Space Grotesk',size:13,weight:'700'},bodyFont:{family:'DM Sans',size:11},boxPadding:5,usePointStyle:true,callbacks:{title:function(i){return ML[i[0].dataIndex]+' '+year;},label:function(c){var v=c.parsed.y,t=c.chart.data.datasets.reduce(function(s,ds){return s+ds.data[c.dataIndex];},0),p=t>0?((v/t)*100).toFixed(1):0;return' '+c.dataset.label+': '+fF(v)+' ('+p+'%)';}}}},scales:{x:{grid:{display:false},ticks:{color:C.xT,font:{family:'Space Grotesk',size:11,weight:'600'},padding:6},border:{color:C.xBd}},y:{beginAtZero:true,grid:{color:C.grd,lineWidth:1},ticks:{color:C.yT,font:{family:'Space Grotesk',size:10},padding:10,callback:function(v){return fS(v);},maxTicksLimit:6},border:{display:false}}},animation:{duration:isL?0:600,easing:'easeOutQuart'}},plugins:[{id:'mVL',afterDatasetsDraw:function(ch){var cx=ch.ctx;ch.data.datasets.forEach(function(ds,di){var m=ch.getDatasetMeta(di);m.data.forEach(function(bar,idx){var val=ds.data[idx];cx.save();cx.textAlign='center';cx.textBaseline='bottom';cx.font='600 8px "Space Grotesk",sans-serif';cx.fillStyle=di===0?C.vL:C.iL;cx.fillText(fS(val),bar.x,bar.y-4);cx.restore();});});}}]});}
}

function updMSum(year){var md=MONTHLY_DATA[year];if(!md)return;var tV=0,tI=0,mxV=0,mxI=0,mxVN='',mxIN='';for(var m=0;m<12;m++){tV+=md.vendor[m];tI+=md.internal[m];if(md.vendor[m]>mxV){mxV=md.vendor[m];mxVN=ML[m];}if(md.internal[m]>mxI){mxI=md.internal[m];mxIN=ML[m];}}var tot=tV+tI,pV=tot>0?((tV/tot)*100).toFixed(1):0,pI=tot>0?((tI/tot)*100).toFixed(1):0;document.getElementById('monthlySummary').innerHTML='<div class="ms-item"><span class="ms-label">Total Vendor</span><span class="ms-val mv">'+fS(tV)+'</span></div><div class="ms-item"><span class="ms-label">Total Internal</span><span class="ms-val mi">'+fS(tI)+'</span></div><div class="ms-item"><span class="ms-label">Vendor Puncak</span><span class="ms-val mv">'+mxVN+' ('+fS(mxV)+')</span></div><div class="ms-item"><span class="ms-label">Internal Puncak</span><span class="ms-val mi">'+mxIN+' ('+fS(mxI)+')</span></div><div class="ms-item"><span class="ms-label">Proporsi Vendor</span><span class="ms-val ma">'+pV+'%</span></div><div class="ms-item"><span class="ms-label">Proporsi Internal</span><span class="ms-val ma">'+pI+'%</span></div>';}

function updSum(data){var tV=0,tI=0;data.forEach(function(d){tV+=d.vendor;tI+=d.internal;});var tot=tV+tI,pV=tot>0?((tV/tot)*100).toFixed(1):0,pI=tot>0?((tI/tot)*100).toFixed(1):0;var li=data.length-1,gV=0,gI=0;if(li>0){gV=data[li-1].vendor>0?((data[li].vendor-data[li-1].vendor)/data[li-1].vendor*100).toFixed(1):(data[li].vendor>0?100:0);gI=data[li-1].internal>0?((data[li].internal-data[li-1].internal)/data[li-1].internal*100).toFixed(1):(data[li].internal>0?100:0);}var aV=data.length>0?tV/data.length:0,aI=data.length>0?tI/data.length:0;function gIc(v){var n=parseFloat(v);if(n>0)return'<i class="fas fa-arrow-trend-up" style="color:#059669;"></i>';if(n<0)return'<i class="fas fa-arrow-trend-down" style="color:#EA580C;"></i>';return'<i class="fas fa-minus" style="color:#6B7280;"></i>';}document.getElementById('summaryRow').innerHTML='<div class="summary-card sv"><div class="sc-icon"><i class="fas fa-truck-field"></i></div><div class="sc-label">Total Vendor</div><div class="sc-num" style="color:var(--vendor);">'+fS(tV)+'</div><div class="sc-sub">Proporsi: <span class="pct pv">'+pV+'%</span></div></div><div class="summary-card si"><div class="sc-icon"><i class="fas fa-industry"></i></div><div class="sc-label">Total Internal</div><div class="sc-num" style="color:var(--internal);">'+fS(tI)+'</div><div class="sc-sub">Proporsi: <span class="pct pi">'+pI+'%</span></div></div><div class="summary-card sa"><div class="sc-icon"><i class="fas fa-chart-line"></i></div><div class="sc-label">Pertumbuhan Vendor</div><div class="sc-num" style="color:var(--accent);">'+(gV>=0?'+':'')+gV+'% '+gIc(gV)+'</div><div class="sc-sub">vs tahun sebelumnya</div></div><div class="summary-card sa"><div class="sc-icon"><i class="fas fa-chart-line"></i></div><div class="sc-label">Pertumbuhan Internal</div><div class="sc-num" style="color:var(--accent);">'+(gI>=0?'+':'')+gI+'% '+gIc(gI)+'</div><div class="sc-sub">vs tahun sebelumnya</div></div><div class="summary-card sv"><div class="sc-icon"><i class="fas fa-calculator"></i></div><div class="sc-label">Rata-rata Vendor/Tahun</div><div class="sc-num" style="color:var(--vendor);">'+fS(aV)+'</div><div class="sc-sub">dari '+data.length+' tahun</div></div><div class="summary-card si"><div class="sc-icon"><i class="fas fa-calculator"></i></div><div class="sc-label">Rata-rata Internal/Tahun</div><div class="sc-num" style="color:var(--internal);">'+fS(aI)+'</div><div class="sc-sub">dari '+data.length+' tahun</div></div>';}

function genTTable(data){var tV=0,tI=0;data.forEach(function(d){tV+=d.vendor;tI+=d.internal;});var h='';data.forEach(function(d){var tot=d.vendor+d.internal;var pV=tot>0?((d.vendor/tot)*100):0;h+='<tr><td class="col-year"><strong>'+d.year+'</strong></td><td class="col-vendor t-vendor" style="text-align:right;">'+fF(d.vendor)+'</td><td class="col-internal t-internal" style="text-align:right;">'+fF(d.internal)+'</td><td class="col-pct-v t-pct-v" style="text-align:center;">'+pV.toFixed(1)+'%</td><td class="col-pct-i t-pct-i" style="text-align:center;">'+(100-pV).toFixed(1)+'%</td><td class="col-bar">'+pBarH(pV)+'</td><td class="col-total" style="text-align:right;">'+fF(tot)+'</td></tr>';});var gT=tV+tI;var gPV=gT>0?((tV/gT)*100):0;h+='<tr class="total-row"><td class="col-year"><strong>TOTAL</strong></td><td class="col-vendor t-vendor" style="text-align:right;">'+fF(tV)+'</td><td class="col-internal t-internal" style="text-align:right;">'+fF(tI)+'</td><td class="col-pct-v t-pct-v" style="text-align:center;">'+gPV.toFixed(1)+'%</td><td class="col-pct-i t-pct-i" style="text-align:center;">'+(100-gPV).toFixed(1)+'%</td><td class="col-bar">'+pBarH(gPV)+'</td><td class="col-total" style="text-align:right;">'+fF(gT)+'</td></tr>';document.getElementById('tbodyTahunan').innerHTML=h;}

function genBTable(year){var md=MONTHLY_DATA[year];if(!md)return;var tV=0,tI=0;var h='';for(var m=0;m<12;m++){var v=md.vendor[m],i=md.internal[m],t=v+i;tV+=v;tI+=i;var pV=t>0?((v/t)*100):0;h+='<tr><td class="col-year"><strong>'+ML[m]+'</strong></td><td class="col-vendor t-vendor" style="text-align:right;">'+fF(v)+'</td><td class="col-internal t-internal" style="text-align:right;">'+fF(i)+'</td><td class="col-pct-v t-pct-v" style="text-align:center;">'+pV.toFixed(1)+'%</td><td class="col-pct-i t-pct-i" style="text-align:center;">'+(100-pV).toFixed(1)+'%</td><td class="col-bar">'+pBarH(pV)+'</td><td class="col-total" style="text-align:right;">'+fF(t)+'</td></tr>';}var gT=tV+tI;var gPV=gT>0?((tV/gT)*100):0;h+='<tr class="total-row"><td class="col-year"><strong>TOTAL</strong></td><td class="col-vendor t-vendor" style="text-align:right;">'+fF(tV)+'</td><td class="col-internal t-internal" style="text-align:right;">'+fF(tI)+'</td><td class="col-pct-v t-pct-v" style="text-align:center;">'+gPV.toFixed(1)+'%</td><td class="col-pct-i t-pct-i" style="text-align:center;">'+(100-gPV).toFixed(1)+'%</td><td class="col-bar">'+pBarH(gPV)+'</td><td class="col-total" style="text-align:right;">'+fF(gT)+'</td></tr>';document.getElementById('tbodyBulanan').innerHTML=h;}

window.switchTableTab=function(tab){
    var bT=document.getElementById('tabTahunanBtn');
    var bB=document.getElementById('tabBulananBtn');
    var wT=document.getElementById('wrapperTahunan');
    var wB=document.getElementById('wrapperBulanan');

    if(tab==='bulanan'&&(selYear===null||!MONTHLY_DATA[selYear])){
        showToast('Klik salah satu bar tahun untuk membuka rincian bulanan');
        return;
    }

    activeTableTab=tab;
    if(tab==='tahunan'){
        bT.classList.add('active');
        bB.classList.remove('active');
        wT.style.display='';
        wB.style.display='none';
    }else{
        bB.classList.add('active');
        bT.classList.remove('active');
        wB.style.display='';
        wT.style.display='none';
    }
};
window.swTab=window.switchTableTab;

function getExcelPlantLabel(){
    if(SELECTED_PLANT==='p1')return'P1';
    if(SELECTED_PLANT==='p2')return'P2';
    return'Gabungan_P1_P2';
}

function getExcelPlantDisplayLabel(){
    if(SELECTED_PLANT==='p1')return'Plant 1 (P1)';
    if(SELECTED_PLANT==='p2')return'Plant 2 (P2)';
    return'Gabungan Plant 1 & Plant 2';
}

function cleanExcelFileName(name){
    return name.replace(/[\\/:*?"<>|]+/g,'_').replace(/\s+/g,'_');
}

function makeExcelRow(label,vendor,internal){
    vendor=Number(vendor)||0;
    internal=Number(internal)||0;
    var total=vendor+internal;
    var pV=total>0?vendor/total:0;
    var pI=total>0?internal/total:0;
    return[label,vendor,internal,pV,pI,(pV*100).toFixed(1)+'% Vendor | '+(pI*100).toFixed(1)+'% Internal',total];
}

function getExcelPeriod(activeTab){
    var startYear;
    var endYear;

    if(activeTab==='bulanan'){
        startYear=selYear;
        endYear=selYear;
    }else{
        startYear=curStart;
        endYear=curEnd;
    }

    return{
        startYear:startYear,
        endYear:endYear,
        startDate:'01 Januari '+startYear,
        endDate:'31 Desember '+endYear
    };
}

function styleExcelWorksheet(ws,dataRowCount,headerRowNumber){
    var dataStartRow=headerRowNumber+1;
    var totalRowNumber=headerRowNumber+dataRowCount;

    var companyStyle={
        font:{bold:true,size:16,color:{rgb:'111827'}},
        alignment:{horizontal:'center',vertical:'center'},
        fill:{fgColor:{rgb:'FFFFFF'}}
    };
    var reportTitleStyle={
        font:{bold:true,size:14,color:{rgb:'EA580C'}},
        alignment:{horizontal:'center',vertical:'center'},
        fill:{fgColor:{rgb:'FFFFFF'}}
    };
    var infoLabelStyle={
        font:{bold:true,color:{rgb:'111827'}},
        fill:{fgColor:{rgb:'FEF3C7'}},
        alignment:{horizontal:'left',vertical:'center'},
        border:{
            top:{style:'thin',color:{rgb:'E5E7EB'}},
            bottom:{style:'thin',color:{rgb:'E5E7EB'}},
            left:{style:'thin',color:{rgb:'E5E7EB'}},
            right:{style:'thin',color:{rgb:'E5E7EB'}}
        }
    };
    var infoValueStyle={
        font:{color:{rgb:'111827'}},
        fill:{fgColor:{rgb:'FFFFFF'}},
        alignment:{horizontal:'left',vertical:'center'},
        border:{
            top:{style:'thin',color:{rgb:'E5E7EB'}},
            bottom:{style:'thin',color:{rgb:'E5E7EB'}},
            left:{style:'thin',color:{rgb:'E5E7EB'}},
            right:{style:'thin',color:{rgb:'E5E7EB'}}
        }
    };
    var headerStyle={
        font:{bold:true,color:{rgb:'111827'}},
        fill:{fgColor:{rgb:'F3F4F6'}},
        alignment:{horizontal:'center',vertical:'center'},
        border:{
            top:{style:'thin',color:{rgb:'D1D5DB'}},
            bottom:{style:'medium',color:{rgb:'D1D5DB'}},
            left:{style:'thin',color:{rgb:'E5E7EB'}},
            right:{style:'thin',color:{rgb:'E5E7EB'}}
        }
    };
    var bodyBorder={
        bottom:{style:'thin',color:{rgb:'E5E7EB'}},
        left:{style:'thin',color:{rgb:'F3F4F6'}},
        right:{style:'thin',color:{rgb:'F3F4F6'}}
    };
    var totalStyle={
        font:{bold:true,color:{rgb:'111827'}},
        fill:{fgColor:{rgb:'FFFBEB'}},
        border:{
            top:{style:'medium',color:{rgb:'D1D5DB'}},
            bottom:{style:'medium',color:{rgb:'D1D5DB'}},
            left:{style:'thin',color:{rgb:'E5E7EB'}},
            right:{style:'thin',color:{rgb:'E5E7EB'}}
        }
    };

    if(ws.A1)ws.A1.s=companyStyle;
    if(ws.A2)ws.A2.s=reportTitleStyle;

    ['A3','A4','A5'].forEach(function(addr){
        if(ws[addr])ws[addr].s=infoLabelStyle;
    });
    ['C3','C4','C5'].forEach(function(addr){
        if(ws[addr])ws[addr].s=infoValueStyle;
    });

    for(var c=0;c<7;c++){
        var h=XLSX.utils.encode_cell({r:headerRowNumber-1,c:c});
        if(ws[h])ws[h].s=headerStyle;
    }

    for(var excelRow=dataStartRow;excelRow<=totalRowNumber;excelRow++){
        var isTotal=excelRow===totalRowNumber;
        for(var col=0;col<7;col++){
            var addr=XLSX.utils.encode_cell({r:excelRow-1,c:col});
            if(!ws[addr])continue;
            var baseAlign=col===0?'center':(col===5?'left':'right');
            ws[addr].s=isTotal
                ?Object.assign({},totalStyle,{alignment:{horizontal:baseAlign,vertical:'center'}})
                :{border:bodyBorder,alignment:{vertical:'center',horizontal:baseAlign}};
        }

        ['B','C','G'].forEach(function(letter){
            var cell=ws[letter+excelRow];
            if(cell)cell.z='"Rp" #,##0';
        });
        ['D','E'].forEach(function(letter){
            var cell=ws[letter+excelRow];
            if(cell)cell.z='0.0%';
        });
    }

    ws['!cols']=[
        {wch:14},{wch:23},{wch:23},{wch:11},{wch:11},{wch:33},{wch:23}
    ];
    ws['!rows']=[
        {hpt:27},
        {hpt:24},
        {hpt:20},
        {hpt:20},
        {hpt:20},
        {hpt:8},
        {hpt:24}
    ];
    ws['!autofilter']={ref:'A'+headerRowNumber+':G'+totalRowNumber};
    ws['!freeze']={
        xSplit:0,
        ySplit:headerRowNumber,
        topLeftCell:'A'+(headerRowNumber+1),
        activePane:'bottomLeft',
        state:'frozen'
    };
}

window.exportTableExcel=function(){
    var btn=document.getElementById('excelBtn');
    if(typeof XLSX==='undefined'){
        showToast('Library Excel gagal dimuat. Periksa koneksi internet/CDN.');
        return;
    }

    if(btn){
        btn.disabled=true;
        btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Menyiapkan...';
    }

    try{
        var headers=[];
        var rows=[];
        var sheetName='';
        var fileName='';
        var plantLabel=getExcelPlantLabel();
        var plantDisplay=getExcelPlantDisplayLabel();
        var period=getExcelPeriod(activeTableTab);
        var reportType='';

        if(activeTableTab==='bulanan'){
            if(selYear===null||!MONTHLY_DATA[selYear]){
                showToast('Pilih tahun terlebih dahulu untuk export rincian bulanan');
                return;
            }

            headers=['Bulan ('+selYear+')','Vendor (IDR)','Internal (IDR)','% Ven','% Int','Proporsi','Total (IDR)'];
            var md=MONTHLY_DATA[selYear];
            var totalVendor=0,totalInternal=0;
            for(var m=0;m<12;m++){
                rows.push(makeExcelRow(ML[m],md.vendor[m],md.internal[m]));
                totalVendor+=Number(md.vendor[m])||0;
                totalInternal+=Number(md.internal[m])||0;
            }
            rows.push(makeExcelRow('TOTAL',totalVendor,totalInternal));
            reportType='Rincian Bulanan';
            sheetName='Bulanan_'+selYear;
            fileName='Sales_Internal_VS_Vendor_Bulanan_'+period.startYear+'-'+period.endYear+'_'+plantLabel+'.xlsx';
        }else{
            headers=['Tahun','Vendor (IDR)','Internal (IDR)','% Ven','% Int','Proporsi','Total (IDR)'];
            var filtered=ALL_DATA.filter(function(d){return d.year>=curStart&&d.year<=curEnd;});
            var totalVendorYear=0,totalInternalYear=0;
            filtered.forEach(function(d){
                rows.push(makeExcelRow(d.year,d.vendor,d.internal));
                totalVendorYear+=Number(d.vendor)||0;
                totalInternalYear+=Number(d.internal)||0;
            });
            rows.push(makeExcelRow('TOTAL',totalVendorYear,totalInternalYear));
            reportType='Rincian Tahunan';
            sheetName='Tahunan_'+curStart+'-'+curEnd;
            fileName='Sales_Internal_VS_Vendor_Tahunan_'+period.startYear+'-'+period.endYear+'_'+plantLabel+'.xlsx';
        }

        var headerRowNumber=7;
        var reportRows=[
            ['PT.IMC TEKNO INDONESIA','','','','','',''],
            ['Sales Internal VS Vendor','','','','','',''],
            ['Tanggal Start','',''+period.startDate,'','','',''],
            ['Tanggal End','',''+period.endDate,'','','',''],
            ['Plant / Jenis','',''+plantDisplay+' / '+reportType,'','','',''],
            ['','','','','','',''],
            headers
        ].concat(rows);

        var ws=XLSX.utils.aoa_to_sheet(reportRows);
        ws['!merges']=[
            XLSX.utils.decode_range('A1:G1'),
            XLSX.utils.decode_range('A2:G2'),
            XLSX.utils.decode_range('A3:B3'),
            XLSX.utils.decode_range('C3:G3'),
            XLSX.utils.decode_range('A4:B4'),
            XLSX.utils.decode_range('C4:G4'),
            XLSX.utils.decode_range('A5:B5'),
            XLSX.utils.decode_range('C5:G5')
        ];
        styleExcelWorksheet(ws,rows.length,headerRowNumber);

        var wb=XLSX.utils.book_new();
        wb.Props={
            Title:'Sales Internal VS Vendor',
            Subject:reportType+' - '+period.startDate+' s/d '+period.endDate,
            Author:'PT.IMC TEKNO INDONESIA',
            Company:'PT.IMC TEKNO INDONESIA',
            CreatedDate:new Date()
        };
        XLSX.utils.book_append_sheet(wb,ws,sheetName.substring(0,31));
        XLSX.writeFile(wb,cleanExcelFileName(fileName),{compression:true});
        showToast('Laporan '+reportType.toLowerCase()+' berhasil diexport ke Excel');
    }catch(err){
        console.error(err);
        showToast('Gagal export Excel: '+(err&&err.message?err.message:'error tidak diketahui'));
    }finally{
        if(btn){
            btn.disabled=false;
            btn.innerHTML='<i class="fas fa-file-excel"></i> Export Excel';
        }
    }
};

var toastT=null;
function showToast(msg){var t=document.getElementById('toast');document.getElementById('toastMsg').textContent=msg;t.classList.add('show');if(toastT)clearTimeout(toastT);toastT=setTimeout(function(){t.classList.remove('show');},2800);}

/* ================================================
   PRINT PREVIEW A3 LANDSCAPE — FIT LEBAR CETAK
   ================================================ */
var printRestoreData=null;
var printWasMonthlyYear=null;
var printRestoring=false;

function printPreviewA3(){
    var btn=document.getElementById('printBtn');
    if(btn){btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Menyiapkan...';}
    showToast('Menyiapkan Print Preview A3 Landscape...');

    printWasMonthlyYear=selYear;
    if(selYear!==null)closeMP();
    printRestoreData=ALL_DATA.filter(function(d){return d.year>=curStart&&d.year<=curEnd;});

    document.body.classList.add('pdf-export-mode','print-export-mode');
    window._pdfLM=true;

    if(chInst){chInst.destroy();chInst=null;}
    if(chMInst){chMInst.destroy();chMInst=null;}
    updChart(printRestoreData);

    setTimeout(function(){
        try{
            window.print();
        }finally{
            setTimeout(restorePrintPreview,120);
        }
    },350);
}

function restorePrintPreview(){
    if(printRestoring||!document.body.classList.contains('print-export-mode'))return;
    printRestoring=true;
    window._pdfLM=false;
    document.body.classList.remove('print-export-mode','pdf-export-mode');

    if(chInst){chInst.destroy();chInst=null;}
    if(chMInst){chMInst.destroy();chMInst=null;}
    if(printRestoreData)updChart(printRestoreData);

    var restoreYear=printWasMonthlyYear;
    printRestoreData=null;
    printWasMonthlyYear=null;

    if(restoreYear!==null&&MONTHLY_DATA[restoreYear]){
        openMP(restoreYear);
    }

    var btn=document.getElementById('printBtn');
    if(btn){btn.disabled=false;btn.innerHTML='<i class="fas fa-print"></i> Print Preview A3';}
    printRestoring=false;
}

window.addEventListener('afterprint',restorePrintPreview);

/* ================================================
   PDF — FIX TOTAL: lebar 1540px, capture persis
   ================================================ */
window._pdfLM=false;
var PDF_W=1540; /* A3 Landscape margin 6mm = ~1540px @96dpi */

function generatePDF(){
    var btn=document.getElementById('pdfBtn');
    if(btn){btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Memproses...';}
    showToast('Menyiapkan PDF A3 Landscape...');
    if(selYear!==null)closeMP();

    setTimeout(function(){
        document.body.classList.add('pdf-export-mode');
        window._pdfLM=true;

        var fData=ALL_DATA.filter(function(d){return d.year>=curStart&&d.year<=curEnd;});

        if(chInst){chInst.destroy();chInst=null;}
        if(chMInst){chMInst.destroy();chMInst=null;}
        updChart(fData);

        setTimeout(function(){
            var el=document.getElementById('mainContainer');

            var opt={
                margin:[6,6,6,6],
                filename:'Laporan_Sales_'+curStart+'-'+curEnd+'_A3.pdf',
                image:{type:'jpeg',quality:0.96},
                html2canvas:{
                    scale:2,
                    useCORS:true,
                    backgroundColor:'#FFFFFF',
                    logging:false,
                    width:PDF_W,
                    windowWidth:PDF_W
                },
                jsPDF:{unit:'mm',format:'a3',orientation:'landscape'},
                pagebreak:{mode:['avoid-all','css','legacy']}
            };

            html2pdf().set(opt).from(el).save().then(function(){
                restPDF(fData);showToast('PDF A3 berhasil diunduh!');
            }).catch(function(err){
                restPDF(fData);showToast('Gagal membuat PDF');console.error(err);
            });
        },400);
    },350);
}

function restPDF(fData){
    window._pdfLM=false;
    document.body.classList.remove('pdf-export-mode');
    if(chInst){chInst.destroy();chInst=null;}
    if(chMInst){chMInst.destroy();chMInst=null;}
    updChart(fData);
    if(selYear!==null&&MONTHLY_DATA[selYear])updMChart(selYear);
    var btn=document.getElementById('pdfBtn');
    if(btn){btn.disabled=false;btn.innerHTML='<i class="fas fa-file-pdf"></i> Download PDF A3';}
}

(function(){var c=document.getElementById('particles');if(!c)return;for(var i=0;i<20;i++){var p=document.createElement('div');p.className='particle';p.style.left=Math.random()*100+'%';p.style.top=Math.random()*100+'%';p.style.width=(1+Math.random()*2)+'px';p.style.height=p.style.width;p.style.background=Math.random()>0.5?'rgba(255,107,53,0.3)':'rgba(0,212,170,0.3)';p.style.animation='floatP '+(4+Math.random()*6)+'s ease-in-out '+(Math.random()*3)+'s infinite alternate';c.appendChild(p);}var st=document.createElement('style');st.textContent='@keyframes floatP{0%{opacity:0;transform:translateY(0) translateX(0);}50%{opacity:0.6;}100%{opacity:0;transform:translateY(-40px) translateX(20px);}}';document.head.appendChild(st);})();

initSel();updAll();
}
</script>
</body>
</html>
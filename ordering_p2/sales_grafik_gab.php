<?php
// ============================================
// FULL OPTIMIZED: LAZY LOADING + KOLOM KURS + EXCEL DINAMIS (TAHUNAN/BULANAN)
// ============================================
set_time_limit(120);
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

// ============================================
// BLOK API AJAX: TAHUNAN & BULANAN TERPISAH
// ============================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'];
    $selected_plant = isset($_GET['plant']) ? strtolower($_GET['plant']) : 'all';
    if (!in_array($selected_plant, ['p1', 'p2', 'all'])) $selected_plant = 'all';

    $conn_opts_base = [
        "Database" => $dbName, "Uid" => $uid, "PWD" => $pwd,
        "CharacterSet" => "UTF-8", "LoginTimeout" => 3, "Encrypt" => false,
    ];
    $servers_to_try = ($selected_plant === 'all') ? ['p1', 'p2'] : [$selected_plant];
    $server_available = ['p1' => false, 'p2' => false];
    $conn_errors = [];

    // ----------------------------------------
    // ACTION 1: FETCH TAHUNAN (SANGAT CEPAT)
    // ----------------------------------------
    if ($action == 'fetch_yearly') {
        $startYear = isset($_GET['startYear']) ? (int)$_GET['startYear'] : date('Y');
        $endYear = isset($_GET['endYear']) ? (int)$_GET['endYear'] : date('Y');
        if ($startYear > $endYear) { $temp = $startYear; $startYear = $endYear; $endYear = $temp; }

        $startDate = $startYear . "-01-01";
        $endDate = $endYear . "-12-31 23:59:59";

        $raw_yearly = [];
        for ($y = $startYear; $y <= $endYear; $y++) {
            $raw_yearly[$y] = ['year' => $y, 'vendor' => 0, 'internal' => 0, 'usd' => 0];
        }

        $sql = "
        WITH VendorItems AS (
            SELECT DISTINCT INV_TRAN.ITEM_ID
            FROM TRANS
            INNER JOIN INV_TRAN ON TRANS.TRAN_ID = INV_TRAN.TRAN_ID
            WHERE TRANS.TRTY_CODE = '12'
        )
        SELECT
            YEAR(DI.DI_DATE) AS SALES_YEAR,
            SUM(
                DIPA_PAR.QTY * DIPA_PAR.PART_PRICE * CASE 
                    WHEN APV.CURR_CODE IN ('IDR', 'RP') OR APV.CURR_CODE IS NULL THEN 1 
                    ELSE ISNULL(RV.CURR_VRATE, 1) 
                END
            ) AS TOTAL_SALES,
            SUM(
                CASE 
                    WHEN ISNULL(USD_R.CURR_VRATE, 0) > 0 THEN
                        (DIPA_PAR.QTY * DIPA_PAR.PART_PRICE * CASE 
                            WHEN APV.CURR_CODE IN ('IDR', 'RP') OR APV.CURR_CODE IS NULL THEN 1 
                            ELSE ISNULL(RV.CURR_VRATE, 1) 
                        END) / USD_R.CURR_VRATE
                    ELSE 0
                END
            ) AS TOTAL_USD,
            CASE WHEN v.ITEM_ID IS NOT NULL THEN 1 ELSE 0 END AS IS_VENDOR
        FROM DI
        INNER JOIN DIPA_PAR ON DI.DI_ID = DIPA_PAR.DI_ID
        INNER JOIN PRICE ON DIPA_PAR.PRICE_ID = PRICE.PRICE_ID
        LEFT JOIN dbo.ACTIVE_PRICE_VIEW AS APV ON PRICE.PRICE_ID = APV.PRICE_ID
        LEFT JOIN dbo.CURR_RAT AS RV
            ON APV.CURR_CODE = RV.CURR_CODE
            AND DI.DI_DATE >= RV.CURR_SDATE
            AND (DI.DI_DATE <= RV.CURR_EDATE OR RV.CURR_EDATE IS NULL)
        LEFT JOIN dbo.CURR_RAT AS USD_R
            ON USD_R.CURR_CODE = 'USD'
            AND DI.DI_DATE >= USD_R.CURR_SDATE
            AND (DI.DI_DATE <= USD_R.CURR_EDATE OR USD_R.CURR_EDATE IS NULL)
        LEFT JOIN VendorItems v ON PRICE.PART_ID = v.ITEM_ID
        WHERE DI.DI_DATE IS NOT NULL 
            AND DI.DI_DATE >= '{$startDate}' AND DI.DI_DATE <= '{$endDate}'
        GROUP BY YEAR(DI.DI_DATE), CASE WHEN v.ITEM_ID IS NOT NULL THEN 1 ELSE 0 END
        ";

        foreach ($servers_to_try as $key) {
            $conn = @sqlsrv_connect($servers_config[$key]['ip'], $conn_opts_base);
            if (!$conn) {
                $conn_errors[] = "<strong>{$servers_config[$key]['label']}</strong> offline.";
                continue;
            }
            $server_available[$key] = true;
            $stmt = @sqlsrv_query($conn, $sql);
            if ($stmt) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $y = (int)$row['SALES_YEAR'];
                    $v = (int)$row['IS_VENDOR'];
                    $s = (float)$row['TOTAL_SALES'];
                    $usd = isset($row['TOTAL_USD']) ? (float)$row['TOTAL_USD'] : 0;
                    if (isset($raw_yearly[$y])) {
                        if ($v === 1) $raw_yearly[$y]['vendor'] += $s;
                        else $raw_yearly[$y]['internal'] += $s;
                        $raw_yearly[$y]['usd'] += $usd;
                    }
                }
                sqlsrv_free_stmt($stmt);
            }
            sqlsrv_close($conn);
        }

        echo json_encode([
            'status' => 'success',
            'yearly_data' => array_values($raw_yearly),
            'servers' => $server_available,
            'errors' => $conn_errors,
            'plant_active' => $selected_plant
        ]);
        exit;
    }

    // ----------------------------------------
    // ACTION 2: FETCH BULANAN (DENGAN TARIKAN KURS)
    // ----------------------------------------
    if ($action == 'fetch_monthly') {
        $targetYear = isset($_GET['targetYear']) ? (int)$_GET['targetYear'] : date('Y');
        
        $raw_monthly = ['vendor' => array_fill(0, 12, 0), 'internal' => array_fill(0, 12, 0), 'usd' => array_fill(0, 12, 0)];
        $raw_monthly_rates = array_fill(0, 12, []); 

        $startDate = $targetYear . "-01-01";
        $endDate = $targetYear . "-12-31 23:59:59";

        $sql = "
        WITH VendorItems AS (
            SELECT DISTINCT INV_TRAN.ITEM_ID
            FROM TRANS
            INNER JOIN INV_TRAN ON TRANS.TRAN_ID = INV_TRAN.TRAN_ID
            WHERE TRANS.TRTY_CODE = '12'
        )
        SELECT
            MONTH(DI.DI_DATE) AS SALES_MONTH,
            CASE WHEN v.ITEM_ID IS NOT NULL THEN 1 ELSE 0 END AS IS_VENDOR,
            APV.CURR_CODE,
            RV.CURR_VRATE,
            SUM(
                DIPA_PAR.QTY * DIPA_PAR.PART_PRICE * CASE 
                    WHEN APV.CURR_CODE IN ('IDR', 'RP') OR APV.CURR_CODE IS NULL THEN 1 
                    ELSE ISNULL(RV.CURR_VRATE, 1) 
                END
            ) AS TOTAL_SALES,
            SUM(
                CASE 
                    WHEN ISNULL(USD_R.CURR_VRATE, 0) > 0 THEN
                        (DIPA_PAR.QTY * DIPA_PAR.PART_PRICE * CASE 
                            WHEN APV.CURR_CODE IN ('IDR', 'RP') OR APV.CURR_CODE IS NULL THEN 1 
                            ELSE ISNULL(RV.CURR_VRATE, 1) 
                        END) / USD_R.CURR_VRATE
                    ELSE 0
                END
            ) AS TOTAL_USD
        FROM DI
        INNER JOIN DIPA_PAR ON DI.DI_ID = DIPA_PAR.DI_ID
        INNER JOIN PRICE ON DIPA_PAR.PRICE_ID = PRICE.PRICE_ID
        LEFT JOIN dbo.ACTIVE_PRICE_VIEW AS APV ON PRICE.PRICE_ID = APV.PRICE_ID
        LEFT JOIN dbo.CURR_RAT AS RV
            ON APV.CURR_CODE = RV.CURR_CODE
            AND DI.DI_DATE >= RV.CURR_SDATE
            AND (DI.DI_DATE <= RV.CURR_EDATE OR RV.CURR_EDATE IS NULL)
        LEFT JOIN dbo.CURR_RAT AS USD_R
            ON USD_R.CURR_CODE = 'USD'
            AND DI.DI_DATE >= USD_R.CURR_SDATE
            AND (DI.DI_DATE <= USD_R.CURR_EDATE OR USD_R.CURR_EDATE IS NULL)
        LEFT JOIN VendorItems v ON PRICE.PART_ID = v.ITEM_ID
        WHERE DI.DI_DATE IS NOT NULL 
            AND DI.DI_DATE >= '{$startDate}' AND DI.DI_DATE <= '{$endDate}'
        GROUP BY 
            MONTH(DI.DI_DATE), 
            CASE WHEN v.ITEM_ID IS NOT NULL THEN 1 ELSE 0 END,
            APV.CURR_CODE,
            RV.CURR_VRATE
        ";

        foreach ($servers_to_try as $key) {
            $conn = @sqlsrv_connect($servers_config[$key]['ip'], $conn_opts_base);
            if (!$conn) continue;
            
            $stmt = @sqlsrv_query($conn, $sql);
            if ($stmt) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $m = (int)$row['SALES_MONTH'];
                    $v = (int)$row['IS_VENDOR'];
                    $s = (float)$row['TOTAL_SALES'];
                    $usd = isset($row['TOTAL_USD']) ? (float)$row['TOTAL_USD'] : 0;
                    
                    $curr = isset($row['CURR_CODE']) ? trim($row['CURR_CODE']) : '';
                    $rate = isset($row['CURR_VRATE']) ? (float)$row['CURR_VRATE'] : 1;

                    if ($v === 1) $raw_monthly['vendor'][$m - 1] += $s;
                    else $raw_monthly['internal'][$m - 1] += $s;
                    $raw_monthly['usd'][$m - 1] += $usd;
                    
                    if ($curr !== '' && !in_array(strtoupper($curr), ['IDR', 'RP'])) {
                        $rate_str = strtoupper($curr) . ' ' . number_format($rate, 0, ',', '.');
                        if (!in_array($rate_str, $raw_monthly_rates[$m - 1])) {
                            $raw_monthly_rates[$m - 1][] = $rate_str;
                        }
                    }
                }
                sqlsrv_free_stmt($stmt);
            }
            sqlsrv_close($conn);
        }

        echo json_encode([
            'status' => 'success', 
            'monthly_data' => $raw_monthly,
            'monthly_rates' => $raw_monthly_rates
        ]);
        exit;
    }
}

// ============================================
// BLOK UI / TAMPILAN AWAL 
// ============================================
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
        
        #loadingOverlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(4px);
            z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center;
            opacity: 1; transition: opacity 0.4s ease;
        }
        #loadingOverlay.hidden { opacity: 0; pointer-events: none; }
        .spinner-ring {
            width: 50px; height: 50px; border: 4px solid var(--border); border-top-color: var(--accent);
            border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 16px;
        }
        .mini-loader { display:none; align-items:center; gap:10px; color:var(--accent); font-size:13px; font-weight:600; font-family:'Space Grotesk',sans-serif; margin-bottom:15px; justify-content:center;}
        .mini-loader i { animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner-text { font-family: 'Space Grotesk', sans-serif; font-weight: 700; color: var(--accent); letter-spacing: 1px; }

        body::before{content:'';position:fixed;top:-250px;right:-150px;width:600px;height:600px;background:radial-gradient(circle,rgba(234,88,12,0.035) 0%,transparent 65%);pointer-events:none;animation:floatB 12s ease-in-out infinite alternate;}
        body::after{content:'';position:fixed;bottom:-250px;left:-150px;width:600px;height:600px;background:radial-gradient(circle,rgba(5,150,105,0.035) 0%,transparent 65%);pointer-events:none;animation:floatB 14s ease-in-out infinite alternate-reverse;}
        @keyframes floatB{0%{transform:translate(0,0) scale(1);}100%{transform:translate(30px,-20px) scale(1.08);}}
        
        .container{max-width:1100px;margin:0 auto;position:relative;z-index:1;}
        .header{margin-bottom:16px;}
        .header-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:15px;}
        .header-title-group{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
        .header h1{font-family:'Space Grotesk',sans-serif;font-size:22px;font-weight:700;letter-spacing:-.3px;}
        .header h1 i{color:var(--accent);margin-right:6px;font-size:18px;}
        .plant-badge{background:var(--accent-dim);color:var(--accent);font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;letter-spacing:.5px;}
        .plant-badge.offline-badge{background:rgba(248,81,73,0.12);color:var(--danger);}
        .header-sub{color:var(--muted);font-size:13px;}
        .header-sub b{color:var(--text);font-weight:600;}

        .header-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap; display:none;}
        .btn-pdf,.btn-print,.btn-excel{border:none;padding:8px 16px;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;transition:all 0.2s;white-space:nowrap;}
        .btn-pdf{background:var(--accent);color:#FFFFFF;box-shadow:0 4px 12px rgba(217,119,6,0.20);}
        .btn-pdf:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(217,119,6,0.28);}
        .btn-print{background:#2563EB;color:#FFFFFF;box-shadow:0 4px 12px rgba(37,99,235,0.18);}
        .btn-print:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(37,99,235,0.28);}
        .btn-excel{background:#15803D;color:#FFFFFF;box-shadow:0 4px 12px rgba(21,128,61,0.20);}
        .btn-excel:hover{transform:translateY(-2px);background:#166534;box-shadow:0 6px 16px rgba(21,128,61,0.28);}

        .err-box{background:rgba(248,81,73,0.08);border:1px solid rgba(248,81,73,0.25);border-radius:10px;padding:14px 18px;margin-bottom:12px; display:none;}
        .err-box .err-title{color:var(--danger);font-size:12px;font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:6px;}
        .err-box .err-content { color:rgba(248,81,73,0.85);font-size:12px;line-height:1.7;}

        .filter-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:12px 18px;margin-bottom:12px;box-shadow:0 6px 20px rgba(15,23,42,0.045);}
        .filter-bar label{font-size:12px;color:var(--muted);font-weight:600;white-space:nowrap;}
        .filter-bar select{background:var(--bg);border:1px solid var(--border);color:var(--text);padding:8px 32px 8px 12px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;outline:none;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238B949E' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;}
        
        .preset-row{display:flex;align-items:center;gap:6px;margin-bottom:16px;flex-wrap:wrap;}
        .preset-row span{font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-right:4px;}
        .preset-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border-radius:20px;font-family:'DM Sans',sans-serif;font-size:11px;font-weight:600;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;transition:all .2s;white-space:nowrap;}
        .preset-btn:hover{border-color:var(--muted);color:var(--text);transform:translateY(-1px);}
        .preset-btn.active{border-color:var(--accent);color:var(--accent);background:var(--accent-dim);}

        .chart-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:28px 32px 24px;position:relative;overflow:hidden;margin-bottom:16px;box-shadow:0 6px 20px rgba(15,23,42,0.045);}
        .chart-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--vendor) 0%,var(--accent) 50%,var(--internal) 100%);}
        .chart-title{font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:24px;display:flex;align-items:center;gap:8px;}
        .chart-title i{color:var(--accent);font-size:14px;}
        .chart-title .click-hint{font-size:11px;font-weight:400;color:var(--muted);text-transform:none;letter-spacing:0;margin-left:auto;display:flex;align-items:center;gap:5px;opacity:.7;}
        .chart-area{position:relative;width:100%;height:380px;}
        .chart-area canvas{width:100%!important;height:100%!important;}

        .empty-state{display:none;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;text-align:center;}
        .empty-state .empty-icon{width:80px;height:80px;border-radius:50%;background:rgba(248,81,73,0.08);display:flex;align-items:center;justify-content:center;font-size:32px;color:var(--danger);margin-bottom:20px;}
        .empty-state h3{font-family:'Space Grotesk',sans-serif;font-size:18px;font-weight:700;color:var(--text);margin-bottom:8px;}

        .custom-legend{display:flex;align-items:center;justify-content:center;gap:32px;margin-top:20px;padding-top:18px;border-top:1px solid var(--border);flex-wrap:wrap;}
        .legend-item{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);font-weight:500;cursor:default;}
        .legend-dot{width:12px;height:12px;border-radius:3px;}
        .legend-dot.vendor{background:linear-gradient(180deg,#FF8C5A,#FF6B35);}
        .legend-dot.internal{background:linear-gradient(180deg,#33E0BE,#00D4AA);}

        .monthly-panel{max-height:0;overflow:hidden;opacity:0;transform:translateY(-8px);transition:max-height .5s ease,opacity .4s ease,transform .4s ease;margin-top:0;}
        .monthly-panel.open{max-height:800px;opacity:1;transform:translateY(0);margin-bottom:16px;}
        .monthly-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:24px 28px 20px;position:relative;overflow:hidden;box-shadow:0 6px 20px rgba(15,23,42,0.045);}
        .monthly-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--accent) 0%,var(--internal) 50%,var(--accent) 100%);}
        .monthly-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;}
        .monthly-header-left{display:flex;align-items:center;gap:10px;}
        .monthly-header-left .year-badge{background:var(--accent-dim);color:var(--accent);font-family:'Space Grotesk',sans-serif;font-size:15px;font-weight:700;padding:5px 14px;border-radius:8px;}
        .close-btn{width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;}
        .monthly-chart-area{position:relative;width:100%;height:250px;}
        .monthly-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;margin-top:16px;padding-top:14px;border-top:1px solid var(--border);}
        
        .ms-item{display:flex;flex-direction:column;gap:2px;}
        .ms-item .ms-label{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;font-weight:600;}
        .ms-item .ms-val{font-family:'Space Grotesk',sans-serif;font-size:15px;font-weight:700;}
        .ms-item .ms-val.mv{color:var(--vendor);}.ms-item .ms-val.mi{color:var(--internal);}.ms-item .ms-val.ma{color:var(--accent);}

        .summary-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:16px;}
        .summary-card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:16px 18px;position:relative;box-shadow:0 6px 20px rgba(15,23,42,0.045);}
        .summary-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;}
        .summary-card.sv::before{background:var(--vendor);}.summary-card.si::before{background:var(--internal);}.summary-card.sa::before{background:var(--accent);}
        .sc-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;margin-bottom:8px;}
        .sv .sc-icon{background:var(--vendor-dim);color:var(--vendor);}.si .sc-icon{background:var(--internal-dim);color:var(--internal);}.sa .sc-icon{background:var(--accent-dim);color:var(--accent);}
        .sc-label{font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:600;margin-bottom:4px;}
        .sc-num{font-family:'Space Grotesk',sans-serif;font-size:20px;font-weight:700;line-height:1;}
        .sc-sub{font-size:11px;color:var(--muted);margin-top:5px;}
        .sc-sub .pct{font-weight:700;}
        .pv{color:var(--vendor);}.pi{color:var(--internal);}

        .table-card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:24px 20px;position:relative;box-shadow:0 6px 20px rgba(15,23,42,0.045); display:none;}
        .table-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--internal) 0%,var(--accent) 50%,var(--vendor) 100%);}
        .table-wrapper{overflow-x:auto;margin-top:12px;}
        .data-table{width:100%;border-collapse:collapse;font-size:12px;text-align:left;}
        .data-table th{font-family:'Space Grotesk',sans-serif;background:#F3F4F6;padding:8px 10px;font-weight:600;border-bottom:2px solid var(--border);white-space:nowrap;font-size:11px;}
        .data-table td{padding:7px 10px;border-bottom:1px solid #EEF2F7;font-family:'Space Grotesk',sans-serif;font-size:11px;white-space:nowrap;}
        .data-table tr.total-row{background:rgba(255,217,61,0.05)!important;font-weight:bold;}
        .data-table tr.total-row td{border-top:2px solid var(--border);border-bottom:2px solid var(--border);color:var(--accent);}
        .t-vendor{color:var(--vendor)!important;}
        .t-internal{color:var(--internal)!important;}
        .pct-bar-cell{display:flex;align-items:center;justify-content:center;}
        .pct-bar-track{width:38px;height:6px;border-radius:3px;background:rgba(17,24,39,0.08);overflow:hidden;position:relative;}
        .pct-bar-fill-v{position:absolute;left:0;top:0;height:100%;border-radius:3px;background:var(--vendor);}
        .pct-bar-fill-i{position:absolute;right:0;top:0;height:100%;border-radius:3px;background:var(--internal);}

        .table-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-left:auto;}
        .table-tabs{display:flex;gap:8px;border-bottom:1px solid var(--border);padding-bottom:10px;}
        .tab-btn{background:transparent;border:1px solid var(--border);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;font-family:'DM Sans',sans-serif;}
        .tab-btn.active{background:var(--accent-dim);color:var(--accent);border-color:var(--accent);}
        .table-title-container{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:10px;}
        
        .toast{position:fixed;bottom:24px;right:24px;background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:12px 20px;font-size:13px;z-index:999;opacity:0;transform:translateY(12px);transition:all .3s;}
        .toast.show{opacity:1;transform:translateY(0);}
        
        body, th, td, h1, .chart-title, .sc-label, .sc-num { color: #000000 !important; }
    </style>
</head>
<body>
<!-- Loading Overlay -->
<div id="loadingOverlay">
    <div class="spinner-ring"></div>
    <div class="spinner-text">Menarik Data Tahun Ini...</div>
</div>

<div class="container" id="mainContainer">
    <div class="header">
        <div class="header-top">
            <div class="header-title-group">
                <h1><i class="fas fa-chart-column"></i> Perbandingan Tahunan — Internal vs Vendor</h1>
                <span class="plant-badge" id="topPlantBadge">PLANT ...</span>
            </div>
            <div class="header-actions" id="exportBtnGroup">
                <button id="printBtn" class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                <button id="pdfBtn" class="btn-pdf" onclick="generatePDF()"><i class="fas fa-file-pdf"></i> Download PDF</button>
            </div>
        </div>
        <div class="header-sub">Nilai Sales (IDR) per tahun &nbsp;|&nbsp; Periode: <b id="periodLabel">...</b></div>
    </div>
    
    <div class="err-box" id="errorBox">
        <div class="err-title"><i class="fas fa-triangle-exclamation"></i> Peringatan Koneksi</div>
        <div class="err-content" id="errorContent"></div>
    </div>
    
    <div class="filter-bar">
        <label for="plantFilter"><i class="fas fa-building" style="color:var(--accent);"></i> Lokasi Plant</label>
        <select id="plantFilter">
            <option value="all">Gabungan (P1 & P2)</option>
            <option value="p1">Plant 1 Saja</option>
            <option value="p2">Plant 2 Saja</option>
        </select>
        <div class="sep"></div><label for="startYear">Dari</label><select id="startYear"></select>
        <div class="sep"></div><label for="endYear">Sampai</label><select id="endYear"></select>
        <div class="range-hint"><i class="fas fa-calendar-days"></i><span id="rangeText">... tahun</span></div>
        <button class="preset-btn" style="margin-left:10px; background:var(--accent); color:#fff; border:none;" onclick="triggerFetchYearly()">Terapkan Filter</button>
    </div>
    
    <div class="preset-row">
        <span><i class="fas fa-bolt" style="color:var(--accent);margin-right:2px;"></i> Cepat:</span>
        <button class="preset-btn active" onclick="applyPreset(1)">Tahun Ini</button>
        <button class="preset-btn" onclick="applyPreset(3)">3 Tahun</button>
        <button class="preset-btn" onclick="applyPreset(5)">5 Tahun</button>
        <button class="preset-btn" onclick="applyPreset(10)">10 Tahun</button>
    </div>

    <div class="chart-card">
        <div class="chart-title"><i class="fas fa-chart-bar"></i> Tren Nilai Sales Tahunan<span class="click-hint"><i class="fas fa-hand-pointer"></i> Klik bar merah/hijau untuk detail bulan</span></div>
        <div class="empty-state" id="emptyState">
            <div class="empty-icon"><i class="fas fa-server"></i></div><h3>Tidak Ada Data</h3><p>Tidak ada data pada rentang waktu ini atau server offline.</p>
        </div>
        <div class="chart-area" id="chartAreaContainer"><canvas id="annualBarChart"></canvas></div>
        <div class="custom-legend" id="legendBar">
            <div class="legend-item"><div class="legend-dot vendor"></div>Vendor</div>
            <div class="legend-item"><div class="legend-dot internal"></div>Internal</div>
            <div class="legend-item"><div style="width:18px;height:2px;background:var(--accent);display:inline-block;margin-right:4px;"></div>Total Gabungan</div>
        </div>
    </div>

    <div class="monthly-panel" id="monthlyPanel">
        <div class="monthly-card">
            <div class="monthly-header">
                <div class="monthly-header-left"><span class="year-badge" id="monthlyYearBadge">...</span><h3>Detail Bulanan</h3></div>
                <button class="close-btn" id="closeMonthly"><i class="fas fa-xmark"></i></button>
            </div>
            
            <div class="mini-loader" id="monthlyLoader"><i class="fas fa-circle-notch"></i> Menarik data bulan...</div>
            
            <div id="monthlyContentContainer" style="display:none;">
                <div class="monthly-chart-area"><canvas id="monthlyBarChart"></canvas></div>
                <div class="monthly-summary" id="monthlySummary"></div>
            </div>
        </div>
    </div>

    <div class="summary-row" id="summaryRow"></div>

    <div class="table-card" id="tableDataContainer">
        <div class="table-title-container">
            <div class="chart-title" style="margin-bottom:0;"><i class="fas fa-table"></i> Tabulasi Rincian</div>
            <div class="table-actions">
                <button type="button" id="excelBtn" class="btn-excel" onclick="exportTableExcel()"><i class="fas fa-file-excel"></i> Export Excel</button>
                <div class="table-tabs">
                    <button type="button" class="tab-btn active" id="tabTahunanBtn" onclick="switchTableTab('tahunan')">Tahunan</button>
                    <button type="button" class="tab-btn" id="tabBulananBtn" onclick="switchTableTab('bulanan')">Bulanan (<span id="tabBulananYear">-</span>)</button>
                </div>
            </div>
        </div>
        <div class="table-wrapper" id="wrapperTahunan">
            <table class="data-table" id="tableTahunan">
                <thead><tr><th>Tahun</th><th style="text-align:right">Vendor (IDR)</th><th style="text-align:right">Internal (IDR)</th><th style="text-align:center">% Ven</th><th style="text-align:center">% Int</th><th style="text-align:center">Proporsi</th><th style="text-align:right">Total (USD)</th><th style="text-align:right">Total (IDR)</th></tr></thead>
                <tbody id="tbodyTahunan"></tbody>
            </table>
        </div>
        <div class="table-wrapper" id="wrapperBulanan" style="display:none;">
            <table class="data-table" id="tableBulanan">
                <thead><tr>
                    <th style="text-align:center">Bulan</th>
                    <th style="text-align:center;color:var(--accent);">Kurs Aktif</th>
                    <th style="text-align:right">Vendor (IDR)</th>
                    <th style="text-align:right">Internal (IDR)</th>
                    <th style="text-align:center">% Ven</th>
                    <th style="text-align:center">% Int</th>
                    <th style="text-align:center">Proporsi</th>
                    <th style="text-align:right">Total (USD)</th>
                    <th style="text-align:right">Total (IDR)</th>
                </tr></thead>
                <tbody id="tbodyBulanan"></tbody>
            </table>
        </div>
    </div>
</div>

<div class="toast" id="toast"><i class="fas fa-check-circle" style="color:var(--internal);"></i><span id="toastMsg"></span></div>

<script>
let ALL_DATA = [];
let CACHED_MONTHLY = {};
let chInst = null;
let chMInst = null;
let selYear = null;
let activeTableTab = 'tahunan';

const ML = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
const CURRENT_YEAR = new Date().getFullYear();

function initFilters() {
    let sS = document.getElementById('startYear');
    let sE = document.getElementById('endYear');
    for(let y = 2015; y <= CURRENT_YEAR + 1; y++) {
        sS.add(new Option(y, y));
        sE.add(new Option(y, y));
    }
    sS.value = CURRENT_YEAR; 
    sE.value = CURRENT_YEAR;
    let urlParams = new URLSearchParams(window.location.search);
    if(urlParams.has('plant')) document.getElementById('plantFilter').value = urlParams.get('plant');
}

function fS(n){if(n>=1e12)return(n/1e12).toFixed(1)+'T';if(n>=1e9)return(n/1e9).toFixed(1)+'M';if(n>=1e6)return(n/1e6).toFixed(1)+'Jt';return n.toString();}
function fF(n){return 'Rp ' + Math.round(n).toLocaleString('id-ID');}
function fUSD(n){return '$ ' + Number(n || 0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
function pBarH(pV){let pI=(100-pV).toFixed(1);return `<div class="pct-bar-cell"><div class="pct-bar-track"><div class="pct-bar-fill-v" style="width:${pV}%;"></div><div class="pct-bar-fill-i" style="width:${pI}%;"></div></div></div>`;}
function showToast(msg){let t=document.getElementById('toast');document.getElementById('toastMsg').textContent=msg;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),3000);}

function triggerFetchYearly() {
    let plant = document.getElementById('plantFilter').value;
    let sY = document.getElementById('startYear').value;
    let eY = document.getElementById('endYear').value;
    
    document.getElementById('loadingOverlay').classList.remove('hidden');
    
    fetch(`?action=fetch_yearly&plant=${plant}&startYear=${sY}&endYear=${eY}`)
        .then(res => res.json())
        .then(data => {
            ALL_DATA = data.yearly_data;
            updateUIYearly(data);
            document.getElementById('loadingOverlay').classList.add('hidden');
        })
        .catch(err => {
            showToast("Gagal menarik data");
            document.getElementById('loadingOverlay').classList.add('hidden');
        });
}

function updateUIYearly(data) {
    let sY = document.getElementById('startYear').value;
    let eY = document.getElementById('endYear').value;
    document.getElementById('periodLabel').textContent = sY + (sY !== eY ? ' — ' + eY : '');
    document.getElementById('rangeText').textContent = (eY - sY + 1) + ' tahun';
    
    let badge = document.getElementById('topPlantBadge');
    let p1 = data.servers.p1, p2 = data.servers.p2;
    if(data.plant_active === 'p1') { badge.innerHTML = 'PLANT 1' + (!p1?' (OFFLINE)':''); badge.className='plant-badge'+(!p1?' offline-badge':''); }
    else if(data.plant_active === 'p2') { badge.innerHTML = 'PLANT 2' + (!p2?' (OFFLINE)':''); badge.className='plant-badge'+(!p2?' offline-badge':''); }
    else { let txt = []; if(p1) txt.push('P1'); if(p2) txt.push('P2'); badge.innerHTML = txt.length > 0 ? txt.join(' & ') : 'SEMUA OFFLINE'; badge.className = 'plant-badge' + (txt.length===0?' offline-badge':''); }

    let errBox = document.getElementById('errorBox');
    if(data.errors && data.errors.length > 0) { errBox.style.display = 'block'; document.getElementById('errorContent').innerHTML = data.errors.map(e => `<div><i class="fas fa-circle" style="font-size:6px;vertical-align:middle;"></i> ${e}</div>`).join(''); } else { errBox.style.display = 'none'; }
    
    let hasData = ALL_DATA && ALL_DATA.some(d => d.vendor > 0 || d.internal > 0);
    document.getElementById('emptyState').style.display = hasData ? 'none' : 'flex';
    document.getElementById('chartAreaContainer').style.display = hasData ? 'block' : 'none';
    document.getElementById('legendBar').style.display = hasData ? 'flex' : 'none';
    document.getElementById('tableDataContainer').style.display = hasData ? 'block' : 'none';
    document.getElementById('exportBtnGroup').style.display = hasData ? 'flex' : 'none';

    if(hasData) { buildChartYearly(); buildSummaryYearly(); buildTableYearly(); } else { document.getElementById('summaryRow').innerHTML = ''; }
}

function buildChartYearly() {
    let lb = ALL_DATA.map(d => d.year), vD = ALL_DATA.map(d => d.vendor), iD = ALL_DATA.map(d => d.internal), tD = ALL_DATA.map(d => d.vendor + d.internal);
    let ctx = document.getElementById('annualBarChart').getContext('2d');
    if(chInst) chInst.destroy();
    let gV = ctx.createLinearGradient(0,0,0,380); gV.addColorStop(0,'rgba(234,88,12,0.85)'); gV.addColorStop(1,'rgba(234,88,12,0.35)');
    let gI = ctx.createLinearGradient(0,0,0,380); gI.addColorStop(0,'rgba(5,150,105,0.85)'); gI.addColorStop(1,'rgba(5,150,105,0.35)');
    chInst = new Chart(ctx,{type:'bar',data:{ labels:lb, datasets:[{label:'Vendor',data:vD,backgroundColor:gV,borderColor:'#EA580C',borderWidth:1,borderRadius:4,order:2},{label:'Internal',data:iD,backgroundColor:gI,borderColor:'#059669',borderWidth:1,borderRadius:4,order:3},{label:'Total',data:tD,type:'line',borderColor:'#D97706',backgroundColor:'rgba(217,119,6,0.08)',borderWidth:2.5,pointBackgroundColor:'#D97706',pointBorderColor:'#FFF',pointRadius:5,tension:0.3,fill:true,order:1}]},options:{ responsive:true, maintainAspectRatio:false, interaction:{mode:'index',intersect:false}, plugins:{legend:{display:false}}, scales:{y:{ticks:{callback:v=>fS(v)}}}, onClick:(e,els)=>{ if(els.length>0) loadMonthlyOnDemand(lb[els[0].index]); } }});
}

function buildSummaryYearly() {
    let tV=0, tI=0; ALL_DATA.forEach(d => { tV+=d.vendor; tI+=d.internal; });
    let tA = tV+tI; let pV = tA>0?(tV/tA*100):0; let pI = tA>0?(tI/tA*100):0;
    document.getElementById('summaryRow').innerHTML = `<div class="summary-card sv"><div class="sc-icon"><i class="fas fa-truck"></i></div><div class="sc-label">Total Vendor</div><div class="sc-num t-vendor">${fF(tV)}</div><div class="sc-sub"><span class="pct pv">${pV.toFixed(1)}%</span></div></div><div class="summary-card si"><div class="sc-icon"><i class="fas fa-building"></i></div><div class="sc-label">Total Internal</div><div class="sc-num t-internal">${fF(tI)}</div><div class="sc-sub"><span class="pct pi">${pI.toFixed(1)}%</span></div></div><div class="summary-card sa"><div class="sc-icon"><i class="fas fa-coins"></i></div><div class="sc-label">Total Gabungan</div><div class="sc-num">${fF(tA)}</div><div class="sc-sub">${ALL_DATA.length} tahun diproses</div></div>`;
}

function buildTableYearly() {
    let html='', tV=0, tI=0, tUSD=0;
    ALL_DATA.forEach(d => {
        let tot = d.vendor+d.internal; tV+=d.vendor; tI+=d.internal; tUSD += Number(d.usd || 0);
        let pV = tot>0?(d.vendor/tot*100):0; let pI = tot>0?(d.internal/tot*100):0;
        html += `<tr><td style="text-align:center">${d.year}</td><td class="t-vendor" style="text-align:right">${fF(d.vendor)}</td><td class="t-internal" style="text-align:right">${fF(d.internal)}</td><td style="text-align:center">${pV.toFixed(1)}%</td><td style="text-align:center">${pI.toFixed(1)}%</td><td>${pBarH(pV)}</td><td style="text-align:right;font-weight:bold">${fUSD(d.usd)}</td><td style="text-align:right;font-weight:bold">${fF(tot)}</td></tr>`;
    });
    let tA=tV+tI, pVA=tA>0?(tV/tA*100):0, pIA=tA>0?(tI/tA*100):0;
    html += `<tr class="total-row"><td style="text-align:center">TOTAL</td><td style="text-align:right">${fF(tV)}</td><td style="text-align:right">${fF(tI)}</td><td style="text-align:center">${pVA.toFixed(1)}%</td><td style="text-align:center">${pIA.toFixed(1)}%</td><td>${pBarH(pVA)}</td><td style="text-align:right">${fUSD(tUSD)}</td><td style="text-align:right">${fF(tA)}</td></tr>`;
    document.getElementById('tbodyTahunan').innerHTML = html;
}

function loadMonthlyOnDemand(yr) {
    selYear = yr;
    document.getElementById('monthlyYearBadge').textContent = yr;
    document.getElementById('tabBulananYear').textContent = yr;
    let panel = document.getElementById('monthlyPanel');
    panel.classList.add('open'); panel.scrollIntoView({behavior:'smooth'});
    
    if (CACHED_MONTHLY[yr]) { renderMonthlyUI(yr, CACHED_MONTHLY[yr].data, CACHED_MONTHLY[yr].rates); return; }

    document.getElementById('monthlyContentContainer').style.display = 'none';
    document.getElementById('monthlyLoader').style.display = 'flex';
    let plant = document.getElementById('plantFilter').value;
    
    fetch(`?action=fetch_monthly&plant=${plant}&targetYear=${yr}`)
        .then(res => res.json())
        .then(data => {
            CACHED_MONTHLY[yr] = { data: data.monthly_data, rates: data.monthly_rates };
            renderMonthlyUI(yr, data.monthly_data, data.monthly_rates);
            document.getElementById('monthlyLoader').style.display = 'none';
            document.getElementById('monthlyContentContainer').style.display = 'block';
        })
        .catch(err => { showToast("Gagal menarik detail bulanan"); document.getElementById('monthlyLoader').style.display = 'none'; });
}

function renderMonthlyUI(yr, md, rates) {
    document.getElementById('monthlyContentContainer').style.display = 'block';
    let ctx = document.getElementById('monthlyBarChart').getContext('2d');
    if(chMInst) chMInst.destroy();
    chMInst = new Chart(ctx,{type:'bar',data:{labels:ML,datasets:[{label:'Vendor',data:md.vendor,backgroundColor:'rgba(234,88,12,0.6)',borderColor:'#EA580C',borderWidth:1},{label:'Internal',data:md.internal,backgroundColor:'rgba(5,150,105,0.6)',borderColor:'#059669',borderWidth:1}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{ticks:{callback:v=>fS(v)}}}}});
    
    let html='', tV=0, tI=0, tUSD=0;
    for(let i=0; i<12; i++){
        let v=md.vendor[i], inn=md.internal[i], tot=v+inn, usd=Number((md.usd && md.usd[i]) ? md.usd[i] : 0); tV+=v; tI+=inn; tUSD+=usd;
        let pV = tot>0?(v/tot*100):0, pI = tot>0?(inn/tot*100):0;
        let rateText = (rates && rates[i] && rates[i].length > 0) ? rates[i].join('<br>') : '-';
        html += `<tr><td style="text-align:center">${ML[i]}</td><td style="text-align:center;font-size:10px;color:var(--accent);font-weight:600;">${rateText}</td><td class="t-vendor" style="text-align:right">${fF(v)}</td><td class="t-internal" style="text-align:right">${fF(inn)}</td><td style="text-align:center">${pV.toFixed(1)}%</td><td style="text-align:center">${pI.toFixed(1)}%</td><td>${pBarH(pV)}</td><td style="text-align:right;font-weight:bold">${fUSD(usd)}</td><td style="text-align:right;font-weight:bold">${fF(tot)}</td></tr>`;
    }
    let tA=tV+tI, pVA=tA>0?(tV/tA*100):0, pIA=tA>0?(tI/tA*100):0;
    html += `<tr class="total-row"><td style="text-align:center">TOTAL</td><td></td><td style="text-align:right">${fF(tV)}</td><td style="text-align:right">${fF(tI)}</td><td style="text-align:center">${pVA.toFixed(1)}%</td><td style="text-align:center">${pIA.toFixed(1)}%</td><td>${pBarH(pVA)}</td><td style="text-align:right">${fUSD(tUSD)}</td><td style="text-align:right">${fF(tA)}</td></tr>`;
    document.getElementById('tbodyBulanan').innerHTML = html;
    switchTableTab('bulanan');
}

function switchTableTab(tab){
    activeTableTab=tab;
    document.getElementById('tabTahunanBtn').classList.toggle('active',tab==='tahunan');
    document.getElementById('tabBulananBtn').classList.toggle('active',tab==='bulanan');
    document.getElementById('wrapperTahunan').style.display=tab==='tahunan'?'block':'none';
    document.getElementById('wrapperBulanan').style.display=tab==='bulanan'?'block':'none';
}

function applyPreset(v) {
    document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('endYear').value = CURRENT_YEAR;
    document.getElementById('startYear').value = CURRENT_YEAR - v + 1;
    triggerFetchYearly();
}

document.getElementById('closeMonthly').onclick = function() {
    document.getElementById('monthlyPanel').classList.remove('open');
    if(activeTableTab === 'bulanan') switchTableTab('tahunan');
};

document.addEventListener("DOMContentLoaded", () => {
    initFilters(); triggerFetchYearly(); 
});

// ==========================================
// EXPORT EXCEL SESUAI TAB YANG AKTIF 
// ==========================================
function exportTableExcel(){
    if(!ALL_DATA || ALL_DATA.length===0) return;
    var wb = XLSX.utils.book_new();
    
    let sY = document.getElementById('startYear').value;
    let eY = document.getElementById('endYear').value;
    let periodStr = sY === eY ? ("Tahun " + sY) : (sY + ' - ' + eY);

    if (activeTableTab === 'tahunan') {
        // ================== EXPORT TAHUNAN ==================
        var tV = 0, tI = 0, tUSD = 0;
        var rowsTahunan = [
            ['PT. IMC TEKNO INDONESIA'],
            ['LAPORAN SALES VENDOR VS INTERNAL (TAHUNAN)'],
            ['Periode: ' + periodStr],
            [], 
            ['Tahun', 'Vendor (IDR)', 'Internal (IDR)', '% Vendor', '% Internal', 'Total (USD)', 'Total (IDR)']
        ];

        ALL_DATA.forEach(function(d){
            var tot = d.vendor + d.internal;
            tV += d.vendor; tI += d.internal; tUSD += Number(d.usd || 0);
            var pV = tot > 0 ? (d.vendor/tot*100) : 0;
            var pI = tot > 0 ? (d.internal/tot*100) : 0;
            rowsTahunan.push([d.year, d.vendor, d.internal, Math.round(pV*100)/100, Math.round(pI*100)/100, Number(d.usd || 0), tot]);
        });

        var tA = tV + tI;
        rowsTahunan.push(['TOTAL', tV, tI, Math.round((tA>0?tV/tA*100:0)*100)/100, Math.round((tA>0?tI/tA*100:0)*100)/100, tUSD, tA]);

        var wsTahunan = XLSX.utils.aoa_to_sheet(rowsTahunan);
        wsTahunan['!cols'] = [{wch:10}, {wch:22}, {wch:22}, {wch:12}, {wch:12}, {wch:18}, {wch:22}];
        
        wsTahunan['!merges'] = [ { s: { r: 0, c: 0 }, e: { r: 0, c: 6 } }, { s: { r: 1, c: 0 }, e: { r: 1, c: 6 } }, { s: { r: 2, c: 0 }, e: { r: 2, c: 6 } } ];
        wsTahunan["A1"].s = { font: { bold: true, sz: 14 }, alignment: { horizontal: "center" } };
        wsTahunan["A2"].s = { font: { bold: true, sz: 12 }, alignment: { horizontal: "center" } };
        wsTahunan["A3"].s = { alignment: { horizontal: "center" } };
        
        for(let c = 0; c <= 6; c++) {
            let cell = XLSX.utils.encode_cell({r:4, c:c});
            if(wsTahunan[cell]) wsTahunan[cell].s = { font: { bold: true }, fill: { fgColor: { rgb: "F3F4F6" } }, alignment: { horizontal: "center" } };
        }

        XLSX.utils.book_append_sheet(wb, wsTahunan, 'Rincian Tahunan');
        let filename = 'Laporan_Sales_Tahunan_' + periodStr.replace(' - ', '_') + '.xlsx';
        XLSX.writeFile(wb, filename);

    } else if (activeTableTab === 'bulanan' && selYear && CACHED_MONTHLY[selYear]) {
        // ================== EXPORT BULANAN ==================
        let md = CACHED_MONTHLY[selYear].data;
        let rates = CACHED_MONTHLY[selYear].rates;
        
        let mRows = [
            ['PT. IMC TEKNO INDONESIA'],
            ['LAPORAN SALES VENDOR VS INTERNAL (BULANAN)'],
            ['Periode: Tahun ' + selYear],
            [],
            ['Bulan', 'Kurs Aktif', 'Vendor (IDR)', 'Internal (IDR)', '% Vendor', '% Internal', 'Total (USD)', 'Total (IDR)']
        ];
        
        let mTV=0, mTI=0, mUSD=0;
        for(let i=0; i<12; i++){
            let v = md.vendor[i], inn = md.internal[i], tot = v + inn, usd = Number((md.usd && md.usd[i]) ? md.usd[i] : 0);
            mTV += v; mTI += inn; mUSD += usd;
            let pV = tot > 0 ? (v/tot*100) : 0, pI = tot > 0 ? (inn/tot*100) : 0;
            let rText = (rates && rates[i] && rates[i].length > 0) ? rates[i].join(', ') : '-';
            mRows.push([ML[i], rText, v, inn, Math.round(pV*100)/100, Math.round(pI*100)/100, usd, tot]);
        }
        
        let mTA = mTV + mTI;
        mRows.push(['TOTAL', '', mTV, mTI, Math.round((mTA>0?mTV/mTA*100:0)*100)/100, Math.round((mTA>0?mTI/mTA*100:0)*100)/100, mUSD, mTA]);
        
        let wsBulanan = XLSX.utils.aoa_to_sheet(mRows);
        wsBulanan['!cols'] = [{wch:10}, {wch:15}, {wch:22}, {wch:22}, {wch:12}, {wch:12}, {wch:18}, {wch:22}];
        
        wsBulanan['!merges'] = [ { s: { r: 0, c: 0 }, e: { r: 0, c: 7 } }, { s: { r: 1, c: 0 }, e: { r: 1, c: 7 } }, { s: { r: 2, c: 0 }, e: { r: 2, c: 7 } } ];
        wsBulanan["A1"].s = { font: { bold: true, sz: 14 }, alignment: { horizontal: "center" } };
        wsBulanan["A2"].s = { font: { bold: true, sz: 12 }, alignment: { horizontal: "center" } };
        wsBulanan["A3"].s = { alignment: { horizontal: "center" } };
        
        for(let c = 0; c <= 7; c++) {
            let cell = XLSX.utils.encode_cell({r:4, c:c});
            if(wsBulanan[cell]) wsBulanan[cell].s = { font: { bold: true }, fill: { fgColor: { rgb: "F3F4F6" } }, alignment: { horizontal: "center" } };
        }

        XLSX.utils.book_append_sheet(wb, wsBulanan, 'Rincian Bulanan ' + selYear);
        let filename = 'Laporan_Sales_Bulanan_' + selYear + '.xlsx';
        XLSX.writeFile(wb, filename);
    }
    
    showToast('Excel berhasil diunduh');
}

function generatePDF(){
    var el=document.getElementById('mainContainer');
    html2pdf().set({margin:6,filename:'Sales.pdf',image:{type:'jpeg',quality:0.98},html2canvas:{scale:2},jsPDF:{unit:'mm',format:'a3',orientation:'landscape'}}).from(el).save();
}
</script>
</body>
</html>
<?php
session_start();
require_once __DIR__ . "/../config/database_ordering.php";

if (!isset($_SESSION['db_user']) || $_SESSION['db_user'] == "") {
    if(isset($_GET['action'])) die(json_encode(['error' => 'Unauthorized']));
    die('<div style="padding:24px;color:#F85149;background:#0D1117;font-family:monospace;">Silakan login terlebih dahulu.</div>');
}

 $uid = $_SESSION['db_user'];
 $pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "";
 $dbName = "msData";

 $servers_config = [
    'p1' => ['ip' => '192.168.0.4', 'label' => 'Plant 1'],
    'p2' => ['ip' => '192.168.0.9', 'label' => 'Plant 2'],
];

// ==========================================
// API ENDPOINT: CEK RANGE TAHUN (CEPAT)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'get_years') {
    header('Content-Type: application/json; charset=utf-8');
    $min_y = date('Y') - 5; $max_y = date('Y');
    
    foreach ($servers_config as $srv) {
        $conn = @sqlsrv_connect($srv['ip'], ["Database"=>$dbName, "Uid"=>$uid, "PWD"=>$pwd, "LoginTimeout"=>2, "Encrypt"=>false]);
        if ($conn) {
            $stmt = @sqlsrv_query($conn, "SELECT ISNULL(MIN(YEAR(DI_DATE)), ".(date('Y')-5).") AS MIN_Y, ISNULL(MAX(YEAR(DI_DATE)), ".date('Y').") AS MAX_Y FROM DI WHERE DI_DATE IS NOT NULL");
            if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $min_y = (int)$row['MIN_Y']; $max_y = (int)$row['MAX_Y'];
                sqlsrv_free_stmt($stmt); sqlsrv_close($conn); break;
            }
            sqlsrv_close($conn);
        }
    }
    echo json_encode(['min_year' => $min_y, 'max_year' => $max_y]);
    exit;
}

// ==========================================
// API ENDPOINT: GET DATA SALES & HPP
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'get_data') {
    set_time_limit(120);
    header('Content-Type: application/json; charset=utf-8');
    
    $selected_plant = isset($_GET['plant']) ? strtolower($_GET['plant']) : 'all';
    $start_year = (int)$_GET['start_year'];
    $end_year = (int)$_GET['end_year'];
    $start_date = "$start_year-01-01 00:00:00";
    $end_date = "$end_year-12-31 23:59:59";

    // --- SQL SALES ---
    $sql_sales = "
    WITH VendorItems AS (
        SELECT DISTINCT INV_TRAN.ITEM_ID FROM TRANS INNER JOIN INV_TRAN ON TRANS.TRAN_ID = INV_TRAN.TRAN_ID WHERE TRANS.TRTY_CODE = '12'
    ),
    SalesData AS (
        SELECT ITEMS.ITEM_ID, YEAR(DI.DI_DATE) AS SALES_YEAR, MONTH(DI.DI_DATE) AS SALES_MONTH, DIPA_PAR.QTY AS QTY, DIPA_PAR.PART_PRICE AS PRICE,
        ISNULL(APV.CURR_CODE, 'IDR') AS CURR_CODE, ISNULL(RV.CURR_VRATE, 1) AS CURR_RATE
        FROM DI INNER JOIN DIPA_PAR ON DI.DI_ID = DIPA_PAR.DI_ID INNER JOIN PRICE ON DIPA_PAR.PRICE_ID = PRICE.PRICE_ID
        INNER JOIN ITEMS ON PRICE.PART_ID = ITEMS.ITEM_ID
        LEFT JOIN dbo.ACTIVE_PRICE_VIEW AS APV ON PRICE.PRICE_ID = APV.PRICE_ID LEFT JOIN dbo.TODAY_RATE_VIEW AS RV ON APV.CURR_CODE = RV.CURR_CODE
        WHERE DI.DI_DATE IS NOT NULL AND DI.DI_DATE BETWEEN ? AND ?
    )
    SELECT s.SALES_YEAR, s.SALES_MONTH, SUM(s.QTY * s.PRICE * CASE WHEN s.CURR_CODE IN ('IDR', 'RP', 'Rp', 'Rp.') THEN 1 ELSE s.CURR_RATE END) AS TOTAL_SALES,
    CASE WHEN v.ITEM_ID IS NOT NULL THEN 1 ELSE 0 END AS IS_VENDOR
    FROM SalesData s LEFT JOIN VendorItems v ON s.ITEM_ID = v.ITEM_ID
    GROUP BY s.SALES_YEAR, s.SALES_MONTH, CASE WHEN v.ITEM_ID IS NOT NULL THEN 1 ELSE 0 END";

    // --- SQL HPP (PERSIS MENGIKUTI PHP LIST HARGA MATERIAL & VENDOR TERBARU) ---
    // Acuan: getMaterialHarga() pada file List Harga Material Konsumsi & Vendor.
    //
    // INTERNAL:
    // - BOM_DEFAULT langsung / 1 level, sama seperti daftar harga.
    // - Komponen hanya ITTY_CODE 02 dan 03.
    // - Part & material harus aktif.
    // - Harga PO terakhir per material: ORDER BY PO_DATE DESC, PO_ID DESC.
    // - IDR langsung; selain IDR dikali CURR_RAT.CURR_VRATE sesuai PO_DATE.
    // - Rumus dibulatkan per baris komponen, lalu dijumlahkan per part:
    //      ITTY 02 = ROUND((BOM_QTY * Harga_IDR) / 1000, 2)
    //      ITTY 03 = ROUND(BOM_QTY * Harga_IDR, 2)
    //
    // VENDOR:
    // - RECEIVE.RCV_TYPE = 3 dan ITEMS.ITTY_CODE = '01'.
    // - Harga receive terakhir per item.
    // - IDR langsung; selain IDR dikali CURR_RAT.CURR_VRATE sesuai RCV_DATE.
    // - Harga unit vendor dibulatkan 2 desimal, sama dengan TOTAL_HARGA pada daftar harga.
    //
    // Tidak memakai BOM recursive, CURR_RAT_TALLY, cutoff USD, atau filter SUP_CODE.
    $sql_hpp_safe = "
    SET NOCOUNT ON;

    ;WITH LatestInternalPO AS (
        SELECT
            PD.ITEM_ID AS MAT_ID,
            PD.POD_PRICE,
            PO.PO_DATE AS PRICE_DATE_RAW,
            PD.POD_UNIT,
            PO.PO_CUR,
            ROW_NUMBER() OVER (
                PARTITION BY PD.ITEM_ID
                ORDER BY PO.PO_DATE DESC, PO.PO_ID DESC
            ) AS RN
        FROM dbo.PO_DETAIL PD
        INNER JOIN dbo.PO PO ON PD.PO_ID = PO.PO_ID
    ),
    InternalBOM AS (
        SELECT
            I.ITEM_ID AS PART_ID,
            M.ITEM_ID AS MAT_ID,
            BD.QTY AS BOM_QTY,
            M.ITTY_CODE
        FROM dbo.BOM_DEFAULT BD
        INNER JOIN dbo.ITEMS M ON BD.ITEM_ID = M.ITEM_ID
        INNER JOIN dbo.ITEMS I ON BD.PART_ID = I.ITEM_ID
        WHERE M.ITTY_CODE IN ('02', '03')
          AND I.ITEM_INACTIVE = 0
          AND M.ITEM_INACTIVE = 0
    ),
    BOM_HPP AS (
        SELECT
            B.PART_ID,
            SUM(
                CASE
                    WHEN B.ITTY_CODE = '02' THEN
                        ROUND(
                            (B.BOM_QTY * CASE
                                WHEN ISNULL(P.PO_CUR, 'IDR') = 'IDR'
                                    THEN ISNULL(P.POD_PRICE, 0)
                                ELSE ISNULL(P.POD_PRICE, 0) * ISNULL(C.CURR_VRATE, 0)
                            END) / 1000.0,
                            2
                        )
                    WHEN B.ITTY_CODE = '03' THEN
                        ROUND(
                            B.BOM_QTY * CASE
                                WHEN ISNULL(P.PO_CUR, 'IDR') = 'IDR'
                                    THEN ISNULL(P.POD_PRICE, 0)
                                ELSE ISNULL(P.POD_PRICE, 0) * ISNULL(C.CURR_VRATE, 0)
                            END,
                            2
                        )
                    ELSE 0
                END
            ) AS HPP_PER_UNIT
        FROM InternalBOM B
        LEFT JOIN LatestInternalPO P
            ON B.MAT_ID = P.MAT_ID
           AND P.RN = 1
        LEFT JOIN dbo.CURR_RAT C
            ON P.PO_CUR = C.CURR_CODE
           AND P.PRICE_DATE_RAW BETWEEN C.CURR_SDATE AND C.CURR_EDATE
        GROUP BY B.PART_ID
    ),
    DailyProd AS (
        SELECT
            P.PD_DATE,
            SUM(P.PD_OK + P.PD_HO) AS TOTAL_QTY,
            BH.HPP_PER_UNIT
        FROM dbo.PRODUCTION P
        INNER JOIN dbo.WO W ON P.WO_ID = W.WO_ID
        LEFT JOIN BOM_HPP BH ON W.ITEM_ID = BH.PART_ID
        WHERE P.PD_DATE BETWEEN ? AND ?
        GROUP BY P.PD_LOT, P.PD_DATE, W.ITEM_ID, BH.HPP_PER_UNIT
        HAVING SUM(P.PD_OK + P.PD_HO) > 0
    ),
    LatestVendorReceive AS (
        SELECT
            I.ITEM_ID,
            RD.POD_PRICE,
            R.RCV_DATE AS PRICE_DATE_RAW,
            PO.PO_CUR,
            R.RCV_NO,
            ROW_NUMBER() OVER (
                PARTITION BY RD.ITEM_ID
                ORDER BY R.RCV_DATE DESC, R.RCV_ID DESC, RD.PO_ID DESC
            ) AS RN
        FROM dbo.RECEIVE R
        INNER JOIN dbo.RECEIVE_DETAIL RD ON R.RCV_ID = RD.RCV_ID
        INNER JOIN dbo.PO PO ON RD.PO_ID = PO.PO_ID
        INNER JOIN dbo.ITEMS I ON RD.ITEM_ID = I.ITEM_ID
        WHERE R.RCV_TYPE = 3
          AND I.ITTY_CODE = '01'
          AND I.ITEM_INACTIVE = 0
    ),
    VendorPrice AS (
        SELECT
            V.ITEM_ID,
            ROUND(
                CASE
                    WHEN ISNULL(V.PO_CUR, 'IDR') = 'IDR'
                        THEN ISNULL(V.POD_PRICE, 0)
                    ELSE ISNULL(V.POD_PRICE, 0) * ISNULL(CV.CURR_VRATE, 0)
                END,
                2
            ) AS VEND_RATE_IDR
        FROM LatestVendorReceive V
        LEFT JOIN dbo.CURR_RAT CV
            ON V.PO_CUR = CV.CURR_CODE
           AND V.PRICE_DATE_RAW BETWEEN CV.CURR_SDATE AND CV.CURR_EDATE
        WHERE V.RN = 1
    ),
    VendorProduction AS (
        SELECT
            R.RCV_DATE AS TRAN_DATE,
            RD.ITEM_ID,
            SUM(RD.RCVD_QTY) AS VEND_QTY,
            MAX(ISNULL(VP.VEND_RATE_IDR, 0)) AS VEND_RATE_IDR
        FROM dbo.RECEIVE R
        INNER JOIN dbo.RECEIVE_DETAIL RD ON R.RCV_ID = RD.RCV_ID
        INNER JOIN dbo.ITEMS I ON RD.ITEM_ID = I.ITEM_ID
        LEFT JOIN VendorPrice VP ON RD.ITEM_ID = VP.ITEM_ID
        WHERE R.RCV_TYPE = 3
          AND I.ITTY_CODE = '01'
          AND I.ITEM_INACTIVE = 0
          AND R.RCV_DATE BETWEEN ? AND ?
        GROUP BY R.RCV_DATE, RD.ITEM_ID
        HAVING SUM(RD.RCVD_QTY) > 0
    )
    SELECT
        CONVERT(VARCHAR(10), PD_DATE, 120) AS PROD_DATE,
        SUM(TOTAL_QTY * ISNULL(HPP_PER_UNIT, 0)) AS DEST_AMOUNT,
        'INTERNAL' AS PROD_SOURCE
    FROM DailyProd
    GROUP BY PD_DATE

    UNION ALL

    SELECT
        CONVERT(VARCHAR(10), TRAN_DATE, 120) AS PROD_DATE,
        SUM(VEND_QTY * ISNULL(VEND_RATE_IDR, 0)) AS DEST_AMOUNT,
        'VENDOR' AS PROD_SOURCE
    FROM VendorProduction
    GROUP BY TRAN_DATE;
    ";
    
    $conn_opts = ["Database"=>$dbName, "Uid"=>$uid, "PWD"=>$pwd, "CharacterSet"=>"UTF-8", "LoginTimeout"=>10, "Encrypt"=>false, "ConnectionPooling"=>false];
    $servers_to_try = ($selected_plant === 'all') ? ['p1', 'p2'] : [$selected_plant];
    
    $raw_yearly = []; $raw_monthly = []; $raw_hpp_yearly = []; $raw_hpp_monthly = [];
    $min_year = 9999; $max_year = 0; $conn_errors = [];

    foreach ($servers_to_try as $key) {
        $ip = $servers_config[$key]['ip']; $name = $servers_config[$key]['label'];
        $conn = @sqlsrv_connect($ip, $conn_opts);
        if (!$conn) {
            $errs = @sqlsrv_errors(); $conn_errors[] = "<strong>{$name}</strong> — " . ($errs ? htmlspecialchars(mb_substr($errs[0]['message'], 0, 100)) : 'Timeout');
            continue;
        }

        // EKSEKUSI SALES
        $params_sales = array($start_date, $end_date);
        $stmt = @sqlsrv_query($conn, $sql_sales, $params_sales);
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $year = (int)$row['SALES_YEAR']; $month = (int)$row['SALES_MONTH'];
                $is_vendor = (int)$row['IS_VENDOR']; $sales = (float)$row['TOTAL_SALES'];
                if ($year < $min_year) $min_year = $year; if ($year > $max_year) $max_year = $year;
                if (!isset($raw_yearly[$year])) {
                    $raw_yearly[$year] = ['year' => $year, 'vendor' => 0, 'internal' => 0, 'total' => 0];
                    $raw_monthly[$year] = ['vendor' => array_fill(0, 12, 0), 'internal' => array_fill(0, 12, 0), 'total' => array_fill(0, 12, 0)];
                }
                if ($is_vendor === 1) { $raw_yearly[$year]['vendor'] += $sales; $raw_monthly[$year]['vendor'][$month - 1] += $sales; }
                else { $raw_yearly[$year]['internal'] += $sales; $raw_monthly[$year]['internal'][$month - 1] += $sales; }
                $raw_yearly[$year]['total'] += $sales; $raw_monthly[$year]['total'][$month - 1] += $sales;
            }
            sqlsrv_free_stmt($stmt);
        } else { 
            $errs = sqlsrv_errors();
            $conn_errors[] = "<strong>{$name} (Sales)</strong> — Gagal: " . ($errs ? htmlspecialchars($errs[0]['message']) : 'Unknown Error'); 
        }

        // EKSEKUSI HPP (4 parameter: produksi internal start/end, receive vendor start/end)
        $params_hpp = array($start_date, $end_date, $start_date, $end_date); 
        $stmt_hpp = @sqlsrv_query($conn, $sql_hpp_safe, $params_hpp);
        if ($stmt_hpp) {
            while ($row_hpp = sqlsrv_fetch_array($stmt_hpp, SQLSRV_FETCH_ASSOC)) {
                $hTimestamp = strtotime($row_hpp['PROD_DATE']); if (!$hTimestamp) continue;
                $hYear = (int)date('Y', $hTimestamp); $hMonth = (int)date('m', $hTimestamp);
                $is_vendor = (trim($row_hpp['PROD_SOURCE']) === 'VENDOR') ? 1 : 0; $amount = (float)$row_hpp['DEST_AMOUNT'];
                
                if ($hYear < $min_year) $min_year = $hYear; if ($hYear > $max_year) $max_year = $hYear;
                if (!isset($raw_hpp_yearly[$hYear])) {
                    $raw_hpp_yearly[$hYear] = ['year' => $hYear, 'vendor' => 0, 'internal' => 0, 'total' => 0];
                    $raw_hpp_monthly[$hYear] = ['vendor' => array_fill(0, 12, 0), 'internal' => array_fill(0, 12, 0), 'total' => array_fill(0, 12, 0)];
                }
                if ($is_vendor === 1) { $raw_hpp_yearly[$hYear]['vendor'] += $amount; $raw_hpp_monthly[$hYear]['vendor'][$hMonth - 1] += $amount; }
                else { $raw_hpp_yearly[$hYear]['internal'] += $amount; $raw_hpp_monthly[$hYear]['internal'][$hMonth - 1] += $amount; }
                $raw_hpp_yearly[$hYear]['total'] += $amount; $raw_hpp_monthly[$hYear]['total'][$hMonth - 1] += $amount;
            }
            sqlsrv_free_stmt($stmt_hpp);
        } else { 
            $errs = sqlsrv_errors(); 
            $conn_errors[] = "<strong>{$name} (HPP)</strong> — Gagal eksekusi: " . ($errs ? htmlspecialchars($errs[0]['message']) : 'Unknown Error CTE'); 
        }
        
        sqlsrv_close($conn);
    }

    $yearly_data = []; $monthly_data = []; $hpp_yearly_data = []; $hpp_monthly_data = [];
    if ($min_year <= $max_year) {
        for ($y = $min_year; $y <= $max_year; $y++) {
            $yearly_data[] = isset($raw_yearly[$y]) ? $raw_yearly[$y] : ['year' => $y, 'vendor' => 0, 'internal' => 0, 'total' => 0];
            $monthly_data[$y] = isset($raw_monthly[$y]) ? $raw_monthly[$y] : ['vendor' => array_fill(0, 12, 0), 'internal' => array_fill(0, 12, 0), 'total' => array_fill(0, 12, 0)];
            $hpp_yearly_data[] = isset($raw_hpp_yearly[$y]) ? $raw_hpp_yearly[$y] : ['year' => $y, 'vendor' => 0, 'internal' => 0, 'total' => 0];
            $hpp_monthly_data[$y] = isset($raw_hpp_monthly[$y]) ? $raw_hpp_monthly[$y] : ['vendor' => array_fill(0, 12, 0), 'internal' => array_fill(0, 12, 0), 'total' => array_fill(0, 12, 0)];
        }
    }
    echo json_encode(['yearly' => $yearly_data, 'monthly' => $monthly_data, 'hpp_yearly' => $hpp_yearly_data, 'hpp_monthly' => $hpp_monthly_data, 'errors' => $conn_errors]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grafik Sales vs HPP</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root{--bg:#0D1117;--card:#161B22;--border:#30363D;--text:#E6EDF3;--muted:#8B949E;--sales:#4C8BF5;--sales-dim:rgba(76,139,245,0.12);--vendor:#FF6B35;--internal:#00D4AA;--accent:#FFD93D;--accent-dim:rgba(255,217,61,0.10);--danger:#F85149;--hpp:#F85149;--hpp-dim:rgba(248,81,73,0.15);--r:12px;--rs:6px;}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;padding:24px 20px 60px;overflow-x:hidden;}
        .container{max-width:1200px;margin:0 auto;position:relative;z-index:1;opacity:0;transform:translateY(20px);animation:fadeUp .6s ease forwards;}
        @keyframes fadeUp{to{opacity:1;transform:translateY(0);}}
        .header{margin-bottom:16px;}.header-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:15px;}.header-title-group{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}.header h1{font-family:'Space Grotesk',sans-serif;font-size:22px;font-weight:700;}.header h1 i{color:var(--accent);margin-right:6px;font-size:18px;}.header-sub{color:var(--muted);font-size:13px;}.header-sub b{color:var(--text);font-weight:600;}
        .btn-pdf{background:var(--accent);color:#0D1117;border:none;padding:8px 18px;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 4px 12px rgba(255,217,61,0.2);transition:all 0.2s;}.btn-pdf:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(255,217,61,0.3);}.btn-pdf:disabled{opacity:.6;cursor:wait;transform:none;}
        #errBox{background:rgba(248,81,73,0.08);border:1px solid rgba(248,81,73,0.25);border-radius:10px;padding:14px 18px;margin-bottom:12px; display:none;}#errBox .err-title{color:var(--danger);font-size:12px;font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:6px;text-transform:uppercase;letter-spacing:.5px;}#errBox .err-item{color:rgba(248,81,73,0.85);font-size:12px;line-height:1.7;padding-left:18px;position:relative;word-wrap: break-word;}#errBox .err-item:not(:last-child){margin-bottom:2px;}#errBox .err-item::before{content:'';position:absolute;left:4px;top:9px;width:5px;height:5px;border-radius:50%;background:rgba(248,81,73,0.5);}
        .filter-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:12px 18px;margin-bottom:12px;}.filter-bar label{font-size:12px;color:var(--muted);font-weight:600;white-space:nowrap;}.filter-bar select{background:var(--bg);border:1px solid var(--border);color:var(--text);padding:8px 32px 8px 12px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;outline:none;cursor:pointer;appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238B949E' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;}.filter-bar select:focus{border-color:var(--accent);}.sep{width:1px;height:24px;background:var(--border);margin:0 2px;}.range-hint{font-size:11px;color:var(--muted);display:flex;align-items:center;gap:5px;margin-left:auto;}.range-hint .count-badge{background:var(--accent-dim);color:var(--accent);font-weight:700;padding:2px 8px;border-radius:10px;font-size:11px;font-family:'Space Grotesk',sans-serif;}
        .preset-row{display:flex;align-items:center;gap:6px;margin-bottom:16px;flex-wrap:wrap;}.preset-row span{font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-right:4px;}.preset-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border-radius:20px;font-family:'DM Sans',sans-serif;font-size:11px;font-weight:600;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;transition:all .2s;white-space:nowrap;}.preset-btn:hover{border-color:var(--muted);color:var(--text);transform:translateY(-1px);}.preset-btn.active{border-color:var(--accent);color:var(--accent);background:var(--accent-dim);}
        .chart-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:28px 32px 24px;position:relative;overflow:hidden;margin-bottom:16px;}.chart-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--sales),var(--hpp));}.chart-title{font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:24px;display:flex;align-items:center;gap:8px;}.chart-title i{color:var(--accent);font-size:14px;}.chart-title .click-hint{font-size:11px;font-weight:400;color:var(--muted);text-transform:none;letter-spacing:0;margin-left:auto;display:flex;align-items:center;gap:5px;opacity:.7;}
        .chart-area{position:relative;width:100%;height:380px;}
        .loading-overlay{position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(13,17,23,0.9);display:none;flex-direction:column;align-items:center;justify-content:center;z-index:10;color:var(--muted);font-size:14px;gap:12px;border-radius:8px;}.loading-overlay.show{display:flex;}.spinner{width:40px;height:40px;border:4px solid var(--border);border-top:4px solid var(--accent);border-radius:50%;animation:spin 1s linear infinite;}@keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}
        .empty-state{display:none;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;text-align:center;}.empty-state.visible{display:flex;}.empty-state .empty-icon{width:80px;height:80px;border-radius:50%;background:rgba(248,81,73,0.08);display:flex;align-items:center;justify-content:center;font-size:32px;color:var(--danger);margin-bottom:20px;}.empty-state h3{font-family:'Space Grotesk',sans-serif;font-size:18px;font-weight:700;color:var(--text);margin-bottom:8px;}.empty-state p{font-size:13px;color:var(--muted);max-width:440px;line-height:1.7;}
        .custom-legend{display:flex;align-items:center;justify-content:center;gap:32px;margin-top:20px;padding-top:18px;border-top:1px solid var(--border);flex-wrap:wrap;}.legend-item{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);font-weight:500;cursor:default;}.legend-dot{width:12px;height:12px;border-radius:3px;}.legend-dot.sales{background:linear-gradient(180deg,#6BA3F7,#4C8BF5);}.legend-dot.hpp{background:var(--hpp);border-radius:50%;}.legend-dot.yoy{width:auto;height:auto;background:transparent;color:var(--internal);font-size:13px;font-weight:800;line-height:1;}
        .monthly-panel{max-height:0;overflow:hidden;opacity:0;transform:translateY(-8px);transition:max-height .5s cubic-bezier(.4,0,.2,1),opacity .4s ease .05s,transform .4s ease .05s,margin-top .4s ease;margin-top:0;}.monthly-panel.open{max-height:700px;opacity:1;transform:translateY(0);margin-bottom:16px;}.monthly-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:24px 28px 20px;position:relative;overflow:hidden;}.monthly-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--sales),var(--hpp));}.monthly-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;}.monthly-header-left{display:flex;align-items:center;gap:10px;}.monthly-header-left .year-badge{background:var(--accent-dim);color:var(--accent);font-family:'Space Grotesk',sans-serif;font-size:15px;font-weight:700;padding:5px 14px;border-radius:8px;}.monthly-header-left h3{font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:1px;}.close-btn{width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--muted);font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;}.close-btn:hover{background:rgba(248,81,73,.1);border-color:var(--danger);color:var(--danger);}.monthly-chart-area{position:relative;width:100%;height:250px;}
        .monthly-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;margin-top:16px;padding-top:14px;border-top:1px solid var(--border);}.ms-item{display:flex;flex-direction:column;gap:2px;}.ms-item .ms-label{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;font-weight:600;}.ms-item .ms-val{font-family:'Space Grotesk',sans-serif;font-size:15px;font-weight:700;}.ms-item .ms-val.ms{color:var(--sales);}.ms-item .ms-val.hpp{color:var(--hpp);}.ms-item .ms-val.gp{color:var(--internal);}
        .summary-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:16px;}.summary-card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:16px 18px;position:relative;overflow:hidden;opacity:0;transform:translateY(12px);animation:fadeUp .4s ease forwards;}.summary-card:nth-child(1){animation-delay:.1s}.summary-card:nth-child(2){animation-delay:.15s}.summary-card:nth-child(3){animation-delay:.2s}.summary-card:nth-child(4){animation-delay:.25s}.summary-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;}.summary-card.ss::before{background:var(--sales);}.summary-card.sh::before{background:var(--hpp);}.summary-card.sg::before{background:var(--internal);}.summary-card.sm::before{background:var(--accent);}
        .sc-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;margin-bottom:8px;}.ss .sc-icon{background:var(--sales-dim);color:var(--sales);}.sh .sc-icon{background:var(--hpp-dim);color:var(--hpp);}.sg .sc-icon{background:rgba(0,212,170,0.12);color:var(--internal);}.sm .sc-icon{background:var(--accent-dim);color:var(--accent);}
        .sc-label{font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:600;margin-bottom:4px;}.sc-num{font-family:'Space Grotesk',sans-serif;font-size:18px;font-weight:700;line-height:1;}.sc-sub{font-size:11px;color:var(--muted);margin-top:5px;}
        .table-card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:24px 20px;position:relative;overflow:visible;page-break-inside:avoid;}.table-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--sales),var(--hpp));}.table-wrapper{overflow-x:auto;margin-top:12px;}.data-table{width:100%;border-collapse:collapse;font-size:12px;text-align:left;}.data-table th{font-family:'Space Grotesk',sans-serif;background:rgba(48,54,61,0.5);color:var(--text);padding:8px 10px;font-weight:600;border-bottom:2px solid var(--border);white-space:nowrap;font-size:11px;}.data-table td{padding:7px 10px;border-bottom:1px solid rgba(48,54,61,0.6);color:var(--text);font-family:'Space Grotesk',sans-serif;font-size:11px;white-space:nowrap;}.data-table tbody tr:hover{background:rgba(255,255,255,0.02);}.data-table tr.total-row{background:rgba(255,217,61,0.05)!important;font-weight:bold;}.data-table tr.total-row td{border-top:2px solid var(--border);border-bottom:2px solid var(--border);color:var(--accent);}.t-total{color:var(--sales)!important;font-weight:700;}.t-hpp{color:var(--hpp)!important;}.t-gp{color:var(--internal)!important;}
        .table-tabs{display:flex;gap:8px;border-bottom:1px solid var(--border);padding-bottom:10px;}.tab-btn{background:transparent;border:1px solid var(--border);color:var(--muted);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;font-family:'DM Sans',sans-serif;transition:all 0.2s;}.tab-btn:hover{border-color:var(--muted);color:var(--text);}.tab-btn.active{background:var(--accent-dim);color:var(--accent);border-color:var(--accent);}.table-title-container{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:10px;}
        .toast{position:fixed;bottom:24px;right:24px;background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:12px 20px;font-size:13px;color:var(--text);z-index:999;opacity:0;transform:translateY(12px);transition:all .3s;display:flex;align-items:center;gap:8px;}.toast.show{opacity:1;transform:translateY(0);}
        .pdf-export-mode,.pdf-export-mode::before,.pdf-export-mode::after{background:#FFF!important;animation:none!important;}.pdf-export-mode .container{background:#FFF!important;padding:8px 12px!important;max-width:1540px!important;width:1540px!important;transform:none!important;opacity:1!important;animation:none!important;margin:0!important;}.pdf-export-mode .header h1{color:#111827!important;font-size:16px!important;}.pdf-export-mode .header h1 i{color:#D97706!important;}.pdf-export-mode .header-sub{color:#6B7280!important;font-size:10px!important;}.pdf-export-mode .chart-card,.pdf-export-mode .monthly-card,.pdf-export-mode .table-card{background:#FFF!important;border-color:#D1D5DB!important;box-shadow:0 1px 3px rgba(0,0,0,0.06)!important;padding:10px 12px!important;margin-bottom:6px!important;overflow:visible!important;width:100%!important;}.pdf-export-mode .chart-card::before,.pdf-export-mode .monthly-card::before,.pdf-export-mode .table-card::before{background:linear-gradient(90deg,#2563EB,#DC2626)!important;}.pdf-export-mode .chart-area{height:260px!important;}.pdf-export-mode .monthly-chart-area{height:180px!important;}.pdf-export-mode .summary-row{gap:5px!important;margin-bottom:6px!important;}.pdf-export-mode .summary-card{background:#F9FAFB!important;border-color:#E5E7EB!important;padding:7px 9px!important;opacity:1!important;transform:none!important;animation:none!important;}.pdf-export-mode .sc-num{color:#111827!important;font-size:13px!important;}.pdf-export-mode .data-table{width:100%!important;table-layout:fixed!important;font-size:9px!important;}.pdf-export-mode .data-table th{background:#F3F4F6!important;color:#111827!important;padding:4px 5px!important;font-size:8px!important;}.pdf-export-mode .data-table td{color:#374151!important;padding:3px 5px!important;font-size:9px!important;}.pdf-export-mode .t-total{color:#2563EB!important;}.pdf-export-mode .t-hpp{color:#DC2626!important;}.pdf-export-mode .t-gp{color:#059669!important;}.pdf-export-mode .filter-bar,.pdf-export-mode .preset-row,.pdf-export-mode .click-hint,.pdf-export-mode #pdfBtn,.pdf-export-mode .close-btn,.pdf-export-mode .table-tabs,.pdf-export-mode .toast,.pdf-export-mode .loading-overlay{display:none!important;}
    </style>
</head>
<body>
<div class="container" id="mainContainer">
    <div class="header">
        <div class="header-top">
            <div class="header-title-group">
                <h1><i class="fas fa-chart-column"></i> Sales Amount vs Material Use</h1>
            </div>
            <button id="pdfBtn" class="btn-pdf" style="display:none;" onclick="generatePDF()"><i class="fas fa-file-pdf"></i> Download PDF A3</button>
        </div>
        <div class="header-sub">Nilai Sales & HPP (IDR) &nbsp;|&nbsp; Harga HPP mengikuti List Harga Material Konsumsi (PO terakhir / Receive Vendor terakhir + CURR_VRATE) &nbsp;|&nbsp; Periode: <b id="periodLabel">Pilih Rentang Waktu</b></div>
    </div>

    <div id="errBox"></div>

    <div class="filter-bar">
        <label for="plantFilter"><i class="fas fa-building" style="color:var(--accent);"></i> Plant</label>
        <select id="plantFilter">
            <option value="all">Gabungan (P1 & P2)</option>
            <option value="p1">Plant 1</option>
            <option value="p2">Plant 2</option>
        </select>
        <div class="sep"></div><label for="startYear">Dari</label><select id="startYear" disabled><option>...</option></select>
        <div class="sep"></div><label for="endYear">Sampai</label><select id="endYear" disabled><option>...</option></select>
        <div class="range-hint"><i class="fas fa-calendar-days"></i><span id="rangeText">...</span><span class="count-badge" id="rangeCount">...</span></div>
    </div>
    <div class="preset-row" id="presetRow" style="opacity:0.5; pointer-events:none;">
        <span><i class="fas fa-bolt" style="color:var(--accent);margin-right:2px;"></i> Cepat:</span>
        <button class="preset-btn" onclick="applyPreset(3)">3 Tahun</button>
        <button class="preset-btn active" onclick="applyPreset(5)">5 Tahun</button>
        <button class="preset-btn" onclick="applyPreset(7)">7 Tahun</button>
    </div>

    <div class="chart-card">
        <div class="chart-title"><i class="fas fa-scale-balanced"></i> Perbandingan Sales vs HPP Tahunan<span class="click-hint"><i class="fas fa-hand-pointer"></i> Klik bar → detail bulanan</span></div>
        <div class="empty-state visible" id="emptyState"><div class="empty-icon"><i class="fas fa-filter"></i></div><h3>Pilih Filter Terlebih Dahulu</h3><p>Silakan pilih Plant dan rentang tahun untuk memulai analisis data.</p></div>
        <div class="chart-area" id="chartArea" style="display:none;">
            <div class="loading-overlay" id="loadingOverlay"><div class="spinner"></div><div> Mengambil data...</div></div>
            <canvas id="annualBarChart"></canvas>
        </div>
        <div class="custom-legend" id="legendBar" style="display:none;">
            <div class="legend-item"><div class="legend-dot sales"></div>Total Sales (IDR)</div>
            <div class="legend-item"><div class="legend-dot hpp"></div>Total HPP (IDR)</div>
            <div class="legend-item"><div class="legend-dot yoy">▲▼</div>% Sales vs tahun sebelumnya</div>
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

    <div class="table-card" id="tableDataContainer" style="display:none;">
        <div class="table-title-container">
            <div class="chart-title" style="margin-bottom:0;"><i class="fas fa-table"></i> Tabulasi Sales vs HPP</div>
            <div class="table-tabs">
                <button class="tab-btn active" id="tabTahunanBtn" onclick="switchTableTab('tahunan')">Tahunan</button>
                <button class="tab-btn" id="tabBulananBtn" onclick="switchTableTab('bulanan')">Bulanan (<span id="tabBulananYear">Pilih Tahun</span>)</button>
            </div>
        </div>
        <div class="table-wrapper" id="wrapperTahunan">
            <table class="data-table" id="tableTahunan"><thead><tr>
                <th>Tahun</th><th style="text-align:right;">Total Sales (IDR)</th><th style="text-align:right;">Total HPP (IDR)</th><th style="text-align:right;">Gross Profit (IDR)</th><th style="text-align:right;">Margin</th>
            </tr></thead><tbody id="tbodyTahunan"></tbody></table>
        </div>
        <div class="table-wrapper" id="wrapperBulanan" style="display:none;">
            <table class="data-table" id="tableBulanan"><thead><tr>
                <th>Bulan (<span id="lblTableBulananYear">2024</span>)</th><th style="text-align:right;">Total Sales (IDR)</th><th style="text-align:right;">Total HPP (IDR)</th><th style="text-align:right;">Gross Profit (IDR)</th><th style="text-align:right;">Margin</th>
            </tr></thead><tbody id="tbodyBulanan"></tbody></table>
        </div>
    </div>
</div>
<div class="toast" id="toast"><i class="fas fa-check-circle" style="color:var(--sales);"></i><span id="toastMsg"></span></div>

<script>
var ALL_DATA=[], MONTHLY_DATA={}, HPP_DATA=[], HPP_MONTHLY={};
var MIN_Y = 2020, MAX_Y = new Date().getFullYear();
var HAS_REAL_DATA = false;
var currentPlant = 'all';
var chInst=null, chMInst=null, curEnd=MAX_Y, curStart=MAX_Y-4, selYear=null;
var MAX_SP=10, isLoading=false;
var ML=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

function fS(n){if(n>=1e12)return(n/1e12).toFixed(1)+'T';if(n>=1e9)return(n/1e9).toFixed(1)+'M';if(n>=1e6)return(n/1e6).toFixed(1)+'Jt';if(n>=1e3)return(n/1e3).toFixed(0)+'Rb';return n.toString();}
function fF(n){return'Rp '+n.toLocaleString('id-ID',{maximumFractionDigits:0});}
function getHppMap(arr){var m={};arr.forEach(function(h){m[h.year]=h;});return m;}
function showToast(m){var t=document.getElementById('toast');document.getElementById('toastMsg').textContent=m;t.classList.add('show');setTimeout(function(){t.classList.remove('show');},2500);}

window.addEventListener('DOMContentLoaded', function() {
    document.getElementById('plantFilter').addEventListener('change', function(){ currentPlant = this.value; loadData(); });
    document.getElementById('closeMonthly').addEventListener('click', closeMP);
    
    fetch('?action=get_years&plant=all')
        .then(r => r.json())
        .then(data => {
            MIN_Y = data.min_year; MAX_Y = data.max_year;
            initSel();
            document.getElementById('presetRow').style.opacity = '1'; document.getElementById('presetRow').style.pointerEvents = 'auto';
            applyPreset(5);
        })
        .catch(() => {
            MIN_Y = MAX_Y - 5; initSel();
            document.getElementById('presetRow').style.opacity = '1'; document.getElementById('presetRow').style.pointerEvents = 'auto';
            applyPreset(5);
        });
});

function initSel(){
    var sS=document.getElementById('startYear'),sE=document.getElementById('endYear');
    sS.innerHTML=''; sE.innerHTML=''; sS.disabled=false; sE.disabled=false;
    for(var y=MIN_Y;y<=MAX_Y;y++){ sS.appendChild(new Option(y,y)); sE.appendChild(new Option(y,y)); }
    sS.value=curStart; sE.value=curEnd;
    sS.addEventListener('change', onRC); sE.addEventListener('change', onRC);
}

function onRC(){
    var s=parseInt(document.getElementById('startYear').value),e=parseInt(document.getElementById('endYear').value);
    if(e<s){e=s;document.getElementById('endYear').value=e;}
    if(e-s+1>MAX_SP){e=s+MAX_SP-1;document.getElementById('endYear').value=e;showToast('Maksimal '+MAX_SP+' tahun');}
    curStart=s;curEnd=e;clrAP();closeMP();loadData();
}
window.applyPreset=function(t){clrAP();var sp=parseInt(t);var e=MAX_Y,s=e-sp+1;if(s<MIN_Y)s=MIN_Y;curStart=s;curEnd=e;document.getElementById('startYear').value=s;document.getElementById('endYear').value=e;document.querySelectorAll('.preset-btn').forEach(function(b){if(b.getAttribute('onclick').indexOf(t)!==-1)b.classList.add('active');});closeMP();loadData();};
function clrAP(){document.querySelectorAll('.preset-btn').forEach(function(b){b.classList.remove('active');});}

async function loadData(){
    if(isLoading) return;
    isLoading = true;
    document.getElementById('loadingOverlay').classList.add('show');
    document.getElementById('errBox').style.display = 'none';
    document.getElementById('chartArea').style.display = '';
    document.getElementById('emptyState').classList.remove('visible');
    document.getElementById('legendBar').style.display = 'none';

    try {
        let url = `?action=get_data&plant=${currentPlant}&start_year=${curStart}&end_year=${curEnd}`;
        let res = await fetch(url);
        let data = await res.json();
        
        if(data.errors && data.errors.length > 0) {
            document.getElementById('errBox').innerHTML = '<div class="err-title"><i class="fas fa-triangle-exclamation"></i> Peringatan SQL Server</div>' + data.errors.map(e=>'<div class="err-item">'+e+'</div>').join('');
            document.getElementById('errBox').style.display = 'block';
        }

        ALL_DATA = data.yearly || []; MONTHLY_DATA = data.monthly || {};
        HPP_DATA = data.hpp_yearly || []; HPP_MONTHLY = data.hpp_monthly || {};
        HAS_REAL_DATA = ALL_DATA.length > 0;
        
        if(HAS_REAL_DATA) {
            updAll();
            document.getElementById('tableDataContainer').style.display = '';
            document.getElementById('pdfBtn').style.display = '';
        } else {
            document.getElementById('emptyState').innerHTML = '<div class="empty-icon"><i class="fas fa-database"></i></div><h3>Tidak Ada Data</h3><p>Tidak ditemukan transaksi sales atau HPP pada rentang tahun yang dipilih.</p>';
            document.getElementById('emptyState').classList.add('visible');
            document.getElementById('chartArea').style.display = 'none';
            document.getElementById('tableDataContainer').style.display = 'none';
            document.getElementById('pdfBtn').style.display = 'none';
        }
    } catch(e) {
        document.getElementById('emptyState').innerHTML = '<div class="empty-icon"><i class="fas fa-wifi"></i></div><h3>Kesalahan Jaringan</h3><p>Gagal mengambil data dari server.</p>';
        document.getElementById('emptyState').classList.add('visible');
        document.getElementById('chartArea').style.display = 'none';
        document.getElementById('errBox').innerHTML = '<div class="err-title"><i class="fas fa-triangle-exclamation"></i> Kesalahan Sistem</div><div class="err-item">Gagal terhubung ke endpoint API.</div>';
        document.getElementById('errBox').style.display = 'block';
    } finally {
        isLoading = false;
        document.getElementById('loadingOverlay').classList.remove('show');
    }
}

function updAll() {
    var f = ALL_DATA.filter(function(d) { return d.year >= curStart && d.year <= curEnd; });
    document.getElementById('rangeText').textContent = f.length + ' tahun';
    document.getElementById('rangeCount').textContent = f.length;
    document.getElementById('periodLabel').textContent = curStart + ' s/d ' + curEnd;
    updChart(f); updSum(f); genTTable(f);
}

// ==========================================
// PERSENTASE KENAIKAN / PENURUNAN YoY
// Dibandingkan dengan tahun sebelumnya.
// Label ditampilkan di atas bar Total Sales.
// ==========================================
function calcYoYPct(currentValue, previousValue) {
    currentValue = Number(currentValue) || 0;
    previousValue = Number(previousValue) || 0;

    // Tidak ada basis pembanding yang valid.
    if (previousValue === 0) return null;

    return ((currentValue - previousValue) / Math.abs(previousValue)) * 100;
}

function formatYoYPct(pct) {
    if (pct === null || !isFinite(pct)) return '-';
    var absPct = Math.abs(pct);
    return (pct > 0 ? '▲ ' : pct < 0 ? '▼ ' : '• ') + absPct.toFixed(1) + '%';
}

var yoyChangePlugin = {
    id: 'yoyChangePlugin',
    afterDatasetsDraw: function(chart, args, pluginOptions) {
        var salesDataset = chart.data.datasets && chart.data.datasets[0];
        var salesMeta = chart.getDatasetMeta(0);

        if (!salesDataset || !salesMeta || !salesMeta.data || salesMeta.hidden) return;

        var values = salesDataset.data || [];
        var ctx = chart.ctx;
        var area = chart.chartArea;
        var isPdf = document.body.classList.contains('pdf-export-mode');

        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.font = '700 ' + (isPdf ? '9px' : '10px') + ' "Space Grotesk", sans-serif';

        for (var i = 1; i < values.length; i++) {
            var pct = calcYoYPct(values[i], values[i - 1]);
            if (pct === null || !isFinite(pct)) continue;

            var el = salesMeta.data[i];
            if (!el) continue;

            var label = formatYoYPct(pct);
            var isUp = pct > 0;
            var isDown = pct < 0;
            var fg = isUp ? '#00D4AA' : isDown ? '#F85149' : '#FFD93D';
            var bg = isPdf
                ? (isUp ? 'rgba(5,150,105,0.12)' : isDown ? 'rgba(220,38,38,0.10)' : 'rgba(217,119,6,0.12)')
                : (isUp ? 'rgba(0,212,170,0.14)' : isDown ? 'rgba(248,81,73,0.14)' : 'rgba(255,217,61,0.14)');

            var textWidth = ctx.measureText(label).width;
            var padX = isPdf ? 4 : 6;
            var boxW = Math.max(isPdf ? 42 : 48, textWidth + (padX * 2));
            var boxH = isPdf ? 16 : 18;
            var x = el.x;
            var y = el.y - (isPdf ? 12 : 14);

            // Pastikan label tidak keluar dari area chart.
            y = Math.max(area.top + (boxH / 2) + 2, y);
            x = Math.max(area.left + (boxW / 2) + 2, Math.min(area.right - (boxW / 2) - 2, x));

            var left = x - boxW / 2;
            var top = y - boxH / 2;
            var radius = boxH / 2;

            ctx.beginPath();
            ctx.moveTo(left + radius, top);
            ctx.lineTo(left + boxW - radius, top);
            ctx.quadraticCurveTo(left + boxW, top, left + boxW, top + radius);
            ctx.lineTo(left + boxW, top + boxH - radius);
            ctx.quadraticCurveTo(left + boxW, top + boxH, left + boxW - radius, top + boxH);
            ctx.lineTo(left + radius, top + boxH);
            ctx.quadraticCurveTo(left, top + boxH, left, top + boxH - radius);
            ctx.lineTo(left, top + radius);
            ctx.quadraticCurveTo(left, top, left + radius, top);
            ctx.closePath();
            ctx.fillStyle = bg;
            ctx.fill();

            ctx.strokeStyle = fg;
            ctx.lineWidth = 1;
            ctx.stroke();

            ctx.fillStyle = fg;
            ctx.fillText(label, x, y + 0.5);
        }

        ctx.restore();
    }
};

function updChart(data) {
    var ctx = document.getElementById('annualBarChart').getContext('2d');
    var sG = ctx.createLinearGradient(0, 0, 0, 400); sG.addColorStop(0, '#6BA3F7'); sG.addColorStop(1, '#4C8BF5');
    var lb = data.map(function(d) { return d.year.toString(); });
    var tS = data.map(function(d) { return d.total; });
    var hppM = getHppMap(HPP_DATA);
    var tH = data.map(function(d) { var h = hppM[d.year]; return h ? h.total : 0; });
    var bP = data.length <= 5 ? 0.45 : data.length <= 7 ? 0.35 : 0.25;
    var C = {sB:'#4C8BF5', hL:'#F85149', ttBg:'#1C2333', ttT:'#E6EDF3', ttBd:'#8B949E', ttBr:'#30363D', grd:'rgba(48,54,61,0.4)', xT:'#E6EDF3', yT:'#8B949E', xBd:'#30363D'};
    document.getElementById('legendBar').style.display = 'flex';
    if(chInst) {
        chInst.data.labels = lb;
        chInst.data.datasets[0].data = tS;
        chInst.data.datasets[0].barPercentage = bP;
        chInst.data.datasets[1].data = tH;
        chInst.update('active');
    } else {
        chInst = new Chart(ctx, {
            type: 'bar',
            data: { labels: lb, datasets: [
                {label: 'Total Sales', type: 'bar', data: tS, backgroundColor: sG, borderColor: C.sB, borderWidth: 1, borderRadius: {topLeft: 6, topRight: 6}, barPercentage: bP, categoryPercentage: 0.7, order: 2},
                {label: 'Total HPP', type: 'line', data: tH, borderColor: C.hL, backgroundColor: 'transparent', borderWidth: 2.5, pointBackgroundColor: C.hL, pointRadius: 4, pointHoverRadius: 6, tension: 0.3, fill: false, order: 1}
            ]},
            plugins: [yoyChangePlugin],
            options: {
                responsive: true, maintainAspectRatio: false, layout: {padding: {top: 42}}, interaction: {mode: 'index', intersect: false},
                onClick: function(evt, el) { if (el.length > 0) togMP(parseInt(chInst.data.labels[el[0].index])); },
                plugins: {
                    legend: {display: false},
                    tooltip: {
                        backgroundColor: C.ttBg, titleColor: C.ttT, bodyColor: C.ttBd, borderColor: C.ttBr, borderWidth: 1, cornerRadius: 8, padding: 14,
                        footerFont: {family: 'Space Grotesk', size: 12, weight: 'bold'}, footerColor: '#00D4AA', footerMarginTop: 8,
                        callbacks: {
                            title: function(i) { return 'Tahun ' + i[0].label; },
                            label: function(c) { return ' ' + c.dataset.label + ': ' + fF(c.raw); },
                            afterBody: function(items) {
                                if (!items || !items.length) return [];
                                var idx = items[0].dataIndex;
                                if (idx <= 0) return ['YoY: Tahun dasar (tanpa pembanding)'];

                                var salesPct = calcYoYPct(tS[idx], tS[idx - 1]);
                                var hppPct = calcYoYPct(tH[idx], tH[idx - 1]);
                                var lines = [];
                                lines.push('Sales YoY: ' + (salesPct === null ? '-' : formatYoYPct(salesPct)));
                                lines.push('HPP YoY: ' + (hppPct === null ? '-' : formatYoYPct(hppPct)));
                                return lines;
                            },
                            footer: function(items) {
                                var s = tS[items[0].dataIndex], h = tH[items[0].dataIndex];
                                var g = s - h, m = s > 0 ? ((g / s) * 100).toFixed(1) : 0;
                                return 'Gross Profit: ' + fF(g) + ' (' + m + '%)';
                            }
                        }
                    }
                },
                scales: {
                    x: {grid: {display: false}, ticks: {color: C.xT, font: {family: 'Space Grotesk', size: 13, weight: '600'}}, border: {color: C.xBd}},
                    y: {beginAtZero: true, grace: '12%', grid: {color: C.grd}, ticks: {color: C.yT, font: {family: 'Space Grotesk', size: 11}, callback: function(v) { return fS(v); }}, border: {display: false}}
                }
            }
        });
    }
}

function togMP(y){if(selYear===y)closeMP();else openMP(y);}
function openMP(y){selYear=y;document.getElementById('monthlyYearBadge').textContent=y;document.getElementById('tabBulananYear').textContent=y;document.getElementById('lblTableBulananYear').textContent=y;document.getElementById('monthlyPanel').classList.add('open');updMChart(y);updMSum(y);genBTable(y);swTab('bulanan');showToast('Detail bulanan tahun '+y);setTimeout(function(){document.getElementById('monthlyPanel').scrollIntoView({behavior:'smooth',block:'nearest'});},200);}
function closeMP(){selYear=null;document.getElementById('tabBulananYear').textContent='Pilih Tahun';document.getElementById('monthlyPanel').classList.remove('open');swTab('tahunan');}

function updMChart(year) {
    var md=MONTHLY_DATA[year], mdH=HPP_MONTHLY[year]; if(!md)return;
    var ctx=document.getElementById('monthlyBarChart').getContext('2d');
    var sG=ctx.createLinearGradient(0,0,0,280); sG.addColorStop(0,'#6BA3F7'); sG.addColorStop(1,'#4C8BF5');
    var C = {sB:'#4C8BF5', hL:'#F85149', ttBg:'#1C2333', ttT:'#E6EDF3', ttBd:'#8B949E', ttBr:'#30363D', grd:'rgba(48,54,61,0.4)', xT:'#E6EDF3', yT:'#8B949E', xBd:'#30363D'};
    var tS=md.total; var tH=mdH?mdH.total:Array(12).fill(0);
    if(chMInst){
        chMInst.data.datasets[0].data=tS;chMInst.data.datasets[1].data=tH;chMInst.update('active');
    }else{
        chMInst=new Chart(ctx,{
            type:'bar', data:{labels:ML,datasets:[
                {label:'Total Sales',type:'bar',data:tS,backgroundColor:sG,borderColor:C.sB,borderWidth:1,barPercentage:0.5,categoryPercentage:0.7,order:2},
                {label:'Total HPP',type:'line',data:tH,borderColor:C.hL,backgroundColor:'transparent',borderWidth:2,pointBackgroundColor:C.hL,pointRadius:3,tension:0.3,fill:false,order:1}
            ]},
            options:{
                responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},
                plugins:{
                    legend:{display:false},
                    tooltip:{
                        backgroundColor:C.ttBg,titleColor:C.ttT,bodyColor:C.ttBd,borderColor:C.ttBr,borderWidth:1,cornerRadius:6,padding:10,
                        footerFont: {family: 'Space Grotesk', size: 11, weight: 'bold'}, footerColor: '#00D4AA', footerMarginTop: 8,
                        callbacks:{
                            label: function(c) { return ' ' + c.dataset.label + ': ' + fF(c.raw); },
                            footer: function(items) {
                                var s = tS[items[0].dataIndex], h = tH[items[0].dataIndex];
                                var g = s - h, m = s > 0 ? ((g / s) * 100).toFixed(1) : 0;
                                return 'Gross Profit: ' + fF(g) + ' (' + m + '%)';
                            }
                        }
                    }
                },
                scales:{
                    x:{grid:{display:false},ticks:{color:C.xT,font:{size:10}},border:{color:C.xBd}},
                    y:{beginAtZero:true,position:'left',grid:{color:C.grd},ticks:{color:C.yT,font:{size:10},callback:function(v){return fS(v);}},border:{display:false}}
                }
            }
        });
    }
}

function updMSum(year) {
    var md=MONTHLY_DATA[year],mdH=HPP_MONTHLY[year];if(!md)return;
    var ts=0,th=0;for(var i=0;i<12;i++){ts+=md.total[i];th+=(mdH?mdH.total[i]:0);}
    var tg=ts-th;var tm=ts>0?((tg/ts)*100).toFixed(1):0;
    document.getElementById('monthlySummary').innerHTML=
    '<div class="ms-item"><div class="ms-label">Total Sales</div><div class="ms-val ms">'+fF(ts)+'</div></div>'+
    '<div class="ms-item"><div class="ms-label">Total HPP</div><div class="ms-val hpp">'+fF(th)+'</div></div>'+
    '<div class="ms-item"><div class="ms-label">Gross Profit</div><div class="ms-val gp">'+fF(tg)+'</div></div>'+
    '<div class="ms-item"><div class="ms-label">Margin</div><div class="ms-val" style="color:var(--accent)">'+tm+'%</div></div>';
}

function updSum(data) {
    var ts=0;var hppM=getHppMap(HPP_DATA);var th=0;
    data.forEach(function(d){ts+=d.total;var h=hppM[d.year];th+=(h?h.total:0);});
    var tg=ts-th;var tm=ts>0?((tg/ts)*100).toFixed(1):0;
    document.getElementById('summaryRow').innerHTML=
    '<div class="summary-card ss"><div class="sc-icon"><i class="fas fa-money-bill-trend-up"></i></div><div class="sc-label">Total Sales Amount</div><div class="sc-num">'+fF(ts)+'</div></div>'+
    '<div class="summary-card sh"><div class="sc-icon"><i class="fas fa-boxes-stacked"></i></div><div class="sc-label">Total HPP Amount</div><div class="sc-num" style="color:var(--hpp)">'+fF(th)+'</div></div>'+
    '<div class="summary-card sg"><div class="sc-icon"><i class="fas fa-coins"></i></div><div class="sc-label">Gross Profit</div><div class="sc-num" style="color:var(--internal)">'+fF(tg)+'</div></div>'+
    '<div class="summary-card sm"><div class="sc-icon"><i class="fas fa-percent"></i></div><div class="sc-label">Gross Margin</div><div class="sc-num" style="color:var(--accent)">'+tm+'%</div><div class="sc-sub">Berdasarkan selisih Sales & HPP</div></div>';
}

function genTTable(data) {
    var tb=document.getElementById('tbodyTahunan');var html='';var ts=0,th=0,tg=0;var hppM=getHppMap(HPP_DATA);
    data.forEach(function(d){
        var s=d.total;var h=hppM[d.year]?hppM[d.year].total:0;var g=s-h;var m=s>0?((g/s)*100).toFixed(1):'0.0';
        ts+=s;th+=h;tg+=g;
        html+='<tr><td>'+d.year+'</td><td style="text-align:right;" class="t-total">'+fF(s)+'</td><td style="text-align:right;" class="t-hpp">'+fF(h)+'</td><td style="text-align:right;" class="t-gp">'+fF(g)+'</td><td style="text-align:right;">'+m+'%</td></tr>';
    });
    var tm=ts>0?((tg/ts)*100).toFixed(1):'0.0';
    html+='<tr class="total-row"><td>TOTAL</td><td style="text-align:right;">'+fF(ts)+'</td><td style="text-align:right;">'+fF(th)+'</td><td style="text-align:right;">'+fF(tg)+'</td><td style="text-align:right;">'+tm+'%</td></tr>';
    tb.innerHTML=html;
}

function genBTable(year) {
    var md=MONTHLY_DATA[year],mdH=HPP_MONTHLY[year];if(!md)return;var tb=document.getElementById('tbodyBulanan');var html='';var ts=0,th=0,tg=0;
    for(var i=0;i<12;i++){
        var s=md.total[i];var h=mdH?mdH.total[i]:0;var g=s-h;var m=s>0?((g/s)*100).toFixed(1):'0.0';
        ts+=s;th+=h;tg+=g;
        html+='<tr><td>'+ML[i]+'</td><td style="text-align:right;" class="t-total">'+fF(s)+'</td><td style="text-align:right;" class="t-hpp">'+fF(h)+'</td><td style="text-align:right;" class="t-gp">'+fF(g)+'</td><td style="text-align:right;">'+m+'%</td></tr>';
    }
    var tm=ts>0?((tg/ts)*100).toFixed(1):'0.0';
    html+='<tr class="total-row"><td>TOTAL</td><td style="text-align:right;">'+fF(ts)+'</td><td style="text-align:right;">'+fF(th)+'</td><td style="text-align:right;">'+fF(tg)+'</td><td style="text-align:right;">'+tm+'%</td></tr>';
    tb.innerHTML=html;
}

window.switchTableTab=swTab;
function swTab(t){
    document.getElementById('tabTahunanBtn').classList.toggle('active',t==='tahunan');
    document.getElementById('tabBulananBtn').classList.toggle('active',t==='bulanan');
    document.getElementById('wrapperTahunan').style.display=t==='tahunan'?'':'none';
    document.getElementById('wrapperBulanan').style.display=t==='bulanan'?'':'none';
}

window.generatePDF=function(){
    var btn=document.getElementById('pdfBtn');btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Memformat...';
    window._pdfLM=true;if(chInst)updChart(ALL_DATA.filter(function(d){return d.year>=curStart&&d.year<=curEnd;}));if(chMInst&&selYear)updMChart(selYear);
    document.body.classList.add('pdf-export-mode');
    setTimeout(function(){
        var element=document.getElementById('mainContainer');var opt={margin:[6,6,6,6],filename:'Laporan_Sales_vs_HPP.pdf',image:{type:'jpeg',quality:0.98},html2canvas:{scale:2,useCORS:true,letterRendering:true},jsPDF:{unit:'mm',format:'a3',orientation:'landscape'}};
        html2pdf().set(opt).from(element).save().then(function(){
            document.body.classList.remove('pdf-export-mode');btn.disabled=false;btn.innerHTML='<i class="fas fa-file-pdf"></i> Download PDF A3';showToast('PDF berhasil diunduh');
        }).catch(function(){document.body.classList.remove('pdf-export-mode');btn.disabled=false;btn.innerHTML='<i class="fas fa-file-pdf"></i> Download PDF A3';});
    },800);
};
</script>
</body>
</html>
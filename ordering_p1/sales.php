<?php
// ============================================
// INCLUDE KONFIGURASI DATABASE (sesuaikan path)
// ============================================
require_once __DIR__ . "/../config/database_ordering.php";

if (!$conn) {
    die('<div style="padding:24px;color:#F85149;background:#0D1117;font-family:monospace;">Koneksi database tidak tersedia. Silakan login terlebih dahulu.</div>');
}

// ============================================
// PARAMETER FILTER
// ============================================
 $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
 $end_date   = isset($_GET['end_date'])   ? trim($_GET['end_date'])   : date('Y-m-d');
 $vendor_all = isset($_GET['vendor_all']) && $_GET['vendor_all'] == '1';
 $filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'all';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) $start_date = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date))   $end_date   = date('Y-m-d');

// ============================================
// EXPORT CSV
// ============================================
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    $sql = buildQuery($vendor_all);
    $params = buildParams($vendor_all, $start_date, $end_date);
    $stmt = sqlsrv_query($conn, $sql, $params);
    $rows = array();
    if ($stmt) {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $r;
        }
        sqlsrv_free_stmt($stmt);
    }
    sqlsrv_close($conn);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=sales_internal_vs_vendor_' . $start_date . '_to_' . $end_date . '.csv');
    $fp = fopen('php://output', 'w');
    fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($fp, array('No', 'Kode Item', 'Nama Item', 'Qty Sales', 'Nilai Sales (IDR)', 'Tipe Produksi'));
    $no = 1; $tq = 0; $tv = 0;
    foreach ($rows as $r) {
        $tipe = intval($r['IS_VENDOR']) === 1 ? 'Vendor' : 'Internal';
        $qs = floatval($r['QTY_SALES']);
        $ts = floatval($r['TOTAL_SALES']);
        fputcsv($fp, array($no++, $r['ITEM_CODE'], $r['ITEM_NAME'], $qs, number_format($ts, 2, '.', ''), $tipe));
        $tq += $qs; $tv += $ts;
    }
    fputcsv($fp, array('', 'TOTAL', '', $tq, number_format($tv, 2, '.', ''), ''));
    fclose($fp);
    exit;
}

// ============================================
// FUNGSI QUERY & PARAMS (SUDAH DENGAN KONVERSI IDR)
// ============================================
function buildQuery($vendor_all) {
    $vendor_where = $vendor_all
        ? "WHERE TRANS.TRTY_CODE = '12'"
        : "WHERE TRANS.TRAN_DATE BETWEEN ? AND ? AND TRANS.TRTY_CODE = '12'";
        
    return "
    WITH VendorItems AS (
        SELECT DISTINCT INV_TRAN.ITEM_ID
        FROM TRANS
        INNER JOIN INV_TRAN ON TRANS.TRAN_ID = INV_TRAN.TRAN_ID
        $vendor_where
    ),
    SalesData AS (
        SELECT
            ITEMS.ITEM_ID,
            ITEMS.ITEM_CODE,
            ITEMS.ITEM_NAME,
            DIPA_PAR.QTY AS QTY,
            DIPA_PAR.PART_PRICE AS PRICE,
            ISNULL(APV.CURR_CODE, 'IDR') AS CURR_CODE,
            ISNULL(RV.CURR_VRATE, 1) AS CURR_RATE
        FROM DI
        INNER JOIN DIPA_PAR ON DI.DI_ID = DIPA_PAR.DI_ID
        INNER JOIN PRICE ON DIPA_PAR.PRICE_ID = PRICE.PRICE_ID
        INNER JOIN ITEMS ON PRICE.PART_ID = ITEMS.ITEM_ID
        -- Join ke view harga dan rate untuk mendapatkan mata uang
        LEFT JOIN dbo.ACTIVE_PRICE_VIEW AS APV ON PRICE.PRICE_ID = APV.PRICE_ID
        LEFT JOIN dbo.TODAY_RATE_VIEW AS RV ON APV.CURR_CODE = RV.CURR_CODE
        WHERE DI.DI_DATE BETWEEN ? AND ?
    )
    SELECT
        s.ITEM_ID,
        s.ITEM_CODE,
        s.ITEM_NAME,
        SUM(s.QTY) AS QTY_SALES,
        -- LOGIC KONVERSI IDR: 
        -- Jika mata uang IDR/RP, kalikan 1. Jika USD/mata uang lain, kalikan rate (CURR_RATE).
        SUM(s.QTY * s.PRICE * CASE WHEN s.CURR_CODE IN ('IDR', 'RP') THEN 1 ELSE s.CURR_RATE END) AS TOTAL_SALES,
        CASE WHEN v.ITEM_ID IS NOT NULL THEN 1 ELSE 0 END AS IS_VENDOR
    FROM SalesData s
    LEFT JOIN VendorItems v ON s.ITEM_ID = v.ITEM_ID
    GROUP BY s.ITEM_ID, s.ITEM_CODE, s.ITEM_NAME, v.ITEM_ID
    ORDER BY TOTAL_SALES DESC
    ";
}

function buildParams($vendor_all, $sd, $ed) {
    return $vendor_all ? array($sd, $ed) : array($sd, $ed, $sd, $ed);
}

// ============================================
// EKSEKUSI QUERY
// ============================================
 $sql = buildQuery($vendor_all);
 $params = buildParams($vendor_all, $start_date, $end_date);
 $stmt = sqlsrv_query($conn, $sql, $params);

 $data = array();
 $total_qty = 0;
 $total_value = 0;
 $vendor_qty = 0;
 $vendor_value = 0;
 $internal_qty = 0;
 $internal_value = 0;
 $vendor_item_count = 0;
 $internal_item_count = 0;
 $query_error = false;
 $error_msg = '';

if ($stmt === false) {
    $query_error = true;
    $errs = sqlsrv_errors();
    if ($errs) {
        foreach ($errs as $e) {
            $error_msg .= htmlspecialchars($e['message']) . ' ';
        }
    }
} else {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $is_vendor = intval($row['IS_VENDOR']) === 1;
        $qs = floatval($row['QTY_SALES']);
        $ts = floatval($row['TOTAL_SALES']);
        $data[] = array(
            'item_id' => $row['ITEM_ID'],
            'item_code' => $row['ITEM_CODE'],
            'item_name' => $row['ITEM_NAME'],
            'qty_sales' => $qs,
            'total_sales' => $ts,
            'is_vendor' => $is_vendor
        );
        $total_qty += $qs;
        $total_value += $ts;
        if ($is_vendor) {
            $vendor_qty += $qs;
            $vendor_value += $ts;
            $vendor_item_count++;
        } else {
            $internal_qty += $qs;
            $internal_value += $ts;
            $internal_item_count++;
        }
    }
    sqlsrv_free_stmt($stmt);
}
sqlsrv_close($conn);

// Persentase
 $pct_vendor_qty   = $total_qty > 0 ? round(($vendor_qty / $total_qty) * 100, 1) : 0;
 $pct_internal_qty = $total_qty > 0 ? round(($internal_qty / $total_qty) * 100, 1) : 0;
 $pct_vendor_val   = $total_value > 0 ? round(($vendor_value / $total_value) * 100, 1) : 0;
 $pct_internal_val = $total_value > 0 ? round(($internal_value / $total_value) * 100, 1) : 0;

// Filter data tampilan
 $display_data = $data;
if ($filter_type === 'vendor') {
    $display_data = array_filter($data, function($d) { return $d['is_vendor']; });
} elseif ($filter_type === 'internal') {
    $display_data = array_filter($data, function($d) { return !$d['is_vendor']; });
}
 $display_data = array_values($display_data);
 $display_count = count($display_data);

// Chart data
 $top15 = array_slice($display_data, 0, 15);
 $chart_labels = json_encode(array_map(function($i) { return $i['item_code']; }, $top15), JSON_UNESCAPED_UNICODE);
 $chart_values = json_encode(array_map(function($i) { return $i['total_sales']; }, $top15));
 $chart_colors = json_encode(array_map(function($i) { return $i['is_vendor'] ? '#FF6B35' : '#00D4AA'; }, $top15));
 $chart_borders = json_encode(array_map(function($i) { return $i['is_vendor'] ? '#FF8C5A' : '#33E0BE'; }, $top15));

 $start_disp = date('d M Y', strtotime($start_date));
 $end_disp   = date('d M Y', strtotime($end_date));
 $plant_label = isset($_SESSION['active_plant']) ? strtoupper($_SESSION['active_plant']) : 'P1';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Internal vs Vendor — <?= htmlspecialchars($plant_label) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --bg:#0D1117;--card:#161B22;--border:#30363D;--text:#E6EDF3;--muted:#8B949E;
            --vendor:#FF6B35;--vendor-dim:rgba(255,107,53,0.12);--vendor-glow:rgba(255,107,53,0.25);
            --internal:#00D4AA;--internal-dim:rgba(0,212,170,0.12);--internal-glow:rgba(0,212,170,0.25);
            --accent:#FFD93D;--accent-dim:rgba(255,217,61,0.10);
            --r:10px;--rs:6px;
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
        body::before{content:'';position:fixed;top:-300px;right:-200px;width:700px;height:700px;background:radial-gradient(circle,rgba(255,107,53,0.04) 0%,transparent 65%);pointer-events:none;}
        body::after{content:'';position:fixed;bottom:-300px;left:-200px;width:700px;height:700px;background:radial-gradient(circle,rgba(0,212,170,0.04) 0%,transparent 65%);pointer-events:none;}
        .wrap{max-width:1360px;margin:0 auto;padding:20px 24px 60px;position:relative;z-index:1;}

        /* HEADER */
        .hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;}
        .hdr h1{font-family:'Space Grotesk',sans-serif;font-size:22px;font-weight:700;letter-spacing:-0.3px;}
        .hdr h1 i{color:var(--accent);margin-right:8px;font-size:18px;}
        .hdr .plant{background:var(--accent-dim);color:var(--accent);font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;letter-spacing:0.5px;}
        .hdr .period{color:var(--muted);font-size:13px;margin-top:4px;}
        .hdr .period b{color:var(--text);font-weight:600;}

        /* FILTER BAR */
        .fbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:12px 16px;margin-bottom:20px;}
        .fbar label{font-size:12px;color:var(--muted);font-weight:500;white-space:nowrap;}
        
        /* DATE PICKER FIXES */
        .fbar input[type="date"]{
            background:var(--bg);
            border:1px solid var(--border);
            color:var(--text);
            padding:7px 10px;
            border-radius:var(--rs);
            font-family:'DM Sans',sans-serif;
            font-size:12px;
            outline:none;
            transition:border .2s;
            color-scheme: dark;
            cursor: pointer;
        }
        .fbar input[type="date"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            opacity: 0.8;
        }
        .fbar input[type="date"]:focus{border-color:var(--accent);}
        
        .fbar .sep{width:1px;height:24px;background:var(--border);margin:0 2px;}
        .fbar .chk-wrap{display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;color:var(--muted);}
        .fbar .chk-wrap input[type="checkbox"]{accent-color:var(--vendor);width:14px;height:14px;cursor:pointer;}
        .btn{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:12px;font-weight:600;border:none;cursor:pointer;transition:all .2s;text-decoration:none;white-space:nowrap;}
        .btn-p{background:var(--accent);color:#0D1117;}.btn-p:hover{background:#FFE566;transform:translateY(-1px);}
        .btn-o{background:transparent;color:var(--muted);border:1px solid var(--border);}
        .btn-o:hover{border-color:var(--muted);color:var(--text);}
        .btn-o.active{border-color:var(--accent);color:var(--accent);background:var(--accent-dim);}

        /* ============================================
           SECTION PERBANDINGAN BESAR — NILAI SALES
           ============================================ */
        .compare-section{
            background:var(--card);
            border:1px solid var(--border);
            border-radius:14px;
            padding:28px 32px;
            margin-bottom:20px;
            position:relative;
            overflow:hidden;
        }
        .compare-section::before{
            content:'';position:absolute;top:0;left:0;right:0;height:2px;
            background:linear-gradient(90deg, var(--vendor) 0%, var(--accent) 50%, var(--internal) 100%);
        }
        .compare-title{
            font-family:'Space Grotesk',sans-serif;
            font-size:13px;font-weight:600;color:var(--muted);
            text-transform:uppercase;letter-spacing:1px;
            margin-bottom:20px;
            display:flex;align-items:center;gap:8px;
        }
        .compare-title i{color:var(--accent);font-size:14px;}

        .compare-grid{
            display:grid;
            grid-template-columns:1fr auto 1fr;
            gap:32px;
            align-items:center;
        }
        @media(max-width:768px){
            .compare-grid{grid-template-columns:1fr;gap:20px;}
            .compare-vs{display:none;}
        }

        /* Sisi Vendor */
        .compare-side{position:relative;}
        .compare-side.vendor-side .side-label{
            display:inline-flex;align-items:center;gap:6px;
            background:var(--vendor-dim);color:var(--vendor);
            font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;
            padding:4px 12px;border-radius:20px;margin-bottom:12px;
        }
        .compare-side.vendor-side .side-label i{font-size:10px;}
        .compare-side.vendor-side .side-value{
            font-family:'Space Grotesk',sans-serif;
            font-size:32px;font-weight:700;color:var(--vendor);
            line-height:1.1;
        }
        .compare-side.vendor-side .side-pct{
            font-family:'Space Grotesk',sans-serif;
            font-size:48px;font-weight:700;color:var(--vendor);
            opacity:0.25;line-height:1;margin-top:4px;
        }
        .compare-side.vendor-side .side-sub{
            font-size:12px;color:var(--muted);margin-top:8px;
            display:flex;gap:16px;
        }
        .compare-side.vendor-side .side-sub span{display:flex;align-items:center;gap:4px;}
        .compare-side.vendor-side .side-sub i{color:var(--vendor);font-size:10px;}

        /* Sisi Internal */
        .compare-side.internal-side .side-label{
            display:inline-flex;align-items:center;gap:6px;
            background:var(--internal-dim);color:var(--internal);
            font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;
            padding:4px 12px;border-radius:20px;margin-bottom:12px;
        }
        .compare-side.internal-side .side-label i{font-size:10px;}
        .compare-side.internal-side .side-value{
            font-family:'Space Grotesk',sans-serif;
            font-size:32px;font-weight:700;color:var(--internal);
            line-height:1.1;
        }
        .compare-side.internal-side .side-pct{
            font-family:'Space Grotesk',sans-serif;
            font-size:48px;font-weight:700;color:var(--internal);
            opacity:0.25;line-height:1;margin-top:4px;
        }
        .compare-side.internal-side .side-sub{
            font-size:12px;color:var(--muted);margin-top:8px;
            display:flex;gap:16px;
        }
        .compare-side.internal-side .side-sub span{display:flex;align-items:center;gap:4px;}
        .compare-side.internal-side .side-sub i{color:var(--internal);font-size:10px;}

        /* VS separator */
        .compare-vs{
            display:flex;flex-direction:column;align-items:center;gap:12px;
        }
        .vs-text{
            font-family:'Space Grotesk',sans-serif;
            font-size:14px;font-weight:700;color:var(--muted);
            background:var(--bg);border:1px solid var(--border);
            width:44px;height:44px;border-radius:50%;
            display:flex;align-items:center;justify-content:center;
        }

        /* Progress bar besar */
        .compare-bar-wrap{
            margin-top:24px;padding-top:20px;border-top:1px solid var(--border);
        }
        .compare-bar-label{
            display:flex;justify-content:space-between;align-items:center;
            margin-bottom:10px;
        }
        .compare-bar-label span{font-size:11px;color:var(--muted);font-weight:500;}
        .compare-bar-label .total-val{
            font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:700;color:var(--text);
        }
        .compare-bar{
            width:100%;height:28px;border-radius:14px;
            background:var(--bg);overflow:hidden;
            display:flex;position:relative;
            border:1px solid var(--border);
        }
        .compare-bar-fill-vendor{
            height:100%;
            background:linear-gradient(90deg, #FF6B35, #FF8C5A);
            display:flex;align-items:center;justify-content:center;
            font-family:'Space Grotesk',sans-serif;
            font-size:11px;font-weight:700;color:#fff;
            transition:width 1s ease;
            min-width:0;
            overflow:hidden;white-space:nowrap;
        }
        .compare-bar-fill-internal{
            height:100%;
            background:linear-gradient(90deg, #00C49A, #00D4AA);
            display:flex;align-items:center;justify-content:center;
            font-family:'Space Grotesk',sans-serif;
            font-size:11px;font-weight:700;color:#0D1117;
            transition:width 1s ease;
            min-width:0;
            overflow:hidden;white-space:nowrap;
        }

        /* Qty mini bar */
        .compare-qty-row{
            display:flex;gap:24px;margin-top:14px;flex-wrap:wrap;
        }
        .compare-qty-item{
            display:flex;align-items:center;gap:8px;
            font-size:12px;color:var(--muted);
        }
        .compare-qty-item .dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
        .compare-qty-item .dot.dv{background:var(--vendor);}
        .compare-qty-item .dot.di{background:var(--internal);}
        .compare-qty-item .dot.da{background:var(--accent);}
        .compare-qty-item strong{color:var(--text);font-family:'Space Grotesk',sans-serif;font-weight:600;}

        /* CARDS KECIL */
        .cgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:20px;}
        .card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:16px 18px;position:relative;overflow:hidden;transition:transform .2s,border-color .2s;opacity:0;transform:translateY(12px);animation:cIn .4s ease forwards;}
        .card:hover{transform:translateY(-2px);border-color:var(--muted);}
        .card:nth-child(1){animation-delay:.04s}.card:nth-child(2){animation-delay:.08s}.card:nth-child(3){animation-delay:.12s}.card:nth-child(4){animation-delay:.16s}
        @keyframes cIn{to{opacity:1;transform:translateY(0);}}
        .card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;}
        .card.cv::before{background:var(--vendor);}.card.ci::before{background:var(--internal);}.card.ca::before{background:var(--accent);}
        .c-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;margin-bottom:10px;}
        .cv .c-icon{background:var(--vendor-dim);color:var(--vendor);}.ci .c-icon{background:var(--internal-dim);color:var(--internal);}.ca .c-icon{background:var(--accent-dim);color:var(--accent);}
        .c-label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;font-weight:600;margin-bottom:4px;}
        .c-num{font-family:'Space Grotesk',sans-serif;font-size:22px;font-weight:700;line-height:1;}
        .c-sub{font-size:11px;color:var(--muted);margin-top:6px;}
        .c-sub .pct{font-weight:700;font-size:12px;}.pv{color:var(--vendor);}.pi{color:var(--internal);}

        /* CHARTS */
        .chgrid{display:grid;grid-template-columns:320px 1fr;gap:12px;margin-bottom:20px;}
        @media(max-width:900px){.chgrid{grid-template-columns:1fr;}}
        .chcard{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:20px;}
        .chcard h3{font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:8px;}
        .chcard h3 i{color:var(--accent);font-size:13px;}
        .doughnut-wrap{max-width:240px;margin:0 auto;}

        /* TABLE */
        .tsec{background:var(--card);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;}
        .thdr{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:10px;}
        .thdr h3{font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:600;display:flex;align-items:center;gap:8px;}
        .thdr h3 i{color:var(--accent);font-size:13px;}
        .thdr .tcount{font-size:11px;color:var(--muted);font-weight:400;margin-left:4px;}
        .ttools{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
        .sbox{position:relative;}
        .sbox i{position:absolute;left:9px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:12px;}
        .sbox input{background:var(--bg);border:1px solid var(--border);color:var(--text);padding:6px 10px 6px 30px;border-radius:var(--rs);font-size:12px;font-family:'DM Sans',sans-serif;outline:none;width:200px;transition:border .2s;}
        .sbox input:focus{border-color:var(--accent);}
        .twrap{overflow-x:auto;}
        table{width:100%;border-collapse:collapse;font-size:12px;}
        thead{background:rgba(255,255,255,0.02);}
        th{padding:10px 14px;text-align:left;font-weight:600;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border);white-space:nowrap;position:sticky;top:0;background:var(--card);z-index:2;}
        td{padding:10px 14px;border-bottom:1px solid rgba(48,54,61,0.5);white-space:nowrap;}
        tr:hover td{background:rgba(255,255,255,0.015);}
        td.num{text-align:right;font-family:'Space Grotesk',sans-serif;font-weight:500;}
        .badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;}
        .badge-v{background:var(--vendor-dim);color:var(--vendor);}.badge-i{background:var(--internal-dim);color:var(--internal);}
        .badge i{font-size:9px;}
        .tfooter{display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-top:1px solid var(--border);flex-wrap:wrap;gap:8px;}
        .tfooter .tf-label{font-size:12px;color:var(--muted);font-weight:600;}
        .tfooter .tf-val{font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:700;margin-left:8px;}
        .pct-bar{width:80px;height:6px;background:var(--bg);border-radius:3px;overflow:hidden;display:inline-block;vertical-align:middle;margin-left:6px;}
        .pct-bar-fill{height:100%;border-radius:3px;transition:width .6s ease;}
        .empty{text-align:center;padding:60px 20px;color:var(--muted);}.empty i{font-size:40px;margin-bottom:12px;opacity:.4;}.empty p{font-size:14px;}
        .errmsg{background:rgba(248,81,73,0.08);border:1px solid rgba(248,81,73,0.3);border-radius:var(--r);padding:16px 20px;color:#F85149;font-size:13px;margin-bottom:20px;}.errmsg i{margin-right:8px;}
        .legend{display:flex;gap:16px;justify-content:center;margin-top:12px;}
        .legend-item{display:flex;align-items:center;gap:6px;font-size:11px;color:var(--muted);}
        .legend-dot{width:10px;height:10px;border-radius:50%;}
        .toast{position:fixed;bottom:24px;right:24px;background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:12px 20px;font-size:13px;color:var(--text);z-index:999;opacity:0;transform:translateY(12px);transition:all .3s;pointer-events:none;}
        .toast.show{opacity:1;transform:translateY(0);pointer-events:auto;}.toast i{margin-right:8px;color:var(--internal);}

        @media(max-width:640px){
            .wrap{padding:12px 12px 40px;}
            .hdr h1{font-size:18px;}
            .cgrid{grid-template-columns:1fr 1fr;}
            .sbox input{width:140px;}
            th,td{padding:8px 10px;font-size:11px;}
            .compare-side.vendor-side .side-value,
            .compare-side.internal-side .side-value{font-size:22px;}
            .compare-side.vendor-side .side-pct,
            .compare-side.internal-side .side-pct{font-size:32px;}
            .compare-section{padding:20px 16px;}
        }
    </style>
</head>
<body>
<div class="wrap">

    <div class="hdr">
        <div>
            <h1><i class="fas fa-chart-pie"></i>Perbandingan Sales — Internal vs Vendor <span class="plant"><?= htmlspecialchars($plant_label) ?></span></h1>
            <div class="period">Periode: <b><?= htmlspecialchars($start_disp) ?></b> s/d <b><?= htmlspecialchars($end_disp) ?></b><?= $vendor_all ? ' &nbsp;<span style="color:var(--vendor);font-size:11px;">(cek vendor semua periode)</span>' : '' ?></div>
        </div>
    </div>

    <form class="fbar" method="GET" action="">
        <label>Dari</label>
        <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" onclick="this.showPicker()">
        
        <label>Sampai</label>
        <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" onclick="this.showPicker()">
        
        <div class="sep"></div>
        <label class="chk-wrap">
            <input type="checkbox" name="vendor_all" value="1" <?= $vendor_all ? 'checked' : '' ?>>
            Cek vendor tanpa batas tanggal
        </label>
        <div class="sep"></div>
        <button type="submit" class="btn btn-p"><i class="fas fa-filter"></i> Tampilkan</button>
        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>&vendor_all=<?= $vendor_all ? '1' : '0' ?>&export=csv" class="btn btn-o"><i class="fas fa-file-csv"></i> Export CSV</a>
    </form>

    <?php if ($query_error): ?>
    <div class="errmsg"><i class="fas fa-exclamation-triangle"></i><?= $error_msg ?></div>
    <?php endif; ?>

    <div class="compare-section">
        <div class="compare-title"><i class="fas fa-scale-balanced"></i> Perbandingan Total Nilai Sales (IDR)</div>

        <div class="compare-grid">
            <div class="compare-side vendor-side">
                <div class="side-label"><i class="fas fa-truck-field"></i> Vendor</div>
                <div class="side-value">Rp <?= number_format($vendor_value, 0, ',', '.') ?></div>
                <div class="side-pct"><?= $pct_vendor_val ?>%</div>
                <div class="side-sub">
                    <span><i class="fas fa-boxes-stacked"></i> Qty: <strong><?= number_format($vendor_qty, 0, ',', '.') ?></strong></span>
                    <span><i class="fas fa-tags"></i> <?= number_format($vendor_item_count, 0, ',', '.') ?> item</span>
                </div>
            </div>

            <div class="compare-vs">
                <div class="vs-text">VS</div>
            </div>

            <div class="compare-side internal-side" style="text-align:right;">
                <div class="side-label" style="margin-left:auto;"><i class="fas fa-industry"></i> Internal</div>
                <div class="side-value">Rp <?= number_format($internal_value, 0, ',', '.') ?></div>
                <div class="side-pct"><?= $pct_internal_val ?>%</div>
                <div class="side-sub" style="justify-content:flex-end;">
                    <span><i class="fas fa-boxes-stacked"></i> Qty: <strong><?= number_format($internal_qty, 0, ',', '.') ?></strong></span>
                    <span><i class="fas fa-tags"></i> <?= number_format($internal_item_count, 0, ',', '.') ?> item</span>
                </div>
            </div>
        </div>

        <div class="compare-bar-wrap">
            <div class="compare-bar-label">
                <span>Proporsi Nilai Sales</span>
                <span class="total-val">Total: Rp <?= number_format($total_value, 0, ',', '.') ?></span>
            </div>
            <div class="compare-bar">
                <div class="compare-bar-fill-vendor" style="width:<?= max($pct_vendor_val, 0) ?>%;">
                    <?php if ($pct_vendor_val > 8): ?><?= $pct_vendor_val ?>%<?php endif; ?>
                </div>
                <div class="compare-bar-fill-internal" style="width:<?= max($pct_internal_val, 0) ?>%;">
                    <?php if ($pct_internal_val > 8): ?><?= $pct_internal_val ?>%<?php endif; ?>
                </div>
            </div>

            <div class="compare-qty-row">
                <div class="compare-qty-item">
                    <div class="dot dv"></div>
                    Vendor Qty: <strong><?= number_format($vendor_qty, 0, ',', '.') ?></strong>
                    <span style="color:var(--vendor);font-weight:600;font-size:11px;"><?= $pct_vendor_qty ?>%</span>
                </div>
                <div class="compare-qty-item">
                    <div class="dot di"></div>
                    Internal Qty: <strong><?= number_format($internal_qty, 0, ',', '.') ?></strong>
                    <span style="color:var(--internal);font-weight:600;font-size:11px;"><?= $pct_internal_qty ?>%</span>
                </div>
                <div class="compare-qty-item">
                    <div class="dot da"></div>
                    Total Qty: <strong><?= number_format($total_qty, 0, ',', '.') ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="cgrid">
        <div class="card cv">
            <div class="c-icon"><i class="fas fa-tags"></i></div>
            <div class="c-label">Item Vendor</div>
            <div class="c-num"><?= number_format($vendor_item_count, 0, ',', '.') ?></div>
            <div class="c-sub">Nilai: <span class="pct pv">Rp <?= number_format($vendor_value, 0, ',', '.') ?></span></div>
        </div>
        <div class="card ci">
            <div class="c-icon"><i class="fas fa-tags"></i></div>
            <div class="c-label">Item Internal</div>
            <div class="c-num"><?= number_format($internal_item_count, 0, ',', '.') ?></div>
            <div class="c-sub">Nilai: <span class="pct pi">Rp <?= number_format($internal_value, 0, ',', '.') ?></span></div>
        </div>
        <div class="card ca">
            <div class="c-icon"><i class="fas fa-boxes-stacked"></i></div>
            <div class="c-label">Total Item</div>
            <div class="c-num"><?= number_format(count($data), 0, ',', '.') ?></div>
            <div class="c-sub">Qty: <strong><?= number_format($total_qty, 0, ',', '.') ?></strong></div>
        </div>
        <div class="card ca">
            <div class="c-icon"><i class="fas fa-coins"></i></div>
            <div class="c-label">Rata-rata / Item</div>
            <div class="c-num" style="font-size:18px;">Rp <?= count($data) > 0 ? number_format($total_value / count($data), 0, ',', '.') : 0 ?></div>
            <div class="c-sub">Per item sales</div>
        </div>
    </div>

    <div class="chgrid">
        <div class="chcard">
            <h3><i class="fas fa-chart-pie"></i> Komposisi Nilai</h3>
            <div class="doughnut-wrap"><canvas id="doughnutChart"></canvas></div>
            <div class="legend">
                <div class="legend-item"><div class="legend-dot" style="background:var(--vendor)"></div>Vendor <?= $pct_vendor_val ?>%</div>
                <div class="legend-item"><div class="legend-dot" style="background:var(--internal)"></div>Internal <?= $pct_internal_val ?>%</div>
            </div>
        </div>
        <div class="chcard">
            <h3><i class="fas fa-chart-bar"></i> Top 15 Item — Nilai Sales</h3>
            <canvas id="barChart" style="max-height:320px;"></canvas>
        </div>
    </div>

    <div class="tsec">
        <div class="thdr">
            <h3><i class="fas fa-table-list"></i> Detail per Item <span class="tcount">(<?= number_format($display_count, 0, ',', '.') ?> item)</span></h3>
            <div class="ttools">
                <button class="btn btn-o <?= $filter_type === 'all' ? 'active' : '' ?>" onclick="setFilter('all')">Semua</button>
                <button class="btn btn-o <?= $filter_type === 'vendor' ? 'active' : '' ?>" onclick="setFilter('vendor')"><i class="fas fa-truck-field" style="color:var(--vendor);font-size:10px;"></i> Vendor</button>
                <button class="btn btn-o <?= $filter_type === 'internal' ? 'active' : '' ?>" onclick="setFilter('internal')"><i class="fas fa-industry" style="color:var(--internal);font-size:10px;"></i> Internal</button>
                <div class="sep" style="height:22px;"></div>
                <div class="sbox">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Cari kode / nama item..." oninput="filterTable()">
                </div>
            </div>
        </div>

        <?php if (empty($display_data) && !$query_error): ?>
        <div class="empty"><i class="fas fa-inbox"></i><p>Tidak ada data untuk periode dan filter yang dipilih.</p></div>
        <?php elseif (!$query_error): ?>
        <div class="twrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:40px;">No</th>
                        <th>Kode Item</th>
                        <th>Nama Item</th>
                        <th style="text-align:right;">Qty Sales</th>
                        <th style="text-align:right;">Nilai Sales (IDR)</th>
                        <th style="text-align:center;">% Total</th>
                        <th style="text-align:center;">Tipe</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                <?php
                $no = 1;
                foreach ($display_data as $d):
                    $pct_of_total = $total_value > 0 ? round(($d['total_sales'] / $total_value) * 100, 2) : 0;
                    $badge_class = $d['is_vendor'] ? 'badge-v' : 'badge-i';
                    $badge_text  = $d['is_vendor'] ? 'Vendor' : 'Internal';
                    $badge_icon  = $d['is_vendor'] ? 'fa-truck-field' : 'fa-industry';
                    $bar_color   = $d['is_vendor'] ? 'var(--vendor)' : 'var(--internal)';
                    $bar_width   = min($pct_of_total * 3, 100);
                ?>
                    <tr>
                        <td class="num" style="color:var(--muted);"><?= $no++ ?></td>
                        <td style="font-weight:600;font-family:'Space Grotesk',sans-serif;"><?= htmlspecialchars($d['item_code']) ?></td>
                        <td><?= htmlspecialchars($d['item_name']) ?></td>
                        <td class="num"><?= number_format($d['qty_sales'], 0, ',', '.') ?></td>
                        <td class="num">Rp <?= number_format($d['total_sales'], 0, ',', '.') ?></td>
                        <td class="num" style="text-align:center;">
                            <?= $pct_of_total ?>%
                            <div class="pct-bar"><div class="pct-bar-fill" style="width:<?= $bar_width ?>%;background:<?= $bar_color ?>;"></div></div>
                        </td>
                        <td style="text-align:center;"><span class="badge <?= $badge_class ?>"><i class="fas <?= $badge_icon ?>"></i><?= $badge_text ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="tfooter">
            <div>
                <span class="tf-label">Total Qty:</span><span class="tf-val" id="footQty"><?= number_format($total_qty, 0, ',', '.') ?></span>
                <span style="margin-left:16px;" class="tf-label">Total Nilai:</span><span class="tf-val" id="footVal">Rp <?= number_format($total_value, 0, ',', '.') ?></span>
            </div>
            <div>
                <span class="tf-label">Vendor:</span><span class="tf-val pv" id="footVendor"><?= number_format($vendor_item_count, 0, ',', '.') ?> item</span>
                <span style="margin-left:12px;" class="tf-label">Internal:</span><span class="tf-val pi" id="footInternal"><?= number_format($internal_item_count, 0, ',', '.') ?> item</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<div class="toast" id="toast"><i class="fas fa-check-circle"></i><span id="toastMsg"></span></div>

<script>
var allData = <?= json_encode($data, JSON_UNESCAPED_UNICODE) ?>;
var currentFilter = '<?= $filter_type ?>';
var grandTotal = 0;
for (var i = 0; i < allData.length; i++) grandTotal += allData[i].total_sales;

// DOUGHNUT
(function(){
    var ctx = document.getElementById('doughnutChart').getContext('2d');
    new Chart(ctx, {
        type:'doughnut',
        data:{
            labels:['Vendor','Internal'],
            datasets:[{data:[<?= $vendor_value ?>,<?= $internal_value ?>],backgroundColor:['#FF6B35','#00D4AA'],borderColor:['#FF8C5A','#33E0BE'],borderWidth:2,hoverOffset:8}]
        },
        options:{
            responsive:true,cutout:'65%',
            plugins:{legend:{display:false},tooltip:{
                backgroundColor:'#1C2333',titleColor:'#E6EDF3',bodyColor:'#8B949E',borderColor:'#30363D',borderWidth:1,cornerRadius:8,padding:12,
                callbacks:{label:function(c){var t=c.dataset.data.reduce(function(a,b){return a+b;},0);var p=t>0?((c.parsed/t)*100).toFixed(1):0;return c.label+': Rp '+c.parsed.toLocaleString('id-ID')+' ('+p+'%)';}}
            }},
            animation:{animateRotate:true,duration:900}
        }
    });
})();

// BAR
(function(){
    var ctx = document.getElementById('barChart').getContext('2d');
    new Chart(ctx,{
        type:'bar',
        data:{
            labels:<?= $chart_labels ?>,
            datasets:[{label:'Nilai Sales',data:<?= $chart_values ?>,backgroundColor:<?= $chart_colors ?>,borderColor:<?= $chart_borders ?>,borderWidth:1,borderRadius:4,barThickness:22}]
        },
        options:{
            indexAxis:'y',responsive:true,maintainAspectRatio:false,
            plugins:{legend:{display:false},tooltip:{
                backgroundColor:'#1C2333',titleColor:'#E6EDF3',bodyColor:'#8B949E',borderColor:'#30363D',borderWidth:1,cornerRadius:8,padding:12,
                callbacks:{label:function(c){return 'Rp '+c.parsed.x.toLocaleString('id-ID');}}
            }},
            scales:{
                x:{grid:{color:'rgba(48,54,61,0.4)'},ticks:{color:'#8B949E',font:{size:11,family:'Space Grotesk'},callback:function(v){if(v>=1e9)return(v/1e9).toFixed(1)+'M';if(v>=1e6)return(v/1e6).toFixed(1)+'Jt';if(v>=1e3)return(v/1e3).toFixed(0)+'Rb';return v;}}},
                y:{grid:{display:false},ticks:{color:'#E6EDF3',font:{size:11,family:'Space Grotesk'}}}
            },
            animation:{duration:800}
        }
    });
})();

// FILTER TABEL
function setFilter(type){
    currentFilter=type;renderTable();updateFilterButtons();
    showToast(type==='all'?'Menampilkan semua item':'Filter: '+(type==='vendor'?'Vendor':'Internal'));
}
function updateFilterButtons(){
    var btns=document.querySelectorAll('.ttools .btn-o');
    for(var i=0;i<btns.length;i++){btns[i].classList.remove('active');var t=btns[i].textContent.trim().toLowerCase();if(currentFilter==='all'&&t==='semua')btns[i].classList.add('active');if(currentFilter==='vendor'&&t.indexOf('vendor')>-1)btns[i].classList.add('active');if(currentFilter==='internal'&&t.indexOf('internal')>-1)btns[i].classList.add('active');}
}
function filterTable(){renderTable();}

function renderTable(){
    var search=document.getElementById('searchInput').value.toLowerCase();
    var tbody=document.getElementById('tableBody');
    var filtered=[];
    for(var i=0;i<allData.length;i++){
        var d=allData[i];
        if(currentFilter==='vendor'&&!d.is_vendor)continue;
        if(currentFilter==='internal'&&d.is_vendor)continue;
        if(search&&d.item_code.toLowerCase().indexOf(search)===-1&&d.item_name.toLowerCase().indexOf(search)===-1)continue;
        filtered.push(d);
    }
    var html='';var tQ=0,tV=0,vC=0,iC=0;
    for(var k=0;k<filtered.length;k++){
        var item=filtered[k];
        var pct=grandTotal>0?((item.total_sales/grandTotal)*100).toFixed(2):0;
        var bw=Math.min(pct*3,100);
        var bc=item.is_vendor?'var(--vendor)':'var(--internal)';
        var bclass=item.is_vendor?'badge-v':'badge-i';
        var btext=item.is_vendor?'Vendor':'Internal';
        var bicon=item.is_vendor?'fa-truck-field':'fa-industry';
        tQ+=item.qty_sales;tV+=item.total_sales;if(item.is_vendor)vC++;else iC++;
        html+='<tr>'
            +'<td class="num" style="color:var(--muted);">'+(k+1)+'</td>'
            +'<td style="font-weight:600;font-family:Space Grotesk,sans-serif;">'+escHtml(item.item_code)+'</td>'
            +'<td>'+escHtml(item.item_name)+'</td>'
            +'<td class="num">'+fmtN(item.qty_sales)+'</td>'
            +'<td class="num">Rp '+fmtN(item.total_sales)+'</td>'
            +'<td class="num" style="text-align:center;">'+pct+'% <div class="pct-bar"><div class="pct-bar-fill" style="width:'+bw+'%;background:'+bc+';"></div></div></td>'
            +'<td style="text-align:center;"><span class="badge '+bclass+'"><i class="fas '+bicon+'"></i>'+btext+'</span></td>'
            +'</tr>';
    }
    tbody.innerHTML=html;
    document.getElementById('footQty').textContent=fmtN(tQ);
    document.getElementById('footVal').textContent='Rp '+fmtN(tV);
    document.getElementById('footVendor').textContent=fmtN(vC)+' item';
    document.getElementById('footInternal').textContent=fmtN(iC)+' item';
    var cEls=document.querySelectorAll('.tcount');for(var c=0;c<cEls.length;c++)cEls[c].textContent='('+fmtN(filtered.length)+' item)';
}

function escHtml(s){var d=document.createElement('div');d.appendChild(document.createTextNode(s));return d.innerHTML;}
function fmtN(n){return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g,'.');}
function showToast(msg){var t=document.getElementById('toast');document.getElementById('toastMsg').textContent=msg;t.classList.add('show');clearTimeout(t._timer);t._timer=setTimeout(function(){t.classList.remove('show');},2000);}
</script>
</body>
</html>
<?php
/**
 * AUTO GABUNGAN BOM & HARGA MATERIAL (PLANT 1 & PLANT 2)
 * Script ini berfungsi sebagai rincian (detail) dari P&L Dashboard.
 * Menampilkan struktur BOM, HPP per unit, Total COGS, dan Status Margin (Loss/Profit) per Bulan.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['db_user']) || $_SESSION['db_user'] == "") {
    die('<div style="padding:24px;color:#F85149;font-family:sans-serif;">Silakan login terlebih dahulu.</div>');
}

$uid = $_SESSION['db_user'];
$pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "";
$databaseName = "msData";

$servers = array(
    'P1' => '192.168.0.4',
    'P2' => '192.168.0.9'
);

$connectionOptions = array("Database" => $databaseName, "Uid" => $uid, "PWD" => $pwd, "CharacterSet" => "UTF-8");

$connP1 = @sqlsrv_connect($servers['P1'], $connectionOptions);
$connP2 = @sqlsrv_connect($servers['P2'], $connectionOptions);

if ($connP1 === false && $connP2 === false) {
    die("Koneksi ke semua Database Gagal (P1 dan P2 down).");
}

function q($conn, $sql, $params = array()) {
    if (!$conn) return false;
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return false; 
    return $stmt;
}

// SETUP BULAN DAN TAHUN
$bulan = isset($_GET['bulan']) ? str_pad($_GET['bulan'], 2, '0', STR_PAD_LEFT) : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

$tglAwalTime = "$tahun-$bulan-01 00:00:00";
$tglAkhirTime = date("Y-m-t", strtotime("$tahun-$bulan-01")) . " 23:59:59";

function getDaftarPart($connP1, $connP2) {
    $sql = "
        SELECT DISTINCT i.ITEM_ID AS PART_ID, i.ITEM_CODE AS PART_CODE, i.ITEM_NAME AS PART_NAME
        FROM BOM_DEFAULT bd
        INNER JOIN ITEMS m ON bd.ITEM_ID = m.ITEM_ID
        INNER JOIN ITEMS i ON bd.PART_ID = i.ITEM_ID
        WHERE (m.ITTY_CODE IN ('02', '03') OR (i.ITEM_CODE LIKE '02%' AND m.ITTY_CODE = '01'))
          AND i.ITEM_INACTIVE = 0 AND m.ITEM_INACTIVE = 0
    ";
    
    $parts = array();
    if ($connP1) {
        $stmt = q($connP1, $sql);
        if ($stmt) { while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { $parts[$row['PART_ID']] = $row; } }
    }
    if ($connP2) {
        $stmt = q($connP2, $sql);
        if ($stmt) { while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { $parts[$row['PART_ID']] = $row; } }
    }
    usort($parts, function($a, $b) { return strcmp($a['PART_CODE'], $b['PART_CODE']); });
    return $parts;
}

function getMaterialHarga($conn, $plant_source, $filter_part_id = '') {
    if (!$conn) return array();
    $sql = "
        ;WITH LatestPO AS
        (
            SELECT pd.ITEM_ID AS MAT_ID, pd.POD_PRICE, po.PO_DATE AS PRICE_DATE_RAW, pd.POD_UNIT, po.PO_CUR,
                   ROW_NUMBER() OVER(PARTITION BY pd.ITEM_ID ORDER BY po.PO_DATE DESC, po.PO_ID DESC) AS rn
            FROM PO_DETAIL pd INNER JOIN PO po ON pd.PO_ID = po.PO_ID
        ),
        PriceData AS
        (
            SELECT p.MAT_ID, p.POD_PRICE, p.PRICE_DATE_RAW, p.POD_UNIT, p.PO_CUR, c.CURR_VRATE,
                CAST(
                    CASE WHEN p.POD_PRICE IS NULL THEN NULL
                         WHEN ISNULL(p.PO_CUR, 'IDR') = 'IDR' THEN p.POD_PRICE
                         WHEN c.CURR_VRATE IS NULL THEN NULL
                         ELSE p.POD_PRICE * c.CURR_VRATE
                    END AS DECIMAL(38, 8)
                ) AS PRICE_IDR,
                CASE WHEN p.POD_PRICE IS NULL THEN 'TANPA_HARGA'
                     WHEN ISNULL(p.PO_CUR, 'IDR') <> 'IDR' AND c.CURR_VRATE IS NULL THEN 'TANPA_KURS'
                     ELSE 'OK'
                END AS PRICE_STATUS
            FROM LatestPO p
            LEFT JOIN CURR_RAT c ON p.PO_CUR = c.CURR_CODE AND p.PRICE_DATE_RAW BETWEEN c.CURR_SDATE AND ISNULL(c.CURR_EDATE, p.PRICE_DATE_RAW)
            WHERE p.rn = 1
        ),
        HppBomTree AS
        (
            SELECT bd.PART_ID AS ROOT_PART_ID, bd.ITEM_ID AS COMPONENT_ID, m.ITTY_CODE,
                   CAST(bd.QTY AS DECIMAL(38, 8)) AS TOTAL_QTY,
                   CAST('/' + CONVERT(VARCHAR(50), bd.PART_ID) + '/' + CONVERT(VARCHAR(50), bd.ITEM_ID) + '/' AS VARCHAR(MAX)) AS BOM_PATH,
                   1 AS BOM_LEVEL
            FROM BOM_DEFAULT bd
            INNER JOIN ITEMS root_item ON bd.PART_ID = root_item.ITEM_ID
            INNER JOIN ITEMS m ON bd.ITEM_ID = m.ITEM_ID
            WHERE root_item.ITTY_CODE = '01' AND root_item.ITEM_INACTIVE = 0 AND m.ITEM_INACTIVE = 0

            UNION ALL

            SELECT bt.ROOT_PART_ID, bd.ITEM_ID AS COMPONENT_ID, m.ITTY_CODE,
                   CAST(bt.TOTAL_QTY * bd.QTY AS DECIMAL(38, 8)) AS TOTAL_QTY,
                   CAST(bt.BOM_PATH + CONVERT(VARCHAR(50), bd.ITEM_ID) + '/' AS VARCHAR(MAX)) AS BOM_PATH,
                   bt.BOM_LEVEL + 1
            FROM HppBomTree bt
            INNER JOIN ITEMS current_item ON bt.COMPONENT_ID = current_item.ITEM_ID AND current_item.ITTY_CODE = '01'
            INNER JOIN BOM_DEFAULT bd ON bt.COMPONENT_ID = bd.PART_ID
            INNER JOIN ITEMS m ON bd.ITEM_ID = m.ITEM_ID
            WHERE bt.BOM_LEVEL < 20 AND current_item.ITEM_INACTIVE = 0 AND m.ITEM_INACTIVE = 0
              AND bt.BOM_PATH NOT LIKE '%/' + CONVERT(VARCHAR(50), bd.ITEM_ID) + '/%'
        ),
        Hpp01Summary AS
        (
            SELECT bt.ROOT_PART_ID,
                CAST(SUM(
                    CASE WHEN bt.ITTY_CODE = '02' THEN ROUND(((bt.TOTAL_QTY * ISNULL(pd.PRICE_IDR, 0)) / 1000.0), 2)
                         WHEN bt.ITTY_CODE = '03' THEN ROUND((bt.TOTAL_QTY * ISNULL(pd.PRICE_IDR, 0)), 2)
                         ELSE 0 END
                ) AS DECIMAL(38, 8)) AS HPP_IDR_PER_UNIT,
                MAX(pd.PRICE_DATE_RAW) AS HPP_DATE_RAW,
                SUM(CASE WHEN bt.ITTY_CODE IN ('02', '03') THEN 1 ELSE 0 END) AS LEAF_COUNT,
                SUM(CASE WHEN bt.ITTY_CODE IN ('02', '03') AND (pd.MAT_ID IS NULL OR pd.PRICE_STATUS <> 'OK') THEN 1 ELSE 0 END) AS MISSING_PRICE_COUNT
            FROM HppBomTree bt LEFT JOIN PriceData pd ON bt.COMPONENT_ID = pd.MAT_ID
            WHERE bt.ITTY_CODE IN ('02', '03')
            GROUP BY bt.ROOT_PART_ID
        ),
        DirectBom AS
        (
            SELECT i.ITEM_ID AS PART_ID, i.ITEM_CODE AS PART_CODE, i.ITEM_NAME AS PART_NAME,
                   m.ITEM_ID AS MAT_ID, m.ITEM_CODE AS MAT_CODE, m.ITEM_NAME AS MAT_NAME,
                   CAST(bd.QTY AS DECIMAL(38, 8)) AS QTY, m.ITTY_CODE
            FROM BOM_DEFAULT bd
            INNER JOIN ITEMS m ON bd.ITEM_ID = m.ITEM_ID
            INNER JOIN ITEMS i ON bd.PART_ID = i.ITEM_ID
            WHERE (m.ITTY_CODE IN ('02', '03') OR (i.ITEM_CODE LIKE '02%' AND m.ITTY_CODE = '01'))
              AND i.ITEM_INACTIVE = 0 AND m.ITEM_INACTIVE = 0
        )

        SELECT
            x.PART_ID, x.PART_CODE, x.PART_NAME,
            x.MAT_ID, x.MAT_CODE, x.MAT_NAME, x.BOM_QTY, x.ITTY_CODE, x.SATUAN_HITUNG,
            x.HARGA_PO, CONVERT(VARCHAR(10), x.PRICE_DATE_RAW, 23) AS TGL_PO,
            x.SATUAN_PO, x.MATA_UANG, x.KURS_VRATE, x.TOTAL_HARGA, x.STATUS_HARGA, x.SUMBER_HARGA,
            '$plant_source' AS PLANT_ASAL
        FROM
        (
            SELECT
                b.PART_ID, b.PART_CODE, b.PART_NAME, b.MAT_ID, b.MAT_CODE, b.MAT_NAME, b.QTY AS BOM_QTY, b.ITTY_CODE,
                CASE b.ITTY_CODE WHEN '01' THEN 'Pcs x HPP' WHEN '02' THEN 'Kg (/1000)' WHEN '03' THEN 'Pcs' ELSE b.ITTY_CODE END AS SATUAN_HITUNG,
                CAST(CASE WHEN b.ITTY_CODE = '01' THEN ISNULL(h.HPP_IDR_PER_UNIT, 0) ELSE ISNULL(pd.POD_PRICE, 0) END AS DECIMAL(38, 8)) AS HARGA_PO,
                CASE WHEN b.ITTY_CODE = '01' THEN h.HPP_DATE_RAW ELSE pd.PRICE_DATE_RAW END AS PRICE_DATE_RAW,
                CASE WHEN b.ITTY_CODE = '01' THEN 'HPP/Pcs' ELSE pd.POD_UNIT END AS SATUAN_PO,
                CASE WHEN b.ITTY_CODE = '01' THEN 'IDR' ELSE ISNULL(pd.PO_CUR, '-') END AS MATA_UANG,
                CASE WHEN b.ITTY_CODE = '01' THEN NULL ELSE pd.CURR_VRATE END AS KURS_VRATE,

                CAST(
                    CASE WHEN b.ITTY_CODE = '01' THEN ROUND((b.QTY * ISNULL(h.HPP_IDR_PER_UNIT, 0)), 2)
                         WHEN b.ITTY_CODE = '02' THEN ROUND(((b.QTY * ISNULL(pd.PRICE_IDR, 0)) / 1000.0), 2)
                         WHEN b.ITTY_CODE = '03' THEN ROUND((b.QTY * ISNULL(pd.PRICE_IDR, 0)), 2)
                         ELSE 0 END
                    AS DECIMAL(38, 2)
                ) AS TOTAL_HARGA,

                CASE WHEN b.ITTY_CODE = '01' AND (h.ROOT_PART_ID IS NULL OR ISNULL(h.LEAF_COUNT, 0) = 0) THEN 'TANPA_HPP'
                     WHEN b.ITTY_CODE = '01' AND ISNULL(h.MISSING_PRICE_COUNT, 0) > 0 THEN 'HPP_TIDAK_LENGKAP'
                     WHEN b.ITTY_CODE = '01' THEN 'OK'
                     WHEN pd.MAT_ID IS NULL THEN 'TANPA_HARGA'
                     ELSE pd.PRICE_STATUS END AS STATUS_HARGA,
                CASE WHEN b.ITTY_CODE = '01' THEN 'HPP BOM ITTY 01' ELSE 'PO TERAKHIR' END AS SUMBER_HARGA
            FROM DirectBom b
            LEFT JOIN PriceData pd ON b.MAT_ID = pd.MAT_ID
            LEFT JOIN Hpp01Summary h ON b.MAT_ID = h.ROOT_PART_ID
        ) x
        WHERE 1 = 1
    ";

    $params = array();
    if ($filter_part_id != '') { $sql .= " AND x.PART_ID = ?"; $params[] = $filter_part_id; }

    $sql .= " ORDER BY x.PART_CODE, CASE x.ITTY_CODE WHEN '01' THEN 1 WHEN '02' THEN 2 WHEN '03' THEN 3 ELSE 9 END, x.MAT_CODE OPTION (MAXRECURSION 100)";

    $stmt = q($conn, $sql, $params);
    $data = array();
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { $data[] = $row; }
    }
    return $data;
}

// =========================================================================
// FUNGSI TARIK DATA SALES MURNI (REVENUE & QTY) PER ITEM DI BULAN TERSEBUT
// =========================================================================
function getSalesDataPerItem($conn, $part_id, $tglMulai, $tglSelesai) {
    if (!$conn || $part_id == '') return null;
    $sql = "
        SELECT SUM(DIPA_PAR.QTY) AS QTY_SOLD,
               SUM(DIPA_PAR.QTY * DIPA_PAR.PART_PRICE * CASE WHEN APV.CURR_CODE IN ('IDR', 'RP') OR APV.CURR_CODE IS NULL THEN 1 ELSE ISNULL(RV.CURR_VRATE, 1) END) AS TOTAL_REVENUE
        FROM DI
        INNER JOIN DIPA_PAR ON DI.DI_ID = DIPA_PAR.DI_ID
        INNER JOIN PRICE ON DIPA_PAR.PRICE_ID = PRICE.PRICE_ID
        LEFT JOIN ACTIVE_PRICE_VIEW AS APV ON PRICE.PRICE_ID = APV.PRICE_ID
        LEFT JOIN CURR_RAT AS RV ON APV.CURR_CODE = RV.CURR_CODE AND DI.DI_DATE BETWEEN RV.CURR_SDATE AND ISNULL(RV.CURR_EDATE, DI.DI_DATE)
        WHERE PRICE.PART_ID = ? AND DI.DI_DATE >= ? AND DI.DI_DATE <= ?
    ";
    $stmt = @sqlsrv_query($conn, $sql, [$part_id, $tglMulai, $tglSelesai]);
    if ($stmt) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        return $row;
    }
    return null;
}

$filter_part_id = isset($_GET['part_id']) ? trim($_GET['part_id']) : '';

$daftar_part = getDaftarPart($connP1, $connP2);
$data_P1 = getMaterialHarga($connP1, 'P1', $filter_part_id);
$data_P2 = getMaterialHarga($connP2, 'P2', $filter_part_id);

$map_P1 = array(); $map_P2 = array();
foreach ($data_P1 as $row) { $map_P1[$row['PART_ID'] . '_' . $row['MAT_ID']] = $row; }
foreach ($data_P2 as $row) { $map_P2[$row['PART_ID'] . '_' . $row['MAT_ID']] = $row; }

$all_keys = array_unique(array_merge(array_keys($map_P1), array_keys($map_P2)));
$final_data = array();

foreach ($all_keys as $key) {
    $row_P1 = isset($map_P1[$key]) ? $map_P1[$key] : null;
    $row_P2 = isset($map_P2[$key]) ? $map_P2[$key] : null;
    $harga_P1 = $row_P1 ? (float)$row_P1['TOTAL_HARGA'] : 0;
    $harga_P2 = $row_P2 ? (float)$row_P2['TOTAL_HARGA'] : 0;
    
    $chosen_row = null;
    if ($harga_P1 > 0 && $harga_P2 > 0) {
        $chosen_row = $row_P1; $chosen_row['STATUS_HARGA'] = 'OK';
    } elseif ($harga_P1 > 0 && $harga_P2 == 0) {
        $chosen_row = $row_P1;
        if ($row_P2) { $chosen_row['STATUS_HARGA'] = 'CROSS_PLANT'; $chosen_row['SUMBER_HARGA'] = 'Diambil dr P1'; }
    } elseif ($harga_P1 == 0 && $harga_P2 > 0) {
        $chosen_row = $row_P2;
        if ($row_P1) { $chosen_row['STATUS_HARGA'] = 'CROSS_PLANT'; $chosen_row['SUMBER_HARGA'] = 'Diambil dr P2'; }
    } else {
        $chosen_row = $row_P1 ? $row_P1 : $row_P2;
        $chosen_row['STATUS_HARGA'] = 'SUBCON'; $chosen_row['HARGA_PO'] = 0; $chosen_row['TOTAL_HARGA'] = 0;
    }
    $final_data[] = $chosen_row;
}

usort($final_data, function($a, $b) {
    $cmp = strcmp($a['PART_CODE'], $b['PART_CODE']);
    if ($cmp !== 0) return $cmp;
    $ittyA = ($a['ITTY_CODE'] == '01') ? 1 : (($a['ITTY_CODE'] == '02') ? 2 : (($a['ITTY_CODE'] == '03') ? 3 : 9));
    $ittyB = ($b['ITTY_CODE'] == '01') ? 1 : (($b['ITTY_CODE'] == '02') ? 2 : (($b['ITTY_CODE'] == '03') ? 3 : 9));
    if ($ittyA != $ittyB) return $ittyA - $ittyB;
    return strcmp($a['MAT_CODE'], $b['MAT_CODE']);
});

$total_harga_idr = 0; $jml_tanpa_harga = 0; $jml_subcon = 0; $jml_cross = 0;
foreach ($final_data as $row) {
    $total_harga_idr += (float)$row['TOTAL_HARGA'];
    if ($row['STATUS_HARGA'] == 'TANPA_HARGA') $jml_tanpa_harga++;
    if ($row['STATUS_HARGA'] == 'SUBCON') $jml_subcon++;
    if ($row['STATUS_HARGA'] == 'CROSS_PLANT') $jml_cross++;
}

// -------------------------------------------------------------
// PENGAMBILAN DATA PENJUALAN MURNI BERDASARKAN BULAN DAN PART
// -------------------------------------------------------------
$qty_sold = 0;
$revenue_sold = 0;

if ($filter_part_id != '') {
    $salesP1 = getSalesDataPerItem($connP1, $filter_part_id, $tglAwalTime, $tglAkhirTime);
    $salesP2 = getSalesDataPerItem($connP2, $filter_part_id, $tglAwalTime, $tglAkhirTime);
    
    if ($salesP1) {
        $qty_sold += (float)$salesP1['QTY_SOLD'];
        $revenue_sold += (float)$salesP1['TOTAL_REVENUE'];
    }
    if ($salesP2) {
        $qty_sold += (float)$salesP2['QTY_SOLD'];
        $revenue_sold += (float)$salesP2['TOTAL_REVENUE'];
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rincian BOM & Margin Material</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; background: #f0f2f5; color: #333; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: #2563EB; color: white; padding: 20px; border-radius: 10px 10px 0 0; }
        .filter-box { background: white; padding: 20px; border: 1px solid #e0e0e0; border-bottom:none; border-radius: 0 0 10px 10px; margin-bottom: 20px;}
        .filter-row { display: flex; gap: 15px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group select, .filter-group input { padding: 8px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; }
        .filter-group select { min-width:300px; }
        .btn { padding: 9px 20px; border: none; border-radius: 6px; font-size: 13px; cursor: pointer; color: white; font-weight:bold; text-decoration:none;}
        .btn-primary { background: #2563EB; } .btn-warning { background: #F59E0B; }
        
        .stats-box { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 15px 0; }
        .stat-card { background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #2563EB; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .stat-label { font-size: 11px; color: #6B7280; font-weight: 700; text-transform: uppercase;}
        .stat-value { font-size: 18px; font-weight: 700; margin-top: 3px; color: #1F2937;}
        
        table { width: 100%; border-collapse: collapse; font-size: 12px; background: white; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-radius:8px; overflow:hidden;}
        thead { background: #F3F4F6; color: #4B5563; } th, td { padding: 10px; border-bottom: 1px solid #E5E7EB; }
        .text-right { text-align: right; } .text-center { text-align: center; }
        
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-01 { background: #FEF3C7; color: #B45309; } .badge-02 { background: #DBEAFE; color: #1D4ED8; } .badge-03 { background: #D1FAE5; color: #15803D; }
        .badge-error { background: #FEE2E2; color: #B91C1C; } .badge-success { background: #D1FAE5; color: #15803D; }
        .badge-subcon { background: #E0F2FE; color: #0369A1; border: 1px solid #38BDF8; }
        .badge-cross { background: #EDE9FE; color: #0369A1; border: 1px solid #7DD3FC; cursor:help;}
        
        .part-header { background: #EFF6FF !important; font-weight: bold; color: #1E40AF; }
        .subtotal-row { background: #F9FAFB !important; font-weight: bold; color: #374151;}
        .grand-total { background: #2563EB !important; color: white !important; font-weight: bold; font-size:14px;}
        .qty-row { background: #FEF3C7 !important; color: #92400E !important; font-weight: bold; font-size:14px;}
        .plant-label { font-size: 10px; color:#6B7280; font-weight:normal; display:block; margin-top:3px;}
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2 style="margin:0; font-size:20px;">📦 Rincian HPP BOM & Analisis Margin Material</h2>
        <p style="opacity:0.8; font-size:13px; margin-top:5px; margin-bottom:0;">Membongkar struktur bahan baku (BOM) dan membandingkan Harga Modal (HPP) dengan Harga Penjualan murni di bulan terkait.</p>
    </div>

    <div class="filter-box">
        <form method="GET">
            <div class="filter-row">
                <div class="filter-group">
                    <label style="font-size:11px; font-weight:bold; margin-bottom:5px;">Bulan</label>
                    <input type="number" name="bulan" min="1" max="12" value="<?php echo (int)$bulan; ?>" style="width:70px;">
                </div>
                <div class="filter-group">
                    <label style="font-size:11px; font-weight:bold; margin-bottom:5px;">Tahun</label>
                    <input type="number" name="tahun" value="<?php echo $tahun; ?>" style="width:90px;">
                </div>
                <div class="filter-group">
                    <label style="font-size:11px; font-weight:bold; margin-bottom:5px;">Pilih Item Barang Jadi (FG)</label>
                    <select name="part_id">
                        <option value="">-- Semua Part --</option>
                        <?php foreach ($daftar_part as $part): ?>
                            <option value="<?php echo $part['PART_ID']; ?>" <?php echo ($filter_part_id == $part['PART_ID']) ? 'selected' : ''; ?>>
                                <?php echo $part['PART_CODE'] . ' - ' . $part['PART_NAME']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group" style="flex-direction:row; gap:5px;">
                    <button type="submit" class="btn btn-primary">Cari Detail</button>
                    <a href="laporan_laba_rugi.php" class="btn btn-warning" style="background:#4B5563;">Kembali ke Dashboard</a>
                </div>
            </div>
        </form>
    </div>

    <?php if ($filter_part_id != ''): ?>
    <div class="stats-box">
        <div class="stat-card"><div class="stat-label">Total Material BOM</div><div class="stat-value"><?php echo count($final_data); ?> Item</div></div>
        <div class="stat-card"><div class="stat-label">HPP / Unit (IDR)</div><div class="stat-value">Rp <?php echo number_format($total_harga_idr, 2, ',', '.'); ?></div></div>
        <div class="stat-card" style="border-left-color:#3B82F6;"><div class="stat-label">Harga Bantuan Cross-Plant</div><div class="stat-value"><?php echo $jml_cross; ?> Material</div></div>
        <div class="stat-card" style="border-left-color:#0284C7;"><div class="stat-label">Indikasi Maklon (Subcon)</div><div class="stat-value"><?php echo $jml_subcon; ?> Material</div></div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center">No</th>
                <th>Part Code</th>
                <th>Material Code</th>
                <th>Material Name</th>
                <th class="text-center">ITTY</th>
                <th class="text-right">BOM QTY</th>
                <th class="text-right">Harga Dasar</th>
                <th class="text-right">Total Harga Modal</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (count($final_data) > 0):
                $no = 1;
                $current_part = '';
                $subtotal_part = 0;
                foreach ($final_data as $row):
                    if ($current_part != $row['PART_CODE']):
                        if ($current_part != ''):
            ?>
                            <tr class="subtotal-row">
                                <td colspan="7" class="text-right">Subtotal <?php echo $current_part; ?></td>
                                <td class="text-right">Rp <?php echo number_format($subtotal_part, 2, ',', '.'); ?></td>
                                <td></td>
                            </tr>
            <?php
                        endif;
                        $subtotal_part = 0;
            ?>
                        <tr class="part-header"><td colspan="9">📦 <?php echo $row['PART_CODE'] . " - " . $row['PART_NAME']; ?></td></tr>
            <?php
                        $current_part = $row['PART_CODE'];
                        $no = 1;
                    endif;
                    $subtotal_part += (float)$row['TOTAL_HARGA'];
            ?>
                    <tr>
                        <td class="text-center"><?php echo $no++; ?></td>
                        <td><?php echo $row['PART_CODE']; ?></td>
                        <td>
                            <b><?php echo $row['MAT_CODE']; ?></b>
                            <span class="plant-label">DB: <?php echo $row['PLANT_ASAL']; ?></span>
                        </td>
                        <td><?php echo $row['MAT_NAME']; ?></td>
                        <td class="text-center"><span class="badge badge-<?php echo $row['ITTY_CODE']; ?>"><?php echo $row['ITTY_CODE']; ?></span></td>
                        <td class="text-right"><?php echo number_format((float)$row['BOM_QTY'], 4, ',', '.'); ?></td>
                        <td class="text-right">Rp <?php echo number_format((float)$row['HARGA_PO'], 2, ',', '.'); ?></td>
                        <td class="text-right" style="color:#1E40AF; font-weight:bold;">Rp <?php echo number_format((float)$row['TOTAL_HARGA'], 2, ',', '.'); ?></td>
                        <td class="text-center">
                            <?php if ($row['STATUS_HARGA'] == 'CROSS_PLANT'): ?>
                                <span class="badge badge-cross" title="<?php echo $row['SUMBER_HARGA']; ?>">Cross Plant</span>
                            <?php elseif ($row['STATUS_HARGA'] == 'SUBCON'): ?>
                                <span class="badge badge-subcon">Subcon</span>
                            <?php elseif ($row['STATUS_HARGA'] == 'TANPA_HARGA'): ?>
                                <span class="badge badge-error">Error</span>
                            <?php else: ?>
                                <span class="badge badge-success">OK</span>
                            <?php endif; ?>
                        </td>
                    </tr>
            <?php endforeach; ?>
                <tr class="subtotal-row">
                    <td colspan="7" class="text-right">Subtotal <?php echo $current_part; ?></td>
                    <td class="text-right">Rp <?php echo number_format($subtotal_part, 2, ',', '.'); ?></td>
                    <td></td>
                </tr>
                
                <tr class="grand-total">
                    <td colspan="7" class="text-right">HPP (MODAL) / UNIT</td>
                    <td class="text-right">Rp <?php echo number_format($total_harga_idr, 2, ',', '.'); ?></td>
                    <td></td>
                </tr>
                
                <!-- BLOK PENANDAAN LOSS / PROFIT (MURNI BERDASARKAN BULAN YANG DIPILIH) -->
                <?php if ($qty_sold > 0): ?>
                    <?php 
                        $harga_jual_satuan = $revenue_sold / $qty_sold;
                        $margin_per_unit = $harga_jual_satuan - $total_harga_idr;
                        $is_loss_unit = ($margin_per_unit < 0);
                        
                        $total_cogs = $total_harga_idr * $qty_sold;
                        $gross_profit = $revenue_sold - $total_cogs;
                        $is_loss_total = ($gross_profit < 0);
                    ?>
                    
                    <tr><td colspan="9" style="background:#F3F4F6; height:5px; padding:0;"></td></tr>
                    
                    <tr style="background:#EFF6FF; font-weight:bold; font-size:14px; color:#1E40AF;">
                        <td colspan="7" class="text-right">HARGA JUAL (SALES) / UNIT</td>
                        <td class="text-right">Rp <?php echo number_format($harga_jual_satuan, 2, ',', '.'); ?></td>
                        <td></td>
                    </tr>
                    <tr style="background:<?php echo $is_loss_unit ? '#FEE2E2' : '#D1FAE5'; ?>; color:<?php echo $is_loss_unit ? '#DC2626' : '#065F46'; ?>; font-weight:bold; font-size:14px;">
                        <td colspan="7" class="text-right">MARGIN PER UNIT (SALES - HPP)</td>
                        <td class="text-right">
                            <?php echo $is_loss_unit ? '📉 RUGI' : '📈 UNTUNG'; ?>
                            Rp <?php echo number_format(abs($margin_per_unit), 2, ',', '.'); ?>
                        </td>
                        <td></td>
                    </tr>

                    <tr><td colspan="9" style="background:#F3F4F6; height:15px; padding:0;"></td></tr>

                    <tr class="qty-row">
                        <td colspan="7" class="text-right">TOTAL QTY TERJUAL (BULAN <?php echo $bulan."/".$tahun; ?>)</td>
                        <td class="text-right">x <?php echo number_format($qty_sold, 0, ',', '.'); ?> Unit</td>
                        <td></td>
                    </tr>
                    <tr style="background:<?php echo $is_loss_total ? '#FEE2E2' : '#DBEAFE'; ?>; color:<?php echo $is_loss_total ? '#DC2626' : '#1D4ED8'; ?>; font-weight:bold; font-size:16px;">
                        <td colspan="7" class="text-right">TOTAL STATUS MARGIN KESELURUHAN</td>
                        <td class="text-right">
                            <?php echo $is_loss_total ? '📉 TOTAL RUGI' : '📈 TOTAL UNTUNG'; ?>
                            Rp <?php echo number_format(abs($gross_profit), 2, ',', '.'); ?>
                        </td>
                        <td></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="9" style="background:#FEF3C7; height:5px; padding:0;"></td></tr>
                    <tr style="background:#FFFBEB; font-weight:bold; color:#B45309;">
                        <td colspan="9" class="text-center" style="padding:15px;">
                            Tidak ada data penjualan (Sales) untuk item ini pada bulan <?php echo $bulan."/".$tahun; ?>. <br>
                            <span style="font-weight:normal; font-size:11px;">Silakan ganti periode bulan/tahun di atas jika ingin melihat riwayat penjualan lainnya.</span>
                        </td>
                    </tr>
                <?php endif; ?>

            <?php else: ?>
                <tr><td colspan="9" class="text-center" style="padding: 30px;">Barang Jadi (FG) ini belum memiliki resep BOM (Material Kosong).</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div style="background:white; padding:40px; text-align:center; border-radius:8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-top:20px;">
            <h3 style="color:#6B7280; margin-bottom:10px;">Pilih Item Terlebih Dahulu</h3>
            <p style="color:#9CA3AF; font-size:14px;">Gunakan tombol di laporan P&L Dashboard, atau pilih Part dari menu dropdown di atas untuk melihat rincian BOM dan Margin.</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
<?php
if ($connP1) sqlsrv_close($connP1);
if ($connP2) sqlsrv_close($connP2);
?>
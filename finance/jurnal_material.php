<?php
// =========================================================================
// JURNAL MATERIAL & ANALISIS SELISIH STOK (CROSS-PLANT)
// Kolom 1-4: Stok Awal, Pembelian, Konsumsi, Stok Akhir (Lengkap dengan Tgl)
// Kolom 5: Perhitungan Selisih (Buku vs Fisik)
// Filter: ITEM_CODE NOT LIKE '7%' (Dikecualikan)
// =========================================================================

set_time_limit(300);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['db_user']) || $_SESSION['db_user'] == "") {
    die('<div style="padding:24px;color:#F85149;font-family:sans-serif;">Silakan login terlebih dahulu.</div>');
}

$uid = $_SESSION['db_user'];
$pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "";
$dbName = "msData";

// BUKA KONEKSI KE DUA SERVER (PLANT 1 & PLANT 2)
$connectionOptions = array("Database" => $dbName, "Uid" => $uid, "PWD" => $pwd, "CharacterSet" => "UTF-8");
$connP1 = @sqlsrv_connect("192.168.0.4", $connectionOptions);
$connP2 = @sqlsrv_connect("192.168.0.9", $connectionOptions);

function q($conn, $sql, $params = array()) {
    if (!$conn) return false;
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return false;
    return $stmt;
}

// POLYFILL PHP 5.4 array_column
if (!function_exists('array_column')) {
    function array_column(array $input, $columnKey, $indexKey = null) {
        $array = array();
        foreach ($input as $value) {
            if (!is_array($value)) continue;
            if (is_null($indexKey)) $array[] = $value[$columnKey];
            else $array[$value[$indexKey]] = $value[$columnKey];
        }
        return $array;
    }
}

// FORMAT RUPIAH
function fRp($val) {
    if ($val < 0) return "(Rp " . number_format(abs($val), 0, ',', '.') . ")";
    return "Rp " . number_format($val, 0, ',', '.');
}

// =========================================================================
// 1. MESIN PENCARI HARGA & NAMA MATERIAL CROSS-PLANT
// =========================================================================
function getMaterialInfoMaster($connP1, $connP2) {
    $sql = "
        ;WITH LatestPO AS (
            SELECT pd.ITEM_ID AS MAT_ID, pd.POD_PRICE, po.PO_DATE AS PRICE_DATE_RAW, pd.POD_UNIT, po.PO_CUR,
                   ROW_NUMBER() OVER(PARTITION BY pd.ITEM_ID ORDER BY po.PO_DATE DESC, po.PO_ID DESC) AS rn
            FROM PO_DETAIL pd INNER JOIN PO po ON pd.PO_ID = po.PO_ID
        ),
        PriceData AS (
            SELECT p.MAT_ID, p.POD_PRICE, p.PO_CUR, c.CURR_VRATE,
                CAST(
                    CASE WHEN p.POD_PRICE IS NULL THEN NULL
                         WHEN ISNULL(p.PO_CUR, 'IDR') = 'IDR' THEN p.POD_PRICE
                         WHEN c.CURR_VRATE IS NULL THEN NULL
                         ELSE p.POD_PRICE * c.CURR_VRATE
                    END AS DECIMAL(38, 8)
                ) AS PRICE_IDR
            FROM LatestPO p
            LEFT JOIN CURR_RAT c ON p.PO_CUR = c.CURR_CODE AND p.PRICE_DATE_RAW BETWEEN c.CURR_SDATE AND ISNULL(c.CURR_EDATE, p.PRICE_DATE_RAW)
            WHERE p.rn = 1
        )
        SELECT m.ITEM_CODE AS MAT_CODE, m.ITEM_NAME AS MAT_NAME, ISNULL(pd.PRICE_IDR, 0) AS HARGA_IDR
        FROM ITEMS m LEFT JOIN PriceData pd ON m.ITEM_ID = pd.MAT_ID
        WHERE m.ITEM_INACTIVE = 0 AND m.ITTY_CODE <> '01' AND m.ITEM_CODE NOT LIKE '7%'
    ";

    $mapInfo = array();
    if ($connP1) {
        $stmt = q($connP1, $sql);
        if ($stmt) { 
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { 
                $mapInfo['P1_' . trim($row['MAT_CODE'])] = array('HARGA' => (float)$row['HARGA_IDR'], 'NAMA' => trim($row['MAT_NAME'])); 
            } 
            sqlsrv_free_stmt($stmt); 
        }
    }
    if ($connP2) {
        $stmt = q($connP2, $sql);
        if ($stmt) { 
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { 
                $mapInfo['P2_' . trim($row['MAT_CODE'])] = array('HARGA' => (float)$row['HARGA_IDR'], 'NAMA' => trim($row['MAT_NAME'])); 
            } 
            sqlsrv_free_stmt($stmt); 
        }
    }
    return $mapInfo;
}

$GLOBAL_MAP_INFO = getMaterialInfoMaster($connP1, $connP2);

function cariInfoCrossPlant($matCode) {
    global $GLOBAL_MAP_INFO;
    $code = trim($matCode);
    $res = array('HARGA' => 0, 'NAMA' => '-');
    
    if (isset($GLOBAL_MAP_INFO['P1_' . $code])) {
        $res['NAMA'] = $GLOBAL_MAP_INFO['P1_' . $code]['NAMA'];
        if ($GLOBAL_MAP_INFO['P1_' . $code]['HARGA'] > 0) $res['HARGA'] = $GLOBAL_MAP_INFO['P1_' . $code]['HARGA'];
    }
    if ($res['HARGA'] == 0 && isset($GLOBAL_MAP_INFO['P2_' . $code])) {
        $res['NAMA'] = $GLOBAL_MAP_INFO['P2_' . $code]['NAMA'];
        if ($GLOBAL_MAP_INFO['P2_' . $code]['HARGA'] > 0) $res['HARGA'] = $GLOBAL_MAP_INFO['P2_' . $code]['HARGA'];
    }
    return $res;
}

function cariHargaCrossPlant($matCode) {
    $info = cariInfoCrossPlant($matCode);
    return $info['HARGA'];
}

// =========================================================================
// 2. SETUP PERIODE
// =========================================================================
$bulan = isset($_GET['bulan']) ? str_pad($_GET['bulan'], 2, '0', STR_PAD_LEFT) : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

$tglAwal = "$tahun-$bulan-01";
$tglAkhir = date("Y-m-t", strtotime($tglAwal)); 
$tglAwalBulanDepan = date('Y-m-d', strtotime('+1 month', strtotime($tglAwal)));

$tglAwalTime = $tglAwal . " 00:00:00";
$tglAkhirTime = $tglAkhir . " 23:59:59";

function fetchDetail($conn, $sql, $params = array(), $plantName = "P1") {
    $results = array();
    if (!$conn) return $results;
    $stmt = @sqlsrv_query($conn, $sql, $params);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $row['PLANT'] = $plantName;
            $results[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    return $results;
}

// =========================================================================
// 3. AMBIL DATA (STOK AWAL, BELI, KONSUMSI, STOK AKHIR)
// =========================================================================

// A. STOK OPNAME (AWAL & AKHIR) - Kecualikan Kode 7%
function fetchStokMat($conn, $tglOpname, $plantName) {
    $results = array();
    if (!$conn) return $results;
    $sql = "
        SELECT CONVERT(VARCHAR(10), s.SOP_SDATE, 120) AS TANGGAL, i.ITEM_CODE, SUM(t.TAG_QTY) AS QTY
        FROM SOP s INNER JOIN TAGS t ON s.SOP_ID = t.SOP_ID INNER JOIN ITEMS i ON t.ITEM_ID = i.ITEM_ID
        WHERE i.ITTY_CODE <> '01' AND i.ITEM_CODE NOT LIKE '7%' AND CONVERT(VARCHAR(10), s.SOP_SDATE, 120) = ?
        GROUP BY s.SOP_SDATE, i.ITEM_CODE
    ";
    $stmt = @sqlsrv_query($conn, $sql, [$tglOpname]);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $itemCode = trim($row['ITEM_CODE']);
            $qty = (float)$row['QTY'];
            $hargaMaster = cariHargaCrossPlant($itemCode);
            $results[] = array('PLANT' => $plantName, 'TANGGAL' => $row['TANGGAL'], 'ITEM_CODE' => $itemCode, 'QTY' => $qty, 'TOTAL_IDR' => $qty * $hargaMaster);
        }
        sqlsrv_free_stmt($stmt);
    }
    return $results;
}

$dtMatAwal = array_merge(fetchStokMat($connP1, $tglAwal, 'P1'), fetchStokMat($connP2, $tglAwal, 'P2'));
$dtMatAkhir = array_merge(fetchStokMat($connP1, $tglAwalBulanDepan, 'P1'), fetchStokMat($connP2, $tglAwalBulanDepan, 'P2'));

// B. PEMBELIAN (RECEIVE) - Kecualikan Kode 7%
$sqlReceive = "
    SELECT CONVERT(VARCHAR(10), r.RCV_DATE, 120) AS TANGGAL, m.ITEM_CODE, rd.RCVD_QTY AS QTY,
           (rd.RCVD_QTY * rd.POD_PRICE * CASE WHEN ISNULL(po.PO_CUR, 'IDR') IN ('IDR','RP') THEN 1 ELSE ISNULL(c.CURR_VRATE, 1) END) AS TOTAL_IDR
    FROM RECEIVE r INNER JOIN RECEIVE_DETAIL rd ON r.RCV_ID = rd.RCV_ID INNER JOIN PO po ON rd.PO_ID = po.PO_ID INNER JOIN ITEMS m ON rd.ITEM_ID = m.ITEM_ID
    LEFT JOIN CURR_RAT c ON po.PO_CUR = c.CURR_CODE AND r.RCV_DATE BETWEEN c.CURR_SDATE AND ISNULL(c.CURR_EDATE, r.RCV_DATE)
    WHERE r.RCV_TYPE = 1 AND r.RCV_DATE >= ? AND r.RCV_DATE <= ? AND m.ITEM_CODE NOT LIKE '7%'
";
$dtRecv = array_merge(fetchDetail($connP1, $sqlReceive, [$tglAwalTime, $tglAkhirTime], 'P1'), fetchDetail($connP2, $sqlReceive, [$tglAwalTime, $tglAkhirTime], 'P2'));

// C. KONSUMSI (TALLY) - Kecualikan Kode awalan 7 di loop
function fetchConsumptMat($conn, $start, $end, $plantName) {
    $results = array();
    if (!$conn) return $results;
    $stmt = @sqlsrv_query($conn, "EXECUTE sp_GenerateTallyConsumtion ?, ?", array($start, $end));
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rl = array_change_key_case($row, CASE_LOWER);
            $itemCode = isset($rl['item_material']) ? trim($rl['item_material']) : '';
            
            // Skip item code yang berawalan angka 7
            if (strpos($itemCode, '7') === 0) continue;
            
            $qty = isset($rl['cons_qty']) ? (float)$rl['cons_qty'] : 0;
            $hargaMaster = cariHargaCrossPlant($itemCode);
            $results[] = array('PLANT' => $plantName, 'TANGGAL' => isset($rl['date']) ? $rl['date'] : '', 'ITEM_CODE' => $itemCode, 'QTY' => $qty, 'TOTAL_IDR' => $qty * $hargaMaster);
        }
        sqlsrv_free_stmt($stmt);
    }
    return $results;
}
$dtConsumpt = array_merge(fetchConsumptMat($connP1, $tglAwal, $tglAkhir, 'P1'), fetchConsumptMat($connP2, $tglAwal, $tglAkhir, 'P2'));

// =========================================================================
// 4. SUSUN KARTU JURNAL MATERIAL
// =========================================================================
$jurnal = array();

function initJurnal(&$arr, $key, $plant, $code) {
    $info = cariInfoCrossPlant($code);
    $arr[$key] = array(
        'PLANT' => $plant, 'ITEM_CODE' => $code, 'ITEM_NAME' => $info['NAMA'],
        'AWAL_QTY' => 0, 'AWAL_RP' => 0, 'AWAL_TGL' => '-',
        'BELI_QTY' => 0, 'BELI_RP' => 0, 'BELI_TGL_ARR' => array(),
        'KONS_QTY' => 0, 'KONS_RP' => 0, 'KONS_TGL_ARR' => array(),
        'AKHIR_QTY' => 0, 'AKHIR_RP' => 0, 'AKHIR_TGL' => '-'
    );
}

// 1. Stok Awal
foreach ($dtMatAwal as $r) {
    $key = $r['PLANT'] . '_' . $r['ITEM_CODE'];
    if (!isset($jurnal[$key])) initJurnal($jurnal, $key, $r['PLANT'], $r['ITEM_CODE']);
    $jurnal[$key]['AWAL_QTY'] += $r['QTY'];
    $jurnal[$key]['AWAL_RP'] += $r['TOTAL_IDR'];
    $jurnal[$key]['AWAL_TGL'] = $r['TANGGAL'];
}

// 2. Pembelian
foreach ($dtRecv as $r) {
    $key = $r['PLANT'] . '_' . $r['ITEM_CODE'];
    if (!isset($jurnal[$key])) initJurnal($jurnal, $key, $r['PLANT'], $r['ITEM_CODE']);
    $jurnal[$key]['BELI_QTY'] += $r['QTY'];
    $jurnal[$key]['BELI_RP'] += $r['TOTAL_IDR'];
    $tglStr = date('d/m/y', strtotime($r['TANGGAL']));
    if (!in_array($tglStr, $jurnal[$key]['BELI_TGL_ARR'])) $jurnal[$key]['BELI_TGL_ARR'][] = $tglStr;
}

// 3. Konsumsi
foreach ($dtConsumpt as $r) {
    $key = $r['PLANT'] . '_' . $r['ITEM_CODE'];
    if (!isset($jurnal[$key])) initJurnal($jurnal, $key, $r['PLANT'], $r['ITEM_CODE']);
    $jurnal[$key]['KONS_QTY'] += $r['QTY'];
    $jurnal[$key]['KONS_RP'] += $r['TOTAL_IDR'];
    $tglStr = is_string($r['TANGGAL']) ? date('d/m/y', strtotime($r['TANGGAL'])) : (is_object($r['TANGGAL']) ? $r['TANGGAL']->format('d/m/y') : '');
    if ($tglStr != '' && !in_array($tglStr, $jurnal[$key]['KONS_TGL_ARR'])) $jurnal[$key]['KONS_TGL_ARR'][] = $tglStr;
}

// 4. Stok Akhir
foreach ($dtMatAkhir as $r) {
    $key = $r['PLANT'] . '_' . $r['ITEM_CODE'];
    if (!isset($jurnal[$key])) initJurnal($jurnal, $key, $r['PLANT'], $r['ITEM_CODE']);
    $jurnal[$key]['AKHIR_QTY'] += $r['QTY'];
    $jurnal[$key]['AKHIR_RP'] += $r['TOTAL_IDR'];
    $jurnal[$key]['AKHIR_TGL'] = $r['TANGGAL'];
}

// Kalkulasi Selisih (Perhitungan)
foreach ($jurnal as &$j) {
    $j['BELI_TGL'] = count($j['BELI_TGL_ARR']) > 0 ? implode(", ", $j['BELI_TGL_ARR']) : '-';
    $j['KONS_TGL'] = count($j['KONS_TGL_ARR']) > 0 ? implode(", ", $j['KONS_TGL_ARR']) : '-';

    // Saldo Buku = Awal + Pembelian - Konsumsi
    $j['BUKU_QTY'] = $j['AWAL_QTY'] + $j['BELI_QTY'] - $j['KONS_QTY'];
    $j['BUKU_RP']  = $j['AWAL_RP'] + $j['BELI_RP'] - $j['KONS_RP'];

    // Selisih = Fisik (Akhir) - Buku
    $j['SELISIH_QTY'] = $j['AKHIR_QTY'] - $j['BUKU_QTY'];
    $j['SELISIH_RP']  = $j['AKHIR_RP'] - $j['BUKU_RP'];
}
unset($j);

// Sorting Berdasarkan ITEM_CODE (Ascending)
usort($jurnal, function($a, $b) {
    return strcmp($a['ITEM_CODE'], $b['ITEM_CODE']);
});

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jurnal Material & Analisis Selisih</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'DM Sans', sans-serif; background: #F3F4F6; padding: 20px; color: #1F2937; margin:0;}
        .card { background: #FFF; border-radius: 10px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 20px; }
        h2 { margin-top:0; color: #2563EB; font-size: 22px; }
        
        .filter-row { display: flex; gap: 10px; background: #F9FAFB; padding: 15px; border-radius: 8px; border: 1px solid #E5E7EB; margin-bottom: 20px; flex-wrap: wrap;}
        .form-group { display: flex; flex-direction: column; gap: 4px; }
        .form-group label { font-size: 11px; font-weight: 700; color: #4B5563; text-transform: uppercase;}
        .form-group input { padding: 8px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; }
        .btn-submit { background: #2563EB; color: white; border: none; padding: 0 20px; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top:18px;}
        .btn-back { background: #6B7280; color: white; text-decoration:none; padding: 10px 20px; border-radius: 6px; font-weight: bold; font-size: 13px; margin-top:18px; display:inline-block;}

        .data-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .data-table th { background: #1E3A8A; color:#FFF; padding: 12px; text-align: left; border: 1px solid #1E3A8A; }
        .data-table td { padding: 10px; border: 1px solid #E5E7EB; vertical-align: top;}
        .data-table tr:nth-child(even) { background: #F9FAFB; }
        
        .badge-p1 { background: #DBEAFE; color: #1E40AF; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-p2 { background: #FEF3C7; color: #991B1B; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        
        .val-right { text-align: right; }
        .info-label { font-size: 10px; color: #6B7280; font-weight:bold; text-transform: uppercase; margin-top:5px; display:block;}
        .info-val { font-size: 13px; font-weight:bold; color: #111827;}
        .info-date { font-size: 11px; color: #3B82F6; background:#EFF6FF; padding:2px 5px; border-radius:4px; display:block; max-height:45px; overflow-y:auto; line-height:1.4; border:1px solid #BFDBFE;}
        
        .status-match { color: #059669; font-weight: bold; background:#D1FAE5; padding:3px 8px; border-radius:4px; display:inline-block;}
        .status-surplus { color: #D97706; font-weight: bold; background:#FEF3C7; padding:3px 8px; border-radius:4px; display:inline-block;}
        .status-minus { color: #DC2626; font-weight: bold; background:#FEE2E2; padding:3px 8px; border-radius:4px; display:inline-block;}

        .col-calc { background: #EEF2FF; border-left: 2px solid #93C5FD !important;}
        .formula-box { background: #EFF6FF; border-left: 4px solid #3B82F6; padding: 10px 15px; margin-bottom: 20px; font-size: 13px; color: #1E40AF; }
    </style>
</head>
<body>

<div class="card">
    <h2><i class="fas fa-book"></i> Jurnal Material & Analisis Selisih Stok</h2>
    
    <form method="GET" class="filter-row no-print">
        <div class="form-group"><label>Bulan</label><input type="number" name="bulan" min="1" max="12" value="<?php echo (int)$bulan; ?>"></div>
        <div class="form-group"><label>Tahun</label><input type="number" name="tahun" value="<?php echo $tahun; ?>"></div>
        <button type="submit" class="btn-submit"><i class="fas fa-search"></i> Proses Jurnal</button>
        <a href="laporan_laba_rugi.php?bulan=<?php echo (int)$bulan; ?>&tahun=<?php echo $tahun; ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke P&L</a>
    </form>

    <div class="formula-box">
        <strong>Rumus Selisih:</strong><br>
        1. <strong>Saldo Buku (Sistem)</strong> = Stok Awal + Pembelian - Konsumsi<br>
        2. <strong>Selisih</strong> = Stok Akhir (Fisik) - Saldo Buku<br>
        <em>* Jika Selisih Minus (-), berarti fisik barang kurang (hilang/susut). Jika Surplus (+), berarti fisik barang lebih banyak dari catatan.</em>
    </div>

    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th width="3%">No</th>
                    <th width="15%">Plant & Material</th>
                    <th width="13%">Kolom 1:<br>STOK AWAL</th>
                    <th width="13%">Kolom 2:<br>PEMBELIAN</th>
                    <th width="13%">Kolom 3:<br>KONSUMSI</th>
                    <th width="13%">Kolom 4:<br>STOK AKHIR</th>
                    <th width="30%" class="col-calc">Kolom 5:<br>PERHITUNGAN (SELISIH)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                foreach($jurnal as $r): 
                    $statClass = "status-match";
                    $statText  = "MATCH / SESUAI";
                    
                    if ($r['SELISIH_QTY'] > 0.0001) {
                        $statClass = "status-surplus";
                        $statText  = "SURPLUS (FISIK LEBIH)";
                    } elseif ($r['SELISIH_QTY'] < -0.0001) {
                        $statClass = "status-minus";
                        $statText  = "MINUS (FISIK KURANG)";
                    }
                ?>
                <tr>
                    <td class="val-right"><?php echo $no++; ?></td>
                    <td>
                        <span class="badge-<?php echo strtolower($r['PLANT']); ?>"><?php echo $r['PLANT']; ?></span>
                        <div style="margin-top:8px; font-weight:bold; font-size:14px; color:#1E3A8A;"><?php echo $r['ITEM_CODE']; ?></div>
                        <div style="margin-top:4px; font-size:11px; color:#4B5563; line-height:1.3;"><?php echo $r['ITEM_NAME']; ?></div>
                    </td>

                    <!-- KOLOM 1: STOK AWAL -->
                    <td>
                        <span class="info-label">Tgl Opname Awal:</span>
                        <div class="info-date"><?php echo $r['AWAL_TGL'] != '-' ? date('d-M-Y', strtotime($r['AWAL_TGL'])) : '-'; ?></div>
                        <span class="info-label">Qty:</span>
                        <div class="info-val"><?php echo number_format($r['AWAL_QTY'], 2, ',', '.'); ?></div>
                        <span class="info-label">Rupiah:</span>
                        <div class="info-val"><?php echo fRp($r['AWAL_RP']); ?></div>
                    </td>

                    <!-- KOLOM 2: PEMBELIAN -->
                    <td>
                        <span class="info-label">Rincian Tgl Masuk:</span>
                        <div class="info-date"><?php echo $r['BELI_TGL']; ?></div>
                        <span class="info-label">Total Qty:</span>
                        <div class="info-val" style="color:#059669;"><?php echo number_format($r['BELI_QTY'], 2, ',', '.'); ?></div>
                        <span class="info-label">Total Rupiah:</span>
                        <div class="info-val" style="color:#059669;"><?php echo fRp($r['BELI_RP']); ?></div>
                    </td>

                    <!-- KOLOM 3: KONSUMSI -->
                    <td>
                        <span class="info-label">Rincian Tgl Pakai:</span>
                        <div class="info-date"><?php echo $r['KONS_TGL']; ?></div>
                        <span class="info-label">Total Qty:</span>
                        <div class="info-val" style="color:#DC2626;"><?php echo number_format($r['KONS_QTY'], 2, ',', '.'); ?></div>
                        <span class="info-label">Total Rupiah:</span>
                        <div class="info-val" style="color:#DC2626;"><?php echo fRp($r['KONS_RP']); ?></div>
                    </td>

                    <!-- KOLOM 4: STOK AKHIR -->
                    <td>
                        <span class="info-label">Tgl Opname Akhir:</span>
                        <div class="info-date"><?php echo $r['AKHIR_TGL'] != '-' ? date('d-M-Y', strtotime($r['AKHIR_TGL'])) : '-'; ?></div>
                        <span class="info-label">Qty Fisik:</span>
                        <div class="info-val" style="color:#1E40AF;"><?php echo number_format($r['AKHIR_QTY'], 2, ',', '.'); ?></div>
                        <span class="info-label">Rupiah Fisik:</span>
                        <div class="info-val" style="color:#1E40AF;"><?php echo fRp($r['AKHIR_RP']); ?></div>
                    </td>

                    <!-- KOLOM 5: PERHITUNGAN -->
                    <td class="col-calc">
                        <table style="width:100%; border:none; margin:0; padding:0;">
                            <tr>
                                <td style="border:none; padding:2px; width:50%;">
                                    <span class="info-label">Catatan Buku (Sistem)</span>
                                    <div>Qty: <strong><?php echo number_format($r['BUKU_QTY'], 2, ',', '.'); ?></strong></div>
                                    <div>Rp: <strong><?php echo fRp($r['BUKU_RP']); ?></strong></div>
                                </td>
                                <td style="border:none; padding:2px; width:50%;">
                                    <span class="info-label">Selisih Aktual</span>
                                    <div style="color:<?php echo $r['SELISIH_QTY'] < 0 ? '#DC2626' : ($r['SELISIH_QTY'] > 0 ? '#D97706' : '#059669'); ?>;">
                                        Qty: <strong><?php echo number_format($r['SELISIH_QTY'], 2, ',', '.'); ?></strong>
                                    </div>
                                    <div style="color:<?php echo $r['SELISIH_RP'] < 0 ? '#DC2626' : ($r['SELISIH_RP'] > 0 ? '#D97706' : '#059669'); ?>;">
                                        Rp: <strong><?php echo fRp($r['SELISIH_RP']); ?></strong>
                                    </div>
                                </td>
                            </tr>
                        </table>
                        <div style="margin-top:10px; text-align:center;">
                            <span class="<?php echo $statClass; ?>"><i class="fas fa-info-circle"></i> <?php echo $statText; ?></span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if(count($jurnal) == 0): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:20px; font-weight:bold; color:#6B7280;">Tidak ada pergerakan material di periode ini.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
<?php
if ($connP1) sqlsrv_close($connP1);
if ($connP2) sqlsrv_close($connP2);
?>
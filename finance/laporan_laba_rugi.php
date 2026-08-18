<?php
// =========================================================================
// DASHBOARD TRUE P&L (PROFIT & LOSS) MANUFAKTUR P1 & P2
// =========================================================================

set_time_limit(300);
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// KONEKSI DATABASE DUAL SERVER (PLANT 1 & PLANT 2)
$uid = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : "sa";
$pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "password_anda";
$dbName = "msData";

$connP1 = @sqlsrv_connect("192.168.0.4", array("Database" => $dbName, "Uid" => $uid, "PWD" => $pwd, "CharacterSet" => "UTF-8"));
$connP2 = @sqlsrv_connect("192.168.0.9", array("Database" => $dbName, "Uid" => $uid, "PWD" => $pwd, "CharacterSet" => "UTF-8"));

if (!function_exists('array_column')) {
    function array_column(array $input, $columnKey) {
        $array = array(); 
        foreach ($input as $value) { $array[] = $value[$columnKey]; } 
        return $array;
    }
}

function q($conn, $sql, $params = array()) {
    if (!$conn) return false;
    return sqlsrv_query($conn, $sql, $params);
}

function fetchAll($conn, $sql, $params = array(), $plantName = "P1") {
    $results = array();
    if (!$conn) return $results;
    $stmt = q($conn, $sql, $params);
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
// 1. ENGINE HPP (MASTER HARGA & NAMA MATERIAL)
// =========================================================================
$GLOBAL_MAP_MAT = array();

function getMaterialHargaMaster($connP1, $connP2) {
    global $GLOBAL_MAP_MAT;
    $sql = "
        ;WITH LatestPO AS (
            SELECT pd.ITEM_ID AS MAT_ID, pd.POD_PRICE, po.PO_DATE AS PRICE_DATE_RAW, pd.POD_UNIT, po.PO_CUR,
                   ROW_NUMBER() OVER(PARTITION BY pd.ITEM_ID ORDER BY po.PO_DATE DESC, po.PO_ID DESC) AS rn
            FROM PO_DETAIL pd INNER JOIN PO po ON pd.PO_ID = po.PO_ID
        ),
        PriceData AS (
            SELECT p.MAT_ID, p.POD_PRICE, p.PRICE_DATE_RAW, p.POD_UNIT, p.PO_CUR, c.CURR_VRATE,
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
        ),
        HppBomTree AS (
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
        Hpp01Summary AS (
            SELECT bt.ROOT_PART_ID,
                CAST(SUM(
                    CASE WHEN bt.ITTY_CODE = '02' THEN ROUND(((bt.TOTAL_QTY * ISNULL(pd.PRICE_IDR, 0)) / 1000.0), 2)
                         WHEN bt.ITTY_CODE = '03' THEN ROUND((bt.TOTAL_QTY * ISNULL(pd.PRICE_IDR, 0)), 2)
                         ELSE 0 END
                ) AS DECIMAL(38, 8)) AS HPP_IDR_PER_UNIT
            FROM HppBomTree bt LEFT JOIN PriceData pd ON bt.COMPONENT_ID = pd.MAT_ID
            WHERE bt.ITTY_CODE IN ('02', '03')
            GROUP BY bt.ROOT_PART_ID
        )
        SELECT m.ITEM_ID AS MAT_ID, m.ITEM_CODE AS MAT_CODE, m.ITEM_NAME AS MAT_NAME,
               CAST(
                   CASE WHEN m.ITTY_CODE = '01' THEN ISNULL(h.HPP_IDR_PER_UNIT, 0)
                        ELSE ISNULL(pd.PRICE_IDR, 0)
                   END AS DECIMAL(38, 8)
               ) AS HARGA_IDR
        FROM ITEMS m
        LEFT JOIN PriceData pd ON m.ITEM_ID = pd.MAT_ID
        LEFT JOIN Hpp01Summary h ON m.ITEM_ID = h.ROOT_PART_ID
        WHERE m.ITEM_INACTIVE = 0
        OPTION (MAXRECURSION 100)
    ";

    $d1 = fetchAll($connP1, $sql, array(), 'P1'); 
    foreach ($d1 as $r) {
        $GLOBAL_MAP_MAT['P1_'.trim($r['MAT_CODE'])] = array('HPP' => (float)$r['HARGA_IDR'], 'NAME' => trim($r['MAT_NAME']));
    }
    
    $d2 = fetchAll($connP2, $sql, array(), 'P2'); 
    foreach ($d2 as $r) {
        $GLOBAL_MAP_MAT['P2_'.trim($r['MAT_CODE'])] = array('HPP' => (float)$r['HARGA_IDR'], 'NAME' => trim($r['MAT_NAME']));
    }
}
getMaterialHargaMaster($connP1, $connP2);

function getHpp($matCode) {
    global $GLOBAL_MAP_MAT; 
    $code = trim($matCode);
    if (isset($GLOBAL_MAP_MAT['P1_' . $code]) && $GLOBAL_MAP_MAT['P1_' . $code]['HPP'] > 0) return $GLOBAL_MAP_MAT['P1_' . $code]['HPP'];
    if (isset($GLOBAL_MAP_MAT['P2_' . $code]) && $GLOBAL_MAP_MAT['P2_' . $code]['HPP'] > 0) return $GLOBAL_MAP_MAT['P2_' . $code]['HPP'];
    return 0;
}

// =========================================================================
// 2. SETUP PERIODE & VARIABEL
// =========================================================================
$bulan = isset($_GET['bulan']) ? str_pad($_GET['bulan'], 2, '0', STR_PAD_LEFT) : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// TANGGAL AWAL = TGL 1 BULAN INI (Untuk Saldo Awal)
$tglAwal = "$tahun-$bulan-01";
$tglAkhir = date("Y-m-t", strtotime($tglAwal)); 

// TANGGAL OPNAME = TGL 1 BULAN BERIKUTNYA (Untuk Stok Akhir Fisik)
$tglOpname = date("Y-m-01", strtotime("+1 month", strtotime($tglAwal)));

$tAwalT = $tglAwal . " 00:00:00";
$tAkhirT = $tglAkhir . " 23:59:59";

$biayaGaji = isset($_GET['gaji']) ? (float)$_GET['gaji'] : 0;
$biayaListrik = isset($_GET['listrik']) ? (float)$_GET['listrik'] : 0;
$biayaOpr = isset($_GET['operasional']) ? (float)$_GET['operasional'] : 0;

// =========================================================================
// 3. TARIK DATA SALES (PENJUALAN)
// =========================================================================
$sqlSales = "
    SELECT i.ITEM_CODE, i.ITEM_NAME, SUM(DIPA_PAR.QTY) AS QTY_SOLD,
           SUM(DIPA_PAR.QTY * DIPA_PAR.PART_PRICE * CASE WHEN ISNULL(APV.CURR_CODE, 'IDR') IN ('IDR', 'RP') THEN 1 ELSE ISNULL(RV.CURR_VRATE, 1) END) AS REVENUE
    FROM DI
    INNER JOIN DIPA_PAR ON DI.DI_ID = DIPA_PAR.DI_ID
    INNER JOIN PRICE ON DIPA_PAR.PRICE_ID = PRICE.PRICE_ID
    INNER JOIN ITEMS i ON PRICE.PART_ID = i.ITEM_ID
    LEFT JOIN ACTIVE_PRICE_VIEW AS APV ON PRICE.PRICE_ID = APV.PRICE_ID
    LEFT JOIN CURR_RAT AS RV ON APV.CURR_CODE = RV.CURR_CODE AND DI.DI_DATE BETWEEN RV.CURR_SDATE AND ISNULL(RV.CURR_EDATE, DI.DI_DATE)
    WHERE DI.DI_DATE >= ? AND DI.DI_DATE <= ?
    GROUP BY i.ITEM_CODE, i.ITEM_NAME
";
$dtSales = array_merge(fetchAll($connP1, $sqlSales, array($tAwalT, $tAkhirT), 'P1'), fetchAll($connP2, $sqlSales, array($tAwalT, $tAkhirT), 'P2'));

$totalRevenue = 0; $totalStdCogs = 0;
$salesList = array();
foreach ($dtSales as $r) {
    $code = trim($r['ITEM_CODE']);
    $qty = (float)$r['QTY_SOLD'];
    $rev = (float)$r['REVENUE'];
    $hpp = getHpp($code);
    $cogs = $qty * $hpp; 

    $totalRevenue += $rev;
    $totalStdCogs += $cogs;
    
    $salesList[] = array(
        'PLANT' => $r['PLANT'], 'CODE' => $code, 'NAME' => $r['ITEM_NAME'], 
        'QTY' => $qty, 'REVENUE' => $rev, 'HPP' => $hpp, 'COGS' => $cogs,
        'PROFIT' => $rev - $cogs, 'MARGIN' => ($rev>0) ? (($rev-$cogs)/$rev)*100 : 0
    );
}

// =========================================================================
// 4. TARIK DATA BIAYA VENDOR (RCV_TYPE = 3)
// =========================================================================
$sqlVendorSubcon = "
    SELECT ISNULL(SUM(
        rd.RCVD_QTY * rd.POD_PRICE * 
        CASE WHEN ISNULL(po.PO_CUR, 'IDR') IN ('IDR', 'RP') THEN 1 
             ELSE ISNULL(cr.CURR_VRATE, 1) END
    ), 0) AS TOTAL_VENDOR_COST
    FROM RECEIVE r
    INNER JOIN RECEIVE_DETAIL rd ON r.RCV_ID = rd.RCV_ID
    INNER JOIN PO po ON rd.PO_ID = po.PO_ID
    LEFT JOIN CURR_RAT cr ON po.PO_CUR = cr.CURR_CODE AND r.RCV_DATE BETWEEN cr.CURR_SDATE AND ISNULL(cr.CURR_EDATE, r.RCV_DATE)
    WHERE r.RCV_TYPE = 3 
      AND r.RCV_DATE >= ? AND r.RCV_DATE <= ?
";
$vendorP1 = fetchAll($connP1, $sqlVendorSubcon, array($tAwalT, $tAkhirT));
$vendorP2 = fetchAll($connP2, $sqlVendorSubcon, array($tAwalT, $tAkhirT));
$totalBiayaVendor = (isset($vendorP1[0]['TOTAL_VENDOR_COST']) ? (float)$vendorP1[0]['TOTAL_VENDOR_COST'] : 0) + 
                    (isset($vendorP2[0]['TOTAL_VENDOR_COST']) ? (float)$vendorP2[0]['TOTAL_VENDOR_COST'] : 0);

$sqlVendorDetail = "
    SELECT r.RCV_NO, r.RCV_DATE, po.PO_NUM, i.ITEM_CODE, i.ITEM_NAME,
           rd.RCVD_QTY, rd.POD_PRICE, po.PO_CUR,
           ISNULL(cr.CURR_VRATE, 1) AS KURS,
           (rd.RCVD_QTY * rd.POD_PRICE * 
            CASE WHEN ISNULL(po.PO_CUR, 'IDR') IN ('IDR', 'RP') THEN 1 
                 ELSE ISNULL(cr.CURR_VRATE, 1) END
           ) AS TOTAL_IDR
    FROM RECEIVE r
    INNER JOIN RECEIVE_DETAIL rd ON r.RCV_ID = rd.RCV_ID
    INNER JOIN PO po ON rd.PO_ID = po.PO_ID
    INNER JOIN ITEMS i ON rd.ITEM_ID = i.ITEM_ID
    LEFT JOIN CURR_RAT cr ON po.PO_CUR = cr.CURR_CODE AND r.RCV_DATE BETWEEN cr.CURR_SDATE AND ISNULL(cr.CURR_EDATE, r.RCV_DATE)
    WHERE r.RCV_TYPE = 3 
      AND r.RCV_DATE >= ? AND r.RCV_DATE <= ?
    ORDER BY r.RCV_DATE DESC
";
$vendorDetailP1 = fetchAll($connP1, $sqlVendorDetail, array($tAwalT, $tAkhirT), 'P1');
$vendorDetailP2 = fetchAll($connP2, $sqlVendorDetail, array($tAwalT, $tAkhirT), 'P2');
$vendorDetailList = array_merge($vendorDetailP1, $vendorDetailP2);


// =========================================================================
// 5. TARIK DATA MUTASI SP & TAG FISIK
// =========================================================================

function fetchMutasiSP($conn, $tAwal, $tAkhir) {
    $res = array();
    if (!$conn) return $res;
    
    $sql = "{CALL dbo.sp_laporan_mutasi_bahanbaku1(?, ?)}";
    $stmt = sqlsrv_query($conn, $sql, array($tAwal, $tAkhir));
    
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if (isset($row["KODE_BARANG"])) {
                $code = trim($row["KODE_BARANG"]);
                if ($code != "") {
                    if (!isset($res[$code])) {
                        $res[$code] = $row;
                        $res[$code]['MASUK'] = (float)$row['MASUK'];
                        $res[$code]['KELUAR'] = (float)$row['KELUAR'];
                    } else {
                        $res[$code]['MASUK'] += (float)$row['MASUK'];
                        $res[$code]['KELUAR'] += (float)$row['KELUAR'];
                    }
                }
            }
        }
        sqlsrv_free_stmt($stmt);
    }
    return $res;
}

// FUNGSI TARIK TAG (Berlaku untuk Saldo Awal & Saldo Akhir)
function fetchTagData($conn, $targetDate) {
    $res = array();
    if (!$conn) return $res;
    
    $sql = "
        SELECT i.ITEM_CODE, SUM(TAGS.TAG_QTY) AS STOK
        FROM TAGS 
        INNER JOIN SOP ON TAGS.SOP_ID = SOP.SOP_ID
        INNER JOIN ITEMS i ON TAGS.ITEM_ID = i.ITEM_ID
        WHERE CAST(SOP.SOP_SDATE AS DATE) = ?
        GROUP BY i.ITEM_CODE
    ";
    $stmt = sqlsrv_query($conn, $sql, array($targetDate));
    
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if (isset($row["ITEM_CODE"])) {
                $res[trim($row["ITEM_CODE"])] = (float)$row['STOK'];
            }
        }
        sqlsrv_free_stmt($stmt);
    }
    return $res;
}

// 1. Tarik Data Mutasi In / Out
$mutasiP1 = fetchMutasiSP($connP1, $tglAwal, $tglAkhir);
$mutasiP2 = fetchMutasiSP($connP2, $tglAwal, $tglAkhir);

// 2. Tarik Data Tag Tgl 1 Bulan Ini (Untuk Saldo Awal)
$tagAwalP1 = fetchTagData($connP1, $tglAwal);
$tagAwalP2 = fetchTagData($connP2, $tglAwal);

// 3. Tarik Data Tag Tgl 1 Bulan Depan (Untuk Stok Akhir Fisik)
$tagAkhirP1 = fetchTagData($connP1, $tglOpname);
$tagAkhirP2 = fetchTagData($connP2, $tglOpname);

$matFlowList = array();
$totalMaterialLoss = 0;

$allMatKeys = array_unique(array_merge(
    array_keys($mutasiP1), array_keys($mutasiP2),
    array_keys($tagAwalP1), array_keys($tagAwalP2),
    array_keys($tagAkhirP1), array_keys($tagAkhirP2)
));

foreach ($allMatKeys as $code) {
    $hpp = getHpp($code);
    
    $m1 = isset($mutasiP1[$code]) ? $mutasiP1[$code] : null;
    $m2 = isset($mutasiP2[$code]) ? $mutasiP2[$code] : null;
    
    $plantSource = "P1 & P2"; 
    // Simplified plant detection
    if (!$m1 && !$m2) {
        if((isset($tagAwalP1[$code]) || isset($tagAkhirP1[$code])) && !(isset($tagAwalP2[$code]) || isset($tagAkhirP2[$code]))) $plantSource = "P1";
        if(!(isset($tagAwalP1[$code]) || isset($tagAkhirP1[$code])) && (isset($tagAwalP2[$code]) || isset($tagAkhirP2[$code]))) $plantSource = "P2";
    } else {
        if ($m1 && !$m2) $plantSource = "P1";
        if (!$m1 && $m2) $plantSource = "P2";
    }
    
    $matName = $m1 ? $m1['NAMA_BARANG'] : ($m2 ? $m2['NAMA_BARANG'] : '-');
    $satuan = $m1 ? $m1['SATUAN'] : ($m2 ? $m2['SATUAN'] : '-');
    if($matName == '-') $matName = isset($GLOBAL_MAP_MAT['P1_'.$code]['NAME']) ? $GLOBAL_MAP_MAT['P1_'.$code]['NAME'] : (isset($GLOBAL_MAP_MAT['P2_'.$code]['NAME']) ? $GLOBAL_MAP_MAT['P2_'.$code]['NAME'] : '-');
    
    // --- 1. SALDO AWAL DIAMBIL DARI TAG BULAN INI ---
    $awalP1 = isset($tagAwalP1[$code]) ? $tagAwalP1[$code] : 0;
    $awalP2 = isset($tagAwalP2[$code]) ? $tagAwalP2[$code] : 0;
    $awal = $awalP1 + $awalP2;

    $masuk  = ($m1 ? $m1['MASUK'] : 0) + ($m2 ? $m2['MASUK'] : 0);
    $keluar = ($m1 ? $m1['KELUAR'] : 0) + ($m2 ? $m2['KELUAR'] : 0);
    
    // --- 2. SALDO AKHIR SISTEM DIHITUNG ULANG DARI SALDO AWAL FISIK ---
    $akhir = $awal + $masuk - $keluar;
    
    // --- 3. STOK OPNAME (FISIK) DIAMBIL DARI TAG BULAN DEPAN ---
    $opnameP1 = isset($tagAkhirP1[$code]) ? $tagAkhirP1[$code] : 0;
    $opnameP2 = isset($tagAkhirP2[$code]) ? $tagAkhirP2[$code] : 0;
    $opname = $opnameP1 + $opnameP2;
    
    // --- 4. SELISIH (STOK FISIK - STOK SISTEM) ---
    $selisih = $opname - $akhir;
    
    $status = ($selisih != 0) ? "TIDAK SESUAI" : "SESUAI";

    // Hitung Loss (Minus = Barang Hilang = Biaya Positif)
    $lossRupiah = ($selisih * -1 * $hpp); 
    
    // Tambahkan ke Total Loss Pabrik
    $totalMaterialLoss += $lossRupiah;

    if ($awal != 0 || $masuk != 0 || $keluar != 0 || $opname != 0 || $selisih != 0) {
        $matFlowList[] = array(
            'PLANT' => $plantSource, 
            'CODE' => $code, 
            'NAME' => $matName, 
            'SATUAN' => $satuan,
            'HPP' => $hpp, 
            'AWAL' => $awal,
            'MASUK' => $masuk,
            'KELUAR' => $keluar,
            'AKHIR' => $akhir,
            'OPNAME' => $opname,
            'SELISIH' => $selisih,
            'LOSS_IDR' => $lossRupiah,
            'STATUS' => $status
        );
    }
}

// Urutkan berdasarkan LOSS Rupiah tertinggi
usort($matFlowList, function($a, $b) {
    if ($a['LOSS_IDR'] == $b['LOSS_IDR']) return 0;
    return ($a['LOSS_IDR'] < $b['LOSS_IDR']) ? 1 : -1;
});

// =========================================================================
// 6. KALKULASI FINAL PROFIT / LOSS
// =========================================================================
$stdGrossProfit = $totalRevenue - $totalStdCogs;

$actualGrossProfit = $stdGrossProfit - $totalMaterialLoss - ($biayaGaji + $biayaListrik + $totalBiayaVendor);
$netProfit = $actualGrossProfit - $biayaOpr;

$pctStdCogs = ($totalRevenue > 0) ? ($totalStdCogs / $totalRevenue) * 100 : 0;
$pctLoss = ($totalRevenue > 0) ? ($totalMaterialLoss / $totalRevenue) * 100 : 0;
$pctActualHpp = ($totalRevenue > 0) ? (($totalStdCogs + $totalMaterialLoss) / $totalRevenue) * 100 : 0;
$pctVendor = ($totalRevenue > 0) ? ($totalBiayaVendor / $totalRevenue) * 100 : 0; 

function fRp($val) { 
    return ($val < 0 ? "-" : "") . "Rp " . number_format(abs($val), 0, ',', '.'); 
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard True P&L Manufaktur</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'DM Sans', sans-serif; background: #f0f2f5; padding: 20px; color: #1F2937; margin:0;}
        .card { background: #FFF; border-radius: 8px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .header-title { color: #1E3A8A; font-size: 24px; font-weight: 700; margin-bottom: 20px; border-bottom: 2px solid #E5E7EB; padding-bottom: 10px;}
        
        .filter-box { display: flex; gap: 10px; background: #F8FAFC; padding: 15px; border: 1px solid #E2E8F0; border-radius: 6px; margin-bottom: 20px;}
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 11px; font-weight: 700; color: #64748B;}
        .form-group input { padding: 8px; border: 1px solid #CBD5E1; border-radius: 4px;}
        .btn-blue { background: #2563EB; color: white; border: none; padding: 8px 20px; border-radius: 4px; font-weight: 600; cursor: pointer; margin-top: 18px;}
        
        .tabs { display: flex; border-bottom: 2px solid #E2E8F0; margin-bottom: 15px; overflow-x: auto; white-space: nowrap;}
        .tab { padding: 10px 20px; cursor: pointer; font-weight: 600; color: #64748B; border-bottom: 3px solid transparent; margin-bottom: -2px; }
        .tab.active { color: #1E40AF; border-bottom-color: #1E40AF; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        /* TABEL RATA KIRI & KECIL */
        .table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .table th { background: #F1F5F9; padding: 10px; text-align: left; border-bottom: 2px solid #CBD5E1; color: #334155;}
        .table td { padding: 8px 10px; border-bottom: 1px solid #E2E8F0; text-align: left; }
        
        .text-red { color: #DC2626; font-weight: 600;}
        .text-green { color: #059669; font-weight: 600;}
        .text-right { text-align: right; }
        
        .pl-table { width: 100%; font-size: 15px; border-collapse: collapse; margin-top: 10px;}
        .pl-table td { padding: 12px; border-bottom: 1px solid #E2E8F0; }
        .pl-table .section { font-weight: 700; background: #F8FAFC; color: #1E3A8A;}
        .pl-table .indent { padding-left: 30px; }
        .pl-table .total { font-weight: 700; font-size: 16px; background: #DBEAFE; color: #1E40AF;}
        .pl-table .grand-total { font-weight: 700; font-size: 18px; background: #1E3A8A; color: white;}
        
        .badge { font-size: 12px; padding: 3px 8px; border-radius: 4px; margin-left: 10px; font-weight: 600; cursor: pointer;}
        .badge-gray { background: #E2E8F0; color: #334155; border:1px solid #CBD5E1;}
        .badge-red { background: #FEE2E2; color: #991B1B; border:1px solid #FCA5A5;}
        .badge-blue { background: #DBEAFE; color: #1E40AF; border:1px solid #93C5FD;}
        .badge-purple { background: #F3E8FF; color: #6B21A8; border:1px solid #D8B4FE;}
        .badge:hover { opacity: 0.8; }
        
        /* Pewarnaan Status */
        .st-sesuai { color: #059669; font-weight: 700; }
        .st-tidak { color: #DC2626; font-weight: 700; }
        
        /* Style untuk Tag/Badge Plant */
        .plant-tag { font-size: 10px; font-weight:bold; background:#1E40AF; color:#FFF; padding:2px 6px; border-radius:3px; display:inline-block; }
        .plant-tag.gabungan { background:#6B21A8; }
        .plant-tag.plant2 { background:#047857; }
    </style>
</head>
<body>

<div class="card">
    <div class="header-title">📊 True Profit & Loss Dashboard (P1 + P2)</div>
    
    <form method="GET" class="filter-box">
        <div class="form-group"><label>Bulan</label><input type="number" name="bulan" min="1" max="12" value="<?php echo (int)$bulan; ?>"></div>
        <div class="form-group"><label>Tahun</label><input type="number" name="tahun" value="<?php echo $tahun; ?>"></div>
        <div class="form-group"><label>Gaji Pabrik (Rp)</label><input type="number" name="gaji" value="<?php echo $biayaGaji; ?>"></div>
        <div class="form-group"><label>Listrik Pabrik (Rp)</label><input type="number" name="listrik" value="<?php echo $biayaListrik; ?>"></div>
        <div class="form-group"><label>Beban Opr (Rp)</label><input type="number" name="operasional" value="<?php echo $biayaOpr; ?>"></div>
        <button type="submit" class="btn-blue">Proses Data</button>
    </form>

    <div class="tabs">
        <div class="tab active" onclick="openTab(event, 'tPL')">Laporan P&L Aktual</div>
        <div class="tab" onclick="openTab(event, 'tSales')">Detail 1: Sales & HPP</div>
        <div class="tab" onclick="openTab(event, 'tLoss')">Detail 3: Mutasi & Loss Produksi ⚠️</div>
        <div class="tab" onclick="openTab(event, 'tVendor')">Detail 2: Maklon Vendor</div>
    </div>

    <!-- TAB 1: TRUE P&L -->
    <div id="tPL" class="tab-content active">
        <div style="background:#FEF2F2; padding:10px; color:#991B1B; font-size:13px; border:1px solid #FCA5A5; border-radius:4px; margin-bottom:15px;">
            <b>INFO:</b> Saldo Awal diambil dari SOP Tag tgl <b><?php echo date('d/m/Y', strtotime($tglAwal)); ?></b>. Stok Akhir/Opname ditarik dari SOP Tag tgl <b><?php echo date('d/m/Y', strtotime($tglOpname)); ?></b>.
        </div>
        <table class="pl-table">
            <tr class="section"><td colspan="2">I. PENDAPATAN</td></tr>
            <tr><td class="indent">Penjualan Bersih (Sales)</td><td class="text-right text-green"><?php echo fRp($totalRevenue); ?></td></tr>
            
            <tr class="section"><td colspan="2">II. HARGA POKOK PENJUALAN (COGS)</td></tr>
            <tr>
                <td class="indent">HPP Modal Barang (Material)
                    <span class="badge badge-gray" onclick="document.querySelectorAll('.tab')[1].click();"><?php echo number_format($pctStdCogs, 2); ?>% dari Sales 🔗</span>
                </td>
                <td class="text-right text-red"><?php echo fRp($totalStdCogs); ?></td>
            </tr>
            
            <tr class="total"><td class="indent">LABA KOTOR STANDAR (TEORI)</td><td class="text-right"><?php echo fRp($stdGrossProfit); ?></td></tr>
            
            <tr class="section"><td colspan="2">III. BIAYA PABRIK & PEMBOROSAN PRODUKSI</td></tr>
            <tr>
                <td class="indent">Biaya Jasa Produksi Vendor / Maklon (Tipe 3) 
                    <?php if($pctVendor>0): ?>
                        <span class="badge badge-purple" onclick="document.querySelectorAll('.tab')[3].click();"><?php echo number_format($pctVendor, 2); ?>% 🔗</span>
                    <?php endif; ?>
                </td>
                <td class="text-right text-red"><?php echo fRp($totalBiayaVendor); ?></td>
            </tr>
            <tr><td class="indent">Biaya Gaji & Listrik Pabrik</td><td class="text-right text-red"><?php echo fRp($biayaGaji + $biayaListrik); ?></td></tr>
            <tr>
                <td class="indent">Pemborosan Material (Berdasarkan Selisih Tag Opname) ⚠️
                    <span class="badge badge-red" onclick="document.querySelectorAll('.tab')[2].click();"><?php echo number_format($pctLoss, 2); ?>% dari Sales 🔗</span>
                </td>
                <td class="text-right <?php echo ($totalMaterialLoss > 0) ? 'text-red' : 'text-green'; ?>">
                    <?php echo fRp($totalMaterialLoss); ?>
                </td>
            </tr>
            
            <tr style="background:#F8FAFC;">
                <td class="indent" style="font-weight:700; color:#1E3A8A;">Total Beban Pabrik & Material Aktual
                    <span class="badge badge-blue"><?php echo number_format($pctActualHpp + $pctVendor, 2); ?>% dari Sales</span>
                </td>
                <td class="text-right text-red" style="font-weight:700; border-top:2px solid #CBD5E1;">
                    <?php echo fRp($totalStdCogs + $totalMaterialLoss + $totalBiayaVendor); ?>
                </td>
            </tr>
            
            <tr class="total"><td class="indent">LABA KOTOR AKTUAL</td><td class="text-right"><?php echo fRp($actualGrossProfit); ?></td></tr>
            
            <tr class="section"><td colspan="2">IV. BIAYA OPERASIONAL</td></tr>
            <tr><td class="indent">Biaya Kantor / Operasional</td><td class="text-right text-red"><?php echo fRp($biayaOpr); ?></td></tr>
            
            <tr class="grand-total"><td class="indent">LABA BERSIH (NET PROFIT)</td><td class="text-right"><?php echo fRp($netProfit); ?></td></tr>
        </table>
    </div>

    <!-- TAB 2: SALES & MARGIN -->
    <div id="tSales" class="tab-content">
        <div style="background:#F1F5F9; padding:15px; border-radius:6px; margin-bottom:15px; font-size:14px; border:1px solid #CBD5E1;">
            <b>Rincian HPP (Modal Material Terjual):</b>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Plant</th><th>Item Code</th><th>Item Name</th><th>Qty Terjual</th>
                    <th>HPP Modal/Unit</th><th>Total COGS</th>
                    <th>Total Revenue</th><th>Margin Teori</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($salesList as $s): ?>
                <tr>
                    <td><b><?php echo $s['PLANT']; ?></b></td><td><?php echo $s['CODE']; ?></td><td><?php echo $s['NAME']; ?></td>
                    <td><?php echo number_format($s['QTY']); ?></td>
                    <td><?php echo fRp($s['HPP']); ?></td><td class="text-red"><?php echo fRp($s['COGS']); ?></td>
                    <td class="text-green"><?php echo fRp($s['REVENUE']); ?></td>
                    <td>
                        <span style="padding:2px 8px; border-radius:4px; font-weight:bold; background:<?php echo ($s['MARGIN']<10)?'#FEE2E2':'#DCFCE7'; ?>; color:<?php echo ($s['MARGIN']<10)?'#DC2626':'#059669'; ?>;">
                            <?php echo number_format($s['MARGIN'], 1); ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- TAB 3: MATERIAL LOSS -->
    <div id="tLoss" class="tab-content">
        <div style="background:#FEF2F2; padding:15px; border-radius:6px; margin-bottom:15px; font-size:14px; border:1px solid #FCA5A5;">
            <b>Tabel Rekonsiliasi Material P1 & P2:</b> <br/>
            - <b>Saldo Awal</b> diambil murni dari Tag SOP Tgl: <b><?php echo date('d/m/Y', strtotime($tglAwal)); ?></b> <br/>
            - <b>Saldo Akhir Sistem</b> = Saldo Awal (Tag) + In - Out <br/>
            - <b>Stok Opname</b> diambil murni dari Tag SOP Tgl: <b><?php echo date('d/m/Y', strtotime($tglOpname)); ?></b>
        </div>
        <table class="table" style="font-size: 11px;">
            <thead>
                <tr>
                    <th>Plant</th> 
                    <th>Kode Barang</th>
                    <th>Nama Barang</th>
                    <th>Sat.</th>
                    <th class="text-right">Harga HPP</th>
                    <th class="text-right" style="background:#FEF9C3;">Saldo Awal<br>(Tag Tgl 1)</th>
                    <th class="text-right">Masuk</th>
                    <th class="text-right">Keluar</th>
                    <th class="text-right" style="background:#F1F5F9;">Saldo Akhir<br>(Hitungan Sistem)</th>
                    <th class="text-right" style="background:#F0FDF4;">Stok Opname<br>(Tag Bln Depan)</th>
                    <th class="text-right" style="background:#FEE2E2;">Selisih Qty</th>
                    <th class="text-right" style="background:#FEF2F2; color:#991B1B;">Loss / Variance (Rp)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($matFlowList as $m): 
                    $statStr = strtoupper(trim($m['STATUS']));
                    $stClass = "st-sesuai";
                    if(strpos($statStr, 'TIDAK') !== false) $stClass = "st-tidak";
                    
                    // Styling untuk tag Plant
                    $tagClass = "plant-tag";
                    if ($m['PLANT'] == "P1 & P2") $tagClass .= " gabungan";
                    elseif ($m['PLANT'] == "P2") $tagClass .= " plant2";
                ?>
                <tr>
                    <td><span class="<?php echo $tagClass; ?>"><?php echo $m['PLANT']; ?></span></td>
                    <td><b><?php echo $m['CODE']; ?></b></td>
                    <td><?php echo $m['NAME']; ?></td>
                    <td><?php echo $m['SATUAN']; ?></td>
                    <td class="text-right"><?php echo number_format($m['HPP'], 0, ',', '.'); ?></td>
                    
                    <td class="text-right" style="background:#FEF9C3; font-weight:bold;"><?php echo number_format($m['AWAL'], 2); ?></td>
                    <td class="text-right"><?php echo number_format($m['MASUK'], 2); ?></td>
                    <td class="text-right"><?php echo number_format($m['KELUAR'], 2); ?></td>
                    
                    <td class="text-right" style="background:#F8FAFC; font-weight:bold;">
                        <?php echo number_format($m['AKHIR'], 2); ?>
                    </td>
                    
                    <td class="text-right" style="background:#F0FDF4; font-weight:bold;">
                        <?php echo number_format($m['OPNAME'], 2); ?>
                    </td>
                    
                    <td class="text-right text-red" style="background:#FEF2F2; font-weight:bold;">
                        <?php echo number_format($m['SELISIH'], 2); ?>
                    </td>
                    
                    <td class="text-right" style="font-weight:bold; background:#FEF2F2; color:<?php echo ($m['LOSS_IDR'] > 0) ? '#DC2626' : '#059669'; ?>;">
                        <?php echo fRp($m['LOSS_IDR']); ?>
                    </td>
                    
                    <td class="<?php echo $stClass; ?>"><?php echo $m['STATUS']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- TAB 4: MAKLON VENDOR -->
    <div id="tVendor" class="tab-content">
        <div style="background:#F3E8FF; padding:15px; border-radius:6px; margin-bottom:15px; font-size:14px; border:1px solid #D8B4FE;">
            <b>Rincian Biaya Jasa Produksi Vendor / Maklon:</b>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Plant</th><th>Tgl Receive</th><th>No. Receive</th><th>No. PO Vendor</th>
                    <th>Kode Barang</th><th>Nama Barang</th>
                    <th>Qty Diterima</th><th>Harga Jasa / Unit</th>
                    <th>Mata Uang (Kurs)</th>
                    <th style="background:#F1F5F9;">Total Biaya (IDR)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($vendorDetailList as $v): 
                    $rcvDate = is_object($v['RCV_DATE']) ? $v['RCV_DATE']->format('d/m/Y') : $v['RCV_DATE'];
                ?>
                <tr>
                    <td><span class="plant-tag <?php echo ($v['PLANT']=='P2')?'plant2':''; ?>"><?php echo $v['PLANT']; ?></span></td>
                    <td><?php echo $rcvDate; ?></td>
                    <td><?php echo $v['RCV_NO']; ?></td>
                    <td><?php echo $v['PO_NUM']; ?></td>
                    <td><?php echo $v['ITEM_CODE']; ?></td>
                    <td><?php echo $v['ITEM_NAME']; ?></td>
                    <td><?php echo number_format((float)$v['RCVD_QTY'], 2); ?></td>
                    <td><?php echo number_format((float)$v['POD_PRICE'], 2); ?></td>
                    <td><?php echo ($v['PO_CUR'] ?: 'IDR') . " (" . number_format((float)$v['KURS']) . ")"; ?></td>
                    <td class="text-red" style="font-weight:700; background:#F8FAFC;">
                        <?php echo fRp($v['TOTAL_IDR']); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($vendorDetailList)): ?>
                <tr>
                    <td colspan="10" style="text-align:center; padding:20px; color:#64748B;">Tidak ada transaksi Receive (Type 3) dari Vendor pada periode ini.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
function openTab(evt, id) {
    let contents = document.querySelectorAll('.tab-content');
    contents.forEach(c => c.classList.remove('active'));
    let tabs = document.querySelectorAll('.tab');
    tabs.forEach(t => t.classList.remove('active'));
    
    document.getElementById(id).classList.add('active');
    if(evt && evt.currentTarget) {
        evt.currentTarget.classList.add('active');
    }
}
</script>
</body>
</html>
<?php 
if($connP1) sqlsrv_close($connP1); 
if($connP2) sqlsrv_close($connP2); 
?>
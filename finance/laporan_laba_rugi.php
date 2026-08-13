<?php
// =========================================================================
// MASTER DASHBOARD P&L KONSOLIDASI (P1 & P2) + CROSS-PLANT PRICING ENGINE
// Menggabungkan Sales, Consumption Material (Tally), Receive, Opname Stok (SOP)
// Dan Mesin Pencari Harga Lintas Plant (Cross-Plant Cross-Reference).
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

// HELPER QUERY
function q($conn, $sql, $params = array()) {
    if (!$conn) return false;
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return false;
    return $stmt;
}

// =========================================================================
// POLYFILL PHP 5.4: Tambahkan fungsi array_column jika tidak tersedia
// =========================================================================
if (!function_exists('array_column')) {
    function array_column(array $input, $columnKey, $indexKey = null) {
        $array = array();
        foreach ($input as $value) {
            if (!is_array($value)) continue;
            if (is_null($indexKey)) {
                $array[] = $value[$columnKey];
            } else {
                $array[$value[$indexKey]] = $value[$columnKey];
            }
        }
        return $array;
    }
}

// =========================================================================
// 1. MESIN PENCARI HARGA MATERIAL CROSS-PLANT (+ HPP BOM UNTUK ITTY 01)
// =========================================================================
function getMaterialHargaMaster($connP1, $connP2) {
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

    $mapHarga = array();
    
    if ($connP1) {
        $stmt = q($connP1, $sql);
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $mapHarga['P1_' . trim($row['MAT_CODE'])] = (float)$row['HARGA_IDR'];
            }
            sqlsrv_free_stmt($stmt);
        }
    }
    
    if ($connP2) {
        $stmt = q($connP2, $sql);
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $mapHarga['P2_' . trim($row['MAT_CODE'])] = (float)$row['HARGA_IDR'];
            }
            sqlsrv_free_stmt($stmt);
        }
    }
    
    return $mapHarga;
}

$GLOBAL_MAP_HARGA = getMaterialHargaMaster($connP1, $connP2);

function cariHargaCrossPlant($matCode) {
    global $GLOBAL_MAP_HARGA;
    $code = trim($matCode);
    
    $hargaP1 = isset($GLOBAL_MAP_HARGA['P1_' . $code]) ? $GLOBAL_MAP_HARGA['P1_' . $code] : 0;
    $hargaP2 = isset($GLOBAL_MAP_HARGA['P2_' . $code]) ? $GLOBAL_MAP_HARGA['P2_' . $code] : 0;
    
    if ($hargaP1 > 0) return $hargaP1;
    if ($hargaP2 > 0) return $hargaP2;
    return 0;
}

// =========================================================================
// 2. SETUP PERIODE & INPUT MANUAL OVERHEAD
// =========================================================================
$bulan = isset($_GET['bulan']) ? str_pad($_GET['bulan'], 2, '0', STR_PAD_LEFT) : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

$tglAwal = "$tahun-$bulan-01";
$tglAkhir = date("Y-m-t", strtotime($tglAwal)); 
$tglAwalBulanDepan = date('Y-m-d', strtotime('+1 month', strtotime($tglAwal)));

$tglAwalTime = $tglAwal . " 00:00:00";
$tglAkhirTime = $tglAkhir . " 23:59:59";
$tglAwalBulanDepanTime = $tglAwalBulanDepan . " 00:00:00";

$biayaGaji      = isset($_GET['gaji']) ? (float)$_GET['gaji'] : 0;
$biayaListrik   = isset($_GET['listrik']) ? (float)$_GET['listrik'] : 0;
$biayaOpr       = isset($_GET['operasional']) ? (float)$_GET['operasional'] : 0;

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
// 3. DATA PENJUALAN (SALES)
// =========================================================================
$sqlSales = "
    SELECT DI.DI_NO, CONVERT(VARCHAR(10), DI.DI_DATE, 120) AS TANGGAL, C.CUST_ABBR, DIPA_PAR.QTY,
           (DIPA_PAR.QTY * DIPA_PAR.PART_PRICE * CASE WHEN APV.CURR_CODE IN ('IDR', 'RP') OR APV.CURR_CODE IS NULL THEN 1 ELSE ISNULL(RV.CURR_VRATE, 1) END) AS TOTAL_IDR
    FROM DI
    INNER JOIN DIPA_PAR ON DI.DI_ID = DIPA_PAR.DI_ID
    INNER JOIN PRICE ON DIPA_PAR.PRICE_ID = PRICE.PRICE_ID
    LEFT JOIN ACTIVE_PRICE_VIEW AS APV ON PRICE.PRICE_ID = APV.PRICE_ID
    LEFT JOIN CURR_RAT AS RV ON APV.CURR_CODE = RV.CURR_CODE AND DI.DI_DATE BETWEEN RV.CURR_SDATE AND ISNULL(RV.CURR_EDATE, DI.DI_DATE)
    LEFT JOIN CUST C ON DI.CUST_ID = C.CUST_ID
    WHERE DI.DI_DATE >= ? AND DI.DI_DATE <= ?
    ORDER BY DI.DI_DATE DESC
";
$dtSales = array_merge(
    fetchDetail($connP1, $sqlSales, [$tglAwalTime, $tglAkhirTime], 'P1'),
    fetchDetail($connP2, $sqlSales, [$tglAwalTime, $tglAkhirTime], 'P2')
);
$totalPendapatan = array_sum(array_column($dtSales, 'TOTAL_IDR'));

// =========================================================================
// 4. DATA MATERIAL KONSUMSI (Tally Consumpt) - Basis 53,24%
// =========================================================================
function fetchConsumptionLinked($conn, $start, $end, $plantName) {
    $results = array();
    if (!$conn) return $results;
    
    $stmt = @sqlsrv_query($conn, "EXECUTE sp_GenerateTallyConsumtion ?, ?", array($start, $end));
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rl = array_change_key_case($row, CASE_LOWER);
            $itemCode = isset($rl['item_material']) ? trim($rl['item_material']) : '';
            $qty = isset($rl['cons_qty']) ? (float)$rl['cons_qty'] : 0;
            
            $hargaMaster = cariHargaCrossPlant($itemCode);
            $totalIdr = $qty * $hargaMaster;

            $results[] = array(
                'PLANT' => $plantName,
                'VCH_NO' => isset($rl['vch_no']) ? $rl['vch_no'] : '',
                'TANGGAL' => isset($rl['date']) ? $rl['date'] : '',
                'ITEM_CODE' => $itemCode,
                'QTY' => $qty,
                'RATE' => $hargaMaster,
                'TOTAL_IDR' => $totalIdr
            );
        }
        sqlsrv_free_stmt($stmt);
    }
    return $results;
}

$dtConsumpt = array_merge(
    fetchConsumptionLinked($connP1, $tglAwal, $tglAkhir, 'P1'),
    fetchConsumptionLinked($connP2, $tglAwal, $tglAkhir, 'P2')
);
$totalMaterialKonsumsi = array_sum(array_column($dtConsumpt, 'TOTAL_IDR'));

// =========================================================================
// 5. DATA PENERIMAAN (PEMBELIAN & SUBCON)
// =========================================================================
$sqlReceive = "
    SELECT r.RCV_NO, CONVERT(VARCHAR(10), r.RCV_DATE, 120) AS TANGGAL,
           CASE WHEN r.RCV_TYPE = 1 THEN 'PEMBELIAN MATERIAL' ELSE 'JASA SUBCON' END AS TIPE,
           m.ITEM_CODE, rd.RCVD_QTY AS QTY,
           (rd.RCVD_QTY * rd.POD_PRICE * CASE WHEN ISNULL(po.PO_CUR, 'IDR') IN ('IDR','RP') THEN 1 ELSE ISNULL(c.CURR_VRATE, 1) END) AS TOTAL_IDR
    FROM RECEIVE r
    INNER JOIN RECEIVE_DETAIL rd ON r.RCV_ID = rd.RCV_ID
    INNER JOIN PO po ON rd.PO_ID = po.PO_ID
    INNER JOIN ITEMS m ON rd.ITEM_ID = m.ITEM_ID
    LEFT JOIN CURR_RAT c ON po.PO_CUR = c.CURR_CODE AND r.RCV_DATE BETWEEN c.CURR_SDATE AND ISNULL(c.CURR_EDATE, r.RCV_DATE)
    WHERE r.RCV_TYPE IN (1,3) AND r.RCV_DATE >= ? AND r.RCV_DATE <= ?
    ORDER BY r.RCV_DATE DESC
";
$dtRecv = array_merge(
    fetchDetail($connP1, $sqlReceive, [$tglAwalTime, $tglAkhirTime], 'P1'),
    fetchDetail($connP2, $sqlReceive, [$tglAwalTime, $tglAkhirTime], 'P2')
);
$totalPembelian = 0; $totalSubcon = 0;
foreach($dtRecv as $r) {
    if ($r['TIPE'] == 'JASA SUBCON') $totalSubcon += (float)$r['TOTAL_IDR'];
    else $totalPembelian += (float)$r['TOTAL_IDR'];
}

// =========================================================================
// 6. DATA SALDO STOK (BARANG JADI & MATERIAL)
// =========================================================================
function fetchStokLinked($conn, $tglOpname, $plantName, $tipeStok = 'FG') {
    $results = array();
    if (!$conn) return $results;
    
    $condition = ($tipeStok == 'FG') ? "i.ITTY_CODE = '01'" : "i.ITTY_CODE <> '01'";

    $sql = "
        SELECT s.SOP_REF AS SOP_NO, 
               CONVERT(VARCHAR(10), s.SOP_SDATE, 120) AS TANGGAL, 
               i.ITEM_CODE, 
               i.ITTY_CODE,
               SUM(t.TAG_QTY) AS QTY
        FROM SOP s
        INNER JOIN TAGS t ON s.SOP_ID = t.SOP_ID
        INNER JOIN ITEMS i ON t.ITEM_ID = i.ITEM_ID
        WHERE $condition 
          AND CONVERT(VARCHAR(10), s.SOP_SDATE, 120) = ?
        GROUP BY s.SOP_REF, s.SOP_SDATE, i.ITEM_CODE, i.ITTY_CODE
    ";
    
    $stmt = @sqlsrv_query($conn, $sql, [$tglOpname]);
    
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $itemCode = trim($row['ITEM_CODE']);
            $qty = (float)$row['QTY'];
            $hargaMaster = cariHargaCrossPlant($itemCode);
            
            if ($row['ITTY_CODE'] == '02') {
                $totalIdr = ($qty * $hargaMaster) / 1000;
            } else {
                $totalIdr = $qty * $hargaMaster;
            }

            $results[] = array(
                'PLANT' => $plantName,
                'SOP_NO' => $row['SOP_NO'],
                'TANGGAL' => $row['TANGGAL'],
                'ITEM_CODE' => $itemCode,
                'ITTY_CODE' => $row['ITTY_CODE'],
                'QTY' => $qty,
                'HARGA_HPP' => $hargaMaster,
                'TOTAL_IDR' => $totalIdr
            );
        }
        sqlsrv_free_stmt($stmt);
    }
    return $results;
}

$dtStokAwal = array_merge(fetchStokLinked($connP1, $tglAwal, 'P1', 'FG'), fetchStokLinked($connP2, $tglAwal, 'P2', 'FG'));
$totalFgAwal = array_sum(array_column($dtStokAwal, 'TOTAL_IDR'));

$dtStokAkhir = array_merge(fetchStokLinked($connP1, $tglAwalBulanDepan, 'P1', 'FG'), fetchStokLinked($connP2, $tglAwalBulanDepan, 'P2', 'FG'));
$totalFgAkhir = array_sum(array_column($dtStokAkhir, 'TOTAL_IDR'));

$dtMatAwal = array_merge(fetchStokLinked($connP1, $tglAwal, 'P1', 'MAT'), fetchStokLinked($connP2, $tglAwal, 'P2', 'MAT'));
$dtMatAkhir = array_merge(fetchStokLinked($connP1, $tglAwalBulanDepan, 'P1', 'MAT'), fetchStokLinked($connP2, $tglAwalBulanDepan, 'P2', 'MAT'));
$jumlahStokAll = count($dtStokAwal) + count($dtStokAkhir) + count($dtMatAwal) + count($dtMatAkhir);


// =========================================================================
// 7. ANALISIS FLOW MATERIAL (Pembanding Rumus Manual vs Tally Sistem)
// =========================================================================
$matFlow = array();

// A. Saldo Awal
foreach ($dtMatAwal as $r) {
    $code = trim($r['ITEM_CODE']);
    if (!isset($matFlow[$code])) $matFlow[$code] = array('ITEM_CODE'=>$code, 'AWAL'=>0, 'BELI'=>0, 'AKHIR'=>0, 'TALLY'=>0);
    $matFlow[$code]['AWAL'] += (float)$r['TOTAL_IDR'];
}
// B. Pembelian (Exclude Subcon)
foreach ($dtRecv as $r) {
    if ($r['TIPE'] == 'PEMBELIAN MATERIAL') {
        $code = trim($r['ITEM_CODE']);
        if (!isset($matFlow[$code])) $matFlow[$code] = array('ITEM_CODE'=>$code, 'AWAL'=>0, 'BELI'=>0, 'AKHIR'=>0, 'TALLY'=>0);
        $matFlow[$code]['BELI'] += (float)$r['TOTAL_IDR'];
    }
}
// C. Saldo Akhir
foreach ($dtMatAkhir as $r) {
    $code = trim($r['ITEM_CODE']);
    if (!isset($matFlow[$code])) $matFlow[$code] = array('ITEM_CODE'=>$code, 'AWAL'=>0, 'BELI'=>0, 'AKHIR'=>0, 'TALLY'=>0);
    $matFlow[$code]['AKHIR'] += (float)$r['TOTAL_IDR'];
}
// D. Aktual Tally Consumpt (Data Sumber 53.24%)
foreach ($dtConsumpt as $r) {
    $code = trim($r['ITEM_CODE']);
    if (!isset($matFlow[$code])) $matFlow[$code] = array('ITEM_CODE'=>$code, 'AWAL'=>0, 'BELI'=>0, 'AKHIR'=>0, 'TALLY'=>0);
    $matFlow[$code]['TALLY'] += (float)$r['TOTAL_IDR'];
}

// Variables for Grand Total Table
$totAwal = 0; $totBeli = 0; $totAkhir = 0; 
$totKonsumsiRumus = 0; $totTally = 0; 

foreach ($matFlow as &$row) {
    $row['KONSUMSI_RUMUS'] = $row['AWAL'] + $row['BELI'] - $row['AKHIR'];
    // Persentase didasarkan pada TALLY (Sistem) agar matching dengan P&L
    $row['PERSEN_SALES_TALLY'] = ($totalPendapatan > 0) ? ($row['TALLY'] / $totalPendapatan) * 100 : 0;
    
    $totAwal += $row['AWAL'];
    $totBeli += $row['BELI'];
    $totAkhir += $row['AKHIR'];
    $totKonsumsiRumus += $row['KONSUMSI_RUMUS'];
    $totTally += $row['TALLY'];
}
unset($row);

$totPersenTally = ($totalPendapatan > 0) ? ($totTally / $totalPendapatan) * 100 : 0;

// Urutkan berdasarkan Tally Terbesar
usort($matFlow, function($a, $b) {
    if ($a['TALLY'] == $b['TALLY']) return 0;
    return ($a['TALLY'] < $b['TALLY']) ? 1 : -1;
});


// =========================================================================
// 8. DATA P&L PER ITEM BARANG JADI (Analisis Margin Profitability)
// =========================================================================
$sqlPLItem = "
    SELECT i.ITEM_ID, i.ITEM_CODE, i.ITEM_NAME, 
           SUM(DIPA_PAR.QTY) AS QTY_SOLD,
           SUM(DIPA_PAR.QTY * DIPA_PAR.PART_PRICE * CASE WHEN APV.CURR_CODE IN ('IDR', 'RP') OR APV.CURR_CODE IS NULL THEN 1 ELSE ISNULL(RV.CURR_VRATE, 1) END) AS TOTAL_REVENUE
    FROM DI
    INNER JOIN DIPA_PAR ON DI.DI_ID = DIPA_PAR.DI_ID
    INNER JOIN PRICE ON DIPA_PAR.PRICE_ID = PRICE.PRICE_ID
    INNER JOIN ITEMS i ON PRICE.PART_ID = i.ITEM_ID
    LEFT JOIN ACTIVE_PRICE_VIEW AS APV ON PRICE.PRICE_ID = APV.PRICE_ID
    LEFT JOIN CURR_RAT AS RV ON APV.CURR_CODE = RV.CURR_CODE AND DI.DI_DATE BETWEEN RV.CURR_SDATE AND ISNULL(RV.CURR_EDATE, DI.DI_DATE)
    WHERE DI.DI_DATE >= ? AND DI.DI_DATE <= ?
    GROUP BY i.ITEM_ID, i.ITEM_CODE, i.ITEM_NAME
";

$sqlErrorMsg = "";
function fetchPLItemSafe($conn, $sql, $params, $plantName) {
    global $sqlErrorMsg;
    $results = array();
    if (!$conn) return $results;
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $err = sqlsrv_errors();
        $sqlErrorMsg .= "<strong>[Data " . $plantName . " Gagal Dimuat]</strong> " . htmlspecialchars($err[0]['message']) . "<br>";
        return $results;
    }
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $row['PLANT'] = $plantName;
        $results[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $results;
}

$dtPLItemRaw = array_merge(
    fetchPLItemSafe($connP1, $sqlPLItem, [$tglAwalTime, $tglAkhirTime], 'P1'),
    fetchPLItemSafe($connP2, $sqlPLItem, [$tglAwalTime, $tglAkhirTime], 'P2')
);

// Proses Kalkulasi HPP dan Margin per Item
$dtPLItem = array();
foreach ($dtPLItemRaw as $row) {
    $itemCode = trim($row['ITEM_CODE']);
    $qtySold = (float)$row['QTY_SOLD'];
    $revenue = (float)$row['TOTAL_REVENUE'];
    
    $hppPerUnit = cariHargaCrossPlant($itemCode);
    $totalCogs = $qtySold * $hppPerUnit;
    
    $grossProfit = $revenue - $totalCogs;
    $marginPersen = ($revenue != 0) ? ($grossProfit / $revenue) * 100 : 0;
    
    $dtPLItem[] = array(
        'PLANT' => $row['PLANT'],
        'PART_ID' => $row['ITEM_ID'], 
        'ITEM_CODE' => $itemCode,
        'ITEM_NAME' => $row['ITEM_NAME'],
        'QTY_SOLD' => $qtySold,
        'REVENUE' => $revenue,
        'HPP_PER_UNIT' => $hppPerUnit,
        'TOTAL_COGS' => $totalCogs,
        'GROSS_PROFIT' => $grossProfit,
        'MARGIN_PERSEN' => $marginPersen
    );
}

// Urutkan Profit
usort($dtPLItem, function($a, $b) {
    if ($a['GROSS_PROFIT'] == $b['GROSS_PROFIT']) return 0;
    return ($a['GROSS_PROFIT'] < $b['GROSS_PROFIT']) ? 1 : -1;
});


// =========================================================================
// 9. KALKULASI FINAL LABA RUGI GLOBAL
// =========================================================================
$totalOverheadPabrik = $biayaGaji + $biayaListrik;
$hppProduksi = $totalMaterialKonsumsi + $totalOverheadPabrik + $totalSubcon;
$cogs = $hppProduksi + $totalFgAwal - $totalFgAkhir;
$labaKotor = $totalPendapatan - $cogs;
$labaBersih = $labaKotor - $biayaOpr;
$persentaseMaterial = ($totalPendapatan > 0) ? ($totalMaterialKonsumsi / $totalPendapatan) * 100 : 0;

function fRp($val) {
    if ($val < 0) return "(Rp " . number_format(abs($val), 0, ',', '.') . ")";
    return "Rp " . number_format($val, 0, ',', '.');
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Validasi & Laba Rugi Manufaktur (Cross-Plant Link)</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'DM Sans', sans-serif; background: #F3F4F6; padding: 20px; color: #1F2937; margin:0;}
        .card { background: #FFF; border-radius: 10px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 20px; }
        h2 { margin-top:0; color: #2563EB; font-size: 22px; }
        
        .filter-row { display: flex; gap: 10px; background: #F9FAFB; padding: 15px; border-radius: 8px; border: 1px solid #E5E7EB; margin-bottom: 20px; flex-wrap: wrap;}
        .form-group { display: flex; flex-direction: column; gap: 4px; }
        .form-group label { font-size: 11px; font-weight: 700; color: #4B5563; text-transform: uppercase;}
        .form-group input, .form-group select { padding: 8px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; }
        .btn-submit { background: #2563EB; color: white; border: none; padding: 0 20px; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top:18px;}
        
        .tabs { display: flex; border-bottom: 2px solid #E5E7EB; margin-bottom: 20px; gap: 5px; overflow-x: auto;}
        .tab-btn { background: none; border: none; padding: 10px 20px; font-size: 14px; font-weight: 600; color: #6B7280; cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; white-space: nowrap;}
        .tab-btn.active { color: #2563EB; border-bottom-color: #2563EB; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .data-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .data-table th { background: #F3F4F6; padding: 10px; text-align: left; border-bottom: 2px solid #D1D5DB; }
        .data-table td { padding: 8px 10px; border-bottom: 1px solid #E5E7EB; }
        .badge-p1 { background: #DBEAFE; color: #1E40AF; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-p2 { background: #FEF3C7; color: #991B1B; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .val-right { text-align: right; font-weight: 600;}

        .pl-table { width: 100%; font-size: 14px; border-collapse: collapse; }
        .pl-table td { padding: 10px; border-bottom: 1px solid #E5E7EB; }
        .pl-table .section { font-weight: bold; background: #F9FAFB; }
        .pl-table .indent { padding-left: 30px; }
        .pl-table .total-row { font-weight: bold; color: white; background: #2563EB; }
        .text-red { color: #DC2626; }
        .persen-badge { background: #DBEAFE; color: #2563EB; font-size: 12px; font-weight: bold; padding: 2px 8px; border-radius: 4px; margin-left: 10px; }
        
        .scrollable { max-height: 500px; overflow-y: auto; border: 1px solid #E5E7EB; border-radius: 8px;}
    </style>
</head>
<body>

<div class="card">
    <h2><i class="fas fa-search-dollar"></i> Dashboard P&L Manufaktur (Cross-Plant Price Link)</h2>
    <form method="GET" class="filter-row">
        <div class="form-group"><label>Bulan</label><input type="number" name="bulan" min="1" max="12" value="<?php echo (int)$bulan; ?>"></div>
        <div class="form-group"><label>Tahun</label><input type="number" name="tahun" value="<?php echo $tahun; ?>"></div>
        <div class="form-group"><label>Gaji Pabrik (Rp)</label><input type="number" name="gaji" value="<?php echo $biayaGaji; ?>"></div>
        <div class="form-group"><label>Listrik Pabrik (Rp)</label><input type="number" name="listrik" value="<?php echo $biayaListrik; ?>"></div>
        <div class="form-group"><label>Beban Kantor (Rp)</label><input type="number" name="operasional" value="<?php echo $biayaOpr; ?>"></div>
        <button type="submit" class="btn-submit">Proses Data</button>
    </form>

    <div class="tabs">
        <button class="tab-btn active" onclick="openTab(event, 'tabPL')">Laporan P&L</button>
        <button class="tab-btn" onclick="openTab(event, 'tabPLItem')">P&L Per Item (<?php echo count($dtPLItem); ?>)</button>
        <button class="tab-btn" onclick="openTab(event, 'tabMatFlow')">Flow & % Material (<?php echo count($matFlow); ?>)</button>
        <button class="tab-btn" onclick="openTab(event, 'tabSales')">Penjualan (<?php echo count($dtSales); ?>)</button>
        <button class="tab-btn" onclick="openTab(event, 'tabConsumpt')">Konsumsi Tally (<?php echo count($dtConsumpt); ?>)</button>
        <button class="tab-btn" onclick="openTab(event, 'tabRecv')">Penerimaan (<?php echo count($dtRecv); ?>)</button>
        <button class="tab-btn" onclick="openTab(event, 'tabStok')">Opname (<?php echo $jumlahStokAll; ?>)</button>
    </div>

    <!-- TAB 1: PROFIT & LOSS GLOBAL -->
    <div id="tabPL" class="tab-content active">
        <table class="pl-table">
            <tr class="section"><td colspan="2">I. PENDAPATAN</td></tr>
            <tr><td class="indent">Penjualan Sales</td><td class="val-right" style="color:#059669;"><?php echo fRp($totalPendapatan); ?></td></tr>
            
            <tr class="section"><td colspan="2">II. HARGA POKOK PENJUALAN (COGS)</td></tr>
            <tr>
                <td class="indent">
                    Material Konsumsi (Tally Consumpt + Cross-Plant Price)
                    <span class="persen-badge"><?php echo number_format($persentaseMaterial, 2, ',', '.'); ?>%</span>
                </td>
                <td class="val-right"><?php echo fRp($totalMaterialKonsumsi); ?></td>
            </tr>
            <tr><td class="indent">Gaji & Listrik Pabrik</td><td class="val-right"><?php echo fRp($biayaGaji + $biayaListrik); ?></td></tr>
            <tr><td class="indent">Jasa Maklon Subcon (Receive Type 3)</td><td class="val-right"><?php echo fRp($totalSubcon); ?></td></tr>
            <tr style="background:#EFF6FF; font-weight:bold;"><td class="indent">Total Harga Pokok Produksi</td><td class="val-right"><?php echo fRp($hppProduksi); ?></td></tr>
            
            <tr><td class="indent">Ditambah: Saldo Awal Barang Jadi (FG)</td><td class="val-right"><?php echo fRp($totalFgAwal); ?></td></tr>
            <tr><td class="indent">Dikurangi: Saldo Akhir Barang Jadi (FG)</td><td class="val-right text-red">(<?php echo fRp($totalFgAkhir); ?>)</td></tr>
            <tr style="font-weight:bold;"><td class="indent">TOTAL COGS</td><td class="val-right text-red">(<?php echo fRp($cogs); ?>)</td></tr>
            
            <tr class="total-row"><td>III. LABA KOTOR (GROSS PROFIT)</td><td class="val-right"><?php echo fRp($labaKotor); ?></td></tr>
            
            <tr class="section"><td colspan="2">IV. BEBAN OPERASIONAL</td></tr>
            <tr><td class="indent">Beban Kantor</td><td class="val-right text-red">(<?php echo fRp($biayaOpr); ?>)</td></tr>
            
            <tr class="total-row" style="background:#059669;"><td>V. LABA BERSIH (NET PROFIT)</td><td class="val-right"><?php echo fRp($labaBersih); ?></td></tr>
        </table>
    </div>

    <!-- TAB 2: P&L PER ITEM (PROFITABILITY) -->
    <div id="tabPLItem" class="tab-content">
        <div class="scrollable" style="padding:10px;">
            <?php if ($sqlErrorMsg != ""): ?>
                <div style="background:#FEE2E2; border:1px solid #F87171; color:#991B1B; padding:15px; border-radius:8px; margin-bottom:15px;">
                    <h4 style="margin-top:0; margin-bottom:10px;"><i class="fas fa-exclamation-triangle"></i> Gagal Memuat Data Item</h4>
                    <?php echo $sqlErrorMsg; ?>
                </div>
            <?php endif; ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Plant</th>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th style="text-align:right;">Qty Sold</th>
                        <th style="text-align:right;">HPP / Unit (Cross-Plant)</th>
                        <th style="text-align:right;">Total COGS</th>
                        <th style="text-align:right;">Total Revenue</th>
                        <th style="text-align:right;">Gross Profit</th>
                        <th style="text-align:right;">Margin (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($dtPLItem as $r): ?>
                    <tr>
                        <td><span class="badge-<?php echo strtolower($r['PLANT']); ?>"><?php echo $r['PLANT']; ?></span></td>
                        <td>
                            <a href="cek_bom.php?part_id=<?php echo $r['PART_ID']; ?>&bulan=<?php echo (int)$bulan; ?>&tahun=<?php echo $tahun; ?>" target="_blank" style="color:#2563EB; text-decoration:none;" title="Lihat Rincian BOM & Material">
                                <b><?php echo $r['ITEM_CODE']; ?></b> <i class="fas fa-external-link-alt" style="font-size:10px; margin-left:3px;"></i>
                            </a>
                        </td>
                        <td><?php echo $r['ITEM_NAME']; ?></td>
                        <td class="val-right"><?php echo number_format($r['QTY_SOLD']); ?></td>
                        <td class="val-right"><?php echo fRp($r['HPP_PER_UNIT']); ?></td>
                        <td class="val-right" style="color:#DC2626;"><?php echo fRp($r['TOTAL_COGS']); ?></td>
                        <td class="val-right" style="color:#059669;"><?php echo fRp($r['REVENUE']); ?></td>
                        <td class="val-right" style="font-weight:bold; color:<?php echo ($r['GROSS_PROFIT'] < 0) ? '#DC2626' : '#2563EB'; ?>;">
                            <?php echo fRp($r['GROSS_PROFIT']); ?>
                        </td>
                        <td class="val-right">
                            <span class="persen-badge" style="background:<?php echo ($r['MARGIN_PERSEN'] < 0) ? '#FEE2E2' : '#DBEAFE'; ?>; color:<?php echo ($r['MARGIN_PERSEN'] < 0) ? '#DC2626' : '#2563EB'; ?>;">
                                <?php echo number_format($r['MARGIN_PERSEN'], 2, ',', '.'); ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 3: ANALISIS MATERIAL FLOW -->
    <div id="tabMatFlow" class="tab-content">
        <div class="scrollable">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode Material</th>
                        <th style="text-align:right; color:#4B5563;">(+) Stok Awal (Rp)</th>
                        <th style="text-align:right; color:#059669;">(+) Pembelian (Rp)</th>
                        <th style="text-align:right; color:#DC2626;">(-) Stok Akhir (Rp)</th>
                        <th style="text-align:right; border-right:2px solid #E5E7EB;" title="Rumus: Awal + Beli - Akhir">Konsumsi (Awal+Beli-Akhir)</th>
                        <th style="text-align:right; background:#F0FDF4; color:#166534;" title="Tally Sistem (Data Asli 53.24%)">Konsumsi Tally (Sistem)</th>
                        <th style="text-align:right; background:#F0FDF4; color:#166534;">% Tally thd Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach($matFlow as $r): 
                    ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <!-- MENGUBAH KODE MATERIAL MENJADI LINK KE WIP.PHP -->
                        <td>
                            <a href="wip.php?start_date=<?php echo $tglAwal; ?>&end_date=<?php echo $tglAwalBulanDepan; ?>&detail_material=<?php echo urlencode(trim($r['ITEM_CODE'])); ?>" target="_blank" style="color:#2563EB; text-decoration:none;" title="Lihat Persentase Pemakaian Material di Produksi">
                                <b><?php echo $r['ITEM_CODE']; ?></b> <i class="fas fa-external-link-alt" style="font-size:10px; margin-left:3px;"></i>
                            </a>
                        </td>
                        <td class="val-right" style="color:#4B5563;"><?php echo fRp($r['AWAL']); ?></td>
                        <td class="val-right" style="color:#059669;"><?php echo fRp($r['BELI']); ?></td>
                        <td class="val-right" style="color:#DC2626;"><?php echo fRp($r['AKHIR']); ?></td>
                        <td class="val-right" style="border-right:2px solid #E5E7EB;">
                            <?php echo fRp($r['KONSUMSI_RUMUS']); ?>
                        </td>
                        <td class="val-right" style="font-weight:bold; background:#DCFCE7; color:#166534;">
                            <?php echo fRp($r['TALLY']); ?>
                        </td>
                        <td class="val-right" style="background:#F0FDF4;">
                            <span class="persen-badge" style="background:#EFF6FF; color:#1E40AF; border:1px solid #BFDBFE;">
                                <?php echo number_format($r['PERSEN_SALES_TALLY'], 2, ',', '.'); ?> %
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <!-- BARIS GRAND TOTAL DITAMBAHKAN AGAR TIDAK BINGUNG -->
                <tfoot>
                    <tr style="font-weight:bold; background:#E5E7EB; font-size:13px;">
                        <td colspan="2" class="val-right" style="padding:12px;">GRAND TOTAL:</td>
                        <td class="val-right" style="padding:12px; color:#4B5563;"><?php echo fRp($totAwal); ?></td>
                        <td class="val-right" style="padding:12px; color:#059669;"><?php echo fRp($totBeli); ?></td>
                        <td class="val-right" style="padding:12px; color:#DC2626;"><?php echo fRp($totAkhir); ?></td>
                        <td class="val-right" style="padding:12px; border-right:2px solid #D1D5DB;"><?php echo fRp($totKonsumsiRumus); ?></td>
                        <td class="val-right" style="padding:12px; background:#BBF7D0; color:#166534; font-size:14px;"><?php echo fRp($totTally); ?></td>
                        <td class="val-right" style="padding:12px; background:#BBF7D0; color:#1E40AF; font-size:14px;"><?php echo number_format($totPersenTally, 2, ',', '.'); ?> %</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- TAB 4: DETAIL SALES -->
    <div id="tabSales" class="tab-content">
        <div class="scrollable">
            <table class="data-table">
                <thead><tr><th>Plant</th><th>DI NO</th><th>Tanggal</th><th>Customer</th><th>QTY</th><th style="text-align:right;">Total IDR</th></tr></thead>
                <tbody>
                    <?php foreach($dtSales as $r): ?>
                    <tr>
                        <td><span class="badge-<?php echo strtolower($r['PLANT']); ?>"><?php echo $r['PLANT']; ?></span></td>
                        <td><?php echo $r['DI_NO']; ?></td><td><?php echo $r['TANGGAL']; ?></td><td><?php echo $r['CUST_ABBR']; ?></td>
                        <td><?php echo number_format($r['QTY']); ?></td><td class="val-right"><?php echo fRp($r['TOTAL_IDR']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 5: DETAIL MATERIAL KONSUMSI (TALLY) -->
    <div id="tabConsumpt" class="tab-content">
        <div class="scrollable">
            <table class="data-table">
                <thead><tr><th>Plant</th><th>VCH No</th><th>Tanggal</th><th>Item Material</th><th>Cons. QTY</th><th>Rate (Cross-Plant)</th><th style="text-align:right;">Total Amount (IDR)</th></tr></thead>
                <tbody>
                    <?php foreach($dtConsumpt as $r): ?>
                    <tr>
                        <td><span class="badge-<?php echo strtolower($r['PLANT']); ?>"><?php echo $r['PLANT']; ?></span></td>
                        <td><?php echo $r['VCH_NO']; ?></td><td><?php echo $r['TANGGAL']; ?></td>
                        <!-- MENGUBAH KODE MATERIAL MENJADI LINK KE WIP.PHP -->
                        <td>
                            <a href="wip.php?start_date=<?php echo $tglAwal; ?>&end_date=<?php echo $tglAwalBulanDepan; ?>&detail_material=<?php echo urlencode(trim($r['ITEM_CODE'])); ?>" target="_blank" style="color:#2563EB; text-decoration:none;" title="Lihat Persentase Pemakaian Material di Produksi">
                                <b><?php echo $r['ITEM_CODE']; ?></b> <i class="fas fa-external-link-alt" style="font-size:10px; margin-left:3px;"></i>
                            </a>
                        </td>
                        <td><?php echo number_format($r['QTY'], 2); ?></td><td><?php echo number_format($r['RATE'], 2); ?></td><td class="val-right"><?php echo fRp($r['TOTAL_IDR']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 6: DETAIL PENERIMAAN / SUBCON -->
    <div id="tabRecv" class="tab-content">
        <div class="scrollable">
            <table class="data-table">
                <thead><tr><th>Plant</th><th>RCV No</th><th>Tipe Penerimaan</th><th>Item Code</th><th>QTY</th><th style="text-align:right;">Total IDR</th></tr></thead>
                <tbody>
                    <?php foreach($dtRecv as $r): ?>
                    <tr>
                        <td><span class="badge-<?php echo strtolower($r['PLANT']); ?>"><?php echo $r['PLANT']; ?></span></td>
                        <td><?php echo $r['RCV_NO']; ?></td><td><b><?php echo $r['TIPE']; ?></b></td><td><?php echo $r['ITEM_CODE']; ?></td>
                        <td><?php echo number_format($r['QTY']); ?></td><td class="val-right"><?php echo fRp($r['TOTAL_IDR']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 7: DETAIL OPNAME / STOK -->
    <div id="tabStok" class="tab-content">
        <div class="scrollable">
            <table class="data-table">
                <thead><tr><th>Plant</th><th>SOP No</th><th>Tanggal</th><th>Item Code</th><th>ITTY</th><th>QTY Fisik</th><th>Harga Cross-Plant</th><th style="text-align:right;">Total IDR</th></tr></thead>
                <tbody>
                    <!-- BAGIAN BARANG JADI -->
                    <tr><td colspan="8" style="background:#DBEAFE; font-weight:bold; text-align:center; color:#1E40AF;">SALDO AWAL BARANG JADI (FG)</td></tr>
                    <?php foreach($dtStokAwal as $r): ?>
                    <tr>
                        <td><span class="badge-<?php echo strtolower($r['PLANT']); ?>"><?php echo $r['PLANT']; ?></span></td>
                        <td><?php echo $r['SOP_NO']; ?></td><td><?php echo $r['TANGGAL']; ?></td><td><b><?php echo $r['ITEM_CODE']; ?></b></td>
                        <td><?php echo $r['ITTY_CODE']; ?></td><td><?php echo number_format($r['QTY']); ?></td><td><?php echo fRp($r['HARGA_HPP']); ?></td><td class="val-right"><?php echo fRp($r['TOTAL_IDR']); ?></td>
                    </tr>
                    <?php endforeach; ?>

                    <tr><td colspan="8" style="background:#DBEAFE; font-weight:bold; text-align:center; color:#1E40AF;">SALDO AKHIR BARANG JADI (FG)</td></tr>
                    <?php foreach($dtStokAkhir as $r): ?>
                    <tr>
                        <td><span class="badge-<?php echo strtolower($r['PLANT']); ?>"><?php echo $r['PLANT']; ?></span></td>
                        <td><?php echo $r['SOP_NO']; ?></td><td><?php echo $r['TANGGAL']; ?></td><td><b><?php echo $r['ITEM_CODE']; ?></b></td>
                        <td><?php echo $r['ITTY_CODE']; ?></td><td><?php echo number_format($r['QTY']); ?></td><td><?php echo fRp($r['HARGA_HPP']); ?></td><td class="val-right"><?php echo fRp($r['TOTAL_IDR']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- BAGIAN MATERIAL -->
                    <tr><td colspan="8" style="background:#FEF3C7; font-weight:bold; text-align:center; color:#991B1B;">SALDO AWAL MATERIAL (RM)</td></tr>
                    <?php foreach($dtMatAwal as $r): ?>
                    <tr>
                        <td><span class="badge-<?php echo strtolower($r['PLANT']); ?>"><?php echo $r['PLANT']; ?></span></td>
                        <td><?php echo $r['SOP_NO']; ?></td><td><?php echo $r['TANGGAL']; ?></td><td><b><?php echo $r['ITEM_CODE']; ?></b></td>
                        <td><?php echo $r['ITTY_CODE']; ?></td><td><?php echo number_format($r['QTY'], 2); ?></td><td><?php echo fRp($r['HARGA_HPP']); ?></td><td class="val-right"><?php echo fRp($r['TOTAL_IDR']); ?></td>
                    </tr>
                    <?php endforeach; ?>

                    <tr><td colspan="8" style="background:#FEF3C7; font-weight:bold; text-align:center; color:#991B1B;">SALDO AKHIR MATERIAL (RM)</td></tr>
                    <?php foreach($dtMatAkhir as $r): ?>
                    <tr>
                        <td><span class="badge-<?php echo strtolower($r['PLANT']); ?>"><?php echo $r['PLANT']; ?></span></td>
                        <td><?php echo $r['SOP_NO']; ?></td><td><?php echo $r['TANGGAL']; ?></td><td><b><?php echo $r['ITEM_CODE']; ?></b></td>
                        <td><?php echo $r['ITTY_CODE']; ?></td><td><?php echo number_format($r['QTY'], 2); ?></td><td><?php echo fRp($r['HARGA_HPP']); ?></td><td class="val-right"><?php echo fRp($r['TOTAL_IDR']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function openTab(evt, tabId) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) { tabcontent[i].classList.remove("active"); }
    tablinks = document.getElementsByClassName("tab-btn");
    for (i = 0; i < tablinks.length; i++) { tablinks[i].classList.remove("active"); }
    document.getElementById(tabId).classList.add("active");
    evt.currentTarget.classList.add("active");
}
</script>
</body>
</html>
<?php
if ($connP1) sqlsrv_close($connP1);
if ($connP2) sqlsrv_close($connP2);
?>
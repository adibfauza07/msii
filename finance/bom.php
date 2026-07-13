<?php
/**
 * Penerimaan Barang per Item — Bulanan
 * + Analisis Konsumsi Material % Tahunan (Jan–Dec)
 * Kompatibel PHP 5.4+
 */
 $config1 = __DIR__ . '/config/database_aging.php';
 $config2 = __DIR__ . '/../config/database_aging.php';
if (file_exists($config1)) require_once $config1;
elseif (file_exists($config2)) require_once $config2;
else die('File config database_aging.php tidak ditemukan.');


// ── Parameter GET ──
 $selected_item_id = isset($_GET['item_id']) ? trim($_GET['item_id']) : '';
 $filter_year      = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// ── 1) Daftar item yang punya penerimaan ──
 $sqlItems = "
    SELECT DISTINCT i.ITEM_ID, i.ITEM_CODE, i.ITEM_NAME
    FROM dbo.ITEMS i
    INNER JOIN dbo.RECEIVE_DETAIL rd ON i.ITEM_ID = rd.ITEM_ID
    INNER JOIN dbo.RECEIVE r ON rd.RCV_ID = r.RCV_ID AND r.RCV_TYPE = 1
    ORDER BY i.ITEM_CODE
";
 $stmtItems = q($sqlItems);
 $itemsJS = array();
while ($i = sqlsrv_fetch_array($stmtItems, SQLSRV_FETCH_ASSOC)) {
    $itemsJS[] = array(
        'id'   => $i['ITEM_ID'],
        'code' => $i['ITEM_CODE'],
        'name' => $i['ITEM_NAME']
    );
}
sqlsrv_free_stmt($stmtItems);
unset($stmtItems);

// ── 2) Daftar tahun ──
 $sqlYears = "
    SELECT DISTINCT YEAR(RCV_DATE) AS YR
    FROM dbo.RECEIVE WHERE RCV_TYPE = 1
    ORDER BY YR DESC
";
 $stmtY = q($sqlYears);
 $years = array();
while ($y = sqlsrv_fetch_array($stmtY, SQLSRV_FETCH_ASSOC)) {
    $years[] = intval($y['YR']);
}
sqlsrv_free_stmt($stmtY);
unset($stmtY);
if (!in_array($filter_year, $years) && count($years) > 0) {
    $filter_year = $years[0];
}

// ── 3) Jika item dipilih, query per bulan ──
 $monthlyData   = array();
 $monthByCur    = array();
 $monthQtyByCur = array();
 $grandTotal    = array();
 $grandQty      = 0;
 $supplierSet   = array();
 $itemInfo      = null;

if ($selected_item_id !== '') {
    // Info item (ditambah ITTY_CODE untuk keperluan konsumsi)
    $sqlII = "SELECT ITEM_CODE, ITEM_NAME, ITTY_CODE FROM dbo.ITEMS WHERE ITEM_ID = ?";
    $stII = q($sqlII, array($selected_item_id));
    $itemInfo = sqlsrv_fetch_array($stII, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stII);
    unset($stII);

    // Data per bulan per supplier
    $sqlM = "
        SELECT
            CONVERT(varchar(7), r.RCV_DATE, 120) AS BULAN,
            s.SUP_CODE, s.SUP_COMP,
            SUM(rd.RCVD_QTY) AS TOTAL_QTY,
            SUM(rd.RCVD_QTY * rd.POD_PRICE * CASE WHEN ISNULL(p.PO_CUR, 'IDR') = 'IDR' THEN 1 ELSE ISNULL(cr.CURR_VRATE, 1) END) AS TOTAL_AMOUNT,
            'IDR' AS PO_CUR,
            COUNT_BIG(*) AS RCPT_COUNT
        FROM dbo.RECEIVE r
        INNER JOIN dbo.SUPPLIER s ON r.SUP_ID = s.SUP_ID
        INNER JOIN dbo.RECEIVE_DETAIL rd ON r.RCV_ID = rd.RCV_ID
        INNER JOIN dbo.ITEMS i ON rd.ITEM_ID = i.ITEM_ID
        INNER JOIN dbo.PO p ON s.SUP_ID = p.SUP_ID AND rd.PO_ID = p.PO_ID
        OUTER APPLY (
            SELECT TOP 1 CURR_VRATE 
            FROM dbo.CURR_RAT 
            WHERE CURR_CODE = p.PO_CUR 
              AND r.RCV_DATE BETWEEN CURR_SDATE AND CURR_EDATE
            ORDER BY CURR_SDATE DESC
        ) cr
        WHERE r.RCV_TYPE = 1
          AND i.ITEM_ID = ?
          AND YEAR(r.RCV_DATE) = ?
        GROUP BY CONVERT(varchar(7), r.RCV_DATE, 120),
                 s.SUP_CODE, s.SUP_COMP, p.PO_CUR
        ORDER BY BULAN DESC, s.SUP_CODE
    ";
    
    $stM = q($sqlM, array($selected_item_id, $filter_year));

    while ($r = sqlsrv_fetch_array($stM, SQLSRV_FETCH_ASSOC)) {
        $m   = $r['BULAN'];
        $cur = (isset($r['PO_CUR']) && $r['PO_CUR'] !== '') ? $r['PO_CUR'] : 'IDR';
        $amt = floatval($r['TOTAL_AMOUNT']);
        $qty = floatval($r['TOTAL_QTY']);

        if (!isset($monthlyData[$m])) $monthlyData[$m] = array();
        $monthlyData[$m][] = array(
            'SUP_CODE'  => $r['SUP_CODE'],
            'SUP_COMP'  => $r['SUP_COMP'],
            'QTY'       => $qty,
            'AMOUNT'    => $amt,
            'CUR'       => $cur,
            'COUNT'     => intval($r['RCPT_COUNT']),
            'AVG_PRICE' => $qty > 0 ? $amt / $qty : 0
        );

        if (!isset($monthByCur[$m])) $monthByCur[$m] = array();
        if (!isset($monthByCur[$m][$cur])) $monthByCur[$m][$cur] = 0;
        $monthByCur[$m][$cur] += $amt;

        if (!isset($monthQtyByCur[$m])) $monthQtyByCur[$m] = array();
        if (!isset($monthQtyByCur[$m][$cur])) $monthQtyByCur[$m][$cur] = 0;
        $monthQtyByCur[$m][$cur] += $qty;

        if (!isset($grandTotal[$cur])) $grandTotal[$cur] = 0;
        $grandTotal[$cur] += $amt;

        $grandQty += $qty;
        $supplierSet[$r['SUP_CODE']] = true;
    }
    sqlsrv_free_stmt($stM);
    unset($stM);

    krsort($monthlyData);
}

// ── Hitung turunan penerimaan ──
 $totalSuppliers = count($supplierSet);
 $primaryCur     = 'IDR';
if (!empty($grandTotal)) {
    $maxVal = max($grandTotal);
    $primaryCur = array_search($maxVal, $grandTotal);
    if ($primaryCur === false) $primaryCur = 'IDR';
}
 $avgPrice = 0;
if ($grandQty > 0 && isset($grandTotal[$primaryCur])) {
    $avgPrice = $grandTotal[$primaryCur] / $grandQty;
}

// Data chart penerimaan
 $chartMonths = array_keys($monthByCur);
sort($chartMonths);
 $chartLabels = array();
 $chartValues = array();
 $chartAvgPrices = array();
foreach ($chartMonths as $m) {
    $chartLabels[] = bulanID($m);
    $chartValues[] = isset($monthByCur[$m][$primaryCur]) ? floatval($monthByCur[$m][$primaryCur]) : 0;
    $q = isset($monthQtyByCur[$m][$primaryCur]) ? floatval($monthQtyByCur[$m][$primaryCur]) : 0;
    $chartAvgPrices[] = $q > 0 ? ((isset($monthByCur[$m][$primaryCur]) ? floatval($monthByCur[$m][$primaryCur]) : 0) / $q) : 0;
}



// ═══════════════════════════════════════════════════════════════════
// KONSUMSI MATERIAL % PER BULAN (Jan–Dec)
// Stok = Stok Langsung Material + Stok FG Dikonversi via BOM
// ITTY='02' → TAG×BOM/1000 | Lainnya → TAG×BOM
// ═══════════════════════════════════════════════════════════════════
 $konsumsiBulanan = array();
 $konsumsiSummary = array(
    'avg_rasio' => 0, 'max_rasio' => 0, 'min_rasio' => 0,
    'bulan_aktif' => 0, 'total_konsumsi_amt' => 0, 'total_penjualan_amt' => 0,
    'total_rasio' => 0, 'available' => false, 'mat_price' => 0, 'mat_cur' => 'IDR',
    'parent_count' => 0, 'is_konversi' => false, 'mat_unit' => 'unit',
    'max_rasio_bulan' => '-', 'min_rasio_bulan' => '-', 'mat_itty' => '-'
);
 $bulanShort = array(1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des');

if ($selected_item_id !== '' && $itemInfo) {
     $mat_itty = isset($itemInfo['ITTY_CODE']) ? $itemInfo['ITTY_CODE'] : '';

// ═══════════════════════════════════════════════════════
// DAFTAR ITTY YANG PERLU KONVERSI (GRAM → KG)
// ═══════════════════════════════════════════════════════
 $itty_konversi_gram = array('02', '13');

 $is_konversi = in_array($mat_itty, $itty_konversi_gram);
 $mat_unit = $is_konversi ? 'kg' : 'unit';

 $konsumsiSummary['is_konversi'] = $is_konversi;
 $konsumsiSummary['mat_unit'] = $mat_unit;
 $konsumsiSummary['mat_itty'] = $mat_itty;

    // Q-a: Jumlah parent FG
    $sqlCnt = "SELECT COUNT(DISTINCT b.PART_ID) AS CNT FROM dbo.BOM_DEFAULT b INNER JOIN dbo.ITEMS fg ON b.PART_ID = fg.ITEM_ID WHERE b.ITEM_ID = ? AND fg.ITTY_CODE = '01'";
    $stCnt = q($sqlCnt, array($selected_item_id));
    $rCnt = sqlsrv_fetch_array($stCnt, SQLSRV_FETCH_ASSOC);
    $konsumsiSummary['parent_count'] = intval($rCnt['CNT']);
    sqlsrv_free_stmt($stCnt);

    // Q-b: Harga material terakhir
    $sqlPr = "
        SELECT TOP 1 rd.POD_PRICE, ISNULL(po.PO_CUR, 'IDR') AS PO_CUR
        FROM dbo.RECEIVE r
        INNER JOIN dbo.RECEIVE_DETAIL rd ON r.RCV_ID = rd.RCV_ID
        INNER JOIN dbo.PO po ON rd.PO_ID = po.PO_ID
        WHERE rd.ITEM_ID = ? AND r.RCV_TYPE = 1
        ORDER BY r.RCV_DATE DESC
    ";
    $stPr = q($sqlPr, array($selected_item_id));
    $matPrice = 0; $matCur = 'IDR';
    if ($rPr = sqlsrv_fetch_array($stPr, SQLSRV_FETCH_ASSOC)) {
        $matPrice = floatval($rPr['POD_PRICE']);
        $matCur = $rPr['PO_CUR'];
    }
    sqlsrv_free_stmt($stPr);
    $konsumsiSummary['mat_price'] = $matPrice;
    $konsumsiSummary['mat_cur'] = $matCur;

    if ($konsumsiSummary['parent_count'] > 0 && $matPrice > 0) {

        // Q-c: Stok 13 tanggal (GABUNGAN: langsung + FG konversi)
        $stockDates = array();
        for ($m = 1; $m <= 12; $m++) {
            $stockDates[] = sprintf('%04d-%02d-01', $filter_year, $m);
        }
        $stockDates[] = sprintf('%04d-01-01', $filter_year + 1);
        $datePH = implode(',', array_fill(0, count($stockDates), '?'));

        $sqlStk = "
    SELECT 
        CONVERT(DATE, s.SOP_SDATE) AS SOP_DATE,
        SUM(CASE 
            WHEN mat.ITTY_CODE IN ('02', '13') 
            THEN t.TAG_QTY * b.QTY / 1000.0 
            ELSE t.TAG_QTY * b.QTY 
        END) AS MAT_QTY
    FROM dbo.SOP s
    INNER JOIN dbo.TAGS t ON s.SOP_ID = t.SOP_ID
    INNER JOIN dbo.ITEMS fg ON t.ITEM_ID = fg.ITEM_ID
    INNER JOIN dbo.BOM_DEFAULT b ON fg.ITEM_ID = b.PART_ID
    INNER JOIN dbo.ITEMS mat ON b.ITEM_ID = mat.ITEM_ID
    WHERE mat.ITEM_CODE = (SELECT ITEM_CODE FROM dbo.ITEMS WHERE ITEM_ID = ?)
      AND fg.ITTY_CODE = '01'
      AND CONVERT(DATE, s.SOP_SDATE) IN ($datePH)
    GROUP BY CONVERT(DATE, s.SOP_SDATE)

    UNION ALL

    SELECT 
        CONVERT(DATE, s.SOP_SDATE) AS SOP_DATE,
        SUM(t.TAG_QTY) AS MAT_QTY
    FROM dbo.SOP s
    INNER JOIN dbo.TAGS t ON s.SOP_ID = t.SOP_ID
    INNER JOIN dbo.ITEMS i ON t.ITEM_ID = i.ITEM_ID
    WHERE i.ITEM_ID = ?
      AND (LEFT(i.ITEM_CODE, 1) = '1' OR i.ITTY_CODE IN ('02', '13'))
      AND CONVERT(DATE, s.SOP_SDATE) IN ($datePH)
    GROUP BY CONVERT(DATE, s.SOP_SDATE)
";
        $stkParams = array_merge(array($selected_item_id), $stockDates, array($selected_item_id), $stockDates);
        $stStk = q($sqlStk, $stkParams);
        $stockData = array();
        while ($rS = sqlsrv_fetch_array($stStk, SQLSRV_FETCH_ASSOC)) {
            $ds = $rS['SOP_DATE'] instanceof DateTime ? $rS['SOP_DATE']->format('Y-m-d') : date('Y-m-d', strtotime($rS['SOP_DATE']));
            if (!isset($stockData[$ds])) $stockData[$ds] = 0;
            $stockData[$ds] += floatval($rS['MAT_QTY']);
        }
        sqlsrv_free_stmt($stStk);

        // Q-d: Penerimaan material per bulan (Qty dan Exact Amount)
        $sqlRcv = "
            SELECT MONTH(r.RCV_DATE) AS BULAN, 
                   ISNULL(SUM(rd.RCVD_QTY), 0) AS TOTAL_RCV,
                   ISNULL(SUM(rd.RCVD_QTY * rd.POD_PRICE * CASE WHEN ISNULL(po.PO_CUR, 'IDR') = 'IDR' THEN 1 ELSE ISNULL(cr.CURR_VRATE, 1) END), 0) AS EXACT_AMOUNT
            FROM dbo.RECEIVE r 
            INNER JOIN dbo.RECEIVE_DETAIL rd ON r.RCV_ID = rd.RCV_ID
            INNER JOIN dbo.PO po ON rd.PO_ID = po.PO_ID
            OUTER APPLY (
                SELECT TOP 1 CURR_VRATE 
                FROM dbo.CURR_RAT 
                WHERE CURR_CODE = po.PO_CUR 
                  AND r.RCV_DATE BETWEEN CURR_SDATE AND CURR_EDATE
                ORDER BY CURR_SDATE DESC
            ) cr
            WHERE rd.ITEM_ID = ? AND r.RCV_TYPE = 1 AND YEAR(r.RCV_DATE) = ?
            GROUP BY MONTH(r.RCV_DATE)
        ";
        $stRcv = q($sqlRcv, array($selected_item_id, $filter_year));
        $rcvByMonth = array();
        while ($rR = sqlsrv_fetch_array($stRcv, SQLSRV_FETCH_ASSOC)) {
            $rcvByMonth[intval($rR['BULAN'])] = array(
                'qty' => floatval($rR['TOTAL_RCV']),
                'amt' => floatval($rR['EXACT_AMOUNT'])
            );
        }
        sqlsrv_free_stmt($stRcv);

     
        // Q-e: Penjualan FG per bulan (Yang Sempat Hilang)
        // Q-e: Penjualan FG per bulan (Yang Sempat Hilang)
       // $sqlSales = "
        //    SELECT MONTH(d.DI_DATE) AS BULAN, 
         //          ISNULL(SUM(dp.QTY * dp.PART_PRICE * CASE WHEN ISNULL(o.ORDR_CURR, 'IDR') = 'IDR' THEN 1 ELSE ISNULL(cr.CURR_VRATE, 1) END), 0) AS TOTAL_SALES
          //  FROM dbo.DI d
          //  INNER JOIN dbo.DIPA_PAR dp ON d.DI_ID = dp.DI_ID
          //  INNER JOIN dbo.PRICE pr ON dp.PRICE_ID = pr.PRICE_ID
          //  INNER JOIN dbo.ORDERS o ON dp.ORDR_ID = o.ORDR_ID
          //  OUTER APPLY (
          //      SELECT TOP 1 CURR_VRATE 
          //      FROM dbo.CURR_RAT 
           //     WHERE CURR_CODE = o.ORDR_CURR 
           //       AND d.DI_DATE BETWEEN CURR_SDATE AND CURR_EDATE
           //     ORDER BY CURR_SDATE DESC
            //) cr
           // WHERE pr.PART_ID IN (
            //    SELECT DISTINCT b.PART_ID FROM dbo.BOM_DEFAULT b WHERE b.ITEM_ID = ?
           // )
           // AND YEAR(d.DI_DATE) = ?
            //GROUP BY MONTH(d.DI_DATE)
        //";
		
		
		// Q-e: Penjualan FG per bulan (Tanpa Konversi Mata Uang)
        $sqlSales = "
            SELECT MONTH(d.DI_DATE) AS BULAN, 
                   ISNULL(SUM(dp.QTY * dp.PART_PRICE), 0) AS TOTAL_SALES
            FROM dbo.DI d
            INNER JOIN dbo.DIPA_PAR dp ON d.DI_ID = dp.DI_ID
            INNER JOIN dbo.PRICE pr ON dp.PRICE_ID = pr.PRICE_ID
            WHERE pr.PART_ID IN (
                SELECT DISTINCT b.PART_ID FROM dbo.BOM_DEFAULT b WHERE b.ITEM_ID = ?
            )
            AND YEAR(d.DI_DATE) = ?
            GROUP BY MONTH(d.DI_DATE)
        ";
        $stSales = q($sqlSales, array($selected_item_id, $filter_year));
        $salesByMonth = array();
        while ($rSl = sqlsrv_fetch_array($stSales, SQLSRV_FETCH_ASSOC)) {
            $salesByMonth[intval($rSl['BULAN'])] = floatval($rSl['TOTAL_SALES']);
        }
        sqlsrv_free_stmt($stSales);
		
		
        $stSales = q($sqlSales, array($selected_item_id, $filter_year));
        $salesByMonth = array();
        while ($rSl = sqlsrv_fetch_array($stSales, SQLSRV_FETCH_ASSOC)) {
            $salesByMonth[intval($rSl['BULAN'])] = floatval($rSl['TOTAL_SALES']);
        }
        sqlsrv_free_stmt($stSales);
		
		
        $stSales = q($sqlSales, array($selected_item_id, $filter_year));
        $salesByMonth = array();
        while ($rSl = sqlsrv_fetch_array($stSales, SQLSRV_FETCH_ASSOC)) {
            $salesByMonth[intval($rSl['BULAN'])] = floatval($rSl['TOTAL_SALES']);
        }
        sqlsrv_free_stmt($stSales);
		
        $stSales = q($sqlSales, array($selected_item_id, $filter_year));
        $salesByMonth = array();
        while ($rSl = sqlsrv_fetch_array($stSales, SQLSRV_FETCH_ASSOC)) {
            $salesByMonth[intval($rSl['BULAN'])] = floatval($rSl['TOTAL_SALES']);
        }
        sqlsrv_free_stmt($stSales);

        // Ambil harga konversi aktual rata-rata per bulan untuk Stok
        $sqlPriceMonth = "
            SELECT MONTH(r.RCV_DATE) AS BULAN, 
                   AVG(rd.POD_PRICE * CASE WHEN ISNULL(po.PO_CUR, 'IDR') = 'IDR' THEN 1 ELSE ISNULL(cr.CURR_VRATE, 1) END) AS AVG_PRICE
            FROM dbo.RECEIVE r 
            INNER JOIN dbo.RECEIVE_DETAIL rd ON r.RCV_ID = rd.RCV_ID
            INNER JOIN dbo.PO po ON rd.PO_ID = po.PO_ID
            OUTER APPLY (
                SELECT TOP 1 CURR_VRATE 
                FROM dbo.CURR_RAT 
                WHERE CURR_CODE = po.PO_CUR 
                  AND r.RCV_DATE BETWEEN CURR_SDATE AND CURR_EDATE
                ORDER BY CURR_SDATE DESC
            ) cr
            WHERE rd.ITEM_ID = ? AND YEAR(r.RCV_DATE) = ?
            GROUP BY MONTH(r.RCV_DATE)
        ";
        $stPm = q($sqlPriceMonth, array($selected_item_id, $filter_year));
        $priceByMonth = array();
        while ($rPm = sqlsrv_fetch_array($stPm, SQLSRV_FETCH_ASSOC)) {
            $priceByMonth[intval($rPm['BULAN'])] = floatval($rPm['AVG_PRICE']);
        }
        sqlsrv_free_stmt($stPm);

        $currentMatPrice = $matPrice; // Fallback ke harga terakhir tahun lalu / global
        
        // Hitung per bulan
        for ($m = 1; $m <= 12; $m++) {
            // Jika ada penerimaan bulan ini, pakai harga aktual bulan ini.
            if (isset($priceByMonth[$m]) && $priceByMonth[$m] > 0) {
                $currentMatPrice = $priceByMonth[$m];
            }

            $awalDate  = sprintf('%04d-%02d-01', $filter_year, $m);
            $nm2 = $m + 1; $ny2 = $filter_year;
            if ($nm2 > 12) { $nm2 = 1; $ny2++; }
            $akhirDate = sprintf('%04d-%02d-01', $ny2, $nm2);

            $stok_awal_qty  = isset($stockData[$awalDate])  ? $stockData[$awalDate]  : 0;
            $stok_akhir_qty = isset($stockData[$akhirDate]) ? $stockData[$akhirDate] : 0;

            $stok_awal_amt  = $stok_awal_qty * $currentMatPrice;
            $stok_akhir_amt = $stok_akhir_qty * $currentMatPrice;

            $rcv_qty         = isset($rcvByMonth[$m]['qty']) ? $rcvByMonth[$m]['qty'] : 0;
            $penerimaan_amt  = isset($rcvByMonth[$m]['amt']) ? $rcvByMonth[$m]['amt'] : 0;
            
            $penjualan_amt   = isset($salesByMonth[$m]) ? $salesByMonth[$m] : 0;

            $konsumsi_aktual = $stok_awal_amt + $penerimaan_amt - $stok_akhir_amt;
            $rasio = $penjualan_amt > 0 ? ($konsumsi_aktual / $penjualan_amt * 100) : 0;

            // Perbaikan Render: Agar baris tidak kosong meskipun penjualan 0
            $has_data = ($stok_awal_amt > 0 || $stok_akhir_amt > 0 || $penerimaan_amt > 0 || $penjualan_amt > 0);

            $konsumsiBulanan[$m] = array(
                'stok_awal_qty'   => $stok_awal_qty,
                'stok_akhir_qty'  => $stok_akhir_qty,
                'stok_awal_amt'   => $stok_awal_amt,
                'penerimaan_amt'  => $penerimaan_amt,
                'stok_akhir_amt'  => $stok_akhir_amt,
                'konsumsi_aktual' => $konsumsi_aktual,
                'penjualan_amt'   => $penjualan_amt,
                'rasio'           => $rasio,
                'has_data'        => $has_data,
                'rcv_qty'         => $rcv_qty
            );
        }

        // Summary
        $rasioList = array();
        foreach ($konsumsiBulanan as $m => $kd) {
            if ($kd['has_data'] && $kd['penjualan_amt'] > 0) { // Masukkan list rasio HANYA jika ada penjualan
                $rasioList[] = $kd['rasio'];
                $konsumsiSummary['bulan_aktif']++;
            }
            if ($kd['has_data']) {
                $konsumsiSummary['total_konsumsi_amt'] += $kd['konsumsi_aktual'];
                $konsumsiSummary['total_penjualan_amt'] += $kd['penjualan_amt'];
            }
        }
        if (count($rasioList) > 0) {
            $konsumsiSummary['avg_rasio'] = array_sum($rasioList) / count($rasioList);
            $konsumsiSummary['max_rasio'] = max($rasioList);
            $konsumsiSummary['min_rasio'] = min($rasioList);
            $konsumsiSummary['total_rasio'] = $konsumsiSummary['total_penjualan_amt'] > 0
                ? ($konsumsiSummary['total_konsumsi_amt'] / $konsumsiSummary['total_penjualan_amt'] * 100)
                : 0;
            foreach ($konsumsiBulanan as $m => $kd) {
                if ($kd['has_data'] && $kd['penjualan_amt'] > 0) {
                    if ($kd['rasio'] == $konsumsiSummary['max_rasio']) $konsumsiSummary['max_rasio_bulan'] = $bulanShort[$m];
                    if ($kd['rasio'] == $konsumsiSummary['min_rasio']) $konsumsiSummary['min_rasio_bulan'] = $bulanShort[$m];
                }
            }
        }
        $konsumsiSummary['available'] = true;
    }
}





// Warna chart konsumsi per bar
 $konsumsiChartBg = array();
 $konsumsiChartBd = array();
for ($m = 1; $m <= 12; $m++) {
    if (isset($konsumsiBulanan[$m]) && $konsumsiBulanan[$m]['has_data']) {
        $rv = $konsumsiBulanan[$m]['rasio'];
        if ($rv <= 80)       { $konsumsiChartBg[$m] = 'rgba(34,197,94,0.3)';  $konsumsiChartBd[$m] = '#22c55e'; }
        elseif ($rv <= 100)  { $konsumsiChartBg[$m] = 'rgba(250,204,21,0.3)';  $konsumsiChartBd[$m] = '#facc15'; }
        else                { $konsumsiChartBg[$m] = 'rgba(255,77,106,0.3)';  $konsumsiChartBd[$m] = '#ff4d6a'; }
    } else {
        $konsumsiChartBg[$m] = 'rgba(74,85,104,0.1)';
        $konsumsiChartBd[$m] = '#4a5568';
    }
}

// ── Helpers ──
function fmtN($v, $d = 0) {
    return number_format(floatval($v), $d, ',', '.');
}
function bulanID($ym) {
    $b = array(
        '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
        '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
        '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
    );
    $p = explode('-', $ym);
    return (isset($b[$p[1]]) ? $b[$p[1]] : $p[1]) . ' ' . $p[0];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penerimaan per Item — Bulanan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root{--bg0:#0b0e13;--bg1:#111620;--bg2:#161c28;--bg3:#1a2233;--bdr:#222d3d;--bdr2:#2c3a50;--t1:#e4e9f2;--t2:#7d8aa0;--t3:#4a5568;--acc:#00d4aa;--acc-d:rgba(0,212,170,.1);--acc-g:rgba(0,212,170,.2);--blu:#4da6ff;--blu-d:rgba(77,166,255,.1);--org:#ff9f43;--org-d:rgba(255,159,67,.1);--red:#ff4d6a;--pur:#a78bfa;--pur-d:rgba(167,139,250,.1);--grn:#22c55e;--grn-d:rgba(34,197,94,.1);--ylw:#facc15;--ylw-d:rgba(250,204,21,.1);--cyn:#06b6d4;--cyn-d:rgba(6,182,212,.1)}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'DM Sans',sans-serif;background:var(--bg0);color:var(--t1);min-height:100vh}
        h1,h2,h3,h4{font-family:'Space Grotesk',sans-serif}
        body::before{content:'';position:fixed;top:-200px;right:-100px;width:600px;height:600px;background:radial-gradient(circle,rgba(0,212,170,.05) 0%,transparent 70%);border-radius:50%;pointer-events:none;z-index:0;animation:d1 22s ease-in-out infinite}
        body::after{content:'';position:fixed;bottom:-150px;left:-100px;width:500px;height:500px;background:radial-gradient(circle,rgba(77,166,255,.04) 0%,transparent 70%);border-radius:50%;pointer-events:none;z-index:0;animation:d2 28s ease-in-out infinite}
        @keyframes d1{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(-30px,20px) scale(1.08)}}
        @keyframes d2{0%,100%{transform:translate(0,0)}50%{transform:translate(25px,-15px)}}
        .mw{position:relative;z-index:1}
        .card{background:var(--bg2);border:1px solid var(--bdr);border-radius:14px}
        .search-wrap{position:relative}
        .search-input{background:var(--bg0);border:2px solid var(--bdr);border-radius:12px;padding:14px 14px 14px 44px;color:var(--t1);font-family:'DM Sans',sans-serif;font-size:15px;outline:none;width:100%;transition:border-color .2s,box-shadow .2s}
        .search-input:focus{border-color:var(--acc);box-shadow:0 0 0 4px var(--acc-d)}
        .search-input::placeholder{color:var(--t3)}
        .search-icon{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:var(--t3);font-size:16px;pointer-events:none;transition:color .2s}
        .search-input:focus ~ .search-icon{color:var(--acc)}
        .search-clear{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--t3);cursor:pointer;font-size:14px;padding:4px;display:none;transition:color .2s}
        .search-clear:hover{color:var(--t1)}
        .search-results{position:absolute;top:calc(100% + 6px);left:0;right:0;background:var(--bg2);border:1px solid var(--bdr2);border-radius:12px;max-height:320px;overflow-y:auto;z-index:100;display:none;box-shadow:0 12px 40px rgba(0,0,0,.4)}
        .search-results.show{display:block}
        .sr-item{padding:10px 16px;cursor:pointer;display:flex;align-items:center;gap:12px;transition:background .15s;border-bottom:1px solid rgba(34,45,61,.4)}
        .sr-item:last-child{border-bottom:none}
        .sr-item:hover{background:rgba(0,212,170,.06)}
        .sr-item .code{font-weight:700;font-size:13px;color:var(--acc);white-space:nowrap;min-width:100px;font-family:'Space Grotesk',sans-serif}
        .sr-item .name{font-size:13px;color:var(--t2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .sr-empty{padding:20px;text-align:center;color:var(--t3);font-size:13px}
        .sr-hint{padding:8px 16px;font-size:11px;color:var(--t3);background:rgba(0,0,0,.2);border-bottom:1px solid var(--bdr)}
        .item-bar{background:linear-gradient(135deg,rgba(0,212,170,.08),rgba(77,166,255,.05));border:1px solid rgba(0,212,170,.2);border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap}
        .item-code{font-family:'Space Grotesk',sans-serif;font-size:20px;font-weight:700;color:var(--acc)}
        .item-name{font-size:15px;color:var(--t2)}
        .item-sep{width:1px;height:28px;background:var(--bdr);flex-shrink:0}
        .scard{position:relative;overflow:hidden}
        .scard::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:14px 14px 0 0}
        .scard.c1::before{background:var(--acc)} .scard.c2::before{background:var(--org)} .scard.c3::before{background:var(--blu)} .scard.c4::before{background:var(--pur)}
        .scard.kc1::before{background:var(--cyn)} .scard.kc2::before{background:var(--grn)} .scard.kc3::before{background:var(--ylw)} .scard.kc4::before{background:var(--org)}
        .dt{width:100%;border-collapse:separate;border-spacing:0}
        .dt thead th{padding:11px 14px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:var(--t3);border-bottom:1px solid var(--bdr);background:rgba(0,0,0,.25);position:sticky;top:0;z-index:2}
        .dt thead th:first-child{border-radius:10px 0 0 0} .dt thead th:last-child{border-radius:0 10px 0 0}
        .dt tbody td{padding:10px 14px;font-size:13px;border-bottom:1px solid rgba(34,45,61,.5);vertical-align:middle}
        .dt tbody tr{transition:background .15s} .dt tbody tr:hover{background:rgba(0,212,170,.03)}
        .month-hdr td{background:var(--bg3)!important;padding:12px 14px!important;border-bottom:2px solid var(--bdr2)!important;font-weight:700;font-size:14px}
        .month-sub td{background:rgba(0,212,170,.04)!important;padding:10px 14px!important;border-bottom:1px solid var(--bdr)!important;font-weight:600}
        .num-r{text-align:right;font-variant-numeric:tabular-nums}
        .curr-badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:var(--org-d);color:var(--org)}
        .bom-link{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600;background:var(--pur-d);color:var(--pur);text-decoration:none;transition:all .2s;border:1px solid rgba(167,139,250,.15)}
        .bom-link:hover{background:rgba(167,139,250,.2);border-color:rgba(167,139,250,.35);transform:translateY(-1px)}
        .tbl-wrap{overflow-x:auto;border-radius:12px;border:1px solid var(--bdr);max-height:60vh;overflow-y:auto}
        .btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:8px;font-family:'DM Sans',sans-serif;font-weight:600;font-size:13px;cursor:pointer;border:none;transition:all .2s;white-space:nowrap}
        .btn-ghost{background:transparent;color:var(--t2);border:1px solid var(--bdr)} .btn-ghost:hover{background:rgba(255,255,255,.04);color:var(--t1);border-color:var(--bdr2)}
        .btn-sm{padding:6px 14px;font-size:12px}
        .btn-change{background:var(--pur-d);color:var(--pur);border:1px solid rgba(167,139,250,.2);padding:8px 16px;font-size:13px;border-radius:8px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s}
        .btn-change:hover{background:rgba(167,139,250,.2);border-color:rgba(167,139,250,.35)}
        .year-pill{padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid var(--bdr);background:transparent;color:var(--t2);transition:all .2s;font-family:'Space Grotesk',sans-serif;text-decoration:none;display:inline-block}
        .year-pill:hover{border-color:var(--bdr2);color:var(--t1)}
        .year-pill.active{background:var(--acc);color:var(--bg0);border-color:var(--acc)}
        .empty-box{text-align:center;padding:60px 20px;color:var(--t3)}
        .empty-box i{font-size:44px;margin-bottom:14px;opacity:.25;display:block}
        .toast-wrap{position:fixed;top:16px;right:16px;z-index:9999}
        .toast-msg{padding:10px 18px;border-radius:8px;font-size:13px;font-weight:500;margin-bottom:6px;animation:si .3s ease;display:flex;align-items:center;gap:8px;box-shadow:0 8px 25px rgba(0,0,0,.3)}
        .toast-info{background:rgba(77,166,255,.15);border:1px solid rgba(77,166,255,.3);color:var(--blu)}
        @keyframes si{from{transform:translateX(80px);opacity:0}to{transform:translateX(0);opacity:1}}

        /* Konsumsi section */
        .konsumsi-section{border:1px solid var(--bdr);border-radius:14px;overflow:hidden;background:var(--bg2);margin-bottom:20px}
        .konsumsi-section-header{padding:16px 20px;background:var(--bg3);border-bottom:1px solid var(--bdr);display:flex;align-items:center;gap:12px;flex-wrap:wrap}
        .konsumsi-section-icon{width:38px;height:38px;border-radius:10px;background:var(--cyn-d);display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .konsumsi-section-body{padding:20px}
        .konsumsi-legend{display:flex;align-items:center;gap:16px;flex-wrap:wrap;padding:10px 14px;background:rgba(0,0,0,.12);border-radius:8px;margin-bottom:16px}
        .konsumsi-legend-item{display:inline-flex;align-items:center;gap:5px;font-size:11px;color:var(--t2)}
        .konsumsi-legend-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0}
        .konsumsi-mini-table{width:100%;border-collapse:separate;border-spacing:0;margin-top:16px;border:1px solid var(--bdr);border-radius:10px;overflow:hidden}
        .konsumsi-mini-table thead th{padding:8px 10px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);border-bottom:1px solid var(--bdr);background:rgba(0,0,0,.25)}
        .konsumsi-mini-table tbody td{padding:7px 10px;font-size:12px;border-bottom:1px solid rgba(34,45,61,.4);vertical-align:middle}
        .konsumsi-mini-table tbody tr:last-child td{border-bottom:none}
        .konsumsi-mini-table tbody tr:hover{background:rgba(0,212,170,.03)}
        .konsumsi-mini-table tfoot td{padding:8px 10px;font-size:12px;font-weight:700;border-top:2px solid var(--bdr2);background:rgba(0,0,0,.1)}
        .rasio-cell{font-weight:700;font-family:'Space Grotesk',sans-serif;font-size:13px}
        .rasio-good{color:var(--grn)} .rasio-warn{color:var(--ylw)} .rasio-bad{color:var(--red)} .rasio-none{color:var(--t3)}
        .konsumsi-info-bar{display:flex;align-items:center;gap:6px;padding:8px 14px;background:var(--cyn-d);border:1px solid rgba(6,182,212,.15);border-radius:8px;font-size:12px;color:var(--cyn);font-weight:600;margin-bottom:16px;flex-wrap:wrap}

        @media print{.no-print{display:none!important}body{background:#fff;color:#111}body::before,body::after{display:none}.card,.konsumsi-section{border-color:#ddd;background:#fafafa}.dt thead th,.konsumsi-mini-table thead th{background:#f0f0f0;color:#333}.month-hdr td{background:#eee!important}.month-sub td{background:#f5f5f5!important;color:#111!important}.bom-link{color:#555;background:#eee;border-color:#ccc}.curr-badge{color:#b45309;background:#fef3c7}.item-bar{background:#f0faf7!important;border-color:#ccc!important}.konsumsi-section-header{background:#eee}.rasio-good{color:#047857}.rasio-warn{color:#a16207}.rasio-bad{color:#b91c1c}}
        @media(max-width:768px){.dt thead th,.dt tbody td{padding:8px 10px;font-size:12px}.item-code{font-size:16px}.konsumsi-mini-table thead th,.konsumsi-mini-table tbody td{padding:6px 8px;font-size:11px}}
        ::-webkit-scrollbar{width:5px;height:5px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--bdr);border-radius:3px}
    </style>
</head>
<body>
<div class="mw max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">

    <header class="mb-6">
        <div class="flex items-center gap-3 mb-1">
            <div style="width:38px;height:38px;border-radius:10px;background:var(--acc-d);display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid fa-boxes-stacked" style="color:var(--acc);font-size:16px;"></i>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight">Penerimaan Barang per Item</h1>
                <p style="color:var(--t2);font-size:13px;">Pilih item untuk melihat penerimaan bulanan dari supplier</p>
            </div>
        </div>
    </header>

    <!-- Pilih Item -->
    <div class="card p-5 sm:p-6 mb-5">
        <div class="flex flex-col lg:flex-row gap-5">
            <div class="flex-1">
                <label style="font-size:11px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:8px;">
                    <i class="fa-solid fa-magnifying-glass" style="margin-right:4px;"></i> Cari Item (kode / nama)
                </label>
                <div class="search-wrap">
                    <input type="text" class="search-input" id="itemSearch" placeholder="Ketik kode atau nama item..." autocomplete="off">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <button class="search-clear" id="clearBtn" onclick="clearSearch()"><i class="fa-solid fa-xmark"></i></button>
                    <div class="search-results" id="searchResults"></div>
                </div>
            </div>
            <div style="width:200px;flex-shrink:0;">
                <label style="font-size:11px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:8px;">
                    <i class="fa-solid fa-calendar" style="margin-right:4px;"></i> Tahun
                </label>
                <div class="flex gap-2 flex-wrap">
                    <?php foreach ($years as $y): ?>
                    <a href="?item_id=<?php echo urlencode($selected_item_id); ?>&year=<?php echo $y; ?>"
                       class="year-pill <?php echo $y === $filter_year ? 'active' : ''; ?>"><?php echo $y; ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div style="margin-top:10px;font-size:11px;color:var(--t3);">
            <i class="fa-solid fa-database" style="margin-right:4px;"></i>
            <?php echo number_format(count($itemsJS)); ?> item tersedia dengan data penerimaan
        </div>
    </div>

    <?php if ($selected_item_id !== '' && $itemInfo): ?>

    <!-- Info Item Terpilih -->
    <div class="item-bar mb-5">
        <div>
            <div class="item-code"><?php echo htmlspecialchars($itemInfo['ITEM_CODE']); ?></div>
            <div class="item-name"><?php echo htmlspecialchars($itemInfo['ITEM_NAME']); ?></div>
        </div>
        <div class="item-sep"></div>
        <a href="bom_trace.php?item_id=<?php echo urlencode($selected_item_id); ?>&item_code=<?php echo urlencode($itemInfo['ITEM_CODE']); ?>"
           class="bom-link" style="padding:8px 16px;font-size:13px;">
            <i class="fa-solid fa-diagram-project"></i> Trace BOM ke Barang Jadi
        </a>
        <div class="item-sep"></div>
        <button class="btn-change" onclick="clearItem()">
            <i class="fa-solid fa-arrow-rotate-left" style="margin-right:4px;"></i> Ganti Item
        </button>
    </div>

    <?php if (empty($monthlyData)): ?>
    <div class="card">
        <div class="empty-box">
            <i class="fa-solid fa-calendar-xmark"></i>
            <p style="font-size:15px;font-weight:500;margin-bottom:4px;">Tidak ada penerimaan tahun <?php echo $filter_year; ?></p>
            <p style="font-size:13px;">Item ini tidak memiliki data penerimaan pada tahun yang dipilih</p>
        </div>
    </div>
    <?php else: ?>

    <!-- Summary Cards Penerimaan -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <div class="card scard c1 p-4">
            <div style="font-size:11px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Total Qty Diterima</div>
            <div style="font-size:24px;font-weight:700;font-family:'Space Grotesk',sans-serif;"><?php echo fmtN($grandQty, 2); ?></div>
        </div>
        <div class="card scard c2 p-4">
            <div style="font-size:11px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">
                Total Amount <span class="curr-badge"><?php echo htmlspecialchars($primaryCur); ?></span>
            </div>
            <div style="font-size:22px;font-weight:700;font-family:'Space Grotesk',sans-serif;"><?php echo fmtN(isset($grandTotal[$primaryCur]) ? $grandTotal[$primaryCur] : 0, 2); ?></div>
            <?php if (count($grandTotal) > 1): ?>
            <div style="font-size:11px;color:var(--t3);margin-top:4px;">
                <?php foreach ($grandTotal as $c => $v): ?>
                    <?php if ($c !== $primaryCur): ?>
                    + <?php echo fmtN($v, 2); ?> <span class="curr-badge"><?php echo htmlspecialchars($c); ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="card scard c3 p-4">
            <div style="font-size:11px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Rata-rata Harga</div>
            <div style="font-size:22px;font-weight:700;font-family:'Space Grotesk',sans-serif;"><?php echo fmtN($avgPrice, 2); ?></div>
            <div style="font-size:11px;color:var(--t3);margin-top:4px;">Weighted average</div>
        </div>
        <div class="card scard c4 p-4">
            <div style="font-size:11px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Jumlah Supplier</div>
            <div style="font-size:24px;font-weight:700;font-family:'Space Grotesk',sans-serif;"><?php echo number_format($totalSuppliers); ?></div>
            <div style="font-size:11px;color:var(--t3);margin-top:4px;">
                <?php echo count($monthlyData); ?> bulan aktif
            </div>
        </div>
    </div>

    <!-- Chart Penerimaan -->
    <div class="card p-5 mb-5">
        <h3 style="font-size:14px;font-weight:600;margin-bottom:14px;color:var(--t2);">
            <i class="fa-solid fa-chart-column" style="color:var(--acc);margin-right:6px;"></i>
            Tren Penerimaan Bulanan — <?php echo htmlspecialchars($itemInfo['ITEM_CODE']); ?> (<?php echo $filter_year; ?>)
        </h3>
        <div style="position:relative;height:280px;">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         ANALISIS KONSUMSI MATERIAL % TAHUNAN (Jan–Dec)
         ═══════════════════════════════════════════════════════════════ -->
    <?php if ($konsumsiSummary['available']): ?>
    <div class="konsumsi-section">
        <div class="konsumsi-section-header">
            <div class="konsumsi-section-icon">
                <i class="fa-solid fa-flask" style="color:var(--cyn);font-size:16px;"></i>
            </div>
            <div style="flex:1;min-width:200px;">
                <h3 style="font-size:15px;font-weight:700;">Total Material Konsumsi — Tahunan <?php echo $filter_year; ?></h3>
                <p style="font-size:12px;color:var(--t2);">Rasio = (Stok Awal + Penerimaan − Stok Akhir) ÷ Penjualan × 100%</p>
            </div>
            <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:6px;font-size:11px;font-weight:600;background:var(--cyn-d);color:var(--cyn);border:1px solid rgba(6,182,212,.15);">
                <i class="fa-solid fa-link" style="font-size:10px;"></i>
                <?php echo $konsumsiSummary['parent_count']; ?> FG
            </span>
        </div>
        <div class="konsumsi-section-body">

            <!-- Info harga material -->
            <div class="konsumsi-info-bar">
                <i class="fa-solid fa-tag" style="font-size:11px;"></i>
                Harga: <strong><?php echo htmlspecialchars($konsumsiSummary['mat_cur']); ?> <?php echo fmtN($konsumsiSummary['mat_price'], 2); ?></strong> / <?php echo $konsumsiSummary['mat_unit']; ?>
<?php if ($konsumsiSummary['is_konversi']): ?>
<span style="margin-left:8px;opacity:.7;">· BOM (gr) ÷ 1000 → kg [ITTY <?php echo htmlspecialchars($konsumsiSummary['mat_itty']); ?>]</span>
<?php else: ?>
<span style="margin-left:8px;opacity:.7;">· BOM langsung × Harga [ITTY <?php echo htmlspecialchars($konsumsiSummary['mat_itty']); ?>]</span>
<?php endif; ?>
            </div>

            <!-- Summary Cards Konsumsi -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <div class="card scard kc1 p-4">
                    <div style="font-size:11px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Total Rasio Tahunan</div>
                    <div style="font-size:26px;font-weight:700;font-family:'Space Grotesk',sans-serif;color:<?php
                        $tr = $konsumsiSummary['total_rasio'];
                        echo $tr <= 80 ? 'var(--grn)' : ($tr <= 100 ? 'var(--ylw)' : 'var(--red)');
                    ?>;">
                        <?php echo fmtN($konsumsiSummary['total_rasio'], 2); ?>%
                    </div>
                    <div style="font-size:11px;color:var(--t3);margin-top:4px;">Konsumsi ÷ Penjualan</div>
                </div>
                <div class="card scard kc2 p-4">
                    <div style="font-size:11px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Rata-rata Bulanan</div>
                    <div style="font-size:26px;font-weight:700;font-family:'Space Grotesk',sans-serif;color:var(--cyn);">
                        <?php echo fmtN($konsumsiSummary['avg_rasio'], 2); ?>%
                    </div>
                    <div style="font-size:11px;color:var(--t3);margin-top:4px;"><?php echo $konsumsiSummary['bulan_aktif']; ?> bulan aktif</div>
                </div>
                <div class="card scard kc3 p-4">
                    <div style="font-size:11px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Total Konsumsi</div>
                    <div style="font-size:20px;font-weight:700;font-family:'Space Grotesk',sans-serif;color:var(--ylw);">
                        <?php echo fmtN($konsumsiSummary['total_konsumsi_amt'], 2); ?>
                    </div>
                    <div style="font-size:11px;color:var(--t3);margin-top:4px;">
                        Max: <?php echo $konsumsiSummary['max_rasio_bulan']; ?> (<?php echo fmtN($konsumsiSummary['max_rasio'],1); ?>%) · Min: <?php echo $konsumsiSummary['min_rasio_bulan']; ?> (<?php echo fmtN($konsumsiSummary['min_rasio'],1); ?>%)
                    </div>
                </div>
                <div class="card scard kc4 p-4">
                    <div style="font-size:11px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Total Penjualan FG</div>
                    <div style="font-size:20px;font-weight:700;font-family:'Space Grotesk',sans-serif;color:var(--grn);">
                        <?php echo fmtN($konsumsiSummary['total_penjualan_amt'], 2); ?>
                    </div>
                    <div style="font-size:11px;color:var(--t3);margin-top:4px;">Amount dari <?php echo $konsumsiSummary['parent_count']; ?> barang jadi</div>
                </div>
            </div>

            <!-- Legend -->
            <div class="konsumsi-legend">
                <span style="font-size:11px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.05em;">Keterangan Warna:</span>
                <span class="konsumsi-legend-item"><span class="konsumsi-legend-dot" style="background:var(--grn);"></span> Sehat (≤80%)</span>
                <span class="konsumsi-legend-item"><span class="konsumsi-legend-dot" style="background:var(--ylw);"></span> Waspada (80–100%)</span>
                <span class="konsumsi-legend-item"><span class="konsumsi-legend-dot" style="background:var(--red);"></span> Rugi (>100%)</span>
                <span class="konsumsi-legend-item"><span class="konsumsi-legend-dot" style="background:var(--t3);opacity:.4;"></span> Tanpa data</span>
                <span style="margin-left:auto;font-size:11px;color:var(--t3);">--- Garis 100% &nbsp;·&nbsp; ── Rata-rata</span>
            </div>

            <!-- Chart Konsumsi -->
            <div style="position:relative;height:300px;">
                <canvas id="konsumsiChart"></canvas>
            </div>

            <!-- Tabel Detail Konsumsi Bulanan -->
            <div style="margin-top:20px;">
                <div style="font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">
                    <i class="fa-solid fa-table-list" style="color:var(--cyn);margin-right:4px;"></i>
                    Detail Konsumsi per Bulan
                </div>
                <div style="overflow-x:auto;border-radius:10px;">
                    <table class="konsumsi-mini-table">
                        <thead>
                            <tr>
                                <th>Bulan</th>
                                <th class="num-r">Stok Awal<br><span style="font-weight:400;font-size:9px;">(Amount)</span></th>
                                <th class="num-r">Penerimaan<br><span style="font-weight:400;font-size:9px;">(Amount)</span></th>
                                <th class="num-r">Stok Akhir<br><span style="font-weight:400;font-size:9px;">(Amount)</span></th>
                                <th class="num-r">Konsumsi Aktual<br><span style="font-weight:400;font-size:9px;">(Amount)</span></th>
                                <th class="num-r">Penjualan FG<br><span style="font-weight:400;font-size:9px;">(Amount)</span></th>
                                <th class="num-r" style="min-width:90px;">Rasio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sumSA = 0; $sumPA = 0; $sumSK = 0; $sumKA = 0; $sumPJ = 0;
                            for ($m = 1; $m <= 12; $m++):
                                $kd = isset($konsumsiBulanan[$m]) ? $konsumsiBulanan[$m] : array('stok_awal_amt'=>0,'penerimaan_amt'=>0,'stok_akhir_amt'=>0,'konsumsi_aktual'=>0,'penjualan_amt'=>0,'rasio'=>0,'has_data'=>false);
                                $sumSA += $kd['stok_awal_amt'];
                                $sumPA += $kd['penerimaan_amt'];
                                $sumSK += $kd['stok_akhir_amt'];
                                $sumKA += $kd['konsumsi_aktual'];
                                $sumPJ += $kd['penjualan_amt'];
                                $rv = $kd['rasio'];
                                if ($kd['has_data']) {
                                    $rc = $rv <= 80 ? 'rasio-good' : ($rv <= 100 ? 'rasio-warn' : 'rasio-bad');
                                    $rvTxt = fmtN($rv, 2) . '%';
                                } else {
                                    $rc = 'rasio-none';
                                    $rvTxt = '—';
                                }
                            ?>
                            <tr>
                                <td style="font-weight:600;white-space:nowrap;"><?php echo $bulanShort[$m]; ?> <?php echo $filter_year; ?></td>
                                <td class="num-r"><?php echo $kd['has_data'] ? fmtN($kd['stok_awal_amt'],2) : '<span style="color:var(--t3);">—</span>'; ?></td>
                                <td class="num-r"><?php echo $kd['has_data'] ? fmtN($kd['penerimaan_amt'],2) : '<span style="color:var(--t3);">—</span>'; ?></td>
                                <td class="num-r"><?php echo $kd['has_data'] ? fmtN($kd['stok_akhir_amt'],2) : '<span style="color:var(--t3);">—</span>'; ?></td>
                                <td class="num-r" style="color:var(--cyn);font-weight:600;"><?php echo $kd['has_data'] ? fmtN($kd['konsumsi_aktual'],2) : '<span style="color:var(--t3);">—</span>'; ?></td>
                                <td class="num-r" style="color:var(--grn);"><?php echo $kd['has_data'] ? fmtN($kd['penjualan_amt'],2) : '<span style="color:var(--t3);">—</span>'; ?></td>
                                <td class="num-r"><span class="rasio-cell <?php echo $rc; ?>"><?php echo $rvTxt; ?></span></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td style="color:var(--t2);">TOTAL</td>
                                <td class="num-r" style="color:var(--cyn);"><?php echo fmtN($sumSA, 2); ?></td>
                                <td class="num-r" style="color:var(--cyn);"><?php echo fmtN($sumPA, 2); ?></td>
                                <td class="num-r" style="color:var(--cyn);"><?php echo fmtN($sumSK, 2); ?></td>
                                <td class="num-r" style="color:var(--cyn);font-size:13px;"><?php echo fmtN($sumKA, 2); ?></td>
                                <td class="num-r" style="color:var(--grn);font-size:13px;"><?php echo fmtN($sumPJ, 2); ?></td>
                                <td class="num-r">
                                    <span class="rasio-cell" style="color:<?php
                                        $ftr = $sumPJ > 0 ? ($sumKA / $sumPJ * 100) : 0;
                                        echo $ftr <= 80 ? 'var(--grn)' : ($ftr <= 100 ? 'var(--ylw)' : 'var(--red)');
                                    ?>;"><?php echo $sumPJ > 0 ? fmtN($ftr, 2) . '%' : '—'; ?></span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Catatan -->
            <div style="margin-top:12px;font-size:11px;color:var(--t3);display:flex;align-items:flex-start;gap:6px;">
                <i class="fa-solid fa-info-circle" style="margin-top:2px;flex-shrink:0;"></i>
                <span>
                    Rasio > 100% berarti biaya material melebihi omzet penjualan FG (rugi pada material tersebut).
                    Stok FG diambil dari SOP/TAGS. Harga material: harga penerimaan terakhir.
                    <?php if ($konsumsiSummary['total_rasio'] > 100): ?>
                    <strong style="color:var(--red);">Tahun ini total rasio <?php echo fmtN($konsumsiSummary['total_rasio'],1); ?>% — perlu tinjauan harga jual atau efisiensi BOM.</strong>
                    <?php elseif ($konsumsiSummary['total_rasio'] > 80): ?>
                    <strong style="color:var(--ylw);">Margin material tipis (<?php echo fmtN($konsumsiSummary['total_rasio'],1); ?>%). Overhead & tenaga kerja belum terhitung.</strong>
                    <?php else: ?>
                    <strong style="color:var(--grn);">Margin material sehat (<?php echo fmtN($konsumsiSummary['total_rasio'],1); ?>%).</strong>
                    <?php endif; ?>
                </span>
            </div>

        </div>
    </div>
    <?php elseif ($konsumsiSummary['parent_count'] === 0): ?>
    <div class="card mb-5">
        <div class="empty-box" style="padding:40px 20px;">
            <i class="fa-solid fa-link-slash"></i>
            <p style="font-size:14px;font-weight:500;color:var(--t2);margin-bottom:4px;">Konsumsi Material Tidak Tersedia</p>
            <p style="font-size:12px;">Material ini tidak terdaftar di BOM_DEFAULT barang jadi (ITTY=01)</p>
        </div>
    </div>
    <?php elseif ($konsumsiSummary['mat_price'] <= 0): ?>
    <div class="card mb-5">
        <div class="empty-box" style="padding:40px 20px;">
            <i class="fa-solid fa-tag"></i>
            <p style="font-size:14px;font-weight:500;color:var(--t2);margin-bottom:4px;">Konsumsi Material Tidak Tersedia</p>
            <p style="font-size:12px;">Tidak ditemukan harga penerimaan untuk material ini</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabel Detail Penerimaan -->
    <div class="card" style="overflow:hidden;">
        <div class="p-4 flex items-center justify-between" style="border-bottom:1px solid var(--bdr);">
            <h3 style="font-size:14px;font-weight:600;color:var(--t2);">
                <i class="fa-solid fa-table-list" style="color:var(--blu);margin-right:6px;"></i>
                Detail per Bulan & Supplier
            </h3>
            <button class="btn btn-ghost btn-sm no-print" onclick="exportCSV()">
                <i class="fa-solid fa-file-csv"></i> Export
            </button>
        </div>
        <div class="tbl-wrap" id="tableContainer">
            <table class="dt" id="mainTable">
                <thead>
                    <tr>
                        <th style="width:35px;">No</th>
                        <th>Supplier</th>
                        <th class="num-r">Qty</th>
                        <th class="num-r">Rata-rata Harga</th>
                        <th>Cur</th>
                        <th class="num-r">Amount</th>
                        <th style="text-align:center;">RCPT</th>
                        <th class="no-print" style="width:90px;">BOM</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rowNum = 0;
                    foreach ($monthlyData as $bulan => $rows):
                        $monthSubs = array();
                        foreach ($rows as $r) {
                            $rc = $r['CUR'];
                            if (!isset($monthSubs[$rc])) $monthSubs[$rc] = 0;
                            $monthSubs[$rc] += $r['AMOUNT'];
                        }
                    ?>
                    <tr class="month-hdr">
                        <td colspan="8">
                            <i class="fa-solid fa-calendar-days" style="color:var(--acc);margin-right:8px;font-size:13px;"></i>
                            <?php echo bulanID($bulan); ?>
                            <span style="color:var(--t3);font-weight:400;margin-left:10px;font-size:12px;">
                                <?php echo count($rows); ?> supplier
                            </span>
                        </td>
                    </tr>

                    <?php foreach ($rows as $r):
                        $rowNum++;
                    ?>
                    <tr>
                        <td style="text-align:center;color:var(--t3);font-size:12px;"><?php echo $rowNum; ?></td>
                        <td>
                            <span style="font-weight:600;"><?php echo htmlspecialchars($r['SUP_CODE']); ?></span>
                            <span style="color:var(--t3);margin-left:6px;font-size:12px;"><?php echo htmlspecialchars($r['SUP_COMP']); ?></span>
                        </td>
                        <td class="num-r"><?php echo fmtN($r['QTY'], 2); ?></td>
                        <td class="num-r"><?php echo fmtN($r['AVG_PRICE'], 2); ?></td>
                        <td><span class="curr-badge"><?php echo htmlspecialchars($r['CUR']); ?></span></td>
                        <td class="num-r" style="font-weight:600;"><?php echo fmtN($r['AMOUNT'], 2); ?></td>
                        <td style="text-align:center;color:var(--t2);"><?php echo $r['COUNT']; ?>x</td>
                        <td class="no-print">
                            <a href="bom_trace.php?item_id=<?php echo urlencode($selected_item_id); ?>&item_code=<?php echo urlencode($itemInfo['ITEM_CODE']); ?>&sup_code=<?php echo urlencode($r['SUP_CODE']); ?>&bulan=<?php echo urlencode($bulan); ?>"
                               class="bom-link" title="Trace BOM">
                                <i class="fa-solid fa-diagram-project" style="font-size:11px;"></i> BOM
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <tr class="month-sub">
                        <td colspan="5" style="text-align:right;color:var(--acc);">Subtotal <?php echo bulanID($bulan); ?></td>
                        <td class="num-r" style="color:var(--acc);font-size:14px;">
                            <?php
                            $first = true;
                            foreach ($monthSubs as $c => $v):
                                if (!$first) echo '<br>';
                                $first = false;
                            ?>
                                <span class="curr-badge" style="margin-right:4px;"><?php echo htmlspecialchars($c); ?></span>
                                <?php echo fmtN($v, 2); ?>
                            <?php endforeach; ?>
                        </td>
                        <td></td>
                        <td class="no-print"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="padding:14px 18px;border-top:2px solid var(--bdr2);background:rgba(0,0,0,.2);display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between;">
            <span style="font-size:13px;font-weight:600;color:var(--t2);">
                <i class="fa-solid fa-calculator" style="margin-right:6px;color:var(--acc);"></i>
                Grand Total <?php echo $filter_year; ?> — <?php echo htmlspecialchars($itemInfo['ITEM_CODE']); ?>
            </span>
            <div style="display:flex;gap:20px;flex-wrap:wrap;">
                <?php foreach ($grandTotal as $c => $v): ?>
                <span style="font-size:15px;font-weight:700;font-family:'Space Grotesk',sans-serif;">
                    <span class="curr-badge" style="margin-right:6px;"><?php echo htmlspecialchars($c); ?></span>
                    <?php echo fmtN($v, 2); ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <?php else: ?>

    <div class="card">
        <div class="empty-box">
            <i class="fa-solid fa-hand-pointer"></i>
            <p style="font-size:16px;font-weight:600;margin-bottom:6px;color:var(--t2);">Pilih Item untuk Memulai</p>
            <p style="font-size:13px;max-width:400px;margin:0 auto;">Ketik kode atau nama item di kolom pencarian di atas, lalu klik item yang dimaksud untuk melihat data penerimaan bulanan.</p>
        </div>
    </div>

    <?php endif; ?>
<footer class="mt-6 text-center" style="color:var(--t3);font-size:11px;">
        <p>Penerimaan per Item — <?php echo date('d M Y H:i'); ?> — Plant: <?php echo htmlspecialchars(isset($active_plant) ? $active_plant : ''); ?></p>
    </footer>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
var ITEMS = <?php echo json_encode($itemsJS, JSON_UNESCAPED_UNICODE); ?>;
var CURRENT_ITEM_ID = "<?php echo addslashes($selected_item_id); ?>";
var CURRENT_YEAR = <?php echo $filter_year; ?>;

var searchInput   = document.getElementById('itemSearch');
var searchResults = document.getElementById('searchResults');
var clearBtn      = document.getElementById('clearBtn');

function escH(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

searchInput.addEventListener('input', function() {
    var q = this.value.toLowerCase().trim();
    clearBtn.style.display = q.length > 0 ? 'block' : 'none';
    if (q.length < 1) { searchResults.classList.remove('show'); return; }

    var filtered = [];
    for (var i = 0; i < ITEMS.length; i++) {
        if (ITEMS[i].code.toLowerCase().indexOf(q) !== -1 || ITEMS[i].name.toLowerCase().indexOf(q) !== -1) {
            filtered.push(ITEMS[i]);
            if (filtered.length >= 60) break;
        }
    }

    if (filtered.length === 0) {
        searchResults.innerHTML = '<div class="sr-empty"><i class="fa-solid fa-circle-exclamation" style="margin-right:6px;"></i>Tidak ditemukan</div>';
    } else {
        var html = '<div class="sr-hint">Klik untuk melihat data penerimaan (' + filtered.length + ' ditemukan)</div>';
        for (var j = 0; j < filtered.length; j++) {
            html += '<div class="sr-item" data-id="' + escH(filtered[j].id) + '" data-code="' + escH(filtered[j].code) + '">'
                + '<span class="code">' + escH(filtered[j].code) + '</span>'
                + '<span class="name">' + escH(filtered[j].name) + '</span>'
                + '</div>';
        }
        searchResults.innerHTML = html;
    }
    searchResults.classList.add('show');
});

searchResults.addEventListener('click', function(e) {
    var item = e.target.closest('.sr-item');
    if (!item) return;
    selectItem(item.dataset.id);
});

searchInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        var first = searchResults.querySelector('.sr-item');
        if (first) { e.preventDefault(); selectItem(first.dataset.id); }
    }
    if (e.key === 'Escape') { searchResults.classList.remove('show'); searchInput.blur(); }
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-wrap')) searchResults.classList.remove('show');
});

function selectItem(id) {
    searchResults.classList.remove('show');
    window.location.href = '?item_id=' + encodeURIComponent(id) + '&year=' + CURRENT_YEAR;
}
function clearSearch() {
    searchInput.value = '';
    clearBtn.style.display = 'none';
    searchResults.classList.remove('show');
    searchInput.focus();
}
function clearItem() {
    window.location.href = '?year=' + CURRENT_YEAR;
}

<?php if ($selected_item_id !== '' && $itemInfo): ?>
searchInput.value = "<?php echo addslashes($itemInfo['ITEM_CODE'] . ' \u2014 ' . $itemInfo['ITEM_NAME']); ?>";
clearBtn.style.display = 'block';
<?php endif; ?>

<?php if ($selected_item_id !== '' && !empty($chartLabels)): ?>
// ── Chart 1: Tren Penerimaan ──
new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chartLabels, JSON_UNESCAPED_UNICODE); ?>,
        datasets: [
            {
                label: 'Amount (<?php echo addslashes($primaryCur); ?>)',
                data: <?php echo json_encode($chartValues); ?>,
                backgroundColor: 'rgba(0, 212, 170, 0.25)',
                borderColor: '#00d4aa',
                borderWidth: 2,
                borderRadius: 6,
                yAxisID: 'y',
                order: 2
            },
            {
                label: 'Rata-rata Harga',
                data: <?php echo json_encode($chartAvgPrices); ?>,
                type: 'line',
                borderColor: '#ff9f43',
                backgroundColor: 'rgba(255, 159, 67, 0.08)',
                borderWidth: 2.5,
                pointRadius: 5,
                pointBackgroundColor: '#ff9f43',
                pointBorderColor: '#1a2233',
                pointBorderWidth: 2,
                pointHoverRadius: 7,
                tension: 0.35,
                fill: true,
                yAxisID: 'y1',
                order: 1
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { labels: { color: '#7d8aa0', font: { family: 'DM Sans', size: 12 }, usePointStyle: true, pointStyleWidth: 12 } },
            tooltip: {
                backgroundColor: '#1a2233', titleColor: '#e4e9f2', bodyColor: '#7d8aa0',
                borderColor: '#2c3a50', borderWidth: 1, cornerRadius: 8, padding: 12,
                titleFont: { family: 'Space Grotesk', weight: '600' }, bodyFont: { family: 'DM Sans' },
                callbacks: {
                    label: function(ctx) {
                        return ctx.dataset.label + ': ' + Number(ctx.raw).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    }
                }
            }
        },
        scales: {
            y: {
                position: 'left',
                title: { display: true, text: 'Amount (<?php echo addslashes($primaryCur); ?>)', color: '#7d8aa0', font: { family: 'DM Sans', size: 12 } },
                grid: { color: 'rgba(255,255,255,0.04)' },
                ticks: { color: '#7d8aa0', font: { size: 11 }, callback: function(v) { return v >= 1e6 ? (v/1e6).toFixed(1)+'M' : v >= 1e3 ? (v/1e3).toFixed(0)+'K' : v; } }
            },
            y1: {
                position: 'right',
                title: { display: true, text: 'Avg Price', color: '#ff9f43', font: { family: 'DM Sans', size: 12 } },
                grid: { drawOnChartArea: false },
                ticks: { color: '#ff9f43', font: { size: 11 }, callback: function(v) { return v >= 1e6 ? (v/1e6).toFixed(1)+'M' : v >= 1e3 ? (v/1e3).toFixed(0)+'K' : v; } }
            },
            x: {
                grid: { color: 'rgba(255,255,255,0.04)' },
                ticks: { color: '#7d8aa0', font: { family: 'DM Sans', size: 11 } }
            }
        }
    }
});
<?php endif; ?>

<?php if ($konsumsiSummary['available']): ?>
// ── Chart 2: Konsumsi Material % Tahunan ──
(function() {
    var ctx = document.getElementById('konsumsiChart');
    if (!ctx) return;

    var labels = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    var data = <?php echo json_encode(array_values(array_map(function($k){ return isset($konsumsiBulanan[$k]) ? round($konsumsiBulanan[$k]['rasio'], 2) : 0; }, range(1,12)))); ?>;
    var bgColors = <?php echo json_encode(array_values(array_map(function($k){ return isset($konsumsiChartBg[$k]) ? $konsumsiChartBg[$k] : 'rgba(74,85,104,0.1)'; }, range(1,12)))); ?>;
    var bdColors = <?php echo json_encode(array_values(array_map(function($k){ return isset($konsumsiChartBd[$k]) ? $konsumsiChartBd[$k] : '#4a5568'; }, range(1,12)))); ?>;
    var avgLine = <?php echo json_encode(array_fill(0, 12, round($konsumsiSummary['avg_rasio'], 2))); ?>;
    var refLine = [100,100,100,100,100,100,100,100,100,100,100,100];

    var konsumsiAmt = <?php echo json_encode(array_values(array_map(function($k){ return isset($konsumsiBulanan[$k]) ? round($konsumsiBulanan[$k]['konsumsi_aktual'], 2) : 0; }, range(1,12)))); ?>;
    var penjualanAmt = <?php echo json_encode(array_values(array_map(function($k){ return isset($konsumsiBulanan[$k]) ? round($konsumsiBulanan[$k]['penjualan_amt'], 2) : 0; }, range(1,12)))); ?>;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Rasio Konsumsi (%)',
                    data: data,
                    backgroundColor: bgColors,
                    borderColor: bdColors,
                    borderWidth: 2,
                    borderRadius: 6,
                    yAxisID: 'y',
                    order: 3
                },
                {
                    label: 'Batas 100%',
                    data: refLine,
                    type: 'line',
                    borderColor: 'rgba(255, 77, 106, 0.45)',
                    borderWidth: 1.5,
                    borderDash: [8, 4],
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    fill: false,
                    yAxisID: 'y',
                    order: 1
                },
                {
                    label: 'Rata-rata (<?php echo fmtN($konsumsiSummary['avg_rasio'], 1); ?>%)',
                    data: avgLine,
                    type: 'line',
                    borderColor: 'rgba(0, 212, 170, 0.7)',
                    borderWidth: 2,
                    borderDash: [4, 3],
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    fill: false,
                    yAxisID: 'y',
                    order: 2
                },
                {
                    label: 'Penjualan FG (Amount)',
                    data: penjualanAmt,
                    type: 'line',
                    borderColor: 'rgba(34, 197, 94, 0.6)',
                    backgroundColor: 'rgba(34, 197, 94, 0.05)',
                    borderWidth: 1.5,
                    pointRadius: 3,
                    pointBackgroundColor: '#22c55e',
                    pointBorderColor: '#1a2233',
                    pointBorderWidth: 1.5,
                    pointHoverRadius: 5,
                    tension: 0.3,
                    fill: true,
                    yAxisID: 'y1',
                    order: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        color: '#7d8aa0',
                        font: { family: 'DM Sans', size: 11 },
                        usePointStyle: true,
                        pointStyleWidth: 10,
                        padding: 16
                    }
                },
                tooltip: {
                    backgroundColor: '#1a2233',
                    titleColor: '#e4e9f2',
                    bodyColor: '#7d8aa0',
                    borderColor: '#2c3a50',
                    borderWidth: 1,
                    cornerRadius: 8,
                    padding: 12,
                    titleFont: { family: 'Space Grotesk', weight: '600', size: 13 },
                    bodyFont: { family: 'DM Sans', size: 12 },
                    callbacks: {
                        label: function(ctx) {
                            if (ctx.datasetIndex === 0) {
                                return 'Rasio: ' + Number(ctx.raw).toFixed(2) + '%';
                            } else if (ctx.datasetIndex === 1) {
                                return 'Batas: 100%';
                            } else if (ctx.datasetIndex === 2) {
                                return 'Rata-rata: <?php echo fmtN($konsumsiSummary['avg_rasio'], 2); ?>%';
                            } else if (ctx.datasetIndex === 3) {
                                return 'Penjualan: ' + Number(ctx.raw).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                            return ctx.dataset.label + ': ' + ctx.raw;
                        },
                        afterBody: function(items) {
                            var idx = items[0].dataIndex;
                            var ka = konsumsiAmt[idx];
                            return ['Konsumsi Aktual: ' + Number(ka).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})];
                        }
                    }
                }
            },
            scales: {
                y: {
                    position: 'left',
                    min: 0,
                    title: { display: true, text: 'Rasio Konsumsi (%)', color: '#7d8aa0', font: { family: 'DM Sans', size: 12 } },
                    grid: { color: 'rgba(255,255,255,0.04)' },
                    ticks: {
                        color: '#7d8aa0',
                        font: { size: 11 },
                        callback: function(v) { return v + '%'; }
                    }
                },
                y1: {
                    position: 'right',
                    title: { display: true, text: 'Penjualan FG (Amount)', color: '#22c55e', font: { family: 'DM Sans', size: 12 } },
                    grid: { drawOnChartArea: false },
                    ticks: {
                        color: '#22c55e',
                        font: { size: 11 },
                        callback: function(v) { return v >= 1e9 ? (v/1e9).toFixed(1)+'B' : v >= 1e6 ? (v/1e6).toFixed(1)+'M' : v >= 1e3 ? (v/1e3).toFixed(0)+'K' : v; }
                    }
                },
                x: {
                    grid: { color: 'rgba(255,255,255,0.04)' },
                    ticks: { color: '#7d8aa0', font: { family: 'DM Sans', size: 11 } }
                }
            }
        }
    });
})();
<?php endif; ?>

function exportCSV() {
    var table = document.getElementById('mainTable');
    if (!table) return;
    var csv = '\uFEFF';
    var rows = table.querySelectorAll('tr');
    for (var i = 0; i < rows.length; i++) {
        if (rows[i].classList.contains('month-hdr')) continue;
        var cells = rows[i].querySelectorAll('td');
        var d = [];
        for (var j = 0; j < cells.length; j++) {
            if (!cells[j].classList.contains('no-print'))
                d.push('"' + cells[j].innerText.replace(/"/g, '""').trim() + '"');
        }
        if (d.length) csv += d.join(',') + '\n';
    }
    var b = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(b);
    a.download = 'penerimaan_<?php echo $selected_item_id && $itemInfo ? addslashes($itemInfo['ITEM_CODE']) : 'item'; ?>_<?php echo $filter_year; ?>.csv';
    a.click();
    URL.revokeObjectURL(a.href);
    showToast('CSV berhasil diunduh');
}

function showToast(msg) {
    var w = document.getElementById('toastWrap');
    var e = document.createElement('div');
    e.className = 'toast-msg toast-info';
    e.innerHTML = '<i class="fa-solid fa-circle-info"></i> ' + msg;
    w.appendChild(e);
    setTimeout(function() { e.style.opacity = '0'; e.style.transition = 'opacity 0.3s'; }, 2500);
    setTimeout(function() { e.remove(); }, 2800);
}
</script>

</body>
</html>

<?php
/**
 * BOM Trace — KONSUMSI BERDASARKAN AMOUNT (RUPIAH)
 * Rumus: (Stok Awal Amount + Penerimaan Amount − Stok Akhir Amount) ÷ Penjualan Amount × 100%
 * 
 * LOGIKA ITTY_CODE:
 * - ITTY 02, 13 → Amount = (BOM_QTY / 1000) × Harga (gram → kg)
 * - ITTY lainnya (03, dll) → Amount = BOM_QTY × Harga (langsung)
 */
 $config1 = __DIR__ . '/config/database_aging.php';
 $config2 = __DIR__ . '/../config/database_aging.php';
if (file_exists($config1)) require_once $config1;
elseif (file_exists($config2)) require_once $config2;
else die('File config database_aging.php tidak ditemukan.');

 $itty_finished_good = '01';
 $filter_fg_only = true;

// ═══════════════════════════════════════════════════════
// DAFTAR ITTY YANG PERLU KONVERSI (GRAM → KG)
// ═══════════════════════════════════════════════════════
 $itty_konversi_gram = array('02', '13');  // BOM dalam gram, perlu ÷1000

 $trace_item_id   = isset($_GET['item_id']) ? trim($_GET['item_id']) : '';
 $trace_item_code = isset($_GET['item_code']) ? trim($_GET['item_code']) : '';
 $trace_sup_code  = isset($_GET['sup_code']) ? trim($_GET['sup_code']) : '';
 $trace_bulan     = isset($_GET['bulan']) ? trim($_GET['bulan']) : '';

 $ref_year  = date('Y');
 $ref_month = date('n');
 $str_bulan = date('F Y');

if ($trace_bulan !== '') {
    $ts = strtotime($trace_bulan);
    if ($ts !== false) {
        $ref_year  = date('Y', $ts);
        $ref_month = date('n', $ts);
        $str_bulan = date('F Y', $ts);
    }
}

 $stok_awal_str  = sprintf('%04d-%02d-01', $ref_year, $ref_month);
 $nm = $ref_month + 1; $ny = $ref_year;
if ($nm > 12) { $nm = 1; $ny++; }
 $stok_akhir_str = sprintf('%04d-%02d-01', $ny, $nm);
 $stok_awal_label  = date('d M Y', strtotime($stok_awal_str));
 $stok_akhir_label = date('d M Y', strtotime($stok_akhir_str));

// ═══════════════════════════════════════════════════════
// QUERY 1: Info material
// ═══════════════════════════════════════════════════════
 $materialInfo = null;
if ($trace_item_id !== '') {
    $stMI = q("SELECT ITEM_ID, ITEM_CODE, ITEM_NAME, ITTY_CODE FROM dbo.ITEMS WHERE ITEM_ID = ?", array($trace_item_id));
    $materialInfo = sqlsrv_fetch_array($stMI, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stMI);
}
if ($materialInfo) $trace_item_code = $materialInfo['ITEM_CODE'];

 $traced_itty = $materialInfo ? $materialInfo['ITTY_CODE'] : '';

// ═══════════════════════════════════════════════════════
// LOGIKA KONVERSI BERDASARKAN ITTY_CODE
// ═══════════════════════════════════════════════════════
 $is_konversi = in_array($traced_itty, $itty_konversi_gram);

 $conv_factor = $is_konversi ? 1000 : 1;
 $mat_unit    = $is_konversi ? 'kg' : 'unit';

if ($is_konversi) {
    $conv_label = 'BOM (gr) ÷ 1000 → kg';
} else {
    $conv_label = 'BOM langsung × Harga (tanpa konversi)';
}

// ═══════════════════════════════════════════════════════
// QUERY 2: Parent items (FG) + harga jual
// ═══════════════════════════════════════════════════════
 $parentItems = array();
 $parentIds   = array();

if ($trace_item_id !== '') {
    $sqlP = "
        SELECT DISTINCT p.ITEM_ID AS PARENT_ID, p.ITEM_CODE AS PARENT_CODE, p.ITEM_NAME AS PARENT_NAME,
               p.ITTY_CODE AS PARENT_ITTY, ISNULL(px.HARGA_JUAL,0) AS HARGA_JUAL,
               px.PRICE_ID, px.PRDT_START, px.PRDT_END
        FROM dbo.BOM_DEFAULT b
        INNER JOIN dbo.ITEMS p ON b.PART_ID = p.ITEM_ID
        OUTER APPLY (
            SELECT TOP 1 pr.PRICE_ID, pd.PRDT_PRICE AS HARGA_JUAL, pd.PRDT_START, pd.PRDT_END
            FROM dbo.PRICE pr INNER JOIN dbo.PRICE_DETAIL pd ON pd.PRICE_ID = pr.PRICE_ID
            WHERE pr.PART_ID = p.ITEM_ID ORDER BY pd.PRDT_START DESC
        ) px
        WHERE b.ITEM_ID = ?
    ";
    $pp = array($trace_item_id);
    if ($filter_fg_only) { $sqlP .= " AND p.ITTY_CODE = ?"; $pp[] = $itty_finished_good; }
    $sqlP .= " ORDER BY p.ITEM_CODE";

    $stP = q($sqlP, $pp);
    while ($pr = sqlsrv_fetch_array($stP, SQLSRV_FETCH_ASSOC)) {
        $parentItems[] = $pr;
        $parentIds[]   = $pr['PARENT_ID'];
    }
    sqlsrv_free_stmt($stP);
}
 $totalParents = count($parentIds);

// ═══════════════════════════════════════════════════════
// QUERY 3: SEMUA BOM + HARGA RECEIVE
// ═══════════════════════════════════════════════════════
 $bomGrouped = array();
if ($totalParents > 0) {
    $inP = implode(',', array_fill(0, $totalParents, '?'));
    $bomParams = array_merge(array($ref_year, $ref_month), $parentIds, array($ref_year, $ref_month));

    $sqlB = "
        WITH BomRows AS (
            SELECT b.PART_ID AS PARENT_ID, m.ITEM_ID AS MAT_ID, m.ITEM_CODE AS MAT_CODE,
                   m.ITEM_NAME AS MAT_NAME, m.ITTY_CODE, b.QTY AS BOM_QTY
            FROM dbo.BOM_DEFAULT b INNER JOIN dbo.ITEMS m ON b.ITEM_ID = m.ITEM_ID
            WHERE b.PART_ID IN ($inP)
        ),
        RankedPrice AS (
            SELECT rd.ITEM_ID, 
                   (rd.POD_PRICE * 
                       CASE 
                           WHEN ISNULL(po.PO_CUR, 'IDR') <> 'IDR' THEN 
                               ISNULL((SELECT TOP 1 cr.CURR_VRATE 
                                       FROM dbo.CURR_RAT cr 
                                       WHERE cr.CURR_CODE = po.PO_CUR 
                                         AND r.RCV_DATE BETWEEN cr.CURR_SDATE AND cr.CURR_EDATE
                                       ORDER BY cr.CURR_SDATE DESC), 1)
                           ELSE 1 
                       END
                   ) AS POD_PRICE_IDR,
                   po.PO_CUR, r.RCV_DATE,
                   ROW_NUMBER() OVER (
                       PARTITION BY rd.ITEM_ID
                       ORDER BY CASE WHEN YEAR(r.RCV_DATE)=? AND MONTH(r.RCV_DATE)=? THEN 0 ELSE 1 END, r.RCV_DATE DESC
                   ) AS rn
            FROM dbo.RECEIVE r INNER JOIN dbo.RECEIVE_DETAIL rd ON r.RCV_ID = rd.RCV_ID
            INNER JOIN dbo.PO po ON rd.PO_ID = po.PO_ID
            WHERE rd.ITEM_ID IN (SELECT DISTINCT MAT_ID FROM BomRows)
        )
        SELECT br.PARENT_ID, br.MAT_ID, br.MAT_CODE, br.MAT_NAME, br.ITTY_CODE, br.BOM_QTY,
               ISNULL(rp.POD_PRICE_IDR,0) AS MAT_PRICE,
               'IDR' AS PO_CUR,
               rp.RCV_DATE AS RCV_DATE_REF,
               CASE WHEN rp.RCV_DATE IS NOT NULL AND YEAR(rp.RCV_DATE)=? AND MONTH(rp.RCV_DATE)=? THEN 1 ELSE 0 END AS IS_EXACT_MONTH
        FROM BomRows br LEFT JOIN RankedPrice rp ON rp.ITEM_ID = br.MAT_ID AND rp.rn = 1
        ORDER BY br.PARENT_ID, br.MAT_CODE
    ";
    $stB = q($sqlB, $bomParams);
    while ($br = sqlsrv_fetch_array($stB, SQLSRV_FETCH_ASSOC)) {
        $pid = $br['PARENT_ID'];
        if (!isset($bomGrouped[$pid])) $bomGrouped[$pid] = array();
        $bomQty = floatval($br['BOM_QTY']); 
        $matPrice = floatval($br['MAT_PRICE']);
        $isExact = intval($br['IS_EXACT_MONTH']) === 1;
        $priceSrc = 'none';
        if ($matPrice > 0 && $isExact) $priceSrc = 'exact';
        elseif ($matPrice > 0 && !$isExact) $priceSrc = 'fallback';
        $rcvDateStr = '';
        if (!empty($br['RCV_DATE_REF'])) $rcvDateStr = $br['RCV_DATE_REF'] instanceof DateTime ? $br['RCV_DATE_REF']->format('d M Y') : date('d M Y', strtotime($br['RCV_DATE_REF']));
        
        // ═══════════════════════════════════════════════════════════
        // PERHITUNGAN AMOUNT BERDASARKAN ITTY_CODE
        // ITTY 02, 13: Amount = (BOM_QTY / 1000) × Harga
        // ITTY LAINNYA (03, dll): Amount = BOM_QTY × Harga
        // ═══════════════════════════════════════════════════════════
        $item_itty = $br['ITTY_CODE'];
        if (in_array($item_itty, $itty_konversi_gram)) {
            // ITTY 02, 13: BOM dalam gram, harga per kg → Amount = (BOM/1000) × Harga
            $amount = ($bomQty / 1000) * $matPrice;
        } else {
            // ITTY 03 dan lainnya: BOM langsung × Harga (tanpa konversi)
            $amount = $bomQty * $matPrice;
        }
        
        $bomGrouped[$pid][] = array(
            'MAT_ID'=>$br['MAT_ID'],'MAT_CODE'=>$br['MAT_CODE'],'MAT_NAME'=>$br['MAT_NAME'],
            'ITTY_CODE'=>$br['ITTY_CODE'],'BOM_QTY'=>$bomQty,'MAT_PRICE'=>$matPrice,
            'PO_CUR'=>$br['PO_CUR'],'AMOUNT'=>$amount,
            'PRICE_SRC'=>$priceSrc,
            'RCV_DATE_STR'=>$rcvDateStr,'IS_EXACT'=>$isExact,'IS_TRACED'=>($br['MAT_ID']==$trace_item_id)
        );
    }
    sqlsrv_free_stmt($stB);
}

// ═══════════════════════════════════════════════════════
// QUERY 4: SEMUA PENJUALAN
// ═══════════════════════════════════════════════════════
 $salesGrouped = array();
if ($totalParents > 0) {
    $inS = implode(',', array_fill(0, $totalParents, '?'));
    $sqlS = "
        SELECT pr.PART_ID AS PARENT_ID, d.DI_ID, d.DI_DATE, c.CUST_CODE, c.CUST_COMP,
               dp.QTY AS DI_QTY, dp.CURR,
               (dp.PART_PRICE * 
                   CASE 
                       WHEN ISNULL(dp.CURR, 'IDR') <> 'IDR' THEN 
                           ISNULL((SELECT TOP 1 cr.CURR_VRATE 
                                   FROM dbo.CURR_RAT cr 
                                   WHERE cr.CURR_CODE = dp.CURR 
                                     AND d.DI_DATE BETWEEN cr.CURR_SDATE AND cr.CURR_EDATE
                                   ORDER BY cr.CURR_SDATE DESC), 1)
                       ELSE 1 
                   END
               ) AS DI_PRICE_IDR
        FROM dbo.DI d 
        INNER JOIN dbo.DIPA_PAR dp ON d.DI_ID = dp.DI_ID
        INNER JOIN dbo.CUST c ON d.CUST_ID = c.CUST_ID 
        INNER JOIN dbo.PRICE pr ON dp.PRICE_ID = pr.PRICE_ID
        WHERE pr.PART_ID IN ($inS) AND YEAR(d.DI_DATE)=? AND MONTH(d.DI_DATE)=?
        ORDER BY pr.PART_ID, d.DI_DATE DESC
    ";
    $stS = q($sqlS, array_merge($parentIds, array($ref_year, $ref_month)));
    while ($sr = sqlsrv_fetch_array($stS, SQLSRV_FETCH_ASSOC)) {
        $pid = $sr['PARENT_ID'];
        if (!isset($salesGrouped[$pid])) $salesGrouped[$pid] = array();
        $diDateStr = '';
        if (!empty($sr['DI_DATE'])) $diDateStr = $sr['DI_DATE'] instanceof DateTime ? $sr['DI_DATE']->format('d M Y') : date('d M Y', strtotime($sr['DI_DATE']));
        $salesGrouped[$pid][] = array(
            'DI_ID'=>$sr['DI_ID'],'DI_DATE_STR'=>$diDateStr,'CUST_CODE'=>$sr['CUST_CODE'],
            'CUST_COMP'=>$sr['CUST_COMP'],'DI_QTY'=>floatval($sr['DI_QTY']),
            'DI_PRICE'=>floatval($sr['DI_PRICE_IDR']),
            'DI_AMOUNT'=>floatval($sr['DI_QTY']) * floatval($sr['DI_PRICE_IDR'])
        );
    }
    sqlsrv_free_stmt($stS);
}

// ═══════════════════════════════════════════════════════
// QUERY 5: STOK AWAL & AKHIR BARANG JADI (SOP + TAGS)
// ═══════════════════════════════════════════════════════
 $stokFG = array();
 $has_stok_data = false;

if ($totalParents > 0) {
    $inFG = implode(',', array_fill(0, $totalParents, '?'));
    $sqlStk = "
        SELECT t.ITEM_ID,
               SUM(CASE WHEN CONVERT(DATE, s.SOP_SDATE) = CONVERT(DATE, ?) THEN t.TAG_QTY ELSE 0 END) AS STOK_AWAL,
               SUM(CASE WHEN CONVERT(DATE, s.SOP_SDATE) = CONVERT(DATE, ?) THEN t.TAG_QTY ELSE 0 END) AS STOK_AKHIR
        FROM dbo.SOP s INNER JOIN dbo.TAGS t ON s.SOP_ID = t.SOP_ID
        WHERE t.ITEM_ID IN ($inFG)
          AND CONVERT(DATE, s.SOP_SDATE) IN (CONVERT(DATE, ?), CONVERT(DATE, ?))
        GROUP BY t.ITEM_ID
    ";
    $stStk = q($sqlStk, array_merge(array($stok_awal_str, $stok_akhir_str), $parentIds, array($stok_awal_str, $stok_akhir_str)));
    while ($rStk = sqlsrv_fetch_array($stStk, SQLSRV_FETCH_ASSOC)) {
        $stokFG[$rStk['ITEM_ID']] = array(
            'STOK_AWAL'  => floatval($rStk['STOK_AWAL']),
            'STOK_AKHIR' => floatval($rStk['STOK_AKHIR'])
        );
        if (floatval($rStk['STOK_AWAL']) > 0 || floatval($rStk['STOK_AKHIR']) > 0) $has_stok_data = true;
    }
    sqlsrv_free_stmt($stStk);
}

// ═══════════════════════════════════════════════════════
// QUERY 6: PENERIMAAN MATERIAL (QTY + HARGA)
// ═══════════════════════════════════════════════════════
 $total_received = 0;
 $penerimaan_amt = 0;
if ($trace_item_id !== '') {
    $sqlRcv = "
        SELECT ISNULL(SUM(rd.RCVD_QTY), 0) AS TOTAL_RCV,
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
        WHERE rd.ITEM_ID = ? AND YEAR(r.RCV_DATE) = ? AND MONTH(r.RCV_DATE) = ?
    ";
    $stRcv = q($sqlRcv, array($trace_item_id, $ref_year, $ref_month));
    if ($rRcv = sqlsrv_fetch_array($stRcv, SQLSRV_FETCH_ASSOC)) {
        $total_received = floatval($rRcv['TOTAL_RCV']);
        // Untuk ITTY 02, 13: QTY receive sudah dalam kg, jadi langsung pakai EXACT_AMOUNT
        // Untuk ITTY lainnya: juga langsung pakai EXACT_AMOUNT karena sudah dalam satuan yang benar
        $penerimaan_amt = floatval($rRcv['EXACT_AMOUNT']);
    }
    sqlsrv_free_stmt($stRcv);
}

// ═══════════════════════════════════════════════════════
// ASSEMBLE — termasuk KONSUMSI BERDASARKAN AMOUNT
// ═══════════════════════════════════════════════════════
 $bomData = array();
 $grandTotalQty = 0; $grandTotalAmt = 0; $grandTotalDI = 0;
 $grandCustomerList = array();

// Variabel konsumsi QTY (untuk detail tabel)
 $total_stok_awal_mat  = 0;
 $total_stok_akhir_mat = 0;
 $total_received_mat   = 0;
 $konsumsi_teoritis_qty = 0;
 $detail_konversi = array();

// Variabel konsumsi AMOUNT (untuk formula & rasio)
 $stok_awal_amt   = 0;
 $stok_akhir_amt  = 0;
 $konsumsi_aktual_amt = 0;

// Harga material yang di-trace
 $traced_mat_price = 0;
 $traced_po_cur    = '-';

foreach ($parentItems as $pi) {
    $pid = $pi['PARENT_ID'];
    $rows = isset($bomGrouped[$pid]) ? $bomGrouped[$pid] : array();
    $sales = isset($salesGrouped[$pid]) ? $salesGrouped[$pid] : array();

    $isHighlighted = false; $totalQty = 0; $totalAmount = 0;
    $fallbackCount = 0; $noPriceCount = 0;
    $tracedBomQty = 0;

    foreach ($rows as &$br) {
        if ($br['IS_TRACED']) {
            $isHighlighted = true;
            $tracedBomQty = $br['BOM_QTY'];
            if ($traced_mat_price <= 0 && $br['MAT_PRICE'] > 0) {
                $traced_mat_price = $br['MAT_PRICE'];
                $traced_po_cur    = $br['PO_CUR'];
            }
        }
        $totalQty += $br['BOM_QTY']; $totalAmount += $br['AMOUNT'];
        if ($br['PRICE_SRC'] === 'fallback') $fallbackCount++;
        if ($br['PRICE_SRC'] === 'none') $noPriceCount++;
    }
    unset($br);

    $salesTotalQty = 0; $salesTotalAmt = 0; $customerSet = array();
    foreach ($sales as $s) {
        $salesTotalQty += $s['DI_QTY']; $salesTotalAmt += $s['DI_AMOUNT'];
        $customerSet[$s['CUST_CODE']] = $s['CUST_COMP'];
    }

    // === KONVERSI STOK FG → KEBUTUHAN MATERIAL (QTY) ===
    $fg_awal  = isset($stokFG[$pid]) ? $stokFG[$pid]['STOK_AWAL'] : 0;
    $fg_akhir = isset($stokFG[$pid]) ? $stokFG[$pid]['STOK_AKHIR'] : 0;

    // conv_factor: 1000 untuk ITTY 02 & 13, 1 untuk lainnya
    $kebutuhan_awal_qty  = ($fg_awal * $tracedBomQty) / $conv_factor;
    $kebutuhan_akhir_qty = ($fg_akhir * $tracedBomQty) / $conv_factor;
    $teoritis_qty        = ($salesTotalQty * $tracedBomQty) / $conv_factor;

    $total_stok_awal_mat  += $kebutuhan_awal_qty;
    $total_stok_akhir_mat += $kebutuhan_akhir_qty;
    $konsumsi_teoritis_qty += $teoritis_qty;

    // === KONVERSI KE AMOUNT (RUPIAH) ===
    $kebutuhan_awal_amt  = $kebutuhan_awal_qty * $traced_mat_price;
    $kebutuhan_akhir_amt = $kebutuhan_akhir_qty * $traced_mat_price;
    $teoritis_amt        = $teoritis_qty * $traced_mat_price;

    $stok_awal_amt  += $kebutuhan_awal_amt;
    $stok_akhir_amt += $kebutuhan_akhir_amt;

    $detail_konversi[] = array(
        'code' => $pi['PARENT_CODE'],
        'name' => $pi['PARENT_NAME'],
        'bom_qty' => $tracedBomQty,
        'fg_awal' => $fg_awal,
        'fg_akhir' => $fg_akhir,
        'kebutuhan_awal_qty' => $kebutuhan_awal_qty,
        'kebutuhan_akhir_qty'=> $kebutuhan_akhir_qty,
        'kebutuhan_awal_amt' => $kebutuhan_awal_amt,
        'kebutuhan_akhir_amt'=> $kebutuhan_akhir_amt,
        'sales_qty' => $salesTotalQty,
        'sales_amt' => $salesTotalAmt,
        'teoritis_qty' => $teoritis_qty,
        'teoritis_amt' => $teoritis_amt
    );

    $grandTotalQty += $salesTotalQty; $grandTotalAmt += $salesTotalAmt;
    $grandTotalDI += count($sales);
    foreach ($customerSet as $cc => $cn) { if (!isset($grandCustomerList[$cc])) $grandCustomerList[$cc] = $cn; }

    $sellPrice = floatval($pi['HARGA_JUAL']);
    $pricePeriod = '';
    if (!empty($pi['PRDT_START'])) {
        $start = $pi['PRDT_START'] instanceof DateTime ? $pi['PRDT_START']->format('d M Y') : date('d M Y', strtotime($pi['PRDT_START']));
        $end = !empty($pi['PRDT_END']) ? ($pi['PRDT_END'] instanceof DateTime ? $pi['PRDT_END']->format('d M Y') : date('d M Y', strtotime($pi['PRDT_END']))) : '∞';
        $pricePeriod = $start . ' s/d ' . $end;
    }

    $bomData[] = array(
        'parent'=>$pi,'rows'=>$rows,'sell_price'=>$sellPrice,'highlighted'=>$isHighlighted,
        'mat_count'=>count($rows),'total_qty'=>$totalQty,'total_amount'=>$totalAmount,
        'price_period'=>$pricePeriod,'has_price'=>($sellPrice>0),
        'fallback_count'=>$fallbackCount,'no_price_count'=>$noPriceCount,
        'sales'=>$sales,'sales_total_qty'=>$salesTotalQty,'sales_total_amt'=>$salesTotalAmt,
        'sales_di_count'=>count($sales),'customers'=>$customerSet,'traced_bom_qty'=>$tracedBomQty
    );
}

// === HITUNG FINAL AMOUNT ===
 $total_received_mat = $total_received;

// Konsumsi Aktual Amount
 $konsumsi_aktual_amt = $stok_awal_amt + $penerimaan_amt - $stok_akhir_amt;

// Rasio: Konsumsi Aktual Amount ÷ Penjualan Amount × 100%
 $penjualan_amount = $grandTotalAmt;
 $rasio_persen     = $penjualan_amount > 0 ? ($konsumsi_aktual_amt / $penjualan_amount * 100) : 0;

// Variance amount
 $variance_amt = $konsumsi_aktual_amt - $konsumsi_teoritis_qty * $traced_mat_price;

function fmtN($v, $d = 0) { return number_format(floatval($v), $d, ',', '.'); }

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BOM Trace — <?php echo htmlspecialchars($trace_item_code); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root{--bg0:#0b0e13;--bg2:#161c28;--bg3:#1a2233;--bdr:#222d3d;--bdr2:#2c3a50;--t1:#e4e9f2;--t2:#7d8aa0;--t3:#4a5568;--acc:#00d4aa;--acc-d:rgba(0,212,170,.1);--blu:#4da6ff;--blu-d:rgba(77,166,255,.1);--org:#ff9f43;--org-d:rgba(255,159,67,.1);--red:#ff4d6a;--red-d:rgba(255,77,106,.1);--pur:#a78bfa;--pur-d:rgba(167,139,250,.1);--ylw:#facc15;--ylw-d:rgba(250,204,21,.1);--grn:#22c55e;--grn-d:rgba(34,197,94,.1);--cyn:#06b6d4;--cyn-d:rgba(6,182,212,.1)}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'DM Sans',sans-serif;background:var(--bg0);color:var(--t1);min-height:100vh}
        h1,h2,h3,h4{font-family:'Space Grotesk',sans-serif}
        body::before{content:'';position:fixed;top:-200px;right:-100px;width:600px;height:600px;background:radial-gradient(circle,rgba(0,212,170,.05) 0%,transparent 70%);border-radius:50%;pointer-events:none;z-index:0;animation:d1 22s ease-in-out infinite}
        body::after{content:'';position:fixed;bottom:-150px;left:-100px;width:500px;height:500px;background:radial-gradient(circle,rgba(77,166,255,.04) 0%,transparent 70%);border-radius:50%;pointer-events:none;z-index:0;animation:d2 28s ease-in-out infinite}
        @keyframes d1{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(-30px,20px) scale(1.08)}}
        @keyframes d2{0%,100%{transform:translate(0,0)}50%{transform:translate(25px,-15px)}}
        .mw{position:relative;z-index:1}
        .card{background:var(--bg2);border:1px solid var(--bdr);border-radius:14px}
        .dt{width:100%;border-collapse:separate;border-spacing:0}
        .dt thead th{padding:10px 14px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:var(--t3);border-bottom:1px solid var(--bdr);background:rgba(0,0,0,.25)}
        .dt tbody td{padding:9px 14px;font-size:13px;border-bottom:1px solid rgba(34,45,61,.5);vertical-align:middle}
        .dt tbody tr{transition:background .15s}
        .dt tbody tr:hover{background:rgba(0,212,170,.03)}
        .dt tbody tr.row-highlight{background:rgba(167,139,250,.08)!important;border-left:3px solid var(--pur)}
        .dt tbody tr.row-fallback{background:rgba(250,204,21,.03)!important}
        .dt tbody tr.row-noprice td{opacity:.45}
        .num-r{text-align:right;font-variant-numeric:tabular-nums}
        .itty-badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:var(--blu-d);color:var(--blu)}
        .traced-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;background:var(--pur-d);color:var(--pur);border:1px solid rgba(167,139,250,.2)}
        .price-tag{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;background:var(--acc-d);color:var(--acc);border:1px solid rgba(0,212,170,.15)}
        .price-tag.none{background:var(--red-d);color:var(--red);border-color:rgba(255,77,106,.15)}
        .src-exact{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:600;background:var(--acc-d);color:var(--acc)}
        .src-fallback{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:600;background:var(--ylw-d);color:var(--ylw);border:1px solid rgba(250,204,21,.15)}
        .src-none{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:600;background:var(--red-d);color:var(--red)}
        .cust-badge{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:600;background:var(--grn-d);color:var(--grn);border:1px solid rgba(34,197,94,.15)}
        .item-bar{background:linear-gradient(135deg,rgba(167,139,250,.08),rgba(77,166,255,.05));border:1px solid rgba(167,139,250,.2);border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap}
        .parent-card{border:1px solid var(--bdr);border-radius:14px;overflow:hidden;margin-bottom:20px;background:var(--bg2);transition:border-color .2s}
        .parent-header{padding:16px 20px;background:var(--bg3);border-bottom:1px solid var(--bdr);display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap}
        .parent-code{font-family:'Space Grotesk',sans-serif;font-size:18px;font-weight:700;color:var(--acc)}
        .parent-name{font-size:14px;color:var(--t2)}
        .stat-mini{display:flex;flex-direction:column;gap:2px;padding:10px 14px;background:rgba(0,0,0,.15);border-radius:8px;min-width:130px}
        .stat-mini-label{font-size:10px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.06em}
        .stat-mini-value{font-size:16px;font-weight:700;font-family:'Space Grotesk',sans-serif}
        .empty-box{text-align:center;padding:60px 20px;color:var(--t3)}.empty-box i{font-size:44px;margin-bottom:14px;opacity:.25;display:block}
        .empty-sm{text-align:center;padding:28px 20px;color:var(--t3);font-size:13px}.empty-sm i{font-size:24px;margin-bottom:8px;opacity:.2;display:block}
        .tbl-wrap{overflow-x:auto;border-radius:0}
        .sep{width:1px;height:28px;background:var(--bdr);flex-shrink:0;align-self:center}
        .back-link{display:inline-flex;align-items:center;gap:6px;color:var(--t2);text-decoration:none;font-size:13px;font-weight:500;transition:color .2s}.back-link:hover{color:var(--acc)}
        .month-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:6px;font-size:11px;font-weight:600;background:var(--blu-d);color:var(--blu);border:1px solid rgba(77,166,255,.15)}
        .warn-inline{display:inline-flex;align-items:center;gap:4px;font-size:11px;color:var(--ylw);margin-top:2px}.warn-inline i{font-size:10px}
        .warn-box{margin-top:10px;padding:8px 14px;border-radius:8px;font-size:12px;display:flex;align-items:flex-start;gap:8px}.warn-box.danger{background:var(--red-d);color:var(--red)}
        .legend-row{display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-top:10px;padding:10px 14px;background:rgba(0,0,0,.15);border-radius:8px}
        .legend-item{display:inline-flex;align-items:center;gap:5px;font-size:11px;color:var(--t2)}
        .section-divider{display:flex;align-items:center;gap:10px;padding:10px 20px;background:rgba(0,0,0,.1);border-top:1px solid var(--bdr)}
        .section-divider-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);white-space:nowrap}
        .section-divider-line{flex:1;height:1px;background:var(--bdr)}
        .sales-footer{padding:12px 20px;border-top:1px solid var(--bdr);background:rgba(34,197,94,.03);display:flex;align-items:center;gap:20px;flex-wrap:wrap}

        /* === KONSUMSI === */
        .konsumsi-card{border:1px solid var(--bdr);border-radius:14px;overflow:hidden;background:var(--bg2);margin-bottom:20px}
        .konsumsi-header{padding:16px 20px;background:var(--bg3);border-bottom:1px solid var(--bdr);display:flex;align-items:center;gap:12px;flex-wrap:wrap}
        .konsumsi-header-icon{width:36px;height:36px;border-radius:9px;background:var(--cyn-d);display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .konsumsi-body{padding:20px}
        .formula-row{display:flex;align-items:stretch;gap:0;flex-wrap:wrap;margin-bottom:16px}
        .formula-box{flex:1;min-width:120px;padding:14px 12px;background:rgba(0,0,0,.12);border:1px solid var(--bdr);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:2px}
        .formula-box:first-child{border-radius:10px 0 0 10px}
        .formula-box.result{background:var(--cyn-d);border-color:rgba(6,182,212,.2);border-radius:0 10px 10px 0}
        .formula-box.result-neg{background:var(--red-d);border-color:rgba(255,77,106,.2);border-radius:0 10px 10px 0}
        .formula-op{display:flex;align-items:center;justify-content:center;width:40px;font-family:'Space Grotesk',sans-serif;font-size:20px;font-weight:700;color:var(--t3);background:rgba(0,0,0,.2);border-top:1px solid var(--bdr);border-bottom:1px solid var(--bdr);flex-shrink:0}
        .formula-label{font-size:9px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.05em;line-height:1.3}
        .formula-value{font-size:17px;font-weight:700;font-family:'Space Grotesk',sans-serif;color:var(--t1)}
        .formula-sub{font-size:9px;color:var(--t3)}
        .formula-box.result .formula-value{color:var(--cyn)}
        .formula-box.result-neg .formula-value{color:var(--red)}

        .ratio-section{display:flex;gap:24px;align-items:center;flex-wrap:wrap;padding:16px 18px;background:rgba(0,0,0,.1);border-radius:10px;border:1px solid var(--bdr)}
        .ratio-info{flex:1;min-width:200px;display:flex;flex-direction:column;gap:8px}
        .ratio-row{display:flex;justify-content:space-between;align-items:center;gap:12px;font-size:13px}
        .ratio-row-label{color:var(--t2)}
        .ratio-row-value{font-weight:700;font-family:'Space Grotesk',sans-serif}
        .ratio-gauge{width:100%;min-width:200px;max-width:320px}
        .ratio-gauge-bar{width:100%;height:32px;background:rgba(255,255,255,.04);border-radius:8px;overflow:hidden;position:relative}
        .ratio-gauge-fill{height:100%;border-radius:8px;transition:width .6s cubic-bezier(.22,1,.36,1);min-width:3px}
        .ratio-gauge-fill.good{background:linear-gradient(90deg,rgba(34,197,94,.4),rgba(34,197,94,.2))}
        .ratio-gauge-fill.warn{background:linear-gradient(90deg,rgba(250,204,21,.4),rgba(250,204,21,.2))}
        .ratio-gauge-fill.bad{background:linear-gradient(90deg,rgba(255,77,106,.4),rgba(255,77,106,.2))}
        .ratio-gauge-markers{position:relative;height:20px;margin-top:4px}
        .ratio-gauge-markers span{position:absolute;font-size:9px;color:var(--t3);transform:translateX(-50%)}
        .ratio-gauge-markers .m0{left:0%}.ratio-gauge-markers .m50{left:33%}.ratio-gauge-markers .m100{left:67%}.ratio-gauge-markers .m150{left:100%}
        .ratio-big{font-family:'Space Grotesk',sans-serif;font-size:28px;font-weight:700;text-align:center;margin-top:6px}
        .ratio-big.good{color:var(--grn)}.ratio-big.warn{color:var(--ylw)}.ratio-big.bad{color:var(--red)}.ratio-big.neutral{color:var(--t3)}
        .konsumsi-note{margin-top:12px;font-size:11px;color:var(--t3);display:flex;align-items:flex-start;gap:6px}
        .konsumsi-note i{margin-top:2px;flex-shrink:0}
        .detail-conv{margin-top:16px;border:1px solid var(--bdr);border-radius:10px;overflow:hidden}
        .detail-conv thead th{font-size:10px!important;padding:8px 10px!important}
        .detail-conv tbody td{font-size:12px!important;padding:7px 10px!important}
        .harga-info{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:rgba(255,159,67,.08);border:1px solid rgba(255,159,67,.15);border-radius:8px;font-size:12px;color:var(--org);font-weight:600;margin-bottom:16px}
        @media print{.no-print{display:none!important}body{background:#fff;color:#111}body::before,body::after{display:none}.card,.parent-card,.konsumsi-card{border-color:#ddd;background:#fafafa}.parent-header,.konsumsi-header{background:#eee}.dt thead th{background:#f0f0f0;color:#333}.dt tbody tr.row-highlight{background:#f0f0ff!important;border-left-color:#7c3aed}.formula-box{background:#f5f5f5;border-color:#ddd}.formula-op{background:#eee;border-color:#ddd}.formula-box.result{background:#ecfeff;border-color:#a5f3fc}.ratio-section{background:#f5f5f5}.src-exact{color:#047857;background:#d1fae5}.src-fallback{color:#a16207;background:#fef3c7}.src-none{color:#b91c1c;background:#fee2e2}.cust-badge{color:#047857;background:#d1fae5}.itty-badge{color:#1d4ed8;background:#dbeafe}.traced-badge{color:#7c3aed;background:#ede9fe}.price-tag{color:#047857;background:#d1fae5}.price-tag.none{color:#b91c1c;background:#fee2e2}}
        @media(max-width:768px){.dt thead th,.dt tbody td{padding:8px 10px;font-size:12px}.parent-code{font-size:15px}.stat-mini{min-width:100px}.formula-box{min-width:80px;padding:10px 6px}.formula-value{font-size:14px}.formula-op{width:28px;font-size:16px}.ratio-section{flex-direction:column;align-items:stretch}.ratio-gauge{max-width:100%}}
        ::-webkit-scrollbar{width:5px;height:5px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--bdr);border-radius:3px}
    </style>
</head>
<body>
<div class="mw max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">

    <header class="mb-6">
        <div class="flex items-center gap-3 mb-2">
            <a href="bom.php" class="back-link no-print"><i class="fa-solid fa-arrow-left"></i> Kembali ke Penerimaan</a>
        </div>
        <div class="flex items-center gap-3 mb-1 flex-wrap">
            <div style="width:38px;height:38px;border-radius:10px;background:var(--pur-d);display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid fa-diagram-project" style="color:var(--pur);font-size:16px;"></i>
            </div>
            <div style="flex:1;min-width:200px;">
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight">BOM Trace — Material ke Barang Jadi</h1>
                <p style="color:var(--t2);font-size:13px;">Menelusuri material → BOM → penjualan & konsumsi (Amount)</p>
            </div>
            <div class="month-badge no-print"><i class="fa-regular fa-calendar"></i> <?php echo htmlspecialchars($str_bulan); ?></div>
        </div>
    </header>

    <?php if (!$materialInfo): ?>
    <div class="card"><div class="empty-box"><i class="fa-solid fa-circle-exclamation"></i><p style="font-size:15px;font-weight:500;">Item tidak ditemukan</p></div></div>
    <?php else: ?>

    <div class="item-bar mb-5">
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--pur);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">
                <i class="fa-solid fa-magnifying-glass" style="margin-right:4px;"></i> Material Di-trace
            </div>
            <div style="font-family:'Space Grotesk',sans-serif;font-size:20px;font-weight:700;color:var(--pur);"><?php echo htmlspecialchars($materialInfo['ITEM_CODE']); ?></div>
            <div style="font-size:14px;color:var(--t2);"><?php echo htmlspecialchars($materialInfo['ITEM_NAME']); ?></div>
        </div>
        <div class="sep"></div>
        <div class="stat-mini">
            <div class="stat-mini-label">Tipe</div>
            <div class="stat-mini-value"><span class="itty-badge"><?php echo htmlspecialchars($materialInfo['ITTY_CODE']); ?></span></div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-label">Harga Material</div>
            <div class="stat-mini-value" style="font-size:14px;color:var(--org);">
                <?php echo $traced_mat_price > 0 ? $traced_po_cur . ' ' . fmtN($traced_mat_price, 2) : '<span style="font-size:12px;color:var(--red);">0</span>'; ?>
            </div>
            <div class="stat-mini-label" style="margin-top:2px;">per <?php echo $mat_unit; ?></div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-label">Dipakai di</div>
            <div class="stat-mini-value" style="color:var(--acc);"><?php echo number_format($totalParents); ?> FG</div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-label">Total Terjual</div>
            <div class="stat-mini-value" style="color:var(--grn);"><?php echo fmtN($grandTotalDI); ?> DI</div>
        </div>
    </div>

    <?php if ($totalParents === 0): ?>
    <div class="card"><div class="empty-box"><i class="fa-solid fa-link-slash"></i><p>Material tidak terdaftar di BOM_DEFAULT manapun</p></div></div>
    <?php else: ?>

    <!-- ═══ ANALISIS KONSUMSI (AMOUNT) ═══ -->
    <div class="konsumsi-card">
        <div class="konsumsi-header">
            <div class="konsumsi-header-icon"><i class="fa-solid fa-flask" style="color:var(--cyn);font-size:15px;"></i></div>
            <div style="flex:1;">
                <h3 style="font-size:15px;font-weight:700;">Analisis Konsumsi Material — Berdasarkan Amount</h3>
                <p style="font-size:12px;color:var(--t2);">Rasio = Konsumsi Aktual (Rp) ÷ Total Penjualan (Rp) × 100%</p>
            </div>
            <span class="month-badge" style="background:var(--cyn-d);color:var(--cyn);border-color:rgba(6,182,212,.2);">
                <i class="fa-solid fa-calendar-days" style="font-size:10px;"></i> <?php echo $stok_awal_label; ?> → <?php echo $stok_akhir_label; ?>
            </span>
        </div>
        <div class="konsumsi-body">

            <div class="harga-info">
                <i class="fa-solid fa-tag" style="font-size:11px;"></i>
                Harga Material: <strong><?php echo htmlspecialchars($traced_po_cur); ?> <?php echo fmtN($traced_mat_price, 2); ?></strong> per <?php echo $mat_unit; ?>
                &nbsp;·&nbsp; Konversi: <?php echo $conv_label; ?>
            </div>

            <!-- FORMULA UTAMA: AMOUNT -->
            <div class="formula-row">
                <div class="formula-box">
                    <div class="formula-label">Stok FG Awal<br>→ Amount</div>
                    <div class="formula-value"><?php echo fmtN($stok_awal_amt, 2); ?></div>
                    <div class="formula-sub">Σ (Pcs × BOM ÷<?php echo $conv_factor; ?>) × Harga</div>
                </div>
                <div class="formula-op">+</div>
                <div class="formula-box">
                    <div class="formula-label">Penerimaan<br>Amount</div>
                    <div class="formula-value"><?php echo fmtN($penerimaan_amt, 2); ?></div>
                    <div class="formula-sub"><?php echo fmtN($total_received_mat, 2); ?> × <?php echo fmtN($traced_mat_price, 2); ?></div>
                </div>
                <div class="formula-op">−</div>
                <div class="formula-box">
                    <div class="formula-label">Stok FG Akhir<br>→ Amount</div>
                    <div class="formula-value"><?php echo fmtN($stok_akhir_amt, 2); ?></div>
                    <div class="formula-sub">Σ (Pcs × BOM ÷<?php echo $conv_factor; ?>) × Harga</div>
                </div>
                <div class="formula-op">=</div>
                <div class="formula-box <?php echo $konsumsi_aktual_amt >= 0 ? 'result' : 'result-neg'; ?>">
                    <div class="formula-label">Konsumsi Aktual<br>Amount (Rp)</div>
                    <div class="formula-value"><?php echo fmtN($konsumsi_aktual_amt, 2); ?></div>
                    <div class="formula-sub">Rp</div>
                </div>
            </div>

            <!-- PEMBAGI -->
            <div style="display:flex;align-items:center;justify-content:center;gap:16px;margin:8px 0 16px;">
                <div style="text-align:right;">
                    <div style="font-size:10px;color:var(--t3);text-transform:uppercase;font-weight:600;">Konsumsi Aktual</div>
                    <div style="font-family:'Space Grotesk',sans-serif;font-size:16px;font-weight:700;color:var(--cyn);"><?php echo fmtN($konsumsi_aktual_amt, 2); ?></div>
                </div>
                <div style="width:48px;height:48px;border-radius:50%;background:var(--cyn-d);border:2px solid rgba(6,182,212,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <span style="font-family:'Space Grotesk',sans-serif;font-size:18px;font-weight:700;color:var(--cyn);">÷</span>
                </div>
                <div style="text-align:left;">
                    <div style="font-size:10px;color:var(--t3);text-transform:uppercase;font-weight:600;">Total Penjualan</div>
                    <div style="font-family:'Space Grotesk',sans-serif;font-size:16px;font-weight:700;color:var(--grn);"><?php echo fmtN($penjualan_amount, 2); ?></div>
                </div>
            </div>

            <!-- RATIO -->
            <div class="ratio-section">
                <div class="ratio-info">
                    <div class="ratio-row">
                        <span class="ratio-row-label">Rasio Konsumsi</span>
                        <span class="ratio-row-value" style="color:var(--cyn);"><?php echo fmtN($rasio_persen, 2); ?>%</span>
                    </div>
                    <div class="ratio-row">
                        <span class="ratio-row-label">Artinya</span>
                        <span class="ratio-row-value" style="font-size:12px;font-weight:500;color:var(--t2);">
                            Setiap <strong style="color:var(--grn);">Rp100</strong> penjualan, terpakai <strong style="color:var(--cyn);">Rp<?php echo fmtN($rasio_persen, 1); ?></strong> untuk material ini
                        </span>
                    </div>
                    <div class="ratio-row">
                        <span class="ratio-row-label">Margin Material</span>
                        <span class="ratio-row-value" style="color:<?php echo (100 - $rasio_persen) > 0 ? 'var(--grn)' : 'var(--red)'; ?>;">
                            <?php echo (100 - $rasio_persen) >= 0 ? '' : '-'; ?><?php echo fmtN(abs(100 - $rasio_persen), 2); ?>%
                        </span>
                    </div>
                </div>
                <div class="ratio-gauge">
                    <?php
                    $barW = min(max($rasio_persen, 0), 150);
                    $barClass = 'good'; $bigClass = 'neutral';
                    if ($penjualan_amount > 0) {
                        if ($rasio_persen <= 80) { $barClass = 'good'; $bigClass = 'good'; }
                        elseif ($rasio_persen <= 100) { $barClass = 'warn'; $bigClass = 'warn'; }
                        else { $barClass = 'bad'; $bigClass = 'bad'; }
                    }
                    ?>
                    <div class="ratio-gauge-bar"><div class="ratio-gauge-fill <?php echo $barClass; ?>" style="width:<?php echo ($barW / 150 * 100); ?>%;"></div></div>
                    <div class="ratio-gauge-markers">
                        <span class="m0">0%</span><span class="m50">50%</span><span class="m100">100%</span><span class="m150">150%</span>
                    </div>
                    <div class="ratio-big <?php echo $bigClass; ?>"><?php echo fmtN($rasio_persen, 2); ?>%</div>
                </div>
            </div>

            <?php if (!$has_stok_data): ?>
            <div class="konsumsi-note" style="color:var(--ylw);margin-top:14px;"><i class="fa-solid fa-triangle-exclamation"></i><span>Data stok FG (SOP/TAGS) tidak ditemukan. Konsumsi aktual dihitung hanya dari penerimaan.</span></div>
            <?php endif; ?>
            <?php if ($penjualan_amount <= 0): ?>
            <div class="konsumsi-note" style="color:var(--ylw);margin-top:14px;"><i class="fa-solid fa-triangle-exclamation"></i><span>Tidak ada penjualan, rasio tidak dapat dihitung.</span></div>
            <?php elseif ($rasio_persen > 100): ?>
            <div class="konsumsi-note" style="color:var(--red);margin-top:14px;"><i class="fa-solid fa-arrow-trend-up"></i><span>Konsumsi material <strong><?php echo fmtN($rasio_persen,1); ?>%</strong> dari penjualan — <strong>RUGI</strong>. Biaya material melebihi omzet. Harga jual perlu ditinjau ulang.</span></div>
            <?php elseif ($rasio_persen > 80): ?>
            <div class="konsumsi-note" style="color:var(--ylw);margin-top:14px;"><i class="fa-solid fa-triangle-exclamation"></i><span>Konsumsi <strong><?php echo fmtN($rasio_persen,1); ?>%</strong> — margin material sangat tipis. Perlu perhatian: overhead, tenaga kerja, dll belum terhitung.</span></div>
            <?php else: ?>
            <div class="konsumsi-note" style="color:var(--grn);margin-top:14px;"><i class="fa-solid fa-circle-check"></i><span>Konsumsi <strong><?php echo fmtN($rasio_persen,1); ?>%</strong> — margin material masih sehat.</span></div>
            <?php endif; ?>

            <!-- DETAIL KONVERSI PER FG -->
            <?php if (count($detail_konversi) > 0): ?>
            <div style="margin-top:18px;">
                <div style="font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">
                    <i class="fa-solid fa-table-list" style="color:var(--cyn);margin-right:4px;"></i> Detail Konversi per Barang Jadi
                </div>
                <div class="detail-conv">
                    <table class="dt">
                        <thead>
                            <tr>
                                <th>Barang Jadi</th>
                                <th class="num-r">BOM Qty</th>
                                <th class="num-r">FG Awal<br><span style="font-weight:400;font-size:9px;">(pcs)</span></th>
                                <th class="num-r">→ Qty<br><span style="font-weight:400;font-size:9px;">(<?php echo $mat_unit; ?>)</span></th>
                                <th class="num-r">→ Amount<br><span style="font-weight:400;font-size:9px;">(Rp)</span></th>
                                <th class="num-r">FG Akhir<br><span style="font-weight:400;font-size:9px;">(pcs)</span></th>
                                <th class="num-r">→ Qty<br><span style="font-weight:400;font-size:9px;">(<?php echo $mat_unit; ?>)</span></th>
                                <th class="num-r">→ Amount<br><span style="font-weight:400;font-size:9px;">(Rp)</span></th>
                                <th class="num-r">Terjual<br><span style="font-weight:400;font-size:9px;">(pcs)</span></th>
                                <th class="num-r">Penjualan<br><span style="font-weight:400;font-size:9px;">Amount (Rp)</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detail_konversi as $dc): ?>
                            <tr>
                                <td style="font-weight:600;white-space:nowrap;"><?php echo htmlspecialchars($dc['code']); ?></td>
                                <td class="num-r"><?php echo fmtN($dc['bom_qty'], 4); ?></td>
                                <td class="num-r"><?php echo fmtN($dc['fg_awal'], 2); ?></td>
                                <td class="num-r" style="color:var(--cyn);"><?php echo fmtN($dc['kebutuhan_awal_qty'], 4); ?></td>
                                <td class="num-r" style="color:var(--cyn);font-weight:600;"><?php echo fmtN($dc['kebutuhan_awal_amt'], 2); ?></td>
                                <td class="num-r"><?php echo fmtN($dc['fg_akhir'], 2); ?></td>
                                <td class="num-r" style="color:var(--cyn);"><?php echo fmtN($dc['kebutuhan_akhir_qty'], 4); ?></td>
                                <td class="num-r" style="color:var(--cyn);font-weight:600;"><?php echo fmtN($dc['kebutuhan_akhir_amt'], 2); ?></td>
                                <td class="num-r" style="color:var(--grn);"><?php echo fmtN($dc['sales_qty'], 2); ?></td>
                                <td class="num-r" style="color:var(--grn);font-weight:600;"><?php echo fmtN($dc['sales_amt'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td style="font-weight:700;color:var(--t2);border-bottom:none;">Total</td>
                                <td style="border-bottom:none;"></td>
                                <td style="border-bottom:none;"></td>
                                <td class="num-r" style="font-weight:700;color:var(--cyn);border-bottom:none;"><?php echo fmtN($total_stok_awal_mat, 4); ?></td>
                                <td class="num-r" style="font-weight:700;color:var(--cyn);border-bottom:none;"><?php echo fmtN($stok_awal_amt, 2); ?></td>
                                <td style="border-bottom:none;"></td>
                                <td class="num-r" style="font-weight:700;color:var(--cyn);border-bottom:none;"><?php echo fmtN($total_stok_akhir_mat, 4); ?></td>
                                <td class="num-r" style="font-weight:700;color:var(--cyn);border-bottom:none;"><?php echo fmtN($stok_akhir_amt, 2); ?></td>
                                <td style="border-bottom:none;"></td>
                                <td class="num-r" style="font-weight:700;color:var(--grn);border-bottom:none;"><?php echo fmtN($penjualan_amount, 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="konsumsi-note" style="color:var(--t3);margin-top:8px;">
                <i class="fa-solid fa-info-circle"></i>
                <span>Amount = Qty (<?php echo $mat_unit; ?>) × Harga (<?php echo htmlspecialchars($traced_po_cur); ?> <?php echo fmtN($traced_mat_price,2); ?>/<?php echo $mat_unit; ?>). Rasio dihitung dari Amount, bukan kuantitas fisik.</span>
            </div>
        </div>
    </div>

    <!-- KETERANGAN -->
    <div class="card p-4 mb-5" style="border-left:3px solid var(--pur);">
        <p style="font-size:13px;color:var(--t2);margin-bottom:8px;">
            <i class="fa-solid fa-circle-info" style="color:var(--pur);margin-right:6px;"></i>
            Qty BOM dalam satuan <strong>Gram (gr)</strong>. Amount BOM: <code style="background:rgba(0,212,170,.1);padding:2px 6px;border-radius:4px;font-size:12px;color:var(--acc);">(Qty / 1000) × Harga / Kg</code>.
        </p>
        <div class="legend-row">
            <span style="font-size:11px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.05em;">Sumber Harga:</span>
            <span class="legend-item"><span class="src-exact"><i class="fa-solid fa-check" style="font-size:8px;"></i> Bulan ini</span></span>
            <span class="legend-item"><span class="src-fallback"><i class="fa-solid fa-arrow-rotate-left" style="font-size:8px;"></i> Fallback</span></span>
            <span class="legend-item"><span class="src-none"><i class="fa-solid fa-xmark" style="font-size:8px;"></i> Kosong</span></span>
        </div>
    </div>

    <!-- ═══ DAFTAR BOM ═══ -->
    <?php foreach ($bomData as $idx => $bd):
        $p = $bd['parent'];
    ?>
    <div class="parent-card">
        <div class="parent-header">
            <div style="min-width:220px;">
                <div style="font-size:10px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">Barang Jadi #<?php echo ($idx + 1); ?></div>
                <div class="parent-code"><?php echo htmlspecialchars($p['PARENT_CODE']); ?></div>
                <div class="parent-name"><?php echo htmlspecialchars($p['PARENT_NAME']); ?></div>
                <?php if ($bd['price_period'] !== ''): ?>
                <div style="font-size:11px;color:var(--t3);margin-top:3px;"><i class="fa-regular fa-clock" style="margin-right:3px;"></i> Harga Jual: <?php echo $bd['price_period']; ?></div>
                <?php endif; ?>
                <?php if ($bd['fallback_count'] > 0 || $bd['no_price_count'] > 0): ?>
                <div class="warn-inline" style="margin-top:4px;"><i class="fa-solid fa-circle-info"></i>
                    <?php if ($bd['fallback_count'] > 0): ?><?php echo $bd['fallback_count']; ?> fallback <?php endif; ?>
                    <?php if ($bd['fallback_count'] > 0 && $bd['no_price_count'] > 0): ?>·<?php endif; ?>
                    <?php if ($bd['no_price_count'] > 0): ?><?php echo $bd['no_price_count']; ?> tanpa harga<?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="sep"></div>
            <div class="stat-mini">
                <div class="stat-mini-label">Harga Jual</div>
                <div class="stat-mini-value" style="color:var(--blu);"><?php echo $bd['has_price'] ? fmtN($bd['sell_price'], 2) : '<span style="font-size:13px;color:var(--red);font-weight:600;">Tidak ada</span>'; ?></div>
                <div style="margin-top:2px;"><?php if ($bd['has_price']): ?><span class="price-tag"><i class="fa-solid fa-tag" style="font-size:9px;"></i> PRICE</span><?php else: ?><span class="price-tag none"><i class="fa-solid fa-tag" style="font-size:9px;"></i> Kosong</span><?php endif; ?></div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-label">Total Komponen</div>
                <div class="stat-mini-value" style="color:var(--org);"><?php echo number_format($bd['mat_count']); ?> Item</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-label">Terjual (DI)</div>
               <div class="stat-mini-value" style="color:var(--grn);"><?php echo number_format($bd['sales_di_count']); ?> Surat</div>
                <?php if ($bd['sales_di_count'] > 0): ?><div style="margin-top:2px;"><span class="cust-badge"><i class="fa-solid fa-users" style="font-size:8px;"></i> <?php echo number_format(count($bd['customers'])); ?> Cust</span></div><?php endif; ?>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="dt">
                <thead><tr>
                    <th style="width:35px;text-align:center;">No</th><th>Kode Material</th><th>Nama Material</th>
                    <th style="text-align:center;">Tipe</th><th class="num-r">Qty BOM (gr)</th>
                    <th class="num-r">Harga / Kg</th><th class="num-r">Amount</th>
                    <th style="text-align:center;">Sumber</th><th style="width:80px;text-align:center;">Status</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($bd['rows'] as $ri => $row):
                        $isTraced = $row['IS_TRACED']; $src = $row['PRICE_SRC'];
                        $rowClass = $isTraced ? 'row-highlight' : ($src === 'fallback' ? 'row-fallback' : ($src === 'none' ? 'row-noprice' : ''));
                    ?>
                    <tr class="<?php echo $rowClass; ?>">
                        <td style="text-align:center;color:var(--t3);font-size:12px;"><?php echo ($ri + 1); ?></td>
                        <td style="font-weight:600;white-space:nowrap;"><?php echo htmlspecialchars($row['MAT_CODE']); ?></td>
                        <td><?php echo htmlspecialchars($row['MAT_NAME']); ?></td>
                        <td style="text-align:center;"><span class="itty-badge"><?php echo htmlspecialchars($row['ITTY_CODE']); ?></span></td>
                        <td class="num-r"><?php echo fmtN($row['BOM_QTY'], 4); ?></td>
                        <td class="num-r" style="color:<?php echo $src === 'none' ? 'var(--t3)' : 'var(--t2)'; ?>;">
                            <?php if ($src === 'none'): ?><span style="font-size:11px;font-style:italic;">0</span>
                            <?php else: ?><span style="font-size:10px;opacity:.6;margin-right:3px;"><?php echo htmlspecialchars($row['PO_CUR']); ?></span><?php echo fmtN($row['MAT_PRICE'], 2); ?><?php endif; ?>
                        </td>
                        <td class="num-r" style="font-weight:600;color:<?php echo $row['AMOUNT'] > 0 ? 'var(--acc)' : 'var(--t3)'; ?>;">
                            <?php echo $row['AMOUNT'] > 0 ? fmtN($row['AMOUNT'], 2) : '-'; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($src === 'exact'): ?><span class="src-exact"><i class="fa-solid fa-check" style="font-size:8px;"></i> Bulan ini</span>
                            <?php elseif ($src === 'fallback'): ?><span class="src-fallback"><i class="fa-solid fa-arrow-rotate-left" style="font-size:8px;"></i> Fallback</span>
                            <?php else: ?><span class="src-none"><i class="fa-solid fa-xmark" style="font-size:8px;"></i> Kosong</span><?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($isTraced): ?><span class="traced-badge"><i class="fa-solid fa-crosshairs" style="font-size:10px;"></i> Di-trace</span>
                            <?php else: ?><span style="color:var(--t3);font-size:12px;">—</span><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr>
                    <td colspan="4" style="text-align:right;font-weight:700;font-size:13px;padding:12px 14px;color:var(--t2);border-bottom:none;">Total BOM</td>
                    <td class="num-r" style="font-weight:700;font-size:14px;padding:12px 14px;color:var(--org);border-bottom:none;"><?php echo fmtN($bd['total_qty'], 4); ?></td>
                    <td style="border-bottom:none;"></td>
                    <td class="num-r" style="font-weight:700;font-size:14px;padding:12px 14px;color:var(--acc);border-bottom:none;">
                        <?php echo fmtN($bd['total_amount'], 2); ?>
                        <?php if ($bd['fallback_count'] > 0): ?><div style="font-size:10px;color:var(--ylw);font-weight:500;margin-top:1px;"><i class="fa-solid fa-arrow-rotate-left" style="font-size:8px;"></i> <?php echo $bd['fallback_count']; ?> fallback</div><?php endif; ?>
                        <?php if ($bd['no_price_count'] > 0): ?><div style="font-size:10px;color:var(--red);font-weight:500;margin-top:1px;"><i class="fa-solid fa-triangle-exclamation" style="font-size:8px;"></i> <?php echo $bd['no_price_count']; ?> tanpa harga</div><?php endif; ?>
                    </td>
                    <td style="border-bottom:none;"></td><td style="border-bottom:none;"></td>
                </tr></tfoot>
            </table>
        </div>

        <?php if ($bd['no_price_count'] > 0): ?>
        <div class="warn-box danger" style="margin:0 20px 0;"><i class="fa-solid fa-triangle-exclamation" style="margin-top:2px;flex-shrink:0;"></i><div><strong><?php echo $bd['no_price_count']; ?> komponen</strong> tanpa data penerimaan.</div></div>
        <?php endif; ?>

        <div class="section-divider">
            <span class="section-divider-label" style="color:var(--grn);"><i class="fa-solid fa-truck-fast" style="margin-right:5px;"></i> Penjualan — <?php echo htmlspecialchars($str_bulan); ?></span>
            <span class="section-divider-line"></span>
            <span style="font-size:11px;color:var(--t3);font-weight:500;"><?php echo number_format($bd['sales_di_count']); ?> surat</span>
        </div>

        <?php if ($bd['sales_di_count'] > 0): ?>
        <div class="tbl-wrap">
            <table class="dt">
                <thead><tr>
                    <th style="width:35px;text-align:center;">No</th><th>Tanggal DI</th><th>Kode Customer</th><th>Nama Customer</th>
                    <th class="num-r">Qty Jual</th><th class="num-r">Harga Satuan</th><th class="num-r">Amount</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($bd['sales'] as $si => $srow): ?>
                    <tr>
                        <td style="text-align:center;color:var(--t3);font-size:12px;"><?php echo ($si + 1); ?></td>
                        <td style="white-space:nowrap;"><i class="fa-regular fa-calendar" style="color:var(--t3);margin-right:4px;font-size:11px;"></i><?php echo $srow['DI_DATE_STR']; ?></td>
                        <td><span class="cust-badge"><?php echo htmlspecialchars($srow['CUST_CODE']); ?></span></td>
                        <td><?php echo htmlspecialchars($srow['CUST_COMP']); ?></td>
                        <td class="num-r" style="font-weight:600;"><?php echo fmtN($srow['DI_QTY'], 2); ?></td>
                        <td class="num-r"><?php echo fmtN($srow['DI_PRICE'], 2); ?></td>
                        <td class="num-r" style="font-weight:600;color:var(--grn);"><?php echo fmtN($srow['DI_AMOUNT'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr>
                    <td colspan="4" style="text-align:right;font-weight:700;font-size:13px;padding:12px 14px;color:var(--t2);border-bottom:none;">Total Penjualan</td>
                    <td class="num-r" style="font-weight:700;font-size:14px;padding:12px 14px;color:var(--grn);border-bottom:none;"><?php echo fmtN($bd['sales_total_qty'], 2); ?></td>
                    <td style="border-bottom:none;"></td>
                    <td class="num-r" style="font-weight:700;font-size:14px;padding:12px 14px;color:var(--grn);border-bottom:none;"><?php echo fmtN($bd['sales_total_amt'], 2); ?></td>
                </tr></tfoot>
            </table>
        </div>
        <?php if (count($bd['customers']) > 0): ?>
        <div class="sales-footer">
            <span style="font-size:11px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.05em;"><i class="fa-solid fa-users" style="margin-right:4px;"></i> Customer:</span>
            <?php foreach ($bd['customers'] as $cc => $cn): ?>
            <span class="cust-badge" style="font-size:11px;padding:3px 10px;"><i class="fa-solid fa-building" style="font-size:9px;"></i> <?php echo htmlspecialchars($cc); ?> — <?php echo htmlspecialchars($cn); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="empty-sm"><i class="fa-solid fa-inbox"></i> Tidak ada penjualan di bulan <?php echo htmlspecialchars($str_bulan); ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- Grand Summary -->
    <div class="card p-5 mt-2" style="border-top:3px solid var(--pur);">
        <h3 style="font-size:14px;font-weight:600;margin-bottom:14px;color:var(--t2);"><i class="fa-solid fa-list-check" style="color:var(--pur);margin-right:6px;"></i> Ringkasan Semua Barang Jadi</h3>
        <div class="tbl-wrap" style="border:1px solid var(--bdr);border-radius:10px;">
            <table class="dt">
                <thead><tr>
                    <th style="text-align:center;">No</th><th>Kode</th><th>Nama Barang Jadi</th>
                    <th class="num-r">Komponen</th><th class="num-r">Amount BOM</th>
                    <th class="num-r" style="text-align:center;">DI</th><th class="num-r" style="text-align:center;">Cust</th>
                    <th class="num-r">Qty Terjual</th><th class="num-r">Amt Penjualan</th>
                    <th class="num-r">Harga Jual</th><th style="text-align:center;">Status</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($bomData as $si => $sd): ?>
                    <tr>
                        <td style="text-align:center;color:var(--t3);"><?php echo ($si + 1); ?></td>
                        <td style="font-weight:600;white-space:nowrap;"><?php echo htmlspecialchars($sd['parent']['PARENT_CODE']); ?></td>
                        <td><?php echo htmlspecialchars($sd['parent']['PARENT_NAME']); ?></td>
                        <td class="num-r"><?php echo number_format($sd['mat_count']); ?></td>
                        <td class="num-r" style="color:var(--acc);font-weight:600;">
                            <?php echo fmtN($sd['total_amount'], 2); ?>
                            <?php if ($sd['no_price_count'] > 0): ?><span style="font-size:10px;color:var(--red);"> *</span><?php endif; ?>
                        </td>
                        <td class="num-r" style="color:var(--grn);font-weight:600;"><?php echo number_format($sd['sales_di_count']); ?></td>
                        <td class="num-r" style="color:var(--grn);"><?php echo number_format(count($sd['customers'])); ?></td>
                        <td class="num-r" style="color:var(--grn);"><?php echo $sd['sales_di_count'] > 0 ? fmtN($sd['sales_total_qty'], 2) : '-'; ?></td>
                        <td class="num-r" style="color:var(--grn);font-weight:600;"><?php echo $sd['sales_di_count'] > 0 ? fmtN($sd['sales_total_amt'], 2) : '-'; ?></td>
                        <td class="num-r" style="<?php echo $sd['has_price'] ? 'color:var(--blu);font-weight:600;' : 'color:var(--red);font-style:italic;'; ?>">
                            <?php echo $sd['has_price'] ? fmtN($sd['sell_price'], 2) : '—'; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($sd['has_price']): ?><span class="price-tag"><i class="fa-solid fa-tag" style="font-size:9px;"></i> Ada</span>
                            <?php else: ?><span class="price-tag none"><i class="fa-solid fa-xmark" style="font-size:9px;"></i> Kosong</span><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <?php $gtComp = 0; $gtBomAmt = 0; foreach ($bomData as $sd) { $gtComp += $sd['mat_count']; $gtBomAmt += $sd['total_amount']; } ?>
                    <tr>
                        <td colspan="3" style="text-align:right;font-weight:700;font-size:13px;padding:12px 14px;color:var(--t2);border-bottom:none;">Grand Total</td>
                        <td class="num-r" style="font-weight:700;font-size:13px;padding:12px 14px;color:var(--t2);border-bottom:none;"><?php echo number_format($gtComp); ?></td>
                        <td class="num-r" style="font-weight:700;font-size:14px;padding:12px 14px;color:var(--acc);border-bottom:none;"><?php echo fmtN($gtBomAmt, 2); ?></td>
                        <td class="num-r" style="font-weight:700;font-size:14px;padding:12px 14px;color:var(--grn);border-bottom:none;"><?php echo number_format($grandTotalDI); ?></td>
                        <td class="num-r" style="font-weight:700;font-size:14px;padding:12px 14px;color:var(--grn);border-bottom:none;"><?php echo number_format(count($grandCustomerList)); ?></td>
                        <td class="num-r" style="font-weight:700;font-size:14px;padding:12px 14px;color:var(--grn);border-bottom:none;"><?php echo fmtN($grandTotalQty, 2); ?></td>
                        <td class="num-r" style="font-weight:700;font-size:14px;padding:12px 14px;color:var(--grn);border-bottom:none;"><?php echo fmtN($grandTotalAmt, 2); ?></td>
                        <td style="border-bottom:none;" colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php
        $totalNoPrice = 0; foreach ($bomData as $sd) $totalNoPrice += $sd['no_price_count'];
        if ($totalNoPrice > 0):
        ?>
        <div style="margin-top:10px;font-size:11px;color:var(--red);display:flex;align-items:center;gap:5px;"><i class="fa-solid fa-asterisk"></i> * = Amount BOM belum lengkap</div>
        <?php endif; ?>

        <?php if (count($grandCustomerList) > 0): ?>
        <div style="margin-top:16px;padding:12px 16px;background:rgba(34,197,94,.04);border:1px solid rgba(34,197,94,.1);border-radius:10px;">
            <div style="font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">
                <i class="fa-solid fa-users" style="color:var(--grn);margin-right:4px;"></i> Semua Customer (<?php echo number_format(count($grandCustomerList)); ?>)
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach ($grandCustomerList as $gcc => $gcn): ?>
                <span class="cust-badge" style="font-size:11px;padding:4px 12px;"><i class="fa-solid fa-building" style="font-size:9px;"></i> <?php echo htmlspecialchars($gcc); ?> — <?php echo htmlspecialchars($gcn); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
    <?php endif; ?>

    <footer class="mt-6 text-center" style="color:var(--t3);font-size:11px;">
        <p>BOM Trace — <?php echo date('d M Y H:i'); ?> — 6 queries — Rasio berdasarkan Amount (Rp) — Stok FG→Material: SOP/TAGS×BOM — Penjualan: DI/DIPA_PAR</p>
    </footer>
</div>

</body>
</html>
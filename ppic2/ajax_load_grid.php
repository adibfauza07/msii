<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

define('LOGIN_PAGE', true); 
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

$configPath = __DIR__ . "/../config/global.php"; 
if (file_exists($configPath)) {
    require_once $configPath;
}

ob_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($conn) || $conn === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Koneksi database gagal.'));
    exit;
}

$tahun = isset($_GET['tahun']) ? trim($_GET['tahun']) : '';
$bulan = isset($_GET['bulan']) ? trim($_GET['bulan']) : '';
$mc_no = isset($_GET['mc_no']) ? trim($_GET['mc_no']) : ''; 

if ($tahun === '' || $bulan === '' || $mc_no === '') {
    echo json_encode(array('status' => 'error', 'message' => 'Parameter pencarian tidak lengkap.'));
    exit;
}

$curYear   = (int)$tahun;
$curMonth  = (int)$bulan;

// MENGHITUNG PERIODE SEBELUMNYA
$prevMonth = $curMonth - 1;
$prevYear  = $curYear;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}
$prevPeriode = sprintf('%04d%02d', $prevYear, $prevMonth); 
$prevStartDateStr = sprintf('%04d%02d01', $prevYear, $prevMonth); // Ditambahkan untuk mencari Beg. Stock bulan lalu

// Menghitung Periode Sekarang dan Selanjutnya
$nextMonth = $curMonth + 1;
$nextYear  = $curYear;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$periode      = sprintf('%04d%02d', $curYear, $curMonth);
$startDateStr = sprintf('%04d%02d01', $curYear, $curMonth); 
$spEndDateStr = date('Ymd', strtotime(sprintf('%04d-%02d-01', $curYear, $curMonth) . ' +1 month -1 day'));
$endDateStr   = date('Ymd', strtotime(sprintf('%04d-%02d-01', $curYear, $curMonth) . ' +1 month')); 

/* =================================================================================
   1. AMBIL HEADER MASTER (RPT_PPIC)
==================================================================================== */
$sqlHdr = "SELECT 
            P.ID_NO, P.MC_NO, P.PART_NAME, P.PART_NO, P.ITEM_CODE, 
            ISNULL(ICV.CUST_COMP, P.CUST) AS CUST, 
            ISNULL(P.FORECAST_N1, 0) AS FORECAST_N1, 
            ISNULL(P.CUR_PO_BO, 0) AS CUR_PO_BO, 
            ISNULL(P.CYCLE_TIME_STD, 0) AS CYCLE_TIME_STD, 
            ISNULL(P.CAVITY_STD, 0) AS CAVITY_STD, 
            ISNULL(P.PROD_PLAN, 0) AS PROD_PLAN,        
            ISNULL(P.CAP_DAY_STD, 0) AS CAP_DAY_STD,    
            ISNULL((CAST(P.PROD_PLAN AS FLOAT) / NULLIF(P.CAP_DAY_STD, 0)), 0) AS WORK_DAY, 
            ISNULL(P.ppa_qty, 0) AS ppa_qty,
            ISNULL(P.SAFETY_STK, 0) AS SAFETY_STK,
            ISNULL(P.no_urut, 0) AS NO_URUT,
            ISNULL(SOP_DATA.BEG_BALANCE, 0) AS BEG_BALANCE,
            
            -- [PERBAIKAN FINAL] KALKULASI MATEMATIS EST STOCK ACTUAL DARI BULAN SEBELUMNYA
            ISNULL((
                SELECT 
                    -- 1. Ambil Beginning Stock Bulan Lalu
                    ISNULL((
                        SELECT SUM(T.TAG_QTY)
                        FROM TAGS T
                        INNER JOIN SOP S ON T.SOP_ID = S.SOP_ID
                        INNER JOIN ITEMS I ON T.ITEM_ID = I.ITEM_ID
                        WHERE S.SOP_SDATE = ?
                          AND LTRIM(RTRIM(I.ITEM_CODE)) = LTRIM(RTRIM(P.ITEM_CODE))
                    ), 0)
                    -- 2. Tambah Pemasukan Stock (Prod OK, Prod HOLD, dll)
                    + ISNULL(SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod HOLD', 'Retur From Cust', 'Replace From Cust2') THEN D.G_TOTAL ELSE 0 END), 0)
                    -- 3. Kurangi Pengeluaran Stock (Del Actual, Limbah, dll)
                    - ISNULL(SUM(CASE WHEN D.DESC_PROD IN ('Del Actual', 'Limbah Out', 'Repl To Customer', 'Retur To Cust2', 'NG Rework') THEN D.G_TOTAL ELSE 0 END), 0)
                FROM RPT_PPIC H_PREV
                INNER JOIN RPT_PPIC_DTL D ON H_PREV.ID_NO = D.ID_NO
                WHERE H_PREV.periode = ? 
                  AND H_PREV.ITEM_CODE = P.ITEM_CODE 
                  AND H_PREV.MC_NO = P.MC_NO 
            ), 0) AS PREV_EST_ACTUAL
            
           FROM RPT_PPIC P
           LEFT JOIN ITEM_CUSTINFO_VIEW ICV ON LTRIM(RTRIM(ICV.PART_CODE)) = LTRIM(RTRIM(P.ITEM_CODE))
           LEFT JOIN (
               SELECT ITEMS.ITEM_CODE, SUM(TAGS.TAG_QTY) AS BEG_BALANCE
               FROM TAGS 
               INNER JOIN SOP ON TAGS.SOP_ID = SOP.SOP_ID 
               INNER JOIN ITEMS ON TAGS.ITEM_ID = ITEMS.ITEM_ID
               WHERE SOP.SOP_SDATE = ?
               GROUP BY ITEMS.ITEM_CODE
           ) SOP_DATA ON LTRIM(RTRIM(P.ITEM_CODE)) = LTRIM(RTRIM(SOP_DATA.ITEM_CODE))
           
           WHERE P.periode = ? AND P.MC_NO = ? 
           ORDER BY CASE WHEN ISNUMERIC(P.no_urut) = 1 THEN CAST(P.no_urut AS INT) ELSE 99999 END ASC";

// BINDING PARAMETER (Ada 5 parameter sekarang)
$stmtHdr = sqlsrv_query($conn, $sqlHdr, array($prevStartDateStr, $prevPeriode, $startDateStr, $periode, $mc_no));

if ($stmtHdr === false) {
    $errors = sqlsrv_errors();
    echo json_encode(array('status' => 'error', 'message' => 'Gagal membaca master data: ' . $errors[0]['message']));
    exit;
}

$headerRows = array();
$arrIdNos   = array();
$arrItemCodes = array(); 

while ($row = sqlsrv_fetch_array($stmtHdr, SQLSRV_FETCH_ASSOC)) {
    if (isset($row['WORK_DAY'])) $row['WORK_DAY'] = round((float)$row['WORK_DAY'], 2);
    foreach ($row as $k => $v) $row[$k] = ($v !== null) ? $v : '';
    $headerRows[] = $row;
    $arrIdNos[]   = (int)$row['ID_NO']; 
    $arrItemCodes[] = trim($row['ITEM_CODE']);
}
sqlsrv_free_stmt($stmtHdr);

if (empty($headerRows)) {
    echo json_encode(array('status' => 'error', 'message' => 'Data tidak ditemukan untuk periode ini.'));
    exit;
}

/* =================================================================================
   2. UPDATE RPT_PPIC_DTL: DEL PLAN, DEL ACTUAL & ALL TRANS TRTY (SYNC DATABASE)
==================================================================================== */
$sqlSP = "EXEC sp_PivotDeliverySchedule_ByCustomer @start_date = ?, @end_date = ?";
$stmtSP = sqlsrv_query($conn, $sqlSP, array($startDateStr, $spEndDateStr));

$spLookup = array(); 
if ($stmtSP !== false) {
    while ($rowSP = sqlsrv_fetch_array($stmtSP, SQLSRV_FETCH_ASSOC)) {
        $spLookup[trim($rowSP['ITEM_CODE'])] = $rowSP;
    }
    sqlsrv_free_stmt($stmtSP);
}

$sqlTrans = "
    SELECT 
        ITEMS.ITEM_CODE, 
        TRANS.TRTY_CODE,
        DAY(TRANS.TRAN_DATE) AS TANGGAL, 
        SUM(INV_TRAN.IT_QTY) AS QTY
    FROM TRANS 
    INNER JOIN INV_TRAN ON TRANS.TRAN_ID = INV_TRAN.TRAN_ID 
    INNER JOIN ITEMS ON INV_TRAN.ITEM_ID = ITEMS.ITEM_ID
    WHERE TRANS.TRTY_CODE IN ('23', '22', '25', '11', '13', '24') 
      AND TRANS.TRAN_DATE >= ? AND TRANS.TRAN_DATE < ?
    GROUP BY ITEMS.ITEM_CODE, TRANS.TRTY_CODE, DAY(TRANS.TRAN_DATE)
";
$stmtTrans = sqlsrv_query($conn, $sqlTrans, array($startDateStr, $endDateStr));

$trtyToDesc = array(
    '23' => 'NG Rework',
    '22' => 'Limbah Out',
    '25' => 'Repl To Customer',
    '11' => 'Retur From Cust',
    '13' => 'Retur To Cust2',
    '24' => 'Replace From Cust2'
);

$transLookup = array();
if ($stmtTrans !== false) {
    while ($row = sqlsrv_fetch_array($stmtTrans, SQLSRV_FETCH_ASSOC)) {
        $ic = trim($row['ITEM_CODE']);
        $trty = trim($row['TRTY_CODE']);
        $tgl = (int)$row['TANGGAL'];
        $qty = (float)$row['QTY'];
        
        if(isset($trtyToDesc[$trty])) {
            $desc = $trtyToDesc[$trty];
            if (!isset($transLookup[$ic])) $transLookup[$ic] = array();
            if (!isset($transLookup[$ic][$desc])) $transLookup[$ic][$desc] = array();
            $transLookup[$ic][$desc][$tgl] = $qty;
        }
    }
    sqlsrv_free_stmt($stmtTrans);
}

$trackedDescs = array_values($trtyToDesc);

sqlsrv_begin_transaction($conn);
$isUpdateSuccess = true;

$sqlFixTemplate = "
    INSERT INTO RPT_PPIC_DTL (ID_NO, DESC_PROD)
    SELECT h.ID_NO, d.DESC_PROD
    FROM RPT_PPIC h
    CROSS JOIN (
        SELECT 'NG Rework' AS DESC_PROD UNION ALL
        SELECT 'Limbah Out' UNION ALL
        SELECT 'Repl To Customer' UNION ALL
        SELECT 'Retur From Cust' UNION ALL
        SELECT 'Retur To Cust2' UNION ALL
        SELECT 'Replace From Cust2'
    ) d
    WHERE h.periode = ? AND h.MC_NO = ?
      AND NOT EXISTS (
          SELECT 1 FROM RPT_PPIC_DTL x WHERE x.ID_NO = h.ID_NO AND x.DESC_PROD = d.DESC_PROD
      )
";
$stmtFix = sqlsrv_query($conn, $sqlFixTemplate, array($periode, $mc_no));
if($stmtFix === false) { $isUpdateSuccess = false; } else { sqlsrv_free_stmt($stmtFix); }

if ($isUpdateSuccess) {
    $updD1=0; $updD2=0; $updD3=0; $updD4=0; $updD5=0; $updD6=0; $updD7=0; $updD8=0; $updD9=0; $updD10=0;
    $updD11=0; $updD12=0; $updD13=0; $updD14=0; $updD15=0; $updD16=0; $updD17=0; $updD18=0; $updD19=0; $updD20=0;
    $updD21=0; $updD22=0; $updD23=0; $updD24=0; $updD25=0; $updD26=0; $updD27=0; $updD28=0; $updD29=0; $updD30=0; $updD31=0;
    $updGTotal=0; $updIdNo=0; $updDesc='';

    $sqlUpdPrepared = "UPDATE RPT_PPIC_DTL SET 
        D1=?, D2=?, D3=?, D4=?, D5=?, D6=?, D7=?, D8=?, D9=?, D10=?,
        D11=?, D12=?, D13=?, D14=?, D15=?, D16=?, D17=?, D18=?, D19=?, D20=?,
        D21=?, D22=?, D23=?, D24=?, D25=?, D26=?, D27=?, D28=?, D29=?, D30=?, D31=?, 
        G_TOTAL=? WHERE ID_NO=? AND DESC_PROD=?";
        
    $stmtUpd = sqlsrv_prepare($conn, $sqlUpdPrepared, array(
        &$updD1, &$updD2, &$updD3, &$updD4, &$updD5, &$updD6, &$updD7, &$updD8, &$updD9, &$updD10,
        &$updD11, &$updD12, &$updD13, &$updD14, &$updD15, &$updD16, &$updD17, &$updD18, &$updD19, &$updD20,
        &$updD21, &$updD22, &$updD23, &$updD24, &$updD25, &$updD26, &$updD27, &$updD28, &$updD29, &$updD30, &$updD31,
        &$updGTotal, &$updIdNo, &$updDesc
    ));

    foreach ($headerRows as $hdr) {
        $updIdNo = $hdr['ID_NO'];
        $item_code = trim($hdr['ITEM_CODE']);

        if (isset($spLookup[$item_code])) {
            $spData = $spLookup[$item_code];
            
            // EXECUTE: Del Plan
            $updDesc = 'Del Plan';
            for ($i=1; $i<=31; $i++) { ${"updD".$i} = isset($spData[$i.'_SCH']) ? (float)$spData[$i.'_SCH'] : 0; }
            $updGTotal = isset($spData['TOTAL_SCH']) ? (float)$spData['TOTAL_SCH'] : 0;
            if (sqlsrv_execute($stmtUpd) === false) { $isUpdateSuccess = false; break; }
            
            // EXECUTE: Del Actual
            $updDesc = 'Del Actual';
            for ($i=1; $i<=31; $i++) { ${"updD".$i} = isset($spData[$i.'_DEL']) ? (float)$spData[$i.'_DEL'] : 0; }
            $updGTotal = isset($spData['TOTAL_DEL']) ? (float)$spData['TOTAL_DEL'] : 0;
            if (sqlsrv_execute($stmtUpd) === false) { $isUpdateSuccess = false; break; }
        }

        foreach ($trackedDescs as $desc) {
            $updDesc = $desc;
            $updGTotal = 0;
            for ($i=1; $i<=31; $i++) { 
                $valT = isset($transLookup[$item_code][$desc][$i]) ? $transLookup[$item_code][$desc][$i] : 0;
                ${"updD".$i} = $valT;
                $updGTotal += $valT;
            }
            if (sqlsrv_execute($stmtUpd) === false) { $isUpdateSuccess = false; break 2; }
        }
    }
}

if ($isUpdateSuccess) sqlsrv_commit($conn); else sqlsrv_rollback($conn);

/* =================================================================================
   3. AMBIL SEMUA DETAIL TRANSAKSI YANG SUDAH TER-UPDATE DARI DATABASE
==================================================================================== */
$detailsByItem = array();

if (count($arrIdNos) > 0) {
    $chunks = array_chunk($arrIdNos, 1000);
    
    foreach ($chunks as $chunk) {
        $inPlaceholders = implode(',', array_fill(0, count($chunk), '?'));
        
        $sqlDtl = "SELECT * FROM RPT_PPIC_DTL 
                   WHERE ID_NO IN ($inPlaceholders) AND DESC_PROD != 'Prod Plan R1'
                   ORDER BY ID_NO, CASE DESC_PROD 
                       WHEN 'Del Plan' THEN 1 
                       WHEN 'Del Actual' THEN 2 
                       WHEN 'Del Balance' THEN 3
                       WHEN 'Prod Plan R0' THEN 4 
                       WHEN 'Prod NG' THEN 5
                       WHEN 'Prod OK' THEN 6 
                       WHEN 'Prod HOLD' THEN 7 
                       WHEN 'Prod Balance' THEN 8
                       WHEN 'NG Rework' THEN 9 
                       WHEN 'Limbah Out' THEN 10
                       WHEN 'Repl To Customer' THEN 11
                       WHEN 'Retur From Cust' THEN 12
                       WHEN 'Retur To Cust2' THEN 13
                       WHEN 'Replace From Cust2' THEN 14
                       WHEN 'Est Stock Plan' THEN 98 
                       WHEN 'Est Stock Actual' THEN 99 
                       ELSE 20 END ASC";
                       
        $stmtDtl = sqlsrv_query($conn, $sqlDtl, $chunk);
        
        if ($stmtDtl !== false) {
            while ($row = sqlsrv_fetch_array($stmtDtl, SQLSRV_FETCH_ASSOC)) {
                for ($i = 1; $i <= 31; $i++) {
                    $col = 'D' . $i;
                    $row[$col] = ($row[$col] !== null && $row[$col] !== '') ? (float)$row[$col] : 0;
                }
                $row['G_TOTAL']   = ($row['G_TOTAL'] !== null && $row['G_TOTAL'] !== '') ? (float)$row['G_TOTAL'] : 0;
                $row['DESC_PROD'] = trim($row['DESC_PROD']); 
                
                $detailsByItem[$row['ID_NO']][] = $row;
            }
            sqlsrv_free_stmt($stmtDtl);
        }
    }
}

/* =================================================================================
   4. OPTIMASI (FRONT-END): AMBIL DATA ASSEMBLY / BOM SEKALIGUS
==================================================================================== */
$bomDataMap = array();
if (count($arrItemCodes) > 0) {
    $uniqueItemCodes = array_unique($arrItemCodes);
    $inPlaceholdersBom = implode(',', array_fill(0, count($uniqueItemCodes), '?'));
    
    $sqlBom = "SELECT 
                LTRIM(RTRIM(ITEMS_1.ITEM_CODE)) AS ITEM_CODE, 
                LTRIM(RTRIM(ITEMS.ITEM_CODE)) AS assembly_part
               FROM BOM_DEFAULT 
               INNER JOIN ITEMS ON BOM_DEFAULT.PART_ID = ITEMS.ITEM_ID 
               INNER JOIN ITEMS AS ITEMS_1 ON BOM_DEFAULT.ITEM_ID = ITEMS_1.ITEM_ID
               WHERE ITEMS_1.ITEM_CODE IN ($inPlaceholdersBom) 
                 AND ITEMS_1.ITTY_CODE = '01'";
                
    $stmtBom = sqlsrv_query($conn, $sqlBom, array_values($uniqueItemCodes));
    if ($stmtBom !== false) {
        while ($rowB = sqlsrv_fetch_array($stmtBom, SQLSRV_FETCH_ASSOC)) {
            $bomDataMap[$rowB['ITEM_CODE']][] = $rowB['assembly_part'];
        }
        sqlsrv_free_stmt($stmtBom);
    }
}

/* =================================================================================
   5. GABUNGKAN DATA & REKALKULASI RUMUS BALANCE (Di Serve ke JSON)
==================================================================================== */
$allItems = array();
foreach ($headerRows as $headerData) {
    $id_no = $headerData['ID_NO'];
    $item_code = trim($headerData['ITEM_CODE']);
    $itemData = isset($detailsByItem[$id_no]) ? $detailsByItem[$id_no] : array();
    
    $idxDelPlan = -1; $idxDelActual = -1; $idxDelBal = -1;
    $idxProdPlan = -1; $idxProdOk = -1; $idxProdBal = -1;
    
    foreach ($itemData as $k => $row) {
        if ($row['DESC_PROD'] === 'Del Plan') $idxDelPlan = $k;
        if ($row['DESC_PROD'] === 'Del Actual') $idxDelActual = $k;
        if ($row['DESC_PROD'] === 'Del Balance') $idxDelBal = $k;
        if ($row['DESC_PROD'] === 'Prod Plan R0') $idxProdPlan = $k;
        if ($row['DESC_PROD'] === 'Prod OK') $idxProdOk = $k;
        if ($row['DESC_PROD'] === 'Prod Balance') $idxProdBal = $k;
    }
    
    if ($idxDelPlan !== -1 && $idxDelActual !== -1 && $idxDelBal !== -1) {
        $prevBal = 0; $gTotalPlan = 0; $gTotalActual = 0;
        for ($i = 1; $i <= 31; $i++) {
            $col = 'D' . $i;
            $plan   = (float)$itemData[$idxDelPlan][$col];
            $actual = (float)$itemData[$idxDelActual][$col];
            
            $currentBal = $prevBal + $actual - $plan;
            $itemData[$idxDelBal][$col] = $currentBal;
            $prevBal = $currentBal;
            
            $gTotalPlan += $plan; $gTotalActual += $actual;
        }
        $itemData[$idxDelPlan]['G_TOTAL']   = $gTotalPlan;
        $itemData[$idxDelActual]['G_TOTAL'] = $gTotalActual;
        $itemData[$idxDelBal]['G_TOTAL']    = 0; 
    }
    
    if ($idxProdPlan !== -1 && $idxProdOk !== -1 && $idxProdBal !== -1) {
        $prevBal = 0; $gTotalPlan = 0; $gTotalOk = 0;
        for ($i = 1; $i <= 31; $i++) {
            $col = 'D' . $i;
            $plan = (float)$itemData[$idxProdPlan][$col];
            $ok   = (float)$itemData[$idxProdOk][$col];
            
            $currentBal = $prevBal + $ok - $plan;
            $itemData[$idxProdBal][$col] = $currentBal;
            $prevBal = $currentBal;
            
            $gTotalPlan += $plan; $gTotalOk += $ok;
        }
        $itemData[$idxProdPlan]['G_TOTAL'] = $gTotalPlan;
        $itemData[$idxProdOk]['G_TOTAL']   = $gTotalOk;
        $itemData[$idxProdBal]['G_TOTAL']  = 0; 
    }
    
    $bomList = isset($bomDataMap[$item_code]) ? $bomDataMap[$item_code] : array();
    
    $allItems[] = array(
        'header' => $headerData, 
        'data'   => $itemData,
        'bom'    => $bomList
    );
}

echo json_encode(array('status' => 'success', 'items' => $allItems));
exit;
?>
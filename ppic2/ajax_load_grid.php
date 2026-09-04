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

// Kalkulasi Bulan Depan
$nextMonth = $curMonth + 1;
$nextYear  = $curYear;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$periode      = sprintf('%04d%02d', $curYear, $curMonth);
$startDateStr = sprintf('%04d%02d01', $curYear, $curMonth); 
$spEndDateStr = date('Ymd', strtotime(sprintf('%04d-%02d-01', $curYear, $curMonth) . ' +1 month -1 day'));

/* =================================================================================
   1. AMBIL HEADER MASTER (RPT_PPIC) + JOIN TABEL STOK OPNAME (SOP) & CUSTINFO
==================================================================================== */
$sqlHdr = "SELECT 
            P.ID_NO, P.MC_NO, P.PART_NAME, P.PART_NO, P.ITEM_CODE, 
            
            -- AMBIL NAMA CUSTOMER DARI VIEW (Bukan P.CUST lagi)
            ISNULL(ICV.CUST_COMP, P.CUST) AS CUST, 
            
            ISNULL(P.FORECAST_N1, 0) AS FORECAST_N1, 
            ISNULL(P.CUR_PO_BO, 0) AS CUR_PO_BO, 
            ISNULL(P.CYCLE_TIME_STD, 0) AS CYCLE_TIME_STD, 
            ISNULL(P.CAVITY_STD, 0) AS CAVITY_STD, 
            ISNULL(P.PROD_PLAN, 0) AS PROD_PLAN,        
            ISNULL(P.CAP_DAY_STD, 0) AS CAP_DAY_STD,    
            
            -- KALKULASI WORK DAYS & SAFETY STK
            ISNULL((CAST(P.PROD_PLAN AS FLOAT) / NULLIF(P.CAP_DAY_STD, 0)), 0) AS WORK_DAY, 
            ISNULL(P.ppa_qty, 0) AS ppa_qty,
            ISNULL(P.SAFETY_STK, 0) AS SAFETY_STK,
            ISNULL(P.no_urut, 0) AS NO_URUT,
            
            -- AMBIL BEG_BALANCE DARI JOIN SOP_DATA
            ISNULL(SOP_DATA.BEG_BALANCE, 0) AS BEG_BALANCE
            
           FROM RPT_PPIC P
           
           -- 1) JOIN KE VIEW CUSTOMER UNTUK NAMA PERUSAHAAN
           LEFT JOIN ITEM_CUSTINFO_VIEW ICV ON LTRIM(RTRIM(ICV.PART_CODE)) = LTRIM(RTRIM(P.ITEM_CODE))
           
           -- 2) JOIN KE TABEL TAGS & SOP UNTUK BEGINNING BALANCE
           LEFT JOIN (
                SELECT 
                    ITEMS.ITEM_CODE,
                    SUM(TAGS.TAG_QTY) AS BEG_BALANCE
                FROM TAGS 
                INNER JOIN SOP ON TAGS.SOP_ID = SOP.SOP_ID 
                INNER JOIN ITEMS ON TAGS.ITEM_ID = ITEMS.ITEM_ID
                WHERE SOP.SOP_SDATE = ?
                GROUP BY ITEMS.ITEM_CODE
           ) SOP_DATA ON LTRIM(RTRIM(P.ITEM_CODE)) = LTRIM(RTRIM(SOP_DATA.ITEM_CODE))
           
           WHERE P.periode = ? AND P.MC_NO = ? 
           ORDER BY CASE 
                        WHEN ISNUMERIC(P.no_urut) = 1 THEN CAST(P.no_urut AS INT) 
                        ELSE 99999 
                    END ASC";

$stmtHdr = sqlsrv_query($conn, $sqlHdr, array($startDateStr, $periode, $mc_no));

if ($stmtHdr === false) {
    $errors = sqlsrv_errors();
    echo json_encode(array('status' => 'error', 'message' => 'Gagal membaca master data: ' . $errors[0]['message']));
    exit;
}

$headerRows = array();
$arrIdNos   = array();

while ($row = sqlsrv_fetch_array($stmtHdr, SQLSRV_FETCH_ASSOC)) {
    if (isset($row['WORK_DAY'])) {
        $row['WORK_DAY'] = round((float)$row['WORK_DAY'], 2);
    }
    foreach ($row as $k => $v) {
        $row[$k] = ($v !== null) ? $v : '';
    }
    $headerRows[] = $row;
    $arrIdNos[]   = (int)$row['ID_NO']; 
}
sqlsrv_free_stmt($stmtHdr);

if (empty($headerRows)) {
    echo json_encode(array('status' => 'error', 'message' => 'Data tidak ditemukan untuk periode ini.'));
    exit;
}

/* =================================================================================
   2. UPDATE RPT_PPIC_DTL: DEL PLAN, DEL ACTUAL & NG REWORK (SYNC KE DATABASE)
==================================================================================== */
// A. Ambil Data Delivery dari Stored Procedure
$sqlSP = "EXEC sp_PivotDeliverySchedule_ByCustomer @start_date = ?, @end_date = ?";
$stmtSP = sqlsrv_query($conn, $sqlSP, array($startDateStr, $spEndDateStr));

$spLookup = array(); 
if ($stmtSP !== false) {
    while ($rowSP = sqlsrv_fetch_array($stmtSP, SQLSRV_FETCH_ASSOC)) {
        $itemCodeSP = trim($rowSP['ITEM_CODE']);
        $spLookup[$itemCodeSP] = $rowSP;
    }
    sqlsrv_free_stmt($stmtSP);
}

// B. Ambil Data NG Rework dari Tabel Transaksi (TRTY_CODE = '23')
$sqlRework = "
    SELECT 
        ITEMS.ITEM_CODE, 
        DAY(TRANS.TRAN_DATE) AS TANGGAL, 
        SUM(INV_TRAN.IT_QTY) AS NG_REWORK
    FROM TRANS 
    INNER JOIN INV_TRAN ON TRANS.TRAN_ID = INV_TRAN.TRAN_ID 
    INNER JOIN ITEMS ON INV_TRAN.ITEM_ID = ITEMS.ITEM_ID
    WHERE TRANS.TRTY_CODE = '23' 
      AND TRANS.TRAN_DATE >= ? AND TRANS.TRAN_DATE < ?
    GROUP BY ITEMS.ITEM_CODE, DAY(TRANS.TRAN_DATE)
";
// Gunakan startDateStr (Tanggal 1 bulan ini) dan endDateStr (Tanggal 1 bulan depan)
$stmtRework = sqlsrv_query($conn, $sqlRework, array($startDateStr, $endDateStr));

$reworkLookup = array();
if ($stmtRework !== false) {
    while ($rowRw = sqlsrv_fetch_array($stmtRework, SQLSRV_FETCH_ASSOC)) {
        $itemCodeRw = trim($rowRw['ITEM_CODE']);
        $tglRw = (int)$rowRw['TANGGAL'];
        $qtyRw = (float)$rowRw['NG_REWORK'];
        
        if (!isset($reworkLookup[$itemCodeRw])) {
            $reworkLookup[$itemCodeRw] = array();
        }
        $reworkLookup[$itemCodeRw][$tglRw] = $qtyRw;
    }
    sqlsrv_free_stmt($stmtRework);
}

// C. Eksekusi UPDATE ke tabel RPT_PPIC_DTL
if (!empty($spLookup) || !empty($reworkLookup)) {
    sqlsrv_begin_transaction($conn);
    $isUpdateSuccess = true;

    foreach ($headerRows as $hdr) {
        $id_no = $hdr['ID_NO'];
        $item_code = trim($hdr['ITEM_CODE']);

        // -- 1. UPDATE Delivery (Plan & Actual) --
        if (isset($spLookup[$item_code])) {
            $spData = $spLookup[$item_code];
            
            $setPlan = array(); $setActual = array();
            for ($i = 1; $i <= 31; $i++) {
                $valSch = isset($spData[$i.'_SCH']) ? (float)$spData[$i.'_SCH'] : 0;
                $valDel = isset($spData[$i.'_DEL']) ? (float)$spData[$i.'_DEL'] : 0;
                
                $setPlan[]   = "D{$i} = {$valSch}";
                $setActual[] = "D{$i} = {$valDel}";
            }

            $totalPlan   = isset($spData['TOTAL_SCH']) ? (float)$spData['TOTAL_SCH'] : 0;
            $totalActual = isset($spData['TOTAL_DEL']) ? (float)$spData['TOTAL_DEL'] : 0;

            $sqlUpdateDtl = "
                UPDATE RPT_PPIC_DTL SET " . implode(', ', $setPlan) . ", G_TOTAL = ? 
                WHERE ID_NO = ? AND DESC_PROD = 'Del Plan';
                
                UPDATE RPT_PPIC_DTL SET " . implode(', ', $setActual) . ", G_TOTAL = ? 
                WHERE ID_NO = ? AND DESC_PROD = 'Del Actual';
            ";

            $stmtUpdate = sqlsrv_query($conn, $sqlUpdateDtl, array($totalPlan, $id_no, $totalActual, $id_no));
            if ($stmtUpdate === false) { $isUpdateSuccess = false; break; }
            sqlsrv_free_stmt($stmtUpdate);
        }

        // -- 2. UPDATE NG Rework --
        if (isset($reworkLookup[$item_code])) {
            $rwData = $reworkLookup[$item_code];
            
            $setRework = array();
            $totalRework = 0;
            
            for ($i = 1; $i <= 31; $i++) {
                $valRw = isset($rwData[$i]) ? $rwData[$i] : 0;
                $setRework[] = "D{$i} = {$valRw}";
                $totalRework += $valRw;
            }

            $sqlUpdRework = "
                UPDATE RPT_PPIC_DTL SET " . implode(', ', $setRework) . ", G_TOTAL = ? 
                WHERE ID_NO = ? AND DESC_PROD = 'NG Rework';
            ";

            $stmtUpdRw = sqlsrv_query($conn, $sqlUpdRework, array($totalRework, $id_no));
            if ($stmtUpdRw === false) { $isUpdateSuccess = false; break; }
            sqlsrv_free_stmt($stmtUpdRw);
        }
    }
    
    if ($isUpdateSuccess) sqlsrv_commit($conn); else sqlsrv_rollback($conn);
}

/* =================================================================================
   3. AMBIL SEMUA DETAIL TRANSAKSI YANG SUDAH TER-UPDATE DARI DATABASE
==================================================================================== */
$detailsByItem = array();

if (count($arrIdNos) > 0) {
    $chunks = array_chunk($arrIdNos, 1000);
    
    foreach ($chunks as $chunk) {
        $inPlaceholders = implode(',', array_fill(0, count($chunk), '?'));
        
        $sqlDtl = "SELECT * FROM RPT_PPIC_DTL 
                   WHERE ID_NO IN ($inPlaceholders) 
                   ORDER BY ID_NO, CASE DESC_PROD 
                       WHEN 'Del Plan' THEN 1 
                       WHEN 'Del Actual' THEN 2 
                       WHEN 'Del Balance' THEN 3
                       WHEN 'Prod Plan R0' THEN 4 
                       WHEN 'Prod Plan R1' THEN 5 
                       WHEN 'Prod NG' THEN 6
                       WHEN 'Prod OK' THEN 7 
                       WHEN 'Prod HOLD' THEN 8 
                       WHEN 'Prod Balance' THEN 9
                       WHEN 'NG Rework' THEN 10 
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
   4. GABUNGKAN DATA & REKALKULASI RUMUS BALANCE
==================================================================================== */
$allItems = array();
foreach ($headerRows as $headerData) {
    $id_no = $headerData['ID_NO'];
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
    
    $allItems[] = array('header' => $headerData, 'data' => $itemData);
}

echo json_encode(array('status' => 'success', 'items' => $allItems));
exit;
?>
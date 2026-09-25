<?php
// ajax_load_mrp.php
require_once __DIR__ . "/../config/global.php"; 

header('Content-Type: application/json; charset=utf-8');

// 1. Sanitasi Input & Validasi (PHP 5.4 Kompatibel)
$tahun = isset($_POST['tahun']) ? (int)$_POST['tahun'] : 0;
$bulan = isset($_POST['bulan']) ? (int)$_POST['bulan'] : 0;
$itemCode = isset($_POST['itemCode']) ? strip_tags(trim($_POST['itemCode'])) : '';

if ($tahun <= 0 || $bulan <= 0) {
    http_response_code(400); 
    echo json_encode(array('error' => 'Tahun dan Bulan wajib diisi.'));
    exit;
}

// Format tanggal untuk SOP_SDATE ('YYYY-MM-01')
$sopDate = sprintf('%04d-%02d-01', $tahun, $bulan);


// =========================================================================================
// QUERY 1: Master MRP Target, Used Plan, Used Act & Beginning Stock
// =========================================================================================
$sqlMain = "
    WITH BegStockCTE AS (
        SELECT 
            T.ITEM_ID, 
            SUM(T.TAG_QTY) AS begining_stok
        FROM TAGS T
        INNER JOIN SOP S ON T.SOP_ID = S.SOP_ID
        INNER JOIN LOC L ON T.LOC_ID = L.LOC_ID
        
        -- GANTI BARIS INI: Gunakan LOC_CODE agar membaca gudang WHS dan PRD secara pasti
        WHERE L.LOC_CODE IN ('WHS', 'PRD') 
          AND S.SOP_SDATE = ? 
          
        GROUP BY T.ITEM_ID
    )
    SELECT 
        V.DOC_YEAR, V.DOC_MONTH, V.ITEM_ID_MAT, V.DESC_PROD, V.SORT_ORDER, V.G_TOTAL, 
        V.D1, V.D2, V.D3, V.D4, V.D5, V.D6, V.D7, V.D8, V.D9, V.D10, 
        V.D11, V.D12, V.D13, V.D14, V.D15, V.D16, V.D17, V.D18, V.D19, V.D20, 
        V.D21, V.D22, V.D23, V.D24, V.D25, V.D26, V.D27, V.D28, V.D29, V.D30, V.D31, 
        I.ITEM_CODE, I.ITEM_NAME, I.ITEM_NO, 
        SI.SUP_COMP, 
        ISNULL(BS.begining_stok, 0) AS BEG_STOCK
    FROM VW_RPT_MUS_TOTAL V
    INNER JOIN ITEMS I ON V.ITEM_ID_MAT = I.ITEM_ID
    OUTER APPLY (
        SELECT TOP 1 SUP_COMP 
        FROM SUP_ITEMS 
        WHERE ITEM_ID = I.ITEM_ID
    ) SI
    LEFT JOIN BegStockCTE BS ON I.ITEM_ID = BS.ITEM_ID
    WHERE V.DOC_YEAR = ? AND V.DOC_MONTH = ?
";

$params = array($sopDate, $tahun, $bulan);

if ($itemCode !== '') {
    $sqlMain .= " AND I.ITEM_CODE = ?";
    $params[] = $itemCode;
}
$sqlMain .= " ORDER BY I.ITEM_CODE ASC";

$stmt = sqlsrv_query($conn, $sqlMain, $params);
if ($stmt === false) {
    http_response_code(500);
    $errors = sqlsrv_errors();
    echo json_encode(array('error' => 'Database error (Main): ' . (isset($errors[0]['message']) ? $errors[0]['message'] : 'Unknown error')));
    exit;
}

$results = array();
$emptyDays = array();
for ($i = 1; $i <= 31; $i++) { 
    $emptyDays['D'.$i] = 0; 
}

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $code = trim($row['ITEM_CODE']);
    
    if (!isset($results[$code])) {
        $results[$code] = array(
            'ITEM_CODE'   => $code,
            'ITEM_NAME'   => trim($row['ITEM_NAME']), 
            'MATERIAL_NO' => !empty($row['ITEM_NO']) ? trim($row['ITEM_NO']) : 'N/A',
            'SUPPLIER'    => !empty($row['SUP_COMP']) ? trim($row['SUP_COMP']) : 'N/A',
            'BEG_STOCK'   => (float)$row['BEG_STOCK'],
            'MIX'         => '0%',
            'rows'        => array(
                'Used Plan'  => $emptyDays,
                'Used Act'   => $emptyDays,
                'In Plan'    => $emptyDays, 
                'In Act'     => $emptyDays, 
                'Out Act'    => $emptyDays  
            )
        );
    }
    
    $descProd = trim($row['DESC_PROD']); 
    if (array_key_exists($descProd, $results[$code]['rows'])) {
        for ($i = 1; $i <= 31; $i++) {
            $results[$code]['rows'][$descProd]['D'.$i] = (float)$row['D'.$i]; 
        }
    }
}
sqlsrv_free_stmt($stmt);

// =========================================================================================
// Parameter Global untuk Query 2, 3, dan 4
// =========================================================================================
$paramsAct = array($tahun, $bulan);
if ($itemCode !== '') {
    $paramsAct[] = $itemCode; // Parameter ke-3 ditambahkan jika ada filter Item Code
}

// =========================================================================================
// QUERY 2: Kalkulasi Agregasi 'In Act' (Penerimaan)
// =========================================================================================
$sqlInAct = "
    SELECT 
        I.ITEM_CODE, 
        DAY(R.RCV_DATE) AS ACT_DAY, 
        SUM(RD.RCVD_QTY) AS TOTAL_QTY
    FROM RECEIVE R
    INNER JOIN RECEIVE_DETAIL RD ON R.RCV_ID = RD.RCV_ID
    INNER JOIN ITEMS I ON RD.ITEM_ID = I.ITEM_ID
    WHERE YEAR(R.RCV_DATE) = ? AND MONTH(R.RCV_DATE) = ?
";
if ($itemCode !== '') { $sqlInAct .= " AND I.ITEM_CODE = ?"; }
$sqlInAct .= " GROUP BY I.ITEM_CODE, DAY(R.RCV_DATE)";

$stmtIn = sqlsrv_query($conn, $sqlInAct, $paramsAct);
if ($stmtIn !== false) {
    while ($rowIn = sqlsrv_fetch_array($stmtIn, SQLSRV_FETCH_ASSOC)) {
        $code = trim($rowIn['ITEM_CODE']);
        $day = (int)$rowIn['ACT_DAY'];
        
        if (isset($results[$code])) {
            $results[$code]['rows']['In Act']['D'.$day] = (float)$rowIn['TOTAL_QTY'];
        }
    }
    sqlsrv_free_stmt($stmtIn);
}

// =========================================================================================
// QUERY 3: Kalkulasi Agregasi 'Out Act' (Pengeluaran Tipe 03 & 05)
// =========================================================================================
$sqlOutAct = "
    SELECT 
        I.ITEM_CODE, 
        DAY(T.TRAN_DATE) AS ACT_DAY, 
        SUM(IT.IT_QTY) AS TOTAL_QTY
    FROM TRANS T
    INNER JOIN INV_TRAN IT ON T.TRAN_ID = IT.TRAN_ID
    INNER JOIN ITEMS I ON IT.ITEM_ID = I.ITEM_ID
    WHERE YEAR(T.TRAN_DATE) = ? AND MONTH(T.TRAN_DATE) = ?
      AND T.TRTY_CODE IN ('03', '05')
";
// [PERBAIKAN]: Menambahkan penyesuaian parameter dan GROUP BY
if ($itemCode !== '') { $sqlOutAct .= " AND I.ITEM_CODE = ?"; }
$sqlOutAct .= " GROUP BY I.ITEM_CODE, DAY(T.TRAN_DATE)";

$stmtOut = sqlsrv_query($conn, $sqlOutAct, $paramsAct);
if ($stmtOut !== false) {
    while ($rowOut = sqlsrv_fetch_array($stmtOut, SQLSRV_FETCH_ASSOC)) {
        $code = trim($rowOut['ITEM_CODE']);
        $day = (int)$rowOut['ACT_DAY'];
        
        if (isset($results[$code])) {
            $results[$code]['rows']['Out Act']['D'.$day] = (float)$rowOut['TOTAL_QTY'];
        }
    }
    sqlsrv_free_stmt($stmtOut);
}

// =========================================================================================
// QUERY 4: Load Data 'In Plan' Tersimpan (ROW_NAME = 'R_IP')
// =========================================================================================
$sqlInPlan = "
    SELECT 
        I.ITEM_CODE, 
        D.D1, D.D2, D.D3, D.D4, D.D5, D.D6, D.D7, D.D8, D.D9, D.D10, 
        D.D11, D.D12, D.D13, D.D14, D.D15, D.D16, D.D17, D.D18, D.D19, D.D20, 
        D.D21, D.D22, D.D23, D.D24, D.D25, D.D26, D.D27, D.D28, D.D29, D.D30, D.D31
    FROM RPT_MRP H
    INNER JOIN RPT_MRP_DTL D ON H.ID_NO = D.ID_NO
    INNER JOIN ITEMS I ON D.ITEM_ID = I.ITEM_ID
    WHERE H.DOC_YEAR = ? AND H.DOC_MONTH = ? 
      AND D.ROW_NAME = 'R_IP'
";
// [PERBAIKAN]: Menambahkan string filter agar parameter bind tidak bentrok
if ($itemCode !== '') { $sqlInPlan .= " AND I.ITEM_CODE = ?"; }

$stmtInPlan = sqlsrv_query($conn, $sqlInPlan, $paramsAct);
if ($stmtInPlan !== false) {
    while ($rowInPlan = sqlsrv_fetch_array($stmtInPlan, SQLSRV_FETCH_ASSOC)) {
        $code = trim($rowInPlan['ITEM_CODE']);
        
        if (isset($results[$code])) {
            for ($i = 1; $i <= 31; $i++) {
                $results[$code]['rows']['In Plan']['D'.$i] = (float)$rowInPlan['D'.$i];
            }
        }
    }
    sqlsrv_free_stmt($stmtInPlan);
}

// =========================================================================================
// OUTPUT JSON
// =========================================================================================
echo json_encode(array_values($results));
exit;
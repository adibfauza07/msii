<?php
// ajax_load_mrp.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
session_write_close(); 
error_reporting(0);

ini_set('memory_limit', '1024M'); 
set_time_limit(300); 

require_once __DIR__ . "/../config/global.php"; 

header('Content-Type: application/json; charset=utf-8');

$tahun = isset($_POST['tahun']) ? (int)$_POST['tahun'] : 0;
$bulan = isset($_POST['bulan']) ? (int)$_POST['bulan'] : 0;
$itemCode = isset($_POST['itemCode']) ? strip_tags(trim($_POST['itemCode'])) : '';
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($tahun <= 0 || $bulan <= 0) {
    http_response_code(400); 
    echo json_encode(array('error' => 'Tahun dan Bulan wajib diisi.'));
    exit;
}

if ($action === 'get_item_list') {
    // MODIFIKASI: Filter I.ITTY_CODE <> '01' agar tidak masuk ke daftar antrean
    $sqlList = "
        SELECT DISTINCT I.ITEM_CODE 
        FROM dbo.RPT_MUS M
        INNER JOIN dbo.ITEMS I ON M.ITEM_ID = I.ITEM_ID
        WHERE M.DOC_YEAR = ? 
          AND M.DOC_MONTH = ?
          AND I.ITTY_CODE <> '01'
        ORDER BY I.ITEM_CODE ASC
    ";
    $stmtList = sqlsrv_query($conn, $sqlList, array($tahun, $bulan));
    $items = array();
    
    if ($stmtList !== false) {
        while ($r = sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)) {
            $items[] = trim($r['ITEM_CODE']);
        }
        sqlsrv_free_stmt($stmtList);
    }
    echo json_encode(array('status' => 'success', 'data' => $items));
    exit;
}

$sopDate = sprintf('%04d-%02d-01', $tahun, $bulan);

// MODIFIKASI: Filter I.ITTY_CODE <> '01' di query utama agar material ini tidak diproses sama sekali
$sqlMain = "
    WITH BegStockCTE AS (
        SELECT T.ITEM_ID, SUM(T.TAG_QTY) AS begining_stok
        FROM TAGS T
        INNER JOIN SOP S ON T.SOP_ID = S.SOP_ID
        INNER JOIN LOC L ON T.LOC_ID = L.LOC_ID
        WHERE L.LOC_CODE IN ('WHS', 'PRD') AND S.SOP_SDATE = ? 
        GROUP BY T.ITEM_ID
    )
    SELECT 
        I.ITEM_CODE, I.ITEM_NAME, I.ITEM_NO, I.ITTY_CODE, 
        SI.SUP_COMP, 
        ISNULL(BS.begining_stok, 0) AS BEG_STOCK
    FROM dbo.RPT_MUS M
    INNER JOIN ITEMS I ON M.ITEM_ID = I.ITEM_ID
    OUTER APPLY (SELECT TOP 1 SUP_COMP FROM SUP_ITEMS WHERE ITEM_ID = I.ITEM_ID) SI
    LEFT JOIN BegStockCTE BS ON I.ITEM_ID = BS.ITEM_ID
    WHERE M.DOC_YEAR = ? 
      AND M.DOC_MONTH = ?
      AND I.ITTY_CODE <> '01'
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
    echo json_encode(array('error' => 'Database error (Main).'));
    exit;
}

$results = array();
$emptyDays = array('G_TOTAL' => 0);
for ($i = 1; $i <= 31; $i++) { $emptyDays['D'.$i] = 0; }

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
}
sqlsrv_free_stmt($stmt);


// =========================================================================
// 2A. BRIDGING PLAN: Tarik Data Prod Plan dari RPT_PPIC
// =========================================================================
$periodePPIC = sprintf('%04d%02d', $tahun, $bulan);
$sqlPpicPlan = "
    SELECT 
        LTRIM(RTRIM(I_MAT.ITEM_CODE)) AS MAT_CODE,
        LTRIM(RTRIM(I_MAT.ITTY_CODE)) AS ITTY_CODE,
        ISNULL(B.QTY, 0) AS NET_WEIGHT,
        SUM(D.G_TOTAL) AS G_TOTAL,
        SUM(D.D1) AS D1, SUM(D.D2) AS D2, SUM(D.D3) AS D3, SUM(D.D4) AS D4, SUM(D.D5) AS D5, 
        SUM(D.D6) AS D6, SUM(D.D7) AS D7, SUM(D.D8) AS D8, SUM(D.D9) AS D9, SUM(D.D10) AS D10,
        SUM(D.D11) AS D11, SUM(D.D12) AS D12, SUM(D.D13) AS D13, SUM(D.D14) AS D14, SUM(D.D15) AS D15, 
        SUM(D.D16) AS D16, SUM(D.D17) AS D17, SUM(D.D18) AS D18, SUM(D.D19) AS D19, SUM(D.D20) AS D20,
        SUM(D.D21) AS D21, SUM(D.D22) AS D22, SUM(D.D23) AS D23, SUM(D.D24) AS D24, SUM(D.D25) AS D25, 
        SUM(D.D26) AS D26, SUM(D.D27) AS D27, SUM(D.D28) AS D28, SUM(D.D29) AS D29, SUM(D.D30) AS D30, 
        SUM(D.D31) AS D31
    FROM dbo.RPT_PPIC H
    INNER JOIN dbo.RPT_PPIC_DTL D ON H.ID_NO = D.ID_NO
    INNER JOIN dbo.ITEMS I_FG ON LTRIM(RTRIM(H.ITEM_CODE)) = LTRIM(RTRIM(I_FG.ITEM_CODE))
    INNER JOIN dbo.BOM_DEFAULT B ON I_FG.ITEM_ID = B.PART_ID
    INNER JOIN dbo.ITEMS I_MAT ON B.ITEM_ID = I_MAT.ITEM_ID
    WHERE H.periode = ? AND D.DESC_PROD IN ('Prod Plan R0', 'Prod Plan')
";

$paramsPlan = array($periodePPIC);
if ($itemCode !== '') {
    $sqlPpicPlan .= " AND LTRIM(RTRIM(I_MAT.ITEM_CODE)) = ?";
    $paramsPlan[] = $itemCode;
}
$sqlPpicPlan .= " GROUP BY LTRIM(RTRIM(I_MAT.ITEM_CODE)), LTRIM(RTRIM(I_MAT.ITTY_CODE)), B.QTY";

$stmtPlan = sqlsrv_query($conn, $sqlPpicPlan, $paramsPlan);
if ($stmtPlan !== false) {
    while ($rowPlan = sqlsrv_fetch_array($stmtPlan, SQLSRV_FETCH_ASSOC)) {
        $matCode = trim($rowPlan['MAT_CODE']);
        if (!isset($results[$matCode])) continue; 

        $nw = (float)$rowPlan['NET_WEIGHT'];
        $ittyCodeMat = trim($rowPlan['ITTY_CODE']);
       $multiplier = 1;
// MODIFIKASI: Hapus ITTY_CODE === '01', 03 dan 05 dikali langsung (tidak dibagi 1000)
// MODIFIKASI: ITTY_CODE 02 dan 13 dibagi 1000, 03 dan 05 dikali langsung
if ($ittyCodeMat === '02' || $ittyCodeMat === '13') { 
    $multiplier = $nw / 1000; 
} elseif ($ittyCodeMat === '03' || $ittyCodeMat === '05') { 
    $multiplier = $nw; 
}
        $totalRow = 0;
        for ($i = 1; $i <= 31; $i++) {
            $val = round(((float)$rowPlan['D'.$i] * $multiplier), 4); 
            $results[$matCode]['rows']['Used Plan']['D'.$i] += $val;
            $totalRow += $val;
        }
        $results[$matCode]['rows']['Used Plan']['G_TOTAL'] += $totalRow;
    }
    sqlsrv_free_stmt($stmtPlan);
}

// =========================================================================
// 2B. BRIDGING ACT BYPASS: Tarik Data Prod Act LANGSUNG dari Mesin (Realtime)
// =========================================================================
$startDateProd = sprintf('%04d-%02d-01 00:00:00', $tahun, $bulan);
$endDateProd   = date('Y-m-d 00:00:00', strtotime("$startDateProd +1 month"));

$sqlPpicAct = "
    SELECT 
        LTRIM(RTRIM(I_MAT.ITEM_CODE)) AS MAT_CODE,
        LTRIM(RTRIM(I_MAT.ITTY_CODE)) AS ITTY_CODE,
        ISNULL(B.QTY, 0) AS NET_WEIGHT,
        DAY(P.PD_DATE) AS ACT_DAY,
        SUM(ISNULL(P.PD_OK,0) + ISNULL(P.PD_NG,0) + ISNULL(P.PD_HO,0)) AS TOTAL_ACT
    FROM dbo.PRODUCTION P
    INNER JOIN dbo.WO W ON P.WO_ID = W.WO_ID
    INNER JOIN dbo.BOM_DEFAULT B ON W.ITEM_ID = B.PART_ID
    INNER JOIN dbo.ITEMS I_MAT ON B.ITEM_ID = I_MAT.ITEM_ID
    WHERE P.PD_DATE >= ? AND P.PD_DATE < ?
";

$paramsActDirect = array($startDateProd, $endDateProd);
if ($itemCode !== '') {
    $sqlPpicAct .= " AND LTRIM(RTRIM(I_MAT.ITEM_CODE)) = ?";
    $paramsActDirect[] = $itemCode;
}
$sqlPpicAct .= " GROUP BY LTRIM(RTRIM(I_MAT.ITEM_CODE)), LTRIM(RTRIM(I_MAT.ITTY_CODE)), B.QTY, DAY(P.PD_DATE)";

$stmtAct = sqlsrv_query($conn, $sqlPpicAct, $paramsActDirect);
if ($stmtAct !== false) {
    while ($rowAct = sqlsrv_fetch_array($stmtAct, SQLSRV_FETCH_ASSOC)) {
        $matCode = trim($rowAct['MAT_CODE']);
        if (!isset($results[$matCode])) continue; 

        $nw = (float)$rowAct['NET_WEIGHT'];
        $ittyCodeMat = trim($rowAct['ITTY_CODE']);
        $day = (int)$rowAct['ACT_DAY'];
        
        $multiplier = 1;
// MODIFIKASI: Hapus ITTY_CODE === '01', 03 dan 05 dikali langsung (tidak dibagi 1000)
// MODIFIKASI: ITTY_CODE 02 dan 13 dibagi 1000, 03 dan 05 dikali langsung
if ($ittyCodeMat === '02' || $ittyCodeMat === '13') { 
    $multiplier = $nw / 1000; 
} elseif ($ittyCodeMat === '03' || $ittyCodeMat === '05') { 
    $multiplier = $nw; 
}
        $val = round(((float)$rowAct['TOTAL_ACT'] * $multiplier), 4);
        $results[$matCode]['rows']['Used Act']['D'.$day] += $val;
        $results[$matCode]['rows']['Used Act']['G_TOTAL'] += $val;
    }
    sqlsrv_free_stmt($stmtAct);
}

// =========================================================================================
// QUERY 3 & 4 & 5: MENGAMBIL IN/OUT TRANSACTION & IN PLAN
// =========================================================================================
$paramsActGlobal = array($tahun, $bulan);
if ($itemCode !== '') { $paramsActGlobal[] = $itemCode; }

$sqlInAct = "
    SELECT I.ITEM_CODE, DAY(R.RCV_DATE) AS ACT_DAY, SUM(RD.RCVD_QTY) AS TOTAL_QTY
    FROM RECEIVE R
    INNER JOIN RECEIVE_DETAIL RD ON R.RCV_ID = RD.RCV_ID
    INNER JOIN ITEMS I ON RD.ITEM_ID = I.ITEM_ID
    WHERE YEAR(R.RCV_DATE) = ? AND MONTH(R.RCV_DATE) = ?
";
if ($itemCode !== '') { $sqlInAct .= " AND I.ITEM_CODE = ?"; }
$sqlInAct .= " GROUP BY I.ITEM_CODE, DAY(R.RCV_DATE)";

$stmtIn = sqlsrv_query($conn, $sqlInAct, $paramsActGlobal);
if ($stmtIn !== false) {
    while ($rowIn = sqlsrv_fetch_array($stmtIn, SQLSRV_FETCH_ASSOC)) {
        $code = trim($rowIn['ITEM_CODE']);
        $day = (int)$rowIn['ACT_DAY'];
        if (isset($results[$code])) { $results[$code]['rows']['In Act']['D'.$day] = (float)$rowIn['TOTAL_QTY']; }
    }
    sqlsrv_free_stmt($stmtIn);
}

$sqlOutAct = "
    SELECT I.ITEM_CODE, DAY(T.TRAN_DATE) AS ACT_DAY, SUM(IT.IT_QTY) AS TOTAL_QTY
    FROM TRANS T
    INNER JOIN INV_TRAN IT ON T.TRAN_ID = IT.TRAN_ID
    INNER JOIN ITEMS I ON IT.ITEM_ID = I.ITEM_ID
    WHERE YEAR(T.TRAN_DATE) = ? AND MONTH(T.TRAN_DATE) = ? AND T.TRTY_CODE IN ('03', '05')
";
if ($itemCode !== '') { $sqlOutAct .= " AND I.ITEM_CODE = ?"; }
$sqlOutAct .= " GROUP BY I.ITEM_CODE, DAY(T.TRAN_DATE)";

$stmtOut = sqlsrv_query($conn, $sqlOutAct, $paramsActGlobal);
if ($stmtOut !== false) {
    while ($rowOut = sqlsrv_fetch_array($stmtOut, SQLSRV_FETCH_ASSOC)) {
        $code = trim($rowOut['ITEM_CODE']);
        $day = (int)$rowOut['ACT_DAY'];
        if (isset($results[$code])) { $results[$code]['rows']['Out Act']['D'.$day] = (float)$rowOut['TOTAL_QTY']; }
    }
    sqlsrv_free_stmt($stmtOut);
}

$sqlInPlan = "
    SELECT I.ITEM_CODE, 
        D.D1, D.D2, D.D3, D.D4, D.D5, D.D6, D.D7, D.D8, D.D9, D.D10, 
        D.D11, D.D12, D.D13, D.D14, D.D15, D.D16, D.D17, D.D18, D.D19, D.D20, 
        D.D21, D.D22, D.D23, D.D24, D.D25, D.D26, D.D27, D.D28, D.D29, D.D30, D.D31
    FROM RPT_MRP H
    INNER JOIN RPT_MRP_DTL D ON H.ID_NO = D.ID_NO
    INNER JOIN ITEMS I ON D.ITEM_ID = I.ITEM_ID
    WHERE H.DOC_YEAR = ? AND H.DOC_MONTH = ? AND D.ROW_NAME = 'R_IP'
";
if ($itemCode !== '') { $sqlInPlan .= " AND I.ITEM_CODE = ?"; }

$stmtInPlan = sqlsrv_query($conn, $sqlInPlan, $paramsActGlobal);
if ($stmtInPlan !== false) {
    while ($rowInPlan = sqlsrv_fetch_array($stmtInPlan, SQLSRV_FETCH_ASSOC)) {
        $code = trim($rowInPlan['ITEM_CODE']);
        if (isset($results[$code])) {
            for ($i = 1; $i <= 31; $i++) { $results[$code]['rows']['In Plan']['D'.$i] = (float)$rowInPlan['D'.$i]; }
        }
    }
    sqlsrv_free_stmt($stmtInPlan);
}

echo json_encode(array_values($results));
exit;
?>
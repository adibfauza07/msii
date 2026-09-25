<?php
// ajax_load_mat_use.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
session_write_close(); 
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . "/../config/global.php"; 

if (!isset($conn) || $conn === false) {
    die(json_encode(array('status' => 'error', 'message' => 'Koneksi DB gagal.')));
}

$tahun   = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
$bulan   = isset($_GET['bulan']) ? (int)$_GET['bulan'] : 0;
$matCode = isset($_GET['mat_code']) ? trim($_GET['mat_code']) : '';
$action  = isset($_GET['action']) ? $_GET['action'] : '';

if ($tahun == 0 || $bulan == 0) {
    die(json_encode(array('status' => 'error', 'message' => 'Parameter Tahun dan Bulan wajib diisi.')));
}

// =========================================================================
// MODE 1: Ambil Daftar Material untuk Antrean Load Berjenjang
// =========================================================================
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

// =========================================================================
// MODE 2: Load Detail per Material (Integrasi Data)
// =========================================================================
if ($matCode == '') {
    die(json_encode(array('status' => 'error', 'message' => 'Material Code kosong.')));
}

try {
    $sqlMat = "SELECT ITEM_ID, ITEM_NAME, ITTY_CODE FROM dbo.ITEMS WHERE ITEM_CODE = ?";
    $stmtMat = sqlsrv_query($conn, $sqlMat, array($matCode));
    $itemIdMat = 0;
    $itemNameMat = '';
    $ittyCodeMat = '';
    
    if ($stmtMat && $rowMat = sqlsrv_fetch_array($stmtMat, SQLSRV_FETCH_ASSOC)) {
        $itemIdMat   = $rowMat['ITEM_ID'];
        $itemNameMat = trim($rowMat['ITEM_NAME']);
        $ittyCodeMat = trim($rowMat['ITTY_CODE']);
    }
    sqlsrv_free_stmt($stmtMat);

    if ($itemIdMat == 0) { 
        throw new Exception("Material Code tidak ditemukan di Master Items."); 
    }

    // MODIFIKASI: Validasi tambahan untuk menolak jika terlanjur diload
    if ($ittyCodeMat === '01') {
        throw new Exception("Material dengan ITTY_CODE '01' tidak dimasukkan/diproses.");
    }

    $sqlHdr = "SELECT ID_NO FROM dbo.RPT_MUS WHERE DOC_YEAR = ? AND DOC_MONTH = ? AND ITEM_ID = ?";
    $stmtHdr = sqlsrv_query($conn, $sqlHdr, array($tahun, $bulan, $itemIdMat));
    $idNo = 0;
    if ($stmtHdr && $rowHdr = sqlsrv_fetch_array($stmtHdr, SQLSRV_FETCH_ASSOC)) { 
        $idNo = $rowHdr['ID_NO']; 
    }
    sqlsrv_free_stmt($stmtHdr);

    if ($idNo == 0) { 
        throw new Exception("Data Material Usage belum di-generate untuk periode ini."); 
    }

    $woDateStr = sprintf('%04d-%02d-01 00:00:00', $tahun, $bulan);
    $sqlDtl = "
        SELECT 
            D.*, 
            I.ITEM_CODE AS FG_CODE, 
            I.ITEM_NAME AS FG_NAME,
            I.ITEM_NO AS FG_ITEM_NO,
            W.WO_NUMBER,
            W.WO_QTY,
            ISNULL(B.QTY, 0) AS NET_WEIGHT
        FROM dbo.RPT_MUS_DTL D
        INNER JOIN dbo.ITEMS I ON D.ITEM_ID_PRD = I.ITEM_ID
        LEFT JOIN (
            SELECT ITEM_ID, WO_NUMBER, WO_QTY,
                   ROW_NUMBER() OVER(PARTITION BY ITEM_ID ORDER BY WO_NUMBER DESC) as rn
            FROM dbo.WO
            WHERE WO_MMYY = CONVERT(DATETIME, ?, 120)
        ) W ON W.ITEM_ID = D.ITEM_ID_PRD AND W.rn = 1
        LEFT JOIN dbo.BOM_DEFAULT B ON B.PART_ID = D.ITEM_ID_PRD AND B.ITEM_ID = ?
        WHERE D.ID_NO = ? 
          AND D.ITEM_ID_PRD IS NOT NULL 
          AND D.ITEM_ID_PRD > 0
        ORDER BY D.ITEM_ID_PRD ASC, D.ID ASC
    ";
    
    $stmtDtl = sqlsrv_query($conn, $sqlDtl, array($woDateStr, $itemIdMat, $idNo));
    if ($stmtDtl === false) { 
        throw new Exception("SQL Error saat memuat Detail RPT_MUS_DTL."); 
    }

    $dataDetailRaw = array();
    while ($row = sqlsrv_fetch_array($stmtDtl, SQLSRV_FETCH_ASSOC)) {
        $fgId = $row['ITEM_ID_PRD'];
        
        if (!isset($dataDetailRaw[$fgId])) {
            $dataDetailRaw[$fgId] = array(
                'MAT_CODE'   => $matCode, 
                'FG_CODE'    => trim($row['FG_CODE']),
                'FG_NAME'    => trim($row['FG_NAME']),
                'FG_ITEM_NO' => trim($row['FG_ITEM_NO']) ? trim($row['FG_ITEM_NO']) : '-',
                'WO_NUMBER'  => trim($row['WO_NUMBER']) ? trim($row['WO_NUMBER']) : '-',
                'WO_QTY'     => (float)$row['WO_QTY'],
                'NET_WEIGHT' => (float)$row['NET_WEIGHT'], 
                'rows_raw'   => array()
            );
        }
    }
    sqlsrv_free_stmt($stmtDtl);

    // =========================================================================
    // 4A. BRIDGING PLAN: Tarik Data Prod Plan dari RPT_PPIC
    // =========================================================================
    $periodePPIC = sprintf('%04d%02d', $tahun, $bulan);
    $sqlPlan = "
        SELECT 
            LTRIM(RTRIM(H.ITEM_CODE)) AS FG_CODE,
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
        WHERE H.periode = ? AND D.DESC_PROD IN ('Prod Plan R0', 'Prod Plan')
        GROUP BY LTRIM(RTRIM(H.ITEM_CODE))
    ";
    $stmtPlan = sqlsrv_query($conn, $sqlPlan, array($periodePPIC));
    $ppicPlanData = array();
    if ($stmtPlan !== false) {
        while ($rowP = sqlsrv_fetch_array($stmtPlan, SQLSRV_FETCH_ASSOC)) {
            $ppicPlanData[$rowP['FG_CODE']] = $rowP;
        }
        sqlsrv_free_stmt($stmtPlan);
    }

    // =========================================================================
    // 4B. BRIDGING ACT BYPASS: Tarik Data Prod Act LANGSUNG dari Mesin
    // =========================================================================
    $startDateProd = sprintf('%04d-%02d-01 00:00:00', $tahun, $bulan);
    $endDateProd   = date('Y-m-d 00:00:00', strtotime("$startDateProd +1 month"));
    
    $sqlAct = "
        SELECT 
            LTRIM(RTRIM(I.ITEM_CODE)) AS FG_CODE,
            DAY(P.PD_DATE) AS ACT_DAY,
            SUM(ISNULL(P.PD_OK,0) + ISNULL(P.PD_NG,0) + ISNULL(P.PD_HO,0)) AS TOTAL_ACT
        FROM dbo.PRODUCTION P
        INNER JOIN dbo.WO W ON P.WO_ID = W.WO_ID
        INNER JOIN dbo.ITEMS I ON W.ITEM_ID = I.ITEM_ID
        WHERE P.PD_DATE >= ? AND P.PD_DATE < ?
        GROUP BY LTRIM(RTRIM(I.ITEM_CODE)), DAY(P.PD_DATE)
    ";
    $stmtAct = sqlsrv_query($conn, $sqlAct, array($startDateProd, $endDateProd));
    $ppicActData = array();
    if ($stmtAct !== false) {
        while ($rowA = sqlsrv_fetch_array($stmtAct, SQLSRV_FETCH_ASSOC)) {
            $fCode = $rowA['FG_CODE'];
            $day = (int)$rowA['ACT_DAY'];
            $ppicActData[$fCode][$day] = (float)$rowA['TOTAL_ACT'];
        }
        sqlsrv_free_stmt($stmtAct);
    }

    // Inisialisasi Akumulator Header
    $summaryCalc = array(
        'Used Plan'  => array('G_TOTAL' => 0),
        'Used Act'   => array('G_TOTAL' => 0),
        'Supply Act' => array('G_TOTAL' => 0)
    );
    for ($i = 1; $i <= 31; $i++) {
        $summaryCalc['Used Plan']['D'.$i] = 0;
        $summaryCalc['Used Act']['D'.$i] = 0;
        $summaryCalc['Supply Act']['D'.$i] = 0;
    }

    $finalDataDetail = array();

    // =========================================================================
    // 5. AUTO CALCULATION
    // =========================================================================
    foreach ($dataDetailRaw as $fgId => $fgData) {
        $nw = $fgData['NET_WEIGHT'];
       $multiplier = 1; 

// MODIFIKASI: Menghapus referensi ITTY_CODE == '01', 03 dan 05 dikali langsung (tidak dibagi 1000)
if ($ittyCodeMat === '02') {
    $multiplier = $nw / 1000;
} elseif ($ittyCodeMat === '03' || $ittyCodeMat === '05') {
    $multiplier = $nw;
}

        $fgCode = $fgData['FG_CODE'];
        
        // Susun Prod Plan
        $fgData['rows_raw']['Prod Plan'] = array('DESC_PROD' => 'Prod Plan', 'G_TOTAL' => 0);
        for ($d=1; $d<=31; $d++) $fgData['rows_raw']['Prod Plan']['D'.$d] = 0;
        
        if (isset($ppicPlanData[$fgCode])) {
            $fgData['rows_raw']['Prod Plan']['G_TOTAL'] = (float)$ppicPlanData[$fgCode]['G_TOTAL'];
            for ($d=1; $d<=31; $d++) {
                $fgData['rows_raw']['Prod Plan']['D'.$d] = (float)$ppicPlanData[$fgCode]['D'.$d];
            }
        }

        // Susun Prod Act (Realtime)
        $fgData['rows_raw']['Prod Act'] = array('DESC_PROD' => 'Prod Act', 'G_TOTAL' => 0);
        for ($d=1; $d<=31; $d++) $fgData['rows_raw']['Prod Act']['D'.$d] = 0;
        
        if (isset($ppicActData[$fgCode])) {
            $gTotalAct = 0;
            for ($d=1; $d<=31; $d++) {
                $val = isset($ppicActData[$fgCode][$d]) ? $ppicActData[$fgCode][$d] : 0;
                $fgData['rows_raw']['Prod Act']['D'.$d] = $val;
                $gTotalAct += $val;
            }
            $fgData['rows_raw']['Prod Act']['G_TOTAL'] = $gTotalAct;
        }

        // Kalkulasi Used Plan = Prod Plan * Multiplier
        $fgData['rows_raw']['Used Plan'] = array('DESC_PROD' => 'Used Plan', 'G_TOTAL' => 0);
        $usedPlanTotal = 0;
        for ($d = 1; $d <= 31; $d++) {
            $val = isset($fgData['rows_raw']['Prod Plan']['D'.$d]) ? ($fgData['rows_raw']['Prod Plan']['D'.$d] * $multiplier) : 0;
            $fgData['rows_raw']['Used Plan']['D'.$d] = round($val, 4);
            $usedPlanTotal += $val;
        }
        $fgData['rows_raw']['Used Plan']['G_TOTAL'] = round($usedPlanTotal, 4);

        // Kalkulasi Used Act = Prod Act * Multiplier
        $fgData['rows_raw']['Used Act'] = array('DESC_PROD' => 'Used Act', 'G_TOTAL' => 0);
        $usedActTotal = 0;
        for ($d = 1; $d <= 31; $d++) {
            $val = isset($fgData['rows_raw']['Prod Act']['D'.$d]) ? ($fgData['rows_raw']['Prod Act']['D'.$d] * $multiplier) : 0;
            $fgData['rows_raw']['Used Act']['D'.$d] = round($val, 4);
            $usedActTotal += $val;
        }
        $fgData['rows_raw']['Used Act']['G_TOTAL'] = round($usedActTotal, 4);

        $orderDesc = array('Prod Plan', 'Prod Act', 'Used Plan', 'Used Act', 'Supply Act');
        $finalRows = array();
        
        foreach ($orderDesc as $desc) {
            if (isset($fgData['rows_raw'][$desc])) {
                $finalRows[] = $fgData['rows_raw'][$desc];
                
                if (array_key_exists($desc, $summaryCalc)) {
                    $summaryCalc[$desc]['G_TOTAL'] += (float)$fgData['rows_raw'][$desc]['G_TOTAL'];
                    for ($d = 1; $d <= 31; $d++) {
                        $summaryCalc[$desc]['D'.$d] += (float)$fgData['rows_raw'][$desc]['D'.$d];
                    }
                }
            }
        }
        
        $fgData['rows'] = $finalRows;
        unset($fgData['rows_raw']);
        $finalDataDetail[] = $fgData;
    }

    $dataTotal = array();
    foreach ($summaryCalc as $desc => $vals) {
        $rowObj = array('DESC_PROD' => $desc, 'G_TOTAL' => round($vals['G_TOTAL'], 4));
        for ($d = 1; $d <= 31; $d++) { 
            $rowObj['D'.$d] = round($vals['D'.$d], 4); 
        }
        $dataTotal[] = $rowObj;
    }

    echo json_encode(array(
        'status'      => 'success',
        'mat_name'    => $itemNameMat,
        'data_total'  => $dataTotal,
        'data_detail' => $finalDataDetail
    ));

} catch (Exception $e) {
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
?>
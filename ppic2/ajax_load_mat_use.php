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

if ($tahun == 0 || $bulan == 0 || $matCode == '') {
    die(json_encode(array('status' => 'error', 'message' => 'Parameter tidak lengkap.')));
}

try {
    // 1. Dapatkan ITEM_ID dan ITTY_CODE Material secara aman (Parameterized Query)
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

    // 2. Dapatkan ID_NO Dokumen Header RPT_MUS
    $sqlHdr = "SELECT ID_NO FROM dbo.RPT_MUS WHERE DOC_YEAR = ? AND DOC_MONTH = ? AND ITEM_ID = ?";
    $stmtHdr = sqlsrv_query($conn, $sqlHdr, array($tahun, $bulan, $itemIdMat));
    $idNo = 0;
    if ($stmtHdr && $rowHdr = sqlsrv_fetch_array($stmtHdr, SQLSRV_FETCH_ASSOC)) { 
        $idNo = $rowHdr['ID_NO']; 
    }
    sqlsrv_free_stmt($stmtHdr);

    if ($idNo == 0) { 
        throw new Exception("Data Material Usage belum di-generate untuk periode ini. Silakan klik 'Generate by WO'."); 
    }

    // 3. Tarik Detail dan sertakan nilai QTY (Net Weight) dari BOM_DEFAULT secara aman
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
    
    // 4. Kelompokkan Data mentah per FG
    while ($row = sqlsrv_fetch_array($stmtDtl, SQLSRV_FETCH_ASSOC)) {
        $fgId = $row['ITEM_ID_PRD'];
        
        if (!isset($dataDetailRaw[$fgId])) {
            $dataDetailRaw[$fgId] = array(
                'FG_CODE'    => trim($row['FG_CODE']),
                'FG_NAME'    => trim($row['FG_NAME']),
                'FG_ITEM_NO' => trim($row['FG_ITEM_NO']) ? trim($row['FG_ITEM_NO']) : '-',
                'WO_NUMBER'  => trim($row['WO_NUMBER']) ? trim($row['WO_NUMBER']) : '-',
                'WO_QTY'     => (float)$row['WO_QTY'],
                'NET_WEIGHT' => (float)$row['NET_WEIGHT'], 
                'rows_raw'   => array()
            );
        }
        
        $desc = trim($row['DESC_PROD']);
        $cleanRow = array();
        foreach($row as $key => $val) {
            if (preg_match('/^D[0-9]+$/', $key) || $key === 'G_TOTAL') {
                $cleanRow[$key] = is_null($val) ? 0 : (float)$val; 
            }
        }
        $cleanRow['DESC_PROD'] = $desc;
        $dataDetailRaw[$fgId]['rows_raw'][$desc] = $cleanRow;
    }
    sqlsrv_free_stmt($stmtDtl);

    // Inisialisasi Akumulator Header Total Usage
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

    // 5. AUTO CALCULATION BERDASARKAN ITTY_CODE
    foreach ($dataDetailRaw as $fgId => $fgData) {
        $nw = $fgData['NET_WEIGHT'];
        $multiplier = 1; 
        
        // Aturan Kustom ITTY_CODE
        if ($ittyCodeMat === '02') {
            $multiplier = $nw / 1000;
        } elseif ($ittyCodeMat === '01' || $ittyCodeMat === '03') {
            $multiplier = $nw;
        }

        $prodPlan = isset($fgData['rows_raw']['Prod Plan']) ? $fgData['rows_raw']['Prod Plan'] : array();
        $prodAct  = isset($fgData['rows_raw']['Prod Act']) ? $fgData['rows_raw']['Prod Act'] : array();

        // Kalkulasi Used Plan = Prod Plan * Multiplier
        $fgData['rows_raw']['Used Plan'] = array('DESC_PROD' => 'Used Plan', 'G_TOTAL' => 0);
        $usedPlanTotal = 0;
        for ($d = 1; $d <= 31; $d++) {
            $val = isset($prodPlan['D'.$d]) ? ($prodPlan['D'.$d] * $multiplier) : 0;
            $fgData['rows_raw']['Used Plan']['D'.$d] = round($val, 4);
            $usedPlanTotal += $val;
        }
        $fgData['rows_raw']['Used Plan']['G_TOTAL'] = round($usedPlanTotal, 4);

        // Kalkulasi Used Act = Prod Act * Multiplier
        $fgData['rows_raw']['Used Act'] = array('DESC_PROD' => 'Used Act', 'G_TOTAL' => 0);
        $usedActTotal = 0;
        for ($d = 1; $d <= 31; $d++) {
            $val = isset($prodAct['D'.$d]) ? ($prodAct['D'.$d] * $multiplier) : 0;
            $fgData['rows_raw']['Used Act']['D'.$d] = round($val, 4);
            $usedActTotal += $val;
        }
        $fgData['rows_raw']['Used Act']['G_TOTAL'] = round($usedActTotal, 4);

        // Urutan baris standar pada UI
        $orderDesc = array('Prod Plan', 'Prod Act', 'Used Plan', 'Used Act', 'Supply Act');
        $finalRows = array();
        
        foreach ($orderDesc as $desc) {
            if (isset($fgData['rows_raw'][$desc])) {
                $finalRows[] = $fgData['rows_raw'][$desc];
                
                // Agregasi otomatis ke Header Summary di atas
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

    // 6. Format Total Akumulasi Header
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
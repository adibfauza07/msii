<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

define('LOGIN_PAGE', true); 
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(300); // Mencegah timeout untuk proses generate massal

$configPath = __DIR__ . "/../config/global.php"; 
if (file_exists($configPath)) {
    require_once $configPath;
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($conn) || $conn === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Koneksi database gagal.'));
    exit;
}

$tahun = isset($_POST['tahun']) ? trim($_POST['tahun']) : '';
$bulan = isset($_POST['bulan']) ? trim($_POST['bulan']) : '';
$mc_no = isset($_POST['mc_no']) ? trim($_POST['mc_no']) : '';

if ($tahun === '' || $bulan === '' || $mc_no === '') {
    echo json_encode(array('status' => 'error', 'message' => 'Tahun, Bulan, dan Machine Code wajib diisi untuk Generate.'));
    exit;
}

$periode = $tahun . str_pad($bulan, 2, '0', STR_PAD_LEFT); 
// Format tanggal untuk T-SQL (Standar SQL 2008)
$sop_date_str = sprintf('%04d-%02d-01 00:00:00', $tahun, $bulan);

if (sqlsrv_begin_transaction($conn) === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Gagal memulai transaksi database.'));
    exit;
}

try {
    // 1. Ambil WO Aktif beserta Beginning Stock
    $sqlWO = "SELECT 
                WO.WO_ID, WO.ITEM_ID, MAC.MAC_CODE AS MC_NO,
                ITEM_CUSTINFO_VIEW.PART_NAME, ITEM_CUSTINFO_VIEW.PART_NO,
                ITEM_CUSTINFO_VIEW.PART_CODE AS ITEM_CODE,
                ITEM_CUSTINFO_VIEW.CUST_COMP AS CUST,
                WO.WO_QTY AS FORECAST_N1, WO.WO_QTY AS CUR_PO_BO,
                IP.ITEM_CYTM AS CYCLE_TIME_STD, IP.ITEM_CAVT AS CAVITY_STD,
                WO.WO_CAP AS MC_CAPACITY, WO.WO_QTY AS PROD_PLAN,
                ISNULL(STK.BEG_BAL, 0) AS BEG_BALANCE
              FROM WO 
              INNER JOIN MAC ON WO.MAC_ID = MAC.MAC_ID 
              INNER JOIN MAG ON MAC.MAG_ID = MAG.MAG_ID 
              INNER JOIN ITEM_CUSTINFO_VIEW ON WO.ITEM_ID = ITEM_CUSTINFO_VIEW.ITEM_ID 
              OUTER APPLY (
                  SELECT TOP 1 ITEM_CYTM, ITEM_CAVT 
                  FROM ITEM_PROD 
                  WHERE ITEM_PROD.ITEM_ID = WO.ITEM_ID 
                  AND (ITEM_PROD.INACTIVE = 0 OR ITEM_PROD.INACTIVE IS NULL) 
                  ORDER BY ITEM_PROD.ITEM_DEFAULT_BOM DESC
              ) IP
              OUTER APPLY (
                  SELECT SUM(T.TAG_QTY) AS BEG_BAL
                  FROM TAGS T
                  INNER JOIN SOP S ON T.SOP_ID = S.SOP_ID
                  WHERE T.ITEM_ID = WO.ITEM_ID
                  AND CAST(S.SOP_SDATE AS DATE) = CAST(? AS DATE)
              ) STK
              WHERE CONVERT(VARCHAR(6), WO.WO_MMYY, 112) = ?
              AND MAC.MAC_CODE = ?";

    $stmtWO = sqlsrv_query($conn, $sqlWO, array($sop_date_str, $periode, $mc_no));
    if ($stmtWO === false) { throw new Exception("Gagal mengekstrak data Master WO."); }

    $woList = array();
    while ($row = sqlsrv_fetch_array($stmtWO, SQLSRV_FETCH_ASSOC)) { $woList[] = $row; }
    sqlsrv_free_stmt($stmtWO);

    if (empty($woList)) { throw new Exception("Tidak ada Work Order aktif untuk Mesin $mc_no pada periode $tahun-$bulan"); }

    $desktop_descriptions = array(
        'Del Plan','Del Actual','Del Balance','Prod Plan R0','Prod Plan R1','Prod NG',
        'Prod OK','Prod HOLD','Prod Balance','NG Rework','Limbah Out','Repl To Customer',
        'Retur From Cust','Retur To Cust2','Replace From Cust2','Est Stock Plan','Est Stock Actual'
    );

    $item_processed = 0;
    $item_skipped = 0; // Menghitung item yang dibatasi/skip

    foreach ($woList as $wo) {
        $current_mc_no = $wo['MC_NO'];
        $item_code     = $wo['ITEM_CODE'];
        $wo_id         = $wo['WO_ID'];
        
        $ct          = ($wo['CYCLE_TIME_STD'] !== null) ? $wo['CYCLE_TIME_STD'] : 0;
        $cav         = ($wo['CAVITY_STD'] !== null) ? $wo['CAVITY_STD'] : 0;
        $prod_plan   = ($wo['PROD_PLAN'] !== null) ? $wo['PROD_PLAN'] : 0;
        $beg_balance = isset($wo['BEG_BALANCE']) ? (float)$wo['BEG_BALANCE'] : 0;
        $safety_stk  = ceil($beg_balance * 0.25);

        // BATASI GENERATE: Cek apakah ID_NO sudah exist di master
        $sqlChkHdr = "SELECT ID_NO FROM RPT_PPIC WHERE periode = ? AND MC_NO = ? AND ITEM_CODE = ?";
        $stmtChkHdr = sqlsrv_query($conn, $sqlChkHdr, array($periode, $current_mc_no, $item_code));
        $rowHdr = sqlsrv_fetch_array($stmtChkHdr, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtChkHdr);

        if ($rowHdr) {
            // Jika sudah eksis, hentikan eksekusi loop untuk part ini dan lompat ke part selanjutnya
            $item_skipped++;
            continue; 
        }

        // INSERT BARU (Karena belum eksis)
        $sqlInsHdr = "INSERT INTO RPT_PPIC (periode, MC_NO, ITEM_CODE, PART_NO, PART_NAME, CUST, FORECAST_N1, CUR_PO_BO, CYCLE_TIME_STD, CAVITY_STD, PROD_PLAN, BEG_BALANCE, SAFETY_STK) 
                      OUTPUT INSERTED.ID_NO 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                      
        $paramsIns = array($periode, $current_mc_no, $item_code, $wo['PART_NO'], $wo['PART_NAME'], $wo['CUST'], $wo['FORECAST_N1'], $wo['CUR_PO_BO'], $ct, $cav, $prod_plan, $beg_balance, $safety_stk);
        $stmtInsHdr = sqlsrv_query($conn, $sqlInsHdr, $paramsIns);
        if ($stmtInsHdr === false) { throw new Exception("Gagal melakukan insert RPT_PPIC untuk Part: $item_code"); }
        
        $rowIns = sqlsrv_fetch_array($stmtInsHdr, SQLSRV_FETCH_ASSOC);
        $id_no = $rowIns['ID_NO'];
        sqlsrv_free_stmt($stmtInsHdr);

        // Inisialisasi Matrix Kosong
        $matrix = array();
        foreach ($desktop_descriptions as $desc) {
            $matrix[$desc] = array_fill(1, 31, 0);
        }

        // Tarik Produksi Aktual
        $sqlProd = "SELECT DAY(PD_DATE) AS DAY_NO, SUM(PD_OK) AS OK_Q, SUM(PD_HO) AS HO_Q, SUM(PD_NG) AS NG_Q 
                    FROM PRODUCTION WHERE WO_ID = ? AND PD_DATE >= ? AND PD_DATE < DATEADD(MONTH, 1, ?) GROUP BY DAY(PD_DATE)";
        $stmtProd = sqlsrv_query($conn, $sqlProd, array($wo_id, $sop_date_str, $sop_date_str));
        if ($stmtProd !== false) {
            while ($rp = sqlsrv_fetch_array($stmtProd, SQLSRV_FETCH_ASSOC)) {
                $d = (int)$rp['DAY_NO'];
                if ($d >= 1 && $d <= 31) {
                    $matrix['Prod OK'][$d] = (float)$rp['OK_Q'];
                    $matrix['Prod HOLD'][$d] = (float)$rp['HO_Q'];
                    $matrix['Prod NG'][$d] = (float)$rp['NG_Q'];
                }
            }
            sqlsrv_free_stmt($stmtProd);
        }

        // Tarik Delivery Plan
        $sqlDp = "SELECT DAY(DELS_DATE) AS DAY_NO, SUM(DELS_QTY) AS QTY FROM DELI_SCH INNER JOIN PRICE ON DELI_SCH.PRICE_ID = PRICE.PRICE_ID INNER JOIN ITEM_CUSTINFO_VIEW ON PRICE.PART_ID = ITEM_CUSTINFO_VIEW.ITEM_ID WHERE DELS_DATE >= ? AND DELS_DATE < DATEADD(MONTH, 1, ?) AND ITEM_CUSTINFO_VIEW.PART_CODE = ? GROUP BY DAY(DELS_DATE)";
        $stmtDp = sqlsrv_query($conn, $sqlDp, array($sop_date_str, $sop_date_str, $item_code));
        if ($stmtDp !== false) {
            while ($rdp = sqlsrv_fetch_array($stmtDp, SQLSRV_FETCH_ASSOC)) {
                $d = (int)$rdp['DAY_NO'];
                if ($d >= 1 && $d <= 31) $matrix['Del Plan'][$d] = (float)$rdp['QTY'];
            }
            sqlsrv_free_stmt($stmtDp);
        }

        // Tarik Delivery Actual
        $sqlDa = "SELECT DAY(DI.DI_DATE) AS DAY_NO, SUM(DIPA_PAR.QTY) AS QTY FROM DI INNER JOIN DIPA_PAR ON DI.DI_ID = DIPA_PAR.DI_ID INNER JOIN PRICE ON DIPA_PAR.PRICE_ID = PRICE.PRICE_ID INNER JOIN ITEM_CUSTINFO_VIEW ON PRICE.PART_ID = ITEM_CUSTINFO_VIEW.ITEM_ID WHERE DI.DI_DATE >= ? AND DI.DI_DATE < DATEADD(MONTH, 1, ?) AND ITEM_CUSTINFO_VIEW.PART_CODE = ? GROUP BY DAY(DI.DI_DATE)";
        $stmtDa = sqlsrv_query($conn, $sqlDa, array($sop_date_str, $sop_date_str, $item_code));
        if ($stmtDa !== false) {
            while ($rda = sqlsrv_fetch_array($stmtDa, SQLSRV_FETCH_ASSOC)) {
                $d = (int)$rda['DAY_NO'];
                if ($d >= 1 && $d <= 31) $matrix['Del Actual'][$d] = (float)$rda['QTY'];
            }
            sqlsrv_free_stmt($stmtDa);
        }

        // Simpan ke RPT_PPIC_DTL
        foreach ($desktop_descriptions as $desc) {
            $g_total = array_sum($matrix[$desc]);
            $daysVals = array_values($matrix[$desc]);
            
            $paramsDtl = array_merge(array($id_no, $desc, $g_total), $daysVals);
            $sqlInsDtl = "INSERT INTO RPT_PPIC_DTL (ID_NO, DESC_PROD, G_TOTAL, D1, D2, D3, D4, D5, D6, D7, D8, D9, D10, D11, D12, D13, D14, D15, D16, D17, D18, D19, D20, D21, D22, D23, D24, D25, D26, D27, D28, D29, D30, D31) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            sqlsrv_query($conn, $sqlInsDtl, $paramsDtl);
        }

        $item_processed++;
    }

    sqlsrv_commit($conn);
    // Info response diupdate menyesuaikan item yg terbuat dan terlewati
    echo json_encode(array('status' => 'success', 'message' => "Generate Logical Stock berhasil. $item_processed Item baru dibuat. $item_skipped Item dilewati (sudah eksis)."));
    exit;

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    exit;
}
?>
<?php
// ajax_get_matrix_ovh.php
header('Content-Type: application/json');

$configPath = __DIR__ . "/../config/global.php";
if (file_exists($configPath)) { 
    require_once $configPath; 
} else {
    echo json_encode(array('status' => 'error', 'message' => 'File config/global.php tidak ditemukan.'));
    exit;
}

if (!isset($conn) || $conn === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Koneksi database gagal.'));
    exit;
}

$tahun = isset($_POST['tahun']) ? (int)$_POST['tahun'] : (int)date('Y');
$bulan = isset($_POST['bulan']) ? (int)$_POST['bulan'] : (int)date('n');
$custCode = isset($_POST['cust_code']) ? trim($_POST['cust_code']) : '';
$totalDays = (int)date('t', strtotime("$tahun-$bulan-01"));

$dataMatrix = array();

try {
    // 0. AMBIL DATA STATUS CENTANG AKTUAL & PLAN DARI DATABASE
    $arrCheckedDatesAct = array();
    $arrCheckedDatesPlan = array();
    
    // Pastikan kolom JENIS ikut di-SELECT
    $sqlChecked = "SELECT MC_NO, ITEM_CODE, HARI, JENIS FROM TBL_OVH_CHECKED_DATES WHERE TAHUN = ? AND BULAN = ?";
    $stmtChecked = sqlsrv_query($conn, $sqlChecked, array($tahun, $bulan));
    if ($stmtChecked !== false) {
        while ($rowChk = sqlsrv_fetch_array($stmtChecked, SQLSRV_FETCH_ASSOC)) {
            $keyChk = trim($rowChk['MC_NO']) . '_' . trim($rowChk['ITEM_CODE']);
            $dayChk = (int)$rowChk['HARI'];
            $jenis = isset($rowChk['JENIS']) ? trim($rowChk['JENIS']) : 'act'; // Default ke 'act' jika kosong

            // Pisahkan array berdasarkan jenis centang
            if ($jenis === 'plan') {
                $arrCheckedDatesPlan[$keyChk][$dayChk] = 1;
            } else {
                $arrCheckedDatesAct[$keyChk][$dayChk] = 1;
            }
        }
    }

    // 1. GET LAST SHOOT
    // PERBAIKAN: Ambil data berdasarkan TAHUN dan BULAN yang sedang aktif/dipilih, BUKAN prevTahun dan prevBulan
    $sqlLastShoot = "SELECT MAC_CODE, ITEM_CODE, LAST_SHOOT FROM MATRIX_LAST_SHOOT WHERE TAHUN = ? AND BULAN = ?";
    $stmtLast = sqlsrv_query($conn, $sqlLastShoot, array($tahun, $bulan)); 
    
    $arrLastShoot = array();
    if ($stmtLast !== false) {
        while ($row = sqlsrv_fetch_array($stmtLast, SQLSRV_FETCH_ASSOC)) {
            $arrLastShoot[trim($row['MAC_CODE']) . '_' . trim($row['ITEM_CODE'])] = (float)$row['LAST_SHOOT'];
        }
    }

    // 2. GET PLAN SCHEDULING
    $sqlPlan = "
        SELECT 
            MAC.MAC_CODE AS MC_NO, Prod_Sch.PS_DATE, Prod_Sch.PS_QTY, 
            Prod_Sch.ITEM_ID, ITEMS.ITEM_CODE, ITEMS.ITEM_NAME, ITEMS.ITEM_NO, 
            ITEM_PROD.ITEM_CAVT AS CAV, CUST.CUST_COMP
        FROM Prod_Sch 
        INNER JOIN ITEMS ON Prod_Sch.ITEM_ID = ITEMS.ITEM_ID 
        INNER JOIN ITEM_PROD ON ITEMS.ITEM_ID = ITEM_PROD.ITEM_ID
        LEFT JOIN ITEM_CUSTINFO_VIEW CUST ON ITEMS.ITEM_ID = CUST.ITEM_ID
        LEFT JOIN MAC ON Prod_Sch.MAC_ID = MAC.MAC_ID
        WHERE YEAR(Prod_Sch.PS_DATE) = ? AND MONTH(Prod_Sch.PS_DATE) = ?
    ";
    
    $paramsPlan = array($tahun, $bulan);
    if (!empty($custCode)) {
        $sqlPlan .= " AND CUST.CUST_CODE = ?";
        $paramsPlan[] = $custCode;
    }
    
    $stmtPlan = sqlsrv_query($conn, $sqlPlan, $paramsPlan);
    if ($stmtPlan === false) throw new Exception(print_r(sqlsrv_errors(), true));

    // Modifikasi fungsi ini untuk menerima dua array check (act dan plan)
    function initItemArray($mc_no, $item_code, $item_no, $item_name, $cavity, $max_days, $last_shoot, $checked_days_act, $checked_days_plan) {
        $item = array(
            'mc_no' => !empty($mc_no) ? trim($mc_no) : '-', 
            'item_code' => !empty($item_code) ? trim($item_code) : '-',
            'item_no' => !empty($item_no) ? trim($item_no) : '-', 
            'item_name' => !empty($item_name) ? trim($item_name) : '-',
            'plan_cavity' => !empty($cavity) ? $cavity : '-', 
            'last_shoot' => $last_shoot, 
            'checked_dates' => $checked_days_act,       // Data centang Aktual
            'checked_dates_plan' => $checked_days_plan, // Data centang Plan
            'days' => array()
        );
        for ($d = 1; $d <= $max_days; $d++) { $item['days'][$d] = array('plan_ovh' => 0, 'act_ovh' => 0); }
        return $item;
    }

    // MAP PLAN
    while ($row = sqlsrv_fetch_array($stmtPlan, SQLSRV_FETCH_ASSOC)) {
        $custName = !empty($row['CUST_COMP']) ? trim($row['CUST_COMP']) : 'UNKNOWN CUSTOMER';
        $mcNo = isset($row['MC_NO']) ? trim($row['MC_NO']) : 'N/A';
        $itemCode = isset($row['ITEM_CODE']) ? trim($row['ITEM_CODE']) : '';
        $itemKey = $mcNo . '_' . $itemCode;
        $day = (int)$row['PS_DATE']->format('j');
        
        if ($day > $totalDays) continue;
        if (!isset($dataMatrix[$custName])) { $dataMatrix[$custName] = array(); }
        
        if (!isset($dataMatrix[$custName][$itemKey])) {
            $last = isset($arrLastShoot[$itemKey]) ? $arrLastShoot[$itemKey] : 0;
            $chkDaysAct = isset($arrCheckedDatesAct[$itemKey]) ? $arrCheckedDatesAct[$itemKey] : array();
            $chkDaysPlan = isset($arrCheckedDatesPlan[$itemKey]) ? $arrCheckedDatesPlan[$itemKey] : array();
            
            $dataMatrix[$custName][$itemKey] = initItemArray($mcNo, $itemCode, $row['ITEM_NO'], $row['ITEM_NAME'], $row['CAV'], $totalDays, $last, $chkDaysAct, $chkDaysPlan);
        }
        $shots = ((float)$row['CAV'] > 0) ? ((float)$row['PS_QTY'] / (float)$row['CAV']) : 0;
        $dataMatrix[$custName][$itemKey]['days'][$day]['plan_ovh'] += round($shots);
    }

    // 3. GET ACTUAL PRODUCTION
    $sqlAct = "
        SELECT 
            MAC.MAC_CODE AS MC_NO, PRODUCTION.PD_SHOT, PRODUCTION.PD_DATE, PRODUCTION.PD_CAV AS CAV, 
            ITEM_CUSTINFO_VIEW.CUST_COMP, ITEMS.ITEM_CODE, ITEMS.ITEM_NO, ITEMS.ITEM_NAME
        FROM PRODUCTION 
        INNER JOIN WO ON PRODUCTION.WO_ID = WO.WO_ID 
        INNER JOIN ITEM_CUSTINFO_VIEW ON WO.ITEM_ID = ITEM_CUSTINFO_VIEW.ITEM_ID
        INNER JOIN MAC ON WO.MAC_ID = MAC.MAC_ID
        LEFT JOIN ITEMS ON WO.ITEM_ID = ITEMS.ITEM_ID
        WHERE YEAR(PRODUCTION.PD_DATE) = ? AND MONTH(PRODUCTION.PD_DATE) = ?
    ";
    
    $paramsAct = array($tahun, $bulan);
    if (!empty($custCode)) {
        $sqlAct .= " AND ITEM_CUSTINFO_VIEW.CUST_CODE = ?";
        $paramsAct[] = $custCode;
    }

    $stmtAct = sqlsrv_query($conn, $sqlAct, $paramsAct);
    if ($stmtAct === false) throw new Exception(print_r(sqlsrv_errors(), true));

    // MAP ACTUAL
    while ($row = sqlsrv_fetch_array($stmtAct, SQLSRV_FETCH_ASSOC)) {
        $custName = !empty($row['CUST_COMP']) ? trim($row['CUST_COMP']) : 'UNKNOWN CUSTOMER';
        $mcNo = isset($row['MC_NO']) ? trim($row['MC_NO']) : 'N/A';
        $itemCode = isset($row['ITEM_CODE']) ? trim($row['ITEM_CODE']) : '';
        $itemKey = $mcNo . '_' . $itemCode;
        $day = (int)$row['PD_DATE']->format('j');
        
        if ($day > $totalDays) continue;
        if (!isset($dataMatrix[$custName])) { $dataMatrix[$custName] = array(); }
        
        if (!isset($dataMatrix[$custName][$itemKey])) {
            $last = isset($arrLastShoot[$itemKey]) ? $arrLastShoot[$itemKey] : 0;
            $chkDaysAct = isset($arrCheckedDatesAct[$itemKey]) ? $arrCheckedDatesAct[$itemKey] : array();
            $chkDaysPlan = isset($arrCheckedDatesPlan[$itemKey]) ? $arrCheckedDatesPlan[$itemKey] : array();
            
            $dataMatrix[$custName][$itemKey] = initItemArray($mcNo, $itemCode, $row['ITEM_NO'], $row['ITEM_NAME'], $row['CAV'], $totalDays, $last, $chkDaysAct, $chkDaysPlan);
        }
        $dataMatrix[$custName][$itemKey]['days'][$day]['act_ovh'] += round((float)$row['PD_SHOT']);
    }

    echo json_encode(array('status' => 'success', 'data' => $dataMatrix));

} catch (Exception $e) {
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
?>
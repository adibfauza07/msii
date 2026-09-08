<?php
// ajax_get_matrix_ovh.php
header('Content-Type: application/json');

// --- 1. LOAD CONFIG & KONEKSI DARI GLOBAL.PHP ---
$configPath = __DIR__ . "/../config/global.php";
if (file_exists($configPath)) { 
    require_once $configPath; 
} else {
    echo json_encode(array('status' => 'error', 'message' => 'File config/global.php tidak ditemukan.'));
    exit;
}

if (!isset($conn) || $conn === false) {
    echo json_encode(array(
        'status' => 'error', 
        'message' => 'Koneksi database gagal atau belum terinisialisasi di global.php.'
    ));
    exit;
}

// --- 2. AMBIL PARAMETER TAHUN, BULAN & FILTER CUSTOMER ---
$tahun = isset($_POST['tahun']) ? (int)$_POST['tahun'] : (int)date('Y');
$bulan = isset($_POST['bulan']) ? (int)$_POST['bulan'] : (int)date('n');
$custCode = isset($_POST['cust_code']) ? trim($_POST['cust_code']) : '';

$totalDays = (int)date('t', strtotime("$tahun-$bulan-01"));

// Hitung Periode Bulan Sebelumnya untuk Last Shoot
$prevBulan = $bulan - 1;
$prevTahun = $tahun;
if ($prevBulan == 0) {
    $prevBulan = 12;
    $prevTahun = $tahun - 1;
}

$dataMatrix = array();

try {
    // --- 3. QUERY LAST SHOOT BULAN SEBELUMNYA ---
    $sqlLastShoot = "SELECT MAC_CODE, ITEM_CODE, LAST_SHOOT FROM MATRIX_LAST_SHOOT WHERE TAHUN = ? AND BULAN = ?";
    $stmtLast = sqlsrv_query($conn, $sqlLastShoot, array($prevTahun, $prevBulan));
    
    $arrLastShoot = array();
    if ($stmtLast !== false) {
        while ($row = sqlsrv_fetch_array($stmtLast, SQLSRV_FETCH_ASSOC)) {
            $key = $row['MAC_CODE'] . '_' . $row['ITEM_CODE'];
            $arrLastShoot[$key] = (float)$row['LAST_SHOOT'];
        }
    }

    // --- 4. QUERY DATA PLAN (JADWAL) ---
    $sqlPlan = "
        SELECT 
            MAC.MAC_CODE AS MC_NO,
            Prod_Sch.PS_DATE, 
            Prod_Sch.PS_QTY, 
            Prod_Sch.ITEM_ID, 
            ITEMS.ITEM_CODE, 
            ITEMS.ITEM_NAME, 
            ITEMS.ITEM_NO, 
            ITEM_PROD.ITEM_CAVT AS CAV,
            CUST.CUST_COMP
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
    
    $resultPlan = array();
    while ($row = sqlsrv_fetch_array($stmtPlan, SQLSRV_FETCH_ASSOC)) {
        if ($row['PS_DATE'] instanceof DateTime) {
            $row['PS_DATE'] = $row['PS_DATE']->format('Y-m-d');
        }
        $resultPlan[] = $row;
    }

    // --- 5. QUERY DATA ACTUAL (PRODUKSI) ---
    $sqlAct = "
        SELECT 
            MAC.MAC_CODE AS MC_NO,
            PRODUCTION.PD_SHOT, 
            PRODUCTION.PD_DATE, 
            PRODUCTION.PD_CAV AS CAV, 
            ITEM_CUSTINFO_VIEW.CUST_CODE, 
            ITEM_CUSTINFO_VIEW.CUST_COMP,
            WO.ITEM_ID,
            ITEMS.ITEM_CODE,
            ITEMS.ITEM_NO,
            ITEMS.ITEM_NAME
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
    
    $resultAct = array();
    while ($row = sqlsrv_fetch_array($stmtAct, SQLSRV_FETCH_ASSOC)) {
        if ($row['PD_DATE'] instanceof DateTime) {
            $row['PD_DATE'] = $row['PD_DATE']->format('Y-m-d');
        }
        $resultAct[] = $row;
    }

    // --- 6. FUNGSI HELPER UNTUK INISIALISASI ARRAY ITEM ---
    function initItemArray($mc_no, $item_code, $item_no, $item_name, $cavity, $max_days, $last_shoot) {
        $item = array(
            'mc_no' => !empty($mc_no) ? $mc_no : '-',
            'item_code' => !empty($item_code) ? $item_code : '-',
            'item_no' => !empty($item_no) ? $item_no : '-',
            'item_name' => !empty($item_name) ? $item_name : '-',
            'plan_cavity' => !empty($cavity) ? $cavity : '-',
            'last_shoot' => $last_shoot,
            'days' => array()
        );
        for ($d = 1; $d <= $max_days; $d++) {
            $item['days'][$d] = array('plan_ovh' => 0, 'act_ovh' => 0);
        }
        return $item;
    }

    // --- 7. SUSUN DATA PLAN KE ARRAY ---
    foreach ($resultPlan as $row) {
        $custName = !empty($row['CUST_COMP']) ? $row['CUST_COMP'] : 'UNKNOWN CUSTOMER';
        $itemCode = isset($row['ITEM_CODE']) ? $row['ITEM_CODE'] : '';
        $itemNo   = isset($row['ITEM_NO']) ? $row['ITEM_NO'] : '';
        $itemName = isset($row['ITEM_NAME']) ? $row['ITEM_NAME'] : '';
        $mcNo     = isset($row['MC_NO']) ? $row['MC_NO'] : 'N/A'; 
        $day      = (int)date('j', strtotime($row['PS_DATE']));
        
        if ($day > $totalDays) continue;

        $cav   = (float)$row['CAV'];
        $qty   = (float)$row['PS_QTY'];
        $shots = ($cav > 0) ? ($qty / $cav) : 0;

        if (!isset($dataMatrix[$custName])) { $dataMatrix[$custName] = array(); }
        
        $itemKey = $mcNo . '_' . $itemCode;
        if (!isset($dataMatrix[$custName][$itemKey])) {
            $last_shoot_val = isset($arrLastShoot[$itemKey]) ? $arrLastShoot[$itemKey] : 0;
            $dataMatrix[$custName][$itemKey] = initItemArray($mcNo, $itemCode, $itemNo, $itemName, $cav, $totalDays, $last_shoot_val);
        }

        $dataMatrix[$custName][$itemKey]['days'][$day]['plan_ovh'] += round($shots);
    }

    // --- 8. SUSUN DATA ACTUAL KE ARRAY ---
    foreach ($resultAct as $row) {
        $custName = !empty($row['CUST_COMP']) ? $row['CUST_COMP'] : 'UNKNOWN CUSTOMER';
        $itemCode = isset($row['ITEM_CODE']) ? $row['ITEM_CODE'] : '';
        $itemNo   = isset($row['ITEM_NO']) ? $row['ITEM_NO'] : '';
        $itemName = isset($row['ITEM_NAME']) ? $row['ITEM_NAME'] : '';
        $mcNo     = isset($row['MC_NO']) ? $row['MC_NO'] : 'N/A'; 
        $day      = (int)date('j', strtotime($row['PD_DATE']));
        
        if ($day > $totalDays) continue;
        
        $actShots = (float)$row['PD_SHOT'];
        $cav      = (float)$row['CAV'];

        if (!isset($dataMatrix[$custName])) { $dataMatrix[$custName] = array(); }
        
        $itemKey = $mcNo . '_' . $itemCode;
        if (!isset($dataMatrix[$custName][$itemKey])) {
            $last_shoot_val = isset($arrLastShoot[$itemKey]) ? $arrLastShoot[$itemKey] : 0;
            $dataMatrix[$custName][$itemKey] = initItemArray($mcNo, $itemCode, $itemNo, $itemName, $cav, $totalDays, $last_shoot_val);
        }

        $dataMatrix[$custName][$itemKey]['days'][$day]['act_ovh'] += round($actShots);
    }

    // --- 9. KIRIM JSON RESPONSE ---
    echo json_encode(array(
        'status' => 'success',
        'data'   => $dataMatrix
    ));

} catch (Exception $e) {
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
?>
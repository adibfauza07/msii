<?php
// ajax_get_detail_ovh.php
header('Content-Type: application/json');

$configPath = __DIR__ . "/../config/global.php";
if (file_exists($configPath)) { 
    require_once $configPath; 
} else {
    echo json_encode(array('status' => 'error', 'message' => 'Config tidak ditemukan.')); exit;
}

$tahun = isset($_POST['tahun']) ? (int)$_POST['tahun'] : 0;
$bulan = isset($_POST['bulan']) ? (int)$_POST['bulan'] : 0;
$hari = isset($_POST['hari']) ? (int)$_POST['hari'] : 0;
$mcCode = isset($_POST['mc_code']) ? trim($_POST['mc_code']) : '';
$itemCode = isset($_POST['item_code']) ? trim($_POST['item_code']) : '';
$tipe = isset($_POST['tipe']) ? $_POST['tipe'] : 'plan';

// Validasi Tanggal (Bentuk String YYYY-MM-DD)
$dateStr = sprintf("%04d-%02d-%02d", $tahun, $bulan, $hari);

$results = array();

try {
    if ($tipe === 'plan') {
        // QUERY DETAIL JADWAL (PLAN)
        $sql = "
            SELECT 
                Prod_Sch.PS_NO AS DOC_NO,
                Prod_Sch.PS_QTY AS QTY,
                ITEM_PROD.ITEM_CAVT AS CAVITY
            FROM Prod_Sch
            INNER JOIN ITEMS ON Prod_Sch.ITEM_ID = ITEMS.ITEM_ID
            INNER JOIN ITEM_PROD ON ITEMS.ITEM_ID = ITEM_PROD.ITEM_ID
            INNER JOIN MAC ON Prod_Sch.MAC_ID = MAC.MAC_ID
            WHERE MAC.MAC_CODE = ? 
              AND ITEMS.ITEM_CODE = ? 
              AND Prod_Sch.PS_DATE = ?
        ";
    } else {
        // QUERY DETAIL AKTUAL (PRODUCTION)
        $sql = "
            SELECT 
                PRODUCTION.PD_NO AS DOC_NO,
                PRODUCTION.PD_QTY AS QTY,
                PRODUCTION.PD_CAV AS CAVITY
            FROM PRODUCTION
            INNER JOIN WO ON PRODUCTION.WO_ID = WO.WO_ID
            INNER JOIN ITEMS ON WO.ITEM_ID = ITEMS.ITEM_ID
            INNER JOIN MAC ON WO.MAC_ID = MAC.MAC_ID
            WHERE MAC.MAC_CODE = ? 
              AND ITEMS.ITEM_CODE = ? 
              AND PRODUCTION.PD_DATE = ?
        ";
    }

    $params = array($mcCode, $itemCode, $dateStr);
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        throw new Exception(print_r(sqlsrv_errors(), true));
    }

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $qty = (float)$row['QTY'];
        $cav = (float)$row['CAVITY'];
        $shots = ($cav > 0) ? round($qty / $cav) : 0;
        
        $results[] = array(
            'DOC_NO' => $row['DOC_NO'],
            'QTY' => $qty,
            'CAVITY' => $cav,
            'TOTAL_SHOTS' => $shots
        );
    }

    echo json_encode(array('status' => 'success', 'data' => $results));

} catch (Exception $e) {
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
?>
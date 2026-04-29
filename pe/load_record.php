<?php
// msii/pe/load_record.php
require_once '../config/database_p1.php';

header('Content-Type: application/json');

$mode = isset($_GET['mode']) ? $_GET['mode'] : '';
$code = isset($_GET['code']) ? intval($_GET['code']) : 0;

// 1. Mode untuk mengambil batas minimum dan maksimum Trial Code
if ($mode == 'minmax') {
    $q = sqlsrv_query($conn, "SELECT MIN(TRIAL_CODE) as min_code, MAX(TRIAL_CODE) as max_code FROM TRIAL_PE");
    $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);
    echo json_encode(['status' => 'ok', 'min_code' => $r['min_code'], 'max_code' => $r['max_code']]);
    exit;
}

$sql = "";
$params = [];

// 2. Tentukan Query berdasarkan tombol navigasi yang diklik
switch ($mode) {
    case 'last':
        $sql = "SELECT TOP 1 * FROM TRIAL_PE ORDER BY TRIAL_CODE DESC";
        break;
    case 'first':
        $sql = "SELECT TOP 1 * FROM TRIAL_PE ORDER BY TRIAL_CODE ASC";
        break;
    case 'next':
        $sql = "SELECT TOP 1 * FROM TRIAL_PE WHERE TRIAL_CODE > ? ORDER BY TRIAL_CODE ASC";
        $params = [$code];
        break;
    case 'prev':
        $sql = "SELECT TOP 1 * FROM TRIAL_PE WHERE TRIAL_CODE < ? ORDER BY TRIAL_CODE DESC";
        $params = [$code];
        break;
    case 'load':
        $sql = "SELECT TOP 1 * FROM TRIAL_PE WHERE TRIAL_CODE = ?";
        $params = [$code];
        break;
    default:
        echo json_encode(['status' => 'error']);
        exit;
}

// 3. Eksekusi Query
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Format tanggal agar bisa masuk ke input type="date"
    if ($row['DATE'] instanceof DateTime) {
        $row['DATE'] = $row['DATE']->format('Y-m-d');
    } else if (!empty($row['DATE'])) {
        $row['DATE'] = date('Y-m-d', strtotime($row['DATE']));
    }

    // Ambil nama Part, Customer, dan Material dari tabel MASTER
    $part = $row['PART_CODE'];
    $sqlMaster = "SELECT TOP 1 I.ITEM_NAME, C.CUST_COMP, MAT.ITEM_NAME as MAT_NAME 
                  FROM ITEMS I 
                  LEFT JOIN TRIAL_PE_STD STD ON I.ITEM_CODE = STD.ITEM_CODE
                  LEFT JOIN CUST C ON STD.CUST_ID = C.CUST_ID
                  LEFT JOIN ITEMS MAT ON STD.MAT_CODE = MAT.ITEM_CODE
                  WHERE I.ITEM_CODE = ?";
                  
    $qM = sqlsrv_query($conn, $sqlMaster, [$part]);
    if ($qM && $rM = sqlsrv_fetch_array($qM, SQLSRV_FETCH_ASSOC)) {
        $row['PART_NAME'] = $rM['ITEM_NAME'];
        $row['CUST_COMP'] = $rM['CUST_COMP'];
        $row['MAT_NAME']  = $rM['MAT_NAME'];
    }

    echo json_encode(['status' => 'ok', 'record' => $row]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan']);
}
?>
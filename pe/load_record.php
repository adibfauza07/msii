<?php
// FILE: msii/pe/load_record.php
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

// === PERBAIKAN UTAMA: Query Base ===
// Langsung JOIN ke tabel Master menggunakan data yang sudah tersimpan di TRIAL_PE
$sqlBase = "
    SELECT TOP 1 T.*, 
        I.ITEM_NAME AS PART_NAME, 
        C.CUST_COMP, 
        MAT.ITEM_NAME AS MAT_NAME 
    FROM TRIAL_PE T
    LEFT JOIN ITEMS I ON T.PART_CODE = I.ITEM_CODE
    LEFT JOIN CUST C ON T.CUST_ID = C.CUST_ID
    LEFT JOIN ITEMS MAT ON T.MAT_USING = MAT.ITEM_ID
";

// 2. Tentukan Kondisi WHERE berdasarkan navigasi
switch ($mode) {
    case 'last':
        $sql = $sqlBase . " ORDER BY T.TRIAL_CODE DESC";
        break;
    case 'first':
        $sql = $sqlBase . " ORDER BY T.TRIAL_CODE ASC";
        break;
    case 'next':
        $sql = $sqlBase . " WHERE T.TRIAL_CODE > ? ORDER BY T.TRIAL_CODE ASC";
        $params = [$code];
        break;
    case 'prev':
        $sql = $sqlBase . " WHERE T.TRIAL_CODE < ? ORDER BY T.TRIAL_CODE DESC";
        $params = [$code];
        break;
    case 'load':
        $sql = $sqlBase . " WHERE T.TRIAL_CODE = ?";
        $params = [$code];
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Mode tidak valid']);
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

    echo json_encode(['status' => 'ok', 'record' => $row]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan']);
}
?>
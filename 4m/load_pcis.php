<?php
// FILE: 4m/load_pcis.php
require_once '../config/database.php';

header('Content-Type: application/json');

$mode = isset($_GET['mode']) ? $_GET['mode'] : '';
$id   = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 1. Ambil ID terkecil dan terbesar untuk membatasi tombol Next/Prev
if ($mode == 'minmax') {
    $q = q("SELECT MIN(CONTROL_ID) as min_id, MAX(CONTROL_ID) as max_id FROM PROSES_CHANGE");
    $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);
    echo json_encode(['status' => 'ok', 'min_id' => $r['min_id'], 'max_id' => $r['max_id']]);
    exit;
}

$sql = "";
$params = [];

// Base Query: Menggabungkan PROSES_CHANGE dengan View Part/Customer dan Dept
// Langsung JOIN ke tabel Master ITEMS untuk mengambil nama Material
$sqlBase = "
    SELECT TOP 1 P.*, 
        V.PART_NAME, 
        V.PART_NO, 
        V.PART_CODE, 
        V.CUST_COMP, 
        D.DEP_NAME,
        MAT.ITEM_NAME AS MATERIAL_NAME
    FROM PROSES_CHANGE P
    LEFT JOIN ITEM_CUSTINFO_VIEW V ON P.ITEM_ID = V.ITEM_ID
    LEFT JOIN DEPT D ON P.DEP_CODE = D.DEP_CODE
    LEFT JOIN ITEMS MAT ON P.MATERIAL_ID = MAT.ITEM_ID
";

switch ($mode) {
    case 'last':
        $sql = $sqlBase . " ORDER BY P.CONTROL_ID DESC";
        break;
    case 'first':
        $sql = $sqlBase . " ORDER BY P.CONTROL_ID ASC";
        break;
    case 'next':
        $sql = $sqlBase . " WHERE P.CONTROL_ID > ? ORDER BY P.CONTROL_ID ASC";
        $params = [$id];
        break;
    case 'prev':
        $sql = $sqlBase . " WHERE P.CONTROL_ID < ? ORDER BY P.CONTROL_ID DESC";
        $params = [$id];
        break;
    case 'load':
        $sql = $sqlBase . " WHERE P.CONTROL_ID = ?";
        $params = [$id];
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Mode tidak valid']);
        exit;
}

$stmt = q($sql, $params);

if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Format tanggal untuk input type="date"
    if ($row['CONTROL_DATE1'] instanceof DateTime) {
        $row['CONTROL_DATE1'] = $row['CONTROL_DATE1']->format('Y-m-d');
    } else if (!empty($row['CONTROL_DATE1'])) {
        $row['CONTROL_DATE1'] = date('Y-m-d', strtotime($row['CONTROL_DATE1']));
    }
    
    // Format tanggal jadwal
    foreach(['SCH_CHANGE', 'START_CHANGE', 'CLOSE_CHANGE'] as $tgl) {
        if ($row[$tgl] instanceof DateTime) {
            $row[$tgl] = $row[$tgl]->format('Y-m-d');
        } else if (!empty($row[$tgl])) {
            $row[$tgl] = date('Y-m-d', strtotime($row[$tgl]));
        }
    }

    echo json_encode(['status' => 'ok', 'record' => $row]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan']);
}
?>
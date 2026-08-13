<?php
/**
 * Membaca data riwayat dari Database untuk DataTables
 */
require_once __DIR__ . "/../config/global.php";
header('Content-Type: application/json; charset=UTF-8');

if (!$conn) {
    echo json_encode(['data' => []]);
    exit;
}

// Mengambil data, diurutkan dari yang paling baru dicetak (DESC)
$sql = "SELECT 
            ID, 
            CONVERT(VARCHAR(16), PRINT_TIME, 120) AS print_time, 
            ITEM_CODE, 
            MATERIAL_NAME, 
            MATERIAL_GRADE, 
            COLOUR,
            CONVERT(VARCHAR(10), RECEIVE_DATE, 120) AS receive_date,
            CONVERT(VARCHAR(10), ISSUE_DATE, 120) AS issue_date,
            CONVERT(VARCHAR(10), EXPIRED_DATE, 120) AS expired_date
        FROM dbo.LABEL_MATERIAL_HISTORY 
        ORDER BY PRINT_TIME DESC";

$stmt = sqlsrv_query($conn, $sql);
$data = array();

if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = array(
            'id'             => $row['ID'],
            'print_time'     => $row['print_time'],
            'item_code'      => $row['ITEM_CODE'],
            'material_name'  => $row['MATERIAL_NAME'],
            'material_grade' => $row['MATERIAL_GRADE'],
            'colour'         => $row['COLOUR'],
            'receive_date'   => $row['receive_date'],
            'issue_date'     => $row['issue_date'],
            'expired_date'   => $row['expired_date']
        );
    }
    sqlsrv_free_stmt($stmt);
}

// Format 'data' adalah format standar yang dikenali oleh DataTables
echo json_encode(['data' => $data]);
?>
<?php
// Pastikan path ke global.php sudah benar sesuai struktur folder Anda
require_once __DIR__ . "/../config/global.php";
header('Content-Type: application/json');

if ($conn === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Koneksi database gagal.'));
    exit;
}

// PERBAIKAN T-SQL: Gunakan klausa IN untuk exact match agar hanya mengambil PRD dan WHS saja
$sql = "SELECT LOC_ID, LOC_CODE, LOC_NAME 
        FROM LOC 
        WHERE LOC_CODE IN ('PRD', 'WHS')
        ORDER BY LOC_CODE ASC";
        
$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Gagal mengambil data lokasi.'));
    exit;
}

$locations = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Sanitasi XSS untuk output JSON (Penting di PHP 5.4)
    $locations[] = array(
        'LOC_ID'   => $row['LOC_ID'],
        'LOC_CODE' => htmlspecialchars($row['LOC_CODE'], ENT_QUOTES, 'UTF-8'),
        'LOC_NAME' => htmlspecialchars($row['LOC_NAME'], ENT_QUOTES, 'UTF-8')
    );
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

echo json_encode(array('status' => 'success', 'data' => $locations));
?>
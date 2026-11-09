<?php
// FILE: ajax_search_spc_code.php
require_once __DIR__ . '/../config/global.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($conn) || $conn === false) {
    echo json_encode(['results' => [['id' => '', 'text' => "DB Error: Koneksi Gagal"]]]);
    exit;
}

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

// Cari berdasarkan SpcCode, PartNo, atau Nama Inspector
$sql = "
    SELECT TOP 20 h.SpcCode, h.PartNo, m.PartName, h.Tgl_Inspeksi 
    FROM SPC_Inspection_Header h
    LEFT JOIN Master_Part_Spec m ON h.PartNo = m.PartNo
    WHERE h.SpcCode LIKE ? OR h.PartNo LIKE ? OR h.Inspector LIKE ?
    ORDER BY h.SpcID DESC
";

$params = ["%".$term."%", "%".$term."%", "%".$term."%"];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(['results' => [['id' => '', 'text' => "SQL Error"]], 'debug' => sqlsrv_errors()]);
    exit;
}

$results = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tgl = ($row['Tgl_Inspeksi'] instanceof DateTime) ? $row['Tgl_Inspeksi']->format('d-m-Y') : $row['Tgl_Inspeksi'];
    // Format tampilan di dropdown: SPC04112600001 (Part123 - 04-11-2026)
    $display_text = $row['SpcCode'] . " (" . $row['PartNo'] . " | " . $tgl . ")";
    
    $results[] = [
        'id' => $row['SpcCode'], // ID yang akan dikirim ke URL
        'text' => $display_text
    ];
}

echo json_encode(['results' => $results]);
exit;
?>
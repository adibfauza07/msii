<?php
// Matikan error/notice HTML yang bisa merusak format JSON secara diam-diam
error_reporting(0);
require_once __DIR__ . '/../config/database_p1.php';
header('Content-Type: application/json; charset=utf-8');

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$data = array();

if ($search !== '') {
    // Tambahkan filter ITEM_INACTIVE agar item yang sudah mati tidak ikut muncul
    $sql = "SELECT TOP 30 ITEM_CODE, ITEM_NAME, ITEM_UNIT 
            FROM ITEMS 
            WHERE (ITEM_CODE LIKE ? OR ITEM_NAME LIKE ?)
            AND ISNULL(ITEM_INACTIVE, 0) = 0
            ORDER BY ITEM_CODE ASC";

    $params = array("%$search%", "%$search%");
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $itemCode = trim((string)$row['ITEM_CODE']);
            $itemName = trim((string)$row['ITEM_NAME']);
            
            // PERBAIKAN: Bersihkan karakter non-UTF8 dari SQL Server 
            // (Ini penyebab utama json_encode sering gagal/blank ke Select2)
            $text = $itemCode . ' - ' . $itemName;
            if (function_exists('mb_convert_encoding')) {
                $text = mb_convert_encoding($text, 'UTF-8', 'auto');
            } else {
                $text = utf8_encode($text);
            }

            $data[] = array(
                'id' => $itemCode, 
                'text' => $text,
                'unit' => trim((string)$row['ITEM_UNIT'])
            );
        }
    }
}

// Return JSON
echo json_encode($data);
?>
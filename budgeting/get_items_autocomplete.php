<?php
if (session_id() == "") { session_start(); }
require_once 'config.php'; // Menggunakan koneksi ganda yang sudah disetup[cite: 1]

if (ob_get_length()) { ob_clean(); }
header('Content-Type: application/json; charset=utf-8');

// Tangkap parameter 'q' yang dikirim oleh Select2 (kata kunci pencarian)
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = array();

// Mencegah query jika input terlalu pendek atau kosong
if (strlen($search) >= 2) {
    // Parameterized query T-SQL untuk SQL Server 2008 (Limit dengan TOP 10)
    $sql = "SELECT TOP 10 ITEM_ID, ITEM_CODE, ITEM_NAME 
            FROM ITEMS 
            WHERE ITEM_CODE LIKE ? OR ITEM_NAME LIKE ? 
            ORDER BY ITEM_NAME ASC";
            
    $param_search = "%" . $search . "%";
    $params = array($param_search, $param_search);

    // Eksekusi query ke database msdata[cite: 1]
    $stmt = sqlsrv_query($conn_msdata, $sql, $params);

    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $results[] = array(
                'id'   => (int)$row['ITEM_ID'],
                'text' => htmlspecialchars($row['ITEM_CODE'] . ' - ' . $row['ITEM_NAME'], ENT_QUOTES, 'UTF-8')
            );
        }
    }
}

// Select2 membutuhkan format JSON spesifik: { "results": [ ... ] }
echo json_encode(array('results' => $results));
exit;
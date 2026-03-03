<?php
// Matikan error visual agar JSON tidak rusak
error_reporting(0);
ini_set('display_errors', 0);

// Pastikan jalur ke database benar
// Folder inventory mundur 1 langkah (..) lalu masuk config
require_once __DIR__ . '/../config/database_p1.php';

header('Content-Type: application/json');

// Cek koneksi
if ($conn === false) {
    // Kirim JSON kosong jika DB gagal, jangan error HTML
    echo json_encode([]);
    exit;
}

$search = isset($_GET['q']) ? $_GET['q'] : '';

// Query Sederhana (Top 20 biar ringan)
$sql = "SELECT TOP 20 TRAN_ID, TRAN_DOC, TRAN_DATE 
        FROM TRANS 
        WHERE TRAN_DOC LIKE ? 
        ORDER BY TRAN_ID DESC";

$params = array("%$search%");
$stmt = sqlsrv_query($conn, $sql, $params);

$data = array();

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Format Tanggal Manual (Biar aman dari error Object)
        $tglDisplay = '';
        if (isset($row['TRAN_DATE']) && $row['TRAN_DATE'] instanceof DateTime) {
            $tglDisplay = $row['TRAN_DATE']->format('d/m/Y');
        }

        $data[] = array(
            'id' => $row['TRAN_ID'],
            'text' => $row['TRAN_DOC'] . ' (' . $tglDisplay . ')'
        );
    }
}

echo json_encode($data);
?>
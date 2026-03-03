<?php
// Simpan file ini dengan nama: api_cari_barang.php
// Mundur satu folder, lalu masuk ke config
require_once __DIR__ . '/../config/database_p1.php';

header('Content-Type: application/json');

// Ambil kata kunci yang diketik user
$search = isset($_GET['q']) ? $_GET['q'] : '';

// Jika kosong, jangan cari apa-apa
if ($search == '') {
    echo json_encode([]);
    exit;
}

// Query mencari Kode ATAU Nama (Top 20 saja biar ringan)
// Tambahkan ITEM_UNIT di sini!
$sql = "SELECT TOP 20 ITEM_CODE, ITEM_NAME, ITEM_UNIT 
        FROM ITEMS 
        WHERE ITEM_CODE LIKE ? OR ITEM_NAME LIKE ?
        ORDER BY ITEM_CODE ASC";

$params = array("%$search%", "%$search%");
$stmt = sqlsrv_query($conn, $sql, $params);

$data = array();
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Format JSON harus 'id' dan 'text' supaya dibaca Select2
        $data[] = array(
            'id' => $row['ITEM_CODE'], 
            'text' => $row['ITEM_CODE'] . ' - ' . $row['ITEM_NAME'], // Tampilan di Dropdown
            'unit' => $row['ITEM_UNIT']
        );
    }
}

echo json_encode($data);
?>
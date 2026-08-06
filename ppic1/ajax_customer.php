<?php
require_once __DIR__ . "/../config/database_ppic.php";

header('Content-Type: application/json');

 $term = isset($_GET['term']) ? $_GET['term'] : '';

// Jika koneksi gagal atau term kosong, kembalikan array kosong
if ($conn === false || empty($term)) {
    echo json_encode([]);
    exit();
}

// Query pencarian CUST_CODE atau CUST_COMP
 $tsql = "SELECT TOP 20 CUST_CODE, CUST_COMP, CUST_ID 
         FROM CUST 
         WHERE CUST_CODE LIKE ? OR CUST_COMP LIKE ?
         ORDER BY CUST_CODE ASC";

// Tambahkan wildcard % untuk pencarian LIKE
 $searchTerm = "%" . $term . "%";
 $params = array($searchTerm, $searchTerm);

 $stmt = sqlsrv_query($conn, $tsql, $params);

if ($stmt === false) {
    echo json_encode([]);
    exit();
}

 $customers = [];

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $customers[] = [
        'label' => $row['CUST_CODE'] . ' - ' . $row['CUST_COMP'], // Teks yang tampil di dropdown
        'value' => $row['CUST_CODE']                              // Teks yang masuk ke input saat dipilih
    ];
}

sqlsrv_free_stmt($stmt);

echo json_encode($customers);
?>
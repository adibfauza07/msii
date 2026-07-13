<?php
// Pastikan path ke file config sesuai dengan struktur folder Anda
require_once __DIR__ . "/../config/database_ppic.php"; 

if (isset($_GET['term']) && $conn) {
    $search = '%' . $_GET['term'] . '%';
    // Ganti nama tabel/kolom jika berbeda
    $tsql = "SELECT TOP 10 CUST_CODE, CUST_COMP FROM CUST 
             WHERE CUST_CODE LIKE ? OR CUST_COMP LIKE ? 
             ORDER BY CUST_CODE ASC";
    
    $params = array($search, $search);
    $stmt = sqlsrv_query($conn, $tsql, $params);
    
    $results = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = array(
            'label' => $row['CUST_CODE'] . ' - ' . $row['CUST_COMP'], // Yang tampil di dropdown
            'value' => $row['CUST_CODE'] // Yang masuk ke input box
        );
    }
    
    header('Content-Type: application/json');
    echo json_encode($results);
}
?>
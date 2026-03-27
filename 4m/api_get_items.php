<?php
// 1. Mulai session dan muat koneksi database
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php'; 

// 2. Tangkap ID Customer dari kiriman AJAX
$custID = isset($_GET['cust_id']) ? $_GET['cust_id'] : '';

echo '<option value="">-- Select Item --</option>';

if ($custID != '') {
    // 3. Query ke View PC_ITEM_CUSTOMER_VIEW yang kamu kirim tadi
    // Kita filter berdasarkan CUST_ID
    $sql = "SELECT ITEM_ID, PART_NO, PART_NAME 
            FROM PC_ITEM_CUSTOMER_VIEW 
            WHERE CUST_ID = ? 
            ORDER BY PART_NAME ASC";
            
    $params = array($custID);
    $stmt = q($sql, $params);

    // 4. Looping hasil query menjadi elemen HTML Option
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $itemID   = $row['ITEM_ID'];
        $partNo   = $row['PART_NO'];
        $partName = $row['PART_NAME'];
        
        // Menampilkan Part No dan Part Name agar user mudah memilih
        echo "<option value='$itemID'>$partNo - $partName</option>";
    }
}
?>
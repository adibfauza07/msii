<?php
session_start();
require_once '../config/database_aging.php';

// Cek apakah parameter ID dikirimkan melalui URL
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Query hapus data berdasarkan id_sales
    $sql = "DELETE FROM TRANS_SALES WHERE id_sales = ?";
    $params = array($id);
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        // Jika gagal, tampilkan pesan error
        die("<h3 style='color:red;'>Gagal menghapus data!</h3><pre>" . print_r(sqlsrv_errors(), true) . "</pre>");
    } else {
        // Jika sukses, kembalikan ke halaman Aging Sales
        header("Location: aging_sales.php");
        exit();
    }
} else {
    // Jika tidak ada ID, langsung kembalikan ke halaman Aging Sales
    header("Location: aging_sales.php");
    exit();
}
?>
<?php
session_start();
require_once '../config/database_aging.php';

if (isset($_POST['save_kategori'])) {
    $kode_kategori = trim($_POST['kode_kategori']);
    $nama_kategori = trim($_POST['nama_kategori']);

    // Cek apakah kode sudah ada agar tidak duplikat
    $cek = sqlsrv_query($conn, "SELECT SalesCode FROM MasterKategoriSales WHERE SalesCode = ?", array($kode_kategori));
    if (sqlsrv_has_rows($cek)) {
        echo "<script>alert('Gagal! Kode Kategori {$kode_kategori} sudah ada di database.'); window.history.back();</script>";
        exit();
    }

    // Query simpan ke tabel MasterKategoriSales
    $sql = "INSERT INTO MasterKategoriSales (SalesCode, SalesName) VALUES (?, ?)";
    $params = array($kode_kategori, $nama_kategori);
    
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        // Jika berhasil, lempar kembali ke halaman Aging Sales
        header("Location: aging_sales.php?msg=kategori_saved");
        exit();
    } else {
        die("<h3 style='color:red;'>Gagal menyimpan Kategori!</h3><pre>" . print_r(sqlsrv_errors(), true) . "</pre>");
    }
}
?>
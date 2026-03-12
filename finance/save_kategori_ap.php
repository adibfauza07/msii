<?php
session_start();
require_once '../config/database_aging.php';

if (isset($_POST['save_kategori_ap'])) {
    $kode_kategori = trim($_POST['kode_kategori']);
    $nama_kategori = trim($_POST['nama_kategori']);

    // Cek apakah kode sudah ada di MasterKategoriAP agar tidak duplikat
    $cek = sqlsrv_query($conn, "SELECT SupplierCode FROM MasterKategoriAP WHERE SupplierCode = ?", array($kode_kategori));
    if (sqlsrv_has_rows($cek)) {
        echo "<script>alert('Gagal! Kode Kategori {$kode_kategori} sudah digunakan.'); window.history.back();</script>";
        exit();
    }

    // Query simpan ke tabel MasterKategoriAP
    $sql = "INSERT INTO MasterKategoriAP (SupplierCode, SupplierName) VALUES (?, ?)";
    $params = array($kode_kategori, $nama_kategori);
    
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        // Jika sukses, lempar balik ke halaman Aging AP
        header("Location: aging_ap.php?msg=kategori_saved");
        exit();
    } else {
        die("<h3 style='color:red;'>Gagal menyimpan Kategori AP!</h3><pre>" . print_r(sqlsrv_errors(), true) . "</pre>");
    }
}
?>
<?php
session_start();
include "koneksi.php";

// Pastikan hanya admin yang bisa akses
if (!isset($_SESSION['ADMIN_LOGIN'])) {
    header("Location: login_admin.php");
    exit;
}

// Pastikan data dikirim dari form
if (isset($_POST['VEND_ID']) && isset($_POST['VEND_PASS'])) {
    $VEND_ID   = $_POST['VEND_ID'];
    $VEND_PASS = md5($_POST['VEND_PASS']); // Enkripsi MD5 seperti vendor login

    // Query update
    $sql = "UPDATE VENDOR SET VEND_PASS = ? WHERE VEND_ID = ?";
    $params = array($VEND_PASS, $VEND_ID);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        echo "<script>alert('✅ Password vendor berhasil diperbarui!'); window.location='list_vendor_master.php';</script>";
    } else {
        echo "<script>alert('❌ Gagal memperbarui password vendor!'); window.location='list_vendor_master.php';</script>";
    }
} else {
    header("Location: list_vendor_master.php");
}
?>

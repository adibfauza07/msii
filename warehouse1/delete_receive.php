<?php
// Pastikan error reporting disesuaikan untuk production
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . "/../config/global.php";

header('Content-Type: application/json; charset=utf-8');

// Proteksi metode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('status' => 'error', 'message' => 'Metode tidak diizinkan.'));
    exit;
}

// Tangkap dan sanitasi input dari Front-End
$qrcode_id = isset($_POST['qrcode_id']) ? trim($_POST['qrcode_id']) : '';
$password  = isset($_POST['password']) ? trim($_POST['password']) : '';

// ==========================================
// 1. VALIDASI PASSWORD (HARDCODED ATAU BISA DARI DB)
// ==========================================
if ($password !== 'q9tj9') {
    echo json_encode(array('status' => 'error', 'message' => 'Akses Ditolak: Password penghapusan salah!'));
    exit;
}

if ($qrcode_id === '') {
    echo json_encode(array('status' => 'error', 'message' => 'ID QR Code tidak valid.'));
    exit;
}

if ($conn === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Koneksi database gagal.'));
    exit;
}

// ==========================================
// 2. EKSEKUSI DELETE DENGAN PARAMETERIZED QUERY (Mencegah SQL Injection)
// ==========================================
$sqlDelete = "DELETE FROM RECEIVE_DETAIL WHERE QRCODE_ID = ?";
$stmtDelete = sqlsrv_query($conn, $sqlDelete, array($qrcode_id));

if ($stmtDelete === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Database error saat menghapus data.'));
    exit;
}

// Cek apakah ada baris yang benar-benar terhapus
$rowsAffected = sqlsrv_rows_affected($stmtDelete);
sqlsrv_free_stmt($stmtDelete);

if ($rowsAffected > 0) {
    echo json_encode(array('status' => 'success', 'message' => 'Data pemasukan berhasil dihapus.'));
} else {
    echo json_encode(array('status' => 'error', 'message' => 'Data tidak ditemukan atau sudah terhapus sebelumnya.'));
}
?>
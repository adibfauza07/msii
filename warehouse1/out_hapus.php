<?php
// Kompatibilitas PHP 5.4 & SQL Server 2008
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once __DIR__ . "/../config/global.php";

header('Content-Type: text/plain; charset=utf-8');

$tranid = isset($_POST['tranid']) ? (int)$_POST['tranid'] : 0;

if ($conn === false || $tranid === 0) {
    echo 'false';
    exit;
}

// Mulai Transaksi Database (ACID Compliance) untuk memastikan penghapusan menyeluruh
sqlsrv_begin_transaction($conn);

try {
    // 1. Hapus data detail terlebih dahulu pada tabel INV_TRAN berdasarkan TRAN_ID
    $sqlDetail = "DELETE FROM INV_TRAN WHERE TRAN_ID = ?";
    $stmtD = sqlsrv_query($conn, $sqlDetail, array($tranid));
    
    if ($stmtD === false) {
        throw new Exception("Gagal menghapus detail transaksi.");
    }
    sqlsrv_free_stmt($stmtD);

    // 2. Setelah detail bersih, hapus header utama pada tabel TRANS berdasarkan TRAN_ID
    $sqlHeader = "DELETE FROM TRANS WHERE TRAN_ID = ?";
    $stmtH = sqlsrv_query($conn, $sqlHeader, array($tranid));
    
    if ($stmtH === false) {
        throw new Exception("Gagal menghapus header transaksi.");
    }
    sqlsrv_free_stmt($stmtH);

    // Commit transaksi jika kedua proses berhasil tanpa error
    sqlsrv_commit($conn);
    echo 'true';

} catch (Exception $e) {
    // Batalkan semua perubahan jika terjadi kegagalan
    sqlsrv_rollback($conn);
    echo 'false';
}
?>
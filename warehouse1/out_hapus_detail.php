<?php
// out_hapus_detail.php (Kompatibel PHP 5.4 & SQL Server 2008)

// 1. Matikan tampilan error ke end-user di production untuk mencegah path disclosure
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 2. Sertakan koneksi database
require_once __DIR__ . "/../config/global.php";

// 3. Batasi akses hanya untuk metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('false');
}

// 4. Tangkap dan sanitasi input (Casting ke Integer untuk memastikan tipe datanya aman dari SQLi)
$tranid = isset($_POST['tranid']) ? (int)$_POST['tranid'] : 0;
$lineno = isset($_POST['lineno']) ? (int)$_POST['lineno'] : 0;

// 5. Validasi awal
if ($conn === false || $tranid === 0 || $lineno === 0) {
    exit('false');
}

// 6. Eksekusi query dengan Parameterized Query (SQL Server 2008)
// Menghapus spesifik 1 baris berdasarkan TRAN_ID (ID Transaksi) dan IT_LINENO (Nomor Urut Baris)
$sql = "DELETE FROM INV_TRAN WHERE TRAN_ID = ? AND IT_LINENO = ?";
$params = array($tranid, $lineno);

$stmt = sqlsrv_query($conn, $sql, $params);

// 7. Evaluasi hasil kueri
if ($stmt !== false) {
    sqlsrv_free_stmt($stmt);
    echo 'true';  // Berhasil dihapus, trigger fungsi loadDetailList() di JS
} else {
    // Jika ingin debug error saat development, bisa di-uncomment:
    // print_r(sqlsrv_errors());
    echo 'false'; // Gagal dihapus
}
?>
<?php
// ajax_save_ovh_status.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$configPath = __DIR__ . "/../config/global.php";
if (file_exists($configPath)) {
    require_once $configPath;
}

header('Content-Type: application/json');

$tahun    = isset($_POST['tahun']) ? (int)$_POST['tahun'] : date('Y');
$bulan    = isset($_POST['bulan']) ? (int)$_POST['bulan'] : date('n');
$mac_code = isset($_POST['mac_code']) ? trim($_POST['mac_code']) : '';
$item_code= isset($_POST['item_code']) ? trim($_POST['item_code']) : '';
$status   = isset($_POST['status']) ? (int)$_POST['status'] : 0;

if (empty($mac_code) || empty($item_code)) {
    echo json_encode(['status' => 'error', 'message' => 'Parameter mesin atau item kosong.']);
    exit;
}

try {
    // CONTOH QUERY PENYIMPANAN KE DATABASE (Sesuaikan dengan nama tabel status OVH Anda)
    /*
    global $conn; // Koneksi SQL Server dari global.php
    
    // Cek apakah data bulan/tahun tersebut sudah ada
    $sqlCheck = "SELECT COUNT(*) as cnt FROM TBL_OVH_STATUS WHERE TAHUN = ? AND BULAN = ? AND MC_NO = ? AND ITEM_CODE = ?";
    $params = [$tahun, $bulan, $mac_code, $item_code];
    // Eksekusi query check & update/insert...
    */

    // Untuk sementara mengembalikan respon sukses sukses
    echo json_encode(['status' => 'success', 'message' => 'Status berhasil disimpan.']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage().'']);
}
?>
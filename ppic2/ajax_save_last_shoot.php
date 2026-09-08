<?php
// ajax_save_last_shoot.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

// --- LOAD CONFIG & KONEKSI ---
$configPath = __DIR__ . "/../config/global.php";
if (file_exists($configPath)) { require_once $configPath; }

if (!isset($conn) || $conn === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Koneksi database gagal.'));
    exit;
}

// --- TERIMA & SANITASI DATA POST ---
$tahun     = isset($_POST['tahun']) ? (int)$_POST['tahun'] : 0;
$bulan     = isset($_POST['bulan']) ? (int)$_POST['bulan'] : 0;
$macCode   = isset($_POST['mac_code']) ? trim($_POST['mac_code']) : '';
$itemCode  = isset($_POST['item_code']) ? trim($_POST['item_code']) : '';
$lastShoot = isset($_POST['last_shoot']) ? (float)$_POST['last_shoot'] : 0;

if ($tahun == 0 || $bulan == 0 || $macCode === '' || $itemCode === '') {
    echo json_encode(array('status' => 'error', 'message' => 'Parameter tidak lengkap untuk menyimpan data.'));
    exit;
}

// --- KALKULASI PERIODE BULAN SEBELUMNYA ---
// Karena kolom Last Shoot merupakan hasil dari bulan sebelumnya
$prevBulan = $bulan - 1;
$prevTahun = $tahun;
if ($prevBulan == 0) {
    $prevBulan = 12;
    $prevTahun = $tahun - 1;
}

try {
    // --- LOGIKA UPSERT (UPDATE OR INSERT) UNTUK SQL SERVER 2008 ---
    $sql = "
        UPDATE MATRIX_LAST_SHOOT 
        SET LAST_SHOOT = ? 
        WHERE TAHUN = ? AND BULAN = ? AND MAC_CODE = ? AND ITEM_CODE = ?;
        
        IF @@ROWCOUNT = 0
        BEGIN
            INSERT INTO MATRIX_LAST_SHOOT (TAHUN, BULAN, MAC_CODE, ITEM_CODE, LAST_SHOOT)
            VALUES (?, ?, ?, ?, ?);
        END
    ";
    
    // Parameter diulang dua kali: 5 untuk UPDATE, 5 untuk INSERT
    $params = array(
        $lastShoot, $prevTahun, $prevBulan, $macCode, $itemCode,
        $prevTahun, $prevBulan, $macCode, $itemCode, $lastShoot
    );
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        throw new Exception(print_r(sqlsrv_errors(), true));
    }
    
    echo json_encode(array('status' => 'success', 'message' => 'Last Shoot berhasil diperbarui.'));

} catch (Exception $e) {
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
?>
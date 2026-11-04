<?php
// ajax_save_ovh_date_status.php
// Kompatibilitas: PHP 5.4 & SQL Server 2008
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
$hari     = isset($_POST['hari']) ? (int)$_POST['hari'] : 0;
$mac_code = isset($_POST['mac_code']) ? trim($_POST['mac_code']) : '';
$item_code= isset($_POST['item_code']) ? trim($_POST['item_code']) : '';
$status   = isset($_POST['status']) ? (int)$_POST['status'] : 0;
$jenis    = isset($_POST['jenis']) ? trim($_POST['jenis']) : ''; // Menangkap parameter 'plan' atau 'act'

// Validasi tambahan untuk memastikan jenis tidak kosong
if (empty($mac_code) || empty($item_code) || $hari <= 0 || empty($jenis)) {
    echo json_encode(array('status' => 'error', 'message' => 'Parameter tidak lengkap. (Data mesin, item, hari, atau jenis kosong)'));
    exit;
}

if (!isset($conn) || $conn === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Koneksi database tidak tersedia.'));
    exit;
}

try {
    if ($status == 1) {
        // Cek apakah data sudah ada sebelumnya agar tidak duplikat (Filter ditambah parameter JENIS)
        $sqlCheck = "SELECT COUNT(*) AS CNT FROM TBL_OVH_CHECKED_DATES WHERE TAHUN = ? AND BULAN = ? AND HARI = ? AND MC_NO = ? AND ITEM_CODE = ? AND JENIS = ?";
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($tahun, $bulan, $hari, $mac_code, $item_code, $jenis));
        
        if ($stmtCheck === false) {
             throw new Exception(print_r(sqlsrv_errors(), true));
        }
        
        $rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);

        if ($rowCheck['CNT'] == 0) {
            // INSERT Data dengan menyertakan JENIS
            $sqlIns = "INSERT INTO TBL_OVH_CHECKED_DATES (TAHUN, BULAN, HARI, MC_NO, ITEM_CODE, JENIS) VALUES (?, ?, ?, ?, ?, ?)";
            $stmtIns = sqlsrv_query($conn, $sqlIns, array($tahun, $bulan, $hari, $mac_code, $item_code, $jenis));
            if ($stmtIns === false) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }
        }
    } else {
        // Jika centang dilepas, hapus data dari database (Filter ditambah parameter JENIS)
        $sqlDel = "DELETE FROM TBL_OVH_CHECKED_DATES WHERE TAHUN = ? AND BULAN = ? AND HARI = ? AND MC_NO = ? AND ITEM_CODE = ? AND JENIS = ?";
        $stmtDel = sqlsrv_query($conn, $sqlDel, array($tahun, $bulan, $hari, $mac_code, $item_code, $jenis));
        if ($stmtDel === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }
    }

    echo json_encode(array('status' => 'success', 'message' => 'Status tanggal ' . strtoupper($jenis) . ' berhasil disimpan.'));
} catch (Exception $e) {
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
?>
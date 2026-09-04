<?php
// 1. Matikan error reporting ke layar agar JSON tidak rusak
error_reporting(0);
while (ob_get_level()) { ob_end_clean(); }

// 2. Panggil session jika belum aktif
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$databaseName = "msData";

// Deteksi server berdasarkan session atau default Plant 2 (192.168.0.9)
$serverName = isset($_SESSION['active_server']) ? $_SESSION['active_server'] : "192.168.0.9";
$uid = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : "";
$pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "";

// Fallback koneksi jika session user kosong saat dipanggil via AJAX
$connectionOptions = array(
    "Database" => $databaseName,
    "CharacterSet" => "UTF-8"
);

// Jika ada kredensial session, masukkan ke opsi
if ($uid !== "") {
    $connectionOptions["Uid"] = $uid;
    $connectionOptions["PWD"] = $pwd;
}

// Lakukan koneksi langsung tanpa redirect header location
$conn = sqlsrv_connect($serverName, $connectionOptions);

$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$term = isset($_GET['term']) ? trim($_GET['term']) : '';

$results = array();

// Tambahkan 'item' ke dalam in_array
if ($term !== '' && in_array($type, array('mag', 'mac', 'item')) && $conn) {
    
    $searchTerm = '%' . $term . '%';
    $params = array();

    if ($type === 'item') {
        // Query untuk Item Code
        $sql = "SELECT TOP 20 ITEM_CODE, ITEM_NAME 
                FROM dbo.ITEMS 
                WHERE ITTY_CODE = '01' 
                AND (ITEM_CODE LIKE ? OR ITEM_NAME LIKE ?) 
                ORDER BY ITEM_CODE ASC";
        $params = array($searchTerm, $searchTerm); // Membutuhkan 2 parameter untuk OR
    } 
    else if ($type === 'mag') {
        // Query untuk Mach Group
        $sql = "SELECT TOP 20 MAG_STATION AS label, MAG_STATION AS value 
                FROM dbo.MAG 
                WHERE MAG_STATION LIKE ? 
                ORDER BY MAG_STATION ASC";
        $params = array($searchTerm);
    } 
    else {
        // Query untuk Machine Code (mac)
        $sql = "SELECT TOP 20 MAC_CODE AS label, MAC_CODE AS value 
                FROM dbo.MAC 
                WHERE MAC_CODE LIKE ? 
                ORDER BY MAC_CODE ASC";
        $params = array($searchTerm);
    }

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Pemisahan format output JSON khusus untuk 'item'
            if ($type === 'item') {
                $results[] = array(
                    'label' => trim($row['ITEM_CODE']) . ' - ' . trim($row['ITEM_NAME']),
                    'value' => trim($row['ITEM_CODE']),
                    'desc'  => trim($row['ITEM_NAME'])
                );
            } else {
                $results[] = array(
                    'label' => trim($row['label']),
                    'value' => trim($row['value'])
                );
            }
        }
        
        if (count($results) === 0) {
            $results[] = array('label' => 'Data tidak ditemukan', 'value' => '');
        }
    } else {
        if (($errors = sqlsrv_errors()) != null) {
            $results[] = array('label' => 'DB ERROR: ' . $errors[0]['message'], 'value' => '');
        }
    }
} else {
    if (!$conn) {
        $results[] = array('label' => 'Koneksi SQL Server Gagal ke IP: ' . $serverName, 'value' => '');
    }
}

// Kirim murni sebagai JSON
header('Content-Type: application/json; charset=utf-8');
echo json_encode($results);
exit;
?>
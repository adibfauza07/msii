<?php
// api_cari_barang.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
session_write_close(); 
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

// Panggil konfigurasi database langsung tanpa fallback yang rumit
require_once __DIR__ . '/config/database.php';

// Jika variabel $conn dari database.php belum ada, buat koneksi eksplisit berdasarkan session
if (!isset($conn) || $conn === false) {
    $databaseName = "msData";
    $serverName = isset($_SESSION['active_server']) ? $_SESSION['active_server'] : "192.168.0.9";
    $uid = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : "";
    $pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "";

    $connectionOptions = array("Database" => $databaseName, "CharacterSet" => "UTF-8");
    if ($uid !== "") { 
        $connectionOptions["Uid"] = $uid; 
        $connectionOptions["PWD"] = $pwd; 
    }
    $conn = @sqlsrv_connect($serverName, $connectionOptions);
}

if (!$conn) {
    echo json_encode(array());
    exit;
}

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

if (strlen($term) < 1) {
    echo json_encode(array());
    exit;
}

try {
    $searchTerm = '%' . $term . '%';

    // Query pencarian ke tabel ITEMS untuk Master Barang
    $sql = "SELECT TOP 20 ITEM_CODE, ITEM_NAME, ITEM_UNIT 
            FROM dbo.ITEMS 
            WHERE ITEM_CODE LIKE ? OR ITEM_NAME LIKE ?
            ORDER BY ITEM_CODE ASC";

    $params = array($searchTerm, $searchTerm);
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) { 
        throw new Exception("DB Error"); 
    }

    $results = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = array(
            'id'    => trim($row['ITEM_CODE']),
            'value' => trim($row['ITEM_CODE']),
            'label' => trim($row['ITEM_CODE']) . ' - ' . trim($row['ITEM_NAME']),
            'name'  => trim($row['ITEM_NAME']),
            'unit'  => trim($row['ITEM_UNIT'])
        );
    }
    sqlsrv_free_stmt($stmt);
    
    echo json_encode($results);

} catch (Exception $e) {
    echo json_encode(array());
}
?>
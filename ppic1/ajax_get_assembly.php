<?php
require_once __DIR__ . "/../config/global.php"; 

// Tambahkan session_start() sebelum mengakses $_SESSION
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$injItemCode = isset($_GET['item_code']) ? trim($_GET['item_code']) : '';

if ($injItemCode === '') {
    echo json_encode(['status' => 'error', 'message' => 'Item code kosong']);
    exit;
}

// Sesuaikan koneksi dengan file config Anda
$serverName = isset($_SESSION['active_server']) ? $_SESSION['active_server'] : "192.168.0.9";
$connectionOptions = array(
    "Database" => "msData", 
    "Uid" => isset($_SESSION['db_user']) ? $_SESSION['db_user'] : "", 
    "PWD" => isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "", 
    "CharacterSet" => "UTF-8"
);
$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Failed']);
    exit;
}

// Eksekusi query BOM menggunakan Parameterized Query
$sql = "SELECT ITEMS.ITEM_CODE AS assembly_part
        FROM BOM_DEFAULT 
        INNER JOIN ITEMS ON BOM_DEFAULT.PART_ID = ITEMS.ITEM_ID 
        INNER JOIN ITEMS AS ITEMS_1 ON BOM_DEFAULT.ITEM_ID = ITEMS_1.ITEM_ID
        WHERE ITEMS_1.ITEM_CODE = ? AND ITEMS_1.ITTY_CODE = '01'";

$params = array($injItemCode);
$stmt = sqlsrv_query($conn, $sql, $params);

$arrAssembly = array();
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $arrAssembly[] = trim($row['assembly_part']);
    }
}

sqlsrv_close($conn);

echo json_encode([
    'status' => 'success',
    'data' => $arrAssembly
]);
?>
<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

error_reporting(0);
ini_set('display_errors', 0);
ob_start();

$configPath = __DIR__ . "/../config/global.php"; 
if (file_exists($configPath)) {
    require_once $configPath;
}

ob_clean();
header('Content-Type: application/json; charset=utf-8');

// Validasi Parameter
$assemblyCode = isset($_GET['assembly_code']) ? trim($_GET['assembly_code']) : '';
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : 0;

if ($assemblyCode === '' || $tahun === 0 || $bulan === 0) {
    echo json_encode(array('status' => 'error', 'message' => 'Parameter tidak lengkap'));
    exit;
}

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
if (!$conn) {
    echo json_encode(array('status' => 'error', 'message' => 'Koneksi database gagal.'));
    exit;
}

// Format periode (YYYYMM)
$periode = sprintf('%04d%02d', $tahun, $bulan);

// Siapkan array kosong default untuk 31 hari
$planData = array();
$actualData = array();
for ($i = 1; $i <= 31; $i++) {
    $planData["D$i"] = 0;
    $actualData["D$i"] = 0;
}

// Ambil Prod Plan R0 dan Prod OK (Actual) dari Assembly Part
$sql = "SELECT D.* 
        FROM RPT_PPIC H
        INNER JOIN RPT_PPIC_DTL D ON H.ID_NO = D.ID_NO
        WHERE H.ITEM_CODE = ? 
          AND H.periode = ?
          AND D.DESC_PROD IN ('Prod Plan R0', 'Prod OK', 'Prod Actual')";

$params = array($assemblyCode, $periode);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Query gagal dieksekusi.'));
    exit;
}

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $desc = trim($row['DESC_PROD']);
    
    for ($i = 1; $i <= 31; $i++) {
        $col = 'D' . $i;
        $val = ($row[$col] !== null && $row[$col] !== '') ? (float)$row[$col] : 0;
        
        // Mapping logika bisnis baru
        if ($desc === 'Prod Plan R0') {
            $planData[$col] = $val;
        } else if ($desc === 'Prod OK' || $desc === 'Prod Actual') {
            // Mengakomodasi penamaan Prod OK atau Prod Actual di database
            $actualData[$col] = $val;
        }
    }
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

echo json_encode(array(
    'status' => 'success',
    'data' => array(
        'plan' => $planData,
        'actual' => $actualData
    )
));
exit;
?>
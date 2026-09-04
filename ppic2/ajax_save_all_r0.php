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

// Koneksi SQL Server
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

// PERBAIKAN: Tangkap Payload r0 sesuai dengan AJAX data: { id_no: idno, r0: r0Data }
$id_no   = isset($_POST['id_no']) ? (int)$_POST['id_no'] : 0;
$daysR0  = isset($_POST['r0']) ? $_POST['r0'] : array(); 
$daysDP  = isset($_POST['days_dp']) ? $_POST['days_dp'] : array(); // Sesuai antisipasi pull delivery nantinya
$daysDA  = isset($_POST['days_da']) ? $_POST['days_da'] : array();

if ($id_no === 0) {
    echo json_encode(array('status' => 'error', 'message' => 'ID_NO tidak valid.'));
    exit;
}

/**
 * Fungsi reusable untuk melakukan Update Matrix pada tabel RPT_PPIC_DTL
 */
function updateGridRow($conn, $id_no, $descProd, $daysData) {
    if (empty($daysData)) return false;

    $sql = "UPDATE RPT_PPIC_DTL 
            SET D1=?, D2=?, D3=?, D4=?, D5=?, D6=?, D7=?, D8=?, D9=?, D10=?,
                D11=?, D12=?, D13=?, D14=?, D15=?, D16=?, D17=?, D18=?, D19=?, D20=?,
                D21=?, D22=?, D23=?, D24=?, D25=?, D26=?, D27=?, D28=?, D29=?, D30=?, D31=?,
                G_TOTAL=? 
            WHERE ID_NO = ? AND DESC_PROD = ?";

    $params = array();
    $gTotal = 0;
    
    // Mapping D1 sampai D31
    for ($i = 1; $i <= 31; $i++) {
        $val = isset($daysData["d$i"]) ? (float)$daysData["d$i"] : 0;
        $params[] = $val;
        $gTotal += $val;
    }
    
    $params[] = $gTotal;   // Parameter G_TOTAL
    $params[] = $id_no;    // Parameter ID_NO
    $params[] = $descProd; // Parameter Tipe Baris

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt) {
        sqlsrv_free_stmt($stmt);
        return true;
    }
    return false;
}

// 1. Eksekusi Save untuk input R0
$isSavedR0 = updateGridRow($conn, $id_no, 'Prod Plan R0', $daysR0);

// 2. Eksekusi Save untuk data Delivery yang di-pull dari Assembly
if (!empty($daysDP)) {
    updateGridRow($conn, $id_no, 'Del Plan', $daysDP);
}
if (!empty($daysDA)) {
    updateGridRow($conn, $id_no, 'Del Actual', $daysDA);
}

sqlsrv_close($conn);

// Tambahkan validasi jika ternyata query update gagal
if (!$isSavedR0 && !empty($daysR0)) {
    echo json_encode(array('status' => 'error', 'message' => 'Gagal mengupdate R0 di database.'));
    exit;
}

echo json_encode(array(
    'status' => 'success', 
    'message' => 'Data jadwal dan Delivery Assembly berhasil diamankan di grid.',
    'prod_sch' => 'Save OK'
));
exit;
?>
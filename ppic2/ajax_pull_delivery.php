<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
error_reporting(0); ini_set('display_errors', 0); ob_start();

$configPath = __DIR__ . "/../config/global.php"; 
if (file_exists($configPath)) { require_once $configPath; }

ob_clean(); header('Content-Type: application/json; charset=utf-8');

// Validasi Parameter (Sekarang menggunakan POST)
$assemblyCode = isset($_POST['assembly_code']) ? trim($_POST['assembly_code']) : '';
$tahun = isset($_POST['tahun']) ? (int)$_POST['tahun'] : 0;
$bulan = isset($_POST['bulan']) ? (int)$_POST['bulan'] : 0;
$id_no = isset($_POST['id_no']) ? (int)$_POST['id_no'] : 0;

if ($assemblyCode === '' || $tahun === 0 || $bulan === 0 || $id_no === 0) {
    echo json_encode(array('status' => 'error', 'message' => 'Parameter tidak lengkap atau ID tidak valid.'));
    exit;
}

$databaseName = "msData";
$serverName = isset($_SESSION['active_server']) ? $_SESSION['active_server'] : "192.168.0.9";
$uid = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : "";
$pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "";
$connectionOptions = array("Database" => $databaseName, "CharacterSet" => "UTF-8");
if ($uid !== "") { $connectionOptions["Uid"] = $uid; $connectionOptions["PWD"] = $pwd; }

$conn = @sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) {
    echo json_encode(array('status' => 'error', 'message' => 'Koneksi database gagal.'));
    exit;
}

$periode = sprintf('%04d%02d', $tahun, $bulan);

// Siapkan array data
$planData = array(); $actualData = array();
for ($i = 1; $i <= 31; $i++) { $planData["D$i"] = 0; $actualData["D$i"] = 0; }

// 1. BACA JADWAL DARI ASSEMBLY PART
$sql = "SELECT D.* FROM RPT_PPIC H 
        INNER JOIN RPT_PPIC_DTL D ON H.ID_NO = D.ID_NO 
        WHERE H.ITEM_CODE = ? AND H.periode = ? 
        AND D.DESC_PROD IN ('Prod Plan R0', 'Prod OK', 'Prod Actual')";
$stmt = sqlsrv_query($conn, $sql, array($assemblyCode, $periode));

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $desc = trim($row['DESC_PROD']);
        for ($i = 1; $i <= 31; $i++) {
            $col = 'D' . $i;
            $val = ($row[$col] !== null && $row[$col] !== '') ? (float)$row[$col] : 0;
            if ($desc === 'Prod Plan R0') { $planData[$col] = $val; } 
            else if ($desc === 'Prod OK' || $desc === 'Prod Actual') { $actualData[$col] = $val; }
        }
    }
    sqlsrv_free_stmt($stmt);
}

// 2. SIMPAN HASIL TARIKAN LANGSUNG KE DATABASE ITEM KOMPONEN SAAT INI
$sqlUpd = "UPDATE RPT_PPIC_DTL SET 
            D1=?, D2=?, D3=?, D4=?, D5=?, D6=?, D7=?, D8=?, D9=?, D10=?,
            D11=?, D12=?, D13=?, D14=?, D15=?, D16=?, D17=?, D18=?, D19=?, D20=?,
            D21=?, D22=?, D23=?, D24=?, D25=?, D26=?, D27=?, D28=?, D29=?, D30=?, D31=?,
            G_TOTAL=? WHERE ID_NO=? AND DESC_PROD=?";

// Eksekusi Update untuk 'Del Plan'
$paramsPlan = []; $gTotalPlan = 0;
for ($i=1; $i<=31; $i++) { $paramsPlan[] = $planData["D$i"]; $gTotalPlan += $planData["D$i"]; }
$paramsPlan[] = $gTotalPlan; $paramsPlan[] = $id_no; $paramsPlan[] = 'Del Plan';
sqlsrv_query($conn, $sqlUpd, $paramsPlan);

// Eksekusi Update untuk 'Del Actual'
$paramsAct = []; $gTotalAct = 0;
for ($i=1; $i<=31; $i++) { $paramsAct[] = $actualData["D$i"]; $gTotalAct += $actualData["D$i"]; }
$paramsAct[] = $gTotalAct; $paramsAct[] = $id_no; $paramsAct[] = 'Del Actual';
sqlsrv_query($conn, $sqlUpd, $paramsAct);

sqlsrv_close($conn);

// 3. KEMBALIKAN RESPON JSON UNTUK DITAMPILKAN DI LAYAR
echo json_encode(array(
    'status' => 'success',
    'data' => array('plan' => $planData, 'actual' => $actualData)
));
exit;
?>
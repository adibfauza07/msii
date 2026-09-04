<?php
// ajax_autocomplete_item_sch.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
session_write_close(); 
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . "/../config/global.php"; 

// 1. KONEKSI DATABASE EKSPLISIT (Agar stabil membaca session)
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
    die(json_encode(array()));
}

$term  = isset($_GET['term']) ? trim($_GET['term']) : '';
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : 0;

if (strlen($term) < 2 || $tahun === 0 || $bulan === 0) {
    echo json_encode(array());
    exit;
}

try {
    // Format periode disamakan dengan tabel RPT_PPIC (Contoh: 202609)
    $periode = sprintf('%04d%02d', $tahun, $bulan);
    $searchTerm = '%' . $term . '%';

    // 2. PERBAIKAN QUERY: Arahkan ke tabel RPT_PPIC (Jadwal Aktif)
    $sql = "SELECT DISTINCT TOP 20
                   P.ITEM_CODE, 
                   P.PART_NAME AS ITEM_NAME,
                   P.MC_NO AS MAC_CODE,
                   ISNULL(G.MAG_STATION, 'N/A') AS MAG_STATION
            FROM dbo.RPT_PPIC P
            LEFT JOIN dbo.MAC M ON LTRIM(RTRIM(P.MC_NO)) = LTRIM(RTRIM(M.MAC_CODE))
            LEFT JOIN dbo.MAG G ON M.MAG_ID = G.MAG_ID
            WHERE P.periode = ?
              AND (P.ITEM_CODE LIKE ? OR P.PART_NAME LIKE ?)";

    $params = array($periode, $searchTerm, $searchTerm);
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) { 
        throw new Exception("DB Error"); 
    }

    $results = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = array(
            'id'          => trim($row['ITEM_CODE']),
            'value'       => trim($row['ITEM_CODE']),
            'label'       => trim($row['ITEM_CODE']) . ' - ' . trim($row['ITEM_NAME']),
            'name'        => trim($row['ITEM_NAME']),
            'mac_code'    => trim($row['MAC_CODE']),
            'mag_station' => trim($row['MAG_STATION'])
        );
    }
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    
    echo json_encode($results);

} catch (Exception $e) {
    echo json_encode(array());
}
?>
<?php
/**
 * API untuk mengambil list WO + Process + MAC + ITEMS 
 * + STANDARDS (Cavity, CycTm, Tonase dari tabel ITEM_PROD)
 * File: get_wo_list.php
 */
ob_start();
require_once __DIR__ . "/../config/global.php";

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$db = null;
if (isset($conn)) $db = $conn;
elseif (isset($connection)) $db = $connection;
elseif (isset($dbconn)) $db = $dbconn;

if (!$db) {
    echo json_encode(array('error' => 'Koneksi database gagal.'));
    exit;
}

$item_code = isset($_GET['item_code']) ? trim((string)$_GET['item_code']) : '';

if ($item_code === '') {
    echo json_encode(array());
    exit;
}

function ac_utf8($value) {
    $text = trim((string) $value);
    if ($text === '') return '';
    if (@preg_match('//u', $text)) return $text;
    if (function_exists('iconv')) {
        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $text);
        if ($converted !== false) return $converted;
    }
    return $text;
}

$like = '%' . $item_code . '%';

// QUERY BARU: Menarik ITEM_CAVT dan ITEM_CYTM dari ITEM_PROD, 
// serta MAG_STATION (Tonase Standar) dari MAG
$sql = "
    SELECT TOP 50 
        WO.WO_ID, 
        WO.WO_NUMBER, 
        WO.WO_START, 
        WO.WO_END, 
        WO.WO_QTY, 
        WO.WO_CAP, 
        WO.PROC_ID,
        ITEMS.ITEM_ID, 
        ITEMS.ITEM_CODE, 
        ITEMS.ITEM_NAME, 
        ITEMS.ITEM_NO, 
        MAC.MAC_CODE, 
        MAC.MAC_SERIAL, 
        PROCESS.PROC_NAME,
        ISNULL(IP.ITEM_CAVT, 0) AS STD_CAV,
        ISNULL(IP.ITEM_CYTM, 0) AS STD_CYCTM,
        ISNULL(STD_MAG.MAG_STATION, '') AS STD_TONASE,
        ISNULL(P.PROD_COUNT, 0) AS PROD_COUNT
    FROM dbo.WO 
    INNER JOIN dbo.PROCESS ON WO.PROC_ID = PROCESS.PROC_ID 
    INNER JOIN dbo.MAC ON WO.MAC_ID = MAC.MAC_ID
    INNER JOIN dbo.ITEMS ON WO.ITEM_ID = ITEMS.ITEM_ID
    LEFT JOIN dbo.ITEM_PROD IP ON ITEMS.ITEM_ID = IP.ITEM_ID
    LEFT JOIN dbo.MAG STD_MAG ON IP.MAG_ID = STD_MAG.MAG_ID
    LEFT JOIN (
        SELECT WO_ID, COUNT(PD_ID) AS PROD_COUNT
        FROM dbo.PRODUCTION
        GROUP BY WO_ID
    ) P ON WO.WO_ID = P.WO_ID
    WHERE ITEMS.ITEM_CODE LIKE ? 
       OR ITEMS.ITEM_NO LIKE ? 
       OR ITEMS.ITEM_NAME LIKE ? 
       OR WO.WO_NUMBER LIKE ?
    ORDER BY 
        WO.WO_START DESC, 
        WO.WO_ID DESC
";

$params = array($like, $like, $like, $like);
$stmt = sqlsrv_query($db, $sql, $params);

$data = array();
if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        
        $woStart = '';
        if (isset($row['WO_START']) && $row['WO_START'] instanceof DateTime) {
            $woStart = $row['WO_START']->format('Y-m-d');
        } elseif (is_string($row['WO_START'])) {
            $woStart = substr($row['WO_START'], 0, 10);
        }

        $woEnd = '';
        if (isset($row['WO_END']) && $row['WO_END'] instanceof DateTime) {
            $woEnd = $row['WO_END']->format('Y-m-d');
        } elseif (is_string($row['WO_END'])) {
            $woEnd = substr($row['WO_END'], 0, 10);
        }

        $data[] = array(
            'WO_ID'       => ac_utf8($row['WO_ID']),
            'WO_NUMBER'   => ac_utf8($row['WO_NUMBER']),
            'WO_QTY'      => ac_utf8($row['WO_QTY']),
            'WO_CAP'      => ac_utf8($row['WO_CAP']),
            'WO_START'    => ac_utf8($woStart),
            'WO_END'      => ac_utf8($woEnd),
            'PROC_ID'     => ac_utf8($row['PROC_ID']),
            'PROC_NAME'   => ac_utf8($row['PROC_NAME']),
            'ITEM_CODE'   => ac_utf8($row['ITEM_CODE']),
            'ITEM_NO'     => ac_utf8($row['ITEM_NO']),
            'ITEM_NAME'   => ac_utf8($row['ITEM_NAME']),
            'MAC_CODE'    => ac_utf8($row['MAC_CODE']),
            'STD_CAV'     => (float)$row['STD_CAV'],      // Pastikan angka terkirim dengan benar
            'STD_CYCTM'   => (float)$row['STD_CYCTM'],    // Pastikan angka terkirim dengan benar
            'TONASE'      => ac_utf8($row['STD_TONASE']),
            'PROD_COUNT'  => (int)$row['PROD_COUNT']
        );
    }
    sqlsrv_free_stmt($stmt);
}

echo json_encode($data);
exit;
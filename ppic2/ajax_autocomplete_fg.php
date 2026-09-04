<?php
// ajax_autocomplete_fg.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
session_write_close(); 
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . "/../config/global.php"; 

if (!isset($conn) || $conn === false) {
    die(json_encode(array()));
}

$term    = isset($_GET['term']) ? trim($_GET['term']) : '';
$tahun   = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
$bulan   = isset($_GET['bulan']) ? (int)$_GET['bulan'] : 0;

if (strlen($term) < 2 || $tahun === 0 || $bulan === 0) {
    echo json_encode(array());
    exit;
}

try {
    $woDateStr = sprintf('%04d-%02d-01 00:00:00', $tahun, $bulan);
    $searchTerm = '%' . $term . '%';

    // Query untuk menarik FG sekaligus mengagregasi Material Code dari BOM
    // Menggunakan T-SQL 2008 standard (STUFF + FOR XML PATH)
    $sql = "SELECT DISTINCT TOP 20
                   I.ITEM_CODE AS FG_CODE, 
                   I.ITEM_NAME AS FG_NAME,
                   I.ITEM_ID,
                   (
                       SELECT STUFF((
                           SELECT ', ' + LTRIM(RTRIM(M.ITEM_CODE))
                           FROM dbo.BOM_DEFAULT B
                           INNER JOIN dbo.ITEMS M ON B.ITEM_ID = M.ITEM_ID
                           WHERE B.PART_ID = I.ITEM_ID
                           FOR XML PATH('')
                       ), 1, 2, '')
                   ) AS REQ_MATERIALS
            FROM dbo.WO W
            INNER JOIN dbo.ITEMS I ON W.ITEM_ID = I.ITEM_ID 
            WHERE W.WO_MMYY = CONVERT(DATETIME, ?, 120)
              AND (I.ITEM_CODE LIKE ? OR I.ITEM_NAME LIKE ?)";

    $params = array($woDateStr, $searchTerm, $searchTerm);
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) { 
        throw new Exception("DB Error"); 
    }

    $results = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Ambil string material, beri fallback jika BOM belum di-setting
        $reqMat = !empty($row['REQ_MATERIALS']) ? trim($row['REQ_MATERIALS']) : 'BOM belum di-setting';
        
        $results[] = array(
            'id'       => trim($row['FG_CODE']),
            'value'    => trim($row['FG_CODE']),
            'label'    => trim($row['FG_CODE']) . ' - ' . trim($row['FG_NAME']),
            'name'     => trim($row['FG_NAME']),
            'req_mat'  => $reqMat // Menyisipkan data material ke JSON response
        );
    }
    sqlsrv_free_stmt($stmt);
    echo json_encode($results);

} catch (Exception $e) {
    echo json_encode(array());
}
?>
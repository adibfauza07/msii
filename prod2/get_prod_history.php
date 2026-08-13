<?php
/**
 * API get_prod_history.php
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
    echo json_encode(array());
    exit;
}

$wo_id = isset($_GET['wo_id']) ? (int)$_GET['wo_id'] : 0;

if ($wo_id === 0) {
    echo json_encode(array());
    exit;
}

// Menambahkan field Actual Data: PD_WKH, PD_SETUPHOUR, PD_CAV, PD_WEIGHT_S, PD_RUN_S, PD_CYTM, PD_LOST_REASON
$sql = "
    SELECT PD_ID, PD_LOT, PD_OK, PD_HO, PD_NG, PD_REM, PD_OPR, PD_PIC_LINE, PD_INPUT,
           ISNULL(PD_LOSTHOUR, 0) AS PD_LOSTHOUR, 
           ISNULL(PD_SC, 0) AS PD_SC,
           ISNULL(PD_WKH, 0) AS PD_WKH,
           ISNULL(PD_SETUPHOUR, 0) AS PD_SETUPHOUR,
           ISNULL(PD_CAV, 0) AS PD_CAV,
           ISNULL(PD_WEIGHT_S, 0) AS PD_WEIGHT_S,
           ISNULL(PD_RUN_S, 0) AS PD_RUN_S,
           ISNULL(PD_CYTM, 0) AS PD_CYTM,
           ISNULL(PD_LOST_REASON, '') AS PD_LOST_REASON
    FROM dbo.PRODUCTION
    WHERE WO_ID = ?
    ORDER BY PD_ID DESC
";

$stmt = sqlsrv_query($db, $sql, array($wo_id));

$data = array();
if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $pdInput = '';
        if (isset($row['PD_INPUT']) && $row['PD_INPUT'] instanceof DateTime) {
            $pdInput = $row['PD_INPUT']->format('d-M-Y');
        } elseif (is_string($row['PD_INPUT'])) {
            $pdInput = date('d-M-Y', strtotime($row['PD_INPUT']));
        }

        $data[] = array(
            'PD_ID'       => (int)$row['PD_ID'],
            'PD_LOT'      => trim($row['PD_LOT']),
            'PD_OK'       => (int)$row['PD_OK'],
            'PD_HO'       => (int)$row['PD_HO'],
            'PD_NG'       => (int)$row['PD_NG'],
            'PD_LOSTHOUR' => (float)$row['PD_LOSTHOUR'],
            'PD_SC'       => (float)$row['PD_SC'],
            'PD_REM'      => trim((string)$row['PD_REM']),
            'PD_OPR'      => trim((string)$row['PD_OPR']),
            'PD_PIC_LINE' => trim((string)$row['PD_PIC_LINE']),
            'PD_INPUT'    => $pdInput,
            // Tambahan array untuk Actual Data
            'PD_WKH'      => (float)$row['PD_WKH'],
            'PD_SETUPHOUR'=> (float)$row['PD_SETUPHOUR'],
            'PD_CAV'      => (int)$row['PD_CAV'],
            'PD_WEIGHT_S' => (float)$row['PD_WEIGHT_S'],
            'PD_RUN_S'    => (float)$row['PD_RUN_S'],
            'PD_CYTM'     => (float)$row['PD_CYTM'],
            'PD_LOST_REASON'=> trim((string)$row['PD_LOST_REASON'])
        );
    }
    sqlsrv_free_stmt($stmt);
}

echo json_encode($data);
exit;
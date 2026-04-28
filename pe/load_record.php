<?php
// load_record.php
require_once __DIR__ . '/../config/database_p1.php';
header('Content-Type: application/json');

$mode = $_GET['mode'] ?? '';
$code = intval($_GET['code'] ?? 0);

$JOIN = "
SELECT 
    T.*, I.ITEM_NAME, I.ITEM_NO, C.CUST_COMP, S.MAT_CODE, M.ITEM_NAME as MAT_NAME,
    S.WEIGHT_PART_STD, S.WEIGHT_RUNNER_STD, S.CYCLE_TIME_STD, S.TONAGE_STD, S.CAVITY_STD
FROM TRIAL_PE2 T
LEFT JOIN ITEMS I ON T.PART_CODE = I.ITEM_CODE
LEFT JOIN CUST C ON T.CUST_ID = C.CUST_ID
LEFT JOIN TRIAL_PE_STD S ON T.PART_CODE = S.ITEM_CODE
LEFT JOIN ITEMS M ON S.MAT_CODE = M.ITEM_CODE
";

if ($mode == 'next') $sql = "SELECT TOP 1 * FROM ($JOIN) X WHERE X.TRIAL_CODE > ? ORDER BY X.TRIAL_CODE ASC";
elseif ($mode == 'prev') $sql = "SELECT TOP 1 * FROM ($JOIN) X WHERE X.TRIAL_CODE < ? ORDER BY X.TRIAL_CODE DESC";
elseif ($mode == 'first') $sql = "SELECT TOP 1 * FROM ($JOIN) X ORDER BY X.TRIAL_CODE ASC";
else $sql = "SELECT TOP 1 * FROM ($JOIN) X WHERE X.TRIAL_CODE = ? OR ? = 0 ORDER BY X.TRIAL_CODE DESC";

$stmt = sqlsrv_query($conn, $sql, [$code, $code]);
$res = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if ($res) {
    if ($res['DATE'] instanceof DateTime) $res['DATE'] = $res['DATE']->format('Y-m-d');
    echo json_encode(['status' => 'ok', 'record' => $res]);
} else {
    echo json_encode(['status' => 'err']);
}
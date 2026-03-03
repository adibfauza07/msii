<?php
require_once '../config/database.php';
$term = isset($_GET['term']) ? $_GET['term'] : '';

$data = [];
$sql = "SELECT TOP 10 TRIAL_CODE, PART_CODE, CUST_ID, TRIAL_REASON, DATE
        FROM TRIAL_PE
        WHERE TRIAL_CODE LIKE ? 
        ORDER BY TRIAL_CODE DESC";
$params = ["%$term%"];
$res = sqlsrv_query($conn, $sql, $params);

if ($res) {
    while ($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) {
        $data[] = [
            'label' => $row['TRIAL_CODE'] . " | " . $row['PART_CODE'] . " | " . $row['TRIAL_REASON'],
            'value' => $row['TRIAL_CODE']
        ];
    }
}

echo json_encode($data);
?>

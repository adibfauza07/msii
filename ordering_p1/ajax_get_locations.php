<?php
require_once __DIR__ . "/../config/database_ordering.php";

$response = array('success' => false, 'data' => array());

$sql = "SELECT LOCATION FROM EPSON_LOC ORDER BY LOCATION ASC";
$stmt = sqlsrv_query($conn, $sql);

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if (isset($row['LOCATION']) && trim($row['LOCATION']) !== '') {
            $response['data'][] = trim($row['LOCATION']);
        }
    }
    $response['success'] = true;
}

echo json_encode($response);
?>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once __DIR__ . "/../config/global.php";

header('Content-Type: application/json; charset=utf-8');
$tranid = isset($_POST['tranid']) ? (int)$_POST['tranid'] : 0;

if ($conn === false || $tranid === 0) {
    echo json_encode(array('response' => 'false'));
    exit;
}

$sql = "
    SELECT TOP 1 TRANS.*, SUPPLIER.SUP_COMP 
    FROM TRANS 
    LEFT JOIN SUPPLIER ON SUPPLIER.SUP_CODE = TRANS.SUP_CODE 
    WHERE TRANS.TRAN_ID = ?
";
$stmt = sqlsrv_query($conn, $sql, array($tranid));

if ($stmt !== false && sqlsrv_has_rows($stmt)) {
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $adate = $row['TRAN_ADATE'] instanceof DateTime ? $row['TRAN_ADATE']->format('Y-m-d') : $row['TRAN_ADATE'];
    $bcdate = $row['BC_DATE'] instanceof DateTime ? $row['BC_DATE']->format('Y-m-d') : $row['BC_DATE'];

    $response = array(
        'response' => 'true',
        'message' => array(array(
            'adate'   => $adate,
            'trty'    => trim($row['TRTY_CODE']),
            'docno'   => trim($row['TRAN_DOC']),
            'supcode' => trim((string)$row['SUP_CODE']),
            'supname' => trim((string)$row['SUP_COMP']),
            'bcty'    => trim((string)$row['BCTY_ID']),
            'bcno'    => trim((string)$row['BC_NO']),
            'bcdate'  => $bcdate
        ))
    );
    echo json_encode($response);
    sqlsrv_free_stmt($stmt);
} else {
    echo json_encode(array('response' => 'false'));
}
?>
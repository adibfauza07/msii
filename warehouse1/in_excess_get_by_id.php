<?php
if (!isset($_SESSION)) { session_start(); }
require_once __DIR__ . "/../config/global.php";

header('Content-Type: application/json');

$tranid = isset($_POST['tranid']) ? (int)$_POST['tranid'] : 0;
if ($conn === false || $tranid === 0) {
    echo json_encode(['response' => 'false']);
    exit;
}

// Ambil Header
$sqlHeader = "
    SELECT T.TRAN_ID, T.TRAN_DATE, T.TRAN_ADATE, T.TRTY_CODE, T.TRAN_DOC, T.SUP_CODE, S.SUP_COMP 
    FROM TRANS T
    LEFT JOIN SUPPLIER S ON T.SUP_CODE = S.SUP_CODE
    WHERE T.TRAN_ID = ?
";
$stmtH = sqlsrv_query($conn, $sqlHeader, array($tranid));
$headerData = [];
if ($stmtH !== false && $rowH = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC)) {
    $adate = $rowH['TRAN_ADATE'] instanceof DateTime ? $rowH['TRAN_ADATE']->format('Y-m-d') : date('Y-m-d');
    $headerData[] = [
        'tranid'  => $rowH['TRAN_ID'],
        'adate'   => $adate,
        'trty'    => $rowH['TRTY_CODE'],
        'docno'   => $rowH['TRAN_DOC'],
        'supcode' => $rowH['SUP_CODE'],
        'supname' => $rowH['SUP_COMP']
    ];
}

// Ambil Detail Item
$sqlDetail = "
    SELECT ITEM_ID, IT_QTY, QRCODE_ID 
    FROM INV_TRAN 
    WHERE TRAN_ID = ? 
    ORDER BY IT_LINENO ASC
";
$stmtD = sqlsrv_query($conn, $sqlDetail, array($tranid));
$detailData = [];
if ($stmtD !== false) {
    while ($rowD = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) {
        $detailData[] = [
            'itemId' => $rowD['ITEM_ID'],
            'qty'    => $rowD['IT_QTY'],
            'qrId'   => $rowD['QRCODE_ID'],
            'poId'   => ''
        ];
    }
}

echo json_encode([
    'response' => !empty($headerData) ? 'true' : 'false',
    'message'  => $headerData,
    'details'  => $detailData
]);
?>
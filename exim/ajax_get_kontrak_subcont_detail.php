<?php
require_once __DIR__ . '/../config/database_p1.php';

header('Content-Type: application/json; charset=utf-8');

function json_out($arr) {
    echo json_encode($arr);
    exit;
}

$po_id = isset($_POST['po_id']) ? trim($_POST['po_id']) : '';

if ($po_id == '') {
    json_out(array(
        'success' => false,
        'message' => 'PO_ID kosong.'
    ));
}

/*
    DETAIL PO
    PO_DETAIL + ITEMS
*/
$sqlDetail = "
SELECT
    D.PO_ID,
    D.REQ_ID,
    D.ITEM_ID,
    D.POD_QTY,
    D.POD_PRICE,
    D.POD_UNIT,
    D.POD_DUE,
    D.POD_AMOUNT,
    ISNULL(I.ITEM_CODE, '') AS ITEM_CODE,
    ISNULL(I.ITEM_NAME, '') AS ITEM_NAME
FROM dbo.PO_DETAIL D
LEFT JOIN dbo.ITEMS I
    ON D.ITEM_ID = I.ITEM_ID
WHERE D.PO_ID = ?
ORDER BY I.ITEM_CODE
";

$stmtDetail = sqlsrv_query($conn, $sqlDetail, array($po_id));

if ($stmtDetail === false) {
    json_out(array(
        'success' => false,
        'message' => print_r(sqlsrv_errors(), true)
    ));
}

$rows = array();

while ($d = sqlsrv_fetch_array($stmtDetail, SQLSRV_FETCH_ASSOC)) {

    /*
        SUB DETAIL MATERIAL BC
        PO_DETAIL_BC + ITEMS material
    */
    $sqlSub = "
    SELECT
        B.PO_ID,
        B.ITEM_ID,
        B.MAT_ID,
        B.MAT_QTY,
        B.NOMOR_BC,
        B.NOMOR_BC_MAT,
        ISNULL(M.ITEM_CODE, '') AS MAT_CODE,
        ISNULL(M.ITEM_NAME, '') AS MAT_NAME
    FROM dbo.PO_DETAIL_BC B
    LEFT JOIN dbo.ITEMS M
        ON B.MAT_ID = M.ITEM_ID
    WHERE B.PO_ID = ?
    AND B.ITEM_ID = ?
    ORDER BY M.ITEM_CODE
    ";

    $stmtSub = sqlsrv_query($conn, $sqlSub, array($po_id, $d['ITEM_ID']));

    if ($stmtSub === false) {
        json_out(array(
            'success' => false,
            'message' => print_r(sqlsrv_errors(), true)
        ));
    }

    $subdetail = array();

    while ($s = sqlsrv_fetch_array($stmtSub, SQLSRV_FETCH_ASSOC)) {
        $subdetail[] = array(
            'PO_ID'        => $s['PO_ID'],
            'ITEM_ID'      => $s['ITEM_ID'],
            'MAT_ID'       => $s['MAT_ID'],
            'MAT_CODE'     => $s['MAT_CODE'],
            'MAT_NAME'     => $s['MAT_NAME'],
            'MAT_QTY'      => $s['MAT_QTY'],
            'NOMOR_BC'     => $s['NOMOR_BC'],
            'NOMOR_BC_MAT' => $s['NOMOR_BC_MAT']
        );
    }

    sqlsrv_free_stmt($stmtSub);

    $rows[] = array(
        'PO_ID'      => $d['PO_ID'],
        'REQ_ID'     => $d['REQ_ID'],
        'ITEM_ID'    => $d['ITEM_ID'],
        'ITEM_CODE'  => $d['ITEM_CODE'],
        'ITEM_NAME'  => $d['ITEM_NAME'],
        'POD_QTY'    => $d['POD_QTY'],
        'POD_PRICE'  => $d['POD_PRICE'],
        'POD_UNIT'   => $d['POD_UNIT'],
        'POD_DUE'    => $d['POD_DUE'],
        'POD_AMOUNT' => $d['POD_AMOUNT'],
        'subdetail'  => $subdetail
    );
}

sqlsrv_free_stmt($stmtDetail);

json_out(array(
    'success' => true,
    'rows' => $rows
));
<?php
require_once __DIR__ . '/../config/database_p1.php';

header('Content-Type: application/json; charset=utf-8');

function json_out($arr) {
    echo json_encode($arr);
    exit;
}

function fmt_date($value) {
    if ($value instanceof DateTime) {
        return $value->format('d-m-Y');
    }

    return '';
}

$po_num = isset($_POST['po_num']) ? trim($_POST['po_num']) : '';

if ($po_num == '') {
    json_out(array(
        'success' => false,
        'message' => 'PO NOMOR belum dipilih.'
    ));
}

$sql = "
SELECT
    PO.PO_ID,
    PO.SUP_ID,
    PO.PO_NUM,
    PO.PO_DATE,
    PO.PO_TO,
    PO.PO_TODEF,
    PO.PO_CUR,
    PO.PO_CLOSE,
    PO.PO_TERM,
    PO.PO_REM,
    PO.PO_TERMDEL,
    PO.PO_TERMDELSCH,
    PO.PO_REV,
    PO.PO_DATEDO,
    SUPPLIER.SUP_ABBR,
    SUPPLIER.SUP_COMP
FROM dbo.PO PO
INNER JOIN dbo.SUPPLIER SUPPLIER
    ON PO.SUP_ID = SUPPLIER.SUP_ID
WHERE SUPPLIER.SUP_ABBR = 'SUB'
AND PO.PO_NUM = ?
ORDER BY PO.PO_DATE DESC
";

$stmt = sqlsrv_query($conn, $sql, array($po_num));

if ($stmt === false) {
    json_out(array(
        'success' => false,
        'message' => print_r(sqlsrv_errors(), true)
    ));
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = array(
        'PO_ID'    => $r['PO_ID'],
        'PO_NUM'   => $r['PO_NUM'],
        'PO_DATE'  => fmt_date($r['PO_DATE']),
        'PO_TO'    => $r['PO_TO'],
        'PO_CUR'   => $r['PO_CUR'],
        'PO_CLOSE' => $r['PO_CLOSE'],
        'SUP_COMP' => $r['SUP_COMP']
    );
}

if (count($rows) == 0) {
    json_out(array(
        'success' => false,
        'message' => 'Data PO tidak ditemukan.'
    ));
}

json_out(array(
    'success' => true,
    'rows' => $rows
));
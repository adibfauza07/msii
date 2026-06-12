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

$q = isset($_POST['q']) ? trim($_POST['q']) : '';

if ($q == '') {
    json_out(array(
        'success' => true,
        'rows' => array()
    ));
}

$sql = "
SELECT TOP 30
    PO.PO_ID,
    PO.SUP_ID,
    PO.PO_NUM,
    PO.PO_DATE,
    PO.PO_TO,
    PO.PO_TODEF,
    PO.PO_CUR,
    PO.PO_CLOSE,
    SUPPLIER.SUP_ABBR,
    SUPPLIER.SUP_COMP
FROM dbo.PO PO
INNER JOIN dbo.SUPPLIER SUPPLIER
    ON PO.SUP_ID = SUPPLIER.SUP_ID
WHERE SUPPLIER.SUP_ABBR = 'SUB'
AND PO.PO_NUM LIKE ?
ORDER BY PO.PO_DATE DESC
";

$stmt = sqlsrv_query($conn, $sql, array('%' . $q . '%'));

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

json_out(array(
    'success' => true,
    'rows' => $rows
));
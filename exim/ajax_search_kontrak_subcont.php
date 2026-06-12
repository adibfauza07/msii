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
SELECT TOP 20
    T.TRAN_DATE,
    T.TRAN_DOC,
    T.TRAN_ID,
    ISNULL(S.SUP_COMP, '') AS SUP_COMP
FROM dbo.TRANS T
LEFT JOIN dbo.SUPPLIER S
    ON T.SUP_ID = S.SUP_ID
WHERE T.TRTY_CODE = '05'
AND T.TRAN_DOC LIKE ?
ORDER BY T.TRAN_DATE DESC
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
        'TRAN_DATE' => fmt_date($r['TRAN_DATE']),
        'TRAN_DOC'  => $r['TRAN_DOC'],
        'TRAN_ID'   => $r['TRAN_ID'],
        'SUP_COMP'  => $r['SUP_COMP']
    );
}

json_out(array(
    'success' => true,
    'rows' => $rows
));
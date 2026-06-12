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

$mat_id = isset($_POST['mat_id']) ? trim($_POST['mat_id']) : '';
$q = isset($_POST['q']) ? trim($_POST['q']) : '';

if ($mat_id == '') {
    json_out(array(
        'success' => true,
        'rows' => array()
    ));
}

$sql = "
SELECT TOP 30
    X.ITEM_ID,
    X.RCV_DATE,
    X.RCV_NO,
    X.JENIS_BC,
    X.NOMOR_BC,
    X.RCVD_QTY,
    X.OS_QTY,
    X.OUT_QTY
FROM (
    SELECT
        R.ITEM_ID,
        R.RCV_DATE,
        R.RCV_NO,
        R.JENIS_BC,
        R.NOMOR_BC,
        R.RCVD_QTY,
        R.RCVD_QTY
            - ISNULL(SUM(W.WO_QTY), 0)
            - ISNULL(SUM(P.MAT_QTY), 0) AS OS_QTY,
        ISNULL(SUM(W.WO_QTY), 0)
            + ISNULL(SUM(P.MAT_QTY), 0) AS OUT_QTY
    FROM dbo.VIEW_RECEIVE_BC R
    LEFT OUTER JOIN dbo.VIEW_PO_DETAIL_BC P
        ON R.ITEM_ID = P.MAT_ID
        AND R.NOMOR_BC = P.NOMOR_BC
    LEFT OUTER JOIN dbo.VIEW_WO_BC_SUM W
        ON R.ITEM_ID = W.ITEM_ID
        AND R.NOMOR_BC = W.NOMOR_BC
    WHERE R.ITEM_ID = ?
    GROUP BY
        R.ITEM_ID,
        R.RCV_DATE,
        R.RCV_NO,
        R.JENIS_BC,
        R.NOMOR_BC,
        R.RCVD_QTY
) X
WHERE X.OS_QTY > 0
AND (
    X.NOMOR_BC LIKE ?
    OR X.RCV_NO LIKE ?
    OR X.JENIS_BC LIKE ?
)
ORDER BY X.RCV_DATE DESC
";

$params = array(
    $mat_id,
    '%' . $q . '%',
    '%' . $q . '%',
    '%' . $q . '%'
);

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    json_out(array(
        'success' => false,
        'message' => print_r(sqlsrv_errors(), true)
    ));
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = array(
        'ITEM_ID'  => $r['ITEM_ID'],
        'RCV_DATE' => fmt_date($r['RCV_DATE']),
        'RCV_NO'   => $r['RCV_NO'],
        'JENIS_BC' => $r['JENIS_BC'],
        'NOMOR_BC' => $r['NOMOR_BC'],
        'RCVD_QTY' => $r['RCVD_QTY'],
        'OS_QTY'   => $r['OS_QTY'],
        'OUT_QTY'  => $r['OUT_QTY']
    );
}

sqlsrv_free_stmt($stmt);

json_out(array(
    'success' => true,
    'rows' => $rows
));
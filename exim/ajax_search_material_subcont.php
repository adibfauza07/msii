<?php
require_once __DIR__ . '/../config/database_p1.php';

header('Content-Type: application/json; charset=utf-8');

function json_out($arr) {
    echo json_encode($arr);
    exit;
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
    ITEM_ID,
    ITEM_CODE,
    ITEM_NAME
FROM dbo.ITEMS
WHERE ITTY_CODE IN ('02', '03', '05')
AND (
    ITEM_CODE LIKE ?
    OR ITEM_NAME LIKE ?
)
ORDER BY ITEM_CODE
";

$params = array('%' . $q . '%', '%' . $q . '%');

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
        'ITEM_ID'   => $r['ITEM_ID'],
        'ITEM_CODE' => $r['ITEM_CODE'],
        'ITEM_NAME' => $r['ITEM_NAME']
    );
}

sqlsrv_free_stmt($stmt);

json_out(array(
    'success' => true,
    'rows' => $rows
));
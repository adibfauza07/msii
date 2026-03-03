<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$term = isset($_GET['term']) ? $_GET['term'] : '';

$sql = "
SELECT TOP 10 
  icvt.CUST_ID, c.CUST_COMP,
  icvt.PART_CODE, icvt.PART_NAME,
  t.MAT_CODE, m.ITEM_NAME AS MAT_NAME
FROM ITEM_CUST_VIEW_TRIAL icvt
JOIN CUST c ON c.CUST_ID = icvt.CUST_ID
JOIN TRIAL_PE_STD t ON t.ITEM_CODE = icvt.PART_CODE
JOIN ITEMS m ON t.MAT_CODE = m.ITEM_CODE
WHERE icvt.PART_CODE LIKE ? OR icvt.PART_NAME LIKE ?
ORDER BY icvt.PART_NAME";

$params = ['%'.$term.'%', '%'.$term.'%'];
$res = sqlsrv_query($conn, $sql, $params);

$data = [];
while ($r = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) {
  $data[] = [
    'label' => $r['PART_CODE'].' - '.$r['PART_NAME'],
    'part_code' => $r['PART_CODE'],
    'part_name' => $r['PART_NAME'],
    'cust_id' => $r['CUST_ID'],
    'cust_comp' => $r['CUST_COMP'],
    'mat_code' => $r['MAT_CODE'],
    'mat_name' => $r['MAT_NAME']
  ];
}
echo json_encode($data);
?>
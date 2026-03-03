<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$mode = isset($_GET['mode']) ? $_GET['mode'] : '';
$code = isset($_GET['code']) ? intval($_GET['code']) : 0;

function fetchRow($conn, $sql, $param = array()) {
    $stmt = sqlsrv_query($conn, $sql, $param);
    if (!$stmt) return null;
    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$r) return null;

    if (isset($r['DATE']) && $r['DATE'] instanceof DateTime) {
        $r['DATE'] = $r['DATE']->format("Y-m-d");
    }

    return $r;
}

/* =======================================================
   QUERY MASTER SESUAI STRUKTUR MASTER ANDA
   ======================================================= */

$JOIN = "
SELECT 
    t.*,

    icvt.PART_NAME,
    icvt.PART_NO,
    icvt.CUST_ID AS CUST_ID_MASTER,
    c.CUST_COMP,

    std.MAT_CODE,
    mat.ITEM_NAME AS MAT_NAME

FROM TRIAL_PE t
LEFT JOIN ITEM_CUST_VIEW_TRIAL icvt 
       ON icvt.PART_CODE = t.PART_CODE
LEFT JOIN CUST c 
       ON c.CUST_ID = icvt.CUST_ID
LEFT JOIN TRIAL_PE_STD std
       ON std.ITEM_CODE = icvt.PART_CODE
LEFT JOIN ITEMS mat
       ON mat.ITEM_CODE = std.MAT_CODE
";

/* =======================================================
   MODE LOAD / NEXT / PREV
   ======================================================= */

$data = null;

if ($mode == "next") {
    $sql = "SELECT TOP 1 * FROM ( $JOIN ) X WHERE X.TRIAL_CODE > ? ORDER BY X.TRIAL_CODE ASC";
    $data = fetchRow($conn, $sql, array($code));
}
else if ($mode == "prev") {
    $sql = "SELECT TOP 1 * FROM ( $JOIN ) X WHERE X.TRIAL_CODE < ? ORDER BY X.TRIAL_CODE DESC";
    $data = fetchRow($conn, $sql, array($code));
}
else if ($mode == "first") {
    $sql = "SELECT TOP 1 * FROM ( $JOIN ) X ORDER BY X.TRIAL_CODE ASC";
    $data = fetchRow($conn, $sql);
}
else if ($mode == "last" || $mode == "load") {
    $sql = "SELECT TOP 1 * FROM ( $JOIN ) X ORDER BY X.TRIAL_CODE DESC";
    $data = fetchRow($conn, $sql);
}
else if ($mode == "minmax") {

    $min = fetchRow($conn, "SELECT MIN(TRIAL_CODE) AS C FROM TRIAL_PE");
    $max = fetchRow($conn, "SELECT MAX(TRIAL_CODE) AS C FROM TRIAL_PE");

    echo json_encode(array(
        "status" => "ok",
        "min_code" => $min['C'],
        "max_code" => $max['C']
    ));
    exit;
}

// jika tidak ada data
if (!$data) {
    echo json_encode(array("status" => "none"));
    exit;
}

echo json_encode(array(
    "status" => "ok",
    "record" => $data
));
?>

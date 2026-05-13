<?php
require_once __DIR__ . "/../config/db_plant2.php";

header("Content-Type: application/json");

if ($conn === false) {
    echo json_encode(array());
    exit();
}

function post_value($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : "";
}

$search_type = post_value("search_type");
$q = post_value("q");

if ($q == "") {
    echo json_encode(array());
    exit();
}

if ($search_type != "DI_INVNO") {
    $search_type = "DI_NO";
}

$like = "%" . $q . "%";
$startLike = $q . "%";

if ($search_type == "DI_INVNO") {
    $where = "D.DI_INVNO LIKE ?";
    $orderCase = "CASE WHEN D.DI_INVNO LIKE ? THEN 0 ELSE 1 END";
} else {
    $where = "D.DI_NO LIKE ?";
    $orderCase = "CASE WHEN D.DI_NO LIKE ? THEN 0 ELSE 1 END";
}

$sql = "
    SELECT TOP 20
        D.DI_ID,
        D.DI_NO,
        D.DI_INVNO,
        D.DI_DSNO,
        D.CUST_ID,
        D.CUST_CODE,
        C.CUST_COMP,
        C.CUST_ABBR
    FROM DI D
    LEFT JOIN CUST C ON C.CUST_ID = D.CUST_ID
    WHERE $where
    ORDER BY
        $orderCase,
        D.DI_ID DESC
";

$params = array($like, $startLike);

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(array());
    exit();
}

$data = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = array(
        "DI_ID"     => intval($row["DI_ID"]),
        "DI_NO"     => trim($row["DI_NO"]),
        "DI_INVNO"  => trim($row["DI_INVNO"]),
        "DI_DSNO"   => trim($row["DI_DSNO"]),
        "CUST_ID"   => intval($row["CUST_ID"]),
        "CUST_CODE" => trim($row["CUST_CODE"]),
        "CUST_COMP" => trim($row["CUST_COMP"]),
        "CUST_ABBR" => trim($row["CUST_ABBR"])
    );
}

echo json_encode($data);
?>
<?php
//search_sup.php
require_once __DIR__ . "/../config/database_ordering.php";

header("Content-Type: application/json");

if ($conn === false) {
    echo json_encode(array());
    exit();
}

 $q = isset($_POST["q"]) ? trim($_POST["q"]) : "";

if ($q == "") {
    echo json_encode(array());
    exit();
}

 $like      = "%" . $q . "%";
 $startLike = $q . "%";

 $sql = "
    SELECT TOP 20
        SUP_CODE,
        SUP_COMP
    FROM SUPPLIER
    WHERE SUP_CODE LIKE ?
       OR SUP_COMP LIKE ?
    ORDER BY
        CASE WHEN SUP_CODE LIKE ? THEN 0 ELSE 1 END,
        SUP_CODE
";

 $params = array($like, $like, $startLike);
 $stmt   = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(array());
    exit();
}

 $data = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = array(
        "SUP_CODE" => trim($row["SUP_CODE"]),
        "SUP_COMP" => trim($row["SUP_COMP"])
    );
}

echo json_encode($data);
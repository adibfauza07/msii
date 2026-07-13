<?php
require_once __DIR__ . "/../config/global.php";

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
        ITEM_CODE,
        ITEM_NAME
    FROM ITEMS
    WHERE ITEM_CODE LIKE ?
       OR ITEM_NAME LIKE ?
    ORDER BY
        CASE WHEN ITEM_CODE LIKE ? THEN 0 ELSE 1 END,
        ITEM_CODE
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
        "ITEM_CODE" => trim($row["ITEM_CODE"]),
        "ITEM_NAME" => trim($row["ITEM_NAME"])
    );
}

echo json_encode($data);
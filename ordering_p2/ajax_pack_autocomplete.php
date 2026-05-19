<?php
require_once __DIR__ . "/../config/db_plant2.php";

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

$like = "%" . $q . "%";
$startLike = $q . "%";

$sql = "
    SELECT TOP 20
        PACK_ID,
        PACK_CODE
    FROM PACK
    WHERE 
        PACK_CODE LIKE ?
        AND ISNULL(PACK_CODE, '') <> ''
    ORDER BY
        CASE WHEN PACK_CODE LIKE ? THEN 0 ELSE 1 END,
        PACK_CODE
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
        "PACK_ID"   => intval($row["PACK_ID"]),
        "PACK_CODE" => trim($row["PACK_CODE"])
    );
}

echo json_encode($data);
?>
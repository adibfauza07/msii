<?php
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

$like = "%" . $q . "%";
$startLike = $q . "%";

// Menambahkan PACK_DESC pada query SELECT dan pencarian WHERE
$sql = "
    SELECT TOP 20
        PACK_ID,
        PACK_CODE,
        PACK_DESC
    FROM PACK
    WHERE 
        (PACK_CODE LIKE ? OR PACK_DESC LIKE ?)
        AND ISNULL(PACK_CODE, '') <> ''
    ORDER BY
        CASE WHEN PACK_CODE LIKE ? THEN 0 ELSE 1 END,
        PACK_CODE
";

$params = array($like, $like, $startLike);

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(array());
    exit();
}

$data = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = array(
        "PACK_ID"   => intval($row["PACK_ID"]),
        "PACK_CODE" => trim($row["PACK_CODE"]),
        "PACK_DESC" => isset($row["PACK_DESC"]) ? trim($row["PACK_DESC"]) : ""
    );
}

echo json_encode($data);
?>
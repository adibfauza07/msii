<?php
// ajax_pack_autocomplete.php
require_once __DIR__ . "/../config/database_ordering.php";

header("Content-Type: application/json");

if (!isset($conn) || $conn === false) {
    echo json_encode(array());
    exit();
}

$q = isset($_POST["q"]) ? trim($_POST["q"]) : "";

if ($q === "") {
    echo json_encode(array());
    exit();
}

$like      = "%" . $q . "%";
$startLike = $q . "%";

$sql = "
    SELECT TOP 20
        PACK_ID,
        PACK_CODE,
        PACK_NAME,
        PACK_DESC,
        PACK_WEIGHT
    FROM PACK
    WHERE PACK_CODE LIKE ?
       OR PACK_NAME LIKE ?
       OR PACK_DESC LIKE ?
    ORDER BY
        CASE WHEN PACK_CODE LIKE ? THEN 0 ELSE 1 END,
        PACK_CODE
";

$params = array($like, $like, $like, $startLike);
$stmt   = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(array());
    exit();
}

$data = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = array(
        "PACK_ID"     => (int)$row["PACK_ID"],
        "PACK_CODE"   => trim($row["PACK_CODE"]),
        // Gunakan isset() untuk PHP 5.4 agar tidak error
        "PACK_NAME"   => isset($row["PACK_NAME"]) ? trim($row["PACK_NAME"]) : "",
        "PACK_DESC"   => isset($row["PACK_DESC"]) ? trim($row["PACK_DESC"]) : "",
        "PACK_WEIGHT" => isset($row["PACK_WEIGHT"]) ? (float)$row["PACK_WEIGHT"] : 0
    );
}

echo json_encode($data);
?>
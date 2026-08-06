<?php
// Sesuaikan path koneksi database (pastikan sama dengan file form.php)
require_once __DIR__ . "/../../config/database_ordering.php";

header("Content-Type: application/json");

if (!isset($conn) || $conn === false) {
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
        ITEM_ID,
        ITEM_CODE,
        ITEM_NAME,
        ITEM_UNIT
    FROM ITEMS
    WHERE ITEM_CODE LIKE ?
       OR ITEM_NAME LIKE ?
    ORDER BY
        CASE WHEN ITEM_CODE LIKE ? THEN 0 ELSE 1 END,
        ITEM_CODE
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
        "ITEM_ID"   => $row["ITEM_ID"],
        "ITEM_CODE" => trim($row["ITEM_CODE"]),
        "ITEM_NAME" => trim($row["ITEM_NAME"]),
        "ITEM_UNIT" => trim($row["ITEM_UNIT"])
    );
}

echo json_encode($data);
?>
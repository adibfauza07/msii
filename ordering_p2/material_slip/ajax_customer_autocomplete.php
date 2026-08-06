<?php
// PERBAIKAN: Ubah path menjadi mundur 2 tingkat (../../)
require_once __DIR__ . "/../../config/database_ordering.php";

header("Content-Type: application/json");

// Pastikan variabel $conn dikenali
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
        CUST_ID,
        CUST_CODE,
        CUST_COMP,
        CUST_ABBR
    FROM CUST
    WHERE CUST_CODE LIKE ?
       OR CUST_COMP LIKE ?
       OR CUST_ABBR LIKE ?
    ORDER BY
        CASE WHEN CUST_CODE LIKE ? THEN 0 ELSE 1 END,
        CUST_CODE
";

$params = array($like, $like, $like, $startLike);

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(array());
    exit();
}

$data = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = array(
        "CUST_ID"   => isset($row["CUST_ID"]) ? $row["CUST_ID"] : '',
        "CUST_CODE" => isset($row["CUST_CODE"]) ? trim($row["CUST_CODE"]) : '',
        "CUST_COMP" => isset($row["CUST_COMP"]) ? trim($row["CUST_COMP"]) : '',
        "CUST_ABBR" => isset($row["CUST_ABBR"]) ? trim($row["CUST_ABBR"]) : ''
    );
}

echo json_encode($data);
?>
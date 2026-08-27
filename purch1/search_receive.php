<?php
// search_receive.php
// PHP 5.4 + SQL Server sqlsrv + jQuery UI Autocomplete

require_once __DIR__ . "/../config/database_ordering.php";

header("Content-Type: application/json; charset=UTF-8");

if (!isset($conn) || $conn === false) {
    echo json_encode(array());
    exit;
}

$term = isset($_GET["term"]) ? trim($_GET["term"]) : "";

if ($term === "") {
    echo json_encode(array());
    exit;
}

$like = "%" . $term . "%";
$startLike = $term . "%";

// Sesuai SP: filter Receive memakai kolom RECEIVE.RCV_NO.
$sql = "
    SELECT TOP 20
        RCV_NO,
        RCV_DATE
    FROM dbo.RECEIVE
    WHERE RCV_NO IS NOT NULL
      AND LTRIM(RTRIM(RCV_NO)) <> ''
      AND RCV_NO LIKE ?
    ORDER BY
        CASE WHEN RCV_NO LIKE ? THEN 0 ELSE 1 END,
        RCV_DATE DESC,
        RCV_NO
";

$params = array($like, $startLike);
$stmt = @sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(array());
    exit;
}

$data = array();
$seen = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $receiveNo = isset($row["RCV_NO"]) ? trim((string) $row["RCV_NO"]) : "";

    if ($receiveNo === "" || isset($seen[$receiveNo])) {
        continue;
    }

    $seen[$receiveNo] = true;

    $data[] = array(
        "label" => $receiveNo,
        "value" => $receiveNo
    );
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

echo json_encode($data);
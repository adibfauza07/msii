<?php
// search_item.php
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

$sql = "
    SELECT TOP 20
        ITEM_CODE,
        ITEM_NAME
    FROM dbo.ITEMS
    WHERE ITEM_CODE LIKE ?
       OR ITEM_NAME LIKE ?
    ORDER BY
        CASE WHEN ITEM_CODE LIKE ? THEN 0 ELSE 1 END,
        ITEM_CODE
";

$params = array($like, $like, $startLike);
$stmt = @sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(array());
    exit;
}

$data = array();

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $itemCode = isset($row["ITEM_CODE"]) ? trim((string) $row["ITEM_CODE"]) : "";
    $itemName = isset($row["ITEM_NAME"]) ? trim((string) $row["ITEM_NAME"]) : "";

    if ($itemCode === "") {
        continue;
    }

    $labelText = $itemCode;
    if ($itemName !== "") {
        $labelText .= " - " . $itemName;
    }

    $data[] = array(
        "label" => $labelText,
        "value" => $itemCode
    );
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

echo json_encode($data);
<?php
require_once "../config/database.php";

$trial = isset($_GET['trial']) ? intval($_GET['trial']) : 0;

$sql = "SELECT ID, Weight_Part_Actual, CAVITY  
        FROM Trial_PE_WPart_ACT
        WHERE Trial_CODE = ?
        ORDER BY ID ASC";

$stmt = sqlsrv_query($conn, $sql, array($trial));

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = [
        "ID" => $row["ID"],
        "Weight_Part_Actual" => $row["Weight_Part_Actual"],
        "CAVITY" => $row["CAVITY"]
    ];
}

echo json_encode($data);

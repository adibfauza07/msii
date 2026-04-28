<?php
require_once __DIR__ . '/../config/database_p1.php';

$trial = intval($_POST['trial']);

$sql = "INSERT INTO Trial_PE_WPart_ACT (Trial_CODE, Weight_Part_Actual, CAVITY)
        VALUES (?, 0, 0)";

$stmt = sqlsrv_query($conn, $sql, array($trial));

if ($stmt){
    echo json_encode(["status"=>"ok"]);
} else {
    echo json_encode(["status"=>"err"]);
}

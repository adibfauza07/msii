<?php
require_once "../config/database.php";

$id     = intval($_POST['id']);
$trial  = intval($_POST['trial']);
$weight = floatval($_POST['weight']);
$cavity = intval($_POST['cavity']);

if ($id == 0) {
    // INSERT
    $sql = "INSERT INTO Trial_PE_WPart_ACT 
            (Trial_CODE, Weight_Part_Actual, CAVITY) 
            VALUES (?, ?, ?)";

    $params = array($trial, $weight, $cavity);

} else {
    // UPDATE
    $sql = "UPDATE Trial_PE_WPart_ACT
            SET Weight_Part_Actual = ?, CAVITY = ?
            WHERE ID = ?";

    $params = array($weight, $cavity, $id);
}

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo json_encode(["status" => "ok"]);
} else {
    echo json_encode(["status" => "err", "msg" => sqlsrv_errors()]);
}

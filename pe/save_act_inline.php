<?php
require_once "../config/database.php";

$id    = intval($_POST['id']);
$field = $_POST['field'];
$value = $_POST['value'];

// Whitelist biar aman
$allowed = ["Weight_Part_Actual", "CAVITY"];
if(!in_array($field, $allowed)){
    echo json_encode(["status"=>"err","msg"=>"field invalid"]);
    exit;
}

$sql = "UPDATE Trial_PE_WPart_ACT SET $field = ? WHERE ID = ?";
$params = array($value, $id);

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt){
    echo json_encode(["status"=>"ok"]);
} else {
    echo json_encode(["status"=>"err"]);
}

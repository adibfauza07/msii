<?php
require_once "../config/database.php";

$id = intval($_POST['id']);

$sql = "DELETE FROM Trial_PE_WPart_ACT WHERE ID = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));

if ($stmt) {
    echo json_encode(["status" => "ok"]);
} else {
    echo json_encode(["status" => "err"]);
}

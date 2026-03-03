<?php
require_once '../config/database_aging.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = $_POST['type']; // 'sales' atau 'ap'
    $id = intval($_POST['id']);

    if ($type == 'sales') {
        $sql = "UPDATE TRANS_SALES SET is_paid = 1 WHERE id_sales = ?";
    } else {
        $sql = "UPDATE TRANS_AP SET is_paid = 1 WHERE id_ap = ?";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->execute(array($id));
        echo "success";
    } else {
        echo "Error: " . $conn->errorInfo()[2];
    }
}
?>
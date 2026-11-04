<?php
if (!isset($_SESSION)) { session_start(); }
require_once __DIR__ . "/../config/global.php";

$tranid = isset($_POST['tranid']) ? (int)$_POST['tranid'] : 0;

if ($conn !== false && $tranid > 0) {
    // Hapus detail
    sqlsrv_query($conn, "DELETE FROM INV_TRAN WHERE TRAN_ID = ?", array($tranid));
    // Hapus header
    $stmt = sqlsrv_query($conn, "DELETE FROM TRANS WHERE TRAN_ID = ?", array($tranid));
    
    if ($stmt !== false) {
        echo 'true';
        exit;
    }
}
echo 'false';
?>
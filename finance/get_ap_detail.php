<?php
// /msii/finance/get_ap_detail.php
require_once '../config/database_aging.php';

if(isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM TRANS_AP WHERE id_ap = ?";
    $stmt = q($sql, array($id));
    $data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    // Format tanggal untuk input HTML date (YYYY-MM-DD)
    if($data['invoice_date'] instanceof DateTime) {
        $data['invoice_date'] = $data['invoice_date']->format('Y-m-d');
    }
    if($data['due_date'] instanceof DateTime) {
        $data['due_date'] = $data['due_date']->format('Y-m-d');
    }

    echo json_encode($data);
}
?>
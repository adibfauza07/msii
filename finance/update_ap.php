<?php
// /msii/finance/update_ap.php
require_once '../config/database_aging.php';

if (isset($_POST['update_ap'])) {
    $id_ap        = $_POST['id_ap'];
    $sup_id       = $_POST['SUP_ID'];
    $inv_num      = $_POST['invoice_number'];
    $inv_date     = $_POST['invoice_date'];
    $due_date     = $_POST['due_date'];
    $amount       = $_POST['amount'];
    $curr         = $_POST['curr_code'];
    $faktur       = $_POST['faktur_pajak'];
    $id_biaya     = $_POST['id_biaya'];
    $id_kat_ap    = $_POST['id_supplier_cat'];

    $deskripsi    = isset($_POST['deskripsi']) ? $_POST['deskripsi'] : NULL;
    
    // Logika Paid: 1 jika dicentang, 0 jika tidak
    $is_paid      = isset($_POST['is_paid']) ? 1 : 0;

    $sql = "UPDATE TRANS_AP SET 
                SUP_ID = ?, 
                invoice_number = ?, 
                invoice_date = ?,
                due_date = ?, 
                amount = ?, 
                curr_code = ?, 
                faktur_pajak = ?,
                id_biaya = ?,
                id_supplier_cat = ?,
                is_paid = ?,
                deskripsi = ?
            WHERE id_ap = ?";
    
    $params = array($sup_id, $inv_num, $inv_date, $due_date, $amount, $curr, $faktur, $id_biaya, $id_kat_ap, $is_paid, $deskripsi, $id_ap);
    
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        header("Location: aging_ap.php?status=success_update");
        exit();
    } else {
        die(print_r(sqlsrv_errors(), true));
    }
}
?>
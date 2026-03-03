<?php
// /msii/finance/save_ap.php
require_once '../config/database_aging.php';

if (isset($_POST['save_ap'])) {
    $sup_id       = $_POST['SUP_ID'];
    $inv_date     = $_POST['invoice_date'];
    $inv_num      = $_POST['invoice_number'];
    $faktur       = $_POST['faktur_pajak'];
    $curr         = $_POST['curr_code'];
    $amount       = $_POST['amount'];
    $due_date     = $_POST['due_date'];
    
    // Menangkap ID Biaya dan Kategori Supplier
    $id_biaya     = isset($_POST['id_biaya']) ? $_POST['id_biaya'] : null;
    $id_kat_ap    = isset($_POST['id_supplier_cat']) ? $_POST['id_supplier_cat'] : null;
    $deskripsi = isset($_POST['deskripsi']) ? $_POST['deskripsi'] : NULL;

    $sql = "INSERT INTO TRANS_AP (SUP_ID, invoice_date, invoice_number, faktur_pajak, curr_code, amount, due_date, id_biaya, id_supplier_cat, deskripsi, is_paid) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
    
    $params = array($sup_id, $inv_date, $inv_num, $faktur, $curr, $amount, $due_date, $id_biaya, $id_kat_ap, $deskripsi);
    
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        header("Location: aging_ap.php?status=success");
        exit();
    } else {
        die(print_r(sqlsrv_errors(), true));
    }
}
?>
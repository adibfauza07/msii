<?php
session_start();
require_once '../config/database_aging.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. AMBIL DATA DARI FORM
    $act = $_POST['act']; // 'insert' atau 'update'
    
    $sup_id         = $_POST['SUP_ID'];
    $invoice_number = $_POST['invoice_number'];
    $faktur_pajak   = $_POST['faktur_pajak'];
    $invoice_date   = $_POST['invoice_date'];
    $due_date       = $_POST['due_date'];
    $amount         = str_replace(',', '', $_POST['amount']); 
    $curr_code      = $_POST['curr_code'];
    $id_kategori    = $_POST['id_supplier_cat'];
    $id_biaya       = !empty($_POST['id_biaya']) ? $_POST['id_biaya'] : null;
    $is_paid        = isset($_POST['is_paid']) ? 1 : 0;

    // 2. LOGIKA UPDATE
    if ($act == 'update') {
        $id_ap = $_POST['id_ap'];

        $sql = "UPDATE TRANS_AP SET 
                SUP_ID = ?, 
                invoice_number = ?, 
                faktur_pajak = ?, 
                invoice_date = ?, 
                due_date = ?, 
                amount = ?, 
                curr_code = ?, 
                id_supplier_cat = ?, 
                id_biaya = ?, 
                is_paid = ?
                WHERE id_ap = ?";
        
        $params = array(
            $sup_id, $invoice_number, $faktur_pajak, $invoice_date, $due_date, 
            $amount, $curr_code, $id_kategori, $id_biaya, $is_paid, $id_ap
        );

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            header("Location: aging_ap.php?msg=updated");
        } else {
            die("Update Gagal: " . print_r(sqlsrv_errors(), true));
        }
    } 
    
    // 3. LOGIKA INSERT (INPUT BARU)
    else if ($act == 'insert') {
        $sql = "INSERT INTO TRANS_AP 
                (SUP_ID, invoice_number, faktur_pajak, invoice_date, due_date, amount, curr_code, id_supplier_cat, id_biaya, is_paid)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = array(
            $sup_id, $invoice_number, $faktur_pajak, $invoice_date, $due_date, 
            $amount, $curr_code, $id_kategori, $id_biaya, 0
        );

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            header("Location: aging_ap.php?msg=saved");
        } else {
            die("Simpan Gagal: " . print_r(sqlsrv_errors(), true));
        }
    }
}
?>
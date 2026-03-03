<?php
require_once '../config/database_aging.php';

if (isset($_POST['update_sales'])) {
    $id_sales     = $_POST['id_sales'];
    $cust_id      = $_POST['CUST_ID'];
    $inv_num      = $_POST['invoice_number'];
    $inv_date     = $_POST['invoice_date'];
    $due_date     = $_POST['due_date'];
    $amount       = $_POST['amount'];
    $curr         = $_POST['curr_code'];
    $faktur       = $_POST['faktur_pajak'];
    $id_biaya     = $_POST['id_biaya'];
    $id_kat_sales = $_POST['id_kategori_sales'];
    
    // Logika checkbox is_paid
    $is_paid      = isset($_POST['is_paid']) ? 1 : 0;

    $sql = "UPDATE TRANS_SALES SET 
                CUST_ID = ?, 
                invoice_number = ?, 
                invoice_date = ?,
                due_date = ?, 
                amount = ?, 
                curr_code = ?, 
                faktur_pajak = ?,
                id_biaya = ?,
                id_kategori_sales = ?,
                is_paid = ?
            WHERE id_sales = ?";
    
    $params = array(
        $cust_id, $inv_num, $inv_date, $due_date, $amount, 
        $curr, $faktur, $id_biaya, $id_kat_sales, $is_paid, $id_sales
    );
    
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        echo "<script>alert('Data Sales Berhasil Diupdate!'); window.location='aging_sales.php';</script>";
    } else {
        die(print_r(sqlsrv_errors(), true));
    }
}
?>
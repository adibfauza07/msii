<?php
require_once '../config/database_aging.php';

if (isset($_POST['save_sales'])) {
    $cust_id      = $_POST['CUST_ID'];
    $inv_date     = $_POST['invoice_date'];
    $inv_num      = $_POST['invoice_number'];
    $faktur       = $_POST['faktur_pajak'];
    $curr         = $_POST['curr_code'];
    $amount       = $_POST['amount'];
    $due_date     = $_POST['due_date'];
    $id_biaya     = $_POST['id_biaya'];
    $id_kat_sales = $_POST['id_kategori_sales'];

    $sql = "INSERT INTO TRANS_SALES (CUST_ID, invoice_date, invoice_number, faktur_pajak, curr_code, amount, due_date, id_biaya, id_kategori_sales, is_paid) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
    
    $params = array($cust_id, $inv_date, $inv_num, $faktur, $curr, $amount, $due_date, $id_biaya, $id_kat_sales);
    
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        echo "<script>alert('Data Sales Berhasil Disimpan!'); window.location='aging_sales.php';</script>";
    } else {
        die(print_r(sqlsrv_errors(), true));
    }
}
?>
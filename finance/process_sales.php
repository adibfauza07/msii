<?php
session_start();
// Sesuaikan path ini dengan lokasi file koneksi database Anda
require_once '../config/database_aging.php';

// Cek apakah form dikirim via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. AMBIL DATA DARI FORM
    $act = $_POST['act']; // 'insert' atau 'update'
    
    $cust_id       = $_POST['cust_id'];
    $invoice_number= $_POST['invoice_number'];
    $faktur_pajak  = $_POST['faktur_pajak'];
    $invoice_date  = $_POST['invoice_date'];
    $due_date      = $_POST['due_date'];
    $amount        = str_replace(',', '', $_POST['amount']); // Hapus koma jika ada format ribuan
    $curr_code     = $_POST['curr_code'];
    $id_kategori   = $_POST['id_kategori_sales'];
    $id_biaya      = !empty($_POST['id_biaya']) ? $_POST['id_biaya'] : null; // Boleh null
    
    // Checkbox is_paid: Jika dicentang bernilai 1, jika tidak 0
    $is_paid       = isset($_POST['is_paid']) ? 1 : 0;

    // 2. LOGIKA UPDATE
    if ($act == 'update') {
        $id_sales = $_POST['id_sales'];

        $sql = "UPDATE TRANS_SALES SET 
                CUST_ID = ?, 
                invoice_number = ?, 
                faktur_pajak = ?, 
                invoice_date = ?, 
                due_date = ?, 
                amount = ?, 
                curr_code = ?, 
                id_kategori_sales = ?, 
                id_biaya = ?, 
                is_paid = ?
                WHERE id_sales = ?";
        
        $params = array(
            $cust_id, $invoice_number, $faktur_pajak, $invoice_date, $due_date, 
            $amount, $curr_code, $id_kategori, $id_biaya, $is_paid, $id_sales
        );

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            header("Location: Aging_Sales.php?msg=updated");
        } else {
            die("Update Gagal: " . print_r(sqlsrv_errors(), true));
        }
    } 
    
    // 3. LOGIKA INSERT (INPUT BARU)
    else if ($act == 'insert') {
        $sql = "INSERT INTO TRANS_SALES 
                (CUST_ID, invoice_number, faktur_pajak, invoice_date, due_date, amount, curr_code, id_kategori_sales, id_biaya, is_paid)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = array(
            $cust_id, $invoice_number, $faktur_pajak, $invoice_date, $due_date, 
            $amount, $curr_code, $id_kategori, $id_biaya, 0 // Default belum lunas saat input baru
        );

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            header("Location: Aging_Sales.php?msg=saved");
        } else {
            die("Simpan Gagal: " . print_r(sqlsrv_errors(), true));
        }
    }
}
?>
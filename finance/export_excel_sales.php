<?php
// export_excel_sales.php
session_start();
require_once '../config/database_aging.php';

// 1. Header agar dibaca sebagai File Excel
$filename = "Aging_Sales_" . date('Ymd') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache"); 
header("Expires: 0");

// 2. Query Data (Sama persis dengan Aging_Sales.php)
// Pastikan filternya sesuai kebutuhan (misal: hanya yang belum lunas)
$sql = "SELECT T.id_sales, S.CUST_COMP, T.invoice_date, T.invoice_number, 
               T.faktur_pajak, T.curr_code, T.amount, T.due_date, B.AccountName, 
               K.SalesName, DATEDIFF(day, GETDATE(), T.due_date) as sisa_hari
        FROM TRANS_SALES T
        LEFT JOIN CUST S ON T.CUST_ID = S.CUST_ID
        LEFT JOIN MasterBiayaSales B ON T.id_biaya = B.id_biaya
        LEFT JOIN MasterKategoriSales K ON T.id_kategori_sales = K.id_kategori_sales
        WHERE T.is_paid = 0
        ORDER BY T.due_date ASC";

$query = sqlsrv_query($conn, $sql);
if ($query === false) { die(print_r(sqlsrv_errors(), true)); }
?>

<table border="1">
    <thead>
        <tr style="background-color:#f2f2f2; font-weight:bold;">
            <th>No.</th>
            <th>Customer</th>
            <th>Tgl Invoice</th>
            <th>No. Invoice</th>
            <th>Faktur Pajak</th>
            <th>Mata Uang</th>
            <th>Total Amount</th>
            <th>Jatuh Tempo</th>
            <th>Kategori</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): 
            $sisa = (int)$row['sisa_hari'];
            $status = ($sisa <= 0) ? "OVERDUE ($sisa Hari)" : "$sisa Hari lagi";
            $color = ($sisa <= 0) ? "red" : "black";
        ?>
        <tr>
            <td style="text-align:center;"><?= $no++ ?></td>
            <td><?= htmlspecialchars($row['CUST_COMP']) ?></td>
            <td><?= ($row['invoice_date']) ? $row['invoice_date']->format('d/m/Y') : '-' ?></td>
            <td style="text-align:left;"><?= htmlspecialchars($row['invoice_number']) ?></td>
            <td style="text-align:left;"><?= htmlspecialchars($row['faktur_pajak']) ?></td>
            <td style="text-align:center;"><?= $row['curr_code'] ?></td>
            <td style="text-align:right;" x:num><?= $row['amount'] ?></td> 
            <td><?= ($row['due_date']) ? $row['due_date']->format('d/m/Y') : '-' ?></td>
            <td><?= htmlspecialchars($row['SalesName']) ?></td>
            <td style="color:<?= $color ?>; font-weight:bold;"><?= $status ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
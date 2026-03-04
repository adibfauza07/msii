<?php
require_once '../config/database_aging.php';

// Nama File saat didownload
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Aging_Sales_" . date('Ymd_His') . ".xls");

// --- MENANGKAP PARAMETER FILTER DARI URL ---
$filter_cust = isset($_GET['cust']) ? $_GET['cust'] : '';
$filter_inv  = isset($_GET['inv'])  ? $_GET['inv']  : '';
$filter_kat  = isset($_GET['kat'])  ? $_GET['kat']  : '';

$where_add = "";
$params = array();

if ($filter_cust !== '') {
    $where_add .= " AND S.CUST_COMP = ? ";
    $params[] = $filter_cust;
}
if ($filter_inv !== '') {
    $where_add .= " AND T.invoice_number LIKE ? ";
    $params[] = "%" . $filter_inv . "%";
}
if ($filter_kat !== '') {
    $where_add .= " AND K.SalesName = ? ";
    $params[] = $filter_kat;
}

// Query Utama
$sql = "SELECT T.id_sales, S.CUST_COMP, T.invoice_date, T.invoice_number, 
               T.faktur_pajak, T.curr_code, T.amount, T.due_date, B.AccountName, 
               K.SalesName, DATEDIFF(day, GETDATE(), T.due_date) as sisa_hari
        FROM TRANS_SALES T
        LEFT JOIN CUST S ON T.CUST_ID = S.CUST_ID
        LEFT JOIN MasterBiayaSales B ON T.id_biaya = B.id_biaya
        LEFT JOIN MasterKategoriSales K ON T.id_kategori_sales = K.id_kategori_sales
        WHERE T.is_paid = 0 " . $where_add . " 
        ORDER BY T.due_date ASC";

$query = sqlsrv_query($conn, $sql, $params);
if ($query === false) { die(print_r(sqlsrv_errors(), true)); }
?>

<!DOCTYPE html>
<html>
<head>
    <title>Export Data Sales</title>
</head>
<body>
    <h3>DATA AGING SALES (CUSTOMER)</h3>
    <table border="1">
        <thead>
            <tr style="background-color:#f2f2f2;">
                <th>No.</th>
                <th>Customer</th>
                <th>Tgl Invoice</th>
                <th>No. Invoice</th>
                <th>Faktur Pajak</th>
                <th>Mata Uang</th>
                <th>Total Amount</th>
                <th>Jatuh Tempo</th>
                <th>Biaya (AR)</th>
                <th>Kategori</th>
                <th>Status (Sisa Hari)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): 
                $sisa = (int)$row['sisa_hari'];
                
                // FORMAT TANGGAL: 01-Sep-26
                $tgl_inv = ($row['invoice_date']) ? $row['invoice_date']->format('d-M-y') : '-';
                $tgl_due = ($row['due_date']) ? $row['due_date']->format('d-M-y') : '-';
            ?>
            <tr>
                <td align="center"><?= $no++ ?></td>
                <td><?= htmlspecialchars($row['CUST_COMP']) ?></td>
                <td align="center"><?= $tgl_inv ?></td>
                
                <td style="mso-number-format:'\@';"><?= htmlspecialchars($row['invoice_number']) ?></td>
                <td style="mso-number-format:'\@';"><?= htmlspecialchars($row['faktur_pajak']) ?></td>
                
                <td align="center"><?= $row['curr_code'] ?></td>
                <td align="right"><?= number_format($row['amount'], 2) ?></td>
                <td align="center"><?= $tgl_due ?></td>
                <td><?= htmlspecialchars($row['AccountName']) ?></td>
                <td><?= htmlspecialchars($row['SalesName']) ?></td>
                <td align="center">
                    <?= ($sisa <= 0) ? "OVERDUE" : $sisa . " Hari lagi" ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
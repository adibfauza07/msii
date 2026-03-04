<?php
require_once '../config/database_aging.php';

// Nama File
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Aging_AP_" . date('Ymd_His') . ".xls");

// --- FILTER ---
$filter_sup = isset($_GET['sup']) ? $_GET['sup'] : '';
$filter_inv = isset($_GET['inv']) ? $_GET['inv'] : '';
$filter_kat = isset($_GET['kat']) ? $_GET['kat'] : '';

$where_add = "";
$params = array();

if ($filter_sup !== '') {
    $where_add .= " AND S.SUP_COMP = ? ";
    $params[] = $filter_sup;
}
if ($filter_inv !== '') {
    $where_add .= " AND T.invoice_number LIKE ? ";
    $params[] = "%" . $filter_inv . "%";
}
if ($filter_kat !== '') {
    $where_add .= " AND K.SupplierName = ? ";
    $params[] = $filter_kat;
}

// Query Utama (Termasuk kolom deskripsi)
$sql = "SELECT T.id_ap, S.SUP_COMP, T.invoice_date, T.invoice_number, 
               T.faktur_pajak, T.curr_code, T.amount, T.due_date, B.AccountName, 
               K.SupplierName as NamaKategori, T.deskripsi, DATEDIFF(day, GETDATE(), T.due_date) as sisa_hari
        FROM TRANS_AP T
        LEFT JOIN SUPPLIER S ON T.SUP_ID = S.SUP_ID
        LEFT JOIN MasterBiayaAP B ON T.id_biaya = B.id_biaya
        LEFT JOIN MasterKategoriAP K ON T.id_supplier_cat = K.id_supplier
        WHERE T.is_paid = 0 " . $where_add . "
        ORDER BY T.due_date ASC";

$query = sqlsrv_query($conn, $sql, $params);
if ($query === false) { die(print_r(sqlsrv_errors(), true)); }
?>

<!DOCTYPE html>
<html>
<head>
    <title>Export Data AP</title>
</head>
<body>
    <h3>DATA AGING AP (HUTANG SUPPLIER)</h3>
    <table border="1">
        <thead>
            <tr style="background-color:#f2f2f2;">
                <th>No.</th>
                <th>Supplier Name</th>
                <th>Tgl Invoice</th>
                <th>No. Invoice</th>
                <th>Faktur Pajak</th>
                <th>Mata Uang</th>
                <th>Total Amount</th>
                <th>Jatuh Tempo</th>
                <th>Biaya (AP)</th>
                <th>Kategori</th>
                <th>Deskripsi</th> <th>Status</th>
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
                <td><?= htmlspecialchars($row['SUP_COMP']) ?></td>
                <td align="center"><?= $tgl_inv ?></td>
                
                <td style="mso-number-format:'\@';"><?= htmlspecialchars($row['invoice_number']) ?></td>
                <td style="mso-number-format:'\@';"><?= htmlspecialchars($row['faktur_pajak']) ?></td>
                
                <td align="center"><?= $row['curr_code'] ?></td>
                <td align="right"><?= number_format($row['amount'], 2) ?></td>
                <td align="center"><?= $tgl_due ?></td>
                <td><?= htmlspecialchars($row['AccountName']) ?></td>
                <td><?= htmlspecialchars($row['NamaKategori']) ?></td>
                <td><?= htmlspecialchars($row['deskripsi']) ?></td>
                <td align="center">
                    <?= ($sisa <= 0) ? "OVERDUE" : $sisa . " Hari lagi" ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
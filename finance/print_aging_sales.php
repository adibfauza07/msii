<?php
// print_aging_sales.php
session_start();
require_once '../config/database_aging.php';

$sql = "SELECT T.id_sales, S.CUST_COMP, T.invoice_date, T.invoice_number, 
               T.faktur_pajak, T.curr_code, T.amount, T.due_date, B.AccountName, 
               K.SalesName, DATEDIFF(day, GETDATE(), T.due_date) as sisa_hari
        FROM TRANS_SALES T
        LEFT JOIN CUST S ON T.CUST_ID = S.CUST_ID
        LEFT JOIN MasterBiayaSales B ON T.id_biaya = B.id_biaya
        LEFT JOIN MasterKategoriSales K ON T.id_kategori_sales = K.id_kategori_sales
        WHERE T.is_paid = 0
        ORDER BY S.CUST_COMP ASC, T.due_date ASC"; // Sort by Customer biar rapi

$query = sqlsrv_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Aging Sales</title>
    <style>
        /* CSS KHUSUS CETAK */
        body { font-family: Arial, sans-serif; font-size: 12px; -webkit-print-color-adjust: exact; }
        h2, h4 { text-align: center; margin: 5px 0; }
        hr { border: 1px solid #000; margin-bottom: 20px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; }
        th { background-color: #ddd; text-align: center; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .text-danger { color: red !important; font-weight: bold; }
        
        /* Set Kertas Landscape saat Print */
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom:20px;">
        <button onclick="window.print()" style="padding:10px 20px; font-weight:bold; cursor:pointer;">
            KLIK DISINI UNTUK CETAK / SIMPAN PDF
        </button>
    </div>

    <h2>LAPORAN AGING SALES (PIUTANG)</h2>
    <h4>Per Tanggal: <?= date('d F Y H:i') ?></h4>
    <h4>Lokasi: <?= ($_SESSION['active_plant'] == 'p2') ? 'PLANT 2' : 'PLANT 1' ?></h4>
    <hr>

    <table>
        <thead>
            <tr>
                <th width="5%">No.</th>
                <th width="20%">Customer</th>
                <th width="10%">Tgl Inv</th>
                <th width="15%">No. Invoice</th>
                <th width="15%">Faktur Pajak</th>
                <th width="5%">Curr</th>
                <th width="12%">Amount</th>
                <th width="10%">Due Date</th>
                <th width="8%">Sisa Hari</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $totalIDR = 0;
            $totalUSD = 0;

            while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): 
                $sisa = (int)$row['sisa_hari'];
                
                // Hitung Total Sederhana
                if(trim($row['curr_code']) == 'USD') {
                    $totalUSD += $row['amount'];
                } else {
                    $totalIDR += $row['amount'];
                }
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td><?= htmlspecialchars($row['CUST_COMP']) ?></td>
                <td class="text-center"><?= ($row['invoice_date']) ? $row['invoice_date']->format('d/m/Y') : '-' ?></td>
                <td><?= htmlspecialchars($row['invoice_number']) ?></td>
                <td><?= htmlspecialchars($row['faktur_pajak']) ?></td>
                <td class="text-center"><?= $row['curr_code'] ?></td>
                <td class="text-end"><?= number_format($row['amount'], 2) ?></td>
                <td class="text-center"><?= ($row['due_date']) ? $row['due_date']->format('d/m/Y') : '-' ?></td>
                <td class="text-center <?= ($sisa <= 0) ? 'text-danger' : '' ?>">
                    <?= ($sisa <= 0) ? "Overdue" : $sisa ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr style="background:#eee; font-weight:bold;">
                <td colspan="6" class="text-center">GRAND TOTAL</td>
                <td class="text-end">
                    IDR: <?= number_format($totalIDR, 2) ?><br>
                    USD: <?= number_format($totalUSD, 2) ?>
                </td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <script>
        // Opsional: Otomatis muncul dialog print saat halaman dibuka
        window.onload = function() { window.print(); }
    </script>
</body>
</html>
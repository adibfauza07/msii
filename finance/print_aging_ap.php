<?php
// Matikan error reporting bawaan biar tidak bikin bingung, kita handle manual
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database_aging.php';

// 1. CEK KONEKSI
if ($conn === false) {
    die("<h3 style='color:red'>Koneksi Database Gagal!</h3><pre>" . print_r(sqlsrv_errors(), true) . "</pre>");
}

// 2. CEK APAKAH TABEL ADA DI SERVER INI?
// (Sering terjadi: Tabel dibuat di Server .9 tapi kita sedang login ke Server .4)
$checkTable = sqlsrv_query($conn, "SELECT TOP 1 * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'TRANS_AP'");
if (!sqlsrv_has_rows($checkTable)) {
    die("<div style='background:red; color:white; padding:20px; text-align:center;'>
            <h3>CRITICAL ERROR: Tabel Tidak Ditemukan!</h3>
            <p>Tabel <b>TRANS_AP</b> tidak ditemukan di server: <b>$serverName</b>.</p>
            <p>Pastikan Anda sudah menjalankan Script SQL Create Table di server ini, atau pilih Plant yang benar saat Login.</p>
         </div>");
}

// 3. FUNGSI AMAN FORMAT TANGGAL
function format_date_aman($dateObj) {
    if ($dateObj instanceof DateTime) {
        return $dateObj->format('d/m/Y');
    } elseif (is_string($dateObj)) {
        // Jika driver mengembalikan string (misal: "2023-10-01")
        return date('d/m/Y', strtotime($dateObj));
    }
    return '-'; // Jika null
}

// 4. QUERY UTAMA
$sql = "SELECT T.id_ap, 
               S.SUP_COMP, 
               T.invoice_date, T.invoice_number, 
               T.faktur_pajak, T.curr_code, T.amount, T.due_date, 
               B.AccountName, 
               K.SupplierName as NamaKategori, 
               DATEDIFF(day, GETDATE(), T.due_date) as sisa_hari
        FROM TRANS_AP T
        LEFT JOIN SUPPLIER S ON T.SUP_ID = S.SUP_ID
        LEFT JOIN MasterBiayaAP B ON T.id_biaya = B.id_biaya
        LEFT JOIN MasterKategoriAP K ON T.id_supplier_cat = K.id_supplier 
        WHERE (T.is_paid = 0 OR T.is_paid IS NULL)
        ORDER BY S.SUP_COMP ASC, T.due_date ASC";

$query = sqlsrv_query($conn, $sql);

// 5. TANGKAP ERROR SQL JIKA QUERY GAGAL
if ($query === false) {
    echo "<div style='border:2px solid red; padding:15px; background:#ffe6e6; font-family:monospace;'>";
    echo "<h3 style='color:red; margin-top:0;'>SQL QUERY ERROR</h3>";
    echo "Query gagal dijalankan. Berikut detailnya:<br><br>";
    if (($errors = sqlsrv_errors()) != null) {
        foreach ($errors as $error) {
            echo "<b>SQLSTATE:</b> " . $error['SQLSTATE'] . "<br>";
            echo "<b>Code:</b> " . $error['code'] . "<br>";
            echo "<b>Message:</b> <span style='color:red'>" . $error['message'] . "</span><br><br>";
        }
    }
    echo "</div>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Aging AP</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; }
        .container { max-width: 100%; margin: 20px auto; }
        h2, h4 { text-align: center; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #444; padding: 6px 8px; vertical-align: middle; }
        th { background-color: #e0e0e0; text-align: center; font-weight: bold; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .text-danger { color: #dc3545; font-weight: bold; }
        
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            .no-print { display: none; }
        }
        .btn-print { background: #0d6efd; color: white; border: none; padding: 8px 20px; cursor: pointer; border-radius: 4px; }
    </style>
</head>
<body>

    <div class="no-print" style="text-align:center; padding:15px; background:#f8f9fa; border-bottom:1px solid #ddd;">
        <button class="btn-print" onclick="window.print()">CETAK / SIMPAN PDF</button>
        <div style="margin-top:5px; font-size:11px; color:#666;">
            Server: <b><?= $serverName ?></b> | User: <b><?= $uid ?></b>
        </div>
    </div>

    <div class="container">
        <h2>LAPORAN AGING AP (HUTANG SUPPLIER)</h2>
        <h4>Per Tanggal: <?= date('d F Y H:i') ?></h4>
        <h4>Lokasi: <?= ($_SESSION['active_plant'] == 'p2') ? 'PLANT 2' : 'PLANT 1' ?></h4>
        <hr>

        <table>
            <thead>
                <tr>
                    <th width="4%">No.</th>
                    <th width="20%">Supplier Name</th>
                    <th width="8%">Tgl Inv.</th>
                    <th width="12%">No. Invoice</th>
                    <th width="12%">Faktur Pajak</th>
                    <th width="5%">Curr</th>
                    <th width="10%">Amount</th>
                    <th width="8%">Due Date</th>

                    <th width="9%">Sisa Hari</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                $totalIDR = 0; $totalUSD = 0;

                while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): 
                    $sisa = isset($row['sisa_hari']) ? (int)$row['sisa_hari'] : 0;
                    
                    if(trim($row['curr_code']) == 'USD') { $totalUSD += $row['amount']; } 
                    else { $totalIDR += $row['amount']; }

                    $statusClass = ($sisa <= 0) ? "text-danger" : "";
                    $statusText = ($sisa <= 0) ? "OVERDUE (" . abs($sisa) . ")" : $sisa . " Hari";
                ?>
                <tr>
<td class="text-center"><?= $no++ ?></td>
                <td><?= htmlspecialchars($row['SUP_COMP']) ?></td>
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
                
                <?php if($no == 1): ?>
                <tr>
                    <td colspan="10" class="text-center" style="padding:20px; font-style:italic; color:#777;">
                        Data Kosong (Tidak ada hutang yang belum lunas di database)
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f8f9fa;">
                    <td colspan="6" class="text-end" style="font-weight:bold;">GRAND TOTAL :</td>
                    <td class="text-end" style="font-weight:bold;">
                        IDR <?= number_format($totalIDR, 2) ?><br>
                        USD <?= number_format($totalUSD, 2) ?>
                    </td>
                    <td colspan="3">
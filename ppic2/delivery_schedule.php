<?php
require_once __DIR__ . "/../config/database_ppic.php";

 $item_code = isset($_GET['item_code']) ? $_GET['item_code'] : '';
 $from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
 $to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-t');

// PERBAIKAN: Gabungkan tabel ITEMS untuk mencocokkan ITEM_CODE
 $tsql = "
    SELECT d.DELS_DATE, d.DELS_QTY 
    FROM dbo.DELI_SCH d
    INNER JOIN dbo.PRICE pr ON d.PRICE_ID = pr.PRICE_ID
    INNER JOIN dbo.ITEMS i ON pr.PART_ID = i.ITEM_ID
    WHERE i.ITEM_CODE = ?
      AND ISDATE(d.DELS_DATE) = 1
      AND d.DELS_DATE >= ? AND d.DELS_DATE <= ?
    ORDER BY d.DELS_DATE ASC
";
 $params = array($item_code, $from_date, $to_date);
 $stmt = sqlsrv_query($conn, $tsql, $params);

if ($stmt === false) {
    die("Error Query: " . print_r(sqlsrv_errors(), true));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Delivery Schedule - <?= htmlspecialchars($item_code) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { margin-top: 0; color: #333; }
        table { width: 40%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #007bff; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .no-data { color: red; font-style: italic; }
        button { padding: 8px 15px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #c82333; }
        /* Style untuk baris total */
        tr.total-row { 
            background-color: #fff3cd; 
            font-weight: bold; 
        }
        tr.total-row td { 
            border-top: 2px solid #333; 
            font-size: 14px; 
        }
    </style>
</head>
<body>
    <h2>Delivery Schedule: <?= htmlspecialchars($item_code) ?></h2>
    <p><strong>Periode:</strong> <?= date('d-M-Y', strtotime($from_date)) ?> s/d <?= date('d-M-Y', strtotime($to_date)) ?></p>

    <table border="1">
        <tr>
            <th style="width: 50%;">Tanggal</th>
            <th style="width: 50%; text-align: right;">Qty</th>
        </tr>
        <?php 
        $has_data = false;
        $total_qty = 0;  // Inisialisasi variabel total
        
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { 
            $has_data = true;
            $tgl = $row['DELS_DATE'] instanceof DateTime ? $row['DELS_DATE']->format('d-M-Y') : $row['DELS_DATE'];
            $qty = (float)$row['DELS_QTY'];  // Pastikan numeric
            $total_qty += $qty;  // Akumulasi total
        ?>
            <tr>
                <td><?= $tgl ?></td>
                <td style="text-align: right;"><?= number_format($qty) ?></td>
            </tr>
        <?php } 
        
        if (!$has_data) {
            echo "<tr><td colspan='2' class='no-data' style='text-align:center;'>Tidak ada jadwal pengiriman untuk periode ini.</td></tr>";
        } else {
            // Tampilkan baris total jika ada data
        ?>
            <tr class="total-row">
                <td><strong>TOTAL</strong></td>
                <td style="text-align: right;"><strong><?= number_format($total_qty) ?></strong></td>
            </tr>
        <?php
        }
        ?>
    </table>
    
    <br>
    <button onclick="window.close()">Tutup</button>
</body>
</html>
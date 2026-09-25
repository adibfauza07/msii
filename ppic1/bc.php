<?php
// bc.php
$configPath = __DIR__ . "/../config/global.php"; 
if (file_exists($configPath)) { require_once $configPath; }
if (session_status() == PHP_SESSION_NONE) { session_start(); }

/* 
 * Koneksi menggunakan sqlsrv_connect() dari global.php yang mengembalikan 'resource'.
 * Diasumsikan variabel koneksi adalah $conn. Jika di global.php namanya $koneksi, 
 * ubah variabel $conn di bawah (pada baris sqlsrv_query) menjadi $koneksi.
 */

// Menangkap parameter 
$itemCode = isset($_GET['item']) ? trim($_GET['item']) : '';
$bcDate   = isset($_GET['date']) ? trim($_GET['date']) : '';

if (empty($itemCode) || empty($bcDate)) {
    die("<div style='padding:15px; color:red; font-family:sans-serif;'>Error: Parameter Item Code dan Tanggal tidak boleh kosong.</div>");
}

// Query menggunakan SUM dan GROUP BY untuk menggabungkan data yang sama
$sql = "SELECT 
            dbo.BC_TRANS.BC_DATE, 
            dbo.RECEIVE_DETAIL.ITEM_ID, 
            SUM(dbo.RECEIVE_DETAIL.RCVD_QTY) AS RCVD_QTY,
            dbo.ITEMS.ITEM_CODE, 
            dbo.ITEMS.ITEM_NAME, 
            dbo.BC_TRANS.JENIS_BC, 
            dbo.BC_TRANS.NOMOR_BC, 
            dbo.SUPPLIER.SUP_CODE, 
            dbo.SUPPLIER.SUP_COMP, 
            dbo.PO.PO_DATE, 
            dbo.PO.PO_NUM
        FROM dbo.RECEIVE 
        INNER JOIN dbo.RECEIVE_DETAIL ON dbo.RECEIVE.RCV_ID = dbo.RECEIVE_DETAIL.RCV_ID 
        INNER JOIN dbo.SUPPLIER ON dbo.RECEIVE.SUP_ID = dbo.SUPPLIER.SUP_ID 
        INNER JOIN dbo.ITEMS ON dbo.RECEIVE_DETAIL.ITEM_ID = dbo.ITEMS.ITEM_ID 
        INNER JOIN dbo.PO ON dbo.RECEIVE_DETAIL.PO_ID = dbo.PO.PO_ID 
        LEFT OUTER JOIN dbo.BC_TRANS ON dbo.RECEIVE.RCV_NO = dbo.BC_TRANS.NO_TRANS
        WHERE (dbo.ITEMS.ITEM_CODE = ?) 
        AND (CAST(dbo.BC_TRANS.BC_DATE AS DATE) = CAST(? AS DATE))
        GROUP BY 
            dbo.BC_TRANS.BC_DATE, 
            dbo.RECEIVE_DETAIL.ITEM_ID, 
            dbo.ITEMS.ITEM_CODE, 
            dbo.ITEMS.ITEM_NAME, 
            dbo.BC_TRANS.JENIS_BC, 
            dbo.BC_TRANS.NOMOR_BC, 
            dbo.SUPPLIER.SUP_CODE, 
            dbo.SUPPLIER.SUP_COMP, 
            dbo.PO.PO_DATE, 
            dbo.PO.PO_NUM";

$params = array($itemCode, $bcDate);
$results = array();
$totalQty = 0; // Variabel untuk menghitung total kuantitas

// Eksekusi menggunakan fungsi bawaan sqlsrv
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    // Tangkap error spesifik sqlsrv jika query gagal
    $errors = sqlsrv_errors();
    $errorMsg = isset($errors[0]['message']) ? $errors[0]['message'] : 'Unknown SQL error';
    die("<div style='padding:15px; color:red;'>Query error: " . htmlspecialchars($errorMsg) . "</div>");
}

// Fetch hasil ke dalam array dan hitung total
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $results[] = $row;
    $totalQty += $row['RCVD_QTY']; // Tambahkan qty setiap baris ke total
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail BC & PO (<?php echo htmlspecialchars($itemCode); ?>)</title>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; font-size: 12px; background-color: #f0f2f5; padding: 20px; color: #333; }
        .panel { background-color: #fff; border: 1px solid #dcdcdc; border-radius: 4px; padding: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .title { font-size: 14px; font-weight: 700; color: #0056b3; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #eee; }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        th, td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
        th { background-color: #000080; color: white; font-weight: 600; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        tr:hover { background-color: #e6f7ff; }
        tfoot td { font-weight: 700; background-color: #e9ecef; }
        .no-data { padding: 15px; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 4px; font-weight: 600; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="panel">
        <div class="title"><i class="fa fa-list-alt"></i> Detail Transaksi: <?php echo htmlspecialchars($itemCode); ?> pada <?php echo htmlspecialchars($bcDate); ?></div>

        <?php if (count($results) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Qty</th>
                        <th>Jenis BC</th>
                        <th>Nomor BC</th>
                        <th>BC Date</th>
                        <th>Supplier</th>
                        <th>PO Number</th>
                        <th>PO Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['ITEM_CODE']); ?></td>
                            <td><?php echo htmlspecialchars($row['ITEM_NAME']); ?></td>
                            <td><?php echo number_format($row['RCVD_QTY'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['JENIS_BC']); ?></td>
                            <td><?php echo htmlspecialchars($row['NOMOR_BC']); ?></td>
                            <td>
                                <?php 
                                    $bDate = $row['BC_DATE'] instanceof DateTime ? $row['BC_DATE']->format('Y-m-d') : substr($row['BC_DATE'], 0, 10);
                                    echo htmlspecialchars($bDate); 
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['SUP_COMP'] . ' (' . $row['SUP_CODE'] . ')'); ?></td>
                            <td><?php echo htmlspecialchars($row['PO_NUM']); ?></td>
                            <td>
                                <?php 
                                    $pDate = $row['PO_DATE'] instanceof DateTime ? $row['PO_DATE']->format('Y-m-d') : substr($row['PO_DATE'], 0, 10);
                                    echo htmlspecialchars($pDate); 
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="text-right"><strong>TOTAL QTY:</strong></td>
                        <td colspan="7"><strong><?php echo number_format($totalQty, 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        <?php else: ?>
            <div class="no-data"><i class="fa fa-info-circle"></i> Tidak ada dokumen BC/PO yang ditemukan untuk item dan tanggal tersebut.</div>
        <?php endif; ?>
    </div>
</body>
</html>
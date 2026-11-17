<?php
if (session_id() == "") {
    session_start();
}

// Memanggil koneksi database
require_once dirname(__DIR__) . "/config/global.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

// Ambil parameter tanggal dari URL (jika ada), default awal bulan ini sampai hari ini
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
$end_date   = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');

$results = array();
$error_message = '';

// Siapkan query eksekusi SP dengan parameter (?)
$sql = "EXEC sp_GetScheduleReceiveReportBySupplier @StartDate = ?, @EndDate = ?";
$params = array($start_date, $end_date);

// Eksekusi menggunakan sqlsrv_query
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    // Tangkap error jika SP gagal dieksekusi
    $error_message = "Query Error: " . print_r(sqlsrv_errors(), true);
} else {
    // Fetch data jika berhasil
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Schedule Receive Report by Supplier</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; color: #333; margin: 20px; }
        h2 { margin-bottom: 5px; }
        .filter-form { margin-bottom: 20px; padding: 15px; background: #f4f4f4; border-radius: 5px; }
        .filter-form label { margin-right: 10px; font-weight: bold; }
        .filter-form input, .filter-form button { padding: 6px 10px; margin-right: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 8px 10px; text-align: left; }
        th { background-color: #2c3e50; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .error { color: red; font-weight: bold; }
        .text-right { text-align: right; }
    </style>
</head>
<body>

    <h2>Schedule Receive Report (By Supplier)</h2>
    <p>Periode Tanggal Penerimaan</p>

    <!-- Form Filter Date to Date -->
    <div class="filter-form">
        <form method="GET" action="">
            <label for="start_date">Dari Tanggal:</label>
            <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
            
            <label for="end_date">Sampai Tanggal:</label>
            <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
            
            <button type="submit">Filter Report</button>
        </form>
    </div>

    <?php if (!empty($error_message)): ?>
        <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>

    <!-- Tabel Hasil Report -->
    <table>
        <thead>
            <tr>
                <th>Sup Code</th>
                <th>Supplier Name (Company)</th>
                <th>RCV No</th>
                <th>RCV Date</th>
                <th>DO No</th>
                <th>PO Num</th>
                <th>Item Code</th>
                <th>Item Name</th>
                <th class="text-right">Total Qty</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($results)): ?>
                <?php foreach ($results as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['SUP_CODE']); ?></td>
                        <td><?php echo htmlspecialchars($row['SUP_COMP']); ?></td>
                        <td><?php echo htmlspecialchars($row['RCV_NO']); ?></td>
                        <?php 
                            // Format tanggal objek DateTime dari SQL Server
                            $rcvDateFormated = "";
                            if ($row['RCV_DATE'] instanceof DateTime) {
                                $rcvDateFormated = $row['RCV_DATE']->format("Y-m-d");
                            } else {
                                $rcvDateFormated = $row['RCV_DATE'];
                            }
                        ?>
                        <td><?php echo htmlspecialchars($rcvDateFormated); ?></td>
                        <td><?php echo htmlspecialchars($row['RCV_DONO']); ?></td>
                        <td><?php echo htmlspecialchars($row['PO_NUM']); ?></td>
                        <td><?php echo htmlspecialchars($row['ITEM_CODE']); ?></td>
                        <td><?php echo htmlspecialchars($row['ITEM_NAME']); ?></td>
                        <td class="text-right"><?php echo htmlspecialchars(number_format((float)$row['TOTAL_RCVD_QTY'], 2, '.', ',')); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" style="text-align: center;">Tidak ada data untuk periode tersebut.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
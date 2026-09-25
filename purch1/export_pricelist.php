<?php
// Include koneksi database Anda
require_once dirname(__DIR__) . "/config/db_plant1.php";

// Tangkap filter dari URL (dikirim dari matpricelist.php)
$sup_code = isset($_GET['sup_code']) ? trim($_GET['sup_code']) : '';

// Susun parameter dan query
$params = array();
$whereClause = " WHERE (dbo.SUP_ITEM_QUO.SUP_CODE IS NOT NULL) ";

if (!empty($sup_code)) {
    $whereClause .= " AND dbo.SUP_ITEM_QUO.SUP_CODE = ? ";
    $params[] = $sup_code;
}

$sql = "SELECT TOP (100) PERCENT 
            dbo.SUP_ITEM_QUO.SUP_CODE, 
            dbo.SUP_ITEM_QUO.SUP_COMP, 
            dbo.SUP_ITEM_QUO.ITEM_CODE, 
            dbo.SUP_ITEM_QUO.ITEM_NAME, 
            dbo.SUP_ITEM_QUO.ITTY_CODE, 
            dbo.ITTY.ITTY_DESC, 
            dbo.SUP_ITEM_QUO.CURR_CODE, 
            dbo.QUOT_DETAIL.QUOD_PRICE, 
            dbo.QUOT_DETAIL.QUOD_UNIT, 
            dbo.QUOT_DETAIL.QUOD_MINQTY, 
            dbo.QUOTATION.QUO_NO, 
            dbo.QUOTATION.QUO_DATE, 
            dbo.QUOTATION.QUO_EFFDATE
        FROM dbo.SUP_ITEM_QUO 
        INNER JOIN dbo.ITTY ON dbo.SUP_ITEM_QUO.ITTY_CODE = dbo.ITTY.ITTY_CODE 
        LEFT OUTER JOIN dbo.QUOT_DETAIL ON dbo.SUP_ITEM_QUO.QUO_ID = dbo.QUOT_DETAIL.QUO_ID AND dbo.SUP_ITEM_QUO.ITEM_ID = dbo.QUOT_DETAIL.ITEM_ID 
        LEFT OUTER JOIN dbo.QUOTATION ON dbo.SUP_ITEM_QUO.QUO_ID = dbo.QUOTATION.QUO_ID
        $whereClause
        ORDER BY dbo.SUP_ITEM_QUO.SUP_CODE, dbo.SUP_ITEM_QUO.ITTY_CODE, dbo.SUP_ITEM_QUO.ITEM_CODE";

// Eksekusi query
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("Error dalam mengeksekusi query. Pastikan koneksi database stabil.");
}

// Set Header HTTP agar terbaca sebagai file Excel (.xls) saat didownload
$filename = "General_Pricelist_" . (!empty($sup_code) ? $sup_code . "_" : "All_") . date('Ymd_His') . ".xls";
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <!-- Style opsional agar ada border saat dibuka di Excel -->
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000000; padding: 5px; font-family: Arial, sans-serif; font-size: 11pt; }
        th { background-color: #d9edf7; font-weight: bold; }
        .num { text-align: right; }
    </style>
</head>
<body>

    <table>
        <thead>
            <tr>
                <!-- Header tabel dibuat lengkap agar mudah di-filter di Excel -->
                <th>Supplier Code</th>
                <th>Supplier Name</th>
                <th>Category Code</th>
                <th>Category Desc</th>
                <th>Item Code</th>
                <th>Item Name</th>
                <th>Price</th>
                <th>Currency</th>
                <th>Unit</th>
                <th>Min Qty</th>
                <th>Quot. NO</th>
                <th>Quot. Date</th>
                <th>Effect. Date</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                <?php
                    // Format Angka Harga
                    $price = is_numeric($row['QUOD_PRICE']) ? number_format($row['QUOD_PRICE'], 5, '.', '') : '0.00000';
                    
                    // Format Tanggal
                    $quo_date = '';
                    if (!empty($row['QUO_DATE'])) {
                        $quo_date = is_object($row['QUO_DATE']) ? $row['QUO_DATE']->format('d-M-Y') : date('d-M-Y', strtotime($row['QUO_DATE']));
                    }

                    $eff_date = '';
                    if (!empty($row['QUO_EFFDATE'])) {
                        $eff_date = is_object($row['QUO_EFFDATE']) ? $row['QUO_EFFDATE']->format('d-M-Y') : date('d-M-Y', strtotime($row['QUO_EFFDATE']));
                    }
                ?>
                <tr>
                    <!-- Penambahan spasi (&nbsp;) di depan kode/teks agar Excel tidak menganggapnya sebagai rumus/angka murni -->
                    <td>&nbsp;<?= htmlspecialchars($row['SUP_CODE']) ?></td>
                    <td><?= htmlspecialchars($row['SUP_COMP']) ?></td>
                    <td>&nbsp;<?= htmlspecialchars($row['ITTY_CODE']) ?></td>
                    <td><?= htmlspecialchars($row['ITTY_DESC']) ?></td>
                    <td>&nbsp;<?= htmlspecialchars($row['ITEM_CODE']) ?></td>
                    <td><?= htmlspecialchars($row['ITEM_NAME']) ?></td>
                    
                    <!-- Harga menggunakan format standar agar bisa dijumlahkan jika perlu -->
                    <td class="num"><?= $price ?></td>
                    <td><?= htmlspecialchars($row['CURR_CODE']) ?></td>
                    <td><?= htmlspecialchars($row['QUOD_UNIT']) ?></td>
                    <td class="num"><?= htmlspecialchars($row['QUOD_MINQTY']) ?></td>
                    <td>&nbsp;<?= htmlspecialchars($row['QUO_NO']) ?></td>
                    <td><?= $quo_date ?></td>
                    <td><?= $eff_date ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

<?php sqlsrv_free_stmt($stmt); ?>
</body>
</html>
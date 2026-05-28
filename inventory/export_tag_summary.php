<?php
require_once __DIR__ . '/../config/database_p1.php';

$sopId = isset($_GET['sop_id']) ? (int)$_GET['sop_id'] : 0;
if ($sopId == 0) die("Error: ID SOP tidak ditemukan.");

// 1. Ambil Header SOP untuk Judul Excel
$sqlHeader = "SELECT SOP_REF, SOP_SDATE FROM dbo.SOP WHERE SOP_ID = ?";
$stmtH = sqlsrv_query($conn, $sqlHeader, array($sopId));
$header = ($stmtH) ? sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC) : null;
$sopRef = ($header && isset($header['SOP_REF'])) ? $header['SOP_REF'] : "SOP-$sopId";

// 2. Set Header agar Browser mendownload file sebagai Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Tag_Summary_$sopRef.xls");
header("Pragma: no-cache");
header("Expires: 0");

// 3. Eksekusi Stored Procedure
$sql = "EXEC RPT_TAGSUM_BY_ITEM @SOP_ID = ?";
$stmt = sqlsrv_query($conn, $sql, array($sopId));
?>

<table border="1">
    <thead>
        <tr style="background-color: #cccccc;">
            <th>LOC NAME</th>
            <th>ITTY DESC</th>
            <th>ITEM CODE</th>
            <th>ITEM NAME</th>
            <th>QTY OK1</th>
            <th>QTY OK2</th>
            <th>QTY HOLD</th>
            <th>TOTAL QTY</th>
            <th>CURR</th>
            <th>PRICE</th>
            <th>USD AMOUNT</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
            <tr>
                <td><?php echo $row['LOC_NAME']; ?></td>
                <td><?php echo $row['ITTY_DESC']; ?></td>
                <td><?php echo $row['ITEM_CODE']; ?></td>
                <td><?php echo $row['ITEM_NAME']; ?></td>
                <td><?php echo number_format($row['TOK1'], 0); ?></td>
                <td><?php echo number_format($row['TOK2'], 0); ?></td>
                <td><?php echo number_format($row['THOLD'], 0); ?></td>
                <td><?php echo number_format($row['TQTY'], 0); ?></td>
                <td><?php echo $row['ITEM_CUR']; ?></td>
                <td><?php echo number_format($row['PRICE'], 4); ?></td>
                <td><?php echo number_format($row['USDAMOUNT'], 2); ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
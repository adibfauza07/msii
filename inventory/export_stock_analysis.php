<?php
require_once __DIR__ . '/../config/database_p1.php';

// 1. Tangkap Parameter
$itemId    = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$period    = isset($_GET['period']) ? (int)$_GET['period'] : 1;
$startDateFormatted = date('Ymd', strtotime($startDate));

// 2. Set Header untuk Export Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Stock_Analysis_Item_" . $itemId . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// 3. Eksekusi Stored Procedure
$sql = "EXEC sp_StockAnalysis4 @STARTDATE = ?, @PERIOD = ?, @ITEM_ID = ?";
$params = array($startDateFormatted, $period, $itemId);
$stmt = sqlsrv_query($conn, $sql, $params);
?>

<table border="1">
    <thead>
        <tr style="background-color: #cccccc;">
            <th>DATE</th>
            <th>LOC GROUP</th>
            <th>LOCATION</th>
            <th>ITEM CODE</th>
            <th>ITEM NAME</th>
            <th>DOC NO</th>
            <th>TYPE</th>
            <th>IN (TIN)</th>
            <th>OUT (TOUT)</th>
            <th>BALANCE</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): 
            $date = ($row['TRAN_DATE'] instanceof DateTime) ? $row['TRAN_DATE']->format('d-M-Y') : $row['TRAN_DATE'];
        ?>
            <tr>
                <td><?php echo $date; ?></td>
                <td><?php echo $row['LOC_GROUP']; ?></td>
                <td><?php echo $row['LOC_NAME']; ?></td>
                <td><?php echo $row['ITEM_CODE']; ?></td>
                <td><?php echo $row['ITEM_NAME']; ?></td>
                <td><?php echo $row['TRAN_DOC']; ?></td>
                <td><?php echo $row['TRTY_DESC']; ?></td>
                <td><?php echo number_format($row['TIN'], 2); ?></td>
                <td><?php echo number_format($row['TOUT'], 2); ?></td>
                <td><?php echo number_format($row['BAL_QTY'], 2); ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
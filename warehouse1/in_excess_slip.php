<?php
require_once __DIR__ . "/../config/global.php"; //[cite: 1]
$tranid = isset($_GET['tranid']) ? $_GET['tranid'] : 0;

// Query Header[cite: 5, 7]
$sqlHeader = "SELECT TRANS.*, TRTY.TRTY_DESC FROM TRANS LEFT JOIN TRTY ON TRTY.TRTY_CODE=TRANS.TRTY_CODE WHERE TRAN_ID = ?";
$stmtHeader = sqlsrv_query($conn, $sqlHeader, array($tranid));
$header = sqlsrv_fetch_array($stmtHeader, SQLSRV_FETCH_ASSOC);

// Query Detail Grouping[cite: 5, 7]
$sqlDetail = "SELECT ITEMS.ITEM_CODE, ITEMS.ITEM_NAME, ITEMS.ITEM_UNIT, SUM(INV_TRAN.IT_QTY) AS IT_QTY FROM INV_TRAN INNER JOIN ITEMS ON ITEMS.ITEM_ID=INV_TRAN.ITEM_ID WHERE INV_TRAN.TRAN_ID = ? GROUP BY INV_TRAN.ITEM_ID, ITEMS.ITEM_CODE, ITEMS.ITEM_NAME, ITEMS.ITEM_UNIT";
$stmtDetail = sqlsrv_query($conn, $sqlDetail, array($tranid));
?>
<html>
<head>
    <title>Incoming Material/Part Slip</title>
    <!-- CSS Cetak[cite: 7] (disingkat untuk keringkasan) -->
</head>
<body onload="window.print()">
    <center>
        <h3>MATERIAL/PART SLIP</h3>
        <p>No: <?=$header['TRAN_DOC']?> | Date: <?=date('d-M-Y', strtotime($header['TRAN_ADATE']))?></p>
        <table border="1" width="100%">
            <tr><th>KODE</th><th>NAMA</th><th>SATUAN</th><th>JUMLAH</th></tr>
            <?php 
            $sub = 0;
            while($row = sqlsrv_fetch_array($stmtDetail, SQLSRV_FETCH_ASSOC)) { 
                $sub += $row['IT_QTY'];
            ?>
            <tr>
                <td><?=$row['ITEM_CODE']?></td>
                <td><?=$row['ITEM_NAME']?></td>
                <td><?=$row['ITEM_UNIT']?></td>
                <td><?=number_format($row['IT_QTY'], 2)?></td>
            </tr>
            <?php } ?>
            <tr><td colspan="3" align="right">TOTAL</td><td><?=number_format($sub, 2)?></td></tr>
        </table>
    </center>
</body>
</html>
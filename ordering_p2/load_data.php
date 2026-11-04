<?php
require_once __DIR__ . "/../config/database_ordering.php";

$type = isset($_GET['type']) ? $_GET['type'] : '';

if ($type == 'staging') {
    $sql = "SELECT ORDR_PO, ITEM_NO, ITEM_CODE, CONVERT(varchar, ORDR_DATE, 23) AS ORDR_DATE, ORDP_QTY, ORDR_CURR, ORDP_PRICE, CONVERT(varchar, ORDR_START, 23) AS ORDR_START, CONVERT(varchar, ORDR_END, 23) AS ORDR_END, IMPORT_STATUS, ERROR_MSG FROM ORDR_IMPORT_XLS ORDER BY IMP_ID";
    $stmt = sqlsrv_query($conn, $sql);
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $bgClass = '';
        if ($row['IMPORT_STATUS'] == 'E') {
            $bgClass = 'class="table-danger" title="Gagal / Sudah Ada"';
        } elseif ($row['IMPORT_STATUS'] == 'Y') {
            $bgClass = 'class="table-success" title="Berhasil Upload"';
        }

        echo "<tr $bgClass>
            <td>
                <a href='javascript:void(0);' onclick='loadCekPO(\"{$row['ORDR_PO']}\")' style='font-weight:bold; color:#0056b3; text-decoration:underline;'>
                    <i class='fas fa-link'></i> {$row['ORDR_PO']}
                </a>
            </td>
            <td>{$row['ITEM_NO']}</td>
            <td>{$row['ITEM_CODE']}</td>
            <td>{$row['ORDR_DATE']}</td>
            <td>{$row['ORDP_QTY']}</td>
            <td>{$row['ORDR_CURR']}</td>
            <td>{$row['ORDP_PRICE']}</td>
            <td>{$row['ORDR_START']}</td>
            <td>{$row['ORDR_END']}</td>
            <td>{$row['IMPORT_STATUS']}</td>
            <td>{$row['ERROR_MSG']}</td>
        </tr>";
    }
} 
elseif ($type == 'cek_po') {
    $po_filter = isset($_GET['po']) ? trim($_GET['po']) : '';
    
    if ($po_filter != '') {
        // PERBAIKAN 1: Query difilter spesifik untuk 1 PO agar JOIN sangat ringan
        $sql = "SELECT 
                    dbo.ORDERS.ORDR_PO, 
                    CONVERT(varchar, dbo.ORDERS.ORDR_DATE, 23) AS ORDR_DATE, 
                    dbo.ORDERS.ORDR_CURR, 
                    dbo.ITEMS.ITEM_CODE, 
                    dbo.ITEMS.ITEM_NO, 
                    dbo.ITEMS.ITEM_NAME, 
                    dbo.ORDR_PAR.ORDP_QTY, 
                    dbo.ORDR_PAR.ORDP_DQTY, 
                    dbo.ORDR_PAR.ORDP_BQTY 
                FROM dbo.ORDERS 
                INNER JOIN dbo.ORDR_PAR ON dbo.ORDERS.ORDR_ID = dbo.ORDR_PAR.ORDR_ID 
                INNER JOIN dbo.PRICE ON dbo.ORDR_PAR.PRICE_ID = dbo.PRICE.PRICE_ID 
                INNER JOIN dbo.ITEMS ON dbo.PRICE.PART_ID = dbo.ITEMS.ITEM_ID
                WHERE dbo.ORDERS.ORDR_PO = ?";
                
        $params = array($po_filter);
        $stmt = sqlsrv_query($conn, $sql, $params);
    } else {
        // PERBAIKAN 2: Gunakan TOP 100 saat halaman pertama kali diload tanpa filter 
        // agar tidak membebani server dengan JOIN seluruh data
        $sql = "SELECT TOP 100 
                    dbo.ORDERS.ORDR_PO, 
                    CONVERT(varchar, dbo.ORDERS.ORDR_DATE, 23) AS ORDR_DATE, 
                    dbo.ORDERS.ORDR_CURR, 
                    dbo.ITEMS.ITEM_CODE, 
                    dbo.ITEMS.ITEM_NO, 
                    dbo.ITEMS.ITEM_NAME, 
                    dbo.ORDR_PAR.ORDP_QTY, 
                    dbo.ORDR_PAR.ORDP_DQTY, 
                    dbo.ORDR_PAR.ORDP_BQTY 
                FROM dbo.ORDERS 
                INNER JOIN dbo.ORDR_PAR ON dbo.ORDERS.ORDR_ID = dbo.ORDR_PAR.ORDR_ID 
                INNER JOIN dbo.PRICE ON dbo.ORDR_PAR.PRICE_ID = dbo.PRICE.PRICE_ID 
                INNER JOIN dbo.ITEMS ON dbo.PRICE.PART_ID = dbo.ITEMS.ITEM_ID
                ORDER BY dbo.ORDERS.ORDR_PO DESC";
                
        $stmt = sqlsrv_query($conn, $sql);
    }
    
    if ($stmt !== false) {
        $rowCount = 0;
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rowCount++;
            echo "<tr>
                <td>{$row['ORDR_PO']}</td>
                <td>{$row['ORDR_DATE']}</td>
                <td>{$row['ORDR_CURR']}</td>
                <td>{$row['ITEM_CODE']}</td>
                <td>{$row['ITEM_NO']}</td>
                <td>{$row['ITEM_NAME']}</td>
                <td>{$row['ORDP_QTY']}</td>
                <td>{$row['ORDP_DQTY']}</td>
                <td>{$row['ORDP_BQTY']}</td>
            </tr>";
        }
        
        if ($rowCount == 0 && $po_filter != '') {
            echo "<tr><td colspan='9' class='text-center text-danger font-weight-bold'><i class='fas fa-exclamation-triangle'></i> PO {$po_filter} belum masuk ke database Orders.</td></tr>";
        }
    } else {
        $errors = sqlsrv_errors();
        $errMsg = isset($errors[0]['message']) ? $errors[0]['message'] : 'Unknown error';
        echo "<tr><td colspan='9' class='text-center text-danger'>Error SQL: $errMsg</td></tr>";
    }
}
?>
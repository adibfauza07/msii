<?php
require_once __DIR__ . '/../config/database_p1.php';

// 1. Tangkap Parameter SOP_ID
$sopId = isset($_GET['sop_id']) ? (int)$_GET['sop_id'] : 0;
if ($sopId == 0) die("Error: ID SOP tidak ditemukan.");

// 2. Ambil Header SOP untuk nama file Excel
$sqlHeader = "SELECT SOP_REF FROM dbo.SOP WHERE SOP_ID = ?";
$stmtH = sqlsrv_query($conn, $sqlHeader, array($sopId));
$header = ($stmtH) ? sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC) : null;
$sopRef = ($header && isset($header['SOP_REF'])) ? $header['SOP_REF'] : "SOP-" . $sopId;

// 3. Set Header agar Browser mendownload sebagai file Excel (.xls)
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=SOP_Conversion_$sopRef.xls");
header("Pragma: no-cache");
header("Expires: 0");

// 4. Eksekusi Stored Procedure
$sql = "EXEC RPT_TAG_MATERIAL @SOP_ID = ?";
$stmt = sqlsrv_query($conn, $sql, array($sopId));
if ($stmt === false) die(print_r(sqlsrv_errors(), true));
?>

<table border="1">
    <thead>
        <tr style="background-color: #cccccc; font-weight: bold;">
            <th>LOCATION</th>
            <th>PARENT CODE</th>
            <th>PARENT NAME</th>
            <th>PARENT QTY</th>
            <th>PARENT UNIT</th>
            <th>MATERIAL (CHILD) CODE</th>
            <th>MATERIAL (CHILD) NAME</th>
            <th>BOM QTY</th>
            <th>BOM UNIT</th>
            <th>CHILD QTY (KG)</th>
            <th>CURRENCY</th>
            <th>PRICE</th>
            <th>USD RATE</th>
            <th>USD AMOUNT</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        if ($stmt !== false) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                // Ambil nilai dengan proteksi versi PHP lama
                $locName    = isset($row['LOC_NAME']) ? $row['LOC_NAME'] : '';
                $parentCode = isset($row['PARENT_CODE']) ? $row['PARENT_CODE'] : '';
                $parentName = isset($row['PARENT_NAME']) ? $row['PARENT_NAME'] : '';
                $parentQty  = isset($row['PARENT_QTY']) ? $row['PARENT_QTY'] : 0;
                $parentUnit = isset($row['PARENT_UNIT']) ? $row['PARENT_UNIT'] : '';
                
                $childCode  = isset($row['CHILD_CODE']) ? $row['CHILD_CODE'] : '';
                $childName  = isset($row['CHILD_NAME']) ? $row['CHILD_NAME'] : '';
                $bomQty     = isset($row['BOM_QTY']) ? $row['BOM_QTY'] : 0;
                $bomUnit    = isset($row['BOM_UNIT']) ? $row['BOM_UNIT'] : '';
                $childQty   = isset($row['CHILD_QTY']) ? $row['CHILD_QTY'] : 0;
                
                $itemCur    = isset($row['ITEM_CUR']) ? $row['ITEM_CUR'] : '';
                $price      = isset($row['PRICE']) ? $row['PRICE'] : 0;
                $currVrate  = isset($row['CURR_VRATE']) ? $row['CURR_VRATE'] : 0;
                $usRate     = isset($row['USRATE']) ? $row['USRATE'] : 1;
                
                // Hitung Nilai USD Amount konversi
                $usdAmount = ($childQty * $price) * ($currVrate / ($usRate > 0 ? $usRate : 1));
                
                echo "<tr>
                    <td>" . htmlspecialchars($locName) . "</td>
                    <td>" . htmlspecialchars($parentCode) . "</td>
                    <td>" . htmlspecialchars($parentName) . "</td>
                    <td align='right'>" . number_format($parentQty, 2) . "</td>
                    <td align='center'>" . htmlspecialchars($parentUnit) . "</td>
                    <td>" . htmlspecialchars($childCode) . "</td>
                    <td>" . htmlspecialchars($childName) . "</td>
                    <td align='right'>" . number_format($bomQty, 5) . "</td>
                    <td align='center'>" . htmlspecialchars($bomUnit) . "</td>
                    <td align='right'>" . number_format($childQty, 2) . "</td>
                    <td align='center'>" . htmlspecialchars($itemCur) . "</td>
                    <td align='right'>" . number_format($price, 5) . "</td>
                    <td align='right'>" . number_format($currVrate, 0) . "</td>
                    <td align='right'>" . number_format($usdAmount, 2) . "</td>
                </tr>";
            }
        }
        ?>
    </tbody>
</table>
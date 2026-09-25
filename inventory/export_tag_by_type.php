<?php
require_once __DIR__ . '/../config/database_p1.php';

// 1. Ambil ID SOP dari parameter URL
$sopId = isset($_GET['sop_id']) ? (int)$_GET['sop_id'] : 0;
if ($sopId == 0) die("Error: ID Dokumen SOP tidak ditemukan.");

// 2. Eksekusi Stored Procedure
$sql = "EXEC TAG_BY_ITTY_USAMT @SOP_ID = ?";
$stmt = sqlsrv_query($conn, $sql, array($sopId));

if ($stmt === false) {
    die("Error Eksekusi SP: " . print_r(sqlsrv_errors(), true));
}

// 3. Persiapan Variabel Grouping
$groupedData = [];
$sopRef = "-";
$sopDate = "-";
$grandTotalQty = 0;
$grandTotalUSD = 0;

// Memproses dan mengelompokkan data berdasarkan ITTY_DESC (Item Type)
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if ($sopRef === "-") {
        $sopRef = $row['SOP_REF'] ? $row['SOP_REF'] : "-";
        $sopDate = ($row['SOP_SDATE'] instanceof DateTime) ? $row['SOP_SDATE']->format('d-M-Y') : $row['SOP_SDATE'];
    }
    
    $ittyDesc = $row['ITTY_DESC'] ? trim($row['ITTY_DESC']) : "TANPA TIPE";
    $groupedData[$ittyDesc][] = $row;
    
    $grandTotalQty += (float)$row['STQTY'];
    $grandTotalUSD += (float)$row['USAMOUNT'];
}

// 4. Set Header untuk memaksa download sebagai file Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Tag_By_Type_" . $sopRef . "_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// 5. Output Data dalam bentuk Tabel HTML (Excel akan membacanya sebagai kolom dan baris)
echo "<table border='1'>";
echo "<tr><th colspan='11'><h3>TAG BY TYPE REPORT</h3></th></tr>";
echo "<tr><th colspan='11'>SOP REF: " . htmlspecialchars($sopRef) . " | SOP DATE: " . htmlspecialchars($sopDate) . "</th></tr>";
echo "<tr></tr>"; // Baris Kosong Pemisah

if (empty($groupedData)) {
    echo "<tr><td colspan='11'>Tidak ada data ditemukan untuk dokumen SOP ini.</td></tr>";
} else {
    // Header Kolom
    echo "<tr style='background-color: #eeeeee; font-weight: bold;'>
            <th>ITEM CODE</th>
            <th>ITEM NAME</th>
            <th>QTY OK1</th>
            <th>QTY OK2</th>
            <th>QTY HOLD</th>
            <th>TOTAL QTY</th>
            <th>UNIT</th>
            <th>CURR</th>
            <th>COST</th>
            <th>USD RATE</th>
            <th>USD AMOUNT</th>
          </tr>";

    foreach ($groupedData as $type => $rows) {
        // Header Tipe (Group)
        echo "<tr style='background-color: #d9edf7; font-weight: bold;'>";
        echo "<td colspan='11'>TYPE: " . strtoupper(htmlspecialchars($type)) . "</td>";
        echo "</tr>";
        
        $subQty = 0; 
        $subUsd = 0;
        
        // Looping Detail Item
        foreach ($rows as $r) {
            $subQty += (float)$r['STQTY'];
            $subUsd += (float)$r['USAMOUNT'];
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($r['ITEM_CODE']) . "</td>";
            echo "<td>" . htmlspecialchars($r['ITEM_NAME']) . "</td>";
            echo "<td>" . $r['TOK1'] . "</td>";
            echo "<td>" . $r['TOK2'] . "</td>";
            echo "<td>" . $r['THOLD'] . "</td>";
            echo "<td><b>" . $r['STQTY'] . "</b></td>";
            echo "<td>" . htmlspecialchars($r['ITEM_UNIT']) . "</td>";
            echo "<td>" . htmlspecialchars($r['ITEM_CUR']) . "</td>";
            echo "<td>" . $r['ITEM_COST'] . "</td>";
            echo "<td>" . $r['USRATE'] . "</td>";
            echo "<td><b>" . $r['USAMOUNT'] . "</b></td>";
            echo "</tr>";
        }
        
        // Subtotal Per Tipe
        echo "<tr style='background-color: #fff3cd; font-weight: bold;'>";
        echo "<td colspan='5' style='text-align: right;'>SUBTOTAL " . strtoupper(htmlspecialchars($type)) . " :</td>";
        echo "<td>" . $subQty . "</td>";
        echo "<td colspan='4'></td>";
        echo "<td>" . $subUsd . "</td>";
        echo "</tr>";
    }

    // Grand Total
    echo "<tr style='background-color: #d4edda; font-weight: bold;'>";
    echo "<td colspan='5' style='text-align: right;'>GRAND TOTAL :</td>";
    echo "<td>" . $grandTotalQty . "</td>";
    echo "<td colspan='4'></td>";
    echo "<td>" . $grandTotalUSD . "</td>";
    echo "</tr>";
}

echo "</table>";
exit;
?>
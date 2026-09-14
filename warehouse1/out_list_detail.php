<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once __DIR__ . "/../config/global.php";

$tranid = isset($_POST['tranid']) ? (int)$_POST['tranid'] : 0;
if ($conn === false || $tranid === 0) {
    echo '<tr><td colspan="5" style="text-align:center">BELUM ADA DETAIL</td></tr>';
    exit;
}

// JOIN diubah ke tabel 'items'
$sql = "
    SELECT 
        T.IT_LINENO, 
        T.ITEM_ID, 
        I.ITEM_CODE, 
        T.IT_QTY, 
        T.QRCODE_ID, 
        T.TRAN_ID 
    FROM INV_TRAN T
    LEFT JOIN items I ON T.ITEM_ID = I.ITEM_ID 
    WHERE T.TRAN_ID = ? 
    ORDER BY T.IT_LINENO ASC
";
$stmt = sqlsrv_query($conn, $sql, array($tranid));

$count = 0;
if ($stmt !== false && sqlsrv_has_rows($stmt)) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        
        // --- LOGIKA PENAMBAHAN CHILPART ---
        $qty = $row['IT_QTY'];
        $tampil_qty = number_format($qty, 2);
        
  
        // ----------------------------------

        // --- TAMPILKAN ITEM CODE ---
        // Jika ITEM_CODE ditemukan, tampilkan ITEM_CODE. Jika tidak, tetap tampilkan ITEM_ID sebagai fallback.
        $item_tampil = !empty($row['ITEM_CODE']) ? $row['ITEM_CODE'] : $row['ITEM_ID'];

        echo '<tr>';
        echo '<td style="vertical-align:middle;">' . $row['IT_LINENO'] . '</td>';
        echo '<td style="vertical-align:middle;">' . htmlspecialchars((string)$item_tampil, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td style="vertical-align:middle;">' . $tampil_qty . '</td>';
        echo '<td style="vertical-align:middle;">' . htmlspecialchars($row['QRCODE_ID'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td style="text-align:center;padding:1px;vertical-align:middle;">';
        echo '<button type="button" tranid="' . $row['TRAN_ID'] . '" lineno="' . $row['IT_LINENO'] . '" class="btn btn-danger btn-bold btn-minier removedetail" title="Delete"><i class="fa fa-trash"></i> Hapus</button>';
        echo '</td>';
        echo '</tr>';
        $count++;
    }
    sqlsrv_free_stmt($stmt);
} else {
    echo '<tr><td colspan="5" style="text-align:center">BELUM ADA DETAIL</td></tr>';
}
echo '<input type="hidden" id="totaldetail" value="' . $count . '">';
?>
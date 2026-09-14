<?php
// Kompatibilitas PHP 5.4 & SQL Server 2008
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once __DIR__ . "/../config/global.php";

header('Content-Type: application/json; charset=utf-8');

$search = isset($_POST['search']) ? trim($_POST['search']) : '';
$page   = isset($_POST['page']) ? (int)$_POST['page'] : 1;
$limit  = 50; 
$start  = (($page - 1) * $limit) + 1;
$end    = $page * $limit;

if ($conn === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Koneksi database gagal.'));
    exit;
}

// 1. Siapkan variabel untuk Query Dinamis
$whereClause = "";
$params = array();

// 2. Jika ada pencarian, baru tambahkan WHERE dan parameternya
if ($search !== '') {
    $whereClause = " WHERE R.RCV_NO LIKE ? ";
    $params[] = "%" . $search . "%";
}

/* 
 * T-SQL SQL Server 2008
 * Menggunakan ROW_NUMBER untuk membatasi jumlah data (Pagination)
 */
$sql = "
    SELECT * FROM (
        SELECT 
            RD.RCV_ID,
            RD.QRCODE_ID, 
            RD.PO_ID, 
            RD.ITEM_ID, 
            I.ITEM_CODE,
            I.ITEM_NAME, 
            RD.RCVD_QTY,
            R.RCV_NO,
            R.RCV_DATE,
            ROW_NUMBER() OVER (ORDER BY R.RCV_DATE DESC, RD.QRCODE_ID DESC) AS RowNum
        FROM RECEIVE_DETAIL RD
        INNER JOIN ITEMS I ON RD.ITEM_ID = I.ITEM_ID
        INNER JOIN RECEIVE R ON RD.RCV_ID = R.RCV_ID
        $whereClause
    ) AS DataRecord
    WHERE RowNum BETWEEN ? AND ?
";

// 3. Masukkan parameter batas awal (start) dan batas akhir (end)
$params[] = $start;
$params[] = $end;

// 4. Eksekusi query
$stmt = sqlsrv_query($conn, $sql, $params);

$data = array();
if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = array(
            'rcv_id'    => $row['RCV_ID'],
            'qrcode_id' => htmlspecialchars((string)$row['QRCODE_ID'], ENT_QUOTES, 'UTF-8'),
            'item_code' => htmlspecialchars((string)$row['ITEM_CODE'], ENT_QUOTES, 'UTF-8'),
            'item_name' => htmlspecialchars((string)$row['ITEM_NAME'], ENT_QUOTES, 'UTF-8'),
            'qty'       => (float)$row['RCVD_QTY'],
            'rcv_no'    => htmlspecialchars((string)$row['RCV_NO'], ENT_QUOTES, 'UTF-8')
        );
    }
    sqlsrv_free_stmt($stmt);
} else {
    // Opsional: Untuk debugging jika query error
    // die(print_r(sqlsrv_errors(), true));
}

echo json_encode(array('status' => 'success', 'data' => $data));
?>
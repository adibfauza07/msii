<?php
// get_po_tracking.php
require_once 'config.php';

// Atur header agar merespons sebagai JSON
header('Content-Type: application/json; charset=utf-8');

// Sanitasi dan validasi input (Wajib untuk keamanan)
$req_id  = isset($_GET['req_id']) ? (int)$_GET['req_id'] : 0;
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

if ($req_id === 0 || $item_id === 0) {
    echo json_encode(array()); // PHP 5.4 safe array syntax
    exit;
}

// T-SQL Query (Disesuaikan dengan standar Relasi Database ERP Umum)
// Kita menggunakan INNER JOIN ke tabel PO dan SUPPLIER
$sql = "
    SELECT 
        PH.PO_DATE, 
        PH.PO_NO, 
        PD.PO_QTY, 
        S.SUP_CODE, 
        S.SUP_NAME 
    FROM PO_DETAIL PD
    INNER JOIN PO_HEADER PH ON PD.PO_NO = PH.PO_NO
    LEFT JOIN MASTER_SUPPLIER S ON PH.SUP_ID = S.SUP_ID
    WHERE PD.REQ_ID = ? AND PD.ITEM_ID = ?
    ORDER BY PH.PO_DATE DESC, PH.PO_NO DESC
";

$params = array($req_id, $item_id);
$stmt = sqlsrv_query($conn_msdata, $sql, $params);

$response = array();

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Handle format tanggal (sqlsrv mengembalikan DateTime object)
        $po_date = ($row['PO_DATE'] instanceof DateTime) 
                    ? $row['PO_DATE']->format('d-m-Y') 
                    : $row['PO_DATE'];

        $response[] = array(
            'po_date'  => $po_date,
            'po_num'   => $row['PO_NO'],
            'qty'      => number_format((float)$row['PO_QTY'], 2, ',', '.'),
            'sup_code' => $row['SUP_CODE'] ? $row['SUP_CODE'] : '-',
            'supplier' => $row['SUP_NAME'] ? $row['SUP_NAME'] : 'Tidak Diketahui'
        );
    }
}

// Return data dalam format JSON
echo json_encode($response);
exit;
?>
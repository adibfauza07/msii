<?php
// ==========================================================
// File: get_open_po_items.php
// Deskripsi: API Mengambil Outstanding Item PO Open (Safe JSON)
// Kompabilitas: PHP 5.4, SQL Server 2008
// ==========================================================

// Matikan display error bawaan PHP agar tidak merusak struktur JSON
error_reporting(0);
@ini_set('display_errors', 0);

require_once 'config.php';

// Paksa header selalu JSON
header('Content-Type: application/json; charset=utf-8');

if (!isset($conn_msdata) || $conn_msdata === false) {
    echo json_encode(array("error" => "Koneksi database msdata gagal di file API."));
    exit;
}

$sup_id = isset($_GET['sup_id']) ? (int)$_GET['sup_id'] : 0;

if ($sup_id <= 0) {
    echo json_encode(array());
    exit;
}

// T-SQL 2008: Menghitung Outstanding (OS) dan filter PO maksimal 2 tahun terakhir
$sql = "
    SELECT * FROM (
        SELECT 
            p.PO_ID, p.PO_NUM, p.PO_DATE, i.ITEM_ID, i.ITEM_CODE, i.ITEM_NAME, ISNULL(i.ITEM_UNIT, '-') AS uom,
            pd.POD_PRICE, pd.POD_QTY AS qty_request,
            (pd.POD_QTY - ISNULL((
                SELECT SUM(rd.RCVD_QTY) 
                FROM RECEIVE_DETAIL rd 
                WHERE rd.PO_ID = p.PO_ID AND rd.ITEM_ID = pd.ITEM_ID
            ), 0)) AS os 
        FROM PO p
        INNER JOIN PO_DETAIL pd ON p.PO_ID = pd.PO_ID
        INNER JOIN ITEMS i ON pd.ITEM_ID = i.ITEM_ID
        WHERE p.SUP_ID = ? AND p.PO_CLOSE = 0 AND p.PO_DATE >= DATEADD(YEAR, -2, GETDATE())
    ) AS FinalTable
    WHERE os > 0
    ORDER BY PO_DATE DESC, PO_NUM ASC, ITEM_CODE ASC
";

$stmt = sqlsrv_query($conn_msdata, $sql, array($sup_id));

// Tangkap Error SQL dan kirim sebagai JSON
if ($stmt === false) {
    $errors = sqlsrv_errors();
    echo json_encode(array("error" => "SQL Error: " . $errors[0]['message']));
    exit;
}

$response = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $response[] = array(
        'po_id'     => (int)$row['PO_ID'],
        'po_num'    => trim($row['PO_NUM']),
        'item_id'   => (int)$row['ITEM_ID'],
        'item_code' => trim($row['ITEM_CODE']),
        'item_name' => trim($row['ITEM_NAME']),
        'uom'       => trim($row['uom']),
        'os'        => (float)$row['os'],
        'pod_price' => (float)$row['pod_price']
    );
}
sqlsrv_free_stmt($stmt);

echo json_encode($response);
exit;
?>
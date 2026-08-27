<?php
// ==========================================================
// File: get_po_details.php
// Deskripsi: Mengambil detail item dari PO yang dipilih
// Kompabilitas: PHP 5.4, SQL Server 2008
// ==========================================================

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$po_ids_param = isset($_GET['po_nos']) ? trim($_GET['po_nos']) : '';

if (empty($po_ids_param)) {
    echo json_encode(array());
    exit;
}

// Sanitasi array ID PO (Pastikan integer untuk keamanan SQL Injection)
$raw_ids = explode(',', $po_ids_param);
$clean_po_ids = array();
foreach ($raw_ids as $id) {
    if ((int)$id > 0) {
        $clean_po_ids[] = (int)$id;
    }
}

if (empty($clean_po_ids)) {
    echo json_encode(array());
    exit;
}

// Buat klausa IN secara aman untuk SQL Server
$placeholders = implode(',', array_fill(0, count($clean_po_ids), '?'));

$sql = "
    SELECT 
        p.PO_ID,
        p.PO_NUM,
        p.SUP_ID,
        i.ITEM_ID,
        i.ITEM_CODE,
        i.ITEM_NAME,
        i.ITEM_UNIT AS uom,
        pd.POD_QTY AS qty_request,
        pd.POD_PRICE AS pod_price
    FROM PO_DETAIL pd
    INNER JOIN PO p ON pd.PO_ID = p.PO_ID
    INNER JOIN ITEMS i ON pd.ITEM_ID = i.ITEM_ID
    WHERE p.PO_ID IN ($placeholders)
    ORDER BY p.PO_NUM ASC, i.ITEM_CODE ASC
";

$stmt = sqlsrv_query($conn_msdata, $sql, $clean_po_ids);
$response = array();

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $response[] = array(
            'po_id'       => (int)$row['PO_ID'],
            'sup_id'      => (int)$row['SUP_ID'],
            'ref_no'      => trim($row['PO_NUM']),
            'item_id'     => (int)$row['ITEM_ID'],
            'item_code'   => trim($row['ITEM_CODE']),
            'item_name'   => trim($row['ITEM_NAME']),
            'uom'         => trim($row['uom']),
            'qty_request' => (float)$row['qty_request'],
            'pod_price'   => (float)$row['pod_price']
        );
    }
}

echo json_encode($response);
exit;
?>
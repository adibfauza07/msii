<?php
// ==========================================================
// File: get_po_list.php
// Deskripsi: Mengambil daftar PO yang masih OPEN dari msdata
// Kompabilitas: PHP 5.4, SQL Server 2008
// ==========================================================

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

// Query mengambil PO yang statusnya belum close (PO_CLOSE = 0)
// Diurutkan dari tanggal terbaru
$sql = "
    SELECT 
        p.PO_ID, 
        p.PO_NUM, 
        p.PO_DATE, 
        s.SUP_COMP 
    FROM PO p
    LEFT JOIN SUPPLIER s ON p.SUP_ID = s.SUP_ID
    WHERE p.PO_CLOSE = 0
    ORDER BY p.PO_DATE DESC, p.PO_NUM DESC
";

$stmt = sqlsrv_query($conn_msdata, $sql);
$response = array();

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $po_date = ($row['PO_DATE'] instanceof DateTime) ? $row['PO_DATE']->format('d-m-Y') : date('d-m-Y', strtotime($row['PO_DATE']));
        $sup_name = $row['SUP_COMP'] ? $row['SUP_COMP'] : 'Supplier Tidak Diketahui';
        
        // Format teks untuk tampilan Select2 agar informatif
        $text = trim($row['PO_NUM']) . " (" . $po_date . ") - " . $sup_name;

        $response[] = array(
            'id'   => (int)$row['PO_ID'],
            'text' => $text
        );
    }
}

echo json_encode($response);
exit;
?>
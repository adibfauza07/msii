<?php
// Pastikan session aktif
if (session_id() == "") { session_start(); }
require_once 'config.php';

// Cegah spasi bocor merusak format JSON
if (ob_get_length()) { ob_clean(); }
header('Content-Type: application/json; charset=utf-8');

$dept_id = isset($_GET['dept_id']) ? trim($_GET['dept_id']) : '';
$items = array();

if ($dept_id !== '') {
    // JOIN ke Quotation dan Vendor untuk mendapatkan harga valid dan quote_id
    $sql = "SELECT 
                mi.item_code, 
                mi.item_name, 
                mi.uom,
                q.quote_id,
                q.unit_price,
                v.vendor_name
            FROM Master_Item mi
            INNER JOIN Quotation q ON mi.item_code = q.item_code AND q.is_active = 1
            INNER JOIN Master_Vendor v ON q.vendor_id = v.vendor_id
            WHERE mi.department_id = ? AND mi.is_active = 1
            ORDER BY mi.item_name ASC";
    
    $stmt = sqlsrv_query($conn, $sql, array($dept_id));
    
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $items[] = array(
                'item_code'   => htmlspecialchars($row['item_code'], ENT_QUOTES, 'UTF-8'),
                'item_name'   => htmlspecialchars($row['item_name'], ENT_QUOTES, 'UTF-8'),
                'uom'         => htmlspecialchars($row['uom'], ENT_QUOTES, 'UTF-8'),
                'quote_id'    => (int)$row['quote_id'],
                'unit_price'  => (float)$row['unit_price'],
                'vendor_name' => htmlspecialchars($row['vendor_name'], ENT_QUOTES, 'UTF-8')
            );
        }
    } else {
        echo json_encode(array('is_error' => true, 'pesan' => print_r(sqlsrv_errors(), true)));
        exit;
    }
}

echo json_encode($items);
exit;
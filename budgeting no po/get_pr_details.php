<?php
if (session_id() == "") { session_start(); }
require_once 'config.php';

if (ob_get_length()) { ob_clean(); }
header('Content-Type: application/json; charset=utf-8');

// Bisa menerima banyak PR sekaligus dipisahkan koma (Contoh: PR26080001,PR26080002)
$pr_nos_raw = isset($_GET['pr_nos']) ? trim($_GET['pr_nos']) : '';
$details = array();

if ($pr_nos_raw !== '') {
    $pr_array = explode(',', $pr_nos_raw);
    
    foreach ($pr_array as $pr_no) {
        $pr_no = trim($pr_no);
        if ($pr_no === '') continue;

        $sql = "SELECT 
                    pd.pr_detail_id,
                    pd.pr_no,
                    pd.item_code,
                    mi.item_name,
                    mi.uom,
                    pd.qty_request
                FROM PR_Detail pd
                INNER JOIN Master_Item mi ON pd.item_code = mi.item_code
                WHERE pd.pr_no = ?";
        
        $stmt = sqlsrv_query($conn, $sql, array($pr_no));
        
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $details[] = array(
                    'pr_detail_id' => (int)$row['pr_detail_id'],
                    'pr_no'        => htmlspecialchars($row['pr_no'], ENT_QUOTES, 'UTF-8'),
                    'item_code'    => htmlspecialchars($row['item_code'], ENT_QUOTES, 'UTF-8'),
                    'item_name'    => htmlspecialchars($row['item_name'], ENT_QUOTES, 'UTF-8'),
                    'uom'          => htmlspecialchars($row['uom'], ENT_QUOTES, 'UTF-8'),
                    'qty_request'  => (float)$row['qty_request']
                );
            }
        }
    }
}

echo json_encode($details);
exit;
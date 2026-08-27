<?php
if (session_id() == "") { session_start(); }
require_once 'config.php';

if (ob_get_length()) { ob_clean(); }
header('Content-Type: application/json; charset=utf-8');

$dept_id = isset($_GET['dept_id']) ? trim($_GET['dept_id']) : '';
$results = array();

if ($dept_id !== '') {
    // Ambil PR yang belum selesai / berstatus valid untuk diterima barangnya
    $sql = "SELECT DISTINCT pr_no, pr_date 
            FROM PR_Header 
            WHERE department_id = ? AND status IN ('PENDING', 'APPROVED') 
            ORDER BY pr_date DESC";
    
    $stmt = sqlsrv_query($conn, $sql, array($dept_id));
    
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $date_str = $row['pr_date'] instanceof DateTime ? $row['pr_date']->format('d-m-Y') : date('d-m-Y', strtotime($row['pr_date']));
            $results[] = array(
                'pr_no' => htmlspecialchars($row['pr_no'], ENT_QUOTES, 'UTF-8'),
                'text'  => htmlspecialchars($row['pr_no'] . ' (Tgl: ' . $date_str . ')', ENT_QUOTES, 'UTF-8')
            );
        }
    }
}

echo json_encode($results);
exit;
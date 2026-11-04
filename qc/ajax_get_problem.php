<?php
// FILE: ajax_get_problem.php
ob_start(); 
error_reporting(E_ALL);
ini_set('display_errors', 0); 

if (session_status() == PHP_SESSION_NONE) { session_start(); }

$active_plant = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1';
$db_path = ($active_plant == 'p2') ? __DIR__ . '/../config/database.php' : __DIR__ . '/../config/database_p1.php';
if(file_exists($db_path)) { require_once $db_path; }

$sampah_html = ob_get_clean(); 
header('Content-Type: application/json');

if (!isset($conn) || $conn === false) {
    echo json_encode(array());
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$like = "%" . $q . "%";

// Ambil data NGT_DESC dari tabel NG_TYPE
$sql = "SELECT DISTINCT NGT_DESC FROM dbo.NG_TYPE WHERE NGT_DESC LIKE ?";
$params = array($like);
$stmt = sqlsrv_query($conn, $sql, $params);

$data = array();
if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $desc = trim($row['NGT_DESC']);
        if($desc != '') {
            $data[] = array(
                'id' => $desc, // id diset sama dengan text karena ini akan disimpan sebagai string di QC_PROBLEM
                'text' => $desc
            );
        }
    }
}

echo json_encode(array('results' => $data));
exit;
?>
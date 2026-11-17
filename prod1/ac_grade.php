<?php
ob_start();
require_once __DIR__ . "/../config/global.php";
while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/json; charset=UTF-8');

$db = null;
if (isset($conn)) $db = $conn;
elseif (isset($connection)) $db = $connection;

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') { echo json_encode(array()); exit; }

$like = '%' . $q . '%';

// Mengambil data grade yang unik (tidak duplikat)
$sql = "
    SELECT DISTINCT TOP 20 ITEM_GRADE 
    FROM dbo.ITEMS 
    WHERE ITEM_GRADE LIKE ? AND ITEM_GRADE IS NOT NULL AND LTRIM(RTRIM(ITEM_GRADE)) <> ''
    ORDER BY ITEM_GRADE
";
$stmt = sqlsrv_query($db, $sql, array($like));

$data = array();
if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = array('GRADE' => trim($row['ITEM_GRADE']));
    }
    sqlsrv_free_stmt($stmt);
}
echo json_encode($data);
exit;
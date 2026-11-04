<?php
// FILE: ajax_get_history.php

// 1. Tahan semua output liar (spasi, error HTML, enter) agar tidak merusak JSON
ob_start(); 
error_reporting(E_ALL);
ini_set('display_errors', 0); 

if (session_status() == PHP_SESSION_NONE) { session_start(); }

$active_plant = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1';
$db_path = ($active_plant == 'p2') ? __DIR__ . '/../config/database.php' : __DIR__ . '/../config/database_p1.php';

if(file_exists($db_path)) { require_once $db_path; }

// 2. Buang semua output yang mengganggu (misal dari global.php)
$sampah_html = ob_get_clean(); 

// 3. Set Header murni JSON
header('Content-Type: application/json');

if (!isset($conn) || $conn === false) {
    echo json_encode(array('error' => 'Variabel koneksi database ($conn) tidak ditemukan di file config.'));
    exit;
}

$part_code = isset($_GET['part_code']) ? $_GET['part_code'] : '';
if ($part_code == "") {
    echo json_encode(array());
    exit;
}

// 4. Query Database
$sql = "SELECT 
            dbo.QC_PROBLEM.ID_PROBLEM, 
            dbo.QC_PROBLEM.PROBLEM, 
            dbo.QC_PROBLEM.SHIFT, 
            CONVERT(varchar, dbo.QC_PROBLEM.TANGGAL, 23) AS TANGGAL, 
            dbo.QC_PROBLEM.Penangan, 
            dbo.QC_PROBLEM.Perbaikan, 
            S.STATUS, 
            CAST(dbo.QC_PROBLEM.Tempat_Foto AS varchar(255)) AS tempat_foto, 
            PIC.PIC, 
            dbo.ITEM_CUSTINFO_VIEW.PART_CODE, 
            dbo.ITEM_CUSTINFO_VIEW.PART_NO, 
            dbo.ITEM_CUSTINFO_VIEW.PART_NAME, 
            dbo.ITEM_CUSTINFO_VIEW.CUST_CODE, 
            dbo.ITEM_CUSTINFO_VIEW.CUST_COMP
        FROM dbo.QC_PROBLEM 
        INNER JOIN dbo.ITEM_CUSTINFO_VIEW ON dbo.QC_PROBLEM.ITEM_ID = dbo.ITEM_CUSTINFO_VIEW.ITEM_ID 
        LEFT OUTER JOIN dbo.STATUS AS S ON S.ID_STATUS = dbo.QC_PROBLEM.STATUS_ID 
        LEFT OUTER JOIN dbo.QC_DIC_PIC AS PIC ON PIC.ID_PROBLEM = dbo.QC_PROBLEM.ID_PROBLEM
        WHERE dbo.ITEM_CUSTINFO_VIEW.PART_CODE = ?
        ORDER BY dbo.QC_PROBLEM.TANGGAL DESC";

$params = array($part_code);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $errors = sqlsrv_errors();
    $err_msg = !empty($errors) ? $errors[0]['message'] : "Unknown SQL Error";
    echo json_encode(array('error' => 'SQL SERVER ERROR: ' . $err_msg));
    exit;
}

$data = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $id = $row['ID_PROBLEM'];
    
    if (!isset($data[$id])) {
        // PERBAIKAN UNTUK PHP 5.4 (Mengganti ?? dengan isset dan ternary)
        $data[$id] = array(
            'ID_PROBLEM'  => $id, 
            'TANGGAL'     => $row['TANGGAL'],
            'PROBLEM'     => isset($row['PROBLEM']) ? trim($row['PROBLEM']) : '',
            'SHIFT'       => isset($row['SHIFT']) ? trim($row['SHIFT']) : '',
            'Penangan'    => isset($row['Penangan']) ? trim($row['Penangan']) : '',
            'Perbaikan'   => isset($row['Perbaikan']) ? trim($row['Perbaikan']) : '',
            'STATUS'      => (isset($row['STATUS']) && trim($row['STATUS']) != '') ? trim($row['STATUS']) : 'OPEN',
            'tempat_foto' => isset($row['tempat_foto']) ? trim($row['tempat_foto']) : '',
            'PIC'         => array() 
        );
    }
    
    if (!empty($row['PIC'])) {
        $data[$id]['PIC'][] = trim($row['PIC']);
    }
}

$results = array();
foreach ($data as $id => $row) {
    $row['PIC'] = implode(', ', $row['PIC']); 
    $results[] = $row;
}

echo json_encode($results);
exit;
?>
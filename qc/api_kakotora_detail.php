<?php
// FILE: msii/qc/api_kakotora_detail.php
ini_set('display_errors', 0);
error_reporting(0);
define('LOGIN_PAGE', true);

// 1. DETEKSI PLANT AKTIF
if (session_status() == PHP_SESSION_NONE) { session_start(); }
$active_plant = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1';

// 2. PILIH KONEKSI
if ($active_plant == 'p2') {
    if(!isset($_SESSION['erp_user'])) $_SESSION['erp_user'] = $_SESSION['db_user'];
    if(!isset($_SESSION['erp_pass'])) $_SESSION['erp_pass'] = $_SESSION['db_pass'];
    $_SESSION['server_sql'] = "192.168.0.9"; // !!! ISI IP PLANT 2 DI SINI !!!
    require_once __DIR__ . '/../config/database.php'; 
} else {
    require_once __DIR__ . '/../config/database_p1.php'; 
}

header('Content-Type: application/json');
if (!$conn) { echo json_encode(array('status'=>'error', 'msg'=>'Koneksi Database Gagal')); exit; }

// --- LOGIC BAWAHNYA SAMA ---
$id = isset($_GET['id']) ? $_GET['id'] : 0;

$sqlDetail = "SELECT cause, counter, pic, status, eff_date FROM car_claim_detail WHERE car_id = ?";
$stmtDetail = sqlsrv_query($conn, $sqlDetail, array($id));
$rowDetail = ($stmtDetail) ? sqlsrv_fetch_array($stmtDetail, SQLSRV_FETCH_ASSOC) : null;

$sqlMaster = "SELECT event_status FROM car_claim WHERE car_id = ?";
$stmtMaster = sqlsrv_query($conn, $sqlMaster, array($id));
$rowMaster = ($stmtMaster) ? sqlsrv_fetch_array($stmtMaster, SQLSRV_FETCH_ASSOC) : null;
$event_status = $rowMaster ? $rowMaster['event_status'] : '-';

function getListString($conn, $table, $col, $id) {
    $sql = "SELECT $col FROM $table WHERE car_id = ?";
    $stmt = sqlsrv_query($conn, $sql, array($id));
    $items = array();
    if ($stmt) { while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { if(!empty($r[$col])) $items[] = $r[$col]; } }
    return empty($items) ? '-' : implode(", ", $items);
}

$efek_car = getListString($conn, 'car_efek', 'EFEK', $id);
$klasifikasi = getListString($conn, 'car_klasifikasi', 'klasifikasi', $id);
$loc_problem = getListString($conn, 'car_loc', 'loc_problem', $id);

$hasImage = false;
$stmtImg = sqlsrv_query($conn, "SELECT TOP 1 car_id FROM car_claim_gambar WHERE car_id = ?", array($id));
if ($stmtImg && sqlsrv_has_rows($stmtImg)) $hasImage = true;

$cause = isset($rowDetail['cause']) ? $rowDetail['cause'] : '-';
$counter = isset($rowDetail['counter']) ? $rowDetail['counter'] : '-';
$pic = isset($rowDetail['pic']) ? $rowDetail['pic'] : '-';
$status = isset($rowDetail['status']) ? $rowDetail['status'] : 'OPEN';
$effDate = (isset($rowDetail['eff_date']) && $rowDetail['eff_date']) ? $rowDetail['eff_date']->format('d-m-Y') : '-';

$data = array('event_status'=> $event_status, 'efek_car'=> $efek_car, 'klasifikasi'=> $klasifikasi, 'loc_problem'=> $loc_problem, 'cause'=> $cause, 'counter'=> $counter, 'pic'=> $pic, 'status'=> $status, 'eff_date'=> $effDate);
echo json_encode(array('status' => 'ok', 'data' => $data, 'has_image' => $hasImage));
?>
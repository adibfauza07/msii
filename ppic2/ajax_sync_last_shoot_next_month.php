<?php
// ajax_sync_last_shoot_next_month.php
ob_start();
set_time_limit(0); 
ini_set('memory_limit', '512M'); 

if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . "/../config/global.php";

header('Content-Type: application/json');

// --- PENERIMA DATA JSON DARI JAVASCRIPT ---
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

$thnDepan = isset($input['tahun_depan']) ? (int)$input['tahun_depan'] : 0;
$blnDepan = isset($input['bulan_depan']) ? (int)$input['bulan_depan'] : 0;
$items = isset($input['items']) ? $input['items'] : [];

if (empty($items) || $thnDepan == 0 || $blnDepan == 0) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap / JSON gagal dibaca.']);
    exit;
}

// Pastikan koneksi db tersedia
if (!isset($conn) || !is_resource($conn)) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database SQL Server ($conn) tidak valid.']);
    exit;
}

// ⚠️ KITA HAPUS BEGIN TRANSACTION AGAR QUERY LANGSUNG AUTO-COMMIT / PERMANEN
// KITA GUNAKAN COUNT(*) KARENA LEBIH STABIL DARI PADA IF EXISTS
$sqlCheck  = "SELECT COUNT(*) AS cnt FROM MATRIX_LAST_SHOOT WHERE TAHUN = ? AND BULAN = ? AND MAC_CODE = ? AND ITEM_CODE = ?";
$sqlUpdate = "UPDATE MATRIX_LAST_SHOOT SET LAST_SHOOT = ? WHERE TAHUN = ? AND BULAN = ? AND MAC_CODE = ? AND ITEM_CODE = ?";
$sqlInsert = "INSERT INTO MATRIX_LAST_SHOOT (TAHUN, BULAN, MAC_CODE, ITEM_CODE, LAST_SHOOT) VALUES (?, ?, ?, ?, ?)";

$hasError = false;
$errorMsg = '';
$inserted = 0;
$updated  = 0;
$sampleData = '';

foreach ($items as $row) {
    $mc    = (string)$row['mc_code'];
    $item  = (string)$row['item_code'];
    $shoot = (float)$row['last_shoot'];

    // Ambil sampel 1 data untuk pembuktian di pop-up
    if ($sampleData === '' && $shoot > 0) {
        $sampleData = "Mesin $mc = $shoot"; 
    }

    // 1. CEK DATA
    $checkParams = [$thnDepan, $blnDepan, $mc, $item];
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, $checkParams);
    
    if ($stmtCheck === false) {
        $hasError = true;
        $errors = sqlsrv_errors();
        $errorMsg = 'Check: ' . $errors[0]['message'];
        break;
    }

    $cekData = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
    $exists = ($cekData && $cekData['cnt'] > 0);
    sqlsrv_free_stmt($stmtCheck);

    // 2. INSERT ATAU UPDATE LANGSUNG AUTO COMMIT
    if ($exists) {
        $updateParams = [$shoot, $thnDepan, $blnDepan, $mc, $item];
        $stmtUpd = sqlsrv_query($conn, $sqlUpdate, $updateParams);
        if ($stmtUpd === false) {
            $hasError = true;
            $errors = sqlsrv_errors();
            $errorMsg = 'Update: ' . $errors[0]['message'];
            break;
        }
        $updated++;
        sqlsrv_free_stmt($stmtUpd);
    } else {
        $insertParams = [$thnDepan, $blnDepan, $mc, $item, $shoot];
        $stmtIns = sqlsrv_query($conn, $sqlInsert, $insertParams);
        if ($stmtIns === false) {
            $hasError = true;
            $errors = sqlsrv_errors();
            $errorMsg = 'Insert: ' . $errors[0]['message'];
            break;
        }
        $inserted++;
        sqlsrv_free_stmt($stmtIns);
    }
}

ob_clean();
if ($hasError) {
    echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $errorMsg]);
} else {
    // Pesan sukses akan menampilkan bukti angka yang ditangkap
    if ($sampleData === '') $sampleData = 'Semua 0';
    $pesanSukses = "Tersimpan ke $blnDepan/$thnDepan.\n- Update: $updated baris\n- Insert: $inserted baris\n\nBUKTI DATA DITANGKAP: $sampleData";
    
    echo json_encode(['status' => 'success', 'message' => $pesanSukses]);
}
exit;
?>
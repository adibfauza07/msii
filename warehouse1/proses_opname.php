<?php
require_once __DIR__ . "/../config/global.php";
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('status' => 'error', 'message' => 'Invalid Request.'));
    exit;
}

if ($conn === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Koneksi database terputus. Silakan muat ulang halaman.'));
    exit;
}

$qr_text = isset($_POST['qr_text']) ? trim($_POST['qr_text']) : '';
$loc_id  = isset($_POST['loc_id']) ? intval($_POST['loc_id']) : 0;

if ($qr_text === '' || $loc_id === 0) {
    echo json_encode(array('status' => 'error', 'message' => 'Lokasi dan QR Code wajib diisi.'));
    exit;
}

$qr_data  = explode('|', $qr_text);
$qr_count = count($qr_data);

$qrcode_id = '';
$wo_id     = '';
$qty       = 0;
$unit      = '';
$item_id   = 0;

// ==========================================
// 1. SMART PARSING: Auto-Detect Format QR Code
// ==========================================
if ($qr_count >= 7) {
    // TERDETEKSI FORMAT PLAN 2
    $qrcode_id = trim($qr_data[0]);
    $wo_id     = trim($qr_data[1]);
    $qty       = floatval($qr_data[4]);
    $unit      = trim($qr_data[5]);
    $item_id   = intval($qr_data[6]);
} elseif ($qr_count >= 5) {
    // TERDETEKSI FORMAT PLAN 1
    $qrcode_id = trim($qr_data[0]);
    $wo_id     = trim($qr_data[1]);
    $qty       = floatval($qr_data[2]);
    $unit      = trim($qr_data[3]);
    $item_id   = intval($qr_data[4]);
} else {
    echo json_encode(array('status' => 'error', 'message' => 'Format QR Code tidak valid (Segmen kurang).'));
    exit;
}

// ==========================================
// 2. VALIDASI SOP AKTIF
// ==========================================
$sqlSop = "SELECT TOP 1 SOP_ID FROM SOP WHERE SOP_FINISHED = 'F' ORDER BY SOP_SDATE DESC";
$stmtSop = sqlsrv_query($conn, $sqlSop);

if ($stmtSop === false || !sqlsrv_has_rows($stmtSop)) {
    echo json_encode(array('status' => 'error', 'message' => 'Tidak ada periode SOP yang sedang aktif.'));
    exit;
}

$sop    = sqlsrv_fetch_array($stmtSop, SQLSRV_FETCH_ASSOC);
$sop_id = $sop['SOP_ID'];
sqlsrv_free_stmt($stmtSop);

// ==========================================
// 3. CEK DUPLIKASI SCAN
// ==========================================
$sqlCheck  = "SELECT TAG_NO FROM TAGS WHERE SOP_ID = ? AND QRCODE_ID = ?";
$stmtCheck = sqlsrv_query($conn, $sqlCheck, array($sop_id, $qrcode_id));

if ($stmtCheck === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Database Error saat memvalidasi QR Code.'));
    exit;
}

if (sqlsrv_has_rows($stmtCheck)) {
    echo json_encode(array(
        'status'  => 'error', 
        'message' => 'QR Code ' . htmlspecialchars($qrcode_id, ENT_QUOTES, 'UTF-8') . ' sudah di-scan!'
    ));
    sqlsrv_free_stmt($stmtCheck);
    sqlsrv_close($conn);
    exit;
}
sqlsrv_free_stmt($stmtCheck);

// ==========================================
// 4. AMBIL DETAIL ITEM (MASTER DATA)
// ==========================================
$sqlItem  = "SELECT ITEM_CODE, ITEM_NAME FROM ITEMS WHERE ITEM_ID = ?";
$stmtItem = sqlsrv_query($conn, $sqlItem, array($item_id));
$itemData = array('ITEM_CODE' => '-', 'ITEM_NAME' => '-');

if ($stmtItem !== false && sqlsrv_has_rows($stmtItem)) {
    $rowItem = sqlsrv_fetch_array($stmtItem, SQLSRV_FETCH_ASSOC);
    $itemData['ITEM_CODE'] = $rowItem['ITEM_CODE'];
    $itemData['ITEM_NAME'] = $rowItem['ITEM_NAME'];
    sqlsrv_free_stmt($stmtItem);
}

// ==========================================
// 5. TRANSAKSI DATABASE (CEGAH RACE CONDITION)
// ==========================================
if (sqlsrv_begin_transaction($conn) === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Gagal memulai transaksi database.'));
    exit;
}

$yy     = date('y'); 
$m      = strtoupper(dechex(date('n'))); 
$prefix = $yy . $m; 

// UPDLOCK, HOLDLOCK memastikan antrian data aman di SQL Server 2008
$sqlMax = "SELECT ISNULL(MAX(CAST(RIGHT(TAG_NO, 4) AS INT)), 0) + 1 AS NextSeq 
           FROM TAGS WITH (UPDLOCK, HOLDLOCK) 
           WHERE TAG_NO LIKE ? + '[0-9][0-9][0-9][0-9]'";
           
$stmtMax = sqlsrv_query($conn, $sqlMax, array($prefix));

if ($stmtMax === false) {
    sqlsrv_rollback($conn);
    echo json_encode(array('status' => 'error', 'message' => 'Gagal membaca urutan tag.'));
    exit;
}

$rowMax  = sqlsrv_fetch_array($stmtMax, SQLSRV_FETCH_ASSOC);
$nextSeq = $rowMax['NextSeq'];
sqlsrv_free_stmt($stmtMax);

$newTag = $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

$sqlInsert = "INSERT INTO TAGS (SOP_ID, ITEM_ID, LOC_ID, TAG_QTY, QRCODE_ID, TAG_NO) 
              VALUES (?, ?, ?, ?, ?, ?)";
$paramsInsert = array($sop_id, $item_id, $loc_id, $qty, $qrcode_id, $newTag);
$stmtInsert   = sqlsrv_query($conn, $sqlInsert, $paramsInsert);

if ($stmtInsert) {
    sqlsrv_commit($conn);
    // Kembalikan QTY & UNIT ke Front-End agar tampilan UI menggunakan 100% data riil dari DB
    echo json_encode(array(
        'status'    => 'success', 
        'message'   => 'Berhasil! QR tersimpan.',
        'tag_no'    => htmlspecialchars($newTag, ENT_QUOTES, 'UTF-8'),
        'item_code' => htmlspecialchars($itemData['ITEM_CODE'], ENT_QUOTES, 'UTF-8'),
        'item_name' => htmlspecialchars($itemData['ITEM_NAME'], ENT_QUOTES, 'UTF-8'),
        'qty'       => $qty,
        'unit'      => htmlspecialchars($unit, ENT_QUOTES, 'UTF-8')
    ));
    sqlsrv_free_stmt($stmtInsert);
} else {
    sqlsrv_rollback($conn);
    echo json_encode(array('status' => 'error', 'message' => 'Gagal menyimpan data ke tabel TAGS.'));
}

sqlsrv_close($conn);
?>
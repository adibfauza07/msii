<?php
// Pastikan error reporting disesuaikan untuk production
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . "/../config/global.php";

header('Content-Type: application/json; charset=utf-8');

// Proteksi metode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('status' => 'error', 'message' => 'Metode tidak diizinkan.'));
    exit;
}

// Tangkap dan sanitasi input
$raw_qr = isset($_POST['qrcode']) ? trim($_POST['qrcode']) : '';

if (strlen($raw_qr) < 10) {
    echo json_encode(array('status' => 'error', 'message' => 'Format QR Code tidak valid.'));
    exit;
}

// ==========================================
// 1. SMART PARSING: Auto-Detect Format QR Code
// ==========================================
$qr_parts = explode('|', $raw_qr);
$qr_count = count($qr_parts);

$qrcode_id = '';
$po_id     = 0;
$rcvd_qty  = 0;

if ($qr_count >= 7) {
    // TERDETEKSI FORMAT PLAN 2
    $qrcode_id = trim($qr_parts[0]);
    $po_id     = (int)trim($qr_parts[1]);
    $rcvd_qty  = (float)trim($qr_parts[4]); // Qty berada di index 4
} elseif ($qr_count >= 5) {
    // TERDETEKSI FORMAT PLAN 1
    $qrcode_id = trim($qr_parts[0]);
    $po_id     = (int)trim($qr_parts[1]);
    $rcvd_qty  = (float)trim($qr_parts[2]); // Qty berada di index 2
} else {
    // Fail-Safe jika format di bawah standar
    echo json_encode(array('status' => 'error', 'message' => 'Gagal: Format QR Code (Segmen) tidak dikenali.'));
    exit;
}

// Ekstrak 10 karakter pertama sebagai ID untuk mencari data di QR_CODE_DATA_MATERIAL
$qr_data_id = substr($qrcode_id, 0, 10); 

if ($conn === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Koneksi database gagal.'));
    exit;
}

// Mulai Transaksi Database untuk menjaga integritas data ERP
if (sqlsrv_begin_transaction($conn) === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Gagal memulai transaksi database.'));
    exit;
}

try {
    // 2. Cek Duplikasi (Mencegah double scan) - Menggunakan UPDLOCK untuk cegah Race Condition
    $sqlCheck = "SELECT TOP 1 QRCODE_ID FROM RECEIVE_DETAIL WITH (UPDLOCK, HOLDLOCK) WHERE QRCODE_ID = ?";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($qrcode_id));
    if ($stmtCheck !== false && sqlsrv_has_rows($stmtCheck)) {
        throw new Exception("QR Code [$qrcode_id] sudah pernah di-scan (Duplikat).");
    }
    if ($stmtCheck !== false) sqlsrv_free_stmt($stmtCheck);

    // 3. Ambil data pelengkap dari QR_CODE_DATA_MATERIAL
    $sqlData = "SELECT TOP 1 RCV_ID, ITEM_ID, POD_PRICE FROM QR_CODE_DATA_MATERIAL WHERE ID = ?";
    $stmtData = sqlsrv_query($conn, $sqlData, array($qr_data_id));
    
    if ($stmtData === false || !sqlsrv_has_rows($stmtData)) {
        throw new Exception("Data Master QR tidak ditemukan untuk ID: $qr_data_id.");
    }
    
    $rowData = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC);
    $rcv_id = $rowData['RCV_ID'];
    $item_id = $rowData['ITEM_ID'];
    $pod_price = $rowData['POD_PRICE'];
    sqlsrv_free_stmt($stmtData);

    // 4. Simpan ke RECEIVE_DETAIL
    $sqlInsert = "
        INSERT INTO RECEIVE_DETAIL (RCV_ID, ITEM_ID, PO_ID, RCVD_QTY, POD_PRICE, QRCODE_ID) 
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $paramsInsert = array($rcv_id, $item_id, $po_id, $rcvd_qty, $pod_price, $qrcode_id);
    $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);

    if ($stmtInsert === false) {
        throw new Exception("Gagal menyimpan data ke database.");
    }
    sqlsrv_free_stmt($stmtInsert);

    // Commit transaksi jika semua query berhasil
    sqlsrv_commit($conn);

    // Kembalikan Response Sukses ke Front-end (Sanitasi XSS pada output)
    echo json_encode(array(
        'status'  => 'success',
        'message' => 'Data material berhasil masuk.',
        'data'    => array(
            'qrcode_id' => htmlspecialchars($qrcode_id, ENT_QUOTES, 'UTF-8'),
            'po_id'     => $po_id,
            'qty'       => $rcvd_qty,
            'item_id'   => $item_id
        )
    ));

} catch (Exception $e) {
    // Rollback jika terjadi kegagalan di salah satu proses
    sqlsrv_rollback($conn);
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
?>
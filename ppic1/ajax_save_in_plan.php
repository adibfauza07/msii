<?php
// ajax_save_in_plan.php (Optimized for PHP 5.4 & SQL Server 2008)

// PROTEKSI: Matikan display_errors di output agar JSON tidak rusak jika ada notice PHP.
// Error akan dilempar ke error_log server (misal: php_errors.log)
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) { session_start(); }
session_write_close(); 
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . "/../config/global.php"; 

if (!isset($conn) || $conn === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Koneksi database ERP terputus.'));
    exit;
}

// 1. Tangkap dan Sanitasi Parameter
$tahun    = isset($_POST['tahun']) ? (int)$_POST['tahun'] : 0;
$bulan    = isset($_POST['bulan']) ? (int)$_POST['bulan'] : 0;
$itemCode = isset($_POST['item_code']) ? trim(strip_tags($_POST['item_code'])) : '';
$days     = isset($_POST['days']) && is_array($_POST['days']) ? $_POST['days'] : array();

if ($tahun === 0 || $bulan === 0 || $itemCode === '') {
    echo json_encode(array('status' => 'error', 'message' => 'Parameter Dokumen MRP (Tahun/Bulan/Item) tidak lengkap.'));
    exit;
}

// 2. Ekstrak data harian (D1-D31)
$gTotal = 0;
$dValues = array();
for ($i = 1; $i <= 31; $i++) {
    $val = isset($days['d'.$i]) ? (float)$days['d'.$i] : 0;
    $dValues[] = $val;
    $gTotal += $val;
}

if (sqlsrv_begin_transaction($conn) === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Gagal menginisiasi transaksi database.'));
    exit;
}

try {
    // A. Cari ITEM_ID
    $sqlMat = "SELECT ITEM_ID FROM dbo.ITEMS WHERE ITEM_CODE = ?";
    $stmtMat = sqlsrv_query($conn, $sqlMat, array($itemCode));
    $itemId = 0;
    if ($stmtMat && $rowMat = sqlsrv_fetch_array($stmtMat, SQLSRV_FETCH_ASSOC)) {
        $itemId = $rowMat['ITEM_ID'];
    }
    sqlsrv_free_stmt($stmtMat);

    if ($itemId === 0) { 
        throw new Exception("Material Code '{$itemCode}' tidak terdaftar di Master Items."); 
    }

    // B. Cari ID_NO Header
    $sqlHdr = "SELECT ID_NO FROM dbo.RPT_MRP WHERE DOC_YEAR = ? AND DOC_MONTH = ?";
    $stmtHdr = sqlsrv_query($conn, $sqlHdr, array($tahun, $bulan));
    $idNo = 0;
    if ($stmtHdr && $rowHdr = sqlsrv_fetch_array($stmtHdr, SQLSRV_FETCH_ASSOC)) {
        $idNo = $rowHdr['ID_NO'];
    }
    sqlsrv_free_stmt($stmtHdr);

    if ($idNo === 0) { 
        throw new Exception("Dokumen MRP periode {$bulan}-{$tahun} belum di-generate oleh sistem."); 
    }

    // C. T-SQL 2008 MERGE (Atomic Upsert yang Aman dari Race Condition)
    // Menggunakan parameter array mapping agar lebih efisien di sisi Database Engine.
    $sqlUpsert = "
        MERGE INTO dbo.RPT_MRP_DTL WITH (HOLDLOCK) AS tgt
        USING (
            SELECT 
                ? AS ID_NO, ? AS ITEM_ID, 'R_IP' AS ROW_NAME, ? AS G_TOTAL,
                ? AS D1, ? AS D2, ? AS D3, ? AS D4, ? AS D5, ? AS D6, ? AS D7, ? AS D8, ? AS D9, ? AS D10,
                ? AS D11, ? AS D12, ? AS D13, ? AS D14, ? AS D15, ? AS D16, ? AS D17, ? AS D18, ? AS D19, ? AS D20,
                ? AS D21, ? AS D22, ? AS D23, ? AS D24, ? AS D25, ? AS D26, ? AS D27, ? AS D28, ? AS D29, ? AS D30, ? AS D31
        ) AS src
        ON tgt.ID_NO = src.ID_NO AND tgt.ITEM_ID = src.ITEM_ID AND tgt.ROW_NAME = src.ROW_NAME
        WHEN MATCHED THEN
            UPDATE SET 
                tgt.G_TOTAL = src.G_TOTAL, 
                tgt.D1 = src.D1, tgt.D2 = src.D2, tgt.D3 = src.D3, tgt.D4 = src.D4, tgt.D5 = src.D5, 
                tgt.D6 = src.D6, tgt.D7 = src.D7, tgt.D8 = src.D8, tgt.D9 = src.D9, tgt.D10 = src.D10,
                tgt.D11 = src.D11, tgt.D12 = src.D12, tgt.D13 = src.D13, tgt.D14 = src.D14, tgt.D15 = src.D15, 
                tgt.D16 = src.D16, tgt.D17 = src.D17, tgt.D18 = src.D18, tgt.D19 = src.D19, tgt.D20 = src.D20,
                tgt.D21 = src.D21, tgt.D22 = src.D22, tgt.D23 = src.D23, tgt.D24 = src.D24, tgt.D25 = src.D25, 
                tgt.D26 = src.D26, tgt.D27 = src.D27, tgt.D28 = src.D28, tgt.D29 = src.D29, tgt.D30 = src.D30, tgt.D31 = src.D31
        WHEN NOT MATCHED THEN
            INSERT (ID_NO, ITEM_ID, ROW_NAME, G_TOTAL, 
                    D1, D2, D3, D4, D5, D6, D7, D8, D9, D10, 
                    D11, D12, D13, D14, D15, D16, D17, D18, D19, D20, 
                    D21, D22, D23, D24, D25, D26, D27, D28, D29, D30, D31)
            VALUES (src.ID_NO, src.ITEM_ID, src.ROW_NAME, src.G_TOTAL, 
                    src.D1, src.D2, src.D3, src.D4, src.D5, src.D6, src.D7, src.D8, src.D9, src.D10,
                    src.D11, src.D12, src.D13, src.D14, src.D15, src.D16, src.D17, src.D18, src.D19, src.D20,
                    src.D21, src.D22, src.D23, src.D24, src.D25, src.D26, src.D27, src.D28, src.D29, src.D30, src.D31);
    ";

    // Susun parameter (Total 34 Param) untuk dikirim ke src virtual table
    $params = array($idNo, $itemId, $gTotal);
    $params = array_merge($params, $dValues); // Masukkan array D1 s/d D31

    $stmt = sqlsrv_query($conn, $sqlUpsert, $params);
    
    if ($stmt === false) {
        // Ambil error dari SQL Server untuk keperluan LOGDING internal, bukan untuk Front-End
        $errors = sqlsrv_errors();
        $debugError = isset($errors[0]['message']) ? $errors[0]['message'] : 'Unknown DB Error';
        error_log("MRP Upsert Error: " . $debugError); // Catat di error_log server
        
        throw new Exception('Terjadi kesalahan pada Server Database saat menyimpan data In Plan.');
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_commit($conn); 

    echo json_encode(array(
        'status' => 'success', 
        'message' => 'Data In Plan MRP berhasil disimpan.'
    ));

} catch (Exception $e) {
    sqlsrv_rollback($conn); 
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
?>
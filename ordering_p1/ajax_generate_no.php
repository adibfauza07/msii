<?php
require_once __DIR__ . "/../config/database_ordering.php";

header("Content-Type: application/json; charset=utf-8");

if ($conn === false) {
    echo json_encode(array("success" => false, "message" => "Koneksi database gagal."));
    exit();
}

$cust_code = isset($_POST['CUST_CODE']) ? trim($_POST['CUST_CODE']) : '';
$di_date   = isset($_POST['DI_DATE']) ? trim($_POST['DI_DATE']) : date('Y-m-d');

if ($cust_code == '') {
    echo json_encode(array("success" => false, "message" => "Customer Code kosong."));
    exit();
}

// Ambil Tahun, Bulan, dan 2-digit Tahun dari DI_DATE
$ts = strtotime($di_date);
if ($ts === false) {
    $ts = time();
}

$year  = date('Y', $ts);
$month = date('m', $ts);
$yy    = date('y', $ts); 

// Mulai Transaksi agar nomor aman dari duplikasi (Race Condition)
if (!sqlsrv_begin_transaction($conn)) {
    echo json_encode(array("success" => false, "message" => "Gagal memulai transaksi database."));
    exit();
}

try {
    // 1. Cek apakah record sequence sudah ada.
    // WITH (UPDLOCK) mengunci baris ini sementara, agar tidak dibaca oleh komputer lain sampai proses ini selesai.
    $sql_check = "
        SELECT LAST_SEQ, INV_FORMAT, DS_FORMAT, DI_FORMAT 
        FROM MS_DI_SEQUENCE WITH (UPDLOCK) 
        WHERE CUST_CODE = ? AND SEQ_YEAR = ? AND SEQ_MONTH = ?
    ";
    $stmt_check = sqlsrv_query($conn, $sql_check, array($cust_code, $year, $month));

    if ($stmt_check === false) {
        throw new Exception("Query sequence gagal: " . print_r(sqlsrv_errors(), true));
    }

    $row = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);

    if (!$row) {
        // Jika belum ada, Insert baru ke tabel Sequence (Mulai dari 1)
        $next_seq   = 1;
        $inv_format = 'INV/[CUST]/[YY][MM]/[SEQ]';
        $ds_format  = 'DS/[CUST]/[YY][MM]/[SEQ]';
        $di_format  = '[YY][MM][SEQ]';
        
        $sql_ins = "
            INSERT INTO MS_DI_SEQUENCE 
            (CUST_CODE, SEQ_YEAR, SEQ_MONTH, LAST_SEQ, INV_FORMAT, DS_FORMAT, DI_FORMAT) 
            VALUES (?, ?, ?, 1, ?, ?, ?)
        ";
        $stmt_ins = sqlsrv_query($conn, $sql_ins, array($cust_code, $year, $month, $inv_format, $ds_format, $di_format));
        
        if ($stmt_ins === false) {
            throw new Exception("Gagal insert sequence baru: " . print_r(sqlsrv_errors(), true));
        }
    } else {
        // Jika ada, tambahkan 1 ke LAST_SEQ
        $next_seq   = intval($row['LAST_SEQ']) + 1;
        $inv_format = $row['INV_FORMAT'] ? $row['INV_FORMAT'] : 'INV/[CUST]/[YY][MM]/[SEQ]';
        $ds_format  = $row['DS_FORMAT'] ? $row['DS_FORMAT'] : 'DS/[CUST]/[YY][MM]/[SEQ]';
        $di_format  = $row['DI_FORMAT'] ? $row['DI_FORMAT'] : '[YY][MM][SEQ]';
        
        $sql_upd = "
            UPDATE MS_DI_SEQUENCE 
            SET LAST_SEQ = ? 
            WHERE CUST_CODE = ? AND SEQ_YEAR = ? AND SEQ_MONTH = ?
        ";
        $stmt_upd = sqlsrv_query($conn, $sql_upd, array($next_seq, $cust_code, $year, $month));
        
        if ($stmt_upd === false) {
            throw new Exception("Gagal update sequence: " . print_r(sqlsrv_errors(), true));
        }
    }

    // Kunci nomor secara permanen di database
    sqlsrv_commit($conn); 

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    echo json_encode(array("success" => false, "message" => $e->getMessage()));
    exit();
}

// Format sequence menjadi minimal 3 digit (contoh: 001, 015, 125)
$seq_str = str_pad($next_seq, 3, "0", STR_PAD_LEFT);

// Fungsi untuk mengganti tag format dengan nilai asli
function apply_format($format, $cust, $yy, $mm, $seq) {
    $res = str_replace("[CUST]", $cust, $format);
    $res = str_replace("[YY]", $yy, $res);
    $res = str_replace("[MM]", $mm, $res);
    $res = str_replace("[SEQ]", $seq, $res);
    return $res;
}

// Terapkan format
$di_no    = apply_format($di_format, $cust_code, $yy, $month, $seq_str);
$di_invno = apply_format($inv_format, $cust_code, $yy, $month, $seq_str);
$di_dsno  = apply_format($ds_format, $cust_code, $yy, $month, $seq_str);

// Kembalikan output JSON untuk diterima oleh JavaScript form utama
echo json_encode(array(
    "success"  => true,
    "DI_NO"    => $di_no,
    "DI_INVNO" => $di_invno,
    "DI_DSNO"  => $di_dsno
));
?>
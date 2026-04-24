<?php
// ======================================================================
require_once "../middleware/Auth.php";
require_once "../middleware/RoleCheck.php";
only(['p1','p2']);
require_once "../config/database.php";
// ======================================================================

if (!isset($_POST['part_id']) || $_POST['part_id'] == "") {
    die("Invalid Request. Part ID kosong.");
}

$part_id = intval($_POST['part_id']);
$qty     = intval($_POST['qty']);
$lot     = isset($_POST['lot']) ? $_POST['lot'] : "";
$tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : "";

// --- PERBAIKAN DI SINI ---
// Ambil part_name dari POST (input form), bukan dari database
$part_name = isset($_POST['part_name']) ? $_POST['part_name'] : "";
// -------------------------

// Query ambil part tetap perlu untuk mendapatkan part_code dan part_no asli
$sql = "SELECT * FROM data_barcode_showa WHERE id = ?";
$res = sqlsrv_query($conn, $sql, [$part_id]);
$p   = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);

if (!$p) {
    die("Data part tidak ditemukan.");
}

$part_code = $p['part_code'];
$part_no   = $p['part_no'];

// Jika input part_name di form kosong, baru pakai yang dari database (fallback)
if ($part_name == "") {
    $part_name = $p['part_name'];
}

$qr_path = "";

// INSERT ke tabel barcode_showa
$sql2 = "
INSERT INTO barcode_showa 
(part_code, part_no, part_name, qty_polibag, qty_box, qr_path, created_at)
VALUES 
(?, ?, ?, ?, ?, ?, GETDATE())
";

// Sekarang $part_name di sini berisi nilai yang sudah dikustom oleh user
$params2 = [
    $part_code,
    $part_no,
    $part_name, 
    $qty,
    $qty,
    $qr_path
];

$stmt = sqlsrv_query($conn, $sql2, $params2);

if ($stmt === false) {
    die("Insert Error: " . print_r(sqlsrv_errors(), true));
}

// GET LAST ID
$sql3 = "SELECT TOP 1 id FROM barcode_showa ORDER BY id DESC";
$last = sqlsrv_query($conn, $sql3);
$row  = sqlsrv_fetch_array($last, SQLSRV_FETCH_ASSOC);

$id = $row['id'];

// Tambahkan parameter lot ke dalam URL agar bisa dibaca oleh print_label.php
// Tambahkan parameter tgl ke dalam URL
header("Location: print_label.php?id=".$id."&lot=".urlencode($lot)."&tgl=".$tanggal);
exit;
?>

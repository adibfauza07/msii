<?php
// FILE: msii/qc/view_image.php
// VERSI KHUSUS DRIVER SQLSRV (Non-PDO)

// 1. Matikan error agar header gambar aman
error_reporting(0);
ini_set('display_errors', 0);

// 2. Bersihkan buffer output
if (ob_get_length()) ob_clean();

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 3. Load Database (Sama seperti sebelumnya)
$active_plant = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1';

// Trik buffer lagi saat load DB untuk membuang spasi bandel
ob_start();
if ($active_plant == 'p2') {
    if(!isset($_SESSION['erp_user'])) $_SESSION['erp_user'] = $_SESSION['db_user'];
    if(!isset($_SESSION['erp_pass'])) $_SESSION['erp_pass'] = $_SESSION['db_pass'];
    $_SESSION['server_sql'] = "192.168.0.9"; 
    require_once __DIR__ . '/../config/database.php'; 
} else {
    require_once __DIR__ . '/../config/database_p1.php'; 
}
ob_end_clean(); // Buang output dari file config

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 4. LOGIKA PENGAMBILAN DATA (SQLSRV STYLE)
if ($id > 0 && isset($conn)) {
    
    $sql = "SELECT gambar FROM car_claim_gambar WHERE car_id = ?";
    $params = array($id);

    // Gunakan sqlsrv_query (Bukan prepare/execute)
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt !== false) {
        if (sqlsrv_fetch($stmt)) {
            // Ambil data gambar sebagai Binary Stream
            $imageStream = sqlsrv_get_field($stmt, 0, SQLSRV_PHPTYPE_STREAM(SQLSRV_ENC_BINARY));
            
            if ($imageStream !== false) {
                header("Content-Type: image/jpeg");
                fpassthru($imageStream); // Kirim stream langsung ke browser
                exit;
            }
        }
    }
}

// 5. GAMBAR FALLBACK (JIKA GAGAL)
header("Content-Type: image/png");
$im = imagecreate(200, 200);
$bg = imagecolorallocate($im, 240, 240, 240);
$text_color = imagecolorallocate($im, 150, 150, 150);
$border = imagecolorallocate($im, 200, 200, 200);

imagerectangle($im, 0, 0, 199, 199, $border);
imagestring($im, 5, 60, 90, "NO IMAGE", $text_color);

imagepng($im);
imagedestroy($im);
?>
<?php
// FILE: msii/qc/cek_data.php
// Script Diagnosa untuk melihat isi data BLOB/Image tanpa merender gambar

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔍 DIAGNOSA DATA GAMBAR</h1>";
echo "<hr>";

// 1. CEK SESSION & KONEKSI
session_start();
$active_plant = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1';
echo "<b>1. Status Session:</b><br>";
echo "&nbsp;&nbsp; - Active Plant: " . $active_plant . "<br>";

$db_file = ($active_plant == 'p2') ? __DIR__ . '/../config/database.php' : __DIR__ . '/../config/database_p1.php';
echo "&nbsp;&nbsp; - Mencoba load DB: " . $db_file . "<br>";

if (file_exists($db_file)) {
    echo "&nbsp;&nbsp; - <span style='color:green'>File Database Ditemukan.</span><br>";
    
    // Load Database
    if ($active_plant == 'p2') {
        if(!isset($_SESSION['erp_user'])) $_SESSION['erp_user'] = $_SESSION['db_user'];
        if(!isset($_SESSION['erp_pass'])) $_SESSION['erp_pass'] = $_SESSION['db_pass'];
        $_SESSION['server_sql'] = "192.168.0.9"; 
        require_once $db_file;
    } else {
        require_once $db_file;
    }

    if (isset($conn)) {
        echo "&nbsp;&nbsp; - <span style='color:green'>Koneksi Berhasil ($active_plant)!</span><br>";
    } else {
        echo "&nbsp;&nbsp; - <span style='color:red'>GAGAL: Variabel \$conn tidak ditemukan!</span><br>";
        exit;
    }
} else {
    echo "&nbsp;&nbsp; - <span style='color:red'>CRITICAL: File database tidak ada!</span><br>";
    exit;
}

// 2. CEK DATA GAMBAR
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
echo "<br><b>2. Cek Data ID: $id</b><br>";

if ($id == 0) {
    echo "Silakan masukkan ID di URL. Contoh: <code>cek_data.php?id=10</code>";
    exit;
}

try {
    $sql = "SELECT car_id, gambar FROM car_claim_gambar WHERE car_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    
    // Coba fetch normal dulu
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        echo "&nbsp;&nbsp; - <span style='color:red'>Data TIDAK DITEMUKAN di tabel car_claim_gambar untuk ID $id</span><br>";
    } else {
        echo "&nbsp;&nbsp; - <span style='color:green'>Row Ditemukan!</span><br>";
        $rawData = $row['gambar'];
        
        // Cek Tipe Data
        echo "<br><b>3. Analisis Fisik Data:</b><br>";
        $type = gettype($rawData);
        echo "&nbsp;&nbsp; - Tipe Data PHP: <b>$type</b><br>";
        
        if ($type == 'resource') {
            echo "&nbsp;&nbsp; - Deteksi: Ini adalah <b>STREAM Resource</b> (Khas SQL Server Driver).<br>";
            // Baca isi stream
            $content = stream_get_contents($rawData);
        } else {
            $content = $rawData;
        }
        
        $len = strlen($content);
        echo "&nbsp;&nbsp; - Ukuran Data: <b>$len bytes</b> (" . round($len/1024, 2) . " KB)<br>";
        
        if ($len == 0) {
            echo "&nbsp;&nbsp; - <span style='color:red'>DATA KOSONG (0 bytes). Gambar rusak atau belum diupload.</span><br>";
        } else {
            // Cek Hex Header (Magic Bytes)
            $hex = bin2hex(substr($content, 0, 20));
            echo "&nbsp;&nbsp; - 20 Bytes Pertama (HEX): <code>$hex</code><br>";
            
            echo "<br><b>4. Kesimpulan Format:</b><br>";
            if (strpos($hex, 'ffd8') === 0) {
                echo "&nbsp;&nbsp; - <span style='color:green'>Valid JPEG Image (Start with FFD8)</span><br>";
            } elseif (strpos($hex, '89504e47') === 0) {
                echo "&nbsp;&nbsp; - <span style='color:green'>Valid PNG Image</span><br>";
            } elseif (strpos($hex, '424d') === 0) {
                echo "&nbsp;&nbsp; - <span style='color:green'>Valid BMP Image</span><br>";
            } else {
                echo "&nbsp;&nbsp; - <span style='color:orange'>UNKNOWN HEADER / OLE OBJECT?</span><br>";
                echo "&nbsp;&nbsp; - Kemungkinan data mengandung header tambahan (misal dari Delphi/Access).<br>";
            }
        }
    }

} catch (Exception $e) {
    echo "Error SQL: " . $e->getMessage();
}
?>
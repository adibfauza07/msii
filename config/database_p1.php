<?php
// 1. PASTIKAN SESI DIMULAI (WAJIB PALING ATAS)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ====================================================================
// PERBAIKAN: SERVER DINAMIS BERDASARKAN SESSION 'active_plant'
// ====================================================================
switch (isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1')
{
    case 'p2':
        $serverName = "192.168.0.9";
        break;

    case 'p1':
    default:
        $serverName = "192.168.0.4";
        break;
}
// ====================================================================

$databaseName = "msData";

$uid = "";
$pwd = "";
$should_connect = false;

// --- LOGIKA DETEKSI USER ---

// KASUS A: Sedang Proses Login (Input dari Form)
if (isset($is_login_process) && $is_login_process == true) {
    // Ambil variabel dari login.php
    if(isset($temp_username) && isset($temp_password)){
        $uid = $temp_username;
        $pwd = $temp_password;
        $should_connect = true;
        
        // Override server jika variabel dari login.php tersedia
        if (isset($serverCheck)) {
            $serverName = $serverCheck;
        }
    }
} 
// KASUS B: Sudah Login (Ada Sesi Tersimpan)
elseif (isset($_SESSION['db_user']) && !empty($_SESSION['db_user'])) {
    $uid = $_SESSION['db_user'];
    $pwd = $_SESSION['db_pass'];
    $should_connect = true;
} 
// KASUS C: Belum Login Sama Sekali
else {
    // Jika file ini dipanggil oleh halaman lain (bukan login.php), TENDANG KELUAR
    if (!defined('LOGIN_PAGE')) {
        header("Location: login.php");
        exit();
    }
    // Jika file ini dipanggil oleh login.php, DIAM SAJA (Jangan connect, biar form login muncul)
    $conn = false;
    return; 
}

// --- EKSEKUSI KONEKSI (Hanya jika kredensial ada) ---
if ($should_connect) {
    $connectionOptions = array(
        "Database" => $databaseName,
        "Uid" => $uid,
        "PWD" => $pwd,
        "CharacterSet" => "UTF-8"
    );

    // Coba Connect
    $conn = sqlsrv_connect($serverName, $connectionOptions);
    
    // Jika koneksi gagal saat sudah punya sesi (misal password ganti atau User tidak ada di server tujuan), hapus sesi & tendang
    if ($conn === false && !defined('LOGIN_PAGE')) {
        // Debugging (Opsional): Uncomment baris bawah ini jika ingin lihat pesan error sebelum redirect
        // die(print_r(sqlsrv_errors(), true)); 
        
        session_destroy();
        header("Location: login.php?error=session_expired");
        exit();
    }
} else {
    $conn = false;
}
?>
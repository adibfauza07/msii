<?php
// Pastikan session selalu dimulai di awal[cite: 3]
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$databaseName = "msData";

// Ambil path folder saat ini yang diakses user[cite: 2]
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : "";

// Set default fallback[cite: 4]
$serverName = "192.168.0.4"; 
$detectedPlant = "p1";

/*
    AUTO DETECT BERDASARKAN FOLDER
    Mendeteksi apakah user berada di qc_p1 atau qc_p2
*/
if (strpos($scriptName, "/qc_p1/") !== false) {
    $serverName = "192.168.0.4";
    $detectedPlant = "p1";
} elseif (strpos($scriptName, "/qc_p2/") !== false) {
    $serverName = "192.168.0.9";
    $detectedPlant = "p2";
} else {
    // Fallback jika tidak ada folder yang cocok, cek session[cite: 2]
    if (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == "p2") {
        $serverName = "192.168.0.9";
        $detectedPlant = "p2";
    } else {
        $serverName = "192.168.0.4";
        $detectedPlant = "p1";
    }
}

// Simpan hasil deteksi ke session agar bisa dipakai secara global[cite: 2]
$_SESSION['active_plant'] = $detectedPlant;
$_SESSION['active_server'] = $serverName;

$uid = "";
$pwd = "";
$should_connect = false;

/* KASUS A: Dipanggil saat proses validasi form login[cite: 2] */
if (isset($is_login_process) && $is_login_process == true) {
    if (isset($temp_username) && isset($temp_password)) {
        $uid = $temp_username;
        $pwd = $temp_password;
        $should_connect = true;

        if (isset($serverCheck) && $serverCheck != "") {
            $serverName = $serverCheck;
            // Sinkronkan active_plant dengan server yang dipilih saat login
            if ($serverCheck == "192.168.0.9") {
                $_SESSION['active_plant'] = "p2";
            } else {
                $_SESSION['active_plant'] = "p1";
            }
            $_SESSION['active_server'] = $serverName;
        }
    }
}
/* KASUS B: User sudah berhasil login sebelumnya[cite: 2] */
elseif (isset($_SESSION['db_user']) && $_SESSION['db_user'] != "") {
    $uid = $_SESSION['db_user'];
    $pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "";
    $should_connect = true;
}
/* KASUS C: User belum login, tendang ke halaman login tunggal[cite: 2] */
else {
    if (!defined('LOGIN_PAGE')) {
        // UBAH PATH INI sesuai dengan lokasi halaman login QC kamu
        header("Location: /msii/qc_login/login.php"); 
        exit();
    }
    $conn = false;
    return;
}

/* KONEKSI KE SQL SERVER[cite: 2] */
if ($should_connect) {
    $connectionOptions = array(
        "Database" => $databaseName,
        "Uid" => $uid,
        "PWD" => $pwd,
        "CharacterSet" => "UTF-8"
    );

    $conn = sqlsrv_connect($serverName, $connectionOptions);

    // Tendang jika koneksi gagal saat tidak di halaman login[cite: 4]
    if ($conn === false && !defined('LOGIN_PAGE')) {
        session_destroy();
        // UBAH PATH INI sesuai dengan lokasi halaman login QC kamu
        header("Location: /msii/qc_login/login.php?error=session_expired");
        exit();
    }
} else {
    $conn = false;
}

// Fungsi helper query disatukan agar lebih rapi[cite: 1]
if (!function_exists('q')) {
    function q($sql, $params = []) {
        global $conn; 
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            die("Query Error di fungsi q(): " . print_r(sqlsrv_errors(), true));
        }
        return $stmt;
    }
}
?>
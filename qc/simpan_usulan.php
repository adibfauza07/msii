<?php
// FILE: msii/qc/simpan_usulan.php

// ==========================================
// 1. KONEKSI DINAMIS (Anti-Error Session)
// ==========================================
if (session_status() == PHP_SESSION_NONE) { session_start(); }
$active_plant = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1';

if ($active_plant == 'p2') {
    if(!isset($_SESSION['erp_user'])) $_SESSION['erp_user'] = $_SESSION['db_user'];
    if(!isset($_SESSION['erp_pass'])) $_SESSION['erp_pass'] = $_SESSION['db_pass'];
    $_SESSION['server_sql'] = "192.168.0.9"; 
    require_once __DIR__ . '/../config/database.php';
} else {
    require_once __DIR__ . '/../config/database_p1.php';
}


// Pastikan session dan koneksi database sudah di-include di atas ini
// require_once __DIR__ . '/../config/database.php';

// Tangkap data
$kode_usul = isset($_POST['kode_usul']) ? $_POST['kode_usul'] : '';
$active_plant = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p2';

// ==========================================
// 1. VALIDASI KODE USULAN (Cegah Duplikat)
// ==========================================
$sqlCek = "SELECT COUNT(*) AS total FROM USULAN_PERUBAHAN WHERE KODE_USUL = ?";
$paramsCek = array($kode_usul);
$resCek = sqlsrv_query($conn, $sqlCek, $paramsCek);

$rowCek = sqlsrv_fetch_array($resCek, SQLSRV_FETCH_ASSOC);
if ($rowCek['total'] > 0) {
    // Jika kode sudah terpakai, kembalikan ke halaman input dengan notifikasi
    echo "<script>
            alert('Gagal menyimpan: Kode Usulan {$kode_usul} sudah digunakan. Sistem akan membuatkan kode baru secara otomatis.');
            window.location.href = 'dashboard_qc.php?page=input_usulan&plant={$active_plant}';
          </script>";
    exit; // Hentikan eksekusi script ke bawah
}

// ==========================================
// 2. PROSES PENYIMPANAN DATA
// ==========================================
// Cek apakah data dikirim dari form (method POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Ambil semua data dari form
    $kode_usul     = $_POST['kode_usul'];
    $issue_date    = $_POST['issue_date'];
    $cust_id       = $_POST['cust_id'];
    $judul_dok     = $_POST['judul_dok'];
    $item_id       = !empty($_POST['item_id']) ? $_POST['item_id'] : null;
    $pj            = $_POST['pj'];
    $revisi_1      = !empty($_POST['revisi_1']) ? $_POST['revisi_1'] : null;
    $isi_revisi    = $_POST['isi_revisi'];
    $alasan_revisi = $_POST['alasan_revisi'];
    $plant_date    = !empty($_POST['plant_date']) ? $_POST['plant_date'] : null;
    $target_date   = !empty($_POST['target_date']) ? $_POST['target_date'] : null;
    $remarks       = $_POST['remarks'];

    // Handle Tipe Usulan (Radio Button)
    $tipe_usulan = isset($_POST['tipe_usulan']) ? $_POST['tipe_usulan'] : 'BARU';
    $baru        = ($tipe_usulan == 'BARU') ? 1 : 0;
    $revisi      = ($tipe_usulan == 'REVISI') ? 1 : 0;
    
    // Handle Dokumen Terkait (Checkboxes)
    $is_std        = isset($_POST['is_std']) ? 1 : 0;
    $wi            = isset($_POST['wi']) ? 1 : 0;
    $check_point   = isset($_POST['check_point']) ? 1 : 0;
    $std_pack      = isset($_POST['std_pack']) ? 1 : 0;
    $fmea          = isset($_POST['fmea']) ? 1 : 0;
    $qcpc          = isset($_POST['qcpc']) ? 1 : 0;
    $setting_par   = isset($_POST['setting_par']) ? 1 : 0;
    $other         = isset($_POST['other']) ? 1 : 0;

    // Siapkan query INSERT
    $sql = "
    INSERT INTO USULAN_PERUBAHAN (
        KODE_USUL, BARU, REVISI, JUDUL_DOK, CUST_ID, ITEM_ID, PJ, 
        ISI_REVISI, ALASAN_REVISI, REVISI_1, QCPC, IS_STD, FMEA, WI, 
        STD_PACK, CHECK_POINT, SETTING_PAR, OTHER, 
        ISSUE_DATE, PLANT_DATE, TARGET_DATE, REMARKS
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, 
        ?, ?, ?, ?, ?, ?, ?, 
        ?, ?, ?, ?, 
        ?, ?, ?, ?
    )";

    // Siapkan parameter
    $params = [
        $kode_usul, $baru, $revisi, $judul_dok, $cust_id, $item_id, $pj,
        $isi_revisi, $alasan_revisi, $revisi_1, $qcpc, $is_std, $fmea, $wi,
        $std_pack, $check_point, $setting_par, $other,
        $issue_date, $plant_date, $target_date, $remarks
    ];

    // Eksekusi query
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        // Jika insert berhasil, coba eksekusi SP
        $sql_sp = "EXEC sp_update_usulan";
        sqlsrv_query($conn, $sql_sp);

        // --- REDIRECT DINAMIS SESUAI PLANT ---
        // Jika Plant 1, arahkan kembali ke menu Plant 1
        if ($active_plant == 'p1') {
            header("Location: dashboard_qc.php?page=usulan_perubahan_p1&plant=p1&status=sukses");
        } else {
            header("Location: dashboard_qc.php?page=usulan_perubahan&plant=p2&status=sukses");
        }
        exit();

    } else {
        // Jika query gagal
        echo "<h1 style='color:red;'>Gagal menyimpan data!</h1>";
        echo "Error: <pre>";
        die(print_r(sqlsrv_errors(), true));
        echo "</pre>";
    }

} else {
    // Jika file ini diakses langsung tanpa method POST
    echo "Akses tidak diizinkan.";
    header("Location: dashboard_qc.php");
    exit();
}
?>
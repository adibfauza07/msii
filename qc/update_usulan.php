<?php
// FILE: msii/qc/update_usulan.php

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

// ==========================================
// 2. PROSES UPDATE DATA
// ==========================================
// Cek apakah data dikirim dari form (method POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Ambil KODE_USUL dari hidden input. Ini adalah KUNCI-nya.
    $kode_usul_edit = $_POST['kode_usul_edit'];

    // Ambil semua data lain dari form
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

    // === Validasi Panjang Data (PENTING untuk UPDATE juga) ===
    if (strlen($pj) > 20) {
        die("<h1>Error: 'Request By (PJ)' terlalu panjang.</h1> <p>Maksimal 20 karakter.</p><a href='javascript:history.back()'>Kembali</a>");
    }
    if (strlen($judul_dok) > 50) {
        die("<h1>Error: 'Document Name' terlalu panjang.</h1> <p>Maksimal 50 karakter.</p><a href='javascript:history.back()'>Kembali</a>");
    }
    if (strlen($remarks) > 50) {
        die("<h1>Error: 'Remark' terlalu panjang.</h1> <p>Maksimal 50 karakter.</p><a href='javascript:history.back()'>Kembali</a>");
    }
    if (strlen($isi_revisi) > 250) {
        die("<h1>Error: 'Isi Revisi' terlalu panjang.</h1> <p>Maksimal 250 karakter.</p><a href='javascript:history.back()'>Kembali</a>");
    }
    if (strlen($alasan_revisi) > 250) {
        die("<h1>Error: 'Alasan Revisi' terlalu panjang.</h1> <p>Maksimal 250 karakter.</p><a href='javascript:history.back()'>Kembali</a>");
    }
    // === Akhir Validasi ===

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

    // Siapkan query UPDATE
    $sql = "
    UPDATE USULAN_PERUBAHAN SET
        ISSUE_DATE = ?, CUST_ID = ?, ITEM_ID = ?, JUDUL_DOK = ?, PJ = ?,
        ISI_REVISI = ?, ALASAN_REVISI = ?, REVISI_1 = ?, BARU = ?, REVISI = ?,
        QCPC = ?, IS_STD = ?, FMEA = ?, WI = ?, STD_PACK = ?, CHECK_POINT = ?, SETTING_PAR = ?, OTHER = ?,
        PLANT_DATE = ?, TARGET_DATE = ?, REMARKS = ?
    WHERE KODE_USUL = ? 
    ";

    // Siapkan parameter
    $params = [
        $issue_date, $cust_id, $item_id, $judul_dok, $pj,
        $isi_revisi, $alasan_revisi, $revisi_1, $baru, $revisi,
        $qcpc, $is_std, $fmea, $wi, $std_pack, $check_point, $setting_par, $other,
        $plant_date, $target_date, $remarks,
        $kode_usul_edit // Parameter terakhir untuk WHERE
    ];

    // Eksekusi query
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        // Jika update berhasil:
        $sql_sp = "EXEC sp_update_usulan";
        sqlsrv_query($conn, $sql_sp);

        // --- REDIRECT DINAMIS SESUAI PLANT ---
        if ($active_plant == 'p1') {
            header("Location: dashboard_qc.php?page=usulan_perubahan_p1&plant=p1&status=sukses");
        } else {
            header("Location: dashboard_qc.php?page=usulan_perubahan&plant=p2&status=sukses");
        }
        exit();

    } else {
        // Jika query gagal
        echo "<h1 style='color:red;'>Gagal mengupdate data!</h1>";
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
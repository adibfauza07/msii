<?php
require_once '../config/database_p1.php';

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
    // Jika checkbox tidak dicentang, ia tidak akan mengirim nilai. 
    // Jadi kita cek 'isset' untuk menilainya 1 (true) atau 0 (false).
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
    // Urutan harus SAMA PERSIS dengan kolom di query INSERT
    $params = [
        $kode_usul, $baru, $revisi, $judul_dok, $cust_id, $item_id, $pj,
        $isi_revisi, $alasan_revisi, $revisi_1, $qcpc, $is_std, $fmea, $wi,
        $std_pack, $check_point, $setting_par, $other,
        $issue_date, $plant_date, $target_date, $remarks
    ];

    // Eksekusi query
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        // Jika insert berhasil:
        
        // 1. Jalankan Stored Procedure untuk update ITEM_NO
        $sql_sp = "EXEC sp_update_usulan";
        sqlsrv_query($conn, $sql_sp);

        // 2. Redirect kembali ke halaman dashboard
        header("Location: usulan_perubahan.php?status=sukses");
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
    header("Location: usulan_perubahan.php");
    exit();
}
?>
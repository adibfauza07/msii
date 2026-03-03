<?php
require_once '../config/database_p1.php';

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
    // Kita tidak validasi kode_usul_edit, karena itu kuncinya
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
    // Berbeda dengan INSERT, kita pakai "SET" dan "WHERE"
    $sql = "
    UPDATE USULAN_PERUBAHAN SET
        ISSUE_DATE = ?,
        CUST_ID = ?,
        ITEM_ID = ?,
        JUDUL_DOK = ?,
        PJ = ?,
        ISI_REVISI = ?,
        ALASAN_REVISI = ?,
        REVISI_1 = ?,
        BARU = ?,
        REVISI = ?,
        QCPC = ?,
        IS_STD = ?,
        FMEA = ?,
        WI = ?,
        STD_PACK = ?,
        CHECK_POINT = ?,
        SETTING_PAR = ?,
        OTHER = ?,
        PLANT_DATE = ?,
        TARGET_DATE = ?,
        REMARKS = ?
    WHERE 
        KODE_USUL = ? 
    "; // Kunci utama ada di WHERE

    // Siapkan parameter
    // Urutan harus SAMA PERSIS dengan kolom di query UPDATE
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
        
        // 1. Jalankan Stored Procedure untuk update ITEM_NO (jika diperlukan)
        $sql_sp = "EXEC sp_update_usulan";
        sqlsrv_query($conn, $sql_sp);

        // 2. Redirect kembali ke halaman dashboard
        header("Location: usulan_perubahan.php?status=diupdate");
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
    header("Location: usulan_perubahan.php");
    exit();
}
?>
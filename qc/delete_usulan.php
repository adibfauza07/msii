<?php
<?php
require_once '../config/database.php'; // Koneksi P2

// (DIUBAH) Ambil data dari POST, bukan GET
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_id'])) {
    
    $kode_usul = $_POST['delete_id']; // (DIUBAH)

    $sql = "DELETE FROM USULAN_PERUBAHAN WHERE KODE_USUL = ?";
    $params = [$kode_usul];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        echo "<h1 style='color:red;'>Gagal menghapus data!</h1>";
        echo "Error: <pre>";
        die(print_r(sqlsrv_errors(), true));
        echo "</pre>";
    }

    // (DIUBAH) Redirect kembali DENGAN MEMBAWA SEMUA FILTER
    // Hapus 'delete_id' agar tidak ter-loop
    unset($_POST['delete_id']); 
    // Bangun ulang query string filter
    $query_string = http_build_query($_POST);
    
    // (DIUBAH) Redirect ke dashboard, BUKAN ke file aslinya, agar sidebar tetap ada
    header("Location: dashboard_qc.php?page=p2&" . $query_string . "&status=dihapus");
    exit();

} else {
    // Jika tidak ada parameter, tendang balik
    echo "Akses tidak valid.";
    header("Location: usulan_perubahan.php");
    exit();
}
?>
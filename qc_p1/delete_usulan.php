<?php
require_once '../config/Database_p1.php'; // Koneksi P1

// Ambil data dari POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_id'])) {
    
    $kode_usul = $_POST['delete_id']; 

    $sql = "DELETE FROM USULAN_PERUBAHAN WHERE KODE_USUL = ?";
    $params = [$kode_usul];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        echo "<h1 style='color:red;'>Gagal menghapus data!</h1>";
        echo "Error: <pre>";
        die(print_r(sqlsrv_errors(), true));
        echo "</pre>";
    }

    unset($_POST['delete_id']); 
    $query_string = http_build_query($_POST);
    
    // Redirect ke dashboard P1
    header("Location: ../qc/dashboard_qc.php?page=p1&" . $query_string . "&status=dihapus");
    exit();

} else {
    echo "Akses tidak valid.";
    header("Location: usulan_perubahan.php");
    exit();
}
?>
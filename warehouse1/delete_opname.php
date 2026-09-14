<?php
// Pastikan koneksi database dimuat
require_once __DIR__ . "/../config/global.php";

// Set header response agar dibaca sebagai JSON oleh AJAX
header('Content-Type: application/json');

// Pastikan request adalah POST dan membawa parameter tag_no
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tag_no'])) {
    
    // Ambil nomor tag dari request AJAX
    $tag_no = trim($_POST['tag_no']);

    if (empty($tag_no) || $tag_no === '-') {
        echo json_encode(['status' => 'error', 'message' => 'Tag No tidak valid.']);
        exit;
    }

    // Query untuk menghapus data dari tabel TAGS berdasarkan TAG_NO
    // (Menggunakan Parameterized Query untuk keamanan dari SQL Injection)
    $sql = "DELETE FROM TAGS WHERE TAG_NO = ?";
    $params = array($tag_no);

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        // Jika berhasil dihapus
        echo json_encode([
            'status' => 'success', 
            'message' => 'Data dengan Tag No ' . htmlspecialchars($tag_no) . ' berhasil dihapus permanen.'
        ]);
    } else {
        // Jika query gagal, tangkap pesan error dari SQL Server
        $errors = sqlsrv_errors();
        $errorMsg = 'Gagal menghapus data di database.';
        if ($errors != null) {
            $errorMsg = $errors[0]['message'];
        }
        
        echo json_encode([
            'status' => 'error', 
            'message' => $errorMsg
        ]);
    }
} else {
    // Jika diakses tidak menggunakan metode POST
    echo json_encode(['status' => 'error', 'message' => 'Permintaan tidak valid.']);
}
?>
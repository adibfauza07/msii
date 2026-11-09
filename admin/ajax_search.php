<?php
// Matikan error reporting sementara agar pesan error/warning tidak merusak output JSON
error_reporting(0); 
require_once __DIR__ . "/../config/global.php";

$term = isset($_GET['term']) ? $_GET['term'] : '';
$result = array();

if ($term !== '') {
    $sql = "SELECT id, kode_dokumen, info_subject FROM tbl_informasi_internal WHERE kode_dokumen LIKE ? OR info_subject LIKE ?";
    $params = array("%$term%", "%$term%");
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $result[] = array(
                'id' => $row['id'],
                'value' => $row['kode_dokumen'], // Teks yang masuk ke kolom input saat dipilih
                'label' => $row['kode_dokumen'] . ' - ' . $row['info_subject'] // Teks yang tampil di daftar dropdown
            );
        }
    }
}

// Pastikan tidak ada spasi atau HTML sebelum baris ini
header('Content-Type: application/json');
echo json_encode($result);
exit;
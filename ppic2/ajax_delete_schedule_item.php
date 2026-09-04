<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . "/../config/global.php"; 

$databaseName = "msData";
$serverName = isset($_SESSION['active_server']) ? $_SESSION['active_server'] : "192.168.0.9";
$uid = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : "";
$pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "";

$connectionOptions = array("Database" => $databaseName, "CharacterSet" => "UTF-8");
if ($uid !== "") { $connectionOptions["Uid"] = $uid; $connectionOptions["PWD"] = $pwd; }
$conn = @sqlsrv_connect($serverName, $connectionOptions);

$id_no = isset($_POST['id_no']) ? (int)$_POST['id_no'] : 0;

if ($id_no > 0) {
    // 1. Hapus riwayat matriks 31 harinya
    $sqlDtl = "DELETE FROM RPT_PPIC_DTL WHERE ID_NO = ?";
    sqlsrv_query($conn, $sqlDtl, array($id_no));

    // 2. Hapus header utamanya (Ini yang krusial agar tidak jadi sampah data)
    $sqlHdr = "DELETE FROM RPT_PPIC WHERE ID_NO = ?";
    $stmtHdr = sqlsrv_query($conn, $sqlHdr, array($id_no));

    if ($stmtHdr) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data utama di database.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID_NO tidak valid.']);
}
?>
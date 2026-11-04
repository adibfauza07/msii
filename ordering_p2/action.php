<?php
require_once __DIR__ . "/../config/database_ordering.php";

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action == 'clear') {
    $sql = "DELETE FROM ORDR_IMPORT_XLS";
    if (sqlsrv_query($conn, $sql)) {
        echo "Semua data staging berhasil dihapus";
    } else {
        echo "Gagal hapus staging.";
    }
} 
elseif ($action == 'import') {
    // Jalankan stored procedure import
    $stmt1 = sqlsrv_query($conn, "EXEC sp_import_po_from_staging");
    
    if($stmt1 === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    // Hitung PO yang berhasil diimport
    $sqlOK = "SELECT COUNT(DISTINCT ORDR_PO) AS CNT FROM ORDR_IMPORT_XLS WHERE IMPORT_STATUS = 'Y'";
    $stmtOK = sqlsrv_query($conn, $sqlOK);
    $rowOK = sqlsrv_fetch_array($stmtOK, SQLSRV_FETCH_ASSOC);
    $vImportOK = $rowOK['CNT'];

    // Hitung PO yang sudah ada
    $sqlExist = "SELECT COUNT(DISTINCT ORDR_PO) AS CNT FROM ORDR_IMPORT_XLS WHERE IMPORT_STATUS = 'E'";
    $stmtExist = sqlsrv_query($conn, $sqlExist);
    $rowExist = sqlsrv_fetch_array($stmtExist, SQLSRV_FETCH_ASSOC);
    $vExistPO = $rowExist['CNT'];

    echo "Import PO selesai\n\nPO berhasil diimport : " . $vImportOK . "\nPO sudah ada : " . $vExistPO;
}
?>
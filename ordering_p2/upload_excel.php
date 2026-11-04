<?php
// Pastikan path ini sudah benar sesuai dengan struktur folder Anda
require_once __DIR__ . "/../config/database_ordering.php";
require_once __DIR__ . "/../../PHPExcel/Classes/PHPExcel.php";

if(isset($_FILES['fileExcel']['name'])) {
    $tmpfname = $_FILES['fileExcel']['tmp_name'];
    
    // Load File Excel
    $excelReader = PHPExcel_IOFactory::createReaderForFile($tmpfname);
    $excelObj = $excelReader->load($tmpfname);
    $worksheet = $excelObj->getSheet(0);
    $lastRow = $worksheet->getHighestRow();
    
    sqlsrv_begin_transaction($conn);
    
    try {
        $sql = "INSERT INTO ORDR_IMPORT_XLS (ORDR_PO, ITEM_NO, ORDR_DATE, ORDP_QTY) VALUES (?, ?, CONVERT(datetime, ?, 112), ?)";
        
        for ($row = 2; $row <= $lastRow; $row++) {
            $vPO = $worksheet->getCell('A'.$row)->getValue();
            
            // --- FIX: Mencegah nomor PO berubah menjadi 1.0031e+009 ---
            if (is_numeric($vPO)) {
                $vPO = number_format($vPO, 0, '', '');
            }
            // ----------------------------------------------------------
            
            $vItemNo = $worksheet->getCell('B'.$row)->getValue();
            $vDateStr = $worksheet->getCell('C'.$row)->getValue(); // Format YYYYMMDD
            $vQty = $worksheet->getCell('D'.$row)->getValue();
            
            if(trim($vPO) != '') {
                $params = array($vPO, $vItemNo, $vDateStr, $vQty);
                $stmt = sqlsrv_query($conn, $sql, $params);
                if($stmt === false) throw new Exception("Error saat insert baris $row");
            }
        }
        
        // Menjalankan SP untuk mengisi harga otomatis
        $stmt_price = sqlsrv_query($conn, "EXEC sp_fill_price_staging");
        if($stmt_price === false) throw new Exception("Error pada sp_fill_price_staging");
        
        sqlsrv_commit($conn);
        echo "Load Excel & auto price selesai";
        
    } catch(Exception $e) {
        sqlsrv_rollback($conn);
        echo "ERROR: " . $e->getMessage();
    }
}
?>
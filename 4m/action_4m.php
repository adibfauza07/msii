<?php
// Pastikan file koneksi database terhubung dengan benar
require_once __DIR__ . '/../config/database.php';

if (isset($_POST['btnSimpan'])) {
    $control_id   = isset($_POST['control_id']) ? intval($_POST['control_id']) : 0;
    $control_no   = trim($_POST['control_no']);
    $control_date = trim($_POST['control_date']);
    $dep_code     = trim($_POST['dep_code']);
    $pic_name     = trim($_POST['pic_name']);
    $item_id      = trim($_POST['item_id']);
    $model        = trim($_POST['model']);
    
    // PERBAIKAN 1: Tampung teks deskripsi material (misal: POM DURACON) ke variabel penampung teks deskripsi
    $material_txt = isset($_POST['material_id']) ? trim($_POST['material_id']) : ''; 
    
    $reason       = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $bef_change   = isset($_POST['bef_change']) ? trim($_POST['bef_change']) : '';
    $aft_change   = isset($_POST['aft_change']) ? trim($_POST['aft_change']) : '';
    $status       = trim($_POST['status']);
    $prepared     = trim($_POST['prepared']);
    
    // Teks Review Departemen
    $pe_remark   = isset($_POST['pe_remark']) ? trim($_POST['pe_remark']) : '';
    $qc_remark   = isset($_POST['qc_remark']) ? trim($_POST['qc_remark']) : '';
    $mold_remark = isset($_POST['mold_remark']) ? trim($_POST['mold_remark']) : '';
    $ppic_remark = isset($_POST['ppic_remark']) ? trim($_POST['ppic_remark']) : '';
    $prod_remark = isset($_POST['prod_remark']) ? trim($_POST['prod_remark']) : '';
    $mkt_remark  = isset($_POST['mkt_remark']) ? trim($_POST['mkt_remark']) : '';

    // Logika Kategori Bit / Boolean Checklist 4M
    $man         = isset($_POST['man']) ? 1 : 0;
    $machine     = isset($_POST['machine']) ? 1 : 0;
    $method      = isset($_POST['method']) ? 1 : 0;
    $material_4m = isset($_POST['material_4m']) ? 1 : 0;
    $internal    = isset($_POST['internal']) ? 1 : 0;
    $customer    = isset($_POST['customer']) ? 1 : 0;
    $supplier    = isset($_POST['supplier']) ? 1 : 0;
    $perm        = isset($_POST['perm']) ? intval($_POST['perm']) : 1;

    if ($control_id > 0) {
        // --- MODE UPDATE DATA PCIS ---
        // PERBAIKAN 2: Mengubah field target dari MATERIAL_ID (INT) menjadi MATERIAL (VARCHAR/Teks)
        $sql = "UPDATE PROSES_CHANGE SET 
                    CONTROL_NO = ?, CONTROL_DATE1 = ?, DEP_CODE = ?, PIC_NAME = ?, 
                    ITEM_ID = ?, MODEL = ?, MATERIAL = ?, MAN = ?, MACHINE = ?, 
                    METHOD = ?, MATERIAL = ?, INTERNAL = ?, CUSTOMER = ?, SUPPLIER = ?, 
                    PERMANENT_CHANGE = ?, REASON = ?, BEF_CHANGE = ?, AFT_CHANGE = ?, STATUS = ?, IMC_PREPARED = ?,
                    PE_REMARK = ?, QC_REMARK = ?, MOLDSHOP_REMARK = ?, PPIC_REMARK = ?, PRODUCTION_REMARK = ?, MARKETING_REMARK = ?
                WHERE CONTROL_ID = ?";
        
        $params = array(
            $control_no, $control_date, $dep_code, $pic_name, $item_id, $model, $material_txt, // <-- Diisi variabel string bersih
            $man, $machine, $method, $material_4m, $internal, $customer, $supplier, $perm, 
            $reason, $bef_change, $aft_change, $status, $prepared,
            $pe_remark, $qc_remark, $mold_remark, $ppic_remark, $prod_remark, $mkt_remark,
            $control_id
        );
        $msg = "Data PCIS berhasil diperbarui!";
    } else {
        // --- MODE INSERT DATA BARU ---
        // PERBAIKAN 3: Mengubah field target insert ke kolom MATERIAL teks deskripsi
        $sql = "INSERT INTO PROSES_CHANGE (
                    CONTROL_NO, CONTROL_DATE1, DEP_CODE, PIC_NAME, ITEM_ID, MODEL, MATERIAL, 
                    MAN, MACHINE, METHOD, MATERIAL, INTERNAL, CUSTOMER, SUPPLIER, PERMANENT_CHANGE, 
                    REASON, BEF_CHANGE, AFT_CHANGE, STATUS, IMC_PREPARED, PE_REMARK, QC_REMARK, MOLDSHOP_REMARK, PPIC_REMARK, PRODUCTION_REMARK, MARKETING_REMARK
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = array(
            $control_no, $control_date, $dep_code, $pic_name, $item_id, $model, $material_txt, // <-- Diisi variabel string bersih
            $man, $machine, $method, $material_4m, $internal, $customer, $supplier, $perm, 
            $reason, $bef_change, $aft_change, $status, $prepared,
            $pe_remark, $qc_remark, $mold_remark, $ppic_remark, $prod_remark, $mkt_remark
        );
        $msg = "Data PCIS baru berhasil disimpan!";
    }
    
    // Eksekusi statement kueri secara aman menggunakan driver SQLSRV bawaan
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) { 
        die(print_r(sqlsrv_errors(), true)); 
    }
    
    echo "<script>
            alert('$msg');
            window.location.href = 'dashboard_4m.php?page=history';
          </script>";
    exit();
}
?>
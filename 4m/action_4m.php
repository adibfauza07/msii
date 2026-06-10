<?php
// Pastikan file koneksi database terhubung dengan benar
require_once __DIR__ . '/../config/database.php';

if (isset($_POST['btnSimpan'])) {
    $control_id   = isset($_POST['control_id']) ? intval($_POST['control_id']) : 0;
    $control_no   = trim($_POST['control_no']);
    $control_date = trim($_POST['control_date']);
    $dep_code     = trim($_POST['dep_code']);
    $pic_name     = trim($_POST['pic_name']);
    $item_id      = (!empty($_POST['item_id'])) ? intval($_POST['item_id']) : null; // Mapping ke [ITEM_ID] [int]
    $model        = trim($_POST['model']);
    
    // Teks deskripsi nama material (misal: POM DURACON NW-02 BLACK)
    $material_txt = isset($_POST['material_txt']) ? trim($_POST['material_txt']) : ''; 
    
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

    // Logika Kategori Bit / Boolean Checklist 4M (0 atau 1 sesuai skema database)
    $man         = isset($_POST['man']) ? 1 : 0;
    $machine     = isset($_POST['machine']) ? 1 : 0;
    $method      = isset($_POST['method']) ? 1 : 0;
    $material_4m = isset($_POST['material_4m']) ? 1 : 0;
    $other       = isset($_POST['other']) ? 1 : 0; // Tambahan kolom [OTHER] [bit] dari skema tabel baru
    $internal    = isset($_POST['internal']) ? 1 : 0;
    $customer    = isset($_POST['customer']) ? 1 : 0;
    $supplier    = isset($_POST['supplier']) ? 1 : 0;
    $perm        = isset($_POST['perm']) ? intval($_POST['perm']) : 1;
    $need_cust   = isset($_POST['need_customer']) ? intval($_POST['need_customer']) : 1; // Mapping ke [NEED_CUSTOMER] [bit]

    // Checklist Dokumen Attachment [bit]
    $f_email     = isset($_POST['attach_email']) ? 1 : 0;
    $f_drawing   = isset($_POST['attach_drawing']) ? 1 : 0;
    $f_sample    = isset($_POST['attach_sample']) ? 1 : 0;
    $f_data      = isset($_POST['attach_data']) ? 1 : 0;

    // SOLUSI JITU: Simpan data string teks deskripsi material ke dalam field [PE_REMARK] agar aman dari crash tipe data
    if (!empty($material_txt)) {
        $pe_remark = $material_txt;
    }

    if ($control_id > 0) {
        // --- MODE UPDATE DATA PCIS ---
        // Urutan kolom diselaraskan lurus dengan data array params (Bebas dari duplikasi kolom MATERIAL!)
        $sql = "UPDATE PROSES_CHANGE SET 
                    CONTROL_NO = ?, CONTROL_DATE1 = ?, DEP_CODE = ?, PIC_NAME = ?, 
                    INTERNAL = ?, CUSTOMER = ?, SUPPLIER = ?, ITEM_ID = ?, MODEL = ?, 
                    MAN = ?, MACHINE = ?, METHOD = ?, MATERIAL = ?, OTHER = ?, PERMANENT_CHANGE = ?, 
                    REASON = ?, NEED_CUSTOMER = ?, BEF_CHANGE = ?, AFT_CHANGE = ?, STATUS = ?, IMC_PREPARED = ?,
                    PE_REMARK = ?, QC_REMARK = ?, MOLDSHOP_REMARK = ?, PPIC_REMARK = ?, PRODUCTION_REMARK = ?, MARKETING_REMARK = ?,
                    EMAIL = ?, DRAWING = ?, SAMPLE = ?, DATA = ?
                WHERE CONTROL_ID = ?";
        
        $params = array(
            $control_no, $control_date, $dep_code, $pic_name, 
            $internal, $customer, $supplier, $item_id, $model,
            $man, $machine, $method, $material_4m, $other, $perm,
            $reason, $need_cust, $bef_change, $aft_change, $status, $prepared,
            $pe_remark, $qc_remark, $mold_remark, $ppic_remark, $prod_remark, $mkt_remark,
            $f_email, $f_drawing, $f_sample, $f_data,
            $control_id
        );
        $msg = "Data PCIS berhasil diperbarui!";
    } else {
        // --- MODE INSERT DATA BARU ---
        $sql = "INSERT INTO PROSES_CHANGE (
                    CONTROL_NO, CONTROL_DATE1, DEP_CODE, PIC_NAME, INTERNAL, CUSTOMER, SUPPLIER, ITEM_ID, MODEL, 
                    MAN, MACHINE, METHOD, MATERIAL, OTHER, PERMANENT_CHANGE, REASON, NEED_CUSTOMER, BEF_CHANGE, AFT_CHANGE, STATUS, IMC_PREPARED, 
                    PE_REMARK, QC_REMARK, MOLDSHOP_REMARK, PPIC_REMARK, PRODUCTION_REMARK, MARKETING_REMARK, EMAIL, DRAWING, SAMPLE, DATA
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = array(
            $control_no, $control_date, $dep_code, $pic_name, $internal, $customer, $supplier, $item_id, $model,
            $man, $machine, $method, $material_4m, $other, $perm, $reason, $need_cust, $bef_change, $aft_change, $status, $prepared,
            $pe_remark, $qc_remark, $mold_remark, $ppic_remark, $prod_remark, $mkt_remark,
            $f_email, $f_drawing, $f_sample, $f_data
        );
        $msg = "Data PCIS baru berhasil disimpan!";
    }
    
    // Eksekusi statement kueri secara aman ke driver SQL Server
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
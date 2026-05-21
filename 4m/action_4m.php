<?php
require_once __DIR__ . '/../config/database.php';

if (isset($_POST['btnSimpan'])) {
    $control_id   = $_POST['control_id']; // Ambil ID dari hidden input
    $control_no   = $_POST['control_no'];
    $control_date = $_POST['control_date'];
    $dep_code     = $_POST['dep_id']; 
    $pic_name     = $_POST['pic_name'];
    $item_id      = $_POST['item_id'];
    $model        = $_POST['model'];
    $material_id  = $_POST['material_id'];
    $reason       = $_POST['reason'];
    $bef_change   = $_POST['bef_change'];
    $aft_change   = $_POST['aft_change'];
    $status       = $_POST['status'];
    
    $man      = isset($_POST['man']) ? 1 : 0;
    $machine  = isset($_POST['machine']) ? 1 : 0;
    $method   = isset($_POST['method']) ? 1 : 0;
    $material = isset($_POST['material']) ? 1 : 0;
    $internal = isset($_POST['internal']) ? 1 : 0;
    $customer = isset($_POST['customer']) ? 1 : 0;
    $supplier = isset($_POST['supplier']) ? 1 : 0;
    $perm     = $_POST['perm'];

    if ($control_id > 0) {
        // MODE UPDATE DATA (Jika ID ditemukan di URL/Form)
        $sql = "UPDATE PROSES_CHANGE SET 
                    CONTROL_NO = ?, CONTROL_DATE1 = ?, DEP_CODE = ?, PIC_NAME = ?, 
                    ITEM_ID = ?, MODEL = ?, MATERIAL = ?, MAN = ?, MACHINE = ?, 
                    METHOD = ?, INTERNAL = ?, CUSTOMER = ?, SUPPLIER = ?, 
                    PERMANENT_CHANGE = ?, REASON = ?, BEF_CHANGE = ?, AFT_CHANGE = ?, STATUS = ? 
                WHERE CONTROL_ID = ?";
        
        $params = array(
            $control_no, $control_date, $dep_code, $pic_name, 
            $item_id, $model, $material_id, $man, $machine, 
            $method, $internal, $customer, $supplier, 
            $perm, $reason, $bef_change, $aft_change, $status, $control_id
        );
    } else {
        // MODE INSERT DATA BARU


    $sql = "INSERT INTO PROSES_CHANGE (
                    CONTROL_NO, CONTROL_DATE1, DEP_CODE, PIC_NAME, 
                    ITEM_ID, MODEL, MATERIAL, MAN, MACHINE, METHOD, INTERNAL, CUSTOMER, SUPPLIER, PERMANENT_CHANGE, REASON, BEF_CHANGE, AFT_CHANGE, STATUS
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = array(
            $control_no, $control_date, $dep_code, $pic_name, 
            $item_id, $model, $material_id, $man, $machine, $method, $internal, $customer, $supplier, $perm, $reason, $bef_change, $aft_change, $status
        );
    }

    $stmt = q($sql, $params);

    if ($stmt) {
        header("Location: ../dashboard_4m.php?page=history&msg=success");
    } else {
        die(print_r(sqlsrv_errors(), true));
    }
}
?>
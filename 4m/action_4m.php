<?php
require_once __DIR__ . '/../config/database.php';

if (isset($_POST['btnSimpan'])) {
    // Tangkap data dari form
    $control_no   = $_POST['control_no'];
    $control_date = $_POST['control_date'];
    $dep_code     = $_POST['dep_code'];
    $pic_name     = $_POST['pic_name'];
    $item_id      = $_POST['item_id'];
    $model        = $_POST['model'];
    
    // Logika Checkbox (bit di SQL Server: 1 untuk true, 0 untuk false)
    $man      = isset($_POST['man']) ? 1 : 0;
    $machine  = isset($_POST['machine']) ? 1 : 0;
    $method   = isset($_POST['method']) ? 1 : 0;
    $material = isset($_POST['material']) ? 1 : 0;
    $internal = isset($_POST['internal']) ? 1 : 0;

    $sql = "INSERT INTO PROSES_CHANGE (
                CONTROL_NO, CONTROL_DATE1, DEP_CODE, PIC_NAME, 
                ITEM_ID, MODEL, MAN, MACHINE, METHOD, MATERIAL, INTERNAL, STATUS
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'OPEN')";
    
    $params = array(
        $control_no, $control_date, $dep_code, $pic_name, 
        $item_id, $model, $man, $machine, $method, $material, $internal
    );

    $stmt = q($sql, $params);

    if ($stmt) {
        header("Location: ../dashboard_4m.php?page=history&msg=success");
    }
}
?>
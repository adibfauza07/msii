<?php
// Pastikan memanggil config database dengan benar
include '../config/database_p1.php';

// Pastikan request menggunakan method POST dan memiliki cust_id
if (isset($_POST['cust_id'])) {
    $cust_id = $_POST['cust_id'];

    // Siapkan wadah untuk respon HTML
    $response = array(
        'part_name_options' => '<option value="">- Pilih Part Name -</option>',
        'part_code_options' => '<option value="">- Pilih Part Code -</option>',
        'material_options'  => '<option value="">- Pilih Material -</option>'
    );

    if (!empty($cust_id)) {
        // ==========================================
        // 1. QUERY UNTUK PART NAME & PART CODE
        // ==========================================
        // Asumsi nama tabelnya adalah ITEM (sesuaikan jika berbeda, misalnya PART_ITEM)
        $sql_part = "SELECT ITEM_ID, PART_NAME, PART_CODE, PART_NO FROM ITEM WHERE CUST_ID = ? ORDER BY PART_NAME ASC";
        $params_part = array($cust_id);
        $stmt_part = sqlsrv_query($conn, $sql_part, $params_part);

        if ($stmt_part !== false) {
            while ($row_part = sqlsrv_fetch_array($stmt_part, SQLSRV_FETCH_ASSOC)) {
                // Tambahkan ke opsi Part Name
                $response['part_name_options'] .= "<option value='".$row_part['ITEM_ID']."'>".$row_part['PART_NAME']."</option>";
                
                // Tambahkan ke opsi Part Code (Biasanya Value-nya tetap ITEM_ID atau PART_CODE, sesuaikan dengan kebutuhan simpan DB)
                $response['part_code_options'] .= "<option value='".$row_part['PART_CODE']."'>".$row_part['PART_CODE']."</option>";
            }
        }

        // ==========================================
        // 2. QUERY UNTUK MATERIAL
        // ==========================================
        // Asumsi nama tabelnya adalah MATERIAL (sesuaikan jika berbeda di database MS SQL kamu)
        // Di Delphi menggunakan Qry_Material dengan field ITEM_ID, ITEM_CODE, ITEM_NAME
        $sql_mat = "SELECT ITEM_ID, ITEM_CODE, ITEM_NAME FROM MATERIAL ORDER BY ITEM_NAME ASC";
        $stmt_mat = sqlsrv_query($conn, $sql_mat);

        if ($stmt_mat !== false) {
            while ($row_mat = sqlsrv_fetch_array($stmt_mat, SQLSRV_FETCH_ASSOC)) {
                $response['material_options'] .= "<option value='".$row_mat['ITEM_ID']."'>".$row_mat['ITEM_NAME']." (".$row_mat['ITEM_CODE'].")</option>";
            }
        }
    }

    // Ubah array response menjadi format JSON agar bisa dibaca oleh JavaScript
    echo json_encode($response);
}
?>
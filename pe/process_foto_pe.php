<?php
// process_foto_pe.php
function uploadBinaryImage($conn, $trial_code, $file_input, $column_name) {
    if (isset($_FILES[$file_input]) && $_FILES[$file_input]['error'] == 0) {
        $imgData = file_get_contents($_FILES[$file_input]['tmp_name']);
        $sql = "UPDATE TRIAL_PE2 SET $column_name = ? WHERE TRIAL_CODE = ?";
        $stmt = sqlsrv_prepare($conn, $sql, [
            [&$imgData, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STREAM(SQLSRV_ENC_BINARY), SQLSRV_SQLTYPE_VARBINARY('max')],
            $trial_code
        ]);
        sqlsrv_execute($stmt);
    }
}
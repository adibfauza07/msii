<?php
// FILE: ajax_save_problem.php
ob_start(); 
error_reporting(E_ALL);
ini_set('display_errors', 0); 

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Deteksi plant aktif dari session (p1 / p2) seperti pada ajax_get_customer.php
$active_plant = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1';
$db_path = ($active_plant == 'p2') ? __DIR__ . '/../config/database.php' : __DIR__ . '/../config/database_p1.php';
if(file_exists($db_path)) { require_once $db_path; }

$sampah = ob_get_clean(); 
header('Content-Type: application/json');

if (!isset($conn) || $conn === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Koneksi database tidak tersedia untuk plant ' . $active_plant));
    exit;
}

$item_id     = isset($_POST['item_id']) ? $_POST['item_id'] : '';
$id_problem  = isset($_POST['id_problem']) ? $_POST['id_problem'] : ''; 
$tanggal     = isset($_POST['tanggal']) ? $_POST['tanggal'] : '';
$shift       = isset($_POST['shift']) ? $_POST['shift'] : '';
$status_id   = isset($_POST['status_id']) ? $_POST['status_id'] : '';
$problem     = isset($_POST['problem']) ? $_POST['problem'] : '';
$penangan    = isset($_POST['penangan']) ? $_POST['penangan'] : '';
$perbaikan   = isset($_POST['perbaikan']) ? $_POST['perbaikan'] : '';
$pic         = isset($_POST['pic']) ? $_POST['pic'] : '';
$tempat_foto = isset($_POST['tempat_foto']) ? $_POST['tempat_foto'] : '';

if (empty($item_id)) {
    echo json_encode(array('status' => 'error', 'message' => 'Pilih Part Number / Part Code terlebih dahulu!'));
    exit;
}
if (trim($problem) == '') {
    echo json_encode(array('status' => 'error', 'message' => 'Kolom Problem pada baris ini tidak boleh kosong!'));
    exit;
}

if (sqlsrv_begin_transaction($conn) === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Gagal memulai transaksi database.'));
    exit;
}

$is_success = true;

if (!empty($id_problem)) {
    // --- UPDATE DATA EXISTING / HISTORY ---
    $sql_upd = "UPDATE dbo.QC_PROBLEM SET TANGGAL=?, SHIFT=?, STATUS_ID=?, PROBLEM=?, Penangan=?, Perbaikan=?, Tempat_Foto=? WHERE ID_PROBLEM=?";
    $params_upd = array($tanggal, $shift, $status_id, $problem, $penangan, $perbaikan, $tempat_foto, $id_problem);
    $stmt_upd = sqlsrv_query($conn, $sql_upd, $params_upd);
    
    if ($stmt_upd === false) {
        $is_success = false;
    } else {
        sqlsrv_query($conn, "DELETE FROM dbo.QC_DIC_PIC WHERE ID_PROBLEM=?", array($id_problem));
        $pic_array = explode(',', $pic);
        foreach ($pic_array as $pic_name) {
            if (trim($pic_name) != '') {
                $stmt_pic = sqlsrv_query($conn, "INSERT INTO dbo.QC_DIC_PIC (ID_PROBLEM, PIC) VALUES (?, ?)", array($id_problem, trim($pic_name)));
                if ($stmt_pic === false) { $is_success = false; break; }
            }
        }
    }
} else {
    // --- INSERT DATA BARU ---
    $sql_ins = "INSERT INTO dbo.QC_PROBLEM (TANGGAL, SHIFT, ITEM_ID, STATUS_ID, PROBLEM, Penangan, Perbaikan, Tempat_Foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?); SELECT SCOPE_IDENTITY() AS last_id;";
    $params_ins = array($tanggal, $shift, $item_id, $status_id, $problem, $penangan, $perbaikan, $tempat_foto);
    $stmt_ins = sqlsrv_query($conn, $sql_ins, $params_ins);

    if ($stmt_ins) {
        sqlsrv_next_result($stmt_ins);
        $row_id = sqlsrv_fetch_array($stmt_ins, SQLSRV_FETCH_ASSOC);
        $new_id = isset($row_id['last_id']) ? $row_id['last_id'] : null;

        $pic_array = explode(',', $pic);
        foreach ($pic_array as $pic_name) {
            if (trim($pic_name) != '') {
                $stmt_pic = sqlsrv_query($conn, "INSERT INTO dbo.QC_DIC_PIC (ID_PROBLEM, PIC) VALUES (?, ?)", array($new_id, trim($pic_name)));
                if ($stmt_pic === false) { $is_success = false; break; }
            }
        }
    } else {
        $is_success = false;
    }
}

if ($is_success) {
    sqlsrv_commit($conn);
    echo json_encode(array('status' => 'success', 'message' => 'Data pada baris ini berhasil disimpan!'));
} else {
    sqlsrv_rollback($conn);
    $errors = sqlsrv_errors();
    $err_msg = !empty($errors) ? $errors[0]['message'] : 'Error Eksekusi Database';
    echo json_encode(array('status' => 'error', 'message' => $err_msg));
}
exit;
?>
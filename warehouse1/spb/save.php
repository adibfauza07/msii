<?php
session_start();

require_once __DIR__ . "/../../config/database_ordering.php";

if (!isset($conn) || $conn === false) {
    header("Location: ../login.php?error=session_expired");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: index.php');
    exit;
}

function base_url($path = '') {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    return $protocol . $host . $dir . '/' . $path;
}

function redirect($url) {
    header('Location: ' . base_url($url));
    exit;
}

function set_flash($key, $message) {
    $_SESSION[$key] = $message;
}

// Fungsi pembantu untuk mengambil pesan error dari SQL Server
function get_sqlsrv_errors() {
    $errors = sqlsrv_errors();
    $message = "";
    if ($errors != null) {
        foreach ($errors as $error) {
            $message .= $error['message'] . " ";
        }
    }
    return $message;
}

sqlsrv_begin_transaction($conn);

try {
    // Tangkap data kolom baru dengan isset() agar aman di PHP versi lama
    $recipient = isset($_POST['RECIPIENT']) ? $_POST['RECIPIENT'] : '';
    $vehicle_no = isset($_POST['VEHICLE_NO']) ? $_POST['VEHICLE_NO'] : '';
    $bc_no = isset($_POST['BC_NO']) ? trim($_POST['BC_NO']) : '';
    if ($bc_no === '/') {
        $bc_no = '';
    }

    if (isset($_POST['ID']) && !empty($_POST['ID'])) {
        // --- PROSES UPDATE ---
        $sql_header = "UPDATE TR_MATERIAL_SLIP_HEADER 
                       SET TRAN_ADATE = ?, TRTY_DESC = ?, RECIPIENT = ?, VEHICLE_NO = ?, BC_NO = ? 
                       WHERE ID = ?";
        
        $params_header = array(
            $_POST['TRAN_ADATE'], 
            $_POST['TRTY_DESC'], 
            $recipient, 
            $vehicle_no, 
            $bc_no, 
            $_POST['ID']
        );
        
        $stmt = sqlsrv_query($conn, $sql_header, $params_header);
        if (!$stmt) throw new Exception("Failed to update header: " . get_sqlsrv_errors());
        
        $header_id = $_POST['ID'];
        
        // Hapus detail lama untuk diganti dengan yang baru
        $sql_delete = "DELETE FROM TR_MATERIAL_SLIP_DETAIL WHERE HEADER_ID = ?";
        $params_delete = array($header_id);
        $stmt = sqlsrv_query($conn, $sql_delete, $params_delete);
        if (!$stmt) throw new Exception("Failed to delete details: " . get_sqlsrv_errors());
        
    } else {
        // --- PROSES INSERT BARU ---
        $sql_header = "INSERT INTO TR_MATERIAL_SLIP_HEADER 
                       (TRAN_DOC, TRANS_TIME, TRAN_ADATE, TRTY_DESC, RECIPIENT, VEHICLE_NO, BC_NO, CREATED_BY, CREATED_DATE) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                       
        $params_header = array(
            $_POST['TRAN_DOC'],
            date('Y-m-d H:i:s'),
            $_POST['TRAN_ADATE'],
            $_POST['TRTY_DESC'],
            $recipient,
            $vehicle_no,
            $bc_no,
            isset($_SESSION['db_user']) ? $_SESSION['db_user'] : 'system',
            date('Y-m-d H:i:s')
        );
        
        $stmt = sqlsrv_query($conn, $sql_header, $params_header);
        if (!$stmt) throw new Exception("Failed to insert header: " . get_sqlsrv_errors());
        
        // PERBAIKAN: Ambil ID berdasarkan TRAN_DOC yang baru saja diinput
        $sql_get_id = "SELECT ID FROM TR_MATERIAL_SLIP_HEADER WHERE TRAN_DOC = ?";
        $stmt_id = sqlsrv_query($conn, $sql_get_id, array($_POST['TRAN_DOC']));
        $row = sqlsrv_fetch_array($stmt_id, SQLSRV_FETCH_ASSOC);
        
        if (!$row || empty($row['ID'])) {
             throw new Exception("Failed to retrieve Header ID for document: " . $_POST['TRAN_DOC']);
        }
        
        $header_id = $row['ID'];
    }
    
    // --- PROSES INSERT DETAIL BARANG ---
    if (isset($_POST['ITEM_NAME']) && is_array($_POST['ITEM_NAME'])) {
        $sql_detail = "INSERT INTO TR_MATERIAL_SLIP_DETAIL 
                       (HEADER_ID, ITEM_CODE, ITEM_NAME, ITEM_UNIT, IT_QTY, REMARK) 
                       VALUES (?, ?, ?, ?, ?, ?)";
                       
        for ($i = 0; $i < count($_POST['ITEM_NAME']); $i++) {
            $item_name = isset($_POST['ITEM_NAME'][$i]) ? trim($_POST['ITEM_NAME'][$i]) : '';
            if ($item_name !== '') {
                $qty_input = isset($_POST['IT_QTY'][$i]) ? $_POST['IT_QTY'][$i] : '';
                $qty = str_replace(',', '', $qty_input);
                $qty = empty($qty) ? 0 : (float)$qty;
                
                $params_detail = array(
                    $header_id,
                    '', // ITEM_CODE sengaja dikosongkan; kolom di form sudah dihapus
                    $item_name,
                    isset($_POST['ITEM_UNIT'][$i]) ? trim($_POST['ITEM_UNIT'][$i]) : '',
                    $qty,
                    isset($_POST['REMARK'][$i]) ? trim($_POST['REMARK'][$i]) : ''
                );
                
                $stmt_detail = sqlsrv_query($conn, $sql_detail, $params_detail);
                if (!$stmt_detail) throw new Exception("Failed to insert detail row " . ($i+1) . ": " . get_sqlsrv_errors());
            }
        }
    }
    
    sqlsrv_commit($conn);
    set_flash('success', 'Data saved successfully');
    redirect('view.php?doc=' . urlencode($_POST['TRAN_DOC']));
    
} catch (Exception $e) {
    sqlsrv_rollback($conn);
    set_flash('error', $e->getMessage()); // Flash message diubah agar error SQL terlihat di UI (opsional)
    redirect('form.php' . (isset($_POST['TRAN_DOC']) ? '?doc=' . urlencode($_POST['TRAN_DOC']) : ''));
}
?>
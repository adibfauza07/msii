<?php
// FILE: msii/qc/api_kakotora_master.php
// UPDATE: Multi-Database Support (P1 & P2)

ini_set('display_errors', 0);
error_reporting(0);
define('LOGIN_PAGE', true);

// 1. DETEKSI PLANT AKTIF
if (session_status() == PHP_SESSION_NONE) { session_start(); }
$active_plant = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1';

// 2. PILIH KONEKSI DATABASE
if ($active_plant == 'p2') {
    // --- SETTING PLANT 2 ---
    // Pastikan session yang dibutuhkan database.php tersedia
    if(!isset($_SESSION['erp_user'])) $_SESSION['erp_user'] = $_SESSION['db_user'];
    if(!isset($_SESSION['erp_pass'])) $_SESSION['erp_pass'] = $_SESSION['db_pass'];
    
    // !!! WAJIB ISI IP SERVER PLANT 2 DI SINI !!!
    $_SESSION['server_sql'] = "192.168.0.9"; 

    // Panggil file database.php milik Plant 2 (Naik 2 folder ke MSII/Config)
    $db_path = __DIR__ . '/../config/database.php';
    if(file_exists($db_path)) {
        require_once $db_path;
    } else {
        echo json_encode(array('status'=>'error', 'msg'=>'File Config P2 tidak ditemukan di: ' . $db_path)); exit;
    }
} else {
    // --- SETTING PLANT 1 (DEFAULT) ---
    require_once __DIR__ . '/../config/database_p1.php'; 
}

header('Content-Type: application/json');

if (!$conn) { echo json_encode(array('status'=>'error', 'msg'=>'Koneksi Database Gagal. Cek Config.')); exit; }

// --- SISA KODE KE BAWAH SAMA PERSIS SEPERTI SEBELUMNYA ---
// (Copy paste sisa logic dari file api_kakotora_master.php yang terakhir saya berikan)
// Agar tidak kepanjangan, saya tulis ulang bagian intinya saja:

$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] == 'get_table_data') {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        $start = ($page - 1) * $limit; $end = $start + $limit;
        $search = isset($_GET['search']) ? $_GET['search'] : '';

        $sqlBase = "FROM car_claim a LEFT JOIN CUST c ON a.cust_id = c.CUST_ID LEFT JOIN ITEM_CUSTINFO_VIEW v ON a.item_id = v.ITEM_ID";
        $params = array(); $whereClause = "";

        if (!empty($search)) {
            $whereClause = " WHERE a.car_no LIKE ? OR a.problem LIKE ? OR c.CUST_COMP LIKE ?";
            $searchParam = "%".$search."%"; $params = array($searchParam, $searchParam, $searchParam);
        }

        $sqlCount = "SELECT COUNT(a.car_id) as total " . $sqlBase . $whereClause;
        $stmtCount = sqlsrv_query($conn, $sqlCount, $params);
        $rowCount = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
        $totalRows = ($rowCount) ? $rowCount['total'] : 0;
        $totalPages = ceil($totalRows / $limit);

        $sqlData = "SELECT * FROM ( SELECT ROW_NUMBER() OVER (ORDER BY a.claim_date DESC) AS RowNum, a.car_id, a.car_no, a.claim_date, a.problem, a.qty, a.event_status, c.CUST_COMP, v.PART_NAME " . $sqlBase . $whereClause . " ) AS RowResult WHERE RowNum > ? AND RowNum <= ?";
        $paramsQuery = array_merge($params, array($start, $end));
        
        $stmtData = sqlsrv_query($conn, $sqlData, $paramsQuery);
        $data = array();
        if ($stmtData) {
            while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
                $tgl = ($row['claim_date']) ? $row['claim_date']->format('d-m-Y') : '-';
                $row['tgl_formatted'] = $tgl;
                if(is_null($row['CUST_COMP'])) $row['CUST_COMP'] = '-';
                if(is_null($row['PART_NAME'])) $row['PART_NAME'] = '-';
                $data[] = $row;
            }
            echo json_encode(array('status' => 'ok', 'data' => $data, 'pagination' => array('current_page' => $page, 'total_pages' => $totalPages, 'total_records' => $totalRows)));
        } else { $e = sqlsrv_errors(); echo json_encode(array('status' => 'error', 'msg' => 'SQL Error: '.$e[0]['message'])); }
        exit;
    }
    // ... Copy paste sisa fungsi GET (get_master, get_complete_data, get_customers, dll) dari file sebelumnya ...
    if ($_GET['action'] == 'get_master') {
        $id = $_GET['id']; $sql = "SELECT * FROM car_claim WHERE car_id = ?"; $stmt = sqlsrv_query($conn, $sql, array($id));
        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) echo json_encode(array('status' => 'ok', 'data' => $row));
        else echo json_encode(array('status' => 'error', 'msg' => 'Data tidak ditemukan'));
        exit;
    }
    elseif ($_GET['action'] == 'get_complete_data') {
        $id = $_GET['id'];
        $sqlHead = "SELECT * FROM car_claim WHERE car_id = ?";
        $stmtHead = sqlsrv_query($conn, $sqlHead, array($id));
        $data = ($stmtHead) ? sqlsrv_fetch_array($stmtHead, SQLSRV_FETCH_ASSOC) : array();
        if(empty($data)) { echo json_encode(array('status'=>'error', 'msg'=>'ID Not Found')); exit; }
        
        $sqlDet = "SELECT cause, counter, pic, eff_date FROM car_claim_detail WHERE car_id = ?";
        $stmtDet = sqlsrv_query($conn, $sqlDet, array($id));
        if($stmtDet && $rDet = sqlsrv_fetch_array($stmtDet, SQLSRV_FETCH_ASSOC)){ $data = array_merge($data, $rDet); } 
        else { $data['cause'] = ''; $data['counter'] = ''; $data['pic'] = ''; $data['eff_date'] = null; }

        $stmtLoc = sqlsrv_query($conn, "SELECT loc_problem FROM car_loc WHERE car_id = ?", array($id));
        $locs = array(); while($r = sqlsrv_fetch_array($stmtLoc, SQLSRV_FETCH_ASSOC)) $locs[] = $r['loc_problem'];
        $data['loc_problem'] = implode(", ", $locs);

        $stmtEfek = sqlsrv_query($conn, "SELECT EFEK FROM car_efek WHERE car_id = ?", array($id));
        $efeks = array(); while($r = sqlsrv_fetch_array($stmtEfek, SQLSRV_FETCH_ASSOC)) $efeks[] = $r['EFEK'];
        $data['efek'] = implode(", ", $efeks);

        $stmtKlas = sqlsrv_query($conn, "SELECT klasifikasi FROM car_klasifikasi WHERE car_id = ?", array($id));
        $rKlas = ($stmtKlas) ? sqlsrv_fetch_array($stmtKlas, SQLSRV_FETCH_ASSOC) : null;
        $data['klasifikasi'] = ($rKlas) ? $rKlas['klasifikasi'] : 'Minor';

        echo json_encode(array('status' => 'ok', 'data' => $data)); exit;
    }
    elseif ($_GET['action'] == 'get_customers') {
        $sql = "SELECT TOP 300 CUST_ID, CUST_CODE, CUST_COMP FROM CUST WHERE CUST_INACTIVE = 0 ORDER BY CUST_COMP ASC";
        $stmt = sqlsrv_query($conn, $sql); $data = array();
        if($stmt) { while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { $data[] = array('id' => $row['CUST_ID'], 'text' => $row['CUST_COMP']); } }
        echo json_encode(array('status' => 'ok', 'data' => $data)); exit;
    }
    elseif ($_GET['action'] == 'get_items_by_cust') {
        $custId = $_GET['cust_id'];
        $sql = "SELECT TOP 200 ITEM_ID, PART_CODE, PART_NAME FROM ITEM_CUSTINFO_VIEW WHERE CUST_ID = ? ORDER BY PART_NAME ASC";
        $stmt = sqlsrv_query($conn, $sql, array($custId)); $data = array();
        if($stmt) { while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { $data[] = array('id' => $row['ITEM_ID'], 'text' => $row['PART_CODE'] . ' - ' . $row['PART_NAME']); } }
        echo json_encode(array('status' => 'ok', 'data' => $data)); exit;
    }
}

if ($method == 'POST') {
    $action = $_POST['action'];
    // FUNGSI SIMPAN DETAIL (SAMA PERSIS)
    function saveDetails($conn, $car_id) {
        $cek = sqlsrv_query($conn, "SELECT car_id FROM car_claim_detail WHERE car_id=?", array($car_id));
        $hasRow = sqlsrv_has_rows($cek);
        $cause = $_POST['cause']; $counter = $_POST['counter']; $pic = $_POST['pic']; $eff_date = !empty($_POST['eff_date']) ? $_POST['eff_date'] : null; $status = $_POST['detail_status'];

        if($hasRow) { $sql = "UPDATE car_claim_detail SET cause=?, counter=?, pic=?, eff_date=?, status=? WHERE car_id=?"; sqlsrv_query($conn, $sql, array($cause, $counter, $pic, $eff_date, $status, $car_id)); } 
        else { $sql = "INSERT INTO car_claim_detail (car_id, cause, counter, pic, eff_date, status) VALUES (?,?,?,?,?,?)"; sqlsrv_query($conn, $sql, array($car_id, $cause, $counter, $pic, $eff_date, $status)); }

        sqlsrv_query($conn, "DELETE FROM car_loc WHERE car_id=?", array($car_id));
        if(!empty($_POST['loc_problem'])) { $locs = explode(",", $_POST['loc_problem']); foreach($locs as $l) { $l=trim($l); if($l) sqlsrv_query($conn, "INSERT INTO car_loc (car_id, loc_problem) VALUES (?,?)", array($car_id, $l)); } }

        sqlsrv_query($conn, "DELETE FROM car_efek WHERE car_id=?", array($car_id));
        if(!empty($_POST['efek'])) { $efs = explode(",", $_POST['efek']); foreach($efs as $e) { $e=trim($e); if($e) sqlsrv_query($conn, "INSERT INTO car_efek (car_id, EFEK) VALUES (?,?)", array($car_id, $e)); } }

        sqlsrv_query($conn, "DELETE FROM car_klasifikasi WHERE car_id=?", array($car_id));
        if(!empty($_POST['klasifikasi'])) { sqlsrv_query($conn, "INSERT INTO car_klasifikasi (car_id, klasifikasi) VALUES (?,?)", array($car_id, $_POST['klasifikasi'])); }

        if(isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['gambar']['tmp_name']; $fileStream = fopen($fileTmp, "rb"); 
            $imgParams = array(array($car_id, SQLSRV_PARAM_IN), array($fileStream, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STREAM(SQLSRV_ENC_BINARY), SQLSRV_SQLTYPE_IMAGE));
            $cekImg = sqlsrv_query($conn, "SELECT car_id FROM car_claim_gambar WHERE car_id=?", array($car_id));
            if(sqlsrv_has_rows($cekImg)) {
                $sqlImg = "UPDATE car_claim_gambar SET gambar = ? WHERE car_id = ?";
                $updParams = array(array($fileStream, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STREAM(SQLSRV_ENC_BINARY), SQLSRV_SQLTYPE_IMAGE), array($car_id, SQLSRV_PARAM_IN));
                sqlsrv_query($conn, $sqlImg, $updParams);
            } else { $sqlImg = "INSERT INTO car_claim_gambar (car_id, gambar) VALUES (?, ?)"; sqlsrv_query($conn, $sqlImg, $imgParams); }
        }
    }

    if ($action == 'insert_master') {
        $car_no = $_POST['car_no']; $claim_date = $_POST['claim_date']; $problem = $_POST['problem']; $qty = $_POST['qty']; $status = $_POST['event_status']; $cust_id = $_POST['cust_id']; $item_id = $_POST['item_id'];
        $cek = sqlsrv_query($conn, "SELECT car_no FROM car_claim WHERE car_no = ?", array($car_no));
        if ($cek && sqlsrv_has_rows($cek)) { echo json_encode(array('status' => 'error', 'msg' => 'Nomor CAR sudah ada!')); exit; }
        $sql = "INSERT INTO car_claim (car_no, claim_date, problem, qty, event_status, cust_id, item_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = sqlsrv_query($conn, $sql, array($car_no, $claim_date, $problem, $qty, $status, $cust_id, $item_id));
        if ($stmt) {
            $resId = sqlsrv_query($conn, "SELECT @@IDENTITY as id"); $rowId = sqlsrv_fetch_array($resId, SQLSRV_FETCH_ASSOC); $newCarId = $rowId['id'];
            saveDetails($conn, $newCarId); echo json_encode(array('status' => 'ok', 'id' => $newCarId));
        } else { $e = sqlsrv_errors(); echo json_encode(array('status' => 'error', 'msg' => 'Gagal: '.$e[0]['message'])); }
    }
    elseif ($action == 'update_master') {
        $id = $_POST['car_id']; $problem = $_POST['problem']; $qty = $_POST['qty']; $status = $_POST['event_status']; $claim_date = $_POST['claim_date']; $cust_id = $_POST['cust_id']; $item_id = $_POST['item_id'];
        $sql = "UPDATE car_claim SET problem=?, qty=?, event_status=?, claim_date=?, cust_id=?, item_id=? WHERE car_id=?";
        $stmt = sqlsrv_query($conn, $sql, array($problem, $qty, $status, $claim_date, $cust_id, $item_id, $id));
        if ($stmt) { saveDetails($conn, $id); echo json_encode(array('status' => 'ok')); } 
        else { $e = sqlsrv_errors(); echo json_encode(array('status' => 'error', 'msg' => 'Gagal Update: '.$e[0]['message'])); }
    }
    elseif ($action == 'delete_master') {
        $id = $_POST['id'];
        $tables = array('car_claim_detail', 'car_claim_gambar', 'car_efek', 'car_klasifikasi', 'car_loc', 'car_event');
        foreach($tables as $tbl) sqlsrv_query($conn, "DELETE FROM $tbl WHERE car_id = ?", array($id));
        $stmt = sqlsrv_query($conn, "DELETE FROM car_claim WHERE car_id = ?", array($id));
        if ($stmt) echo json_encode(array('status' => 'ok')); else echo json_encode(array('status' => 'error', 'msg' => 'Gagal Hapus'));
    }
    exit;
}
?>
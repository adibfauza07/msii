<?php
session_start();

// Load database connection from parent config folder
require_once __DIR__ . "/../../config/database_ordering.php";

// Jika koneksi gagal atau belum login
if (!isset($conn) || $conn === false) {
    header("Location: ../login.php?error=session_expired");
    exit();
}

// Helper functions
function generate_doc_number() {
    global $conn;
    if (!$conn) return 'MS-' . date('Ym') . '-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
    
    sqlsrv_begin_transaction($conn);
    
    $sql_check = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'GEN_DOC_NUMBER'";
    $stmt = sqlsrv_query($conn, $sql_check);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    if ($row['cnt'] == 0) {
        $sql_create = "CREATE TABLE GEN_DOC_NUMBER (DOC_TYPE VARCHAR(20) PRIMARY KEY, LAST_NUMBER INT DEFAULT 0)";
        sqlsrv_query($conn, $sql_create);
        sqlsrv_query($conn, "INSERT INTO GEN_DOC_NUMBER (DOC_TYPE, LAST_NUMBER) VALUES ('MATSLIP', 0)");
        $last_number = 1;
    } else {
        $sql = "SELECT LAST_NUMBER FROM GEN_DOC_NUMBER WHERE DOC_TYPE = 'MATSLIP'";
        $stmt = sqlsrv_query($conn, $sql);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($row) {
            $last_number = $row['LAST_NUMBER'] + 1;
        } else {
            $last_number = 1;
            sqlsrv_query($conn, "INSERT INTO GEN_DOC_NUMBER (DOC_TYPE, LAST_NUMBER) VALUES ('MATSLIP', 1)");
        }
    }
    
    sqlsrv_query($conn, "UPDATE GEN_DOC_NUMBER SET LAST_NUMBER = $last_number WHERE DOC_TYPE = 'MATSLIP'");
    sqlsrv_commit($conn);
    
    return 'MS-' . date('Ym') . '-' . str_pad($last_number, 6, '0', STR_PAD_LEFT);
}

function get_header($doc_number) {
    global $conn;
    if (!$conn) return null;
    $sql = "SELECT * FROM TR_MATERIAL_SLIP_HEADER WHERE TRAN_DOC = ?";
    $params = array($doc_number);
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) return null;
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

function get_details($header_id) {
    global $conn;
    if (!$conn) return array();
    $sql = "SELECT * FROM TR_MATERIAL_SLIP_DETAIL WHERE HEADER_ID = ? ORDER BY ID";
    $params = array($header_id);
    $stmt = sqlsrv_query($conn, $sql, $params);
    $results = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
    return $results;
}

// Modifikasi untuk menangkap pencarian
function get_all_headers($limit = 10, $offset = 0, $search = "") {
    global $conn;
    if (!$conn) return array();
    
    $where = "";
    $params = array();
    
    // Jika ada input search, tambahkan filter WHERE
    if (!empty($search)) {
        $where = " WHERE TRAN_DOC LIKE ? OR TRTY_DESC LIKE ? OR RECIPIENT LIKE ? OR CREATED_BY LIKE ? ";
        $searchTerm = "%" . $search . "%";
        $params = array($searchTerm, $searchTerm, $searchTerm, $searchTerm);
    }
    
    $sql = "SELECT * FROM (
        SELECT *, ROW_NUMBER() OVER (ORDER BY ID DESC) as RowNum 
        FROM TR_MATERIAL_SLIP_HEADER
        $where
    ) as temp WHERE RowNum > ? AND RowNum <= ?";
    
    // Push offset & limit ke dalam parameter query
    array_push($params, $offset, $offset + $limit);
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    $results = array();
    if($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $results[] = $row;
        }
    }
    return $results;
}

// Modifikasi count agar menyesuaikan jumlah data pencarian
function count_all_headers($search = "") {
    global $conn;
    if (!$conn) return 0;
    
    $where = "";
    $params = array();
    
    if (!empty($search)) {
        $where = " WHERE TRAN_DOC LIKE ? OR TRTY_DESC LIKE ? OR RECIPIENT LIKE ? OR CREATED_BY LIKE ? ";
        $searchTerm = "%" . $search . "%";
        $params = array($searchTerm, $searchTerm, $searchTerm, $searchTerm);
    }
    
    $sql = "SELECT COUNT(*) as total FROM TR_MATERIAL_SLIP_HEADER $where";
    $stmt = sqlsrv_query($conn, $sql, $params);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return $row ? $row['total'] : 0;
}

function get_instansi() {
    return array('nama_instansi' => 'PT. MAJU JAYA');
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

function get_flash($key) {
    if (isset($_SESSION[$key])) {
        $message = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $message;
    }
    return null;
}

// Main content variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$headers = get_all_headers($limit, $offset, $search);
$total = count_all_headers($search);
$total_pages = ceil($total / $limit);

// Mempertahankan parameter pencarian untuk link pagination
$search_query = !empty($search) ? '&search=' . urlencode($search) : '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>SURAT PENGANTAR BARANG</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f7f6; font-family: "Segoe UI", Roboto, Arial, sans-serif; overflow-x: hidden; }
        
        #sidebar {
            width: 250px; height: 100vh; background: #1f2a36; color: white;
            position: fixed; top: 0; left: 0; z-index: 1050;
            display: flex; flex-direction: column; box-shadow: 3px 0 10px rgba(0,0,0,0.2);
        }
        #sidebar .brand { padding: 22px 20px; font-size: 18px; font-weight: 700; background: #1a232d; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-link { color: #aab0b6; padding: 12px 20px; font-size: 14.5px; border-left: 4px solid transparent; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background: #2c3e50; color: #fff !important; border-left-color: #3498db; }
        .nav-link i { margin-right: 10px; font-size: 1.1rem; }
        .menu-label { padding: 20px 20px 8px 20px; font-size: 11px; text-transform: uppercase; color: #5b6e80; font-weight: 800; letter-spacing: 1px; }
        .sidebar-footer { margin-top: auto; padding: 15px 20px; background: #161e27; border-top: 1px solid rgba(255,255,255,0.05); }
        
        /* FIX LAYOUT: Gunakan margin-left agar content benar-benar mulai setelah sidebar */
        #content { margin-left: 250px; transition: all 0.3s; width: calc(100% - 250px); min-height: 100vh; }
        
        .top-header { background: white; padding: 15px 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card-custom { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); background: white; margin-bottom: 25px; overflow: hidden; }
        
        /* --- PERBAIKAN SIDEBAR UNTUK BOOTSTRAP 3 --- */
        #sidebar .nav > li > a:hover, 
        #sidebar .nav > li > a:focus,
        #sidebar a:hover,
        #sidebar a:focus {
            background-color: #2c3e50 !important;
            text-decoration: none !important;
            color: #ffffff !important;
        }
        .mt-auto { margin-top: auto !important; }
        .d-flex { display: flex !important; }
        .flex-column { flex-direction: column !important; }
        .w-100 { width: 100% !important; }
        .gap-2 { gap: 0.5rem !important; }
        .align-items-center { align-items: center !important; }

        #sidebar i.bi { vertical-align: middle; margin-top: -2px; display: inline-block; }
        .sidebar-footer { display: flex; flex-direction: column; gap: 5px; }
        .sidebar-footer .btn { padding: 6px 12px !important; font-size: 12px !important; border-radius: 6px !important; width: 100%; }

        #sidebar .nav > li > a.nav-link, #sidebar .nav-link {
            padding: 12px 20px !important; font-size: 14.5px !important; display: flex !important; align-items: center !important;
        }
        #sidebar ul, #sidebar .nav { padding-left: 0 !important; margin-bottom: 0 !important; list-style: none !important; }
        #sidebar .sidebar-footer .btn {
            padding: 8px 12px !important; font-size: 13px !important; line-height: 1.5 !important; display: block !important; width: 100% !important; text-align: center !important;
        }
        #sidebar { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
    </style>
</head>
<body>
<div class="d-flex w-100">
    <?php include '../includes/sidebar.php'; ?>
    
    <div id="content">
        <!-- FIX LAYOUT: Gunakan container-fluid agar mengisi ruang sisa dengan padding yang rapi -->
        <div class="container-fluid" style="padding: 20px 30px;">
            <h2>SURAT PENGANTAR BARANG</h2>
            <hr>
            
            <?php if ($msg = get_flash('success')): ?>
                <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            
            <?php if ($msg = get_flash('error')): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            
            <div class="row" style="margin-bottom: 20px;">
                <div class="col-md-6">
                    <a href="form.php" class="btn btn-primary">
                        <span class="glyphicon glyphicon-plus"></span> Create New
                    </a>
                </div>
                <div class="col-md-6">
                    <form method="get" class="form-inline pull-right">
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-default">
                            <span class="glyphicon glyphicon-search"></span>
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th width="50" class="text-center">No</th>
                            <th class="text-center">Document Number</th>
                            <th class="text-center">Date</th>
                            <th class="text-center">From (Departemen)</th>
                            <th class="text-center">Recipient (Kepada Yth)</th>
                            <th class="text-center">Created By</th>
                            <th width="150" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($headers)): ?>
                            <?php $no = 1 + (($page - 1) * $limit); ?>
                            <?php foreach ($headers as $header): ?>
                            <tr>
                                <td class="text-center" style="vertical-align: middle;"><?= $no++ ?></td>
                                <td style="vertical-align: middle;"><strong><?= htmlspecialchars($header['TRAN_DOC']) ?></strong></td>
                                <td class="text-center" style="vertical-align: middle;">
                                    <?php 
                                        if (isset($header['TRAN_ADATE']) && is_object($header['TRAN_ADATE'])) {
                                            echo $header['TRAN_ADATE']->format('d-M-Y');
                                        } else {
                                            echo date('d-M-Y', strtotime(isset($header['TRAN_ADATE']) ? $header['TRAN_ADATE'] : ''));
                                        }
                                    ?>
                                </td>
                                <td style="vertical-align: middle;"><?= htmlspecialchars($header['TRTY_DESC']) ?></td>
                                <td style="vertical-align: middle;"><?= htmlspecialchars(isset($header['RECIPIENT']) ? $header['RECIPIENT'] : '-') ?></td>
                                <td style="vertical-align: middle;"><?= htmlspecialchars($header['CREATED_BY']) ?></td>
                                <td class="text-center" style="vertical-align: middle;">
                                    <div class="btn-group btn-group-xs" style="margin-bottom:0;">
                                        <a href="view.php?doc=<?= urlencode($header['TRAN_DOC']) ?>" class="btn btn-info" title="View">
                                            <span class="glyphicon glyphicon-eye-open"></span>
                                        </a>
                                        <a href="print.php?doc=<?= urlencode($header['TRAN_DOC']) ?>" target="_blank" class="btn btn-default" title="Print">
                                            <span class="glyphicon glyphicon-print"></span>
                                        </a>
                                        <a href="form.php?doc=<?= urlencode($header['TRAN_DOC']) ?>" class="btn btn-warning" title="Edit">
                                            <span class="glyphicon glyphicon-edit"></span>
                                        </a>
                                        <a href="delete.php?id=<?= $header['ID'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure?')" title="Delete">
                                            <span class="glyphicon glyphicon-trash"></span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">Tidak ada data ditemukan untuk pencarian "<strong><?= htmlspecialchars($search) ?></strong>"</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="text-center">
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li><a href="?page=<?= $page-1 ?><?= $search_query ?>">&laquo;</a></li>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="<?= ($i == $page) ? 'active' : '' ?>">
                                <a href="?page=<?= $i ?><?= $search_query ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <li><a href="?page=<?= $page+1 ?><?= $search_query ?>">&raquo;</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
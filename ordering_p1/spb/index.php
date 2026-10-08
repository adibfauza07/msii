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

function get_all_headers($limit = 10, $offset = 0) {
    global $conn;
    if (!$conn) return array();
    $sql = "SELECT * FROM (
        SELECT *, ROW_NUMBER() OVER (ORDER BY ID DESC) as RowNum 
        FROM TR_MATERIAL_SLIP_HEADER
    ) as temp WHERE RowNum > ? AND RowNum <= ?";
    $params = array($offset, $offset + $limit);
    $stmt = sqlsrv_query($conn, $sql, $params);
    $results = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
    return $results;
}

function count_all_headers() {
    global $conn;
    if (!$conn) return 0;
    $sql = "SELECT COUNT(*) as total FROM TR_MATERIAL_SLIP_HEADER";
    $stmt = sqlsrv_query($conn, $sql);
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

// Main content
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$headers = get_all_headers($limit, $offset);
$total = count_all_headers();
$total_pages = ceil($total / $limit);
?>
<!DOCTYPE html>
<html>
<head>
    <title>SURAT PENGANTAR BARANG</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <style>
        .container { padding: 20px; width: 95%; max-width: 1200px; }
        .btn-group { margin-bottom: 20px; }
        .table th { background: #f5f5f5; text-align: center; vertical-align: middle !important; }
        .table td { vertical-align: middle !important; }
        .alert { margin-top: 10px; }
    </style>
</head>
<body>
<div class="container">
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
                <input type="text" name="search" class="form-control" placeholder="Search...">
                <button type="submit" class="btn btn-default">
                    <span class="glyphicon glyphicon-search"></span>
                </button>
            </form>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover">
            <thead>
                <tr>
                    <th width="50">No</th>
                    <th>Document Number</th>
                    <th>Date</th>
                    <th>From (Departemen)</th>
                    <th>Recipient (Kepada Yth)</th>
                    <th>Created By</th>
                    <th width="150">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($headers)): ?>
                    <?php $no = 1 + (($page - 1) * $limit); ?>
                    <?php foreach ($headers as $header): ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td><strong><?= htmlspecialchars($header['TRAN_DOC']) ?></strong></td>
                        <td class="text-center">
                            <?php 
                                if (isset($header['TRAN_ADATE']) && is_object($header['TRAN_ADATE'])) {
                                    echo $header['TRAN_ADATE']->format('d-M-Y');
                                } else {
                                    echo date('d-M-Y', strtotime(isset($header['TRAN_ADATE']) ? $header['TRAN_ADATE'] : ''));
                                }
                            ?>
                        </td>
                        <td><?= htmlspecialchars($header['TRTY_DESC']) ?></td>
                        
                        <!-- DATA KOLOM BARU DITAMPILKAN DI SINI (Fixed for PHP 5) -->
                        <td><?= htmlspecialchars(isset($header['RECIPIENT']) ? $header['RECIPIENT'] : '-') ?></td>
                        
                        <td><?= htmlspecialchars($header['CREATED_BY']) ?></td>
                        <td class="text-center">
                            <div class="btn-group btn-group-xs">
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
                        <td colspan="7" class="text-center">No data found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($total_pages > 1): ?>
        <div class="text-center">
            <ul class="pagination">
                <?php if ($page > 1): ?>
                    <li><a href="?page=<?= $page-1 ?>">&laquo;</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="<?= ($i == $page) ? 'active' : '' ?>">
                        <a href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <li><a href="?page=<?= $page+1 ?>">&raquo;</a></li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
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
    <!-- Tambahkan link Bootstrap Icons di sini -->
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
        
        #content { padding-left: 250px; transition: all 0.3s; }
        .top-header { background: white; padding: 15px 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }

        .card-custom { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); background: white; margin-bottom: 25px; overflow: hidden; }
        .card-header-custom { background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #eef0f3; font-weight: 700; color: #1f2a36; display: flex; align-items: center; }
        .card-header-custom i { color: #3498db; margin-right: 10px; font-size: 1.2rem; }
        
        label { font-weight: 600; color: #495057; font-size: 0.85rem; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;}
        .form-control, .form-select { border-radius: 8px; border: 1px solid #ced4da; padding: 10px 15px; font-size: 0.95rem;}
        .form-control:focus, .form-select:focus { box-shadow: 0 0 0 0.25rem rgba(52,152,219,0.25); border-color: #3498db; }
        .form-control[readonly] { background-color: #f8f9fa; border-color: #e9ecef; }
        
        .table-act th { background: #1f2a36; color: white; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; border:none;}
        #ACT_TABLE td.editable { cursor:pointer; background: #fffbe6; font-weight: bold;}
        #ACT_TABLE td.editable:hover { background: #fff3cd; }
        #ACT_TABLE td.editable input { width:100%; border:1px solid #3498db; border-radius:4px; padding:4px 8px; font-size:0.9rem; }
        
        .btn-nav { border-radius: 8px; padding: 10px 20px; font-weight: 600; letter-spacing: 0.5px; }
        .floating-action { background: white; padding: 15px; border-radius: 12px; box-shadow: 0 -4px 15px rgba(0,0,0,0.05); position: sticky; bottom: 20px; z-index: 100;}
    
        .ui-autocomplete {
            position: absolute;
            z-index: 9999 !important; 
            background-color: #ffffff;
            border: 1px solid #ced4da;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            max-height: 250px;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 5px 0;
        }
        .ui-menu-item .ui-menu-item-wrapper {
            padding: 8px 15px;
            font-size: 0.9rem;
            cursor: pointer;
        }
        .ui-menu-item .ui-menu-item-wrapper:hover,
        .ui-menu-item .ui-menu-item-wrapper.ui-state-active {
            background-color: #3498db !important;
            color: #ffffff !important;
            border: none;
            margin: 0;
        }

        /* --- PERBAIKAN SIDEBAR UNTUK BOOTSTRAP 3 --- */
        
        /* 1. Mencegah background putih dari Bootstrap 3 saat menu di-hover */
        #sidebar .nav > li > a:hover, 
        #sidebar .nav > li > a:focus,
        #sidebar a:hover,
        #sidebar a:focus {
            background-color: #2c3e50 !important;
            text-decoration: none !important;
            color: #ffffff !important;
        }

        /* 2. Menambahkan fungsi Flexbox & Spacing yang hilang di Bootstrap 3 */
        .mt-auto { margin-top: auto !important; }
        .d-flex { display: flex !important; }
        .flex-column { flex-direction: column !important; }
        .w-100 { width: 100% !important; }
        .gap-2 { gap: 0.5rem !important; }
        .align-items-center { align-items: center !important; }

        /* 3. Merapikan icon agar sejajar dengan teks */
        #sidebar i.bi {
            vertical-align: middle;
            margin-top: -2px;
            display: inline-block;
        }

        /* 4. Merapikan tombol (Kembali ke ERP / Logout) di bagian footer */
        .sidebar-footer {
            display: flex;
            flex-direction: column;
            gap: 5px; /* Memberi jarak antar tombol jika tumpuk */
        }
        .sidebar-footer .btn {
            padding: 6px 12px !important;
            font-size: 12px !important;
            border-radius: 6px !important;
            width: 100%;
        }

        /* 5. Memaksa Spasi dan Ukuran Font Navigasi agar sesuai desain asli */
        #sidebar .nav > li > a.nav-link,
        #sidebar .nav-link {
            padding: 12px 20px !important;
            font-size: 14.5px !important;
            display: flex !important;
            align-items: center !important;
        }

        /* 6. Menghilangkan margin/padding bawaan list (ul) Bootstrap 3 */
        #sidebar ul, #sidebar .nav {
            padding-left: 0 !important;
            margin-bottom: 0 !important;
            list-style: none !important;
        }

        /* 7. Memperbaiki tombol di Footer agar tidak terlalu pipih */
        #sidebar .sidebar-footer .btn {
            padding: 8px 12px !important;
            font-size: 13px !important;
            line-height: 1.5 !important;
            display: block !important;
            width: 100% !important;
            text-align: center !important;
        }
        
        /* 8. Menghaluskan render teks agar tidak terlihat tipis/pecah */
        #sidebar {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
    </style>
</head>
<body>
<div class="d-flex w-100">
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Bungkus konten dengan div id="content" -->
    <div id="content" class="w-100" style="width: 100%;">
        <div class="container">
            <br>
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
        </div> <!-- Penutup container -->
    </div> <!-- Penutup id="content" -->
</div> <!-- Penutup d-flex w-100 -->
</body>
</html>
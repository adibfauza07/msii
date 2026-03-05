<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['db_user'])) { header("Location: login.php"); exit(); }

require_once __DIR__ . '/../config/database_p1.php'; 
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Warehouse Dashboard | ERP Plant 1</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        body { background: #f4f7fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        /* Sidebar Styling */
        #sidebar { width: 250px; height: 100vh; background: #1e2937; color: #ecf0f1; position: fixed; display: flex; flex-direction: column; z-index: 1000; }
        #sidebar .brand-section { padding: 25px; background: #111827; font-weight: 700; font-size: 1.1rem; text-align: center; color: #3b82f6; }
        .nav-link { color: #9ca3af; padding: 12px 20px; transition: 0.3s; border-radius: 5px; margin: 2px 10px; }
        .nav-link:hover, .nav-link.active { background: #374151; color: #ffffff !important; }
        .nav-link i { margin-right: 10px; }
        
        /* Main Content */
        #content { margin-left: 250px; padding: 30px; transition: 0.3s; }
        .stat-card { border: none; border-radius: 12px; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        
        @media (max-width: 768px) {
            #sidebar { margin-left: -250px; }
            #content { margin-left: 0; }
            #sidebar.active { margin-left: 0; }
        }
    </style>
</head>
<body>

<div id="sidebar">
    <div class="brand-section">
        <i class="bi bi-truck-flatbed"></i> WH-MANAGEMENT
    </div>
    <div class="mt-3">
        <ul class="nav flex-column">
            <li class="nav-item"><a href="?page=home" class="nav-link <?=($page=='home'?'active':'')?>"><i class="bi bi-grid-1x2"></i> Dashboard</a></li>
            <li class="nav-item"><a href="?page=stock" class="nav-link <?=($page=='stock'?'active':'')?>"><i class="bi bi-box-seam"></i> Inventaris Part</a></li>
            <li class="nav-item"><a href="?page=customer" class="nav-link <?=($page=='customer'?'active':'')?>"><i class="bi bi-people"></i> Daftar Customer</a></li>
        </ul>
    </div>
    <div class="mt-auto p-3" style="background: #111827;">
        <div class="d-flex align-items-center mb-2">
            <div class="bg-success rounded-circle me-2" style="width: 8px; height: 8px;"></div>
            <small class="text-white">Online: <?=$_SESSION['db_user']?></small>
        </div>
        <a href="logout.php" class="btn btn-outline-danger btn-sm w-100">Keluar Sistem</a>
    </div>
</div>

<div id="content">
    <?php if($page == 'home'): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold m-0">Ringkasan Operasional Gudang</h4>
            <span class="badge bg-primary px-3 py-2"><?=date('d M Y')?></span>
        </div>
        
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="card stat-card shadow-sm p-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-muted small fw-bold">TOTAL ITEM</div>
                            <div class="fs-3 fw-bold">
                                <?php 
                                $q = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM ITEMS WHERE ITEM_INACTIVE=0");
                                $r = sqlsrv_fetch_array($q); echo $r['t'];
                                ?>
                            </div>
                        </div>
                        <div class="text-primary fs-1"><i class="bi bi-boxes"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stat-card shadow-sm p-3 border-bottom border-primary border-4">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-muted small fw-bold">CUSTOMER</div>
                            <div class="fs-3 fw-bold">
                                <?php 
                                $q = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM CUST WHERE CUST_INACTIVE=0");
                                $r = sqlsrv_fetch_array($q); echo $r['t'];
                                ?>
                            </div>
                        </div>
                        <div class="text-info fs-1"><i class="bi bi-building"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stat-card shadow-sm p-3 border-bottom border-danger border-4">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-muted small fw-bold text-danger">STOK KRITIS</div>
                            <div class="fs-3 fw-bold text-danger">
                                <?php 
                                $q = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM ITEMS WHERE ITEM_ONHAND <= ITEM_SAFETY AND ITEM_INACTIVE=0");
                                $r = sqlsrv_fetch_array($q); echo $r['t'];
                                ?>
                            </div>
                        </div>
                        <div class="text-danger fs-1"><i class="bi bi-exclamation-triangle"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold"><i class="bi bi-list-stars me-2"></i>Status Stok & Customer Terkait</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Part No / Code</th>
                                <th>Deskripsi Item</th>
                                <th>Customer (Pemilik)</th>
                                <th class="text-center">Stok</th>
                                <th>Unit</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Query menggabungkan View dengan Tabel CUST
                            $sql = "SELECT TOP 15 v.*, c.CUST_COMP, i.ITEM_ONHAND, i.ITEM_SAFETY 
                                    FROM ITEM_CUST_VIEW v
                                    INNER JOIN ITEMS i ON v.PART_ID = i.ITEM_ID
                                    LEFT JOIN CUST c ON v.CUST_ID = c.CUST_ID
                                    ORDER BY i.ITEM_ONHAND ASC";
                            $query = sqlsrv_query($conn, $sql);
                            while($r = sqlsrv_fetch_array($query)):
                                $is_low = ($r['ITEM_ONHAND'] <= $r['ITEM_SAFETY']);
                            ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark"><?=$r['PART_NO']?></div>
                                    <small class="text-muted"><?=$r['PART_CODE']?></small>
                                </td>
                                <td><?=$r['PART_NAME']?></td>
                                <td><span class="text-uppercase small fw-bold text-muted"><?=($r['CUST_COMP'] )?></span></td>
                                <td class="text-center fw-bold <?=($is_low?'text-danger':'text-primary')?>">
                                    <?=number_format($r['ITEM_ONHAND'])?>
                                </td>
                                <td><small><?=$r['PART_UNIT']?></small></td>
                                <td>
                                    <?= $is_low ? 
                                        '<span class="badge bg-danger-subtle text-danger border border-danger">Low Stock</span>' : 
                                        '<span class="badge bg-success-subtle text-success border border-success">Normal</span>' 
                                    ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="p-5 text-center">
            <img src="https://cdn-icons-png.flaticon.com/512/2452/2452144.png" width="120" class="mb-3 opacity-50">
            <h5>Modul <?=$page?> Sedang Disiapkan</h5>
            <p class="text-muted">Fitur ini akan segera tersedia untuk operasional Warehouse.</p>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
require_once 'config.php';

// Fungsi untuk mencegah XSS (Kompatibel PHP 5.4)
function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

$message = "";

// ==========================================================
// PROSES DELETE / CANCEL PR (Hanya untuk status PENDING)
// ==========================================================
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['pr_no'])) {
    $cancel_pr_no = trim($_GET['pr_no']);
    
    $sql_check = "SELECT status FROM PR_Header WHERE pr_no = ?";
    $stmt_check = sqlsrv_query($conn, $sql_check, array($cancel_pr_no));
    
    if ($stmt_check && sqlsrv_has_rows($stmt_check)) {
        $row_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
        
        if ($row_check['status'] === 'PENDING') {
            $sql_cancel = "UPDATE PR_Header SET status = 'CANCELED' WHERE pr_no = ?";
            $stmt_cancel = sqlsrv_query($conn, $sql_cancel, array($cancel_pr_no));
            
            if ($stmt_cancel) {
                $message = "<div class='alert alert-success alert-dismissible'><button type='button' class='close' data-dismiss='alert'>&times;</button><i class='fas fa-check-circle'></i> Purchase Request <strong>".h($cancel_pr_no)."</strong> berhasil dibatalkan.</div>";
            } else {
                $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Gagal membatalkan PR: " . print_r(sqlsrv_errors(), true) . "</div>";
            }
        } else {
            $message = "<div class='alert alert-warning'><i class='fas fa-lock'></i> PR <strong>".h($cancel_pr_no)."</strong> tidak dapat dibatalkan karena statusnya sudah ".h($row_check['status']).".</div>";
        }
    }
}

// ==========================================================
// FILTER & PENCARIAN (READ)
// ==========================================================
$filter_plant = isset($_GET['plant_id']) ? trim($_GET['plant_id']) : '';
$filter_dept  = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';
$filter_search= isset($_GET['search']) ? trim($_GET['search']) : ''; // Parameter Pencarian Baru

$sql_pr = "
    SELECT 
        PR_Header.pr_no, PR_Header.pr_date, PR_Header.plant_id, PR_Header.department_id, 
        PR_Header.total_amount, PR_Header.status, PR_Header.created_by, PR_Header.created_at, 
        PR_Detail.pr_detail_id, PR_Detail.item_code, PR_Detail.quote_id, 
        PR_Detail.qty_request, PR_Detail.qty_received, PR_Detail.unit_price, PR_Detail.subtotal
    FROM PR_Header
    INNER JOIN PR_Detail ON PR_Header.pr_no = PR_Detail.pr_no
    WHERE 1=1
";

$params = array();

if ($filter_plant !== '') {
    $sql_pr .= " AND PR_Header.plant_id = ?";
    $params[] = $filter_plant;
}

if ($filter_dept !== '') {
    $sql_pr .= " AND PR_Header.department_id = ?";
    $params[] = $filter_dept;
}

// Logika Pencarian Global (PR No atau Item Code)
if ($filter_search !== '') {
    $sql_pr .= " AND (PR_Header.pr_no LIKE ? OR PR_Detail.item_code LIKE ?)";
    $params[] = "%" . $filter_search . "%";
    $params[] = "%" . $filter_search . "%";
}

$sql_pr .= " ORDER BY PR_Header.pr_date DESC, PR_Header.pr_no DESC";
$stmt_pr = sqlsrv_query($conn, $sql_pr, $params);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Purchase Request - ERP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: Tahoma, sans-serif; font-size: 13px; padding: 20px; }
        .table th, .table td { vertical-align: middle; white-space: nowrap; }
        @media print {
            @page { size: A5 landscape; margin: 10mm; }
            body { background-color: #ffffff; padding: 0; }
            .no-print { display: none !important; }
            .card { border: none !important; box-shadow: none !important; margin: 0 !important; }
            .card-header { display: none !important; }
            .table-responsive { overflow: visible !important; }
            .table th, .table td { padding: 4px; font-size: 11px; }
        }
    </style>
</head>
<body>

    <div class="container-fluid">
        <?php echo $message; ?>

        <div class="card shadow-sm mb-4 no-print">
            <div class="card-header bg-dark text-white">
                <i class="fas fa-filter"></i> Filter Purchase Request
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <!-- Baris 1: Filter Dropdown -->
                    <div class="form-inline mb-3">
                        <label class="mr-2 font-weight-bold">Plant:</label>
                        <select name="plant_id" class="form-control mr-4">
                            <option value="">-- Semua Plant --</option>
                            <option value="P1" <?php echo ($filter_plant === 'P1') ? 'selected' : ''; ?>>P1 - Plant 1</option>
                            <option value="P2" <?php echo ($filter_plant === 'P2') ? 'selected' : ''; ?>>P2 - Plant 2</option>
                        </select>

                        <label class="mr-2 font-weight-bold">Department:</label>
                        <input type="text" name="department_id" class="form-control mr-4" placeholder="Kode Dept (mis: IT)" value="<?php echo h($filter_dept); ?>">

                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Tampilkan Data</button>
                        <a href="pr_report.php" class="btn btn-secondary ml-2"><i class="fas fa-sync"></i> Reset</a>
                        <button type="button" class="btn btn-info ml-auto" onclick="window.print()"><i class="fas fa-print"></i> Cetak A5</button>
                    </div>
                    
                    <!-- Baris 2: Pencarian Global (Sesuai Gambar) -->
                    <div class="form-inline">
                        <input type="text" name="search" class="form-control border-primary" style="width: 400px;" placeholder="Pencarian Nomor PR atau Kode Barang..." value="<?php echo h($filter_search); ?>">
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white no-print">
                <i class="fas fa-list"></i> Data Purchase Request
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>PR No</th>
                                <th>Tanggal</th>
                                <th class="text-center">Plant</th>
                                <th class="text-center">Dept</th>
                                <th>Item Code</th>
                                <th class="text-center">Qty Req</th>
                                <th class="text-right">Unit Price</th>
                                <th class="text-right">Subtotal</th>
                                <th class="text-center">Status</th>
                                <th class="text-center no-print" width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grand_total_qty = 0;
                            $grand_total_amount = 0;

                            if ($stmt_pr !== false) {
                                $has_data = false;
                                while ($row = sqlsrv_fetch_array($stmt_pr, SQLSRV_FETCH_ASSOC)) {
                                    $has_data = true;
                                    $pr_date = $row['pr_date'] instanceof DateTime ? $row['pr_date']->format('d-m-Y') : $row['pr_date'];
                                    
                                    if ($row['status'] !== 'CANCELED') {
                                        $grand_total_qty += (float)$row['qty_request'];
                                        $grand_total_amount += (float)$row['subtotal'];
                                    }
                            ?>
                            <tr>
                                <td><strong><?php echo h($row['pr_no']); ?></strong></td>
                                <td><?php echo $pr_date; ?></td>
                                <td class="text-center"><?php echo h($row['plant_id']); ?></td>
                                <td class="text-center"><?php echo h($row['department_id']); ?></td>
                                <td><?php echo h($row['item_code']); ?></td>
                                <td class="text-center"><?php echo number_format($row['qty_request'], 2, ',', '.'); ?></td>
                                <td class="text-right"><?php echo number_format($row['unit_price'], 2, ',', '.'); ?></td>
                                <td class="text-right font-weight-bold"><?php echo number_format($row['subtotal'], 2, ',', '.'); ?></td>
                                <td class="text-center">
                                    <?php 
                                        if ($row['status'] === 'APPROVED') {
                                            echo '<span class="badge badge-success">APPROVED</span>';
                                        } elseif ($row['status'] === 'CANCELED') {
                                            echo '<span class="badge badge-danger">CANCELED</span>';
                                        } else {
                                            echo '<span class="badge badge-warning">'.h($row['status']).'</span>';
                                        }
                                    ?>
                                </td>
                                <td class="text-center no-print">
                                    <div class="btn-group" role="group">
                                        <a href="report_pr.php?pr_no=<?php echo urlencode($row['pr_no']); ?>" target="_blank" class="btn btn-sm btn-info" title="Cetak PR">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        
                                        <?php if ($row['status'] === 'PENDING'): ?>
                                            <!-- Tombol Edit Terhubung ke edit_pr.php -->
                                            <a href="edit_pr.php?pr_no=<?php echo urlencode($row['pr_no']); ?>" class="btn btn-sm btn-primary" title="Edit PR">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?action=cancel&pr_no=<?php echo urlencode($row['pr_no']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Anda yakin ingin membatalkan Purchase Request ini?');" title="Batalkan PR">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-secondary" disabled><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-secondary" disabled><i class="fas fa-times"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                                }
                                if (!$has_data) {
                                    echo "<tr><td colspan='10' class='text-center text-danger'>Data tidak ditemukan.</td></tr>";
                                }
                            } else {
                                echo "<tr><td colspan='10' class='text-center text-danger'>Gagal memuat data.</td></tr>";
                            }
                            ?>
                        </tbody>
                        <?php if (isset($has_data) && $has_data): ?>
                        <tfoot class="thead-light">
                            <tr>
                                <th colspan="5" class="text-right align-middle font-weight-bold">GRAND TOTAL (ACTIVE):</th>
                                <th class="text-center font-weight-bold text-primary"><?php echo number_format($grand_total_qty, 2, ',', '.'); ?></th>
                                <th></th>
                                <th class="text-right font-weight-bold text-success"><?php echo number_format($grand_total_amount, 2, ',', '.'); ?></th>
                                <th colspan="2" class="no-print"></th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
if (isset($stmt_pr) && $stmt_pr !== false) sqlsrv_free_stmt($stmt_pr);
if ($conn !== false) sqlsrv_close($conn);
?>
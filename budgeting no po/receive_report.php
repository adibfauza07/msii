<?php
require_once 'config.php';

// Fungsi untuk mencegah XSS (Kompatibel PHP 5.4)
function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

$message = "";

// ==========================================================
// PROSES DELETE / BATAL PENERIMAAN BARANG
// ==========================================================
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['receive_no'])) {
    $cancel_rcv_no = trim($_GET['receive_no']);
    
    // Mulai transaksi untuk memastikan Header dan Detail terhapus bersamaan
    if (sqlsrv_begin_transaction($conn) === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $is_deleted = true;

    // 1. Hapus Detail Penerimaan (Receive_Det)
    $sql_del_det = "DELETE FROM Receive_Det WHERE receive_no = ?";
    $stmt_del_det = sqlsrv_query($conn, $sql_del_det, array($cancel_rcv_no));
    if (!$stmt_del_det) { $is_deleted = false; }

    // 2. Hapus Header Penerimaan (Receive_Header)
    if ($is_deleted) {
        $sql_del_head = "DELETE FROM Receive_Header WHERE receive_no = ?";
        $stmt_del_head = sqlsrv_query($conn, $sql_del_head, array($cancel_rcv_no));
        if (!$stmt_del_head) { $is_deleted = false; }
    }

    if ($is_deleted) {
        sqlsrv_commit($conn);
        $message = "<div class='alert alert-success alert-dismissible'><button type='button' class='close' data-dismiss='alert'>&times;</button><i class='fas fa-check-circle'></i> Dokumen Penerimaan <strong>".h($cancel_rcv_no)."</strong> berhasil dibatalkan dan dihapus dari sistem.</div>";
    } else {
        sqlsrv_rollback($conn);
        $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Gagal membatalkan Penerimaan: " . print_r(sqlsrv_errors(), true) . "</div>";
    }
}

// ==========================================================
// FILTER & PENCARIAN (READ)
// ==========================================================
$filter_plant = isset($_GET['plant_id']) ? trim($_GET['plant_id']) : '';
$filter_dept  = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';
$filter_search= isset($_GET['search']) ? trim($_GET['search']) : ''; 

// Query SQL Server 2008
$sql_rcv = "
    SELECT 
        rh.receive_no, 
        rh.receive_date, 
        rh.plant_id, 
        rh.department_id, 
        rh.received_by, 
        rd.item_code, 
        rd.qty_in,
        pd.pr_no,
        mi.item_name,
        mi.uom
    FROM Receive_Header rh
    INNER JOIN Receive_Det rd ON rh.receive_no = rd.receive_no
    LEFT JOIN PR_Detail pd ON rd.pr_detail_id = pd.pr_detail_id
    LEFT JOIN Master_Item mi ON rd.item_code = mi.item_code
    WHERE 1=1
";

$params = array();

if ($filter_plant !== '') {
    $sql_rcv .= " AND rh.plant_id = ?";
    $params[] = $filter_plant;
}

if ($filter_dept !== '') {
    $sql_rcv .= " AND rh.department_id = ?";
    $params[] = $filter_dept;
}

// Pencarian Global (Nomor GR, Nomor PR, atau Kode Barang)
if ($filter_search !== '') {
    $sql_rcv .= " AND (rh.receive_no LIKE ? OR pd.pr_no LIKE ? OR rd.item_code LIKE ?)";
    $params[] = "%" . $filter_search . "%";
    $params[] = "%" . $filter_search . "%";
    $params[] = "%" . $filter_search . "%";
}

$sql_rcv .= " ORDER BY rh.receive_date DESC, rh.receive_no DESC";
$stmt_rcv = sqlsrv_query($conn, $sql_rcv, $params);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penerimaan Barang (GR) - ERP</title>
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
                <i class="fas fa-filter"></i> Filter Laporan Penerimaan Barang
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
                        <a href="receive_report.php" class="btn btn-secondary ml-2"><i class="fas fa-sync"></i> Reset</a>
                        <button type="button" class="btn btn-info ml-auto" onclick="window.print()"><i class="fas fa-print"></i> Cetak A5</button>
                    </div>
                    
                    <!-- Baris 2: Pencarian Global -->
                    <div class="form-inline">
                        <input type="text" name="search" class="form-control border-primary" style="width: 450px;" placeholder="Pencarian No. Penerimaan, No. PR, atau Kode Barang..." value="<?php echo h($filter_search); ?>">
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-success text-white no-print">
                <i class="fas fa-boxes"></i> Data Goods Receipt (Penerimaan Barang)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>No. Dokumen (GR)</th>
                                <th>Tanggal Terima</th>
                                <th class="text-center">Plant</th>
                                <th class="text-center">Dept</th>
                                <th>Diterima Oleh</th>
                                <th>No. PR Ref.</th>
                                <th>Kode & Nama Barang</th>
                                <th class="text-right">Qty Diterima</th>
                                <th class="text-center no-print" width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grand_total_qty = 0;

                            if ($stmt_rcv !== false) {
                                $has_data = false;
                                while ($row = sqlsrv_fetch_array($stmt_rcv, SQLSRV_FETCH_ASSOC)) {
                                    $has_data = true;
                                    $rcv_date = $row['receive_date'] instanceof DateTime ? $row['receive_date']->format('d-m-Y H:i') : date('d-m-Y H:i', strtotime($row['receive_date']));
                                    
                                    $grand_total_qty += (float)$row['qty_in'];
                            ?>
                            <tr>
                                <td><strong><?php echo h($row['receive_no']); ?></strong></td>
                                <td><?php echo $rcv_date; ?></td>
                                <td class="text-center"><?php echo h($row['plant_id']); ?></td>
                                <td class="text-center"><?php echo h($row['department_id']); ?></td>
                                <td><i class="fas fa-user text-muted"></i> <?php echo h($row['received_by']); ?></td>
                                <td><span class="badge badge-info"><?php echo h($row['pr_no']); ?></span></td>
                                <td>
                                    <strong><?php echo h($row['item_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo h($row['item_name']); ?></small>
                                </td>
                                <td class="text-right font-weight-bold text-success">
                                    <?php echo number_format($row['qty_in'], 2, ',', '.') . ' ' . h($row['uom']); ?>
                                </td>
                                <td class="text-center no-print">
                                    <div class="btn-group" role="group">
                                        <!-- Tombol Cetak ICL -->
                                        <a href="report_penerimaan.php?id=<?php echo urlencode($row['receive_no']); ?>" target="_blank" class="btn btn-sm btn-info" title="Cetak ICL">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        
                                        <!-- Tombol Hapus/Batal GR -->
                                        <a href="?action=cancel&receive_no=<?php echo urlencode($row['receive_no']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Peringatan: Membatalkan dokumen ini akan menghapus data penerimaan secara permanen. Lanjutkan?');" title="Batalkan Penerimaan">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                                }
                                if (!$has_data) {
                                    echo "<tr><td colspan='9' class='text-center text-danger py-4'>Data tidak ditemukan.</td></tr>";
                                }
                            } else {
                                echo "<tr><td colspan='9' class='text-center text-danger py-4'>Gagal memuat data: " . h(print_r(sqlsrv_errors(), true)) . "</td></tr>";
                            }
                            ?>
                        </tbody>
                        <?php if (isset($has_data) && $has_data): ?>
                        <tfoot class="thead-light">
                            <tr>
                                <th colspan="7" class="text-right align-middle font-weight-bold">GRAND TOTAL QTY DITERIMA:</th>
                                <th class="text-right font-weight-bold text-success" style="font-size: 14px;">
                                    <?php echo number_format($grand_total_qty, 2, ',', '.'); ?>
                                </th>
                                <th class="no-print"></th>
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
if (isset($stmt_rcv) && $stmt_rcv !== false) sqlsrv_free_stmt($stmt_rcv);
if ($conn !== false) sqlsrv_close($conn);
?>
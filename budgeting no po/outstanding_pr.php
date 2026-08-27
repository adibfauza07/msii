<?php
require_once 'config.php';

// Fungsi anti-XSS untuk PHP 5.4
function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

// Menangkap filter dari GET Request
$filter_dept  = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';
$filter_plant = isset($_GET['plant_id']) ? trim($_GET['plant_id']) : '';

// Query SQL Server 2008 - Menghitung selisih PR dan Receive
$sql_outstanding = "
    SELECT 
        ph.pr_no, 
        ph.pr_date, 
        ph.department_id, 
        ph.plant_id,
        pd.item_code, 
        pd.qty_request,
        ISNULL(rcv.total_received, 0) AS total_received,
        (pd.qty_request - ISNULL(rcv.total_received, 0)) AS outstanding_qty
    FROM PR_Header ph
    INNER JOIN PR_Detail pd ON ph.pr_no = pd.pr_no
    LEFT JOIN (
        -- Subquery untuk menjumlahkan qty_in per pr_detail_id
        SELECT 
            rd.pr_detail_id, 
            SUM(rd.qty_in) AS total_received
        FROM Receive_Det rd
        INNER JOIN Receive_Header rh ON rd.receive_no = rh.receive_no
        GROUP BY rd.pr_detail_id
    ) rcv ON pd.pr_detail_id = rcv.pr_detail_id
    WHERE 1=1
";

$params = array();

if ($filter_dept !== '') {
    $sql_outstanding .= " AND ph.department_id = ?";
    $params[] = $filter_dept;
}

if ($filter_plant !== '') {
    $sql_outstanding .= " AND ph.plant_id = ?";
    $params[] = $filter_plant;
}

// Urutkan berdasarkan PR terbaru
$sql_outstanding .= " ORDER BY ph.pr_date DESC, ph.pr_no DESC";

$stmt = sqlsrv_query($conn, $sql_outstanding, $params);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Outstanding PR vs Receive - ERP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: Tahoma, sans-serif; font-size: 13px; padding: 20px; }
        .table th, .table td { vertical-align: middle; white-space: nowrap; }
        .row-completed { background-color: #d4edda !important; } /* Hijau jika lunas */
    </style>
</head>
<body>

    <div class="container-fluid">
        <!-- FORM FILTER -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-search"></i> Filter Outstanding PR per Departemen
            </div>
            <div class="card-body">
                <form method="GET" action="" class="form-inline">
                    <label class="mr-2 font-weight-bold">Department:</label>
                    <input type="text" name="department_id" class="form-control mr-4" placeholder="Mis: HRD, IT, PROD" value="<?php echo h($filter_dept); ?>">
                    
                    <label class="mr-2 font-weight-bold">Plant:</label>
                    <select name="plant_id" class="form-control mr-4">
                        <option value="">-- Semua Plant --</option>
                        <option value="P1" <?php echo ($filter_plant === 'P1') ? 'selected' : ''; ?>>P1 - Plant 1</option>
                        <option value="P2" <?php echo ($filter_plant === 'P2') ? 'selected' : ''; ?>>P2 - Plant 2</option>
                    </select>

                    <button type="submit" class="btn btn-info"><i class="fas fa-filter"></i> Proses Data</button>
                    <a href="outstanding_pr.php" class="btn btn-secondary ml-2"><i class="fas fa-sync"></i> Reset</a>
                </form>
            </div>
        </div>

        <!-- TABEL HASIL -->
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <i class="fas fa-clipboard-list"></i> Realisasi Pengajuan Barang per Departemen
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="thead-light text-center">
                            <tr>
                                <th>PR No</th>
                                <th>Tanggal PR</th>
                                <th>Dept</th>
                                <th>Plant</th>
                                <th>Item Code</th>
                                <th>Qty Request (A)</th>
                                <th>Qty Received (B)</th>
                                <th>Outstanding (A - B)</th>
                                <th>Status Penerimaan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($stmt !== false) {
                                $has_data = false;
                                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                    $has_data = true;
                                    
                                    // Format tanggal
                                    $pr_date = $row['pr_date'] instanceof DateTime ? $row['pr_date']->format('d-m-Y') : $row['pr_date'];
                                    
                                    // Logika status baris
                                    $outstanding = (float)$row['outstanding_qty'];
                                    $qty_request = (float)$row['qty_request'];
                                    
                                    $row_class = '';
                                    $status_badge = '';
                                    
                                    if ($outstanding <= 0) {
                                        $row_class = 'row-completed'; // Lunas / terpenuhi
                                        $status_badge = '<span class="badge badge-success">Completed</span>';
                                    } elseif ($outstanding < $qty_request) {
                                        $status_badge = '<span class="badge badge-warning">Parsial</span>';
                                    } else {
                                        $status_badge = '<span class="badge badge-danger">Open / Belum Datang</span>';
                                    }
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td><strong><?php echo h($row['pr_no']); ?></strong></td>
                                <td class="text-center"><?php echo $pr_date; ?></td>
                                <td class="text-center"><?php echo h($row['department_id']); ?></td>
                                <td class="text-center"><?php echo h($row['plant_id']); ?></td>
                                <td><?php echo h($row['item_code']); ?></td>
                                <td class="text-right text-primary font-weight-bold"><?php echo number_format($qty_request, 2, ',', '.'); ?></td>
                                <td class="text-right text-success font-weight-bold"><?php echo number_format((float)$row['total_received'], 2, ',', '.'); ?></td>
                                <td class="text-right text-danger font-weight-bold"><?php echo number_format($outstanding, 2, ',', '.'); ?></td>
                                <td class="text-center"><?php echo $status_badge; ?></td>
                            </tr>
                            <?php 
                                }
                                if (!$has_data) {
                                    echo "<tr><td colspan='9' class='text-center text-danger'>Data tidak ditemukan.</td></tr>";
                                }
                            } else {
                                echo "<tr><td colspan='9' class='text-center text-danger'>Gagal memuat data: " . h(print_r(sqlsrv_errors(), true)) . "</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</body>
</html>

<?php
if (isset($stmt) && $stmt !== false) sqlsrv_free_stmt($stmt);
if ($conn !== false) sqlsrv_close($conn);
?>
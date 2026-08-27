<?php
require_once 'config.php'; // Menggunakan $conn untuk database 'budget'

// Fungsi sanitasi
function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

$message = "";

// Proses Eksekusi Approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_pr') {
    $pr_no = isset($_POST['pr_no']) ? trim($_POST['pr_no']) : '';
    
    if (!empty($pr_no)) {
        // Parameterized Query untuk keamanan
        $sql_approve = "UPDATE PR_Header SET status = 'APPROVED' WHERE pr_no = ? AND status = 'PENDING'";
        $stmt_approve = sqlsrv_query($conn, $sql_approve, array($pr_no));
        
        if ($stmt_approve) {
            // Cek apakah ada baris yang terpengaruh (mencegah approval ganda)
            $rows_affected = sqlsrv_rows_affected($stmt_approve);
            if ($rows_affected > 0) {
                $message = "<div class='alert alert-success shadow-sm'>PR Nomor <strong>" . h($pr_no) . "</strong> berhasil disetujui.</div>";
            } else {
                $message = "<div class='alert alert-warning shadow-sm'>PR tersebut sudah disetujui atau tidak ditemukan.</div>";
            }
        } else {
            $message = "<div class='alert alert-danger shadow-sm'>Gagal memproses persetujuan: " . print_r(sqlsrv_errors(), true) . "</div>";
        }
    }
}

// Ambil Daftar PR yang masih PENDING
$sql_list = "
    SELECT pr_no, pr_date, department_id, plant_id, total_amount, created_by 
    FROM PR_Header 
    WHERE status = 'PENDING' 
    ORDER BY pr_date ASC
";
$stmt_list = sqlsrv_query($conn, $sql_list);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Finance Approval - ERP Budgeting</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: Tahoma, sans-serif; font-size: 13px; padding: 20px; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white font-weight-bold">
            <i class="fas fa-check-double"></i> Otorisasi Finance - Menunggu Persetujuan
        </div>
        <div class="card-body">
            <?php echo $message; ?>
            
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="thead-light text-center">
                        <tr>
                            <th width="5%">No</th>
                            <th width="15%">No. Dokumen PR</th>
                            <th width="15%">Tanggal</th>
                            <th width="20%">Plant & Departemen</th>
                            <th width="15%">Pemohon</th>
                            <th width="15%">Total Nilai (Rp)</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        if ($stmt_list !== false && sqlsrv_has_rows($stmt_list)) {
                            while ($row = sqlsrv_fetch_array($stmt_list, SQLSRV_FETCH_ASSOC)): 
                                $date_str = $row['pr_date'] instanceof DateTime ? $row['pr_date']->format('d-m-Y') : date('d-m-Y', strtotime($row['pr_date']));
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td class="font-weight-bold text-primary"><?php echo h($row['pr_no']); ?></td>
                            <td class="text-center"><?php echo $date_str; ?></td>
                            <td class="text-center">
                                <span class="badge badge-info"><?php echo h($row['plant_id']); ?></span> 
                                <?php echo h($row['department_id']); ?>
                            </td>
                            <td class="text-center"><?php echo h($row['created_by']); ?></td>
                            <td class="text-right font-weight-bold">
                                <?php echo number_format($row['total_amount'], 2, ',', '.'); ?>
                            </td>
                            <td class="text-center">
                                <!-- Form action untuk persetujuan spesifik -->
                                <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin menyetujui PR <?php echo h($row['pr_no']); ?>?');">
                                    <input type="hidden" name="action" value="approve_pr">
                                    <input type="hidden" name="pr_no" value="<?php echo h($row['pr_no']); ?>">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php 
                            endwhile; 
                        } else {
                            echo "<tr><td colspan='7' class='text-center text-muted py-4'>Tidak ada Purchase Request yang menunggu persetujuan.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
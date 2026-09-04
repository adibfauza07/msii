<?php
// ==========================================================
// File: finance_approval.php
// Deskripsi: Otorisasi Finance Hybrid (Budget PR & MSDATA PO)
// Kompabilitas: PHP 5.4, SQL Server 2008
// ==========================================================

if (session_id() == "") session_start();

// Memanggil 2 koneksi: $conn (Budget) dan $conn_msdata (MSDATA)
require_once 'config.php';
if (!isset($conn) || !isset($conn_msdata)) die("Koneksi database tidak tersedia.");

$current_file = basename($_SERVER['PHP_SELF']); 

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
function getv($n, $d="") { return isset($_GET[$n]) ? trim((string)$_GET[$n]) : $d; }
function postv($n, $d="") { return isset($_POST[$n]) ? trim((string)$_POST[$n]) : $d; }

$message = "";
$action = postv("action", "");
$source = getv("source", postv("doc_source", "PR")); // Default PR (Budget)

// ==========================================================
// 1. PROSES EKSEKUSI APPROVAL (HYBRID)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'approve_pr') {
    $doc_no = postv('doc_no');
    
    if (!empty($doc_no)) {
        if ($source === 'PO') {
            // --- JALUR MSDATA (PO) ---
            // Sesuaikan nama kolom status MSDATA Delphi Anda di sini (Contoh: REQ_APP, REQ_STAT, atau STATUS)
            $kolom_status_msdata = "REQ_APP"; 
            
            $sql_approve = "UPDATE dbo.REQUISITION SET {$kolom_status_msdata} = 1 WHERE REQ_NO = ? AND ISNULL({$kolom_status_msdata}, 0) = 0";
            $stmt_approve = sqlsrv_query($conn_msdata, $sql_approve, array($doc_no));
            $koneksi_aktif = $conn_msdata;
        } else {
            // --- JALUR BUDGET (PR) ---
            $sql_approve = "UPDATE Receive_Header SET status = 'APPROVED' WHERE receive_no = ? AND status = 'PENDING'"; // Asumsi nama tabel approval budget PR_Header
            $sql_approve = "UPDATE PR_Header SET status = 'APPROVED' WHERE pr_no = ? AND status = 'PENDING'";
            $stmt_approve = sqlsrv_query($conn, $sql_approve, array($doc_no));
            $koneksi_aktif = $conn;
        }
        
        if ($stmt_approve) {
            $rows_affected = sqlsrv_rows_affected($stmt_approve);
            if ($rows_affected > 0) {
                sqlsrv_commit($koneksi_aktif);
                $message = "<div class='alert alert-success shadow-sm'><i class='fas fa-check-circle'></i> Dokumen <strong>" . h($doc_no) . "</strong> berhasil disetujui.</div>";
            } else {
                $message = "<div class='alert alert-warning shadow-sm'><i class='fas fa-exclamation-triangle'></i> Dokumen <strong>" . h($doc_no) . "</strong> sudah disetujui sebelumnya atau tidak ditemukan.</div>";
            }
        } else {
            $err_msg = "";
            foreach(sqlsrv_errors() as $err) { $err_msg .= $err['message'] . "<br>"; }
            $message = "<div class='alert alert-danger shadow-sm'>Gagal memproses persetujuan:<br>" . $err_msg . "</div>";
        }
    }
}

// ==========================================================
// 2. AMBIL DAFTAR PENDING APPROVAL (HYBRID ALIASING)
// ==========================================================
if ($source === 'PO') {
    // Kueri MSDATA (Delphi) - Menyesuaikan Alias agar sama dengan PR
    $sql_list = "
        SELECT 
            r.REQ_NO AS doc_no, 
            r.REQ_DATE AS doc_date, 
            ISNULL(d.DEP_NAME, '-') AS dept_name, 
            'P1' AS plant_id, 
            ISNULL(r.REQ_PIC, '-') AS created_by, 
            0 AS total_amount 
        FROM dbo.REQUISITION r
        LEFT JOIN dbo.DEPT d ON r.DEP_ID = d.DEP_ID
        WHERE ISNULL(r.REQ_APP, 0) = 0
        ORDER BY r.REQ_DATE ASC
    ";
    $stmt_list = sqlsrv_query($conn_msdata, $sql_list);
} else {
    // Kueri Budgeting (PR Non-PO) - Menyesuaikan Alias agar sama dengan MSDATA
    $sql_list = "
        SELECT 
            ph.pr_no AS doc_no, 
            ph.pr_date AS doc_date, 
            ISNULL(md.department_name, ph.department_id) AS dept_name, 
            ph.plant_id, 
            ph.created_by,
            ph.total_amount 
        FROM PR_Header ph
        LEFT JOIN Master_Department md ON ph.department_id = md.department_id
        WHERE ph.status = 'PENDING' 
        ORDER BY ph.pr_date ASC
    ";
    $stmt_list = sqlsrv_query($conn, $sql_list);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Finance Approval - ERP Hybrid</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: Tahoma, Arial, sans-serif; font-size: 13px; padding: 20px; }
        .top-navbar { background-color: #004d40; color: #fff; padding: 12px 20px; font-size: 18px; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; border-radius: 4px;}
        .source-box { background: #fff; border: 2px dashed #004d40; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .table th { background-color: #e9ecef; vertical-align: middle; border: 1px solid #aaa; color: #333; font-weight: bold; }
        .table td { vertical-align: middle; }
        .btn-custom { font-size: 12px; font-weight: bold; padding: 5px 12px; border-radius: 4px; }
    </style>
</head>
<body>

<div class="container-fluid">

    <div class="top-navbar">
        <i class="fas fa-check-double"></i> Otorisasi Finance - Menunggu Persetujuan
    </div>

    <?php echo $message; ?>

    <!-- TOGGLE SUMBER DOKUMEN -->
    <div class="source-box text-center shadow-sm">
        <label class="font-weight-bold d-block text-dark mb-3"><i class="fas fa-random"></i> PILIH SUMBER DOKUMEN REQUEST</label>
        
        <div class="custom-control custom-radio custom-control-inline">
            <input type="radio" id="srcPR" name="doc_source_nav" class="custom-control-input" value="PR" onchange="window.location.href='<?php echo $current_file; ?>?source=PR'" <?php echo $source=='PR'?'checked':''; ?>>
            <label class="custom-control-label text-success font-weight-bold" for="srcPR" style="cursor: pointer;"><i class="fas fa-file-invoice"></i> Budgeting / PR (Non-PO)</label>
        </div>
        <div class="custom-control custom-radio custom-control-inline">
            <input type="radio" id="srcPO" name="doc_source_nav" class="custom-control-input" value="PO" onchange="window.location.href='<?php echo $current_file; ?>?source=PO'" <?php echo $source=='PO'?'checked':''; ?>>
            <label class="custom-control-label text-primary font-weight-bold" for="srcPO" style="cursor: pointer;"><i class="fas fa-shopping-cart"></i> Purchase Order (MSDATA)</label>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white font-weight-bold py-2">
            <i class="fas fa-list"></i> Daftar Request Pending (<?php echo $source === 'PO' ? 'MSDATA' : 'BUDGETING'; ?>)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0">
                    <thead class="text-center">
                        <tr>
                            <th width="5%">No</th>
                            <th width="15%">No. Dokumen</th>
                            <th width="12%">Tanggal</th>
                            <th width="23%">Plant & Departemen</th>
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
                                $date_str = $row['doc_date'] instanceof DateTime ? $row['doc_date']->format('d-m-Y') : date('d-m-Y', strtotime($row['doc_date']));
                                
                                // Jika MSDATA tidak memiliki total amount, tampilkan strip (-)
                                $amount_str = ($row['total_amount'] > 0) ? number_format($row['total_amount'], 2, ',', '.') : '-';
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td class="font-weight-bold <?php echo $source === 'PO' ? 'text-primary' : 'text-success'; ?>"><?php echo h($row['doc_no']); ?></td>
                            <td class="text-center"><?php echo $date_str; ?></td>
                            <td class="text-left">
                                <span class="badge badge-info"><?php echo h($row['plant_id']); ?></span> 
                                <?php echo h($row['dept_name']); ?>
                            </td>
                            <td class="text-center"><?php echo h($row['created_by']); ?></td>
                            <td class="text-right font-weight-bold"><?php echo $amount_str; ?></td>
                            <td class="text-center">
                                <form method="POST" action="<?php echo $current_file; ?>" onsubmit="return confirm('Apakah Anda yakin ingin menyetujui dokumen <?php echo h($row['doc_no']); ?>?');" style="margin:0;">
                                    <input type="hidden" name="action" value="approve_pr">
                                    <input type="hidden" name="doc_source" value="<?php echo h($source); ?>">
                                    <input type="hidden" name="doc_no" value="<?php echo h($row['doc_no']); ?>">
                                    <button type="submit" class="btn btn-sm btn-success btn-custom shadow-sm">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php 
                            endwhile; 
                        } else {
                            echo "<tr><td colspan='7' class='text-center text-muted py-5'><i class=".'"fas fa-folder-open fa-3x mb-3 text-secondary"'."></i><br>Tidak ada dokumen yang menunggu persetujuan.</td></tr>";
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
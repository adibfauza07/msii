<?php
// ==========================================================
// File: receive_report.php
// Deskripsi: Laporan Penerimaan Barang (GR) Hybrid - Optimized
// Kompabilitas: PHP 5.4, SQL Server 2008
// ==========================================================

require_once 'config.php';

// Fungsi Sanitasi XSS (PHP 5.4 Compatible)
function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

$message = "";
$source = isset($_GET['source']) ? trim($_GET['source']) : 'budget';

// ==========================================================
// 1. PROSES DELETE / BATAL PENERIMAAN BARANG (HYBRID & HIERARKIS)
// ==========================================================
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['receive_no'])) {
    $cancel_rcv_no = trim($_GET['receive_no']);
    $active_conn = ($source === 'budget') ? $conn : $conn_msdata;
    
    // Mulai Transaksi SQL Server
    if (sqlsrv_begin_transaction($active_conn) === false) {
        die("Transaction initialization failed.");
    }

    $is_deleted = true;

    if ($source === 'budget') {
        // Hapus Jalur Budgeting (Top-Down Deletion untuk menghindari FK Constraint)
        
        // 1A. Cari Nomor Jurnal yang terkait dengan penerimaan ini
        $sql_find_jurnal = "SELECT journal_no FROM Journal_Header WHERE ref_no = ?";
        $stmt_find_j = sqlsrv_query($active_conn, $sql_find_jurnal, array($cancel_rcv_no));
        
        if ($stmt_find_j && sqlsrv_has_rows($stmt_find_j)) {
            while ($row_j = sqlsrv_fetch_array($stmt_find_j, SQLSRV_FETCH_ASSOC)) {
                $j_no = $row_j['journal_no'];
                // Hapus Detail Jurnal
                $sql_del_j_det = "DELETE FROM Journal_Detail WHERE journal_no = ?";
                if (!sqlsrv_query($active_conn, $sql_del_j_det, array($j_no))) { $is_deleted = false; break; }
                // Hapus Header Jurnal
                $sql_del_j_head = "DELETE FROM Journal_Header WHERE journal_no = ?";
                if (!sqlsrv_query($active_conn, $sql_del_j_head, array($j_no))) { $is_deleted = false; break; }
            }
        }

        // 1B. Hapus Detail Penerimaan
        if ($is_deleted) {
            $sql_del_det = "DELETE FROM Receive_Det WHERE receive_no = ?";
            if (!sqlsrv_query($active_conn, $sql_del_det, array($cancel_rcv_no))) { $is_deleted = false; }
        }

        // 1C. Hapus Header Penerimaan
        if ($is_deleted) {
            $sql_del_head = "DELETE FROM Receive_Header WHERE receive_no = ?";
            if (!sqlsrv_query($active_conn, $sql_del_head, array($cancel_rcv_no))) { $is_deleted = false; }
        }
        
    } else {
        // Hapus Jalur MSDATA
        // Karena ada FK antara RECEIVE dan RECEIVE_DETAIL, kita ambil RCV_ID dulu
        $sql_find_rcv = "SELECT RCV_ID FROM RECEIVE WHERE RCV_NO = ?";
        $stmt_find = sqlsrv_query($active_conn, $sql_find_rcv, array($cancel_rcv_no));
        
        if ($stmt_find && sqlsrv_has_rows($stmt_find)) {
            $row_rcv = sqlsrv_fetch_array($stmt_find, SQLSRV_FETCH_ASSOC);
            $rcv_id = $row_rcv['RCV_ID'];

            // Hapus Detail (RECEIVE_DETAIL)
            $sql_del_det = "DELETE FROM RECEIVE_DETAIL WHERE RCV_ID = ?";
            $stmt_del_det = sqlsrv_query($active_conn, $sql_del_det, array($rcv_id));
            if (!$stmt_del_det) { $is_deleted = false; }

            // Hapus Header (RECEIVE)
            if ($is_deleted) {
                $sql_del_head = "DELETE FROM RECEIVE WHERE RCV_ID = ?";
                $stmt_del_head = sqlsrv_query($active_conn, $sql_del_head, array($rcv_id));
                if (!$stmt_del_head) { $is_deleted = false; }
            }
        } else {
            $is_deleted = false; // Data dokumen tidak ditemukan di DB
        }
    }

    // Eksekusi Transaksi
    if ($is_deleted) {
        sqlsrv_commit($active_conn);
        $message = "<div class='alert alert-success alert-dismissible'><button type='button' class='close' data-dismiss='alert'>&times;</button><i class='fas fa-check-circle'></i> Dokumen Penerimaan <strong>".h($cancel_rcv_no)."</strong> berhasil dibatalkan dan dihapus.</div>";
    } else {
        sqlsrv_rollback($active_conn);
        $err_msg = "";
        $errors = sqlsrv_errors();
        // Cegah Information Disclosure (Error disanitasi)
        if ($errors != null) { foreach ($errors as $error) { $err_msg .= h($error['message']) . "<br>"; } }
        $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Gagal membatalkan Penerimaan: " . $err_msg . "</div>";
    }
}

// ==========================================================
// 2. LOAD DATA COMBO BOX DEPARTEMEN DINAMIS
// ==========================================================
$filter_plant = isset($_GET['plant_id']) ? trim($_GET['plant_id']) : '';
$filter_dept  = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';
$filter_search= isset($_GET['search']) ? trim($_GET['search']) : ''; 

$opt_dept = '<option value="">-- Semua Departemen --</option>';

if ($source === 'budget') {
    $sql_m_dept = "SELECT department_id, department_name FROM Master_Department ORDER BY department_name ASC";
    $stmt_m_dept = sqlsrv_query($conn, $sql_m_dept);
    if ($stmt_m_dept) {
        while ($d = sqlsrv_fetch_array($stmt_m_dept, SQLSRV_FETCH_ASSOC)) {
            $selected = ($filter_dept === (string)$d['department_id']) ? 'selected' : '';
            $opt_dept .= '<option value="'.h($d['department_id']).'" '.$selected.'>'.h($d['department_name']).'</option>';
        }
    }
} else {
    // Pada msdata, filter dept digunakan untuk pencarian silang PR/PO
    $sql_m_dept = "SELECT DEP_ID, DEP_CODE, DEP_NAME FROM DEPT ORDER BY DEP_NAME ASC";
    $stmt_m_dept = sqlsrv_query($conn_msdata, $sql_m_dept);
    if ($stmt_m_dept) {
        while ($d = sqlsrv_fetch_array($stmt_m_dept, SQLSRV_FETCH_ASSOC)) {
            $selected = ($filter_dept === (string)$d['DEP_ID']) ? 'selected' : '';
            $opt_dept .= '<option value="'.(int)$d['DEP_ID'].'" '.$selected.'>'.h($d['DEP_CODE'].' - '.$d['DEP_NAME']).'</option>';
        }
    }
}

// ==========================================================
// 3. FILTER & PENCARIAN (READ - HYBRID)
// ==========================================================
$params = array();
$stmt_rcv = false;

// KUNCI OPTIMASI: Hanya eksekusi kueri jika Departemen sudah dipilih
$is_ready_to_load = ($filter_dept !== ''); 

if ($is_ready_to_load) {
    if ($source === 'budget') {
        // Query SQL Server 2008 - Jalur Budgeting
        $sql_rcv = "
            SELECT 
                rh.receive_no AS doc_no, 
                rh.receive_date AS doc_date, 
                rh.plant_id AS plant, 
                rh.department_id AS dept, 
                rh.received_by AS rcv_by, 
                rd.item_code AS code, 
                rd.qty_in AS qty,
                pd.pr_no AS ref_no,
                mi.item_name AS name,
                mi.uom AS unit
            FROM Receive_Header rh
            INNER JOIN Receive_Det rd ON rh.receive_no = rd.receive_no
            LEFT JOIN PR_Detail pd ON rd.pr_detail_id = pd.pr_detail_id
            LEFT JOIN Master_Item mi ON rd.item_code = mi.item_code
            WHERE rh.department_id = ?
        ";
        
        $params[] = $filter_dept; // Langsung masukkan parameter wajib
        
        if ($filter_plant !== '') { $sql_rcv .= " AND rh.plant_id = ?"; $params[] = $filter_plant; }
        if ($filter_search !== '') { 
            $sql_rcv .= " AND (rh.receive_no LIKE ? OR pd.pr_no LIKE ? OR rd.item_code LIKE ?)";
            $params[] = "%" . $filter_search . "%"; $params[] = "%" . $filter_search . "%"; $params[] = "%" . $filter_search . "%";
        }

        $sql_rcv .= " ORDER BY rh.receive_date DESC, rh.receive_no DESC";
        $stmt_rcv = sqlsrv_query($conn, $sql_rcv, $params);

    } else {
        // Query SQL Server 2008 - Jalur MSDATA
        // Menggunakan s.SUP_COMP (Bukan SUP_NAME)
        $sql_rcv = "
            SELECT 
                rh.RCV_NO AS doc_no, 
                rh.RCV_DATE AS doc_date, 
                '-' AS plant, 
                COALESCE(d.DEP_NAME, s.SUP_COMP, 'TIDAK DIKETAHUI') AS dept, 
                rh.RCV_PIC AS rcv_by, 
                i.ITEM_CODE AS code, 
                rd.RCVD_QTY AS qty,
                po.PO_NUM AS ref_no,
                i.ITEM_NAME AS name,
                i.ITEM_UNIT AS unit
            FROM RECEIVE rh
            INNER JOIN RECEIVE_DETAIL rd ON rh.RCV_ID = rd.RCV_ID
            LEFT JOIN PO po ON rd.PO_ID = po.PO_ID
            LEFT JOIN SUPPLIER s ON rh.SUP_ID = s.SUP_ID
            LEFT JOIN ITEMS i ON rd.ITEM_ID = i.ITEM_ID
            LEFT JOIN (
                -- Subquery menghindari duplikasi row jika 1 item di PO merujuk banyak PR
                SELECT DISTINCT PO_ID, ITEM_ID, REQ_ID FROM PO_DETAIL
            ) pod ON po.PO_ID = pod.PO_ID AND rd.ITEM_ID = pod.ITEM_ID
            LEFT JOIN REQUISITION req ON pod.REQ_ID = req.REQ_ID
            LEFT JOIN DEPT d ON req.DEP_ID = d.DEP_ID
            WHERE req.DEP_ID = ?
        ";

        $params[] = (int)$filter_dept; // Langsung masukkan parameter wajib
        
        if ($filter_search !== '') { 
            $sql_rcv .= " AND (rh.RCV_NO LIKE ? OR po.PO_NUM LIKE ? OR i.ITEM_CODE LIKE ? OR i.ITEM_NAME LIKE ?)";
            $params[] = "%" . $filter_search . "%"; $params[] = "%" . $filter_search . "%"; 
            $params[] = "%" . $filter_search . "%"; $params[] = "%" . $filter_search . "%";
        }

        $sql_rcv .= " ORDER BY rh.RCV_DATE DESC, rh.RCV_NO DESC";
        $stmt_rcv = sqlsrv_query($conn_msdata, $sql_rcv, $params);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penerimaan Barang (Hybrid) - ERP</title>
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

        <div class="card shadow-sm mb-4 no-print border-success">
            <div class="card-header bg-dark text-white font-weight-bold">
                <i class="fas fa-filter"></i> Filter Laporan Penerimaan Barang
            </div>
            <div class="card-body bg-light">
                <form method="GET" action="">
                    <!-- SUMBER DATA -->
                    <div class="form-row mb-3">
                        <div class="col-md-3">
                            <label class="font-weight-bold text-success">Pilih Sumber Data:</label>
                            <select name="source" class="form-control font-weight-bold" onchange="this.form.submit()">
                                <option value="budget" <?php echo ($source === 'budget') ? 'selected' : ''; ?>>Jalur Budgeting (Non-PO)</option>
                                <option value="msdata" <?php echo ($source === 'msdata') ? 'selected' : ''; ?>>Jalur Utama (PO - MSDATA)</option>
                            </select>
                        </div>
                        
                        <?php if ($source === 'budget'): ?>
                        <div class="col-md-2">
                            <label class="font-weight-bold">Plant:</label>
                            <select name="plant_id" class="form-control" onchange="this.form.submit()">
                                <option value="">-- Semua --</option>
                                <option value="P1" <?php echo ($filter_plant === 'P1') ? 'selected' : ''; ?>>P1</option>
                                <option value="P2" <?php echo ($filter_plant === 'P2') ? 'selected' : ''; ?>>P2</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-4">
                            <label class="font-weight-bold">Department (Wajib) <span class="text-danger">*</span> :</label>
                            <select name="department_id" class="form-control border-info" onchange="this.form.submit()" required>
                                <?php echo $opt_dept; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- PENCARIAN & TOMBOL -->
                    <div class="form-inline">
                        <input type="text" name="search" class="form-control border-success mr-2" style="width: 450px;" placeholder="Pencarian No. Penerimaan, No. PO/PR, atau Kode Barang..." value="<?php echo h($filter_search); ?>">
                        <button type="submit" class="btn btn-success"><i class="fas fa-search"></i> Cari Data</button>
                        <a href="receive_report.php?source=<?php echo h($source); ?>" class="btn btn-secondary ml-2"><i class="fas fa-sync"></i> Reset</a>
                        <button type="button" class="btn btn-info ml-auto" onclick="window.print()"><i class="fas fa-print"></i> Cetak A5</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-success text-white font-weight-bold no-print">
                <i class="fas fa-boxes"></i> Data Goods Receipt - <?php echo ($source === 'budget') ? 'BUDGETING' : 'MSDATA'; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>No. Dokumen (GR)</th>
                                <th>Tanggal Terima</th>
                                <?php if($source === 'budget') echo '<th class="text-center">Plant</th>'; ?>
                                <th>Departemen / Supplier</th>
                                <th>Penerima (PIC)</th>
                                <th>No. Referensi (PR/PO)</th>
                                <th>Kode & Nama Barang</th>
                                <th class="text-right">Qty Diterima</th>
                                <th class="text-center no-print" width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grand_total_qty = 0;

                            // Cek apakah filter departemen sudah diisi
                            if (!$is_ready_to_load) {
                                $colspan = ($source === 'budget') ? '9' : '8';
                                echo "<tr><td colspan='".$colspan."' class='text-center text-muted py-5'>
                                        <i class='fas fa-search fa-3x mb-3 text-secondary'></i><br>
                                        <h5 class='text-secondary'>Silakan pilih <strong>Department</strong> pada filter di atas</h5>
                                        <p>Data penerimaan barang akan tampil setelah Anda memilih departemen.</p>
                                      </td></tr>";
                            } 
                            // Jika departemen sudah dipilih, proses penampilan data
                            else {
                                if ($stmt_rcv !== false) {
                                    $has_data = false;
                                    while ($row = sqlsrv_fetch_array($stmt_rcv, SQLSRV_FETCH_ASSOC)) {
                                        $has_data = true;
                                        $rcv_date = $row['doc_date'] instanceof DateTime ? $row['doc_date']->format('d-m-Y H:i') : date('d-m-Y H:i', strtotime($row['doc_date']));
                                        
                                        $grand_total_qty += (float)$row['qty'];
                            ?>
                            <tr>
                                <td><strong class="text-success"><?php echo h($row['doc_no']); ?></strong></td>
                                <td><?php echo $rcv_date; ?></td>
                                <?php if($source === 'budget') echo '<td class="text-center">'.h($row['plant']).'</td>'; ?>
                                <td><?php echo h($row['dept']); ?></td>
                                <td><i class="fas fa-user text-muted"></i> <?php echo h($row['rcv_by']); ?></td>
                                <td><span class="badge badge-info"><?php echo h($row['ref_no'] ? $row['ref_no'] : '-'); ?></span></td>
                                <td>
                                    <strong><?php echo h($row['code']); ?></strong><br>
                                    <small class="text-muted"><?php echo h($row['name']); ?></small>
                                </td>
                                <td class="text-right font-weight-bold text-success">
                                    <?php echo number_format($row['qty'], 2, ',', '.') . ' ' . h($row['unit']); ?>
                                </td>
                                <td class="text-center no-print">
                                    <div class="btn-group" role="group">
                                        
                                        <!-- LOGIKA ROUTING CETAK LAPORAN -->
                                        <?php if ($source === 'budget'): ?>
                                            <a href="report_penerimaan.php?id=<?php echo urlencode($row['doc_no']); ?>" target="_blank" class="btn btn-sm btn-info" title="Cetak Bukti Budgeting">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="report_penerimaan_msdata.php?id=<?php echo urlencode($row['doc_no']); ?>&plant=<?php echo urlencode($filter_plant); ?>" target="_blank" class="btn btn-sm btn-primary" title="Cetak Bukti PO (MSDATA)">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- Tombol Batal/Hapus -->
                                        <a href="?source=<?php echo urlencode($source); ?>&action=cancel&receive_no=<?php echo urlencode($row['doc_no']); ?>&department_id=<?php echo urlencode($filter_dept); ?>&plant_id=<?php echo urlencode($filter_plant); ?>&search=<?php echo urlencode($filter_search); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Peringatan: Membatalkan dokumen ini akan menghapus data penerimaan secara permanen (Termasuk Jurnal jika ada). Lanjutkan?');" title="Batalkan Penerimaan">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                                    }
                                    if (!$has_data) {
                                        $colspan = ($source === 'budget') ? '9' : '8';
                                        echo "<tr><td colspan='".$colspan."' class='text-center text-danger py-4 font-weight-bold'>Data penerimaan tidak ditemukan untuk departemen ini.</td></tr>";
                                    }
                                } else {
                                    $colspan = ($source === 'budget') ? '9' : '8';
                                    $err_str = "";
                                    foreach(sqlsrv_errors() as $e) { $err_str .= h($e['message']) . " "; }
                                    echo "<tr><td colspan='".$colspan."' class='text-center text-danger py-4'>Gagal memuat data: " . $err_str . "</td></tr>";
                                }
                            }
                            ?>
                        </tbody>
                        
                        <?php if ($is_ready_to_load && isset($has_data) && $has_data): ?>
                        <tfoot class="thead-light">
                            <tr>
                                <?php $footer_span = ($source === 'budget') ? '7' : '6'; ?>
                                <th colspan="<?php echo $footer_span; ?>" class="text-right align-middle font-weight-bold">GRAND TOTAL QTY DITERIMA:</th>
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
// Bebaskan resource database (Best Practice PHP)
if (isset($stmt_rcv) && $stmt_rcv !== false) sqlsrv_free_stmt($stmt_rcv);
if (isset($stmt_m_dept) && $stmt_m_dept !== false) sqlsrv_free_stmt($stmt_m_dept);
if ($conn !== false) sqlsrv_close($conn);
if (isset($conn_msdata) && $conn_msdata !== false) sqlsrv_close($conn_msdata);
?>
<?php
// Pastikan koneksi database sudah ada
if (!isset($conn)) { die("Direct access not allowed."); }

// Ambil parameter filter jika ada (untuk pencarian di header)
$filter_no   = isset($_GET['f_no']) ? trim($_GET['f_no']) : '';
$filter_part = isset($_GET['f_part']) ? trim($_GET['f_part']) : '';
$filter_status = isset($_GET['f_status']) ? trim($_GET['f_status']) : '';

// 1. QUERY UNTUK PILIHAN FILTER (SELECT2)
$q_filter_no = q("SELECT DISTINCT CONTROL_NO FROM PROSES_CHANGE WHERE CONTROL_NO IS NOT NULL ORDER BY CONTROL_NO ASC");
$q_filter_part = q("SELECT DISTINCT ITEM_ID, PART_NO, PART_NAME FROM PC_ITEM_CUSTOMER_VIEW ORDER BY PART_NAME ASC");

// 2. QUERY UTAMA DENGAN FILTER DYNAMIC
$sql = "SELECT 
            P.CONTROL_ID, 
            P.CONTROL_NO, 
            P.CONTROL_DATE1, 
            P.MODEL, 
            P.PIC_NAME, 
            P.STATUS,
            V.PART_NO,
            V.PART_NAME
        FROM PROSES_CHANGE P
        LEFT JOIN PC_ITEM_CUSTOMER_VIEW V ON P.ITEM_ID = V.ITEM_ID
        WHERE 1=1";

$params = array();

if ($filter_no != '') {
    $sql .= " AND P.CONTROL_NO = ?";
    $params[] = $filter_no;
}
if ($filter_part != '') {
    $sql .= " AND P.ITEM_ID = ?";
    $params[] = $filter_part;
}
if ($filter_status != '') {
    $sql .= " AND P.STATUS = ?";
    $params[] = $filter_status;
}

$sql .= " ORDER BY P.CONTROL_ID DESC";
$query = q($sql, $params);
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet" />

<style>
    /* Custom style agar mirip dashboard PE */
    .search-card { background: #f8f9fa; border: 1px solid #e3e6f0; border-radius: 0.35rem; }
    .select2-container--bootstrap-5 .select2-selection { font-size: 0.8rem; min-height: 31px; }
    .table th { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
    .table td { font-size: 12px; vertical-align: middle; }
</style>

<div class="container-fluid px-0">
    <div class="card search-card shadow-sm mb-4">
        <div class="card-body p-3">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-search me-2"></i>Filter Pencarian</h6>
            <form method="GET" action="">
                <input type="hidden" name="page" value="history">
                
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="x-small fw-bold text-muted mb-1">Control No</label>
                        <select name="f_no" id="filter_no" class="form-select form-select-sm select2-init">
                            <option value="">-- Semua Control No --</option>
                            <?php while($fno = sqlsrv_fetch_array($q_filter_no, SQLSRV_FETCH_ASSOC)): ?>
                                <option value="<?php echo $fno['CONTROL_NO']; ?>" <?php echo $filter_no == $fno['CONTROL_NO'] ? 'selected' : ''; ?>>
                                    <?php echo $fno['CONTROL_NO']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="x-small fw-bold text-muted mb-1">Part Name / No</label>
                        <select name="f_part" id="filter_part" class="form-select form-select-sm select2-init">
                            <option value="">-- Semua Part --</option>
                            <?php while($fpt = sqlsrv_fetch_array($q_filter_part, SQLSRV_FETCH_ASSOC)): ?>
                                <option value="<?php echo $fpt['ITEM_ID']; ?>" <?php echo $filter_part == $fpt['ITEM_ID'] ? 'selected' : ''; ?>>
                                    <?php echo $fpt['PART_NO'] . " - " . $fpt['PART_NAME']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="x-small fw-bold text-muted mb-1">Status</label>
                        <select name="f_status" class="form-select form-select-sm">
                            <option value="">-- Semua Status --</option>
                            <option value="OPEN" <?php echo $filter_status == 'OPEN' ? 'selected' : ''; ?>>OPEN</option>
                            <option value="CLOSE" <?php echo $filter_status == 'CLOSE' ? 'selected' : ''; ?>>CLOSE</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="btn-group w-100">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel-fill me-1"></i> Cari</button>
                            <a href="?page=history" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-clockwise"></i></a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-primary mb-0"><i class="bi bi-clock-history me-2"></i>Riwayat Perubahan PCIS</h5>
            </div>
            
            <div class="table-responsive">
                <table id="tableHistoryPCIS" class="table table-striped table-hover table-bordered table-sm w-100">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" width="12%">Control No</th>
                            <th class="text-center" width="10%">Tanggal</th>
                            <th class="text-center" width="10%">Model</th>
                            <th>Part No / Name</th>
                            <th width="12%">PIC</th>
                            <th class="text-center" width="8%">Status</th>
                            <th class="text-center" width="10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td class="fw-bold text-center text-primary"><?php echo $row['CONTROL_NO']; ?></td>
                            <td class="text-center"><?php echo $row['CONTROL_DATE1'] ? $row['CONTROL_DATE1']->format('d-m-Y') : '-'; ?></td>
                            <td class="text-center"><?php echo $row['MODEL']; ?></td>
                            <td>
                                <small class="text-muted fw-bold"><?php echo $row['PART_NO']; ?></small><br>
                                <?php echo $row['PART_NAME']; ?>
                            </td>
                            <td><?php echo $row['PIC_NAME']; ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo ($row['STATUS'] == 'CLOSE') ? 'success' : 'warning text-dark'; ?>">
                                    <?php echo $row['STATUS'] == 'CLOSE' ? 'CLOSE' : 'OPEN'; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="print_pcis.php?no=<?php echo $row['CONTROL_NO']; ?>" target="_blank" class="btn btn-outline-secondary btn-sm" title="Print"><i class="bi bi-printer text-dark"></i></a>
                                    <a href="?page=input_change&id=<?php echo $row['CONTROL_ID']; ?>" class="btn btn-outline-primary btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <a href="delete_4m.php?id=<?php echo $row['CONTROL_ID']; ?>" onclick="return confirm('Yakin hapus data ini?')" class="btn btn-outline-danger btn-sm" title="Hapus"><i class="bi bi-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // 1. Inisialisasi Select2 pada Header Pencarian dengan Tema Bootstrap 5
    $('.select2-init').select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $(document).body
    });

    // 2. Inisialisasi DataTables dengan Konfigurasi Pagination Pilihanmu (5, 10, 25)
    $('#tableHistoryPCIS').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": false, // Kita matikan search box default karena sudah buat filter di atas
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "lengthMenu": [5, 10, 25], // Menampilkan pilihan list sesuai request-mu
        "pageLength": 10, // Default baris data yang muncul pertama kali
        "language": {
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ entries",
            "paginate": {
                "first": "First",
                "last": "Last",
                "next": "Next",
                "previous": "Previous"
            }
        }
    });
});
</script>
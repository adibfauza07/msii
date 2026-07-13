<?php
if (!isset($conn)) { die("Direct access not allowed."); }

$filter_no     = isset($_GET['f_no']) ? trim($_GET['f_no']) : '';
$filter_cust   = isset($_GET['f_cust']) ? trim($_GET['f_cust']) : ''; // Parameter baru untuk Customer
$filter_part   = isset($_GET['f_part']) ? trim($_GET['f_part']) : '';
$filter_status = isset($_GET['f_status']) ? trim($_GET['f_status']) : '';

$q_filter_no   = q("SELECT DISTINCT CONTROL_NO FROM PROSES_CHANGE WHERE CONTROL_NO IS NOT NULL ORDER BY CONTROL_NO ASC");
$q_filter_cust = q("SELECT DISTINCT CUST_ID, CUST_COMP FROM PC_ITEM_CUSTOMER_VIEW WHERE CUST_COMP IS NOT NULL ORDER BY CUST_COMP ASC"); // Query list customer
$q_filter_part = q("SELECT DISTINCT ITEM_ID, PART_NO, PART_NAME FROM PC_ITEM_CUSTOMER_VIEW ORDER BY PART_NAME ASC");

// Query utama ditambahkan kolom V.CUST_COMP
$sql = "SELECT P.CONTROL_ID, P.CONTROL_NO, P.CONTROL_DATE1, P.MODEL, P.PIC_NAME, P.STATUS, V.PART_NO, V.PART_NAME, V.CUST_COMP 
        FROM PROSES_CHANGE P 
        LEFT JOIN PC_ITEM_CUSTOMER_VIEW V ON P.ITEM_ID = V.ITEM_ID 
        WHERE 1=1";

$params = array();
if ($filter_no != '') { $sql .= " AND P.CONTROL_NO = ?"; $params[] = $filter_no; }
if ($filter_cust != '') { $sql .= " AND V.CUST_ID = ?"; $params[] = $filter_cust; } // Filter clause customer
if ($filter_part != '') { $sql .= " AND P.ITEM_ID = ?"; $params[] = $filter_part; }
if ($filter_status != '') { $sql .= " AND P.STATUS = ?"; $params[] = $filter_status; }

$sql .= " ORDER BY P.CONTROL_DATE1 DESC, P.CONTROL_ID DESC"; // Urutan default DB
$query = q($sql, $params);
?>

<style>
    .filter-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; }
    .x-small-lbl { font-size: 11px; font-weight: bold; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block; }
    .select2-container--bootstrap-5 .select2-selection { font-size: 12.5px !important; min-height: 31px !important; }
    .btn-aksi { padding: 3px 8px; font-size: 11px; } /* Memperkecil tombol aksi agar rapi */
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="input_pcis.php" class="btn btn-primary btn-sm fw-bold shadow-sm"><i class="bi bi-plus-lg me-1"></i>Buat Pengajuan PCIS (4M)</a>
    <a href="?page=rekap" class="btn btn-outline-success btn-sm fw-bold shadow-sm"><i class="bi bi-file-earmark-excel me-1"></i> Cetak Rekap / Summary</a>
</div>

<div class="card filter-box shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="">
            <input type="hidden" name="page" value="history">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="x-small-lbl">Control No</label>
                    <select name="f_no" id="select2_control_no" class="form-select form-select-sm select2-searchable">
                        <option value="">-- Semua --</option>
                        <?php while($fn = sqlsrv_fetch_array($q_filter_no, SQLSRV_FETCH_ASSOC)): ?>
                            <option value="<?php echo rtrim($fn['CONTROL_NO']); ?>" <?php echo ($filter_no == rtrim($fn['CONTROL_NO'])) ? 'selected' : ''; ?>><?php echo rtrim($fn['CONTROL_NO']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="x-small-lbl">Customer</label>
                    <select name="f_cust" id="select2_cust" class="form-select form-select-sm select2-searchable">
                        <option value="">-- Semua Customer --</option>
                        <?php while($fc = sqlsrv_fetch_array($q_filter_cust, SQLSRV_FETCH_ASSOC)): ?>
                            <option value="<?php echo $fc['CUST_ID']; ?>" <?php echo ($filter_cust == $fc['CUST_ID']) ? 'selected' : ''; ?>><?php echo $fc['CUST_COMP']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="x-small-lbl">Part Name / No</label>
                    <select name="f_part" id="select2_part" class="form-select form-select-sm select2-searchable">
                        <option value="">-- Semua Part --</option>
                        <?php while($fp = sqlsrv_fetch_array($q_filter_part, SQLSRV_FETCH_ASSOC)): ?>
                            <option value="<?php echo $fp['ITEM_ID']; ?>" <?php echo ($filter_part == $fp['ITEM_ID']) ? 'selected' : ''; ?>><?php echo $fp['PART_NO'] . " - " . $fp['PART_NAME']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="x-small-lbl">Status</label>
                    <select name="f_status" class="form-select form-select-sm fw-bold text-center">
                        <option value="">-- Semua --</option>
                        <option value="OPEN" <?php echo ($filter_status == 'OPEN') ? 'selected' : ''; ?>>OPEN</option>
                        <option value="CLOSE" <?php echo ($filter_status == 'CLOSE') ? 'selected' : ''; ?>>CLOSE</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary btn-sm w-100 fw-bold"><i class="bi bi-search me-1"></i> Cari</button>
                    <?php if($filter_no != '' || $filter_cust != '' || $filter_part != '' || $filter_status != ''): ?>
                        <a href="?page=history" class="btn btn-outline-danger btn-sm" title="Reset Filter"><i class="bi bi-x-circle"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-clock-history me-2"></i>Riwayat Perubahan PCIS</h5>
        <div class="table-responsive">
            <table id="tableHistoryPCIS" class="table table-striped table-hover table-sm w-100" style="font-size: 12px;">
                <thead class="table-dark">
                    <tr>
                        <th width="14%">Control No</th>
                        <th width="9%">Tanggal</th>
                        <th width="15%">Customer</th> <th width="17%">Part Name / Details</th>
                        <th width="10%">Model</th>
                        <th width="10%">PIC</th>
                        <th width="5%" class="text-center">Status</th>
                        <th width="20%" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): ?>
                    <tr>
                        <td class="fw-bold text-primary"><?php echo rtrim($row['CONTROL_NO']); ?></td>
                        <?php 
                        $sortDate = ($row['CONTROL_DATE1'] instanceof DateTime) ? $row['CONTROL_DATE1']->format('Ymd') : '00000000';
                        $viewDate = ($row['CONTROL_DATE1'] instanceof DateTime) ? $row['CONTROL_DATE1']->format('d-m-Y') : '-';
                        ?>
                        <td data-order="<?php echo $sortDate; ?>"><?php echo $viewDate; ?></td>
                        <td class="fw-bold text-dark"><?php echo $row['CUST_COMP'] ? $row['CUST_COMP'] : '-'; ?></td> <td><span class="fw-bold text-muted"><?php echo $row['PART_NO']; ?></span><br><?php echo $row['PART_NAME']; ?></td>
                        <td><?php echo $row['MODEL']; ?></td>
                        <td><?php echo $row['PIC_NAME']; ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo (trim($row['STATUS']) == 'CLOSE') ? 'success' : 'warning text-dark'; ?>">
                                <?php echo trim($row['STATUS']) == 'CLOSE' ? 'CLOSE' : 'OPEN'; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="print_pcis.php?no=<?php echo $row['CONTROL_NO']; ?>" target="_blank" class="btn btn-outline-secondary btn-aksi" title="Print Standard"><i class="bi bi-printer"></i></a>
                                
                                <a href="print_pcis_epson.php?no=<?php echo $row['CONTROL_NO']; ?>" target="_blank" class="btn btn-outline-info btn-aksi fw-bold text-dark" title="Print Format Epson"><i class="bi bi-printer-fill text-info"></i> Epson</a>
                                
                                <a href="input_pcis.php?id=<?php echo $row['CONTROL_ID']; ?>" class="btn btn-outline-primary btn-aksi" title="Edit"><i class="bi bi-pencil"></i></a>
                                <a href="delete_4m.php?id=<?php echo $row['CONTROL_ID']; ?>" onclick="return confirm('Yakin hapus data ini?')" class="btn btn-outline-danger btn-aksi" title="Hapus"><i class="bi bi-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.select2-searchable').select2({
        theme: 'bootstrap-5',
        width: '100%',
        allowClear: true
    });

    $('#tableHistoryPCIS').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "responsive": true,
        "lengthMenu": [5, 10, 25, 50],
        "pageLength": 10,
        "order": [[1, "desc"]]
    });
});
</script>
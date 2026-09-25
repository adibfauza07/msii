<?php
if (!isset($conn)) { die("Direct access not allowed."); }

$filter_no     = isset($_GET['f_no']) ? trim($_GET['f_no']) : '';
$filter_cust   = isset($_GET['f_cust']) ? trim($_GET['f_cust']) : ''; 
$filter_part   = isset($_GET['f_part']) ? trim($_GET['f_part']) : '';
$filter_status = isset($_GET['f_status']) ? trim($_GET['f_status']) : '';

$q_filter_no   = q("SELECT DISTINCT CONTROL_NO FROM PROSES_CHANGE WHERE CONTROL_NO IS NOT NULL ORDER BY CONTROL_NO ASC");
$q_filter_cust = q("SELECT DISTINCT CUST_ID, CUST_COMP FROM PC_ITEM_CUSTOMER_VIEW WHERE CUST_COMP IS NOT NULL ORDER BY CUST_COMP ASC"); 
$q_filter_part = q("SELECT DISTINCT ITEM_ID, PART_NO, PART_NAME FROM PC_ITEM_CUSTOMER_VIEW ORDER BY PART_NAME ASC");

$sql = "SELECT P.CONTROL_ID, P.CONTROL_NO, P.CONTROL_DATE1, P.MODEL, P.PIC_NAME, P.STATUS, V.PART_NO, V.PART_NAME, V.CUST_COMP 
        FROM PROSES_CHANGE P 
        LEFT JOIN PC_ITEM_CUSTOMER_VIEW V ON P.ITEM_ID = V.ITEM_ID 
        WHERE 1=1";

$params = array();
if ($filter_no != '') { $sql .= " AND P.CONTROL_NO = ?"; $params[] = $filter_no; }
if ($filter_cust != '') { $sql .= " AND V.CUST_ID = ?"; $params[] = $filter_cust; } 
if ($filter_part != '') { $sql .= " AND P.ITEM_ID = ?"; $params[] = $filter_part; }
if ($filter_status != '') { $sql .= " AND P.STATUS = ?"; $params[] = $filter_status; }

$sql .= " ORDER BY P.CONTROL_DATE1 DESC, P.CONTROL_ID DESC"; 
$query = q($sql, $params);
?>

<style>
    /* Styling khusus konten dalam (Sidebar sudah diurus parent dashboard) */
    .filter-card { background: #fff; border-top: 4px solid #8b5cf6 !important; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); }
    .x-small-lbl { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 6px; display: block; letter-spacing: 0.5px;}
    .select2-container--bootstrap-5 .select2-selection { font-size: 13px !important; min-height: 36px !important; border-radius: 8px; border-color: #ced4da; }
    .btn-aksi { padding: 5px 10px; font-size: 12px; border-radius: 6px; font-weight: 600; } 
    .table-custom-header th { background-color: #1e2833 !important; color: #fff; font-weight: 600; text-align: center; }
    .card-custom { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); background: white; margin-bottom: 25px; overflow: hidden; }
    .page-title-box { background: white; padding: 15px 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 20px; }
</style>

<div class="page-title-box d-flex justify-content-between align-items-center">
    <h4 class="mb-0 fw-bold text-dark"><i class="fas fa-history text-primary me-2"></i> Riwayat Perubahan PCIS</h4>
    <div>
        <a href="input_pcis.php" class="btn btn-primary btn-sm fw-bold shadow-sm me-2"><i class="fas fa-plus me-1"></i> Buat Pengajuan (4M)</a>
        <a href="?page=rekap" class="btn btn-outline-success btn-sm fw-bold shadow-sm"><i class="fas fa-file-excel me-1"></i> Rekap / Summary</a>
    </div>
</div>

<div class="card filter-card mb-4 border-0">
    <div class="card-body p-4">
        <form method="GET" action="">
            <input type="hidden" name="page" value="history">
            <div class="row g-3 align-items-end">
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
                    <select name="f_status" class="form-select fw-bold text-center" style="min-height: 36px; border-radius: 8px;">
                        <option value="">-- Semua --</option>
                        <option value="OPEN" <?php echo ($filter_status == 'OPEN') ? 'selected' : ''; ?>>OPEN</option>
                        <option value="CLOSE" <?php echo ($filter_status == 'CLOSE') ? 'selected' : ''; ?>>CLOSE</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary w-100 fw-bold shadow-sm" style="border-radius: 8px; min-height: 36px;"><i class="fas fa-search me-1"></i> Cari</button>
                    <?php if($filter_no != '' || $filter_cust != '' || $filter_part != '' || $filter_status != ''): ?>
                        <a href="?page=history" class="btn btn-outline-danger shadow-sm" style="border-radius: 8px; min-height: 36px; padding-top: 6px;" title="Reset Filter"><i class="fas fa-times-circle"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card card-custom">
    <div class="card-body p-0">
        <div class="table-responsive p-3">
            <table id="tableHistoryPCIS" class="table table-striped table-hover align-middle w-100" style="font-size: 13px;">
                <thead class="table-custom-header">
                    <tr>
                        <th width="14%">Control No</th>
                        <th width="9%">Tanggal</th>
                        <th width="15%">Customer</th> 
                        <th width="17%">Part Name / Details</th>
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
                        <td class="text-center" data-order="<?php echo $sortDate; ?>"><?php echo $viewDate; ?></td>
                        <td class="fw-bold text-dark"><?php echo $row['CUST_COMP'] ? $row['CUST_COMP'] : '-'; ?></td> 
                        <td><span class="fw-bold text-muted"><?php echo $row['PART_NO']; ?></span><br><?php echo $row['PART_NAME']; ?></td>
                        <td class="text-center"><?php echo $row['MODEL'] ? $row['MODEL'] : '-'; ?></td>
                        <td class="text-center"><?php echo $row['PIC_NAME']; ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo (trim($row['STATUS']) == 'CLOSE') ? 'success' : 'warning text-dark'; ?> px-2 py-1">
                                <?php echo trim($row['STATUS']) == 'CLOSE' ? 'CLOSE' : 'OPEN'; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group shadow-sm">
                                <a href="print_pcis.php?no=<?php echo $row['CONTROL_NO']; ?>" target="_blank" class="btn btn-outline-secondary btn-aksi" title="Print Standard"><i class="fas fa-print"></i></a>
                                
                                <a href="print_pcis_epson.php?no=<?php echo $row['CONTROL_NO']; ?>" target="_blank" class="btn btn-outline-info btn-aksi fw-bold text-dark" title="Print Format Epson"><i class="fas fa-print text-info me-1"></i> Epson</a>
                                
                                <a href="input_pcis.php?id=<?php echo $row['CONTROL_ID']; ?>" class="btn btn-outline-primary btn-aksi" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="delete_4m.php?id=<?php echo $row['CONTROL_ID']; ?>" onclick="return confirm('Yakin hapus data ini?')" class="btn btn-outline-danger btn-aksi" title="Hapus"><i class="fas fa-trash-alt"></i></a>
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
<?php
// Pastikan koneksi database sudah ada
if (!isset($conn)) { die("Direct access not allowed."); }

// Cek apakah user sudah menekan tombol filter atau belum
$is_filtered = isset($_GET['filter']) ? true : false;

// Ambil Parameter Filter Sesuai Stored Procedure
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');    
$cust_alias = isset($_GET['cust_alias']) ? trim($_GET['cust_alias']) : '';
$item_code  = isset($_GET['item_code']) ? trim($_GET['item_code']) : '';
$f_status   = isset($_GET['f_status']) ? trim($_GET['f_status']) : '';

// Query Lookup Dropdown Filter (Select2)
$q_cust_list = q("SELECT DISTINCT CUST_COMP FROM PC_ITEM_CUSTOMER_VIEW WHERE CUST_COMP IS NOT NULL ORDER BY CUST_COMP ASC");
$q_item_list = q("SELECT DISTINCT PART_NO, PART_NAME FROM PC_ITEM_CUSTOMER_VIEW ORDER BY PART_NAME ASC");

$query = false;

// Hanya jalankan query eksekusi SP jika tombol filter sudah ditekan
if ($is_filtered) {
    $sp_start = date('Ymd', strtotime($start_date));
    $sp_end   = date('Ymd', strtotime($end_date));  
    
    $sp_item = ($item_code != '') ? trim($item_code) : '%';
    $sp_cust = ($cust_alias != '') ? trim($cust_alias) : '%';

    // Panggil Stored Procedure REP_PCIS1
    $sql = "{CALL REP_PCIS1(?, ?, ?, ?)}";
    $params = array($sp_start, $sp_end, $sp_item, $sp_cust);
    $query = q($sql, $params);
}
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet" />

<style>
    .filter-card { background: #f8f9fa; border-top: 3px solid #8b5cf6 !important; border-radius: 8px; }
    .table th { font-size: 11px; text-align: center; vertical-align: middle; background-color: #1f2a36 !important; color: white; padding: 8px 4px; }
    .table td { font-size: 11px; vertical-align: middle; }
    .badge-4m { font-size: 9px; padding: 3px 6px; margin: 1px; display: inline-block; }
    .x-small { font-size: 11px; }
</style>

<div class="container-fluid px-0">
    <div class="card filter-card shadow-sm mb-4">
        <div class="card-body p-3">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-journal-text me-2"></i>Filter Rekapitulasi Perubahan (Summary SP Model)</h6>
            <form method="GET" action="dashboard_4m.php">
                <input type="hidden" name="page" value="rekap">
                
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="x-small fw-bold text-muted mb-1">Tanggal Awal</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="x-small fw-bold text-muted mb-1">Tanggal Akhir</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="x-small fw-bold text-muted mb-1">Customer</label>
                        <select name="cust_alias" id="filter_cust" class="form-select form-select-sm select2-init">
                            <option value="">-- Semua --</option>
                            <?php while($c = sqlsrv_fetch_array($q_cust_list, SQLSRV_FETCH_ASSOC)): 
                                $clean_cust = trim($c['CUST_COMP']);
                                if ($clean_cust == '') continue;
                            ?> 
                                <option value="<?php echo $clean_cust; ?>" <?php echo $cust_alias == $clean_cust ? 'selected' : ''; ?>>
                                    <?php echo $clean_cust; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="x-small fw-bold text-muted mb-1">Part / Item Name</label>
                        <select name="item_code" id="filter_item" class="form-select form-select-sm select2-init">
                            <option value="" <?php echo ($item_code == '') ? 'selected' : ''; ?>>-- Semua Part --</option>
                            <?php 
                            while($i = sqlsrv_fetch_array($q_item_list, SQLSRV_FETCH_ASSOC)): 
                                $clean_part_no = isset($i['PART_NO']) ? trim($i['PART_NO']) : '';
                                $part_name     = isset($i['PART_NAME']) ? trim($i['PART_NAME']) : '';
                                if ($clean_part_no == '') continue;
                            ?>
                                <option value="<?php echo $clean_part_no; ?>" <?php echo ($item_code === $clean_part_no) ? 'selected' : ''; ?>><?php echo $clean_part_no . " - " . $part_name; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="x-small fw-bold text-muted mb-1">Status</label>
                        <select name="f_status" class="form-select form-select-sm fw-bold text-center">
                            <option value="">-- Semua --</option>
                            <option value="OPEN" <?php echo $f_status == 'OPEN' ? 'selected' : ''; ?>>OPEN</option>
                            <option value="CLOSE" <?php echo $f_status == 'CLOSE' ? 'selected' : ''; ?>>CLOSE</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="btn-group w-100">
                            <button type="submit" name="filter" value="1" class="btn btn-primary btn-sm fw-bold"><i class="bi bi-funnel"></i> Filter</button>
                            
                            <a href="print_rekap.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&cust_alias=<?php echo urlencode($cust_alias); ?>&item_code=<?php echo $item_code; ?>&f_status=<?php echo $f_status; ?>&filter=<?php echo $is_filtered ? '1' : '0'; ?>" target="_blank" id="btnCetakSummary" class="btn btn-success btn-sm fw-bold">
                                <i class="bi bi-printer"></i> Cetak Summary
                            </a>
                            <a href="?page=rekap" class="btn btn-secondary btn-sm" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tableRekap4M" class="table table-striped table-hover table-bordered table-sm w-100">
                    <thead class="table-dark">
                        <tr>
                            <th width="12%">No. Control</th>
                            <th width="10%">Tanggal</th>
                            <th width="15%">Customer</th>
                            <th width="12%">Part Code</th>
                            <th width="22%">Part Name / Item</th>
                            <th width="10%">Model</th>
                            <th width="14%">Kategori (4M)</th>
                            <th width="8%">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no_data = true;
                        if ($is_filtered && $query):
                            while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                                $row_cust = isset($row['CUST_ALIAS']) ? trim($row['CUST_ALIAS']) : '-';
                                $current_status = isset($row['STATUS']) ? trim(strval($row['STATUS'])) : 'OPEN';
                                
                                if ($f_status != '' && $current_status != $f_status) { continue; }
                                $no_data = false;
                        ?>
                        <tr>
                            <td class="fw-bold text-center text-primary"><?php echo isset($row['CONTROL_NO']) ? rtrim($row['CONTROL_NO']) : '-'; ?></td>
                            <td class="text-center">
                                <?php 
                                if (isset($row['CONTROL_DATE1'])) {
                                    echo $row['CONTROL_DATE1'] instanceof DateTime ? $row['CONTROL_DATE1']->format('d-m-Y') : date('d-m-Y', strtotime($row['CONTROL_DATE1']));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td class="fw-bold text-secondary"><?php echo $row_cust; ?></td>
                            <td class="fw-bold text-dark"><?php echo isset($row['PART_CODE']) ? trim($row['PART_CODE']) : (isset($row['PART_NO']) ? trim($row['PART_NO']) : '-'); ?></td>
                            <td><?php echo isset($row['PART_NAME']) ? trim($row['PART_NAME']) : (isset($row['ITEM_NAME']) ? trim($row['ITEM_NAME']) : '-'); ?></td>
                            <td class="text-center fw-bold text-muted"><?php echo isset($row['MODEL']) ? trim($row['MODEL']) : '-'; ?></td>
                            <td>
                                <div class="d-flex flex-wrap justify-content-center">
                                    <?php if(!empty($row['MAN'])): ?><span class="badge bg-danger badge-4m">Man</span><?php endif; ?>
                                    <?php if(!empty($row['MACHINE'])): ?><span class="badge bg-primary badge-4m">Machine</span><?php endif; ?>
                                    <?php if(!empty($row['METHOD'])): ?><span class="badge bg-warning text-dark badge-4m">Method</span><?php endif; ?>
                                    <?php if(!empty($row['MATERIAL'])): ?><span class="badge bg-success badge-4m">Material</span><?php endif; ?>
                                    <?php if(!empty($row['OTHER'])): ?><span class="badge bg-secondary badge-4m">Other</span><?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo ($current_status == 'CLOSE') ? 'success' : 'warning text-dark'; ?>">
                                    <?php echo $current_status; ?>
                                </span>
                            </td>
                        </tr>
                        <?php 
                            endwhile; 
                        endif; 
                        
                        if ($no_data): 
                        ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted bg-light">
                                <?php if (!$is_filtered): ?>
                                    <i class="bi bi-info-circle me-1"></i> Silakan tentukan rentang tanggal parameter di atas, lalu klik tombol <b>Filter</b> untuk memunculkan data rekap.
                                <?php else: ?>
                                    <i class="bi bi-exclamation-triangle me-1"></i> Tidak ada data rekapitulasi ditemukan untuk kriteria filter ini.
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
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
    $('.select2-init').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });

    // Inisialisasi DataTables
    var table = $('#tableRekap4M').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true, // WAJIB TRUE: Agar kolom search DataTables aktif
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "lengthMenu": [5, 10, 25],
        "pageLength": 10
    });

    // PERBAIKAN LOGIKA UTAMA: Jembatan Sinkronisasi Visual Search box ke Tombol Cetak Summary
    // Ambil URL dasar dari tombol cetak summary bawaan PHP
    var basePrintUrl = $('#btnCetakSummary').attr('href');

    // Setiap kali user mengetik sesuatu di kotak 'Search' DataTables
    table.on('search.dt', function() {
        var searchKeyword = table.search(); // Ambil teks pencarian (misal: 'Diamond')
        
        if(searchKeyword.trim() !== "") {
            // Pasang parameter &dt_search=Diamond ke dalam URL tombol cetak secara dinamis
            $('#btnCetakSummary').attr('href', basePrintUrl + '&dt_search=' + encodeURIComponent(searchKeyword));
        } else {
            // Kembalikan ke URL semula jika kotak pencarian dikosongkan
            $('#btnCetakSummary').attr('href', basePrintUrl);
        }
    });
});
</script>
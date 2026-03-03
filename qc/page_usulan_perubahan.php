<?php
// FILE: msii/qc/page_usulan_perubahan.php

if (!isset($conn) || $conn === false) {
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    $active_plant = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p2'; // Default p2
    
    if(!isset($_SESSION['erp_user'])) $_SESSION['erp_user'] = $_SESSION['db_user'];
    if(!isset($_SESSION['erp_pass'])) $_SESSION['erp_pass'] = $_SESSION['db_pass'];
    $_SESSION['server_sql'] = "192.168.0.9"; 
    require_once __DIR__ . '/../config/database.php';
}

$custList = [];
$sqlCust = "SELECT DISTINCT CUST_ID, CUST_COMP FROM CUST WHERE CUST_INACTIVE = 0 ORDER BY CUST_COMP";
$resCust = sqlsrv_query($conn, $sqlCust);
if($resCust) { while ($r = sqlsrv_fetch_array($resCust, SQLSRV_FETCH_ASSOC)) { $custList[] = $r; } }

$requestList = [];
$sqlReq = "SELECT DISTINCT request FROM usulan_request ORDER BY request";
$resReq = sqlsrv_query($conn, $sqlReq);
if ($resReq) { while ($r = sqlsrv_fetch_array($resReq, SQLSRV_FETCH_ASSOC)) { $requestList[] = $r['request']; } }

$remarkList = [];
$sqlRem = "SELECT DISTINCT keterangan FROM usulan_remark ORDER BY keterangan";
$resRem = sqlsrv_query($conn, $sqlRem);
if ($resRem) { while ($r = sqlsrv_fetch_array($resRem, SQLSRV_FETCH_ASSOC)) { $remarkList[] = $r['keterangan']; } }

$start_date = isset($_REQUEST['start_date']) ? $_REQUEST['start_date'] : date('Y-m-01');
$end_date   = isset($_REQUEST['end_date']) ? $_REQUEST['end_date'] : date('Y-m-d');
$cust_id    = isset($_REQUEST['cust_id']) ? $_REQUEST['cust_id'] : '';
$kode_usul  = isset($_REQUEST['kode_usul']) ? $_REQUEST['kode_usul'] : '';
$request_by = isset($_REQUEST['request_by']) ? $_REQUEST['request_by'] : '';
$remark     = isset($_REQUEST['remark']) ? $_REQUEST['remark'] : '';

if (isset($_POST['delete_id_marker'])) {
    $del_id = $_POST['delete_id'];
    $sqlDel = "DELETE FROM USULAN_PERUBAHAN WHERE KODE_USUL = ?";
    $stmtDel = sqlsrv_query($conn, $sqlDel, array($del_id));
    if($stmtDel) {
        echo "<div class='alert alert-success alert-dismissible fade show'>Data $del_id berhasil dihapus. <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } else {
        echo "<div class='alert alert-danger'>Gagal menghapus data.</div>";
    }
}
?>

<link href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/responsive/3.0.2/css/responsive.bootstrap5.min.css" rel="stylesheet">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold text-secondary m-0" id="pageTitle">
        <i class="bi bi-shield-check text-warning"></i> DASHBOARD USULAN (PLANT 2)
    </h4>
    <div>
        <a href="dashboard_qc.php?page=input_usulan&plant=p2" class="btn btn-sm btn-primary shadow-sm">
            <i class="bi bi-plus-lg"></i> Buat Usulan Baru
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-bottom-0">
        <h6 class="m-0 fw-bold text-secondary"><i class="bi bi-funnel text-warning"></i> Filter Data Plant 2</h6>
    </div>
    <div class="card-body bg-light rounded-bottom">
      <form id="filterForm_p2" class="row g-2 align-items-end">
        <input type="hidden" name="filter_active" value="true">
        
        <div class="col-6 col-md-2">
          <label class="form-label small fw-bold text-muted">Dari</label>
          <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" class="form-control form-control-sm border-0 shadow-sm">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small fw-bold text-muted">Sampai</label>
          <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" class="form-control form-control-sm border-0 shadow-sm">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label small fw-bold text-muted">Customer</label>
          <select id="cust_id" name="cust_id" class="form-select form-select-sm border-0 shadow-sm">
            <option value="">-- Semua --</option>
            <?php foreach ($custList as $c): ?>
              <option value="<?= $c['CUST_ID'] ?>" <?= ($c['CUST_ID']==$cust_id)?'selected':'' ?>><?= htmlspecialchars($c['CUST_COMP']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-2">
          <label class="form-label small fw-bold text-muted">Kode Usulan</label>
          <input type="text" id="kode_usul" name="kode_usul" class="form-control form-control-sm border-0 shadow-sm" value="<?= htmlspecialchars($kode_usul) ?>" placeholder="Cari...">
        </div>
        <div class="col-6 col-md-1">
          <label class="form-label small fw-bold text-muted">Request</label>
          <select id="request_by" name="request_by" class="form-select form-select-sm border-0 shadow-sm">
            <option value="">All</option>
            <?php foreach ($requestList as $r): ?>
              <option value="<?= $r ?>" <?= ($r==$request_by)?'selected':'' ?>><?= htmlspecialchars($r) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-1">
          <label class="form-label small fw-bold text-muted">Remark</label>
          <select id="remark" name="remark" class="form-select form-select-sm border-0 shadow-sm">
            <option value="">All</option>
            <?php foreach ($remarkList as $r): ?>
              <option value="<?= $r ?>" <?= ($r==$remark)?'selected':'' ?>><?= htmlspecialchars($r) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-1">
          <button type="button" id="btn-filter-ajax-p2" class="btn btn-warning fw-bold btn-sm w-100 shadow-sm">
            <i class="bi bi-search"></i> Cari
          </button>
        </div>
      </form>
    </div>
</div>

<div id="ajax-loading-p2" style="display: none;" class="text-center py-4">
    <div class="spinner-border text-warning" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Loading...</span>
    </div>
    <div class="mt-2 fw-bold text-secondary">Memuat Data Plant 2...</div>
</div>

<div id="report-buttons-container-p2" class="mb-3 text-end"></div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive p-3">
        <table id="laporanTable_p2" class="table table-striped table-hover align-middle" style="width:100%; font-size: 13px;">
          <thead class="table-dark">
            <tr>
              <th class="text-nowrap text-center">Aksi</th><th>Kode</th><th>Issue Date</th><th>Customer</th>
              <th>Part No</th><th>Part Name</th><th>Doc Name</th><th>Req By</th>
              <th>Isi Revisi</th><th>Alasan Revisi</th><th>Plan</th><th>Target</th><th>Remark</th>
            </tr>
          </thead>
          <tbody id="laporan-table-body-p2">
            <tr><td colspan="13" class="text-center text-muted py-5"><i class="bi bi-search fs-1 d-block mb-2 text-light"></i> Silakan gunakan tombol cari untuk menampilkan data Plant 2.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
</div>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white py-3">
        <h6 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Konfirmasi Hapus</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <i class="bi bi-trash text-danger" style="font-size: 3rem;"></i>
        <p class="mt-3 mb-1">Yakin ingin menghapus data usulan ini?</p>
        <p class="fw-bold fs-5 text-dark mb-0" id="delete-info-item"></p>
        <small class="text-muted">Data yang dihapus tidak dapat dikembalikan.</small>
      </div>
      <div class="modal-footer bg-light py-2">
        <form id="deleteForm" method="POST">
          <input type="hidden" id="delete_id_input" name="delete_id">
          <input type="hidden" name="delete_id_marker" value="true">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger px-4">Ya, Hapus!</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/3.0.2/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/3.0.2/js/responsive.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#btn-filter-ajax-p2').on('click', function() {
        var formData = $('#filterForm_p2').serialize();
        
        $('#ajax-loading-p2').show();
        $('#report-buttons-container-p2').empty();
        
        try {
            if (typeof $.fn.dataTable !== 'undefined' && $.fn.dataTable.isDataTable('#laporanTable_p2')) {
                $('#laporanTable_p2').DataTable().clear().destroy();
            }
        } catch(e) { console.warn("DataTables reset skipped."); }
        
        $('#laporan-table-body-p2').empty();

        $.ajax({
            url: 'ajax_filter_p2.php', 
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                $('#ajax-loading-p2').hide();
                if (response && response.table) {
                    $('#report-buttons-container-p2').html(response.buttons);
                    $('#laporan-table-body-p2').html(response.table);
                    
                    if (!response.table.includes('text-danger')) {
                        $('#laporanTable_p2').DataTable({
                            destroy: true, 
                            responsive: true,
                            language: { 
                                "search": "Cari Cepat:",
                                "emptyTable": "Tidak ada data usulan yang ditemukan di Plant 2.",
                                "zeroRecords": "Pencarian tidak menemukan hasil."
                            },
                            "columnDefs": [ { "orderable": false, "targets": 0 } ],
                            "order": [[ 1, "desc" ]] 
                        });
                    }
                } else {
                    $('#laporan-table-body-p2').html('<tr><td colspan="13" class="text-danger text-center fw-bold py-4">Format data dari server tidak sesuai.</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                $('#ajax-loading-p2').hide();
                $('#laporan-table-body-p2').html('<tr><td colspan="13" class="text-danger text-center fw-bold py-4"><i class="bi bi-exclamation-octagon fs-3 d-block mb-2"></i> Gagal Memuat Data Plant 2.<br><small class="text-muted fw-normal">Error: ' + error + '</small></td></tr>');
            }
        });
    });

    $('#confirmDeleteModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var info = button.data('info');
        var modal = $(this);
        modal.find('#delete_id_input').val(id);
        modal.find('#delete-info-item').text(info);
        $('#deleteForm').attr('action', window.location.href);
    });
});

function printFormByKode_P2() {
  var kode = prompt("Masukkan Kode Usulan (Contoh: U00097):");
  if (kode) { window.open('report_form_pdf.php?kode_usul=' + encodeURIComponent(kode) + '&plant=p2', '_blank'); }
}
</script>
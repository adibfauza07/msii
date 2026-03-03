<?php
// Koneksi ke Database PLANT 1
require_once '../config/Database_p1.php'; 

// === Ambil daftar untuk dropdown filter ===
$custList = [];
$sqlCust = "SELECT DISTINCT CUST_ID, CUST_COMP FROM CUST WHERE CUST_INACTIVE = 0 ORDER BY CUST_COMP";
$resCust = sqlsrv_query($conn, $sqlCust);
if ($resCust) {
    while ($r = sqlsrv_fetch_array($resCust, SQLSRV_FETCH_ASSOC)) {
        $custList[] = $r;
    }
}

$requestList = [];
$sqlReq = "SELECT DISTINCT request FROM usulan_request ORDER BY request";
$resReq = sqlsrv_query($conn, $sqlReq);
if ($resReq) {
    while ($r = sqlsrv_fetch_array($resReq, SQLSRV_FETCH_ASSOC)) {
        $requestList[] = $r['request'];
    }
}

$remarkList = [];
$sqlRem = "SELECT DISTINCT keterangan FROM usulan_remark ORDER BY keterangan";
$resRem = sqlsrv_query($conn, $sqlRem);
if ($resRem) {
    while ($r = sqlsrv_fetch_array($resRem, SQLSRV_FETCH_ASSOC)) {
        $remarkList[] = $r['keterangan'];
    }
} 

// === Parameter filter ===
$start_date = isset($_REQUEST['start_date']) ? $_REQUEST['start_date'] : date('Y-m-01');
$end_date   = isset($_REQUEST['end_date']) ? $_REQUEST['end_date'] : date('Y-m-d');
$cust_id    = isset($_REQUEST['cust_id']) ? $_REQUEST['cust_id'] : '';
$kode_usul  = isset($_REQUEST['kode_usul']) ? $_REQUEST['kode_usul'] : '';
$request_by = isset($_REQUEST['request_by']) ? $_REQUEST['request_by'] : '';
$remark     = isset($_REQUEST['remark']) ? $_REQUEST['remark'] : '';

// Cek apakah filter/delete dijalankan
// (Hanya untuk refresh status delete, data utama diload via AJAX)
$has_filter = isset($_REQUEST['delete_id_marker']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard Usulan Perubahan (Plant 1)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="../assets/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<!-- DataTables CSS -->
<link href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/responsive/3.0.2/css/responsive.bootstrap5.min.css" rel="stylesheet">
<style>
body { background-color: #f7f9fc; font-family: "Segoe UI", Arial, sans-serif; }
.main-content { padding: 2rem; }
.content-header { margin-bottom: 1.5rem; }
.card { border: none; border-radius: 0.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
.card-filter { background-color: #fff; }
.card-header { background-color: #f9f9f9; border-bottom: 1px solid #f0f0f0; padding: 1rem 1.25rem; font-weight: 600; }
.card-body { padding: 1.5rem; }
.table thead { background: #343a40; color: white; } 
.table-hover tbody tr:hover { background-color: #e6f7ff; } /* P1 Tema Biru Muda */
</style>
</head>
<body>

<main class="main-content">
  
  <!-- Header Halaman -->
  <div class="content-header d-flex justify-content-between align-items-center">
    <h1 class="h3"><i class="bi bi-shield-check"></i> Dashboard Usulan Perubahan (Plant 1)</h1>
    <div>
        <a href="../qc_p1/input_usulan.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Tambah Usulan Baru
        </a>
    </div>
  </div>

  <!-- Kartu Filter -->
  <div class="card card-filter">
    <div class="card-header">Filter Data (Plant 1)</div>
    <div class="card-body">
      <form id="filterForm" class="row g-3 align-items-end">
        <div class="col-12 col-md-3 col-lg-2">
          <label class="form-label">Dari Tanggal</label>
          <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" class="form-control">
        </div>
        <div class="col-12 col-md-3 col-lg-2">
          <label class="form-label">Sampai Tanggal</label>
          <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" class="form-control">
        </div>
        <div class="col-12 col-md-6 col-lg-3">
          <label class="form-label">Customer</label>
          <select id="cust_id" name="cust_id" class="form-select">
            <option value="">-- Semua Customer --</option>
            <?php foreach ($custList as $c): ?>
              <option value="<?= $c['CUST_ID'] ?>" <?= ($c['CUST_ID']==$cust_id)?'selected':'' ?>>
                <?= htmlspecialchars($c['CUST_COMP']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-6 col-lg-2">
          <label class="form-label">Kode Usulan</label>
          <input type="text" id="kode_usul" name="kode_usul" class="form-control" value="<?= htmlspecialchars($kode_usul) ?>" placeholder="Cari Kode...">
        </div>
        <div class="col-12 col-md-3 col-lg-1">
          <label class="form-label">Request</label>
          <select id="request_by" name="request_by" class="form-select">
            <option value="">All</option>
            <?php foreach ($requestList as $r): ?>
              <option value="<?= $r ?>" <?= ($r==$request_by)?'selected':'' ?>><?= htmlspecialchars($r) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-3 col-lg-1">
          <label class="form-label">Remark</label>
          <select id="remark" name="remark" class="form-select">
            <option value="">All</option>
            <?php foreach ($remarkList as $r): ?>
              <option value="<?= $r ?>" <?= ($r==$remark)?'selected':'' ?>><?= htmlspecialchars($r) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-4 col-lg-1">
          <!-- ID P1 -->
          <button type="button" id="btn-filter-ajax-p1" class="btn btn-primary w-100">
            <i class="bi bi-search"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Container untuk Tombol Report -->
  <div id="report-buttons-container-p1"></div>

  <!-- Indikator Loading -->
  <div class="ajax-loading" id="ajax-loading-p1" style="display: none; text-align: center; padding: 20px; font-size: 1.2rem; color: #555;">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-2">Sedang memuat data Plant 1...</p>
  </div>

  <!-- Kartu Hasil Data -->
  <div class="card card-results">
    <div class="card-header">Hasil Data (Plant 1)</div>
    <div class="card-body">
      <div class="table-responsive">
        <table id="laporanTable" class="table table-striped table-bordered" style="width:100%">
          <thead class="table-dark">
            <tr>
              <th class="text-nowrap">Aksi</th>
              <th class="text-nowrap">Kode Usulan</th>
              <th class="text-nowrap">Issue Date</th>
              <th class="text-nowrap">Customer</th>
              <th class="text-nowrap">Part No</th>
              <th class="text-nowrap">Part Name</th>
              <th class="text-nowrap">Document Name</th>
              <th class="text-nowrap">Request By</th>
              <th>Isi Revisi</th>
              <th>Alasan Revisi</th>
              <th class="text-nowrap">Plan Date</th>
              <th class="text-nowrap">Target Date</th>
              <th class="text-nowrap">Remark</th>
            </tr>
          </thead>
          <tbody id="laporan-table-body-p1">
             <tr>
                <td colspan="13" class="text-center text-muted">Silakan gunakan filter di atas untuk menampilkan data.</td>
             </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<!-- Modal Konfirmasi Hapus -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmDeleteModalLabel">Konfirmasi Hapus (Plant 1)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Apakah Anda yakin ingin menghapus data ini?</p>
        <p class="text-muted"><strong id="delete-info-item"></strong></p>
        <p class="text-danger">Tindakan ini tidak dapat dikembalikan.</p>
      </div>
      <div class="modal-footer">
        <form id="deleteForm" action="../qc_p1/delete_usulan.php" method="POST">
          <input type="hidden" id="delete_id_input" name="delete_id">
          <!-- Kirim ulang filter -->
          <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
          <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
          <input type="hidden" name="cust_id" value="<?php echo htmlspecialchars($cust_id); ?>">
          <input type="hidden" name="kode_usul" value="<?php echo htmlspecialchars($kode_usul); ?>">
          <input type="hidden" name="request_by" value="<?php echo htmlspecialchars($request_by); ?>">
          <input type="hidden" name="remark" value="<?php echo htmlspecialchars($remark); ?>">
          <input type="hidden" name="delete_id_marker" value="true">
          
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">Ya, Hapus Data</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../assets/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/3.0.2/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/3.0.2/js/responsive.bootstrap5.min.js"></script>

<script>
var dataTableInstance = null;

$(document).ready(function() {
  
  $('#confirmDeleteModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget); 
    var id = button.data('id'); 
    var info = button.data('info'); 
    var modal = $(this);
    modal.find('#delete_id_input').val(id); 
    modal.find('#delete-info-item').text(info); 

    // Update filter values di form delete
    modal.find('input[name="start_date"]').val( $('#start_date').val() );
    modal.find('input[name="end_date"]').val( $('#end_date').val() );
    modal.find('input[name="cust_id"]').val( $('#cust_id').val() );
    modal.find('input[name="kode_usul"]').val( $('#kode_usul').val() );
    modal.find('input[name="request_by"]').val( $('#request_by').val() );
    modal.find('input[name="remark"]').val( $('#remark').val() );
  });

  // AJAX Handler untuk Tombol Filter (ID P1)
  $('#btn-filter-ajax-p1').on('click', function() {
    var formData = $('#filterForm').serialize();

    $('#ajax-loading-p1').show();
    $('#report-buttons-container-p1').empty(); 
    
    if(dataTableInstance) {
        dataTableInstance.destroy();
    }
    $('#laporan-table-body-p1').html('<tr><td colspan="13" class="text-center text-muted">Memuat data...</td></tr>');

    $.ajax({
        url: '../qc_p1/ajax_filter_p1.php', 
        type: 'POST',
        data: formData,
        // dataType: 'json', // KITA MATIKAN DULU AGAR TIDAK ERROR OTOMATIS
        success: function(rawResponse) {
            $('#ajax-loading-p1').hide();
            
            try {
                // Bersihkan whitespace jika ada
                var cleanResponse = rawResponse.trim();
                var response = JSON.parse(cleanResponse);

                // Jika BERHASIL diparse menjadi JSON
                $('#report-buttons-container-p1').html(response.buttons);
                $('#laporan-table-body-p1').html(response.table);
                
                if (response.table && response.table.indexOf('text-danger') === -1) {
                    dataTableInstance = $('#laporanTable').DataTable({
                        responsive: true,
                        language: { "search": "Cari:" },
                        "columnDefs": [
                            { "orderable": false, "targets": 0 }
                        ],
                        "order": [[ 1, "desc" ]]
                    });
                }

            } catch (e) {
                // JIKA GAGAL DIPARSE (Berarti ada error PHP/HTML yang nyelip)
                console.error("JSON Parse Error:", e);
                console.log("Raw Response:", rawResponse);
                
                alert("Terjadi masalah saat membaca data. Cek pesan error di tabel.");
                
                // Tampilkan apa yang sebenarnya dikirim oleh server agar kita bisa baca errornya
                var debugHtml = '<div class="alert alert-warning">Data diterima tapi format salah. Berikut isinya:</div>';
                debugHtml += '<pre style="background:#f8f9fa; padding:10px; border:1px solid #ddd; max-height:300px; overflow:auto;">' + $('<div>').text(rawResponse).html() + '</pre>';
                
                $('#report-buttons-container-p1').html('<div class="alert alert-danger">Gagal memuat tombol.</div>');
                $('#laporan-table-body-p1').html('<tr><td colspan="13">' + debugHtml + '</td></tr>');
            }
        },
        error: function(xhr, status, error) {
            $('#ajax-loading-p1').hide();
            $('#report-buttons-container-p1').html('<div class="alert alert-danger">Gagal koneksi ke server.</div>');
            $('#laporan-table-body-p1').html('<tr><td colspan="13" class="text-center text-danger">Error AJAX: ' + xhr.status + ' ' + xhr.statusText + '</td></tr>');
        }
    });
  });

});

function printFormByKode_P1() {
  var kode = prompt("Masukkan Kode Usulan yang ingin Anda cetak (Contoh: U00097):");
  if (kode) {
    window.open('../qc_p1/report_form_pdf.php?kode_usul=' + encodeURIComponent(kode), '_blank');
  }
}
</script>

</body>
</html>
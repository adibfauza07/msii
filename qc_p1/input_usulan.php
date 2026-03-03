<?php
require_once '../config/Database_p1.php'; // DB P1

// Ambil data untuk dropdown
$custList = [];
$sqlCust = "SELECT CUST_ID, CUST_COMP FROM CUST WHERE CUST_INACTIVE = 0 ORDER BY CUST_COMP";
$resCust = sqlsrv_query($conn, $sqlCust);
while ($r = sqlsrv_fetch_array($resCust, SQLSRV_FETCH_ASSOC)) {
    $custList[] = $r;
}

$requestList = [];
$sqlReq = "SELECT DISTINCT request FROM usulan_request ORDER BY request";
$resReq = sqlsrv_query($conn, $sqlReq);
while ($r = sqlsrv_fetch_array($resReq, SQLSRV_FETCH_ASSOC)) {
    $requestList[] = $r['request'];
}

$remarkList = [];
$sqlRem = "SELECT DISTINCT keterangan FROM usulan_remark ORDER BY keterangan";
$resRem = sqlsrv_query($conn, $sqlRem);
while ($r = sqlsrv_fetch_array($resRem, SQLSRV_FETCH_ASSOC)) {
    $remarkList[] = $r['keterangan'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Input Usulan Perubahan (Plant 1)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="../assets/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<!-- CSS untuk Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<style>
body { background:#f7f9fc; font-family:"Segoe UI",Arial,sans-serif;}
.card { border: none; border-radius: 0.75rem; box-shadow: 0 3px 10px rgba(0,0,0,0.08);}
.card-header { background-color: #fff; border-bottom: 1px solid #f0f0f0; padding: 0.75rem 1.25rem; font-weight: 600; color: #ff5f5f; font-size: 1.1rem;}
label, .form-label { font-weight: 600; }
.select2-container--bootstrap-5 .select2-selection { border-radius: 0.375rem !important; }
.select2-container--bootstrap-5 .select2-selection--single { height: calc(1.5em + 0.75rem + 2px) !important; padding: 0.375rem 0.75rem !important; }
.select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered { line-height: 1.5 !important; color: #495057; }
.select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow { height: 100% !important; top: 0 !important; }
.select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field { border-radius: 0.25rem; }
</style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-file-earmark-plus"></i> Form Input Usulan Perubahan (Plant 1)</h3>
    <a href="usulan_perubahan.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left-circle"></i> Kembali
    </a>
  </div>

  <form method="POST" action="simpan_usulan.php">
    <div class="row g-3">

      <div class="col-md-8">
        <!-- Card 1: Informasi Utama -->
        <div class="card mb-3">
          <div class="card-header">Informasi Utama</div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Kode Usulan</label>
                <input type="text" name="kode_usul" class="form-control" placeholder="Contoh: VU0300" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Issue Date</label>
                <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-md-12">
                <label class="form-label">Customer</label>
                <select name="cust_id" class="form-select select2-static" required>
                  <option value="">-- Pilih Customer --</option>
                  <?php foreach ($custList as $c): ?>
                    <option value="<?= $c['CUST_ID'] ?>"><?= htmlspecialchars($c['CUST_COMP']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Part No</label>
                <select name="item_id" id="item_id_ajax" class="form-select">
                  <option value="">-- Ketik untuk mencari Part No --</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Part Name</label>
                <input type="text" id="part_name" name="part_name" class="form-control" readonly style="background-color: #e9ecef;">
              </div>
            </div>
          </div>
        </div>

        <!-- Card 2: Detail Permintaan -->
        <div class="card mb-3">
          <div class="card-header">Detail Permintaan</div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Request By</label>
                <select name="pj" class="form-select select2-static">
                  <option value="">-- Pilih --</option>
                  <?php foreach ($requestList as $r): ?>
                    <option value="<?= $r ?>"><?= htmlspecialchars($r) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Document Name</label>
                <input type="text" name="judul_dok" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label">Tipe Usulan</label>
                <div class="input-group">
                  <div class="form-check form-check-inline pt-2">
                    <input class="form-check-input" type="radio" name="tipe_usulan" id="tipe_baru" value="BARU" checked>
                    <label class="form-check-label" for="tipe_baru">Baru</label>
                  </div>
                  <div class="form-check form-check-inline pt-2">
                    <input class="form-check-input" type="radio" name="tipe_usulan" id="tipe_revisi" value="REVISI">
                    <label class="form-check-label" for="tipe_revisi">Revisi</Labe>
                  </div>
                  <input type="number" name="revisi_1" class="form-control form-control-sm" placeholder="No. Revisi" style="max-width: 100px;">
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Card 3: Deskripsi Revisi -->
        <div class="card mb-3">
          <div class="card-header">Deskripsi Revisi</div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Isi Revisi</label>
                <textarea name="isi_revisi" class="form-control" rows="3"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Alasan Revisi</label>
                <textarea name="alasan_revisi" class="form-control" rows="3"></textarea>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- INFO KANAN (col-md-4) -->
      <div class="col-md-4">
        <!-- Dokumen Terkait -->
        <div class="card mb-3">
          <div class="card-header">Dokumen Terkait</div>
          <div class="card-body">
            <div class="row">
              <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_std" value="1" id="is_std"><label class="form-check-label" for="is_std">Inspection STD</label></div></div>
              <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="fmea" value="1" id="fmea"><label class="form-check-label" for="fmea">FMEA</label></div></div>
              <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="wi" value="1" id="wi"><label class="form-check-label" for="wi">Work Instruction</label></div></div>
              <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="qcpc" value="1" id="qcpc"><label class="form-check-label" for="qcpc">QCPC</label></div></div>
              <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="check_point" value="1" id="check_point"><label class="form-check-label" for="check_point">Check Point</label></div></div>
              <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="setting_par" value="1" id="setting_par"><label class="form-check-label" for="setting_par">Setting Parameter</label></div></div>
              <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="std_pack" value="1" id="std_pack"><label class="form-check-label" for="std_pack">Packing STD</label></div></div>
              <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="other" value="1" id="other"><label class="form-check-label" for="other">Other</label></div></div>
            </div>
          </div>
        </div>

        <!-- Tanggal & Remark -->
        <div class="card mb-3">
          <div class="card-header">Tanggal & Remark</div>
          <div class="card-body">
            <div class="mb-2">
              <label class="form-label">Plan Date</label>
              <input type="date" name="plant_date" class="form-control">
            </div>
            <div class="mb-2">
              <label class="form-label">Target Date</label>
              <input type="date" name="target_date" class="form-control">
            </div>
            <div class="mb-2">
              <label class="form-label">Remark</label>
              <select name="remarks" class="form-select select2-static">
                <option value="">-- Pilih Remark --</option>
                <?php foreach ($remarkList as $r): ?>
                  <option value="<?= $r ?>"><?= htmlspecialchars($r) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- BUTTON BAWAH -->
    <div class="row">
      <div class="col-12 d-flex justify-content-end mt-4 border-top pt-3">
        <a href="usulan_perubahan.php" class="btn btn-outline-secondary me-3">
          <i class="bi bi-x-circle"></i> Batal
        </a>
        <button type="submit" class="btn btn-primary px-4">
          <i class="bi bi-save"></i> Simpan Data
        </button>
      </div>
    </div>

  </form>
</div>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../assets/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
  
  $('.select2-static').select2({
    theme: "bootstrap-5",
    width: '100%'
  });

  $('#item_id_ajax').select2({
    theme: "bootstrap-5",
    width: '100%',
    placeholder: 'Ketik min 2 huruf untuk cari Part No...',
    minimumInputLength: 2, 
    ajax: {
      url: 'search_items.php', 
      dataType: 'json',
      delay: 250, 
      data: function (params) {
        return {
          term: params.term, 
          page: params.page
        };
      },
      processResults: function (data, params) {
        params.page = params.page || 1;
        return {
          results: data.results,
          pagination: {
            more: data.pagination.more
          }
        };
      },
      cache: true
    },
    templateResult: function (data) {
      if (data.loading) {
        return data.text;
      }
      var $container = $(
          "<div class='select2-result-repository clearfix'>" +
              "<div class='select2-result-repository__title'></div>" +
          "</div>"
      );
      $container.find(".select2-result-repository__title").text(data.text);
      return $container;
    },
    templateSelection: function (data) {
      if (data.item_no) {
        return data.item_no; 
      }
      return data.text; 
    }
  });

  $('#item_id_ajax').on('select2:select', function (e) {
    var data = e.params.data;
    var partName = data.item_name || ''; 
    $('#part_name').val(partName);
  });

  $('#item_id_ajax').on('select2:unselect', function (e) {
    $('#part_name').val(''); 
  });

});
</script>
</body>
</html>
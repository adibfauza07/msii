<?php
require_once '../config/Database_p1.php'; // DB P1

// 1. Cek URL
if (!isset($_GET['kode_usul'])) {
    header("Location: usulan_perubahan.php");
    exit();
}

$kode_usul_edit = $_GET['kode_usul'];

// 2. Ambil data
$sql = "
SELECT 
    U.*, 
    I.ITEM_NO, 
    I.ITEM_NAME
FROM 
    USULAN_PERUBAHAN U
LEFT JOIN 
    ITEMS I ON U.ITEM_ID = I.ITEM_ID
WHERE 
    U.KODE_USUL = ?
";
$params = [$kode_usul_edit];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false || !($data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    echo "Data usulan dengan kode '{$kode_usul_edit}' tidak ditemukan.";
    exit();
}

// 3. Data Dropdown
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
<title>Edit Usulan Perubahan (Plant 1 - <?= htmlspecialchars($data['KODE_USUL']) ?>)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="../assets/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<style>
body { background:#f7f9fc; font-family:"Segoe UI",Arial,sans-serif;}
.card { border:none; border-radius:1rem; box-shadow:0 3px 10px rgba(0,0,0,0.1);}
label { font-weight: 600; }
.form-label { font-weight: 600; }
.card-info { background:#eef5ff; }
.card-info-2 { background:#e8fff1; }
.select2-container--bootstrap-5 .select2-selection { border-radius: 0.375rem !important; }
.select2-container--bootstrap-5 .select2-selection--single { height: calc(1.5em + 0.75rem + 2px) !important; padding: 0.375rem 0.75rem !important; }
.select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered { line-height: 1.5 !important; }
.select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow { height: 100% !important; top: 0 !important; }
.select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered { color: #495057; }
.select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field { border-radius: 0.25rem; }
</style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-pencil-square"></i> Form Edit Usulan Perubahan (Plant 1)</h3>
    <a href="usulan_perubahan.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left-circle"></i> Kembali
    </a>
  </div>

  <form method="POST" action="update_usulan.php">
    <input type="hidden" name="kode_usul_edit" value="<?= htmlspecialchars($data['KODE_USUL']) ?>">

    <div class="card p-4 mb-3">
      <div class="row g-3">

        <div class="col-md-8">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Kode Usulan</label>
              <input type="text" name="kode_usul" class="form-control" value="<?= htmlspecialchars($data['KODE_USUL']) ?>" readonly style="background-color: #e9ecef;">
            </div>
            <div class="col-md-6">
              <label class="form-label">Issue Date</label>
              <input type="date" name="issue_date" class="form-control" value="<?= $data['ISSUE_DATE'] ? $data['ISSUE_DATE']->format('Y-m-d') : '' ?>">
            </div>
            <div class="col-md-12">
              <label class="form-label">Customer</label>
              <select name="cust_id" class="form-select select2-static" required>
                <option value="">-- Pilih Customer --</option>
                <?php foreach ($custList as $c): ?>
                  <option value="<?= $c['CUST_ID'] ?>" <?= ($c['CUST_ID'] == $data['CUST_ID']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['CUST_COMP']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Part No</label>
              <select name="item_id" id="item_id_ajax" class="form-select">
                <?php
                if (!empty($data['ITEM_ID'])) {
                    echo "<option value='{$data['ITEM_ID']}' selected>" . htmlspecialchars($data['ITEM_NO']) . "</option>";
                } else {
                    echo "<option value=''>-- Ketik untuk mencari Part No --</option>";
                }
                ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Part Name</label>
              <input type="text" id="part_name" name="part_name" class="form-control" value="<?= htmlspecialchars($data['ITEM_NAME']) ?>" readonly style="background-color: #e9ecef;">
            </div>
            <div class="col-md-6">
              <label class="form-label">Request By</label>
              <select name="pj" class="form-select select2-static">
                <option value="">-- Pilih --</option>
                <?php foreach ($requestList as $r): ?>
                  <option value="<?= $r ?>" <?= ($r == $data['PJ']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($r) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Tipe Usulan</label>
              <div class="input-group">
                <div class="form-check form-check-inline pt-2">
                  <input class="form-check-input" type="radio" name="tipe_usulan" id="tipe_baru" value="BARU" <?= ($data['BARU']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="tipe_baru">Baru</label>
                </div>
                <div class="form-check form-check-inline pt-2">
                  <input class="form-check-input" type="radio" name="tipe_usulan" id="tipe_revisi" value="REVISI" <?= ($data['REVISI']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="tipe_revisi">Revisi</Labe>
                </div>
                <input type="number" name="revisi_1" class="form-control form-control-sm" placeholder="No. Revisi" style="max-width: 100px;" value="<?= htmlspecialchars($data['REVISI_1']) ?>">
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Document Name</label>
              <input type="text" name="judul_dok" class="form-control" value="<?= htmlspecialchars($data['JUDUL_DOK']) ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Isi Revisi</label>
              <textarea name="isi_revisi" class="form-control" rows="3"><?= htmlspecialchars($data['ISI_REVISI']) ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Alasan Revisi</label>
              <textarea name="alasan_revisi" class="form-control" rows="3"><?= htmlspecialchars($data['ALASAN_REVISI']) ?></textarea>
            </div>
          </div>
        </div>

        <!-- INFO KANAN -->
        <div class="col-md-4">
          
          <div class="card card-info p-3 mb-3">
            <h5 class="mb-3">Dokumen Terkait</h5>
            <div class="row">
              <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_std" value="1" id="is_std" <?= ($data['IS_STD']) ? 'checked' : '' ?>><label class="form-check-label" for="is_std">Inspection STD</label></div></div>
              <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="fmea" value="1" id="fmea" <?= ($data['FMEA']) ? 'checked' : '' ?>><label class="form-check-label" for="fmea">FMEA</label></div></div>
              <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="wi" value="1" id="wi" <?= ($data['WI']) ? 'checked' : '' ?>><label class="form-check-label" for="wi">Work Instruction</label></div></div>
              <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="qcpc" value="1" id="qcpc" <?= ($data['QCPC']) ? 'checked' : '' ?>><label class="form-check-label" for="qcpc">QCPC</label></div></div>
              <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="check_point" value="1" id="check_point" <?= ($data['CHECK_POINT']) ? 'checked' : '' ?>><label class="form-check-label" for="check_point">Check Point</label></div></div>
              <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="setting_par" value="1" id="setting_par" <?= ($data['SETTING_PAR']) ? 'checked' : '' ?>><label class="form-check-label" for="setting_par">Setting Parameter</label></div></div>
              <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="std_pack" value="1" id="std_pack" <?= ($data['STD_PACK']) ? 'checked' : '' ?>><label class="form-check-label" for="std_pack">Packing STD</label></div></div>
              <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="other" value="1" id="other" <?= ($data['OTHER']) ? 'checked' : '' ?>><label class="form-check-label" for="other">Other</label></div></div>
            </div>
          </div>

          <div class="card card-info-2 p-3">
            <h5 class="mb-3">Tanggal & Remark</h5>
            <div class="mb-2">
              <label class="form-label">Plan Date</label>
              <input type="date" name="plant_date" class="form-control" value="<?= $data['PLANT_DATE'] ? $data['PLANT_DATE']->format('Y-m-d') : '' ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">Target Date</label>
              <input type="date" name="target_date" class="form-control" value="<?= $data['TARGET_DATE'] ? $data['TARGET_DATE']->format('Y-m-d') : '' ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">Remark</label>
              <select name="remarks" class="form-select select2-static">
                <option value="">-- Pilih Remark --</option>
                <?php foreach ($remarkList as $r): ?>
                  <option value="<?= $r ?>" <?= ($r == $data['REMARKS']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($r) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

        </div>

      </div>
    </div>

    <div class="text-center mb-4">
      <button type="submit" class="btn btn-primary px-5 btn-lg">
        <i class="bi bi-save"></i> Simpan Perubahan
      </button>
    </div>

  </form>
</div>

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
      if (data.loading) { return data.text; }
      var $container = $("<div class='select2-result-repository clearfix'><div class='select2-result-repository__title'></div></div>");
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
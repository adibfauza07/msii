<?php
require_once '../config/database.php';

if (!isset($_GET['code'])) {
  die("Kode trial tidak ditemukan!");
}

$code = $_GET['code'];

// === Ambil data lama ===
$sql = "SELECT * FROM TRIAL_PE WHERE TRIAL_CODE = ?";
$res = sqlsrv_query($conn, $sql, [$code]);
$data = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
if (!$data) die("Data trial tidak ditemukan!");

// === Dropdown Jenis & Judge ===
$jenis_list = [];
$resJenis = sqlsrv_query($conn, "SELECT ID, JENIS_TRIAL FROM TRIAL_PE_JENIS ORDER BY JENIS_TRIAL");
if ($resJenis) while ($r = sqlsrv_fetch_array($resJenis, SQLSRV_FETCH_ASSOC)) $jenis_list[] = $r;

$judge_list = [];
$resJudge = sqlsrv_query($conn, "SELECT ID, JUDGE_TRIAL FROM JUDGE_TRIAL ORDER BY JUDGE_TRIAL");
if ($resJudge) while ($r = sqlsrv_fetch_array($resJudge, SQLSRV_FETCH_ASSOC)) $judge_list[] = $r;

// Utility biar aman di PHP 5.x
function postv($k, $d = '') { return isset($_POST[$k]) ? $_POST[$k] : $d; }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $foto_name = isset($data['foto']) ? $data['foto'] : null;
  if (!empty($_FILES['foto']['name'])) {
    $targetDir = "../assets/foto_trial/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    $base = preg_replace('/[^A-Za-z0-9_\.-]/', '_', basename($_FILES['foto']['name']));
    $foto_name = time() . "_" . $base;
    $targetFile = $targetDir . $foto_name;

    if (!empty($data['foto']) && file_exists($targetDir . $data['foto'])) @unlink($targetDir . $data['foto']);
    move_uploaded_file($_FILES['foto']['tmp_name'], $targetFile);
  }

  // === Update data lengkap ===
  $sql_update = "UPDATE TRIAL_PE SET 
    DATE=?, PART_CODE=?, CUST_ID=?, QUANTITY_TRIAL=?, TRIAL_REASON=?, TRIAL_TIMES=?, 
    MAT_USING=?, MAT_DRYING_TIME=?, MOLD_SET_UP=?, MOLD_SET_DOWN=?, TRIAL_DURATION=?, 
    QE_COMMENT=?, PE_COMMENT=?, JUDGE_ID=?, PIC=?, WEIGHT_RUNNER=?, PREPARED=?, CHECKED=?, 
    APPROVED=?, QTY_OK=?, QTY_NG=?, CYCLE_TIME_ACT=?, MAC_NO=?, JENIS_ID=?, TONAGE=?, 
    CORRECTIVE_ACTION=?, ANALYSYS=?, foto=? WHERE TRIAL_CODE=?";

  $params = [
    postv('DATE'), postv('PART_CODE'), postv('CUST_ID'), postv('QUANTITY_TRIAL'), postv('TRIAL_REASON'),
    postv('TRIAL_TIMES'), postv('MAT_USING'), postv('MAT_DRYING_TIME'), postv('MOLD_SET_UP'), postv('MOLD_SET_DOWN'),
    postv('TRIAL_DURATION'), postv('QE_COMMENT'), postv('PE_COMMENT'), postv('JUDGE_ID'), postv('PIC'),
    postv('WEIGHT_RUNNER'), postv('PREPARED'), postv('CHECKED'), postv('APPROVED'), postv('QTY_OK'),
    postv('QTY_NG'), postv('CYCLE_TIME_ACT'), postv('MAC_NO'), postv('JENIS_ID'), postv('TONAGE'),
    postv('CORRECTIVE_ACTION'), postv('ANALYSYS'), $foto_name, $code
  ];

  $stmt = sqlsrv_query($conn, $sql_update, $params);
  if ($stmt) {
    echo "<script>alert('Data trial berhasil diperbarui!'); window.location='dashboard_pe.php';</script>";
    exit;
  } else {
    echo "<pre>Gagal update data:\n" . print_r(sqlsrv_errors(), true) . "</pre>";
  }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Edit Data Trial PE</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="../assets/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body { background:#f7f9fc; font-family:"Segoe UI",Arial,sans-serif; }
h3 { color:#4a3ce5; font-weight:700; }
.card { border:none; border-radius:1rem; box-shadow:0 3px 10px rgba(0,0,0,0.1);}
label { font-weight:500; }
textarea { resize:vertical; min-height:60px; }
.img-preview { max-width:200px; border-radius:10px; margin-top:5px; border:1px solid #ddd; }
</style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-pencil-square"></i> Edit Data Trial Produk (PE)</h3>
    <a href="dashboard_pe.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
  </div>

  <form method="POST" enctype="multipart/form-data" class="card p-4">
    <div class="row g-3">

      <div class="col-md-3">
        <label>Kode Trial</label>
        <input type="text" class="form-control" readonly value="<?= htmlspecialchars($data['TRIAL_CODE']); ?>">
      </div>

      <div class="col-md-3">
        <label>Tanggal</label>
        <input type="date" name="DATE" class="form-control" value="<?= $data['DATE'] ? date_format($data['DATE'], 'Y-m-d') : ''; ?>">
      </div>

      <div class="col-md-3">
        <label>Kode Part</label>
        <input type="text" name="PART_CODE" class="form-control" value="<?= htmlspecialchars($data['PART_CODE']); ?>">
      </div>

      <div class="col-md-3">
        <label>Customer ID</label>
        <input type="text" name="CUST_ID" class="form-control" value="<?= htmlspecialchars($data['CUST_ID']); ?>">
      </div>

      <div class="col-md-3">
        <label>Qty Trial</label>
        <input type="number" name="QUANTITY_TRIAL" class="form-control" value="<?= htmlspecialchars($data['QUANTITY_TRIAL']); ?>">
      </div>

      <div class="col-md-3">
        <label>Alasan Trial</label>
        <input type="text" name="TRIAL_REASON" class="form-control" value="<?= htmlspecialchars($data['TRIAL_REASON']); ?>">
      </div>

      <div class="col-md-3">
        <label>Trial Ke</label>
        <input type="number" name="TRIAL_TIMES" class="form-control" value="<?= htmlspecialchars($data['TRIAL_TIMES']); ?>">
      </div>

      <div class="col-md-3">
        <label>Material</label>
        <input type="text" name="MAT_USING" class="form-control" value="<?= htmlspecialchars($data['MAT_USING']); ?>">
      </div>

      <div class="col-md-3">
        <label>Drying Time (menit)</label>
        <input type="number" name="MAT_DRYING_TIME" class="form-control" value="<?= htmlspecialchars($data['MAT_DRYING_TIME']); ?>">
      </div>

      <div class="col-md-3">
        <label>Mold Set Up (menit)</label>
        <input type="number" name="MOLD_SET_UP" class="form-control" value="<?= htmlspecialchars($data['MOLD_SET_UP']); ?>">
      </div>

      <div class="col-md-3">
        <label>Mold Set Down (menit)</label>
        <input type="number" name="MOLD_SET_DOWN" class="form-control" value="<?= htmlspecialchars($data['MOLD_SET_DOWN']); ?>">
      </div>

      <div class="col-md-3">
        <label>Durasi Trial (menit)</label>
        <input type="number" name="TRIAL_DURATION" class="form-control" value="<?= htmlspecialchars($data['TRIAL_DURATION']); ?>">
      </div>

      <div class="col-md-6">
        <label>QE Comment</label>
        <textarea name="QE_COMMENT" class="form-control"><?= htmlspecialchars($data['QE_COMMENT']); ?></textarea>
      </div>

      <div class="col-md-6">
        <label>PE Comment</label>
        <textarea name="PE_COMMENT" class="form-control"><?= htmlspecialchars($data['PE_COMMENT']); ?></textarea>
      </div>

      <div class="col-md-3">
        <label>PIC</label>
        <input type="text" name="PIC" class="form-control" value="<?= htmlspecialchars($data['PIC']); ?>">
      </div>

      <div class="col-md-3">
        <label>Weight Runner</label>
        <input type="text" name="WEIGHT_RUNNER" class="form-control" value="<?= htmlspecialchars($data['WEIGHT_RUNNER']); ?>">
      </div>

      <div class="col-md-3">
        <label>Prepared By</label>
        <input type="text" name="PREPARED" class="form-control" value="<?= htmlspecialchars($data['PREPARED']); ?>">
      </div>

      <div class="col-md-3">
        <label>Checked By</label>
        <input type="text" name="CHECKED" class="form-control" value="<?= htmlspecialchars($data['CHECKED']); ?>">
      </div>

      <div class="col-md-3">
        <label>Approved By</label>
        <input type="text" name="APPROVED" class="form-control" value="<?= htmlspecialchars($data['APPROVED']); ?>">
      </div>

      <div class="col-md-3">
        <label>Qty OK</label>
        <input type="number" name="QTY_OK" class="form-control" value="<?= htmlspecialchars($data['QTY_OK']); ?>">
      </div>

      <div class="col-md-3">
        <label>Qty NG</label>
        <input type="number" name="QTY_NG" class="form-control" value="<?= htmlspecialchars($data['QTY_NG']); ?>">
      </div>

      <div class="col-md-3">
        <label>Cycle Time Actual</label>
        <input type="text" name="CYCLE_TIME_ACT" class="form-control" value="<?= htmlspecialchars($data['CYCLE_TIME_ACT']); ?>">
      </div>

      <div class="col-md-3">
        <label>No Mesin</label>
        <input type="text" name="MAC_NO" class="form-control" value="<?= htmlspecialchars($data['MAC_NO']); ?>">
      </div>

      <div class="col-md-3">
        <label>Jenis Trial</label>
        <select name="JENIS_ID" class="form-select">
          <option value="">-- Pilih Jenis --</option>
          <?php foreach ($jenis_list as $j): ?>
            <option value="<?= $j['ID']; ?>" <?= ($data['JENIS_ID']==$j['ID'])?'selected':''; ?>><?= $j['JENIS_TRIAL']; ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label>Tonnage</label>
        <input type="text" name="TONAGE" class="form-control" value="<?= htmlspecialchars($data['TONAGE']); ?>">
      </div>

      <div class="col-md-6">
        <label>Corrective Action</label>
        <textarea name="CORRECTIVE_ACTION" class="form-control"><?= htmlspecialchars($data['CORRECTIVE_ACTION']); ?></textarea>
      </div>

      <div class="col-md-6">
        <label>Analisis</label>
        <textarea name="ANALYSYS" class="form-control"><?= htmlspecialchars($data['ANALYSYS']); ?></textarea>
      </div>

      <div class="col-md-6">
        <label>Judge</label>
        <select name="JUDGE_ID" class="form-select">
          <option value="">-- Pilih Hasil --</option>
          <?php foreach ($judge_list as $j): ?>
            <?php
              $color = "black";
              if (stripos($j['JUDGE_TRIAL'], "OK") !== false) $color = "green";
              elseif (stripos($j['JUDGE_TRIAL'], "NG") !== false) $color = "red";
              elseif (stripos($j['JUDGE_TRIAL'], "RE") !== false) $color = "orange";
            ?>
            <option value="<?= $j['ID']; ?>" <?= ($data['JUDGE_ID']==$j['ID'])?'selected':''; ?> style="color:<?= $color ?>;font-weight:bold;">
              <?= htmlspecialchars($j['JUDGE_TRIAL']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label>Upload Foto (opsional)</label>
        <input type="file" name="foto" class="form-control" accept="image/*">
        <?php if (!empty($data['foto'])): ?>
          <img src="../assets/foto_trial/<?= htmlspecialchars($data['foto']); ?>" class="img-preview">
        <?php endif; ?>
      </div>

    </div>
    <div class="text-center mt-4">
      <button type="submit" class="btn btn-success px-5"><i class="bi bi-save"></i> Simpan Perubahan</button>
    </div>
  </form>
</div>
</body>
</html>

<?php
require_once '../config/database.php';

/* ===========================================================
   HAPUS DATA TRIAL + HAPUS ACT
   =========================================================== */
if (isset($_GET['delete'])) {

    $del = intval($_GET['delete']);

    // Hapus ACT (Actual Weight) dulu (foreign key)
    $sqlAct = "DELETE FROM TRIAL_PE_WPart_ACT WHERE TRIAL_CODE = ?";
    sqlsrv_query($conn, $sqlAct, array($del));

    // Hapus foto kalau ada
    $sqlFoto = "SELECT foto FROM TRIAL_PE WHERE TRIAL_CODE = ?";
    $resFoto = sqlsrv_query($conn, $sqlFoto, array($del));
    if ($resFoto && ($r = sqlsrv_fetch_array($resFoto, SQLSRV_FETCH_ASSOC))) {
        if (!empty($r['foto'])) {
            $path = "../assets/foto_trial/" . $r['foto'];
            if (file_exists($path)) unlink($path);
        }
    }

    // Hapus main data
    $sqlMain = "DELETE FROM TRIAL_PE WHERE TRIAL_CODE = ?";
    $stmtDel = sqlsrv_query($conn, $sqlMain, array($del));

    if ($stmtDel) {
        echo "<script>alert('Data trial berhasil dihapus!'); window.location='dashboard_pe.php';</script>";
        exit;
    } else {
        echo "<pre>Gagal hapus data:\n" . print_r(sqlsrv_errors(), true) . "</pre>";
        exit;
    }
}


// === Ambil daftar customer untuk dropdown ===
$custList = [];
$sqlCust = "SELECT DISTINCT CUST_ID, CUST_COMP FROM CUST ORDER BY CUST_COMP";
$resCust = sqlsrv_query($conn, $sqlCust);
if ($resCust) {
    while ($r = sqlsrv_fetch_array($resCust, SQLSRV_FETCH_ASSOC)) {
        $custList[] = $r;
    }
}

// === Parameter filter ===
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$cust_id    = isset($_GET['cust_id']) ? $_GET['cust_id'] : '';

// === Query utama ===
$sql = "
SELECT 
    T.TRIAL_CODE, T.DATE, T.PART_CODE, T.CUST_ID, T.QUANTITY_TRIAL, T.TRIAL_REASON, T.TRIAL_TIMES, 
    T.MAT_USING, T.MAT_DRYING_TIME, T.MOLD_SET_UP, T.MOLD_SET_DOWN, T.TRIAL_DURATION, 
    T.QE_COMMENT, T.PE_COMMENT, T.JUDGE_ID, T.PIC, T.WEIGHT_RUNNER, T.PREPARED, T.CHECKED, T.APPROVED,
    T.QTY_OK, T.QTY_NG, T.CYCLE_TIME_ACT, T.MAC_NO, T.JENIS_ID, T.TONAGE, 
    T.CORRECTIVE_ACTION, T.ANALYSYS, T.foto,
    C.CUST_COMP, J.JENIS_TRIAL, JD.JUDGE_TRIAL
FROM TRIAL_PE T
LEFT JOIN CUST C ON C.CUST_ID = T.CUST_ID
LEFT JOIN TRIAL_PE_JENIS J ON J.ID = T.JENIS_ID
LEFT JOIN JUDGE_TRIAL JD ON JD.ID = T.JUDGE_ID
WHERE T.DATE BETWEEN ? AND ?";
$params = [$start_date, $end_date];

if (!empty($cust_id)) {
    $sql .= " AND T.CUST_ID = ?";
    $params[] = $cust_id;
}
$sql .= " ORDER BY T.DATE DESC";

$stmt = sqlsrv_query($conn, $sql, $params);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard Product Engineering</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="../assets/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body { background-color: #f7f9fc; font-family: "Segoe UI", Arial, sans-serif; }
h3 { color: #4a3ce5; font-weight: 700; }
.table thead { background: #4a3ce5; color: white; }
.table-hover tbody tr:hover { background-color: #eef1ff; }
.card { border: none; border-radius: 1rem; box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
</style>
<script>
function confirmDelete(code) {
  if (confirm("Yakin ingin menghapus data trial: " + code + " ?")) {
    window.location = "dashboard_pe.php?delete=" + code;
  }
}
</script>
</head>
<body>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-kanban"></i> Dashboard Product Engineering</h3>
    <div>
      <a href="input_trial_pe.php" class="btn btn-primary me-2">
        <i class="bi bi-plus-circle"></i> Tambah Trial Baru
      </a>
      <a href="export_trial_pe.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&cust_id=<?= $cust_id ?>" class="btn btn-success">
        <i class="bi bi-file-earmark-excel"></i> Export Excel
      </a>
    </div>
  </div>

  <!-- Filter -->
  <div class="card p-3 mb-4">
    <form class="row g-3 align-items-end" method="get">
      <div class="col-md-3">
        <label class="form-label">Dari Tanggal</label>
        <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Sampai Tanggal</label>
        <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Customer</label>
        <select name="cust_id" class="form-select">
          <option value="">-- Semua Customer --</option>
          <?php foreach ($custList as $c): ?>
            <option value="<?= $c['CUST_ID'] ?>" <?= ($c['CUST_ID']==$cust_id)?'selected':'' ?>>
              <?= htmlspecialchars($c['CUST_COMP']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-success w-100">
          <i class="bi bi-search"></i> Filter
        </button>
      </div>
      <div class="col-md-1">
        <a href="dashboard_pe.php" class="btn btn-secondary w-100"><i class="bi bi-arrow-repeat"></i></a>
      </div>
    </form>
  </div>

  <!-- Tabel -->
  <div class="card p-4">
    <div class="table-responsive">
      <table class="table table-bordered table-hover table-striped align-middle small">
        <thead class="text-center">
          <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>Kode Trial</th>
            <th>Part Code</th>
            <th>Customer</th>
            <th>Qty</th>
            <th>Trial Reason</th>
            <th>Trial Ke</th>
            <th>Material</th>
            <th>Drying Time</th>
            <th>Mold Setup</th>
            <th>Mold Down</th>
            <th>Duration</th>
            <th>QE Comment</th>
            <th>PE Comment</th>
            <th>Judge</th>
            <th>PIC</th>
            <th>Runner</th>
            <th>Prepared</th>
            <th>Checked</th>
            <th>Approved</th>
            <th>Qty OK</th>
            <th>Qty NG</th>
            <th>Cycle Time</th>
            <th>No Mesin</th>
            <th>Jenis Trial</th>
            <th>Tonnage</th>
            <th>Corrective Action</th>
            <th>Analisis</th>
            <th>Foto</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $no = 1;
          if ($stmt && sqlsrv_has_rows($stmt)) {
              while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                  $fotoPath = !empty($row['foto']) ? "../assets/foto_trial/" . $row['foto'] : "";
                  echo "<tr>";
                  echo "<td class='text-center'>{$no}</td>";
                  echo "<td>" . ($row['DATE'] ? date_format($row['DATE'], 'Y-m-d') : '') . "</td>";
                  echo "<td>{$row['TRIAL_CODE']}</td>";
                  echo "<td>{$row['PART_CODE']}</td>";
                  echo "<td>{$row['CUST_COMP']}</td>";
                  echo "<td>{$row['QUANTITY_TRIAL']}</td>";
                  echo "<td>{$row['TRIAL_REASON']}</td>";
                  echo "<td>{$row['TRIAL_TIMES']}</td>";
                  echo "<td>{$row['MAT_USING']}</td>";
                  echo "<td>{$row['MAT_DRYING_TIME']}</td>";
                  echo "<td>{$row['MOLD_SET_UP']}</td>";
                  echo "<td>{$row['MOLD_SET_DOWN']}</td>";
                  echo "<td>{$row['TRIAL_DURATION']}</td>";
                  echo "<td>{$row['QE_COMMENT']}</td>";
                  echo "<td>{$row['PE_COMMENT']}</td>";
                  echo "<td>{$row['JUDGE_TRIAL']}</td>";
                  echo "<td>{$row['PIC']}</td>";
                  echo "<td>{$row['WEIGHT_RUNNER']}</td>";
                  echo "<td>{$row['PREPARED']}</td>";
                  echo "<td>{$row['CHECKED']}</td>";
                  echo "<td>{$row['APPROVED']}</td>";
                  echo "<td>{$row['QTY_OK']}</td>";
                  echo "<td>{$row['QTY_NG']}</td>";
                  echo "<td>{$row['CYCLE_TIME_ACT']}</td>";
                  echo "<td>{$row['MAC_NO']}</td>";
                  echo "<td>{$row['JENIS_TRIAL']}</td>";
                  echo "<td>{$row['TONAGE']}</td>";
                  echo "<td>{$row['CORRECTIVE_ACTION']}</td>";
                  echo "<td>{$row['ANALYSYS']}</td>";

                  if ($fotoPath && file_exists($fotoPath)) {
                      echo "<td class='text-center'><a href='{$fotoPath}' target='_blank'><img src='{$fotoPath}' width='60' height='60' class='rounded shadow-sm'></a></td>";
                  } else {
                      echo "<td class='text-center text-muted'>-</td>";
                  }

                  echo "<td class='text-center'>
                          <a href='edit_trial_pe.php?code={$row['TRIAL_CODE']}' class='btn btn-warning btn-sm me-1'><i class='bi bi-pencil'></i></a>
                          <a href='report_trial_pe_pdf.php?code={$row['TRIAL_CODE']}' target='_blank' class='btn btn-danger btn-sm me-1'><i class='bi bi-file-earmark-pdf'></i></a>
                          <button class='btn btn-danger btn-sm' onclick=\"confirmDelete('{$row['TRIAL_CODE']}')\"><i class='bi bi-trash'></i></button>
                        </td>";
                  echo "</tr>";
                  $no++;
              }
          } else {
              echo "<tr><td colspan='30' class='text-center text-danger'>Tidak ada data trial untuk filter ini</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>

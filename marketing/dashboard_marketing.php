<?php
require_once '../config/database.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Marketing</title>
  <link href="../assets/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4">
  <h3 class="fw-bold text-primary mb-3">Dashboard Marketing</h3>
  <a href="../index.php" class="btn btn-outline-secondary btn-sm mb-3">← Menu Utama</a>
  <hr>

  <?php
  $sql = "SELECT COUNT(*) AS total_leads FROM leads";
  $res = sqlsrv_query($conn, $sql);
  $total = 0;
  if ($res && ($r = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC))) {
      $total = $r['total_leads'];
  }
  ?>
  <div class="alert alert-primary text-center fs-5">Total Leads: <?php echo $total; ?></div>
</div>

</body>
</html>

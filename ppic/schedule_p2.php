<?php
require_once "../config/database.php";

// ---------- PARAMETER ----------
if (isset($_POST['start']) && $_POST['start'] != '') {
    $start = $_POST['start'];
} else {
    $start = date("Y-m-01");
}

if (isset($_POST['end']) && $_POST['end'] != '') {
    $end = $_POST['end'];
} else {
    $end = date("Y-m-d");
}

if (isset($_POST['cust']) && $_POST['cust'] != '') {
    $cust_code = $_POST['cust'];
} else {
    $cust_code = NULL;
}

$start8 = str_replace("-", "", $start);
$end8   = str_replace("-", "", $end);

// -------- LIST CUSTOMER ----------
$sqlCust = "SELECT CUST_ID, CUST_CODE, CUST_COMP FROM CUST ORDER BY CUST_CODE";
$custList = sqlsrv_query($conn, $sqlCust);
?>
<!DOCTYPE html>
<html>
<head>
<title>Delivery Schedule Report</title>
<link rel="stylesheet" href="../assets/bootstrap.min.css">
<style>
body { font-size:13px; padding:20px; }
.table th { text-align:center; font-size:11px; }
.table td { text-align:center; font-size:11px; }
.item-title { font-weight:bold; margin-top:25px; }
.dash { border-top:1px dashed #888; margin:20px 0; }
</style>
</head>

<body>

<!-- BACK BUTTON -->
<a href="dashboard_ppic.php" class="btn btn-secondary" style="margin-bottom:15px;">
    ← Kembali ke Menu P2
</a>

<h4><b>Delivery Schedule Report (P2)</b></h4>

<!-- ================= FILTER ================= -->
<form method="POST" class="form-inline" style="margin-bottom:20px;">

    <label><b>Customer:</b>&nbsp;</label>
    <select name="cust" class="form-control" required>
        <option value="">-- pilih customer --</option>
        <?php
        while ($c = sqlsrv_fetch_array($custList, SQLSRV_FETCH_ASSOC)) {
            $selected = ($cust_code == $c['CUST_CODE']) ? "selected" : "";
            echo "<option value='".$c['CUST_CODE']."' $selected>".
                 $c['CUST_CODE']." - ".$c['CUST_COMP'].
                 "</option>";
        }
        ?>
    </select>

    &nbsp;&nbsp;
    <label><b>Start:</b>&nbsp;</label>
    <input type="date" name="start" class="form-control" value="<?php echo $start; ?>" required>

    &nbsp;&nbsp;
    <label><b>End:</b>&nbsp;</label>
    <input type="date" name="end" class="form-control" value="<?php echo $end; ?>" required>

    &nbsp;&nbsp;
    <button type="submit" class="btn btn-primary">Tampilkan</button>

    &nbsp;&nbsp;
    <?php if(isset($_POST['cust'])) { ?>
        <a href="schedule_p2_excel.php?start=<?php echo $start; ?>&end=<?php echo $end; ?>&cust=<?php echo $cust_code; ?>"
           class="btn btn-success">Export Excel</a>
    <?php } ?>

</form>

<?php
if (!isset($_POST['cust'])) exit;

// ================= CALL SP =================
$sql = "{CALL sp_PivotDeliverySchedule_ByCustomer(?,?,?)}";
$params = array($start8, $end8, $cust_code);
$stmt = sqlsrv_query($conn, $sql, $params);

$data = array();
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $key = $r["CUST_CODE"]."|".$r["ITEM_CODE"];
    $data[$key] = $r;
}
?>

<h5>
Hasil: <b><?php echo $cust_code; ?></b> |
Periode <b><?php echo $start; ?></b> s/d <b><?php echo $end; ?></b>
</h5>
<hr>

<?php foreach ($data as $key => $h) { ?>

<div class="item-title">
    <?php echo $h['CUST_CODE']." - ".$h['CUST_COMP']; ?><br>
    <?php echo $h['ITEM_CODE']." - ".$h['ITEM_NAME']; ?>
</div>

<table class="table table-bordered table-sm">
<thead>
<tr>
    <th></th>
    <?php
    for ($i=1; $i<=31; $i++) {
        echo "<th>".str_pad($i,2,'0',STR_PAD_LEFT)."</th>";
    }
    ?>
</tr>
</thead>

<tbody>

<!-- PLAN -->
<tr>
    <td>Pla</td>
    <?php
    for ($i=1; $i<=31; $i++) {
        $col = $i."_SCH";
        $val = isset($h[$col]) ? round($h[$col]) : 0;
        echo "<td>".($val==0? "-" : $val)."</td>";
    }
    ?>
</tr>

<!-- ACTUAL -->
<tr>
    <td>Act</td>
    <?php
    for ($i=1; $i<=31; $i++) {
        $col = $i."_DEL";
        $val = isset($h[$col]) ? round($h[$col]) : 0;
        echo "<td>".($val==0? "-" : $val)."</td>";
    }
    ?>
</tr>

<!-- BALANCE -->
<tr>
    <td>Bal</td>
    <?php
    for ($i=1; $i<=31; $i++) {
        $col = $i."_BAL";
        $val = isset($h[$col]) ? round($h[$col]) : 0;
        echo "<td>".($val==0? "-" : $val)."</td>";
    }
    ?>
</tr>

</tbody>
</table>

<div class="dash"></div>

<?php } ?>

</body>
</html>

<?php
require "../config/database.php";

// Ambil parameter
$from  = isset($_GET['from']) ? $_GET['from'] : "";
$to    = isset($_GET['to']) ? $_GET['to'] : "";
$mac   = isset($_GET['mac']) ? $_GET['mac'] : "";
$plant = isset($_GET['plant']) ? $_GET['plant'] : "";

// Query SP
$sql = "{CALL sp_daily_mtn_web(?, ?, ?, ?)}";
$params = array($from, $to, $mac, $plant);
$stmt = sqlsrv_query($conn, $sql, $params);

?>
<!DOCTYPE html>
<html>
<head>
<title>Daily MTN Report</title>

<style>
body{
    font-family: Arial, Helvetica, sans-serif;
    padding:20px;
    margin:0;
}

h2{
    text-align:center;
    margin-bottom:10px;
}

/* PRINT MODE LANDSCAPE */
@media print {
    @page {
        size: landscape;
        margin: 10mm;
    }
    #btnPrint { display:none; }
}

.print-info td {
    border:none;
    padding:4px 0;
    font-size:13px;
}

.table-header th {
    background:#eee;
    text-align:center;
    border:1px solid #000;
    padding:5px;
    font-size:12px;
}

td {
    border:1px solid #000;
    padding:5px;
    font-size:12px;
}

table {
    border-collapse: collapse;
    width:100%;
    margin-top:20px;
}

#btnPrint {
    padding:8px 16px;
    margin-bottom:20px;
    background:#28a745;
    color:white;
    border:none;
    cursor:pointer;
    border-radius:4px;
}

.doc-code {
    position: fixed;
    bottom: 15px;
    left: 15px;
    font-size: 11px;
    font-weight: bold;
    color: #333;
}
</style>

</head>
<body>

<button id="btnPrint" onclick="window.print()">🖨 Cetak</button>

<h2>DAILY MTN REPORT</h2>

<table class="print-info">
<tr>
    <td width="150"><b>Dari Tanggal</b></td>
    <td>: <?= htmlspecialchars($from) ?></td>
    <td width="150"><b>Sampai Tanggal</b></td>
    <td>: <?= htmlspecialchars($to) ?></td>
</tr>
<tr>
    <td><b>MAC</b></td>
    <td>: <?= htmlspecialchars($mac) ?></td>
    <td><b>Plant</b></td>
    <td>: <?= htmlspecialchars($plant) ?></td>
</tr>
</table>

<table>
<thead>
<tr class="table-header">
    <th width="90">Tanggal</th>
    <th width="50">MAC</th>
    <th width="200">Problem</th>
    <th width="160">Cause</th>
    <th width="80">PIC</th>
    <th width="60">From</th>
    <th width="60">To</th>
    <th width="70">Status</th>
    <th>Remark</th>
</tr>
</thead>

<tbody>
<?php while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
<tr>
    <td><?= $row['DATE'] ? $row['DATE']->format("d-M-Y") : "" ?></td>
    <td><?= htmlspecialchars($row['MAC']) ?></td>
    <td><?= htmlspecialchars($row['PROBLEM']) ?></td>
    <td><?= htmlspecialchars($row['CAUSE']) ?></td>
    <td><?= htmlspecialchars($row['PIC']) ?></td>

    <td>
    <?= ($row['FROM_HOURS'] instanceof DateTime)  
        ? $row['FROM_HOURS']->format("H:i") 
        : "" ?>
    </td>

    <td>
    <?= ($row['TO_HOURS'] instanceof DateTime)  
        ? $row['TO_HOURS']->format("H:i") 
        : "" ?>
    </td>

    <td><?= htmlspecialchars($row['STATUS']) ?></td>
    <td><?= htmlspecialchars($row['DESCRIPTION']) ?></td>
</tr>
<?php endwhile; ?>
</tbody>



    <!-- TAMBAHKAN TFOOT INI SEBAGAI PENGGANJAL -->
    <tfoot>
        <tr>
            <!-- colspan="9" karena tabelmu punya 9 kolom -->
            <td colspan="9" style="border: none; height: 30px;"></td>
        </tr>
    </tfoot>



<!-- Elemen kode form di pojok kiri bawah -->
<div class="doc-code">FM.MTN.S01-43</div>

</table>





</body>
</html>

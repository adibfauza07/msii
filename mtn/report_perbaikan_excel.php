<?php
// FILE: mtn/report_perbaikan_excel.php

require "../config/database.php";

// 1. Ambil Parameter
$from  = isset($_GET['from'])  ? $_GET['from']  : "";
$to    = isset($_GET['to'])    ? $_GET['to']    : "";
$mac   = isset($_GET['mac'])   ? trim($_GET['mac'])   : "";
$plant = isset($_GET['plant']) ? trim($_GET['plant']) : "";

// Konversi tanggal
$from_sql = $from ? date("Y-m-d", strtotime($from)) : "";
$to_sql   = $to   ? date("Y-m-d", strtotime($to))   : "";
$mac_param = ($mac === "" ? "%" : $mac);
$plant_param = ($plant === "" ? 0 : (int)$plant);

// 2. Query Database
$data = [];
if ($from_sql && $to_sql) {
    $sql    = "{CALL SP_MTN_HISTORY_PERBAIKAN_new(?, ?, ?, ?)}";
    $params = array($from_sql, $to_sql, $mac_param, $plant_param);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt !== false) {
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
    }
}

// 3. HEADER EXCEL (Memicu Download)
// FIX: Menggunakan kurung kurawal {} agar PHP bisa membedakan variabel dan teks
$filename = "Laporan_Perbaikan_{$from_sql}_sd_{$to_sql}.xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// 4. Output Data dalam Format HTML Table (Excel bisa baca ini)
?>
<table border="1">
    <thead>
        <tr style="background-color:#ccc;">
            <th>MAC</th>
            <th>Plant</th>
            <th>Tonage</th>
            <th>Tanggal</th>
            <th>Description</th>
            <th>Service</th>
            <th>Kerusakan</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($data as $r): ?>
        <tr>
            <td style="mso-number-format:'\@';"><?= $r['MAC'] ?></td> 
            <td style="text-align:center;"><?= $r['PLANT'] ?></td>
            <td style="text-align:center;"><?= $r['TONAGE'] ?></td>
            <td><?= $r['DATE'] instanceof DateTime ? $r['DATE']->format("d-M-Y") : "" ?></td>
            <td><?= htmlspecialchars($r['DESCRIPTION']) ?></td>
            <td><?= htmlspecialchars($r['SERVICE']) ?></td>
            <td><?= htmlspecialchars($r['KERUSAKAN']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
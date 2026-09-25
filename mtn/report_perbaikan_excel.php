<?php
require "../config/database.php";

$from  = isset($_GET['from'])  ? $_GET['from']  : "";
$to    = isset($_GET['to'])    ? $_GET['to']    : "";
$mac   = isset($_GET['mac'])   ? trim($_GET['mac'])   : "";
$plant = isset($_GET['plant']) ? trim($_GET['plant']) : "";

$from_sql = $from ? date("Y-m-d", strtotime($from)) : "";
$to_sql   = $to   ? date("Y-m-d", strtotime($to))   : "";
$mac_param = ($mac === "" ? "%" : $mac);
$plant_param = ($plant === "" ? 0 : (int)$plant);

$data = [];
if ($from_sql && $to_sql) {
    $sql    = "{CALL SP_MTN_HISTORY_PERBAIKAN_new(?, ?, ?, ?)}";
    $params = [$from_sql, $to_sql, $mac_param, $plant_param];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt !== false) {
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
    }
}

$filename = "Laporan_Perbaikan_{$from_sql}_sd_{$to_sql}.xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<style>
    .header-table { background-color: #007bff; color: #ffffff; font-weight: bold; text-align: center; }
    .title-row { font-size: 16pt; font-weight: bold; text-align: center; }
    .filter-row { font-size: 11pt; text-align: center; font-style: italic; }
</style>

<table border="1">
    <tr>
        <td colspan="7" class="title-row">LAPORAN PERBAIKAN MESIN</td>
    </tr>
    <tr>
        <td colspan="7" class="filter-row">
            Periode: <?= $from_sql ?> s/d <?= $to_sql ?> | MAC: <?= ($mac === "" ? "Semua" : $mac) ?> | Plant: <?= ($plant === "" ? "Semua" : $plant) ?>
        </td>
    </tr>
    <tr><td colspan="7"></td></tr>
    <thead>
        <tr>
            <th class="header-table">MAC</th>
            <th class="header-table">Plant</th>
            <th class="header-table">Tonage</th>
            <th class="header-table">Tanggal</th>
            <th class="header-table">Description</th>
            <th class="header-table">Service</th>
            <th class="header-table">Kerusakan</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($data as $r): ?>
        <tr>
            <td style="mso-number-format:'\@'; text-align:center;"><?= $r['MAC'] ?></td> 
            <td style="text-align:center;"><?= $r['PLANT'] ?></td>
            <td style="text-align:center;"><?= $r['TONAGE'] ?></td>
            <td style="text-align:center;"><?= $r['DATE'] instanceof DateTime ? $r['DATE']->format("d-M-Y") : "" ?></td>
            <td><?= htmlspecialchars($r['DESCRIPTION']) ?></td>
            <td><?= htmlspecialchars($r['SERVICE']) ?></td>
            <td><?= htmlspecialchars($r['KERUSAKAN']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
require_once "../config/database_ppic.php";
require_once "../vendor/autoload.php";

use Dompdf\Dompdf;

$cust  = $_GET['cust'] ?? '';
$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';

$sql = "{CALL SP_DI_PART_new(?, ?, ?)}";
$params = array($cust, $start, $end);
$stmt = sqlsrv_query($conn, $sql, $params);

$html = "
<h3>DI PART Report</h3>
<p>Customer: <b>{$cust}</b><br>
Periode: <b>{$start}</b> s/d <b>{$end}</b></p>
<table border='1' cellspacing='0' cellpadding='3' width='100%'>
    <tr>
        <th>No</th>
        <th>PART_NUM</th>
        <th>PART_NO</th>
        <th>PART_NAME</th>
        <th>BAL_QTY</th>
        <th>DAILY_SCH</th>
        <th>BALANCE</th>
    </tr>";

$no = 1;
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $html .= "<tr>
        <td>{$no}</td>
        <td>{$r['PART_NUM']}</td>
        <td>{$r['PART_NO']}</td>
        <td>{$r['PART_NAME']}</td>
        <td>{$r['BAL_QTY']}</td>
        <td>{$r['DAILY_SCH']}</td>
        <td>{$r['BALANCE']}</td>
    </tr>";
    $no++;
}

$html .= "</table>";

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = "DI_PART_{$cust}_{$start}_{$end}.pdf";
$dompdf->stream($filename, ["Attachment" => true]);
exit;

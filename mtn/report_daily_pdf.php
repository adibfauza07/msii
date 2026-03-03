<?php
require "../config/database.php";
require "../dompdf/autoload.inc.php";   // pastikan lokasi benar

use Dompdf\Dompdf;

$from  = date("Y-m-d", strtotime($_GET['from']));
$to    = date("Y-m-d", strtotime($_GET['to']));
$mac   = $_GET['mac'] == '' ? 0 : intval($_GET['mac']);
$plant = $_GET['plant'] == '' ? 0 : intval($_GET['plant']);

$sql  = "EXEC sp_daily_mtn_web ?, ?, ?, ?";
$stmt = q($sql, [$from, $to, $mac, $plant]);

$rows = [];
while($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
    $rows[] = $r;
}

$html = "
<h3 style='text-align:center;'>DAILY MTN REPORT</h3>
<p><b>Periode:</b> {$_GET['from']} s/d {$_GET['to']}</p>
<hr>

<table border='1' width='100%' cellspacing='0' cellpadding='4'>
<tr style='background:#ddd; font-weight:bold;'>
    <td>Tanggal</td>
    <td>MAC</td>
    <td>Problem</td>
    <td>Cause</td>
    <td>PIC</td>
    <td>From</td>
    <td>To</td>
    <td>Status</td>
    <td>Remark</td>
</tr>";

foreach($rows as $r){
    $html .= "
    <tr>
        <td>".date("d-M-Y", strtotime($r['DATE']))."</td>
        <td>{$r['MAC']}</td>
        <td>{$r['PROBLEM']}</td>
        <td>{$r['CAUSE']}</td>
        <td>{$r['PIC']}</td>
        <td>".substr($r['FROM_HOURS'],0,5)."</td>
        <td>".substr($r['TO_HOURS'],0,5)."</td>
        <td>{$r['STATUS']}</td>
        <td>{$r['DESCRIPTION']}</td>
    </tr>";
}

$html .= "</table>";

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("Daily-MTN-Report.pdf", ["Attachment" => false]);
?>

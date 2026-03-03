<?php
require "../config/database.php";
require "../assets/tcpdf_min/tcpdf.php";

// ==========================================
// GET PARAMETER
// ==========================================
$from  = isset($_GET['from']) ? $_GET['from'] : "";
$to    = isset($_GET['to'])   ? $_GET['to']   : "";
$mac   = isset($_GET['mac'])  ? $_GET['mac']  : "";
$plant = isset($_GET['plant'])? $_GET['plant']: "";

if ($from == "" || $to == "") {
    die("Parameter tanggal tidak lengkap.");
}

// Convert ke format SQL
$fromSql = date("Y-m-d", strtotime($from));
$toSql   = date("Y-m-d", strtotime($to));

// ==========================================
//  AMBIL DATA DARI STORED PROCEDURE
// ==========================================
$sql = "
EXEC sp_daily_mtn_web 
    @from_date = ?, 
    @end_date  = ?, 
    @mac       = ?, 
    @plan      = ?
";

$param = array($fromSql, $toSql, $mac, $plant);
$rs = q($sql, $param);

// ==========================================
//  INIT PDF (Landscape)
// ==========================================
$pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator("MSII");
$pdf->SetAuthor("MSII System");
$pdf->SetTitle("Daily MTN Report");
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(TRUE, 8);
$pdf->AddPage();

// ==========================================
//  TITLE & FILTER HEADER
// ==========================================
$html = '
<h2 style="text-align:center; margin-bottom:5px;">DAILY MTN REPORT</h2>

<table cellpadding="4" style="font-size:12px; width:100%;">
    <tr>
        <td width="140"><b>Dari Tanggal</b></td>
        <td width="200">: '.$from.'</td>

        <td width="140"><b>Sampai Tanggal</b></td>
        <td width="200">: '.$to.'</td>
    </tr>

    <tr>
        <td><b>MAC</b></td>
        <td>: '.$mac.'</td>

        <td><b>Plant</b></td>
        <td>: '.$plant.'</td>
    </tr>
</table>

<br><br>
';

// ==========================================
//  TABLE HEADER
// ==========================================
$html .= '
<table border="1" cellpadding="4" cellspacing="0" width="100%" style="font-size:10px;">
<thead>
<tr style="background-color:#f2f2f2; font-weight:bold; text-align:center;">
    <th width="70">Tanggal</th>
    <th width="40">MAC</th>
    <th width="160">Problem</th>
    <th width="160">Cause</th>
    <th width="80">PIC</th>
    <th width="55">From</th>
    <th width="55">To</th>
    <th width="60">Status</th>
    <th width="210">Remark</th>
</tr>
</thead>
<tbody>
';

// ==========================================
//  ISI TABLE
// ==========================================
while ($r = sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC)) {

    // Tanggal
    $tgl = "";
    if (!empty($r['DATE']) && $r['DATE'] instanceof DateTime) {
        $tgl = $r['DATE']->format("d-M-Y");
    }

    // Time
    $fromh = ($r['FROM_HOURS'] instanceof DateTime) ? $r['FROM_HOURS']->format("H:i") : "";
    $toh   = ($r['TO_HOURS']   instanceof DateTime) ? $r['TO_HOURS']->format("H:i")   : "";

    $problem = htmlspecialchars($r['PROBLEM']);
    $cause   = htmlspecialchars($r['CAUSE']);
    $desc    = htmlspecialchars($r['DESCRIPTION']);
    $pic     = htmlspecialchars($r['PIC']);
    $status  = htmlspecialchars($r['STATUS']);
    $macVal  = htmlspecialchars($r['MAC']);

    $html .= "
    <tr>
        <td>$tgl</td>
        <td>$macVal</td>
        <td>$problem</td>
        <td>$cause</td>
        <td>$pic</td>
        <td>$fromh</td>
        <td>$toh</td>
        <td>$status</td>
        <td>$desc</td>
    </tr>
    ";
}

$html .= "</tbody></table>";

// ==========================================
//  CETAK PDF
// ==========================================
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output("daily_mtn_report.pdf", "I");
exit;
?>

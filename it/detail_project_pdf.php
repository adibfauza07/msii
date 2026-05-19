<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "auth.php";
require_once "../config/database_p2.php";
require_once "../tcpdf/tcpdf.php";

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("Project tidak valid.");
}

if (function_exists('validateProjectAccess')) {
    validateProjectAccess($conn, $id);
}

/* =========================
   DATA PROJECT
========================= */
$sql = "SELECT *
        FROM dbo.it_projects
        WHERE id = ?";

$q = sqlsrv_query($conn, $sql, array($id));

if ($q === false) {
    die(print_r(sqlsrv_errors(), true));
}

$project = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);

if (!$project) {
    die("Project tidak ditemukan.");
}

/* =========================
   AVG PROGRESS
========================= */
$sql_avg = "SELECT ISNULL(AVG(CAST(progress AS FLOAT)),0) AS avg_progress
            FROM dbo.it_project_progress
            WHERE project_id = ?";

$q_avg = sqlsrv_query($conn, $sql_avg, array($id));

if ($q_avg === false) {
    die(print_r(sqlsrv_errors(), true));
}

$r_avg = sqlsrv_fetch_array($q_avg, SQLSRV_FETCH_ASSOC);

$avg_progress = isset($r_avg['avg_progress'])
    ? round($r_avg['avg_progress'], 0)
    : 0;

/* =========================
   RIWAYAT PROGRESS
========================= */
$sql_progress = "SELECT *
                 FROM dbo.it_project_progress
                 WHERE project_id = ?
                 ORDER BY id DESC";

$q_progress = sqlsrv_query($conn, $sql_progress, array($id));

if ($q_progress === false) {
    die(print_r(sqlsrv_errors(), true));
}

/* =========================
   TOTAL BIAYA PROGRESS
========================= */
$sql_total_progress = "SELECT ISNULL(SUM(biaya),0) AS total
                       FROM dbo.it_project_progress
                       WHERE project_id = ?";

$q_total_progress = sqlsrv_query($conn, $sql_total_progress, array($id));

if ($q_total_progress === false) {
    die(print_r(sqlsrv_errors(), true));
}

$total_progress = sqlsrv_fetch_array($q_total_progress, SQLSRV_FETCH_ASSOC);

/* =========================
   RIWAYAT BIAYA ACTUAL
========================= */
$sql_biaya = "SELECT *
              FROM dbo.it_project_costs
              WHERE project_id = ?
              ORDER BY id DESC";

$q_biaya = sqlsrv_query($conn, $sql_biaya, array($id));

if ($q_biaya === false) {
    die(print_r(sqlsrv_errors(), true));
}

/* =========================
   TOTAL BIAYA ACTUAL
========================= */
$sql_total_biaya = "SELECT ISNULL(SUM(nominal),0) AS total
                    FROM dbo.it_project_costs
                    WHERE project_id = ?";

$q_total_biaya = sqlsrv_query($conn, $sql_total_biaya, array($id));

if ($q_total_biaya === false) {
    die(print_r(sqlsrv_errors(), true));
}

$total_biaya = sqlsrv_fetch_array($q_total_biaya, SQLSRV_FETCH_ASSOC);

/* =========================
   VARIABLE
========================= */
$nama_project = isset($project['nama_software']) ? $project['nama_software'] : '-';
$deskripsi = isset($project['deskripsi']) ? $project['deskripsi'] : '-';
$department = isset($project['department']) ? $project['department'] : '-';
$pic_department = isset($project['pic_department']) ? $project['pic_department'] : '-';
$pic_it = isset($project['pic_it']) ? $project['pic_it'] : '-';
$status_project = isset($project['status']) ? $project['status'] : '-';

$total_biaya_actual = isset($total_biaya['total']) ? $total_biaya['total'] : 0;
$total_biaya_progress = isset($total_progress['total']) ? $total_progress['total'] : 0;

/*
   PERBAIKAN:
   Budget Project sekarang dibuat sama dengan Total Biaya Progress.
*/
$budget_project = $total_biaya_progress;

/*
   Pengajuan = Total Biaya Progress - Total Biaya Actual.
*/
$pengajuan = $total_biaya_progress - $total_biaya_actual;

/*
   Karena Budget Project = Total Biaya Progress,
   maka Sisa Budget hasilnya sama dengan Pengajuan.
*/
$sisa_budget = $budget_project - $total_biaya_actual;

$deadline = "-";

if (!empty($project['deadline'])) {
    $deadline = $project['deadline']->format('d-M-Y');
}

/* =========================
   CREATE PDF PORTRAIT A4
========================= */
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetCreator('IT Project');
$pdf->SetAuthor('IT Project');
$pdf->SetTitle($nama_project);
$pdf->SetSubject('Detail Project');

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(true, 8);
$pdf->AddPage();

$pdf->SetFont('helvetica', '', 8);

/* =========================
   HTML
========================= */
$html = '

<style>
body{
    font-family: helvetica;
    font-size:8px;
}

.judul{
    text-align:center;
    font-size:16px;
    font-weight:bold;
    margin-bottom:3px;
}

.subjudul{
    text-align:center;
    font-size:9px;
    margin-bottom:10px;
    color:#555;
}

.section-title{
    font-size:10px;
    font-weight:bold;
    margin-top:8px;
    margin-bottom:4px;
}

table{
    border-collapse:collapse;
    width:100%;
}

th{
    background-color:#e5e7eb;
    font-weight:bold;
}

th, td{
    border:1px solid #000;
    padding:3px;
    font-size:7.5px;
    vertical-align:top;
}

.text-right{
    text-align:right;
}
</style>

<div class="judul">
    '.htmlspecialchars($nama_project).'
</div>

<div class="subjudul">
    DETAIL PROJECT
</div>

<div class="section-title">Data Project</div>

<table cellpadding="3">
    <tr>
        <th width="30%">Nama Project</th>
        <td width="70%">'.htmlspecialchars($nama_project).'</td>
    </tr>

    <tr>
        <th>Deskripsi</th>
        <td>'.htmlspecialchars($deskripsi).'</td>
    </tr>

    <tr>
        <th>Department</th>
        <td>'.htmlspecialchars($department).'</td>
    </tr>

    <tr>
        <th>PIC Department</th>
        <td>'.htmlspecialchars($pic_department).'</td>
    </tr>

    <tr>
        <th>PIC IT</th>
        <td>'.htmlspecialchars($pic_it).'</td>
    </tr>

    <tr>
        <th>Deadline</th>
        <td>'.$deadline.'</td>
    </tr>

    <tr>
        <th>Budget Project</th>
        <td>Rp '.number_format($budget_project, 0, ',', '.').'</td>
    </tr>

    <tr>
        <th>Total Biaya Actual</th>
        <td>Rp '.number_format($total_biaya_actual, 0, ',', '.').'</td>
    </tr>

    <tr>
        <th>Sisa Budget</th>
        <td>Rp '.number_format($sisa_budget, 0, ',', '.').'</td>
    </tr>

    <tr>
        <th>AVG Progress</th>
        <td>'.$avg_progress.'%</td>
    </tr>

    <tr>
        <th>Status</th>
        <td>'.htmlspecialchars($status_project).'</td>
    </tr>
</table>

<div class="section-title">Riwayat Progress</div>

<table cellpadding="3">
    <tr>
        <th width="5%">No</th>
        <th width="14%">Tanggal</th>
        <th width="28%">Catatan</th>
        <th width="8%">%</th>
        <th width="10%">Jumlah</th>
        <th width="15%">Harga</th>
        <th width="12%">Total Harga</th>
        <th width="8%">Status</th>
    </tr>
';

$no = 1;

while ($p = sqlsrv_fetch_array($q_progress, SQLSRV_FETCH_ASSOC)) {

    $tanggal = "-";

    if (!empty($p['tanggal'])) {
        $tanggal = $p['tanggal']->format('d-M-Y');
    }

    $catatan = isset($p['catatan']) ? $p['catatan'] : '';
    $progress = isset($p['progress']) ? $p['progress'] : 0;

    $qty = isset($p['qty']) ? $p['qty'] : 0;
    $amount = isset($p['amount']) ? $p['amount'] : 0;

    $biaya_progress = isset($p['biaya']) ? $p['biaya'] : 0;
    $status_progress = isset($p['status']) ? $p['status'] : '';

    $html .= '
    <tr>
        <td>'.$no++.'</td>
        <td>'.$tanggal.'</td>
        <td>'.htmlspecialchars($catatan).'</td>
        <td>'.htmlspecialchars($progress).'%</td>
        <td>'.number_format($qty, 2).'</td>
        <td>Rp '.number_format($amount, 0, ',', '.').'</td>
        <td>Rp '.number_format($biaya_progress, 0, ',', '.').'</td>
        <td>'.htmlspecialchars($status_progress).'</td>
    </tr>
    ';
}

if ($no == 1) {
    $html .= '
    <tr>
        <td colspan="8" style="text-align:center;">
            Belum ada data progress
        </td>
    </tr>
    ';
}

$html .= '
    <tr>
        <th colspan="6" class="text-right">Total Biaya Progress</th>
        <th colspan="2">Rp '.number_format($total_biaya_progress, 0, ',', '.').'</th>
    </tr>
</table>

<div class="section-title">Riwayat Pembayaran Aktual</div>

<table cellpadding="3">
    <tr>
        <th width="5%">No</th>
        <th width="15%">Tanggal</th>
        <th width="20%">Kategori</th>
        <th width="40%">Keterangan</th>
        <th width="20%">Nominal</th>
    </tr>
';

$no = 1;

while ($b = sqlsrv_fetch_array($q_biaya, SQLSRV_FETCH_ASSOC)) {

    $tanggal = "-";

    if (!empty($b['tanggal'])) {
        $tanggal = $b['tanggal']->format('d-M-Y');
    }

    $kategori = isset($b['kategori']) ? $b['kategori'] : '';
    $keterangan = isset($b['keterangan']) ? $b['keterangan'] : '';
    $nominal = isset($b['nominal']) ? $b['nominal'] : 0;

    $html .= '
    <tr>
        <td>'.$no++.'</td>
        <td>'.$tanggal.'</td>
        <td>'.htmlspecialchars($kategori).'</td>
        <td>'.htmlspecialchars($keterangan).'</td>
        <td>Rp '.number_format($nominal, 0, ',', '.').'</td>
    </tr>
    ';
}

if ($no == 1) {
    $html .= '
    <tr>
        <td colspan="5" style="text-align:center;">
            Belum ada data biaya actual
        </td>
    </tr>
    ';
}

$html .= '
    <tr>
        <th colspan="4" class="text-right">Total Biaya Actual</th>
        <th>Rp '.number_format($total_biaya_actual, 0, ',', '.').'</th>
    </tr>

    <tr>
        <th colspan="4" class="text-right">Pengajuan</th>
        <th>Rp '.number_format($pengajuan, 0, ',', '.').'</th>
    </tr>
</table>
';

$pdf->writeHTML($html, true, false, true, false, '');

$file_name = "detail_project_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $nama_project) . ".pdf";

$pdf->Output($file_name, 'I');
exit;
?>
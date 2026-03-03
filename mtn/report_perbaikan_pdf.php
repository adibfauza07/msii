<?php
// FILE: mtn/report_perbaikan_pdf.php

require "../config/database.php";

// 1. LOAD LIBRARY TCPDF
if (file_exists('../tcpdf/tcpdf.php')) {
    require_once('../tcpdf/tcpdf.php');
} elseif (file_exists('../vendor/tecnickcom/tcpdf/tcpdf.php')) {
    require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');
} else {
    die("Error: Library TCPDF tidak ditemukan.");
}

// 2. Ambil Parameter
$from  = isset($_GET['from'])  ? $_GET['from']  : "";
$to    = isset($_GET['to'])    ? $_GET['to']    : "";
$mac   = isset($_GET['mac'])   ? trim($_GET['mac'])   : "";
$plant = isset($_GET['plant']) ? trim($_GET['plant']) : "";

$from_sql = $from ? date("Y-m-d", strtotime($from)) : "";
$to_sql   = $to   ? date("Y-m-d", strtotime($to))   : "";
$mac_param = ($mac === "" ? "%" : $mac);
$plant_param = ($plant === "" ? 0 : (int)$plant);

// 3. Query Database
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

// 4. SETUP PDF
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('MSII System');
$pdf->SetTitle('Laporan Perbaikan Mesin');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(TRUE, 10);
$pdf->AddPage();

// 5. SUSUN HTML
$periodeStr = ($from_sql ? date("d-M-Y", strtotime($from_sql)) : "-") . ' s/d ' . ($to_sql ? date("d-M-Y", strtotime($to_sql)) : "-");
$filterStr  = "MAC: " . ($mac === "" ? "SEMUA" : $mac) . " | Plant: " . ($plant === "" ? "SEMUA" : $plant);

// DEFINISI LEBAR KOLOM (TOTAL HARUS 100%)
// Kita simpan di variabel biar gampang diedit dan pasti sama
$w1 = "8%";   // MAC
$w2 = "5%";   // Plant
$w3 = "6%";   // Tonage
$w4 = "12%";  // Tanggal
$w5 = "24%";  // Description
$w6 = "25%";  // Service
$w7 = "20%";  // Kerusakan

$html = '
<h2 style="text-align:center;">LAPORAN PERBAIKAN MESIN</h2>
<p style="text-align:center; font-size:10pt;">
    Periode: '.$periodeStr.'<br>
    '.$filterStr.'
</p>
<br>

<table border="1" cellpadding="5" cellspacing="0" style="font-size:9pt; width:100%;">
    <thead>
        <tr style="background-color:#eee; font-weight:bold;">
            <th width="'.$w1.'" align="center">MAC</th>
            <th width="'.$w2.'" align="center">Plt</th>
            <th width="'.$w3.'" align="center">Ton</th>
            <th width="'.$w4.'" align="center">Tanggal</th>
            <th width="'.$w5.'" align="center">Description</th>
            <th width="'.$w6.'" align="center">Service</th>
            <th width="'.$w7.'" align="center">Kerusakan</th>
        </tr>
    </thead>
    <tbody>';

if (empty($data)) {
    $html .= '<tr><td colspan="7" align="center">Tidak ada data untuk periode ini.</td></tr>';
} else {
    foreach ($data as $r) {
        $tgl = $r['DATE'] instanceof DateTime ? $r['DATE']->format("d-M-Y") : "";
        
        $desc  = nl2br(htmlspecialchars($r['DESCRIPTION']));
        $serv  = nl2br(htmlspecialchars($r['SERVICE']));
        $rusak = htmlspecialchars($r['KERUSAKAN']);

        // FIX: Tambahkan width="xx%" di setiap td agar sama persis dengan th
        $html .= '<tr>
            <td width="'.$w1.'" align="center">'.$r['MAC'].'</td>
            <td width="'.$w2.'" align="center">'.$r['PLANT'].'</td>
            <td width="'.$w3.'" align="center">'.$r['TONAGE'].'</td>
            <td width="'.$w4.'" align="center">'.$tgl.'</td>
            <td width="'.$w5.'">'.$desc.'</td>
            <td width="'.$w6.'">'.$serv.'</td>
            <td width="'.$w7.'">'.$rusak.'</td>
        </tr>';
    }
}

$html .= '</tbody></table>';

// 6. Output
$pdf->writeHTML($html, true, false, true, false, '');
$filename = "Laporan_Perbaikan_" . date("Ymd_His") . ".pdf";
$pdf->Output($filename, 'I'); 
?>
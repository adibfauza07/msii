<?php 
// slip.php (MATERIAL/PART SLIP - TCPDF Layout Terkunci 1 Halaman)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Kompatibilitas PHP 5.4 untuk Sesi
if (!isset($_SESSION)) { 
    session_start(); 
}

require_once __DIR__ . "/../config/global.php";
require_once __DIR__ . "/../assets/tcpdf_min/tcpdf.php";

// Sanitasi Input (Cegah SQL Injection)
$tranid = isset($_GET['tranid']) ? intval($_GET['tranid']) : 0;

if ($conn === false || $tranid === 0) {
    die("Koneksi database gagal atau TRAN_ID tidak valid.");
}

// Logika Deteksi Plant
$ses_plant = isset($_SESSION['plant']) ? strtolower(trim($_SESSION['plant'])) : 'plan1';

if ($ses_plant === 'plan2' || $ses_plant === 'plant2') {
    $nama_pabrik = "PT. IMC TEKNO INDONESIA PLANT 2";
    $dept = "PPIC DEPARTEMENT PLANT 2"; 
} else {
    $nama_pabrik = "PT. IMC TEKNO INDONESIA PLANT 1";
    $dept = "PPIC DEPARTEMENT";
}

// Eksekusi Kueri Header (Parameterized Query)
$sqlHeader = "
    SELECT TOP 1 
        TRANS.*, 
        TRTY.TRTY_DESC
    FROM TRANS
    LEFT JOIN TRTY ON TRTY.TRTY_CODE = TRANS.TRTY_CODE
    WHERE TRANS.TRAN_ID = ?
";
$stmtH = sqlsrv_query($conn, $sqlHeader, array($tranid));
if ($stmtH === false || !sqlsrv_has_rows($stmtH)) {
    die("Data transaksi tidak ditemukan.");
}
$header = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmtH);

// Toleransi Format Tanggal PHP 5.4
$tran_time = is_object($header['TRANS_TIME']) ? $header['TRANS_TIME']->format('H:i:s') : (string)$header['TRANS_TIME'];
$tran_adate = is_object($header['TRAN_ADATE']) ? $header['TRAN_ADATE']->format('d-M-Y') : (string)$header['TRAN_ADATE'];

// Eksekusi Kueri Detail Grouping
$sqlDetail = "
    SELECT 
        ITEMS.ITEM_CODE, 
        ITEMS.ITEM_NAME, 
        ITEMS.ITEM_UNIT, 
        SUM(INV_TRAN.IT_QTY) AS IT_QTY
    FROM INV_TRAN
    INNER JOIN ITEMS ON ITEMS.ITEM_ID = INV_TRAN.ITEM_ID
    WHERE INV_TRAN.TRAN_ID = ?
    GROUP BY 
        INV_TRAN.ITEM_ID, 
        ITEMS.ITEM_CODE, 
        ITEMS.ITEM_NAME, 
        ITEMS.ITEM_UNIT
";
$stmtD = sqlsrv_query($conn, $sqlDetail, array($tranid));
$details = array();
$total_qty = 0;
if ($stmtD !== false) {
    while ($row = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) {
        $details[] = $row;
        $total_qty += (float)$row['IT_QTY'];
    }
    sqlsrv_free_stmt($stmtD);
}

// Inisialisasi TCPDF
$pdf = new TCPDF('L', 'mm', 'A5', true, 'UTF-8', false);
$pdf->SetCreator('ERP System');
$pdf->SetAuthor($dept);
$pdf->SetTitle('Material/Part Slip - ' . $header['TRAN_DOC']);
$pdf->SetPrintHeader(false);
$pdf->SetPrintFooter(false);
$pdf->SetMargins(7, 5, 7); // Margin diperkecil agar pas 1 halaman
$pdf->SetAutoPageBreak(TRUE, 5);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 9);

// Konstruksi HTML untuk TCPDF
$html = '
<table width="100%" cellpadding="1" border="0">
    <tr>
        <td width="60%">
            <b style="font-size:13px;">'.htmlspecialchars($nama_pabrik, ENT_QUOTES, 'UTF-8').'</b><br>
            <span style="font-size:9px;">'.htmlspecialchars($dept, ENT_QUOTES, 'UTF-8').'</span>
        </td>
        <td width="40%" align="right" style="font-size:9px;">
            TIME: '.htmlspecialchars($tran_time, ENT_QUOTES, 'UTF-8').'
        </td>
    </tr>
    <tr>
        <td colspan="2" align="center" style="line-height:1.5;">
            <br><b style="font-size:12px; text-decoration: underline;">MATERIAL/PART SLIP</b>
        </td>
    </tr>
</table>
<br>

<table width="100%" cellpadding="1" style="font-size:9px;" border="0">
    <tr>
        <td width="12%"><b>NOMOR</b></td><td width="2%">:</td><td width="36%"><b>'.htmlspecialchars($header['TRAN_DOC'], ENT_QUOTES, 'UTF-8').'</b></td>
        <td width="15%"></td><td width="2%"></td><td width="33%"></td>
    </tr>
    <tr>
        <td><b>DATE</b></td><td>:</td><td><b>'.htmlspecialchars($tran_adate, ENT_QUOTES, 'UTF-8').'</b></td>
        <td><b>FROM</b></td><td>:</td><td><b>'.htmlspecialchars((string)$header['TRTY_DESC'], ENT_QUOTES, 'UTF-8').'</b></td>
    </tr>
</table>
<br>

<table width="100%" border="1" cellpadding="3" style="font-size:9px;">
    <tr align="center" style="font-weight:bold;">
        <th width="5%">NO</th>
        <th width="15%">WO NO</th>
        <th width="20%">ITEM CODE</th>
        <th width="32%">MATERIAL NAME</th>
        <th width="6%">UNIT</th>
        <th width="10%">QUANTITY</th>
        <th width="12%">REMARK</th>
    </tr>';

    // Konfigurasi Baris: Tetap 8 baris agar persis dengan desain
    $max_rows = 8;
    $total_items = count($details);
    $rows_to_print = ($total_items > $max_rows) ? $total_items : $max_rows;

    for ($i = 0; $i < $rows_to_print; $i++) {
        if ($i < $total_items) {
            $key = $details[$i];
            
            // HACK TCPDF: Potong string di level PHP (Max ~38 karakter) untuk simulasi "text-overflow: ellipsis"
            $matName = $key['ITEM_NAME'];
            if (strlen($matName) > 38) {
                $matName = substr($matName, 0, 35) . '...';
            }
            
            $html .= '<tr>
                    <td align="center">'.($i+1).'</td>
                    <td></td>
                    <td>'.htmlspecialchars($key['ITEM_CODE'], ENT_QUOTES, 'UTF-8').'</td>
                    <td>'.htmlspecialchars($matName, ENT_QUOTES, 'UTF-8').'</td>
                    <td align="center">'.htmlspecialchars($key['ITEM_UNIT'], ENT_QUOTES, 'UTF-8').'</td>
                    <td align="right">'.number_format($key['IT_QTY'], 2).'</td>
                    <td></td>
                  </tr>';
        } else {
            // Cetak baris kosong persis seperti gambar
            $html .= '<tr><td align="center">'.($i+1).'</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
        }
    }

    $html .= '
    <tr>
        <td colspan="5" align="right"><b>TOTAL</b></td>
        <td align="right"><b>'.number_format($total_qty, 2).'</b></td>
        <td></td>
    </tr>
</table>
<br>

<table width="100%" cellpadding="1" style="font-size:9px;" border="0">
    <tr>
        <td colspan="3" style="font-size:7px;">FM.CO.01-36(Revisi2:Tgl.10.Des.2019)</td>
    </tr>
    <tr>
        <td colspan="3"><br></td>
    </tr>
    <tr align="center">
        <td width="35%">DELIVERED</td>
        <td width="30%"></td>
        <td width="35%">RECEIVED</td>
    </tr>
    <tr>
        <td colspan="3"><br><br><br><br></td>
    </tr>
    <tr align="center">
        <td>(........................................)</td>
        <td></td>
        <td>(........................................)</td>
    </tr>
</table>';

// Tulis HTML ke PDF
$pdf->writeHTML($html, true, false, true, false, '');

// Output Preview
$pdf->Output('SLIP_'.$header['TRAN_DOC'].'.pdf', 'I');
?>
<?php 
// slip.php (TCPDF - Kompatibel PHP 5.4 & SQL Server 2008 - Layout Terkunci 1 Halaman)
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (!isset($_SESSION)) { 
    session_start(); 
}
require_once __DIR__ . "/../config/global.php";
require_once __DIR__ . "/../assets/tcpdf_min/tcpdf.php";

$tranid = isset($_GET['tranid']) ? intval($_GET['tranid']) : 0;

if ($conn === false || $tranid === 0) {
    die("Koneksi database gagal atau TRAN_ID tidak valid.");
}

// ==== LOGIKA DETEKSI PLANT (ADDRESS) ====
$ses_plant = isset($_SESSION['plant']) ? strtolower(trim($_SESSION['plant'])) : 'plan1';

if ($ses_plant === 'plan2' || $ses_plant === 'plant2') {
    $nama_pabrik = "PT. IMC TEKNO INDONESIA PLANT 2";
    $alamat_pabrik = "Kawasan Industry Kota Bukit Indah ST-1 <br>Blok A-III Lot No.15E-15F<br>Purwakarta, Jawa Barat 41181, Indonesia.";
} else {
    $nama_pabrik = "PT. IMC TEKNO INDONESIA PLANT 2";
    $alamat_pabrik = "Kawasan Industry Kota Bukit Indah ST-1 <br>Blok A-III Lot No.15E-15F<br>Purwakarta, Jawa Barat 41181, Indonesia.";
}
// ========================================

// 1. Kueri Header Transaksi
$sqlHeader = "
    SELECT TOP 1 
        TRANS.*, 
        SUPPLIER.SUP_COMP, 
        BCTY.BCTY_NAME, 
        TRTY.TRTY_DESC
    FROM TRANS
    LEFT JOIN SUPPLIER ON SUPPLIER.SUP_CODE = TRANS.SUP_CODE
    LEFT JOIN BCTY ON BCTY.BCTY_ID = TRANS.BCTY_ID
    LEFT JOIN TRTY ON TRTY.TRTY_CODE = TRANS.TRTY_CODE
    WHERE TRANS.TRAN_ID = ?
";
$stmtH = sqlsrv_query($conn, $sqlHeader, array($tranid));
if ($stmtH === false || !sqlsrv_has_rows($stmtH)) {
    die("Data transaksi tidak ditemukan.");
}
$header = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmtH);

$tran_time = is_object($header['TRANS_TIME']) ? $header['TRANS_TIME']->format('H:i:s') : (string)$header['TRANS_TIME'];
$tran_adate = is_object($header['TRAN_ADATE']) ? $header['TRAN_ADATE']->format('d-M-Y') : (string)$header['TRAN_ADATE'];
$bc_info = trim($header['BCTY_NAME']) != '' ? htmlspecialchars($header['BCTY_NAME'] . '/' . $header['BC_NO'], ENT_QUOTES, 'UTF-8') : '-';

// 2. Kueri Detail
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
if ($stmtD !== false) {
    while ($row = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) {
        $details[] = $row;
    }
    sqlsrv_free_stmt($stmtD);
}

// SETUP TCPDF
$pdf = new TCPDF('L', 'mm', 'A5', true, 'UTF-8', false);
$pdf->SetCreator('ERP System');
$pdf->SetTitle('Surat Pengantar Barang - ' . $header['TRAN_DOC']);
$pdf->SetPrintHeader(false);
$pdf->SetPrintFooter(false);
$pdf->SetMargins(5, 5, 5); 

// KUNCI UTAMA: Matikan AutoPageBreak agar tidak pernah lari ke halaman 2
$pdf->SetAutoPageBreak(FALSE, 0); 
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 9);

$html = '
<table width="100%" cellpadding="1" border="0">
    <tr>
        <td width="70%">
            <b style="font-size:13px;">'.htmlspecialchars($nama_pabrik, ENT_QUOTES, 'UTF-8').'</b><br>
            <span style="font-size:9px;">'.$alamat_pabrik.'</span>
        </td>
        <td width="30%" align="right" style="font-size:9px;">
            TIME: '.htmlspecialchars($tran_time, ENT_QUOTES, 'UTF-8').'
        </td>
    </tr>
    <tr>
        <td colspan="2" align="center" style="line-height:1.2;">
            <b style="font-size:12px; text-decoration: underline;">SURAT PENGANTAR BARANG</b>
        </td>
    </tr>
</table>
<br>

<table width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td width="50%">
            <table width="100%" cellpadding="2" border="1" style="border-right:none; font-size:9px;">
                <tr>
                    <td width="24%">Nomor</td><td width="4%" align="center">:</td><td width="72%"><b>'.htmlspecialchars($header['TRAN_DOC'], ENT_QUOTES, 'UTF-8').'</b></td>
                </tr>
                <tr>
                    <td>Tanggal</td><td align="center">:</td><td><b>'.htmlspecialchars($tran_adate, ENT_QUOTES, 'UTF-8').'</b></td>
                </tr>
                <tr>
                    <td>No. Kend</td><td align="center">:</td><td></td>
                </tr>
                <tr>
                    <td>Jenis/NO.BC</td><td align="center">:</td><td>'.$bc_info.'</td>
                </tr>
            </table>
        </td>
        <td width="50%">
            <table width="100%" cellpadding="2" border="1" style="font-size:9px; height: 100%;">
                <tr>
                    <td height="50">Kepada Yth.<br><b style="font-size:10px;">'.htmlspecialchars((string)$header['SUP_COMP'], ENT_QUOTES, 'UTF-8').'</b></td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<br>

<table width="100%" border="1" cellpadding="2" style="font-size:9px;">
    <tr align="center" style="font-weight:bold;">
        <th width="5%">NO</th>
        <th width="15%">KODE</th>
        <th width="35%">NAMA</th>
        <th width="8%">SATUAN</th>
        <th width="12%">JUMLAH</th>
        <th width="25%">KETERANGAN</th>
    </tr>';

    $max_rows = 8;
    $total_items = count($details);
    $rows_to_print = ($total_items > $max_rows) ? $total_items : $max_rows;

    for ($i = 0; $i < $rows_to_print; $i++) {
        if ($i < $total_items) {
            $key = $details[$i];
            
            // POTONG TEKS NAMA MATERIAL
            $matName = $key['ITEM_NAME'];
            if (strlen($matName) > 40) {
                $matName = substr($matName, 0, 37) . '...';
            }

            $html .= '<tr>
                    <td align="center">'.($i+1).'</td>
                    <td>'.htmlspecialchars($key['ITEM_CODE'], ENT_QUOTES, 'UTF-8').'</td>
                    <td>'.htmlspecialchars($matName, ENT_QUOTES, 'UTF-8').'</td>
                    <td align="center">'.htmlspecialchars($key['ITEM_UNIT'], ENT_QUOTES, 'UTF-8').'</td>
                    <td align="right">'.number_format($key['IT_QTY'], 2).'</td>
                    <td></td>
                  </tr>';
        } else {
            $html .= '<tr><td align="center">'.($i+1).'</td><td></td><td></td><td></td><td></td><td></td></tr>';
        }
    }

$html .= '
</table>
<br>

<table width="100%" cellpadding="1" style="font-size:9px;" border="0">
    <tr align="center">
        <td width="20%">Penerima</td>
        <td width="20%"></td>
        <td width="20%">Mengetahui</td>
        <td width="20%"></td>
        <td width="20%">Hormat Kami</td>
    </tr>
    <!-- PERUBAHAN: Menggunakan atribut height (35) untuk jarak tanda tangan, bukan tag BR -->
    <tr><td colspan="5" height="35"></td></tr>
    <tr align="center">
        <td>(........................)</td>
        <td></td>
        <td>(........................)</td>
        <td></td>
        <td>(........................)</td>
    </tr>
    <tr><td colspan="5" height="5"></td></tr>
    <tr>
        <td style="font-size:8px;">1. White:Customer</td>
        <td style="font-size:8px;">2. Pink:Accounting</td>
        <td style="font-size:8px;">3. Yellow:Store</td>
        <td style="font-size:8px;">4. Blue:Customer</td>
        <td style="font-size:8px;">5. Green:Security</td>
    </tr>
    <tr>
        <td colspan="5" style="font-size:7px; padding-top:2px;">FM.CO.01-20(Revisi4:Tgl.1.Mei.12)</td>
    </tr>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('SLIP_'.$header['TRAN_DOC'].'.pdf', 'I');
?>
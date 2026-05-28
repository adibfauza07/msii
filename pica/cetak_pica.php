<?php
session_start();
if (!isset($_SESSION['db_user'])) { header("Location: ../login.php"); exit(); }

require_once __DIR__ . '/../config/database_p1.php';
// PANGGIL LIBRARY TCPDF DARI FOLDER ASSETS
require_once __DIR__ . '/../assets/tcpdf_min/tcpdf.php';

$id = isset($_GET['id']) ? $_GET['id'] : 0;

// Ambil Data dari Database
$sql = "SELECT h.*, CONVERT(varchar, h.PicaDate, 106) as Tanggal, 
        w.MainProblem, w.DataSupport, w.Why1, w.Why2, w.Why3, w.Why4, w.Why5,
        a.ObjectiveTarget, a.Activity, a.Dept, a.PIC, a.StatusRemark
        FROM PICA_HEADER h
        LEFT JOIN PICA_5WHY w ON h.PicaID = w.PicaID
        LEFT JOIN PICA_ACTION a ON h.PicaID = a.PicaID
        WHERE h.PicaID = ?";
        
$stmt = sqlsrv_query($conn, $sql, array($id));

// PENDETEKSI ERROR SQL SERVER
if ($stmt === false) { 
    echo "<h3>Gagal mengambil data dari Database!</h3>";
    echo "Detail Error SQL Server:<br>";
    die(print_r(sqlsrv_errors(), true)); 
}

$dt = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$dt) { die("Data PICA tidak ditemukan."); }

// ==========================================================
// PENYESUAIAN PHP 5.6 (Mencegah error unexpected '?')
// ==========================================================
$noTr = isset($dt['NoTR']) ? htmlspecialchars($dt['NoTR']) : '';
$tanggal = isset($dt['Tanggal']) ? htmlspecialchars($dt['Tanggal']) : '';
$problemTitle = isset($dt['ProblemTitle']) ? htmlspecialchars($dt['ProblemTitle']) : '';
$customer = isset($dt['Customer']) ? htmlspecialchars($dt['Customer']) : '';
$supplier = isset($dt['Supplier']) ? htmlspecialchars($dt['Supplier']) : '';

$mainProblem = isset($dt['MainProblem']) ? nl2br(htmlspecialchars($dt['MainProblem'])) : '';
$dataSupport = isset($dt['DataSupport']) ? nl2br(htmlspecialchars($dt['DataSupport'])) : '';
$why1 = isset($dt['Why1']) ? htmlspecialchars($dt['Why1']) : '';
$why2 = isset($dt['Why2']) ? htmlspecialchars($dt['Why2']) : '';
$why3 = isset($dt['Why3']) ? htmlspecialchars($dt['Why3']) : '';
$why4 = isset($dt['Why4']) ? htmlspecialchars($dt['Why4']) : '';
$why5 = isset($dt['Why5']) ? htmlspecialchars($dt['Why5']) : '';

$objectiveTarget = isset($dt['ObjectiveTarget']) ? nl2br(htmlspecialchars($dt['ObjectiveTarget'])) : '';
$activity = isset($dt['Activity']) ? nl2br(htmlspecialchars($dt['Activity'])) : '';
$dept = isset($dt['Dept']) ? htmlspecialchars($dt['Dept']) : '';
$pic = isset($dt['PIC']) ? htmlspecialchars($dt['PIC']) : '';
$statusRemark = isset($dt['StatusRemark']) ? strtoupper(htmlspecialchars($dt['StatusRemark'])) : '';

// ==========================================================
// KONFIGURASI TCPDF
// ==========================================================
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(TRUE, 10);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 8);

// ==========================================================
// KONTEN HTML UNTUK DI-GENERATE JADI PDF
// ==========================================================
$html = '
<style>
    th, td { vertical-align: middle; }
    .title-cell { font-weight: bold; text-align: center; letter-spacing: 2px; font-size: 10px; background-color: #f9f9f9; }
    .text-center { text-align: center; }
    .text-bold { font-weight: bold; }
</style>

<table border="1" cellpadding="4" cellspacing="0" width="100%">
    <tr>
        <td width="35%">
            <table border="0" cellpadding="2" width="100%">
                <tr><td width="25%" class="text-bold">NO TR</td><td width="5%">:</td><td width="70%">'.$noTr.'</td></tr>
                <tr><td class="text-bold">DATE</td><td>:</td><td>'.$tanggal.'</td></tr>
                <tr><td class="text-bold">PROBLEM</td><td>:</td><td>'.$problemTitle.'</td></tr>
                <tr><td class="text-bold">CUSTOMER</td><td>:</td><td>'.$customer.'</td></tr>
            </table>
        </td>
        <td width="40%" class="text-center" style="vertical-align: middle;">
            <br>
            <span style="font-size: 26px; font-weight: bold; font-style: italic;">
                <span style="color: #008000;">M</span> <span style="color: #333;">P I - C A</span>
            </span>
            <br><br>
            <span style="font-size: 11px; font-weight: bold;">SUPPLIER : '.$supplier.'</span>
        </td>
        <td width="25%">
            <table border="1" cellpadding="3" width="100%" style="text-align:center;">
                <tr>
                    <td width="25%">Approved:</td>
                    <td width="25%">Approved:</td>
                    <td width="25%">Checked:</td>
                    <td width="25%">Prepared:</td>
                </tr>
                <tr>
                    <td height="40"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td colspan="3" class="title-cell">PROBLEM IDENTIFICATION</td>
    </tr>
    <tr>
        <td colspan="3" style="padding: 0;">
            <table border="1" cellpadding="4" cellspacing="0" width="100%">
                <tr class="text-center text-bold">
                    <td rowspan="2" width="15%" style="line-height: 20px;">MAIN PROBLEM</td>
                    <td rowspan="2" width="25%" style="line-height: 20px;">DATA SUPPORT</td>
                    <td colspan="5" width="60%">PROBLEM ANALYZE</td>
                </tr>
                <tr class="text-center text-bold">
                    <td width="12%">1st WHY</td>
                    <td width="12%">2nd WHY</td>
                    <td width="12%">3rd WHY</td>
                    <td width="12%">4th WHY</td>
                    <td width="12%">5th WHY</td>
                </tr>
                <tr>
                    <td height="120">'.$mainProblem.'</td>
                    <td>'.$dataSupport.'</td>
                    <td>'.$why1.'</td>
                    <td>'.$why2.'</td>
                    <td>'.$why3.'</td>
                    <td>'.$why4.'</td>
                    <td>'.$why5.'</td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td colspan="3" class="title-cell">CORRECTIVE ACTION</td>
    </tr>
    <tr>
        <td colspan="3" style="padding: 0;">
            <table border="1" cellpadding="3" cellspacing="0" width="100%">
                <tr class="text-center text-bold">
                    <td rowspan="2" width="15%" style="line-height: 20px;">OBJECTIVE TARGET</td>
                    <td rowspan="2" width="31%" style="line-height: 20px;">ACTIVITY</td>
                    <td colspan="2" width="14%">RESPONSIBILITY</td>
                    <td colspan="2" width="14%">DUE DATE</td>
                    <td colspan="3" width="26%">AKTUAL REALISASI</td>
                </tr>
                <tr class="text-center text-bold" style="font-size: 7px;">
                    <td width="7%">DEPT</td>
                    <td width="7%">P.I.C</td>
                    <td width="7%">DATE</td>
                    <td width="7%">TIME</td>
                    <td width="8%">DATE</td>
                    <td width="8%">TIME</td>
                    <td width="10%">REMARK</td>
                </tr>
                <tr>
                    <td height="80">'.$objectiveTarget.'</td>
                    <td>'.$activity.'</td>
                    
                    <td class="text-center" style="line-height: 80px;">'.$dept.'</td>
                    <td class="text-center" style="line-height: 80px;">'.$pic.'</td>
                    
                    <td></td><td></td><td></td><td></td>
                    <td class="text-center text-bold" style="line-height: 80px;">'.$statusRemark.'</td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td colspan="3" style="padding: 0;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td width="35%" style="border-right: 1px solid #000;">
                        <table border="1" cellpadding="4" cellspacing="0" width="100%">
                            <tr><td class="title-cell" style="background-color: #fff;">REMARK</td></tr>
                            <tr><td height="50"></td></tr>
                        </table>
                    </td>
                    <td width="65%">
                        <table border="1" cellpadding="2" cellspacing="0" width="100%" style="font-size: 7px; text-align: center;">
                            <tr><td colspan="10" class="title-cell" style="background-color: #fff; font-size: 8px;">INFORMATION DISTRIBUTION TO RELATED DEPARTMENT</td></tr>
                            <tr class="text-bold">
                                <td width="10%">DEPT</td><td width="10%">P.I.C</td><td width="10%">DATE</td><td width="10%">TIME</td><td width="10%">SIGN</td>
                                <td width="10%">DEPT</td><td width="10%">P.I.C</td><td width="10%">DATE</td><td width="10%">TIME</td><td width="10%">SIGN</td>
                            </tr>
                            <tr>
                                <td height="16"></td><td></td><td></td><td></td><td></td>
                                <td></td><td></td><td></td><td></td><td></td>
                            </tr>
                            <tr>
                                <td height="16"></td><td></td><td></td><td></td><td></td>
                                <td></td><td></td><td></td><td></td><td></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
';

$pdf->writeHTML($html, true, false, true, false, '');
ob_end_clean();
$pdf->Output('PICA_Report_'.$id.'.pdf', 'I');
?>
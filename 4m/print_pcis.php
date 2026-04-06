<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/database.php';

// Ambil Nomor Control dari URL (Sesuai fungsi printData di JavaScript sebelumnya)
$no = isset($_GET['no']) ? $_GET['no'] : '';

if ($no == '') die("Nomor Kontrol Tidak Ditemukan.");

// Query Lengkap mengambil Header & Join ke Master Data
// Query yang disesuaikan dengan nama kolom di View PC_ITEM_CUSTOMER_VIEW
$sql = "SELECT P.*, C.CUST_COMP, V.PART_NAME, V.PART_NO, D.DEP_NAME 
        FROM PROSES_CHANGE P
        LEFT JOIN CUST C ON P.ITEM_ID = C.CUST_ID 
        LEFT JOIN PC_ITEM_CUSTOMER_VIEW V ON P.ITEM_ID = V.ITEM_ID
        LEFT JOIN DEPT D ON P.DEP_CODE = D.DEP_CODE
        WHERE P.CONTROL_NO = ?";

$stmt = q($sql, array($no));
$d = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$d) die("Data dengan nomor $no tidak ada di database.");

// Format Tanggal
function fTgl($date) {
    return ($date instanceof DateTime) ? $date->format('d-M-Y') : '-';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak PCIS - <?php echo $no; ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #000; margin: 0; padding: 20px; }
        .wrapper { width: 100%; max-width: 210mm; margin: 0 auto; border: 1px solid #ccc; padding: 10px; }
        
        /* Header */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .header-table td { border: 1px solid #000; padding: 5px; vertical-align: top; }
        .title-pcis { font-size: 14px; font-weight: bold; text-align: center; text-decoration: underline; }
        
        /* Main Layout Table */
        .main-table { width: 100%; border-collapse: collapse; }
        .main-table td { border: 1px solid #000; padding: 4px; vertical-align: top; }
        .bg-gray { background-color: #f0f0f0; font-weight: bold; }
        
        /* Checkbox & Radio Manual Styling */
        .box { width: 10px; height: 10px; border: 1px solid #000; display: inline-block; margin-right: 3px; vertical-align: middle; text-align: center; line-height: 10px; font-size: 9px; }
        .checked { background-color: #000; color: #fff; }

        /* Footer / Approval */
        .footer-table { width: 100%; border-collapse: collapse; margin-top: -1px; }
        .footer-table td { border: 1px solid #000; padding: 5px; text-align: center; height: 20px; }
        
        @media print {
            .no-print { display: none; }
            .wrapper { border: none; padding: 0; }
            body { padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer;">KLIK UNTUK PRINT</button>
        <button onclick="window.close()" style="padding: 10px 20px; cursor: pointer;">TUTUP</button>
    </div>

    <div class="wrapper">
        <table class="header-table">
            <tr>
                <td width="20%"><img src="../logo_imc.jpg" width="60"></td>
                <td width="60%" class="title-pcis">PROSES CHANGE INFORMATION SHEET (PCIS)</td>
                <!-- Ganti bagian ini -->
<td width="20%">Plant: <?php echo isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p1' ? '1' : '2'; ?></td>
            </tr>
        </table>

        <table class="main-table">
            <tr>
                <td width="15%" class="bg-gray">CONTROL NO</td>
                <td width="35%"><?php echo $d['CONTROL_NO']; ?></td>
                <td width="15%" class="bg-gray">TO</td>
                <td width="35%"><?php echo $d['TO_PCIS']; ?></td>
            </tr>
            <tr>
                <td class="bg-gray">DATE</td>
                <td><?php echo fTgl($d['CONTROL_DATE1']); ?></td>
                <td class="bg-gray">CC</td>
                <td><?php echo $d['CC']; ?></td>
            </tr>
            <tr>
                <td class="bg-gray">REQUEST BY</td>
                <td>
                    <span class="box <?php echo $d['INTERNAL'] ? 'checked' : ''; ?>"><?php echo $d['INTERNAL'] ? 'v' : ''; ?></span> Internal &nbsp;
                    <span class="box <?php echo $d['CUSTOMER'] ? 'checked' : ''; ?>"><?php echo $d['CUSTOMER'] ? 'v' : ''; ?></span> Customer &nbsp;
                    <span class="box <?php echo $d['SUPPLIER'] ? 'checked' : ''; ?>"><?php echo $d['SUPPLIER'] ? 'v' : ''; ?></span> Supplier
                </td>
                <td class="bg-gray">PIC / DEPT</td>
                <td><?php echo $d['PIC_NAME'] . " / " . $d['DEP_NAME']; ?></td>
            </tr>
            <tr>
                <td class="bg-gray">CUSTOMER</td>
                <td><?php echo $d['CUST_COMP']; ?></td>
                <td class="bg-gray">PART NO/NAME</td>
                <!-- Ganti baris ini -->
<td><?php echo $d['PART_NO'] . " - " . $d['PART_NAME']; ?></td>
            </tr>
            <tr>
                <td class="bg-gray">ITEM CHANGE</td>
                <td>
                    <span class="box <?php echo $d['MAN'] ? 'checked' : ''; ?>"></span> Man &nbsp;
                    <span class="box <?php echo $d['MACHINE'] ? 'checked' : ''; ?>"></span> Machine &nbsp;
                    <span class="box <?php echo $d['METHOD'] ? 'checked' : ''; ?>"></span> Method &nbsp;
                    <span class="box <?php echo $d['MATERIAL'] ? 'checked' : ''; ?>"></span> Material
                </td>
                <td class="bg-gray">CHANGE TYPE</td>
                <td>
                    <span class="box <?php echo $d['PERMANENT_CHANGE'] ? 'checked' : ''; ?>"></span> Permenant &nbsp;
                    <span class="box <?php echo !$d['PERMANENT_CHANGE'] ? 'checked' : ''; ?>"></span> Temporary
                </td>
            </tr>
            <tr>
                <td colspan="4" class="bg-gray">REASON / PURPOSE:</td>
            </tr>
            <tr>
                <td colspan="4" style="height: 50px;"><?php echo nl2br($d['REASON']); ?></td>
            </tr>
            <tr>
                <td colspan="2" class="bg-gray" style="color: red;">BEFORE CHANGE:</td>
                <td colspan="2" class="bg-gray" style="color: green;">AFTER CHANGE:</td>
            </tr>
            <tr>
                <td colspan="2" style="height: 80px;"><?php echo nl2br($d['BEF_CHANGE']); ?></td>
                <td colspan="2" style="height: 80px;"><?php echo nl2br($d['AFT_CHANGE']); ?></td>
            </tr>
        </table>

        <!-- Timeline Section -->
        <table class="main-table" style="margin-top: -1px;">
            <tr>
                <td width="33%" class="bg-gray">SCHEDULE PROCESS CHANGE</td>
                <td width="33%" class="bg-gray">START CHANGING DATE</td>
                <td width="34%" class="bg-gray">CLOSING DATE</td>
            </tr>
            <tr>
                <td><?php echo fTgl($d['SCH_CHANGE']); ?></td>
                <td><?php echo fTgl($d['START_CHANGE']); ?></td>
                <td><?php echo fTgl($d['CLOSE_CHANGE']); ?></td>
            </tr>
        </table>

        <!-- Approval Section -->
        <table class="footer-table" style="margin-top: 10px;">
            <tr class="bg-gray">
                <td colspan="3">PT IMC TEKNO INDONESIA</td>
                <td colspan="2">CUSTOMER</td>
            </tr>
            <tr style="height: 10px;">
                <td width="20%">PREPARED</td><td width="20%">CHECKED</td><td width="20%">APPROVED</td>
                <td width="20%">CHECKED</td><td width="20%">APPROVED</td>
            </tr>
            <tr style="height: 80px;">
                <td><br><br><?php echo $d['IMC_PREPARED']; ?></td>
                <td><br><br><?php echo $d['IMC_CHECKED']; ?></td>
                <td><br><br><?php echo $d['IMC_APROVE']; ?></td>
                <td><br><br><?php echo $d['CUSTOMER_CHECKED']; ?></td>
                <td><br><br><?php echo $d['CUSTOMER_APROVE']; ?></td>
            </tr>
        </table>
        
        <div style="margin-top: 10px; font-size: 9px; font-style: italic;">
            Printed by ERP System at: <?php echo date('d-m-Y H:i:s'); ?> | FM.CO.01-35
        </div>
    </div>

</body>
</html>
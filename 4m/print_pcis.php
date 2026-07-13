<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/database.php';

// Ambil Nomor Control dari URL parameter
$no = isset($_GET['no']) ? trim($_GET['no']) : '';
$is_filtered = isset($_GET['filtered']) ? filter_var($_GET['filtered'], FILTER_VALIDATE_BOOLEAN) : false;

if ($no == '') {
    die("<div style='padding:20px; color:red; font-family:Arial;'><b>Error:</b> Control Number tidak ditemukan pada URL parameter.</div>");
}

// Query presisi menarik seluruh record PROSES_CHANGE beserta data view internal
$sql = "SELECT P.*, 
        V.PART_NAME, V.PART_NO, V.PART_CODE, V.CUST_COMP, V.CUST_ALIAS, 
        D.DEP_NAME,
        MAT.ITEM_NAME AS MATERIAL_NAME,
        MAT.ITEM_CODE AS MATERIAL_CODE
        FROM PROSES_CHANGE P
        LEFT JOIN ITEM_CUSTINFO_VIEW V ON P.ITEM_ID = V.ITEM_ID
        LEFT JOIN DEPT D ON P.DEP_CODE = D.DEP_CODE
        LEFT JOIN ITEMS MAT ON P.MATERIAL_ID = MAT.ITEM_ID
        WHERE P.CONTROL_NO = ?";

$stmt = q($sql, array($no));
$d = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$d) {
    die("<div style='padding:20px; color:red; font-family:Arial;'><b>Error:</b> Data dengan nomor " . htmlspecialchars($no) . " tidak terdaftar di database.</div>");
}

// Format Tanggal standard dokumen (d-M-Y)
function fTgl($date) {
    return ($date instanceof DateTime) ? $date->format('d-M-Y') : (!empty($date) ? date('d-M-Y', strtotime($date)) : '');
}

// Fungsi render simbol kotak centang standard Crystal Report (.rpt)
function renderBox($checked) {
    return ($checked == 1 || $checked === true || strtolower(trim(strval($checked))) === 'yes') 
        ? '<span class="cb-icon">&#9745;</span>'  // Checked box
        : '<span class="cb-icon">&#9744;</span>'; // Unchecked box
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>PROCESS CHANGE INFORMATION SHEET - <?php echo htmlspecialchars($no); ?></title>
    <style>
        * { box-sizing: border-box; -moz-box-sizing: border-box; }
        body { font-family: "Arial", sans-serif; font-size: 9.5pt; color: #000; background-color: #fff; margin: 0; padding: 0; }
        
        /* BAR NAVIGASI ATAS */
        .no-print { background: #f1f5f9; padding: 10px; text-align: center; border-bottom: 1px solid #cbd5e1; width: 100%; }
        .no-print button { padding: 6px 16px; font-weight: bold; font-size: 12px; cursor: pointer; border-radius: 4px; margin: 0 5px; }
        
        /* Mengunci ukuran kertas portrait A4 mirip lembar kerja .rpt asli */
        .report-page { width: 210mm; margin: 0 auto; padding: 12mm 10mm; background: #fff; }
        .main-title { font-size: 13pt; font-weight: bold; text-align: center; letter-spacing: 0.5px; margin-bottom: 15px; }
        
        /* Master Grid Tabel Lurus Tanpa Spasi */
        table { width: 100%; border-collapse: collapse; margin-bottom: -1px; table-layout: fixed; }
        th, td { border: 1px solid #000000; padding: 4px 6px; vertical-align: top; font-size: 8.5pt; }
        
        .lbl-bold { font-weight: bold; }
        .lbl-italic { font-style: italic; color: #334155; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        .cb-icon { font-size: 11pt; font-weight: bold; vertical-align: middle; margin-right: 3px; }
        .content-block { min-height: 55px; font-size: 8.5pt; line-height: 1.2; white-space: pre-wrap; }
        
        /* Utility Khusus Perbaikan Management Grid Border Sub-Tabel PIC */
        .sub-table-pic td { border: none !important; padding: 3px 0; }
        
        /* UTILITY ABSOLUT UNTUK MENYEMBUNYIKAN TOMBOL SAAT PRINT OUT */
        @media print {
            html, body { background: #fff; margin: 0; padding: 0; }
            .no-print { display: none !important; visibility: hidden !important; height: 0 !important; padding: 0 !important; border: none !important; }
            .report-page { width: 100%; margin: 0; padding: 5mm; border: none !important; }
        }
    </style>
</head>
<body <?php echo $is_filtered ? 'onload="window.print()"' : ''; ?>>

    <div class="no-print">
        <button onclick="window.print()" style="background: #2563eb; color: white; border: 1px solid #1d4ed8;">Cetak / Print Report</button>
        <button onclick="window.close()" style="background: #64748b; color: white; border: 1px solid #475569;">Tutup Halaman</button>
    </div>

    <div class="report-page">
        <div class="main-title">PROCESS CHANGE INFORMATION SHEET</div>

        <table>
            <tr>
                <td width="8%" style="border:none;" class="lbl-bold">TO</td>
                <td width="42%" style="border-bottom: 1px solid #000; border-top:none; border-left:none; border-right:none;"><?php echo htmlspecialchars(isset($d['TO_PCIS']) ? trim($d['TO_PCIS']) : ''); ?></td>
                <td width="15%" style="border:none;" class="lbl-bold">CONTROL NO</td>
                <td width="35%" style="border-bottom: 1px solid #000; border-top:none; border-left:none; border-right:none;" class="lbl-bold"><?php echo htmlspecialchars($d['CONTROL_NO']); ?></td>
            </tr>
            <tr>
                <td style="border:none;" class="lbl-bold">CC</td>
                <td style="border: none; border-bottom: 1px solid #000; padding-left: 5px;"><?php echo htmlspecialchars(isset($d['CC']) ? trim($d['CC']) : ''); ?></td>
                <td style="border:none;" class="lbl-bold">CONTROL DATE</td>
                <td style="border-bottom: 1px solid #000; border-top:none; border-left:none; border-right:none;"><?php echo fTgl($d['CONTROL_DATE1']); ?></td>
            </tr>
            <tr style="height: 6px;"><td colspan="4" style="border:none;"></td></tr>
        </table>

        <table>
            <tr>
                <td width="30%" class="lbl-italic" style="border-bottom:none;">Request by</td>
                <td width="70%" class="lbl-italic" style="border-bottom:none;">Person in Charge</td>
            </tr>
            <tr>
                <td style="border-top:none; padding-left: 15px; vertical-align: middle;">
                    <div style="margin-bottom: 5px;"><?php echo renderBox($d['INTERNAL']); ?> Internal</div>
                    <div style="margin-bottom: 5px;"><?php echo renderBox($d['CUSTOMER']); ?> Customer</div>
                    <div><?php echo renderBox($d['SUPPLIER']); ?> Supplier</div>
                </td>
                <td style="border-top:none; padding: 6px 12px;">
                    <table class="sub-table-pic" style="width:100%; border:none; margin:0;">
                        <tr>
                            <td width="15%">Name</td>
                            <td width="5%">:</td>
                            <td width="80%" style="border-bottom:1px solid #000 !important;"><?php echo htmlspecialchars($d['PIC_NAME']); ?></td>
                        </tr>
                        <tr>
                            <td>Department</td>
                            <td>:</td>
                            <td style="border-bottom:1px solid #000 !important;"><?php echo htmlspecialchars($d['DEP_NAME'] ? $d['DEP_NAME'] : $d['DEP_CODE']); ?></td>
                        </tr>
                        <tr>
                            <td>Sign</td>
                            <td>:</td>
                            <td style="border-bottom:1px solid #000 !important;"></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table>
            <tr class="text-center lbl-bold">
                <td width="45%">Part Name</td>
                <td width="35%">Part No</td>
                <td width="20%">Model</td>
            </tr>
            <tr class="text-center">
                <td><?php echo htmlspecialchars($d['PART_NAME'] ? $d['PART_NAME'] : '-'); ?></td>
                <td><?php echo htmlspecialchars($d['PART_NO'] ? $d['PART_NO'] : '-'); ?></td>
                <td><?php echo htmlspecialchars($d['MODEL'] ? $d['MODEL'] : '-'); ?></td>
            </tr>
            <tr class="text-center lbl-bold">
                <td>Material</td>
                <td>Material Code</td>
                <td>Customer</td>
            </tr>
            <tr class="text-center">
                <td><?php echo htmlspecialchars($d['MATERIAL_NAME'] ? $d['MATERIAL_NAME'] : '-'); ?></td>
                <td><?php echo htmlspecialchars($d['MATERIAL_CODE'] ? $d['MATERIAL_CODE'] : '-'); ?></td>
                <td><?php echo htmlspecialchars($d['CUST_COMP'] ? $d['CUST_COMP'] : ($d['CUST_ALIAS'] ? $d['CUST_ALIAS'] : '-')); ?></td>
            </tr>
        </table>

        <table>
            <tr>
                <td width="45%" class="lbl-italic" style="border-bottom:none;">Item Change</td>
                <td width="55%" class="lbl-italic" style="border-bottom:none;">Changing Type</td>
            </tr>
            <tr>
                <td style="border-top:none; padding: 6px 15px;">
                    <div style="display: inline-block; width: 45%; margin-bottom: 4px;"><?php echo renderBox($d['MAN']); ?> Man</div>
                    <div style="display: inline-block; width: 45%; margin-bottom: 4px;"><?php echo renderBox($d['MATERIAL']); ?> Material</div>
                    <div style="display: inline-block; width: 45%;"><?php echo renderBox($d['MACHINE']); ?> Machine</div>
                    <div style="display: inline-block; width: 45%;"><?php echo renderBox($d['OTHER']); ?> Other</div>
                    <div style="margin-top: 4px;"><?php echo renderBox($d['METHOD']); ?> Method</div>
                </td>
                <td style="border-top:none; padding: 6px 15px;">
                    <div style="margin-bottom: 4px;"><?php echo renderBox($d['PERMANENT_CHANGE']); ?> Permanent change</div>
                    <div style="margin-bottom: 2px;"><?php echo renderBox(!$d['PERMANENT_CHANGE']); ?> Temporary change</div>
                    <div style="padding-left: 20px;" class="lbl-italic">Implementation date (until when) : __________________</div>
                </td>
            </tr>
        </table>

        <table>
            <tr>
                <td width="80%" class="lbl-italic" style="border-bottom:none;">Reason / Purpose</td>
                <td width="20%" class="lbl-italic text-center" style="border-bottom:none; font-size:7.5pt;">Do we need Customer Approved</td>
            </tr>
            <tr>
                <td style="border-top:none; padding: 6px;">
                    <div class="content-block"><?php echo nl2br(htmlspecialchars($d['REASON'])); ?></div>
                </td>
                <td style="border-top:none; text-align: center; vertical-align: middle; padding-left: 10px;">
                    <div style="margin-bottom: 5px;"><?php echo renderBox($d['NEED_CUSTOMER'] == 1); ?> Yes</div>
                    <div><?php echo renderBox($d['NEED_CUSTOMER'] == 0 || $d['NEED_CUSTOMER'] === null); ?> No</div>
                </td>
            </tr>
        </table>

        <table>
            <tr class="lbl-bold">
                <td width="50%" style="color: #b91c1c;">BEFORE CHANGE</td>
                <td width="50%" style="color: #15803d;">AFTER CHANGE</td>
            </tr>
            <tr>
                <td><div style="min-height: 90px;" class="content-block"><?php echo nl2br(htmlspecialchars($d['BEF_CHANGE'])); ?></div></td>
                <td><div style="min-height: 90px;" class="content-block"><?php echo nl2br(htmlspecialchars($d['AFT_CHANGE'])); ?></div></td>
            </tr>
        </table>

        <table class="text-center lbl-bold" style="font-size: 8pt;">
            <tr>
                <td width="33.33%">Schedule Proses change : <span style="font-weight:normal;"><?php echo fTgl($d['SCH_CHANGE'] ? $d['SCH_CHANGE'] : ''); ?></span></td>
                <td width="33.33%">Start Changing date : <span style="font-weight:normal;"><?php echo fTgl($d['START_CHANGE'] ? $d['START_CHANGE'] : ''); ?></span></td>
                <td width="33.33%">Close Changing date : <span style="font-weight:normal;"><?php echo fTgl($d['CLOSE_CHANGE'] ? $d['CLOSE_CHANGE'] : ''); ?></span></td>
            </tr>
        </table>

        <table>
            <tr>
                <td class="lbl-italic" style="padding: 4px 10px;">
                    <span style="margin-right: 25px;">Attachment :</span>
                    <span style="margin-right: 25px;"><?php echo renderBox($d['EMAIL']); ?> Email / Information</span>
                    <span style="margin-right: 25px;"><?php echo renderBox($d['DRAWING']); ?> Drawing</span>
                    <span style="margin-right: 25px;"><?php echo renderBox($d['SAMPLE']); ?> Sample</span>
                    <span><?php echo renderBox($d['DATA']); ?> Data</span>
                </td>
            </tr>
        </table>

        <table>
            <tr class="text-center lbl-bold" style="background-color: #f8fafc;">
                <td width="20%">CONFIRMATION -></td>
                <td width="65%" class="lbl-italic" style="font-weight: normal; text-align: left; font-size: 8pt;">* Please put confirmation base on impact and risk management</td>
                <td width="15%">SIGN</td>
            </tr>
            <tr>
                <td class="lbl-bold" style="vertical-align: middle;">PPIC</td>
                <td><div style="min-height: 32px;" class="content-block"><?php echo htmlspecialchars($d['PPIC_REMARK'] ? $d['PPIC_REMARK'] : ''); ?></div></td>
                <td></td>
            </tr>
            <tr>
                <td class="lbl-bold" style="vertical-align: middle;">QC</td>
                <td><div style="min-height: 32px;" class="content-block"><?php echo htmlspecialchars($d['QC_REMARK'] ? $d['QC_REMARK'] : ''); ?></div></td>
                <td></td>
            </tr>
            <tr>
                <td class="lbl-bold" style="vertical-align: middle;">PRODUCTION</td>
                <td><div style="min-height: 32px;" class="content-block"><?php echo htmlspecialchars($d['PRODUCTION_REMARK'] ? $d['PRODUCTION_REMARK'] : ''); ?></div></td>
                <td></td>
            </tr>
            <tr>
                <td class="lbl-bold" style="vertical-align: middle;">MOLD SHOP</td>
                <td><div style="min-height: 32px;" class="content-block"><?php echo htmlspecialchars($d['MOLDSHOP_REMARK'] ? $d['MOLDSHOP_REMARK'] : ''); ?></div></td>
                <td></td>
            </tr>
            <tr>
                <td class="lbl-bold" style="vertical-align: middle;">PE</td>
                <td><div style="min-height: 32px;" class="content-block"><?php echo htmlspecialchars($d['PE_REMARK'] ? $d['PE_REMARK'] : ''); ?></div></td>
                <td></td>
            </tr>
            <tr>
                <td class="lbl-bold" style="vertical-align: middle;">MARKETING</td>
                <td><div style="min-height: 32px;" class="content-block"><?php echo htmlspecialchars($d['MARKETING_REMARK'] ? $d['MARKETING_REMARK'] : ''); ?></div></td>
                <td></td>
            </tr>
        </table>

        <table style="width: 100%; border-collapse: collapse; margin-top: 10px; table-layout: fixed;">
            <tr class="text-center lbl-bold" style="font-size: 8pt; background-color: #f1f5f9;">
                <td width="35%">CUSTOMER JUDGEMENT</td>
                <td colspan="2" width="30%">CUSTOMER APPROVAL</td>
                <td colspan="3" width="35%">PT. IMC Tekno Indonesia</td>
            </tr>
            <tr class="text-center" style="font-size: 7.5pt; font-weight: bold; background: #fafafa;">
                <td rowspan="3" style="padding: 6px; text-align: left; vertical-align: top; width: 35%;">
                    <span class="lbl-italic" style="font-size: 8pt; display:block; margin-bottom:2px;">Comment:</span>
                    <div style="min-height: 45px; font-size:8pt; color:#475569;"><?php echo htmlspecialchars($d['CUSTOMER_COMMENT'] ? $d['CUSTOMER_COMMENT'] : ''); ?></div>
                    <div class="text-right" style="padding-right: 15px; margin-top: 15px;">
                        <span style="margin-right: 15px; font-weight:bold; font-size:10pt;"><?php echo renderBox(isset($d['CUSTOMER_JUDGEMENT']) && ($d['CUSTOMER_JUDGEMENT'] == 1 || $d['CUSTOMER_JUDGEMENT'] === true)); ?> OK</span>
                        <span style="font-weight:bold; font-size:10pt;"><?php echo renderBox(isset($d['CUSTOMER_JUDGEMENT']) && ($d['CUSTOMER_JUDGEMENT'] == 0 || $d['CUSTOMER_JUDGEMENT'] === false) && $d['CUSTOMER_JUDGEMENT'] !== null && $d['CUSTOMER_JUDGEMENT'] !== ''); ?> NG</span>
                    </div>
                </td>
                <td width="15%">Approved</td>
                <td width="15%">Checked</td>
                <td width="11.66%">Approved</td>
                <td width="11.66%">Checked</td>
                <td width="11.68%">Prepared</td>
            </tr>
            <tr style="height: 70px;">
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr class="text-center" style="font-size: 8pt; font-weight: bold; background: #fff;">
                <td style="vertical-align: bottom; padding: 4px 2px; border-top: 1px dashed #94a3b8 !important;"><?php echo htmlspecialchars($d['CUSTOMER_APROVE'] ? $d['CUSTOMER_APROVE'] : ''); ?></td>
                <td style="vertical-align: bottom; padding: 4px 2px; border-top: 1px dashed #94a3b8 !important;"><?php echo htmlspecialchars($d['CUSTOMER_CHECKED'] ? $d['CUSTOMER_CHECKED'] : ''); ?></td>
                <td style="vertical-align: bottom; padding: 4px 2px; border-top: 1px dashed #94a3b8 !important;"><?php echo htmlspecialchars($d['IMC_APROVE'] ? $d['IMC_APROVE'] : ''); ?></td>
                <td style="vertical-align: bottom; padding: 4px 2px; border-top: 1px dashed #94a3b8 !important;"><?php echo htmlspecialchars($d['IMC_CHECKED'] ? $d['IMC_CHECKED'] : ''); ?></td>
                <td style="vertical-align: bottom; padding: 4px 2px; border-top: 1px dashed #94a3b8 !important;"><?php echo htmlspecialchars($d['IMC_PREPARED'] ? $d['IMC_PREPARED'] : ''); ?></td>
            </tr>
        </table>
        
        <div style="font-size: 7.5pt; font-weight: bold; font-family: Arial; margin-top: 5px; text-align: left;">
            FM.EG.C.03-015-01 (REV.TGL.28/05/2026)
        </div>
    </div>

</body>
</html>
<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/database.php';

// Ambil Nomor Control dari URL parameter
$no = isset($_GET['no']) ? trim($_GET['no']) : '';

if ($no == '') {
    die("<div style='padding:20px; color:red; font-family:Arial;'><b>Error:</b> Control Number tidak ditemukan.</div>");
}

// Query presisi (menggunakan JOIN CUST agar data tidak gagal ditarik)
$sql = "SELECT P.*, 
        V.PART_NAME, V.PART_CODE AS PART_NO, V.PART_CODE, 
        C.CUST_COMP, 
        D.DEP_NAME,
        MAT.ITEM_NAME AS MATERIAL_NAME,
        MAT.ITEM_CODE AS MATERIAL_CODE
        FROM PROSES_CHANGE P
        LEFT JOIN ITEM_CUSTINFO_VIEW V ON P.ITEM_ID = V.ITEM_ID
        LEFT JOIN CUST C ON V.CUST_ID = C.CUST_ID
        LEFT JOIN DEPT D ON P.DEP_CODE = D.DEP_CODE
        LEFT JOIN ITEMS MAT ON P.MATERIAL_ID = MAT.ITEM_ID
        WHERE P.CONTROL_NO = ?";

$stmt = q($sql, array($no));
$d = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$d) {
    die("<div style='padding:20px; color:red; font-family:Arial;'><b>Error:</b> Data dengan nomor " . htmlspecialchars($no) . " tidak terdaftar di database.</div>");
}

function fTgl($date) {
    return ($date instanceof DateTime) ? $date->format('d-M-Y') : (!empty($date) ? date('d-M-Y', strtotime($date)) : '');
}

// Render simbol kotak centang agar saat di-print bentuknya konsisten
function renderBox($checked) {
    return ($checked == 1 || $checked === true || strtolower(trim(strval($checked))) === 'yes') 
        ? '<span style="font-size:12pt; font-family: Arial;">&#9745;</span>' 
        : '<span style="font-size:12pt; font-family: Arial;">&#9744;</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>EPSON 4M APPLICATION FORM - <?php echo htmlspecialchars($no); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Arial", sans-serif; font-size: 8pt; color: #000; margin: 0; padding: 0; background: #f1f5f9; }
        
        .no-print { background: #fff; padding: 12px; text-align: center; border-bottom: 1px solid #cbd5e1; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .no-print button { padding: 8px 16px; font-weight: bold; cursor: pointer; border-radius: 4px; border: none; }
        
        .report-page { width: 210mm; margin: 15px auto; padding: 5mm; background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        
        /* PENGATURAN GRID TABEL ABSOLUT */
        table.master-table { width: 100%; border-collapse: collapse; table-layout: fixed; border: 2px solid #000; }
        table.master-table td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
        
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        .bg-gray { background-color: #f0f0f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .title-doc { font-size: 14pt; font-weight: bold; letter-spacing: 0.5px; margin: 0; }
        .sub-box { border: 1px solid #000; padding: 3px; font-size: 7.5pt; text-align: center; }
        
        @media print {
            body { background: #fff; margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .report-page { width: 100%; margin: 0; padding: 0; border: none; box-shadow: none; }
            @page { margin: 5mm; size: A4 portrait; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="background: #2563eb; color: white;">Cetak / Print Epson Format</button>
        <button onclick="window.close()" style="background: #64748b; color: white;">Tutup Halaman</button>
    </div>

    <div class="report-page">
        <table class="master-table">
            <tr>
                <td colspan="2" style="width: 25%; border: none; padding: 6px;">
                    <div class="sub-box" style="margin-bottom: 4px; width: 90%;">COMPLETED BY PARTS ENG</div>
                    <div class="sub-box" style="width: 90%;">COMPLETED BY QA</div>
                </td>
                <td colspan="4" class="text-center" style="width: 50%; border: none; vertical-align: middle;">
                    <h2 class="title-doc">4M APPLICATION FORM</h2>
                </td>
                <td colspan="2" style="width: 25%; border: none; padding: 6px; text-align: right;">
                    <div style="font-size:14pt; font-weight:bold; margin-bottom:4px; padding-right:10px;">EPSON</div>
                    <div class="sub-box" style="width: 90%; float: right;">COMPLETED BY APPLICANT</div>
                </td>
            </tr>
            
            <tr>
                <td colspan="2">
                    <b>Date:</b> <?php echo fTgl($d['CONTROL_DATE1']); ?><br><br>
                    <b>Issue No:</b> <?php echo htmlspecialchars($d['CONTROL_NO']); ?>
                </td>
                <td colspan="2">
                    <div class="fw-bold mb-1">4M Category</div>
                    <div style="margin-bottom: 2px;"><?php echo renderBox($d['INTERNAL']); ?> Internal</div>
                    <div><?php echo renderBox($d['CUSTOMER']); ?> External</div>
                </td>
                <td colspan="4">
                    <div class="fw-bold mb-1">4M Type</div>
                    <table style="width:100%; border:none; margin-top:2px;">
                        <tr>
                            <td style="border:none; padding:0;"><?php echo renderBox($d['MAN']); ?> Man</td>
                            <td style="border:none; padding:0;"><?php echo renderBox($d['MACHINE']); ?> Machine</td>
                            <td style="border:none; padding:0;"><?php echo renderBox($d['METHOD']); ?> Method</td>
                            <td style="border:none; padding:0;"><?php echo renderBox($d['MATERIAL']); ?> Material</td>
                            <td style="border:none; padding:0;"><?php echo renderBox($d['OTHER']); ?> Others</td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr>
                <td colspan="8" class="text-center fw-bold bg-gray" style="padding: 4px;">Supplier Name: PT. IMC TEKNO INDONESIA</td>
            </tr>
            <tr>
                <td colspan="8" class="text-center fw-bold bg-gray" style="padding: 4px;">APPLICATION</td>
            </tr>
            <tr class="text-center bg-gray fw-bold">
                <td colspan="2" style="width: 25%;">Applicant Name<br>(Department)</td>
                <td colspan="2" style="width: 25%;">Application Date</td>
                <td colspan="1" style="width: 20%;">Activity Date</td>
                <td colspan="3" style="width: 30%;">Applicant Signature</td>
            </tr>
            <tr class="text-center">
                <td colspan="2" style="vertical-align: middle;">
                    <?php echo htmlspecialchars($d['PIC_NAME']); ?><br>
                    (<?php echo htmlspecialchars($d['DEP_NAME'] ? $d['DEP_NAME'] : $d['DEP_CODE']); ?>)
                </td>
                <td colspan="2" style="vertical-align: middle;"><?php echo fTgl($d['START_CHANGE']); ?></td>
                <td colspan="1" style="vertical-align: middle;"><?php echo fTgl($d['CLOSE_CHANGE']); ?></td>
                <td style="vertical-align: bottom; width:10%;">Prepared by<br><br><br><?php echo htmlspecialchars($d['IMC_PREPARED']); ?></td>
                <td style="vertical-align: bottom; width:10%;">Checked by<br><br><br><?php echo htmlspecialchars($d['IMC_CHECKED']); ?></td>
                <td style="vertical-align: bottom; width:10%;">Approved by<br><br><br><?php echo htmlspecialchars($d['IMC_APROVE']); ?></td>
            </tr>

            <tr class="text-center bg-gray fw-bold">
                <td colspan="2">Model</td>
                <td colspan="2">Part Name</td>
                <td colspan="2">Part Code (9-Digit)</td>
                <td colspan="2">Tool No. / CAV</td>
            </tr>
            <tr class="text-center">
                <td colspan="2"><?php echo htmlspecialchars($d['MODEL']); ?></td>
                <td colspan="2"><?php echo htmlspecialchars($d['PART_NAME']); ?></td>
                <td colspan="2"><?php echo htmlspecialchars($d['PART_CODE']); ?></td>
                <td colspan="2">-</td>
            </tr>

            <tr>
                <td colspan="4" style="height: 60px;">
                    <div class="fw-bold bg-gray" style="border-bottom: 1px solid #000; margin: -3px -5px 3px -5px; padding: 2px 5px;">Content 4M:</div>
                    <?php echo nl2br(htmlspecialchars($d['AFT_CHANGE'])); ?>
                </td>
                <td colspan="4" style="height: 60px;">
                    <div class="fw-bold bg-gray" style="border-bottom: 1px solid #000; margin: -3px -5px 3px -5px; padding: 2px 5px;">Reason for Application:</div>
                    <?php echo nl2br(htmlspecialchars($d['REASON'])); ?>
                </td>
            </tr>

            <tr>
                <td colspan="8" class="text-center fw-bold bg-gray" style="padding: 4px;">Treatment Stock</td>
            </tr>
            <tr>
                <td colspan="3" style="width: 33.3%; padding: 0;">
                    <div class="text-center fw-bold bg-gray" style="border-bottom: 1px solid #000; padding: 2px;">Old Stock</div>
                    <div style="padding: 4px; height: 50px;">Quantity: <br>Location: <br>Remark: </div>
                </td>
                <td colspan="2" style="width: 33.3%; padding: 0;">
                    <div class="text-center fw-bold bg-gray" style="border-bottom: 1px solid #000; padding: 2px;">4M Stock</div>
                    <div style="padding: 4px; height: 50px;">Quantity: <br>Location: <br>Remark: </div>
                </td>
                <td colspan="3" style="width: 33.3%; padding: 0;">
                    <div class="text-center fw-bold bg-gray" style="border-bottom: 1px solid #000; padding: 2px;">New Stock</div>
                    <div style="padding: 4px; height: 50px;">Quantity: <br>Location: <br>Remark: </div>
                </td>
            </tr>

            <tr class="text-center bg-gray fw-bold">
                <td colspan="4" style="width: 50%;">Improvement Plan</td>
                <td colspan="4" style="width: 50%;">Requirement for approval</td>
            </tr>
            <tr>
                <td colspan="4" style="height: 120px;">
                    <?php echo nl2br(htmlspecialchars($d['PE_REMARK'])); ?>
                </td>
                <td colspan="4">
                    <table style="width:100%; border:none;">
                        <tr>
                            <td style="border:none; width:50%; line-height: 1.5;">
                                <?php echo renderBox($d['SAMPLE']); ?> Sample Part<br>
                                <?php echo renderBox($d['DATA']); ?> Inspection Result<br>
                                <?php echo renderBox(0); ?> QCPC<br>
                                <?php echo renderBox(0); ?> Stock Simulation<br>
                                <?php echo renderBox(0); ?> Actual Sample Part<br>
                                <?php echo renderBox(0); ?> Working Instruction<br>
                                <?php echo renderBox(0); ?> Data Capability<br>
                                <?php echo renderBox(0); ?> Trial Report
                            </td>
                            <td style="border:none; width:50%; line-height: 1.5;">
                                <?php echo renderBox(0); ?> MSDS & ChemSherpa<br>
                                <?php echo renderBox(0); ?> M/C Parameter<br>
                                <?php echo renderBox(0); ?> Packing Standard<br><br>
                                <b>QA Entry Date:</b><br>
                                _____________
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr>
                <td colspan="8">
                    <div class="fw-bold bg-gray" style="border-bottom: 1px solid #000; margin: -3px -5px 3px -5px; padding: 2px 5px;">COMMENTS:</div>
                    <div style="height: 35px;"><?php echo htmlspecialchars($d['CUSTOMER_COMMENT']); ?></div>
                </td>
            </tr>
            <tr class="text-center bg-gray fw-bold">
                <td colspan="8">SUPPLIER APPROVAL (INTERNAL)</td>
            </tr>
            <tr class="text-center">
                <td colspan="2">Received Date<br><br>_____________</td>
                <td colspan="2" style="vertical-align: middle;">
                    <div><?php echo renderBox($d['CUSTOMER_JUDGEMENT'] == 1); ?> Judgement: OK</div>
                    <div><?php echo renderBox($d['CUSTOMER_JUDGEMENT'] === 0); ?> NG</div>
                </td>
                <td colspan="2">Judgement date<br><br>_____________</td>
                <td colspan="2" style="padding:0;">
                    <table style="width:100%; height:100%; border:none;">
                        <tr class="text-center bg-gray" style="font-size: 7pt;">
                            <td style="border-top:none; border-left:none;">Prepared</td>
                            <td style="border-top:none;">Checked</td>
                            <td style="border-top:none; border-right:none;">Approved</td>
                        </tr>
                        <tr>
                            <td style="border-left:none; border-bottom:none; height: 30px;"></td>
                            <td style="border-bottom:none;"></td>
                            <td style="border-right:none; border-bottom:none;"></td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr class="text-center bg-gray fw-bold">
                <td colspan="4">Distribution</td>
                <td colspan="2">Necessary / Not</td>
                <td colspan="2">Check</td>
            </tr>
            <tr>
                <td colspan="4" style="line-height: 1.5;">
                    <?php echo renderBox(1); ?> QC & QA<br>
                    <?php echo renderBox(1); ?> Mold Shop<br>
                    <?php echo renderBox(1); ?> Engineering<br>
                    <?php echo renderBox(1); ?> Production
                </td>
                <td colspan="2" style="line-height: 1.5;">
                    <?php echo renderBox(0); ?> Maintenance<br>
                    <?php echo renderBox(0); ?> WH & Delivery<br>
                    <?php echo renderBox(0); ?> HRGA<br>
                    <?php echo renderBox(0); ?> Sales & Purchasing
                </td>
                <td colspan="2" style="vertical-align: middle;">
                    <?php echo renderBox($d['NEED_CUSTOMER']); ?> Customer Approval
                </td>
            </tr>

            <tr class="text-center bg-gray fw-bold">
                <td colspan="8">IEI APPROVAL (EXTERNAL)</td>
            </tr>
            <tr>
                <td colspan="3" style="padding: 0; height: 60px;">
                    <div class="fw-bold bg-gray" style="border-bottom: 1px solid #000; padding: 2px 5px;">Reference Document for Approval</div>
                    <div style="padding: 4px;">Evaluation Document No:<br>Line Trial Document No:</div>
                </td>
                <td colspan="2" style="padding: 0;">
                    <div class="fw-bold bg-gray" style="border-bottom: 1px solid #000; padding: 2px 5px;">PARTS ENGINEERING COMMENTS:</div>
                </td>
                <td colspan="3" style="padding: 0;">
                    <div class="text-center fw-bold bg-gray" style="border-bottom: 1px solid #000; padding: 2px;">IEI APPROVED BY</div>
                    <table style="width:100%; height:100%; border:none;">
                        <tr class="text-center" style="font-size: 7pt;">
                            <td style="border-top:none; border-left:none;">Prepared</td>
                            <td style="border-top:none;">Checked</td>
                            <td style="border-top:none; border-right:none;">Approved</td>
                        </tr>
                        <tr>
                            <td style="border-left:none; border-bottom:none; height: 35px;"></td>
                            <td style="border-bottom:none;"></td>
                            <td style="border-right:none; border-bottom:none;"></td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="text-center fw-bold bg-gray" style="padding: 6px;">
                <td colspan="3"><?php echo renderBox(0); ?> Evaluation OK &nbsp;&nbsp;&nbsp; <?php echo renderBox(0); ?> NG</td>
                <td colspan="2"><?php echo renderBox(0); ?> Line Trial OK &nbsp;&nbsp;&nbsp; <?php echo renderBox(0); ?> NG</td>
                <td colspan="3"><?php echo renderBox(0); ?> FINAL OK &nbsp;&nbsp;&nbsp; <?php echo renderBox(0); ?> JUDGEMENT NG</td>
            </tr>
        </table>
    </div>
</body>
</html>
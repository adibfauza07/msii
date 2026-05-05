<?php
// FILE: msii/pe/cetak_trial_pe.php

// Meredam error bawaan PHP agar tidak merusak layout PDF/Cetak
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);

require_once '../config/database_p1.php';

$code = isset($_GET['code']) ? intval($_GET['code']) : 0;

if ($code == 0) {
    die("Kode Trial tidak valid!");
}

// 1. Ambil Data Master Trial
$sql = "SELECT T.*, 
        I.ITEM_NAME AS PART_NAME, 
        C.CUST_COMP, 
        MAT.ITEM_NAME AS MAT_NAME,
        STD.WEIGHT_PART_STD, STD.WEIGHT_RUNNER_STD, STD.CYCLE_TIME_STD, STD.TONAGE_STD, STD.CAVITY_STD,
        J.JUDGE_TRIAL
        FROM TRIAL_PE T
        LEFT JOIN ITEMS I ON T.PART_CODE = I.ITEM_CODE
        LEFT JOIN CUST C ON T.CUST_ID = C.CUST_ID
        LEFT JOIN ITEMS MAT ON T.MAT_USING = MAT.ITEM_ID
        LEFT JOIN TRIAL_PE_STD STD ON T.PART_CODE = STD.ITEM_CODE
        LEFT JOIN JUDGE_TRIAL J ON T.JUDGE_ID = J.ID
        WHERE T.TRIAL_CODE = ?";

$stmt = sqlsrv_query($conn, $sql, array($code));
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$data) {
    die("Data Trial tidak ditemukan!");
}

// Format Tanggal
$tgl_trial = '-';
if (isset($data['DATE'])) {
    $tgl_trial = $data['DATE'] instanceof DateTime ? $data['DATE']->format('d M Y') : date('d M Y', strtotime($data['DATE']));
}

// 2. Ambil Data Actual Cavity Weight
$sqlAct = "SELECT * FROM Trial_PE_WPart_ACT WHERE Trial_CODE = ?";
$stmtAct = sqlsrv_query($conn, $sqlAct, array($code));
$act_weights = [];
$total_cavity_weight = 0;
if($stmtAct){
    while($rowA = sqlsrv_fetch_array($stmtAct, SQLSRV_FETCH_ASSOC)){
        $act_weights[] = $rowA;
        $total_cavity_weight += floatval($rowA['Weight_Part_Actual']);
    }
}
$total_weight = $total_cavity_weight + floatval(isset($data['WEIGHT_RUNNER']) ? $data['WEIGHT_RUNNER'] : 0);

// Siapkan 8 slot untuk Cavity Weight
$cavs = [];
for($i=0; $i<8; $i++) {
    $cavs[] = isset($act_weights[$i]) ? $act_weights[$i]['Weight_Part_Actual'] : '';
}

// Helper untuk Checkbox agar kebal error jika data kosong
function chk($val) { 
    $v = trim((string)$val) == 'V' ? 'V' : (trim((string)$val) == 'X' ? 'X' : '&nbsp;');
    return "<div class='chk-box'>{$v}</div>"; 
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Trial Report #<?= $code ?></title>
    <style>
        @page { size: A4; margin: 10mm; }
        body { font-family: 'Arial', sans-serif; font-size: 9px; color: #000; background: #fff; margin:0; padding:0; line-height: 1.2; }
        .wrapper { width: 100%; max-width: 780px; margin: 0 auto; }
        .bold { font-weight: bold; }
        .text-center { text-align: center; }
        table.bordered { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        table.bordered th, table.bordered td { border: 1px solid #000; padding: 2px 4px; vertical-align: top; }
        table.noborder { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 9px; }
        table.noborder td { padding: 3px 2px; vertical-align: bottom; }
        .label-col { font-weight: bold; width: 18%; text-transform: uppercase; }
        .colon-col { width: 2%; text-align: center; font-weight: bold; }
        .val-col { border-bottom: 1px solid #000; }
        .spacer-col { width: 4%; }
        .chk-box { display: inline-block; width: 12px; height: 12px; border: 1px solid #000; text-align: center; line-height: 12px; font-weight: bold; font-size: 9px; vertical-align: middle; background: #fff; margin-left: 2px;}
        .photo-box { border: 1px solid #ccc; width: 100%; height: 90px; display: flex; align-items: center; justify-content: center; color: #ccc; margin-top: 2px; }
        .photo-box img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .sig-table { border-collapse: collapse; text-align: center; }
        .sig-table td { border: 1px solid #000; padding: 2px; height: 40px; vertical-align: bottom; width: 33.33%; font-weight: bold;}
        .sig-table th { border: 1px solid #000; padding: 2px; background: #fff;}
        .flex-container { display: flex; justify-content: space-between; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

<div class="wrapper">
    <div class="no-print" style="text-align: right; margin-bottom: 10px;">
        <button onclick="window.print()" style="padding: 8px 15px; background: #3498db; color: #fff; border: none; cursor: pointer; font-weight: bold;">🖨️ Cetak Report</button>
    </div>

    <div class="flex-container" style="margin-bottom: 10px;">
        <div class="bold" style="font-size: 11px;">
            PT. IMC TEKNO INDONESIA<br>
            DEPT. PRODUCT ENGINEERING
        </div>
        <div class="text-center" style="flex-grow: 1;">
            <div class="bold" style="font-size: 16px; margin-bottom: 5px;">TRIAL REPORT</div>
        </div>
        <div style="width: 150px;"></div>
    </div>
    
    <div style="margin-bottom: 5px; font-size: 9px;">
        <span class="bold">DATE :</span> <span style="display:inline-block; width: 150px; border-bottom: 1px solid #000; padding-left: 5px;"><?= $tgl_trial ?></span>
    </div>

    <table class="noborder">
        <tr>
            <td class="label-col">CUSTOMER</td><td class="colon-col">:</td>
            <td class="val-col" style="width: 25%;"><?= isset($data['CUST_COMP']) ? htmlspecialchars($data['CUST_COMP']) : '' ?></td>
            <td class="spacer-col"></td>
            <td class="label-col" style="width: 20%;">MATERIAL NAME, COLOUR</td><td class="colon-col">:</td>
            <td class="val-col" style="width: 25%;"><?= isset($data['MAT_NAME']) ? htmlspecialchars($data['MAT_NAME']) : '' ?></td>
        </tr>
        <tr>
            <td class="label-col">PART NAME</td><td class="colon-col">:</td>
            <td class="val-col"><?= isset($data['PART_NAME']) ? htmlspecialchars($data['PART_NAME']) : '' ?></td>
            <td class="spacer-col"></td>
            <td class="label-col">MATERIAL USING</td><td class="colon-col">:</td>
            <td class="val-col">Virgin: <?= (100 - floatval(isset($data['REGRIND_PCT']) ? $data['REGRIND_PCT'] : 0)) ?> % &nbsp;&nbsp;|&nbsp;&nbsp; Regrind: <?= floatval(isset($data['REGRIND_PCT']) ? $data['REGRIND_PCT'] : 0) ?> %</td>
        </tr>
        <tr>
            <td class="label-col">PART NO.</td><td class="colon-col">:</td>
            <td class="val-col"><?= isset($data['PART_CODE']) ? htmlspecialchars($data['PART_CODE']) : '' ?></td>
            <td class="spacer-col"></td>
            <td class="label-col">MATERIAL DRYING TIME</td><td class="colon-col">:</td>
            <td class="val-col"><?= isset($data['MAT_DRYING_TIME']) ? $data['MAT_DRYING_TIME'] : '' ?> Hours</td>
        </tr>
        <tr>
            <td class="label-col">PART CODE</td><td class="colon-col">:</td>
            <td class="val-col"><?= isset($data['PART_CODE']) ? htmlspecialchars($data['PART_CODE']) : '' ?></td>
            <td class="spacer-col"></td>
            <td class="label-col">MOLD SET UP / SET DOWN</td><td class="colon-col">:</td>
            <td class="val-col"><?= isset($data['MOLD_SET_UP']) ? $data['MOLD_SET_UP'] : '' ?> / <?= isset($data['MOLD_SET_DOWN']) ? $data['MOLD_SET_DOWN'] : '' ?> Minutes</td>
        </tr>
        <tr>
            <td class="label-col">QUANTITY TRIAL</td><td class="colon-col">:</td>
            <td class="val-col"><?= isset($data['QUANTITY_TRIAL']) ? $data['QUANTITY_TRIAL'] : '' ?> Shots</td>
            <td class="spacer-col"></td>
            <td class="label-col">TRIAL DURATION</td><td class="colon-col">:</td>
            <td class="val-col"><?= isset($data['TRIAL_DURATION']) ? $data['TRIAL_DURATION'] : '' ?> Min</td>
        </tr>
        <tr>
            <td class="label-col">CYCLE TIME</td><td class="colon-col">:</td>
            <td class="val-col">STD: <?= isset($data['CYCLE_TIME_STD']) ? $data['CYCLE_TIME_STD'] : '' ?> &nbsp;&nbsp; ACT: <?= isset($data['CYCLE_TIME_ACT']) ? $data['CYCLE_TIME_ACT'] : '' ?> Second</td>
            <td class="spacer-col"></td>
            <td class="label-col">OPERATION</td><td class="colon-col">:</td>
            <td class="val-col"><?= isset($data['OPERATION']) ? htmlspecialchars($data['OPERATION']) : '-' ?></td>
        </tr>
        <tr>
            <td class="label-col">M/C TONAGE</td><td class="colon-col">:</td>
            <td class="val-col">STD: <?= isset($data['TONAGE_STD']) ? $data['TONAGE_STD'] : '' ?> &nbsp;&nbsp; ACT: <?= isset($data['TONAGE']) ? $data['TONAGE'] : '' ?></td>
            <td class="spacer-col"></td>
            <td class="label-col">TRIAL REASON</td><td class="colon-col">:</td>
            <td class="val-col"><?= isset($data['TRIAL_REASON']) ? htmlspecialchars($data['TRIAL_REASON']) : '' ?></td>
        </tr>
    </table>

    <table class="bordered text-center">
        <tr>
            <td colspan="8" class="bold text-center" style="padding: 2px;">WEIGHT PART/PCS</td>
            <td rowspan="2" class="bold" style="vertical-align: middle; width: 15%;">CAVITY NUMBER</td>
            <td rowspan="2" class="bold" style="vertical-align: middle; width: 15%;">RUNNER<br>WEIGHT</td>
            <td rowspan="2" class="bold" style="vertical-align: middle; width: 15%;">TOTAL WEIGHT</td>
        </tr>
        <tr>
            <?php foreach($cavs as $c): ?>
            <td style="height: 25px; width: 6.8%; vertical-align: bottom; font-size: 8px;">
                <span style="font-size: 9px;"><?= $c ?></span><br>gr
            </td>
            <?php endforeach; ?>
        </tr>
        <tr>
            <td colspan="8" class="bold">TOTAL PART &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <?= $total_cavity_weight ?> gr</td>
            <td class="bold text-center" style="font-size: 12px;"><?= count($act_weights) > 0 ? count($act_weights) : (isset($data['CAVITY_STD']) ? $data['CAVITY_STD'] : 0) ?></td>
            <td class="bold"><?= floatval(isset($data['WEIGHT_RUNNER']) ? $data['WEIGHT_RUNNER'] : 0) ?> gr</td>
            <td class="bold"><?= $total_weight ?> gr</td>
        </tr>
    </table>

    <table class="bordered">
        <tr>
            <td class="bold text-center" style="width: 25%;">PICTURE OF PART</td>
            <td class="bold text-center" style="width: 60%;">QUALITY ENGINEERING COMMENT</td>
            <td class="bold text-center" style="width: 15%;">JUDGMENT</td>
        </tr>
        <tr>
            <td style="padding: 5px;">
                <div class="photo-box" style="height: 120px;">
                    <?php if(!empty($data['foto'])): ?><img src="../assets/foto_trial/<?= $data['foto'] ?>"><?php else: ?>No Picture<?php endif; ?>
                </div>
            </td>
            <td style="padding: 5px; position: relative;">
                <div style="min-height: 20px; font-style: italic; border-bottom: 1px dashed #999; margin-bottom: 5px;">
                    <?= isset($data['QE_COMMENT']) ? htmlspecialchars($data['QE_COMMENT']) : '' ?>
                </div>
                <div class="flex-container">
                    <div style="width: 48%;">
                        <div class="bold">*APPEARANCE :</div>
                        <table style="width: 100%; border:none; font-size:8px;">
                            <tr><td style="border:none;">&bull; NO BURRY</td><td style="border:none; text-align:right;">OK (V) / NG (X) <?= chk(isset($data['CHK_BURRY']) ? $data['CHK_BURRY'] : '') ?></td></tr>
                            <tr><td style="border:none;">&bull; NO SHORTMOLD</td><td style="border:none; text-align:right;">OK (V) / NG (X) <?= chk(isset($data['CHK_SHORTMOLD']) ? $data['CHK_SHORTMOLD'] : '') ?></td></tr>
                            <tr><td style="border:none;">&bull; NO BURNING</td><td style="border:none; text-align:right;">OK (V) / NG (X) <?= chk(isset($data['CHK_BURNING']) ? $data['CHK_BURNING'] : '') ?></td></tr>
                            <tr><td style="border:none;">&bull; NO DENTED</td><td style="border:none; text-align:right;">OK (V) / NG (X) <?= chk(isset($data['CHK_DENTED']) ? $data['CHK_DENTED'] : '') ?></td></tr>
                            <tr><td style="border:none;">&bull; NO SCRATCH</td><td style="border:none; text-align:right;">OK (V) / NG (X) <?= chk(isset($data['CHK_SCRATCH']) ? $data['CHK_SCRATCH'] : '') ?></td></tr>
                        </table>
                    </div>
                    <div style="width: 48%; padding-top: 10px;">
                        <table style="width: 100%; border:none; font-size:8px;">
                            <tr><td style="border:none;">&bull; NO VOID</td><td style="border:none; text-align:right;">OK (V) / NG (X) <?= chk(isset($data['CHK_VOID']) ? $data['CHK_VOID'] : '') ?></td></tr>
                            <tr><td style="border:none;">&bull; NO WELD LINE</td><td style="border:none; text-align:right;">OK (V) / NG (X) <?= chk(isset($data['CHK_WELDLINE']) ? $data['CHK_WELDLINE'] : '') ?></td></tr>
                            <tr><td style="border:none;">&bull; NO SINK MARK</td><td style="border:none; text-align:right;">OK (V) / NG (X) <?= chk(isset($data['CHK_SINKMARK']) ? $data['CHK_SINKMARK'] : '') ?></td></tr>
                            <tr><td style="border:none;">&bull; NO SILVER MARK</td><td style="border:none; text-align:right;">OK (V) / NG (X) <?= chk(isset($data['CHK_SILVER']) ? $data['CHK_SILVER'] : '') ?></td></tr>
                        </table>
                    </div>
                </div>
                <div style="margin-top: 5px;">
                    <span class="bold">DIMENSION :</span> &nbsp;&nbsp;&nbsp; OK (V) / NG (X) <?= chk('') ?>
                </div>
                <div style="position: absolute; bottom: 5px; left: 5px; right: 5px; font-size: 8px;">
                    *Remark : <?= isset($data['PE_COMMENT']) ? htmlspecialchars($data['PE_COMMENT']) : '' ?>
                </div>
            </td>
            <td class="text-center" style="position: relative;">
                <div style="font-size: 18px; font-weight: bold; border: 2px solid #000; padding: 10px; display: inline-block; margin-top: 10px; width: 70%;">
                    <?= isset($data['JUDGE_TRIAL']) ? $data['JUDGE_TRIAL'] : '...' ?>
                </div>
                <div style="position: absolute; bottom: 5px; left: 5px; text-align: left; font-size: 9px;">
                    PIC: <span class="bold"><?= isset($data['PIC']) ? htmlspecialchars($data['PIC']) : '...' ?></span>
                </div>
            </td>
        </tr>
    </table>

    <table class="bordered">
        <tr>
            <td class="bold text-center" style="width: 25%;">PICTURE OF MATERIAL BAG</td>
            <td class="bold text-center" style="width: 50%;">PICTURE OF MOLD</td>
            <td class="bold text-center" style="width: 25%;">CATEGORY OF MACHINE</td>
        </tr>
        <tr>
            <td style="padding: 5px;">
                <div class="photo-box">
                    <?php if(!empty($data['foto_material'])): ?><img src="../assets/foto_trial/<?= $data['foto_material'] ?>"><?php else: ?>No Picture<?php endif; ?>
                </div>
            </td>
            <td style="padding: 0;">
                <div style="display: flex; height: 100%;">
                    <div style="width: 35%; padding: 3px; display: flex; flex-direction: column;">
                        <span class="bold" style="font-size: 8px;">* Core :</span>
                        <div class="photo-box" style="flex-grow: 1;">
                            <?php if(!empty($data['foto_mold_core'])): ?><img src="../assets/foto_trial/<?= $data['foto_mold_core'] ?>"><?php else: ?>No Picture<?php endif; ?>
                        </div>
                    </div>
                    <div style="width: 35%; padding: 3px; display: flex; flex-direction: column;">
                        <span class="bold" style="font-size: 8px;">* Cavity :</span>
                        <div class="photo-box" style="flex-grow: 1;">
                            <?php if(!empty($data['foto_mold_cavity'])): ?><img src="../assets/foto_trial/<?= $data['foto_mold_cavity'] ?>"><?php else: ?>No Picture<?php endif; ?>
                        </div>
                    </div>
                    <div style="width: 30%; border-left: 1px solid #000; padding: 3px; font-size: 8px;">
                        <div class="bold text-center" style="margin-bottom: 2px;">YA (V) / TIDAK (X)</div>
                        <table style="width: 100%; border:none;">
                            <tr><td style="border:none; padding:1px;">&bull; Ejector Jam</td><td style="border:none; padding:1px; text-align:right;"><?= chk('') ?></td></tr>
                            <tr><td style="border:none; padding:1px;">&bull; Runner Stuck</td><td style="border:none; padding:1px; text-align:right;"><?= chk('') ?></td></tr>
                            <tr><td style="border:none; padding:1px;">&bull; Part Stuck</td><td style="border:none; padding:1px; text-align:right;"><?= chk('') ?></td></tr>
                            <tr><td style="border:none; padding:1px;">&bull; Cooling Leakage</td><td style="border:none; padding:1px; text-align:right;"><?= chk('') ?></td></tr>
                            <tr><td style="border:none; padding:1px;">&bull; Undercut Mold</td><td style="border:none; padding:1px; text-align:right;"><?= chk('') ?></td></tr>
                            <tr><td style="border:none; padding:1px;">&bull; Slider Jam</td><td style="border:none; padding:1px; text-align:right;"><?= chk('') ?></td></tr>
                            <tr><td style="border:none; padding:1px;">&bull; Mold Can't Clamping</td><td style="border:none; padding:1px; text-align:right;"><?= chk('') ?></td></tr>
                            <tr><td style="border:none; padding:1px;">&bull; Nipple Complete</td><td style="border:none; padding:1px; text-align:right;"><?= chk('') ?></td></tr>
                        </table>
                    </div>
                </div>
            </td>
            <td style="padding: 3px; font-size: 8px;">
                <div class="bold text-center" style="margin-bottom: 2px;">YA (V) / TIDAK (X)</div>
                <table style="width: 100%; border:none;">
                    <tr><td style="border:none; padding:1px;">&bull; BACKFLOW</td><td style="border:none; padding:1px; text-align:right;"><?= chk('') ?></td></tr>
                    <tr><td style="border:none; padding:1px;">&bull; ROBOT</td><td style="border:none; padding:1px; text-align:right;"><?= chk('') ?></td></tr>
                    <tr><td style="border:none; padding:1px;">&bull; HEATER BARREL</td><td style="border:none; padding:1px; text-align:right;"><?= chk('') ?></td></tr>
                    <tr><td style="border:none; padding:1px;">&bull; CONVEYOR</td><td style="border:none; padding:1px; text-align:right;"><?= chk('') ?></td></tr>
                    <tr><td style="border:none; padding:1px;">&bull; MTC</td><td style="border:none; padding:1px; text-align:right;"><?= chk('') ?></td></tr>
                    <tr><td style="border:none; padding:1px;">&bull; HEATER CONTROL</td><td style="border:none; padding:1px; text-align:right;"><?= chk('') ?></td></tr>
                </table>
                <div style="margin-top: 5px;">*Remark :</div>
            </td>
        </tr>
    </table>

    <table class="bordered">
        <tr>
            <td class="bold text-center" style="width: 25%;">PROBLEM</td>
            <td class="bold text-center" style="width: 25%;">ANALYSIS</td>
            <td class="bold text-center" style="width: 50%;">PICTURE OF MACHINE STATISTIC</td>
        </tr>
        <tr>
            <td style="height: 50px;"></td> 
            <td><?= nl2br(htmlspecialchars(isset($data['ANALYSIS']) ? $data['ANALYSIS'] : (isset($data['ANALYSYS']) ? $data['ANALYSYS'] : ''))) ?></td>
            <td rowspan="3" style="padding: 5px;">
                <div class="photo-box" style="height: 90%;">No Picture</div>
            </td>
        </tr>
        <tr>
            <td colspan="2" class="bold text-center">CORRECTIVE ACTION</td>
        </tr>
        <tr>
            <td colspan="2" style="height: 50px;"><?= nl2br(htmlspecialchars(isset($data['CORRECTIVE_ACTION']) ? $data['CORRECTIVE_ACTION'] : '')) ?></td>
        </tr>
    </table>

    <div class="flex-container" style="margin-top: 5px;">
        <table class="sig-table" style="width: 60%;">
            <tr><th colspan="3">PRODUCT ENGINEERING</th></tr>
            <tr>
                <td style="height: 15px; font-size:8px;">PREPARED</td>
                <td style="height: 15px; font-size:8px;">CHECKED</td>
                <td style="height: 15px; font-size:8px;">APPROVED</td>
            </tr>
            <tr>
                <td><?= isset($data['PREPARED']) ? htmlspecialchars($data['PREPARED']) : '' ?></td>
                <td><?= isset($data['CHECKED']) ? htmlspecialchars($data['CHECKED']) : '' ?></td>
                <td><?= isset($data['APPROVED']) ? htmlspecialchars($data['APPROVED']) : '' ?></td>
            </tr>
        </table>
        
        <table class="sig-table" style="width: 38%;">
            <tr>
                <th style="width: 50%;">KNOWLEDGE</th>
                <th style="width: 50%;">PPIC</th>
            </tr>
            <tr>
                <td style="height: 55px;"></td>
                <td style="height: 55px;"></td>
            </tr>
        </table>
    </div>
    
    <div style="font-size: 8px; font-weight: bold; margin-top: 2px;">
        FM.EG.C03-006 (Rev-2, 24 Feb 26)
    </div>

</div>

</body>
</html>
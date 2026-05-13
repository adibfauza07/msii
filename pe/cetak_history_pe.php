<?php
// msii/pe/cetak_history_pe.php
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);
require_once '../config/database_p1.php';

// 1. Tangkap Parameter Filter dari Dashboard
$start = !empty($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$end   = !empty($_GET['end']) ? $_GET['end'] : date('Y-m-t'); 
$filter_part = isset($_GET['filter_part']) ? trim($_GET['filter_part']) : '';
$filter_cust = isset($_GET['filter_cust']) ? trim($_GET['filter_cust']) : '';

// 2. Query mencari Part apa saja yang memiliki riwayat trial di rentang filter ini
$sqlParts = "SELECT DISTINCT T.PART_CODE, I.ITEM_NAME, I.ITEM_NO, C.CUST_COMP, MAT.ITEM_NAME AS MAT_NAME,
             STD.WEIGHT_PART_STD, STD.WEIGHT_RUNNER_STD, STD.CYCLE_TIME_STD, STD.TONAGE_STD, STD.CAVITY_STD
             FROM TRIAL_PE T
             LEFT JOIN ITEMS I ON T.PART_CODE = I.ITEM_CODE
             LEFT JOIN CUST C ON T.CUST_ID = C.CUST_ID
             LEFT JOIN ITEMS MAT ON T.MAT_USING = MAT.ITEM_ID
             LEFT JOIN TRIAL_PE_STD STD ON T.PART_CODE = STD.ITEM_CODE
             WHERE T.DATE BETWEEN ? AND ?";
$params = array($start, $end);

if (!empty($filter_part)) { $sqlParts .= " AND I.ITEM_NAME LIKE ? "; $params[] = "%$filter_part%"; }
if (!empty($filter_cust)) { $sqlParts .= " AND C.CUST_COMP LIKE ? "; $params[] = "%$filter_cust%"; }
$sqlParts .= " ORDER BY T.PART_CODE";

$stmtP = sqlsrv_query($conn, $sqlParts, $params);
$parts = [];
if($stmtP) { while($row = sqlsrv_fetch_array($stmtP, SQLSRV_FETCH_ASSOC)) { $parts[] = $row; } }

if (count($parts) == 0) { die("<h2 style='text-align:center; font-family:Arial; margin-top:50px;'>Tidak ada riwayat trial pada filter ini.</h2>"); }

// Fungsi Helper untuk merangkum semua NG Checklist menjadi sebuah kalimat
function getIssueSummary($r) {
    $ng = [];
    $checks = [
        'CHK_BURRY'=>'Burry', 'CHK_VOID'=>'Void', 'CHK_SHORTMOLD'=>'Shortmold', 'CHK_WELDLINE'=>'Weldline', 
        'CHK_BURNING'=>'Burning', 'CHK_SINKMARK'=>'Sinkmark', 'CHK_DENTED'=>'Dented', 'CHK_SILVER'=>'Silvermark', 
        'CHK_SCRATCH'=>'Scratch', 'CHK_DIMENSION'=>'Dimension NG', 'CHK_EJECTOR_JAM'=>'Ejector Jam', 
        'CHK_RUNNER_STUCK'=>'Runner Stuck', 'CHK_PART_STUCK'=>'Part Stuck', 'CHK_COOLING_LEAKAGE'=>'Cooling Leak', 
        'CHK_UNDERCUT_MOLD'=>'Undercut', 'CHK_SLIDER_JAM'=>'Slider Jam', 'CHK_MOLD_CLAMPING'=>'Clamping Issue'
    ];
    foreach($checks as $col => $lbl) {
        if(isset($r[$col]) && trim($r[$col]) === 'X') $ng[] = $lbl;
    }
    if(count($ng) == 0) return "OK (No Issue)";
    return "NG: " . implode(", ", $ng);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>History By Item - PE Report</title>
    <style>
        /* Menggunakan Layout Landscape A4 */
        @page { size: A4 landscape; margin: 8mm; }
        body { font-family: 'Arial', sans-serif; font-size: 9px; color: #000; background: #fff; margin:0; padding:0; }
        .wrapper { width: 100%; max-width: 1080px; margin: 0 auto; }
        .page-break { page-break-after: always; }
        .page-break:last-child { page-break-after: auto; }
        
        .bold { font-weight: bold; }
        .text-center { text-align: center; }
        
        /* Layout Header 3 Kolom */
        .header-container { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; font-size: 10px;}
        .col-left, .col-mid { width: 35%; }
        .col-right { width: 28%; }
        
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { padding: 2px; vertical-align: top; border: none; }
        
        /* Signature Table */
        .sig-table { width: 100%; border-collapse: collapse; text-align: center; }
        .sig-table th, .sig-table td { border: 1px solid #000; padding: 2px; }
        .sig-table td { height: 40px; vertical-align: bottom; }
        
        /* Main History Table */
        .data-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 4px; vertical-align: middle; }
        .data-table th { background-color: #f0f0f0; text-transform: uppercase; font-size: 8px;}
        .data-table td { font-size: 8.5px; }
        
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

    <div class="no-print text-center" style="padding: 15px; background: #f8f9fa; border-bottom: 2px solid #ddd; margin-bottom: 15px;">
        <h3 style="margin: 0; font-family: Arial;">History Trial (Ditemukan <?= count($parts) ?> Part)</h3>
        <button onclick="window.print()" style="margin-top: 10px; padding: 10px 20px; background: #3498db; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">🖨️ Cetak / Save to PDF</button>
    </div>

    <?php foreach ($parts as $p): ?>
    <div class="wrapper page-break">
        
        <div class="header-container">
            <div class="col-left">
                <table class="header-table">
                    <tr><td width="35%">PART CODE</td><td width="5%">:</td><td class="bold"><?= htmlspecialchars($p['PART_CODE']) ?></td></tr>
                    <tr><td>PART NAME</td><td>:</td><td class="bold"><?= htmlspecialchars($p['ITEM_NAME']) ?></td></tr>
                    <tr><td>PART NO</td><td>:</td><td><?= htmlspecialchars($p['ITEM_NO']) ?></td></tr>
                    <tr><td>MATERIAL NAME</td><td>:</td><td><?= htmlspecialchars($p['MAT_NAME']) ?></td></tr>
                    <tr><td>CUSTOMER</td><td>:</td><td><?= htmlspecialchars($p['CUST_COMP']) ?></td></tr>
                </table>
            </div>
            
            <div class="col-mid">
                <table class="header-table">
                    <tr><td width="45%">Standar Tonage</td><td width="5%">:</td><td class="bold"><?= $p['TONAGE_STD'] ?> Ton</td></tr>
                    <tr><td>Standar Cavity</td><td>:</td><td class="bold"><?= $p['CAVITY_STD'] ?></td></tr>
                    <tr><td>Standar Cycle Time</td><td>:</td><td class="bold"><?= $p['CYCLE_TIME_STD'] ?> Second</td></tr>
                    <tr><td>Standar Part Weight</td><td>:</td><td class="bold"><?= $p['WEIGHT_PART_STD'] ?> gr</td></tr>
                    <tr><td>Standar Runner Weight</td><td>:</td><td class="bold"><?= $p['WEIGHT_RUNNER_STD'] ?> gr</td></tr>
                </table>
            </div>

            <div class="col-right">
                <table class="sig-table">
                    <tr>
                        <th width="33%">Approved</th>
                        <th width="33%">Checked</th>
                        <th width="33%">Prepared</th>
                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>
            </div>
        </div>

        <h2 class="text-center" style="margin: 5px 0 10px 0; font-size: 18px; letter-spacing: 2px;">HISTORY TRIAL</h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th width="3%">NO</th>
                    <th width="7%">DATE</th>
                    <th width="5%">M.C NO</th>
                    <th width="15%">ACTUAL TRIAL</th>
                    <th width="12%">TRIAL REASON</th>
                    <th width="4%">OK</th>
                    <th width="4%">NG</th>
                    <th width="20%">APP, MOLD & M/C CHECK (NG ISSUE)</th>
                    <th width="8%">PIC</th>
                    <th width="7%">OPERATION</th>
                    <th width="15%">CORRECTIVE ACTION</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Tarik data histori trial khusus untuk Part ini saja
                $sqlHist = "SELECT T.*, J.JUDGE_TRIAL, 
                            (SELECT SUM(Weight_Part_Actual) FROM Trial_PE_WPart_ACT WHERE Trial_CODE = T.TRIAL_CODE) as TOT_WEIGHT
                            FROM TRIAL_PE T
                            LEFT JOIN JUDGE_TRIAL J ON T.JUDGE_ID = J.ID
                            WHERE T.PART_CODE = ? AND T.DATE BETWEEN ? AND ?
                            ORDER BY T.DATE ASC";
                $stmtH = sqlsrv_query($conn, $sqlHist, array($p['PART_CODE'], $start, $end));
                $no = 1;
                $hasHist = false;

                if($stmtH) {
                    while($h = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC)) {
                        $hasHist = true;
                        $tgl = $h['DATE'] instanceof DateTime ? $h['DATE']->format('d M Y') : date('d M Y', strtotime($h['DATE']));
                        
                        // Cek Isu dari kolom-kolom baru
                        $issueSummary = getIssueSummary($h);
                        $issueClass = ($issueSummary == "OK (No Issue)") ? "color: green;" : "color: red; font-weight: bold;";

                        echo "<tr>
                                <td class='text-center'>{$no}</td>
                                <td class='text-center'>{$tgl}</td>
                                <td class='text-center'>{$h['MAC_NO']}</td>
                                <td>
                                    Tonage : <b>{$h['TONAGE']} Ton</b><br>
                                    Cycle Time : <b>{$h['CYCLE_TIME_ACT']} s</b><br>
                                    Runner Weight : <b>".floatval($h['WEIGHT_RUNNER'])." gr</b><br>
                                    Part Weight : <b>".floatval($h['TOT_WEIGHT'])." gr</b>
                                </td>
                                <td>".htmlspecialchars($h['TRIAL_REASON'])."</td>
                                <td class='text-center'>{$h['QTY_OK']}</td>
                                <td class='text-center'>{$h['QTY_NG']}</td>
                                <td style='{$issueClass}'>{$issueSummary}</td>
                                <td class='text-center'>".htmlspecialchars($h['PIC'])."</td>
                                <td class='text-center'>{$h['OPERATION']}</td>
                                <td>".nl2br(htmlspecialchars($h['CORRECTIVE_ACTION']))."</td>
                              </tr>";
                        $no++;
                    }
                }
                if(!$hasHist) {
                    echo "<tr><td colspan='11' class='text-center text-muted'>Belum ada record trial aktual.</td></tr>";
                }
                ?>
            </tbody>
        </table>
        
    </div>
    <?php endforeach; ?>

</body>
</html>
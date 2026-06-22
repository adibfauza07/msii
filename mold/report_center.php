<?php
// Batasi memori agar load data raksasa tetap aman
ini_set('memory_limit', '512M');
require_once __DIR__ . "/../config/database_mold.php";

// 1. QUERY DROPDOWN SEARCHABLE (DATALIST PART NO)
$sqlPart = "SELECT DISTINCT PART_NO, PART_NAME FROM ITEM_CUST_MOLD WHERE PART_NO IS NOT NULL ORDER BY PART_NO ASC";
$resPart = sqlsrv_query($conn, $sqlPart);

// 2. PERBAIKAN UTAMA: Query Dropdown Klasifikasi Kasus dari MOLD_CLASSIFICATION
$sqlClass = "SELECT ID, CLASSIFICATION FROM MOLD_CLASSIFICATION WHERE CLASSIFICATION IS NOT NULL ORDER BY CLASSIFICATION ASC";
$resClass = sqlsrv_query($conn, $sqlClass);

$showReport = false;
$reportData = [];
$startDateForm = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
$endDateForm = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');

if (isset($_POST['btnCetak'])) {
    $startDate = str_replace('-', '', $startDateForm);
    $endDate   = str_replace('-', '', $endDateForm);
    
    $moldNo    = (!empty($_POST['mold_no'])) ? $_POST['mold_no'] : '%';
    $custAlias = '%'; 
    $classId   = (!empty($_POST['classification'])) ? $_POST['classification'] : '%';
    
    $tsql = "{call sp_history_mold_new(?, ?, ?, ?, ?)}";
    $params = [
        [$startDate, SQLSRV_PARAM_IN],
        [$endDate, SQLSRV_PARAM_IN],
        [$moldNo, SQLSRV_PARAM_IN],
        [$custAlias, SQLSRV_PARAM_IN],
        [$classId, SQLSRV_PARAM_IN]
    ];
    
    $options = array("Scrollable" => SQLSRV_CURSOR_KEYSET);
    $stmt = sqlsrv_query($conn, $tsql, $params, $options);
    
    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Ambil data dengan fallback huruf besar/kecil untuk menghindari bug case-sensitive driver
        $cust = isset($row['CUST_ALIAS']) ? trim($row['CUST_ALIAS']) : (isset($row['cust_alias']) ? trim($row['cust_alias']) : 'TANPA CUSTOMER');
        $pNo  = isset($row['PART_NO']) ? trim($row['PART_NO']) : (isset($row['part_no']) ? trim($row['part_no']) : 'UNKN-PART');
        $pName = isset($row['PART_NAME']) ? trim($row['PART_NAME']) : (isset($row['part_name']) ? trim($row['part_name']) : '');
        
        $reportData[$cust][$pNo]['info'] = ['PART_NAME' => $pName];
        $reportData[$cust][$pNo]['details'][] = $row;
    }
    sqlsrv_free_stmt($stmt);
    $showReport = true;
}

// Fungsi hitung Durasi Jam Kerja
function hitungDurasiWeb($start, $finish) {
    if (empty($start) || empty($finish) || $start == $finish) return "0:00";
    try {
        $time1 = new DateTime($start);
        $time2 = new DateTime($finish);
        $interval = $time1->diff($time2);
        return $interval->format('%H:%I');
    } catch (Exception $e) {
        return "-";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>History Mold Report Standard</title>
    <style>
        body { padding: 15px; background: #d4d0c8; font-family: Tahoma, Arial, sans-serif; font-size: 11px; color: #000; }
        .filter-container { border: 1px solid #808080; background: #eeeeee; padding: 15px; margin-bottom: 15px; }
        .form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 12px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 4px; }
        .form-group input, .form-group select { width: 100%; padding: 4px; box-sizing: border-box; font-family: Tahoma; font-size: 11px; border: 1px solid #7f9db9; background: #fff; }
        .btn-action { background: #d4d0c8; border: 2px outset #ffffff; padding: 6px 16px; font-weight: bold; cursor: pointer; font-family: Tahoma; font-size: 11px; }
        .btn-action:active { border: 2px inset #ffffff; }
        
        .report-header { text-align: center; margin-bottom: 15px; position: relative; }
        .company-name { font-weight: bold; text-align: left; font-size: 12px; float: left; }
        .doc-number { font-weight: bold; text-align: right; font-size: 12px; float: right; }
        .report-title { font-size: 18px; font-weight: bold; letter-spacing: 1px; margin-top: 10px; clear: both; }
        .report-periode { font-size: 11px; margin-top: 5px; font-weight: bold; }
        
        .report-table { width: 100%; border-collapse: collapse; background: #ffffff; margin-bottom: 25px; font-size: 10px; }
        .report-table th { border: 1px solid #000; padding: 5px; background: #ffffff; font-weight: bold; text-transform: uppercase; font-size: 10px; }
        .report-table td { border: 1px solid #000; padding: 4px; vertical-align: top; }
        
        .group-customer { background: #f2f2f2; font-weight: bold; font-size: 11px; padding: 6px 4px !important; border-bottom: 2px solid #000 !important; }
        .group-mold { background: #ffffff; font-weight: bold; padding: 4px !important; color: #000; border-bottom: 1px dashed #808080 !important; }
        .text-center { text-align: center; }
        
        @media print { .filter-container { display: none; } body { background: #fff; padding: 0; } }
    </style>
</head>
<body>

<div class="filter-container">
    <h3 style="margin-top:0; border-bottom:1px solid #808080; padding-bottom:4px; color:#2c3e50;">FILTER LAPORAN HISTORY MOLD</h3>
    <form method="POST" action="">
        <div class="form-grid">
            <div class="form-group">
                <label>Tanggal Mulai</label>
                <input type="date" name="start_date" value="<?php echo $startDateForm; ?>" required>
            </div>
            <div class="form-group">
                <label>Tanggal Selesai</label>
                <input type="date" name="end_date" value="<?php echo $endDateForm; ?>" required>
            </div>
            <div class="form-group">
                <label>Nomor Mold (Part No)</label>
                <input type="text" name="mold_no" list="part_list" value="<?php echo isset($_POST['mold_no']) ? h($_POST['mold_no']) : ''; ?>" placeholder="Ketik atau pilih...">
                <datalist id="part_list">
                    <?php while($p = sqlsrv_fetch_array($resPart, SQLSRV_FETCH_ASSOC)): ?>
                        <option value="<?php echo h($p['PART_NO']); ?>"><?php echo h($p['PART_NAME']); ?></option>
                    <?php endwhile; ?>
                </datalist>
            </div>
            <div class="form-group">
                <label>Klasifikasi Kasus</label>
                <select name="classification">
                    <option value="">-- SEMUA KLASIFIKASI --</option>
                    <?php if($resClass !== false): ?>
                        <?php while($c = sqlsrv_fetch_array($resClass, SQLSRV_FETCH_ASSOC)): ?>
                            <?php 
                                $cID = isset($c['ID']) ? $c['ID'] : $c['id']; 
                                $cDesc = isset($c['CLASSIFICATION']) ? $c['CLASSIFICATION'] : $c['classification']; 
                            ?>
                            <option value="<?php echo h($cID); ?>" <?php echo (isset($_POST['classification']) && $_POST['classification'] == $cID) ? 'selected' : ''; ?>>
                                <?php echo h($cDesc); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>
        <button type="submit" name="btnCetak" class="btn-action">TAMPILKAN HISTORY</button>
        <?php if($showReport && !empty($reportData)): ?>
            <button type="button" class="btn-action" onclick="window.print();" style="margin-left:5px; background:#27ae60; color:white;">PRINT / PDF</button>
        <?php endif; ?>
    </form>
</div>

<?php if ($showReport): ?>
    <div class="report-header">
        <div class="company-name">PT.IMC TEKNO INDONESIA</div>
        <div class="doc-number">FM.MD.S-02-30</div>
        <div class="report-title">HISTORY MOLD</div>
        <div class="report-periode">
            FROM : <?php echo date('d - F - Y', strtotime($startDateForm)); ?> &nbsp;&nbsp;&nbsp;&nbsp; 
            TO : <?php echo date('d - F - Y', strtotime($endDateForm)); ?>
        </div>
    </div>

    <?php if (!empty($reportData)): ?>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 8%;">DATE</th>
                    <th style="width: 12%;">KIND OF WORK</th>
                    <th style="width: 15%;">CAUSE OF WORK</th>
                    <th style="width: 23%;">WORKING_PROSES</th>
                    <th style="width: 7%;">PIC</th>
                    <th style="width: 6%;">STATUS</th>
                    <th style="width: 13%;">REFERENCE</th>
                    <th style="width: 6%;">START</th>
                    <th style="width: 6%;">FINISH</th>
                    <th style="width: 4%;">DURASI</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportData as $customerAlias => $parts): ?>
                    <tr>
                        <td colspan="10" class="group-customer"><?php echo h($customerAlias); ?></td>
                    </tr>
                    
                    <?php foreach ($parts as $partNo => $partContent): ?>
                        <tr>
                            <td colspan="10" class="group-mold">
                                <span style="text-decoration: underline; margin-right: 15px;"><?php echo h($partNo); ?></span> 
                                <span><?php echo h($partContent['info']['PART_NAME']); ?></span>
                            </td>
                        </tr>
                        
                        <?php foreach ($partContent['details'] as $row): ?>
                            <?php
                                // Ambil nilai row dengan pengecekan ganda huruf besar dan kecil
                                $rDate = isset($row['DATE']) ? $row['DATE'] : (isset($row['date']) ? $row['date'] : null);
                                $rClass = isset($row['CLASSIFICATION']) ? $row['CLASSIFICATION'] : (isset($row['classification']) ? $row['classification'] : '-');
                                $rProblem = isset($row['PROBLEM']) ? $row['PROBLEM'] : (isset($row['problem']) ? $row['problem'] : '-');
                                $rWork = isset($row['WORKING_PROSES']) ? $row['WORKING_PROSES'] : (isset($row['working_proses']) ? $row['working_proses'] : '-');
                                $rPic = isset($row['PIC']) ? $row['PIC'] : (isset($row['pic']) ? $row['pic'] : '-');
                                $rStatus = isset($row['STATUS']) ? $row['STATUS'] : (isset($row['status']) ? $row['status'] : '-');
                                $rRef = isset($row['REFERENCE']) ? $row['REFERENCE'] : (isset($row['reference']) ? $row['reference'] : '-');
                                $rStart = isset($row['START']) ? $row['START'] : (isset($row['start']) ? $row['start'] : '-');
                                $rFinish = isset($row['FISINSH']) ? $row['FISINSH'] : (isset($row['fisinsh']) ? $row['fisinsh'] : '-');
                            ?>
                            <tr>
                                <td class="text-center">
                                    <?php echo ($rDate instanceof DateTime) ? h($rDate->format('d-M-y')) : h($rDate); ?>
                                </td>
                                <td><?php echo h($rClass); ?></td> 
                                <td><?php echo h($rProblem); ?></td>
                                <td><?php echo h($rWork); ?></td>
                                <td class="text-center"><?php echo h($rPic); ?></td>
                                <td class="text-center" style="font-weight:bold; color:<?php echo ($rStatus == 1 || $rStatus === 'CLOSE' || $rStatus === 'CLOSED') ? '#27ae60' : '#c0392b'; ?>">
                                    <?php echo ($rStatus == 1 || $rStatus === 'CLOSE' || $rStatus === 'CLOSED') ? 'CLOSE' : 'OPEN'; ?>
                                </td>
                                <td><?php echo h($rRef); ?></td>
                                <td class="text-center">
                                    <?php echo ($rStart instanceof DateTime) ? h($rStart->format('H:i')) : h($rStart); ?>
                                </td>
                                <td class="text-center">
                                    <?php echo ($rFinish instanceof DateTime) ? h($rFinish->format('H:i')) : h($rFinish); ?>
                                </td>
                                <td class="text-center">
                                    <?php 
                                        $strStart = ($rStart instanceof DateTime) ? $rStart->format('H:i') : $rStart;
                                        $strFinish = ($rFinish instanceof DateTime) ? $rFinish->format('H:i') : $rFinish;
                                        echo hitungDurasiWeb($strStart, $strFinish); 
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div style="text-align:center; padding:20px; background:#fff; border:1px solid #808080; font-weight:bold; font-size:12px;">
            Tidak ada transaksi history mold ditemukan untuk parameter terpilih.
        </div>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>
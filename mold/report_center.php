<?php
ini_set('memory_limit', '512M');
require_once __DIR__ . "/../config/database_mold.php";

if (!function_exists('h')) {
    function h($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

$alertMsg = "";
$alertType = "";

// -------------------------------------------------------------
// 1. QUERY MASTER MOLD & KLASIFIKASI KASUS
// -------------------------------------------------------------
$sqlMasterMold = "SELECT MOLD_ID, PART_NO, PART_NAME, CUST_ALIAS FROM MOLD_MASTER WHERE PART_NO IS NOT NULL ORDER BY PART_NO ASC";
$resMasterMold = sqlsrv_query($conn, $sqlMasterMold);

if ($resMasterMold === false) {
    $sqlMasterMold = "SELECT MOLD_ID, PART_NO, PART_NAME, CUST_ALIAS FROM ITEM_CUST_MOLD WHERE PART_NO IS NOT NULL ORDER BY PART_NO ASC";
    $resMasterMold = sqlsrv_query($conn, $sqlMasterMold);
    if ($resMasterMold === false) {
        die("<pre>Gagal mengambil data master part:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }
}

$arrMasterMold = [];
while ($p = sqlsrv_fetch_array($resMasterMold, SQLSRV_FETCH_ASSOC)) {
    $arrMasterMold[] = [
        'id'   => $p['MOLD_ID'],
        'no'   => trim($p['PART_NO']),
        'name' => isset($p['PART_NAME']) ? trim($p['PART_NAME']) : '',
        'cust' => isset($p['CUST_ALIAS']) ? trim($p['CUST_ALIAS']) : ''
    ];
}

// Query Klasifikasi Kasus dari MOLD_CLASSIFICATION[cite: 2, 6]
$sqlClass = "SELECT ID, CLASSIFICATION FROM MOLD_CLASSIFICATION WHERE CLASSIFICATION IS NOT NULL ORDER BY CLASSIFICATION ASC";
$resClass = sqlsrv_query($conn, $sqlClass);
$arrClass = [];
if ($resClass !== false) {
    while ($c = sqlsrv_fetch_array($resClass, SQLSRV_FETCH_ASSOC)) {
        $arrClass[] = [
            'id'   => isset($c['ID']) ? $c['ID'] : $c['id'],
            'desc' => isset($c['CLASSIFICATION']) ? $c['CLASSIFICATION'] : $c['classification']
        ];
    }
}

// -------------------------------------------------------------
// 2. PROSES DELETE DATA HISTORY MOLD
// -------------------------------------------------------------
if (isset($_POST['btnDeleteHistory'])) {
    $delMoldId = !empty($_POST['del_mold_id']) ? intval($_POST['del_mold_id']) : null;
    $delDate   = !empty($_POST['del_date']) ? $_POST['del_date'] : null;
    $delRef    = isset($_POST['del_ref']) ? trim($_POST['del_ref']) : '';
    $delStart  = isset($_POST['del_start']) ? trim($_POST['del_start']) : '';

    if ($delMoldId && $delDate) {
        $sqlDelete = "DELETE FROM MOLD_HISTORY_DETAIL 
                      WHERE MOLD_ID = ? 
                        AND CONVERT(VARCHAR(10), [DATE], 120) = ? 
                        AND ISNULL(REFERENCE, '') = ?
                        AND (CONVERT(VARCHAR(8), [START], 108) LIKE ? OR ISNULL([START], '') = ?)";
        $paramsDel = [$delMoldId, $delDate, $delRef, $delStart . '%', $delStart];
        $stmtDel = sqlsrv_query($conn, $sqlDelete, $paramsDel);

        if ($stmtDel === false) {
            $err = sqlsrv_errors();
            $alertMsg = "Gagal menghapus data: " . (isset($err[0]['message']) ? $err[0]['message'] : 'Query error');
            $alertType = "danger";
        } else {
            $alertMsg = "Data History Mold berhasil dihapus!";
            $alertType = "success";
        }
    }
}

// -------------------------------------------------------------
// 3. PROSES UPDATE (EDIT) DATA HISTORY MOLD
// -------------------------------------------------------------
if (isset($_POST['btnUpdateHistory'])) {
    $editMoldId   = intval($_POST['edit_mold_id']);
    $editOrigDate = $_POST['edit_orig_date'];
    $editOrigRef  = trim($_POST['edit_orig_ref']);
    $editOrigStart= trim($_POST['edit_orig_start']);

    $newDate       = $_POST['edit_date'];
    $newClassId    = !empty($_POST['edit_class_id']) ? intval($_POST['edit_class_id']) : null;
    $newProblem    = trim($_POST['edit_problem']);
    $newWork       = trim($_POST['edit_work']);
    $newPic        = trim($_POST['edit_pic']);
    $newStatus     = intval($_POST['edit_status']); // 0 = OPEN, 1 = CLOSE
    $newRef        = trim($_POST['edit_ref']);
    
    // Format waktu ke format jam bersih
    $newStart      = !empty($_POST['edit_start']) ? trim($_POST['edit_start']) : null;
    $newFinish     = !empty($_POST['edit_finish']) ? trim($_POST['edit_finish']) : null;
    $newPartChange = trim($_POST['edit_part_change']);

    $sqlUpdate = "UPDATE MOLD_HISTORY_DETAIL 
                  SET [DATE] = ?,
                      CLASSIFICATON = ?,
                      PROBLEM = ?,
                      WORKING_PROSES = ?,
                      PIC = ?,
                      [STATUS] = ?,
                      REFERENCE = ?,
                      [START] = ?,
                      FISINSH = ?,
                      SPARE_PART_CHANGE = ?
                  WHERE MOLD_ID = ? 
                    AND CONVERT(VARCHAR(10), [DATE], 120) = ? 
                    AND ISNULL(REFERENCE, '') = ?
                    AND (CONVERT(VARCHAR(8), [START], 108) LIKE ? OR ISNULL([START], '') = ?)";

    $paramsUpd = [
        $newDate, $newClassId, $newProblem, $newWork, $newPic, $newStatus,
        $newRef, $newStart, $newFinish, $newPartChange,
        $editMoldId, $editOrigDate, $editOrigRef, $editOrigStart . '%', $editOrigStart
    ];
    $stmtUpd = sqlsrv_query($conn, $sqlUpdate, $paramsUpd);

    if ($stmtUpd === false) {
        $err = sqlsrv_errors();
        $alertMsg = "Gagal mengupdate data: " . (isset($err[0]['message']) ? $err[0]['message'] : 'Query error');
        $alertType = "danger";
    } else {
        $alertMsg = "Data History Mold berhasil diperbarui!";
        $alertType = "success";
    }
}

// -------------------------------------------------------------
// 4. SIMPAN HISTORY MOLD BARU
// -------------------------------------------------------------
if (isset($_POST['btnSimpanHistory'])) {
    $moldId     = !empty($_POST['in_mold_id']) ? intval($_POST['in_mold_id']) : null;
    $date       = !empty($_POST['in_date']) ? $_POST['in_date'] : date('Y-m-d');
    $classId    = !empty($_POST['in_class_id']) ? intval($_POST['in_class_id']) : null;
    $problem    = !empty($_POST['in_problem']) ? trim($_POST['in_problem']) : '';
    $work       = !empty($_POST['in_working_proses']) ? trim($_POST['in_working_proses']) : '';
    $pic        = !empty($_POST['in_pic']) ? trim($_POST['in_pic']) : '';
    $status     = (isset($_POST['in_status']) && ($_POST['in_status'] === '1' || $_POST['in_status'] === 'CLOSE')) ? 1 : 0;
    $ref        = !empty($_POST['in_ref']) ? trim($_POST['in_ref']) : '';
    $startTime  = !empty($_POST['in_start']) ? trim($_POST['in_start']) : null;
    $finishTime = !empty($_POST['in_finish']) ? trim($_POST['in_finish']) : null;
    $partChange = !empty($_POST['in_part_change']) ? trim($_POST['in_part_change']) : '';

    if (empty($moldId)) {
        $alertMsg = "Gagal Simpan: Part No / Mold harus dipilih dari master data list!";
        $alertType = "danger";
    } else {
        $sqlInsertHistory = "INSERT INTO MOLD_HISTORY_DETAIL 
            (MOLD_ID, [DATE], CLASSIFICATON, PROBLEM, WORKING_PROSES, PIC, [STATUS], REFERENCE, [START], FISINSH, SPARE_PART_CHANGE) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $moldId, $date, $classId, $problem, $work, $pic, 
            $status, $ref, $startTime, $finishTime, $partChange
        ];
        
        $stmtInsert = sqlsrv_query($conn, $sqlInsertHistory, $params);

        if ($stmtInsert === false) {
            $errors = sqlsrv_errors();
            $alertMsg = "Gagal simpan history mold: " . (isset($errors[0]['message']) ? $errors[0]['message'] : 'Kesalahan query SQL');
            $alertType = "danger";
        } else {
            $alertMsg = "Data History Mold berhasil disimpan!";
            $alertType = "success";
        }
    }
}

// -------------------------------------------------------------
// 5. FILTER LAPORAN & EXPORT EXCEL
// -------------------------------------------------------------
$showReport = false;
$reportData = [];
$startDateForm = isset($_REQUEST['start_date']) ? $_REQUEST['start_date'] : date('Y-m-01');
$endDateForm   = isset($_REQUEST['end_date'])   ? $_REQUEST['end_date']   : date('Y-m-d');

function hitungDurasiWeb($start, $finish) {
    if (empty($start) || empty($finish) || $start == $finish || $start == '-' || $finish == '-') return "00:00";
    try {
        $time1 = new DateTime($start);
        $time2 = new DateTime($finish);
        $interval = $time1->diff($time2);
        return $interval->format('%H:%I');
    } catch (Exception $e) {
        return "-";
    }
}

$isExport = isset($_POST['btnExport']);
$isSubmit = isset($_POST['btnCetak']) || $isExport || isset($_POST['btnSimpanHistory']) || isset($_POST['btnUpdateHistory']) || isset($_POST['btnDeleteHistory']);

if ($isSubmit) {
    $startDate = !empty($startDateForm) ? date('Ymd', strtotime($startDateForm)) : date('Ym01');
    $endDate   = !empty($endDateForm)   ? date('Ymd', strtotime($endDateForm . ' +1 day')) : date('Ymd', strtotime('+1 day'));

    $rawMoldNo = isset($_REQUEST['mold_no']) ? trim($_REQUEST['mold_no'], " \t\n\r\0\x0B'") : '';
    $moldNo    = ($rawMoldNo !== '') ? '%' . $rawMoldNo . '%' : '%';

    $custAlias = '%'; 
    $classId   = (!empty($_REQUEST['classification'])) ? $_REQUEST['classification'] : '%';
    
    $tsql = "{call sp_history_mold_new(?, ?, ?, ?, ?)}";
    $params = [
        [$startDate, SQLSRV_PARAM_IN],
        [$endDate,   SQLSRV_PARAM_IN],
        [$moldNo,    SQLSRV_PARAM_IN],
        [$custAlias, SQLSRV_PARAM_IN],
        [$classId,   SQLSRV_PARAM_IN]
    ];
    
    $options = array("Scrollable" => SQLSRV_CURSOR_KEYSET);
    $stmt = sqlsrv_query($conn, $tsql, $params, $options);
    
    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $cust = isset($row['CUST_ALIAS']) ? trim($row['CUST_ALIAS']) : (isset($row['cust_alias']) ? trim($row['cust_alias']) : 'TANPA CUSTOMER');
        $pNo  = isset($row['PART_NO']) ? trim($row['PART_NO']) : (isset($row['part_no']) ? trim($row['part_no']) : 'UNKN-PART');
        $pName = isset($row['PART_NAME']) ? trim($row['PART_NAME']) : (isset($row['part_name']) ? trim($row['part_name']) : '');
        
        $reportData[$cust][$pNo]['info'] = ['PART_NAME' => $pName];
        $reportData[$cust][$pNo]['details'][] = $row;
    }
    sqlsrv_free_stmt($stmt);
    $showReport = true;

    // Export Excel Langsung (Tanpa kolom Aksi)[cite: 1, 6]
    if ($isExport) {
        $fileName = "History_Mold_" . date('Ymd_His') . ".xls";
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$fileName\"");
        header("Pragma: no-cache");
        header("Expires: 0");
        ?>
        <table border="1">
            <thead>
                <tr>
                    <th colspan="10" style="text-align: center; font-size: 14pt; font-weight: bold; height: 30px;">
                        PT. IMC TEKNO INDONESIA - HISTORY MOLD (FM.MD.S-02-30)
                    </th>
                </tr>
                <tr>
                    <th colspan="10" style="text-align: center; font-weight: bold;">
                        FROM : <?php echo date('d - F - Y', strtotime($startDateForm)); ?> &nbsp;&nbsp;&nbsp;&nbsp; 
                        TO : <?php echo date('d - F - Y', strtotime($endDateForm)); ?>
                    </th>
                </tr>
                <tr style="background-color: #2c3e50; color: #ffffff; font-weight: bold;">
                    <th>DATE</th>
                    <th>KIND OF WORK</th>
                    <th>CAUSE OF WORK</th>
                    <th>WORKING_PROSES</th>
                    <th>PIC</th>
                    <th>STATUS</th>
                    <th>REFERENCE</th>
                    <th>START</th>
                    <th>FINISH</th>
                    <th>DURASI</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportData as $customerAlias => $parts): ?>
                    <tr>
                        <td colspan="10" style="background-color: #d9edf7; font-weight: bold;"><?php echo h($customerAlias); ?></td>
                    </tr>
                    <?php foreach ($parts as $partNo => $partContent): ?>
                        <tr>
                            <td colspan="10" style="background-color: #fcf8e3; font-weight: bold;">
                                <span style="mso-number-format:'\@';"><?php echo h($partNo); ?></span> - <?php echo h($partContent['info']['PART_NAME']); ?>
                            </td>
                        </tr>
                        <?php foreach ($partContent['details'] as $row): ?>
                            <?php
                                $rDate = isset($row['DATE']) ? $row['DATE'] : (isset($row['date']) ? $row['date'] : null);
                                $rClass = isset($row['CLASSIFICATION']) ? $row['CLASSIFICATION'] : (isset($row['classification']) ? $row['classification'] : '-');
                                $rProblem = isset($row['PROBLEM']) ? $row['PROBLEM'] : (isset($row['problem']) ? $row['problem'] : '-');
                                $rWork = isset($row['WORKING_PROSES']) ? $row['WORKING_PROSES'] : (isset($row['working_proses']) ? $row['working_proses'] : '-');
                                $rPic = isset($row['PIC']) ? $row['PIC'] : (isset($row['pic']) ? $row['pic'] : '-');
                                $rStatus = isset($row['STATUS']) ? $row['STATUS'] : (isset($row['status']) ? $row['status'] : 0);
                                $rRef = isset($row['REFERENCE']) ? $row['REFERENCE'] : (isset($row['reference']) ? $row['reference'] : '-');
                                $rStart = isset($row['START']) ? $row['START'] : (isset($row['start']) ? $row['start'] : '-');
                                $rFinish = isset($row['FISINSH']) ? $row['FISINSH'] : (isset($row['fisinsh']) ? $row['fisinsh'] : '-');

                                $strStart24 = ($rStart instanceof DateTime) ? $rStart->format('H:i') : ($rStart ? date('H:i', strtotime($rStart)) : '-');
                                $strFinish24 = ($rFinish instanceof DateTime) ? $rFinish->format('H:i') : ($rFinish ? date('H:i', strtotime($rFinish)) : '-');
                            ?>
                            <tr>
                                <td align="center"><?php echo ($rDate instanceof DateTime) ? h($rDate->format('d-M-y')) : h($rDate); ?></td>
                                <td><?php echo h($rClass); ?></td> 
                                <td><?php echo h($rProblem); ?></td>
                                <td><?php echo h($rWork); ?></td>
                                <td align="center"><?php echo h($rPic); ?></td>
                                <td align="center" style="font-weight:bold; color:<?php echo ($rStatus == 1 || $rStatus === 'CLOSE' || $rStatus === 'CLOSED') ? '#27ae60' : '#c0392b'; ?>;">
                                    <?php echo ($rStatus == 1 || $rStatus === 'CLOSE' || $rStatus === 'CLOSED') ? 'CLOSE' : 'OPEN'; ?>
                                </td>
                                <td style="mso-number-format:'\@';"><?php echo h($rRef); ?></td>
                                <td align="center"><?php echo h($strStart24); ?></td>
                                <td align="center"><?php echo h($strFinish24); ?></td>
                                <td align="center"><?php echo hitungDurasiWeb($strStart24, $strFinish24); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>History Mold - Entry & Report</title>
    <style>
        body { padding: 15px; background: #d4d0c8; font-family: Tahoma, Arial, sans-serif; font-size: 11px; color: #000; margin: 0; }
        .box-container { border: 1px solid #808080; background: #eeeeee; padding: 12px; margin-bottom: 15px; }
        .box-title { margin-top: 0; border-bottom: 1px solid #808080; padding-bottom: 4px; color: #2c3e50; font-size: 12px; font-weight: bold; }
        
        .form-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 10px; }
        .form-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 10px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 3px; }
        .form-group input, .form-group select { width: 100%; padding: 4px; box-sizing: border-box; font-family: Tahoma; font-size: 11px; border: 1px solid #7f9db9; background: #fff; }
        .form-group input[readonly] { background: #e0e0e0; color: #333; }

        .btn-action { background: #d4d0c8; border: 2px outset #ffffff; padding: 5px 15px; font-weight: bold; cursor: pointer; font-family: Tahoma; font-size: 11px; }
        .btn-action:active { border: 2px inset #ffffff; }
        .btn-save { background: #2980b9; color: #fff; border: 2px outset #5dade2; }
        .btn-save:hover { background: #1f618d; }

        .btn-edit { background: #f39c12; color: #fff; border: 1px solid #d68910; padding: 3px 8px; font-size: 10px; font-weight: bold; cursor: pointer; border-radius: 2px; }
        .btn-del { background: #c0392b; color: #fff; border: 1px solid #962d22; padding: 3px 8px; font-size: 10px; font-weight: bold; cursor: pointer; border-radius: 2px; margin-left: 3px; }
        .btn-edit:hover { background: #d68910; }
        .btn-del:hover { background: #962d22; }

        .alert { padding: 8px 12px; margin-bottom: 12px; border-radius: 2px; font-weight: bold; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
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

        /* Modal Overlay & Content */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 9999; justify-content: center; align-items: center; }
        .modal-content { background: #eeeeee; border: 2px solid #2c3e50; border-radius: 4px; width: 750px; max-width: 95%; padding: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.4); font-family: Tahoma, Arial, sans-serif; font-size: 11px; }
        .modal-header { font-size: 13px; font-weight: bold; color: #2c3e50; border-bottom: 1px solid #808080; padding-bottom: 6px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { cursor: pointer; font-size: 18px; font-weight: bold; color: #c0392b; line-height: 1; }
        
        @media print { .box-container, .alert, .col-action, .modal-overlay { display: none !important; } body { background: #fff; padding: 0; } }
    </style>
</head>
<body>

<?php if (!empty($alertMsg)): ?>
    <div class="alert alert-<?php echo $alertType; ?>"><?php echo h($alertMsg); ?></div>
<?php endif; ?>

<!-- ======================================================== -->
<!-- FORM INPUT TRANSAKSI HISTORY MOLD BARU                  -->
<!-- ======================================================== -->
<div class="box-container">
    <div class="box-title">INPUT TRANSAKSI HISTORY MOLD BARU</div>
    <form method="POST" action="">
        <div class="form-grid-3">
            <div class="form-group">
                <label>PART NO <span style="color:red;">*</span></label>
                <input type="text" id="input_hist_part_no" list="list_hist_parts" placeholder="Ketik Part No..." required autocomplete="off">
                <datalist id="list_hist_parts">
                    <?php foreach ($arrMasterMold as $m): ?>
                        <option value="<?php echo h($m['no']); ?>"><?php echo h($m['name']); ?></option>
                    <?php endforeach; ?>
                </datalist>
                <input type="hidden" name="in_mold_id" id="in_hist_mold_id" required>
            </div>
            <div class="form-group">
                <label>NAMA PART (MOLD NAME)</label>
                <input type="text" id="in_hist_mold_name" readonly placeholder="(Otomatis terisi)">
            </div>
            <div class="form-group">
                <label>CUSTOMER ALIAS</label>
                <input type="text" id="in_hist_cust_alias" readonly placeholder="(Otomatis terisi)">
            </div>
        </div>

        <div class="form-grid-4">
            <div class="form-group">
                <label>DATE (TANGGAL)</label>
                <input type="date" name="in_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label>KIND OF WORK (KLASIFIKASI)</label>
                <select name="in_class_id" required>
                    <option value="">-- PILIH KLASIFIKASI --</option>
                    <?php foreach ($arrClass as $c): ?>
                        <option value="<?php echo h($c['id']); ?>"><?php echo h($c['desc']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>PIC</label>
                <input type="text" name="in_pic" placeholder="Nama teknisi / PIC..." required>
            </div>
            <div class="form-group">
                <label>STATUS</label>
                <select name="in_status" required>
                    <option value="0">OPEN</option>
                    <option value="1">CLOSE</option>
                </select>
            </div>
        </div>

        <div class="form-grid-3">
            <div class="form-group">
                <label>CAUSE OF WORK (PROBLEM / PENYEBAB)</label>
                <input type="text" name="in_problem" placeholder="Problem / kendala mold..." required>
            </div>
            <div class="form-group">
                <label>WORKING PROSES (TINDAKAN / PERBAIKAN)</label>
                <input type="text" name="in_working_proses" placeholder="Tindakan proses perbaikan..." required>
            </div>
            <div class="form-group">
                <label>REFERENCE (SPK / NO. DOKUMEN)</label>
                <input type="text" name="in_ref" placeholder="No. referensi / SPK...">
            </div>
        </div>

        <div class="form-grid-4">
            <div class="form-group">
                <label>START TIME (JAM 24H)</label>
                <input type="text" name="in_start" value="08:00" maxlength="5" placeholder="HH:MM" pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]">
            </div>
            <div class="form-group">
                <label>FINISH TIME (JAM 24H)</label>
                <input type="text" name="in_finish" value="09:00" maxlength="5" placeholder="HH:MM" pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]">
            </div>
            <div class="form-group">
                <label>SPARE PART CHANGE</label>
                <input type="text" name="in_part_change" placeholder="Penggantian spare part...">
            </div>
            <div style="display: flex; align-items: flex-end;">
                <button type="submit" name="btnSimpanHistory" class="btn-action btn-save" style="width: 100%; height: 26px;">
                    SIMPAN HISTORY MOLD
                </button>
            </div>
        </div>
    </form>
</div>

<!-- ======================================================== -->
<!-- FORM FILTER LAPORAN & EXPORT EXCEL                      -->
<!-- ======================================================== -->
<div class="box-container">
    <div class="box-title">FILTER LAPORAN HISTORY MOLD</div>
    <form method="POST" action="">
        <div class="form-grid-4">
            <div class="form-group">
                <label>Tanggal Mulai</label>
                <input type="date" name="start_date" value="<?php echo h($startDateForm); ?>" required>
            </div>
            <div class="form-group">
                <label>Tanggal Selesai</label>
                <input type="date" name="end_date" value="<?php echo h($endDateForm); ?>" required>
            </div>
            <div class="form-group">
                <label>Nomor Mold (Part No)</label>
                <input type="text" name="mold_no" list="filter_hist_part_list" value="<?php echo isset($_REQUEST['mold_no']) ? h(trim($_REQUEST['mold_no'], "'")) : ''; ?>" placeholder="Ketik atau pilih...">
                <datalist id="filter_hist_part_list">
                    <?php foreach ($arrMasterMold as $m): ?>
                        <option value="<?php echo h($m['no']); ?>"><?php echo h($m['name']); ?></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-group">
                <label>Klasifikasi Kasus</label>
                <select name="classification">
                    <option value="">-- SEMUA KLASIFIKASI --</option>
                    <?php foreach ($arrClass as $c): ?>
                        <option value="<?php echo h($c['id']); ?>" <?php echo (isset($_REQUEST['classification']) && $_REQUEST['classification'] == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo h($c['desc']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit" name="btnCetak" class="btn-action">TAMPILKAN HISTORY</button>
        <?php if($showReport && !empty($reportData)): ?>
            <button type="submit" name="btnExport" class="btn-action" style="margin-left:5px; background:#1d6f42; color:white; border-color:#1d6f42;">EXPORT EXCEL</button>
            <button type="button" class="btn-action" onclick="window.print();" style="margin-left:5px; background:#27ae60; color:white;">PRINT / PDF</button>
        <?php endif; ?>
    </form>
</div>

<!-- ======================================================== -->
<!-- TABEL LAPORAN HISTORY MOLD                              -->
<!-- ======================================================== -->
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
                    <th style="width: 7%;">DATE</th>
                    <th style="width: 11%;">KIND OF WORK</th>
                    <th style="width: 14%;">CAUSE OF WORK</th>
                    <th style="width: 20%;">WORKING_PROSES</th>
                    <th style="width: 6%;">PIC</th>
                    <th style="width: 5%;">STATUS</th>
                    <th style="width: 11%;">REFERENCE</th>
                    <th style="width: 5%;">START</th>
                    <th style="width: 5%;">FINISH</th>
                    <th style="width: 4%;">DURASI</th>
                    <th class="col-action" style="width: 12%; text-align: center;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportData as $customerAlias => $parts): ?>
                    <tr>
                        <td colspan="11" class="group-customer"><?php echo h($customerAlias); ?></td>
                    </tr>
                    
                    <?php foreach ($parts as $partNo => $partContent): ?>
                        <tr>
                            <td colspan="11" class="group-mold">
                                <span style="text-decoration: underline; margin-right: 15px;"><?php echo h($partNo); ?></span> 
                                <span><?php echo h($partContent['info']['PART_NAME']); ?></span>
                            </td>
                        </tr>
                        
                        <?php foreach ($partContent['details'] as $row): ?>
                            <?php
                                $rDate = isset($row['DATE']) ? $row['DATE'] : (isset($row['date']) ? $row['date'] : null);
                                $rClass = isset($row['CLASSIFICATION']) ? $row['CLASSIFICATION'] : (isset($row['classification']) ? $row['classification'] : '-');
                                $rProblem = isset($row['PROBLEM']) ? $row['PROBLEM'] : (isset($row['problem']) ? $row['problem'] : '-');
                                $rWork = isset($row['WORKING_PROSES']) ? $row['WORKING_PROSES'] : (isset($row['working_proses']) ? $row['working_proses'] : '-');
                                $rPic = isset($row['PIC']) ? $row['PIC'] : (isset($row['pic']) ? $row['pic'] : '-');
                                $rStatus = isset($row['STATUS']) ? $row['STATUS'] : (isset($row['status']) ? $row['status'] : 0);
                                $rRef = isset($row['REFERENCE']) ? $row['REFERENCE'] : (isset($row['reference']) ? $row['reference'] : '-');
                                $rStart = isset($row['START']) ? $row['START'] : (isset($row['start']) ? $row['start'] : '-');
                                $rFinish = isset($row['FISINSH']) ? $row['FISINSH'] : (isset($row['fisinsh']) ? $row['fisinsh'] : '-');
                                $rPartChange = isset($row['SPARE_PART_CHANGE']) ? $row['SPARE_PART_CHANGE'] : '';

                                $strDateIso = ($rDate instanceof DateTime) ? $rDate->format('Y-m-d') : date('Y-m-d', strtotime($rDate));
                                
                                // Format 24 Jam di Tabel[cite: 14]
                                $strStart24 = ($rStart instanceof DateTime) ? $rStart->format('H:i') : ($rStart ? date('H:i', strtotime($rStart)) : '-');
                                $strFinish24 = ($rFinish instanceof DateTime) ? $rFinish->format('H:i') : ($rFinish ? date('H:i', strtotime($rFinish)) : '-');

                                $curMoldId = 0;
                                foreach ($arrMasterMold as $mm) {
                                    if (strcasecmp($mm['no'], $partNo) === 0) {
                                        $curMoldId = $mm['id'];
                                        break;
                                    }
                                }

                                $curClassId = 0;
                                foreach ($arrClass as $ac) {
                                    if (strcasecmp($ac['desc'], $rClass) === 0) {
                                        $curClassId = $ac['id'];
                                        break;
                                    }
                                }
                            ?>
                            <tr>
                                <td class="text-center"><?php echo ($rDate instanceof DateTime) ? h($rDate->format('d-M-y')) : h($rDate); ?></td>
                                <td><?php echo h($rClass); ?></td> 
                                <td><?php echo h($rProblem); ?></td>
                                <td><?php echo h($rWork); ?></td>
                                <td class="text-center"><?php echo h($rPic); ?></td>
                                <td class="text-center" style="font-weight:bold; color:<?php echo ($rStatus == 1 || $rStatus === 'CLOSE' || $rStatus === 'CLOSED') ? '#27ae60' : '#c0392b'; ?>;">
                                    <?php echo ($rStatus == 1 || $rStatus === 'CLOSE' || $rStatus === 'CLOSED') ? 'CLOSE' : 'OPEN'; ?>
                                </td>
                                <td><?php echo h($rRef); ?></td>
                                <td class="text-center"><?php echo h($strStart24); ?></td>
                                <td class="text-center"><?php echo h($strFinish24); ?></td>
                                <td class="text-center"><?php echo hitungDurasiWeb($strStart24, $strFinish24); ?></td>
                                
                                <!-- KOLOM AKSI EDIT & HAPUS[cite: 14] -->
                                <td class="col-action text-center" style="white-space: nowrap;">
                                    <button type="button" class="btn-edit" 
                                        onclick='openEditModal(<?php echo json_encode([
                                            "mold_id"     => $curMoldId,
                                            "part_no"     => $partNo,
                                            "part_name"   => $partContent["info"]["PART_NAME"],
                                            "date"        => $strDateIso,
                                            "class_id"    => $curClassId,
                                            "problem"     => ($rProblem === "-") ? "" : $rProblem,
                                            "work"        => ($rWork === "-") ? "" : $rWork,
                                            "pic"         => ($rPic === "-") ? "" : $rPic,
                                            "status"      => ($rStatus == 1 || $rStatus === "CLOSE" || $rStatus === "CLOSED") ? 1 : 0,
                                            "ref"         => ($rRef === "-") ? "" : $rRef,
                                            "start"       => ($strStart24 === "-") ? "" : $strStart24,
                                            "finish"      => ($strFinish24 === "-") ? "" : $strFinish24,
                                            "part_change" => $rPartChange
                                        ]); ?>)'>
                                        EDIT
                                    </button>

                                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data riwayat mold ini?');">
                                        <input type="hidden" name="start_date" value="<?php echo h($startDateForm); ?>">
                                        <input type="hidden" name="end_date" value="<?php echo h($endDateForm); ?>">
                                        <input type="hidden" name="mold_no" value="<?php echo isset($_REQUEST['mold_no']) ? h($_REQUEST['mold_no']) : ''; ?>">
                                        <input type="hidden" name="classification" value="<?php echo isset($_REQUEST['classification']) ? h($_REQUEST['classification']) : ''; ?>">
                                        <input type="hidden" name="del_mold_id" value="<?php echo h($curMoldId); ?>">
                                        <input type="hidden" name="del_date" value="<?php echo h($strDateIso); ?>">
                                        <input type="hidden" name="del_ref" value="<?php echo ($rRef === '-') ? '' : h($rRef); ?>">
                                        <input type="hidden" name="del_start" value="<?php echo ($strStart24 === '-') ? '' : h($strStart24); ?>">
                                        <button type="submit" name="btnDeleteHistory" class="btn-del">HAPUS</button>
                                    </form>
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

<!-- ======================================================== -->
<!-- MODAL POPUP EDIT DATA HISTORY MOLD[cite: 15]                       -->
<!-- ======================================================== -->
<div id="modalEdit" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <span>EDIT DATA RIWAYAT MOLD</span>
            <span class="modal-close" onclick="closeEditModal()">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="start_date" value="<?php echo h($startDateForm); ?>">
            <input type="hidden" name="end_date" value="<?php echo h($endDateForm); ?>">
            <input type="hidden" name="mold_no" value="<?php echo isset($_REQUEST['mold_no']) ? h($_REQUEST['mold_no']) : ''; ?>">
            <input type="hidden" name="classification" value="<?php echo isset($_REQUEST['classification']) ? h($_REQUEST['classification']) : ''; ?>">

            <input type="hidden" name="edit_mold_id" id="edit_mold_id">
            <input type="hidden" name="edit_orig_date" id="edit_orig_date">
            <input type="hidden" name="edit_orig_ref" id="edit_orig_ref">
            <input type="hidden" name="edit_orig_start" id="edit_orig_start">

            <div class="form-grid-3">
                <div class="form-group">
                    <label>PART NO</label>
                    <input type="text" id="edit_part_no" readonly>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>MOLD NAME</label>
                    <input type="text" id="edit_part_name" readonly>
                </div>
            </div>

            <div class="form-grid-4">
                <div class="form-group">
                    <label>TANGGAL</label>
                    <input type="date" name="edit_date" id="edit_date" required>
                </div>
                <div class="form-group">
                    <label>KLASIFIKASI</label>
                    <select name="edit_class_id" id="edit_class_id" required>
                        <option value="">-- PILIH KLASIFIKASI --</option>
                        <?php foreach ($arrClass as $c): ?>
                            <option value="<?php echo h($c['id']); ?>"><?php echo h($c['desc']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>PIC</label>
                    <input type="text" name="edit_pic" id="edit_pic" required>
                </div>
                <div class="form-group">
                    <label>STATUS</label>
                    <select name="edit_status" id="edit_status" required>
                        <option value="0">OPEN</option>
                        <option value="1">CLOSE</option>
                    </select>
                </div>
            </div>

            <div class="form-grid-3">
                <div class="form-group">
                    <label>PROBLEM / PENYEBAB</label>
                    <input type="text" name="edit_problem" id="edit_problem" required>
                </div>
                <div class="form-group">
                    <label>WORKING PROSES</label>
                    <input type="text" name="edit_work" id="edit_work" required>
                </div>
                <div class="form-group">
                    <label>REFERENCE (SPK / OVH)</label>
                    <input type="text" name="edit_ref" id="edit_ref">
                </div>
            </div>

            <div class="form-grid-4">
                <div class="form-group">
                    <label>START TIME (24H)</label>
                    <input type="text" name="edit_start" id="edit_start" maxlength="5" placeholder="HH:MM" pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]">
                </div>
                <div class="form-group">
                    <label>FINISH TIME (24H)</label>
                    <input type="text" name="edit_finish" id="edit_finish" maxlength="5" placeholder="HH:MM" pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>SPARE PART CHANGE</label>
                    <input type="text" name="edit_part_change" id="edit_part_change">
                </div>
            </div>

            <div style="text-align: right; margin-top: 15px;">
                <button type="button" class="btn-action" onclick="closeEditModal()" style="margin-right: 5px;">BATAL</button>
                <button type="submit" name="btnUpdateHistory" class="btn-action btn-save">SIMPAN PERUBAHAN</button>
            </div>
        </form>
    </div>
</div>

<script>
    const masterMoldList = <?php echo json_encode($arrMasterMold); ?>;
    const inputPartNo = document.getElementById('input_hist_part_no');
    const inputMoldId = document.getElementById('in_hist_mold_id');
    const inputMoldName = document.getElementById('in_hist_mold_name');
    const inputCustAlias = document.getElementById('in_hist_cust_alias');

    inputPartNo.addEventListener('input', function() {
        const val = this.value.replace(/['"]/g, '').trim().toUpperCase();
        const found = masterMoldList.find(m => m.no.toUpperCase() === val);
        if (found) {
            inputMoldId.value = found.id;
            inputMoldName.value = found.name;
            inputCustAlias.value = found.cust;
        } else {
            inputMoldId.value = '';
            inputMoldName.value = '';
            inputCustAlias.value = '';
        }
    });

    // Fungsi konversi jam apa pun menjadi format 24 Jam murni (HH:mm)
    function formatTo24Hour(timeStr) {
        if (!timeStr || timeStr === '-' || timeStr === '') return '';
        timeStr = timeStr.trim().toUpperCase();

        if (timeStr.includes('AM') || timeStr.includes('PM')) {
            const isPM = timeStr.includes('PM');
            const clean = timeStr.replace('AM', '').replace('PM', '').trim();
            const parts = clean.split(':');
            let hours = parseInt(parts[0], 10);
            const minutes = parts[1] ? parts[1].trim() : '00';

            if (isPM && hours < 12) hours += 12;
            if (!isPM && hours === 12) hours = 0;

            return String(hours).padStart(2, '0') + ':' + minutes.substring(0, 2);
        }

        const parts = timeStr.split(':');
        if (parts.length >= 2) {
            const h = String(parseInt(parts[0], 10)).padStart(2, '0');
            const m = parts[1].substring(0, 2);
            return h + ':' + m;
        }
        return timeStr;
    }

    function openEditModal(data) {
        document.getElementById('edit_mold_id').value = data.mold_id;
        document.getElementById('edit_orig_date').value = data.date;
        document.getElementById('edit_orig_ref').value = data.ref;
        document.getElementById('edit_orig_start').value = data.start;

        document.getElementById('edit_part_no').value = data.part_no;
        document.getElementById('edit_part_name').value = data.part_name;
        document.getElementById('edit_date').value = data.date;
        document.getElementById('edit_class_id').value = data.class_id;
        document.getElementById('edit_pic').value = data.pic;
        document.getElementById('edit_status').value = data.status;
        document.getElementById('edit_problem').value = data.problem;
        document.getElementById('edit_work').value = data.work;
        document.getElementById('edit_ref').value = data.ref;
        
        // Format otomatis ke 24 Jam saat membuka modal
        document.getElementById('edit_start').value = formatTo24Hour(data.start);
        document.getElementById('edit_finish').value = formatTo24Hour(data.finish);
        document.getElementById('edit_part_change').value = data.part_change;

        document.getElementById('modalEdit').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('modalEdit').style.display = 'none';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('modalEdit');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
</script>

</body>
</html>
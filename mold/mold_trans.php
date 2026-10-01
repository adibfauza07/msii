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
// 1. QUERY MASTER MOLD & MASTER CUSTOMER
// -------------------------------------------------------------
$sqlMasterMold = "SELECT MM.MOLD_ID, MM.PART_NO, MM.PART_NAME, MM.CUST_ALIAS 
                  FROM MOLD_MASTER MM 
                  WHERE MM.PART_NO IS NOT NULL 
                  ORDER BY MM.PART_NO ASC";
$resMasterMold = sqlsrv_query($conn, $sqlMasterMold);

if ($resMasterMold === false) {
    $sqlMasterMold = "SELECT MM.MOLD_ID, ICM.PART_NO, ICM.PART_NAME, ICM.CUST_ALIAS 
                      FROM MOLD_MASTER MM 
                      INNER JOIN ITEM_CUST_MOLD ICM ON MM.PART_ID = ICM.PART_ID 
                      WHERE ICM.PART_NO IS NOT NULL 
                      ORDER BY ICM.PART_NO ASC";
    $resMasterMold = sqlsrv_query($conn, $sqlMasterMold);
    if ($resMasterMold === false) {
        die("<pre>Gagal mengambil data master mold:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }
}

$arrMasterMold = [];
while ($m = sqlsrv_fetch_array($resMasterMold, SQLSRV_FETCH_ASSOC)) {
    $cleanNo = trim($m['PART_NO'], " \t\n\r\0\x0B'");
    $arrMasterMold[] = [
        'id'   => $m['MOLD_ID'],
        'no'   => $cleanNo,
        'name' => isset($m['PART_NAME']) ? trim($m['PART_NAME']) : '',
        'cust' => isset($m['CUST_ALIAS']) ? trim($m['CUST_ALIAS']) : ''
    ];
}

// Master Dropdown Customer untuk Filter Laporan
$sqlCust = "SELECT DISTINCT CUST_ALIAS FROM ITEM_CUST_MOLD WHERE CUST_ALIAS IS NOT NULL ORDER BY CUST_ALIAS ASC";
$resCust = sqlsrv_query($conn, $sqlCust);

// -------------------------------------------------------------
// 2. PROSES DELETE DATA TRANSAKSI MOLD
// -------------------------------------------------------------
if (isset($_POST['btnDeleteTrans'])) {
    $delIdTrans = !empty($_POST['del_id_trans']) ? intval($_POST['del_id_trans']) : null;
    $delMoldId  = !empty($_POST['del_mold_id']) ? intval($_POST['del_mold_id']) : null;
    $delDate    = !empty($_POST['del_date']) ? $_POST['del_date'] : null;

    if ($delIdTrans) {
        $sqlDelete = "DELETE FROM MOLD_TRANS_DETAIL WHERE ID_TRANS = ?";
        $stmtDel = sqlsrv_query($conn, $sqlDelete, [$delIdTrans]);
    } else {
        $sqlDelete = "DELETE FROM MOLD_TRANS_DETAIL 
                      WHERE MOLD_ID = ? AND CONVERT(VARCHAR(19), [DATE], 120) = ?";
        $stmtDel = sqlsrv_query($conn, $sqlDelete, [$delMoldId, $delDate]);
    }

    if ($stmtDel === false) {
        $err = sqlsrv_errors();
        $alertMsg = "Gagal menghapus transaksi: " . (isset($err[0]['message']) ? $err[0]['message'] : 'Kesalahan query');
        $alertType = "danger";
    } else {
        $alertMsg = "Transaksi mutasi mold berhasil dihapus!";
        $alertType = "success";
    }
}

// -------------------------------------------------------------
// 3. PROSES UPDATE (EDIT) DATA TRANSAKSI MOLD
// -------------------------------------------------------------
if (isset($_POST['btnUpdateTrans'])) {
    $editIdTrans   = !empty($_POST['edit_id_trans']) ? intval($_POST['edit_id_trans']) : null;
    $editMoldId    = intval($_POST['edit_mold_id']);
    
    $editDateRaw   = !empty($_POST['edit_date']) ? str_replace('T', ' ', $_POST['edit_date']) : date('Y-m-d H:i:s');
    $editDate      = date('Y-m-d H:i:s', strtotime($editDateRaw));

    $editJenis     = intval($_POST['edit_jenis_trans']); // 1 = IN, 2 = OUT
    $editMoldIn    = ($editJenis === 1) ? 1 : 0;
    $editMoldOut   = ($editJenis === 2) ? 1 : 0;
    $editReason    = substr(trim($_POST['edit_reason']), 0, 30);
    $editVendor    = substr(trim($_POST['edit_vendor']), 0, 30);
    $editSupplier  = substr(trim($_POST['edit_supplier']), 0, 30);

    if ($editIdTrans) {
        $sqlUpdate = "UPDATE MOLD_TRANS_DETAIL 
                      SET [DATE] = ?,
                          JENIS_TRANSAKSI = ?,
                          MOLD_IN = ?,
                          MOLD_OUT = ?,
                          REASON = ?,
                          VENDOR = ?,
                          SUPPLIER = ?
                      WHERE ID_TRANS = ?";
        $paramsUpd = [$editDate, $editJenis, $editMoldIn, $editMoldOut, $editReason, $editVendor, $editSupplier, $editIdTrans];
    } else {
        $editOrigDate = $_POST['edit_orig_date'];
        $sqlUpdate = "UPDATE MOLD_TRANS_DETAIL 
                      SET [DATE] = ?,
                          JENIS_TRANSAKSI = ?,
                          MOLD_IN = ?,
                          MOLD_OUT = ?,
                          REASON = ?,
                          VENDOR = ?,
                          SUPPLIER = ?
                      WHERE MOLD_ID = ? AND CONVERT(VARCHAR(19), [DATE], 120) = ?";
        $paramsUpd = [$editDate, $editJenis, $editMoldIn, $editMoldOut, $editReason, $editVendor, $editSupplier, $editMoldId, $editOrigDate];
    }

    $stmtUpd = sqlsrv_query($conn, $sqlUpdate, $paramsUpd);
    if ($stmtUpd === false) {
        $err = sqlsrv_errors();
        $alertMsg = "Gagal memperbarui data: " . (isset($err[0]['message']) ? $err[0]['message'] : 'Kesalahan query');
        $alertType = "danger";
    } else {
        $alertMsg = "Data transaksi berhasil diperbarui!";
        $alertType = "success";
    }
}

// -------------------------------------------------------------
// 4. SIMPAN TRANSAKSI MOLD BARU
// -------------------------------------------------------------
if (isset($_POST['btnSimpanTrans'])) {
    $moldId         = !empty($_POST['in_mold_id']) ? intval($_POST['in_mold_id']) : null;
    $rawDate        = !empty($_POST['in_date']) ? str_replace('T', ' ', $_POST['in_date']) : date('Y-m-d H:i:s');
    $transDate      = date('Y-m-d H:i:s', strtotime($rawDate));

    $jenisTransaksi = !empty($_POST['in_jenis_trans']) ? intval($_POST['in_jenis_trans']) : 1; // 1 = IN, 2 = OUT
    $moldIn         = ($jenisTransaksi === 1) ? 1 : 0;
    $moldOut        = ($jenisTransaksi === 2) ? 1 : 0;
    $reason         = !empty($_POST['in_reason']) ? substr(trim($_POST['in_reason']), 0, 30) : '';
    $vendor         = !empty($_POST['in_vendor']) ? substr(trim($_POST['in_vendor']), 0, 30) : '';
    $supplier       = !empty($_POST['in_supplier']) ? substr(trim($_POST['in_supplier']), 0, 30) : '';

    if (empty($moldId)) {
        $alertMsg = "Gagal Simpan: Part No / Mold harus dipilih dari master data!";
        $alertType = "danger";
    } else {
        $insertSql = "INSERT INTO MOLD_TRANS_DETAIL 
                      (MOLD_ID, [DATE], MOLD_IN, MOLD_OUT, REASON, VENDOR, SUPPLIER, JENIS_TRANSAKSI) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $paramsInsert = [$moldId, $transDate, $moldIn, $moldOut, $reason, $vendor, $supplier, $jenisTransaksi];
        $stmtInsert = sqlsrv_query($conn, $insertSql, $paramsInsert);

        if ($stmtInsert === false) {
            $errors = sqlsrv_errors();
            $alertMsg = "Gagal menyimpan transaksi: " . (isset($errors[0]['message']) ? $errors[0]['message'] : 'Kesalahan query');
            $alertType = "danger";
        } else {
            $alertMsg = "Transaksi Mold berhasil disimpan!";
            $alertType = "success";
        }
    }
}

// -------------------------------------------------------------
// 5. FILTER LAPORAN & EXPORT EXCEL
// -------------------------------------------------------------
$showReport = false;
$reportData = [];
$startDateForm = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
$endDateForm   = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');

$isExport = isset($_POST['btnExport']);
$isSubmit = isset($_POST['btnCetak']) || $isExport || isset($_POST['btnSimpanTrans']) || isset($_POST['btnUpdateTrans']) || isset($_POST['btnDeleteTrans']);

if ($isSubmit) {
    $startDate = !empty($_POST['start_date']) ? date('Ymd', strtotime($_POST['start_date'])) : date('Ym01');
    $endDate   = !empty($_POST['end_date'])   ? date('Ymd', strtotime($_POST['end_date']))   : date('Ymt');

    // Pembersihan otomatis karakter kutip (') dan spasi
    $rawMoldNo = isset($_POST['mold_no']) ? trim($_POST['mold_no'], " \t\n\r\0\x0B'") : '';
    $moldNo    = ($rawMoldNo !== '') ? '%' . $rawMoldNo . '%' : '%';

    $rawCust   = isset($_POST['cust_alias']) ? trim($_POST['cust_alias'], " \t\n\r\0\x0B'") : '';
    $custAlias = ($rawCust !== '') ? '%' . $rawCust . '%' : '%';
    
    $tsql = "{call sp_trans_mold_new(?, ?, ?, ?)}";
    $params = [
        [$startDate, SQLSRV_PARAM_IN],
        [$endDate,   SQLSRV_PARAM_IN],
        [$moldNo,    SQLSRV_PARAM_IN],
        [$custAlias, SQLSRV_PARAM_IN]
    ];
    
    $options = array("Scrollable" => SQLSRV_CURSOR_KEYSET);
    $stmt = sqlsrv_query($conn, $tsql, $params, $options);
    
    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $reportData[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    $showReport = true;

    if ($isExport) {
        $fileName = "Laporan_Mutasi_Mold_" . date('Ymd_His') . ".xls";
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$fileName\"");
        header("Pragma: no-cache");
        header("Expires: 0");
        ?>
        <table border="1">
            <thead>
                <tr style="background-color: #2e4053; color: #ffffff; font-weight: bold;">
                    <th>NO. PART</th>
                    <th>NAMA PART</th>
                    <th>ALIAS CUST</th>
                    <th>TANGGAL MUTASI</th>
                    <th>MOLD IN</th>
                    <th>MOLD OUT</th>
                    <th>REASON / KETERANGAN</th>
                    <th>VENDOR</th>
                    <th>SUPPLIER</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($reportData)): ?>
                    <?php foreach ($reportData as $row): ?>
                        <tr>
                            <td style="mso-number-format:'\@';"><?php echo isset($row['PART_NO']) ? h($row['PART_NO']) : '-'; ?></td>
                            <td><?php echo isset($row['PART_NAME']) ? h($row['PART_NAME']) : '-'; ?></td>
                            <td><?php echo isset($row['CUST_ALIAS']) ? h($row['CUST_ALIAS']) : '-'; ?></td>
                            <td><?php echo (isset($row['DATE']) && $row['DATE'] instanceof DateTime) ? h($row['DATE']->format('d-M-Y H:i')) : '-'; ?></td>
                            <td align="right"><?php echo (isset($row['MOLD_IN']) && ($row['MOLD_IN'] == 1 || $row['MOLD_IN'] === true)) ? '1' : '0'; ?></td>
                            <td align="right"><?php echo (isset($row['MOLD_OUT']) && ($row['MOLD_OUT'] == 1 || $row['MOLD_OUT'] === true)) ? '1' : '0'; ?></td>
                            <td><?php echo isset($row['REASON']) ? h($row['REASON']) : '-'; ?></td>
                            <td><?php echo isset($row['VENDOR']) ? h($row['VENDOR']) : '-'; ?></td>
                            <td><?php echo isset($row['SUPPLIER']) ? h($row['SUPPLIER']) : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
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
    <title>Mold Transaction Entry & Report</title>
    <style>
        body { padding: 15px; background: #d4d0c8; font-family: Tahoma, Arial, sans-serif; font-size: 11px; margin: 0; color: #000; }
        .box-container { border: 1px solid #808080; background: #eeeeee; padding: 12px; margin-bottom: 15px; }
        .box-title { margin-top: 0; border-bottom: 1px solid #808080; padding-bottom: 4px; color: #2e4053; font-size: 12px; font-weight: bold; }
        
        .form-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 10px; }
        .form-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 10px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 3px; }
        .form-group input, .form-group select { width: 100%; padding: 4px; box-sizing: border-box; font-family: Tahoma; font-size: 11px; border: 1px solid #7f9db9; background: #ffffff; }
        .form-group input[readonly] { background: #e0e0e0; color: #333; }

        .btn-action { background: #d4d0c8; border: 2px outset #ffffff; padding: 5px 15px; font-weight: bold; cursor: pointer; font-family: Tahoma; font-size: 11px; }
        .btn-action:active { border: 2px inset #ffffff; }
        .btn-save { background: #2980b9; color: #fff; border: 2px outset #5dade2; }
        .btn-save:hover { background: #1f618d; }

        .btn-edit { background: #f39c12; color: #fff; border: 1px solid #d68910; padding: 2px 6px; font-size: 10px; font-weight: bold; cursor: pointer; border-radius: 2px; }
        .btn-del { background: #c0392b; color: #fff; border: 1px solid #962d22; padding: 2px 6px; font-size: 10px; font-weight: bold; cursor: pointer; border-radius: 2px; margin-left: 2px; }
        .btn-edit:hover { background: #d68910; }
        .btn-del:hover { background: #962d22; }

        .alert { padding: 8px 12px; margin-bottom: 12px; border-radius: 2px; font-weight: bold; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .report-table { width: 100%; border-collapse: collapse; background: #ffffff; margin-top: 10px; }
        .report-table th { background: #2e4053; color: white; padding: 7px; border: 1px solid #7f8c8d; font-size: 10px; }
        .report-table td { padding: 5px; border: 1px solid #bdc3c7; }
        .report-table tr:nth-child(even) { background: #f8f9fa; }
        .text-center { text-align: center; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 9999; justify-content: center; align-items: center; }
        .modal-content { background: #eeeeee; border: 2px solid #2c3e50; border-radius: 4px; width: 700px; max-width: 95%; padding: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.4); font-family: Tahoma, Arial, sans-serif; font-size: 11px; }
        .modal-header { font-size: 13px; font-weight: bold; color: #2c3e50; border-bottom: 1px solid #808080; padding-bottom: 6px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { cursor: pointer; font-size: 16px; font-weight: bold; color: #c0392b; }

        @media print { .box-container, .alert, .col-action, .modal-overlay { display: none !important; } body { background: #fff; padding: 0; } }
    </style>
</head>
<body>

<?php if (!empty($alertMsg)): ?>
    <div class="alert alert-<?php echo $alertType; ?>"><?php echo h($alertMsg); ?></div>
<?php endif; ?>

<!-- FORM INPUT TRANSAKSI MUTASI MOLD BARU -->
<div class="box-container">
    <div class="box-title">INPUT TRANSAKSI MUTASI MOLD BARU (MOLD_TRANS_DETAIL)</div>
    <form method="POST" action="">
        <div class="form-grid-3">
            <div class="form-group">
                <label>PART NO <span style="color:red;">*</span></label>
                <input type="text" id="input_part_no" list="list_master_part" placeholder="Ketik Part No..." required autocomplete="off">
                <datalist id="list_master_part">
                    <?php foreach ($arrMasterMold as $m): ?>
                        <option value="<?php echo h($m['no']); ?>"><?php echo h($m['name']); ?></option>
                    <?php endforeach; ?>
                </datalist>
                <input type="hidden" name="in_mold_id" id="in_mold_id" required>
            </div>
            <div class="form-group">
                <label>MOLD NAME (PART NAME)</label>
                <input type="text" id="in_mold_name" readonly placeholder="(Otomatis terisi)">
            </div>
            <div class="form-group">
                <label>CUSTOMER ALIAS</label>
                <input type="text" id="in_cust_alias" readonly placeholder="(Otomatis terisi)">
            </div>
        </div>

        <div class="form-grid-4">
            <div class="form-group">
                <label>TANGGAL TRANSAKSI</label>
                <input type="datetime-local" name="in_date" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
            </div>
            <div class="form-group">
                <label>JENIS TRANSAKSI</label>
                <select name="in_jenis_trans" required>
                    <option value="1">MOLD IN (1)</option>
                    <option value="2">MOLD OUT (2)</option>
                </select>
            </div>
            <div class="form-group">
                <label>VENDOR</label>
                <input type="text" name="in_vendor" maxlength="30" placeholder="Nama Vendor...">
            </div>
            <div class="form-group">
                <label>SUPPLIER</label>
                <input type="text" name="in_supplier" maxlength="30" placeholder="Nama Supplier...">
            </div>
        </div>

        <div style="display: flex; gap: 10px; align-items: flex-end;">
            <div class="form-group" style="flex: 1;">
                <label>REASON / KETERANGAN (Maks 30 Karakter)</label>
                <input type="text" name="in_reason" maxlength="30" placeholder="Alasan mutasi / perpindahan...">
            </div>
            <div style="margin-bottom: 1px;">
                <button type="submit" name="btnSimpanTrans" class="btn-action btn-save">SIMPAN TRANSAKSI BARU</button>
            </div>
        </div>
    </form>
</div>

<!-- FORM FILTER LAPORAN TRANSAKSI & EXPORT EXCEL -->
<div class="box-container">
    <div class="box-title">FILTER LAPORAN TRANSAKSI (MUTASI) MOLD</div>
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
                <input type="text" name="mold_no" list="filter_part_list" value="<?php echo (isset($_POST['mold_no'])) ? h(trim($_POST['mold_no'], "'")) : ''; ?>" placeholder="Ketik atau pilih...">
                <datalist id="filter_part_list">
                    <?php foreach ($arrMasterMold as $m): ?>
                        <option value="<?php echo h($m['no']); ?>"><?php echo h($m['name']); ?></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            
            <div class="form-group">
                <label>Alias Customer</label>
                <input type="text" name="cust_alias" list="filter_cust_list" value="<?php echo (isset($_POST['cust_alias'])) ? h(trim($_POST['cust_alias'], "'")) : ''; ?>" placeholder="Ketik atau pilih...">
                <datalist id="filter_cust_list">
                    <?php while($c = sqlsrv_fetch_array($resCust, SQLSRV_FETCH_ASSOC)): ?>
                        <option value="<?php echo h(trim($c['CUST_ALIAS'], "'")); ?>"></option>
                    <?php endwhile; ?>
                </datalist>
            </div>
        </div>
        <button type="submit" name="btnCetak" class="btn-action">TAMPILKAN TRANSAKSI</button>
        <?php if($showReport): ?>
            <button type="submit" name="btnExport" class="btn-action" style="margin-left: 5px; background: #1d6f42; color: white; border-color: #1d6f42;">EXPORT EXCEL</button>
            <button type="button" class="btn-action" onclick="window.print();" style="margin-left: 5px; background: #27ae60; color: white; border-color: #27ae60;">PRINT / PDF</button>
        <?php endif; ?>
    </form>
</div>

<!-- TABEL LAPORAN HASIL TRANSAKSI MUTASI -->
<?php if ($showReport): ?>
    <?php if (!empty($reportData)): ?>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 14%;">NO. PART</th>
                    <th style="width: 18%;">NAMA PART</th>
                    <th style="width: 8%;">ALIAS CUST</th>
                    <th style="width: 12%;">TANGGAL MUTASI</th>
                    <th style="width: 6%;">MOLD IN</th>
                    <th style="width: 6%;">MOLD OUT</th>
                    <th style="width: 14%;">REASON / KETERANGAN</th>
                    <th style="width: 8%;">VENDOR</th>
                    <th style="width: 8%;">SUPPLIER</th>
                    <th class="col-action" style="width: 8%;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportData as $row): ?>
                    <?php
                        $partNo   = isset($row['PART_NO']) ? trim($row['PART_NO'], " \t\n\r\0\x0B'") : '';
                        $partName = isset($row['PART_NAME']) ? trim($row['PART_NAME']) : '';
                        $rDate    = isset($row['DATE']) ? $row['DATE'] : null;
                        $rIn      = (isset($row['MOLD_IN']) && ($row['MOLD_IN'] == 1 || $row['MOLD_IN'] === true)) ? 1 : 0;
                        $rOut     = (isset($row['MOLD_OUT']) && ($row['MOLD_OUT'] == 1 || $row['MOLD_OUT'] === true)) ? 1 : 0;
                        $rReason  = isset($row['REASON']) ? trim($row['REASON']) : '';
                        $rVendor  = isset($row['VENDOR']) ? trim($row['VENDOR']) : '';
                        $rSupplier= isset($row['SUPPLIER']) ? trim($row['SUPPLIER']) : '';
                        $idTrans  = isset($row['ID_TRANS']) ? intval($row['ID_TRANS']) : 0;

                        $strDateIso = ($rDate instanceof DateTime) ? $rDate->format('Y-m-d\TH:i') : '';
                        $strDateSql = ($rDate instanceof DateTime) ? $rDate->format('Y-m-d H:i:s') : '';
                        $strDateDisplay = ($rDate instanceof DateTime) ? $rDate->format('d-M-Y H:i') : '-';

                        $curMoldId = 0;
                        foreach ($arrMasterMold as $mm) {
                            if (strcasecmp($mm['no'], $partNo) === 0) {
                                $curMoldId = $mm['id'];
                                break;
                            }
                        }
                    ?>
                    <tr>
                        <td><?php echo h($partNo); ?></td>
                        <td><?php echo h($partName); ?></td>
                        <td><?php echo isset($row['CUST_ALIAS']) ? h($row['CUST_ALIAS']) : '-'; ?></td>
                        <td class="text-center"><?php echo h($strDateDisplay); ?></td>
                        <td class="text-center" style="font-weight:bold; color:<?php echo ($rIn == 1) ? '#27ae60' : '#888'; ?>;">
                            <?php echo $rIn; ?>
                        </td>
                        <td class="text-center" style="font-weight:bold; color:<?php echo ($rOut == 1) ? '#c0392b' : '#888'; ?>;">
                            <?php echo $rOut; ?>
                        </td>
                        <td><?php echo ($rReason !== '') ? h($rReason) : '-'; ?></td>
                        <td><?php echo ($rVendor !== '') ? h($rVendor) : '-'; ?></td>
                        <td><?php echo ($rSupplier !== '') ? h($rSupplier) : '-'; ?></td>

                        <td class="col-action text-center" style="white-space: nowrap;">
                            <button type="button" class="btn-edit" 
                                onclick='openEditTransModal(<?php echo json_encode([
                                    "id_trans"  => $idTrans,
                                    "mold_id"   => $curMoldId,
                                    "part_no"   => $partNo,
                                    "part_name" => $partName,
                                    "date"      => $strDateIso,
                                    "date_sql"  => $strDateSql,
                                    "jenis"     => ($rIn == 1) ? 1 : 2,
                                    "reason"    => $rReason,
                                    "vendor"    => $rVendor,
                                    "supplier"  => $rSupplier
                                ]); ?>)'>
                                EDIT
                            </button>

                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi ini?');">
                                <input type="hidden" name="start_date" value="<?php echo h($startDateForm); ?>">
                                <input type="hidden" name="end_date" value="<?php echo h($endDateForm); ?>">
                                <input type="hidden" name="mold_no" value="<?php echo isset($_POST['mold_no']) ? h($_POST['mold_no']) : ''; ?>">
                                <input type="hidden" name="cust_alias" value="<?php echo isset($_POST['cust_alias']) ? h($_POST['cust_alias']) : ''; ?>">
                                <input type="hidden" name="del_id_trans" value="<?php echo h($idTrans); ?>">
                                <input type="hidden" name="del_mold_id" value="<?php echo h($curMoldId); ?>">
                                <input type="hidden" name="del_date" value="<?php echo h($strDateSql); ?>">
                                <button type="submit" name="btnDeleteTrans" class="btn-del">HAPUS</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div style="text-align:center; padding:20px; background:#fff; border:1px solid #808080; font-weight:bold;">
            Data transaksi tidak ditemukan.
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- MODAL POPUP EDIT TRANSAKSI MOLD -->
<div id="modalEditTrans" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <span>EDIT TRANSAKSI MUTASI MOLD</span>
            <span class="modal-close" onclick="closeEditTransModal()">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="start_date" value="<?php echo h($startDateForm); ?>">
            <input type="hidden" name="end_date" value="<?php echo h($endDateForm); ?>">
            <input type="hidden" name="mold_no" value="<?php echo isset($_POST['mold_no']) ? h($_POST['mold_no']) : ''; ?>">
            <input type="hidden" name="cust_alias" value="<?php echo isset($_POST['cust_alias']) ? h($_POST['cust_alias']) : ''; ?>">

            <input type="hidden" name="edit_id_trans" id="edit_id_trans">
            <input type="hidden" name="edit_mold_id" id="edit_mold_id">
            <input type="hidden" name="edit_orig_date" id="edit_orig_date">

            <div class="form-grid-3">
                <div class="form-group">
                    <label>PART NO</label>
                    <input type="text" id="edit_part_no" readonly>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>MOLD NAME (PART NAME)</label>
                    <input type="text" id="edit_part_name" readonly>
                </div>
            </div>

            <div class="form-grid-4">
                <div class="form-group">
                    <label>TANGGAL TRANSAKSI</label>
                    <input type="datetime-local" name="edit_date" id="edit_date" required>
                </div>
                <div class="form-group">
                    <label>JENIS TRANSAKSI</label>
                    <select name="edit_jenis_trans" id="edit_jenis_trans" required>
                        <option value="1">MOLD IN (1)</option>
                        <option value="2">MOLD OUT (2)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>VENDOR</label>
                    <input type="text" name="edit_vendor" id="edit_vendor" maxlength="30">
                </div>
                <div class="form-group">
                    <label>SUPPLIER</label>
                    <input type="text" name="edit_supplier" id="edit_supplier" maxlength="30">
                </div>
            </div>

            <div class="form-group">
                <label>REASON / KETERANGAN</label>
                <input type="text" name="edit_reason" id="edit_reason" maxlength="30">
            </div>

            <div style="text-align: right; margin-top: 15px;">
                <button type="button" class="btn-action" onclick="closeEditTransModal()" style="margin-right: 5px;">BATAL</button>
                <button type="submit" name="btnUpdateTrans" class="btn-action btn-save">SIMPAN PERUBAHAN</button>
            </div>
        </form>
    </div>
</div>

<script>
    const masterMoldList = <?php echo json_encode($arrMasterMold); ?>;
    const inputPartNo = document.getElementById('input_part_no');
    const inputMoldId = document.getElementById('in_mold_id');
    const inputMoldName = document.getElementById('in_mold_name');
    const inputCustAlias = document.getElementById('in_cust_alias');

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

    function openEditTransModal(data) {
        document.getElementById('edit_id_trans').value = data.id_trans;
        document.getElementById('edit_mold_id').value = data.mold_id;
        document.getElementById('edit_orig_date').value = data.date_sql;

        document.getElementById('edit_part_no').value = data.part_no;
        document.getElementById('edit_part_name').value = data.part_name;
        document.getElementById('edit_date').value = data.date;
        document.getElementById('edit_jenis_trans').value = data.jenis;
        document.getElementById('edit_vendor').value = data.vendor;
        document.getElementById('edit_supplier').value = data.supplier;
        document.getElementById('edit_reason').value = data.reason;

        document.getElementById('modalEditTrans').style.display = 'flex';
    }

    function closeEditTransModal() {
        document.getElementById('modalEditTrans').style.display = 'none';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('modalEditTrans');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
</script>

</body>
</html>
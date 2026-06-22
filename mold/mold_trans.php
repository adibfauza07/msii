<?php
ini_set('memory_limit', '512M');
require_once __DIR__ . "/../config/database_mold.php";

// 1. QUERY UNTUK DROPDOWN SEARCHABLE (DATALIST)
// Ambil list master nomor part yang ada di sistem
$sqlPart = "SELECT DISTINCT PART_NO, PART_NAME FROM ITEM_CUST_MOLD WHERE PART_NO IS NOT NULL ORDER BY PART_NO ASC";
$resPart = sqlsrv_query($conn, $sqlPart);

// Ambil list master customer alias yang ada di sistem
$sqlCust = "SELECT DISTINCT CUST_ALIAS FROM ITEM_CUST_MOLD WHERE CUST_ALIAS IS NOT NULL ORDER BY CUST_ALIAS ASC";
$resCust = sqlsrv_query($conn, $sqlCust);


$showReport = false;
$reportData = [];

if (isset($_POST['btnCetak'])) {
    $startDate = !empty($_POST['start_date']) ? str_replace('-', '', $_POST['start_date']) : date('Ym01');
    $endDate   = !empty($_POST['end_date']) ? str_replace('-', '', $_POST['end_date']) : date('Ymt');
    $moldNo    = (!empty($_POST['mold_no'])) ? $_POST['mold_no'] : '%';
    $custAlias = (!empty($_POST['cust_alias'])) ? $_POST['cust_alias'] : '%';
    
    $tsql = "{call sp_trans_mold_new(?, ?, ?, ?)}";
    $params = [
        [$startDate, SQLSRV_PARAM_IN],
        [$endDate, SQLSRV_PARAM_IN],
        [$moldNo, SQLSRV_PARAM_IN],
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
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Mold Transaction Report</title>
    <style>
        body { padding: 15px; background: #d4d0c8; font-family: Tahoma, Arial, sans-serif; font-size: 11px; }
        .filter-container { border: 1px solid #808080; background: #eeeeee; padding: 15px; margin-bottom: 15px; }
        .form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 12px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 4px; }
        .form-group input { width: 100%; padding: 4px; box-sizing: border-box; font-family: Tahoma; font-size: 11px; border: 1px solid #7f9db9; background: #ffffff; }
        .btn-action { background: #d4d0c8; border: 2px outset #ffffff; padding: 6px 16px; font-weight: bold; cursor: pointer; font-family: Tahoma; font-size: 11px; }
        .btn-action:active { border: 2px inset #ffffff; }
        .report-table { width: 100%; border-collapse: collapse; background: #ffffff; margin-top: 10px; }
        .report-table th { background: #2e4053; color: white; padding: 8px; border: 1px solid #7f8c8d; }
        .report-table td { padding: 6px; border: 1px solid #bdc3c7; }
        .report-table tr:nth-child(even) { background: #f8f9fa; }
        @media print { .filter-container { display: none; } body { background: #fff; } }
    </style>
</head>
<body>

<div class="filter-container">
    <h3 style="margin-top: 0; border-bottom: 1px solid #808080; padding-bottom: 4px; color: #2e4053;">FILTER LAPORAN TRANSAKSI (MUTASI) MOLD</h3>
    <form method="POST" action="">
        <div class="form-grid">
            <div class="form-group">
                <label>Tanggal Mulai</label>
                <input type="date" name="start_date" value="<?php echo isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01'); ?>" required>
            </div>
            <div class="form-group">
                <label>Tanggal Selesai</label>
                <input type="date" name="end_date" value="<?php echo isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d'); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Nomor Mold (Part No)</label>
                <input type="text" name="mold_no" list="part_list" value="<?php echo (isset($_POST['mold_no'])) ? h($_POST['mold_no']) : ''; ?>" placeholder="Ketik atau pilih...">
                <datalist id="part_list">
                    <?php while($p = sqlsrv_fetch_array($resPart, SQLSRV_FETCH_ASSOC)): ?>
                        <option value="<?php echo h($p['PART_NO']); ?>"><?php echo h($p['PART_NAME']); ?></option>
                    <?php endwhile; ?>
                </datalist>
            </div>
            
            <div class="form-group">
                <label>Alias Customer</label>
                <input type="text" name="cust_alias" list="cust_list" value="<?php echo (isset($_POST['cust_alias'])) ? h($_POST['cust_alias']) : ''; ?>" placeholder="Ketik atau pilih...">
                <datalist id="cust_list">
                    <?php while($c = sqlsrv_fetch_array($resCust, SQLSRV_FETCH_ASSOC)): ?>
                        <option value="<?php echo h($c['CUST_ALIAS']); ?>"></option>
                    <?php endwhile; ?>
                </datalist>
            </div>
        </div>
        <button type="submit" name="btnCetak" class="btn-action">TAMPILKAN TRANSAKSI</button>
        <?php if($showReport): ?>
            <button type="button" class="btn-action" onclick="window.print();" style="margin-left: 5px; background: #27ae60; color: white; border-color: #27ae60;">PRINT / PDF</button>
        <?php endif; ?>
    </form>
</div>

<?php if ($showReport): ?>
    <?php if (!empty($reportData)): ?>
        <table class="report-table">
            <thead>
                <tr>
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
                <?php foreach ($reportData as $row): ?>
                    <tr>
                        <td><?php echo isset($row['PART_NO']) ? h($row['PART_NO']) : '-'; ?></td>
                        <td><?php echo isset($row['PART_NAME']) ? h($row['PART_NAME']) : '-'; ?></td>
                        <td><?php echo isset($row['CUST_ALIAS']) ? h($row['CUST_ALIAS']) : '-'; ?></td>
                        <td><?php echo isset($row['DATE']) ? h($row['DATE']->format('d-M-Y H:i')) : '-'; ?></td>
                        <td><?php echo isset($row['MOLD_IN']) ? h($row['MOLD_IN']) : '0'; ?></td>
                        <td><?php echo isset($row['MOLD_OUT']) ? h($row['MOLD_OUT']) : '0'; ?></td>
                        <td><?php echo isset($row['REASON']) ? h($row['REASON']) : '-'; ?></td>
                        <td><?php echo isset($row['VENDOR']) ? h($row['VENDOR']) : '-'; ?></td>
                        <td><?php echo isset($row['SUPPLIER']) ? h($row['SUPPLIER']) : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div style="text-align:center; padding:20px; background:#fff; border:1px solid #808080; font-weight:bold;">Data transaksi tidak ditemukan.</div>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>
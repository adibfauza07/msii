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
// 1. QUERY MASTER MOLD & MASTER LOKASI (SESUAI DELPHI dm1)
// -------------------------------------------------------------
$sqlMasterMold = "SELECT MOLD_ID, PART_NO, PART_NAME, CUST_ALIAS 
                  FROM MOLD_MASTER 
                  WHERE PART_NO IS NOT NULL 
                  ORDER BY PART_NO ASC";
$resMasterMold = sqlsrv_query($conn, $sqlMasterMold);

if ($resMasterMold === false) {
    die("<pre>Gagal mengambil data MOLD_MASTER:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$arrMasterMold = [];
$mapMoldById = [];
while ($m = sqlsrv_fetch_array($resMasterMold, SQLSRV_FETCH_ASSOC)) {
    $mId = intval($m['MOLD_ID']);
    $cleanNo = trim($m['PART_NO'], " \t\n\r\0\x0B'");
    $item = [
        'id'   => $mId,
        'no'   => $cleanNo,
        'name' => isset($m['PART_NAME']) ? trim($m['PART_NAME']) : '',
        'cust' => isset($m['CUST_ALIAS']) ? trim($m['CUST_ALIAS']) : ''
    ];
    $arrMasterMold[] = $item;
    $mapMoldById[$mId] = $item;
}

// Lookup DS_loc_trans (MOLD_TRAN_LOC + TIPE)
$sqlLoc = "SELECT MTL.ID, MTL.NAMA, MTLT.[TYPE] AS TIPE_DESC 
           FROM MOLD_TRAN_LOC MTL 
           LEFT JOIN MOLD_TRAN_LOC_TYPE MTLT ON MTL.TIPE_ID = MTLT.ID 
           ORDER BY MTL.NAMA ASC";
$resLoc = sqlsrv_query($conn, $sqlLoc);
if ($resLoc === false) {
    $sqlLoc = "SELECT ID, NAMA, '' AS TIPE_DESC FROM MOLD_TRAN_LOC ORDER BY NAMA ASC";
    $resLoc = sqlsrv_query($conn, $sqlLoc);
}

$arrLoc = [];
if ($resLoc !== false) {
    while ($l = sqlsrv_fetch_array($resLoc, SQLSRV_FETCH_ASSOC)) {
        $arrLoc[] = [
            'id'   => intval($l['ID']),
            'nama' => trim($l['NAMA']),
            'tipe' => isset($l['TIPE_DESC']) ? trim($l['TIPE_DESC']) : ''
        ];
    }
}

// Lookup Customer untuk Filter
$sqlCust = "SELECT DISTINCT CUST_ALIAS FROM MOLD_MASTER WHERE CUST_ALIAS IS NOT NULL AND CUST_ALIAS <> '' ORDER BY CUST_ALIAS ASC";
$resCust = sqlsrv_query($conn, $sqlCust);
$arrCust = [];
if ($resCust !== false) {
    while ($c = sqlsrv_fetch_array($resCust, SQLSRV_FETCH_ASSOC)) {
        $arrCust[] = trim($c['CUST_ALIAS'], " \t\n\r\0\x0B'");
    }
}

// -------------------------------------------------------------
// 2. PROSES DELETE DATA TRANSAKSI MOLD (BERDASARKAN ID_TRANS)
// -------------------------------------------------------------
if (isset($_POST['btnDeleteTrans'])) {
    $delIdTrans = !empty($_POST['del_id_trans']) ? intval($_POST['del_id_trans']) : 0;

    if ($delIdTrans > 0) {
        $sqlDelete = "DELETE FROM MOLD_TRANS_DETAIL WHERE ID_TRANS = ?";
        $stmtDel = sqlsrv_query($conn, $sqlDelete, [$delIdTrans]);

        if ($stmtDel === false) {
            $err = sqlsrv_errors();
            $alertMsg = "Gagal menghapus transaksi: " . (isset($err[0]['message']) ? $err[0]['message'] : 'Kesalahan query');
            $alertType = "danger";
        } else {
            $alertMsg = "Data transaksi mutasi mold berhasil dihapus!";
            $alertType = "success";
        }
    } else {
        $alertMsg = "Gagal Hapus: ID Transaksi tidak valid.";
        $alertType = "danger";
    }
}

// -------------------------------------------------------------
// 3. PROSES UPDATE (EDIT) DATA TRANSAKSI MOLD
// -------------------------------------------------------------
if (isset($_POST['btnUpdateTrans'])) {
    $editIdTrans = !empty($_POST['edit_id_trans']) ? intval($_POST['edit_id_trans']) : 0;
    $editDate    = !empty($_POST['edit_date']) ? date('Y-m-d', strtotime($_POST['edit_date'])) : date('Y-m-d');
    $editJenis   = !empty($_POST['edit_jenis_trans']) ? intval($_POST['edit_jenis_trans']) : 1; // 1 = IN, 2 = OUT
    $editDari    = !empty($_POST['edit_dari_tran']) ? intval($_POST['edit_dari_tran']) : null;
    $editKe      = !empty($_POST['edit_ke_tran']) ? intval($_POST['edit_ke_tran']) : null;
    $editReason  = isset($_POST['edit_reason']) ? substr(trim($_POST['edit_reason']), 0, 30) : '';

    $moldIn  = ($editJenis === 1) ? 1 : 0;
    $moldOut = ($editJenis === 2) ? 1 : 0;

    if ($editIdTrans > 0) {
        $sqlUpdate = "UPDATE MOLD_TRANS_DETAIL 
                      SET [DATE] = ?,
                          JENIS_TRANSAKSI = ?,
                          MOLD_IN = ?,
                          MOLD_OUT = ?,
                          DARI_TRAN = ?,
                          KE_TRAN = ?,
                          REASON = ?
                      WHERE ID_TRANS = ?";
        $paramsUpd = [$editDate, $editJenis, $moldIn, $moldOut, $editDari, $editKe, $editReason, $editIdTrans];
        $stmtUpd = sqlsrv_query($conn, $sqlUpdate, $paramsUpd);

        if ($stmtUpd === false) {
            $err = sqlsrv_errors();
            $alertMsg = "Gagal memperbarui data: " . (isset($err[0]['message']) ? $err[0]['message'] : 'Kesalahan query');
            $alertType = "danger";
        } else {
            $alertMsg = "Data transaksi berhasil diperbarui!";
            $alertType = "success";
        }
    } else {
        $alertMsg = "Gagal Update: ID Transaksi tidak ditemukan.";
        $alertType = "danger";
    }
}

// -------------------------------------------------------------
// 4. SIMPAN TRANSAKSI MOLD BARU (SESUAI DS_Mold_Trans DELPHI)
// -------------------------------------------------------------
if (isset($_POST['btnSimpanTrans'])) {
    $moldId         = !empty($_POST['in_mold_id']) ? intval($_POST['in_mold_id']) : 0;
    $transDate      = !empty($_POST['in_date']) ? date('Y-m-d', strtotime($_POST['in_date'])) : date('Y-m-d');
    $jenisTransaksi = !empty($_POST['in_jenis_trans']) ? intval($_POST['in_jenis_trans']) : 1; // 1 = IN, 2 = OUT
    $dariTran       = !empty($_POST['in_dari_tran']) ? intval($_POST['in_dari_tran']) : null;
    $keTran         = !empty($_POST['in_ke_tran']) ? intval($_POST['in_ke_tran']) : null;
    $reason         = isset($_POST['in_reason']) ? substr(trim($_POST['in_reason']), 0, 30) : '';

    $moldIn  = ($jenisTransaksi === 1) ? 1 : 0;
    $moldOut = ($jenisTransaksi === 2) ? 1 : 0;

    if ($moldId <= 0) {
        $alertMsg = "Gagal Simpan: PART NO harus dipilih dari daftar Master Mold!";
        $alertType = "danger";
    } elseif (empty($dariTran)) {
        $alertMsg = "Gagal Simpan: Lokasi DARI wajib dipilih!";
        $alertType = "danger";
    } else {
        $insertSql = "INSERT INTO MOLD_TRANS_DETAIL 
                      (MOLD_ID, [DATE], JENIS_TRANSAKSI, MOLD_IN, MOLD_OUT, DARI_TRAN, KE_TRAN, REASON) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $paramsInsert = [$moldId, $transDate, $jenisTransaksi, $moldIn, $moldOut, $dariTran, $keTran, $reason];
        $stmtInsert = sqlsrv_query($conn, $insertSql, $paramsInsert);

        if ($stmtInsert === false) {
            $errors = sqlsrv_errors();
            $alertMsg = "Gagal menyimpan transaksi: " . (isset($errors[0]['message']) ? $errors[0]['message'] : 'Kesalahan query');
            $alertType = "danger";
        } else {
            $alertMsg = "Transaksi Mold berhasil disimpan ke database & langsung sinkron dengan Delphi!";
            $alertType = "success";
            if (isset($mapMoldById[$moldId])) {
                $_REQUEST['mold_no'] = $mapMoldById[$moldId]['no'];
                $_REQUEST['page'] = 1;
            }
        }
    }
}

// -------------------------------------------------------------
// 5. FILTER, PAGINATION & EXPORT EXCEL
// -------------------------------------------------------------
$filterMoldNo  = isset($_REQUEST['mold_no']) ? trim($_REQUEST['mold_no'], " \t\n\r\0\x0B'") : '';
$filterCust    = isset($_REQUEST['cust_alias']) ? trim($_REQUEST['cust_alias'], " \t\n\r\0\x0B'") : '';
$startDateForm = isset($_REQUEST['start_date']) ? $_REQUEST['start_date'] : '';
$endDateForm   = isset($_REQUEST['end_date'])   ? $_REQUEST['end_date']   : '';

// Pengaturan Pagination (10, 15, 20, 25 baris per halaman)
$allowedLimits = [10, 15, 20, 25];
$perPage = isset($_REQUEST['per_page']) && in_array(intval($_REQUEST['per_page']), $allowedLimits) 
           ? intval($_REQUEST['per_page']) 
           : 15;
$currentPage = isset($_REQUEST['page']) && intval($_REQUEST['page']) > 0 ? intval($_REQUEST['page']) : 1;

$isExport = isset($_POST['btnExport']);

$whereClauses = ["1=1"];
$queryParams  = [];

if ($filterMoldNo !== '') {
    $whereClauses[] = "MM.PART_NO LIKE ?";
    $queryParams[]  = '%' . $filterMoldNo . '%';
}

if ($filterCust !== '') {
    $whereClauses[] = "MM.CUST_ALIAS LIKE ?";
    $queryParams[]  = '%' . $filterCust . '%';
}

if ($startDateForm !== '') {
    $whereClauses[] = "MTD.[DATE] >= ?";
    $queryParams[]  = date('Y-m-d 00:00:00', strtotime($startDateForm));
}

if ($endDateForm !== '') {
    $whereClauses[] = "MTD.[DATE] <= ?";
    $queryParams[]  = date('Y-m-d 23:59:59', strtotime($endDateForm));
}

$whereSql = implode(" AND ", $whereClauses);

// Export Excel (Mengunduh seluruh data sesuai filter tanpa terpotong halaman)
if ($isExport) {
    $sqlExport = "SELECT 
                    MTD.ID_TRANS,
                    MTD.MOLD_ID,
                    MM.PART_NO,
                    MM.PART_NAME,
                    MM.CUST_ALIAS,
                    MTD.[DATE],
                    MTD.JENIS_TRANSAKSI,
                    MTD.MOLD_IN,
                    MTD.MOLD_OUT,
                    MTD.DARI_TRAN,
                    LOC_DARI.NAMA AS DARI_DESC,
                    MTD.KE_TRAN,
                    LOC_KE.NAMA AS KE_DESC,
                    MTD.REASON
                 FROM MOLD_TRANS_DETAIL MTD
                 LEFT JOIN MOLD_MASTER MM ON MTD.MOLD_ID = MM.MOLD_ID
                 LEFT JOIN MOLD_TRAN_LOC LOC_DARI ON MTD.DARI_TRAN = LOC_DARI.ID
                 LEFT JOIN MOLD_TRAN_LOC LOC_KE ON MTD.KE_TRAN = LOC_KE.ID
                 WHERE {$whereSql}
                 ORDER BY MTD.[DATE] DESC, MTD.ID_TRANS DESC";

    $stmtExp = sqlsrv_query($conn, $sqlExport, $queryParams);
    $fileName = "Mold_Transaction_" . date('Ymd_His') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$fileName\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    ?>
    <table border="1">
        <thead>
            <tr style="background-color: #2e4053; color: #ffffff; font-weight: bold;">
                <th>PART NO</th>
                <th>MOLD NAME</th>
                <th>CUSTOMER</th>
                <th>TANGGAL</th>
                <th>JENIS TRANSAKSI</th>
                <th>DARI</th>
                <th>PIC PENGIRIM</th>
                <th>KE</th>
                <th>PIC PENERIMA</th>
                <th>KETERANGAN</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($stmtExp !== false): ?>
                <?php while ($row = sqlsrv_fetch_array($stmtExp, SQLSRV_FETCH_ASSOC)): ?>
                    <?php
                        $isOut = ($row['JENIS_TRANSAKSI'] == 2 || $row['MOLD_OUT'] == 1);
                        $jenisText = ($row['JENIS_TRANSAKSI'] == 1 || $row['MOLD_IN'] == 1) ? 'IN' : ($isOut ? 'OUT' : '-');
                        $tglStr = ($row['DATE'] instanceof DateTime) ? $row['DATE']->format('d - M - Y') : '-';
                    ?>
                    <tr>
                        <td style="mso-number-format:'\@';"><?php echo h(trim($row['PART_NO'])); ?></td>
                        <td><?php echo h(trim($row['PART_NAME'])); ?></td>
                        <td><?php echo h(trim($row['CUST_ALIAS'])); ?></td>
                        <td align="center"><?php echo h($tglStr); ?></td>
                        <td align="center"><?php echo $jenisText; ?></td>
                        <td><?php echo h($row['DARI_DESC']); ?></td>
                        <td align="right"><?php echo h($row['DARI_TRAN']); ?></td>
                        <td><?php echo h($row['KE_DESC']); ?></td>
                        <td align="right"><?php echo h($row['KE_TRAN']); ?></td>
                        <td><?php echo h($row['REASON']); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
    exit;
}

// Hitung Total Baris Data untuk Pagination
$sqlCount = "SELECT COUNT(*) AS total_rows
             FROM MOLD_TRANS_DETAIL MTD
             LEFT JOIN MOLD_MASTER MM ON MTD.MOLD_ID = MM.MOLD_ID
             WHERE {$whereSql}";
$stmtCount = sqlsrv_query($conn, $sqlCount, $queryParams);
$totalRows = 0;
if ($stmtCount !== false) {
    $rCount = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
    $totalRows = $rCount ? intval($rCount['total_rows']) : 0;
    sqlsrv_free_stmt($stmtCount);
}

$totalPages = ($totalRows > 0) ? ceil($totalRows / $perPage) : 1;
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
if ($currentPage < 1) {
    $currentPage = 1;
}

$startRow = (($currentPage - 1) * $perPage) + 1;
$endRow   = $currentPage * $perPage;

// Query Data dengan Paging (ROW_NUMBER() kompatibel SQL Server 2008+)
$sqlPaged = "SELECT * FROM (
                SELECT 
                    ROW_NUMBER() OVER (ORDER BY MTD.[DATE] DESC, MTD.ID_TRANS DESC) AS RowNum,
                    MTD.ID_TRANS,
                    MTD.MOLD_ID,
                    MM.PART_NO,
                    MM.PART_NAME,
                    MM.CUST_ALIAS,
                    MTD.[DATE],
                    MTD.JENIS_TRANSAKSI,
                    MTD.MOLD_IN,
                    MTD.MOLD_OUT,
                    MTD.DARI_TRAN,
                    LOC_DARI.NAMA AS DARI_DESC,
                    MTD.KE_TRAN,
                    LOC_KE.NAMA AS KE_DESC,
                    MTD.REASON
                FROM MOLD_TRANS_DETAIL MTD
                LEFT JOIN MOLD_MASTER MM ON MTD.MOLD_ID = MM.MOLD_ID
                LEFT JOIN MOLD_TRAN_LOC LOC_DARI ON MTD.DARI_TRAN = LOC_DARI.ID
                LEFT JOIN MOLD_TRAN_LOC LOC_KE ON MTD.KE_TRAN = LOC_KE.ID
                WHERE {$whereSql}
             ) AS PagedResult
             WHERE RowNum BETWEEN ? AND ?
             ORDER BY RowNum ASC";

$pagedParams = array_merge($queryParams, [$startRow, $endRow]);
$stmtTrans = sqlsrv_query($conn, $sqlPaged, $pagedParams);
if ($stmtTrans === false) {
    die("<pre>Gagal mengambil data transaksi:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$reportData = [];
while ($row = sqlsrv_fetch_array($stmtTrans, SQLSRV_FETCH_ASSOC)) {
    $reportData[] = $row;
}
sqlsrv_free_stmt($stmtTrans);

// Fungsi pembantu membuat URL Pagination dengan tetap membawa filter aktif
function buildPageUrl($pageNum, $perPage, $moldNo, $cust, $start, $end) {
    $params = [
        'page'       => $pageNum,
        'per_page'   => $perPage,
        'mold_no'    => $moldNo,
        'cust_alias' => $cust,
        'start_date' => $start,
        'end_date'   => $end
    ];
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Mold Transaction - Entry & Monitoring</title>
    <style>
        body { padding: 15px; background: #d4d0c8; font-family: Tahoma, Arial, sans-serif; font-size: 11px; margin: 0; color: #000; }
        .box-container { border: 1px solid #808080; background: #eeeeee; padding: 12px; margin-bottom: 12px; }
        .box-title { margin-top: 0; border-bottom: 1px solid #808080; padding-bottom: 4px; color: #2e4053; font-size: 12px; font-weight: bold; }
        
        .form-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 10px; }
        .form-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 10px; }
        .form-grid-6 { display: grid; grid-template-columns: 1.2fr 1fr 0.9fr 0.9fr 0.6fr auto; gap: 8px; align-items: flex-end; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 3px; }
        .form-group input, .form-group select { width: 100%; padding: 4px; box-sizing: border-box; font-family: Tahoma; font-size: 11px; border: 1px solid #7f9db9; background: #ffffff; }
        .form-group input[readonly] { background: #b9d1ea; color: #000; font-weight: bold; }

        .btn-action { background: #d4d0c8; border: 2px outset #ffffff; padding: 5px 12px; font-weight: bold; cursor: pointer; font-family: Tahoma; font-size: 11px; }
        .btn-action:active { border: 2px inset #ffffff; }
        .btn-save { background: #2980b9; color: #fff; border: 2px outset #5dade2; }
        .btn-save:hover { background: #1f618d; }

        .btn-edit { background: #f39c12; color: #fff; border: 1px solid #d68910; padding: 3px 8px; font-size: 10px; font-weight: bold; cursor: pointer; border-radius: 2px; }
        .btn-del { background: #c0392b; color: #fff; border: 1px solid #962d22; padding: 3px 8px; font-size: 10px; font-weight: bold; cursor: pointer; border-radius: 2px; margin-left: 2px; }
        .btn-edit:hover { background: #d68910; }
        .btn-del:hover { background: #962d22; }

        .alert { padding: 8px 12px; margin-bottom: 12px; border-radius: 2px; font-weight: bold; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .report-table { width: 100%; border-collapse: collapse; background: #ffffff; margin-top: 6px; font-size: 11px; }
        .report-table th { background: #2e4053; color: white; padding: 6px; border: 1px solid #7f8c8d; font-size: 10px; text-align: left; }
        .report-table td { padding: 5px 6px; border: 1px solid #bdc3c7; }
        .report-table tr:nth-child(even) { background: #f8f9fa; }
        .report-table tr:hover { background: #eaf2f8; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        /* Styling Navigasi Pagination */
        .pagination-bar { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; padding-top: 8px; border-top: 1px solid #dcdcdc; }
        .pagination-info { font-weight: bold; color: #2e4053; font-size: 11px; }
        .pagination-links { display: flex; gap: 4px; align-items: center; }
        .page-link { display: inline-block; padding: 4px 9px; background: #eeeeee; border: 1px solid #808080; color: #000; text-decoration: none; font-weight: bold; border-radius: 2px; font-size: 11px; }
        .page-link:hover { background: #d4d0c8; }
        .page-link.active { background: #2980b9; color: #ffffff; border-color: #1f618d; }
        .page-link.disabled { color: #999999; background: #f5f5f5; border-color: #cccccc; pointer-events: none; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 9999; justify-content: center; align-items: center; }
        .modal-content { background: #eeeeee; border: 2px solid #2c3e50; border-radius: 4px; width: 680px; max-width: 95%; padding: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.4); font-family: Tahoma, Arial, sans-serif; font-size: 11px; }
        .modal-header { font-size: 13px; font-weight: bold; color: #2c3e50; border-bottom: 1px solid #808080; padding-bottom: 6px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { cursor: pointer; font-size: 18px; font-weight: bold; color: #c0392b; line-height: 1; }

        @media print { .box-container, .alert, .col-action, .pagination-bar, .modal-overlay { display: none !important; } body { background: #fff; padding: 0; } .table-wrapper { display: block !important; border: none !important; padding: 0 !important; } }
    </style>
</head>
<body>

<?php if (!empty($alertMsg)): ?>
    <div class="alert alert-<?php echo $alertType; ?>"><?php echo h($alertMsg); ?></div>
<?php endif; ?>

<!-- ======================================================== -->
<!-- 1. FORM INPUT TRANSAKSI BARU (IDENTIK DENGAN DELPHI)     -->
<!-- ======================================================== -->
<div class="box-container">
    <div class="box-title">INPUT TRANSAKSI MOLD BARU (MOLD TRANSACTION)</div>
    <form method="POST" action="">
        <div class="form-grid-3">
            <div class="form-group">
                <label>PART NO <span style="color:red;">*</span></label>
                <input type="text" id="input_part_no" list="list_master_part" placeholder="Ketik atau pilih Part No..." required autocomplete="off">
                <datalist id="list_master_part">
                    <?php foreach ($arrMasterMold as $m): ?>
                        <option value="<?php echo h($m['no']); ?>"><?php echo h($m['name']); ?> (<?php echo h($m['cust']); ?>)</option>
                    <?php endforeach; ?>
                </datalist>
                <input type="hidden" name="in_mold_id" id="in_mold_id" required>
            </div>
            <div class="form-group">
                <label>MOLD NAME</label>
                <input type="text" id="in_mold_name" readonly placeholder="(Otomatis terisi sesuai Part No)">
            </div>
            <div class="form-group">
                <label>CUSTOMER</label>
                <input type="text" id="in_cust_alias" readonly placeholder="(Otomatis terisi)" style="background:#e0e0e0; font-weight:normal;">
            </div>
        </div>

        <div class="form-grid-4">
            <div class="form-group">
                <label>TANGGAL <span style="color:red;">*</span></label>
                <input type="date" name="in_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label>JENIS TRANSAKSI <span style="color:red;">*</span></label>
                <select name="in_jenis_trans" required>
                    <option value="1">IN</option>
                    <option value="2">OUT</option>
                </select>
            </div>
            <div class="form-group">
                <label>DARI (LOKASI PENGIRIM) <span style="color:red;">*</span></label>
                <select name="in_dari_tran" required>
                    <option value="">-- PILIH DARI --</option>
                    <?php foreach ($arrLoc as $l): ?>
                        <option value="<?php echo h($l['id']); ?>">
                            <?php echo h($l['nama']); ?><?php echo ($l['tipe'] !== '') ? ' [' . h($l['tipe']) . ']' : ''; ?> (ID: <?php echo h($l['id']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>KE (LOKASI PENERIMA)</label>
                <select name="in_ke_tran">
                    <option value="">-- PILIH KE --</option>
                    <?php foreach ($arrLoc as $l): ?>
                        <option value="<?php echo h($l['id']); ?>">
                            <?php echo h($l['nama']); ?><?php echo ($l['tipe'] !== '') ? ' [' . h($l['tipe']) . ']' : ''; ?> (ID: <?php echo h($l['id']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display: flex; gap: 10px; align-items: flex-end;">
            <div class="form-group" style="flex: 1;">
                <label>KETERANGAN / REMARK (Maks 30 Karakter)</label>
                <input type="text" name="in_reason" maxlength="30" placeholder="Contoh: NEW MOLD / A/ Repair / Dikembalikan...">
            </div>
            <div style="margin-bottom: 1px;">
                <button type="submit" name="btnSimpanTrans" class="btn-action btn-save">SIMPAN TRANSAKSI BARU</button>
            </div>
        </div>
    </form>
</div>

<!-- ======================================================== -->
<!-- 2. FILTER PENCARIAN & JUMLAH BARIS PER HALAMAN           -->
<!-- ======================================================== -->
<div class="box-container">
    <div class="box-title">FILTER & PENCARIAN RIWAYAT TRANSAKSI MOLD</div>
    <form method="GET" action="">
        <input type="hidden" name="page" value="1">
        <div class="form-grid-6">
            <div class="form-group">
                <label>Pilih / Cari PART NO</label>
                <input type="text" name="mold_no" list="filter_part_list" value="<?php echo h($filterMoldNo); ?>" placeholder="Ketik Part No (Contoh: 1904035)...">
                <datalist id="filter_part_list">
                    <?php foreach ($arrMasterMold as $m): ?>
                        <option value="<?php echo h($m['no']); ?>"><?php echo h($m['name']); ?></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            
            <div class="form-group">
                <label>Customer</label>
                <input type="text" name="cust_alias" list="filter_cust_list" value="<?php echo h($filterCust); ?>" placeholder="Semua Customer...">
                <datalist id="filter_cust_list">
                    <?php foreach ($arrCust as $c): ?>
                        <option value="<?php echo h($c); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="form-group">
                <label>Dari Tanggal</label>
                <input type="date" name="start_date" value="<?php echo h($startDateForm); ?>">
            </div>

            <div class="form-group">
                <label>Sampai Tanggal</label>
                <input type="date" name="end_date" value="<?php echo h($endDateForm); ?>">
            </div>

            <div class="form-group">
                <label>Tampil / Hal</label>
                <select name="per_page" onchange="this.form.submit()">
                    <?php foreach ($allowedLimits as $lim): ?>
                        <option value="<?php echo $lim; ?>" <?php echo ($perPage === $lim) ? 'selected' : ''; ?>>
                            <?php echo $lim; ?> Baris
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 4px; margin-bottom: 1px;">
                <button type="submit" class="btn-action">CARI</button>
                <a href="mold_trans.php" class="btn-action" style="text-decoration:none; color:#000; display:inline-block; line-height:14px;">RESET</a>
                <button type="submit" formmethod="POST" name="btnExport" class="btn-action" style="background: #1d6f42; color: white; border-color: #1d6f42;">EXCEL</button>
                <button type="button" class="btn-action" onclick="window.print();" style="background: #27ae60; color: white; border-color: #27ae60;">PRINT</button>
            </div>
        </div>
    </form>
</div>

<!-- ======================================================== -->
<!-- 3. TABEL DATA TRANSAKSI DENGAN PAGINATION                -->
<!-- ======================================================== -->
<div class="box-container table-wrapper" style="background: #ffffff;">
    <?php
        $displayFrom = ($totalRows > 0) ? $startRow : 0;
        $displayTo   = ($endRow > $totalRows) ? $totalRows : $endRow;
    ?>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
        <span style="font-weight:bold; color:#2e4053;">
            DAFTAR TRANSAKSI MOLD 
            <?php if ($filterMoldNo !== ''): ?>
                (Part No: <span style="color:#c0392b;"><?php echo h($filterMoldNo); ?></span>)
            <?php endif; ?>
            — Menampilkan <?php echo number_format($displayFrom); ?> s/d <?php echo number_format($displayTo); ?> dari Total <?php echo number_format($totalRows); ?> Data
        </span>
        <span style="font-weight:bold; color:#555;">
            Halaman <?php echo $currentPage; ?> dari <?php echo $totalPages; ?>
        </span>
    </div>

    <?php if (!empty($reportData)): ?>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 4%;" class="text-center">NO</th>
                    <th style="width: 11%;">PART NO</th>
                    <th style="width: 15%;">MOLD NAME</th>
                    <th style="width: 9%;">CUSTOMER</th>
                    <th style="width: 9%;" class="text-center">TANGGAL</th>
                    <th style="width: 7%;" class="text-center">JENIS TRANSAKSI</th>
                    <th style="width: 13%;">DARI</th>
                    <th style="width: 5%;" class="text-right">PIC PENGIRIM</th>
                    <th style="width: 13%;">KE</th>
                    <th style="width: 5%;" class="text-right">PIC PENERIMA</th>
                    <th style="width: 10%;">KETERANGAN</th>
                    <th class="col-action text-center" style="width: 8%;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportData as $row): ?>
                    <?php
                        $rowNum   = intval($row['RowNum']);
                        $idTrans  = intval($row['ID_TRANS']);
                        $moldId   = intval($row['MOLD_ID']);
                        $partNo   = isset($row['PART_NO']) ? trim($row['PART_NO']) : '-';
                        $partName = isset($row['PART_NAME']) ? trim($row['PART_NAME']) : '-';
                        $custName = isset($row['CUST_ALIAS']) ? trim($row['CUST_ALIAS']) : '-';
                        $rDate    = $row['DATE'];
                        
                        $jenisId  = intval($row['JENIS_TRANSAKSI']);
                        $isOut    = ($jenisId === 2 || (!empty($row['MOLD_OUT']) && $row['MOLD_OUT'] == 1));
                        $isIn     = ($jenisId === 1 || (!empty($row['MOLD_IN']) && $row['MOLD_IN'] == 1));
                        $jenisTxt = $isIn ? 'IN' : ($isOut ? 'OUT' : '');

                        $dariDesc = !empty($row['DARI_DESC']) ? trim($row['DARI_DESC']) : '';
                        $dariId   = !empty($row['DARI_TRAN']) ? intval($row['DARI_TRAN']) : '';
                        $keDesc   = !empty($row['KE_DESC']) ? trim($row['KE_DESC']) : '';
                        $keId     = !empty($row['KE_TRAN']) ? intval($row['KE_TRAN']) : '';
                        $reason   = isset($row['REASON']) ? trim($row['REASON']) : '';

                        $strDateDisplay = ($rDate instanceof DateTime) ? $rDate->format('d - M - Y') : '-';
                        $strDateInput   = ($rDate instanceof DateTime) ? $rDate->format('Y-m-d') : '';
                    ?>
                    <tr>
                        <td class="text-center" style="color:#666;"><?php echo $rowNum; ?></td>
                        <td><strong><?php echo h($partNo); ?></strong></td>
                        <td><?php echo h($partName); ?></td>
                        <td><?php echo h($custName); ?></td>
                        <td class="text-center"><?php echo h($strDateDisplay); ?></td>
                        <td class="text-center" style="font-weight:bold; color:<?php echo ($jenisTxt === 'IN') ? '#27ae60' : '#c0392b'; ?>;">
                            <?php echo h($jenisTxt); ?>
                        </td>
                        <td><?php echo h($dariDesc); ?></td>
                        <td class="text-right"><?php echo h($dariId); ?></td>
                        <td><?php echo h($keDesc); ?></td>
                        <td class="text-right"><?php echo h($keId); ?></td>
                        <td><?php echo h($reason); ?></td>

                        <td class="col-action text-center" style="white-space: nowrap;">
                            <button type="button" class="btn-edit" 
                                data-id-trans="<?php echo h($idTrans); ?>"
                                data-part-no="<?php echo h($partNo); ?>"
                                data-part-name="<?php echo h($partName); ?>"
                                data-date="<?php echo h($strDateInput); ?>"
                                data-jenis="<?php echo ($jenisTxt === 'OUT') ? '2' : '1'; ?>"
                                data-dari="<?php echo h($dariId); ?>"
                                data-ke="<?php echo h($keId); ?>"
                                data-reason="<?php echo h($reason); ?>"
                                onclick="handleEditTransClick(this)">
                                EDIT
                            </button>

                            <form method="POST" action="<?php echo h(buildPageUrl($currentPage, $perPage, $filterMoldNo, $filterCust, $startDateForm, $endDateForm)); ?>" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi tanggal <?php echo h($strDateDisplay); ?> ini?');">
                                <input type="hidden" name="del_id_trans" value="<?php echo h($idTrans); ?>">
                                <button type="submit" name="btnDeleteTrans" class="btn-del">HAPUS</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- BAR NAVIGASI PAGINATION BAWAH -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination-bar">
                <div class="pagination-info">
                    Menampilkan <?php echo $displayFrom; ?> - <?php echo $displayTo; ?> dari <?php echo number_format($totalRows); ?> transaksi
                </div>
                <div class="pagination-links">
                    <!-- Tombol Awal & Sebelumnya -->
                    <a class="page-link <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>" 
                       href="<?php echo h(buildPageUrl(1, $perPage, $filterMoldNo, $filterCust, $startDateForm, $endDateForm)); ?>" title="Halaman Pertama">&laquo; Awal</a>
                    <a class="page-link <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>" 
                       href="<?php echo h(buildPageUrl($currentPage - 1, $perPage, $filterMoldNo, $filterCust, $startDateForm, $endDateForm)); ?>" title="Sebelumnya">&lsaquo; Prev</a>

                    <?php
                        $range = 2; // Jumlah nomor halaman di kiri & kanan halaman aktif
                        $startPage = max(1, $currentPage - $range);
                        $endPage   = min($totalPages, $currentPage + $range);

                        if ($startPage > 1) {
                            echo '<a class="page-link" href="' . h(buildPageUrl(1, $perPage, $filterMoldNo, $filterCust, $startDateForm, $endDateForm)) . '">1</a>';
                            if ($startPage > 2) {
                                echo '<span style="padding:0 4px; color:#666;">...</span>';
                            }
                        }

                        for ($p = $startPage; $p <= $endPage; $p++):
                    ?>
                        <a class="page-link <?php echo ($p === $currentPage) ? 'active' : ''; ?>" 
                           href="<?php echo h(buildPageUrl($p, $perPage, $filterMoldNo, $filterCust, $startDateForm, $endDateForm)); ?>">
                            <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>

                    <?php
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<span style="padding:0 4px; color:#666;">...</span>';
                            }
                            echo '<a class="page-link" href="' . h(buildPageUrl($totalPages, $perPage, $filterMoldNo, $filterCust, $startDateForm, $endDateForm)) . '">' . $totalPages . '</a>';
                        }
                    ?>

                    <!-- Tombol Berikutnya & Akhir -->
                    <a class="page-link <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>" 
                       href="<?php echo h(buildPageUrl($currentPage + 1, $perPage, $filterMoldNo, $filterCust, $startDateForm, $endDateForm)); ?>" title="Berikutnya">Next &rsaquo;</a>
                    <a class="page-link <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>" 
                       href="<?php echo h(buildPageUrl($totalPages, $perPage, $filterMoldNo, $filterCust, $startDateForm, $endDateForm)); ?>" title="Halaman Terakhir">Akhir &raquo;</a>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div style="text-align:center; padding:20px; background:#f9f9f9; border:1px solid #ccc; font-weight:bold; color:#555;">
            Tidak ada data transaksi yang cocok dengan pencarian Anda.
        </div>
    <?php endif; ?>
</div>

<!-- ======================================================== -->
<!-- 4. MODAL POPUP EDIT TRANSAKSI MOLD                       -->
<!-- ======================================================== -->
<div id="modalEditTrans" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <span>EDIT DATA TRANSAKSI MOLD</span>
            <span class="modal-close" onclick="closeEditTransModal()">&times;</span>
        </div>
        <form method="POST" action="<?php echo h(buildPageUrl($currentPage, $perPage, $filterMoldNo, $filterCust, $startDateForm, $endDateForm)); ?>">
            <input type="hidden" name="edit_id_trans" id="edit_id_trans">

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
                    <label>JENIS TRANSAKSI</label>
                    <select name="edit_jenis_trans" id="edit_jenis_trans" required>
                        <option value="1">IN</option>
                        <option value="2">OUT</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>DARI (PENGIRIM)</label>
                    <select name="edit_dari_tran" id="edit_dari_tran" required>
                        <option value="">-- PILIH DARI --</option>
                        <?php foreach ($arrLoc as $l): ?>
                            <option value="<?php echo h($l['id']); ?>">
                                <?php echo h($l['nama']); ?> (ID: <?php echo h($l['id']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>KE (PENERIMA)</label>
                    <select name="edit_ke_tran" id="edit_ke_tran">
                        <option value="">-- PILIH KE --</option>
                        <?php foreach ($arrLoc as $l): ?>
                            <option value="<?php echo h($l['id']); ?>">
                                <?php echo h($l['nama']); ?> (ID: <?php echo h($l['id']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>KETERANGAN / REMARK</label>
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

    function handleEditTransClick(btn) {
        document.getElementById('edit_id_trans').value = btn.dataset.idTrans;
        document.getElementById('edit_part_no').value = btn.dataset.partNo;
        document.getElementById('edit_part_name').value = btn.dataset.partName;
        document.getElementById('edit_date').value = btn.dataset.date;
        document.getElementById('edit_jenis_trans').value = btn.dataset.jenis;
        document.getElementById('edit_dari_tran').value = btn.dataset.dari || '';
        document.getElementById('edit_ke_tran').value = btn.dataset.ke || '';
        document.getElementById('edit_reason').value = btn.dataset.reason;

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
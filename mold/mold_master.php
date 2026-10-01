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
// 1. QUERY MASTER CUSTOMER UNTUK DROPDOWN
// -------------------------------------------------------------
$sqlCust = "SELECT DISTINCT CUST_ALIAS FROM ITEM_CUST_MOLD WHERE CUST_ALIAS IS NOT NULL AND CUST_ALIAS <> '' ORDER BY CUST_ALIAS ASC";
$resCust = sqlsrv_query($conn, $sqlCust);
$arrCust = [];
if ($resCust !== false) {
    while ($c = sqlsrv_fetch_array($resCust, SQLSRV_FETCH_ASSOC)) {
        $arrCust[] = trim($c['CUST_ALIAS']);
    }
}

// -------------------------------------------------------------
// 2. SIMPAN MASTER MOLD BARU
// -------------------------------------------------------------
if (isset($_POST['btnSimpanMaster'])) {
    $partNo    = substr(trim($_POST['in_part_no']), 0, 50);
    $partName  = substr(trim($_POST['in_part_name']), 0, 100);
    $custAlias = substr(trim($_POST['in_cust_alias']), 0, 30);
    $partCode  = !empty($_POST['in_part_code']) ? substr(trim($_POST['in_part_code']), 0, 8) : null;
    $tooling   = !empty($_POST['in_tooling']) ? intval($_POST['in_tooling']) : null;

    if (empty($partNo) || empty($partName)) {
        $alertMsg = "Gagal Simpan: Part No (Mold Number) dan Mold Name wajib diisi!";
        $alertType = "danger";
    } else {
        // Cek duplikasi Part No di MOLD_MASTER
        $qCheck = sqlsrv_query($conn, "SELECT COUNT(*) AS cnt FROM MOLD_MASTER WHERE PART_NO = ?", [$partNo]);
        $rCheck = sqlsrv_fetch_array($qCheck, SQLSRV_FETCH_ASSOC);

        if ($rCheck && $rCheck['cnt'] > 0) {
            $alertMsg = "Gagal Simpan: Mold Number (Part No) '{$partNo}' sudah terdaftar!";
            $alertType = "danger";
        } else {
            // Ambil PART_ID dari ITEM_CUST_MOLD jika sudah ada
            $qPartId = sqlsrv_query($conn, "SELECT TOP 1 PART_ID, CUST_ID FROM ITEM_CUST_MOLD WHERE PART_NO = ?", [$partNo]);
            $rPartId = sqlsrv_fetch_array($qPartId, SQLSRV_FETCH_ASSOC);
            $partId = $rPartId ? $rPartId['PART_ID'] : null;
            $custId = $rPartId ? $rPartId['CUST_ID'] : null;

            // Jika belum ada di ITEM_CUST_MOLD, buatkan record pendamping
            if (!$partId) {
                $sqlInsertItem = "INSERT INTO ITEM_CUST_MOLD (PART_CODE, PART_NO, PART_NAME, CUST_ALIAS) VALUES (?, ?, ?, ?); SELECT SCOPE_IDENTITY() AS NEW_ID;";
                $stmtItem = sqlsrv_query($conn, $sqlInsertItem, [$partCode, $partNo, $partName, $custAlias]);
                if ($stmtItem) {
                    sqlsrv_next_result($stmtItem);
                    $rowNew = sqlsrv_fetch_array($stmtItem, SQLSRV_FETCH_ASSOC);
                    $partId = $rowNew ? $rowNew['NEW_ID'] : null;
                }
            }

            $sqlInsertMaster = "INSERT INTO MOLD_MASTER (PART_ID, PART_CODE, PART_NO, PART_NAME, CUST_ALIAS, CUST_ID, TOOLING) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
            $paramsMaster = [$partId, $partCode, $partNo, $partName, $custAlias, $custId, $tooling];
            $stmtMaster = sqlsrv_query($conn, $sqlInsertMaster, $paramsMaster);

            if ($stmtMaster === false) {
                $err = sqlsrv_errors();
                $alertMsg = "Gagal menyimpan ke MOLD_MASTER: " . (isset($err[0]['message']) ? $err[0]['message'] : '');
                $alertType = "danger";
            } else {
                $alertMsg = "Master Mold baru berhasil ditambahkan!";
                $alertType = "success";
            }
        }
    }
}

// -------------------------------------------------------------
// 3. UPDATE (EDIT) MASTER MOLD
// -------------------------------------------------------------
if (isset($_POST['btnUpdateMaster'])) {
    $editMoldId   = intval($_POST['edit_mold_id']);
    $editPartNo   = substr(trim($_POST['edit_part_no']), 0, 50);
    $editPartName = substr(trim($_POST['edit_part_name']), 0, 100);
    $editCust     = substr(trim($_POST['edit_cust_alias']), 0, 30);
    $editCode     = !empty($_POST['edit_part_code']) ? substr(trim($_POST['edit_part_code']), 0, 8) : null;
    $editTooling  = !empty($_POST['edit_tooling']) ? intval($_POST['edit_tooling']) : null;

    $sqlUpdate = "UPDATE MOLD_MASTER 
                  SET PART_NO = ?, PART_NAME = ?, CUST_ALIAS = ?, PART_CODE = ?, TOOLING = ? 
                  WHERE MOLD_ID = ?";
    $paramsUpd = [$editPartNo, $editPartName, $editCust, $editCode, $editTooling, $editMoldId];
    $stmtUpd = sqlsrv_query($conn, $sqlUpdate, $paramsUpd);

    if ($stmtUpd === false) {
        $err = sqlsrv_errors();
        $alertMsg = "Gagal memperbarui master mold: " . (isset($err[0]['message']) ? $err[0]['message'] : '');
        $alertType = "danger";
    } else {
        // Sinkronisasi data nama ke ITEM_CUST_MOLD
        sqlsrv_query($conn, "UPDATE ITEM_CUST_MOLD SET PART_NAME = ?, CUST_ALIAS = ? WHERE PART_NO = ?", [$editPartName, $editCust, $editPartNo]);
        $alertMsg = "Master Mold berhasil diperbarui!";
        $alertType = "success";
    }
}

// -------------------------------------------------------------
// 4. HAPUS MASTER MOLD
// -------------------------------------------------------------
if (isset($_POST['btnDeleteMaster'])) {
    $delMoldId = intval($_POST['del_mold_id']);

    // Cek apakah mold sudah dipakai di riwayat transaksi atau history
    $qCheckTrans = sqlsrv_query($conn, "SELECT COUNT(*) AS cnt FROM MOLD_TRANS_DETAIL WHERE MOLD_ID = ?", [$delMoldId]);
    $rCheckTrans = sqlsrv_fetch_array($qCheckTrans, SQLSRV_FETCH_ASSOC);

    $qCheckHist = sqlsrv_query($conn, "SELECT COUNT(*) AS cnt FROM MOLD_HISTORY_DETAIL WHERE MOLD_ID = ?", [$delMoldId]);
    $rCheckHist = sqlsrv_fetch_array($qCheckHist, SQLSRV_FETCH_ASSOC);

    if (($rCheckTrans && $rCheckTrans['cnt'] > 0) || ($rCheckHist && $rCheckHist['cnt'] > 0)) {
        $alertMsg = "Gagal Hapus: Mold ini sudah memiliki catatan riwayat mutasi / perbaikan!";
        $alertType = "danger";
    } else {
        $stmtDel = sqlsrv_query($conn, "DELETE FROM MOLD_MASTER WHERE MOLD_ID = ?", [$delMoldId]);
        if ($stmtDel === false) {
            $err = sqlsrv_errors();
            $alertMsg = "Gagal menghapus mold: " . (isset($err[0]['message']) ? $err[0]['message'] : '');
            $alertType = "danger";
        } else {
            $alertMsg = "Master Mold berhasil dihapus!";
            $alertType = "success";
        }
    }
}

// -------------------------------------------------------------
// 5. QUERY LIST MASTER MOLD & EXPORT EXCEL
// -------------------------------------------------------------
$searchKeyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterCust    = isset($_GET['filter_cust']) ? trim($_GET['filter_cust']) : '';

$whereClauses = ["1=1"];
$paramsQuery = [];

if ($searchKeyword !== '') {
    $whereClauses[] = "(PART_NO LIKE ? OR PART_NAME LIKE ? OR PART_CODE LIKE ?)";
    $kw = "%" . $searchKeyword . "%";
    $paramsQuery[] = $kw;
    $paramsQuery[] = $kw;
    $paramsQuery[] = $kw;
}

if ($filterCust !== '') {
    $whereClauses[] = "CUST_ALIAS = ?";
    $paramsQuery[] = $filterCust;
}

$whereSql = implode(" AND ", $whereClauses);
$sqlLoadMaster = "SELECT MOLD_ID, PART_ID, PART_CODE, PART_NO, PART_NAME, CUST_ALIAS, TOOLING 
                  FROM MOLD_MASTER 
                  WHERE {$whereSql} 
                  ORDER BY PART_NO ASC";
$resLoadMaster = sqlsrv_query($conn, $sqlLoadMaster, $paramsQuery);

$masterList = [];
if ($resLoadMaster !== false) {
    while ($row = sqlsrv_fetch_array($resLoadMaster, SQLSRV_FETCH_ASSOC)) {
        $masterList[] = $row;
    }
}

// Export Excel Langsung
if (isset($_POST['btnExportExcel'])) {
    $fileName = "Master_Mold_" . date('Ymd_His') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$fileName\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    ?>
    <table border="1">
        <thead>
            <tr style="background-color: #2c3e50; color: #ffffff; font-weight: bold;">
                <th>MOLD NUMBER (PART NO)</th>
                <th>MOLD NAME</th>
                <th>CUSTOMER</th>
                <th>PART CODE</th>
                <th>TOOLING</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($masterList as $m): ?>
                <tr>
                    <td style="mso-number-format:'\@';"><?php echo h($m['PART_NO']); ?></td>
                    <td><?php echo h($m['PART_NAME']); ?></td>
                    <td><?php echo h($m['CUST_ALIAS']); ?></td>
                    <td style="mso-number-format:'\@';"><?php echo h($m['PART_CODE']); ?></td>
                    <td align="center"><?php echo h($m['TOOLING']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Mold Master Control</title>
    <style>
        body { padding: 15px; background: #d4d0c8; font-family: Tahoma, Arial, sans-serif; font-size: 11px; margin: 0; color: #000; }
        .box-container { border: 1px solid #808080; background: #eeeeee; padding: 12px; margin-bottom: 12px; }
        .box-title { margin-top: 0; border-bottom: 1px solid #808080; padding-bottom: 4px; color: #2e4053; font-size: 12px; font-weight: bold; }
        
        .form-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 8px; }
        .form-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 8px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 3px; }
        .form-group input, .form-group select { width: 100%; padding: 4px; box-sizing: border-box; font-family: Tahoma; font-size: 11px; border: 1px solid #7f9db9; background: #ffffff; }

        .btn-action { background: #d4d0c8; border: 2px outset #ffffff; padding: 5px 14px; font-weight: bold; cursor: pointer; font-family: Tahoma; font-size: 11px; }
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

        .report-table { width: 100%; border-collapse: collapse; background: #ffffff; font-size: 10px; margin-top: 8px; }
        .report-table th { background: #2e4053; color: white; padding: 6px; border: 1px solid #7f8c8d; text-align: left; }
        .report-table td { padding: 5px; border: 1px solid #bdc3c7; }
        .report-table tr:nth-child(even) { background: #f8f9fa; }
        .text-center { text-align: center; }

        /* Modal Dialog Edit */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 9999; justify-content: center; align-items: center; }
        .modal-content { background: #eeeeee; border: 2px solid #2c3e50; border-radius: 4px; width: 550px; max-width: 95%; padding: 15px; font-family: Tahoma, Arial, sans-serif; font-size: 11px; }
        .modal-header { font-size: 12px; font-weight: bold; color: #2c3e50; border-bottom: 1px solid #808080; padding-bottom: 5px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { cursor: pointer; font-size: 16px; font-weight: bold; color: #c0392b; }

        @media print { .box-container, .alert, .col-action, .modal-overlay { display: none !important; } body { background: #fff; padding: 0; } }
    </style>
</head>
<body>

<?php if (!empty($alertMsg)): ?>
    <div class="alert alert-<?php echo $alertType; ?>"><?php echo h($alertMsg); ?></div>
<?php endif; ?>

<!-- ======================================================== -->
<!-- 1. FORM INPUT MASTER MOLD BARU                           -->
<!-- ======================================================== -->
<div class="box-container">
    <div class="box-title">TAMBAH MASTER DATA MOLD BARU (MOLD_MASTER)</div>
    <form method="POST" action="">
        <div class="form-grid-4">
            <div class="form-group">
                <label>MOLD NUMBER (PART NO) <span style="color:red;">*</span></label>
                <input type="text" name="in_part_no" placeholder="Contoh: 05030666990A101..." required autocomplete="off">
            </div>
            <div class="form-group" style="grid-column: span 2;">
                <label>MOLD NAME (PART NAME) <span style="color:red;">*</span></label>
                <input type="text" name="in_part_name" placeholder="Nama part cetakan mold..." required autocomplete="off">
            </div>
            <div class="form-group">
                <label>CUSTOMER ALIAS</label>
                <input type="text" name="in_cust_alias" list="list_cust_master" placeholder="Pilih atau ketik alias..." autocomplete="off">
                <datalist id="list_cust_master">
                    <?php foreach ($arrCust as $c): ?>
                        <option value="<?php echo h($c); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
        </div>

        <div style="display: flex; gap: 10px; align-items: flex-end;">
            <div class="form-group" style="width: 25%;">
                <label>PART CODE (KODE PART)</label>
                <input type="text" name="in_part_code" maxlength="8" placeholder="Maks 8 char...">
            </div>
            <div class="form-group" style="width: 25%;">
                <label>TOOLING NUMBER</label>
                <input type="number" name="in_tooling" placeholder="No. Tooling...">
            </div>
            <div style="margin-bottom: 1px; flex: 1; text-align: right;">
                <button type="submit" name="btnSimpanMaster" class="btn-action btn-save">SIMPAN MASTER MOLD</button>
            </div>
        </div>
    </form>
</div>

<!-- ======================================================== -->
<!-- 2. PENCARIAN & FILTER MASTER MOLD                        -->
<!-- ======================================================== -->
<div class="box-container">
    <div class="box-title">PENCARIAN & FILTER DATA MOLD</div>
    <form method="GET" action="" style="display: flex; gap: 10px; align-items: flex-end;">
        <div class="form-group" style="flex: 2;">
            <label>CARI MOLD NUMBER / NAMA PART</label>
            <input type="text" name="search" value="<?php echo h($searchKeyword); ?>" placeholder="Ketik kata kunci pencarian...">
        </div>
        <div class="form-group" style="flex: 1;">
            <label>FILTER CUSTOMER</label>
            <select name="filter_cust">
                <option value="">-- SEMUA CUSTOMER --</option>
                <?php foreach ($arrCust as $c): ?>
                    <option value="<?php echo h($c); ?>" <?php echo ($filterCust === $c) ? 'selected' : ''; ?>>
                        <?php echo h($c); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-action">CARI DATA</button>
        <a href="mold_master.php" class="btn-action" style="text-decoration: none; padding: 5px 12px; line-height: 14px;">RESET</a>
    </form>
</div>

<!-- ======================================================== -->
<!-- 3. TABEL DATA MOLD MASTER                                -->
<!-- ======================================================== -->
<div class="box-container" style="background: #ffffff;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
        <span style="font-weight: bold; font-size: 12px; color: #2c3e50;">
            DAFTAR MASTER MOLD (Total: <?php echo count($masterList); ?> Mold)
        </span>
        <div>
            <form method="POST" action="" style="display: inline;">
                <button type="submit" name="btnExportExcel" class="btn-action" style="background:#1d6f42; color:#fff; border-color:#1d6f42;">EXPORT EXCEL</button>
            </form>
            <button type="button" class="btn-action" onclick="window.print();" style="background:#27ae60; color:#fff; border-color:#27ae60;">PRINT</button>
        </div>
    </div>

    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 25%;">MOLD NUMBER (PART NO)</th>
                <th style="width: 35%;">MOLD NAME (PART NAME)</th>
                <th style="width: 15%;">CUSTOMER</th>
                <th style="width: 10%;">PART CODE</th>
                <th style="width: 7%;" class="text-center">TOOLING</th>
                <th class="col-action text-center" style="width: 8%;">AKSI</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($masterList)): ?>
                <?php foreach ($masterList as $m): ?>
                    <tr>
                        <td><strong><?php echo h($m['PART_NO']); ?></strong></td>
                        <td><?php echo h($m['PART_NAME']); ?></td>
                        <td><?php echo !empty($m['CUST_ALIAS']) ? h($m['CUST_ALIAS']) : '-'; ?></td>
                        <td><?php echo !empty($m['PART_CODE']) ? h($m['PART_CODE']) : '-'; ?></td>
                        <td class="text-center"><?php echo isset($m['TOOLING']) ? h($m['TOOLING']) : '-'; ?></td>
                        
                        <!-- AKSI EDIT & HAPUS -->
                        <td class="col-action text-center" style="white-space: nowrap;">
                            <button type="button" class="btn-edit" 
                                onclick='openEditModal(<?php echo json_encode([
                                    "mold_id"    => $m["MOLD_ID"],
                                    "part_no"    => $m["PART_NO"],
                                    "part_name"  => $m["PART_NAME"],
                                    "cust_alias" => $m["CUST_ALIAS"],
                                    "part_code"  => $m["PART_CODE"],
                                    "tooling"    => $m["TOOLING"]
                                ]); ?>)'>
                                EDIT
                            </button>

                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Master Mold ini?');">
                                <input type="hidden" name="del_mold_id" value="<?php echo h($m['MOLD_ID']); ?>">
                                <button type="submit" name="btnDeleteMaster" class="btn-del">HAPUS</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center" style="padding: 20px; color: #888; font-weight: bold;">
                        Data Master Mold tidak ditemukan.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ======================================================== -->
<!-- 4. MODAL POPUP EDIT MASTER MOLD                          -->
<!-- ======================================================== -->
<div id="modalEditMaster" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <span>EDIT DATA MASTER MOLD</span>
            <span class="modal-close" onclick="closeEditModal()">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="edit_mold_id" id="edit_mold_id">

            <div class="form-group" style="margin-bottom: 8px;">
                <label>MOLD NUMBER (PART NO)</label>
                <input type="text" name="edit_part_no" id="edit_part_no" required maxlength="50">
            </div>

            <div class="form-group" style="margin-bottom: 8px;">
                <label>MOLD NAME (PART NAME)</label>
                <input type="text" name="edit_part_name" id="edit_part_name" required maxlength="100">
            </div>

            <div class="form-group" style="margin-bottom: 8px;">
                <label>CUSTOMER ALIAS</label>
                <select name="edit_cust_alias" id="edit_cust_alias">
                    <option value="">-- PILIH CUSTOMER --</option>
                    <?php foreach ($arrCust as $c): ?>
                        <option value="<?php echo h($c); ?>"><?php echo h($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                <div class="form-group">
                    <label>PART CODE</label>
                    <input type="text" name="edit_part_code" id="edit_part_code" maxlength="8">
                </div>
                <div class="form-group">
                    <label>TOOLING NUMBER</label>
                    <input type="number" name="edit_tooling" id="edit_tooling">
                </div>
            </div>

            <div style="text-align: right; margin-top: 15px;">
                <button type="button" class="btn-action" onclick="closeEditModal()" style="margin-right: 5px;">BATAL</button>
                <button type="submit" name="btnUpdateMaster" class="btn-action btn-save">SIMPAN PERUBAHAN</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(data) {
        document.getElementById('edit_mold_id').value = data.mold_id;
        document.getElementById('edit_part_no').value = data.part_no;
        document.getElementById('edit_part_name').value = data.part_name;
        document.getElementById('edit_cust_alias').value = data.cust_alias || '';
        document.getElementById('edit_part_code').value = data.part_code || '';
        document.getElementById('edit_tooling').value = data.tooling || '';

        document.getElementById('modalEditMaster').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('modalEditMaster').style.display = 'none';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('modalEditMaster');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
</script>

</body>
</html>
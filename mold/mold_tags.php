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
// 1. QUERY MASTER RACK & MASTER LOKASI (LOOKUP)
// -------------------------------------------------------------
$sqlRack = "SELECT RACK_ID, RACK FROM MOLD_RACK ORDER BY RACK ASC";
$resRack = sqlsrv_query($conn, $sqlRack);
$arrRack = [];
if ($resRack !== false) {
    while ($r = sqlsrv_fetch_array($resRack, SQLSRV_FETCH_ASSOC)) {
        $arrRack[] = $r;
    }
}

$sqlLoc = "SELECT MTL.ID, MTL.NAMA, MTLT.[TYPE] AS TIPE_DESC 
           FROM MOLD_TRAN_LOC MTL 
           LEFT JOIN MOLD_TRAN_LOC_TYPE MTLT ON MTL.TIPE_ID = MTLT.ID 
           ORDER BY MTL.NAMA ASC";
$resLoc = sqlsrv_query($conn, $sqlLoc);
$arrLoc = [];
if ($resLoc !== false) {
    while ($l = sqlsrv_fetch_array($resLoc, SQLSRV_FETCH_ASSOC)) {
        $arrLoc[] = $l;
    }
}

// -------------------------------------------------------------
// 2. KELOLA PERIODE SOP (BUAT PERIODE BARU)
// -------------------------------------------------------------
if (isset($_POST['btnCreateSOP'])) {
    $newSopDate = !empty($_POST['new_sop_date']) ? $_POST['new_sop_date'] . "-01 00:00:00" : date('Y-m-01 00:00:00');
    
    $sqlAddSop = "INSERT INTO MOLD_SOP (SOP_SDATE) VALUES (?)";
    $stmtAddSop = sqlsrv_query($conn, $sqlAddSop, [$newSopDate]);
    if ($stmtAddSop === false) {
        $err = sqlsrv_errors();
        $alertMsg = "Gagal membuat SOP baru: " . (isset($err[0]['message']) ? $err[0]['message'] : '');
        $alertType = "danger";
    } else {
        $alertMsg = "Periode SOP baru berhasil dibuat!";
        $alertType = "success";
    }
}

// Ambil List Periode SOP
$sqlSOP = "SELECT SOP_ID, SOP_SDATE FROM MOLD_SOP ORDER BY SOP_SDATE DESC";
$resSOP = sqlsrv_query($conn, $sqlSOP);
$arrSOP = [];
if ($resSOP !== false) {
    while ($s = sqlsrv_fetch_array($resSOP, SQLSRV_FETCH_ASSOC)) {
        $arrSOP[] = $s;
    }
}

$selectedSopId = isset($_REQUEST['sop_id']) && !empty($_REQUEST['sop_id']) 
    ? intval($_REQUEST['sop_id']) 
    : (!empty($arrSOP) ? $arrSOP[0]['SOP_ID'] : 0);

// -------------------------------------------------------------
// 3. GENERATE TAGS (LOGIKA TRANSACT-SQL CTE DELPHI)
// -------------------------------------------------------------
if (isset($_POST['btnGenerateTags'])) {
    if (empty($selectedSopId)) {
        $alertMsg = "Gagal Generate: Periode SOP belum dipilih!";
        $alertType = "danger";
    } else {
        sqlsrv_begin_transaction($conn);
        try {
            // Ambil batas tanggal SOP
            $qDate = sqlsrv_query($conn, "SELECT SOP_SDATE FROM MOLD_SOP WHERE SOP_ID = ?", [$selectedSopId]);
            $rDate = sqlsrv_fetch_array($qDate, SQLSRV_FETCH_ASSOC);
            $sopDate = $rDate ? $rDate['SOP_SDATE'] : new DateTime();
            $strSopDate = ($sopDate instanceof DateTime) ? $sopDate->format('Y-m-d H:i:s') : $sopDate;

            // Hapus tags lama pada SOP ini
            $qDel = sqlsrv_query($conn, "DELETE FROM MOLD_TAGS WHERE SOP_ID = ?", [$selectedSopId]);
            if ($qDel === false) throw new Exception("Gagal reset tags lama: " . print_r(sqlsrv_errors(), true));

            // Jalankan CTE Generator langsung (Sesuai Qry_generate_Tags di Delphi)
            $sqlGen = ";WITH cte AS 
                       (
                         SELECT ID_TRANS, MOLD_ID, [DATE],
                                rn = ROW_NUMBER() OVER (PARTITION BY MOLD_ID ORDER BY [DATE] DESC)
                         FROM MOLD_TRANS_DETAIL
                         WHERE KE_TRAN IS NOT NULL AND [DATE] <= ?
                       ) 
                       INSERT INTO MOLD_TAGS (SOP_ID, MOLD_ID, ID_MOLD_TRAN_LOC)
                       SELECT ?, MTD.MOLD_ID, MTD.KE_TRAN
                       FROM MOLD_TRANS_DETAIL AS MTD
                       INNER JOIN cte ON cte.ID_TRANS = MTD.ID_TRANS
                       WHERE rn <= 1
                       ORDER BY MTD.MOLD_ID";

            $stmtGen = sqlsrv_query($conn, $sqlGen, [$strSopDate, $selectedSopId]);
            if ($stmtGen === false) throw new Exception("Gagal eksekusi query generate: " . print_r(sqlsrv_errors(), true));

            sqlsrv_commit($conn);
            $alertMsg = "Berhasil generate mold tags untuk periode SOP terpilih!";
            $alertType = "success";
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            $alertMsg = $e->getMessage();
            $alertType = "danger";
        }
    }
}

// -------------------------------------------------------------
// 4. UPDATE DATA TAG (RACK / NUMBER MOLD)
// -------------------------------------------------------------
if (isset($_POST['btnUpdateTag'])) {
    $tagMoldId   = intval($_POST['tag_mold_id']);
    $tagSopId    = intval($_POST['tag_sop_id']);
    $tagNumber   = substr(trim($_POST['tag_number_mold']), 0, 30);
    $tagRackId   = !empty($_POST['tag_rack_id']) ? intval($_POST['tag_rack_id']) : null;
    $tagLocId    = !empty($_POST['tag_loc_id']) ? intval($_POST['tag_loc_id']) : null;

    $sqlUpdateTag = "UPDATE MOLD_TAGS 
                     SET NUMBER_MOLD = ?, RACK_ID = ?, ID_MOLD_TRAN_LOC = ? 
                     WHERE SOP_ID = ? AND MOLD_ID = ?";
    $stmtUpdTag = sqlsrv_query($conn, $sqlUpdateTag, [$tagNumber, $tagRackId, $tagLocId, $tagSopId, $tagMoldId]);

    if ($stmtUpdTag === false) {
        $err = sqlsrv_errors();
        $alertMsg = "Gagal memperbarui tag: " . (isset($err[0]['message']) ? $err[0]['message'] : '');
        $alertType = "danger";
    } else {
        $alertMsg = "Data Tag Mold berhasil diperbarui!";
        $alertType = "success";
    }
}

// -------------------------------------------------------------
// 5. QUERY LOAD DATA TAGS UNTUK TABEL & EXPORT
// -------------------------------------------------------------
$tagsData = [];
if (!empty($selectedSopId)) {
    $sqlLoadTags = "SELECT 
                        T.SOP_ID,
                        T.MOLD_ID,
                        MM.PART_NO AS MOLD_NO,
                        MM.PART_NAME AS MOLD_NAME,
                        MM.CUST_ALIAS,
                        T.NUMBER_MOLD,
                        MTL.NAMA AS MOLD_LOC,
                        MTLT.[TYPE] AS LOC_TYPE,
                        MR.RACK,
                        T.RACK_ID,
                        T.ID_MOLD_TRAN_LOC
                    FROM MOLD_TAGS T
                    LEFT JOIN MOLD_MASTER MM ON T.MOLD_ID = MM.MOLD_ID
                    LEFT JOIN MOLD_TRAN_LOC MTL ON T.ID_MOLD_TRAN_LOC = MTL.ID
                    LEFT JOIN MOLD_TRAN_LOC_TYPE MTLT ON MTL.TIPE_ID = MTLT.ID
                    LEFT JOIN MOLD_RACK MR ON T.RACK_ID = MR.RACK_ID
                    WHERE T.SOP_ID = ?
                    ORDER BY MM.PART_NO ASC";
    $resLoadTags = sqlsrv_query($conn, $sqlLoadTags, [$selectedSopId]);
    if ($resLoadTags !== false) {
        while ($row = sqlsrv_fetch_array($resLoadTags, SQLSRV_FETCH_ASSOC)) {
            $tagsData[] = $row;
        }
    }
}

// Export Excel Langsung
if (isset($_POST['btnExportExcel'])) {
    $fileName = "Mold_Tags_SOP_" . $selectedSopId . "_" . date('Ymd_His') . ".xls";
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
                <th>NUMBER MOLD</th>
                <th>MOLD LOCATION</th>
                <th>JENIS LOKASI</th>
                <th>RACK</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tagsData as $t): ?>
                <tr>
                    <td style="mso-number-format:'\@';"><?php echo h($t['MOLD_NO']); ?></td>
                    <td><?php echo h($t['MOLD_NAME']); ?></td>
                    <td><?php echo h($t['CUST_ALIAS']); ?></td>
                    <td style="mso-number-format:'\@';"><?php echo h($t['NUMBER_MOLD']); ?></td>
                    <td><?php echo h($t['MOLD_LOC']); ?></td>
                    <td><?php echo h($t['LOC_TYPE']); ?></td>
                    <td><?php echo h($t['RACK']); ?></td>
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
    <title>Generate Mold Tags</title>
    <style>
        body { padding: 15px; background: #d4d0c8; font-family: Tahoma, Arial, sans-serif; font-size: 11px; margin: 0; color: #000; }
        .box-container { border: 1px solid #808080; background: #eeeeee; padding: 12px; margin-bottom: 12px; }
        .box-title { margin-top: 0; border-bottom: 1px solid #808080; padding-bottom: 4px; color: #2e4053; font-size: 12px; font-weight: bold; }
        
        .form-row { display: flex; gap: 10px; align-items: flex-end; margin-bottom: 8px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 3px; }
        .form-group input, .form-group select { width: 100%; padding: 4px; box-sizing: border-box; font-family: Tahoma; font-size: 11px; border: 1px solid #7f9db9; background: #ffffff; }

        .btn-action { background: #d4d0c8; border: 2px outset #ffffff; padding: 5px 14px; font-weight: bold; cursor: pointer; font-family: Tahoma; font-size: 11px; }
        .btn-action:active { border: 2px inset #ffffff; }
        .btn-gen { background: #8e44ad; color: #fff; border: 2px outset #bb8fce; }
        .btn-gen:hover { background: #7d3c98; }
        .btn-save { background: #2980b9; color: #fff; border: 2px outset #5dade2; }
        .btn-save:hover { background: #1f618d; }
        .btn-edit { background: #f39c12; color: #fff; border: 1px solid #d68910; padding: 2px 6px; font-size: 10px; font-weight: bold; cursor: pointer; border-radius: 2px; }
        .btn-edit:hover { background: #d68910; }

        .alert { padding: 8px 12px; margin-bottom: 12px; border-radius: 2px; font-weight: bold; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .report-table { width: 100%; border-collapse: collapse; background: #ffffff; font-size: 10px; margin-top: 8px; }
        .report-table th { background: #2e4053; color: white; padding: 6px; border: 1px solid #7f8c8d; text-align: left; }
        .report-table td { padding: 5px; border: 1px solid #bdc3c7; }
        .report-table tr:nth-child(even) { background: #f8f9fa; }
        .text-center { text-align: center; }

        /* Modal Popup Edit Tag */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 9999; justify-content: center; align-items: center; }
        .modal-content { background: #eeeeee; border: 2px solid #2c3e50; border-radius: 4px; width: 500px; max-width: 95%; padding: 15px; font-family: Tahoma, Arial, sans-serif; font-size: 11px; }
        .modal-header { font-size: 12px; font-weight: bold; color: #2c3e50; border-bottom: 1px solid #808080; padding-bottom: 5px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { cursor: pointer; font-size: 16px; font-weight: bold; color: #c0392b; }

        @media print { .box-container, .alert, .col-action, .modal-overlay { display: none !important; } body { background: #fff; padding: 0; } }
    </style>
</head>
<body>

<?php if (!empty($alertMsg)): ?>
    <div class="alert alert-<?php echo $alertType; ?>"><?php echo h($alertMsg); ?></div>
<?php endif; ?>

<div class="box-container">
    <div class="box-title">PENGATURAN PERIODE SOP & GENERATE TAGS</div>
    
    <div style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
        <!-- Pilih SOP yang ada -->
        <form method="GET" action="" style="display: flex; gap: 8px; align-items: flex-end;">
            <div class="form-group">
                <label>PILIH PERIODE SOP</label>
                <select name="sop_id" onchange="this.form.submit()" style="min-width: 200px;">
                    <?php foreach ($arrSOP as $s): ?>
                        <?php 
                            $dt = ($s['SOP_SDATE'] instanceof DateTime) ? $s['SOP_SDATE']->format('F - Y') : $s['SOP_SDATE'];
                        ?>
                        <option value="<?php echo h($s['SOP_ID']); ?>" <?php echo ($selectedSopId == $s['SOP_ID']) ? 'selected' : ''; ?>>
                            <?php echo h($dt); ?> (ID: <?php echo h($s['SOP_ID']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <noscript><button type="submit" class="btn-action">PILIH</button></noscript>
        </form>

        <!-- Buat SOP Baru -->
        <form method="POST" action="" style="display: flex; gap: 8px; align-items: flex-end; border-left: 1px dashed #999; padding-left: 15px;">
            <div class="form-group">
                <label>BUAT SOP BARU (BULAN/TAHUN)</label>
                <input type="month" name="new_sop_date" value="<?php echo date('Y-m'); ?>" required>
            </div>
            <button type="submit" name="btnCreateSOP" class="btn-action">TAMBAH SOP</button>
        </form>

        <!-- Tombol Generate Tags -->
        <form method="POST" action="" style="border-left: 1px dashed #999; padding-left: 15px;">
            <input type="hidden" name="sop_id" value="<?php echo h($selectedSopId); ?>">
            <button type="submit" name="btnGenerateTags" class="btn-action btn-gen" onclick="return confirm('Generate Tags akan mereset dan membaca ulang posisi mutasi terakhir mold. Lanjutkan?');">
                GENERATE TAGS (SOP AKTIF)
            </button>
        </form>
    </div>
</div>

<!-- TABEL HASIL GENERATE MOLD TAGS -->
<div class="box-container" style="background: #fff;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
        <span style="font-weight: bold; font-size: 12px; color: #2c3e50;">
            DAFTAR MOLD TAGS (Total: <?php echo count($tagsData); ?> Tag)
        </span>
        <div>
            <form method="POST" action="" style="display: inline;">
                <input type="hidden" name="sop_id" value="<?php echo h($selectedSopId); ?>">
                <button type="submit" name="btnExportExcel" class="btn-action" style="background:#1d6f42; color:#fff; border-color:#1d6f42;">EXPORT EXCEL</button>
            </form>
            <button type="button" class="btn-action" onclick="window.print();" style="background:#27ae60; color:#fff; border-color:#27ae60;">PRINT TAGS</button>
        </div>
    </div>

    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 15%;">MOLD NUMBER (PART NO)</th>
                <th style="width: 25%;">MOLD NAME</th>
                <th style="width: 12%;">NUMBER MOLD</th>
                <th style="width: 20%;">MOLD LOCATION</th>
                <th style="width: 10%;">JENIS LOKASI</th>
                <th style="width: 10%;">RACK</th>
                <th class="col-action text-center" style="width: 8%;">AKSI</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($tagsData)): ?>
                <?php foreach ($tagsData as $t): ?>
                    <tr>
                        <td><strong><?php echo h($t['MOLD_NO']); ?></strong></td>
                        <td><?php echo h($t['MOLD_NAME']); ?></td>
                        <td><?php echo !empty($t['NUMBER_MOLD']) ? h($t['NUMBER_MOLD']) : '-'; ?></td>
                        <td><?php echo !empty($t['MOLD_LOC']) ? h($t['MOLD_LOC']) : '-'; ?></td>
                        <td><?php echo !empty($t['LOC_TYPE']) ? h($t['LOC_TYPE']) : '-'; ?></td>
                        <td><?php echo !empty($t['RACK']) ? h($t['RACK']) : '-'; ?></td>
                        <td class="col-action text-center">
                            <button type="button" class="btn-edit" 
                                onclick='openEditTagModal(<?php echo json_encode([
                                    "sop_id"      => $t["SOP_ID"],
                                    "mold_id"     => $t["MOLD_ID"],
                                    "part_no"     => $t["MOLD_NO"],
                                    "part_name"   => $t["MOLD_NAME"],
                                    "number_mold" => $t["NUMBER_MOLD"],
                                    "rack_id"     => $t["RACK_ID"],
                                    "loc_id"      => $t["ID_MOLD_TRAN_LOC"]
                                ]); ?>)'>
                                EDIT
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center" style="padding: 20px; font-weight: bold; color: #888;">
                        Belum ada tag untuk periode SOP ini. Silakan klik tombol "GENERATE TAGS".
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- MODAL POPUP EDIT TAG -->
<div id="modalEditTag" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <span>EDIT DATA MOLD TAG</span>
            <span class="modal-close" onclick="closeEditTagModal()">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="sop_id" value="<?php echo h($selectedSopId); ?>">
            <input type="hidden" name="tag_sop_id" id="edit_tag_sop_id">
            <input type="hidden" name="tag_mold_id" id="edit_tag_mold_id">

            <div class="form-group" style="margin-bottom: 8px;">
                <label>PART NO</label>
                <input type="text" id="edit_tag_part_no" readonly style="background:#e0e0e0;">
            </div>

            <div class="form-group" style="margin-bottom: 8px;">
                <label>MOLD NAME</label>
                <input type="text" id="edit_tag_part_name" readonly style="background:#e0e0e0;">
            </div>

            <div class="form-group" style="margin-bottom: 8px;">
                <label>NUMBER MOLD (NOMOR SERI FISIK)</label>
                <input type="text" name="tag_number_mold" id="edit_tag_number" maxlength="30" placeholder="Ketik nomor seri...">
            </div>

            <div class="form-group" style="margin-bottom: 8px;">
                <label>LOKASI PENYIMPANAN / TRANSAKSI</label>
                <select name="tag_loc_id" id="edit_tag_loc">
                    <option value="">-- TANPA LOKASI --</option>
                    <?php foreach ($arrLoc as $l): ?>
                        <option value="<?php echo h($l['ID']); ?>">
                            <?php echo h($l['NAMA']); ?><?php echo !empty($l['TIPE_DESC']) ? ' (' . h($l['TIPE_DESC']) . ')' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label>RACK (RAK PENYIMPANAN)</label>
                <select name="tag_rack_id" id="edit_tag_rack">
                    <option value="">-- PILIH RAK --</option>
                    <?php foreach ($arrRack as $r): ?>
                        <option value="<?php echo h($r['RACK_ID']); ?>"><?php echo h($r['RACK']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="text-align: right;">
                <button type="button" class="btn-action" onclick="closeEditTagModal()" style="margin-right: 5px;">BATAL</button>
                <button type="submit" name="btnUpdateTag" class="btn-action btn-save">SIMPAN PERUBAHAN</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditTagModal(data) {
        document.getElementById('edit_tag_sop_id').value = data.sop_id;
        document.getElementById('edit_tag_mold_id').value = data.mold_id;
        document.getElementById('edit_tag_part_no').value = data.part_no;
        document.getElementById('edit_tag_part_name').value = data.part_name;
        document.getElementById('edit_tag_number').value = data.number_mold || '';
        document.getElementById('edit_tag_loc').value = data.loc_id || '';
        document.getElementById('edit_tag_rack').value = data.rack_id || '';

        document.getElementById('modalEditTag').style.display = 'flex';
    }

    function closeEditTagModal() {
        document.getElementById('modalEditTag').style.display = 'none';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('modalEditTag');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
</script>

</body>
</html>
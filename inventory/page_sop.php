<?php
require_once __DIR__ . '/../config/database_p1.php';

// ==================================================================================
// BAGIAN 1: PROSES DATA (POST)
// ==================================================================================

// A. HAPUS SOP
if (isset($_POST['btnHapusSOP'])) {
    $idToDelete = $_POST['hapus_id'];
    if ($idToDelete) {
        sqlsrv_begin_transaction($conn);
        try {
            sqlsrv_query($conn, "DELETE FROM TAGS WHERE SOP_ID = ?", array($idToDelete));
            $stmtDel = sqlsrv_query($conn, "DELETE FROM SOP WHERE SOP_ID = ?", array($idToDelete));
            
            if ($stmtDel) {
                sqlsrv_commit($conn);
                echo "<script>alert('Data SOP Berhasil Dihapus!'); window.location.href='?page=sop';</script>";
            } else {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            echo "<script>alert('Gagal Hapus: ".$e->getMessage()."');</script>";
        }
    }
}

// B. SIMPAN / UPDATE SOP
if (isset($_POST['btnSimpanSOP']) || isset($_POST['btnUpdateSOP'])) {
    $isUpdate = isset($_POST['btnUpdateSOP']);
    $currentID = $_POST['hapus_id'];

    $sopRef     = $_POST['SOP_REF'];
    $sopDate    = $_POST['SOP_SDATE'];
    $sopRem     = $_POST['SOP_REM'];
    $sopFinished= isset($_POST['SOP_FINISHED']) ? 'T' : 'F';
    $sopBy      = $_SESSION['db_user'];

    $tagNos     = isset($_POST['tag_no']) ? $_POST['tag_no'] : [];
    $locIds     = isset($_POST['loc_id']) ? $_POST['loc_id'] : [];
    $itemCodes  = isset($_POST['item_code']) ? $_POST['item_code'] : [];
    $tagQtys    = isset($_POST['tag_qty']) ? $_POST['tag_qty'] : [];

    if (empty($sopRef)) {
        echo "<div class='alert alert-warning'>Ref # tidak boleh kosong!</div>";
    } else {
        sqlsrv_begin_transaction($conn);
        try {
            $targetID = 0;

            if ($isUpdate) {
                $sqlHead = "UPDATE SOP SET SOP_REF=?, SOP_SDATE=?, SOP_REM=?, SOP_FINISHED=?, SOP_BY=? WHERE SOP_ID=?";
                $paramsHead = array($sopRef, $sopDate, $sopRem, $sopFinished, $sopBy, $currentID);
                if (!sqlsrv_query($conn, $sqlHead, $paramsHead)) throw new Exception("Gagal Update Header SOP");
                
                if (!sqlsrv_query($conn, "DELETE FROM TAGS WHERE SOP_ID=?", array($currentID))) throw new Exception("Gagal Reset Tags");
                $targetID = $currentID;
            } else {
                $sqlHead = "INSERT INTO SOP (SOP_REF, SOP_SDATE, SOP_REM, SOP_FINISHED, SOP_BY) 
                            OUTPUT INSERTED.SOP_ID VALUES (?, ?, ?, ?, ?)";
                $paramsHead = array($sopRef, $sopDate, $sopRem, $sopFinished, $sopBy);
                $stmtHead = sqlsrv_query($conn, $sqlHead, $paramsHead);
                if ($stmtHead === false) throw new Exception("Gagal Simpan Header");
                $rowID = sqlsrv_fetch_array($stmtHead);
                $targetID = $rowID['SOP_ID'];
            }

// UPDATE: Hapus ITEM_NAME & ITEM_UNIT dari INSERT agar kompatibel dengan Plant 2
            $sqlDet = "INSERT INTO TAGS (SOP_ID, TAG_NO, LOC_ID, ITEM_ID, ITEM_CODE, TAG_QTY, TAG_BY, TAG_SDATE, LOC_CODE) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            foreach ($tagNos as $index => $tagNo) {

            

                $locId = $locIds[$index];
                $code  = $itemCodes[$index];
                $qty   = $tagQtys[$index];

                $qItem = sqlsrv_query($conn, "SELECT TOP 1 ITEM_ID, ITEM_NAME, ITEM_UNIT FROM ITEMS WHERE ITEM_CODE = ?", array($code));
                $rItem = sqlsrv_fetch_array($qItem);
                $itemId   = $rItem ? $rItem['ITEM_ID'] : 0;
                $itemName = $rItem ? $rItem['ITEM_NAME'] : '-';
                $itemUnit = $rItem ? $rItem['ITEM_UNIT'] : '-';

                $qLoc = sqlsrv_query($conn, "SELECT TOP 1 LOC_CODE FROM LOC WHERE LOC_ID = ?", array($locId));
                $rLoc = sqlsrv_fetch_array($qLoc);
                $locCode = $rLoc ? $rLoc['LOC_CODE'] : '-';

                // ... (kodingan variable tetap sama) ...
                
                // UPDATE: Hapus $itemName & $itemUnit dari array parameter
                $paramsDet = array($targetID, $tagNo, $locId, $itemId, $code, $qty, $sopBy, $sopDate, $locCode);
                
                if (!sqlsrv_query($conn, $sqlDet, $paramsDet)) throw new Exception("Gagal Simpan Tag: $tagNo");
            }

            sqlsrv_commit($conn);
            echo "<script>alert('Data Tersimpan!'); window.location.href='?page=sop&id=$targetID';</script>";
            exit;

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    }
}

// ==================================================================================
// BAGIAN 2: LOGIKA VIEW & PAGINATION
// ==================================================================================

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'view';
$currentID = isset($_GET['id']) ? $_GET['id'] : null;

// Pagination Variables
$pageNo   = isset($_GET['hal']) ? (int)$_GET['hal'] : 1;
$limit    = 50; 
$startRow = ($pageNo - 1) * $limit + 1;
$endRow   = $pageNo * $limit;
$totalRow = 0;
$totalPage= 1;

$dataHeader = [
    'SOP_ID'=>'', 'SOP_REF'=>'', 'SOP_SDATE'=>date('Y-m-d'), 'SOP_REM'=>'', 'SOP_FINISHED'=>'F', 'SOP_BY'=>''
];
$dataDetail = [];

if ($mode != 'new') {
    if (!$currentID) {
        $qLast = sqlsrv_query($conn, "SELECT TOP 1 SOP_ID FROM SOP ORDER BY SOP_ID DESC");
        if ($rLast = sqlsrv_fetch_array($qLast)) $currentID = $rLast['SOP_ID'];
    }

    if ($currentID) {
        // 1. Ambil Header
        $qHead = sqlsrv_query($conn, "SELECT * FROM SOP WHERE SOP_ID = ?", array($currentID));
        if ($rHead = sqlsrv_fetch_array($qHead, SQLSRV_FETCH_ASSOC)) {
            $dataHeader = $rHead;
            if ($dataHeader['SOP_SDATE'] instanceof DateTime) 
                $dataHeader['SOP_SDATE'] = $dataHeader['SOP_SDATE']->format('Y-m-d');
        }

        // 2. Hitung Total Row
        $qCount = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM TAGS WHERE SOP_ID = ?", array($currentID));
        $rCount = sqlsrv_fetch_array($qCount);
        $totalRow = $rCount['total'];
        $totalPage = ceil($totalRow / $limit);

        // 3. Ambil Detail (REVISI: HINDARI KOLOM GANDA)
        // Kita tidak pakai T.*, tapi pilih kolom manual agar tidak bentrok
$sqlTags = "SELECT * FROM (
                        SELECT ROW_NUMBER() OVER (ORDER BY T.TAG_NO ASC) AS RowNum,
                               T.TAG_NO,
                               T.TAG_QTY,
                               T.LOC_ID,
                               
                               ISNULL(L.LOC_NAME, T.LOC_CODE) as LOC_NAME_DISPLAY,
                               ISNULL(I.ITEM_CODE, T.ITEM_CODE) as ITEM_CODE, 
                               I.ITEM_NAME as ITEM_NAME_DISPLAY,  -- AMBIL DARI MASTER SAJA (Lebih Aman)
                               I.ITEM_UNIT as ITEM_UNIT_DISPLAY   -- AMBIL DARI MASTER SAJA
                        FROM TAGS T
                        LEFT JOIN LOC L ON T.LOC_ID = L.LOC_ID
                        LEFT JOIN ITEMS I ON (T.ITEM_ID = I.ITEM_ID OR T.ITEM_CODE = I.ITEM_CODE)
                        WHERE T.SOP_ID = ?
                    ) AS RowData
                    WHERE RowNum >= ? AND RowNum <= ?";
        
        $paramsPaging = array($currentID, $startRow, $endRow);
        $qDet = sqlsrv_query($conn, $sqlTags, $paramsPaging);
        
        if ($qDet === false) {
            die("<div class='alert alert-danger'>Error Query SOP: " . print_r(sqlsrv_errors(), true) . "</div>");
        }

        while ($rDet = sqlsrv_fetch_array($qDet, SQLSRV_FETCH_ASSOC)) {
            $dataDetail[] = $rDet;
        }
    }
}

// Status & Navigasi
$isLocked = ($dataHeader['SOP_FINISHED'] == 'T');
$isEntry = ($mode == 'new' || ($mode == 'edit' && !$isLocked));

$prevID = $nextID = $firstID = $lastID = null;
if ($mode == 'view' && $currentID) {
    $qP = sqlsrv_query($conn, "SELECT TOP 1 SOP_ID FROM SOP WHERE SOP_ID < ? ORDER BY SOP_ID DESC", array($currentID)); if($r=sqlsrv_fetch_array($qP)) $prevID=$r['SOP_ID'];
    $qN = sqlsrv_query($conn, "SELECT TOP 1 SOP_ID FROM SOP WHERE SOP_ID > ? ORDER BY SOP_ID ASC", array($currentID)); if($r=sqlsrv_fetch_array($qN)) $nextID=$r['SOP_ID'];
    $qF = sqlsrv_query($conn, "SELECT TOP 1 SOP_ID FROM SOP ORDER BY SOP_ID ASC"); if($r=sqlsrv_fetch_array($qF)) $firstID=$r['SOP_ID'];
    $qL = sqlsrv_query($conn, "SELECT TOP 1 SOP_ID FROM SOP ORDER BY SOP_ID DESC"); if($r=sqlsrv_fetch_array($qL)) $lastID=$r['SOP_ID'];
}

$optLoc = "";
$qL = sqlsrv_query($conn, "SELECT LOC_ID, LOC_CODE, LOC_NAME FROM LOC WHERE LOC_VISIBLE=1 ORDER BY LOC_CODE ASC");
while($r=sqlsrv_fetch_array($qL)) {
    $optLoc .= "<option value='{$r['LOC_ID']}'>{$r['LOC_CODE']} - {$r['LOC_NAME']}</option>";
}
?>

<form method="POST" action="">
    <input type="hidden" name="hapus_id" value="<?php echo $currentID; ?>">

    <div class="row">
        <div class="col-12 mb-3">
            <div class="card bg-light border">
                <div class="card-body p-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="btn-group btn-group-sm">
                        <a href="?page=sop&id=<?php echo $firstID; ?>" class="btn btn-outline-secondary <?php echo (!$firstID || $mode!='view')?'disabled':''; ?>"><i class="bi bi-skip-backward-fill"></i></a>
                        <a href="?page=sop&id=<?php echo $prevID; ?>" class="btn btn-outline-secondary <?php echo (!$prevID || $mode!='view')?'disabled':''; ?>"><i class="bi bi-caret-left-fill"></i></a>
                        <button type="button" class="btn btn-outline-secondary disabled fw-bold px-3">
                            <?php echo ($mode=='new') ? 'INPUT SOP BARU' : 'REF: ' . $dataHeader['SOP_REF']; ?>
                        </button>
                        <a href="?page=sop&id=<?php echo $nextID; ?>" class="btn btn-outline-secondary <?php echo (!$nextID || $mode!='view')?'disabled':''; ?>"><i class="bi bi-caret-right-fill"></i></a>
                        <a href="?page=sop&id=<?php echo $lastID; ?>" class="btn btn-outline-secondary <?php echo (!$lastID || $mode!='view')?'disabled':''; ?>"><i class="bi bi-skip-forward-fill"></i></a>
                    </div>

                    <div class="btn-group btn-group-sm">
                        <a href="?page=sop&mode=new" class="btn btn-success fw-bold <?php echo ($mode=='new')?'active':''; ?>"><i class="bi bi-plus-lg"></i> BARU</a>
                        
                        <?php if ($isLocked): ?>
                             <button type="button" class="btn btn-secondary" disabled><i class="bi bi-lock-fill"></i> LOCKED</button>
                        <?php else: ?>
                             <a href="?page=sop&mode=edit&id=<?php echo $currentID; ?>" class="btn btn-warning fw-bold <?php echo ($mode=='edit')?'active':''; ?> <?php echo (!$currentID)?'disabled':''; ?>"><i class="bi bi-pencil-square"></i> EDIT</a>
                        <?php endif; ?>

                        <?php if ($mode=='view' && $currentID && !$isLocked): ?>
                            <button type="submit" name="btnHapusSOP" class="btn btn-danger fw-bold" onclick="return confirm('Hapus Data SOP Ini?');"><i class="bi bi-dash-lg"></i> HAPUS</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary disabled"><i class="bi bi-dash-lg"></i> HAPUS</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 mb-3">
            <div class="card" style="background-color: <?php echo $isEntry ? '#fff3cd' : '#e8f5e9'; ?>;">
                <div class="card-body py-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-6 col-md-2">
                            <label class="small fw-bold">Ref #</label>
                            <input type="text" class="form-control form-control-sm fw-bold" name="SOP_REF" value="<?php echo $dataHeader['SOP_REF']; ?>" <?php echo !$isEntry?'readonly':''; ?> required>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="small fw-bold">Date</label>
                            <input type="date" class="form-control form-control-sm" name="SOP_SDATE" value="<?php echo $dataHeader['SOP_SDATE']; ?>" <?php echo !$isEntry?'readonly':''; ?>>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="small fw-bold">PIC</label>
                            <input type="text" class="form-control form-control-sm" value="<?php echo $dataHeader['SOP_BY'] ? $dataHeader['SOP_BY'] : $_SESSION['db_user']; ?>" readonly>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="small fw-bold">Remark</label>
                            <input type="text" class="form-control form-control-sm" name="SOP_REM" value="<?php echo $dataHeader['SOP_REM']; ?>" <?php echo !$isEntry?'readonly':''; ?>>
                        </div>
                        <div class="col-6 col-md-2">
                            <div class="form-check bg-white border rounded p-2">
                                <input class="form-check-input ms-1" type="checkbox" name="SOP_FINISHED" id="chkFin" <?php echo ($dataHeader['SOP_FINISHED']=='T')?'checked':''; ?> <?php echo !$isEntry?'disabled':''; ?>>
                                <label class="form-check-label fw-bold text-success ms-2" for="chkFin">FINISHED (T)</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if($isEntry): ?>
        <div class="col-12 mb-3">
            <div class="card bg-light border-primary border-opacity-25">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-2">
                            <label class="small fw-bold">Lokasi</label>
                            <select id="inputLoc" class="form-select form-select-sm"><?php echo $optLoc; ?></select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="small fw-bold">Tag #</label>
                            <input type="text" id="inputTagNo" class="form-control form-control-sm" placeholder="No. Tag">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="small fw-bold">Item</label>
                            <select id="inputBarang" class="form-select select2-ajax" style="width: 100%;"></select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="small fw-bold">Qty</label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="inputQty" class="form-control" value="0">
                                <span class="input-group-text bg-white" id="inputUnit">-</span>
                            </div>
                        </div>
                        <div class="col-12 col-md-2">
                            <button type="button" id="btnTambah" class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-circle"></i> Tambah</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="col-12 col-lg-9">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm mb-0" id="tabelSop" style="font-size:13px; min-width: 600px;">
                        <thead class="table-dark">
                            <tr>
                                <th>LOC</th>
                                <th>TAG #</th>
                                <th>ITEM CODE</th>
                                <th>ITEM NAME</th>
                                <th class="text-end">QTY</th>
                                <th>UNIT</th>
                                <?php if($isEntry): ?><th class="text-center" width="80">AKSI</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if(!empty($dataDetail)) {
                                foreach($dataDetail as $row) {
                                    if($isEntry) {
                                        echo "<tr class='row-item'>
                                            <td><input type='hidden' name='loc_id[]' value='{$row['LOC_ID']}'>{$row['LOC_NAME_DISPLAY']}</td>
                                            <td class='cell-edit'><input type='hidden' name='tag_no[]' class='val-real' value='{$row['TAG_NO']}'><span class='val-txt'>{$row['TAG_NO']}</span><input type='text' class='form-control form-control-sm val-input d-none' value='{$row['TAG_NO']}'></td>
                                            <td><input type='hidden' name='item_code[]' value='{$row['ITEM_CODE']}'>{$row['ITEM_CODE']}</td>
                                            <td>{$row['ITEM_NAME_DISPLAY']}</td>
                                            <td class='text-end cell-edit'><input type='hidden' name='tag_qty[]' class='val-real' value='{$row['TAG_QTY']}'><span class='val-txt'>".number_format($row['TAG_QTY'], 2)."</span><input type='number' class='form-control form-control-sm val-input d-none' value='{$row['TAG_QTY']}' step='0.01'></td>
                                            <td>{$row['ITEM_UNIT_DISPLAY']}</td>
                                            <td class='text-center align-middle'>
                                                <button type='button' class='btn btn-success btn-sm btn-save-row px-2 py-0 d-none me-1'><i class='bi bi-check'></i></button>
                                                <button type='button' class='btn btn-danger btn-sm btn-hapus px-2 py-0'><i class='bi bi-x'></i></button>
                                            </td>
                                        </tr>";
                                    } else {
                                        echo "<tr>
                                            <td>{$row['LOC_NAME_DISPLAY']}</td>
                                            <td class='font-monospace'>{$row['TAG_NO']}</td>
                                            <td class='font-monospace text-primary'>{$row['ITEM_CODE']}</td>
                                            <td>{$row['ITEM_NAME_DISPLAY']}</td>
                                            <td class='text-end fw-bold'>".number_format($row['TAG_QTY'], 2)."</td>
                                            <td>{$row['ITEM_UNIT_DISPLAY']}</td>
                                        </tr>";
                                    }
                                }
                            } else {
                                echo "<tr><td colspan='7' class='text-center py-4 text-muted'>Tidak ada data tag.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPage > 1 && $mode != 'new'): ?>
                <div class="card-footer bg-white py-2">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <small class="text-muted mb-2 mb-md-0">
                            Menampilkan <?php echo count($dataDetail); ?> dari <b><?php echo number_format($totalRow); ?></b> data.
                            (Hal <?php echo $pageNo; ?>/<?php echo $totalPage; ?>)
                        </small>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo ($pageNo <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=sop&id=<?php echo $currentID; ?>&mode=<?php echo $mode; ?>&hal=<?php echo $pageNo - 1; ?>">Prev</a>
                                </li>
                                
                                <?php
                                $start = max(1, $pageNo - 2);
                                $end = min($totalPage, $pageNo + 2);
                                if($start > 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                                for ($i = $start; $i <= $end; $i++) {
                                    $active = ($i == $pageNo) ? 'active' : '';
                                    echo "<li class='page-item $active'><a class='page-link' href='?page=sop&id=$currentID&mode=$mode&hal=$i'>$i</a></li>";
                                }
                                if($end < $totalPage) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                                ?>

                                <li class="page-item <?php echo ($pageNo >= $totalPage) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=sop&id=<?php echo $currentID; ?>&mode=<?php echo $mode; ?>&hal=<?php echo $pageNo + 1; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12 col-lg-3 mt-3 mt-lg-0">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-dark text-white text-center fw-bold py-2">AKSI</div>
                <div class="card-body d-grid gap-2">
                    <?php if ($isEntry): ?>
                        <?php if ($mode=='edit'): ?>
                             <button type="submit" name="btnUpdateSOP" class="btn btn-warning w-100 fw-bold" onclick="return confirm('Simpan Perubahan?');"><i class="bi bi-save"></i> UPDATE</button>
                        <?php else: ?>
                             <button type="submit" name="btnSimpanSOP" class="btn btn-success w-100 fw-bold" onclick="return confirm('Simpan SOP Baru?');"><i class="bi bi-save"></i> SIMPAN</button>
                        <?php endif; ?>
                        <a href="?page=sop&id=<?php echo $currentID; ?>" class="btn btn-outline-secondary w-100">BATAL</a>
                    <?php else: ?>
                        <div class="alert alert-<?php echo $isLocked?'danger':'info'; ?> text-center small mb-0">
                            <?php echo $isLocked ? '<i class="bi bi-lock-fill"></i> SOP FINISHED' : 'READ ONLY MODE'; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold py-2 border-bottom">LAPORAN</div>
                <div class="card-body p-2">
                    <div class="list-group list-group-flush small">
                        <?php 
                        // Cegah klik tombol kalau belum ada ID (sedang mode BARU)
                        $linkDisabled = empty($currentID) ? 'disabled text-muted' : '';
                        ?>

                       <a href="report_tag_list.php?sop=<?php echo $currentID; ?>" target="_blank" class="list-group-item list-group-item-action py-2">
                            <i class="bi bi-card-list me-2 text-primary"></i> <b>Tag List Detail</b>
                        </a>
                        
                        <a href="report_tag_summary.php?sop=<?php echo $currentID; ?>" target="_blank" class="list-group-item list-group-item-action py-2 <?php echo $linkDisabled; ?>">
                            <i class="bi bi-table me-2 text-success"></i> <b>Tag Summary By Item</b>
                        </a>

                        <a href="#" class="list-group-item list-group-item-action py-2 disabled text-muted">
                            <i class="bi bi-calculator me-2"></i> Var. Before Adjust
                        </a>
                        
                        <a href="#" class="list-group-item list-group-item-action py-2 disabled text-muted">
                            <i class="bi bi-arrow-left-right me-2"></i> SOP Conversion
                        </a>
                    </div>
                </div>
            </div>
            </div>
</form>

<?php if ($isEntry): ?>
<script>
$(document).ready(function() {
    $('#inputBarang').on('select2:select', function (e) { 
        $('#inputUnit').text('Kg'); 
        $('#inputQty').focus(); 
    });

    $('#btnTambah').click(function() {
        var locId   = $('#inputLoc').val();
        var locName = $('#inputLoc option:selected').text().split(' - ')[1];
        var tagNo   = $('#inputTagNo').val();
        var code    = $('#inputBarang').val();
        var name    = $('#inputBarang option:selected').text();
        var qty     = $('#inputQty').val();
        var unit    = $('#inputUnit').text();

        if(!code || !tagNo) { alert('Mohon isi Tag No dan Barang!'); return; }

        var html = `<tr class='row-item'>
            <td><input type='hidden' name='loc_id[]' value='${locId}'>${locName}</td>
            <td class='cell-edit'><input type='hidden' name='tag_no[]' class='val-real' value='${tagNo}'><span class='val-txt'>${tagNo}</span><input type='text' class='form-control form-control-sm val-input d-none' value='${tagNo}'></td>
            <td><input type='hidden' name='item_code[]' value='${code}'>${code}</td>
            <td>${name}</td>
            <td class='text-end cell-edit'><input type='hidden' name='tag_qty[]' class='val-real' value='${qty}'><span class='val-txt'>${parseFloat(qty).toFixed(2)}</span><input type='number' class='form-control form-control-sm val-input d-none' value='${qty}' step='0.01'></td>
            <td>${unit}</td>
            <td class='text-center align-middle'>
                <button type='button' class='btn btn-success btn-sm btn-save-row px-2 py-0 d-none me-1'><i class='bi bi-check'></i></button>
                <button type='button' class='btn btn-danger btn-sm btn-hapus px-2 py-0'><i class='bi bi-x'></i></button>
            </td>
        </tr>`;
        $('#tabelSop tbody').append(html);
        $('#emptyRowMsg').hide();
        $('#inputTagNo').val(''); $('#inputBarang').val(null).trigger('change'); $('#inputQty').val(0); $('#inputTagNo').focus();
    });

    $(document).on('click', '.btn-hapus', function() { $(this).closest('tr').remove(); });
    $(document).on('dblclick', '.cell-edit', function() {
        var $td = $(this); $td.find('.val-txt').addClass('d-none'); $td.find('.val-input').removeClass('d-none').focus().select(); $td.closest('tr').find('.btn-save-row').removeClass('d-none');
    });
    $(document).on('click', '.btn-save-row', function() {
        var $row = $(this).closest('tr');
        $row.find('.cell-edit').each(function() {
            var $td = $(this); var newVal = $td.find('.val-input').val();
            $td.find('.val-real').val(newVal);
            if($td.hasClass('text-end')) newVal = parseFloat(newVal).toFixed(2);
            $td.find('.val-txt').text(newVal).removeClass('d-none'); $td.find('.val-input').addClass('d-none');
        });
        $(this).addClass('d-none');
    });
    $(document).on('keypress', '.val-input', function(e) { if(e.which == 13) { e.preventDefault(); $(this).closest('tr').find('.btn-save-row').click(); } });
});
</script>
<?php endif; ?>
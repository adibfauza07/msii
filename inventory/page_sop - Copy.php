<?php
require_once __DIR__ . '/../config/database_p1.php';
set_time_limit(120); 

// ==================================================================================
// DETEKSI AJAX
// ==================================================================================
$isAjax = (isset($_GET['ajax']) && $_GET['ajax'] == '1') || (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1');

// ==================================================================================
// FITUR: PENCARIAN ITEM AUTOCOMPLETE VIA AJAX (ENTRY ROW)
// ==================================================================================
if (isset($_POST['ajax_search_item'])) {
    while (ob_get_level()) ob_end_clean(); 
    header('Content-Type: application/json');
    
    $q = trim($_POST['q']);
    $res = [];
    
    $sql = "SELECT TOP 40 ITEM_ID, ITEM_CODE, ITEM_NAME, ITEM_UNIT FROM ITEMS WHERE ITEM_CODE LIKE ? OR ITEM_NAME LIKE ? ORDER BY ITEM_CODE ASC";
    $stmt = sqlsrv_query($conn, $sql, array("%$q%", "%$q%"));
    
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $res[] = [
                'ITEM_ID' => $row['ITEM_ID'],
                'ITEM_CODE' => trim((string)$row['ITEM_CODE']),
                'ITEM_NAME' => trim((string)$row['ITEM_NAME']),
                'ITEM_UNIT' => trim((string)$row['ITEM_UNIT'])
            ];
        }
    }
    echo json_encode($res);
    exit;
}

// ==================================================================================
// FITUR: PENCARIAN TOP SEARCH (TAG/ITEM) AUTOCOMPLETE VIA AJAX
// ==================================================================================
if (isset($_POST['ajax_top_search'])) {
    while (ob_get_level()) ob_end_clean(); 
    header('Content-Type: application/json');
    
    $q = trim($_POST['q']);
    $sop_id = (int)$_POST['sop_id'];
    $res = [];
    
    // PERBAIKAN: Menambahkan T.TAG_QTY dan join tabel LOC untuk mendapatkan LOC_NAME
    $sql = "SELECT TOP 15 T.TAG_NO, ISNULL(I.ITEM_CODE, T.ITEM_CODE) AS ITEM_CODE, I.ITEM_NAME, 
                   T.TAG_QTY, ISNULL(L.LOC_NAME, T.LOC_CODE) AS LOC_NAME
            FROM TAGS T 
            LEFT JOIN ITEMS I ON T.ITEM_CODE = I.ITEM_CODE
            LEFT JOIN LOC L ON T.LOC_ID = L.LOC_ID
            WHERE T.SOP_ID = ? AND (T.TAG_NO LIKE ? OR I.ITEM_CODE LIKE ? OR I.ITEM_NAME LIKE ?)
            ORDER BY T.TAG_NO ASC";
    $stmt = sqlsrv_query($conn, $sql, array($sop_id, "%$q%", "%$q%", "%$q%"));
    
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $res[] = [
                'TAG_NO' => trim((string)$row['TAG_NO']),
                'ITEM_CODE' => trim((string)$row['ITEM_CODE']),
                'ITEM_NAME' => trim((string)$row['ITEM_NAME']),
                'TAG_QTY' => (float)$row['TAG_QTY'], // Ambil Qty
                'LOC_NAME' => trim((string)$row['LOC_NAME']) // Ambil Lokasi
            ];
        }
    }
    echo json_encode($res);
    exit;
}

// ==================================================================================
// FITUR: HAPUS TAG SATUAN VIA AJAX
// ==================================================================================
if (isset($_POST['ajax_delete_tag'])) {
    while (ob_get_level()) ob_end_clean(); 
    
    header('Content-Type: application/json');
    $delSopId = (int)$_POST['sop_id'];
    $delTagNo = $_POST['tag_no'];
    
    $sqlDel = "DELETE FROM TAGS WHERE SOP_ID = ? AND TAG_NO = ?";
    $stmtDel = sqlsrv_query($conn, $sqlDel, array($delSopId, $delTagNo));
    
    if ($stmtDel) {
        echo json_encode(['success' => true]);
    } else {
        $err = sqlsrv_errors();
        echo json_encode(['success' => false, 'message' => $err[0]['message']]);
    }
    exit;
}

// ==================================================================================
// BAGIAN 1: PROSES DATA (POST)
// ==================================================================================

if (isset($_POST['btnHapusSOP'])) {
    $idToDelete = $_POST['hapus_id'];
    if ($idToDelete) {
        $sqlCekDetail = "SELECT COUNT(*) as total_tag FROM TAGS WHERE SOP_ID = ?";
        $stmtCek = sqlsrv_query($conn, $sqlCekDetail, array($idToDelete));
        $rowCek = sqlsrv_fetch_array($stmtCek);
        
        if ($rowCek['total_tag'] > 0) {
            echo "<script>alert('GAGAL MENGHAPUS: SOP tidak dapat dihapus karena sudah memiliki data Tag/Detail. Hapus semua Tag terlebih dahulu!'); window.history.back();</script>";
        } else {
            sqlsrv_begin_transaction($conn);
            try {
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
}

if (isset($_POST['btnSimpanSOP']) || isset($_POST['btnUpdateSOP']) || (isset($_POST['action']) && $_POST['action'] == 'save')) {
    $isUpdate = isset($_POST['btnUpdateSOP']) || (isset($_POST['action']) && $_POST['action'] == 'save' && $_POST['hapus_id'] > 0);
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
    $oldTagNos  = isset($_POST['old_tag_no']) ? $_POST['old_tag_no'] : [];

    if (empty($sopRef)) {
        if ($isAjax) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(["status" => "error", "message" => "Ref # tidak boleh kosong!"]);
            exit;
        }
        echo "<div class='alert alert-warning'>Ref # tidak boleh kosong!</div>";
    } else {
        sqlsrv_begin_transaction($conn);
        try {
            $targetID = 0;

            if ($isUpdate) {
                $sqlHead = "UPDATE SOP SET SOP_REF=?, SOP_SDATE=?, SOP_REM=?, SOP_FINISHED=?, SOP_BY=? WHERE SOP_ID=?";
                $paramsHead = array($sopRef, $sopDate, $sopRem, $sopFinished, $sopBy, $currentID);
                if (!sqlsrv_query($conn, $sqlHead, $paramsHead)) throw new Exception("Gagal Update Header SOP");
                $targetID = $currentID;
            } else {
                $sqlHead = "INSERT INTO SOP (SOP_REF, SOP_SDATE, SOP_REM, SOP_FINISHED, SOP_BY) OUTPUT INSERTED.SOP_ID VALUES (?, ?, ?, ?, ?)";
                $paramsHead = array($sopRef, $sopDate, $sopRem, $sopFinished, $sopBy);
                $stmtHead = sqlsrv_query($conn, $sqlHead, $paramsHead);
                if ($stmtHead === false) throw new Exception("Gagal Simpan Header");
                $rowID = sqlsrv_fetch_array($stmtHead);
                $targetID = $rowID['SOP_ID'];
            }

            foreach ($tagNos as $index => $tagNo) {
                $locId  = $locIds[$index];
                $code   = $itemCodes[$index];
                $qty    = $tagQtys[$index];
                $oldTag = isset($oldTagNos[$index]) && $oldTagNos[$index] !== '' ? substr($oldTagNos[$index], 0, 7) : substr($tagNo, 0, 7);

                $qItem = sqlsrv_query($conn, "SELECT TOP 1 ITEM_ID, ITEM_NAME, ITEM_UNIT FROM ITEMS WHERE ITEM_CODE = ?", array($code));
                $rItem = sqlsrv_fetch_array($qItem);
                $itemId = $rItem ? $rItem['ITEM_ID'] : 0;

                $qLoc = sqlsrv_query($conn, "SELECT TOP 1 LOC_CODE FROM LOC WHERE LOC_ID = ?", array($locId));
                $rLoc = sqlsrv_fetch_array($qLoc);
                $locCode = $rLoc ? $rLoc['LOC_CODE'] : '-';

                if ($itemId === 0) throw new Exception("Item Code {$code} tidak ada di Master Database.");

                $safeTagNo = substr($tagNo, 0, 7);
                $qCek = sqlsrv_query($conn, "SELECT TAG_NO FROM TAGS WHERE SOP_ID=? AND TAG_NO=?", array($targetID, $oldTag));
                
                if (sqlsrv_has_rows($qCek)) {
                    $sqlUpd = "UPDATE TAGS SET TAG_NO=?, LOC_ID=?, ITEM_ID=?, ITEM_CODE=?, TAG_QTY=?, TAG_BY=?, LOC_CODE=? WHERE SOP_ID=? AND TAG_NO=?";
                    $paramsUpd = array($safeTagNo, $locId, $itemId, substr($code, 0, 8), $qty, substr($sopBy, 0, 15), substr($locCode, 0, 5), $targetID, $oldTag);
                    $stmtDet = sqlsrv_query($conn, $sqlUpd, $paramsUpd);
                } else {
                    $sqlIns = "INSERT INTO TAGS (SOP_ID, TAG_NO, LOC_ID, ITEM_ID, ITEM_CODE, TAG_QTY, TAG_BY, LOC_CODE) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $paramsIns = array($targetID, $safeTagNo, $locId, $itemId, substr($code, 0, 8), $qty, substr($sopBy, 0, 15), substr($locCode, 0, 5));
                    $stmtDet = sqlsrv_query($conn, $sqlIns, $paramsIns);
                }
                
                if ($stmtDet === false) {
                    $err = sqlsrv_errors();
                    throw new Exception("Gagal Menyimpan Tag: $safeTagNo \n" . $err[0]['message']);
                }
            }

            sqlsrv_commit($conn);

            if ($isAjax) {
                while (ob_get_level()) ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(["status" => "success", "sop_id" => $targetID]);
                exit;
            }

            echo "<script>alert('Data Tersimpan Sempurna!'); window.location.href='?page=sop&id=$targetID';</script>";
            exit;

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            
            if ($isAjax) {
                while (ob_get_level()) ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
                exit;
            }
            
            echo "<div class='container mt-3'><div class='alert alert-danger shadow'><h4>🚨 ERROR</h4><textarea class='form-control' rows='4' readonly>" . htmlspecialchars($e->getMessage()) . "</textarea></div></div>";
        }
    }
}

// ==================================================================================
// BAGIAN 2: LOGIKA VIEW, SEARCH & PAGINATION
// ==================================================================================

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'view';
$currentID = isset($_GET['id']) ? $_GET['id'] : null;
$searchQ = isset($_GET['q']) ? trim($_GET['q']) : '';

$pageNo   = isset($_GET['hal']) ? (int)$_GET['hal'] : 1;
if ($pageNo < 1) $pageNo = 1;
$limit    = 50; 
$startRow = ($pageNo - 1) * $limit + 1;
$endRow   = $pageNo * $limit;

$totalRow = 0;
$totalPage= 1;
$totalRowSop = 0; 

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
        // Ambil Header
        $qHead = sqlsrv_query($conn, "SELECT * FROM SOP WHERE SOP_ID = ?", array($currentID));
        if ($rHead = sqlsrv_fetch_array($qHead, SQLSRV_FETCH_ASSOC)) {
            $dataHeader = $rHead;
            if ($dataHeader['SOP_SDATE'] instanceof DateTime) 
                $dataHeader['SOP_SDATE'] = $dataHeader['SOP_SDATE']->format('Y-m-d');
        }

        // Terapkan filter pencarian
        $searchCondition = "";
        $paramsCount = array($currentID);

        if ($searchQ !== '') {
            $searchCondition = " AND (T.TAG_NO LIKE ? OR T.ITEM_CODE LIKE ? OR I.ITEM_NAME LIKE ?) ";
            $paramsCount[] = "%$searchQ%";
            $paramsCount[] = "%$searchQ%";
            $paramsCount[] = "%$searchQ%";
        }

        // Hitung total data berdasarkan filter
        $qCount = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM TAGS T LEFT JOIN ITEMS I ON T.ITEM_CODE = I.ITEM_CODE WHERE T.SOP_ID = ?" . $searchCondition, $paramsCount);
        $rCount = sqlsrv_fetch_array($qCount);
        $totalRow = $rCount['total'];
        $totalPage = ceil($totalRow / $limit);
        $totalRowSop = $totalRow; // Karena query selalu dijalankan

        // Query memuat data (Default 50 Baris terakhir yang baru diinput berkat T.TAG_NO DESC)
        $sqlTags = "SELECT * FROM (
                        SELECT ROW_NUMBER() OVER (ORDER BY T.TAG_NO DESC) AS RowNum,
                               T.TAG_NO, T.TAG_QTY, T.LOC_ID,
                               ISNULL(L.LOC_NAME, T.LOC_CODE) as LOC_NAME_DISPLAY,
                               ISNULL(I.ITEM_CODE, T.ITEM_CODE) as ITEM_CODE, 
                               I.ITEM_NAME as ITEM_NAME_DISPLAY,
                               I.ITEM_UNIT as ITEM_UNIT_DISPLAY
                        FROM TAGS T
                        LEFT JOIN LOC L ON T.LOC_ID = L.LOC_ID
                        LEFT JOIN ITEMS I ON T.ITEM_CODE = I.ITEM_CODE
                        WHERE T.SOP_ID = ? $searchCondition
                    ) AS RowData
                    WHERE RowNum >= ? AND RowNum <= ?";
        
        $paramsPaging = $paramsCount; 
        $paramsPaging[] = $startRow;
        $paramsPaging[] = $endRow;
        
        $qDet = sqlsrv_query($conn, $sqlTags, $paramsPaging);
        
        while ($rDet = sqlsrv_fetch_array($qDet, SQLSRV_FETCH_ASSOC)) {
            $dataDetail[] = $rDet;
        }
    }
}

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

<!-- STYLE KHUSUS TAMPILAN CLASSIC DESKTOP APP -->
<style>
    .classic-app { background-color: #f0f0f0; font-family: "Segoe UI", Tahoma, sans-serif; font-size: 12px; color: #000; padding-bottom: 20px; }
    .classic-menu { background-color: #f0f0f0; border-bottom: 1px solid #ccc; padding: 2px 10px; }
    .classic-menu .dropdown-toggle { color: #000; font-size: 12px; text-decoration: none; padding: 5px 10px; }
    .classic-menu .dropdown-toggle:hover { background-color: #e0e0e0; }
    .classic-header { padding: 10px 15px; }
    .c-label { display: block; font-size: 11px; margin-bottom: 2px; }
    .c-input { border: 1px solid #7a7a7a; padding: 2px 4px; font-size: 12px; height: 22px; width: 100%; box-sizing: border-box; }
    .c-input[readonly] { background-color: #e9e9e9; }
    
    .nav-arrows { display: inline-flex; border: 1px solid #a0a0a0; background: #fff; margin-bottom: 5px; }
    .nav-arrows button { background: transparent; border: none; border-right: 1px solid #e0e0e0; padding: 2px 8px; cursor: pointer; color: #000; font-size: 11px; }
    .nav-arrows button:last-child { border-right: none; }
    .nav-arrows button:hover { background: #e5f1fb; }
    
    .control-group { border: 1px solid #a0a0a0; padding: 8px 10px 6px 10px; position: relative; display: inline-flex; gap: 5px; background-color: #e9ecd5; }
    .control-legend { font-size: 11px; position: absolute; top: -7px; left: 10px; background: #e9ecd5; padding: 0 5px; color: #333; }
    .btn-control { border: 1px solid #7a7a7a; background: #f0f0f0; padding: 2px 15px; font-size: 12px; cursor: pointer; color: #000; min-width: 70px; text-align: center; }
    .btn-control:hover:not(:disabled) { background: #e5f1fb; border-color: #0078d7; }
    .btn-control:disabled { color: #aaa; cursor: default; border-color: #ccc; }
    
    .c-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #ccc; }
    .c-table th { background: #e0e0e0; border: 1px solid #ccc; border-right: 1px solid #fff; padding: 4px; text-align: left; font-weight: normal; font-size: 11px; }
    .c-table td { border: 1px solid #ccc; padding: 4px; font-size: 12px; }
    .c-table tr:nth-child(even) { background-color: #f9f9f9; }
    
    .search-box { display: flex; align-items: center; background: #fff; border: 1px solid #7a7a7a; padding: 1px 5px; height: 22px; }
    .search-box input { border: none; outline: none; font-size: 11px; width: 250px; }
    
    /* ENTRY ROW DI DALAM TABEL */
    #entryRow { background-color: #e9ecd5; border-bottom: 2px solid #a0a0a0; }
    #entryRow input, #entryRow select { background-color: #fff; border: 1px solid #0078d7; }
    #entryRow input:focus, #entryRow select:focus { background-color: #e5f1fb; outline: none; border-color: #005a9e; }
    
    /* CUSTOM AUTOCOMPLETE STYLE */
    .ac-box { position: absolute; z-index: 9999; background: #ffffff; color: #333; border: 1px solid #ccc; border-radius: 2px; max-height: 200px; overflow-y: auto; min-width: 300px; display: none; font-size: 12px; box-shadow: 2px 2px 5px rgba(0,0,0,0.2); }
    .ac-item { padding: 4px 8px; cursor: pointer; border-bottom: 1px solid #f4f4f4; }
    .ac-item:hover, .ac-item.active { background: #008080; color: #ffffff; }
</style>

<div class="classic-app">
    <form method="POST" action="" id="mainForm">
        <input type="hidden" name="hapus_id" id="hapus_id" value="<?php echo $currentID; ?>">
        <input type="hidden" name="action" id="form_action" value="save">

        <!-- 1. MENU Laporan -->
        <div class="classic-menu d-flex align-items-center">
            <img src="https://img.icons8.com/color/16/000000/report-card.png" class="me-1"> <b>Stock Opname</b>
            <div class="dropdown ms-3">
                <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Laporan
                </a>
                <ul class="dropdown-menu dropdown-menu-sm" style="font-size: 12px; border-radius: 0;">
                    <?php $lDis = empty($currentID) ? 'disabled' : ''; ?>
                    <li><a class="dropdown-item <?php echo $lDis; ?>" href="report_tag_list.php?sop=<?php echo $currentID; ?>" target="_blank">Tag List Detail</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item <?php echo $lDis; ?>" href="report_var_before.php?id=<?php echo $currentID; ?>" target="_blank">Variance Before Adjust Material</a></li>
                    <li><a class="dropdown-item <?php echo $lDis; ?>" href="report_var_before_barang.php?id=<?php echo $currentID; ?>" target="_blank">Variance Before Adjust Barang Jadi</a></li>
                    <li><a class="dropdown-item <?php echo $lDis; ?>" href="var_before_all.php?id=<?php echo $currentID; ?>" target="_blank">Variance Before Adjust Barang Jadi ALL</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalTagSummary">Tag Summary By Item</a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalStockAnalysis">Stock Analysis Report</a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalTagType">Report Tag By Type</a></li>
                </ul>
            </div>
        </div>

        <!-- 2. HEADER SECTION -->
        <div class="classic-header pb-1">
            <div class="row gx-2 mb-1" style="max-width: 600px;">
                <div class="col-4">
                    <span class="c-label">Ref #</span>
                    <input type="text" class="c-input" name="SOP_REF" value="<?php echo $dataHeader['SOP_REF']; ?>" <?php echo !$isEntry?'readonly':''; ?> required>
                </div>
                <div class="col-4">
                    <span class="c-label">Date</span>
                    <input type="date" class="c-input" name="SOP_SDATE" value="<?php echo $dataHeader['SOP_SDATE']; ?>" <?php echo !$isEntry?'readonly':''; ?>>
                </div>
            </div>
            <div class="row gx-2 mb-1" style="max-width: 600px;">
                <div class="col-4">
                    <span class="c-label">PIC</span>
                    <input type="text" class="c-input" value="<?php echo $dataHeader['SOP_BY'] ? $dataHeader['SOP_BY'] : $_SESSION['db_user']; ?>" readonly>
                </div>
                <div class="col-8">
                    <span class="c-label">Remark</span>
                    <input type="text" class="c-input" name="SOP_REM" value="<?php echo $dataHeader['SOP_REM']; ?>" <?php echo !$isEntry?'readonly':''; ?>>
                </div>
            </div>
            <div class="row gx-2 mb-2" style="max-width: 600px;">
                <div class="col-2">
                    <span class="c-label">Finished</span>
                    <input type="text" class="c-input text-center fw-bold" style="width: 30px;" value="<?php echo ($dataHeader['SOP_FINISHED']=='T')?'T':'F'; ?>" readonly>
                    <input type="checkbox" name="SOP_FINISHED" style="display:none;" <?php echo ($dataHeader['SOP_FINISHED']=='T')?'checked':''; ?> <?php echo !$isEntry?'disabled':''; ?>>
                    <?php if($isEntry): ?>
                        <small class="text-muted" style="cursor:pointer;" onclick="$('input[name=SOP_FINISHED]').click(); $(this).prev().val($('input[name=SOP_FINISHED]').is(':checked')?'T':'F');">(Klik T/F)</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 3. PANAH NAVIGASI & CONTROL GROUP -->
        <div style="padding: 0 15px;">
            <div class="nav-arrows">
                <button type="button" onclick="window.location.href='?page=sop&id=<?php echo $firstID; ?>'" <?php echo (!$firstID || $mode!='view')?'disabled':''; ?>><i class="bi bi-chevron-bar-left"></i></button>
                <button type="button" onclick="window.location.href='?page=sop&id=<?php echo $prevID; ?>'" <?php echo (!$prevID || $mode!='view')?'disabled':''; ?>><i class="bi bi-caret-left-fill"></i></button>
                <button type="button" onclick="window.location.href='?page=sop&id=<?php echo $nextID; ?>'" <?php echo (!$nextID || $mode!='view')?'disabled':''; ?>><i class="bi bi-caret-right-fill"></i></button>
                <button type="button" onclick="window.location.href='?page=sop&id=<?php echo $lastID; ?>'" <?php echo (!$lastID || $mode!='view')?'disabled':''; ?>><i class="bi bi-chevron-bar-right"></i></button>
            </div>
            <br>
            <div class="d-flex flex-wrap align-items-center mb-2" style="gap: 15px;">
                <div class="control-group">
                    <div class="control-legend">Control</div>
                    
                    <button type="button" class="btn-control" onclick="window.location.href='?page=sop&mode=new'" <?php echo $mode=='new'?'disabled':''; ?>>Tambah</button>
                    <button type="button" class="btn-control" onclick="window.location.href='?page=sop&mode=edit&id=<?php echo $currentID; ?>'" <?php echo ($mode=='edit' || $isLocked || !$currentID)?'disabled':''; ?>>Rubah</button>
                    <button type="submit" name="btnHapusSOP" class="btn-control" onclick="return confirm('Hapus Data SOP Ini?');" <?php echo ($mode=='view' && $currentID && !$isLocked)?'':'disabled'; ?>>Hapus</button>
                    
                    <?php if($mode=='edit'): ?>
                        <button type="button" class="btn-control" onclick="saveHeaderOnly();">Simpan</button>
                    <?php elseif($mode=='new'): ?>
                        <button type="submit" name="btnSimpanSOP" class="btn-control" onclick="return confirm('Simpan SOP Baru?');">Simpan</button>
                    <?php else: ?>
                        <button type="button" class="btn-control" disabled>Simpan</button>
                    <?php endif; ?>
                    
                    <button type="button" class="btn-control" onclick="window.location.href='?page=sop&id=<?php echo $currentID; ?>'" <?php echo ($mode=='view')?'disabled':''; ?>>Batal</button>
                    <button type="button" class="btn-control" onclick="window.location.reload();">Refresh</button>
                </div>

                <!-- KOLOM PENCARIAN UTAMA DENGAN AUTOCOMPLETE (AKTIF DI VIEW & EDIT) -->
                <div class="search-box">
                    <i class="bi bi-search text-muted me-1" style="font-size: 10px;"></i>
                    <input type="text" id="topSearchInput" value="<?php echo htmlspecialchars($searchQ); ?>" placeholder="Cari Tag # atau Item (Enter)..." 
                           autocomplete="off" 
                           oninput="showTopSearchAC(this)" 
                           onkeydown="topSearchKey(event, this)">
                </div>
                
                <?php if($searchQ != ''): ?>
                    <a href="?page=sop&id=<?php echo $currentID; ?>&mode=<?php echo $mode; ?>" class="text-danger small text-decoration-none fw-bold">✕ Reset Filter</a>
                <?php endif; ?>

                <span class="text-muted small ms-auto">
                    Menampilkan <b><?php echo count($dataDetail); ?></b> baris data dari total <b><?php echo number_format($totalRow); ?></b> <?php echo $searchQ ? 'hasil pencarian' : 'data SOP'; ?> (Hal <?php echo $pageNo; ?>/<?php echo max(1, $totalPage); ?>)
                </span>
            </div>
        </div>

        <!-- 4. TABEL BESERTA ENTRY ROW -->
        <div style="padding: 0 15px; margin-top: 5px; max-height: 55vh; overflow-y: auto;">
            <table class="c-table" id="tabelSop">
                <thead>
                    <tr>
                        <th width="30"></th>
                        <th width="120">LOC</th>
                        <th width="100">TAG #</th>
                        <th width="180">ITEM (Search Kode/Nama)</th>
                        <th>ITEM NAME</th>
                        <th class="text-end" width="80">QTY</th>
                        <th width="50">UNIT</th>
                        <?php if($isEntry): ?><th class="text-center" width="60">AKSI</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <!-- ENTRY ROW: Tampil paling atas saat mode Entry -->
                    <?php if($isEntry): ?>
                    <tr id="entryRow">
                        <td class="text-center fw-bold text-success" title="Baris Input Baru">+</td>
                        <td><select id="inputLoc" class="c-input"><?php echo $optLoc; ?></select></td>
                        <td><input type="text" id="inputTagNo" class="c-input" placeholder="Tag # (Enter)" autocomplete="off"></td>
                        <td>
                            <input type="hidden" id="inputItemId">
                            <input type="text" id="inputItemCode" class="c-input" placeholder="Ketik Kode/Nama..." autocomplete="off" oninput="showItemAC(this)" onkeydown="itemKey(event, this)">
                        </td>
                        <td><input type="text" id="inputItemName" class="c-input bg-light" readonly tabindex="-1"></td>
                        <td class="text-end"><input type="number" id="inputQty" class="c-input text-end" value="0" step="0.01"></td>
                        <td id="inputUnit" class="text-center">-</td>
                        <td class="text-center"><small class="text-muted" style="font-size:10px;">Enter=Save</small></td>
                    </tr>
                    <?php endif; ?>

                    <?php 
                    if(!empty($dataDetail)) {
                        foreach($dataDetail as $row) {
                            if($isEntry) {
                                echo "<tr class='row-item' data-sop-id='{$currentID}' data-old-tag='{$row['TAG_NO']}'>
                                    <td class='text-center text-muted'>▶</td>
                                    <td><input type='hidden' name='loc_id[]' value='{$row['LOC_ID']}'>{$row['LOC_NAME_DISPLAY']}</td>
                                    <td class='cell-edit'><input type='hidden' name='tag_no[]' class='val-real' value='{$row['TAG_NO']}'><span class='val-txt'>{$row['TAG_NO']}</span><input type='text' class='c-input val-input d-none' value='{$row['TAG_NO']}'></td>
                                    <td width='120'><input type='hidden' name='item_code[]' value='{$row['ITEM_CODE']}'>{$row['ITEM_CODE']}</td>
                                    <td>{$row['ITEM_NAME_DISPLAY']}</td>
                                    <td class='text-end cell-edit'><input type='hidden' name='tag_qty[]' class='val-real' value='{$row['TAG_QTY']}'><span class='val-txt'>".number_format($row['TAG_QTY'], 2)."</span><input type='number' class='c-input val-input d-none text-end' value='{$row['TAG_QTY']}' step='0.01'></td>
                                    <td>{$row['ITEM_UNIT_DISPLAY']}</td>
                                    <td class='text-center align-middle'>
                                        <button type='button' class='btn btn-success btn-sm btn-save-row px-1 py-0 d-none me-1'><i class='bi bi-check'></i></button>
                                        <button type='button' class='btn btn-danger btn-sm btn-hapus px-1 py-0'><i class='bi bi-trash'></i></button>
                                    </td>
                                </tr>";
                            } else {
                                echo "<tr>
                                    <td class='text-center text-muted'>▶</td>
                                    <td>{$row['LOC_NAME_DISPLAY']}</td>
                                    <td>{$row['TAG_NO']}</td>
                                    <td width='120'>{$row['ITEM_CODE']}</td>
                                    <td>{$row['ITEM_NAME_DISPLAY']}</td>
                                    <td class='text-end'>".number_format($row['TAG_QTY'], 2)."</td>
                                    <td>{$row['ITEM_UNIT_DISPLAY']}</td>
                                </tr>";
                            }
                        }
                    } else if (!$isEntry) {
                        echo "<tr><td colspan='8' class='text-center text-muted py-3'>Tidak ada data ditemukan.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            
            <!-- NAVIGASI HALAMAN -->
            <?php if ($totalPage > 1 && $mode != 'new'): ?>
            <div class="mt-2 d-flex justify-content-end align-items-center" style="font-size: 11px;">
                <div>
                    Halaman: 
                    <?php
                    $startPage = max(1, $pageNo - 4);
                    $endPage   = min($totalPage, $pageNo + 5);
                    if ($startPage > 1) {
                        echo "<a href='?page=sop&id=$currentID&mode=$mode&q=".urlencode($searchQ)."&hal=1' class='text-primary text-decoration-none mx-1'>[1]</a> ... ";
                    }
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        $fw = ($i == $pageNo) ? 'fw-bold text-dark bg-warning px-1 border' : 'text-primary';
                        echo "<a href='?page=sop&id=$currentID&mode=$mode&q=".urlencode($searchQ)."&hal=$i' class='$fw text-decoration-none mx-1'>[$i]</a>";
                    }
                    if ($endPage < $totalPage) {
                        echo " ... <a href='?page=sop&id=$currentID&mode=$mode&q=".urlencode($searchQ)."&hal=$totalPage' class='text-primary text-decoration-none mx-1'>[$totalPage]</a>";
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ================= ELEMENT AUTOCOMPLETE MELAYANG (AC BOX) ================= -->
<div id="acBox" class="ac-box"></div>

<!-- ================= MODAL REPORT ================= -->
<?php
$optSop = ""; 
$qSop = sqlsrv_query($conn, "SELECT TOP 100 SOP_ID, SOP_REF, SOP_SDATE FROM SOP ORDER BY CONVERT(date, SOP_SDATE) DESC, SOP_ID DESC");
if ($qSop) {
    while($rSop = sqlsrv_fetch_array($qSop)) {
        $tglSop = ($rSop['SOP_SDATE'] instanceof DateTime) ? $rSop['SOP_SDATE']->format('d-M-Y') : $rSop['SOP_SDATE'];
        $optSop .= "<option value='{$rSop['SOP_ID']}'>{$rSop['SOP_REF']} ({$tglSop})</option>"; 
    }
}
?>
<div class="modal fade" id="modalTagSummary" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content" style="font-size:12px;">
      <div class="modal-header py-2 bg-light">
        <h6 class="modal-title mb-0">Tag Summary By Item</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-2">
        <label>Pilih Dokumen SOP:</label>
        <select id="ts_sop_id" class="form-select form-select-sm mb-2"><?php echo $optSop; ?></select>
        <button onclick="cetakTagSummary()" class="btn btn-dark btn-sm w-100 mb-1">Print Tag Summary</button>
        <button onclick="cetakSopConversion()" class="btn btn-primary btn-sm w-100">Print SOP Conversion</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="modalStockAnalysis" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content" style="font-size:12px;">
      <div class="modal-header py-2 bg-light">
        <h6 class="modal-title mb-0">Stock Analysis Report</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-2">
        <label>Pilih Item:</label>
        <select id="sa_item_id" class="form-select form-select-sm select2-ajax w-100 mb-2">
            <option value="0" selected>-- SEMUA ITEM (ALL) --</option>
        </select>
        <label>Start Date:</label>
        <input type="date" id="sa_start_date" class="form-control form-control-sm mb-2" value="<?php echo date('Y-m-01'); ?>">
        <label>Periode (Bulan):</label>
        <input type="number" id="sa_period" class="form-control form-control-sm mb-3" value="1" min="1">
        <button onclick="cetakStockAnalysis()" class="btn btn-dark btn-sm w-100">Print Analysis</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="modalTagType" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content" style="font-size:12px;">
      <div class="modal-header py-2 bg-light">
        <h6 class="modal-title mb-0">Report Tag By Type</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-2">
        <label>Pilih Dokumen SOP:</label>
        <select id="tag_by_type_sop_id" class="form-select form-select-sm mb-3"><?php echo $optSop; ?></select>
        <button onclick="cetakTagByType()" class="btn btn-dark btn-sm w-100">Print Tag By Type</button>
      </div>
    </div>
  </div>
</div>

<!-- ================= SCRIPTS GLOBAL (BERFUNGSI DI VIEW & EDIT) ================= -->
<script>
function cetakSopConversion() { var sop_id = $('#ts_sop_id').val(); if (!sop_id) return alert('Pilih Dokumen!'); window.open('print_sop_conversion.php?sop_id=' + sop_id, '_blank'); }
function cetakTagSummary() { var sop_id = $('#ts_sop_id').val(); if (!sop_id) return alert('Pilih Dokumen!'); window.open('print_tag_summary_item.php?sop_id=' + sop_id, '_blank'); }
function cetakStockAnalysis() { var item_id = $('#sa_item_id').val(); var start_date = $('#sa_start_date').val(); var period = $('#sa_period').val(); if (!item_id) return alert('Pilih Item!'); if (!start_date || !period) return alert('Isi Tanggal/Periode!'); window.open('print_stock_analysis.php?item_id=' + item_id + '&start_date=' + start_date + '&period=' + period, '_blank'); }
function cetakTagByType() { var sop_id = $('#tag_by_type_sop_id').val(); if (!sop_id) return alert('Pilih Dokumen!'); window.open('print_tag_by_type.php?sop_id=' + sop_id, '_blank'); }

// ==========================================
// CUSTOM AUTOCOMPLETE SCRIPT (GLOBAL)
// ==========================================
var acBox=null, acItems=[], acIndex=-1, acMode="";

function byId(id){return document.getElementById(id);}
function initAC(){ acBox=byId("acBox"); }
function hideAC(){
    if(acBox){ acBox.style.display="none"; acBox.innerHTML=""; }
    acItems=[]; acIndex=-1; acMode="";
}
function positionAC(input){
    initAC(); var r=input.getBoundingClientRect();
    acBox.style.left=(r.left+(window.scrollX||window.pageXOffset))+"px";
    acBox.style.top=(r.bottom+(window.scrollY||window.pageYOffset))+"px";
    acBox.style.width=(r.width<300?300:r.width)+"px";
}
function renderAC(renderText, pickFunc){
    initAC(); acBox.innerHTML="";
    for(var i=0; i<acItems.length; i++){
        var d = document.createElement("div");
        d.className = "ac-item" + (i==acIndex ? " active" : "");
        d.innerHTML = renderText(acItems[i]);
        d.setAttribute("data-index", i);
        d.onmousedown = function(){ pickFunc(acItems[parseInt(this.getAttribute("data-index"),10)]); };
        acBox.appendChild(d);
    }
    acBox.style.display = acItems.length>0 ? "block" : "none";
}
function acMove(step, renderText, pickFunc){
    if(acItems.length<=0) return;
    acIndex += step;
    if(acIndex<0) acIndex = acItems.length-1;
    if(acIndex>=acItems.length) acIndex = 0;
    renderAC(renderText, pickFunc);
}
function acEnter(pickFunc){
    if(acItems.length<=0) return false;
    if(acIndex<0) acIndex=0;
    pickFunc(acItems[acIndex]);
    return true;
}

$(document).on("click", function(e) {
    if(acBox && !acBox.contains(e.target) && e.target.id !== 'inputItemCode' && e.target.id !== 'topSearchInput') { hideAC(); }
});

// ----------------------------------------------------
// A. AUTOCOMPLETE UNTUK TOP SEARCH BAR
// ----------------------------------------------------

// Fungsi helper agar format teks rapi dan tidak perlu diketik berulang
function renderTopSearchFormat(o) {
    return "<b>" + o.TAG_NO + "</b> | " + o.ITEM_CODE + " - " + o.ITEM_NAME + 
           "<br><span style='font-size:10.5px; color:#555;'>Loc: <b>" + o.LOC_NAME + "</b> &nbsp;|&nbsp; Qty: <b>" + o.TAG_QTY + "</b></span>";
}

function showTopSearchAC(input) {
    initAC();
    var key = (input.value || "").trim().toUpperCase();
    var sopId = $('#hapus_id').val();
    acMode = "topsearch"; acItems = []; acIndex = -1;
    if(key.length < 1 || !sopId){ hideAC(); return; }

    $.ajax({
        url: window.location.href,
        type: 'POST',
        dataType: 'json',
        data: { ajax_top_search: 1, q: key, sop_id: sopId },
        success: function(res) {
            if(res && res.length > 0) {
                acItems = res;
                positionAC(input);
                // Panggil format tampilan baru
                renderAC(function(o){ return renderTopSearchFormat(o); }, pickTopSearch);
            } else {
                hideAC();
            }
        }
    });
}

function topSearchKey(e, input) {
    if(e.key === "ArrowDown") {
        e.preventDefault(); 
        if(acMode !== "topsearch" || acItems.length == 0) showTopSearchAC(input);
        acMove(1, function(o){ return renderTopSearchFormat(o); }, pickTopSearch); 
        return false;
    }
    if(e.key === "ArrowUp") {
        e.preventDefault(); 
        if(acMode !== "topsearch" || acItems.length == 0) showTopSearchAC(input);
        acMove(-1, function(o){ return renderTopSearchFormat(o); }, pickTopSearch); 
        return false;
    }
    if(e.key === "Enter") {
        if(acMode === "topsearch" && acItems.length > 0 && acIndex >= 0) { 
            e.preventDefault(); acEnter(pickTopSearch); return false; 
        }
        e.preventDefault();
        window.location.href = '?page=sop&id=<?php echo $currentID; ?>&mode=<?php echo $mode; ?>&q=' + encodeURIComponent(input.value);
        return false;
    }
    if(e.key === "Escape") hideAC();
}

function pickTopSearch(o) {
    hideAC();
    window.location.href = '?page=sop&id=<?php echo $currentID; ?>&mode=<?php echo $mode; ?>&q=' + encodeURIComponent(o.TAG_NO);
}
</script>

<?php if ($isEntry): ?>
<script>
// ----------------------------------------------------
// B. AUTOCOMPLETE UNTUK INPUT ITEM (Entry Row)
// ----------------------------------------------------
function showItemAC(input) {
    initAC();
    var key = (input.value || "").trim().toUpperCase();
    acMode = "item"; acItems = []; acIndex = -1;
    if(key.length < 1){ hideAC(); return; }

    $.ajax({
        url: window.location.href,
        type: 'POST',
        dataType: 'json',
        data: { ajax_search_item: 1, q: key },
        success: function(res) {
            if(res && res.length > 0) {
                acItems = res;
                positionAC(input);
                renderAC(function(o){ return "<b>" + o.ITEM_CODE + "</b> - " + o.ITEM_NAME; }, pickItem);
            } else {
                hideAC();
            }
        }
    });
}

function itemKey(e, input) {
    if(e.key === "ArrowDown") {
        e.preventDefault(); 
        if(acMode !== "item" || acItems.length == 0) showItemAC(input);
        acMove(1, function(o){ return "<b>" + o.ITEM_CODE + "</b> - " + o.ITEM_NAME; }, pickItem); 
        return false;
    }
    if(e.key === "ArrowUp") {
        e.preventDefault(); 
        if(acMode !== "item" || acItems.length == 0) showItemAC(input);
        acMove(-1, function(o){ return "<b>" + o.ITEM_CODE + "</b> - " + o.ITEM_NAME; }, pickItem); 
        return false;
    }
    if(e.key === "Enter") {
        if(acMode === "item" && acItems.length > 0) { 
            e.preventDefault(); 
            acEnter(pickItem); 
            return false; 
        }
        e.preventDefault(); 
        return false;
    }
    if(e.key === "Escape") hideAC();
}

function pickItem(o) {
    $('#inputItemId').val(o.ITEM_ID);
    $('#inputItemCode').val(o.ITEM_CODE);
    $('#inputItemName').val(o.ITEM_NAME);
    $('#inputUnit').text(o.ITEM_UNIT || '-');
    hideAC();
    $('#inputQty').focus().select();
}

// =========================================================
// FUNGSI SIMPAN AJAX (OPTIMASI)
// =========================================================
function saveSopAjax(rowEl, callback) {
    var sopRef = $('input[name="SOP_REF"]').val();
    if (!sopRef) {
        alert("Ref # tidak boleh kosong!");
        $('input[name="SOP_REF"]').focus();
        return;
    }

    var formData = new FormData();
    formData.append("is_ajax", "1");
    formData.append("action", "save");
    formData.append("hapus_id", $('#hapus_id').val());
    formData.append("SOP_REF", sopRef);
    formData.append("SOP_SDATE", $('input[name="SOP_SDATE"]').val());
    formData.append("SOP_REM", $('input[name="SOP_REM"]').val());
    if ($('input[name="SOP_FINISHED"]').is(':checked')) {
        formData.append("SOP_FINISHED", "T");
    }

    var oldBg = "";
    if (rowEl) {
        oldBg = rowEl.css('background-color');
        rowEl.css('background-color', '#fff9c4'); 
        formData.append("loc_id[]", rowEl.find('input[name="loc_id[]"]').val());
        formData.append("tag_no[]", rowEl.find('input[name="tag_no[]"]').val());
        formData.append("old_tag_no[]", rowEl.attr('data-old-tag') || rowEl.find('input[name="tag_no[]"]').val());
        formData.append("item_code[]", rowEl.find('input[name="item_code[]"]').val());
        formData.append("tag_qty[]", rowEl.find('input[name="tag_qty[]"]').val());
    }

    $.ajax({
        url: window.location.href,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            if (res.status === 'success') {
                if (rowEl) {
                    var currentTag = rowEl.find('input[name="tag_no[]"]').val();
                    rowEl.attr('data-old-tag', currentTag);
                    rowEl.css('background-color', '#c8e6c9'); 
                    setTimeout(function(){ rowEl.css('background-color', oldBg); }, 800);
                }
                
                var currId = $('#hapus_id').val();
                if (!currId || currId == "0") {
                    $('#hapus_id').val(res.sop_id);
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState(null, null, "?page=sop&mode=edit&id=" + res.sop_id);
                    }
                }
                if (callback) callback();
            } else {
                alert("Gagal Simpan:\n" + res.message);
                if (rowEl) rowEl.css('background-color', '#ffcdd2');
            }
        },
        error: function() {
            alert("Kesalahan Jaringan saat menyimpan data.");
            if (rowEl) rowEl.css('background-color', '#ffcdd2');
        }
    });
}

function saveHeaderOnly() {
    if (!confirm('Simpan perubahan Header SOP?')) return;
    saveSopAjax(null, function() {
        alert('Data Header SOP berhasil disimpan!');
        window.location.href = '?page=sop&id=' + $('#hapus_id').val();
    });
}

// ==========================================
// EVENT HANDLER ENTRY DATA
// ==========================================
$(document).ready(function() {
    
    $('#inputTagNo').on('keydown', function(e){
        if(e.key === "Enter" || e.keyCode === 13) {
            e.preventDefault();
            if ($(this).val().trim() !== '') {
                $('#inputItemCode').focus().select();
            }
        }
    });

    $('#inputQty').on('keydown', function(e) {
        if (e.key === "Enter" || e.keyCode === 13) {
            e.preventDefault();
            
            var locId   = $('#inputLoc').val();
            var locName = $('#inputLoc option:selected').text().split(' - ')[1] || $('#inputLoc option:selected').text();
            var tagNo   = $('#inputTagNo').val().trim();
            var code    = $('#inputItemCode').val().trim();
            var name    = $('#inputItemName').val();
            var qty     = $(this).val();
            var unit    = $('#inputUnit').text();

            if(!code || !tagNo) { 
                alert('Mohon isi Tag No dan Pilih Item!'); 
                if(!tagNo) $('#inputTagNo').focus(); else $('#inputItemCode').focus();
                return; 
            }

            if ($('#tabelSop tbody tr td[colspan="8"]').length > 0) {
                $('#tabelSop tbody').find('tr').not('#entryRow').remove();
            }

            var html = `<tr class='row-item' data-sop-id='${$('#hapus_id').val()}' data-old-tag='${tagNo}'>
                <td class='text-center text-muted'>▶</td>
                <td><input type='hidden' name='loc_id[]' value='${locId}'>${locName}</td>
                <td class='cell-edit'><input type='hidden' name='tag_no[]' class='val-real' value='${tagNo}'><span class='val-txt'>${tagNo}</span><input type='text' class='c-input val-input d-none' value='${tagNo}'></td>
                <td width='120'><input type='hidden' name='item_code[]' value='${code}'>${code}</td>
                <td>${name}</td>
                <td class='text-end cell-edit'><input type='hidden' name='tag_qty[]' class='val-real' value='${qty}'><span class='val-txt'>${parseFloat(qty).toFixed(2)}</span><input type='number' class='c-input text-end val-input d-none' value='${qty}' step='0.01'></td>
                <td>${unit}</td>
                <td class='text-center align-middle'>
                    <button type='button' class='btn btn-success btn-sm btn-save-row px-1 py-0 d-none me-1'><i class='bi bi-check'></i></button>
                    <button type='button' class='btn btn-danger btn-sm btn-hapus px-1 py-0'><i class='bi bi-trash'></i></button>
                </td>
            </tr>`;
            
            $('#entryRow').after(html);
            var $newRow = $('#entryRow').next('tr.row-item');

            $('#inputTagNo').val(''); 
            $('#inputItemCode').val('');
            $('#inputItemName').val('');
            $('#inputItemId').val('');
            $('#inputQty').val(0);
            $('#inputUnit').text('-');

            saveSopAjax($newRow, function() {
                $('#inputTagNo').focus(); 
            });
        }
    });

    // ==========================================
    // INLINE EDIT & HAPUS
    // ==========================================
    $(document).on('click', '.btn-hapus', function() { 
        var $row = $(this).closest('tr');
        var sopId = $('#hapus_id').val();
        var tagNo = $row.find('input[name="tag_no[]"]').val();

        if (confirm('Yakin ingin menghapus Tag ' + tagNo + ' secara permanen?')) {
            if (sopId && sopId != "0" && tagNo) {
                $.ajax({
                    url: window.location.href, 
                    type: 'POST',
                    dataType: 'text',
                    data: { ajax_delete_tag: 1, sop_id: sopId, tag_no: tagNo },
                    success: function(res) {
                        if (res.includes('"success":true') || res.includes('"success": true')) {
                            $row.remove();
                        } else {
                            alert('Gagal menghapus Tag dari database.');
                        }
                    },
                    error: function() { alert('Kesalahan Jaringan.'); }
                });
            } else {
                $row.remove(); 
            }
        }
    });

    $(document).on('dblclick', '.cell-edit', function() {
        var $td = $(this);
        $td.find('.val-txt').addClass('d-none'); 
        $td.find('.val-input').removeClass('d-none').focus().select();
        $td.closest('tr').find('.btn-save-row').removeClass('d-none');
    });

    $(document).on('click', '.btn-save-row', function() {
        var $row = $(this).closest('tr');
        $row.find('.cell-edit').each(function() {
            var $td = $(this); 
            var newVal = $td.find('.val-input').val();
            $td.find('.val-real').val(newVal);
            if($td.hasClass('text-end')) newVal = parseFloat(newVal).toFixed(2);
            $td.find('.val-txt').text(newVal).removeClass('d-none');
            $td.find('.val-input').addClass('d-none');
        });
        $(this).addClass('d-none');
        saveSopAjax($row);
    });

    $(document).on('keypress', '.val-input', function(e) { 
        if(e.which == 13 || e.keyCode == 13) { 
            e.preventDefault(); 
            $(this).closest('tr').find('.btn-save-row').click(); 
        } 
    });
});
</script>
<?php endif; ?>
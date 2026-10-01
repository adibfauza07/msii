<?php
if (session_id() == "") session_start();

require_once __DIR__ . '/config/database.php';
if ($conn === false) die("Koneksi database gagal.");

// CEK AKTIF PLANT (Untuk Transaksi)
$isPlant1 = true;
if (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2') {
    $isPlant1 = false;
}
$TABEL_BC = "BC_TRANS"; 

// ==================================================================================
// COMMON FUNCTIONS & ROUTING
// ==================================================================================
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
function getv($n, $d="") { return isset($_GET[$n]) ? trim((string)$_GET[$n]) : $d; }
function postv($n, $d="") { return isset($_POST[$n]) ? trim((string)$_POST[$n]) : $d; }

$pageName = isset($_GET['page']) ? $_GET['page'] : 'trans';
$urlBase  = "?page=" . urlencode($pageName) . "&";

// Inisialisasi Variabel Global untuk Menghindari Notice Undefined Variable
$tab = getv('tab', 'sop');
$mode = getv('mode', 'view');
$currentID = getv('id', null);

// FUNGSI AUTO NUMBER TRANSAKSI
function generate_trans_no($conn) {
    $prefix = date('ym'); 
    $searchPrefix = $prefix . '%';
    
    $sql = "SELECT TOP (1) TRAN_DOC FROM TRANS WHERE TRAN_DOC LIKE ? ORDER BY TRAN_ID DESC";
    $stmt = @sqlsrv_query($conn, $sql, array($searchPrefix));
    
    $nextVal = 1;
    if ($stmt !== false) {
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($r && isset($r["TRAN_DOC"])) {
            $lastDoc = trim($r["TRAN_DOC"]);
            if (strlen($lastDoc) >= 7) {
                $numPart = substr($lastDoc, 4, 5);
                if (is_numeric($numPart)) {
                    $nextVal = intval($numPart) + 1;
                }
            }
        }
    }
    return $prefix . str_pad($nextVal, 5, "0", STR_PAD_LEFT) . "/";
}

// ==================================================================================
// AJAX: PENCARIAN DOKUMEN TRANSAKSI (AUTOCOMPLETE)
// ==================================================================================
$ajaxMode = getv("ajax", "");
if ($ajaxMode == "trans_search") {
    // KUNCI PERBAIKAN: Bersihkan semua buffer output (HTML dari index.php) sebelum memuntahkan JSON
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header("Content-Type: application/json; charset=utf-8");
    $qAjax = getv("q", "");
    $likeAjax = "%" . strtoupper($qAjax) . "%";
    
    $sql = "SELECT TOP 10 TRAN_ID, TRAN_DOC, TRAN_DATE FROM TRANS WHERE UPPER(TRAN_DOC) LIKE ? ORDER BY TRAN_DATE DESC, TRAN_DOC DESC";
    $stmtSearch = sqlsrv_query($conn, $sql, array($likeAjax));
    
    $out = array();
    if ($stmtSearch !== false) {
        while ($r = sqlsrv_fetch_array($stmtSearch, SQLSRV_FETCH_ASSOC)) {
            $tgl = ($r['TRAN_DATE'] instanceof DateTime) ? $r['TRAN_DATE']->format('d-M-Y') : $r['TRAN_DATE'];
            $out[] = array(
                "TRAN_ID" => intval($r["TRAN_ID"]),
                "TRAN_DOC" => trim((string)$r["TRAN_DOC"]),
                "TRAN_DATE" => $tgl
            );
        }
    }
    echo json_encode(array("success" => true, "rows" => $out));
    exit; // Hentikan eksekusi script agar tabel HTML di bawah tidak ikut ter-print
}

// ==================================================================================
// AJAX: HAPUS TAG SATUAN (SOP)
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
// PROSES DATA (POST) - TAB SOP
// ==================================================================================
if (isset($_POST['btnHapusSOP'])) {
    $idToDelete = $_POST['hapus_id'];
    if ($idToDelete) {
        sqlsrv_begin_transaction($conn);
        try {
            sqlsrv_query($conn, "DELETE FROM TAGS WHERE SOP_ID = ?", array($idToDelete));
            $stmtDel = sqlsrv_query($conn, "DELETE FROM SOP WHERE SOP_ID = ?", array($idToDelete));
            if ($stmtDel) {
                sqlsrv_commit($conn);
                echo "<script>window.location.href='{$urlBase}tab=sop&msg=deleted';</script>"; exit;
            } else throw new Exception(print_r(sqlsrv_errors(), true));
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            $errorSop = "Gagal Hapus: ".$e->getMessage();
        }
    }
}

if (isset($_POST['btnSimpanSOP']) || isset($_POST['btnUpdateSOP'])) {
    $isUpdate = isset($_POST['btnUpdateSOP']);
    $curID = $_POST['hapus_id'];

    $sopRef     = $_POST['SOP_REF'];
    $sopDate    = $_POST['SOP_SDATE'];
    $sopRem     = $_POST['SOP_REM'];
    $sopFinished= isset($_POST['SOP_FINISHED']) ? 'T' : 'F';
    $sopBy      = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : 'SYSTEM';

    $tagNos     = isset($_POST['tag_no']) ? $_POST['tag_no'] : [];
    $locIds     = isset($_POST['loc_id']) ? $_POST['loc_id'] : [];
    $itemCodes  = isset($_POST['item_code']) ? $_POST['item_code'] : [];
    $tagQtys    = isset($_POST['tag_qty']) ? $_POST['tag_qty'] : [];

    if (empty($sopRef)) {
        $errorSop = "Ref # tidak boleh kosong!";
    } else {
        sqlsrv_begin_transaction($conn);
        try {
            $targetID = 0;
            if ($isUpdate) {
                $sqlHead = "UPDATE SOP SET SOP_REF=?, SOP_SDATE=?, SOP_REM=?, SOP_FINISHED=?, SOP_BY=? WHERE SOP_ID=?";
                $paramsHead = array($sopRef, $sopDate, $sopRem, $sopFinished, $sopBy, $curID);
                if (!sqlsrv_query($conn, $sqlHead, $paramsHead)) throw new Exception("Gagal Update Header SOP");
                $targetID = $curID;
            } else {
                $sqlHead = "INSERT INTO SOP (SOP_REF, SOP_SDATE, SOP_REM, SOP_FINISHED, SOP_BY) OUTPUT INSERTED.SOP_ID VALUES (?, ?, ?, ?, ?)";
                $paramsHead = array($sopRef, $sopDate, $sopRem, $sopFinished, $sopBy);
                $stmtHead = sqlsrv_query($conn, $sqlHead, $paramsHead);
                if ($stmtHead === false) throw new Exception("Gagal Simpan Header");
                $rowID = sqlsrv_fetch_array($stmtHead);
                $targetID = $rowID['SOP_ID'];
            }

            foreach ($tagNos as $index => $tagNo) {
                $locId = $locIds[$index];
                $code  = $itemCodes[$index];
                $qty   = $tagQtys[$index];

                $qItem = sqlsrv_query($conn, "SELECT TOP 1 ITEM_ID, ITEM_NAME, ITEM_UNIT FROM ITEMS WHERE ITEM_CODE = ?", array($code));
                $rItem = sqlsrv_fetch_array($qItem);
                $itemId   = $rItem ? $rItem['ITEM_ID'] : 0;
                $locCode = "-";
                $qLoc = sqlsrv_query($conn, "SELECT TOP 1 LOC_CODE FROM LOC WHERE LOC_ID = ?", array($locId));
                if($rLoc = sqlsrv_fetch_array($qLoc)) $locCode = $rLoc['LOC_CODE'];

                if ($itemId === 0) throw new Exception("Item Code {$code} tidak ada di Master Database.");
                $safeTagNo = substr($tagNo, 0, 7);

                $qCek = sqlsrv_query($conn, "SELECT TAG_NO FROM TAGS WHERE SOP_ID=? AND TAG_NO=?", array($targetID, $safeTagNo));
                if (sqlsrv_has_rows($qCek)) {
                    $sqlUpd = "UPDATE TAGS SET LOC_ID=?, ITEM_ID=?, ITEM_CODE=?, TAG_QTY=?, TAG_BY=?, LOC_CODE=? WHERE SOP_ID=? AND TAG_NO=?";
                    $paramsUpd = array($locId, $itemId, substr($code, 0, 8), $qty, substr($sopBy, 0, 15), substr($locCode, 0, 5), $targetID, $safeTagNo);
                    $stmtDet = sqlsrv_query($conn, $sqlUpd, $paramsUpd);
                } else {
                    $sqlIns = "INSERT INTO TAGS (SOP_ID, TAG_NO, LOC_ID, ITEM_ID, ITEM_CODE, TAG_QTY, TAG_BY, LOC_CODE) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $paramsIns = array($targetID, $safeTagNo, $locId, $itemId, substr($code, 0, 8), $qty, substr($sopBy, 0, 15), substr($locCode, 0, 5));
                    $stmtDet = sqlsrv_query($conn, $sqlIns, $paramsIns);
                }
                if ($stmtDet === false) { $err = sqlsrv_errors(); throw new Exception("Gagal Menyimpan Tag: $safeTagNo \n" . $err[0]['message']); }
            }
            sqlsrv_commit($conn);
            echo "<script>window.location.href='{$urlBase}tab=sop&id=$targetID&msg=saved';</script>"; exit;
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            $errorSop = "ERROR DATABASE: " . $e->getMessage();
        }
    }
}

// ==================================================================================
// PROSES DATA (POST) - TAB TRANSACTION
// ==================================================================================
if (isset($_POST['btnHapusTransaksi'])) {
    $idToDelete = $_POST['hapus_id'];
    if ($idToDelete) {
        sqlsrv_begin_transaction($conn);
        try {
            sqlsrv_query($conn, "DELETE FROM INV_TRAN WHERE TRAN_ID = ?", array($idToDelete));
            if ($isPlant1) sqlsrv_query($conn, "DELETE FROM $TABEL_BC WHERE NO_TRANS = (SELECT TRAN_DOC FROM TRANS WHERE TRAN_ID = ?)", array($idToDelete));
            $stmtDel = sqlsrv_query($conn, "DELETE FROM TRANS WHERE TRAN_ID = ?", array($idToDelete));
            if ($stmtDel) {
                sqlsrv_commit($conn);
                echo "<script>window.location.href='{$urlBase}tab=transaction&msg=deleted';</script>"; exit;
            } else throw new Exception(print_r(sqlsrv_errors(), true));
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            $errorTrans = "Gagal Hapus: " . $e->getMessage();
        }
    }
}

if (isset($_POST['btnSimpanTransaksi']) || isset($_POST['btnUpdateTransaksi'])) {
    $isUpdate = isset($_POST['btnUpdateTransaksi']);
    $curID = $_POST['hapus_id'];

    $tranDoc   = substr(trim($_POST['TRAN_DOC']), 0, 30);
    $tranDate  = $_POST['TRAN_DATE'];   
    $trtyCode  = $_POST['TRTY_CODE'];
    $supCode   = $_POST['SUP_CODE'];
    $remark    = substr(trim($_POST['TRAN_REM']), 0, 50);
    $BCdate    = (isset($_POST['BC_DATE']) && trim($_POST['BC_DATE']) !== '') ? $_POST['BC_DATE'] : null;
    $tranADate = $BCdate ? $BCdate : date('Y-m-d');
    if ($supCode === "") $supCode = null;

    $items    = isset($_POST['item_code']) ? $_POST['item_code'] : [];
    $qtys     = isset($_POST['item_qty']) ? $_POST['item_qty'] : [];

    if (count($items) == 0) {
        $errorTrans = "Gagal: Belum ada barang!";
    } else {
        sqlsrv_begin_transaction($conn);
        try {
            if (!$isUpdate && $trtyCode != '14') {
                $cekDoc = sqlsrv_query($conn, "SELECT TOP 1 TRAN_DOC FROM TRANS WHERE TRAN_DOC = ?", array($tranDoc));
                if ($cekDoc && sqlsrv_fetch_array($cekDoc)) throw new Exception("No. Dokumen '{$tranDoc}' sudah dipakai! Silakan ganti No. Dokumen.");
            }

            if ($isPlant1) {
                $jenisBC   = isset($_POST['JENIS_BC']) ? $_POST['JENIS_BC'] : '';
                $nomorBC   = isset($_POST['NOMOR_BC']) ? substr(trim($_POST['NOMOR_BC']), 0, 50) : '';

                if ($isUpdate) {
                    $sqlHead = "UPDATE TRANS SET TRAN_DATE=?, TRTY_CODE=?, SUP_CODE=?, TRAN_REM=?, TRAN_ADATE=? WHERE TRAN_ID=?";
                    $stmtHead = sqlsrv_query($conn, $sqlHead, array($tranDate, $trtyCode, $supCode, $remark, $tranADate, $curID));
                    if ($stmtHead === false) throw new Exception("Gagal Update Header (P1)");
                    sqlsrv_query($conn, "DELETE FROM $TABEL_BC WHERE NO_TRANS=?", array($tranDoc));
                    $stmtBC = sqlsrv_query($conn, "INSERT INTO $TABEL_BC (NO_TRANS, JENIS_BC, NOMOR_BC, BC_DATE) VALUES (?, ?, ?, ?)", array($tranDoc, $jenisBC, $nomorBC, $BCdate));
                    if ($stmtBC === false) throw new Exception("Gagal Update Tabel BC");
                    $targetID = $curID;
                } else {
                    $sqlHead = "INSERT INTO TRANS (TRAN_DOC, TRAN_DATE, TRTY_CODE, SUP_CODE, TRAN_REM, TRAN_ADATE) VALUES (?, ?, ?, ?, ?, ?); SELECT SCOPE_IDENTITY() AS ID";
                    $stmtHead = sqlsrv_query($conn, $sqlHead, array($tranDoc, $tranDate, $trtyCode, $supCode, $remark, $tranADate));
                    if ($stmtHead === false) throw new Exception("Gagal Insert Header (P1)");
                    sqlsrv_next_result($stmtHead); $rowID = sqlsrv_fetch_array($stmtHead); $targetID = $rowID['ID'];
                    $stmtBC = sqlsrv_query($conn, "INSERT INTO $TABEL_BC (NO_TRANS, JENIS_BC, NOMOR_BC, BC_DATE) VALUES (?, ?, ?, ?)", array($tranDoc, $jenisBC, $nomorBC, $BCdate));
                    if ($stmtBC === false) throw new Exception("Gagal Insert Tabel BC");
                }
            } else {
                if ($isUpdate) {
                    $sqlHead = "UPDATE TRANS SET TRAN_DATE=?, TRTY_CODE=?, SUP_CODE=?, TRAN_REM=?, TRAN_ADATE=? WHERE TRAN_ID=?";
                    $stmtHead = sqlsrv_query($conn, $sqlHead, array($tranDate, $trtyCode, $supCode, $remark, $tranADate, $curID));
                    if ($stmtHead === false) throw new Exception("Gagal Update Header (P2)");
                    $targetID = $curID;
                } else {
                    $sqlHead = "INSERT INTO TRANS (TRAN_DOC, TRAN_DATE, TRTY_CODE, SUP_CODE, TRAN_REM, TRAN_ADATE) VALUES (?, ?, ?, ?, ?, ?); SELECT SCOPE_IDENTITY() AS ID";
                    $stmtHead = sqlsrv_query($conn, $sqlHead, array($tranDoc, $tranDate, $trtyCode, $supCode, $remark, $tranADate));
                    if ($stmtHead === false) throw new Exception("Gagal Insert Header (P2)");
                    sqlsrv_next_result($stmtHead); $rowID = sqlsrv_fetch_array($stmtHead); $targetID = $rowID['ID'];
                }
            }

            if ($isUpdate) {
                $stmtDelDet = sqlsrv_query($conn, "DELETE FROM INV_TRAN WHERE TRAN_ID=?", array($targetID));
                if ($stmtDelDet === false) throw new Exception("Gagal Reset Detail INV_TRAN");
            }

            $sqlDet = "INSERT INTO INV_TRAN (TRAN_ID, ITEM_ID, ITEM_CODE, IT_QTY, IT_LINENO, TRAN_REMARK, ST_CODE) VALUES (?, ?, ?, ?, ?, ?, 'OK')";
            $lineNo = 1;
            foreach ($items as $index => $code) {
                $qCek = sqlsrv_query($conn, "SELECT TOP 1 ITEM_ID FROM ITEMS WHERE ITEM_CODE = ?", array($code));
                $rCek = sqlsrv_fetch_array($qCek);
                $itemID = $rCek ? $rCek['ITEM_ID'] : 0;
                $stmtDet = sqlsrv_query($conn, $sqlDet, array($targetID, $itemID, $code, $qtys[$index], $lineNo, $remark));
                if ($stmtDet === false) throw new Exception("Gagal Insert Detail baris $lineNo");
                $lineNo++;
            }
            
            sqlsrv_commit($conn);
            echo "<script>window.location.href='{$urlBase}tab=transaction&id=$targetID&msg=saved';</script>"; exit;
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            $errorTrans = "TERJADI ERROR DATABASE: " . $e->getMessage();
        }
    }
}

// Global Messages
if (getv("msg") == "saved") { $messageSop = "Data berhasil disimpan."; $messageTrans = "Data berhasil disimpan."; }
if (getv("msg") == "deleted") { $messageSop = "Data berhasil dihapus."; $messageTrans = "Data berhasil dihapus."; }

// ==================================================================================
// LOAD DATA GLOBAL (Dropdowns)
// ==================================================================================
$optLoc = "";
$qL = sqlsrv_query($conn, "SELECT LOC_ID, LOC_CODE, LOC_NAME FROM LOC WHERE LOC_VISIBLE=1 ORDER BY LOC_CODE ASC");
if($qL) while($r=sqlsrv_fetch_array($qL)) $optLoc .= "<option value='{$r['LOC_ID']}'>{$r['LOC_CODE']} - {$r['LOC_NAME']}</option>";

$arrSup = [];
$qS = sqlsrv_query($conn, "SELECT SUP_ID, SUP_CODE, SUP_COMP FROM SUPPLIER WHERE ISNULL(SUP_CODE,'') <> '' ORDER BY SUP_CODE ASC");
if($qS) {
    while($r = sqlsrv_fetch_array($qS, SQLSRV_FETCH_ASSOC)) {
        $arrSup[] = array(
            "SUP_ID" => intval($r["SUP_ID"]),
            "SUP_CODE" => trim((string)$r["SUP_CODE"]),
            "SUP_COMP" => trim((string)$r["SUP_COMP"])
        );
    }
}

$arrTrty = [];
$qT = sqlsrv_query($conn, "SELECT TRTY_CODE, TRTY_DESC FROM TRTY ORDER BY TRTY_CODE ASC");
if($qT) while($r=sqlsrv_fetch_array($qT)) $arrTrty[] = $r;


// ==================================================================================
// LOAD DATA TAB 1: SOP
// ==================================================================================
$dataSopHeader = ['SOP_ID'=>'', 'SOP_REF'=>'', 'SOP_SDATE'=>date('Y-m-d'), 'SOP_REM'=>'', 'SOP_FINISHED'=>'F', 'SOP_BY'=>''];
$dataSopDetail = [];
$sopPageNo = isset($_GET['hal']) ? (int)$_GET['hal'] : 1;
$sopLimit = 50; $sopStartRow = ($sopPageNo - 1) * $sopLimit + 1; $sopEndRow = $sopPageNo * $sopLimit;
$sopTotalRow = 0; $sopTotalPage = 1;
if ($mode == 'edit') { $sopStartRow = 1; $sopEndRow = 99999999; }

$sopCurrentID = ($tab == 'sop') ? $currentID : null;

if ($tab == 'sop' && $mode != 'new') {
    if (!$sopCurrentID) {
        $qLast = sqlsrv_query($conn, "SELECT TOP 1 SOP_ID FROM SOP ORDER BY SOP_ID DESC");
        if ($qLast && $rLast = sqlsrv_fetch_array($qLast)) $sopCurrentID = $rLast['SOP_ID'];
    }
    if ($sopCurrentID) {
        $qHead = sqlsrv_query($conn, "SELECT * FROM SOP WHERE SOP_ID = ?", array($sopCurrentID));
        if ($qHead && $rHead = sqlsrv_fetch_array($qHead, SQLSRV_FETCH_ASSOC)) {
            $dataSopHeader = $rHead;
            if ($dataSopHeader['SOP_SDATE'] instanceof DateTime) $dataSopHeader['SOP_SDATE'] = $dataSopHeader['SOP_SDATE']->format('Y-m-d');
        }
        $qCount = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM TAGS WHERE SOP_ID = ?", array($sopCurrentID));
        if ($qCount && $rCount = sqlsrv_fetch_array($qCount)) {
            $sopTotalRow = $rCount['total']; $sopTotalPage = ceil($sopTotalRow / $sopLimit);
        }
        $sqlTags = "SELECT * FROM (SELECT ROW_NUMBER() OVER (ORDER BY T.TAG_NO ASC) AS RowNum, T.TAG_NO, T.TAG_QTY, T.LOC_ID, ISNULL(L.LOC_NAME, T.LOC_CODE) as LOC_NAME_DISPLAY, ISNULL(I.ITEM_CODE, T.ITEM_CODE) as ITEM_CODE, I.ITEM_NAME as ITEM_NAME_DISPLAY, I.ITEM_UNIT as ITEM_UNIT_DISPLAY FROM TAGS T LEFT JOIN LOC L ON T.LOC_ID = L.LOC_ID LEFT JOIN ITEMS I ON (T.ITEM_ID = I.ITEM_ID OR T.ITEM_CODE = I.ITEM_CODE) WHERE T.SOP_ID = ?) AS RowData WHERE RowNum >= ? AND RowNum <= ?";
        $qDet = sqlsrv_query($conn, $sqlTags, array($sopCurrentID, $sopStartRow, $sopEndRow));
        if ($qDet) while ($rDet = sqlsrv_fetch_array($qDet, SQLSRV_FETCH_ASSOC)) $dataSopDetail[] = $rDet;
    }
}
$isSopLocked = ($dataSopHeader['SOP_FINISHED'] == 'T');
$isSopEntry = ($mode == 'new' || ($mode == 'edit' && !$isSopLocked));

$sopPrevID = $sopNextID = $sopFirstID = $sopLastID = null;
if ($tab == 'sop' && $mode == 'view' && $sopCurrentID) {
    $qP = sqlsrv_query($conn, "SELECT TOP 1 SOP_ID FROM SOP WHERE SOP_ID < ? ORDER BY SOP_ID DESC", array($sopCurrentID)); if($qP && $r=sqlsrv_fetch_array($qP)) $sopPrevID=$r['SOP_ID'];
    $qN = sqlsrv_query($conn, "SELECT TOP 1 SOP_ID FROM SOP WHERE SOP_ID > ? ORDER BY SOP_ID ASC", array($sopCurrentID)); if($qN && $r=sqlsrv_fetch_array($qN)) $sopNextID=$r['SOP_ID'];
    $qF = sqlsrv_query($conn, "SELECT TOP 1 SOP_ID FROM SOP ORDER BY SOP_ID ASC"); if($qF && $r=sqlsrv_fetch_array($qF)) $sopFirstID=$r['SOP_ID'];
    $qL = sqlsrv_query($conn, "SELECT TOP 1 SOP_ID FROM SOP ORDER BY SOP_ID DESC"); if($qL && $r=sqlsrv_fetch_array($qL)) $sopLastID=$r['SOP_ID'];
}

// ==================================================================================
// LOAD DATA TAB 2: TRANSACTION
// ==================================================================================
$dataTransHeader = ['TRAN_ID' => '', 'TRAN_DOC' => '', 'TRAN_DATE' => date('Y-m-d'), 'TRAN_ADATE' => date('Y-m-d'), 'TRTY_CODE' => '', 'SUP_CODE' => '', 'TRAN_REM' => '', 'JENIS_BC' => '', 'NOMOR_BC' => '', 'BC_DATE' => ''];
$dataTransDetail = [];
$transCurrentID = ($tab == 'transaction') ? $currentID : null;

if ($tab == 'transaction') {
    if ($mode == 'new') {
        $dataTransHeader['TRAN_DOC'] = generate_trans_no($conn); // Auto Number Dinamis & Reset Per Bulan
    } else {
        if (!$transCurrentID) {
            $qLast = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS ORDER BY TRAN_ID DESC");
            if ($qLast && $rLast = sqlsrv_fetch_array($qLast)) $transCurrentID = $rLast['TRAN_ID'];
        }
        if ($transCurrentID) {
            if ($isPlant1) $sqlHead = "SELECT T.*, B.JENIS_BC, B.NOMOR_BC, B.BC_DATE FROM TRANS T LEFT JOIN $TABEL_BC B ON T.TRAN_DOC = B.NO_TRANS WHERE T.TRAN_ID = ?";
            else $sqlHead = "SELECT * FROM TRANS WHERE TRAN_ID = ?";
            $qHead = sqlsrv_query($conn, $sqlHead, array($transCurrentID));
            if ($qHead === false) $qHead = sqlsrv_query($conn, "SELECT * FROM TRANS WHERE TRAN_ID = ?", array($transCurrentID));
            if ($qHead !== false && $rHead = sqlsrv_fetch_array($qHead, SQLSRV_FETCH_ASSOC)) {
                $dataTransHeader = $rHead;
                if (!isset($dataTransHeader['JENIS_BC'])) $dataTransHeader['JENIS_BC'] = '';
                if (!isset($dataTransHeader['NOMOR_BC'])) $dataTransHeader['NOMOR_BC'] = '';
                if (isset($dataTransHeader['TRAN_DATE']) && $dataTransHeader['TRAN_DATE'] instanceof DateTime) $dataTransHeader['TRAN_DATE'] = $dataTransHeader['TRAN_DATE']->format('Y-m-d');
                if (isset($dataTransHeader['TRAN_ADATE']) && $dataTransHeader['TRAN_ADATE'] instanceof DateTime) $dataTransHeader['TRAN_ADATE'] = $dataTransHeader['TRAN_ADATE']->format('Y-m-d');
                else if (empty($dataTransHeader['TRAN_ADATE'])) $dataTransHeader['TRAN_ADATE'] = date('Y-m-d');
                if (isset($dataTransHeader['BC_DATE']) && $dataTransHeader['BC_DATE'] instanceof DateTime) {
                    if ($dataTransHeader['BC_DATE']->format('Y') == '1900') $dataTransHeader['BC_DATE'] = '';
                    else $dataTransHeader['BC_DATE'] = $dataTransHeader['BC_DATE']->format('Y-m-d');
                } else $dataTransHeader['BC_DATE'] = '';
            }
            $sqlDetail = "SELECT T.IT_LINENO, T.IT_QTY, COALESCE(NULLIF(I.ITEM_CODE, ''), NULLIF(T.ITEM_CODE, ''), '???') as ITEM_CODE, COALESCE(NULLIF(I.ITEM_NAME, ''), NULLIF(T.TRAN_REMARK, ''), '(Barang Tidak Dikenal)') as ITEM_NAME, COALESCE(NULLIF(I.ITEM_UNIT, ''), '-') as ITEM_UNIT FROM INV_TRAN T LEFT JOIN ITEMS I ON (T.ITEM_ID = I.ITEM_ID OR (T.ITEM_CODE IS NOT NULL AND LTRIM(RTRIM(T.ITEM_CODE)) = LTRIM(RTRIM(I.ITEM_CODE)))) WHERE T.TRAN_ID = ? ORDER BY T.IT_LINENO ASC";
            $qDet = sqlsrv_query($conn, $sqlDetail, array($transCurrentID));
            if ($qDet) while ($rDet = sqlsrv_fetch_array($qDet, SQLSRV_FETCH_ASSOC)) $dataTransDetail[] = $rDet;
        }
    }
}
$isTransEntry = ($tab == 'transaction' && ($mode == 'new' || $mode == 'edit'));

$transPrevID = $transNextID = $transFirstID = $transLastID = null;
if ($tab == 'transaction' && !$isTransEntry && $transCurrentID) {
    $qP = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS WHERE TRAN_ID < ? ORDER BY TRAN_ID DESC", array($transCurrentID)); if($qP && $r=sqlsrv_fetch_array($qP)) $transPrevID = $r['TRAN_ID'];
    $qN = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS WHERE TRAN_ID > ? ORDER BY TRAN_ID ASC", array($transCurrentID)); if($qN && $r=sqlsrv_fetch_array($qN)) $transNextID = $r['TRAN_ID'];
    $qF = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS ORDER BY TRAN_ID ASC"); if($qF && $r=sqlsrv_fetch_array($qF)) $transFirstID = $r['TRAN_ID'];
    $qL = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS ORDER BY TRAN_ID DESC"); if($qL && $r=sqlsrv_fetch_array($qL)) $transLastID = $r['TRAN_ID'];
}

$optTrty = ""; foreach($arrTrty as $r) { $s = ($dataTransHeader['TRTY_CODE'] == $r['TRTY_CODE']) ? 'selected' : ''; $optTrty .= "<option value='{$r['TRTY_CODE']}' $s>{$r['TRTY_CODE']} - {$r['TRTY_DESC']}</option>"; }

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOP & Transaction Module</title>

    <style>
        .box { background: #ffffff; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; border-top: 3px solid #d2d6de; }
        .box.box-primary { border-top-color: #3c8dbc; }
        .box.box-success { border-top-color: #00a65a; }
        .box.box-warning { border-top-color: #f39c12; }
        .box.box-solid.bg-gray-light { background-color: #f8f9fa !important; border: 1px solid #e1e5eb; border-top: none; }
        .box-header { padding: 10px 15px; border-bottom: 1px solid #f4f4f4; display: flex; justify-content: space-between; align-items: center; }
        .box-title { font-size: 16px; margin: 0; font-weight: bold; display: inline-block; }
        .box-body { padding: 15px; }
        
        .form-control { width: 100%; border-radius: 4px; border: 1px solid #ccc; height: 30px; padding: 4px 10px; font-size: 13px; box-shadow: none; transition: border-color 0.15s ease-in-out; }
        .form-control:focus { border-color: #3c8dbc; outline: 0; }
        .form-control[readonly], .form-control[disabled] { background-color: #eeeeee; cursor: not-allowed; }
        
        .btn { border-radius: 4px; font-size: 12px; font-weight: bold; padding: 5px 12px; cursor: pointer; border: 1px solid transparent; transition: background-color 0.2s; display: inline-block; text-decoration: none; text-align: center; }
        .btn-success { background-color: #00a65a; color: #fff; border-color: #008d4c; }
        .btn-success:hover { background-color: #008d4c; }
        .btn-primary { background-color: #3c8dbc; color: #fff; border-color: #367fa9; }
        .btn-primary:hover { background-color: #286090; }
        .btn-warning { background-color: #f39c12; color: #fff; border-color: #e08e0b; }
        .btn-danger { background-color: #dd4b39; color: #fff; border-color: #d73925; }
        .btn-default { background-color: #f4f4f4; color: #444; border-color: #ddd; }
        .btn-default:hover { background-color: #e7e7e7; }
        .btn-block { display: block; width: 100%; margin-bottom: 5px;}
        .btn.disabled { opacity: 0.65; cursor: not-allowed; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { font-weight: 600; margin-bottom: 5px; display: inline-block; font-size: 12px; color: #333; }
        
        .nav-tabs-custom { margin-bottom: 20px; background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,0.1); border-radius: 3px; }
        .nav-tabs { border-bottom-color: #f4f4f4; margin: 0; padding: 0; list-style: none; display: flex; background: #fff;}
        .nav-tabs > li { margin-bottom: -1px; }
        .nav-tabs > li > a { color: #444; border-radius: 0; padding: 12px 20px; display: block; text-decoration: none; border-top: 3px solid transparent; border-right: 1px solid #f4f4f4; font-weight: bold; font-size: 14px;}
        .nav-tabs > li > a:hover { color: #222; background: #f4f4f4; }
        .nav-tabs > li.active > a { border-top-color: #3c8dbc; border-bottom: none; background-color: #ecf0f5; color: #3c8dbc; }
        
        .tab-content { background: #ecf0f5; padding: 15px; }
        .tab-pane { display: none; animation: fadeIn 0.3s ease-in-out; }
        .tab-pane.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        .grid-table { width: 100%; border-collapse: collapse; background: #fff; table-layout: fixed; }
        .grid-table th, .grid-table td { border: 1px solid #888; padding: 4px; height: 25px; font-size: 12px; white-space: nowrap; overflow: hidden; }
        .grid-table th { background: #d9d6ce; font-weight: bold; text-align: left; }
        .grid-table tbody tr:hover { background-color: #cce5ff; cursor: pointer;}
        .grid-table input { width: 100%; height: 21px; border: 1px solid #ccc; padding: 1px 3px; box-sizing: border-box; font-size: 12px; }
        .grid-table input:focus { outline: 1px solid #2f65d9; }
        .num { text-align: right; } .center { text-align: center; }
        
        .list-group { margin-bottom: 0; padding-left: 0; list-style: none; }
        .list-group-item { position: relative; display: block; padding: 10px 15px; margin-bottom: -1px; background-color: #fff; border: 1px solid #ddd; font-size: 13px; text-decoration: none; color:#333; }
        .list-group-item:hover { background-color:#f5f5f5; }
        .font-monospace { font-family: monospace; }
        .d-none { display: none !important; }

        /* Autocomplete List CSS */
        .ac-box { position: absolute; z-index: 9999; background: #fff; color: #000; border: 1px solid #333; max-height: 210px; overflow-y: auto; min-width: 250px; display: none; font-size: 12px; box-shadow: 2px 2px 5px rgba(0,0,0,0.3); }
        .ac-item { padding: 6px 10px; cursor: pointer; border-bottom: 1px solid #ddd; }
        .ac-item:hover, .ac-item.active { background: #2f65d9; color: #fff; }
        .autocomplete-wrap { position: relative; display: block; }
        .autocomplete-sub { font-size: 10px; color: #666; }
        .ac-item.active .autocomplete-sub { color: #eee; }
    </style>
</head>
<body style="padding: 15px; background-color: #ecf0f5;">

<div class="row">
    <div class="col-md-12">
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                <li class="<?php echo $tab == 'sop' ? 'active' : ''; ?> tab-li"><a href="#" onclick="openTab(event, 'sop')"><i class="fa fa-cubes"></i> STOCK OPNAME (SOP)</a></li>
                <li class="<?php echo $tab == 'transaction' ? 'active' : ''; ?> tab-li"><a href="#" onclick="openTab(event, 'transaction')"><i class="fa fa-exchange"></i> TRANSAKSI (INV_TRAN)</a></li>
            </ul>
            
            <div class="tab-content">
                <?php if (isset($errorSop) && $errorSop != "") { echo '<div class="alert alert-danger" style="padding:8px; margin-bottom:15px; font-weight:bold;">'.h($errorSop).'</div>'; } ?>
                <?php if (isset($errorTrans) && $errorTrans != "") { echo '<div class="alert alert-danger" style="padding:8px; margin-bottom:15px; font-weight:bold;">'.h($errorTrans).'</div>'; } ?>
                <?php if (isset($messageSop) && $messageSop != "" && $tab == 'sop') { echo '<div class="alert alert-success" style="padding:8px; margin-bottom:15px; font-weight:bold;">'.h($messageSop).'</div>'; } ?>
                <?php if (isset($messageTrans) && $messageTrans != "" && $tab == 'transaction') { echo '<div class="alert alert-success" style="padding:8px; margin-bottom:15px; font-weight:bold;">'.h($messageTrans).'</div>'; } ?>

                <!-- ==========================================
                     TAB 1: SOP (STOCK OPNAME)
                =========================================== -->
                <div id="sop" class="tab-pane <?php echo $tab == 'sop' ? 'active' : ''; ?>">
                    <form method="POST" action="" id="formSop">
                        <input type="hidden" name="hapus_id" value="<?php echo h($sopCurrentID); ?>">
                        <div class="row">
                            <div class="col-md-9">
                                <div class="box box-solid bg-gray-light" style="margin-bottom: 15px;">
                                    <div class="box-body" style="padding: 10px;">
                                        <div class="pull-left">
                                            <div class="btn-group">
                                                <a href="?page=<?php echo $pageName; ?>&tab=sop&id=<?php echo h($sopFirstID); ?>" class="btn btn-default <?php echo (!$sopFirstID || $mode!='view')?'disabled':''; ?>"><i class="fa fa-fast-backward"></i></a>
                                                <a href="?page=<?php echo $pageName; ?>&tab=sop&id=<?php echo h($sopPrevID); ?>" class="btn btn-default <?php echo (!$sopPrevID || $mode!='view')?'disabled':''; ?>"><i class="fa fa-backward"></i></a>
                                                <button type="button" class="btn btn-default disabled" style="font-weight:bold; min-width:150px; color:#333;">
                                                    <?php echo ($mode=='new') ? 'INPUT SOP BARU' : 'REF: ' . h($dataSopHeader['SOP_REF']); ?>
                                                </button>
                                                <a href="?page=<?php echo $pageName; ?>&tab=sop&id=<?php echo h($sopNextID); ?>" class="btn btn-default <?php echo (!$sopNextID || $mode!='view')?'disabled':''; ?>"><i class="fa fa-forward"></i></a>
                                                <a href="?page=<?php echo $pageName; ?>&tab=sop&id=<?php echo h($sopLastID); ?>" class="btn btn-default <?php echo (!$sopLastID || $mode!='view')?'disabled':''; ?>"><i class="fa fa-fast-forward"></i></a>
                                            </div>
                                        </div>
                                        <div class="pull-right">
                                            <div class="btn-group">
                                                <a href="?page=<?php echo $pageName; ?>&tab=sop&mode=new" class="btn btn-success <?php echo ($mode=='new')?'active':''; ?>"><i class="fa fa-plus"></i> BARU</a>
                                                <?php if ($isSopLocked): ?>
                                                    <button type="button" class="btn btn-default disabled"><i class="fa fa-lock"></i> LOCKED</button>
                                                <?php else: ?>
                                                    <a href="?page=<?php echo $pageName; ?>&tab=sop&mode=edit&id=<?php echo h($sopCurrentID); ?>" class="btn btn-warning <?php echo ($mode=='edit')?'active':''; ?> <?php echo (!$sopCurrentID)?'disabled':''; ?>"><i class="fa fa-pencil"></i> EDIT</a>
                                                <?php endif; ?>
                                                <?php if ($mode=='view' && $sopCurrentID && !$isSopLocked): ?>
                                                    <button type="submit" name="btnHapusSOP" class="btn btn-danger" onclick="return confirm('Hapus Data SOP Ini?');"><i class="fa fa-trash"></i> HAPUS</button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-default disabled"><i class="fa fa-trash"></i> HAPUS</button>
                                                <?php endif; ?>

                                                <?php if ($isSopEntry): ?>
                                                    <?php if ($mode=='edit'): ?>
                                                         <button type="submit" name="btnUpdateSOP" class="btn btn-warning" onclick="return confirm('Simpan Perubahan?');"><i class="fa fa-save"></i> UPDATE</button>
                                                    <?php else: ?>
                                                         <button type="submit" name="btnSimpanSOP" class="btn btn-success" onclick="return confirm('Simpan SOP Baru?');"><i class="fa fa-save"></i> SIMPAN</button>
                                                    <?php endif; ?>
                                                    <a href="?page=<?php echo $pageName; ?>&tab=sop&id=<?php echo $sopCurrentID; ?>" class="btn btn-default">BATAL</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="clearfix"></div>
                                    </div>
                                </div>

                                <div class="box <?php echo $isSopEntry ? 'box-warning' : 'box-success'; ?>">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Header Stock Opname</h3>
                                    </div>
                                    <div class="box-body" style="<?php echo $isSopEntry ? 'background-color: #fff9e6;' : ''; ?>">
                                        <div class="row">
                                            <div class="col-md-2 form-group"><label>Ref #</label><input type="text" class="form-control text-primary" style="font-weight:bold;" name="SOP_REF" value="<?php echo h($dataSopHeader['SOP_REF']); ?>" <?php echo !$isSopEntry?'readonly':''; ?> required></div>
                                            <div class="col-md-2 form-group"><label>Date</label><input type="date" class="form-control" name="SOP_SDATE" value="<?php echo h($dataSopHeader['SOP_SDATE']); ?>" <?php echo !$isSopEntry?'readonly':''; ?>></div>
                                            <div class="col-md-2 form-group"><label>PIC</label><input type="text" class="form-control" value="<?php echo h($dataSopHeader['SOP_BY'] ? $dataSopHeader['SOP_BY'] : (isset($_SESSION['db_user'])?$_SESSION['db_user']:'SYSTEM')); ?>" readonly></div>
                                            <div class="col-md-4 form-group"><label>Remark</label><input type="text" class="form-control" name="SOP_REM" value="<?php echo h($dataSopHeader['SOP_REM']); ?>" <?php echo !$isSopEntry?'readonly':''; ?>></div>
                                            <div class="col-md-2 form-group" style="padding-top: 23px;">
                                                <label style="color:#00a65a; font-weight:bold;"><input type="checkbox" name="SOP_FINISHED" value="1" <?php echo ($dataSopHeader['SOP_FINISHED']=='T')?'checked':''; ?> <?php echo !$isSopEntry?'disabled':''; ?>> FINISHED (T)</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <?php if($isSopEntry): ?>
                                <div class="box box-primary">
                                    <div class="box-body" style="background:#eaf2f8;">
                                        <div class="row">
                                            <div class="col-md-2 form-group"><label>Lokasi</label><select id="sop_inputLoc" class="form-control"><?php echo $optLoc; ?></select></div>
                                            <div class="col-md-2 form-group"><label>Tag #</label><input type="text" id="sop_inputTagNo" class="form-control" placeholder="No. Tag"></div>
                                            <div class="col-md-4 form-group"><label>Item</label><select id="sop_inputBarang" class="form-control sop-select2-ajax" style="width: 100%;"></select></div>
                                            <div class="col-md-2 form-group"><label>Qty</label>
                                                <div style="display:flex;">
                                                    <input type="number" id="sop_inputQty" class="form-control" value="0" style="width:70%;">
                                                    <span id="sop_inputUnit" style="width:30%; padding:5px; background:#ddd; text-align:center; border:1px solid #ccc; border-left:none;">-</span>
                                                </div>
                                            </div>
                                            <div class="col-md-2 form-group" style="padding-top:23px;"><button type="button" id="sop_btnTambah" class="btn btn-primary btn-block"><i class="fa fa-plus"></i> Tambah</button></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div style="overflow-x:auto;">
                                    <table class="grid-table" id="tabelSop">
                                        <thead>
                                            <tr>
                                                <th style="width:15%;">LOC</th><th style="width:15%;">TAG #</th><th style="width:20%;">ITEM CODE</th><th>ITEM NAME</th>
                                                <th style="width:10%; text-align:right;">QTY</th><th style="width:10%;">UNIT</th>
                                                <?php if($isSopEntry): ?><th style="width:8%; text-align:center;">AKSI</th><?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            if(!empty($dataSopDetail)) {
                                                foreach($dataSopDetail as $row) {
                                                    if($isSopEntry) {
                                                        echo "<tr class='row-item' data-sop-id='{$sopCurrentID}' data-tag-no='{$row['TAG_NO']}'>
                                                            <td><input type='hidden' name='loc_id[]' value='{$row['LOC_ID']}'>{$row['LOC_NAME_DISPLAY']}</td>
                                                            <td class='cell-edit'><input type='hidden' name='tag_no[]' class='val-real' value='{$row['TAG_NO']}'><span class='val-txt font-monospace'>{$row['TAG_NO']}</span><input type='text' class='form-control val-input d-none' value='{$row['TAG_NO']}'></td>
                                                            <td><input type='hidden' name='item_code[]' value='{$row['ITEM_CODE']}'><span class='text-primary fw-bold font-monospace'>{$row['ITEM_CODE']}</span></td>
                                                            <td>{$row['ITEM_NAME_DISPLAY']}</td>
                                                            <td class='text-right cell-edit'><input type='hidden' name='tag_qty[]' class='val-real' value='{$row['TAG_QTY']}'><span class='val-txt fw-bold'>".number_format($row['TAG_QTY'], 2)."</span><input type='number' class='form-control val-input d-none' value='{$row['TAG_QTY']}' step='0.01'></td>
                                                            <td>{$row['ITEM_UNIT_DISPLAY']}</td>
                                                            <td class='text-center'>
                                                                <button type='button' class='btn btn-success btn-xs btn-save-row d-none'><i class='fa fa-check'></i></button>
                                                                <button type='button' class='btn btn-danger btn-xs btn-hapus'><i class='fa fa-times'></i></button>
                                                            </td>
                                                        </tr>";
                                                    } else {
                                                        echo "<tr>
                                                            <td>{$row['LOC_NAME_DISPLAY']}</td><td class='font-monospace'>{$row['TAG_NO']}</td><td class='font-monospace text-primary fw-bold'>{$row['ITEM_CODE']}</td>
                                                            <td>{$row['ITEM_NAME_DISPLAY']}</td><td class='text-right fw-bold'>".number_format($row['TAG_QTY'], 2)."</td><td>{$row['ITEM_UNIT_DISPLAY']}</td>
                                                        </tr>";
                                                    }
                                                }
                                            } else {
                                                $cols = $isSopEntry ? 7 : 6;
                                                echo "<tr><td colspan='{$cols}' class='text-center text-muted'>Tidak ada data tag.</td></tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if ($sopTotalPage > 1 && $mode != 'new' && $mode != 'edit'): ?>
                                    <div style="margin-top:10px; display:flex; justify-content:space-between;">
                                        <div><small>Hal <?php echo $sopPageNo; ?>/<?php echo $sopTotalPage; ?> (Total: <?php echo number_format($sopTotalRow); ?>)</small></div>
                                        <div>
                                            <a href="?page=<?php echo $pageName; ?>&tab=sop&id=<?php echo $sopCurrentID; ?>&mode=<?php echo $mode; ?>&hal=<?php echo max(1, $sopPageNo-1); ?>" class="btn btn-default btn-xs">Prev</a>
                                            <a href="?page=<?php echo $pageName; ?>&tab=sop&id=<?php echo $sopCurrentID; ?>&mode=<?php echo $mode; ?>&hal=<?php echo min($sopTotalPage, $sopPageNo+1); ?>" class="btn btn-default btn-xs">Next</a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-3">
                                <div class="box box-solid box-default">
                                    <div class="box-header with-border" style="background-color: #222d32; color: #fff; text-align:center;">
                                        <h3 class="box-title" style="font-size: 14px; font-weight:bold;">STATUS SOP</h3>
                                    </div>
                                    <div class="box-body" style="text-align: center;">
                                        <?php if ($isSopEntry): ?>
                                            <div class="alert alert-warning" style="margin:0; padding:10px;"><i class="fa fa-pencil"></i> MODE EDIT / INPUT</div>
                                        <?php else: ?>
                                            <div class="alert alert-<?php echo $isSopLocked?'danger':'info'; ?>" style="margin:0; padding:10px;">
                                                <?php echo $isSopLocked ? '<i class="fa fa-lock"></i> SOP FINISHED' : 'READ ONLY MODE'; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="box box-solid">
                                    <div class="box-header with-border" style="background-color: #f4f4f4; text-align:center;">
                                        <h3 class="box-title" style="font-size: 13px; font-weight:bold;">MENU LAPORAN (REPORT)</h3>
                                    </div>
                                    <div class="box-body" style="padding:0;">
                                        <ul class="list-group">
                                            <?php $linkDis = empty($sopCurrentID) ? 'pointer-events:none; opacity:0.5;' : ''; ?>
                                            <a href="report_tag_list.php?sop=<?php echo $sopCurrentID; ?>" target="_blank" class="list-group-item" style="<?php echo $linkDis; ?>"><i class="fa fa-list text-primary"></i> Tag List Detail</a>
                                            <a href="report_var_before.php?id=<?php echo $sopCurrentID; ?>" target="_blank" class="list-group-item" style="<?php echo $linkDis; ?>"><i class="fa fa-calculator text-primary"></i> Variance Before Adjust</a>
                                        </ul>
                                    </div>
                                </div>

                                <?php 
                                $optSopAll = ""; 
                                $qSA = sqlsrv_query($conn, "SELECT SOP_ID, SOP_REF, SOP_SDATE FROM SOP ORDER BY SOP_SDATE DESC");
                                if ($qSA) while($rSA = sqlsrv_fetch_array($qSA)) { 
                                    $tgl = ($rSA['SOP_SDATE'] instanceof DateTime) ? $rSA['SOP_SDATE']->format('d-M-Y') : $rSA['SOP_SDATE'];
                                    $optSopAll .= "<option value='{$rSA['SOP_ID']}'>{$rSA['SOP_REF']} ({$tgl})</option>"; 
                                }
                                ?>
                                <div class="box box-success">
                                    <div class="box-header with-border text-center"><h3 class="box-title" style="font-size:12px;">TAG SUMMARY BY ITEM</h3></div>
                                    <div class="box-body" style="background:#e8f5e9;">
                                        <div class="form-group"><label>Pilih SOP:</label><select id="ts_sop_id" class="form-control sop-select2-std" style="width:100%;"><option value="">-- Pilih SOP --</option><?php echo $optSopAll; ?></select></div>
                                        <button type="button" onclick="cetakTagSummary()" class="btn btn-default btn-block"><i class="fa fa-print"></i> Print Tag Summary</button>
                                        <button type="button" onclick="cetakSopConversion()" class="btn btn-primary btn-block"><i class="fa fa-sitemap"></i> Print SOP Conversion</button>
                                    </div>
                                </div>
                                <div class="box box-primary">
                                    <div class="box-header with-border text-center"><h3 class="box-title" style="font-size:12px;">STOCK ANALYSIS</h3></div>
                                    <div class="box-body" style="background:#eaf2f8;">
                                        <div class="form-group"><label>Item:</label><select id="sa_item_id" class="form-control sop-select2-ajax" style="width:100%;"><option value="0">-- ALL ITEM --</option></select></div>
                                        <div class="form-group"><label>Start Date:</label><input type="date" id="sa_start_date" class="form-control" value="<?php echo date('Y-m-01'); ?>"></div>
                                        <div class="form-group"><label>Periode (Bln):</label><input type="number" id="sa_period" class="form-control" value="1" min="1"></div>
                                        <button type="button" onclick="cetakStockAnalysis()" class="btn btn-default btn-block"><i class="fa fa-print"></i> Print Analysis</button>
                                    </div>
                                </div>
                                <div class="box box-info">
                                    <div class="box-header with-border text-center"><h3 class="box-title" style="font-size:12px;">TAG BY TYPE</h3></div>
                                    <div class="box-body" style="background:#e8f4f8;">
                                        <div class="form-group"><label>Pilih SOP:</label><select id="tag_by_type_sop_id" class="form-control sop-select2-std" style="width:100%;"><option value="">-- Pilih SOP --</option><?php echo $optSopAll; ?></select></div>
                                        <button type="button" onclick="cetakTagByType()" class="btn btn-default btn-block"><i class="fa fa-print"></i> Print Tag By Type</button>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </form>
                </div>

                <!-- ==========================================
                     TAB 2: TRANSACTION (INV_TRAN)
                =========================================== -->
                <div id="transaction" class="tab-pane <?php echo $tab == 'transaction' ? 'active' : ''; ?>">
                    <form method="POST" action="" id="formTrans">
                        <input type="hidden" name="hapus_id" value="<?php echo h($transCurrentID); ?>">
                        <div class="row">
                            <div class="col-md-9">
                                <div class="box box-solid bg-gray-light" style="margin-bottom: 15px;">
                                    <div class="box-body" style="padding: 10px;">
                                        <div class="pull-left">
                                            <div class="btn-group">
                                                <a href="?page=<?php echo $pageName; ?>&tab=transaction&id=<?php echo h($transFirstID); ?>" class="btn btn-default <?php echo (!$transFirstID || $isTransEntry)?'disabled':''; ?>"><i class="fa fa-fast-backward"></i></a>
                                                <a href="?page=<?php echo $pageName; ?>&tab=transaction&id=<?php echo h($transPrevID); ?>" class="btn btn-default <?php echo (!$transPrevID || $isTransEntry)?'disabled':''; ?>"><i class="fa fa-backward"></i></a>
                                            </div>
                                            
                                            <!-- PENCARIAN DOKUMEN MENGGUNAKAN AUTOCOMPLETE JS MURNI -->
                                            <div class="autocomplete-wrap" style="display:inline-block; width: 220px; vertical-align:top; margin:0 5px;">
                                                <input type="text" id="trans_cariDokumen" class="form-control" placeholder="<?php echo ($transCurrentID && !$isTransEntry) ? h($dataTransHeader['TRAN_DOC']) : 'Cari No. Dokumen...'; ?>" autocomplete="off" oninput="showTransSearchAC(this)" onkeydown="transSearchKey(event, this)">
                                            </div>

                                            <div class="btn-group">
                                                <a href="?page=<?php echo $pageName; ?>&tab=transaction&id=<?php echo h($transNextID); ?>" class="btn btn-default <?php echo (!$transNextID || $isTransEntry)?'disabled':''; ?>"><i class="fa fa-forward"></i></a>
                                                <a href="?page=<?php echo $pageName; ?>&tab=transaction&id=<?php echo h($transLastID); ?>" class="btn btn-default <?php echo (!$transLastID || $isTransEntry)?'disabled':''; ?>"><i class="fa fa-fast-forward"></i></a>
                                            </div>
                                        </div>
                                        <div class="pull-right">
                                            <div class="btn-group">
                                                <a href="?page=<?php echo $pageName; ?>&tab=transaction&mode=new" class="btn btn-success <?php echo ($mode=='new')?'active':''; ?>"><i class="fa fa-plus"></i> BARU</a>
                                                <a href="?page=<?php echo $pageName; ?>&tab=transaction&mode=edit&id=<?php echo h($transCurrentID); ?>" class="btn btn-warning <?php echo ($mode=='edit')?'active':''; ?> <?php echo (!$transCurrentID || $isTransEntry)?'disabled':''; ?>"><i class="fa fa-pencil"></i> EDIT</a>
                                                <?php if (!$isTransEntry && $transCurrentID): ?>
                                                    <button type="submit" name="btnHapusTransaksi" class="btn btn-danger" onclick="return confirm('Hapus Data Ini?');"><i class="fa fa-trash"></i> HAPUS</button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-default disabled"><i class="fa fa-trash"></i> HAPUS</button>
                                                <?php endif; ?>
                                                <a href="?page=<?php echo $pageName; ?>&tab=transaction" class="btn btn-primary"><i class="fa fa-refresh"></i></a>

                                                <?php if ($isTransEntry): ?>
                                                    <?php if ($mode == 'edit'): ?>
                                                        <button type="submit" name="btnUpdateTransaksi" class="btn btn-warning" onclick="return confirm('Update?');"><i class="fa fa-save"></i> UPDATE</button>
                                                    <?php else: ?>
                                                        <button type="submit" name="btnSimpanTransaksi" class="btn btn-success" onclick="return confirm('Simpan?');"><i class="fa fa-save"></i> SIMPAN</button>
                                                    <?php endif; ?>
                                                    <a href="?page=<?php echo $pageName; ?>&tab=transaction&id=<?php echo h($transCurrentID); ?>" class="btn btn-default">BATAL</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="clearfix"></div>
                                    </div>
                                </div>

                                <div class="box <?php echo $isTransEntry ? 'box-warning' : 'box-success'; ?>">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Header Transaksi</h3>
                                    </div>
                                    <div class="box-body" style="<?php echo $isTransEntry ? 'background-color: #fff9e6;' : ''; ?>">
                                        <div class="row">
                                            <div class="col-md-3 form-group"><label>No. Dokumen</label><input type="text" class="form-control text-primary" style="font-weight:bold;" name="TRAN_DOC" maxlength="30" value="<?php echo h($dataTransHeader['TRAN_DOC']); ?>" required <?php echo !$isTransEntry?'readonly':''; ?>></div>
                                            <div class="col-md-3 form-group"><label>Tipe Transaksi</label><select class="form-control" name="TRTY_CODE" <?php echo !$isTransEntry?'disabled':''; ?>><?php echo $optTrty; ?></select></div>
                                            
                                            <!-- SUPPLIER AUTOCOMPLETE -->
                                            <div class="col-md-6 form-group autocomplete-wrap">
                                                <label>Supplier</label>
                                                <div style="display: flex; gap: 5px;">
                                                    <input type="text" name="SUP_CODE" id="trans_sup_code" class="form-control font-monospace" style="width: 35%;" placeholder="Sup Code..." value="<?php echo h($dataTransHeader['SUP_CODE']); ?>" autocomplete="off" oninput="showTransSupplierAC(this)" onkeydown="transSupplierKey(event, this)" <?php echo !$isTransEntry?'readonly':''; ?>>
                                                    <input type="text" id="trans_sup_comp" class="form-control" style="width: 65%;" placeholder="Nama Perusahaan Supplier..." value="<?php 
                                                        $compName = '';
                                                        foreach($arrSup as $s) { if($s['SUP_CODE'] == $dataTransHeader['SUP_CODE']) { $compName = $s['SUP_COMP']; break; } }
                                                        echo h($compName);
                                                    ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-2 form-group"><label class="text-danger">Input Date</label><input type="date" class="form-control" name="TRAN_DATE" value="<?php echo h($dataTransHeader['TRAN_DATE']); ?>" <?php echo !$isTransEntry?'readonly':''; ?>></div>
                                            <div class="col-md-2 form-group"><label>Trans. Date</label><input type="date" class="form-control" name="BC_DATE" value="<?php echo h($dataTransHeader['BC_DATE']); ?>" <?php echo !$isTransEntry?'readonly':''; ?>></div>
                                            <?php if ($isPlant1): ?>
                                                <div class="col-md-2 form-group"><label class="text-primary">Tipe BC</label>
                                                    <select class="form-control" name="JENIS_BC" <?php echo !$isTransEntry?'disabled':''; ?>>
                                                        <option value="">- Non BC -</option>
                                                        <option value="BC.2.3" <?php echo ($dataTransHeader['JENIS_BC']=='BC.2.3')?'selected':''; ?>>BC.2.3</option><option value="BC.2.5" <?php echo ($dataTransHeader['JENIS_BC']=='BC.2.5')?'selected':''; ?>>BC.2.5</option><option value="BC.2.6.1" <?php echo ($dataTransHeader['JENIS_BC']=='BC.2.6.1')?'selected':''; ?>>BC.2.6.1</option><option value="BC.2.6.2" <?php echo ($dataTransHeader['JENIS_BC']=='BC.2.6.2')?'selected':''; ?>>BC.2.6.2</option><option value="BC.2.7" <?php echo ($dataTransHeader['JENIS_BC']=='BC.2.7')?'selected':''; ?>>BC.2.7</option><option value="BC.3.0" <?php echo ($dataTransHeader['JENIS_BC']=='BC.3.0')?'selected':''; ?>>BC.3.0</option><option value="BC.4.0" <?php echo ($dataTransHeader['JENIS_BC']=='BC.4.0')?'selected':''; ?>>BC.4.0</option><option value="BC.4.1" <?php echo ($dataTransHeader['JENIS_BC']=='BC.4.1')?'selected':''; ?>>BC.4.1</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3 form-group"><label class="text-primary">Nomor BC</label><input type="text" class="form-control" name="NOMOR_BC" placeholder="Ketik No BC..." maxlength="50" value="<?php echo h($dataTransHeader['NOMOR_BC']); ?>" <?php echo !$isTransEntry?'readonly':''; ?>></div>
                                                <div class="col-md-3 form-group">
                                            <?php else: ?>
                                                <div class="col-md-8 form-group">
                                            <?php endif; ?>
                                                <label>Keterangan (Max 50 Char)</label><input type="text" class="form-control" name="TRAN_REM" maxlength="50" value="<?php echo h($dataTransHeader['TRAN_REM']); ?>" <?php echo !$isTransEntry?'readonly':''; ?>>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ITEM DETAIL INPUT MENGGUNAKAN PURE AJAX AUTOCOMPLETE (api_cari_barang.php) -->
                                <?php if($isTransEntry): ?>
                                <div class="box box-primary">
                                    <div class="box-body" style="background:#eaf2f8;">
                                        <div class="row">
                                            <div class="col-md-6 form-group autocomplete-wrap">
                                                <label>Cari Barang (Kode / Nama)</label>
                                                <input type="text" id="trans_inputBarang" class="form-control" placeholder="Ketik Kode atau Nama Barang..." autocomplete="off" oninput="showTransItemAC(this)" onkeydown="transItemKey(event, this)">
                                            </div>
                                            <div class="col-md-2 form-group"><label>Qty</label><input type="number" id="trans_inputQty" class="form-control" value="0" step="0.01"></div>
                                            <div class="col-md-2 form-group"><label>Unit</label><input type="text" id="trans_inputUnit" class="form-control" value="Pcs" readonly style="background:#eee; text-align:center;"></div>
                                            <div class="col-md-2 form-group" style="padding-top:23px;"><button type="button" class="btn btn-primary btn-block" id="trans_btnTambahRow"><i class="fa fa-plus"></i> TAMBAH</button></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div style="overflow-x:auto;">
                                    <table class="grid-table" id="trans_tabelDetail">
                                        <thead>
                                            <tr><th style="width:5%;text-align:center;">No</th><th style="width:20%;">Kode Item</th><th>Nama Barang</th><th style="width:10%;text-align:right;">Qty</th><th style="width:10%;text-align:center;">Unit</th><?php if($isTransEntry): ?><th style="width:10%;text-align:center;">Aksi</th><?php endif; ?></tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            if (!empty($dataTransDetail)) {
                                                $no = 1;
                                                foreach ($dataTransDetail as $row) {
                                                    if ($isTransEntry) {
                                                        echo "<tr class='row-item'>
                                                            <td class='center'>$no</td>
                                                            <td><input type='hidden' name='item_code[]' value='{$row['ITEM_CODE']}'><span class='font-monospace fw-bold text-primary'>{$row['ITEM_CODE']}</span></td>
                                                            <td>{$row['ITEM_NAME']}</td>
                                                            <td class='text-right trans-cell-qty' style='cursor: pointer;' title='Double click'>
                                                                <input type='hidden' name='item_qty[]' class='trans-input-qty-val' value='{$row['IT_QTY']}'>
                                                                <span class='trans-txt-qty'>".number_format($row['IT_QTY'], 2)."</span>
                                                                <input type='number' class='form-control trans-input-qty-edit d-none' value='{$row['IT_QTY']}' step='0.01'>
                                                            </td>
                                                            <td class='center'><input type='hidden' name='item_unit[]' value='{$row['ITEM_UNIT']}'>{$row['ITEM_UNIT']}</td>
                                                            <td class='center'>
                                                                <button type='button' class='btn btn-success btn-xs trans-btn-save-row d-none'><i class='fa fa-check'></i></button>
                                                                <button type='button' class='btn btn-danger btn-xs trans-btn-hapus-row'><i class='fa fa-times'></i></button>
                                                            </td>
                                                        </tr>";
                                                    } else {
                                                        echo "<tr>
                                                            <td class='center'>$no</td><td class='font-monospace'>{$row['ITEM_CODE']}</td><td>{$row['ITEM_NAME']}</td>
                                                            <td class='text-right fw-bold'>".number_format($row['IT_QTY'], 2)."</td><td class='center'>{$row['ITEM_UNIT']}</td>
                                                        </tr>";
                                                    }
                                                    $no++;
                                                }
                                            } else {
                                                $colspan = $isTransEntry ? 6 : 5;
                                                echo "<tr><td colspan='$colspan' class='center text-muted'>Tidak ada detail barang.</td></tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="box box-solid box-default">
                                    <div class="box-header with-border" style="background-color: #222d32; color: #fff; text-align:center;">
                                        <h3 class="box-title" style="font-size: 14px; font-weight:bold;">STATUS TRANSAKSI</h3>
                                    </div>
                                    <div class="box-body" style="text-align: center;">
                                        <?php if ($isTransEntry): ?>
                                            <div class="alert alert-warning" style="margin:0; padding:10px;"><i class="fa fa-pencil"></i> MODE EDIT / INPUT</div>
                                        <?php else: ?>
                                            <div class="alert alert-info" style="margin:0; padding:10px;"><i class="fa fa-lock"></i> DATA TERSIMPAN</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="box box-solid">
                                    <div class="box-header with-border" style="background-color: #f4f4f4; text-align:center;">
                                        <h3 class="box-title" style="font-size: 13px; font-weight:bold;">CETAK DOKUMEN</h3>
                                    </div>
                                    <div class="box-body" style="padding:0;">
                                        <?php 
                                        $disBtn = ($transCurrentID) ? '' : 'pointer-events:none; opacity:0.5;';
                                        $trty = $dataTransHeader['TRTY_CODE'];
                                        $disPart = ($transCurrentID && in_array($trty, ['03', '04', '08', '09', '96', '97'])) ? "" : "pointer-events:none; opacity:0.5;";
                                        ?>
                                        <ul class="list-group">
                                            <a href="print_icl.php?id=<?php echo h($transCurrentID); ?>" target="_blank" class="list-group-item" style="<?php echo $disBtn; ?>">ICL OTOMATIS</a>
                                            <a href="print_spb.php?id=<?php echo h($transCurrentID); ?>&type=MAT" target="_blank" class="list-group-item" style="<?php echo $disBtn; ?>">MAT SLIP</a>
                                            <a href="print_slip_physical.php?id=<?php echo h($transCurrentID); ?>" target="_blank" class="list-group-item" style="<?php echo $disPart; ?>">PART SLIP (03/04/08/09/96/97)</a>
                                            <a href="print_spb.php?id=<?php echo h($transCurrentID); ?>&type=SPB" target="_blank" class="list-group-item" style="<?php echo $disBtn; ?>">SPB (Surat Jalan)</a>
                                        </ul>
                                    </div>
                                </div>

                                <div class="box box-warning">
                                    <div class="box-header with-border text-center"><h3 class="box-title" style="font-size:12px;">REKAP LIST ICL</h3></div>
                                    <div class="box-body" style="background:#fff9e6;">
                                        <div class="form-group"><label>Dari Tanggal (Opsional):</label><input type="date" id="icl_start_date" class="form-control"></div>
                                        <div class="form-group"><label>Sampai Tanggal (Opsional):</label><input type="date" id="icl_end_date" class="form-control"></div>
                                        <button type="button" id="btnTampilIcl" class="btn btn-default btn-block"><i class="fa fa-list"></i> TAMPILKAN LIST</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Div untuk Tempat Tampil Hasil Autocomplete -->
<div id="acBox" class="ac-box"></div>

<!-- =========================================================================
     JAVASCRIPT LENGKAP (TAB SOP & TAB TRANSACTION)
========================================================================== -->
<script>
    // Data Array Supplier dari PHP untuk Autocomplete
    var transSupplierData = <?php echo json_encode($arrSup); ?>;

    // Utils AC
    function enc(v) { return encodeURIComponent(v == null ? "" : v); }
    function htmlEncode(v) { return String(v == null ? "" : v).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }
    function byId(id){return document.getElementById(id);}
    
    var acBox = null, acItems = [], acIndex = -1, acMode = "";
    
    function initAC() { acBox = byId("acBox"); }
    function hideAC() { if(acBox) { acBox.style.display="none"; acBox.innerHTML=""; } acItems = []; acIndex = -1; acMode = ""; }
    function positionAC(input) { initAC(); var r = input.getBoundingClientRect(); acBox.style.left = (r.left + window.scrollX) + "px"; acBox.style.top = (r.bottom + window.scrollY) + "px"; acBox.style.width = (r.width < 250 ? 250 : r.width) + "px"; }
    function renderAC(renderText, pickFunc) { initAC(); acBox.innerHTML = ""; for(var i=0; i<acItems.length; i++) { var d = document.createElement("div"); d.className = "ac-item" + (i == acIndex ? " active" : ""); d.innerHTML = renderText(acItems[i]); d.setAttribute("data-index", i); d.onmousedown = function() { pickFunc(acItems[parseInt(this.getAttribute("data-index"), 10)]); }; acBox.appendChild(d); } acBox.style.display = acItems.length > 0 ? "block" : "none"; }
    function acMove(step, renderText, pickFunc) { if(acItems.length <= 0) return; acIndex += step; if(acIndex < 0) acIndex = acItems.length - 1; if(acIndex >= acItems.length) acIndex = 0; renderAC(renderText, pickFunc); }
    function acEnter(pickFunc) { if(acItems.length <= 0) return false; if(acIndex < 0) acIndex = 0; pickFunc(acItems[acIndex]); return true; }

    // AJAX TRANSAKSI SEARCH LOGIC (DIPERBAIKI DENGAN TAMPILAN POP-UP RAPI)
function showTransSearchAC(input) {
    initAC(); 
    var key = (input.value || "").trim();
    acMode = "trans_search"; 
    acItems = []; 
    acIndex = -1;
    
    if(key.length < 1) { hideAC(); return; }

    var xhr = new XMLHttpRequest();
    // PERUBAHAN DISINI: Tembak langsung ke search_trans.php
    xhr.open("GET", "search_trans.php?q=" + enc(key), true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if(res.success) {
                    acItems = res.rows || [];
                    positionAC(input);
                    renderAC(function(r) { 
                        return "<b>" + htmlEncode(r.TRAN_DOC) + "</b><div class='autocomplete-sub'>Tanggal: " + htmlEncode(r.TRAN_DATE) + "</div>"; 
                    }, pickTransSearch);
                } else hideAC();
            } catch(e) {
                console.log("Error Parsing JSON Autocomplete:", e);
                console.log("Response dari Server:", xhr.responseText);
                hideAC(); 
            }
        }
    };
    xhr.send(null);
}

    function transSearchKey(e, input) {
        if(e.key === "ArrowDown") { e.preventDefault(); if(acMode !== "trans_search" || acItems.length == 0) showTransSearchAC(input); acMove(1, function(r){return "<b>"+htmlEncode(r.TRAN_DOC)+"</b><div class='autocomplete-sub'>Tanggal: "+htmlEncode(r.TRAN_DATE)+"</div>";}, pickTransSearch); return false; }
        if(e.key === "ArrowUp") { e.preventDefault(); if(acMode !== "trans_search" || acItems.length == 0) showTransSearchAC(input); acMove(-1, function(r){return "<b>"+htmlEncode(r.TRAN_DOC)+"</b><div class='autocomplete-sub'>Tanggal: "+htmlEncode(r.TRAN_DATE)+"</div>";}, pickTransSearch); return false; }
        if(e.key === "Enter") { if(acMode === "trans_search" && acItems.length > 0){ e.preventDefault(); acEnter(pickTransSearch); return false; } return true; }
        if(e.key === "Escape") hideAC();
    }

    function pickTransSearch(r) {
        hideAC();
        window.location.href = "?page=<?php echo urlencode($pageName); ?>&tab=transaction&id=" + enc(r.TRAN_ID);
    }

    // SUPPLIER AUTOCOMPLETE LOGIC
    function showTransSupplierAC(input) {
        initAC();
        var key = (input.value || "").toUpperCase();
        acMode = "trans_supplier"; 
        acItems = []; 
        acIndex = -1;
        
        if(key.length < 1) { 
            hideAC(); 
            byId("trans_sup_comp").value = "";
            return; 
        }

        for(var i = 0; i < transSupplierData.length; i++) {
            var s = transSupplierData[i];
            var textMatch = (s.SUP_CODE || "") + " " + (s.SUP_COMP || "");
            if(textMatch.toUpperCase().indexOf(key) >= 0) {
                acItems.push(s);
            }
            if(acItems.length >= 40) break;
        }
        
        positionAC(input);
        renderAC(function(s) { 
            return "<b>" + htmlEncode(s.SUP_CODE) + "</b> - " + htmlEncode(s.SUP_COMP); 
        }, pickTransSupplier);
    }

    function transSupplierKey(e, input) {
        if(e.key === "ArrowDown") { 
            e.preventDefault(); 
            if(acMode !== "trans_supplier" || acItems.length == 0) showTransSupplierAC(input); 
            acMove(1, function(s){ return "<b>" + htmlEncode(s.SUP_CODE) + "</b> - " + htmlEncode(s.SUP_COMP); }, pickTransSupplier); 
            return false; 
        }
        if(e.key === "ArrowUp") { 
            e.preventDefault(); 
            if(acMode !== "trans_supplier" || acItems.length == 0) showTransSupplierAC(input); 
            acMove(-1, function(s){ return "<b>" + htmlEncode(s.SUP_CODE) + "</b> - " + htmlEncode(s.SUP_COMP); }, pickTransSupplier); 
            return false; 
        }
        if(e.key === "Enter") { 
            if(acMode === "trans_supplier" && acItems.length > 0) { 
                e.preventDefault(); 
                acEnter(pickTransSupplier); 
                return false; 
            } 
            return true; 
        }
        if(e.key === "Escape") hideAC();
    }

    function pickTransSupplier(s) {
        byId("trans_sup_code").value = s.SUP_CODE;
        byId("trans_sup_comp").value = s.SUP_COMP;
        hideAC();
    }

    // ITEM / BARANG AUTOCOMPLETE LOGIC (MENGGUNAKAN api_cari_barang.php)
    var selectedItemCode = "";
    function showTransItemAC(input) {
        initAC();
        var key = (input.value || "").trim();
        acMode = "trans_item"; 
        acItems = []; 
        acIndex = -1;
        
        if(key.length < 1) { 
            hideAC(); 
            selectedItemCode = "";
            return; 
        }

        var xhr = new XMLHttpRequest();
        xhr.open("GET", "api_cari_barang.php?term=" + enc(key), true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    acItems = res || [];
                    positionAC(input);
                    renderAC(function(item) { 
                        return htmlEncode(item.label); 
                    }, pickTransItem);
                } catch(e) { hideAC(); }
            }
        };
        xhr.send(null);
    }

    function transItemKey(e, input) {
        if(e.key === "ArrowDown") { 
            e.preventDefault(); 
            acMove(1, function(item){ return htmlEncode(item.label); }, pickTransItem); 
            return false; 
        }
        if(e.key === "ArrowUp") { 
            e.preventDefault(); 
            acMove(-1, function(item){ return htmlEncode(item.label); }, pickTransItem); 
            return false; 
        }
        if(e.key === "Enter") { 
            if(acMode === "trans_item" && acItems.length > 0) { 
                e.preventDefault(); 
                acEnter(pickTransItem); 
                return false; 
            } 
            return true; 
        }
        if(e.key === "Escape") hideAC();
    }

    function pickTransItem(item) {
        selectedItemCode = item.id; // Menyimpan kode item yang dipilih
        byId("trans_inputBarang").value = item.label; // Menampilkan label di input
        byId("trans_inputUnit").value = item.unit || "Pcs"; // Otomatis mengisi unit
        hideAC();
        byId("trans_inputQty").focus();
        byId("trans_inputQty").select();
    }

    // MENGHILANGKAN ACBOX KETIKA KLIK DI LUAR AREA
    document.addEventListener("click", function(e) {
        initAC();
        if(acBox && !acBox.contains(e.target)) {
            if(!e.target || !e.target.getAttribute || e.target.getAttribute("autocomplete") !== "off") hideAC();
        }
    });

    // Tab Navigation
    function openTab(evt, tabName) {
        evt.preventDefault(); 
        var tabPanes = document.getElementsByClassName("tab-pane"); for (var i = 0; i < tabPanes.length; i++) tabPanes[i].classList.remove("active");
        var tabLis = document.getElementsByClassName("tab-li"); for (var i = 0; i < tabLis.length; i++) tabLis[i].classList.remove("active");
        document.getElementById(tabName).classList.add("active"); evt.currentTarget.parentElement.classList.add("active");
        var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?page=<?php echo urlencode($pageName); ?>&tab=' + tabName;
        window.history.pushState({path:newUrl}, '', newUrl);
    }

    // Reports Call
    function cetakSopConversion() { var s = document.getElementById('ts_sop_id').value; if (!s) { alert('Silakan pilih Dokumen SOP!'); return; } window.open('print_sop_conversion.php?sop_id=' + s, '_blank'); }
    function cetakTagSummary() { var s = document.getElementById('ts_sop_id').value; if (!s) { alert('Silakan pilih Dokumen SOP!'); return; } window.open('print_tag_summary_item.php?sop_id=' + s, '_blank'); }
    function cetakStockAnalysis() { var i = document.getElementById('sa_item_id').value; var d = document.getElementById('sa_start_date').value; var p = document.getElementById('sa_period').value; if (!i || i=="0") { alert('Silakan pilih Item!'); return; } if (!d || !p) { alert('Tanggal dan Periode wajib diisi!'); return; } window.open('print_stock_analysis.php?item_id=' + i + '&start_date=' + d + '&period=' + p, '_blank'); }
    function cetakTagByType() { var s = document.getElementById('tag_by_type_sop_id').value; if (!s) { alert('Silakan pilih Dokumen SOP!'); return; } window.open('print_tag_by_type.php?sop_id=' + s, '_blank'); }

    $(document).ready(function() {
        // --- TRANSACTION SCRIPTS ---
        <?php if($isTransEntry): ?>
        $('#trans_btnTambahRow').click(function() {
            var kode = selectedItemCode; 
            var namaFull = $('#trans_inputBarang').val(); 
            var qty  = $('#trans_inputQty').val(); 
            var unit = $('#trans_inputUnit').val();

            if (!kode) { 
                kode = namaFull.split(' - ')[0].trim();
            }

            if (!kode) { alert('Pilih atau ketik barang dulu!'); return; } 
            if (qty <= 0 || isNaN(qty)) { alert('Qty harus > 0'); return; }

            var namaBarang = namaFull.indexOf(' - ') >= 0 ? namaFull.split(' - ').slice(1).join(' - ') : namaFull;

            var rowCount = $('#trans_tabelDetail tbody tr').length + 1; 
            if($('#trans_tabelDetail tbody tr td').hasClass('text-muted')) { 
                $('#trans_tabelDetail tbody').empty(); 
                rowCount=1; 
            }

            var html = `<tr class='row-item'><td class="center">${rowCount}</td><td><input type="hidden" name="item_code[]" value="${kode}"><span class="font-monospace fw-bold text-primary">${kode}</span></td><td>${namaBarang}</td><td class='text-right trans-cell-qty' style='cursor: pointer;' title='Double click'><input type='hidden' name='item_qty[]' class='trans-input-qty-val' value='${qty}'><span class='trans-txt-qty'>${parseFloat(qty).toFixed(2)}</span><input type='number' class='form-control trans-input-qty-edit d-none' value='${qty}' step='0.01'></td><td class="center"><input type="hidden" name="item_unit[]" value="${unit}">${unit}</td><td class="center"><button type='button' class='btn btn-success btn-xs trans-btn-save-row d-none'><i class='fa fa-check'></i></button> <button type='button' class='btn btn-danger btn-xs trans-btn-hapus-row'><i class='fa fa-times'></i></button></td></tr>`;
            
            $('#trans_tabelDetail tbody').append(html); 
            $('#trans_inputBarang').val(''); 
            selectedItemCode = '';
            $('#trans_inputQty').val(0); 
            $('#trans_inputUnit').val('Pcs');
            $('#trans_inputBarang').focus(); 
        });

        $('#trans_inputQty').on('keypress', function(e) { if (e.which == 13) { e.preventDefault(); $('#trans_btnTambahRow').click(); } });
        $(document).on('click', '.trans-btn-hapus-row', function() {$(this).closest('tr').remove(); });
        $(document).on('dblclick', '.trans-cell-qty', function() { var$td = $(this);$td.find('.trans-txt-qty').addClass('d-none'); $td.find('.trans-input-qty-edit').removeClass('d-none').focus().select();$td.closest('tr').find('.trans-btn-save-row').removeClass('d-none'); });
        $(document).on('click', '.trans-btn-save-row', function() { var$row = $(this).closest('tr'); var$tdQty = $row.find('.trans-cell-qty'); var newVal =$tdQty.find('.trans-input-qty-edit').val(); $tdQty.find('.trans-input-qty-val').val(newVal);$tdQty.find('.trans-txt-qty').text(parseFloat(newVal).toFixed(2)).removeClass('d-none'); $tdQty.find('.trans-input-qty-edit').addClass('d-none');$(this).addClass('d-none'); });
        $(document).on('keypress', '.trans-input-qty-edit', function(e) { if(e.which == 13) { e.preventDefault();$(this).closest('tr').find('.trans-btn-save-row').click(); } });
        <?php endif; ?>

        $('#btnTampilIcl').click(function() { var start = $('#icl_start_date').val(); var end = $('#icl_end_date').val(); window.open('print_icl_list.php?start_date=' + start + '&end_date=' + end, '_blank'); });
    });
</script>

</body>
</html>
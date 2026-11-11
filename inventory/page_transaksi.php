<?php
if (session_id() == "") {
    session_start();
}

require_once __DIR__ . '/../config/database_p1.php';

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
function getv($n, $d="") { return isset($_GET[$n]) ? trim((string)$_GET[$n]) : $d; }
function postv($n, $d="") { return isset($_POST[$n]) ? trim((string)$_POST[$n]) : $d; }
function sql_error_text() { return print_r(sqlsrv_errors(), true); }
function to_float($v) { $v = trim((string)$v); return $v==="" ? 0 : floatval(str_replace(",", "", $v)); }

function fmt_date($v) {
    if ($v instanceof DateTime) return $v->format("Y-m-d");
    if ($v === "" || $v === null) return date("Y-m-d");
    $t = strtotime((string)$v);
    return $t === false ? date("Y-m-d") : date("Y-m-d", $t);
}

function fmt_date_view($v) {
    if ($v instanceof DateTime) return $v->format("d-M-Y");
    if ($v === "" || $v === null) return "";
    $t = strtotime((string)$v);
    return $t === false ? "" : date("d-M-Y", $t);
}

function find_item_id_by_code($conn, $itemCode) {
    $itemCode = trim((string)$itemCode);
    if ($itemCode == "") return 0;

    $sql = "SELECT TOP 1 ITEM_ID FROM dbo.ITEMS WHERE ITEM_CODE = ?";
    $stmt = sqlsrv_query($conn, $sql, array($itemCode));

    if ($stmt === false) return 0;
    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    return $r ? intval($r["ITEM_ID"]) : 0;
}

// CEK AKTIF PLANT
$isPlant1 = true;
if (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2') {
    $isPlant1 = false;
}
$TABEL_BC = "BC_TRANS"; 

$message = "";
$error = "";

$action = postv("action", "");
$editId = intval(getv("id", "0")); 
$q = getv("q", "");

// ==== PENANDA AJAX UNTUK SAVE BY ENTER ====
$isAjax = (isset($_GET['ajax']) && $_GET['ajax'] == '1') || (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1');
// ==========================================

/* ======================================================
   DELETE TRANSAKSI HEADER
====================================================== */
if ($action == "delete") {
    $tranId = intval(postv("tran_id", "0"));

    if ($tranId <= 0) {
        $error = "Pilih transaksi dulu.";
    } else {
        sqlsrv_begin_transaction($conn);
        $ok = true;

        $stmtDelDet = sqlsrv_query($conn, "DELETE FROM INV_TRAN WHERE TRAN_ID = ?", array($tranId));
        if ($stmtDelDet === false) { $ok = false; $error = "Delete detail transaksi gagal:\n" . sql_error_text(); }

        if ($ok && $isPlant1) {
            $sqlBC = "DELETE FROM $TABEL_BC WHERE NO_TRANS = (SELECT TRAN_DOC FROM TRANS WHERE TRAN_ID = ?)";
            $stmtDelBC = sqlsrv_query($conn, $sqlBC, array($tranId));
            if ($stmtDelBC === false) { $ok = false; $error = "Delete BC gagal:\n" . sql_error_text(); }
        }

        if ($ok) {
            $stmtDel = sqlsrv_query($conn, "DELETE FROM TRANS WHERE TRAN_ID = ?", array($tranId));
            if ($stmtDel === false) { $ok = false; $error = "Delete transaksi gagal:\n" . sql_error_text(); }
        }

        if ($ok) {
            sqlsrv_commit($conn);
            header("Location: ?page=transaksi&msg=deleted");
            exit;
        } else {
            sqlsrv_rollback($conn);
        }
    }
}

/* ======================================================
   SAVE TRANSAKSI
====================================================== */
if ($action == "save") {
    $tranId       = intval(postv("tran_id", "0"));
    $tranDoc      = substr(trim(postv("tran_doc", "")), 0, 30);
    $tranDate     = postv("tran_date", date("Y-m-d"));
    $trtyCode     = postv("trty_code", "");
    $supCode      = postv("sup_code", "");
    $remark       = substr(trim(postv("tran_rem", "")), 0, 50);
    $BCdate       = postv("bc_date", "");
    $tranADate    = ($BCdate != "") ? $BCdate : date("Y-m-d");

    if ($supCode === "") $supCode = null;
    $BCdateVal = ($BCdate == "") ? null : $BCdate;

    $jenisBC = isset($_POST['jenis_bc']) ? $_POST['jenis_bc'] : '';
    $nomorBC = isset($_POST['nomor_bc']) ? substr(trim($_POST['nomor_bc']), 0, 50) : '';

    $allowEmpty = intval(postv("allow_empty_detail", "0"));

    if ($tranDate == "") $tranDate = date("Y-m-d");

    $itemIds    = isset($_POST["item_id"]) ? $_POST["item_id"] : array();
    $itemCodes  = isset($_POST["item_code"]) ? $_POST["item_code"] : array();
    $qtys       = isset($_POST["it_qty"]) ? $_POST["it_qty"] : array();

    $hasDetail = false;

    for ($i = 0; $i < count($itemIds); $i++) {
        $itemId = intval($itemIds[$i]);
        $itemCode = isset($itemCodes[$i]) ? trim((string)$itemCodes[$i]) : "";
        if ($itemId <= 0 && $itemCode != "") $itemId = find_item_id_by_code($conn, $itemCode);
        $qty = isset($qtys[$i]) ? to_float($qtys[$i]) : 0;
        
        if ($itemId > 0 && $qty > 0) { $hasDetail = true; break; }
    }

    if (!$hasDetail && !($allowEmpty == 1 && $tranId > 0)) {
        $error = "Detail transaksi minimal 1 baris dan qty harus lebih dari 0.";
    } else {
        sqlsrv_begin_transaction($conn);
        $ok = true;
        
        // Pengecekan Duplicate No Dokumen jika New Record dan bukan kode tertentu
        if ($tranId == 0 && $trtyCode != '14') {
            $cekDoc = sqlsrv_query($conn, "SELECT TOP 1 TRAN_DOC FROM TRANS WHERE TRAN_DOC = ?", array($tranDoc));
            if ($cekDoc && sqlsrv_fetch_array($cekDoc)) {
                $ok = false;
                $error = "No. Dokumen '{$tranDoc}' sudah dipakai! Silakan ganti No. Dokumen.";
            }
        }

        if ($ok) {
            if ($tranId > 0) {
                $sqlH = "UPDATE TRANS SET TRAN_DATE=?, TRTY_CODE=?, SUP_CODE=?, TRAN_REM=?, TRAN_ADATE=? WHERE TRAN_ID=?";
                $paramsH = array($tranDate, $trtyCode, $supCode, $remark, $tranADate, $tranId);
                $stmtH = sqlsrv_query($conn, $sqlH, $paramsH);
                if ($stmtH === false) { $ok = false; $error = "Simpan header gagal:\n" . sql_error_text(); }
            } else {
                $sqlH = "INSERT INTO TRANS (TRAN_DOC, TRAN_DATE, TRTY_CODE, SUP_CODE, TRAN_REM, TRAN_ADATE) VALUES (?, ?, ?, ?, ?, ?); SELECT SCOPE_IDENTITY() AS ID";
                $paramsH = array($tranDoc, $tranDate, $trtyCode, $supCode, $remark, $tranADate);
                $stmtH = sqlsrv_query($conn, $sqlH, $paramsH);
                
                if ($stmtH === false) {
                    $ok = false; $error = "Simpan header gagal:\n" . sql_error_text();
                } else {
                    sqlsrv_next_result($stmtH); 
                    $newRow = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
                    if ($newRow && isset($newRow['ID'])) {
                        $tranId = intval($newRow['ID']);
                    } else { 
                        $ok = false; $error = "Gagal mengambil TRAN_ID yang baru."; 
                    }
                }
            }
        }

        // Simpan Data BC khusus Plant 1
        if ($ok && $isPlant1) {
            sqlsrv_query($conn, "DELETE FROM $TABEL_BC WHERE NO_TRANS=?", array($tranDoc));
            $stmtBC = sqlsrv_query($conn, "INSERT INTO $TABEL_BC (NO_TRANS, JENIS_BC, NOMOR_BC, BC_DATE) VALUES (?, ?, ?, ?)", array($tranDoc, $jenisBC, $nomorBC, $BCdateVal));
            if ($stmtBC === false) { $ok = false; $error = "Gagal Update Tabel BC:\n" . sql_error_text(); }
        }

        if ($ok) {
            $stmtDel = sqlsrv_query($conn, "DELETE FROM INV_TRAN WHERE TRAN_ID = ?", array($tranId));
            if ($stmtDel === false) { $ok = false; $error = "Hapus detail gagal:\n" . sql_error_text(); }
        }

        if ($ok) {
            $lineNo = 1;
            for ($i = 0; $i < count($itemIds); $i++) {
                $itemId = intval($itemIds[$i]);
                $itemCode = isset($itemCodes[$i]) ? trim((string)$itemCodes[$i]) : "";
                if ($itemId <= 0 && $itemCode != "") $itemId = find_item_id_by_code($conn, $itemCode);

                $qty  = isset($qtys[$i]) ? to_float($qtys[$i]) : 0;
                
                if ($itemId <= 0 || $qty <= 0) continue;

                $sqlD = "INSERT INTO INV_TRAN (TRAN_ID, ITEM_ID, ITEM_CODE, IT_QTY, IT_LINENO, TRAN_REMARK, ST_CODE) VALUES (?, ?, ?, ?, ?, ?, 'OK')";
                $paramsD = array($tranId, $itemId, $itemCode, $qty, $lineNo, $remark);
                $stmtD = sqlsrv_query($conn, $sqlD, $paramsD);

                if ($stmtD === false) { $ok = false; $error = "Simpan detail gagal:\n" . sql_error_text(); break; }
                $lineNo++;
            }
        }

        if ($ok) {
            sqlsrv_commit($conn);
            
            // ==== JIKA DISIMPAN LEWAT ENTER (AJAX) ====
            if ($isAjax) {
                error_reporting(0); // Mencegah warning PHP merusak format JSON
                while (ob_get_level()) { ob_end_clean(); } // Bersihkan HTML dari halaman dashboard utama
                header('Content-Type: application/json');
                echo json_encode(array("status" => "success", "tran_id" => $tranId));
                exit;
            }
            // ==========================================

            header("Location: ?page=transaksi&id=" . $tranId . "&msg=saved");
            exit;
        } else {
            sqlsrv_rollback($conn);
            if ($error == "") $error = "Simpan gagal:\n" . sql_error_text();
            
            // ==== JIKA ERROR LEWAT ENTER (AJAX) ====
            if ($isAjax) {
                error_reporting(0);
                while (ob_get_level()) { ob_end_clean(); } 
                header('Content-Type: application/json');
                echo json_encode(array("status" => "error", "message" => $error));
                exit;
            }
            // =======================================
        }
    }
    
    // ==== TANGKAP ERROR VALIDASI FORM JIKA VIA AJAX ====
    if ($error != "" && $isAjax) {
        error_reporting(0);
        while (ob_get_level()) { ob_end_clean(); } 
        header('Content-Type: application/json');
        echo json_encode(array("status" => "error", "message" => $error));
        exit;
    }
    // ===================================================
}

/* ======================================================
   LOAD HEADER
====================================================== */
$header = array("TRAN_ID" => "", "TRAN_DOC" => "", "TRAN_DATE" => date("Y-m-d"), "TRTY_CODE" => "", "TRTY_DESC" => "", "SUP_CODE" => "", "SUP_COMP" => "", "TRAN_REM" => "", "JENIS_BC" => "", "NOMOR_BC" => "", "BC_DATE" => "");
$details = array();

if ($editId > 0) {
    if ($isPlant1) {
        $sqlH = "
            SELECT T.TRAN_ID, T.TRAN_DOC, T.TRAN_DATE, T.TRAN_ADATE, T.TRTY_CODE, T.SUP_CODE, T.TRAN_REM, 
                   TY.TRTY_DESC, S.SUP_COMP, B.JENIS_BC, B.NOMOR_BC, B.BC_DATE 
            FROM TRANS T 
            LEFT JOIN TRTY TY ON T.TRTY_CODE = TY.TRTY_CODE
            LEFT JOIN SUPPLIER S ON T.SUP_CODE = S.SUP_CODE
            LEFT JOIN $TABEL_BC B ON T.TRAN_DOC = B.NO_TRANS 
            WHERE T.TRAN_ID = ?
        ";
    } else {
        $sqlH = "
            SELECT T.TRAN_ID, T.TRAN_DOC, T.TRAN_DATE, T.TRAN_ADATE, T.TRTY_CODE, T.SUP_CODE, T.TRAN_REM, 
                   TY.TRTY_DESC, S.SUP_COMP
            FROM TRANS T 
            LEFT JOIN TRTY TY ON T.TRTY_CODE = TY.TRTY_CODE
            LEFT JOIN SUPPLIER S ON T.SUP_CODE = S.SUP_CODE
            WHERE T.TRAN_ID = ?
        ";
    }
    
    $stmtH = sqlsrv_query($conn, $sqlH, array($editId));
    if ($stmtH !== false) {
        $rh = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
        if ($rh) {
            foreach ($header as $k => $v) if (isset($rh[$k])) $header[$k] = $rh[$k];
            $header["TRAN_DATE"] = fmt_date($header["TRAN_DATE"]);
            
            // Format BC_DATE
            if (isset($header['BC_DATE']) && $header['BC_DATE'] instanceof DateTime) {
                if ($header['BC_DATE']->format('Y') == '1900') {
                    $header['BC_DATE'] = '';
                } else {
                    $header['BC_DATE'] = $header['BC_DATE']->format('Y-m-d');
                }
            } else {
                $header['BC_DATE'] = '';
            }
        }
    }

    $sqlD = "
        SELECT T.IT_LINENO, T.IT_QTY, T.ITEM_ID, 
               COALESCE(NULLIF(I.ITEM_CODE, ''), NULLIF(T.ITEM_CODE, ''), '???') as ITEM_CODE,
               COALESCE(NULLIF(I.ITEM_NAME, ''), NULLIF(T.TRAN_REMARK, ''), '(Barang Tidak Dikenal)') as ITEM_NAME,
               COALESCE(NULLIF(I.ITEM_UNIT, ''), '-') as ITEM_UNIT
        FROM INV_TRAN T 
        LEFT JOIN ITEMS I ON (T.ITEM_ID = I.ITEM_ID OR (T.ITEM_CODE IS NOT NULL AND LTRIM(RTRIM(T.ITEM_CODE)) = LTRIM(RTRIM(I.ITEM_CODE))))
        WHERE T.TRAN_ID = ? 
        ORDER BY T.IT_LINENO ASC
    ";
    $stmtD = sqlsrv_query($conn, $sqlD, array($editId));
    if ($stmtD !== false) {
        while ($rd = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) $details[] = $rd;
    }
} else {
    // Generate new document number if no edit
    $header["TRAN_DOC"] = "TR-" . date('ymd-His'); 
}

/* ======================================================
   MESSAGE
====================================================== */
if (getv("msg", "") == "saved") $message = "Transaksi berhasil disimpan.";
if (getv("msg", "") == "deleted") $message = "Transaksi berhasil dihapus.";

/* ======================================================
   SUPPLIER AUTO COMPLETE & TYPE
====================================================== */
$supplierAuto = array();
$sqlSup = "SELECT SUP_CODE, SUP_COMP FROM dbo.SUPPLIER WHERE ISNULL(SUP_CODE, '') <> '' ORDER BY SUP_COMP ASC";
$stmtSup = sqlsrv_query($conn, $sqlSup);
if ($stmtSup !== false) {
    while ($s = sqlsrv_fetch_array($stmtSup, SQLSRV_FETCH_ASSOC)) {
        $supplierAuto[] = array("SUP_CODE" => trim((string)$s["SUP_CODE"]), "SUP_COMP" => trim((string)$s["SUP_COMP"]));
    }
}

$trtyAuto = array();
$sqlTrty = "SELECT TRTY_CODE, TRTY_DESC FROM dbo.TRTY ORDER BY TRTY_CODE ASC";
$stmtTrty = sqlsrv_query($conn, $sqlTrty);
if ($stmtTrty !== false) {
    while ($t = sqlsrv_fetch_array($stmtTrty, SQLSRV_FETCH_ASSOC)) {
        $trtyAuto[] = array("TRTY_CODE" => trim((string)$t["TRTY_CODE"]), "TRTY_DESC" => trim((string)$t["TRTY_DESC"]));
    }
}

/* ======================================================
   ITEM AUTO COMPLETE
====================================================== */
$itemAuto = array();
$sqlItem = "SELECT TOP 3000 ITEM_ID, ITEM_CODE, ITEM_NAME, ITEM_UNIT FROM dbo.ITEMS WHERE ISNULL(ITEM_CODE, '') <> '' AND ISNULL(ITEM_INACTIVE, 0) = 0 ORDER BY ITEM_CODE";
$stmtItem = sqlsrv_query($conn, $sqlItem);
if ($stmtItem !== false) {
    while ($it = sqlsrv_fetch_array($stmtItem, SQLSRV_FETCH_ASSOC)) {
        $itemAuto[] = array("ITEM_ID" => intval($it["ITEM_ID"]), "ITEM_CODE" => trim((string)$it["ITEM_CODE"]), "ITEM_NAME" => trim((string)$it["ITEM_NAME"]), "ITEM_UNIT" => trim((string)$it["ITEM_UNIT"]));
    }
}

/* ======================================================
   TRANS AUTO COMPLETE
====================================================== */
$tranAuto = array();
$sqlTranAuto = "SELECT TOP 500 T.TRAN_ID, T.TRAN_DOC, T.TRAN_DATE, TY.TRTY_DESC, S.SUP_COMP FROM dbo.TRANS T LEFT JOIN dbo.TRTY TY ON T.TRTY_CODE = TY.TRTY_CODE LEFT JOIN dbo.SUPPLIER S ON T.SUP_CODE = S.SUP_CODE ORDER BY T.TRAN_DATE DESC, T.TRAN_ID DESC";
$stmtTranAuto = sqlsrv_query($conn, $sqlTranAuto);
if ($stmtTranAuto !== false) {
    while ($ta = sqlsrv_fetch_array($stmtTranAuto, SQLSRV_FETCH_ASSOC)) {
        $tranAuto[] = array("TRAN_ID" => intval($ta["TRAN_ID"]), "TRAN_DOC" => trim((string)$ta["TRAN_DOC"]), "TRAN_DATE" => fmt_date_view($ta["TRAN_DATE"]), "TRTY_DESC" => trim((string)$ta["TRTY_DESC"]), "SUP_COMP" => trim((string)$ta["SUP_COMP"]));
    }
}

/* ======================================================
   NAVIGASI (PREV / NEXT)
====================================================== */
$prevID = $nextID = $firstID = $lastID = null;
if ($editId > 0) {
    $qP = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS WHERE TRAN_ID < ? ORDER BY TRAN_ID DESC", array($editId)); if($qP && $r=sqlsrv_fetch_array($qP)) $prevID = $r['TRAN_ID'];
    $qN = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS WHERE TRAN_ID > ? ORDER BY TRAN_ID ASC", array($editId)); if($qN && $r=sqlsrv_fetch_array($qN)) $nextID = $r['TRAN_ID'];
    $qF = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS ORDER BY TRAN_ID ASC"); if($qF && $r=sqlsrv_fetch_array($qF)) $firstID = $r['TRAN_ID'];
    $qL = sqlsrv_query($conn, "SELECT TOP 1 TRAN_ID FROM TRANS ORDER BY TRAN_ID DESC"); if($qL && $r=sqlsrv_fetch_array($qL)) $lastID = $r['TRAN_ID'];
}

if (count($details) == 0) {
    for ($i = 0; $i < 10; $i++) {
        $details[] = array("ITEM_ID" => "", "ITEM_CODE" => "", "ITEM_NAME" => "", "IT_QTY" => "");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transaksi General</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        /* CSS KHUSUS TAMPILAN WINDOWS CLASSIC / DELPHI (Menyesuaikan gambar) */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            background-color: #f0f0f0;
            margin: 0;
            padding: 0;
        }
        
        .main-container {
            width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #c8e6c9; /* Warna Hijau Muda Sesuai Gambar Transaksi General */
        }

        /* SEARCH DIPINDAHKAN KE TITLE BAR */
        .title-bar {
            background-color: #ffffff;
            font-size: 13px;
            font-family: Arial, sans-serif;
            padding: 3px 10px;
            color: #000;
            border-bottom: 1px solid #a0a0a0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .title-left {
            display: flex;
            align-items: center;
        }
        
        .title-bar img {
            height: 16px;
            margin-right: 5px;
        }
        
        .search-area {
            position: relative;
        }

        /* HEADER GREEN AREA */
        .green-header {
            padding: 10px;
            color: #000000;
        }

        .lbl-black {
            color: #000000;
            font-size: 12px;
            margin-bottom: 2px;
            display: block;
            font-weight: bold;
        }

        .input-classic {
            width: 100%;
            height: 22px;
            font-size: 12px;
            padding: 0 4px;
            border: 1px solid #7f9db9;
            box-sizing: border-box;
            background: #fff;
            transition: background-color 0.3s;
        }

        .input-classic:read-only { background-color: #e0e0e0; color: #000; }
        
        select.input-classic { padding: 0; }

        .btn-classic {
            background: #f0f0f0;
            border: 1px solid #888;
            border-bottom-color: #444;
            border-right-color: #444;
            padding: 2px 10px;
            font-size: 14px;
            color: #000;
            cursor: pointer;
            box-shadow: inset 1px 1px 0px #fff;
        }
        .btn-classic:active {
            box-shadow: inset 1px 1px 2px #888;
            border: 1px solid #444;
            border-bottom-color: #888;
            border-right-color: #888;
        }
        .btn-action-wide { width: 75px; margin-right: 2px; }
        .btn-action-side { margin-bottom: 3px; min-width: 150px; font-size: 12px; }

        .fieldset-control {
            border: 1px solid #a0a0a0;
            padding: 8px 10px;
            margin-top: 5px;
            position: relative;
            display: inline-block;
        }
        
        .fieldset-control legend {
            color: #000;
            font-size: 11px;
            background: #c8e6c9;
            padding: 0 4px;
            position: absolute;
            top: -8px;
            left: 5px;
        }

        /* GRID TABLE CLASSIC */
        .datagrid-wrapper {
            flex-grow: 1;
            overflow: auto;
            background: #fff;
            border: 1px solid #a0a0a0;
            margin: 10px;
            width: fit-content;
            min-width: 500px;
        }

        table.datagrid {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }

        table.datagrid th {
            background-color: #ece9d8;
            border-right: 1px solid #c0c0c0;
            border-bottom: 1px solid #c0c0c0;
            font-weight: normal;
            font-size: 11px;
            padding: 2px 4px;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        table.datagrid td {
            border-right: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
            padding: 0;
            font-size: 11px;
        }

        table.datagrid input[type="text"] {
            width: 100%;
            height: 18px;
            border: none;
            padding: 0 4px;
            font-size: 11px;
            background: transparent;
            box-sizing: border-box;
            transition: background-color 0.3s;
        }
        table.datagrid input[type="text"]:focus {
            background: #e7f1ff;
            outline: 1px solid #000;
        }
        table.datagrid tr.detail-selected td {
            background-color: #d0d0d0;
        }

        .footer-bar {
            background: #c8e6c9;
            padding: 5px 10px;
            display: flex;
            align-items: center;
        }

        /* AUTOCOMPLETE STYLING */
        .ac-box {
            position: absolute;
            z-index: 9999;
            background: #ffffff;
            color: #333;
            border: 1px solid #ccc;
            border-radius: 2px;
            max-height: 200px;
            overflow-y: auto;
            min-width: 300px;
            display: none;
            font-size: 12px;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.2);
        }
        .ac-item { padding: 4px 8px; cursor: pointer; border-bottom: 1px solid #f4f4f4; }
        .ac-item:hover, .ac-item.active { background: #808000; color: #ffffff; }
        
        .ac-item-type { padding: 4px 8px; cursor: pointer; border-bottom: 1px solid #f4f4f4; }
        .ac-item-type:hover, .ac-item-type.active { background: #808000; color: #ffffff; }

        /* Bootstrap grid system utilitas (minimalis) */
        .row { display: flex; flex-wrap: wrap; margin-right: -5px; margin-left: -5px; }
        .col-1 { flex: 0 0 8.333%; max-width: 8.333%; padding: 0 5px; }
        .col-2 { flex: 0 0 16.666%; max-width: 16.666%; padding: 0 5px; }
        .col-3 { flex: 0 0 25%; max-width: 25%; padding: 0 5px; }
        .col-4 { flex: 0 0 33.333%; max-width: 33.333%; padding: 0 5px; }
        .col-6 { flex: 0 0 50%; max-width: 50%; padding: 0 5px; }
        .col-8 { flex: 0 0 66.666%; max-width: 66.666%; padding: 0 5px; }
        .col-12 { flex: 0 0 100%; max-width: 100%; padding: 0 5px; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        /* Box Action (Cetak) */
        .action-box {
            border: 2px solid #829cbc;
            padding: 10px;
            background-color: #c8e6c9;
            width: 200px;
        }
    </style>

    <script>
        // Gunakan pengaman tambahan ?: '[]' agar jika json_encode gagal di PHP 5.4, JS tidak akan syntax error
        var supplierData = <?php echo json_encode($supplierAuto) ? json_encode($supplierAuto) : '[]'; ?>;
        var trtyData = <?php echo json_encode($trtyAuto) ? json_encode($trtyAuto) : '[]'; ?>;
        var itemData = <?php echo json_encode($itemAuto) ? json_encode($itemAuto) : '[]'; ?>;
        var tranData = <?php echo json_encode($tranAuto) ? json_encode($tranAuto) : '[]'; ?>;

        var acBox = null;
        var acItems = [];
        var acIndex = -1;
        var acMode = "";
        var acRow = -1;
        var rowSeq = <?php echo count($details); ?>;
        var selectedDetailRow = null;

        function byId(id) { return document.getElementById(id); }
        function setValue(id, value) { var el = byId(id); if (el) el.value = value == null ? "" : value; }

        function initAC() { acBox = document.getElementById("acBox"); }

        function hideAC() {
            if (acBox) { acBox.style.display = "none"; acBox.innerHTML = ""; }
            acItems = []; acIndex = -1; acMode = ""; acRow = -1;
        }

        function positionAC(input) {
            initAC();
            var rect = input.getBoundingClientRect();
            acBox.style.left = (rect.left + (window.scrollX || window.pageXOffset)) + "px";
            acBox.style.top = (rect.bottom + (window.scrollY || window.pageYOffset)) + "px";
            acBox.style.width = rect.width < 300 ? "300px" : rect.width + "px";
        }

        function renderAC(renderText, pickFunc, className) {
            initAC();
            acBox.innerHTML = "";
            var cls = className ? className : "ac-item";
            
            for (var i = 0; i < acItems.length; i++) {
                var div = document.createElement("div");
                div.className = cls + (i == acIndex ? " active" : "");
                div.innerHTML = renderText(acItems[i]);
                div.setAttribute("data-index", i);
                div.onmousedown = function () {
                    var idx = parseInt(this.getAttribute("data-index"), 10);
                    pickFunc(acItems[idx]);
                };
                acBox.appendChild(div);
            }
            acBox.style.display = acItems.length > 0 ? "block" : "none";
        }

        function acMove(step, renderText, pickFunc, className) {
            if (acItems.length <= 0) return;
            acIndex += step;
            if (acIndex < 0) acIndex = acItems.length - 1;
            if (acIndex >= acItems.length) acIndex = 0;
            renderAC(renderText, pickFunc, className);
        }

        function acEnter(pickFunc) {
            if (acItems.length <= 0) return false;
            if (acIndex < 0) acIndex = 0;
            pickFunc(acItems[acIndex]);
            return true;
        }

        function submitSave(allowEmpty) {
            if (byId("allow_empty_detail")) {
                byId("allow_empty_detail").value = allowEmpty ? "1" : "0";
            }
            var form = byId("tranForm");
            if (form) form.submit();
        }
        
        // ==== FUNGSI SIMPAN AJAX (MENGGUNAKAN XMLHTTPREQUEST) ====
        function triggerAjaxSave(inputEl) {
            var form = byId("tranForm");
            
            if(typeof FormData === "undefined" || typeof XMLHttpRequest === "undefined") {
                submitSave(false); 
                return;
            }

            var formData = new FormData(form);
            formData.append("is_ajax", "1"); 
            
            var oldBg = inputEl.style.backgroundColor;
            inputEl.style.backgroundColor = "#fff9c4"; 
            
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "?page=transaksi&ajax=1", true);
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            if (data.status === "success") {
                                inputEl.style.backgroundColor = "#c8e6c9"; 
                                setTimeout(function(){ inputEl.style.backgroundColor = oldBg; }, 1000);
                                
                                var currId = byId("tran_id").value;
                                if (currId === "" || currId === "0") {
                                    byId("tran_id").value = data.tran_id;
                                    if (window.history && window.history.replaceState) {
                                        window.history.replaceState(null, null, "?page=transaksi&id=" + data.tran_id);
                                    }
                                }
                            } else {
                                alert("Gagal Simpan:\n" + data.message);
                                inputEl.style.backgroundColor = "#ffcdd2"; 
                            }
                        } catch (e) {
                            console.error("AJAX Error: ", e);
                            console.error("Raw Response: ", xhr.responseText);
                            alert("Gagal membaca respon server. (Cek console log/F12)");
                            inputEl.style.backgroundColor = "#ffcdd2";
                        }
                    } else {
                        alert("Error saat menghubungi server.");
                        inputEl.style.backgroundColor = "#ffcdd2";
                    }
                }
            };
            xhr.send(formData);
        }

        // ==========================================================
        // FUNGSI BANTUAN UNTUK MENCARI BARIS BARU / PINDAH FOKUS
        // ==========================================================
        function focusNextRow(input) {
            var tbody = byId("detailBody");
            if (!tbody) return;
            var rows = tbody.getElementsByTagName("tr");
            var currentRowIndex = -1;
            
            var el = input;
            while(el && el.nodeName !== "TR") {
                el = el.parentNode;
            }
            
            if (el) {
                for(var i = 0; i < rows.length; i++) {
                    if(rows[i] === el) {
                        currentRowIndex = i;
                        break;
                    }
                }
            }

            if (currentRowIndex === -1) return;

            // Jika ini baris terakhir, buat baris baru
            if (currentRowIndex === rows.length - 1) {
                addRow();
            } else {
                // Jika bukan baris terakhir, pindah fokus ke baris bawahnya
                var nextRow = rows[currentRowIndex + 1];
                var inputs = nextRow.getElementsByTagName("input");
                for(var j=0; j < inputs.length; j++){
                    if(inputs[j].name === "item_code[]") {
                        inputs[j].focus();
                        inputs[j].select();
                        break;
                    }
                }
            }
        }

        // Mencegah form tersubmit biasa, lalu simpan ajax dan pindah baris
        function fieldEnterSave(e, inputEl) {
            if (e.key === "Enter") {
                e.preventDefault();
                triggerAjaxSave(inputEl);
                focusNextRow(inputEl);
                return false; 
            }
        }
        // ==========================================================

        function newData() { window.location.href = "?page=transaksi"; }
        function goEdit(id) { if(!id) return; window.location.href = "?page=transaksi&id=" + encodeURIComponent(id); }
        function confirmDelete() { return confirm("Yakin hapus transaksi ini?"); }

        function deleteCurrent() {
            var id = byId("tran_id").value;
            if (id == "" || id == "0") { alert("Pilih transaksi dulu."); return; }
            if (!confirmDelete()) return;
            byId("delete_tran_id").value = id;
            byId("deleteForm").submit();
        }
        
        function deleteRow(btn) {
            var tr = btn.parentNode ? btn.parentNode.parentNode : null;
            if (!confirm("Hapus baris detail ini?")) return;
            if(tr) tr.parentNode.removeChild(tr);
            renumberRows();
            var trId = byId("tran_id").value;
            if (trId != "" && trId != "0") submitSave(true);
        }

        function selectDetailRow(tr){
            var rows = document.querySelectorAll("#detailBody tr");
            for(var i = 0; i < rows.length; i++) rows[i].className = rows[i].className.replace(" detail-selected", "");
            selectedDetailRow = tr;
            if(tr) tr.className += " detail-selected";
        }

        // Supplier AutoComplete
        function showSupplierAC(input) {
            initAC();
            var key = (input.value || "").toUpperCase();
            acMode = "supplier"; acItems = []; acIndex = -1;

            if (key.length < 1) { hideAC(); return; }
            for (var i = 0; i < supplierData.length; i++) {
                var s = supplierData[i];
                var text = (s.SUP_CODE || "") + " " + (s.SUP_COMP || "");
                if (text.toUpperCase().indexOf(key) >= 0) acItems.push(s);
                if (acItems.length >= 30) break;
            }

            positionAC(input);
            renderAC(function (s) { return "<b>" + s.SUP_CODE + "</b> - " + s.SUP_COMP; }, pickSupplier);
        }

        function supplierKey(e, input) {
            if (e.key === "ArrowDown") {
                e.preventDefault();
                if (acMode !== "supplier" || acItems.length == 0) showSupplierAC(input);
                acMove(1, function (s) { return "<b>" + s.SUP_CODE + "</b> - " + s.SUP_COMP; }, pickSupplier);
                return false;
            }
            if (e.key === "ArrowUp") {
                e.preventDefault();
                if (acMode !== "supplier" || acItems.length == 0) showSupplierAC(input);
                acMove(-1, function (s) { return "<b>" + s.SUP_CODE + "</b> - " + s.SUP_COMP; }, pickSupplier);
                return false;
            }
            if (e.key === "Enter") {
                if (acMode === "supplier" && acItems.length > 0) {
                    e.preventDefault(); acEnter(pickSupplier); return false;
                }
                e.preventDefault(); submitSave(false); return false;
            }
            if (e.key === "Escape") hideAC();
        }

        function pickSupplier(s) {
            setValue("sup_code", s.SUP_CODE);
            setValue("sup_comp", s.SUP_COMP);
            hideAC();
            var trandate = byId("tran_date");
            if (trandate) { trandate.focus(); }
        }

        // Transaction Type AutoComplete
        function showTrtyAC(input) {
            initAC();
            var key = (input.value || "").toUpperCase();
            acMode = "trty"; acItems = []; acIndex = -1;

            if (key.length < 1) { hideAC(); return; }
            for (var i = 0; i < trtyData.length; i++) {
                var t = trtyData[i];
                var text = (t.TRTY_CODE || "") + " " + (t.TRTY_DESC || "");
                if (text.toUpperCase().indexOf(key) >= 0) acItems.push(t);
                if (acItems.length >= 30) break;
            }

            positionAC(input);
            renderAC(function (t) { return "<b>" + t.TRTY_CODE + "</b> - " + t.TRTY_DESC; }, pickTrty, "ac-item-type");
        }

        function trtyKey(e, input) {
            if (e.key === "ArrowDown") {
                e.preventDefault();
                if (acMode !== "trty" || acItems.length == 0) showTrtyAC(input);
                acMove(1, function (t) { return "<b>" + t.TRTY_CODE + "</b> - " + t.TRTY_DESC; }, pickTrty, "ac-item-type");
                return false;
            }
            if (e.key === "ArrowUp") {
                e.preventDefault();
                if (acMode !== "trty" || acItems.length == 0) showTrtyAC(input);
                acMove(-1, function (t) { return "<b>" + t.TRTY_CODE + "</b> - " + t.TRTY_DESC; }, pickTrty, "ac-item-type");
                return false;
            }
            if (e.key === "Enter") {
                if (acMode === "trty" && acItems.length > 0) {
                    e.preventDefault(); acEnter(pickTrty); return false;
                }
                e.preventDefault(); submitSave(false); return false;
            }
            if (e.key === "Escape") hideAC();
        }

        function pickTrty(t) {
            setValue("trty_code", t.TRTY_CODE);
            setValue("trty_desc", t.TRTY_DESC);
            hideAC();
            var supCode = byId("sup_code");
            if (supCode) { supCode.focus(); supCode.select(); }
        }

        function setRowItem(row, item) {
            setValue("item_id_" + row, item.ITEM_ID || "");
            setValue("item_code_" + row, item.ITEM_CODE || "");
            setValue("item_name_" + row, item.ITEM_NAME || "");
        }

        function showItemAC(input, row) {
            initAC();
            var key = (input.value || "").toUpperCase();
            acMode = "item"; acRow = row; acItems = []; acIndex = -1;

            if (key.length < 1) { hideAC(); return; }

            for (var i = 0; i < itemData.length; i++) {
                var item = itemData[i];
                var code = (item.ITEM_CODE || "").toUpperCase();
                var name = (item.ITEM_NAME || "").toUpperCase();
                if (code.indexOf(key) >= 0 || name.indexOf(key) >= 0) acItems.push(item);
                if (acItems.length >= 40) break;
            }

            positionAC(input);
            renderAC(function (item) { return "<b>" + item.ITEM_CODE + "</b> - " + item.ITEM_NAME; }, pickItem);
        }

        function itemKey(e, input, row) {
            if (e.key === "ArrowDown") {
                e.preventDefault();
                if (acMode !== "item" || acItems.length == 0) showItemAC(input, row);
                acMove(1, function (item) { return "<b>" + item.ITEM_CODE + "</b> - " + item.ITEM_NAME; }, pickItem);
                return false;
            }
            if (e.key === "ArrowUp") {
                e.preventDefault();
                if (acMode !== "item" || acItems.length == 0) showItemAC(input, row);
                acMove(-1, function (item) { return "<b>" + item.ITEM_CODE + "</b> - " + item.ITEM_NAME; }, pickItem);
                return false;
            }
            if (e.key === "Enter") {
                if (acMode === "item" && acItems.length > 0) { e.preventDefault(); acEnter(pickItem); return false; }
                e.preventDefault(); return false;
            }
            if (e.key === "Escape") hideAC();
        }

        function pickItem(item) {
            var row = acRow;
            setRowItem(row, item); hideAC();
            var qty = byId("it_qty_" + row);
            if (qty) { qty.focus(); qty.select(); }
        }

        function headerKey(e) {
            if (e.key === "Enter") {
                if (acMode !== "" && acItems.length > 0) return true;
                e.preventDefault(); submitSave(false); return false;
            }
        }
        
        function addRow() {
            var tbody = byId("detailBody");
            var row = rowSeq;
            rowSeq++;
            var tr = document.createElement("tr");
            tr.onclick = function(){ selectDetailRow(this); };

            tr.innerHTML =
                '<td style="width:25px;" class="text-center align-middle row-no"></td>' +
                '<td style="width:25px;" class="text-center align-middle">' + row + '</td>' +
                '<td style="width:120px;">' +
                    '<input type="hidden" name="item_id[]" id="item_id_' + row + '">' +
                    '<input type="text" name="item_code[]" id="item_code_' + row + '" autocomplete="off" oninput="showItemAC(this, ' + row + ')" onkeydown="itemKey(event, this, ' + row + ')">' +
                '</td>' +
                '<td><input type="text" name="item_name[]" id="item_name_' + row + '" readonly></td>' +
                '<td style="width:90px;"><input type="text" class="text-right" name="it_qty[]" id="it_qty_' + row + '" onkeydown="fieldEnterSave(event, this)"></td>' +
                '<td class="text-center" style="width:40px;">' +
                    '<button type="button" class="btn-classic" style="padding:0 5px;" onclick="deleteRow(this)" title="Delete">X</button>' +
                '</td>';

            tbody.appendChild(tr);
            renumberRows();
            selectDetailRow(tr);
            byId("item_code_" + row).focus();
        }

        function renumberRows() {
            var rows = byId("detailBody").getElementsByTagName("tr");
            for (var i = 0; i < rows.length; i++) {
                var noCell = rows[i].getElementsByClassName("row-no")[0];
                if (noCell) noCell.innerHTML = (i==0) ? "<i class='fas fa-caret-right'></i>" : "";
            }
        }

        function showTranAC(input) {
            initAC();
            var key = (input.value || "").toUpperCase();
            acMode = "tran"; acItems = []; acIndex = -1;

            if (key.length < 1) { hideAC(); return; }

            for (var i = 0; i < tranData.length; i++) {
                var t = tranData[i];
                var text = (t.TRAN_DOC || "") + " " + (t.TRAN_DATE || "") + " " + (t.SUP_COMP || "") + " " + (t.TRTY_DESC || "");
                if (text.toUpperCase().indexOf(key) >= 0) acItems.push(t);
                if (acItems.length >= 30) break;
            }

            positionAC(input);
            renderAC(function (t) {
                return "<b>" + t.TRAN_DOC + "</b> - " + t.TRAN_DATE + "<br><small style='color:#666;'>" + t.SUP_COMP + " | " + t.TRTY_DESC + "</small>";
            }, pickTran);
        }

        function tranSearchKey(e, input) {
            if (e.key === "ArrowDown") {
                e.preventDefault();
                if (acMode !== "tran" || acItems.length == 0) showTranAC(input);
                acMove(1, function (t) { return "<b>" + t.TRAN_DOC + "</b> - " + t.TRAN_DATE + "<br><small style='color:#666;'>" + t.SUP_COMP + " | " + t.TRTY_DESC + "</small>"; }, pickTran);
                return false;
            }
            if (e.key === "ArrowUp") {
                e.preventDefault();
                if (acMode !== "tran" || acItems.length == 0) showTranAC(input);
                acMove(-1, function (t) { return "<b>" + t.TRAN_DOC + "</b> - " + t.TRAN_DATE + "<br><small style='color:#666;'>" + t.SUP_COMP + " | " + t.TRTY_DESC + "</small>"; }, pickTran);
                return false;
            }
            if (e.key === "Enter") {
                if (acMode === "tran" && acItems.length > 0) { e.preventDefault(); acEnter(pickTran); return false; }
                return true;
            }
            if (e.key === "Escape") hideAC();
        }

        function pickTran(t) {
            hideAC();
            window.location.href = "?page=transaksi&id=" + encodeURIComponent(t.TRAN_ID);
        }

        // Functions for cetak
        function cetakIclOtomatis() {
            var trId = byId('tran_id').value;
            if(!trId) { alert("Pilih dokumen terlebih dahulu!"); return; }
            window.open("print_icl.php?id=" + encodeURIComponent(trId), "CETAK_ICL", "width=900,height=700,scrollbars=yes");
        }
        
        function cetakMatSlip() {
            var trId = byId('tran_id').value;
            if(!trId) { alert("Pilih dokumen terlebih dahulu!"); return; }
            window.open("print_spb.php?id=" + encodeURIComponent(trId) + "&type=MAT", "CETAK_MAT", "width=900,height=700,scrollbars=yes");
        }
        
        function cetakPartSlip() {
            var trId = byId('tran_id').value;
            var trty = byId('trty_code').value;
            if(!trId) { alert("Pilih dokumen terlebih dahulu!"); return; }
            
            var allowTrty = ['03', '04', '08', '09', '96', '97'];
            if(allowTrty.indexOf(trty) === -1) {
                alert("Part Slip hanya untuk Tipe Transaksi: 03, 04, 08, 09, 96, 97");
                return;
            }
            
            window.open("print_slip_physical.php?id=" + encodeURIComponent(trId), "CETAK_PART", "width=900,height=700,scrollbars=yes");
        }
        
        function cetakSpb() {
            var trId = byId('tran_id').value;
            if(!trId) { alert("Pilih dokumen terlebih dahulu!"); return; }
            window.open("print_spb.php?id=" + encodeURIComponent(trId) + "&type=SPB", "CETAK_SPB", "width=900,height=700,scrollbars=yes");
        }

        // Cross-browser DOMContentLoaded alternative
        function ready(fn) {
            if (document.readyState != 'loading'){
                fn();
            } else if (document.addEventListener) {
                document.addEventListener('DOMContentLoaded', fn);
            } else {
                document.attachEvent('onreadystatechange', function() {
                    if (document.readyState != 'loading') fn();
                });
            }
        }

        ready(function() {
            var rows = document.getElementById("detailBody").getElementsByTagName("tr");
            for(var i=0; i<rows.length; i++){
                rows[i].onclick = function(){ selectDetailRow(this); };
            }
            renumberRows();
        });

        if(document.addEventListener) {
            document.addEventListener("click", function (e) {
                initAC();
                if (acBox && !acBox.contains(e.target)) {
                    if (!e.target || !e.target.getAttribute || e.target.getAttribute("autocomplete") !== "off") hideAC();
                }
            });
        } else {
            document.attachEvent("onclick", function (e) {
                var target = e.srcElement || e.target;
                initAC();
                if (acBox && !acBox.contains(target)) {
                    if (!target || !target.getAttribute || target.getAttribute("autocomplete") !== "off") hideAC();
                }
            });
        }
    </script>
</head>
<body>

<div class="main-container">
    <!-- Title Bar -->
    <div class="title-bar">
        <div class="title-left">
            <img src="https://img.icons8.com/color/48/000000/ms-excel.png" alt="icon"> Transaksi General
        </div>
        
        <!-- KOLOM SEARCH DIPINDAH KE SINI -->
        <div class="search-area">
            <input type="text" name="q" class="input-classic" style="width:250px;" value="<?php echo h($q); ?>" placeholder="Search Document..." autocomplete="off" oninput="showTranAC(this)" onkeydown="tranSearchKey(event, this)">
        </div>
    </div>

    <?php if ($message != "") { echo "<div style='background:#d4edda; color:#155724; padding:5px; text-align:center; font-size:12px;'>$message</div>"; } ?>
    <?php if ($error != "") { echo "<div style='background:#f8d7da; color:#721c24; padding:5px; text-align:center; font-size:12px;'>$error</div>"; } ?>

    <form id="tranForm" method="post" action="?page=transaksi" style="display: flex; flex-direction: column; flex-grow: 1;">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="tran_id" id="tran_id" value="<?php echo h($header["TRAN_ID"]); ?>">
        <input type="hidden" name="allow_empty_detail" id="allow_empty_detail" value="0">

        <!-- HEADER GREEN AREA -->
        <div class="green-header">
            <div class="row">
                <div class="col-8">
                    <!-- Baris 1: Doc No & Type -->
                    <div class="row">
                        <div class="col-4">
                            <label class="lbl-black">Internal Document</label>
                            <input type="text" class="input-classic" name="tran_doc" id="tran_doc" value="<?php echo h($header["TRAN_DOC"]); ?>" onkeydown="headerKey(event)">
                        </div>
                        <div class="col-2">
                            <label class="lbl-black">Type</label>
                            <input type="text" class="input-classic" name="trty_code" id="trty_code" value="<?php echo h($header["TRTY_CODE"]); ?>" autocomplete="off" oninput="showTrtyAC(this)" onkeydown="trtyKey(event, this)">
                        </div>
                        <div class="col-6">
                            <label class="lbl-black">Type Description</label>
                            <input type="text" class="input-classic bg-light" name="trty_desc" id="trty_desc" value="<?php echo h($header["TRTY_DESC"]); ?>" readonly>
                        </div>
                    </div>
                    
                    <!-- Baris 2: Supplier -->
                    <div class="row mt-1">
                        <div class="col-12">
                            <label class="lbl-black">Supplier</label>
                            <!-- Kita sembunyikan code untuk tampilan bersih dan kirim ke hidden -->
                            <input type="hidden" name="sup_code" id="sup_code" value="<?php echo h($header["SUP_CODE"]); ?>">
                            <input type="text" class="input-classic" name="sup_comp" id="sup_comp" value="<?php echo h($header["SUP_COMP"]); ?>" autocomplete="off" oninput="showSupplierAC(this)" onkeydown="supplierKey(event, this)">
                        </div>
                    </div>

                    <!-- Baris 3: External Doc, Date, T Date -->
                    <div class="row mt-1">
                        <div class="col-4">
                            <label class="lbl-black">External Document</label>
                            <input type="text" class="input-classic bg-light" readonly>
                        </div>
                        <div class="col-4">
                            <label class="lbl-black">Input Date</label>
                            <input type="date" class="input-classic" name="tran_date" id="tran_date" value="<?php echo h($header["TRAN_DATE"]); ?>" onkeydown="headerKey(event)">
                        </div>
                        <div class="col-4">
                            <label class="lbl-black">Transaction Date</label>
                            <input type="date" class="input-classic" name="bc_date" id="bc_date" value="<?php echo h($header["BC_DATE"] != '' ? $header["BC_DATE"] : date('Y-m-d')); ?>" onkeydown="headerKey(event)">
                        </div>
                    </div>
                    
                    <!-- Baris 4: Remark -->
                    <div class="row mt-1">
                        <div class="col-4">
                            <label class="lbl-black">TRAN_REM</label>
                            <input type="text" class="input-classic" name="tran_rem" id="tran_rem" value="<?php echo h($header["TRAN_REM"]); ?>" onkeydown="headerKey(event)">
                        </div>
                        
                        <div class="col-8 d-flex align-items-end" style="padding-bottom: 2px;">
                            <div style="background: #f0f0f0; border: 1px solid #a0a0a0; padding: 2px 10px; display:inline-block; border-radius:3px;">
                                <i class="fas fa-step-backward" style="cursor:pointer;" onclick="window.location.href='?page=transaksi&id=<?php echo $firstID; ?>'"></i> &nbsp;
                                <i class="fas fa-caret-left" style="cursor:pointer;" onclick="window.location.href='?page=transaksi&id=<?php echo $prevID; ?>'"></i> &nbsp;
                                <i class="fas fa-caret-right" style="cursor:pointer;" onclick="window.location.href='?page=transaksi&id=<?php echo $nextID; ?>'"></i> &nbsp;
                                <i class="fas fa-step-forward" style="cursor:pointer;" onclick="window.location.href='?page=transaksi&id=<?php echo $lastID; ?>'"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="row mt-2">
                        <div class="col-12">
                            <fieldset class="fieldset-control">
                                <legend>Control</legend>
                                <button type="button" class="btn-classic btn-action-wide" onclick="newData()">Tambah</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="goEdit('<?php echo h($editId); ?>')">Rubah</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="deleteCurrent()">Hapus</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="submitSave(false)">Simpan</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="newData()">Batal</button>
                                <button type="button" class="btn-classic btn-action-wide" onclick="window.location.href='?page=transaksi'">Refresh</button>
                            </fieldset>
                        </div>
                    </div>
                </div>

                <!-- Kanan: Tombol Cetak Dokumen -->
                <div class="col-4">
                    <div style="display:flex; flex-direction:column; align-items:flex-end;">
                        <div class="action-box">
                            <label class="lbl-black text-center mb-1" style="font-weight:bold;">CETAK DATA</label>
                            <button type="button" class="btn-classic btn-action-side w-100" onclick="alert('Belum ada format')">CETAK ICL</button>
                            <button type="button" class="btn-classic btn-action-side w-100" onclick="cetakIclOtomatis()">ICL OTOMATIS</button>
                            <button type="button" class="btn-classic btn-action-side w-100" onclick="cetakPartSlip()">PART SLIP</button>
                            <button type="button" class="btn-classic btn-action-side w-100" onclick="cetakMatSlip()">MAT SLIP</button>
                            <button type="button" class="btn-classic btn-action-side w-100" onclick="cetakSpb()">SPB</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- GRID DATA AREA -->
        <div class="datagrid-wrapper">
            <table class="datagrid" id="detailTable">
                <thead>
                    <tr>
                        <th style="width:25px;" class="text-center"></th>
                        <th style="width:25px;" class="text-center">###</th>
                        <th style="width:120px;">ITEM_CODE</th>
                        <th style="width:300px;">ITEM_NAME</th>
                        <th style="width:90px;" class="text-right">QTY</th>
                        <th style="width:40px;" class="text-center">ACT</th>
                    </tr>
                </thead>
                <tbody id="detailBody">
                    <?php for ($i = 0; $i < count($details); $i++) { 
                        $d = $details[$i];
                        $qty = isset($d["IT_QTY"]) ? floatval($d["IT_QTY"]) : 0;
                        $lineNo = isset($d["IT_LINENO"]) ? intval($d["IT_LINENO"]) : ($i + 1);
                    ?>
                        <tr>
                            <td class="text-center align-middle row-no" style="width:25px;"></td>
                            <td class="text-center align-middle" style="width:25px;"><?php echo $lineNo; ?></td>
                            <td style="width:120px;">
                                <input type="hidden" name="item_id[]" id="item_id_<?php echo h($i); ?>" value="<?php echo h($d["ITEM_ID"]); ?>">
                                <input type="text" name="item_code[]" id="item_code_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_CODE"]) ? $d["ITEM_CODE"] : ""); ?>" autocomplete="off" oninput="showItemAC(this, <?php echo h($i); ?>)" onkeydown="itemKey(event, this, <?php echo h($i); ?>)">
                            </td>
                            <td>
                                <input type="text" name="item_name[]" id="item_name_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_NAME"]) ? $d["ITEM_NAME"] : ""); ?>" readonly>
                            </td>
                            <td style="width:90px;">
                                <input type="text" class="text-right" name="it_qty[]" id="it_qty_<?php echo h($i); ?>" value="<?php echo h($qty == 0 ? "" : $qty); ?>" onkeydown="fieldEnterSave(event, this)">
                            </td>
                            <td class="text-center" style="width:40px;">
                                <button type="button" class="btn-classic" style="padding:0 5px;" onclick="deleteRow(this)" title="Delete">X</button>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <!-- FOOTER BAR / NAV Bawah -->
        <div class="footer-bar">
            <div>
                <i class="fas fa-step-backward" style="cursor:pointer;" onclick="window.location.href='?page=transaksi&id=<?php echo $firstID; ?>'"></i> &nbsp;
                <i class="fas fa-backward" style="cursor:pointer;" onclick="window.location.href='?page=transaksi&id=<?php echo $prevID; ?>'"></i> &nbsp;
                <i class="fas fa-caret-left" style="cursor:pointer;" onclick="window.location.href='?page=transaksi&id=<?php echo $prevID; ?>'"></i> &nbsp;
                <i class="fas fa-play" style="cursor:pointer;"></i> &nbsp;
                <i class="fas fa-forward" style="cursor:pointer;" onclick="window.location.href='?page=transaksi&id=<?php echo $nextID; ?>'"></i> &nbsp;
                <i class="fas fa-step-forward" style="cursor:pointer;" onclick="window.location.href='?page=transaksi&id=<?php echo $lastID; ?>'"></i> &nbsp;&nbsp;&nbsp;
                <i class="fas fa-plus" style="cursor:pointer;" onclick="addRow()"></i> &nbsp;
                <i class="fas fa-check" style="cursor:pointer;" onclick="submitSave(false)"></i> &nbsp;
                <i class="fas fa-times" style="cursor:pointer;" onclick="newData()"></i> &nbsp;
                <i class="fas fa-redo" style="cursor:pointer;" onclick="window.location.href='?page=transaksi'"></i>
            </div>
        </div>
    </form>

    <form id="deleteForm" method="post" action="?page=transaksi" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="tran_id" id="delete_tran_id" value="">
    </form>
</div>

<div id="acBox" class="ac-box"></div>

</body>
</html>
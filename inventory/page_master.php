<?php
if (session_id() == "") {
    session_start();
}

require_once __DIR__ . '/../config/database_p1.php';

if ($conn === false) {
    die("Koneksi database gagal.");
}

// ==================================================================================
// FUNGSI HELPER 
// ==================================================================================
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
function getv($n, $d="") { return isset($_GET[$n]) ? trim((string)$_GET[$n]) : $d; }
function postv($n, $d="") { return isset($_POST[$n]) ? trim((string)$_POST[$n]) : $d; }
function sql_error_text() { return print_r(sqlsrv_errors(), true); }
function to_float($v) { $v = trim((string)$v); return $v==="" ? 0 : floatval(str_replace(",", "", $v)); }

// ==================================================================================
// CEK AKTIF PLANT & HAK AKSES
// ==================================================================================
$isPlant1 = true;
if (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2') {
    $isPlant1 = false;
}

$TABEL_BC = "BC_TRANS"; 

$current_user = isset($_SESSION['db_user']) ? strtolower(trim($_SESSION['db_user'])) : '';
$is_IT_Dept = ($current_user === 'admin' || $current_user === 'it' || $current_user === 'plan1' || $current_user === 'plan2') ? true : false;

// ==================================================================================
// BAGIAN 1: PROSES DATA (POST) - SIMPAN, UPDATE, HAPUS
// ==================================================================================

$isAjax = (isset($_GET['ajax']) && $_GET['ajax'] == '1') || (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1');

// A. HAPUS BARANG (-)
if (isset($_POST['action']) && $_POST['action'] == 'delete') {
    if (!$is_IT_Dept) {
        if($isAjax){
            echo json_encode(["status" => "error", "message" => "AKSES DITOLAK! Anda tidak memiliki izin untuk menghapus Item."]); exit;
        } else {
            echo "<script>alert('AKSES DITOLAK! Anda tidak memiliki izin untuk menghapus Item.'); window.location.href='?page=master';</script>"; exit;
        }
    }

    $idToDelete = postv('hapus_id');
    if ($idToDelete) {
        $cekTrans = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM INV_TRAN WHERE ITEM_CODE=?", array($idToDelete));
        $isUsedTrans = ($cekTrans && sqlsrv_fetch_array($cekTrans));

        $cekTags = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM TAGS WHERE ITEM_CODE=?", array($idToDelete));
        $isUsedTags = ($cekTags && sqlsrv_fetch_array($cekTags));

        if ($isUsedTrans || $isUsedTags) {
            $err_msg = "GAGAL MENGHAPUS: Barang tidak bisa dihapus karena sudah dipakai dalam data Transaksi atau Stock Opname!";
            if($isAjax){ echo json_encode(["status" => "error", "message" => $err_msg]); exit; }
            else { echo "<script>alert('$err_msg'); window.location.href='?page=master&id=".urlencode($idToDelete)."';</script>"; exit;}
        } else {
            $sqlDel = "DELETE FROM ITEMS WHERE ITEM_CODE = ?";
            $stmtDel = sqlsrv_query($conn, $sqlDel, array($idToDelete));
            
            if ($stmtDel) {
                if($isAjax){ echo json_encode(["status" => "success"]); exit; }
                else { echo "<script>alert('Barang {$idToDelete} Berhasil Dihapus secara permanen!'); window.location.href='?page=master';</script>"; exit;}
            } else {
                if($isAjax){ echo json_encode(["status" => "error", "message" => "Gagal Hapus (Database Error)"]); exit; }
                else { echo "<script>alert('Gagal Hapus (Database Error)'); window.location.href='?page=master&id=".urlencode($idToDelete)."';</script>"; exit;}
            }
        }
    }
}

// B. SIMPAN DATA (BARU / UPDATE)
if (isset($_POST['action']) && $_POST['action'] == 'save') {
    $editIdParam = postv('hapus_id');
    $isUpdate = ($editIdParam != "");
    
    if ($isUpdate && !$is_IT_Dept) {
        if($isAjax){ echo json_encode(["status" => "error", "message" => "AKSES DITOLAK! Anda tidak memiliki izin untuk mengedit Item."]); exit; }
        else { echo "<script>alert('AKSES DITOLAK! Anda tidak memiliki izin untuk mengedit Item.'); window.location.href='?page=master';</script>"; exit; }
    }
    
    $code = postv('ITEM_CODE');
    $name = postv('ITEM_NAME');
    $unit = postv('ITEM_UNIT');
    $cost = to_float(postv('ITEM_COST'));
    $curr = postv('ITEM_CUR');
    $itty = postv('ITTY_CODE');
    $itemNo = postv('ITEM_NO');
    
    $inactive = isset($_POST['ITEM_INACTIVE']) ? 1 : 0;
    $forsale  = isset($_POST['ITEM_FORSALE']) ? 1 : 0;
    $inv      = isset($_POST['ITEM_INV']) ? 1 : 0;

    if (empty($code) || empty($name)) {
        if($isAjax){ echo json_encode(["status" => "error", "message" => "Kode dan Nama Barang wajib diisi!"]); exit; }
        else { echo "<script>alert('Kode dan Nama Barang wajib diisi!'); window.history.back();</script>"; exit; }
    } else {
        $params = null;
        if ($isUpdate) {
            $sql = "UPDATE ITEMS SET ITEM_NAME=?, ITEM_UNIT=?, ITEM_COST=?, ITEM_CUR=?, ITTY_CODE=?, 
                    ITEM_INACTIVE=?, ITEM_FORSALE=?, ITEM_INV=?, ITEM_NO=? 
                    WHERE ITEM_CODE=?";
            $params = array($name, $unit, $cost, $curr, $itty, $inactive, $forsale, $inv, $itemNo, $code);
        } else {
            $cek = sqlsrv_query($conn, "SELECT ITEM_CODE FROM ITEMS WHERE ITEM_CODE=?", array($code));
            if($cek && sqlsrv_has_rows($cek)){
                if($isAjax){ echo json_encode(["status" => "error", "message" => "Gagal! Kode Barang $code sudah ada."]); exit; }
                else { echo "<script>alert('Gagal! Kode Barang $code sudah ada.'); window.history.back();</script>"; exit; }
            } else {
                $sql = "INSERT INTO ITEMS (ITEM_NAME, ITEM_UNIT, ITEM_COST, ITEM_CUR, ITTY_CODE, 
                        ITEM_INACTIVE, ITEM_FORSALE, ITEM_INV, ITEM_CODE, ITEM_NO) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $params = array($name, $unit, $cost, $curr, $itty, $inactive, $forsale, $inv, $code, $itemNo);
            }
        }

        if ($params) {
            $stmt = sqlsrv_query($conn, $sql, $params);
            if ($stmt) {
                if($isAjax){
                    echo json_encode(["status" => "success", "item_code" => $code]); exit;
                } else {
                    echo "<script>alert('Data Berhasil Disimpan!'); window.location.href='?page=master&id=".urlencode($code)."';</script>"; exit;
                }
            } else {
                if($isAjax){ echo json_encode(["status" => "error", "message" => "Error Database saat menyimpan data."]); exit; }
                else { echo "<script>alert('Error Database saat menyimpan data.'); window.history.back();</script>"; exit; }
            }
        }
    }
}

// ==================================================================================
// BAGIAN 2: LOGIKA NAVIGASI, PAGINATION & VIEW 
// ==================================================================================
$mode = getv('mode', 'view');
$currentID = getv('id');
$isEntry = ($mode == 'new' || $mode == 'edit');

if ($mode === 'edit' && !$is_IT_Dept) {
    echo "<script>alert('AKSES DITOLAK! Silakan hubungi IT Departement untuk mengedit item.'); window.location.href='?page=master';</script>";
    exit;
}

$data = [
    'ITEM_CODE'=>'', 'ITEM_NAME'=>'', 'ITEM_NO'=>'', 'ITEM_UNIT'=>'Pcs', 'ITEM_COST'=>0, 'ITEM_CUR'=>'IDR',
    'ITTY_CODE'=>'RM', 'ITEM_INACTIVE'=>0, 'ITEM_FORSALE'=>1, 'ITEM_INV'=>1, 'ITEM_ONHAND'=>0
];

if ($mode != 'new') {
    if (empty($currentID)) {
        $qFirst = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITEM_CODE IS NOT NULL AND ITEM_CODE <> '' ORDER BY ITEM_CODE ASC");
        if ($qFirst && $rFirst = sqlsrv_fetch_array($qFirst)) {
            $currentID = trim($rFirst['ITEM_CODE']);
        }
    }

    if (!empty($currentID)) {
        $qData = sqlsrv_query($conn, "SELECT * FROM ITEMS WHERE ITEM_CODE = ?", array($currentID));
        if ($qData && $rData = sqlsrv_fetch_array($qData, SQLSRV_FETCH_ASSOC)) {
            $data = $rData;
        }
    }
}

// Data untuk tombol Navigasi
$prevID = $nextID = $firstID = $lastID = null;
if (!$isEntry && !empty($currentID)) {
    $qP = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITEM_CODE < ? AND ITEM_CODE <> '' ORDER BY ITEM_CODE DESC", array($currentID)); 
    if($qP && $r=sqlsrv_fetch_array($qP)) $prevID = trim($r['ITEM_CODE']);
    
    $qN = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITEM_CODE > ? AND ITEM_CODE <> '' ORDER BY ITEM_CODE ASC", array($currentID)); 
    if($qN && $r=sqlsrv_fetch_array($qN)) $nextID = trim($r['ITEM_CODE']);
    
    $qF = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITEM_CODE <> '' ORDER BY ITEM_CODE ASC"); 
    if($qF && $r=sqlsrv_fetch_array($qF)) $firstID = trim($r['ITEM_CODE']);
    
    $qL = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITEM_CODE <> '' ORDER BY ITEM_CODE DESC"); 
    if($qL && $r=sqlsrv_fetch_array($qL)) $lastID = trim($r['ITEM_CODE']);
}

// LOGIKA PAGINATION
$limit = 50; // Tampilkan 50 per halaman
$page = isset($_GET['p']) ? intval($_GET['p']) : 0; // Jika 0, auto-detect halaman berdasarkan ID

// Hitung total seluruh item
$totalItems = 0;
$qCount = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM ITEMS");
if ($qCount && $rCount = sqlsrv_fetch_array($qCount)) {
    $totalItems = $rCount['total'];
}
$totalPages = ceil($totalItems / $limit);
if ($totalPages == 0) $totalPages = 1;

// Jika page tidak diset manual di URL, auto-detect halaman berisi $currentID
if ($page === 0 && !empty($currentID) && !$isEntry) {
    $qRow = sqlsrv_query($conn, "
        WITH CTE AS (
            SELECT ITEM_CODE, ROW_NUMBER() OVER (ORDER BY ITEM_CODE ASC) AS rn 
            FROM ITEMS
        )
        SELECT rn FROM CTE WHERE ITEM_CODE = ?
    ", array($currentID));
    
    if ($qRow && $rRow = sqlsrv_fetch_array($qRow)) {
        $page = ceil($rRow['rn'] / $limit);
    }
}
if ($page <= 0) $page = 1;
if ($page > $totalPages) $page = $totalPages;

// Hitung Offset & Row Limits (Kompatibel SQL Server 2008 & 2012+)
$startRow = (($page - 1) * $limit) + 1;
$endRow   = ($page * $limit);


$optItty = "";
$qItty = sqlsrv_query($conn, "SELECT ITTY_CODE, ITTY_DESC FROM ITTY ORDER BY ITTY_CODE ASC");
if ($qItty) {
    while($r = sqlsrv_fetch_array($qItty)) {
        $sel = (trim($data['ITTY_CODE']) == trim($r['ITTY_CODE'])) ? 'selected' : '';
        $optItty .= "<option value='".h(trim($r['ITTY_CODE']))."' $sel>".h(trim($r['ITTY_DESC']))."</option>";
    }
}

/* ======================================================
   ITEM AUTO COMPLETE UNTUK SEARCH
====================================================== */
$itemAuto = array();
$sqlItem = "SELECT ITEM_ID, ITEM_CODE, ITEM_NAME FROM dbo.ITEMS WHERE ISNULL(ITEM_CODE, '') <> '' ORDER BY ITEM_CODE";
$stmtItem = sqlsrv_query($conn, $sqlItem);
if ($stmtItem !== false) {
    while ($it = sqlsrv_fetch_array($stmtItem, SQLSRV_FETCH_ASSOC)) {
        $itemAuto[] = array("ITEM_ID" => intval($it["ITEM_ID"]), "ITEM_CODE" => trim((string)$it["ITEM_CODE"]), "ITEM_NAME" => trim((string)$it["ITEM_NAME"]));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Master Item</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        /* CSS TAMPILAN WINDOWS CLASSIC / DELPHI */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            background-color: #c4c4c4;
            margin: 0;
            padding: 0;
        }
        
        .main-container {
            width: 100%;
            height: calc(100vh - 60px); 
            display: flex;
            background-color: #c4c4c4;
            padding: 10px;
            box-sizing: border-box;
            gap: 15px;
        }

        .top-search-bar {
            width: 100%;
            display: flex;
            justify-content: flex-end;
            padding: 5px 15px;
            background-color: #c4c4c4;
            box-sizing: border-box;
            position: relative;
            z-index: 10;
        }

        /* AREA KIRI (FORM) */
        .left-panel {
            width: 400px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
        }

        .lbl-black {
            color: #000000;
            font-size: 11px;
            margin-bottom: 1px;
            display: block;
            text-transform: uppercase;
        }

        .input-classic {
            width: 100%;
            height: 20px;
            font-size: 11px;
            padding: 0 4px;
            border: 1px solid #7f9db9; 
            border-bottom-color: #fff;
            border-right-color: #fff;
            box-sizing: border-box;
            background: #fff;
            margin-bottom: 6px;
            transition: background-color 0.3s;
        }

        .input-classic:read-only, .input-classic[disabled] { 
            background-color: #e0e0e0; 
            color: #000; 
        }
        
        select.input-classic { padding: 0; }
        
        .fieldset-control {
            border: 1px solid #a0a0a0;
            padding: 8px 10px;
            margin-top: 10px;
            position: relative;
            display: inline-block;
            background: #c4c4c4;
        }
        
        .fieldset-control legend {
            color: #000;
            font-size: 11px;
            background: #c4c4c4;
            padding: 0 4px;
            position: absolute;
            top: -8px;
            left: 5px;
        }

        .btn-classic {
            background: #f0f0f0;
            border: 1px solid #888;
            border-bottom-color: #444;
            border-right-color: #444;
            padding: 2px 10px;
            font-size: 12px;
            color: #000;
            cursor: pointer;
            box-shadow: inset 1px 1px 0px #fff;
            margin-right: 2px;
            text-decoration: none;
        }
        .btn-classic:active {
            box-shadow: inset 1px 1px 2px #888;
            border: 1px solid #444;
            border-bottom-color: #888;
            border-right-color: #888;
        }
        .btn-classic.disabled {
            color: #a0a0a0;
            pointer-events: none;
            cursor: default;
        }
        .btn-action-wide { width: 70px; }

        .nav-buttons {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
            gap: 2px;
        }
        .btn-classic-icon {
            background: #f0f0f0;
            border: 1px solid #888;
            border-bottom-color: #444;
            border-right-color: #444;
            font-size: 11px;
            color: #000;
            cursor: pointer;
            box-shadow: inset 1px 1px 0px #fff;
            height: 20px;
            min-width: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        .btn-classic-icon:active {
            box-shadow: inset 1px 1px 2px #888;
            border: 1px solid #444;
            border-bottom-color: #888;
            border-right-color: #888;
        }
        .btn-classic-icon.disabled {
            color: #a0a0a0;
            cursor: default;
        }

        .checkbox-group {
            display: flex;
            gap: 10px;
            margin-top: 2px;
            margin-bottom: 6px;
        }
        .checkbox-group label {
            font-size: 11px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
        }
        .checkbox-group input { margin-right: 3px; margin-top: 0; }

        /* AREA KANAN (TABEL & PAGINATION) */
        .right-panel {
            flex-grow: 1;
            display: flex;
            flex-direction: column; /* Flex agar table bisa scroll, pagination diam dibawah */
            background: #fff;
            border: 1px solid #888;
            border-bottom-color: #fff;
            border-right-color: #fff;
            height: calc(100vh - 20px);
        }

        .table-container {
            flex-grow: 1;
            overflow: auto;
        }

        /* PAGINATION UI CONTAINER */
        .pagination-container {
            flex-shrink: 0;
            background: #e0e0e0;
            padding: 6px 10px;
            border-top: 1px solid #a0a0a0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
        }

        table.datagrid {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }

        table.datagrid th {
            background-color: #e0e0e0;
            border-right: 1px solid #c0c0c0;
            border-bottom: 1px solid #c0c0c0;
            font-weight: normal;
            font-size: 11px;
            padding: 3px 5px;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 2;
            text-transform: uppercase;
        }

        table.datagrid td {
            border-right: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
            padding: 2px 4px;
            font-size: 11px;
            cursor: pointer;
        }
        
        table.datagrid tr:hover td {
            background-color: #e7f1ff;
        }

        table.datagrid tr.active td {
            background-color: #000;
            color: #fff;
        }
        
        .row-flex { display: flex; gap: 10px; }
        .flex-1 { flex: 1; }
        .no-select { user-select: none; }

        /* AUTOCOMPLETE STYLING */
        .ac-box {
            position: absolute;
            z-index: 9999;
            background: #ffffff;
            color: #333;
            border: 1px solid #ccc;
            border-radius: 2px;
            max-height: 250px;
            overflow-y: auto;
            min-width: 350px;
            display: none;
            font-size: 12px;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.2);
        }
        .ac-item { padding: 5px 8px; cursor: pointer; border-bottom: 1px solid #f4f4f4; }
        .ac-item:hover, .ac-item.active { background: #808000; color: #ffffff; }
    </style>
    
    <script>
        var itemData = <?php echo json_encode($itemAuto) ? json_encode($itemAuto) : '[]'; ?>;
        
        var acBox = null;
        var acItems = [];
        var acIndex = -1;
        var acMode = "";

        function byId(id) { return document.getElementById(id); }

        function initAC() { acBox = document.getElementById("acBox"); }

        function hideAC() {
            if (acBox) { acBox.style.display = "none"; acBox.innerHTML = ""; }
            acItems = []; acIndex = -1; acMode = "";
        }

        function positionAC(input) {
            initAC();
            var rect = input.getBoundingClientRect();
            acBox.style.left = (rect.left + (window.scrollX || window.pageXOffset) - 150) + "px";
            acBox.style.top = (rect.bottom + (window.scrollY || window.pageYOffset)) + "px";
            acBox.style.width = "350px";
        }

        function renderAC(renderText, pickFunc) {
            initAC();
            acBox.innerHTML = "";
            
            for (var i = 0; i < acItems.length; i++) {
                var div = document.createElement("div");
                div.className = "ac-item" + (i == acIndex ? " active" : "");
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

        function acMove(step, renderText, pickFunc) {
            if (acItems.length <= 0) return;
            acIndex += step;
            if (acIndex < 0) acIndex = acItems.length - 1;
            if (acIndex >= acItems.length) acIndex = 0;
            renderAC(renderText, pickFunc);
        }

        function acEnter(pickFunc) {
            if (acItems.length <= 0) return false;
            if (acIndex < 0) acIndex = 0;
            pickFunc(acItems[acIndex]);
            return true;
        }

        function showItemSearchAC(input) {
            initAC();
            var key = (input.value || "").toUpperCase();
            acMode = "item_search"; acItems = []; acIndex = -1;

            if (key.length < 1) { hideAC(); return; }

            var startsWithMatch = [];
            var containsMatch = [];

            for (var i = 0; i < itemData.length; i++) {
                var item = itemData[i];
                var code = (item.ITEM_CODE || "").toUpperCase();
                var name = (item.ITEM_NAME || "").toUpperCase();
                
                if (code.indexOf(key) === 0) {
                    startsWithMatch.push(item);
                } else if (code.indexOf(key) > 0 || name.indexOf(key) >= 0) {
                    containsMatch.push(item);
                }
            }
            
            acItems = startsWithMatch.concat(containsMatch).slice(0, 150);

            positionAC(input);
            renderAC(function (item) { return "<b>" + item.ITEM_CODE + "</b> - " + item.ITEM_NAME; }, pickItemSearch);
        }

        function itemSearchKey(e, input) {
            if (e.key === "ArrowDown") {
                e.preventDefault();
                if (acMode !== "item_search" || acItems.length == 0) showItemSearchAC(input);
                acMove(1, function (item) { return "<b>" + item.ITEM_CODE + "</b> - " + item.ITEM_NAME; }, pickItemSearch);
                return false;
            }
            if (e.key === "ArrowUp") {
                e.preventDefault();
                if (acMode !== "item_search" || acItems.length == 0) showItemSearchAC(input);
                acMove(-1, function (item) { return "<b>" + item.ITEM_CODE + "</b> - " + item.ITEM_NAME; }, pickItemSearch);
                return false;
            }
            if (e.key === "Enter") {
                if (acMode === "item_search" && acItems.length > 0) { e.preventDefault(); acEnter(pickItemSearch); return false; }
                e.preventDefault(); return false;
            }
            if (e.key === "Escape") hideAC();
        }

        function pickItemSearch(item) {
            hideAC();
            window.location.href = "?page=master&id=" + encodeURIComponent(item.ITEM_CODE);
        }

        function submitSave() {
            var form = byId("masterForm");
            if (form) form.submit();
        }
        
        function triggerAjaxSave(inputEl) {
            var form = byId("masterForm");
            
            if(typeof FormData === "undefined" || typeof XMLHttpRequest === "undefined") {
                submitSave(); 
                return;
            }

            var formData = new FormData(form);
            formData.append("is_ajax", "1"); 
            formData.append("action", "save");
            
            if (byId("hapus_id").value != "") {
                formData.append("btnUpdate", "1");
            } else {
                formData.append("btnSimpan", "1");
            }
            
            var oldBg = inputEl.style.backgroundColor;
            inputEl.style.backgroundColor = "#fff9c4"; 
            
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "?page=master&ajax=1", true);
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            if (data.status === "success") {
                                inputEl.style.backgroundColor = "#c8e6c9"; 
                                setTimeout(function(){ inputEl.style.backgroundColor = oldBg; }, 1000);
                                
                                var currId = byId("hapus_id").value;
                                if (currId === "" || currId === "0") {
                                    byId("hapus_id").value = data.item_code;
                                    if (window.history && window.history.replaceState) {
                                        window.history.replaceState(null, null, "?page=master&id=" + data.item_code);
                                    }
                                }
                            } else {
                                alert("Gagal Simpan:\n" + data.message);
                                inputEl.style.backgroundColor = "#ffcdd2"; 
                            }
                        } catch (e) {
                            alert("Gagal membaca respon server. Pastikan tidak ada karakter aneh.");
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

        function fieldEnterSave(e, inputEl) {
            if (e.key === "Enter") {
                e.preventDefault();
                if(!inputEl.hasAttribute("readonly") && !inputEl.hasAttribute("disabled")) {
                     triggerAjaxSave(inputEl);
                }
                return false; 
            }
        }

        function newData() { window.location.href = "?page=master&mode=new"; }
        function goEdit() { 
            var id = byId("hapus_id").value;
            if(!id) { alert("Pilih Item dulu."); return; }
            window.location.href = "?page=master&mode=edit&id=" + encodeURIComponent(id); 
        }
        function deleteCurrent() {
            var id = byId("hapus_id").value;
            if (id == "" || id == "0") { alert("Pilih item dulu."); return; }
            if (!confirm('Hapus Barang ' + id + ' secara permanen?')) return;
            
            var actionInput = document.createElement("input");
            actionInput.type = "hidden";
            actionInput.name = "action";
            actionInput.value = "delete";
            
            var form = byId("deleteForm");
            form.appendChild(actionInput);
            form.submit();
        }

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
            var activeRow = document.querySelector('.right-panel tr.active');
            if (activeRow) {
                activeRow.scrollIntoView({ behavior: 'instant', block: 'center' });
            }
        });

        if(document.addEventListener) {
            document.addEventListener("click", function (e) {
                initAC();
                if (acBox && !acBox.contains(e.target)) {
                    if (!e.target || !e.target.getAttribute || e.target.getAttribute("autocomplete") !== "off") hideAC();
                }
            });
        }
    </script>
</head>
<body>

<div class="top-search-bar">
    <input type="text" class="input-classic" style="width: 280px; margin: 0; padding: 3px;" placeholder="Search Code/Name..." autocomplete="off" oninput="showItemSearchAC(this)" onkeydown="itemSearchKey(event, this)">
</div>

<div class="main-container">
    
    <!-- PANEL KIRI (FORM ENTRY) -->
    <div class="left-panel">
        <?php if (!$is_IT_Dept && !$isEntry): ?>
            <div style="color:red; font-size:10px; margin-bottom:5px;">Info: Mode Read-Only (Hanya IT Dept yang bisa Edit/Hapus).</div>
        <?php endif; ?>
        
        <form id="masterForm" method="post" action="?page=master">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="hapus_id" id="hapus_id" value="<?php echo h($currentID); ?>">
            
            <div class="row-flex align-items-center">
                <div class="flex-1">
                    <label class="lbl-black">CODE</label>
                    <input type="text" class="input-classic" name="ITEM_CODE" value="<?php echo h($data['ITEM_CODE']); ?>" <?php echo ($mode!='new') ? 'readonly' : 'required'; ?> maxlength="20" onkeydown="fieldEnterSave(event, this)">
                </div>
                
                <div class="checkbox-group" style="padding-top: 15px;">
                    <label><input type="checkbox" name="ITEM_INV" value="1" <?php echo ($data['ITEM_INV']==1)?'checked':''; ?> <?php echo !$isEntry ? 'disabled' : ''; ?>> INV</label>
                    <label><input type="checkbox" name="ITEM_FORSALE" value="1" <?php echo ($data['ITEM_FORSALE']==1)?'checked':''; ?> <?php echo !$isEntry ? 'disabled' : ''; ?>> SALE</label>
                    <label><input type="checkbox" name="ITEM_INACTIVE" value="1" <?php echo ($data['ITEM_INACTIVE']==1)?'checked':''; ?> <?php echo !$isEntry ? 'disabled' : ''; ?>> NOT ACTIVE</label>
                </div>
            </div>

            <label class="lbl-black">NOMOR</label>
            <input type="text" class="input-classic" name="ITEM_NO" value="<?php echo isset($data['ITEM_NO']) ? h($data['ITEM_NO']) : ''; ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="30" onkeydown="fieldEnterSave(event, this)">

            <label class="lbl-black">NAME</label>
            <input type="text" class="input-classic" name="ITEM_NAME" value="<?php echo h($data['ITEM_NAME']); ?>" <?php echo !$isEntry ? 'readonly' : 'required'; ?> onkeydown="fieldEnterSave(event, this)">

            <label class="lbl-black">COLOUR</label>
            <select class="input-classic" disabled><option></option></select> 

            <div class="row-flex">
                <div class="flex-1">
                    <label class="lbl-black">COST</label>
                    <input type="number" step="0.001" class="input-classic text-right" name="ITEM_COST" value="<?php echo h($data['ITEM_COST']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> onkeydown="fieldEnterSave(event, this)">
                </div>
                <div class="flex-1">
                    <label class="lbl-black">CURRENCY</label>
                    <select class="input-classic" name="ITEM_CUR" <?php echo !$isEntry ? 'disabled' : ''; ?>>
                        <option value="IDR" <?php echo ($data['ITEM_CUR']=='IDR')?'selected':''; ?>>IDR</option>
                        <option value="USD" <?php echo ($data['ITEM_CUR']=='USD')?'selected':''; ?>>USD</option>
                        <option value="JPY" <?php echo ($data['ITEM_CUR']=='JPY')?'selected':''; ?>>JPY</option>
                    </select>
                </div>
            </div>

            <div class="row-flex">
                <div class="flex-1">
                    <label class="lbl-black">ITEM UNIT</label>
                    <select class="input-classic" name="ITEM_UNIT" <?php echo !$isEntry ? 'disabled' : ''; ?>>
                        <option value="Pcs" <?php echo (trim($data['ITEM_UNIT'])=='Pcs')?'selected':''; ?>>Pcs</option>
                        <option value="Kg" <?php echo (trim($data['ITEM_UNIT'])=='Kg')?'selected':''; ?>>Kg</option>
                        <option value="Ltr" <?php echo (trim($data['ITEM_UNIT'])=='Ltr')?'selected':''; ?>>Ltr</option>
                        <option value="Set" <?php echo (trim($data['ITEM_UNIT'])=='Set')?'selected':''; ?>>Set</option>
                        <option value="Roll" <?php echo (trim($data['ITEM_UNIT'])=='Roll')?'selected':''; ?>>Roll</option>
                        <option value="Mtr" <?php echo (trim($data['ITEM_UNIT'])=='Mtr')?'selected':''; ?>>Mtr</option>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="lbl-black">TYPE</label>
                    <div class="row-flex" style="gap:2px;">
                        <select class="input-classic" name="ITTY_CODE" <?php echo !$isEntry ? 'disabled' : ''; ?> style="width: 50px;">
                            <?php 
                                $qItty2 = sqlsrv_query($conn, "SELECT ITTY_CODE FROM ITTY ORDER BY ITTY_CODE ASC");
                                if ($qItty2) {
                                    while($r=sqlsrv_fetch_array($qItty2)) {
                                        $sel = (trim($data['ITTY_CODE']) == trim($r['ITTY_CODE'])) ? 'selected' : '';
                                        echo "<option value='".h(trim($r['ITTY_CODE']))."' $sel>".h(trim($r['ITTY_CODE']))."</option>";
                                    }
                                }
                            ?>
                        </select>
                        <select class="input-classic" <?php echo !$isEntry ? 'disabled' : ''; ?>>
                            <?php echo $optItty; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row-flex">
                <div class="flex-1">
                    <label class="lbl-black">ONHAND</label>
                    <input type="text" class="input-classic text-right" value="<?php echo number_format(floatval($data['ITEM_ONHAND']), 0); ?>" readonly style="background:#e0e0e0;">
                </div>
                <div class="flex-1">
                    <label class="lbl-black">STD PACKING</label>
                    <input type="text" class="input-classic text-right" value="0" readonly>
                </div>
            </div>

            <div class="row-flex">
                <div class="flex-1">
                    <label class="lbl-black">SAFETY</label>
                    <input type="text" class="input-classic text-right" value="0" readonly>
                </div>
                <div class="flex-1">
                    <label class="lbl-black">SAFETY MAX</label>
                    <input type="text" class="input-classic text-right" value="" readonly>
                </div>
            </div>
            
            <div class="row-flex">
                <div class="flex-1">
                    <label class="lbl-black">LOCATION CUSTOMER</label>
                    <input type="text" class="input-classic" readonly>
                </div>
                <div class="flex-1">
                    <label class="lbl-black">QTY DEPRESIASI</label>
                    <input type="text" class="input-classic" readonly>
                </div>
            </div>

            <label class="lbl-black">MODEL</label>
            <input type="text" class="input-classic" readonly>

            <label class="lbl-black">SUPPLIER CODE</label>
            <input type="text" class="input-classic" readonly>

            <!-- Navigasi Klasik -->
            <div class="nav-buttons no-select mt-1">
                <a href="?page=master&id=<?php echo urlencode((string)$firstID); ?>" class="btn-classic-icon <?php echo (!$firstID || $isEntry)?'disabled':''; ?>" title="First"><i class="fas fa-step-backward"></i></a>
                <a href="?page=master&id=<?php echo urlencode((string)$prevID); ?>" class="btn-classic-icon <?php echo (!$prevID || $isEntry)?'disabled':''; ?>" title="Previous"><i class="fas fa-backward"></i></a>
                <a href="?page=master&id=<?php echo urlencode((string)$prevID); ?>" class="btn-classic-icon <?php echo (!$prevID || $isEntry)?'disabled':''; ?>" title="Previous"><i class="fas fa-caret-left"></i></a>
                <a href="?page=master&id=<?php echo urlencode((string)$nextID); ?>" class="btn-classic-icon <?php echo (!$nextID || $isEntry)?'disabled':''; ?>" title="Next"><i class="fas fa-play"></i></a>
                <a href="?page=master&id=<?php echo urlencode((string)$nextID); ?>" class="btn-classic-icon <?php echo (!$nextID || $isEntry)?'disabled':''; ?>" title="Next"><i class="fas fa-forward"></i></a>
                <a href="?page=master&id=<?php echo urlencode((string)$lastID); ?>" class="btn-classic-icon <?php echo (!$lastID || $isEntry)?'disabled':''; ?>" title="Last"><i class="fas fa-step-forward"></i></a>
                <span style="display:inline-block; width: 5px;"></span>
                
                <a href="?page=master&mode=new" class="btn-classic-icon <?php echo ($mode=='new')?'active':''; ?>" title="Tambah (Insert)"><i class="fas fa-plus"></i></a>
                <a href="#" class="btn-classic-icon disabled" title="Edit (Gunakan Double Click Tabel)"><i class="fas fa-minus"></i></a>
                <a href="<?php echo ($is_IT_Dept && $currentID) ? "?page=master&mode=edit&id=$currentID" : "#"; ?>" class="btn-classic-icon <?php echo (!$is_IT_Dept || !$currentID || $isEntry)?'disabled':''; ?>" title="Rubah (Edit)"><i class="fas fa-caret-up"></i></a>
                
                <?php if($isEntry): ?>
                    <button type="submit" name="<?php echo $mode=='edit' ? 'btnUpdate' : 'btnSimpan'; ?>" class="btn-classic-icon" title="Simpan (Post)" onclick="return confirm('Simpan Data?');"><i class="fas fa-check"></i></button>
                    <a href="?page=master&id=<?php echo urlencode((string)$currentID); ?>" class="btn-classic-icon" title="Batal (Cancel)"><i class="fas fa-times"></i></a>
                <?php else: ?>
                    <button type="button" class="btn-classic-icon disabled"><i class="fas fa-check"></i></button>
                    <button type="button" class="btn-classic-icon disabled"><i class="fas fa-times"></i></button>
                <?php endif; ?>
                
                <a href="?page=master" class="btn-classic-icon" title="Refresh"><i class="fas fa-redo"></i></a>
                
                <?php if($is_IT_Dept && !$isEntry && $currentID): ?>
                    <button type="button" class="btn-classic-icon" style="color:red;" title="Hapus (Delete)" onclick="deleteCurrent();"><i class="fas fa-eraser"></i></button>
                <?php endif; ?>
            </div>
            
            <fieldset class="fieldset-control no-select mt-2">
                <legend>Control</legend>
                <button type="button" class="btn-classic btn-action-wide" onclick="newData()">Tambah</button>
                <button type="button" class="btn-classic btn-action-wide <?php echo (!$is_IT_Dept || !$currentID || $isEntry)?'disabled':''; ?>" onclick="goEdit()">Rubah</button>
                
                <?php if($is_IT_Dept && !$isEntry && $currentID): ?>
                    <button type="button" class="btn-classic btn-action-wide" onclick="deleteCurrent()">Hapus</button>
                <?php else: ?>
                    <button type="button" class="btn-classic btn-action-wide disabled">Hapus</button>
                <?php endif; ?>
                
                <?php if($isEntry): ?>
                    <button type="button" class="btn-classic btn-action-wide" onclick="submitSave()">Simpan</button>
                    <button type="button" class="btn-classic btn-action-wide" onclick="window.location.href='?page=master&id=<?php echo urlencode((string)$currentID); ?>'">Batal</button>
                <?php else: ?>
                    <button type="button" class="btn-classic btn-action-wide disabled">Simpan</button>
                    <button type="button" class="btn-classic btn-action-wide disabled">Batal</button>
                <?php endif; ?>
                
                <button type="button" class="btn-classic btn-action-wide" onclick="window.location.href='?page=master'">Refresh</button>
            </fieldset>
            
        </form>
    </div>

    <!-- PANEL KANAN (TABEL LIST ITEM & PAGINATION) -->
    <div class="right-panel">
        <div class="table-container">
            <table class="datagrid">
                <thead>
                    <tr>
                        <th style="width: 25px;"></th>
                        <th style="width: 80px;">CODE</th>
                        <th style="width: 120px;">NOMOR</th>
                        <th>NAME</th>
                        <th style="width: 60px;" class="text-right">COST</th>
                        <th style="width: 40px;" class="text-center">INV</th>
                        <th style="width: 40px;" class="text-center">SALE</th>
                        <th style="width: 70px;" class="text-center">NOT ACTIVE</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // QUERY PAGINATION AMAN UNTUK SEMUA VERSI SQL SERVER (2008 & 2012+)
                    $sqlList = "
                        SELECT * FROM (
                            SELECT ITEM_CODE, ITEM_NO, ITEM_NAME, ITEM_COST, ITEM_INV, ITEM_FORSALE, ITEM_INACTIVE,
                                   ROW_NUMBER() OVER (ORDER BY ITEM_CODE ASC) AS RowNum
                            FROM ITEMS
                        ) AS DataPagination
                        WHERE RowNum BETWEEN ? AND ?
                    ";
                    
                    $qList = sqlsrv_query($conn, $sqlList, array($startRow, $endRow));
                    
                    if ($qList !== false) {
                        while ($row = sqlsrv_fetch_array($qList, SQLSRV_FETCH_ASSOC)) {
                            $isActiveRow = (trim($currentID) == trim($row['ITEM_CODE'])) ? 'active' : '';
                            $invCheck = ($row['ITEM_INV'] == 1) ? '☑' : '☐';
                            $saleCheck = ($row['ITEM_FORSALE'] == 1) ? '☑' : '☐';
                            $inacCheck = ($row['ITEM_INACTIVE'] == 1) ? '☑' : '☐';
                    ?>
                        <tr class="<?php echo $isActiveRow; ?>" onclick="window.location.href='?page=master&id=<?php echo urlencode(trim($row['ITEM_CODE'])); ?>'">
                            <td class="text-center"><?php echo ($isActiveRow) ? '▶' : ''; ?></td>
                            <td><?php echo h($row['ITEM_CODE']); ?></td>
                            <td><?php echo isset($row['ITEM_NO']) ? h($row['ITEM_NO']) : ''; ?></td>
                            <td><?php echo h($row['ITEM_NAME']); ?></td>
                            <td class="text-right"><?php echo number_format((float)$row['ITEM_COST'], 3, '.', ''); ?></td>
                            <td class="text-center"><?php echo $invCheck; ?></td>
                            <td class="text-center"><?php echo $saleCheck; ?></td>
                            <td class="text-center"><?php echo $inacCheck; ?></td>
                        </tr>
                    <?php 
                        } 
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- PENGENDALI PAGINATION (DI BAWAH TABEL) -->
        <div class="pagination-container">
            <div>
                Total: <b><?php echo number_format($totalItems, 0, ',', '.'); ?></b> items | Page <b><?php echo $page; ?></b> of <b><?php echo $totalPages; ?></b>
            </div>
            <div>
                <?php 
                // Membuat Link Base
                $linkBase = "?page=master&id=".urlencode((string)$currentID)."&mode=".urlencode((string)$mode)."&p="; 
                ?>
                <a href="<?php echo $linkBase . 1; ?>" class="btn-classic <?php echo ($page <= 1) ? 'disabled' : ''; ?>">First Page</a>
                <a href="<?php echo $linkBase . ($page - 1); ?>" class="btn-classic <?php echo ($page <= 1) ? 'disabled' : ''; ?>">Prev Page</a>
                <a href="<?php echo $linkBase . ($page + 1); ?>" class="btn-classic <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">Next Page</a>
                <a href="<?php echo $linkBase . $totalPages; ?>" class="btn-classic <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">Last Page</a>
            </div>
        </div>
        
    </div>

</div>

<form id="deleteForm" method="post" action="?page=master" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="hapus_id" id="delete_item_id" value="<?php echo h($currentID); ?>">
    <input type="hidden" name="btnHapus" value="1">
</form>

<div id="acBox" class="ac-box"></div>

</body>
</html>
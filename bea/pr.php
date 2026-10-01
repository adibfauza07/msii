<?php
if (session_id() == "") {
    session_start();
}

require_once __DIR__ . '/config/database.php';

if ($conn === false) {
    die("Koneksi database gagal.");
}

// ==========================================================
// COMMON FUNCTIONS
// ==========================================================
function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8"); }
function getv($name, $default = "") { return isset($_GET[$name]) ? trim((string)$_GET[$name]) : $default; }
function postv($name, $default = "") { return isset($_POST[$name]) ? trim((string)$_POST[$name]) : $default; }
function sql_error_text() { return print_r(sqlsrv_errors(), true); }
function to_float($value) { $value = trim((string)$value); if ($value == "") return 0; return floatval(str_replace(",", "", $value)); }
function fmt_date($value) {
    if ($value instanceof DateTime) return $value->format("Y-m-d");
    if ($value == "" || $value === null) return date("Y-m-d");
    $ts = strtotime((string)$value);
    return $ts === false ? date("Y-m-d") : date("Y-m-d", $ts);
}
function fmt_date_view($value) {
    if ($value instanceof DateTime) return $value->format("d-M-Y");
    if ($value == "" || $value === null) return "";
    $ts = strtotime((string)$value);
    return $ts === false ? "" : date("d-M-Y", $ts);
}
function roman_month($m) {
    $r = array(1=>"I",2=>"II",3=>"III",4=>"IV",5=>"V",6=>"VI",7=>"VII",8=>"VIII",9=>"IX",10=>"X",11=>"XI",12=>"XII");
    return isset($r[intval($m)]) ? $r[intval($m)] : "I";
}

// ==========================================================
// SPECIFIC DB FUNCTIONS
// ==========================================================
function generate_req_no($conn, $reqDate) {
    $prefix = "RQ" . date("ymd", strtotime($reqDate));
    $stmt = sqlsrv_query($conn, "SELECT TOP 1 REQ_NO FROM dbo.REQUISITION WHERE REQ_NO LIKE ? ORDER BY REQ_NO DESC", array($prefix . "%"));
    $next = 1;
    if ($stmt !== false) {
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($r && isset($r["REQ_NO"])) {
            $lastSeq = intval(substr(trim((string)$r["REQ_NO"]), strlen($prefix)));
            $next = $lastSeq + 1;
        }
    }
    return $prefix . str_pad($next, 3, "0", STR_PAD_LEFT);
}

function generate_quo_no($conn, $quoDate) {
    $prefix = date("ymd", strtotime($quoDate));
    $stmt = sqlsrv_query($conn, "SELECT TOP 1 QUO_NO FROM dbo.QUOTATION WHERE QUO_NO LIKE ? ORDER BY QUO_NO DESC", array($prefix . "%"));
    $next = 1;
    if ($stmt !== false) {
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($r && isset($r["QUO_NO"])) {
            $lastSeq = intval(substr(trim((string)$r["QUO_NO"]), strlen($prefix)));
            $next = $lastSeq + 1;
        }
    }
    return $prefix . str_pad($next, 3, "0", STR_PAD_LEFT);
}

function get_refs($conn) {
    $ret = array("NEXT_PO"=>1, "PO_NUM"=>"%s/IMC/PO/%s/%s");
    $stmt = @sqlsrv_query($conn, "SELECT TOP 1 NEXT_PO, PO_NUM FROM dbo.REFS");
    if ($stmt !== false) {
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($r) {
            if (isset($r["NEXT_PO"])) $ret["NEXT_PO"] = intval($r["NEXT_PO"]);
            if (isset($r["PO_NUM"]) && trim((string)$r["PO_NUM"]) !== "") $ret["PO_NUM"] = trim((string)$r["PO_NUM"]);
        }
    }
    return $ret;
}

function generate_po_num($conn, $poDate) {
    $roman = roman_month(date("n", strtotime($poDate)));
    $year = date("Y", strtotime($poDate));
    
    // Perbaikan: Tambahkan % di belakang untuk mengabaikan spasi tak terlihat di database
    // Gunakan ORDER BY PO_NUM DESC untuk memastikan kita mengambil angka tertinggi
    $searchPattern = "%/" . $roman . "/" . $year . "%";
    $stmt = sqlsrv_query($conn, "SELECT TOP 1 PO_NUM FROM dbo.PO WHERE PO_NUM LIKE ? ORDER BY PO_NUM DESC", array($searchPattern));
    
    $next = 1;
    if ($stmt !== false) {
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($r && isset($r["PO_NUM"])) {
            $parts = explode("/", trim((string)$r["PO_NUM"]));
            if (isset($parts[0]) && is_numeric($parts[0])) {
                $next = intval($parts[0]) + 1;
            }
        }
    }

    $refs = get_refs($conn);
    $nextText = str_pad($next, 3, "0", STR_PAD_LEFT);
    $poNum = @sprintf(trim($refs["PO_NUM"]), $nextText, $roman, $year);
    
    if ($poNum == "" || strpos($poNum, "%") !== false) {
        $poNum = $nextText . "/IMC/PO/" . $roman . "/" . $year;
    }
    
    return trim($poNum);
}

function inc_next_po($conn) {
    @sqlsrv_query($conn, "UPDATE TOP (1) dbo.REFS SET NEXT_PO = ISNULL(NEXT_PO, 0) + 1");
}

function find_item_id_by_code($conn, $itemCode) {
    $itemCode = trim((string)$itemCode);
    if ($itemCode == "") return 0;
    $stmt = sqlsrv_query($conn, "SELECT TOP 1 ITEM_ID FROM dbo.ITEMS WHERE ITEM_CODE = ?", array($itemCode));
    if ($stmt === false) return 0;
    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return $r ? intval($r["ITEM_ID"]) : 0;
}

function get_quotation_price($conn, $supId, $itemId, $cur) {
    $sql = "
        SELECT TOP 1 D.QUOD_PRICE
        FROM dbo.QUOT_DETAIL D
        INNER JOIN dbo.QUOTATION Q ON D.QUO_ID = Q.QUO_ID
        WHERE Q.SUP_ID = ? AND D.ITEM_ID = ? AND Q.CURR_CODE = ? AND ISNULL(D.QUOD_PRICE,0) > 0
        ORDER BY CASE WHEN ISNULL(D.QUOD_ACTIVE,0)=1 THEN 0 ELSE 1 END, Q.QUO_DATE DESC, Q.QUO_ID DESC
    ";
    $stmt = sqlsrv_query($conn, $sql, array(intval($supId), intval($itemId), trim((string)$cur)));
    if ($stmt !== false) {
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($r) return floatval($r["QUOD_PRICE"]);
    }
    return 0;
}

// ==========================================================
// DYNAMIC PAGE ROUTING & ACTION INTERCEPTORS
// ==========================================================
$pageName = isset($_GET['page']) ? $_GET['page'] : 'pr';
$urlBase  = "?page=" . urlencode($pageName) . "&";

$tab = getv("tab", "pr");
if ($tab != "pr" && $tab != "quotation" && $tab != "po") $tab = "pr";

$action = getv("action", postv("action", ""));

/* --- POPUP: PO VIEW (PR TAB) --- */
if ($action == "po_view") {
    $reqId  = intval(getv("req_id", "0"));
    $itemId = intval(getv("item_id", "0"));
    $sqlPO = "SELECT REQ_ID, ITEM_ID, PO_DATE AS [PO DATE], PO_NUM AS [PO #], POD_QTY AS [Qty], SUP_CODE AS [Sup.Code], SUP_COMP AS [Supplier] FROM dbo.REQ_ITEM_PO_VIEW WHERE REQ_ID = ? AND ITEM_ID = ? ORDER BY PO_DATE DESC, PO_NUM DESC";
    $stmtPO = sqlsrv_query($conn, $sqlPO, array($reqId, $itemId));
    ?>
    <!DOCTYPE html><html><head><title>PO List</title><style>body{font-family:Tahoma,sans-serif;font-size:12px;background:#ecf0f5;padding:10px;} th{background:#3c8dbc;color:#fff;padding:5px;} td{border:1px solid #ccc;padding:4px;}</style></head><body>
    <div style="background:#2c3e50;color:#fff;padding:8px;font-weight:bold;margin-bottom:10px;">PO LIST - REQ_ID: <?php echo h($reqId); ?> / ITEM_ID: <?php echo h($itemId); ?></div>
    <table style="width:100%;border-collapse:collapse;background:#fff;"><thead><tr><th>No</th><th>PO Date</th><th>PO #</th><th>Qty</th><th>Sup.Code</th><th>Supplier</th></tr></thead><tbody>
    <?php $no=1; while ($p = sqlsrv_fetch_array($stmtPO, SQLSRV_FETCH_ASSOC)) { ?>
        <tr><td style="text-align:center;"><?php echo $no++; ?></td><td><?php echo h(fmt_date_view($p["PO DATE"])); ?></td><td><?php echo h($p["PO #"]); ?></td><td style="text-align:right;"><?php echo h(number_format(floatval($p["Qty"]), 2)); ?></td><td><?php echo h($p["Sup.Code"]); ?></td><td><?php echo h($p["Supplier"]); ?></td></tr>
    <?php } if ($no == 1) echo "<tr><td colspan='6' style='text-align:center;'>PO belum ada untuk item ini.</td></tr>"; ?>
    </tbody></table><button onclick="window.close()" style="margin-top:10px;padding:5px 15px;cursor:pointer;">CLOSE</button></body></html>
    <?php exit;
}

/* --- POPUP: QUOTATION HISTORY (QUOTATION TAB) --- */
if ($action == "quo_history") {
    $itemId = intval(getv("item_id", "0"));
    $sqlHis = "SELECT TOP 200 Q.QUO_NO, Q.QUO_DATE, Q.QUO_EFFDATE, Q.CURR_CODE, S.SUP_CODE, S.SUP_COMP, D.QUOD_PRICE, D.QUOD_MINQTY, D.QUOD_UNIT, D.QUOD_TERM, D.QUOD_ACTIVE FROM dbo.QUOT_DETAIL D INNER JOIN dbo.QUOTATION Q ON D.QUO_ID = Q.QUO_ID LEFT JOIN dbo.SUPPLIER S ON Q.SUP_ID = S.SUP_ID WHERE D.ITEM_ID = ? ORDER BY Q.QUO_DATE DESC, Q.QUO_NO DESC";
    $stmtHis = sqlsrv_query($conn, $sqlHis, array($itemId));
    ?>
    <!DOCTYPE html><html><head><title>Quo History</title><style>body{font-family:Tahoma,sans-serif;font-size:12px;background:#ecf0f5;padding:10px;} th{background:#f39c12;color:#fff;padding:5px;} td{border:1px solid #ccc;padding:4px;}</style></head><body>
    <div style="background:#2c3e50;color:#fff;padding:8px;font-weight:bold;margin-bottom:10px;">QUOTATION HISTORY - ITEM_ID : <?php echo h($itemId); ?></div>
    <table style="width:100%;border-collapse:collapse;background:#fff;"><thead><tr><th>No</th><th>Quo No</th><th>Date</th><th>Eff Date</th><th>Curr</th><th>Supplier</th><th>Price</th><th>Min.Qty</th><th>Unit</th><th>Term</th><th>Active</th></tr></thead><tbody>
    <?php $no=1; while ($r = sqlsrv_fetch_array($stmtHis, SQLSRV_FETCH_ASSOC)) { ?>
        <tr><td style="text-align:center;"><?php echo $no++; ?></td><td><?php echo h($r["QUO_NO"]); ?></td><td><?php echo h(fmt_date_view($r["QUO_DATE"])); ?></td><td><?php echo h(fmt_date_view($r["QUO_EFFDATE"])); ?></td><td align="center"><?php echo h($r["CURR_CODE"]); ?></td><td><?php echo h($r["SUP_COMP"]); ?></td><td align="right"><?php echo h(number_format(floatval($r["QUOD_PRICE"]), 2)); ?></td><td align="right"><?php echo h(number_format(floatval($r["QUOD_MINQTY"]), 2)); ?></td><td><?php echo h($r["QUOD_UNIT"]); ?></td><td><?php echo h($r["QUOD_TERM"]); ?></td><td align="center"><?php echo intval($r["QUOD_ACTIVE"])==1?"YES":""; ?></td></tr>
    <?php } if ($no == 1) echo "<tr><td colspan='11' style='text-align:center;'>History tidak ditemukan.</td></tr>"; ?>
    </tbody></table><button onclick="window.close()" style="margin-top:10px;padding:5px 15px;cursor:pointer;">CLOSE</button></body></html>
    <?php exit;
}

/* --- POPUP: RECEIVE VIEW (PO TAB) --- */
if ($action == "receive_view") {
    $poNum = getv("po_num",""); $itemId = intval(getv("item_id","0"));
    $sql = "SELECT R.RCV_DATE, R.RCV_NO, R.RCV_DONO, RD.RCVD_QTY, S.SUP_CODE, S.SUP_COMP, I.ITEM_CODE, I.ITEM_NAME FROM dbo.RECEIVE_DETAIL RD INNER JOIN dbo.RECEIVE R ON R.RCV_ID = RD.RCV_ID INNER JOIN dbo.PO P ON P.PO_ID = RD.PO_ID INNER JOIN dbo.ITEMS I ON I.ITEM_ID = RD.ITEM_ID INNER JOIN dbo.SUPPLIER S ON S.SUP_ID = P.SUP_ID AND S.SUP_ID = R.SUP_ID WHERE RD.ITEM_ID = ? AND P.PO_NUM = ? ORDER BY R.RCV_DATE DESC, R.RCV_NO DESC";
    $stmt = sqlsrv_query($conn, $sql, array($itemId, $poNum));
    $rows = array(); $total = 0; $itemCode = ""; $itemName = "";
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if ($itemCode === "") { $itemCode = trim((string)$r["ITEM_CODE"]); $itemName = trim((string)$r["ITEM_NAME"]); }
        $total += floatval($r["RCVD_QTY"]); $rows[] = $r;
    }
    if ($itemCode === "") {
        $stmtItem = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE, ITEM_NAME FROM dbo.ITEMS WHERE ITEM_ID = ?", array($itemId));
        if ($stmtItem !== false && $ri = sqlsrv_fetch_array($stmtItem, SQLSRV_FETCH_ASSOC)) { $itemCode = trim((string)$ri["ITEM_CODE"]); $itemName = trim((string)$ri["ITEM_NAME"]); }
    }
    ?>
    <!DOCTYPE html><html><head><title>Receive List</title><style>body{font-family:Tahoma,sans-serif;font-size:12px;background:#ecf0f5;padding:10px;} th{background:#00a65a;color:#fff;padding:5px;} td{border:1px solid #ccc;padding:4px;} .num{text-align:right}</style></head><body>
    <div style="background:#2c3e50;color:#fff;padding:8px;font-weight:bold;margin-bottom:10px;">RECEIVE LIST - PO: <?php echo h($poNum); ?> / ITEM: <?php echo h($itemCode." - ".$itemName); ?></div>
    <table style="width:100%;border-collapse:collapse;background:#fff;"><thead><tr><th>No</th><th>Receive Date</th><th>Receive No</th><th>DO No</th><th>Qty</th><th>Supplier</th></tr></thead><tbody>
    <?php $no=1; foreach($rows as $r) { ?>
    <tr><td align="center"><?php echo $no++; ?></td><td><?php echo h(fmt_date_view($r["RCV_DATE"])); ?></td><td><?php echo h($r["RCV_NO"]); ?></td><td><?php echo h($r["RCV_DONO"]); ?></td><td class="num"><?php echo h(number_format(floatval($r["RCVD_QTY"]),2)); ?></td><td><?php echo h($r["SUP_COMP"]); ?></td></tr>
    <?php } if ($no == 1) echo "<tr><td colspan='6' align='center'>Data receive belum ada.</td></tr>"; else echo "<tr style='background:#eee;font-weight:bold;'><td colspan='4' align='right'>TOTAL</td><td class='num'>".h(number_format($total,2))."</td><td></td></tr>"; ?>
    </tbody></table><button onclick="window.close()" style="margin-top:10px;padding:5px 15px;cursor:pointer;">CLOSE</button></body></html>
    <?php exit;
}

/* --- AJAX: RATE JSON (PO TAB) --- */
if ($action == "rate_json") {
    header("Content-Type: application/json; charset=utf-8");
    $price = get_quotation_price($conn, intval(getv("sup_id","0")), intval(getv("item_id","0")), getv("curr_code",""));
    echo json_encode(array("ok"=>$price>0?1:0, "price"=>$price)); exit;
}

$message = "";
$error = "";
$editId = intval(getv("edit", "0"));

// Variabel Global Data
$itemAuto = array(); $reqAuto = array(); $supplierAuto = array(); $quoAuto = array(); $osReqAuto = array(); $poAuto = array();


// ==========================================================
// 1. DATA PROCESSING & SAVE/DELETE - TAB PR
// ==========================================================
if ($tab == "pr" && $action == "delete") {
    $reqId = intval(postv("req_id", "0"));
    if ($reqId > 0) {
        $stmtCek = sqlsrv_query($conn, "SELECT COUNT(*) AS CNT FROM dbo.REQ_DETAIL WHERE REQ_ID = ?", array($reqId));
        if ($stmtCek && ($rc = sqlsrv_fetch_array($stmtCek, SQLSRV_FETCH_ASSOC)) && intval($rc["CNT"]) > 0) {
            $error = "Tidak dapat menghapus, masih ada detail requisition.";
        } else {
            if (sqlsrv_query($conn, "DELETE FROM dbo.REQUISITION WHERE REQ_ID = ?", array($reqId))) {
                echo "<script>window.location.href='{$urlBase}tab=pr&msg=deleted';</script>"; exit;
            } else $error = "Delete gagal:\n" . sql_error_text();
        }
    }
}
if ($tab == "pr" && $action == "save") {
    $reqId = intval(postv("req_id", "0")); $reqNo = strtoupper(postv("req_no", "")); $reqDate = postv("req_date", date("Y-m-d")); $reqDue = postv("req_due", date("Y-m-d")); $reqRem = postv("req_rem", ""); $depId = intval(postv("dep_id", "0")); $reqIsamr = isset($_POST["req_isamr"]) ? 1 : 0; $reqClose = isset($_POST["req_close"]) ? 1 : 0; $allowEmptyDetail = intval(postv("allow_empty_detail", "0"));
    if ($reqNo == "") $reqNo = generate_req_no($conn, $reqDate);
    if ($depId <= 0) $error = "Departement wajib dipilih.";
    else {
        $itemIds = isset($_POST["item_id"]) ? $_POST["item_id"] : array(); $itemCodes = isset($_POST["item_code"]) ? $_POST["item_code"] : array(); $qtys = isset($_POST["reqd_qty"]) ? $_POST["reqd_qty"] : array(); $rems = isset($_POST["reqd_rem"]) ? $_POST["reqd_rem"] : array();
        $hasDetail = false;
        for ($i = 0; $i < count($itemIds); $i++) {
            $itemId = intval($itemIds[$i]); $itemCode = isset($itemCodes[$i]) ? trim((string)$itemCodes[$i]) : "";
            if ($itemId <= 0 && $itemCode != "") $itemId = find_item_id_by_code($conn, $itemCode);
            if ($itemId > 0 && (isset($qtys[$i]) ? to_float($qtys[$i]) : 0) > 0) { $hasDetail = true; break; }
        }
        if (!$hasDetail && !($allowEmptyDetail == 1 && $reqId > 0)) $error = "Detail item minimal 1 baris dan qty harus lebih dari 0.";
        else {
            sqlsrv_begin_transaction($conn); $ok = true;
            if ($reqId > 0) {
                if (sqlsrv_query($conn, "UPDATE dbo.REQUISITION SET REQ_NO=?, REQ_DATE=?, REQ_DUE=?, REQ_REM=?, REQ_ISAMR=?, REQ_CLOSE=?, DEP_ID=? WHERE REQ_ID=?", array($reqNo, $reqDate, $reqDue, $reqRem, $reqIsamr, $reqClose, $depId, $reqId)) === false) $ok = false;
            } else {
                $stmtH = sqlsrv_query($conn, "INSERT INTO dbo.REQUISITION (REQ_NO, REQ_DATE, REQ_DUE, REQ_REM, REQ_ISAMR, REQ_CLOSE, DEP_ID) OUTPUT INSERTED.REQ_ID VALUES (?, ?, ?, ?, ?, ?, ?)", array($reqNo, $reqDate, $reqDue, $reqRem, $reqIsamr, $reqClose, $depId));
                if ($stmtH === false) $ok = false;
                else { $newRow = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_NUMERIC); if ($newRow) $reqId = intval($newRow[0]); else $ok = false; }
            }
            if ($ok) sqlsrv_query($conn, "DELETE FROM dbo.REQ_DETAIL WHERE REQ_ID = ?", array($reqId));
            if ($ok) {
                for ($i = 0; $i < count($itemIds); $i++) {
                    $itemId = intval($itemIds[$i]); $itemCode = isset($itemCodes[$i]) ? trim((string)$itemCodes[$i]) : "";
                    if ($itemId <= 0 && $itemCode != "") $itemId = find_item_id_by_code($conn, $itemCode);
                    $qty = isset($qtys[$i]) ? to_float($qtys[$i]) : 0; $rem = isset($rems[$i]) ? trim((string)$rems[$i]) : "";
                    if ($itemId > 0 && $qty > 0) {
                        if (sqlsrv_query($conn, "INSERT INTO dbo.REQ_DETAIL (ITEM_ID, REQ_ID, REQD_QTY, REQD_REM) VALUES (?, ?, ?, ?)", array($itemId, $reqId, $qty, $rem)) === false) { $ok = false; break; }
                    }
                }
            }
            if ($ok) { 
                sqlsrv_commit($conn); 
                echo "<script>window.location.href='{$urlBase}tab=pr&edit=" . $reqId . "&msg=saved';</script>"; exit; 
            }
            else { sqlsrv_rollback($conn); $error = "Simpan gagal:\n" . sql_error_text(); }
        }
    }
}

// ==========================================================
// 2. DATA PROCESSING & SAVE/DELETE - TAB QUOTATION
// ==========================================================
if ($tab == "quotation" && $action == "delete") {
    $quoId = intval(postv("quo_id", "0"));
    if ($quoId > 0) {
        $stmtCek = sqlsrv_query($conn, "SELECT COUNT(*) AS CNT FROM dbo.QUOT_DETAIL WHERE QUO_ID = ?", array($quoId));
        if ($stmtCek && ($rc = sqlsrv_fetch_array($stmtCek, SQLSRV_FETCH_ASSOC)) && intval($rc["CNT"]) > 0) $error = "Tidak dapat menghapus, masih ada detail quotation.";
        else {
            if (sqlsrv_query($conn, "DELETE FROM dbo.QUOTATION WHERE QUO_ID = ?", array($quoId))) { 
                echo "<script>window.location.href='{$urlBase}tab=quotation&msg=deleted';</script>"; exit; 
            } 
            else $error = "Delete quotation gagal:\n" . sql_error_text();
        }
    }
}
if ($tab == "quotation" && $action == "save") {
    $quoId = intval(postv("quo_id", "0")); $supId = intval(postv("sup_id", "0")); $quoNo = strtoupper(postv("quo_no", "")); $quoDate = postv("quo_date", date("Y-m-d")); $currCode = strtoupper(postv("curr_code", "IDR")); $quoEffDate = postv("quo_effdate", date("Y-m-d")); $allowEmpty = intval(postv("allow_empty_detail", "0"));
    if ($quoNo == "") $quoNo = generate_quo_no($conn, $quoDate);
    if ($supId <= 0) $error = "Supplier wajib dipilih.";
    else {
        $itemIds = isset($_POST["item_id"]) ? $_POST["item_id"] : array(); $itemCodes = isset($_POST["item_code"]) ? $_POST["item_code"] : array(); $prices = isset($_POST["quod_price"]) ? $_POST["quod_price"] : array(); $minQtys = isset($_POST["quod_minqty"]) ? $_POST["quod_minqty"] : array(); $units = isset($_POST["quod_unit"]) ? $_POST["quod_unit"] : array(); $terms = isset($_POST["quod_term"]) ? $_POST["quod_term"] : array(); $actives = isset($_POST["quod_active"]) ? $_POST["quod_active"] : array();
        $hasDetail = false;
        for ($i = 0; $i < count($itemIds); $i++) {
            $itemId = intval($itemIds[$i]); $itemCode = isset($itemCodes[$i]) ? trim((string)$itemCodes[$i]) : "";
            if ($itemId <= 0 && $itemCode != "") $itemId = find_item_id_by_code($conn, $itemCode);
            if ($itemId > 0 && (isset($prices[$i]) ? to_float($prices[$i]) : 0) > 0) { $hasDetail = true; break; }
        }
        if (!$hasDetail && !($allowEmpty == 1 && $quoId > 0)) $error = "Detail quotation minimal 1 baris dan price harus lebih dari 0.";
        else {
            sqlsrv_begin_transaction($conn); $ok = true;
            if ($quoId > 0) {
                if (sqlsrv_query($conn, "UPDATE dbo.QUOTATION SET SUP_ID=?, QUO_NO=?, QUO_DATE=?, CURR_CODE=?, QUO_EFFDATE=? WHERE QUO_ID=?", array($supId, $quoNo, $quoDate, $currCode, $quoEffDate, $quoId)) === false) $ok = false;
            } else {
                $stmtH = sqlsrv_query($conn, "INSERT INTO dbo.QUOTATION (SUP_ID, QUO_NO, QUO_DATE, CURR_CODE, QUO_EFFDATE) OUTPUT INSERTED.QUO_ID VALUES (?, ?, ?, ?, ?)", array($supId, $quoNo, $quoDate, $currCode, $quoEffDate));
                if ($stmtH === false) $ok = false; else { $newRow = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_NUMERIC); if ($newRow) $quoId = intval($newRow[0]); else $ok = false; }
            }
            if ($ok) sqlsrv_query($conn, "DELETE FROM dbo.QUOT_DETAIL WHERE QUO_ID = ?", array($quoId));
            if ($ok) {
                for ($i = 0; $i < count($itemIds); $i++) {
                    $itemId = intval($itemIds[$i]); $itemCode = isset($itemCodes[$i]) ? trim((string)$itemCodes[$i]) : "";
                    if ($itemId <= 0 && $itemCode != "") $itemId = find_item_id_by_code($conn, $itemCode);
                    $price = isset($prices[$i]) ? to_float($prices[$i]) : 0; $minQty = isset($minQtys[$i]) ? to_float($minQtys[$i]) : 0; $unit = isset($units[$i]) ? trim((string)$units[$i]) : ""; $term = isset($terms[$i]) ? trim((string)$terms[$i]) : ""; $active = isset($actives[$i]) ? 1 : 0;
                    if ($itemId > 0 && $price > 0) {
                        if (sqlsrv_query($conn, "INSERT INTO dbo.QUOT_DETAIL (QUOD_PRICE, QUOD_MINQTY, QUOD_UNIT, QUOD_TERM, ITEM_ID, QUO_ID, QUOD_ACTIVE) VALUES (?, ?, ?, ?, ?, ?, ?)", array($price, $minQty, $unit, $term, $itemId, $quoId, $active)) === false) { $ok = false; break; }
                    }
                }
            }
            if ($ok) { 
                sqlsrv_commit($conn); 
                echo "<script>window.location.href='{$urlBase}tab=quotation&edit=" . $quoId . "&msg=saved';</script>"; exit; 
            }
            else { sqlsrv_rollback($conn); $error = "Simpan gagal:\n" . sql_error_text(); }
        }
    }
}

// ==========================================================
// 3. DATA PROCESSING & SAVE/DELETE - TAB PO
// ==========================================================
if ($tab == "po" && $action == "delete") {
    $poId = intval(postv("po_id","0"));
    if ($poId > 0) {
        $stmtCek = sqlsrv_query($conn, "SELECT COUNT(*) AS CNT FROM dbo.PO_DETAIL WHERE PO_ID = ?", array($poId));
        if ($stmtCek && ($rc = sqlsrv_fetch_array($stmtCek, SQLSRV_FETCH_ASSOC)) && intval($rc["CNT"]) > 0) $error = "Tidak dapat menghapus, masih terdapat detailnya.";
        else {
            if (sqlsrv_query($conn, "DELETE FROM dbo.PO WHERE PO_ID = ?", array($poId))) { 
                echo "<script>window.location.href='{$urlBase}tab=po&msg=deleted';</script>"; exit; 
            } 
            else $error = "Delete PO gagal:\n".sql_error_text();
        }
    }
}
if ($tab == "po" && $action == "save") {
    $poId = intval(postv("po_id","0")); $poNum = strtoupper(postv("po_num","")); $poDate = postv("po_date",date("Y-m-d")); $poDateDo = postv("po_datedo",date("Y-m-d")); $supId = intval(postv("sup_id","0")); $poTo = intval(postv("po_to","0")); $poToDef = isset($_POST["po_todef"]) ? 1 : 0; $poCur = strtoupper(postv("po_cur","IDR")); $poClose = isset($_POST["po_close"]) ? 1 : 0; $poTerm = postv("po_term",""); $poTermDel = postv("po_termdel",""); $poTermDelSch = isset($_POST["po_termdelsch"]) ? 1 : 0; $poRem = postv("po_rem",""); $allowEmpty = intval(postv("allow_empty_detail","0"));
    $autoNoUsed = false; if ($poNum == "") { $poNum = generate_po_num($conn, $poDate); $autoNoUsed = true; }
    if ($supId <= 0) $error = "Supplier wajib dipilih."; elseif ($poTo <= 0) $error = "Invoice To wajib dipilih."; elseif ($poCur == "") $error = "Currency wajib diisi.";
    if ($error == "") {
        $reqIds = isset($_POST["req_id"]) ? $_POST["req_id"] : array(); $itemIds = isset($_POST["item_id"]) ? $_POST["item_id"] : array(); $itemCodes = isset($_POST["item_code"]) ? $_POST["item_code"] : array(); $podDues = isset($_POST["pod_due"]) ? $_POST["pod_due"] : array(); $podQtys = isset($_POST["pod_qty"]) ? $_POST["pod_qty"] : array(); $podPrices = isset($_POST["pod_price"]) ? $_POST["pod_price"] : array(); $podUnits = isset($_POST["pod_unit"]) ? $_POST["pod_unit"] : array();
        $hasDetail = false;
        for ($i=0; $i<count($itemIds); $i++) {
            $itemId = intval($itemIds[$i]); $itemCode = isset($itemCodes[$i]) ? trim((string)$itemCodes[$i]) : "";
            if ($itemId <= 0 && $itemCode != "") $itemId = find_item_id_by_code($conn, $itemCode);
            if ($itemId > 0 && (isset($podQtys[$i]) ? to_float($podQtys[$i]) : 0) > 0) { $hasDetail = true; break; }
        }
        if (!$hasDetail && !($allowEmpty == 1 && $poId > 0)) $error = "Detail PO minimal 1 baris dan qty harus lebih dari 0.";
        else {
            sqlsrv_begin_transaction($conn); $ok = true;
            if ($poId > 0) {
                if (sqlsrv_query($conn,"UPDATE dbo.PO SET SUP_ID=?, PO_DATE=?, PO_TO=?, PO_TODEF=?, PO_CUR=?, PO_CLOSE=?, PO_TERM=?, PO_TERMDEL=?, PO_TERMDELSCH=?, PO_REV=ISNULL(PO_REV,0)+1, PO_DATEDO=?, PO_NUM=?, PO_REM=? WHERE PO_ID=?", array($supId,$poDate,$poTo,$poToDef,$poCur,$poClose,$poTerm,$poTermDel,$poTermDelSch,$poDateDo,$poNum,$poRem,$poId)) === false) { $ok=false; $error="Simpan header PO gagal."; }
            } else {
                $stmtH = sqlsrv_query($conn,"INSERT INTO dbo.PO (SUP_ID, PO_DATE, PO_TO, PO_TODEF, PO_CUR, PO_CLOSE, PO_TERM, PO_TERMDEL, PO_TERMDELSCH, PO_REV, PO_DATEDO, PO_NUM, PO_REM) OUTPUT INSERTED.PO_ID VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)", array($supId,$poDate,$poTo,$poToDef,$poCur,$poClose,$poTerm,$poTermDel,$poTermDelSch,$poDateDo,$poNum,$poRem));
                if ($stmtH === false) { $ok=false; $error="Simpan header PO gagal."; } else { $new = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_NUMERIC); if ($new) $poId = intval($new[0]); else { $ok=false; $error="PO_ID baru tidak terbaca."; } }
            }
            if ($ok) sqlsrv_query($conn, "DELETE FROM dbo.PO_DETAIL WHERE PO_ID = ?", array($poId));
            if ($ok) {
                for ($i=0; $i<count($itemIds); $i++) {
                    $itemId = intval($itemIds[$i]); $itemCode = isset($itemCodes[$i]) ? trim((string)$itemCodes[$i]) : "";
                    if ($itemId <= 0 && $itemCode != "") $itemId = find_item_id_by_code($conn, $itemCode);
                    $reqId = isset($reqIds[$i]) ? intval($reqIds[$i]) : 0; $podDue = isset($podDues[$i]) ? trim((string)$podDues[$i]) : ""; $qty = isset($podQtys[$i]) ? to_float($podQtys[$i]) : 0; $price = isset($podPrices[$i]) ? to_float($podPrices[$i]) : 0; $unit = isset($podUnits[$i]) ? trim((string)$podUnits[$i]) : "";
                    if ($itemId <= 0 || $qty <= 0) continue;
                    if ($price <= 0) $price = get_quotation_price($conn, $supId, $itemId, $poCur);
                    if ($price <= 0) { $ok=false; $error="Price tidak ditemukan untuk item ".$itemCode; break; }
                    if ($podDue == "") $podDue = $poDateDo;
                    if (sqlsrv_query($conn,"INSERT INTO dbo.PO_DETAIL (PO_ID, REQ_ID, ITEM_ID, POD_QTY, POD_PRICE, POD_UNIT, POD_DUE) VALUES (?, ?, ?, ?, ?, ?, ?)", array($poId,$reqId,$itemId,$qty,$price,$unit,$podDue)) === false) { $ok=false; $error="Simpan detail gagal."; break; }
                }
            }
            if ($ok) { 
                sqlsrv_commit($conn); 
                if ($autoNoUsed) inc_next_po($conn); 
                echo "<script>window.location.href='{$urlBase}tab=po&edit=".$poId."&msg=saved';</script>"; exit; 
            }
            else { sqlsrv_rollback($conn); if ($error == "") $error = "Simpan PO gagal:\n".sql_error_text(); }
        }
    }
}


// Global Messages
if (getv("msg", "") == "saved") $message = "Data berhasil disimpan.";
if (getv("msg", "") == "deleted") $message = "Data berhasil dihapus.";

// ==========================================================
// GLOBALS DATA (Di-load di awal untuk semua TAB agar JS siap)
// ==========================================================
// 1. DATA DEPT
$deptList = array();
$stmtDept = sqlsrv_query($conn, "SELECT DEP_ID, DEP_NAME, DEP_CODE FROM dbo.DEPT ORDER BY DEP_CODE");
if ($stmtDept !== false) while ($d = sqlsrv_fetch_array($stmtDept, SQLSRV_FETCH_ASSOC)) $deptList[] = array("DEP_ID" => intval($d["DEP_ID"]), "DEP_CODE" => trim((string)$d["DEP_CODE"]), "DEP_NAME" => trim((string)$d["DEP_NAME"]));

// 2. DATA ITEM
$stmtItem = sqlsrv_query($conn, "SELECT TOP 3000 ITEM_ID, ITEM_CODE, ITEM_NAME, ITEM_UNIT, ITEM_COST FROM dbo.ITEMS WHERE ISNULL(ITEM_CODE, '') <> '' AND ISNULL(ITEM_INACTIVE, 0) = 0 ORDER BY ITEM_CODE");
if ($stmtItem !== false) while ($it = sqlsrv_fetch_array($stmtItem, SQLSRV_FETCH_ASSOC)) $itemAuto[] = array("ITEM_ID" => intval($it["ITEM_ID"]), "ITEM_CODE" => trim((string)$it["ITEM_CODE"]), "ITEM_NAME" => trim((string)$it["ITEM_NAME"]), "ITEM_UNIT" => trim((string)$it["ITEM_UNIT"]), "ITEM_COST" => isset($it["ITEM_COST"]) ? floatval($it["ITEM_COST"]) : 0);

// 3. DATA SUPPLIER
$stmtSup = sqlsrv_query($conn,"SELECT TOP 1000 SUP_ID, SUP_CODE, SUP_COMP, CURR_CODE, SUP_TERM FROM dbo.SUPPLIER WHERE ISNULL(SUP_CODE,'') <> '' ORDER BY SUP_CODE");
if ($stmtSup !== false) while ($s = sqlsrv_fetch_array($stmtSup, SQLSRV_FETCH_ASSOC)) $supplierAuto[] = array("SUP_ID"=>intval($s["SUP_ID"]), "SUP_CODE"=>trim((string)$s["SUP_CODE"]), "SUP_COMP"=>trim((string)$s["SUP_COMP"]), "CURR_CODE"=>trim((string)$s["CURR_CODE"]), "SUP_TERM"=>trim((string)$s["SUP_TERM"]));

// 4. DATA CURRENCY
$currList = array("IDR","USD","JPY","YEN");
$stmtCurr = @sqlsrv_query($conn, "SELECT DISTINCT LTRIM(RTRIM(CURR_CODE)) AS CURR_CODE FROM dbo.CURR WHERE ISNULL(CURR_CODE,'') <> '' ORDER BY LTRIM(RTRIM(CURR_CODE))");
if ($stmtCurr !== false) { $currList = array(); while ($c = sqlsrv_fetch_array($stmtCurr, SQLSRV_FETCH_ASSOC)) if (trim((string)$c["CURR_CODE"]) != "") $currList[] = trim((string)$c["CURR_CODE"]); }

// 5. DATA REQUISITION AUTOCOMPLETE
$stmtReqAuto = sqlsrv_query($conn, "SELECT TOP 500 R.REQ_ID, R.REQ_NO, R.REQ_DATE, R.REQ_REM, D.DEP_CODE, D.DEP_NAME FROM dbo.REQUISITION R LEFT JOIN dbo.DEPT D ON R.DEP_ID = D.DEP_ID ORDER BY R.REQ_DATE DESC, R.REQ_NO DESC");
if ($stmtReqAuto !== false) while ($ra = sqlsrv_fetch_array($stmtReqAuto, SQLSRV_FETCH_ASSOC)) $reqAuto[] = array("REQ_ID" => intval($ra["REQ_ID"]), "REQ_NO" => trim((string)$ra["REQ_NO"]), "REQ_DATE" => fmt_date_view($ra["REQ_DATE"]), "REQ_REM" => trim((string)$ra["REQ_REM"]), "DEP_CODE" => trim((string)$ra["DEP_CODE"]), "DEP_NAME" => trim((string)$ra["DEP_NAME"]));

// 6. DATA QUOTATION AUTOCOMPLETE
$stmtQuoAuto = sqlsrv_query($conn, "SELECT TOP 500 Q.QUO_ID, Q.QUO_NO, Q.QUO_DATE, Q.CURR_CODE, S.SUP_CODE, S.SUP_COMP FROM dbo.QUOTATION Q LEFT JOIN dbo.SUPPLIER S ON Q.SUP_ID = S.SUP_ID ORDER BY Q.QUO_DATE DESC, Q.QUO_NO DESC");
if ($stmtQuoAuto !== false) while ($qa = sqlsrv_fetch_array($stmtQuoAuto, SQLSRV_FETCH_ASSOC)) $quoAuto[] = array("QUO_ID" => intval($qa["QUO_ID"]), "QUO_NO" => trim((string)$qa["QUO_NO"]), "QUO_DATE" => fmt_date_view($qa["QUO_DATE"]), "CURR_CODE" => trim((string)$qa["CURR_CODE"]), "SUP_CODE" => trim((string)$qa["SUP_CODE"]), "SUP_COMP" => trim((string)$qa["SUP_COMP"]));

// 7. DATA OS REQ AUTOCOMPLETE
$stmtOS = sqlsrv_query($conn, ";WITH OSREQ AS (SELECT RD.ITEM_ID, I.ITEM_CODE, I.ITEM_NAME, I.ITEM_UNIT, RD.REQ_ID, R.REQ_DUE, R.REQ_DATE, R.REQ_NO, RD.REQD_QTY, ISNULL(IPV.POD_QTY,0) AS POD_QTY, RD.REQD_QTY - ISNULL(IPV.POD_QTY,0) AS REQ_BQTY FROM dbo.REQ_DETAIL RD INNER JOIN dbo.REQUISITION R ON RD.REQ_ID = R.REQ_ID INNER JOIN dbo.ITEMS I ON RD.ITEM_ID = I.ITEM_ID LEFT JOIN dbo.ITEM_REQ_PO_VIEW IPV ON RD.REQ_ID = IPV.REQ_ID AND RD.ITEM_ID = IPV.ITEM_ID WHERE R.REQ_CLOSE = 0 AND RD.REQD_QTY - ISNULL(IPV.POD_QTY,0) > 0), QUO AS (SELECT Q.SUP_ID, Q.CURR_CODE, QD.ITEM_ID, QD.QUOD_PRICE, ROW_NUMBER() OVER (PARTITION BY Q.SUP_ID, Q.CURR_CODE, QD.ITEM_ID ORDER BY CASE WHEN ISNULL(QD.QUOD_ACTIVE,0)=1 THEN 0 ELSE 1 END, Q.QUO_DATE DESC, Q.QUO_ID DESC) AS RN FROM dbo.QUOTATION Q INNER JOIN dbo.QUOT_DETAIL QD ON Q.QUO_ID = QD.QUO_ID WHERE ISNULL(QD.QUOD_PRICE,0) > 0) SELECT TOP 2000 O.ITEM_ID, O.ITEM_CODE, O.ITEM_NAME, O.ITEM_UNIT, O.REQ_ID, O.REQ_DUE, O.REQ_DATE, O.REQ_NO, O.REQD_QTY, O.POD_QTY, O.REQ_BQTY, Q.SUP_ID, Q.CURR_CODE, Q.QUOD_PRICE FROM OSREQ O INNER JOIN QUO Q ON O.ITEM_ID = Q.ITEM_ID AND Q.RN = 1 ORDER BY Q.SUP_ID, Q.CURR_CODE, O.ITEM_CODE, O.REQ_DUE, O.REQ_NO");
if ($stmtOS !== false) while ($o = sqlsrv_fetch_array($stmtOS, SQLSRV_FETCH_ASSOC)) $osReqAuto[] = array("ITEM_ID"=>intval($o["ITEM_ID"]),"ITEM_CODE"=>trim((string)$o["ITEM_CODE"]),"ITEM_NAME"=>trim((string)$o["ITEM_NAME"]),"ITEM_UNIT"=>trim((string)$o["ITEM_UNIT"]),"REQ_ID"=>intval($o["REQ_ID"]),"REQ_DUE"=>fmt_date($o["REQ_DUE"]),"REQ_DUE_VIEW"=>fmt_date_view($o["REQ_DUE"]),"REQ_DATE"=>fmt_date($o["REQ_DATE"]),"REQ_DATE_VIEW"=>fmt_date_view($o["REQ_DATE"]),"REQ_NO"=>trim((string)$o["REQ_NO"]),"REQD_QTY"=>floatval($o["REQD_QTY"]),"POD_QTY"=>floatval($o["POD_QTY"]),"REQ_BQTY"=>floatval($o["REQ_BQTY"]),"SUP_ID"=>intval($o["SUP_ID"]),"CURR_CODE"=>trim((string)$o["CURR_CODE"]),"QUOD_PRICE"=>floatval($o["QUOD_PRICE"]));

// 8. DATA PO AUTOCOMPLETE
$stmtPoAuto = sqlsrv_query($conn, "SELECT TOP 500 P.PO_ID, P.PO_NUM, P.PO_DATE, S.SUP_CODE, S.SUP_COMP FROM dbo.PO P LEFT JOIN dbo.SUPPLIER S ON P.SUP_ID = S.SUP_ID ORDER BY P.PO_DATE DESC, P.PO_NUM DESC");
if ($stmtPoAuto !== false) while ($pa = sqlsrv_fetch_array($stmtPoAuto, SQLSRV_FETCH_ASSOC)) $poAuto[] = array("PO_ID" => intval($pa["PO_ID"]), "PO_NUM" => trim((string)$pa["PO_NUM"]), "PO_DATE" => fmt_date_view($pa["PO_DATE"]), "SUP_CODE" => trim((string)$pa["SUP_CODE"]), "SUP_COMP" => trim((string)$pa["SUP_COMP"]));


// ==========================================================
// LOAD DATA DEPENDING ON ACTIVE TAB
// ==========================================================
$q_search = getv("q", "");

// --- LOAD DATA PR ---
$pr_header = array("REQ_ID" => "", "REQ_NO" => "", "REQ_DATE" => date("Y-m-d"), "REQ_DUE" => date("Y-m-d"), "REQ_REM" => "", "REQ_ISAMR" => 0, "REQ_CLOSE" => 0, "DEP_ID" => "");
if ($tab == "pr" && $editId == 0) { $pr_header["REQ_NO"] = generate_req_no($conn, $pr_header["REQ_DATE"]); }
$pr_details = array();
if ($tab == "pr" && $editId > 0) {
    $stmtH = sqlsrv_query($conn, "SELECT * FROM dbo.REQUISITION WHERE REQ_ID = ?", array($editId));
    if ($stmtH !== false && $rh = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC)) {
        foreach ($pr_header as $k => $v) if (isset($rh[$k])) $pr_header[$k] = $rh[$k];
        $pr_header["REQ_DATE"] = fmt_date($pr_header["REQ_DATE"]); $pr_header["REQ_DUE"] = fmt_date($pr_header["REQ_DUE"]);
    }
    $stmtD = sqlsrv_query($conn, "SELECT D.*, I.ITEM_CODE, I.ITEM_NAME, I.ITEM_UNIT FROM dbo.REQ_DETAIL D LEFT JOIN dbo.ITEMS I ON D.ITEM_ID = I.ITEM_ID WHERE D.REQ_ID = ? ORDER BY I.ITEM_CODE", array($editId));
    if ($stmtD !== false) while ($rd = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) $pr_details[] = $rd;
}
if (count($pr_details) == 0) { for ($i = 0; $i < 5; $i++) $pr_details[] = array("ITEM_ID" => "", "ITEM_CODE" => "", "ITEM_NAME" => "", "ITEM_UNIT" => "", "REQD_QTY" => "", "REQD_REM" => ""); }

$pr_where = ""; $pr_params = array();
if ($tab == "pr" && $q_search != "") { $pr_where = "WHERE R.REQ_NO LIKE ? OR R.REQ_REM LIKE ? OR DPT.DEP_NAME LIKE ? OR DPT.DEP_CODE LIKE ?"; $pr_params = array("%".$q_search."%", "%".$q_search."%", "%".$q_search."%", "%".$q_search."%"); }
$stmtPrList = sqlsrv_query($conn, "SELECT TOP 300 R.REQ_ID, R.REQ_NO, R.REQ_DATE, R.REQ_DUE, R.REQ_REM, R.REQ_ISAMR, R.REQ_CLOSE, R.DEP_ID, DPT.DEP_CODE, DPT.DEP_NAME, COUNT(D.ITEM_ID) AS DETAIL_COUNT, ISNULL(SUM(D.REQD_QTY), 0) AS TOTAL_QTY FROM dbo.REQUISITION R LEFT JOIN dbo.DEPT DPT ON R.DEP_ID = DPT.DEP_ID LEFT JOIN dbo.REQ_DETAIL D ON R.REQ_ID = D.REQ_ID $pr_where GROUP BY R.REQ_ID, R.REQ_NO, R.REQ_DATE, R.REQ_DUE, R.REQ_REM, R.REQ_ISAMR, R.REQ_CLOSE, R.DEP_ID, DPT.DEP_CODE, DPT.DEP_NAME ORDER BY R.REQ_DATE DESC, R.REQ_NO DESC", $pr_params);

// --- LOAD DATA QUOTATION ---
$quo_header = array("QUO_ID" => "", "SUP_ID" => "", "SUP_CODE" => "", "SUP_COMP" => "", "QUO_NO" => "", "QUO_DATE" => date("Y-m-d"), "CURR_CODE" => "IDR", "QUO_EFFDATE" => date("Y-m-d"));
if ($tab == "quotation" && $editId == 0) { $quo_header["QUO_NO"] = generate_quo_no($conn, $quo_header["QUO_DATE"]); }
$quo_details = array();
if ($tab == "quotation" && $editId > 0) {
    $stmtH = sqlsrv_query($conn, "SELECT Q.*, S.SUP_CODE, S.SUP_COMP FROM dbo.QUOTATION Q LEFT JOIN dbo.SUPPLIER S ON Q.SUP_ID = S.SUP_ID WHERE Q.QUO_ID = ?", array($editId));
    if ($stmtH !== false && $rh = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC)) {
        foreach ($quo_header as $k => $v) if (isset($rh[$k])) $quo_header[$k] = $rh[$k];
        $quo_header["QUO_DATE"] = fmt_date($quo_header["QUO_DATE"]); $quo_header["QUO_EFFDATE"] = fmt_date($quo_header["QUO_EFFDATE"]);
    }
    $stmtD = sqlsrv_query($conn, "SELECT D.*, I.ITEM_CODE, I.ITEM_NAME, I.ITEM_UNIT FROM dbo.QUOT_DETAIL D LEFT JOIN dbo.ITEMS I ON D.ITEM_ID = I.ITEM_ID WHERE D.QUO_ID = ? ORDER BY I.ITEM_CODE", array($editId));
    if ($stmtD !== false) while ($rd = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) $quo_details[] = $rd;
}
if (count($quo_details) == 0) { for ($i = 0; $i < 5; $i++) $quo_details[] = array("ITEM_ID" => "", "ITEM_CODE" => "", "ITEM_NAME" => "", "ITEM_UNIT" => "", "QUOD_PRICE" => "", "QUOD_MINQTY" => "", "QUOD_UNIT" => "", "QUOD_TERM" => "", "QUOD_ACTIVE" => 0); }

$quo_where = ""; $quo_params = array();
if ($tab == "quotation" && $q_search != "") { $quo_where = "WHERE Q.QUO_NO LIKE ? OR S.SUP_CODE LIKE ? OR S.SUP_COMP LIKE ?"; $quo_params = array("%".$q_search."%", "%".$q_search."%", "%".$q_search."%"); }
$stmtQuoList = sqlsrv_query($conn, "SELECT TOP 300 Q.QUO_ID, Q.QUO_NO, Q.QUO_DATE, Q.QUO_EFFDATE, Q.CURR_CODE, S.SUP_CODE, S.SUP_COMP, COUNT(D.ITEM_ID) AS DETAIL_COUNT FROM dbo.QUOTATION Q LEFT JOIN dbo.SUPPLIER S ON Q.SUP_ID = S.SUP_ID LEFT JOIN dbo.QUOT_DETAIL D ON Q.QUO_ID = D.QUO_ID $quo_where GROUP BY Q.QUO_ID, Q.QUO_NO, Q.QUO_DATE, Q.QUO_EFFDATE, Q.CURR_CODE, S.SUP_CODE, S.SUP_COMP ORDER BY Q.QUO_DATE DESC, Q.QUO_NO DESC", $quo_params);

// --- LOAD DATA PO ---
$po_header = array("PO_ID"=>"", "PO_NUM"=>"", "PO_DATE"=>date("Y-m-d"), "PO_DATEDO"=>date("Y-m-d"), "SUP_ID"=>"", "SUP_CODE"=>"", "SUP_COMP"=>"", "PO_TO"=>"", "TO_SUP_CODE"=>"", "TO_SUP_COMP"=>"", "PO_TODEF"=>0, "PO_CUR"=>"IDR", "PO_CLOSE"=>0, "PO_TERM"=>"", "PO_TERMDEL"=>"", "PO_TERMDELSCH"=>0, "PO_REM"=>"");
if ($tab == "po" && $editId == 0) { $po_header["PO_NUM"] = generate_po_num($conn, $po_header["PO_DATE"]); }
$po_details = array();
if ($tab == "po" && $editId > 0) {
    $stmtH = sqlsrv_query($conn, "SELECT P.*, S.SUP_CODE, S.SUP_COMP, ST.SUP_CODE AS TO_SUP_CODE, ST.SUP_COMP AS TO_SUP_COMP FROM dbo.PO P LEFT JOIN dbo.SUPPLIER S ON P.SUP_ID = S.SUP_ID LEFT JOIN dbo.SUPPLIER ST ON P.PO_TO = ST.SUP_ID WHERE P.PO_ID = ?", array($editId));
    if ($stmtH !== false && $rh = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC)) {
        foreach ($po_header as $k=>$v) if (isset($rh[$k])) $po_header[$k] = $rh[$k];
        $po_header["PO_DATE"] = fmt_date($po_header["PO_DATE"]); $po_header["PO_DATEDO"] = fmt_date($po_header["PO_DATEDO"]);
    }
    $stmtD = sqlsrv_query($conn, "SELECT D.*, (ISNULL(D.POD_QTY,0) * ISNULL(D.POD_PRICE,0)) AS POD_AMOUNT, I.ITEM_CODE, I.ITEM_NAME, R.REQ_NO FROM dbo.PO_DETAIL D LEFT JOIN dbo.ITEMS I ON D.ITEM_ID = I.ITEM_ID LEFT JOIN dbo.REQUISITION R ON D.REQ_ID = R.REQ_ID WHERE D.PO_ID = ? ORDER BY I.ITEM_CODE, R.REQ_NO", array($editId));
    if ($stmtD !== false) while ($rd = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) $po_details[] = $rd;
}
if (count($po_details) == 0) { for ($i=0; $i<4; $i++) $po_details[] = array("REQ_ID"=>"", "ITEM_ID"=>"", "ITEM_CODE"=>"", "ITEM_NAME"=>"", "REQ_NO"=>"", "POD_DUE"=>date("Y-m-d"), "POD_QTY"=>"", "POD_PRICE"=>"", "POD_UNIT"=>"", "POD_AMOUNT"=>""); }

$po_where = ""; $po_params = array();
if ($tab == "po" && $q_search != "") { $po_where = "WHERE P.PO_NUM LIKE ? OR S.SUP_CODE LIKE ? OR S.SUP_COMP LIKE ?"; $po_params = array("%".$q_search."%","%".$q_search."%","%".$q_search."%"); }
$stmtPoList = sqlsrv_query($conn, "SELECT TOP 300 P.PO_ID, P.PO_NUM, P.PO_DATE, P.PO_DATEDO, P.PO_CUR, P.PO_CLOSE, S.SUP_CODE, S.SUP_COMP, COUNT(D.ITEM_ID) AS DETAIL_COUNT, ISNULL(SUM(ISNULL(D.POD_QTY,0) * ISNULL(D.POD_PRICE,0)),0) AS TOTAL_AMOUNT FROM dbo.PO P LEFT JOIN dbo.SUPPLIER S ON P.SUP_ID = S.SUP_ID LEFT JOIN dbo.PO_DETAIL D ON P.PO_ID = D.PO_ID $po_where GROUP BY P.PO_ID, P.PO_NUM, P.PO_DATE, P.PO_DATEDO, P.PO_CUR, P.PO_CLOSE, S.SUP_CODE, S.SUP_COMP ORDER BY P.PO_DATE DESC, P.PO_NUM DESC", $po_params);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchasing System - PR, Quotation, PO</title>

    <style>
        /* CSS Khusus untuk memastikan Layout selaras dengan AdminLTE & Bootstrap */
        body { background-color: #ecf0f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .box { background: #ffffff; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; border-top: 3px solid #d2d6de; }
        .box.box-primary { border-top-color: #3c8dbc; }
        .box.box-success { border-top-color: #00a65a; }
        .box.box-warning { border-top-color: #f39c12; }
        .box-header { padding: 10px 15px; border-bottom: 1px solid #f4f4f4; display: flex; justify-content: space-between; align-items: center; }
        .box-title { font-size: 16px; margin: 0; font-weight: bold; display: inline-block; }
        .box-body { padding: 15px; }
        
        .form-control { width: 100%; border-radius: 4px; border: 1px solid #ccc; height: 30px; padding: 4px 10px; font-size: 13px; box-shadow: none; transition: border-color 0.15s ease-in-out; }
        .form-control:focus { border-color: #3c8dbc; outline: 0; }
        .form-control[readonly] { background-color: #e6e6e6; cursor: not-allowed; }
        
        .btn { border-radius: 4px; font-size: 12px; font-weight: bold; padding: 5px 12px; cursor: pointer; border: 1px solid transparent; transition: background-color 0.2s; display: inline-block; text-decoration: none; }
        .btn-success { background-color: #00a65a; color: #fff; border-color: #008d4c; }
        .btn-success:hover { background-color: #008d4c; }
        .btn-primary { background-color: #3c8dbc; color: #fff; border-color: #367fa9; }
        .btn-primary:hover { background-color: #286090; }
        .btn-default { background-color: #f4f4f4; color: #444; border-color: #ddd; }
        .btn-danger { background-color: #dd4b39; color: #fff; border-color: #d73925; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { font-weight: 600; margin-bottom: 5px; display: inline-block; font-size: 12px; color: #333; }
        
        /* Styling Tabs */
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

        /* PERBAIKAN: Layout Grid Tabel Standar Dirapatkan */
        .grid-table { width: 100%; border-collapse: collapse; background: #fff; table-layout: fixed; }
        .grid-table th, .grid-table td { 
            border: 1px solid #888; 
            padding: 2px !important; /* Rapatkan padding cell */
            height: 24px !important; /* Kurangi tinggi baris */
            font-size: 12px; 
            white-space: nowrap; 
            overflow: hidden; 
            vertical-align: middle; 
        }
        .grid-table th { background: #d9d6ce; font-weight: bold; text-align: left; }
        .grid-table tbody tr:hover { background-color: #cce5ff; cursor: pointer;}
        
        /* PERBAIKAN: Input di dalam grid-table dirapatkan */
        .grid-table input, .grid-table select { 
            width: 100%; 
            height: 22px !important; /* Tinggi input diturunkan agar pas di dalam baris */
            border: none; 
            padding: 0 4px; 
            box-sizing: border-box; 
            font-size: 12px; 
            margin: 0;
            background: transparent;
        }
        .grid-table input:focus, .grid-table select:focus { outline: 1px solid #2f65d9; background: #fff; }
        
        /* Tombol kecil di dalam grid-table */
        .grid-table .btn-xs {
            padding: 1px 6px !important;
            font-size: 11px;
            height: 20px;
            line-height: 1.2;
            margin: 0;
        }

        .num { text-align: right; }
        .center { text-align: center; }

        .ac-box { position: absolute; z-index: 9999; background: #fff; color: #000; border: 1px solid #333; max-height: 210px; overflow-y: auto; min-width: 280px; display: none; font-size: 12px; box-shadow: 2px 2px 5px rgba(0,0,0,0.3); }
        .ac-item { padding: 4px 6px; cursor: pointer; border-bottom: 1px solid #ddd; }
        .ac-item:hover, .ac-item.active { background: #2f65d9; color: #fff; }
    </style>
</head>
<body style="padding: 15px;">

<div class="row">
    <div class="col-md-12">
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                <li class="<?php echo $tab == 'pr' ? 'active' : ''; ?> tab-li"><a href="<?php echo $urlBase; ?>tab=pr" onclick="openTab(event, 'pr')"><i class="fa fa-file-text-o"></i> PURCHASE REQUISITION (PR)</a></li>
                <li class="<?php echo $tab == 'quotation' ? 'active' : ''; ?> tab-li"><a href="<?php echo $urlBase; ?>tab=quotation" onclick="openTab(event, 'quotation')"><i class="fa fa-file-pdf-o"></i> QUOTATION</a></li>
                <li class="<?php echo $tab == 'po' ? 'active' : ''; ?> tab-li"><a href="<?php echo $urlBase; ?>tab=po" onclick="openTab(event, 'po')"><i class="fa fa-shopping-cart"></i> PURCHASE ORDER (PO)</a></li>
            </ul>
            
            <div class="tab-content">
                <?php if ($message != "") { echo '<div class="alert alert-success" style="padding:8px; margin-bottom:15px; font-weight:bold;">'.h($message).'</div>'; } ?>
                <?php if ($error != "") { echo '<div class="alert alert-danger" style="padding:8px; margin-bottom:15px; font-weight:bold;">'.h($error).'</div>'; } ?>

                <!-- ==========================================
                     TAB 1: PURCHASE REQUISITION (PR)
                =========================================== -->
                <div id="pr" class="tab-pane <?php echo $tab == 'pr' ? 'active' : ''; ?>">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-list"></i> Form Purchase Requisition (PR)</h3>
                            <div class="box-tools pull-right">
                                <button type="button" class="btn btn-default btn-sm" onclick="window.location.href='<?php echo $urlBase; ?>tab=pr'">NEW PR</button>
                                <button type="button" class="btn btn-danger btn-sm" onclick="pr_deleteCurrent()">HAPUS HEADER</button>
                                <button type="submit" form="prForm" class="btn btn-success btn-sm">SIMPAN PR</button>
                            </div>
                        </div>
                        <div class="box-body">
                            <form id="prForm" method="post" action="<?php echo $urlBase; ?>tab=pr">
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="req_id" id="pr_req_id" value="<?php echo h($pr_header["REQ_ID"]); ?>">
                                <input type="hidden" name="allow_empty_detail" id="pr_allow_empty_detail" value="0">
                                
                                <div class="row">
                                    <div class="col-md-2 form-group"><label>REQ NO</label><input type="text" name="req_no" class="form-control" value="<?php echo h($pr_header["REQ_NO"]); ?>" placeholder="Auto" onkeydown="pr_headerKey(event)"></div>
                                    <div class="col-md-2 form-group"><label>REQ DATE</label><input type="date" name="req_date" class="form-control" value="<?php echo h($pr_header["REQ_DATE"]); ?>" onkeydown="pr_headerKey(event)"></div>
                                    <div class="col-md-2 form-group"><label>REQ DUE</label><input type="date" name="req_due" class="form-control" value="<?php echo h($pr_header["REQ_DUE"]); ?>" onkeydown="pr_headerKey(event)"></div>
                                    <div class="col-md-3 form-group">
                                        <label>DEPT</label>
                                        <select name="dep_id" class="form-control" onkeydown="pr_headerKey(event)">
                                            <option value="">-- Pilih Dept --</option>
                                            <?php foreach ($deptList as $d) { echo '<option value="'.h($d["DEP_ID"]).'" '.(intval($pr_header["DEP_ID"])==intval($d["DEP_ID"])?"selected":"").'>'.h($d["DEP_CODE"]." - ".$d["DEP_NAME"]).'</option>'; } ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 form-group" style="padding-top: 23px;">
                                        <label style="margin-right: 15px;"><input type="checkbox" name="req_isamr" value="1" <?php echo intval($pr_header["REQ_ISAMR"])==1?"checked":""; ?>> Is AMR</label>
                                        <label><input type="checkbox" name="req_close" value="1" <?php echo intval($pr_header["REQ_CLOSE"])==1?"checked":""; ?>> Close</label>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12 form-group"><label>REQ REMARK</label><input type="text" name="req_rem" class="form-control" value="<?php echo h($pr_header["REQ_REM"]); ?>" onkeydown="pr_headerKey(event)"></div>
                                </div>

                                <div style="overflow-x:auto;">
                                    <table class="grid-table" style="margin-top: 10px;">
                                        <thead>
                                            <tr>
                                                <th style="width:35px;">No</th><th style="width:120px;">ITEM CODE</th><th>ITEM NAME</th>
                                                <th style="width:80px;">UNIT</th><th style="width:90px;">QTY</th><th style="width:170px;">REMARK</th>
                                                <th style="width:75px;">PO / X</th>
                                            </tr>
                                        </thead>
                                        <tbody id="pr_detailBody">
                                            <?php for ($i=0; $i<count($pr_details); $i++) { $d = $pr_details[$i]; $qty = isset($d["REQD_QTY"])?floatval($d["REQD_QTY"]):0; ?>
                                                <tr>
                                                    <td class="center pr-row-no"><?php echo h($i + 1); ?></td>
                                                    <td>
                                                        <input type="hidden" name="item_id[]" id="pr_item_id_<?php echo h($i); ?>" value="<?php echo h($d["ITEM_ID"]); ?>">
                                                        <input type="text" name="item_code[]" id="pr_item_code_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_CODE"])?$d["ITEM_CODE"]:""); ?>" autocomplete="off" oninput="pr_showItemAC(this, <?php echo h($i); ?>)" onkeydown="pr_itemKey(event, this, <?php echo h($i); ?>)">
                                                    </td>
                                                    <td><input type="text" name="item_name[]" id="pr_item_name_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_NAME"])?$d["ITEM_NAME"]:""); ?>" autocomplete="off" oninput="pr_showItemAC(this, <?php echo h($i); ?>)" onkeydown="pr_itemKey(event, this, <?php echo h($i); ?>)"></td>
                                                    <td><input type="text" name="item_unit[]" id="pr_item_unit_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_UNIT"])?$d["ITEM_UNIT"]:""); ?>" readonly></td>
                                                    <td><input type="text" name="reqd_qty[]" id="pr_reqd_qty_<?php echo h($i); ?>" class="num pr-qty-input" value="<?php echo h($qty==0?"":$qty); ?>" onkeydown="pr_qtyKey(event, this)"></td>
                                                    <td><input type="text" name="reqd_rem[]" value="<?php echo h(isset($d["REQD_REM"])?$d["REQD_REM"]:""); ?>" onkeydown="pr_headerKey(event)"></td>
                                                    <td><button type="button" class="btn btn-default btn-xs" onclick="pr_showPO(this)">PO</button> <button type="button" class="btn btn-danger btn-xs" onclick="pr_deleteRow(this)">X</button></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" class="btn btn-default" style="margin-top:10px;" onclick="pr_addRow()">+ Tambah Baris</button>
                            </form>
                            
                            <form id="pr_deleteForm" method="post" action="<?php echo $urlBase; ?>tab=pr">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="req_id" id="pr_delete_req_id" value="">
                            </form>
                        </div>
                        
                        <div class="box-footer">
                            <form method="get" action="" class="form-inline">
                                <input type="hidden" name="page" value="<?php echo htmlspecialchars($pageName); ?>">
                                <input type="hidden" name="tab" value="pr">
                                <div class="form-group" style="margin:0;">
                                    <input type="text" name="q" id="pr_req_search" value="<?php echo h($q_search); ?>" class="form-control" style="width:280px;" placeholder="REQ No / Dept / Remark" autocomplete="off" oninput="pr_showReqAC(this)" onkeydown="pr_reqSearchKey(event, this)">
                                </div>
                                <button type="submit" class="btn btn-primary">SEARCH</button>
                                <a href="<?php echo $urlBase; ?>tab=pr" class="btn btn-default">ALL</a>
                            </form>
                        </div>
                    </div>
                    
                    <div style="height: 200px; overflow: auto; border:1px solid #ddd; background:#fff;">
                        <table class="grid-table">
                            <thead><tr><th style="width:100px;">REQ_NO</th><th style="width:90px;">REQ_DATE</th><th style="width:90px;">REQ_DUE</th><th style="width:120px;">DEPT</th><th>REQ_REM</th><th style="width:65px;">AMR</th><th style="width:65px;">CLOSE</th><th style="width:70px;">DETAIL</th><th style="width:80px;">QTY</th></tr></thead>
                            <tbody>
                                <?php if($stmtPrList !== false) { while ($r = sqlsrv_fetch_array($stmtPrList, SQLSRV_FETCH_ASSOC)) { ?>
                                    <tr onclick="window.location.href='<?php echo $urlBase; ?>tab=pr&edit=<?php echo h($r["REQ_ID"]); ?>'">
                                        <td><?php echo h($r["REQ_NO"]); ?></td><td><?php echo h(fmt_date_view($r["REQ_DATE"])); ?></td><td><?php echo h(fmt_date_view($r["REQ_DUE"])); ?></td><td><?php echo h(trim((string)$r["DEP_CODE"])." ".trim((string)$r["DEP_NAME"])); ?></td><td><?php echo h($r["REQ_REM"]); ?></td><td class="center"><?php echo intval($r["REQ_ISAMR"])==1?"YES":""; ?></td><td class="center"><?php echo intval($r["REQ_CLOSE"])==1?"YES":""; ?></td><td class="num"><?php echo h(number_format(floatval($r["DETAIL_COUNT"]),0)); ?></td><td class="num"><?php echo h(number_format(floatval($r["TOTAL_QTY"]),2)); ?></td>
                                    </tr>
                                <?php } } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==========================================
                     TAB 2: QUOTATION
                =========================================== -->
                <div id="quotation" class="tab-pane <?php echo $tab == 'quotation' ? 'active' : ''; ?>">
                    <div class="box box-warning">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-file-pdf-o"></i> Data Quotation Supplier</h3>
                            <div class="box-tools pull-right">
                                <button type="button" class="btn btn-default btn-sm" onclick="window.location.href='<?php echo $urlBase; ?>tab=quotation'">NEW QUOTATION</button>
                                <button type="button" class="btn btn-danger btn-sm" onclick="quo_deleteCurrent()">HAPUS HEADER</button>
                                <button type="submit" form="quoForm" class="btn btn-success btn-sm">SIMPAN QUOTATION</button>
                            </div>
                        </div>
                        <div class="box-body">
                            <form id="quoForm" method="post" action="<?php echo $urlBase; ?>tab=quotation">
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="quo_id" id="quo_quo_id" value="<?php echo h($quo_header["QUO_ID"]); ?>">
                                <input type="hidden" name="sup_id" id="quo_sup_id" value="<?php echo h($quo_header["SUP_ID"]); ?>">
                                <input type="hidden" name="allow_empty_detail" id="quo_allow_empty_detail" value="0">

                                <div class="row">
                                    <div class="col-md-2 form-group"><label>Supplier</label><input type="text" name="sup_code" id="quo_sup_code" class="form-control text-primary" style="font-weight:bold;" value="<?php echo h($quo_header["SUP_CODE"]); ?>" autocomplete="off" oninput="quo_showSupplierAC(this)" onkeydown="quo_supplierKey(event, this)"></div>
                                    <div class="col-md-4 form-group"><label>Company</label><input type="text" name="sup_comp" id="quo_sup_comp" class="form-control" value="<?php echo h($quo_header["SUP_COMP"]); ?>" autocomplete="off" oninput="quo_showSupplierAC(this)" onkeydown="quo_supplierKey(event, this)"></div>
                                    <div class="col-md-2 form-group"><label>No. QUO</label><input type="text" name="quo_no" id="quo_quo_no" class="form-control" value="<?php echo h($quo_header["QUO_NO"]); ?>" placeholder="Auto" onkeydown="quo_headerKey(event)"></div>
                                    <div class="col-md-1 form-group">
                                        <label>Curr.</label>
                                        <select name="curr_code" id="quo_curr_code" class="form-control" onkeydown="quo_headerKey(event)">
                                            <?php foreach ($currList as $c) { echo '<option value="'.h($c).'" '.(trim((string)$quo_header["CURR_CODE"])==$c?"selected":"").'>'.h($c).'</option>'; } ?>
                                        </select>
                                    </div>
                                    <div class="col-md-1 form-group"><label>Date</label><input type="date" name="quo_date" class="form-control" value="<?php echo h($quo_header["QUO_DATE"]); ?>" onkeydown="quo_headerKey(event)"></div>
                                    <div class="col-md-2 form-group"><label>End Date</label><input type="date" name="quo_effdate" class="form-control" value="<?php echo h($quo_header["QUO_EFFDATE"]); ?>" onkeydown="quo_headerKey(event)"></div>
                                </div>

                                <div style="overflow-x:auto;">
                                    <table class="grid-table" style="margin-top: 10px;">
                                        <thead>
                                            <tr>
                                                <th style="width:35px;">No</th><th style="width:110px;">Code</th><th>Item Name</th>
                                                <th style="width:65px;">Unit</th><th style="width:90px;">Price</th><th style="width:80px;">Min.Qty</th>
                                                <th style="width:120px;">Term</th><th style="width:75px;">Active</th><th style="width:85px;">OS/PO / X</th>
                                            </tr>
                                        </thead>
                                        <tbody id="quo_detailBody">
                                            <?php for ($i=0; $i<count($quo_details); $i++) { $d = $quo_details[$i]; $price = isset($d["QUOD_PRICE"])?floatval($d["QUOD_PRICE"]):0; $minqty = isset($d["QUOD_MINQTY"])?floatval($d["QUOD_MINQTY"]):0; $active = isset($d["QUOD_ACTIVE"])?intval($d["QUOD_ACTIVE"]):0; ?>
                                                <tr>
                                                    <td class="center quo-row-no"><?php echo h($i + 1); ?></td>
                                                    <td>
                                                        <input type="hidden" name="item_id[]" id="quo_item_id_<?php echo h($i); ?>" value="<?php echo h($d["ITEM_ID"]); ?>">
                                                        <input type="text" name="item_code[]" id="quo_item_code_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_CODE"])?$d["ITEM_CODE"]:""); ?>" autocomplete="off" oninput="quo_showItemAC(this, <?php echo h($i); ?>)" onkeydown="quo_itemKey(event, this, <?php echo h($i); ?>)">
                                                    </td>
                                                    <td><input type="text" name="item_name[]" id="quo_item_name_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_NAME"])?$d["ITEM_NAME"]:""); ?>" autocomplete="off" oninput="quo_showItemAC(this, <?php echo h($i); ?>)" onkeydown="quo_itemKey(event, this, <?php echo h($i); ?>)"></td>
                                                    <td><input type="text" name="quod_unit[]" id="quo_quod_unit_<?php echo h($i); ?>" value="<?php echo h(isset($d["QUOD_UNIT"])?$d["QUOD_UNIT"]:""); ?>"></td>
                                                    <td><input type="text" name="quod_price[]" id="quo_quod_price_<?php echo h($i); ?>" class="num" value="<?php echo h($price==0?"":$price); ?>" onkeydown="quo_fieldEnterSave(event)"></td>
                                                    <td><input type="text" name="quod_minqty[]" class="num" value="<?php echo h($minqty==0?"":$minqty); ?>" onkeydown="quo_fieldEnterSave(event)"></td>
                                                    <td><input type="text" name="quod_term[]" value="<?php echo h(isset($d["QUOD_TERM"])?$d["QUOD_TERM"]:""); ?>" onkeydown="quo_fieldEnterSave(event)"></td>
                                                    <td class="center"><input type="checkbox" name="quod_active[<?php echo h($i); ?>]" value="1" <?php echo $active==1?"checked":""; ?>></td>
                                                    <td><button type="button" class="btn btn-default btn-xs" onclick="quo_showHistory(this)">OS/PO</button> <button type="button" class="btn btn-danger btn-xs" onclick="quo_deleteRow(this)">X</button></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" class="btn btn-default" style="margin-top:10px;" onclick="quo_addRow()">+ Tambah Baris</button>
                            </form>
                            <form id="quo_deleteForm" method="post" action="<?php echo $urlBase; ?>tab=quotation">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="quo_id" id="quo_delete_quo_id" value="">
                            </form>
                        </div>

                        <div class="box-footer">
                            <form method="get" action="" class="form-inline">
                                <input type="hidden" name="page" value="<?php echo htmlspecialchars($pageName); ?>">
                                <input type="hidden" name="tab" value="quotation">
                                <div class="form-group" style="margin:0;">
                                    <input type="text" name="q" id="quo_search" value="<?php echo h($q_search); ?>" class="form-control" style="width:300px;" placeholder="Quotation No / Supplier" autocomplete="off" oninput="quo_showQuoAC(this)" onkeydown="quo_quoSearchKey(event, this)">
                                </div>
                                <button type="submit" class="btn btn-primary">SEARCH</button>
                                <a href="<?php echo $urlBase; ?>tab=quotation" class="btn btn-default">ALL</a>
                            </form>
                        </div>
                    </div>

                     <div style="height: 200px; overflow: auto; border:1px solid #ddd; background:#fff;">
                        <table class="grid-table">
                            <thead><tr><th style="width:110px;">QUO_NO</th><th style="width:90px;">DATE</th><th style="width:90px;">END DATE</th><th style="width:70px;">CURR</th><th style="width:100px;">SUPPLIER</th><th>COMPANY</th><th style="width:70px;">DETAIL</th></tr></thead>
                            <tbody>
                                <?php if($stmtQuoList !== false) { while ($r = sqlsrv_fetch_array($stmtQuoList, SQLSRV_FETCH_ASSOC)) { ?>
                                    <tr onclick="window.location.href='<?php echo $urlBase; ?>tab=quotation&edit=<?php echo h($r["QUO_ID"]); ?>'">
                                        <td><?php echo h($r["QUO_NO"]); ?></td><td><?php echo h(fmt_date_view($r["QUO_DATE"])); ?></td><td><?php echo h(fmt_date_view($r["QUO_EFFDATE"])); ?></td><td class="center"><?php echo h($r["CURR_CODE"]); ?></td><td><?php echo h($r["SUP_CODE"]); ?></td><td><?php echo h($r["SUP_COMP"]); ?></td><td class="num"><?php echo h(number_format(floatval($r["DETAIL_COUNT"]),0)); ?></td>
                                    </tr>
                                <?php } } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==========================================
                     TAB 3: PURCHASE ORDER (PO)
                =========================================== -->
                <div id="po" class="tab-pane <?php echo $tab == 'po' ? 'active' : ''; ?>">
                    <div class="box box-success">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-shopping-cart"></i> Manajemen Purchase Order (PO)</h3>
                            <div class="box-tools pull-right">
                                <button type="button" class="btn btn-default btn-sm" onclick="window.location.href='<?php echo $urlBase; ?>tab=po'">NEW PO</button>
                                <button type="button" class="btn btn-danger btn-sm" onclick="po_deleteCurrent()">HAPUS HEADER</button>
                                <button type="submit" form="poForm" class="btn btn-success btn-sm">SIMPAN PO</button>
                            </div>
                        </div>
                        <div class="box-body">
                            <form id="poForm" method="post" action="<?php echo $urlBase; ?>tab=po">
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="po_id" id="po_po_id" value="<?php echo h($po_header["PO_ID"]); ?>">
                                <input type="hidden" name="sup_id" id="po_sup_id" value="<?php echo h($po_header["SUP_ID"]); ?>">
                                <input type="hidden" name="po_to" id="po_po_to" value="<?php echo h($po_header["PO_TO"]); ?>">
                                <input type="hidden" name="allow_empty_detail" id="po_allow_empty_detail" value="0">

                                <div class="row">
                                    <div class="col-md-2 form-group"><label>PO No.</label><input type="text" name="po_num" id="po_po_num" class="form-control text-success" style="font-weight:bold;" value="<?php echo h($po_header["PO_NUM"]); ?>" placeholder="Auto" onkeydown="po_headerKey(event)"></div>
                                    <div class="col-md-2 form-group"><label>Date</label><input type="date" name="po_date" class="form-control" value="<?php echo h($po_header["PO_DATE"]); ?>" onkeydown="po_headerKey(event)"></div>
                                    <div class="col-md-2 form-group"><label>Date DO</label><input type="date" name="po_datedo" class="form-control" value="<?php echo h($po_header["PO_DATEDO"]); ?>" onkeydown="po_headerKey(event)"></div>
                                    <div class="col-md-1 form-group" style="padding-top: 23px;"><label><input type="checkbox" name="po_close" value="1" <?php echo intval($po_header["PO_CLOSE"])==1?"checked":""; ?>> Close</label></div>
                                    <div class="col-md-2 form-group"><label>Supplier</label><input type="text" name="sup_code" id="po_sup_code" class="form-control" value="<?php echo h($po_header["SUP_CODE"]); ?>" autocomplete="off" oninput="po_showSupplierAC(this, 'sup')" onkeydown="po_supplierKey(event, this, 'sup')"></div>
                                    <div class="col-md-3 form-group"><label>Company</label><input type="text" name="sup_comp" id="po_sup_comp" class="form-control" value="<?php echo h($po_header["SUP_COMP"]); ?>" autocomplete="off" oninput="po_showSupplierAC(this, 'sup')" onkeydown="po_supplierKey(event, this, 'sup')"></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3 form-group"><label>Terms.of Pmt</label><input type="text" name="po_term" id="po_po_term" class="form-control" value="<?php echo h($po_header["PO_TERM"]); ?>" onkeydown="po_headerKey(event)"></div>
                                    <div class="col-md-3 form-group"><label>Term.of Delv</label><input type="text" name="po_termdel" id="po_po_termdel" class="form-control" value="<?php echo h($po_header["PO_TERMDEL"]); ?>" onkeydown="po_headerKey(event)"></div>
                                    <div class="col-md-1 form-group">
                                        <label>Cur.</label>
                                        <select name="po_cur" id="po_po_cur" class="form-control" onkeydown="po_headerKey(event)">
                                            <?php foreach($currList as $c) { echo '<option value="'.h($c).'" '.(trim((string)$po_header["PO_CUR"])==$c?"selected":"").'>'.h($c).'</option>'; } ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2 form-group"><label>Invoice To</label><input type="text" name="to_sup_code" id="po_to_sup_code" class="form-control" value="<?php echo h($po_header["TO_SUP_CODE"]); ?>" autocomplete="off" oninput="po_showSupplierAC(this, 'to')" onkeydown="po_supplierKey(event, this, 'to')"></div>
                                    <div class="col-md-3 form-group"><label>Invoice Company</label><input type="text" name="to_sup_comp" id="po_to_sup_comp" class="form-control" value="<?php echo h($po_header["TO_SUP_COMP"]); ?>" autocomplete="off" oninput="po_showSupplierAC(this, 'to')" onkeydown="po_supplierKey(event, this, 'to')"></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-group"><label>Remark</label><input type="text" name="po_rem" class="form-control" value="<?php echo h($po_header["PO_REM"]); ?>" onkeydown="po_headerKey(event)"></div>
                                    <div class="col-md-6 form-group" style="padding-top: 23px;">
                                        <label style="margin-right:15px;"><input type="checkbox" name="po_todef" value="1" <?php echo intval($po_header["PO_TODEF"])==1?"checked":""; ?>> PT. IMC TEKNO</label>
                                        <label><input type="checkbox" name="po_termdelsch" value="1" <?php echo intval($po_header["PO_TERMDELSCH"])==1?"checked":""; ?>> Delivery Schedule</label>
                                    </div>
                                </div>

                                <div style="margin-bottom:10px;">
                                    <button type="button" class="btn btn-default btn-sm" onclick="po_printPO()"><i class="fa fa-print"></i> Print PO</button>
                                </div>

                                <div style="overflow-x:auto;">
                                    <table class="grid-table" style="margin-top: 10px;">
                                        <thead>
                                            <tr>
                                                <th style="width:30px;">#</th><th style="width:110px;">Code</th><th>Item Name</th>
                                                <th style="width:110px;">Due On</th><th style="width:70px;">Qty.</th><th style="width:90px;">Rate</th>
                                                <th style="width:60px;">Unit</th><th style="width:110px;">Req.#</th><th style="width:100px;">Amount</th>
                                                <th style="width:40px;">X</th>
                                            </tr>
                                        </thead>
                                        <tbody id="po_detailBody">
                                            <?php for($i=0;$i<count($po_details);$i++) { $d=$po_details[$i]; $qty=isset($d["POD_QTY"])?floatval($d["POD_QTY"]):0; $price=isset($d["POD_PRICE"])?floatval($d["POD_PRICE"]):0; $amount=isset($d["POD_AMOUNT"])?floatval($d["POD_AMOUNT"]):($qty*$price); ?>
                                                <tr onclick="po_selectDetailRow(this)">
                                                    <td class="center po-row-no"><?php echo h($i+1); ?></td>
                                                    <td>
                                                        <input type="hidden" name="req_id[]" id="po_req_id_<?php echo h($i); ?>" value="<?php echo h($d["REQ_ID"]); ?>">
                                                        <input type="hidden" name="item_id[]" id="po_item_id_<?php echo h($i); ?>" value="<?php echo h($d["ITEM_ID"]); ?>">
                                                        <input type="text" name="item_code[]" id="po_item_code_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_CODE"])?$d["ITEM_CODE"]:""); ?>" autocomplete="off" oninput="po_showOSAC(this, <?php echo h($i); ?>)" onkeydown="po_osKey(event, this, <?php echo h($i); ?>)">
                                                    </td>
                                                    <td><input type="text" name="item_name[]" id="po_item_name_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_NAME"])?$d["ITEM_NAME"]:""); ?>" autocomplete="off" oninput="po_showOSAC(this, <?php echo h($i); ?>)" onkeydown="po_osKey(event, this, <?php echo h($i); ?>)"></td>
                                                    <td><input type="date" name="pod_due[]" id="po_pod_due_<?php echo h($i); ?>" value="<?php echo h(isset($d["POD_DUE"])?fmt_date($d["POD_DUE"]):date("Y-m-d")); ?>" onkeydown="po_fieldEnterSave(event, <?php echo h($i); ?>)"></td>
                                                    <td><input type="text" name="pod_qty[]" id="po_pod_qty_<?php echo h($i); ?>" class="num" value="<?php echo h($qty==0?"":$qty); ?>" onkeyup="po_calcAmount(<?php echo h($i); ?>)" onkeydown="po_fieldEnterSave(event, <?php echo h($i); ?>)"></td>
                                                    <td><input type="text" name="pod_price[]" id="po_pod_price_<?php echo h($i); ?>" class="num" value="<?php echo h($price==0?"":$price); ?>" onkeyup="po_calcAmount(<?php echo h($i); ?>)" onkeydown="po_fieldEnterSave(event, <?php echo h($i); ?>)"></td>
                                                    <td><input type="text" name="pod_unit[]" id="po_pod_unit_<?php echo h($i); ?>" value="<?php echo h(isset($d["POD_UNIT"])?$d["POD_UNIT"]:""); ?>" onkeydown="po_fieldEnterSave(event, <?php echo h($i); ?>)"></td>
                                                    <td><input type="text" name="req_no[]" id="po_req_no_<?php echo h($i); ?>" value="<?php echo h(isset($d["REQ_NO"])?$d["REQ_NO"]:""); ?>" readonly></td>
                                                    <td><input type="text" id="po_pod_amount_<?php echo h($i); ?>" class="num" value="<?php echo h($amount==0?"":number_format($amount,2,".","")); ?>" readonly></td>
                                                    <td><button type="button" class="btn btn-danger btn-xs" onclick="po_deleteRow(this); event.stopPropagation();">X</button></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div style="margin-top:10px; display:flex; justify-content:space-between;">
                                    <button type="button" class="btn btn-default" onclick="po_addRow()">+ Tambah Baris</button>
                                    <button type="button" class="btn btn-primary" onclick="po_showReceive()">Show Receive</button>
                                </div>
                            </form>
                            <form id="po_deleteForm" method="post" action="<?php echo $urlBase; ?>tab=po">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="po_id" id="po_delete_po_id" value="">
                            </form>
                        </div>
                        
                        <div class="box-footer">
                            <form method="get" action="" class="form-inline">
                                <input type="hidden" name="page" value="<?php echo htmlspecialchars($pageName); ?>">
                                <input type="hidden" name="tab" value="po">
                                <div class="form-group" style="margin:0;">
                                    <input type="text" name="q" id="po_search_input" value="<?php echo h($q_search); ?>" class="form-control" style="width:300px;" placeholder="PO No / Supplier" autocomplete="off" oninput="po_showPoAC(this)" onkeydown="po_poSearchKey(event, this)">
                                </div>
                                <button type="submit" class="btn btn-primary">SEARCH</button>
                                <a href="<?php echo $urlBase; ?>tab=po" class="btn btn-default">ALL</a>
                            </form>
                        </div>
                    </div>

                    <div style="height: 200px; overflow: auto; border:1px solid #ddd; background:#fff;">
                        <table class="grid-table">
                            <thead><tr><th style="width:130px;">PO No</th><th style="width:90px;">Date</th><th style="width:90px;">Date DO</th><th style="width:50px;">Cur</th><th style="width:80px;">Sup.Code</th><th>Supplier</th><th style="width:55px;">Close</th><th style="width:60px;">Detail</th><th style="width:100px;">Amount</th></tr></thead>
                            <tbody>
                                <?php if($stmtPoList !== false) { while($r=sqlsrv_fetch_array($stmtPoList, SQLSRV_FETCH_ASSOC)){ ?>
                                    <tr onclick="window.location.href='<?php echo $urlBase; ?>tab=po&edit=<?php echo h($r["PO_ID"]); ?>'">
                                        <td><?php echo h($r["PO_NUM"]); ?></td><td><?php echo h(fmt_date_view($r["PO_DATE"])); ?></td><td><?php echo h(fmt_date_view($r["PO_DATEDO"])); ?></td><td class="center"><?php echo h($r["PO_CUR"]); ?></td><td><?php echo h($r["SUP_CODE"]); ?></td><td><?php echo h($r["SUP_COMP"]); ?></td><td class="center"><?php echo intval($r["PO_CLOSE"])==1?"YES":""; ?></td><td class="num"><?php echo h(number_format(floatval($r["DETAIL_COUNT"]),0)); ?></td><td class="num"><?php echo h(number_format(floatval($r["TOTAL_AMOUNT"]),2)); ?></td>
                                    </tr>
                                <?php } } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div> <!-- END tab-content -->
        </div> <!-- END nav-tabs-custom -->
        
    </div>
</div>

<!-- =========================================================================
     GLOBAL AC BOX & TAB LOGIC
========================================================================== -->
<div id="acBox" class="ac-box"></div>
<script>
    function openTab(evt, tabName) {
        evt.preventDefault(); 
        var tabPanes = document.getElementsByClassName("tab-pane"); for (var i = 0; i < tabPanes.length; i++) tabPanes[i].classList.remove("active");
        var tabLis = document.getElementsByClassName("tab-li"); for (var i = 0; i < tabLis.length; i++) tabLis[i].classList.remove("active");
        document.getElementById(tabName).classList.add("active"); evt.currentTarget.parentElement.classList.add("active");
        var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?page=<?php echo $pageName; ?>&tab=' + tabName;
        window.history.pushState({path:newUrl}, '', newUrl);
    }
    
    function enc(v) { return encodeURIComponent(v == null ? "" : v); }
    function acHtml(v) { return String(v == null ? "" : v).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }
    function byId(id) { return document.getElementById(id); }
    function setValue(id, value) { var el = byId(id); if (el) el.value = value == null ? "" : value; }
    
    var acBox = null; var acItems = []; var acIndex = -1; var acMode = ""; var acRow = -1; var supplierTarget = "sup";
    function initAC() { acBox = byId("acBox"); }
    function hideAC() { if (acBox) { acBox.style.display = "none"; acBox.innerHTML = ""; } acItems = []; acIndex = -1; acMode = ""; acRow = -1; }
    function positionAC(input) { initAC(); var rect = input.getBoundingClientRect(); acBox.style.left = (rect.left + window.scrollX) + "px"; acBox.style.top = (rect.bottom + window.scrollY) + "px"; acBox.style.width = rect.width < 280 ? "280px" : rect.width + "px"; }
    function renderAC(renderText, pickFunc) { initAC(); acBox.innerHTML = ""; for (var i = 0; i < acItems.length; i++) { var div = document.createElement("div"); div.className = "ac-item" + (i == acIndex ? " active" : ""); div.innerHTML = renderText(acItems[i]); div.setAttribute("data-index", i); div.onmousedown = function () { pickFunc(acItems[parseInt(this.getAttribute("data-index"), 10)]); }; acBox.appendChild(div); } acBox.style.display = acItems.length > 0 ? "block" : "none"; }
    function acMove(step, renderText, pickFunc) { if (acItems.length <= 0) return; acIndex += step; if (acIndex < 0) acIndex = acItems.length - 1; if (acIndex >= acItems.length) acIndex = 0; renderAC(renderText, pickFunc); }
    function acEnter(pickFunc) { if (acItems.length <= 0) return false; if (acIndex < 0) acIndex = 0; pickFunc(acItems[acIndex]); return true; }
    
    document.addEventListener("click", function (e) { initAC(); if (acBox && !acBox.contains(e.target)) { if (!e.target || !e.target.getAttribute || e.target.getAttribute("autocomplete") !== "off") hideAC(); } });

    // Arrays pre-loaded via PHP
    var itemData = <?php echo json_encode($itemAuto); ?>;
    var reqData = <?php echo json_encode($reqAuto); ?>;
    var supplierData = <?php echo json_encode($supplierAuto); ?>;
    var quoData = <?php echo json_encode($quoAuto); ?>;
    var osReqData = <?php echo json_encode($osReqAuto); ?>;
    var poData = <?php echo json_encode($poAuto); ?>;
</script>

<!-- =========================================================================
     JAVASCRIPT PURCHASE REQUISITION (PR)
========================================================================== -->
<script>
    var pr_rowSeq = <?php echo count($pr_details); ?>;
    function pr_submitSave(allowEmpty) { var f=byId("prForm"); if(byId("pr_allow_empty_detail")) byId("pr_allow_empty_detail").value=allowEmpty?"1":"0"; if(f) f.submit(); }
    function pr_deleteCurrent() { var id=byId("pr_req_id").value; if(id==""||id=="0"){alert("Pilih requisition dulu.");return;} if(!confirm("Yakin hapus requisition ini?"))return; byId("pr_delete_req_id").value=id; byId("pr_deleteForm").submit(); }
    
    function pr_findItemByCode(code) { code=(code||"").toUpperCase(); for(var i=0;i<itemData.length;i++){ if((itemData[i].ITEM_CODE||"").toUpperCase()==code) return itemData[i]; } return null; }
    function pr_findItemByName(name) { name=(name||"").toUpperCase(); for(var i=0;i<itemData.length;i++){ if((itemData[i].ITEM_NAME||"").toUpperCase()==name) return itemData[i]; } return null; }
    function pr_setRowItem(row, item) { setValue("pr_item_id_"+row, item.ITEM_ID||""); setValue("pr_item_code_"+row, item.ITEM_CODE||""); setValue("pr_item_name_"+row, item.ITEM_NAME||""); setValue("pr_item_unit_"+row, item.ITEM_UNIT||""); }

    function pr_showItemAC(input, row) {
        initAC(); var key=(input.value||"").toUpperCase(); acMode="pr_item"; acRow=row; acItems=[]; acIndex=-1; if(key.length<1){hideAC();return;}
        for(var i=0;i<itemData.length;i++){ var item=itemData[i]; if((item.ITEM_CODE||"").toUpperCase().indexOf(key)>=0 || (item.ITEM_NAME||"").toUpperCase().indexOf(key)>=0){ acItems.push(item); } if(acItems.length>=40) break; }
        positionAC(input); renderAC(function(item){return "<b>"+item.ITEM_CODE+"</b> - "+item.ITEM_NAME;}, pr_pickItem);
    }
    function pr_itemKey(e, input, row) {
        if(e.key==="ArrowDown"){ e.preventDefault(); if(acMode!=="pr_item"||acItems.length==0) pr_showItemAC(input,row); acMove(1, function(item){return "<b>"+item.ITEM_CODE+"</b> - "+item.ITEM_NAME;}, pr_pickItem); return false; }
        if(e.key==="ArrowUp"){ e.preventDefault(); if(acMode!=="pr_item"||acItems.length==0) pr_showItemAC(input,row); acMove(-1, function(item){return "<b>"+item.ITEM_CODE+"</b> - "+item.ITEM_NAME;}, pr_pickItem); return false; }
        if(e.key==="Enter"){ if(acMode==="pr_item"&&acItems.length>0){ e.preventDefault(); acEnter(pr_pickItem); return false; } e.preventDefault(); var item=null; if(input.id.indexOf("pr_item_code_")===0) item=pr_findItemByCode(input.value); else item=pr_findItemByName(input.value); if(item){ pr_setRowItem(row,item); var qty=byId("pr_reqd_qty_"+row); if(qty){qty.focus();qty.select();} } return false; }
        if(e.key==="Escape") hideAC();
    }
    function pr_pickItem(item) { var row=acRow; pr_setRowItem(row,item); hideAC(); var qty=byId("pr_reqd_qty_"+row); if(qty){qty.focus();qty.select();} }
    function pr_qtyKey(e, input) {
        if(e.key==="Enter"){ e.preventDefault(); if(input&&input.value!="") pr_submitSave(false); return false; }
        if(e.key==="ArrowDown"){ e.preventDefault(); var qtys=document.getElementsByClassName("pr-qty-input"); for(var i=0;i<qtys.length;i++){ if(qtys[i]===input&&qtys[i+1]){ qtys[i+1].focus(); qtys[i+1].select(); break; } } }
        if(e.key==="ArrowUp"){ e.preventDefault(); var qtys2=document.getElementsByClassName("pr-qty-input"); for(var j=0;j<qtys2.length;j++){ if(qtys2[j]===input&&qtys2[j-1]){ qtys2[j-1].focus(); qtys2[j-1].select(); break; } } }
    }
    
    function pr_showReqAC(input) {
        initAC(); var key=(input.value||"").toUpperCase(); acMode="req"; acItems=[]; acIndex=-1; if(key.length<1){hideAC();return;}
        for(var i=0;i<reqData.length;i++){ var r=reqData[i]; var text=(r.REQ_NO||"")+" "+(r.REQ_DATE||"")+" "+(r.DEP_CODE||"")+" "+(r.DEP_NAME||"")+" "+(r.REQ_REM||""); if(text.toUpperCase().indexOf(key)>=0) acItems.push(r); if(acItems.length>=30) break; }
        positionAC(input); renderAC(function(r){return "<b>"+r.REQ_NO+"</b> - "+r.REQ_DATE+" - "+r.DEP_CODE+" "+r.DEP_NAME+" - "+r.REQ_REM;}, pr_pickReq);
    }
    function pr_reqSearchKey(e, input) {
        if(e.key==="ArrowDown"){ e.preventDefault(); if(acMode!=="req"||acItems.length==0) pr_showReqAC(input); acMove(1, function(r){return "<b>"+r.REQ_NO+"</b> - "+r.REQ_DATE+" - "+r.DEP_CODE+" "+r.DEP_NAME+" - "+r.REQ_REM;}, pr_pickReq); return false; }
        if(e.key==="ArrowUp"){ e.preventDefault(); if(acMode!=="req"||acItems.length==0) pr_showReqAC(input); acMove(-1, function(r){return "<b>"+r.REQ_NO+"</b> - "+r.REQ_DATE+" - "+r.DEP_CODE+" "+r.DEP_NAME+" - "+r.REQ_REM;}, pr_pickReq); return false; }
        if(e.key==="Enter"){ if(acMode==="req"&&acItems.length>0){ e.preventDefault(); acEnter(pr_pickReq); return false; } return true; }
        if(e.key==="Escape") hideAC();
    }
    function pr_pickReq(r) { hideAC(); window.location.href="<?php echo $urlBase; ?>tab=pr&edit="+encodeURIComponent(r.REQ_ID); }
    function pr_headerKey(e) { if(e.key==="Enter"){ if(acMode!==""&&acItems.length>0)return true; e.preventDefault(); pr_submitSave(false); return false; } }

    function pr_addRow() {
        var tbody=byId("pr_detailBody"); var row=pr_rowSeq; pr_rowSeq++; var tr=document.createElement("tr");
        tr.innerHTML='<td class="center pr-row-no"></td><td><input type="hidden" name="item_id[]" id="pr_item_id_'+row+'"><input type="text" name="item_code[]" id="pr_item_code_'+row+'" autocomplete="off" oninput="pr_showItemAC(this, '+row+')" onkeydown="pr_itemKey(event, this, '+row+')"></td><td><input type="text" name="item_name[]" id="pr_item_name_'+row+'" autocomplete="off" oninput="pr_showItemAC(this, '+row+')" onkeydown="pr_itemKey(event, this, '+row+')"></td><td><input type="text" name="item_unit[]" id="pr_item_unit_'+row+'" readonly></td><td><input type="text" name="reqd_qty[]" id="pr_reqd_qty_'+row+'" class="num pr-qty-input" onkeydown="pr_qtyKey(event, this)"></td><td><input type="text" name="reqd_rem[]" onkeydown="pr_headerKey(event)"></td><td><button type="button" class="btn btn-default btn-xs" onclick="pr_showPO(this)">PO</button> <button type="button" class="btn btn-danger btn-xs" onclick="pr_deleteRow(this)">X</button></td>';
        tbody.appendChild(tr); pr_renumberRows(); byId("pr_item_code_"+row).focus();
    }
    function pr_deleteRow(btn) { var tr=btn.parentNode.parentNode; var reqId=byId("pr_req_id").value; if(!confirm("Hapus baris detail ini?"))return; tr.parentNode.removeChild(tr); pr_renumberRows(); if(reqId!=""&&reqId!="0") pr_submitSave(true); }
    function pr_renumberRows() { var rows=byId("pr_detailBody").getElementsByTagName("tr"); for(var i=0;i<rows.length;i++){ var noCell=rows[i].getElementsByClassName("pr-row-no")[0]; if(noCell) noCell.innerHTML=i+1; else rows[i].cells[0].innerHTML=i+1; } }
    function pr_showPO(btn) {
        var tr=btn.parentNode.parentNode; var reqId=byId("pr_req_id").value; var itemInput=tr.querySelector('input[name="item_id[]"]'); var itemId=itemInput?itemInput.value:"";
        if(reqId==""||reqId=="0"){alert("Simpan requisition dulu.");return;} if(itemId==""||itemId=="0"){alert("Pilih item dulu.");return;}
        window.open("?page=<?php echo $pageName; ?>&tab=pr&action=po_view&req_id="+encodeURIComponent(reqId)+"&item_id="+encodeURIComponent(itemId), "PO_LIST", "width=760,height=420,scrollbars=yes,resizable=yes");
    }
</script>

<!-- =========================================================================
     JAVASCRIPT QUOTATION (Isolated)
========================================================================== -->
<script>
    var quo_rowSeq = <?php echo count($quo_details); ?>;
    function quo_submitSave(allowEmpty) { var f=byId("quoForm"); if(byId("quo_allow_empty_detail")) byId("quo_allow_empty_detail").value=allowEmpty?"1":"0"; if(f) f.submit(); }
    function quo_deleteCurrent() { var id=byId("quo_quo_id").value; if(id==""||id=="0"){alert("Pilih quotation dulu.");return;} if(!confirm("Yakin hapus quotation ini?"))return; byId("quo_delete_quo_id").value=id; byId("quo_deleteForm").submit(); }
    function quo_headerKey(e) { if(e.key==="Enter"){ if(acMode!==""&&acItems.length>0)return true; e.preventDefault(); quo_submitSave(false); return false; } }
    function quo_fieldEnterSave(e) { if(e.key==="Enter"){ e.preventDefault(); quo_submitSave(false); return false; } }

    function quo_fillSupplier(s) { setValue("quo_sup_id", s.SUP_ID); setValue("quo_sup_code", s.SUP_CODE); setValue("quo_sup_comp", s.SUP_COMP); if(s.CURR_CODE) setValue("quo_curr_code", s.CURR_CODE); }
    function quo_showSupplierAC(input) {
        initAC(); var key=(input.value||"").toUpperCase(); acMode="quo_supplier"; acItems=[]; acIndex=-1; if(key.length<1){hideAC();return;}
        for(var i=0;i<supplierData.length;i++){ var s=supplierData[i]; var text=(s.SUP_CODE||"")+" "+(s.SUP_COMP||""); if(text.toUpperCase().indexOf(key)>=0) acItems.push(s); if(acItems.length>=30) break; }
        positionAC(input); renderAC(function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;}, quo_pickSupplier);
    }
    function quo_supplierKey(e, input) {
        if(e.key==="ArrowDown"){ e.preventDefault(); if(acMode!=="quo_supplier"||acItems.length==0) quo_showSupplierAC(input); acMove(1, function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;}, quo_pickSupplier); return false; }
        if(e.key==="ArrowUp"){ e.preventDefault(); if(acMode!=="quo_supplier"||acItems.length==0) quo_showSupplierAC(input); acMove(-1, function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;}, quo_pickSupplier); return false; }
        if(e.key==="Enter"){ if(acMode==="quo_supplier"&&acItems.length>0){ e.preventDefault(); acEnter(quo_pickSupplier); return false; } e.preventDefault(); quo_submitSave(false); return false; }
        if(e.key==="Escape") hideAC();
    }
    function quo_pickSupplier(s) { quo_fillSupplier(s); hideAC(); var quoNo=byId("quo_quo_no"); if(quoNo){quoNo.focus();quoNo.select();} }

    function quo_setRowItem(row, item) { setValue("quo_item_id_"+row, item.ITEM_ID||""); setValue("quo_item_code_"+row, item.ITEM_CODE||""); setValue("quo_item_name_"+row, item.ITEM_NAME||""); setValue("quo_quod_unit_"+row, item.ITEM_UNIT||""); var price=byId("quo_quod_price_"+row); if(price && item.ITEM_COST && parseFloat(item.ITEM_COST)!=0) price.value=item.ITEM_COST; }
    function quo_showItemAC(input, row) {
        initAC(); var key=(input.value||"").toUpperCase(); acMode="quo_item"; acRow=row; acItems=[]; acIndex=-1; if(key.length<1){hideAC();return;}
        for(var i=0;i<itemData.length;i++){ var item=itemData[i]; var code=(item.ITEM_CODE||"").toUpperCase(); var name=(item.ITEM_NAME||"").toUpperCase(); if(code.indexOf(key)>=0 || name.indexOf(key)>=0) acItems.push(item); if(acItems.length>=40) break; }
        positionAC(input); renderAC(function(item){return "<b>"+item.ITEM_CODE+"</b> - "+item.ITEM_NAME;}, quo_pickItem);
    }
    function quo_itemKey(e, input, row) {
        if(e.key==="ArrowDown"){ e.preventDefault(); if(acMode!=="quo_item"||acItems.length==0) quo_showItemAC(input,row); acMove(1, function(item){return "<b>"+item.ITEM_CODE+"</b> - "+item.ITEM_NAME;}, quo_pickItem); return false; }
        if(e.key==="ArrowUp"){ e.preventDefault(); if(acMode!=="quo_item"||acItems.length==0) quo_showItemAC(input,row); acMove(-1, function(item){return "<b>"+item.ITEM_CODE+"</b> - "+item.ITEM_NAME;}, quo_pickItem); return false; }
        if(e.key==="Enter"){ if(acMode==="quo_item"&&acItems.length>0){ e.preventDefault(); acEnter(quo_pickItem); return false; } e.preventDefault(); return false; }
        if(e.key==="Escape") hideAC();
    }
    function quo_pickItem(item) { var row=acRow; quo_setRowItem(row,item); hideAC(); var price=byId("quo_quod_price_"+row); if(price){price.focus();price.select();} }

    function quo_addRow() {
        var tbody=byId("quo_detailBody"); var row=quo_rowSeq; quo_rowSeq++; var tr=document.createElement("tr");
        tr.innerHTML='<td class="center quo-row-no"></td><td><input type="hidden" name="item_id[]" id="quo_item_id_'+row+'"><input type="text" name="item_code[]" id="quo_item_code_'+row+'" autocomplete="off" oninput="quo_showItemAC(this, '+row+')" onkeydown="quo_itemKey(event, this, '+row+')"></td><td><input type="text" name="item_name[]" id="quo_item_name_'+row+'" autocomplete="off" oninput="quo_showItemAC(this, '+row+')" onkeydown="quo_itemKey(event, this, '+row+')"></td><td><input type="text" name="quod_unit[]" id="quo_quod_unit_'+row+'"></td><td><input type="text" name="quod_price[]" id="quo_quod_price_'+row+'" class="num" onkeydown="quo_fieldEnterSave(event)"></td><td><input type="text" name="quod_minqty[]" class="num" onkeydown="quo_fieldEnterSave(event)"></td><td><input type="text" name="quod_term[]" onkeydown="quo_fieldEnterSave(event)"></td><td class="center"><input type="checkbox" name="quod_active['+row+']" value="1"></td><td><button type="button" class="btn btn-default btn-xs" onclick="quo_showHistory(this)">OS/PO</button> <button type="button" class="btn btn-danger btn-xs" onclick="quo_deleteRow(this)">X</button></td>';
        tbody.appendChild(tr); quo_renumberRows(); byId("quo_item_code_"+row).focus();
    }
    function quo_deleteRow(btn) { var tr=btn.parentNode.parentNode; if(!confirm("Hapus baris detail ini?"))return; tr.parentNode.removeChild(tr); quo_renumberRows(); var quoId=byId("quo_quo_id").value; if(quoId!=""&&quoId!="0") quo_submitSave(true); }
    function quo_renumberRows() { var rows=byId("quo_detailBody").getElementsByTagName("tr"); for(var i=0;i<rows.length;i++){ var noCell=rows[i].getElementsByClassName("quo-row-no")[0]; if(noCell) noCell.innerHTML=i+1; else rows[i].cells[0].innerHTML=i+1; } }
    function quo_showHistory(btn) { var tr=btn.parentNode.parentNode; var itemInput=tr.querySelector('input[name="item_id[]"]'); var itemId=itemInput?itemInput.value:""; if(itemId==""||itemId=="0"){alert("Pilih item dulu.");return;} window.open("?page=<?php echo $pageName; ?>&tab=quotation&action=quo_history&item_id="+encodeURIComponent(itemId), "QUO_HISTORY", "width=980,height=420,scrollbars=yes,resizable=yes"); }

    function quo_showQuoAC(input) {
        initAC(); var key=(input.value||"").toUpperCase(); acMode="quo"; acItems=[]; acIndex=-1; if(key.length<1){hideAC();return;}
        for(var i=0;i<quoData.length;i++){ var q=quoData[i]; var text=(q.QUO_NO||"")+" "+(q.QUO_DATE||"")+" "+(q.SUP_CODE||"")+" "+(q.SUP_COMP||""); if(text.toUpperCase().indexOf(key)>=0) acItems.push(q); if(acItems.length>=30) break; }
        positionAC(input); renderAC(function(q){return "<b>"+q.QUO_NO+"</b> - "+q.QUO_DATE+" - "+q.SUP_CODE+" "+q.SUP_COMP;}, quo_pickQuo);
    }
    function quo_quoSearchKey(e, input) {
        if(e.key==="ArrowDown"){ e.preventDefault(); if(acMode!=="quo"||acItems.length==0) quo_showQuoAC(input); acMove(1, function(q){return "<b>"+q.QUO_NO+"</b> - "+q.QUO_DATE+" - "+q.SUP_CODE+" "+q.SUP_COMP;}, quo_pickQuo); return false; }
        if(e.key==="ArrowUp"){ e.preventDefault(); if(acMode!=="quo"||acItems.length==0) quo_showQuoAC(input); acMove(-1, function(q){return "<b>"+q.QUO_NO+"</b> - "+q.QUO_DATE+" - "+q.SUP_CODE+" "+q.SUP_COMP;}, quo_pickQuo); return false; }
        if(e.key==="Enter"){ if(acMode==="quo"&&acItems.length>0){ e.preventDefault(); acEnter(quo_pickQuo); return false; } return true; }
        if(e.key==="Escape") hideAC();
    }
    function quo_pickQuo(q) { hideAC(); window.location.href="<?php echo $urlBase; ?>tab=quotation&edit="+encodeURIComponent(q.QUO_ID); }
</script>

<!-- =========================================================================
     JAVASCRIPT PURCHASE ORDER (PO) (Isolated)
========================================================================== -->
<script>
    var po_rowSeq = <?php echo count($po_details); ?>;
    var po_selectedDetailRow = null;

    function po_submitSave(allowEmpty) { var f=byId("poForm"); if(byId("po_allow_empty_detail")) byId("po_allow_empty_detail").value=allowEmpty?"1":"0"; if(f) f.submit(); }
    function po_deleteCurrent() { var id=byId("po_po_id").value; if(id==""||id=="0"){alert("Pilih PO dulu.");return;} if(!confirm("Yakin hapus PO ini?"))return; byId("po_delete_po_id").value=id; byId("po_deleteForm").submit(); }
    function po_headerKey(e) { if(e.key==="Enter"){ if(acMode!==""&&acItems.length>0)return true; e.preventDefault(); po_submitSave(false); return false; } }
    function po_selectDetailRow(tr) { var rows=document.querySelectorAll("#po_detailBody tr"); for(var i=0;i<rows.length;i++) rows[i].classList.remove("O_selected"); po_selectedDetailRow=tr; if(tr)tr.classList.add("O_selected"); }
    function po_calcAmount(row) { var q=byId("po_pod_qty_"+row), p=byId("po_pod_price_"+row); if(!q||!p)return; var qty=parseFloat((q.value||"0").replace(/,/g,"")); var price=parseFloat((p.value||"0").replace(/,/g,"")); if(isNaN(qty))qty=0; if(isNaN(price))price=0; setValue("po_pod_amount_"+row,(qty*price).toFixed(2)); }
    function po_fieldEnterSave(e, row) { if(e.key==="Enter"){ e.preventDefault(); po_submitSave(false); return false; } if(row!==undefined) setTimeout(function(){po_calcAmount(row);},10); }

    function po_showSupplierAC(input, target) {
        initAC(); supplierTarget=target; var key=(input.value||"").toUpperCase(); acMode="po_supplier"; acItems=[]; acIndex=-1; if(key.length<1){hideAC();return;}
        for(var i=0;i<supplierData.length;i++){ var s=supplierData[i]; var t=(s.SUP_CODE||"")+" "+(s.SUP_COMP||""); if(t.toUpperCase().indexOf(key)>=0) acItems.push(s); if(acItems.length>=40) break; }
        positionAC(input); renderAC(function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;}, po_pickSupplier);
    }
    function po_supplierKey(e, input, target) {
        supplierTarget=target;
        if(e.key==="ArrowDown"){ e.preventDefault(); if(acMode!=="po_supplier"||acItems.length==0) po_showSupplierAC(input,target); acMove(1, function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;}, po_pickSupplier); return false; }
        if(e.key==="ArrowUp"){ e.preventDefault(); if(acMode!=="po_supplier"||acItems.length==0) po_showSupplierAC(input,target); acMove(-1, function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;}, po_pickSupplier); return false; }
        if(e.key==="Enter"){ if(acMode==="po_supplier"&&acItems.length>0){ e.preventDefault(); acEnter(po_pickSupplier); return false; } e.preventDefault(); po_submitSave(false); return false; }
        if(e.key==="Escape") hideAC();
    }
    function po_pickSupplier(s) {
        if(supplierTarget=="to"){ setValue("po_po_to",s.SUP_ID); setValue("po_to_sup_code",s.SUP_CODE); setValue("po_to_sup_comp",s.SUP_COMP); }
        else { setValue("po_sup_id",s.SUP_ID); setValue("po_sup_code",s.SUP_CODE); setValue("po_sup_comp",s.SUP_COMP); if(s.CURR_CODE)setValue("po_po_cur",s.CURR_CODE); if(s.SUP_TERM)setValue("po_po_term",s.SUP_TERM); if(!byId("po_po_to").value||byId("po_po_to").value=="0"){setValue("po_po_to",s.SUP_ID);setValue("po_to_sup_code",s.SUP_CODE);setValue("po_to_sup_comp",s.SUP_COMP);} }
        hideAC();
    }

    function po_showOSAC(input, row) {
        initAC(); var supId=byId("po_sup_id").value; var curr=byId("po_po_cur").value; if(!supId||supId=="0"){alert("Pilih supplier dulu.");input.value="";return;} if(!curr){alert("Pilih currency dulu.");input.value="";return;}
        var key=(input.value||"").toUpperCase(); acMode="po_osreq"; acRow=row; acItems=[]; acIndex=-1; if(key.length<1){hideAC();return;}
        for(var i=0;i<osReqData.length;i++){ var o=osReqData[i]; if(parseInt(o.SUP_ID,10)!=parseInt(supId,10))continue; if((o.CURR_CODE||"").toUpperCase()!=(curr||"").toUpperCase())continue; var t=(o.ITEM_CODE||"")+" "+(o.ITEM_NAME||"")+" "+(o.REQ_NO||"")+" "+(o.REQ_DUE_VIEW||""); if(t.toUpperCase().indexOf(key)>=0) acItems.push(o); if(acItems.length>=60) break; }
        positionAC(input); renderAC(function(o){return "<b>"+o.ITEM_CODE+"</b> - "+o.ITEM_NAME+" | Req: "+o.REQ_NO+" | Bal: "+o.REQ_BQTY+" | Due: "+o.REQ_DUE_VIEW+" | Price: "+o.QUOD_PRICE;}, po_pickOS);
    }
    function po_osKey(e, input, row) {
        if(e.key==="ArrowDown"){ e.preventDefault(); if(acMode!=="po_osreq"||acItems.length==0) po_showOSAC(input,row); acMove(1, function(o){return "<b>"+o.ITEM_CODE+"</b> - "+o.ITEM_NAME+" | Req: "+o.REQ_NO+" | Bal: "+o.REQ_BQTY+" | Due: "+o.REQ_DUE_VIEW+" | Price: "+o.QUOD_PRICE;}, po_pickOS); return false; }
        if(e.key==="ArrowUp"){ e.preventDefault(); if(acMode!=="po_osreq"||acItems.length==0) po_showOSAC(input,row); acMove(-1, function(o){return "<b>"+o.ITEM_CODE+"</b> - "+o.ITEM_NAME+" | Req: "+o.REQ_NO+" | Bal: "+o.REQ_BQTY+" | Due: "+o.REQ_DUE_VIEW+" | Price: "+o.QUOD_PRICE;}, po_pickOS); return false; }
        if(e.key==="Enter"){ if(acMode==="po_osreq"&&acItems.length>0){ e.preventDefault(); acEnter(po_pickOS); return false; } e.preventDefault(); return false; }
        if(e.key==="Escape") hideAC();
    }
    function po_pickOS(o) {
        var row=acRow; setValue("po_req_id_"+row,o.REQ_ID); setValue("po_item_id_"+row,o.ITEM_ID); setValue("po_item_code_"+row,o.ITEM_CODE); setValue("po_item_name_"+row,o.ITEM_NAME); setValue("po_pod_due_"+row,o.REQ_DUE); setValue("po_pod_qty_"+row,o.REQ_BQTY); setValue("po_pod_unit_"+row,o.ITEM_UNIT); setValue("po_req_no_"+row,o.REQ_NO); setValue("po_pod_price_"+row,o.QUOD_PRICE); hideAC(); po_calcAmount(row); var q=byId("po_pod_qty_"+row); if(q){q.focus();q.select();}
    }

    function po_addRow() {
        var tbody=byId("po_detailBody"); var row=po_rowSeq; po_rowSeq++; var tr=document.createElement("tr"); tr.onclick=function(){po_selectDetailRow(this);};
        tr.innerHTML='<td class="center po-row-no"></td><td><input type="hidden" name="req_id[]" id="po_req_id_'+row+'"><input type="hidden" name="item_id[]" id="po_item_id_'+row+'"><input type="text" name="item_code[]" id="po_item_code_'+row+'" autocomplete="off" oninput="po_showOSAC(this, '+row+')" onkeydown="po_osKey(event, this, '+row+')"></td><td><input type="text" name="item_name[]" id="po_item_name_'+row+'" autocomplete="off" oninput="po_showOSAC(this, '+row+')" onkeydown="po_osKey(event, this, '+row+')"></td><td><input type="date" name="pod_due[]" id="po_pod_due_'+row+'" value="<?php echo date("Y-m-d"); ?>"></td><td><input type="text" name="pod_qty[]" id="po_pod_qty_'+row+'" class="num" onkeyup="po_calcAmount('+row+')" onkeydown="po_fieldEnterSave(event, '+row+')"></td><td><input type="text" name="pod_price[]" id="po_pod_price_'+row+'" class="num" onkeyup="po_calcAmount('+row+')" onkeydown="po_fieldEnterSave(event, '+row+')"></td><td><input type="text" name="pod_unit[]" id="po_pod_unit_'+row+'"></td><td><input type="text" name="req_no[]" id="po_req_no_'+row+'" readonly></td><td><input type="text" id="po_pod_amount_'+row+'" class="num" readonly></td><td><button type="button" class="btn btn-danger btn-xs" onclick="po_deleteRow(this); event.stopPropagation();">X</button></td>';
        tbody.appendChild(tr); po_renumberRows(); po_selectDetailRow(tr); byId("po_item_code_"+row).focus();
    }
    function po_deleteRow(btn) { var tr=btn.parentNode.parentNode; if(!confirm("Hapus baris detail ini?"))return; tr.parentNode.removeChild(tr); po_renumberRows(); var id=byId("po_po_id").value; if(id!=""&&id!="0") po_submitSave(true); }
    function po_renumberRows() { var rows=byId("po_detailBody").getElementsByTagName("tr"); for(var i=0;i<rows.length;i++){ var c=rows[i].getElementsByClassName("po-row-no")[0]; if(c)c.innerHTML=i+1; else rows[i].cells[0].innerHTML=i+1; } }

    function po_showReceive() {
        var poNum=byId("po_po_num").value; if(!poNum){alert("Pilih / simpan PO dulu.");return;} var tr=po_selectedDetailRow; if(!tr){var rows=byId("po_detailBody").getElementsByTagName("tr");if(rows.length>0)tr=rows[0];} if(!tr){alert("Pilih detail item dulu.");return;} var itemInput=tr.querySelector('input[name="item_id[]"]'); var itemId=itemInput?itemInput.value:""; if(!itemId||itemId=="0"){alert("Pilih detail item dulu.");return;}
        window.open("?page=<?php echo $pageName; ?>&tab=po&action=receive_view&po_num="+encodeURIComponent(poNum)+"&item_id="+encodeURIComponent(itemId),"RECEIVE_LIST","width=760,height=420,scrollbars=yes,resizable=yes");
    }
    function po_printPO() { var poId=byId("po_po_id").value; if(!poId||poId=="0"){alert("Pilih / simpan PO dulu.");return;} window.open("print_po.php?po_id="+encodeURIComponent(poId),"PRINT_PO","width=900,height=700,scrollbars=yes,resizable=yes"); }

    // Fitur JS untuk Autocomplete Pencarian PO
    function po_showPoAC(input) {
        initAC(); var key=(input.value||"").toUpperCase(); acMode="po_search"; acItems=[]; acIndex=-1; if(key.length<1){hideAC();return;}
        for(var i=0;i<poData.length;i++){ 
            var p=poData[i]; 
            var text=(p.PO_NUM||"")+" "+(p.PO_DATE||"")+" "+(p.SUP_CODE||"")+" "+(p.SUP_COMP||""); 
            if(text.toUpperCase().indexOf(key)>=0) acItems.push(p); 
            if(acItems.length>=30) break; 
        }
        positionAC(input); renderAC(function(p){return "<b>"+p.PO_NUM+"</b> - "+p.PO_DATE+" - "+p.SUP_CODE+" "+p.SUP_COMP;}, po_pickPo);
    }
    function po_poSearchKey(e, input) {
        if(e.key==="ArrowDown"){ e.preventDefault(); if(acMode!=="po_search"||acItems.length==0) po_showPoAC(input); acMove(1, function(p){return "<b>"+p.PO_NUM+"</b> - "+p.PO_DATE+" - "+p.SUP_CODE+" "+p.SUP_COMP;}, po_pickPo); return false; }
        if(e.key==="ArrowUp"){ e.preventDefault(); if(acMode!=="po_search"||acItems.length==0) po_showPoAC(input); acMove(-1, function(p){return "<b>"+p.PO_NUM+"</b> - "+p.PO_DATE+" - "+p.SUP_CODE+" "+p.SUP_COMP;}, po_pickPo); return false; }
        if(e.key==="Enter"){ if(acMode==="po_search"&&acItems.length>0){ e.preventDefault(); acEnter(po_pickPo); return false; } return true; }
        if(e.key==="Escape") hideAC();
    }
    function po_pickPo(p) { hideAC(); window.location.href="<?php echo $urlBase; ?>tab=po&edit="+encodeURIComponent(p.PO_ID); }
</script>

</body>
</html>
<?php
if (session_id() == "") {
    session_start();
}

// Bersihkan buffer agar tidak ada spasi/HTML liar yang ikut terkirim
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database_p1.php';

if ($conn === false) {
    echo json_encode(array("status" => "error", "message" => "Koneksi database gagal."));
    exit;
}

function postv($n, $d="") { return isset($_POST[$n]) ? trim((string)$_POST[$n]) : $d; }
function to_float($v) { $v = trim((string)$v); return $v==="" ? 0 : floatval(str_replace(",", "", $v)); }
function find_item_id_by_code($conn, $itemCode) {
    $itemCode = trim((string)$itemCode);
    if ($itemCode == "") return 0;
    $sql = "SELECT TOP 1 ITEM_ID FROM dbo.ITEMS WHERE ITEM_CODE = ?";
    $stmt = sqlsrv_query($conn, $sql, array($itemCode));
    if ($stmt === false) return 0;
    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return $r ? intval($r["ITEM_ID"]) : 0;
}

$isPlant1 = true;
if (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2') {
    $isPlant1 = false;
}
$TABEL_BC = "BC_TRANS";

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

if (!$hasDetail && $tranId <= 0) {
    echo json_encode(array("status" => "error", "message" => "Detail transaksi minimal 1 baris dan qty harus lebih dari 0."));
    exit;
}

sqlsrv_begin_transaction($conn);
$ok = true;
$errorMsg = "";

if ($tranId > 0) {
    $sqlH = "UPDATE TRANS SET TRAN_DATE=?, TRTY_CODE=?, SUP_CODE=?, TRAN_REM=?, TRAN_ADATE=? WHERE TRAN_ID=?";
    $paramsH = array($tranDate, $trtyCode, $supCode, $remark, $tranADate, $tranId);
    $stmtH = sqlsrv_query($conn, $sqlH, $paramsH);
    if ($stmtH === false) { $ok = false; $errorMsg = "Simpan header gagal:\n" . print_r(sqlsrv_errors(), true); }
} else {
    $sqlH = "INSERT INTO TRANS (TRAN_DOC, TRAN_DATE, TRTY_CODE, SUP_CODE, TRAN_REM, TRAN_ADATE) VALUES (?, ?, ?, ?, ?, ?); SELECT SCOPE_IDENTITY() AS ID";
    $paramsH = array($tranDoc, $tranDate, $trtyCode, $supCode, $remark, $tranADate);
    $stmtH = sqlsrv_query($conn, $sqlH, $paramsH);
    
    if ($stmtH === false) {
        $ok = false; $errorMsg = "Simpan header gagal:\n" . print_r(sqlsrv_errors(), true);
    } else {
        sqlsrv_next_result($stmtH); 
        $newRow = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
        if ($newRow && isset($newRow['ID'])) {
            $tranId = intval($newRow['ID']);
        } else { 
            $ok = false; $errorMsg = "Gagal mengambil TRAN_ID yang baru."; 
        }
    }
}

if ($ok && $isPlant1) {
    sqlsrv_query($conn, "DELETE FROM $TABEL_BC WHERE NO_TRANS=?", array($tranDoc));
    $stmtBC = sqlsrv_query($conn, "INSERT INTO $TABEL_BC (NO_TRANS, JENIS_BC, NOMOR_BC, BC_DATE) VALUES (?, ?, ?, ?)", array($tranDoc, $jenisBC, $nomorBC, $BCdateVal));
    if ($stmtBC === false) { $ok = false; $errorMsg = "Gagal Update Tabel BC:\n" . print_r(sqlsrv_errors(), true); }
}

if ($ok) {
    $stmtDel = sqlsrv_query($conn, "DELETE FROM INV_TRAN WHERE TRAN_ID = ?", array($tranId));
    if ($stmtDel === false) { $ok = false; $errorMsg = "Hapus detail gagal:\n" . print_r(sqlsrv_errors(), true); }
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

        if ($stmtD === false) { $ok = false; $errorMsg = "Simpan detail gagal:\n" . print_r(sqlsrv_errors(), true); break; }
        $lineNo++;
    }
}

if ($ok) {
    sqlsrv_commit($conn);
    echo json_encode(array("status" => "success", "tran_id" => $tranId));
} else {
    sqlsrv_rollback($conn);
    echo json_encode(array("status" => "error", "message" => $errorMsg));
}
exit;
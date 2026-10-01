<?php
if (session_id() == "") session_start();

require_once __DIR__ . '/config/database.php';
if ($conn === false) die("Koneksi database gagal.");

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

function generate_rcv_no($conn) {
    // Mendapatkan tahun (2 digit) dan bulan (2 digit) saat ini, contoh: "2610" untuk Oktober 2026
    $prefix = date('ym'); 
    
    // Mengambil nomor dokumen terbesar berdasarkan RCV_DATE DESC, RCV_NO DESC untuk bulan berjalan
    $searchPrefix = $prefix . '%';
    $sql = "SELECT TOP (1) RCV_NO FROM dbo.RECEIVE WHERE RCV_NO LIKE ? ORDER BY RCV_DATE DESC, RCV_NO DESC";
    $stmt = @sqlsrv_query($conn, $sql, array($searchPrefix));
    
    $nextVal = 1;
    if ($stmt !== false) {
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($r && isset($r["RCV_NO"])) {
            $lastDoc = trim($r["RCV_NO"]);
            // Mengambil 5 karakter angka setelah 4 karakter pertama (YYMM)
            if (strlen($lastDoc) >= 9) {
                $numPart = substr($lastDoc, 4, 5);
                if (is_numeric($numPart)) {
                    $nextVal = intval($numPart) + 1;
                }
            }
        }
    }
    
    // Menggabungkan: YYMM + 5 digit angka dengan padding nol di depan + garis miring (/) di akhir
    return $prefix . str_pad($nextVal, 5, "0", STR_PAD_LEFT) . "/";
}

function inc_next_rcv_no($conn) {
    // Dikosongkan karena penomoran kini dihitung secara dinamis dari data tabel RECEIVE
}

$pageName = isset($_GET['page']) ? $_GET['page'] : 'receive';
$urlBase  = "?page=" . urlencode($pageName) . "&";

$message = "";
$error = "";
$action = postv("action","");
$editId = intval(getv("edit","0"));

/* ======================================================
   AJAX AUTOCOMPLETE (DITAMBAHKAN UNTUK PENCARIAN RCV BAWAH)
====================================================== */
$ajaxMode = getv("ajax", "");
if ($ajaxMode != "") {
    header("Content-Type: application/json; charset=utf-8");
    $qAjax = getv("q", "");
    $likeAjax = "%" . $qAjax . "%";

    if ($ajaxMode == "receive_search") {
        $sql = "
            SELECT TOP 10 
                R.RCV_ID, ISNULL(R.RCV_NO, '') AS RCV_NO, ISNULL(R.RCV_DONO, '') AS RCV_DONO, ISNULL(S.SUP_COMP, '') AS SUP_COMP
            FROM dbo.RECEIVE R
            LEFT JOIN dbo.SUPPLIER S ON R.SUP_ID = S.SUP_ID
            WHERE (? = '' OR ISNULL(R.RCV_NO, '') LIKE ? OR ISNULL(R.RCV_DONO, '') LIKE ? OR ISNULL(S.SUP_COMP, '') LIKE ?)
            ORDER BY R.RCV_DATE DESC, R.RCV_NO DESC
        ";
        $stmtSearch = sqlsrv_query($conn, $sql, array($qAjax, $likeAjax, $likeAjax, $likeAjax));
        $out = array();
        if ($stmtSearch !== false) {
            while ($rSearch = sqlsrv_fetch_array($stmtSearch, SQLSRV_FETCH_ASSOC)) {
                $out[] = array(
                    "RCV_ID" => intval($rSearch["RCV_ID"]),
                    "RCV_NO" => trim((string)$rSearch["RCV_NO"]),
                    "RCV_DONO" => trim((string)$rSearch["RCV_DONO"]),
                    "SUP_COMP" => trim((string)$rSearch["SUP_COMP"])
                );
            }
        }
        echo json_encode(array("success" => true, "rows" => $out));
        exit;
    }
}

/* ======================================================
   AKSI SPESIFIK: SET NEXT ICL, TRIGGER ON/OFF, UPDATE PRICE 
====================================================== */
if ($action == "set_next_icl") {
    $nextVal = intval(postv("upd_rcv_id", "0")); 
    if ($nextVal > 0) {
        $sqlSet = "UPDATE dbo.REFS SET NEXT_PR = ? WHERE REF_ID = 1";
        if (sqlsrv_query($conn, $sqlSet, array($nextVal))) $message = "SET NEXT ICL BERHASIL diubah menjadi: " . $nextVal;
        else $error = "Gagal mengubah NEXT ICL:\n" . sql_error_text();
    }
}

if ($action == "trigger_off") {
    $sqlOff = "BEGIN ALTER TABLE [dbo].[receive_detail] DISABLE TRIGGER add_inv_recvd; ALTER TABLE [dbo].[receive_detail] DISABLE TRIGGER del_inv_recvd; ALTER TABLE [dbo].[receive_detail] DISABLE TRIGGER upd_inv_recvd; END";
    if (sqlsrv_query($conn, $sqlOff)) $message = "TRIGGER OFF BERHASIL"; else $error = "Gagal mematikan trigger:\n".sql_error_text();
}

if ($action == "trigger_on") {
    $sqlOn = "BEGIN ALTER TABLE [dbo].[receive_detail] ENABLE TRIGGER add_inv_recvd; ALTER TABLE [dbo].[receive_detail] ENABLE TRIGGER del_inv_recvd; ALTER TABLE [dbo].[receive_detail] ENABLE TRIGGER upd_inv_recvd; END";
    if (sqlsrv_query($conn, $sqlOn)) $message = "TRIGGER ON BERHASIL"; else $error = "Gagal menyalakan trigger:\n".sql_error_text();
}

if ($action == "update_price") {
    $updRcvId = intval(postv("upd_rcv_id", "0")); $updItemId = intval(postv("upd_item_id", "0")); $updPoId = intval(postv("upd_po_id", "0"));
    $stmtUpd = sqlsrv_query($conn, "EXEC sp_update_price_rec ?, ?, ?", array($updRcvId, $updItemId, $updPoId));
    if ($stmtUpd === false) $error = "Update Price Gagal:\n".sql_error_text(); else $message = "UPDATE PRICE SELESAI";
}

/* ======================================================
   DELETE RECEIVE
====================================================== */
if ($action == "delete") {
    $rcvId = intval(postv("rcv_id","0"));
    if ($rcvId <= 0) {
        $error = "Pilih Receive dulu.";
    } else {
        $stmtCek = sqlsrv_query($conn, "SELECT COUNT(*) AS CNT FROM dbo.RECEIVE_DETAIL WHERE RCV_ID = ?", array($rcvId));
        if ($stmtCek !== false) {
            $rc = sqlsrv_fetch_array($stmtCek, SQLSRV_FETCH_ASSOC);
            if (intval($rc["CNT"]) > 0) {
                $error = "Tidak dapat menghapus, Receive masih memiliki detail.";
            } else {
                sqlsrv_begin_transaction($conn);
                $ok = true;
                $stmtBc = sqlsrv_query($conn, "DELETE FROM dbo.BC_TRANS WHERE NO_TRANS = (SELECT RCV_NO FROM dbo.RECEIVE WHERE RCV_ID = ?)", array($rcvId));
                if ($stmtBc === false) $ok = false;
                if ($ok) {
                    $stmtDel = sqlsrv_query($conn, "DELETE FROM dbo.RECEIVE WHERE RCV_ID = ?", array($rcvId));
                    if ($stmtDel === false) $ok = false;
                }
                if ($ok) {
                    sqlsrv_commit($conn);
                    echo "<script>window.location.href='{$urlBase}msg=deleted';</script>"; exit;
                } else {
                    sqlsrv_rollback($conn);
                    $error = "Delete Receive gagal:\n".sql_error_text();
                }
            }
        }
    }
}

/* ======================================================
   SAVE RECEIVE
====================================================== */
if ($action == "save") {
    $rcvId = intval(postv("rcv_id","0")); $rcvNo = strtoupper(postv("rcv_no","")); $rcvDate = postv("rcv_date",date("Y-m-d")); $rcvDono = postv("rcv_dono",""); $supId = intval(postv("sup_id","0")); $rcvPic = postv("rcv_pic",""); $rcvType = postv("rcv_type","1");
    $jenisBc = postv("jenis_bc",""); $nomorBc = postv("nomor_bc",""); $bcDate = postv("bc_date",date("Y-m-d"));
    $allowEmpty = intval(postv("allow_empty_detail","0"));

    if ($rcvDate == "") $rcvDate = date("Y-m-d");
    if ($rcvNo == "") $rcvNo = generate_rcv_no($conn);

    $isNewRecord = ($rcvId == 0); 
    if ($supId <= 0) $error = "Supplier wajib dipilih.";

    if ($error == "") {
        $itemIds = isset($_POST["item_id"]) ? $_POST["item_id"] : array();
        $poIds = isset($_POST["po_id"]) ? $_POST["po_id"] : array();
        $qtys = isset($_POST["rcvd_qty"]) ? $_POST["rcvd_qty"] : array();
        $prices = isset($_POST["pod_price"]) ? $_POST["pod_price"] : array();

        $hasDetail = false;
        for ($i=0; $i<count($itemIds); $i++) {
            $itemId = intval($itemIds[$i]); $qty = isset($qtys[$i]) ? to_float($qtys[$i]) : 0;
            if ($itemId > 0 && $qty > 0) { $hasDetail = true; break; }
        }

        if (!$hasDetail && !($allowEmpty == 1 && $rcvId > 0)) {
            $error = "Detail Receive minimal 1 baris dan qty harus lebih dari 0.";
        } else {
            sqlsrv_begin_transaction($conn);
            $ok = true;

            if (!$isNewRecord) {
                $sqlH = "UPDATE dbo.RECEIVE SET RCV_NO=?, RCV_DONO=?, RCV_DATE=?, RCV_PIC=?, SUP_ID=?, RCV_TYPE=? WHERE RCV_ID=?";
                $stmtH = sqlsrv_query($conn, $sqlH, array($rcvNo, $rcvDono, $rcvDate, $rcvPic, $supId, $rcvType, $rcvId));
                if ($stmtH === false) { $ok=false; $error="Simpan header gagal:\n".sql_error_text(); }
            } else {
                $sqlH = "
                    SET NOCOUNT ON;
                    DECLARE @OutputTbl TABLE (NEW_ID INT);
                    INSERT INTO dbo.RECEIVE (RCV_NO, RCV_DONO, RCV_DATE, RCV_PIC, SUP_ID, RCV_TYPE) 
                    OUTPUT INSERTED.RCV_ID INTO @OutputTbl
                    VALUES (?, ?, ?, ?, ?, ?);
                    SELECT NEW_ID FROM @OutputTbl;
                ";
                $stmtH = sqlsrv_query($conn, $sqlH, array($rcvNo, $rcvDono, $rcvDate, $rcvPic, $supId, $rcvType));
                if ($stmtH === false) {
                    $ok=false; $error="Simpan header gagal:\n".sql_error_text();
                } else {
                    $new = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
                    if ($new && isset($new['NEW_ID'])) { $rcvId = intval($new['NEW_ID']); } 
                    else { 
                        if (sqlsrv_next_result($stmtH)) {
                            $new = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
                            if ($new && isset($new['NEW_ID'])) $rcvId = intval($new['NEW_ID']);
                            else { $ok=false; $error="RCV_ID baru tidak terbaca (Next Result)."; }
                        } else { $ok=false; $error="RCV_ID baru tidak terbaca."; }
                    }
                }
            }

            if ($ok) {
                sqlsrv_query($conn, "DELETE FROM dbo.BC_TRANS WHERE NO_TRANS=?", array($rcvNo));
                if ($jenisBc != "" || $nomorBc != "") {
                    $sqlBC = "INSERT INTO dbo.BC_TRANS (NO_TRANS, JENIS_BC, NOMOR_BC, BC_DATE) VALUES (?, ?, ?, ?)";
                    $stmtBc = sqlsrv_query($conn, $sqlBC, array($rcvNo, $jenisBc, $nomorBc, $bcDate));
                    if ($stmtBc === false) { $ok=false; $error="Simpan BC gagal:\n".sql_error_text(); }
                }
            }

            if ($ok) {
                $stmtDel = sqlsrv_query($conn, "DELETE FROM dbo.RECEIVE_DETAIL WHERE RCV_ID = ?", array($rcvId));
                if ($stmtDel === false) { $ok=false; $error="Hapus detail lama gagal:\n".sql_error_text(); }
            }

            if ($ok) {
                for ($i=0; $i<count($itemIds); $i++) {
                    $itemId = intval($itemIds[$i]); $poId = intval($poIds[$i]); $qty = isset($qtys[$i]) ? to_float($qtys[$i]) : 0; $price = isset($prices[$i]) ? to_float($prices[$i]) : 0;
                    if ($itemId <= 0 || $poId <= 0 || $qty <= 0) continue;
                    $sqlD = "INSERT INTO dbo.RECEIVE_DETAIL (RCV_ID, ITEM_ID, PO_ID, RCVD_QTY, POD_PRICE) VALUES (?, ?, ?, ?, ?)";
                    $stmtD = sqlsrv_query($conn, $sqlD, array($rcvId, $itemId, $poId, $qty, $price));
                    if ($stmtD === false) { $ok=false; $error="Simpan detail gagal:\n".sql_error_text(); break; }
                }
            }

            if ($ok) {
                sqlsrv_commit($conn);
                if ($isNewRecord) inc_next_rcv_no($conn); 
                echo "<script>window.location.href='{$urlBase}edit=".$rcvId."&msg=saved';</script>"; exit;
            } else {
                sqlsrv_rollback($conn);
                if ($error == "") $error = "Simpan Receive gagal:\n".sql_error_text();
            }
        }
    }
}

/* ======================================================
   LOAD HEADER & DETAILS
====================================================== */
$header = array(
    "RCV_ID"=>"", "RCV_NO"=>"", "RCV_DATE"=>date("Y-m-d"), "RCV_DONO"=>"",
    "SUP_ID"=>"", "SUP_CODE"=>"", "SUP_COMP"=>"", "RCV_PIC"=>"", "RCV_TYPE"=>"1",
    "JENIS_BC"=>"", "NOMOR_BC"=>"", "BC_DATE"=>date("Y-m-d")
);
$details = array();

if ($editId > 0) {
    $sqlH = "
        SELECT R.RCV_ID, R.RCV_NO, R.RCV_DATE, R.RCV_DONO, R.SUP_ID, R.RCV_PIC, R.RCV_TYPE,
               S.SUP_CODE, S.SUP_COMP, B.JENIS_BC, B.NOMOR_BC, B.BC_DATE
        FROM dbo.RECEIVE R
        LEFT JOIN dbo.SUPPLIER S ON R.SUP_ID = S.SUP_ID
        LEFT JOIN dbo.BC_TRANS B ON R.RCV_NO = B.NO_TRANS
        WHERE R.RCV_ID = ?
    ";
    $stmtH = sqlsrv_query($conn,$sqlH,array($editId));
    if ($stmtH !== false && $rh = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC)) {
        foreach ($header as $k=>$v) if (isset($rh[$k])) $header[$k] = $rh[$k];
        $header["RCV_DATE"] = fmt_date($header["RCV_DATE"]);
        $header["BC_DATE"] = fmt_date($header["BC_DATE"]);
    }

    $sqlD = "
        SELECT D.RCV_ID, D.ITEM_ID, D.PO_ID, D.RCVD_QTY, D.POD_PRICE,
               I.ITEM_CODE, I.ITEM_NAME, P.PO_NUM
        FROM dbo.RECEIVE_DETAIL D
        LEFT JOIN dbo.ITEMS I ON D.ITEM_ID = I.ITEM_ID
        LEFT JOIN dbo.PO P ON D.PO_ID = P.PO_ID
        WHERE D.RCV_ID = ?
        ORDER BY I.ITEM_CODE
    ";
    $stmtD = sqlsrv_query($conn,$sqlD,array($editId));
    if ($stmtD !== false) while ($rd = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) $details[] = $rd;
} else {
    $header["RCV_NO"] = generate_rcv_no($conn);
}

if (getv("msg") == "saved") $message = "Receive berhasil disimpan.";
if (getv("msg") == "deleted") $message = "Receive berhasil dihapus.";

/* ======================================================
   SUPPLIER, DEPT, & BC DATA (Untuk Autocomplete / Select)
====================================================== */
$supplierAuto = array();
$sqlSup = "SELECT TOP 1000 SUP_ID, SUP_CODE, SUP_COMP FROM dbo.SUPPLIER WHERE ISNULL(SUP_CODE,'') <> '' ORDER BY SUP_CODE";
$stmtSup = sqlsrv_query($conn,$sqlSup);
if ($stmtSup !== false) while ($s = sqlsrv_fetch_array($stmtSup, SQLSRV_FETCH_ASSOC)) $supplierAuto[] = array("SUP_ID"=>intval($s["SUP_ID"]), "SUP_CODE"=>trim((string)$s["SUP_CODE"]), "SUP_COMP"=>trim((string)$s["SUP_COMP"]));

$deptAuto = array();
$stmtDept = sqlsrv_query($conn,"SELECT DEP_ID, DEP_CODE, DEP_NAME FROM dbo.DEPT ORDER BY DEP_CODE");
if ($stmtDept !== false) while ($d = sqlsrv_fetch_array($stmtDept, SQLSRV_FETCH_ASSOC)) $deptAuto[] = array("DEP_NAME"=>trim((string)$d["DEP_NAME"]));

$bcList = array();
$stmtBc = sqlsrv_query($conn,"SELECT BCTY_NAME FROM dbo.BCTY");
if ($stmtBc !== false) while ($b = sqlsrv_fetch_array($stmtBc, SQLSRV_FETCH_ASSOC)) $bcList[] = trim((string)$b["BCTY_NAME"]);

/* LIST RECEIVE BAWAH (DIBATASI TOP 10) */
$q_search = getv("q","");
$where = "";
$paramsList = array();
if ($q_search != "") {
    $where = "WHERE R.RCV_NO LIKE ? OR S.SUP_CODE LIKE ? OR S.SUP_COMP LIKE ? OR R.RCV_DONO LIKE ?";
    $paramsList = array("%".$q_search."%","%".$q_search."%","%".$q_search."%","%".$q_search."%");
}

// PERUBAHAN: SELECT TOP 10 agar list data tidak terlalu panjang
$sqlList = "
    SELECT TOP 10 R.RCV_ID, R.RCV_NO, R.RCV_DATE, R.RCV_DONO, R.RCV_PIC, S.SUP_CODE, S.SUP_COMP,
           COUNT(D.ITEM_ID) AS DETAIL_COUNT
    FROM dbo.RECEIVE R
    LEFT JOIN dbo.SUPPLIER S ON R.SUP_ID = S.SUP_ID
    LEFT JOIN dbo.RECEIVE_DETAIL D ON R.RCV_ID = D.RCV_ID
    $where
    GROUP BY R.RCV_ID, R.RCV_NO, R.RCV_DATE, R.RCV_DONO, R.RCV_PIC, S.SUP_CODE, S.SUP_COMP
    ORDER BY R.RCV_DATE DESC, R.RCV_NO DESC
";
$stmtList = sqlsrv_query($conn,$sqlList,$paramsList);

if (count($details) == 0) {
    for ($i=0; $i<3; $i++) $details[] = array("ITEM_ID"=>"","PO_ID"=>"","ITEM_CODE"=>"","ITEM_NAME"=>"","PO_NUM"=>"","RCVD_QTY"=>"","POD_PRICE"=>"");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Receive</title>

    <style>
        /* CSS Khusus untuk memastikan Layout selaras dengan AdminLTE & Bootstrap */
        .box { background: #ffffff; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; border-top: 3px solid #d2d6de; }
        .box.box-primary { border-top-color: #3c8dbc; }
        .box-header { padding: 10px 15px; border-bottom: 1px solid #f4f4f4; display: flex; justify-content: space-between; align-items: center; }
        .box-title { font-size: 16px; margin: 0; font-weight: bold; display: inline-block; }
        .box-body { padding: 15px; }
        
        .form-control { width: 100%; border-radius: 4px; border: 1px solid #ccc; height: 30px; padding: 4px 10px; font-size: 13px; box-shadow: none; transition: border-color 0.15s ease-in-out; }
        .form-control:focus { border-color: #3c8dbc; outline: 0; }
        .form-control[readonly] { background-color: #e6e6e6; cursor: not-allowed; }
        
        .btn { border-radius: 4px; font-size: 12px; font-weight: bold; padding: 5px 12px; cursor: pointer; border: 1px solid transparent; transition: background-color 0.2s; display: inline-block; text-decoration: none; }
        .btn-success { background-color: #00a65a; color: #fff; border-color: #008d4c; }
        .btn-primary { background-color: #3c8dbc; color: #fff; border-color: #367fa9; }
        .btn-default { background-color: #f4f4f4; color: #444; border-color: #ddd; }
        .btn-danger { background-color: #dd4b39; color: #fff; border-color: #d73925; }
        .btn-warning { background-color: #f39c12; color: #fff; border-color: #e08e0b; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { font-weight: 600; margin-bottom: 5px; display: inline-block; font-size: 12px; color: #333; }
        
        /* PERUBAHAN: Layout Grid Tabel (Baris lebih dirapatkan) */
        .grid-table { width: 100%; border-collapse: collapse; background: #fff; table-layout: fixed; }
        .grid-table th, .grid-table td { border: 1px solid #888; padding: 2px 4px !important; height: 22px; font-size: 11px; white-space: nowrap; overflow: hidden; }
        .grid-table th { background: #d9d6ce; font-weight: bold; text-align: left; }
        .grid-table tbody tr:hover { background-color: #cce5ff; cursor: pointer;}
        .grid-table input { width: 100%; height: 20px; border: none; padding: 1px 3px; box-sizing: border-box; font-size: 11px; }
        .grid-table input:focus { outline: 1px solid #2f65d9; }
        
        .num { text-align: right; }
        .center { text-align: center; }

        .detail-selected { background: #2f70c9 !important; color: #ffffff; }
        .detail-selected input { color: #000; }

        /* Autocomplete List */
        .ac-box { position: absolute; z-index: 9999; background: #fff; color: #000; border: 1px solid #333; max-height: 210px; overflow-y: auto; min-width: 280px; display: none; font-size: 12px; box-shadow: 2px 2px 5px rgba(0,0,0,0.3); }
        .ac-item { padding: 4px 6px; cursor: pointer; border-bottom: 1px solid #ddd; }
        .ac-item:hover, .ac-item.active { background: #2f65d9; color: #fff; }
        .autocomplete-wrap { position: relative; display: block; }
        
        .table-container { overflow-y: auto; border: 1px solid #ddd; margin-bottom: 15px; background: #fff; }
    </style>
</head>
<body style="padding: 15px; background-color: #ecf0f5;">

<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-truck"></i> Purchase Receive (Penerimaan Barang)</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-default btn-sm" onclick="newData()">NEW RECEIVE</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteCurrent()">HAPUS HEADER</button>
                    <button type="submit" form="rcvForm" class="btn btn-success btn-sm">SIMPAN RECEIVE</button>
                </div>
            </div>
            
            <div class="box-body">
                <?php if ($message != "") { echo '<div class="alert alert-success" style="padding:8px; margin-bottom:15px; font-weight:bold;">'.h($message).'</div>'; } ?>
                <?php if ($error != "") { echo '<div class="alert alert-danger" style="padding:8px; margin-bottom:15px; font-weight:bold;">'.h($error).'</div>'; } ?>

                <form id="rcvForm" method="post" action="">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="rcv_id" id="rcv_id" value="<?php echo h($header["RCV_ID"]); ?>">
                    <input type="hidden" name="sup_id" id="sup_id" value="<?php echo h($header["SUP_ID"]); ?>">
                    <input type="hidden" name="allow_empty_detail" id="allow_empty_detail" value="0">

                    <!-- Panel Header -->
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label>I.C.L No.</label>
                            <input type="text" name="rcv_no" class="form-control text-primary" style="font-weight:bold;" value="<?php echo h($header["RCV_NO"]); ?>">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Date</label>
                            <input type="date" name="rcv_date" class="form-control" value="<?php echo h($header["RCV_DATE"]); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>D.O #</label>
                            <input type="text" name="rcv_dono" class="form-control" value="<?php echo h($header["RCV_DONO"]); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 form-group autocomplete-wrap">
                            <label>Sup.Code</label>
                            <input type="text" name="sup_code" id="sup_code" class="form-control" value="<?php echo h($header["SUP_CODE"]); ?>" autocomplete="off" oninput="showSupplierAC(this)" onkeydown="supplierKey(event, this)">
                        </div>
                        <div class="col-md-9 form-group">
                            <label>Sup.Company</label>
                            <input type="text" name="sup_comp" id="sup_comp" class="form-control" value="<?php echo h($header["SUP_COMP"]); ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 form-group autocomplete-wrap">
                            <label>PIC</label>
                            <input type="text" name="rcv_pic" id="rcv_pic" class="form-control" value="<?php echo h($header["RCV_PIC"]); ?>" autocomplete="off" oninput="showPicAC(this)" onkeydown="picKey(event, this)">
                        </div>
                        <div class="col-md-9 form-group">
                            <label>TYPE</label>
                            <select name="rcv_type" class="form-control">
                                <option value="1" <?php echo $header["RCV_TYPE"]=="1"?"selected":""; ?>>Material - Supplier</option>
                                <option value="2" <?php echo $header["RCV_TYPE"]=="2"?"selected":""; ?>>Spare Part - Supplier</option>
                                <option value="3" <?php echo $header["RCV_TYPE"]=="3"?"selected":""; ?>>Part - Vendor</option>
                                <option value="4" <?php echo $header["RCV_TYPE"]=="4"?"selected":""; ?>>Others</option>
                            </select>
                        </div>
                    </div>

                    <!-- Panel Bea Cukai -->
                    <div class="row" style="background:#00a65a; padding-top:15px; margin: 0 -15px 15px -15px; color:#fff;">
                        <div class="col-md-3 form-group">
                            <label style="color:#fff;">Jenis BC</label>
                            <select name="jenis_bc" class="form-control">
                                <option value="">- Pilih BC -</option>
                                <?php foreach($bcList as $b) { echo '<option value="'.h($b).'" '.($header["JENIS_BC"]==$b?"selected":"").'>'.h($b).'</option>'; } ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label style="color:#fff;">Nomor BC</label>
                            <input type="text" name="nomor_bc" class="form-control" value="<?php echo h($header["NOMOR_BC"]); ?>">
                        </div>
                        <div class="col-md-3 form-group">
                            <label style="color:#fff;">Tanggal BC</label>
                            <input type="date" name="bc_date" class="form-control" value="<?php echo h($header["BC_DATE"]); ?>">
                        </div>
                    </div>

                    <!-- Action Toolbar -->
                    <div style="margin-bottom: 10px;">
                        <button type="button" class="btn btn-default btn-sm" onclick="cetakICL()"><i class="fa fa-print"></i> Cetak ICL</button>
                        <button type="button" class="btn btn-default btn-sm" onclick="cetakICLOto()"><i class="fa fa-print"></i> ICL OTO</button>
                        <button type="button" class="btn btn-default btn-sm" onclick="cetakSchedule()"><i class="fa fa-calendar"></i> Sch Material</button>
                        <button type="button" class="btn btn-default btn-sm pull-right" onclick="setNextICL()"><i class="fa fa-cogs"></i> SET NEXT ICL</button>
                    </div>

                    <!-- Detail Grid -->
                    <div style="overflow-x:auto;">
                        <table class="grid-table">
                            <thead>
                                <tr>
                                    <th style="width:35px; text-align:center;">No</th>
                                    <th style="width:130px;">Item Code</th>
                                    <th style="width:300px;">Item Name</th>
                                    <th style="width:90px;">Recv Qty</th>
                                    <th style="width:100px;">Price</th>
                                    <th style="width:160px;">PO #</th>
                                    <th style="width:40px; text-align:center;">X</th>
                                </tr>
                            </thead>
                            <tbody id="detailBody">
                                <?php for($i=0;$i<count($details);$i++){ $d=$details[$i]; $qty=isset($d["RCVD_QTY"])?floatval($d["RCVD_QTY"]):0; $price=isset($d["POD_PRICE"])?floatval($d["POD_PRICE"]):0; ?>
                                    <tr>
                                        <td class="center row-no"><?php echo h($i+1); ?></td>
                                        <td>
                                            <input type="hidden" name="item_id[]" id="item_id_<?php echo h($i); ?>" value="<?php echo h($d["ITEM_ID"]); ?>">
                                            <input type="hidden" name="po_id[]" id="po_id_<?php echo h($i); ?>" value="<?php echo h($d["PO_ID"]); ?>">
                                            <input type="text" name="item_code[]" id="item_code_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_CODE"])?$d["ITEM_CODE"]:""); ?>" autocomplete="off" oninput="showPOItemAC(this, <?php echo h($i); ?>)" onkeydown="poItemKey(event, this, <?php echo h($i); ?>)">
                                        </td>
                                        <td><input type="text" name="item_name[]" id="item_name_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_NAME"])?$d["ITEM_NAME"]:""); ?>" readonly></td>
                                        <td><input type="text" name="rcvd_qty[]" id="rcvd_qty_<?php echo h($i); ?>" class="num" value="<?php echo h($qty==0?"":$qty); ?>" onkeydown="fieldEnterSave(event)"></td>
                                        <td><input type="text" name="pod_price[]" id="pod_price_<?php echo h($i); ?>" class="num" value="<?php echo h($price==0?"":$price); ?>" readonly></td>
                                        <td><input type="text" name="po_num[]" id="po_num_<?php echo h($i); ?>" value="<?php echo h(isset($d["PO_NUM"])?$d["PO_NUM"]:""); ?>" readonly></td>
                                        <td class="center"><button type="button" class="btn btn-danger btn-xs" onclick="deleteRow(this); event.stopPropagation();">X</button></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top:10px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <button type="button" class="btn btn-default" onclick="addRow()">+ Tambah Baris</button>
                        </div>
                        <div>
                            <span style="color:#dd4b39; font-weight:bold; font-size:11px; margin-right: 10px;">* JANGAN LUPA KLIK ON KEMBALI SETELAH OFF</span>
                            <button type="button" class="btn btn-danger btn-sm" onclick="triggerAction('trigger_off')">TRIGGER OFF</button>
                            <button type="button" class="btn btn-warning btn-sm" onclick="triggerUpdatePrice()">UPDATE PRICE</button>
                            <button type="button" class="btn btn-success btn-sm" onclick="triggerAction('trigger_on')">TRIGGER ON</button>
                        </div>
                    </div>
                </form>

                <!-- Hidden Forms -->
                <form id="deleteForm" method="post" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="rcv_id" id="delete_rcv_id" value="">
                </form>

                <form id="execForm" method="post" action="">
                    <input type="hidden" name="action" id="exec_action" value="">
                    <input type="hidden" name="upd_rcv_id" id="upd_rcv_id" value="">
                    <input type="hidden" name="upd_item_id" id="upd_item_id" value="">
                    <input type="hidden" name="upd_po_id" id="upd_po_id" value="">
                    <input type="hidden" name="edit" value="<?php echo h($editId); ?>">
                </form>
            </div>
            
            <div class="box-footer">
                <form method="get" action="" class="form-inline">
                    <input type="hidden" name="page" value="<?php echo htmlspecialchars($pageName); ?>">
                    <!-- PERUBAHAN: Form Pencarian Menggunakan Autocomplete Baru -->
                    <div class="form-group autocomplete-wrap" style="margin:0;">
                        <input type="text" name="q" id="searchReceive" value="<?php echo h($q_search); ?>" class="form-control" style="width:300px;" placeholder="RCV No / DO No / Supplier" autocomplete="off" oninput="showReceiveSearchAC(this)" onkeydown="receiveSearchKey(event, this)">
                        <div id="searchReceiveList" class="autocomplete-list"></div>
                    </div>
                    <button type="submit" class="btn btn-primary">SEARCH</button>
                    <a href="<?php echo $urlBase; ?>" class="btn btn-default">ALL</a>
                </form>
            </div>
        </div>

        <div class="table-container" style="height: 250px;">
            <table class="grid-table" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th style="width:130px;">RCV NO</th>
                        <th style="width:100px;">Date</th>
                        <th style="width:120px;">DO #</th>
                        <th style="width:90px;">PIC</th>
                        <th style="width:90px;">Sup.Code</th>
                        <th>Supplier</th>
                        <th style="width:60px;">Detail</th>
                    </tr>
                </thead>
                <tbody id="rcvListBody">
                    <?php if($stmtList !== false) { while($r=sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)){ ?>
                        <tr onclick="goEdit('<?php echo h($r["RCV_ID"]); ?>')">
                            <td><?php echo h($r["RCV_NO"]); ?></td>
                            <td class="center"><?php echo h(fmt_date_view($r["RCV_DATE"])); ?></td>
                            <td><?php echo h($r["RCV_DONO"]); ?></td>
                            <td><?php echo h($r["RCV_PIC"]); ?></td>
                            <td><?php echo h($r["SUP_CODE"]); ?></td>
                            <td><?php echo h($r["SUP_COMP"]); ?></td>
                            <td class="num"><?php echo h(number_format(floatval($r["DETAIL_COUNT"]),0)); ?></td>
                        </tr>
                    <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="acBox" class="ac-box"></div>

<!-- =========================================================================
     GLOBAL AC BOX LOGIC & UTILS
========================================================================== -->
<script>
function enc(v) { return encodeURIComponent(v == null ? "" : v); }
function htmlEncode(v) { return String(v == null ? "" : v).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }
function byId(id){return document.getElementById(id);}
function setValue(id,value){var el=byId(id); if(el) el.value=(value==null?"":value);}

var supplierData = <?php echo json_encode($supplierAuto); ?>;
var deptData = <?php echo json_encode($deptAuto); ?>;
var acBox=null, acItems=[], acIndex=-1, acMode="", acRow=-1;
var rowSeq=<?php echo count($details); ?>;
var selectedDetailRow=null;

function initAC(){acBox=byId("acBox");}
function hideAC(){if(acBox){acBox.style.display="none";acBox.innerHTML="";} acItems=[];acIndex=-1;acMode="";acRow=-1;}
function positionAC(input){initAC();var r=input.getBoundingClientRect();acBox.style.left=(r.left+window.scrollX)+"px";acBox.style.top=(r.bottom+window.scrollY)+"px";acBox.style.width=(r.width<360?360:r.width)+"px";}
function renderAC(renderText,pickFunc){initAC();acBox.innerHTML="";for(var i=0;i<acItems.length;i++){var d=document.createElement("div");d.className="ac-item"+(i==acIndex?" active":"");d.innerHTML=renderText(acItems[i]);d.setAttribute("data-index",i);d.onmousedown=function(){pickFunc(acItems[parseInt(this.getAttribute("data-index"),10)]);};acBox.appendChild(d);}acBox.style.display=acItems.length>0?"block":"none";}
function acMove(step,renderText,pickFunc){if(acItems.length<=0)return;acIndex+=step;if(acIndex<0)acIndex=acItems.length-1;if(acIndex>=acItems.length)acIndex=0;renderAC(renderText,pickFunc);}
function acEnter(pickFunc){if(acItems.length<=0)return false;if(acIndex<0)acIndex=0;pickFunc(acItems[acIndex]);return true;}

function submitSave(allowEmpty){if(byId("allow_empty_detail")) byId("allow_empty_detail").value=allowEmpty?"1":"0";var f=byId("rcvForm");if(f) f.submit();}
function newData(){window.location.href="<?php echo $urlBase; ?>";}
function goEdit(id){if(!id)return;window.location.href="<?php echo $urlBase; ?>edit="+encodeURIComponent(id);}
function deleteCurrent(){var id=byId("rcv_id").value;if(id==""||id=="0"){alert("Pilih Receive dulu.");return;}if(!confirm("Yakin hapus Receive ini?"))return;byId("delete_rcv_id").value=id;byId("deleteForm").submit();}
function headerKey(e){if(e.key==="Enter"){if(acMode!==""&&acItems.length>0)return true;e.preventDefault();submitSave(false);return false;}}
function fieldEnterSave(e){if(e.key==="Enter"){e.preventDefault();submitSave(false);return false;}}

function selectDetailRow(tr){var rows=document.querySelectorAll("#detailBody tr");for(var i=0;i<rows.length;i++) rows[i].classList.remove("detail-selected");selectedDetailRow=tr;if(tr)tr.classList.add("detail-selected");}

// Supplier Autocomplete
function showSupplierAC(input){initAC();var key=(input.value||"").toUpperCase();acMode="supplier";acItems=[];acIndex=-1;if(key.length<1){hideAC();return;}for(var i=0;i<supplierData.length;i++){var s=supplierData[i];var t=(s.SUP_CODE||"")+" "+(s.SUP_COMP||"");if(t.toUpperCase().indexOf(key)>=0)acItems.push(s);if(acItems.length>=40)break;}positionAC(input);renderAC(function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;},pickSupplier);}
function supplierKey(e,input){if(e.key==="ArrowDown"){e.preventDefault();if(acMode!=="supplier"||acItems.length==0)showSupplierAC(input);acMove(1,function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;},pickSupplier);return false;}if(e.key==="ArrowUp"){e.preventDefault();if(acMode!=="supplier"||acItems.length==0)showSupplierAC(input);acMove(-1,function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;},pickSupplier);return false;}if(e.key==="Enter"){if(acMode==="supplier"&&acItems.length>0){e.preventDefault();acEnter(pickSupplier);return false;}e.preventDefault();submitSave(false);return false;}if(e.key==="Escape")hideAC();}
function pickSupplier(s){setValue("sup_id",s.SUP_ID);setValue("sup_code",s.SUP_CODE);setValue("sup_comp",s.SUP_COMP);hideAC();}

// PIC / Dept Autocomplete
function showPicAC(input){initAC();var key=(input.value||"").toUpperCase();acMode="pic";acItems=[];acIndex=-1;if(key.length<1){hideAC();return;}for(var i=0;i<deptData.length;i++){var d=deptData[i];if((d.DEP_NAME||"").toUpperCase().indexOf(key)>=0)acItems.push(d);if(acItems.length>=40)break;}positionAC(input);renderAC(function(d){return d.DEP_NAME;},pickPic);}
function picKey(e,input){if(e.key==="ArrowDown"){e.preventDefault();if(acMode!=="pic"||acItems.length==0)showPicAC(input);acMove(1,function(d){return d.DEP_NAME;},pickPic);return false;}if(e.key==="ArrowUp"){e.preventDefault();if(acMode!=="pic"||acItems.length==0)showPicAC(input);acMove(-1,function(d){return d.DEP_NAME;},pickPic);return false;}if(e.key==="Enter"){if(acMode==="pic"&&acItems.length>0){e.preventDefault();acEnter(pickPic);return false;}e.preventDefault();return false;}if(e.key==="Escape")hideAC();}
function pickPic(d){setValue("rcv_pic",d.DEP_NAME);hideAC();}

function showReceiveSearchAC(input) {
    initAC(); var key = (input.value || "").toUpperCase();
    acMode = "rcv_search"; acItems = []; acIndex = -1;
    if(key.length < 1) { hideAC(); return; }

    var xhr = new XMLHttpRequest();
    // PERBAIKAN: Tembak langsung ke file search_receive.php agar responnya dijamin berupa JSON
    xhr.open("GET", "search_receive.php?q=" + enc(key), true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if(res.success) {
                    acItems = res.rows || [];
                    positionAC(input);
                    renderAC(function(r) { return "<b>" + htmlEncode(r.RCV_NO) + "</b> - DO: " + htmlEncode(r.RCV_DONO) + "<div class='autocomplete-sub'>Sup: " + htmlEncode(r.SUP_COMP) + "</div>"; }, pickReceiveSearch);
                } else hideAC();
            } catch(e) { hideAC(); }
        }
    };
    xhr.send(null);
}

function receiveSearchKey(e, input) {
    if(e.key==="ArrowDown"){ e.preventDefault(); if(acMode!=="rcv_search"||acItems.length==0) showReceiveSearchAC(input); acMove(1, function(r){return "<b>"+htmlEncode(r.RCV_NO)+"</b> - DO: "+htmlEncode(r.RCV_DONO)+"<div class='autocomplete-sub'>Sup: "+htmlEncode(r.SUP_COMP)+"</div>";}, pickReceiveSearch); return false; }
    if(e.key==="ArrowUp"){ e.preventDefault(); if(acMode!=="rcv_search"||acItems.length==0) showReceiveSearchAC(input); acMove(-1, function(r){return "<b>"+htmlEncode(r.RCV_NO)+"</b> - DO: "+htmlEncode(r.RCV_DONO)+"<div class='autocomplete-sub'>Sup: "+htmlEncode(r.SUP_COMP)+"</div>";}, pickReceiveSearch); return false; }
    if(e.key==="Enter"){ if(acMode==="rcv_search"&&acItems.length>0){ e.preventDefault(); acEnter(pickReceiveSearch); return false; } return true; }
    if(e.key==="Escape") hideAC();
}

function pickReceiveSearch(r) {
    hideAC();
    // Langsung redirect ke mode edit RCV_ID yang diklik
    window.location.href = "<?php echo $urlBase; ?>edit=" + enc(r.RCV_ID);
}

// PO Item Autocomplete (AJAX Request)
function showPOItemAC(input, row) {
    initAC();
    var supId = byId("sup_id").value;
    if(!supId || supId == "0") { alert("Pilih supplier dulu."); input.value = ""; return; }
    
    var key = (input.value || "").toUpperCase();
    acMode = "poitem"; acRow = row; acItems = []; acIndex = -1;
    if(key.length < 1){ hideAC(); return; }

    var xhr = new XMLHttpRequest();
    xhr.open("POST", "search_po_item.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            acItems = JSON.parse(xhr.responseText);
            positionAC(input);
            renderAC(function(o){ return "<b>"+o.ITEM_CODE+"</b> - "+o.ITEM_NAME+" | PO: "+o.PO_NUM+" | O/S: "+o.OS_QTY; }, pickPOItem);
        }
    };
    xhr.send("q=" + encodeURIComponent(key) + "&sup_id=" + encodeURIComponent(supId));
}

function poItemKey(e,input,row){if(e.key==="ArrowDown"){e.preventDefault();if(acMode!=="poitem"||acItems.length==0)showPOItemAC(input,row);acMove(1,function(o){return "<b>"+o.ITEM_CODE+"</b> - "+o.ITEM_NAME+" | PO: "+o.PO_NUM+" | O/S: "+o.OS_QTY;},pickPOItem);return false;}if(e.key==="ArrowUp"){e.preventDefault();if(acMode!=="poitem"||acItems.length==0)showPOItemAC(input,row);acMove(-1,function(o){return "<b>"+o.ITEM_CODE+"</b> - "+o.ITEM_NAME+" | PO: "+o.PO_NUM+" | O/S: "+o.OS_QTY;},pickPOItem);return false;}if(e.key==="Enter"){if(acMode==="poitem"&&acItems.length>0){e.preventDefault();acEnter(pickPOItem);return false;}e.preventDefault();return false;}if(e.key==="Escape")hideAC();}
function pickPOItem(o){var row=acRow;setValue("item_id_"+row,o.ITEM_ID);setValue("po_id_"+row,o.PO_ID);setValue("item_code_"+row,o.ITEM_CODE);setValue("item_name_"+row,o.ITEM_NAME);setValue("po_num_"+row,o.PO_NUM);setValue("rcvd_qty_"+row,o.OS_QTY);setValue("pod_price_"+row,o.POD_PRICE);hideAC();var q=byId("rcvd_qty_"+row);if(q){q.focus();q.select();}}

function addRow(){var tbody=byId("detailBody");var row=rowSeq;rowSeq++;var tr=document.createElement("tr");tr.onclick=function(){selectDetailRow(this);};tr.innerHTML='<td class="center row-no"></td><td><input type="hidden" name="item_id[]" id="item_id_'+row+'"><input type="hidden" name="po_id[]" id="po_id_'+row+'"><input type="text" name="item_code[]" id="item_code_'+row+'" autocomplete="off" oninput="showPOItemAC(this, '+row+')" onkeydown="poItemKey(event, this, '+row+')"></td><td><input type="text" name="item_name[]" id="item_name_'+row+'" readonly style="background:#f9f9f9"></td><td><input type="text" name="rcvd_qty[]" id="rcvd_qty_'+row+'" class="num" onkeydown="fieldEnterSave(event, '+row+')"></td><td><input type="text" name="pod_price[]" id="pod_price_'+row+'" class="num" readonly style="background:#f9f9f9"></td><td><input type="text" name="po_num[]" id="po_num_'+row+'" readonly style="background:#f9f9f9"></td><td class="center"><button type="button" class="btn btn-danger btn-xs" onclick="deleteRow(this); event.stopPropagation();">X</button></td>';tbody.appendChild(tr);renumberRows();selectDetailRow(tr);byId("item_code_"+row).focus();}
function deleteRow(btn){var tr=btn.parentNode.parentNode.parentNode;if(!confirm("Hapus baris detail ini?"))return;tr.parentNode.removeChild(tr);renumberRows();var id=byId("rcv_id").value;if(id!=""&&id!="0")submitSave(true);}
function renumberRows(){var rows=byId("detailBody").getElementsByTagName("tr");for(var i=0;i<rows.length;i++){var c=rows[i].getElementsByClassName("row-no")[0];if(c)c.innerHTML=i+1;else rows[i].cells[0].innerHTML=i+1;}}

// Trigger & Action Custom Buttons
function triggerAction(actionName) {
    if(!confirm("Jalankan aksi " + actionName.toUpperCase() + "?")) return;
    byId("exec_action").value = actionName;
    byId("execForm").submit();
}

function triggerUpdatePrice() {
    var rcvId = byId("rcv_id").value;
    if(!rcvId || rcvId == "0") { alert("Pilih / simpan Receive terlebih dahulu."); return; }
    
    var tr = selectedDetailRow;
    if(!tr){
        var rows=byId("detailBody").getElementsByTagName("tr");
        if(rows.length>0) tr=rows[0];
    }
    if(!tr) { alert("Pilih baris detail item dulu."); return; }
    
    var itemInput = tr.querySelector('input[name="item_id[]"]');
    var poInput = tr.querySelector('input[name="po_id[]"]');
    var itemId = itemInput ? itemInput.value : "";
    var poId = poInput ? poInput.value : "";
    
    if(!itemId || !poId) { alert("Pilih detail item yang valid."); return; }
    
    if(confirm("Jalankan Update Price untuk item yang dipilih?")) {
        byId("exec_action").value = "update_price";
        byId("upd_rcv_id").value = rcvId;
        byId("upd_item_id").value = itemId;
        byId("upd_po_id").value = poId;
        byId("execForm").submit();
    }
}

// Aksi Tombol Cetak ICL
function cetakICL() {
    var rcvId = byId("rcv_id").value;
    if (!rcvId || rcvId == "0") { alert("Simpan atau pilih data Receive terlebih dahulu!"); return; }
    window.open("print_icl.php?id=" + encodeURIComponent(rcvId), "CETAK_ICL", "width=900,height=700,scrollbars=yes");
}

// Aksi Tombol ICL OTO
function cetakICLOto() {
    var rcvId = byId("rcv_id").value;
    if (!rcvId || rcvId == "0") { alert("Simpan atau pilih data Receive terlebih dahulu!"); return; }
    window.open("print_icl_oto.php?id=" + encodeURIComponent(rcvId), "CETAK_OTO", "width=900,height=700,scrollbars=yes");
}

// Aksi Tombol Sch Material
function cetakSchedule() {
    // FIX: Menggunakan name='rcv_no' alih-alih class 'rcv-no' agar aman
    var rcvNoInput = document.querySelector('input[name="rcv_no"]');
    if (!rcvNoInput || !rcvNoInput.value) { alert("Simpan atau pilih data Receive terlebih dahulu!"); return; }
    var shortNo = rcvNoInput.value.substring(0, 6);
    window.open("print_schedule.php?no=" + encodeURIComponent(shortNo), "CETAK_SCH", "width=900,height=700,scrollbars=yes");
}

// Aksi Tombol SET NEXT ICL
function setNextICL() {
    var nextNum = prompt("Masukkan nilai antrian untuk ICL selanjutnya:", "");
    if (nextNum !== null && nextNum.trim() !== "") {
        if (isNaN(nextNum)) {
            alert("Format harus berupa angka!");
            return;
        }
        byId("exec_action").value = "set_next_icl";
        byId("upd_rcv_id").value = nextNum; 
        byId("execForm").submit();
    }
}

document.addEventListener("DOMContentLoaded",function(){var rows=document.querySelectorAll("#detailBody tr");for(var i=0;i<rows.length;i++){rows[i].onclick=function(){selectDetailRow(this);};}});
document.addEventListener("click",function(e){initAC();var searchList = byId("searchReceiveList"); if(acBox&&!acBox.contains(e.target)){if(!e.target||!e.target.getAttribute||e.target.getAttribute("autocomplete")!=="off")hideAC();} if(searchList&&!searchList.contains(e.target)&&e.target.id!=="searchReceive") searchList.style.display="none"; });
</script>
</body>
</html>
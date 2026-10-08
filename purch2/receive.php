<?php
if (session_id() == "") session_start();

require_once dirname(__DIR__) . "/config/db_plant2.php";
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
    $ret = array("NEXT_PR"=>1, "PR_NUM"=>"%s");
    $stmt = @sqlsrv_query($conn, "SELECT TOP 1 NEXT_PR, PR_NUM FROM dbo.REFS WHERE REF_ID = 1");
    if ($stmt !== false) {
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($r) {
            if (isset($r["NEXT_PR"])) $ret["NEXT_PR"] = intval($r["NEXT_PR"]);
            if (isset($r["PR_NUM"]) && trim((string)$r["PR_NUM"]) !== "") $ret["PR_NUM"] = trim((string)$r["PR_NUM"]);
        }
    }
    return trim(sprintf($ret["PR_NUM"], str_pad($ret["NEXT_PR"], 6, "0", STR_PAD_LEFT)));
}

function inc_next_rcv_no($conn) {
    @sqlsrv_query($conn, "UPDATE TOP (1) dbo.REFS SET NEXT_PR = ISNULL(NEXT_PR, 0) + 1 WHERE REF_ID = 1");
}

$message = "";
$error = "";
$action = postv("action","");
$editId = intval(getv("edit","0"));

/* ======================================================
   AKSI SPESIFIK: SET NEXT ICL, TRIGGER ON/OFF, UPDATE PRICE 
====================================================== */
if ($action == "set_next_icl") {
    $nextVal = intval(postv("upd_rcv_id", "0")); 
    if ($nextVal > 0) {
        $sqlSet = "UPDATE dbo.REFS SET NEXT_PR = ? WHERE REF_ID = 1";
        if (sqlsrv_query($conn, $sqlSet, array($nextVal))) {
            $message = "SET NEXT ICL BERHASIL diubah menjadi: " . $nextVal;
        } else {
            $error = "Gagal mengubah NEXT ICL:\n" . sql_error_text();
        }
    }
}

if ($action == "trigger_off") {
    $sqlOff = "BEGIN 
               ALTER TABLE [dbo].[receive_detail] DISABLE TRIGGER add_inv_recvd; 
               ALTER TABLE [dbo].[receive_detail] DISABLE TRIGGER del_inv_recvd; 
               ALTER TABLE [dbo].[receive_detail] DISABLE TRIGGER upd_inv_recvd; 
               END";
    if (sqlsrv_query($conn, $sqlOff)) $message = "TRIGGER OFF BERHASIL";
    else $error = "Gagal mematikan trigger:\n".sql_error_text();
}

if ($action == "trigger_on") {
    $sqlOn = "BEGIN 
              ALTER TABLE [dbo].[receive_detail] ENABLE TRIGGER add_inv_recvd; 
              ALTER TABLE [dbo].[receive_detail] ENABLE TRIGGER del_inv_recvd; 
              ALTER TABLE [dbo].[receive_detail] ENABLE TRIGGER upd_inv_recvd; 
              END";
    if (sqlsrv_query($conn, $sqlOn)) $message = "TRIGGER ON BERHASIL";
    else $error = "Gagal menyalakan trigger:\n".sql_error_text();
}

if ($action == "update_price") {
    $updRcvId = intval(postv("upd_rcv_id", "0"));
    $updItemId = intval(postv("upd_item_id", "0"));
    $updPoId = intval(postv("upd_po_id", "0"));
    
    $stmtUpd = sqlsrv_query($conn, "EXEC sp_update_price_rec ?, ?, ?", array($updRcvId, $updItemId, $updPoId));
    if ($stmtUpd === false) $error = "Update Price Gagal:\n".sql_error_text();
    else $message = "UPDATE PRICE SELESAI";
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
                    header("Location: receive.php?msg=deleted"); exit;
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
    $rcvId = intval(postv("rcv_id","0"));
    $rcvNo = strtoupper(postv("rcv_no",""));
    $rcvDate = postv("rcv_date",date("Y-m-d"));
    $rcvDono = postv("rcv_dono","");
    $supId = intval(postv("sup_id","0"));
    $rcvPic = postv("rcv_pic","");
    $rcvType = postv("rcv_type","1");
    
    $jenisBc = postv("jenis_bc","");
    $nomorBc = postv("nomor_bc","");
    $bcDate = postv("bc_date",date("Y-m-d"));

    $allowEmpty = intval(postv("allow_empty_detail","0"));

    if ($rcvDate == "") $rcvDate = date("Y-m-d");

    if ($rcvNo == "") {
        $rcvNo = generate_rcv_no($conn);
    }

    $isNewRecord = ($rcvId == 0);

    if ($supId <= 0) $error = "Supplier wajib dipilih.";

    if ($error == "") {
        $itemIds = isset($_POST["item_id"]) ? $_POST["item_id"] : array();
        $poIds = isset($_POST["po_id"]) ? $_POST["po_id"] : array();
        $qtys = isset($_POST["rcvd_qty"]) ? $_POST["rcvd_qty"] : array();
        $prices = isset($_POST["pod_price"]) ? $_POST["pod_price"] : array();

        $hasDetail = false;
        for ($i=0; $i<count($itemIds); $i++) {
            $itemId = intval($itemIds[$i]);
            $qty = isset($qtys[$i]) ? to_float($qtys[$i]) : 0;
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
                    if ($new && isset($new['NEW_ID'])) {
                        $rcvId = intval($new['NEW_ID']);
                    } else { 
                        if (sqlsrv_next_result($stmtH)) {
                            $new = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
                            if ($new && isset($new['NEW_ID'])) {
                                $rcvId = intval($new['NEW_ID']);
                            } else {
                                $ok=false; $error="RCV_ID baru tidak terbaca (Next Result).";
                            }
                        } else {
                            $ok=false; $error="RCV_ID baru tidak terbaca."; 
                        }
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
                    $itemId = intval($itemIds[$i]);
                    $poId = intval($poIds[$i]);
                    $qty = isset($qtys[$i]) ? to_float($qtys[$i]) : 0;
                    $price = isset($prices[$i]) ? to_float($prices[$i]) : 0;

                    if ($itemId <= 0 || $poId <= 0 || $qty <= 0) continue;

                    $sqlD = "INSERT INTO dbo.RECEIVE_DETAIL (RCV_ID, ITEM_ID, PO_ID, RCVD_QTY, POD_PRICE) VALUES (?, ?, ?, ?, ?)";
                    $stmtD = sqlsrv_query($conn, $sqlD, array($rcvId, $itemId, $poId, $qty, $price));
                    if ($stmtD === false) { $ok=false; $error="Simpan detail gagal:\n".sql_error_text(); break; }
                }
            }

            if ($ok) {
                sqlsrv_commit($conn);
                if ($isNewRecord) inc_next_rcv_no($conn); 
                header("Location: receive.php?edit=".$rcvId."&msg=saved");
                exit;
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
   SUPPLIER & DEPT DATA (Untuk Autocomplete)
====================================================== */
$supplierAuto = array();
$sqlSup = "SELECT TOP 1000 SUP_ID, SUP_CODE, SUP_COMP FROM dbo.SUPPLIER WHERE ISNULL(SUP_CODE,'') <> '' ORDER BY SUP_CODE";
$stmtSup = sqlsrv_query($conn,$sqlSup);
if ($stmtSup !== false) {
    while ($s = sqlsrv_fetch_array($stmtSup, SQLSRV_FETCH_ASSOC)) {
        $supplierAuto[] = array("SUP_ID"=>intval($s["SUP_ID"]), "SUP_CODE"=>trim((string)$s["SUP_CODE"]), "SUP_COMP"=>trim((string)$s["SUP_COMP"]));
    }
}

$deptAuto = array();
$stmtDept = sqlsrv_query($conn,"SELECT DEP_ID, DEP_CODE, DEP_NAME FROM dbo.DEPT ORDER BY DEP_CODE");
if ($stmtDept !== false) {
    while ($d = sqlsrv_fetch_array($stmtDept, SQLSRV_FETCH_ASSOC)) {
        $deptAuto[] = array("DEP_NAME"=>trim((string)$d["DEP_NAME"]));
    }
}

$bcList = array();
$stmtBc = sqlsrv_query($conn,"SELECT BCTY_NAME FROM dbo.BCTY");
if ($stmtBc !== false) {
    while ($b = sqlsrv_fetch_array($stmtBc, SQLSRV_FETCH_ASSOC)) $bcList[] = trim((string)$b["BCTY_NAME"]);
}

/* ======================================================
   RECEIVE AUTO COMPLETE DATA
====================================================== */
$rcvAuto = array();
$sqlRcvAuto = "
    SELECT TOP 1000 R.RCV_ID, R.RCV_NO, R.RCV_DONO, S.SUP_CODE, S.SUP_COMP
    FROM dbo.RECEIVE R
    LEFT JOIN dbo.SUPPLIER S ON R.SUP_ID = S.SUP_ID
    ORDER BY R.RCV_DATE DESC, R.RCV_NO DESC
";
$stmtRcvAuto = sqlsrv_query($conn, $sqlRcvAuto);
if ($stmtRcvAuto !== false) {
    while ($ra = sqlsrv_fetch_array($stmtRcvAuto, SQLSRV_FETCH_ASSOC)) {
        $rcvAuto[] = array(
            "RCV_ID" => intval($ra["RCV_ID"]),
            "RCV_NO" => trim((string)$ra["RCV_NO"]),
            "RCV_DONO" => trim((string)$ra["RCV_DONO"]),
            "SUP_CODE" => trim((string)$ra["SUP_CODE"]),
            "SUP_COMP" => trim((string)$ra["SUP_COMP"])
        );
    }
}


/* LIST RECEIVE */
$q = getv("q","");
$where = "";
$paramsList = array();
if ($q != "") {
    $where = "WHERE R.RCV_NO LIKE ? OR S.SUP_CODE LIKE ? OR S.SUP_COMP LIKE ? OR R.RCV_DONO LIKE ?";
    $paramsList = array("%".$q."%","%".$q."%","%".$q."%","%".$q."%");
}

$sqlList = "
    SELECT TOP 300 R.RCV_ID, R.RCV_NO, R.RCV_DATE, R.RCV_DONO, R.RCV_PIC, S.SUP_CODE, S.SUP_COMP,
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
    for ($i=0; $i<3; $i++) {
        $details[] = array("ITEM_ID"=>"","PO_ID"=>"","ITEM_CODE"=>"","ITEM_NAME"=>"","PO_NUM"=>"","RCVD_QTY"=>"","POD_PRICE"=>"");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase Receive - Purchasing</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Theme style (AdminLTE 3) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

    <style>
        .ac-box {
            position: absolute;
            z-index: 9999;
            background: #ffffff;
            color: #333;
            border: 1px solid #ccc;
            border-radius: 4px;
            max-height: 250px;
            overflow-y: auto;
            min-width: 360px;
            display: none;
            font-size: 14px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .ac-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f4f4f4;
        }
        .ac-item:last-child { border-bottom: none; }
        .ac-item:hover, .ac-item.active { background: #007bff; color: #ffffff; }

        /* CUSTOM COMPACT TABLE ROW */
        #detailTable th, #detailTable td {
            padding: 2px 4px !important;
            vertical-align: middle !important;
        }
        .table-input-transparent {
            border: 1px solid transparent;
            background: transparent;
            height: 22px !important;
            padding: 0px 4px !important;
            font-size: 12px !important;
            border-radius: 0;
        }
        .table-input-transparent:focus {
            background: #fff;
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        #detailTable .btn-xs {
            padding: 1px 6px !important;
            font-size: 11px !important;
            height: 22px !important;
            line-height: 1.5 !important;
        }
        tr.pointer:hover, tr.detail-selected td {
            cursor: pointer;
            background-color: #e7f1ff !important;
        }
    </style>

    <script>
        var supplierData = <?php echo json_encode($supplierAuto); ?>;
        var deptData = <?php echo json_encode($deptAuto); ?>;
        var rcvData = <?php echo json_encode($rcvAuto); ?>;
        
        var acBox=null, acItems=[], acIndex=-1, acMode="", acRow=-1;
        var rowSeq=<?php echo count($details); ?>;
        var selectedDetailRow=null;

        function byId(id){return document.getElementById(id);}
        function setValue(id,value){var el=byId(id); if(el) el.value=(value==null?"":value);}

        function initAC(){acBox=byId("acBox");}
        function hideAC(){
            if(acBox){acBox.style.display="none";acBox.innerHTML="";}
            acItems=[];acIndex=-1;acMode="";acRow=-1;
        }
        function positionAC(input){
            initAC();var r=input.getBoundingClientRect();
            acBox.style.left=(r.left+window.scrollX)+"px";
            acBox.style.top=(r.bottom+window.scrollY)+"px";
            acBox.style.width=(r.width<360?360:r.width)+"px";
        }
        function renderAC(renderText,pickFunc){
            initAC();acBox.innerHTML="";
            for(var i=0;i<acItems.length;i++){
                var d=document.createElement("div");
                d.className="ac-item"+(i==acIndex?" active":"");
                d.innerHTML=renderText(acItems[i]);
                d.setAttribute("data-index",i);
                d.onmousedown=function(){pickFunc(acItems[parseInt(this.getAttribute("data-index"),10)]);};
                acBox.appendChild(d);
            }
            acBox.style.display=acItems.length>0?"block":"none";
        }
        function acMove(step,renderText,pickFunc){
            if(acItems.length<=0)return;
            acIndex+=step;
            if(acIndex<0)acIndex=acItems.length-1;
            if(acIndex>=acItems.length)acIndex=0;
            renderAC(renderText,pickFunc);
        }
        function acEnter(pickFunc){
            if(acItems.length<=0)return false;
            if(acIndex<0)acIndex=0;
            pickFunc(acItems[acIndex]);
            return true;
        }

        function submitSave(allowEmpty){
            if(byId("allow_empty_detail")) byId("allow_empty_detail").value=allowEmpty?"1":"0";
            var f=byId("rcvForm");if(f) f.submit();
        }
        function newData(){window.location.href="receive.php";}
        function goEdit(id){if(!id)return;window.location.href="receive.php?edit="+encodeURIComponent(id);}
        function deleteCurrent(){
            var id=byId("rcv_id").value;
            if(id==""||id=="0"){alert("Pilih Receive dulu.");return;}
            if(!confirm("Yakin hapus Receive ini?"))return;
            byId("delete_rcv_id").value=id;byId("deleteForm").submit();
        }
        function headerKey(e){
            if(e.key==="Enter"){
                if(acMode!==""&&acItems.length>0)return true;
                e.preventDefault();submitSave(false);return false;
            }
        }
        function fieldEnterSave(e){
            if(e.key==="Enter"){e.preventDefault();submitSave(false);return false;}
        }

        function selectDetailRow(tr){
            var rows=document.querySelectorAll("#detailBody tr");
            for(var i=0;i<rows.length;i++) rows[i].classList.remove("detail-selected");
            selectedDetailRow=tr;
            if(tr)tr.classList.add("detail-selected");
        }

        // Receive Search AutoComplete (Header)
        function showRcvAC(input){
            initAC();
            var key=(input.value||"").toUpperCase();
            acMode="rcv"; acItems=[]; acIndex=-1;
            if(key.length<1){hideAC();return;}
            
            for(var i=0; i<rcvData.length; i++){
                var r = rcvData[i];
                var t = (r.RCV_NO||"")+" "+(r.RCV_DONO||"")+" "+(r.SUP_CODE||"")+" "+(r.SUP_COMP||"");
                if(t.toUpperCase().indexOf(key) >= 0) acItems.push(r);
                if(acItems.length >= 40) break;
            }
            positionAC(input);
            renderAC(function(r){
                return "<b>" + r.RCV_NO + "</b> - DO: " + r.RCV_DONO + "<br><small class='text-muted'>" + r.SUP_CODE + " - " + r.SUP_COMP + "</small>";
            }, pickRcv);
        }
        function rcvKey(e,input){
            if(e.key==="ArrowDown"){
                e.preventDefault(); if(acMode!=="rcv"||acItems.length==0) showRcvAC(input);
                acMove(1, function(r){ return "<b>" + r.RCV_NO + "</b> - DO: " + r.RCV_DONO + "<br><small class='text-muted'>" + r.SUP_CODE + " - " + r.SUP_COMP + "</small>"; }, pickRcv); return false;
            }
            if(e.key==="ArrowUp"){
                e.preventDefault(); if(acMode!=="rcv"||acItems.length==0) showRcvAC(input);
                acMove(-1, function(r){ return "<b>" + r.RCV_NO + "</b> - DO: " + r.RCV_DONO + "<br><small class='text-muted'>" + r.SUP_CODE + " - " + r.SUP_COMP + "</small>"; }, pickRcv); return false;
            }
            if(e.key==="Enter"){
                if(acMode==="rcv"&&acItems.length>0){e.preventDefault(); acEnter(pickRcv); return false;}
                return true; 
            }
            if(e.key==="Escape") hideAC();
        }
        function pickRcv(r){
            hideAC();
            goEdit(r.RCV_ID);
        }

        // Supplier Autocomplete
        function showSupplierAC(input){
            initAC();
            var key=(input.value||"").toUpperCase();
            acMode="supplier";acItems=[];acIndex=-1;
            if(key.length<1){hideAC();return;}
            for(var i=0;i<supplierData.length;i++){
                var s=supplierData[i];var t=(s.SUP_CODE||"")+" "+(s.SUP_COMP||"");
                if(t.toUpperCase().indexOf(key)>=0)acItems.push(s);
                if(acItems.length>=40)break;
            }
            positionAC(input);
            renderAC(function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;},pickSupplier);
        }
        function supplierKey(e,input){
            if(e.key==="ArrowDown"){
                e.preventDefault();if(acMode!=="supplier"||acItems.length==0)showSupplierAC(input);
                acMove(1,function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;},pickSupplier);return false;
            }
            if(e.key==="ArrowUp"){
                e.preventDefault();if(acMode!=="supplier"||acItems.length==0)showSupplierAC(input);
                acMove(-1,function(s){return "<b>"+s.SUP_CODE+"</b> - "+s.SUP_COMP;},pickSupplier);return false;
            }
            if(e.key==="Enter"){
                if(acMode==="supplier"&&acItems.length>0){e.preventDefault();acEnter(pickSupplier);return false;}
                e.preventDefault();submitSave(false);return false;
            }
            if(e.key==="Escape")hideAC();
        }
        function pickSupplier(s){
            setValue("sup_id",s.SUP_ID);setValue("sup_code",s.SUP_CODE);setValue("sup_comp",s.SUP_COMP);hideAC();
        }

        // PIC / Dept Autocomplete
        function showPicAC(input){
            initAC();
            var key=(input.value||"").toUpperCase();
            acMode="pic";acItems=[];acIndex=-1;
            if(key.length<1){hideAC();return;}
            for(var i=0;i<deptData.length;i++){
                var d=deptData[i];
                if((d.DEP_NAME||"").toUpperCase().indexOf(key)>=0)acItems.push(d);
                if(acItems.length>=40)break;
            }
            positionAC(input);
            renderAC(function(d){return d.DEP_NAME;},pickPic);
        }
        function picKey(e,input){
            if(e.key==="ArrowDown"){
                e.preventDefault();if(acMode!=="pic"||acItems.length==0)showPicAC(input);
                acMove(1,function(d){return d.DEP_NAME;},pickPic);return false;
            }
            if(e.key==="ArrowUp"){
                e.preventDefault();if(acMode!=="pic"||acItems.length==0)showPicAC(input);
                acMove(-1,function(d){return d.DEP_NAME;},pickPic);return false;
            }
            if(e.key==="Enter"){
                if(acMode==="pic"&&acItems.length>0){e.preventDefault();acEnter(pickPic);return false;}
                e.preventDefault();return false;
            }
            if(e.key==="Escape")hideAC();
        }
        function pickPic(d){setValue("rcv_pic",d.DEP_NAME);hideAC();}

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

        function poItemKey(e,input,row){
            if(e.key==="ArrowDown"){
                e.preventDefault();if(acMode!=="poitem"||acItems.length==0)showPOItemAC(input,row);
                acMove(1,function(o){return "<b>"+o.ITEM_CODE+"</b> - "+o.ITEM_NAME+" | PO: "+o.PO_NUM+" | O/S: "+o.OS_QTY;},pickPOItem);return false;
            }
            if(e.key==="ArrowUp"){
                e.preventDefault();if(acMode!=="poitem"||acItems.length==0)showPOItemAC(input,row);
                acMove(-1,function(o){return "<b>"+o.ITEM_CODE+"</b> - "+o.ITEM_NAME+" | PO: "+o.PO_NUM+" | O/S: "+o.OS_QTY;},pickPOItem);return false;
            }
            if(e.key==="Enter"){
                if(acMode==="poitem"&&acItems.length>0){e.preventDefault();acEnter(pickPOItem);return false;}
                e.preventDefault();return false;
            }
            if(e.key==="Escape")hideAC();
        }
        function pickPOItem(o){
            var row=acRow;
            setValue("item_id_"+row,o.ITEM_ID);setValue("po_id_"+row,o.PO_ID);
            setValue("item_code_"+row,o.ITEM_CODE);setValue("item_name_"+row,o.ITEM_NAME);
            setValue("po_num_"+row,o.PO_NUM);setValue("rcvd_qty_"+row,o.OS_QTY);
            setValue("pod_price_"+row,o.POD_PRICE);
            hideAC();
            var q=byId("rcvd_qty_"+row);if(q){q.focus();q.select();}
        }

        function addRow(){
            var tbody=byId("detailBody");var row=rowSeq;rowSeq++;
            var tr=document.createElement("tr");
            tr.onclick=function(){selectDetailRow(this);};
            tr.innerHTML=
                '<td class="text-center align-middle row-no"></td>' +
                '<td>' +
                    '<input type="hidden" name="item_id[]" id="item_id_'+row+'">' +
                    '<input type="hidden" name="po_id[]" id="po_id_'+row+'">' +
                    '<input type="text" class="form-control form-control-sm table-input-transparent" name="item_code[]" id="item_code_'+row+'" autocomplete="off" oninput="showPOItemAC(this, '+row+')" onkeydown="poItemKey(event, this, '+row+')">' +
                '</td>' +
                '<td><input type="text" class="form-control form-control-sm table-input-transparent bg-light" name="item_name[]" id="item_name_'+row+'" readonly></td>' +
                '<td><input type="text" class="form-control form-control-sm table-input-transparent text-right" name="rcvd_qty[]" id="rcvd_qty_'+row+'" onkeydown="fieldEnterSave(event)"></td>' +
                '<td><input type="text" class="form-control form-control-sm table-input-transparent text-right bg-light" name="pod_price[]" id="pod_price_'+row+'" readonly></td>' +
                '<td><input type="text" class="form-control form-control-sm table-input-transparent bg-light" name="po_num[]" id="po_num_'+row+'" readonly></td>' +
                '<td class="text-center"><button type="button" class="btn btn-danger btn-xs" onclick="deleteRow(this); event.stopPropagation();" title="Delete"><i class="fas fa-times"></i></button></td>';

            tbody.appendChild(tr);
            renumberRows();selectDetailRow(tr);byId("item_code_"+row).focus();
        }

        function deleteRow(btn){
            var tr=btn.closest("tr");
            if(!confirm("Hapus baris detail ini?"))return;
            tr.parentNode.removeChild(tr);renumberRows();
            var id=byId("rcv_id").value;if(id!=""&&id!="0")submitSave(true);
        }
        function renumberRows(){
            var rows=byId("detailBody").getElementsByTagName("tr");
            for(var i=0;i<rows.length;i++){
                var c=rows[i].getElementsByClassName("row-no")[0];
                if(c)c.innerHTML=i+1;
            }
        }

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
            var rcvNo = document.querySelector('.rcv-no').value;
            if (!rcvNo) { alert("Simpan atau pilih data Receive terlebih dahulu!"); return; }
            var shortNo = rcvNo.substring(0, 6);
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

        document.addEventListener("DOMContentLoaded",function(){
            var rows=document.querySelectorAll("#detailBody tr");
            for(var i=0;i<rows.length;i++){rows[i].onclick=function(){selectDetailRow(this);};}
        });
        document.addEventListener("click",function(e){
            initAC();if(acBox&&!acBox.contains(e.target)){if(!e.target||!e.target.getAttribute||e.target.getAttribute("autocomplete")!=="off")hideAC();}
        });
    </script>
</head>
<body class="hold-transition layout-top-nav">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand-md navbar-light navbar-white">
        <div class="container-fluid">
            <a href="dashboard_home.php" class="navbar-brand">
                <span class="brand-text font-weight-light"><i class="fas fa-truck-loading text-primary mr-2"></i> Purchasing System</span>
            </a>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a href="dashboard_home.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Close</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Purchase Receive</h1>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">

                <?php if ($message != "") { ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <h5><i class="icon fas fa-check"></i> Success!</h5>
                        <?php echo h($message); ?>
                    </div>
                <?php } ?>

                <?php if ($error != "") { ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <h5><i class="icon fas fa-ban"></i> Error!</h5>
                        <?php echo h($error); ?>
                    </div>
                <?php } ?>

                <div class="card card-primary card-outline">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">
                            <button type="button" class="btn btn-sm btn-default mr-1" onclick="newData()"><i class="fas fa-file"></i> New</button>
                            <button type="button" class="btn btn-sm btn-success mr-1" onclick="submitSave(false)"><i class="fas fa-save"></i> Save Receive</button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteCurrent()"><i class="fas fa-trash"></i> Delete</button>
                        </h3>
                        
                        <div class="card-tools">
                            <form method="get" action="receive.php" class="form-inline m-0" id="searchFormTop">
                                <div class="input-group input-group-sm" style="width: 300px;">
                                    <input type="text" name="q" class="form-control float-right" value="<?php echo h($q); ?>" placeholder="Search RCV No / DO / Sup" autocomplete="off" oninput="showRcvAC(this)" onkeydown="rcvKey(event, this)">
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-default"><i class="fas fa-search"></i></button>
                                        <a href="receive.php" class="btn btn-default" title="Reset"><i class="fas fa-sync"></i></a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <form id="rcvForm" method="post" action="receive.php">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="rcv_id" id="rcv_id" value="<?php echo h($header["RCV_ID"]); ?>">
                        <input type="hidden" name="sup_id" id="sup_id" value="<?php echo h($header["SUP_ID"]); ?>">
                        <input type="hidden" name="allow_empty_detail" id="allow_empty_detail" value="0">

                        <div class="card-body">
                            <!-- Panel 1: Receive Header -->
                            <div class="p-3 rounded mb-3" style="background-color: #008080;">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="text-warning">I.C.L No.</label>
                                            <input type="text" class="form-control form-control-sm rcv-no" name="rcv_no" value="<?php echo h($header["RCV_NO"]); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="text-warning">Date</label>
                                            <input type="date" class="form-control form-control-sm" name="rcv_date" value="<?php echo h($header["RCV_DATE"]); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="text-warning">D.O #</label>
                                            <input type="text" class="form-control form-control-sm" name="rcv_dono" value="<?php echo h($header["RCV_DONO"]); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label class="text-warning">Supplier</label>
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control" style="max-width:100px;" name="sup_code" id="sup_code" value="<?php echo h($header["SUP_CODE"]); ?>" autocomplete="off" oninput="showSupplierAC(this)" onkeydown="supplierKey(event, this)">
                                                <input type="text" class="form-control bg-light" name="sup_comp" id="sup_comp" value="<?php echo h($header["SUP_COMP"]); ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="text-warning">PIC</label>
                                            <input type="text" class="form-control form-control-sm" name="rcv_pic" id="rcv_pic" value="<?php echo h($header["RCV_PIC"]); ?>" autocomplete="off" oninput="showPicAC(this)" onkeydown="picKey(event, this)">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="text-warning">TYPE</label>
                                            <select class="form-control form-control-sm" name="rcv_type">
                                                <option value="1" <?php echo $header["RCV_TYPE"]=="1"?"selected":""; ?>>Material - Supplier</option>
                                                <option value="2" <?php echo $header["RCV_TYPE"]=="2"?"selected":""; ?>>Spare Part - Supplier</option>
                                                <option value="3" <?php echo $header["RCV_TYPE"]=="3"?"selected":""; ?>>Part - Vendor</option>
                                                <option value="4" <?php echo $header["RCV_TYPE"]=="4"?"selected":""; ?>>Others</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Panel 2: Bea Cukai -->
                            <div class="p-3 rounded mb-3" style="background-color: #008000;">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group mb-0">
                                            <label class="text-warning">Jenis BC</label>
                                            <select class="form-control form-control-sm" name="jenis_bc">
                                                <option value="">- Pilih BC -</option>
                                                <?php foreach($bcList as $b) { ?>
                                                    <option value="<?php echo h($b); ?>" <?php echo $header["JENIS_BC"]==$b?"selected":""; ?>><?php echo h($b); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-0">
                                            <label class="text-warning">Nomor BC</label>
                                            <input type="text" class="form-control form-control-sm" name="nomor_bc" value="<?php echo h($header["NOMOR_BC"]); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-0">
                                            <label class="text-warning">Tanggal BC</label>
                                            <input type="date" class="form-control form-control-sm" name="bc_date" value="<?php echo h($header["BC_DATE"]); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary mr-1" onclick="cetakICL()"><i class="fas fa-print"></i> Cetak ICL</button>
                                <button type="button" class="btn btn-sm btn-outline-primary mr-1" onclick="cetakICLOto()"><i class="fas fa-print"></i> ICL OTO</button>
                                <button type="button" class="btn btn-sm btn-outline-primary mr-1" onclick="cetakSchedule()"><i class="fas fa-calendar-alt"></i> Sch Material</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setNextICL()"><i class="fas fa-cogs"></i> SET NEXT ICL</button>
                            </div>

                            <div class="table-responsive p-0 border mt-3">
                                <table class="table table-bordered table-sm text-sm m-0" id="detailTable">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="text-center" style="width:40px;">No</th>
                                            <th style="width:140px;">Item Code</th>
                                            <th>Item Name</th>
                                            <th style="width:100px;">Recv Qty</th>
                                            <th style="width:120px;">Price</th>
                                            <th style="width:150px;">PO #</th>
                                            <th class="text-center" style="width:50px;">ACT</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detailBody">
                                        <?php for($i=0;$i<count($details);$i++){ 
                                            $d=$details[$i];
                                            $qty=isset($d["RCVD_QTY"])?floatval($d["RCVD_QTY"]):0;
                                            $price=isset($d["POD_PRICE"])?floatval($d["POD_PRICE"]):0;
                                        ?>
                                            <tr>
                                                <td class="text-center align-middle row-no"><?php echo h($i+1); ?></td>
                                                <td>
                                                    <input type="hidden" name="item_id[]" id="item_id_<?php echo h($i); ?>" value="<?php echo h($d["ITEM_ID"]); ?>">
                                                    <input type="hidden" name="po_id[]" id="po_id_<?php echo h($i); ?>" value="<?php echo h($d["PO_ID"]); ?>">
                                                    <input type="text" class="form-control form-control-sm table-input-transparent" name="item_code[]" id="item_code_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_CODE"])?$d["ITEM_CODE"]:""); ?>" autocomplete="off" oninput="showPOItemAC(this, <?php echo h($i); ?>)" onkeydown="poItemKey(event, this, <?php echo h($i); ?>)">
                                                </td>
                                                <td><input type="text" class="form-control form-control-sm table-input-transparent bg-light" name="item_name[]" id="item_name_<?php echo h($i); ?>" value="<?php echo h(isset($d["ITEM_NAME"])?$d["ITEM_NAME"]:""); ?>" readonly></td>
                                                <td><input type="text" class="form-control form-control-sm table-input-transparent text-right" name="rcvd_qty[]" id="rcvd_qty_<?php echo h($i); ?>" value="<?php echo h($qty==0?"":$qty); ?>" onkeydown="fieldEnterSave(event)"></td>
                                                <td><input type="text" class="form-control form-control-sm table-input-transparent text-right bg-light" name="pod_price[]" id="pod_price_<?php echo h($i); ?>" value="<?php echo h($price==0?"":$price); ?>" readonly></td>
                                                <td><input type="text" class="form-control form-control-sm table-input-transparent bg-light" name="po_num[]" id="po_num_<?php echo h($i); ?>" value="<?php echo h(isset($d["PO_NUM"])?$d["PO_NUM"]:""); ?>" readonly></td>
                                                <td class="text-center"><button type="button" class="btn btn-danger btn-xs" onclick="deleteRow(this); event.stopPropagation();" title="Delete"><i class="fas fa-times"></i></button></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                            <div>
                                <button type="button" class="btn btn-primary btn-sm" onclick="addRow()"><i class="fas fa-plus"></i> Tambah Baris</button>
                            </div>
                            <div class="text-right">
                                <div class="mb-1">
                                    <button type="button" class="btn btn-sm btn-danger mr-1" onclick="triggerAction('trigger_off')">OFF</button>
                                    <button type="button" class="btn btn-sm btn-warning mr-1" onclick="triggerUpdatePrice()">UPDATE</button>
                                    <button type="button" class="btn btn-sm btn-success" onclick="triggerAction('trigger_on')">ON</button>
                                </div>
                                <span class="text-danger font-weight-bold border border-danger px-2 py-1 bg-white d-inline-block rounded" style="font-size: 11px;">
                                    JANGAN LUPA KLIK TOMBOL ON LAGI SETELAH KLIK TOMBOL OFF
                                </span>
                            </div>
                        </div>
                    </form>
                </div>

                <form id="deleteForm" method="post" action="receive.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="rcv_id" id="delete_rcv_id" value="">
                </form>

                <form id="execForm" method="post" action="receive.php">
                    <input type="hidden" name="action" id="exec_action" value="">
                    <input type="hidden" name="upd_rcv_id" id="upd_rcv_id" value="">
                    <input type="hidden" name="upd_item_id" id="upd_item_id" value="">
                    <input type="hidden" name="upd_po_id" id="upd_po_id" value="">
                    <input type="hidden" name="edit" value="<?php echo h($editId); ?>">
                </form>

                <div class="card">
                    <div class="card-body p-0 table-responsive" style="max-height: 400px;">
                        <table class="table table-striped table-hover table-head-fixed text-nowrap table-sm text-sm">
                            <thead>
                                <tr>
                                    <th style="width:30px;"></th>
                                    <th>RCV NO</th>
                                    <th>Date</th>
                                    <th>DO #</th>
                                    <th>PIC</th>
                                    <th>Sup.Code</th>
                                    <th>Supplier</th>
                                    <th class="text-right">Detail</th>
                                </tr>
                            </thead>
                            <tbody id="rcvListBody">
                                <?php while($r=sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)){ ?>
                                    <?php $rcvIdLink=intval($r["RCV_ID"]); ?>
                                    <tr class="pointer po-row" onclick="goEdit('<?php echo h($rcvIdLink); ?>')">
                                        <td class="text-center text-primary"><i class="fas fa-caret-right"></i></td>
                                        <td class="font-weight-bold text-dark"><?php echo h($r["RCV_NO"]); ?></td>
                                        <td><?php echo h(fmt_date_view($r["RCV_DATE"])); ?></td>
                                        <td><?php echo h($r["RCV_DONO"]); ?></td>
                                        <td><?php echo h($r["RCV_PIC"]); ?></td>
                                        <td><?php echo h($r["SUP_CODE"]); ?></td>
                                        <td><?php echo h($r["SUP_COMP"]); ?></td>
                                        <td class="text-right"><?php echo h(number_format(floatval($r["DETAIL_COUNT"]),0,".",",")); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div id="acBox" class="ac-box"></div>

<!-- jQuery -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>